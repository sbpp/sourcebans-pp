<?php

declare(strict_types=1);

namespace Sbpp\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Pins the shared Details / Group / Servers / Permissions tab strip
 * across the four edit-admin templates. Permissions must stay in the
 * same chrome as the sibling pages.
 */
final class EditAdminTabsChromeTest extends TestCase
{
    private const THEME = ROOT . 'themes/default/';

    /** @return list<string> */
    private function editAdminTemplates(): array
    {
        return [
            'page_admin_edit_admins_details.tpl',
            'page_admin_edit_admins_group.tpl',
            'page_admin_edit_admins_servers.tpl',
            'page_admin_edit_admins_perms.tpl',
        ];
    }

    public function testEveryEditAdminTemplateShipsSharedTabStrip(): void
    {
        $required = [
            'role="tablist"',
            'aria-label="Edit admin sections"',
            'data-testid="admin-tab-details"',
            'data-testid="admin-tab-group"',
            'data-testid="admin-tab-servers"',
            'data-testid="admin-tab-permissions"',
            'data-testid="admin-tab-back"',
            'admin-tabs__back',
            'o=editdetails',
            'o=editgroup',
            'o=editservers',
            'o=editpermissions',
        ];

        foreach ($this->editAdminTemplates() as $file) {
            $path = self::THEME . $file;
            $this->assertFileExists($path);
            $src = (string) file_get_contents($path);
            foreach ($required as $needle) {
                $this->assertStringContainsString(
                    $needle,
                    $src,
                    "{$file} must include {$needle}",
                );
            }
        }
    }

    public function testPermissionsTemplateMarksPermissionsTabCurrent(): void
    {
        $src = (string) file_get_contents(self::THEME . 'page_admin_edit_admins_perms.tpl');

        $this->assertMatchesRegularExpression(
            '/data-testid="admin-tab-permissions"[^>]*aria-current="page"|aria-current="page"[^>]*data-testid="admin-tab-permissions"/',
            $src,
            'Permissions page must mark the Permissions tab as current',
        );
        $this->assertStringContainsString('class="card-tab page-section"', $src);
        $this->assertStringContainsString('Edit admin · {$admin_name|escape}', $src);
        $this->assertStringNotContainsString('Permissions for {$admin_name|escape}', $src);
        $this->assertSame(
            2,
            substr_count($src, 'table--keep-mobile'),
            'both permission tables must opt out of the mobile .table hide',
        );
        $this->assertStringContainsString('data-testid="edit-perms-web-select-all"', $src);
        $this->assertStringContainsString('data-testid="edit-perms-server-select-all"', $src);
        $this->assertStringContainsString('perms-group-head', $src);
        $this->assertStringContainsString('perms-group-child', $src);
        $this->assertStringContainsString('perms-group-sep', $src);
    }

    public function testEditAdminHandlersSkipOrphanBackStrip(): void
    {
        $handlers = [
            'admin.edit.admindetails.php',
            'admin.edit.admingroup.php',
            'admin.edit.adminservers.php',
            'admin.edit.adminperms.php',
        ];
        foreach ($handlers as $file) {
            $src = (string) file_get_contents(ROOT . 'pages/' . $file);
            $this->assertStringNotContainsString(
                'new \\Sbpp\\View\\AdminTabs([],',
                $src,
                "{$file} must not mount the empty AdminTabs back strip (dead space under the topbar)",
            );
            $this->assertStringNotContainsString(
                'new AdminTabs([],',
                $src,
                "{$file} must not mount the empty AdminTabs back strip (dead space under the topbar)",
            );
        }
    }
}
