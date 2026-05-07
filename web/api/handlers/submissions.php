<?php
/*************************************************************************
This file is part of SourceBans++

SourceBans++ (c) 2014-2024 by SourceBans++ Dev Team

The SourceBans++ Web panel is licensed under a
Creative Commons Attribution-NonCommercial-ShareAlike 3.0 Unported License.

You should have received a copy of the license along with this
work.  If not, see <http://creativecommons.org/licenses/by-nc-sa/3.0/>.
*************************************************************************/

function api_submissions_remove(array $params): array
{
    global $userbank;
    $sid    = (int)($params['sid']    ?? 0);
    $archiv = (string)($params['archiv'] ?? '');

    if ($archiv === '1') { // archive
        $GLOBALS['PDO']->query("UPDATE `:prefix_submissions` SET archiv = '1', archivedby = :aid WHERE subid = :sid");
        $GLOBALS['PDO']->bind(':aid', $userbank->GetAid());
        $GLOBALS['PDO']->bind(':sid', $sid);
        $ok = $GLOBALS['PDO']->execute();

        $cnt = (int)($GLOBALS['PDO']->query("SELECT count(subid) AS cnt FROM `:prefix_submissions` WHERE archiv = '0'")->single()['cnt'] ?? 0);

        if (!$ok) {
            throw new ApiError('archive_failed', 'There was a problem moving the submission to the archive. Check the logs for more info');
        }
        Log::add('m', 'Submission Archived', "Submission ($sid) has been moved to the archive.");
        return [
            'remove'  => ["sid_$sid", "sid_{$sid}a"],
            'counter' => ['subcount' => $cnt],
            'message' => [
                'title' => 'Submission Archived',
                'body'  => 'The selected submission has been moved to the archive!',
                'kind'  => 'green',
                // #1275 — admin-bans is Pattern A; the operator was on
                // the submissions queue, so land them back on the same
                // section's current view (not the default add-ban
                // surface bare `?p=admin&c=bans` would route to).
                'redir' => 'index.php?p=admin&c=bans&section=submissions',
            ],
        ];
    }

    if ($archiv === '0') { // delete
        $GLOBALS['PDO']->query("DELETE FROM `:prefix_submissions` WHERE subid = :sid");
        $GLOBALS['PDO']->bind(':sid', $sid);
        $ok = $GLOBALS['PDO']->execute();

        $GLOBALS['PDO']->query("DELETE FROM `:prefix_demos` WHERE demid = :sid AND demtype = 'S'");
        $GLOBALS['PDO']->bind(':sid', $sid);
        $GLOBALS['PDO']->execute();

        $cnt = (int)($GLOBALS['PDO']->query("SELECT count(subid) AS cnt FROM `:prefix_submissions` WHERE archiv = '1'")->single()['cnt'] ?? 0);

        if (!$ok) {
            throw new ApiError('delete_failed', 'There was a problem deleting the submission from the database. Check the logs for more info');
        }
        Log::add('m', 'Submission Deleted', "Submission ($sid) has been deleted.");
        return [
            'remove'  => ["asid_$sid", "asid_{$sid}a"],
            'counter' => ['subcountarchiv' => $cnt],
            'message' => [
                'title' => 'Submission Deleted',
                'body'  => 'The selected submission has been deleted from the database',
                'kind'  => 'green',
                // #1275 — delete only fires from the archive view, so
                // land back on the archive (not the current queue).
                'redir' => 'index.php?p=admin&c=bans&section=submissions&view=archive',
            ],
        ];
    }

    if ($archiv === '2') { // restore
        $GLOBALS['PDO']->query("UPDATE `:prefix_submissions` SET archiv = '0', archivedby = NULL WHERE subid = :sid");
        $GLOBALS['PDO']->bind(':sid', $sid);
        $ok = $GLOBALS['PDO']->execute();

        $cnt = (int)($GLOBALS['PDO']->query("SELECT count(subid) AS cnt FROM `:prefix_submissions` WHERE archiv = '0'")->single()['cnt'] ?? 0);

        if (!$ok) {
            throw new ApiError('restore_failed', 'There was a problem restoring the submission from the archive. Check the logs for more info');
        }
        Log::add('m', 'Submission Restored', "Submission ($sid) has been restored from the archive.");
        return [
            'remove'  => ["asid_$sid", "asid_{$sid}a"],
            'counter' => ['subcountarchiv' => $cnt],
            'message' => [
                'title' => 'Submission Restored',
                'body'  => 'The selected submission has been restored from the archive!',
                'kind'  => 'green',
                // #1275 — restore moves the row back into the live
                // queue, so land the operator on the current view to
                // confirm the row reappeared.
                'redir' => 'index.php?p=admin&c=bans&section=submissions',
            ],
        ];
    }

    throw new ApiError('bad_request', 'Unknown archiv value');
}
