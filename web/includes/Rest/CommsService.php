<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under the Elastic License 2.0.
// See LICENSE.txt for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

declare(strict_types=1);

namespace Sbpp\Rest;

use BanRemoval;
use Sbpp\Api\Api;
use Sbpp\Api\ApiError;
use Sbpp\Auth\UserManager;
use Sbpp\Db\Database;
use SteamID\SteamID;

/**
 * REST comms resource. List/get are dedicated queries. Writes go through
 * `Api::invoke` (`comms.add` / `comms.unblock` / `comms.delete`).
 *
 * Stored `type`: 1 = mute (voice), 2 = gag (chat). Silence is two rows.
 */
final class CommsService
{
    /**
     * @param array<string, mixed> $query
     * @return array{data: list<array<string, mixed>>, meta: array{page: int, per_page: int, total: int}}
     */
    public function list(array $query): array
    {
        PruneComms();
        $page = max(1, (int) ($query['page'] ?? 1));
        $perPage = (int) ($query['per_page'] ?? 30);
        if ($perPage < 1) {
            $perPage = 30;
        }
        $perPage = min(100, $perPage);
        $offset = ($page - 1) * $perPage;

        [$whereSql, $binds] = $this->filters($query);
        $pdo = $this->db();

        $pdo->query('SELECT COUNT(*) AS c FROM `:prefix_comms` AS C WHERE ' . $whereSql);
        foreach ($binds as $name => $value) {
            $pdo->bind($name, $value);
        }
        $countRow = $pdo->single();
        $total = is_array($countRow) ? (int) ($countRow['c'] ?? 0) : 0;

        $pdo->query(
            $this->selectSql()
            . ' WHERE ' . $whereSql
            . ' ORDER BY C.created DESC, C.bid DESC'
            . ' LIMIT :lim OFFSET :off'
        );
        foreach ($binds as $name => $value) {
            $pdo->bind($name, $value);
        }
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
    public function get(int $cid): array
    {
        PruneComms();
        if ($cid <= 0) {
            throw new ApiError('validation', 'Block id must be a positive integer.', 'cid', 400);
        }
        $pdo = $this->db();
        $pdo->query($this->selectSql() . ' WHERE C.bid = :cid');
        $pdo->bind(':cid', $cid);
        $row = $pdo->single();
        if (!is_array($row)) {
            throw new ApiError('not_found', 'Block not found.', null, 404);
        }
        return $this->toResource($row);
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function create(array $body): array
    {
        $type = $this->bodyType($body);
        $steam = trim((string) ($body['steam'] ?? ''));
        $params = [
            'nickname' => (string) ($body['name'] ?? $body['nickname'] ?? ''),
            'type' => $type,
            'steam' => $steam,
            'length' => (int) ($body['length'] ?? 0),
            'reason' => (string) ($body['reason'] ?? ''),
        ];

        $pdo = $this->db();
        $beforeRow = $pdo->query('SELECT MAX(bid) AS m FROM `:prefix_comms`')->single();
        $before = is_array($beforeRow) ? (int) ($beforeRow['m'] ?? 0) : 0;

        Api::invoke('comms.add', $params);

        $created = $this->rowsAfter($before, $steam);
        if ($created === []) {
            throw new ApiError('server_error', 'Block was not created.', null, 500);
        }
        if (count($created) === 1) {
            return $created[0];
        }
        $byKind = [];
        foreach ($created as $block) {
            $byKind[(string) $block['kind']] = $block;
        }
        return [
            'kind' => 'silence',
            'blocks' => $created,
            'mute' => $byKind['mute'] ?? null,
            'gag' => $byKind['gag'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function unblock(int $cid, string $ureason): array
    {
        if ($cid <= 0) {
            throw new ApiError('validation', 'Block id must be a positive integer.', 'cid', 400);
        }
        Api::invoke('comms.unblock', ['bid' => $cid, 'ureason' => $ureason]);
        return $this->get($cid);
    }

    /**
     * @return array{id: int, deleted: true}
     */
    public function delete(int $cid): array
    {
        if ($cid <= 0) {
            throw new ApiError('validation', 'Block id must be a positive integer.', 'cid', 400);
        }
        Api::invoke('comms.delete', ['bid' => $cid]);
        return ['id' => $cid, 'deleted' => true];
    }

    /**
     * @param array<string, mixed> $query
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function filters(array $query): array
    {
        $where = ['1=1'];
        $binds = [];

        $state = (string) ($query['state'] ?? '');
        $fragment = $this->stateFragment($state);
        if ($fragment !== null) {
            $where[] = $fragment;
        }

        $sid = (int) ($query['server'] ?? 0);
        if ($sid > 0) {
            $where[] = 'C.sid = :filter_sid';
            $binds[':filter_sid'] = $sid;
        }

        $kind = strtolower(trim((string) ($query['kind'] ?? '')));
        if ($kind === 'mute') {
            $where[] = 'C.type = 1';
        } elseif ($kind === 'gag') {
            $where[] = 'C.type = 2';
        }

        $searchText = trim((string) ($query['search'] ?? ''));
        if ($searchText !== '') {
            $authidPattern = SteamID::toSearchPattern($searchText);
            try {
                if (SteamID::isValidID($searchText)) {
                    $converted = SteamID::toSteam2($searchText);
                    if (is_string($converted) && $converted !== '') {
                        $searchText = $converted;
                    }
                }
            } catch (\Exception) {
            }
            $like = '%' . $searchText . '%';
            $parts = [];
            if ($authidPattern !== null) {
                $parts[] = 'C.authid REGEXP :search_auth';
                $binds[':search_auth'] = $authidPattern;
            } else {
                $parts[] = 'C.authid LIKE :search_auth';
                $binds[':search_auth'] = $like;
            }
            $parts[] = 'C.name LIKE :search_name';
            $binds[':search_name'] = $like;
            $parts[] = 'C.reason LIKE :search_reason';
            $binds[':search_reason'] = $like;
            $where[] = '(' . implode(' OR ', $parts) . ')';
        }

        return [implode(' AND ', $where), $binds];
    }

    private function stateFragment(string $state): ?string
    {
        return match ($state) {
            'permanent' => '(C.RemoveType IS NULL AND C.RemovedOn IS NULL AND C.length = 0)',
            'active' => '(C.RemoveType IS NULL AND C.RemovedOn IS NULL AND (C.length = 0 OR C.ends > UNIX_TIMESTAMP()))',
            'expired' => "(C.RemoveType = 'E'"
                . ' OR (C.RemoveType IS NULL AND C.length > 0 AND C.ends < UNIX_TIMESTAMP() AND C.RemovedOn IS NULL)'
                . ' OR (C.RemoveType IS NULL AND C.RemovedOn IS NOT NULL AND C.length > 0 AND (C.RemovedBy IS NULL OR C.RemovedBy = 0)))',
            'unmuted', 'unbanned' => "(C.RemoveType IN ('D', 'U') OR (C.RemovedOn IS NOT NULL AND C.RemoveType IS NULL AND C.RemovedBy IS NOT NULL AND C.RemovedBy > 0))",
            default => null,
        };
    }

    private function selectSql(): string
    {
        return 'SELECT C.bid, C.type, C.authid, C.name, C.created, C.ends, C.length,'
            . ' C.reason, C.sid, C.RemovedOn, C.RemovedBy, C.RemoveType, C.ureason,'
            . ' COALESCE(NULLIF(C.admin_name, \'\'), AD.user) AS admin_name'
            . ' FROM `:prefix_comms` AS C'
            . ' LEFT JOIN `:prefix_admins` AS AD ON C.aid = AD.aid';
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function toResource(array $row): array
    {
        $type = (int) $row['type'];
        $kind = match ($type) {
            1 => 'mute',
            2 => 'gag',
            default => 'unknown',
        };
        $authid = (string) ($row['authid'] ?? '');
        $created = (int) $row['created'];
        $length = (int) $row['length'];
        $ends = (int) $row['ends'];
        $removedOn = $row['RemovedOn'] !== null ? (int) $row['RemovedOn'] : null;
        $removedByInt = $row['RemovedBy'] !== null ? (int) $row['RemovedBy'] : 0;
        $removal = BanRemoval::tryFrom((string) ($row['RemoveType'] ?? ''));
        $isPre2AdminLift = $removal === null && $removedOn !== null && $removedByInt > 0;

        $state = match (true) {
            $removal === BanRemoval::Unbanned, $removal === BanRemoval::Deleted => 'unmuted',
            $removal === BanRemoval::Expired => 'expired',
            $isPre2AdminLift => 'unmuted',
            $length === 0 => 'permanent',
            $ends > 0 && $ends < time() => 'expired',
            default => 'active',
        };

        $steam2 = ($authid !== '' && SteamID::isValidID($authid)) ? $authid : null;
        $steam64 = null;
        if ($steam2 !== null) {
            $converted = SteamID::toSteam64($steam2);
            if ($converted !== false && $converted !== null && $converted !== '') {
                $steam64 = (string) $converted;
            }
        }

        $hideAdmin = PublicVisibility::hideAdminName();
        $sid = (int) ($row['sid'] ?? 0);
        $unblockReason = null;
        if ($state === 'unmuted' && !$hideAdmin) {
            $raw = trim((string) ($row['ureason'] ?? ''));
            $unblockReason = $raw !== '' ? $raw : null;
        }

        return [
            'id' => (int) $row['bid'],
            'player_name' => (string) ($row['name'] ?? ''),
            'kind' => $kind,
            'steam' => $steam2,
            'steam64' => $steam64,
            'reason' => (string) ($row['reason'] ?? ''),
            'created' => $created,
            'ends' => $ends,
            'length' => $length,
            'state' => $state,
            'admin_name' => $hideAdmin ? null : (string) ($row['admin_name'] ?? ''),
            'server_id' => $sid > 0 ? $sid : null,
            'unblock_reason' => $unblockReason,
            'removed_at' => $hideAdmin ? null : $removedOn,
        ];
    }

    /**
     * @param array<string, mixed> $body
     */
    private function bodyType(array $body): int
    {
        $kind = strtolower(trim((string) ($body['kind'] ?? '')));
        if ($kind !== '') {
            return match ($kind) {
                'mute' => 1,
                'gag' => 2,
                'silence' => 3,
                default => throw new ApiError('validation', 'kind must be mute, gag, or silence.', 'kind', 400),
            };
        }
        $type = (int) ($body['type'] ?? 0);
        if (!in_array($type, [1, 2, 3], true)) {
            throw new ApiError('validation', 'type must be 1 (mute), 2 (gag), or 3 (silence).', 'type', 400);
        }
        return $type;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rowsAfter(int $before, string $rawSteam): array
    {
        $steam2 = $rawSteam;
        if ($rawSteam !== '' && SteamID::isValidID($rawSteam)) {
            $converted = SteamID::toSteam2($rawSteam);
            if (is_string($converted) && $converted !== '') {
                $steam2 = $converted;
            }
        }
        /** @var UserManager $userbank */
        $userbank = $GLOBALS['userbank'];
        $aid = $userbank->GetAid();
        $pdo = $this->db();
        $pdo->query(
            $this->selectSql()
            . ' WHERE C.bid > :before AND C.authid = :authid AND C.aid = :aid'
            . ' ORDER BY C.bid ASC'
        );
        $pdo->bind(':before', $before);
        $pdo->bind(':authid', $steam2);
        $pdo->bind(':aid', $aid);
        $rows = $pdo->resultset();
        $out = [];
        foreach ($rows as $row) {
            $out[] = $this->toResource($row);
        }
        return $out;
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
