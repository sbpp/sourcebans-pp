<?php
/*************************************************************************
This file is part of SourceBans++

SourceBans++ (c) 2014-2026 by SourceBans++ Dev Team

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

/*
 * SourceMod command/group override editor — extracted from
 * admin.admins.php during the #1123 B17 single-template redesign so
 * the overrides "tab" has a single PHP handler, a single
 * `Sbpp\View\AdminOverridesView` DTO, and a single `.tpl` per theme
 * (default + sbpp2026). admin.admins.php still routes here via a
 * top-level `require`, so the existing `?p=admin&c=admins` URL keeps
 * its three-tab layout.
 *
 * The save/delete/duplicate-check logic below is the verbatim block
 * that used to live at the bottom of admin.admins.php; the move is
 * mechanical so behaviour parity (incl. error messages and the
 * delete-by-blanking-name UX) is preserved.
 */

if (!defined("IN_SB")) {
    echo "You should not be here. Only follow links!";
    die();
}

global $userbank, $theme;

$overrides_error        = "";
$overrides_save_success = false;

try {
    if (isset($_POST['new_override_name'])) {
        if (isset($_POST['override_id'])) {
            $edit_errors = "";
            foreach ($_POST['override_id'] as $index => $id) {
                if ($_POST['override_type'][$index] != "command" && $_POST['override_type'][$index] != "group") {
                    continue;
                }

                $id = (int) $id;
                if (empty($_POST['override_name'][$index])) {
                    $GLOBALS['PDO']->query("DELETE FROM `:prefix_overrides` WHERE id = :id");
                    $GLOBALS['PDO']->bind(':id', $id);
                    $GLOBALS['PDO']->execute();
                    continue;
                }

                $chk = $GLOBALS['PDO']->query("SELECT id FROM `:prefix_overrides` WHERE name = ? AND type = ? AND id != ?")->resultset([
                    $_POST['override_name'][$index],
                    $_POST['override_type'][$index],
                    $id,
                ]);
                if (!empty($chk)) {
                    $edit_errors .= "&bull; There already is an override with name \\\"" . htmlspecialchars(addslashes($_POST['override_name'][$index])) . "\\\" from the selected type.<br />";
                    continue;
                }

                $GLOBALS['PDO']->query("UPDATE `:prefix_overrides` SET name = ?, type = ?, flags = ? WHERE id = ?")->execute([
                    $_POST['override_name'][$index],
                    $_POST['override_type'][$index],
                    trim($_POST['override_flags'][$index]),
                    $id,
                ]);
            }

            if (!empty($edit_errors)) {
                throw new Exception("There were errors applying your changes:<br /><br />" . $edit_errors);
            }
        }

        if (!empty($_POST['new_override_name'])) {
            if ($_POST['new_override_type'] != "command" && $_POST['new_override_type'] != "group") {
                throw new Exception("Invalid override type.");
            }

            $chk = $GLOBALS['PDO']->query("SELECT id FROM `:prefix_overrides` WHERE name = ? AND type = ?")->resultset([
                $_POST['new_override_name'],
                $_POST['new_override_type'],
            ]);
            if (!empty($chk)) {
                throw new Exception("There already is an override with that name from the selected type.");
            }

            $GLOBALS['PDO']->query("INSERT INTO `:prefix_overrides` (type, name, flags) VALUES (?, ?, ?)")->execute([
                $_POST['new_override_type'],
                $_POST['new_override_name'],
                trim($_POST['new_override_flags']),
            ]);
        }

        $overrides_save_success = true;
    }
} catch (Exception $e) {
    $overrides_error = $e->getMessage();
}

$overrides_rows = $GLOBALS['PDO']->query("SELECT id, type, name, flags FROM `:prefix_overrides`")->resultset();

// Carry both the legacy `id`/`name` keys (used by the default theme's
// page_admin_overrides.tpl) and the redesigned `oid`/`command_or_group`
// aliases (used by the sbpp2026 template) so the same DTO satisfies
// both .tpl files during the dual-theme rollout.
$overrides_list = [];
foreach ($overrides_rows as $row) {
    $row['oid']              = (int) $row['id'];
    $row['command_or_group'] = (string) $row['name'];
    $overrides_list[]        = $row;
}

\Sbpp\View\Renderer::render($theme, new \Sbpp\View\AdminOverridesView(
    permission_addadmin: $userbank->HasAccess(ADMIN_OWNER | ADMIN_ADD_ADMINS),
    overrides_error: $overrides_error,
    overrides_save_success: $overrides_save_success,
    overrides_list: $overrides_list,
));
