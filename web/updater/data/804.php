<?php

// Issue #1113 / #521: dashboard intro text used to render straight DB HTML
// through `{$dashboard_text nofilter}`, which made every admin with
// ADMIN_SETTINGS a stored-XSS vector for every dashboard visitor. The fix
// (a) removes the WYSIWYG editor in favour of a plain textarea, and
// (b) pipes `dash.intro.text` through Sbpp\Markup\IntroRenderer
//     (CommonMark, html_input=escape, allow_unsafe_links=false) before it
//     hits Smarty.
//
// data.sql now seeds a Markdown-shaped default. This migration rewrites
// only the *legacy* default value on existing installs so admins who
// customised their intro text are not clobbered. Their custom HTML will
// render as escaped text (acceptable degradation for a security fix —
// they re-author it in Markdown next time they edit settings).
//
// Idempotent: if the row already holds the new default (or anything
// other than the legacy default — a customised intro, or this migration
// already ran), the UPDATE matches no rows and is a no-op.
//
// `$this` is supplied by Updater::update() which loads this file inside the
// Updater instance scope; PHPStan can't see that, so the next two calls are
// suppressed in the same way every sibling migration would be.
// @phpstan-ignore variable.undefined
$this->dbs->query(
    "UPDATE `:prefix_settings` SET `value` = :new_value "
    . "WHERE `setting` = 'dash.intro.text' AND `value` = :legacy_value"
);
// @phpstan-ignore variable.undefined
$this->dbs->bind(':new_value', "# Your new SourceBans install\n\nSourceBans++ successfully installed!");
// @phpstan-ignore variable.undefined
$this->dbs->bind(':legacy_value', '<center><p>Your new SourceBans install</p><p>SourceBans++ successfully installed!</center>');
// @phpstan-ignore variable.undefined
$this->dbs->execute();

return true;
