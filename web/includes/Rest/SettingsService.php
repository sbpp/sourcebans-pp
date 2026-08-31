<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under the Elastic License 2.0.
// See LICENSE.txt for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

declare(strict_types=1);

namespace Sbpp\Rest;

use Sbpp\Api\ApiError;
use Sbpp\Config;
use Sbpp\Db\Database;
use Sbpp\Export\EntityExporter;
use Sbpp\Log;
use LogType;

/**
 * REST panel settings. Dedicated queries. Never returns or writes
 * `smtp.pass` or `telemetry.instance_id`.
 */
final class SettingsService
{
    /**
     * @return array<string, string>
     */
    public function get(): array
    {
        return $this->allVisible();
    }

    /**
     * @param array<string|int, mixed> $body
     * @return array<string, string>
     */
    public function patch(array $body): array
    {
        if ($body === []) {
            throw new ApiError('validation', 'Send at least one setting key.', null, 400);
        }

        $pdo = $this->db();
        $changed = [];
        foreach ($body as $key => $value) {
            if (!is_string($key) || $key === '') {
                throw new ApiError('validation', 'Setting keys must be strings.', null, 400);
            }
            if (in_array($key, EntityExporter::FORBIDDEN_SETTING_KEYS, true)) {
                throw new ApiError('validation', 'That setting cannot be read or written.', $key, 400);
            }
            $pdo->query('SELECT setting FROM `:prefix_settings` WHERE setting = :setting');
            $pdo->bind(':setting', $key);
            $row = $pdo->single();
            if (!is_array($row)) {
                throw new ApiError('validation', 'Unknown setting.', $key, 400);
            }
            $stored = $this->stringify($value, $key);
            $pdo->query('UPDATE `:prefix_settings` SET `value` = :value WHERE `setting` = :setting');
            $pdo->bind(':value', $stored);
            $pdo->bind(':setting', $key);
            $pdo->execute();
            $changed[] = $key;
        }

        Config::init($pdo);
        Log::add(
            LogType::Message,
            'Settings Updated',
            'REST updated: ' . implode(', ', $changed),
        );

        return $this->allVisible();
    }

    /**
     * @return array<string, string>
     */
    private function allVisible(): array
    {
        $forbidden = EntityExporter::FORBIDDEN_SETTING_KEYS;
        $placeholders = implode(',', array_fill(0, count($forbidden), '?'));
        $pdo = $this->db();
        $pdo->query(
            "SELECT `setting`, `value` FROM `:prefix_settings`"
            . " WHERE `setting` NOT IN ({$placeholders}) ORDER BY `setting`"
        );
        $i = 1;
        foreach ($forbidden as $key) {
            $pdo->bind($i++, $key);
        }
        $out = [];
        foreach ($pdo->resultset() as $row) {
            $name = (string) ($row['setting'] ?? '');
            if ($name === '') {
                continue;
            }
            $out[$name] = (string) ($row['value'] ?? '');
        }
        return $out;
    }

    private function stringify(mixed $value, string $key): string
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        if (is_string($value)) {
            return $value;
        }
        if ($value === null) {
            return '';
        }
        throw new ApiError('validation', 'Value must be a string, number, or boolean.', $key, 400);
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
