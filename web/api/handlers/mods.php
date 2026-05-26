<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under the Elastic License 2.0.
// See LICENSE.txt for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

function api_mods_add(array $params): array
{
    $name           = htmlspecialchars(strip_tags((string)($params['name']   ?? '')));
    $folder         = htmlspecialchars(strip_tags((string)($params['folder'] ?? '')));
    $icon           = htmlspecialchars(strip_tags((string)($params['icon']   ?? '')));
    $steamUniverse  = (int)($params['steam_universe'] ?? 0);
    $enabled        = (int)(bool)($params['enabled']  ?? false);

    $check = $GLOBALS['PDO']->query("SELECT * FROM `:prefix_mods` WHERE modfolder = ? OR name = ?;")
        ->single([$folder, $name]);

    if (!empty($check)) {
        throw new ApiError('mod_exists', 'A mod using that folder or name already exists.');
    }

    $GLOBALS['PDO']->query(
        "INSERT INTO `:prefix_mods`(name,icon,modfolder,steam_universe,enabled) VALUES (?,?,?,?,?)"
    )->execute([$name, $icon, $folder, $steamUniverse, $enabled]);

    Log::add(LogType::Message, 'Mod Added', "Mod ($name) has been added.");

    return [
        'reload'  => true,
        'message' => [
            'title' => 'Mod Added',
            'body'  => 'The game mod has been successfully added',
            'kind'  => 'green',
            'redir' => 'index.php?p=admin&c=mods',
        ],
    ];
}

/**
 * Delete a mod row + its on-disk icon (#1397).
 *
 * Modern JSON twin of the v1.x sourcebans.js `RemoveMod()` helper
 * (deleted at #1123 D1) — `page_admin_mods_list.tpl` wires the
 * trash-can button through `Actions.ModsRemove` via the
 * `#mod-delete-dialog` confirm + reason modal. There is no legacy
 * GET fallback for `o=remove` here (the v1.x JS helper went
 * straight to xajax then to this handler), so this is the single
 * delete path; the modal's no-JS / no-dispatcher fallback just
 * lands the operator back on the mods list.
 *
 * Inputs:
 *   - `mid`     (int, required)    — the mod id to remove.
 *   - `ureason` (string, optional) — admin-supplied reason. We trim
 *     it and append `Reason: …` to the audit-log entry when
 *     non-empty. Empty / omitted is allowed (the modal carries
 *     `aria-required="false"`); mod deletion is a lifecycle
 *     action, not a moderation flip, so we don't gate the call on
 *     it the way `bans.unban` / `comms.unblock` do.
 *
 * @param array{ mid?: int|string, ureason?: string } $params
 * @return array{
 *     remove: string,
 *     message: array{ title: string, body: string, kind: string, redir: string }
 * }
 */
function api_mods_remove(array $params): array
{
    $mid = (int)($params['mid'] ?? 0);
    // Trim whitespace so a textarea that contains only spaces produces an
    // empty reason (audit-log suffix omitted) rather than `Reason:    `.
    $ureason = trim((string)($params['ureason'] ?? ''));

    $GLOBALS['PDO']->query("SELECT icon, name FROM `:prefix_mods` WHERE mid = :mid");
    $GLOBALS['PDO']->bind(':mid', $mid);
    $row = $GLOBALS['PDO']->single();

    if ($row && !empty($row['icon'])) {
        @unlink(SB_ICONS . '/' . $row['icon']);
    }

    $GLOBALS['PDO']->query("DELETE FROM `:prefix_mods` WHERE mid = :mid");
    $GLOBALS['PDO']->bind(':mid', $mid);
    $ok = $GLOBALS['PDO']->execute();

    if (!$ok) {
        throw new ApiError('delete_failed', 'There was a problem deleting the MOD from the database. Check the logs for more info');
    }

    // #1397: trail the optional admin-supplied reason in the audit-log
    // entry so admins reading the log later can see *why* the mod was
    // removed. Mirrors the canonical "Reason: $ureason" suffix shape
    // from `api_admins_remove` / `api_bans_unban` / `api_comms_unblock`
    // — the suffix is omitted when the operator left the field blank.
    $modName = ($row && isset($row['name'])) ? (string) $row['name'] : ('mid ' . $mid);
    $logBody = "MOD ({$modName}) has been deleted.";
    if ($ureason !== '') {
        $logBody .= " Reason: {$ureason}";
    }
    Log::add(LogType::Message, 'MOD Deleted', $logBody);

    return [
        'remove'  => "mid_$mid",
        'message' => [
            'title' => 'MOD Deleted',
            'body'  => 'The selected MOD has been deleted from the database',
            'kind'  => 'green',
            'redir' => 'index.php?p=admin&c=mods',
        ],
    ];
}
