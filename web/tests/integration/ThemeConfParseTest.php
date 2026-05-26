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
    }
}
