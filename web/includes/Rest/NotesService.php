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
 * REST notes resource. Scoped per Steam ID. Writes reuse `notes.add` /
 * `notes.delete`. List is a dedicated query so Steam64 in `?steam=` works.
 */
final class NotesService
{
    /**
     * @param array<string, mixed> $query
     * @return array{data: list<array<string, mixed>>, meta: array{total: int}}
     */
    public function list(array $query): array
    {
        $steam = $this->canonicalSteam((string) ($query['steam'] ?? $query['steam_id'] ?? ''));
        $pdo = $this->db();
        $pdo->query(
            'SELECT N.nid, N.steam_id, N.body, N.created, N.aid,'
            . ' (SELECT user FROM `:prefix_admins` WHERE aid = N.aid) AS author'
            . ' FROM `:prefix_notes` AS N'
            . ' WHERE N.steam_id = :steam'
            . ' ORDER BY N.created DESC, N.nid DESC'
        );
        $pdo->bind(':steam', $steam);
        $rows = $pdo->resultset();
        $data = [];
        foreach ($rows as $row) {
            $data[] = $this->toResource($row);
        }
        return ['data' => $data, 'meta' => ['total' => count($data)]];
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function create(array $body): array
    {
        $steam = $this->canonicalSteam((string) ($body['steam'] ?? $body['steam_id'] ?? ''));
        $out = Api::invoke('notes.add', [
            'steam_id' => $steam,
            'body' => (string) ($body['body'] ?? ''),
        ]);
        $nid = (int) ($out['nid'] ?? 0);
        if ($nid <= 0) {
            throw new ApiError('server_error', 'Note was not created.', null, 500);
        }
        return $this->get($nid);
    }

    /**
     * @return array<string, mixed>
     */
    public function get(int $nid): array
    {
        $pdo = $this->db();
        $pdo->query(
            'SELECT N.nid, N.steam_id, N.body, N.created, N.aid,'
            . ' (SELECT user FROM `:prefix_admins` WHERE aid = N.aid) AS author'
            . ' FROM `:prefix_notes` AS N WHERE N.nid = :nid'
        );
        $pdo->bind(':nid', $nid);
        $row = $pdo->single();
        if (!is_array($row)) {
            throw new ApiError('not_found', 'Note not found.', null, 404);
        }
        return $this->toResource($row);
    }

    /**
     * @return array<string, mixed>
     */
    public function delete(int $nid): array
    {
        Api::invoke('notes.delete', ['nid' => $nid]);
        return ['id' => $nid];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function toResource(array $row): array
    {
        $steam2 = (string) $row['steam_id'];
        $steam64 = null;
        if ($steam2 !== '' && SteamID::isValidID($steam2)) {
            $steam64 = SteamID::toSteam64($steam2);
        }
        return [
            'id' => (int) $row['nid'],
            'steam' => $steam2,
            'steam64' => $steam64,
            'body' => (string) $row['body'],
            'created' => (int) $row['created'],
            'author' => $row['author'] !== null ? (string) $row['author'] : null,
            'author_aid' => (int) $row['aid'],
        ];
    }

    private function canonicalSteam(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            throw new ApiError('validation', 'steam is required.', 'steam', 400);
        }
        if (!preg_match(SteamID::HANDLER_STRICT_REGEX, $raw)) {
            throw new ApiError('validation', 'Please enter a valid Steam ID or Community ID', 'steam', 400);
        }
        return SteamID::toSteam2($raw);
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
