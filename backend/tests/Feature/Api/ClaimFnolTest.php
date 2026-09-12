<?php

namespace Tests\Feature\Api;

use AlphaDirect\Http\Controllers\Api\V1\ClaimFnolController;
use Tests\TestCase;

/**
 * Claims FNOL API — route wiring + auth gating (deterministic, DB-free).
 *
 * These assert every FNOL endpoint sits behind auth:sanctum. Deeper behaviour
 * (permission middleware + the `claims_fnol` flag 404) needs a seeded DB with
 * Spatie permissions, so it is exercised in integration, not here — this file
 * stays fast and side-effect free.
 */
class ClaimFnolTest extends TestCase
{
    public function test_list_requires_authentication(): void
    {
        $this->getJson('/api/v1/claims/fnol')->assertStatus(401);
    }

    public function test_show_requires_authentication(): void
    {
        $this->getJson('/api/v1/claims/fnol/1')->assertStatus(401);
    }

    public function test_create_requires_authentication(): void
    {
        $this->postJson('/api/v1/claims/fnol', [
            'claimant_name' => 'Test Claimant',
            'description'   => 'Reported loss',
        ])->assertStatus(401);
    }

    public function test_update_requires_authentication(): void
    {
        $this->putJson('/api/v1/claims/fnol/1', ['claim_type' => 'Motor'])->assertStatus(401);
    }

    public function test_convert_requires_authentication(): void
    {
        $this->postJson('/api/v1/claims/fnol/1/convert')->assertStatus(401);
    }

    /**
     * The tracker stage accordions post their collected map as `stage_data`.
     * Assert the create endpoint still sits behind auth when that field is
     * present (the field itself is optional/additive). Deeper behaviour — convert
     * writing stage_data into claim_tracker_workflow via ClaimStageTimelineService
     * — needs a seeded DB + Spatie permissions and is exercised in integration.
     */
    public function test_create_with_stage_data_requires_authentication(): void
    {
        $this->postJson('/api/v1/claims/fnol', [
            'claimant_name' => 'Test Claimant',
            'description'   => 'Reported loss',
            'stage_data'    => [
                'assessor_allotment_date' => '2026-08-07',
                'gt_number'               => 'GT-123',
            ],
        ])->assertStatus(401);
    }

    /**
     * The tracker->Graphite claim-type resolver (ported into convert()) must map
     * every coarse label + non-motor sub-type to a canonical claims.claim_type
     * ENUM value, and return null for anything unmapped (which drives convert()'s
     * 422 refuse-guard). Pure method — no DB needed.
     */
    public function test_resolve_claim_type_maps_labels_to_enum_values(): void
    {
        $c = new ClaimFnolController();

        // Coarse labels. (claims-team CONFIRMED 2026-08-26: Motor Claim -> 'Motor', was 'Accident'.)
        $this->assertSame('Motor', $c->resolveClaimType('Motor Claim', null));
        $this->assertSame('Glass', $c->resolveClaimType('Glass', null));
        $this->assertSame('Key Loss', $c->resolveClaimType('Lock & Key', null)); // not 'key_loss'

        // Non-Motor: keyed by the sub-type, not the coarse label.
        $this->assertSame('FIRE', $c->resolveClaimType('Non-Motor Claim', 'Fire'));
        $this->assertSame('WORKERSCOMPENSATION', $c->resolveClaimType('Non-Motor Claim', 'Workmen Compensation (WCA)'));
        $this->assertSame('Hospital CashBack', $c->resolveClaimType('Non-Motor Claim', 'Hospital Cash Back'));
        $this->assertSame('BUILDINGSCOMBINED', $c->resolveClaimType('Non-Motor Claim', 'Commercial-Building'));
        $this->assertSame('CONTRACTORSALLRISKS', $c->resolveClaimType('Non-Motor Claim', "Contractors' All Risk"));

        // Unmapped / incomplete => null (convert() will 422).
        $this->assertNull($c->resolveClaimType('Non-Motor Claim', null));          // sub-type missing
        $this->assertNull($c->resolveClaimType('Non-Motor Claim', 'Nonexistent')); // sub-type unmapped
        $this->assertNull($c->resolveClaimType('Totally Unknown Type', null));     // coarse unmapped
        $this->assertNull($c->resolveClaimType('', null));                         // empty
        $this->assertNull($c->resolveClaimType(null, null));                       // null
    }

    public function test_close_requires_authentication(): void
    {
        $this->postJson('/api/v1/claims/fnol/1/close')->assertStatus(401);
    }
}
