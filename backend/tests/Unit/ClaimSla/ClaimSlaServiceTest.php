<?php

namespace Tests\Unit\ClaimSla;

use AlphaDirect\Models\ClaimTrackerWorkflow;
use AlphaDirect\Services\ClaimSla\ClaimSlaCalendar;
use AlphaDirect\Services\ClaimSla\ClaimSlaService;
use AlphaDirect\Services\ClaimSla\WorkingDayCalculator;
use Carbon\Carbon;
use Tests\TestCase;

/**
 * SLA computation logic — deadlines per claim type, breach detection, working-
 * day math (incl. holidays). Boots the app for config('claims_sla') but touches
 * NO database: the calendar is stubbed and workflow rows are transient models.
 */
class ClaimSlaServiceTest extends TestCase
{
    /** A ClaimSlaService whose calendar is a fixed Mon–Fri week + given holidays. */
    private function service(array $holidays = []): ClaimSlaService
    {
        $calendar = new class($holidays) extends ClaimSlaCalendar {
            public function __construct(private array $hols) {}
            public function calculator(): WorkingDayCalculator
            {
                return new WorkingDayCalculator([1, 2, 3, 4, 5], $this->hols, 'Africa/Gaborone');
            }
        };

        return new ClaimSlaService($calendar);
    }

    private function workflow(array $attrs = []): ClaimTrackerWorkflow
    {
        $wf = new ClaimTrackerWorkflow();
        $wf->claim_id = 1;
        foreach ($attrs as $k => $v) {
            $wf->{$k} = $v;
        }
        return $wf;
    }

    public function test_resolves_classes_from_claim_type(): void
    {
        $svc = $this->service();
        $this->assertSame('motor', $svc->resolveClass('Motor Comprehensive'));
        $this->assertSame('glass', $svc->resolveClass('Windscreen / Glass'));
        $this->assertSame('lock_and_key', $svc->resolveClass('Lock and Key'));
        $this->assertSame('non_motor', $svc->resolveClass('Household Contents')); // default
        $this->assertSame('non_motor', $svc->resolveClass(null));
    }

    public function test_motor_matrix_is_17_working_days_over_6_stages(): void
    {
        $svc   = $this->service();
        $start = Carbon::parse('2026-07-06'); // Monday
        $eval  = $svc->evaluate($this->workflow(), 'Motor', $start, $start);

        $this->assertSame('motor', $eval['class']);
        $this->assertSame(17, $eval['total_working_days']);
        $this->assertCount(6, $eval['stages']);
        // Overall due = 17 working days after the Monday start = 2026-07-29 (Wed).
        $this->assertSame('2026-07-29', $eval['overall_due_date']);
        // Last stage cumulative budget is the class total.
        $this->assertSame(17, end($eval['stages'])['due_working_days']);
    }

    public function test_glass_and_lock_are_two_working_days(): void
    {
        $svc   = $this->service();
        $start = Carbon::parse('2026-07-06');
        $this->assertSame(2, $svc->evaluate($this->workflow(), 'Glass', $start, $start)['total_working_days']);
        $this->assertSame(2, $svc->evaluate($this->workflow(), 'Lock & Key', $start, $start)['total_working_days']);
    }

    public function test_non_motor_uses_default_working_days(): void
    {
        $svc   = $this->service();
        $start = Carbon::parse('2026-07-06');
        $eval  = $svc->evaluate($this->workflow(['non_motor_sub_type' => 'Unknown Sub']), 'Fire & Allied', $start, $start);
        $this->assertSame('non_motor', $eval['class']);
        $this->assertSame((int) config('claims_sla.matrix.non_motor.default_working_days'), $eval['total_working_days']);
    }

    public function test_open_claim_before_due_is_on_track(): void
    {
        $svc   = $this->service();
        $start = Carbon::parse('2026-07-06');
        // Evaluate on the start day: nothing done, well before any due date.
        $eval = $svc->evaluate($this->workflow(), 'Motor', $start, $start);
        $this->assertSame('on_track', $eval['overall_status']);
        $this->assertFalse($eval['breached']);
        $this->assertFalse($eval['completed']);
    }

    public function test_open_claim_past_overall_due_is_breached(): void
    {
        $svc   = $this->service();
        $start = Carbon::parse('2026-07-06');
        // asOf well past the 17-wd overall due, nothing completed.
        $eval = $svc->evaluate($this->workflow(), 'Motor', $start, Carbon::parse('2026-08-15'));
        $this->assertSame('breached', $eval['overall_status']);
        $this->assertTrue($eval['breached']);
    }

    public function test_completed_on_time_is_met_completed_late_is_missed(): void
    {
        $svc   = $this->service();
        $start = Carbon::parse('2026-07-06'); // overall due 2026-07-29

        // Job finished on the due date => met.
        $met = $svc->evaluate($this->workflow(['job_end_date' => '2026-07-29']), 'Motor', $start, Carbon::parse('2026-07-30'));
        $this->assertSame('met', $met['overall_status']);
        $this->assertTrue($met['completed']);
        $this->assertFalse($met['breached']);

        // Job finished after the due date => missed (retrospective breach).
        $missed = $svc->evaluate($this->workflow(['job_end_date' => '2026-08-05']), 'Motor', $start, Carbon::parse('2026-08-06'));
        $this->assertSame('missed', $missed['overall_status']);
        $this->assertTrue($missed['breached']);
    }

    public function test_due_soon_within_warning_window(): void
    {
        $svc   = $this->service();
        $start = Carbon::parse('2026-07-06');
        // Glass overall due = 2 wd after Mon = Wed 2026-07-08. One working day
        // before (Tue 07-07) should be amber with the default 1-day window.
        $eval = $svc->evaluate($this->workflow(), 'Glass', $start, Carbon::parse('2026-07-07'));
        $this->assertSame('due_soon', $eval['overall_status']);
    }

    public function test_holiday_pushes_due_date_out(): void
    {
        $start = Carbon::parse('2026-07-06'); // Monday
        // Glass 2 wd with NO holiday => Wed 07-08.
        $this->assertSame('2026-07-08', $this->service()->evaluate($this->workflow(), 'Glass', $start, $start)['overall_due_date']);
        // With Tue 07-07 a holiday, the 2nd working day slips to Thu 07-09.
        $this->assertSame('2026-07-09', $this->service(['2026-07-07'])->evaluate($this->workflow(), 'Glass', $start, $start)['overall_due_date']);
    }

    public function test_per_stage_breach_flags(): void
    {
        $svc   = $this->service();
        $start = Carbon::parse('2026-07-06');
        // Stage 1 (assessor allotment) due 2 wd out = Wed 07-08; leave it open
        // and evaluate a week later => that stage breached.
        $eval   = $svc->evaluate($this->workflow(), 'Motor', $start, Carbon::parse('2026-07-15'));
        $stage1 = $eval['stages'][0];
        $this->assertSame('assessor_allotment_date', $stage1['key']);
        $this->assertSame('breached', $stage1['status']);
        $this->assertTrue($stage1['breached']);
    }
}
