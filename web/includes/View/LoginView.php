<?php
declare(strict_types=1);

namespace Sbpp\View;

/**
 * Login page — binds to `page_login.tpl`.
 *
 * The template renders with the custom `-{ … }-` delimiter pair so
 * inline `<script>` blocks can keep `{` / `}` for JS object literals
 * without `{literal}` wrapping. The page handler swaps delimiters
 * around `Renderer::render()` (mirroring the
 * `Sbpp\View\YourAccountView` pattern); {@see View::DELIMITERS}
 * teaches `SmartyTemplateRule` to scan with the matching pair so
 * template/View parity is still enforced.
 *
 * The login screen is anonymous-only: the page handler short-circuits
 * to the dashboard when the caller already has a session, so this View
 * never needs the `Sbpp\View\Perms::for()` boolean splat — there are
 * no `{if $can_*}` gates in the template. Inline error banner content
 * (failed login, locked account, no access, …) is surfaced via
 * `window.SBPP.showToast(...)` driven off the `?m=…` query param on
 * page load, so the message text lives client-side and is not a
 * property of this View.
 *
 *   - `$normallogin_show` — gated by `config.enablenormallogin`,
 *     hides the username/password form when off.
 *   - `$steamlogin_show`  — gated by `config.enablesteamlogin`,
 *     hides the "Continue with Steam" button when off.
 *   - `$redir`            — Post-login redirect target. The template
 *     echoes it on a dead `data-legacy-redir="…"` attribute so the
 *     SmartyTemplateRule property↔reference parity check stays green;
 *     the actual login wiring posts via
 *     `sb.api.call(Actions.AuthLogin, …)` with a hardcoded
 *     `redirect: ''` (post-login destination is the dashboard).
 */
final class LoginView extends View
{
    public const TEMPLATE = 'page_login.tpl';

    // The `@var` is intentionally narrower than the base View::DELIMITERS
    // (which is `array{0: string, 1: string}`). PHPStan's reflection
    // inherits the base annotation onto overridden constants, which widens
    // the literal `'-{'` / `'}-'` types to plain `string` and breaks
    // `SmartyTemplateRule::delimitersFor()` — that helper inspects each
    // element via `getConstantStrings()` to pick the matching scan
    // delimiters, and a non-literal `string` returns zero strings, so the
    // rule silently falls back to the default `{ … }` pair and starts
    // matching JS object literals as Smarty tags. Pinning the literal
    // values here keeps the override tighter than the base and gives the
    // rule the exact strings it needs.
    /** @var array{0: '-{', 1: '}-'} */
    public const DELIMITERS = ['-{', '}-'];

    public function __construct(
        public readonly bool $normallogin_show,
        public readonly bool $steamlogin_show,
        public readonly string $redir,
    ) {
    }
}
