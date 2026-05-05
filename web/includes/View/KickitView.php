<?php
declare(strict_types=1);

namespace Sbpp\View;

/**
 * Per-server "kick this player" iframe — binds to `page_kickit.tpl`.
 *
 * Renders inside an iframe spawned by `ShowKickBox()` in
 * `web/scripts/sourcebans.js` after a ban is added. It is a
 * self-contained `<html>` document (no chrome shell) and uses the
 * `-{ … }-` Smarty delimiter pair so the inline JS can keep its raw
 * `{` / `}` tokens.
 *
 * The property set is the union of what the **default** and
 * **sbpp2026** themes' `page_kickit.tpl` consume — both legs of the
 * dual-theme PHPStan matrix (#1123 A2) scan against the same View, so
 * mismatches between either template and this contract surface as
 * `sbpp.view.{missingProperty,unusedProperty}` errors before merge.
 *
 *   - `$csrf_token`: HTML `<meta name="csrf-token">` payload — sb.api
 *     reads it for the X-CSRF-Token header on every JSON call.
 *   - `$total`: row count, used by the iframe-internal counter that
 *     decides when to redirect the parent window back to the bans
 *     admin list.
 *   - `$check` / `$type`: pass-through query params forwarded into
 *     {@link api_kickit_kick_player()} (steam id / ip + integer
 *     discriminator).
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
     */
    public function __construct(
        public readonly string $csrf_token,
        public readonly int $total,
        public readonly string $check,
        public readonly int $type,
        public readonly array $servers,
    ) {
    }
}
