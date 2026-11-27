<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under the Elastic License 2.0.
// See LICENSE.txt for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

declare(strict_types=1);

namespace Sbpp\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Pins the edit-group permissions chrome to the same `.edit-perms-*` /
 * `.perms-group-*` vocabulary as edit-admin-permissions.
 */
final class EditGroupChromeTest extends TestCase
{
    public function testEditGroupTemplateUsesSharedPermsChrome(): void
    {
        $src = (string) file_get_contents(ROOT . 'themes/default/page_admin_edit_group.tpl');

        $this->assertStringContainsString('class="card-tab page-section"', $src);
        $this->assertStringContainsString('edit-perms-select-all', $src);
        $this->assertStringContainsString('data-testid="edit-group-web-select-all"', $src);
        $this->assertStringContainsString('data-testid="edit-group-server-select-all"', $src);
        $this->assertStringContainsString('data-perms-scope="web"', $src);
        $this->assertStringContainsString('data-perms-scope="server"', $src);
        $this->assertStringContainsString('perms-group-head', $src);
        $this->assertStringContainsString('perms-group-child', $src);
        $this->assertStringContainsString('perms-group-sep', $src);
        $this->assertStringContainsString('table--compact', $src);
        $this->assertStringContainsString('data-testid="edit-group-back"', $src);
        $this->assertStringNotContainsString('class="pl-6"', $src);
    }

    public function testEditGroupHandlerSkipsOrphanBackStripAndWiresSelectAll(): void
    {
        $src = (string) file_get_contents(ROOT . 'pages/admin.edit.group.php');

        $this->assertStringNotContainsString(
            'new \\Sbpp\\View\\AdminTabs([],',
            $src,
        );
        $this->assertStringNotContainsString(
            'new AdminTabs([],',
            $src,
        );
        $this->assertStringContainsString('wireSelectAll', $src);
        $this->assertStringContainsString("wireSelectAll('web')", $src);
        $this->assertStringContainsString("wireSelectAll('server')", $src);
    }
}
