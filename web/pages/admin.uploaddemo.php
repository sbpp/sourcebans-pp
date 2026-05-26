<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under the Elastic License 2.0.
// See LICENSE.txt for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

declare(strict_types=1);

include_once __DIR__ . '/../init.php';
include_once __DIR__ . '/../includes/system-functions.php';

global $userbank, $theme;

// `chdir(ROOT)` keeps relative theme paths working — see
// admin.kickit.php for the historical rationale.
chdir(ROOT);

\Sbpp\Upload\UploadHandler::handle(
    userbank: $userbank,
    theme:    $theme,
    permission: \WebPermission::mask(
        \WebPermission::Owner,
        \WebPermission::AddBan,
        \WebPermission::EditOwnBans,
        \WebPermission::EditGroupBans,
        \WebPermission::EditAllBans,
    ),
    deniedAuditMsg: $userbank->GetProperty('user') . " tried to upload a demo, but doesn't have access.",
    deniedUserMsg:  "You don't have access to this!",
    field:    'demo_file',
    allowed:  ['zip', 'rar', 'dem', '7z', 'bz2', 'gz'],
    destDir:  SB_DEMOS,
    callback: 'demo',
    renameToHash: true,
    auditOk:  'Demo Uploaded',
    auditFmt: 'A new demo has been uploaded: %s',
    errorMsg: '<b>File must be DEM, ZIP, RAR, 7Z, BZ2 or GZ filetype.</b><br><br>',
    title:    'Upload Demo',
    formName: 'demup',
    formats:  'a DEM, ZIP, RAR, 7Z, BZ2 or GZ',
);
