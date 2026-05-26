<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under the Elastic License 2.0.
// See LICENSE.txt for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

use Sbpp\Auth\Host;
use Sbpp\Mail\EmailType;
use Sbpp\Mail\Mail;
use Sbpp\Mail\Mailer;
use Sbpp\Markup\IntroRenderer;

/**
 * Upstream GitHub Releases endpoint. Overridable via the
 * `SB_RELEASE_LATEST_URL` constant so tests can point at a local fixture
 * instead of the real GitHub API; defining it before the handler runs is
 * a no-op for production self-hosters who never see the constant.
 */
function _api_system_release_upstream_url(): string
{
    if (defined('SB_RELEASE_LATEST_URL') && is_string(SB_RELEASE_LATEST_URL) && SB_RELEASE_LATEST_URL !== '') {
        return SB_RELEASE_LATEST_URL;
    }
    return 'https://api.github.com/repos/sbpp/sourcebans-pp/releases/latest';
}

/**
 * Re-read the cached GitHub release payload, returning null when the file
 * is missing, unreadable, or doesn't decode to the expected
 * `{tag_name, html_url, cached_at}` shape. The "stale-while-error" branch
 * in the main handler relies on this returning a usable payload regardless
 * of `cached_at`, so don't filter on TTL here.
 *
 * @return array{tag_name: string, html_url: string, cached_at: int}|null
 */
function _api_system_release_load_cache(string $file): ?array
{
    if (!is_file($file)) {
        return null;
    }
    $raw = @file_get_contents($file);
    if ($raw === false || $raw === '') {
        return null;
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return null;
    }
    $tag = $decoded['tag_name'] ?? null;
    $url = $decoded['html_url'] ?? null;
    $at  = $decoded['cached_at'] ?? null;
    if (!is_string($tag) || $tag === '' || !is_string($url) || !is_int($at)) {
        return null;
    }
    return ['tag_name' => $tag, 'html_url' => $url, 'cached_at' => $at];
}

/**
 * Persist the latest upstream payload + a fresh `cached_at` timestamp.
 * Failure to write (read-only filesystem, permission glitch, …) is
 * silently ignored; the in-memory response we already constructed is
 * still served, and the next call will just re-fetch.
 *
 * Writes are atomic: dump the payload into a sibling tempfile, then
 * `rename()` it into place. Without that, two concurrent panel hits on
 * a cold cache could both call `file_put_contents` on the live file,
 * interleave their writes, and leave a half-written JSON that
 * `_api_system_release_load_cache` would then reject — repeatedly,
 * because every subsequent fetch tries to re-cache and may collide
 * again. `rename()` is atomic on POSIX filesystems (the panel only
 * runs on Linux per docker-compose.yml), so the live file is never
 * partially written.
 */
function _api_system_release_save_cache(string $file, string $tagName, string $htmlUrl): void
{
    $payload = json_encode([
        'tag_name'  => $tagName,
        'html_url'  => $htmlUrl,
        'cached_at' => time(),
    ]);
    if ($payload === false) {
        return;
    }
    $tmp = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
    if (@file_put_contents($tmp, $payload) === strlen($payload)) {
        @rename($tmp, $file);
    } else {
        @unlink($tmp);
    }
}

/**
 * Fetch the latest release from the configured upstream and return the
 * `{tag_name, html_url}` pair. Returns null on any network failure,
 * non-2xx response, malformed JSON, or missing tag_name — the caller
 * uses null as the "fall back to cache" signal.
 *
 * GitHub requires a `User-Agent` on every API call; the documented
 * `Accept: application/vnd.github+json` pins the response shape to the
 * v3 contract so a future server-side default change can't surprise us.
 * The 5s timeout keeps the handler from stalling a page render on a
 * network blip — the panel falls through to the cache (or the "Error"
 * envelope) instead of hanging.
 *
 * @return array{tag_name: string, html_url: string}|null
 */
function _api_system_release_fetch_upstream(): ?array
{
    // Identify the panel version in the User-Agent so GitHub's rate-limit
    // dashboards (and self-hosters tailing their proxy logs) can attribute
    // traffic to a specific install. The upstream URL is hardcoded
    // `https://`, so only the `https` stream wrapper key is consulted —
    // the `http` block file_get_contents would read on a `http://` URL is
    // dead defense; omit it.
    $headers = 'User-Agent: SourceBans++/' . SB_VERSION . "\r\n"
             . "Accept: application/vnd.github+json\r\n";
    $context = stream_context_create([
        'https' => [
            'method'        => 'GET',
            'header'        => $headers,
            'timeout'       => 5,
            'ignore_errors' => true,
        ],
    ]);

    $body = @file_get_contents(_api_system_release_upstream_url(), false, $context);
    if ($body === false || $body === '') {
        return null;
    }

    // `ignore_errors=true` makes file_get_contents return the body even on
    // a non-2xx status, so reject anything outside 2xx explicitly. PHP 8.5
    // deprecated the magic local $http_response_header in favour of
    // http_get_last_response_headers() (introduced in 8.4); the panel's
    // floor is 8.5 (#1289) so the function is always available.
    $responseHeaders = http_get_last_response_headers();
    if (is_array($responseHeaders) && isset($responseHeaders[0]) && preg_match('~HTTP/\S+\s+(\d+)~', $responseHeaders[0], $m)) {
        $status = (int) $m[1];
        if ($status < 200 || $status >= 300) {
            return null;
        }
    }

    $decoded = json_decode($body, true);
    if (!is_array($decoded)) {
        return null;
    }
    $tag = $decoded['tag_name'] ?? null;
    $url = $decoded['html_url'] ?? null;
    if (!is_string($tag) || $tag === '' || !is_string($url)) {
        return null;
    }
    return ['tag_name' => $tag, 'html_url' => $url];
}

/**
 * Build the public response shape from a cached or freshly-fetched
 * release pair, branching on whether the local panel is on a real
 * version, on the dev sentinel, or trails / leads the upstream tag.
 *
 * Strips an optional leading `v`/`V` from the upstream tag (GitHub
 * releases historically use both `v1.8.4` and `1.8.4` shapes; SB_VERSION
 * is always plain). The `'dev'` sentinel from `init.php` is special-cased
 * because `version_compare('1.8.4', 'dev')` returns 1 (PHP treats `dev`
 * as a pre-release suffix), which would otherwise falsely advertise
 * "A New Release is Available." on every dev-checkout panel.
 *
 * @return array{release_latest: string, release_url: string, release_msg: string, release_update: bool}
 */
function _api_system_release_format(string $tagName, string $htmlUrl, string $localVersion): array
{
    $latest = ltrim($tagName, 'vV');

    if ($localVersion === 'dev') {
        return [
            'release_latest' => $latest,
            'release_url'    => $htmlUrl,
            'release_msg'    => 'Tracking development build.',
            'release_update' => false,
        ];
    }

    if (version_compare($latest, $localVersion) > 0) {
        return [
            'release_latest' => $latest,
            'release_url'    => $htmlUrl,
            'release_msg'    => 'A New Release is Available.',
            'release_update' => true,
        ];
    }

    return [
        'release_latest' => $latest,
        'release_url'    => $htmlUrl,
        'release_msg'    => 'You have the Latest Release.',
        'release_update' => false,
    ];
}

/**
 * Public action: report whether a newer SourceBans++ release is
 * available. Sources from `api.github.com/repos/sbpp/sourcebans-pp/releases/latest`
 * with a 1-day on-disk cache + stale-while-error fallback (the cached
 * payload is served regardless of TTL when the upstream call fails) so a
 * busy panel can't blow through GitHub's 60 req/hr unauthenticated limit
 * and a transient GitHub blip doesn't paint the panel red.
 *
 * @return array{release_latest: string, release_url: string, release_msg: string, release_update: bool}
 */
function api_system_check_version(array $params): array
{
    // 1-day TTL is the rate-limit answer: GitHub's unauthenticated REST
    // API caps each source IP at 60 req/hr, and a busy panel polled by
    // every admin tab on every page render would burn that trivially.
    // Releases ship far less often than once a day, so anything tighter
    // wouldn't buy a more accurate "is there an upgrade?" signal.
    $ttlSeconds = 86400;
    $cacheFile  = SB_CACHE . 'github_release_latest.json';
    $cached     = _api_system_release_load_cache($cacheFile);

    if ($cached !== null && (time() - $cached['cached_at']) < $ttlSeconds) {
        return _api_system_release_format($cached['tag_name'], $cached['html_url'], SB_VERSION);
    }

    $fetched = _api_system_release_fetch_upstream();
    if ($fetched !== null) {
        _api_system_release_save_cache($cacheFile, $fetched['tag_name'], $fetched['html_url']);
        return _api_system_release_format($fetched['tag_name'], $fetched['html_url'], SB_VERSION);
    }

    if ($cached !== null) {
        // Stale-while-error: cache exists but is past TTL and the upstream
        // call just failed. Quiet — the user-visible response is still the
        // cached payload, panel stays green.
        return _api_system_release_format($cached['tag_name'], $cached['html_url'], SB_VERSION);
    }

    // Both the upstream fetch AND the cache fallback are unavailable;
    // there's nothing meaningful to render. Log to the PHP error log so
    // a self-hoster troubleshooting "panel says `Error retrieving latest
    // release.`" gets a hint pointing at the upstream rather than at the
    // panel itself. Only fired on this fully-degraded branch — the
    // fresh-fetch-failed-but-cache-exists path above is benign.
    error_log('SourceBans++: system.check_version upstream fetch failed and no cache available');

    return [
        'release_latest' => 'Error',
        'release_url'    => '',
        'release_msg'    => 'Error retrieving latest release.',
        'release_update' => false,
    ];
}

function api_system_sel_theme(array $params): array
{
    $theme = rawurldecode((string)($params['theme'] ?? ''));
    $theme = str_replace(['../', '..\\', chr(0)], '', $theme);
    $theme = basename($theme);

    if ($theme === '' || $theme[0] === '.' || !in_array($theme, scandir(SB_THEMES), true)
        || !is_dir(SB_THEMES . $theme) || !file_exists(SB_THEMES . $theme . '/theme.conf.php')) {
        throw new ApiError('invalid_theme', 'Invalid theme selected.');
    }

    include SB_THEMES . $theme . '/theme.conf.php';
    if (!defined('theme_screenshot')) {
        throw new ApiError('bad_theme', 'Bad theme selected.');
    }

    return [
        'theme'      => $theme,
        'name'       => theme_name,
        'author'     => theme_author,
        'version'    => theme_version,
        'link'       => theme_link,
        'screenshot' => 'themes/' . $theme . '/' . strip_tags(theme_screenshot),
    ];
}

function api_system_apply_theme(array $params): array
{
    $theme = rawurldecode((string)($params['theme'] ?? ''));
    $theme = str_replace(['../', '..\\', chr(0)], '', $theme);
    $theme = basename($theme);

    if ($theme === '' || $theme[0] === '.' || !in_array($theme, scandir(SB_THEMES), true)
        || !is_dir(SB_THEMES . $theme) || !file_exists(SB_THEMES . $theme . '/theme.conf.php')) {
        throw new ApiError('invalid_theme', 'Invalid theme selected.');
    }

    include SB_THEMES . $theme . '/theme.conf.php';
    if (!defined('theme_screenshot')) {
        throw new ApiError('bad_theme', 'Bad theme selected.');
    }

    $GLOBALS['PDO']->query("UPDATE `:prefix_settings` SET value = ? WHERE setting = 'config.theme'")->execute([$theme]);
    return ['reload' => true];
}

function api_system_clear_cache(array $params): array
{
    $cachedir = dir(SB_CACHE);
    while (($entry = $cachedir->read()) !== false) {
        if (is_file($cachedir->path . $entry)) {
            @unlink($cachedir->path . $entry);
        }
    }
    $cachedir->close();

    return ['cleared' => true];
}

function api_system_send_mail(array $params): array
{
    global $username;
    $subject = (string)($params['subject'] ?? '');
    $message = (string)($params['message'] ?? '');
    $type    = (string)($params['type']    ?? '');
    $id      = (int)($params['id']      ?? 0);

    if ($type !== 's' && $type !== 'p') {
        throw new ApiError('bad_type', 'Bad email type.');
    }

    $email = '';
    if ($type === 's') {
        $GLOBALS['PDO']->query("SELECT email FROM `:prefix_submissions` WHERE subid = :id");
        $GLOBALS['PDO']->bind(':id', $id);
        $row = $GLOBALS['PDO']->single();
        $email = $row['email'] ?? '';
    } elseif ($type === 'p') {
        $GLOBALS['PDO']->query("SELECT email FROM `:prefix_protests` WHERE pid = :id");
        $GLOBALS['PDO']->bind(':id', $id);
        $row = $GLOBALS['PDO']->single();
        $email = $row['email'] ?? '';
    }

    if (empty($email)) {
        throw new ApiError('no_email', 'There is no email to send to supplied.');
    }

    $sent = Mail::send($email, EmailType::Custom, [
        '{message}' => $message,
        '{subject}' => $subject,
        '{admin}'   => $username,
        '{link}'    => Host::complete(true),
        '{home}'    => Host::complete(true),
    ], $subject);

    if (!$sent) {
        throw new ApiError('mail_failed', 'Failed to send the email to the user.');
    }

    Log::add(LogType::Message, 'Email Sent', "$username send an email to $email. Subject: '[SourceBans++] $subject'; Message: $message");

    // #1275 — admin-bans is Pattern A; the email-sending UI is reached
    // from one of the two queues, so route the operator back to the
    // queue they came from. `$type` is 's' (submission) or 'p'
    // (protest) — guarded above, so the match is exhaustive.
    $redir = $type === 's'
        ? 'index.php?p=admin&c=bans&section=submissions'
        : 'index.php?p=admin&c=bans&section=protests';

    return [
        'message' => [
            'title' => 'Email Sent',
            'body'  => 'The email has been sent to the user.',
            'kind'  => 'green',
            'redir' => $redir,
        ],
    ];
}

/**
 * Issue #1455: send a SMTP test message so an operator can verify
 * the credentials they just typed into Admin → Settings actually work
 * end-to-end (Symfony Mailer → SMTP relay → recipient inbox) without
 * having to wait for a real password-reset / ban-protest event to fire
 * the next outbound mail.
 *
 * Permission gate: ADMIN_OWNER | ADMIN_WEB_SETTINGS. Matches the other
 * settings-page-only handlers (sel_theme / apply_theme / clear_cache /
 * preview_intro_text); only an operator who can edit SMTP settings has
 * a legitimate reason to trigger a test send.
 *
 * Error-envelope contract:
 *   - validation         missing or malformed recipient
 *   - smtp_not_configured  smtp.host / smtp.user / smtp.pass empty (we
 *                          short-circuit BEFORE Mail::send so the user
 *                          gets a pointed message instead of the
 *                          generic mail_failed branch — `Mail::send`'s
 *                          internal `Mailer::create() === null` arm
 *                          also returns false but only logs to
 *                          `:prefix_log`)
 *   - rate_limited       too many sends in the throttle window;
 *                        the message interpolates "try again in Ns"
 *                        so the client can echo it directly via a
 *                        toast — there's no separate machine-readable
 *                        `retry_after_seconds` field on the wire
 *                        (ApiError only carries code + message + field
 *                        + httpStatus; if a future client needs a
 *                        numeric value, parse it from the message
 *                        or extend ApiError with a `details` payload).
 *   - mail_failed        SMTP error reported by Symfony Mailer (full
 *                        cause already lives in `:prefix_log` by way
 *                        of `Mail::send`'s own catch block — we don't
 *                        echo it to the client to avoid leaking
 *                        provider error strings that may carry
 *                        credentials / hostnames)
 *
 * Throttle: at most one send attempt per 10 seconds per panel
 * install. The scope is per-install (not per-user) because the abuse
 * surface is the outbound SMTP relay, not a per-user resource — two
 * admins racing each other shouldn't double the relay's load. We
 * stamp the throttle file BEFORE the SMTP I/O so a slow / hung
 * connection can't be hammered while a first call is mid-handshake
 * (so a series of mail_failed outcomes ALSO consume rate-limit
 * slots — intentional). The counter is a single-int cache file
 * under SB_CACHE, mirroring the shape
 * `_api_system_release_save_cache` uses (atomic tempfile +
 * `rename()`). Stale-while-error: if the cache directory is
 * unwritable we let the send through rather than fail closed —
 * losing the rate limit is preferable to losing the diagnostic
 * affordance the operator opened this surface to use.
 *
 * Throttle interaction with api_system_clear_cache: that handler
 * removes every file in SB_CACHE, including this throttle file. An
 * operator who hits rate_limited and then clicks "Clear cache"
 * resets the throttle. Acceptable because (a) both surfaces are
 * gated by the same ADMIN_OWNER | ADMIN_WEB_SETTINGS mask, so the
 * "throttle bypass" doesn't escalate privileges; (b) the threat
 * model the throttle defends is a *script* hammering this endpoint,
 * not a settings-admin abusing their own cache button.
 *
 * Audit: every send (success OR mail_failed) lands an entry in
 * `:prefix_log` so test sends can't be used to silently probe SMTP
 * credentials or enumerate valid relay endpoints. The success entry
 * names the recipient (which is operator-controlled — by default the
 * operator's own email); the failure entry adds the SMTP error class
 * for diagnostics. The validation / smtp_not_configured / rate_limited
 * branches don't log (they never reach the SMTP wire — same shape
 * `api_system_send_mail` uses).
 *
 * @param array{to?: string} $params
 * @return array{to: string, sent_at: int}
 */
function api_system_test_email(array $params): array
{
    global $userbank;

    // Resolve the calling admin's display name via $userbank rather
    // than the legacy `global $username` shape — `$username` is only
    // set by the install-wizard pages (web/install/pages/page.[2-6].php)
    // and the PHPUnit ApiTestCase shim; the JSON-API lifecycle in
    // production never assigns it, so `global $username` would
    // resolve to an unset variable, emitting empty strings into the
    // audit log and the email body's {admin} placeholder. The same
    // bug-shape lives in api_system_send_mail / api_account_* and
    // is a separate cleanup — don't propagate it forward here. See
    // the broader sweep tracked alongside this PR.
    $adminName = (string) ($userbank->GetProperty('user') ?? '');

    $to = trim((string) ($params['to'] ?? ''));
    if ($to === '') {
        // Default to the logged-in admin's email so an operator who
        // just wants to "send the test to me" doesn't have to retype
        // their own address (which they can read off Admin → My
        // account if it slipped their mind).
        $to = (string) ($userbank->GetProperty('email') ?? '');
    }
    if ($to === '') {
        throw new ApiError(
            'validation',
            'Enter a recipient email address (no email is configured on your admin account).',
            'to',
        );
    }
    // FILTER_VALIDATE_EMAIL enforces RFC 822 grammar. PHP's
    // implementation also rejects strings well over RFC 5321's
    // 254-octet forward-path cap in every build the panel
    // supports (the regex is internally bounded), but exact
    // byte-edge behavior varies subtly across PHP versions, so
    // don't rely on this as a hard size gate. If a future
    // bug-report surfaces a 255-byte address slipping through,
    // add an explicit `strlen($to) > 254` guard ahead of the
    // filter — for now the filter is sufficient in practice.
    if (filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
        throw new ApiError('validation', 'Recipient email is not a valid address.', 'to');
    }

    // Short-circuit on the smtp.host / smtp.user / smtp.pass-empty
    // arm so the user sees actionable copy ("configure SMTP first")
    // instead of the generic mail_failed branch. Mail::send itself
    // also returns false in this state but only logs to
    // `:prefix_log` — easy to miss. We deliberately don't validate
    // smtp.port: Mailer defaults to 25, which is the right shape
    // for a local relay (the most common test target).
    if (Mailer::create() === null) {
        throw new ApiError(
            'smtp_not_configured',
            'SMTP host, username, or password is empty. Configure SMTP first and save the form, then try again.',
        );
    }

    // Rate limit: at most 1 send per 10s, scoped to the entire panel
    // install (not the calling admin). The throttle window is short
    // enough that a legitimate operator iterating on SMTP settings
    // isn't blocked, but long enough that a script can't flood the
    // outbound relay (a 10s window plus the audit-log row per send
    // makes burst abuse both bandwidth-bounded AND traceable).
    $throttleSeconds = 10;
    $now = time();
    $cachePath = SB_CACHE . 'test-email-throttle';
    $previous = @file_get_contents($cachePath);
    if ($previous !== false) {
        $previousTs = (int) trim((string) $previous);
        $elapsed = $now - $previousTs;
        if ($previousTs > 0 && $elapsed >= 0 && $elapsed < $throttleSeconds) {
            $retryAfter = $throttleSeconds - $elapsed;
            throw new ApiError(
                'rate_limited',
                sprintf('Test email throttled — try again in %d seconds.', $retryAfter),
            );
        }
    }

    // Stamp the throttle file BEFORE the SMTP I/O so a slow / hung
    // SMTP connection (e.g. operator pointed `smtp.host` at an
    // unroutable address) can't be hammered while the first call is
    // still mid-handshake. We write atomically via tempfile+rename
    // (same shape `_api_system_release_save_cache` uses) so a
    // crashing PHP process leaves either the old timestamp or the
    // new one — never a half-written file.
    //
    // `tempnam()` falls back to `sys_get_temp_dir()` (typically
    // `/tmp`) when SB_CACHE is unwritable. If `/tmp` and SB_CACHE
    // are on different filesystems (common in containerized
    // deployments where `/tmp` is host tmpfs and the panel cache
    // is a bind mount), `rename()` fails with EXDEV and the tmp
    // file would leak if we didn't unlink it on the failure
    // branch. So: unlink whenever either the write OR the rename
    // misses, not just the write.
    if (!is_dir(SB_CACHE)) {
        @mkdir(SB_CACHE, 0o775, true);
    }
    $tmpPath = @tempnam(SB_CACHE, 'tem');
    if ($tmpPath !== false) {
        if (@file_put_contents($tmpPath, (string) $now) === false
            || !@rename($tmpPath, $cachePath)
        ) {
            @unlink($tmpPath);
        }
    }

    // Both subject slots emit the same string: the email header
    // subject AND the body's `<strong>{subject}</strong>` slot in
    // `contact_custom.html`. They're rendered in different
    // surfaces (mail-client subject line vs HTML body), so a
    // recipient seeing one identifier in their inbox and a
    // different one in the body would be confusing. The
    // `[SourceBans++]` prefix is what their mail client surfaces
    // alongside other notifications from the panel.
    $subject = '[SourceBans++] SMTP test email';
    $message = "This is a test email from SourceBans++ confirming your SMTP credentials are working."
        . " It was triggered manually by admin '" . $adminName . "' from Admin → Settings → SMTP."
        . " You can safely ignore it — no action is required.";
    $sent = Mail::send($to, EmailType::Custom, [
        '{message}' => $message,
        '{subject}' => $subject,
        '{admin}'   => $adminName,
        '{link}'    => Host::complete(true),
        '{home}'    => Host::complete(true),
    ], $subject);

    if (!$sent) {
        // The full cause already lives in `:prefix_log` (Mail::send
        // catches the Throwable from Symfony Mailer and emits a
        // `LogType::Error, 'Mail error', $e->getMessage()` row).
        // Mirror that with a paired audit entry so the success /
        // failure ratio is visible in the audit log without having
        // to cross-reference timestamps.
        Log::add(LogType::Warning, 'Test email failed', "Admin '$adminName' tried to send a test email to '$to'; see prior 'Mail error' entry for the SMTP-side cause.");
        throw new ApiError(
            'mail_failed',
            'SMTP send failed. Check the audit log under Admin → System Log for the cause.',
        );
    }

    Log::add(LogType::Message, 'Test email sent', "Admin '$adminName' sent a SMTP test email to '$to'.");

    return [
        'to' => $to,
        'sent_at' => $now,
    ];
}

/**
 * Render an admin-authored Markdown snippet (currently used by the
 * dashboard `dash.intro.text` setting; #1207 SET-1) through the same
 * `Sbpp\Markup\IntroRenderer` the public dashboard uses, so the
 * settings page can show a live "this is what visitors will see"
 * preview without forcing the admin to save + navigate to `/`.
 *
 * Critical: NEVER render the supplied Markdown with anything other
 * than `IntroRenderer::renderIntroText`. The renderer wraps
 * league/commonmark with `html_input: 'escape'` and
 * `allow_unsafe_links: false` — that's the only safe path documented
 * in AGENTS.md ("Admin-authored display text"). #1113 was a stored
 * XSS rooted in a parallel render path; ducking back into a plain
 * CommonMark/Parsedown call here would re-open the vector.
 *
 * Gated on `ADMIN_OWNER | ADMIN_WEB_SETTINGS` because the only
 * caller is the settings page (gated on the same flag), and we'd
 * rather refuse a stray call from another surface than discover a
 * new caller exists.
 *
 * @param array{markdown?: string} $params
 * @return array{html: string}
 */
function api_system_preview_intro_text(array $params): array
{
    $markdown = (string) ($params['markdown'] ?? '');
    // Long inputs can stall the renderer; the textarea has no
    // server-side length limit but a 64 KiB ceiling is well past any
    // reasonable intro length (the rendered field already lives in
    // a single TEXT column) and prevents a logged-in admin from
    // wedging the API with a runaway paste.
    if (strlen($markdown) > 65536) {
        $markdown = substr($markdown, 0, 65536);
    }
    return ['html' => IntroRenderer::renderIntroText($markdown)];
}

function api_system_rehash_admins(array $params): array
{
    $serversCsv = (string)($params['servers'] ?? '');
    $servers = array_filter(explode(',', $serversCsv), fn($v) => $v !== '');
    $results = [];
    foreach ($servers as $sid) {
        $ret = rcon('sm_rehash', (int)$sid);
        $results[] = [
            'sid'     => (int)$sid,
            'success' => $ret === '',
        ];
    }
    return ['results' => $results];
}
