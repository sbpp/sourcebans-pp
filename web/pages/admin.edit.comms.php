<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under the Elastic License 2.0.
// See LICENSE.txt for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

if (!defined("IN_SB")) {
    echo "You should not be here. Only follow links!";
    die();
}

global $userbank, $theme;

new AdminTabs([], $userbank, $theme);

if ($_GET['key'] != $_SESSION['banlist_postkey']) {
    \Sbpp\View\Toast::emit(
        'error',
        'Error',
        'Possible hacking attempt (URL Key mismatch)!',
        'index.php?p=admin&c=comms',
    );
    PageDie();
}
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    \Sbpp\View\Toast::emit(
        'error',
        'Error',
        'No block id specified. Please only follow links!',
        'index.php?p=admin&c=comms',
    );
    PageDie();
}
$_GET['id'] = (int) $_GET['id'];

$GLOBALS['PDO']->query("SELECT bid, ba.type, ba.authid, ba.name, created, ends, length, reason, ba.aid, ba.sid, ad.user, ad.gid
    FROM `:prefix_comms` AS ba
    LEFT JOIN `:prefix_admins` AS ad ON ba.aid = ad.aid
    WHERE bid = :bid");
$GLOBALS['PDO']->bind(':bid', $_GET['id']);
$res = $GLOBALS['PDO']->single();

isset($_GET["page"]) ? $pagelink = "&page=" . urlencode($_GET["page"]) : $pagelink = "";

// Not-found guard MUST come BEFORE the permission check (#1410). On a
// request for a non-existent cid `$res` is false; the perm check below
// then reads `$res['aid']` / `$res['gid']` which PHP 8.x evaluates to
// `null` while emitting an `E_WARNING: Trying to access array offset on
// value of type bool`. The `null != $userbank->GetAid()` comparison
// then trips the deny-access branch, so the user sees the misleading
// "You don't have access to this!" toast for what's actually a stale
// link / typo'd cid (and incidentally a side-channel for "does this
// cid exist" enumeration). Running the existence check first surfaces
// the correct "block has been deleted" toast AND clears the warning.
//
// PageDie after the toast emit (parity with every OTHER Toast::emit
// branch in this file — L22 / L31 / the perm check below). Without
// terminating here the perm check would still dereference `$res`
// (false) and emit the warning; and the Renderer::render + the inline
// `<script>` further down dereference `$res` too, where the warning
// lands literally inside the script's string literals (the
// `selectLengthTypeReason` short-echo block that builds JS string
// args from the row fields) — every warning is plain text appearing
// INSIDE quoted JS strings, which is a JavaScript "Invalid or
// unexpected token" parse error. PageDie cuts the render before any
// of that runs.
if (!$res) {
    \Sbpp\View\Toast::emit(
        'error',
        'Error',
        'There was an error getting details. Maybe the block has been deleted?',
        'index.php?p=commslist' . $pagelink,
    );
    PageDie();
}

if (!$userbank->HasAccess(WebPermission::mask(WebPermission::Owner, WebPermission::EditAllBans)) && (!$userbank->HasAccess(WebPermission::EditOwnBans) && $res['aid'] != $userbank->GetAid()) && (!$userbank->HasAccess(WebPermission::EditGroupBans) && $res['gid'] != $userbank->GetProperty('gid'))) {
    \Sbpp\View\Toast::emit(
        'error',
        'Error',
        "You don't have access to this!",
        'index.php?p=admin&c=comms',
    );
    PageDie();
}

// #1402: per-field inline-error setters now emit vanilla DOM calls
// instead of the MooTools `$('id').setStyle('display', 'block')` shape
// that died with sourcebans.js at #1123 D1. Each error tuple becomes
// `document.getElementById(id).textContent = msg; …style.display='block'`
// in the page-tail `<script>` block. We JSON-encode both the id and
// the message so a dynamic value (admin name, conflicting bid) can't
// break out of the string literal — pre-fix `$admin['user']` was
// concatenated verbatim, so an admin renamed `O'Reilly` would have
// produced unparseable JS and the entire error display would silently
// no-op (the broader page would still render, but the operator would
// see no feedback at all about *why* the save failed).
/** @var list<array{string, string}> */
$errorFields = [];

if (isset($_POST['name'])) {
    // #1420 follow-up #2 — validate the raw Steam ID shape BEFORE the
    // `SteamID::toSteam2()` conversion. Pre-fix this surface called
    // `toSteam2()` on the raw POST value as its first statement; on a
    // garbage input (the `STEAM_0:0:` empty-Z bypass, the
    // `asdfSTEAM_0:0:123` substring-bypass, the
    // `asdf 76561197960265728 garbage` embedded-Steam64 bypass — see
    // `web/tests/integration/SteamIDValidationTest.php`) the converter
    // threw `Invalid SteamID input!` from `resolveInputID()`, the
    // exception escaped the page handler unhandled, and the user got
    // a generic 500 page render instead of the inline "Please enter a
    // valid Steam ID or Community ID" message on the form. The library
    // tightening (follow-up #1) made the throw stricter which made the
    // 500 page render strictly MORE frequent on edit-comms; the
    // validate-before-convert order surfaces the failure on the form
    // as the same per-field message comms-add uses.
    $rawSteam       = trim((string) ($_POST['steam'] ?? ''));
    $_POST['type']  = (int) ($_POST['type'] ?? 0);

    // Form Validation
    $error = 0;
    // Steam ID — validate raw shape FIRST; convert only on a pass.
    if ($rawSteam === '') {
        $error++;
        $errorFields[] = ['steam.msg', 'You must type a Steam ID or Community ID'];
        $_POST['steam'] = '';
    } elseif (!\SteamID\SteamID::isValidID($rawSteam)) {
        $error++;
        $errorFields[] = ['steam.msg', 'Please enter a valid Steam ID or Community ID'];
        // Re-emit the operator's raw input verbatim on the bounce so
        // they see exactly what they typed and can correct the typo.
        $_POST['steam'] = $rawSteam;
    } else {
        // Convert ONLY after the shape gate passes. With the library
        // tightening from follow-up #1 this call cannot throw — every
        // input passing `isValidID()` resolves through the shared
        // `ID_PATTERNS` table.
        $_POST['steam'] = \SteamID\SteamID::toSteam2($rawSteam);
    }

    // Didn't type a custom reason
    if ($_POST['listReason'] == "other" && empty($_POST['txtReason'])) {
        $error++;
        $errorFields[] = ['reason.msg', 'You must type a reason'];
    }

    // prune any old bans
    PruneComms();

    if ($error == 0) {
        // Check if the new steamid is already blocked. Surface the
        // conflicting bid so the admin can investigate the OTHER
        // active row that's blocking this edit (mirror of the JSON
        // `comms.add` action — see `web/api/handlers/comms.php`).
        $GLOBALS['PDO']->query("SELECT bid FROM `:prefix_comms` WHERE authid = :authid AND RemovedBy IS NULL AND type = :type AND bid != :bid AND (length = 0 OR ends > UNIX_TIMESTAMP()) ORDER BY bid DESC LIMIT 1");
        $GLOBALS['PDO']->bindMultiple([
            ':authid' => $_POST['steam'],
            ':type'   => (int) $_POST['type'],
            ':bid'    => (int) $_GET['id'],
        ]);
        $chk = $GLOBALS['PDO']->single();
        if ($chk) {
            $error++;
            $existingBid = (int) $chk['bid'];
            $errorFields[] = ['steam.msg', 'This SteamID is already blocked by block #' . $existingBid];
        } else {
            // Check if player is immune
            $admchk = $userbank->GetAllAdmins();
            foreach ($admchk as $admin) {
                if ($admin['authid'] == $_POST['steam'] && $userbank->GetProperty('srv_immunity') < $admin['srv_immunity']) {
                    $error++;
                    $errorFields[] = ['steam.msg', 'Admin ' . (string) $admin['user'] . ' is immune'];
                    break;
                }
            }
        }
    }

    $reason        = $_POST['listReason'] == "other" ? $_POST['txtReason'] : $_POST['listReason'];

    if (!$_POST['banlength']) {
        $_POST['banlength'] = 0;
    } else {
        $_POST['banlength'] = (int) $_POST['banlength'] * 60;
    }
    // Show the new values in the form
    $res['name']   = $_POST['name'];
    $res['authid'] = $_POST['steam'];

    $res['length'] = $_POST['banlength'];
    $res['type']   = $_POST['type'];
    $res['reason'] = $reason;

    // Only process if there are still no errors
    if ($error == 0) {
        $GLOBALS['PDO']->query("SELECT length, authid, type FROM `:prefix_comms` WHERE bid = :bid");
        $GLOBALS['PDO']->bind(':bid', $_GET['id']);
        $lengthrev = $GLOBALS['PDO']->single();

        $GLOBALS['PDO']->query(
            "UPDATE `:prefix_comms` SET
            `name` = :name, `type` = :type, `reason` = :reason, `authid` = :authid,
            `length` = :length,
            `ends` 	 =  `created` + :ends
            WHERE bid = :bid"
        );
        $GLOBALS['PDO']->bindMultiple([
            ':name'   => $_POST['name'],
            ':type'   => $_POST['type'],
            ':reason' => $reason,
            ':authid' => $_POST['steam'],
            ':length' => $_POST['banlength'],
            ':ends'   => $_POST['banlength'],
            ':bid'    => (int) $_GET['id'],
        ]);
        $GLOBALS['PDO']->execute();

        if ($_POST['banlength'] != $lengthrev['length']) {
            Log::add(LogType::Message, "Block edited", "Block for ({$lengthrev['authid']}) has been updated."
                . " Before: length ({$lengthrev['length']}), type ({$lengthrev['type']});"
                . " Now: length ({$_POST['banlength']}), type ({$_POST['type']}).");
        }
        \Sbpp\View\Toast::emit(
            'success',
            'Block updated',
            'The block has been updated successfully',
            'index.php?p=commslist' . $pagelink,
        );
    }
}

\Sbpp\View\Renderer::render($theme, new \Sbpp\View\AdminCommsEditView(
    ban_name: (string) $res['name'],
    ban_authid: trim((string) $res['authid']),
));

// #1402: page-tail JS — vanilla DOM, no MooTools.
// Pre-fix this block was wrapped in MooTools' `window.addEvent('domready',
// function(){...})` and used `$('id').innerHTML = …; $('id').setStyle(...)`
// — every one of those calls failed with `ReferenceError: $ is not
// defined` since MooTools / sourcebans.js died at #1123 D1. Now: the
// page-tail script runs on DOMContentLoaded, uses
// `document.getElementById(...)` to flip per-field error containers,
// and `changeReason` reaches for the modern API too.
$errorScriptParts = [];
foreach ($errorFields as [$id, $msg]) {
    $idJson  = json_encode($id);
    $msgJson = json_encode($msg);
    $errorScriptParts[] = "var el = document.getElementById($idJson); if (el) { el.textContent = $msgJson; el.style.display = 'block'; }";
}
$errorScript = implode("\n", $errorScriptParts);
?>
<script type="text/javascript">
document.addEventListener('DOMContentLoaded', function () {
<?=$errorScript?>

});
function changeReason(szListValue) {
    var dreason = document.getElementById('dreason');
    if (dreason) {
        dreason.style.display = (szListValue == "other") ? "block" : "none";
    }
}
// `selectLengthTypeReason` is the post-mount hydrator that picks the
// existing block's type / length / reason on the <select>s. Inlined as
// a self-contained vanilla function (the v1.x version lived in the
// removed pre-v2.0.0 bulk JS file) so the call below works without any
// chrome-level helper — without it the call would throw ReferenceError,
// leave the type/length/reason at their defaults, and silently clobber
// the row when the admin clicks Save.
function selectLengthTypeReason(length, type, reason) {
    var banlength = document.getElementById('banlength');
    if (banlength) {
        for (var i = 0; i < banlength.options.length; i++) {
            if (banlength.options[i].value === String(length / 60)) {
                banlength.options[i].selected = true;
                break;
            }
        }
    }
    var ttype = document.getElementById('type');
    if (ttype && ttype.options[type]) ttype.options[type].selected = true;

    var list = document.getElementById('listReason');
    if (list) {
        for (var i = 0; i < list.options.length; i++) {
            if (list.options[i].innerHTML === reason) {
                list.options[i].selected = true;
                break;
            }
            if (list.options[i].value === 'other') {
                var txt = document.getElementById('txtReason');
                var dre = document.getElementById('dreason');
                if (txt) txt.value = reason;
                if (dre) dre.style.display = 'block';
                list.options[i].selected = true;
                break;
            }
        }
    }
}
selectLengthTypeReason('<?=(int) $res['length']?>', '<?=(int) $res['type'] - 1?>', '<?=addslashes($res['reason'])?>');
</script>
