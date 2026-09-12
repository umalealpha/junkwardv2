<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Log;
use AlphaDirect\Models\CronKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Scheduler timezone — interprets all ->at('HH:MM') and ->dailyAt() in
     * this timezone, regardless of the PHP app.timezone or DB timezone.
     *
     * Why we override per-scheduler instead of global app.timezone:
     *   - DB stays consistently in UTC (every storage timestamp is UTC,
     *     no conversion drift, audit-clean across regions)
     *   - Carbon::now() and Eloquent timestamps continue writing UTC
     *   - ONLY the scheduler reads local time so portal `run_time = 15:45`
     *     means 15:45 in the country we serve (Botswana for now)
     *
     * Future regional expansion:
     *   When we deploy to another African country, change SCHEDULE_TIMEZONE
     *   env var on that region's cron task (e.g. 'Africa/Nairobi' for Kenya).
     *   Code stays unchanged; only env differs per region.
     *
     * Why not global app.timezone:
     *   Changing app.timezone to Africa/Gaborone would make Carbon::now()
     *   return BW time and Eloquent would store BW timestamps to DB. That
     *   breaks the "UTC in DB always" invariant the team wants.
     */
    protected function scheduleTimezone(): string
    {
        return env('SCHEDULE_TIMEZONE', 'Africa/Gaborone');
    }

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // $schedule->command('inspire')->hourly();
        // Webhook buffer drainer — runs every minute, replays buffered RealPay/DPO/N-Genius
        // webhooks through their real handlers. The WebhookBuffer middleware is deployed and
        // unconditionally buffers incoming webhooks into `webhook_buffer`; without this drainer
        // they pile up unprocessed. That was the root cause of GRA-0203: new RealPay policies'
        // transaction logs stayed empty because collection webhooks were buffered but never
        // applied, leaving installments stuck at 'A'. runInBackground so the every-minute drain
        // launches as its own subprocess and never blocks/starves the schedule:run pass;
        // withoutOverlapping so a slow drain never doubles up on itself.
        // RE-ENABLED 2026-08-28 (GRA-0203): the CRON scheduler is reliable (ran every minute
        // without stalling), whereas the backend `webhook:process-buffer` scheduler stalled twice
        // (27 + 28 Aug) and let the buffer back up while it was the sole consumer. The cron apply
        // bug that #1989 disabled around is fixed alongside this re-enable — RealPayController::
        // updateInstallment now sets $paymentData['policy_id'] and updatePaymentTransactions
        // tolerates a missing key, so this drainer posts SUCCESSFUL collections correctly instead
        // of 401-dropping them. Kept BESIDE the backend drainer for redundancy: both apply
        // correctly, both idempotent by InstalmentReferenceNumber, and #1982's return-value guard
        // still parks any remaining bad apply as a visible 'failed' (never a silent 'done').
        $schedule->command('webhook:process --source=all --batch=50 --pause=200')
            ->everyMinute()
            ->runInBackground()
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/webhook-process.log'));

        // Customer Refund Engine — Omni reconciliation backstop (thin cron
        // twin of backend refunds:reconcile-omni; see that class docblock).
        // Hourly monitor (dry-run, logs stuck states); nightly --commit
        // re-triggers the backend's idempotent paid path for paid-but-unposted
        // refunds. No-ops until the refund_requests table exists. runInBackground
        // per the GRA-0203 dispatch-reliability fix — never block the loop.
        $schedule->command('refunds:reconcile-omni')
            ->hourlyAt(20)
            ->runInBackground()
            ->withoutOverlapping(10)
            ->appendOutputTo(storage_path('logs/cronLogs.log'));
        $schedule->command('refunds:reconcile-omni --commit')
            ->dailyAt('22:15')
            ->runInBackground()
            ->withoutOverlapping(30)
            ->appendOutputTo(storage_path('logs/cronLogs.log'));

        // RealPay reflection backstop (GRA-0203 root fix). Backstop for the
        // residual drip the every-minute webhook drain can't catch (RealPay
        // success whose webhook was never delivered / only half-applied —
        // installment 'S' but no payment row). Windowed to 14 days so it never
        // scans the full 6.6M installment table; idempotent (upsert by
        // referenceNumber); events suppressed (no commission/cashback/customer
        // message). runInBackground per the GRA-0203 dispatch-reliability fix —
        // never block the schedule:run loop.
        // DISABLED 2026-08-03 — runaway pile-up incident (master 99% CPU). This is the
        // LIVE scheduler twin, so this is the copy that actually fired. runInBackground()
        // releases the withoutOverlapping(30) lock at dispatch (not when the days-long
        // query finishes), so overlap protection never held → every 6h a fresh copy
        // fired → ~26 stacked. Re-enable ONLY after the query is indexed to finish fast
        // AND the overlap guard is fixed (drop runInBackground or use a real lock).
        // $schedule->command('realpay:recover-missing-tx --commit --days=14')
        //     ->everySixHours()
        //     ->runInBackground()
        //     ->withoutOverlapping(30)
        //     ->appendOutputTo(storage_path('logs/cronLogs.log'));

        // Tag every log line emitted by a scheduled command with {"cron":"<name>"}.
        // Pairs with the Cron Logs admin page substring filter — without this
        // tag, only DomComMonthlyAutoRenew (which sets context inside its own
        // handle()) shows up when filtered by cron name.
        $tagCron = static function ($event, string $name) {
            return $event->before(function () use ($name) {
                Log::withContext(['cron' => $name]);
            });
        };

        // Gate the cron_kernel loop so prod AND staging fire the DB-driven schedule.
        //   - Production: APP_STATUS=Production (capital P on the live task def)
        //   - Staging:    APP_ENV=staging
        // strcasecmp is used because APP_STATUS has historically been written as
        // both "Production" and "production" depending on who edited the task
        // definition — a single typo caused every scheduled DB-driven cron to
        // silently stop firing on 2026-06-06. Belt-and-braces: ALSO check
        // app()->environment() which doesn't depend on APP_STATUS at all.
        // app()->environment() is preferred over env('APP_ENV') because env()
        // returns null at runtime once config:cache has run.
        $isProd    = strcasecmp((string) env('APP_STATUS', ''), 'production') === 0;
        $isStaging = strcasecmp((string) env('APP_STATUS', ''), 'staging') === 0;
        if($isProd || $isStaging || app()->environment(['production', 'staging'])){
            $commonsHourly = CronKernel::where('status',1)->where('run_type','Hourly')->whereIn("run_on_server", ["cron_server", null])->get();
            $commonsDaily = CronKernel::where('status',1)->where('run_type','Daily')->whereIn("run_on_server", ["cron_server", null])->get();
            $commonsweekly_sundays = CronKernel::where('status',1)->where('run_type','weekly_sundays')->whereIn("run_on_server", ["cron_server", null])->get();
            $commonslastDayOfMonth = CronKernel::where('status',1)->where('run_type','lastDayOfMonth')->whereIn("run_on_server", ["cron_server", null])->get();
            if(count($commonsHourly) > 0){
                 foreach($commonsHourly as  $Hourly){
                     $tagCron($schedule->command($Hourly->cron_name)->hourly()->runInBackground(), $Hourly->cron_name);
                 }

            }
            if(count($commonsDaily) > 0){
                 foreach($commonsDaily as  $Daily){
                     // withoutOverlapping(120) — releases the lock after 120
                     // minutes max. The bare withoutOverlapping() used a
                     // 24-hour default; if a daily run crashed mid-flight the
                     // lock stayed stuck and every subsequent day's invocation
                     // silently skipped. DomComMonthlyAutoRenew stopped firing
                     // on 25 Mar 2026 for exactly this reason. 120 min is
                     // plenty of headroom for the slowest daily batch
                     // (DomComMonthlyAutoRenew loops over thousands of
                     // policies); past that, treat the prior run as dead
                     // and let the next day's run go.
                     // runInBackground(): dispatch each due command as its own
                     // subprocess so schedule:run returns immediately. WITHOUT it,
                     // schedule:run runs every due command in the FOREGROUND in
                     // series, so ONE hanging command (slow mail/S3/payment gateway
                     // with no timeout) freezes the whole pass and starves every
                     // cron in the ~15-min window behind it (see supervisord.conf).
                     // That was the root cause of sendpolicydocument (20:20),
                     // policyRenewUpdateData (20:23), updateorangereferencenumbers
                     // (20:30) and LedgerPaymentTransDomCom (03:30) silently dying.
                     $tagCron($schedule->command($Daily->cron_name)->daily()->at($Daily->run_time)->runInBackground()->withoutOverlapping(120), $Daily->cron_name);
                 }

            }
            if(count($commonsweekly_sundays) > 0){
                 foreach($commonsweekly_sundays as  $sundays){
                     $tagCron($schedule->command($sundays->cron_name)->weekly()->sundays()->at($sundays->run_time)->runInBackground(), $sundays->cron_name);
                 }

            }
            if(count($commonslastDayOfMonth) > 0){
                 foreach($commonslastDayOfMonth as  $lastDayOfMonth){
                     $tagCron($schedule->command($lastDayOfMonth->cron_name)->lastDayOfMonth($lastDayOfMonth->run_time)->runInBackground(), $lastDayOfMonth->cron_name);
                 }

            }
/*
             //  hourly start//
            $schedule->command('generatepolicydocument:cron')->hourly(); #->between('19:00', '04:00');
            $schedule->command('processrealpaypayment:cron')->hourly();
            $schedule->command('quoteoperations:cron')->hourly();


            



            // daily  start///
            //Time   start  00:00 to 05:59    //

            $schedule->command('policyExpiredToday:cron')->daily()->at('03:00');
            $schedule->command('withoutkycpolicyreport:cron')->daily()->at('03:13');
            $schedule->command('vehicleduplicatepolicy:cron')->daily()->at('03:26');
            $schedule->command('expiredpolicyPaymentReport:cron')->daily()->at('04:00');

            //*******************Time   End  00:00 to 05:59    //



            //Time   start  06:00 to 11:59    //
            $schedule->command('DPODuplicateTransactions:cron')->daily()->at('07:00');
            $schedule->command('termactivedeactive:cron')->dailyAt('07:40');

            // FNOL documentation reminders (twin of backend/app/Console/Kernel.php).
            // The live scheduler runs from THIS cron app, so the command must be
            // registered here to fire in Production. Double send-gated (claims_fnol
            // flag ON + claims_fnol.reminders_armed) so it is a cheap no-op —
            // counting would-sends only — until Claims explicitly arms it. Batch
            // cap (claims_fnol.reminder_batch_limit) keeps the first armed run
            // controlled. runInBackground so it never blocks the serial tick.
            $schedule->command('claims:fnol-doc-reminders')
                     ->dailyAt('09:30')
                     ->withoutOverlapping(10)
                     ->name('claims-fnol-doc-reminders')
                     ->runInBackground();

            // Treaty statement clocks — BR-ACC-01 (accounts rendered within 45
            // days of the quarter's close) and BR-ACC-02 (Reinsurers confirm
            // within 14 of receipt).
            //
            // THIS IS THE COPY THAT ACTUALLY RUNS. The same command is
            // registered in backend/app/Console/Kernel.php, but the live
            // scheduler runs from this app — so a treaty deadline registered
            // only there would be watched by nothing in Production.
            //
            // Reports only. Rendering an account sends figures to reinsurers and
            // is somebody's decision, not a scheduler's, so --open is not passed
            // and no status is ever moved. It also reports the case no query
            // over the statements table can see: a quarter that closed with NO
            // statement, which cannot be overdue because nothing is looking at
            // it.
            //
            // Daily because BR-ACC-10 charges interest at 110% of prime from the
            // due date — a deadline missed by a week is a week of interest
            // nobody meant to incur. First account due 14 November 2026.
            $schedule->command('treaty:statement-clocks')
                     ->dailyAt('07:30')
                     ->withoutOverlapping(10)
                     ->name('treaty-statement-clocks')
                     ->runInBackground();



            //********************Time   end  06:00 to 11:59    //



            //Time   start  12:00 to 17:59    //

            

            //**********************Time   end  12:00 to 17:59    //



            //Time   start  18:00 to 23:59    //
            $schedule->command('quoteByToday:cron')->dailyAt('19:10');
            $schedule->command('dailyupgradepolicies:cron')->dailyAt('19:15');
            $schedule->command('dailycancelledpolicies:cron')->dailyAt('19:36'); */
            // dailykycreport:cron is now scheduled from the cron_kernel table
            // (cron portal) as a single Daily entry at day-end — see the
            // DB-driven loop above, which adds runInBackground + withoutOverlapping.
            // Hardcoding it here as well double-scheduled it. Do NOT re-add.
            /*
            $schedule->command('claimstoday:cron')->dailyAt('19:45');
            $schedule->command('policyRenewUpdateData:cron')->dailyAt('20:00');
            $schedule->command('policyrenewaloperations:cron')->dailyAt('20:01');
            $schedule->command('setcustomercategory:cron')->dailyAt('20:15');
            $schedule->command('policiesByToday:cron')->dailyAt('20:20');
            $schedule->command('dailykycforactivatedpolicyreport:cron')->dailyAt('20:25');
            $schedule->command('updateorangereferencenumbers:cron')->dailyAt('20:30');
            $schedule->command('PolicyLedgerDaily:cron')->dailyAt('20:40');
            $schedule->command('checkcustomerbankingdata:cron')->dailyAt('21:08');
            $schedule->command('reraterenewpolicy:cron')->dailyAt('21:10');
            $schedule->command('FetchEmailNotExistsDpoTransactions:cron')->dailyAt('21:30');
            $schedule->command('getexpiredcards:cron')->dailyAt('22:00');
            $schedule->command('getlowbalancetransactions:cron')->dailyAt('22:02');
            $schedule->command('updatepremiumbillingdata:cron')->dailyAt('22:30');
           
            $schedule->command('policy:activatedToday')->dailyAt('23:00');
            $schedule->command('checkcustomerkycdata:cron')->dailyAt('23:10');
            $schedule->command('matiUpdateKycDocuments:cron')->dailyAt('23:30');
            $schedule->command('realpayfailedtransactions:cron')->dailyAt('23:50');
            $schedule->command('cron:daily-report')->dailyAt('23:55');
            // **************Time   end  18:00 to 23:59    //
            

         

            //  weekly start//

             $schedule->command('policyledger:cron')->weekly()->sundays()->at('01:00');
             $schedule->command('adiduplicatepolicy:cron')->weekly()->sundays()->at('11:00');
             $schedule->command('UpdateOrangeTransactions:cron')->weekly()->sundays()->at('13:00');
             $schedule->command('addbankbranches:cron')->weekly()->sundays()->at('15:00');
             $schedule->command('sendbeneficiaryupdatesms:cron')->weekly()->sundays()->at('15:00');

          

            //  other start//
            $schedule->command('agentcollectionrate:cron')->lastDayOfMonth('23:00');
       */
            //dpo payment
            // $schedule->command('policy:generateSubscriptionToken')->daily()->at('09:00');
            // $schedule->command('policy:chargeRecurrentToken')->daily()->at('09:05');
            // $schedule->command('policy:generateSubscriptionToken')->daily()->at('23:00');
            // $schedule->command('policy:chargeRecurrentToken')->daily()->at('23:05');

            //$schedule->command('TestCron:cron')->daily()->at('11:40');
            // $schedule->command('dpo:pay')->daily()->at('23:15');
            #$schedule->command('updatecustomerkycstatus:cron')->dailyAt('22:05');


             //***************************  other end*******************************************//


        }
        // Note: removed the prior `else` branch that hardcoded dpo:pay +
        // OrangeScheduledTransactionPayment for local dev. Both are now
        // registered unconditionally in the always-on safety-net block
        // below (with withoutOverlapping) so they fire on every env.

        // ── Always-on critical payment crons (safety-net) ────────────────
        // These MUST fire on production and staging regardless of the
        // cron_kernel loop, because:
        //   1. dpo:pay sweeps the daily DPO settlement run — missing it for
        //      one night means a day of customer payments don't get reconciled.
        //   2. OrangeScheduledTransactionPayment processes the morning Orange
        //      Money debit run — missing it delays premium collection by 24h.
        // Historically these were registered only inside the if/else gate,
        // which meant a single typo in APP_STATUS silently disabled them.
        // Registering them here as well guarantees they fire even if the
        // cron_kernel loop is mis-configured. The withoutOverlapping(120)
        // guard prevents a stuck run from blocking the next day's run for
        // more than 2 hours.
        $tagCron(
            $schedule->command('dpo:pay')->dailyAt('23:15')->withoutOverlapping(120),
            'dpo:pay'
        );
        $tagCron(
            $schedule->command('OrangeScheduledTransactionPayment:cron')->dailyAt('08:30')->withoutOverlapping(120),
            'OrangeScheduledTransactionPayment:cron'
        );

        /*Active CRONS disabled*/
        $tagCron($schedule->command('cancelpoliciescron:cron')->dailyAt('20:30'), 'cancelpoliciescron:cron');

        // Renewal notification pipeline — daily 07:00 UTC
        $tagCron($schedule->command('renewal:notify')->dailyAt('07:00')->withoutOverlapping(), 'renewal:notify');

        // MotoLink (motolink.app) INBOUND assessment bridge (Claims-Tracker port).
        // Pulls vehicle assessments every 15 min and mirrors them onto the
        // matching claim (matched on claim_number). OFF / DARK by default: with
        // no MOTOLINK_API_KEY configured the command is a COMPLETE no-op (no
        // outbound call, no DB write, no error). This is the LIVE tick — the cron/
        // twin app runs the prod scheduler; the same command is also registered
        // in backend/app/Console/Kernel.php for manual runs + parity.
        // runInBackground per the GRA-0203 dispatch-reliability convention.
        $tagCron(
            $schedule->command('claims:motolink-sync')
                ->cron('*/15 * * * *')
                ->runInBackground()
                ->withoutOverlapping(10)
                ->appendOutputTo(storage_path('logs/cronLogs.log')),
            'claims:motolink-sync'
        );

        // Self-heal stuck withoutOverlapping mutex locks every night at 02:00.
        // Without this, a single crashed cron run can hold a Redis lock until
        // the next container restart — silently skipping every subsequent
        // daily schedule firing for that command (this is what caused
        // DomComMonthlyAutoRenew + DomComQuaterlyAutoRenew to stop firing
        // for weeks at a time in Mar/Apr/May 2026). 02:00 is intentionally
        // before any business-hours cron so we never clear a still-running
        // lock by accident.
        $tagCron($schedule->command('schedule:clear-locks')->dailyAt('02:00')->withoutOverlapping(), 'schedule:clear-locks');

        // ── pyengine financial / compliance reports ───────────────────────
        // 10 jobs defined in pyengine/engine.py weren't scheduled anywhere
        // — admin UI showed them all as "Never run" except when manually
        // triggered. Now they auto-fire on a staggered schedule. Heavy
        // reports go weekly Sunday early morning to avoid colliding with
        // daily settlement / commission workloads. Each writes to
        // cron_runs.summary so /api/v1/cron-reports/ surfaces in admin UI.
        //
        // Daily — light + critical
        $schedule->exec('python3 -m pyengine.engine --job anomaly')
                 ->dailyAt('04:30')->withoutOverlapping()->name('pyengine-anomaly');
        $schedule->exec('python3 -m pyengine.engine --job payment_anomaly')
                 ->dailyAt('04:45')->withoutOverlapping()->name('pyengine-payment-anomaly');
        $schedule->exec('python3 -m pyengine.engine --job premium_anomaly')
                 ->dailyAt('05:00')->withoutOverlapping()->name('pyengine-premium-anomaly');
        $schedule->exec('python3 -m pyengine.engine --job policy_audit')
                 ->dailyAt('05:15')->withoutOverlapping()->name('pyengine-policy-audit');
        $schedule->exec('python3 -m pyengine.engine --job kyc_compliance_report')
                 ->dailyAt('05:30')->withoutOverlapping()->name('pyengine-kyc-compliance');
        $schedule->exec('python3 -m pyengine.engine --job claims_anomaly')
                 ->dailyAt('05:45')->withoutOverlapping()->name('pyengine-claims-anomaly');

        // Weekly — heavier aggregates. Sundays.
        $schedule->exec('python3 -m pyengine.engine --job written_premium')
                 ->weekly()->sundays()->at('03:00')
                 ->withoutOverlapping()->name('pyengine-written-premium');
        $schedule->exec('python3 -m pyengine.engine --job ageing')
                 ->weekly()->sundays()->at('03:30')
                 ->withoutOverlapping()->name('pyengine-ageing');
        $schedule->exec('python3 -m pyengine.engine --job collections_report')
                 ->weekly()->sundays()->at('04:00')
                 ->withoutOverlapping()->name('pyengine-collections');
        $schedule->exec('python3 -m pyengine.engine --job reinsurance_anomaly')
                 ->weekly()->sundays()->at('04:30')
                 ->withoutOverlapping()->name('pyengine-reinsurance-anomaly');
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
