<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under the Elastic License 2.0.
// See LICENSE.txt for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

declare(strict_types=1);

namespace Sbpp\Rest;

use Sbpp\Api\Api;
use Sbpp\Api\ApiError;
use Sbpp\Db\Database;
use SteamID\SteamID;

/**
 * REST ban-submission queue. List/get are dedicated queries. DELETE reuses
 * `submissions.remove` with `archiv=0` (hard delete).
 */
final class SubmissionsService
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
        $countRow = $pdo->query("SELECT COUNT(*) AS c FROM `:prefix_submissions` WHERE {$archivSql}")->single();
        $total = is_array($countRow) ? (int) ($countRow['c'] ?? 0) : 0;

        $pdo->query(
            'SELECT subid, submitted, ModID, SteamId, name, email, reason, ip, subname, sip,'
            . ' archiv, archivedby, server'
            . " FROM `:prefix_submissions` WHERE {$archivSql}"
            . ' ORDER BY subid DESC LIMIT :lim OFFSET :off'
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
    public function get(int $sid): array
    {
        $pdo = $this->db();
        $pdo->query(
            'SELECT subid, submitted, ModID, SteamId, name, email, reason, ip, subname, sip,'
            . ' archiv, archivedby, server'
            . ' FROM `:prefix_submissions` WHERE subid = :sid'
        );
        $pdo->bind(':sid', $sid);
        $row = $pdo->single();
        if (!is_array($row)) {
            throw new ApiError('not_found', 'Submission not found.', null, 404);
        }
        return $this->toResource($row);
    }

    /**
     * @return array<string, mixed>
     */
    public function delete(int $sid): array
    {
        $this->get($sid);
        Api::invoke('submissions.remove', ['sid' => $sid, 'archiv' => '0']);
        return ['id' => $sid];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function toResource(array $row): array
    {
        $rawSteam = trim((string) ($row['SteamId'] ?? ''));
        $steam2 = null;
        $steam64 = null;
        if ($rawSteam !== '' && SteamID::isValidID($rawSteam)) {
            $steam2 = SteamID::toSteam2($rawSteam);
            $converted = SteamID::toSteam64($steam2);
            if ($converted !== false && $converted !== null && $converted !== '') {
                $steam64 = (string) $converted;
            }
        }

        $archivedBy = $row['archivedby'] ?? null;
        $serverId = (int) ($row['server'] ?? 0);
        $modId = (int) ($row['ModID'] ?? 0);
        $subname = trim((string) ($row['subname'] ?? ''));
        $sip = trim((string) ($row['sip'] ?? ''));

        return [
            'id' => (int) $row['subid'],
            'steam' => $steam2 ?? ($rawSteam !== '' ? $rawSteam : null),
            'steam64' => $steam64,
            'player_name' => (string) ($row['name'] ?? ''),
            'email' => (string) ($row['email'] ?? ''),
            'reason' => (string) ($row['reason'] ?? ''),
            'ip' => (string) ($row['ip'] ?? ''),
            'submitter_name' => $subname !== '' ? $subname : null,
            'submitter_ip' => $sip !== '' ? $sip : null,
            'server_id' => $serverId > 0 ? $serverId : null,
            'mod_id' => $modId > 0 ? $modId : null,
            'submitted' => (int) ($row['submitted'] ?? 0),
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
