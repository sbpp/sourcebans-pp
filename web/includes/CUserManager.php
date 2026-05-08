<?php

use Lcobucci\JWT\Token;

/*************************************************************************
This file is part of SourceBans++

SourceBans++ (c) 2014-2024 by SourceBans++ Dev Team

The SourceBans++ Web panel is licensed under a
Creative Commons Attribution-NonCommercial-ShareAlike 3.0 Unported License.

You should have received a copy of the license along with this
work.  If not, see <http://creativecommons.org/licenses/by-nc-sa/3.0/>.

This program is based off work covered by the following copyright(s):
SourceBans 1.4.11
Copyright © 2007-2014 SourceBans Team - Part of GameConnect
Licensed under CC-BY-NC-SA 3.0
Page: <http://www.sourcebans.net/> - <http://www.gameconnect.net/>
*************************************************************************/
final class CUserManager
{
    private readonly int $aid;

    private array $admins = [];

    private readonly Database $dbh;

    public function __construct(?Token $token)
    {
        $this->dbh = new Database(DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS, DB_PREFIX, DB_CHARSET);

        $this->aid = (int)($token?->claims()->get('aid') ?? -1);

        $this->GetUserArray($this->aid);
    }

    /**
     * Gets all user details from the database, saves them into
     * the admin array 'cache', and then returns the array.
     */
    public function GetUserArray(?int $aid = null): array|false
    {
        $aid ??= $this->aid;
        // Invalid aid
        if ($aid < 0 || empty($aid)) {
            return false;
        }

        // We already got the data from the DB, and its saved in the manager
        if (isset($this->admins[$aid]) && !empty($this->admins[$aid])) {
            return $this->admins[$aid];
        }
        // Not in the manager, so we need to get them from DB
        $this->dbh->query("SELECT adm.user user, adm.authid authid, adm.password password, adm.gid gid, adm.email email, adm.validate validate, adm.extraflags extraflags,
							adm.immunity admimmunity,sg.immunity sgimmunity, adm.srv_password srv_password, adm.srv_group srv_group, adm.srv_flags srv_flags,sg.flags sgflags,
							wg.flags wgflags, wg.name wgname, adm.lastvisit lastvisit
							FROM `:prefix_admins` AS adm
							LEFT JOIN `:prefix_groups` AS wg ON adm.gid = wg.gid
							LEFT JOIN `:prefix_srvgroups` AS sg ON adm.srv_group = sg.name
							WHERE adm.aid = :aid");
        $this->dbh->bind(':aid', $aid);
        $res = $this->dbh->single();

        if (!$res) {
            return false;  // ohnoes some type of db error
        }

        $user = [];
        //$user['user'] = stripslashes($res[0]);
        $user['aid'] = $aid; //immediately obvious
        $user['user'] = $res['user'];
        $user['authid'] = $res['authid'];
        $user['password'] = $res['password'];
        $user['gid'] = $res['gid'];
        $user['email'] = $res['email'];
        $user['validate'] = $res['validate'];
        $user['extraflags'] = ((int) $res['extraflags'] | (int) $res['wgflags']);

        $user['srv_immunity'] = (int) $res['sgimmunity'];

        if ((int) $res['admimmunity'] > (int) $res['sgimmunity']) {
            $user['srv_immunity'] = (int) $res['admimmunity'];
        }

        $user['srv_password'] = $res['srv_password'];
        $user['srv_groups'] = $res['srv_group'];
        $user['srv_flags'] = $res['srv_flags'] . $res['sgflags'];
        $user['group_name'] = $res['wgname'];
        $user['lastvisit'] = $res['lastvisit'];
        $this->admins[$aid] = $user;
        return $user;
    }


    /**
     * Will check to see if an admin has any of the flags given.
     *
     * Accepts three call shapes (issue #1290 phase D.4):
     * - A single `WebPermission` enum case:
     *   `HasAccess(WebPermission::Owner)`.
     * - An int bitmask (legacy `ADMIN_OWNER | ADMIN_ADD_BAN` or
     *   `WebPermission::mask(WebPermission::Owner, WebPermission::AddBan)`).
     * - A SourceMod-style char-flag string ("abc"): each char is
     *   checked against the admin's `srv_flags` column.
     *
     * The `int|string` shapes are kept verbatim from the legacy
     * pre-enum world; the enum form is the modern path. Both
     * coexist intentionally — `init.php`'s `define`d `ADMIN_*`
     * constants are still emitted, so procedural code keeps
     * working. For a multi-flag check, prefer
     * `HasAccess(WebPermission::mask(WebPermission::Owner, WebPermission::AddBan))`
     * over the bare `int|` shape: the explicit `mask()` call documents
     * intent at the call site without losing the legacy back-compat.
     */
    public function HasAccess(WebPermission|int|string $flags, ?int $aid = null): bool
    {
        $aid ??= $this->aid;

        if ($flags instanceof WebPermission) {
            $flags = $flags->value;
        }

        if (empty($flags) || $aid <= 0) {
            return false;
        }

        if (!isset($this->admins[$aid])) {
            $this->GetUserArray($aid);
        }

        if (is_numeric($flags)) {
            return ((int) $this->admins[$aid]['extraflags'] & (int) $flags) !== 0;
        }

        $srvFlags = (string) ($this->admins[$aid]['srv_flags'] ?? '');
        $flagsStr = (string) $flags;
        for ($i=0; $i < strlen($srvFlags); $i++) {
            for ($a=0; $a < strlen($flagsStr); $a++) {
                if (str_contains($srvFlags[$i], $flagsStr[$a])) {
                    return true;
                }
            }
        }
        return false;
    }


    /**
     * Gets a 'property' from the user array eg. 'authid'.
     */
    public function GetProperty(string $name, ?int $aid = null): mixed
    {
        $aid ??= $this->aid;
        if (empty($name) || $aid < 0) {
            return false;
        }

        if (!isset($this->admins[$aid])) {
            $this->GetUserArray($aid);
        }

        return $this->admins[$aid][$name] ?? null;
    }

    /**
     * @return bool
     */
    public function is_logged_in(): bool
    {
        return $this->aid !== -1;
    }

    /**
     * @param int|null $aid
     * @return bool
     */
    public function is_admin($aid = null): bool
    {
        $aid ??= $this->aid;

        return $this->HasAccess(ALL_WEB, $aid);
    }


    public function GetAid(): int
    {
        return $this->aid;
    }


    public function GetAllAdmins(): array
    {
        $this->dbh->query('SELECT aid FROM `:prefix_admins`');
        $res = $this->dbh->resultset();
        foreach ($res as $admin) {
            $this->GetUserArray($admin['aid']);
        }
        return $this->admins;
    }


    public function GetAdmin(?int $aid = null): array|false
    {
        $aid ??= $this->aid;
        if ($aid < 0) {
            return false;
        }

        if (!isset($this->admins[$aid])) {
            $this->GetUserArray($aid);
        }
        return $this->admins[$aid];
    }

    public function isNameTaken(string $name): bool
    {
        $this->dbh->query("SELECT 1 FROM `:prefix_admins` WHERE user = :user");
        $this->dbh->bind(':user', $name);
        $data = $this->dbh->single(fetchType: PDO::FETCH_COLUMN);

        return $data === 1;
    }

    public function isSteamIDTaken(string $steamid): bool
    {
        $this->dbh->query("SELECT 1 FROM `:prefix_admins` WHERE authid = :steamid");
        $this->dbh->bind(':steamid', $steamid);
        $data = $this->dbh->single(fetchType: PDO::FETCH_COLUMN);

        return $data === 1;
    }

    public function isEmailTaken(string $email): bool
    {
        $this->dbh->query("SELECT 1 FROM `:prefix_admins` WHERE email = :email");
        $this->dbh->bind(':email', $email);
        $data = $this->dbh->single(fetchType: PDO::FETCH_COLUMN);

        return $data === 1;
    }

    public function isCurrentPasswordValid(int $aid, string $pass): bool
    {
        $this->dbh->query("SELECT password FROM `:prefix_admins` WHERE aid = :aid");
        $this->dbh->bind(':aid', $aid);
        $hash = $this->dbh->single();
        return password_verify($pass, $hash['password']);
    }

    public function AddAdmin(string $name, string $steam, string $password, string $email, int $web_group, int $web_flags, string $srv_group, string $srv_flags, int $immunity, string $srv_password): int
    {
        if (!empty($password) && strlen((string) $password) < MIN_PASS_LENGTH) {
            throw new RuntimeException('Password must be at least ' . MIN_PASS_LENGTH . ' characters long.');
        }
        if (empty($password)) {
            throw new RuntimeException('Password cannot be empty.');
        }
        $this->dbh->query('INSERT INTO `:prefix_admins` (user, authid, password, gid, email, extraflags, immunity, srv_group, srv_flags, srv_password)
                           VALUES (:user, :authid, :password, :gid, :email, :extraflags, :immunity, :srv_group, :srv_flags, :srv_password)');
        $this->dbh->bind(':user', $name);
        $this->dbh->bind(':authid', str_replace('STEAM_1', 'STEAM_0', $steam));
        $this->dbh->bind(':password', password_hash($password, PASSWORD_BCRYPT));
        $this->dbh->bind(':gid', $web_group);
        $this->dbh->bind(':email', $email);
        $this->dbh->bind(':extraflags', $web_flags);
        $this->dbh->bind(':immunity', $immunity);
        $this->dbh->bind(':srv_group', $srv_group);
        $this->dbh->bind(':srv_flags', $srv_flags);
        $this->dbh->bind(':srv_password', $srv_password);

        return ($this->dbh->execute()) ? (int)$this->dbh->lastInsertId() : -1;
    }
}
