<?php
declare(strict_types=1);

namespace Sbpp\View;

/**
 * Per-server "kick this player" iframe — binds to `page_kickit.tpl`.
 *
 * Renders inside an iframe opened from the admin bans list after a ban
 * is added. It is a self-contained `<html>` document (no chrome shell)
 * and uses the `-{ … }-` Smarty delimiter pair so the inline JS can
 * keep its raw `{` / `}` tokens.
 *
 *   - `$csrf_token`: HTML `<meta name="csrf-token">` payload — sb.api
 *     reads it for the X-CSRF-Token header on every JSON call.
 *   - `$total`: row count, used by the iframe-internal counter that
 *     decides when to redirect the parent window back to the bans
 *     admin list.
 *   - `$check` / `$type`: pass-through query params forwarded into
 *     {@link api_kickit_kick_player()} (steam id / ip + integer
 *     discriminator).
 *   - `$mode`: `'ban'` (post-ban-kick flow embedded in the
 *     "Ban Added" iframe on `admin.bans.php`, default) or `'kick'`
 *     (standalone kick-only flow from the right-click context menu on
 *     `?p=servers`). Drives both the rendered page heading copy AND
 *     the `mode` param forwarded to
 *     {@link api_kickit_kick_player()}. See `web/pages/admin.kickit.php`
 *     for the URL-param allowlist (#1439).
 *   - `$servers`: per-row markers for the polling JS; the
 *     {@link api_kickit_load_servers()} JSON action refreshes the
 *     rcon-availability flag at runtime.
 *
 * No `can_*` properties: the page handler dies early on missing
 * `ADMIN_OWNER | ADMIN_ADD_BAN`, so the template never gates anything
 * on permissions and {@see Perms::for()} would only declare unused
 * properties.
 */
final class KickitView extends View
{
    public const TEMPLATE = 'page_kickit.tpl';

    /** @var array{0: string, 1: string} */
    public const DELIMITERS = ['-{', '}-'];

    /**
     * @param list<array{num:int, ip:string, port:string|int}> $servers
     * @param 'ban'|'kick' $mode
     *
     * `$mode` carries an inline default of `'ban'` so call sites that
     * predate #1439 keep working without an audit (the post-ban iframe
     * embed in `admin.bans.php` is the canonical "no mode supplied"
     * caller). New callers should pass `'kick'` explicitly when they
     * mean the standalone kick flow.
     */
    public function __construct(
        public readonly string $csrf_token,
        public readonly int $total,
        public readonly string $check,
        public readonly int $type,
        public readonly array $servers,
        public readonly string $mode = 'ban',
    ) {
    }
}
