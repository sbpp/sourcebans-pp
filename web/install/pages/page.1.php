<?php
declare(strict_types=1);

// Step 1 of the install wizard — license agreement.
//
// Pure render: emit the licence text + the "I accept" form. The form
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

// Short-form licence summary. The full legal text lives at
// LICENSE.md in the repo root — a textarea-with-12-pages-of-text
// reads as legalese-noise that nobody reads, so we surface a
// human-readable summary + the canonical reference link.
$licenseText = <<<'TEXT'
This installation of SourceBans++ is governed by the project's
licence:

  - The web panel is distributed under the Creative Commons
    Attribution-NonCommercial-ShareAlike 3.0 Unported license
    (CC BY-NC-SA 3.0).

  - The bundled SourceMod plugins are distributed under the GNU
    General Public License version 3 (GPL-3.0).

  - This project is based on work covered by the original
    SourceBans 1.4.11 copyright held by the SourceBans Team /
    GameConnect (CC BY-NC-SA 3.0).

By installing SourceBans++ you agree to comply with the terms of
the licences above. Full text:

  - https://creativecommons.org/licenses/by-nc-sa/3.0/
  - https://www.gnu.org/licenses/gpl-3.0.html
  - LICENSE.md (shipped at the root of this install)

Tick the "I accept" box below to continue.
TEXT;

// @phpstan-ignore variable.undefined
Renderer::render($theme, new InstallLicenseView(
    page_title:  'Licence',
    step:        1,
    step_title:  'Licence agreement',
    step_count:  5,
    step_label:  'Licence',
    license_text: $licenseText,
));
