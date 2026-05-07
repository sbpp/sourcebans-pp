<?php
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

global $userbank, $theme;

use Sbpp\Mail\EmailType;
use Sbpp\Mail\Mail;
use Sbpp\Mail\Mailer;
use xPaw\SourceQuery\SourceQuery;

if (!defined("IN_SB")) {
    echo "You should not be here. Only follow links!";
    die();
}

/**
 * Emits an inline `<script>` that surfaces a toast via
 * `window.SBPP.showToast` (theme.js) on the current response, with
 * `sb.message.*` (sb.js) as a fallback. Replaces the v1.x-era
 * `<script>ShowBox(…)</script>` calls — `ShowBox` shipped in
 * `web/scripts/sourcebans.js`, which was deleted in #1123 D1 / #1160,
 * so the legacy callsite throws ReferenceError and silently swallows
 * the message in the sbpp2026 chrome (#1176).
 *
 * No redirect: the submit page re-renders its own form on the same URL
 * (validation-error replay or post-success reset to empty values), so
 * the toast fires inline on whichever response the browser is already
 * loading. The helper waits for `DOMContentLoaded` before calling
 * `window.SBPP.showToast` because page handlers emit this `<script>`
 * mid-body, before `core/footer.tpl` includes `theme.js` — the IIFE
 * runs synchronously during parse, when `window.SBPP` is still
 * undefined.
 *
 * `$kind` is one of `'info' | 'success' | 'warn' | 'error'`, matching
 * the `ToastOpts.kind` typedef in `web/themes/default/js/theme.js`.
 *
 * Strings are JSON-encoded with the same flag set as the canonical
 * helper in `web/pages/admin.edit.ban.php` so embedded quotes /
 * newlines / non-ASCII bytes survive the round-trip into the script
 * body without further escaping.
 */
function emitSubmitToast(string $kind, string $title, string $body): void
{
    $flags = JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
    $kindJs = json_encode($kind, $flags);
    $titleJs = json_encode($title, $flags);
    $bodyJs = json_encode($body, $flags);
    echo <<<HTML
<script>
(function () {
    var kind = {$kindJs};
    var title = {$titleJs};
    var body = {$bodyJs};
    function show() {
        var SBPP = window.SBPP;
        if (SBPP && typeof SBPP.showToast === 'function') {
            SBPP.showToast({ kind: kind, title: title, body: body });
            return;
        }
        if (window.sb && window.sb.message) {
            var fn = (kind === 'error') ? window.sb.message.error
                : (kind === 'success') ? window.sb.message.success
                : window.sb.message.info;
            if (typeof fn === 'function') fn(title, body || '');
        }
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', show);
    } else {
        show();
    }
})();
</script>
HTML;
}

if (!Config::getBool('config.enablesubmit')) {
    emitSubmitToast('error', 'Submissions disabled', 'This page is disabled. You should not be here.');
    PageDie();
}
if (!isset($_POST['subban']) || $_POST['subban'] != 1) {
    $SteamID       = "";
    $BanIP         = "";
    $PlayerName    = "";
    $BanReason     = "";
    $SubmitterName = "";
    $Email         = "";
    $SID           = -1;
} else {
    $SteamID       = trim((string) ($_POST['SteamID']    ?? ''));
    $BanIP         = trim((string) ($_POST['BanIP']      ?? ''));
    $PlayerName    = (string) ($_POST['PlayerName']  ?? '');
    $BanReason     = (string) ($_POST['BanReason']   ?? '');
    $SubmitterName = (string) ($_POST['SubmitName']  ?? '');
    $Email         = trim((string) ($_POST['EmailAddr']  ?? ''));
    $SID           = (int) ($_POST['server']         ?? -1);
    $validsubmit   = true;
    $errors        = "";
    if ((strlen($SteamID) != 0 && $SteamID != "STEAM_0:") && !\SteamID\SteamID::isValidID($SteamID)) {
        $errors .= '* Please type a valid STEAM ID.<br>';
        $validsubmit = false;
    }
    if (strlen($BanIP) != 0 && !filter_var($BanIP, FILTER_VALIDATE_IP)) {
        $errors .= '* Please type a valid IP-address.<br>';
        $validsubmit = false;
    }
    // #1207 PUB-4: at least one of Steam ID / IP must be provided.
    // Mirrors the inline guard in `page_submitban.tpl` so JS-off
    // visitors can't sneak an empty pair past the form. The "STEAM_0:"
    // sentinel is what the page handler re-emits when the user blanked
    // the Steam ID after a previous bounce (see `STEAMID:` assignment
    // around L293 below) — treat it as empty for this rule too.
    if (
        (strlen($SteamID) == 0 || $SteamID == "STEAM_0:")
        && strlen($BanIP) == 0
    ) {
        $errors .= '* Please enter a Steam ID or an IP address before submitting.<br>';
        $validsubmit = false;
    }
    if (strlen($PlayerName) == 0) {
        $errors .= '* You must include a player name<br>';
        $validsubmit = false;
    }
    if (strlen($BanReason) == 0) {
        $errors .= '* You must include comments<br>';
        $validsubmit = false;
    }
    if (!filter_var($Email, FILTER_VALIDATE_EMAIL)) {
        $errors .= '* You must include a valid email address<br>';
        $validsubmit = false;
    }
    if ($SID == -1) {
        $errors .= '* Please select a server.<br>';
        $validsubmit = false;
    }
    if (!empty($_FILES['demo_file']['name'])) {
        if (!checkExtension($_FILES['demo_file']['name'], ['zip', 'rar', 'dem', '7z', 'bz2', 'gz'])) {
            $errors .= '* A demo can only be a dem, zip, rar, 7z, bz2 or a gz filetype.<br>';
            $validsubmit = false;
        }
    }
    $GLOBALS['PDO']->query("SELECT length FROM `:prefix_bans` WHERE authid = :authid AND RemoveType IS NULL");
    $GLOBALS['PDO']->bind(':authid', $SteamID);
    $checkres = $GLOBALS['PDO']->resultset();
    if (count($checkres) == 1 && $checkres[0]['length'] == 0) {
        $errors .= '* The player is already banned permanent.<br>';
        $validsubmit = false;
    }


    if (!$validsubmit) {
        // Validation errors are accumulated as `* msg<br>` HTML
        // fragments (legacy ShowBox markup). Convert <br> separators
        // to plain spaces so the toast `body` (rendered as text via
        // theme.js's escapeHtml) reads as a single line per error.
        emitSubmitToast(
            'error',
            'Please fix the following',
            (string) preg_replace('#<br\s*/?>#i', ' ', $errors)
        );
    }

    if ($validsubmit) {
        $filename = md5($SteamID . time());
        //echo SB_DEMOS."/".$filename;
        $demo     = move_uploaded_file($_FILES['demo_file']['tmp_name'], SB_DEMOS . "/" . $filename);
        if ($demo || empty($_FILES['demo_file']['name'])) {
            if ($SID != 0) {
                $GLOBALS['PDO']->query("SELECT ip, port FROM `:prefix_servers` WHERE sid = :sid");
                $GLOBALS['PDO']->bind(':sid', $SID);
                $server = $GLOBALS['PDO']->single();

                $query = new SourceQuery();
                try {
                    $query->Connect($server['ip'], $server['port'], 1, SourceQuery::SOURCE);
                    $info = $query->GetInfo();
                } catch (Exception $e) {
                    $mailserver = "Server: Error Connecting (".$server['ip'].":".$server['port'].")\n";
                } finally {
                    $query->Disconnect();
                }

                if (!empty($info['HostName'])) {
                    $mailserver = "Server: ".$info['HostName']." (".$server['ip'].":".$server['port'].")\n";
                } else {
                    $mailserver = "Server: Error Connecting (".$server['ip'].":".$server['port'].")\n";
                }

                $GLOBALS['PDO']->query("SELECT m.mid FROM `:prefix_servers` as s LEFT JOIN `:prefix_mods` as m ON m.mid = s.modid WHERE s.sid = :sid");
                $GLOBALS['PDO']->bind(':sid', $SID);
                $modid = $GLOBALS['PDO']->single();
            } else {
                $mailserver = "Server: Other server\n";
                $modid['mid']   = 0;
            }
            if ($SteamID == "STEAM_0:") {
                $SteamID = "";
            }
            $GLOBALS['PDO']->query("INSERT INTO `:prefix_submissions`(submitted,SteamId,name,email,ModID,reason,ip,subname,sip,archiv,server) VALUES (UNIX_TIMESTAMP(),?,?,?,?,?,?,?,?,0,?)")->execute([
                $SteamID,
                $PlayerName,
                $Email,
                $modid['mid'],
                $BanReason,
                $_SERVER['REMOTE_ADDR'],
                $SubmitterName,
                $BanIP,
                $SID,
            ]);
            $subid = (int) $GLOBALS['PDO']->lastInsertId();

            if (!empty($_FILES['demo_file']['name'])) {
                $GLOBALS['PDO']->query("INSERT INTO `:prefix_demos`(demid,demtype,filename,origname) VALUES (?, 'S', ?, ?)")->execute([
                    $subid,
                    $filename,
                    $_FILES['demo_file']['name'],
                ]);
            }
            $SteamID       = "";
            $BanIP         = "";
            $PlayerName    = "";
            $BanReason     = "";
            $SubmitterName = "";
            $Email         = "";
            $SID           = -1;

            // Send an email when ban was posted
            $headers = 'From: ' . SB_EMAIL . "\n" . 'X-Mailer: PHP/' . phpversion();

            $admins = $userbank->GetAllAdmins();
            $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '');
            $requri = substr($requestUri, 0, (int) strrpos($requestUri, ".php") - 5);
            $mailDests = [];

            foreach ($admins as $admin) {
                if ($userbank->HasAccess(ADMIN_OWNER | ADMIN_BAN_SUBMISSIONS, $admin['aid']) || $userbank->HasAccess(ADMIN_NOTIFY_SUB, $admin['aid'])) {
                    $mailDests []= $admin['email'];
                }
            }

            if (count($mailDests) > 0)
            {
                $demoLink = empty($_FILES['demo_file']['name']) ? 'no' : 'yes (http://' . $_SERVER['HTTP_HOST'] . $requri . 'getdemo.php?type=S&id=' . $subid . ')';

                $isEmailSent = Mail::send($mailDests, EmailType::BanSubmission, [
                    '{admin}' => 'admin',
                    '{name}' => $_POST['PlayerName'],
                    '{steamid}' => $_POST['SteamdID'] ?? 'NA',
                    '{demo}' => $demoLink,
                    '{server}' => $mailserver,
                    '{reason}' => $_POST['BanReason'],
                    '{home}' => Host::complete(true),
                    '{link}' => Host::complete(true) . '/index.php?p=admin&c=bans#%5E2'
                ]);
            }

            emitSubmitToast(
                'success',
                'Submitted',
                'Your submission has been added into the database, and will be reviewed by one of our admins.'
            );
        } else {
            emitSubmitToast(
                'error',
                'Upload failed',
                'There was an error uploading your demo to the server. Please try again later.'
            );
            Log::add("e", "Demo Upload Failed", "A demo failed to upload for a submission from ($Email)");
        }
    }
}

//serverlist
$GLOBALS['PDO']->query("SELECT sid, ip, port FROM `:prefix_servers` WHERE enabled = 1 ORDER BY modid, sid");
$servers = $GLOBALS['PDO']->resultset();

foreach ($servers as $key => $server) {
    $query = new SourceQuery();
    try {
        $query->Connect($server['ip'], $server['port'], 1, SourceQuery::SOURCE);
        $info = $query->GetInfo();
        $servers[$key]['hostname'] = $info['HostName'];
    } catch (Exception $e) {
        $servers[$key]['hostname'] = "Error Connecting (".$server['ip'].":".$server['port'].")";
    } finally {
        $query->Disconnect();
    }
}

\Sbpp\View\Renderer::render($theme, new \Sbpp\View\SubmitBanView(
    STEAMID: $SteamID == "" ? "STEAM_0:" : $SteamID,
    ban_ip: $BanIP,
    player_name: $PlayerName,
    ban_reason: $BanReason,
    subplayer_name: $SubmitterName,
    player_email: $Email,
    server_list: $servers,
    server_selected: $SID,
));
