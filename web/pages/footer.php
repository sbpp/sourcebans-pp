<?php
/*************************************************************************
This file is part of SourceBans++

Copyright � 2014-2016 SourceBans++ Dev Team <https://github.com/sbpp>

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
Copyright � 2007-2014 SourceBans Team - Part of GameConnect
Licensed under CC BY-NC-SA 3.0
Page: <http://www.sourcebans.net/> - <http://www.gameconnect.net/>
*************************************************************************/

if (!defined("IN_SB")) {
    echo "You should not be here. Only follow links!";
    die();
}

global $theme;
$theme->assign('SB_REV', (SB_DEV) ? "-git" . SB_GITREV : '');
$theme->assign('SB_VERSION', SB_VERSION);
$theme->assign('SB_QUOTE', CreateQuote());
$theme->display('page_footer.tpl');

if (isset($_GET['p'])) {
    $_SESSION['p'] = $_GET['p'];
}
if (isset($_GET['c'])) {
    $_SESSION['c'] = $_GET['c'];
}
if (isset($_GET['p']) && $_GET['p'] != "login") {
    $_SESSION['q'] = $_SERVER['QUERY_STRING'];
}
if (defined('DEVELOPER_MODE')) {
    global $start;
    $time      = microtime();
    $time      = explode(" ", $time);
    $time      = $time[1] + $time[0];
    $finish    = $time;
    $totaltime = ($finish - $start);
    printf("<h3>Page took %f seconds to load.</h3>", $totaltime);

    echo '<h3>User Manager Data</h3><pre>';
    PrintArray($userbank);
    echo '</pre><h3>Post Data</h3><pre>';
    print_r($_POST);
    echo '</pre><h3>Session Data</h3><pre>';
    print_r($_SESSION);
    echo '</pre> ';
    echo '</pre><h3>Cookie Data</h3><pre>';
    print_r($_COOKIE);
    echo '</pre> ';
}
?>
</div>
<script type="text/javascript">
var settab = ProcessAdminTabs();
window.addEvent('domready', function(){
<?php
if (isset($GLOBALS['server_qry'])) {
    echo $GLOBALS['server_qry'];
}
?>
        var Tips2 = new Tips($$('.tip'), {
            initialize:function(){
                this.fx = new Fx.Style(this.toolTip, 'opacity', {duration: 300, wait: false}).set(0);
            },
            onShow: function(toolTip) {
                this.fx.start(1);
            },
            onHide: function(toolTip) {
                this.fx.start(0);
            }
        });
        var Tips4 = new Tips($$('.perm'), {
            className: 'perm'
        });
    });
<?php
if (isset($GLOBALS['NavRewrite'])) {
    echo "$('nav').setHTML('" . $GLOBALS['NavRewrite'] . "');";
}
?>
    $('content_title').setHTML('<?=$GLOBALS['TitleRewrite']?>');


    <?php
    if (isset($GLOBALS['enable'])) {
    ?>
    if ($('<?=$GLOBALS['enable']?>')) {
        if (settab != -1) {
            $(settab).setStyle('display', 'block');
        } else {
            $('<?=$GLOBALS['enable']?>').setStyle('display', 'block');
        }
    }
    <?php
    }
    if (isset($_GET['o']) && $_GET['o'] == "rcon") {
        echo "var scroll = new Fx.Scroll($('rcon'),{duration: 500, transition: Fx.Transitions.Cubic.easeInOut});
        if(scroll)scroll.toBottom();";
    }
    ?>
    </script>
<?php
if (is_object($log)) {
    $log->WriteLogEntries();
}
?>
<!--[if lt IE 7]>
<script defer type="text/javascript" src="./scripts/pngfix.js"></script>
<![endif]-->
</body>
</html>
