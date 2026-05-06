<?php
declare(strict_types=1);

namespace Sbpp\View;

/**
 * Public ban list page — binds to `page_bans.tpl`.
 *
 * `$ban_list` items expose `bid|name|steam|state|length_human|
 * banned_human|sname|can_edit_ban|can_unban` plus avatar metadata; the
 * page chrome reads `$total_bans`, `$can_export`, `$hidetext`,
 * `$searchlink`, `$ban_nav`, the comment-edit scratch pad
 * (`$comment`, `$commenttype`, `$commenttext`, `$ctype`, `$cid`,
 * `$page`, `$canedit`, `$othercomments`), and the testid hooks reach
 * the per-row `state`.
 *
 * Each row also carries the legacy keys (`ban_id|player|class|
 * reban_link|edit_link|…`) so any third-party theme that forked the
 * pre-v2.0.0 default theme keeps rendering. SmartyTemplateRule does
 * not introspect array contents, so both shapes coexist on each
 * `$ban_list` item.
 *
 * `$general_unban`, `$can_delete`, `$groupban`, `$friendsban`,
 * `$hideadminname`, `$hideplayerips`, `$view_bans`, `$view_comments`,
 * `$admin_postkey` are preserved here (and referenced by an
 * `{if false}…{/if}` manifest at the EOF of the template) for the
 * same compatibility reason.
 */
final class BanListView extends View
{
    public const TEMPLATE = 'page_bans.tpl';

    /**
     * @param list<array<string,mixed>>           $ban_list
     * @param int|false                           $comment        Bid being commented on, or false when not in comment-edit mode.
     * @param int                                 $page           Active pagination page (or -1 when not paginated).
     * @param array<int, array<string,mixed>>|string $othercomments  Sibling comments shown beneath the editor; "None" string when the ban has no other comments.
     */
    public function __construct(
        public readonly array $ban_list,
        public readonly string $ban_nav,
        public readonly int $total_bans,
        public readonly bool $view_bans,
        public readonly bool $view_comments,
        public readonly int|false $comment,
        public readonly string $commenttype,
        public readonly string $commenttext,
        public readonly string $ctype,
        public readonly string $cid,
        public readonly int $page,
        public readonly bool $canedit,
        public readonly array|string $othercomments,
        public readonly string $searchlink,
        public readonly string $hidetext,
        public readonly bool $hideadminname,
        public readonly bool $hideplayerips,
        public readonly bool $groupban,
        public readonly bool $friendsban,
        public readonly bool $general_unban,
        public readonly bool $can_delete,
        public readonly bool $can_export,
        public readonly string $admin_postkey,
        // #1207: gates the first-run empty-state CTA in `page_bans.tpl`
        // (admins with `ADMIN_ADD_BAN` see "Add a ban", everyone else
        // sees the body copy without the link). Splatted from
        // `Perms::for($userbank)` in `web/pages/page.banlist.php`.
        public readonly bool $can_add_ban,
        // #1207: detects whether the current request applied any filter
        // (search text / advSearch / hide-inactive). Drives the
        // first-run-vs-filtered split in the empty-state shape.
        public readonly bool $is_filtered,
    ) {
    }
}
