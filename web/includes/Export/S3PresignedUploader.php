<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under the Elastic License 2.0.
// See LICENSE.txt for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

declare(strict_types=1);

namespace Sbpp\Export;

/**
 * cURL-based PUT uploader for S3-compatible presigned URLs.
 *
 * Used by `web/export.php`'s `s3` mode: the entry point builds the
 * bundle to a tempfile under `SB_CACHE/exports/<bundle_id>.zip`,
 * then hands the file path + the operator-pasted presigned URL to
 * {@see upload}. The uploader streams the file through cURL's
 * `CURLOPT_INFILE` so a multi-GB bundle doesn't sit in PHP memory.
 *
 * Wire contract:
 *
 *   - **HTTPS only.** `http://` presigned URLs are refused at the
 *     scheme guard — the bundle carries admin emails + IPs + every
 *     Steam ID the panel knows about + unban reasons; cleartext
 *     transit is unacceptable. Throws
 *     {@see ExportError::PRESIGN_INVALID_SCHEME}.
 *   - **URL parses via `parse_url`.** Operator typos that fail
 *     `parse_url` short-circuit with {@see ExportError::PRESIGN_INVALID_URL}
 *     before the cURL request fires; the operator gets a pointed
 *     error message instead of a misleading "S3 returned X" body.
 *   - **`Content-Length` header set explicitly.** S3 + R2 + MinIO
 *     all require it on PUT (chunked transfer encoding is not
 *     accepted by S3-style APIs even when the spec would otherwise
 *     allow it).
 *   - **`CURLOPT_TIMEOUT = 0` (no overall timeout).** Uploads on a
 *     2 GB bundle over a slow link can take a long time; cutting
 *     them off with a wall-clock timeout would surface the wrong
 *     error to the operator ("upload failed" when the actual
 *     answer is "network is slow"). The connect timeout
 *     ({@see CONNECT_TIMEOUT_SECONDS}) is still bounded because a
 *     DNS / TLS-handshake stall is unambiguously a problem.
 *   - **HTTP 200 / 201 / 204 are success.** Everything else throws
 *     {@see ExportError::S3_PUT_FAILED} with the response body
 *     truncated to {@see RESPONSE_BODY_LIMIT} bytes — enough for
 *     S3's XML error envelope plus a comfortable margin.
 *
 * Test override: {@see _setHttpTransportForTests} swaps the cURL
 * call with a closure that receives `(string $url, string $localPath,
 * int $size): array{http_code: int, body: string}` and returns the
 * shape the production path produces. Mirrors
 * `Sbpp\Announce\AnnouncementFetcher::_setHttpFetcherForTests`.
 * Production code never sets it.
 */
final class S3PresignedUploader
{
    /**
     * Bound on the connect-only phase (DNS resolution + TLS handshake).
     * The post-connect upload is bounded by the connection's own
     * write speed, not by an overall timeout — see class docblock.
     */
    private const CONNECT_TIMEOUT_SECONDS = 60;

    /**
     * Truncation cap on the response body we surface in
     * {@see ExportError} messages. S3's XML error envelope is
     * typically <500 bytes; 2 KiB leaves room for verbose providers
     * without dumping a multi-MB body into the audit log.
     */
    private const RESPONSE_BODY_LIMIT = 2 * 1024;

    /**
     * Test-injected transport override. When set, {@see upload}
     * routes through this closure instead of cURL. Closure signature:
     * `function (string $url, string $localPath, int $size): array{http_code: int, body: string}`.
     *
     * @var (callable(string, string, int): array{http_code: int, body: string})|null
     */
    private static $httpTransport = null;

    /**
     * Hot-swap the HTTP transport for tests.
     *
     * @param (callable(string, string, int): array{http_code: int, body: string})|null $transport
     */
    public static function _setHttpTransportForTests(?callable $transport): void
    {
        self::$httpTransport = $transport;
    }

    /**
     * PUT the bundle at `$localPath` to `$url`. Returns void on
     * success; throws {@see ExportError} with a per-failure-mode
     * code on any error.
     */
    public function upload(string $url, string $localPath): void
    {
        $this->validateUrl($url);

        if (!is_file($localPath)) {
            throw new ExportError(
                ExportError::DISK_WRITE_FAILED,
                'Bundle staging file is missing or unreadable: ' . $localPath,
            );
        }
        $size = filesize($localPath);
        if ($size === false) {
            throw new ExportError(
                ExportError::DISK_WRITE_FAILED,
                'Failed to read bundle staging file size: ' . $localPath,
            );
        }
        $size = (int) $size;

        $result = self::$httpTransport !== null
            ? (self::$httpTransport)($url, $localPath, $size)
            : $this->doCurlPut($url, $localPath, $size);

        $code = $result['http_code'];
        $body = $result['body'];

        if (!in_array($code, [200, 201, 204], true)) {
            throw new ExportError(
                ExportError::S3_PUT_FAILED,
                sprintf(
                    'Presigned PUT returned HTTP %d. Response body (truncated to %d bytes): %s',
                    $code,
                    self::RESPONSE_BODY_LIMIT,
                    self::truncate($body, self::RESPONSE_BODY_LIMIT),
                ),
            );
        }
    }

    /**
     * Scheme-then-parse-then-host validation. Each step has its
     * own dedicated error code so the operator-facing toast can
     * surface a pointed message.
     */
    private function validateUrl(string $url): void
    {
        // Cheap prefix check before parse_url runs — `http://`
        // gets a deterministic refusal regardless of what the rest
        // of the URL looks like.
        if (strncasecmp($url, 'https://', 8) !== 0) {
            throw new ExportError(
                ExportError::PRESIGN_INVALID_SCHEME,
                'Presigned URL must use the https:// scheme. The bundle carries admin emails, '
                . 'IP addresses, and Steam IDs — cleartext transit is not acceptable.',
            );
        }
        $parts = parse_url($url);
        if ($parts === false) {
            throw new ExportError(
                ExportError::PRESIGN_INVALID_URL,
                'Could not parse the presigned URL. Check that you pasted the full URL with no surrounding whitespace.',
            );
        }
        $scheme = isset($parts['scheme']) ? strtolower((string) $parts['scheme']) : '';
        $host   = isset($parts['host']) ? (string) $parts['host'] : '';
        if ($scheme !== 'https') {
            throw new ExportError(
                ExportError::PRESIGN_INVALID_SCHEME,
                'Presigned URL must use the https:// scheme. The bundle carries admin emails, '
                . 'IP addresses, and Steam IDs — cleartext transit is not acceptable.',
            );
        }
        if ($host === '') {
            throw new ExportError(
                ExportError::PRESIGN_INVALID_URL,
                'Presigned URL is missing a host component.',
            );
        }
    }

    /**
     * The cURL transport itself. Isolated so the test override can
     * substitute without touching the network.
     *
     * @return array{http_code: int, body: string}
     */
    private function doCurlPut(string $url, string $localPath, int $size): array
    {
        $fh = fopen($localPath, 'rb');
        if ($fh === false) {
            throw new ExportError(
                ExportError::DISK_WRITE_FAILED,
                'Failed to open bundle staging file for read: ' . $localPath,
            );
        }
        $ch = curl_init();
        if ($ch === false) {
            fclose($fh);
            throw new ExportError(
                ExportError::S3_PUT_FAILED,
                'Failed to initialise cURL handle for the presigned PUT.',
            );
        }
        // `CURLOPT_PUT = true` is the legacy shape; the modern
        // surface combines CUSTOMREQUEST + INFILE + INFILESIZE
        // (and Content-Length explicitly via CURLOPT_HTTPHEADER
        // because some cURL builds don't auto-emit it on a
        // CUSTOMREQUEST PUT).
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_CUSTOMREQUEST  => 'PUT',
            CURLOPT_UPLOAD         => true,
            CURLOPT_INFILE         => $fh,
            CURLOPT_INFILESIZE     => $size,
            CURLOPT_HTTPHEADER     => [
                'Content-Length: ' . $size,
                // S3 + R2 + MinIO all accept `application/octet-stream`
                // on a presigned PUT. Setting it explicitly avoids cURL
                // defaulting to the C-locale `application/x-www-form-urlencoded`
                // which some S3-compatible endpoints reject as a sigv4
                // header-set mismatch.
                'Content-Type: application/octet-stream',
                // Defeat any `Expect: 100-continue` round-trip cURL
                // would otherwise add on a large PUT — S3 explicitly
                // doesn't support the Expect handshake and adds 1
                // round-trip-time of latency for nothing.
                'Expect:',
            ],
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT_SECONDS,
            CURLOPT_TIMEOUT        => 0,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FAILONERROR    => false,
            CURLOPT_FOLLOWLOCATION => false,
        ]);

        $body  = curl_exec($ch);
        $code  = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err   = curl_error($ch);
        $errno = curl_errno($ch);
        // PHP 8.0+ deprecated curl_close() — the CurlHandle is now
        // freed at the end of the variable's scope. We let GC handle
        // it; the explicit close was a holdover from the libcurl
        // resource-handle era.
        fclose($fh);

        if ($body === false) {
            throw new ExportError(
                ExportError::S3_PUT_FAILED,
                sprintf('cURL transport failed (errno=%d): %s', $errno, $err !== '' ? $err : 'unknown error'),
            );
        }

        return [
            'http_code' => $code,
            'body'      => is_string($body) ? $body : '',
        ];
    }

    /**
     * Truncate `$body` to at most `$limit` bytes, appending a
     * marker so the operator knows the body was clipped. UTF-8
     * boundary-safe is unnecessary here — S3 / R2 emit ASCII XML
     * envelopes, and a half-character at the truncation boundary
     * is acceptable for a diagnostic message.
     */
    private static function truncate(string $body, int $limit): string
    {
        if (strlen($body) <= $limit) {
            return $body;
        }
        return substr($body, 0, $limit) . '… (truncated)';
    }
}
