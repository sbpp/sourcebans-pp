<?php
declare(strict_types=1);

namespace Sbpp\View;

/**
 * Per-server "block this player" iframe — binds to `page_blockit.tpl`.
 *
 * Mirrors {@see KickitView} (same iframe contract, same `-{ … }-`
 * delimiter rationale) but for the comm-block flow spawned by
 * `ShowBlockBox()` in `web/scripts/sourcebans.js` after a comm block
 * is added. The {@link api_blockit_block_player()} JSON action takes
 * the same `check` / `sid` / `num` / `type` plus a `length` (block
 * duration) parameter, so this view carries one extra property
 * compared to {@see KickitView}.
 */
final class BlockitView extends View
{
    public const TEMPLATE = 'page_blockit.tpl';

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
        public readonly int $length,
        public readonly array $servers,
    ) {
    }
}
