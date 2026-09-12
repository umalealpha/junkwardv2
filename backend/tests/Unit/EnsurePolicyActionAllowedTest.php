<?php

namespace Tests\Unit;

use AlphaDirect\Http\Middleware\EnsurePolicyActionAllowed;
use AlphaDirect\Services\Validation\PolicyValidationRuleEngine;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Tests for EnsurePolicyActionAllowed.
 *
 * Uses the Laravel TestCase so Log/auth facades resolve cleanly. The
 * actual engine evaluation has its own test suite in
 * PolicyValidationRuleEngineTest.
 */
class EnsurePolicyActionAllowedTest extends TestCase
{
    public function test_unknown_action_key_lets_request_through(): void
    {
        $mw = new EnsurePolicyActionAllowed();
        $req = new Request();
        $called = false;
        $next = function ($r) use (&$called) {
            $called = true;
            return response('ok');
        };

        // bogus action key — middleware must not block
        $response = $mw->handle($req, $next, 'canSomethingMadeUp');

        $this->assertTrue($called, 'next() should be called when actionKey is unknown');
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_missing_policy_id_lets_request_through(): void
    {
        $mw = new EnsurePolicyActionAllowed();
        $req = new Request();
        // no route() id set → middleware treats as "nothing to check"
        $called = false;
        $next = function ($r) use (&$called) {
            $called = true;
            return response('ok');
        };

        $response = $mw->handle($req, $next, 'canIssue');

        $this->assertTrue($called, 'next() should be called when policy id is missing');
    }

    public function test_action_keys_constant_in_sync_with_engine(): void
    {
        // Guard against drift: if PolicyValidationRuleEngine::ACTIONS adds or
        // renames a key, this middleware's actionKey validation will silently
        // start letting requests through for the new key. This test pins the
        // ACTIONS list so the engine PR + the middleware PR have to land
        // together.
        $expected = [
            'canRate',
            'canPrintQuote',
            'canPrintApp',
            'canBindApp',
            'canSubmitUnbound',
            'canIssue',
        ];
        $this->assertSame($expected, PolicyValidationRuleEngine::ACTIONS);
    }
}
