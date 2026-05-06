<?php

declare(strict_types=1);

namespace Sbpp\Tests\Unit;

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
     * decodes to a triple. The resolver returns the JSON contents
     * verbatim; `dev` is whatever the JSON declares.
     */
    public function testTarballJsonWins(): void
    {
        $resolved = Version::resolve(
            versionJsonPath: '/whatever',
            jsonReader: static fn (): array => [
                'version' => '2.1.0',
                'git'     => 'abc1234',
                'dev'     => false,
            ],
            // Both git callbacks must be inert when JSON wins; assert that
            // by erroring if they fire.
            gitDescribe: static fn (): string => self::fail('git describe must not run when version.json resolves'),
            gitShortRev: static fn (): string => self::fail('git rev-parse must not run when version.json resolves'),
        );

        $this->assertSame('2.1.0',   $resolved['version']);
        $this->assertSame('abc1234', $resolved['git']);
        $this->assertFalse($resolved['dev']);
    }

    /**
     * Tier 2 — git checkout case: no `version.json`, but `git describe`
     * returns a tag and `git rev-parse` returns a sha. Both feed into
     * the result; `dev` is true (we're running off a checkout, not a
     * release tarball).
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
        $this->assertTrue($resolved['dev']);
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
        $this->assertTrue($resolved['dev']);
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
        $this->assertTrue($resolved['dev']);

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
}
