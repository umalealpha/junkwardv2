<?php

namespace AlphaDirect\Console\Commands;
use AlphaDirect\Models\CronStatus;
use Illuminate\Console\Command;
use AlphaDirect\Customer;
use Carbon\Carbon;
use Log;
use DB;
use AlphaDirect\Mail\MailTemplate;
use AlphaDirect\EmailBroadcasting;
use AlphaDirect\Models\ProductCoverage;
use AlphaDirect\Models\PolicyCoverage;
use AlphaDirect\Models\MisReportCalculation;
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

class getMISReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'getMISReport';

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

        $columns = array('POLICY NO','Policy Frequency' ,'Term Start Date','Term End Date ','PRODUCT' ,'INSURED NAME', 'TRANS PK', 'TRANS TYPE','REFERENCE NUMBER','AGENCY','AGENT','RIKS PK','RISK NAME','MOTOR DESC','MOTOR MAKE','MOTOR PK','BOOKING DATE');
        // fputcsv($file, $columns);
        $results = array();
       $misReportData =  DB::select('CALL tempgetMISreportDOMCOM(?, ?)', ['2024-07-01', '2025-12-28']);
        //$misReportData = collect(DB::select('call getMISreportDOMCOM()'))->where('policyId', 101732);
    // dd($misReportData);
        $totalPreData = $coverId = $resultDataNewMotor = [];
        if (count($misReportData)>0) {
            foreach($misReportData as $policy)
            {
                            $sumInsuredValue=0;
                         
                              
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
                            ->where('policy_coverage_id', $policy->sonali)
                            ->whereNull('deleted_at')
                            ->sum('calculated_value');
                           // dd($SpecifiedCoveragesItemsData);

                        $SpecifiedsumInsured = DB::table('policy_specified_items')
                            ->where('policy_coverage_id', $policy->sonali)
                            ->whereNull('deleted_at')
                            ->sum('sum_insured');



                        $calculatedValue = DB::table('policy_coverage_detail')
                            ->join('tb_cvgpccoverages', 'tb_cvgpccoverages.id', '=', 'policy_coverage_detail.coverage_id')
                            ->where('policy_coverage_id', $policy->sonali)
                            ->whereNull('policy_coverage_detail.deleted_at')
                            ->sum('policy_coverage_detail.calculated_value');
                        //dd($policy->sonali,$calculatedValue);
 
                        $sumInsuredValue = DB::table('policy_coverage_detail')
                        ->join('tb_cvgpccoverages', 'tb_cvgpccoverages.id', '=', 'policy_coverage_detail.coverage_id')
                        ->whereNull('policy_coverage_detail.deleted_at')
                        ->where('policy_coverage_id', $policy->sonali)
                        ->sum(DB::raw("
                            COALESCE(policy_coverage_detail.coverage_value, 0)
                        + COALESCE(policy_coverage_detail.ratefactor_value, 0)
                        + COALESCE(
                                CAST(REPLACE(policy_coverage_detail.ratefactor_AnnualWages, ',', '') AS DECIMAL(20,2)),
                                0
                            )
                        "));
                             $sumInsuredValuedata=0;
                            if($policy->coverage_id==9)
                            {
                              
                               $sumInsuredValuedata = DB::table('policy_coverages_data')
                           // ->join('tb_cvgpccoverages', 'tb_cvgpccoverages.id', '=', 'policy_coverages_data.policyCoverageID')
                            //->whereNull('policy_coverages_data.deleted_at')
                            ->where('policyCoverageID',$policy->sonali)
                            ->sum('amount_to_be_guaranteed');
                            
                            }
                           
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
                            
                                   
                            if($policy->s_ScreenName != 'Public Liability' && $policy->s_ScreenName != 'Defective Workmanship' && $policy->s_ScreenName != 'Commercial Motor' && $policy->s_ScreenName != 'Personal Motor' && $policy->s_ScreenName != 'Motor Traders Internal'  && $policy->s_ScreenName != 'Motor Traders External' && $policy->s_ScreenName != 'Workers Compensation' && $policy->s_ScreenName != 'Personal Accident')
                            {
                                

                                $netr=0;
                                $QUOTA=0;
                                $qscPrem=0;
                                $surplusValue=0;
                                $facaValue=0;
                                $fpValue=0;
                                $nrPrem=0;
                                $qPrem=0;
                                $surplusPrem=0;
                                $facaPrem=0;
                                $fpPrem=0;
                                $QSCL=0;
                                $AFCL=0;
                                $SCL=0;
                                $TCL=0;
                                $qscValue=0;
                                       
                               // added by snehal on 1-10-25
                                //$new_result= self::reinsurance($policy->s_CoverageCode);
                                $totalFinalPre_old=((float)$calculatedValue + (float)$SpecifiedCoveragesItemsData + (float)$FidelityGuaranteeTotal + (float)$policyExtentionDetail );
                                $totalFinalPre=((float)$calculatedValue + (float)$SpecifiedCoveragesItemsData +(float)$FidelityGuaranteeTotal  );
                                // if($policy->s_ScreenName == 'Fidelity Guarantee')
                                // {
                                //     dd($calculatedValue,(float)$SpecifiedCoveragesItemsData,$FidelityGuaranteeTotal,$totalFinalPre);
                                // }
                               // dd($sumInsuredValue,$SpecifiedsumInsured,$policy->s_ScreenName,$policy->policyId);
                               //cehck for extenstion deatils needs to add or not 
                                $sumInsuredValue_1=((float)$sumInsuredValue + (float)$SpecifiedsumInsured + (float)$sumInsuredValuedata+(float)$policySumExtentionDetail);
                           $sumInsuredValue_new=((float)$sumInsuredValue + (float)$SpecifiedsumInsured + (float)$sumInsuredValuedata);
                               
                           //misc_com group 
                                // if (in_array(strtoupper($policy->s_ScreenName),
                                // ['OFFICECONTENTS', 'THEFT', 'MONEY', 'GLASS', 'BUSINESSALLRISKS']))
                                // {
                                //         $SCL =0;
                                //         $AFCL =  (float)2000000;
                                //         $QSCL = (float)1000000;
                                //         $TCL = (float)3000000;
                                // }
                                // elseif (in_array(strtoupper($policy->s_ScreenName),
                                //     [
                                //         'FIRE',
                                //         'BUILDINGS COMBINED',
                                //     //  'OFFICE CONTENTS',  // check for officce content with sonali 
                                //         'BUSINESS INTERRUPTION',
                                //         'ACCOUNTS RECEIVABLE',
                                //         'HOUSE HOLDERS',
                                //         'HOUSE OWNERS'
                                //     ]
                                // )){
                                //     $SCL = (float)40000000;
                                //     $AFCL = (float)50000000;
                                //     $QSCL = (float)10000000;
                                //     $TCL = (float)100000000;
                                // }
                                // elseif (in_array(strtoupper($policy->s_ScreenName),['ACCIDENTAL DAMAGE']))
                                //     {
                                //         $SCL = 0;
                                //         $AFCL = 0;
                                //         $QSCL = (float)7500000;
                                //         $TCL = (float)7500000;
                                // }
                                //   elseif (in_array(strtoupper($policy->s_ScreenName),['ELECTRONIC EQUIPMENT']))
                                //     {
                                    
                                //         $SCL = 0;
                                //         $AFCL = 0;
                                //         $QSCL = (float)6000000;
                                //         $TCL = (float)6000000;
                                // }
                                //    elseif (in_array(strtoupper($policy->s_ScreenName),['FIDELITY GUARANTEE']))
                                //     {
                                //         $data = MisReportCalculation::where('s_ScreenName', $policy->s_ScreenName)->first();
                                //         // $SCL = 0;
                                //         // $AFCL = 0;
                                //         // $QSCL = (float)1000000;
                                //         // $TCL = (float)1000000;
                                //         $SCL = (float)$data->SCL;
                                //         $AFCL = (float)$data->AFCL;
                                //         $QSCL = (float)$data->QSCL;
                                //         $TCL = (float)$data->TCL;
                                // }
                                 $data = MisReportCalculation::where('s_ScreenName', $policy->s_ScreenName)->first();
                                        // $SCL = 0;
                                        // $AFCL = 0;
                                        // $QSCL = (float)1000000;
                                        // $TCL = (float)1000000;
                                        $SCL = (float)$data->SCL;
                                        $AFCL = (float)$data->AFCL;
                                        $QSCL = (float)$data->QSCL;
                                        $TCL = (float)$data->TCL;
                                // Calculate QSC value once and store it
                                $qscValue = self :: QSC($sumInsuredValue_new, $QSCL);
                                  //  dd($qscValue);
                                $netr=self:: NR($qscValue);
                                $QUOTA=self:: Q($qscValue);
                                $qscPrem = self ::calculatePQSC($sumInsuredValue_new, $QSCL, $totalFinalPre, $qscValue);
                                $surplusValue = self ::Surplus($sumInsuredValue_new, $QSCL, $SCL, $qscValue);
                                $facaValue = self ::FACA($sumInsuredValue_new, $QSCL, $SCL, $TCL, $AFCL);
                                 $fpValue = self ::FP($sumInsuredValue_new, $TCL);
                                 // Calculate premium allocations
                                $nrPrem = self ::calculateNR($qscPrem);
                                $qPrem = self ::calculateQ($qscPrem);
                               
                                $surplusPrem = self ::calculateSurplusPremium($totalFinalPre, $surplusValue, $sumInsuredValue_new);
                                $facaPrem = self ::calculateFACAPremium($totalFinalPre, $facaValue, $sumInsuredValue_new);
                                $fpPrem = self ::calculateFPPremium($totalFinalPre, $fpValue, $sumInsuredValue_new);
                        
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
                                'coverageCode'=>$policy->s_CoverageCode,
                                'policyPremium'=>$policy->bookingDate,
                                //'totalFinalPre'=>($calculatedValue + $SpecifiedCoveragesItemsData + $FidelityGuaranteeTotal + $policyExtentionDetail ),
                                'totalFinalPre'=>($calculatedValue + $SpecifiedCoveragesItemsData + $FidelityGuaranteeTotal + $policyExtentionDetail ),
                                'policyId'=>$policy->policyId,
                                'policyCover'=>$policy->s_ScreenName,
                                'sumInsuredValue'=>($sumInsuredValue_1),
                                'sumInsuredValue1'=>($sumInsuredValue_new),
                                'totalFinalPre'=>$totalFinalPre,
                                'NETRETENTION_SI'=>$netr,
                                'NetRetention'=>$nrPrem,
                                'QUOTASHARING_SI'=>$QUOTA, //---------
                                'QUOTASHARING'=>$qPrem,
                                'SURPLUS_SI'=>$surplusValue,
                                'SURPLUS'=>$surplusPrem,
                                'AUTO_FAC_SI'=>$facaValue,
                                'AUTO_FAC'=>$facaPrem,
                                'FACULTATIVE_SI'=>$fpValue,
                                'FACULTATIVE'=>$fpPrem];

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
                                    
                                    
                                    $TCL = (float)6500000;
                                    $QSCL = (float)5000000;
                                    $SCL = (float)0;
                                    $AFCL = (float)1500000;
                                    $ADlimit=(float)300000;
                                    // Calculate QSC value once and store it
                                    $sumInsuredValue = (float)$cValue->coverage_value;
                                    $totalFinalPre=$cValue->calculated_value_main + $SpecifiedsumInsured;

                                    $qscValue = (float)self :: QSC($sumInsuredValue, $QSCL);
                                    $netrmotorcom=self:: NRmotorcom($qscValue,$ADlimit);
                                    $QUOTAmotorcom=self:: Qmotorcom($qscValue);

                                    
                                    $qscPremmotorcom = self ::calculatePQSC($sumInsuredValue, $QSCL, $totalFinalPre, $qscValue);
                                    // IF(SI > QSCL, IF(SI <= SCL, SI - QSC, SCL),0)
                                    $surplusValuemotorcom = self ::Surplusmotorcom($sumInsuredValue,$QSCL,$SCL,$qscValue);
                                
                                    $facaValuemotorcom = self ::FACAmotorcom($sumInsuredValue,$netrmotorcom,$QUOTAmotorcom, $SCL, $TCL, $AFCL);
                                    $fpValuemotorcom = self ::FPmotorcom($sumInsuredValue, $TCL,$qscValue,$netrmotorcom,$QUOTAmotorcom);
                                //    dd($fpValuemotorcom);
                                    /// calculate percent for premium
                                    $NRpercentmoto=(float)($netrmotorcom/$sumInsuredValue)*100;
                                    $QUOTApercentmoto=(float)($QUOTAmotorcom/$sumInsuredValue)*100;
                                    $surpluspercentmoto=(float)($surplusValuemotorcom/$sumInsuredValue)*100;
                                    $facaValuemotorcom=(float)($facaValuemotorcom/$sumInsuredValue)*100;
                                    $fpValuemotorcom=(float)($fpValuemotorcom/$sumInsuredValue)*100;


                                    // Calculate premium allocations
                                    $nrPremmotorcom = self ::calculatepremotorcom($totalFinalPre,$NRpercentmoto);
                                    $qPremmotorcom = self ::calculatepremotorcom($totalFinalPre,$QUOTApercentmoto);
                                    $surplusPremmotorcom = self ::calculatepremotorcom($totalFinalPre, $surpluspercentmoto);
                                    $facaPremmotorcom = self ::calculatepremotorcom($totalFinalPre, $facaValuemotorcom);
                                    $fpPremmotorcom = self ::calculatepremotorcom($totalFinalPre, $fpValuemotorcom);
                           
                           
                                  
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
                                        'coverageCode'=>$policy->s_CoverageCode,
                                        'policyPremium'=>$policy->bookingDate,
                                        'totalFinalPre'=>$cValue->calculated_value_main + $SpecifiedsumInsured,
                                        'policyCover'=>$cValue->s_ScreenName,
                                        'sumInsuredValue'=>$cValue->coverage_value,
                                        //added by snehal on 3-10-25
                                        'sumInsuredValue1'=>$cValue->coverage_value,
                                        'totalFinalPre'=>$totalFinalPre,
                                        'NETRETENTION_SI'=>$netrmotorcom,
                                        'NetRetention'=>$nrPremmotorcom,
                                        'QUOTASHARING_SI'=>$QUOTAmotorcom, //---------
                                        'QUOTASHARING'=>$qPremmotorcom,
                                        'SURPLUS_SI'=>$surplusValuemotorcom,
                                        'SURPLUS'=>$surplusPremmotorcom,
                                        'AUTO_FAC_SI'=>$facaValuemotorcom,
                                        'AUTO_FAC'=>$facaPremmotorcom,
                                        'FACULTATIVE_SI'=>$fpValuemotorcom,
                                        'FACULTATIVE'=>$fpPremmotorcom];
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
                            ->select(array('motor.registration_no as motorDesc',
                                    'motor.make as motorMake',
                                    'motor.id as motorPK',
                                    'motor.calculated_value_main as calculated_value_main',
                                    'motor.coverage_value_main as coverage_value_main',
                                    'motor.coverage_value as coverage_value',
                                    'tb_cvgpccoverages.s_ScreenName'))
                            ->get()->toArray();

                            if(count($policiesCommercialMotor) > 0 )
                            {
                                 $TCL = (float)6500000;
                                    $QSCL = (float)5000000;
                                    $SCL = (float)0;
                                    $AFCL = (float)1500000;
                                    $ADlimit=(float)300000;
                                foreach($policiesCommercialMotor as $cKey => $cValue)
                                {
                                
                                   
                                    // Calculate QSC value once and store it
                                    // $sumInsuredValue = (float)$cValue->coverage_value;
                                    // $totalFinalPre= (float)$cValue->calculated_value_main;
  
                                    $sumInsuredValue = (float)$cValue->coverage_value+(float)$SpecifiedsumInsured;
                                    $totalFinalPre= (float)$cValue->calculated_value_main+(float)$SpecifiedCoveragesItemsData;
              
                                    $qscValue = (float)self :: QSC($sumInsuredValue, $QSCL);
                                    $netrmotorcom=self:: NRmotorcom($qscValue,$ADlimit);
                                    $QUOTAmotorcom=self:: Qmotorcom($qscValue);

                                    
                                    $qscPremmotorcom = self ::calculatePQSC($sumInsuredValue, $QSCL, $totalFinalPre, $qscValue);
                                    // IF(SI > QSCL, IF(SI <= SCL, SI - QSC, SCL),0)
                                    $surplusValuemotorcom = self ::Surplusmotorcom($sumInsuredValue,$QSCL,$SCL,$qscValue);
                                
                                    $facaValuemotorcom = self ::FACAmotorcom($sumInsuredValue,$netrmotorcom,$QUOTAmotorcom, $SCL, $TCL, $AFCL);
                                    $fpValuemotorcom = self ::FPmotorcom($sumInsuredValue, $TCL,$qscValue,$netrmotorcom,$QUOTAmotorcom);
                                   
                                    /// clculate percent for premium
                                    $NRpercentmoto=(float)($netrmotorcom/$sumInsuredValue)*100;
                                    $QUOTApercentmoto=(float)($QUOTAmotorcom/$sumInsuredValue)*100;
                                    $surpluspercentmoto=(float)($surplusValuemotorcom/$sumInsuredValue)*100;
                                    $facaValuepermotorcom=(float)($facaValuemotorcom/$sumInsuredValue)*100;
                                    $fpValuemotorcom=(float)($fpValuemotorcom/$sumInsuredValue)*100;


                                    // Calculate premium allocations
                                    $nrPremmotorcom = self ::calculatepremotorcom($totalFinalPre,$NRpercentmoto);
                                    $qPremmotorcom = self ::calculatepremotorcom($totalFinalPre,$QUOTApercentmoto);
                                    $surplusPremmotorcom = self ::calculatepremotorcom($totalFinalPre, $surpluspercentmoto);
                                    $facaPremmotorcom = self ::calculatepremotorcom($totalFinalPre, $facaValuepermotorcom);
                                    $fpPremmotorcom = self ::calculatepremotorcom($totalFinalPre, $fpValuemotorcom);
                           
                                
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
                                        'coverageCode'=>$policy->s_CoverageCode,
                                        'policyPremium'=>$policy->bookingDate,
                                        'totalFinalPre'=>$totalFinalPre, //+ $SpecifiedsumInsured,
                                        'policyCover'=>$cValue->s_ScreenName,
                                        'sumInsuredValue'=>$sumInsuredValue,
                                        //added by snehal on 3-10-25
                                        'sumInsuredValue1'=>$sumInsuredValue,
                                        'totalFinalPre'=>$totalFinalPre, 
                                        'NETRETENTION_SI'=>$netrmotorcom,
                                        'NetRetention'=>$nrPremmotorcom,
                                        'QUOTASHARING_SI'=>$QUOTAmotorcom, //---------
                                        'QUOTASHARING'=>$qPremmotorcom,
                                        'SURPLUS_SI'=>$surplusValuemotorcom,
                                        'SURPLUS'=>$surplusPremmotorcom,
                                        'AUTO_FAC_SI'=>$facaValuemotorcom,
                                        'AUTO_FAC'=>$facaPremmotorcom,
                                        'FACULTATIVE_SI'=>$fpValuemotorcom,
                                        'FACULTATIVE'=>$fpPremmotorcom];
                                         //$results = array_map("unserialize", array_unique(array_map("serialize", $results)));
                                }
                                
                            }
                        }
                        if($policy->s_ScreenName == 'Motor Traders External'){
                           
                             $policiesMotorTradersExternal=DB::table('motor_traders')
                            ->join('policy_coverages', 'policy_coverages.id', '=', 'motor_traders.policy_coverage_id')
                            ->leftjoin('policy_specified_items', 'policy_specified_items.policy_coverage_id', '=', 'policy_coverages.id')
                            ->join('tb_cvgpccoverages', 'tb_cvgpccoverages.id', '=', 'policy_coverages.coverage_id')
                            ->where('policy_coverages.policy_id', '=', $policy->policyId)
                            ->where('policy_coverages.coverage_id', '=',15)
                            ->whereNull('policy_coverages.deleted_at')
                            // ->whereNull('policy_specified_items.deleted_at')
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
                // $policiesMotorTradersExternal = DB::table('motor_traders as mt')
                //     ->join('policy_coverages as pc', 'pc.id', '=', 'mt.policy_coverage_id')

                //     // ✅ LEFT JOIN with deleted condition INSIDE JOIN
                //     ->leftJoin('policy_specified_items as psi', function ($join) {
                //         $join->on('psi.policy_coverage_id', '=', 'pc.id')
                //              ->whereNull('psi.deleted_at');
                //     })

                //     ->join('tb_cvgpccoverages as cvg', 'cvg.id', '=', 'pc.coverage_id')

                //     ->where('pc.policy_id', $policy->policyId)
                //     ->where('pc.coverage_id', 15)
                //     ->whereNull('pc.deleted_at')

                //     ->select(
                //         'cvg.s_ScreenName',

                //         DB::raw(
                //             'SUM(
                //                 mt.loss_or_damage_coverage_value +
                //                 mt.third_party_liability_coverage_value +
                //                 mt.medical_benefits_coverage_value
                //             ) AS sumInsured'
                //         ),

                //         DB::raw(
                //             'SUM(
                //                 mt.loss_or_damage_calculated_value +
                //                 mt.third_party_liability_calculated_value +
                //                 mt.medical_benefits_calculated_value +
                //                 mt.vehicle_lent_hire_calculated_value +
                //                 mt.social_domestic_pleasure_calculated_value +
                //                 mt.unauthoried_use_calculated_value +
                //                 mt.windscreen_calculated_value +
                //                 mt.contigent_liability_calculated_value +
                //                 mt.wreckage_removal_calculated_value +
                //                 mt.Loss_of_use_of_customer_calculated_value +
                //                 mt.loss_of_key_calculated_value +
                //                 mt.motor_cycle_motor_tricycle_calculated_value +
                //                 mt.special_type_vehicle_calculated_value +
                //                 mt.passanger_liability_respect_of_motor_calculated_value
                //             ) AS motor_traders_total_sum'
                //         )
                //     )
                //     ->get()
                //     ->toArray();


                           //   dd($policiesMotorTradersExternal->toSql(), $policiesMotorTradersExternal->getBindings());
                            if(count($policiesMotorTradersExternal) > 0)
                            {
                              
                                $TCL = (float) 11500000.00;
                                $QSCL = (float) 10000000.00;
                                $SCL = (float)0;
                                $AFCL = (float) 1500000.00;
                                $ADlimit=(float)300000;
                             
                                foreach($policiesMotorTradersExternal as $cKey => $cValue)
                                {
                                    //echo "hi";
                                     // Calculate QSC value once and store it
                                  
                                    
                                     $sumInsuredValue = (float)$cValue->sumInsured+(float)$SpecifiedsumInsured;
                                    $totalFinalPre= (float)$cValue->motor_traders_total_sum+(float)$SpecifiedCoveragesItemsData;
                                // dd($sumInsuredValue,$totalFinalPre);
                                    $qscValue = (float)self :: QSC($sumInsuredValue, $QSCL);
                                    $netrmotorcom=self:: NRmotorcom($qscValue,$ADlimit);
                                    $QUOTAmotorcom=self:: Qmotorcom($qscValue);

                                    
                                    $qscPremmotorcom = self ::calculatePQSC($sumInsuredValue, $QSCL, $totalFinalPre, $qscValue);
                                    // IF(SI > QSCL, IF(SI <= SCL, SI - QSC, SCL),0)
                                    $surplusValuemotorcom = self ::Surplusmotorcom($sumInsuredValue,$QSCL,$SCL,$qscValue);
                                
                                    $facaValuemotorcom = self ::FACAmotorcom($sumInsuredValue,$netrmotorcom,$QUOTAmotorcom, $SCL, $TCL, $AFCL);
                                    $fpValuemotorcom = self ::FPmotorcom($sumInsuredValue, $TCL,$qscValue,$netrmotorcom,$QUOTAmotorcom);
                                //   dd($fpValuemotorcom,$sumInsuredValue);
                                    /// clculate percent for premium
                                    $NRpercentmoto=(float)($netrmotorcom/$sumInsuredValue)*100;
                                    $QUOTApercentmoto=(float)($QUOTAmotorcom/$sumInsuredValue)*100;
                                    $surpluspercentmoto=(float)($surplusValuemotorcom/$sumInsuredValue)*100;
                                    $facaValuepermotorcom=(float)($facaValuemotorcom/$sumInsuredValue)*100;
                                    $fpValuepermotorcom=(float)($fpValuemotorcom/$sumInsuredValue)*100;
                                  
                                    // Calculate premium allocations
                                    $nrPremmotorcom = self ::calculatepremotorcom($totalFinalPre,$NRpercentmoto);
                                    $qPremmotorcom = self ::calculatepremotorcom($totalFinalPre,$QUOTApercentmoto);
                                    $surplusPremmotorcom = self ::calculatepremotorcom($totalFinalPre, $surpluspercentmoto);
                                    $facaPremmotorcom = self ::calculatepremotorcom($totalFinalPre, $facaValuepermotorcom);
                                    $fpPremmotorcom = self ::calculatepremotorcom($totalFinalPre, $fpValuepermotorcom);
                                    
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
                                        'coverageCode'=>$policy->s_CoverageCode,
                                        'policyPremium'=>$policy->bookingDate,
                                        'totalFinalPre'=>$totalFinalPre,
                                        'policyCover'=>$cValue->s_ScreenName,
                                        'sumInsuredValue'=>$sumInsuredValue,
                                        //added by snehal on 3-10-25
                                        'sumInsuredValue1'=>$sumInsuredValue,
                                        'totalFinalPre'=>$totalFinalPre,
                                        'NETRETENTION_SI'=>$netrmotorcom,
                                        'NetRetention'=>$nrPremmotorcom,
                                        'QUOTASHARING_SI'=>$QUOTAmotorcom, //---------
                                        'QUOTASHARING'=>$qPremmotorcom,
                                        'SURPLUS_SI'=>$surplusValuemotorcom,
                                        'SURPLUS'=>$surplusPremmotorcom,
                                        'AUTO_FAC_SI'=>$facaValuemotorcom,
                                        'AUTO_FAC'=>$facaPremmotorcom,
                                        'FACULTATIVE_SI'=>$fpValuemotorcom,
                                        'FACULTATIVE'=>$fpPremmotorcom];

                                        //$results = array_map("unserialize", array_unique(array_map("serialize", $results)));
                                }
                                //dd($results );
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
                          //  dd($policiesMotorTradersInternal->toSql(), $policiesMotorTradersInternal->getBindings());
                         if(count($policiesMotorTradersInternal) > 0)
                            {
                                $TCL = (float) 11500000.00;
                                $QSCL = (float) 10000000.00;
                                $SCL = (float)0;
                                $AFCL = (float) 1500000.00;
                                $ADlimit=(float)300000;
                              //  dd($policiesMotorTradersInternal);
                                foreach($policiesMotorTradersInternal as $cKey => $cValue)
                                {
                                              
                                    // Calculate QSC value once and store it
                                    // $sumInsuredValue = (float)$cValue->sumInsured;
                                    $sumInsuredValue = (float)$cValue->sumInsured+(float)$SpecifiedsumInsured;
                                    $totalFinalPre= (float)$cValue->motor_traders_total_sum+(float)$SpecifiedCoveragesItemsData;
                                //  dd($sumInsuredValue,$totalFinalPre);
                                    $qscValue = self :: QSC($sumInsuredValue, $QSCL);
                                  
                                    $netrmotorcom=self:: NRmotorcom($qscValue,$ADlimit);
                                    $QUOTAmotorcom=self:: Qmotorcom($qscValue);
                                  
                                    $qscPremmotorcom = self ::calculatePQSC($sumInsuredValue, $QSCL, $totalFinalPre, $qscValue);
                                    
                                    $surplusValuemotorcom = self ::Surplusmotorcom($sumInsuredValue,$QSCL,$SCL,$qscValue);
                                    $facaValuemotorcom = self ::FACAmotorcom($sumInsuredValue,$netrmotorcom,$QUOTAmotorcom, $SCL, $TCL, $AFCL);
                                    $fpValuemotorcom = self ::FPmotorcom($sumInsuredValue, $TCL,$qscValue,$netrmotorcom,$QUOTAmotorcom);
                                  //dd($fpValuemotorcom);
                                    /// clculate percent for premium
                                    $NRpercentmoto=(float)($netrmotorcom/$sumInsuredValue)*100;
                                    $QUOTApercentmoto=(float)($QUOTAmotorcom/$sumInsuredValue)*100;
                                    $surpluspercentmoto=(float)($surplusValuemotorcom/$sumInsuredValue)*100;
                                    $facaValuepercentmotorcom=(float)($facaValuemotorcom/$sumInsuredValue)*100;
                                    $fpValuepercentmotorcom=(float)($fpValuemotorcom/$sumInsuredValue)*100;
                                   // dd($fpValuemotorcom);
                                    // Calculate premium allocations
                                    $nrPremmotorcom = self ::calculatepremotorcom($totalFinalPre,$NRpercentmoto);
                                    $qPremmotorcom = self ::calculatepremotorcom($totalFinalPre,$QUOTApercentmoto);
                                    $surplusPremmotorcom = self ::calculatepremotorcom($totalFinalPre, $surpluspercentmoto);
                                    $facaPremmotorcom = self ::calculatepremotorcom($totalFinalPre, $facaValuepercentmotorcom);
                                    $fpPremmotorcom = self ::calculatepremotorcom($totalFinalPre, $fpValuepercentmotorcom);
                                                                
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
                                        'coverageCode'=>$policy->s_CoverageCode,
                                        'policyPremium'=>$policy->bookingDate,
                                        'totalFinalPre'=>$cValue->motor_traders_total_sum + $SpecifiedCoveragesItemsData,
                                        'policyCover'=>$cValue->s_ScreenName,
                                        'sumInsuredValue'=>$sumInsuredValue,
                                       //added by snehal on 3-10-25
                                        'sumInsuredValue1'=>$sumInsuredValue,
                                        'totalFinalPre'=>$totalFinalPre,
                                        'NETRETENTION_SI'=>$netrmotorcom,
                                        'NetRetention'=>$nrPremmotorcom,
                                        'QUOTASHARING_SI'=>$QUOTAmotorcom, //---------
                                        'QUOTASHARING'=>$qPremmotorcom,
                                        'SURPLUS_SI'=>$surplusValuemotorcom,
                                        'SURPLUS'=>$surplusPremmotorcom,
                                        'AUTO_FAC_SI'=>$facaValuemotorcom,
                                        'AUTO_FAC'=>$facaPremmotorcom,
                                        'FACULTATIVE_SI'=>$fpValuemotorcom,
                                        'FACULTATIVE'=>$fpPremmotorcom];
                                        //$results = array_map("unserialize", array_unique(array_map("serialize", $results)));
                                }
                            }

                        }
                        if($policy->s_ScreenName == 'Workers Compensation' || $policy->s_ScreenName == 'Personal Accident'  || $policy->s_ScreenName == 'Public Liability' || $policy->s_ScreenName == 'Defective Workmanship' )
                            {
                                $netr=0;
                                $QUOTA=0;
                                $qscPrem=0;
                                $surplusValue=0;
                                $facaValue=0;
                                $fpValue=0;
                                $nrPrem=0;
                                $qPrem=0;
                                $surplusPrem=0;
                                $facaPrem=0;
                                $fpPrem=0;
                                $QSCL=0;
                                $AFCL=0;
                                $SCL=0;
                                $TCL=0;
                                 $qscValue=0;
                             
                               // added by snehal on 29-12-25
                            
                                $totalFinalPre=((float)$calculatedValue + (float)$SpecifiedCoveragesItemsData + (float)$FidelityGuaranteeTotal + (float)$policyExtentionDetail );
                               // dd($sumInsuredValue,$SpecifiedsumInsured,$policy->s_ScreenName,$policy->policyId);
                               //check for extenstion deatils needs to add or not 
                                $sumInsuredValue_1=((float)$sumInsuredValue + (float)$SpecifiedsumInsured + (float)$sumInsuredValuedata+(float)$policySumExtentionDetail);
                               $sumInsuredValue_new=((float)$sumInsuredValue + (float)$SpecifiedsumInsured + (float)$sumInsuredValuedata);
                                //misc_com group 
                                
                                        $SCL =0;
                                        $AFCL = 0;
                                        $EOL = (float)300000;
                                        $TCL = 0;
                              
                               
                                // Calculate QSC value once and store it
                                $netr = self :: NREOL($sumInsuredValue_new, $EOL);
                               
                                $fpValue = self ::FPEOL($sumInsuredValue_new, $EOL);
                                 // Calculate premium allocations
                                $nrPrem = self ::calculatePremiumEOL($totalFinalPre,$netr,$sumInsuredValue_new);

                                $fpPrem = self ::calculatePremiumEOL($totalFinalPre,$fpValue,$sumInsuredValue_new);
                            //    dd($netr,$fpValue,$nrPrem,$fpPrem);
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
                                'coverageCode'=>$policy->s_CoverageCode,
                                'policyPremium'=>$policy->bookingDate,
                                //'totalFinalPre'=>($calculatedValue + $SpecifiedCoveragesItemsData + $FidelityGuaranteeTotal + $policyExtentionDetail ),
                                'totalFinalPre'=>($calculatedValue + $SpecifiedCoveragesItemsData + $FidelityGuaranteeTotal + $policyExtentionDetail ),
                                'policyId'=>$policy->policyId,
                                'policyCover'=>$policy->s_ScreenName,
                                'sumInsuredValue'=>($sumInsuredValue_1),
                                'sumInsuredValue1'=>($sumInsuredValue_new),
                                'totalFinalPre'=>$totalFinalPre,
                                'NETRETENTION_SI'=>$netr,
                                'NetRetention'=>$nrPrem,
                                'QUOTASHARING_SI'=>$QUOTA, //---------
                                'QUOTASHARING'=>$qPrem,
                                'SURPLUS_SI'=>$surplusValue,
                                'SURPLUS'=>$surplusPrem,
                                'AUTO_FAC_SI'=>$facaValue,
                                'AUTO_FAC'=>$facaPrem,
                                'FACULTATIVE_SI'=>$fpValue,
                                'FACULTATIVE'=>$fpPrem];

                            } 
                                                       
        }
    }
//}
        $pages = "POLICY NO,Term Start Date,Term End Date ,PRODUCT,Policy Frequency,Policy Premium,Policy Sum Insured,Policy Coverages,INSURED NAME,TRANS PK,TRANS TYPE,STATUS,REFERENCE NUMBER,AGENCY,AGENT,RIKS PK,RISK NAME,MOTOR DESC,MOTOR MAKE,MOTOR PK,BOOKING DATE,COVERAGE CODE,PAYMENT REFERENCE,Total Sum Insured,Total Premium,NETRETENTION SI,NETRETENTION,QUOTASHARING SI,QUOTASHARING,SURPLUS SI,SURPLUS,Auto Facultative SI,Auto Facultative,FACULTATIVE SI,FACULTATIVE,Treaty Name,Regulatory Mapping Name,FAC PLACEMENT NO,RISK BAND,MAX TRANS YES / NO\n";
        foreach ($results as $where) {
            $pages .="{$where['policyNumber']},{$where['termStartDate']},{$where['termEndDate']},{$where['productName']},{$where['premiumFreq']},{$where['totalFinalPre']},{$where['sumInsuredValue']},{$where['policyCover']},{$where['insuredName']},{$where['transPk']},{$where['transType']},{$where['Status']},{$where['gfsPolicyNo']},{$where['agenciesName']},{$where['agentName']},{$where['riskId']},{$where['riskName']},{$where['motorDesc']},{$where['motorMake']},{$where['motorPK']},{$where['bookingDate']},{$where['coverageCode']},{$where['paymentReference']},{$where['sumInsuredValue1']}, {$where['totalFinalPre']}, {$where['NETRETENTION_SI']}, {$where['NetRetention']}, {$where['QUOTASHARING_SI']}, {$where['QUOTASHARING']}, {$where['SURPLUS_SI']}, {$where['SURPLUS']}, {$where['AUTO_FAC_SI']}, {$where['AUTO_FAC']}, {$where['FACULTATIVE_SI']}, {$where['FACULTATIVE']}\n";
        } 

        $date = \Carbon\Carbon::now()->timestamp;
        $path = 'MIS-'.$date.'_report.csv';
        Storage::disk('public')->put($path, $pages, 'public');

        $attachments = array();
        array_push($attachments, $path);      
        ////*************Email send new fuction **************/////
        // $cronSendMail = new CronController();
        // $hook = 'get_mis_report';
        // $cronSendMail->AllCronMail($attachments,$hook,$cron);
        // ////*************Email send new fuction END **************///// 
        // $cron->end = Carbon::now();
        // $cron->save();
                

    }



// for coverage motor com
function NRmotorcom($QSC, $ADNetLimit) {
    return ($QSC * 0.2 <= $ADNetLimit) ? ($QSC * 0.2) : $ADNetLimit;
}
function NREOL($SI, $EOL) {
    
    return ($SI < $EOL) ? $SI : $EOL;
}
function FPEOL($SI,$EOL)
{
    return ($SI < $EOL) ? 0 : $SI-$EOL;
}

function Qmotorcom($QSC) {
   
    return $QSC * 0.8;
}
function Surplusmotorcom($SI, $QSCL, $SCL, $QSC) {
   
    if ($SI > $QSCL) {
        if ($SI <= $SCL) {
            return $SI - $QSC;
        } else {
            return $SCL;
        }
    } else {
        return 0;
    }
}

function FACAmotorcom($SI, $NR, $Q, $SCL, $TCL, $AFCL) {
    if ($SI > ($NR + $Q + $SCL)) {
        if ($SI <= $TCL) {
            return $SI - ($NR + $Q + $SCL);
        } else {
            return $AFCL;
        }
    } else {
        return 0;
    }
}
function FPmotorcom($SI, $TCL, $QSC, $NR, $Q) {
    if ($SI > $TCL) {
        return $SI - $TCL + $QSC - $NR - $Q;
    } else {
        return 0;
    }
}
function calculatepremotorcom($SI,$perc) {

    return ($SI * $perc)/100;  // Net Retention Premium
}


// for other coverage calculation
function QSC($SI, $QSCL) {
    
    return $SI <= $QSCL ? $SI : $QSCL;
}

function NR($QSC) {
  
    return $QSC * 0.7;
}

function Q($QSC) {
   
    return $QSC * 0.3;
}

function Surplus($SI, $QSCL, $SCL, $QSC) {
    if ($SI > $QSCL) {
        if ($SI <= $SCL) {
            return $SI - $QSC;
        } else {
            return ($SI - $QSC <= $SCL) ? $SI - $QSC : $SCL;
        }
    }
    return 0;
}

function FACA($SI, $QSCL, $SCL, $TCL, $AFCL) {
    if ($SI > ($QSCL + $SCL)) {
        return ($SI <= $TCL) ? $SI - ($QSCL + $SCL) : $AFCL;
    }
    return 0;
}


function FP($SI, $TCL) {
    return $SI > $TCL ? $SI - $TCL : 0;
}


function calculatePQSC($SI, $QSCL, $PRM, $QSC) {
    
    return $SI > $QSCL ? $PRM * $QSC / $SI : $PRM;  // Quota Share Premium
}

function calculateNR($PQSC) {
    return $PQSC * 0.7;  // Net Retention Premium
}

function calculateQ($PQSC) {
    return $PQSC * 0.3;  // Quota Premium
}

function calculateSurplusPremium($PRM, $SP, $SI) {
    return $SI > 0 ? $PRM * $SP / $SI : 0;  // Surplus Premium
}


function calculatePremiumEOL($PRM, $val,$SI) {

   $percentage=((float)$val/(float)$SI);
   return $percentage*$PRM;
}

function calculateFACAPremium($PRM, $FACA, $SI) {
    return $SI > 0 ? $PRM * $FACA / $SI : 0;  // Facultative Premium
}

function calculateFPPremium($PRM, $FP, $SI) {
    return $SI > 0 ? $PRM * $FP / $SI : 0;  // Facultative Placement Premium
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

    public static function reinsurance($currentD)
    {
         
    }
}
