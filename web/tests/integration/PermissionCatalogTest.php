<?php

declare(strict_types=1);

namespace Sbpp\Tests\Integration;

use Sbpp\Tests\ApiTestCase;
use Sbpp\View\PermissionCatalog;

/**
 * Lock the categorisation contract for `Sbpp\View\PermissionCatalog`.
 *
 * The catalog is the single source of truth for grouping the
 * `web/configs/permissions/web.json` flags into the categories the
 * "Your permissions" card on `page_youraccount.tpl` paints (#1207
 * ADM-9). Two invariants matter:
 *
 *   1. **Coverage**: every `ADMIN_*` constant in `web.json` (except
 *      the meta-bucket `ALL_WEB`) belongs to **exactly one**
 *      category. A new flag added to `web.json` without a matching
 *      entry in `WEB_CATEGORIES` would silently become invisible on
 *      the account page; this test fails on that case so the gap is
 *      caught at PR review time.
 *   2. **Owner bypass**: holding `ADMIN_OWNER` lights up every
 *      category, mirroring the convention `BitToString` and
 *      `CheckAdminAccess(ADMIN_OWNER|…)` use across the panel.
 *      Regressing this would also regress the "Your permissions"
 *      card for every owner-tier admin.
 */
final class PermissionCatalogTest extends ApiTestCase
{
    /**
     * Coverage invariant: every `ADMIN_*` constant in
     * `web/configs/permissions/web.json` belongs to **exactly one**
     * category in `WEB_CATEGORIES`. The meta-bucket `ALL_WEB` is the
     * only exemption — it's a convenience constant, not a flag.
     *
     * The web.json source is read directly so the test catches a
     * mismatch between the JSON and the catalog at the JSON's level
     * (i.e. a new row in JSON but no matching catalog entry) — not
     * just a mismatch against the live constants which `init.php`
     * defined from the same JSON.
     */
    public function testEveryAdminConstantBelongsToExactlyOneCategory(): void
    {
        $webJson = json_decode(
            (string) file_get_contents(ROOT . 'configs/permissions/web.json'),
            true,
        );
        $this->assertIsArray($webJson, 'web.json must decode as an associative array');

        $jsonNames = array_keys($webJson);
        $jsonNames = array_values(array_filter(
            $jsonNames,
            static fn (string $n): bool => $n !== 'ALL_WEB' && str_starts_with($n, 'ADMIN_'),
        ));

        $seen = [];
        foreach (PermissionCatalog::WEB_CATEGORIES as $category) {
            foreach ($category['constants'] as $name) {
                $this->assertArrayNotHasKey(
                    $name,
                    $seen,
                    "$name appears in multiple categories — it must live in exactly one bucket so the rendered permissions list doesn't double-count it.",
                );
                $seen[$name] = $category['key'];
            }
        }

        $catalogNames = array_keys($seen);
        sort($jsonNames);
        sort($catalogNames);
        $this->assertSame(
            $jsonNames,
            $catalogNames,
            "Mismatch between web.json and PermissionCatalog::WEB_CATEGORIES — every flag in the JSON must be assigned to a category.",
        );
    }

    /**
     * Owner bypass: a user holding only `ADMIN_OWNER` sees every
     * category populated. This mirrors `BitToString`'s legacy
     * behaviour (the helper this catalog supersedes for the account
     * page) so administrative semantics are preserved across the
     * shape change.
     */
    public function testOwnerMaskPopulatesEveryCategory(): void
    {
        $grouped = PermissionCatalog::groupedDisplayFromMask(
            (int) constant('ADMIN_OWNER'),
        );

        $this->assertCount(
            count(PermissionCatalog::WEB_CATEGORIES),
            $grouped,
            'ADMIN_OWNER must light up every category.',
        );

        $expectedKeys = array_column(PermissionCatalog::WEB_CATEGORIES, 'key');
        $actualKeys   = array_column($grouped, 'key');
        $this->assertSame(
            $expectedKeys,
            $actualKeys,
            'Category render order must match WEB_CATEGORIES order.',
        );
    }

    /**
     * Empty-mask invariant: a user with `extraflags = 0` gets an
     * empty list, NOT a list of 7 empty categories. The template
     * paints the `(none)` empty-state for that case, so any code
     * that treats an empty list as "owner without categories" would
     * mis-render — the explicit empty list is the contract.
     */
    public function testEmptyMaskReturnsEmptyList(): void
    {
        $this->assertSame(
            [],
            PermissionCatalog::groupedDisplayFromMask(0),
        );
    }

    /**
     * Narrow-mask: an admin holding only ban-add + edit-own flags
     * sees ONLY the bans category, with exactly those two display
     * strings. Categories that have no granted flags are dropped so
     * the surface only paints buckets with content (otherwise an
     * "Admins" heading with zero rows reads as "you lost your
     * admins permissions" rather than "this category is irrelevant
     * to your role").
     */
    public function testNarrowMaskOnlyPopulatesOwningCategory(): void
    {
        $mask = ((int) constant('ADMIN_ADD_BAN'))
              | ((int) constant('ADMIN_EDIT_OWN_BANS'));
        $grouped = PermissionCatalog::groupedDisplayFromMask($mask);

        $this->assertCount(1, $grouped, 'mask only covers the bans category');
        $this->assertSame('bans', $grouped[0]['key']);
        $this->assertSame('Bans', $grouped[0]['label']);
        $this->assertSame(['Add Bans', 'Edit Own Bans'], $grouped[0]['perms']);
    }

    /**
     * Narrow-mask spanning two categories: a user with one bans flag
     * + one settings flag sees both categories, in the order
     * declared by `WEB_CATEGORIES` (bans before settings — settings
     * sit near the end of the list because most admins don't have
     * the flag).
     */
    public function testMultiCategoryNarrowMaskOrderMatchesCatalog(): void
    {
        $mask = ((int) constant('ADMIN_BAN_PROTESTS'))
              | ((int) constant('ADMIN_WEB_SETTINGS'));
        $grouped = PermissionCatalog::groupedDisplayFromMask($mask);

        $this->assertSame(
            ['bans', 'settings'],
            array_column($grouped, 'key'),
        );
        $this->assertSame(['Ban Appeals'], $grouped[0]['perms']);
        $this->assertSame(['Web Settings'], $grouped[1]['perms']);
    }

    /**
     * `ALL_WEB` carries every flag bit but the catalog must skip it
     * (it's the meta-bucket, not a category). Combine `ALL_WEB` with
     * a real flag and verify only the real categories surface, with
     * no `all_web` row leaking through.
     */
    public function testAllWebMaskDoesNotProduceMetaCategory(): void
    {
        $mask    = (int) constant('ALL_WEB');
        $grouped = PermissionCatalog::groupedDisplayFromMask($mask);

        $keys = array_column($grouped, 'key');
        $this->assertNotContains('all_web', $keys);
        foreach ($grouped as $group) {
            $this->assertNotContains(
                'All Web Permissions',
                $group['perms'],
                'ALL_WEB display string must never appear in any category.',
            );
        }
    }
}
