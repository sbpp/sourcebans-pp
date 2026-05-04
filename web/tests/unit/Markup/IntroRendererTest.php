<?php

declare(strict_types=1);

namespace Sbpp\Tests\Unit\Markup;

use PHPUnit\Framework\TestCase;
use Sbpp\Markup\IntroRenderer;

/**
 * Issue #1113 / closes #521 regression suite for the dashboard intro
 * renderer.
 *
 * The vulnerability was: `dash.intro.text` from `sb_settings` was emitted
 * through `{$dashboard_text nofilter}` on the dashboard. Any admin with
 * `ADMIN_SETTINGS` could store arbitrary HTML in that field via the
 * TinyMCE editor, and that HTML rendered raw on every dashboard visit —
 * stored XSS targeting every panel visitor, including unauthenticated
 * banlist viewers if the dashboard is the public default page.
 *
 * The fix replaces the WYSIWYG editor with a plain Markdown textarea and
 * pipes the value through CommonMark with `html_input: 'escape'` and
 * `allow_unsafe_links: false`. These tests pin both halves of that
 * contract:
 *
 *   - **Negative**: HTML / `javascript:` / `data:` payloads do NOT survive
 *     to the rendered output as live HTML.
 *   - **Positive**: ordinary Markdown still renders the expected tags so
 *     the feature remains useful for admins.
 *   - **Migration**: the legacy HTML default (`<center>...</center>`) is
 *     rendered as escaped text, demonstrating the acceptable degradation
 *     for installs whose admins customised with raw HTML before the fix.
 */
final class IntroRendererTest extends TestCase
{
    /**
     * Top of the threat model: a `<script>` tag pasted into the intro
     * setting must not appear as a live tag in the output. CommonMark's
     * `html_input: 'escape'` mode escapes it to `&lt;script&gt;…`, which
     * renders as visible text — what an admin would expect from a
     * Markdown editor that "doesn't accept HTML".
     */
    public function testScriptTagIsNeverEmittedLiterally(): void
    {
        $payload = '<script>alert(1)</script>';

        $rendered = IntroRenderer::renderIntroText($payload);

        $this->assertStringNotContainsString('<script', $rendered,
            'A literal <script> tag must not survive into the rendered HTML.');
        $this->assertStringNotContainsString('</script>', $rendered,
            'A literal </script> tag must not survive into the rendered HTML.');
        // Sanity check: the escaped form should be present so admins
        // can see what they typed.
        $this->assertStringContainsString('&lt;script&gt;', $rendered);
    }

    /**
     * Belt-and-braces around the same threat — common XSS sinks expressed
     * as inline event handlers / iframes / SVG should all be escaped, not
     * parsed. The escaped output may still mention the attribute name
     * (e.g. `&lt;img src=x onerror="..."&gt;`); what matters is that the
     * tag itself is rendered as text, not as a live HTML element.
     */
    public function testCommonInlineHtmlVectorsAreEscaped(): void
    {
        $vectors = [
            '<img src=x onerror="alert(1)">',
            '<iframe src="https://evil.test"></iframe>',
            '<svg onload="alert(1)"></svg>',
            '<a href="javascript:alert(1)">click</a>',
        ];

        foreach ($vectors as $payload) {
            $rendered = IntroRenderer::renderIntroText($payload);
            // None of these should produce a live tag — the `<` MUST be
            // entity-encoded by CommonMark's html_input=escape mode. We
            // assert on the live-tag form rather than the substring
            // because the escaped output legitimately mentions e.g.
            // "onerror=" inside the visible &lt;img …&gt; text.
            $this->assertStringNotContainsString('<img', $rendered, "img passed through for: $payload");
            $this->assertStringNotContainsString('<iframe', $rendered, "iframe passed through for: $payload");
            $this->assertStringNotContainsString('<svg', $rendered, "svg passed through for: $payload");
            // Sanity: the `<` of every vector starts the input, so its
            // escaped form must show up in the output.
            $this->assertStringContainsString('&lt;', $rendered, "no escaped `<` for: $payload");
        }
    }

    /**
     * `allow_unsafe_links: false` strips dangerous URL schemes from
     * Markdown links during rendering. The link text remains; the href
     * is removed (or replaced with an empty value), so a crafted
     * `[click](javascript:alert(1))` cannot become an executable anchor.
     */
    public function testJavascriptSchemeMarkdownLinkIsStripped(): void
    {
        $rendered = IntroRenderer::renderIntroText('[click](javascript:alert(1))');

        $this->assertStringNotContainsString('javascript:', $rendered,
            'A Markdown link with a javascript: href must not retain the dangerous scheme.');
    }

    public function testDataSchemeMarkdownLinkIsStripped(): void
    {
        $rendered = IntroRenderer::renderIntroText('[click](data:text/html,<script>alert(1)</script>)');

        $this->assertStringNotContainsString('data:text/html', $rendered,
            'A Markdown link with a data: HTML href must not retain the dangerous scheme.');
    }

    /**
     * Positive case: ordinary Markdown still works. If this fails the
     * feature is broken for admins, which is just as bad as a regression
     * on the security side.
     */
    public function testValidMarkdownRendersExpectedTags(): void
    {
        $rendered = IntroRenderer::renderIntroText("# Hi\n\n[link](https://example.com)");

        $this->assertStringContainsString('<h1>Hi</h1>', $rendered);
        $this->assertStringContainsString('<a href="https://example.com">link</a>', $rendered);
    }

    public function testParagraphsAndEmphasisRender(): void
    {
        $rendered = IntroRenderer::renderIntroText("Hello **world**.\n\nSecond paragraph.");

        $this->assertStringContainsString('<strong>world</strong>', $rendered);
        $this->assertStringContainsString('<p>Hello', $rendered);
        $this->assertStringContainsString('<p>Second paragraph.</p>', $rendered);
    }

    /**
     * Migration evidence: the original (pre-#1113) `data.sql` default
     * was `<center><p>Your new SourceBans install</p><p>SourceBans++
     * successfully installed!</center>`. After the fix, that exact
     * string would render as escaped text on installs that haven't been
     * migrated, AND on installs whose admins customised with raw HTML.
     *
     * This is the documented "acceptable degradation": admins who relied
     * on raw HTML re-author in Markdown next time they edit the setting.
     * The paired updater migration (`web/updater/data/804.php`) replaces
     * only the legacy default value with the new Markdown default, so
     * fresh installs and untouched upgraded installs both end up clean.
     */
    public function testLegacyHtmlDefaultIsRenderedAsEscapedText(): void
    {
        $legacyDefault = '<center><p>Your new SourceBans install</p><p>SourceBans++ successfully installed!</center>';

        $rendered = IntroRenderer::renderIntroText($legacyDefault);

        $this->assertStringNotContainsString('<center>', $rendered,
            'Legacy <center> default must render as text, not as a live tag.');
        $this->assertStringContainsString('&lt;center&gt;', $rendered,
            'Legacy <center> default must render as escaped text so an admin sees what to migrate.');
        // Plain text content still shows up so the page isn't empty.
        $this->assertStringContainsString('Your new SourceBans install', $rendered);
    }

    /**
     * Empty / whitespace-only input is a normal user state (a blank
     * intro, an admin who deleted the field). The renderer should handle
     * it without throwing, and produce no markup that would visually
     * collapse the dashboard layout.
     */
    public function testEmptyInputRendersToEmptyOutput(): void
    {
        $this->assertSame('', IntroRenderer::renderIntroText(''));
    }

    public function testWhitespaceOnlyInputRendersToEmptyOutput(): void
    {
        $this->assertSame('', IntroRenderer::renderIntroText("   \n\n   "));
    }
}
