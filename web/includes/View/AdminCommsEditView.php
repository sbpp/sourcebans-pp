<?php
declare(strict_types=1);

namespace Sbpp\View;

/**
 * Edit-existing-block form — binds to `page_admin_edit_comms.tpl`.
 *
 * Only the player name and authid are surfaced through Smarty; the
 * current type / length / reason are still hydrated from inline
 * `<script>selectLengthTypeReason(…)</script>` the page handler emits
 * after `Renderer::render`. The template uses the default Smarty
 * `{ … }` delimiters, so this View inherits {@see View::DELIMITERS}
 * unchanged.
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
