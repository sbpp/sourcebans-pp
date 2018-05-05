<?php

class Updater
{
    private $currentVersion = 0;
    private $latestVersion = 0;
    private $updateList = null;

    private $dbs = null;
    private $stack = [];

    public function __construct(Database $dbs)
    {
        $this->dbs = $dbs;

        $this->getUpdateList('store.json');
        $this->getLatestVersion();
        $this->getCurrentVersion();

        if (!is_numeric($this->currentVersion) || $this->currentVersion < 0) {
            $this->currentVersion = 0;
        }
        $this->update();
        $this->updateDBVersion($this->currentVersion);
    }

    private function getUpdateList($file)
    {
        if (file_exists($file)) {
            $this->updateList = json_decode(file_get_contents($file), true);
        }
    }

    private function getLatestVersion()
    {
        if (!is_null($this->updateList)) {
            $this->latestVersion = array_pop(array_keys($this->updateList));
        }
    }

    private function getCurrentVersion()
    {
        $this->dbs->query("SELECT value FROM `:prefix_settings` WHERE setting = 'config.version'");
        $version = $this->dbs->single();
        $this->currentVersion = (int)$version['value'];
    }

    private function updateDBVersion($version)
    {
        $this->dbs->query("UPDATE `:prefix_settings` SET value = :value WHERE setting = 'config.version'");
        $this->dbs->bind(':value', $version, \PDO::PARAM_INT);
        return $this->dbs->execute();
    }

    private function update()
    {
        $this->stack[] = "Checking current database version... <b> ".$this->currentVersion."</b>";

        if (!$this->needUpdate()) {
            $this->stack[] = 'Installation up-to-date.';
            return;
        }

        $this->stack[] = "Updating database to version: <b>".$this->latestVersion."</b>";

        foreach ($this->updateList as $version => $file) {
            if ($version > $this->currentVersion) {
                $this->stack[] = 'Running Update: <b>'.$version.'</b>';

                if (!file_exists('data/'.$file)) {
                    $this->stack[] = '<b>Error executing: /updater/data/'.$file.'</b>. Stopping Update!';
                    break;
                }

                $update = require_once('data/'.$file);

                $this->stack[] = ($update) ? 'Update <b>Done</b>.' : 'Update <b>Failed</b>!';
                if (!$update) {
                    break;
                }
                $this->currentVersion = $version;
            }
        }

        if (!$this->needUpdate()) {
            $this->stack[] = 'Updated successfully. Please delete the /updater folder.';
        }
    }

    public function getMessageStack()
    {
        return $this->stack;
    }

    public function needUpdate()
    {
        return ($this->currentVersion < $this->latestVersion) ? true : false;
    }
}
