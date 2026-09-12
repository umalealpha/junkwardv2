<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Http\Traits\AuthorisesTravelJourney;
use AlphaDirect\Services\Mapfre\MapfreResponse;
use AlphaDirect\Services\Mapfre\MapfreNotConfiguredException;
use AlphaDirect\Services\Mapfre\MapfreTravelClient;
use AlphaDirect\Services\Travel\TravelPolicyNumberGenerator;
use AlphaDirect\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Public Travel Insurance endpoints — the portal's ONLY door to MAPFRE.
 *
 * The Start portal (start-fe-react) never talks to MAPFRE / MAWDY directly:
 * the eMiA + Cognito credentials are server-side secrets and the upstream
 * payload shape is MAPFRE's, not ours. This controller wraps
 * Services\Mapfre\MapfreTravelClient and exposes a stable, snake_case,
 * portal-shaped surface:
 *
 *   POST /public/travel/verify-agent     agent id + PIN + Travel permission gate
 *   GET  /public/travel/packages         cover tiers (config-driven, no upstream call)
 *   GET  /public/travel/trip-types       MAPFRE dealer trip types
 *   GET  /public/travel/destinations     MAPFRE destinations for a trip type
 *   GET  /public/travel/fiscal-id-types  MAPFRE fiscal-id catalogue
 *   POST /public/travel/price            indicative premium for one product
 *   POST /public/travel/price-tiers      EVERY cover tier priced for a trip
 *   POST /public/travel/quote            DEPRECATED — see below
 *   POST /public/travel/contract         issue the policy -> contract number
 *                                        + our own TRVL{YYYY}{NNNNNN} number
 *
 * On /quote: MAPFRE's upstream /quotes answers 403 "country identifier code not
 * valid" for this dealer once the body is schema-valid (verified 2026-08-21 for
 * both BW and ES), so it cannot succeed. Nothing needs it either — /contract is
 * self-contained, taking the whole trip plus every party rather than a quote
 * reference. The portal no longer calls it; kept only so an older client does
 * not 404.
 *
 * Guard rails:
 *   - price / quote / contract require an `agent_id` that resolves to an
 *     ACTIVE user holding the Travel Insurance permission. The journey is
 *     agent-driven (branch / call-centre sale), so an unauthorised agent must
 *     never be able to reach the upstream rating or bind calls.
 *   - contract additionally requires the customer's OTP Bearer session (the
 *     same token the motor confirm step uses) because it mutates state
 *     upstream and must be attributable to a verified cellphone.
 *   - the agent PIN is accepted ONLY by verify-agent and is never persisted,
 *     logged, or forwarded. Downstream calls carry the resolved agent id
 *     only — same contract as the payment-conversion flows.
 *   - upstream failures never leak our base URL / credentials: the Guzzle
 *     exception text is logged, and the portal gets the upstream body's own
 *     business message (when it has one) plus a stable error code.
 */
class PublicTravelController extends Controller
{
    /**
     * TRAVEL_PERMISSION + the agent / OTP-session guards live in the trait so
     * PublicTravelProposalController gates the journey by the same rules.
     */
    use AuthorisesTravelJourney;

    /** Hard bounds on a trip — mirrored in the portal so the UI blocks first. */
    private const MIN_TRIP_DAYS   = 1;
    private const MAX_TRIP_DAYS   = 365;
    private const MAX_TRAVELLERS  = 10;

    public function __construct(
        private MapfreTravelClient $mapfre,
        private TravelPolicyNumberGenerator $policyNumbers,
    ) {}

    // ─── Agent authorisation ────────────────────────────────────────────────

    /**
     * POST /public/travel/verify-agent  { agent_id, pin }
     *
     * Same credential check as the legacy frontendpay/verifyAgent2 (users.pin
     * + users.active), PLUS the Travel Insurance permission check that gates
     * this journey. Returns 200 with { ok:false, error } rather than a bare
     * 401 for the "wrong PIN" / "no permission" cases so the portal can show
     * a specific message per failure without treating it as a transport error.
     */
    public function verifyAgent(Request $request): JsonResponse
    {
        $request->validate([
            'agent_id' => 'required|string|max:16',
            'pin'      => 'required|string|max:16',
        ]);

        $agent = User::where('id', $request->input('agent_id'))
            ->first(['id', 'firstName', 'lastName', 'active', 'pin', 'agency_id']);

        if (!$agent) {
            return response()->json([
                'ok'         => false,
                'error'      => 'agent_not_found',
                'error_type' => 'agent',
                'message'    => 'No agent found with ID: ' . $request->input('agent_id'),
            ]);
        }

        if ($agent->pin === null || (string) $agent->pin !== (string) $request->input('pin')) {
            return response()->json([
                'ok'         => false,
                'error'      => 'invalid_pin',
                'error_type' => 'pin',
                'message'    => 'That PIN does not match agent ID ' . $agent->id . '.',
            ]);
        }

        if ((int) $agent->active !== 1) {
            return response()->json([
                'ok'         => false,
                'error'      => (int) $agent->active === 2 ? 'agent_suspended' : 'agent_inactive',
                'error_type' => 'agent',
                'message'    => (int) $agent->active === 2
                    ? 'Agent suspended with ID: ' . $agent->id
                    : 'Agent is not active with ID: ' . $agent->id,
            ]);
        }

        if (!$this->hasTravelPermission($agent)) {
            Log::info('public_travel.permission_denied', ['agent_id' => $agent->id]);
            return response()->json([
                'ok'         => false,
                'error'      => 'travel_permission_denied',
                'error_type' => 'permission',
                'message'    => 'Agent ' . $agent->id . ' is not authorised to sell Travel Insurance. '
                              . 'Ask your manager to grant the Travel Insurance permission.',
            ]);
        }

        $name = trim(($agent->firstName ?? '') . ' ' . ($agent->lastName ?? ''));

        return response()->json([
            'ok'    => true,
            'agent' => [
                'id'         => (int) $agent->id,
                'name'       => $name,
                'agency_id'  => $agent->agency_id !== null ? (int) $agent->agency_id : null,
                'can_travel' => true,
            ],
            'message' => 'You will be assisted by ' . $name,
        ]);
    }

    // ─── Catalogue ──────────────────────────────────────────────────────────

    /**
     * GET /public/travel/packages
     *
     * The cover tiers the portal offers, each carrying the MAPFRE product id
     * used by /price, /quote and /contract. Purely config-driven — no upstream
     * call, so the portal can render the tier picker even while MAPFRE is
     * unreachable. A tier without a configured id comes back available=false.
     */
    public function packages(): JsonResponse
    {
        $tiers = (array) config('services.mapfre.travel_products', []);

        $packages = [];
        foreach ($tiers as $tier) {
            $id = isset($tier['id']) ? trim((string) $tier['id']) : '';
            $packages[] = [
                'code'       => (string) ($tier['code'] ?? ''),
                'name'       => (string) ($tier['name'] ?? ''),
                'blurb'      => (string) ($tier['blurb'] ?? ''),
                'product_id' => $id !== '' ? $id : null,
                'available'  => $id !== '',
            ];
        }

        return response()->json([
            'ok'       => true,
            'packages' => $packages,
        ]);
    }

    /** GET /public/travel/trip-types */
    public function tripTypes(): JsonResponse
    {
        return $this->relay(fn () => $this->mapfre->tripTypes(), 'trip_types');
    }

    /** GET /public/travel/destinations?trip_type=2 */
    public function destinations(Request $request): JsonResponse
    {
        $request->validate(['trip_type' => 'required|string|max:32']);

        $tripType = (string) $request->query('trip_type');

        return $this->relay(fn () => $this->mapfre->destinations($tripType), 'destinations');
    }

    /** GET /public/travel/fiscal-id-types */
    public function fiscalIdTypes(): JsonResponse
    {
        return $this->relay(fn () => $this->mapfre->fiscalIdTypes(), 'fiscal_id_types');
    }

    // ─── Rating / quote / bind ──────────────────────────────────────────────

    /**
     * POST /public/travel/price — indicative premium, nothing persisted.
     * Screen 1 calls this every time the trip inputs settle.
     */
    /**
     * POST /public/travel/price-tiers — price EVERY cover tier for one trip.
     *
     * Screen 1's "Package / cover tier" card is populated from this: MAPFRE
     * returns its own product id, name, price and cover list per tier, so the
     * tiers are discovered at runtime instead of being declared with
     * MAPFRE_TRAVEL_PRODUCT_* env vars (which MAPFRE never issued).
     *
     * Shares buildPricePayload() with /price: MAPFRE's /price schema — verified
     * live on 2026-08-20 — takes no traveller array, only a traveller COUNT.
     * The tier variant simply posts to /product/ALL/price instead of one id.
     */
    public function priceTiers(Request $request): JsonResponse
    {
        $request->validate([
            'agent_id'      => 'required|string|max:16',
            'trip_type'     => 'required|string|max:32',
            'destination'   => 'required|string|max:64',
            'start_date'    => 'required|date_format:Y-m-d',
            'end_date'      => 'required|date_format:Y-m-d',
            'travellers'    => 'required|integer|min:1|max:' . self::MAX_TRAVELLERS,
            'travellers_over'=> 'nullable|integer|min:0|max:' . self::MAX_TRAVELLERS,
            'promo_code'    => 'nullable|string|max:32',
        ]);

        if ($denied = $this->denyUnlessTravelAgent($request)) return $denied;

        [$body, $error] = $this->buildPricePayload($request);
        if ($error) return $error;

        $response = $this->call(fn () => $this->mapfre->priceAll($body));
        if ($response instanceof JsonResponse) return $response;

        if (!$response->isSuccess()) {
            return $this->upstreamFailure($response, 'price_tiers');
        }

        return response()->json([
            'ok'    => true,
            'tiers' => $this->normaliseTiers($response->data()),
        ]);
    }

    public function price(Request $request): JsonResponse
    {
        // Same body as /price-tiers — MAPFRE's /product/{id}/price and
        // /product/ALL/price share one schema; the only difference is which
        // product the path names. `travellers` may arrive as the trip form's
        // DOB array or as a bare count: buildPricePayload() takes either,
        // because MAPFRE prices on headcount and wants no traveller list.
        $request->validate([
            'agent_id'         => 'required|string|max:16',
            // MAPFRE keys /product/{x}/price on its productCode
            // (TRI25it21000002), NOT the numeric id that comes back beside it —
            // a numeric id answers 422 "Wrong product code." `product_id` is
            // kept as an alias for callers written before that was known.
            'product_code'     => 'required_without:product_id|nullable|string|max:64',
            'product_id'       => 'required_without:product_code|nullable|string|max:64',
            'trip_type'        => 'required|string|max:32',
            'destination'      => 'required|string|max:64',
            'start_date'       => 'required|date_format:Y-m-d',
            'end_date'         => 'required_without:duration_days|nullable|date_format:Y-m-d',
            'duration_days'    => 'required_without:end_date|nullable|integer|min:' . self::MIN_TRIP_DAYS . '|max:' . self::MAX_TRIP_DAYS,
            'travellers'       => 'required',
            'travellers.*.dob' => 'required|date_format:Y-m-d|before:today',
            'travellers_over'  => 'nullable|integer|min:0|max:' . self::MAX_TRAVELLERS,
            'promo_code'       => 'nullable|string|max:32',
        ]);

        if ($denied = $this->denyUnlessTravelAgent($request)) return $denied;

        [$trip, $error] = $this->buildPricePayload($request);
        if ($error) return $error;

        $productCode = (string) ($request->input('product_code') ?: $request->input('product_id'));
        $response    = $this->call(fn () => $this->mapfre->price($productCode, $trip));
        if ($response instanceof JsonResponse) return $response;

        if (!$response->isSuccess()) {
            return $this->upstreamFailure($response, 'price', ['product_code' => $productCode]);
        }

        return response()->json([
            'ok'   => true,
            'data' => $this->normalisePremium($response->data()),
        ]);
    }

    /**
     * POST /public/travel/quote — validate the trip upstream and persist a
     * MAPFRE quote. Returns the quote_id + token that /contract needs.
     */
    public function quote(Request $request): JsonResponse
    {
        // Same body as /contract — verified 2026-08-21: MAPFRE's /quotes
        // accepts the identical trip + parties payload and answers with
        // { quoteId, productCode, productName, token, dealerAccountCode }.
        // Non-binding, so no OTP session is required (unlike /contract).
        $request->validate([
            'agent_id'        => 'required|string|max:16',
            'product_code'    => 'required|string|max:64',
            'trip_type'       => 'required|string|max:32',
            'destination'     => 'required|string|max:64',
            'start_date'      => 'required|date_format:Y-m-d',
            'end_date'        => 'required|date_format:Y-m-d',
            'travellers_over' => 'nullable|integer|min:0|max:' . self::MAX_TRAVELLERS,

            'holder.first_name'  => 'required|string|max:60',
            'holder.middle_name' => 'nullable|string|max:60',
            'holder.last_name'   => 'required|string|max:60',
            'holder.id_type'     => 'required|string|in:Omang,Passport',
            'holder.omang'       => 'required_if:holder.id_type,Omang|nullable|string|max:40',
            'holder.passport'    => 'required_if:holder.id_type,Passport|nullable|string|max:40',
            'holder.dob'         => 'required|date_format:Y-m-d|before:today',
            'holder.mobile'      => 'required|string|max:24',
            'holder.email'       => 'required|email|max:160',
            'holder.address'     => 'required|string|max:250',

            'travellers'                  => 'nullable|array|max:' . (self::MAX_TRAVELLERS - 1),
            'travellers.*.first_name'     => 'required|string|max:60',
            'travellers.*.last_name'      => 'required|string|max:60',
            'travellers.*.dob'            => 'required|date_format:Y-m-d|before:today',
            'travellers.*.fiscal_id'      => 'required|string|max:40',
            'travellers.*.fiscal_id_type' => 'required|string|max:40',

            'contact.name'   => 'nullable|string|max:120',
            'contact.email'  => 'nullable|email|max:160',
            'contact.mobile' => 'nullable|string|max:24',
        ]);

        if ($denied = $this->denyUnlessTravelAgent($request)) return $denied;

        [$payload, $error] = $this->buildContractPayload($request);
        if ($error) return $error;

        // A quote must never trigger customer email — only the bind does.
        $payload['sendEmail'] = false;

        $productCode = (string) $request->input('product_code');
        $response    = $this->call(fn () => $this->mapfre->quote($productCode, $payload));
        if ($response instanceof JsonResponse) return $response;

        if (!$response->isSuccess()) {
            return $this->upstreamFailure($response, 'quote', ['product_code' => $productCode]);
        }

        $data    = $response->data();
        $quoteId = (string) ($data['quoteId'] ?? '');
        if ($quoteId === '') {
            // A 2xx with no quoteId is unusable downstream — treat it as an
            // upstream fault rather than handing the portal a hollow success.
            Log::error('public_travel.quote_missing_id', ['product_code' => $productCode]);
            return response()->json([
                'ok'      => false,
                'error'   => 'mapfre_quote_incomplete',
                'message' => 'MAPFRE accepted the quote but returned no quote reference. Please retry.',
            ], 502);
        }

        return response()->json([
            'ok'   => true,
            'data' => [
                'quote_id'     => $quoteId,
                'quote_token'  => (string) ($data['token'] ?? ''),
                'product_code' => (string) ($data['productCode'] ?? $productCode),
                'product_name' => (string) ($data['productName'] ?? ''),
                'dealer_code'  => (string) ($data['dealerAccountCode'] ?? ''),
                'upstream'     => $data,
            ],
        ]);
    }

    /**
     * POST /public/travel/contract — bind the quote into a MAPFRE contract.
     *
     * Requires the customer's OTP Bearer session on top of the agent check:
     * this is the state-changing step, so it must be attributable to a
     * verified cellphone the same way policy create is.
     */
    public function contract(Request $request): JsonResponse
    {
        // MAPFRE's /contract is self-contained: the whole trip AND every party
        // travels in this one body. There is no "bind a saved quote" step —
        // /quotes answers 403 "country identifier code not valid" for BW (and
        // ES), so the working flow is price -> contract.
        $request->validate([
            'agent_id'        => 'required|string|max:16',
            // The PATH segment is MAPFRE's productCode (e.g. TRI25it21000002),
            // NOT the numeric product id that /product/ALL/price also returns.
            'product_code'    => 'required|string|max:64',
            'trip_type'       => 'required|string|max:32',
            'destination'     => 'required|string|max:64',
            'start_date'      => 'required|date_format:Y-m-d',
            'end_date'        => 'required|date_format:Y-m-d',
            'travellers_over' => 'nullable|integer|min:0|max:' . self::MAX_TRAVELLERS,
            // From /quote. Optional — MAPFRE's contract does NOT bind against a
            // quote — but when present it keys the audit row, which is far more
            // traceable than a hash of the trip.
            'quote_id'        => 'nullable|string|max:64',

            // ─ Policyholder: comes straight from the portal's Customer card.
            'holder.first_name'  => 'required|string|max:60',
            'holder.middle_name' => 'nullable|string|max:60',
            'holder.last_name'   => 'required|string|max:60',
            'holder.id_type'     => 'required|string|in:Omang,Passport',
            'holder.omang'       => 'required_if:holder.id_type,Omang|nullable|string|max:40',
            'holder.passport'    => 'required_if:holder.id_type,Passport|nullable|string|max:40',
            'holder.dob'         => 'required|date_format:Y-m-d|before:today',
            'holder.mobile'      => 'required|string|max:24',
            'holder.email'       => 'required|email|max:160',
            'holder.address'     => 'required|string|max:250',

            // ─ Travellers 2..N (the policyholder is traveller 1).
            'travellers'                    => 'nullable|array|max:' . (self::MAX_TRAVELLERS - 1),
            'travellers.*.first_name'       => 'required|string|max:60',
            'travellers.*.last_name'        => 'required|string|max:60',
            'travellers.*.dob'              => 'required|date_format:Y-m-d|before:today',
            'travellers.*.fiscal_id'        => 'required|string|max:40',
            'travellers.*.fiscal_id_type'   => 'required|string|max:40',
            'travellers.*.email'            => 'nullable|email|max:160',
            'travellers.*.mobile'           => 'nullable|string|max:24',

            // ─ Contact person. Optional: defaults to the policyholder.
            'contact.name'   => 'nullable|string|max:120',
            'contact.email'  => 'nullable|email|max:160',
            'contact.mobile' => 'nullable|string|max:24',
        ]);

        $session = $this->requireOtpSession($request);
        if ($session instanceof JsonResponse) return $session;

        if ($denied = $this->denyUnlessTravelAgent($request)) return $denied;

        [$payload, $error] = $this->buildContractPayload($request);
        if ($error) return $error;

        $productCode = (string) $request->input('product_code');

        // The reference keys the mapfre_quote_submissions audit row that stops
        // the same sale being bound twice. It MUST be unique per sale: the
        // client's own fallback is productCode-based, so every contract for a
        // tier would collide on one reference and defeat the guard entirely.
        // Prefer MAPFRE's quote id (traceable straight back to /quote); fall
        // back to a hash of the trip when the agent skipped the quote step.
        // Derived, never random, so a replayed request collides deliberately.
        $quoteId = (string) $request->input('quote_id', '');
        $reference = $quoteId !== ''
            ? 'AD-TRV-' . $quoteId
            : 'AD-TRV-' . substr(hash('sha256', implode('|', [
                $productCode,
                (string) $request->input('holder.id_type'),
                (string) ($request->input('holder.omang') ?: $request->input('holder.passport')),
                (string) $request->input('start_date'),
                (string) $request->input('end_date'),
                (string) $request->input('destination'),
            ])), 0, 24);
        $payload['reference'] = $reference;
        // Recorded on the audit row by MapfreTravelClient::contract(). Verified
        // 2026-08-21 that MAPFRE tolerates unknown body fields (both this and
        // `reference` returned 200 on /quotes), so carrying it is safe.
        if ($quoteId !== '') $payload['quoteId'] = $quoteId;

        $response = $this->call(fn () => $this->mapfre->contract($productCode, $payload));
        if ($response instanceof JsonResponse) return $response;

        if (!$response->isSuccess()) {
            return $this->upstreamFailure($response, 'contract', [
                'product_code' => $productCode,
                'reference'    => $reference,
            ]);
        }

        $data = $response->data();

        // The policy now exists, so it gets an Alpha Direct policy number:
        // TRVL{year}{6-digit sequence}. Minted AFTER the bind succeeds, so the
        // many ways a contract can be rejected upstream don't each burn a
        // number. Keyed on $reference, so a replayed bind reuses its number
        // rather than taking a second one.
        //
        // Best-effort by design: the contract is already bound upstream at this
        // point, so a counter failure must not turn a sold policy into an error
        // the agent will retry. It is logged at error level and the portal
        // renders the MAPFRE contract number alone until it is backfilled.
        $policyNumber = null;
        try {
            $policyNumber = $this->policyNumbers->assignTo($reference);
        } catch (\Throwable $e) {
            Log::error('public_travel.policy_number_mint_failed', [
                'reference'       => $reference,
                'contract_number' => $data['contractNumber'] ?? null,
                'msg'             => $e->getMessage(),
            ]);
        }

        Log::info('public_travel.contract_bound', [
            'agent_id'        => $request->input('agent_id'),
            'cellphone'       => $session['cellphone'] ?? null,
            'product_code'    => $productCode,
            'reference'       => $reference,
            'quote_id'        => $quoteId !== '' ? $quoteId : null,
            'contract_number' => $data['contractNumber'] ?? null,
            'policy_number'   => $policyNumber,
        ]);

        return response()->json([
            'ok'   => true,
            'data' => [
                'contract_number' => (string) ($data['contractNumber'] ?? ''),
                // Ours; MAPFRE's contractNumber is theirs. Null only if the
                // mint failed — see the log line above.
                'policy_number'   => $policyNumber,
                'product_name'    => (string) ($data['productName'] ?? ''),
                'reference'       => $reference,
                'quote_id'        => $quoteId,
                'upstream'        => $data,
            ],
        ], 201);
    }

    // ─── internals ──────────────────────────────────────────────────────────

    /**
     * Pull the fields the portal actually renders out of a MAPFRE price /
     * quote body, and pass the decoded body through as `upstream` so a tier
     * detail we haven't modelled yet is still displayable without a redeploy.
     */
    /**
     * Build MAPFRE's /price body. Verified against PRE on 2026-08-20 by walking
     * their JSON-schema validator, so these names and formats are exact:
     *
     *   departureDate / arrivalDate  DD/MM/YYYY  (ISO is rejected outright)
     *   tripType / destination       catalogue `code` values
     *   numberOfTravelers            integer, not string
     *   numberOfTravelersOver        integer, <= numberOfTravelers
     *   tripPrice / promoCode        present in MAPFRE's own sample body
     *
     * NOTE: no traveller array and no product id — pricing is by headcount.
     * `numberOfTravelersOver` is a subset count whose age threshold MAPFRE has
     * not documented; it defaults to 0 until they confirm it.
     *
     * @return array{0: array, 1: ?JsonResponse}
     */
    private function buildPricePayload(Request $request): array
    {
        $start = Carbon::createFromFormat('Y-m-d', (string) $request->input('start_date'))->startOfDay();

        if ($request->filled('end_date')) {
            $end = Carbon::createFromFormat('Y-m-d', (string) $request->input('end_date'))->startOfDay();
        } else {
            // duration_days counts days of cover INCLUSIVE of the start date,
            // so a 1-day trip starts and ends on the same date.
            $end = $start->copy()->addDays(max(0, (int) $request->input('duration_days') - 1));
        }

        if ($end->lt($start)) {
            return [[], $this->reject('invalid_trip_dates', 'The travel end date cannot be before the start date.')];
        }
        if ($start->lt(Carbon::today())) {
            return [[], $this->reject('start_date_in_past', 'The travel start date cannot be in the past.')];
        }

        $days = $start->diffInDays($end) + 1;
        if ($days < self::MIN_TRIP_DAYS || $days > self::MAX_TRIP_DAYS) {
            return [[], $this->reject(
                'invalid_trip_duration',
                'A trip must be between ' . self::MIN_TRIP_DAYS . ' and ' . self::MAX_TRIP_DAYS . ' days; this one is ' . $days . '.',
            )];
        }

        // Either shape: the trip form posts a DOB row per traveller, the tier
        // picker posts a count. MAPFRE only ever wants the count.
        $posted     = $request->input('travellers');
        $travellers = is_array($posted) ? count($posted) : (int) $posted;
        if ($travellers < 1 || $travellers > self::MAX_TRAVELLERS) {
            return [[], $this->reject(
                'invalid_traveller_count',
                'A trip must cover between 1 and ' . self::MAX_TRAVELLERS . ' travellers.',
            )];
        }
        $over       = (int) $request->input('travellers_over', 0);
        // MAPFRE rejects the whole request when the "over" subset exceeds the
        // total, with a message that names neither field — clamp instead.
        $over = max(0, min($over, $travellers));

        return [[
            'tripType'              => (string) $request->input('trip_type'),
            'destination'           => (string) $request->input('destination'),
            'departureDate'         => $start->format('d/m/Y'),
            'arrivalDate'           => $end->format('d/m/Y'),
            'numberOfTravelers'     => $travellers,
            'numberOfTravelersOver' => $over,
            'tripPrice'             => 0,
            'promoCode'             => (string) $request->input('promo_code', ''),
        ], null];
    }

    /**
     * Build MAPFRE's /contract body from the portal's own fields.
     *
     * `infoPolicyHolder` is mapped ENTIRELY from the Customer card the agent
     * already fills in (name, ID type + Omang/passport, DOB, mobile, email,
     * residential address) — no extra fields are asked for. Fields MAPFRE
     * accepts but Botswana has no natural source for (addressNumber, postCode,
     * town, province) are OMITTED rather than filled with invented values;
     * none of them is schema-required. Verified required set on /contract is
     * arrivalDate, departureDate, destination, numberOfTravelers, sendEmail.
     *
     * The policyholder is traveller 1 (`isTraveller: true`), so
     * `numberOfTravelers` = 1 + the extra travellers, and `infoTravellers`
     * carries ONLY those extras — matching MAPFRE's own sample where
     * numberOfTravelers=2 with a single infoTravellers entry.
     *
     * @return array{0: array, 1: ?JsonResponse}
     */
    private function buildContractPayload(Request $request): array
    {
        $start = Carbon::createFromFormat('Y-m-d', (string) $request->input('start_date'))->startOfDay();
        $end   = Carbon::createFromFormat('Y-m-d', (string) $request->input('end_date'))->startOfDay();

        if ($end->lt($start)) {
            return [[], $this->reject('invalid_trip_dates', 'The travel end date cannot be before the start date.')];
        }
        if ($start->lt(Carbon::today())) {
            return [[], $this->reject('start_date_in_past', 'The travel start date cannot be in the past.')];
        }
        $days = $start->diffInDays($end) + 1;
        if ($days < self::MIN_TRIP_DAYS || $days > self::MAX_TRIP_DAYS) {
            return [[], $this->reject(
                'invalid_trip_duration',
                'A trip must be between ' . self::MIN_TRIP_DAYS . ' and ' . self::MAX_TRIP_DAYS . ' days; this one is ' . $days . '.',
            )];
        }

        $extras = (array) $request->input('travellers', []);
        $total  = 1 + count($extras);
        if ($total > self::MAX_TRAVELLERS) {
            return [[], $this->reject('too_many_travellers', 'A trip may cover at most ' . self::MAX_TRAVELLERS . ' travellers.')];
        }
        $over = max(0, min((int) $request->input('travellers_over', 0), $total));

        $idType   = (string) $request->input('holder.id_type');
        $holderId = $idType === 'Passport'
            ? (string) $request->input('holder.passport')
            : (string) $request->input('holder.omang');

        $firstNames = trim(implode(' ', array_filter([
            (string) $request->input('holder.first_name'),
            (string) $request->input('holder.middle_name'),
        ])));
        $surname   = (string) $request->input('holder.last_name');
        $email     = (string) $request->input('holder.email');
        $mobile    = (string) $request->input('holder.mobile');
        $prefix    = (string) config('services.mapfre.mobile_prefix', '267');

        $address = (string) $request->input('holder.address');

        $holder = [
            'fiscalIdType' => $this->mapfreFiscalIdType($idType),
            'fiscalId'     => $holderId,
            'name'         => $firstNames,
            'surname'      => $surname,
            'email'        => $email,
            'address'      => $address,
            // MAPFRE makes addressNumber SCHEMA-REQUIRED on /quotes and
            // /contract (omitting it returns 400 'object has missing required
            // properties (["addressNumber"])'). The portal captures one free-text
            // residential address, so derive the plot/street number from it —
            // "Plot 4567, Extension 12" -> "4567". Botswana addresses are often
            // plot-only with no separate number, hence the "0" fallback: MAPFRE
            // needs the key present, and the full address is sent verbatim above.
            'addressNumber' => $this->deriveAddressNumber($address),
            'birthDate'    => Carbon::createFromFormat('Y-m-d', (string) $request->input('holder.dob'))->format('d/m/Y'),
            'mobilePrefix' => $prefix,
            'mobile'       => $mobile,
            'isTraveller'  => true,
        ];

        $travellers = [];
        foreach ($extras as $t) {
            $row = [
                'fiscalIdType' => (string) ($t['fiscal_id_type'] ?? ''),
                'fiscalId'     => (string) ($t['fiscal_id'] ?? ''),
                'name'         => (string) ($t['first_name'] ?? ''),
                'surname'      => (string) ($t['last_name'] ?? ''),
                'birthDate'    => Carbon::createFromFormat('Y-m-d', (string) ($t['dob'] ?? ''))->format('d/m/Y'),
                'mobilePrefix' => $prefix,
            ];
            // MAPFRE requires contact details on EVERY passenger — omitting
            // them returns 422 "Some of the passengers are missing some of the
            // required fields." The portal's traveller rows capture name / DOB
            // / ID only, so fall back to the policyholder's email and mobile:
            // this is an agent-assisted sale of ONE trip, and MAPFRE uses these
            // for assistance contact, not for identifying the traveller.
            $row['email']  = !empty($t['email'])  ? (string) $t['email']  : $email;
            $row['mobile'] = !empty($t['mobile']) ? (string) $t['mobile'] : $mobile;
            $travellers[] = $row;
        }

        // Contact person defaults to the policyholder — the portal does not ask
        // for a separate one, and MAPFRE uses it for assistance callbacks.
        $contact = [
            'name'   => (string) ($request->input('contact.name')   ?: trim($firstNames . ' ' . $surname)),
            'email'  => (string) ($request->input('contact.email')  ?: $email),
            'mobile' => (string) ($request->input('contact.mobile') ?: $mobile),
        ];

        return [[
            'tripType'              => (string) $request->input('trip_type'),
            'destination'           => (string) $request->input('destination'),
            'departureDate'         => $start->format('d/m/Y'),
            'arrivalDate'           => $end->format('d/m/Y'),
            'numberOfTravelers'     => $total,
            'numberOfTravelersOver' => $over,
            'tripPrice'             => 0,
            'promoCode'             => '',
            'sendEmail'             => true,
            'infoPolicyHolder'      => $holder,
            'infoTravellers'        => $travellers,
            'infoContactPerson'     => $contact,
        ], null];
    }

    /**
     * First run of digits in a free-text address, for MAPFRE's required
     * `addressNumber`. Falls back to "0" when the address carries no number.
     */
    private function deriveAddressNumber(string $address): string
    {
        return preg_match('/\d+/', $address, $m) ? $m[0] : '0';
    }

    /**
     * Map the portal's ID type onto a MAPFRE fiscal-id code.
     *
     * MAPFRE's /catalog/fiscalIdTypes for this dealer is a LATAM list (CPF,
     * CNPJ, RUT, RFC, DIMEX…) with NO Omang entry, so Passport -> 2
     * (PASAPORTE) is the only mapping we can make with confidence. The Omang
     * code is config-driven and defaults to PASAPORTE so the journey is not
     * blocked, pending MAPFRE telling us which code an Omang should carry.
     */
    private function mapfreFiscalIdType(string $portalIdType): string
    {
        if ($portalIdType === 'Passport') {
            return (string) config('services.mapfre.fiscal_id_type_passport', '2');
        }

        return (string) config('services.mapfre.fiscal_id_type_omang', '2');
    }

    /**
     * Flatten MAPFRE's /product/ALL/price response — a top-level array, one
     * entry per cover tier — into the portal's snake_case shape.
     */
    private function normaliseTiers(array $rows): array
    {
        // Guard against MAPFRE wrapping the list in a envelope in some future
        // version; today the array IS the response body.
        if (isset($rows['data']) && is_array($rows['data'])) $rows = $rows['data'];

        $tiers = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['id'])) continue;
            $price = is_array($row['priceData'] ?? null) ? $row['priceData'] : [];

            $tiers[] = [
                'product_id'   => (string) $row['id'],
                'product_code' => isset($row['productCode']) ? (string) $row['productCode'] : null,
                'name'         => isset($row['productName']) ? (string) $row['productName'] : null,
                'gross_price'  => isset($price['grossPrice']) ? (float) $price['grossPrice'] : null,
                'net_price'    => isset($price['netPrice'])   ? (float) $price['netPrice']   : null,
                'taxes'        => isset($price['taxes'])      ? (float) $price['taxes']      : null,
                'currency'     => (string) ($price['currency'] ?? 'BWP'),
                'covers'       => array_values(array_filter(array_map(
                    fn ($c) => is_array($c) && isset($c['coverName'])
                        ? ['code' => (string) ($c['coverCode'] ?? ''), 'name' => (string) $c['coverName']]
                        : null,
                    is_array($row['infoCover'] ?? null) ? $row['infoCover'] : [],
                ))),
                'upstream'     => $row,
            ];
        }

        return $tiers;
    }

    private function normalisePremium(array $data): array
    {
        // MAPFRE's /product/{code}/price answers with the SAME shape as
        // /product/ALL/price — a LIST carrying one row, with the money nested
        // under priceData — not a flat premium object. Verified live
        // 2026-08-26: reading totalPremium/currency off the top level yielded
        // total_premium null and currency "BWP" on a perfectly good 200.
        $tier = $this->normaliseTiers($data)[0] ?? null;
        if ($tier !== null) {
            return [
                'total_premium' => $tier['gross_price'],
                'currency'      => $tier['currency'],
                'product_code'  => $tier['product_code'],
                'product_name'  => $tier['name'],
                'upstream'      => $tier['upstream'],
            ];
        }

        $premium = $data['totalPremium'] ?? $data['premium'] ?? $data['total'] ?? null;

        return [
            'total_premium' => $premium !== null ? (float) $premium : null,
            'currency'      => (string) ($data['currency'] ?? 'BWP'),
            'product_code'  => isset($data['productCode']) ? (string) $data['productCode'] : null,
            'product_name'  => isset($data['productName']) ? (string) $data['productName'] : null,
            'upstream'      => $data,
        ];
    }

    /** GET relay: run the call, hand a success body straight back under $key. */
    private function relay(callable $fn, string $key): JsonResponse
    {
        $response = $this->call($fn);
        if ($response instanceof JsonResponse) return $response;

        if (!$response->isSuccess()) {
            return $this->upstreamFailure($response, $key);
        }

        $data = $response->data();
        // MAPFRE returns bare JSON arrays for the catalogue endpoints; some
        // envs wrap them in { data: [...] }. Normalise to a flat list.
        $list = array_key_exists('data', $data) && is_array($data['data']) ? $data['data'] : $data;

        return response()->json([
            'ok' => true,
            $key => array_values($list),
        ]);
    }

    /**
     * Run a MapfreTravelClient call, converting its non-response outcomes into
     * portal-safe error bodies.
     *
     *   - credential missing        -> 503 travel_not_configured
     *   - auth rejected / timeout   -> 502 travel_unavailable
     *   - integration switched off  -> 503 travel_integration_disabled
     *
     * The first two were previously one branch, so an authentication failure
     * was reported as a configuration gap.
     *
     * @return MapfreResponse|JsonResponse
     */
    private function call(callable $fn)
    {
        try {
            $response = $fn();
        } catch (MapfreNotConfiguredException $e) {
            // A credential is genuinely absent in this environment.
            // Operational, not the customer's problem.
            Log::error('public_travel.not_configured', [
                'msg'        => $e->getMessage(),
                'config_key' => $e->configKey,
            ]);
            return response()->json([
                'ok'      => false,
                'error'   => 'travel_not_configured',
                'message' => 'Travel Insurance is not configured on this environment yet.',
            ], 503);
        } catch (\RuntimeException $e) {
            // Reached MAPFRE (or tried to) and it did not authenticate: a
            // rejected client secret, an expired password, a token response
            // without access_token, a timeout. These used to fall into the
            // branch above and be reported as `travel_not_configured`, which
            // sent people checking env vars that were already correct. It is
            // an availability problem, so it is reported as one.
            Log::error('public_travel.auth_failed', ['msg' => $e->getMessage()]);
            return response()->json([
                'ok'      => false,
                'error'   => 'travel_unavailable',
                'message' => 'Travel Insurance is temporarily unavailable. Please try again.',
            ], 502);
        } catch (\Throwable $e) {
            Log::error('public_travel.client_error', ['msg' => $e->getMessage()]);
            return response()->json([
                'ok'      => false,
                'error'   => 'travel_unavailable',
                'message' => 'Travel Insurance is temporarily unavailable. Please try again.',
            ], 502);
        }

        if (!$response->isSuccess()
            && $response->httpStatus === null
            && str_contains((string) $response->error, 'disabled')) {
            return response()->json([
                'ok'      => false,
                'error'   => 'travel_integration_disabled',
                'message' => 'Travel Insurance is currently switched off. Please try again later.',
            ], 503);
        }

        return $response;
    }

    /**
     * Map an upstream MAPFRE failure onto a portal-safe response.
     *
     *   4xx -> 422 mapfre_rejected  (business/validation feedback the user can
     *          fix and retry) — except 401/403, which is OUR credential
     *          problem and surfaces as a 502.
     *   5xx / transport -> 502 mapfre_unavailable.
     *
     * The Guzzle exception text is logged, never returned: it embeds our
     * MAPFRE base URL. Only the upstream body's own message is passed on.
     */
    private function upstreamFailure(MapfreResponse $response, string $op, array $context = []): JsonResponse
    {
        $status = $response->httpStatus;

        Log::warning('public_travel.upstream_failed', array_merge($context, [
            'op'    => $op,
            'http'  => $status,
            'error' => $response->error,
        ]));

        $upstreamMessage = $this->upstreamMessage($response->rawExcerpt);

        if ($status !== null && $status >= 400 && $status < 500 && !in_array($status, [401, 403], true)) {
            return response()->json([
                'ok'            => false,
                'error'         => 'mapfre_rejected',
                'message'       => $upstreamMessage ?? 'MAPFRE could not accept these trip details. Please check them and try again.',
                'upstream_http' => $status,
            ], 422);
        }

        return response()->json([
            'ok'            => false,
            'error'         => 'mapfre_unavailable',
            'message'       => $upstreamMessage ?? 'MAPFRE is not responding right now. Please try again in a few minutes.',
            'upstream_http' => $status,
        ], 502);
    }

    /** Best-effort business message out of an upstream JSON error body. */
    private function upstreamMessage(?string $rawExcerpt): ?string
    {
        if ($rawExcerpt === null || trim($rawExcerpt) === '') return null;

        $decoded = json_decode($rawExcerpt, true);
        if (!is_array($decoded)) return null;

        foreach (['message', 'error', 'detail', 'description', 'errorMessage'] as $key) {
            if (!empty($decoded[$key]) && is_string($decoded[$key])) {
                return mb_substr($decoded[$key], 0, 300);
            }
        }
        // { errors: { field: ["msg"] } } / { errors: ["msg"] }
        if (!empty($decoded['errors'])) {
            $flat = collect($decoded['errors'])->flatten()->filter(fn ($v) => is_string($v))->first();
            if ($flat) return mb_substr($flat, 0, 300);
        }

        return null;
    }
}
