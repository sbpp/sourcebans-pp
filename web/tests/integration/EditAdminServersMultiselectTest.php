<?php

declare(strict_types=1);

namespace Sbpp\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Edit Admin → Servers access uses the same data-multiselect shape as
 * Add Admin. Wire values stay g{gid} / s{sid} for the native form POST.
 * Individual-server option labels hydrate via Actions.ServersHostPlayers.
 */
final class EditAdminServersMultiselectTest extends TestCase
{
    private string $template;

    private string $templateNoComments;

    protected function setUp(): void
    {
        parent::setUp();

        $tplPath = ROOT . 'themes/default/page_admin_edit_admins_servers.tpl';
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

    public function testServersSelectIsMultiselectWithHostOptions(): void
    {
        $this->assertMatchesRegularExpression(
            '/<select\b[^>]*\bid="edit-admin-servers"[^>]*>/s',
            $this->template,
            'Individual servers select must exist with id edit-admin-servers.',
        );
        $this->assertMatchesRegularExpression(
            '/<select\b[^>]*\bid="edit-admin-servers"[^>]*\bdata-multiselect\b[^>]*>/s',
            $this->template,
        );
        $this->assertMatchesRegularExpression(
            '/<select\b[^>]*\bid="edit-admin-servers"[^>]*\bmultiple\b[^>]*>/s',
            $this->template,
        );
        $this->assertMatchesRegularExpression(
            '/<select\b[^>]*\bid="edit-admin-servers"[^>]*\bname="servers\[\]"[^>]*>/s',
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
            '/<select\b[^>]*\bid="edit-admin-server-groups"[^>]*\bdata-multiselect\b[^>]*>/s',
            $this->template,
            'Server groups must be a data-multiselect.',
        );
        $this->assertMatchesRegularExpression(
            '/<select\b[^>]*\bid="edit-admin-server-groups"[^>]*\bmultiple\b[^>]*>/s',
            $this->template,
        );
        $this->assertMatchesRegularExpression(
            '/<select\b[^>]*\bid="edit-admin-server-groups"[^>]*\bname="group\[\]"[^>]*>/s',
            $this->template,
        );
        $this->assertMatchesRegularExpression(
            '/<option\b[^>]*\bvalue="g\{\$group\.gid\}"/',
            $this->template,
        );
    }

    public function testAssignedServersPreSelectOptions(): void
    {
        $this->assertStringContainsString(
            '$asrv.srv_group_id == $group.gid}selected',
            $this->templateNoComments,
        );
        $this->assertStringContainsString(
            '$asrv.server_id == $server.sid}selected',
            $this->templateNoComments,
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
        $this->assertStringContainsString(
            "getElementById('edit-admin-servers')",
            $this->templateNoComments,
        );
    }

    public function testCheckboxGridsAreGone(): void
    {
        $this->assertStringNotContainsString(
            'input[name="group[]"]',
            $this->templateNoComments,
        );
        $this->assertStringNotContainsString(
            'input[name="servers[]"]',
            $this->templateNoComments,
        );
        $this->assertDoesNotMatchRegularExpression(
            '/<input\b[^>]*\btype="checkbox"[^>]*\bname="group\[\]"/',
            $this->templateNoComments,
        );
        $this->assertDoesNotMatchRegularExpression(
            '/<input\b[^>]*\btype="checkbox"[^>]*\bname="servers\[\]"/',
            $this->templateNoComments,
        );
    }
}
