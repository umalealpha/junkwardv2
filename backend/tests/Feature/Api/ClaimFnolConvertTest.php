<?php

namespace Tests\Feature\Api;

use AlphaDirect\Http\Controllers\Api\V1\ClaimFnolController;
use AlphaDirect\Models\ClaimFnol;
use AlphaDirect\Services\IntegrationSettings;
use AlphaDirect\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * FNOL -> claim convert (#1839) — smoke test.
 *
 * Two layers, so the suite is useful with OR without a live DB:
 *
 *  1. DB-FREE (always runs): exhaustive assertions on
 *     ClaimFnolController::resolveClaimType() — the pure tracker->Graphite
 *     claim-type resolver the #1839 fix added. This is the heart of the fix:
 *     every coarse label + every non-motor sub-type must resolve to a canonical
 *     claims.claim_type ENUM value, and anything unmapped must return null
 *     (which drives convert()'s 422 refuse-guard so no empty-typed claim is
 *     ever created). No database is touched.
 *
 *  2. DB-BACKED integration (skips when no DB): create policy + FNOL, POST
 *     convert, and assert the created claim's claim_type, the 422/no-claim
 *     refuse path for an unmapped label, and that stage_data lands on the
 *     claim_tracker_workflow row. Follows the tests/Feature/Api convention:
 *     hand-inserted fixtures on the shared MySQL (RefreshDatabase is not an
 *     option), cleaned up in tearDown, and markTestSkipped when the DB / a
 *     claim-create user / the schema isn't available (e.g. offline dev boxes).
 *
 * NOTE on Goods In Transit: resolveClaimType maps GIT -> 'GOODSINTRANSIT'
 * (asserted in layer 1), but a real convert of a GIT FNOL is *expected* to be
 * refused by ClaimsController::store()'s GIT pre-validation (it requires a full
 * sub_claim_data payload the FNOL convert never sends). That is a store-level
 * required-field guard, not the empty-type bug — so the GIT success assertion
 * lives at the resolver level only, and the DB-backed success path uses Motor /
 * Glass (types with no extra create-time required-field validation).
 */
class ClaimFnolConvertTest extends TestCase
{
    private const FLAG = 'claims_fnol';

    private ?User $user = null;
    private bool $dbReady = false;
    private bool $flagWasSet = false;

    private ?int $customerId = null;
    private ?int $policyId = null;
    private string $policyNumber = '';
    /** FNOL ids created during a test, cleaned in tearDown. */
    private array $fnolIds = [];
    /** claim ids created during a test, cleaned in tearDown. */
    private array $claimIds = [];

    // ── Layer 1: DB-FREE resolver coverage (always runs) ─────────────────────

    /**
     * Every COARSE label maps to its canonical claims.claim_type ENUM value.
     * (Motor Claim -> Motor, Glass -> Glass, Lock & Key -> Key Loss.)
     */
    public function test_resolve_claim_type_covers_all_coarse_labels(): void
    {
        $c = new ClaimFnolController();

        // claims-team CONFIRMED 2026-08-26: Motor Claim stores as 'Motor' (was 'Accident').
        $this->assertSame('Motor', $c->resolveClaimType('Motor Claim', null));
        $this->assertNotSame('Accident', $c->resolveClaimType('Motor Claim', null));
        $this->assertSame('Glass', $c->resolveClaimType('Glass', null));
        // The #1839 fix: 'Lock & Key' must resolve to the ENUM member 'Key Loss'
        // (space), NOT the raw tracker slug 'key_loss' (which is not an ENUM
        // member and coerced to '' — the empty-type bug).
        $this->assertSame('Key Loss', $c->resolveClaimType('Lock & Key', null));
        $this->assertNotSame('key_loss', $c->resolveClaimType('Lock & Key', null));
    }

    /**
     * EVERY non-motor sub-type (claim_type == 'Non-Motor Claim') maps to its
     * canonical ENUM value. This locks the full NON_MOTOR_CLAIM_TYPE_MAP so a
     * silent map edit can't reintroduce an unmapped/empty-typed convert.
     *
     * @dataProvider nonMotorMappings
     */
    public function test_resolve_claim_type_covers_every_non_motor_sub_type(string $subType, string $expected): void
    {
        $c = new ClaimFnolController();
        $this->assertSame($expected, $c->resolveClaimType('Non-Motor Claim', $subType));
    }

    /** @return array<string,array{0:string,1:string}> */
    public static function nonMotorMappings(): array
    {
        return [
            'Fire'                       => ['Fire', 'FIRE'],
            'Theft'                      => ['Theft', 'THEFT'],
            'Workmen Compensation (WCA)' => ['Workmen Compensation (WCA)', 'WORKERSCOMPENSATION'],
            'Travel Insurance'           => ['Travel Insurance', 'TRAVELINSURANCE'],
            'Legal'                      => ['Legal', 'Legal'],
            'Hospital Cash Back'         => ['Hospital Cash Back', 'Hospital CashBack'],
            'Goods In Transit (GIT)'     => ['Goods In Transit (GIT)', 'GOODSINTRANSIT'],
            'Mobile and Electronics'     => ['Mobile and Electronics', 'MOBILEELECTRONICDEVICES'],
            'Fidelity'                   => ['Fidelity', 'FIDELITYGUARANTEE'],
            'Defective Workmanship'      => ['Defective Workmanship', 'DEFECTIVEWORKMANSHIP'],
            'Machinery Breakdown'        => ['Machinery Breakdown', 'DEFECTIVEWORKMANSHIP'],
            'Plant All Risk'             => ['Plant All Risk', 'PLANTALLRISKS'],
            "Contractors' All Risk"      => ["Contractors' All Risk", 'CONTRACTORSALLRISKS'],
            'Money'                      => ['Money', 'LIABILITY'],
            'Commercial-Building'        => ['Commercial-Building', 'BUILDINGSCOMBINED'],
            'Domestic-Building'          => ['Domestic-Building', 'BUILDINGSCOMBINED'],
            'Commercial-Contents'        => ['Commercial-Contents', 'OFFICECONTENTS'],
            'Domestic-Contents'          => ['Domestic-Contents', 'OFFICECONTENTS'],
            'All Risk'                   => ['All Risk', 'BUSINESSALLRISKS'],
            'Accidental Death'           => ['Accidental Death', 'PERSONALALLRISKS'],
            'Medical Malpractice'        => ['Medical Malpractice', 'MEDICALMALPRACTICE'],
            'Bonu'                       => ['Bonu', 'Legal'],
            'Burglary'                   => ['Burglary', 'THEFT'],
            'Houseowners'                => ['Houseowners', 'HOUSEOWNERS'],
            'Public Liability'           => ['Public Liability', 'LIABILITY'],
            'Business Interruption'      => ['Business Interruption', 'BUSINESSINTERRUPTION'],
        ];
    }

    /**
     * Unmapped / incomplete inputs all resolve to null — the signal convert()
     * uses to REFUSE (422) rather than create an empty-typed claim.
     */
    public function test_resolve_claim_type_returns_null_for_unmapped_and_incomplete(): void
    {
        $c = new ClaimFnolController();

        $this->assertNull($c->resolveClaimType(null, null));                          // null label
        $this->assertNull($c->resolveClaimType('', null));                            // empty label
        $this->assertNull($c->resolveClaimType('   ', null));                         // whitespace-only label
        $this->assertNull($c->resolveClaimType('Totally Unknown Type', null));        // unmapped coarse
        $this->assertNull($c->resolveClaimType('Non-Motor Claim', null));             // sub-type missing
        $this->assertNull($c->resolveClaimType('Non-Motor Claim', ''));               // sub-type empty
        $this->assertNull($c->resolveClaimType('Non-Motor Claim', '   '));            // sub-type whitespace
        $this->assertNull($c->resolveClaimType('Non-Motor Claim', 'Nonexistent'));    // sub-type unmapped
    }

    /** Labels/sub-types are trimmed before lookup (tracker sends padded values). */
    public function test_resolve_claim_type_trims_whitespace(): void
    {
        $c = new ClaimFnolController();
        $this->assertSame('Motor', $c->resolveClaimType('  Motor Claim  ', null));
        $this->assertSame('FIRE', $c->resolveClaimType('Non-Motor Claim', '  Fire  '));
    }

    // ── Layer 2: DB-BACKED convert integration (skips without a DB) ──────────

    /**
     * A Motor FNOL with a resolvable active policy + loss date converts into a
     * real 'Motor' claim (claims-team CONFIRMED 2026-08-26: 'Motor', was
     * 'Accident'), the Allocated Date is carried onto claims.claim_allocated_on,
     * and the stage_data captured at intake is written onto the new claim's
     * claim_tracker_workflow row.
     */
    public function test_convert_motor_creates_motor_claim_and_applies_stage_data(): void
    {
        $this->bootDbOrSkip();

        $fnolId = $this->makeFnol([
            'claim_type'         => 'Motor Claim',
            'claim_allocated_on' => '2026-08-10',
            'stage_data' => [
                'assessor_name'           => 'Jane Assessor',
                'assessor_allotment_date' => '2026-08-07',
                'gt_number'               => 'GT-SMOKE-1',
            ],
        ]);

        $response = $this->postJson("/api/v1/claims/fnol/{$fnolId}/convert");
        $response->assertStatus(201);

        $claimId = $response->json('data.claim_id');
        $this->assertNotNull($claimId, 'convert should return the new claim id');
        $this->claimIds[] = (int) $claimId;

        // The claim persisted with the resolved ENUM type (Motor, not Accident).
        $this->assertSame('Motor', DB::table('claims')->where('id', $claimId)->value('claim_type'));

        // The Allocated Date captured at intake was carried onto the claim.
        $this->assertSame(
            '2026-08-10',
            substr((string) DB::table('claims')->where('id', $claimId)->value('claim_allocated_on'), 0, 10)
        );

        // The FNOL is now converted and links to the claim.
        $fnol = DB::table('claim_fnol')->where('id', $fnolId)->first();
        $this->assertSame(ClaimFnol::STATUS_CONVERTED, $fnol->status);
        $this->assertSame((int) $claimId, (int) $fnol->converted_claim_id);

        // stage_data was applied to claim_tracker_workflow via ClaimStageTimelineService.
        $wf = DB::table('claim_tracker_workflow')->where('claim_id', $claimId)->first();
        $this->assertNotNull($wf, 'a claim_tracker_workflow row should exist after convert');
        $this->assertSame('Jane Assessor', $wf->assessor_name);
        $this->assertSame('GT-SMOKE-1', $wf->gt_number);
    }

    /** A Glass FNOL converts into a 'Glass' claim. */
    public function test_convert_glass_creates_glass_claim(): void
    {
        $this->bootDbOrSkip();

        $fnolId = $this->makeFnol(['claim_type' => 'Glass']);

        $response = $this->postJson("/api/v1/claims/fnol/{$fnolId}/convert");
        $response->assertStatus(201);

        $claimId = $response->json('data.claim_id');
        $this->assertNotNull($claimId);
        $this->claimIds[] = (int) $claimId;

        $this->assertSame('Glass', DB::table('claims')->where('id', $claimId)->value('claim_type'));
    }

    /**
     * The #1839 guard end-to-end: an FNOL whose claim_type has NO Graphite
     * mapping is refused with 422 and NO claim is created; the FNOL is left
     * open (never stranded in 'converting'). Minimal side effects — only an
     * FNOL fixture, no claim cascade.
     */
    public function test_convert_unmapped_type_is_refused_422_and_creates_no_claim(): void
    {
        $this->bootDbOrSkip();

        $fnolId = $this->makeFnol(['claim_type' => 'Totally Unknown Type']);

        $before = (int) DB::table('claims')->count();

        $response = $this->postJson("/api/v1/claims/fnol/{$fnolId}/convert");
        $response->assertStatus(422);

        $after = (int) DB::table('claims')->count();
        $this->assertSame($before, $after, 'no claim should be created for an unmapped type');

        // FNOL is untouched (still open), not stranded in 'converting'.
        $this->assertSame(
            ClaimFnol::STATUS_OPEN,
            DB::table('claim_fnol')->where('id', $fnolId)->value('status')
        );
    }

    /** A Non-Motor FNOL with an unknown sub-type is likewise refused with 422. */
    public function test_convert_non_motor_unknown_sub_type_is_refused_422(): void
    {
        $this->bootDbOrSkip();

        $fnolId = $this->makeFnol([
            'claim_type'         => 'Non-Motor Claim',
            'non_motor_sub_type' => 'Nonexistent Sub Type',
        ]);

        $before = (int) DB::table('claims')->count();

        $this->postJson("/api/v1/claims/fnol/{$fnolId}/convert")->assertStatus(422);

        $this->assertSame($before, (int) DB::table('claims')->count());
        $this->assertSame(
            ClaimFnol::STATUS_OPEN,
            DB::table('claim_fnol')->where('id', $fnolId)->value('status')
        );
    }

    // ── fixtures / helpers ───────────────────────────────────────────────────

    /**
     * Skip the DB-backed tests unless a live DB with the required schema and a
     * claim-create-capable user is available. When it is, stand up a customer +
     * active (product_id 1, status 1) policy fixture and turn the FNOL flag on.
     */
    private function bootDbOrSkip(): void
    {
        try {
            DB::connection()->getPdo();
        } catch (\Throwable $e) {
            $this->markTestSkipped('No database connection: ' . $e->getMessage());
        }

        foreach (['claim_fnol', 'policies', 'customer', 'claims', 'claim_tracker_workflow'] as $table) {
            if (!Schema::hasTable($table)) {
                $this->markTestSkipped("Required table '{$table}' is missing.");
            }
        }

        // convert is gated by permission:claim-create — need a user that holds it.
        $user = User::query()->whereNotNull('id')->orderBy('id')->first();
        if (!$user) {
            $this->markTestSkipped('No users in database.');
        }
        try {
            if (method_exists($user, 'can') && !$user->can('claim-create')) {
                $this->markTestSkipped('First user lacks the claim-create permission.');
            }
        } catch (\Throwable $e) {
            $this->markTestSkipped('Permission subsystem unavailable: ' . $e->getMessage());
        }
        $this->user = $user;
        Sanctum::actingAs($user, ['*']);

        // Turn the FNOL feature on (else every endpoint 404s) and remember to
        // restore it. Wrapped so a missing integration_settings table skips.
        try {
            IntegrationSettings::setEnabled(self::FLAG, true, $user, 'ClaimFnolConvertTest');
            $this->flagWasSet = true;
        } catch (\Throwable $e) {
            $this->markTestSkipped('Cannot toggle integration_settings: ' . $e->getMessage());
        }

        $suffix             = substr((string) microtime(true), -6);
        $this->policyNumber = 'FNOLSMK' . $suffix;
        $this->customerId   = DB::table('customer')->insertGetId([
            'firstName'  => 'Fnol',
            'lastName'   => 'Smoke',
            'cellphone'  => '71000009',
            'email'      => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->policyId = DB::table('policies')->insertGetId([
            'policyNumber' => $this->policyNumber,
            'customer_id'  => $this->customerId,
            'product_id'   => 1,
            'status'       => 1,
            'premium'      => 350.00,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        $this->dbReady = true;
    }

    /**
     * Insert an open FNOL fixture with a resolvable policy + loss date, plus any
     * overrides (claim_type / non_motor_sub_type / stage_data). Returns its id.
     *
     * @param array<string,mixed> $overrides
     */
    private function makeFnol(array $overrides = []): int
    {
        $row = array_merge([
            'fnol_number'    => 'FN-SMK-' . substr((string) microtime(true), -6) . '-' . count($this->fnolIds),
            'claimant_name'  => 'Smoke Claimant',
            'description'    => 'Smoke-test reported loss.',
            'policy_id'      => $this->policyId,
            'policy_number'  => $this->policyNumber,
            'loss_date'      => now()->subDay()->format('Y-m-d'),
            'status'         => ClaimFnol::STATUS_OPEN,
            'source'         => 'manual',
            'created_by'     => $this->user?->id,
            'updated_by'     => $this->user?->id,
            'created_at'     => now(),
            'updated_at'     => now(),
        ], $overrides);

        if (array_key_exists('stage_data', $row) && is_array($row['stage_data'])) {
            $row['stage_data'] = json_encode($row['stage_data']);
        }

        $id = DB::table('claim_fnol')->insertGetId($row);
        $this->fnolIds[] = $id;

        return $id;
    }

    protected function tearDown(): void
    {
        if ($this->dbReady) {
            try {
                if (!empty($this->claimIds)) {
                    $claimNumbers = DB::table('claims')->whereIn('id', $this->claimIds)
                        ->pluck('claim_number')->filter()->all();
                    DB::table('claim_tracker_workflow')->whereIn('claim_id', $this->claimIds)->delete();
                    DB::table('claim_edit_log')->whereIn('claim_id', $this->claimIds)->delete();
                    if (!empty($claimNumbers) && Schema::hasTable('new_claims')) {
                        DB::table('new_claims')->whereIn('claim_number', $claimNumbers)->delete();
                    }
                    DB::table('audits')->where('auditable_type', 'like', '%Claim')
                        ->whereIn('auditable_id', $this->claimIds)->delete();
                    DB::table('claims')->whereIn('id', $this->claimIds)->delete();
                }
                if (!empty($this->fnolIds)) {
                    DB::table('claim_fnol')->whereIn('id', $this->fnolIds)->delete();
                }
                if ($this->policyId) {
                    DB::table('policies')->where('id', $this->policyId)->delete();
                }
                if ($this->customerId) {
                    DB::table('customer')->where('id', $this->customerId)->delete();
                }
                if ($this->flagWasSet) {
                    // Best-effort: flip the flag back off (correct mysql_system
                    // connection) so the toggle state doesn't leak.
                    IntegrationSettings::setEnabled(self::FLAG, false, $this->user, 'ClaimFnolConvertTest teardown');
                }
            } catch (\Throwable $e) {
                // Cleanup is best-effort; never let it mask a test result.
            }
        }

        parent::tearDown();
    }
}
