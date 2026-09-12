<?php

namespace AlphaDirect\Services\Reinsurance;

use Illuminate\Support\Carbon;

/**
 * The treaty conditions a facultative placement has to respect, tested against
 * what the register actually holds.
 *
 * SOURCE: the "CONDITIONS ATTACHING TO THE CAPACITY ABOVE" section of the Alpha
 * Direct Capacities Table 2026/27, which states on its own face that it is built
 * solely from the two signed slips.
 *
 * WHAT THIS DOES NOT DO. That section carries eight conditions. Only two of them
 * can be tested from the fields a placement captures, and only those two are here:
 *
 *   · policy period — the treaty excludes a period over eighteen months in all
 *   · the named-group exception to the inwards-facultative restriction
 *
 * The rest are absent on purpose, not by oversight:
 *
 *   · inwards FAC restricted to 25% of the treaty limit — the limit it is 25% OF
 *     is the unresolved question on the cession bifurcation. Testing against an
 *     unconfirmed number would give a false pass or a false fail, and either is
 *     worse than saying nothing.
 *   · minimum MPL 50% on referral, co-insurance 50%, the engineering referral
 *     thresholds, the advanced loss of profits ceiling — these need an MPL, a
 *     co-insurance share, a plant value, a project value, a bridge span and an
 *     annual gross profit. None is captured anywhere, and whether they belong on
 *     the FAC placement or upstream in underwriting is Underwriting's call.
 *
 * EVERYTHING HERE IS A REPORT, NEVER A BLOCK. A finding tells the underwriter what
 * the treaty says; it does not refuse the capture. Refusing a placement on a rule
 * derived from a table rather than from the slip itself would stop real work on the
 * strength of a secondary document.
 */
class FacMandateService
{
    /**
     * Every mandate finding for one placement.
     *
     * @param  object  $p  A placement row or model — anything carrying period_from,
     *                     period_to and insured_name.
     * @return array<int,array{code:string,severity:string,title:string,detail:string,authority:string}>
     */
    public function findingsFor(object $p): array
    {
        return array_values(array_filter([
            $this->policyPeriodFinding($p),
            $this->namedGroupFinding($p),
        ]));
    }

    /** Short codes only, for the register's row flags. @return array<int,string> */
    public function flagsFor(object $p): array
    {
        return array_column($this->findingsFor($p), 'code');
    }

    /**
     * Policy period over the treaty's ceiling.
     *
     * "Policies issued or renewed for a period exceeding twelve months plus odd
     * time not exceeding eighteen months in all are excluded." So twelve months
     * plus odd time is allowed, and the ceiling on the whole thing is eighteen.
     * A policy longer than that is outside BOTH treaties, which makes any cession
     * shown against it cover that does not exist.
     *
     * @return array{code:string,severity:string,title:string,detail:string,authority:string}|null
     */
    public function policyPeriodFinding(object $p): ?array
    {
        $months = $this->policyMonths($p);
        if ($months === null) {
            return null;
        }

        $max = (float) config('fac.mandates.max_policy_months', 18.0);
        if ($months <= $max) {
            return null;
        }

        return [
            'code'      => 'policy_period_over_treaty_max',
            'severity'  => 'danger',
            'title'     => 'Policy period is outside the treaty',
            'detail'    => sprintf(
                'This policy runs %s months. Both treaties exclude a period exceeding twelve '
                . 'months plus odd time, not exceeding %s months in all, so this risk is not '
                . 'covered by either treaty and any cession shown against it is not cover.',
                rtrim(rtrim(number_format($months, 1, '.', ''), '0'), '.'),
                rtrim(rtrim(number_format($max, 1, '.', ''), '0'), '.')
            ),
            'authority' => 'Capacities Table 2026/27, Policy period (both treaties)',
        ];
    }

    /**
     * One of the named groups that may cede up to 100% of treaty capacity.
     *
     * This is INFORMATION, and the wording matters. A match does not grant the
     * exception — it tells the underwriter the exception may be available, so the
     * 25% restriction is not applied by reflex to a risk the slip carves out. The
     * match is on the insured name because that is all the register holds, and a
     * group trades under more than one name, so a miss is expected and is not a
     * finding either way.
     *
     * @return array{code:string,severity:string,title:string,detail:string,authority:string}|null
     */
    public function namedGroupFinding(object $p): ?array
    {
        $group = $this->namedGroupFor($p);
        if ($group === null) {
            return null;
        }

        return [
            'code'      => 'named_group_exception',
            'severity'  => 'info',
            'title'     => $group . ' Group — inwards FAC exception may apply',
            'detail'    => sprintf(
                'The insured appears to be a %s Group risk. Inwards facultative reinsurance '
                . 'ceded to the treaty is normally restricted to 25%% of the treaty limit, but '
                . 'the General slip carves out %s Group, where up to 100%% of treaty capacity '
                . 'may be ceded. Confirm the insured really is part of the group before relying '
                . 'on this — the match is on the name only.',
                $group,
                $group
            ),
            'authority' => 'Capacities Table 2026/27, Inwards Facultative Reinsurance ceded to the treaty',
        ];
    }

    /** Which named group the insured looks like, if any. */
    public function namedGroupFor(object $p): ?string
    {
        $insured = trim((string) ($p->insured_name ?? ''));
        if ($insured === '') {
            return null;
        }

        foreach ((array) config('fac.mandates.named_group_exceptions', []) as $group) {
            $group = trim((string) $group);
            if ($group !== '' && stripos($insured, $group) !== false) {
                return $group;
            }
        }

        return null;
    }

    /**
     * The policy period in months, or null where it cannot be told.
     *
     * Null rather than zero when a date is missing: an unknown period is not a
     * short one, and reporting "0 months" would read as a pass on a risk nobody
     * has actually measured.
     */
    public function policyMonths(object $p): ?float
    {
        $from = $p->period_from ?? null;
        $to   = $p->period_to ?? null;
        if (!$from || !$to) {
            return null;
        }

        try {
            $start = Carbon::parse($from)->startOfDay();
            $end   = Carbon::parse($to)->startOfDay();
        } catch (\Throwable) {
            return null;
        }

        if ($end->lessThanOrEqualTo($start)) {
            return null;
        }

        // Whole months plus the part month, so 12 months and one day reads as
        // 12.03 rather than rounding down to a compliant 12.
        $whole = $start->diffInMonths($end);
        $rest  = $start->copy()->addMonths($whole);
        $span  = $rest->copy()->addMonth()->diffInDays($rest) ?: 30;

        return round($whole + ($rest->diffInDays($end) / $span), 2);
    }
}
