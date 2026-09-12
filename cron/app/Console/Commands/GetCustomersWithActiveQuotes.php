<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Customer;
use AlphaDirect\QuotesForPromotion;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Log;
use AlphaDirect\Models\CronStatus;

class GetCustomersWithActiveQuotes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'getcustomerswithactivequotes:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get customers with active quotes';

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
        $cron->name = "getcustomerswithactivequotes:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        $quotes =  DB::select(DB::raw("SELECT customer_id,quoteNumber,status FROM motor_comp_quotes where customer_id not in (select customer_id from motor_comp_quotes where Status<>1) group by customer_id order by created_at DESC;"));

        if(count($quotes) != 0){
            Log::info('Cron Started to fetch quotes');
            foreach($quotes as $key=>$quote){
                sleep(1);
                if($quote->quoteNumber != null && $quote->status == 1){
                    $check = QuotesForPromotion::where('quoteNumber',$quote->quoteNumber)->exists();
                    if($check == false){
                        $add = new QuotesForPromotion();
                        $add->customer_id = $quote->customer_id;
                        $add->quoteNumber = $quote->quoteNumber;
                        $add->is_rerated = 0;
                        $add->is_email_sent = 0;
                        $add->is_sms_sent = 0;
                        $add->quote_status = $quote->status;
                        $add->save();
                    }
                }
            }
        }
         $cron->end = \Carbon\Carbon::now();
        $cron->save();
    }
}
