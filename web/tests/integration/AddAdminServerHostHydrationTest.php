<?php

declare(strict_types=1);

namespace Sbpp\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Add Admin Individual servers access is a data-multiselect. Option
 * labels hydrate via Actions.ServersHostPlayers in the page-tail
 * script (same shape as box_admin_admins_search.tpl). Wire values
 * stay s{sid} for api_admins_add.
 */
final class AddAdminServerHostHydrationTest extends TestCase
{
    private string $template;

    private string $templateNoComments;

    protected function setUp(): void
    {
        parent::setUp();

        $tplPath = ROOT . 'themes/default/page_admin_admins_add.tpl';
        $tpl     = file_get_contents($tplPath);
        if ($tpl === false) {
            self::fail("setUp could not read {$tplPath}");
        }
        $this->template           = $tpl;
        $this->templateNoComments = self::stripSmartyComments($tpl);
    }

    private static function stripSmartyComments(string $contents): string
    {
        return (string) preg_replace('/\{\*.*?\*\}/s', '', $contents);
    }

    public function testTemplateDoesNotIncludeServerTileHydrateScript(): void
    {
        $this->assertStringNotContainsString(
            'src="./scripts/server-tile-hydrate.js"',
            $this->templateNoComments,
            'Add Admin server access uses option[data-server-host] hydrate, not server-tile-hydrate.js.',
        );
    }

    public function testServersSelectIsMultiselectWithHostOptions(): void
    {
        $this->assertMatchesRegularExpression(
            '/<select\b[^>]*\bid="admin-add-servers"[^>]*>/s',
            $this->template,
            'Individual servers select must exist with id admin-add-servers.',
        );
        $this->assertMatchesRegularExpression(
            '/<select\b[^>]*\bid="admin-add-servers"[^>]*\bdata-multiselect\b[^>]*>/s',
            $this->template,
        );
        $this->assertMatchesRegularExpression(
            '/<select\b[^>]*\bid="admin-add-servers"[^>]*\bmultiple\b[^>]*>/s',
            $this->template,
        );
        $this->assertMatchesRegularExpression(
            '/<option\b[^>]*\bvalue="s\{\$server\.sid\}"[^>]*\bdata-server-host\b/s',
            $this->template,
            'Each server option must carry value="s{sid}" and data-server-host.',
        );
        $this->assertMatchesRegularExpression(
            '/\bdata-sid="\{\$server\.sid\}"/',
            $this->template,
        );
        $this->assertMatchesRegularExpression(
            '/\bdata-ip="\{\$server\.ip\}"/',
            $this->template,
        );
        $this->assertMatchesRegularExpression(
            '/\bdata-port="\{\$server\.port\}"/',
            $this->template,
        );
    }

    public function testGroupsSelectIsMultiselect(): void
    {
        $this->assertMatchesRegularExpression(
            '/<select\b[^>]*\bid="admin-add-server-groups"[^>]*\bdata-multiselect\b[^>]*>/s',
            $this->template,
            'Server groups must be a data-multiselect.',
        );
        $this->assertMatchesRegularExpression(
            '/<select\b[^>]*\bid="admin-add-server-groups"[^>]*\bmultiple\b[^>]*>/s',
            $this->template,
        );
        $this->assertMatchesRegularExpression(
            '/<option\b[^>]*\bvalue="g\{\$group\.gid\}"/',
            $this->template,
        );
    }

    public function testPageTailHydratesViaServersHostPlayers(): void
    {
        $this->assertStringContainsString(
            'option[data-server-host]',
            $this->templateNoComments,
        );
        $this->assertStringContainsString(
            'Actions.ServersHostPlayers',
            $this->templateNoComments,
        );
        $this->assertStringContainsString(
            'trunchostname: 70',
            $this->templateNoComments,
        );
        $this->assertStringContainsString(
            "Offline (' + ip + ':' + port + ')",
            $this->templateNoComments,
        );
    }

    public function testCollectorsReadMultiselectSelectedOptions(): void
    {
        $this->assertStringContainsString(
            "collectMultiSelectValues('admin-add-server-groups')",
            $this->templateNoComments,
        );
        $this->assertStringContainsString(
            "collectMultiSelectValues('admin-add-servers')",
            $this->templateNoComments,
        );
        $this->assertStringNotContainsString(
            'input[name="group[]"]',
            $this->templateNoComments,
        );
        $this->assertStringNotContainsString(
            'input[name="servers[]"]',
            $this->templateNoComments,
        );
    }

    public function testLegacyTileAndLoadServerHostHooksAreGone(): void
    {
        $this->assertStringNotContainsString(
            'data-server-hydrate="auto"',
            $this->templateNoComments,
        );
        $this->assertStringNotContainsString(
            'data-testid="server-tile"',
            $this->templateNoComments,
        );
        $this->assertStringNotContainsString(
            'data-testid="server-host"',
            $this->templateNoComments,
        );
        $this->assertDoesNotMatchRegularExpression(
            '/<span\b[^>]*\bid="sa\{\$server\.sid\}"/',
            $this->templateNoComments,
        );
        $this->assertStringNotContainsString(
            "LoadServerHost('",
            $this->templateNoComments,
        );
    }

    public function testAdminAdminsAddViewCarriesNoOrphanServerScriptProperty(): void
    {
        $viewPath = ROOT . 'includes/View/AdminAdminsAddView.php';
        $this->assertFileExists($viewPath);
        $stripped = php_strip_whitespace($viewPath);

        $this->assertDoesNotMatchRegularExpression(
            '/\$server_script\b/',
            $stripped,
            'AdminAdminsAddView must not declare a server_script property.',
        );
    }
}
