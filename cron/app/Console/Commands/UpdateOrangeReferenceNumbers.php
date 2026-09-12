<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Claim;
use AlphaDirect\Http\Controllers\Payment\OrangeMoney\OrangeMoneyController;
use AlphaDirect\PaymentSchedule;
use AlphaDirect\PaymentTransaction;
use AlphaDirect\Transaction;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Log;
use DB;
use DateTime;
use AlphaDirect\Models\CronStatus;

class UpdateOrangeReferenceNumbers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'updateorangereferencenumbers:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update orange reference numbers';

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
        $cron->name = "updateorangereferencenumbers:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        Log::info('Cron Started for orange reference numbers');

        $trans = PaymentTransaction::whereBetween(\Illuminate\Support\Facades\DB::raw('date(created_at)') , [Carbon::parse('today')
            ->format('Y-m-d')  , Carbon::parse('today')
            ->format('Y-m-d') ])
            ->where('paymentMethod','orangeMoney')
            ->orderBy('id','DESC')
            ->get();

        $payment = new OrangeMoneyController();

        foreach($trans as $key=>$tr){
            sleep(1);
            $orange = $payment->addSchedule($tr->policyNumber);

            //->whereRaw('MONTH(payment_date) = ?',[$currentMonth])

            $check = PaymentSchedule::where('reference_number',$tr->referenceNumber)->get(array('id'));

            if(count($check) == 0){
                $currentMonth = date('m');
                $data = PaymentSchedule::where('policy_number',$tr->policyNumber)
                    ->where('status',0)
                    ->first();

                if($data == null)
                    $data = new PaymentSchedule();

                $data->reference_number = $tr->referenceNumber;
                $data->success_payment_date = (new DateTime())->format('Y-m-d');
                $data->amount = $tr->amount;
                $data->status = 1;
                $data->save();
            }

        }
        $cron->end = \Carbon\Carbon::now();
        $cron->save();
    }
}
