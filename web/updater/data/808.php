<?php

// Issue #1307: v2.0.0 ships a complete chrome rewrite (#1123 / #1207 /
// #1259 / #1275) — new typed View DTOs (`Sbpp\View\*`), new admin
// sidebar partial (`core/admin_sidebar.tpl`), `core/admin_tabs.tpl`
// reduced to the back-link partial, every template signature changed,
// MooTools / `xajax` / `sb-callback.php` removed, `openTab()` JS
// deleted. A fork theme inherited from v1.x literally does not contain
// the templates v2.0 expects to render — best case the operator gets
// `Smarty: Unable to load template …` fatals, worst case Smarty falls
// through to a half-rendered page where every variable is undefined.
//
// The updater wizard itself runs against `default` (the `IS_UPDATE`
// override in `web/init.php` lines 217-219), but that scoping ends
// when the operator clicks "Return to panel" and the next request
// reads `:prefix_settings.config.theme` from disk again.
//
// Force `config.theme` back to the in-tree shipped theme so the panel
// actually loads after the upgrade. Operators who maintain a fork can
// re-select it from Admin → Settings → Themes once they've ported it
// to the v2.0 templating contract (per #1115's "Theme authors"
// guidance).
//
// Idempotent: the WHERE clause matches no rows on a re-run, and
// matches no rows on installs that were already on `default` to begin
// with. The default value here matches the seed in
// `web/install/includes/sql/data.sql` so fresh installs and upgraded
// installs converge.
//
// `$this` is supplied by Updater::update() which loads this file
// inside the Updater instance scope; PHPStan can't see that, so the
// next two calls are suppressed in the same way every sibling
// migration would be.
// @phpstan-ignore variable.undefined
$this->dbs->query(
    "UPDATE `:prefix_settings` SET `value` = 'default' "
    . "WHERE `setting` = 'config.theme' AND `value` <> 'default'"
);
// @phpstan-ignore variable.undefined
$this->dbs->execute();

return true;
