<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\BankBranches;
use AlphaDirect\Banks;
use AlphaDirect\EmailBroadcasting;
use AlphaDirect\Http\Controllers\Admin\DocumentController;
use AlphaDirect\Mail\MailTemplate;
use AlphaDirect\Models\PolicyRenewal;
use AlphaDirect\MotorComprehensiveQuotes;
use AlphaDirect\Policy;
use AlphaDirect\PolicyCoverCancelNote;
use AlphaDirect\Quote;
use AlphaDirect\RealpayFailedTransEmails;
use AlphaDirect\RealpayLogs;
use Carbon\Carbon;
use DateInterval;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use PDF;
use Str;
use Auth;
use File;
use DB;
use DateTime;
use Log;
use AlphaDirect\Models\CronStatus;
class regenerateCoverNote extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'regeneratecovernote:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Regenerate cover note';

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
        $cron->name = "regeneratecovernote:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        $data = DB::select(DB::raw('select * from sentPolicyDocumentLogs where documents like "%Cover_%"'));
        $type = 'Cover';
        $updatedDocs = [];
        if(count($data) > 0){
            foreach($data as $key=>$d){
                if($d->documents && $d->policyNumber){
                    $documents = unserialize($d->documents);
                    if(Str::contains($documents[count($documents)-1], 'Cover_')){
                        $policy = Policy::join('customer','customer.id','policies.customer_id')
                            ->join('vehicle','vehicle.policy_id','policies.id')
                            ->join('customer_banking','customer_banking.policy_id','policies.id')
                            ->where('policies.policyNumber',$d->policyNumber)
                            ->first(
                                array(
                                    'policies.id as policy_id',
                                    'policies.policyNumber as policyNumber',
                                    'policies.created_at as created_at',
                                    'vehicle.make',
                                    'vehicle.model',
                                    'vehicle.year',
                                    'vehicle.vehiclePlate',
                                    'vehicle.financial_interest',
                                    'vehicle.financial_interest_other',
                                    'policies.sum_assured as estimated_value',
                                    'customer_banking.billing as payment_method',
                                    'customer_banking.bankName',
                                    'customer_banking.branchCode',
                                    'customer_banking.billing',
                                    'customer.firstName',
                                    'customer.lastName',
                                    'customer.email',
                                    'customer.id as customer_id',
                                )
                            );

                        if($policy != null){
                            if($policy->financial_interest){
                                $i = $policy->financial_interest;
                            }elseif($policy->financial_interest_other){
                                $i = $policy->financial_interest_other;
                            }else{
                                $i = null;
                            }

                            $today = new DateTime($policy->created_at);
                            $from = $today->format('d-m-Y');
                            $interval = new DateInterval('P1Y');
                            $to = $today->add($interval)->modify("-1 day")->format('d-m-Y');


                            if($policy->payment_method == "RealPay"){
                                if($policy->bankName && $policy->branchCode){
                                    $bank = Banks::where('bank_number',$policy->bankName)->first(array('bank_name'));
                                    $branch = BankBranches::where('bank_id',$policy->bankName)
                                        ->where('branch_id',$policy->branchCode)
                                        ->first(array('name'));

                                    $policy['bankName'] = $bank->bank_name;
                                    $policy['branchCode'] = $branch->name;
                                }
                            }

                            $today = new DateTime();
                            $policy['curr_date'] = $today->format('d-m-Y');
                            $policy['financial_interest'] = $i;

                            $documentController = new DocumentController();

                            $qrCode = $documentController->generateQRCode($policy->policy_id);

                            $array =[
                                'policy'=>$policy,
                                'to'=>$to,
                                'from'=>$from,
                                'QRCode'=>$qrCode,
                            ];

                            $path = $documents[count($documents)-1];
                            libxml_use_internal_errors(true);

                            if($type == 'Cancel') {
                                $pdf = PDF::loadView('admin.notes.cancel_note-New', $array);
                                $hook = 'policy_cancel_note';
                            }
                            elseif($type == 'Cover') {
                                $pdf = PDF::loadView('admin.notes.cover_note-New', $array);
                                $hook = 'policy_cover_note';
                            }
                            else {
                                return Redirect::back()->with('error', 'Sorry! Please specify document type to generate pdf');
                            }

                            Storage::disk('s3')->put($path, $pdf->output(), 'public');

                            $store = PolicyCoverCancelNote::where('policy_id',$policy->policy_id)->first();
//
//                            if($store == null)
//                                $store = new PolicyCoverCancelNote();

                            $store->policy_id = $policy->policy_id;
                            $store->path = $path;
                            $store->doc_type = $type;
                            $store->save();

                            if(File::exists($qrCode)) {
                                File::delete($qrCode);
                            }

                            array_push($updatedDocs,$path);

                        }else{
                            return null;
                        }
                    }
                }
            }
            log::info($updatedDocs);
        }
         $cron->end = \Carbon\Carbon::now();
         $cron->save();
    }
}


