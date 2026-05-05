<?php
declare(strict_types=1);

namespace Sbpp\View;

/**
 * "Add a block" tab on the admin comms page — binds to
 * `page_admin_comms_add.tpl`.
 *
 * SourceComms reuses the bans permission set: there is no separate
 * `ADMIN_ADD_COMM` flag, so `permission_addban` (precomputed via
 * {@see Perms::for()} as `can_add_ban`) gates the form. The naming
 * matches the legacy `default/page_admin_comms_add.tpl` so a single
 * View instance satisfies both themes during the v2.0.0 rollout window
 * (#1123): SmartyTemplateRule scans whichever theme is active and the
 * property name has to line up on both legs of the matrix.
 */
final class AdminCommsAddView extends View
{
    public const TEMPLATE = 'page_admin_comms_add.tpl';

    public function __construct(
        public readonly bool $permission_addban,
    ) {
    }
}
