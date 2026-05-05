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
*}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>{$title} : SourceBans++</title>
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
            <input type="file"
                   id="uploadfile-input"
                   class="input"
                   name="{$input_name}"
                   data-testid="uploadfile-input"
                   required>

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
</body>
</html>
