<?php
class AdminTabs
{
    private $tabs = [];
    public function __construct(array $tabs, $userbank)
    {
        foreach ($tabs as $tab) {
            if ($userbank->HasAccess($tab['permission'])) {
                if (!isset($tab['config']) || $tab['config']) {
                    $this->tabs[] = $tab;
                }
            }
        }
        Template::render('core/admin_tabs', [
            'tabs' => $this->tabs
        ]);
    }
}
