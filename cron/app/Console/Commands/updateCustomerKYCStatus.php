<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\KYC;
use Illuminate\Console\Command;
use DB;
use Log;
use AlphaDirect\Models\CronStatus;
use AlphaDirect\Policy;
use PDF;
use Illuminate\Support\Facades\Storage;
use AlphaDirect\Http\Controllers\CronController;
use Carbon\Carbon;

class updateCustomerKYCStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'updatecustomerkycstatus:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update customer kyc status';

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
        $cron->name = "updatecustomerkycstatus:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        /*$kycData = DB::select(DB::raw('select cp.id,ck.id as data_id,cp.customer_id,cp.omang,cp.passport,ck.compliance from customer_kyc ck
                                    inner join customer_profile cp on cp.customer_id = ck.customer_id
                                    where (cp.omang is null and cp.passport is null) and ck.compliance = 1 order by ck.id desc;'));

        $documentsCheck = DB::select(DB::raw('select cp.id,ck.id as data_id,cp.customer_id,
                                        cp.omang,cp.passport,ck.compliance,ck.driving_license,
                                        ck.omang,ck.omangBack,ck.proof_income,ck.proof_residence,ck.passport from customer_kyc ck
                                        inner join customer_profile cp on cp.customer_id = ck.customer_id
                                        where (cp.omang is null and cp.passport is null) and (
										ck.driving_license is null
										and ck.omang is null
                                        and ck.omangBack is null
                                        and ck.proof_income is null
                                        and ck.proof_residence is null
                                        and ck.passport is null
                                        ) order by ck.id desc;'));

        $statusCheck = DB::select(DB::raw('select cp.id,ck.id as data_id,cp.customer_id,cp.omang,cp.passport,ck.compliance,ck.status from customer_kyc ck
                                    inner join customer_profile cp on cp.customer_id = ck.customer_id
                                    where ck.status like "Unchecked" and ck.compliance = 1 order by ck.id desc;'));

        if(count($statusCheck) > 0){
            foreach($statusCheck as $key=>$data){
                $kyc = KYC::where('id',$data->data_id)->update(['compliance'=>0,'status'=>'Unchecked']);
            }
        }
        if(count($kycData) > 0){
            foreach($kycData as $key=>$data){
                $kyc = KYC::where('id',$data->data_id)->update(['compliance'=>0,'status'=>'Unchecked']);
            }
        }

        if(count($documentsCheck) > 0){
            foreach($documentsCheck as $key=>$data){
                $kyc = KYC::where('id',$data->data_id)->update(['compliance'=>3,'status'=>'Unchecked']);
            }
        }*/
        $startDate =  Carbon::parse('today')->subDay(2)->format('Y-m-d'). ' 00:00:01';
        $endDate   =  Carbon::parse('today')->format('Y-m-d'). ' 23:59:59';
        $compliantData = KYC::where('compliance',1)
            ->where('status','Approve')
            ->whereBetween('updated_at', [$startDate, $endDate])
            ->get(['id','compliance','status','omangExpiry','licenseExpiry','passportExpiry']);

        $getStatusUpdatedList = [];
        if(count($compliantData) > 0){
            foreach($compliantData as $key=>$cd){
                try{
                    if($cd->omangExpiry != null){
                        $omangDate = str_replace('/', '-', $cd->omangExpiry);
                        $date1 = date('Y-m-d',strtotime($omangDate));

                        $date1 = Carbon::parse($date1)
                            ->addYear(1)->format('Y-m-d');

                        if(Carbon::now()->format('Y-m-d') == $date1){
                            $cd->compliance = 0;
                            $cd->status = 'Recheck(KYC Expired)';
                            $cd->save();
                        }
                    }

                    if($cd->passportExpiry != null){
                        $passportDate = str_replace('/', '-', $cd->passportExpiry);
                        $date2 = date('Y-m-d',strtotime($passportDate));

                        $date2 = Carbon::parse($date2)
                            ->addYear(1)->format('Y-m-d');
                        if(Carbon::now()->format('Y-m-d') == $date2){
                            $cd->compliance = 0;
                            $cd->status = 'Recheck(KYC Expired)';
                            $cd->save();
                        }
                    }

                    if($cd->licenseExpiry != null){
                        $licenseDate = str_replace('/', '-', $cd->licenseExpiry);
                        $date3 = date('Y-m-d',strtotime($licenseDate));

                        $date3 = Carbon::parse($date3)
                            ->addYear(1)->format('Y-m-d');

                        if(Carbon::now()->format('Y-m-d') == $date3){
                            $cd->compliance = 0;
                            $cd->status = 'Recheck(KYC Expired)';
                            $cd->save();
                        }
                    }

                    // GRA-0120: only count LIVE motor-comprehensive policies when
                    // deciding whether to flag "Recheck(Docs not uploaded)". A
                    // cancelled (2) or expired (3) policy must NOT keep dragging the
                    // customer back onto the KYC pending/recheck dashboard — there's
                    // nothing to chase docs for once the policy is dead. (Ports the
                    // fix already present in the backend copy to the scheduled cron copy.)
                    $policies = Policy::where('customer_id',$cd->customer_id)->where('product_id',3)->whereNotIn('status',[2,3])->count();
                    if ($policies > 0) {
                        if (!isset($cd->omang) || !isset($cd->omangBack) || !isset($cd->passport) || !isset($cd->driving_license) || !isset($cd->proof_residence) || !isset($cd->proof_income)) {
                            $cd->compliance = 0;
                            $cd->status = 'Recheck(Docs not uploaded)';
                            $cd->save();

                            array_push($getStatusUpdatedList,$cd);

                        }
                    }

                }catch(\Exception $ex){
                   \Log::error($cd->id.'--'.$ex->getMessage());
                }
            }
        }



        $report = [
            'getKycList' => $getStatusUpdatedList,
            'title'    => 'Kyc status updated List'
        ];

        $date = \Carbon\Carbon::now()->timestamp;
        $path = 'policies-'.$date.'/updatedCustomerKycStatus.pdf';
        libxml_use_internal_errors(true);
        $pdf = PDF::loadView('admin.notes.updatedCustomerKycStatus', $report)->setPaper('a3', 'landscape');
        Storage::disk('s3')->put($path, $pdf->output(), 'public');
        $attachments = array();
        array_push($attachments, $path);

        ////*************Email send new fuction **************/////
        $cronSendMail = new CronController();
        $hook = 'updated_customer_kyc_status';
        $cronSendMail->AllCronMail($attachments,$hook,$cron);

        ////*************Email send new fuction END **************/////

        $cron->end = \Carbon\Carbon::now();
        $cron->save();
        return Command::SUCCESS;
    }
}
