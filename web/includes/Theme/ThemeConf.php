<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under the Elastic License 2.0.
// See LICENSE.txt for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

declare(strict_types=1);

namespace Sbpp\Theme;

/**
 * Read theme.conf.php metadata without executing the file (PHP cannot
 * define() the same constant twice in one request).
 *
 * The admin Settings → Themes picker uses this regex reader. JSON API
 * handlers ({@see api_system_sel_theme}) include the manifest and read
 * live constants — richer PHP expressions work there but not in the picker.
 */
final class ThemeConf
{
    /**
     * Pluck a `define('<key>', "<value>")` or `define('<key>', '<value>')`
     * literal out of a theme.conf.php source string.
     */
    public static function parseDefine(string $src, string $key, string $default): string
    {
        $pattern = '/define\(\s*\'' . preg_quote($key, '/') . '\'\s*,\s*(?:"([^"]*)"|\'((?:[^\'\\\\]|\\\\.)*)\')\s*\)\s*;/';
        if (preg_match($pattern, $src, $m) !== 1) {
            return $default;
        }

        if (preg_match('/,\s*"/', $m[0]) === 1) {
            $value = $m[1];
        } else {
            $value = self::unescapeSingleQuoted($m[2] ?? '');
        }

        return strip_tags($value);
    }

    /**
     * Allow only http(s) homepage URLs for theme cards; empty is valid.
     */
    public static function sanitizeLink(string $link): string
    {
        $link = trim(strip_tags($link));
        if ($link === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $link) !== 1) {
            return '';
        }

        return $link;
    }

    /**
     * Screenshot filenames must stay inside the theme directory (basename only).
     */
    public static function sanitizeScreenshotFilename(string $name, string $default): string
    {
        $name = trim(strip_tags($name));
        $base = basename(str_replace('\\', '/', $name));
        if ($base === '' || $base === '.' || $base === '..' || str_contains($base, '/')) {
            return $default;
        }

        return $base;
    }

    private static function unescapeSingleQuoted(string $value): string
    {
        return str_replace(['\\\\', "\\'"], ['\\', "'"], $value);
    }
}
