<?php
/*************************************************************************
	This file is part of SourceBans++

	Copyright © 2014-2019 SourceBans++ Dev Team <https://github.com/sbpp>

	SourceBans++ is licensed under a
	GNU GENERAL PUBLIC LICENSE Version 3.

	You should have received a copy of the license along with this
	work.  If not, see <https://www.gnu.org/licenses/gpl-3.0.txt>.

	THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
	IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
	FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
	AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
	LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
	OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
	THE SOFTWARE.

	This program is based off work covered by the following copyright(s):
		SourceBans 1.4.11
		Copyright © 2007-2014 SourceBans Team - Part of GameConnect
		Licensed under GPLv3
		Page: <http://www.sourcebans.net/> - <http://www.gameconnect.net/>
*************************************************************************/

class CUserManager
{
    private $aid = -1;
    private $admins = array();
    private $dbh = null;

    /**
     * Class constructor
     *
     * @param $aid the current user's aid
     * @param $password the current user's password
     * @return noreturn.
     */
    public function __construct($token)
    {
        $this->dbh = new Database(DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS, DB_PREFIX, DB_CHARSET);

        $this->aid = ((bool)$token) ? $token->getClaim('aid') : -1;

        $this->GetUserArray($this->aid);
    }


    /**
     * Gets all user details from the database, saves them into
     * the admin array 'cache', and then returns the array
     *
     * @param $aid the ID of admin to get info for.
     * @return array.
     */
    public function GetUserArray($aid = null)
    {
        if (is_null($aid)) {
            $aid = $this->aid;
        }
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

        $user = array();
        //$user['user'] = stripslashes($res[0]);
        $user['aid'] = $aid; //immediately obvious
        $user['user'] = $res['user'];
        $user['authid'] = $res['authid'];
        $user['password'] = $res['password'];
        $user['gid'] = $res['gid'];
        $user['email'] = $res['email'];
        $user['validate'] = $res['validate'];
        $user['extraflags'] = (intval($res['extraflags']) | intval($res['wgflags']));

        $user['srv_immunity'] = intval($res['sgimmunity']);

        if (intval($res['admimmunity']) > intval($res['sgimmunity'])) {
            $user['srv_immunity'] = intval($res['admimmunity']);
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
     * Will check to see if an admin has any of the flags given
     *
     * @param $flags The flags to check for.
     * @param $aid The user to check flags for.
     * @return boolean.
     */
    public function HasAccess($flags, $aid = null)
    {
        if (is_null($aid)) {
            $aid = $this->aid;
        }

        if (empty($flags) || $aid <= 0) {
            return false;
        }

        if (!isset($this->admins[$aid])) {
            $this->GetUserArray($aid);
        }

        if (is_numeric($flags)) {
            return ($this->admins[$aid]['extraflags'] & $flags) != 0 ? true : false;
        }

        for ($i=0; $i < strlen($this->admins[$aid]['srv_flags']); $i++) {
            for ($a=0; $a < strlen($flags); $a++) {
                if (strstr($this->admins[$aid]['srv_flags'][$i], $flags[$a])) {
                    return true;
                }
            }
        }
    }


    /**
     * Gets a 'property' from the user array eg. 'authid'
     *
     * @param $aid the ID of admin to get info for.
     * @return mixed.
     */
    public function GetProperty($name, $aid = null)
    {
        if (is_null($aid)) {
            $aid = $this->aid;
        }
        if (empty($name) || $aid < 0) {
            return false;
        }

        if (!isset($this->admins[$aid])) {
            $this->GetUserArray($aid);
        }

        return $this->admins[$aid][$name];
    }

    public function is_logged_in()
    {
        if ($this->aid != -1) {
            return true;
        }
        return false;
    }

    public function is_admin($aid = null)
    {
        if (is_null($aid)) {
            $aid = $this->aid;
        }

        if ($this->HasAccess(ALL_WEB, $aid)) {
            return true;
        }
        return false;
    }


    public function GetAid()
    {
        return $this->aid;
    }


    public function GetAllAdmins()
    {
        $this->dbh->query('SELECT aid FROM `:prefix_admins`');
        $res = $this->dbh->resultset();
        foreach ($res as $admin) {
            $this->GetUserArray($admin['aid']);
        }
        return $this->admins;
    }


    public function GetAdmin($aid = null)
    {
        if (is_null($aid)) {
            $aid = $this->aid;
        }
        if ($aid < 0 || !is_int($aid)) {
            return false;
        }

        if (!isset($this->admins[$aid])) {
            $this->GetUserArray($aid);
        }
        return $this->admins[$aid];
    }

    public function isNameTaken($name)
    {
        $this->dbh->query("SELECT 1 FROM `:prefix_admins` WHERE user = :user");
        $this->dbh->bind(':user', $name);
        $data = $this->dbh->single();

        return (bool)$data[1];
    }

    public function isSteamIDTaken($steamid)
    {
        $this->dbh->query("SELECT 1 FROM `:prefix_admins` WHERE authid = :steamid");
        $this->dbh->bind(':steamid', $steamid);
        $data = $this->dbh->single();

        return (bool)$data[1];
    }

    public function isEmailTaken($email)
    {
        $this->dbh->query("SELECT 1 FROM `:prefix_admins` WHERE email = :email");
        $this->dbh->bind(':email', $email);
        $data = $this->dbh->single();

        return (bool)$data[1];
    }

    public function isCurrentPasswordValid($aid, $pass)
    {
        $this->dbh->query("SELECT password FROM `:prefix_admins` WHERE aid = :aid");
        $this->dbh->bind(':aid', $aid);
        $hash = $this->dbh->single();
        return password_verify($pass, $hash['password']);
    }

    public function AddAdmin($name, $steam, $password, $email, $web_group, $web_flags, $srv_group, $srv_flags, $immunity, $srv_password)
    {
        if (!empty($password) && strlen($password) < MIN_PASS_LENGTH) {
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
