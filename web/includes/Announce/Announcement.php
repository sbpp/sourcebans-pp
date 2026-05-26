<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under the Elastic License 2.0.
// See LICENSE.txt for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

declare(strict_types=1);

namespace Sbpp\Announce;

/**
 * One announcement entry as the home dashboard renders it.
 *
 * Constructed by {@see AnnouncementFetcher::latest()} from the cached
 * upstream JSON feed. Body Markdown is pre-rendered through
 * {@see \Sbpp\Markup\IntroRenderer} so the template can drop
 * `body_html` straight in via `{nofilter}` without re-running the
 * renderer per page paint.
 *
 * Rendered fields are deliberately the bare minimum the dashboard
 * strip needs — anything richer belongs upstream in the JSON feed,
 * not on the disk cache or the wire format. Adding a field here
 * means revisiting:
 *   - {@see AnnouncementFetcher::buildAnnouncement()} (the constructor
 *     call that maps a raw cache entry onto this DTO).
 *   - The home page handler's {@see \Sbpp\Announce\Announcement}
 *     → array conversion in `web/pages/page.home.php`, since Smarty
 *     consumes the array shape (`{$announcement.title}` etc.).
 *   - The `Sbpp\View\HomeDashboardView` `?array $announcement`
 *     property's `@var` shape annotation.
 *   - The template (`page_dashboard.tpl`) — every new field needs an
 *     `{if $announcement.<field>}` arm or `SmartyTemplateRule` will
 *     flag the unused property.
 */
final class Announcement
{
    public function __construct(
        public readonly string $id,
        public readonly string $title,
        public readonly string $body_html,
        public readonly string $url,
        public readonly ?int $published_at,
        public readonly ?string $published_human,
    ) {
    }
}
