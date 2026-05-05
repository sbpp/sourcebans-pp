<?php
/*************************************************************************
This file is part of SourceBans++

SourceBans++ (c) 2014-2024 by SourceBans++ Dev Team

The SourceBans++ Web panel is licensed under a
Creative Commons Attribution-NonCommercial-ShareAlike 3.0 Unported License.

You should have received a copy of the license along with this
work.  If not, see <http://creativecommons.org/licenses/by-nc-sa/3.0/>.

Per-player notes for the player-detail drawer's Notes tab (#1165).

Notes are scoped per Steam ID — they follow the player across re-bans
and unbans rather than living per `bid`. The Notes tab in the drawer is
admin-only; the dispatcher gates these handlers on `requireAdmin=true`
so anonymous + non-admin logged-in callers are rejected before they
reach the SQL.

The body field is stored raw UTF-8 (no `htmlspecialchars` on insert)
per the JSON-API anti-pattern in AGENTS.md — Smarty / drawer JS escape
on display.
*************************************************************************/

/**
 * List the notes attached to a Steam ID, newest first. Returns the same
 * `{items, total}` shape `bans.player_history` / `comms.player_history`
 * use so the drawer's pane builders can share a list renderer.
 *
 * Inputs: `steam_id` (string, Steam2 form — `STEAM_0:1:N`).
 *
 * @return array{items: list<array{nid: int, body: string, created: int, created_human: string, author: string|null, author_aid: int}>, total: int}
 */
function api_notes_list(array $params): array
{
    $steamId = trim((string)($params['steam_id'] ?? ''));
    if ($steamId === '') {
        throw new ApiError('bad_request', 'steam_id is required', 'steam_id');
    }

    $rows = $GLOBALS['PDO']->query(
        "SELECT N.nid, N.body, N.created, N.aid,
                (SELECT user FROM `:prefix_admins` WHERE aid = N.aid) AS author
           FROM `:prefix_notes` AS N
          WHERE N.steam_id = ?
       ORDER BY N.created DESC, N.nid DESC"
    )->resultset([$steamId]);

    $items = [];
    foreach ($rows as $r) {
        $items[] = [
            'nid'           => (int)$r['nid'],
            'body'          => (string)$r['body'],
            'created'       => (int)$r['created'],
            'created_human' => Config::time((int)$r['created']),
            'author'        => $r['author'] !== null ? (string)$r['author'] : null,
            'author_aid'    => (int)$r['aid'],
        ];
    }

    return ['items' => $items, 'total' => count($items)];
}

/**
 * Add a note for the given Steam ID. The current admin's `aid` is
 * recorded as the author; the body is stored raw UTF-8 and escaped on
 * display.
 *
 * Inputs: `steam_id` (string), `body` (string, 1..4000 chars after trim).
 *
 * @return array{nid: int, item: array{nid: int, body: string, created: int, created_human: string, author: string|null, author_aid: int}}
 */
function api_notes_add(array $params): array
{
    global $userbank, $username;

    $steamId = trim((string)($params['steam_id'] ?? ''));
    $body    = trim((string)($params['body']     ?? ''));

    if ($steamId === '') {
        throw new ApiError('bad_request', 'steam_id is required', 'steam_id');
    }
    if ($body === '') {
        throw new ApiError('validation', 'Note body cannot be empty.', 'body');
    }
    // Cap at 4000 characters so a single overlong paste doesn't bloat
    // the table; the drawer textarea also enforces a maxlength matching
    // this so the client gets immediate feedback.
    if (mb_strlen($body) > 4000) {
        throw new ApiError('validation', 'Note body is limited to 4000 characters.', 'body');
    }

    $now = time();
    $aid = $userbank->GetAid();

    $GLOBALS['PDO']->query(
        "INSERT INTO `:prefix_notes` (steam_id, aid, body, created) VALUES (?, ?, ?, ?)"
    )->execute([$steamId, $aid, $body, $now]);

    $nid = (int)$GLOBALS['PDO']->lastInsertId();
    Log::add('m', 'Note Added', "$username added a note for $steamId");

    return [
        'nid'  => $nid,
        'item' => [
            'nid'           => $nid,
            'body'          => $body,
            'created'       => $now,
            'created_human' => Config::time($now),
            'author'        => (string)$username,
            'author_aid'    => $aid,
        ],
    ];
}

/**
 * Delete a single note. Only the note's author or an `ADMIN_OWNER` may
 * delete it; any other admin gets `forbidden` so the casual reader of
 * the Notes tab can't blow away another admin's pinned context.
 *
 * Inputs: `nid` (int).
 *
 * @return array{nid: int}
 */
function api_notes_delete(array $params): array
{
    global $userbank, $username;

    $nid = (int)($params['nid'] ?? 0);
    if ($nid <= 0) {
        throw new ApiError('bad_request', 'nid must be a positive integer', 'nid');
    }

    $row = $GLOBALS['PDO']->query("SELECT aid, steam_id FROM `:prefix_notes` WHERE nid = ?")
        ->single([$nid]);
    if (!$row) {
        throw new ApiError('not_found', 'Note not found.', null, 404);
    }

    $authorAid = (int)$row['aid'];
    $callerAid = $userbank->GetAid();
    $canDelete = $authorAid === $callerAid || $userbank->HasAccess(ADMIN_OWNER);

    if (!$canDelete) {
        throw new ApiError('forbidden', 'You can only delete your own notes.', null, 403);
    }

    $GLOBALS['PDO']->query("DELETE FROM `:prefix_notes` WHERE nid = ?")->execute([$nid]);

    Log::add('m', 'Note Deleted', "$username deleted note #$nid for {$row['steam_id']}");

    return ['nid' => $nid];
}
