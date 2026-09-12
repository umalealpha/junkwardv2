<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\MotorComprehensiveQuotes;
use AlphaDirect\QuoteSettings;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Log;
use AlphaDirect\Models\CronStatus;

class UpdateQuoteExpiryDate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'updatequoteexpirydate:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update quote expiry date';

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
        $cron->name = "updatequoteexpirydate:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        Log::info('Cron Started to update expiry date of quotes');

        $quotes = MotorComprehensiveQuotes::where('status',1)->where('expiry_date',null)->orderBy('id','DESC')->get(array('id','expiry_date','created_at'));
        $setting = QuoteSettings::orderBy('id','DESC')->first(array('DaysToExpireQuote'));

        if(count($quotes) > 0){
            foreach($quotes as $key=>$quote){
                $quote->expiry_date = Carbon::parse($quote->created_at)->addDays($setting->DaysToExpireQuote)->format('Y-m-d');
                $quote->save();
            }
        }
         $cron->end = \Carbon\Carbon::now();
        $cron->save();
    }
}
