<?php

namespace Sbpp\Tests\Api;

use Sbpp\Tests\ApiTestCase;
use Sbpp\Tests\Fixture;

/**
 * Per-handler coverage for web/api/handlers/system.php.
 *
 * The system handlers are mostly thin wrappers around external state
 * (mail, theme files on disk, the upstream version JSON), so the tests
 * here focus on the structured-error envelopes the panel actually
 * branches on, plus the local-only side effects that don't need
 * working RCON / SMTP / network.
 */
final class SystemTest extends ApiTestCase
{
    public function testCheckVersionIsPublicAndReturnsShape(): void
    {
        // Prime the cache so the handler doesn't hit GitHub from CI: the
        // handler always prefers a fresh cache (TTL is 1 day, our payload
        // is `cached_at => now`) and the snapshot then locks the wire
        // shape byte-for-byte without any network dependency. SB_VERSION
        // is `'test'` in the bootstrap and `version_compare` ranks any
        // unrecognised string below every numeric tag, so a tag of
        // `1.8.4` deterministically lands on the "update available"
        // branch; the snapshot pins that.
        $this->primeReleaseCache('1.8.4');

        $env = $this->api('system.check_version', []);
        $this->assertTrue($env['ok']);
        $this->assertSame('1.8.4', $env['data']['release_latest']);
        $this->assertSame(
            'https://github.com/sbpp/sourcebans-pp/releases/tag/1.8.4',
            $env['data']['release_url']
        );
        $this->assertTrue($env['data']['release_update']);
        // The deprecated `dev` / `dev_*` fields from the legacy upstream
        // shape are gone (#1214); guard against a regression that
        // re-introduces them.
        $this->assertArrayNotHasKey('dev',         $env['data']);
        $this->assertArrayNotHasKey('dev_latest',  $env['data']);
        $this->assertArrayNotHasKey('dev_msg',     $env['data']);
        $this->assertArrayNotHasKey('dev_update',  $env['data']);
        $this->assertSnapshot('system/check_version_update_available', $env);
    }

    public function testCheckVersionLatestReleaseFromCache(): void
    {
        // Tag below SB_VERSION = 'test' in version_compare's ordering is
        // impossible (any unrecognised string sorts below every numeric
        // tag), so seed `release_latest` equal to SB_VERSION; the
        // version_compare branch returns 0 and we land on the "you have
        // the latest" copy. This locks the third public response shape
        // without depending on network or on flipping SB_VERSION.
        $this->primeReleaseCache('test');

        $env = $this->api('system.check_version', []);
        $this->assertTrue($env['ok']);
        $this->assertSame('test', $env['data']['release_latest']);
        $this->assertFalse($env['data']['release_update']);
        $this->assertSnapshot('system/check_version_latest_release', $env);
    }

    public function testCheckVersionStripsLeadingVFromTag(): void
    {
        // GitHub releases historically use both `1.8.4` and `v1.8.4`
        // tag shapes; the handler normalises the stored tag so consumers
        // never have to branch on the prefix.
        $this->primeReleaseCache('v9.9.9');

        $env = $this->api('system.check_version', []);
        $this->assertTrue($env['ok']);
        $this->assertSame('9.9.9', $env['data']['release_latest']);
        $this->assertTrue($env['data']['release_update']);
    }

    public function testCheckVersionErrorWhenNoCacheAndUpstreamUnreachable(): void
    {
        // Force the upstream URL to a port that nothing listens on so
        // the fetch fails immediately (ECONNREFUSED, no 5s wait), drop
        // the cache, and assert the handler reports the documented
        // degraded shape. SB_RELEASE_LATEST_URL is a constant; once
        // defined it sticks for the rest of the PHPUnit process. That's
        // fine: every other check_version test seeds a fresh cache
        // (TTL 1d) so they never reach the upstream-fetch branch.
        @unlink(SB_CACHE . 'github_release_latest.json');
        if (!defined('SB_RELEASE_LATEST_URL')) {
            define('SB_RELEASE_LATEST_URL', 'http://127.0.0.1:1/');
        }

        $env = $this->api('system.check_version', []);

        $this->assertTrue($env['ok']);
        $this->assertSame('Error', $env['data']['release_latest']);
        $this->assertSame('', $env['data']['release_url']);
        $this->assertFalse($env['data']['release_update']);
        $this->assertSnapshot('system/check_version_error', $env);
    }

    public function testCheckVersionStaleCacheServedWhenUpstreamUnreachable(): void
    {
        // Stale-while-error contract: cache exists but is past the
        // 1-day TTL AND the upstream is unreachable -> serve the cached
        // payload anyway (NOT the `Error` envelope). Prime the cache
        // with `cached_at` one second past the TTL so the in-TTL fast
        // path is skipped and the handler falls into the upstream-fetch
        // branch; force the upstream URL to a refused port so that
        // fetch returns null; assert the handler returns the formatted
        // cached shape, not `release_latest: 'Error'`. Re-priming the
        // cache here (rather than relying on a previously-primed file)
        // makes the test order-independent: even if it runs after
        // `testCheckVersionErrorWhenNoCacheAndUpstreamUnreachable`
        // unlinked the file, the stale entry below is what the assertion
        // depends on.
        if (!defined('SB_RELEASE_LATEST_URL')) {
            define('SB_RELEASE_LATEST_URL', 'http://127.0.0.1:1/');
        }
        $this->primeReleaseCache('1.7.2', time() - 86401);

        $env = $this->api('system.check_version', []);

        $this->assertTrue($env['ok']);
        $this->assertSame('1.7.2', $env['data']['release_latest']);
        $this->assertSame(
            'https://github.com/sbpp/sourcebans-pp/releases/tag/1.7.2',
            $env['data']['release_url']
        );
        $this->assertNotSame('Error', $env['data']['release_latest']);
        $this->assertSnapshot('system/check_version_stale_while_error', $env);
    }

    public function testCheckVersionDevSentinelShortCircuitsBeforeVersionCompare(): void
    {
        // Pin the explicit guard added in #1214: a dev-checkout panel
        // (SB_VERSION === 'dev') must NOT compare its local version
        // against the upstream tag with version_compare(), because PHP
        // treats `dev` as a pre-release suffix and ranks it below every
        // numeric tag — `version_compare('1.8.4', 'dev')` returns 1, so
        // a naive compare would falsely advertise an update on every
        // dev checkout. The handler short-circuits that case to return
        // `release_update: false` + a "tracking development build" copy
        // so dev-mode users aren't nagged.
        //
        // SB_VERSION is fixed at 'test' in the bootstrap and PHP can't
        // redefine constants mid-run, so exercise the helper directly
        // with the sentinel as the local-version argument; that's the
        // exact branch the dispatcher would hit on a real dev checkout.
        // The handler file is loaded transitively via Api::bootstrap()
        // in Fixture::install(), so the global helper is in scope by
        // the time setUp() returns. Leading `\` makes it explicit
        // we're reaching for a global function from inside the
        // `Sbpp\Tests\Api` namespace.
        $resp = \_api_system_release_format(
            '1.8.4',
            'https://github.com/sbpp/sourcebans-pp/releases/tag/1.8.4',
            'dev'
        );

        $this->assertSame('1.8.4', $resp['release_latest']);
        $this->assertSame(
            'https://github.com/sbpp/sourcebans-pp/releases/tag/1.8.4',
            $resp['release_url']
        );
        $this->assertSame('Tracking development build.', $resp['release_msg']);
        $this->assertFalse(
            $resp['release_update'],
            'dev sentinel must NOT advertise an update; PHP version_compare("1.8.4", "dev") returns 1.'
        );

        // Belt-and-suspenders: confirm `version_compare` would, in fact,
        // return 1 for the same pair. If a future PHP version changes
        // the comparison, the assertion above is still the correct
        // guard, but this lets a maintainer see immediately why the
        // short-circuit is necessary.
        $this->assertSame(
            1,
            version_compare('1.8.4', 'dev'),
            'PHP semantics changed: version_compare("1.8.4", "dev") no longer returns 1.'
        );
    }

    /**
     * Seed the on-disk cache the handler reads first. The default
     * `cached_at` is `time()`, which (with the 1-day TTL) makes the
     * handler hit the in-TTL fast path and skip the upstream fetch
     * entirely — what the success-path snapshot tests rely on. Pass an
     * older timestamp to exercise the stale-while-error path instead.
     */
    private function primeReleaseCache(string $tagName, ?int $cachedAt = null): void
    {
        $cache = SB_CACHE;
        if (!is_dir($cache) && !@mkdir($cache, 0o775, true) && !is_dir($cache)) {
            $this->markTestSkipped('cache dir not writable');
        }
        file_put_contents($cache . 'github_release_latest.json', (string) json_encode([
            'tag_name'  => $tagName,
            'html_url'  => 'https://github.com/sbpp/sourcebans-pp/releases/tag/' . $tagName,
            'cached_at' => $cachedAt ?? time(),
        ]));
    }

    public function testSelThemeRejectsBlank(): void
    {
        $this->loginAsAdmin();
        $env = $this->api('system.sel_theme', ['theme' => '']);
        $this->assertEnvelopeError($env, 'invalid_theme');
        $this->assertSnapshot('system/sel_theme_invalid', $env);
    }

    public function testSelThemeRejectsTraversal(): void
    {
        // The handler scrubs `../` and `..\\` before basename(), so any
        // injected traversal turns into a stripped basename that won't
        // resolve to a real theme dir.
        $this->loginAsAdmin();
        $env = $this->api('system.sel_theme', ['theme' => '../etc/passwd']);
        $this->assertEnvelopeError($env, 'invalid_theme');
    }

    public function testSelThemeRejectsAnonymous(): void
    {
        $env = $this->api('system.sel_theme', ['theme' => 'default']);
        $this->assertEnvelopeError($env, 'forbidden');
    }

    public function testApplyThemeRejectsInvalid(): void
    {
        $this->loginAsAdmin();
        $env = $this->api('system.apply_theme', ['theme' => 'no-such-theme']);
        $this->assertEnvelopeError($env, 'invalid_theme');
    }

    public function testApplyThemeWritesSetting(): void
    {
        // The dev panel ships only `default`. Pin `config.theme` to
        // something else first so we can prove the handler updated it.
        Fixture::rawPdo()->prepare(sprintf(
            "REPLACE INTO `%s_settings` (`setting`, `value`) VALUES ('config.theme', 'old')",
            DB_PREFIX
        ))->execute();

        $this->loginAsAdmin();
        $env = $this->api('system.apply_theme', ['theme' => 'default']);
        $this->assertTrue($env['ok'], json_encode($env));

        $val = Fixture::rawPdo()->query(sprintf(
            "SELECT value FROM `%s_settings` WHERE setting = 'config.theme'",
            DB_PREFIX
        ))->fetchColumn();
        $this->assertSame('default', $val);
        $this->assertSnapshot('system/apply_theme_success', $env);
    }

    public function testApplyThemeRejectsAnonymous(): void
    {
        $env = $this->api('system.apply_theme', ['theme' => 'default']);
        $this->assertEnvelopeError($env, 'forbidden');
    }

    public function testClearCacheReportsClearedTrue(): void
    {
        $this->loginAsAdmin();

        // Pre-seed a file so we can prove it's gone afterwards.
        $cache = SB_CACHE;
        if (!is_dir($cache) && !@mkdir($cache, 0o775, true) && !is_dir($cache)) {
            $this->markTestSkipped('cache dir not writable');
        }
        file_put_contents($cache . 'sentinel.txt', 'remove me');

        $env = $this->api('system.clear_cache', []);
        $this->assertTrue($env['ok']);
        $this->assertTrue($env['data']['cleared']);
        $this->assertFileDoesNotExist($cache . 'sentinel.txt');
        $this->assertSnapshot('system/clear_cache_success', $env);
    }

    public function testClearCacheRejectsAnonymous(): void
    {
        $env = $this->api('system.clear_cache', []);
        $this->assertEnvelopeError($env, 'forbidden');
    }

    public function testSendMailRejectsBadType(): void
    {
        $this->loginAsAdmin();
        $env = $this->api('system.send_mail', [
            'subject' => 'x', 'message' => 'y', 'type' => 'q', 'id' => 1,
        ]);
        $this->assertEnvelopeError($env, 'bad_type');
        $this->assertSnapshot('system/send_mail_bad_type', $env);
    }

    public function testSendMailRejectsMissingEmail(): void
    {
        $this->loginAsAdmin();
        // No submission seeded → handler reads $row['email'] = '' and
        // throws no_email.
        $env = $this->api('system.send_mail', [
            'subject' => 'x', 'message' => 'y', 'type' => 's', 'id' => 9999,
        ]);
        $this->assertEnvelopeError($env, 'no_email');
        $this->assertSnapshot('system/send_mail_no_email', $env);
    }

    public function testSendMailReportsMailFailureForKnownAddress(): void
    {
        // Seed a submission with an email so we hit the Mail::send path.
        // Without working SMTP the handler turns the false return into
        // the structured `mail_failed` envelope. The wire shape of that
        // failure is what we lock down.
        $pdo = Fixture::rawPdo();
        $pdo->prepare(sprintf(
            'INSERT INTO `%s_submissions`
              (`name`, `SteamId`, `email`, `reason`, `archiv`, `submitted`, `ModID`, `ip`, `server`)
             VALUES (?, ?, ?, ?, "0", ?, 0, "127.0.0.1", 0)',
            DB_PREFIX
        ))->execute(['MailMe', 'STEAM_0:0:1', 'mailme@example.test', 'reason', time()]);
        $sid = (int)$pdo->lastInsertId();

        $this->loginAsAdmin();
        $env = $this->api('system.send_mail', [
            'subject' => 'hi', 'message' => 'hello', 'type' => 's', 'id' => $sid,
        ]);
        $this->assertEnvelopeError($env, 'mail_failed');
        $this->assertSnapshot('system/send_mail_failed', $env);
    }

    public function testSendMailRejectsAnonymous(): void
    {
        $env = $this->api('system.send_mail', [
            'subject' => 'x', 'message' => 'y', 'type' => 's', 'id' => 1,
        ]);
        $this->assertEnvelopeError($env, 'forbidden');
    }

    public function testRehashAdminsReturnsResultsListWithoutServers(): void
    {
        $this->loginAsAdmin();
        $env = $this->api('system.rehash_admins', ['servers' => '']);
        $this->assertTrue($env['ok']);
        $this->assertSame([], $env['data']['results']);
        $this->assertSnapshot('system/rehash_admins_empty', $env);
    }

    public function testRehashAdminsReportsRconFailureForUnknownSid(): void
    {
        $this->loginAsAdmin();
        $env = $this->api('system.rehash_admins', ['servers' => '9999']);
        $this->assertTrue($env['ok']);
        $this->assertCount(1, $env['data']['results']);
        $this->assertSame(9999, (int)$env['data']['results'][0]['sid']);
        $this->assertFalse($env['data']['results'][0]['success']);
    }

    public function testRehashAdminsRejectsAnonymous(): void
    {
        $env = $this->api('system.rehash_admins', ['servers' => '']);
        $this->assertEnvelopeError($env, 'forbidden');
    }

    /**
     * #1207 SET-1: live Markdown preview for the dashboard intro field.
     *
     * The handler is a thin wrapper around
     * `Sbpp\Markup\IntroRenderer::renderIntroText()`; we lock in the
     * rendered shape (CommonMark inline -> safe HTML) plus the two
     * security-critical paths the renderer enforces (raw HTML escaped,
     * `javascript:` links stripped). If a future PR routes preview
     * through a different renderer, this row fires loudly.
     */
    public function testPreviewIntroTextRendersCommonMarkParagraph(): void
    {
        $this->loginAsAdmin();
        $env = $this->api('system.preview_intro_text', [
            'markdown' => 'Hello **world** with [a link](https://example.test).',
        ]);
        $this->assertTrue($env['ok'], json_encode($env));
        $this->assertSame(
            "<p>Hello <strong>world</strong> with <a href=\"https://example.test\">a link</a>.</p>\n",
            $env['data']['html']
        );
        $this->assertSnapshot('system/preview_intro_text_paragraph', $env);
    }

    public function testPreviewIntroTextEscapesRawHtml(): void
    {
        $this->loginAsAdmin();
        $env = $this->api('system.preview_intro_text', [
            'markdown' => 'safe <script>alert(1)</script> text',
        ]);
        $this->assertTrue($env['ok']);
        // CommonMark with html_input=escape renders inline HTML as
        // literal text — the `<script>` shows up entity-encoded, not
        // as a parsed tag, so the preview pane never executes admin
        // input. This is the contract from `IntroRenderer::converter`.
        $this->assertStringNotContainsString('<script>', $env['data']['html']);
        $this->assertStringContainsString('&lt;script&gt;', $env['data']['html']);
    }

    public function testPreviewIntroTextStripsUnsafeLinks(): void
    {
        $this->loginAsAdmin();
        $env = $this->api('system.preview_intro_text', [
            'markdown' => '[click](javascript:alert(1))',
        ]);
        $this->assertTrue($env['ok']);
        // `allow_unsafe_links: false` (IntroRenderer) drops
        // javascript:/data:/vbscript: hrefs entirely.
        $this->assertStringNotContainsString('javascript:', $env['data']['html']);
    }

    public function testPreviewIntroTextRendersEmptyForBlankInput(): void
    {
        $this->loginAsAdmin();
        $env = $this->api('system.preview_intro_text', ['markdown' => '']);
        $this->assertTrue($env['ok']);
        $this->assertSame('', $env['data']['html']);
    }

    public function testPreviewIntroTextRejectsAnonymous(): void
    {
        $env = $this->api('system.preview_intro_text', ['markdown' => 'hi']);
        $this->assertEnvelopeError($env, 'forbidden');
    }
}
