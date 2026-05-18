<?php
/*************************************************************************
This file is part of SourceBans++

SourceBans++ (c) 2014-2024 by SourceBans++ Dev Team

The SourceBans++ Web panel is licensed under a
Creative Commons Attribution-NonCommercial-ShareAlike 3.0 Unported License.

You should have received a copy of the license along with this
work.  If not, see <http://creativecommons.org/licenses/by-nc-sa/3.0/>.
*************************************************************************/

function api_protests_remove(array $params): array
{
    global $userbank, $username;
    $pid    = (int)($params['pid']    ?? 0);
    $archiv = (string)($params['archiv'] ?? '');

    if ($archiv === '0') {
        $GLOBALS['PDO']->query("DELETE FROM `:prefix_protests` WHERE pid = :pid");
        $GLOBALS['PDO']->bind(':pid', $pid);
        $ok = $GLOBALS['PDO']->execute();

        $GLOBALS['PDO']->query("DELETE FROM `:prefix_comments` WHERE type = 'P' AND bid = :pid");
        $GLOBALS['PDO']->bind(':pid', $pid);
        $GLOBALS['PDO']->execute();

        $cnt = (int)($GLOBALS['PDO']->query("SELECT count(pid) AS cnt FROM `:prefix_protests` WHERE archiv = '1'")->single()['cnt'] ?? 0);

        if (!$ok) {
            throw new ApiError('delete_failed', 'There was a problem deleting the protest from the database. Check the logs for more info');
        }

        Log::add(LogType::Message, 'Protest Deleted', "Protest ($pid) has been deleted.");
        return [
            'remove'        => ["apid_$pid", "apid_{$pid}a"],
            'counter'       => ['protcountarchiv' => $cnt],
            'message'       => [
                'title' => 'Protest Deleted',
                'body'  => 'The selected protest has been deleted from the database',
                'kind'  => 'green',
                // #1275 — delete only fires from the archive view, so
                // land back on the archive (not the default add-ban
                // surface bare `?p=admin&c=bans` would route to).
                'redir' => 'index.php?p=admin&c=bans&section=protests&view=archive',
            ],
        ];
    }

    if ($archiv === '1') {
        $GLOBALS['PDO']->query("UPDATE `:prefix_protests` SET archiv = '1', archivedby = :aid WHERE pid = :pid");
        $GLOBALS['PDO']->bind(':aid', $userbank->GetAid());
        $GLOBALS['PDO']->bind(':pid', $pid);
        $ok = $GLOBALS['PDO']->execute();

        $cnt = (int)($GLOBALS['PDO']->query("SELECT count(pid) AS cnt FROM `:prefix_protests` WHERE archiv = '0'")->single()['cnt'] ?? 0);

        if (!$ok) {
            throw new ApiError('archive_failed', 'There was a problem moving the protest to the archive. Check the logs for more info');
        }

        Log::add(LogType::Message, 'Protest Archived', "Protest ($pid) has been moved to the archive.");
        return [
            'remove'  => ["pid_$pid", "pid_{$pid}a"],
            'counter' => ['protcount' => $cnt],
            'message' => [
                'title' => 'Protest Archived',
                'body'  => 'The selected protest has been moved to the archive.',
                'kind'  => 'green',
                // #1275 — admin-bans is Pattern A; the operator was on
                // the protests queue, so land them back on the same
                // section's current view.
                'redir' => 'index.php?p=admin&c=bans&section=protests',
            ],
        ];
    }

    if ($archiv === '2') {
        $GLOBALS['PDO']->query("UPDATE `:prefix_protests` SET archiv = '0', archivedby = NULL WHERE pid = :pid");
        $GLOBALS['PDO']->bind(':pid', $pid);
        $ok = $GLOBALS['PDO']->execute();

        $cnt = (int)($GLOBALS['PDO']->query("SELECT count(pid) AS cnt FROM `:prefix_protests` WHERE archiv = '1'")->single()['cnt'] ?? 0);

        if (!$ok) {
            throw new ApiError('restore_failed', 'There was a problem restoring the protest from the archive. Check the logs for more info');
        }

        Log::add(LogType::Message, 'Protest Deleted', "Protest ($pid) has been restored from the archive.");
        return [
            'remove'  => ["apid_$pid", "apid_{$pid}a"],
            'counter' => ['protcountarchiv' => $cnt],
            'message' => [
                'title' => 'Protest Restored',
                'body'  => 'The selected protest has been restored from the archive.',
                'kind'  => 'green',
                // #1275 — restore moves the row back into the live
                // queue, so land the operator on the current view to
                // confirm the row reappeared.
                'redir' => 'index.php?p=admin&c=bans&section=protests',
            ],
        ];
    }

    throw new ApiError('bad_request', 'Unknown archiv value');
}
