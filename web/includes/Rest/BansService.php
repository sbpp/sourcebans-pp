<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under the Elastic License 2.0.
// See LICENSE.txt for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

declare(strict_types=1);

namespace Sbpp\Rest;

use BanRemoval;
use BanType;
use Sbpp\Api\Api;
use Sbpp\Api\ApiError;
use Sbpp\Db\Database;
use SteamID\SteamID;

/**
 * REST ban resource. List/get are dedicated queries (public hide-* applies).
 * Writes go through `Api::invoke` (`bans.add` / `bans.unban`).
 */
final class BansService
{
    /**
     * @param array<string, mixed> $query
     * @return array{data: list<array<string, mixed>>, meta: array{page: int, per_page: int, total: int}}
     */
    public function list(array $query): array
    {
        PruneBans();
        $page = max(1, (int) ($query['page'] ?? 1));
        $perPage = (int) ($query['per_page'] ?? 30);
        if ($perPage < 1) {
            $perPage = 30;
        }
        $perPage = min(100, $perPage);
        $offset = ($page - 1) * $perPage;

        [$whereSql, $binds] = $this->filters($query);
        $pdo = $this->db();

        $countSql = 'SELECT COUNT(*) AS c FROM `:prefix_bans` AS BA WHERE ' . $whereSql;
        $pdo->query($countSql);
        foreach ($binds as $name => $value) {
            $pdo->bind($name, $value);
        }
        $countRow = $pdo->single();
        $total = is_array($countRow) ? (int) ($countRow['c'] ?? 0) : 0;

        $pdo->query(
            $this->selectSql()
            . ' WHERE ' . $whereSql
            . ' ORDER BY BA.created DESC, BA.bid DESC'
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
    public function get(int $bid): array
    {
        PruneBans();
        if ($bid <= 0) {
            throw new ApiError('validation', 'Ban id must be a positive integer.', 'bid', 400);
        }
        $pdo = $this->db();
        $pdo->query($this->selectSql() . ' WHERE BA.bid = :bid');
        $pdo->bind(':bid', $bid);
        $row = $pdo->single();
        if (!is_array($row)) {
            throw new ApiError('not_found', 'Ban not found.', null, 404);
        }
        return $this->toResource($row);
    }

    /**
     * @param array<string, mixed> $body
     * @return array{ban: array<string, mixed>, kick: array<string, mixed>|null}
     */
    public function create(array $body): array
    {
        $banType = $this->bodyBanType($body);
        $params = [
            'nickname' => (string) ($body['name'] ?? $body['nickname'] ?? ''),
            'type' => $banType->value,
            'steam' => trim((string) ($body['steam'] ?? '')),
            'ip' => (string) ($body['ip'] ?? ''),
            'length' => (int) ($body['length'] ?? 0),
            'reason' => (string) ($body['reason'] ?? ''),
            'dfile' => '',
            'dname' => '',
            'fromsub' => 0,
        ];
        $out = Api::invoke('bans.add', $params);
        $bid = (int) ($out['bid'] ?? 0);
        if ($bid <= 0) {
            throw new ApiError('server_error', 'Ban was not created.', null, 500);
        }

        $kickMeta = null;
        if ($this->wantsKick($body)) {
            $kickit = is_array($out['kickit'] ?? null) ? $out['kickit'] : [];
            $check = (string) ($kickit['check'] ?? '');
            if ($check === '') {
                $row = $this->get($bid);
                $check = $banType === BanType::Steam
                    ? (string) ($row['steam'] ?? '')
                    : (string) ($row['ip'] ?? '');
            }
            if ($check !== '') {
                try {
                    $kickMeta = Kicker::fanOut($check, $banType->value);
                } catch (ApiError $e) {
                    $kickMeta = ['error' => $e->errorCode, 'message' => $e->getMessage()];
                }
            }
        }

        return ['ban' => $this->get($bid), 'kick' => $kickMeta];
    }

    /**
     * @return array<string, mixed>
     */
    public function unban(int $bid, string $ureason): array
    {
        if ($bid <= 0) {
            throw new ApiError('validation', 'Ban id must be a positive integer.', 'bid', 400);
        }
        Api::invoke('bans.unban', ['bid' => $bid, 'ureason' => $ureason]);
        return $this->get($bid);
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
            $where[] = 'BA.sid = :filter_sid';
            $binds[':filter_sid'] = $sid;
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
            if (!PublicVisibility::hidePlayerIps()) {
                $parts[] = 'BA.ip LIKE :search_ip';
                $binds[':search_ip'] = $like;
            }
            if ($authidPattern !== null) {
                $parts[] = 'BA.authid REGEXP :search_auth';
                $binds[':search_auth'] = $authidPattern;
            } else {
                $parts[] = 'BA.authid LIKE :search_auth';
                $binds[':search_auth'] = $like;
            }
            $parts[] = 'BA.name LIKE :search_name';
            $binds[':search_name'] = $like;
            $parts[] = 'BA.reason LIKE :search_reason';
            $binds[':search_reason'] = $like;
            $where[] = '(' . implode(' OR ', $parts) . ')';
        }

        return [implode(' AND ', $where), $binds];
    }

    private function stateFragment(string $state): ?string
    {
        return match ($state) {
            'permanent' => '(BA.RemoveType IS NULL AND BA.RemovedOn IS NULL AND BA.length = 0)',
            'active' => '(BA.RemoveType IS NULL AND BA.RemovedOn IS NULL AND (BA.length = 0 OR BA.ends > UNIX_TIMESTAMP()))',
            'expired' => "(BA.RemoveType = 'E'"
                . ' OR (BA.RemoveType IS NULL AND BA.length > 0 AND BA.ends < UNIX_TIMESTAMP() AND BA.RemovedOn IS NULL)'
                . ' OR (BA.RemoveType IS NULL AND BA.RemovedOn IS NOT NULL AND BA.length > 0 AND (BA.RemovedBy IS NULL OR BA.RemovedBy = 0)))',
            'unbanned' => "(BA.RemoveType IN ('D', 'U') OR (BA.RemovedOn IS NOT NULL AND BA.RemoveType IS NULL AND BA.RemovedBy IS NOT NULL AND BA.RemovedBy > 0))",
            default => null,
        };
    }

    private function selectSql(): string
    {
        return 'SELECT BA.bid, BA.type, BA.ip, BA.authid, BA.name, BA.created, BA.ends, BA.length,'
            . ' BA.reason, BA.sid, BA.RemovedOn, BA.RemovedBy, BA.RemoveType, BA.ureason,'
            . ' COALESCE(NULLIF(BA.admin_name, \'\'), AD.user) AS admin_name'
            . ' FROM `:prefix_bans` AS BA'
            . ' LEFT JOIN `:prefix_admins` AS AD ON BA.aid = AD.aid';
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function toResource(array $row): array
    {
        $banType = BanType::tryFrom((int) $row['type']) ?? BanType::Steam;
        $authid = (string) ($row['authid'] ?? '');
        $banIp = (string) ($row['ip'] ?? '');
        $created = (int) $row['created'];
        $length = (int) $row['length'];
        $ends = (int) $row['ends'];
        $removedOn = $row['RemovedOn'] !== null ? (int) $row['RemovedOn'] : null;
        $removedByInt = $row['RemovedBy'] !== null ? (int) $row['RemovedBy'] : 0;
        $removal = BanRemoval::tryFrom((string) ($row['RemoveType'] ?? ''));
        $isPre2AdminLift = $removal === null && $removedOn !== null && $removedByInt > 0;

        $state = match (true) {
            $removal === BanRemoval::Unbanned, $removal === BanRemoval::Deleted => 'unbanned',
            $removal === BanRemoval::Expired => 'expired',
            $isPre2AdminLift => 'unbanned',
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

        $hideIps = PublicVisibility::hidePlayerIps();
        $hideAdmin = PublicVisibility::hideAdminName();
        $sid = (int) ($row['sid'] ?? 0);

        $unbanReason = null;
        if ($state === 'unbanned' && !$hideAdmin) {
            $raw = trim((string) ($row['ureason'] ?? ''));
            $unbanReason = $raw !== '' ? $raw : null;
        }

        return [
            'id' => (int) $row['bid'],
            'player_name' => (string) ($row['name'] ?? ''),
            'type' => $banType === BanType::Ip ? 'ip' : 'steam',
            'steam' => $steam2,
            'steam64' => $steam64,
            'ip' => $hideIps || $banIp === '' ? null : $banIp,
            'reason' => (string) ($row['reason'] ?? ''),
            'created' => $created,
            'ends' => $ends,
            'length' => $length,
            'state' => $state,
            'admin_name' => $hideAdmin ? null : (string) ($row['admin_name'] ?? ''),
            'server_id' => $sid > 0 ? $sid : null,
            'unban_reason' => $unbanReason,
            'removed_at' => $hideAdmin ? null : $removedOn,
        ];
    }

    /**
     * @param array<string, mixed> $body
     */
    private function bodyBanType(array $body): BanType
    {
        $raw = $body['type'] ?? 0;
        if (is_string($raw)) {
            return strtolower($raw) === 'ip' ? BanType::Ip : BanType::Steam;
        }
        return BanType::tryFrom((int) $raw) ?? BanType::Steam;
    }

    /**
     * @param array<string, mixed> $body
     */
    private function wantsKick(array $body): bool
    {
        $kick = $body['kick'] ?? false;
        return $kick === true || $kick === 1;
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
