<?php
declare(strict_types=1);

namespace Sbpp\View;

/**
 * Public "Submit a ban / Report a player" form — binds to
 * `page_submitban.tpl`. Reached via `index.php?p=submit` and gated
 * server-side by `Config::getBool('config.enablesubmit')` in
 * {@see web/pages/page.submit.php}, so reaching the view at all means
 * submissions are enabled.
 *
 * Variable contract is the union of fields the legacy
 * `web/themes/default/page_submitban.tpl` references and the new
 * `web/themes/sbpp2026/page_submitban.tpl` references; both render the
 * same form, just with different chrome. Keeping the names identical
 * lets `SmartyTemplateRule`'s dual-theme matrix (#1123 A2) check both
 * templates against this single View without divergence.
 *
 * Form-input `name=` POST keys (`SteamID`, `BanIP`, `PlayerName`,
 * `BanReason`, `SubmitName`, `EmailAddr`, `server`, `demo_file`) are
 * locked by the page handler's `$_POST['…']` reads and by the
 * `:prefix_submissions` schema; only the visual layout changes.
 */
final class SubmitBanView extends View
{
    public const TEMPLATE = 'page_submitban.tpl';

    /**
     * @param list<array<string,mixed>> $server_list Each row has at
     *     least `sid` (int) and `hostname` (string), populated from
     *     `:prefix_servers` plus a SourceQuery hostname lookup in
     *     `web/pages/page.submit.php`. The legacy template iterates as
     *     `{$server.sid}` / `{$server.hostname}`.
     * @param int $server_selected `sid` of the server the submitter
     *     picked on the previous failed POST (or `-1` on first load),
     *     used to render the matching `<option selected>` after a
     *     validation bounce.
     */
    public function __construct(
        public readonly string $STEAMID,
        public readonly string $ban_ip,
        public readonly string $player_name,
        public readonly string $ban_reason,
        public readonly string $subplayer_name,
        public readonly string $player_email,
        public readonly array $server_list,
        public readonly int $server_selected,
    ) {
    }
}
