<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under the Elastic License 2.0.
// See LICENSE.txt for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

declare(strict_types=1);

namespace Sbpp\View;

/**
 * Edit form for a single mod — binds to `page_admin_edit_mod.tpl`.
 *
 * Variable contract:
 *   - `name`           — current mod name (htmlspecialchars'd before
 *                        UPDATE in `admin.edit.mod.php`; see #1113
 *                        audit).
 *   - `folder`         — current mod folder (same caveat).
 *   - `mod_icon`       — current icon filename (same caveat).
 *   - `steam_universe` — first digit of `STEAM_X:Y:Z`.
 *   - `enabled`        — current "Enabled" flag, server-rendered as
 *                        the checkbox's `checked` attribute. Pre-#5
 *                        the page handler emitted an inline
 *                        `$('enabled').checked = …` script after the
 *                        template; the legacy shim is gone now.
 */
final class AdminEditModView extends View
{
    public const TEMPLATE = 'page_admin_edit_mod.tpl';

    public function __construct(
        public readonly string $name,
        public readonly string $folder,
        public readonly string $mod_icon,
        public readonly int|string $steam_universe,
        public readonly bool $enabled,
    ) {
    }
}
