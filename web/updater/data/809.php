<?php

// Issue #1235 follow-up: convert the v1.x `template.logo` default
// (`logos/sb-large.png`) to the new v2.0 default (`images/favicon.svg`).
// The v1 default points at `web/themes/<theme>/logos/sb-large.png`, a
// path the v2.0 default theme doesn't ship — the setting was vestigial
// in v2.0 (no template consumed `$logo`) until this PR wired it back
// into the sidebar / login / updater brand mark. Fresh installs get
// the new default via `web/install/includes/sql/data.sql`; this
// migration backfills upgrades.
//
// The WHERE clause pins the value to the v1.x default so admins who
// already customised `template.logo` in v1.x are NOT touched — their
// pointer survives the upgrade. Re-running the migration on an
// already-converged install is a no-op (the WHERE matches zero rows).
//
// `$this` is supplied by Updater::update() which loads this file inside
// the Updater instance scope; PHPStan can't see that, so the next two
// calls are suppressed in the same way every sibling migration would be
// (the others live in phpstan-baseline.neon).
// @phpstan-ignore variable.undefined
$this->dbs->query(
    "UPDATE `:prefix_settings` SET `value` = 'images/favicon.svg' "
    . "WHERE `setting` = 'template.logo' AND `value` = 'logos/sb-large.png'"
);
// @phpstan-ignore variable.undefined
$this->dbs->execute();

return true;
