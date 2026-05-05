<?php
declare(strict_types=1);

namespace Sbpp\View;

/**
 * Edit form for a single mod — binds to `page_admin_edit_mod.tpl`.
 *
 * `web/pages/admin.edit.mod.php` still drives this template via direct
 * `$theme->assign()` calls (the page handler is out of scope for this
 * Phase B ticket). The variable contract preserved here mirrors what
 * that page assigns:
 *
 *   - `name`           — current mod name (post-store htmlspecialchars'd
 *                        in admin.edit.mod.php; see #1113 audit).
 *   - `folder`         — current mod folder (same caveat).
 *   - `mod_icon`       — current icon filename (same caveat).
 *   - `steam_universe` — first digit of STEAM_X:Y:Z (PDO returns an
 *                        int-as-string by default; see Database.php).
 *
 * The `enabled` checkbox is intentionally NOT a template variable: the
 * legacy page handler emits an inline `<script>` after the template
 * that sets `$('enabled').checked` from the row's `enabled` column,
 * because the .tpl is rendered before the row's checkbox state is
 * known to Smarty. Preserving that path keeps admin.edit.mod.php out
 * of this PR's scope; D-series can fold it in once it migrates to
 * `Renderer::render`.
 */
final class AdminEditModView extends View
{
    public const TEMPLATE = 'page_admin_edit_mod.tpl';

    public function __construct(
        public readonly string $name,
        public readonly string $folder,
        public readonly string $mod_icon,
        public readonly int|string $steam_universe,
    ) {
    }
}
