<?php

/**
 * FAC Register configuration.
 *
 * Alert recipients live HERE, not hard-coded in the services, so that changing
 * who gets told a premium has landed is a config change and not a deploy.
 *
 * The mailboxes below are PLACEHOLDERS until Reinsurance confirms the named
 * recipients. Any list left empty means the event is still recorded on the
 * placement trail — it just cannot be emailed. The trail never depends on mail.
 */
return [

    // ── Alert recipients ─────────────────────────────────────────────────
    // Comma-separated in .env, e.g. FAC_DEBTORS_RECIPIENTS="a@x.co.bw,b@x.co.bw"
    'recipients' => [
        'debtors'    => array_filter(array_map('trim', explode(',', (string) env('FAC_DEBTORS_RECIPIENTS', '')))),
        'ri_team'    => array_filter(array_map('trim', explode(',', (string) env('FAC_RI_TEAM_RECIPIENTS', '')))),
        'uw_manager' => array_filter(array_map('trim', explode(',', (string) env('FAC_UW_MANAGER_RECIPIENTS', '')))),
        'finance'    => array_filter(array_map('trim', explode(',', (string) env('FAC_FINANCE_RECIPIENTS', '')))),
        // Always copied on outbound slips so there is an internal record.
        'slip_bcc'   => array_filter(array_map('trim', explode(',', (string) env('FAC_SLIP_BCC', '')))),
    ],

    'from' => [
        'address' => env('FAC_FROM_ADDRESS', 'reinsurance@alphadirect.co.bw'),
        'name'    => env('FAC_FROM_NAME', 'Alpha Direct Reinsurance'),
    ],

    // ── Money ────────────────────────────────────────────────────────────
    'vat_rate'          => (float) env('FAC_VAT_RATE', 0.14),   // Botswana VAT 14%
    'base_currency'     => 'BWP',
    // Gross − (commission + net) may not exceed this, in currency units.
    'reconcile_tolerance' => 0.01,

    // ── PPW — Premium Payment Warranty ───────────────────────────────────
    'ppw' => [
        'warn_days_before' => (int) env('FAC_PPW_WARN_DAYS', 7),
    ],

    // ── Settlement ───────────────────────────────────────────────────────
    // Days after the client premium lands by which we should have settled the
    // reinsurer, when the counterparty carries no explicit terms.
    'default_settlement_days' => (int) env('FAC_SETTLEMENT_DAYS', 30),

    // ── Slips ────────────────────────────────────────────────────────────
    'slips' => [
        // Slips are NEVER emailed automatically. Generation is automatic;
        // sending is an explicit, permissioned, human action. Flipping this to
        // true is a deliberate decision — an outbound document to a reinsurer
        // is a contractual communication.
        'auto_send'      => (bool) env('FAC_SLIP_AUTO_SEND', false),
        's3_prefix'      => 'fac',
        'disk'           => env('FAC_SLIP_DISK', 's3'),
    ],

    // ── Documents attached to a placement ────────────────────────────────
    //
    // Signed slips, proofs of payment, RI payment advices. The disk was
    // hardcoded to s3, which made every attachment untestable on an environment
    // whose S3 credentials are not working — and the test environment's are not:
    // "InvalidAccessKeyId: The AWS Access Key Id you provided does not exist in
    // our records", reported 18 Aug 2026 against fac/slips/2026-005.
    //
    // Production stays on s3 by default. A test environment can set
    // FAC_DOCUMENT_DISK=public and exercise the whole flow without credentials.
    'documents' => [
        'disk' => env('FAC_DOCUMENT_DISK', 's3'),
    ],

    // ── Auto-generated entries ───────────────────────────────────────────
    'auto_entries' => [
        // Instalment placements (Monthly / Quarterly policies) roll forward from
        // the slip's terms as each new policy transaction is booked. Generated
        // rows land as status = draft and MUST be confirmed by a human before
        // they count towards the payable.
        'enabled'          => (bool) env('FAC_AUTO_ENTRIES', true),
        'create_as_status' => 'draft',
        'lookback_days'    => (int) env('FAC_AUTO_ENTRIES_LOOKBACK', 45),
    ],

    // ── Coverage scan: "is this policy FAC'ed?" ──────────────────────────
    'coverage' => [
        // policy_reinsurance.type_id, as used by PolicyController::reinsurance
        //   1 = Quota Share · 3 = Net Retention · 4 = Surplus
        //   5 = Facultative (the share the treaty programme could not absorb)
        //   6 = Facultative Placement
        'facultative_type_ids' => [5, 6],
        // A required-vs-placed gap below this (in Pula of ceded premium) is not
        // worth chasing; anything above it is reported as uncovered.
        'materiality_bwp'      => (float) env('FAC_COVERAGE_MATERIALITY', 100.0),
        // The gap is now measured in SUM INSURED — the question is whether the
        // exposure is covered, which premium cannot answer. Shortfalls below
        // this are rounding, not exposure.
        'materiality_si'       => (float) env('FAC_COVERAGE_MATERIALITY_SI', 1000.0),
        // BR-STD-01: facultative placement is mandatory where the sum insured
        // exceeds this, and must be confirmed before the risk is treated as
        // covered. Below it, sum insured left outside the treaty may legitimately
        // be retained net, so it is reported but not escalated.
        'mandatory_si_threshold' => (float) env('FAC_MANDATORY_SI', 50000000.0),
    ],

    /*
    |---------------------------------------------------------------------------
    | Placement mandates
    |---------------------------------------------------------------------------
    |
    | The conditions the 2026/27 treaties attach to their capacity, as recorded in
    | the Alpha Direct Capacities Table 2026/27 (built solely from the two signed
    | slips — J.B. Boda General Quota Share amended signed slip, and the Motor
    | Quota Share slip with Continental Re as lead).
    |
    | ONLY the two conditions that can be tested from what the register already
    | captures are here. The others in that section of the table — minimum MPL 50%
    | on referral, co-insurance 50%, the engineering referral thresholds and the
    | advanced loss of profits ceiling — need fields the placement does not hold,
    | and where they should be captured is an Underwriting decision rather than a
    | development one. They are deliberately absent rather than half-implemented.
    |
    | The 25%-of-treaty-limit restriction is also absent: the limit it is 25% OF is
    | the open question on the cession bifurcation, so a test against it would be a
    | test against a number nobody has confirmed.
    |
    */
    'mandates' => [
        // Capacities Table, "Policy period (both treaties)": policies issued or
        // renewed for a period exceeding twelve months plus odd time, not
        // exceeding eighteen months in all, are excluded from the treaty.
        'max_policy_months' => (float) env('FAC_MAX_POLICY_MONTHS', 18.0),

        // Capacities Table, "Inwards Facultative Reinsurance ceded to the treaty":
        // restricted to 25% of the Reinsured's treaty limit, unless otherwise
        // agreed by the Leading Reinsurer. EXCEPTION — for these groups Alpha
        // Direct may cede up to 100% of treaty capacity.
        //
        // Matched on the insured name because that is what the register holds. It
        // is a REPORTING aid, not an authority: a match tells the underwriter the
        // exception may apply, it does not grant it. Reinsurance owns this list —
        // add the trading names each group actually writes under.
        'named_group_exceptions' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('FAC_NAMED_GROUPS', 'Choppies,Kamoso,Motovac'))
        ))),
    ],

    // ── Intelligence (DeepSeek) ──────────────────────────────────────────
    'intelligence' => [
        'enabled'  => (bool) env('FAC_AI_ENABLED', true),
        'provider' => env('FAC_AI_PROVIDER', 'deepseek'),
        'model'    => env('FAC_AI_MODEL', 'deepseek-reasoner'),
        'base_url' => env('FAC_AI_BASE_URL', 'https://api.deepseek.com'),
        'timeout'  => (int) env('FAC_AI_TIMEOUT', 90),
        // NOTE: there is deliberately NO 'redact' switch here.
        //
        // Redaction is unconditional in FacIntelligenceService — nothing that
        // identifies a natural person may leave the building (AD-POL-AI-GOV-001,
        // not waivable by anyone), and the service refuses to send at all if the
        // privacy check cannot run. A setting that could turn that off should not
        // exist even when it defaults to on: a branch that must never be taken is
        // a branch that eventually gets taken.
    ],
];
