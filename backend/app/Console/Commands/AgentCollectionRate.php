<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use AlphaDirect\User;
use AlphaDirect\Policy;
use AlphaDirect\Mail\AgentCollectionRateReport;
use PDF;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use AlphaDirect\PaymentTransaction;
use AlphaDirect\PolicyLedgers;
use AlphaDirect\Models\CronStatus;

class AgentCollectionRate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'agentcollectionrate:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Agent collection rate';

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
        $cron->name = "agentcollectionrate:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
          $agents=User::where('agency_id','!=',null)->where('active', 1)->get(array('id','firstName', 'lastName'));
          $agentData = [];
          $attachments = [];
          $complete_agentData=[];
          if($agents != null)
          {
                foreach($agents as $agent)
                {
                   // if($agent->id == 1008){
                    /***** Monthly  *****/
                    $name=$agent->firstName.' '.$agent->lastName;
                    $policies=Policy::where('agent_id', $agent->id)->whereMonth('created_at',Carbon::now()->month)->count();
                    // $premium=Policy::where('agent_id',$agent->id)->whereMonth('created_at', Carbon::now()->month)->sum('premium');
                    $get_policy_id=Policy::where('agent_id',$agent->id)->whereMonth('created_at',Carbon::now()->month)->get('id');
                    // Invoice / payment totals read invoice_amount, NOT debit/credit:
                    // policy_ledger.debit is stale on 'Invoice' rows (see
                    // AccountStatementService::rowAmount()), which understated every
                    // agent's billed premium and so overstated their collection rate.
                    // Receipts stay on `credit`: it is populated on every live Payment
                    // row, while invoice_amount is populated on only 56% of them, so
                    // sourcing payments from invoice_amount would under-count collections
                    // and understate each agent's rate.
                    $premium=PolicyLedgers::whereIn('policy_id',$get_policy_id)->where('trans_type','Invoice')->whereMonth('accounting_date',Carbon::now()->month)->sum('invoice_amount');
                    $policies_number=Policy::where('agent_id',$agent->id)->whereMonth('created_at',Carbon::now()->month)->get('policyNumber');

                    $policies_cancelled = Policy::where('agent_id', $agent->id)->whereMonth('created_at', Carbon::now()->month)->where('status',2)->count();
                    // $sum=PaymentTransaction::whereIn('policyNumber',$policies_number)->where('amount', '!=', 1)->where('status', 'Success')->where('is_refund',0)->whereMonth('created_at', Carbon::now()->month)->sum('amount');
                    $sum=PolicyLedgers::whereIn('policy_id',$get_policy_id)->where('trans_type','Payment')->whereMonth('accounting_date', Carbon::now()->month)->sum('credit');

                    if($premium != 0 and $sum !=0)
                     {
                        $percentage=$sum/$premium*100;
                        $percentage=round($percentage, 2);

                        if ($percentage > 100) {
                            $percentage = 100;
                            $sum = $premium;
                        }
                     }
                     else{
                         $percentage=0;
                     }

                    array_push($agentData,['name'=>$name ,'policies'=>$policies,'premium'=>$premium,'sum'=>$sum,'percentage'=>$percentage,'policies_cancelled'=>$policies_cancelled]);
                    // dd($agentData);

                    /***** Since Begining *****/

                    $agentname=$agent->firstName.' '.$agent->lastName;
                    $agentpolicies=Policy::where('agent_id', $agent->id)->count();
                    $agent_policies_id=Policy::where('agent_id', $agent->id)->get('id');
                    // $agentpremium=Policy::where('agent_id',$agent->id)->sum('premium');
                    $agentpremium=PolicyLedgers::whereIn('policy_id',$agent_policies_id)->where('trans_type','Invoice')->sum('invoice_amount');
                    $agentpolicies_number=Policy::where('agent_id',$agent->id)->get('policyNumber');
                    $agentCancelledpolicies=Policy::where('agent_id', $agent->id)->where('status',2)->count();

                    // $agentsum=PaymentTransaction::whereIn('policyNumber',$policies_number)->where('amount', '!=', 1)->where('status', 'Success')->where('is_refund',0)->sum('amount');
                    $agentsum=PolicyLedgers::whereIn('policy_id',$agent_policies_id)->where('trans_type','Payment')->sum('credit');

                    if($agentpremium != 0 && $agentsum != 0)
                     {
                        $complete_percentage=$agentsum/$agentpremium*100;
                        $complete_percentage=round($complete_percentage, 2);

                        if ($complete_percentage > 100) {
                            $complete_percentage = 100;
                            $agentsum = $agentpremium;
                        }
                     }else{
                        $complete_percentage=0;
                     }

                    array_push($complete_agentData,['name'=>$agentname ,'policies'=>$agentpolicies,'premium'=>$agentpremium,'sum'=>$agentsum,'percentage'=>$complete_percentage,'agentCancelledpolicies'=>$agentCancelledpolicies]);
                    // dd($complete_agentData);

                // }
            }

                $agentData = collect($agentData)->sortByDesc('percentage')->all();
                $complete_agentData = collect($complete_agentData)->sortByDesc('percentage')->all();
                $todayDate = Carbon::now()->timestamp;
                $path = 'AgentCollectionRateReport/'.$todayDate.'/agentcollectionrate.pdf';
                // dd($path);
                libxml_use_internal_errors(true);
                $pdf = PDF::loadView('admin.notes.AgentCollectionRate',['agentData' =>$agentData , 'complete_agentData'=>$complete_agentData]);
                Storage::disk('s3')->put($path, $pdf->output(), 'public');
               //Storage::put('public/pdf/dailykycreport.pdf', $pdf->output());
                $attachments[] = $path;

                if(env('APP_STATUS') == 'Production') {
                    $email = array(
                        'kkatolkar@alphadirect.co.bw',
                        'amunzara@alphadirect.co.bw',
                        'pmaswibilili@alphadirect.co.bw',
                        'arjuniyer@alphadirect.co.bw',
                        'nbarot@theriskco.com',
                        'pganesharajah@alphadirect.co.bw',
                        'tmotlogelwa@alphadirect.co.bw',
                        'lntabeni@alphadirect.co.bw',
                        'sshah@alphadirect.co.bw',
                        'aprasad@alphadirect.co.bw',
                        'aiyer@alphadirect.co.bw'
                    );
                }else{
                    $email = array(
                        //'sshah@alphadirect.co.bw',
                       // 'kkatolkar@alphadirect.co.bw',
                        'satyajeetbcd@gmail.com',
                    );
                }


                if(count($email) > 0 && (count($agentData) > 0 ||count($complete_agentData) > 0 )  )
                {
                    foreach($email as $d)
                    {
                        if($d)
                        {
                            $data = new \stdClass();
                            $data->attachment=$attachments;
                            //$data->date = $today->format('Y-m-d');
                            //mail::to('mesanketshah@gmail.com')->cc(['kkatolkar', 'arjuniyer@alphadirect.co.bw', 'pganesharajah@alphadirect.co.bw'])->send(new LedgerDailyReport($data));
                            $markdown = new AgentCollectionRateReport($data);
                            $html = $markdown->render('Mail.AgentCollectionRateReport');
                            event(new \AlphaDirect\Events\SendMail($d,'Monthly Agent Collection Rate Report',"",$html,$attachments));
                            //$sent=\Illuminate\Support\Facades\Mail::to($d)->send(new AgentCollectionRateReport($data));
                        }
                    }
                    $cron->mail_send = 1;
                    $cron->save();
                }

            }
            $cron->end = \Carbon\Carbon::now();
            $cron->save();
     }
}
