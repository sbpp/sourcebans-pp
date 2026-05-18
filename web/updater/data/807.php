<?php

// Issue #1126: anonymous opt-out telemetry. Fresh installs get the four
// `telemetry.*` settings from `web/install/includes/sql/data.sql`; this
// migration backfills them on upgrades so existing panels carry the same
// defaults — `telemetry.enabled = 1` (default-on, opt-out via Admin →
// Settings → Features → Telemetry; see the help-icon copy and the docs
// site's `Upgrading from 1.8.x to 2.0.x` page for the full disclosure
// surface), `telemetry.last_ping = 0`
// (next request runs the cooldown check and may reserve a slot),
// `telemetry.instance_id = ''` (Telemetry::resolveInstanceId() mints
// + persists on first ping), `telemetry.endpoint =
// https://cf-analytics-telemetry.sbpp.workers.dev/v1/ping` (operator
// can repoint to a private collector or `''` to disable network calls
// without flipping the user-facing toggle).
//
// INSERT IGNORE relies on the UNIQUE KEY on `setting`, so re-running this
// migration is a no-op. Defaults must stay in lockstep with data.sql.
//
// `$this` is supplied by Updater::update() which loads this file inside the
// Updater instance scope; PHPStan can't see that, so the next two calls are
// suppressed in the same way every sibling migration would be (the others
// live in phpstan-baseline.neon).
// @phpstan-ignore variable.undefined
$this->dbs->query(
    "INSERT IGNORE INTO `:prefix_settings` (`setting`, `value`) VALUES "
    . "('telemetry.enabled', '1'), "
    . "('telemetry.last_ping', '0'), "
    . "('telemetry.instance_id', ''), "
    . "('telemetry.endpoint', 'https://cf-analytics-telemetry.sbpp.workers.dev/v1/ping')"
);
// @phpstan-ignore variable.undefined
$this->dbs->execute();

return true;
