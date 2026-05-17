<?php

declare(strict_types=1);

namespace Sbpp\Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sbpp\Announce\Announcement;
use Sbpp\Announce\AnnouncementFetcher;

/**
 * Regression suite for the daily project-announcements feed (#announcements-feed).
 *
 * Every test drives the cache directly via `_setHttpFetcherForTests`,
 * mirroring `SourceQueryCacheTest`'s probe-override shape — production
 * never exercises the test hook. The contract under test is the cache
 * shape (atomic write, stale-while-error), the parser's defensive
 * validation (drops malformed / expired / oversized entries), and the
 * Markdown body's safe-HTML rendering through {@see \Sbpp\Markup\IntroRenderer}.
 *
 * Tests do NOT cover the live HTTPS fetch — that's a network round-trip
 * the suite isn't allowed to make. The wire layer's correctness is
 * pinned by the `system.check_version` handler tests (same shape) and
 * the manual `make sync-…` workflow operators run during a release.
 */
final class AnnouncementFetcherTest extends TestCase
{
    private string $cacheFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cacheFile = SB_CACHE . 'announcements.json';
        @unlink($this->cacheFile);
        // Sweep stale tempfiles from a prior crash so the
        // "no `.tmp` strays" assertions stay tight.
        foreach (glob(SB_CACHE . 'announcements.json.*.tmp') ?: [] as $tmp) {
            @unlink($tmp);
        }
        AnnouncementFetcher::_setHttpFetcherForTests(null);
        AnnouncementFetcher::_setUpstreamUrlForTests(null);

        // Default the URL constant to a non-empty placeholder so
        // `tickIfDue()`'s air-gap branch doesn't short-circuit
        // every test. The constant is write-once, so individual
        // tests that need to drive the air-gap / scheme-guard
        // branches override the URL via `_setUpstreamUrlForTests`
        // instead — see `testEmptyAirGapUrlShortCircuitsTheFetcher`
        // and `testNonHttpUrlSchemeShortCircuitsTheFetcher`.
        if (!defined('SB_ANNOUNCEMENTS_URL')) {
            // The bootstrap doesn't define this (init.php does at
            // runtime), so the test harness owns the default.
            define('SB_ANNOUNCEMENTS_URL', 'https://example.invalid/announcements.json');
        }
    }

    protected function tearDown(): void
    {
        AnnouncementFetcher::_setHttpFetcherForTests(null);
        AnnouncementFetcher::_setUpstreamUrlForTests(null);
        @unlink($this->cacheFile);
        parent::tearDown();
    }

    public function testColdCacheReturnsNull(): void
    {
        // No file on disk → no announcement to render. The dashboard
        // handler treats this as "render nothing".
        $this->assertNull(AnnouncementFetcher::latest());
    }

    public function testTickIfDuePopulatesCacheOnFirstSuccessfulFetch(): void
    {
        $body = json_encode([
            [
                'id'           => '2026-05-rc1',
                'title'        => 'v2.0.0 RC1',
                'body_md'      => 'Read the **release notes**.',
                'url'          => 'https://example.com/rc1',
                'published_at' => '2026-05-15T00:00:00Z',
            ],
        ], JSON_THROW_ON_ERROR);

        $callCount = 0;
        AnnouncementFetcher::_setHttpFetcherForTests(static function (string $url) use ($body, &$callCount): string {
            $callCount++;
            return $body;
        });

        AnnouncementFetcher::tickIfDue();

        $this->assertSame(1, $callCount, 'cold cache must fire exactly one upstream call');
        $this->assertFileExists($this->cacheFile);

        $latest = AnnouncementFetcher::latest();
        $this->assertInstanceOf(Announcement::class, $latest);
        $this->assertSame('2026-05-rc1', $latest->id);
        $this->assertSame('v2.0.0 RC1', $latest->title);
        $this->assertStringContainsString('<strong>release notes</strong>', $latest->body_html,
            'body_md must be Markdown-rendered through IntroRenderer');
        $this->assertSame('https://example.com/rc1', $latest->url);
        $this->assertSame('2026-05-15', $latest->published_human);
    }

    public function testTickIfDueIsNoopWhenCacheIsFresh(): void
    {
        // Pre-warm the cache with a recent mtime; tickIfDue() must
        // see it as fresh and skip the fetcher.
        file_put_contents($this->cacheFile, json_encode([
            ['id' => 'fresh', 'title' => 'Fresh entry'],
        ]));

        $callCount = 0;
        AnnouncementFetcher::_setHttpFetcherForTests(static function () use (&$callCount): ?string {
            $callCount++;
            return null;
        });

        AnnouncementFetcher::tickIfDue();
        AnnouncementFetcher::tickIfDue();
        AnnouncementFetcher::tickIfDue();

        $this->assertSame(0, $callCount,
            'a fresh cache (mtime within TTL) must not fire the upstream — that is the load-bearing daily-rate guarantee');
    }

    public function testTickIfDueRefetchesWhenCacheIsStale(): void
    {
        file_put_contents($this->cacheFile, json_encode([
            ['id' => 'old', 'title' => 'Old entry'],
        ]));
        // Push the mtime past the TTL window without sleeping.
        @touch($this->cacheFile, time() - (AnnouncementFetcher::CACHE_TTL_SECONDS + 60));

        $newBody = json_encode([
            ['id' => 'new', 'title' => 'New entry'],
        ], JSON_THROW_ON_ERROR);
        $callCount = 0;
        AnnouncementFetcher::_setHttpFetcherForTests(static function () use ($newBody, &$callCount): string {
            $callCount++;
            return $newBody;
        });

        AnnouncementFetcher::tickIfDue();

        $this->assertSame(1, $callCount, 'stale cache must trigger one fetch');
        $latest = AnnouncementFetcher::latest();
        $this->assertNotNull($latest);
        $this->assertSame('new', $latest->id);
    }

    public function testStaleCachePreservedWhenUpstreamFails(): void
    {
        // Pre-existing cache with a stale mtime + an upstream that
        // returns null (timeout / non-2xx / DNS failure). The cache
        // must NOT be overwritten — the operator should keep seeing
        // the previous announcement until the upstream recovers.
        file_put_contents($this->cacheFile, json_encode([
            ['id' => 'preserved', 'title' => 'Preserved entry'],
        ]));
        @touch($this->cacheFile, time() - (AnnouncementFetcher::CACHE_TTL_SECONDS + 60));
        $originalContents = file_get_contents($this->cacheFile);

        AnnouncementFetcher::_setHttpFetcherForTests(static fn(): ?string => null);

        AnnouncementFetcher::tickIfDue();

        $this->assertFileExists($this->cacheFile, 'failed fetch must NOT delete the existing cache');
        $this->assertSame($originalContents, file_get_contents($this->cacheFile),
            'stale-while-error: the file must be byte-identical after a failed upstream call');
        $latest = AnnouncementFetcher::latest();
        $this->assertNotNull($latest);
        $this->assertSame('preserved', $latest->id);
    }

    public function testOversizedBodyIsRejected(): void
    {
        // Build a JSON body with a body_md that pushes the total
        // past the 256 KiB cap. The fetcher must reject and leave
        // the cache untouched.
        $payload = json_encode([
            [
                'id'      => 'huge',
                'title'   => 'Huge',
                'body_md' => str_repeat('A', 300_000),
            ],
        ], JSON_THROW_ON_ERROR);
        $this->assertGreaterThan(262_144, strlen($payload),
            'sanity: the payload must actually exceed the cap');

        AnnouncementFetcher::_setHttpFetcherForTests(static fn(): string => $payload);

        AnnouncementFetcher::tickIfDue();

        $this->assertFileDoesNotExist($this->cacheFile,
            'an oversized response must NOT land on disk — defence-in-depth against a hostile upstream');
    }

    public function testMalformedJsonIsHandledGracefully(): void
    {
        // The wire layer accepted the body (size, status), but it
        // doesn't parse as JSON. The cache file is written verbatim
        // (we trust the upstream not to silently corrupt itself),
        // but `latest()` should drop it and return null.
        AnnouncementFetcher::_setHttpFetcherForTests(static fn(): string => 'not valid JSON {[');

        AnnouncementFetcher::tickIfDue();

        $this->assertNull(AnnouncementFetcher::latest(),
            'a malformed cache file must surface as "no announcement", not a fatal');
    }

    public function testEntriesMissingRequiredFieldsAreDropped(): void
    {
        $body = json_encode([
            ['title' => 'No id, dropped'],                         // missing id
            ['id' => 'no-title-dropped'],                          // missing title
            ['id' => '', 'title' => 'Empty id, dropped'],          // empty id
            ['id' => 'good', 'title' => ''],                       // empty title, dropped
            ['id' => str_repeat('x', 100), 'title' => 'Overlong'], // overlong id, dropped
            ['id' => 'valid', 'title' => 'The keeper'],
        ], JSON_THROW_ON_ERROR);
        AnnouncementFetcher::_setHttpFetcherForTests(static fn(): string => $body);

        AnnouncementFetcher::tickIfDue();

        $latest = AnnouncementFetcher::latest();
        $this->assertNotNull($latest);
        $this->assertSame('valid', $latest->id, 'parser must drop every malformed entry and keep the one valid one');
    }

    public function testExpiredEntriesAreFilteredOut(): void
    {
        $body = json_encode([
            [
                'id'           => 'expired',
                'title'        => 'Long gone',
                'published_at' => '2026-04-01T00:00:00Z',
                'expires_at'   => '2026-04-15T00:00:00Z',
            ],
            [
                'id'           => 'live',
                'title'        => 'Still alive',
                'published_at' => '2026-04-10T00:00:00Z',
                'expires_at'   => gmdate('c', time() + 86_400),
            ],
        ], JSON_THROW_ON_ERROR);
        AnnouncementFetcher::_setHttpFetcherForTests(static fn(): string => $body);

        AnnouncementFetcher::tickIfDue();

        $latest = AnnouncementFetcher::latest();
        $this->assertNotNull($latest);
        $this->assertSame('live', $latest->id, 'expired entries must be filtered out at parse time');
    }

    public function testNewestEntryWinsByPublishedAt(): void
    {
        // Out-of-order on disk. The parser's usort must surface the
        // newest by published_at regardless of array order in the
        // source file.
        $body = json_encode([
            ['id' => 'mid', 'title' => 'Middle', 'published_at' => '2026-03-01T00:00:00Z'],
            ['id' => 'old', 'title' => 'Oldest', 'published_at' => '2026-01-01T00:00:00Z'],
            ['id' => 'new', 'title' => 'Newest', 'published_at' => '2026-05-15T00:00:00Z'],
        ], JSON_THROW_ON_ERROR);
        AnnouncementFetcher::_setHttpFetcherForTests(static fn(): string => $body);

        AnnouncementFetcher::tickIfDue();

        $latest = AnnouncementFetcher::latest();
        $this->assertNotNull($latest);
        $this->assertSame('new', $latest->id);
    }

    public function testEntriesWithoutPublishedAtSortLast(): void
    {
        // A maintainer who forgets `published_at` on a new entry
        // shouldn't accidentally pin it to the top — entries
        // without a date sort below every dated entry.
        $body = json_encode([
            ['id' => 'undated', 'title' => 'No date'],
            ['id' => 'dated',   'title' => 'Dated', 'published_at' => '2026-05-15T00:00:00Z'],
        ], JSON_THROW_ON_ERROR);
        AnnouncementFetcher::_setHttpFetcherForTests(static fn(): string => $body);

        AnnouncementFetcher::tickIfDue();

        $latest = AnnouncementFetcher::latest();
        $this->assertNotNull($latest);
        $this->assertSame('dated', $latest->id,
            'entries with a published_at must sort above entries without one');
    }

    public function testDuplicateIdsAreDeduplicated(): void
    {
        $body = json_encode([
            ['id' => 'dup', 'title' => 'First copy',  'published_at' => '2026-05-15T00:00:00Z'],
            ['id' => 'dup', 'title' => 'Second copy', 'published_at' => '2026-05-15T00:00:00Z'],
        ], JSON_THROW_ON_ERROR);
        AnnouncementFetcher::_setHttpFetcherForTests(static fn(): string => $body);

        AnnouncementFetcher::tickIfDue();

        $latest = AnnouncementFetcher::latest();
        $this->assertNotNull($latest);
        $this->assertSame('First copy', $latest->title,
            'first occurrence wins on duplicate id — protects against a sloppy upstream double-publish');
    }

    public function testNonHttpUrlIsRejected(): void
    {
        // Defence-in-depth: the body's Markdown is escaped by
        // IntroRenderer, but the top-level `url` is rendered as a
        // literal `<a href="...">` so we gate it at parse time.
        $body = json_encode([
            ['id' => 'bad', 'title' => 'Bad URL', 'url' => 'javascript:alert(1)'],
            ['id' => 'ok',  'title' => 'OK URL',  'url' => 'https://example.com/'],
        ], JSON_THROW_ON_ERROR);
        AnnouncementFetcher::_setHttpFetcherForTests(static fn(): string => $body);

        AnnouncementFetcher::tickIfDue();

        $latest = AnnouncementFetcher::latest();
        $this->assertNotNull($latest);
        $this->assertSame('ok', $latest->id, 'javascript: / data: URLs must be rejected at the parser');
    }

    public function testBodyMarkdownIsRenderedSafely(): void
    {
        // Pin the IntroRenderer integration: a `<script>` in the
        // body_md must be escaped to visible text, not emitted as
        // a live tag.
        $body = json_encode([
            [
                'id'      => 'xss',
                'title'   => 'XSS attempt',
                'body_md' => '<script>alert(1)</script>',
            ],
        ], JSON_THROW_ON_ERROR);
        AnnouncementFetcher::_setHttpFetcherForTests(static fn(): string => $body);

        AnnouncementFetcher::tickIfDue();

        $latest = AnnouncementFetcher::latest();
        $this->assertNotNull($latest);
        $this->assertStringNotContainsString('<script', $latest->body_html,
            'a literal <script> tag in body_md must NOT survive into body_html');
        $this->assertStringContainsString('&lt;script&gt;', $latest->body_html,
            'IntroRenderer escapes the tag to visible text — the contract from the #1113 fix');
    }

    public function testCacheWriteIsAtomicWithNoTempfileLeak(): void
    {
        $body = json_encode([
            ['id' => 'atomic', 'title' => 'Atomic write'],
        ], JSON_THROW_ON_ERROR);
        AnnouncementFetcher::_setHttpFetcherForTests(static fn(): string => $body);

        AnnouncementFetcher::tickIfDue();

        $this->assertFileExists($this->cacheFile);
        $strays = glob(SB_CACHE . 'announcements.json.*.tmp') ?: [];
        $this->assertSame([], $strays,
            'tempfile rename must be atomic; no `.tmp` artifacts should survive a successful write');
    }

    public function testBodyIsPersistedVerbatim(): void
    {
        // The cache file is a byte-for-byte copy of the upstream body.
        // The parsed shape lives in memory only — easier to inspect
        // a "wrong content rendered" report when the on-disk file
        // matches what the upstream served.
        $body = json_encode([
            ['id' => 'verbatim', 'title' => 'Verbatim'],
        ], JSON_THROW_ON_ERROR);
        AnnouncementFetcher::_setHttpFetcherForTests(static fn(): string => $body);

        AnnouncementFetcher::tickIfDue();

        $this->assertSame($body, file_get_contents($this->cacheFile),
            'the persisted file must be byte-identical to the upstream body');
    }

    public function testEmptyAirGapUrlShortCircuitsTheFetcher(): void
    {
        // Empty URL = the documented air-gap escape hatch. The
        // shutdown hook MUST NOT touch the network on this branch.
        // Drive the override via `_setUpstreamUrlForTests` since the
        // constant itself is write-once at runtime.
        AnnouncementFetcher::_setUpstreamUrlForTests('');

        $callCount = 0;
        AnnouncementFetcher::_setHttpFetcherForTests(static function () use (&$callCount): ?string {
            $callCount++;
            return null;
        });

        AnnouncementFetcher::tickIfDue();

        $this->assertSame(0, $callCount,
            'air-gap (empty URL) MUST NOT invoke the upstream fetcher');
        $this->assertFileDoesNotExist($this->cacheFile,
            'air-gap path MUST NOT touch the cache file');
    }

    #[DataProvider('nonHttpSchemeProvider')]
    public function testNonHttpUrlSchemeShortCircuitsTheFetcher(string $url): void
    {
        // Defence-in-depth against SSRF: if `config.php` is
        // misconfigured (or compromised) and points
        // `SB_ANNOUNCEMENTS_URL` at any non-http(s) stream wrapper,
        // the resolver MUST drop to the air-gap branch. Without
        // this guard, `file://` would let arbitrary local files
        // land in the cache, `php://input` would echo the request
        // body, etc.
        AnnouncementFetcher::_setUpstreamUrlForTests($url);

        $callCount = 0;
        AnnouncementFetcher::_setHttpFetcherForTests(static function () use (&$callCount): ?string {
            $callCount++;
            return null;
        });

        AnnouncementFetcher::tickIfDue();

        $this->assertSame(0, $callCount,
            "non-http(s) URL ({$url}) MUST NOT invoke the upstream fetcher");
        $this->assertFileDoesNotExist($this->cacheFile,
            "non-http(s) URL ({$url}) MUST NOT touch the cache file");
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function nonHttpSchemeProvider(): iterable
    {
        yield 'file scheme'  => ['file:///etc/passwd'];
        yield 'php scheme'   => ['php://filter/read=convert.base64-encode/resource=/etc/passwd'];
        yield 'phar scheme'  => ['phar:///tmp/x.phar'];
        yield 'data scheme'  => ['data:text/plain,malicious'];
        yield 'ftp scheme'   => ['ftp://example.com/feed.json'];
        yield 'gopher scheme'=> ['gopher://example.com/'];
    }

    public function testHttpsUrlIsAccepted(): void
    {
        // The positive arm: a plain `https://` URL flows through
        // `resolveUpstreamUrl()` to the fetcher unchanged. Pinning
        // this so a future tightening of the regex (e.g. requiring
        // `https://` only, dropping `http://`) doesn't silently
        // break self-hosted mirrors.
        AnnouncementFetcher::_setUpstreamUrlForTests('https://mirror.example.com/announcements.json');

        $receivedUrl = null;
        AnnouncementFetcher::_setHttpFetcherForTests(static function (string $url) use (&$receivedUrl): ?string {
            $receivedUrl = $url;
            return '[]';
        });

        AnnouncementFetcher::tickIfDue();

        $this->assertSame('https://mirror.example.com/announcements.json', $receivedUrl,
            'http(s) URL MUST be passed verbatim to the fetcher');
    }

    public function testHttpUrlIsAccepted(): void
    {
        // Plain `http://` is also legal — operators on intranet
        // mirrors without TLS shouldn't be forced into a custom
        // CA story. The scheme guard's job is to reject stream
        // wrappers, not enforce HTTPS.
        AnnouncementFetcher::_setUpstreamUrlForTests('http://mirror.example.lan/announcements.json');

        $callCount = 0;
        AnnouncementFetcher::_setHttpFetcherForTests(static function () use (&$callCount): ?string {
            $callCount++;
            return '[]';
        });

        AnnouncementFetcher::tickIfDue();

        $this->assertSame(1, $callCount,
            'plain http:// URL MUST reach the fetcher');
    }

    public function testIsoTimestampAndIntegerTimestampBothParse(): void
    {
        // Future-proofing: feeds may emit unix integers instead of
        // ISO-8601 strings. Both shapes must yield the same result.
        $isoTs = strtotime('2026-05-15T00:00:00Z');
        $this->assertNotFalse($isoTs);

        $body = json_encode([
            ['id' => 'iso', 'title' => 'ISO',     'published_at' => '2026-05-15T00:00:00Z'],
            ['id' => 'int', 'title' => 'Integer', 'published_at' => $isoTs - 86400],
        ], JSON_THROW_ON_ERROR);
        AnnouncementFetcher::_setHttpFetcherForTests(static fn(): string => $body);

        AnnouncementFetcher::tickIfDue();

        $latest = AnnouncementFetcher::latest();
        $this->assertNotNull($latest);
        $this->assertSame($isoTs, $latest->published_at);
    }
}
