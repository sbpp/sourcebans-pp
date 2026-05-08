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

if (!defined("IN_SB")) {
    echo "You should not be here. Only follow links!";
    die();
}

global $userbank, $theme;

new AdminTabs([], $userbank, $theme);

/**
 * Emits an inline `<script>` that surfaces a toast via
 * `window.SBPP.showToast` (theme.js) with `sb.message.*` (sb.js) as a
 * fallback, then redirects after a short delay. Replaces the v1.x-era
 * `<script>ShowBox(…)</script>` calls (the legacy bulk JS file is gone
 * since v2.0.0).
 *
 * The strings are JSON-encoded so embedded quotes / newlines / non-ASCII
 * survive the round-trip into the script body without further escaping.
 */
function emitEditBanToastAndRedirect(string $kind, string $title, string $body, string $redirect): void
{
    $kindJs = json_encode($kind, JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    $titleJs = json_encode($title, JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    $bodyJs = json_encode($body, JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    $redirectJs = json_encode($redirect, JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    echo <<<HTML
<script>
(function () {
    var kind = {$kindJs};
    var title = {$titleJs};
    var body = {$bodyJs};
    var redirect = {$redirectJs};
    var SBPP = window.SBPP;
    if (SBPP && typeof SBPP.showToast === 'function') {
        SBPP.showToast({ kind: kind === 'red' ? 'error' : kind === 'green' ? 'success' : kind, title: title, body: body });
    } else if (window.sb && window.sb.message) {
        var fn = (kind === 'red') ? window.sb.message.error
            : (kind === 'green') ? window.sb.message.success
            : window.sb.message.info;
        if (typeof fn === 'function') fn(title, body, redirect);
    }
    if (redirect) {
        setTimeout(function () { window.location.href = redirect; }, 1500);
    }
})();
</script>
HTML;
}

if ($_GET['key'] != $_SESSION['banlist_postkey']) {
    emitEditBanToastAndRedirect('red', 'Error', 'Possible hacking attempt (URL Key mismatch)!', 'index.php?p=admin&c=bans');
    PageDie();
}
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    emitEditBanToastAndRedirect('red', 'Error', 'No ban id specified. Please only follow links!', 'index.php?p=admin&c=bans');
    PageDie();
}
$_GET['id'] = (int) $_GET['id'];

// Native PDO prepares (#1175 / Slice 3) reject the same named placeholder
// reused across positions, so split the bid lookup into two distinct
// names and bind both. The subquery's `:demo_bid` and the outer
// `:bid` both pull from `$_GET['id']`, which is already int-cast above.
$GLOBALS['PDO']->query("
    				SELECT bid, ba.ip, ba.type, ba.authid, ba.name, created, ends, length, reason, ba.aid, ba.sid AS ba_sid, ad.user, ad.gid, CONCAT(se.ip,':',se.port) AS server_addr, se.sid AS se_sid, mo.icon, (SELECT origname FROM `:prefix_demos` WHERE demtype = 'b' AND demid = :demo_bid) AS dname
    				FROM `:prefix_bans` AS ba
    				LEFT JOIN `:prefix_admins` AS ad ON ba.aid = ad.aid
    				LEFT JOIN `:prefix_servers` AS se ON se.sid = ba.sid
    				LEFT JOIN `:prefix_mods` AS mo ON mo.mid = se.modid
    				WHERE bid = :bid");
$GLOBALS['PDO']->bind(':bid', $_GET['id']);
$GLOBALS['PDO']->bind(':demo_bid', $_GET['id']);
$res = $GLOBALS['PDO']->single();

isset($_GET["page"]) ? $pagelink = "&page=" . urlencode($_GET["page"]) : $pagelink = "";

if (!$res) {
    emitEditBanToastAndRedirect('red', 'Error', 'There was an error getting details. Maybe the ban has been deleted?', 'index.php?p=banlist' . $pagelink);
    PageDie();
}

$canEditBan = (bool) $userbank->HasAccess(WebPermission::mask(WebPermission::Owner, WebPermission::EditAllBans))
    || ($userbank->HasAccess(WebPermission::EditOwnBans) && $res['aid'] == $userbank->GetAid())
    || ($userbank->HasAccess(WebPermission::EditGroupBans) && $res['gid'] == $userbank->GetProperty('gid'));

if (!$canEditBan) {
    emitEditBanToastAndRedirect('red', 'Error', "You don't have access to this!", 'index.php?p=admin&c=bans');
    PageDie();
}

/**
 * Per-field validation errors collected during the POST step.
 * Replayed at the bottom of the page by the tail <script>: each entry
 * sets the matching `<id>.msg` div's textContent + reveals it via
 * `style.display = 'block'`. Vanilla DOM only — no MooTools `setStyle()`
 * / `setHTML()` (the v1.x bulk JS file is gone since v2.0.0).
 *
 * @var array<string, string> $validationErrors  field id (`name`, `steam`,
 *     `ip`, `reason`, `length`, `demo`) → message string.
 */
$validationErrors = [];

/** Whether the current POST resulted in a successful UPDATE; drives the
 *  success toast + redirect emitted by the tail script. */
$postSuccess = false;

if (isset($_POST['name'])) {
    $_POST['steam'] = \SteamID\SteamID::toSteam2(trim((string) ($_POST['steam'] ?? '')));
    $_POST['type']  = (int) ($_POST['type'] ?? 0);
    $postBanType    = BanType::tryFrom((int) $_POST['type']) ?? BanType::Steam;

    // Form Validation
    $error = 0;
    // If they didn't type a steamid
    if (empty($_POST['steam']) && $postBanType === BanType::Steam) {
        $error++;
        $validationErrors['steam'] = 'You must type a Steam ID or Community ID';
    } elseif ($postBanType === BanType::Steam && !\SteamID\SteamID::isValidID($_POST['steam'])) {
        $error++;
        $validationErrors['steam'] = 'Please enter a valid Steam ID or Community ID';
    } elseif (empty($_POST['ip']) && $postBanType === BanType::Ip) {
        // Didn't type an IP
        $error++;
        $validationErrors['ip'] = 'You must type an IP';
    } elseif ($postBanType === BanType::Ip && !filter_var($_POST['ip'], FILTER_VALIDATE_IP)) {
        $error++;
        $validationErrors['ip'] = 'You must type a valid IP';
    }

    // Didn't type a custom reason
    if ($_POST['listReason'] == "other" && empty($_POST['txtReason'])) {
        $error++;
        $validationErrors['reason'] = 'You must type a reason';
    }

    // prune any old bans
    PruneBans();

    if ($error == 0) {
        // Check if the new steamid is already banned
        if ($postBanType === BanType::Steam) {
            $GLOBALS['PDO']->query("SELECT count(bid) AS count FROM `:prefix_bans` WHERE authid = :authid AND (length = 0 OR ends > UNIX_TIMESTAMP()) AND RemovedBy IS NULL AND type = '0' AND bid != :bid");
            $GLOBALS['PDO']->bindMultiple([
                ':authid' => $_POST['steam'],
                ':bid'    => (int) $_GET['id'],
            ]);
            $chk = $GLOBALS['PDO']->single();

            if ((int) $chk['count'] > 0) {
                $error++;
                $validationErrors['steam'] = 'This SteamID is already banned';
            } else {
                // Check if player is immune
                $admchk = $userbank->GetAllAdmins();
                foreach ($admchk as $admin) {
                    if ($admin['authid'] == $_POST['steam'] && $userbank->GetProperty('srv_immunity') < $admin['srv_immunity']) {
                        $error++;
                        $validationErrors['steam'] = 'Admin ' . $admin['user'] . ' is immune';
                        break;
                    }
                }
            }
        } elseif ($postBanType === BanType::Ip) {
            // Check if the ip is already banned
            $GLOBALS['PDO']->query("SELECT count(bid) AS count FROM `:prefix_bans` WHERE ip = :ip AND (length = 0 OR ends > UNIX_TIMESTAMP()) AND RemovedBy IS NULL AND type = '1' AND bid != :bid");
            $GLOBALS['PDO']->bindMultiple([
                ':ip'  => $_POST['ip'],
                ':bid' => (int) $_GET['id'],
            ]);
            $chk = $GLOBALS['PDO']->single();

            if ((int) $chk['count'] > 0) {
                $error++;
                $validationErrors['ip'] = 'This IP is already banned';
            }
        }
    }

    $_POST['ip'] = preg_replace('#[^\d\.]#', '', (string) ($_POST['ip'] ?? '')); //strip ip of all but numbers and dots
    $reason = $_POST['listReason'] == "other" ? $_POST['txtReason'] : $_POST['listReason'];

    if (!$_POST['banlength']) {
        $_POST['banlength'] = 0;
    } else {
        $_POST['banlength'] = (int) $_POST['banlength'] * 60;
    }

    // Show the new values in the form
    $res['name']   = $_POST['name'];
    $res['authid'] = $_POST['steam'];
    $res['ip']     = $_POST['ip'];
    $res['length'] = $_POST['banlength'];
    $res['type']   = $_POST['type'];
    $res['reason'] = $reason;

    // Only process if there are still no errors
    if ($error == 0) {
        $GLOBALS['PDO']->query("SELECT length, authid FROM `:prefix_bans` WHERE bid = :bid");
        $GLOBALS['PDO']->bind(':bid', $_GET['id']);
        $lengthrev = $GLOBALS['PDO']->single();

        $GLOBALS['PDO']->query(
            "UPDATE `:prefix_bans` SET
            `name` = :name, `type` = :type, `reason` = :reason, `authid` = :authid,
            `length` = :length,
            `ip` = :ip,
            `country` = '',
            `ends` 	 =  `created` + :ends
            WHERE bid = :bid"
        );
        $GLOBALS['PDO']->bindMultiple([
            ':name'   => $_POST['name'],
            ':type'   => $postBanType->value,
            ':reason' => $reason,
            ':authid' => $_POST['steam'],
            ':length' => $_POST['banlength'],
            ':ip'     => $_POST['ip'],
            ':ends'   => $_POST['banlength'],
            ':bid'    => (int) $_GET['id'],
        ]);
        $GLOBALS['PDO']->execute();

        // Set all submissions to archived for that steamid
        $GLOBALS['PDO']->query("UPDATE `:prefix_submissions` SET archiv = '3', archivedby = :aid WHERE SteamId = :steam");
        $GLOBALS['PDO']->bindMultiple([
            ':aid'   => $userbank->GetAid(),
            ':steam' => $_POST['steam'],
        ]);
        $GLOBALS['PDO']->execute();

        if (!empty($_POST['dname'])) {
            $GLOBALS['PDO']->query("SELECT filename FROM `:prefix_demos` WHERE demid = :id");
            $GLOBALS['PDO']->bind(':id', $_GET['id']);
            $demoid = $GLOBALS['PDO']->single();
            @unlink(SB_DEMOS . "/" . $demoid['filename']);
            $GLOBALS['PDO']->query(
                "REPLACE INTO `:prefix_demos`
                (`demid`, `demtype`, `filename`, `origname`)
                VALUES
                (:demid,
                'b',
                :filename,
                :origname)"
            );
            $GLOBALS['PDO']->bindMultiple([
                ':demid'    => (int) $_GET['id'],
                ':filename' => $_POST['did'],
                ':origname' => $_POST['dname'],
            ]);
            $GLOBALS['PDO']->execute();
            $res['dname'] = $_POST['dname'];
        }

        if ($_POST['banlength'] != $lengthrev['length']) {
            Log::add(LogType::Message, "Ban length edited", "Ban length for ({$lengthrev['authid']}) has been updated."
                . " Before: {$lengthrev['length']}; Now: {$_POST['banlength']}.");
        }
        $postSuccess = true;
    }
}

$customReason = Config::getBool('bans.customreasons')
    ? unserialize((string) Config::get('bans.customreasons'))
    : false;
/** @var false|list<string> $customReason */

\Sbpp\View\Renderer::render($theme, new \Sbpp\View\AdminBansEditView(
    can_edit_ban: $canEditBan,
    ban_name:     (string) $res['name'],
    ban_authid:   trim((string) $res['authid']),
    ban_ip:       (string) $res['ip'],
    // Issue #1113: dname is the admin-supplied original filename of the
    // demo (POST'd by whoever edited the ban + uploaded the demo, stored
    // as `:prefix_demos.origname`). It used to be interpolated raw into
    // HTML rendered with `nofilter`, so a filename like
    // `<img src=x onerror=…>` turned the edit-ban page into stored XSS
    // for any admin viewing that ban. htmlspecialchars + ENT_QUOTES so
    // the value is safe inside both the surrounding `<b>…</b>` text and
    // the `nofilter` render in the template.
    ban_demo: !empty($res['dname'])
        ? 'Uploaded: <b>' . htmlspecialchars((string) $res['dname'], ENT_QUOTES, 'UTF-8') . '</b>'
        : '',
    customreason: $customReason,
));

// Tail script — self-contained vanilla helpers the page needs after
// the row renders (the v1.x MooTools-flavoured `changeReason`,
// `selectLengthTypeReason`, `demo` came from the removed bulk JS file).
//
// Order:
//   1. Replay POST validation errors into the per-field `*.msg` divs.
//   2. Emit the "saved" toast + redirect on success.
//   3. Define `changeReason`, `selectLengthTypeReason`, `demo` as
//      vanilla functions targeting the matching DOM ids.
//   4. Hydrate the `<select>`s with the row's current type/length/
//      reason via `selectLengthTypeReason`.
$validationErrorsJs = json_encode($validationErrors, JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$banLengthSeconds = (int) $res['length'];
$banType = (int) $res['type'];
// $res['reason'] is admin-controlled free text — JSON-encode it so any
// quote / backslash / non-ASCII byte survives the round-trip into the
// inline script body unmolested. The hydrator does an equality check on
// `<option>.value` against this string, so preserving the literal byte
// sequence matters.
$banReasonJs = json_encode((string) $res['reason'], JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$redirectUrl = 'index.php?p=banlist' . $pagelink;
$redirectUrlJs = json_encode($redirectUrl, JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$postSuccessJs = $postSuccess ? 'true' : 'false';
?>
<script>
(function () {
    'use strict';

    /** @type {{[k: string]: string}} */
    var validationErrors = <?=$validationErrorsJs?>;
    var postSuccess = <?=$postSuccessJs?>;
    var redirectUrl = <?=$redirectUrlJs?>;

    function $id(id) { return document.getElementById(id); }
    function setMsg(id, text) {
        var el = $id(id);
        if (!el) return;
        if (text) {
            el.textContent = text;
            el.style.display = 'block';
        } else {
            el.textContent = '';
            el.style.display = 'none';
        }
    }
    function toast(kind, title, body) {
        var SBPP = window.SBPP;
        if (SBPP && typeof SBPP.showToast === 'function') {
            SBPP.showToast({
                kind: kind === 'red' ? 'error' : kind === 'green' ? 'success' : kind,
                title: title,
                body: body
            });
            return;
        }
        if (window.sb && window.sb.message) {
            var fn = (kind === 'red') ? window.sb.message.error
                : (kind === 'green') ? window.sb.message.success
                : window.sb.message.info;
            if (typeof fn === 'function') fn(title, body || '');
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        Object.keys(validationErrors).forEach(function (field) {
            setMsg(field + '.msg', validationErrors[field]);
        });
        if (postSuccess) {
            toast('green', 'Ban updated', 'The ban has been updated successfully');
            setTimeout(function () { window.location.href = redirectUrl; }, 1500);
        }
    });

    // Toggle the custom-reason textarea on/off based on the listReason
    // dropdown. Replaces the legacy `$('dreason').style.display = …`
    // helper that used the MooTools `$()` selector.
    window.changeReason = function (szListValue) {
        var dre = $id('dreason');
        if (dre) dre.style.display = (szListValue === 'other' ? 'block' : 'none');
    };

    // Vanilla replacement for the v1.x `selectLengthTypeReason` helper:
    // hydrate the type / banlength / listReason <select>s with the
    // current ban's stored values after the form has rendered. Falls
    // back to the "Other reason" branch (revealing the textarea +
    // pre-filling its value) when the stored reason isn't one of the
    // hard-coded preset options or a configured custom reason.
    window.selectLengthTypeReason = function (length, type, reason) {
        var banlength = $id('banlength');
        if (banlength) {
            for (var i = 0; i < banlength.options.length; i++) {
                if (banlength.options[i].value === String(length / 60)) {
                    banlength.options[i].selected = true;
                    break;
                }
            }
        }
        var ttype = $id('type');
        if (ttype && ttype.options[type]) {
            ttype.options[type].selected = true;
        }
        var list = $id('listReason');
        if (!list) return;
        for (var j = 0; j < list.options.length; j++) {
            if (list.options[j].innerHTML === reason) {
                list.options[j].selected = true;
                return;
            }
            if (list.options[j].value === 'other') {
                var txt = $id('txtReason');
                var dre = $id('dreason');
                if (txt) txt.value = reason;
                if (dre) dre.style.display = 'block';
                list.options[j].selected = true;
                return;
            }
        }
    };

    // The "Upload a demo" popup (pages/admin.uploaddemo.php) calls
    // `window.opener.demo(id, name)` after a successful upload. Update
    // the demo-status inline banner + the hidden `did`/`dname` inputs
    // so the next save persists the new demo.
    //
    // The popup escapes `id` and `name` via JSON encoding before the
    // call (admin.uploaddemo.php), so they are JavaScript primitives
    // here; we still treat `name` as untrusted text and write it via
    // textContent + a leading static label so any HTML metacharacters
    // render literally, not as markup.
    window.demo = function (id, name) {
        var msg = $id('demo.msg');
        if (msg) {
            msg.textContent = '';
            var label = document.createTextNode('Uploaded: ');
            var b = document.createElement('b');
            b.textContent = String(name);
            msg.appendChild(label);
            msg.appendChild(b);
        }
        var did = $id('did');
        var dname = $id('dname');
        if (did) did.value = id;
        if (dname) dname.value = name;
    };

    document.addEventListener('DOMContentLoaded', function () {
        window.selectLengthTypeReason(<?=$banLengthSeconds?>, <?=$banType?>, <?=$banReasonJs?>);
    });
})();
</script>
