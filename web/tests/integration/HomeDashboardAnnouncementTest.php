<?php

declare(strict_types=1);

namespace Sbpp\Tests\Integration;

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Sbpp\Tests\ApiTestCase;
use Sbpp\View\HomeDashboardView;
use Smarty\Smarty;

/**
 * End-to-end test for the announcement-strip surface gating
 * (#announcements-feed):
 *
 *   - Admin viewer + populated cache → `$announcement` reaches the
 *     View DTO.
 *   - Anonymous viewer + populated cache → `$announcement` is null
 *     (the page handler short-circuits to `null` for non-admins).
 *   - Admin viewer + empty cache → `$announcement` is null (no
 *     content to surface).
 *
 * Each test runs in its own process because `web/pages/page.home.php`
 * declares the top-level `SbppServerQryHelpers()` helper that PHP
 * can't redeclare in one process. Process isolation also avoids
 * the `$GLOBALS['userbank']` / `$_SESSION` cross-contamination that
 * otherwise haunts process-shared tests of page handlers. Mirrors
 * the {@see Php82DeprecationsTest} render-harness pattern.
 *
 * The stub Smarty captures the View DTO by overriding
 * `Renderer::render`'s downstream `$theme->assign(...)` calls — we
 * never actually render the .tpl, the contract under test is
 * "what the page handler passes to the renderer".
 */
final class HomeDashboardAnnouncementTest extends ApiTestCase
{
    private string $cacheFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cacheFile = SB_CACHE . 'announcements.json';
        @unlink($this->cacheFile);
    }

    protected function tearDown(): void
    {
        @unlink($this->cacheFile);
        parent::tearDown();
    }

    /**
     * The page handler builds a `HomeDashboardView` and passes it to
     * `Renderer::render($theme, $view)`. Renderer assigns each public
     * property onto Smarty; we capture the assignments and assert the
     * `announcement` property's shape.
     */
    private function makeCapturingTheme(): object
    {
        return new class extends Smarty {
            /** @var array<string,mixed> */
            public array $captured = [];

            /** @phpstan-ignore method.childParameterType */
            public function assign($tpl_var, $value = null, $nocache = false, $scope = null)
            {
                if (is_string($tpl_var)) {
                    $this->captured[$tpl_var] = $value;
                }
                return $this;
            }

            public function display($template = null, $cache_id = null, $compile_id = null)
            {
                return '';
            }
        };
    }

    /**
     * Wire the globals every page handler reads (`$theme`,
     * `$userbank`, etc.) and return the capturing stub so callers
     * can read back the announcement assignment.
     */
    private function bootRenderHarness(): object
    {
        $theme = $this->makeCapturingTheme();
        $GLOBALS['theme']    = $theme;
        $GLOBALS['userbank'] = $GLOBALS['userbank'] ?? new \CUserManager(null);
        $GLOBALS['username'] = $GLOBALS['username'] ?? 'tester';
        return $theme;
    }

    /**
     * Pre-warm the cache with a single announcement so
     * `AnnouncementFetcher::latest()` returns a populated DTO.
     */
    private function seedCacheWithAnnouncement(string $id, string $title): void
    {
        file_put_contents($this->cacheFile, json_encode([
            [
                'id'           => $id,
                'title'        => $title,
                'body_md'      => 'Body **markdown**.',
                'url'          => 'https://example.com/post',
                'published_at' => '2026-05-15T00:00:00Z',
            ],
        ]));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testAdminSeesAnnouncementWhenCacheIsPopulated(): void
    {
        $this->loginAsAdmin();
        $this->seedCacheWithAnnouncement('rc1', 'v2.0.0 RC1 is out');
        $theme = $this->bootRenderHarness();

        $_SESSION = [];
        $_GET     = [];

        ob_start();
        try {
            require ROOT . 'pages/page.home.php';
        } finally {
            ob_end_clean();
        }

        $this->assertArrayHasKey('announcement', $theme->captured);
        $announcement = $theme->captured['announcement'];
        $this->assertIsArray($announcement, 'admin + populated cache must yield the announcement array');
        $this->assertSame('rc1', $announcement['id']);
        $this->assertSame('v2.0.0 RC1 is out', $announcement['title']);
        $this->assertStringContainsString('<strong>markdown</strong>', $announcement['body_html'],
            'body_md must be Markdown-rendered through IntroRenderer before reaching the View');
        $this->assertSame('2026-05-15', $announcement['published_human']);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testAnonymousVisitorSeesNoAnnouncement(): void
    {
        // No loginAsAdmin → the global userbank is anonymous (set in
        // ApiTestCase::setUp). The page handler's
        // `$userbank->is_admin() ? ... : null` short-circuit must
        // produce `null` so the anonymous landing page never paints
        // the strip.
        $this->seedCacheWithAnnouncement('rc1', 'v2.0.0 RC1 is out');
        $theme = $this->bootRenderHarness();

        $_SESSION = [];
        $_GET     = [];

        ob_start();
        try {
            require ROOT . 'pages/page.home.php';
        } finally {
            ob_end_clean();
        }

        $this->assertArrayHasKey('announcement', $theme->captured);
        $this->assertNull($theme->captured['announcement'],
            'anonymous viewers must NEVER see the announcement strip — gate is server-side');
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testAdminWithEmptyCacheGetsNullAnnouncement(): void
    {
        // Cold install (no `announcements.json` yet) → the strip
        // doesn't paint until the next shutdown hook lands the
        // cache. Documented "first render after install renders no
        // banner" behaviour.
        $this->loginAsAdmin();
        $this->assertFileDoesNotExist($this->cacheFile);
        $theme = $this->bootRenderHarness();

        $_SESSION = [];
        $_GET     = [];

        ob_start();
        try {
            require ROOT . 'pages/page.home.php';
        } finally {
            ob_end_clean();
        }

        $this->assertArrayHasKey('announcement', $theme->captured);
        $this->assertNull($theme->captured['announcement'],
            'cold cache must surface as null — the strip stays hidden until the next tick lands');
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testHomeDashboardViewCarriesTheAnnouncementProperty(): void
    {
        // Belt-and-braces: the View DTO itself must declare the
        // `announcement` property. A regression that drops the
        // property from `HomeDashboardView` would break the
        // template rendering chain (SmartyTemplateRule would flag
        // the unused / missing property at static-analysis time,
        // but this runtime gate catches the procedural-handler
        // side too).
        $reflection = new \ReflectionClass(HomeDashboardView::class);
        $this->assertTrue(
            $reflection->hasProperty('announcement'),
            'HomeDashboardView must declare the `announcement` readonly property — see the View DTO docblock',
        );
        $property = $reflection->getProperty('announcement');
        $this->assertTrue($property->isReadOnly(),
            'the announcement property must be readonly to match the View DTO contract');
    }
}
