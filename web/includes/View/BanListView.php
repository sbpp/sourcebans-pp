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
     * @param list<array{sid: int, name: string}> $server_list    Enabled servers for the public filter bar's `<select name="server">` (#1226).
     * @param array{search: string, server: string, time: string} $filters Current filter state — drives the sticky filter bar's pre-fill + active selected `<option>` (#1226).
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
        // #1207 + #1226: detects whether the current request applied any
        // filter (search text / advSearch / hide-inactive / server /
        // time). Drives the first-run-vs-filtered split in the empty-
        // state shape.
        public readonly bool $is_filtered,
        // #1226: public filter parity with `CommsListView`. The
        // server list mirrors `CommsListView::$servers`; it's named
        // `server_list` here to match the existing
        // `box_admin_bans_search.tpl` convention so a future
        // consolidation between the inline filter bar and the
        // advanced-search box doesn't have to rename the property.
        public readonly array $server_list,
        public readonly array $filters,
        // #1315: drives the `<details class="filters-details">`
        // disclosure that wraps the advanced-search box at the top
        // of `page_bans.tpl`. True iff the request URL carries the
        // `?advSearch=&advType=` legacy-shim pair (the v1.x power-
        // user surface re-exposed as a default-collapsed disclosure
        // — see "Sub-paged advanced search" notes in the issue body).
        // Bare `?p=banlist` / simple-bar filters (`?searchText=` /
        // `?server=` / `?time=`) intentionally leave the disclosure
        // closed so the unfiltered list reaches above the fold —
        // those filters are visible on the inline sticky bar and
        // don't need the larger card open. Mirrors the post-submit
        // auto-open contract #1303 introduced for admin-admins.
        public readonly bool $is_advanced_search_open,
    ) {
    }
}
