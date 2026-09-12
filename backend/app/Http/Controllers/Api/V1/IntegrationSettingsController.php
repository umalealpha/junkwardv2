<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Services\IntegrationSettings;
use AlphaDirect\Services\Mapfre\MapfreAuthClient;
use AlphaDirect\Services\Mapfre\MapfreTravelClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * IntegrationSettingsController — admin provision to enable/disable third-party
 * integrations at runtime (no redeploy), with an audit trail of every toggle.
 *
 * Routes (api_v1.php, behind auth:sanctum):
 *   GET  /api/v1/integrations                 list known integrations + state
 *   GET  /api/v1/integrations/{integration}   one integration's state
 *   PUT  /api/v1/integrations/{integration}   toggle enabled (authorised roles)
 *
 * Authorisation: admin / Super Admin / developer roles, OR any role granted
 * the optional `manage_integrations` permission. The permission check is
 * guarded so it never throws if that permission isn't seeded — the named
 * roles always work.
 */
class IntegrationSettingsController extends Controller
{
    /** Integrations this UI can manage: slug => human label. */
    private const KNOWN = [
        'swiftly' => 'Swiftly Finance',
        'mapfre'  => 'MAPFRE Travel',
        // Internal feature flag (not a third-party provider): the claims
        // auto-email / automation pipeline. On/off only — no credentials.
        'claims_automation' => 'Claims Automation',
        // Internal feature flags: the Claims-Tracker -> Graphite migration
        // (on/off only, default OFF, no credentials).
        'claims_sla' => 'Claims SLA',                               // Phase 1: SLA + stage-timeline engine
        'claims_incentive_report' => 'Claims Incentive Report',     // Phase 3: panel-beater / glass %
        'claims_mentions'         => 'Claims Review-Note @Mentions', // Phase 3: @mention capture
        'claims_decision_workflow' => 'Claims Decision Workflow',    // Wave 3: approve / repudiate / reverse
        // Backdate governance: enforcement hook + alerts on claim stage-date
        // edits (default OFF — admin screen is preview-capable while off).
        'claims_backdate_governance' => 'Claims Backdate Governance',
        'claims_notifications'    => 'Claims Notifications',         // notification log + admin dashboard
        // Phase 2: scheduled executive KPI email reports. Default OFF — with
        // the flag OFF the scheduler still renders/logs each due report but
        // sends NOTHING; only flag-ON + an enabled schedule + explicit
        // recipients actually dispatches mail via Mailgun.
        'claims_scheduled_reports' => 'Claims Scheduled KPI Reports',
        // Phase 2: bulk claim import (.xlsx/.csv). Default OFF — with the flag
        // OFF the upload/preview (dry-run) still works for admins, but the
        // confirm/commit step is refused. Import always routes each row through
        // the existing claim-create path (validation intact).
        'claims_bulk_import' => 'Claims Bulk Import',
        'claims_comment_status'   => 'Claims Comment Status & Priority Reminders', // comment-status workflow + unread-mention reminder tick sends
        'claimant_tracking'       => 'Claimant Tracking Link',      // Phase 2: public claim-status page + auto-issue link on claim-create
        'claims_docs'             => 'Claims Document Generator',   // static "Claim Forms" tab under Claims (7 correspondence forms; frontend-only, no backend)
        // FNOL (First Notification of Loss) intake — lightweight pre-claim ledger
        // + send-gated doc-reminder command + convert-to-claim. Default OFF; the
        // reminder command is additionally disarmed via config('claims_fnol.reminders_armed').
        'claims_fnol'             => 'Claims FNOL Intake',
        // Premium confirmation on the claim (Finance's spreadsheet, replaced).
        // The feature shipped 11 Aug fully wired (engine, listener, tracker,
        // routes, prod migration) but this slug was never exposed here, so the
        // runtime toggle could not be switched on at all. Default OFF; green
        // auto-releases, amber/red always reaches a person, never auto-declines.
        'premium_confirmation'    => 'Claims Premium Confirmation',
        // PO-in-Graphite Phase 2: the claim page's Omni purchase-order panel
        // (server-side proxy; render-only, never persisted). `configured`
        // lights up once OMNI_PO_API_KEY lands via SSM — see services.omni_po.
        'omni_po'                 => 'Omni Purchase Orders (Claims)',
        // Underwriting Bottleneck dashboard (CFO 27 Aug) — read-only new-business
        // funnel + daily approval-SLA digest. Default OFF: the dashboard route
        // 404s and the SLA digest no-ops until switched on here, so the feature is
        // safe to ship to prod dark and lit up only on the CFO's sign-off.
        'uw_bottleneck'           => 'Underwriting Bottleneck Dashboard',
        // Alpha Transit Cover (courier goods-in-transit) inbound event
        // ingestion — POST /api/v1/webhooks/alpha-transit/event. Default OFF:
        // while off the endpoint answers 503 and the ATC platform's retry
        // queue holds the events (replayable, nothing lost). Bearer token via
        // ALPHA_TRANSIT_WEBHOOK_TOKEN (SSM) — see services.alpha_transit.
        'alpha_transit'           => 'Alpha Transit Cover (Courier GIT)',
    ];

    /**
     * Config keys that must ALL be non-empty for an integration to count as
     * "configured" on the admin card.
     *
     * Defaults to ['api_key'] for anything not listed, which is what this
     * screen always assumed. That assumption is wrong for any provider which
     * does not authenticate with a single API key: MAPFRE uses a Cognito +
     * eMiA credential set and has NO `services.mapfre.api_key`, so it reported
     * "Not configured" permanently — even with every credential present and
     * the integration working.
     *
     * MAPFRE is deliberately absent here: its key set spans TWO clients and a
     * class constant cannot merge them. It is resolved in requiredConfigKeys().
     *
     * Internal feature flags are absent on purpose: they have no credentials,
     * and the admin UI hides the chip for them entirely.
     */
    private const CONFIG_KEYS = [
        'swiftly' => ['api_key'],
        'omni_po' => ['api_key'],
        // Inbound webhook integrations authenticate the OTHER way round: the
        // partner pushes to us and presents a shared token, so there is no
        // outbound api_key to look for.
        'alpha_transit' => ['webhook_token'],
    ];

    public function index(): JsonResponse
    {
        $data = [];
        foreach (self::KNOWN as $slug => $label) {
            $data[] = $this->stateFor($slug, $label);
        }

        return response()->json([
            'data'        => $data,
            'can_manage'  => $this->canManage(),
        ]);
    }

    public function show(string $integration): JsonResponse
    {
        if (!isset(self::KNOWN[$integration])) {
            return response()->json(['error' => 'Unknown integration'], 404);
        }

        return response()->json([
            'data'       => $this->stateFor($integration, self::KNOWN[$integration]),
            'can_manage' => $this->canManage(),
        ]);
    }

    public function update(Request $request, string $integration): JsonResponse
    {
        if (!isset(self::KNOWN[$integration])) {
            return response()->json(['error' => 'Unknown integration'], 404);
        }

        if (!$this->canManage()) {
            return response()->json(['error' => 'You are not authorised to change integration settings'], 403);
        }

        $validated = $request->validate([
            'enabled' => 'required|boolean',
            'notes'   => 'nullable|string|max:500',
        ]);

        $result = IntegrationSettings::setEnabled(
            $integration,
            (bool) $validated['enabled'],
            $request->user(),
            $validated['notes'] ?? null,
        );

        return response()->json([
            'success' => true,
            'message' => self::KNOWN[$integration] . ' ' . ($result['enabled'] ? 'enabled' : 'disabled'),
            'data'    => $result,
        ]);
    }

    /**
     * POST /api/v1/integrations/{integration}/test-connection
     *
     * Live connectivity + auth check against the provider.
     *
     * Swiftly: GET /offtaker/suppliers (uses the API key already loaded into
     * config) and surfaces the program_id + supplier list it returns — which
     * is also how we resolve the program_id when it wasn't shared up front.
     *
     * MAPFRE: the Cognito -> eMiA auth chain, which validates every credential
     * without creating provider-side state. See testMapfreConnection().
     *
     * Always returns 200 with an {ok:bool} body so the UI can render a clean
     * pass/fail without treating provider errors as request failures.
     */
    public function testConnection(string $integration): JsonResponse
    {
        if (!isset(self::KNOWN[$integration])) {
            return response()->json(['error' => 'Unknown integration'], 404);
        }
        if (!$this->canManage()) {
            return response()->json(['error' => 'You are not authorised to test integration connections'], 403);
        }
        // Dispatch per provider. This used to be `if ($integration !== 'swiftly')`,
        // which is why the MAPFRE card's Test connection button — advertised by
        // the admin UI's own capability map (CAPS.mapfre.connectionTest) since
        // the master-detail Integrations screen shipped — always came back
        // "Connection test is not available for this integration". The button
        // and the endpoint had simply drifted apart.
        if ($integration === 'mapfre') {
            return $this->testMapfreConnection();
        }

        if ($integration !== 'swiftly') {
            return response()->json(['ok' => false, 'error' => 'Connection test is not available for this integration'], 200);
        }

        try {
            $resp = app(\AlphaDirect\Services\Swiftly\SwiftlyService::class)->listSuppliers();
        } catch (\Throwable $e) {
            // assertConfigured throws when the API key isn't set yet.
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 200);
        }

        if (!$resp->isSuccess()) {
            return response()->json([
                'ok'          => false,
                'http_status' => $resp->httpStatus,
                'error'       => $resp->error ?? 'Connection failed',
            ], 200);
        }

        // Best-effort extraction — the suppliers endpoint returns the program_id
        // and supplier list; exact shape may vary, so surface what we find and
        // keep a short raw preview for diagnostics.
        $data      = $resp->data();
        $programId = $data['program_id'] ?? $data['programId'] ?? ($data['program']['id'] ?? null);
        $suppliers = $data['suppliers'] ?? $data['data'] ?? (array_is_list($data) ? $data : []);
        if (!is_array($suppliers)) {
            $suppliers = [];
        }

        return response()->json([
            'ok'             => true,
            'http_status'    => $resp->httpStatus,
            'program_id'     => $programId,
            'supplier_count' => count($suppliers),
            'suppliers'      => array_slice($suppliers, 0, 20),
            'raw_preview'    => $resp->rawExcerpt,
        ]);
    }

    /**
     * Live MAPFRE connectivity + credential check.
     *
     * MAPFRE has no cheap read-only "list" endpoint equivalent to Swiftly's
     * /offtaker/suppliers, and firing a real /quotes or /contract call would
     * create provider-side state just to answer "are we connected?". The auth
     * chain is the right probe: MapfreAuthClient::token() validates every
     * credential (Cognito client-credentials grant, then the eMiA LOGIN
     * exchange) against both live endpoints, and throws a specific
     * "not configured: missing services.mapfre.X" when a credential is absent.
     *
     * forgetTokens() first, deliberately: token() returns a cached eMiA token
     * when one is warm, so without this the test could report success without
     * contacting MAPFRE at all. token() repopulates the cache on the way out,
     * so the net effect is a REFRESHED token rather than a cleared one — no
     * in-flight request is left without credentials.
     *
     * Never returns the token. The non-secret fields it does return (base_url,
     * country_id) are what actually distinguishes the PRE dealer from a
     * production one, which is the usual reason to run this.
     */
    private function testMapfreConnection(): JsonResponse
    {
        // Enumerate every absent credential before attempting the round trip.
        // token() -> assertConfigured() throws on the FIRST missing key, and
        // cognito_token_url happens to be first — so an environment missing
        // several answered "missing services.mapfre.cognito_token_url", and
        // only revealed the next one after that had been supplied. One answer
        // listing all of them is the difference between one fix and seven.
        //
        // Deliberately the UNION of both clients, not MapfreAuthClient's own
        // list: the auth chain does not need `base_url`, so testing only its
        // keys reported "Authenticated with MAPFRE" (echoing a null base_url)
        // on an environment where every travel call answered 503
        // travel_not_configured.
        $missing = $this->missingConfigKeys('mapfre');
        if ($missing !== []) {
            return response()->json([
                'ok'      => false,
                'missing' => $missing,
                'error'   => 'MAPFRE is not configured on this environment — '
                    . (count($missing) === 1 ? 'missing credential: ' : 'missing ' . count($missing) . ' credentials: ')
                    . implode(', ', array_map(static fn ($k) => "services.mapfre.{$k}", $missing)),
            ], 200);
        }

        $auth = app(\AlphaDirect\Services\Mapfre\MapfreAuthClient::class);

        try {
            $auth->forgetTokens();
            $token = $auth->token();
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 200);
        }

        if (!is_string($token) || $token === '') {
            return response()->json([
                'ok'    => false,
                'error' => 'MAPFRE returned an empty token.',
            ], 200);
        }

        return response()->json([
            'ok'         => true,
            'message'    => 'Authenticated with MAPFRE (Cognito + eMiA).',
            'base_url'   => config('services.mapfre.base_url'),
            'country_id' => config('services.mapfre.country_id'),
        ]);
    }

    /**
     * POST /api/v1/integrations/{integration}/test-invoice
     *
     * Submits a TEST invoice to the provider so we can exercise the flow on
     * staging. For Swiftly, passing auto_request_early_payment=true makes
     * Swiftly also raise an approved early-payment request and fire the webhook
     * back to us — validating the full loop from this one call.
     *
     * Generates its own invoice_id. Amount is entered in Pula and converted to
     * minor units (thebe) for the API. Always 200 with {ok:bool}.
     */
    public function submitTestInvoice(Request $request, string $integration): JsonResponse
    {
        if (!isset(self::KNOWN[$integration])) {
            return response()->json(['error' => 'Unknown integration'], 404);
        }
        if (!$this->canManage()) {
            return response()->json(['error' => 'You are not authorised to submit test invoices'], 403);
        }
        if ($integration !== 'swiftly') {
            return response()->json(['ok' => false, 'error' => 'Test invoice is not available for this integration'], 200);
        }

        $validated = $request->validate([
            'invoice_id'  => 'nullable|string|max:191',   // reuse for duplicate/retry tests
            'supplier_id' => 'required|string|max:191',
            'amount'      => 'nullable|numeric|min:1',     // Pula
            'currency'    => 'nullable|string|max:8',
            'due_at'      => 'nullable|date',
            'percentage_requested'       => 'nullable|numeric',
            'auto_request_early_payment' => 'nullable|boolean',
            'force'       => 'nullable|boolean',           // bypass our local dedupe → hit Swiftly's 422
        ]);

        $amountPula = (float) ($validated['amount'] ?? 50000);
        $invoice = [
            'invoice_id'                 => $validated['invoice_id'] ?? ('TEST-' . now()->format('YmdHis')),
            'supplier_id'                => $validated['supplier_id'],
            'amount_minor'               => (int) round($amountPula * 100),
            'due_at'                     => $validated['due_at'] ?? now()->addDays(60)->format('Y-m-d'),
            'auto_request_early_payment' => $validated['auto_request_early_payment'] ?? true,
            'skip_idempotency'           => (bool) ($validated['force'] ?? false),
        ];
        if (!empty($validated['currency'])) {
            $invoice['currency'] = strtoupper($validated['currency']);
        }
        if (isset($validated['percentage_requested'])) {
            $invoice['percentage_requested'] = $validated['percentage_requested'];
        }

        try {
            $resp = app(\AlphaDirect\Services\Swiftly\SwiftlyService::class)->submitInvoice($invoice);
        } catch (\Throwable $e) {
            // e.g. missing program_id — return a clear reason, not a generic 500.
            return response()->json([
                'ok'         => false,
                'invoice_id' => $invoice['invoice_id'],
                'error'      => $e->getMessage(),
            ], 200);
        }

        return response()->json([
            'ok'          => $resp->isSuccess(),
            'invoice_id'  => $invoice['invoice_id'],
            'http_status' => $resp->httpStatus,
            'error'       => $resp->isSuccess() ? null : ($resp->error ?? 'submission failed'),
            // Echo the request we sent, so the console shows exactly what went out.
            'request'     => [
                'invoice_id'                 => $invoice['invoice_id'],
                'program_id'                 => IntegrationSettings::getSetting('swiftly', 'program_id') ?: config('services.swiftly.program_id'),
                'supplier_id'                => $invoice['supplier_id'],
                'invoice_amount'             => $invoice['amount_minor'],
                'currency'                   => $invoice['currency'] ?? config('services.swiftly.currency', 'BWP'),
                'due_at'                     => $invoice['due_at'],
                'percentage_requested'       => $validated['percentage_requested'] ?? null,
                'auto_request_early_payment' => $invoice['auto_request_early_payment'],
                'force'                      => $invoice['skip_idempotency'],
            ],
            'response'    => $resp->data(),
            'raw_preview' => $resp->rawExcerpt,
        ], 200);
    }

    /**
     * GET /api/v1/integrations/{integration}/webhooks
     *
     * Recent inbound webhooks captured for this integration (Swiftly early-
     * payment notifications). Lets the test console show receipt + parsed
     * payload + whether the HMAC signature verified.
     */
    public function webhooks(string $integration): JsonResponse
    {
        if (!isset(self::KNOWN[$integration])) {
            return response()->json(['error' => 'Unknown integration'], 404);
        }
        if (!$this->canManage()) {
            return response()->json(['error' => 'Not authorised'], 403);
        }
        if ($integration !== 'swiftly') {
            return response()->json(['data' => []]);
        }

        try {
            $rows = \Illuminate\Support\Facades\DB::connection('mysql_system')
                ->table('swiftly_webhook_events')
                ->orderByDesc('id')
                ->limit(25)
                ->get(['id', 'event_id', 'invoice_id', 'event_type', 'signature_valid', 'status', 'payload', 'received_at']);

            $data = $rows->map(function ($r) {
                return [
                    'id'              => $r->id,
                    'event_type'      => $r->event_type,
                    'invoice_id'      => $r->invoice_id,
                    'signature_valid' => (bool) $r->signature_valid,
                    'status'          => $r->status,
                    'received_at'     => $r->received_at,
                    'payload'         => json_decode($r->payload ?? 'null', true),
                ];
            });

            return response()->json(['data' => $data]);
        } catch (\Throwable $e) {
            return response()->json(['data' => [], 'error' => $e->getMessage()], 200);
        }
    }

    /**
     * Every config key that must be non-empty for this integration to work.
     *
     * See CONFIG_KEYS — anything unlisted keeps the historical single
     * `api_key` check.
     *
     * MAPFRE spans two clients and needs BOTH sets. Checking only the auth
     * client's list (as this screen used to) left `base_url` unchecked, so the
     * card could read "Configured" while every /public/travel/* call threw
     * MapfreNotConfiguredException('base_url') and answered 503
     * travel_not_configured — the chip pointing away from the missing key.
     */
    private function requiredConfigKeys(string $slug): array
    {
        if ($slug === 'mapfre') {
            return array_values(array_unique(array_merge(
                MapfreAuthClient::REQUIRED_CONFIG_KEYS,
                MapfreTravelClient::REQUIRED_CONFIG_KEYS,
            )));
        }

        return self::CONFIG_KEYS[$slug] ?? ['api_key'];
    }

    /**
     * Which of those keys are absent. Key NAMES only — never a value, not even
     * a masked one. Empty array means fully configured.
     */
    private function missingConfigKeys(string $slug): array
    {
        return array_values(array_filter(
            $this->requiredConfigKeys($slug),
            fn (string $key): bool => empty(config("services.$slug.$key")),
        ));
    }

    /** Are this integration's credentials present? */
    private function isConfigured(string $slug): bool
    {
        return $this->missingConfigKeys($slug) === [];
    }

    private function stateFor(string $slug, string $label): array
    {
        $row = IntegrationSettings::get($slug);

        return [
            'integration'     => $slug,
            'label'           => $label,
            'enabled'         => $row ? (bool) $row->enabled : (bool) config("services.$slug.enabled", false),
            'configured'      => $this->isConfigured($slug),
            // WHICH keys are absent, so an operator does not have to grep the
            // server log for public_travel.not_configured to find out. Names
            // only, and only for someone who may manage integrations anyway.
            'missing_config'  => $this->canManage() ? $this->missingConfigKeys($slug) : null,
            // Non-secret identifier, editable from the UI (falls back to env).
            'program_id'      => IntegrationSettings::getSetting($slug, 'program_id') ?: config("services.$slug.program_id"),
            'updated_by'      => $row->updated_by_name ?? null,
            'updated_at'      => $row->updated_at ?? null,
            'notes'           => $row->notes ?? null,
        ];
    }

    /**
     * PUT /api/v1/integrations/{integration}/settings
     *
     * Save non-secret settings from the admin UI — currently the program_id
     * (an identifier, not a secret). Lets an operator set it without an env
     * change / redeploy. Secrets (api_key, webhook_secret) are NOT accepted here.
     */
    public function updateSettings(Request $request, string $integration): JsonResponse
    {
        if (!isset(self::KNOWN[$integration])) {
            return response()->json(['error' => 'Unknown integration'], 404);
        }
        if (!$this->canManage()) {
            return response()->json(['error' => 'You are not authorised to change integration settings'], 403);
        }

        $validated = $request->validate([
            'program_id' => 'nullable|string|max:191',
        ]);

        IntegrationSettings::putSetting($integration, 'program_id', $validated['program_id'] ?? null, $request->user());

        return response()->json([
            'success' => true,
            'message' => self::KNOWN[$integration] . ' settings updated',
            'data'    => $this->stateFor($integration, self::KNOWN[$integration]),
        ]);
    }

    /**
     * admin / Super Admin / developer roles, or the optional
     * `manage_integrations` permission. Mirrors CronConfigController::canRunCron.
     */
    private function canManage(): bool
    {
        $user = Auth::user();
        if (!$user) {
            return false;
        }
        if ($user->hasAnyRole(['admin', 'Super Admin', 'developer'])) {
            return true;
        }
        try {
            return $user->hasPermissionTo('manage_integrations');
        } catch (\Throwable $e) {
            // Permission not seeded — fall back to roles only.
            return false;
        }
    }
}
