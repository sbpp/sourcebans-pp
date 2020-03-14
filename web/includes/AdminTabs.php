<?php

/**
 * Class AdminTabs
 */
class AdminTabs
{
    private $tabs = [];

    /**
     * AdminTabs constructor.
     *
     * @param array        $tabs
     * @param CUserManager $userbank
     * @param Smarty       $theme
     */
    public function __construct(array $tabs, $userbank, $theme)
    {
        foreach ($tabs as $tab) {
            if ($userbank->HasAccess($tab['permission'])) {
                if (!isset($tab['config']) || $tab['config']) {
                    $this->tabs[] = $tab;
                }
            }
        }

        $theme->assign('tabs', $this->tabs);
        $theme->display('core/admin_tabs.tpl');
    }
}
