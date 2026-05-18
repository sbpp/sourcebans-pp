<?php
declare(strict_types=1);

namespace Sbpp\View;

/**
 * "Features" sub-tab of the admin settings page — binds to
 * `page_admin_settings_features.tpl`. Toggles for opt-in/optional
 * panel features (group banning, kickit, public bans export, etc.).
 *
 * Group banning + Friends banning depend on a configured Steam Web API
 * key; `$steamapi` reflects whether `STEAMAPIKEY` is set so the
 * template can disable the toggle inline rather than failing at submit.
 *
 * The template renders checkboxes statefully
 * (`{if $config_debug}checked{/if}`) directly off the boolean
 * properties — no inline `<script>` patching.
 */
final class AdminFeaturesView extends View
{
    public const TEMPLATE = 'page_admin_settings_features.tpl';

    public function __construct(
        public readonly bool $steamapi,
        public readonly bool $can_web_settings,
        public readonly bool $can_owner,
        public readonly bool $export_public,
        public readonly bool $enable_kickit,
        public readonly bool $enable_groupbanning,
        public readonly bool $enable_friendsbanning,
        public readonly bool $enable_adminrehashing,
        public readonly bool $enable_steamlogin,
        public readonly bool $enable_normallogin,
        public readonly bool $enable_publiccomments,
        public readonly bool $telemetry_enabled,
    ) {
    }
}
