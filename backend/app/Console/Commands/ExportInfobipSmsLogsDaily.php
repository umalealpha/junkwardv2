<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use AlphaDirect\Models\CronStatus;
use AlphaDirect\Services\InfobipSmsExportService;
use Carbon\Carbon;

/**
 * GRA-0155 — Scheduled daily export of Infobip SMS delivery logs.
 *
 * Ticket intent: "integrate the Infobip SMS channel records such that they are
 * stored daily in company databases to avoid archived data retrieval issues and
 * fees". ("OMNI" in the ticket title is NOT an external system — clarified as
 * shorthand for our own storage + a self-service download for authorized users.
 * Destination is S3; the download side lives in SmsLogExportController.)
 *
 * This command exports the PREVIOUS day's Infobip SMS delivery records to a CSV
 * on the company S3 archive (the `s3` disk — same sink used by
 * dailytransectioncsv:cron and customer:banking-report). Once the CSV lives in
 * our own bucket, we no longer pay Infobip's archived-log retrieval fees.
 *
 * The actual CSV-build + S3-write is delegated to InfobipSmsExportService so the
 * nightly cron and the on-demand date-range download share one code path.
 *
 * Data source: `sms_infobip_log` only (authoritative Infobip delivery/status
 * record). We deliberately do NOT join `sms_email_log`: its FK
 * `sms_infobip_log.sms_email_log_id` is NULL on every row (SendSmsFired.php
 * save-order bug) and it's an unindexed giant table that would time out the
 * nightly slice. See InfobipSmsExportService.
 *
 * Pattern copied from: DailyTransectionCsv.php + CustomerBankingReportCron.php.
 * Must be mirrored into cron/ (the container that actually runs prod crons) and
 * scheduled via the cron_kernel table (run_on_server='cron_server', run_type
 * 'Daily'). See report notes.
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

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(InfobipSmsExportService $exporter)
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

            $result = $exporter->exportRange($day, $day);

            if ($result['rows'] === 0) {
                Log::info('InfobipSmsExport: no SMS log rows for the period — nothing to export.');
                $this->info('No Infobip SMS log rows for ' . $day->format('Y-m-d') . '.');
                $cron->end = Carbon::now();
                $cron->save();
                return 0;
            }

            $s3Url = config('app.S3_BASE_URL') . '/' . $result['path'];
            Log::info("InfobipSmsExport: {$result['rows']} rows uploaded to S3: {$s3Url}");
            $this->info("Exported {$result['rows']} rows to {$s3Url}");

            // Ops notification — same single-inbox convention as
            // CustomerBankingReportCron (kkatolkar@alphadirect.co.bw).
            try {
                \Illuminate\Support\Facades\Mail::raw(
                    "Infobip SMS log export for " . $day->format('d M Y') . " (CAT).\n"
                  . "Rows: {$result['rows']}\n"
                  . "S3 URL: {$s3Url}\n"
                  . "Filename: {$result['filename']}\n",
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
}
