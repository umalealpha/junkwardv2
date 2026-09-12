<?php

/**
 * Reinsurance cession parameters for the treaty year in force.
 *
 * These drive RegulatoryCessionCalculator when it is built through
 * RegulatoryCessionService. They are CONFIGURATION, not defaults baked into the
 * calculator, because the class limit is the one figure Reinsurance has moved
 * twice and will move again.
 *
 * WHY THE CLASS LIMITS READ 10,000,000 AND NOT SCHEDULE A.
 *
 * There are two written instructions, and they disagree. The later one wins here
 * because it is also what the live tables hold.
 *
 *   17 Aug 2026  Schedule A per class — Motor 5,000,000, Transportation 3,000,000,
 *                Miscellaneous 1,000,000, Guarantee 1,000,000. This is what
 *                RegulatoryCessionCalculator::ROUTES still carries as its own
 *                default, and what RI-12 section 4 tabulates.
 *
 *   26 Aug 2026  ONE treaty limit for every regulatory mapping, in Tlamelo
 *                Chimidza's words: "the treaty stipulates limit of 7,000,000 on
 *                cession and 3,000,000 on retention for the regulatory mapping.
 *                However our limits underwritten may be lower than the treaty
 *                limits which we will use the limit on those covers." Applied to
 *                the live tables by
 *                backend/database/manual/apply_uniform_treaty_limit_2627.php,
 *                which records that the Schedule A configuration understated
 *                cession by 56,172,000 on COMG2026213751.
 *
 * Wiring the calculator on its own Schedule A defaults would therefore have moved
 * live cession BACK to the understated basis. Setting them here keeps the
 * regulatory basis agreeing with what is already configured, so the cutover
 * changes how cession is ROUTED without changing what it comes to.
 *
 * To go back to Schedule A, set the four class limits to their 17 August figures.
 * Nothing else needs to change.
 */
return [

    /*
     * Treaty parameters. Every one is a slip figure — RI-TRTY-WP-01 P01 to P09.
     * first_line must equal retention_leg + quota_share_leg, and retention_pct
     * plus cession_pct must equal 1; the calculator refuses to build otherwise.
     */
    'params' => [
        'first_line'      => (float) env('RI_FIRST_LINE', 10000000),
        'retention_leg'   => (float) env('RI_RETENTION_LEG', 3000000),
        'quota_share_leg' => (float) env('RI_QUOTA_SHARE_LEG', 7000000),
        /*
         * EVIDENCED 29 August 2026, and the earlier doubt is closed.
         *
         * "Alpha Direct - Final Terms 2026-27_updated.xlsx", sheet 'Prop',
         * "Fire and Engineering Surplus (To be re-named as 1st Surplus)":
         *
         *     MAXIMUM RETENTION      10,000,000
         *     NO. OF LINES                    4
         *     MAXIMUM LIMIT          40,000,000
         *
         * Four lines struck on the 10,000,000 maximum retention — which is the
         * quota share's own maximum limit, our first line — give 40,000,000
         * exactly. The work paper's doubt assumed the lines were struck on the
         * 3,000,000 net retention leg, which would give 12,000,000 and does not
         * match the stated maximum limit on the same row.
         *
         * The treaty is named Fire and Engineering, which is why the surplus is
         * Property and Engineering only.
         */
        'surplus'         => (float) env('RI_SURPLUS', 40000000),
        'auto_fac'        => (float) env('RI_AUTO_FAC', 50000000),
        'retention_pct'   => (float) env('RI_RETENTION_PCT', 0.30),
        'cession_pct'     => (float) env('RI_CESSION_PCT', 0.70),
        // General Quota Share, per the 3 August slip. See 'terms' below for the
        // surplus and Motor rates, which differ.
        'ceding_commission_pct' => (float) env('RI_CEDING_COMMISSION_PCT', 0.375),
    ],

    /*
     * Class limits per regulatory mapping, overriding the calculator's own.
     * NULL means no cap. Property and Engineering take their capacity from the
     * layer stack instead, so they carry none.
     */
    'class_limits' => [
        'Motor'          => 10000000.0,
        'Transportation' => 10000000.0,
        'Miscellaneous'  => 10000000.0,
        'Guarantee'      => 10000000.0,
    ],

    /*
     * Groups whose Schedule A limit is narrower than their mapping's.
     *
     * The trailer carve-out survives the uniform limit: Schedule A words it "Own
     * Damage, any one trailer" at 1,500,000, and RI-12 section 5 keeps it as a
     * group-level override. Without it a trailer would cede against 10,000,000
     * where the slip gives 1,500,000.
     */
    'group_limits' => [
        'MOTOR_TRAILERS_COM' => 1500000.0,
        'MOTOR_TRAILERS_DOM' => 1500000.0,
    ],

    /*
     * Treaty terms the statement of account needs. Not read by the cession
     * calculator — recorded here so the figures live with the rest of the year's
     * configuration rather than in a mail thread.
     *
     * ALL CONFIRMED 31 AUGUST 2026, and each supersedes a conflicting reading of
     * the Final Terms workbook. Reinsurance: "we should abide by the treaty terms
     * on the slips" — the slips Mr Beka provided on 3 August.
     */
    'terms' => [
        'general' => [
            // The Final Terms workbook reads "To be advised". The 3 August slip
            // states 37.5%, and the slip governs.
            'ceding_commission_pct' => 0.375,
            // The workbook reads 1,000,000. The slip states 500,000.
            'cash_loss_limit'       => 500000.0,
            'event_limit'           => 70000000.0,
            'epi'                   => 24872310.0,

            /*
             * Read off the signed slip on 1 September 2026, page 3. These were
             * missing entirely: the surplus carried a profit commission and
             * General carried none, which would have accrued nothing on the
             * larger of the two treaties for a whole underwriting year.
             *
             *   "The Reinsurer shall pay to the reinsured a profit commission of
             *    30.00% on the profits of each underwriting year with Management
             *    Expenses of 7.5% and Losses carried forward to 5 years."
             *
             * First statement 24 months from inception, with the fourth quarter
             * account; second 12 months after that; thereafter every 24 months
             * unless the result moves by 20% or more.
             */
            'profit_commission_pct'    => 0.30,
            'management_expense'       => 0.075,
            'loss_carry_forward_years' => 5,
            'profit_first_statement_months' => 24,
            'profit_material_change_pct'    => 0.20,

            /*
             * BR-ACC-12: the reserve deposit earns the AVERAGE CALL RATE FOR THE
             * YEAR less 2.00%. The margin is a treaty term and lives on
             * TreatyReserveDeposit; the call rate is a market rate published by
             * the Bank of Botswana and has no source inside this system.
             *
             * NULL, NOT A GUESS. A plausible-looking rate would accrue interest
             * nobody agreed on somebody else's money. Left null, the builder
             * reports interest_rate.configured = false and accrues nothing, and
             * the retention itself still computes — which is the part that
             * actually moves cash this quarter.
             */
            'reserve_call_rate_pct' => env('RI_RESERVE_CALL_RATE_PCT') !== null
                ? (float) env('RI_RESERVE_CALL_RATE_PCT')
                : null,
        ],
        'surplus' => [
            // Fire and Engineering Surplus, per the Final Terms workbook.
            'ceding_commission_pct' => 0.30,
            'profit_commission_pct' => 0.285,
            'management_expense'    => 0.075,   // LCFTE
            'cash_loss_limit'       => 500000.0,
            'event_limit'           => 120000000.0,
            'epi'                   => 5200000.0,
        ],
        'motor' => [
            'provisional_commission_pct' => 0.25,
            'commission_min_pct'         => 0.225,
            'commission_max_pct'         => 0.40,
            'loss_ratio_cap'             => 0.75,
            'cash_loss_limit'            => 250000.0,
            'epi'                        => 70000000.0,

            /*
             * RESERVE DEPOSIT — RI-01 OPEN ITEM 7, CLOSED 7 SEPTEMBER 2026.
             *
             * Reinsurance ruled that the reserve deposit applies to the GQS
             * treaty, so Motor retains NOTHING. That is a genuine nil, and it is
             * the term the slip never gave.
             *
             * NULL HERE NOW MEANS NIL, NOT REFUSE — the reverse of what this
             * block said before the ruling. StatementReserveDepositBuilder::
             * termsFor() coalesces Motor's retained_pct to 0.0, so the builder
             * produces a nil deposit rather than declining. Until 7 September it
             * declined, and that was right at the time: the slip said only
             * "Domestic Reinsurers - Nil" while Article 9 provides for a deposit
             * against reinsurers not domiciled in Botswana and GIC Re is
             * foreign, so refusing was not the same as nil — and assuming zero
             * would have asserted something the slip did not support. Somebody
             * has now said.
             *
             * THE RULING IS A DEFAULT, NOT A CEILING. Setting any of these still
             * overrides the nil, so Motor terms stated later take effect. The
             * first cut of that change hardcoded the zero past the config and a
             * test caught it: a config knob that does nothing is worse than none.
             *
             * The refusal in depositsFor() survives for a THIRD treaty arriving
             * with no terms, which is what it was really guarding.
             */
            'reserve_retained_pct'   => env('RI_MOTOR_RESERVE_PCT') !== null
                ? (float) env('RI_MOTOR_RESERVE_PCT')
                : null,
            'reserve_margin_pct'     => env('RI_MOTOR_RESERVE_MARGIN_PCT') !== null
                ? (float) env('RI_MOTOR_RESERVE_MARGIN_PCT')
                : null,
            'reserve_call_rate_pct'  => env('RI_MOTOR_RESERVE_CALL_RATE_PCT') !== null
                ? (float) env('RI_MOTOR_RESERVE_CALL_RATE_PCT')
                : null,
        ],

        /*
         * How a reinsurer's participation is expressed.
         *
         * Confirmed 31 August 2026: "The reinsurer split is based on the 100%
         * risk. The splits may not add up to 100% due to the retention element in
         * the treaty."
         *
         * So a share of 29% means 29% of the WHOLE risk, not 29% of the ceded
         * portion, and the shares on a fully placed treaty sum to the cession
         * percentage — 70% — never to 100%.
         *
         * ReinsuranceEngine::splitByReinsurers() takes shares of the amount it is
         * splitting and requires them to sum to 100. That gate is correct for
         * shares OF THE CESSION, so a share stored on this basis must be divided
         * by the cession before it reaches the engine: 29% of 100% becomes 41.43%
         * of the cession.
         *
         * ParticipationCalculator does that conversion and, more importantly,
         * refuses to produce a split from a panel that is not fully placed.
         */
        'participation_basis' => 'of_100_percent',

        /*
         * Brokerage — BR-COM-16, 2.50% on both treaties.
         *
         * ON CEDED PREMIUM, NOT ON PREMIUM NET OF COMMISSION. The slips give the
         * rate and not the base, but BR-COM-17 is "no OTHER deductions from
         * premium" — which puts brokerage in the same category as the ceding
         * commission, both taken off premium rather than one off the other. Two
         * deductions compounding would also make the order they are applied in
         * matter, and nothing in the slips sets an order.
         */
        'brokerage_pct' => 0.025,

        /*
         * VAT — BR-ACC-17 and BR-ACC-18.
         *
         * NO LINE IS WRITTEN BY DEFAULT, AND THAT IS THE READING OF THE SLIPS
         * RATHER THAN A GAP. BR-ACC-18 is explicit: "All treaty figures,
         * including those reflected in attached statistics, exclude VAT unless
         * otherwise stated." A statement built from treaty figures is therefore
         * VAT-exclusive, and BR-ACC-17 — "VAT applies to the financial
         * transactions arising from the agreements, at the rate current at the
         * time of the transaction" — says that VAT exists, not that it belongs
         * inside this account.
         *
         * WHICH LINES WOULD ATTRACT IT IS A TAX QUESTION, NOT A TREATY ONE. The
         * three flows go in different directions and would not be treated alike:
         * premium ceded to a non-resident reinsurer is an imported service,
         * ceding commission is the cedant supplying a service to that reinsurer,
         * and brokerage is the intermediary's. Guessing would move real money, so
         * `applies_to` is empty until Finance rules on it, and a statement built
         * with it empty reports vat.configured = false rather than quietly
         * carrying no VAT.
         *
         * To switch it on, list the item types — e.g. ['brokerage'] — and the
         * rate is read from the same place the facultative side reads it.
         */
        'vat' => [
            'rate'       => (float) env('RI_VAT_RATE', env('FAC_VAT_RATE', 0.14)),
            'applies_to' => [],
        ],

        /*
         * BR-ACC-13 letters of credit MOVED TO A REGISTER, 4 September 2026.
         *
         * This was a list of company names, and a name has no expiry: an
         * instrument that lapsed went on exempting a reinsurer from a 40%
         * retention until somebody edited this file. The deposit IS the
         * security, so an exemption outliving it is the one failure that
         * matters here.
         *
         * See treaty_letters_of_credit and TreatyLetterOfCredit, which hold the
         * issuer, amount, effective and expiry dates, whether the instrument is
         * on the form prescribed by the Registrar, and any cancellation — and
         * are tested as at the quarter's close rather than today.
         */

        /*
         * The market prime lending rate — BR-ACC-10, for delay interest at 110%.
         *
         * EFFECTIVE-DATED, NOT A SINGLE FIGURE. Interest runs from a due date to
         * a date of payment, and a rate that moved inside that window changes
         * the answer. One "current rate" would silently apply today's number to
         * last quarter's delay.
         *
         * EMPTY MEANS UNPRICEABLE, NOT ZERO. With no rate on file an overdue
         * balance is reported as overdue and uncomputable, because accruing zero
         * on it reads as "paid on time". The 110% multiple is a treaty term and
         * lives on StatementDelayInterestBuilder; the prime rate is published by
         * the banks and is not ours to invent.
         *
         * Shape: [['from' => '2026-07-01', 'pct' => 6.75], ...]
         */
        'prime_rate' => [],
    ],

    /*
     * The treaty's own calendar.
     *
     * THE CLOCKS COUNT FROM HERE. Quarters are enumerated from this year so that
     * a quarter with NO statement at all is visible — one that was never started
     * cannot be overdue, because nothing is looking at it, and it reads exactly
     * like a quarter that has not closed yet.
     *
     * 2026 means the 2026/27 year, opening 1 July 2026.
     */
    'treaty' => [
        'first_underwriting_year' => (int) env('RI_FIRST_UNDERWRITING_YEAR', 2026),
    ],

    /*
     * The three annual aggregates.
     *
     * NOT event limits, and they must not be read as one. An event limit resets
     * per occurrence; an aggregate is a pot for the whole underwriting year and
     * every recovery takes from it. Once empty the treaty pays nothing further on
     * that peril until 1 July, however severe the next loss is.
     *
     * MOTOR RIOT CARRIES BOTH, AT THE SAME FIGURE. Its event limit is 50,000,000
     * and its annual aggregate is also 50,000,000, so one riot at the full event
     * limit exhausts the year's riot cover. That is what the slip says; it is not
     * a transcription error, and it is the kind of thing nobody notices until the
     * second event.
     *
     * Amounts only. WHICH losses each aggregate catches -- peril, territory and
     * coverage -- lives in AnnualAggregateCalculator::AGGREGATES, because a
     * peril list is a rule rather than a figure, and a figure typed into config
     * cannot be wrong in a way that a test would not catch.
     *
     * Sources: RI-01 6.3 BR-CAP-15 to 17; RI-12 8; both signed slips.
     */
    'annual_aggregates' => [
        // General slip -- SRCC, risks situated in Zimbabwe.
        'srcc_zimbabwe'        => 20000000.0,
        // General slip -- War and Civil War, Goods in Transit, all territories.
        'war_goods_in_transit' => 6000000.0,
        // Motor slip -- Riot and Strike.
        'motor_riot_strike'    => 50000000.0,
    ],

    /*
     * The accounting clock and what a late balance costs.
     *
     * Signed General slip, page 5 -- read 1 September 2026 and previously only
     * half known. The 45 and 14 day clocks were already carried; the INTEREST
     * was not, and a statement of account that cannot price a late balance is
     * incomplete.
     *
     *   "Accounts will be rendered quarterly by the Reinsured to the Reinsurers
     *    within 45 (Forty-Five) days after the close of each quarter."
     *   "Following receipt of the statement of account, the Reinsurer's shall
     *    confirm accounts or raise any objections to them within fourteen days."
     *   "DELAY IN PAYMENT INTEREST: Interest of 110% of market prime lending
     *    rate on balances due from due date to date of payment for overdue
     *    balances."
     *   "CURRENCY: Botswana Pula."   -- the treaty is Pula only, confirmed.
     *
     * The prime rate itself is a market rate and moves, so it is NOT stored
     * here. Only the multiple is a treaty term.
     */
    'accounts' => [
        'render_within_days'      => 45,
        'confirm_within_days'     => 14,
        'delay_interest_multiple' => 1.10,   // of market prime lending rate
        'currency'                => 'BWP',
        'settlement_from_cedant'  => 'cheque attached',
    ],

    /*
     * Underwriting restrictions from the LIMITS block of the General slip.
     * Mirrored in TreatyControlsCalculator, which carries the reasoning.
     */
    'underwriting' => [
        'mpl_minimum_pct'      => 0.50,   // "minimum MPL of 50% on referral"
        'co_insurance_max_pct' => 0.50,   // "Co-Insurance: 50%"
        'inwards_fac_pct'      => 0.25,
    ],

    /*
     * THE PANELS, AS THE SIGNED SLIPS ACTUALLY EVIDENCE THEM.
     *
     * Read off the signing pages on 1 September 2026 -- General page 60, Motor
     * page 35. Until then these figures came from Reinsurance's workbook; they
     * now come from the executed documents, and they agree.
     *
     * NEITHER PANEL IS COMPLETE, and that is the finding, not a gap in this
     * file. A quota share panel on the of-100% basis sums to the CESSION, so a
     * fully placed treaty reaches 70.00 and neither of these is close:
     *
     *   General  FM Re 29.00 + PBC Re 7.00  = 36.00  -> 34.00 unplaced
     *   Motor    Continental 25.00 + GIC Re 11.90 = 36.90 -> 33.10 unplaced
     *
     * Two things the slips show that a workbook could not:
     *
     *   · Continental Re's FINAL SIGNED LINE IS BLANK. Only a written line of
     *     25% is on the page, so its 25.00 below is a written line and not a
     *     signed one -- recorded as such rather than promoted.
     *   · PBC Re and GIC Re are written on the OF-CESSION basis in manuscript
     *     ("10% of cession", "17% of cession or 11.90% of 100%"). GIC Re's own
     *     note gives both, and 17% of 70% is 11.90%, which is what confirms the
     *     conversion ParticipationCalculator applies.
     *
     * ParticipationCalculator REFUSES to split on a panel that does not reach
     * the cession, so these are here to be reported against, not to be used.
     */
    'panels' => [
        'general' => [
            ['reinsurer' => 'FM Re Property & Casualty Botswana',
             'of_100_pct' => 29.00, 'status' => 'signed',
             'source' => 'General slip p60, signed 13 Jul 2026'],
            ['reinsurer' => 'PBC Re',
             'of_100_pct' => 7.00, 'of_cession_pct' => 10.00, 'status' => 'signed',
             'source' => 'General slip p60, manuscript, 16 Jul 2026'],
        ],
        'motor' => [
            ['reinsurer' => 'Continental Reinsurance Ltd Botswana',
             'of_100_pct' => 25.00, 'status' => 'written_only',
             'source' => 'Motor slip p35, stamped 13 Jul 2026, final signed line BLANK'],
            ['reinsurer' => 'GIC Re South Africa Ltd',
             'of_100_pct' => 11.90, 'of_cession_pct' => 17.00, 'status' => 'signed',
             'source' => 'Motor slip p35, manuscript "WL 22.50% / SL 17% of cession"'],
        ],
    ],

    /*
     * Groups held out of the treaty whatever class their mapping carries.
     *
     * Confirmed by Reinsurance 29 August 2026: "Travel insurance does not make
     * part of the treaty and should be treated as 100% retained. And it should be
     * classed under Miscellaneous." Miscellaneous cedes, so the mapping alone
     * would cede 70% of every travel risk within the class limit. The mapping is
     * for reporting; this is what decides whether the treaty took the risk.
     */
    'retained_groups' => [
        'TRAVELINSURANCE_COM',
        'TRAVELINSURANCE_DOM',
    ],

    /*
     * Every reinsurance group and the regulatory class it routes on.
     *
     * THE CLASS IS A PROPERTY OF THE GROUP, not of the sub-coverage.
     * reinsurance_group_coverage holds sub-coverages — PROPERTYANDBI_COM legitimately
     * holds BUILDINGS, STOCK, PLANT AND MACHINERY, RENT and CONTENTS and no parent
     * FIRE row at all — and every row in a group carries that group's class.
     *
     * Sources: the mapping annexed 17 August 2026, as amended by Reinsurance on
     * 24, 25, 29 and 31 August. Public Liability, Personal Accident and Workers
     * Compensation are Accident, not Liability ("forming part of the uninsured
     * component", covered under the non-proportional treaties). Travel insurance
     * is Miscellaneous but held out of the treaty by 'retained_groups' above,
     * because Miscellaneous cedes and travel does not.
     */
    'group_mappings' => [
        // Property. Layered: 3m / 7m / 40m surplus / 50m Auto FAC / 50m FAC.
        'PROPERTYANDBI_COM'                         => 'Property',
        'PROPERTYANDBI_DOM'                         => 'Property',
        'ELECTRONIC_EQ_AND_BI_COM'                  => 'Property',
        'ELECTRONICEQANDBI_DOM'                     => 'Property',
        'ACCIDENTAL_DAMAGE_COM'                     => 'Property',
        // Point 4: both traders groups are Property despite the name.
        'MOTOR_TRADERS_COM_EXT'                     => 'Property',
        'MOTOR_TRADERS_COM_INT'                     => 'Property',

        // Engineering. Same cascade as Property.
        'ENGINEERING_AND_BI_COM'                    => 'Engineering',

        // Capped classes.
        'MOTOR_COM'                                 => 'Motor',
        'MOTOR_DOM'                                 => 'Motor',
        'MOTOR_TRAILERS_COM'                        => 'Motor',
        'MOTOR_TRAILERS_DOM'                        => 'Motor',
        'GOODSINTRANSIT_COM'                        => 'Transportation',
        'MISC_COM'                                  => 'Miscellaneous',
        'MISCANDFG_COM'                             => 'Miscellaneous',
        'MISCANDFG_DOM'                             => 'Miscellaneous',
        'FIDELITYG_COM'                             => 'Guarantee',
        // Classed here, held out of the treaty by 'retained_groups'.
        'TRAVELINSURANCE_COM'                       => 'Miscellaneous',
        'TRAVELINSURANCE_DOM'                       => 'Miscellaneous',

        // Do not cede. 100% retained, raised monthly as exceptions.
        'PUBLICLIABANDDEFECTIVEWORKMAN_COM'         => 'Accident',
        'PUBLICLIABANDDEFECTIVEWORKMAN_DOM'         => 'Accident',
        'PUBLICLIABANDDEFECTIVEWORKMANBODYCORP_COM' => 'Accident',
        'GROUPPERSONALACCIDENT_COM'                 => 'Accident',
        'GROUPPERSONALACCIDENT_DOM'                 => 'Accident',
        'WC_COM'                                    => 'Accident',
        'WC_DOM'                                    => 'Accident',
        // Not named in the 31 August reply, so they stay on Liability. Retained
        // either way; do not move them without an instruction.
        'PRODUCTSLIABILITY_COM'                     => 'Liability',
        'PROFESSIONALINDEMNITYINSURANCE_COM'        => 'Liability',
    ],

    /*
     * Groups deliberately left unmapped, each with its reason. Listed rather than
     * omitted so an unmapped group is a decision on the record and not an
     * oversight — a group nobody classified retains its whole class silently.
     */
    'unmapped_groups' => [
        'AVIATION_COM'       => 'no Schedule A capacity; carries no rows',
        'BODY_CORPORATE_COM' => 'no Schedule A capacity; carries no rows',
        'MOTOR_PER_ACCIDENT' => 'carries no rows',
        'try_new_grp'        => 'test data — should be deleted, not mapped',
    ],

    /*
     * Which engine decides live cession.
     *
     * 'legacy'     — the formula chain in PolicyCoverage. Routes by reinsurance
     *                group. This is what ships today.
     * 'regulatory' — RegulatoryCessionCalculator, routing by regulatory mapping.
     *
     * Left on legacy deliberately. The regulatory basis is reachable now for
     * comparison (reinsurance:regulatory-cession) so Reinsurance can agree the
     * difference on COMG2026213751 before any live figure moves. Flip this only
     * once that reconciliation is signed off.
     */
    'engine' => env('RI_CESSION_ENGINE', 'legacy'),
];
