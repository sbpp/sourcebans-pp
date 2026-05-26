<?php

declare(strict_types=1);

namespace Sbpp\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * #1464 — the edit-ban page must surface a getdemo.php download link
 * when a demo row exists, and ship the remove-demo affordance.
 */
final class AdminEditBanDemoTest extends TestCase
{
    private static function webRoot(): string
    {
        return dirname(__DIR__, 2);
    }

    public function testEditBanPageBuildsDemoDownloadLink(): void
    {
        $contents = file_get_contents(self::webRoot() . '/pages/admin.edit.ban.php');
        $this->assertIsString($contents);
        $this->assertStringContainsString("UPPER(demtype) = 'B'", $contents);
        $this->assertStringContainsString('getdemo.php?type=B&id=', $contents);
        $this->assertStringContainsString('data-testid="editban-demo-download"', $contents);
        $this->assertStringContainsString('Actions.BansRemoveDemo', $contents);
    }

    public function testEditBanTemplateShipsRemoveDemoButton(): void
    {
        $contents = file_get_contents(self::webRoot() . '/themes/default/page_admin_edit_ban.tpl');
        $this->assertIsString($contents);
        $this->assertStringContainsString('data-testid="editban-demo-remove"', $contents);
        $this->assertStringContainsString('data-ban-id="{$ban_id}"', $contents);
    }
}
