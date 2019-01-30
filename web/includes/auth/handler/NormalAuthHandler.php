<?php

class NormalAuthHandler
{
    private $dbs = null;
    private $result = false;

    public function __construct(\Database $dbs, string $username, string $password, bool $remember)
    {
        $this->dbs = $dbs;
        $user = $this->getInfosFromDatabase($username);

        $maxlife = (($remember) ? Config::get('auth.maxlife.remember') : Config::get('auth.maxlife'))*60;

        if (!empty($password) && (!empty($user['password']) || !is_null($user['password']))) {
            if ($this->checkPassword($password, $user['password'])) {
                $this->result = true;
                Auth::login($user['aid'], $maxlife);
            } elseif ($this->legacyPasswordCheck($password, $user['password'])) {
                $this->result = true;
                $this->updatePasswordHash($password, $user['aid']);
                Auth::login($user['aid'], $maxlife);
            }
        }
    }

    public function getResult()
    {
        return $this->result;
    }

    private function checkPassword(string $password, string $hash)
    {
        return (bool)(password_verify($password, $hash));
    }

    private function legacyPasswordCheck(string $password, string $hash)
    {
        $crypt = @crypt($password, SB_NEW_SALT);
        $sha1 = @sha1(sha1('SourceBans' . $password));

        return (bool)(hash_equals($crypt, $hash) || hash_equals($sha1, $hash));
    }

    private function updatePasswordHash(string $password, int $aid)
    {
        $this->dbs->query("UPDATE `:prefix_admins` SET password = :password WHERE aid = :aid");
        $this->dbs->bind(':aid', $aid, \PDO::PARAM_INT);
        $this->dbs->bind(':password', password_hash($password, PASSWORD_BCRYPT), \PDO::PARAM_STR);
        $this->dbs->execute();
    }

    private function getInfosFromDatabase(string $username)
    {
        $this->dbs->query("SELECT aid, password FROM `:prefix_admins` WHERE user = :username");
        $this->dbs->bind(':username', $username, \PDO::PARAM_STR);
        return $this->dbs->single();
    }
}
