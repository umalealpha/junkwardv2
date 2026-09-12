<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * The array-key contract inside ClaimFormPrefill.
 *
 * WHY THIS TEST EXISTS. `build()` read `$customer['address']` and
 * `$customer['id_number']` after `customer()` had stopped returning them. In
 * PHP 8 an undefined array key is a warning that Laravel promotes to an
 * exception, so EVERY call to build() threw: every staff send failed and every
 * claimant link returned a 500. `php -l` passed, tsc passed, the blade check
 * passed, and three separate review rounds read the file without seeing it —
 * because it is a runtime fault that no syntax tool can see.
 *
 * This test reads the source and proves the consumer never reads a key the
 * producer does not return. It needs no database and no Laravel boot, so it
 * runs anywhere, including on a laptop that cannot start the app.
 */
class ClaimFormPrefillContractTest extends TestCase
{
    private string $src;

    protected function setUp(): void
    {
        parent::setUp();
        $this->src = (string) file_get_contents(__DIR__ . '/../../app/Services/Claims/ClaimFormPrefill.php');
        $this->assertNotSame('', $this->src, 'ClaimFormPrefill.php could not be read.');
    }

    /**
     * @dataProvider producers
     */
    public function test_build_only_reads_keys_its_producer_returns(string $var, string $method): void
    {
        $produced = $this->keysReturnedBy($method);
        $this->assertNotEmpty($produced, "Could not find the keys returned by {$method}().");

        foreach ($this->keysReadFrom($var) as $key) {
            $this->assertContains(
                $key,
                $produced,
                "build() reads \${$var}['{$key}'] but {$method}() never returns '{$key}'. "
                . 'That is a runtime exception on every call, not a warning — see the note on this test.'
            );
        }
    }

    public static function producers(): array
    {
        return [
            'insured from customer()' => ['customer', 'customer'],
            'policy from policy()'    => ['policy', 'policy'],
            'vehicle from vehicle()'  => ['vehicle', 'vehicle'],
        ];
    }

    /** Every `$var['key']` read in the body of build(). */
    private function keysReadFrom(string $var): array
    {
        $body = $this->methodBody('build');
        preg_match_all('/\$' . preg_quote($var, '/') . "\['([a-z_]+)'\]/", $body, $m);

        return array_values(array_unique($m[1] ?? []));
    }

    /** Every `'key' =>` produced anywhere in the named method (all return paths). */
    private function keysReturnedBy(string $method): array
    {
        $body = $this->methodBody($method);
        preg_match_all("/'([a-z_]+)'\s*=>/", $body, $m);

        return array_values(array_unique($m[1] ?? []));
    }

    /** Crude but sufficient: from the signature to the next method at the same depth. */
    private function methodBody(string $method): string
    {
        $start = strpos($this->src, 'function ' . $method . '(');
        $this->assertNotFalse($start, "Method {$method}() not found in ClaimFormPrefill.");

        $rest = substr($this->src, $start);
        $next = preg_match('/\n    (?:private|public|protected) function /', $rest, $mm, PREG_OFFSET_CAPTURE)
            ? $mm[0][1]
            : strlen($rest);

        return substr($rest, 0, $next);
    }

    /** The Omang must never be put on a claim form or in the pre-fill payload. */
    public function test_prefill_carries_no_identity_number(): void
    {
        $build = $this->methodBody('build');

        foreach (['id_number', 'omang', 'passport'] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $build,
                "build() must not place '{$forbidden}' in the pre-fill payload — it is emailed to the customer "
                . 'and is not needed on any claim form (AD-POL-AI-GOV-001, data minimisation).'
            );
        }
    }

    /**
     * The claim must be resolved from `claims` only. `claims` and `new_claims`
     * have overlapping id ranges, and this payload is emailed to a customer, so
     * a bare-id fallback across both tables could address the wrong claim.
     */
    public function test_claim_is_read_from_the_authoritative_table_only(): void
    {
        $build = $this->methodBody('build');

        $this->assertStringContainsString("DB::table('claims')", $build);
        $this->assertStringNotContainsString(
            "DB::table('new_claims')",
            $build,
            'build() must not fall back to new_claims — overlapping ids could resolve to a different claim.'
        );
    }
}
