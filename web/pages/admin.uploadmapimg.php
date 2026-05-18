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
        \WebPermission::AddServer,
    ),
    deniedAuditMsg: $userbank->GetProperty('user') . " tried to upload a mapimage, but doesn't have access.",
    deniedUserMsg:  "You don't have access to this!",
    field:    'mapimg_file',
    allowed:  ['jpg'],
    destDir:  SB_MAPS,
    callback: 'mapimg',
    renameToHash: false,
    auditOk:  'Map Image Uploaded',
    auditFmt: 'A new map image has been uploaded: %s',
    errorMsg: '<b>File must be JPG filetype.</b><br><br>',
    title:    'Upload Mapimage',
    formName: 'mapimgup',
    formats:  'a JPG',
);
