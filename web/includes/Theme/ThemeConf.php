<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under the Elastic License 2.0.
// See LICENSE.txt for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

declare(strict_types=1);

namespace Sbpp\Theme;

/**
 * Read theme.conf.php metadata without executing the file (PHP cannot
 * define() the same constant twice in one request).
 */
final class ThemeConf
{
    /**
     * Pluck a `define('<key>', "<value>")` or `define('<key>', '<value>')`
     * literal out of a theme.conf.php source string.
     */
    public static function parseDefine(string $src, string $key, string $default): string
    {
        $pattern = '/define\(\s*\'' . preg_quote($key, '/') . '\'\s*,\s*(?:"([^"]*)"|\'([^\']*)\')\s*\)\s*;/';
        if (preg_match($pattern, $src, $m) === 1) {
            $value = $m[1] !== '' ? $m[1] : $m[2];

            return strip_tags($value);
        }

        return $default;
    }
}
