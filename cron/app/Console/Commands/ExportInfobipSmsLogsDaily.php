<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use AlphaDirect\Models\CronStatus;
use Carbon\Carbon;

/**
 * GRA-0155 — Scheduled daily export of Infobip SMS delivery logs (CRON COPY).
 *
 * This is the cron/ container copy of the command — the container that actually
 * runs prod scheduled jobs. It is kept deliberately SELF-CONTAINED (inline CSV
 * logic, no dependency on the backend/ InfobipSmsExportService or the
 * infobip-sms config keys, which don't exist in this app). The backend/ copy is
 * the richer one (shares logic with the self-service download controller); this
 * copy only has to do the nightly export.
 *
 * Ticket intent: archive the previous day's Infobip SMS channel records to the
 * company S3 bucket so we stop paying Infobip archived-log retrieval fees.
 * "OMNI" in the ticket title is NOT an external system — it's shorthand for our
 * own storage + a self-service download (built on the backend/ side). S3 is the
 * destination.
 *
 * Data source: `sms_infobip_log` only (authoritative Infobip delivery/status
 * record). We do NOT join `sms_email_log`: its FK sms_email_log_id is NULL on
 * every row (SendSmsFired.php save-order bug) and it's an unindexed giant table
 * that would time out the nightly slice.
 *
 * Pattern copied from: DailyTransectionCsv.php. Scheduled via the cron_kernel
 * table (run_on_server='cron_server', run_type 'Daily'). See report notes.
 */
class ExportInfobipSmsLogsDaily extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sms:export-infobip-logs
                            {--date= : Export this Y-m-d instead of yesterday (backfill)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'GRA-0155: Export the previous day\'s Infobip SMS delivery logs to a CSV on S3 (company archive)';

    /**
     * Column order for the exported CSV.
     */
    private array $columns = [
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

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        ini_set('memory_limit', '512M');

        $cron = new CronStatus();
        $cron->name = 'sms:export-infobip-logs';
        $cron->start = Carbon::now();
        $cron->save();

        try {
            $day = $this->option('date')
                ? Carbon::parse($this->option('date'))
                : Carbon::parse('today')->subDay();

            $startDate = $day->copy()->format('Y-m-d') . ' 00:00:00';
            $endDate   = $day->copy()->format('Y-m-d') . ' 23:59:59';

            Log::info("InfobipSmsExport: exporting {$startDate} .. {$endDate}");

            $csv = implode(',', $this->columns) . "\n";
            $rowCount = 0;

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
                        foreach ($this->columns as $col) {
                            $line[] = $this->csvCell($r->{$col} ?? null);
                        }
                        $csv .= implode(',', $line) . "\n";
                        $rowCount++;
                    }
                });

            if ($rowCount === 0) {
                Log::info('InfobipSmsExport: no SMS log rows for the period — nothing to export.');
                $this->info('No Infobip SMS log rows for ' . $day->format('Y-m-d') . '.');
                $cron->end = Carbon::now();
                $cron->save();
                return 0;
            }

            $filename = 'infobip_sms_logs_' . $day->format('Y-m-d') . '_' . Carbon::now()->timestamp . '.csv';
            $s3Path = 'reports/infobip-sms-logs/' . $filename;
            Storage::disk('s3')->put($s3Path, $csv, 'public');

            $s3Url = config('app.S3_BASE_URL') . '/' . $s3Path;

            Log::info("InfobipSmsExport: {$rowCount} rows uploaded to S3: {$s3Url}");
            $this->info("Exported {$rowCount} rows to {$s3Url}");

            try {
                \Illuminate\Support\Facades\Mail::raw(
                    "Infobip SMS log export for " . $day->format('d M Y') . " (CAT).\n"
                  . "Rows: {$rowCount}\n"
                  . "S3 URL: {$s3Url}\n"
                  . "Filename: {$filename}\n",
                    function ($m) use ($day) {
                        $m->to('kkatolkar@alphadirect.co.bw')
                          ->subject('[Graphite] Infobip SMS Log Export — ' . $day->format('d M Y'));
                    }
                );
            } catch (\Throwable $e) {
                Log::warning('InfobipSmsExport notify email failed: ' . $e->getMessage());
            }

            $cron->end = Carbon::now();
            $cron->save();

            return 0;
        } catch (\Throwable $e) {
            Log::error('InfobipSmsExport error: ' . $e->getMessage());
            $this->error('Error exporting Infobip SMS logs: ' . $e->getMessage());
            $cron->end = Carbon::now();
            $cron->save();
            return 1;
        }
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
