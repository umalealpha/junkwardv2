<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'paym8' => [
        'url' => env('APP_STATUS') === 'Production'
            ? 'https://paym8online.com/'
            : 'https://test.paym8online.com/',
        'key' => env('APP_STATUS') === 'Production'
            ? 'QWxwaGFEaXJlY3Q6QEFscGhhMTIz'
            : 'QWxwaGFEaXJlY3Q6QTFQaEBEMXIzYyEh',
        'product' => env('APP_STATUS') === 'Production'
            ? '1QBTPZ'
            : 'TD7SGG',
        'channel' => 'BotswanaRealtimeACB',
    ],
//'BotswanaSameDayDebitOrder'

    'opensanctions' => [
        'base' => env('OPENSANCTIONS_BASE', 'https://api.opensanctions.org'),
        'key'  => env('OPENSANCTIONS_KEY'),
    ],

    'anthropic' => [
        'key'   => env('ANTHROPIC_API_KEY'),
        'model' => env('AI_MODEL', 'claude-opus-4-6'),
    ],

    'groq' => [
        'key'   => env('GROQ_API_KEY'),
        'model' => env('AI_MODEL', 'llama-3.3-70b-versatile'),
    ],

    'gemini' => [
        'key'   => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-2.5-flash'),
    ],

    'react_portal' => [
        'url' => env('REACT_SPA_URL', 'http://localhost:3000'),
    ],

    // Swiftly Finance — supply-chain / early-payment finance integration.
    // V2-only. Outbound: invoice submission. Inbound: signed early-payment
    // webhooks. Secrets are issued by Swiftly during onboarding and MUST be
    // provided via SSM SecureString in prod (never committed). Read via
    // config() everywhere (NOT runtime env()) so values survive config:cache.
    // See app/Services/Swiftly/ and docs at D:\ADRisk\Swiftly Integration.
    'swiftly' => [
        // Default on/off when no integration_settings row exists yet. The DB
        // flag (toggled from the admin UI) is authoritative once set.
        'enabled'        => env('SWIFTLY_ENABLED', false),
        'base_url'       => env('SWIFTLY_BASE_URL', 'https://api.staging.swiftly.finance'),
        'api_key'        => env('SWIFTLY_API_KEY'),
        'program_id'     => env('SWIFTLY_PROGRAM_ID'),
        'supplier_id'    => env('SWIFTLY_SUPPLIER_ID'),
        // Shared secret used to verify the HMAC-SHA256 signature Swiftly puts
        // on the X-Webhook-Signature header of inbound notifications. Distinct
        // from api_key.
        'webhook_secret' => env('SWIFTLY_WEBHOOK_SECRET'),
        'currency'       => env('SWIFTLY_CURRENCY', 'BWP'),
        'timeout'        => (int) env('SWIFTLY_TIMEOUT', 30),
        'connect_timeout'=> (int) env('SWIFTLY_CONNECT_TIMEOUT', 10),
    ],

    // Customer Refund Engine — Omni (alpha-finance) money-leg handoff.
    // Graphite owns intake → review → approve; Omni owns Finance queue →
    // FNB EFT → paid, then calls back POST /api/v1/webhooks/omni/refund-paid.
    // Default OFF: this is the config FALLBACK for
    // IntegrationSettings::isEnabled('omni_refunds') — the DB flag (Admin >
    // Integrations, audited) is authoritative once set. While off, approved
    // requests park with omni_status='not_sent' (the SOP runs arms-off).
    // Secrets via SSM SecureString in prod — never committed, never in the DB.
    'omni_refunds' => [
        'enabled'         => env('OMNI_REFUNDS_ENABLED', false),
        'base_url'        => env('OMNI_BASE_URL', ''),
        // Bearer Graphite presents to Omni's inbound endpoint (their
        // REFUND_INBOUND_TOKEN setting — shared secret).
        'inbound_token'   => env('OMNI_REFUND_INBOUND_TOKEN'),
        // Bearer Omni presents on the paid callback (their
        // GRAPHITE_REFUND_CALLBACK_TOKEN setting). Fail-closed if unset.
        'callback_token'  => env('OMNI_REFUND_CALLBACK_TOKEN'),
        'timeout'         => (int) env('OMNI_TIMEOUT', 30),
        'connect_timeout' => (int) env('OMNI_CONNECT_TIMEOUT', 10),
        // Keyed-HMAC key for the bank-account blind index. MUST be byte-identical
        // to Omni's REFUND_ACCOUNT_INDEX_KEY or the two fraud engines fingerprint
        // the same account differently and cross-system matching silently returns
        // nothing. Read via config() so it survives config:cache.
        'account_index_key' => env('REFUND_ACCOUNT_INDEX_KEY'),
        // VAT rate used to back VAT out of a VAT-inclusive MIS/MIB premium when
        // reversing it on a refund credit note. Botswana has moved between 12%
        // and 14%; a hardcoded rate mis-splits reversals of older premium.
        'vat_rate' => (float) env('REFUND_CN_VAT_RATE', 0.14),
    ],

    // PO-in-Graphite Phase 2 — read-only lookup of the Omni purchase orders
    // raised for a claim (CFO spec 12 Aug 2026: Omni OWNS POs; Graphite
    // renders live and NEVER persists PO status or amounts). Config FALLBACK
    // for IntegrationSettings::isEnabled('omni_po'); the DB flag (Admin >
    // Integrations) is authoritative once set. The key is Omni's scoped
    // `po-claim-read` ApiKey — delivered via the Vault, set as an SSM
    // SecureString in prod, never committed anywhere.
    'omni_po' => [
        'enabled'  => env('OMNI_PO_ENABLED', false),
        'base_url' => env('OMNI_PO_BASE_URL', env('OMNI_BASE_URL', 'https://omni.alphadirect.co.bw')),
        'api_key'  => env('OMNI_PO_API_KEY'),
        // Prathap's confirmed non-functionals: 2s timeout, ~60s cache.
        'timeout'         => (int) env('OMNI_PO_TIMEOUT', 2),
        'cache_seconds'   => (int) env('OMNI_PO_CACHE_SECONDS', 60),
    ],

    // Alpha Transit Cover — embedded courier goods-in-transit platform
    // (transit.alphadirect.co.bw). Inbound only in Phase 1: the ATC platform
    // POSTs six event types to /api/v1/webhooks/alpha-transit/event with a
    // shared bearer (its GRAPHITE_API_KEY env var). Config FALLBACK for
    // IntegrationSettings::isEnabled('alpha_transit') — the DB flag (Admin >
    // Integrations, audited) is authoritative once set. Default OFF: while
    // off the endpoint answers 503 and ATC's retry queue holds the events.
    // Token via SSM SecureString in prod — never committed, never in the DB.
    'alpha_transit' => [
        'enabled'       => env('ALPHA_TRANSIT_ENABLED', false),
        // Bearer the ATC platform presents on every event POST. Fail-closed
        // if unset. Must equal the GRAPHITE_API_KEY set on the ATC stack.
        'webhook_token' => env('ALPHA_TRANSIT_WEBHOOK_TOKEN'),
    ],

    // Claimant self-service tracking — public OTP-gated claim-status page
    // (Claims Tracker -> Graphite V2, Phase 2). This is the config FALLBACK
    // for the runtime toggle: IntegrationSettings::isEnabled('claimant_tracking')
    // reads the integration_settings DB row first (flipped from Admin >
    // Integrations, per-environment, audited) and falls back to this value
    // when no row exists. Default OFF — the feature ships dark and is armed
    // from the UI with no redeploy. See app/Services/ClaimTrackingService.php.
    'claimant_tracking' => [
        'enabled' => env('CLAIMANT_TRACKING_ENABLED', false),
    ],

    // Same runtime-toggle pattern for the claim-form send button: a handler
    // presses send on the claim, the claimant gets a pre-filled form plus a
    // no-password link to complete it online. Default OFF — armed from
    // Admin > Integrations (`claims_form_dispatch`) with no redeploy.
    // See app/Services/Claims/ClaimFormDispatchService.php.
    'claims_form_dispatch' => [
        'enabled' => env('CLAIMS_FORM_DISPATCH_ENABLED', false),
    ],

    // Comment-status workflow + priority @mention reminders (Claims Tracker ->
    // Graphite V2). Config FALLBACK for the runtime toggle
    // IntegrationSettings::isEnabled('claims_comment_status'); the DB row
    // (Admin > Integrations) wins. Default OFF — gates the reminder-tick SENDS
    // only; the set/read API and UI preview are RBAC-gated, not flag-gated.
    'claims_comment_status' => [
        'enabled' => env('CLAIMS_COMMENT_STATUS_ENABLED', false),
    ],

    'microsoft' => [
        'client_id'     => env('MICROSOFT_CLIENT_ID', ''),
        'client_secret' => env('MICROSOFT_CLIENT_SECRET', ''),
        'tenant_id'     => env('MICROSOFT_TENANT_ID', 'common'), // 'common' for multi-tenant
        'redirect_uri'  => env('MICROSOFT_REDIRECT_URI', ''),
    ],

    // Standalone Puppeteer PDF microservice. See pdf-service/ at repo root.
    // When PDF_SERVICE_URL is set, PdfGeneratorService routes all render
    // requests here. If unreachable, falls back to Snappy (wkhtmltopdf) then
    // DomPDF. See app/Services/PdfGeneratorService.php.
    'pdf' => [
        'url'              => env('PDF_SERVICE_URL', ''),
        'key'              => env('PDF_API_KEY', ''),
        'timeout'          => (int) env('PDF_SERVICE_TIMEOUT', 150),
        'fallback_snappy'  => (bool) env('PDF_FALLBACK_SNAPPY', true),
    ],

    // Shared partner API key checked by the VerifyApiKey middleware
    // (BizSure createPolicy, lookup write-back, policies/lastId, ...).
    // MUST live in config (not read via env() at runtime) so it survives
    // `php artisan config:cache` in production — otherwise env() returns
    // null under a cached config and every partner request 401s.
    'partner' => [
        'api_key' => env('API_KEY'),
    ],

    // MAPFRE / MAWDY "maip-travel" v1.0 travel-insurance integration (V2-only).
    // Two-stage auth: AWS Cognito client-credentials → eMiA user token.
    // Secrets (cognito_client_id/secret, username, password) are issued by
    // MAPFRE and set via SSM SecureString in prod — never commit real values.
    // MUST live in config (not read via env() at runtime) so they survive
    // `php artisan config:cache` in production — otherwise env() returns null
    // under a cached config and every MAPFRE call fails to authenticate.
    'mapfre' => [
        // Default on/off when no integration_settings row exists yet. The DB
        // flag (toggled from the admin UI) is authoritative once set.
        'enabled'              => env('MAPFRE_ENABLED', false),
        'base_url'             => env('MAPFRE_BASE_URL'),
        'auth_url'             => env('MAPFRE_AUTH_URL'),
        'cognito_token_url'    => env('MAPFRE_COGNITO_TOKEN_URL'),
        // Secrets — no defaults; supplied by MAPFRE, kept out of source.
        'cognito_client_id'    => env('MAPFRE_COGNITO_CLIENT_ID'),
        'cognito_client_secret'=> env('MAPFRE_COGNITO_CLIENT_SECRET'),
        'username'             => env('MAPFRE_USERNAME'),
        'password'             => env('MAPFRE_PASSWORD'),
        // eMiA LOGIN country (body of /oauth2/emia/token). BW is accepted.
        'country'              => env('MAPFRE_COUNTRY', 'BW'),
        // The `countryId` REQUEST HEADER on /api/v1/* — a DIFFERENT thing.
        // Catalogue + /price accept BW, but /quotes and /contract answer
        // 403 "country identifier code not valid" for BW on the PRE dealer:
        // its products are Italian (TRI25**it**21000002, priced in EUR) and
        // only countryId=IT is provisioned. Verified 2026-08-21 — IT returns a
        // real quoteId, every other value 403s. Must become BW once MAPFRE
        // provision a Botswana dealer.
        'country_id'           => env('MAPFRE_COUNTRY_ID', env('MAPFRE_COUNTRY', 'BW')),
        'language'             => env('MAPFRE_LANGUAGE', 'en'),
        'timeout'              => (int) env('MAPFRE_TIMEOUT', 30),
        'connect_timeout'      => (int) env('MAPFRE_CONNECT_TIMEOUT', 10),

        // Dialling prefix sent as infoPolicyHolder.mobilePrefix / infoTravellers[].mobilePrefix
        // on /contract. The portal captures 8 local digits only.
        'mobile_prefix'        => env('MAPFRE_MOBILE_PREFIX', '267'),
        // MAPFRE fiscal-id codes (see GET /api/v1/catalog/fiscalIdTypes).
        // 2 = PASAPORTE. Their catalogue for this dealer is a LATAM list with
        // NO Omang entry, so the Omang mapping is a placeholder that also
        // points at PASAPORTE until MAPFRE confirm the correct code.
        'fiscal_id_type_passport' => env('MAPFRE_FISCAL_ID_TYPE_PASSPORT', '2'),
        'fiscal_id_type_omang'    => env('MAPFRE_FISCAL_ID_TYPE_OMANG', '2'),

        // Travel package / cover-tier catalogue surfaced to the Start portal
        // by GET /api/v1/public/travel/packages. MAPFRE identifies a cover
        // tier by its own product id, which differs per environment (PRE vs
        // PROD) and is issued by MAPFRE — so the ids are env-driven and the
        // tier *labels* live here.
        //
        // Set these to MAPFRE's productCode (TRI25it21000002), NOT the numeric
        // id returned beside it — /product/{x}/price, /quotes and /contract all
        // key the path on the code and answer 422 "Wrong product code." for a
        // numeric id (verified live 2026-08-26). Leaving them blank is fine:
        // /public/travel/price-tiers discovers every tier at runtime and is
        // what the portal actually uses. A tier whose id is unset is returned with
        // available=false, and the portal renders it disabled rather than
        // firing a doomed /price call against a guessed product id.
        'travel_products' => [
            [
                'code'  => 'essential',
                'name'  => 'Essential',
                'blurb' => 'Emergency medical + repatriation cover.',
                'id'    => env('MAPFRE_TRAVEL_PRODUCT_ESSENTIAL'),
            ],
            [
                'code'  => 'standard',
                'name'  => 'Standard',
                'blurb' => 'Medical, baggage, delay and cancellation cover.',
                'id'    => env('MAPFRE_TRAVEL_PRODUCT_STANDARD'),
            ],
            [
                'code'  => 'premium',
                'name'  => 'Premium',
                'blurb' => 'Highest limits, winter sports and gadget cover.',
                'id'    => env('MAPFRE_TRAVEL_PRODUCT_PREMIUM'),
            ],
        ],
    ],

    // Alpha Bridge helpdesk — "Report an Issue" widget (V2-only). Graphite's
    // helpdesk module is migrating to Bridge; ticket creation goes through
    // Bridge's embeddable widget. The widget secret is exchanged for a
    // short-lived JWT by BridgeWidgetController — server-to-server only,
    // never sent to the browser. Set via SSM SecureString in prod, never
    // commit the real value. MUST live in config (not read via env() at
    // runtime) so it survives `php artisan config:cache` in production.
    // See d:\ADRisk\Alpha-Bridge\docs\widget.md.
    // Both env-name schemes are accepted — ALPHA_BRIDGE_* (integration doc)
    // and BRIDGE_* (task-def revisions carry one or the other) — so a
    // task-def rename can never silently break the integration again.
    // app_id is per-environment ("graphite-staging" on staging, "graphite"
    // on prod) — each has its own WIDGET_KEYS entry + secret on Bridge.
    'alpha_bridge' => [
        'base_url'      => env('ALPHA_BRIDGE_BASE_URL', env('BRIDGE_BASE_URL', 'https://bridge.alphadirect.co.bw')),
        'widget_secret' => env('ALPHA_BRIDGE_WIDGET_SECRET', env('BRIDGE_WIDGET_SECRET')),
        'app_id'        => env('ALPHA_BRIDGE_WIDGET_APP_ID', env('BRIDGE_WIDGET_APP_ID', 'graphite')),
    ],

    // Alpha Brain — the isolated collections/compliance decision engine runs as
    // its own service (never publicly exposed). Graphite reaches it ONLY
    // server-to-server via BrainProxyController, so the browser never talks to
    // the brain directly and the brain's service token stays server-side. The
    // base_url is the brain's stable INTERNAL address (set per-environment); the
    // token is a brain dashboard token (read scope) used to authenticate the proxy.
    'alpha_brain' => [
        'base_url' => rtrim((string) env('ALPHA_BRAIN_URL', ''), '/'),
        'token'    => env('ALPHA_BRAIN_TOKEN'),
    ],

];
