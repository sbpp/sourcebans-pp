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
 * Variable shape:
 *
 *   - The first block (`config_title`, `config_logo`, …, `config_smtp`,
 *     `config_mail_from_*`) mirrors the legacy default theme template's
 *     variables verbatim so SmartyTemplateRule passes the default-theme
 *     PHPStan leg without baseline churn. The legacy theme drives every
 *     dynamic checkbox via inline `<script>` blocks emitted by
 *     `web/pages/admin.settings.php`, so it doesn't reference the
 *     boolean toggles in this View.
 *   - The second block (`active_section`, `$can_*`, the boolean
 *     toggles, `config_default_page`, `config_smtp_verify_peer`) is
 *     consumed only by the sbpp2026 template, which renders checkboxes
 *     statefully (`{if $config_debug}checked{/if}`) instead of patching
 *     them post-hoc. PHPStan baselines these as "unused" against the
 *     default-theme leg; the sbpp2026 leg sets
 *     `reportUnmatchedIgnoredErrors=false` so the baseline collapses
 *     cleanly when D1 retires the legacy template.
 */
final class AdminSettingsView extends View
{
    public const TEMPLATE = 'page_admin_settings_settings.tpl';

    /**
     * @param list<string>            $bans_customreason Persisted custom ban reasons,
     *     each entity-encoded at write time. Empty list when disabled.
     * @param array{0: string, 1: string, 2: string} $config_smtp Legacy
     *     [host, user, port] tuple used by the default theme.
     */
    public function __construct(
        public readonly string $config_title,
        public readonly string $config_logo,
        public readonly int $config_min_password,
        public readonly string $config_dateformat,
        public readonly string $config_dash_title,
        public readonly string $config_dash_text,
        public readonly int $auth_maxlife,
        public readonly int $auth_maxlife_remember,
        public readonly int $auth_maxlife_steam,
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
