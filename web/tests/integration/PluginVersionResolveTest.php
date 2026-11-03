<?php

declare(strict_types=1);

namespace Sbpp\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Pins resolve-plugin-version.sh (native API / SB_VERSION generation for spcomp).
 */
final class PluginVersionResolveTest extends TestCase
{
    private static ?string $incBackup = null;

    protected function setUp(): void
    {
        if (self::$incBackup === null) {
            self::$incBackup = file_get_contents(self::incPath());
        }
    }

    protected function tearDown(): void
    {
        if (self::$incBackup !== null) {
            file_put_contents(self::incPath(), self::$incBackup);
        }
    }

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

    /**
     * @param array<string, string> $env
     * @param list<string>          $unset
     *
     * @return array{0: string, 1: int}
     */
    private function runResolver(array $env, array $unset = []): array
    {
        $envCmd = 'env';
        foreach ($unset as $key) {
            $envCmd .= ' -u ' . escapeshellarg($key);
        }
        foreach ($env as $key => $value) {
            $envCmd .= ' ' . $key . '=' . escapeshellarg($value);
        }

        $cmd = sprintf(
            'cd %s && %s bash %s 2>&1',
            escapeshellarg(self::repoRoot()),
            $envCmd,
            escapeshellarg(self::scriptPath()),
        );

        exec($cmd, $output, $code);

        return [implode("\n", $output), $code];
    }

    public function testReleaseVersionWritesTagAndApiEpoch(): void
    {
        $tmp = sys_get_temp_dir() . '/sbpp-version-test-' . bin2hex(random_bytes(4));
        mkdir($tmp, 0o755, true);

        [, $code] = $this->runResolver([
            'SBPP_RELEASE_VERSION' => '2.0.0',
            'SBPP_VERSION_JSON' => $tmp . '/missing-version.json',
        ]);

        self::assertSame(0, $code);

        $inc = file_get_contents(self::incPath());
        self::assertIsString($inc);
        self::assertStringContainsString('#define SB_VERSION                        "2.0.0"', $inc);
        self::assertStringContainsString('#define MAJOR_REVISION                    2', $inc);
        self::assertStringContainsString('#define MINOR_REVISION                    1', $inc);

        @rmdir($tmp);
    }

    public function testReleaseSemverMinorDoesNotBumpApiMinorRevision(): void
    {
        $missing = sys_get_temp_dir() . '/sbpp-no-json-' . bin2hex(random_bytes(4)) . '.json';

        [, $code] = $this->runResolver([
            'SBPP_RELEASE_VERSION' => '2.1.0',
            'SBPP_VERSION_JSON' => $missing,
        ]);

        self::assertSame(0, $code);

        $inc = file_get_contents(self::incPath());
        self::assertIsString($inc);
        self::assertStringContainsString('#define SB_VERSION                        "2.1.0"', $inc);
        self::assertStringContainsString('#define MAJOR_REVISION                    2', $inc);
        self::assertStringContainsString('#define MINOR_REVISION                    1', $inc);
        self::assertStringNotContainsString('#define MINOR_REVISION                    2', $inc);
    }

    public function testVersionJsonTierWhenPresent(): void
    {
        $jsonPath = sys_get_temp_dir() . '/sbpp-version-json-' . bin2hex(random_bytes(4)) . '.json';
        file_put_contents($jsonPath, json_encode(['version' => '2.1.3', 'git' => 'abc1234'], JSON_THROW_ON_ERROR));

        [, $code] = $this->runResolver(
            ['SBPP_VERSION_JSON' => $jsonPath],
            ['SBPP_RELEASE_VERSION'],
        );

        self::assertSame(0, $code);

        $inc = file_get_contents(self::incPath());
        self::assertIsString($inc);
        self::assertStringContainsString('#define SB_VERSION                        "2.1.3"', $inc);

        @unlink($jsonPath);
    }

    public function testVersionJsonTierWritesToIsolatedOutputPath(): void
    {
        $jsonPath = sys_get_temp_dir() . '/sbpp-version-json-' . bin2hex(random_bytes(4)) . '.json';
        $outPath = sys_get_temp_dir() . '/sbpp-version-inc-' . bin2hex(random_bytes(4)) . '.inc';
        file_put_contents($jsonPath, json_encode(['version' => '9.8.7', 'git' => 'deadbeef'], JSON_THROW_ON_ERROR));

        [, $code] = $this->runResolver(
            [
                'SBPP_VERSION_JSON' => $jsonPath,
                'SBPP_VERSION_INC_OUT' => $outPath,
            ],
            ['SBPP_RELEASE_VERSION'],
        );

        self::assertSame(0, $code);

        $inc = file_get_contents($outPath);
        self::assertIsString($inc);
        self::assertStringContainsString('#define SB_VERSION                        "9.8.7"', $inc);
        self::assertStringContainsString('#define MAJOR_REVISION                    2', $inc);
        self::assertStringContainsString('#define MINOR_REVISION                    1', $inc);
        self::assertSame(self::$incBackup, file_get_contents(self::incPath()));

        @unlink($jsonPath);
        @unlink($outPath);
    }

    /**
     * Writes a fake `git` onto a fresh directory so the git-describe tier
     * resolves deterministically, regardless of whether the checkout running
     * the suite sits on a release tag (#1613). Prepend the returned
     * directory to PATH.
     */
    private static function stubGitDir(string $describe, string $sha = 'abc1234'): string
    {
        $dir = sys_get_temp_dir() . '/sbpp-git-stub-' . bin2hex(random_bytes(4));
        mkdir($dir, 0o755, true);

        $script = "#!/bin/sh\n"
            . "for a in \"\$@\"; do\n"
            . "  case \"\$a\" in\n"
            . '    describe) printf \'%s\\n\' ' . escapeshellarg($describe) . "; exit 0 ;;\n"
            . '    rev-parse) printf \'%s\\n\' ' . escapeshellarg($sha) . "; exit 0 ;;\n"
            . "  esac\n"
            . "done\n"
            . "exit 1\n";

        file_put_contents($dir . '/git', $script);
        chmod($dir . '/git', 0o755);

        return $dir;
    }

    private static function removeStubGitDir(string $dir): void
    {
        @unlink($dir . '/git');
        @rmdir($dir);
    }

    public function testGitDescribeLeavesTripletAsDevWhenNotExactSemver(): void
    {
        $outPath = sys_get_temp_dir() . '/sbpp-version-inc-' . bin2hex(random_bytes(4)) . '.inc';
        $stub = self::stubGitDir('2.2.3-5-gabc1234');

        [, $code] = $this->runResolver(
            [
                'SBPP_VERSION_JSON' => self::repoRoot() . '/web/configs/version.json.nonexistent',
                'SBPP_VERSION_INC_OUT' => $outPath,
                'PATH' => $stub . PATH_SEPARATOR . (string) getenv('PATH'),
            ],
            ['SBPP_RELEASE_VERSION'],
        );

        self::removeStubGitDir($stub);

        self::assertSame(0, $code);

        $inc = file_get_contents($outPath);
        self::assertIsString($inc);
        self::assertStringContainsString('#define SB_VERSION                        "2.2.3-5-gabc1234"', $inc);
        self::assertStringContainsString('#define SB_VERSION_MAJOR                  "dev"', $inc);
        self::assertStringContainsString('#define SB_VERSION_MINOR                  "0"', $inc);
        self::assertStringContainsString('#define SB_VERSION_PATCH                  "0"', $inc);
        self::assertStringContainsString('#define MAJOR_REVISION                    2', $inc);

        @unlink($outPath);
    }

    public function testGitDescribeResolvesTripletWhenExactSemverTag(): void
    {
        $outPath = sys_get_temp_dir() . '/sbpp-version-inc-' . bin2hex(random_bytes(4)) . '.inc';
        $stub = self::stubGitDir('2.2.3');

        [, $code] = $this->runResolver(
            [
                'SBPP_VERSION_JSON' => self::repoRoot() . '/web/configs/version.json.nonexistent',
                'SBPP_VERSION_INC_OUT' => $outPath,
                'PATH' => $stub . PATH_SEPARATOR . (string) getenv('PATH'),
            ],
            ['SBPP_RELEASE_VERSION'],
        );

        self::removeStubGitDir($stub);

        self::assertSame(0, $code);

        $inc = file_get_contents($outPath);
        self::assertIsString($inc);
        self::assertStringContainsString('#define SB_VERSION                        "2.2.3"', $inc);
        self::assertStringContainsString('#define SB_VERSION_MAJOR                  "2"', $inc);
        self::assertStringContainsString('#define SB_VERSION_MINOR                  "2"', $inc);
        self::assertStringContainsString('#define SB_VERSION_PATCH                  "3"', $inc);
        self::assertStringContainsString('#define MAJOR_REVISION                    2', $inc);
        self::assertStringContainsString('(git abc1234)', $inc);

        @unlink($outPath);
    }

    public function testVersionJsonTierFailsWhenPhpMissing(): void
    {
        $tmpdir = sys_get_temp_dir() . '/sbpp-php-missing-' . bin2hex(random_bytes(4));
        $emptyBin = $tmpdir . '/bin';
        mkdir($emptyBin, 0o755, true);

        $jsonPath = $tmpdir . '/version.json';
        $outPath = $tmpdir . '/out.inc';
        file_put_contents($jsonPath, json_encode(['version' => '1.2.3', 'git' => 'abc'], JSON_THROW_ON_ERROR));

        $bash = is_executable('/bin/bash') ? '/bin/bash' : '/usr/bin/bash';

        $cmd = sprintf(
            'cd %s && env -u SBPP_RELEASE_VERSION SBPP_VERSION_JSON=%s SBPP_VERSION_INC_OUT=%s PATH=%s %s %s 2>&1',
            escapeshellarg(self::repoRoot()),
            escapeshellarg($jsonPath),
            escapeshellarg($outPath),
            escapeshellarg($emptyBin),
            escapeshellarg($bash),
            escapeshellarg(self::scriptPath()),
        );

        exec($cmd, $output, $code);

        self::assertNotSame(0, $code);
        self::assertFileDoesNotExist($outPath);
        self::assertSame(self::$incBackup, file_get_contents(self::incPath()));

        @unlink($jsonPath);
        @rmdir($emptyBin);
        @rmdir($tmpdir);
    }
}
