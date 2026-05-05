<?php
declare(strict_types=1);

namespace Sbpp\View;

/**
 * Edit-existing-block form — binds to `page_admin_edit_comms.tpl`.
 *
 * The variable contract matches the legacy
 * `default/page_admin_edit_comms.tpl`: only the player name and
 * authid are surfaced through Smarty; the current type / length /
 * reason are still hydrated from inline `<script>selectLengthTypeReason(…)</script>`
 * the page handler emits after `Renderer::render` (legacy convention,
 * preserved during the v2.0.0 rollout window so the same View
 * satisfies SmartyTemplateRule on both theme legs of the CI matrix).
 *
 * The legacy template uses Smarty's custom `-{ … }-` delimiters; the
 * sbpp2026 redesign moves to the standard `{ … }` pair so the View
 * binds with the default {@see View::DELIMITERS}. The page handler
 * picks the right delimiter set per active theme — see
 * `web/pages/admin.edit.comms.php`.
 */
final class AdminCommsEditView extends View
{
    public const TEMPLATE = 'page_admin_edit_comms.tpl';

    public function __construct(
        public readonly string $ban_name,
        public readonly string $ban_authid,
    ) {
    }
}
