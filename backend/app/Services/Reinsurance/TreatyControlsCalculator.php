<?php

namespace AlphaDirect\Services\Reinsurance;

use InvalidArgumentException;

/**
 * Underwriting controls the treaties impose, tested before a risk is bound.
 *
 * Built against the "CONDITIONS ATTACHING TO THE CAPACITY ABOVE" section and
 * Notes 7 and 8 of the Alpha Direct Capacities Table 2026/27 — Reinsurance's own
 * document, which states on its face that it is built solely from the two signed
 * slips. Reading the source rather than a summary of it is what turned several of
 * these from "cannot be tested" into rules with figures.
 *
 * EVERY CONTROL RETURNS A FINDING, AND ONE OF THEM BLOCKS. The facultative
 * threshold is a hard gate: a sum insured above 50,000,000 with no facultative
 * placement is uninsured exposure the company standard forbids binding. Every
 * other control reports. That distinction is deliberate — the gate is Alpha
 * Direct's own standard, while the treaty conditions can be varied by agreement
 * with the Leading Reinsurer, so refusing on one would stop work the underwriter
 * may already have cleared.
 *
 * THREE OUTCOMES ON TERRITORY, NOT TWO. Covered, excluded, or refer. The slips
 * name what is covered and what is excluded; everything else is a question.
 * Reading silence as permission is how a risk gets written outside the treaty and
 * discovered at claim. Four things widen the scope beyond the named territories —
 * the cover, the insured's domicile, the named groups, and a special acceptance —
 * and all four are in Notes 7 and 8.
 *
 * WHAT IS STILL NOT HERE.
 *
 *   Co-insurance 50% gross-up. The RULE is known — 50%, General slip — but
 *   nothing in the system captures a co-insurance share to apply it to. RI-11
 *   point 6, unanswered since 29 August.
 *
 *   The three annual aggregates. They need erosion tracked per underwriting year,
 *   peril and territory — storage, not arithmetic, so they are their own piece.
 *
 * THE INWARDS FACULTATIVE BASE IS A PARAMETER, NOT A CONSTANT. The condition says
 * 25% of "the Reinsured's treaty limit" and the exception says "100% of treaty
 * capacity". The table uses CAPACITY for the Schedule A per-class figures, and
 * Note 5 is explicit that treaty-level limits "are not per-risk capacity and must
 * not be added to the class limits" — so the base reads as the class capacity,
 * 2,500,000 on a 10,000,000 first line, rather than 17,500,000 off the event
 * limit. The caller supplies it, so the rule is testable now and the reading can
 * be corrected in one place if Reinsurance says otherwise.
 *
 * PURE. No database, no container. These figures decide whether a risk may be
 * bound, so they have to be reproducible from their inputs alone.
 *
 * Sources: J.B. Boda General Quota Share 2026/27 amended signed slip; Motor
 * Quota Share 2026/27 Continental Re lead signed slip; RI-01 requirements 15, 16
 * and 13.
 */
class TreatyControlsCalculator
{
    /**
     * Facultative is mandatory above this sum insured.
     *
     * Alpha Direct's own underwriting standard, not a treaty term — which is why
     * it is the one control here that blocks rather than reports.
     */
    public const FACULTATIVE_MANDATORY_ABOVE = 50000000.0;

    /** Motor's commission is struck on a loss ratio capped at this. */
    public const MOTOR_LOSS_RATIO_CAP = 0.75;

    /**
     * Territories each treaty covers, lower-cased. Note 7 of the Capacities Table.
     *
     * General is named territory by territory. Motor is regional — "Sub-Saharan
     * Africa and the Indian Ocean Islands per the country schedule in the Motor
     * slip", and THAT SCHEDULE IS NOW LOADED — all 48 countries, read off page 2
     * of the Continental Re lead signed slip and grouped as the slip groups them.
     * Page 1 gives the clause: "Sub-Saharan Africa and / or Indian Ocean Islands
     * (as per the table below) in respect of policies issued in: Botswana;
     * Original Countries, by partner insurance companies and facultatively
     * accepted by Alpha Direct Insurance Company in Botswana."
     *
     * THREE THINGS THE SLIP GETS LOOSE, kept here rather than tidied away:
     *
     *   · It prints "Lesontha". That is Lesotho — there is no such country —
     *     so the correct spelling is used and the slip's is not carried.
     *   · It prints "Swaziland", renamed Eswatini in 2018. Both are listed, so
     *     a policy captured under either name matches.
     *   · Its island column is headed "African Island Nations (Indian Ocean"
     *     — the bracket is never closed — yet it lists Cape Verde and Sao Tome
     *     and Principe, which are Atlantic. The COUNTRIES govern, not the
     *     heading, so both are covered.
     *
     * GENERAL IS A DIFFERENT AND MUCH NARROWER SCOPE, and it is now read off
     * too -- signed General slip page 1: "1. Botswana, Zimbabwe, Zambia and
     * Incidental interests abroad excluding USA and Canada." That is the whole
     * list, which is why Kenya is covered on Motor and refers on General. The
     * same page gives the worldwide covers and the three group extensions
     * carried below, all of which match what was already configured.
     */
    public const COVERED = [
        'general' => ['botswana', 'zimbabwe', 'zambia'],

        // Motor slip, page 2, by the slip's own regional grouping.
        'motor'   => [
            // Central Africa
            'democratic republic of congo', 'drc', 'republic of congo', 'congo',
            'central african republic', 'rwanda', 'burundi',
            // East Africa
            'sudan', 'kenya', 'tanzania', 'uganda', 'djibouti', 'eritrea',
            'ethiopia', 'somalia',
            // Southern Africa
            'angola', 'botswana', 'lesotho', 'malawi', 'mozambique', 'namibia',
            'south africa', 'swaziland', 'eswatini', 'zambia', 'zimbabwe',
            // West Africa
            'benin', 'burkina faso', 'cameroon', 'chad', "cote d'ivoire",
            'cote divoire', 'ivory coast', 'equatorial guinea', 'gabon',
            'the gambia', 'gambia', 'ghana', 'guinea', 'guinea-bissau',
            'guinea bissau', 'liberia', 'mali', 'mauritania', 'niger', 'nigeria',
            'senegal', 'sierra leone', 'togo',
            // African island nations
            'cape verde', 'cabo verde', 'comoros', 'madagascar', 'mauritius',
            'sao tome and principe', 'sao tome', 'seychelles',
        ],
    ];

    /**
     * Covers written WORLDWIDE regardless of where the risk sits — Note 7.
     *
     * Matched on the cover name, not the territory, which is why they are here
     * rather than in COVERED: a laptop in France is inside the treaty and a
     * building in France is not.
     */
    public const WORLDWIDE_COVERS = [
        'all risks', 'laptop', 'personal belongings', 'personal legal liability',
    ];

    /**
     * Named groups that extend the General territorial scope, for policies
     * ISSUED IN BOTSWANA — Note 7. The same three groups carry the inwards
     * facultative exception.
     */
    public const GROUP_TERRITORY_EXTENSIONS = [
        'choppies' => ['south africa', 'zimbabwe'],
        'motovac'  => ['south africa', 'namibia'],
        'kamoso'   => ['south africa', 'namibia'],
    ];

    /**
     * Special acceptances — Note 8. Agreed by the Leading Reinsurer only.
     *
     * Botho University is explicitly granted OUTSIDE territorial scope with no
     * facultative inwards restriction, so it must not be refused on either
     * ground. Diagnofirm is named with no stated terms.
     */
    public const SPECIAL_ACCEPTANCES = [
        'botho university' => [
            'territory_waived'   => true,
            'inwards_fac_waived' => true,
            'peak_value'         => 5000000.0,
            'note'               => 'Lesotho, peak value BWP 5,000,000, granted outside territorial '
                . 'scope with no facultative inwards restriction. All other terms apply.',
        ],
        'diagnofirm' => [
            'territory_waived'   => true,
            'inwards_fac_waived' => false,
            'peak_value'         => null,
            'note'               => 'Named special acceptance. No further terms stated.',
        ],
    ];

    /** Inwards facultative ceded to the treaty, as a share of the treaty limit. */
    public const INWARDS_FAC_SHARE = 0.25;

    /** The named groups may cede up to 100% of treaty capacity. */
    public const INWARDS_FAC_EXEMPT = ['choppies', 'kamoso', 'motovac'];

    /** Accidental Damage above this is excluded from the 100% quota share limit. */
    public const ACCIDENTAL_DAMAGE_CEILING = 7500000.0;

    /** Advanced Loss of Profits above this annual gross profit is excluded. */
    public const ALOP_CEILING = 10000000.0;

    /** Engineering referral thresholds — Note 42, Referral Risks n, o, p, r. */
    public const ENGINEERING_REFERRAL = 10000000.0;
    public const BRIDGE_SPAN_M       = 50.0;
    public const BRIDGE_LENGTH_M     = 100.0;

    /** Policies over this many months are excluded from both treaties. */
    public const MAX_POLICY_MONTHS = 18.0;

    /**
     * Contingent Business Interruption sub-limits — the SUB-LIMITS section of the
     * Capacities Table.
     *
     * Each is a PAIR: a percentage of the Business Interruption limit AND a cash
     * ceiling, and the lower of the two applies. On a 20,000,000 BI limit the
     * named-supplier extension is 75% — 15,000,000 — but the cash ceiling holds it
     * to 7,500,000. Applying only the percentage would double the cover on any BI
     * limit above 10,000,000.
     */
    public const CBI = [
        'named'     => ['share' => 0.75, 'ceiling' => 7500000.0,
                        'label' => 'named suppliers and customers'],
        'unnamed'   => ['share' => 0.10, 'ceiling' => 1000000.0,
                        'label' => 'unnamed suppliers and customers'],
        // Three months of the ANNUAL business interruption sum insured, or the
        // ceiling, whichever is lower.
        'utilities' => ['months' => 3.0, 'ceiling' => 1000000.0,
                        'label' => 'public utilities and denial or prevention of access'],
    ];

    /**
     * PML / MPL and co-insurance, from the LIMITS block of the General slip.
     *
     *     "Acceptances on a PML basis are subject to a minimum MPL of 50% on
     *      referral."
     *     "Co-Insurance: 50%"
     *
     * THE FIRST IS UNAMBIGUOUS. Where a risk is written on a Probable Maximum
     * Loss basis, the MPL may not be assessed below half the sum insured without
     * a referral. Writing a 30% MPL on a 100,000,000 building presents the treaty
     * with 30,000,000 of exposure on a risk that can burn for 100,000,000, and it
     * is the cheapest way to understate what is ceded.
     *
     * THE SECOND IS NOT, and it is read here as a CAP on Alpha Direct's share.
     * It sits in LIMITS beside the inwards facultative restriction, which is a
     * cap, so a cap is the reading that fits its neighbours — and it is the
     * reading that errs towards referral rather than towards silently accepting
     * a larger line. A share above 50% refers; it is not refused. If Reinsurance
     * means something else by it, the constant moves and the tests move with it.
     */
    public const MPL_MINIMUM_PCT     = 0.50;
    public const CO_INSURANCE_MAX_PCT = 0.50;

    /** Denial of access reaches this far, and this far only. */
    public const CBI_ACCESS_KM         = 50.0;

    /** Except where the logical access point is beyond 50km, and then this far. */
    public const CBI_ACCESS_KM_EXTENDED = 100.0;

    /**
     * Territories the slips exclude outright, on both treaties.
     *
     * Excluded is not the same as unnamed. These are refused; an unnamed
     * territory is referred.
     */
    public const EXCLUDED = ['united states', 'usa', 'united states of america', 'canada'];

    private float $facultativeThreshold;

    public function __construct(?float $facultativeThreshold = null)
    {
        $this->facultativeThreshold = $facultativeThreshold ?? self::FACULTATIVE_MANDATORY_ABOVE;

        if ($this->facultativeThreshold < 0) {
            throw new InvalidArgumentException('The facultative threshold cannot be negative.');
        }
    }

    // ───────────────────────────────────────── facultative threshold

    /** Whether a facultative placement is required before this risk may be bound. */
    public function facultativeRequired(float $sumInsured): bool
    {
        return $sumInsured > $this->facultativeThreshold;
    }

    /**
     * The gate. Returns NULL where the risk may be bound.
     *
     * A risk over the threshold with a placement recorded passes; over the
     * threshold with none is refused. This is the only control here that refuses,
     * because binding uninsured exposure above the company's own standard is not
     * a reporting matter.
     *
     * @return array{code:string,severity:string,title:string,detail:string}|null
     */
    public function facultativeFinding(float $sumInsured, bool $hasPlacement): ?array
    {
        if (! $this->facultativeRequired($sumInsured)) {
            return null;
        }

        if ($hasPlacement) {
            return [
                'code'     => 'FAC_REQUIRED_PLACED',
                'severity' => 'info',
                'title'    => 'Facultative required, and placed',
                'detail'   => sprintf(
                    'Sum insured %s is above the %s threshold. A facultative placement is recorded.',
                    number_format($sumInsured, 2),
                    number_format($this->facultativeThreshold, 2)
                ),
            ];
        }

        return [
            'code'     => 'FAC_REQUIRED_MISSING',
            'severity' => 'block',
            'title'    => 'Facultative required and not placed',
            'detail'   => sprintf(
                'Sum insured %s is above the %s threshold and no facultative placement is '
                . 'recorded. The balance is uninsured exposure, not treaty cover.',
                number_format($sumInsured, 2),
                number_format($this->facultativeThreshold, 2)
            ),
        ];
    }

    // ───────────────────────────────────────── territorial scope

    /**
     * 'covered', 'excluded' or 'refer'. Never a bare boolean — see the class note.
     *
     * Four things can widen the scope beyond the named territories, and all four
     * are in Note 7 or Note 8:
     *
     *   the cover     All Risks, Laptops, Personal Belongings and Personal Legal
     *                 Liability are worldwide wherever the risk sits
     *   the insured   Liability business of an insured domiciled in Sub-Saharan
     *                 Africa is worldwide
     *   the group     Choppies adds South Africa and Zimbabwe; Motovac and Kamoso
     *                 each add South Africa and Namibia, for policies issued in
     *                 Botswana
     *   a special     Botho University is granted outside territorial scope
     *   acceptance    outright
     *
     * @param  array{cover?:string,insured?:string,group?:string,domiciled_in_ssa?:bool}  $context
     */
    public function territoryStatus(string $territory, string $treaty, array $context = []): string
    {
        $t = strtolower(trim($territory));
        $k = strtolower(trim($treaty));

        if (! array_key_exists($k, self::COVERED)) {
            throw new InvalidArgumentException("No territorial scope configured for treaty '{$treaty}'.");
        }

        // A special acceptance granted outside scope is settled before anything
        // else, including the exclusions — that is what "granted as outside of
        // Territorial Scope" means.
        if ($this->specialAcceptanceFor($context['insured'] ?? null)['territory_waived'] ?? false) {
            return 'covered';
        }

        if ($t === '') {
            return 'refer';
        }
        // Excluded wins over covered: a slip that names an exclusion means it.
        if (in_array($t, self::EXCLUDED, true)) {
            return 'excluded';
        }
        if (in_array($t, self::COVERED[$k], true)) {
            return 'covered';
        }

        // Worldwide by cover. USA and Canada are already refused above, so this
        // widens the scope without reaching into the exclusions.
        $cover = strtolower(trim((string) ($context['cover'] ?? '')));
        if ($cover !== '' && $this->matchesAny($cover, self::WORLDWIDE_COVERS)) {
            return 'covered';
        }

        // Worldwide for liability business of an insured domiciled in Sub-Saharan
        // Africa. The domicile is the test, not the location of the risk.
        if (! empty($context['domiciled_in_ssa'])
            && str_contains($cover, 'liability')) {
            return 'covered';
        }

        // A named group extends the General scope for policies issued in Botswana.
        $group = strtolower(trim((string) ($context['group'] ?? '')));
        if ($group !== '') {
            foreach (self::GROUP_TERRITORY_EXTENSIONS as $name => $extra) {
                if (str_contains($group, $name) && in_array($t, $extra, true)) {
                    return 'covered';
                }
            }
        }

        return 'refer';
    }

    /**
     * The special acceptance for an insured, or an empty set.
     *
     * Matched on a substring of the insured name because the slip names the
     * organisation and the policy carries its trading name — "Diagnofirm Medical
     * Laboratories (Pty) Ltd" against "Diagnofirm".
     *
     * @return array{territory_waived?:bool,inwards_fac_waived?:bool,peak_value?:float|null,note?:string}
     */
    public function specialAcceptanceFor(?string $insured): array
    {
        if ($insured === null || trim($insured) === '') {
            return [];
        }

        $name = strtolower(trim($insured));

        foreach (self::SPECIAL_ACCEPTANCES as $key => $terms) {
            if (str_contains($name, $key)) {
                return $terms;
            }
        }

        return [];
    }

    /** @param array<int,string> $needles */
    private function matchesAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $n) {
            if (str_contains($haystack, $n)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{code:string,severity:string,title:string,detail:string}|null
     */
    public function territoryFinding(string $territory, string $treaty, array $context = []): ?array
    {
        $status = $this->territoryStatus($territory, $treaty, $context);
        $named  = trim($territory) === '' ? '(none stated)' : trim($territory);

        if ($status === 'covered') {
            return null;
        }

        if ($status === 'excluded') {
            return [
                'code'     => 'TERRITORY_EXCLUDED',
                'severity' => 'warn',
                'title'    => 'Territory excluded by the slip',
                'detail'   => "{$named} is excluded on both treaties. Nothing written there cedes; "
                    . 'the whole risk is retained net.',
            ];
        }

        return [
            'code'     => 'TERRITORY_UNKNOWN',
            'severity' => 'warn',
            'title'    => 'Territory not named on the slip',
            'detail'   => "{$named} is neither covered nor excluded by the "
                . strtolower($treaty) . ' slip. Refer to Reinsurance before binding — an unnamed '
                . 'territory is a question, not permission.',
        ];
    }

    // ───────────────────────────────────────── the remaining conditions

    /**
     * Inwards facultative ceded to the treaty — 25% of the Reinsured's treaty
     * limit, with the three named groups exempt at 100% of treaty capacity.
     *
     * WHICH LIMIT THE 25% IS OF IS STILL NOT STATED, so it is a parameter rather
     * than a constant. The condition says "the Reinsured's treaty limit" and the
     * exception says "100% of treaty capacity", and the Capacities Table uses
     * CAPACITY for the Schedule A per-class figures while Note 5 is explicit that
     * treaty-level limits "are not per-risk capacity and must not be added to the
     * class limits". On that reading the base is the class capacity — 2,500,000
     * on a 10,000,000 first line — and not 17,500,000 off the event limit. The
     * caller supplies it either way, so the rule is testable now and the reading
     * can be corrected in one place.
     */
    public function inwardsFacultativeCap(float $treatyLimit, ?string $group = null): float
    {
        if ($treatyLimit < 0) {
            throw new InvalidArgumentException('The treaty limit cannot be negative.');
        }

        return $this->inwardsFacultativeExempt($group)
            ? $treatyLimit
            : $treatyLimit * self::INWARDS_FAC_SHARE;
    }

    /** Choppies, Kamoso and Motovac may cede up to 100% of treaty capacity. */
    public function inwardsFacultativeExempt(?string $group): bool
    {
        if ($group === null) {
            return false;
        }

        return $this->matchesAny(strtolower(trim($group)), self::INWARDS_FAC_EXEMPT);
    }

    /**
     * @return array{code:string,severity:string,title:string,detail:string}|null
     */
    public function inwardsFacultativeFinding(
        float $ceded,
        float $treatyLimit,
        ?string $group = null,
        ?string $insured = null
    ): ?array {
        // Botho University is granted with no facultative inwards restriction.
        if ($this->specialAcceptanceFor($insured)['inwards_fac_waived'] ?? false) {
            return null;
        }

        $cap = $this->inwardsFacultativeCap($treatyLimit, $group);

        if ($ceded <= $cap) {
            return null;
        }

        return [
            'code'     => 'INWARDS_FAC_OVER_CAP',
            'severity' => 'warn',
            'title'    => 'Inwards facultative over the treaty restriction',
            'detail'   => sprintf(
                'Ceding %s of inwards facultative against a cap of %s. The slip restricts this to '
                . '25%% of the treaty limit unless the Leading Reinsurer agrees otherwise.',
                number_format($ceded, 2),
                number_format($cap, 2)
            ),
        ];
    }

    /**
     * Accidental Damage above 7,500,000 is excluded from the 100% quota share
     * limit, with its resulting Business Interruption.
     */
    public function accidentalDamageFinding(float $limitOfLiability): ?array
    {
        if ($limitOfLiability <= self::ACCIDENTAL_DAMAGE_CEILING) {
            return null;
        }

        return [
            'code'     => 'AD_ABOVE_CEILING',
            'severity' => 'warn',
            'title'    => 'Accidental Damage above the quota share ceiling',
            'detail'   => sprintf(
                'Accidental Damage limit %s exceeds %s. The excess and its resulting Business '
                . 'Interruption are excluded from the quota share, so they are retained net.',
                number_format($limitOfLiability, 2),
                number_format(self::ACCIDENTAL_DAMAGE_CEILING, 2)
            ),
        ];
    }

    /**
     * Advanced Loss of Profits and Delay in Start-up are excluded above
     * 10,000,000 of annual gross profit for the cedant's share.
     */
    public function alopFinding(float $annualGrossProfit): ?array
    {
        if ($annualGrossProfit <= self::ALOP_CEILING) {
            return null;
        }

        return [
            'code'     => 'ALOP_EXCLUDED',
            'severity' => 'warn',
            'title'    => 'Advanced Loss of Profits excluded',
            'detail'   => sprintf(
                'Annual gross profit %s exceeds %s, so Advanced Loss of Profits and Delay in '
                . 'Start-up cover is excluded from the treaty and retained net.',
                number_format($annualGrossProfit, 2),
                number_format(self::ALOP_CEILING, 2)
            ),
        ];
    }

    /**
     * Engineering risks the slip sends to referral — Referral Risks n, o, p, r.
     *
     * Four separate triggers and any one of them refers, so they are reported
     * together with the reason named. A referral is not a refusal.
     *
     * @param  array{plant_value?:float,project_value?:float,in_botswana?:bool,bridge_span_m?:float,bridge_length_m?:float}  $risk
     * @return array{code:string,severity:string,title:string,detail:string}|null
     */
    public function engineeringReferralFinding(array $risk): ?array
    {
        $reasons = [];

        if ((float) ($risk['plant_value'] ?? 0) > self::ENGINEERING_REFERRAL) {
            $reasons[] = 'Contractors Plant and Machinery over '
                . number_format(self::ENGINEERING_REFERRAL, 2);
        }
        if ((float) ($risk['project_value'] ?? 0) > self::ENGINEERING_REFERRAL) {
            $reasons[] = ($risk['in_botswana'] ?? true)
                ? 'project in Botswana over ' . number_format(self::ENGINEERING_REFERRAL, 2)
                : 'single project outside Botswana over ' . number_format(self::ENGINEERING_REFERRAL, 2);
        }
        if ((float) ($risk['bridge_span_m'] ?? 0) > self::BRIDGE_SPAN_M) {
            $reasons[] = 'bridge single span over ' . self::BRIDGE_SPAN_M . 'm';
        }
        if ((float) ($risk['bridge_length_m'] ?? 0) > self::BRIDGE_LENGTH_M) {
            $reasons[] = 'bridge total length over ' . self::BRIDGE_LENGTH_M . 'm';
        }

        if (! $reasons) {
            return null;
        }

        return [
            'code'     => 'ENGINEERING_REFERRAL',
            'severity' => 'warn',
            'title'    => 'Engineering risk requires referral',
            'detail'   => 'Refer to Reinsurance before binding: ' . implode('; ', $reasons) . '.',
        ];
    }

    /**
     * Policies over eighteen months in all are excluded from both treaties.
     *
     * "Twelve months plus odd time not exceeding eighteen months in all" — so
     * eighteen is the outside edge and anything beyond it is out of the treaty,
     * not merely referred.
     */
    public function policyPeriodFinding(float $months): ?array
    {
        if ($months <= self::MAX_POLICY_MONTHS) {
            return null;
        }

        return [
            'code'     => 'PERIOD_EXCLUDED',
            'severity' => 'warn',
            'title'    => 'Policy period over eighteen months',
            'detail'   => sprintf(
                'A period of %s months exceeds the eighteen months both treaties allow, so the '
                . 'policy is excluded and retained net.',
                rtrim(rtrim(number_format($months, 1), '0'), '.')
            ),
        ];
    }

    // ──────────────────────────────────────── PML basis, and co-insurance

    /**
     * A PML-basis acceptance below the minimum MPL.
     *
     * @return array{code:string,severity:string,title:string,detail:string}|null
     */
    public function mplFinding(float $mpl, float $sumInsured): ?array
    {
        if ($sumInsured <= 0.0) {
            throw new InvalidArgumentException(
                'A sum insured is needed to test the MPL against.'
            );
        }
        if ($mpl < 0.0) {
            throw new InvalidArgumentException('An MPL cannot be negative.');
        }

        $floor = $sumInsured * self::MPL_MINIMUM_PCT;

        if ($mpl >= $floor) {
            return null;
        }

        return [
            'code'     => 'MPL_BELOW_MINIMUM',
            'severity' => 'refer',
            'title'    => 'MPL below the treaty minimum',
            'detail'   => sprintf(
                'This risk is written on a PML basis at %s, which is %s%% of the sum insured of '
                . '%s. The slip sets a minimum MPL of %s%% on referral, so %s is the least that '
                . 'may be presented without one.',
                number_format($mpl, 2),
                number_format(($mpl / $sumInsured) * 100, 2),
                number_format($sumInsured, 2),
                number_format(self::MPL_MINIMUM_PCT * 100, 0),
                number_format($floor, 2)
            ),
        ];
    }

    /**
     * Alpha Direct's own share of a co-insured risk.
     *
     * THE TREATY SEES OUR SHARE, NOT THE WHOLE RISK. On a 200,000,000 building
     * co-insured 40% to us, the sum insured that reaches the cession is
     * 80,000,000. Presenting the whole 200,000,000 would cede a risk two and a
     * half times the one actually written, and presenting nothing would cede
     * none of it.
     */
    public function coInsuredShare(float $totalSumInsured, float $sharePct): float
    {
        if ($totalSumInsured < 0.0) {
            throw new InvalidArgumentException('A sum insured cannot be negative.');
        }
        if ($sharePct <= 0.0 || $sharePct > 1.0) {
            throw new InvalidArgumentException(
                'A co-insurance share must be greater than 0 and no more than 1.'
            );
        }

        return round($totalSumInsured * $sharePct, 2);
    }

    /**
     * A co-insurance share above what the slip allows.
     *
     * @return array{code:string,severity:string,title:string,detail:string}|null
     */
    public function coInsuranceFinding(float $sharePct): ?array
    {
        if ($sharePct <= 0.0 || $sharePct > 1.0) {
            throw new InvalidArgumentException(
                'A co-insurance share must be greater than 0 and no more than 1.'
            );
        }

        if ($sharePct <= self::CO_INSURANCE_MAX_PCT) {
            return null;
        }

        return [
            'code'     => 'CO_INSURANCE_OVER_SHARE',
            'severity' => 'refer',
            'title'    => 'Co-insurance share above the treaty limit',
            'detail'   => sprintf(
                'Alpha Direct is taking %s%% of this co-insured risk against the %s%% the slip '
                . 'states. Refer to the Leading Reinsurer before binding.',
                number_format($sharePct * 100, 2),
                number_format(self::CO_INSURANCE_MAX_PCT * 100, 0)
            ),
        ];
    }

    // ──────────────────────────────────────── contingent business interruption

    /**
     * The most a CBI extension may carry, on the lower of its two tests.
     *
     * @param  string      $type       named, unnamed or utilities
     * @param  float       $biLimit    the Business Interruption limit of liability
     * @param  float|null  $annualBi   annual BI sum insured, for the utilities test
     */
    public function cbiLimit(string $type, float $biLimit, ?float $annualBi = null): float
    {
        $key = strtolower(trim($type));

        if (! array_key_exists($key, self::CBI)) {
            throw new InvalidArgumentException(
                "Unknown CBI extension '{$type}'. Use named, unnamed or utilities."
            );
        }
        if ($biLimit < 0 || ($annualBi !== null && $annualBi < 0)) {
            throw new InvalidArgumentException('A CBI limit cannot be negative.');
        }

        $terms = self::CBI[$key];

        if ($key === 'utilities') {
            if ($annualBi === null) {
                throw new InvalidArgumentException(
                    'The utilities extension is tested on the ANNUAL business interruption sum '
                    . 'insured, which was not supplied.'
                );
            }
            $onMonths = $annualBi * ($terms['months'] / 12.0);

            return round(min($onMonths, $terms['ceiling']), 2);
        }

        return round(min($biLimit * $terms['share'], $terms['ceiling']), 2);
    }

    /**
     * @return array{code:string,severity:string,title:string,detail:string}|null
     */
    public function cbiFinding(
        string $type,
        float $requested,
        float $biLimit,
        ?float $annualBi = null
    ): ?array {
        $cap = $this->cbiLimit($type, $biLimit, $annualBi);

        if ($requested <= $cap) {
            return null;
        }

        return [
            'code'     => 'CBI_OVER_SUBLIMIT',
            'severity' => 'warn',
            'title'    => 'CBI extension over its sub-limit',
            'detail'   => sprintf(
                'Contingent Business Interruption for %s is written at %s against a sub-limit of '
                . '%s. The excess is outside the treaty and retained net.',
                self::CBI[strtolower(trim($type))]['label'],
                number_format($requested, 2),
                number_format($cap, 2)
            ),
        ];
    }

    /**
     * Whether a denial-of-access location is within reach.
     *
     * Within 50km always. Between 50 and 100km only where the LOGICAL ACCESS
     * POINT is itself beyond 50km — that is the condition the slip attaches, so a
     * location 70km away with a nearer access point is out of reach even though
     * it is inside the extended distance.
     */
    public function cbiAccessInReach(float $km, bool $logicalAccessPointBeyond50 = false): bool
    {
        if ($km < 0) {
            throw new InvalidArgumentException('A distance cannot be negative.');
        }
        if ($km <= self::CBI_ACCESS_KM) {
            return true;
        }

        return $logicalAccessPointBeyond50 && $km <= self::CBI_ACCESS_KM_EXTENDED;
    }

    // ───────────────────────────────────────── motor loss ratio cap

    /**
     * Motor's loss ratio, capped at 75% for commission purposes.
     *
     * The cap protects the REINSURER's commission exposure, not Alpha Direct's:
     * above 75% the sliding scale stops sliding and commission holds at its
     * minimum. Applying the raw ratio instead would carry the scale past the
     * point the slip stops it.
     */
    public function cappedLossRatio(float $lossRatio): float
    {
        if ($lossRatio < 0) {
            throw new InvalidArgumentException('A loss ratio cannot be negative.');
        }

        return min($lossRatio, self::MOTOR_LOSS_RATIO_CAP);
    }

    public function lossRatioCapped(float $lossRatio): bool
    {
        return $lossRatio > self::MOTOR_LOSS_RATIO_CAP;
    }

    // ───────────────────────────────────────── all together

    /**
     * Every control, for one risk.
     *
     * @param  array{sum_insured?:float|int|string,has_fac_placement?:bool,territory?:string,treaty?:string}  $risk
     * @return array{findings:array<int,array<string,string>>, blocked:bool}
     */
    public function assess(array $risk): array
    {
        $treaty   = (string) ($risk['treaty'] ?? 'general');
        $insured  = $risk['insured'] ?? null;
        $findings = [];

        $add = function (?array $f) use (&$findings) {
            if ($f !== null) {
                $findings[] = $f;
            }
        };

        $add($this->facultativeFinding(
            (float) ($risk['sum_insured'] ?? 0),
            (bool) ($risk['has_fac_placement'] ?? false)
        ));

        if (array_key_exists('territory', $risk)) {
            $add($this->territoryFinding((string) $risk['territory'], $treaty, [
                'cover'            => $risk['cover'] ?? null,
                'insured'          => $insured,
                'group'            => $risk['group'] ?? null,
                'domiciled_in_ssa' => $risk['domiciled_in_ssa'] ?? false,
            ]));
        }

        if (array_key_exists('inwards_fac_ceded', $risk) && array_key_exists('treaty_limit', $risk)) {
            $add($this->inwardsFacultativeFinding(
                (float) $risk['inwards_fac_ceded'],
                (float) $risk['treaty_limit'],
                $risk['group'] ?? null,
                $insured
            ));
        }

        if (array_key_exists('accidental_damage_limit', $risk)) {
            $add($this->accidentalDamageFinding((float) $risk['accidental_damage_limit']));
        }

        if (array_key_exists('alop_annual_gross_profit', $risk)) {
            $add($this->alopFinding((float) $risk['alop_annual_gross_profit']));
        }

        $add($this->engineeringReferralFinding($risk));

        if (array_key_exists('policy_months', $risk)) {
            $add($this->policyPeriodFinding((float) $risk['policy_months']));
        }

        return [
            'findings'           => $findings,
            'blocked'            => (bool) array_filter($findings, fn ($f) => $f['severity'] === 'block'),
            'special_acceptance' => $this->specialAcceptanceFor($insured) ?: null,
        ];
    }
}
