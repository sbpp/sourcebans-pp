<?php
/*************************************************************************
This file is part of SourceBans++

SourceBans++ (c) 2014-2024 by SourceBans++ Dev Team

The SourceBans++ Web panel is licensed under a
Creative Commons Attribution-NonCommercial-ShareAlike 3.0 Unported License.

You should have received a copy of the license along with this
work.  If not, see <http://creativecommons.org/licenses/by-nc-sa/3.0/>.

CLI generator for web/scripts/api-contract.js.

The browser side used to hand-duplicate every action name and permission
constant from PHP. That made silent drift (a renamed handler / a new
permission flag) easy and dangerous. This script reads the PHP source of
truth and emits a single contract file that the JS code imports as
`Actions.*` / `Perms.*`. CI re-runs the generator and fails when the file
on disk is stale, so the duplication can never come back.

Inputs:
  - web/api/handlers/_register.php       (action -> handler / perm table)
  - web/api/handlers/*.php               (handler functions + @param/@return)
  - web/configs/permissions/web.json     (canonical permission flag values)

Output:
  - web/scripts/api-contract.js          (deterministic, sorted, byte-stable)
*************************************************************************/

declare(strict_types=1);

const WEB_ROOT      = __DIR__ . '/..';
const REGISTER_FILE = WEB_ROOT . '/api/handlers/_register.php';
const HANDLER_DIR   = WEB_ROOT . '/api/handlers';
const PERMS_FILE    = WEB_ROOT . '/configs/permissions/web.json';
const OUTPUT_FILE   = WEB_ROOT . '/scripts/api-contract.js';

main();

function main(): void
{
    $actions = parseRegister(REGISTER_FILE);
    $docs    = collectHandlerDocs(HANDLER_DIR);
    $perms   = loadPerms(PERMS_FILE);

    $js = renderContract($actions, $docs, $perms);

    if (!file_exists(dirname(OUTPUT_FILE))) {
        mkdir(dirname(OUTPUT_FILE), 0o775, true);
    }
    file_put_contents(OUTPUT_FILE, $js);

    fwrite(STDERR, "wrote " . OUTPUT_FILE . " (" . count($actions) . " actions, "
        . count($perms) . " perms, " . countDocumented($actions, $docs) . " typedefs)\n");
}

/**
 * Parse Api::register('action.name', 'fn_name', ...) lines out of _register.php.
 * Returns a list of ['action' => ..., 'fn' => ...] entries in source order.
 *
 * We intentionally use a regex rather than nikic/php-parser: the registry
 * file is a flat list of register() calls with no expressions on the
 * action/fn arguments, and pulling in a parser just for this would be
 * disproportionate. The grammar is:
 *
 *     Api::register( ' action.name ' , ' fn_name ' [, perm_expr [, $bool [, $bool ]]]);
 *
 * @return list<array{action: string, fn: string}>
 */
function parseRegister(string $path): array
{
    if (!is_file($path)) {
        fwrite(STDERR, "fatal: registry file not found: $path\n");
        exit(1);
    }

    $src = (string)file_get_contents($path);
    $rx  = "/Api::register\(\s*'([a-z][a-z0-9_.]*)'\s*,\s*'([a-zA-Z_][a-zA-Z0-9_]*)'/";
    if (preg_match_all($rx, $src, $m, PREG_SET_ORDER) === false) {
        fwrite(STDERR, "fatal: regex failed against $path\n");
        exit(1);
    }

    $out  = [];
    $seen = [];
    foreach ($m as $hit) {
        $action = $hit[1];
        $fn     = $hit[2];
        if (isset($seen[$action])) {
            fwrite(STDERR, "fatal: duplicate action '$action' in $path\n");
            exit(1);
        }
        $seen[$action] = true;
        $out[] = ['action' => $action, 'fn' => $fn];
    }

    if ($out === []) {
        fwrite(STDERR, "fatal: no Api::register() calls matched in $path\n");
        exit(1);
    }
    return $out;
}

/**
 * Walk every handler PHP file and collect docblocks attached to each
 * `function api_xxx(...)`. The returned map is keyed by function name and
 * holds the rendered `@param`/`@return` lines verbatim — we don't attempt
 * to parse phpDocumentor types into TS types. JSDoc accepts the same
 * `array{key: type, ...}` syntax as phpstan, and even when it doesn't the
 * JSDoc block still serves as readable documentation for the JS author.
 *
 * @return array<string, array{summary: string, params: list<array{name:string,type:string}>, return: ?string}>
 */
function collectHandlerDocs(string $dir): array
{
    if (!is_dir($dir)) {
        fwrite(STDERR, "fatal: handler dir not found: $dir\n");
        exit(1);
    }

    $files = glob($dir . '/*.php') ?: [];
    sort($files); // deterministic walk
    $out = [];

    foreach ($files as $file) {
        $src = (string)file_get_contents($file);

        // Match: /** ... */ \n function api_xxx_yyy(array $params): array
        // The docblock body is captured verbatim; we strip it apart below.
        $rx = '~/\*\*\s*\n((?:[^*]|\*(?!/))*?)\*/\s*\n\s*function\s+(api_[a-zA-Z0-9_]+)\s*\(~';
        if (preg_match_all($rx, $src, $m, PREG_SET_ORDER) === false) {
            continue;
        }
        foreach ($m as $hit) {
            $fn  = $hit[2];
            $doc = $hit[1];
            $out[$fn] = parseDocblock($doc);
        }
    }

    ksort($out);
    return $out;
}

/**
 * Strip the leading " * " from every line and split out the summary,
 * "\@param" entries, and "\@return" entry. Multi-line entries (e.g. expanded
 * `array{}` shapes) are joined with single spaces — JSDoc is forgiving
 * about whitespace inside braces.
 *
 * @return array{summary: string, params: list<array{name:string,type:string}>, return: ?string}
 */
function parseDocblock(string $raw): array
{
    $lines = preg_split('/\R/', $raw) ?: [];
    $clean = [];
    foreach ($lines as $ln) {
        $ln = preg_replace('/^\s*\*\s?/', '', $ln) ?? $ln;
        $clean[] = rtrim($ln);
    }

    $summary = '';
    $params  = [];
    $return  = null;

    $i = 0;
    while ($i < count($clean) && !preg_match('/^@/', $clean[$i])) {
        $summary .= ($summary === '' ? '' : ' ') . trim($clean[$i]);
        $i++;
    }
    $summary = trim($summary);

    while ($i < count($clean)) {
        $line = $clean[$i];
        if (preg_match('/^@param\s+(\S+)\s+\$(\w+)/', $line, $pm)) {
            $type = $pm[1];
            $name = $pm[2];
            $i++;
            while ($i < count($clean) && $clean[$i] !== '' && !preg_match('/^@/', $clean[$i])) {
                $type .= ' ' . trim($clean[$i]);
                $i++;
            }
            $params[] = ['name' => $name, 'type' => trim($type)];
            continue;
        }
        if (preg_match('/^@return\s+(.+)$/', $line, $rm)) {
            $type = $rm[1];
            $i++;
            while ($i < count($clean) && $clean[$i] !== '' && !preg_match('/^@/', $clean[$i])) {
                $type .= ' ' . trim($clean[$i]);
                $i++;
            }
            $return = trim($type);
            continue;
        }
        $i++;
    }

    return ['summary' => $summary, 'params' => $params, 'return' => $return];
}

/** @return array<string, int|float> */
function loadPerms(string $path): array
{
    if (!is_file($path)) {
        fwrite(STDERR, "fatal: perms file not found: $path\n");
        exit(1);
    }
    $raw     = (string)file_get_contents($path);
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        fwrite(STDERR, "fatal: $path is not valid JSON\n");
        exit(1);
    }

    $out = [];
    /** @var mixed $perm */
    foreach ($decoded as $name => $perm) {
        if (!is_string($name) || !is_array($perm) || !array_key_exists('value', $perm)) {
            continue;
        }
        $val = $perm['value'];
        if (!is_int($val) && !is_float($val)) {
            // Skip string-valued flags (sourcemod side); we only export web flags.
            continue;
        }
        $out[$name] = $val;
    }

    ksort($out);
    return $out;
}

/**
 * Convert "auth.login" -> "AuthLogin", "bans.add_comment" -> "BansAddComment".
 */
function pascalCaseFromAction(string $action): string
{
    $parts = preg_split('/[._]/', $action) ?: [];
    $out   = '';
    foreach ($parts as $p) {
        if ($p === '') {
            continue;
        }
        $out .= strtoupper(substr($p, 0, 1)) . substr($p, 1);
    }
    return $out;
}

/**
 * @param list<array{action:string,fn:string}> $actions
 * @param array<string, array{summary:string,params:list<array{name:string,type:string}>,return:?string}> $docs
 */
function countDocumented(array $actions, array $docs): int
{
    $n = 0;
    foreach ($actions as $a) {
        if (isset($docs[$a['fn']]) && $docs[$a['fn']]['return'] !== null) {
            $n++;
        }
    }
    return $n;
}

/**
 * @param list<array{action:string,fn:string}> $actions
 * @param array<string, array{summary:string,params:list<array{name:string,type:string}>,return:?string}> $docs
 * @param array<string, int|float> $perms
 */
function renderContract(array $actions, array $docs, array $perms): string
{
    // Stable, alphabetical key order so re-runs with the same inputs produce
    // a byte-identical file.
    $byKey = [];
    foreach ($actions as $entry) {
        $key = pascalCaseFromAction($entry['action']);
        if (isset($byKey[$key])) {
            fwrite(STDERR, "fatal: PascalCase collision for key '$key' "
                . "(actions '{$byKey[$key]['action']}' and '{$entry['action']}')\n");
            exit(1);
        }
        $byKey[$key] = $entry;
    }
    ksort($byKey);

    $out  = "// AUTOGENERATED FILE — DO NOT EDIT.\n";
    $out .= "// Regenerate with `composer api-contract` (or `./sbpp.sh composer api-contract`).\n";
    $out .= "// Sources: web/api/handlers/_register.php, web/api/handlers/*.php, web/configs/permissions/web.json\n";
    $out .= "//\n";
    $out .= "// Note: most Api{Key}Request/Response typedefs below are placeholder\n";
    $out .= "// `Object` shapes. The generator only emits a tight type when the PHP\n";
    $out .= "// handler carries a `@param`/`@return` docblock; otherwise it falls back\n";
    $out .= "// to `Object` so the typedef name still resolves. Tightening individual\n";
    $out .= "// handlers is a follow-up — see web/bin/generate-api-contract.php.\n";
    $out .= "// @ts-check\n";
    $out .= "\n";

    // ---- typedefs ---------------------------------------------------------
    // One Api{Key}Request / Api{Key}Response typedef per action so call sites
    // can opt into `/** @type {ApiAdminsAddRequest} */` annotations as the
    // handler corpus picks up docblocks over time. Handlers without a
    // docblock fall back to a permissive `Object` so the names always
    // resolve and tsc never has to special-case missing types.
    $out .= "/* === Generated typedefs ============================================= */\n\n";
    foreach ($byKey as $key => $entry) {
        $doc = $docs[$entry['fn']] ?? null;
        $hasDoc = $doc !== null && ($doc['summary'] !== '' || $doc['params'] !== [] || $doc['return'] !== null);
        $reqType  = $hasDoc ? renderParamsType($doc['params']) : null;
        $respType = ($hasDoc && $doc['return'] !== null) ? phpTypeToJsdoc($doc['return']) : 'Object';

        $out .= "/**\n";
        if ($hasDoc && $doc['summary'] !== '') {
            $wrapped = wordwrap($doc['summary'], 76, "\n", true);
            foreach (explode("\n", $wrapped) as $line) {
                $out .= " * " . $line . "\n";
            }
            $out .= " *\n";
        }
        $out .= " * @typedef {" . ($reqType ?? 'Object') . "} Api{$key}Request\n";
        $out .= " * @typedef {{$respType}} Api{$key}Response\n";
        $out .= " */\n";
    }
    $out .= "\n";

    // ---- Actions ----------------------------------------------------------
    $out .= "/**\n";
    $out .= " * Action names accepted by sb.api.call(). Keys are PascalCase derived from\n";
    $out .= " * the dotted action ('admins.remove' -> 'AdminsRemove').\n";
    $out .= " */\n";
    $out .= "var Actions = Object.freeze({\n";
    foreach ($byKey as $key => $entry) {
        $out .= "    " . $key . ": " . phpToJsString($entry['action']) . ",\n";
    }
    $out .= "});\n\n";

    // ---- Perms ------------------------------------------------------------
    $out .= "/**\n";
    $out .= " * Web permission bitmask flags. Values mirror configs/permissions/web.json\n";
    $out .= " * verbatim — keep PHP names so cross-language searches still find both.\n";
    $out .= " */\n";
    $out .= "var Perms = Object.freeze({\n";
    foreach ($perms as $name => $value) {
        // JS Number can represent integers up to 2^53-1 exactly; every flag
        // we emit fits in 32 bits, so direct emission is safe.
        $out .= "    " . $name . ": " . formatNumber($value) . ",\n";
    }
    $out .= "});\n\n";

    // ---- Globals (browser) ------------------------------------------------
    // The panel loads scripts as classic <script> tags, not modules. Attach
    // to globalThis explicitly so type-checking (#1098) can resolve the
    // symbols across files even when bundlers / strict-mode ESM hosts
    // deviate from "top-level var = window.var".
    $out .= "if (typeof globalThis !== 'undefined') {\n";
    $out .= "    globalThis.Actions = Actions;\n";
    $out .= "    globalThis.Perms   = Perms;\n";
    $out .= "}\n";

    return $out;
}

/**
 * Render a list of @param entries as a JSDoc object literal type:
 *   `{aid: number, password: string}`
 *
 * Returns null if there are no params (we still emit the response typedef).
 *
 * @param list<array{name:string,type:string}> $params
 */
function renderParamsType(array $params): ?string
{
    if ($params === []) {
        return null;
    }
    $bits = [];
    foreach ($params as $p) {
        $bits[] = $p['name'] . ': ' . phpTypeToJsdoc($p['type']);
    }
    return '{' . implode(', ', $bits) . '}';
}

/**
 * Best-effort translation of a phpDocumentor / phpstan type expression to a
 * JSDoc-compatible type. We handle the cases that actually appear in the
 * handler corpus today (primitives, `array{...}` shapes, `array<int, T>`
 * lists, `list<T>`, `?T` nullables, union literals). Anything we can't
 * confidently map degrades to `Object` so JSDoc parsers don't choke and
 * tsc still runs cleanly.
 */
function phpTypeToJsdoc(string $type): string
{
    $t = trim($type);

    // array{ ... } -> { ... } (object-shape literal). PhpDoc allows a
    // trailing comma inside the braces; JSDoc/TS does not.
    $t = (string)preg_replace('/\barray\s*\{/', '{', $t);
    $t = (string)preg_replace('/,(\s*[}>])/', '$1', $t);

    // Sequence types: array<int, T> / array<T> / list<T>  -> Array<T>
    $t = (string)preg_replace('/\barray\s*<\s*int\s*,\s*/', 'Array<', $t);
    $t = (string)preg_replace('/\barray\s*<\s*/', 'Array<', $t);
    $t = (string)preg_replace('/\blist\s*</', 'Array<', $t);

    // Bare `array` (no shape) -> `Object`.
    $t = (string)preg_replace('/\barray\b(?!\s*[<{])/', 'Object', $t);

    // Primitives.
    $t = (string)preg_replace('/\bint\b/', 'number', $t);
    $t = (string)preg_replace('/\bfloat\b/', 'number', $t);
    $t = (string)preg_replace('/\bbool\b/', 'boolean', $t);
    $t = (string)preg_replace('/\bmixed\b/', 'unknown', $t);

    // ?T -> (T | null).  Match the smallest balanced expression after the ?
    // so `?array<int, T>` keeps the generic intact.
    $t = (string)preg_replace_callback('/\?([A-Za-z_][\w<>{}\[\]\s,:|\']*)/u', function ($m): string {
        return '(' . trim($m[1]) . ' | null)';
    }, $t);

    // Collapse stray double spaces from substitutions.
    $t = (string)preg_replace('/\s+/', ' ', $t);

    return trim($t);
}

function phpToJsString(string $s): string
{
    // The action set is restricted to [a-z0-9._] so a single-quoted JS
    // literal is always safe; just guard against future surprises.
    return "'" . str_replace(["\\", "'"], ["\\\\", "\\'"], $s) . "'";
}

function formatNumber(int|float $n): string
{
    if (is_int($n)) {
        return (string)$n;
    }
    if ($n === floor($n) && $n >= 0 && $n < (2 ** 53)) {
        return (string)(int)$n;
    }
    return rtrim(rtrim(sprintf('%.20f', $n), '0'), '.');
}
