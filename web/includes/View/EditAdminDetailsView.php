<?php
declare(strict_types=1);

namespace Sbpp\View;

/**
 * "Edit admin details" page — binds to
 * `page_admin_edit_admins_details.tpl`.
 *
 * The page handler (`admin.edit.admindetails.php`) gates entry on
 * `ADMIN_OWNER | ADMIN_EDIT_ADMINS` (or self-edit) before reaching the
 * template, so the View doesn't carry its own access boolean. `$change_pass`
 * is a per-request capability flag from the handler — true when the current
 * user is allowed to set the target admin's password (root or self).
 *
 * The property set is intentionally identical to the legacy handler's
 * `$theme->assign(...)` calls so the existing `$theme->display(...)` path
 * keeps rendering the redesigned template unchanged. The handler itself
 * (`admin.edit.admindetails.php`) is out of B11's scope — converting the
 * three per-tab edit handlers (details/group/servers) to `Renderer::render`
 * is queued for a follow-up; `SmartyTemplateRule` still pins the
 * View ↔ template parity for the redesign in the meantime.
 */
final class EditAdminDetailsView extends View
{
    public const TEMPLATE = 'page_admin_edit_admins_details.tpl';

    public function __construct(
        public readonly string $user,
        public readonly string $authid,
        public readonly string $email,
        public readonly bool $a_spass,
        public readonly bool $change_pass,
    ) {
    }
}
