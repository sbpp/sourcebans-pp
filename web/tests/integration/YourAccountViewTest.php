<?php

declare(strict_types=1);

namespace Sbpp\Tests\Integration;

use Sbpp\Tests\ApiTestCase;
use Sbpp\Tests\Fixture;
use Sbpp\View\PermissionCatalog;
use Sbpp\View\YourAccountView;

/**
 * Lock the wire shape of `Sbpp\View\YourAccountView`.
 *
 * The view is the seam between `web/pages/page.youraccount.php`
 * (which builds it from the DB row + the user's bitmask) and
 * `page_youraccount.tpl` (which renders the categorised "Your
 * permissions" block, the password forms, and the change-email
 * form). #1207 ADM-9 swapped the previously-flat
 * `web_permissions: list<string>` for a structured
 * `web_permissions_grouped: list<{key, label, perms: list<string>}>`,
 * matching the category split that lives in
 * `Sbpp\View\PermissionCatalog::WEB_CATEGORIES`. This test pins the
 * shape so:
 *
 *   1. The template's `{foreach from=$web_permissions_grouped item=group}`
 *      loop has the keys it expects (`key`, `label`, `perms`), and
 *   2. A future "simplifying" refactor that flattens the grouped
 *      form back to a list (or shuffles the category order) shows
 *      up as a red CI run, not as a stealth UX regression.
 *
 * Snapshot rationale: a JSON-pretty-printed dump of `get_object_vars`
 * is dense but exhaustive — every published property is surfaced and
 * every value is locked. The snapshot pattern (see
 * `Sbpp\Tests\ApiTestCase::assertSnapshot`) is the same one the API
 * contract tests use; the existing snapshot directory layout is
 * `web/tests/api/__snapshots__/<topic>/<scenario>.json`. We file
 * YourAccountView's snapshot under a `views/` subdirectory so it
 * doesn't intermix with the JSON-API envelopes there.
 */
final class YourAccountViewTest extends ApiTestCase
{
    /**
     * Snapshot the seeded admin/admin (ADMIN_OWNER) view shape.
     *
     * Owner-bypass means every category lights up; the snapshot
     * captures that the order is bans → servers → admins → groups
     * → mods → settings → owner (the catalog's source order, which
     * is also the rendered order on the page).
     */
    public function testSeededAdminProducesGroupedPermissionsSnapshot(): void
    {
        $this->loginAsAdmin();
        /** @var \CUserManager $userbank */
        $userbank = $GLOBALS['userbank'];

        $extraflags = (int) $userbank->GetProperty('extraflags');
        $this->assertSame(
            (int) constant('ADMIN_OWNER'),
            $extraflags,
            'The seeded admin/admin must hold ADMIN_OWNER only — see Fixture::seedAdmin().',
        );

        $view = new YourAccountView(
            srvpwset:                false,
            email:                   'snapshot@example.test',
            user_aid:                Fixture::adminAid(),
            web_permissions_grouped: PermissionCatalog::groupedDisplayFromMask($extraflags),
            server_permissions:      false,
            min_pass_len:            6,
        );

        // Pull the published shape exactly the way `Renderer::render`
        // hands it to Smarty — `get_object_vars` walks public
        // readonly properties in declaration order. Wrap it in an
        // `ok` / `data` envelope so `assertSnapshot`'s existing
        // JSON-pretty-print path produces a stable file.
        $envelope = [
            'ok'   => true,
            'data' => get_object_vars($view),
        ];
        // `user_aid` is the fixture-seeded autoincrement that depends
        // on insertion order; redact so the rest of the shape stays
        // locked even when the seed grows extra rows.
        $this->assertSnapshot('views/youraccount_owner', $envelope, ['data.user_aid']);
    }

    /**
     * Hand-rolled assertion for the structured permission shape so a
     * missing key in `web_permissions_grouped` doesn't sneak past the
     * snapshot through a JSON round-trip artefact. Also documents
     * the shape contract inline — future readers don't have to
     * reverse-engineer from the snapshot file what `key`, `label`,
     * and `perms` are supposed to be.
     */
    public function testWebPermissionsGroupedShapeMatchesCatalog(): void
    {
        $this->loginAsAdmin();
        /** @var \CUserManager $userbank */
        $userbank = $GLOBALS['userbank'];

        $extraflags = (int) $userbank->GetProperty('extraflags');
        $view = new YourAccountView(
            srvpwset:                false,
            email:                   '',
            user_aid:                Fixture::adminAid(),
            web_permissions_grouped: PermissionCatalog::groupedDisplayFromMask($extraflags),
            server_permissions:      false,
            min_pass_len:            6,
        );

        $this->assertCount(
            count(PermissionCatalog::WEB_CATEGORIES),
            $view->web_permissions_grouped,
            'Owner-bypass must populate every catalog category.',
        );
        foreach ($view->web_permissions_grouped as $i => $group) {
            $this->assertArrayHasKey('key',   $group, "category $i missing 'key'");
            $this->assertArrayHasKey('label', $group, "category $i missing 'label'");
            $this->assertArrayHasKey('perms', $group, "category $i missing 'perms'");
            $this->assertNotEmpty(
                $group['perms'],
                "category {$group['key']} must have at least one perm for the owner mask.",
            );
        }
    }

    /**
     * No-permission case (a freshly-created admin with `extraflags
     * = 0`). The view publishes an empty `web_permissions_grouped`
     * list — the template's `{if $web_permissions_grouped}` empty
     * branch then renders the "(none)" copy with the
     * `account-permissions-web-empty` testid the e2e suite anchors
     * on.
     */
    public function testEmptyMaskPublishesEmptyList(): void
    {
        $view = new YourAccountView(
            srvpwset:                false,
            email:                   '',
            user_aid:                0,
            web_permissions_grouped: PermissionCatalog::groupedDisplayFromMask(0),
            server_permissions:      false,
            min_pass_len:            6,
        );

        $this->assertSame([], $view->web_permissions_grouped);
    }
}
