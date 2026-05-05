<?php
declare(strict_types=1);

namespace Sbpp\View;

/**
 * Login page — binds to `page_login.tpl`.
 *
 * Both the legacy `web/themes/default/page_login.tpl` and the
 * sbpp2026 redesign render with the custom `-{ … }-` delimiter pair
 * so the inline `<script>` can use `{` / `}` for JS object literals
 * without `{literal}` wrapping. The page handler swaps delimiters
 * around `Renderer::render()` (mirroring the
 * `Sbpp\View\YourAccountView` pattern); {@see View::DELIMITERS}
 * teaches `SmartyTemplateRule` to scan with the matching pair so
 * template/View parity is still enforced in both PHPStan legs.
 *
 * The login screen is anonymous-only: the page handler short-circuits
 * to the dashboard when the caller already has a session, so this View
 * never needs the `Sbpp\View\Perms::for()` boolean splat — there are
 * no `{if $can_*}` gates in the template. The property surface is
 * intentionally limited to what BOTH templates actually consume so the
 * dual-theme PHPStan matrix added in #1123 A2 stays clean without
 * baseline entries during the rollout window:
 *
 *   - `$normallogin_show` — gated by `config.enablenormallogin`,
 *     hides the username/password form when off.
 *   - `$steamlogin_show`  — gated by `config.enablesteamlogin`,
 *     hides the "Continue with Steam" button when off.
 *   - `$redir`            — *legacy compatibility shim*. The legacy
 *     `web/themes/default/page_login.tpl` inlines this string as a
 *     JavaScript expression into its login button's `onclick=` and
 *     into the Enter/Space `keydown` handler (currently the literal
 *     `DoLogin('');`, which depends on `DoLogin` from
 *     `web/scripts/sourcebans.js` — loaded only by the legacy chrome).
 *     The new sbpp2026 template does NOT use it for login wiring (it
 *     posts via `sb.api.call(Actions.AuthLogin, …)` with a hardcoded
 *     `redirect: ''`); the property exists on this View purely so the
 *     legacy theme keeps rendering correctly during the rollout window
 *     AND the sbpp2026 template's dead `data-legacy-redir` attribute
 *     keeps SmartyTemplateRule's "every declared property must be
 *     referenced" check happy across BOTH legs of the dual-theme
 *     PHPStan matrix added in #1123 A2. Both the property and the
 *     legacy template go away with #1123 D1.
 *
 * Inline error banner content (failed login, locked account, no
 * access, …) is intentionally NOT a property: the sbpp2026 template
 * surfaces the `?m=…` query param via `window.SBPP.showToast(...)`
 * on page load, so the message text lives client-side. The legacy
 * template still emits `ShowBox()` JS dialogs from the page handler
 * for backwards compatibility; both branches go away when #1123 D1
 * deletes the legacy bundle.
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
    // rule the exact strings it needs. (`Sbpp\View\YourAccountView`
    // carries the looser annotation and only gets away with it because
    // B20 hasn't replaced its A1 stub yet — the stub short-circuits the
    // rule before the delimiter mismatch matters.)
    /** @var array{0: '-{', 1: '}-'} */
    public const DELIMITERS = ['-{', '}-'];

    public function __construct(
        public readonly bool $normallogin_show,
        public readonly bool $steamlogin_show,
        public readonly string $redir,
    ) {
    }
}
