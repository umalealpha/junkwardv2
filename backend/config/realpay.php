<?php

/*
|--------------------------------------------------------------------------
| RealPay debit-order configuration
|--------------------------------------------------------------------------
|
| These values were previously read via raw env() calls scattered through the
| RealPay controllers, services and cron commands. Under `php artisan
| config:cache` (standard in production) env() returns NULL outside of config
| files, which made the RealPay START OAuth fail with "Could not authenticate
| with RealPay (START)". Mapping them here — where env() is evaluated at cache
| time — lets the rest of the app read them via config() and survive caching.
|
| Two platforms are configured:
|   - `legacy` : the original RealPay merchant (REALPAY_BASE_URL / CLIENT_AUTH),
|                used by motor-comprehensive (product_id 3).
|   - `start`  : the START platform (REALPAY_START_BASE_URL / START_CLIENT_AUTH),
|                used by instant products (product_id != 3).
|
| `fnb_product` is shared across both platforms (the legacy FNB product code is
| used even when the START base URL is targeted — preserving prior behaviour).
|
| START falls back to the legacy RealPay values when its own env vars are not
| set. On environments where the START platform mirrors the legacy merchant
| (e.g. UAT, where both point at uat.realpaycollect.com with the same client
| auth), the START-specific vars may be absent — without this fallback
| `clientAuthForInstantProduct()` sees empty config and returns null, surfacing
| as "Could not authenticate with RealPay (START)". The fallback keeps instant
| products working anywhere the legacy creds are configured, and is a no-op when
| the START vars ARE set (they take precedence).
|
*/

return [

    // Legacy RealPay platform (motor comprehensive / product_id 3).
    'base_url'           => env('REALPAY_BASE_URL'),
    'client_auth'        => env('CLIENT_AUTH'),
    'merchant'           => env('REALPAY_MERCHANT'),
    'product'            => env('REALPAY_PRODUCT'),
    'version'            => env('REALPAY_VERSION'),

    // Shared FNB product code (used under both the legacy and START base URLs).
    'fnb_product'        => env('REALPAY_FNB_PRODUCT'),

    // Alternate auth key referenced in a single legacy call site; kept distinct
    // so behaviour is preserved exactly.
    'realpay_client_auth' => env('REALPAY_CLIENT_AUTH'),

    // START RealPay platform (instant products / product_id != 3).
    // Each value falls back to the legacy RealPay equivalent when the
    // START-specific env var is empty/absent (see header note).
    'start' => [
        'base_url'    => env('REALPAY_START_BASE_URL') ?: env('REALPAY_BASE_URL'),
        'client_auth' => env('START_CLIENT_AUTH')      ?: env('CLIENT_AUTH'),
        'merchant'    => env('REALPAY_START_MERCHANT')  ?: env('REALPAY_MERCHANT'),
        'product'     => env('REALPAY_START_PRODUCT')   ?: env('REALPAY_PRODUCT'),
        'version'     => env('REALPAY_START_VERSION')   ?: env('REALPAY_VERSION'),
    ],

    /*
    |----------------------------------------------------------------------
    | Collect Now — in-flight debit guard
    |----------------------------------------------------------------------
    |
    | A RealPay one-off (OOFF) instalment is only ACCEPTED synchronously; the
    | bank result arrives later on the webhook. Until it does, the premiums sit
    | at scheduled_transactions.status 1, which still counts as outstanding —
    | so without a guard a second Collect Now press debits the customer again.
    |
    | PayNowService refuses a RealPay collection while an earlier accepted debit
    | from within this window has not yet produced a payment_transactions row.
    | Set to 0 to disable the guard.
    |
    */
    'collect_now_inflight_hours' => (int) env('REALPAY_COLLECT_NOW_INFLIGHT_HOURS', 72),

    /*
    |----------------------------------------------------------------------
    | Mandate lifecycle tracking (realpay_mandates)
    |----------------------------------------------------------------------
    |
    | Bookkeeping only. Nothing under this key can activate a policy —
    | activation stays in RealPayController::updateInstallment(). See
    | AlphaDirect\Services\RealPayMandateService and
    | docs/REALPAY_MANDATE_IMPLEMENTATION.md.
    |
    */
    'mandate' => [

        // Master switch. On by default: the tracking writes only to the two new
        // realpay_mandate* tables and the service no-ops when they are absent,
        // so it cannot affect an environment that has not migrated yet.
        'enabled' => filter_var(env('REALPAY_MANDATE_ENABLED', true), FILTER_VALIDATE_BOOLEAN),

        // Refuse a second contract when the policy already holds an `active`
        // mandate — a first collection has succeeded against it, so another
        // contract is an unambiguous double debit.
        'block_on_active' => filter_var(env('REALPAY_MANDATE_BLOCK_ON_ACTIVE', true), FILTER_VALIDATE_BOOLEAN),

        // Same refusal for `registered` / `redirected` / `authenticated`
        // mandates. Off by default: those states can be stale if a contract was
        // cancelled outside the paths that report back here, and a false
        // "already active" permanently blocks reprocessing — the failure the
        // comment on RealPayController::contractIsActive() documents. Left off,
        // the guard shadow-logs instead, so the real duplicate rate is
        // measurable before anyone enforces it.
        'block_on_registered' => filter_var(env('REALPAY_MANDATE_BLOCK_ON_REGISTERED', false), FILTER_VALIDATE_BOOLEAN),
    ],

    /*
    |----------------------------------------------------------------------
    | DebiCheck / eMandate (NOT YET IMPLEMENTED)
    |----------------------------------------------------------------------
    |
    | Placeholders for the hosted-eMandate journey. The endpoint, its field
    | names, the webhook events and the mandate type are NOT in this codebase
    | and are deliberately not guessed — see §4 of
    | docs/REALPAY_MANDATE_IMPLEMENTATION.md. Confirm `max_collection_multiplier`
    | with RealPay before changing it: it caps what we may ever collect.
    |
    */
    'debicheck' => [
        'mandate_type'              => env('REALPAY_DEBICHECK_TYPE'),          // TT1 / TT2 / TT3 — unconfirmed
        'max_collection_multiplier' => env('REALPAY_DEBICHECK_MAX_MULTIPLIER', 1.0),
    ],

    /*
    |----------------------------------------------------------------------
    | Duplicate-contract guard (cancel-before-create)
    |----------------------------------------------------------------------
    |
    | Reprocess Payment and Update Expired Card Details both end in "create a
    | RealPay contract for this policy". Run either of them twice and the policy
    | ends up with two live contracts and the customer is debited twice.
    |
    | RealPayDuplicateContractGuard closes that: before a contract is created it
    | asks RealPay what is already live on the policy, cancels it, verifies the
    | cancellation, and refuses the creation when the cancellation did not take.
    |
    | 'enabled'             — master switch. Off = the old create-first
    |                         behaviour, kept only as an operational escape
    |                         hatch.
    | 'verify_after_cancel' — re-read the portal after cancelling and refuse if
    |                         anything is still active. One extra round-trip per
    |                         cancellation; it is what makes "only one active
    |                         contract remains" an assertion rather than a hope.
    | 'block_when_unverifiable'
    |                       — RealPay unreachable AND our own records show an
    |                         active contract. true (default) refuses the
    |                         creation, because a create that cannot be preceded
    |                         by a cancel is exactly the double-debit case.
    |
    */
    'duplicate_guard' => [
        'enabled'                 => filter_var(env('REALPAY_DUPLICATE_GUARD_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
        'verify_after_cancel'     => filter_var(env('REALPAY_DUPLICATE_GUARD_VERIFY', true), FILTER_VALIDATE_BOOLEAN),
        'block_when_unverifiable' => filter_var(env('REALPAY_DUPLICATE_GUARD_BLOCK_UNVERIFIABLE', true), FILTER_VALIDATE_BOOLEAN),
    ],

    /*
    |----------------------------------------------------------------------
    | Cancel the debit order when the policy is cancelled
    |----------------------------------------------------------------------
    |
    | Cancelling a policy used to leave its RealPay contract live — back-office
    | cancelled every one by hand, and until they got to it the customer kept
    | being debited. RealPayPolicyCancellationService closes that: every
    | cancellation journey asks RealPay what is still live on the policy,
    | cancels it, and records the outcome.
    |
    | 'enabled'             — master switch. Off = the old behaviour (policy
    |                         cancelled, contract left running), kept only as an
    |                         operational escape hatch. Turning it off is a
    |                         decision to debit cancelled customers.
    | 'verify_after_cancel' — re-read the portal after cancelling and report the
    |                         policy as still outstanding if anything is active.
    |                         One extra round-trip; it is what makes "no further
    |                         debits" an assertion rather than a hope.
    |
    | A cancellation that fails never blocks the policy cancellation. It is
    | logged under '[REALPAY POLICY CANCEL]' and left at
    | realpay_cancel_requests.cancel_status = 2, which
    | `realpay:retry-policy-cancellations` re-drives.
    |
    | 'retry_apply'         — whether the hourly scheduled retry actually calls
    |                         RealPay. True by default, unlike the bulk
    |                         dead-mandate sweep: these are cancellations a user
    |                         explicitly asked for and that failed, so leaving
    |                         them report-only would just re-create the manual
    |                         queue this work removes. False makes the scheduled
    |                         run write its CSV and touch nothing.
    |
    */
    'cancel_with_policy' => [
        'enabled'             => filter_var(env('REALPAY_CANCEL_WITH_POLICY', true), FILTER_VALIDATE_BOOLEAN),
        'verify_after_cancel' => filter_var(env('REALPAY_CANCEL_WITH_POLICY_VERIFY', true), FILTER_VALIDATE_BOOLEAN),
        'retry_apply'         => filter_var(env('REALPAY_CANCEL_RETRY_APPLY', true), FILTER_VALIDATE_BOOLEAN),
    ],

    /*
    |----------------------------------------------------------------------
    | Billing-date sync (policy billing date → RealPay debit schedule)
    |----------------------------------------------------------------------
    |
    | RealPay holds the instalment schedule and debits on InstalmentActionDate
    | by itself — Graphite is never asked. Editing a policy's billing date used
    | to write policies.billingStartDate and nothing else, so the policy read
    | "28th" while the customer was still debited on the 18th (MIS2026213635).
    |
    | RealPayBillingDateSynchroniser closes that: a billing-date edit on a
    | policy with a live contract queues an "update all instalments" job
    | (realpay_logs event 3, drained hourly by processrealpaypayment:cron) that
    | moves the schedule onto the new day, and corrects
    | customer_banking.billing_day at the same time. It updates the instalments
    | in place — it never cancels or recreates the contract, so it cannot
    | produce a duplicate debit order.
    |
    | 'enabled'      — master switch. Off = the old behaviour (billing date
    |                  changes in Graphite only), kept as an escape hatch.
    | 'audit_fix'    — whether the daily `realpay:audit-billing-dates` run
    |                  queues corrections for the policies it finds, or only
    |                  writes its CSV. Off by default: the backlog should be
    |                  reviewed before it is corrected en masse.
    |
    */
    'billing_date_sync' => [
        'enabled'   => filter_var(env('REALPAY_BILLING_DATE_SYNC', true), FILTER_VALIDATE_BOOLEAN),
        'audit_fix' => filter_var(env('REALPAY_BILLING_DATE_AUDIT_FIX', false), FILTER_VALIDATE_BOOLEAN),
    ],

    /*
    |----------------------------------------------------------------------
    | Rule A — dead-mandate stop (realpay:stop-dead-mandates)
    |----------------------------------------------------------------------
    |
    | false (default): the hourly scheduled run is REPORT-ONLY — it writes the
    | would-cancel / Rule-B-candidate CSV and touches nothing on RealPay.
    | true: the scheduled run passes --apply and actually cancels dead
    | mandates (limit 100/run). Flip only after the first report-only CSVs
    | have been reviewed.
    |
    */
    'mandate_stop_apply' => filter_var(env('REALPAY_MANDATE_STOP_APPLY', false), FILTER_VALIDATE_BOOLEAN),

];
