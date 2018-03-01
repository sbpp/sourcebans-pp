<?php

class AccessManager
{
    private $aid = null;
    private $flags = null;

    public function __construct($aid)
    {
        if (filter_var($aid, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) {
            $this->aid = $aid;
        }
        $this->getFlags();
    }

    private function getFlags()
    {
        if (is_null($this->aid)) {
            return [];
        }

        Flight::db()->query(
            "SELECT adm.extraflags, wg.flags wgflags, sg.flags sgflags, adm.srv_flags
            FROM `:prefix_admins` AS adm
            LEFT JOIN `:prefix_groups` AS wg ON adm.gid = wg.gid
            LEFT JOIN `:prefix_srvgroups` AS sg ON adm.srv_group = sg.name
            WHERE adm.aid = :id"
        );
        Flight::db()->bind(':id', $this->aid);
        $data = Flight::db()->single();

        $this->flags = [
            'extraflags' => (intval($data['extraflags']) | intval($data['wgflags'])),
            'srv_flags' => $data['srv_flags'].$data['sgflags']
        ];
    }

    public function check($flags)
    {
        if (is_numeric($flags)) {
            return ($this->flags['extraflags'] & $flags) != 0 ? true : false;
        }

        for ($i = 0; $i < strlen($this->flags['srv_flags']); $i++) {
            for ($a = 0; $a < strlen($flags); $a++) {
                if (strstr($this->flags['srv_flags'][$i], $flags[$a])) {
                    return true;
                }
            }
        }
        return false;
    }
}
