<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under the Elastic License 2.0.
// See LICENSE.txt for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

declare(strict_types=1);

namespace Sbpp\Rest;

use Sbpp\Db\Database;

/**
 * Read-only group catalog for REST clients that map external roles to
 * SourceBans web / SourceMod groups.
 */
final class GroupsService
{
    /**
     * @return array{web: list<array{id: int, name: string, flags: int}>, server: list<array{id: int, name: string, flags: string, immunity: int}>}
     */
    public function list(): array
    {
        $pdo = $this->db();
        $webRows = $pdo->query(
            'SELECT gid, name, flags FROM `:prefix_groups` WHERE type = 1 ORDER BY name ASC'
        )->resultset();
        $web = [];
        foreach ($webRows as $row) {
            $web[] = [
                'id' => (int) $row['gid'],
                'name' => (string) $row['name'],
                'flags' => (int) $row['flags'],
            ];
        }

        $srvRows = $pdo->query(
            'SELECT id, name, flags, immunity FROM `:prefix_srvgroups` ORDER BY name ASC'
        )->resultset();
        $server = [];
        foreach ($srvRows as $row) {
            $server[] = [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'flags' => (string) $row['flags'],
                'immunity' => (int) $row['immunity'],
            ];
        }

        return ['web' => $web, 'server' => $server];
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
