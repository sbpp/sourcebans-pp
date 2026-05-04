<?php

/*************************************************************************
This file is part of SourceBans++

SourceBans++ (c) 2014-2024 by SourceBans++ Dev Team

The SourceBans++ Web panel is licensed under a
Creative Commons Attribution-NonCommercial-ShareAlike 3.0 Unported License.

You should have received a copy of the license along with this
work.  If not, see <http://creativecommons.org/licenses/by-nc-sa/3.0/>.
 *************************************************************************/

declare(strict_types=1);

namespace Sbpp\Markup;

use League\CommonMark\CommonMarkConverter;
use League\CommonMark\ConverterInterface;

/**
 * Markdown renderer for admin-controlled display text stored in the DB
 * (currently the dashboard `dash.intro.text` setting; the convention is
 * "anything an admin writes through the panel and we render to public
 * visitors goes through here").
 *
 * Issue #1113 / closes #521: the dashboard intro previously rendered
 * straight DB HTML through `{$dashboard_text nofilter}`, which made any
 * admin with `ADMIN_SETTINGS` a stored-XSS vector for every dashboard
 * visitor. We swapped the WYSIWYG editor for a plain textarea and now
 * pipe the value through CommonMark with `html_input: 'escape'` and
 * `allow_unsafe_links: false`, so:
 *
 *   - Inline HTML is rendered as text, not parsed (so `<script>` shows
 *     up literally; it does not execute).
 *   - `javascript:` / `data:` links are stripped during rendering.
 *
 * Existing installs whose intro is still the legacy
 * `<center><p>...</p></center>` HTML default are converted to the new
 * Markdown default by the paired updater migration
 * (`web/updater/data/804.php`); admins who customised with HTML will see
 * their text rendered as escaped text — acceptable degradation for a
 * security fix.
 *
 * The converter is constructed lazily and cached as a private static so
 * we only pay the configuration cost once per request and the class
 * stays trivial to unit-test (no DI, no globals).
 */
final class IntroRenderer
{
    private static ?ConverterInterface $converter = null;

    /**
     * Render the dashboard intro text from CommonMark Markdown to HTML.
     * Output is safe to drop into a template behind `nofilter`.
     */
    public static function renderIntroText(string $markdown): string
    {
        return self::converter()->convert($markdown)->getContent();
    }

    private static function converter(): ConverterInterface
    {
        if (self::$converter === null) {
            self::$converter = new CommonMarkConverter([
                // Treat any inline HTML as text. We deliberately don't use
                // 'strip' so admins see exactly what they typed when they
                // accidentally paste raw HTML — escaped, but still legible.
                'html_input'         => 'escape',
                // Drop javascript:/data:/vbscript: hrefs so a Markdown link
                // can't be turned into an XSS vector either.
                'allow_unsafe_links' => false,
                // Belt-and-braces: keep the document tree shallow enough
                // that a pathological intro can't blow the stack.
                'max_nesting_level'  => 50,
            ]);
        }
        return self::$converter;
    }
}
