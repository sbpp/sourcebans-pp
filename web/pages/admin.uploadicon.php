<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under Creative Commons Attribution-NonCommercial-ShareAlike 3.0.
// See LICENSE.md for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

declare(strict_types=1);

include_once __DIR__ . '/../init.php';
include_once __DIR__ . '/../includes/system-functions.php';

global $userbank, $theme;

chdir(ROOT);

\Sbpp\Upload\UploadHandler::handle(
    userbank: $userbank,
    theme:    $theme,
    permission: \WebPermission::mask(
        \WebPermission::Owner,
        \WebPermission::EditMods,
        \WebPermission::AddMods,
    ),
    deniedAuditMsg: $userbank->GetProperty('user') . " tried to upload a mod icon, but doesn't have access.",
    deniedUserMsg:  "You don't have access to this!",
    field:    'icon_file',
    allowed:  ['gif', 'jpg', 'png'],
    destDir:  SB_ICONS,
    callback: 'icon',
    renameToHash: false,
    auditOk:  'Mod Icon Uploaded',
    auditFmt: 'A new mod icon has been uploaded: %s',
    errorMsg: '<b>File must be GIF, JPG or PNG filetype.</b><br><br>',
    title:    'Upload Icon',
    formName: 'iconup',
    formats:  'a GIF, PNG or JPG',
);
