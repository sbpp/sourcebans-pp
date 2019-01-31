<?php

class SteamAuthHandler
{
    private $openid = null;
    private $dbs = null;

    public function __construct(\LightOpenID $openid, \Database $dbs)
    {
        $this->openid = $openid;
        $this->dbs = $dbs;

        if ($this->openid->validate()) {
            $steamid = $this->validate();
            if ($steamid) {
                $this->check($steamid);
            }
        } elseif (!$this->openid->mode) {
            $this->login();
        }
    }

    private function login()
    {
        $this->openid->identity = 'https://steamcommunity.com/openid';
        header("Location: ".$this->openid->authUrl());
    }

    private function validate()
    {
        $pattern = "/^https:\/\/steamcommunity\.com\/openid\/id\/(7[0-9]{15,25}+)$/";
        preg_match($pattern, $this->openid->identity, $match);

        return (!empty($match[1])) ? $match[1] : false;
    }

    private function check(string $steamid)
    {
        $steamid = \SteamID\SteamID::toSteam2($steamid);

        $this->dbs->query('SELECT aid FROM `:prefix_admins` WHERE authid = :authid');
        $this->dbs->bind(':authid', $steamid);
        $result = $this->dbs->single();

        if (!empty($result['aid']) && !is_null($result['aid'])) {
            $maxlife = Config::get('auth.maxlife.steam') * 60;
            Auth::login($result['aid'], $maxlife);
            header("Location: ".Host::complete());
            return;
        }

        header("Location: ".Host::complete()."/index.php?p=login&m=steam_failed");
    }
}
