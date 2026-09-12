<?php

namespace AlphaDirect\Http\Controllers\Api;

use AlphaDirect\AccidentDriver;
use AlphaDirect\Claim;
use AlphaDirect\ClaimAccident;
use AlphaDirect\ClaimCellphone;
use AlphaDirect\ClaimKeyLoss;
use AlphaDirect\ClaimLife;
use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Models\Motor;
use AlphaDirect\Models\NewClaim;
use AlphaDirect\Policy;
use AlphaDirect\Services\Claims\MotolinkAssessmentBridge;
use AlphaDirect\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * Claims Tracker (claims.alphadirect.co.bw) -> Graphite V2 bridge.
 *
 * Port of graphiteBWV8 Api\ClaimsTrackerController (feat/claims-tracker-api)
 * onto Graphite V2. The contract (request body, response shape, path,
 * api-key header) is IDENTICAL to the V1 endpoint so Claims Tracker only
 * needs to re-point GRAPHITE_API_BASE at the V2 backend — no Claims-Tracker
 * code change. The live Claims Tracker adapter is lib/graphiteErp.js which
 * POSTs {policy_number, claim_type, date_of_loss, description, external_ref,
 * claim_handler_email} and expects {claim_id, claim_number, status,
 * idempotent} back.
 *
 * Creates a parent `claims` row, mints a `claim_number` using a race-safe
 * two-phase save (save -> claim_number = 'G'.year.PK -> save), and
 * optionally persists per-type sub-table details.
 *
 * Differs from the in-app ClaimsV2Controller::store in three ways, all
 * deliberate for an external, unauthenticated caller:
 *   - JSON in, JSON out, api-key gated. No session / auth()->id().
 *   - created_by is written as a sentinel "claims-tracker:{external_ref}"
 *     string (or the resolved handler user id) so the audit trail still
 *     identifies the source even though there is no authed user.
 *   - external_ref idempotency: a unique index on claims.external_ref makes
 *     concurrent retries collide at the DB level; we return 200 + the
 *     existing claim_number rather than a duplicate row.
 *
 * Two V2 reconciliations vs. the V1 port:
 *   1. claim_number uses the race-safe two-phase save (V1 bridge style),
 *      which is strictly better than ClaimsV2Controller::store's max(id)+1
 *      and produces the same 'G'+year+6-digit-PK format.
 *   2. The opening reserve (transaction_type=86) is seeded for EVERY claim,
 *      mirroring the live ClaimsV2Controller::store, so claims-tracker
 *      claims render identically in the V2 React Reserves tab.
 */
class ClaimsTrackerController extends Controller
{
    /**
     * Friendly claim-type aliases (case-insensitive on input) -> the
     * canonical label that the in-app claim flow writes into
     * claims.claim_type. Anything not in this map is rejected by the
     * validator. Claims Tracker's admin-configured `graphiteClaimTypeMap`
     * must emit one of these slugs (or canonical labels).
     */
    private const TYPE_MAP = [
        // Claims-team CONFIRMED (Bharath, 26 Aug + 01 Sep): motor claims must store
        // as 'Motor', not 'Accident'. This is the sync-path twin of PR #1987 (which
        // fixed the FNOL-convert path). Both are valid claims.claim_type ENUM members;
        // the motor detail table (claim_accident) + is_motor logic are keyed to BOTH
        // 'Motor' and 'Accident' below so nothing is orphaned.
        'accident'                => 'Motor',
        'motor'                   => 'Motor',
        'motoraccident'           => 'Motor',
        'motortradersexternal'    => 'MOTORTRADERSEXTERNAL',
        'motortradersinternal'    => 'MOTORTRADERSINTERNAL',
        'glass'                   => 'Glass',
        'life'                    => 'Life',
        'cellphone'               => 'Cellphone',
        'key_loss'                => 'Key Loss',
        'locksandkeys'            => 'Key Loss',
        'legal'                   => 'Legal',
        'hospital_cash'           => 'Hospital CashBack',
        'businessallrisks'        => 'BUSINESSALLRISKS',
        'personalallrisks'        => 'PERSONALALLRISKS',
        'businessinterruption'    => 'BUSINESSINTERRUPTION',
        'theft'                   => 'THEFT',
        'workerscompensation'     => 'WORKERSCOMPENSATION',
        'statedbenefits'          => 'STATEDBENEFITS',
        'defectiveworkmanship'    => 'DEFECTIVEWORKMANSHIP',
        'fidelityguarantee'       => 'FIDELITYGUARANTEE',
        'travelinsurance'         => 'TRAVELINSURANCE',
        'goodsintransit'          => 'GOODSINTRANSIT',
        'fire'                    => 'FIRE',
        'buildingscombined'       => 'BUILDINGSCOMBINED',
        'accidentaldamage'        => 'ACCIDENTALDAMAGE',
        'liability'               => 'LIABILITY',
        'mobileelectronicdevices' => 'MOBILEELECTRONICDEVICES',
        'officecontents'          => 'OFFICECONTENTS',
        'contractorsallrisks'     => 'CONTRACTORSALLRISKS',
        'erectionallrisk'         => 'ERECTIONALLRISK',
        'plantallrisks'           => 'PLANTALLRISKS',
        'professionalindemnity'   => 'PROFESSIONALINDEMNITY',
        'medicalmalpractice'      => 'MEDICALMALPRACTICE',
    ];

    /**
     * Read-only accessor for the canonical claim-type map. Lets the Claims
     * Master-Data module DERIVE (never store) the Graphite Claim-Type Map /
     * Non-Motor Claim Types tile from the single source of truth without
     * duplicating the map. Returns [alias => canonical label].
     */
    public static function typeMap(): array
    {
        return self::TYPE_MAP;
    }

    /**
     * Standard motor "First Amount Payable" excess schedule. Used as a
     * fallback when a policy/vehicle has no per-vehicle excess captured on
     * the `motor` row. These are the lines shown on the Graphite policy
     * document (resources/views/policyDoc.blade.php "FIRST AMOUNT PAYABLE").
     * min_percent / min_amount are left null so the caller can tell a
     * standard line (unpriced) from a policy-specific captured value.
     */
    private const STANDARD_EXCESS_SCHEDULE = [
        ['type' => 'Own Damage',                          'min_percent' => null, 'min_amount' => null],
        ['type' => 'Theft/Hijacking Excess (each claim)', 'min_percent' => null, 'min_amount' => null],
        ['type' => 'Underage Driver <30 Years',           'min_percent' => null, 'min_amount' => null],
        ['type' => 'License Issue <2 Years from Policy Issue', 'min_percent' => null, 'min_amount' => null],
        ['type' => 'Windscreen / Glass',                  'min_percent' => null, 'min_amount' => null],
        ['type' => 'Loss of Keys',                        'min_percent' => null, 'min_amount' => null],
    ];

    /**
     * READ-ONLY cover lookup for the MotoLink assessment platform.
     *
     *   GET /api/claims-tracker/policy-cover
     *     ?claim_number=G2026000123   (a MotoLink assessment claim number)
     *     | ?policy_number=COMG2026128234
     *     | ?policy_id=12345
     *
     * Gated by the SAME VerifyClaimsTrackerApiKey middleware / api-key that
     * the Claims Tracker create endpoint already uses, so no new secret has
     * to be distributed to MotoLink.
     *
     * Returns ONLY two pieces of underwriting data — Sum Insured and the
     * excess (First Amount Payable) schedule. It deliberately exposes NO
     * customer name, omang/ID, passport, contact, or bank details. The
     * output array is an explicit whitelist; nothing else is ever returned.
     *
     * Resolution:
     *   - claim_number -> claims.policy_id -> policies.id
     *   - Sum Insured  = policies.sum_assured, falling back to the latest
     *     motor.estimated_value when the policy-level total is missing.
     *   - Excess       = per-vehicle motor excess columns
     *     (own_damage_*, windscreen_*, loss_of_keys_*); if none captured,
     *     the STANDARD_EXCESS_SCHEDULE is returned with
     *     excess_source = "standard_schedule".
     *
     * Never 404s on a missing *field*: if Sum Insured truly cannot be found
     * it still returns 200 with sum_insured = null and a missing_fields
     * array, so MotoLink/UW can route the assessment to a human instead of
     * guessing. It only 404s when the policy/claim itself does not exist.
     */
    public function policyCover(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'claim_number'  => ['required_without_all:policy_number,policy_id', 'string', 'max:40'],
            'policy_number' => ['required_without_all:claim_number,policy_id', 'string', 'max:80'],
            'policy_id'     => ['required_without_all:claim_number,policy_number', 'integer', 'min:1'],
        ]);

        // 1) Resolve the policy from whichever identifier was supplied.
        $policy        = null;
        $resolvedVia   = null;
        $claimNumber   = null;

        if (! empty($validated['claim_number'])) {
            $claimNumber = trim($validated['claim_number']);
            $claim = Claim::where('claim_number', $claimNumber)->first(['id', 'policy_id', 'claim_number']);
            if (! $claim) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Claim not found',
                ], 404);
            }
            $policy      = Policy::find($claim->policy_id);
            $resolvedVia = 'claim_number';
        } elseif (! empty($validated['policy_id'])) {
            $policy      = Policy::find($validated['policy_id']);
            $resolvedVia = 'policy_id';
        } else {
            $policy      = Policy::where('policyNumber', $validated['policy_number'])->first();
            $resolvedVia = 'policy_number';
        }

        if (! $policy) {
            return response()->json([
                'status'  => false,
                'message' => 'Policy not found',
            ], 404);
        }

        // 2) Sum Insured: policy-level sum_assured, fall back to the latest
        //    motor row's estimated_value for this policy.
        $sumInsured = is_numeric($policy->sum_assured) ? (float) $policy->sum_assured : null;
        if ($sumInsured === null || $sumInsured <= 0) {
            $motorValue = DB::table('motor')
                ->join('policy_coverages', 'policy_coverages.id', '=', 'motor.policy_coverage_id')
                ->where('policy_coverages.policy_id', $policy->id)
                ->whereNull('motor.deleted_at')
                ->whereNull('policy_coverages.deleted_at')
                ->orderByDesc('motor.id')
                ->value('motor.estimated_value');
            if (is_numeric($motorValue) && (float) $motorValue > 0) {
                $sumInsured = (float) $motorValue;
            }
        }

        // 3) Excess: per-vehicle captured excess on the latest motor row.
        $motorRow = DB::table('motor')
            ->join('policy_coverages', 'policy_coverages.id', '=', 'motor.policy_coverage_id')
            ->where('policy_coverages.policy_id', $policy->id)
            ->whereNull('motor.deleted_at')
            ->whereNull('policy_coverages.deleted_at')
            ->orderByDesc('motor.id')
            ->select(
                'motor.own_damage_minimun_percent',
                'motor.own_damage_minimum_amount',
                'motor.windscreen_minimun_percent',
                'motor.windscreen_minimum_amount',
                'motor.loss_of_keys_minimun_percent',
                'motor.loss_of_keys_minimum_amount'
            )
            ->first();

        $excesses     = [];
        $excessSource = 'standard_schedule';
        if ($motorRow) {
            $candidates = [
                ['type' => 'Own Damage',          'p' => $motorRow->own_damage_minimun_percent,    'a' => $motorRow->own_damage_minimum_amount],
                ['type' => 'Windscreen / Glass',  'p' => $motorRow->windscreen_minimun_percent,    'a' => $motorRow->windscreen_minimum_amount],
                ['type' => 'Loss of Keys',        'p' => $motorRow->loss_of_keys_minimun_percent,  'a' => $motorRow->loss_of_keys_minimum_amount],
            ];
            foreach ($candidates as $c) {
                $hasP = is_numeric($c['p']) && (float) $c['p'] > 0;
                $hasA = is_numeric($c['a']) && (float) $c['a'] > 0;
                if ($hasP || $hasA) {
                    $excesses[] = [
                        'type'        => $c['type'],
                        'min_percent' => $hasP ? (float) $c['p'] : null,
                        'min_amount'  => $hasA ? (float) $c['a'] : null,
                    ];
                }
            }
            if (! empty($excesses)) {
                $excessSource = 'policy';
            }
        }
        if (empty($excesses)) {
            $excesses     = self::STANDARD_EXCESS_SCHEDULE;
            $excessSource = 'standard_schedule';
        }

        // 4) Named-missing-field routing: never guess a Sum Insured.
        //    Normalize a 0/blank value to null so MotoLink never treats 0 as
        //    real cover; surface it as a named missing field instead.
        if ($sumInsured !== null && $sumInsured <= 0) {
            $sumInsured = null;
        }
        $missing = [];
        if ($sumInsured === null) {
            $missing[] = 'sum_insured';
        }

        // 5) Whitelisted response. NOTHING beyond these keys is returned.
        return response()->json([
            'status'        => true,
            'policy_number' => $policy->policyNumber,
            'claim_number'  => $claimNumber,
            'resolved_via'  => $resolvedVia,
            'sum_insured'   => $sumInsured,
            'currency'      => 'BWP',
            'excesses'      => array_values($excesses),
            'excess_source' => $excessSource,
            'missing_fields' => $missing,
        ], 200);
    }

    /**
     * MotoLink (motolink.app) assessment PUSH receiver — inbound webhook.
     *
     *   POST /api/claims-tracker/assessment
     *   Header: api-key: <MOTOLINK_PUSH_KEY>   (dedicated push key —
     *           VerifyMotolinkPushKey, NOT the cover-lookup key)
     *   Body:   one assessment event as JSON, in MotoLink's own shape.
     *
     * The direction-flipped counterpart to the scheduled pull
     * (claims:motolink-sync): instead of Graphite polling MotoLink every 15
     * minutes, MotoLink pushes one event per job as it happens. Both share the
     * SAME mapping (MotolinkAssessmentBridge::normalizeAssessment) and the SAME
     * write (MotolinkAssessmentBridge::applyNormalized), so a claim ends up
     * identical whichever path delivered it.
     *
     * Contract (as promised to MotoLink in the integration response):
     *   200  {status:true, ...}        assessment mirrored onto the claim
     *   404  {status:false, error}     no Graphite claim for that claim_number
     *   422  {status:false, error}     empty body / no claim number in payload
     *   401  (middleware)              missing / bad api-key
     *   429  (throttle)                rate limit exceeded
     *
     * Idempotent: a re-sent event re-writes the same machine columns on the
     * same claim and never duplicates, so MotoLink can retry safely.
     *
     * X-Correlation-Id: if MotoLink sends one it is echoed back as a response
     * header on every outcome (200/404/422/500), per B1.7 of the integration
     * response, so their logs can pair request and response.
     */
    public function assessment(Request $request, MotolinkAssessmentBridge $bridge): JsonResponse
    {
        $response = $this->handleAssessment($request, $bridge);

        // Echo only a safe token — reject anything that could smuggle header
        // characters (Symfony throws on CR/LF, which would turn a bad header
        // into a 500 instead of a clean ignore).
        $cid = trim((string) $request->header('X-Correlation-Id', ''));
        if ($cid !== '' && strlen($cid) <= 128 && preg_match('/^[A-Za-z0-9._:-]+$/', $cid)) {
            $response->header('X-Correlation-Id', $cid);
        }

        return $response;
    }

    private function handleAssessment(Request $request, MotolinkAssessmentBridge $bridge): JsonResponse
    {
        $payload = $request->all();
        if (! is_array($payload) || $payload === []) {
            return response()->json([
                'status' => false,
                'error'  => 'Empty or invalid JSON body',
            ], 422);
        }

        try {
            $n = $bridge->normalizeAssessment($payload);
        } catch (\Throwable $e) {
            // A crash in OUR own mapping must not be billed to MotoLink as a bad
            // payload with no trace. Log the message only — never the payload (PII).
            Log::warning('[motolink-push] normalize failed', [
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'status' => false,
                'error'  => 'Malformed assessment payload',
            ], 422);
        }

        if ($n['claimNumber'] === '') {
            return response()->json([
                'status' => false,
                'error'  => 'Assessment is missing a claim number',
            ], 422);
        }

        $result = $bridge->applyNormalized($n);

        switch ($result['status']) {
            case 'updated':
                return response()->json([
                    'status'          => true,
                    'claim_number'    => $n['claimNumber'],
                    'assessment_id'   => $n['assessmentId'],
                    'motolink_status' => $n['status'],
                    'total_loss'      => (bool) $n['totalLoss'],
                    'idempotent'      => true,
                ], 200);

            case 'unmatched':
                return response()->json([
                    'status' => false,
                    'error'  => 'No Graphite claim found for claim number ' . $n['claimNumber'],
                ], 404);

            case 'error':
            default:
                Log::error('[motolink-push] apply failed', [
                    'claim_number' => $n['claimNumber'],
                    'error'        => $result['error'] ?? 'unknown',
                ]);
                return response()->json([
                    'status' => false,
                    'error'  => 'Internal error applying assessment',
                ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $allowedTypes = array_unique(array_merge(
            array_keys(self::TYPE_MAP),
            array_values(self::TYPE_MAP),
            array_map('strtolower', array_values(self::TYPE_MAP))
        ));

        $validated = $request->validate([
            // policy_id is the integer PK; policy_number is the visible
            // identifier (e.g. "COMG2026128234"). Exactly one must be given.
            'policy_id'           => ['required_without:policy_number', 'integer', 'min:1'],
            'policy_number'       => ['required_without:policy_id', 'string', 'max:80'],
            'claim_type'          => ['required', 'string', Rule::in($allowedTypes)],
            'date_of_loss'        => ['required', 'date', 'before_or_equal:today'],
            'description'         => ['required', 'string', 'max:2000'],
            'external_ref'        => ['nullable', 'string', 'max:80'],
            // claim_handler_email: lookup against `users.email` to derive
            // claims.created_by. If unset OR no matching active user, we
            // fall back to a sentinel string so the audit trail still
            // identifies Claims Tracker as the source.
            'claim_handler_email' => ['nullable', 'email', 'max:160'],
            // recorder_email: who logged/synced the claim (audit). Kept SEPARATE
            // from the assigned handler — drives claims.created_by so the handler
            // column (claims.claim_allocated_to) is not polluted by the recorder.
            // Optional/backward-compatible: older tracker builds omit it.
            'recorder_email'      => ['nullable', 'email', 'max:160'],
            // channel: Broker/Direct as recorded on the tracker — persisted so the
            // V2 list/dashboard show the real channel instead of guessing from the
            // policy's agent_id. Backward-compatible: older builds omit it.
            'channel'             => ['nullable', 'string', Rule::in(['Broker', 'Direct'])],
            // vehicle_plate: real registration (the tracker pre-filters free-text).
            'vehicle_plate'       => ['nullable', 'string', 'max:50'],
            'accident'            => ['sometimes', 'array'],
            'life'                => ['sometimes', 'array'],
            'cellphone'           => ['sometimes', 'array'],
            'key_loss'            => ['sometimes', 'array'],
            'glass'               => ['sometimes', 'array'],
        ]);

        $handlerUser = null;
        if (! empty($validated['claim_handler_email'])) {
            $handlerUser = User::where('email', $validated['claim_handler_email'])
                ->where('active', 1)
                ->first(['id', 'firstName', 'lastName']);
        }

        // Recorder (who logged/synced the claim) — audit only, resolved separately
        // so it can populate created_by without clobbering the assigned handler.
        $recorderUser = null;
        if (! empty($validated['recorder_email'])) {
            $recorderUser = User::where('email', $validated['recorder_email'])
                ->where('active', 1)
                ->first(['id']);
        }

        $canonicalType = self::TYPE_MAP[strtolower($validated['claim_type'])]
            ?? $validated['claim_type'];

        $policy = ! empty($validated['policy_id'])
            ? Policy::find($validated['policy_id'])
            : Policy::where('policyNumber', $validated['policy_number'])->first();
        if (! $policy) {
            return response()->json([
                'status'  => false,
                'message' => 'Policy not found',
            ], 404);
        }

        // Idempotency: if an external_ref was provided and we already have
        // a claim for it, return the existing one instead of creating a
        // duplicate. The unique index also catches races at insert time.
        if (! empty($validated['external_ref'])) {
            $existing = Claim::where('external_ref', $validated['external_ref'])->first();
            if ($existing) {
                return response()->json([
                    'status'       => true,
                    'claim_id'     => $existing->id,
                    'claim_number' => $existing->claim_number,
                    'idempotent'   => true,
                    'message'      => 'Claim already exists for this external_ref',
                ], 200);
            }
        }

        DB::beginTransaction();
        try {
            $claim = new Claim();
            $claim->customer_id      = $policy->customer_id;
            $claim->agent_id         = $policy->agent_id;
            $claim->policy_id        = $policy->id;
            $claim->claim_type       = $canonicalType;
            // 'Pending' — the default new-claim state. Must be a member of the
            // claims.status ENUM ('Pending','Approved','Rejected','Closed',
            // 'Reopen','Open'); the old 'New' isn't in the ENUM and silently
            // coerced to '' (rendered as "Unknown" in the claims list).
            // Matches ClaimsController::store (in-app create), which also
            // defaults to 'Pending'.
            $claim->status           = 'Pending';
            $claim->registered_claim = Carbon::parse($validated['date_of_loss'])->format('Y-m-d');
            $claim->note             = $validated['description'];
            $claim->source           = 'claims-tracker';
            $claim->external_ref     = $validated['external_ref'] ?? null;
            // created_by = the RECORDER (audit trail) when the tracker sends
            // recorder_email; otherwise fall back to the handler, then a sentinel,
            // so older tracker builds behave exactly as before. The DISPLAYED
            // "Claim Handler" now comes from claim_allocated_to (set below), so the
            // recorder no longer masquerades as the handler.
            $claim->created_by       = $recorderUser
                ? (string) $recorderUser->id
                : ($handlerUser
                    ? (string) $handlerUser->id
                    : 'claims-tracker:' . ($validated['external_ref'] ?? 'no-ref'));
            // Assigned handler — the V2 list/dashboard read claims.claim_allocated_to
            // with FIRST precedence for the "Claim Handler" column. This was never
            // set on synced claims, so the display fell back to created_by (the
            // recorder). Setting it here is the go-forward half of the handler fix;
            // the one-time remap corrects the already-synced rows.
            $claim->claim_allocated_to = $handlerUser?->id;
            // Persist the tracker's real channel + plate so the V2 list/dashboard
            // stop deriving channel from agent_id and stop showing an empty plate.
            // channel is guarded (added by a paired migration) so a boot before the
            // migration runs can never fatal the sync.
            if (\Illuminate\Support\Facades\Schema::hasColumn('claims', 'channel')) {
                $claim->channel = $validated['channel'] ?? null;
            }
            $claim->vehicle_plate    = $validated['vehicle_plate'] ?? null;
            $claim->ip               = $request->ip();
            $claim->save();

            // Race-safe claim_number: G + year + 6-digit zero-padded PK.
            // Same format as ClaimsV2Controller::store, but keyed off the
            // real auto-increment id rather than max(id)+1.
            $claim->claim_number = 'G' . Carbon::now()->year . str_pad($claim->id, 6, '0', STR_PAD_LEFT);
            $claim->save();

            $this->seedInitialReserve($claim, $policy, $handlerUser);
            $this->writeSubTable($claim, $canonicalType, $validated);
            $this->writeClaimShells($claim, $canonicalType, $policy, $validated, $handlerUser);

            DB::commit();
        } catch (\Illuminate\Database\QueryException $e) {
            DB::rollBack();
            // 1062 = MySQL duplicate-key. Most likely the external_ref
            // unique index caught a concurrent retry.
            if ((int) ($e->errorInfo[1] ?? 0) === 1062 && ! empty($validated['external_ref'])) {
                $existing = Claim::where('external_ref', $validated['external_ref'])->first();
                if ($existing) {
                    return response()->json([
                        'status'       => true,
                        'claim_id'     => $existing->id,
                        'claim_number' => $existing->claim_number,
                        'idempotent'   => true,
                        'message'      => 'Claim already exists for this external_ref',
                    ], 200);
                }
            }
            Log::error('[ClaimsTracker] DB error on claim create', [
                'sql_state' => $e->errorInfo[0] ?? null,
                'code'      => $e->errorInfo[1] ?? null,
                'message'   => $e->getMessage(),
            ]);
            return response()->json([
                'status'  => false,
                'message' => 'Database error',
            ], 500);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('[ClaimsTracker] Claim create failed', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ]);
            return response()->json([
                'status'  => false,
                'message' => 'Internal error creating claim',
            ], 500);
        }

        // Claims automation: gated at DISPATCH so the event and all its
        // listeners stay fully dormant until switched on. Source of truth is
        // the runtime toggle (Admin > Integrations), audited and per-env;
        // defaults off. Never break the response.
        if (\AlphaDirect\Services\IntegrationSettings::isEnabled('claims_automation', false)) {
            try {
                event(new \AlphaDirect\Events\ClaimEvent($claim->id, 'claim_created', [
                    'claim_type' => $canonicalType,
                    'source'     => 'claims-tracker',
                ]));
            } catch (\Throwable $e) {
                Log::warning('[ClaimsTracker] claim_created event dispatch failed', [
                    'claim_id' => $claim->id, 'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'status'       => true,
            'claim_id'     => $claim->id,
            'claim_number' => $claim->claim_number,
            'claim_type'   => $claim->claim_type,
            'policy_id'    => $claim->policy_id,
        ], 201);
    }

    /**
     * Seed the opening reserve header (transaction_type=86, "Initial
     * Reserves") + its coverage row with zero amounts. Mirrors the live
     * ClaimsV2Controller::store (lines 497-517) so every claims-tracker
     * claim has the same opening-balance ledger row the V2 Reserves tab
     * expects. Admins set real reserve values via the UI.
     */
    private function seedInitialReserve(Claim $claim, Policy $policy, ?User $handlerUser): void
    {
        $now = Carbon::now();

        $reserveId = DB::table('claim_reserves')->insertGetId([
            'claim_id'         => $claim->id,
            'date'             => $now->format('Y-m-d'),
            'product_id'       => $policy->product_id ?? null,
            'transaction_type' => 86,
            'payee'            => $handlerUser ? (string) $handlerUser->id : null,
            'created_at'       => $now,
            'updated_at'       => $now,
        ]);

        DB::table('claim_reserves_coverages')->insert([
            'reserve_id'    => $reserveId,
            'claim_id'      => $claim->id,
            'product_id'    => $policy->product_id ?? null,
            'coverage_id'   => 0,
            'coverage_name' => null,
            'reserve_amt'   => 0,
            'payment_amt'   => 0,
            'balance'       => 0,
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);
    }

    /**
     * Persist per-type details into the relevant sub-table when the
     * caller provided them. Sub-tables use $guarded = ['id'] so we can
     * pass through whatever columns the caller knows about; unknown
     * columns will surface as QueryException via the calling try/catch.
     *
     * Types not handled here (FIRE, BAR, EAR, etc.) still get a parent
     * `claims` row created — the description lives in claims.note and
     * Graphite admins fill in the sub-table via the normal UI when
     * they pick up the claim. This is intentional for v1: the long
     * tail of commercial sub-tables is dozens of columns each, and
     * Claims Tracker's day-1 traffic is overwhelmingly motor / life /
     * glass / cellphone / key-loss.
     */
    private function writeSubTable(Claim $claim, string $canonicalType, array $validated): void
    {
        switch ($canonicalType) {
            case 'Motor':      // motor claims now store as 'Motor' (was 'Accident')
            case 'Accident':
            case 'MOTORTRADERSEXTERNAL':
            case 'MOTORTRADERSINTERNAL':
                if (! empty($validated['accident'])) {
                    $row = new ClaimAccident();
                    $row->claim_id = $claim->id;
                    foreach ($validated['accident'] as $col => $val) {
                        $row->{$col} = $val;
                    }
                    $row->save();
                }
                break;

            case 'Life':
                if (! empty($validated['life'])) {
                    $row = new ClaimLife();
                    $row->claim_id = $claim->id;
                    foreach ($validated['life'] as $col => $val) {
                        $row->{$col} = $val;
                    }
                    $row->save();
                }
                break;

            case 'Cellphone':
                if (! empty($validated['cellphone'])) {
                    $row = new ClaimCellphone();
                    $row->claim_id = $claim->id;
                    foreach ($validated['cellphone'] as $col => $val) {
                        $row->{$col} = $val;
                    }
                    $row->save();
                }
                break;

            case 'Key Loss':
                if (! empty($validated['key_loss'])) {
                    $row = new ClaimKeyLoss();
                    $row->claim_id = $claim->id;
                    foreach ($validated['key_loss'] as $col => $val) {
                        $row->{$col} = $val;
                    }
                    $row->save();
                }
                break;

            case 'Glass':
                if (! empty($validated['glass'])) {
                    // glass_claims has no clean Eloquent model, so use the
                    // query builder. Caller-supplied keys must match the
                    // glass_claims schema.
                    DB::table('glass_claims')->insert(array_merge(
                        $validated['glass'],
                        [
                            'user_id'    => $claim->customer_id,
                            'agent_id'   => $claim->agent_id,
                            'policy_id'  => $claim->policy_id,
                            'claimStep'  => 'final',
                            'isActive'   => 1,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    ));
                }
                break;
        }
    }

    /**
     * Seed the placeholder shadow/sub-table rows a Claims Tracker claim needs so
     * both the React app and the legacy Blade admin view work correctly:
     *
     *   - new_claims row  — created for EVERY claim (all types). Graphite's own
     *     create flow always dual-writes new_claims, and ClaimsController::update()
     *     persists Classification & Allocation and the per-type sub-claim tables
     *     keyed off new_claims. Without this row those edits silently no-op for
     *     tracker claims (the bug that surfaced on Glass claim G2026005093).
     *   - claim_accidents / accident_driver rows — motor-only; the legacy motor
     *     Blade view dereferences $claimAccident->approved_otherParty and
     *     $accidentDriver->name without null guards.
     *
     * Placeholders use minimum viable defaults — Claims Tracker doesn't carry
     * Graphite-internal taxonomy (claim_sub_type_id, type_of_loss); admins fill
     * real data via the UI. Idempotent: skips new_claims if one already exists.
     * The opening reserve is NOT recreated here (seedInitialReserve already ran).
     */
    private function writeClaimShells(Claim $claim, string $canonicalType, Policy $policy, array $validated, ?User $handlerUser): void
    {
        $isMotorType    = in_array($canonicalType, ['Motor', 'Accident', 'MOTORTRADERSEXTERNAL', 'MOTORTRADERSINTERNAL'], true);
        $isMotorProduct = in_array((int) $policy->product_id, [7, 8], true);
        $isMotor        = $isMotorType && $isMotorProduct;

        // new_claims shadow row — for ALL claim types, not just motor. Guarded
        // so we never duplicate a row another step may already have written.
        if (! NewClaim::where('claim_number', $claim->claim_number)->exists()) {
            $newClaim = new NewClaim();
            $newClaim->policy_id            = $policy->id;
            $newClaim->policyNumber         = $policy->policyNumber;
            $newClaim->claim_number         = $claim->claim_number;
            $newClaim->is_motor_claim       = $isMotor ? 1 : 0;
            $newClaim->co_attorney_involved = 0;
            $newClaim->attorney_involved    = 0;
            $newClaim->claim_reported_by    = 'Claims Tracker';
            // The Claim Details tab reads the registration off THIS row
            // (ClaimsController::show -> $newClaim->vehicle_plate), not off
            // claims.vehicle_plate, which store() already sets. Without this
            // line the plate is accepted, stored on the parent claim, and the
            // tab a handler actually opens still shows nothing.
            $newClaim->vehicle_plate        = $validated['vehicle_plate'] ?? null;
            $newClaim->claim_type           = 0;
            $newClaim->claim_sub_type_id    = 0;
            $newClaim->type_of_loss         = 0;
            $newClaim->date_of_loss         = Carbon::parse($validated['date_of_loss'])->format('Y-m-d');
            $newClaim->catastrophe_loss     = 0;
            $newClaim->dfs_complaint        = 0;
            $newClaim->claim_approved       = '0';
            $newClaim->status               = 'Pending';
            $newClaim->claim_allocated_to   = $handlerUser ? (string) $handlerUser->id : null;
            $newClaim->created_by           = $handlerUser
                ? (string) $handlerUser->id
                : 'claims-tracker:' . ($validated['external_ref'] ?? 'no-ref');
            $newClaim->save();
        }

        // claim_accidents / accident_driver shells are only needed by the legacy
        // motor Blade admin view, so keep them gated to motor claims.
        if (! $isMotor) {
            return;
        }

        // Only create a claim_accidents shell if the caller didn't already
        // pass an `accident` block via writeSubTable.
        if (! ClaimAccident::where('claim_id', $claim->id)->first()) {
            $accident = new ClaimAccident();
            $accident->claim_id = $claim->id;
            $accident->save();
        }

        // accident_driver shell — the admin edit_accident.blade dereferences
        // $accidentDriver->name without a null guard.
        if (! AccidentDriver::where('claim_id', $claim->id)->first()) {
            $driver = new AccidentDriver();
            $driver->claim_id = $claim->id;
            $driver->save();
        }
    }
}
