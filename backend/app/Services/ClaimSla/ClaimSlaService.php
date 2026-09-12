<?php

namespace AlphaDirect\Services\ClaimSla;

use AlphaDirect\Models\ClaimTrackerWorkflow;
use Carbon\Carbon;

/**
 * ClaimSlaService — computes a claim's SLA ON THE FLY from the stage timestamps
 * already held in claim_tracker_workflow (+ the claim's start anchor). It does
 * NOT persist anything and NEVER writes to live claim / financial tables, so it
 * is completely inert until read and safe to ship dark.
 *
 * The deadline matrix (working days per claim class / sub-type) lives in
 * config/claims_sla.php; the working-day math is done by WorkingDayCalculator
 * over the shared Help Desk business calendar.
 *
 * Stage status vocabulary:
 *   met       — stage completed on/before its due date
 *   missed    — stage completed AFTER its due date (retrospective breach)
 *   breached  — stage still open and now past its due date
 *   due_soon  — stage open, within the amber warning window
 *   on_track  — stage open, comfortably before due
 * `breached` boolean on a stage/overall == status in {missed, breached}.
 */
class ClaimSlaService
{
    public function __construct(private ClaimSlaCalendar $calendar)
    {
    }

    /**
     * Resolve the SLA start anchor (working-day 0) for a claim from config:
     * the workflow anchor column first, then the claim fallbacks, finally the
     * workflow row's own created_at / now(). $claim may be a Claim model or any
     * object/array carrying the fallback attributes.
     */
    public function startAnchor(ClaimTrackerWorkflow $wf, $claim = null): Carbon
    {
        $anchorCol = (string) config('claims_sla.start_anchor.workflow_column', 'claim_docs_received');
        if (!empty($wf->{$anchorCol})) {
            return Carbon::parse($wf->{$anchorCol});
        }
        foreach ((array) config('claims_sla.start_anchor.claim_fallbacks', []) as $attr) {
            $val = is_array($claim) ? ($claim[$attr] ?? null) : ($claim->{$attr} ?? null);
            if (!empty($val)) {
                return Carbon::parse($val);
            }
        }
        return $wf->created_at ? Carbon::parse($wf->created_at) : Carbon::now();
    }

    /**
     * Map a coarse claims.claim_type to an SLA matrix class using the
     * case-insensitive substring map in config (first match wins).
     */
    public function resolveClass(?string $claimType): string
    {
        $type = strtolower(trim((string) $claimType));
        if ($type !== '') {
            foreach ((array) config('claims_sla.type_map', []) as $needle => $class) {
                if (str_contains($type, strtolower((string) $needle))) {
                    return $class;
                }
            }
        }
        return (string) config('claims_sla.default_class', 'non_motor');
    }

    /**
     * Resolve the working-day total + concrete stage list for a class/sub-type.
     *
     * @return array{total:int, label:string, stages:array<int,array{key:string,label:string,working_days:int}>}
     */
    public function resolveMatrix(string $class, ?string $subType = null): array
    {
        $matrix = (array) config('claims_sla.matrix', []);
        $def    = $matrix[$class] ?? $matrix[(string) config('claims_sla.default_class', 'non_motor')] ?? null;

        if (!$def) {
            return ['total' => 0, 'label' => ucfirst($class), 'stages' => []];
        }

        // Total working days: sub-type override for non-motor-style classes.
        if (array_key_exists('default_working_days', $def)) {
            $total = (int) $def['default_working_days'];
            $sub   = strtolower(trim((string) $subType));
            if ($sub !== '') {
                foreach ((array) ($def['sub_types'] ?? []) as $name => $days) {
                    if (strtolower((string) $name) === $sub) {
                        $total = (int) $days;
                        break;
                    }
                }
            }
        } else {
            $total = (int) ($def['total_working_days'] ?? 0);
        }

        $stages = [];
        foreach ((array) ($def['stages'] ?? []) as $stage) {
            $stages[] = [
                'key'          => (string) $stage['key'],
                'label'        => (string) $stage['label'],
                // A null working_days means "the class total" (single-deadline classes).
                'working_days' => $stage['working_days'] === null ? $total : (int) $stage['working_days'],
            ];
        }

        return ['total' => $total, 'label' => (string) ($def['label'] ?? ucfirst($class)), 'stages' => $stages];
    }

    /**
     * Full per-claim SLA evaluation.
     *
     * @param ClaimTrackerWorkflow $wf         the stage timeline row
     * @param string               $claimType  claims.claim_type (coarse)
     * @param Carbon               $startAnchor SLA clock start (working day 0)
     * @param Carbon|null          $asOf        evaluation instant (defaults now)
     * @return array<string,mixed>
     */
    public function evaluate(ClaimTrackerWorkflow $wf, string $claimType, Carbon $startAnchor, ?Carbon $asOf = null): array
    {
        $asOf   = $asOf ? $asOf->copy() : Carbon::now();
        $calc   = $this->calendar->calculator();
        $class  = $this->resolveClass($claimType);
        $sub    = $wf->non_motor_sub_type;
        $matrix = $this->resolveMatrix($class, $sub);
        $window = (int) config('claims_sla.due_soon_working_days', 1);

        $stages       = [];
        $anyBreached  = false;
        $anyDueSoon   = false;
        $lastKey      = null;

        foreach ($matrix['stages'] as $stage) {
            $lastKey       = $stage['key'];
            $dueDate       = $calc->addWorkingDays($startAnchor, (int) $stage['working_days']);
            $completedRaw  = $wf->{$stage['key']} ?? null;
            $completedDate = $completedRaw ? Carbon::parse($completedRaw) : null;

            $status = $this->stageStatus($completedDate, $dueDate, $asOf, $calc, $window);
            $breached = in_array($status, ['missed', 'breached'], true);
            $anyBreached = $anyBreached || $breached;
            $anyDueSoon  = $anyDueSoon || $status === 'due_soon';

            $stages[] = [
                'key'                   => $stage['key'],
                'label'                 => $stage['label'],
                'due_working_days'      => (int) $stage['working_days'],
                'due_date'              => $dueDate->toDateString(),
                'completed_date'        => $completedDate?->toDateString(),
                'status'                => $status,
                'breached'              => $breached,
                'variance_working_days' => $this->variance($completedDate, $dueDate, $asOf, $calc),
            ];
        }

        // Overall = driven by the final (job-completion) stage / class total.
        $overallDue       = $calc->addWorkingDays($startAnchor, (int) $matrix['total']);
        $overallCompleted = $lastKey && ($wf->{$lastKey} ?? null) ? Carbon::parse($wf->{$lastKey}) : null;
        $overallStatus    = $this->stageStatus($overallCompleted, $overallDue, $asOf, $calc, $window);

        return [
            'claim_id'           => $wf->claim_id,
            'class'              => $class,
            'class_label'        => $matrix['label'],
            'sub_type'           => $sub,
            'customer_type'      => $wf->customer_type,
            'start_date'         => $startAnchor->toDateString(),
            'total_working_days' => (int) $matrix['total'],
            'overall_due_date'   => $overallDue->toDateString(),
            'overall_status'     => $overallStatus,
            'breached'           => in_array($overallStatus, ['missed', 'breached'], true),
            'completed'          => $overallCompleted !== null,
            'stages'             => $stages,
        ];
    }

    /** Classify one stage. */
    private function stageStatus(?Carbon $completed, Carbon $due, Carbon $asOf, WorkingDayCalculator $calc, int $window): string
    {
        if ($completed !== null) {
            return $completed->startOfDay()->lte($due->copy()->startOfDay()) ? 'met' : 'missed';
        }
        $today = $asOf->copy()->startOfDay();
        if ($today->gt($due->copy()->startOfDay())) {
            return 'breached';
        }
        // Working days remaining until due; <= window (and >= 0) => amber.
        $remaining = $calc->workingDaysBetween($today, $due);
        return $remaining <= $window ? 'due_soon' : 'on_track';
    }

    /**
     * Signed working-day variance vs due date. Positive = late/over,
     * negative = early/under. Uses completion date if done, else "now".
     */
    private function variance(?Carbon $completed, Carbon $due, Carbon $asOf, WorkingDayCalculator $calc): float
    {
        $ref = ($completed ?? $asOf)->copy()->startOfDay();
        $d   = $due->copy()->startOfDay();
        if ($ref->gt($d)) {
            return round($calc->workingDaysBetween($d, $ref), 2);
        }
        return round(-1 * $calc->workingDaysBetween($ref, $d), 2);
    }
}
