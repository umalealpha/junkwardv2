<?php

namespace Tests\Feature\SmartUw;

use AlphaDirect\Services\SmartUw\PhpScheduleExtractor;
use Tests\TestCase;

/**
 * Smart Upload maps a client's schedule into OUR four buckets — Coverage,
 * Extension, Miscellaneous Item, Excess — and is instructed never to guess.
 *
 * Two outcomes are therefore normal, and neither may be treated as a failure:
 *
 *   needs_check    the line is placed, but flagged for a person to check.
 *   unclassified[] the line is NOT placed at all, because a wrong Excess or
 *                  Coverage changes the premium and nothing downstream would
 *                  catch it.
 *
 * These tests pin the two behaviours the rest of the feature is built on: the
 * exception list an underwriter is shown, and the shape-tolerance that stops a
 * model's sloppy answer becoming a crash on the review screen.
 */
class ScheduleClassificationTest extends TestCase
{
    /**
     * Public + static since the JOB has to normalise sidecar-produced risks,
     * which never pass through the extractor class at all.
     */
    private function normalise(array $risk): array
    {
        return PhpScheduleExtractor::normaliseClassification($risk);
    }

    public function test_it_lists_a_flagged_line_as_please_check(): void
    {
        $risk = [
            'coverages' => [[
                'section' => 'Fire & Allied Perils',
                'details' => [
                    ['description' => 'Buildings', 'sum_insured' => 3120000],
                    ['description' => 'Loss of rent 25%', 'needs_check' => true,
                     'check_reason' => 'could be an extension'],
                ],
            ]],
        ];

        $out = PhpScheduleExtractor::classificationExceptions($risk);

        $this->assertCount(1, $out);
        $this->assertStringContainsString('Please check', $out[0]);
        $this->assertStringContainsString('Fire & Allied Perils', $out[0]);
        $this->assertStringContainsString('Coverage', $out[0]);
        $this->assertStringContainsString('Loss of rent 25%', $out[0]);
        $this->assertStringContainsString('could be an extension', $out[0]);
    }

    public function test_it_lists_an_unplaced_line_as_needs_classification(): void
    {
        $risk = [
            'coverages' => [[
                'section'      => 'Goods in Transit',
                'unclassified' => [
                    ['text' => 'Riot & strike 5% min P2,500', 'reason' => 'excess or extension?'],
                ],
            ]],
            'unclassified' => [
                ['text' => 'ADDITIONAL CLAUSES APPLY', 'section' => 'Notes'],
            ],
        ];

        $out = PhpScheduleExtractor::classificationExceptions($risk);

        $this->assertCount(2, $out);
        $this->assertStringContainsString('Needs classification — Goods in Transit', $out[0]);
        $this->assertStringContainsString('excess or extension?', $out[0]);
        // A line under no section still reaches the underwriter, and says where
        // it was printed — it is not silently dropped.
        $this->assertStringContainsString('Needs classification — no section', $out[1]);
        $this->assertStringContainsString('Notes', $out[1]);
    }

    public function test_a_clean_extraction_has_no_exceptions(): void
    {
        $risk = [
            'coverages' => [[
                'section'  => 'Public Liability',
                'details'  => [['description' => 'Limit of indemnity', 'sum_insured' => 5000000]],
                'excesses' => [['text' => 'P2,500 each and every claim', 'min_amount' => 2500]],
            ]],
        ];

        $this->assertSame([], PhpScheduleExtractor::classificationExceptions($risk));
    }

    /**
     * The review screen reads these keys directly, so a model that answers
     * with a bare string, a single object, or a flag written as text must not
     * be able to break it — or crash the panel an underwriter needs in order
     * to classify anything at all.
     */
    public function test_it_tolerates_the_shapes_a_model_actually_returns(): void
    {
        $risk = $this->normalise([
            'coverages' => [[
                'section' => 'Fire',
                // A single object where a list was asked for.
                'details' => ['description' => 'Buildings', 'needs_check' => 'true'],
                // A bare string line.
                'unclassified' => 'Sprinkler warranty applies',
            ]],
            // Missing entirely.
        ]);

        $cov = $risk['coverages'][0];
        $this->assertTrue(array_is_list($cov['details']));
        $this->assertTrue($cov['details'][0]['needs_check'], 'the string "true" is a flag');
        $this->assertTrue(array_is_list($cov['unclassified']));
        $this->assertSame('Sprinkler warranty applies', $cov['unclassified'][0]['text']);
        // Buckets the model omitted exist as empty lists, never as null.
        $this->assertSame([], $cov['extensions']);
        $this->assertSame([], $cov['specified_items']);
        $this->assertSame([], $cov['excesses']);
        $this->assertSame([], $risk['unclassified']);
    }

    /**
     * The JOB normalises every risk, including one the in-process reader has
     * already normalised — the sidecar does not go through that class at all,
     * and the job cannot tell the two apart. A second pass must therefore
     * change nothing, or the in-process path would be damaged to fix the
     * sidecar path.
     */
    public function test_normalising_twice_changes_nothing(): void
    {
        $once = $this->normalise([
            'coverages' => [[
                'section'      => 'Fire',
                'details'      => ['description' => 'Buildings', 'needs_check' => 'yes'],
                'excesses'     => [['text' => '10% min P10,000', 'min_percent' => 10, 'min_amount' => 10000]],
                'unclassified' => 'Sprinkler warranty applies',
            ]],
        ]);

        $this->assertSame($once, $this->normalise($once));
    }

    /**
     * A sidecar-read schedule never carries _exceptions — only the in-process
     * reader writes them. The job recounts from the JSON, so an unplaced line
     * still reaches the underwriter with a confidence that reflects it.
     */
    public function test_exceptions_are_countable_from_a_sidecar_shaped_risk(): void
    {
        // Exactly what the sidecar can answer: a bucket as a bare string.
        $out = PhpScheduleExtractor::classificationExceptions($this->normalise([
            'coverages' => [[
                'section'      => 'Goods in Transit',
                'unclassified' => 'Riot & strike 5% min P2,500',
            ]],
        ]));

        $this->assertCount(1, $out);
        $this->assertStringContainsString('Needs classification — Goods in Transit', $out[0]);
        $this->assertStringContainsString('Riot & strike', $out[0]);
    }

    /** An unclassified entry with no text is the only thing dropped — there is
     *  nothing there for an underwriter to place. */
    public function test_it_drops_only_an_empty_pending_line(): void
    {
        $risk = $this->normalise([
            'coverages' => [[
                'section'      => 'Fire',
                'unclassified' => [
                    ['text' => '   '],
                    ['sum_insured' => 1000],
                    ['text' => 'Debris removal', 'premium' => 120],
                ],
            ]],
        ]);

        $rows = $risk['coverages'][0]['unclassified'];
        $this->assertCount(1, $rows);
        $this->assertSame('Debris removal', $rows[0]['text']);
        $this->assertSame(120, $rows[0]['premium']);
    }
}
