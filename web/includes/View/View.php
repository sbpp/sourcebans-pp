<?php
declare(strict_types=1);

namespace Sbpp\View;

/**
 * Base class for typed Smarty-template view models.
 *
 * Each concrete subclass:
 *   - Targets exactly one .tpl file (identified by {@see View::TEMPLATE}).
 *   - Declares every template variable as a public readonly property.
 *
 * The SmartyTemplateRule PHPStan rule inspects concrete subclasses and
 * verifies that the declared property set matches the variables actually
 * referenced in the bound template.
 * See `web/includes/PHPStan/SmartyTemplateRule.php`.
 *
 * Most templates use Smarty's default `{…}` delimiters. Views whose template
 * uses the custom `-{…}-` pair (currently only `page_login.tpl`, in both the
 * legacy and sbpp2026 themes) must override {@see View::DELIMITERS} with the
 * matching pair so the rule parses the template correctly.
 *
 * ### Permission booleans
 *
 * Views that gate template content on the current user's permissions
 * declare each gate as its own constructor-promoted
 * `public readonly bool $can_<flag>` property (e.g. `$can_add_ban`,
 * `$can_edit_all_bans`, `$can_owner`). The page handler builds the View
 * by splatting {@see Perms::for()} into the named arguments:
 *
 * ```php
 * use Sbpp\View\Perms;
 * use Sbpp\View\Renderer;
 *
 * Renderer::render($theme, new BanListView(
 *     ...Perms::for($userbank),
 *     ban_list: $bans,
 *     // …
 * ));
 * ```
 *
 * `Perms::for()` returns a flat `['can_<flag>' => bool, …]` array
 * covering every `ADMIN_*` constant defined from
 * `web/configs/permissions/web.json`. PHP discards splatted keys the
 * View doesn't declare, so each subclass opts in to only the booleans
 * its template actually consumes. `SmartyTemplateRule` keeps both
 * sides honest: every template-referenced `$can_*` must be declared,
 * and every declared `$can_*` must be referenced.
 *
 * Each `bool` lives on the concrete subclass (NOT on this base) on
 * purpose: declaring booleans the template never reads would silently
 * defeat `SmartyTemplateRule`'s parity check, so dead permission
 * checks would accumulate untestably across pages.
 */
abstract class View
{
    /**
     * Smarty template filename, relative to the theme directory
     * (e.g. 'page_dashboard.tpl').
     */
    public const TEMPLATE = '';

    /**
     * Left/right delimiter pair for the template this view binds to.
     * Override on the subclass when the template is rendered with custom
     * delimiters such as `-{ … }-`.
     *
     * @var array{0: string, 1: string}
     */
    public const DELIMITERS = ['{', '}'];
}
