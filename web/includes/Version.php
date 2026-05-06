<?php
declare(strict_types=1);

namespace Sbpp;

/**
 * Three-tier version resolution for the panel chrome (#1207 CC-5).
 *
 * Sources, in order:
 *
 *   1. `configs/version.json` — emitted into release tarballs by the
 *      release pipeline. Self-hosters who installed by unzipping a tarball
 *      always hit this branch.
 *   2. `git describe --tags --always` (with `git rev-parse --short HEAD`
 *      as a sibling) — covers operators running directly off a git
 *      checkout outside Docker. Returns a tag (e.g. `2.1.0`) when
 *      HEAD is exactly on one and a `<tag>-<n>-g<sha>` describe string
 *      otherwise.
 *   3. The literal sentinel `'dev'` — the third-tier fallback. Surfaces
 *      when both higher tiers resolved empty: typically the dev docker
 *      container (no `.git` bind-mount, no `git` binary in the image)
 *      or any operator running from a non-tarball source where git
 *      isn't available.
 *
 * Pre-#1207 the third tier was the literal `'N/A'`, which read like a
 * runtime error in the footer and confused operators. `'dev'` is a
 * self-describing sentinel; the chrome's `<footer data-version="…">`
 * hook (see `web/themes/default/core/footer.tpl`) lets telemetry and
 * E2E specs distinguish dev installs from release tarball installs
 * without parsing the user-visible string.
 *
 * Pure helper: no DB, no Smarty. Side-effect-free except for `shell_exec`
 * to git. The class exists so PHPUnit can lock the fallback contract
 * without `defined('SB_VERSION')` blowing up against bootstrap-time
 * constants.
 */
final class Version
{
    public const DEV_SENTINEL = 'dev';

    /**
     * Resolve the version pair `[version, git]` exactly the way
     * `init.php` consumes it for the `SB_VERSION` / `SB_GITREV`
     * constants.
     *
     * The `'dev'` *sentinel string* in the `version` slot is the
     * canonical way to identify a dev-checkout panel (#1207 CC-5);
     * an out-of-band `dev: bool` field used to live alongside it but
     * was dropped in #1214 — every consumer now branches on either
     * `SB_VERSION === self::DEV_SENTINEL` for the "is this a dev
     * build?" question or on `SB_GITREV` directly for the "do we
     * have a SHA to print?" question. Carrying a separate boolean
     * was redundant once `system.check_version` stopped gating on
     * it (the gated branch had already gone obsolete because it
     * compared a numeric git rev that no longer exists).
     *
     * @param  callable|null $jsonReader  fn(string $path): ?array — defaults to
     *                                    file_get_contents + json_decode.
     * @param  callable|null $gitDescribe fn(): string — defaults to shell_exec.
     * @param  callable|null $gitShortRev fn(): string — defaults to shell_exec.
     * @return array{version: string, git: int|string}
     */
    public static function resolve(
        string $versionJsonPath,
        ?callable $jsonReader = null,
        ?callable $gitDescribe = null,
        ?callable $gitShortRev = null,
    ): array {
        $jsonReader  ??= self::defaultJsonReader();
        $gitDescribe ??= self::defaultGitDescribe();
        $gitShortRev ??= self::defaultGitShortRev();

        $tarball = $jsonReader($versionJsonPath);
        if (is_array($tarball) && isset($tarball['version'])) {
            return [
                'version' => (string) $tarball['version'],
                'git'     => $tarball['git'] ?? 0,
            ];
        }

        $tag = trim($gitDescribe());
        $sha = trim($gitShortRev());
        if ($tag !== '' || $sha !== '') {
            return [
                'version' => $tag !== '' ? $tag : self::DEV_SENTINEL,
                'git'     => $sha,
            ];
        }

        return [
            'version' => self::DEV_SENTINEL,
            'git'     => 0,
        ];
    }

    /**
     * @return callable(string): ?array<string, mixed>
     */
    private static function defaultJsonReader(): callable
    {
        return static function (string $path): ?array {
            if (!is_readable($path)) {
                return null;
            }
            $raw = @file_get_contents($path);
            if ($raw === false || $raw === '') {
                return null;
            }
            $decoded = @json_decode($raw, true);
            return is_array($decoded) ? $decoded : null;
        };
    }

    /**
     * @return callable(): string
     */
    private static function defaultGitDescribe(): callable
    {
        return static fn (): string => (string) @shell_exec('git describe --tags --always 2>/dev/null');
    }

    /**
     * @return callable(): string
     */
    private static function defaultGitShortRev(): callable
    {
        return static fn (): string => (string) @shell_exec('git rev-parse --short HEAD 2>/dev/null');
    }
}
