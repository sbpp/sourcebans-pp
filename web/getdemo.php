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

require_once("init.php");

if (!isset($_GET['id']) || !isset($_GET['type'])) {
    die('No id or type parameter.');
}
if (strcasecmp($_GET['type'], "B") != 0 && strcasecmp($_GET['type'], "S") != 0) {
    die('Bad type');
}
$id   = (int) $_GET['id'];
$demo = $GLOBALS['db']->GetRow("SELECT filename, origname FROM `" . DB_PREFIX . "_demos` WHERE demtype=? AND demid=?;", array(
    $_GET['type'],
    $id
));
//Official Fix: https://code.google.com/p/sourcebans/source/detail?r=165
if (!$demo) {
    die('Demo not found.');
}
$demo['filename'] = basename($demo['filename']);
if (!in_array($demo['filename'], scandir(SB_DEMOS)) || !file_exists(SB_DEMOS . "/" . $demo['filename'])) {
    die('File not found.');
}

header('Content-type: application/force-download');
header('Content-Transfer-Encoding: Binary');
header('Content-disposition: attachment; filename="' . $demo['origname'] . '"');
header("Content-Length: " . filesize(SB_DEMOS . "/" . $demo['filename']));
readfile(SB_DEMOS . "/" . $demo['filename']);
