<?php

declare(strict_types=1);

namespace Sbpp\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sbpp\Telemetry\Region;
use Sbpp\Telemetry\Telemetry;

/**
 * Pure-function coverage for the sbpp/cf-analytics#6 telemetry
 * slots: the `panel.region` timezone → continent table, and the
 * `scale.games` / `scale.ban_history_months` helpers. The DB-backed
 * side lives in `TelemetryCollectTest`.
 */
final class TelemetryRegionTest extends TestCase
{
    /**
     * @return iterable<string, array{string, ?string}>
     */
    public static function zones(): iterable
    {
        yield 'europe'               => ['Europe/Berlin', 'eu'];
        yield 'asia'                 => ['Asia/Tokyo', 'as'];
        yield 'africa'               => ['Africa/Lagos', 'af'];
        yield 'australia'            => ['Australia/Sydney', 'oc'];
        yield 'pacific'              => ['Pacific/Auckland', 'oc'];
        yield 'north america'        => ['America/New_York', 'na'];
        yield 'central america'      => ['America/Mexico_City', 'na'];
        yield 'south america'        => ['America/Sao_Paulo', 'sa'];
        yield 'argentina subzone'    => ['America/Argentina/Buenos_Aires', 'sa'];
        yield 'atlantic europe'      => ['Atlantic/Canary', 'eu'];
        yield 'atlantic africa'      => ['Atlantic/Cape_Verde', 'af'];
        yield 'atlantic unmapped'    => ['Atlantic/South_Georgia', null];
        yield 'indian africa'        => ['Indian/Mauritius', 'af'];
        yield 'indian asia'          => ['Indian/Maldives', 'as'];
        yield 'pacific south america' => ['Pacific/Galapagos', 'sa'];
        yield 'antarctica'           => ['Antarctica/McMurdo', null];
        yield 'etc'                  => ['Etc/GMT+5', null];
        yield 'utc'                  => ['UTC', null];
        yield 'numeric offset'       => ['-8', null];
        yield 'iso offset'           => ['+01:00', null];
        yield 'unknown zone'         => ['Europe/Atlantis', null];
        yield 'empty'                => ['', null];
    }

    #[DataProvider('zones')]
    public function testFromTimezone(string $zone, ?string $expected): void
    {
        $this->assertSame($expected, Region::fromTimezone($zone));
    }

    public function testResolvePrefersConfiguredZoneName(): void
    {
        $this->assertSame('as', Region::resolve('Asia/Seoul', 'Europe/Paris'));
    }

    public function testResolveFallsBackToPhpDefaultForLegacyOffsets(): void
    {
        // Pre-2.0 upgrades can leave a bare UTC offset in config.timezone.
        $this->assertSame('eu', Region::resolve('1', 'Europe/Paris'));
        $this->assertSame('eu', Region::resolve(null, 'Europe/Paris'));
        $this->assertNull(Region::resolve('', 'UTC'));
    }

    /**
     * @return iterable<string, array{list<string>, array<string, bool>}>
     */
    public static function modfolders(): iterable
    {
        $none = ['tf' => false, 'cstrike' => false, 'csgo' => false, 'garrysmod' => false, 'left4dead2' => false, 'other' => false];
        yield 'no servers'     => [[], $none];
        yield 'tracked only'   => [['tf', 'csgo'], array_merge($none, ['tf' => true, 'csgo' => true])];
        yield 'untracked only' => [['dod'], array_merge($none, ['other' => true])];
        yield 'case folded'    => [['GarrysMod'], array_merge($none, ['garrysmod' => true])];
        yield 'l4d1 is other'  => [['left4dead'], array_merge($none, ['other' => true])];
        yield 'literal other'  => [['other'], array_merge($none, ['other' => true])];
    }

    /**
     * @param list<string> $folders
     * @param array<string, bool> $expected
     */
    #[DataProvider('modfolders')]
    public function testGamesFromModfolders(array $folders, array $expected): void
    {
        $this->assertSame($expected, Telemetry::gamesFromModfolders($folders));
    }

    public function testMonthsBetweenCountsWholeCalendarMonths(): void
    {
        $at = static fn (string $date): int => (new \DateTimeImmutable($date, new \DateTimeZone('UTC')))->getTimestamp();

        $this->assertSame(0, Telemetry::monthsBetween($at('2026-01-31'), $at('2026-02-28')));
        $this->assertSame(1, Telemetry::monthsBetween($at('2026-01-15'), $at('2026-02-15')));
        $this->assertSame(74, Telemetry::monthsBetween($at('2020-08-01'), $at('2026-10-25')));
        $this->assertSame(0, Telemetry::monthsBetween($at('2026-10-25'), $at('2020-01-01')), 'future oldest ban clamps to 0');
    }
}
