<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Models\TreatyStatement;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * The 45- and 14-day clocks — RI-18 step 7, BR-ACC-01 and BR-ACC-02.
 *
 * TWO DEADLINES AND ONE GAP. Accounts are rendered within 45 days of the
 * quarter's close and confirmed within 14 of being rendered. Both are
 * measurable against the statements table. The third thing this reports is not:
 * a quarter that CLOSED AND HAS NO STATEMENT AT ALL cannot be overdue, because
 * nothing is looking at it — it is invisible to every query that reads the
 * table, and reads exactly like a quarter that has not closed yet. So the
 * quarters are enumerated from the calendar and compared against what exists,
 * rather than the other way round.
 *
 * IT REPORTS; IT DOES NOT RENDER. Rendering an account is sending figures to
 * reinsurers and is somebody's decision, not a scheduler's. --open will create
 * the missing DRAFT shells, because an empty draft commits nothing and makes
 * the work visible, but nothing here moves a statement to rendered.
 *
 * --as-at EXISTS SO THE CLOCKS CAN BE TESTED. A deadline that can only be
 * exercised by waiting for a real date is one nobody tests, and these decide
 * whether interest starts running under BR-ACC-10.
 */
class TreatyStatementClocks extends Command
{
    protected $signature = 'treaty:statement-clocks
        {--as-at= : Judge the clocks as at this date (Y-m-d), for testing}
        {--treaty= : Limit to one treaty}
        {--warn-days=7 : Days before a deadline to start warning}
        {--open : Create DRAFT statements for closed quarters that have none}';

    protected $description = 'Report treaty statements due to be rendered (45 days) or confirmed (14 days)';

    /** The treaties that render accounts. */
    private const TREATIES = ['general', 'motor'];

    public function handle(): int
    {
        $asAt = $this->option('as-at') ?: date('Y-m-d');

        if (strtotime($asAt) === false) {
            $this->error("Not a date: {$asAt}");

            return self::FAILURE;
        }

        $treaties = $this->option('treaty')
            ? [strtolower(trim((string) $this->option('treaty')))]
            : self::TREATIES;

        $report = $this->report($asAt, $treaties, (int) $this->option('warn-days'));

        if ($this->option('open')) {
            $report['opened'] = $this->openMissing($report['missing']);
        }

        $this->render($report, $asAt);

        foreach ($report['overdue_render'] as $r) {
            Log::warning('Treaty statement overdue for rendering', $r);
        }
        foreach ($report['overdue_confirm'] as $r) {
            Log::warning('Treaty statement overdue for confirmation', $r);
        }
        foreach ($report['missing'] as $r) {
            Log::warning('Treaty quarter closed with no statement', $r);
        }

        // ALWAYS SUCCESS. A late statement is news, not a failed job — a
        // scheduler that reports failure every night until somebody renders an
        // account trains people to ignore it.
        return self::SUCCESS;
    }

    /**
     * @param  array<int,string>  $treaties
     * @return array<string,mixed>
     */
    public function report(string $asAt, array $treaties, int $warnDays = 7): array
    {
        $missing = [];
        $dueSoon = [];
        $overdueRender = [];
        $overdueConfirm = [];
        $awaitingConfirm = [];

        foreach ($treaties as $treaty) {
            $quarters = TreatyStatement::quartersClosedBy($asAt);

            foreach ($quarters as $q) {
                $statement = TreatyStatement::where('treaty', $treaty)
                    ->where('underwriting_year', $q['underwriting_year'])
                    ->where('quarter', $q['quarter'])
                    ->first();

                if ($statement === null) {
                    $missing[] = [
                        'treaty'     => $treaty,
                        'year'       => $q['underwriting_year'],
                        'quarter'    => $q['quarter'],
                        'period_end' => $q['period_end'],
                        'render_due' => $q['render_due'],
                        'days_over'  => $this->daysBetween($q['render_due'], $asAt),
                    ];

                    continue;
                }

                $row = [
                    'treaty'     => $treaty,
                    'year'       => (int) $statement->underwriting_year,
                    'quarter'    => (int) $statement->quarter,
                    'status'     => (string) $statement->status,
                    'render_due' => $q['render_due'],
                ];

                $renderLate = $statement->renderLateness($asAt);

                if ($statement->getRawOriginal('rendered_at') === null) {
                    if ($renderLate['late']) {
                        $overdueRender[] = $row + ['days_over' => $renderLate['days']];
                    } elseif ($this->daysBetween($asAt, $q['render_due']) <= $warnDays) {
                        $dueSoon[] = $row + ['days_left' => $this->daysBetween($asAt, $q['render_due'])];
                    }

                    continue;
                }

                // Rendered. Now the 14-day clock.
                if (in_array($statement->status, [
                    TreatyStatement::STATUS_CONFIRMED,
                    TreatyStatement::STATUS_SETTLED,
                ], true)) {
                    continue;
                }

                $confirmLate = $statement->confirmLateness($asAt);

                if ($confirmLate['late']) {
                    $overdueConfirm[] = $row + [
                        'confirm_due' => (string) ($statement->getRawOriginal('confirm_due') ?? ''),
                        'days_over'   => $confirmLate['days'],
                    ];
                } else {
                    $awaitingConfirm[] = $row + [
                        'confirm_due' => (string) ($statement->getRawOriginal('confirm_due') ?? ''),
                    ];
                }
            }
        }

        return [
            'as_at'             => $asAt,
            'missing'           => $missing,
            'due_soon'          => $dueSoon,
            'overdue_render'    => $overdueRender,
            'awaiting_confirm'  => $awaitingConfirm,
            'overdue_confirm'   => $overdueConfirm,
        ];
    }

    /**
     * Create DRAFT shells for closed quarters that have none.
     *
     * A draft commits nothing — it carries no items and no figures — but it puts
     * the quarter where the rest of the process can see it.
     *
     * @param  array<int,array<string,mixed>>  $missing
     * @return int
     */
    private function openMissing(array $missing): int
    {
        $opened = 0;

        foreach ($missing as $m) {
            $p = TreatyStatement::periodFor((int) $m['year'], (int) $m['quarter']);

            TreatyStatement::create([
                'treaty'            => $m['treaty'],
                'underwriting_year' => $m['year'],
                'quarter'           => $m['quarter'],
                'period_start'      => $p['start'],
                'period_end'        => $p['end'],
                'render_due'        => $p['render_due'],
                'status'            => TreatyStatement::STATUS_DRAFT,
                'note'              => 'Opened by treaty:statement-clocks — the quarter had closed '
                                     . 'with no statement.',
            ]);

            $opened++;
        }

        return $opened;
    }

    /** Whole days from one date to another, negative if the second is earlier. */
    private function daysBetween(string $from, string $to): int
    {
        return (int) floor(
            (strtotime(substr($to, 0, 10)) - strtotime(substr($from, 0, 10))) / 86400
        );
    }

    /** @param array<string,mixed> $report */
    private function render(array $report, string $asAt): void
    {
        $this->line("Treaty statement clocks as at {$asAt}");
        $this->line('');

        $sections = [
            'missing'          => 'CLOSED QUARTERS WITH NO STATEMENT (BR-ACC-01)',
            'overdue_render'   => 'OVERDUE FOR RENDERING (BR-ACC-01, 45 days)',
            'overdue_confirm'  => 'OVERDUE FOR CONFIRMATION (BR-ACC-02, 14 days)',
            'due_soon'         => 'Due for rendering shortly',
            'awaiting_confirm' => 'Rendered, inside the confirmation window',
        ];

        $anything = false;

        foreach ($sections as $key => $heading) {
            if ($report[$key] === []) {
                continue;
            }

            $anything = true;
            $this->line($heading);

            foreach ($report[$key] as $r) {
                $tail = isset($r['days_over'])
                    ? "{$r['days_over']} days over"
                    : (isset($r['days_left']) ? "{$r['days_left']} days left" : ($r['status'] ?? ''));

                $this->line(sprintf(
                    '  %-8s %d Q%d   due %s   %s',
                    $r['treaty'],
                    $r['year'],
                    $r['quarter'],
                    $r['render_due'],
                    $tail
                ));
            }

            $this->line('');
        }

        if (isset($report['opened'])) {
            $this->info("Opened {$report['opened']} draft statement(s).");
        }

        if (! $anything) {
            $this->info('Nothing due. Every closed quarter has a statement and no clock has run out.');
        }
    }
}
