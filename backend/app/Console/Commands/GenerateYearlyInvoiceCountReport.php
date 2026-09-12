<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use PDF;
use DB;
use Illuminate\Support\Facades\Storage;
use AlphaDirect\Models\CronStatus;
use AlphaDirect\Http\Controllers\CronController;
use Log;
class GenerateYearlyInvoiceCountReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'generateYearlyInvoiceCountReport:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate Yearly Invoice Count Report';

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
        $cron->name = "generateYearlyInvoiceCountReport:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();

        Log::info('Cron Started to generate report with invoice count.');

        $policies = DB::select(DB::raw('
            SELECT pl.policy_id,pt.id,pt.term_start_date,pt.term_end_date,pt.frequency,p.policyNumber,p.status FROM graphite_archive.policy_ledger as pl
            join Graphite_live.policy_term pt on pl.policy_id = pt.policy_id
            join Graphite_live.policies p on pl.policy_id = p.id
            where pl.trans_type="invoice" and date(pl.accounting_date) between pt.term_start_date and pt.term_end_date
            order by pl.policy_id desc;
        '));

        $data = [
            'data'=>$policies
        ];

        $date = \Carbon\Carbon::now()->timestamp;
        $path = 'Invoice/created-'.$date.'/generateYearlyInvoiceCountReport.pdf';

        libxml_use_internal_errors(true);
        $pdf = PDF::loadView('admin.notes.generateYearlyInvoiceCountReport', $data)->setPaper('a3', 'landscape');
        Storage::disk('s3')->put($path, $pdf->output(), 'public');

        $attachments = array();
        array_push($attachments, $path);
        if(count($policies) > 0){
            ////*************Email send new fuction **************/////
            $cronSendMail = new CronController();
            $hook = 'generate_invoice_count_report';
            $cronSendMail->AllCronMail($attachments,$hook,$cron);
            ////*************Email send new fuction END **************/////
        }

        Storage::disk('s3')->delete($path);
        $cron->end = \Carbon\Carbon::now();
        $cron->save();
    }
}
