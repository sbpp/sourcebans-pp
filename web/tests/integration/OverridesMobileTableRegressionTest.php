<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under the Elastic License 2.0.
// See LICENSE.txt for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

declare(strict_types=1);

namespace Sbpp\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Form tables under Admins → Overrides (and the sister group-edit
 * overrides editor) have no paired mobile-card surface. At <=768px
 * the global `.table { display: none }` rule would hide every
 * editable row (including the empty-state message) unless the
 * table opts out via `table--keep-mobile` — the same escape hatch
 * edit-admin-permissions already uses.
 */
final class OverridesMobileTableRegressionTest extends TestCase
{
    public function testOverridesEditorKeepsTableOnMobile(): void
    {
        $src = (string) file_get_contents(ROOT . 'themes/default/page_admin_overrides.tpl');
        $this->assertMatchesRegularExpression(
            '/class="table[^"]*table--keep-mobile[^"]*"[^>]*data-testid="overrides-table"|'
            . 'data-testid="overrides-table"[^>]*class="table[^"]*table--keep-mobile/',
            $src,
            'overrides editor table must carry table--keep-mobile',
        );
    }

    public function testEditGroupFormTablesKeepOnMobile(): void
    {
        $src = (string) file_get_contents(ROOT . 'themes/default/page_admin_edit_group.tpl');
        $this->assertSame(
            3,
            substr_count($src, 'table--keep-mobile'),
            'web perms, server perms, and group overrides tables must all opt out',
        );
        $this->assertMatchesRegularExpression(
            '/class="table[^"]*table--keep-mobile[^"]*"[^>]*id="overrides-table"|'
            . 'id="overrides-table"[^>]*class="table[^"]*table--keep-mobile/',
            $src,
            'group overrides table must carry table--keep-mobile',
        );
    }

    public function testKeepMobileOptOutRuleExistsInThemeCss(): void
    {
        $css = (string) file_get_contents(ROOT . 'themes/default/css/theme.css');
        $this->assertMatchesRegularExpression(
            '/@media\s*\(\s*max-width:\s*768px\s*\)\s*\{[^}]*\.table\s*\{\s*display:\s*none\s*;/s',
            $css,
        );
        $this->assertStringContainsString(
            '.table.table--keep-mobile { display: table; }',
            $css,
        );
    }
}
