<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use AlphaDirect\Models\CronStatus;
use Carbon\Carbon;
use DB;
use Log;
class DOMCOMPolicyTerm extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'domComPolicyTerm';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'dom com policy term status';

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
     * @return int
     */
    public function handle()
    {
        $cron = new CronStatus();
        $cron->name = "domComPolicyTerm";
        $cron->start = Carbon::now();
        $cron->save();
        Log::info('Dom Com Policy Term Status');
        $reportData = DB::select('call domComPolicyTerm()');
        if (count($reportData)>0) {
            foreach($reportData as $policy)
            {
                $docs = DB::select(DB::raw('UPDATE policy_term SET `status` = "Active" where id = '.$policy->id));
            }
        }
        return 0;
    }
}
