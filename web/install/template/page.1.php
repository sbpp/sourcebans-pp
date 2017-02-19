<?php
if (!defined("IN_SB")) {
    echo "You should not be here. Only follow links!";
    die();
}
?>
    <b><p>To use this webpanel software, you are required to read and accept the following license. If you do not agree with the license, then go and make your own ban/admin system.<br /><br />
    An explanation  of this license is available <u><a href="https://creativecommons.org/licenses/by-nc-sa/3.0/">here</a></u>.</p></b>

        <table style="width: 101%; margin: 0 0 -2px -2px;">
            <tr>
                <td colspan="3" class="listtable_top"><b>Creative Commons - Attribution-NonCommercial-ShareAlike 3.0</b></td>
            </tr>
        </table>
        <div id="submit-main">
        <div align="center">
        <textarea cols="105" rows="32">
This program is part of SourceBans++.

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

    SourceComms 0.9.266
    Copyright (C) 2013-2014 Alexandr Duplishchev
    Licensed under GNU GPL version 3, or later.
    Page: <https://forums.alliedmods.net/showthread.php?p=1883705> - <https://github.com/d-ai/SourceComms>

    SourceBans TF2 Theme v1.0
    Copyright © 2014 IceMan
    Page: <https://forums.alliedmods.net/showthread.php?t=252533>
        </textarea>
        <br /><br />

<form action="index.php?p=submit" method="POST" enctype="multipart/form-data">
            <input type="checkbox" name="accept" id="accept" /><span style="cursor:pointer;" onclick="($('accept').checked?$('accept').checked=false:$('accept').checked=true)"> I have read, and accept the license</span>
            <br/><br/>
            <input type="button" TABINDEX=2 onclick="checkAccept()" name="button" class="btn ok" id="button" value="Ok" />
        </div>
    </div>
</form>
<script type="text/javascript">
$E('html').onkeydown = function(event){
	var event = new Event(event);
    if (event.key == 'enter' ) checkAccept();
};
function checkAccept()
{
	if($('accept').checked)
        window.location = "index.php?step=2";
    else {
        ShowBox('Error', 'If you do not accept the license, you may NOT install this web panel.', 'red', '', true);
    }
}
</script>
