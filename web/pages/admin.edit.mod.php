<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under Creative Commons Attribution-NonCommercial-ShareAlike 3.0.
// See LICENSE.md for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

declare(strict_types=1);

if (!defined('IN_SB')) {
    echo 'You should not be here. Only follow links!';
    die();
}

global $userbank, $theme;

new \Sbpp\View\AdminTabs([], $userbank, $theme);

require_once __DIR__ . '/_admin_edit_helpers.php';

$modId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($modId <= 0) {
    sbpp_admin_edit_die_with_toast(
        'No mod id specified. Please only follow links.',
        'index.php?p=admin&c=mods',
    );
    return;
}

if (!$userbank->HasAccess(\WebPermission::mask(\WebPermission::Owner, \WebPermission::EditMods))) {
    \Sbpp\Log::add(
        \LogType::Warning,
        'Hacking Attempt',
        $userbank->GetProperty('user') . " tried to edit a mod, but doesn't have access.",
    );
    sbpp_admin_edit_die_with_toast(
        "You aren't allowed to edit mods.",
        'index.php?p=admin&c=mods',
    );
    return;
}

$pdo = $GLOBALS['PDO'];

$modRow = $pdo->query(
    'SELECT name, modfolder, icon, enabled, steam_universe
        FROM `:prefix_mods` WHERE mid = :mid'
)->single([':mid' => $modId]);

if (!$modRow) {
    sbpp_admin_edit_die_with_toast(
        'There was an error getting details. Maybe the mod was deleted?',
        'index.php?p=admin&c=mods',
    );
    return;
}

/** @var array<string,string> $validationErrors */
$validationErrors = [];
$postSuccess      = false;

if (isset($_POST['name'])) {
    \CSRF::rejectIfInvalid();

    $rawName    = trim((string) $_POST['name']);
    $rawFolder  = trim((string) ($_POST['folder']   ?? ''));
    $rawIcon    = trim((string) ($_POST['icon_hid'] ?? ''));
    $enabled    = isset($_POST['enabled']) && $_POST['enabled'] === '1';
    $universe   = (int) ($_POST['steam_universe'] ?? 0);

    if ($rawName === '') {
        $validationErrors['name'] = 'You must type a name for the mod.';
    } else {
        $clash = $pdo->query('SELECT mid FROM `:prefix_mods` WHERE name = :name AND mid != :mid')
            ->single([':name' => $rawName, ':mid' => $modId]);
        if ($clash) {
            $validationErrors['name'] = 'A mod with that name already exists.';
        }
    }

    if ($rawFolder === '') {
        $validationErrors['folder'] = "You must enter the mod's folder name.";
    } else {
        $clash = $pdo->query('SELECT mid FROM `:prefix_mods` WHERE modfolder = :folder AND mid != :mid')
            ->single([':folder' => $rawFolder, ':mid' => $modId]);
        if ($clash) {
            $validationErrors['folder'] = 'A mod using that folder already exists.';
        }
    }

    $name   = htmlspecialchars(strip_tags($rawName));
    $folder = htmlspecialchars(strip_tags($rawFolder));
    $icon   = htmlspecialchars(strip_tags($rawIcon));

    if ($validationErrors === []) {
        if (((string) $modRow['icon']) !== $rawIcon) {
            @unlink(SB_ICONS . '/' . (string) $modRow['icon']);
        }

        $pdo->query(
            'UPDATE `:prefix_mods`
                SET name = :name, modfolder = :folder, icon = :icon,
                    enabled = :enabled, steam_universe = :steam_universe
                WHERE mid = :mid'
        );
        $pdo->bindMultiple([
            ':name'           => $name,
            ':folder'         => $folder,
            ':icon'           => $icon,
            ':enabled'        => $enabled ? 1 : 0,
            ':steam_universe' => $universe,
            ':mid'            => $modId,
        ]);
        $pdo->execute();

        \Sbpp\Log::add(
            \LogType::Message,
            'Mod Updated',
            "Mod ($name) has been updated.",
        );
        $postSuccess = true;
    }

    // Reflect submitted values back into the form (validation may
    // have failed for a reason other than the value the operator
    // typed — e.g. duplicate folder — so they shouldn't lose the
    // edit on re-render).
    $modRow['name']           = $name;
    $modRow['modfolder']      = $folder;
    $modRow['icon']           = $icon;
    $modRow['enabled']        = $enabled ? 1 : 0;
    $modRow['steam_universe'] = $universe;
}

\Sbpp\View\Renderer::render($theme, new \Sbpp\View\AdminEditModView(
    name:           (string) ($modRow['name']           ?? ''),
    folder:         (string) ($modRow['modfolder']      ?? ''),
    mod_icon:       (string) ($modRow['icon']           ?? ''),
    steam_universe: (int)    ($modRow['steam_universe'] ?? 0),
    enabled:        (bool)   ($modRow['enabled']        ?? false),
));

sbpp_admin_edit_emit_tail_script(
    successTitle:    'Mod updated',
    successBody:     'The mod has been updated successfully.',
    successRedirect: 'index.php?p=admin&c=mods',
    postSuccess:     $postSuccess,
    rehashSids:      [],
    validationErrors:$validationErrors,
);
