<?php

declare(strict_types=1);

namespace Sbpp\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * The docker dev stack must never ping the live telemetry endpoint.
 *
 * `docker/db-init/00-render-schema.sh` seeds the dev DB from
 * `data.sql`, which ships `telemetry.enabled = 1` and the production
 * cf-analytics endpoint. Every web request in the dev container runs
 * `Telemetry::tickIfDue()` at shutdown, so local `./sbpp.sh up`, the
 * E2E workflow, and the docs-screenshot capture in CI would each send
 * a ping from a throwaway install. `docker/php/dev-prepend.php` (the
 * dev container's `auto_prepend_file`) defines `SBPP_SKIP_TELEMETRY`
 * for web SAPIs to stop that.
 *
 * Two contracts, both pure file / subprocess checks (no DB):
 *
 *   1. {@see testDevPrependDefinesSkipTelemetryForWebRequests} — the
 *      prepend defines the constant behind a non-CLI gate, and the
 *      dev Dockerfile still wires the prepend in.
 *   2. {@see testDevPrependLeavesCliAlone} — under the CLI the
 *      prepend must NOT define it, otherwise `./sbpp.sh test` (PHPUnit
 *      inside the container, which also loads the prepend) would
 *      short-circuit `TelemetryOptOutTest`'s direct `tickIfDue()` calls.
 */
final class DevPrependTelemetryTest extends TestCase
{
    private function prependPath(): string
    {
        return ROOT . '../docker/php/dev-prepend.php';
    }

    public function testDevPrependDefinesSkipTelemetryForWebRequests(): void
    {
        $prepend = $this->prependPath();
        $this->assertFileExists($prepend);
        $source = (string) file_get_contents($prepend);

        $this->assertMatchesRegularExpression(
            "/if\s*\(\s*PHP_SAPI\s*!==\s*'cli'\s*&&\s*!defined\('SBPP_SKIP_TELEMETRY'\)\s*\)\s*\{\s*"
            . "define\('SBPP_SKIP_TELEMETRY',\s*true\);/",
            $source,
            'docker/php/dev-prepend.php must define SBPP_SKIP_TELEMETRY for web requests so the '
            . 'dev / E2E / CI stack never pings the live telemetry endpoint.'
        );

        $dockerfile = (string) file_get_contents(ROOT . '../docker/Dockerfile');
        $this->assertStringContainsString(
            'auto_prepend_file=/usr/local/etc/php/dev-prepend.php',
            $dockerfile,
            'docker/Dockerfile must keep loading dev-prepend.php via auto_prepend_file.'
        );
    }

    public function testDevPrependLeavesCliAlone(): void
    {
        $code = 'require ' . var_export($this->prependPath(), true) . ';'
            . ' echo defined("SBPP_SKIP_TELEMETRY") ? "defined" : "undefined";';
        $output = shell_exec(escapeshellarg(PHP_BINARY) . ' -n -r ' . escapeshellarg($code));

        $this->assertSame(
            'undefined',
            $output,
            'dev-prepend.php must not define SBPP_SKIP_TELEMETRY under the CLI; '
            . 'TelemetryOptOutTest relies on tickIfDue() running inside the container.'
        );
    }
}
