<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under Creative Commons Attribution-NonCommercial-ShareAlike 3.0.
// See LICENSE.md for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

global $userbank, $theme;

if (!defined("IN_SB")) {
    echo "You should not be here. Only follow links!";
    die();
}
if ($userbank->GetAid() == -1) {
    echo "You shoudnt be here. looks like we messed up ><";
    die();
}

$GLOBALS['PDO']->query("SELECT `srv_password`, `email` FROM `:prefix_admins` WHERE `aid` = :aid");
$GLOBALS['PDO']->bind(':aid', $userbank->GetAid());
$res      = $GLOBALS['PDO']->single();
$srvpwset = !empty($res['srv_password']);

// #1207 ADM-9: group the granted web permissions by display category
// (Bans, Servers, Admins, …) so the "Your permissions" card renders a
// 2–3 column grid instead of a 30-item bullet wall. The category list
// + grouping logic lives in `Sbpp\View\PermissionCatalog`; see that
// class's docblock for the rationale.
$webExtraFlags = (int) $userbank->GetProperty("extraflags");

\Sbpp\View\Renderer::render($theme, new \Sbpp\View\YourAccountView(
    srvpwset:                $srvpwset,
    email:                   (string) ($res['email'] ?? ''),
    user_aid:                (int) $userbank->GetAid(),
    web_permissions_grouped: \Sbpp\View\PermissionCatalog::groupedDisplayFromMask($webExtraFlags),
    server_permissions:      SmFlagsToSb($userbank->GetProperty("srv_flags")),
    min_pass_len:            (int) MIN_PASS_LENGTH,
));
