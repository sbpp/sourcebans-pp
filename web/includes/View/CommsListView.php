<?php
declare(strict_types=1);

namespace Sbpp\View;

/**
 * Public communications-blocklist (mute/gag) page — binds to
 * `page_comms.tpl`. The template renders the public block list; the
 * inline comment-edit drawer is a deferred-scope follow-up.
 *
 * Per-row shape (each entry of `$ban_list`) — the redesign's slim
 * keys layered on top of every legacy key already present
 * (mod_icon, banlength, ban_date, view_*, …) so any third-party theme
 * that forked the pre-v2.0.0 default keeps rendering off the same
 * row source:
 *   - cid           int    block id (legacy `bid`)
 *   - name          string player nickname (already cleaned + slashed)
 *   - steam         string SteamID2
 *   - type          string 'mute' | 'gag' | 'silence' | 'unknown'
 *   - length_human  string e.g. "Permanent" | "1 day" (already
 *                          SecondsToString-formatted by the handler)
 *   - started_human string Config::time-formatted display
 *   - started_iso   string ISO-8601 timestamp for <time datetime>
 *   - state         string 'active' | 'expired' | 'unmuted' | 'permanent'
 *   - sname         string server hostname / "Web Block" label
 *   - admin         string admin nickname (or false if hidden / deleted)
 *   - avatar_hue    int    0–359 HSL hue derived from `crc32(name . steam)`
 *                          for the row's avatar tile
 *   - edit_url      string admin edit URL
 *   - unmute_url    string|null URL to unmute/ungag, null if N/A
 *   - delete_url    string admin delete URL
 *
 * `$ban_nav`, `$hidetext`, `$hideadminname`, `$view_bans`,
 * `$view_comments` and the comment-drawer set (`$comment`,
 * `$commenttype`, `$commenttext`, `$ctype`, `$cid`, `$page`,
 * `$othercomments`, `$canedit`) are preserved on this View for the
 * same compatibility reason.
 */
final class CommsListView extends View
{
    public const TEMPLATE = 'page_comms.tpl';

    /**
     * @param list<array<string,mixed>> $ban_list Comm rows. See class
     *     docblock for the per-row shape.
     * @param array{
     *     search: string,
     *     server: string,
     *     time: string,
     *     state: string,
     *     type: string,
     * } $filters Current filter state — drives the sticky filter bar +
     *     active chip highlighting.
     * @param list<array{sid: int, name: string}> $servers Server list
     *     for the "All servers" dropdown filter.
     * @param array{
     *     from: int,
     *     to: int,
     *     total: int,
     *     prev_url: ?string,
     *     next_url: ?string,
     * } $pagination Page navigation data. `prev_url` / `next_url`
     *     are `null` at the page boundaries so the template can
     *     `disabled` them.
     * @param list<array<string,mixed>> $othercomments Sibling comments
     *     in the comment-edit drawer (legacy theme contract).
     */
    public function __construct(
        public readonly int $total_bans,
        public readonly string $searchlink,
        public readonly array $ban_list,
        public readonly array $filters,
        public readonly array $servers,
        public readonly array $pagination,
        public readonly bool $hide_inactive,
        public readonly string $hide_inactive_toggle_url,
        public readonly bool $can_add_comm,
        public readonly bool $can_edit_comm,
        public readonly bool $can_unmute_gag,
        public readonly bool $can_delete_comm,
        // #1207: detects whether the current request applied any filter
        // (search text / server / time / state / type / hide-inactive).
        // Drives the first-run-vs-filtered split in the empty-state
        // shape — when zero rows AND no filter, the empty state shows
        // "no comm blocks recorded yet" with an "Add a comm block" CTA;
        // with a filter, it stays "No comm blocks match those filters"
        // + "Clear filters".
        public readonly bool $is_filtered,
        // Legacy template variables — preserved on the View (rather
        // than left as raw $theme->assign() calls in the handler) so
        // SmartyTemplateRule can verify any third-party theme that
        // forked the pre-v2.0.0 default keeps its variable references
        // typed.
        public readonly bool|int|string $comment,
        public readonly string $commenttype,
        public readonly bool $canedit,
        public readonly string $commenttext,
        public readonly string $ctype,
        public readonly int|string $cid,
        public readonly int $page,
        public readonly array $othercomments,
        public readonly string $ban_nav,
        public readonly string $hidetext,
        public readonly bool $hideadminname,
        public readonly bool $view_comments,
        public readonly bool $view_bans,
    ) {
    }
}
