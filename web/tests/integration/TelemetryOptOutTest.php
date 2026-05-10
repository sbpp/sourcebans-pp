<?php

declare(strict_types=1);

namespace Sbpp\Tests\Integration;

use Sbpp\Config;
use Sbpp\Telemetry\Telemetry;
use Sbpp\Tests\ApiTestCase;
use Sbpp\Tests\Fixture;

/**
 * #1126 — opt-out short-circuits the tick before any DB read past
 * the cached `Config::get('telemetry.enabled')` lookup.
 *
 * The acceptance criterion ("With `telemetry.enabled=0`, page-load
 * wall time is unchanged within 1ms median against a no-op
 * baseline") is best gated by a structural assertion on the
 * `telemetry.last_ping` row, since the production path runs cURL
 * in a `register_shutdown_function` callback and we don't want
 * tests dialling external IPs anyway.
 *
 * The shape of the assertion: with the toggle off,
 * `Telemetry::tickIfDue()` must NOT touch `last_ping`. The fixture
 * starts every test at `last_ping = 0`, so a non-zero value after
 * `tickIfDue()` returns is proof the gate let the slot
 * reservation through.
 */
final class TelemetryOptOutTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Telemetry::resetInstanceIdMemoForTests();
    }

    public function testTickIfDueShortCircuitsWhenDisabled(): void
    {
        $this->setTelemetryEnabled(false);

        Telemetry::tickIfDue();

        $this->assertSame(
            '0',
            $this->fetchSetting('telemetry.last_ping'),
            'With telemetry disabled, tickIfDue must not reserve a slot.'
        );
        $this->assertSame(
            '',
            $this->fetchSetting('telemetry.instance_id'),
            'With telemetry disabled, tickIfDue must not mint an instance ID.'
        );
    }

    /**
     * Counterpart: with telemetry enabled and the endpoint pointed
     * at an unroutable address (so the cURL POST fails silently),
     * the slot reservation still runs and `last_ping` flips off `0`.
     * This pins the "reserved at start, not after success" rule
     * from the issue body — a flapping endpoint costs one ping/day,
     * not one ping/request.
     */
    public function testTickIfDueReservesSlotEvenWhenEndpointUnreachable(): void
    {
        $this->setTelemetryEnabled(true);
        $this->setSetting('telemetry.endpoint', 'http://127.0.0.1:1');

        Telemetry::tickIfDue();

        $reserved = (int) $this->fetchSetting('telemetry.last_ping');
        $this->assertGreaterThan(
            0,
            $reserved,
            'Slot reservation must succeed even when the network call fails.'
        );
    }

    /**
     * Inside the cooldown window, a second `tickIfDue` is a no-op:
     * `last_ping` is already recent so the threshold check returns
     * before the atomic UPDATE runs. We pre-stamp the slot to "now"
     * and assert the value doesn't move.
     */
    public function testTickIfDueIsNoopInsideCooldown(): void
    {
        $this->setTelemetryEnabled(true);
        $now = time();
        $this->setSetting('telemetry.last_ping', (string) $now);
        // Endpoint deliberately empty so even if the gate broke,
        // no socket would be touched — but the `last_ping` value
        // is still the load-bearing assertion.
        $this->setSetting('telemetry.endpoint', '');

        Telemetry::tickIfDue();

        $this->assertSame(
            (string) $now,
            $this->fetchSetting('telemetry.last_ping'),
            'A within-cooldown tick must leave last_ping untouched.'
        );
    }

    private function setTelemetryEnabled(bool $on): void
    {
        $this->setSetting('telemetry.enabled', $on ? '1' : '0');
    }

    private function setSetting(string $key, string $value): void
    {
        $pdo  = Fixture::rawPdo();
        $stmt = $pdo->prepare(sprintf(
            'UPDATE `%s_settings` SET `value` = ? WHERE `setting` = ?',
            DB_PREFIX
        ));
        $stmt->execute([$value, $key]);
        // Re-prime the in-process Config cache so Telemetry sees the new value.
        Config::init($GLOBALS['PDO']);
    }

    private function fetchSetting(string $key): string
    {
        $pdo  = Fixture::rawPdo();
        $stmt = $pdo->prepare(sprintf(
            'SELECT `value` FROM `%s_settings` WHERE `setting` = ?',
            DB_PREFIX
        ));
        $stmt->execute([$key]);
        return (string) $stmt->fetchColumn();
    }
}
