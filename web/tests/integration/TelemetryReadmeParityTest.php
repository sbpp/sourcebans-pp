<?php

declare(strict_types=1);

namespace Sbpp\Tests\Integration;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Sbpp\Telemetry\Schema1;

/**
 * #1126 — README's `## Privacy & telemetry` field list must
 * deep-equal the vendored schema's leaf field set.
 *
 * Mechanism:
 *
 *   1. README wraps the field list in
 *      `<!-- TELEMETRY-FIELDS-START -->` /
 *      `<!-- TELEMETRY-FIELDS-END -->` HTML comments.
 *   2. Each line inside that block looks like
 *      `- \`scale.bans_active\` — short description.`
 *      The leading dash + backticked dot-path is the parser's
 *      anchor; the prose after the em-dash is descriptive copy
 *      free to evolve without breaking the test.
 *   3. The set extracted from the README is sorted and
 *      `assertSame`'d against `Schema1::payloadFieldNames()`.
 *
 * Drift symptom: someone adds a field to the schema lock file
 * but forgets to add the matching bullet to README → this test
 * fails. Or the reverse: someone documents a field that hasn't
 * landed in the lock file yet.
 *
 * The marker comments are load-bearing: do NOT remove them
 * without paired updates here.
 */
final class TelemetryReadmeParityTest extends TestCase
{
    private const README_PATH = ROOT . '../README.md';

    public function testReadmeFieldListMatchesSchema(): void
    {
        $readmeFields = $this->extractReadmeFields();
        sort($readmeFields);

        $schemaFields = Schema1::payloadFieldNames();
        sort($schemaFields);

        $this->assertSame(
            $schemaFields,
            $readmeFields,
            "README.md's '## Privacy & telemetry' field list (between the "
            . "<!-- TELEMETRY-FIELDS-START --> / <!-- TELEMETRY-FIELDS-END --> markers) "
            . "must deep-equal Schema1::payloadFieldNames(). Update README.md when "
            . "the schema lock file changes."
        );
    }

    /**
     * @return list<string>
     */
    private function extractReadmeFields(): array
    {
        $path = realpath(self::README_PATH);
        if ($path === false || !is_readable($path)) {
            throw new RuntimeException('Could not locate README.md at ' . self::README_PATH);
        }
        $raw = (string) file_get_contents($path);

        if (preg_match(
            '/<!--\s*TELEMETRY-FIELDS-START\s*-->(.*?)<!--\s*TELEMETRY-FIELDS-END\s*-->/s',
            $raw,
            $m
        ) !== 1) {
            $this->fail(
                "README.md is missing the '<!-- TELEMETRY-FIELDS-START -->' / "
                . "'<!-- TELEMETRY-FIELDS-END -->' marker block under "
                . "'## Privacy & telemetry'. The TelemetryReadmeParityTest "
                . "depends on these markers to keep README and Schema1 in sync."
            );
        }
        $block = $m[1];

        $fields = [];
        if (preg_match_all('/^\s*-\s+`([a-z0-9_.]+)`/m', $block, $matches) > 0) {
            foreach ($matches[1] as $candidate) {
                $fields[] = $candidate;
            }
        }
        return $fields;
    }
}
