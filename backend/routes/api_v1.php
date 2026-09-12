<?php

use AlphaDirect\Http\Controllers\Api\V1\AuthController;
use AlphaDirect\Http\Controllers\Api\V1\BizSurePartnerController;
use AlphaDirect\Http\Controllers\Api\V1\DataAccessRequestController;
use AlphaDirect\Http\Controllers\Api\V1\MeController;
use AlphaDirect\Http\Controllers\Api\V1\MeAvatarController;
use AlphaDirect\Http\Controllers\Api\V1\AdminPdfProcessController;
use AlphaDirect\Http\Controllers\Api\V1\ClaimsController;
use AlphaDirect\Http\Controllers\Api\V1\CustomerController;
use AlphaDirect\Http\Controllers\Api\V1\DashboardController;
use AlphaDirect\Http\Controllers\Api\V1\LookupController;
use AlphaDirect\Http\Controllers\Api\V1\PolicyController;
use AlphaDirect\Http\Controllers\Api\V1\PolicyCreateController;
use AlphaDirect\Http\Controllers\Api\V1\EndorseChangeSummaryController;
use AlphaDirect\Http\Controllers\Api\V1\CreditNoteController;
use AlphaDirect\Http\Controllers\Api\V1\ProductController;
use AlphaDirect\Http\Controllers\Api\V1\QuoteController;
use AlphaDirect\Http\Controllers\Api\V1\GroupPolicyController;
use AlphaDirect\Http\Controllers\Api\V1\RenewalController;
use AlphaDirect\Http\Controllers\Api\V1\CancelRequestController;
use AlphaDirect\Http\Controllers\Api\V1\EmployerGroupController;
use AlphaDirect\Http\Controllers\Api\V1\HelpDeskController;
use AlphaDirect\Http\Controllers\Api\V1\BridgeWidgetController;
use AlphaDirect\Http\Controllers\Api\V1\BrainProxyController;
use AlphaDirect\Http\Controllers\Api\V1\SlaController;
use AlphaDirect\Http\Controllers\Api\V1\SlaDashboardController;
use AlphaDirect\Http\Controllers\Api\V1\SlaReportController;
use AlphaDirect\Http\Controllers\Api\V1\BatchProcessingController;
use AlphaDirect\Http\Controllers\Api\V1\CustomerKycController;
use AlphaDirect\Http\Controllers\Api\V1\KycAccessReportController;
use AlphaDirect\Http\Controllers\Api\V1\PreinspectionController;
use AlphaDirect\Http\Controllers\Api\V1\AnomalyFindingsController;
use AlphaDirect\Http\Controllers\Api\V1\ReconciliationController;
use AlphaDirect\Http\Controllers\Api\V1\SettlementReconciliationController;
use AlphaDirect\Http\Controllers\Api\V1\ExceptionsController;
use AlphaDirect\Http\Controllers\Api\V1\CronReportController;
use AlphaDirect\Http\Controllers\Api\V1\FacRegisterApiController;
use AlphaDirect\Http\Controllers\Api\V1\ReinsuranceApiController;
use AlphaDirect\Http\Controllers\Api\V1\CustomerBlockListController;
use AlphaDirect\Http\Controllers\Api\V1\ExcelImportController;
use AlphaDirect\Http\Controllers\Api\V1\RealpaySettlementImportController;
use AlphaDirect\Http\Controllers\Api\V1\VehicleLookupController;
use AlphaDirect\Http\Controllers\Api\V1\AiAssistantController;
use AlphaDirect\Http\Controllers\Api\V1\MasterDataController;
use AlphaDirect\Http\Controllers\Api\V1\AdGroupKycController;
use AlphaDirect\Http\Controllers\Api\V1\UnionSchemeController;
use AlphaDirect\Http\Controllers\Api\V1\UnionPaymentsController;
use AlphaDirect\Http\Controllers\Api\V1\CronConfigController;
use AlphaDirect\Http\Controllers\Api\V1\CronLogsController;
use AlphaDirect\Http\Controllers\Api\V1\DomComBatchRenewController;
use AlphaDirect\Http\Controllers\Api\V1\PolicyExcelController;
use AlphaDirect\Http\Controllers\Api\V1\SpecialistCoverageController;
use AlphaDirect\Http\Controllers\Api\V1\BondsCollateralDocumentController;
use AlphaDirect\Http\Controllers\Api\V1\NotificationController;
use AlphaDirect\Http\Controllers\Api\V1\CollectNowController;
use AlphaDirect\Http\Controllers\Api\V1\PaymentController;
use AlphaDirect\Http\Controllers\Api\V1\CommissionController;
use AlphaDirect\Http\Controllers\Api\V1\ClaimsV2Controller;
use AlphaDirect\Http\Controllers\Api\V1\UwBottleneckController;
use AlphaDirect\Http\Controllers\Api\V1\ClaimSlaController;
use AlphaDirect\Http\Controllers\Api\V1\ClaimFnolController;
use AlphaDirect\Http\Controllers\Api\V1\ClaimDecisionController;
use AlphaDirect\Http\Controllers\Api\V1\ClaimNotificationController;
use AlphaDirect\Http\Controllers\Api\V1\KycFieldsApiController;
use AlphaDirect\Http\Controllers\Api\V1\KycComplianceApiController;
use AlphaDirect\Http\Controllers\Api\V1\CommunicationController;
use AlphaDirect\Http\Controllers\Api\V1\PremiumRegisterController;
use AlphaDirect\Http\Controllers\Api\V1\RenewalDashboardController;
use AlphaDirect\Http\Controllers\Api\V1\AgentController;
use AlphaDirect\Http\Controllers\Api\V1\RolePermissionController;
use AlphaDirect\Http\Controllers\Api\V1\AccountingController;
use AlphaDirect\Http\Controllers\Api\V1\SupplierController;
use AlphaDirect\Http\Controllers\Api\V1\ClaimsIncentiveReportController;
use AlphaDirect\Http\Controllers\Api\V1\AssessorController;
use AlphaDirect\Http\Controllers\Api\V1\InflationRateController;
use AlphaDirect\Http\Controllers\Api\V1\LawyerController;
use AlphaDirect\Http\Controllers\Api\V1\ClaimsMasterDataController;
use AlphaDirect\Http\Controllers\Api\V1\ClaimsConfigController;
use AlphaDirect\Http\Controllers\Api\V1\ClaimReportScheduleController;
use AlphaDirect\Http\Controllers\Api\V1\ClaimBulkImportController;
use AlphaDirect\Http\Controllers\Api\V1\RepairCenterController;
use AlphaDirect\Http\Controllers\Api\V1\StateController;
use AlphaDirect\Http\Controllers\Api\V1\CityController;
use AlphaDirect\Http\Controllers\Api\V1\ActivationCodeController;
use AlphaDirect\Http\Controllers\Api\V1\RealPayContractController;
use AlphaDirect\Http\Controllers\Api\V1\PolicyRealpayController;
use AlphaDirect\Http\Controllers\Api\V1\PolicyReratePremiumController;
use AlphaDirect\Http\Controllers\Api\V1\AdminConfigController;
use AlphaDirect\Http\Controllers\Api\V1\ProductConfigController;
use AlphaDirect\Http\Controllers\Api\V1\UnderwritingController;
use AlphaDirect\Http\Controllers\Api\V1\SmartUploadController;
use AlphaDirect\Http\Controllers\Api\V1\SmsEmailTemplateController;
use AlphaDirect\Http\Controllers\Api\V1\WhatsAppTemplateController;
use AlphaDirect\Http\Controllers\Api\V1\Finance\PaymentTransactionController as FinancePaymentTransactionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1 Routes
|--------------------------------------------------------------------------
| These are the versioned API routes consumed by the React SPA and future
| microservices. The original routes/api.php is NEVER modified — all
| third-party webhooks (DPO, VCS, WhatsApp) continue to use those paths.
|
*/

Route::prefix('v1')->name('api.v1.')->group(function () {

    // ─── Health Check (no auth) ─────────────────────────────────────────────
    Route::get('health', function () {
        try {
            \DB::connection()->getPdo();
            return response()->json(['status' => 'ok', 'db' => 'connected', 'time' => now()->toIso8601String()]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'db' => 'disconnected'], 503);
        }
    })->name('health');

    // ─── Delivery Webhooks (no auth — secured by provider signatures) ────────
    Route::post('webhooks/infobip/sms-dlr',   [\AlphaDirect\Http\Controllers\Api\V1\DeliveryWebhookController::class, 'infobipSms']);
    Route::post('webhooks/mailgun/events',    [\AlphaDirect\Http\Controllers\Api\V1\DeliveryWebhookController::class, 'mailgunEvent']);
    Route::post('webhooks/whatsapp/status',   [\AlphaDirect\Http\Controllers\Api\V1\DeliveryWebhookController::class, 'whatsappStatus']);
    Route::get('webhooks/whatsapp/status',    [\AlphaDirect\Http\Controllers\Api\V1\DeliveryWebhookController::class, 'whatsappVerify']);
    Route::get('webhooks/whatsapp/verify',    [\AlphaDirect\Http\Controllers\Api\V1\DeliveryWebhookController::class, 'whatsappVerify']);

    // ─── WhatsApp AI Bot (no auth — Meta webhook) ──────────────────────────
    Route::post('webhooks/whatsapp/ai', [\AlphaDirect\Http\Controllers\Api\V1\WhatsAppAiController::class, 'webhook']);
    Route::get('webhooks/whatsapp/ai',  [\AlphaDirect\Http\Controllers\Api\V1\WhatsAppAiController::class, 'verify']);

    // ─── Payment provider webhooks (no auth — DPO posts here) ──────────────
    Route::post('webhooks/dpo/push',                   [\AlphaDirect\Http\Controllers\Api\V1\PaymentWebhookController::class, 'dpoPush'])->name('webhooks.dpo');
    // Browser redirect target after DPO checkout (the RedirectURL/BackURL/
    // DeclinedURL we hand DPO at createToken). Accept GET *and* POST — DPO
    // returns the customer with a GET in most cases but POSTs the result
    // fields in some configurations; the legacy saveonlinepayment route used
    // Route::any for exactly this reason. A GET-only route 405s the POST
    // variant, which surfaces as a broken redirect-back. We verify the token,
    // materialise as a fallback, then bounce to the customer FE success/
    // failure page.
    Route::match(['get', 'post'], 'webhooks/dpo/return', [\AlphaDirect\Http\Controllers\Api\V1\PaymentWebhookController::class, 'dpoReturn'])->name('webhooks.dpo.return');

    // ─── Swiftly Finance webhooks (no Bearer — verified by HMAC signature) ──
    // Inbound early-payment notifications. VerifySwiftlySignature checks the
    // X-Webhook-Signature (HMAC-SHA256 over the raw body) and fails closed.
    // Throttled generously — low-volume, but capped to blunt abuse.
    Route::post('webhooks/swiftly/early-payment', [\AlphaDirect\Http\Controllers\Api\V1\SwiftlyWebhookController::class, 'earlyPayment'])
        ->middleware(['swiftly.signature', 'throttle:120,1'])
        ->name('webhooks.swiftly.earlyPayment');

    // ─── Omni (alpha-finance) refund-paid callback (no Sanctum — shared bearer) ─
    // Customer Refund Engine: Omni tells us a refund left FNB so we post it to
    // the policy + flip the portal flag. VerifyOmniCallbackToken compares the
    // bearer constant-time and fails closed; the handler is idempotent on
    // graphite_ref and only 2xx-es once the paid state is durably recorded
    // (Omni marks POSTED_BACK on any 2xx and never retries).
    Route::post('webhooks/omni/refund-paid', [\AlphaDirect\Http\Controllers\Api\V1\Refunds\OmniCallbackController::class, 'paid'])
        ->middleware(['omni.callback', 'throttle:120,1'])
        ->name('webhooks.omni.refundPaid');

    // ─── Alpha Transit Cover events (no Sanctum — shared bearer) ────────────
    // Courier GIT platform (transit.alphadirect.co.bw) posts its six event
    // types here: policy.created, policy.payment_status_changed,
    // payment.received, recon.monthly_settled, claim.created, claim.updated.
    // VerifyAlphaTransitToken compares the bearer constant-time and fails
    // closed; the handler is idempotent on X-Idempotency-Key plus each
    // entity's natural key, and 503s while the integration toggle is off
    // (ATC's retry queue holds events — replayable, nothing lost).
    Route::post('webhooks/alpha-transit/event', [\AlphaDirect\Http\Controllers\Api\V1\AlphaTransitWebhookController::class, 'event'])
        ->middleware(['atc.webhook', 'throttle:120,1'])
        ->name('webhooks.alphaTransit.event');

    // ─── Public lookups (no auth — feed the customer-facing start.alphadirect.co.bw) ─
    // These return non-sensitive catalogue data: products, plans, countries,
    // states, cities. Cache TTLs in LookupController are aggressive so the
    // hot path is always a Redis hit.
    Route::prefix('public/lookups')->name('public.lookups.')->middleware('throttle:api_read')->group(function () {
        Route::get('products',                        [LookupController::class, 'productsForStart'])->name('products');
        Route::get('products/{id}/plans',             [LookupController::class, 'plansByProduct'])->name('plansByProduct');
        Route::get('countries',                       [LookupController::class, 'countries'])->name('countries');
        Route::get('countries/{id}/states',           [LookupController::class, 'statesByCountry'])->name('statesByCountry');
        Route::get('states/{id}/cities',              [LookupController::class, 'citiesByState'])->name('citiesByState');
        Route::get('bundle-settings',                 [LookupController::class, 'publicBundleSettings'])->name('bundleSettings');
    });

    // ─── Public lead capture (no auth — start.alphadirect.co.bw) ─────────
    // Throttled aggressively (5/min per IP) — these are bot magnets. Both
    // endpoints persist to public_leads and log; ops triages in the admin.
    Route::prefix('public/leads')->name('public.leads.')->middleware('throttle:5,1')->group(function () {
        Route::post('callback', [\AlphaDirect\Http\Controllers\Api\V1\LeadController::class, 'submitCallback'])->name('callback');
        Route::post('issue',    [\AlphaDirect\Http\Controllers\Api\V1\LeadController::class, 'reportIssue'])->name('issue');
    });

    // ─── Marketing-website form submissions ───────────────────────────────
    // Every form on alphadirect-website-bw (quotation enquiries, contact,
    // claims, careers, newsletter) lands in website_leads. CAPTCHA is
    // verified by the website's own server before it calls this endpoint;
    // the throttle is the second line of defence.
    Route::post('public/website-leads', [\AlphaDirect\Http\Controllers\Api\V1\WebsiteLeadController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('public.websiteLeads.store');
    // Career-application file uploads (CV + credentials), one file per
    // request so multi-file applications stay under body-size limits.
    Route::post('public/website-leads/files', [\AlphaDirect\Http\Controllers\Api\V1\WebsiteLeadController::class, 'uploadFile'])
        ->middleware('throttle:30,1')
        ->name('public.websiteLeads.uploadFile');

    // ─── Frontend data-load error reporter ───────────────────────────────
    // Fire-and-forget endpoint the V2 React frontend posts to when a
    // fetch / React Query call fails. Each report is logged + emailed to
    // developers@theriskco.com with the [GRAPHITE-V2-DATA-ERR] subject
    // tag so the team can spot silent broken pages without needing
    // every operator to report them.
    //
    // Throttled at 10/min per IP — enough to capture genuine failure
    // bursts (e.g. backend hiccup affecting many open browser tabs) but
    // tight enough to prevent an attacker spamming the inbox.
    Route::post('error-report', [\AlphaDirect\Http\Controllers\Api\V1\ErrorReportController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('error-report');

    // ─── Public chunked upload ───────────────────────────────────────────
    // Two endpoints share the chunking machinery so every file path in
    // the start portal can survive Botswana low-bandwidth networks:
    //
    //   chunk       — Bearer-auth, persists to S3 (KYC, vehicle inspection)
    //   scan-chunk  — no auth, assembles in /tmp, runs OCR, deletes the
    //                 file. For the document scanner that just needs to
    //                 *read* the doc, never store it.
    //
    // Both use 100KB chunks. A 10MB file is 100 requests so 60/min on
    // the authed surface is borderline (one retry permitted); the scan
    // surface gets 200/min because OCR uploads are typically smaller
    // (single-page docs) and we want the FE retry loop to be roomy.
    Route::prefix('public/uploads')->name('public.uploads.')->group(function () {
        Route::post('chunk',      [\AlphaDirect\Http\Controllers\Api\V1\UploadController::class, 'chunk'])
            ->middleware('throttle:60,1')->name('chunk');
        Route::post('scan-chunk', [\AlphaDirect\Http\Controllers\Api\V1\UploadController::class, 'scanChunk'])
            ->middleware('throttle:200,1')->name('scanChunk');
    });

    // Customer KYC submission — finalises a set of `public_uploaded_files`
    // rows into a `customer_kyc` row for the KYC team's review queue.
    // The chunk endpoint already requires a Bearer; the submit endpoint
    // re-validates so the two requests can't drift apart in auth state.
    Route::prefix('public/kyc')->name('public.kyc.')->middleware('throttle:20,1')->group(function () {
        Route::post('submit', [\AlphaDirect\Http\Controllers\Api\V1\PublicKycController::class, 'submit'])
            ->name('submit');
    });

    // ─── Vehicle pre-inspection submission ───────────────────────────────
    // Final step of the plate→OTP→upload flow. The customer has already
    // uploaded each photo via /public/uploads/chunk (purpose=vehicle_inspection,
    // Bearer-auth) so this endpoint just wires the photos to the workflow
    // table (agentpreinspection) after re-checking ownership.
    Route::prefix('public/vehicle-inspections')->name('public.vehicle_inspections.')->middleware('throttle:10,1')->group(function () {
        Route::post('submit', [\AlphaDirect\Http\Controllers\Api\V1\PublicVehicleInspectionController::class, 'submit'])
            ->name('submit');
    });

    // ─── Public OCR extraction (no auth — vision-LLM scan for onboarding) ─
    // Costs roughly $0.01/scan via Anthropic so throttle:10,1 per IP keeps
    // bots from running up the bill while staying generous enough for a
    // human filling a form (Omang front + back + license + vehicle valuation
    // + employment letter + proof of residence ≈ 6 calls).
    Route::prefix('public/ocr')->name('public.ocr.')->middleware('throttle:10,1')->group(function () {
        Route::post('extract', [\AlphaDirect\Http\Controllers\Api\V1\OcrController::class, 'extract'])->name('extract');
    });

    // ─── Public consent (no auth — captured before first OTP send) ──────
    // Customer tells us if they have WhatsApp, what email they prefer,
    // and which channel we should default to. Stored per-cellphone with
    // consent_at + consent_ip for audit.
    Route::prefix('public/consent')->name('public.consent.')->middleware('throttle:10,1')->group(function () {
        Route::get ('contact', [\AlphaDirect\Http\Controllers\Api\V1\ConsentController::class, 'show'])->name('show');
        Route::post('contact', [\AlphaDirect\Http\Controllers\Api\V1\ConsentController::class, 'contact'])->name('contact');
        // DPA-grade privacy / T&C consent — append-only audit table.
        // FE must collect three mandatory checkboxes (terms / privacy
        // / data-processing) before this returns 201. Optional marketing
        // checkbox stored alongside.
        Route::post('privacy', [\AlphaDirect\Http\Controllers\Api\V1\ConsentController::class, 'privacy'])->name('privacy');
    });

    // ─── Partner portal (courier / retailer staff on start) ──────────
    // Email + password login → opaque bearer token (partner_user_tokens).
    // Partner-only products (GIT) require this token at create time.
    Route::prefix('public/partner')->name('public.partner.')->group(function () {
        Route::post('login',        [\AlphaDirect\Http\Controllers\Api\V1\PublicPartnerAuthController::class, 'login'])->middleware('throttle:10,1')->name('login');
        Route::post('set-password', [\AlphaDirect\Http\Controllers\Api\V1\PublicPartnerAuthController::class, 'setPassword'])->middleware('throttle:10,1')->name('set-password');
        Route::middleware(['partner.auth', 'throttle:60,1'])->group(function () {
            Route::get('me',               [\AlphaDirect\Http\Controllers\Api\V1\PublicPartnerAuthController::class, 'me'])->name('me');
            Route::post('logout',          [\AlphaDirect\Http\Controllers\Api\V1\PublicPartnerAuthController::class, 'logout'])->name('logout');
            Route::post('change-password', [\AlphaDirect\Http\Controllers\Api\V1\PublicPartnerAuthController::class, 'changePassword'])->middleware('throttle:10,1')->name('change-password');
            Route::get('policies',         [\AlphaDirect\Http\Controllers\Api\V1\PublicPartnerAuthController::class, 'policies'])->name('policies');
            Route::get('policies/{policyNumber}/documents', [\AlphaDirect\Http\Controllers\Api\V1\PublicPartnerAuthController::class, 'documents'])->where('policyNumber', '[A-Za-z0-9-]+')->name('policies.documents');
            Route::get('policies/{policyNumber}/wording-pdf',             [\AlphaDirect\Http\Controllers\Api\V1\PublicPartnerAuthController::class, 'wordingPdf'])->where('policyNumber', '[A-Za-z0-9-]+')->name('policies.wording-pdf');
            Route::get('policies/{policyNumber}/complaint-procedure-pdf', [\AlphaDirect\Http\Controllers\Api\V1\PublicPartnerAuthController::class, 'complaintProcedurePdf'])->where('policyNumber', '[A-Za-z0-9-]+')->name('policies.complaint-pdf');
        });
    });

    // ─── Public policy lookup (no auth for masked search; session-token gated for full detail) ─
    // Replaces legacy findPolicy / findPolicy2 / findPolicyAlphaFePay.
    // Search returns masked PII only — full detail requires a session
    // token from a successful OTP/magic-link verify, and the token's
    // cellphone must match the policy's customer cellphone.

    Route::prefix('public/policies')->name('public.policies.')->group(function () {
        Route::post('find',                   [\AlphaDirect\Http\Controllers\Api\V1\PublicPolicyController::class, 'find'])
            ->middleware('throttle:10,1')->name('find');
        Route::post('{policyNumber}/detail',  [\AlphaDirect\Http\Controllers\Api\V1\PublicPolicyController::class, 'detail'])
            ->middleware('throttle:20,1')->name('detail');
        // Fixed-tier "Gold" upgrades (TP→TP Gold, ADI→ADI Gold, cellphone
        // P49→P99). Bearer-gated + cellphone-bound inside the controller,
        // same as detail. Tight throttle — this mutates the live policy and
        // can initiate a payment, so it shouldn't fire fast.
        Route::post('{policyNumber}/upgrade', [\AlphaDirect\Http\Controllers\Api\V1\PublicPolicyUpgradeController::class, 'upgrade'])
            ->middleware('throttle:5,1')->name('upgrade');

        // Hospital Cashback Insurance (product_id=9) co-applicant self-service —
        // add/edit/remove a dependant on an already-issued policy. Each mutation
        // recalculates premium immediately (HcbCoapplicantService). Same
        // Bearer-session + cellphone-ownership guard as detail()/upgrade above.
        Route::post('{policyNumber}/coapplicants',                    [\AlphaDirect\Http\Controllers\Api\V1\PublicPolicyController::class, 'addCoapplicant'])
            ->middleware('throttle:10,1')->name('coapplicants.add');
        Route::put('{policyNumber}/coapplicants/{coapplicantId}',     [\AlphaDirect\Http\Controllers\Api\V1\PublicPolicyController::class, 'updateCoapplicant'])
            ->middleware('throttle:10,1')->name('coapplicants.update');
        Route::delete('{policyNumber}/coapplicants/{coapplicantId}',  [\AlphaDirect\Http\Controllers\Api\V1\PublicPolicyController::class, 'deleteCoapplicant'])
            ->middleware('throttle:10,1')->name('coapplicants.delete');

        // Duplicate-prevention: is the customer (by identity) already holding a
        // non-cancelled policy of this single-cover product (ADI/Legal/Mobile)?
        // Live pre-check for the Start create form; create endpoints re-enforce.
        Route::post('check-product-duplicate', [\AlphaDirect\Http\Controllers\Api\V1\PublicPolicyController::class, 'checkProductDuplicate'])
            ->middleware('throttle:30,1')->name('check-product-duplicate');
    });

    // ─── Public customer lookup (masked PII only) ────────────────────────
    // Returns only { exists, displayName, masked cellphone } — never
    // email, address, DOB, or full name. Used by the OCR-first onboarding
    // flow on Landing to detect "returning customer" so we can prompt
    // for OTP. Full PII requires session-token auth.
    Route::get('public/customers/lookup', [\AlphaDirect\Http\Controllers\Api\V1\PublicPolicyController::class, 'publicCustomerLookup'])
        ->middleware('throttle:30,1')->name('public.customers.lookup');

    // ─── Public motor rating engine (no auth — quote shopping) ──────────
    // The customer is shopping; they don't have a session yet. Wraps
    // VehicleLookupController::calculatePremium with a tighter throttle
    // (15/min/IP) since each call hits the upstream rating service.
    Route::post('public/vehicle/calculate-premium', [\AlphaDirect\Http\Controllers\Api\V1\VehicleLookupController::class, 'calculatePremium'])
        ->middleware('throttle:15,1')->name('public.vehicle.calculatePremium');

    // ─── Public Travel Insurance (MAPFRE / MAWDY connector) ─────────────
    // The Start portal's Travel journey (Screens 1-3) talks ONLY to these
    // endpoints — never to MAPFRE directly, because the eMiA + Cognito
    // credentials are server-side secrets. PublicTravelController wraps
    // Services\Mapfre\MapfreTravelClient and normalises both the payload
    // shape and the failure modes.
    //
    // Throttles reflect what each endpoint costs:
    //   verify-agent  5/min  — PIN check, must not be brute-forceable
    //   catalogue     api_read — cheap, cacheable lookups
    //   price        15/min  — one upstream rating call each, fires on
    //                          every settled edit of the trip form
    //   quote        10/min  — persists a quote upstream
    //   contract      5/min  — binds a contract; must not fire fast
    Route::prefix('public/travel')->name('public.travel.')->group(function () {
        Route::post('verify-agent',    [\AlphaDirect\Http\Controllers\Api\V1\PublicTravelController::class, 'verifyAgent'])
            ->middleware('throttle:5,1')->name('verifyAgent');

        Route::get('packages',         [\AlphaDirect\Http\Controllers\Api\V1\PublicTravelController::class, 'packages'])
            ->middleware('throttle:api_read')->name('packages');
        Route::get('trip-types',       [\AlphaDirect\Http\Controllers\Api\V1\PublicTravelController::class, 'tripTypes'])
            ->middleware('throttle:api_read')->name('tripTypes');
        Route::get('destinations',     [\AlphaDirect\Http\Controllers\Api\V1\PublicTravelController::class, 'destinations'])
            ->middleware('throttle:api_read')->name('destinations');
        Route::get('fiscal-id-types',  [\AlphaDirect\Http\Controllers\Api\V1\PublicTravelController::class, 'fiscalIdTypes'])
            ->middleware('throttle:api_read')->name('fiscalIdTypes');

        // Prices EVERY cover tier for one trip in a single upstream call
        // (MAPFRE's /product/ALL/price). Feeds Screen 1's tier picker.
        Route::post('price-tiers',     [\AlphaDirect\Http\Controllers\Api\V1\PublicTravelController::class, 'priceTiers'])
            ->middleware('throttle:15,1')->name('priceTiers');
        Route::post('price',           [\AlphaDirect\Http\Controllers\Api\V1\PublicTravelController::class, 'price'])
            ->middleware('throttle:15,1')->name('price');
        Route::post('quote',           [\AlphaDirect\Http\Controllers\Api\V1\PublicTravelController::class, 'quote'])
            ->middleware('throttle:10,1')->name('quote');
        Route::post('contract',        [\AlphaDirect\Http\Controllers\Api\V1\PublicTravelController::class, 'contract'])
            ->middleware('throttle:5,1')->name('contract');

        // ─── Digital proposal form, signed by OTP ───────────────────────
        // The paper "Travel Insurance Proposal Form" as a screen: proposer,
        // other travellers, the four health questions, the declaration, and
        // an OTP in place of the Insured's Signature (ECTA 2014 s.17).
        // PublicTravelProposalController enforces the order server-side —
        // declaration accepted before a code is issued, row immutable once
        // signed — and renders + stores the completed form on signature.
        //
        //   declaration   api_read — static wording, cacheable, no auth
        //   proposal      20/min   — the portal re-posts the whole draft
        //   otp/send       5/min   — issues a real OTP; must not be pumpable
        //   otp/verify    10/min   — service adds a 5-attempt lockout
        //   link          10/min   — post-bind linkage + document re-render
        Route::get('proposal/declaration', [\AlphaDirect\Http\Controllers\Api\V1\PublicTravelProposalController::class, 'declaration'])
            ->middleware('throttle:api_read')->name('proposal.declaration');
        Route::post('proposal',            [\AlphaDirect\Http\Controllers\Api\V1\PublicTravelProposalController::class, 'save'])
            ->middleware('throttle:20,1')->name('proposal.save');
        Route::get('proposal/{id}',        [\AlphaDirect\Http\Controllers\Api\V1\PublicTravelProposalController::class, 'show'])
            ->whereNumber('id')->middleware('throttle:api_read')->name('proposal.show');
        Route::post('proposal/{id}/otp/send',   [\AlphaDirect\Http\Controllers\Api\V1\PublicTravelProposalController::class, 'sendOtp'])
            ->whereNumber('id')->middleware('throttle:5,1')->name('proposal.otp.send');
        Route::post('proposal/{id}/otp/verify', [\AlphaDirect\Http\Controllers\Api\V1\PublicTravelProposalController::class, 'verifyOtp'])
            ->whereNumber('id')->middleware('throttle:10,1')->name('proposal.otp.verify');
        Route::post('proposal/{id}/link',       [\AlphaDirect\Http\Controllers\Api\V1\PublicTravelProposalController::class, 'link'])
            ->whereNumber('id')->middleware('throttle:10,1')->name('proposal.link');
    });

    // ─── Public payment initiate (Bearer-auth, payment_authorize purpose) ─
    // Customer has OTP-verified their cellphone and is being redirected
    // to the gateway to actually pay. Each gateway has its own initiator
    // because the payload + auth model differs (DPO is XML createToken,
    // RealPay is JSON, N-Genius/VCS are different again). Throttled
    // 10/min/IP — payment redirects shouldn't fire faster than that.
    Route::prefix('public/payments')->name('public.payments.')->middleware('throttle:10,1')->group(function () {
        Route::post('dpo/initiate',     [\AlphaDirect\Http\Controllers\Api\V1\PublicPaymentController::class, 'initiateDpo'])->name('dpo.initiate');
        Route::post('ngenius/initiate', [\AlphaDirect\Http\Controllers\Api\V1\PublicPaymentController::class, 'initiateNgenius'])->name('ngenius.initiate');
        Route::post('vcs/initiate',     [\AlphaDirect\Http\Controllers\Api\V1\PublicPaymentController::class, 'initiateVcs'])->name('vcs.initiate');
    });

    // ─── Public policy create (Bearer-auth, motor quote) ────────────────
    // Persists the customer-facing motor form into motor_quotes and
    // returns a quote_number that the FE then hands to DPO as CompanyRef.
    // After payment success, an admin-side worker materialises the
    // quote into real Policy + Customer + Motor rows.
    Route::prefix('public/policies')->middleware('throttle:5,1')->group(function () {
        Route::post('create-motor', [\AlphaDirect\Http\Controllers\Api\V1\PublicPolicyCreateController::class, 'createMotor'])
            ->name('public.policies.create.motor');
        // Bundle creation — multi-product cart from /start (Bundled Products
        // tile). Server recomputes subtotal + discount + total so a tampered
        // FE can't claim a higher discount tier.
        Route::post('create-bundle', [\AlphaDirect\Http\Controllers\Api\V1\PublicBundleCreateController::class, 'createBundle'])
            ->name('public.policies.create.bundle');

        // ─── Hospital Cashback Insurance (Adults Plan) ──────────────────
        // Replaces the legacy /api/createPolicy + /api/getHospitalCashbackPremium
        // chain alphaFEV2 used (ActivatetsosologoController::store +
        // ::calculatePremium + ::hospital_cashback). All three methods now
        // live on AlphaDirect\Http\Controllers\Api\V1\HospitalCashbackController
        // for one clean V1 surface.
        Route::post('hospital-cashback/premium-options',
            [\AlphaDirect\Http\Controllers\Api\V1\HospitalCashbackController::class, 'premiumOptions'])
            ->name('public.policies.hcb.premium-options');
        Route::post('hospital-cashback/calculate-premium',
            [\AlphaDirect\Http\Controllers\Api\V1\HospitalCashbackController::class, 'calculatePremium'])
            ->name('public.policies.hcb.calculate-premium');
        Route::post('create-hospital-cashback',
            [\AlphaDirect\Http\Controllers\Api\V1\HospitalCashbackController::class, 'createPolicy'])
            ->name('public.policies.create.hospital-cashback');

        // ─── Accidental Death Insurance (product 1) ─────────────────────
        // Replaces the legacy graphiteBWV8 /api/createPolicy call for
        // product 1. Same three-endpoint shape as HCB so the FE has one
        // canonical surface. See AccidentalDeathController for the V8
        // parity notes and the covered-lives vs beneficiaries split.
        Route::post('accidental-death/premium-options',
            [\AlphaDirect\Http\Controllers\Api\V1\AccidentalDeathController::class, 'premiumOptions'])
            ->name('public.policies.ad.premium-options');
        Route::post('accidental-death/calculate-premium',
            [\AlphaDirect\Http\Controllers\Api\V1\AccidentalDeathController::class, 'calculatePremium'])
            ->name('public.policies.ad.calculate-premium');
        Route::post('create-accidental-death',
            [\AlphaDirect\Http\Controllers\Api\V1\AccidentalDeathController::class, 'createPolicy'])
            ->name('public.policies.create.accidental-death');

        // ─── Legal Insurance (product 4) ────────────────────────────────
        // Dedicated endpoint because the generic MaterialiseBundleQuoteJob
        // cannot reconstruct the spouse-as-legal-beneficiary row required
        // for Legal payouts. Mirrors the HCB / ACD three-endpoint shape.
        Route::post('legal-insurance/premium-options',
            [\AlphaDirect\Http\Controllers\Api\V1\LegalInsuranceController::class, 'premiumOptions'])
            ->name('public.policies.legal.premium-options');
        Route::post('legal-insurance/calculate-premium',
            [\AlphaDirect\Http\Controllers\Api\V1\LegalInsuranceController::class, 'calculatePremium'])
            ->name('public.policies.legal.calculate-premium');
        Route::post('create-legal-insurance',
            [\AlphaDirect\Http\Controllers\Api\V1\LegalInsuranceController::class, 'createPolicy'])
            ->name('public.policies.create.legal-insurance');

        // ─── Third Party Car Insurance (product 2) ──────────────────────
        // Dedicated endpoint — third-party motor cover. Captures core
        // vehicle identity (plate / make / model / year) but no sum-insured
        // valuation. Mirrors the Legal / HCB / ACD shape; mints MIS- so DPO
        // initiate resolves it natively.
        Route::post('third-party-car/premium-options',
            [\AlphaDirect\Http\Controllers\Api\V1\ThirdPartyCarController::class, 'premiumOptions'])
            ->name('public.policies.tpcar.premium-options');
        Route::post('third-party-car/calculate-premium',
            [\AlphaDirect\Http\Controllers\Api\V1\ThirdPartyCarController::class, 'calculatePremium'])
            ->name('public.policies.tpcar.calculate-premium');
        Route::post('create-third-party-car',
            [\AlphaDirect\Http\Controllers\Api\V1\ThirdPartyCarController::class, 'createPolicy'])
            ->name('public.policies.create.third-party-car');

        // ─── Goods-in-Transit / Alpha Transit Cover (product 25) ────────
        // Dedicated endpoint — per-shipment transit cover, 14 days, one-time
        // value-rated premium (same rating table as the courier platform).
        // Mints GIT- so DPO initiate resolves it natively; premium_freq
        // 'once' keeps it out of the recurring debit-order schedule.
        Route::post('goods-in-transit/rating-options',
            [\AlphaDirect\Http\Controllers\Api\V1\GoodsInTransitController::class, 'ratingOptions'])
            ->name('public.policies.git.rating-options');
        Route::post('goods-in-transit/calculate-premium',
            [\AlphaDirect\Http\Controllers\Api\V1\GoodsInTransitController::class, 'calculatePremium'])
            ->name('public.policies.git.calculate-premium');
        Route::post('create-goods-in-transit',
            [\AlphaDirect\Http\Controllers\Api\V1\GoodsInTransitController::class, 'createPolicy'])
            ->name('public.policies.create.goods-in-transit');

        // ─── Mobile and Electronic Device Insurance (product 5) ─────────
        // Dedicated endpoint for the STANDALONE device-cover purchase.
        // Before this, product 5 could only be staged via /create-bundle
        // (a BQ-* quote materialised at payment time), so a single-product
        // "create a device policy now (pending payment)" had no path.
        // Captures the insured device into policy_cellphone; mints MIS-.
        // NOTE: product 5 is deliberately NOT added to the bundle's
        // DEDICATED_ENDPOINT_PRODUCTS gate — device cover is still a valid
        // bundle line (MaterialiseBundleQuoteJob::seedDevices handles it).
        Route::post('mobile-electronic/premium-options',
            [\AlphaDirect\Http\Controllers\Api\V1\MobileElectronicController::class, 'premiumOptions'])
            ->name('public.policies.mobelec.premium-options');
        Route::post('mobile-electronic/calculate-premium',
            [\AlphaDirect\Http\Controllers\Api\V1\MobileElectronicController::class, 'calculatePremium'])
            ->name('public.policies.mobelec.calculate-premium');
        Route::post('create-mobile-electronic',
            [\AlphaDirect\Http\Controllers\Api\V1\MobileElectronicController::class, 'createPolicy'])
            ->name('public.policies.create.mobile-electronic');
    });

    // ─── Customer self-service ("/me") ────────────────────────────────
    // Bearer-session-gated read endpoints powering the customer portal.
    // The session's cellphone resolves to a customer_id and every query
    // is scoped to that ID, so a token-holder can never read a different
    // customer's data. Throttled gently (60/min) — a customer flicking
    // between policies + payments can hit it many times in a session.
    Route::prefix('public/me')->name('public.me.')->middleware('throttle:60,1')->group(function () {
        Route::get('policies',                       [\AlphaDirect\Http\Controllers\Api\Public\MeController::class, 'policies'])->name('policies');
        Route::get('policies/{policyNumber}',        [\AlphaDirect\Http\Controllers\Api\Public\MeController::class, 'policyDetail'])->name('policy.detail');
        Route::get('policies/{policyNumber}/documents', [\AlphaDirect\Http\Controllers\Api\Public\MeController::class, 'documents'])->name('policy.documents');
        Route::get('payments',                       [\AlphaDirect\Http\Controllers\Api\Public\MeController::class, 'payments'])->name('payments');

        // Tighter throttle than the read endpoints above — this triggers a
        // real PDF-generate + email send, not a cheap read.
        Route::post('policies/{policyNumber}/resend-documents', [\AlphaDirect\Http\Controllers\Api\Public\MeController::class, 'resendDocuments'])
            ->middleware('throttle:5,1')->name('policy.resend-documents');
    });

    // ─── Public RealPay (debit-order setup) ───────────────────────────
    // Banks + branches are read-only lookups (no auth, GETs only).
    // initiate is Bearer-gated (payment_authorize session) and locked to
    // the motor_quote's cellphone. Live RealPay API call gated by
    // REALPAY_LIVE env so the FE flow can be tested without sandbox creds.
    Route::prefix('public/realpay')->name('public.realpay.')->group(function () {
        Route::get('banks',                       [\AlphaDirect\Http\Controllers\Api\Public\RealpayController::class, 'banks'])
            ->middleware('throttle:60,1')->name('banks');
        Route::get('banks/{bankId}/branches',     [\AlphaDirect\Http\Controllers\Api\Public\RealpayController::class, 'branches'])
            ->whereNumber('bankId')->middleware('throttle:60,1')->name('branches');
        Route::post('initiate',                   [\AlphaDirect\Http\Controllers\Api\Public\RealpayController::class, 'initiate'])
            ->middleware('throttle:5,1')->name('initiate');

        // Lets the front end ask what happened after a debit-order journey
        // instead of guessing. Returns only what the customer already holds:
        // mandate state, policy state and what to do next — no banking details,
        // no identity data, no amounts. A policy number in a query string is not
        // authentication, which is why nothing sensitive is exposed here.
        //
        // The two states are NOT the same fact. An `active` mandate means the
        // first collection succeeded; policy activation is applied separately by
        // the instalment webhook.
        Route::get('mandate-status',              [\AlphaDirect\Http\Controllers\Api\Public\RealpayController::class, 'mandateStatus'])
            ->middleware('throttle:30,1')->name('mandateStatus');
    });

    // ─── Public OTP (no auth — verifies customer identity in start flows) ─
    // Send: 5/min/IP — cellphone-level cooldown is enforced inside the
    // service (60s per cellphone+purpose). Verify: 20/min/IP so a frustrated
    // customer mistyping the code isn't bounced before brute-force lockout
    // (5 attempts) kicks in.
    //
    // Channel chain (Botswana-aware): SMS works on every phone with no
    // data, so it's the default. WhatsApp only with explicit consent
    // (data is expensive, customer may not have the app). Email last —
    // requires data + a valid address.
    Route::prefix('public/otp/customer')->name('public.otp.customer.')->group(function () {
        Route::post('send',   [\AlphaDirect\Http\Controllers\Api\V1\OtpController::class, 'send'])
            ->middleware('throttle:5,1')->name('send');
        Route::post('verify', [\AlphaDirect\Http\Controllers\Api\V1\OtpController::class, 'verify'])
            ->middleware('throttle:20,1')->name('verify');

        // Magic-link variant — one-tap URL replaces the 6-digit code.
        // Send: 5/min/IP (same as code OTP). Verify: GET-by-design,
        // 30/min/IP because URL clicks may have FE retries on flaky
        // networks; the token is single-use anyway.
        Route::post('magic-link',          [\AlphaDirect\Http\Controllers\Api\V1\OtpController::class, 'sendMagicLink'])
            ->middleware('throttle:5,1')->name('magicLink.send');
        Route::get('magic-link/verify',    [\AlphaDirect\Http\Controllers\Api\V1\OtpController::class, 'verifyMagicLink'])
            ->middleware('throttle:30,1')->name('magicLink.verify');

        // Plate-based OTP — for vehicle inspection. The customer brings
        // the car to the depot, types the plate, and the BE looks up the
        // owning customer's cellphone server-side so it never crosses
        // to the FE. Send + verify keyed on `plate` instead of `identifier`.
        Route::post('send-for-plate',   [\AlphaDirect\Http\Controllers\Api\V1\OtpController::class, 'sendForPlate'])
            ->middleware('throttle:5,1')->name('sendForPlate');
        Route::post('verify-for-plate', [\AlphaDirect\Http\Controllers\Api\V1\OtpController::class, 'verifyForPlate'])
            ->middleware('throttle:20,1')->name('verifyForPlate');
    });

    // ─── Authentication ───────────────────────────────────────────────────────
    Route::post('auth/login',          [AuthController::class, 'login'])->middleware('throttle:auth')->name('auth.login');
    Route::post('auth/setup-password', [AuthController::class, 'setupPassword'])->name('auth.setupPassword'); // disabled unless APP_SETUP_KEY set
    Route::post('auth/logout',         [AuthController::class, 'logout'])->middleware('auth:sanctum')->name('auth.logout');
    Route::get('auth/user',            [AuthController::class, 'user'])->middleware('auth:sanctum')->name('auth.user');

    // ─── SSO (Single Sign-On) ───────────────────────────────────────────────
    Route::get('auth/sso/generate',  [AuthController::class, 'generateSsoToken'])->middleware('auth:sanctum')->name('auth.sso.generate');
    Route::post('auth/sso/exchange', [AuthController::class, 'exchangeSsoToken'])->middleware('throttle:auth')->name('auth.sso.exchange');
    Route::get('auth/microsoft/url', [\AlphaDirect\Http\Controllers\Auth\MicrosoftSsoController::class, 'apiRedirectUrl'])->name('auth.microsoft.url');

    // ─── Authenticated read routes ────────────────────────────────────────────
    // NB: pii.mask removed — V2 is an internal admin tool and operators need
    // to see full customer names / emails / phones to do their job. The
    // masking middleware was built for customer-facing rekyc flows and
    // applied too broadly.
    Route::middleware(['auth:sanctum', 'throttle:api_read', 'XssSanitizer'])->group(function () {

        // Current-user profile — aggregated identity + account + manager +
        // workload stats for the User Profile page.
        Route::get('me/profile', [MeController::class, 'profile'])->name('me.profile');
        Route::post('me/avatar',   [MeAvatarController::class, 'upload'])->name('me.avatar.upload');
        Route::delete('me/avatar', [MeAvatarController::class, 'destroy'])->name('me.avatar.destroy');

        // Dashboard
        Route::get('dashboard/stats', [DashboardController::class, 'stats'])->name('dashboard.stats');
        Route::get('dashboard/finance', [DashboardController::class, 'financeStats'])->name('dashboard.finance');
        Route::get('dashboard/sales-performance', [DashboardController::class, 'salesPerformance'])->name('dashboard.salesPerformance');

        // Website leads — submissions from the public marketing website
        Route::get('website-leads', [\AlphaDirect\Http\Controllers\Api\V1\WebsiteLeadController::class, 'index'])->name('websiteLeads.index');
        Route::get('website-leads/files/{name}', [\AlphaDirect\Http\Controllers\Api\V1\WebsiteLeadController::class, 'downloadFile'])->name('websiteLeads.downloadFile');
        Route::patch('website-leads/{id}/status', [\AlphaDirect\Http\Controllers\Api\V1\WebsiteLeadController::class, 'updateStatus'])->name('websiteLeads.updateStatus');

        // Policies — read
        Route::get('policies',                      [PolicyController::class, 'index'])->name('policies.index');
        Route::get('policies/{id}',                 [PolicyController::class, 'show'])->name('policies.show');

        // ── Call-centre OTP unlock (POPIA/DPA design 2026-06-22) ──────────
        // Non-privileged agent OTP-unlocks a policy (customer reads back the
        // code) before sensitive actions / full PII reveal. Admins+Managers
        // bypass. Every unlock is logged. Reuses PublicOtpService (Infobip).
        // (POSTs sit in the read group for cohesion; the service itself rate-
        // limits OTP sends — reviewer may relocate to the write group.)
        Route::get('policies/{id}/unlock/status',  [\AlphaDirect\Http\Controllers\Api\V1\OtpUnlockController::class, 'status'])->name('policies.unlock.status');
        Route::post('policies/{id}/unlock/send',   [\AlphaDirect\Http\Controllers\Api\V1\OtpUnlockController::class, 'send'])->name('policies.unlock.send');
        Route::post('policies/{id}/unlock/verify', [\AlphaDirect\Http\Controllers\Api\V1\OtpUnlockController::class, 'verify'])->name('policies.unlock.verify');

        // Policy edit data (for loading draft back into form)
        Route::get('policies/{id}/edit-data',         [PolicyCreateController::class, 'editData'])->name('policies.editData');

        // Policy lazy-loaded tabs
        Route::get('policies/{id}/vehicles',        [PolicyController::class, 'vehicles'])->name('policies.vehicles');
        Route::get('policies/{id}/members',         [PolicyController::class, 'members'])->name('policies.members');
        Route::get('policies/{id}/devices',         [PolicyController::class, 'devices'])->name('policies.devices');
        Route::get('policies/{id}/coverages',       [PolicyController::class, 'coverages'])->name('policies.coverages');
        Route::get('policies/{id}/actions',         [PolicyController::class, 'actions'])->name('policies.actions');
        Route::get('policies/{id}/claims',          [PolicyController::class, 'claims'])->name('policies.claims');
        Route::get('policies/{id}/transactions',    [PolicyController::class, 'transactions'])->name('policies.transactions');
        Route::get('policies/{id}/risk-addresses',  [PolicyController::class, 'riskAddresses'])->name('policies.riskAddresses');
        Route::get('policies/{id}/kyc-documents',   [PolicyController::class, 'kycDocuments'])->name('policies.kycDocuments');
        Route::get('policies/{id}/logs',             [PolicyController::class, 'logs'])->name('policies.logs');
        Route::get('policies/{id}/logs/export',      [PolicyController::class, 'logsExport'])->name('policies.logs.export');
        Route::get('policies/{id}/ledger',           [PolicyController::class, 'ledger'])->name('policies.ledger');
        Route::get('policies/{id}/health-summary',   [PolicyController::class, 'healthSummary'])->name('policies.healthSummary');
        Route::get('policies/{id}/client-health',    [PolicyController::class, 'clientHealth'])->name('policies.clientHealth');
        Route::get('policies/{id}/schedule-transactions', [PolicyController::class, 'scheduleTransactions'])->name('policies.scheduleTransactions');
        Route::post('policies/{id}/schedule-transactions', [PolicyController::class, 'addScheduleTransaction'])->name('policies.addScheduleTransaction');
        Route::post('policies/{id}/schedule-transactions/cancel-all', [PolicyController::class, 'cancelAllScheduleTransactions'])->name('policies.cancelAllScheduleTransactions');
        Route::post('policies/{id}/schedule-transactions/{scheduleId}/cancel', [PolicyController::class, 'cancelScheduleTransaction'])->name('policies.cancelScheduleTransaction');
        Route::put('policies/{id}/schedule-transactions/billing-date', [PolicyController::class, 'updateAllScheduleBillingDates'])->name('policies.updateAllScheduleBillingDates');
        Route::put('policies/{id}/schedule-transactions/{scheduleId}/billing-date', [PolicyController::class, 'updateScheduleBillingDate'])->name('policies.updateScheduleBillingDate');
        Route::put('policies/{id}/schedule-transactions/{scheduleId}/premium', [PolicyController::class, 'updateSchedulePremium'])->name('policies.updateSchedulePremium');
        Route::get('policies/{id}/mati',             [PolicyController::class, 'matiVerification'])->name('policies.mati');
        // Mati Verification tab actions (V8 parity): send link, fetch from API, update IDs.
        Route::post('policies/{id}/mati/verification-link/{type}', [PolicyController::class, 'matiVerificationLink'])->name('policies.mati.link');
        Route::post('policies/{id}/mati/fetch',      [PolicyController::class, 'matiFetch'])->name('policies.mati.fetch');
        Route::post('policies/{id}/mati/update',     [PolicyController::class, 'matiUpdate'])->name('policies.mati.update');
        Route::get('policies/{id}/linked-policies',  [PolicyController::class, 'linkedPolicies'])->name('policies.linked');
        Route::get('policies/{id}/attachments',      [PolicyController::class, 'attachments'])->name('policies.attachments');
        // V8 Attachment-tab payload (one row per policy_attachments record).
        Route::get('policies/{id}/attachment-list',  [PolicyController::class, 'attachmentList'])->name('policies.attachmentList');
        // file_type lookup for the Document Type dropdown on the Attachments tab.
        Route::get('policies/lookups/file-types',    [PolicyController::class, 'fileTypesLookup'])->name('policies.fileTypesLookup');
        Route::get('policies/{id}/terms',                    [PolicyController::class, 'terms'])->name('policies.terms');
        Route::get('policies/{id}/reinsurance',              [PolicyController::class, 'reinsurance'])->name('policies.reinsurance');
        Route::post('policies/{id}/reinsurance/recalculate', [PolicyController::class, 'recalculateReinsurance'])->name('policies.reinsurance.recalculate');
        Route::get('policies/{id}/reinsurance/status', [PolicyController::class, 'reinsuranceStatus'])->name('policies.reinsurance.status');
        Route::get('policies/{id}/specialist-coverages',     [PolicyController::class, 'specialistCoverages'])->name('policies.specialistCoverages');

        // Excel template downloads (read-safe: generates and streams the file, no DB writes)
        Route::get('policies/{id}/excel-template/{type}',    [PolicyExcelController::class, 'downloadTemplate'])->name('policies.excelTemplate');
        Route::get('policies/{id}/export-full',              [PolicyExcelController::class, 'exportFullPolicy'])->name('policies.exportFull');

        // Customers
        // Customer Block List (static path MUST come before {id} wildcard)
        Route::get('customers',              [CustomerController::class, 'index'])->name('customers.index');
        // Quick-add a new customer (name + contact only; KYC/PII captured
        // later via customers/{id} update).
        Route::post('customers',             [CustomerController::class, 'store'])->name('customers.store');
        Route::get('customers/block-list', [CustomerBlockListController::class, 'index'])->name('customers.blockList');
        Route::post('customers/block-list', [CustomerBlockListController::class, 'store'])->name('customers.blockList.store');
        // Detail for the FE "View" modal. Numeric constraint so it can't
        // shadow the static "block-list" GET above, and it sits before the
        // customers/{id} wildcard so the two-segment path resolves here.
        Route::get('customers/block-list/{id}', [CustomerBlockListController::class, 'show'])
            ->whereNumber('id')->name('customers.blockList.show');
        Route::put('customers/block-list/{id}', [CustomerBlockListController::class, 'update'])
            ->whereNumber('id')->name('customers.blockList.update')
            ->middleware('permission:customer-edit|customer-kyc-edit');
        Route::post('customers/{id}/unblock', [CustomerBlockListController::class, 'unblock'])
            ->whereNumber('id')->name('customers.unblock')
            ->middleware('permission:customer-edit|customer-kyc-edit');
        // Customer aliases (add/remove) — back the block-list detail modal's
        // alias list. POST adds one alias to a customer; DELETE removes one by
        // alias id. Registered here (before the customers/{id} wildcard) with
        // the same permission gate as update/unblock.
        Route::post('customers/{id}/aliases', [CustomerBlockListController::class, 'addAlias'])
            ->whereNumber('id')->name('customers.aliases.store')
            ->middleware('permission:customer-edit|customer-kyc-edit');
        Route::delete('customers/aliases/{aliasId}', [CustomerBlockListController::class, 'removeAlias'])
            ->whereNumber('aliasId')->name('customers.aliases.destroy')
            ->middleware('permission:customer-edit|customer-kyc-edit');
        // Lookup-by-identifier — used by alphaFEV2's OCR-first onboarding
        // flow to pre-fill the form with a returning customer's profile.
        // Must come BEFORE the {id} route or Laravel matches "lookup" as
        // an integer parameter and 404s on the show() handler.
        Route::get('customers/lookup',     [CustomerController::class, 'lookup'])->name('customers.lookup');
        Route::get('customers/{id}',        [CustomerController::class, 'show'])->name('customers.show');
        Route::put('customers/{id}',        [CustomerController::class, 'update'])->name('customers.update')
            ->middleware('permission:customer-edit|customer-kyc-edit');
        Route::get('customers/{id}/policies', [CustomerController::class, 'policies'])->name('customers.policies');

        // Company management
        Route::post('companies', [LookupController::class, 'createCompany'])->name('companies.store');
        Route::get('lookups/companies/{id}/details', [LookupController::class, 'companyDetails'])->name('companies.details');
        Route::put('companies/{id}', [LookupController::class, 'updateCompany'])->name('companies.update');

        // Products (cached, available to all authenticated users)
        Route::get('products',                       [ProductController::class, 'index'])->name('products.index');
        Route::get('products/{id}/plans',            [ProductController::class, 'plans'])->name('products.plans');
        // Toggle whether a product is visible on start.alphadirect.co.bw.
        // Same flag the legacy graphite admin's product-edit form writes;
        // busts the public lookup cache so the change is immediate.
        Route::patch('products/{id}/visibility',     [ProductController::class, 'setVisibility'])->name('products.setVisibility');

        // ─── Lookups (form data for creation wizards) ─────────────────────────
        Route::get('lookups/policy-create',          [LookupController::class, 'policyCreateData'])->name('lookups.policyCreate');
        Route::get('lookups/agencies',               [LookupController::class, 'agencies'])->name('lookups.agencies');
        Route::get('lookups/agents',                 [LookupController::class, 'agents'])->name('lookups.agents');
        Route::get('lookups/departments',            [LookupController::class, 'departments'])->name('lookups.departments');
        Route::get('lookups/roles',                  [LookupController::class, 'roles'])->name('lookups.roles');
        Route::get('lookups/agencies/{id}/agents',   [LookupController::class, 'agentsByAgency'])->name('lookups.agentsByAgency');
        Route::get('lookups/states/{id}/cities',     [LookupController::class, 'citiesByState'])->name('lookups.citiesByState');
        Route::get('lookups/products/{id}/plans',    [LookupController::class, 'plansByProduct'])->name('lookups.plansByProduct');
        Route::get('lookups/products/{id}/motor-types', [LookupController::class, 'motorTypesByProduct'])->name('lookups.motorTypesByProduct');
        Route::get('lookups/products/{id}/coverages',[LookupController::class, 'coveragesByProduct'])->name('lookups.coveragesByProduct');
        Route::get('lookups/coverages/{id}/subcoverages',    [LookupController::class, 'subcoverages'])->name('lookups.subcoverages');
        Route::get('lookups/coverages/{id}/extensions',      [LookupController::class, 'extensions'])->name('lookups.extensions');
        Route::get('lookups/extensions/{extensionCode}/limits', [LookupController::class, 'extensionLimits'])->name('lookups.extensionLimits');
        Route::get('lookups/coverages/{id}/specified-items',  [LookupController::class, 'specifiedItems'])->name('lookups.specifiedItems');
        Route::get('lookups/companies',              [LookupController::class, 'companies'])->name('lookups.companies');
        Route::get('lookups/companies/{id}/sub',     [LookupController::class, 'subCompanies'])->name('lookups.subCompanies');
        Route::get('lookups/vehicle-makes',          [LookupController::class, 'vehicleMakes'])->name('lookups.vehicleMakes');
        Route::get('lookups/vehicle-makes/{id}/models', [LookupController::class, 'vehicleModels'])->name('lookups.vehicleModels');
        Route::get('lookups/device-brands',          [LookupController::class, 'deviceBrands'])->name('lookups.deviceBrands');
        Route::get('lookups/device-brands/{brand}/models', [LookupController::class, 'deviceModels'])->name('lookups.deviceModels');
        Route::get('lookups/banks/{id}/branches',    [LookupController::class, 'bankBranches'])->name('lookups.bankBranches');
        Route::get('lookups/products/{id}/details',  [LookupController::class, 'productDetails'])->name('lookups.productDetails');
        Route::get('lookups/transaction-types',       [LookupController::class, 'transactionTypes'])->name('lookups.transactionTypes');
        Route::get('lookups/transaction-subtypes/{type}', [LookupController::class, 'transactionSubTypes'])->name('lookups.transactionSubTypes');

        // Vehicle lookups (for quote rerate)
        Route::get('vehicle/makes', [VehicleLookupController::class, 'makes'])->name('vehicle.makes');
        Route::get('vehicle/models', [VehicleLookupController::class, 'models'])->name('vehicle.models');
        Route::get('vehicle/years', [VehicleLookupController::class, 'years'])->name('vehicle.years');
        Route::get('vehicle/variants', [VehicleLookupController::class, 'variants'])->name('vehicle.variants');
        Route::post('vehicle/calculate-premium', [VehicleLookupController::class, 'calculatePremium'])->name('vehicle.calculatePremium');

        // What's New — in-app release notes (login notification + changelog)
        Route::get('release-notes', [\AlphaDirect\Http\Controllers\Api\V1\ReleaseNoteController::class, 'index'])->name('releaseNotes.index');
        Route::get('release-notes/unseen', [\AlphaDirect\Http\Controllers\Api\V1\ReleaseNoteController::class, 'unseen'])->name('releaseNotes.unseen');
        Route::post('release-notes/seen', [\AlphaDirect\Http\Controllers\Api\V1\ReleaseNoteController::class, 'markSeen'])->name('releaseNotes.seen');
        Route::post('release-notes', [\AlphaDirect\Http\Controllers\Api\V1\ReleaseNoteController::class, 'store'])->middleware('role:Super Admin|Manager|Admin')->name('releaseNotes.store');

        // Quotes
        Route::get('quotes', [QuoteController::class, 'index'])->name('quotes.index');
        Route::get('quotes/{id}', [QuoteController::class, 'show'])->name('quotes.show');
        Route::get('quotes/{id}/history', [QuoteController::class, 'history'])->name('quotes.history');

        // Group Policies
        Route::get('group-policies', [GroupPolicyController::class, 'index'])->name('groupPolicies.index');

        // Renewals
        Route::get('renewals', [RenewalController::class, 'index'])->name('renewals.index');

        // Cancel Requests
        Route::get('cancel-requests', [CancelRequestController::class, 'index'])->name('cancelRequests.index');
        // DB-driven cancellation reasons (same source as the start frontend) for
        // the Policy Details > Cancel Policy modal.
        Route::get('cancel-requests/feedback-options', [CancelRequestController::class, 'feedbackOptions'])->name('cancelRequests.feedbackOptions');
        // Stored cancellation reason for a (cancelled) policy — shown on the
        // policy detail page.
        Route::get('policies/{id}/cancel-reason', [CancelRequestController::class, 'cancelReason'])->name('policies.cancelReason');

        // Assign Agent tab (old policy edit page parity) — agents + stores
        // lists plus the policy's current assignment.
        Route::get('policies/{id}/assign-agent', [PolicyController::class, 'assignAgentData'])->name('policies.assignAgentData');
        // Per-plan policy wording documents (old edit page Documents tab
        // $emailDocs parity) for the V2 Documents tab.
        Route::get('policies/{id}/wording-documents', [PolicyController::class, 'wordingDocuments'])->name('policies.wordingDocuments');

        // Employer Groups & AD Group Policies
        Route::get('employer-groups', [EmployerGroupController::class, 'index'])->name('employerGroups.index');
        Route::get('employer-groups/{id}', [EmployerGroupController::class, 'show'])->whereNumber('id')->name('employerGroups.show');
        Route::get('ad-group-policies', [EmployerGroupController::class, 'policies'])->name('adGroupPolicies.index');

        // Alpha Bridge widget — new tickets are now raised in the Bridge
        // helpdesk via its embeddable widget. The SPA calls this to swap the
        // signed-in session for a short-lived Bridge widget JWT; the shared
        // secret never leaves the server. See BridgeWidgetController.
        Route::post('bridge-widget/token', [BridgeWidgetController::class, 'token'])->name('bridgeWidget.token');
        Route::get('bridge-widget/assignees', [BridgeWidgetController::class, 'assignees'])->name('bridgeWidget.assignees');

        // Alpha Brain — read-only dashboard proxy. Surfaces the isolated brain
        // service's inbox inside Graphite under Graphite RBAC. The browser never
        // reaches the brain directly; the service token stays server-side.
        // GET-only — approvals are NOT proxied (see BrainProxyController).
        //
        // Two access tiers (CFO 26 Jul):
        //   summary/health  — COUNTS ONLY, PII-free — open to ALL authenticated
        //                     employees (the totals are not confidential).
        //   queue/affected/ — CUSTOMER-LEVEL rows (policy refs, detail) — gated by
        //   activity          `brain-queue` (Finance/Compliance/Underwriting/Claims
        //                     roles + named users; see the brain-queue seed migration).
        //   glossary/features/ — DEFINITIONS and COUNTS only, PII-free — open to all
        //   healthcare          employees. The console will not render a number it
        //                       cannot explain, so glossary is required for the page
        //                       to work (CFO 28 Jul: "make this self explanatory").
        //   extract           — Excel/CSV of customer-level rows → same
        //                       `brain-queue` tier as the queue itself.
        Route::get('brain/summary', [BrainProxyController::class, 'summary'])->name('brain.summary');
        Route::get('brain/health',  [BrainProxyController::class, 'health'])->name('brain.health');
        Route::get('brain/glossary', [BrainProxyController::class, 'glossary'])->name('brain.glossary');
        Route::get('brain/features', [BrainProxyController::class, 'features'])->name('brain.features');
        Route::get('brain/healthcare', [BrainProxyController::class, 'healthcare'])->name('brain.healthcare');
        Route::middleware('permission:brain-queue')->group(function () {
            Route::get('brain/queue',    [BrainProxyController::class, 'queue'])->name('brain.queue');
            Route::get('brain/affected', [BrainProxyController::class, 'affected'])->name('brain.affected');
            Route::get('brain/activity', [BrainProxyController::class, 'activity'])->name('brain.activity');
            Route::get('brain/extract',  [BrainProxyController::class, 'extract'])->name('brain.extract');
        });

        // Help Desk — issue intake + triage. Ungated (any authenticated user
        // can raise and view tickets); assignment routes the issue to whoever
        // can solve it. Reporter is captured from the session, not the body.
        Route::get('help-desk-tickets', [HelpDeskController::class, 'index'])->name('helpDesk.index');
        Route::get('help-desk-tickets/summary', [HelpDeskController::class, 'summary'])->name('helpDesk.summary');
        Route::post('help-desk-tickets', [HelpDeskController::class, 'store'])->name('helpDesk.store');
        Route::get('help-desk-tickets/{id}', [HelpDeskController::class, 'show'])->whereNumber('id')->name('helpDesk.show');
        Route::get('help-desk-tickets/{id}/audit', [HelpDeskController::class, 'audit'])->whereNumber('id')->name('helpDesk.audit');
        Route::get('help-desk/sla/dashboard', [SlaDashboardController::class, 'index'])->name('helpDesk.slaDashboard');
        Route::get('help-desk/sla/reports/{type}', [SlaReportController::class, 'export'])->name('helpDesk.slaReport');
        Route::get('help-desk-tickets/{id}/sla', [SlaController::class, 'show'])->whereNumber('id')->name('helpDesk.sla');
        Route::get('help-desk-tickets/{id}/sla/events', [SlaController::class, 'events'])->whereNumber('id')->name('helpDesk.slaEvents');

        // ─── Claims SLA + stage timeline (Claims Tracker -> Graphite, Phase 1) ──
        // Inert unless the `claims_sla` runtime toggle is on (default OFF). Reads;
        // static paths declared BEFORE the {id} wildcards. RBAC enforced in the
        // controller (Claims Team / Claims Manager).
        Route::get('claims/sla/dashboard', [ClaimSlaController::class, 'dashboard'])->name('claims.slaDashboard');
        Route::get('claims/sla/leaderboard', [ClaimSlaController::class, 'leaderboard'])->name('claims.slaLeaderboard');
        Route::get('claims-v2/{id}/sla-timeline', [ClaimSlaController::class, 'timeline'])->whereNumber('id')->name('claims.slaTimeline');
        Route::get('claims-v2/{id}/sla', [ClaimSlaController::class, 'sla'])->whereNumber('id')->name('claims.sla');

        // ─── Claims backdate governance — read/preview (Claims Tracker port) ──────
        // Management reads are a PREVIEW surface (RBAC in controller), available to
        // manage-role users regardless of the `claims_backdate_governance` flag so
        // admins can pre-configure. Only the enforcement hook + alerts + request
        // submission are flag-gated. Never touches live claim/financial data.
        Route::get('claims/backdate/settings',      [\AlphaDirect\Http\Controllers\Api\V1\BackdateControlController::class, 'settings'])->name('claims.backdate.settings');
        Route::get('claims/backdate/grants',        [\AlphaDirect\Http\Controllers\Api\V1\BackdateControlController::class, 'grants'])->name('claims.backdate.grants');
        Route::get('claims/backdate/grants/active', [\AlphaDirect\Http\Controllers\Api\V1\BackdateControlController::class, 'activeGrants'])->name('claims.backdate.grants.active');
        Route::get('claims/backdate/events',        [\AlphaDirect\Http\Controllers\Api\V1\BackdateControlController::class, 'events'])->name('claims.backdate.events');
        Route::get('claims/backdate/requests',      [\AlphaDirect\Http\Controllers\Api\V1\BackdateControlController::class, 'requests'])->name('claims.backdate.requests');
        // ─── Claims Notifications log + dashboard (Claims Tracker -> Graphite) ──
        // Inert unless the `claims_notifications` runtime toggle is on (default
        // OFF) — every endpoint 404s while disabled. READ-ONLY over the additive
        // claim_notification_log. RBAC enforced in the controller (Admin /
        // Super Admin / Claims Manager). Static paths BEFORE the {id} wildcard.
        Route::get('claims/notifications/overview', [ClaimNotificationController::class, 'overview'])->name('claims.notif.overview');
        Route::get('claims/notifications/recent', [ClaimNotificationController::class, 'recent'])->name('claims.notif.recent');
        Route::get('claims/notifications/by-trigger', [ClaimNotificationController::class, 'byTrigger'])->name('claims.notif.byTrigger');
        Route::get('claims/notifications/recent-failures', [ClaimNotificationController::class, 'recentFailures'])->name('claims.notif.recentFailures');
        Route::get('claims/notifications/daily-volume', [ClaimNotificationController::class, 'dailyVolume'])->name('claims.notif.dailyVolume');
        Route::get('claims-v2/{id}/notifications', [ClaimNotificationController::class, 'forClaim'])->whereNumber('id')->name('claims.notif.forClaim');

        Route::get('help-desk-tickets/{id}/comments', [HelpDeskController::class, 'comments'])->whereNumber('id')->name('helpDesk.comments');
        Route::post('help-desk-tickets/{id}/comments', [HelpDeskController::class, 'addComment'])->whereNumber('id')->name('helpDesk.addComment');
        Route::post('help-desk-tickets/{id}/assign', [HelpDeskController::class, 'assign'])->whereNumber('id')->name('helpDesk.assign');
        Route::post('help-desk-tickets/{id}/status', [HelpDeskController::class, 'updateStatus'])->whereNumber('id')->name('helpDesk.status');
        Route::post('help-desk-tickets/{id}/close', [HelpDeskController::class, 'close'])->whereNumber('id')->name('helpDesk.close');
        Route::post('help-desk-tickets/{id}/reopen', [HelpDeskController::class, 'reopen'])->whereNumber('id')->name('helpDesk.reopen');
        Route::post('help-desk-tickets/{id}/clone', [HelpDeskController::class, 'clone'])->whereNumber('id')->name('helpDesk.clone');
        Route::get('help-desk-tickets/{id}/attachments/{slot}', [HelpDeskController::class, 'downloadAttachment'])->whereNumber('id')->whereNumber('slot')->name('helpDesk.attachment');
        // Edit an existing ticket + manage its attachments.
        Route::post('help-desk-tickets/{id}/update', [HelpDeskController::class, 'update'])->whereNumber('id')->name('helpDesk.update');
        Route::post('help-desk-tickets/{id}/attachments', [HelpDeskController::class, 'addAttachment'])->whereNumber('id')->name('helpDesk.addAttachment');
        Route::delete('help-desk-tickets/{id}/attachments/{slot}', [HelpDeskController::class, 'deleteAttachment'])->whereNumber('id')->whereNumber('slot')->name('helpDesk.deleteAttachment');

        // Batch Processing
        Route::get('batch-report', [BatchProcessingController::class, 'report'])->name('batchProcessing.report');

        // Customer KYC
        Route::get('kyc', [CustomerKycController::class, 'index'])->name('kyc.index');
        // {kycId} = customer_kyc.id (V8 parity). See CustomerKycController::detail.
        Route::get('kyc/{kycId}/detail', [CustomerKycController::class, 'detail'])->name('kyc.detail');
        // KYC decisions (overall approve/reject + per-document verdicts) are
        // restricted to holders of `customer-kyc-approve` — the `KYC Approver`
        // role (three named people) plus Super Admin, seeded 2026-09-10 (migration
        // 2026_09_10_000000_seed_customer_kyc_approve_permission). Before this
        // gate any authenticated account could approve KYC by URL. Viewing the
        // queue/detail stays on `customer-kyc-list` (sidebar) — read routes are
        // deliberately NOT gated here. Pinned by KycApprovalPermissionTest.
        Route::post('kyc/{kycId}/status', [CustomerKycController::class, 'updateStatus'])->name('kyc.updateStatus')->middleware('permission:customer-kyc-approve');
        Route::post('kyc/{kycId}/verify-document', [CustomerKycController::class, 'verifyDocument'])->name('kyc.verifyDocument')->middleware('permission:customer-kyc-approve');
        // Synchronous AML/sanctions screening for the KYC review page.
        // {customerId} (NOT customer_kyc.id) — AML cases are per-customer.
        Route::post('kyc/{customerId}/run-opensanctions', [CustomerKycController::class, 'runSanctionsCheck'])->name('kyc.runSanctions')->where('customerId', '[0-9]+');
        Route::get('kyc/employer-group', [CustomerKycController::class, 'employerGroup'])->name('kyc.employerGroup');
        Route::get('kyc/duplicates', [CustomerKycController::class, 'duplicates'])->name('kyc.duplicates');
        Route::get('kyc/deduplication', [CustomerKycController::class, 'deduplication'])->name('kyc.deduplication');
        // Customers flagged by AML screening (kyc_cases.sanctions_max > 0).
        // JSON port of the Blade admin.sanctioned-customers page.
        Route::get('kyc/sanctioned', [CustomerKycController::class, 'sanctioned'])->name('kyc.sanctioned');

        // AD Group KYC campaign management — JSON port of the V8 Blade admin
        // (admin.ad-group-kyc.*), unreachable in V2 behind BlockV1AdminPanel.
        // Static ad-group segment sits before the kyc/{kycId} wildcards above.
        Route::get('kyc/ad-group/dashboard', [AdGroupKycController::class, 'dashboard'])->name('adGroupKyc.dashboard');
        Route::get('kyc/ad-group/campaigns', [AdGroupKycController::class, 'campaigns'])->name('adGroupKyc.campaigns.index');
        Route::get('kyc/ad-group/campaigns/{id}', [AdGroupKycController::class, 'showCampaign'])->whereNumber('id')->name('adGroupKyc.campaigns.show');
        Route::get('kyc/ad-group/campaigns/{id}/links', [AdGroupKycController::class, 'campaignLinks'])->whereNumber('id')->name('adGroupKyc.campaigns.links');
        Route::get('kyc/ad-group/campaigns/{id}/export', [AdGroupKycController::class, 'exportCampaign'])->whereNumber('id')->name('adGroupKyc.campaigns.export');
        Route::get('kyc/ad-group/links/{id}', [AdGroupKycController::class, 'showLink'])->whereNumber('id')->name('adGroupKyc.links.show');

        // KYC Access Audit Report (port of graphiteBWV8 admin/kycAccessReport).
        // Static paths — registered before any kyc/{id} wildcard. Gated by the
        // dedicated kyc-access-report permission (see the seed migration).
        Route::get('kyc/access-report', [KycAccessReportController::class, 'index'])
            ->name('kyc.accessReport')
            ->middleware('permission:kyc-access-report');
        Route::get('kyc/access-report/export', [KycAccessReportController::class, 'export'])
            ->name('kyc.accessReport.export')
            ->middleware('permission:kyc-access-report');

        // DOM/COM-tier Customer KYC (products 7, 8, 16, 17, 18, 19, 20, 22).
        // Mirrors the MIS KYC endpoints above but reads/writes both
        // customer_kyc and customer_kyc_dom_com per V8's combined flow.
        Route::get('dom-com-kyc', [\AlphaDirect\Http\Controllers\Api\V1\DomComKycController::class, 'index'])->name('domComKyc.index');
        Route::get('dom-com-kyc/{kycId}/detail', [\AlphaDirect\Http\Controllers\Api\V1\DomComKycController::class, 'detail'])->name('domComKyc.detail');
        // Same approver gate as the MIS decision routes above.
        Route::post('dom-com-kyc/{kycId}/status', [\AlphaDirect\Http\Controllers\Api\V1\DomComKycController::class, 'updateStatus'])->name('domComKyc.updateStatus')->middleware('permission:customer-kyc-approve');
        Route::post('dom-com-kyc/{kycId}/verify-document', [\AlphaDirect\Http\Controllers\Api\V1\DomComKycController::class, 'verifyDocument'])->name('domComKyc.verifyDocument')->middleware('permission:customer-kyc-approve');

        // KYC Fields (admin config)
        Route::get('kyc-fields',           [KycFieldsApiController::class, 'index'])->name('kycFields.index');
        Route::get('kyc-fields/columns',   [KycFieldsApiController::class, 'columns'])->name('kycFields.columns');
        Route::post('kyc-fields',          [KycFieldsApiController::class, 'store'])->name('kycFields.store');
        Route::put('kyc-fields/{id}',      [KycFieldsApiController::class, 'update'])->name('kycFields.update');
        Route::delete('kyc-fields/{id}',   [KycFieldsApiController::class, 'destroy'])->name('kycFields.destroy');

        // KYC Compliance Rules (admin config)
        Route::get('kyc-compliance',              [KycComplianceApiController::class, 'index'])->name('kycCompliance.index');
        Route::get('kyc-compliance/products',     [KycComplianceApiController::class, 'products'])->name('kycCompliance.products');
        Route::get('kyc-compliance/mati',         [KycComplianceApiController::class, 'getMatiStatus'])->name('kycCompliance.getMati');
        Route::post('kyc-compliance/mati',        [KycComplianceApiController::class, 'setMatiStatus'])->name('kycCompliance.setMati');
        Route::post('kyc-compliance',             [KycComplianceApiController::class, 'store'])->name('kycCompliance.store');
        Route::put('kyc-compliance/{id}',         [KycComplianceApiController::class, 'update'])->name('kycCompliance.update');
        Route::delete('kyc-compliance/{id}',      [KycComplianceApiController::class, 'destroy'])->name('kycCompliance.destroy');

        // Communications (templates, logs, SMS)
        Route::get('communications/templates',           [CommunicationController::class, 'templates']);
        Route::post('communications/templates',          [CommunicationController::class, 'storeTemplate']);
        Route::put('communications/templates/{id}',      [CommunicationController::class, 'updateTemplate']);
        Route::delete('communications/templates/{id}',   [CommunicationController::class, 'destroyTemplate']);
        Route::get('communications/logs',                [CommunicationController::class, 'logs']);
        Route::get('communications/sms-templates',       [CommunicationController::class, 'smsTemplates']);
        Route::post('communications/sms-templates',      [CommunicationController::class, 'storeSmsTemplate']);
        Route::put('communications/sms-templates/{id}',  [CommunicationController::class, 'updateSmsTemplate']);
        Route::delete('communications/sms-templates/{id}',[CommunicationController::class, 'destroySmsTemplate']);
        Route::get('communications/sms-email-logs',      [CommunicationController::class, 'smsEmailLogs']);

        // Premium Register (earned/unearned)
        Route::get('premium-register/summary',       [PremiumRegisterController::class, 'summary']);
        Route::get('premium-register/detail',        [PremiumRegisterController::class, 'detail']);
        Route::get('premium-register/posting-dates', [PremiumRegisterController::class, 'postingDates']);

        // Renewal Dashboard
        Route::get('renewals/dashboard',        [RenewalDashboardController::class, 'dashboard']);
        Route::get('renewals/pipeline',         [RenewalDashboardController::class, 'pipeline']);
        Route::get('renewals/pipeline/export',         [RenewalDashboardController::class, 'exportPipeline'])->name('renewals.pipeline.export');
        // MIS auto-debit actions (legacy cron parity)
        Route::post('renewals/{policyId}/send-payment-url', [RenewalDashboardController::class, 'sendPaymentUrl'])->name('renewals.sendPaymentUrl');
        Route::post('renewals/{policyId}/auto-renew',       [RenewalDashboardController::class, 'autoRenew'])->name('renewals.autoRenew');
        Route::post('renewals/{policyId}/deactivate',       [RenewalDashboardController::class, 'deactivate'])->name('renewals.deactivate');

        // MIS (product_id 3) renew flow — JSON port of the legacy Blade
        // "Policy Renewal" page (renewPolicy.blade.php) so the React app can
        // drive Motor Comprehensive renewals end to end.
        Route::get('renewals/{policyId}/mis-renew-flow', [RenewalController::class, 'misRenewFlow'])->whereNumber('policyId')->name('renewals.misRenewFlow');
        Route::post('renewals/{policyId}/move-to-renew', [RenewalController::class, 'moveToRenew'])->whereNumber('policyId')->name('renewals.moveToRenew');
        Route::post('renewals/{policyId}/rerate',        [RenewalController::class, 'rerate'])->whereNumber('policyId')->name('renewals.rerate');
        Route::post('renewals/{policyId}/generate-link', [RenewalController::class, 'generateLink'])->whereNumber('policyId')->name('renewals.generateLink');
        Route::post('renewals/{policyId}/pay-cash',      [RenewalController::class, 'payCash'])->whereNumber('policyId')->name('renewals.payCash');
        Route::post('renewals/{policyId}/pay-realpay',   [RenewalController::class, 'payRealpay'])->whereNumber('policyId')->name('renewals.payRealpay');
        Route::post('renewals/{policyId}/renew-no-payment', [RenewalController::class, 'payNone'])->whereNumber('policyId')->name('renewals.payNone');

        // Agents & Agencies
        Route::get('agents',            [AgentController::class, 'agents']);
        Route::post('agents',           [AgentController::class, 'store']);
        Route::get('agents/{id}',       [AgentController::class, 'agentDetail']);
        Route::put('agents/{id}',       [AgentController::class, 'update']);
        Route::get('agent-logins',      [AgentController::class, 'logins']);
        Route::get('agencies',          [AgentController::class, 'agencies']);
        Route::post('agencies',         [AgentController::class, 'storeAgency']);
        Route::put('agencies/{id}',     [AgentController::class, 'updateAgency']);

        // Partner companies (couriers / retailers) + their start-portal logins.
        // Staff-management equivalent for the embedded B2B2C products.
        Route::middleware('permission:partner-company-list')->group(function () {
            Route::get('partner-companies',       [\AlphaDirect\Http\Controllers\Api\V1\PartnerCompanyAdminController::class, 'index']);
            Route::get('partner-companies/{id}',  [\AlphaDirect\Http\Controllers\Api\V1\PartnerCompanyAdminController::class, 'show'])->whereNumber('id');
        });
        Route::middleware('permission:partner-company-edit')->group(function () {
            Route::post('partner-companies',                                          [\AlphaDirect\Http\Controllers\Api\V1\PartnerCompanyAdminController::class, 'store']);
            Route::put('partner-companies/{id}',                                      [\AlphaDirect\Http\Controllers\Api\V1\PartnerCompanyAdminController::class, 'update'])->whereNumber('id');
            Route::post('partner-companies/{id}/users',                               [\AlphaDirect\Http\Controllers\Api\V1\PartnerCompanyAdminController::class, 'storeUser'])->whereNumber('id');
            Route::put('partner-companies/{id}/users/{userId}',                       [\AlphaDirect\Http\Controllers\Api\V1\PartnerCompanyAdminController::class, 'updateUser'])->whereNumber('id')->whereNumber('userId');
            Route::post('partner-companies/{id}/users/{userId}/send-credentials',     [\AlphaDirect\Http\Controllers\Api\V1\PartnerCompanyAdminController::class, 'sendCredentials'])->whereNumber('id')->whereNumber('userId');
            Route::post('partner-companies/{id}/users/{userId}/revoke-sessions',      [\AlphaDirect\Http\Controllers\Api\V1\PartnerCompanyAdminController::class, 'revokeSessions'])->whereNumber('id')->whereNumber('userId');
        });

        // Users
        Route::get('users',                     [\AlphaDirect\Http\Controllers\Api\V1\UserController::class, 'index']);
        // store()/update() attach a role_id, so they are gated like the role
        // attach routes below (2026-09-10: closes the self-assign path into
        // the exclusive `KYC Approver` role). Only the Users admin page calls them.
        Route::post('users',                    [\AlphaDirect\Http\Controllers\Api\V1\UserController::class, 'store'])->middleware('role_or_permission:Super Admin|Admin|Manager|user-edit');
        Route::get('users/{id}',                [\AlphaDirect\Http\Controllers\Api\V1\UserController::class, 'show']);
        Route::get('users/{id}/login-activity', [\AlphaDirect\Http\Controllers\Api\V1\UserController::class, 'loginActivity'])->whereNumber('id');
        Route::put('users/{id}',                [\AlphaDirect\Http\Controllers\Api\V1\UserController::class, 'update'])->middleware('role_or_permission:Super Admin|Admin|Manager|user-edit');
        Route::post('users/{id}/toggle-status', [\AlphaDirect\Http\Controllers\Api\V1\UserController::class, 'toggleStatus']);
        Route::post('users/{id}/reset-password',[\AlphaDirect\Http\Controllers\Api\V1\UserController::class, 'resetPassword']);
        Route::get('users/pins',                [\AlphaDirect\Http\Controllers\Api\V1\UserController::class, 'pinList']);
        Route::put('users/{id}/pin',            [\AlphaDirect\Http\Controllers\Api\V1\UserController::class, 'updatePin']);
        Route::post('users/generate-pins',      [\AlphaDirect\Http\Controllers\Api\V1\UserController::class, 'generatePins']);

        // Roles & Permissions
        Route::get('roles',                    [RolePermissionController::class, 'roles']);
        Route::get('roles/{id}',               [RolePermissionController::class, 'roleDetail']);
        Route::get('permissions',              [RolePermissionController::class, 'permissions']);
        Route::get('roles/{id}/under-roles',   [RolePermissionController::class, 'getUnderRoles']);
        Route::get('users/{id}/roles',             [RolePermissionController::class, 'userRoles']);
        // Mutations that change WHO holds a role or WHAT a role grants were
        // open to every authenticated account. That let any staff token
        // attach itself to a privileged role (e.g. `KYC Approver`, which is
        // meant to be an exclusive three-person list as of 2026-09-10) or
        // add a permission to its own role. Gated to the admin roles that
        // already administer access in the legacy panel, plus the matching
        // legacy permissions so a delegated admin keeps working.
        // Additive role management — keeps Super Admin / Manager / Admin etc.
        // intact when devs swap themselves into a Grade role for RBAC testing.
        Route::middleware('role_or_permission:Super Admin|Admin|Manager|role-edit')->group(function () {
            Route::post('roles',                   [RolePermissionController::class, 'storeRole']);
            Route::put('roles/{id}',               [RolePermissionController::class, 'updateRole']);
            Route::delete('roles/{id}',            [RolePermissionController::class, 'destroyRole']);
            Route::post('roles/under-roles',       [RolePermissionController::class, 'storeUnderRoles']);
        });
        Route::middleware('role_or_permission:Super Admin|Admin|Manager|user-edit')->group(function () {
            Route::post('users/{id}/assign-role',      [RolePermissionController::class, 'assignRole'])->name('users.assignRoleLegacy');
            Route::post('users/{id}/roles/{roleId}',   [RolePermissionController::class, 'assignRole'])
                ->where('roleId', '[0-9]+')->name('users.assignRole');
            Route::delete('users/{id}/roles/{roleId}', [RolePermissionController::class, 'removeRole'])
                ->where('roleId', '[0-9]+')->name('users.removeRole');
        });

        // Accounting
        Route::get('accounts',               [AccountingController::class, 'accounts']);
        Route::post('accounts',              [AccountingController::class, 'storeAccount']);
        Route::put('accounts/{id}',          [AccountingController::class, 'updateAccount']);
        Route::delete('accounts/{id}',       [AccountingController::class, 'destroyAccount']);
        Route::get('accounting-rules',       [AccountingController::class, 'rules']);
        Route::post('accounting-rules',      [AccountingController::class, 'storeRule']);
        Route::put('accounting-rules/{id}',  [AccountingController::class, 'updateRule']);
        Route::delete('accounting-rules/{id}',[AccountingController::class, 'destroyRule']);
        Route::get('sub-ledger',             [AccountingController::class, 'subLedger']);
        Route::get('trial-balance',          [AccountingController::class, 'trialBalance']);

        // Suppliers
        Route::get('suppliers',              [SupplierController::class, 'index']);
        Route::post('suppliers',             [SupplierController::class, 'store']);
        Route::put('suppliers/{id}',         [SupplierController::class, 'update']);
        Route::delete('suppliers/{id}',      [SupplierController::class, 'destroy']);
        // Phase-3: mark a supplier as an approved panel-beater / glass supplier
        // (incentive report). Flag-gated in the controller (claims_incentive_report,
        // default OFF); RBAC: Claims Manager | Admin | Super Admin.
        Route::patch('suppliers/{id}/incentive-flags', [ClaimsIncentiveReportController::class, 'markSupplier'])->name('suppliers.incentiveFlags')->middleware('role:Claims Manager|Admin|Super Admin');

        // Assessors (master data — feeds the claim Assessor tab Non-Motor dropdown)
        Route::get('assessors',              [AssessorController::class, 'index']);
        Route::post('assessors',             [AssessorController::class, 'store']);
        Route::get('assessors/{id}',         [AssessorController::class, 'show'])->whereNumber('id');
        Route::put('assessors/{id}',         [AssessorController::class, 'update']);
        Route::delete('assessors/{id}',      [AssessorController::class, 'destroy']);

        // Lawyers (master data — mirrors Assessors CRUD)
        Route::get('lawyers',                [LawyerController::class, 'index']);
        Route::post('lawyers',               [LawyerController::class, 'store']);
        Route::get('lawyers/{id}',           [LawyerController::class, 'show'])->whereNumber('id');
        Route::put('lawyers/{id}',           [LawyerController::class, 'update']);
        Route::delete('lawyers/{id}',        [LawyerController::class, 'destroy']);

        // ─── Inflation rate master (UW-owned renewal SI uplift) ─────────────
        // The percentages `php artisan policy:inflate-buildings-si --master`
        // applies. /options feeds the form's product + coverage dropdowns,
        // /impact previews one rule and /applied reads back what actually moved.
        //
        // READ = every signed-in user. Nothing here moves money, and anyone
        // handling a renewal needs to be able to see why a sum insured changed.
        Route::get('inflation-rates/options',      [InflationRateController::class, 'options']);
        Route::get('inflation-rates/applied',      [InflationRateController::class, 'applied']);
        Route::get('inflation-rates',              [InflationRateController::class, 'index']);
        Route::get('inflation-rates/{id}',         [InflationRateController::class, 'show'])->whereNumber('id');
        Route::get('inflation-rates/{id}/impact',  [InflationRateController::class, 'impact'])->whereNumber('id');

        // WRITE = one role. A row here re-prices live renewal quotes, so
        // add/edit/delete belongs to a single named owner rather than "anyone
        // in Admin or UW".
        //
        // Gated on the ROLE NAME so the whole grant is done in the /roles
        // screen — create the role "Inflation Rate Manager", assign it to the
        // one person, done. No seed migration: the roles API can attach only
        // permissions that already exist in the `permissions` table and has no
        // endpoint to create one, so a permission-based gate would have needed
        // a migration to be usable at all.
        //
        // `inflation_rate_manage` is listed too and costs nothing today
        // (Spatie's checkPermissionTo returns false for a permission that does
        // not exist) — it means a later move to a real permission needs no code
        // change. Super Admin is the break-glass: this app has no super-admin
        // gate bypass, so without it a bad rule could become uncorrectable.
        // For strictly one role, drop `Super Admin|` from the line below.
        Route::middleware('role_or_permission:Super Admin|Inflation Rate Manager|inflation_rate_manage')->group(function () {
            Route::post('inflation-rates',            [InflationRateController::class, 'store']);
            Route::put('inflation-rates/{id}',        [InflationRateController::class, 'update'])->whereNumber('id');
            Route::delete('inflation-rates/{id}',     [InflationRateController::class, 'destroy'])->whereNumber('id');
        });

        // ─── Geography master-data (States/Provinces + Cities) ──────────────
        // Powers the Province/State + City dropdowns in the Edit Risk Address
        // modal (via the /lookups/* read cache). Add & edit only — no delete:
        // risk_address + customer_profile reference these ids with no FK, so a
        // delete would orphan them. Role-gated Admin | Super Admin.
        Route::middleware('role:Admin|admin|Super Admin')->group(function () {
            Route::get('master/states',        [StateController::class, 'index']);
            Route::post('master/states',       [StateController::class, 'store']);
            Route::put('master/states/{id}',   [StateController::class, 'update'])->whereNumber('id');

            Route::get('master/cities',        [CityController::class, 'index']);
            Route::post('master/cities',       [CityController::class, 'store']);
            Route::put('master/cities/{id}',   [CityController::class, 'update'])->whereNumber('id');
        });

        // ─── Claims Master-Data (Claims-Tracker migration) ──────────────────
        // First-class claims master-data screen. Read-through GETs over
        // Graphite's own tables + a claims_config store for the tracker-only
        // lists. Role-gated Admin | Super Admin (per the migration brief).
        // FNOL / new-claim dropdown lookups (handlers, brokers, reinsurers, glass
        // suppliers) must be reachable by the same roles that can open the
        // new-claim form (CLAIMS_FNOL_ROLES: Claims Team / Claims Manager / Admin
        // / Super Admin). When they were Admin-only, Claims Team/Manager users
        // passed the page gate but got 403 here, so every dropdown rendered empty
        // (the FE swallows the error). These are read-only lookups — safe to widen.
        Route::middleware('role:Admin|admin|Super Admin|Claims Manager|Claims Team')->group(function () {
            Route::get('claims-masterdata/handlers',       [ClaimsMasterDataController::class, 'handlers']);
            Route::get('claims-masterdata/brokers',        [ClaimsMasterDataController::class, 'brokers']);
            Route::get('claims-masterdata/reinsurers',     [ClaimsMasterDataController::class, 'reinsurers']);
            Route::get('claims-masterdata/suppliers',      [ClaimsMasterDataController::class, 'suppliers']);
        });

        Route::middleware('role:Admin|admin|Super Admin')->group(function () {
            // Read-through tiles for the admin master-data screen only.
            Route::get('claims-masterdata/summary',        [ClaimsMasterDataController::class, 'summary']);
            Route::get('claims-masterdata/system-users',   [ClaimsMasterDataController::class, 'systemUsers']);
            Route::get('claims-masterdata/claim-type-map', [ClaimsMasterDataController::class, 'claimTypeMap']);
            // Guarded flag write on an existing supplier (not behind the
            // incentive-report feature flag — master-data upkeep).
            Route::patch('claims-masterdata/suppliers/{id}/approval', [ClaimsMasterDataController::class, 'toggleSupplierApproval'])->whereNumber('id');

            // Tracker-only config store (claims_config) CRUD.
            Route::get('claims-config',                    [ClaimsConfigController::class, 'categories']);
            Route::get('claims-config/{category}',         [ClaimsConfigController::class, 'index']);
            Route::post('claims-config/{category}',        [ClaimsConfigController::class, 'store']);
            Route::put('claims-config/{category}/{id}',    [ClaimsConfigController::class, 'update'])->whereNumber('id');
            Route::delete('claims-config/{category}/{id}', [ClaimsConfigController::class, 'destroy'])->whereNumber('id');
        });

        // ─── Claims Admin: scheduled KPI reports + bulk import (Phase 2) ─────
        // Role-gated Admin | Super Admin | Claims Manager. Additive + flagged:
        //   • report schedules — manageable any time; the actual SEND is gated
        //     by the `claims_scheduled_reports` flag at the command layer, so
        //     nothing goes out until an admin arms the flag + enables a schedule
        //     with recipients. `preview` renders HTML without sending.
        //   • bulk import — analyze + preview (dry-run) work here; COMMIT is
        //     gated by the `claims_bulk_import` flag + explicit confirm in the
        //     controller, routes every row through the existing claim-create
        //     path, and the commit route is extra rate-limited.
        // Report schedules are admin-only — a Claims Manager must not create or
        // change what goes out to the exec distribution list.
        Route::middleware('role:Admin|admin|Super Admin')->group(function () {
            Route::get('claims-report-schedules',           [ClaimReportScheduleController::class, 'index']);
            Route::post('claims-report-schedules',          [ClaimReportScheduleController::class, 'store']);
            Route::post('claims-report-schedules/preview',  [ClaimReportScheduleController::class, 'preview']);
            Route::put('claims-report-schedules/{id}',      [ClaimReportScheduleController::class, 'update'])->whereNumber('id');
            Route::delete('claims-report-schedules/{id}',   [ClaimReportScheduleController::class, 'destroy'])->whereNumber('id');
            Route::post('claims-report-schedules/{id}/run', [ClaimReportScheduleController::class, 'runNow'])->whereNumber('id');
        });

        Route::middleware('role:Admin|admin|Super Admin|Claims Manager')->group(function () {
            Route::post('claims-import/analyze', [ClaimBulkImportController::class, 'analyze']);
            Route::post('claims-import/preview', [ClaimBulkImportController::class, 'preview']);
            Route::post('claims-import/commit',  [ClaimBulkImportController::class, 'commit'])->middleware('throttle:6,1');
        });

        // Repair Centers
        Route::get('repair-centers',         [RepairCenterController::class, 'index']);
        Route::post('repair-centers',        [RepairCenterController::class, 'store']);
        Route::put('repair-centers/{id}',    [RepairCenterController::class, 'update']);
        Route::delete('repair-centers/{id}', [RepairCenterController::class, 'destroy']);

        // Activation Codes
        Route::get('activation-codes',          [ActivationCodeController::class, 'index']);
        Route::post('activation-codes/check',   [ActivationCodeController::class, 'check']);
        Route::get('activation-codes/activated', [ActivationCodeController::class, 'activated']);

        // RealPay Contracts (admin-wide listing — kept for compatibility)
        Route::get('realpay-contracts',      [RealPayContractController::class, 'index']);
        Route::get('realpay-contracts/{id}',  [RealPayContractController::class, 'show']);

        // RealPay bank/branch lookups for the Policy Details > Add Realpay Contract tab
        Route::get('realpay/banks',                       [PolicyRealpayController::class, 'banks'])->name('realpay.banks');
        Route::get('realpay/banks/{bankId}/branches',     [PolicyRealpayController::class, 'branches'])->name('realpay.branches');

        // Per-policy RealPay reads (Policy Details > Realpay Contract Lists + Transactions tabs)
        Route::get('policies/{policyId}/realpay/contracts',     [PolicyRealpayController::class, 'listContracts'])->name('policies.realpay.contracts');
        Route::get('policies/{policyId}/realpay/installments',  [PolicyRealpayController::class, 'listInstallments'])->name('policies.realpay.installments');
        // RealPay Transactions tab actions (V8 parity): add new installment
        // (live API), and queue single / bulk installment updates via realpay_logs.
        Route::post('policies/{policyId}/realpay/installments',            [PolicyRealpayController::class, 'addInstallment'])->name('policies.realpay.addInstallment');
        Route::post('policies/{policyId}/realpay/installments/update',     [PolicyRealpayController::class, 'updateInstallment'])->name('policies.realpay.updateInstallment');
        Route::post('policies/{policyId}/realpay/installments/update-all', [PolicyRealpayController::class, 'updateAllInstallments'])->name('policies.realpay.updateAllInstallments');
        // Check the LIVE RealPay portal for an existing contract on this policy
        // and sync it (contract + installments) into our DB, then return it.
        // Makes a slow external RealPay round-trip — POST so it's not cached.
        Route::post('policies/{policyId}/realpay/sync-from-portal', [PolicyRealpayController::class, 'syncFromPortal'])->name('policies.realpay.syncFromPortal');

        // Payment-method conversion log (Policy Details > Payment Conversions tab).
        // GRA-0182 parity with the V8 "Payment Update Contract" tab — who switched
        // this policy's payment method (e.g. DPO → RealPay), old/new method, whether
        // the old contract was cancelled, and when. Reads update_contract.
        Route::get('policies/{policyId}/payment-conversions', [PolicyRealpayController::class, 'paymentConversions'])->name('policies.paymentConversions');

        // Rerate Premium reads (Policy Details > Rerate Premium tab — MIS Motor Comprehensive)
        Route::get('policies/{id}/rerate-premium',         [PolicyReratePremiumController::class, 'loadRerateData'])->name('policies.rerate.load');
        Route::get('policies/{id}/rerate-premium/history', [PolicyReratePremiumController::class, 'history'])->name('policies.rerate.history');

        // Per-policy transaction logs (Policy Details > Transaction Logs tab)
        Route::get('policies/{policyId}/transaction-logs',                   [PaymentController::class, 'policyTransactionLogs'])->name('policies.transactionLogs');
        Route::get('policies/{policyId}/transaction-logs/{txnId}/proof',     [PaymentController::class, 'transactionProofUrl'])->name('policies.transactionLogs.proof');

        // Payment Vendors
        Route::get('payment-vendors',         [AdminConfigController::class, 'paymentVendors']);
        Route::post('payment-vendors',        [AdminConfigController::class, 'storePaymentVendor']);
        Route::put('payment-vendors/{id}',    [AdminConfigController::class, 'updatePaymentVendor']);

        // Regions & Departments
        Route::get('regions',                 [AdminConfigController::class, 'regions']);
        Route::post('regions',                [AdminConfigController::class, 'storeRegion']);
        Route::put('regions/{id}',            [AdminConfigController::class, 'updateRegion']);
        Route::get('departments',             [AdminConfigController::class, 'departments']);
        Route::post('departments',            [AdminConfigController::class, 'storeDepartment']);
        Route::put('departments/{id}',        [AdminConfigController::class, 'updateDepartment']);

        // Complaints Register
        Route::get('complaints',                       [AdminConfigController::class, 'complaints']);
        Route::get('complaints/lookups',               [AdminConfigController::class, 'complaintLookups']);
        Route::get('complaints/prefill',               [AdminConfigController::class, 'complaintPrefill']);
        Route::get('complaints/export',                [AdminConfigController::class, 'exportComplaints']);
        Route::post('complaints',                      [AdminConfigController::class, 'storeComplaint']);
        Route::put('complaints/{id}',                  [AdminConfigController::class, 'updateComplaint']);
        Route::get('complaints/{id}/documents',        [AdminConfigController::class, 'complaintDocuments']);
        Route::post('complaints/{id}/documents',       [AdminConfigController::class, 'uploadComplaintDocument']);
        Route::delete('complaints/{id}/documents/{docId}', [AdminConfigController::class, 'deleteComplaintDocument']);

        // Customer Rewards & Tiers
        Route::get('reward-tiers',            [AdminConfigController::class, 'rewardTiers']);
        Route::post('reward-tiers',           [AdminConfigController::class, 'storeRewardTier']);
        Route::put('reward-tiers/{id}',       [AdminConfigController::class, 'updateRewardTier']);
        Route::get('benefits',                [AdminConfigController::class, 'benefits']);
        Route::post('benefits',               [AdminConfigController::class, 'storeBenefit']);
        Route::get('customer-rewards',        [AdminConfigController::class, 'customerRewards']);

        // Product Configuration
        Route::get('products/{id}/config',         [ProductConfigController::class, 'detail']);
        Route::put('products/{id}/config',         [ProductConfigController::class, 'updateProduct']);
        Route::post('products/{id}/plans',         [ProductConfigController::class, 'storePlan']);
        Route::put('product-plans/{id}',           [ProductConfigController::class, 'updatePlan']);
        Route::delete('product-plans/{id}',        [ProductConfigController::class, 'deletePlan']);

        // Underwriting Workbench
        Route::get('underwriting/queue',           [UnderwritingController::class, 'queue']);
        Route::get('underwriting/{actionId}/preview', [UnderwritingController::class, 'preview']);
        Route::post('underwriting/{actionId}/decide', [UnderwritingController::class, 'decide']);

        // Smart Underwriting Upload — broker schedule -> smart-uw-engine -> review screen.
        // RBAC-gated: only users with the 'underwriting_smart_upload' permission
        // (Spatie) can reach the endpoint — not every authenticated user.
        // 'smart-upload' is a distinct literal segment, safe alongside the queue routes.
        Route::middleware('permission:underwriting_smart_upload')->group(function () {
            Route::post('underwriting/smart-upload',     [SmartUploadController::class, 'upload']);
            // Target picker: policy number -> matching policies + their actions.
            // Declared BEFORE the {id} route; the whereNumber('id') constraint
            // already stops 'policy-lookup' being swallowed, but keeping the
            // literal first makes the intent obvious.
            Route::get('underwriting/smart-upload/policy-lookup', [SmartUploadController::class, 'policyLookup']);
            Route::get('underwriting/smart-upload/{id}', [SmartUploadController::class, 'status'])->whereNumber('id');
            // The underwriter's own classification of a segment — which bucket
            // an unplaced line belongs in, and the "please check" lines they
            // have confirmed. Writes the extraction row only; the schedule
            // still reaches the policy through the wizard's endpoints.
            // Literal 'extraction' segment, so it cannot be read as the
            // numeric {id} above.
            Route::patch('underwriting/smart-upload/extraction/{id}',
                [SmartUploadController::class, 'saveExtraction'])->whereNumber('id');

            // AI provider configuration for the extractor. Gated HARDER than
            // the upload itself: reading a schedule is day-to-day underwriting
            // work, writing the provider key is administration. Same role list
            // the legacy Credentials Vault page used (routes/web.php), which is
            // the screen this replaces — that page is unreachable on a deployed
            // env because BlockV1AdminPanel 404s the whole V1 panel.
            // GET reveals no key, only whether one is set.
            Route::middleware('role:Super Admin|Manager|Admin|developer')->group(function () {
                Route::get('underwriting/smart-upload/ai-config',  [SmartUploadController::class, 'aiConfig']);
                Route::post('underwriting/smart-upload/ai-config', [SmartUploadController::class, 'saveAiConfig']);
            });
        });

        // SMS & Email Templates
        Route::get('sms-templates',                [SmsEmailTemplateController::class, 'smsTemplates']);
        Route::post('sms-templates',               [SmsEmailTemplateController::class, 'storeSmsTemplate']);
        Route::put('sms-templates/{id}',           [SmsEmailTemplateController::class, 'updateSmsTemplate']);
        Route::delete('sms-templates/{id}',        [SmsEmailTemplateController::class, 'destroySmsTemplate']);
        Route::get('email-templates',              [SmsEmailTemplateController::class, 'emailTemplates']);
        Route::post('email-templates',             [SmsEmailTemplateController::class, 'storeEmailTemplate']);
        Route::put('email-templates/{id}',         [SmsEmailTemplateController::class, 'updateEmailTemplate']);
        Route::delete('email-templates/{id}',      [SmsEmailTemplateController::class, 'destroyEmailTemplate']);
        Route::get('customers/{id}/comms-timeline', [SmsEmailTemplateController::class, 'customerTimeline']);

        // WhatsApp templates — Meta Business Management API CRUD.
        // Source-of-truth is Meta (PENDING -> APPROVED/REJECTED lifecycle),
        // so we don't mirror to local DB; UI re-fetches each time.
        Route::get('whatsapp-templates',           [WhatsAppTemplateController::class, 'index']);
        Route::post('whatsapp-templates',          [WhatsAppTemplateController::class, 'store']);
        Route::delete('whatsapp-templates/{name}', [WhatsAppTemplateController::class, 'destroy']);
        Route::post('whatsapp-templates/test',     [WhatsAppTemplateController::class, 'test']);

        // Preinspection
        Route::get('preinspection/vehicles', [PreinspectionController::class, 'vehicles'])->name('preinspection.vehicles');
        Route::get('preinspection/vehicles/{id}', [PreinspectionController::class, 'vehicleShow'])->whereNumber('id')->name('preinspection.vehicles.show');
        Route::post('preinspection/vehicles/{id}/approve', [PreinspectionController::class, 'vehicleUpdate'])->whereNumber('id')->name('preinspection.vehicles.approve');
        Route::get('preinspection/devices', [PreinspectionController::class, 'devices'])->name('preinspection.devices');
        Route::get('preinspection/devices/{id}', [PreinspectionController::class, 'deviceShow'])->whereNumber('id')->name('preinspection.devices.show');
        Route::post('preinspection/devices/{id}/approve', [PreinspectionController::class, 'deviceUpdate'])->whereNumber('id')->name('preinspection.devices.approve');

        // Reconciliation (general operational anomaly engine — pre-existing)
        Route::get('reconciliation/summary', [ReconciliationController::class, 'summary'])->name('reconciliation.summary');
        Route::get('reconciliation/runs', [ReconciliationController::class, 'runs'])->name('reconciliation.runs');
        Route::get('reconciliation/anomalies', [ReconciliationController::class, 'anomalies'])->name('reconciliation.anomalies');
        Route::get('reconciliation/export-status/{jobId}', [ReconciliationController::class, 'exportStatus'])->name('reconciliation.exportStatus');
        Route::get('reconciliation/export-download/{jobId}', [ReconciliationController::class, 'exportDownload'])->name('reconciliation.exportDownload');

        // Anomaly findings (WhatsApp anomaly engine: duplicate policies, premium mismatch, etc.)
        // Persisted findings live in anomaly_findings; admin UI consumes these endpoints.
        Route::get('anomalies/summary',                       [AnomalyFindingsController::class, 'summary'])->name('anomalies.summary');
        Route::get('anomalies/findings',                      [AnomalyFindingsController::class, 'findings'])->name('anomalies.findings');
        Route::post('anomalies/findings/{id}/review',         [AnomalyFindingsController::class, 'review'])->name('anomalies.review');
        Route::post('anomalies/findings/{id}/resolve',        [AnomalyFindingsController::class, 'resolve'])->name('anomalies.resolve');
        Route::post('anomalies/findings/{id}/dismiss',        [AnomalyFindingsController::class, 'dismiss'])->name('anomalies.dismiss');

        // Settlement reconciliation (DPO / RealPay daily settlement file vs payment_transactions)
        // Operator uploads provider's settlement CSV here; matcher runs on upload;
        // unmatched/drift transactions surface in the UI for accept/dispute workflow.
        Route::post('finance/settlement-reconciliation/upload',
            [SettlementReconciliationController::class, 'upload'])->name('settlement.upload');
        Route::get('finance/settlement-reconciliation/runs',
            [SettlementReconciliationController::class, 'listRuns'])->name('settlement.runs');
        Route::get('finance/settlement-reconciliation/runs/{id}',
            [SettlementReconciliationController::class, 'showRun'])->name('settlement.run');
        Route::get('finance/settlement-reconciliation/runs/{id}/transactions',
            [SettlementReconciliationController::class, 'listTransactions'])->name('settlement.transactions');
        Route::get('finance/settlement-reconciliation/runs/{id}/download-original',
            [SettlementReconciliationController::class, 'downloadOriginal'])->name('settlement.download');
        Route::post('finance/settlement-reconciliation/runs/{id}/close',
            [SettlementReconciliationController::class, 'closeRun'])->name('settlement.close');
        Route::post('finance/settlement-reconciliation/runs/{id}/rematch',
            [SettlementReconciliationController::class, 'rematch'])->name('settlement.rematch');
        Route::post('finance/settlement-reconciliation/transactions/{id}/accept',
            [SettlementReconciliationController::class, 'acceptFinding'])->name('settlement.accept');
        Route::post('finance/settlement-reconciliation/transactions/{id}/dispute',
            [SettlementReconciliationController::class, 'disputeFinding'])->name('settlement.dispute');

        // ── Reconciliation Exceptions (routine-written; Finance reviews + comments) ──
        // Frontend-gated by the 'view_exceptions' / 'comment_exceptions' permissions
        // (same convention as settlement reconciliation — no route-level can: gate).
        Route::get('finance/exceptions/runs',    [ExceptionsController::class, 'runs'])->name('exceptions.runs');
        Route::get('finance/exceptions/summary', [ExceptionsController::class, 'summary'])->name('exceptions.summary');
        Route::post('finance/exceptions/generate', [ExceptionsController::class, 'generate'])->name('exceptions.generate');
        Route::get('finance/exceptions',         [ExceptionsController::class, 'index'])->name('exceptions.index');
        Route::get('finance/exceptions/{id}',    [ExceptionsController::class, 'show'])->whereNumber('id')->name('exceptions.show');
        Route::post('finance/exceptions/{id}/comments', [ExceptionsController::class, 'addComment'])->whereNumber('id')->name('exceptions.addComment');
        Route::post('finance/exceptions/{id}/status',   [ExceptionsController::class, 'updateStatus'])->whereNumber('id')->name('exceptions.status');

        // Finance Reports (generated by Python cron engine)
        Route::get('cron-reports', [CronReportController::class, 'index'])->name('cronReports.index');
        Route::get('cron-reports/download/{filename}', [CronReportController::class, 'download'])->name('cronReports.download');

        // Cron Report Trigger
        Route::post('cron-reports/trigger/{key}', [CronReportController::class, 'trigger'])->name('cronReports.trigger');

        // Cron Configuration Management
        Route::get('cron-config', [CronConfigController::class, 'index'])->name('cronConfig.index');
        Route::get('cron-config/{key}', [CronConfigController::class, 'show'])->name('cronConfig.show');
        Route::put('cron-config/{key}', [CronConfigController::class, 'update'])->name('cronConfig.update');
        Route::get('cron-config/{key}/stakeholders', [CronConfigController::class, 'stakeholders'])->name('cronConfig.stakeholders');
        Route::post('cron-config/{key}/stakeholders', [CronConfigController::class, 'addStakeholder'])->name('cronConfig.addStakeholder');
        Route::put('cron-config/stakeholders/{id}', [CronConfigController::class, 'updateStakeholder'])->name('cronConfig.updateStakeholder');
        Route::delete('cron-config/stakeholders/{id}', [CronConfigController::class, 'deleteStakeholder'])->name('cronConfig.deleteStakeholder');

        // Integration on/off provision (read). Toggle (PUT) is in the write
        // group below and is gated to authorised roles in the controller.
        Route::get('integrations',                       [\AlphaDirect\Http\Controllers\Api\V1\IntegrationSettingsController::class, 'index'])->name('integrations.index');
        Route::get('integrations/{integration}',         [\AlphaDirect\Http\Controllers\Api\V1\IntegrationSettingsController::class, 'show'])->name('integrations.show');
        // Recent inbound webhooks for the test console.
        Route::get('integrations/{integration}/webhooks', [\AlphaDirect\Http\Controllers\Api\V1\IntegrationSettingsController::class, 'webhooks'])->name('integrations.webhooks');

        // Alpha Transit Cover — read-only ops view over the courier-GIT
        // ingestion (atc_* tables). Corrections happen on the ATC platform
        // and re-sync via the webhook; nothing here mutates.
        Route::get('alpha-transit/summary',   [\AlphaDirect\Http\Controllers\Api\V1\AlphaTransitAdminController::class, 'summary'])->name('alphaTransit.summary');
        Route::get('alpha-transit/shipments', [\AlphaDirect\Http\Controllers\Api\V1\AlphaTransitAdminController::class, 'shipments'])->name('alphaTransit.shipments');
        Route::get('alpha-transit/payments',  [\AlphaDirect\Http\Controllers\Api\V1\AlphaTransitAdminController::class, 'payments'])->name('alphaTransit.payments');
        Route::get('alpha-transit/claims',    [\AlphaDirect\Http\Controllers\Api\V1\AlphaTransitAdminController::class, 'claims'])->name('alphaTransit.claims');
        Route::get('alpha-transit/events',    [\AlphaDirect\Http\Controllers\Api\V1\AlphaTransitAdminController::class, 'events'])->name('alphaTransit.events');
        // Per-policy shipment record — the policy view's Product Details tab.
        Route::get('alpha-transit/policy/{policyId}', [\AlphaDirect\Http\Controllers\Api\V1\AlphaTransitAdminController::class, 'policyShipment'])->whereNumber('policyId')->name('alphaTransit.policyShipment');

        // Laravel Kernel Cron Management (cron_kernel + cron_mail + cron_status)
        Route::get('cron-kernel/status-summary', [CronConfigController::class, 'kernelStatusSummary'])->name('cronKernel.summary');
        Route::get('cron-kernel/daily-activity/download', [CronConfigController::class, 'dailyActivityDownload'])->name('cronKernel.dailyDownload');
        Route::get('cron-kernel/daily-activity', [CronConfigController::class, 'dailyActivity'])->name('cronKernel.dailyActivity');
        Route::get('cron-kernel', [CronConfigController::class, 'kernelJobs'])->name('cronKernel.index');
        Route::get('cron-kernel/{id}', [CronConfigController::class, 'showKernelJob'])->name('cronKernel.show');
        Route::put('cron-kernel/{id}', [CronConfigController::class, 'updateKernelJob'])->name('cronKernel.update');
        Route::post('cron-kernel/{id}/mail', [CronConfigController::class, 'addKernelMail'])->name('cronKernel.addMail');
        Route::delete('cron-kernel/{id}/mail', [CronConfigController::class, 'removeKernelMail'])->name('cronKernel.removeMail');
        Route::post('cron-kernel/{id}/run-now', [CronConfigController::class, 'runNow'])->name('cronKernel.runNow');

        // Cron Laravel Logs — admin-only proxy to cron container's laravel.log
        // Reads happen server-side; FE never sees the cron container directly.
        Route::get('cron-logs/names', [CronLogsController::class, 'names'])->name('cronLogs.names');
        Route::get('cron-logs/tail',  [CronLogsController::class, 'tail'])->name('cronLogs.tail');

        // System diagnostics — admin-only storage health view + S3 re-sync.
        Route::get('system/storage-status',                [\AlphaDirect\Http\Controllers\Api\V1\SystemDiagnosticsController::class, 'storageStatus'])->name('system.storageStatus');
        Route::post('system/storage/resync-static-pdfs',   [\AlphaDirect\Http\Controllers\Api\V1\SystemDiagnosticsController::class, 'resyncStaticPdfs'])->name('system.storage.resyncStaticPdfs');
        // Restart queue workers + clear stuck scheduler locks. Self-service
        // recovery for "Queued (large)" PDF jobs stalled when the minutely
        // cron's framework/schedule-* lock is wedged. Admin/Super Admin only
        // (gated inside the controller). Operates on the shared Redis, so it
        // reaches the separate cron/queue ECS services too.
        Route::post('system/restart-queue',                [\AlphaDirect\Http\Controllers\Api\V1\SystemDiagnosticsController::class, 'restartQueue'])->name('system.restartQueue');

        // Wordings Manager — org-wide canonical policy wording PDFs (see D:\ADRisk\Wordings_Policy.html).
        // View + download is open to all authed users; write actions gated inside the controller.
        Route::get('wordings/categories',                  [\AlphaDirect\Http\Controllers\Api\V1\WordingsController::class, 'categories'])->name('wordings.categories');
        Route::get('wordings',                             [\AlphaDirect\Http\Controllers\Api\V1\WordingsController::class, 'index'])->name('wordings.index');
        Route::get('wordings/{id}/download',               [\AlphaDirect\Http\Controllers\Api\V1\WordingsController::class, 'download'])->whereNumber('id')->name('wordings.download');
        Route::post('wordings',                            [\AlphaDirect\Http\Controllers\Api\V1\WordingsController::class, 'store'])->name('wordings.store');
        Route::patch('wordings/{id}/deactivate',           [\AlphaDirect\Http\Controllers\Api\V1\WordingsController::class, 'deactivate'])->whereNumber('id')->name('wordings.deactivate');
        Route::patch('wordings/{id}/reactivate',           [\AlphaDirect\Http\Controllers\Api\V1\WordingsController::class, 'reactivate'])->whereNumber('id')->name('wordings.reactivate');
        Route::delete('wordings/{id}',                     [\AlphaDirect\Http\Controllers\Api\V1\WordingsController::class, 'destroy'])->whereNumber('id')->name('wordings.destroy');

        // GRA-0155 — Infobip SMS-log exports (self-service download).
        // List + presigned download of the daily SMS-log CSVs written to S3 by
        // the sms:export-infobip-logs cron. Gated by the `sms-logs-download`
        // permission (Spatie route middleware — same pattern as the KYC Access
        // Report; per-user delegable via Roles & Permissions). The on-demand
        // range-export (POST) lives in the api_write group below.
        Route::get('system/sms-exports',                   [\AlphaDirect\Http\Controllers\Api\V1\SmsLogExportController::class, 'index'])->name('system.smsExports.index')->middleware('permission:sms-logs-download');
        Route::get('system/sms-exports/download',          [\AlphaDirect\Http\Controllers\Api\V1\SmsLogExportController::class, 'download'])->name('system.smsExports.download')->middleware('permission:sms-logs-download');

        // Reinsurance — read + show + form lookups
        Route::get('reinsurance/form-lookups',          [ReinsuranceApiController::class, 'formLookups'])->name('reinsurance.formLookups');
        // Reinsurance Types — full CRUD
        Route::get('reinsurance/types',                 [ReinsuranceApiController::class, 'types'])->name('reinsurance.types');
        Route::post('reinsurance/types',                [ReinsuranceApiController::class, 'storeType'])->name('reinsurance.types.store');
        Route::get('reinsurance/types/{id}',            [ReinsuranceApiController::class, 'showType'])->name('reinsurance.showType');
        Route::put('reinsurance/types/{id}',            [ReinsuranceApiController::class, 'updateType'])->name('reinsurance.types.update');
        Route::delete('reinsurance/types/{id}',         [ReinsuranceApiController::class, 'destroyType'])->name('reinsurance.types.destroy');
        // Coverage Groups — full CRUD
        Route::get('reinsurance/coverage-groups',       [ReinsuranceApiController::class, 'coverageGroups'])->name('reinsurance.coverageGroups');
        Route::post('reinsurance/coverage-groups',      [ReinsuranceApiController::class, 'storeGroup'])->name('reinsurance.groups.store');
        Route::get('reinsurance/coverage-groups/{id}',  [ReinsuranceApiController::class, 'showGroup'])->name('reinsurance.showGroup');
        Route::put('reinsurance/coverage-groups/{id}',  [ReinsuranceApiController::class, 'updateGroup'])->name('reinsurance.groups.update');
        Route::delete('reinsurance/coverage-groups/{id}', [ReinsuranceApiController::class, 'destroyGroup'])->name('reinsurance.groups.destroy');
        // Product → coverages (used by Coverage Group Add/Edit to populate matrix)
        Route::get('reinsurance/products/{id}/coverages', [ReinsuranceApiController::class, 'productCoverages'])->name('reinsurance.productCoverages');
        // Sub-coverages for dynamic coverage loading (e.g., FIRE, OFFICE CONTENTS)
        Route::get('reinsurance/sub-coverages/{coverageCode}', [ReinsuranceApiController::class, 'getProductSubCoverages'])->name('reinsurance.subCoverages');
        // Hardcoded coverage variants (e.g., COMMERCIALMOTOR, MOTOR TRADERS)
        Route::get('reinsurance/hardcoded-coverages/{coverageCode}', [ReinsuranceApiController::class, 'getHardcodedCoverages'])->name('reinsurance.hardcodedCoverages');
        // Formulas — full CRUD
        Route::get('reinsurance/formulas',              [ReinsuranceApiController::class, 'formulas'])->name('reinsurance.formulas');
        Route::post('reinsurance/formulas',             [ReinsuranceApiController::class, 'storeFormula'])->name('reinsurance.formulas.store');
        Route::get('reinsurance/formulas/{id}',         [ReinsuranceApiController::class, 'showFormula'])->name('reinsurance.showFormula');
        Route::put('reinsurance/formulas/{id}',         [ReinsuranceApiController::class, 'updateFormula'])->name('reinsurance.formulas.update');
        Route::delete('reinsurance/formulas/{id}',      [ReinsuranceApiController::class, 'destroyFormula'])->name('reinsurance.formulas.destroy');
        // Treaties — full CRUD
        Route::get('reinsurance/treaties',              [ReinsuranceApiController::class, 'treaties'])->name('reinsurance.treaties');
        Route::post('reinsurance/treaties',             [ReinsuranceApiController::class, 'storeTreaty'])->name('reinsurance.treaties.store');
        Route::get('reinsurance/treaties/{id}',         [ReinsuranceApiController::class, 'showTreaty'])->name('reinsurance.showTreaty');
        Route::put('reinsurance/treaties/{id}',         [ReinsuranceApiController::class, 'updateTreaty'])->name('reinsurance.treaties.update');
        Route::delete('reinsurance/treaties/{id}',      [ReinsuranceApiController::class, 'destroyTreaty'])->name('reinsurance.treaties.destroy');
        Route::post('reinsurance/treaties/{id}/rollover', [ReinsuranceApiController::class, 'rolloverTreaty'])->name('reinsurance.treaties.rollover');

        // ─── Policy Validation ──────────────────────────────────────────────────
        Route::get('policy-validation/rules',         [MasterDataController::class, 'validationRules'])->name('policyValidation.rules');
        Route::post('policy-validation/rules',        [MasterDataController::class, 'validationRulesStore'])->name('policyValidation.rules.store');
        Route::put('policy-validation/rules/{id}',    [MasterDataController::class, 'validationRulesUpdate'])->name('policyValidation.rules.update');
        Route::delete('policy-validation/rules/{id}', [MasterDataController::class, 'validationRulesDestroy'])->name('policyValidation.rules.destroy');

        Route::get('policy-validation/groups',         [MasterDataController::class, 'validationGroups'])->name('policyValidation.groups');
        Route::post('policy-validation/groups',        [MasterDataController::class, 'validationGroupsStore'])->name('policyValidation.groups.store');
        Route::put('policy-validation/groups/{id}',    [MasterDataController::class, 'validationGroupsUpdate'])->name('policyValidation.groups.update');
        Route::delete('policy-validation/groups/{id}', [MasterDataController::class, 'validationGroupsDestroy'])->name('policyValidation.groups.destroy');

        // Role → ValidationRuleGroup binding (drives Submit-to-Approval rule gate)
        Route::get('policy-validation/role-groups',       [MasterDataController::class, 'rolesWithRuleGroup'])->name('policyValidation.roleGroups');
        Route::put('policy-validation/roles/{id}/group',  [MasterDataController::class, 'rolesAssignRuleGroup'])->name('policyValidation.assignRoleGroup');

        // Preview — diagnostic endpoint. Returns the 6-action permission map
        // (canRate / canPrintQuote / canPrintApp / canBindApp / canSubmitUnbound
        // / canIssue) for a (policy, action, current user). Used by the frontend
        // to pre-emptively grey out buttons before the user clicks. Optional
        // ?action_id=NN query string; defaults to the policy's latest action.
        Route::get('policy-validation/preview/{policyId}', [MasterDataController::class, 'validationPreview'])->name('policyValidation.preview');

        // ─── Coverage Master & Reinsurers ────────────────────────────────────────
        Route::get('master/coverages',       [MasterDataController::class, 'coverageMaster'])->name('master.coverages');
        Route::get('master/coverages/all',   [MasterDataController::class, 'coverageMasterList'])->name('master.coveragesList');
        Route::get('reinsurance/reinsurers',         [MasterDataController::class, 'reinsurers'])->name('reinsurance.reinsurers');
        Route::post('reinsurance/reinsurers',        [MasterDataController::class, 'reinsurersStore'])->name('reinsurance.reinsurers.store');
        Route::put('reinsurance/reinsurers/{id}',    [MasterDataController::class, 'reinsurersUpdate'])->name('reinsurance.reinsurers.update');
        Route::delete('reinsurance/reinsurers/{id}', [MasterDataController::class, 'reinsurersDestroy'])->name('reinsurance.reinsurers.destroy');

        // ─── FAC Register — reads ─────────────────────────────────────────────
        // Facultative placements: the register, the live SUMMARY, the variance to
        // the ledger, and the "is this policy FAC'ed?" check. Writes live in the
        // api_write group below and are separately permissioned — the underwriter
        // who raises a line must not be able to sign off its own payment.
        Route::middleware('permission:reinsurance-fac-list')->group(function () {
            Route::get('reinsurance/fac',                     [FacRegisterApiController::class, 'index'])->name('reinsurance.fac.index');
            Route::get('reinsurance/fac/summary',             [FacRegisterApiController::class, 'summary'])->name('reinsurance.fac.summary');
            Route::get('reinsurance/fac/variance',            [FacRegisterApiController::class, 'variance'])->name('reinsurance.fac.variance');
            Route::get('reinsurance/fac/lookup',              [FacRegisterApiController::class, 'lookupPolicy'])->name('reinsurance.fac.lookup');
            Route::get('reinsurance/fac/coverage',            [FacRegisterApiController::class, 'coverageForPolicy'])->name('reinsurance.fac.coverage');
            Route::get('reinsurance/fac/coverage-scan',       [FacRegisterApiController::class, 'coverageScan'])->name('reinsurance.fac.coverageScan');
            Route::get('reinsurance/fac/export',              [FacRegisterApiController::class, 'export'])->name('reinsurance.fac.export');
            Route::get('reinsurance/fac/slips',               [FacRegisterApiController::class, 'slips'])->name('reinsurance.fac.slips');
            // The cession bordereau — the statement a broker or reinsurer agrees
            // line by line. Read-only, so it sits with the list rights rather than
            // behind the slip right.
            Route::get('reinsurance/fac/bordereau',          [FacRegisterApiController::class, 'bordereau'])->name('reinsurance.fac.bordereau');
            Route::get('reinsurance/fac/bordereau/csv',      [FacRegisterApiController::class, 'bordereauCsv'])->name('reinsurance.fac.bordereau.csv');
            Route::get('reinsurance/fac/slips/{slipId}/download', [FacRegisterApiController::class, 'downloadSlip'])->whereNumber('slipId')->name('reinsurance.fac.slips.download');
            Route::get('reinsurance/fac/{id}',                [FacRegisterApiController::class, 'show'])->whereNumber('id')->name('reinsurance.fac.show');
            Route::get('reinsurance/fac/{id}/attachments/{attId}', [FacRegisterApiController::class, 'downloadAttachment'])->whereNumber('id')->whereNumber('attId')->name('reinsurance.fac.attachments.download');
        });

        // ─── Companies (admin CRUD — full list incl. sub-companies) ────────────
        // /lookups/companies remains the policy-wizard picker (limit 50).
        Route::get('master/companies',         [MasterDataController::class, 'companiesAdminIndex'])->name('master.companies.index');
        Route::post('master/companies',        [MasterDataController::class, 'companiesAdminStore'])->name('master.companies.store');
        Route::put('master/companies/{id}',    [MasterDataController::class, 'companiesAdminUpdate'])->name('master.companies.update');
        Route::delete('master/companies/{id}', [MasterDataController::class, 'companiesAdminDestroy'])->name('master.companies.destroy');

        // ─── Union Group Scheme (registration + members + dashboard) ───────────
        // Legal Insurance Group Scheme per union (BONU, BOWASEWU). Registering a
        // union auto-mints its group policy (MIS<union_code>, overridable).
        // Gated by the union permissions (2026_07_25_000012_seed_union_permissions).
        Route::get('unions',                 [UnionSchemeController::class, 'index'])->middleware('permission:view_unions')->name('unions.index');
        Route::post('unions',                [UnionSchemeController::class, 'store'])->middleware('permission:create_union')->name('unions.store');
        Route::get('unions/{id}',            [UnionSchemeController::class, 'show'])->middleware('permission:view_unions')->name('unions.show');
        Route::put('unions/{id}',            [UnionSchemeController::class, 'update'])->middleware('permission:edit_union')->name('unions.update');
        Route::patch('unions/{id}/status',   [UnionSchemeController::class, 'setStatus'])->middleware('permission:activate_deactivate_union')->name('unions.setStatus');
        Route::delete('unions/{id}',         [UnionSchemeController::class, 'destroy'])->middleware('permission:edit_union')->name('unions.destroy');
        Route::get('unions/{id}/dashboard',  [UnionSchemeController::class, 'dashboard'])->middleware('permission:view_unions')->name('unions.dashboard');
        // Members — gated by view_unions (same access level as Union Management).
        // Static Excel paths BEFORE the {memberId} wildcards.
        Route::get('unions/{id}/members',                 [UnionSchemeController::class, 'members'])->middleware('permission:view_unions')->name('unions.members.index');
        Route::get('unions/{id}/members/template',        [UnionSchemeController::class, 'memberTemplate'])->middleware('permission:view_unions')->name('unions.members.template');
        Route::get('unions/{id}/members/export',          [UnionSchemeController::class, 'exportMembers'])->middleware('permission:view_unions')->name('unions.members.export');
        Route::post('unions/{id}/members/import',         [UnionSchemeController::class, 'importMembers'])->middleware('permission:view_unions')->name('unions.members.import');
        Route::post('unions/{id}/members',                [UnionSchemeController::class, 'storeMember'])->middleware('permission:view_unions')->name('unions.members.store');
        Route::put('unions/{id}/members/{memberId}',      [UnionSchemeController::class, 'updateMember'])->middleware('permission:view_unions')->name('unions.members.update');
        Route::delete('unions/{id}/members/{memberId}',   [UnionSchemeController::class, 'destroyMember'])->middleware('permission:view_unions')->name('unions.members.destroy');

        // Legal claims (BONU claim form filed against a member) — same view_unions
        // access level as the rest of the union module. The mirrored standard
        // claim is separately visible in the Claims module under claim-list.
        Route::get('unions/{id}/legal-claims',                        [UnionSchemeController::class, 'legalClaims'])->middleware('permission:view_unions')->name('unions.legalClaims.index');
        Route::get('unions/{id}/legal-claims/{claimId}',              [UnionSchemeController::class, 'showLegalClaim'])->middleware('permission:view_unions')->name('unions.legalClaims.show');
        Route::post('unions/{id}/members/{memberId}/legal-claims',    [UnionSchemeController::class, 'storeLegalClaim'])->middleware('permission:view_unions')->name('unions.legalClaims.store');

        // Monthly premium collection (BONU brief 2026-09-08): payment list per
        // month + proof-of-payment files. Reads = view_unions (Underwriting,
        // Accounts, Claims); imports/uploads/deletes = manage_union_payments
        // (2026_09_08_130001_seed_union_payments_permission). Static paths
        // before wildcards.
        Route::get('unions/{id}/payments',                     [UnionPaymentsController::class, 'index'])->middleware('permission:view_unions')->name('unions.payments.index');
        Route::get('unions/{id}/payments/periods',             [UnionPaymentsController::class, 'periods'])->middleware('permission:view_unions')->name('unions.payments.periods');
        Route::get('unions/{id}/payments/template',            [UnionPaymentsController::class, 'template'])->middleware('permission:view_unions')->name('unions.payments.template');
        Route::post('unions/{id}/payments/import',             [UnionPaymentsController::class, 'import'])->middleware('permission:manage_union_payments')->name('unions.payments.import');
        Route::put('unions/{id}/payments/members/{memberId}',  [UnionPaymentsController::class, 'setMember'])->middleware('permission:manage_union_payments')->name('unions.payments.setMember');
        Route::get('unions/{id}/payments/proofs',              [UnionPaymentsController::class, 'proofs'])->middleware('permission:view_unions')->name('unions.payments.proofs');
        Route::post('unions/{id}/payments/proofs',             [UnionPaymentsController::class, 'storeProof'])->middleware('permission:manage_union_payments')->name('unions.payments.storeProof');
        Route::delete('unions/{id}/payments/proofs/{proofId}', [UnionPaymentsController::class, 'destroyProof'])->middleware('permission:manage_union_payments')->name('unions.payments.destroyProof');
        Route::put('unions/{id}/legal-claims/{claimId}',              [UnionSchemeController::class, 'updateLegalClaim'])->middleware('permission:view_unions')->name('unions.legalClaims.update');

        // ─── High-Risk Countries (AML watch-list CRUD) ─────────────────────────
        // Drives the "High Risk Customer" badge on the policy view.
        Route::get('master/high-risk-countries',            [MasterDataController::class, 'highRiskCountriesIndex'])->name('master.highRiskCountries.index');
        Route::get('master/high-risk-countries/options',    [MasterDataController::class, 'highRiskCountriesOptions'])->name('master.highRiskCountries.options');
        Route::post('master/high-risk-countries',           [MasterDataController::class, 'highRiskCountriesStore'])->name('master.highRiskCountries.store');
        Route::delete('master/high-risk-countries/{id}',    [MasterDataController::class, 'highRiskCountriesDestroy'])->name('master.highRiskCountries.destroy');

        // ─── Specified-Coverage Items (master CRUD) ────────────────────────────
        // Legacy parity: Livewire/Coverage/SpecifiedCoverage/{Table,Add,Edit}.
        Route::get('master/specified-coverage-items',          [MasterDataController::class, 'specifiedCoverageItemsIndex'])->name('master.specifiedCoverageItems.index');
        Route::post('master/specified-coverage-items',         [MasterDataController::class, 'specifiedCoverageItemsStore'])->name('master.specifiedCoverageItems.store');
        Route::get('master/specified-coverage-items/{id}',     [MasterDataController::class, 'specifiedCoverageItemsShow'])->name('master.specifiedCoverageItems.show');
        Route::put('master/specified-coverage-items/{id}',     [MasterDataController::class, 'specifiedCoverageItemsUpdate'])->name('master.specifiedCoverageItems.update');
        Route::delete('master/specified-coverage-items/{id}',  [MasterDataController::class, 'specifiedCoverageItemsDestroy'])->name('master.specifiedCoverageItems.destroy');

        // Coverage Master — write ops (legacy Livewire/Coverage/{Add,Edit,Table})
        Route::post('master/coverages',            [MasterDataController::class, 'coverageMasterStore'])->name('master.coverages.store');
        Route::put('master/coverages/{id}',        [MasterDataController::class, 'coverageMasterUpdate'])->name('master.coverages.update');
        Route::delete('master/coverages/{id}',     [MasterDataController::class, 'coverageMasterDestroy'])->name('master.coverages.destroy');

        // Sub Coverages — legacy Livewire/SubCoverage/{Table,Add,Edit}
        Route::get('master/sub-coverages',         [MasterDataController::class, 'subCoveragesIndex'])->name('master.subCoverages.index');
        Route::post('master/sub-coverages',        [MasterDataController::class, 'subCoveragesStore'])->name('master.subCoverages.store');
        Route::put('master/sub-coverages/{id}',    [MasterDataController::class, 'subCoveragesUpdate'])->name('master.subCoverages.update');
        Route::delete('master/sub-coverages/{id}', [MasterDataController::class, 'subCoveragesDestroy'])->name('master.subCoverages.destroy');

        // Extensions / Excess / Misc — legacy Livewire/Extentions/{Table,Add,Edit}
        Route::get('master/extensions',            [MasterDataController::class, 'extensionsIndex'])->name('master.extensions.index');
        Route::post('master/extensions',           [MasterDataController::class, 'extensionsStore'])->name('master.extensions.store');
        Route::put('master/extensions/{id}',       [MasterDataController::class, 'extensionsUpdate'])->name('master.extensions.update');
        Route::delete('master/extensions/{id}',    [MasterDataController::class, 'extensionsDestroy'])->name('master.extensions.destroy');

        // Excel Import Activities
        Route::get('import-activities',   [ExcelImportController::class, 'activities'])->name('importActivities.index');
        Route::post('excel-import/upload', [ExcelImportController::class, 'upload'])->name('excelImport.upload');

        // RealPay settlement import (dedicated screen: upload -> dry-run preview -> confirm -> commit)
        Route::post('realpay-settlement/upload',              [RealpaySettlementImportController::class, 'upload'])->name('realpaySettlement.upload');
        Route::get('realpay-settlement/imports',              [RealpaySettlementImportController::class, 'index'])->name('realpaySettlement.index');
        Route::get('realpay-settlement/imports/{id}',         [RealpaySettlementImportController::class, 'show'])->whereNumber('id')->name('realpaySettlement.show');
        Route::post('realpay-settlement/imports/{id}/confirm', [RealpaySettlementImportController::class, 'confirm'])->whereNumber('id')->name('realpaySettlement.confirm');

        // ─── Claims FNOL (First Notification of Loss) intake ─────────────────
        // Lightweight pre-claim ledger: record a reported loss before it is a
        // registrable claim, chase docs (send-gated command), then convert into
        // a real claim (reusing ClaimsController::store). Inert unless the
        // `claims_fnol` runtime toggle is on (default OFF) — every endpoint 404s
        // while disabled. Declared BEFORE the `claims/{id}` wildcard below so the
        // static `claims/fnol` path isn't captured as an {id}. RBAC per route:
        // read = claim-list, create/convert = claim-create, edit/close = claim-edit.
        Route::get('claims/fnol',              [ClaimFnolController::class, 'index'])->name('claims.fnol.index')->middleware('permission:claim-list');
        Route::post('claims/fnol',             [ClaimFnolController::class, 'store'])->name('claims.fnol.store')->middleware('permission:claim-create');
        Route::get('claims/fnol/{id}',         [ClaimFnolController::class, 'show'])->whereNumber('id')->name('claims.fnol.show')->middleware('permission:claim-list');
        Route::put('claims/fnol/{id}',         [ClaimFnolController::class, 'update'])->whereNumber('id')->name('claims.fnol.update')->middleware('permission:claim-edit');
        Route::post('claims/fnol/{id}/convert', [ClaimFnolController::class, 'convert'])->whereNumber('id')->name('claims.fnol.convert')->middleware('permission:claim-create');
        Route::post('claims/fnol/{id}/close',  [ClaimFnolController::class, 'close'])->whereNumber('id')->name('claims.fnol.close')->middleware('permission:claim-edit');

        // Claims — read (static paths MUST come before {id} wildcard)
        Route::get('claims',                         [ClaimsController::class, 'index'])->name('claims.index');
        Route::get('claims/create-data',             [ClaimsController::class, 'createData'])->name('claims.createData');
        Route::get('claims/assessor-list',           [ClaimsController::class, 'assessorList'])->name('claims.assessorList');
        Route::get('claims/policy/{id}/coverages',   [ClaimsController::class, 'policyCoverages'])->name('claims.policyCoverages');
        Route::get('claims/claim-types-by-policy',  [ClaimsController::class, 'claimTypesByPolicy'])->name('claims.claimTypesByPolicy');
        Route::get('claims/policy/{policyId}/claim-types-by-action/{actionId}', [ClaimsController::class, 'claimTypesByAction'])->name('claims.claimTypesByAction');
        Route::get('claims/sub-types-by-claim-type', [ClaimsController::class, 'subTypesByClaimType'])->name('claims.subTypesByClaimType');
        Route::get('claims/{id}',                    [ClaimsController::class, 'show'])->name('claims.show');
        Route::get('claims/{id}/policy-actions',     [ClaimsController::class, 'policyActions'])->name('claims.policyActions');

        // Claims V2 — read (static paths MUST come before {id} wildcard)
        Route::get('claims-v2',                        [ClaimsV2Controller::class, 'index'])->name('claimsV2.index');
        Route::get('claims-v2/form-config',            [ClaimsV2Controller::class, 'formConfig'])->name('claimsV2.formConfig');
        Route::get('claims-v2/claim-types',            [ClaimsV2Controller::class, 'claimTypes'])->name('claimsV2.claimTypes');
        // Attachments / Document-Type dropdown lookup — mirrors legacy
        // Lookup::where('key','file_type') feeding the attachment blade's
        // Document Type <select>.
        Route::get('claims-v2/lookups/file-types',     [ClaimsV2Controller::class, 'fileTypeLookup'])->name('claimsV2.fileTypes');
        Route::get('claims-v2/dashboard',              [ClaimsV2Controller::class, 'dashboard'])->name('claimsV2.dashboard');
        // Claims Tracker Dashboard 1:1 replica feed (all aggregates in one call).
        // Additive, read-only. Static path — declared BEFORE the claims-v2/{id}
        // wildcard so 'tracker-dashboard' isn't matched as an {id}.
        Route::get('claims-v2/tracker-dashboard',      [ClaimsV2Controller::class, 'trackerDashboard'])->name('claimsV2.trackerDashboard');
        // Underwriting bottleneck dashboard (CFO 2026-08-27) — read-only aggregates
        // of the new-business funnel (policy_actions.status). No customer PII.
        // Additive, read-only; gated to the underwriting/manager roles below.
        Route::get('uw-bottleneck/dashboard',          [UwBottleneckController::class, 'dashboard'])->name('uwBottleneck.dashboard')->middleware('role:Underwriting Head|Underwriter|Manager|Claims Manager|Admin|Super Admin');
        // Claims Tracker "All Claims" list 1:1 replica feed (paginated rows +
        // SLA-derived stage chips / status). Additive, read-only. Static path —
        // declared BEFORE the claims-v2/{id} wildcard so 'tracker-list' isn't
        // matched as an {id}.
        Route::get('claims-v2/tracker-list',           [ClaimsV2Controller::class, 'trackerList'])->name('claimsV2.trackerList');
        // Claims Tracker "Incentive Report" 1:1 replica feed (two per-handler
        // supplier-compliance tables: panel-beater + glass, month-filtered).
        // Additive, read-only. Static path — declared BEFORE the claims-v2/{id}
        // wildcard so 'tracker-incentive' isn't matched as an {id}. RBAC mirrors
        // the existing incentive route (Claims Manager | Admin | Super Admin).
        Route::get('claims-v2/tracker-incentive',      [ClaimsV2Controller::class, 'trackerIncentive'])->name('claimsV2.trackerIncentive')->middleware('role:Claims Manager|Admin|Super Admin');
        // Claims Tracker replica "Del" — REVERSIBLE soft-delete (+ restore).
        // Stamps claims.deleted_at (the tracker list hides stamped rows); never
        // destroys the claim / reserves / payments. Role-gated like the legacy
        // tracker (Admin | Super Admin | Claims Manager). Static paths declared
        // BEFORE the claims-v2/{id} wildcard.
        // Controlled + audited delete (v5 #1): gated by the `claim-delete`
        // permission (Admin/Super Admin + four named claims-team users) instead of
        // a broad role, so delete is restricted to the confirmed individuals.
        Route::post('claims-v2/{id}/soft-delete',      [ClaimsV2Controller::class, 'softDelete'])->name('claimsV2.softDelete')->middleware('permission:claim-delete');
        Route::post('claims-v2/{id}/restore',          [ClaimsV2Controller::class, 'restore'])->name('claimsV2.restore')->middleware('permission:claim-delete');
        // Phase-3 incentive report (approved panel-beater / glass-supplier %).
        // Feature-flagged inside the controller (claims_incentive_report, default
        // OFF); RBAC: Claims Manager | Admin | Super Admin. Static path — declared
        // BEFORE the claims-v2/{id} wildcard so 'reports' isn't matched as an {id}.
        Route::get('claims-v2/reports/incentive',      [ClaimsIncentiveReportController::class, 'report'])->name('claimsV2.incentiveReport')->middleware('role:Claims Manager|Admin|Super Admin');
        // Claims Tracker "API Access" replica — read-only registry of Sanctum
        // personal access tokens (the genuine "who can call the API" source in
        // Graphite) + usage stats. Additive, read-only, never returns the token
        // secret. Admin | Super Admin only. Static path — declared BEFORE the
        // claims-v2/{id} wildcard so 'api-access' isn't matched as an {id}.
        Route::get('claims-v2/api-access',             [ClaimsV2Controller::class, 'apiAccess'])->name('claimsV2.apiAccess')->middleware('role:Admin|admin|Super Admin');
        // Claim-form send button — the handler picks the form and the address,
        // and the claimant is emailed a pre-filled PDF plus a no-password link.
        // A human chooses because the claim type does NOT reliably say motor vs
        // non-motor ('Accident' is 41% of claims and its flags disagree), so a
        // machine picking the form would send the wrong one at scale. Whole
        // surface is dark behind the `claims_form_dispatch` runtime flag, which
        // the controller checks. Static paths declared BEFORE the claims-v2/{id}
        // wildcard so they are not matched as an {id}.
        Route::get('claims-v2/{id}/claim-form-options', [\AlphaDirect\Http\Controllers\Api\V1\ClaimFormDispatchController::class, 'options'])
            ->whereNumber('id')->name('claimsV2.claimFormOptions')
            ->middleware('role:Claims Manager|Admin|admin|Super Admin|Underwriter');
        Route::post('claims-v2/{id}/send-claim-form',   [\AlphaDirect\Http\Controllers\Api\V1\ClaimFormDispatchController::class, 'send'])
            ->whereNumber('id')->name('claimsV2.sendClaimForm')
            ->middleware(['role:Claims Manager|Admin|admin|Super Admin|Underwriter', 'throttle:20,1']);

        // Finance's two actions on a premium confirmation. Gated to Finance and
        // above — a claims handler must not be able to sign off the premium
        // check that exists to control their own claim.
        Route::post('claims-v2/premium-confirmations/{id}/record', [\AlphaDirect\Http\Controllers\Api\V1\PremiumConfirmationController::class, 'record'])
            ->whereNumber('id')->name('claimsV2.premiumConfirmationRecord')
            ->middleware('role:Finance|Admin|admin|Super Admin|Manager');
        Route::post('claims-v2/premium-confirmations/{id}/draft-collection', [\AlphaDirect\Http\Controllers\Api\V1\PremiumConfirmationController::class, 'draftCollection'])
            ->whereNumber('id')->name('claimsV2.premiumConfirmationDraft')
            ->middleware('role:Finance|Admin|admin|Super Admin|Manager');

        // Premium confirmation — Finance's manual spreadsheet, moved onto the
        // claim and off Odoo. Raised automatically when a claim is registered;
        // paid-up releases itself; arrears always reaches a person and NEVER
        // auto-declines. Dark behind the `premium_confirmation` runtime flag,
        // checked in the controller. Static paths BEFORE the claims-v2/{id}
        // wildcard so they are not matched as an {id}.
        Route::get('claims-v2/premium-confirmations',  [\AlphaDirect\Http\Controllers\Api\V1\PremiumConfirmationController::class, 'index'])
            ->name('claimsV2.premiumConfirmations')
            ->middleware('role:Claims Manager|Finance|Manager|Admin|admin|Super Admin');
        Route::get('claims-v2/{id}/premium-confirmation', [\AlphaDirect\Http\Controllers\Api\V1\PremiumConfirmationController::class, 'forClaim'])
            ->whereNumber('id')->name('claimsV2.premiumConfirmationForClaim')
            ->middleware('role:Claims Manager|Finance|Manager|Admin|admin|Super Admin');

        // PO-in-Graphite Phase 2 — the Omni purchase orders raised for this
        // claim, proxied SERVER-SIDE (the Omni key never reaches the browser).
        // Dark behind the `omni_po` runtime flag (404 while off). Read-only,
        // rendered live, never persisted — Omni stays the accounting truth.
        // Claims department + finance + admins, per the CFO spec's audience.
        Route::get('claims-v2/{id}/purchase-orders', [\AlphaDirect\Http\Controllers\Api\V1\ClaimPurchaseOrdersController::class, 'forClaim'])
            ->whereNumber('id')->name('claimsV2.purchaseOrders')
            ->middleware('role:Claims Team|Claim Handler|Claim Processor|Claims Manager|Finance|Manager|Admin|admin|Super Admin');

        Route::get('claims-v2/{id}',                   [ClaimsV2Controller::class, 'show'])->name('claimsV2.show');
        Route::get('claims/{id}/reinsurance',          [ClaimsV2Controller::class, 'reinsurance'])->name('claims.reinsurance');
        Route::get('claims/{id}/review-notes',         [ClaimsV2Controller::class, 'reviewNotes'])->name('claims.reviewNotes');
        Route::get('claims/{id}/comment-status',       [ClaimsV2Controller::class, 'commentStatus'])->name('claims.commentStatus');
        Route::get('claims-v2/lookups/reserve-types',     [ClaimsV2Controller::class, 'reserveTransactionTypes'])->name('claimsV2.reserveTypes');
        Route::get('claims-v2/lookups/reserve-sub-types', [ClaimsV2Controller::class, 'reserveTransactionSubTypes'])->name('claimsV2.reserveSubTypes');
        Route::get('claims-v2/lookups/payees',            [ClaimsV2Controller::class, 'reservePayees'])->name('claimsV2.payees');
        Route::get('claims-v2/{id}/reserves',          [ClaimsV2Controller::class, 'reserves'])->name('claimsV2.reserves');
        Route::get('claims-v2/{id}/reserves/coverages', [ClaimsV2Controller::class, 'reserveCoverages'])->name('claimsV2.reserveCoverages');
        Route::get('claims-v2/{id}/reserves/{crcId}/void-info', [ClaimsV2Controller::class, 'voidPaymentInfo'])->name('claimsV2.voidPaymentInfo');
        Route::get('claims-v2/{id}/documents',         [ClaimsV2Controller::class, 'documents'])->name('claimsV2.documents');
        Route::get('claims-v2/{id}/closing-documents', [ClaimsV2Controller::class, 'closingDocuments'])->name('claimsV2.closingDocuments');
        Route::get('claims-v2/{id}/assessment',        [ClaimsV2Controller::class, 'assessment'])->name('claimsV2.assessment');

        // Assessor workflow (mirrors graphiteBWV8 admin.claims.accident + assessorUpload)
        // NB: claims/assessor-list moved above the /{id} route to avoid
        // Laravel matching 'assessor-list' as an {id} wildcard.
        Route::get('claims/{id}/assessor-reports',     [ClaimsController::class, 'assessorReports'])->name('claims.assessorReports');
        Route::get('claims-v2/{id}/third-parties',     [ClaimsV2Controller::class, 'thirdParties'])->name('claimsV2.thirdParties');
        Route::get('claims-v2/{id}/quotes',            [ClaimsV2Controller::class, 'quotes'])->name('claimsV2.quotes');
        Route::get('claims-v2/{id}/timeline',          [ClaimsV2Controller::class, 'timeline'])->name('claimsV2.timeline');
        // Claim decision workflow (approve/repudiate/reverse) — additive layer,
        // flag `claims_decision_workflow` (default OFF); gating is in-controller
        // (admin preview + flag), so no route-level permission middleware here.
        Route::get('claims-v2/{id}/decision',          [ClaimDecisionController::class, 'show'])->whereNumber('id')->name('claimsV2.decision.show');

        // Payments — read
        Route::get('payments/dpo',          [PaymentController::class, 'dpoTransactions'])->name('payments.dpo');
        Route::get('payments/realpay',      [PaymentController::class, 'realpayLogs'])->name('payments.realpay');
        Route::get('payments/orange-money', [PaymentController::class, 'orangeMoneyTransactions'])->name('payments.orangeMoney');
        Route::get('payments/schedule',     [PaymentController::class, 'scheduleTransactions'])->name('payments.schedule');

        // Payments — DPO online-payment flow (mirrors legacy DpoPaymentController)
        Route::get('payments/online/find-policy',     [PaymentController::class, 'findPolicyForOnlinePayment'])->name('payments.findForOnline');
        Route::post('payments/online/save',           [PaymentController::class, 'saveOnlinePayment'])->name('payments.saveOnline');
        Route::post('payments/online/verify',         [PaymentController::class, 'verifyPayment'])->name('payments.verify');
        Route::post('payments/online/charge-recurrent', [PaymentController::class, 'chargeTokenRecurrent'])->name('payments.chargeRecurrent');

        // ─── Customer Refund Engine — reads (area-scoped in-controller) ─────
        // Route middleware gates the ACTION (any refund permission may read);
        // controllers additionally filter every query by the caller's
        // refund_area_mis / refund_area_dc permission — the DPA control.
        // Static paths BEFORE the {id} route so 'summary'/'export' don't bind as ids.
        // NOTE: named 'summary', NOT 'metrics' — Cloudflare's WAF blocks any URL
        // path segment starting with 'metrics' (scanner protection), so the old
        // endpoint returned a CF 403 and never reached Laravel: every user's
        // dashboard tiles silently read zero.
        Route::get('refund-requests/summary', [\AlphaDirect\Http\Controllers\Api\V1\Refunds\RefundRequestController::class, 'metrics'])
            ->middleware('permission:refund-report|refund-create|refund-review|refund-approve')->name('refundRequests.summary');
        Route::get('refund-requests/export',  [\AlphaDirect\Http\Controllers\Api\V1\Refunds\RefundRequestController::class, 'export'])
            ->middleware('permission:refund-report')->name('refundRequests.export');
        Route::get('refund-requests',         [\AlphaDirect\Http\Controllers\Api\V1\Refunds\RefundRequestController::class, 'index'])
            ->middleware('permission:refund-report|refund-create|refund-review|refund-approve')->name('refundRequests.index');
        Route::get('refund-requests/{id}',    [\AlphaDirect\Http\Controllers\Api\V1\Refunds\RefundRequestController::class, 'show'])
            ->whereNumber('id')->middleware('permission:refund-report|refund-create|refund-review|refund-approve')->name('refundRequests.show');
        // Full bank account number for statement verification (logged). Only the
        // reviewer/approver roles — not plain refund-report visibility.
        Route::get('refund-requests/{id}/account', [\AlphaDirect\Http\Controllers\Api\V1\Refunds\RefundRequestController::class, 'revealAccount'])
            ->whereNumber('id')->middleware('permission:refund-review|refund-approve|refund-cfo-approve')->name('refundRequests.revealAccount');
        Route::get('refund-requests/{id}/documents/{docId}/download', [\AlphaDirect\Http\Controllers\Api\V1\Refunds\RefundRequestController::class, 'downloadDocument'])
            ->whereNumber('id')->whereNumber('docId')
            ->middleware('permission:refund-report|refund-create|refund-review|refund-approve')->name('refundRequests.documents.download');
        Route::get('refund-requests/{id}/assignable-users', [\AlphaDirect\Http\Controllers\Api\V1\Refunds\RefundRequestReviewController::class, 'assignableUsers'])
            ->whereNumber('id')->middleware('permission:refund-approve')->name('refundRequests.assignableUsers');
        // Finance review-and-post queue — prepared Credit Note entries for
        // paid return-premium refunds (never auto-posted; CFO 2026-07-26).
        Route::get('refund-accounting', [\AlphaDirect\Http\Controllers\Api\V1\Refunds\RefundAccountingController::class, 'index'])
            ->middleware('permission:refund-accounting-post')->name('refundAccounting.index');

        // DPO refunds — single + bulk (the legacy gateway-refund tool, distinct
        // from the Customer Refund Engine above). RETRO-GATED 2026-07-27 (CFO
        // task): these previously ran on auth:sanctum alone — any authenticated
        // user could issue/replay a DPO refund. Now restricted to the roles
        // that actually operate the tool (Finance/admins) or refund approvers.
        Route::group(['middleware' => 'role_or_permission:Finance|Admin|admin|Super Admin|refund-approve'], function () {
        Route::post('payments/{id}/refund',            [\AlphaDirect\Http\Controllers\Api\V1\RefundController::class, 'refundSingle'])->name('payments.refund');
        Route::get('payments/refunds',                 [\AlphaDirect\Http\Controllers\Api\V1\RefundController::class, 'listRefunds'])->name('payments.refunds.list');
        Route::get('payments/refunds/bulk',            [\AlphaDirect\Http\Controllers\Api\V1\RefundController::class, 'listBulkBatches'])->name('payments.refunds.bulk.list');
        Route::post('payments/refunds/bulk',           [\AlphaDirect\Http\Controllers\Api\V1\RefundController::class, 'createBulkBatch'])->name('payments.refunds.bulk.create');
        Route::get('payments/refunds/bulk/{id}',       [\AlphaDirect\Http\Controllers\Api\V1\RefundController::class, 'showBulkBatch'])->name('payments.refunds.bulk.show');
        Route::post('payments/refunds/bulk/{id}/run',  [\AlphaDirect\Http\Controllers\Api\V1\RefundController::class, 'runBulkBatch'])->name('payments.refunds.bulk.run');
        Route::get('payments/refunds/bulk/{id}/csv',   [\AlphaDirect\Http\Controllers\Api\V1\RefundController::class, 'exportBulkBatchCsv'])->name('payments.refunds.bulk.csv');
        Route::get('payments/refunds/{refundId}',      [\AlphaDirect\Http\Controllers\Api\V1\RefundController::class, 'showRefund'])->name('payments.refunds.show');
        });

        // Cross-system policy cancellation
        Route::post('policies/{id}/cancel-all', [PolicyCreateController::class, 'cancelPolicyFromAll'])->name('policies.cancelAll');

        // Commissions — read
        Route::get('commission/rules',                   [CommissionController::class, 'rules'])->name('commission.rules');
        Route::get('commission/targets',                 [CommissionController::class, 'targets'])->name('commission.targets');
        Route::get('commission/ledger',                  [CommissionController::class, 'ledger'])->name('commission.ledger');
        Route::get('commission/dashboard',               [CommissionController::class, 'dashboard'])->name('commission.dashboard');
        Route::get('commission/fraud-alerts',            [CommissionController::class, 'fraudAlerts'])->name('commission.fraudAlerts');
        Route::get('commission/agent/{agentId}/summary', [CommissionController::class, 'agentSummary'])->name('commission.agentSummary');


        // ─── Audit Trail ─────────────────────────────────────────────────────────
        Route::get('audit-trail', function (\Illuminate\Http\Request $request) {
            $validated = $request->validate([
                'search'    => 'nullable|string|max:200',
                'user'      => 'nullable|integer',
                'action'    => 'nullable|string|max:50',
                'date_from' => 'nullable|date',
                'date_to'   => 'nullable|date',
                'per_page'  => 'nullable|integer|min:5|max:100',
                'page'      => 'nullable|integer|min:1',
            ]);

            $perPage = (int) ($validated['per_page'] ?? 50);

            $query = \DB::table('activity_log')
                ->leftJoin('users', function ($join) {
                    $join->on('activity_log.causer_id', '=', 'users.id')
                         ->where('activity_log.causer_type', '=', 'AlphaDirect\\User');
                })
                ->select(
                    'activity_log.id',
                    'activity_log.log_name',
                    'activity_log.description',
                    'activity_log.subject_type',
                    'activity_log.subject_id',
                    'activity_log.causer_id',
                    'activity_log.properties',
                    'activity_log.created_at',
                    // `users` has no `name` column — it stores firstName/lastName
                    // (the model exposes a fullName accessor, but raw queries must
                    // concat the real columns).
                    \DB::raw("TRIM(CONCAT(COALESCE(users.firstName, ''), ' ', COALESCE(users.lastName, ''))) as causer_name")
                );

            // Filters
            if (!empty($validated['search'])) {
                $search = $validated['search'];
                $query->where('activity_log.description', 'like', "%{$search}%");
            }
            if (!empty($validated['user'])) {
                $query->where('activity_log.causer_id', $validated['user']);
            }
            if (!empty($validated['action'])) {
                $query->where('activity_log.log_name', $validated['action']);
            }
            if (!empty($validated['date_from'])) {
                $query->whereDate('activity_log.created_at', '>=', $validated['date_from']);
            }
            if (!empty($validated['date_to'])) {
                $query->whereDate('activity_log.created_at', '<=', $validated['date_to']);
            }

            $query->orderBy('activity_log.id', 'desc');
            $paginated = $query->paginate($perPage);

            // Gather filter options (distinct users who appear in log, distinct log_names)
            $causerUsers = \DB::table('activity_log')
                ->join('users', function ($join) {
                    $join->on('activity_log.causer_id', '=', 'users.id')
                         ->where('activity_log.causer_type', '=', 'AlphaDirect\\User');
                })
                ->select('users.id', \DB::raw("TRIM(CONCAT(COALESCE(users.firstName, ''), ' ', COALESCE(users.lastName, ''))) as name"))
                ->distinct()
                // Order by the selected alias — ordering by a non-selected column
                // (e.g. users.firstName) is rejected by MySQL under SELECT DISTINCT.
                ->orderBy('name')
                ->limit(200)
                ->get();

            $logNames = \DB::table('activity_log')
                ->select('log_name')
                ->distinct()
                ->orderBy('log_name')
                ->pluck('log_name')
                ->filter()
                ->values();

            return response()->json([
                'data' => collect($paginated->items())->map(function ($row) {
                    // Try to extract IP from properties JSON
                    $props = json_decode($row->properties ?? '{}', true) ?: [];
                    return [
                        'id'           => $row->id,
                        'date'         => $row->created_at,
                        'user'         => $row->causer_name ?? 'System',
                        'causer_id'    => $row->causer_id,
                        'action'       => $row->description ?? $row->log_name ?? '',
                        'subject_type' => $row->subject_type ?? '',
                        'subject_id'   => $row->subject_id,
                        'details'      => $row->description ?? '',
                        'ip_address'   => $props['ip'] ?? $props['ip_address'] ?? null,
                    ];
                })->values(),
                'meta' => [
                    'total'        => $paginated->total(),
                    'per_page'     => $paginated->perPage(),
                    'current_page' => $paginated->currentPage(),
                    'last_page'    => $paginated->lastPage(),
                ],
                'filters' => [
                    'users'     => $causerUsers,
                    'log_names' => $logNames,
                ],
            ]);
        })->name('auditTrail.index');

        // ─── Consent compliance dashboard (read) ─────────────────────────
        // Surfaces KPIs (consents/week + month, coverage %, avg verify-to-
        // accept, revocation rate) and a paginated, filterable list. Backs
        // the /admin/consents UI which mirrors /admin/anomalies layout.
        Route::get('admin/consents/summary', [\AlphaDirect\Http\Controllers\Api\V1\ConsentsAdminController::class, 'summary'])->name('admin.consents.summary');
        Route::get('admin/consents',         [\AlphaDirect\Http\Controllers\Api\V1\ConsentsAdminController::class, 'list'])->name('admin.consents.list');

        // ─── MAPFRE / MAWDY travel binds (read-only ops console) ─────────
        // A portal travel sale is bound in MAPFRE's book and never becomes a
        // Graphite policy, so it appears on no policy screen. These three
        // endpoints are the only view ops has of it: KPI tiles, a filtered
        // list, and the same rows as CSV. Read-only by design — nothing here
        // writes, and the stored contract payload is never returned (DPA).
        // Authorisation is enforced in the controller (admin / Super Admin /
        // developer, or the manage_integrations permission).
        Route::get('admin/mapfre-submissions/summary', [\AlphaDirect\Http\Controllers\Api\V1\MapfreSubmissionsController::class, 'summary'])->name('admin.mapfreSubmissions.summary');
        Route::get('admin/mapfre-submissions/export',  [\AlphaDirect\Http\Controllers\Api\V1\MapfreSubmissionsController::class, 'export'])->name('admin.mapfreSubmissions.export');
        Route::get('admin/mapfre-submissions',         [\AlphaDirect\Http\Controllers\Api\V1\MapfreSubmissionsController::class, 'list'])->name('admin.mapfreSubmissions.list');
    });

    // ─── Authenticated write routes ───────────────────────────────────────────
    Route::middleware(['auth:sanctum', 'throttle:api_write', 'XssSanitizer'])->group(function () {

        // Integration on/off toggle. Authorisation (admin/Super Admin/developer
        // or manage_integrations permission) is enforced in the controller;
        // every toggle is written to the activity-log audit trail.
        Route::put('integrations/{integration}', [\AlphaDirect\Http\Controllers\Api\V1\IntegrationSettingsController::class, 'update'])->name('integrations.update');

        // ─── Customer Refund Engine — writes ─────────────────────────────────
        // Every route carries its Spatie action permission (mirrors claims.store);
        // area separation + separation-of-duties are enforced in
        // RefundRequestService. Money leg (Omni handoff) is additionally gated
        // by IntegrationSettings 'omni_refunds' — default OFF.
        Route::post('refund-requests', [\AlphaDirect\Http\Controllers\Api\V1\Refunds\RefundRequestController::class, 'store'])
            ->middleware('permission:refund-create')->name('refundRequests.store');
        Route::put('refund-requests/{id}', [\AlphaDirect\Http\Controllers\Api\V1\Refunds\RefundRequestController::class, 'update'])
            ->whereNumber('id')->middleware('permission:refund-create')->name('refundRequests.update');
        Route::post('refund-requests/{id}/documents', [\AlphaDirect\Http\Controllers\Api\V1\Refunds\RefundRequestController::class, 'uploadDocument'])
            ->whereNumber('id')->middleware('permission:refund-create')->name('refundRequests.documents.upload');
        // Return a rejected refund to draft so the intaker can correct it
        // (Finance ask 2026-08-18). Creator/admin + rejected-only, enforced in
        // the service.
        Route::post('refund-requests/{id}/reset-to-draft', [\AlphaDirect\Http\Controllers\Api\V1\Refunds\RefundRequestController::class, 'resetToDraft'])
            ->whereNumber('id')->middleware('permission:refund-create')->name('refundRequests.resetToDraft');
        Route::post('refund-requests/{id}/submit', [\AlphaDirect\Http\Controllers\Api\V1\Refunds\RefundRequestController::class, 'submit'])
            ->whereNumber('id')->middleware('permission:refund-submit')->name('refundRequests.submit');
        Route::post('refund-requests/{id}/review', [\AlphaDirect\Http\Controllers\Api\V1\Refunds\RefundRequestReviewController::class, 'review'])
            ->whereNumber('id')->middleware('permission:refund-review')->name('refundRequests.review');
        Route::post('refund-requests/{id}/reject', [\AlphaDirect\Http\Controllers\Api\V1\Refunds\RefundRequestReviewController::class, 'reject'])
            ->whereNumber('id')->middleware('permission:refund-review')->name('refundRequests.reject');
        // Escalate is open to reviewers AND approvers: an Administrator blocked
        // by a CRITICAL fraud flag at approve() must be able to send the case
        // up to the CFO themselves (fraud-engine flow), not wait on a reviewer.
        Route::post('refund-requests/{id}/escalate', [\AlphaDirect\Http\Controllers\Api\V1\Refunds\RefundRequestReviewController::class, 'escalate'])
            ->whereNumber('id')->middleware('permission:refund-review|refund-approve')->name('refundRequests.escalate');
        Route::post('refund-requests/{id}/approve', [\AlphaDirect\Http\Controllers\Api\V1\Refunds\RefundRequestReviewController::class, 'approve'])
            ->whereNumber('id')->middleware('permission:refund-approve')->name('refundRequests.approve');
        // Soft delete a single request (Finance ask, Keetile 2026-07-30).
        // Reviewer/approver only; the service refuses once money has moved and
        // the row stays recoverable + counted in fraud history.
        // Delete is CFO/Super-Admin only (was reviewer|approver). A reviewer or
        // approver rejects or escalates; they can no longer remove another
        // person's refund entry. Restore reverses a delete so nothing is ever
        // lost for good.
        Route::delete('refund-requests/{id}', [\AlphaDirect\Http\Controllers\Api\V1\Refunds\RefundRequestReviewController::class, 'destroy'])
            ->whereNumber('id')->middleware('permission:refund-cfo-approve')->name('refundRequests.destroy');
        Route::post('refund-requests/{id}/restore', [\AlphaDirect\Http\Controllers\Api\V1\Refunds\RefundRequestReviewController::class, 'restore'])
            ->whereNumber('id')->middleware('permission:refund-cfo-approve')->name('refundRequests.restore');
        Route::post('refund-requests/{id}/cfo-approve', [\AlphaDirect\Http\Controllers\Api\V1\Refunds\RefundRequestReviewController::class, 'cfoApprove'])
            // Either permission opens the route; RefundRequestService::cfoApprove()
            // then decides which is sufficient for THIS request — the >P50k gate
            // and any CRITICAL-flagged escalation need refund-cfo-approve, while
            // refund-escalation-clear covers ordinary escalations only
            // (CFO 2026-09-02).
            ->whereNumber('id')->middleware('permission:refund-cfo-approve|refund-escalation-clear')->name('refundRequests.cfoApprove');

        // Finance records a refund it already paid OUTSIDE Graphite (manual FNB
        // payment made while the Omni money leg was dark). Gated on
        // refund-accounting-post — it is an accounting statement about money
        // Finance moved, so Finance makes it. No money moves here; the request
        // simply leaves the payout queue (maybeSend() only sends approved /
        // cfo_approved), which is what stops a client being paid twice.
        // Replaces the hand-written production UPDATE this used to require.
        Route::post('refund-requests/{id}/settle-manually', [\AlphaDirect\Http\Controllers\Api\V1\Refunds\RefundRequestReviewController::class, 'settleManually'])
            ->whereNumber('id')->middleware('permission:refund-accounting-post')->name('refundRequests.settleManually');
        // Undo one recorded in error, so a mistake never strands a real refund.
        Route::post('refund-requests/{id}/settle-manually/undo', [\AlphaDirect\Http\Controllers\Api\V1\Refunds\RefundRequestReviewController::class, 'undoManualSettlement'])
            ->whereNumber('id')->middleware('permission:refund-accounting-post')->name('refundRequests.settleManually.undo');
        Route::post('refund-requests/{id}/assign', [\AlphaDirect\Http\Controllers\Api\V1\Refunds\RefundRequestReviewController::class, 'assign'])
            ->whereNumber('id')->middleware('permission:refund-approve')->name('refundRequests.assign');
        // Finance posts/dismisses the prepared Credit Note entries.
        Route::post('refund-accounting/{id}/post', [\AlphaDirect\Http\Controllers\Api\V1\Refunds\RefundAccountingController::class, 'post'])
            ->whereNumber('id')->middleware('permission:refund-accounting-post')->name('refundAccounting.post');
        Route::post('refund-accounting/{id}/dismiss', [\AlphaDirect\Http\Controllers\Api\V1\Refunds\RefundAccountingController::class, 'dismiss'])
            ->whereNumber('id')->middleware('permission:refund-accounting-post')->name('refundAccounting.dismiss');
        // Voiding is deliberately NARROWER than post/dismiss: it is reserved to
        // refund-cfo-approve. Dismiss is Finance ruling on an entry it reviewed;
        // voiding declares an entry invalid and frees the refund to be credited
        // again. That belongs with the CFO, not with the queue's own operators.
        Route::post('refund-accounting/{id}/void', [\AlphaDirect\Http\Controllers\Api\V1\Refunds\RefundAccountingController::class, 'void'])
            ->whereNumber('id')->middleware('permission:refund-cfo-approve')->name('refundAccounting.void');
        // Live connectivity/auth check against the provider (Swiftly: calls the
        // suppliers endpoint, returns program_id + supplier list). Authorised
        // in the controller.
        Route::post('integrations/{integration}/test-connection', [\AlphaDirect\Http\Controllers\Api\V1\IntegrationSettingsController::class, 'testConnection'])->name('integrations.testConnection');
        // Submit a TEST invoice to the provider (Swiftly: with
        // auto_request_early_payment it also raises the early-payment request
        // and fires the webhook back). Authorised in the controller.
        Route::post('integrations/{integration}/test-invoice', [\AlphaDirect\Http\Controllers\Api\V1\IntegrationSettingsController::class, 'submitTestInvoice'])->name('integrations.testInvoice');
        // Save non-secret settings from the UI (e.g. program_id) — no redeploy.
        Route::put('integrations/{integration}/settings', [\AlphaDirect\Http\Controllers\Api\V1\IntegrationSettingsController::class, 'updateSettings'])->name('integrations.updateSettings');

        // Claims SLA — record stage dates on the claim_tracker_workflow row.
        // Inert unless `claims_sla` is on; RBAC (Claims Team / Claims Manager) +
        // the claims-edit permission both enforced. Additive write only (workflow
        // row + claim_edit_log audit) — never touches the live claim/financials.
        Route::patch('claims-v2/{id}/sla-timeline', [ClaimSlaController::class, 'updateTimeline'])->whereNumber('id')->middleware('permission:claim-edit')->name('claims.slaTimelineUpdate');

        // ─── Claims backdate governance — writes (Claims Tracker port) ─────────────
        // Grant/settings/decide are manage-role only (RBAC in controller), preview-
        // capable regardless of the flag. Request submission requires the flag ON.
        Route::post('claims/backdate/settings',              [\AlphaDirect\Http\Controllers\Api\V1\BackdateControlController::class, 'saveSettings'])->name('claims.backdate.settings.save');
        Route::post('claims/backdate/grants',                [\AlphaDirect\Http\Controllers\Api\V1\BackdateControlController::class, 'createGrant'])->name('claims.backdate.grants.create');
        Route::post('claims/backdate/grants/{id}/revoke',    [\AlphaDirect\Http\Controllers\Api\V1\BackdateControlController::class, 'revokeGrant'])->whereNumber('id')->name('claims.backdate.grants.revoke');
        Route::post('claims/backdate/requests',              [\AlphaDirect\Http\Controllers\Api\V1\BackdateControlController::class, 'submitRequest'])->name('claims.backdate.requests.submit');
        Route::post('claims/backdate/requests/{id}/decide',  [\AlphaDirect\Http\Controllers\Api\V1\BackdateControlController::class, 'decideRequest'])->whereNumber('id')->name('claims.backdate.requests.decide');

        // GRA-0155 — on-demand Infobip SMS-log export for a date range.
        // Generates the CSV on the fly (capped by infobip-sms.max_range_days),
        // writes it to S3 and returns a presigned URL. Gated by the
        // `sms-logs-download` permission (Spatie route middleware).
        Route::post('system/sms-exports/generate', [\AlphaDirect\Http\Controllers\Api\V1\SmsLogExportController::class, 'generate'])->name('system.smsExports.generate')->middleware('permission:sms-logs-download');

        // Quote actions
        Route::put('quotes/{id}/update', [QuoteController::class, 'update'])->name('quotes.update');
        Route::post('quotes/{id}/reject', [QuoteController::class, 'reject'])->name('quotes.reject');
        Route::post('quotes/{id}/update-premium', [QuoteController::class, 'updatePremium'])->name('quotes.updatePremium');
        Route::post('quotes/{id}/export', [QuoteController::class, 'export'])->name('quotes.export');
        Route::post('quotes/{id}/mark-used', [QuoteController::class, 'markUsed'])->name('quotes.markUsed');

        // Employer Groups mutations (V8 admin port). Create was previously in
        // the read group with an in-controller check — moved here with the
        // rest. Per-action Spatie permissions, seeded by
        // EmployerGroupPermissionsSeeder.
        Route::post('employer-groups', [EmployerGroupController::class, 'store'])
            ->middleware('permission:employer-group-create')->name('employerGroups.store');
        Route::put('employer-groups/{id}', [EmployerGroupController::class, 'update'])
            ->whereNumber('id')->middleware('permission:employer-group-edit')->name('employerGroups.update');
        Route::delete('employer-groups/{id}', [EmployerGroupController::class, 'destroy'])
            ->whereNumber('id')->middleware('permission:employer-group-delete')->name('employerGroups.destroy');
        Route::post('employer-groups/{id}/send-hr-credentials', [EmployerGroupController::class, 'sendHrCredentials'])
            ->whereNumber('id')->middleware('permission:employer-group-send-comms')->name('employerGroups.sendHrCredentials');
        Route::post('employer-groups/{id}/send-onboarding-email', [EmployerGroupController::class, 'sendOnboardingEmail'])
            ->whereNumber('id')->middleware('permission:employer-group-send-comms')->name('employerGroups.sendOnboardingEmail');

        // AD Group KYC campaign mutations — staff-only (role gate; no seeded
        // ad-group-kyc permission exists yet). Reads live in the read group.
        Route::middleware('role:Admin|admin|Super Admin')->group(function () {
            Route::post('kyc/ad-group/campaigns', [AdGroupKycController::class, 'storeCampaign'])->name('adGroupKyc.campaigns.store');
            Route::put('kyc/ad-group/campaigns/{id}', [AdGroupKycController::class, 'updateCampaign'])->whereNumber('id')->name('adGroupKyc.campaigns.update');
            Route::post('kyc/ad-group/campaigns/{id}/generate-links', [AdGroupKycController::class, 'generateLinks'])->whereNumber('id')->name('adGroupKyc.campaigns.generateLinks');
            Route::post('kyc/ad-group/campaigns/{id}/send-links', [AdGroupKycController::class, 'sendLinks'])->whereNumber('id')->name('adGroupKyc.campaigns.sendLinks');
            Route::post('kyc/ad-group/campaigns/{id}/notifications', [AdGroupKycController::class, 'bulkNotify'])->whereNumber('id')->name('adGroupKyc.campaigns.bulkNotify');
            Route::post('kyc/ad-group/campaigns/{id}/reminders', [AdGroupKycController::class, 'sendReminders'])->whereNumber('id')->name('adGroupKyc.campaigns.reminders');
            Route::post('kyc/ad-group/campaigns/{id}/escalations', [AdGroupKycController::class, 'sendEscalations'])->whereNumber('id')->name('adGroupKyc.campaigns.escalations');
            Route::post('kyc/ad-group/links/{id}/resend', [AdGroupKycController::class, 'resendLink'])->whereNumber('id')->name('adGroupKyc.links.resend');
        });

        // Cancel request actions
        Route::post('cancel-requests/{id}/approve', [CancelRequestController::class, 'approve'])->name('cancelRequests.approve');
        Route::post('cancel-requests/{id}/decline', [CancelRequestController::class, 'decline'])->name('cancelRequests.decline');
        // Immediate cancel from the policy view page (MIS retail products 1,2,3,4,5,9)
        Route::post('policies/{id}/cancel', [CancelRequestController::class, 'cancelImmediate'])->name('policies.cancelImmediate');
        // Resend the cancellation SMS / email for an already-cancelled policy —
        // parity with the old Edit Policy page's "Send policy cancelled
        // email/sms" buttons. type ∈ {email, sms}.
        Route::post('policies/{id}/resend-cancellation/{type}', [CancelRequestController::class, 'resendCancellationNotice'])->name('policies.resendCancellationNotice');

        // Soft Delete Item — Super Admin only (authorisation enforced in controller).
        // Soft-deletes a single record from a given table by ID (stamps deleted_at).
        Route::post('admin/soft-delete', [\AlphaDirect\Http\Controllers\Api\V1\SoftDeleteController::class, 'softDelete'])->name('admin.softDelete');
        // Assign Agent tab — update the policy's agent / store (old
        // admin.policy.agentUpdate parity).
        Route::post('policies/{id}/assign-agent', [PolicyController::class, 'assignAgent'])->name('policies.assignAgent');

        // Reconciliation actions
        Route::post('reconciliation/anomalies/export',           [ReconciliationController::class, 'export'])->name('reconciliation.export');
        Route::post('reconciliation/prune',                      [ReconciliationController::class, 'prune'])->name('reconciliation.prune');
        Route::post('reconciliation/anomalies/{id}/acknowledge', [ReconciliationController::class, 'acknowledge'])->name('reconciliation.acknowledge');
        Route::post('reconciliation/anomalies/{id}/resolve',     [ReconciliationController::class, 'resolve'])->name('reconciliation.resolve');
        Route::post('reconciliation/anomalies/{id}/false-positive', [ReconciliationController::class, 'markFalsePositive'])->name('reconciliation.falsePositive');

        // Policy creation wizard
        Route::post('policies',                                    [PolicyCreateController::class, 'store'])->name('policies.store');
        Route::put('policies/{id}',                                [PolicyCreateController::class, 'update'])->name('policies.update');
        // Note: GET policies/{id}/edit-data is also registered in the read group (line ~76) — that read-group
        // registration (with pii.mask) is preferred for real edits. Do NOT re-register here.
        Route::post('policies/{id}/risk-addresses',                [PolicyCreateController::class, 'addRiskAddress'])->name('policies.addRiskAddress');
        Route::put('policies/{id}/risk-addresses/{raId}',          [PolicyCreateController::class, 'updateRiskAddress'])->name('policies.updateRiskAddress');
        Route::delete('policies/{id}/risk-addresses/{raId}',       [PolicyCreateController::class, 'deleteRiskAddress'])->name('policies.deleteRiskAddress');
        Route::post('policies/{id}/risk-addresses/{raId}/reinstate', [PolicyCreateController::class, 'reinstateRiskAddress'])->name('policies.reinstateRiskAddress');
        Route::get('policies/{id}/risk-addresses-with-coverages',  [PolicyCreateController::class, 'getRiskAddressesWithCoverages'])->name('policies.riskAddressesWithCoverages');

        // Coverage management
        Route::post('policies/{id}/coverages',                     [PolicyCreateController::class, 'addCoverage'])->name('policies.addCoverage');
        Route::post('policies/{id}/sync-coverages',                  [PolicyCreateController::class, 'syncCoverages'])->name('policies.syncCoverages');
        Route::post('policies/{id}/coverage-masters/{masterId}/clone', [PolicyCreateController::class, 'cloneCoverageMaster'])->name('policies.cloneCoverageMaster');
        Route::put('policies/{id}/coverages/{covId}',              [PolicyCreateController::class, 'updateCoverage'])->name('policies.updateCoverage');
        Route::put('policies/{id}/coverages/{covId}/details/{detailId}', [PolicyCreateController::class, 'updateCoverageDetail'])->name('policies.updateCoverageDetail');
        Route::delete('policies/{id}/coverages/{covId}',                        [PolicyCreateController::class, 'deleteCoverage'])->name('policies.deleteCoverage');
        Route::post('policies/{id}/coverages/{covId}/reinstate',                [PolicyCreateController::class, 'reinstateCoverage'])->name('policies.reinstateCoverage');
        Route::post('policies/{id}/coverages/{covId}/details/{detailId}/reinstate', [PolicyCreateController::class, 'reinstateCoverageDetail'])->name('policies.reinstateCoverageDetail');
        // Motor vehicle management per coverage
        Route::get('policies/{id}/coverages/{covId}/motor',                  [PolicyCreateController::class, 'listMotorVehicles'])->name('policies.listMotor');
        Route::get('policies/{id}/coverages/{covId}/available-vehicles',     [PolicyCreateController::class, 'availableVehiclesForMotor'])->name('policies.availableVehicles');
        Route::post('policies/{id}/coverages/{covId}/motor',                 [PolicyCreateController::class, 'addMotorVehicle'])->name('policies.addMotor');
        Route::put('policies/{id}/coverages/{covId}/motor/{motorId}',        [PolicyCreateController::class, 'updateMotorVehicle'])->name('policies.updateMotor');
        Route::delete('policies/{id}/coverages/{covId}/motor/{motorId}',     [PolicyCreateController::class, 'deleteMotorVehicle'])->name('policies.deleteMotor');
        Route::post('policies/{id}/coverages/{covId}/motor/{motorId}/reinstate', [PolicyCreateController::class, 'reinstateMotorVehicle'])->name('policies.reinstateMotor');
        // Specialist (non-motor, non-COM/DOM) coverage cancel/reinstate.
        // {table} is one of the ten specialist tables (car_coverages,
        // par_coverages, ear_coverages, travel_coverages, medical_malpractice_
        // coverages, machinery_breakdown_coverages, professional_indemnity_
        // coverages, marine_directors_officers_coverages, marine_cargo_once_
        // off_coverages, marine_cargo_open_coverages); validated server-side.
        Route::post('policies/{id}/coverages/{covId}/specialist/{table}/{rowId}/cancel',    [PolicyCreateController::class, 'cancelSpecialistCoverage'])->name('policies.cancelSpecialistCoverage');
        Route::post('policies/{id}/coverages/{covId}/specialist/{table}/{rowId}/reinstate', [PolicyCreateController::class, 'reinstateSpecialistCoverage'])->name('policies.reinstateSpecialistCoverage');
        // Per-motor specified items
        Route::post('policies/{id}/coverages/{covId}/motor/{motorId}/specified-items',            [PolicyCreateController::class, 'addMotorSpecifiedItem'])->name('policies.addMotorSpecifiedItem');
        Route::put('policies/{id}/coverages/{covId}/motor/{motorId}/specified-items/{itemId}',    [PolicyCreateController::class, 'updateMotorSpecifiedItem'])->name('policies.updateMotorSpecifiedItem');
        Route::delete('policies/{id}/coverages/{covId}/motor/{motorId}/specified-items/{itemId}', [PolicyCreateController::class, 'deleteMotorSpecifiedItem'])->name('policies.deleteMotorSpecifiedItem');
        Route::post('policies/{id}/coverages/{covId}/motor/{motorId}/specified-items/{itemId}/reinstate', [PolicyCreateController::class, 'reinstateMotorSpecifiedItem'])->name('policies.reinstateMotorSpecifiedItem');
        Route::put('policies/{id}/coverages/{covId}/motor/{motorId}/note',                        [PolicyCreateController::class, 'upsertMotorNote'])->name('policies.upsertMotorNote');
        // Motor Traders Ext/Int — single-row-per-coverage CRUD. 32 fields each
        // covering loss/damage, third-party, medical, plus extensions and
        // minimum limits. Mirrors the legacy graphiteBWV8 manage-coverages
        // MotorTraders sections but exposed as discrete REST endpoints so the
        // React wizard can save independently of the monolithic submit() flow.
        Route::get('policies/{id}/coverages/{covId}/motor-traders-external',  [PolicyCreateController::class, 'getMotorTradersExternal'])->name('policies.getMotorTradersExternal');
        Route::put('policies/{id}/coverages/{covId}/motor-traders-external',  [PolicyCreateController::class, 'saveMotorTradersExternal'])->name('policies.saveMotorTradersExternal');
        Route::get('policies/{id}/coverages/{covId}/motor-traders-internal',  [PolicyCreateController::class, 'getMotorTradersInternal'])->name('policies.getMotorTradersInternal');
        Route::put('policies/{id}/coverages/{covId}/motor-traders-internal',  [PolicyCreateController::class, 'saveMotorTradersInternal'])->name('policies.saveMotorTradersInternal');
        Route::delete('policies/{id}/coverages/{covId}/details/{detailId}',  [PolicyCreateController::class, 'deleteCoverageDetail'])->name('policies.deleteCoverageDetail');
        Route::delete('policies/{id}/coverages/{covId}/extensions/{extId}',  [PolicyCreateController::class, 'deleteExtension'])->name('policies.deleteExtension');
        Route::get('policies/{id}/coverages/{covId}/specified-items',             [PolicyCreateController::class, 'listSpecifiedItems'])->name('policies.listSpecifiedItems');
        Route::post('policies/{id}/coverages/{covId}/specified-items',            [PolicyCreateController::class, 'addSpecifiedItem'])->name('policies.addSpecifiedItem');
        Route::delete('policies/{id}/coverages/{covId}/specified-items/{itemId}', [PolicyCreateController::class, 'deleteSpecifiedItem'])->name('policies.deleteSpecifiedItem');
        Route::put('policies/{id}/coverages/{covId}/specified-items/{itemId}',    [PolicyCreateController::class, 'updateSpecifiedItem'])->name('policies.updateSpecifiedItem');
        Route::post('policies/{id}/calculate-premium',             [PolicyCreateController::class, 'calculatePremium'])->name('policies.calculatePremium');

        // Notifications
        Route::get('notifications',                [NotificationController::class, 'index'])->name('notifications.index');
        Route::put('notifications/{id}/read',      [NotificationController::class, 'markRead'])->name('notifications.markRead');
        Route::put('notifications/{id}/unread',    [NotificationController::class, 'markUnread'])->name('notifications.markUnread');
        Route::post('notifications/mark-all-read', [NotificationController::class, 'markAllRead'])->name('notifications.markAllRead');
        Route::get('document-jobs',                [NotificationController::class, 'documentJobs'])->name('documentJobs');
        Route::get('document-jobs/error-log/{errorLogId}', [NotificationController::class, 'errorLogDetail'])->name('documentJobs.errorLog');
        Route::post('document-jobs/{id}/cancel',   [NotificationController::class, 'cancelDocumentJob'])->name('documentJobs.cancel');
        Route::post('document-jobs/{id}/retry',    [NotificationController::class, 'retryDocumentJob'])->name('documentJobs.retry');

        // Policy documents (Quote Sheet + Policy Document)
        Route::post('policies/{id}/generate-quote-pdf',            [PolicyCreateController::class, 'generateQuotePdf'])->name('policies.generateQuotePdf')->middleware('policy.action:canPrintQuote');
        Route::get('policies/{id}/quote-pdf-status/{jobId}',       [PolicyCreateController::class, 'quotePdfStatus'])->name('policies.quotePdfStatus')->middleware('throttle:60,1'); // 60 requests per minute for polling
        // Cross-user awareness — latest V2 Quote Sheet job for THIS policy,
        // regardless of which user created it. Polled every 5s by every open
        // PolicyDetailPage so all viewers see the same "Started by X" banner.
        // Throttle 120/min covers a user with up to ~10 tabs open on
        // different policies polling every 5s.
        Route::get('policies/{id}/v2-quote/latest',                [PolicyCreateController::class, 'v2QuoteLatestForPolicy'])->name('policies.v2QuoteLatestForPolicy')->middleware('throttle:120,1');
        Route::get('policies/{id}/download-quote-pdf/{jobId}',     [PolicyCreateController::class, 'downloadQuotePdf'])->name('policies.downloadQuotePdf');
        // Stored generated PDFs (Policy Document / V2 Quote Sheet) per
        // action_id — Documents tab lists them for direct download.
        Route::get('policies/{id}/policy-documents',               [PolicyCreateController::class, 'listGeneratedPolicyDocuments'])->name('policies.listGeneratedPolicyDocuments');
        Route::get('system/static-pdfs/health',                    [PolicyCreateController::class, 'staticPdfsHealth'])->name('system.staticPdfsHealth');
        Route::post('policies/{id}/generate-policy-document',      [PolicyCreateController::class, 'generatePolicyDocument'])->name('policies.generatePolicyDocument')->middleware('policy.action:canPrintApp');
        // One-page cover sheet with the client-access QR. An ADDITIONAL
        // document — generate-policy-document and the full pack are unchanged.
        // Permission-gated inside the controller on `policy-cover-sheet-share`
        // (seeded by 2026_09_08_143000), so it can be granted role-wise or to
        // an individual user without making them an admin.
        Route::post('policies/{id}/cover-sheet',                    [PolicyCreateController::class, 'generateCoverSheet'])->name('policies.generateCoverSheet')->middleware('throttle:20,1');
        // Staging-only OTP readout for testers. Returns an empty payload on
        // production — see CoverSheetAccessService::mayShowCodeOnScreen().
        Route::get('policies/{id}/cover-sheet/otp',                 [PolicyCreateController::class, 'coverSheetOtp'])->name('policies.coverSheetOtp')->middleware('throttle:60,1');
        Route::post('policies/{id}/generate-policy-documents-range', [PolicyCreateController::class, 'generatePolicyDocumentsRange'])->name('policies.generatePolicyDocumentsRange')->middleware('policy.action:canPrintApp');
        Route::post('policies/{id}/send-documents',                [PolicyCreateController::class, 'sendDocuments'])->name('policies.sendDocuments');
        Route::post('policies/{id}/generate-v2-quote-sheet',     [PolicyCreateController::class, 'generateV2QuoteSheet'])->name('policies.generateV2QuoteSheet');
        Route::post('policies/{id}/generate-rate-sheet',         [PolicyCreateController::class, 'generateRateSheet'])->name('policies.generateRateSheet');

        // Policy submit/issue/action workflow
        Route::post('policies/{id}/submit-approval',               [PolicyCreateController::class, 'submitToApproval'])->name('policies.submitApproval')->middleware('policy.action:canSubmitUnbound');
        Route::post('policies/{id}/approve',                       [PolicyCreateController::class, 'approvePolicy'])->name('policies.approve');
        Route::post('policies/{id}/reject',                        [PolicyCreateController::class, 'rejectPolicy'])->name('policies.reject');
        Route::post('policies/{id}/issue',                         [PolicyCreateController::class, 'issuePolicy'])->name('policies.issue')->middleware('policy.action:canIssue');
        Route::post('policies/{id}/unissue',                       [PolicyCreateController::class, 'unissuePolicy'])->name('policies.unissue');
        Route::post('policies/{id}/ntu',                           [PolicyCreateController::class, 'markPolicyNTU'])->name('policies.ntu');
        Route::post('policies/{id}/lapse',                         [PolicyCreateController::class, 'lapsePolicy'])->name('policies.lapse');
        Route::post('policies/{id}/delete-action',                 [PolicyCreateController::class, 'deleteAction'])->name('policies.deleteAction');
        Route::post('policies/{id}/delete-renew',                  [PolicyCreateController::class, 'deleteRenew'])->name('policies.deleteRenew');
        // Super Admin only: re-rate a RENEW-ISSUED action and sync its invoice
        // amount to the rated premium (role re-checked in the controller).
        Route::post('policies/{id}/rate-renew-invoice',            [PolicyCreateController::class, 'rateRenewInvoice'])->name('policies.rateRenewInvoice');
        // DOM/COM Batch Renew (Super Admin / Admin) — manual trigger for the
        // monthly / quarterly / anniversary renewal crons, optionally for a
        // single policy via --policy.
        Route::post('dom-com-batch-renew/run',                     [DomComBatchRenewController::class, 'run'])->name('domcom.batchRenew.run');
        Route::post('policies/{id}/refresh-endorse',               [PolicyCreateController::class, 'refreshEndorse'])->name('policies.refreshEndorse');
        // READ-ONLY preview of the backdated-endorse forward propagation (no
        // writes). Admin / Super Admin only (gated inside the controller).
        Route::post('policies/{id}/refresh-endorse/dry-run',       [PolicyCreateController::class, 'refreshEndorseDryRun'])->name('policies.refreshEndorseDryRun');
        Route::get('policies/{id}/refresh-endorse/status',         [PolicyCreateController::class, 'refreshEndorseStatus'])->name('policies.refreshEndorseStatus');
        // Refresh Endorsement Range (Super Admin / Admin) — bounded from → to
        // rebuild driven from a standalone admin page. action-lookup previews
        // the typed From/To actions before running the destructive rebuild.
        Route::get('policies/action-lookup/{actionId}',            [PolicyCreateController::class, 'actionLookup'])->name('policies.actionLookup')->where('actionId', '[0-9]+');
        // Read-only count of how many actions a range fill/rebuild would touch —
        // feeds the "N action(s) will refresh" line in the confirm dialog.
        Route::post('policies/refresh-endorse-range/preview',      [PolicyCreateController::class, 'refreshEndorseRangePreview'])->name('policies.refreshEndorseRangePreview');
        Route::post('policies/refresh-endorse-range',              [PolicyCreateController::class, 'refreshEndorseRange'])->name('policies.refreshEndorseRange');
        Route::post('policies/{id}/kyc-documents',                 [PolicyCreateController::class, 'uploadKycDocuments'])->name('policies.uploadKycDocuments');
        // V8 parity — per-doc clear used by the policy KYC upload grid's
        // trash icon. {field} accepts canonical KYC_DOC_CATALOGUE keys + legacy aliases.
        Route::delete('policies/{id}/kyc-documents/{field}',       [PolicyCreateController::class, 'deleteKycDocument'])->name('policies.deleteKycDocument')->where('field', '[A-Za-z0-9_]+');
        Route::post('policies/{id}/new-transaction',               [PolicyCreateController::class, 'newTransaction'])->name('policies.newTransaction');
        Route::post('policies/{id}/endorse',                       [PolicyCreateController::class, 'endorsePolicy'])->name('policies.endorse');
        Route::post('policies/{id}/renew-create',                  [PolicyCreateController::class, 'renewPolicyCreate'])->name('policies.renewCreate');
        // High-risk customer yearly renewal — all products EXCEPT Motor Comp (3).
        Route::post('policies/{id}/high-risk-renew',               [PolicyCreateController::class, 'highRiskRenew'])->name('policies.highRiskRenew');
        Route::post('policies/{id}/reinstate-create',              [PolicyCreateController::class, 'reinstatePolicyCreate'])->name('policies.reinstateCreate')->middleware('permission:policy_reinstate');
        // Status-based reinstate for policies cancelled outside the V2
        // transaction workflow (legacy / MIS retail — status=2, no ISSUED
        // CANCEL). Gated by the same permission as legacy reinstatement.
        Route::post('policies/{id}/reinstate-legacy',              [PolicyCreateController::class, 'reinstateLegacyPolicy'])->name('policies.reinstateLegacy')->middleware('permission:policy_reinstate');
        // GRA-0194 — cancel the LIVE DPO mandate on a migrated-but-active
        // policy (DPO→RealPay double-debit fix) WITHOUT cancelling the policy.
        // Gated by the same permission as the legacy manual DPO-suspend
        // provision (V1 policy-suspend_payment_dpo).
        Route::post('policies/{id}/cancel-dpo-contract',           [PolicyCreateController::class, 'cancelDpoContract'])->name('policies.cancelDpoContract')->middleware('permission:policy-suspend_payment_dpo');
        Route::post('policies/{id}/renewal-link',                  [PolicyCreateController::class, 'generateRenewalLink'])->name('policies.renewalLink');
        Route::put('policies/{id}/actions/{actionId}',             [PolicyCreateController::class, 'updateTransaction'])->name('policies.updateTransaction');
        // Set the premium frequency ON ONE ACTION (policy_actions.current_frequency_id),
        // with an opt-in flag to push the same value to policies.premium_freq.
        // Super Admin only: it is the repair tool for the stamps the old EditWizard
        // write path corrupted, and there is no SQL access from the ops portal.
        Route::patch('policies/{id}/actions/{actionId}/frequency', [PolicyController::class, 'updateActionFrequency'])->name('policies.updateActionFrequency')->middleware('role:Super Admin');
        // READ-ONLY Endorse/Cancel change-summary diagnostic — reuses the Livewire
        // EndorseChangeSummary logic so the React popup matches the edit-wizard one.
        Route::get('policies/{id}/actions/{actionId}/change-summary', [EndorseChangeSummaryController::class, 'show'])->name('policies.changeSummary');
        Route::get('policies/{id}/invoice/{ledgerId}',             [PolicyCreateController::class, 'generateInvoicePdf'])->name('policies.invoicePdf');

        // Invoice Credit Note — the V2 replacement for the legacy CR screen at
        // /admin/policy/creditNoteView/{id}, which 404s on this domain because
        // BlockV1AdminPanel gates the whole /admin/* tree (Prathap UAT §2.2).
        // Gated on policy_credit_note — an EXISTING permission (id 431), already
        // used by the legacy ledger view's @can. No seed migration ships with this
        // feature: assign it to the roles that need it (Finance / Accounts /
        // Debtors / Payables raise credit notes in practice) from the Roles &
        // Permissions screen. Live currently has it on Super Admin only, so
        // everyone else gets 403 until it is attached.
        Route::get('policies/{id}/invoice/{ledgerId}/credit-note',            [CreditNoteController::class, 'show'])->middleware('permission:policy_credit_note')->name('policies.creditNote.show');
        Route::post('policies/{id}/invoice/{ledgerId}/credit-note/calculate', [CreditNoteController::class, 'calculate'])->middleware('permission:policy_credit_note')->name('policies.creditNote.calculate');
        Route::post('policies/{id}/invoice/{ledgerId}/credit-note',           [CreditNoteController::class, 'store'])->middleware('permission:policy_credit_note')->name('policies.creditNote.store');
        // Re-date an already-posted note — the only way to correct the notes that
        // were stamped with now() before the posting date became selectable.
        Route::patch('policies/{id}/invoice/{ledgerId}/credit-note/date',      [CreditNoteController::class, 'updateDate'])->middleware('permission:policy_credit_note')->name('policies.creditNote.updateDate');
        // The credit note DOCUMENT, keyed by credit_note.id rather than by invoice
        // — the Credit Notes tab lists notes, not invoices. Deliberately NOT gated
        // on policy_credit_note: RAISING a note is the privileged act; reading one
        // that already exists is not, and the tab already showed the CloudFront
        // link to everyone who can see the ledger.
        Route::get('policies/{id}/credit-note/{creditNoteId}/pdf',             [CreditNoteController::class, 'pdf'])->name('policies.creditNote.pdf');
        Route::get('policies/{id}/account-statement-pdf',          [PolicyCreateController::class, 'generateAccountStatementPdf'])->name('policies.accountStatementPdf');
        Route::get('policies/{id}/complaint-procedure-pdf',        [PolicyCreateController::class, 'downloadComplaintProcedure'])->name('policies.complaintProcedurePdf');
        Route::get('policies/{id}/policy-wording-pdf',             [PolicyCreateController::class, 'downloadPolicyWording'])->name('policies.policyWordingPdf');
        Route::post('policies/{id}/generate-cover-note',           [PolicyCreateController::class, 'generateCoverNote'])->name('policies.generateCoverNote');
        Route::post('policies/{id}/generate-cancel-note',          [PolicyCreateController::class, 'generateCancelNote'])->name('policies.generateCancelNote');
        Route::post('policies/{id}/create-term',                   [PolicyCreateController::class, 'createTerm'])->name('policies.createTerm');

        // Extensions
        Route::get('policies/{id}/extensions',                     [PolicyCreateController::class, 'getExtensions'])->name('policies.extensions');

        // Members
        Route::post('policies/{id}/members',                       [PolicyCreateController::class, 'addMember'])->name('policies.addMember');
        Route::put('policies/{id}/members/{memberId}',             [PolicyCreateController::class, 'updateMember'])->name('policies.updateMember');
        Route::delete('policies/{id}/members/{memberId}',          [PolicyCreateController::class, 'deleteMember'])->name('policies.deleteMember');

        // Hospital Cashback Co-Applicants — distinct from the generic Members
        // CRUD above; writes go through HcbCoapplicantService so premium
        // recalculates immediately. Gated separately since it mutates billing.
        Route::get('policies/{id}/coapplicants',                      [PolicyCreateController::class, 'listCoapplicants'])->name('policies.listCoapplicants');
        Route::post('policies/{id}/coapplicants',                     [PolicyCreateController::class, 'addCoapplicant'])->name('policies.addCoapplicant')->middleware('permission:hcb-coapplicants-manage');
        Route::put('policies/{id}/coapplicants/{coapplicantId}',      [PolicyCreateController::class, 'updateCoapplicant'])->name('policies.updateCoapplicant')->middleware('permission:hcb-coapplicants-manage');
        Route::delete('policies/{id}/coapplicants/{coapplicantId}',   [PolicyCreateController::class, 'deleteCoapplicant'])->name('policies.deleteCoapplicant')->middleware('permission:hcb-coapplicants-manage');

        // Beneficiaries
        Route::post('policies/{id}/beneficiaries',                 [PolicyCreateController::class, 'addBeneficiary'])->name('policies.addBeneficiary');
        Route::put('policies/{id}/beneficiaries/{beneficiaryId}',  [PolicyCreateController::class, 'updateBeneficiary'])->name('policies.updateBeneficiary');
        Route::delete('policies/{id}/beneficiaries/{beneficiaryId}',[PolicyCreateController::class, 'deleteBeneficiary'])->name('policies.deleteBeneficiary');

        // Vehicles
        Route::post('policies/{id}/vehicles',                      [PolicyCreateController::class, 'addVehicle'])->name('policies.addVehicle');
        Route::put('policies/{id}/vehicles/{vehicleId}',           [PolicyCreateController::class, 'updateVehicle'])->name('policies.updateVehicle');
        Route::delete('policies/{id}/vehicles/{vehicleId}',        [PolicyCreateController::class, 'deleteVehicle'])->name('policies.deleteVehicle');

        // Devices
        Route::post('policies/{id}/devices',                       [PolicyCreateController::class, 'addDevice'])->name('policies.addDevice');
        Route::put('policies/{id}/devices/{deviceId}',             [PolicyCreateController::class, 'updateDevice'])->name('policies.updateDevice');
        Route::delete('policies/{id}/devices/{deviceId}',          [PolicyCreateController::class, 'deleteDevice'])->name('policies.deleteDevice');

        // Attachments
        Route::post('policies/{id}/attachments',                   [PolicyCreateController::class, 'uploadAttachment'])->name('policies.uploadAttachment');
        Route::delete('policies/{id}/attachments/{attachId}',      [PolicyCreateController::class, 'deleteAttachment'])->name('policies.deleteAttachment');

        // Banking
        Route::put('policies/{id}/banking',                        [PolicyCreateController::class, 'updateBanking'])->name('policies.updateBanking');
        // Banking documents (Bank Statement + Debit Authorization Form) — Banking Details tab
        Route::get('policies/{id}/banking-documents',              [PolicyCreateController::class, 'bankingDocuments'])->name('policies.bankingDocuments');
        Route::post('policies/{id}/banking-documents',             [PolicyCreateController::class, 'uploadBankingDocuments'])->name('policies.uploadBankingDocuments');
        Route::post('policies/{id}/banking-documents/verify',      [PolicyCreateController::class, 'verifyBankingDocument'])->name('policies.verifyBankingDocument');
        Route::delete('policies/{id}/banking-documents/{field}',   [PolicyCreateController::class, 'deleteBankingDocument'])->name('policies.deleteBankingDocument')->where('field', '[A-Za-z0-9_]+');

        // No Claims Declaration (MIS policies only) — No Claims Declaration tab.
        // URLs keep the pre-rename `claims-waiver` path; label-only rename.
        Route::get('policies/{id}/claims-waiver',                  [PolicyCreateController::class, 'claimsWaiver'])->name('policies.claimsWaiver');
        Route::post('policies/{id}/claims-waiver',                 [PolicyCreateController::class, 'uploadClaimsWaiver'])->name('policies.uploadClaimsWaiver');
        Route::delete('policies/{id}/claims-waiver',               [PolicyCreateController::class, 'deleteClaimsWaiver'])->name('policies.deleteClaimsWaiver');
        // Approve / reject the declaration. Gated by Services\ClaimsWaiverApprovalGate
        // (the approver ROLE only — no admin bypass), not by the upload permission:
        // Underwriting uploads, a named approver signs off.
        Route::post('policies/{id}/claims-waiver/approve',          [PolicyCreateController::class, 'approveClaimsWaiver'])->name('policies.approveClaimsWaiver');
        // OTP e-signature (2026-09-08): SMS a code to the customer's registered
        // number; a verified code generates the signed PDF in place of a scan.
        // Throttled per user+IP like the public OTP routes — an SMS costs money.
        Route::post('policies/{id}/claims-waiver/otp/send',         [PolicyCreateController::class, 'sendClaimsWaiverOtp'])->middleware('throttle:5,1')->name('policies.claimsWaiverOtpSend');
        Route::post('policies/{id}/claims-waiver/otp/verify',       [PolicyCreateController::class, 'verifyClaimsWaiverOtp'])->middleware('throttle:20,1')->name('policies.claimsWaiverOtpVerify');

        // Specialist Coverage CRUD (EAR / CAR / PAR / Medical Malpractice / Professional Indemnity)
        Route::get('policies/{id}/specialist-coverages/{type}',              [SpecialistCoverageController::class, 'show'])->name('policies.specialistCoverage.show');
        Route::post('policies/{id}/specialist-coverages/{type}',             [SpecialistCoverageController::class, 'store'])->name('policies.specialistCoverage.store');
        Route::put('policies/{id}/specialist-coverages/{type}/{recordId}',   [SpecialistCoverageController::class, 'update'])->name('policies.specialistCoverage.update');
        Route::delete('policies/{id}/specialist-coverages/{type}/{recordId}',[SpecialistCoverageController::class, 'destroy'])->name('policies.specialistCoverage.destroy');
        Route::post('policies/{id}/specialist-coverages/{type}/{recordId}/approve-period', [SpecialistCoverageController::class, 'approvePeriod'])->name('policies.specialistCoverage.approvePeriod');
        // Bonds only — EXCO confirmation that the collateral on the schedule is
        // actually held. Permission is re-checked in the controller
        // (`bonds-approve`), which is also the Bonds approve/issue gate.
        Route::post('policies/{id}/specialist-coverages/{type}/{recordId}/confirm-collateral', [SpecialistCoverageController::class, 'confirmCollateral'])->name('policies.specialistCoverage.confirmCollateral');
        Route::post('policies/{id}/specialist-coverages/{type}/upload-wording',          [SpecialistCoverageController::class, 'uploadWording'])->name('policies.specialistCoverage.uploadWording');
        Route::get('policies/{id}/specialist-coverages/{type}/download-wording/{recordId}', [SpecialistCoverageController::class, 'downloadWording'])->name('policies.specialistCoverage.downloadWording');

        // Bonds only — collateral PROOF documents (bank guarantee, cession,
        // deposit receipt). Anyone may upload; only EXCO (`bonds-approve`,
        // re-checked in the controller) may approve or reject, and Issue is
        // blocked until an approved document exists for the action being
        // issued (Services\Bonds\BondsIssuanceGate::collateralDocumentBlocker).
        Route::get('policies/{id}/bonds/collateral-documents',                    [BondsCollateralDocumentController::class, 'index'])->name('policies.bondsCollateralDocs.index');
        Route::post('policies/{id}/bonds/collateral-documents',                   [BondsCollateralDocumentController::class, 'store'])->name('policies.bondsCollateralDocs.store');
        Route::post('policies/{id}/bonds/collateral-documents/{docId}/approve',   [BondsCollateralDocumentController::class, 'approve'])->name('policies.bondsCollateralDocs.approve');
        Route::delete('policies/{id}/bonds/collateral-documents/{docId}',         [BondsCollateralDocumentController::class, 'destroy'])->name('policies.bondsCollateralDocs.destroy');
        Route::get('policies/{id}/bonds/collateral-documents/{docId}/download',   [BondsCollateralDocumentController::class, 'download'])->name('policies.bondsCollateralDocs.download');

        // Excel import (write: parses the uploaded file and inserts rows into the DB)
        Route::post('policies/{id}/excel-import/{type}',           [PolicyExcelController::class, 'importData'])->name('policies.excelImport');

        // Claims — mutations require claim-create / claim-edit. Read-only roles
        // (claim-list only, e.g. Finance claims review) must be blocked here,
        // not just in the UI. GET debug/data-dump/edit-history stay read-open.
        Route::post('claims',                                      [ClaimsController::class, 'store'])->name('claims.store')->middleware('permission:claim-create');
        Route::put('claims/{id}',                                   [ClaimsController::class, 'update'])->name('claims.update')->middleware('permission:claim-edit');
        Route::post('claims/{id}/relink-policy',                    [ClaimsController::class, 'relinkPolicy'])->name('claims.relinkPolicy')->middleware('permission:claim-edit');
        Route::get('claims/{id}/debug-linkage',                     [ClaimsController::class, 'debugLinkage'])->name('claims.debugLinkage');
        Route::get('claims/{id}/data-dump',                         [ClaimsController::class, 'dataDump'])->name('claims.dataDump');
        Route::get('claims/{id}/edit-history',                      [ClaimsController::class, 'editHistory'])->name('claims.editHistory');
        Route::patch('claims/{id}/status',                         [ClaimsController::class, 'updateStatus'])->name('claims.updateStatus')->middleware('permission:claim-edit');

        // Claims V2 — write
        Route::post('claims-v2',                                       [ClaimsV2Controller::class, 'store'])->name('claimsV2.store')->middleware('permission:claim-create');
        Route::put('claims-v2/{id}',                                   [ClaimsV2Controller::class, 'update'])->name('claimsV2.update')->middleware('permission:claim-edit');
        Route::patch('claims-v2/{id}/status',                          [ClaimsV2Controller::class, 'updateStatus'])->name('claimsV2.updateStatus')->middleware('permission:claim-edit');
        Route::post('claims-v2/{id}/reserves',                         [ClaimsV2Controller::class, 'storeReserve'])->name('claimsV2.storeReserve')->middleware('permission:claim-edit');
        Route::post('claims-v2/{id}/reserves/{reserveId}/void',        [ClaimsV2Controller::class, 'voidPayment'])->name('claimsV2.voidPayment')->middleware('permission:claim-edit');
        Route::post('claims-v2/{id}/documents',                        [ClaimsV2Controller::class, 'uploadDocument'])->name('claimsV2.uploadDocument')->middleware('permission:claim-edit');
        Route::delete('claims-v2/{id}/documents/{docId}',              [ClaimsV2Controller::class, 'deleteDocument'])->name('claimsV2.deleteDocument')->middleware('permission:claim-edit');
        Route::post('claims/{id}/review-notes',                        [ClaimsV2Controller::class, 'storeReviewNote'])->name('claims.storeReviewNote')->middleware('permission:claim-edit');
        Route::put('claims/{id}/comment-status',                       [ClaimsV2Controller::class, 'setCommentStatus'])->name('claims.setCommentStatus')->middleware('permission:claim-edit');
        Route::post('claims-v2/{id}/closing-documents',                [ClaimsV2Controller::class, 'uploadClosingDocuments'])->name('claimsV2.uploadClosingDocuments')->middleware('permission:claim-edit');
        Route::post('claims-v2/{id}/assessment',                       [ClaimsV2Controller::class, 'storeAssessment'])->name('claimsV2.storeAssessment')->middleware('permission:claim-edit');
        // assessor_tab included: Analytics/Debtors upload assessor reports but
        // don't hold claim-edit (verified against PROD role grants 2026-07-10).
        Route::post('claims/{id}/assessor-upload',                     [ClaimsController::class, 'assessorUpload'])->name('claims.assessorUpload')->middleware('permission:claim-edit|assessor_tab');
        Route::post('claims-v2/{id}/third-parties',                    [ClaimsV2Controller::class, 'storeThirdParty'])->name('claimsV2.storeThirdParty')->middleware('permission:claim-edit');
        Route::delete('claims-v2/{id}/third-parties/{tpId}',           [ClaimsV2Controller::class, 'deleteThirdParty'])->name('claimsV2.deleteThirdParty')->middleware('permission:claim-edit');
        Route::post('claims-v2/{id}/quotes',                           [ClaimsV2Controller::class, 'storeQuote'])->name('claimsV2.storeQuote')->middleware('permission:claim-edit');
        Route::post('claims-v2/{id}/quotes/{quoteId}/accept',          [ClaimsV2Controller::class, 'acceptQuote'])->name('claimsV2.acceptQuote')->middleware('permission:claim-edit');
        // Claim decision workflow — writes only to the additive claim_decisions
        // table (+ activity_log); never touches claims.status. RBAC + flag gate
        // are enforced inside ClaimDecisionController.
        Route::post('claims-v2/{id}/decision',                         [ClaimDecisionController::class, 'decide'])->whereNumber('id')->name('claimsV2.decision.decide');
        Route::post('claims-v2/{id}/decision/reverse',                 [ClaimDecisionController::class, 'reverse'])->whereNumber('id')->name('claimsV2.decision.reverse');

        // ─── Document Scanning / OCR ─────────────────────────────────────────
        Route::post('ocr/parse',                             [\AlphaDirect\Http\Controllers\Api\V1\OcrController::class, 'parse']);
        Route::post('ocr/upload',                            [\AlphaDirect\Http\Controllers\Api\V1\OcrController::class, 'upload']);
        Route::get('ocr/policy-documents/{policyId}',        [\AlphaDirect\Http\Controllers\Api\V1\OcrController::class, 'policyDocuments']);

        // ─── AML / Sanctions screening (synchronous) ────────────────────────
        // Wraps OpenSanctionsClient::match so customer-facing flows can
        // block policy submission on sanction hits before issuance. Async
        // batch screening still runs via the WeeklyAmlScreening cron.
        Route::post('aml/check',                             [\AlphaDirect\Http\Controllers\Api\V1\AmlController::class, 'check'])->name('aml.check');

        // ─── Collect Now (Pay Now) ────────────────────────────────────────────────
        // Trigger immediate premium debit via DPO or RealPay from the admin UI.
        // GET  collect-now/summary                   — dashboard stats
        // GET  collect-now/history/{policyId}        — per-policy event history
        // GET  policies/{id}/collect-now/outstanding — selectable outstanding premiums
        // POST policies/{id}/collect-now             — trigger collection
        Route::get('collect-now/summary',          [CollectNowController::class, 'summary'])->name('collectNow.summary');
        Route::get('collect-now/history/{policyId}',[CollectNowController::class, 'history'])->name('collectNow.history');
        Route::get('policies/{id}/collect-now/outstanding', [CollectNowController::class, 'outstanding'])->name('policies.collectNow.outstanding');
        Route::post('policies/{id}/collect-now',   [CollectNowController::class, 'collectNow'])->name('policies.collectNow');

        // Payments — write (offline cash payment, now supports proof upload + V8 fields)
        Route::post('payments/{policyId}/offline', [PaymentController::class, 'offlinePayment'])->name('payments.offline');

        // Per-policy RealPay write (Policy Details > Add Realpay Contract tab)
        Route::post('policies/{policyId}/realpay/contracts', [PolicyRealpayController::class, 'createContract'])->name('policies.realpay.create');
        Route::post('policies/{policyId}/realpay/contracts/{contractId}/cancel', [PolicyRealpayController::class, 'cancelContract'])->name('policies.realpay.cancel');

        // Rerate Premium writes (MIS Motor Comprehensive) — gated by the
        // 'Premium-Rerate Premium' permission. Two-step: recalculate previews a
        // PENDING rate; accept commits it. Discount/surcharge + custom-rate
        // adjust the previewed annual premium before accept.
        Route::middleware('permission:Premium-Rerate Premium')->group(function () {
            Route::post('policies/{id}/rerate-premium/recalculate',        [PolicyReratePremiumController::class, 'recalculate'])->name('policies.rerate.recalculate');
            Route::post('policies/{id}/rerate-premium/discount-surcharge', [PolicyReratePremiumController::class, 'applyDiscountSurcharge'])->name('policies.rerate.discountSurcharge');
            Route::post('policies/{id}/rerate-premium/custom-rate',        [PolicyReratePremiumController::class, 'applyCustomRate'])->name('policies.rerate.customRate');
            Route::post('policies/{id}/rerate-premium/accept',             [PolicyReratePremiumController::class, 'accept'])->name('policies.rerate.accept');
        });

        // Per-policy transaction-log reverse + refund (Policy Details > Transaction Logs tab)
        // V8 parity: the trash icons in V8 actually trigger reverse, not delete.
        Route::post('policies/{policyId}/transaction-logs/{txnId}/reverse', [PaymentController::class, 'reverseTransaction'])->name('policies.transactionLogs.reverse');
        Route::post('policies/{policyId}/transaction-logs/refund',          [PaymentController::class, 'refundTransaction'])->name('policies.transactionLogs.refund');

        // ─── AI Assistant ──────────────────────────────────────────────────────
        Route::post('ai/query',                  [AiAssistantController::class, 'query']);
        Route::get('ai/conversations',           [AiAssistantController::class, 'conversations']);
        Route::post('ai/conversations',          [AiAssistantController::class, 'newConversation']);
        Route::get('ai/conversations/{id}',      [AiAssistantController::class, 'getConversation']);
        Route::delete('ai/conversations/{id}',   [AiAssistantController::class, 'deleteConversation']);

        // ─── Commissions — write ────────────────────────────────────────────────
        Route::post('commission/rules',                          [CommissionController::class, 'storeRule'])->name('commission.storeRule');
        Route::put('commission/rules/{id}',                      [CommissionController::class, 'updateRule'])->name('commission.updateRule');
        Route::delete('commission/rules/{id}',                   [CommissionController::class, 'deleteRule'])->name('commission.deleteRule');
        Route::post('commission/targets',                        [CommissionController::class, 'storeTarget'])->name('commission.storeTarget');
        Route::put('commission/targets/{id}',                    [CommissionController::class, 'updateTarget'])->name('commission.updateTarget');
        Route::delete('commission/targets/{id}',                 [CommissionController::class, 'deleteTarget'])->name('commission.deleteTarget');
        Route::post('commission/ledger/bulk-approve',            [CommissionController::class, 'bulkApprove'])->name('commission.bulkApprove');
        Route::post('commission/ledger/{id}/approve',            [CommissionController::class, 'approveLedgerEntry'])->name('commission.approveLedger');
        Route::post('commission/fraud-alerts/{id}/review',       [CommissionController::class, 'reviewFraudAlert'])->name('commission.reviewFraudAlert');
        Route::post('commission/run',                            [CommissionController::class, 'runCalculation'])->name('commission.run');

        // Policy Clone
        Route::post('policies/{id}/clone',                       [PolicyCreateController::class, 'clonePolicy'])->name('policies.clone');
        Route::get('commission/runs',                            [CommissionController::class, 'runs'])->name('commission.runs');

        // ─── Reinsurance — write ────────────────────────────────────────────────
        Route::post('reinsurance/types',                  [ReinsuranceApiController::class, 'storeType'])->name('reinsurance.storeType');
        Route::put('reinsurance/types/{id}',              [ReinsuranceApiController::class, 'updateType'])->name('reinsurance.updateType');
        Route::delete('reinsurance/types/{id}',           [ReinsuranceApiController::class, 'destroyType'])->name('reinsurance.destroyType');

        Route::post('reinsurance/treaties',               [ReinsuranceApiController::class, 'storeTreaty'])->name('reinsurance.storeTreaty');
        Route::put('reinsurance/treaties/{id}',           [ReinsuranceApiController::class, 'updateTreaty'])->name('reinsurance.updateTreaty');
        Route::delete('reinsurance/treaties/{id}',        [ReinsuranceApiController::class, 'destroyTreaty'])->name('reinsurance.destroyTreaty');

        Route::post('reinsurance/formulas',               [ReinsuranceApiController::class, 'storeFormula'])->name('reinsurance.storeFormula');
        Route::put('reinsurance/formulas/{id}',           [ReinsuranceApiController::class, 'updateFormula'])->name('reinsurance.updateFormula');
        Route::delete('reinsurance/formulas/{id}',        [ReinsuranceApiController::class, 'destroyFormula'])->name('reinsurance.destroyFormula');
        Route::post('reinsurance/formulas/initialize-names', [ReinsuranceApiController::class, 'initializeFormulaNames'])->name('reinsurance.initializeFormulaNames');

        // ─── FAC Register — writes ────────────────────────────────────────────
        // Deliberately split by permission. Capture is one right; signing off a
        // settlement, cancelling a placement, sending a slip to a reinsurer and
        // closing a period are four others. The underwriter who raises a line
        // must not be able to approve its own payment.
        Route::post('reinsurance/fac',                    [FacRegisterApiController::class, 'store'])->middleware('permission:reinsurance-fac-create')->name('reinsurance.fac.store');
        Route::put('reinsurance/fac/{id}',                [FacRegisterApiController::class, 'update'])->whereNumber('id')->middleware('permission:reinsurance-fac-edit')->name('reinsurance.fac.update');
        Route::post('reinsurance/fac/{id}/sync-policy',   [FacRegisterApiController::class, 'syncPolicy'])->whereNumber('id')->middleware('permission:reinsurance-fac-edit')->name('reinsurance.fac.syncPolicy');
        Route::post('reinsurance/fac/{id}/attachments',   [FacRegisterApiController::class, 'storeAttachment'])->whereNumber('id')->middleware('permission:reinsurance-fac-edit')->name('reinsurance.fac.attachments.store');
        // Filing the signed slip is the step that turns a draft placement into a
        // payable one, so it sits behind the slip right rather than plain edit.
        Route::post('reinsurance/fac/{id}/signed-slip',   [FacRegisterApiController::class, 'storeSignedSlip'])->whereNumber('id')->middleware('permission:reinsurance-fac-slip')->name('reinsurance.fac.signedSlip');
        Route::post('reinsurance/fac/{id}/client-paid',   [FacRegisterApiController::class, 'markClientPaid'])->whereNumber('id')->middleware('permission:reinsurance-fac-edit')->name('reinsurance.fac.clientPaid');
        Route::post('reinsurance/fac/{id}/settle',        [FacRegisterApiController::class, 'settle'])->whereNumber('id')->middleware('permission:reinsurance-fac-settle')->name('reinsurance.fac.settle');
        Route::post('reinsurance/fac/{id}/cancel',        [FacRegisterApiController::class, 'cancel'])->whereNumber('id')->middleware('permission:reinsurance-fac-cancel')->name('reinsurance.fac.cancel');
        Route::post('reinsurance/fac/slips',              [FacRegisterApiController::class, 'generateSlip'])->middleware('permission:reinsurance-fac-slip')->name('reinsurance.fac.slips.generate');
        Route::post('reinsurance/fac/slips/{slipId}/send', [FacRegisterApiController::class, 'sendSlip'])->whereNumber('slipId')->middleware('permission:reinsurance-fac-slip')->name('reinsurance.fac.slips.send');
        // The term-sheet wording — deductible, description of risk, territorial scope.
        // Legal terms on a signed document, so they carry the same permission as
        // generating and sending the slip.
        Route::put('reinsurance/fac/slips/{slipId}/terms', [FacRegisterApiController::class, 'updateSlipTerms'])->whereNumber('slipId')->middleware('permission:reinsurance-fac-slip')->name('reinsurance.fac.slips.terms');
        Route::post('reinsurance/fac/period/gl',          [FacRegisterApiController::class, 'storeGl'])->middleware('permission:reinsurance-fac-close')->name('reinsurance.fac.gl');
        Route::post('reinsurance/fac/period/close',       [FacRegisterApiController::class, 'closePeriod'])->middleware('permission:reinsurance-fac-close')->name('reinsurance.fac.close');
        Route::post('reinsurance/fac/ask',                [FacRegisterApiController::class, 'ask'])->middleware('permission:reinsurance-fac-list')->name('reinsurance.fac.ask');

        Route::post('reinsurance/coverage-groups',        [ReinsuranceApiController::class, 'storeGroup'])->name('reinsurance.storeGroup');
        Route::put('reinsurance/coverage-groups/{id}',    [ReinsuranceApiController::class, 'updateGroup'])->name('reinsurance.updateGroup');
        Route::delete('reinsurance/coverage-groups/{id}', [ReinsuranceApiController::class, 'destroyGroup'])->name('reinsurance.destroyGroup');
    });

    // ─── BizSure partner API (server-to-server) ──────────────────────────────
    // Authenticated by a PRE-MINTED Sanctum service token scoped to the bizsure
    // ability (mint via `php artisan partner:mint-token bizsure`). This mirrors
    // the alpha-finance ERP service-token pattern (see Kernel `ability` middleware)
    // — no password login, so the SSO gate never applies. Reads accept a read OR
    // write token; writes require bizsure:write. See BIZSURE_V2_INTEGRATION.md.
    Route::prefix('partner/bizsure')
        ->middleware(['auth:sanctum', 'throttle:api_write', 'XssSanitizer'])
        ->group(function () {
            Route::middleware('ability:bizsure:read,bizsure:write')->group(function () {
                Route::get('policies/{number}', [BizSurePartnerController::class, 'show'])->name('partner.bizsure.show');
                Route::get('lookups/{key}',     [BizSurePartnerController::class, 'lookup'])->name('partner.bizsure.lookup');
                Route::get('agents/{code}',     [BizSurePartnerController::class, 'verifyAgent'])->name('partner.bizsure.verifyAgent');
            });
            Route::middleware('ability:bizsure:write')->group(function () {
                Route::post('policies',                  [BizSurePartnerController::class, 'store'])->name('partner.bizsure.store');
                Route::post('policies/{number}/kyc',     [BizSurePartnerController::class, 'uploadKyc'])->name('partner.bizsure.kyc');
                Route::post('policies/{number}/payment', [BizSurePartnerController::class, 'recordPayment'])->name('partner.bizsure.payment');
            });
        });
});

// ─── OTP-gated consent capture (outside v1 wrapper so URLs match the
// spec shape /api/public/v1/consents/...) ──────────────────────────────
// Three-step flow: start → OTP issued · verify → identity confirmed,
// session minted · accept → audit row written with full evidence chain
// (otp_sent_at → otp_verified_at → accepted_at + SHA-256 tamper-evidence
// hash). 10/min/IP throttle covers legitimate retries while blocking
// brute-force. NBFIRA TCF + DPA 2024 + ECTA 2014 compliant.
Route::prefix('public/v1/consents')->name('public.consents.')->middleware('throttle:10,1')->group(function () {
    Route::post('start',  [\AlphaDirect\Http\Controllers\Api\Public\ConsentController::class, 'start'])->name('start');
    Route::post('verify', [\AlphaDirect\Http\Controllers\Api\Public\ConsentController::class, 'verify'])->name('verify');
    Route::post('accept', [\AlphaDirect\Http\Controllers\Api\Public\ConsentController::class, 'accept'])->name('accept');
});

// ─── Claimant self-service claim tracking (Claims Tracker -> Graphite,
// Phase 2). Public + unauthenticated — the claimant proves ownership with an
// OTP sent to the claim's registered contact. Whole surface is gated by the
// `claimant_tracking` runtime flag (default OFF) inside the controller, and
// fail-closed / non-enumerating. Outside the v1 wrapper so the URL matches the
// public spec shape /api/public/v1/claim-tracking/... ─────────────────────
// Tight throttles: request-otp is the SMS-cost + abuse surface (5/min/IP),
// verify a touch looser for legit retries (10/min/IP), status read is roomier
// (30/min/IP) since a claimant may refresh their own claim page.
Route::prefix('public/v1/claim-tracking')->name('public.claim_tracking.')->group(function () {
    Route::post('request-otp', [\AlphaDirect\Http\Controllers\Api\V1\ClaimTrackingController::class, 'requestOtp'])
        ->middleware('throttle:5,1')->name('requestOtp');
    Route::post('verify-otp',  [\AlphaDirect\Http\Controllers\Api\V1\ClaimTrackingController::class, 'verifyOtp'])
        ->middleware('throttle:10,1')->name('verifyOtp');
    Route::post('status',      [\AlphaDirect\Http\Controllers\Api\V1\ClaimTrackingController::class, 'status'])
        ->middleware('throttle:30,1')->name('status');
});

// ─── Claimant claim-form completion (no password). Public + unauthenticated.
// The 64-char token IS the credential — minted per claim, revocable, 90-day
// expiry, purpose-scoped to 'fill_form' so the OTP-gated status link cannot be
// replayed here. Whole surface is gated by `claims_form_dispatch` (default OFF)
// inside the controller, and fails closed without saying why (no enumeration).
// Throttles: reading your own form is roomy, submitting and uploading are not.
Route::prefix('public/v1/claim-form')->name('public.claim_form.')->group(function () {
    Route::get('{token}',         [\AlphaDirect\Http\Controllers\Api\V1\PublicClaimFormController::class, 'show'])
        ->middleware('throttle:30,1')->name('show');
    Route::post('{token}/submit', [\AlphaDirect\Http\Controllers\Api\V1\PublicClaimFormController::class, 'submit'])
        ->middleware('throttle:10,1')->name('submit');
    Route::post('{token}/upload', [\AlphaDirect\Http\Controllers\Api\V1\PublicClaimFormController::class, 'upload'])
        ->middleware('throttle:20,1')->name('upload');
});

// Customer-initiated consent withdrawal. Bearer session required (customer
// proves they own the cellphone via OTP/magic-link first). Lives under
// /api/v1/customers/me/* per the spec's URL shape — same controller
// handles it since the auth model is identical to the start/verify flow.
Route::prefix('v1/customers/me/consents')->name('customer.consents.')->middleware('throttle:10,1')->group(function () {
    Route::post('{id}/revoke', [\AlphaDirect\Http\Controllers\Api\Public\ConsentController::class, 'revoke'])
        ->whereNumber('id')
        ->name('revoke');
});

// Admin manual PDF processing trigger (emergency cron backup)
Route::middleware('auth:api')->prefix('v1/admin')->name('admin.')->group(function () {
    Route::post('process-pdf-jobs', [AdminPdfProcessController::class, 'processPending'])->name('processPdfJobs');
    Route::get('pdf-queue-status', [AdminPdfProcessController::class, 'queueStatus'])->name('pdfQueueStatus');
});

// ─── Finance ERP read-only API (added 2026-06-09) ─────────────────────
// Sanctum-authed read-only endpoints consumed by the alpha-finance Django
// ERP and any other approved downstream system. Lives under its own
// /v1/finance/* prefix so we can evolve the contract independently of
// the React SPA's /v1/* surface.
//
// Auth model:
//   - Bearer Sanctum token minted per service account (NOT a human user).
//     The mint command lives at `php artisan finance:mint-erp-token`.
//   - Token must carry the `finance:read` ability. The `ability:` middleware
//     (Laravel\Sanctum\Http\Middleware\CheckForAnyAbility) returns 403 if
//     the calling token is missing it — so even a leaked admin user token
//     can't hit these endpoints unless it was explicitly minted with the
//     ability.
//   - throttle:api_read uses the same 60req/min bucket as the SPA reads;
//     ERPs that sweep heavily can run multiple service accounts to scale.
//
// All endpoints are SELECT-only. No write paths live in this group.
Route::prefix('v1/finance')
    ->middleware(['auth:sanctum', 'ability:finance:read', 'throttle:api_read'])
    ->name('finance.')
    ->group(function () {
        Route::get('payment-transactions',        [FinancePaymentTransactionController::class, 'index'])
            ->name('paymentTransactions.index');
        Route::get('payment-transactions/{id}',   [FinancePaymentTransactionController::class, 'show'])
            ->whereNumber('id')
            ->name('paymentTransactions.show');
    });


// ─────────────────────────────────────────────────────────────────────────
// Data Access Request — DPO-gated reveal of masked customer PII.
// Requester raises a request (policy + fields + >=50-word justification);
// an approver (customer-data.approve permission) approves/denies; only an
// approved request can reveal the fields. Every step is audited.
// ─────────────────────────────────────────────────────────────────────────
Route::prefix('v1/data-access')
    ->middleware(['auth:sanctum'])
    ->name('dataAccess.')
    ->group(function () {
        Route::post('/',              [DataAccessRequestController::class, 'store'])->name('store');
        Route::get('mine',            [DataAccessRequestController::class, 'mine'])->name('mine');
        Route::get('queue',           [DataAccessRequestController::class, 'queue'])->name('queue');
        Route::post('{id}/decide',    [DataAccessRequestController::class, 'decide'])->whereNumber('id')->name('decide');
        Route::get('{id}/reveal',     [DataAccessRequestController::class, 'reveal'])->whereNumber('id')->name('reveal');
    });
