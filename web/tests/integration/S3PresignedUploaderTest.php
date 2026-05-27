<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under the Elastic License 2.0.
// See LICENSE.txt for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

declare(strict_types=1);

namespace Sbpp\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Sbpp\Export\ExportError;
use Sbpp\Export\S3PresignedUploader;

/**
 * Wire contract pins for the S3 presigned PUT uploader.
 *
 * The uploader is the export subsystem's outbound network surface
 * — every bundle byte ends up here on the `s3` path — so the gate
 * shape, error envelope, and transport contract are pinned at
 * unit-test level. Production HTTP transport is exercised by the
 * Playwright spec under the dev MinIO stack; here we substitute
 * the cURL call via `_setHttpTransportForTests` so the test stays
 * deterministic and doesn't require network access.
 *
 * Scope:
 *
 *   - Scheme guard — `http://` and non-URL inputs reject BEFORE
 *     the transport ever runs (fail-closed; cleartext transit of
 *     a panel-wide PII dataset is unacceptable per the plan).
 *   - Happy path — the transport receives the exact `(url,
 *     localPath, size)` tuple, and 200 / 201 / 204 are all
 *     accepted as success.
 *   - Error mapping — non-2xx responses throw
 *     `ExportError::S3_PUT_FAILED` with the body truncated for
 *     diagnostics; the error code matches the wire contract
 *     documented in `data-export.mdx`.
 *   - Missing-file guard — `DISK_WRITE_FAILED` when the bundle
 *     path doesn't exist (typically an entry-point bug, but
 *     surfaces cleanly).
 *
 * Mirrors the `_setHttpFetcherForTests` testing pattern from
 * `Sbpp\Announce\AnnouncementFetcher::_setHttpFetcherForTests` —
 * same shape, same teardown discipline, same single-source for
 * the production HTTP path.
 */
final class S3PresignedUploaderTest extends TestCase
{
    /** @var list<string> Temp files to delete on teardown. */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        S3PresignedUploader::_setHttpTransportForTests(null);
        foreach ($this->tempFiles as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
        $this->tempFiles = [];
        parent::tearDown();
    }

    /**
     * The first byte of the scheme guard: any URL that isn't
     * `https://`-prefixed must reject BEFORE the transport fires
     * (and BEFORE the file is even opened). `http://` is the
     * canonical risk shape — the bundle carries admin emails, IP
     * addresses, and every Steam ID the panel knows about, so
     * cleartext transit is unacceptable.
     *
     * The fail-closed property is the contract: an operator who
     * fat-fingers `http` vs `https` on a presigned URL must NOT
     * silently get a cleartext upload that "worked".
     */
    public function testHttpSchemeRejectedBeforeTransportCalled(): void
    {
        $transportCalls = 0;
        S3PresignedUploader::_setHttpTransportForTests(
            function () use (&$transportCalls): array {
                $transportCalls++;
                return ['http_code' => 200, 'body' => ''];
            },
        );

        try {
            (new S3PresignedUploader())->upload('http://example.com/bucket/key', $this->writeTempBundle('hello'));
            $this->fail('http:// URL must throw ExportError(PRESIGN_INVALID_SCHEME)');
        } catch (ExportError $e) {
            $this->assertSame(
                ExportError::PRESIGN_INVALID_SCHEME,
                $e->code(),
                'http:// must surface as PRESIGN_INVALID_SCHEME — operator-facing toast keys off the code',
            );
            // Defense-in-depth: scheme-guard wording in the
            // exception message MUST mention the cleartext-PII
            // risk so log readers understand why the gate fired.
            $this->assertStringContainsString('https://', $e->getMessage());
        }

        $this->assertSame(
            0,
            $transportCalls,
            'Transport must NOT be called when the URL fails the scheme guard — fail-closed contract',
        );
    }

    /**
     * Sister to the above: a string that doesn't even parse as
     * a URL via `parse_url` must reject with the dedicated
     * `PRESIGN_INVALID_URL` code (NOT the scheme code), so the
     * operator gets a pointed error message ("typo'd URL") vs.
     * the generic scheme error.
     *
     * Tricky shape: `parse_url('https://')` returns `['scheme' =>
     * 'https']` with no host — that case rides the host-empty
     * branch of the validator. The values below trigger the
     * harder failure modes: malformed scheme delimiter and
     * raw-string-no-scheme-at-all.
     */
    public function testNonHttpsNonUrlInputsRejected(): void
    {
        $transportCalls = 0;
        S3PresignedUploader::_setHttpTransportForTests(
            function () use (&$transportCalls): array {
                $transportCalls++;
                return ['http_code' => 200, 'body' => ''];
            },
        );

        $bad = [
            'not-a-url-at-all',
            'ftp://example.com/key',
            'javascript:alert(1)',
            'file:///etc/passwd',
            'data:application/zip,bytes',
        ];

        foreach ($bad as $url) {
            $threw = false;
            try {
                (new S3PresignedUploader())->upload($url, $this->writeTempBundle('hello'));
            } catch (ExportError $e) {
                $threw = true;
                // All non-https inputs route through the scheme
                // guard first (it's a cheap prefix check before
                // parse_url runs), so the error code is
                // PRESIGN_INVALID_SCHEME for every one of these.
                // The dedicated PRESIGN_INVALID_URL code applies
                // to URLs that ARE https but fail parse_url (e.g.
                // `https://` with no host — exercised separately).
                $this->assertSame(
                    ExportError::PRESIGN_INVALID_SCHEME,
                    $e->code(),
                    "Non-https URL '{$url}' must surface as PRESIGN_INVALID_SCHEME",
                );
            }
            $this->assertTrue(
                $threw,
                "Non-https URL '{$url}' must throw ExportError — silent acceptance would tunnel the bundle through an arbitrary scheme",
            );
        }

        $this->assertSame(
            0,
            $transportCalls,
            'Transport must NOT be called for any non-https input',
        );
    }

    /**
     * A `https://` URL with no host component (e.g. `https://`,
     * `https:///path/only`) passes the scheme check but fails the
     * parse / host-empty branch. The dedicated error code lets
     * the operator-facing toast surface "URL is missing a host"
     * instead of the generic scheme message.
     */
    public function testHttpsWithoutHostRejectedAsInvalidUrl(): void
    {
        $transportCalls = 0;
        S3PresignedUploader::_setHttpTransportForTests(
            function () use (&$transportCalls): array {
                $transportCalls++;
                return ['http_code' => 200, 'body' => ''];
            },
        );

        try {
            (new S3PresignedUploader())->upload('https:///just-a-path', $this->writeTempBundle('hi'));
            $this->fail('https:// URL with no host must reject');
        } catch (ExportError $e) {
            $this->assertSame(
                ExportError::PRESIGN_INVALID_URL,
                $e->code(),
                'No-host https URL must surface as PRESIGN_INVALID_URL (not _SCHEME — operator typed a syntactically valid scheme but typo\'d the rest)',
            );
        }

        $this->assertSame(0, $transportCalls);
    }

    /**
     * Happy path. The transport closure receives the exact
     * `(url, localPath, size)` tuple the production cURL call
     * would consume; a 200 response from the transport means
     * `upload()` returns void without throwing. The Content-Length
     * header is the load-bearing contract on S3 / R2 / MinIO
     * presigned PUT, and the transport receives the size via the
     * closure args so production can write it into the request.
     */
    public function testHappyPathPassesTransportArgsAndAcceptsTwoOhOh(): void
    {
        $captured = [];
        S3PresignedUploader::_setHttpTransportForTests(
            function (string $url, string $localPath, int $size) use (&$captured): array {
                $captured = compact('url', 'localPath', 'size');
                return ['http_code' => 200, 'body' => ''];
            },
        );

        $body = str_repeat('a', 1234);
        $path = $this->writeTempBundle($body);

        (new S3PresignedUploader())->upload('https://example.com/bucket/key?sig=abc', $path);

        $this->assertSame(
            'https://example.com/bucket/key?sig=abc',
            $captured['url'],
            'Transport must receive the verbatim URL the caller supplied',
        );
        $this->assertSame($path, $captured['localPath'], 'Transport must receive the verbatim file path');
        $this->assertSame(strlen($body), $captured['size'], 'Transport must receive the file size for the Content-Length header');
    }

    /**
     * 201 Created and 204 No Content are also success codes on
     * S3-compatible APIs. S3 itself returns 200 for PUT object;
     * R2 returns 200; MinIO returns 200; some private storage
     * gateways return 201 or 204. All three must be treated as
     * success — otherwise an operator's legitimate upload to a
     * private gateway would surface as a "failed" toast with a
     * misleading error message.
     */
    public function testTwoOhOneAndTwoOhFourAreAlsoSuccess(): void
    {
        foreach ([201, 204] as $code) {
            S3PresignedUploader::_setHttpTransportForTests(
                fn(): array => ['http_code' => $code, 'body' => ''],
            );

            // Reach here means upload() did NOT throw — that's
            // success per the contract.
            (new S3PresignedUploader())->upload('https://example.com/x', $this->writeTempBundle('x'));
            $this->assertTrue(true, "HTTP {$code} must be treated as success");
        }
    }

    /**
     * Non-2xx response must surface as `ExportError::S3_PUT_FAILED`
     * with the response body in the exception message. The body
     * carries S3's XML error envelope (`<Error><Code>...</Code>
     * <Message>...</Message>`) which the operator needs to
     * diagnose the failure — typically a sigv4 mismatch, an
     * expired URL, or a policy denial. Surface it untruncated up
     * to the 2 KiB limit.
     */
    public function testNon2xxResponseSurfacesAsS3PutFailed(): void
    {
        $body403 = '<?xml version="1.0"?><Error><Code>AccessDenied</Code><Message>Request has expired</Message></Error>';
        S3PresignedUploader::_setHttpTransportForTests(
            fn(): array => ['http_code' => 403, 'body' => $body403],
        );

        try {
            (new S3PresignedUploader())->upload('https://example.com/expired', $this->writeTempBundle('x'));
            $this->fail('403 response must throw ExportError(S3_PUT_FAILED)');
        } catch (ExportError $e) {
            $this->assertSame(ExportError::S3_PUT_FAILED, $e->code());
            $this->assertStringContainsString('HTTP 403', $e->getMessage());
            $this->assertStringContainsString('AccessDenied', $e->getMessage(), 'Response body must surface in the error message for diagnostics');
            $this->assertStringContainsString('Request has expired', $e->getMessage());
        }
    }

    /**
     * A 500-class response surfaces the same way — the gate
     * doesn't distinguish between client and server errors; the
     * operator's response in both cases is to look at the body
     * + the URL and retry / fix.
     */
    public function testFiveHundredResponseSurfacesAsS3PutFailed(): void
    {
        S3PresignedUploader::_setHttpTransportForTests(
            fn(): array => ['http_code' => 503, 'body' => 'service temporarily unavailable'],
        );

        try {
            (new S3PresignedUploader())->upload('https://example.com/x', $this->writeTempBundle('x'));
            $this->fail('503 must throw');
        } catch (ExportError $e) {
            $this->assertSame(ExportError::S3_PUT_FAILED, $e->code());
            $this->assertStringContainsString('HTTP 503', $e->getMessage());
            $this->assertStringContainsString('service temporarily unavailable', $e->getMessage());
        }
    }

    /**
     * Response bodies longer than the truncation cap (2 KiB) must
     * surface with the truncation marker appended so the operator
     * (or log reader) understands the body was clipped. The cap
     * exists because a misconfigured S3-compatible endpoint could
     * theoretically return a multi-MB body, and dumping that into
     * the audit log would be a poor outcome.
     */
    public function testLongResponseBodyTruncated(): void
    {
        // 3 KiB body — well past the 2 KiB cap.
        $bigBody = str_repeat('X', 3 * 1024);
        S3PresignedUploader::_setHttpTransportForTests(
            fn(): array => ['http_code' => 400, 'body' => $bigBody],
        );

        try {
            (new S3PresignedUploader())->upload('https://example.com/x', $this->writeTempBundle('x'));
            $this->fail('400 must throw');
        } catch (ExportError $e) {
            $this->assertSame(ExportError::S3_PUT_FAILED, $e->code());
            $this->assertStringContainsString('truncated', $e->getMessage(), 'Truncation marker must surface so the reader knows the body was clipped');
            // The error message contains a prefix + the truncated body — the prefix is small (under 256 chars).
            // The 3 KiB body should have been clipped to 2 KiB, leaving the message under 2.5 KiB total.
            $this->assertLessThan(
                3 * 1024,
                strlen($e->getMessage()),
                'Total message must be smaller than the un-truncated body — truncation is the contract',
            );
        }
    }

    /**
     * Missing-file guard. If the bundle staging file disappears
     * between the entry point creating it and the uploader being
     * called (race? aggressive tmp cleanup? typo'd path?), the
     * uploader must surface a dedicated `DISK_WRITE_FAILED` code
     * instead of crashing inside `fopen`. The transport must NOT
     * be called.
     */
    public function testMissingBundleFileSurfacesAsDiskWriteFailed(): void
    {
        $transportCalls = 0;
        S3PresignedUploader::_setHttpTransportForTests(
            function () use (&$transportCalls): array {
                $transportCalls++;
                return ['http_code' => 200, 'body' => ''];
            },
        );

        $nonexistent = sys_get_temp_dir() . '/sbpp-export-test-missing-' . bin2hex(random_bytes(8)) . '.zip';

        try {
            (new S3PresignedUploader())->upload('https://example.com/x', $nonexistent);
            $this->fail('Missing bundle file must throw ExportError');
        } catch (ExportError $e) {
            $this->assertSame(ExportError::DISK_WRITE_FAILED, $e->code());
            $this->assertStringContainsString($nonexistent, $e->getMessage());
        }

        $this->assertSame(0, $transportCalls, 'Transport must NOT be called when the bundle file is missing');
    }

    /**
     * The class constants for the wire-facing error codes must
     * carry the byte-stable string values documented in
     * `data-export.mdx`. Operator tooling, audit-log greps, and
     * `data-export.mdx` itself all reference these literals; a
     * drift here would silently break every downstream consumer.
     */
    public function testWireFacingErrorCodesAreByteStable(): void
    {
        $this->assertSame('cap_exceeded',           ExportError::CAP_EXCEEDED);
        $this->assertSame('s3_put_failed',          ExportError::S3_PUT_FAILED);
        $this->assertSame('presign_invalid_scheme', ExportError::PRESIGN_INVALID_SCHEME);
        $this->assertSame('presign_invalid_url',    ExportError::PRESIGN_INVALID_URL);
        $this->assertSame('disk_write_failed',      ExportError::DISK_WRITE_FAILED);
        $this->assertSame('disk_full',              ExportError::DISK_FULL);
    }

    /**
     * Write a temp file with the given bytes and register it for
     * teardown. Returns the absolute path.
     */
    private function writeTempBundle(string $body): string
    {
        $path = sys_get_temp_dir() . '/sbpp-export-test-' . bin2hex(random_bytes(8)) . '.zip';
        file_put_contents($path, $body);
        $this->tempFiles[] = $path;
        return $path;
    }
}
