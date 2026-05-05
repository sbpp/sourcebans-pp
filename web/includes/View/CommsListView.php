<?php
declare(strict_types=1);

namespace Sbpp\View;

/**
 * Public communications-blocklist (mute/gag) page — binds to
 * `page_comms.tpl`. New in #1123 B4 alongside the sbpp2026 redesign of
 * the public comms list.
 *
 * Dual-theme bridge (Phase B). The legacy `themes/default/page_comms.tpl`
 * renders both a public block list AND an inline comment-edit drawer
 * (`{if $comment} … {else}{$ban_list} … {/if}`); the new
 * `themes/sbpp2026/page_comms.tpl` only shows the block list (the comment
 * editor will get its own redesign post-B4 — see the new template's
 * header docblock for the deferred-scope list). This view declares the
 * union of variables both templates consume so `SmartyTemplateRule`
 * passes against either theme:
 *
 *   - sbpp2026-only properties: $ban_list (with the slim handoff-style
 *     row keys), $filters, $servers, $pagination, $hide_inactive(_toggle_url),
 *     $can_{add,edit,unmute_gag,delete}_comm.
 *   - legacy-only properties: $ban_nav, $hidetext, $hideadminname,
 *     $view_bans, $view_comments, plus the comment-drawer set
 *     ($comment, $commenttype, $commenttext, $ctype, $cid, $page,
 *     $othercomments, $canedit). The default theme reads these
 *     directly; the sbpp2026 template ignores them. Each is captured in
 *     the `unusedProperty` block of `phpstan-baseline.neon` so the
 *     default-leg PHPStan run still fails on *new* drift while the
 *     sbpp2026-leg run (with `reportUnmatchedIgnoredErrors=false`) is
 *     unaffected. D1 deletes the legacy theme + drops the unused
 *     properties + the corresponding baseline entries in one shot.
 *
 * Per-row shape (each entry of `$ban_list`) — sbpp2026 keys layered on
 * top of every legacy key already present (mod_icon, banlength, ban_date,
 * view_*, …) so both themes render off the same row source:
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
 *                          for the row's avatar tile (sbpp2026 only)
 *   - edit_url      string admin edit URL
 *   - unmute_url    string|null URL to unmute/ungag, null if N/A
 *   - delete_url    string admin delete URL
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
     *     active chip highlighting (sbpp2026 only).
     * @param list<array{sid: int, name: string}> $servers Server list
     *     for the "All servers" dropdown filter (sbpp2026 only).
     * @param array{
     *     from: int,
     *     to: int,
     *     total: int,
     *     prev_url: ?string,
     *     next_url: ?string,
     * } $pagination Page navigation data (sbpp2026 only). `prev_url` /
     *     `next_url` are `null` at the page boundaries so the template
     *     can `disabled` them.
     * @param list<array<string,mixed>> $othercomments Sibling comments
     *     in the comment-edit drawer (legacy default theme only).
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
        // Legacy default-theme template variables. Kept on the View
        // (rather than left as raw $theme->assign() calls in the
        // handler) so SmartyTemplateRule can verify both themes end up
        // with every variable they reference. Each of these is
        // ignored by `themes/sbpp2026/page_comms.tpl` and disappears
        // at D1 cutover.
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
