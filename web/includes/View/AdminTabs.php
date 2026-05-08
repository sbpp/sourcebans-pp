<?php

namespace Sbpp\View;

/**
 * Class AdminTabs
 *
 * Drives the intra-page section nav for Pattern A admin routes — the
 * routes that subdivide via `?section=<slug>` URLs (servers, mods,
 * groups, settings, comms, admins, bans; see AGENTS.md "Sub-paged
 * admin routes").
 *
 * #1259 unified the chrome
 * ------------------------
 * Pre-#1259 there were two visuals:
 *   - settings rendered a vertical 14rem sidebar inline in every
 *     `page_admin_settings_*.tpl`,
 *   - servers / mods / groups rendered a horizontal pill strip via
 *     `core/admin_tabs.tpl`.
 * The horizontal strip read as "tabs into one document" rather than
 * "navigation between sibling pages" even after #1239 routed each
 * section to its own URL, and stacked badly on dense routes (mods has
 * a 3-section strip already; admins family hits 4+). #1259 lifted the
 * settings sidebar into the parameterized `core/admin_sidebar.tpl`
 * partial and pointed every Pattern A handler at it via this class.
 *
 * #1239 — anchor links, no JS toggle
 * ----------------------------------
 * Pre-#1239 the partial emitted `<button onclick="openTab(this, …)">`
 * elements that called a JS function from the v1.x sourcebans.js bulk
 * file (removed at #1123 D1). Clicks were silent no-ops — every pane
 * was visible permanently — until the broken chrome was repaired in
 * #1239 by routing each section through its own `?section=<slug>` URL.
 *
 * Two render shapes
 * -----------------
 * 1. `$tabs === []` (edit-* pages: admin.edit.ban.php, admin.rcon.php,
 *    admin.email.php, …):
 *      Emits `core/admin_tabs.tpl` which renders just the trailing
 *      "Back" anchor — there's no sub-section nav for these surfaces,
 *      only the affordance to leave. This shape is unchanged by #1259.
 *
 * 2. `$tabs !== []` (Pattern A pages: admin.servers, admin.mods,
 *    admin.groups, admin.settings, admin.comms, admin.admins,
 *    admin.bans):
 *      Opens the sidebar shell (`<div class="admin-sidebar-shell">`),
 *      emits `core/admin_sidebar.tpl` (the <aside> + link list), then
 *      opens the content column (`<div class="admin-sidebar-content">`).
 *      The page handler is responsible for closing both wrappers AFTER
 *      `Renderer::render(...)` runs:
 *
 *      ```php
 *      new AdminTabs($sections, $userbank, $theme, $section, 'Settings sections');
 *      Renderer::render($theme, new AdminSettingsView(...));
 *      echo '</div></div><!-- /.admin-sidebar-content + /.admin-sidebar-shell -->';
 *      ```
 *
 *      The wrapper opens before the View and closes after, so each
 *      `Renderer::render` call slots into the content column without
 *      per-View structural changes.
 *
 * Each tab in the `$tabs` array carries:
 *   - `name`        Display label rendered as the link text.
 *   - `permission`  Bitmask for `CUserManager::HasAccess()`. The tab
 *                   is omitted entirely when the current user lacks
 *                   the flag.
 *   - `url`         (required for non-empty tabs) The link target. The
 *                   page handler is responsible for building it
 *                   (typically `index.php?p=admin&c=<page>&section=<slug>`)
 *                   so the partial doesn't need to know about routing.
 *   - `slug`        (required for non-empty tabs) Short identifier used
 *                   for the `data-testid` and active-tab matching.
 *   - `icon`        (optional) Lucide icon name (e.g. `server`,
 *                   `puzzle`). When omitted the partial falls back to
 *                   a generic `circle-dot` so every row has matching
 *                   visual weight.
 *   - `config`      (optional) Feature-toggle gate; the tab is omitted
 *                   when `config` is set and falsy.
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
     * @param string|null   $activeSlug    Slug of the section to mark with
     *     `aria-current="page"`. When null, the first accessible tab's
     *     slug wins. When the value doesn't match any visible tab no
     *     tab is marked active (and the sidebar falls back to its
     *     all-inactive look — still better than every entry looking
     *     identical).
     * @param string|null   $sidebarLabel  aria-label for the sidebar
     *     <aside>. Screen readers announce the navigation by this
     *     label ("Settings sections" / "Server sections" / …). Only
     *     consumed when `$tabs` is non-empty (the empty-tabs Back-link
     *     shape has no sidebar).
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
            // Edit-* shape: just the trailing Back anchor. The legacy
            // partial still owns this surface — there's no sidebar to
            // unify, only the "leave this page" affordance.
            $theme->assign('tabs', $this->tabs);
            $theme->assign('active_tab', $resolvedActive);
            $theme->display('core/admin_tabs.tpl');
            return;
        }

        // Sidebar shape (#1259). Open the shell + render the <aside> +
        // open the content column. Closing tags live in the calling
        // page handler — see the class docblock for the contract.
        $sidebarId    = 'admin-sidebar';
        $sidebarLabel = $sidebarLabel !== null && $sidebarLabel !== ''
            ? $sidebarLabel
            : 'Page sections';

        echo '<div class="admin-sidebar-shell" data-testid="admin-sidebar-shell">';
        $theme->assign('tabs', $this->tabs);
        $theme->assign('active_tab', $resolvedActive);
        $theme->assign('sidebar_id', $sidebarId);
        $theme->assign('sidebar_label', $sidebarLabel);
        $theme->display('core/admin_sidebar.tpl');
        echo '<div class="admin-sidebar-content">';
    }
}

// Issue #1290 phase B: legacy global-name shim. The page handlers still
// call `new AdminTabs(...)` directly; this alias keeps them working
// until the call-site sweep PR.
class_alias(\Sbpp\View\AdminTabs::class, 'AdminTabs');
