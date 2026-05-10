<?php

declare(strict_types=1);

namespace Sbpp\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sbpp\Version;

/**
 * Regression suite for `\Sbpp\Version::resolve()` — the three-tier
 * version resolution that drives `SB_VERSION`, the chrome footer, and
 * the `data-version="…"` attribute (#1207 CC-5).
 *
 * Pre-#1207 the third-tier fallback was the literal `'N/A'`, which read
 * like a runtime error in the footer when no `configs/version.json` was
 * shipped (dev) AND `git describe` came back empty (also dev — the
 * docker image doesn't ship a `git` binary and `.git` isn't bind-
 * mounted into the web container). The fix swaps the literal for the
 * `'dev'` sentinel so dev installs read as "you're running from
 * source, not a tarball" instead of "something is broken".
 *
 * These cases pin every branch of the fallback so a future refactor
 * can't silently regress to `'N/A'` (or worse, `''` / `null`).
 */
final class VersionTest extends TestCase
{
    /**
     * Tier 1 — release tarball case: `configs/version.json` exists and
     * decodes to a pair. The resolver returns the JSON contents
     * verbatim. (#1214 dropped the legacy `dev: bool` field; consumers
     * now branch on `version === Version::DEV_SENTINEL` or on `git`
     * directly, never on a separate boolean.)
     */
    public function testTarballJsonWins(): void
    {
        $resolved = Version::resolve(
            versionJsonPath: '/whatever',
            jsonReader: static fn (): array => [
                'version' => '2.1.0',
                'git'     => 'abc1234',
            ],
            // Both git callbacks must be inert when JSON wins; assert that
            // by erroring if they fire.
            gitDescribe: static fn (): string => self::fail('git describe must not run when version.json resolves'),
            gitShortRev: static fn (): string => self::fail('git rev-parse must not run when version.json resolves'),
        );

        $this->assertSame('2.1.0',   $resolved['version']);
        $this->assertSame('abc1234', $resolved['git']);
    }

    /**
     * Tier 2 — git checkout case: no `version.json`, but `git describe`
     * returns a tag and `git rev-parse` returns a sha. Both feed into
     * the result; the dev-checkout signal is implicit in the describe
     * string (`-N-g<sha>` suffix) — no separate boolean is carried.
     */
    public function testGitDescribeUsedWhenJsonAbsent(): void
    {
        $resolved = Version::resolve(
            versionJsonPath: '/whatever',
            jsonReader: static fn (): ?array => null,
            gitDescribe: static fn (): string => "v2.0.0-3-gabc1234\n",
            gitShortRev: static fn (): string => "abc1234\n",
        );

        $this->assertSame('v2.0.0-3-gabc1234', $resolved['version']);
        $this->assertSame('abc1234',           $resolved['git']);
    }

    /**
     * Tier 2 mid-state: `git rev-parse --short HEAD` returns a sha but
     * `git describe` is empty (rare but possible — repo has no tags
     * yet, or `--always` would be needed for a sha-only describe).
     * The sentinel takes the `version` slot; the sha still lands.
     */
    public function testGitShaWithoutTagFallsBackToDevSentinel(): void
    {
        $resolved = Version::resolve(
            versionJsonPath: '/whatever',
            jsonReader: static fn (): ?array => null,
            gitDescribe: static fn (): string => '',
            gitShortRev: static fn (): string => "abc1234\n",
        );

        $this->assertSame(Version::DEV_SENTINEL, $resolved['version']);
        $this->assertSame('abc1234',             $resolved['git']);
    }

    /**
     * Tier 3 — the canonical dev-docker case: no `version.json`, no git
     * binary, no `.git` bind-mount. Pre-#1207 the resolver emitted
     * `'N/A'` here; the regression guard is that the sentinel is now
     * the project-defined `'dev'`. This is the case the chrome's
     * `<footer data-version="dev">` hook keys off.
     */
    public function testDevSentinelWhenNoSourceAvailable(): void
    {
        $resolved = Version::resolve(
            versionJsonPath: '/whatever',
            jsonReader: static fn (): ?array => null,
            gitDescribe: static fn (): string => '',
            gitShortRev: static fn (): string => '',
        );

        $this->assertSame(Version::DEV_SENTINEL, $resolved['version']);
        $this->assertSame(0,                     $resolved['git']);

        // Pin the literal too: the CC-5 contract is specifically that the
        // sentinel is `'dev'` (not `'unreleased'` / `'N/A'` / `''`).
        // Telemetry, bug-report templates, and the E2E spec for the
        // footer all key off this exact string.
        $this->assertSame('dev', Version::DEV_SENTINEL);
    }

    /**
     * Whitespace from `shell_exec`'s trailing newline is normalised. The
     * resolver `trim()`s both git outputs so the resulting `version`
     * never carries a trailing newline that would mangle the footer or
     * the `data-version` attribute.
     */
    public function testGitOutputIsTrimmed(): void
    {
        $resolved = Version::resolve(
            versionJsonPath: '/whatever',
            jsonReader: static fn (): ?array => null,
            gitDescribe: static fn (): string => "  v2.0.0  \n",
            gitShortRev: static fn (): string => "  abc1234  \n",
        );

        $this->assertSame('v2.0.0',  $resolved['version']);
        $this->assertSame('abc1234', $resolved['git']);
    }

    /**
     * Robustness — an unreadable / missing `version.json` resolves to
     * the JSON-tier returning `null`, not a fatal error. Pairs with
     * `is_readable()` in the default reader; this test pins the same
     * shape via the injected callback so the resolver's branches are
     * exercised even if `is_readable()` ever changes behaviour.
     */
    public function testMissingVersionJsonFallsThrough(): void
    {
        $resolved = Version::resolve(
            versionJsonPath: '/path/that/does/not/exist',
            jsonReader: static fn (string $path): ?array => null,
            gitDescribe: static fn (): string => '',
            gitShortRev: static fn (): string => '',
        );

        $this->assertSame(Version::DEV_SENTINEL, $resolved['version']);
    }

    /**
     * Issue #1305 — the canonical "v1.x file preserved through a
     * v1→v2 upgrade overlay" case. The v1.x repo carried
     * `web/configs/version.json` as a checked-in, hand-edited file
     * with `{"version": "1.8.1", "git": "1434"}`; that file was
     * deleted from `main` in #1070 (commit `9d1caefd`, May 2 2026)
     * with the release workflow taking over file ownership at build
     * time. An operator who upgraded a v1.8 install to v2.0 with a
     * "skip if exists" overlay tool (FTP, `rsync` without `--delete`,
     * a manual directory-by-directory copy that treats `configs/` as
     * user data) keeps the stale file on disk; pre-#1305 the
     * resolver returned that file's contents verbatim and the chrome
     * footer read `SourceBans++ 1.8.1 | Git: 1434` on a v2.0 install.
     *
     * The fix: tier-1 input is gated by the major-component floor
     * (`Version::MIN_TIER1_MAJOR`). Anything below the floor falls
     * through to tier-2 (`git describe`) so the operator sees the
     * actual codebase version (or the `'dev'` sentinel — see the
     * sibling test below — instead of phantom v1.x copy.
     */
    public function testStaleV1JsonFallsThroughToGitDescribe(): void
    {
        $resolved = Version::resolve(
            versionJsonPath: '/whatever',
            jsonReader: static fn (): array => [
                'version' => '1.8.1',
                'git'     => '1434',
            ],
            gitDescribe: static fn (): string => "v2.0.0\n",
            gitShortRev: static fn (): string => "abc1234\n",
        );

        $this->assertSame('v2.0.0',  $resolved['version']);
        $this->assertSame('abc1234', $resolved['git']);
    }

    /**
     * Companion to the test above — same stale-tier-1 case but on a
     * production install where git isn't available (no `.git` dir,
     * no `git` binary in the image). The fall-through cascade lands
     * at tier-3, the `'dev'` sentinel. That's not a perfect outcome
     * (the operator sees `dev` instead of the actual `2.0.0` they
     * deployed), but it's a self-describing signal that something
     * is wrong with the install metadata — and crucially, it's NOT
     * the phantom v1.x string that telemetry / bug reports / E2E
     * specs would key off.
     */
    public function testStaleV1JsonFallsThroughToDevSentinel(): void
    {
        $resolved = Version::resolve(
            versionJsonPath: '/whatever',
            jsonReader: static fn (): array => [
                'version' => '1.8.1',
                'git'     => '1434',
            ],
            gitDescribe: static fn (): string => '',
            gitShortRev: static fn (): string => '',
        );

        $this->assertSame(Version::DEV_SENTINEL, $resolved['version']);
        $this->assertSame(0,                     $resolved['git']);
    }

    /**
     * Boundary — a tier-1 file exactly at the floor (`2.0.0`) is
     * accepted. The release workflow writes the git tag verbatim, so
     * the v2.0.0 tarball's `version.json` reads `"version": "2.0.0"`
     * exactly — that's the case this guards.
     */
    public function testTarballJsonAtFloorMajorIsAccepted(): void
    {
        $resolved = Version::resolve(
            versionJsonPath: '/whatever',
            jsonReader: static fn (): array => [
                'version' => '2.0.0',
                'git'     => 'abc1234',
            ],
            gitDescribe: static fn (): string => self::fail('git describe must not run when JSON tier-1 is acceptable'),
            gitShortRev: static fn (): string => self::fail('git rev-parse must not run when JSON tier-1 is acceptable'),
        );

        $this->assertSame('2.0.0',   $resolved['version']);
        $this->assertSame('abc1234', $resolved['git']);
    }

    /**
     * Pre-release tags within the current major (`2.0.0-rc.1`,
     * `2.1.0-beta.3`) are accepted. The release.yml regex allows
     * `^[0-9]+\.[0-9]+\.[0-9]+(-[0-9A-Za-z.-]+)?...` so a v2.0.0-rc.1
     * tarball is a real shape. Compared to a strict
     * `version_compare(jsonVersion, '2.0.0', '>=')` floor — which
     * would reject `2.0.0-rc.1` because PHP / semver sort
     * pre-release identifiers BEFORE the release — major-only
     * comparison correctly admits them.
     */
    public function testTarballJsonPreReleaseWithinCurrentMajorIsAccepted(): void
    {
        $resolved = Version::resolve(
            versionJsonPath: '/whatever',
            jsonReader: static fn (): array => [
                'version' => '2.0.0-rc.1',
                'git'     => 'abc1234',
            ],
            gitDescribe: static fn (): string => self::fail('git describe must not run when pre-release JSON is acceptable'),
            gitShortRev: static fn (): string => self::fail('git rev-parse must not run when pre-release JSON is acceptable'),
        );

        $this->assertSame('2.0.0-rc.1', $resolved['version']);
        $this->assertSame('abc1234',    $resolved['git']);
    }

    /**
     * Forward-compat — a future major (3.x.y) is accepted without a
     * code change here. The floor is a *minimum*, not a *match*;
     * future v3 tarballs are perfectly trustworthy on the current v2
     * codebase as long as the deployment is consistent (which is the
     * operator's contract — the floor is here to catch the *backward*
     * mismatch that #1305 surfaces, not to enforce same-major).
     */
    public function testTarballJsonFutureMajorIsAccepted(): void
    {
        $resolved = Version::resolve(
            versionJsonPath: '/whatever',
            jsonReader: static fn (): array => [
                'version' => '3.0.0',
                'git'     => 'def5678',
            ],
            gitDescribe: static fn (): string => self::fail('git describe must not run when forward-compat JSON is acceptable'),
            gitShortRev: static fn (): string => self::fail('git rev-parse must not run when forward-compat JSON is acceptable'),
        );

        $this->assertSame('3.0.0',   $resolved['version']);
        $this->assertSame('def5678', $resolved['git']);
    }

    /**
     * Defensive — a malformed `version` field (empty string, free
     * text, the `'dev'` sentinel itself somehow slipping into the
     * JSON, a hand-edited "unknown" placeholder) doesn't match the
     * `^v?\d+` shape and falls through. The release.yml regex
     * enforces full semver on the build tag so a real tarball can
     * never produce these, but operator hand-edits and corrupted
     * JSON are real-world failure modes worth pinning.
     */
    #[DataProvider('provideMalformedTier1Versions')]
    public function testTarballJsonMalformedFallsThrough(string $malformedVersion): void
    {
        $resolved = Version::resolve(
            versionJsonPath: '/whatever',
            jsonReader: static fn () => [
                'version' => $malformedVersion,
                'git'     => 'abc1234',
            ],
            gitDescribe: static fn (): string => 'v2.0.0',
            gitShortRev: static fn (): string => 'def5678',
        );

        $this->assertSame('v2.0.0',  $resolved['version'], "malformed tier-1 version '$malformedVersion' should fall through to tier-2");
        $this->assertSame('def5678', $resolved['git']);
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function provideMalformedTier1Versions(): iterable
    {
        yield 'empty string'                  => [''];
        yield 'unknown placeholder'           => ['unknown'];
        yield 'dev sentinel leaked into JSON' => ['dev'];
        yield 'free-text edit'                => ['SourceBans++'];
        yield 'leading dot'                   => ['.0.0'];
        yield 'wrong prefix'                  => ['ver1.8.1'];
    }

    /**
     * Pin the floor constant explicitly. A future bump of the floor
     * (say, when v3.0 ships and the maintainer wants to start
     * rejecting v2.x stale files in v3.x installs the same way #1305
     * rejects v1.x in v2.x) shows up here as a deliberate test edit
     * rather than a silent constant change. Keeps the rationale
     * paired with the bump.
     */
    public function testFloorConstantIsTwo(): void
    {
        $this->assertSame(2, Version::MIN_TIER1_MAJOR);
    }
}
