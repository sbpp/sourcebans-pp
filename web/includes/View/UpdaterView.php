<?php
declare(strict_types=1);

namespace Sbpp\View;

/**
 * Updater wizard page — binds to `updater.tpl`.
 *
 * The updater is bootstrapped by `web/updater/index.php`, NOT by the
 * normal `index.php` -> `route()` -> `pages/*.php` lifecycle. It still
 * loads `web/init.php`, so the global `$theme` Smarty instance, the
 * `$theme_url` assignment, and the configured template directory are
 * all available — but `init.php` deliberately forces
 * `$theme_name = "default"` when `IS_UPDATE` is defined (see the
 * `defined("IS_UPDATE")` branch in `init.php`). That means the updater
 * always renders the **legacy** `themes/default/updater.tpl` until D1
 * deletes the legacy theme and renames `sbpp2026/` into `default/`,
 * at which point this redesigned template takes over with no other
 * code change.
 *
 * `SmartyTemplateRule` resolves the template against whichever
 * theme directory is active (set per-leg via `SBPP_PHPSTAN_THEME` —
 * see `phpstan.yml`'s matrix). Both legs of the matrix scan a
 * template named `updater.tpl`; they share the same one-element
 * variable contract (`$updates`) so the View doesn't need a
 * per-theme alternate.
 */
final class UpdaterView extends View
{
    public const TEMPLATE = 'updater.tpl';

    /**
     * @param list<string> $updates Pre-formatted message lines from
     *     {@see \Updater::getMessageStack()}. Each line may contain a
     *     small subset of HTML markup (only `<b>` for emphasis around
     *     int version numbers / static filenames built inside
     *     `Updater.php`); no user input flows into them.
     */
    public function __construct(
        public readonly array $updates,
    ) {
    }
}
