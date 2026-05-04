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
 * uses the custom `-{…}-` pair (currently only `page_youraccount.tpl`) must
 * override {@see View::DELIMITERS} with the matching pair so the rule parses
 * the template correctly.
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
