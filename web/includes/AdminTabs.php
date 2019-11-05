<?php
class AdminTabs
{
    private $tabs = [];
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
