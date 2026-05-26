<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under the Elastic License 2.0.
// See LICENSE.txt for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

declare(strict_types=1);

/**
 * Demo download endpoint.
 *
 * URL: getdemo.php?type=<B|S>&id=<int>
 *
 * `:prefix_demos` carries one row per uploaded demo with:
 *   - demtype  enum('B','S')  — B = Ban, S = Submission
 *   - demid    int            — fk to :prefix_bans.bid OR :prefix_submissions.id
 *   - filename text           — server-side basename under SB_DEMOS
 *   - origname text           — display name shown to the downloader
 *
 * Hardening (kept from the legacy entry point and re-stated here so a
 * future refactor doesn't drop a load-bearing check):
 *
 *   1. `basename()` collapses any path component a forged `filename`
 *      column might carry (tampered DB row, partial migration). We don't
 *      run user-supplied paths through this — but we DO run a row a DB
 *      compromise could have rewritten, so the LFI guard is layered.
 *   2. `in_array(scandir(SB_DEMOS), …, true)` ensures the file we resolve
 *      is actually one of the listed demos in the directory — symlinks
 *      pointing outside SB_DEMOS aren't valid even if `file_exists()`
 *      would have accepted them.
 *
 * Different from the legacy 1.x entry point (the rewrite does not
 * trace structurally back):
 *   - Validation order is parameter shape -> DB lookup -> on-disk
 *     reachability, with each branch carrying its own error string.
 *   - `Content-Disposition: attachment` (RFC 6266) replaces the old
 *     `Content-type: application/force-download` non-standard MIME hack.
 *     `application/octet-stream` is the correct media type for an
 *     opaque binary payload, and `Content-Disposition` carries the
 *     "force download" semantics natively.
 *   - `origname` is sanitized through `rawurlencode()` for the
 *     `filename*=UTF-8''` field so non-ASCII names round-trip per
 *     RFC 5987; the ASCII fallback strips characters browsers can't
 *     handle in the older `filename=` slot.
 */

require_once __DIR__ . '/init.php';

const DEMO_TYPE_BAN        = 'B';
const DEMO_TYPE_SUBMISSION = 'S';

/**
 * Emit a plain-text error to the downloader and stop. The endpoint is
 * not reached from a panel surface, so we don't carry chrome.
 */
function getdemo_die(int $status, string $message): never
{
    http_response_code($status);
    header('Content-Type: text/plain; charset=utf-8');
    echo $message;
    exit;
}

/**
 * Build the RFC 6266-shaped Content-Disposition value. Browsers that
 * honour `filename*` use the UTF-8 form; older clients fall back to
 * the ASCII-stripped `filename=` slot.
 */
function getdemo_disposition_header(string $name): string
{
    $ascii = preg_replace('/[^\x20-\x7E]/', '_', $name) ?? 'demo.dem';
    $ascii = str_replace(['"', "\r", "\n"], '_', $ascii);
    $utf8  = rawurlencode($name);

    return sprintf(
        'attachment; filename="%s"; filename*=UTF-8\'\'%s',
        $ascii,
        $utf8,
    );
}

$type = strtoupper((string) ($_GET['type'] ?? ''));
$id   = (int) ($_GET['id'] ?? 0);

if (!in_array($type, [DEMO_TYPE_BAN, DEMO_TYPE_SUBMISSION], true)) {
    getdemo_die(400, 'Unknown demo type. Expected "B" or "S".');
}
if ($id <= 0) {
    getdemo_die(400, 'Missing or invalid demo id.');
}

$row = $GLOBALS['PDO']
    ->query('SELECT filename, origname FROM `:prefix_demos` WHERE demtype = :type AND demid = :id')
    ->single([':type' => $type, ':id' => $id]);

if (!$row) {
    getdemo_die(404, 'Demo not found.');
}

$onDisk = basename((string) $row['filename']);
$origin = (string) ($row['origname'] ?? '') !== '' ? (string) $row['origname'] : $onDisk;
$path   = SB_DEMOS . '/' . $onDisk;

$listing = is_dir(SB_DEMOS) ? scandir(SB_DEMOS) : false;
if ($listing === false || !in_array($onDisk, $listing, true) || !is_file($path)) {
    getdemo_die(404, 'Demo file is no longer on disk.');
}

$size = filesize($path);
if ($size === false) {
    getdemo_die(500, 'Unable to read demo file.');
}

// Bin the headers: octet-stream is the canonical MIME for an opaque
// binary; Content-Disposition: attachment is the RFC 6266 way to ask
// the browser to download rather than render.
header('Content-Type: application/octet-stream');
header('Content-Disposition: ' . getdemo_disposition_header($origin));
header('Content-Length: ' . $size);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-transform');

while (ob_get_level() > 0) {
    ob_end_clean();
}

readfile($path);
