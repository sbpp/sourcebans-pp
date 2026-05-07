<?php
declare(strict_types=1);

namespace Sbpp\View;

/**
 * "Main settings" sub-tab of the admin settings page — binds to
 * `page_admin_settings_settings.tpl`.
 *
 * The dashboard intro text (`dash.intro.text`) field stays a plain
 * `<textarea>` here. The previous TinyMCE WYSIWYG was the source of
 * the stored-XSS vector fixed in #1113; the value now flows through
 * `Sbpp\Markup\IntroRenderer` (CommonMark, `html_input: 'escape'`,
 * `allow_unsafe_links: false`) on render. Re-introducing any HTML
 * editor here would re-open that vector. See AGENTS.md
 * "Anti-patterns" + "Admin-authored display text".
 *
 * The template renders checkboxes statefully
 * (`{if $config_debug}checked{/if}`) directly off the boolean
 * properties — no inline `<script>` patching.
 */
final class AdminSettingsView extends View
{
    public const TEMPLATE = 'page_admin_settings_settings.tpl';

    /**
     * @param list<string>            $bans_customreason Persisted custom ban reasons,
     *     each entity-encoded at write time. Empty list when disabled.
     * @param array{0: string, 1: string, 2: string} $config_smtp Legacy
     *     [host, user, port] tuple. Preserved for any third-party theme
     *     that forked the pre-v2.0.0 default; the shipped template
     *     reads the individual `$config_smtp_*` properties below.
     */
    public function __construct(
        public readonly string $config_title,
        public readonly string $config_logo,
        public readonly int $config_min_password,
        public readonly string $config_dateformat,
        public readonly string $config_dash_title,
        public readonly string $config_dash_text,
        // #1207 SET-1: pre-rendered HTML for the live-preview pane's
        // first paint. Source value is `config_dash_text` (raw
        // Markdown); the page handler runs it through
        // `Sbpp\Markup\IntroRenderer::renderIntroText()` so the
        // template can drop the result into the preview pane behind
        // `nofilter` without the JS round-trip. The JS preview update
        // (debounced on textarea input) calls
        // `system.preview_intro_text` for fresh renders.
        public readonly string $config_dash_text_preview,
        public readonly int $auth_maxlife,
        public readonly int $auth_maxlife_remember,
        public readonly int $auth_maxlife_steam,
        // #1232: human-readable echoes for the three minute-typed
        // auth lifetime fields. The wire format stays minutes (these
        // are display-only spans next to each `<input type="number">`);
        // the strings come from `Sbpp\Util\Duration::humanizeMinutes()`
        // and are mirrored client-side by the page-tail JS so the echo
        // updates as the operator types. First paint is server-rendered
        // so the page works without JS.
        public readonly string $auth_maxlife_human,
        public readonly string $auth_maxlife_remember_human,
        public readonly string $auth_maxlife_steam_human,
        public readonly int $config_bans_per_page,
        public readonly array $config_smtp,
        public readonly string $config_mail_from_email,
        public readonly string $config_mail_from_name,
        public readonly array $bans_customreason,
        public readonly bool $can_web_settings,
        public readonly bool $can_owner,
        public readonly string $active_section,
        public readonly bool $config_debug,
        public readonly bool $enable_submit,
        public readonly bool $enable_protest,
        public readonly bool $enable_commslist,
        public readonly bool $protest_emailonlyinvolved,
        public readonly bool $dash_lognopopup,
        public readonly int $config_default_page,
        public readonly bool $banlist_hideadmname,
        public readonly bool $banlist_nocountryfetch,
        public readonly bool $banlist_hideplayerips,
        public readonly bool $config_smtp_verify_peer,
    ) {
    }
}
