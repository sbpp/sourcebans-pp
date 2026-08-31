<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under the Elastic License 2.0.
// See LICENSE.txt for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

declare(strict_types=1);

namespace Sbpp\Rest;

use Sbpp\Api\Api;
use Sbpp\Api\ApiError;
use Sbpp\Auth\UserManager;
use Sbpp\Db\Database;
use Sbpp\Security\Crypto;
use SteamID\SteamID;
use WebPermission;

/**
 * REST admin resource: list, get, upsert by aid/Steam64, deactivate.
 */
final class AdminsService
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

        $pdo = $this->db();
        $countRow = $pdo->query('SELECT COUNT(*) AS c FROM `:prefix_admins`')->single();
        $total = is_array($countRow) ? (int) ($countRow['c'] ?? 0) : 0;

        $pdo->query(
            'SELECT A.aid, A.user, A.authid, A.email, A.enabled, A.immunity, A.gid, A.srv_group, A.lastvisit,'
            . ' WG.name AS web_group_name, SG.id AS server_group_id'
            . ' FROM `:prefix_admins` A'
            . ' LEFT JOIN `:prefix_groups` WG ON A.gid = WG.gid'
            . ' LEFT JOIN `:prefix_srvgroups` SG ON A.srv_group = SG.name'
            . ' ORDER BY A.aid ASC'
            . ' LIMIT :lim OFFSET :off'
        );
        $pdo->bind(':lim', $perPage);
        $pdo->bind(':off', $offset);
        $rows = $pdo->resultset();

        $data = [];
        foreach ($rows as $row) {
            $data[] = $this->toResource($row, $this->serverIds((int) $row['aid']));
        }

        return [
            'data' => $data,
            'meta' => ['page' => $page, 'per_page' => $perPage, 'total' => $total],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function get(AdminId $id): array
    {
        $row = $this->find($id);
        if ($row === null) {
            throw new ApiError('not_found', 'Admin not found.', null, 404);
        }
        return $this->toResource($row, $this->serverIds((int) $row['aid']));
    }

    /**
     * PUT (upsert on Steam64, replace-existing on aid) or PATCH (merge, no create).
     *
     * @param array<string, mixed> $body
     */
    public function upsert(AdminId $id, array $body, string $method): Response
    {
        $this->assertBodySteamMatchesPath($id, $body);
        $existing = $this->find($id);

        if ($id->aid !== null) {
            if ($existing === null) {
                throw new ApiError('not_found', 'Admin not found.', null, 404);
            }
            $updated = $this->update((int) $existing['aid'], $body, reactivate: false);
            return Envelope::ok($updated['admin'], ['rehash' => $updated['rehash']]);
        }

        if ($existing === null) {
            if ($method === 'PATCH') {
                throw new ApiError('not_found', 'Admin not found.', null, 404);
            }
            $created = $this->create((string) $id->steam64, $body);
            return Envelope::ok($created['admin'], ['rehash' => $created['rehash']], 201);
        }

        $updated = $this->update((int) $existing['aid'], $body, reactivate: $method === 'PUT');
        return Envelope::ok($updated['admin'], ['rehash' => $updated['rehash']]);
    }

    /**
     * @return array<string, mixed>
     */
    public function deactivate(AdminId $id, string $reason): array
    {
        $row = $this->requireRow($id);
        $aid = (int) $row['aid'];
        $this->refuseSelf($aid, 'deactivate');
        $out = Api::invoke('admins.deactivate', ['aid' => $aid, 'ureason' => $reason]);
        $sids = $this->rehashSidsFromHandler($out, $aid);
        $fresh = $this->requireRow(new AdminId($aid, null));
        return [
            'admin' => $this->toResource($fresh, $this->serverIds($aid)),
            'rehash' => Rehasher::run($sids),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function reactivate(AdminId $id, string $reason): array
    {
        $row = $this->requireRow($id);
        $aid = (int) $row['aid'];
        $out = Api::invoke('admins.reactivate', ['aid' => $aid, 'ureason' => $reason]);
        $sids = $this->rehashSidsFromHandler($out, $aid);
        $fresh = $this->requireRow(new AdminId($aid, null));
        return [
            'admin' => $this->toResource($fresh, $this->serverIds($aid)),
            'rehash' => Rehasher::run($sids),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function remove(AdminId $id, string $reason): array
    {
        $row = $this->requireRow($id);
        $aid = (int) $row['aid'];
        $this->refuseSelf($aid, 'delete');
        $sids = function_exists('_api_admins_rehash_sids') ? _api_admins_rehash_sids($aid) : [];
        $out = Api::invoke('admins.remove', ['aid' => $aid, 'ureason' => $reason]);
        $fromHandler = $this->sidsFromCsv(isset($out['rehash']) && is_string($out['rehash']) ? $out['rehash'] : null);
        return [
            'deleted' => $aid,
            'rehash' => Rehasher::run($fromHandler !== [] ? $fromHandler : $sids),
        ];
    }

    /**
     * @param array<string, mixed> $body
     * @return array{admin: array<string, mixed>, rehash: array<string, mixed>}
     */
    private function create(string $steam64, array $body): array
    {
        global $userbank;
        $name = trim((string) ($body['name'] ?? ''));
        if ($name === '') {
            throw new ApiError('validation', 'You must type a name for the admin.', 'name', 400);
        }
        if (str_contains($name, "'")) {
            throw new ApiError('validation', "An admin name can not contain a \"'\".", 'name', 400);
        }
        if ($userbank->isNameTaken($name)) {
            throw new ApiError('conflict', 'An admin with this name already exists.', 'name', 409);
        }

        $steam2 = SteamID::toSteam2($steam64);
        if ($userbank->isSteamIDTaken($steam2)) {
            throw new ApiError('conflict', 'An admin with this Steam ID already exists.', 'steam', 409);
        }

        $webGroupId = $this->optionalInt($body, 'web_group_id');
        $email = trim((string) ($body['email'] ?? ''));
        if ($webGroupId !== null && $webGroupId > 0 && $email === '') {
            throw new ApiError('validation', 'You must type an e-mail address.', 'email', 400);
        }
        if ($email !== '' && $userbank->isEmailTaken($email)) {
            throw new ApiError('conflict', 'This email address is already in use.', 'email', 409);
        }

        $gid = $this->resolveWebGroup($webGroupId);
        $srv = $this->resolveServerGroup($this->optionalInt($body, 'server_group_id'));
        $immunity = max(0, (int) ($body['immunity'] ?? 0));
        $password = (string) ($body['password'] ?? '');
        if ($password === '') {
            $password = Crypto::genPassword();
        }
        if (strlen($password) < MIN_PASS_LENGTH) {
            throw new ApiError(
                'validation',
                'Your password must be at-least ' . MIN_PASS_LENGTH . ' characters long.',
                'password',
                400,
            );
        }

        $aid = $userbank->AddAdmin(
            $name,
            $steam2,
            $password,
            $email,
            $gid,
            0,
            $srv['name'],
            '',
            $immunity,
            '',
        );
        if ($aid <= -1) {
            throw new ApiError('create_failed', 'The admin failed to be added to the database.', null, 500);
        }

        $serverIds = $this->optionalIntList($body, 'server_ids');
        $this->replaceServerAccess($aid, $srv['id'], $serverIds ?? []);

        $sids = function_exists('_api_admins_rehash_sids') ? _api_admins_rehash_sids($aid) : [];
        $fresh = $this->requireRow(new AdminId($aid, null));
        return [
            'admin' => $this->toResource($fresh, $this->serverIds($aid)),
            'rehash' => Rehasher::run($sids),
        ];
    }

    /**
     * @param array<string, mixed> $body
     * @return array{admin: array<string, mixed>, rehash: array<string, mixed>}
     */
    private function update(int $aid, array $body, bool $reactivate): array
    {
        global $userbank;
        $pdo = $this->db();
        $pdo->query('SELECT user, authid, email, gid, srv_group, immunity, enabled FROM `:prefix_admins` WHERE aid = :aid');
        $pdo->bind(':aid', $aid);
        $current = $pdo->single();
        if (!is_array($current)) {
            throw new ApiError('not_found', 'Admin not found.', null, 404);
        }

        $name = array_key_exists('name', $body) ? trim((string) $body['name']) : (string) $current['user'];
        if ($name === '') {
            throw new ApiError('validation', 'You must type a name for the admin.', 'name', 400);
        }
        if (str_contains($name, "'")) {
            throw new ApiError('validation', "An admin name can not contain a \"'\".", 'name', 400);
        }
        if ($name !== (string) $current['user'] && $userbank->isNameTaken($name)) {
            throw new ApiError('conflict', 'An admin with this name already exists.', 'name', 409);
        }

        $steam2 = (string) $current['authid'];
        if (array_key_exists('steam', $body) && trim((string) $body['steam']) !== '') {
            $rawSteam = trim((string) $body['steam']);
            if (!preg_match(SteamID::HANDLER_STRICT_REGEX, $rawSteam)) {
                throw new ApiError('validation', 'Please enter a valid Steam ID or Community ID.', 'steam', 400);
            }
            $steam2 = SteamID::toSteam2($rawSteam);
            if ($steam2 !== (string) $current['authid'] && $userbank->isSteamIDTaken($steam2)) {
                throw new ApiError('conflict', 'An admin with this Steam ID already exists.', 'steam', 409);
            }
        }

        $email = array_key_exists('email', $body) ? trim((string) $body['email']) : (string) $current['email'];
        $webGroupId = array_key_exists('web_group_id', $body)
            ? $this->optionalInt($body, 'web_group_id')
            : (int) $current['gid'];
        $gid = array_key_exists('web_group_id', $body)
            ? $this->resolveWebGroup($webGroupId)
            : (int) $current['gid'];
        if ($gid > 0 && $email === '') {
            throw new ApiError('validation', 'You must type an e-mail address.', 'email', 400);
        }
        if ($email !== '' && $email !== (string) $current['email'] && $userbank->isEmailTaken($email)) {
            throw new ApiError('conflict', 'This email address is already in use.', 'email', 409);
        }

        $immunity = array_key_exists('immunity', $body)
            ? max(0, (int) $body['immunity'])
            : (int) $current['immunity'];

        $srvName = (string) ($current['srv_group'] ?? '');
        $srvId = -1;
        if (array_key_exists('server_group_id', $body)) {
            $srv = $this->resolveServerGroup($this->optionalInt($body, 'server_group_id'));
            $srvName = $srv['name'];
            $srvId = $srv['id'];
        } else {
            $pdo->query('SELECT id FROM `:prefix_srvgroups` WHERE name = :name');
            $pdo->bind(':name', $srvName);
            $sg = $pdo->single();
            $srvId = is_array($sg) ? (int) $sg['id'] : -1;
        }

        $enabled = (int) ($current['enabled'] ?? 1);
        if ($reactivate) {
            $enabled = 1;
        }

        $pdo->query(
            'UPDATE `:prefix_admins` SET user = :user, authid = :authid, email = :email,'
            . ' gid = :gid, immunity = :immunity, srv_group = :srv_group, enabled = :enabled'
            . ' WHERE aid = :aid'
        );
        $pdo->bind(':user', $name);
        $pdo->bind(':authid', $steam2);
        $pdo->bind(':email', $email);
        $pdo->bind(':gid', $gid);
        $pdo->bind(':immunity', $immunity);
        $pdo->bind(':srv_group', $srvName);
        $pdo->bind(':enabled', $enabled);
        $pdo->bind(':aid', $aid);
        $pdo->execute();

        if (array_key_exists('server_ids', $body) || array_key_exists('server_group_id', $body)) {
            $serverIds = array_key_exists('server_ids', $body)
                ? ($this->optionalIntList($body, 'server_ids') ?? [])
                : $this->serverIds($aid);
            $this->replaceServerAccess($aid, $srvId, $serverIds);
        }

        $sids = function_exists('_api_admins_rehash_sids') ? _api_admins_rehash_sids($aid) : [];
        $fresh = $this->requireRow(new AdminId($aid, null));
        return [
            'admin' => $this->toResource($fresh, $this->serverIds($aid)),
            'rehash' => Rehasher::run($sids),
        ];
    }

    /**
     * @param array<string, mixed> $body
     */
    private function assertBodySteamMatchesPath(AdminId $id, array $body): void
    {
        if (!$id->isSteam64() || !array_key_exists('steam', $body)) {
            return;
        }
        $raw = trim((string) $body['steam']);
        if ($raw === '') {
            return;
        }
        if (!preg_match(SteamID::HANDLER_STRICT_REGEX, $raw)) {
            throw new ApiError('validation', 'Please enter a valid Steam ID or Community ID.', 'steam', 400);
        }
        $path64 = (string) $id->steam64;
        $body64 = SteamID::toSteam64(SteamID::toSteam2($raw));
        if ((string) $body64 !== $path64) {
            throw new ApiError(
                'conflict',
                'Body steam does not match the Steam64 in the URL.',
                'steam',
                409,
            );
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function find(AdminId $id): ?array
    {
        $pdo = $this->db();
        $sql = 'SELECT A.aid, A.user, A.authid, A.email, A.enabled, A.immunity, A.gid, A.srv_group, A.lastvisit,'
            . ' WG.name AS web_group_name, SG.id AS server_group_id'
            . ' FROM `:prefix_admins` A'
            . ' LEFT JOIN `:prefix_groups` WG ON A.gid = WG.gid'
            . ' LEFT JOIN `:prefix_srvgroups` SG ON A.srv_group = SG.name'
            . ' WHERE ';
        if ($id->steam64 !== null) {
            $steam2 = SteamID::toSteam2($id->steam64);
            $pdo->query($sql . 'A.authid = :authid LIMIT 1');
            $pdo->bind(':authid', $steam2);
        } else {
            $pdo->query($sql . 'A.aid = :aid LIMIT 1');
            $pdo->bind(':aid', (int) $id->aid);
        }
        $row = $pdo->single();
        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function requireRow(AdminId $id): array
    {
        $row = $this->find($id);
        if ($row === null) {
            throw new ApiError('not_found', 'Admin not found.', null, 404);
        }
        return $row;
    }

    /**
     * @param array<string, mixed> $row
     * @param list<int> $serverIds
     * @return array<string, mixed>
     */
    private function toResource(array $row, array $serverIds): array
    {
        $steam2 = (string) ($row['authid'] ?? '');
        $steam64 = null;
        if ($steam2 !== '' && SteamID::isValidID($steam2)) {
            $converted = SteamID::toSteam64($steam2);
            if ($converted !== false && $converted !== null && $converted !== '') {
                $steam64 = (string) $converted;
            }
        }
        $gid = (int) ($row['gid'] ?? -1);
        $rawSrvGid = $row['server_group_id'] ?? null;
        $srvGroupId = ($rawSrvGid !== null && $rawSrvGid !== '' && (int) $rawSrvGid > 0)
            ? (int) $rawSrvGid
            : null;

        return [
            'id' => (int) $row['aid'],
            'name' => (string) $row['user'],
            'steam' => $steam2 !== '' ? $steam2 : null,
            'steam64' => $steam64,
            'email' => (string) ($row['email'] ?? ''),
            'enabled' => (int) ($row['enabled'] ?? 1) === 1,
            'immunity' => (int) ($row['immunity'] ?? 0),
            'web_group_id' => $gid > 0 ? $gid : null,
            'web_group_name' => $gid > 0 ? (string) ($row['web_group_name'] ?? '') : null,
            'server_group_id' => $srvGroupId,
            'server_group_name' => $srvGroupId !== null ? (string) ($row['srv_group'] ?? '') : null,
            'server_ids' => $serverIds,
            'lastvisit' => $row['lastvisit'] === null ? null : (int) $row['lastvisit'],
        ];
    }

    /**
     * @return list<int>
     */
    private function serverIds(int $aid): array
    {
        $pdo = $this->db();
        $pdo->query(
            'SELECT server_id FROM `:prefix_admins_servers_groups`'
            . ' WHERE admin_id = :aid AND server_id > 0'
        );
        $pdo->bind(':aid', $aid);
        $ids = [];
        foreach ($pdo->resultset() as $row) {
            $ids[] = (int) $row['server_id'];
        }
        return $ids;
    }

    /**
     * @param list<int> $serverIds
     */
    private function replaceServerAccess(int $aid, int $srvGroupId, array $serverIds): void
    {
        $pdo = $this->db();
        $pdo->beginTransaction();
        try {
            $pdo->query('DELETE FROM `:prefix_admins_servers_groups` WHERE admin_id = :aid');
            $pdo->bind(':aid', $aid);
            $pdo->execute();

            if ($srvGroupId > 0) {
                $pdo->query(
                    'INSERT INTO `:prefix_admins_servers_groups` (admin_id, group_id, srv_group_id, server_id)'
                    . ' VALUES (:aid, :gid, :sgid, -1)'
                );
                $pdo->bind(':aid', $aid);
                $pdo->bind(':gid', $srvGroupId);
                $pdo->bind(':sgid', $srvGroupId);
                $pdo->execute();
            }

            foreach ($serverIds as $sid) {
                if ($sid <= 0) {
                    continue;
                }
                $pdo->query(
                    'INSERT INTO `:prefix_admins_servers_groups` (admin_id, group_id, srv_group_id, server_id)'
                    . ' VALUES (:aid_s, :gid_s, -1, :sid)'
                );
                $pdo->bind(':aid_s', $aid);
                $pdo->bind(':gid_s', $srvGroupId > 0 ? $srvGroupId : -1);
                $pdo->bind(':sid', $sid);
                $pdo->execute();
            }
            $pdo->endTransaction();
        } catch (\Throwable $e) {
            $pdo->cancelTransaction();
            throw $e;
        }
    }

    private function resolveWebGroup(?int $webGroupId): int
    {
        global $userbank;
        if ($webGroupId === null || $webGroupId <= 0) {
            return -1;
        }
        $pdo = $this->db();
        $pdo->query('SELECT gid, flags FROM `:prefix_groups` WHERE gid = :gid AND type = 1');
        $pdo->bind(':gid', $webGroupId);
        $row = $pdo->single();
        if (!is_array($row)) {
            throw new ApiError('validation', 'Unknown web group.', 'web_group_id', 400);
        }
        if (((int) $row['flags'] & ADMIN_OWNER) !== 0 && !$userbank->HasAccess(WebPermission::Owner)) {
            throw new ApiError('forbidden', 'No access', null, 403);
        }
        return (int) $row['gid'];
    }

    /**
     * @return array{id: int, name: string}
     */
    private function resolveServerGroup(?int $serverGroupId): array
    {
        if ($serverGroupId === null || $serverGroupId <= 0) {
            return ['id' => -1, 'name' => ''];
        }
        $pdo = $this->db();
        $pdo->query('SELECT id, name FROM `:prefix_srvgroups` WHERE id = :id');
        $pdo->bind(':id', $serverGroupId);
        $row = $pdo->single();
        if (!is_array($row)) {
            throw new ApiError('validation', 'Unknown server group.', 'server_group_id', 400);
        }
        return ['id' => (int) $row['id'], 'name' => (string) $row['name']];
    }

    /**
     * @param array<string, mixed> $body
     */
    private function optionalInt(array $body, string $key): ?int
    {
        if (!array_key_exists($key, $body) || $body[$key] === null || $body[$key] === '') {
            return null;
        }
        if (!is_numeric($body[$key])) {
            throw new ApiError('validation', 'Must be an integer.', $key, 400);
        }
        return (int) $body[$key];
    }

    /**
     * @param array<string, mixed> $body
     * @return list<int>|null
     */
    private function optionalIntList(array $body, string $key): ?array
    {
        if (!array_key_exists($key, $body)) {
            return null;
        }
        if ($body[$key] === null) {
            return [];
        }
        if (!is_array($body[$key])) {
            throw new ApiError('validation', 'Must be an array of integers.', $key, 400);
        }
        $out = [];
        foreach ($body[$key] as $v) {
            if (!is_numeric($v)) {
                throw new ApiError('validation', 'Must be an array of integers.', $key, 400);
            }
            $out[] = (int) $v;
        }
        return $out;
    }

    private function refuseSelf(int $aid, string $verb): void
    {
        /** @var UserManager $userbank */
        $userbank = $GLOBALS['userbank'];
        if ($aid === $userbank->GetAid()) {
            throw new ApiError('validation', 'You cannot ' . $verb . ' your own account.', 'id', 400);
        }
    }

    /**
     * @param array<string, mixed> $out
     * @return list<int>
     */
    private function rehashSidsFromHandler(array $out, int $aid): array
    {
        $fromHandler = $this->sidsFromCsv(isset($out['rehash']) && is_string($out['rehash']) ? $out['rehash'] : null);
        if ($fromHandler !== []) {
            return $fromHandler;
        }
        return function_exists('_api_admins_rehash_sids') ? _api_admins_rehash_sids($aid) : [];
    }

    /**
     * @return list<int>
     */
    private function sidsFromCsv(?string $csv): array
    {
        if ($csv === null || $csv === '') {
            return [];
        }
        $sids = [];
        foreach (explode(',', $csv) as $part) {
            $part = trim($part);
            if ($part !== '') {
                $sids[] = (int) $part;
            }
        }
        return $sids;
    }

    private function db(): Database
    {
        $pdo = $GLOBALS['PDO'] ?? null;
        if ($pdo instanceof Database) {
            return $pdo;
        }
        return new Database(DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS, DB_PREFIX, DB_CHARSET);
    }
}
