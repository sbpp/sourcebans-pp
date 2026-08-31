<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under the Elastic License 2.0.
// See LICENSE.txt for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

declare(strict_types=1);

namespace Sbpp\Rest;

use Sbpp\Api\Api;
use Sbpp\Api\ApiError;
use Sbpp\Db\Database;
use Sbpp\Log;
use Sbpp\Servers\SourceQueryCache;
use LogType;

/**
 * REST server resource. List/get are dedicated queries (never `rcon`).
 * Create/remove go through `Api::invoke`. PATCH is dedicated (no RPC handler).
 */
final class ServersService
{
    /**
     * @param array<string, mixed> $query
     * @return array{data: list<array<string, mixed>>, meta: array{page: int, per_page: int, total: int}}
     */
    public function list(array $query): array
    {
        $page = max(1, (int) ($query['page'] ?? 1));
        $perPage = (int) ($query['per_page'] ?? 30);
        if ($perPage < 1) {
            $perPage = 30;
        }
        $perPage = min(100, $perPage);
        $offset = ($page - 1) * $perPage;

        $where = '1=1';
        $binds = [];
        if (array_key_exists('enabled', $query) && $query['enabled'] !== '' && $query['enabled'] !== null) {
            $enabled = $query['enabled'];
            $flag = $enabled === true || $enabled === 'true' || $enabled === '1' || $enabled === 1;
            $where .= ' AND S.enabled = :enabled';
            $binds[':enabled'] = $flag ? 1 : 0;
        }

        $pdo = $this->db();
        $pdo->query('SELECT COUNT(*) AS c FROM `:prefix_servers` S WHERE ' . $where);
        foreach ($binds as $name => $value) {
            $pdo->bind($name, $value);
        }
        $countRow = $pdo->single();
        $total = is_array($countRow) ? (int) ($countRow['c'] ?? 0) : 0;

        $pdo->query(
            $this->selectSql()
            . ' WHERE ' . $where
            . ' ORDER BY S.sid ASC'
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
    public function get(int $sid): array
    {
        $row = $this->find($sid);
        if ($row === null) {
            throw new ApiError('not_found', 'Server not found.', null, 404);
        }
        return $this->toResource($row);
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function create(array $body): array
    {
        $ip = trim((string) ($body['ip'] ?? $body['address'] ?? ''));
        $port = (string) ($body['port'] ?? '');
        $rcon = (string) ($body['rcon'] ?? '');
        $rcon2 = array_key_exists('rcon2', $body) ? (string) $body['rcon2'] : $rcon;
        $mod = (int) ($body['mod'] ?? $body['mod_id'] ?? -2);
        $enabled = array_key_exists('enabled', $body)
            ? ($body['enabled'] === true || $body['enabled'] === 'true' || $body['enabled'] === 1 || $body['enabled'] === '1')
            : true;
        $groupIds = $this->intList($body['group_ids'] ?? null);
        $group = $groupIds === [] ? '0' : implode(',', $groupIds);

        $out = Api::invoke('servers.add', [
            'ip' => $ip,
            'port' => $port,
            'rcon' => $rcon,
            'rcon2' => $rcon2,
            'mod' => $mod,
            'enabled' => $enabled,
            'group' => $group,
        ]);
        $sid = (int) ($out['sid'] ?? 0);
        if ($sid <= 0) {
            throw new ApiError('server_error', 'Server was not created.', null, 500);
        }
        return $this->get($sid);
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function update(int $sid, array $body): array
    {
        $row = $this->find($sid);
        if ($row === null) {
            throw new ApiError('not_found', 'Server not found.', null, 404);
        }

        $ip = array_key_exists('ip', $body) || array_key_exists('address', $body)
            ? trim((string) ($body['ip'] ?? $body['address'] ?? ''))
            : (string) $row['ip'];
        $port = array_key_exists('port', $body)
            ? (int) $body['port']
            : (int) $row['port'];
        $mod = array_key_exists('mod', $body) || array_key_exists('mod_id', $body)
            ? (int) ($body['mod'] ?? $body['mod_id'] ?? 0)
            : (int) $row['modid'];
        $enabled = array_key_exists('enabled', $body)
            ? ($body['enabled'] === true || $body['enabled'] === 'true' || $body['enabled'] === 1 || $body['enabled'] === '1')
            : ((int) $row['enabled'] === 1);

        if ($ip === '') {
            throw new ApiError('validation', 'You must type the server address.', 'address', 400);
        }
        if (!filter_var($ip, FILTER_VALIDATE_IP) && !filter_var($ip, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            throw new ApiError('validation', 'You must type a valid IP or hostname.', 'address', 400);
        }
        if (strlen($ip) > 64) {
            throw new ApiError('validation', 'Server address must be at most 64 characters.', 'address', 400);
        }
        if ($port < 1 || $port > 65535) {
            throw new ApiError('validation', 'You must type a valid port number (1-65535).', 'port', 400);
        }

        $pdo = $this->db();
        $pdo->query(
            'SELECT sid FROM `:prefix_servers` WHERE ip = :ip AND port = :port AND sid != :sid'
        );
        $pdo->bind(':ip', $ip);
        $pdo->bind(':port', $port);
        $pdo->bind(':sid', $sid);
        $clash = $pdo->single();
        if (is_array($clash)) {
            throw new ApiError('duplicate', 'There already is a server with that IP:Port combination.', 'address', 409);
        }

        $pdo->query(
            'UPDATE `:prefix_servers`
                SET ip = :ip, port = :port, modid = :modid, enabled = :enabled
                WHERE sid = :sid'
        );
        $pdo->bind(':ip', $ip);
        $pdo->bind(':port', $port);
        $pdo->bind(':modid', $mod);
        $pdo->bind(':enabled', $enabled ? 1 : 0);
        $pdo->bind(':sid', $sid);
        $pdo->execute();

        if (array_key_exists('rcon', $body)) {
            $pdo->query('UPDATE `:prefix_servers` SET rcon = :rcon WHERE sid = :sid_rcon');
            $pdo->bind(':rcon', (string) $body['rcon']);
            $pdo->bind(':sid_rcon', $sid);
            $pdo->execute();
        }

        if (array_key_exists('group_ids', $body)) {
            $this->replaceGroups($sid, $this->intList($body['group_ids']));
        }

        Log::add(LogType::Message, 'Server Updated', "Server ({$ip}:{$port}) has been updated.");
        return $this->get($sid);
    }

    /**
     * @return array<string, mixed>
     */
    public function remove(int $sid): array
    {
        $this->get($sid);
        Api::invoke('servers.remove', ['sid' => $sid]);
        return ['id' => $sid];
    }

    /**
     * @return array<string, mixed>
     */
    public function rcon(int $sid, string $command): array
    {
        $this->get($sid);
        $out = Api::invoke('servers.send_rcon', [
            'sid' => $sid,
            'command' => $command,
            'output' => true,
        ]);
        unset($out['message'], $out['reload'], $out['__redirect']);
        return $out;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function toResource(array $row): array
    {
        $sid = (int) $row['sid'];
        $ip = (string) $row['ip'];
        $port = (int) $row['port'];
        return [
            'id' => $sid,
            'ip' => $ip,
            'port' => $port,
            'enabled' => (int) $row['enabled'] === 1,
            'mod' => [
                'id' => (int) $row['modid'],
                'name' => (string) ($row['mod_name'] ?? ''),
                'folder' => (string) ($row['modfolder'] ?? ''),
            ],
            'group_ids' => $this->groupIds($sid),
            'query' => $this->liveQuery($ip, $port),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function liveQuery(string $ip, int $port): ?array
    {
        $cached = SourceQueryCache::fetch($ip, $port);
        if ($cached === null) {
            return null;
        }
        $info = $cached['info'];
        $hostname = (string) preg_replace('/[\x00-\x1f]/', '', (string) ($info['HostName'] ?? ''));
        return [
            'hostname' => $hostname,
            'map' => basename((string) ($info['Map'] ?? '')),
            'players' => (int) ($info['Players'] ?? 0),
            'maxplayers' => (int) ($info['MaxPlayers'] ?? 0),
            'secure' => (bool) ($info['Secure'] ?? false),
        ];
    }

    /**
     * @return list<int>
     */
    private function groupIds(int $sid): array
    {
        $pdo = $this->db();
        $pdo->query('SELECT group_id FROM `:prefix_servers_groups` WHERE server_id = :sid ORDER BY group_id ASC');
        $pdo->bind(':sid', $sid);
        $ids = [];
        foreach ($pdo->resultset() as $row) {
            $gid = (int) ($row['group_id'] ?? 0);
            if ($gid > 0) {
                $ids[] = $gid;
            }
        }
        return $ids;
    }

    /**
     * @param list<int> $groupIds
     */
    private function replaceGroups(int $sid, array $groupIds): void
    {
        $pdo = $this->db();
        $pdo->beginTransaction();
        try {
            $pdo->query('DELETE FROM `:prefix_servers_groups` WHERE server_id = :sid');
            $pdo->bind(':sid', $sid);
            $pdo->execute();
            foreach ($groupIds as $gid) {
                if ($gid <= 0) {
                    continue;
                }
                $pdo->query(
                    'INSERT INTO `:prefix_servers_groups` (server_id, group_id) VALUES (:sid_ins, :gid)'
                );
                $pdo->bind(':sid_ins', $sid);
                $pdo->bind(':gid', $gid);
                $pdo->execute();
            }
            $pdo->endTransaction();
        } catch (\Throwable $e) {
            $pdo->cancelTransaction();
            throw $e;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function find(int $sid): ?array
    {
        if ($sid <= 0) {
            return null;
        }
        $pdo = $this->db();
        $pdo->query($this->selectSql() . ' WHERE S.sid = :sid');
        $pdo->bind(':sid', $sid);
        $row = $pdo->single();
        return is_array($row) ? $row : null;
    }

    private function selectSql(): string
    {
        return 'SELECT S.sid, S.ip, S.port, S.modid, S.enabled, M.name AS mod_name, M.modfolder'
            . ' FROM `:prefix_servers` S'
            . ' LEFT JOIN `:prefix_mods` M ON S.modid = M.mid';
    }

    /**
     * @param mixed $raw
     * @return list<int>
     */
    private function intList(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $v) {
            if (is_numeric($v)) {
                $n = (int) $v;
                if ($n > 0) {
                    $out[] = $n;
                }
            }
        }
        return array_values(array_unique($out));
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
