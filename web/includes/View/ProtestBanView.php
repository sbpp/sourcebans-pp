<?php
declare(strict_types=1);

namespace Sbpp\View;

/**
 * Public ban appeal ("protest a ban") form — binds to
 * `page_protestban.tpl`.
 *
 * The page is rendered for both the empty-form and post-submission
 * re-render cases. On a re-render after validation failure the
 * user-supplied values are seeded back into the form via these
 * properties so the user does not have to retype them.
 *
 * The ban-context "type" (Steam vs IP) is intentionally NOT a
 * separate property: the template derives the initially-visible row
 * from whether `$ip` is non-empty, which matches the only path that
 * can leave `$ip` populated after a failed submit (the user picked
 * "IP Address" as the ban type).
 */
final class ProtestBanView extends View
{
    public const TEMPLATE = 'page_protestban.tpl';

    public function __construct(
        public readonly string $steam_id,
        public readonly string $ip,
        public readonly string $player_name,
        public readonly string $reason,
        public readonly string $player_email,
    ) {
    }
}
