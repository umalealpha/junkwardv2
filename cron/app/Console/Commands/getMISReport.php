<?php

namespace AlphaDirect\Console\Commands;
use AlphaDirect\Models\CronStatus;
use AlphaDirect\Models\GetMisReport;
use AlphaDirect\User;
use Illuminate\Console\Command;
use AlphaDirect\Customer;
use Carbon\Carbon;
use Log;
use Illuminate\Support\Facades\DB;
use AlphaDirect\Mail\MailTemplate;
use AlphaDirect\EmailBroadcasting;
use AlphaDirect\Models\ProductCoverage;
use AlphaDirect\Models\PolicyCoverage;
use Illuminate\Support\Facades\Storage;
use AlphaDirect\Http\Controllers\CronController;
use AlphaDirect\Models\PolicyCoveragesData;
use AlphaDirect\Models\PolicyExcessesData;
use AlphaDirect\Models\PolicyBusiExcessesData;
use AlphaDirect\Models\PolicyCoverageDetail;
use AlphaDirect\Models\PolicyAction;
use AlphaDirect\Models\CoverageMaster;
use AlphaDirect\Region;
use AlphaDirect\Models\Extention;
use AlphaDirect\Models\PolicyExtentionDetails;
use AlphaDirect\Models\TbCvgpcLimits;
use AlphaDirect\Models\SpecifiedCoveragesItems;

class GetMISReportCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'getMISReport {--start-date=} {--end-date=} {--report-id=} {--auth-id=}';

    /**
     * The console command description..
     *
     * @var string
     */
    protected $description = 'getMISReport';

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
        $cron->name = "getMISReport";
        $cron->start = Carbon::now();
        $cron->save();
        Log::info('Get MIS report started');

        // Get parameters from command options or use defaults
        $startDate = $this->option('start-date') ?: '2025-07-01';
        $endDate = $this->option('end-date') ?: Carbon::today()->toDateString();
        $reportId = $this->option('report-id');
        $authId = $this->option('auth-id');
        
        // Get the report record if report-id is provided
        $misReportRecord = null;
        $userEmail = null;
        if ($reportId) {
            $misReportRecord = GetMisReport::find($reportId);
            if ($misReportRecord) {
                $misReportRecord->status = 'processing';
                $misReportRecord->save();
                
                // Get user email
                if ($authId) {
                    $user = User::find($authId);
                    if ($user) {
                        $userEmail = $user->email;
                    }
                }
            }
        }

        $columns = array('POLICY NO','Policy Frequency' ,'Term Start Date','Term End Date ','PRODUCT' ,'INSURED NAME', 'TRANS PK', 'TRANS TYPE','REFERENCE NUMBER','AGENCY','AGENT','RIKS PK','RISK NAME','MOTOR DESC','MOTOR MAKE','MOTOR PK','BOOKING DATE');
        // fputcsv($file, $columns);
        $results = array();
        $misReportData = DB::select('CALL getMISreportDOMCOM(?, ?)', [$startDate, $endDate]);
        //$misReportData = collect(DB::select('call getMISreportDOMCOM()'))->where('policyId', 101732);
        
        $totalPreData = $coverId = $resultDataNewMotor = [];
        if (count($misReportData)>0) {
            foreach($misReportData as $policy)
            {
                            
                            $premiumFreq = $policy->premiumFreq;
                            if($premiumFreq == 1)
                            {
                                $policy->premiumFreq = 'MONTHLY';
                            //  $totalAmtpre = ($totalFinalPre/12);
                            }
                            else if($premiumFreq == 2)
                            {
                                $policy->premiumFreq = '3 INSTALLMENTS';
                            // $totalAmtpre = ($totalFinalPre/3);
                            }
                            else if($premiumFreq == 3)
                            {
                                $policy->premiumFreq = 'ANNUAL';
                            // $totalAmtpre = $totalFinalPre;
                            }
                            else if($premiumFreq == 4)
                            {
                                $policy->premiumFreq = 'SEMIANNUAL';
                            // $totalAmtpre = ($totalFinalPre/2);
                            }
                            else if($premiumFreq == 5)
                            {
                                $policy->premiumFreq = 'QUARTERLY';
                            // $totalAmtpre = ($totalFinalPre/4);
                            }
                            else
                            {
                                $policy->premiumFreq = 'N/A';
                            }

                            $SpecifiedCoveragesItemsData = DB::table('policy_specified_items')
                            ->where('policy_coverage_id',$policy->sonali)
                            ->sum('calculated_value');

                            $SpecifiedsumInsured = DB::table('policy_specified_items')
                            ->where('policy_coverage_id',$policy->sonali)
                            ->sum('sum_insured');

                            $calculatedValue = DB::table('policy_coverage_detail')
                            ->join('tb_cvgpccoverages', 'tb_cvgpccoverages.id', '=', 'policy_coverage_detail.coverage_id')
                            ->where('policy_coverage_id', $policy->sonali)
                            ->whereNull('policy_coverage_detail.deleted_at')
                            ->sum('policy_coverage_detail.calculated_value');
        
 
                            $sumInsuredValue = DB::table('policy_coverage_detail')
                            ->join('tb_cvgpccoverages', 'tb_cvgpccoverages.id', '=', 'policy_coverage_detail.coverage_id')
                            ->whereNull('policy_coverage_detail.deleted_at')
                            ->where('policy_coverage_id',$policy->sonali)
                            ->sum('coverage_value');
                        
                            $policyExtentionDetail = 0;
                            $policyExtentionDetail = DB::table('policy_extention_detail')
                             ->where('policy_coverage_id', $policy->sonali)
                             ->where('s_ParentCoverageID',$policy->tb_cvgpccoverages_id)
                            ->sum('extention_calculated_value');
                            
                            $policySumExtentionDetail = DB::table('policy_extention_detail')
                            ->where('policy_coverage_id',$policy->sonali)
                            ->sum('extention_coverage_value');

                            $FidelityGuaranteeTotal = 0;
                            if($policy->s_ScreenName == 'Fidelity Guarantee')
                            {
                                $FidelityGuaranteeTotal = DB::table('policy_coverages_data')
                                ->where('policy_id',$policy->policyId)
                                ->sum('premium');
                            }
                            
                            
                            if($policy->s_ScreenName != 'Commercial Motor' && $policy->s_ScreenName != 'Personal Motor' && $policy->s_ScreenName != 'Motor Traders External' && $policy->s_ScreenName != 'Motor Traders Internal')
                            {
                                $results[] = [
                                'policyNumber'=>$policy->policyNumber, 
                                'premiumFreq'=>$policy->premiumFreq, 
                                'termStartDate'=>$policy->termStartDate, 
                                'termEndDate'=>$policy->termEndDate,
                                'productName'=>$policy->productName,
                                'insuredName'=>isset($policy->insuredName)?$policy->insuredName:$policy->cName,
                                'transPk'=>$policy->transPk,
                                'transType'=>$policy->transType,
                                'Status'=>$policy->Status,
                                'paymentReference'=>$policy->paymentReference,
                                'gfsPolicyNo'=>$policy->gfsPolicyNo,
                                'agenciesName'=>$policy->agenciesName,
                                'agentName'=>$policy->agentName,
                                'riskId'=>$policy->riskId,
                                'riskName'=>str_replace(',', ' ',$policy->riskName),
                                'motorDesc'=>'',
                                'motorMake'=>'',
                                'motorPK'=>'',
                                'bookingDate'=>$policy->bookingDate,
                                'policyPremium'=>$policy->bookingDate,
                                'totalFinalPre'=>($calculatedValue + $SpecifiedCoveragesItemsData + $FidelityGuaranteeTotal + $policyExtentionDetail ),
                                'policyId'=>$policy->policyId,
                                'policyCover'=>$policy->s_ScreenName,
                                'sumInsuredValue'=>($sumInsuredValue + $SpecifiedsumInsured + $policySumExtentionDetail)] ;
                            } 
                        
                        if($policy->s_ScreenName == 'Personal Motor')
                        {
                            $policiesPersonalMotor=DB::table('policy_coverages')
                            ->join('motor', 'motor.policy_coverage_id', '=', 'policy_coverages.id')
                            ->join('risk_address', 'risk_address.id', '=', 'policy_coverages.risk_address_id')
                            ->join('tb_cvgpccoverages', 'tb_cvgpccoverages.id', '=', 'policy_coverages.coverage_id')
                            ->where('policy_coverages.policy_id', '=', $policy->policyId)
                            ->where('policy_coverages.coverage_id',27)
                            ->where('policy_coverages.row_type','NEW')
                            ->where('policy_coverages.id',$policy->policy_coverages_id)
                            ->whereNull('motor.deleted_at')
                            ->whereNull('policy_coverages.deleted_at')
                            ->select(array('motor.registration_no as motorDesc',
                                    'motor.make as motorMake',
                                    'motor.id as motorPK',
                                    'motor.calculated_value_main as calculated_value_main',
                                    'motor.coverage_value_main as coverage_value_main',
                                    'motor.coverage_value as coverage_value',
                                    'tb_cvgpccoverages.s_ScreenName'))
                            ->get()->toArray();
                            if(count($policiesPersonalMotor) > 0 )
                            {
                                foreach($policiesPersonalMotor as $cKey => $cValue)
                                {
                                
                                    $results[] = [
                                        'policyNumber'=>$policy->policyNumber, 
                                        'premiumFreq'=>$policy->premiumFreq, 
                                        'termStartDate'=>$policy->termStartDate, 
                                        'termEndDate'=>$policy->termEndDate,
                                        'productName'=>$policy->productName,
                                        'insuredName'=>isset($policy->insuredName)?$policy->insuredName:$policy->cName,
                                        'transPk'=>$policy->transPk,
                                        'transType'=>$policy->transType,
                                        'Status'=>$policy->Status,
                                        'paymentReference'=>$policy->paymentReference,
                                        'gfsPolicyNo'=>$policy->gfsPolicyNo,
                                        'agenciesName'=>$policy->agenciesName,
                                        'agentName'=>$policy->agentName,
                                        'riskId'=>$policy->riskId,
                                        'riskName'=>str_replace(',', ' ',$policy->riskName),
                                        'motorDesc'=>$cValue->motorDesc,
                                        'motorMake'=>$cValue->motorMake,
                                        'motorPK'=>$cValue->motorPK,
                                        'bookingDate'=>$policy->bookingDate,
                                        'policyPremium'=>$policy->bookingDate,
                                        'totalFinalPre'=>$cValue->calculated_value_main + $SpecifiedsumInsured,
                                        'policyCover'=>$cValue->s_ScreenName,
                                        'sumInsuredValue'=>$cValue->coverage_value] ;
                                        //$results = array_map("unserialize", array_unique(array_map("serialize", $results)));
                                }
                            }
                        }
                        if($policy->s_ScreenName == 'Commercial Motor')
                        {
                            $policiesCommercialMotor=DB::table('policy_coverages')
                            ->join('motor', 'motor.policy_coverage_id', '=', 'policy_coverages.id')
                            ->join('risk_address', 'risk_address.id', '=', 'policy_coverages.risk_address_id')
                            ->join('tb_cvgpccoverages', 'tb_cvgpccoverages.id', '=', 'policy_coverages.coverage_id')
                            ->where('policy_coverages.policy_id', '=', $policy->policyId)
                            ->where('motor.policy_coverage_id', '=', $policy->sonali)
                            ->where('policy_coverages.coverage_id',22)
                            ->whereNull('policy_coverages.deleted_at')
                            ->whereNull('motor.deleted_at')
                            ->select(array('motor.registration_no as motorDesc',
                                    'motor.make as motorMake',
                                    'motor.id as motorPK',
                                    'motor.calculated_value_main as calculated_value_main',
                                    'motor.calculated_value as calculated_value',
                                    
                                    'motor.coverage_value_main as coverage_value_main',
                                    'motor.coverage_value as coverage_value',
                                    'tb_cvgpccoverages.s_ScreenName'))
                            ->get()->toArray();

                            if(count($policiesCommercialMotor) > 0 )
                            {
                                foreach($policiesCommercialMotor as $cKey => $cValue)
                                {
                                        if($cValue->calculated_value_main == 0){
                                            $calculated_value =    $cValue->calculated_value;
                                        }else{
                                            $calculated_value =    $cValue->calculated_value_main;
                                        }
                                    $results[] = [
                                        'policyNumber'=>$policy->policyNumber, 
                                        'premiumFreq'=>$policy->premiumFreq, 
                                        'termStartDate'=>$policy->termStartDate, 
                                        'termEndDate'=>$policy->termEndDate,
                                        'productName'=>$policy->productName,
                                        'insuredName'=>isset($policy->insuredName)?$policy->insuredName:$policy->cName,
                                        'transPk'=>$policy->transPk,
                                        'transType'=>$policy->transType,
                                        'Status'=>$policy->Status,
                                        'paymentReference'=>$policy->paymentReference,
                                        'gfsPolicyNo'=>$policy->gfsPolicyNo,
                                        'agenciesName'=>$policy->agenciesName,
                                        'agentName'=>$policy->agentName,
                                        'riskId'=>$policy->riskId,
                                        'riskName'=>str_replace(',', ' ',$policy->riskName),
                                        'motorDesc'=>$cValue->motorDesc,
                                        'motorMake'=>$cValue->motorMake,
                                        'motorPK'=>$cValue->motorPK,
                                        'bookingDate'=>$policy->bookingDate,
                                        'policyPremium'=>$policy->bookingDate,
                                        'totalFinalPre'=>$calculated_value, //+ $SpecifiedsumInsured,
                                        'policyCover'=>$cValue->s_ScreenName,
                                        'sumInsuredValue'=>$cValue->coverage_value] ;
                                         //$results = array_map("unserialize", array_unique(array_map("serialize", $results)));
                                }
                                
                            }
                        }
                        if($policy->s_ScreenName == 'Motor Traders External'){
                            $policiesMotorTradersExternal=DB::table('motor_traders')
                            ->join('policy_coverages', 'policy_coverages.id', '=', 'motor_traders.policy_coverage_id')
                            # ->leftjoin('policy_specified_items', 'policy_specified_items.policy_coverage_id', '=', 'policy_coverages.id')
                            ->join('tb_cvgpccoverages', 'tb_cvgpccoverages.id', '=', 'policy_coverages.coverage_id')
                            ->where('policy_coverages.policy_id', '=', $policy->policyId)
                            ->where('policy_coverages.coverage_id', '=',15)
                            ->whereNull('policy_coverages.deleted_at')
                            ->select(array('tb_cvgpccoverages.s_ScreenName',
                            DB::raw('SUM(loss_or_damage_coverage_value + third_party_liability_coverage_value + medical_benefits_coverage_value) as sumInsured'),
                            DB::raw('SUM(loss_or_damage_calculated_value+third_party_liability_calculated_value+
                                    medical_benefits_calculated_value +                                    
                                    vehicle_lent_hire_calculated_value +
                                    social_domestic_pleasure_calculated_value +
                                    unauthoried_use_calculated_value +
                                    windscreen_calculated_value +
                                    contigent_liability_calculated_value +
                                    wreckage_removal_calculated_value +
                                    Loss_of_use_of_customer_calculated_value +
                                    loss_of_key_calculated_value +
                                    motor_cycle_motor_tricycle_calculated_value +
                                    special_type_vehicle_calculated_value +
                                    passanger_liability_respect_of_motor_calculated_value) as motor_traders_total_sum')))
                            ->get()->toArray();
                            if(count($policiesMotorTradersExternal) > 0)
                            {
                                foreach($policiesMotorTradersExternal as $cKey => $cValue)
                                {
                                        $results[] = [
                                        'policyNumber'=>$policy->policyNumber, 
                                        'premiumFreq'=>$policy->premiumFreq, 
                                        'termStartDate'=>$policy->termStartDate, 
                                        'termEndDate'=>$policy->termEndDate,
                                        'productName'=>$policy->productName,
                                        'insuredName'=>isset($policy->insuredName)?$policy->insuredName:$policy->cName,
                                        'transPk'=>$policy->transPk,
                                        'transType'=>$policy->transType,
                                        'Status'=>$policy->Status,
                                        'paymentReference'=>$policy->paymentReference,
                                        'gfsPolicyNo'=>$policy->gfsPolicyNo,
                                        'agenciesName'=>$policy->agenciesName,
                                        'agentName'=>$policy->agentName,
                                        'riskId'=>$policy->riskId,
                                        'riskName'=>str_replace(',', ' ',$policy->riskName),
                                        'motorDesc'=>'',
                                        'motorMake'=>'',
                                        'motorPK'=>'',
                                        'bookingDate'=>$policy->bookingDate,
                                        'policyPremium'=>$policy->bookingDate,
                                        'totalFinalPre'=>$cValue->motor_traders_total_sum + $SpecifiedCoveragesItemsData,
                                        'policyCover'=>$cValue->s_ScreenName,
                                        'sumInsuredValue'=>$cValue->sumInsured + $SpecifiedsumInsured] ;
                                        //$results = array_map("unserialize", array_unique(array_map("serialize", $results)));
                                }
                            }
                        }
                        if($policy->s_ScreenName == 'Motor Traders Internal')
                        {
                            $policiesMotorTradersInternal=DB::table('motor_traders_internal')
                            ->join('policy_coverages', 'policy_coverages.id', '=', 'motor_traders_internal.policy_coverage_id')
                            # ->leftjoin('policy_specified_items', 'policy_specified_items.policy_coverage_id', '=', 'policy_coverages.id')
                            ->join('tb_cvgpccoverages', 'tb_cvgpccoverages.id', '=', 'policy_coverages.coverage_id')
                            ->where('policy_coverages.policy_id', '=', $policy->policyId)
                            ->where('policy_coverages.coverage_id', '=',16)
                            ->whereNull('policy_coverages.deleted_at')
                            ->groupBy('type_of_cover')
                            ->select(array('tb_cvgpccoverages.s_ScreenName','loss_or_damage_coverage_value',
                            DB::raw('SUM(loss_or_damage_coverage_value + third_party_liability_coverage_value + medical_benefits_coverage_value) as sumInsured'),
                            DB::raw('SUM(loss_or_damage_calculated_value + third_party_liability_calculated_value +
                                    medical_benefits_calculated_value +                                    
                                    vehicle_lent_hire_calculated_value +
                                    social_domestic_pleasure_calculated_value +
                                    unauthoried_use_calculated_value +
                                    windscreen_calculated_value +
                                    contigent_liability_calculated_value +
                                    wreckage_removal_calculated_value +
                                    Loss_of_use_of_customer_calculated_value +
                                    loss_of_key_calculated_value +
                                    motor_cycle_motor_tricycle_calculated_value +
                                    special_type_vehicle_calculated_value +
                                    passanger_liability_respect_of_motor_calculated_value) as motor_traders_total_sum')))
                            ->get()->toArray();
                           
                            if(count($policiesMotorTradersInternal) > 0)
                            {
                                foreach($policiesMotorTradersInternal as $cKey => $cValue)
                                {

                                    $results[] = [
                                        'policyNumber'=>$policy->policyNumber, 
                                        'premiumFreq'=>$policy->premiumFreq, 
                                        'termStartDate'=>$policy->termStartDate, 
                                        'termEndDate'=>$policy->termEndDate,
                                        'productName'=>$policy->productName,
                                        'insuredName'=>isset($policy->insuredName)?$policy->insuredName:$policy->cName,
                                        'transPk'=>$policy->transPk,
                                        'transType'=>$policy->transType,
                                        'Status'=>$policy->Status,
                                        'paymentReference'=>$policy->paymentReference,
                                        'gfsPolicyNo'=>$policy->gfsPolicyNo,
                                        'agenciesName'=>$policy->agenciesName,
                                        'agentName'=>$policy->agentName,
                                        'riskId'=>$policy->riskId,
                                        'riskName'=>str_replace(',', ' ',$policy->riskName),
                                        'motorDesc'=>'',
                                        'motorMake'=>'',
                                        'motorPK'=>'',
                                        'bookingDate'=>$policy->bookingDate,
                                        'policyPremium'=>$policy->bookingDate,
                                        'totalFinalPre'=>$cValue->motor_traders_total_sum + $SpecifiedCoveragesItemsData,
                                        'policyCover'=>$cValue->s_ScreenName,
                                        'sumInsuredValue'=>$cValue->sumInsured + $SpecifiedsumInsured] ;
                                        //$results = array_map("unserialize", array_unique(array_map("serialize", $results)));
                                }
                            }

                        }
            }
        }
       
        $pages = "POLICY NO,Term Start Date,Term End Date ,PRODUCT,Policy Frequency,Policy Premium,Policy Sum Insured,Policy Coverages,INSURED NAME,TRANS PK,TRANS TYPE,STATUS,REFERENCE NUMBER,AGENCY,AGENT,RIKS PK,RISK NAME,MOTOR DESC,MOTOR MAKE,MOTOR PK,BOOKING DATE\n";
        foreach ($results as $where) {
            $pages .="{$where['policyNumber']},{$where['termStartDate']},{$where['termEndDate']},{$where['productName']},{$where['premiumFreq']},{$where['totalFinalPre']},{$where['sumInsuredValue']},{$where['policyCover']},{$where['insuredName']},{$where['transPk']},{$where['transType']},{$where['Status']},{$where['gfsPolicyNo']},{$where['agenciesName']},{$where['agentName']},{$where['riskId']},{$where['riskName']},{$where['motorDesc']},{$where['motorMake']},{$where['motorPK']},{$where['bookingDate']},\n"; 
        } 

        $date = \Carbon\Carbon::now()->timestamp;
        $path = 'MIS-'.$date.'_report.csv';
        Storage::disk('s3')->put($path, $pages, 'public');
       //dd($path);
        $attachments = array();
        array_push($attachments, $path);
        
        // Update the report record with the path
        if ($misReportRecord) {
            $misReportRecord->report_path = $path;
            $misReportRecord->status = 'completed';
            $misReportRecord->save();
        }
        
        ////*************Email send new fuction **************/////
        if ($userEmail && $misReportRecord) {
            // Send email to the specific user
            $data = new \stdClass();
            $data->user_id = $authId;
            $data->hook = 'get_mis_report';
            $data->customer_id = null;
            $data->attachment = $attachments;
            $emailTemplate = EmailBroadcasting::where('hook_slug', $data->hook)->first(array('subject'));
            if ($emailTemplate) {
                $markdown = new MailTemplate($data);
                $html = $markdown->render('Mail.mailTemplate',['data'=>$data]);
                event(new \AlphaDirect\Events\SendMail($userEmail, $emailTemplate->subject, "", $html, $attachments, ['hook' => $data->hook]));
            }
        } else {
            // Use default cron email system
            $cronSendMail = new CronController();
            $hook = 'get_mis_report';
            $cronSendMail->AllCronMail($attachments,$hook,$cron);
        }
        ////*************Email send new fuction END **************///// 
        $cron->end = Carbon::now();
        $cron->save();
                

    }

    private function getExtensionsByType($coverageId, $extensionType)
    {
        $query = "CAST(n_DisplaySequence AS UNSIGNED ) asc";
        return PolicyExtentionDetails::where('policy_coverage_id', $coverageId)
            ->where('type', $extensionType)
            ->orderByRaw( $query)
            ->get();
    }

    public static function getProductCov($product_id)
    {
        if ($product_id == 7) {
            return ProductCoverage::join('tb_cvgpccoverages','product_coverage.coverage_id','tb_cvgpccoverages.id')
                                ->where('product_coverage.product_id',$product_id)
                                ->where('tb_cvgpccoverages.s_UsageType', 'PARENT')
                                ->where('tb_cvgpccoverages.s_DISPLAYTOUSER','=','1')
                                // ->orderBy('tb_cvgpccoverages.n_DisplaySequence','asc')
                                ->orderby('tb_cvgpccoverages.id', 'asc' )
                                ->select('tb_cvgpccoverages.*','product_coverage.product_id')
                                ->get();
        } elseif ($product_id == 8) {
            return ProductCoverage::join('tb_cvgpccoverages','product_coverage.coverage_id','tb_cvgpccoverages.id')
                                ->where('product_coverage.product_id',$product_id)
                                ->where('tb_cvgpccoverages.s_UsageType', 'PARENT')
                                ->where('tb_cvgpccoverages.s_DISPLAYTOUSER','=','1')
                                ->orderBy('tb_cvgpccoverages.n_DisplaySequence','asc')
                                // ->orderby('tb_cvgpccoverages.id', 'asc' )
                                ->select('tb_cvgpccoverages.*','product_coverage.product_id')
                                ->get();
        }

    }
}
