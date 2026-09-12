<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Policy;
use Carbon\Carbon;
use Illuminate\Console\Command;
use DB;
use Illuminate\Support\Facades\Storage;
use Log;
use PDF;
use AlphaDirect\Models\CronStatus;
use AlphaDirect\Http\Controllers\CronController;

class GenerateMonthlySalesReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'generateMonthlySalesReport:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate Monthly Sales Report';

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
        $cron->name = "generateMonthlySalesReport:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        Log::info('Cron Started for generating sales report.');

        $toDate = Carbon::now()->subDays(1)->format('Y-m-d');
        $fromDate = Carbon::createFromFormat('Y-m-d', $toDate)->subMonth()->subDays(1)->format('Y-m-d');

        $getBilledCounForNewSales = DB::select('call getBilledCounForNewSales(?,?)',[$fromDate,$toDate]);

        $getBilledCountForRecurring = DB::select('call getBilledCountForRecurring(?,?)',[$fromDate,$toDate]);

        $getPaidCountForNewSales = DB::select('call getPaidCountForNewSales(?,?)',[$fromDate,$toDate]);

        $getPaidCountForRecurring = DB::select('call getPaidCountForRecurring(?,?)',[$fromDate,$toDate]);

        //*********** New Sales (Amount) ***********//
        $totalAmtNewSalesBilled = 0;
        foreach ($getBilledCounForNewSales as $key => $item) {
            $totalAmtNewSalesBilled += $item->total_billed_amount;
        }

        $totalAmtNewSalesPaid = 0;
        foreach ($getPaidCountForNewSales as $key => $item) {
            $totalAmtNewSalesPaid += $item->total_paid_amount;
        }

        $amtNewSales = [];
        for ($i = 0; $i < count($getBilledCounForNewSales); $i++) {
            $amtNewSales[] = [
                'getBilled' => isset($getBilledCounForNewSales[$i]) ? $getBilledCounForNewSales[$i] : null,
                'getPaid' => isset($getPaidCountForNewSales[$i]) ? $getPaidCountForNewSales[$i] : null,
            ];
        }

        //*********** Recurring Collection (Amount) ***********//
        $totalAmtRecurringBilled = 0;
        foreach ($getBilledCountForRecurring as $key => $item) {
            $totalAmtRecurringBilled += $item->total_recurring_billed_amount;
        }

        $totalAmtRecurringPaid = 0;
        foreach ($getPaidCountForRecurring as $key => $item) {
            $totalAmtRecurringPaid += $item->total_recurring_paid_amount;
        }

        $amtRecurringCollection = [];
        for ($i = 0; $i < count($getBilledCountForRecurring); $i++) {
            $amtRecurringCollection[] = [
                'getBilled' => isset($getBilledCountForRecurring[$i]) ? $getBilledCountForRecurring[$i] : null,
                'getPaid' => isset($getPaidCountForRecurring[$i]) ? $getPaidCountForRecurring[$i] : null,
            ];
        }

        //*********** Sales + Collection (Amount) ***********//

        $amtRecurringAndNewSales = [];
        for ($i = 0; $i < count($getBilledCounForNewSales); $i++) {
            $amtRecurringAndNewSales[] = [
                'getBilledNewSales' => isset($getBilledCounForNewSales[$i]) ? $getBilledCounForNewSales[$i] : null,
                'getPaidNewSales' => isset($getPaidCountForNewSales[$i]) ? $getPaidCountForNewSales[$i] : null,
                'getBilledRecurring' => isset($getBilledCountForRecurring[$i]) ? $getBilledCountForRecurring[$i] : null,
                'getPaidRecurring' => isset($getPaidCountForRecurring[$i]) ? $getPaidCountForRecurring[$i] : null,
            ];
        }

        $salesCollTotalbilled = 0;
        $salesCollTotalPaid = 0;

        foreach ($amtRecurringAndNewSales as $key => $item) {
            $billedSalesColl = 0;
            $paidSalesColl = 0;

            if (isset($item['getBilledNewSales'])) {
                if (isset($item['getBilledRecurring'])) {
                    $billedSalesColl = $item['getBilledNewSales']->total_billed_amount + $item['getBilledRecurring']->total_recurring_billed_amount;
                }
            }

            if (isset($item['getPaidNewSales'])) {
                if (isset($item['getPaidRecurring'])) {
                    $paidSalesColl = $item['getPaidNewSales']->total_paid_amount + $item['getPaidRecurring']->total_recurring_paid_amount;
                }
            }

            $salesCollTotalbilled += $billedSalesColl;

            $salesCollTotalPaid += $paidSalesColl;
        }

        //*********** New Sales (Count) ***********//
        $totalCountNewSalesBilled = 0;
        foreach ($getBilledCounForNewSales as $key => $item) {
            $totalCountNewSalesBilled += $item->new_sales;
        }

        $totalCountNewSalesPaid = 0;
        foreach ($getPaidCountForNewSales as $key => $item) {
            $totalCountNewSalesPaid += $item->new_sales;
        }


        //*********** Recurring Collection (Count) ***********//
        $totalCountRecurringBilled = 0;
        foreach ($getBilledCountForRecurring as $key => $item) {
            $totalCountRecurringBilled += $item->recurring;
        }

        $totalCountRecurringPaid = 0;
        foreach ($getPaidCountForRecurring as $key => $item) {
            $totalCountRecurringPaid += $item->recurring;
        }

         //*********** Sales + Collection (Amount) ***********//

        $salesCollCountTotalbilled = 0;
        $salesCollCountTotalPaid = 0;

        foreach ($amtRecurringAndNewSales as $key => $item) {
            $billedCountSalesColl = 0;
            $paidCountSalesColl = 0;

            if (isset($item['getBilledNewSales'])) {
                if (isset($item['getBilledRecurring'])) {
                    $billedCountSalesColl = $item['getBilledNewSales']->new_sales + $item['getBilledRecurring']->recurring;
                }
            }


            if (isset($item['getPaidNewSales'])) {
                if (isset($item['getPaidRecurring'])) {
                    $paidCountSalesColl = $item['getPaidNewSales']->new_sales + $item['getPaidRecurring']->recurring;
                }
            }

            $salesCollCountTotalbilled += $billedCountSalesColl;

            $salesCollCountTotalPaid += $paidCountSalesColl;
        }

        $report = [
            'amtNewSales' => $amtNewSales,
            'totalAmtNewSalesBilled' => $totalAmtNewSalesBilled,
            'totalAmtNewSalesPaid' => $totalAmtNewSalesPaid,
            'amtRecurringCollection' => $amtRecurringCollection,
            'totalAmtRecurringBilled' => $totalAmtRecurringBilled,
            'totalAmtRecurringPaid' => $totalAmtRecurringPaid,
            'amtRecurringAndNewSales' => $amtRecurringAndNewSales,
            'salesCollTotalbilled' => $salesCollTotalbilled,
            'salesCollTotalPaid' => $salesCollTotalPaid,
            'totalCountNewSalesBilled' => $totalCountNewSalesBilled,
            'totalCountNewSalesPaid' => $totalCountNewSalesPaid,
            'totalCountRecurringBilled' => $totalCountRecurringBilled,
            'totalCountRecurringPaid' => $totalCountRecurringPaid,
            'salesCollCountTotalbilled' => $salesCollCountTotalbilled,
            'salesCollCountTotalPaid' => $salesCollCountTotalPaid,
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'title'    => 'Sales Report'
        ];

        // return $report;
        // return view('admin.notes.generateMonthlySalesReport',$report);
        $date = \Carbon\Carbon::now()->timestamp;
        $path = 'policies-'.$date.'/generateMonthlySalesReport.pdf';
        libxml_use_internal_errors(true);
        $pdf = PDF::loadView('admin.notes.generateMonthlySalesReport', $report)->setPaper('a3', 'portrait');
        Storage::disk('s3')->put($path, $pdf->output(), 'public');
        // return Storage::disk('s3')->download($path);
        $attachments = array();
        array_push($attachments, $path);

        ////*************Email send new fuction **************/////
        $cronSendMail = new CronController();
        $hook = 'generate_monthly_sales_report';
        $cronSendMail->AllCronMail($attachments,$hook,$cron);

        ////*************Email send new fuction END **************/////

        $cron->end = \Carbon\Carbon::now();
        $cron->save();
    }
}
