<?php

/**
 * Class AdminTabs
 *
 * Renders the intra-page section nav (`core/admin_tabs.tpl`) used by
 * admin routes that subdivide their chrome into sections (servers,
 * mods, groups, …) plus the edit.* pages that pass an empty `$tabs`
 * array so only the trailing "Back" link renders.
 *
 * Pre-#1239 the partial emitted `<button onclick="openTab(this, …)">`
 * elements that called a JS function from the v1.x sourcebans.js bulk
 * file (removed at #1123 D1). Clicks were silent no-ops — every pane
 * was visible permanently — until the broken chrome was repaired in
 * #1239 by routing each section through its own `?section=<slug>` URL.
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
 *   - `config`      (optional) Feature-toggle gate; the tab is omitted
 *                   when `config` is set and falsy.
 *
 * Edit-* pages still pass `[]` (empty `$tabs`) so the chrome renders
 * just the "Back" anchor — that path is unchanged.
 *
 * @phpstan-type TabSpec array{
 *     name: string,
 *     permission: int|string,
 *     url?: string,
 *     slug?: string,
 *     config?: bool,
 * }
 */
class AdminTabs
{
    /** @var list<TabSpec> */
    private array $tabs = [];

    /**
     * @param list<TabSpec> $tabs
     * @param CUserManager  $userbank
     * @param Smarty        $theme
     * @param string|null   $activeSlug Slug of the section to mark with
     *     `aria-current="page"`. When null, the first accessible tab's
     *     slug wins. When the value doesn't match any visible tab no
     *     tab is marked active (and the strip falls back to its
     *     inactive look — still better than every tab looking
     *     identical).
     */
    public function __construct(array $tabs, $userbank, $theme, ?string $activeSlug = null)
    {
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

        $theme->assign('tabs', $this->tabs);
        $theme->assign('active_tab', $resolvedActive);
        $theme->display('core/admin_tabs.tpl');
    }
}
