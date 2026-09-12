<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Http\Traits\AuthorisesTravelJourney;
use AlphaDirect\Services\PdfGeneratorService;
use AlphaDirect\Services\PublicOtpService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Digital Travel Insurance Proposal Form — the paper form, signed by OTP.
 *
 * Replaces the scanned-and-emailed "Travel Insurance Proposal Form" (Alpha
 * Direct / MAPFRE, rev. 2022): particulars of proposer, other persons
 * travelling, the four health questions, the four-clause declaration, and the
 * Insured's Signature block. Everything is captured as it is on paper except
 * the signature, which becomes a one-time code sent to the proposer's mobile —
 * ECTA 2014 s.17 recognises that verified code as an electronic signature, and
 * DPA 2024 requires us to be able to prove WHEN and BY WHOM it was given.
 *
 *   GET  /public/travel/proposal/declaration      canonical questions + clauses
 *   POST /public/travel/proposal                  save / update the draft
 *   GET  /public/travel/proposal/{id}             read it back (status, document)
 *   POST /public/travel/proposal/{id}/otp/send    issue the signature OTP
 *   POST /public/travel/proposal/{id}/otp/verify  VERIFY = SIGN, then render+store
 *   POST /public/travel/proposal/{id}/link        attach the bound contract
 *
 * The order is enforced server-side, not just in the UI:
 *   - a draft cannot be saved without the agent gate AND the customer's OTP
 *     session (same two locks as /public/travel/contract);
 *   - the signature OTP is refused until the declaration is accepted, so the
 *     customer can never be asked to sign something they haven't accepted;
 *   - once signed the row is immutable — further saves are refused, because
 *     the row IS the signed instrument. Only /link may add the contract number
 *     that MAPFRE returns afterwards.
 *
 * DPA 2024: the health answers are special-category data. They are persisted
 * on travel_proposal_forms and rendered into the generated PDF; they are never
 * written to a log line and never forwarded upstream (MAPFRE's /contract takes
 * no health fields — the declaration is OUR underwriting record).
 */
class PublicTravelProposalController extends Controller
{
    use AuthorisesTravelJourney;

    /** Table (V2 ops DB, beside public_otps + mapfre_quote_submissions). */
    private const TABLE = 'travel_proposal_forms';

    /** Mirrors PublicTravelController::MAX_TRAVELLERS. */
    private const MAX_TRAVELLERS = 10;

    /**
     * Wording revision of the declaration + questions below. Stored on every
     * signed row so we can always reproduce WHAT was accepted, not just that
     * something was. Bump this whenever a word of either list changes.
     */
    public const DECLARATION_VERSION = 'travel-2022.1';

    /**
     * The four health questions, verbatim from the paper form. Keyed so the
     * answers survive a re-wording, and served to the portal from here so the
     * screen can never show a question the stored answer doesn't belong to.
     */
    public const QUESTIONS = [
        'accidents' => 'Has any person for whom this insurance is being proposed suffered any accident(s) previously?',
        'physical_defect' => 'Does any person for whom this insurance is being proposed suffer from any physical defect?',
        'chronic_illness' => 'Does any of the persons for whom this insurance is being proposed suffer from any chronic/ recurring illness?',
        'other_condition' => 'Does any of the persons for whom this insurance is being proposed suffer from any other medical condition?',
    ];

    /** The declaration, verbatim from page 2 of the paper form. */
    public const DECLARATION_CLAUSES = [
        'I/we hereby declare that all persons named in this application form are in good health and will not travel unless they are in good health and fit to undertake the insured trip nor has anyone named in the application been diagnosed with and does not suffer from any medical condition for which medical treatment may be required. I am/We are aware that this is not a general health insurance policy and that pre-existing medical conditions are not covered.',
        'I/We have been made aware of certain restrictions to do with the cover do apply as per the terms, conditions and exclusions which are fully described in the policy wording.',
        'I/We accept the levels of cover chosen and have read the cover involved as described in the summary of cover and the policy document.',
        'I/We agree that the company shall have the right to access my/our medical records prior to the journey in order to proceed with assessment of a claim and/or render medical assistance.',
    ];

    /**
     * GET /public/travel/proposal/declaration
     *
     * The portal renders THIS text rather than its own copy, so the customer
     * accepts exactly the wording the signed PDF will carry. Public and
     * cacheable — it is the same static form for everyone.
     */
    public function declaration(): JsonResponse
    {
        return response()->json([
            'ok'        => true,
            'version'   => self::DECLARATION_VERSION,
            'questions' => collect(self::QUESTIONS)->map(fn ($text, $key) => [
                'key'  => $key,
                'text' => $text,
            ])->values(),
            'clauses'   => self::DECLARATION_CLAUSES,
        ]);
    }

    /**
     * POST /public/travel/proposal
     *
     * Create the draft, or replace the answers on one already in progress
     * (`proposal_id`). Idempotent by design: the portal re-posts the whole
     * form on every visit to the page, so a back-and-forth through the
     * journey updates one row instead of littering drafts.
     */
    public function save(Request $request): JsonResponse
    {
        $request->validate($this->formRules());

        $session = $this->requireOtpSession($request);
        if ($session instanceof JsonResponse) return $session;

        if ($denied = $this->denyUnlessTravelAgent($request)) return $denied;

        $sessionCell = self::normaliseCell((string) ($session['cellphone'] ?? ''));
        $formCell    = self::normaliseCell((string) $request->input('proposer.mobile'));

        // The signature goes to the proposer's mobile, so that number must be
        // the one this journey verified. Otherwise an agent could route a
        // customer's signature to a handset the customer never proved.
        if ($sessionCell === '' || $sessionCell !== $formCell) {
            return $this->reject(
                'mobile_mismatch',
                'The proposer mobile must be the number verified for this quote. Re-verify the mobile you want to sign with.',
                422,
            );
        }

        $dates = $this->validateTripDates($request);
        if ($dates instanceof JsonResponse) return $dates;
        [$departure, $return] = $dates;

        $medical = $this->normaliseMedical($request);
        if ($medical instanceof JsonResponse) return $medical;

        $existing = null;
        if ($request->filled('proposal_id')) {
            $existing = $this->findOwned((int) $request->input('proposal_id'), $sessionCell);
            if ($existing instanceof JsonResponse) return $existing;

            if ($existing->status === 'signed') {
                return $this->reject(
                    'proposal_signed',
                    'This proposal form has already been signed and cannot be changed. Start a new proposal if the details are wrong.',
                    409,
                );
            }
        }

        $accepted   = $request->boolean('declaration_accepted');
        $travellers = $this->normaliseTravellers($request);

        $row = [
            'agent_id'    => (string) $request->input('agent_id'),
            'status'      => 'draft',

            'surname'     => trim((string) $request->input('proposer.surname')),
            'first_names' => trim((string) $request->input('proposer.first_names')),
            'dob'         => (string) $request->input('proposer.dob'),
            'passport_no' => trim((string) $request->input('proposer.passport_no')),
            'occupation'  => trim((string) $request->input('proposer.occupation')),
            'address'     => trim((string) $request->input('proposer.address')),
            'email'       => trim((string) $request->input('proposer.email')),
            'mobile'      => $formCell,

            'departure_date' => $departure->toDateString(),
            'return_date'    => $return->toDateString(),
            'destination'    => trim((string) $request->input('trip.destination')),
            'trip_type'      => trim((string) $request->input('trip.trip_type', '')) ?: null,

            'next_of_kin_name'         => trim((string) $request->input('next_of_kin.name')),
            'next_of_kin_phone'        => trim((string) $request->input('next_of_kin.phone')),
            'next_of_kin_relationship' => trim((string) $request->input('next_of_kin.relationship')),

            'travellers'      => json_encode($travellers),
            'medical_answers' => json_encode($medical),
            'has_medical_disclosure' => collect($medical)->contains(fn ($a) => $a['answer'] === true),

            'declaration_accepted' => $accepted,
            'declaration_version'  => self::DECLARATION_VERSION,
            // Stamped on the transition to accepted; cleared if they un-tick.
            'declaration_accepted_at' => $accepted
                ? ($existing && $existing->declaration_accepted && $existing->declaration_accepted_at
                    ? $existing->declaration_accepted_at
                    : Carbon::now())
                : null,

            'quote_id'      => $request->input('quote.quote_id') ?: null,
            'product_code'  => $request->input('quote.product_code') ?: null,
            'product_name'  => $request->input('quote.product_name') ?: null,
            'premium'       => $request->input('quote.premium'),
            'currency'      => $request->input('quote.currency') ?: null,

            'ip'         => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
            'updated_at' => Carbon::now(),
        ];

        if ($existing) {
            // Any edit voids a signature code already in flight: the code was
            // issued against the answers as they were, so it must not be able
            // to sign a version the customer never saw. verifyOtp() refuses
            // with otp_not_sent until a fresh code is requested.
            $this->table()->where('id', $existing->id)->update($row + [
                'otp_id'      => null,
                'otp_sent_at' => null,
            ]);
            $id        = (int) $existing->id;
            $reference = (string) $existing->reference;
        } else {
            $reference = $this->mintReference();
            $id = (int) $this->table()->insertGetId($row + [
                'reference'  => $reference,
                'created_at' => Carbon::now(),
            ]);
        }

        // Nothing from the proposer or their health answers goes in the log.
        Log::info('travel_proposal.saved', [
            'proposal_id'  => $id,
            'reference'    => $reference,
            'agent_id'     => $request->input('agent_id'),
            'accepted'     => $accepted,
            'travellers'   => count($travellers) + 1,
            'disclosures'  => (bool) $row['has_medical_disclosure'],
        ]);

        return response()->json([
            'ok'   => true,
            'data' => $this->publicShape($this->table()->where('id', $id)->first()),
        ], $existing ? 200 : 201);
    }

    /**
     * GET /public/travel/proposal/{id}
     *
     * Lets the portal recover state after a reload — status, whether the
     * declaration is accepted, and the signed document's URL.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $session = $this->requireOtpSession($request);
        if ($session instanceof JsonResponse) return $session;

        $row = $this->findOwned($id, self::normaliseCell((string) ($session['cellphone'] ?? '')));
        if ($row instanceof JsonResponse) return $row;

        return response()->json(['ok' => true, 'data' => $this->publicShape($row)]);
    }

    /**
     * POST /public/travel/proposal/{id}/otp/send
     *
     * Issue the signature OTP. Goes through PublicOtpService so the signature
     * code gets the same hardening as every other OTP in the estate (hashed
     * storage, 5-minute validity, 60-second resend cooldown, 5-attempt
     * lockout, WhatsApp → SMS → email chain). `source` records on the OTP row
     * that this code was a proposal SIGNATURE, not a login.
     */
    public function sendOtp(Request $request, int $id): JsonResponse
    {
        $request->validate(['agent_id' => 'required|string|max:16']);

        $session = $this->requireOtpSession($request);
        if ($session instanceof JsonResponse) return $session;

        if ($denied = $this->denyUnlessTravelAgent($request)) return $denied;

        $row = $this->findOwned($id, self::normaliseCell((string) ($session['cellphone'] ?? '')));
        if ($row instanceof JsonResponse) return $row;

        if ($row->status === 'signed') {
            return $this->reject('proposal_signed', 'This proposal form is already signed.', 409);
        }

        // The declaration gates the signature: you cannot be asked to sign
        // what you have not accepted. The UI blocks this too — this is the lock.
        if (!$row->declaration_accepted) {
            return $this->reject(
                'declaration_not_accepted',
                'The declaration has to be accepted before the signature code can be sent.',
            );
        }

        $otp = app(PublicOtpService::class);
        $result = $otp->send(
            (string) $row->mobile,
            'customer_auth',
            $request->ip(),
            (string) $request->userAgent(),
            ['source' => 'travel_proposal_sign', 'reason_note' => $row->reference, 'email' => $row->email],
        );

        if (($result['ok'] ?? false) !== true) {
            $error = (string) ($result['error'] ?? 'otp_send_failed');
            return response()->json([
                'ok'      => false,
                'error'   => $error,
                'wait_s'  => $result['wait_s'] ?? null,
                'message' => $error === 'cooldown'
                    ? 'A code was just sent. Wait ' . ($result['wait_s'] ?? 60) . ' seconds before asking for another.'
                    : 'Could not send the signature code. Check the mobile number and try again.',
            ], $error === 'cooldown' ? 429 : 502);
        }

        // Bind the code that was just issued to this proposal so verify can
        // prove the signature came from THAT code and not some other OTP the
        // customer happened to have in flight.
        $issued = DB::connection('mysql_system')->table('public_otps')
            ->where('cellphone', $row->mobile)
            ->where('purpose', 'customer_auth')
            ->whereNull('consumed_at')
            ->orderByDesc('id')
            ->first(['id', 'created_at']);

        $this->table()->where('id', $row->id)->update([
            'otp_id'        => $issued->id ?? null,
            'otp_cellphone' => $row->mobile,
            'otp_sent_at'   => $issued->created_at ?? Carbon::now(),
            'updated_at'    => Carbon::now(),
        ]);

        return response()->json([
            'ok'         => true,
            'sent_to'    => $result['sent_to'] ?? null,
            'channel'    => $result['channel'] ?? null,
            'expires_in' => $result['expires_in'] ?? PublicOtpService::VALIDITY_SECONDS,
            // QA allow-list echo (never populated for real customers — see
            // PublicOtpService::send). Passed through so the portal's dev
            // helper works the same way it does on the /auth screen.
            '_test_otp'  => $result['_test_otp'] ?? null,
        ]);
    }

    /**
     * POST /public/travel/proposal/{id}/otp/verify  { agent_id, code }
     *
     * THE SIGNING STEP. A correct code means the proposal is signed: the row
     * is stamped with the verification evidence, sealed with a hash over it,
     * and the completed form is rendered and stored.
     */
    public function verifyOtp(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'agent_id' => 'required|string|max:16',
            'code'     => 'required|string|min:4|max:8',
        ]);

        $session = $this->requireOtpSession($request);
        if ($session instanceof JsonResponse) return $session;

        if ($denied = $this->denyUnlessTravelAgent($request)) return $denied;

        $row = $this->findOwned($id, self::normaliseCell((string) ($session['cellphone'] ?? '')));
        if ($row instanceof JsonResponse) return $row;

        if ($row->status === 'signed') {
            // Idempotent: a double-tap on Verify returns the existing
            // signature rather than a confusing error.
            return response()->json(['ok' => true, 'data' => $this->publicShape($row)]);
        }
        if (!$row->declaration_accepted) {
            return $this->reject(
                'declaration_not_accepted',
                'The declaration has to be accepted before the form can be signed.',
            );
        }
        if (!$row->otp_id) {
            return $this->reject('otp_not_sent', 'Send the signature code first.');
        }

        $result = app(PublicOtpService::class)
            ->verify((string) $row->mobile, 'customer_auth', (string) $request->input('code'), $request->ip());

        if (($result['ok'] ?? false) !== true) {
            $error = (string) ($result['error'] ?? 'invalid');
            return response()->json([
                'ok'            => false,
                'error'         => $error,
                'attempts_left' => $result['attempts_left'] ?? null,
                'message'       => match ($error) {
                    'invalid'       => 'That code is wrong. ' . ($result['attempts_left'] ?? 0) . ' attempts left.',
                    'expired'       => 'That code has expired. Send a new one.',
                    'locked'        => 'Too many wrong attempts. Send a new code.',
                    'no_pending_otp' => 'There is no code waiting to be verified. Send a new one.',
                    default         => 'The code could not be verified. Send a new one.',
                },
            ], 422);
        }

        // The OTP row this proposal was bound to — the signature's provenance.
        $otpRow = DB::connection('mysql_system')->table('public_otps')
            ->where('id', $row->otp_id)
            ->first(['id', 'created_at', 'consumed_at', 'delivered_via', 'attempts']);

        // verify() consumes the newest pending code for this number. If that
        // wasn't the one we bound, the signature isn't attributable to this
        // proposal — refuse rather than sign against the wrong evidence.
        if (!$otpRow || $otpRow->consumed_at === null) {
            Log::warning('travel_proposal.otp_binding_lost', [
                'proposal_id' => $row->id,
                'otp_id'      => $row->otp_id,
            ]);
            return $this->reject(
                'otp_binding_lost',
                'That code belongs to a different verification. Send a new signature code for this form.',
            );
        }

        $signedAt = Carbon::parse($otpRow->consumed_at);

        $evidence = [
            'reference'               => $row->reference,
            'mobile'                  => $row->mobile,
            'otp_id'                  => (int) $otpRow->id,
            'otp_sent_at'             => (string) $otpRow->created_at,
            'otp_verified_at'         => (string) $otpRow->consumed_at,
            'otp_channel'             => $otpRow->delivered_via,
            'declaration_version'     => $row->declaration_version,
            'declaration_accepted_at' => (string) $row->declaration_accepted_at,
            'surname'                 => $row->surname,
            'first_names'             => $row->first_names,
            'dob'                     => (string) $row->dob,
            'passport_no'             => $row->passport_no,
            'departure_date'          => (string) $row->departure_date,
            'return_date'             => (string) $row->return_date,
            'destination'             => $row->destination,
            'travellers'              => (string) $row->travellers,
            'medical_answers'         => (string) $row->medical_answers,
            'quote_id'                => $row->quote_id,
            'ip'                      => $request->ip(),
            'user_agent'              => substr((string) $request->userAgent(), 0, 255),
        ];

        $update = [
            'status'           => 'signed',
            'otp_cellphone'   => $row->mobile,
            'otp_sent_at'     => $otpRow->created_at,
            'otp_verified_at' => $otpRow->consumed_at,
            'otp_channel'     => $otpRow->delivered_via,
            'otp_attempts'    => (int) $otpRow->attempts,
            'signed_at'       => $signedAt,
            'signature_method' => 'otp',
            'evidence_hash'   => hash('sha256', json_encode($evidence)),
            'ip'              => $request->ip(),
            'user_agent'      => substr((string) $request->userAgent(), 0, 255),
            'updated_at'      => Carbon::now(),
        ];
        $this->table()->where('id', $row->id)->update($update);

        Log::info('travel_proposal.signed', [
            'proposal_id'     => $row->id,
            'reference'       => $row->reference,
            'agent_id'        => $request->input('agent_id'),
            'otp_id'          => (int) $otpRow->id,
            'otp_channel'     => $otpRow->delivered_via,
            'otp_verified_at' => (string) $otpRow->consumed_at,
        ]);

        // Render + store the completed form. A rendering failure must never
        // undo a valid signature: the signed row stands and the document is
        // reproducible from it (the /link step regenerates, and so does
        // GET {id} once the renderer is healthy again).
        $signed = $this->table()->where('id', $row->id)->first();
        $document = $this->generateDocument($signed);

        return response()->json([
            'ok'   => true,
            'data' => $this->publicShape($this->table()->where('id', $row->id)->first()) + [
                'document_pending' => $document === null,
            ],
        ]);
    }

    /**
     * POST /public/travel/proposal/{id}/link  { agent_id, contract_number?, quote_id?, policy_id? }
     *
     * Called after /public/travel/contract binds the cover, so the signed
     * proposal carries the policy it belongs to. The document is re-rendered
     * so the stored form shows the contract number beside the signature — the
     * content is derived from this row, whose evidence_hash is unchanged, so
     * regenerating adds the linkage without touching the signed evidence.
     */
    public function link(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'agent_id'         => 'required|string|max:16',
            'contract_number'  => 'nullable|string|max:64',
            'quote_id'         => 'nullable|string|max:64',
            'mapfre_reference' => 'nullable|string|max:64',
            'policy_id'        => 'nullable|integer',
        ]);

        $session = $this->requireOtpSession($request);
        if ($session instanceof JsonResponse) return $session;

        if ($denied = $this->denyUnlessTravelAgent($request)) return $denied;

        $row = $this->findOwned($id, self::normaliseCell((string) ($session['cellphone'] ?? '')));
        if ($row instanceof JsonResponse) return $row;

        if ($row->status !== 'signed') {
            return $this->reject('proposal_not_signed', 'This proposal form has not been signed yet.');
        }

        $update = array_filter([
            'contract_number'  => $request->input('contract_number'),
            'quote_id'         => $request->input('quote_id'),
            'mapfre_reference' => $request->input('mapfre_reference'),
            'policy_id'        => $request->input('policy_id'),
        ], fn ($v) => $v !== null && $v !== '');

        if ($update) {
            $this->table()->where('id', $row->id)->update($update + ['updated_at' => Carbon::now()]);
        }

        Log::info('travel_proposal.linked', [
            'proposal_id'     => $row->id,
            'reference'       => $row->reference,
            'contract_number' => $request->input('contract_number'),
        ]);

        $document = $this->generateDocument($this->table()->where('id', $row->id)->first());

        return response()->json([
            'ok'   => true,
            'data' => $this->publicShape($this->table()->where('id', $row->id)->first()) + [
                'document_pending' => $document === null,
            ],
        ]);
    }

    // ─── internals ──────────────────────────────────────────────────────────

    private function table()
    {
        return DB::connection('mysql_system')->table(self::TABLE);
    }

    /**
     * Render the proposal to PDF and store it through the project's document
     * pipeline (PdfGeneratorService → StorageService: S3 with local fallback).
     *
     * Returns the storage result, or null when generation failed — callers
     * treat null as "signed, document to follow" rather than an error.
     */
    private function generateDocument(object $row): ?array
    {
        try {
            $result = app(PdfGeneratorService::class)->generateAndStore(
                'travel/proposals/' . $row->reference . '.pdf',
                'v2.livewire.pdf.travel-proposal-form',
                [
                    'proposal'   => $row,
                    'travellers' => json_decode((string) $row->travellers, true) ?: [],
                    'medical'    => json_decode((string) $row->medical_answers, true) ?: [],
                    'questions'  => self::QUESTIONS,
                    'clauses'    => self::DECLARATION_CLAUSES,
                ],
                ['format' => 'A4'],
            );

            $this->table()->where('id', $row->id)->update([
                'document_path'         => $result['path'] ?? null,
                'document_disk'         => $result['disk'] ?? null,
                'document_url'          => $result['url'] ?? null,
                'document_generated_at' => Carbon::now(),
                'updated_at'            => Carbon::now(),
            ]);

            Log::info('travel_proposal.document_stored', [
                'proposal_id' => $row->id,
                'reference'   => $row->reference,
                'disk'        => $result['disk'] ?? null,
                'size'        => $result['size'] ?? null,
            ]);

            return $result;
        } catch (\Throwable $e) {
            Log::error('travel_proposal.document_failed', [
                'proposal_id' => $row->id,
                'reference'   => $row->reference,
                'msg'         => $e->getMessage(),
            ]);
            return null;
        }
    }

    /** Validation for the whole form. Shared by create and update. */
    private function formRules(): array
    {
        return [
            'agent_id'    => 'required|string|max:16',
            'proposal_id' => 'nullable|integer',

            'proposer.surname'     => 'required|string|max:60',
            'proposer.first_names' => 'required|string|max:120',
            'proposer.dob'         => 'required|date_format:Y-m-d|before:today',
            // The travel document the traveller will present at the border —
            // the paper form asks for it in the proposer block.
            'proposer.passport_no' => 'required|string|max:40',
            'proposer.occupation'  => 'required|string|max:80',
            'proposer.address'     => 'required|string|max:250',
            'proposer.email'       => 'required|email|max:160',
            'proposer.mobile'      => 'required|string|max:24',

            'trip.departure_date' => 'required|date_format:Y-m-d',
            'trip.return_date'    => 'required|date_format:Y-m-d',
            'trip.destination'    => 'required|string|max:120',
            'trip.trip_type'      => 'nullable|string|max:60',

            'next_of_kin.name'         => 'required|string|max:120',
            'next_of_kin.phone'        => 'required|string|max:24',
            'next_of_kin.relationship' => 'required|string|max:60',

            // Other persons travelling — the proposer is not repeated here.
            'travellers'                => 'nullable|array|max:' . (self::MAX_TRAVELLERS - 1),
            'travellers.*.full_name'    => 'required|string|max:120',
            'travellers.*.dob'          => 'required|date_format:Y-m-d|before:today',
            'travellers.*.passport_no'  => 'nullable|string|max:40',
            'travellers.*.relationship' => 'required|string|max:60',

            'medical'           => 'required|array',
            'medical.*.answer'  => 'required|boolean',
            'medical.*.details' => 'nullable|string|max:1000',

            'declaration_accepted' => 'required|boolean',

            'quote.quote_id'     => 'nullable|string|max:64',
            'quote.product_code' => 'nullable|string|max:64',
            'quote.product_name' => 'nullable|string|max:120',
            'quote.premium'      => 'nullable|numeric|min:0',
            'quote.currency'     => 'nullable|string|max:8',
        ];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}|JsonResponse
     */
    private function validateTripDates(Request $request)
    {
        $departure = Carbon::createFromFormat('Y-m-d', (string) $request->input('trip.departure_date'))->startOfDay();
        $return    = Carbon::createFromFormat('Y-m-d', (string) $request->input('trip.return_date'))->startOfDay();

        if ($return->lt($departure)) {
            return $this->reject('invalid_trip_dates', 'The return date cannot be before the departure date.');
        }

        return [$departure, $return];
    }

    /**
     * Answers keyed by QUESTIONS, with the paper form's "If Yes please give
     * details" enforced. Unknown keys are dropped so a stale client cannot
     * store an answer against a question that no longer exists.
     *
     * @return array|JsonResponse
     */
    private function normaliseMedical(Request $request)
    {
        $posted = (array) $request->input('medical', []);
        $out    = [];

        foreach (array_keys(self::QUESTIONS) as $key) {
            if (!array_key_exists($key, $posted)) {
                return $this->reject(
                    'medical_answers_incomplete',
                    'Every health question on the proposal form has to be answered Yes or No.',
                );
            }
            $answer  = filter_var($posted[$key]['answer'] ?? null, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            $details = trim((string) ($posted[$key]['details'] ?? ''));

            if ($answer === null) {
                return $this->reject(
                    'medical_answers_incomplete',
                    'Every health question on the proposal form has to be answered Yes or No.',
                );
            }
            if ($answer === true && $details === '') {
                return $this->reject(
                    'medical_details_required',
                    'A "Yes" answer needs details — the proposal form requires them for underwriting.',
                );
            }

            $out[$key] = ['answer' => $answer, 'details' => $answer ? $details : null];
        }

        return $out;
    }

    /** Other persons travelling, trimmed to the columns the form prints. */
    private function normaliseTravellers(Request $request): array
    {
        return collect((array) $request->input('travellers', []))
            ->map(fn ($t) => [
                'full_name'    => trim((string) ($t['full_name'] ?? '')),
                'dob'          => (string) ($t['dob'] ?? ''),
                'passport_no'  => trim((string) ($t['passport_no'] ?? '')),
                'relationship' => trim((string) ($t['relationship'] ?? '')),
            ])
            ->values()
            ->all();
    }

    /**
     * Load the proposal, refusing one that belongs to another cellphone — the
     * id is a public path segment, so ownership is checked, not assumed.
     *
     * @return object|JsonResponse
     */
    private function findOwned(int $id, string $sessionCell)
    {
        $row = $this->table()->where('id', $id)->first();
        if (!$row) {
            return response()->json([
                'ok' => false, 'error' => 'proposal_not_found',
                'message' => 'That proposal form could not be found.',
            ], 404);
        }
        if ($sessionCell === '' || self::normaliseCell((string) $row->mobile) !== $sessionCell) {
            return response()->json([
                'ok' => false, 'error' => 'proposal_forbidden',
                'message' => 'That proposal form belongs to a different customer.',
            ], 403);
        }
        return $row;
    }

    /** Unique, human-quotable proposal reference. */
    private function mintReference(): string
    {
        for ($i = 0; $i < 5; $i++) {
            $candidate = 'AD-TPF-' . strtoupper(bin2hex(random_bytes(4)));
            if (!$this->table()->where('reference', $candidate)->exists()) {
                return $candidate;
            }
        }
        throw new \RuntimeException('Could not mint a unique travel proposal reference.');
    }

    /**
     * What the portal is allowed to see. The health answers ARE returned —
     * the customer typed them on this device and the review card shows them
     * back — but nothing about the OTP beyond the audit stamps.
     */
    private function publicShape(object $row): array
    {
        return [
            'proposal_id'          => (int) $row->id,
            'reference'            => (string) $row->reference,
            'status'               => (string) $row->status,
            'declaration_accepted' => (bool) $row->declaration_accepted,
            'declaration_version'  => $row->declaration_version,
            'has_medical_disclosure' => (bool) $row->has_medical_disclosure,
            'travellers'           => json_decode((string) $row->travellers, true) ?: [],
            'medical'              => json_decode((string) $row->medical_answers, true) ?: [],
            'signed_at'            => $row->signed_at ? Carbon::parse($row->signed_at)->toIso8601String() : null,
            'signature_method'     => $row->signature_method,
            'otp_verified_at'      => $row->otp_verified_at ? Carbon::parse($row->otp_verified_at)->toIso8601String() : null,
            'otp_channel'          => $row->otp_channel,
            'signed_mobile'        => $row->otp_cellphone ? self::maskCell((string) $row->otp_cellphone) : null,
            'evidence_hash'        => $row->evidence_hash,
            'contract_number'      => $row->contract_number,
            'quote_id'             => $row->quote_id,
            'document_url'         => $row->document_url,
            'document_generated_at' => $row->document_generated_at
                ? Carbon::parse($row->document_generated_at)->toIso8601String()
                : null,
        ];
    }

    /**
     * Local-digits form, matching PublicOtpService::normalize — public_otps
     * stores the 8-digit BW number, so the comparison has to be on that shape.
     */
    private static function normaliseCell(string $raw): string
    {
        $digits = preg_replace('/\D/', '', $raw);
        if (str_starts_with($digits, '267') && strlen($digits) === 11) {
            $digits = substr($digits, 3);
        }
        return $digits;
    }

    private static function maskCell(string $cell): string
    {
        $len = strlen($cell);
        if ($len < 5) return '***';
        return substr($cell, 0, 2) . str_repeat('*', $len - 4) . substr($cell, -2);
    }
}
