<?php

declare(strict_types=1);

namespace Sbpp\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Issue #1270 (drive-by) regression suite for `\getDirSize()` and the
 * underlying `\getDirSizeBytes()` recursion in
 * `web/includes/system-functions.php`.
 *
 * Pre-#1270, `getDirSize()` was a single function that recursed into
 * itself: each recursive call returned a `sizeFormat()` string (`"4.00 kB"`)
 * which the caller then `+=`'d back into a numeric `$size` accumulator.
 * Under PHP 8 strict numerics, that path:
 *
 *   1. Triggered an `E_WARNING` ("non-numeric value encountered") on the
 *      first nested directory — `failOnWarning` would have caught it had
 *      this helper had a test, which it didn't.
 *   2. Silently undercounted any tree with nested subdirectories,
 *      because `(int)"4.00 kB"` is `4` (the leading digits before the
 *      space) — i.e. a `web/demos/<server>/<demo>.dem` layout would
 *      report wildly wrong sizes the moment a per-server subdirectory
 *      existed.
 *
 * The fix split the helper in two:
 *
 *   - `getDirSizeBytes($dir): int` — pure-int recursion. Each level
 *     returns a typed `int` so callers (including the recursive call
 *     itself) can `+=` without coercion. This is the source of truth.
 *   - `getDirSize($dir): string` — thin formatting wrapper that calls
 *     `getDirSizeBytes()` once and pipes the result through
 *     `sizeFormat()` for display. The public signature is unchanged
 *     so callers (`web/pages/page.admin.php`, third-party theme forks)
 *     keep working without edits.
 *
 * These cases pin both halves of the contract:
 *
 *   - **Bytes path** — empty, single-file, sibling files, nested
 *     subdirs, deeply nested. The nested cases are the regression
 *     guard for the silent undercount.
 *   - **String wrapper** — the same dirs render through `sizeFormat()`,
 *     and `getDirSize()` returns exactly what `sizeFormat(getDirSizeBytes(…))`
 *     would. Pins the wrapper hasn't drifted from the recursion.
 *
 * The whole suite operates on a per-test temp tree under
 * `sys_get_temp_dir()` so it's hermetic — no fixture, no DB, no
 * docker-mounted `web/demos/` (which is shared across stacks and
 * could be polluted by other tests).
 */
final class SystemFunctionsTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir() . '/sbpp-getdirsize-test-' . uniqid('', true);
        if (!mkdir($this->root, 0o775, true) && !is_dir($this->root)) {
            self::fail("setUp could not create temp root {$this->root}");
        }
    }

    protected function tearDown(): void
    {
        // Recursive cleanup. Always runs even if a test failed mid-write
        // so the next run gets a clean slate. We control everything
        // under $this->root, so the unlink/rmdir loop is bounded by what
        // the tests themselves wrote.
        $this->rmrf($this->root);
        parent::tearDown();
    }

    /**
     * Empty directory → 0 bytes / `'0 B'`. The boundary case the
     * `sizeFormat()` early-return is for: `log(0, 1024)` is `-INF`,
     * which would otherwise crash the format step.
     */
    public function testEmptyDirIsZero(): void
    {
        $this->assertSame(0,     getDirSizeBytes($this->root));
        $this->assertSame('0 B', getDirSize($this->root));
    }

    /**
     * Single-file directory. The formatter chooses the smallest unit
     * `<1 kB`; the `' B'` suffix is what the legacy v1.x admin landing
     * was rendering for fresh installs with no demos retained.
     */
    public function testSingleFile(): void
    {
        $this->writeFile('one.txt', 'abc');

        $this->assertSame(3,     getDirSizeBytes($this->root));
        $this->assertSame('3 B', getDirSize($this->root));
    }

    /**
     * Multiple sibling files in the same directory. No recursion in
     * play yet — this case worked even on the legacy single-function
     * shape; included as the regression baseline so the nested cases
     * below have something to compare against.
     */
    public function testMultipleSiblingFiles(): void
    {
        $this->writeFile('a.txt', str_repeat('x', 100));
        $this->writeFile('b.txt', str_repeat('y', 200));
        $this->writeFile('c.txt', str_repeat('z', 300));

        $this->assertSame(600, getDirSizeBytes($this->root));
        $this->assertSame('600 B', getDirSize($this->root));
    }

    /**
     * The canonical regression case: one file at the root + a nested
     * subdirectory containing more files. This is the shape that
     * actually exists on production installs (`web/demos/<server>/<demo>.dem`)
     * and the one the legacy single-function shape silently
     * undercounted.
     *
     * Pre-#1270: the recursive call returned `sizeFormat(N)` (a string
     * like `"500 B"`), and `(int)"500 B"` is `500`, but `(int)"1.5 kB"`
     * is `1` — so as soon as a subdir's total crossed a unit boundary
     * the bytes silently dropped. With three subdirs each above 1 kB,
     * the legacy code would report ~3 bytes for a tree containing
     * thousands.
     *
     * Post-#1270: `getDirSizeBytes()` returns a strict int at every
     * level so the running sum can't be poisoned by a unit suffix.
     */
    public function testNestedSubdirIsSummedCorrectly(): void
    {
        $this->writeFile('root.txt', str_repeat('a', 50));

        mkdir($this->root . '/sub');
        $this->writeFile('sub/x.txt', str_repeat('b', 1500));
        $this->writeFile('sub/y.txt', str_repeat('c', 2500));

        $this->assertSame(
            50 + 1500 + 2500,
            getDirSizeBytes($this->root),
            'nested-subdir sum must include both root files AND every file under each subdirectory; '
                . 'the pre-#1270 single-function shape silently undercounted because the recursive call '
                . 'returned a `sizeFormat()` string and `(int)"4.00 kB"` is `4`, not `4096`.'
        );

        $this->assertSame(
            sizeFormat(50 + 1500 + 2500),
            getDirSize($this->root),
            'getDirSize must return exactly sizeFormat(getDirSizeBytes(…)) — no drift between the wrapper '
                . 'and the underlying recursion.'
        );
    }

    /**
     * Deeply nested (six levels) tree with a single leaf file. Pins
     * that the recursion bottoms out cleanly and that the typed-int
     * return travels back up six frames without any string coercion
     * along the way. The legacy shape would have lost the bytes at
     * each unit-boundary frame.
     */
    public function testDeeplyNestedSingleFileBubblesUpUnchanged(): void
    {
        $deepDir = 'a/b/c/d/e/f';
        if (!mkdir($this->root . '/' . $deepDir, 0o775, true)) {
            self::fail("could not create nested dir {$deepDir}");
        }
        $this->writeFile($deepDir . '/leaf.bin', str_repeat("\0", 4096));

        $this->assertSame(4096, getDirSizeBytes($this->root));
        $this->assertSame('4 kB', getDirSize($this->root));
    }

    /**
     * Multiple sibling subdirectories. The pre-#1270 shape failed
     * specifically when more than one subdirectory existed at the same
     * level, because each subdir's `sizeFormat()` string was concatenated
     * (via `+=`) into the running int. Three sibling subdirs each above
     * the kB / MB boundary make the silent-undercount bug visually
     * obvious in failure mode.
     */
    public function testMultipleSiblingSubdirsSumIndependently(): void
    {
        foreach (['srv-a', 'srv-b', 'srv-c'] as $i => $name) {
            mkdir($this->root . '/' . $name);
            // Each subdir > 1 kB so the legacy single-function shape would
            // hit the unit-boundary undercount on each one.
            $this->writeFile($name . '/demo.dem', str_repeat('.', 2048 + $i * 1024));
        }

        $this->assertSame(
            2048 + (2048 + 1024) + (2048 + 2048),
            getDirSizeBytes($this->root),
            'three sibling subdirs each > 1 kB; legacy shape would have silently lost the kB-boundary bytes.'
        );
    }

    /**
     * Mixed tree — root file + nested subdir + sibling subdirs at the
     * same level. The end-to-end shape that actually exists on a
     * production `web/demos/` directory.
     */
    public function testMixedTreeFiles(): void
    {
        $this->writeFile('top.txt', str_repeat('A', 1));

        mkdir($this->root . '/dirA');
        $this->writeFile('dirA/inside.txt', str_repeat('B', 10));

        mkdir($this->root . '/dirB');
        mkdir($this->root . '/dirB/nested');
        $this->writeFile('dirB/nested/deep.txt', str_repeat('C', 100));
        $this->writeFile('dirB/peer.txt',        str_repeat('D', 1000));

        $expected = 1 + 10 + 100 + 1000;
        $this->assertSame($expected, getDirSizeBytes($this->root));
        $this->assertSame(sizeFormat($expected), getDirSize($this->root));
    }

    /**
     * Convenience: write `$contents` to `$rel` (path relative to
     * `$this->root`), creating any missing parent directories
     * implicitly via the test author's explicit mkdir calls above.
     * Bails on disk failure so a flaky tmpfs doesn't manifest as a
     * confusing assertion error downstream.
     */
    private function writeFile(string $rel, string $contents): void
    {
        $path = $this->root . '/' . $rel;
        if (file_put_contents($path, $contents) === false) {
            self::fail("could not write fixture file {$path}");
        }
    }

    /**
     * Recursive rm. Walks $dir bottom-up so unlink/rmdir never trip
     * "directory not empty". Idempotent — silently no-ops if $dir was
     * never created (e.g. setUp() bailed before mkdir).
     */
    private function rmrf(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        if ($items === false) {
            return;
        }
        foreach ($items as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }
            $path = $dir . '/' . $name;
            if (is_dir($path) && !is_link($path)) {
                $this->rmrf($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }
}
