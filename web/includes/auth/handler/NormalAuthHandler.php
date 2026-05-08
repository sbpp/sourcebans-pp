<?php

class NormalAuthHandler
{
    private $result = false;

    public function __construct(
        private Database $dbs,
        string $username, string $password, bool $remember
    )
    {
        $user = $this->getInfosFromDatabase($username);

        $maxlife = (($remember) ? Config::get('auth.maxlife.remember') : Config::get('auth.maxlife')) * 60;

        if (!$user || empty($password))
            return;

        if (!empty($password) && (!empty($user['password']) || $user['password'] !== null)) {
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
        $this->dbs->bindMultiple([
            ':password' => password_hash($password, PASSWORD_BCRYPT),
            ':aid'      => $aid,
        ]);
        return $this->dbs->execute();
    }

    private function getInfosFromDatabase(string $username)
    {
        $this->dbs->query("SELECT aid, password FROM `:prefix_admins` WHERE user = :user");
        $this->dbs->bind(':user', $username);
        return $this->dbs->single();
    }
}
