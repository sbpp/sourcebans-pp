<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under the Elastic License 2.0.
// See LICENSE.txt for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

global $userbank, $theme;

use Sbpp\Mail\EmailType;
use Sbpp\Mail\Mail;
use Sbpp\Mail\Mailer;
use xPaw\SourceQuery\SourceQuery;

if (!defined("IN_SB")) {
    echo "You should not be here. Only follow links!";
    die();
}

// Toast emission consolidated onto `Sbpp\View\Toast::emit` (#1403). The
// previous local `emitSubmitToast()` helper was the prototype shape this
// surface used to work around the v1.x `<script>ShowBox(…)</script>`
// blobs throwing `ReferenceError` after #1123 D1 deleted
// `web/scripts/sourcebans.js`. The lift unifies that pattern with the
// five sister pages the #1403 audit caught still emitting raw
// `<script>ShowBox(...)</script>` (lostpassword / protest / banlist /
// commslist / admin.edit.comms) so every chrome-toast emission goes
// through one helper + one wire format. See `web/includes/View/Toast.php`
// for the contract.

if (!Config::getBool('config.enablesubmit')) {
    \Sbpp\View\Toast::emit('error', 'Submissions disabled', 'This page is disabled. You should not be here.');
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
    // #1420 follow-up #2: the legacy `STEAM_0:` sentinel that the
    // page handler used to re-emit when the SteamID was blank was
    // dropped in the same commit that added the strict
    // `pattern="STEAM_[01]:[01]:\d+|\[U:1:\d+\]|\d{17}"` attribute to
    // the form input — the sentinel would have failed the pattern
    // check AND blocked submission for the legitimate IP-only path.
    // With the sentinel gone, `$SteamID === ''` is the canonical
    // "no Steam ID typed" state and both server-side rules below
    // (validate-shape + at-least-one-of) key off it.
    if (strlen($SteamID) != 0 && !\SteamID\SteamID::isValidID($SteamID)) {
        $errors .= '* Please type a valid STEAM ID.<br>';
        $validsubmit = false;
    }
    if (strlen($BanIP) != 0 && !filter_var($BanIP, FILTER_VALIDATE_IP)) {
        $errors .= '* Please type a valid IP-address.<br>';
        $validsubmit = false;
    }
    // #1207 PUB-4: at least one of Steam ID / IP must be provided.
    // Mirrors the inline guard in `page_submitban.tpl` so JS-off
    // visitors can't sneak an empty pair past the form.
    if (strlen($SteamID) == 0 && strlen($BanIP) == 0) {
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
        \Sbpp\View\Toast::emit(
            'error',
            'Please fix the following',
            (string) preg_replace('#<br\s*/?>#i', ' ', $errors),
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
            // #1420 follow-up #2: the legacy `STEAM_0:` empty sentinel
            // was dropped at the top of this handler — the operator-side
            // shape is now strictly "empty string" for the no-Steam-ID
            // path, and the strict `pattern="…"` on the form input plus
            // the server-side `SteamID::isValidID()` gate above mean
            // anything reaching here is either '' or a fully-formed
            // SteamID. No defensive sentinel collapse needed.
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
                if ($userbank->HasAccess(WebPermission::mask(WebPermission::Owner, WebPermission::BanSubmissions), $admin['aid']) || $userbank->HasAccess(WebPermission::NotifySub, $admin['aid'])) {
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
                    // #1275 — admin-bans is Pattern A; the legacy `#^2`
                    // anchor that targeted the old page-toc chrome is no
                    // longer wired. Link directly to the submissions
                    // section so the email recipient lands on the queue
                    // they're being asked to review.
                    '{link}' => Host::complete(true) . '/index.php?p=admin&c=bans&section=submissions'
                ]);
            }

            \Sbpp\View\Toast::emit(
                'success',
                'Submitted',
                'Your submission has been added into the database, and will be reviewed by one of our admins.',
            );
        } else {
            \Sbpp\View\Toast::emit(
                'error',
                'Upload failed',
                'There was an error uploading your demo to the server. Please try again later.',
            );
            Log::add(LogType::Error, "Demo Upload Failed", "A demo failed to upload for a submission from ($Email)");
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
    // #1420 follow-up #2: re-emit the raw input verbatim (or '' on the
    // first-paint / IP-only path). The legacy `STEAM_0:` sentinel was
    // pre-fill UX that broke the moment the template grew a strict
    // `pattern="…"` attribute — the sentinel didn't match the regex
    // and the browser blocked submission for legit IP-only flows.
    // The form's `placeholder="STEAM_0:0:12345"` is the modern shape
    // for "show the operator what we expect" without populating the
    // input.
    STEAMID: $SteamID,
    ban_ip: $BanIP,
    player_name: $PlayerName,
    ban_reason: $BanReason,
    subplayer_name: $SubmitterName,
    player_email: $Email,
    server_list: $servers,
    server_selected: $SID,
));
