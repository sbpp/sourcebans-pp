<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under the Elastic License 2.0.
// See LICENSE.txt for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

declare(strict_types=1);

namespace Sbpp\Tests\Integration;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Sbpp\View\AdminGroupsListView;

/**
 * Pins the client-side master-detail catalog contract: the View
 * carries a JSON blob, the list template emits it, and the page
 * handler builds the payload from `$web_group_list`.
 */
final class WebGroupsCatalogTest extends TestCase
{
    public function testAdminGroupsListViewExposesCatalogJsonProperty(): void
    {
        $ref = new ReflectionClass(AdminGroupsListView::class);
        $this->assertTrue(
            $ref->hasProperty('web_groups_catalog_json'),
            'AdminGroupsListView must expose web_groups_catalog_json for the client selector',
        );
    }

    public function testGroupsListTemplateEmitsCatalogAndClientSelector(): void
    {
        $src = (string) file_get_contents(ROOT . 'themes/default/page_admin_groups_list.tpl');

        $this->assertStringContainsString('id="web-groups-catalog"', $src);
        $this->assertStringContainsString('data-testid="web-groups-catalog"', $src);
        $this->assertStringContainsString('{$web_groups_catalog_json nofilter}', $src);
        $this->assertStringContainsString('data-testid="group-detail-name"', $src);
        $this->assertStringContainsString('data-testid="group-detail-members"', $src);
        $this->assertStringContainsString('history.pushState', $src);
        $this->assertStringContainsString('Discard unsaved changes', $src);
    }

    public function testGroupsListHandlerBuildsCatalogJson(): void
    {
        $src = php_strip_whitespace(ROOT . 'pages/admin.groups.php');

        $this->assertStringContainsString('$web_groups_catalog', $src);
        $this->assertStringContainsString('web_groups_catalog_json', $src);
        $this->assertStringContainsString('JSON_HEX_TAG', $src);
        $this->assertStringContainsString('JSON_THROW_ON_ERROR', $src);
    }

    public function testSpaceY2UtilityExistsForServerGroupTiles(): void
    {
        $css = (string) file_get_contents(ROOT . 'themes/default/css/theme.css');
        $this->assertStringContainsString(
            '.space-y-2 > * + * { margin-top: 0.5rem; }',
            $css,
        );
    }
}
