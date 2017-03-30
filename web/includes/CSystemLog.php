<?php
/*************************************************************************
	This file is part of SourceBans++

	Copyright © 2014-2016 SourceBans++ Dev Team <https://github.com/sbpp>

	SourceBans++ is licensed under a
	Creative Commons Attribution-NonCommercial-ShareAlike 3.0 Unported License.

	You should have received a copy of the license along with this
	work.  If not, see <http://creativecommons.org/licenses/by-nc-sa/3.0/>.

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
		Licensed under CC BY-NC-SA 3.0
		Page: <http://www.sourcebans.net/> - <http://www.gameconnect.net/>
*************************************************************************/

class CSystemLog {
    private $log_list = array();
    private $type = "";
    private $title = "";
    private $msg = "";
    private $aid = 0;
    private $host = "";
    private $created = 0;
    private $parent_function = "";
    private $query = "";

    public function __construct($tpe="", $ttl="", $mg="", $done=true, $HideDebug = false)
    {
        global $userbank;
        if (!empty($tpe) && !empty($ttl) && !empty($mg)) {
            $this->type = $tpe;
            $this->title = $ttl;
            $this->msg = $mg;
            // if (!$HideDebug && ((isset($_GET['debug']) && $_GET['debug'] == 1) || defined("DEVELOPER_MODE")))
            // {
            //     echo "CSystemLog: " . $mg;
            // }

            if (!$userbank) {
                return false;
            }

            $this->aid =  $userbank->GetAid()?$userbank->GetAid():"-1";
            $this->host = $_SERVER['REMOTE_ADDR'];
            $this->created = time();
            $this->parent_function = $this->_getCaller();
            $this->query = isset($_SERVER['QUERY_STRING'])?$_SERVER['QUERY_STRING']:'';
            if (isset($done) && $done == true) {
                $this->WriteLog();
            }
        }
    }

    public function AddLogItem($tpe, $ttl, $mg)
    {
        $item = array();
        $item['type'] = $tpe;
        $item['title'] = $ttl;
        $item['msg'] = $mg;
        $item['aid'] =  SB_AID;
        $item['host'] = $_SERVER['REMOTE_ADDR'];
        $item['created'] = time();
        $item['parent_function'] = $this->_getCaller();
        $item['query'] = $_SERVER['QUERY_STRING'];

        array_push($this->log_list, $item);
    }

    public function WriteLogEntries()
    {
        $this->log_list = array_unique($this->log_list);
        foreach ($this->log_list as $logentry) {
            if (!$logentry['query']) {
                $logentry['query'] = "N/A";
            }
            if (isset($GLOBALS['db'])) {
                $sm_log_entry = $GLOBALS['db']->Prepare("INSERT INTO ".DB_PREFIX."_log(type,title,message, function, query, aid, host, created)
						VALUES (?,?,?,?,?,?,?,?)");
                        $GLOBALS['db']->Execute($sm_log_entry, array($logentry['type'], $logentry['title'], $logentry['msg'], (string)$logentry['parent_function'],$logentry['query'], $logentry['aid'], $logentry['host'], $logentry['created']));
            }
        }
        unset($this->log_list);
    }

    public function WriteLog()
    {
        if (!$this->query) {
            $this->query = "N/A";
        }
        if (isset($GLOBALS['db'])) {
            $sm_log_entry = $GLOBALS['db']->Prepare("INSERT INTO ".DB_PREFIX."_log(type,title,message, function, query, aid, host, created)
						VALUES (?,?,?,?,?,?,?,?)");
            $GLOBALS['db']->Execute($sm_log_entry, array($this->type, $this->title, $this->msg, (string)$this->parent_function,$this->query, $this->aid, $this->host, $this->created));
        }
    }

    private function _getCaller()
    {
        $bt = debug_backtrace();

        $functions = isset($bt[2]['file'])?$bt[2]['file'] . " - " . $bt[2]['line'] . "<br />":'';
        $functions .= isset($bt[3]['file'])?$bt[3]['file'] . " - " . $bt[3]['line'] . "<br />":'';
        $functions .= isset($bt[4]['file'])?$bt[4]['file'] . " - " . $bt[4]['line'] . "<br />":'';
        $functions .= isset($bt[5]['file'])?$bt[5]['file'] . " - " . $bt[5]['line'] . "<br />":'';
        $functions .= isset($bt[6]['file'])?$bt[6]['file'] . " - " . $bt[6]['line'] . "<br />":'';
        return $functions;
    }

    public function GetAll($start, $limit, $searchstring = "")
    {
        if (!is_object($GLOBALS['db'])) {
            return false;
        }

        $start = (int)$start;
        $limit = (int)$limit;
        $sm_logs = $GLOBALS['db']->GetAll("SELECT ad.user, l.type, l.title, l.message, l.function, l.query, l.host, l.created, l.aid
										   FROM ".DB_PREFIX."_log AS l
										   LEFT JOIN ".DB_PREFIX."_admins AS ad ON l.aid = ad.aid
										   ".$searchstring."
										   ORDER BY l.created DESC
										   LIMIT $start, $limit");
        return $sm_logs;
    }

    public function LogCount($searchstring="")
    {
        $sm_logs = $GLOBALS['db']->GetRow("SELECT count(l.lid) AS count FROM ".DB_PREFIX."_log AS l".$searchstring);
        return $sm_logs[0];
    }

    public function CountLogList()
    {
        return count($this->log_list);
    }
}
