<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under the Elastic License 2.0.
// See LICENSE.txt for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

declare(strict_types=1);

namespace Sbpp\Rest;

use Sbpp\Api\Api;
use Sbpp\Api\ApiError;
use Sbpp\Config;
use Sbpp\Db\Database;

/**
 * REST comments on bans and comms. Writes reuse `bans.add_comment` /
 * `bans.edit_comment` / `bans.remove_comment`. GET is public and follows
 * `config.enablepubliccomments` plus `banlist.hideadminname`.
 */
final class CommentsService
{
    private const TYPE_LABEL = [
        'B' => 'ban',
        'C' => 'comm',
        'S' => 'submission',
        'P' => 'protest',
    ];

    /**
     * @param array<string, mixed> $query
     * @return array{data: list<array<string, mixed>>, meta: array{page: int, per_page: int, total: int}}
     */
    public function listForParent(int $parentId, string $ctype, array $query): array
    {
        $this->assertParent($parentId, $ctype);
        [$page, $perPage, $offset] = $this->page($query);

        if (!$this->commentsVisible()) {
            return [
                'data' => [],
                'meta' => ['page' => $page, 'per_page' => $perPage, 'total' => 0],
            ];
        }

        $pdo = $this->db();
        $pdo->query(
            'SELECT COUNT(*) AS c FROM `:prefix_comments` WHERE type = :type AND bid = :bid'
        );
        $pdo->bind(':type', $ctype);
        $pdo->bind(':bid', $parentId);
        $countRow = $pdo->single();
        $total = is_array($countRow) ? (int) ($countRow['c'] ?? 0) : 0;

        $pdo->query(
            'SELECT C.cid, C.bid, C.type, C.aid, C.commenttxt, C.added, C.editaid, C.edittime,'
            . ' (SELECT user FROM `:prefix_admins` WHERE aid = C.aid) AS author'
            . ' FROM `:prefix_comments` AS C'
            . ' WHERE C.type = :type AND C.bid = :bid'
            . ' ORDER BY C.added ASC, C.cid ASC LIMIT :lim OFFSET :off'
        );
        $pdo->bind(':type', $ctype);
        $pdo->bind(':bid', $parentId);
        $pdo->bind(':lim', $perPage);
        $pdo->bind(':off', $offset);
        $rows = $pdo->resultset();

        $data = [];
        foreach ($rows as $row) {
            $data[] = $this->toResource($row);
        }

        return [
            'data' => $data,
            'meta' => ['page' => $page, 'per_page' => $perPage, 'total' => $total],
        ];
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function create(int $parentId, string $ctype, array $body): array
    {
        $this->assertParent($parentId, $ctype);
        $text = $this->bodyText($body);
        Api::invoke('bans.add_comment', [
            'bid' => $parentId,
            'ctype' => $ctype,
            'ctext' => $text,
            'page' => -1,
        ]);
        $id = (int) $this->db()->lastInsertId();
        if ($id <= 0) {
            $id = $this->latestCid($parentId, $ctype);
        }
        if ($id <= 0) {
            throw new ApiError('server_error', 'Comment was not created.', null, 500);
        }
        return $this->get($id);
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function update(int $cid, array $body): array
    {
        $row = $this->row($cid);
        $text = $this->bodyText($body);
        Api::invoke('bans.edit_comment', [
            'cid' => $cid,
            'ctype' => (string) $row['type'],
            'ctext' => $text,
            'page' => -1,
        ]);
        return $this->get($cid);
    }

    /**
     * @return array<string, mixed>
     */
    public function delete(int $cid): array
    {
        $row = $this->row($cid);
        Api::invoke('bans.remove_comment', [
            'cid' => $cid,
            'ctype' => (string) $row['type'],
            'page' => -1,
        ]);
        return ['id' => $cid];
    }

    /**
     * @return array<string, mixed>
     */
    public function get(int $cid): array
    {
        return $this->toResource($this->row($cid));
    }

    /**
     * @return array<string, mixed>
     */
    private function row(int $cid): array
    {
        $pdo = $this->db();
        $pdo->query(
            'SELECT C.cid, C.bid, C.type, C.aid, C.commenttxt, C.added, C.editaid, C.edittime,'
            . ' (SELECT user FROM `:prefix_admins` WHERE aid = C.aid) AS author'
            . ' FROM `:prefix_comments` AS C WHERE C.cid = :cid'
        );
        $pdo->bind(':cid', $cid);
        $row = $pdo->single();
        if (!is_array($row)) {
            throw new ApiError('not_found', 'Comment not found.', null, 404);
        }
        return $row;
    }

    private function assertParent(int $parentId, string $ctype): void
    {
        $table = match ($ctype) {
            'B' => '`:prefix_bans`',
            'C' => '`:prefix_comms`',
            default => throw new ApiError('bad_type', 'Bad comment type.', null, 400),
        };
        $pdo = $this->db();
        $pdo->query("SELECT bid FROM {$table} WHERE bid = :id");
        $pdo->bind(':id', $parentId);
        $row = $pdo->single();
        if (!is_array($row)) {
            $label = $ctype === 'C' ? 'Comm' : 'Ban';
            throw new ApiError('not_found', $label . ' not found.', null, 404);
        }
    }

    /**
     * @param array<string, mixed> $body
     */
    private function bodyText(array $body): string
    {
        $text = trim((string) ($body['body'] ?? $body['ctext'] ?? ''));
        if ($text === '') {
            throw new ApiError('validation', 'body is required.', 'body', 400);
        }
        return $text;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function toResource(array $row): array
    {
        $hideAdmin = PublicVisibility::hideAdminName();
        $letter = (string) ($row['type'] ?? '');
        $author = $row['author'] ?? null;
        $editaid = $row['editaid'] ?? null;
        $edittime = $row['edittime'] ?? null;

        return [
            'id' => (int) $row['cid'],
            'parent_id' => (int) $row['bid'],
            'type' => self::TYPE_LABEL[$letter] ?? $letter,
            'body' => (string) ($row['commenttxt'] ?? ''),
            'created' => (int) ($row['added'] ?? 0),
            'author' => $hideAdmin || $author === null ? null : (string) $author,
            'author_aid' => $hideAdmin ? null : (int) $row['aid'],
            'edited_at' => $edittime !== null ? (int) $edittime : null,
            'editor_aid' => $hideAdmin || $editaid === null ? null : (int) $editaid,
        ];
    }

    private function commentsVisible(): bool
    {
        return Config::getBool('config.enablepubliccomments') || PublicVisibility::isAdmin();
    }

    private function latestCid(int $parentId, string $ctype): int
    {
        $pdo = $this->db();
        $pdo->query(
            'SELECT cid FROM `:prefix_comments` WHERE type = :type AND bid = :bid'
            . ' ORDER BY cid DESC LIMIT 1'
        );
        $pdo->bind(':type', $ctype);
        $pdo->bind(':bid', $parentId);
        $row = $pdo->single();
        return is_array($row) ? (int) ($row['cid'] ?? 0) : 0;
    }

    /**
     * @param array<string, mixed> $query
     * @return array{0: int, 1: int, 2: int}
     */
    private function page(array $query): array
    {
        $page = max(1, (int) ($query['page'] ?? 1));
        $perPage = (int) ($query['per_page'] ?? 30);
        if ($perPage < 1) {
            $perPage = 30;
        }
        $perPage = min(100, $perPage);
        return [$page, $perPage, ($page - 1) * $perPage];
    }

    private function db(): Database
    {
        $pdo = $GLOBALS['PDO'] ?? null;
        if ($pdo instanceof Database) {
            return $pdo;
        }
        return new Database(DB_HOST, (int) DB_PORT, DB_NAME, DB_USER, DB_PASS, DB_PREFIX, DB_CHARSET);
    }
}
