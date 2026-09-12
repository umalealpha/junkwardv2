<?php

namespace AlphaDirect\Services;

use AlphaDirect\Customer;
use AlphaDirect\Policy;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Hardened public payment-initiation service for the customer-facing
 * start.alphadirect.co.bw flows. Replaces the sprawling
 * DpoPaymentController paths (createToken across ~5 entry points) with
 * a single typed initiator per gateway, audit-logged and idempotent.
 *
 * Currently wired:
 *   - DPO    (Direct Payment Online — primary BW card gateway)
 *
 * Pending Phase 2 ports:
 *   - RealPay (direct-debit)
 *   - N-Genius (card)
 *   - VCS (card)
 *
 * Each initiator builds the gateway request, posts it, captures the
 * response in payment_activity_log, and returns:
 *   { redirect_url, reference, gateway, expires_at }
 *
 * The FE then window.location-redirects the customer to the gateway
 * URL. Gateway calls back with the result via webhooks already wired
 * on V2 (see /webhooks/dpo/push in routes/api_v1.php).
 */
class PublicPaymentService
{
    public const CONTEXTS = [
        'policy_create', 'redo_payment', 'redo_payment_motor',
        'update_card', 'clear_dues', 'upgrade',
    ];

    /**
     * DPO createToken. Returns redirect URL the FE should open.
     *
     * @param  array  $session     Validated public_session_tokens row
     * @param  string $policyNumber
     * @param  float  $amount      VAT-inclusive total in Pula
     * @param  string $context     One of self::CONTEXTS — affects redirect URLs
     * @param  string $email       Receipt email
     * @param  string $ip
     */
    public function initiateDpo(array $session, string $policyNumber, float $amount, string $context, string $email, ?string $ip = null, ?string $feOrigin = null): array
    {
        if (!in_array($context, self::CONTEXTS, true)) {
            return ['ok' => false, 'error' => 'invalid_context'];
        }
        if ($amount < 0.01) {
            return ['ok' => false, 'error' => 'invalid_amount'];
        }

        // Three reference shapes hit this endpoint:
        //   - existing policy (DOM2024…)        → look up Policy + Customer
        //   - motor quote (MQ-20260430-AB12CD)  → look up motor_quotes
        //   - bundle quote (BQ-20260527-XXXXXX) → look up bundle_quotes
        // Motor + bundle quotes are staged: the real Policy rows are minted by
        // a materialise job after the DPO push confirms payment, so for those
        // we resolve the payment envelope from the quote row, not a Policy.
        // The session's cellphone must match whichever record resolves.
        $sessionPhone = $this->normalize((string) ($session['cellphone'] ?? ''));
        $first = $last = $cell = '';

        if (str_starts_with($policyNumber, 'MQ-')) {
            $quote = DB::table('motor_quotes')->where('quote_number', $policyNumber)->first();
            if (!$quote) return ['ok' => false, 'error' => 'quote_not_found'];
            if ($this->normalize((string) ($quote->cellphone ?? '')) !== $sessionPhone) {
                Log::warning('public_payments.dpo.quote_session_mismatch', ['quote' => $policyNumber]);
                return ['ok' => false, 'error' => 'session_policy_mismatch'];
            }
            $custData = json_decode($quote->customer_payload, true) ?: [];
            $first = (string) ($custData['firstName'] ?? '');
            $last  = (string) ($custData['lastName']  ?? '');
            $cell  = $sessionPhone;
            // Override email from request only if the quote's stored email is empty.
            if (empty($email) && !empty($custData['email'])) {
                $email = (string) $custData['email'];
            }
        } elseif (str_starts_with($policyNumber, 'BQ-')) {
            $quote = DB::table('bundle_quotes')->where('quote_number', $policyNumber)->first();
            if (!$quote) return ['ok' => false, 'error' => 'quote_not_found'];
            if ($this->normalize((string) ($quote->cellphone ?? '')) !== $sessionPhone) {
                Log::warning('public_payments.dpo.bundle_session_mismatch', ['quote' => $policyNumber]);
                return ['ok' => false, 'error' => 'session_policy_mismatch'];
            }
            $custData = json_decode($quote->customer_payload, true) ?: [];
            $first = (string) ($custData['firstName'] ?? '');
            $last  = (string) ($custData['lastName']  ?? '');
            $cell  = $sessionPhone;
            if (empty($email) && !empty($custData['email'])) {
                $email = (string) $custData['email'];
            }
        } else {
            $policy = Policy::with('customer:id,firstName,lastName,cellphone,email')
                ->where('policyNumber', $policyNumber)
                ->first();

            if (!$policy) return ['ok' => false, 'error' => 'policy_not_found'];

            $customerPhone = $this->normalize((string) ($policy->customer?->cellphone ?? ''));
            if (!$customerPhone || $customerPhone !== $sessionPhone) {
                Log::warning('public_payments.dpo.session_mismatch', [
                    'policy' => $policyNumber, 'context' => $context,
                ]);
                return ['ok' => false, 'error' => 'session_policy_mismatch'];
            }

            $first = (string) ($policy->customer?->firstName ?? '');
            $last  = (string) ($policy->customer?->lastName  ?? '');
            $cell  = $customerPhone;

            // One-time-premium policies (Goods-in-Transit, premium_freq
            // 'once'): the charge is exactly the policy premium — the FE
            // renders it read-only, and this is the server-side wall against
            // a hand-crafted request charging any other figure.
            if (($policy->premium_freq ?? null) === 'once') {
                if (abs((float) $amount - (float) $policy->premium) > 0.01) {
                    Log::warning('public_payments.dpo.amount_mismatch_once', [
                        'policy' => $policyNumber, 'sent' => $amount, 'premium' => $policy->premium,
                    ]);
                    return ['ok' => false, 'error' => 'amount_mismatch'];
                }
                // Double-pay wall: a once-off policy that is no longer
                // pending (status 0) has already been paid/activated (or
                // cancelled) — there is nothing a second full premium could
                // apply to and no refund path, so refuse re-initiation
                // (delayed IPN + customer retry from a stale tab).
                if ((int) $policy->status !== 0) {
                    Log::warning('public_payments.dpo.once_already_settled', [
                        'policy' => $policyNumber, 'status' => $policy->status,
                    ]);
                    return ['ok' => false, 'error' => 'already_paid'];
                }
            }
        }

        // Callback URLs — DPO redirects the browser to the BE webhook
        // handler (`api.v1.webhooks.dpo.return`), which verifies the
        // transaction with DPO and then 302s the customer to the FE
        // thank-you / payment-failed route. Two constraints:
        //   1. Must point at the BE domain (FE doesn't expose this route).
        //   2. Must be publicly reachable HTTPS — DPO's CloudFront WAF
        //      rejects localhost/HTTP URLs with a 403, which then breaks
        //      simplexml_load_string on the HTML error body and surfaces
        //      to the FE as `gateway_bad_response`.
        // APP_URL must be the public BE domain so DPO accepts the createToken
        // request. In prod, APP_URL on ECS is already the BE domain. For local
        // dev (where APP_URL is localhost) point it at a public HTTPS tunnel
        // when testing payments — see the guard below.
        $base = rtrim((string) config('app.url'), '/');

        // DPO's CloudFront WAF 403-blocks any createToken whose callback URLs
        // are localhost / non-HTTPS, returning an HTML error body that fails
        // XML parsing and surfaces as the opaque `gateway_bad_response`. Fail
        // fast here with an actionable error instead so misconfiguration is
        // obvious (set APP_URL to a public HTTPS host, or a tunnel
        // like ngrok for local testing).
        if (!preg_match('#^https://#i', $base) || preg_match('#//(localhost|127\.0\.0\.1|\[::1\])#i', $base)) {
            Log::error('public_payments.dpo.non_public_callback_base', ['base' => $base]);
            return [
                'ok'     => false,
                'error'  => 'gateway_misconfigured',
                'detail' => 'APP_URL must be a public HTTPS URL; DPO rejects localhost/HTTP callbacks. Got: ' . $base,
            ];
        }

        // `fe` = the start host the customer came from (validated against the
        // CORS start allowlist in Helper::startSpaBase, so a forged value can
        // never redirect off-site). dpoReturn sends the browser back there.
        $fe = \AlphaDirect\Helper::startSpaBase($feOrigin);
        $qs = http_build_query([
            'policy_number' => $policyNumber,
            'amount'        => number_format($amount, 2, '.', ''),
            'context'       => $context,
            'fe'            => $fe,
        ]);
        $redirectUrl = "{$base}/api/v1/webhooks/dpo/return?{$qs}&result=success";
        $backUrl     = "{$base}/api/v1/webhooks/dpo/return?{$qs}&result=back";
        $declinedUrl = "{$base}/api/v1/webhooks/dpo/return?{$qs}&result=declined";

        $companyToken = env('COMPANY_TOKEN', '');
        $currency     = env('PAYMENT_CURRENCY', 'BWP');
        $serviceType  = env('SERVICE_TYPE', '3854');   // legacy default
        $countryCode  = env('CUSTOMER_COUNTRY', 'BW');
        // Normalise to the bare host — DPO_URL is set to the full /API/v6/
        // endpoint in some environments, which would otherwise produce a
        // doubled "/API/v6/API/v6/" path when we append below.
        $dpoUrl       = preg_replace('#/API/v\d+/?$#i', '', rtrim((string) env('DPO_URL', 'https://secure.3gdirectpay.com'), '/'));
        $payUrl       = $this->dpoPayUrl();

        if (empty($companyToken)) {
            return ['ok' => false, 'error' => 'gateway_misconfigured', 'detail' => 'COMPANY_TOKEN not set'];
        }

        // Hand-rolled XML — DPO requires this exact shape, the field order
        // is significant. xmlspecialchars() the user-provided strings so
        // an apostrophe in a name doesn't break the envelope.
        $xml = '<?xml version="1.0" encoding="utf-8"?>'
            . '<API3G>'
            . '<CompanyToken>' . htmlspecialchars($companyToken, ENT_XML1) . '</CompanyToken>'
            . '<Request>createToken</Request>'
            . '<Transaction>'
            . '<PaymentAmount>'   . number_format($amount, 2, '.', '') . '</PaymentAmount>'
            . '<PaymentCurrency>' . htmlspecialchars($currency, ENT_XML1) . '</PaymentCurrency>'
            . '<CompanyRef>'      . htmlspecialchars($policyNumber, ENT_XML1) . '</CompanyRef>'
            . '<RedirectURL>'     . htmlspecialchars($redirectUrl, ENT_XML1) . '</RedirectURL>'
            . '<BackURL>'         . htmlspecialchars($backUrl, ENT_XML1) . '</BackURL>'
            . '<DeclinedURL>'     . htmlspecialchars($declinedUrl, ENT_XML1) . '</DeclinedURL>'
            . '<PTL>2</PTL>'
            . '<PTLtype>hours</PTLtype>'
            . '<TransactionChargeType>1</TransactionChargeType>'
            . '<customerFirstName>' . htmlspecialchars($first, ENT_XML1) . '</customerFirstName>'
            . '<customerLastName>'  . htmlspecialchars($last, ENT_XML1)  . '</customerLastName>'
            . '<customerCountry>'   . htmlspecialchars($countryCode, ENT_XML1) . '</customerCountry>'
            . '<customerPhone>'     . htmlspecialchars($cell, ENT_XML1) . '</customerPhone>'
            . '<customerDialCode>'  . htmlspecialchars($countryCode, ENT_XML1) . '</customerDialCode>'
            . '<customerEmail>'     . htmlspecialchars($email, ENT_XML1) . '</customerEmail>'
            . '<AllowRecurrent>1</AllowRecurrent>'
            . '</Transaction>'
            . '<Services><Service>'
            . '<ServiceType>'        . htmlspecialchars($serviceType, ENT_XML1) . '</ServiceType>'
            . '<ServiceDescription>' . htmlspecialchars($context, ENT_XML1) . '</ServiceDescription>'
            . '<ServiceDate>'        . Carbon::now()->format('Y/m/d') . '</ServiceDate>'
            . '</Service></Services>'
            . '</API3G>';

        $endpoint = $dpoUrl . '/API/v6/';
        $curl = curl_init($endpoint);
        curl_setopt_array($curl, [
            CURLOPT_HTTPHEADER     => ['Content-Type: text/xml'],
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $xml,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => env('CURL_VERIFY_SSL', config('app.env') === 'production'),
            CURLOPT_SSL_VERIFYHOST => env('CURL_VERIFY_SSL', config('app.env') === 'production') ? 2 : 0,
        ]);
        $body = curl_exec($curl);
        $err  = curl_error($curl);
        curl_close($curl);

        // For motor quotes there's no Policy row yet — pass the quote number
        // as companyRef so the audit log keeps the trail.
        $this->logAttempt('dpo.createToken', $policy ?? null, $amount, $xml, $body, $err, $context, $ip, $policyNumber);

        if ($err) {
            return ['ok' => false, 'error' => 'gateway_unreachable', 'detail' => $err];
        }

        $parsed = @simplexml_load_string($body);
        if (!$parsed) {
            return ['ok' => false, 'error' => 'gateway_bad_response'];
        }
        $arr = json_decode(json_encode($parsed), true);

        // DPO returns Result=000 + TransToken on success.
        if (($arr['Result'] ?? null) !== '000' || empty($arr['TransToken'])) {
            return [
                'ok'      => false,
                'error'   => 'gateway_declined',
                'message' => $arr['ResultExplanation'] ?? 'Payment gateway declined the request.',
            ];
        }

        $token = (string) $arr['TransToken'];
        $sep   = str_contains($payUrl, '?') ? '&' : '?';

        return [
            'ok'           => true,
            'gateway'      => 'dpo',
            'reference'    => $token,
            'redirect_url' => $payUrl . $sep . 'ID=' . urlencode($token),
            'expires_at'   => Carbon::now()->addHours(2)->toIso8601String(),
        ];
    }

    /**
     * RealPay direct-debit lives at /api/v1/public/realpay/initiate
     * (Api\Public\RealpayController). It's a contract-create flow, not a
     * card-redirect like DPO, so it stayed on its own namespace. Leaving
     * this stub for compatibility with anything that wires through here.
     */
    public function initiateRealpay(array $session, string $policyNumber, float $amount, string $context, array $bankDetails): array
    {
        return [
            'ok'       => false,
            'error'    => 'use_dedicated_endpoint',
            'message'  => 'RealPay direct-debit is exposed at POST /api/v1/public/realpay/initiate.',
        ];
    }

    /**
     * N-Genius (Network International) card-redirect initiator.
     *
     * Two-stage:
     *   1. OAuth token via Basic auth from NGENIUS_API_KEY
     *   2. Create SALE order with redirect URL in _links.payment.href
     *
     * Ported from NgeniusPaymentController::NgeniusPayment + pay (V8).
     * Amount goes in CENTS (× 100). N-Genius redirects back to
     * GRAPHITE_URL/api/NgeniusResponse on success / NgeniusCancelResponse on cancel
     * — webhook handlers already exist in V2.
     */
    public function initiateNgenius(array $session, string $policyNumber, float $amount, string $context, string $email): array
    {
        if (!in_array($context, self::CONTEXTS, true)) {
            return ['ok' => false, 'error' => 'invalid_context'];
        }
        if ($amount < 0.01) {
            return ['ok' => false, 'error' => 'invalid_amount'];
        }

        $policy = Policy::with('customer:id,firstName,lastName,cellphone,email')
            ->where('policyNumber', $policyNumber)
            ->first();
        if (!$policy) return ['ok' => false, 'error' => 'policy_not_found'];

        if ($block = $this->refuseNonDpoForOnce($policy, 'ngenius')) return $block;

        $sessionPhone  = $this->normalize((string) ($session['cellphone'] ?? ''));
        $customerPhone = $this->normalize((string) ($policy->customer?->cellphone ?? ''));
        if (!$customerPhone || $customerPhone !== $sessionPhone) {
            Log::warning('public_payments.ngenius.session_mismatch', [
                'policy' => $policyNumber, 'context' => $context,
            ]);
            return ['ok' => false, 'error' => 'session_policy_mismatch'];
        }

        $apiKey    = (string) env('NGENIUS_API_KEY');
        $tokenUrl  = rtrim((string) env('NGENIUS_TOKEN_URL'), '/');
        $outlet    = (string) env('NGENIUS_TWOSTAGE_OUTLETS_KEY');
        $graphite  = rtrim((string) (env('GRAPHITE_URL') ?: env('APP_URL')), '/');
        if (!$apiKey || !$tokenUrl || !$outlet) {
            return ['ok' => false, 'error' => 'gateway_misconfigured', 'detail' => 'NGENIUS_* env vars missing'];
        }

        $verifySsl = filter_var(env('CURL_VERIFY_SSL', config('app.env') === 'production'), FILTER_VALIDATE_BOOLEAN);

        // ── Stage 1: OAuth token ──────────────────────────────────────
        $idCurl = curl_init($tokenUrl . '/identity/auth/access-token');
        curl_setopt_array($idCurl, [
            CURLOPT_HTTPHEADER     => [
                'content-type: application/vnd.ni-identity.v1+json',
                'accept: application/vnd.ni-identity.v1+json',
                'authorization: Basic ' . $apiKey,
            ],
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => '',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => $verifySsl,
            CURLOPT_SSL_VERIFYHOST => $verifySsl ? 2 : 0,
        ]);
        $idBody = curl_exec($idCurl);
        $idErr  = curl_error($idCurl);
        curl_close($idCurl);

        if ($idErr) {
            $this->logAttempt('ngenius.token', $policy, $amount, 'oauth', $idBody, $idErr, $context, null, $policyNumber);
            return ['ok' => false, 'error' => 'gateway_unreachable', 'detail' => $idErr];
        }
        $idJson = json_decode((string) $idBody, true) ?: [];
        $accessToken = (string) ($idJson['access_token'] ?? '');
        if (!$accessToken) {
            $this->logAttempt('ngenius.token', $policy, $amount, 'oauth', $idBody, 'no_access_token', $context, null, $policyNumber);
            return ['ok' => false, 'error' => 'gateway_bad_response'];
        }

        // ── Stage 2: create SALE order ────────────────────────────────
        $first = (string) ($policy->customer?->firstName ?? '');
        $last  = (string) ($policy->customer?->lastName ?? '');
        $order = [
            'action' => 'SALE',
            'amount' => [
                'currencyCode' => (string) env('PAYMENT_CURRENCY', 'BWP'),
                'value'        => (int) round($amount * 100),
            ],
            'emailAddress' => $email ?: ($policy->customer?->email ?? null),
            'merchantAttributes' => [
                'redirectUrl'             => $graphite . '/api/NgeniusResponse',
                'cancelUrl'               => $graphite . '/api/NgeniusCancelResponse',
                'merchantOrderReference'  => $outlet,
                'skip3DS'                 => true,
                'skipConfirmationPage'    => true,
                'policyNumber'            => $policyNumber,
                'leadSource'              => 'start.alphadirect.co.bw',
                'context'                 => $context,
            ],
            'billingAddress' => [
                'firstName' => $first,
                'lastName'  => $last,
            ],
        ];

        $orderUrl = $tokenUrl . '/transactions/outlets/' . $outlet . '/orders';
        $payCurl  = curl_init($orderUrl);
        $payload  = json_encode($order);
        curl_setopt_array($payCurl, [
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/vnd.ni-payment.v2+json',
                'Accept: application/vnd.ni-payment.v2+json',
            ],
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => $verifySsl,
            CURLOPT_SSL_VERIFYHOST => $verifySsl ? 2 : 0,
        ]);
        $payBody = curl_exec($payCurl);
        $payErr  = curl_error($payCurl);
        curl_close($payCurl);

        $this->logAttempt('ngenius.createOrder', $policy, $amount, $payload, $payBody, $payErr, $context, null, $policyNumber);

        if ($payErr) {
            return ['ok' => false, 'error' => 'gateway_unreachable', 'detail' => $payErr];
        }
        $payJson = json_decode((string) $payBody, true) ?: [];
        $href = $payJson['_links']['payment']['href'] ?? null;
        if (!$href) {
            return [
                'ok'      => false,
                'error'   => 'gateway_declined',
                'message' => $payJson['errors'][0]['message'] ?? 'N-Genius did not return a payment URL.',
            ];
        }

        return [
            'ok'           => true,
            'gateway'      => 'ngenius',
            'reference'    => (string) ($payJson['reference'] ?? $payJson['merchantAttributes']['merchantOrderReference'] ?? ''),
            'redirect_url' => $href,
            'expires_at'   => Carbon::now()->addHours(2)->toIso8601String(),
        ];
    }

    /**
     * VCS (Virtual Card Services) once-off-card initiator.
     *
     * Quirk: VCS does NOT return a clean redirect URL. Its endpoint
     * (https://www.vcs.co.za/vvonline/vcspay.aspx) returns an HTML body
     * that the customer's browser is supposed to render — usually a
     * 3DS form or an immediate redirect. The FE must POST to a
     * data: URL or write the HTML to a hidden iframe.
     *
     * Ported from PaymentController::vcsOnceOffForStart (V8).
     * Returns { ok, gateway: 'vcs', reference, redirect_html, ... }.
     */
    public function initiateVcs(array $session, string $policyNumber, float $amount, string $context, string $email): array
    {
        if (!in_array($context, self::CONTEXTS, true)) {
            return ['ok' => false, 'error' => 'invalid_context'];
        }
        if ($amount < 0.01) {
            return ['ok' => false, 'error' => 'invalid_amount'];
        }

        $policy = Policy::with('customer:id,firstName,middleName,lastName,cellphone,email')
            ->where('policyNumber', $policyNumber)
            ->first();
        if (!$policy) return ['ok' => false, 'error' => 'policy_not_found'];

        if ($block = $this->refuseNonDpoForOnce($policy, 'vcs')) return $block;

        $sessionPhone  = $this->normalize((string) ($session['cellphone'] ?? ''));
        $customerPhone = $this->normalize((string) ($policy->customer?->cellphone ?? ''));
        if (!$customerPhone || $customerPhone !== $sessionPhone) {
            Log::warning('public_payments.vcs.session_mismatch', [
                'policy' => $policyNumber, 'context' => $context,
            ]);
            return ['ok' => false, 'error' => 'session_policy_mismatch'];
        }

        $terminalId = (string) env('VCS_TERMINAL_ID');
        $graphite   = rtrim((string) (env('GRAPHITE_URL') ?: env('APP_URL')), '/');
        $vcsUrl     = (string) env('VCS_PAY_URL', 'https://www.vcs.co.za/vvonline/vcspay.aspx');
        if (!$terminalId) {
            return ['ok' => false, 'error' => 'gateway_misconfigured', 'detail' => 'VCS_TERMINAL_ID not set'];
        }

        // Reference number — legacy generated via generate_string() helper.
        // Use a deterministic but unique shape here: VCS-{policy}-{random}.
        $reference = 'VCS-' . substr($policyNumber, 0, 16) . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));

        $first  = (string) ($policy->customer?->firstName ?? '');
        $middle = (string) ($policy->customer?->middleName ?? '');
        $last   = (string) ($policy->customer?->lastName ?? '');
        $cardName = trim("{$first} {$middle} {$last}");

        $form = [
            'p1'             => $terminalId,
            'p2'             => $reference,
            'p3'             => 'Once off for ' . $policyNumber,
            'p4'             => number_format($amount, 2, '.', ''),
            'p5'             => (string) env('PAYMENT_CURRENCY', 'BWP'),
            'p8'             => $customerPhone,
            'CardholderName' => $cardName,
            'p10'            => $graphite . '/paymentpending?p2=' . $reference,
            'p11'            => $email ?: ($policy->customer?->email ?? 'accounts@alphadirect.co.bw'),
            'p13'            => number_format($amount, 2, '.', ''),
            'm1'             => $context,
            'Budget'         => 'N',
            'Mobile'         => 'Y',
            'UrlsProvided'   => 'Y',
            'ApprovedUrl'    => $graphite . '/api/acceptedCallback',
            'DeclinedUrl'    => $graphite . '/api/declinedCallback',
        ];

        $verifySsl = filter_var(env('CURL_VERIFY_SSL', config('app.env') === 'production'), FILTER_VALIDATE_BOOLEAN);
        $curl = curl_init($vcsUrl);
        curl_setopt_array($curl, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($form),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => $verifySsl,
            CURLOPT_SSL_VERIFYHOST => $verifySsl ? 2 : 0,
        ]);
        $body = curl_exec($curl);
        $err  = curl_error($curl);
        curl_close($curl);

        $this->logAttempt('vcs.onceOff', $policy, $amount, json_encode($form), $body, $err, $context, null, $policyNumber);

        if ($err) {
            return ['ok' => false, 'error' => 'gateway_unreachable', 'detail' => $err];
        }
        $html = trim(preg_replace('/\s\s+/', ' ', (string) $body));
        if ($html === '') {
            return ['ok' => false, 'error' => 'gateway_bad_response'];
        }

        return [
            'ok'            => true,
            'gateway'       => 'vcs',
            'reference'     => $reference,
            'redirect_html' => $html,   // FE renders this into a hidden frame / data URL
            'redirect_url'  => null,    // VCS doesn't return a clean URL — keep contract shape
            'expires_at'    => Carbon::now()->addHours(2)->toIso8601String(),
        ];
    }

    // ─── Internals ──────────────────────────────────────────────────────

    /**
     * One-time-premium policies (Goods-in-Transit, premium_freq 'once') may
     * ONLY be paid via DPO — business rule. The DPO path pins the amount to
     * the policy premium and its webhook knows the 'once' semantics (14-day
     * cover, no schedule); the legacy N-Genius/VCS capture handlers do
     * neither: they activate any status-0 policy at whatever amount was
     * initiated, stamp expiry_date = now + 1 YEAR and dispatch the
     * recurring-schedule event. Refusing here closes both the underpayment
     * hole and the accidental year-of-cover/schedule corruption.
     */
    private function refuseNonDpoForOnce(Policy $policy, string $gateway): ?array
    {
        if (($policy->premium_freq ?? null) !== 'once') return null;

        Log::warning("public_payments.{$gateway}.once_policy_refused", [
            'policy' => $policy->policyNumber,
        ]);
        return ['ok' => false, 'error' => 'gateway_not_allowed', 'detail' => 'One-time-premium policies are payable via DPO only.'];
    }

    /**
     * Hosted-payment-page URL the customer is sent to with ?ID=<TransToken>.
     *
     * DPO's legacy pages on secure.3gdirectpay.com are unreliable: pay.asp
     * 302s to pay.php, which (observed 2026-09-09) answers with a malformed
     * relative Location — `https://secure.3gdirectpay.com/https://pay.3gdirectpay.com/?ID=…`
     * — and the customer lands on an IIS "Server Error in '/' Application"
     * page instead of paying. payv2.php merely 302s to the new host. So any
     * DPO_PAY_URL that points at a legacy page on secure.3gdirectpay.com is
     * normalised to the new hosted page https://pay.3gdirectpay.com/, which
     * serves the payment form directly. A DPO_PAY_URL on any other host
     * (e.g. a sandbox) is used as-is.
     */
    private function dpoPayUrl(): string
    {
        $configured = trim((string) env('DPO_PAY_URL', ''));
        $host = strtolower((string) parse_url($configured, PHP_URL_HOST));
        if ($configured === '' || $host === '' || $host === 'secure.3gdirectpay.com') {
            return 'https://pay.3gdirectpay.com/';
        }
        return $configured;
    }

    private function logAttempt(string $api, ?Policy $policy, float $amount, string $request, ?string $response, ?string $error, string $context, ?string $ip, ?string $companyRef = null): void
    {
        try {
            DB::table('payment_activity_logs')->insert([
                'policy_number'   => $companyRef ?? $policy?->policyNumber,
                'customer_id'     => $policy?->customer_id,
                'amount'          => $amount,
                'api_name'        => $api,
                'reason'          => $error ?: $context,
                'request_json'    => $this->toJsonBlob($request),
                'response_json'   => $this->toJsonBlob((string) $response),
                'created_at'      => Carbon::now(),
                'updated_at'      => Carbon::now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('public_payments.audit_log_failed', ['msg' => $e->getMessage()]);
        }
    }

    /**
     * payment_activity_logs has CHECK (json_valid(request_json/response_json)).
     * DPO speaks XML, and returns HTML on outages — wrap in a JSON envelope
     * so legacy log readers keep working without us doing a real XML→JSON parse.
     */
    private function toJsonBlob(string $payload): ?string
    {
        if ($payload === '') return null;
        $truncated = mb_substr($payload, 0, 60000);
        return json_encode(['raw' => $truncated], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function normalize(string $cellphone): string
    {
        $digits = preg_replace('/\D/', '', $cellphone);
        if (str_starts_with($digits, '267') && strlen($digits) === 11) return substr($digits, 3);
        return $digits;
    }
}
