<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under the Elastic License 2.0.
// See LICENSE.txt for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

/*
 * "Full data export" admin page handler. Renders the form chrome at
 * `?p=admin&c=export`; the actual streaming work lives at panel-root
 * in `web/export.php` (binary wire format → doesn't fit the JSON API
 * dispatcher; same pattern as `exportbans.php` / `getdemo.php`).
 *
 * Lifecycle:
 *   1. Defence-in-depth permission re-check via `CheckAdminAccess` —
 *      the page-builder route already gates on `ADMIN_OWNER`, but
 *      direct page-include shapes (a future refactor, a misrouted
 *      request) hit this gate too. Belt-and-braces.
 *   2. Pre-flight pass via `ManifestBuilder::build()`. The returned
 *      `exceeds_cap` flag is **s3-mode-specific** (the 5 GiB S3
 *      single-PUT object-size ceiling minus the safety margin);
 *      direct ZIP download is uncapped under Zip64. The template
 *      gates only the S3 submit on `$exceeds_cap` — the ZIP submit
 *      stays enabled regardless so the operator can always reach
 *      the uncapped path.
 *   3. Mount the `AdminTabs` back-link partial via `new AdminTabs([], …)`
 *      (the back-link-only shape — same as `admin.email.php` /
 *      `admin.rcon.php`).
 *   4. Surface any pending result toast (the entry point's redirect
 *      arms — `?result=success&bid=<bid>` / `?result=error&code=<code>`).
 *   5. Render the form via `Renderer::render(...)`.
 *
 * The `?result=…` toast lives at the page handler (NOT the entry
 * point) because the entry point is a one-shot streaming body and
 * can't both stream binary AND paint a toast. The 302-then-toast
 * shape lets the chrome's `flushPendingToasts` consumer (`theme.js`)
 * pick up the wire-format `<script class="sbpp-pending-toast">`
 * block at first paint.
 */

if (!defined('IN_SB')) {
    echo "You should not be here. Only follow links!";
    die();
}

global $theme, $userbank;

// 1. Defence-in-depth permission gate. page-builder.php already gated
//    `?p=admin&c=export` on ADMIN_OWNER; this is the page-include
//    contract that survives a future routing refactor.
CheckAdminAccess(ADMIN_OWNER);

// 2. Pre-flight pass. `exceeds_cap` carries the S3 PUT cap signal —
//    the template gates only the S3 submit on it; the ZIP submit
//    stays enabled regardless because direct ZIP download is
//    uncapped under Zip64.
$manifest = (new \Sbpp\Export\ManifestBuilder(
    dbs:          $GLOBALS['PDO'],
    demosDir:     SB_DEMOS,
    panelVersion: SB_VERSION,
))->build();

// 3. Mount the back-link header.
new AdminTabs([], $userbank, $theme);

// 4. Surface any pending result toast from the entry point's redirect.
//    Both arms emit a SINGLE Toast::emit call; `$redirect` is `null`
//    because we're already on the destination page (no second hop).
//    Persistent error toasts (`duration_ms: 0`) match the "destructive
//    operation FAILED" semantic per AGENTS.md "Server-side toast
//    emission" — the operator MUST acknowledge before moving on.
$result = (string) ($_GET['result'] ?? '');
if ($result === 'success') {
    $bid = (string) ($_GET['bid'] ?? '');
    \Sbpp\View\Toast::emit(
        'success',
        'Export delivered',
        $bid !== ''
            ? sprintf('Bundle %s uploaded to the presigned URL.', $bid)
            : 'Bundle uploaded to the presigned URL.',
    );
} elseif ($result === 'error') {
    $code = (string) ($_GET['code'] ?? '');
    \Sbpp\View\Toast::emit(
        'error',
        'Export failed',
        sbpp_admin_export_describe_error($code),
        null,
        0,
    );
}

// 5. Render the form. The admin-page-content / "1" wrappers match
//    the rest of the single-page admin routes (admin.email.php, etc.).
$totalDemos = count($manifest->demo_files);
echo '<div id="admin-page-content"><div id="1">';
\Sbpp\View\Renderer::render($theme, new \Sbpp\View\AdminExportView(
    panel_version:          $manifest->panel_version,
    row_counts:             $manifest->row_counts,
    total_admins:           $manifest->row_counts['admins'] ?? 0,
    total_bans:             $manifest->row_counts['bans'] ?? 0,
    total_comms:            $manifest->row_counts['comms'] ?? 0,
    total_demos:            $totalDemos,
    demo_total_bytes:       $manifest->demo_total_bytes,
    estimated_bundle_bytes: $manifest->estimated_bundle_bytes,
    exceeds_cap:            $manifest->exceeds_cap,
    cap_bytes:              $manifest->cap_bytes,
));
echo '</div></div>';

/**
 * Translate an `\Sbpp\Export\ExportError` code back into an
 * operator-readable body string for the redirect-result toast.
 *
 * Codes that have an obvious user remedy (cap exceeded, presign URL
 * malformed) get a pointed message; the rest fall back to a generic
 * "check the audit log" line so the operator knows where to look.
 * The audit log already carries the full diagnostic body — we don't
 * want to surface a 2 KiB cURL response truncation into a toast.
 */
function sbpp_admin_export_describe_error(string $code): string
{
    return match ($code) {
        \Sbpp\Export\ExportError::CAP_EXCEEDED =>
            'Bundle exceeds the 5 GiB S3 PUT limit. Use direct ZIP download (uncapped), or prune data and retry.',
        \Sbpp\Export\ExportError::PRESIGN_INVALID_SCHEME =>
            'Presigned URL must use HTTPS. Regenerate it and retry.',
        \Sbpp\Export\ExportError::PRESIGN_INVALID_URL =>
            'Presigned URL is malformed. Paste the full URL from your S3 client.',
        \Sbpp\Export\ExportError::DISK_WRITE_FAILED, \Sbpp\Export\ExportError::DISK_FULL =>
            'Could not stage the bundle. Check that cache/ is writable and has free space.',
        \Sbpp\Export\ExportError::S3_PUT_FAILED =>
            'S3 rejected the upload. See the audit log for the HTTP status and response.',
        'mode_invalid' =>
            'Unknown export mode. Use the form buttons.',
        default =>
            'Export failed. See the audit log.',
    };
}
