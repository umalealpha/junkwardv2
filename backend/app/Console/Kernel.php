<?php
namespace AlphaDirect\Console;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Log;
use AlphaDirect\Jobs\ActivationCodeJob;
use Illuminate\Support\Facades\Artisan;
use AlphaDirect\Models\CronKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        // Commands\DemoCron::class,
        Commands\AuthFallbackLogin::class,
        Commands\CleanupDuplicateCoverages::class,
        Commands\FixProfessionalIndemnityCoverageId::class,
        Commands\PolicyPaymentCron::class,
        Commands\KycComplianceCheck::class,
        Commands\ReservePaymentBalanceMail::class,
        Commands\MatiUpdateKycDocuments::class,
        Commands\KycComplianceUpdate::class,
        Commands\UpdateCustomerKycCompliance::class,
        Commands\AgentCollectionRate::class,
        Commands\whatsAppForNoDocumentOnPolicy::class,
        Commands\preInspectionVehiclePending::class,
        commands\preInspectionDevicePending::class,
        commands\ProcessUploadedExcelFiles::class,
        Commands\CreateRekycLinksForEligibleCustomers::class,
        Commands\ProcessCustomerDeduplication::class,
        Commands\ValidateCustomerBanking::class,
        Commands\CalculateObdPremium::class,
        Commands\WeeklyAmlScreening::class,
        Commands\ValidateWhatsAppNumbers::class,
        Commands\InvoiceGenerator::class,
        Commands\CronDailyReportCommand::class,
        Commands\DetectWhatsAppAnomalies::class,
        Commands\DiagnoseWordingMerge::class,
        Commands\ReconRealpayExceptions::class,
        Commands\ReconStatementReflectionExceptions::class,
    ];

    /**
     * .57890-
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    public function schedule(Schedule $schedule)
    {
        if(env('APP_STATUS') == 'Production'){
            $commonsHourly = CronKernel::where('status',1)->where('run_type','Hourly')->where('run_on_server','bw_server')->get();
            $commonsDaily = CronKernel::where('status',1)->where('run_type','Daily')->where('run_on_server','bw_server')->get();
            $commonsweekly_sundays = CronKernel::where('status',1)->where('run_type','weekly_sundays')->where('run_on_server','bw_server')->get();
            $commonslastDayOfMonth = CronKernel::where('status',1)->where('run_type','lastDayOfMonth')->where('run_on_server','bw_server')->get();
            // ─── DOUBLE-CHARGE FIX (3/3) ──────────────────────────────
            // Commands that issue payments (DPO debits, RealPay creates,
            // refund jobs) must NEVER overlap. If the cron server retriggers
            // before the previous run finishes, withoutOverlapping(120)
            // makes Laravel's scheduler bail out without re-running.
            // The atomic-claim fix in each command is the primary defence;
            // this is defence-in-depth at the scheduler layer.
            $paymentCronNames = [
                'policy:processDPOPayment',
                'policy:chargeRecurrentToken',
                'policy:chargePendingRecurringToken',
                'dpo:pay',
                'realpay:cancelContracts',
                'policy:dpo-duplicate-transactions',
            ];
            $applyOverlapGuard = function ($event, string $name) use ($paymentCronNames) {
                if (in_array($name, $paymentCronNames, true)) {
                    return $event->withoutOverlapping(120);
                }
                return $event;
            };

            if(count($commonsHourly) > 0){
                 foreach($commonsHourly as  $Hourly){
                     $applyOverlapGuard($schedule->command($Hourly->cron_name)->hourly(), $Hourly->cron_name);
                 }

            }
            if(count($commonsDaily) > 0){
                 foreach($commonsDaily as  $Daily){
                     $applyOverlapGuard($schedule->command($Daily->cron_name)->daily()->at($Daily->run_time), $Daily->cron_name);
                 }

            }
            if(count($commonsweekly_sundays) > 0){
                 foreach($commonsweekly_sundays as  $sundays){
                     $applyOverlapGuard($schedule->command($sundays->cron_name)->weekly()->sundays()->at($sundays->run_time), $sundays->cron_name);
                 }

            }
            if(count($commonslastDayOfMonth) > 0){
                 foreach($commonslastDayOfMonth as  $lastDayOfMonth){
                     $applyOverlapGuard($schedule->command($lastDayOfMonth->cron_name)->lastDayOfMonth($lastDayOfMonth->run_time), $lastDayOfMonth->cron_name);
                 }

            }
        }
        // Daily cron activity summary — runs at 23:55 every day, emails kkatolkar@alphadirect.co.bw
        $schedule->command('cron:daily-report')->dailyAt('23:55');

        // UW approval SLA escalation (CFO 2026-08-27) — daily digest of policy
        // approvals sitting past UW_APPROVAL_SLA_DAYS. Fail-safe: sends NOTHING
        // unless UW_APPROVAL_SLA_RECIPIENTS is set (never blasts the 2,133
        // policy_approved holders). Read-only on policy data.
        $schedule->command('uw:approval-sla --send')->dailyAt('07:30');

        // PDF job processor — picks up queued_long rows (big policies that the
        // inline HTTP path can't render within Cloudflare's 100s timeout) and
        // runs them via artisan with unbounded execution time + 4GB memory.
        // MUST be hardcoded here — the DB-driven scheduler above depends on
        // the `cron_kernels` table which doesn't exist in prod (every query
        // throws silently). Without this line, big-policy V2 Quote PDFs
        // queue forever and COMG2024112441-class cases accumulate.
        // --limit=3 lets one tick drain 3 jobs sequentially (still one process,
        // no parallel memory pressure). withoutOverlapping(5) shortens the lock
        // so a single hung job can't block the queue for a full 10 minutes.
        // Morning surge was stuck at one quote per minute system-wide.
        // Phase 2 (2026-06-10): heartbeat-based stale reset in
        // ProcessPdfJobs (no more "updated_at < 10m" false-kills), short
        // withoutOverlapping window so a stale lock can't pin the queue.
        // withoutOverlapping uses Redis cache lock now (CACHE_DRIVER=redis
        // is set on backend+cron task defs), so a stale lock from a crashed
        // tick can't pin the queue for a full window like the old file-cache
        // lock used to.
        //
        // 2026-06-11 hotfix: reverted everyThirtySeconds() → everyMinute().
        // Laravel 8 (this codebase) doesn't have ->everyThirtySeconds() —
        // that helper was added in Laravel 11. The 30s tick threw
        // BadMethodCallException every scheduler tick on PROD, crashing
        // schedule:run and blocking the entire backend scheduler from
        // firing ANY scheduled tasks. The 60s tick is fine — the
        // controller's per-job inline kick (after the PHP_BINARY fix)
        // is the primary trigger; this scheduler is just the orphan
        // backstop sweeper.
        $schedule->command('pdf:process-pending --limit=3')
                 ->everyMinute()
                 ->withoutOverlapping(2)
                 ->name('pdf-process-pending')
                 ->onOneServer();

        // Claims comment-status priority reminders (flag: claims_comment_status,
        // default OFF). Emails the assignee of any review note whose @mention is
        // still unread past its priority threshold. Hardcoded here (like
        // pdf:process-pending) because the DB-driven scheduler above only runs on
        // PROD and depends on the cron_kernels table. The command self-gates on
        // the flag, so while the feature ships dark this tick is a cheap no-op.
        $schedule->command('claims:mention-reminder-tick')
                 ->everyFiveMinutes()
                 ->withoutOverlapping(10)
                 ->name('claims-mention-reminder-tick')
                 ->onOneServer();

        // GRA-0054 / RealPay webhooks: drain webhook_buffer continuously.
        // The WebhookBuffer middleware captures incoming webhooks (fast 200) but
        // had NO consumer — RealPay installment notifications piled up unprocessed
        // since the 2026-06-04 V2 cut-over (tens of thousands stuck 'pending'),
        // leaving ledgers/transaction-log stale. This sips the buffer in small,
        // idempotent batches every minute (updateInstallment matches by
        // InstalmentReferenceNumber + upserts payments, so replay never
        // double-applies). withoutOverlapping so a slow run never stacks; this
        // both clears the backlog gradually and keeps new webhooks flowing.
        $schedule->command('webhook:process-buffer --batch=100')
                 ->everyMinute()
                 ->withoutOverlapping(2)
                 ->name('webhook-process-buffer')
                 ->onOneServer();

        // Help Desk SLA engine — FULLY UNSCHEDULED since the 2026-07 cutover:
        // Alpha Bridge owns Help Desk (tickets, SLA, and every notification
        // email). Graphite's native module is a frozen read archive, so:
        //   • hd:sla-digest   (daily 08:00 summary)             — retired 07-14
        //   • hd:sla-evaluate (5-min 75%/90%/breach warnings)   — retired 07-14;
        //     archived tickets can no longer be actioned here, so its warnings
        //     were pure noise on top of Bridge's own notifications.
        // The SlaDigest / SlaEvaluate command classes and mailables are kept
        // on disk (runnable manually) in case a historical re-send is needed.

        // Daily cleanup of stale V2 quote-sheet / policy-document PDFs.
        // Keeps the 2 most recent completed jobs per (policy_id, action_id,
        // document_title) and deletes the S3 file + nulls file_name in DB
        // for everything older. Safety floor is 7 days — a freshly-generated
        // file is never deletable by this job. Runs at 03:00 SAST (lowest
        // traffic window per cron-runs data). withoutOverlapping prevents
        // a long sweep from launching twice if a previous run is still going.
        $schedule->command('pdf:cleanup-stale-quote-files')
                 ->dailyAt('03:00')
                 ->withoutOverlapping(120)
                 ->name('pdf-cleanup-stale-quote-files')
                 ->onOneServer();

        // Claims scheduled KPI reports (Claims-Tracker migration, Phase 2).
        // Ticks hourly and fires any claim_report_schedules row due this hour.
        // SEND-GATED: with the `claims_scheduled_reports` flag OFF (default) the
        // command only assembles/renders/logs — it sends no mail. Safe to run
        // everywhere. No-ops when the table is absent. Runs at :05 so it lands
        // just after the top of the configured hour. NOTE: the live cron tick
        // runs from the separate graphite-cron codebase (cron/) — register this
        // command there too before relying on it in Production (see comments
        // above re: the cron container running a different Laravel app).
        $schedule->command('claims:run-report-schedules')
                 ->hourlyAt(5)
                 ->withoutOverlapping(10)
                 ->name('claims-run-report-schedules')
                 ->onOneServer();

        // MotoLink (motolink.app) INBOUND assessment bridge (Claims-Tracker port).
        // Pulls vehicle assessments and mirrors them onto the matching claim
        // (matched on claim_number). OFF / DARK by default: with no
        // MOTOLINK_API_KEY configured the command is a COMPLETE no-op — it makes
        // no outbound call, writes nothing, and never errors. Cadence mirrors the
        // tracker's 15-min default. Hardcoded here (like the two claims ticks
        // above) because the DB-driven scheduler only runs on PROD. NOTE: the
        // live cron tick runs from the separate cron/ twin app — this command is
        // ALSO registered there (cron/app/Console/Commands + Kernel).
        $schedule->command('claims:motolink-sync')
                 ->cron('*/15 * * * *')
                 ->withoutOverlapping(10)
                 ->name('claims-motolink-sync')
                 ->onOneServer();

        // FNOL documentation reminders (flag: claims_fnol, default OFF; ALSO
        // disarmed by default via config('claims_fnol.reminders_armed')). Chases
        // outstanding documents on open FNOLs by email. DOUBLE send-gated: sends
        // NOTHING unless the flag is ON *and* reminders are armed — so while the
        // feature ships dark this daily tick is a cheap no-op (it only counts
        // would-sends). Hardcoded here (like the claims ticks above) because the
        // DB-driven scheduler only runs on PROD. NOTE: the live scheduler runs
        // from the separate cron/ twin app — register this command there too
        // before relying on it in Production.
        $schedule->command('claims:fnol-doc-reminders')
                 ->dailyAt('09:30')
                 ->withoutOverlapping(10)
                 ->name('claims-fnol-doc-reminders')
                 ->onOneServer();

        // Treaty statement clocks — BR-ACC-01 (render within 45 days of the
        // quarter's close) and BR-ACC-02 (reinsurers confirm within 14 of
        // receipt). Reports only: rendering an account sends figures to
        // reinsurers and is somebody's decision, not a scheduler's, so this
        // never moves a statement's status and --open is not passed here.
        //
        // It also reports the case no query over the statements table can see —
        // a quarter that closed with NO statement at all, which cannot be
        // overdue because nothing is looking at it.
        //
        // Daily rather than weekly because BR-ACC-10 charges interest at 110% of
        // prime from the due date, so a deadline missed by a week is a week of
        // interest nobody meant to incur.
        //
        // NOTE: the live scheduler runs from the separate cron/ twin app —
        // register this command there too before relying on it in Production.
        $schedule->command('treaty:statement-clocks')
                 ->dailyAt('07:30')
                 ->withoutOverlapping(10)
                 ->name('treaty-statement-clocks')
                 ->onOneServer();

        // Customer Refund Engine — Omni reconciliation backstop (GRA-0203
        // class: money paid, callback lost — Omni's callback fires ONCE with
        // no retry). Hourly dry-run for visibility; the daily 22:15 sweep
        // commits fixes (retry failed handoffs, self-heal unposted paid
        // refunds — all idempotent). No-ops while the engine tables are empty
        // and skips handoffs while the omni_refunds integration is OFF.
        // NOTE: the live scheduler runs from the cron/ twin app — this command
        // is ALSO registered there (cron/app/Console/Commands + Kernel).
        $schedule->command('refunds:reconcile-omni')
                 ->hourlyAt(20)
                 ->withoutOverlapping(10)
                 ->name('refunds-reconcile-omni-dryrun')
                 ->onOneServer();
        $schedule->command('refunds:reconcile-omni --commit')
                 ->dailyAt('22:15')
                 ->withoutOverlapping(30)
                 ->name('refunds-reconcile-omni-commit')
                 ->onOneServer();

        // RealPay reflection backstop (GRA-0203 root fix). The every-minute
        // webhook drain keeps ~everything current; this catches the residual
        // drip — RealPay successes whose webhook was never delivered / only
        // half-applied (installment landed 'S' but the payment write did not).
        // Windowed to the last 14 days so it never table-scans the 6.6M-row
        // installment table; idempotent (upsert by referenceNumber); writes with
        // events suppressed (no agent commission / cashback / customer message).
        // NOTE: the live scheduler is the cron/ twin — this command is ALSO
        // registered there (cron/app/Console/Commands + Kernel).
        // DISABLED 2026-08-03 — runaway pile-up incident (master 99% CPU).
        // withoutOverlapping(30) is a 30-MINUTE lock, but this query runs for DAYS
        // (un-indexed NOT EXISTS over ~6.6M payment_transactions). The lock expired
        // long before each run finished, so every 6h a fresh copy fired → ~26 stacked
        // → master pegged. Re-enable ONLY after: (1) the NOT-EXISTS join is indexed so
        // the query finishes in seconds, and (2) the overlap guard exceeds real runtime
        // (and the cron twin drops runInBackground(), which releases the lock at dispatch).
        // $schedule->command('realpay:recover-missing-tx --commit --days=14')
        //          ->everySixHours()
        //          ->withoutOverlapping(30)
        //          ->name('realpay-recover-missing-tx-backstop')
        //          ->onOneServer();

        // Reflection reconciliation — REPORT ONLY, deliberately not --commit.
        // Answers "are there RealPay debits with no Graphite payment record?"
        // once a day and leaves the answer in the cron log; a human decides
        // whether to repair (`realpay:reconcile-reflection --commit`).
        //
        // Cheap where the disabled backstop above was not: source=exceptions
        // reads realpay_reflection_exceptions, which is indexed on
        // (resolved_at, created_at) and holds only open failures — typically a
        // handful of rows. It does NOT run the un-indexed NOT EXISTS scan over
        // payment_transactions that caused the 2026-08-03 pile-up (that is
        // source=installments, left for manual runs until the join is indexed).
        $schedule->command('realpay:reconcile-reflection --days=7 --source=exceptions')
                 ->dailyAt('06:15')
                 ->withoutOverlapping(10)
                 ->name('realpay-reflection-report')
                 ->onOneServer();

        // Rule A (CFO 10 Aug 2026): a cancelled/deactivated policy must stop
        // debiting the customer. Reconciling control — every hour, find
        // contracts that will still debit (future 'A' instalments) on dead
        // policies and cancel them on RealPay's side. Catches every status
        // writer, including query-builder bulk fixes that bypass
        // PolicyObserver. Contracts that collected in the last 90 days are
        // NEVER touched — they are reported as Rule B reactivation candidates.
        //
        // REALPAY_MANDATE_STOP_APPLY=true arms the RealPay-side cancellation;
        // until then every scheduled run is report-only (CSV in
        // storage/app/realpay-mandate-stop/). --limit=100 with a 4-calls/sec
        // throttle keeps a full apply run under ~2 minutes, well inside the
        // 50-minute overlap lock (lesson of the 2026-08-03 backstop pile-up:
        // the lock must exceed the real runtime, not hope).
        $schedule->command('realpay:stop-dead-mandates --limit=100'
                . (config('realpay.mandate_stop_apply') ? ' --apply' : ''))
                 ->hourly()
                 ->withoutOverlapping(50)
                 ->name('realpay-stop-dead-mandates')
                 ->onOneServer();

        // Finish the RealPay cancellations that failed at the moment their
        // policy was cancelled. RealPayPolicyCancellationService runs inline
        // with every cancellation journey and deliberately does not block the
        // policy cancellation when RealPay is unreachable — it leaves
        // realpay_cancel_requests.cancel_status = 2 and logs under
        // '[REALPAY POLICY CANCEL]'. This is what comes back for those, so a
        // RealPay outage costs a delay rather than a manual cancellation.
        //
        // --requested-only keeps the unattended run to policies where a
        // cancellation was explicitly requested and failed. The wider signals
        // (a live contract row, a queued instalment) belong to
        // realpay:stop-dead-mandates above, whose Rule B carve-out protects a
        // policy cancelled in error that is still collecting; this command has
        // no such carve-out and must not sweep that population on its own.
        // REALPAY_CANCEL_RETRY_APPLY=false disarms it back to report-only.
        $schedule->command('realpay:retry-policy-cancellations --requested-only --limit=100'
                . (config('realpay.cancel_with_policy.retry_apply', true) ? ' --apply' : ''))
                 ->hourly()
                 ->withoutOverlapping(50)
                 ->name('realpay-retry-policy-cancellations')
                 ->onOneServer();

        // Policies whose RealPay debit day no longer matches the billing date
        // configured in Graphite. RealPayBillingDateSynchroniser stops new
        // divergence at the edit; this finds the backlog left by every edit
        // made before it existed (MIS2026213635 and its siblings).
        //
        // Report-only until REALPAY_BILLING_DATE_AUDIT_FIX=true — the CSV in
        // storage/app/realpay-billing-date-audit/ should be reviewed with
        // Finance before a batch of live debit schedules is moved. --fix only
        // ever queues an instalment move; it never cancels or creates a
        // contract, so it cannot produce a duplicate debit order.
        $schedule->command('realpay:audit-billing-dates --limit=100'
                . (config('realpay.billing_date_sync.audit_fix') ? ' --fix' : ''))
                 ->dailyAt('05:30')
                 ->withoutOverlapping(50)
                 ->name('realpay-audit-billing-dates')
                 ->onOneServer();

        // Failsafe: if ANY pdf job sits in queued/queued_long for >30 min the
        // cron loop itself is broken. Log a warning so someone notices before
        // the next user complains.
        $schedule->call(function () {
            $stuck = \Illuminate\Support\Facades\DB::connection('mysql_system')->table('v2_pdf_jobs')
                ->whereIn('status', ['queued_long', 'queued'])
                ->where('created_at', '<', now()->subMinutes(30))
                ->count();
            if ($stuck > 0) {
                Log::warning("PDF failsafe: {$stuck} v2_pdf_jobs row(s) have been queued >30min — cron loop may be broken");
            }
        })->everyFiveMinutes()->name('pdf-stuck-check');

        // Prune timestamped PDFs from docroot (legacy PDFMerger / Snappy sinks in
        // Admin/PolicyController.php and PDFController.php save to public_path('<time>.pdf')).
        // Without this they accumulate on the task's ephemeral disk forever and eventually
        // fill it. Bounded to 24h retention — plenty for a user who just kicked off a merge
        // and hasn't downloaded it yet.
        $schedule->call(function () {
            $cutoff = time() - 86400;
            foreach (glob(public_path('*.pdf')) ?: [] as $f) {
                if (@filemtime($f) < $cutoff) @unlink($f);
            }
        })->hourly()->name('prune-docroot-pdfs')->withoutOverlapping();

        /*Active CRONS disabled for Development server*/
        $schedule->command('excel:process-uploaded-files')->daily()->at('12:43');
        
        // OBD Premium Calculation - runs on the 1st day of every month
       // $schedule->command('obd:calculate-premium')->monthlyOn(1, '02:00');
        
        if(env('APP_STATUS') == 'Production'){
//            $schedule->command('gfspolicyledger:cron')->daily()->at('22:00');
            $schedule->command('dailytransectioncsv:cron')->daily()->at('07:05');

            // DomCom batch renewals (Monthly / Quarterly / Annual) are
            // scheduled via the cron_kernel DB table, read by the graphite-cron
            // container's Kernel (cron/app/Console/Kernel.php). The historical
            // "cron_kernels table doesn't exist in prod" caveat no longer holds
            // — the Cron Portal at /system/cron-portal proves the table exists
            // and 78+ jobs run from it. Keeping hardcoded entries here made the
            // backend scheduler fire the same command at a DIFFERENT time
            // (e.g. cron_kernel=06:00 vs backend=07:15), so any portal time
            // change was silently overridden by the second daily fire.
            //
            // Source of truth = cron_kernel row (run_on_server='cron_server').
            // Change the time via Cron Portal → row → run_time, takes effect
            // on the next minute's schedule:run tick in the cron container.
            
            // $schedule->command('fetchdummyrealpaydata:cron')->daily()->at('19:59');
            // $schedule->command('deleteDebitOrdersRealpayData:cron')->daily()->at('13:30');
            #$schedule->command('whatsAppForWrongCustCoverNote:cron')->daily()->at('21:10');
            // $schedule->command('CreateDPOScheduledTransactions:cron')->daily()->at('10:15');
            // $schedule->command('CreateNewProdContractWithExistingDetails:cron')->daily()->at('09:30');

            // $schedule->command('processrealpaypayment:cron')->hourly();
            // $schedule->command('addbankbranches:cron')->weekly()->sundays()->at('15:00');
            // $schedule->command('quoteoperations:cron')->hourly();
            // $schedule->command('PolicyLedgerDaily:cron')->dailyAt('23:30');
            // $schedule->command('realpayfailedtransactions:cron')->dailyAt('23:50');
            // $schedule->command('generatepolicydocument:cron')->hourly(); #->between('19:00', '04:00');
            //$schedule->command('quoteByToday:cron')->dailyAt('19:10');
            //$schedule->command('policiesByToday:cron')->dailyAt('19:32');
            //$schedule->command('claimstoday:cron')->dailyAt('19:45');
            // $schedule->command('UpdateOrangeTransactions:cron')->weekly()->sundays()->at('13:00');
            // $schedule->command('updateorangereferencenumbers:cron')->dailyAt('20:30');
            // $schedule->command('dailycancelledpolicies:cron')->dailyAt('19:36');
            // $schedule->command('dailykycreport:cron')->dailyAt('19:38')->appendOutputTo(storage_path('logs/cronLogs.log'));
            // $schedule->command('policyrenewaloperations:cron')->dailyAt('20:01');
            // $schedule->command('reraterenewpolicy:cron')->dailyAt('21:10');
            // $schedule->command('updatepremiumbillingdata:cron')->dailyAt('22:30');
            // $schedule->command('setcustomercategory:cron')->dailyAt('20:15');
            // $schedule->command('matiUpdateKycDocuments:cron')->dailyAt('23:30');
            // $schedule->command('sendbeneficiaryupdatesms:cron')->weekly()->sundays()->at('15:00');
            // $schedule->command('dailykycforactivatedpolicyreport:cron')->dailyAt('20:30');
            // $schedule->command('policy:activatedToday')->dailyAt('23:00');
            #$schedule->command('updatecustomerkycstatus:cron')->dailyAt('22:05');

            // $schedule->command('checkcustomerbankingdata:cron')->dailyAt('21:08');
            // $schedule->command('checkcustomerkycdata:cron')->dailyAt('23:10');
            // $schedule->command('policyRenewUpdateData:cron')->dailyAt('20:00');

            // $schedule->command('getexpiredcards:cron')->dailyAt('22:00');
            // $schedule->command('getlowbalancetransactions:cron')->dailyAt('22:02');
            // $schedule->command('agentcollectionrate:cron')->lastDayOfMonth('23:00');

            // //dpo payment
            // $schedule->command('policy:generateSubscriptionToken')->daily()->at('09:00');
            // $schedule->command('policy:chargeRecurrentToken')->daily()->at('09:15');
            // $schedule->command('policy:generateSubscriptionToken')->daily()->at('23:00');
            // $schedule->command('policy:chargeRecurrentToken')->daily()->at('23:15');
            // $schedule->command('dpo:pay')->daily()->at('23:15');
            $schedule->command('processrealpaypayment:cron')->hourly();
            $schedule->command('demobw:cron')->daily()->at('16:15')->withoutOverlapping();
           // $schedule->command('clearApplicationCache:cron')->daily()->at('07:29')->withoutOverlapping();
            //$schedule->command('clearApplicationCache:cron')->daily()->at('20:59')->withoutOverlapping();
             //$schedule->command('policy:processDPOpayment')->daily()->at('01:30')->withoutOverlapping();
             //$schedule->command('policy:processDPOpayment')->everySixHours($minutes = 10);
            
              //$schedule->command('policy:processDPOpayment')->daily()->at('02:38');
             
            //  $schedule->command('policy:processDPOpayment')->daily()->at('18:38')->withoutOverlapping();
            //  $schedule->command('AddReconsilationTransactionIntoTxLog:cron')->daily()->at('15:45')->withoutOverlapping();
            //    $schedule->command('OrangeScheduledTransactionPayment:cron')->dailyAt('18:20');
            $schedule->command('getRealpayFailedTxmonthly:cron')->daily()->at('21:30');

        }else{
            //dpo payment
            // $schedule->command('dpo:pay')->daily()->at('23:15');
            // #$schedule->command('policy:activatedToday')->dailyAt('11:30');
          #  $schedule->command('policy:processDPOpayment')->daily()->at('11:30');
             //$schedule->command('OrangeScheduledTransactionPayment:cron')->dailyAt('18:20');
            // $schedule->command('processrealpaypayment:cron')->everyMinute();
        }

        // $schedule->command('clearApplicationCache:cron')->daily()->at('07:29');
        // $schedule->command('clearApplicationCache:cron')->daily()->at('20:59');
         $schedule->command('queue:work')->daily()->at('08:20');
        //  $schedule->command('policy:processDPOpayment')->daily()->at('07:30');
        //  $schedule->command('policy:processDPOpayment')->daily()->at('21:00');
        /*Active CRONS disabled*/


        //$schedule->command('kyccomplianceupdate:cron')->dailyAt('23:30');
        //$schedule->command('cancelrealpaycontracts:cron')->everyFiveMinutes();
        //$schedule->command('dumpsummaryageanalysisreport:cron')->dailyAt("3:30");
        // $schedule->command('kyccompliancecheck:cron')->everyMinute();

//        $schedule->command('cancelrealpaycontracts:cron')->everyFiveMinutes();
//        $schedule->command('sendsuccesspaymentsms:cron')->dailyAt("09:30");
//        $schedule->command('updaterealpaycontractsrerate:cron')->everyFiveMinutes();
//        $schedule->command('fetchpendingpreinspection:cron')->dailyAt("9:30");
//        $schedule->command('fetchpendingactivationpolicies:cron')->dailyAt("9:30");
//        $schedule->command('fetchpendingdevicepreinspection:cron')->dailyAt("9:30");
//        $schedule->command('fetchpendingkyccustomers:cron')->dailyAt("9:30");
//        $schedule->command('sendsmsemailpolicypaymentfailed:cron')->everyMinute();
//        $schedule->command('sendsmsemailpolicypendingactivation:cron')->dailyAt('17:30');
//        $schedule->command('sendsmsemailkycpending:cron')->dailyAt('20:30');
         # $schedule->command('sendsmsemailpolicycancelled:cron')->dailyAt('17:30');
//        $schedule->command('sendsmsemaildevicepreinspectionprending:cron')->dailyAt('20:30');
//        $schedule->command('sendsmsemailvehiclepreinspectionprending:cron')->dailyAt('20:30');
//        $schedule->command('fetchpendingactivationpolicies:cron')->everyMinute();
        //   $schedule->command('cancelpoliciescron:cron')->dailyAt('20:30');

//        Promotion Purpose CRON
        //$schedule->command('getcustomerswithactivequotes:cron')->everyMinute();
        //$schedule->command('ratewithexistingquoteinfo:cron')->everyMinute();
        //$schedule->command('sendpromotionalemailsms:cron')->everyFiveMinutes();

        //$schedule->command('motorcompcustomerpolicies:cron')->everyFiveMinutes();

        //$schedule->command('updatequoteexpirydate:cron')->everyFiveMinutes();

        #$schedule->command('sendVatMemo:cron')->dailyAt('16:30');
        #$schedule->command('updatePremiumRealpay:cron')->dailyAt('16:30');

        //$schedule->command('payment:cron')->yearly();
        //$schedule->command('cancelrealpaycontract:cron')->everyMinute();
        //$schedule->command('policyledger:cron')->dailyAt("18:00");
        //$schedule->command('paymentremindersms:cron')->dailyAt("12:30");
        //$schedule->command('paymentstatusdumpdata:cron')->dailyAt("08:42");
        //$schedule->command('sendpaymentsms:cron')->everyMinute();
        $schedule->command('whatsAppForNoDocumentOnPolicy:cron')->daily()->at('08:05');

        $schedule->command('preInspectionVehiclePending:cron')->daily()->at('09:05');
        $schedule->command('preInspectionDevicePending:cron')->daily()->at('10:05');
        
        // Customer deduplication processing - runs daily at 2:00 AM
        //$schedule->command('customers:process-deduplication')->daily()->at('02:00');
        
        // Banking validation - runs daily at 3:00 AM
        //$schedule->command('banking:validate')->daily()->at('03:00');
        
        // Re-KYC link creation for eligible customers - runs daily at 11:00 AM
        //$schedule->command('rekyc:create-links-for-eligible-customers')->daily()->at('11:00');
        // $schedule->command('invoiceMonthly:domcom')->daily()->at('18:00');

        // $schedule->command('DomComMonthlyAutoRenew:cron') 
        // ->daily()->at('19:50'); // Runs at 3:30 PM
        // $schedule->command('DomComQuaterlyAutoRenew:cron') 
        // ->daily()
        // ->at('19:55'); // Runs at 3:30 PM
        // $schedule->command('invoiceMonthly:domcom')->daily()->at('11:05');

        // ── WhatsApp AI — anomaly detection + daily briefs ──────────────────
        // NOTE: these are NOT scheduled here any more.
        //
        // The wa:detect-anomalies command lives in this Laravel app (backend),
        // but the Laravel scheduler runs in the graphite-cron container which is
        // built from a *different* Laravel codebase (deployment-package/cron/).
        // That codebase doesn't register this command, so the schedule entries
        // here never fired — they were vestigial.
        //
        // Anomaly scheduling now lives in AWS EventBridge, targeting ECS RunTask
        // on graphite-backend (where the command + WHATSAPP_TOKEN env both live):
        //   - graphite-wa-anomaly-scan       cron(0/30 * * * ? *)
        //   - graphite-wa-morning-brief      cron(0 6 * * ? *)
        //   - graphite-wa-evening-summary    cron(30 15 * * ? *)
        // IAM role: graphite-eventbridge-ecs-runtask
        // Created 2026-04-28 after WhatsApp anomaly alerts went silent.

        // ── Phase 12 operational crons ──────────────────────────────────────
        // Commission calculation — daily at 22:00 (after policies have been processed)
        $schedule->command('commission:calculate')->dailyAt('22:00')->withoutOverlapping();

        // Renewal notification pipeline — daily at 07:00 UTC
        $schedule->command('renewal:notify')->dailyAt('07:00')->withoutOverlapping();

        // Notification cleanup — Sundays at 03:00 (prune read notifications > 90 days)
        $schedule->command('notification:cleanup')->weekly()->sundays()->at('03:00')->withoutOverlapping();

        // Professional Indemnity / Medical Malpractice coverage_id + product/plan
        // corrections are ONE-TIME data fixes, NOT a recurring schedule.
        //
        // They were briefly wired to run daily (06:45 / 06:50), which re-mutated
        // PI/MedMal product_id / plan_id / coverage_id every morning inside the
        // sensitive nightly billing window — same data-mutation risk class as the
        // Sonali incident. Disabled so they never auto-fire on Production.
        //
        // The artisan commands remain registered. Run manually (once, verified)
        // if the data ever drifts again:
        //   php artisan policy:fix-pi-coverage-id   (PI  -> coverage_id 1715, product 20 / plan 35)
        //   php artisan policy:fix-mm-coverage-id   (MM  -> coverage_id 1714, product 20 / plan 35)
        // $schedule->command('policy:fix-pi-coverage-id')->dailyAt('06:45')->withoutOverlapping();
        // $schedule->command('policy:fix-mm-coverage-id')->dailyAt('06:50')->withoutOverlapping();

        // ── Financial reports + dashboard cache ─────────────────────────────
        // V1 had ~14 report commands sitting orphan (no schedule). Re-wired
        // here with explicit times — staggered across early morning so any
        // single failure doesn't cascade. All output to ops via existing
        // mailer (recipients defined in each command's email block).
        //
        // finance:compute-dashboard populates finance_dashboard_cache which
        // backs the FinanceController dashboard. Without it the dashboard
        // shows empty data — was the most-asked "why is finance dashboard
        // empty" question.
        $schedule->command('finance:compute-dashboard')->dailyAt('05:00')->withoutOverlapping();

        // Daily payment + policy reports (06:00 - 08:00 window, after V1's
        // overnight batches but before business hours).
        $schedule->command('dailyfailedtrxnreport:cron')->dailyAt('07:30');
        $schedule->command('expiredpolicyPaymentReport:cron')->dailyAt('07:45');
        $schedule->command('customer:banking-report')->dailyAt('08:00');

        // Weekly + monthly + yearly summaries.
        $schedule->command('policy:comprehensive-payment-report')->weekly()->mondays()->at('06:30');
        $schedule->command('getMISReport')->weekly()->mondays()->at('06:00');
        $schedule->command('allpolicyreport:cron')->weekly()->mondays()->at('06:15');
        $schedule->command('generateMonthlySalesReport:cron')->monthlyOn(1, '06:00');
        $schedule->command('generateYearlyInvoiceCountReport:cron')->yearlyOn(1, 1, '06:30');

        // Reconciliation Exceptions — DOMG/COMG policies vs RealPay mandates.
        // Read-only; writes the week's exceptions into the Exceptions module
        // (recon_exception_*) for Finance to review + comment in /finance/exceptions.
        // Weekly, Mondays 04:00. Idempotent per run_date (won't overwrite a closed run).
        $schedule->command('recon:realpay-exceptions')->weekly()->mondays()->at('04:00')->withoutOverlapping();

        // Reconciliation Exceptions — payment→ledger→statement REFLECTION tie-out.
        // Same module as recon:realpay-exceptions (recon_exception_*), sibling type.
        // Read-only on operational tables; writes a fresh dated run for Finance in
        // /finance/exceptions. Idempotent per (source, run_date) — a weekly run
        // creates a NEW dated snapshot and never overwrites a prior/closed run, so
        // it can't wipe the run Finance is actively working. min-gap default 1
        // (positive gaps only = collected-but-not-reflected). Staggered 30 min
        // after the realpay sweep to avoid overlapping on the same tables.
        $schedule->command('recon:statement-reflection-exceptions')->weekly()->mondays()->at('04:30')->withoutOverlapping();

        // KYC reports — V1 had these commented out for unclear reason. Re-
        // enabled because compliance specifically asked for daily KYC posture.
        // dailykycreport:cron is DB-managed in production via the cron_kernel
        // table (see the run_type='Daily' loop above), which schedules it at
        // its own run_time. Hardcoding it here too would double-schedule it on
        // prod (this line runs outside the APP_STATUS guard). To change WHEN it
        // runs, edit the cron_kernel row's run_time — NOT this file.
        // Day-end run required: the KYC procedures filter ck.created_at =
        // CURDATE(), so a morning run reports ~0 rows and emails an empty PDF.
        // $schedule->command('dailykycreport:cron')->dailyAt('22:00');
        $schedule->command('dailykycforactivatedpolicyreport:cron')->dailyAt('07:15');

        // Note: adiduplicatepolicy:cron is intentionally NOT scheduled here —
        // duplicate-policy detection is now handled by the WhatsApp anomaly
        // engine (wa:detect-anomalies) with smarter dedupe (motor by plate,
        // cellphone by IMEI). Running both would double-alert ops.
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
