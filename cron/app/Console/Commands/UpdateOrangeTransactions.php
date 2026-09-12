<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\PaymentSchedule;
use Carbon\Carbon;
use Illuminate\Console\Command;
use DB;
use Log;
use AlphaDirect\Models\CronStatus;

class UpdateOrangeTransactions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'UpdateOrangeTransactions:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update Orange Transactions';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
         $cron = new CronStatus();
        $cron->name = "UpdateOrangeTransactions:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        Log::info('Cron Started for orange transactions');

        $data = PaymentSchedule::whereBetween(\Illuminate\Support\Facades\DB::raw('date(payment_date)') , [today()->subDays(-0)
            ->format('Y-m-d')  , Carbon::parse(today()->subDays(-7))
            ->format('Y-m-d') ])
            ->get(array('id'));

        $update = PaymentSchedule::whereBetween(\Illuminate\Support\Facades\DB::raw('date(payment_date)') , [today()->subDays(1)
            ->format('Y-m-d')  , Carbon::parse(today()->subDays(7))
            ->format('Y-m-d') ])
            ->get(array('id'));

        if($data != null){
            foreach($data as $d){
                DB::table('policy_payment_schedules')
                    ->where('id', $d->id)
                    ->update(array('is_approaching' => 1));
            }
        }

        if($update != null){
            foreach($update as $u){
                DB::table('policy_payment_schedules')
                    ->where('id', $u->id)
                    ->update(array('is_approaching' => 0));
            }
        }
        $cron->end = \Carbon\Carbon::now();
        $cron->save();
    }
}
