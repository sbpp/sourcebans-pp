<?php
declare(strict_types=1);

namespace Sbpp\View\Install;

use Sbpp\View\View;

/**
 * Step 1 of the install wizard — license agreement.
 *
 * Pair: web/install/pages/page.1.php +
 * web/themes/default/install/page_license.tpl. Owns no behaviour;
 * it's a static "read this, tick the box, click continue" gate that
 * the v2.0.0 chrome rewrite (#1123) inherited from the v1.x wizard.
 *
 * The chrome props (`$page_title`, `$step`, `$step_title`,
 * `$step_count`, `$step_label`) propagate to every wizard step view
 * via the `{include file="install/_chrome.tpl"}` partial — see the
 * `_chrome.tpl` docblock for the rationale on the per-view duplication.
 *
 * `$license_text` is the plain-text dump of the project license — kept
 * server-side so the View has a single source of truth for what the
 * checkbox commits the operator to.
 */
final class InstallLicenseView extends View
{
    public const TEMPLATE = 'install/page_license.tpl';

    public function __construct(
        public readonly string $page_title,
        public readonly int $step,
        public readonly string $step_title,
        public readonly int $step_count,
        public readonly string $step_label,
        public readonly string $license_text,
    ) {
    }
}
