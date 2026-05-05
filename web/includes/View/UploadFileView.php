<?php
declare(strict_types=1);

namespace Sbpp\View;

/**
 * Generic "pick a file and POST it" popup — binds to
 * `page_uploadfile.tpl`.
 *
 * The same template + view backs three popup-window flows opened via
 * `window.open(...)` from the parent admin page:
 *
 *   - `web/pages/admin.uploadicon.php`   → mod icon (gif/jpg/png)
 *   - `web/pages/admin.uploadmapimg.php` → server-list map image (jpg)
 *   - `web/pages/admin.uploaddemo.php`   → demo attachment
 *     (zip/rar/dem/7z/bz2/gz)
 *
 * Each handler dies early on insufficient permissions, so the template
 * itself never gates content on `can_*` flags.
 *
 * Variables (one row per `{$prop}` reference in either default or
 * sbpp2026 `page_uploadfile.tpl`):
 *
 *   - `$title`: window title + heading.
 *   - `$message`: ALREADY-RENDERED HTML emitted with `nofilter`. On a
 *     successful upload each handler builds a
 *     `<script>window.opener.<cb>(...);self.close()</script>` blob
 *     that JSON-encodes the admin-controlled filename with
 *     `JSON_HEX_*` flags (#1113 fix); on a rejection the handlers
 *     emit a hand-built `<b>…</b>` error string. The template MUST
 *     keep the `nofilter` comment annotation in place.
 *   - `$input_name`: the `name=` attribute of the `<input type="file">`,
 *     so each handler reads its `$_FILES[<input_name>]` entry directly.
 *   - `$form_name`: HTML `id` of the `<form>` (used by the popup's
 *     callbacks to look the form up via `document.getElementById`).
 *   - `$formats`: human-readable allowed-formats sentence ("a JPG",
 *     "a GIF, PNG or JPG", …) shown in the help text.
 */
final class UploadFileView extends View
{
    public const TEMPLATE = 'page_uploadfile.tpl';

    public function __construct(
        public readonly string $title,
        public readonly string $message,
        public readonly string $input_name,
        public readonly string $form_name,
        public readonly string $formats,
    ) {
    }
}
