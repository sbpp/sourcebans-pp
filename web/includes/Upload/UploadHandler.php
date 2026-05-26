<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under the Elastic License 2.0.
// See LICENSE.txt for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

declare(strict_types=1);

namespace Sbpp\Upload;

use Sbpp\Log;
use Sbpp\View\Renderer;
use Sbpp\View\UploadFileView;
use LogType;

/**
 * Shared file-upload chrome for the three pop-up upload handlers
 * (`admin.uploaddemo.php`, `admin.uploadicon.php`,
 * `admin.uploadmapimg.php`).
 *
 * The pre-rewrite path duplicated the same six-step flow across the
 * three pages (CSRF check → extension allowlist → `move_uploaded_file`
 * → `Log::add` → emit a `<script>window.opener.<callback>(...)</script>`
 * blob → render the popup template). This class centralises every
 * step; each page handler now just calls
 * {@see UploadHandler::handle()} with its specific allowlist + callback
 * + permission gate + audit-log title.
 *
 * Filename hygiene contract:
 *   - Demo uploads (#1113): the on-disk name is a fresh
 *     `md5(time() . rand())` so admin-controlled strings never reach
 *     `move_uploaded_file()`'s second argument; the original name is
 *     surfaced back to the opener via `window.opener.demo()` for the
 *     UI label only.
 *   - Icon / map-image uploads: the on-disk name still mirrors the
 *     uploaded filename for back-compat with theme forks that look it
 *     up by name. {@see UploadHandler::sanitiseName()} hardens it
 *     against directory traversal (`..`, `/`, `\`) — the legacy code
 *     trusted `$_FILES[…][name]` verbatim, which was the LFI surface
 *     #1113's audit flagged.
 *
 * `window.opener.<cb>(…)` is invoked with JSON-encoded arguments so
 * `'`, `"`, `<`, `>`, `&` survive the HTML-attribute + JS-string
 * round-trip — same #1113 fix that motivated the legacy `JSON_HEX_*`
 * flag set.
 */
final class UploadHandler
{
    private const JS_FLAGS = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP
        | JSON_HEX_QUOT | JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES;

    /**
     * @param object $userbank Active session (`Sbpp\Auth\UserManager`
     *   in practice, typed-loose here so legacy alias `CUserManager`
     *   keeps working). Pass-through for the permission check.
     * @param object $theme    Smarty instance.
     * @param int    $permission Bitmask of `WebPermission::*` (or
     *   `WebPermission::mask(...)`) allowed to use this surface.
     * @param string $deniedAuditMsg Audit-log body for the denied path
     *   (matches the legacy "tried to upload a demo, but doesn't have
     *   access" / icon / mapimage lines).
     * @param string $deniedUserMsg Body returned via `die()` when the
     *   user doesn't have access (preserved verbatim from v1.x for
     *   theme-fork compatibility).
     * @param string $field        `$_FILES[…]` key (`demo_file`,
     *   `icon_file`, `mapimg_file`).
     * @param list<string> $allowed Lower-case extension allowlist.
     * @param string $destDir Final destination directory (must exist
     *   and be writable; `SB_DEMOS`, `SB_ICONS`, `SB_MAPS` are the
     *   three live values).
     * @param string $callback `window.opener` callback name
     *   (`demo` / `icon` / `mapimg`) — the JS function the parent
     *   window is expected to define.
     * @param bool   $renameToHash When `true` (demo flow), the on-disk
     *   filename is replaced with `md5(time() . rand())` BEFORE the
     *   final move. The original filename is sent back to the opener
     *   as the second `window.opener.<cb>()` argument; the parent uses
     *   it as the human label. When `false` (icon / mapimage), the
     *   sanitised original name is used as the on-disk name.
     * @param string $auditOk    `Log::add` title on success.
     * @param string $auditFmt   `Log::add` body — `%s` is replaced
     *   with the original filename. Mirrors the legacy strings.
     * @param string $errorMsg   Body shown in the popup when the
     *   uploaded file fails the extension / size / sanity check.
     * @param string $title      Popup `<h3>` title.
     * @param string $formName   Form's `name=` attribute (used by the
     *   View DTO to scope CSS).
     * @param string $formats    Human-readable allowed formats
     *   ("a DEM, ZIP, …").
     */
    public static function handle(
        object $userbank,
        object $theme,
        int $permission,
        string $deniedAuditMsg,
        string $deniedUserMsg,
        string $field,
        array $allowed,
        string $destDir,
        string $callback,
        bool $renameToHash,
        string $auditOk,
        string $auditFmt,
        string $errorMsg,
        string $title,
        string $formName,
        string $formats,
    ): void {
        if (!$userbank->HasAccess($permission)) {
            Log::add(LogType::Warning, 'Hacking Attempt', $deniedAuditMsg);
            die($deniedUserMsg);
        }

        $message = '';

        if (isset($_POST['upload'])) {
            \CSRF::rejectIfInvalid();

            $message = self::moveAndCallback(
                field:         $field,
                allowed:       $allowed,
                destDir:       $destDir,
                callback:      $callback,
                renameToHash:  $renameToHash,
                auditOk:       $auditOk,
                auditFmt:      $auditFmt,
                errorMsg:      $errorMsg,
            );
        }

        Renderer::render($theme, new UploadFileView(
            title:      $title,
            message:    $message,
            input_name: $field,
            form_name:  $formName,
            formats:    $formats,
        ));
    }

    /**
     * Returns the popup body — either the success `<script>` blob or
     * the format-error string. Internal seam for the public
     * {@see handle()} flow; kept private so callers go through the
     * permission-gated entry point.
     *
     * @param list<string> $allowed
     */
    private static function moveAndCallback(
        string $field,
        array $allowed,
        string $destDir,
        string $callback,
        bool $renameToHash,
        string $auditOk,
        string $auditFmt,
        string $errorMsg,
    ): string {
        $upload = $_FILES[$field] ?? null;
        if (!is_array($upload) || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return $errorMsg;
        }

        $rawName = (string) ($upload['name'] ?? '');
        if (!\checkExtension($rawName, $allowed)) {
            return $errorMsg;
        }

        $tmp = (string) ($upload['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            return $errorMsg;
        }

        $sanitised = self::sanitiseName($rawName);
        $diskName  = $renameToHash
            ? md5((string) time() . random_int(0, 1000))
            : $sanitised;

        $dest = rtrim($destDir, '/\\') . '/' . $diskName;
        if (!@move_uploaded_file($tmp, $dest)) {
            return $errorMsg;
        }

        Log::add(LogType::Message, $auditOk, sprintf($auditFmt, $rawName));

        $jsDisk = json_encode($diskName,  self::JS_FLAGS);
        $jsName = json_encode($rawName,   self::JS_FLAGS);
        $args   = $renameToHash ? "$jsDisk,$jsName" : $jsName;
        return "<script>window.opener.{$callback}($args);self.close()</script>";
    }

    /**
     * Strip directory-traversal characters from an admin-uploaded
     * filename. The legacy `move_uploaded_file($_, SB_X . '/' . $name)`
     * trusted `$name` verbatim, which let a `name=../../../etc/passwd`
     * upload land outside `SB_X`.
     */
    public static function sanitiseName(string $raw): string
    {
        $base = basename($raw);
        // basename() handles `/` on POSIX but not `\\`, so strip both
        // explicitly. Trim leading dots so a `..hidden.png` upload
        // doesn't become a hidden file. Final fallback to a
        // timestamp-based name when basename() collapsed everything
        // (e.g. ".." input).
        $base = str_replace('\\', '/', $base);
        $base = basename($base);
        $base = ltrim($base, '.');
        if ($base === '') {
            $base = 'upload-' . time();
        }
        return $base;
    }
}
