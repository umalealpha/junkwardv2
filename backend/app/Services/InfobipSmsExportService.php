<?php

namespace AlphaDirect\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * GRA-0155 — shared Infobip SMS-log export logic.
 *
 * Single source of truth for turning `sms_infobip_log` rows into a CSV on the
 * company S3 archive. Used by:
 *   - ExportInfobipSmsLogsDaily (nightly cron — previous day)
 *   - SmsLogExportController::generate (on-demand date-range, self-service)
 *
 * Data source is `sms_infobip_log` only — the authoritative Infobip
 * delivery/status record. We deliberately do NOT join `sms_email_log`
 * (message body / recipient): its FK `sms_infobip_log.sms_email_log_id` is NULL
 * on every row (SendSmsFired.php save-order bug), and it is an unindexed giant
 * table that would time out a daily/range slice. See ExportInfobipSmsLogsDaily.
 */
class InfobipSmsExportService
{
    /** Column order for the exported CSV (also the header row). */
    public const COLUMNS = [
        'infobip_log_id',
        'sms_email_log_id',
        'message_id',
        'status',
        'pull_status',
        'sentAt',
        'doneAt',
        'done_at',
        'error',
        'created_at',
        'updated_at',
    ];

    public function disk(): string
    {
        return (string) config('infobip-sms.export_disk', 's3');
    }

    public function prefix(): string
    {
        return rtrim((string) config('infobip-sms.export_prefix', 'reports/infobip-sms-logs'), '/');
    }

    /**
     * Build the CSV for [$from 00:00:00 .. $to 23:59:59] and store it on the
     * export disk. Returns [s3Path, filename, rowCount]. Does NOT upload when
     * there are zero rows (rowCount 0, s3Path/filename null).
     *
     * @param  Carbon  $from  inclusive start day
     * @param  Carbon  $to    inclusive end day
     * @return array{path: ?string, filename: ?string, rows: int}
     */
    public function exportRange(Carbon $from, Carbon $to): array
    {
        $startDate = $from->copy()->startOfDay()->format('Y-m-d H:i:s');
        $endDate   = $to->copy()->endOfDay()->format('Y-m-d H:i:s');

        Log::info("InfobipSmsExport: building CSV for {$startDate} .. {$endDate}");

        $csv = implode(',', self::COLUMNS) . "\n";
        $rowCount = 0;

        // Chunk defensively — a normal day is small, but bulk-SMS campaign days
        // spike. orderBy id keeps chunk paging stable.
        DB::table('sms_infobip_log as l')
            ->whereBetween('l.created_at', [$startDate, $endDate])
            ->orderBy('l.id')
            ->select([
                'l.id as infobip_log_id',
                'l.sms_email_log_id',
                'l.message_id',
                'l.status',
                'l.pull_status',
                'l.sentAt',
                'l.doneAt',
                'l.done_at',
                'l.error',
                'l.created_at',
                'l.updated_at',
            ])
            ->chunk(2000, function ($rows) use (&$csv, &$rowCount) {
                foreach ($rows as $r) {
                    $line = [];
                    foreach (self::COLUMNS as $col) {
                        $line[] = $this->csvCell($r->{$col} ?? null);
                    }
                    $csv .= implode(',', $line) . "\n";
                    $rowCount++;
                }
            });

        if ($rowCount === 0) {
            return ['path' => null, 'filename' => null, 'rows' => 0];
        }

        $label = $from->isSameDay($to)
            ? $from->format('Y-m-d')
            : $from->format('Y-m-d') . '_to_' . $to->format('Y-m-d');

        $filename = 'infobip_sms_logs_' . $label . '_' . Carbon::now()->timestamp . '.csv';
        $s3Path   = $this->prefix() . '/' . $filename;

        Storage::disk($this->disk())->put($s3Path, $csv, 'public');

        Log::info("InfobipSmsExport: {$rowCount} rows uploaded to {$s3Path}");

        return ['path' => $s3Path, 'filename' => $filename, 'rows' => $rowCount];
    }

    /**
     * Escape a value for CSV. Wraps in quotes and doubles internal quotes when
     * the cell contains a comma, quote or newline.
     */
    private function csvCell($value): string
    {
        if ($value === null) {
            return '';
        }
        $s = (string) $value;
        if (strpbrk($s, ",\"\n\r") !== false) {
            return '"' . str_replace('"', '""', $s) . '"';
        }
        return $s;
    }
}
