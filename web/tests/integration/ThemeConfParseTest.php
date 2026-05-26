<?php

declare(strict_types=1);

namespace Sbpp\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Sbpp\Theme\ThemeConf;

/**
 * Issue #1466: the admin Themes picker regex-reads theme.conf.php
 * without executing it. The shipped default manifest uses
 * single-quoted define() values; the pre-fix parser only matched
 * double-quoted literals, so cards showed "by Unknown · v?".
 */
final class ThemeConfParseTest extends TestCase
{
    public function testDefaultThemeConfParsesSingleQuotedDefines(): void
    {
        $src = (string) file_get_contents(ROOT . 'themes/default/theme.conf.php');

        $this->assertSame('SourceBans++ Default', ThemeConf::parseDefine($src, 'theme_name', ''));
        $this->assertSame('SourceBans++ Dev Team', ThemeConf::parseDefine($src, 'theme_author', 'Unknown'));
        $this->assertSame('2.0.0', ThemeConf::parseDefine($src, 'theme_version', '?'));
        $this->assertSame('https://github.com/sbpp/sourcebans-pp', ThemeConf::parseDefine($src, 'theme_link', ''));
        $this->assertSame('screenshot.jpg', ThemeConf::parseDefine($src, 'theme_screenshot', ''));
    }

    public function testParseDefineStillAcceptsDoubleQuotedManifests(): void
    {
        $src = <<<'PHP'
<?php
define('theme_name', "Fork Theme");
define('theme_author', "Example Author");
define('theme_version', "1.2.3");
define('theme_link', "https://example.com/theme");
define('theme_screenshot', "preview.png");
PHP;

        $this->assertSame('Fork Theme', ThemeConf::parseDefine($src, 'theme_name', ''));
        $this->assertSame('Example Author', ThemeConf::parseDefine($src, 'theme_author', 'Unknown'));
        $this->assertSame('1.2.3', ThemeConf::parseDefine($src, 'theme_version', '?'));
        $this->assertSame('https://example.com/theme', ThemeConf::parseDefine($src, 'theme_link', 'missing'));
        $this->assertSame('preview.png', ThemeConf::parseDefine($src, 'theme_screenshot', ''));
    }

    public function testEmptyDoubleQuotedValueDoesNotFatal(): void
    {
        $src = "<?php\ndefine('theme_link', \"\");\n";

        $this->assertSame('', ThemeConf::parseDefine($src, 'theme_link', 'fallback'));
    }

    public function testEmptySingleQuotedValue(): void
    {
        $src = "<?php\ndefine('theme_link', '');\n";

        $this->assertSame('', ThemeConf::parseDefine($src, 'theme_link', 'fallback'));
    }

    public function testEscapedApostropheInSingleQuotedValue(): void
    {
        $src = "<?php\ndefine('theme_author', 'Bob\\'s Fork');\n";

        $this->assertSame("Bob's Fork", ThemeConf::parseDefine($src, 'theme_author', 'Unknown'));
    }

    public function testDoubleQuotedKeyIsNotMatched(): void
    {
        $src = 'define("theme_name", "Wrong key shape");';

        $this->assertSame('fallback', ThemeConf::parseDefine($src, 'theme_name', 'fallback'));
    }

    public function testSanitizeLinkAllowsEmptyAndHttpUrls(): void
    {
        $this->assertSame('', ThemeConf::sanitizeLink(''));
        $this->assertSame('https://example.com', ThemeConf::sanitizeLink('https://example.com'));
        $this->assertSame('http://example.com/path', ThemeConf::sanitizeLink('http://example.com/path'));
    }

    public function testSanitizeLinkRejectsNonHttpSchemes(): void
    {
        $this->assertSame('', ThemeConf::sanitizeLink('javascript:alert(1)'));
        $this->assertSame('', ThemeConf::sanitizeLink('file:///etc/passwd'));
    }

    public function testSanitizeScreenshotFilenameStripsPaths(): void
    {
        $this->assertSame('shot.jpg', ThemeConf::sanitizeScreenshotFilename('shot.jpg', 'screenshot.jpg'));
        $this->assertSame('shot.jpg', ThemeConf::sanitizeScreenshotFilename('../../../shot.jpg', 'screenshot.jpg'));
        $this->assertSame('screenshot.jpg', ThemeConf::sanitizeScreenshotFilename('', 'screenshot.jpg'));
        $this->assertSame('screenshot.jpg', ThemeConf::sanitizeScreenshotFilename('..', 'screenshot.jpg'));
    }

    public function testDefaultThemeDiscoveryRowMatchesManifest(): void
    {
        $filename = 'default';
        $confSrc  = (string) file_get_contents(ROOT . 'themes/default/theme.conf.php');

        $row = [
            'author'  => ThemeConf::parseDefine($confSrc, 'theme_author', 'Unknown'),
            'version' => ThemeConf::parseDefine($confSrc, 'theme_version', '?'),
            'link'    => ThemeConf::sanitizeLink(ThemeConf::parseDefine($confSrc, 'theme_link', '')),
        ];

        $this->assertSame('SourceBans++ Dev Team', $row['author']);
        $this->assertSame('2.0.0', $row['version']);
        $this->assertSame('https://github.com/sbpp/sourcebans-pp', $row['link']);
        $this->assertStringContainsString(
            'themes/' . $filename . '/',
            'themes/' . $filename . '/' . ThemeConf::sanitizeScreenshotFilename(
                ThemeConf::parseDefine($confSrc, 'theme_screenshot', 'screenshot.jpg'),
                'screenshot.jpg',
            ),
        );
    }
}
