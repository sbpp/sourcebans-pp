<?php

declare(strict_types=1);

namespace Sbpp\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Pins resolve-plugin-version.sh (native API / SB_VERSION generation for spcomp).
 */
final class PluginVersionResolveTest extends TestCase
{
    private static function repoRoot(): string
    {
        return dirname(__DIR__, 3);
    }

    private static function scriptPath(): string
    {
        return self::repoRoot() . '/game/addons/sourcemod/scripting/scripts/resolve-plugin-version.sh';
    }

    private static function incPath(): string
    {
        return self::repoRoot() . '/game/addons/sourcemod/scripting/include/sbpp_version.inc';
    }

    public function testReleaseVersionWritesTagAndApiEpoch(): void
    {
        $tmp = sys_get_temp_dir() . '/sbpp-version-test-' . bin2hex(random_bytes(4));
        mkdir($tmp, 0o755, true);
        $out = $tmp . '/sbpp_version.inc';

        $env = array_merge($_ENV, [
            'SBPP_RELEASE_VERSION' => '2.0.0',
            'SBPP_VERSION_JSON' => $tmp . '/missing-version.json',
        ]);

        $cmd = sprintf(
            'cd %s && SBPP_RELEASE_VERSION=%s SBPP_VERSION_JSON=%s bash %s 2>&1',
            escapeshellarg(self::repoRoot()),
            escapeshellarg('2.0.0'),
            escapeshellarg($tmp . '/missing-version.json'),
            escapeshellarg(self::scriptPath()),
        );

        exec($cmd, $output, $code);
        self::assertSame(0, $code, implode("\n", $output));

        $inc = file_get_contents(self::incPath());
        self::assertIsString($inc);
        self::assertStringContainsString('#define SB_VERSION                        "2.0.0"', $inc);
        self::assertStringContainsString('#define MAJOR_REVISION                    2', $inc);
        self::assertStringContainsString('#define MINOR_REVISION                    0', $inc);

        @unlink($out);
        @rmdir($tmp);
    }

    public function testVersionJsonTierWhenPresent(): void
    {
        $jsonPath = sys_get_temp_dir() . '/sbpp-version-json-' . bin2hex(random_bytes(4)) . '.json';
        file_put_contents($jsonPath, json_encode(['version' => '2.1.3', 'git' => 'abc1234'], JSON_THROW_ON_ERROR));

        $cmd = sprintf(
            'cd %s && env -u SBPP_RELEASE_VERSION SBPP_VERSION_JSON=%s bash %s 2>&1',
            escapeshellarg(self::repoRoot()),
            escapeshellarg($jsonPath),
            escapeshellarg(self::scriptPath()),
        );
        exec($cmd, $output, $code);
        self::assertSame(0, $code, implode("\n", $output));

        $inc = file_get_contents(self::incPath());
        self::assertIsString($inc);
        self::assertStringContainsString('#define SB_VERSION                        "2.1.3"', $inc);

        @unlink($jsonPath);
    }
}
