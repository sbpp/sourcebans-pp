<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under the Elastic License 2.0.
// See LICENSE.txt for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

declare(strict_types=1);

namespace Sbpp\Rest;

use Sbpp\Api\Api;
use Sbpp\Api\ApiError;
use Sbpp\Db\Database;

/**
 * REST ban-protest queue. List/get are dedicated queries. DELETE reuses
 * `protests.remove` with `archiv=0` (hard delete).
 */
final class ProtestsService
{
    /**
     * @param array<string, mixed> $query
     * @return array{data: list<array<string, mixed>>, meta: array{page: int, per_page: int, total: int}}
     */
    public function list(array $query): array
    {
        [$page, $perPage, $offset] = $this->page($query);
        $archived = $this->wantsArchived($query);
        $archivSql = $archived ? 'archiv <> 0' : 'archiv = 0';

        $pdo = $this->db();
        $countRow = $pdo->query("SELECT COUNT(*) AS c FROM `:prefix_protests` WHERE {$archivSql}")->single();
        $total = is_array($countRow) ? (int) ($countRow['c'] ?? 0) : 0;

        $pdo->query(
            'SELECT pid, bid, datesubmitted, reason, email, archiv, archivedby, pip'
            . " FROM `:prefix_protests` WHERE {$archivSql}"
            . ' ORDER BY pid DESC LIMIT :lim OFFSET :off'
        );
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
     * @return array<string, mixed>
     */
    public function get(int $pid): array
    {
        $pdo = $this->db();
        $pdo->query(
            'SELECT pid, bid, datesubmitted, reason, email, archiv, archivedby, pip'
            . ' FROM `:prefix_protests` WHERE pid = :pid'
        );
        $pdo->bind(':pid', $pid);
        $row = $pdo->single();
        if (!is_array($row)) {
            throw new ApiError('not_found', 'Protest not found.', null, 404);
        }
        return $this->toResource($row);
    }

    /**
     * @return array<string, mixed>
     */
    public function delete(int $pid): array
    {
        $this->get($pid);
        Api::invoke('protests.remove', ['pid' => $pid, 'archiv' => '0']);
        return ['id' => $pid];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function toResource(array $row): array
    {
        $archivedBy = $row['archivedby'] ?? null;
        return [
            'id' => (int) $row['pid'],
            'ban_id' => (int) $row['bid'],
            'submitted' => (int) ($row['datesubmitted'] ?? 0),
            'reason' => (string) ($row['reason'] ?? ''),
            'email' => (string) ($row['email'] ?? ''),
            'ip' => (string) ($row['pip'] ?? ''),
            'archived' => (int) ($row['archiv'] ?? 0) !== 0,
            'archived_by' => $archivedBy !== null && (int) $archivedBy > 0 ? (int) $archivedBy : null,
        ];
    }

    /**
     * @param array<string, mixed> $query
     */
    private function wantsArchived(array $query): bool
    {
        if (!array_key_exists('archived', $query)) {
            return false;
        }
        $v = $query['archived'];
        return $v === true || $v === 1 || $v === '1' || $v === 'true';
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
