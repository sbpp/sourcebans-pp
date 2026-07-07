{*
    SourceBans++ 2026 — page_admin_export.tpl
    Bound to Sbpp\View\AdminExportView (validated by SmartyTemplateRule).

    "Full data export" admin surface. Two delivery modes:
      - ZIP download (stream-to-output, no intermediate disk) — uncapped
      - S3 presigned PUT (build-to-disk-then-PUT) — 5 GiB S3 single-PUT cap

    Both forms POST to web/export.php (top-level entry point — binary
    wire format → doesn't fit the JSON dispatcher; same shape as
    `exportbans.php` / `getdemo.php`). See that file's lifecycle
    docblock for the security contract.

    The mode is carried as a hidden `<input name="mode" value="…">`
    inside each form (NOT as a `?mode=…` query string on the action
    URL); the entry point reads `$_POST['mode']` and rejects anything
    other than `'zip'` / `'s3'`.

    `$exceeds_cap` carries the S3 single-PUT cap signal (5 GiB minus
    the 64 MiB safety margin — the hard structural limit on a
    presigned PUT across every S3-API-compatible provider). When
    true, the S3 submit is disabled and an `.empty-state` block
    points the operator at the uncapped ZIP path. The ZIP submit
    stays enabled regardless because direct ZIP download is uncapped
    under Zip64. The s3 arm re-enforces the cap at the entry point
    so a hand-edited form submission can't bypass it.
*}
<section class="p-6" data-testid="admin-export-section" style="max-width:56rem">
    <div class="mb-6">
        <h1 style="font-size:1.5rem;font-weight:600;margin:0">Full data export</h1>
        <p class="text-sm text-muted m-0 mt-2">
            Download a ZIP bundle of every panel row and uploaded demo.
            Use it for backups, migration, or analytics.
            See the <a href="https://sbpp.github.io/configuring/data-export/" target="_blank" rel="noopener noreferrer">data-export docs</a>
            for the wire format and per-mode setup.
        </p>
        <p class="text-xs text-muted m-0 mt-2" data-testid="admin-export-rookhelm">
            Moving to a hosted panel?
            <a href="https://rookhelm.com?utm_source=sourcebans-pp&amp;utm_medium=referral&amp;utm_content=panel-export"
               target="_blank"
               rel="noopener">RookHelm</a>
            imports this export directly.
        </p>
    </div>

    <div class="card mb-4" data-testid="admin-export-summary-card">
        <div class="card__header">
            <h2 style="font-size:1.125rem;font-weight:600;margin:0">What's in the bundle</h2>
            <p class="text-xs text-muted m-0 mt-1">
                Each export gets a fresh bundle ID. Counts below are a snapshot from page load.
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
                    <dt class="text-xs text-muted">Cap (S3 PUT)</dt>
                    <dd class="font-mono" data-testid="admin-export-cap-bytes" style="margin:0">{($cap_bytes / 1024 / 1024)|number_format:1} MiB</dd>
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
        {* First-run vs filtered shape per AGENTS.md "Empty states": the
           bundle is too big for S3's single-PUT cap, but the ZIP path
           is still available so the operator isn't blocked outright.
           Use the first-run shape with NO Clear-filters CTA — the
           ZIP form above IS the path forward, no extra CTA needed.
           `data-filtered="false"` per the convention. *}
        <div class="card" data-testid="admin-export-cap-empty" data-filtered="false">
            <div class="empty-state">
                <span class="empty-state__icon" aria-hidden="true">
                    <i data-lucide="alert-triangle" style="width:18px;height:18px"></i>
                </span>
                <h2 class="empty-state__title">Bundle exceeds the 5 GiB S3 PUT limit</h2>
                <p class="empty-state__body">
                    Estimated bundle is {($estimated_bundle_bytes / 1024 / 1024)|number_format:1} MiB.
                    Use the ZIP download form above (no cap),
                    or prune demos / rows and reload to enable the S3 form.
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
                    The browser downloads the bundle directly.
                    Keep the tab open until it finishes; closing it aborts the transfer.
                </p>
                <p class="text-xs text-muted m-0">
                    No staging file. The progress bar moves in real time.
                </p>
            </div>
            <div class="card__header" style="border-top:1px solid var(--border);border-bottom:0;justify-content:flex-end">
                <button type="submit"
                        class="btn btn--primary"
                        data-testid="admin-export-zip-submit">
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
                    Panel stages the bundle on disk, then PUTs it to your URL.
                    Closing the tab cancels the upload; the audit log records the attempt.
                </p>
                <label class="label mt-3" for="admin-export-s3-url">Presigned PUT URL</label>
                <textarea class="textarea"
                          id="admin-export-s3-url"
                          name="presign_url"
                          rows="3"
                          required
                          pattern="^https://[^\s]+$"
                          title="Paste a presigned HTTPS PUT URL from your S3 client (e.g. aws s3 presign --http-method PUT)"
                          placeholder="https://bucket.s3.region.amazonaws.com/path/sbpp-export.zip?X-Amz-..."
                          data-testid="admin-export-s3-url"></textarea>
                <p class="text-xs text-muted m-0 mt-1">
                    HTTPS only. Use a short expiry (1 hour or less).
                    The URL is a single-use write credential; don't paste it in chat or logs.
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
