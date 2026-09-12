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

        $arr = [1,239,240,243,244,245,247,251,252,254,256,259,268,272,274,276,277,278,284,286,289,290,291,292,293,296,297,298,299,300,301,303,304,305,306,310,312,313,314,315,316,317,318,319,320,321,322,327,335,336,339,341,344,345,346,347,348,349,350,352,354,355,356,361,363,365,366,367,368,374,375,376,377,378,379,380,381,382,383,385,386,387,390,391,392,393,394,395,397,398,401,404,405,406,407,408,411,412,414,415,416,417,418,419,420,421,422,423,424,425,426,427,428,430,431,432,433,434,435,436,437,438,439,440,441,442,443,445,446,447,448,449,450,451,452,453,454,455,456,457,458,459,460,461,462,465,466,467,468,469,470,471,472,474,475,476,477,481,482,484,485,487,488,489,490,491,492,493,495,496,498,499,500,501,505,506,508,509,510,511,512,513,514,515,516,517,518,519,520,522,523,524,525,526,527,528,529,531,533,534,535,536,537,538,539,540,542,544,550,551,552,553,554,558,559,560,562,563,565,566,567,570,571,575,576,578,579,583,584,587,590,591,596,597,598,602,603,605,617,618,619,620,622,623,624,625,626,627,628,629,630,631,633,636,637,638,639,644,646,648,651,652,653,654,655,659,662,663,664,666,668,674,675,676,677,678,679,680,681,683,684,685,686,690,691,692,693,694,695,696,697,698,699,700,701,702,704,705,706,710,711,712,713,717,718,720,721,722,723,724,725,726,728,729,730,731,732,733,734,735,738,739,741,742,743,744,745,746,747,748,749,750,751,752,753,754,755,756,757,758,760,761,762,763,764,765,766,767,770,771,773,776,780,781,782,783,784,785,786,787,788,789,790,791,792,793,794,795,796,797,798,799,800,801,802,803,804,805,809,810,811,812,813,814,815,816];
        $agents = User::whereIn('id',$arr)->get();

        // $agents = User::where('active',"1")->where('llmUserStatus',null)->limit(100)->get();

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
