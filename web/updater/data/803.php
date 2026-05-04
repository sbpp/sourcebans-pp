<?php

// Issue #1109: configurable SMTP From Email + From Name. New installs get
// these via install/includes/sql/data.sql; this migration backfills them on
// upgrades so Admin → Settings → Email shows the seeded defaults instead of
// blank fields, keeping fresh and upgraded installs in lockstep.
//
// `from_email` is left blank so existing installs continue to fall back to
// the legacy `SB_EMAIL` constant in config.php (with the once-per-process
// deprecation warning emitted by Mailer::resolveFrom()). `from_name`
// defaults to the brand string used elsewhere in the panel.
//
// INSERT IGNORE relies on the UNIQUE KEY on `setting`, so re-running this
// migration is a no-op.
//
// `$this` is supplied by Updater::update() which loads this file inside the
// Updater instance scope; PHPStan can't see that, so the next two calls are
// suppressed in the same way every sibling migration would be (the others
// live in phpstan-baseline.neon).
// @phpstan-ignore variable.undefined
$this->dbs->query(
    "INSERT IGNORE INTO `:prefix_settings` (`setting`, `value`) VALUES "
    . "('config.mail.from_email', ''), ('config.mail.from_name', 'SourceBans++')"
);
// @phpstan-ignore variable.undefined
$this->dbs->execute();

return true;
