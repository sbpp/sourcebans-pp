{*
 SourceBans++ 2026 — page_uploadfile.tpl
 Bound view: \Sbpp\View\UploadFileView (web/includes/View/UploadFileView.php).

 Self-contained popup window opened via window.open(...) from one
 of the parent admin pages (admin.uploadicon.php,
 admin.uploadmapimg.php, admin.uploaddemo.php). On a successful
 upload the page handler builds a {$message} blob containing
 `<script>window.opener.<cb>(<json-encoded args>);self.close()</script>`
 using JSON_HEX_* flags so the admin-controlled filename is safe
 to interpolate into both the HTML attribute and the JS string
 layers (#1113 fix). The template is the only Phase B/C template
 that legitimately needs `{$message nofilter}`.

 The theme stylesheet path is hardcoded relative to the popup's
 URL (/pages/admin.upload*.php), so `../themes/default/css/theme.css`
 resolves correctly.

 Anti-FOUC bootloader (#1438): this template renders a separate
 popup window opened with `window.open(...)` from a parent admin
 page (which is dark-mode-aware via `core/header.tpl`). The popup
 is same-origin, so `localStorage['sbpp-theme']` is reachable
 from here — but the popup ships its own chromeless `<head>` and
 doesn't load `theme.js`, so without the bootloader below the
 popup paints stark white over the operator's dark-mode parent.
 The inline script mirrors `core/header.tpl`'s bootloader exactly
 (same THEME_KEY 'sbpp-theme', same default 'system', same dark-
 resolution predicate). Note the body explicitly uses
 `background:var(--bg-page)`, so the resolved tokens drive the
 paint directly; without `html.dark` the page would resolve to
 the `:root` light tokens regardless of operator preference. See
 "Anti-FOUC theme bootloader" in AGENTS.md Conventions for the
 full contract; regression gate is
 `web/tests/integration/IframeChromeAntiFoucBootloaderTest.php`.
*}
<!DOCTYPE html>
<html lang="en">
<head>
 <meta charset="utf-8">
 <meta name="viewport" content="width=device-width,initial-scale=1">
 <title>{$title} : SourceBans++</title>
 <script>
 (function () {
 try {
 var m = localStorage.getItem('sbpp-theme') || 'system';
 document.documentElement.setAttribute('data-theme-pref', m);
 var d = m === 'dark' || (m === 'system' && window.matchMedia
 && matchMedia('(prefers-color-scheme: dark)').matches);
 if (d) document.documentElement.classList.add('dark');
 } catch (e) { /* localStorage / matchMedia unavailable; default to light */ }
 })();
 </script>
 <link rel="stylesheet" href="../themes/default/css/theme.css">
</head>
<body style="padding:1rem;background:var(--bg-page)">
<div class="card" style="max-width:24rem;margin:0 auto">
    <div class="card__header">
        <div>
            <h3>{$title}</h3>
            <p>Pick a file to upload. The file must be {$formats} file format.</p>
        </div>
    </div>
    <div class="card__body">
        {if $message}
            <div class="text-xs"
                 data-testid="uploadfile-message"
                 style="margin-bottom:0.75rem;color:var(--text)">
                {* nofilter: $message is server-built — either an empty string, a hand-built `<b>… file must be …</b>` rejection literal, or a popup-callback `<script>window.opener.<cb>(<json-encoded args>);self.close()</script>` blob whose admin-controlled filename was JSON_HEX_*-encoded by admin.upload{demo,icon,mapimg}.php so every special char survives both the HTML and JS layers (#1113). *}
                {$message nofilter}
            </div>
        {/if}

        <form action=""
              method="POST"
              id="{$form_name}"
              enctype="multipart/form-data"
              data-testid="uploadfile-form">
            {csrf_field}
            <input type="hidden" name="upload" value="1">

            <label class="label" for="uploadfile-input">Select file</label>
            <div class="file-input">
                <label class="btn btn--secondary">
                    <input type="file"
                           id="uploadfile-input"
                           name="{$input_name}"
                           data-testid="uploadfile-input"
                           data-file-input
                           required
                           hidden>
                    Choose file&hellip;
                </label>
                <span class="text-muted text-sm" data-file-name>No file chosen</span>
            </div>

            <div style="margin-top:0.75rem;display:flex;justify-content:flex-end">
                <button type="submit"
                        class="btn btn--primary btn--sm"
                        data-testid="uploadfile-submit">
                    Save
                </button>
            </div>
        </form>
    </div>
</div>
{* Popup window has no theme.js (only theme.css), so the filename mirror runs inline. *}
<script>
(function () {
    document.addEventListener('change', function (e) {
        var t = e.target;
        if (!t || !(t instanceof HTMLInputElement)) return;
        if (t.type !== 'file' || !t.matches('[data-file-input]')) return;
        var lbl = t.closest('label');
        var wrap = lbl && lbl.parentElement;
        var span = wrap && wrap.querySelector('[data-file-name]');
        if (!span) return;
        var f = t.files && t.files[0];
        span.textContent = f ? f.name : 'No file chosen';
    });
})();
</script>
</body>
</html>
