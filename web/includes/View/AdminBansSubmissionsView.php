<?php
declare(strict_types=1);

namespace Sbpp\View;

/**
 * Current submissions sub-tab of the admin bans page — binds to
 * `page_admin_bans_submissions.tpl`.
 */
final class AdminBansSubmissionsView extends View
{
    public const TEMPLATE = 'page_admin_bans_submissions.tpl';

    /**
     * @param list<array<string,mixed>> $submission_list
     */
    public function __construct(
        public readonly bool $permissions_submissions,
        public readonly bool $permissions_editsub,
        public readonly int $submission_count,
        public readonly string $submission_nav,
        public readonly array $submission_list,
    ) {
    }
}
