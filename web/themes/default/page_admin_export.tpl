{*
    SourceBans++ 2026 — page_admin_export.tpl
    Bound to Sbpp\View\AdminExportView (validated by SmartyTemplateRule).

    "Full data export" admin surface. Two delivery modes:
      - ZIP download (stream-to-output, no intermediate disk)
      - S3 presigned PUT (build-to-disk-then-PUT)

    Both forms POST to web/export.php (top-level entry point — binary
    wire format → doesn't fit the JSON dispatcher; same shape as
    `exportbans.php` / `getdemo.php`). See that file's lifecycle
    docblock for the security contract.

    The mode is carried as a hidden `<input name="mode" value="…">`
    inside each form (NOT as a `?mode=…` query string on the action
    URL); the entry point reads `$_POST['mode']` and rejects anything
    other than `'zip'` / `'s3'`.

    When `$exceeds_cap` is true (the bundle would blow the 4 GiB ZIP
    2.0 spec ceiling minus our 64 MiB safety margin), both submit
    buttons are disabled and the page shows an `.empty-state` block
    explaining what to prune. The operator MUST clear the cap before
    they can act — the same gate is re-enforced at the entry point so
    a hand-edited form submission can't bypass it.
*}
<section class="p-6" data-testid="admin-export-section" style="max-width:56rem">
    <div class="mb-6">
        <h1 style="font-size:1.5rem;font-weight:600;margin:0">Full data export</h1>
        <p class="text-sm text-muted m-0 mt-2">
            Stream a one-shot ZIP bundle of every panel row + uploaded demo
            for backup, migration, or downstream analytics.
            See the <a href="https://sbpp.github.io/configuring/data-export/" target="_blank" rel="noopener noreferrer">data-export docs</a>
            for the on-disk wire format and a worked example for each delivery mode.
        </p>
    </div>

    <div class="card mb-4" data-testid="admin-export-summary-card">
        <div class="card__header">
            <h2 style="font-size:1.125rem;font-weight:600;margin:0">What's in the bundle</h2>
            <p class="text-xs text-muted m-0 mt-1">
                A fresh UUIDv4 bundle ID is minted at submit time; the totals below
                reflect the snapshot taken when this page loaded.
            </p>
        </div>
        <div class="card__body">
            <dl class="install-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(14rem,1fr));gap:0.75rem 1.5rem;margin:0">
                <div>
                    <dt class="text-xs text-muted">Panel version</dt>
                    <dd class="font-mono" data-testid="admin-export-panel-version" style="margin:0">{$panel_version|escape}</dd>
                </div>
                <div>
                    <dt class="text-xs text-muted">Admins</dt>
                    <dd class="font-mono" data-testid="admin-export-count-admins" style="margin:0">{$total_admins|number_format}</dd>
                </div>
                <div>
                    <dt class="text-xs text-muted">Bans</dt>
                    <dd class="font-mono" data-testid="admin-export-count-bans" style="margin:0">{$total_bans|number_format}</dd>
                </div>
                <div>
                    <dt class="text-xs text-muted">Comm blocks</dt>
                    <dd class="font-mono" data-testid="admin-export-count-comms" style="margin:0">{$total_comms|number_format}</dd>
                </div>
                <div>
                    <dt class="text-xs text-muted">Demos</dt>
                    <dd class="font-mono" data-testid="admin-export-count-demos" style="margin:0">{$total_demos|number_format}</dd>
                </div>
                <div>
                    <dt class="text-xs text-muted">Demos on disk</dt>
                    <dd class="font-mono" data-testid="admin-export-demo-bytes" style="margin:0">{($demo_total_bytes / 1024 / 1024)|number_format:1} MiB</dd>
                </div>
                <div>
                    <dt class="text-xs text-muted">Estimated bundle</dt>
                    <dd class="font-mono" data-testid="admin-export-estimate-bytes" style="margin:0">{($estimated_bundle_bytes / 1024 / 1024)|number_format:1} MiB</dd>
                </div>
                <div>
                    <dt class="text-xs text-muted">Cap (ZIP 2.0)</dt>
                    <dd class="font-mono" data-testid="admin-export-cap-bytes" style="margin:0">{($cap_bytes / 1024 / 1024 / 1024)|number_format:2} GiB</dd>
                </div>
            </dl>

            {if $row_counts|@count > 0}
                <details class="mt-4">
                    <summary class="text-sm" style="cursor:pointer">Per-entity row counts</summary>
                    <dl class="install-grid mt-2" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(12rem,1fr));gap:0.25rem 1.5rem;margin:0;font-size:var(--fs-xs)">
                        {foreach from=$row_counts key=entity item=count}
                            <div style="display:flex;justify-content:space-between;gap:0.5rem">
                                <dt class="text-muted font-mono">{$entity|escape}</dt>
                                <dd class="font-mono" style="margin:0">{$count|number_format}</dd>
                            </div>
                        {/foreach}
                    </dl>
                </details>
            {/if}
        </div>
    </div>

    {if $exceeds_cap}
        {* First-run vs filtered shape per AGENTS.md "Empty states": this is
           a structural block (no rows can be exported until the operator
           shrinks the bundle), so we use the first-run shape with NO
           Clear-filters CTA — the operator's only path forward is to
           prune their data, which isn't a one-click action we can
           surface. `data-filtered="false"` per the convention. *}
        <div class="card" data-testid="admin-export-cap-empty" data-filtered="false">
            <div class="empty-state">
                <span class="empty-state__icon" aria-hidden="true">
                    <i data-lucide="alert-triangle" style="width:18px;height:18px"></i>
                </span>
                <h2 class="empty-state__title">Bundle exceeds the 4 GiB ZIP cap</h2>
                <p class="empty-state__body">
                    The estimated bundle ({($estimated_bundle_bytes / 1024 / 1024)|number_format:1} MiB)
                    overflows the ZIP 2.0 spec ceiling minus the 64 MiB safety margin.
                    Prune old demos via the per-row Demo affordance on the <a href="?p=banlist">public banlist</a>
                    or trim unrelated rows (banlog, audit log) and re-load this page.
                    The submit buttons below are disabled until the bundle fits.
                </p>
            </div>
        </div>
    {/if}

    <div class="grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(22rem,1fr));gap:1rem;margin-top:1rem">
        <form class="card"
              method="post"
              action="export.php"
              data-testid="admin-export-zip-form">
            {csrf_field}
            <input type="hidden" name="mode" value="zip">
            <div class="card__header">
                <h2 style="font-size:1.125rem;font-weight:600;margin:0">Stream a ZIP download</h2>
            </div>
            <div class="card__body space-y-2">
                <p class="text-sm text-muted m-0">
                    The browser downloads the bundle directly. The connection
                    must stay open for the full transfer — close the tab and the
                    download aborts (cleanly; no on-disk state to clean up).
                </p>
                <p class="text-xs text-muted m-0">
                    No staging file is written; the bundle streams from
                    <code>php://output</code> through the writer's
                    <code>flush()</code> calls so the browser's progress bar
                    moves in real time.
                </p>
            </div>
            <div class="card__header" style="border-top:1px solid var(--border);border-bottom:0;justify-content:flex-end">
                <button type="submit"
                        class="btn btn--primary"
                        data-testid="admin-export-zip-submit"
                        {if $exceeds_cap}disabled{/if}>
                    <i data-lucide="download" style="width:16px;height:16px"></i>
                    Export as ZIP
                </button>
            </div>
        </form>

        <form class="card"
              method="post"
              action="export.php"
              data-testid="admin-export-s3-form">
            {csrf_field}
            <input type="hidden" name="mode" value="s3">
            <div class="card__header">
                <h2 style="font-size:1.125rem;font-weight:600;margin:0">Upload to S3 presigned URL</h2>
            </div>
            <div class="card__body space-y-2">
                <p class="text-sm text-muted m-0">
                    Bundle is staged on disk, then PUT to your presigned URL.
                    The HTTPS connection holds for the full upload; close the
                    tab mid-upload and the request aborts but the audit log
                    captures the partial transfer.
                </p>
                <label class="label mt-3" for="admin-export-s3-url">Presigned PUT URL</label>
                <textarea class="textarea"
                          id="admin-export-s3-url"
                          name="presign_url"
                          rows="3"
                          required
                          pattern="^https://[^\s]+$"
                          title="Paste a presigned HTTPS PUT URL from your S3 client (e.g. aws s3 presign --http-method PUT …)"
                          placeholder="https://bucket.s3.region.amazonaws.com/path/sbpp-export.zip?X-Amz-…"
                          data-testid="admin-export-s3-url"></textarea>
                <p class="text-xs text-muted m-0 mt-1">
                    HTTPS only (no http://). Use a short expiry (≤1 hour).
                    Never paste this URL into chat or logs — it's a single-use
                    write credential.
                </p>
            </div>
            <div class="card__header" style="border-top:1px solid var(--border);border-bottom:0;justify-content:flex-end">
                <button type="submit"
                        class="btn btn--primary"
                        data-testid="admin-export-s3-submit"
                        {if $exceeds_cap}disabled{/if}>
                    <i data-lucide="upload-cloud" style="width:16px;height:16px"></i>
                    Upload to S3
                </button>
            </div>
        </form>
    </div>
</section>

{* Page-tail script: gates the submit buttons through window.SBPP.setBusy
   so the browser doesn't appear frozen while the request travels. The
   ZIP path streams a binary response body (no page-render reset, no
   redirect); the S3 path 302-redirects back here on completion. Both
   tear the page down so we deliberately DON'T `setBusy(submitBtn, false)`
   in the submit handler — the next paint resets the DOM either way. The
   local `setBusy` wrapper falls back to plain `disabled` so third-party
   themes that strip theme.js still gate against double-clicks. *}
{literal}
<script>
(function () {
    'use strict';
    function setBusy(btn, busy) {
        if (!btn) return;
        var S = window.SBPP;
        if (S && typeof S.setBusy === 'function') {
            S.setBusy(btn, busy);
        } else {
            btn.disabled = busy === undefined ? true : !!busy;
        }
    }
    var forms = document.querySelectorAll('[data-testid="admin-export-zip-form"], [data-testid="admin-export-s3-form"]');
    for (var i = 0; i < forms.length; i++) {
        forms[i].addEventListener('submit', function (e) {
            var submit = e.target.querySelector('[type="submit"]');
            if (submit && submit.disabled) {
                e.preventDefault();
                return;
            }
            setBusy(submit, true);
        });
    }
})();
</script>
{/literal}
