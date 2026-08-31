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
 * REST game-mod resource. List/get are dedicated queries. Writes reuse
 * `mods.add` / `mods.remove`.
 */
final class ModsService
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
        $countRow = $pdo->query('SELECT COUNT(*) AS c FROM `:prefix_mods`')->single();
        $total = is_array($countRow) ? (int) ($countRow['c'] ?? 0) : 0;

        $pdo->query(
            'SELECT mid, name, icon, modfolder, steam_universe, enabled'
            . ' FROM `:prefix_mods` ORDER BY mid ASC LIMIT :lim OFFSET :off'
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
    public function get(int $mid): array
    {
        $pdo = $this->db();
        $pdo->query(
            'SELECT mid, name, icon, modfolder, steam_universe, enabled FROM `:prefix_mods` WHERE mid = :mid'
        );
        $pdo->bind(':mid', $mid);
        $row = $pdo->single();
        if (!is_array($row)) {
            throw new ApiError('not_found', 'Mod not found.', null, 404);
        }
        return $this->toResource($row);
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function create(array $body): array
    {
        $folder = (string) ($body['folder'] ?? $body['modfolder'] ?? '');
        $name = (string) ($body['name'] ?? '');
        Api::invoke('mods.add', [
            'name' => $name,
            'folder' => $folder,
            'icon' => (string) ($body['icon'] ?? ''),
            'steam_universe' => (int) ($body['steam_universe'] ?? 0),
            'enabled' => $body['enabled'] ?? true,
        ]);
        $pdo = $this->db();
        $pdo->query('SELECT mid FROM `:prefix_mods` WHERE modfolder = :folder OR name = :name ORDER BY mid DESC');
        $pdo->bind(':folder', $folder);
        $pdo->bind(':name', $name);
        $row = $pdo->single();
        if (!is_array($row)) {
            throw new ApiError('server_error', 'Mod was not created.', null, 500);
        }
        return $this->get((int) $row['mid']);
    }

    /**
     * @return array<string, mixed>
     */
    public function delete(int $mid, string $ureason): array
    {
        $this->get($mid);
        Api::invoke('mods.remove', ['mid' => $mid, 'ureason' => $ureason]);
        return ['id' => $mid];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function toResource(array $row): array
    {
        return [
            'id' => (int) $row['mid'],
            'name' => (string) $row['name'],
            'folder' => (string) $row['modfolder'],
            'icon' => (string) $row['icon'],
            'steam_universe' => (int) $row['steam_universe'],
            'enabled' => (int) $row['enabled'] === 1,
        ];
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
