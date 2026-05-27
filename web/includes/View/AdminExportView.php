<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under the Elastic License 2.0.
// See LICENSE.txt for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

declare(strict_types=1);

namespace Sbpp\View;

/**
 * "Full data export" admin surface — binds to `page_admin_export.tpl`.
 *
 * Rendered by `web/pages/admin.export.php` against `?p=admin&c=export`.
 * The form template offers two delivery modes (ZIP-download +
 * S3-presigned-PUT) that POST to `web/export.php` — see that file's
 * lifecycle docblock for the wire contract.
 *
 * The pre-flight count + size totals come from
 * {@see \Sbpp\Export\ManifestBuilder}'s `build()`. The `$exceeds_cap`
 * flag is **s3-mode-specific** — it carries the S3 single-PUT
 * object-size cap (5 GiB minus the safety margin) which is the
 * hard structural limit on a presigned PUT across every S3-API
 * compatible provider. Direct ZIP download is **uncapped** under
 * Zip64, so the template gates ONLY the S3 submit on `$exceeds_cap`;
 * the ZIP submit stays enabled regardless, and the `.empty-state`
 * block tells the operator to use the ZIP form OR prune data when
 * the S3 form is disabled.
 *
 * `$row_counts` is the full per-entity table the manifest carries; the
 * template renders it as a definition list so the operator gets the
 * same breakdown the bundle's `manifest.json` will pin (transparency
 * about what's actually leaving the panel).
 *
 * The form template uses the `{csrf_field}` Smarty function (which
 * renders the hidden CSRF input + token from `\Sbpp\Security\CSRF`'s
 * canonical slot), so the View does NOT carry CSRF properties — it
 * would just duplicate the smarty-function call site. Future refactors
 * that need to expose the token to JS (the JSON-API client already
 * picks it up automatically via `theme.js`) should add the property
 * AND consume it in the template so SmartyTemplateRule stays happy.
 *
 * The View carries NO permission booleans / `$can_*` flags because
 * the entire admin route is gated on `\WebPermission::Owner` at three
 * sites: the page-builder (`web/includes/page-builder.php`'s
 * `$adminRoutes['export']`), the page handler's
 * `CheckAdminAccess(ADMIN_OWNER)` re-check, and the entry point itself
 * (`web/export.php`). Anything that hits this View has already passed
 * those gates.
 */
final class AdminExportView extends View
{
    public const TEMPLATE = 'page_admin_export.tpl';

    /**
     * @param array<string, int> $row_counts             Entity name → row count, in deterministic name order.
     * @param bool               $exceeds_cap            True iff the estimated bundle would exceed the S3 PUT cap (5 GiB minus the safety margin). S3-specific — the template gates only the S3 submit on this flag. ZIP download stays available.
     * @param int                $cap_bytes              The S3 PUT cap (`MAX_S3_PUT_BYTES - SAFETY_MARGIN_BYTES`). Surfaced as the "Cap (S3 PUT)" row in the template's totals breakdown. Does not apply to ZIP direct download.
     */
    public function __construct(
        public readonly string $panel_version,
        public readonly array $row_counts,
        public readonly int $total_admins,
        public readonly int $total_bans,
        public readonly int $total_comms,
        public readonly int $total_demos,
        public readonly int $demo_total_bytes,
        public readonly int $estimated_bundle_bytes,
        public readonly bool $exceeds_cap,
        public readonly int $cap_bytes,
    ) {
    }
}
