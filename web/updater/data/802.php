<?php

// Issue #1102: split normal-auth gating from the Steam-login button toggle.
// `config.enablesteamlogin` was previously (incorrectly) gating both, so an
// admin who disabled Steam login also disabled normal-auth login. Insert the
// new row defaulted ON so panels upgrading from older versions continue to
// allow normal logins. INSERT IGNORE relies on the UNIQUE KEY on `setting`,
// so re-running this migration is a no-op.
//
// `$this` is supplied by Updater::update() which loads this file inside the
// Updater instance scope; PHPStan can't see that, so the next two calls are
// suppressed in the same way every sibling migration would be (the others
// live in phpstan-baseline.neon).
// @phpstan-ignore variable.undefined
$this->dbs->query(
    "INSERT IGNORE INTO `:prefix_settings` (`setting`, `value`) VALUES ('config.enablenormallogin', '1')"
);
// @phpstan-ignore variable.undefined
$this->dbs->execute();

return true;
