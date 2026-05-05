<?php

/**
 * Class AdminTabs
 *
 * Renders the intra-page section nav (`core/admin_tabs.tpl`) used by
 * `?p=admin&c=bans`, `?p=admin&c=admins`, `?p=admin&c=groups`, and the
 * edit.* pages (which pass an empty `$tabs` array so only the Back
 * link renders).
 *
 * The optional `$activeTab` argument decides which tab gets
 * `aria-current="page"` (and the matching active-state styling). Pages
 * may pass it explicitly when they track the visible section (e.g.
 * via `?tab=…`); otherwise the first accessible tab is treated as
 * active so the strip never renders as an undifferentiated row of
 * identical buttons (#1186).
 */
class AdminTabs
{
    private array $tabs = [];

    /**
     * AdminTabs constructor.
     *
     * @param array        $tabs
     * @param CUserManager $userbank
     * @param Smarty       $theme
     * @param string|null  $activeTab Name of the tab to mark with
     *     `aria-current="page"`. When null, the first accessible tab
     *     wins. When the string doesn't match any visible tab no tab
     *     is marked active (and the strip falls back to its inactive
     *     look — still better than every tab looking identical).
     */
    public function __construct(array $tabs, $userbank, $theme, ?string $activeTab = null)
    {
        foreach ($tabs as $tab) {
            if ($userbank->HasAccess($tab['permission'])) {
                if (!isset($tab['config']) || $tab['config']) {
                    $this->tabs[] = $tab;
                }
            }
        }

        $resolvedActive = '';
        if ($activeTab !== null && $activeTab !== '') {
            $resolvedActive = $activeTab;
        } elseif (isset($this->tabs[0]['name']) && is_scalar($this->tabs[0]['name'])) {
            $resolvedActive = (string) $this->tabs[0]['name'];
        }

        $theme->assign('tabs', $this->tabs);
        $theme->assign('active_tab', $resolvedActive);
        $theme->display('core/admin_tabs.tpl');
    }
}
