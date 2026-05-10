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
 *      always hit this branch. The file's major version is gated against
 *      `MIN_TIER1_MAJOR` so a stale v1.x file preserved through a
 *      botched v1→v2 overlay can never be trusted (#1305).
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
     * Floor for the tier-1 (`configs/version.json`) major component.
     *
     * The release pipeline (`.github/workflows/release.yml`) writes the
     * git tag verbatim into the tarball's `configs/version.json` at build
     * time, so a healthy tarball ALWAYS carries `version` aligned with
     * the codebase's current major. A file whose major is below this
     * floor is structurally stale — the canonical case (#1305) is the
     * v1.x repo's checked-in `{"version": "1.8.1", "git": "1434"}` file
     * surviving a v1.8 → v2.0 upgrade because the operator's overlay
     * tool ("FTP skip if exists", `rsync` without `--delete`, manual
     * directory copy that treats `configs/` as user data) preserved it.
     * The resolver rejects such files and falls through to tier-2 /
     * tier-3 so the footer reads a self-describing fallback (`dev` or
     * the live git describe string) instead of phantom v1.x copy.
     *
     * Bump this on every MAJOR release (3.0, 4.0, …) — patch and minor
     * releases stay below the same floor. Forgetting to bump it on a
     * future v3.0 silently re-opens the same bug pattern for v2.x
     * files in v3.x installs (the maintainer would see the symptom in
     * the bug report and bump it then), but the floor check is the
     * structural guard that means a stale tier-1 input can never win
     * silently regardless of what the upgrade overlay tool did.
     *
     * Major-only (vs. a full `version_compare` against `MIN_VERSION =
     * '2.0.0'`) is deliberate: pre-release tarballs like `2.0.0-rc.1`
     * sort BELOW `2.0.0` under PHP's `version_compare` (correctly per
     * semver's "pre-release < release" rule), so a strict
     * `version_compare(..., '2.0.0', '>=')` would silently reject every
     * v2.0.0-rc.* tarball install. Comparing the major component alone
     * accepts pre-release tags within the current major while still
     * rejecting any v1.x file. The release.yml regex enforces full
     * semver on the tag (`^[0-9]+\.[0-9]+\.[0-9]+...`), so a tier-1
     * file that doesn't even match `^v?\d+` is doubly suspect (manual
     * edit, empty / corrupt JSON) and falls through to tier-2.
     */
    public const MIN_TIER1_MAJOR = 2;

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
     * Tier-1 input is gated by `isAcceptableTier1Version()` so a
     * stale v1.x file (or a malformed entry) can't poison the chrome
     * footer and downstream consumers like the telemetry payload.
     * See `MIN_TIER1_MAJOR` for the rationale.
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
            $jsonVersion = (string) $tarball['version'];
            if (self::isAcceptableTier1Version($jsonVersion)) {
                return [
                    'version' => $jsonVersion,
                    'git'     => $tarball['git'] ?? 0,
                ];
            }
            // Stale / malformed tier-1 input: deliberately fall through
            // to tier-2 (git describe) and tier-3 (dev sentinel) below.
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
     * True when a tier-1 `configs/version.json` `version` field is
     * trustworthy enough to surface as `SB_VERSION`. Keys off
     * `MIN_TIER1_MAJOR` — see that constant's docblock for the full
     * rationale (#1305).
     *
     * Reject conditions (all collapse to "fall through to tier-2"):
     *   - `'1.8.1'` / `'1.x.y'` for any x, y — the canonical v1.x
     *     baked-in stale file from before #1070's deletion.
     *   - `'dev'` / `'unknown'` / `''` / any string that doesn't
     *     start with an optional `v` and a major digit — defensive
     *     against operator hand-edits and truncated / corrupt JSON.
     *   - A semver tag whose major is below the floor.
     *
     * Accept conditions:
     *   - Any version where the major component (after an optional
     *     `v` prefix) is `>= MIN_TIER1_MAJOR`. This lets pre-release
     *     tags within the current major (`2.0.0-rc.1`, `2.1.0-beta.3`)
     *     through, plus any future major (`3.0.0`, `4.5.2`) without a
     *     code change.
     */
    private static function isAcceptableTier1Version(string $version): bool
    {
        if (!preg_match('/^v?(\d+)/', $version, $m)) {
            return false;
        }

        return (int) $m[1] >= self::MIN_TIER1_MAJOR;
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
