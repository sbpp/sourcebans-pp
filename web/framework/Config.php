<?php

class Config
{
    private $config = [];

    public function __construct($db)
    {
        $db->query('SELECT * FROM `:prefix_settings` GROUP BY `setting`, `value`');
        foreach ($db->resultset() as $rule) {
            $this->config[$rule['setting']] = $rule['value'];
        }
    }

    public function get($setting)
    {
        return (array_key_exists($setting, $this->config)) ? $this->config[$setting] : false;
    }
}
