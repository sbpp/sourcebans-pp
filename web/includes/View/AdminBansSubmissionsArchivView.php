<?php
declare(strict_types=1);

namespace Sbpp\View;

/**
 * Archived submissions sub-tab of the admin bans page — binds to
 * `page_admin_bans_submissions_archiv.tpl`.
 */
final class AdminBansSubmissionsArchivView extends View
{
    public const TEMPLATE = 'page_admin_bans_submissions_archiv.tpl';

    /**
     * @param list<array<string,mixed>> $submission_list_archiv
     */
    public function __construct(
        public readonly bool $permissions_submissions,
        public readonly bool $permissions_editsub,
        public readonly int $submission_count_archiv,
        public readonly string $asubmission_nav,
        public readonly array $submission_list_archiv,
    ) {
    }
}
