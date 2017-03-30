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

class CUI
{
    public function drawButton($text, $click, $class, $ids = "", $submit = false)
    {
        $type = $submit ? "submit" : "button";
        $button = "<input type='$type' onclick=\"$click\" name='$ids' class='btn $class' onmouseover='ButtonOver(\"$ids\")' onmouseout='ButtonOver(\"$ids\")' id='$ids' value='$text' />";
        return $button;
    }

    public function drawInlineBox($title, $text, $color)
    {
        $icon = "";
        switch ($color) {
            case "red":
                $icon = "warning";
                break;
            case "blue":
                $icon = "info";
                break;
            case "green":
                $icon = "yay";
        }
        $text = '<div id="msg-'.$color.'-debug" style="">
				 <i><img src="./images/'.$icon.'.png" alt="MsgIcon" /></i>
				 <b>' . $title .'</b>
				 <br />
		 		' . $text . '</i>
				</div>';
        return $text;
    }
}
