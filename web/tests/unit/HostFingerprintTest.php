<?php

declare(strict_types=1);

namespace Sbpp\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sbpp\Telemetry\HostFingerprint;
use Sbpp\Telemetry\Telemetry;

/**
 * Pure-function coverage for the telemetry host fingerprint
 * (sbpp/cf-analytics#3). The integration side (every key present,
 * every value the right type) lives in `TelemetryCollectTest`; this
 * pins the parsers whose output the analytics `host_kind` heuristic
 * thresholds against.
 */
final class HostFingerprintTest extends TestCase
{
    /**
     * @return iterable<string, array{string, ?int}>
     */
    public static function memoryLimits(): iterable
    {
        yield 'megabytes'          => ['256M', 256];
        yield 'lowercase suffix'   => ['128m', 128];
        yield 'gigabytes'          => ['1G', 1024];
        yield 'kilobytes'          => ['524288K', 512];
        yield 'raw bytes'          => ['134217728', 128];
        yield 'surrounding spaces' => [' 64M ', 64];
        yield 'unlimited'          => ['-1', null];
        yield 'empty'              => ['', null];
        yield 'garbage'            => ['lots', null];
    }

    #[DataProvider('memoryLimits')]
    public function testParseMemoryLimitMb(string $raw, ?int $expected): void
    {
        $this->assertSame($expected, HostFingerprint::parseMemoryLimitMb($raw));
    }

    public function testCountDisabledFunctionsIgnoresBlanksAndWhitespace(): void
    {
        $this->assertSame(0, HostFingerprint::countDisabledFunctions(''));
        $this->assertSame(3, HostFingerprint::countDisabledFunctions('exec, passthru ,shell_exec,'));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function docroots(): iterable
    {
        yield 'cpanel'                => ['/home/alice/public_html', true];
        yield 'cpanel subdir'         => ['/home/alice/public_html/sourcebans', true];
        yield 'cpanel multi-disk'     => ['/home3/alice/public_html', true];
        yield 'directadmin'           => ['/home/alice/domains/example.com/public_html', true];
        yield 'docker / vps'          => ['/var/www/html', false];
        yield 'public_html lookalike' => ['/home/alice/public_html_old', false];
        yield 'not under home'        => ['/srv/home/alice/public_html', false];
        yield 'empty'                 => ['', false];
    }

    #[DataProvider('docroots')]
    public function testIsUserHomeDocroot(string $docroot, bool $expected): void
    {
        $this->assertSame($expected, HostFingerprint::isUserHomeDocroot($docroot));
    }

    public function testCollectNeverLeaksRawDocroot(): void
    {
        $saved = $_SERVER['DOCUMENT_ROOT'] ?? null;
        $_SERVER['DOCUMENT_ROOT'] = '/home/canary-user-7f3a/public_html';
        try {
            $env = HostFingerprint::collect();
        } finally {
            if ($saved === null) {
                unset($_SERVER['DOCUMENT_ROOT']);
            } else {
                $_SERVER['DOCUMENT_ROOT'] = $saved;
            }
        }
        $this->assertTrue($env['docroot_user_home']);
        $this->assertStringNotContainsString('canary-user-7f3a', (string) json_encode($env));
    }

    public function testWithoutNullsDropsNullLeavesRecursively(): void
    {
        $this->assertSame(
            ['schema' => 1, 'env' => ['php' => '8.5', 'zts' => false, 'max_execution_time' => 0]],
            Telemetry::withoutNulls([
                'schema' => 1,
                'env'    => ['php' => '8.5', 'memory_limit_mb' => null, 'zts' => false, 'max_execution_time' => 0],
            ])
        );
    }
}
