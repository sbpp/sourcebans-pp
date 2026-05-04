<?php
declare(strict_types=1);

namespace Sbpp\View;

/**
 * "Import bans" tab on the admin bans page — binds to
 * `page_admin_bans_import.tpl`.
 */
final class AdminBansImportView extends View
{
    public const TEMPLATE = 'page_admin_bans_import.tpl';

    public function __construct(
        public readonly bool $permission_import,
        public readonly bool $extreq,
    ) {
    }
}
