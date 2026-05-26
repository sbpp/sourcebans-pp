<?php
declare(strict_types=1);

// Step 1 of the install wizard — license agreement.
//
// Pure render: emit the license text + the "I accept" form. The form
// POSTs to ?step=2; PHP's `required` on the checkbox is the gate
// (with a defensive page-tail JS fallback in the .tpl).
//
// `$theme` is the local-scope alias to the install Smarty instance
// brought in by web/install/index.php. Routing.php require()s this
// file inside that scope, so $theme is in scope here without needing
// $GLOBALS['installTheme'].

use Sbpp\View\Install\InstallLicenseView;
use Sbpp\View\Renderer;

require_once PANEL_INCLUDES_PATH . '/View/Install/InstallLicenseView.php';
require_once PANEL_INCLUDES_PATH . '/View/Renderer.php';

// Short-form license summary. The full legal text lives at
// LICENSE.txt in the repo root; a textarea-with-12-pages-of-text
// reads as legalese-noise that nobody reads, so we surface a
// human-readable summary plus the canonical reference link.
//
// Issue #1335 m2: pre-fix this step used British "Licence"
// throughout (page title, step title, step label, body copy, the
// checkbox label). Everywhere else in the repo uses American
// "License" (README, file paths (`page_license.tpl`), test IDs
// (`install-license-*`), the project's own `LICENSE.txt`). Standardise
// here too.
//
// v2.0.0 flipped the panel license from CC-BY-NC-SA 3.0 to the
// Elastic License 2.0, a software-purpose, source-available
// license. `THIRD-PARTY-NOTICES.txt` carries the upstream-lineage
// attributions (SourceBans 1.4.x, SourceComms, InterWave Studios
// theme.conf, LightOpenID, TinyMCE).
$licenseText = <<<'TEXT'
This installation of SourceBans++ is governed by the project's
license:

  - The web panel is distributed under the Elastic License 2.0
    (ELv2). You may use, copy, modify, create derivative works of,
    and redistribute the panel for hobby use, community use,
    bundling it into a Docker image, packaging it for a distro,
    or publishing a Pterodactyl egg. All of that stays free.

    What ELv2 reserves is the right to provide the panel as a
    hosted or managed service to third parties. If your business
    model involves offering SourceBans++ to your customers as a
    hosted, managed product, a separate commercial license is
    required. Self-hosting for your own community, clan, or
    network is not a "managed service to third parties" and is
    free under ELv2.

  - The bundled SourceMod plugins are distributed under the GNU
    General Public License version 3 (GPL-3.0).

  - This project descends from SourceBans 1.4.11 (SourceBans Team
    / GameConnect, 2007-2014). The v2.0 panel has been
    substantively rewritten; see THIRD-PARTY-NOTICES.txt for the
    upstream-lineage attributions.

By installing SourceBans++ you agree to comply with the terms of
the licenses above. Full text:

  - LICENSE.txt           (Elastic License 2.0, shipped at the root of this install)
  - LICENSE-plugins.txt   (GPLv3, shipped at the root of this install)
  - https://www.elastic.co/licensing/elastic-license
  - https://www.gnu.org/licenses/gpl-3.0.html

Tick the "I accept" box below to continue.
TEXT;

// @phpstan-ignore variable.undefined
Renderer::render($theme, new InstallLicenseView(
    page_title:  'License',
    step:        1,
    step_title:  'License agreement',
    step_count:  5,
    step_label:  'License',
    license_text: $licenseText,
));
