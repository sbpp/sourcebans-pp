<?php

declare(strict_types=1);

namespace Sbpp\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Demo download surfaces. #1464 covers the edit-ban page; #1554 restores
 * the public banlist row and player-drawer CTAs.
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

    public function testBanlistShipsResponsiveDemoDownloadActions(): void
    {
        $contents = file_get_contents(self::webRoot() . '/themes/default/page_bans.tpl');
        $this->assertIsString($contents);
        $this->assertStringContainsString('{if $ban.demo_available}', $contents);
        $this->assertStringContainsString('data-testid="row-action-demo-download"', $contents);
        $this->assertStringContainsString('data-testid="row-action-demo-download-mobile"', $contents);
        $this->assertStringContainsString('href="getdemo.php?type=B&amp;id={$ban.bid}"', $contents);
    }

    public function testPlayerDrawerUsesDemoCountForDownloadAction(): void
    {
        $contents = file_get_contents(self::webRoot() . '/themes/default/js/theme.js');
        $this->assertIsString($contents);
        $this->assertStringContainsString('Number(data && data.demo_count) > 0', $contents);
        $this->assertStringContainsString("getdemo.php?type=B&id=", $contents);
        $this->assertStringContainsString('data-testid="drawer-demo-download"', $contents);

        $reference = file_get_contents(self::webRoot() . '/themes/default/partials/player-drawer.tpl');
        $this->assertIsString($reference);
        $this->assertStringContainsString('{if $detail.demo_count > 0}', $reference);
        $this->assertStringContainsString('data-testid="drawer-demo-download"', $reference);
    }

    public function testAddBanUploadCallbackUsesTextContentAndLiveDomApis(): void
    {
        $contents = file_get_contents(self::webRoot() . '/pages/admin.bans.php');
        $this->assertIsString($contents);

        $this->assertStringContainsString('function demo(id, name)', $contents);
        $this->assertStringContainsString('document.getElementById("demo.msg")', $contents);
        $this->assertStringContainsString('message.textContent = "Uploaded: " + String(name)', $contents);
        $this->assertStringNotContainsString('$(\'demo.msg\').setHTML', $contents);
        $this->assertStringNotContainsString("byId('demo.msg').innerHTML", $contents);
    }

    public function testDemoDownloadRejectsLinksAndRequiresCanonicalRootContainment(): void
    {
        $contents = file_get_contents(self::webRoot() . '/getdemo.php');
        $this->assertIsString($contents);

        $this->assertStringContainsString('is_link($path)', $contents);
        $this->assertStringContainsString('str_contains($onDisk, "\0")', $contents);
        $this->assertStringContainsString('$demoRoot = realpath(SB_DEMOS)', $contents);
        $this->assertStringContainsString('$resolvedPath = realpath($path)', $contents);
        $this->assertStringContainsString('str_starts_with(', $contents);
        $this->assertStringContainsString('$path = $resolvedPath;', $contents);
        $this->assertLessThan(
            strpos($contents, 'readfile($path)'),
            strpos($contents, 'is_link($path)'),
            'the symlink and root-containment gates must run before any demo bytes are read',
        );
    }
}
