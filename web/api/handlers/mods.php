<?php
/*************************************************************************
This file is part of SourceBans++

SourceBans++ (c) 2014-2024 by SourceBans++ Dev Team

The SourceBans++ Web panel is licensed under a
Creative Commons Attribution-NonCommercial-ShareAlike 3.0 Unported License.

You should have received a copy of the license along with this
work.  If not, see <http://creativecommons.org/licenses/by-nc-sa/3.0/>.
*************************************************************************/

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

    Log::add('m', 'Mod Added', "Mod ($name) has been added.");

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

function api_mods_remove(array $params): array
{
    $mid = (int)($params['mid'] ?? 0);

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

    Log::add('m', 'MOD Deleted', "MOD ({$row['name']}) has been deleted.");

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
