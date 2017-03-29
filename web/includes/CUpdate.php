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

class CUpdater
{
    private $store=0;

    public function __construct()
    {
        if (!is_numeric($this->getCurrentRevision())) {
            $this->_updateVersionNumber(0); // Set at 0 initially, this will cause all database updates to be run
        } elseif ($this->getCurrentRevision() == -1) { // They have some fubar version fix it for them :|
            $GLOBALS['db']->Execute("INSERT INTO `".DB_PREFIX."_settings` (`setting`, `value`) VALUES ('config.version', '0')");
        }
    }

    public function getLatestPackageVersion()
    {
        $retval = 0;
        foreach ($this->_getStore() as $version => $key) {
            if ($version > $retval) {
                $retval = $version;
            }
        }
        return $retval;
    }

    public function doUpdates()
    {
        $retstr = "";
        $error = false;
        $i = 0;
        foreach ($this->_getStore() as $version => $key) {
            if ($version > $this->getCurrentRevision()) {
                $i++;
                $retstr .= "Running update: <b>v" . $version . "</b>... ";
                if (!include(ROOT . "updater/data/" . $key)) {
                    // OHSHI! Something went tits up :(
                    $retstr .= "<b>Error executing: /updater/data/" . $key . ". Stopping Update!</b>";
                    $error = true;
                    break;
                }
                // File was executed successfully
                $retstr .= "Done.<br /><br />";
                $this->_updateVersionNumber($version);
            }
        }
        if ($i == 0) {
            $retstr .= "<br />Nothing to update...";
            return $retstr;
        }
        if (!$error) {
            $retstr .= "<br />Updated successfully. Please delete the /updater folder.";
            return $retstr;
        }
        $retstr .= "<br />Update Failed.";
        return $retstr;
    }

    public function getCurrentRevision()
    {
        return (isset($GLOBALS['config']['config.version']))?$GLOBALS['config']['config.version']:-1;
    }

    public function needsUpdate()
    {
        return($this->getLatestPackageVersion() > $this->getCurrentRevision());
    }

    private function _getStore()
    {
        if ($this->store==0) {
            return include ROOT . "/updater/store.php";
        }
        return $this->store;
    }

    private function _updateVersionNumber($rev)
    {
        $ret = $GLOBALS['db']->Execute("UPDATE ".DB_PREFIX."_settings SET value = ? WHERE setting = 'config.version';", array((int)$rev));
        return !(empty($ret));
    }
}
