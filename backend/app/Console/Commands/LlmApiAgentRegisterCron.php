<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use PDF;
use Auth;
use File;
use Illuminate\Support\Facades\DB;
use Log;
use AlphaDirect\Models\CronStatus;
use AlphaDirect\Http\Controllers\CronController;
use AlphaDirect\Events\ReportInExcelDownloadEvent;
use AlphaDirect\Exports\ReportExport;
use AlphaDirect\User;
use Maatwebsite\Excel\Facades\Excel;
use AlphaDirect\Stores;
use Illuminate\Http\Request;
use AlphaDirect\Http\Controllers\LlmApiCrontroller;
class LlmApiAgentRegisterCron extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'llmapiagent:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

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
        $cron->name = "llmapiagent:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        $agents = User::where('active',"1")->where('llmUserStatus',null)->get();
        Log::info(count($agents));
        if(count($agents) > 0){
            $ids = [];
            foreach($agents as $id){
                   $ids[] = $id->id;
            }
                $arjunapi =  new LlmApiCrontroller();
                $arjunapi->RegisterAgent($ids);
        }

        $cron->end = \Carbon\Carbon::now();
        $cron->save();

    }
}
