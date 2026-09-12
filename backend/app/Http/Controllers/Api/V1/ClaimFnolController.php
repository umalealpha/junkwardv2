<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Models\ClaimFnol;
use AlphaDirect\Policy;
use AlphaDirect\Services\ClaimSla\ClaimStageTimelineService;
use AlphaDirect\Services\IntegrationSettings;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * ClaimFnolController — the First Notification of Loss (FNOL) intake API
 * (Claims Tracker -> Graphite).
 *
 * FNOL is a lightweight pre-claim ledger: it records a reported loss BEFORE it
 * is a registrable Graphite claim (docs missing / policy not yet resolved).
 * Documents are chased by the send-gated `claims:fnol-doc-reminders` command;
 * once complete an FNOL is CONVERTED into a real claim by REUSING the existing
 * ClaimsController::store path (no claim-creation logic is duplicated here).
 *
 * The whole controller is inert until the `claims_fnol` runtime toggle is on
 * (Admin > Integrations, default OFF, env fallback CLAIMS_FNOL_ENABLED). Every
 * endpoint 404s while disabled so the feature ships completely dark — mirrors
 * ClaimSlaController's guard on `claims_sla`.
 *
 * RBAC is enforced at the route (Spatie permission middleware): create/convert
 * require `claim-create`, update/close require `claim-edit`, list/show require
 * `claim-list` — matching how the ClaimsController routes are gated.
 */
class ClaimFnolController extends Controller
{
    // ── GET /api/v1/claims/fnol ──────────────────────────────────────────────
    public function index(Request $request): JsonResponse
    {
        if ($guard = $this->guardFlag()) {
            return $guard;
        }

        $validated = $request->validate([
            'status'   => 'nullable|string|in:open,converted,closed',
            'search'   => 'nullable|string|max:150',
            'page'     => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $perPage = (int) ($validated['per_page'] ?? 15);

        $paginator = ClaimFnol::query()
            ->when($validated['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($validated['search'] ?? null, function ($q, $term) {
                $like = '%' . trim($term) . '%';
                $q->where(function ($w) use ($like) {
                    $w->where('fnol_number', 'like', $like)
                      ->orWhere('claimant_name', 'like', $like)
                      ->orWhere('policy_number', 'like', $like)
                      ->orWhere('external_ref', 'like', $like)
                      ->orWhere('contact_email', 'like', $like);
                });
            })
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json([
            'data' => array_map(
                fn (ClaimFnol $f) => $f->toApiArray(),
                $paginator->items()
            ),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
            ],
        ]);
    }

    // ── POST /api/v1/claims/fnol ─────────────────────────────────────────────
    public function store(Request $request): JsonResponse
    {
        if ($guard = $this->guardFlag()) {
            return $guard;
        }

        // Deliberately RELAXED: only a claimant name + a description are
        // required. The whole point of FNOL is to capture a loss the instant
        // it is reported, before policy / docs / loss-date are known.
        $validated = $request->validate([
            'claimant_name'      => 'required|string|max:255',
            'description'        => 'required|string',
            'policy_number'      => 'nullable|string|max:100',
            'policy_id'          => 'nullable|integer',
            'claim_type'         => 'nullable|string|max:100',
            'loss_date'          => 'nullable|date',
            'reported_date'      => 'nullable|date',
            'claim_allocated_on' => 'nullable|date',
            'contact_phone'      => 'nullable|string|max:40',
            'contact_email'      => 'nullable|email|max:191',
            'estimate_amount'    => 'nullable|numeric',
            'outstanding_docs'   => 'nullable|array',
            'outstanding_docs.*' => 'nullable|string|max:255',
            // Tracker stage-timeline captured at intake (no claim_id yet); a JSON
            // map applied to claim_tracker_workflow on convert. Optional/additive.
            'stage_data'         => 'nullable|array',
            // ── Claims-Tracker "New Claim" Basic-Information fields (all
            //    optional/additive; only sent by the flag-gated tracker-style
            //    create form). Backward-compatible: absent = untouched. ──
        ] + $this->trackerFieldRules());

        $fnol = new ClaimFnol();
        $fnol->fnol_number      = ClaimFnol::nextFnolNumber();
        $fnol->claimant_name    = $validated['claimant_name'];
        $fnol->description      = $validated['description'];
        $fnol->policy_number    = $validated['policy_number'] ?? null;
        $fnol->policy_id        = $validated['policy_id'] ?? $this->resolvePolicyId($validated['policy_number'] ?? null);
        $fnol->claim_type       = $validated['claim_type'] ?? null;
        $fnol->loss_date        = $validated['loss_date'] ?? null;
        $fnol->reported_date    = $validated['reported_date'] ?? null;
        $fnol->claim_allocated_on = $validated['claim_allocated_on'] ?? null;
        $fnol->contact_phone    = $validated['contact_phone'] ?? null;
        $fnol->contact_email    = $validated['contact_email'] ?? null;
        $fnol->estimate_amount  = $validated['estimate_amount'] ?? null;
        $fnol->outstanding_docs = $this->cleanDocs($validated['outstanding_docs'] ?? null);
        $fnol->stage_data       = $this->cleanStageData($validated['stage_data'] ?? null);
        $fnol->status           = ClaimFnol::STATUS_OPEN;
        $fnol->source           = 'manual';
        $fnol->created_by       = $request->user()?->id;
        $fnol->updated_by       = $request->user()?->id;
        $this->applyTrackerFields($fnol, $validated);
        $fnol->save();

        return response()->json(['data' => $fnol->fresh()->toApiArray()], 201);
    }

    // ── GET /api/v1/claims/fnol/{id} ─────────────────────────────────────────
    public function show(int $id): JsonResponse
    {
        if ($guard = $this->guardFlag()) {
            return $guard;
        }
        $fnol = ClaimFnol::find($id);
        if (!$fnol) {
            return response()->json(['message' => 'FNOL not found.'], 404);
        }

        return response()->json(['data' => $fnol->toApiArray()]);
    }

    // ── PUT /api/v1/claims/fnol/{id} ─────────────────────────────────────────
    public function update(Request $request, int $id): JsonResponse
    {
        if ($guard = $this->guardFlag()) {
            return $guard;
        }
        $fnol = ClaimFnol::find($id);
        if (!$fnol) {
            return response()->json(['message' => 'FNOL not found.'], 404);
        }

        $validated = $request->validate([
            'claimant_name'      => 'sometimes|required|string|max:255',
            'description'        => 'sometimes|required|string',
            'policy_number'      => 'sometimes|nullable|string|max:100',
            'policy_id'          => 'sometimes|nullable|integer',
            'claim_type'         => 'sometimes|nullable|string|max:100',
            'loss_date'          => 'sometimes|nullable|date',
            'reported_date'      => 'sometimes|nullable|date',
            'claim_allocated_on' => 'sometimes|nullable|date',
            'contact_phone'      => 'sometimes|nullable|string|max:40',
            'contact_email'      => 'sometimes|nullable|email|max:191',
            'estimate_amount'    => 'sometimes|nullable|numeric',
            'outstanding_docs'   => 'sometimes|nullable|array',
            'outstanding_docs.*' => 'nullable|string|max:255',
            'stage_data'         => 'sometimes|nullable|array',
        ] + $this->trackerFieldRules('sometimes|'));

        // status / fnol_number / converted_claim_id / reminder bookkeeping are
        // intentionally NOT editable here — status transitions go through the
        // convert + close endpoints so the state machine stays clean.
        foreach (['claimant_name', 'description', 'policy_number', 'policy_id',
                  'claim_type', 'loss_date', 'reported_date', 'claim_allocated_on', 'contact_phone', 'contact_email',
                  'estimate_amount'] as $field) {
            if (array_key_exists($field, $validated)) {
                $fnol->{$field} = $validated[$field];
            }
        }
        if (array_key_exists('outstanding_docs', $validated)) {
            $fnol->outstanding_docs = $this->cleanDocs($validated['outstanding_docs']);
        }
        if (array_key_exists('stage_data', $validated)) {
            $fnol->stage_data = $this->cleanStageData($validated['stage_data']);
        }
        $this->applyTrackerFields($fnol, $validated);
        // If a policy_number was set/changed without an explicit policy_id, try
        // to resolve the id so convert can find it later.
        if (array_key_exists('policy_number', $validated)
            && !array_key_exists('policy_id', $validated)
            && $fnol->policy_number) {
            $fnol->policy_id = $this->resolvePolicyId($fnol->policy_number);
        }
        $fnol->updated_by = $request->user()?->id;
        $fnol->save();

        return response()->json(['data' => $fnol->fresh()->toApiArray()]);
    }

    // ── POST /api/v1/claims/fnol/{id}/convert ────────────────────────────────
    public function convert(Request $request, int $id): JsonResponse
    {
        if ($guard = $this->guardFlag()) {
            return $guard;
        }
        $fnol = ClaimFnol::find($id);
        if (!$fnol) {
            return response()->json(['message' => 'FNOL not found.'], 404);
        }
        if ($fnol->status !== ClaimFnol::STATUS_OPEN) {
            return response()->json([
                'message' => "Only an open FNOL can be converted (this one is {$fnol->status}).",
            ], 422);
        }

        // A real claim needs a resolvable policy + a loss date. Enforce here so
        // the operator gets a precise message rather than a raw store() 422.
        $policy = $this->resolvePolicy($fnol);
        if (!$policy) {
            return response()->json([
                'message' => 'FNOL has no resolvable policy. Set a valid policy_id or policy_number before converting.',
            ], 422);
        }
        if (empty($fnol->loss_date)) {
            return response()->json([
                'message' => 'FNOL has no loss_date. Record the date of loss before converting.',
            ], 422);
        }

        // Resolve the tracker's coarse claim-type label (+ non-motor sub-type)
        // into a canonical claims.claim_type ENUM value BEFORE we flip the FNOL
        // to 'converting' — so an unmapped label never strands the FNOL. REFUSE
        // (422) on no mapping, mirroring the tracker's resolveGraphiteSlug guard;
        // this prevents empty-typed claims (the bug this fix closes).
        $resolvedClaimType = $this->resolveClaimType($fnol->claim_type, $fnol->non_motor_sub_type);
        if ($resolvedClaimType === null) {
            $label  = trim((string) $fnol->claim_type);
            $suffix = ($label === 'Non-Motor Claim' && !empty($fnol->non_motor_sub_type))
                ? ' — ' . $fnol->non_motor_sub_type
                : '';
            $human  = $label === '' ? '(none set)' : $label . $suffix;
            return response()->json([
                'message' => "Claim type '{$human}' has no Graphite mapping — cannot register. Contact IT.",
            ], 422);
        }

        // Atomically claim this FNOL for conversion. The compare-and-swap
        // (UPDATE ... WHERE status = 'open') lets only ONE caller flip it
        // open -> converting, so two concurrent converts — or a retry after a
        // mid-convert failure — cannot each create a claim (review: TOCTOU /
        // duplicate-claim window). Everyone else is turned away with a 409.
        $claimed = ClaimFnol::where('id', $fnol->id)
            ->where('status', ClaimFnol::STATUS_OPEN)
            ->update([
                'status'     => ClaimFnol::STATUS_CONVERTING,
                'updated_by' => $request->user()?->id,
                'updated_at' => now(),
            ]);
        if ($claimed === 0) {
            return response()->json([
                'message' => 'This FNOL is already being converted or is no longer open.',
            ], 409);
        }
        $fnol->refresh();

        $lossDate = optional($fnol->loss_date)->format('Y-m-d');

        // REUSE the existing claim-create path — do NOT duplicate claim logic.
        // Build a synthetic request carrying the FNOL data and invoke
        // ClaimsController::store (claim-number generation, new_claims dual-write,
        // audit, etc. all run exactly as for a normal claim creation).
        $payload = array_filter([
            'policy_id'            => $policy->id,
            'claim_type'           => $resolvedClaimType,
            'loss_date'            => $lossDate,
            'incident_date'        => $lossDate,
            'reported_date'        => optional($fnol->reported_date)->format('Y-m-d') ?? now()->format('Y-m-d'),
            'claim_allocated_on'   => optional($fnol->claim_allocated_on)->format('Y-m-d'),
            'reported_by'          => $fnol->claimant_name,
            'incident_description' => $fnol->description,
            'description_of_loss'  => $fnol->description,
        ], fn ($v) => $v !== null && $v !== '');

        $sub = Request::create('/api/v1/claims', 'POST', $payload);
        $sub->headers->set('Accept', 'application/json');
        $sub->setUserResolver(fn () => $request->user());

        try {
            $response = app(ClaimsController::class)->store($sub);
        } catch (ValidationException $e) {
            // Most likely a missing claim_type (store requires it). Surface the
            // field errors so the operator can complete the FNOL then retry.
            $this->releaseConverting($fnol);
            return response()->json([
                'message' => 'Cannot convert — the claim record is incomplete.',
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            // Anything unexpected during claim creation: release the FNOL back to
            // open so it is not stranded in 'converting', then re-throw.
            $this->releaseConverting($fnol);
            throw $e;
        }

        $status = $response->getStatusCode();
        $body   = $response->getData(true);
        if ($status < 200 || $status >= 300) {
            // Claim creation failed — release the FNOL back to open and pass the
            // underlying reason (e.g. policy not active) straight through.
            $this->releaseConverting($fnol);
            return response()->json([
                'message' => $body['message'] ?? 'Claim creation failed during conversion.',
                'errors'  => $body['errors'] ?? null,
            ], $status >= 400 ? $status : 422);
        }

        $claimId     = $body['data']['id'] ?? null;
        $claimNumber = $body['data']['claim_number'] ?? null;

        $fnol->converted_claim_id = $claimId;
        $fnol->status            = ClaimFnol::STATUS_CONVERTED;
        $fnol->updated_by        = $request->user()?->id;
        $fnol->save();

        // If the FNOL carried tracker stage-timeline data (captured on the unified
        // tracker-style create form, which has no claim_id at intake), apply it to
        // the new claim's claim_tracker_workflow row now that the claim exists —
        // reusing the SAME ClaimStageTimelineService the /claims-v2/{id}/sla-timeline
        // PATCH uses. Its array_intersect_key(EDITABLE_FIELDS) whitelist silently
        // drops any unknown/stray key, and date/numeric handling matches the tab.
        // BEST-EFFORT: a stage-write failure must NOT fail the convert — the claim
        // is already created and the FNOL already marked converted; log and move on
        // so the existing convert contract is preserved.
        if ($claimId !== null && is_array($fnol->stage_data) && !empty($fnol->stage_data)) {
            try {
                app(ClaimStageTimelineService::class)->update((int) $claimId, $fnol->stage_data, $request->user());
            } catch (\Throwable $e) {
                Log::warning('FNOL convert: stage_data apply to claim_tracker_workflow failed', [
                    'fnol_id'  => $fnol->id,
                    'claim_id' => $claimId,
                    'error'    => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'data' => [
                'fnol'         => $fnol->fresh()->toApiArray(),
                'claim_id'     => $claimId !== null ? (int) $claimId : null,
                'claim_number' => $claimNumber,
            ],
        ], 201);
    }

    /**
     * Release an FNOL claimed for conversion back to 'open' — but only if it is
     * still 'converting', so a concurrent terminal state is never clobbered.
     */
    private function releaseConverting(ClaimFnol $fnol): void
    {
        ClaimFnol::where('id', $fnol->id)
            ->where('status', ClaimFnol::STATUS_CONVERTING)
            ->update(['status' => ClaimFnol::STATUS_OPEN, 'updated_at' => now()]);
        $fnol->status = ClaimFnol::STATUS_OPEN;
    }

    // ── POST /api/v1/claims/fnol/{id}/close ──────────────────────────────────
    public function close(Request $request, int $id): JsonResponse
    {
        if ($guard = $this->guardFlag()) {
            return $guard;
        }
        $fnol = ClaimFnol::find($id);
        if (!$fnol) {
            return response()->json(['message' => 'FNOL not found.'], 404);
        }

        $validated = $request->validate([
            'reason' => 'nullable|string|max:1000',
        ]);

        $fnol->status     = ClaimFnol::STATUS_CLOSED;
        $fnol->updated_by = $request->user()?->id;
        // Append the close reason to the description as an audit crumb (no extra
        // column needed) when one is supplied.
        if (!empty($validated['reason'])) {
            $fnol->description = rtrim((string) $fnol->description)
                . "\n\n[Closed] " . $validated['reason'];
        }
        $fnol->save();

        return response()->json(['data' => $fnol->fresh()->toApiArray()]);
    }

    // ── Claim-type mapping (tracker -> Graphite ENUM) ─────────────────────────
    //
    // Source of truth: the Claims Tracker's master_data 'graphiteClaimTypeMap'
    // and its resolver (D:\ADRisk\claims\lib\graphiteErp.js resolveGraphiteSlug).
    // Resolver rule (ported verbatim): if claim_type == 'Non-Motor Claim', the
    // lookup key is the non_motor_sub_type; otherwise the key is the claim_type
    // label. No match => null (caller REFUSES the convert with a 422).
    //
    // Targets below are ENUM-canonical (validated against the live
    // claims.claim_type ENUM + the subTableMap in ClaimsController::store). Note
    // the 'Lock & Key' => 'Key Loss' fix: the raw tracker slug was 'key_loss',
    // which is NOT an ENUM member (the ENUM value is 'Key Loss' with a space) and
    // would have coerced to '' — the exact empty-type bug this map closes.
    //
    // ⚠️ FOUR items flagged for Bharath to confirm (shipped as the tracker has
    //    them so behaviour matches the proven tracker->Graphite integration):
    //      1. 'Motor Claim' => 'Accident'  (confirm 'Accident' vs 'Motor')
    //      2. 'Machinery Breakdown' => 'DEFECTIVEWORKMANSHIP' (native likely 'MACHINERYBREAKDOWN')
    //      3. 'Money' => 'LIABILITY' (native likely 'MONEY')
    //      4. 'Accidental Death' => 'PERSONALALLRISKS' (ENUM also has 'Accidental Death')

    /** Coarse claim_type label => canonical claims.claim_type ENUM value. */
    private const COARSE_CLAIM_TYPE_MAP = [
        // Bharath/claims-team CONFIRMED 2026-08-26: a Motor Claim must store as
        // 'Motor', not 'Accident'. Both are valid claims.claim_type ENUM members
        // and neither has a per-type subtable (subTableMap), so this is a pure
        // label change. Existing G-prefixed 'Accident' rows are remapped by a
        // one-time data fix; this closes the go-forward source.
        'Motor Claim' => 'Motor',
        'Glass'       => 'Glass',
        'Lock & Key'  => 'Key Loss',   // FIX: tracker slug 'key_loss' is not an ENUM member
    ];

    /** non_motor_sub_type (when claim_type == 'Non-Motor Claim') => ENUM value. */
    private const NON_MOTOR_CLAIM_TYPE_MAP = [
        'Fire'                         => 'FIRE',
        'Theft'                        => 'THEFT',
        'Workmen Compensation (WCA)'   => 'WORKERSCOMPENSATION',
        'Travel Insurance'            => 'TRAVELINSURANCE',
        'Legal'                        => 'Legal',
        'Hospital Cash Back'           => 'Hospital CashBack',
        'Goods In Transit (GIT)'       => 'GOODSINTRANSIT',
        'Mobile and Electronics'       => 'MOBILEELECTRONICDEVICES',
        'Fidelity'                     => 'FIDELITYGUARANTEE',
        'Defective Workmanship'        => 'DEFECTIVEWORKMANSHIP',
        'Machinery Breakdown'          => 'DEFECTIVEWORKMANSHIP', // TODO(Bharath): confirm — native likely 'MACHINERYBREAKDOWN'
        'Plant All Risk'               => 'PLANTALLRISKS',
        "Contractors' All Risk"        => 'CONTRACTORSALLRISKS',
        'Money'                        => 'LIABILITY',            // TODO(Bharath): confirm — native likely 'MONEY'
        'Commercial-Building'          => 'BUILDINGSCOMBINED',
        'Domestic-Building'            => 'BUILDINGSCOMBINED',
        'Commercial-Contents'          => 'OFFICECONTENTS',
        'Domestic-Contents'            => 'OFFICECONTENTS',
        'All Risk'                     => 'BUSINESSALLRISKS',
        'Accidental Death'             => 'PERSONALALLRISKS',     // TODO(Bharath): confirm — ENUM also has 'Accidental Death'
        'Medical Malpractice'          => 'MEDICALMALPRACTICE',
        // Claims-team v5 #06 additions. "Third Party Only" (a MOTOR cover) and
        // "Homeowners" (duplicate of "Houseowners") were intentionally excluded —
        // confirm the ENUM targets with Bharath before entrenching.
        'Bonu'                         => 'Legal',                // Bonu = a legal-type product
        'Burglary'                     => 'THEFT',                // Graphite groups Burglary under THEFT
        'Houseowners'                  => 'HOUSEOWNERS',
        'Public Liability'             => 'LIABILITY',            // no dedicated PL ENUM; closest is LIABILITY
        // Drift fix: 'Business Interruption' was already in the FE list but missing
        // from this map, so it 422'd on convert. Map it to its ENUM value.
        'Business Interruption'        => 'BUSINESSINTERRUPTION',
    ];

    /**
     * Resolve a tracker claim_type (+ non-motor sub-type) to a canonical
     * claims.claim_type ENUM value, or null when there is no mapping (unknown
     * label, or a Non-Motor claim with a missing/unmapped sub-type). Public so
     * it is unit-testable without a DB.
     */
    public function resolveClaimType(?string $claimType, ?string $subType): ?string
    {
        $label = trim((string) $claimType);
        if ($label === '') {
            return null;
        }
        if ($label === 'Non-Motor Claim') {
            $key = trim((string) $subType);
            if ($key === '') {
                return null;
            }
            return self::NON_MOTOR_CLAIM_TYPE_MAP[$key] ?? null;
        }
        return self::COARSE_CLAIM_TYPE_MAP[$label] ?? null;
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    /**
     * The Claims-Tracker "New Claim" Basic-Information fields, as validation
     * rules. All optional + nullable so the endpoints stay backward-compatible:
     * a payload that omits them (the pre-tracker FNOL client) is unaffected.
     *
     * @param string $prefix '' for store(), 'sometimes|' for the partial update().
     * @return array<string,string>
     */
    private function trackerFieldRules(string $prefix = ''): array
    {
        return [
            'channel'              => $prefix . 'nullable|string|max:20',
            'broker_name'          => $prefix . 'nullable|string|max:255',
            'claims_handler'       => $prefix . 'nullable|string|max:255',
            'plate_number'         => $prefix . 'nullable|string|max:50',
            'reserve_amount'       => $prefix . 'nullable|numeric',
            'claim_paid_amount'    => $prefix . 'nullable|numeric',
            'customer_type'        => $prefix . 'nullable|string|max:50',
            'customer_type_other'  => $prefix . 'nullable|string|max:255',
            'non_motor_sub_type'   => $prefix . 'nullable|string|max:120',
            'assessor'             => $prefix . 'nullable|string|max:255',
            'assessor_other'       => $prefix . 'nullable|string|max:255',
            'glass_supplier'       => $prefix . 'nullable|string|max:255',
            'glass_supplier_other' => $prefix . 'nullable|string|max:255',
            'reinsurer'            => $prefix . 'nullable|string|max:255',
            'is_fac'               => $prefix . 'nullable|boolean',
            'comment_status'       => $prefix . 'nullable|string|max:120',
            'comment_sub_reason'   => $prefix . 'nullable|string|max:120',
        ];
    }

    /** Copy any present tracker Basic-Info fields from validated input onto the model. */
    private function applyTrackerFields(ClaimFnol $fnol, array $validated): void
    {
        foreach (array_keys($this->trackerFieldRules()) as $field) {
            if (array_key_exists($field, $validated)) {
                $fnol->{$field} = $validated[$field];
            }
        }
    }

    /** JsonResponse to short-circuit on (flag off), or null to proceed. */
    private function guardFlag(): ?JsonResponse
    {
        if (!$this->enabled()) {
            return response()->json(['message' => 'FNOL intake is not enabled.'], 404);
        }
        return null;
    }

    private function enabled(): bool
    {
        return IntegrationSettings::isEnabled(
            (string) config('claims_fnol.integration_key', 'claims_fnol'),
            (bool) config('claims_fnol.enabled', false),
        );
    }

    /** Normalise the outstanding-docs list to a clean array of non-empty strings. */
    private function cleanDocs($docs): ?array
    {
        if (!is_array($docs)) {
            return null;
        }
        $clean = array_values(array_filter(array_map(
            fn ($d) => is_string($d) ? trim($d) : null,
            $docs
        ), fn ($d) => $d !== null && $d !== ''));

        return $clean;
    }

    /**
     * Normalise the tracker stage-timeline map to the ClaimStageTimelineService
     * whitelist: keep only known EDITABLE_FIELDS keys with non-empty string
     * values. Anything else (unknown keys, blanks) is dropped, so what we persist
     * is exactly what convert can safely replay. Returns null when nothing valid.
     */
    private function cleanStageData($data): ?array
    {
        if (!is_array($data)) {
            return null;
        }
        $allowed = array_flip(ClaimStageTimelineService::EDITABLE_FIELDS);
        $clean = [];
        foreach ($data as $key => $value) {
            if (!is_string($key) || !isset($allowed[$key])) {
                continue;
            }
            if (is_string($value)) {
                $value = trim($value);
            }
            if ($value === null || $value === '') {
                continue;
            }
            $clean[$key] = is_scalar($value) ? (string) $value : null;
        }
        $clean = array_filter($clean, fn ($v) => $v !== null && $v !== '');

        return $clean === [] ? null : $clean;
    }

    /** Best-effort policy_id lookup from a policyNumber (never throws). */
    private function resolvePolicyId(?string $policyNumber): ?int
    {
        if (!$policyNumber) {
            return null;
        }
        try {
            $id = Policy::where('policyNumber', $policyNumber)->value('id');
            return $id ? (int) $id : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Resolve the FNOL's policy (by id first, then policyNumber). Null if none. */
    private function resolvePolicy(ClaimFnol $fnol): ?Policy
    {
        try {
            if ($fnol->policy_id) {
                $p = Policy::find($fnol->policy_id);
                if ($p) {
                    return $p;
                }
            }
            if ($fnol->policy_number) {
                return Policy::where('policyNumber', $fnol->policy_number)->first();
            }
        } catch (\Throwable $e) {
            return null;
        }
        return null;
    }
}
