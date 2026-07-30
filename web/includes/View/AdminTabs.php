<?php

namespace Sbpp\View;

/**
 * Class AdminTabs
 *
 * Two render shapes remain after #1490:
 *
 * 1. `$tabs === []` (edit-* pages: admin.edit.ban.php, admin.rcon.php,
 *    admin.email.php, admin.export.php, …):
 *      Emits `core/admin_tabs.tpl` which renders just the trailing
 *      "Back" anchor — there's no sub-section nav for these surfaces,
 *      only the affordance to leave.
 *
 * 2. `$tabs !== []` (legacy Pattern A callers):
 *      No-op. Section links live in the main sidebar accordion
 *      (`AdminNavCatalog` + `core/navbar.tpl`, #1490). Page handlers
 *      may still construct AdminTabs with a non-empty list during
 *      migration; the constructor accepts the call and renders
 *      nothing so content is not wrapped in a second rail.
 *
 * Prefer dropping non-empty `new AdminTabs(...)` calls from Pattern A
 * handlers entirely. Keep the empty-tabs Back-link shape.
 *
 * @phpstan-type TabSpec array{
 *     name: string,
 *     permission: int|string,
 *     url?: string,
 *     slug?: string,
 *     icon?: string,
 *     config?: bool,
 * }
 */
final class AdminTabs
{
    /** @var list<TabSpec> */
    private array $tabs = [];

    /**
     * @param list<TabSpec> $tabs
     * @param string|null   $activeSlug    Kept for call-site compatibility;
     *     unused when `$tabs` is non-empty (main sidebar owns active state).
     * @param string|null   $sidebarLabel  Kept for call-site compatibility;
     *     unused when `$tabs` is non-empty.
     */
    public function __construct(
        array $tabs,
        \Sbpp\Auth\UserManager $userbank,
        \Smarty\Smarty $theme,
        ?string $activeSlug = null,
        ?string $sidebarLabel = null,
    ) {
        foreach ($tabs as $tab) {
            if ($userbank->HasAccess($tab['permission'])) {
                if (!isset($tab['config']) || $tab['config']) {
                    $this->tabs[] = $tab;
                }
            }
        }

        $resolvedActive = '';
        if ($activeSlug !== null && $activeSlug !== '') {
            $resolvedActive = $activeSlug;
        } elseif (isset($this->tabs[0]['slug']) && is_scalar($this->tabs[0]['slug'])) {
            $resolvedActive = (string) $this->tabs[0]['slug'];
        }

        if ($this->tabs === []) {
            $theme->assign('tabs', $this->tabs);
            $theme->assign('active_tab', $resolvedActive);
            $theme->display('core/admin_tabs.tpl');
            return;
        }

        // #1490 — section nav moved into the main sidebar accordion.
        // Non-empty AdminTabs no longer opens admin-sidebar-shell.
        unset($sidebarLabel);
    }
}

// Issue #1290 phase B: legacy global-name shim. The page handlers still
// call `new AdminTabs(...)` directly; this alias keeps them working
// until the call-site sweep PR.
class_alias(\Sbpp\View\AdminTabs::class, 'AdminTabs');
