<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A scheduled executive claims KPI email report.
 *
 * Rows describe WHEN a report fires and WHO receives it; the actual send is
 * gated by the `claims_scheduled_reports` feature flag + `enabled` + a non-empty
 * recipient list (see ClaimsRunReportSchedules). This model never sends mail.
 *
 * @property int         $id
 * @property string      $name
 * @property string      $report_type
 * @property string      $frequency      daily|weekly|monthly
 * @property int|null    $day_of_week    0(Sun)..6(Sat) for weekly
 * @property int|null    $day_of_month   1..31 for monthly
 * @property int         $hour           0..23
 * @property array       $recipients     email addresses
 * @property bool        $enabled
 */
class ClaimReportSchedule extends Model
{
    protected $table = 'claim_report_schedules';

    protected $fillable = [
        'name',
        'report_type',
        'frequency',
        'day_of_week',
        'day_of_month',
        'hour',
        'recipients',
        'enabled',
        'last_run_at',
        'last_status',
        'last_note',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'recipients'   => 'array',
        'enabled'      => 'boolean',
        'day_of_week'  => 'integer',
        'day_of_month' => 'integer',
        'hour'         => 'integer',
        'last_run_at'  => 'datetime',
    ];

    /**
     * Is this schedule due to fire at $now, and has it not already fired this
     * period? Matches the hour, then the day rule for the frequency, and finally
     * de-dupes against last_run_at so a per-minute/hourly tick fires it once.
     */
    public function isDue(Carbon $now): bool
    {
        if ((int) $this->hour !== (int) $now->hour) {
            return false;
        }

        switch ($this->frequency) {
            case 'daily':
                $matchesDay = true;
                break;

            case 'weekly':
                // Carbon dayOfWeek: 0 (Sun) .. 6 (Sat) — same convention we store.
                $matchesDay = $this->day_of_week !== null
                    && (int) $this->day_of_week === (int) $now->dayOfWeek;
                break;

            case 'monthly':
                // Clamp the configured day to the current month's length so
                // "31" still fires on the last day of a short month.
                $target = min((int) ($this->day_of_month ?? 1), $now->daysInMonth);
                $matchesDay = $target === (int) $now->day;
                break;

            default:
                return false;
        }

        if (!$matchesDay) {
            return false;
        }

        // De-dupe: already ran within this same hour window today.
        if ($this->last_run_at instanceof Carbon
            && $this->last_run_at->isSameDay($now)
            && (int) $this->last_run_at->hour === (int) $now->hour) {
            return false;
        }

        return true;
    }

    /** Normalised, de-duplicated, valid recipient email list. */
    public function recipientList(): array
    {
        $list = is_array($this->recipients) ? $this->recipients : [];
        $clean = [];
        foreach ($list as $email) {
            $email = trim((string) $email);
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $clean[strtolower($email)] = $email;
            }
        }
        return array_values($clean);
    }

    /** The period [from, to] this run should report on, given its frequency. */
    public function periodFor(Carbon $now): array
    {
        switch ($this->frequency) {
            case 'daily':
                return [$now->copy()->subDay()->startOfDay(), $now->copy()->subDay()->endOfDay()];
            case 'monthly':
                return [$now->copy()->subMonthNoOverflow()->startOfMonth(), $now->copy()->subMonthNoOverflow()->endOfMonth()];
            case 'weekly':
            default:
                return [$now->copy()->subDays(7)->startOfDay(), $now->copy()->subDay()->endOfDay()];
        }
    }
}
