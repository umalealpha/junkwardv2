<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\EmailBroadcasting;
use AlphaDirect\Mail\MailTemplate;
use Illuminate\Console\Command;
use AlphaDirect\KYC;
use AlphaDirect\Product;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use PDF;
use DB;
use AlphaDirect\Models\CronStatus;
use AlphaDirect\Http\Controllers\CronController;
class DailyKycforActivatedPolicyReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dailykycforactivatedpolicyreport:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Daily kyc for activated policy report';

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
        $cron->name = "dailykycforactivatedpolicyreport:cron";
        $cron->start = Carbon::now();
        $cron->save();

        $policies=KYC::join('policies','customer_kyc.customer_id','=','policies.customer_id')
            ->join('customer','customer_kyc.customer_id','customer.id')
            ->where('policies.status',1)
            ->orderby('customer_kyc.id','desc')
            ->whereBetween(\Illuminate\Support\Facades\DB::raw('date(customer_kyc.updated_at)') , [Carbon::parse('today')
                ->format('Y-m-d')  , Carbon::parse('today')
                ->format('Y-m-d') ])
                ->where(function ($query) {
                    $query->where('customer_kyc.status','!=' ,'Unchecked')
                    ->orWhere('customer_kyc.status','Renew');
                  })

                ->where(function ($query2) {
                    $query2->where('customer_kyc.approved_date',null)
                    ->OrwhereBetween(\Illuminate\Support\Facades\DB::raw('date(customer_kyc.approved_date)') , [Carbon::parse('today')
                    ->format('Y-m-d')  , Carbon::parse('today')
                    ->format('Y-m-d') ]);
                  })
                ->get(array(
                'policies.product_id',
                'policies.agent_id',
                'customer.firstName',
                'customer.lastName',
                'policies.policyNumber',
                'policies.status',
                'customer_kyc.status as kyc_status',
                 'customer_kyc.remark',
                'customer_kyc.omang',
                'customer_kyc.omangBack',
                'customer_kyc.passport',
                'customer_kyc.proof_residence',
                'customer_kyc.proof_income',
                'customer_kyc.driving_license',
                'customer_kyc.approved_date',
                'customer_kyc.performed_by',
                'customer_kyc.compliance'
        ));

        $data=[];

        foreach($policies as $key => $p)
        {


            if($p->product_id !=null && $p->product_id == 3)
            {
                if(($p->omang != null && $p->omangBack !=null || $p->passport !=null) && ($p->proof_residence != null) && ($p->proof_income != null) && ($p->driving_license != null) )
                {
                     if($p->product_id != null)
                     {
                         $product=Product::find($p->product_id);
                         //dd($product->name);
                     }
                    array_push($data,['policyNumber'=>$p->policyNumber,'Customer'=>$p->firstName.' '.$p->lastName ,'Product'=>$product->name,'policy_status'=>$p->status,'compliance'=>$p->compliance,'Kyc_status'=>$p->kyc_status,'remark'=>$p->remark,'performed_by'=>$p->performed_by,'approved_date'=>$p->approved_date,'agent_id'=>$p->agent_id]);

                }

            }else if($p->product_id != null && $p->product_id == 2)
            {
                if(($p->omang != null && $p->omangBack !=null  || ($p->passport!=null)) && ($p->proof_residence != null) && ($p->driving_license != null))
                {
                    if($p->product_id != null)
                     {
                         $product=Product::find($p->product_id);
                         //dd($product->name);
                     }

                     array_push($data,['policyNumber'=>$p->policyNumber,'Customer'=>$p->firstName.' '.$p->lastName ,'Product'=>$product->name,'policy_status'=>$p->status,'compliance'=>$p->compliance ,'Kyc_status'=>$p->kyc_status,'remark'=>$p->remark,'performed_by'=>$p->performed_by,'approved_date'=>$p->approved_date,'agent_id'=>$p->agent_id]);

                }

            }
            else
            {
                if(($p->omang != null && $p->omangBack !=null )|| ($p->passport !=null))
                {
                    if($p->product_id != null)
                     {
                         $product=Product::find($p->product_id);
                         //dd($product->name);
                     }
                     array_push($data,['policyNumber'=>$p->policyNumber,'Customer'=>$p->firstName.' '.$p->lastName ,'Product'=>$product->name,'policy_status'=>$p->status,'compliance'=>$p->compliance,'Kyc_status'=>$p->kyc_status,'remark'=>$p->remark,'performed_by'=>$p->performed_by,'approved_date'=>$p->approved_date,'agent_id'=>$p->agent_id]);

                }
            }
        }


        /*** Count for unchecked kyc for today */
        $today_unchecked_count= count($data);

        /**** Count for Rejected or other status for today */
        $today_other_count=KYC::join('policies','customer_kyc.customer_id','=','policies.customer_id')
        ->join('customer','customer_kyc.customer_id','customer.id')
        ->where('policies.status',1)
        ->orderby('customer_kyc.id','desc')
        ->whereBetween(\Illuminate\Support\Facades\DB::raw('date(customer_kyc.updated_at)') , [Carbon::parse('today')
            ->format('Y-m-d')  , Carbon::parse('today')
            ->format('Y-m-d') ])
            ->where(function ($query) {
                $query->where('customer_kyc.status','!=' ,'Unchecked')
                ->orWhere('customer_kyc.status','Renew');
              })

            ->where(function ($query2) {
                $query2->where('customer_kyc.approved_date',null)
                ->OrwhereBetween(\Illuminate\Support\Facades\DB::raw('date(customer_kyc.approved_date)') , [Carbon::parse('today')
                ->format('Y-m-d')  , Carbon::parse('today')
                ->format('Y-m-d') ]);
              })
             ->get(array(
                'policies.product_id',
                'customer.firstName',
                'customer.lastName',
                'policies.policyNumber',
                'policies.status',
                'customer_kyc.status as kyc_status',
                'customer_kyc.omang',
                'customer_kyc.omangBack',
                'customer_kyc.passport',
                'customer_kyc.proof_residence',
                'customer_kyc.proof_income',
                'customer_kyc.driving_license',
                'customer_kyc.performed_by',
                'customer_kyc.compliance'
             ));
            $today_other_status_count=[];
            $policy_numbers=[];
        foreach($today_other_count as $key => $p)
        {


            if($p->product_id !=null && $p->product_id == 3)
            {
                if(($p->omang != null && $p->omangBack !=null || $p->passport !=null) && ($p->proof_residence != null) && ($p->proof_income != null) && ($p->driving_license != null) )
                {
                     if($p->product_id != null)
                     {
                         $product=Product::find($p->product_id);
                         //dd($product->name);
                     }
                    array_push($today_other_status_count,['policyNumber'=>$p->policyNumber,'Customer'=>$p->firstName.' '.$p->lastName ,'Product'=>$product->name,'policy_status'=>$p->status,'compliance'=>$p->compliance,'Kyc_status'=>$p->kyc_status]);
                    array_push($policy_numbers,$p->policyNumber);
                }

            }else if($p->product_id != null && $p->product_id == 2)
            {
                if(($p->omang != null && $p->omangBack !=null  || ($p->passport!=null)) && ($p->proof_residence != null) && ($p->driving_license != null))
                {
                    if($p->product_id != null)
                     {
                         $product=Product::find($p->product_id);
                         //dd($product->name);
                     }

                     array_push($today_other_status_count,['policyNumber'=>$p->policyNumber,'Customer'=>$p->firstName.' '.$p->lastName ,'Product'=>$product->name,'policy_status'=>$p->status,'compliance'=>$p->compliance ,'Kyc_status'=>$p->kyc_status]);
                     array_push($policy_numbers,$p->policyNumber);

                }

            }
            else
            {
                if(($p->omang != null && $p->omangBack !=null )|| ($p->passport !=null))
                {
                    if($p->product_id != null)
                     {
                         $product=Product::find($p->product_id);
                         //dd($product->name);
                     }
                     array_push($today_other_status_count,['policyNumber'=>$p->policyNumber,'Customer'=>$p->firstName.' '.$p->lastName ,'Product'=>$product->name,'policy_status'=>$p->status,'compliance'=>$p->compliance,'Kyc_status'=>$p->kyc_status]);
                     array_push($policy_numbers,$p->policyNumber);
                }
            }
        }

        /**** Count for total unchecked kyc */
        $total= $policies=KYC::join('policies','customer_kyc.customer_id','=','policies.customer_id')
        ->join('customer','customer_kyc.customer_id','customer.id')
        ->where('policies.status',1)
        ->orderby('customer_kyc.id','desc')
        ->where(function ($query) {
            $query->where('customer_kyc.status','!=' ,'Unchecked')
            ->orWhere('customer_kyc.status','Renew');
          })


        ->get(array(
            'policies.product_id',
            'customer.firstName',
            'customer.lastName',
            'policies.policyNumber',
            'policies.status',
            'customer_kyc.status as kyc_status',
            'customer_kyc.omang',
            'customer_kyc.omangBack',
            'customer_kyc.passport',
            'customer_kyc.proof_residence',
            'customer_kyc.proof_income',
            'customer_kyc.driving_license',
              'customer_kyc.compliance'
         ));

        $total_count=[];

        foreach($total as $key => $p)
         {

            if($p->product_id !=null && $p->product_id == 3)
            {
                if(($p->omang != null && $p->omangBack !=null || $p->passport !=null) && ($p->proof_residence != null) && ($p->proof_income != null) && ($p->driving_license != null) )
                {
                     if($p->product_id != null)
                     {
                         $product=Product::find($p->product_id);
                         //dd($product->name);
                     }
                    array_push($total_count,['policyNumber'=>$p->policyNumber,'Customer'=>$p->firstName.' '.$p->lastName ,'Product'=>$product->name,'policy_status'=>$p->status,'compliance'=>$p->compliance,'Kyc_status'=>$p->kyc_status]);
                }

            }else if($p->product_id != null && $p->product_id == 2)
            {
                if(($p->omang != null && $p->omangBack !=null  || ($p->passport!=null)) && ($p->proof_residence != null) && ($p->driving_license != null))
                {
                    if($p->product_id != null)
                     {
                         $product=Product::find($p->product_id);
                         //dd($product->name);
                     }

                     array_push($total_count,['policyNumber'=>$p->policyNumber,'Customer'=>$p->firstName.' '.$p->lastName ,'Product'=>$product->name,'policy_status'=>$p->status,'compliance'=>$p->compliance ,'Kyc_status'=>$p->kyc_status]);

                }

            }
            else
            {
                if(($p->omang != null && $p->omangBack !=null )|| ($p->passport !=null))
                {
                    if($p->product_id != null)
                     {
                         $product=Product::find($p->product_id);
                         //dd($product->name);
                     }
                     array_push($total_count,['policyNumber'=>$p->policyNumber,'Customer'=>$p->firstName.' '.$p->lastName ,'Product'=>$product->name,'policy_status'=>$p->status,'compliance'=>$p->compliance,'Kyc_status'=>$p->kyc_status]);
                }
            }
        }

        /**** Total upto yesterday ****/

        $total_upto_yesterday= $total= $policies=KYC::join('policies','customer_kyc.customer_id','=','policies.customer_id')
        ->join('customer','customer_kyc.customer_id','customer.id')
        ->where('policies.status',1)
        ->orderby('customer_kyc.id','desc')
        ->where(\Illuminate\Support\Facades\DB::raw('date(customer_kyc.updated_at)'),'<',Carbon::parse('today')->format('Y-m-d'))
        ->where(function ($query) {
            $query->where('customer_kyc.status','!=' ,'Unchecked')
            ->orWhere('customer_kyc.status','Renew');
          })

        // ->where(function ($query2) {
        //     $query2->where('customer_kyc.approved_date',null)
        //     ->OrwhereBetween(\Illuminate\Support\Facades\DB::raw('date(customer_kyc.approved_date)') , [Carbon::parse('today')
        //     ->format('Y-m-d')  , Carbon::parse('today')
        //     ->format('Y-m-d') ]);
        //   })
        ->get(array(
            'policies.product_id',
            'customer.firstName',
            'customer.lastName',
            'policies.policyNumber',
            'policies.status',
            'customer_kyc.status as kyc_status',
            'customer_kyc.omang',
            'customer_kyc.omangBack',
            'customer_kyc.passport',
            'customer_kyc.proof_residence',
            'customer_kyc.proof_income',
            'customer_kyc.driving_license',
            'policies.created_at',
              'customer_kyc.compliance'
        ));
         $yesterday_count_total=[];

        foreach($total_upto_yesterday as $key => $p)
        {

            if($p->product_id !=null && $p->product_id == 3)
            {
                if(($p->omang != null && $p->omangBack !=null || $p->passport !=null) && ($p->proof_residence != null) && ($p->proof_income != null) && ($p->driving_license != null) )
                {
                     if($p->product_id != null)
                     {
                         $product=Product::find($p->product_id);
                         //dd($product->name);
                     }
                    array_push($yesterday_count_total,['policyNumber'=>$p->policyNumber,'Customer'=>$p->firstName.' '.$p->lastName ,'Product'=>$product->name,'policy_status'=>$p->status,'compliance'=>$p->compliance,'Kyc_status'=>$p->kyc_status,'created_at'=>Carbon::parse($p->created_at)->format('Y-m-d') ]);
                }

            }else if($p->product_id != null && $p->product_id == 2)
            {
                if(($p->omang != null && $p->omangBack !=null  || ($p->passport!=null)) && ($p->proof_residence != null) && ($p->driving_license != null))
                {
                    if($p->product_id != null)
                     {
                         $product=Product::find($p->product_id);
                         //dd($product->name);
                     }

                     array_push( $yesterday_count_total,['policyNumber'=>$p->policyNumber,'Customer'=>$p->firstName.' '.$p->lastName ,'Product'=>$product->name,'policy_status'=>$p->status,'compliance'=>$p->compliance ,'Kyc_status'=>$p->kyc_status,'created_at'=>Carbon::parse($p->created_at)->format('Y-m-d')]);

                }

            }
            else
            {
                if(($p->omang != null && $p->omangBack !=null )|| ($p->passport !=null))
                {
                    if($p->product_id != null)
                     {
                         $product=Product::find($p->product_id);
                         //dd($product->name);
                     }
                     array_push($yesterday_count_total,['policyNumber'=>$p->policyNumber,'Customer'=>$p->firstName.' '.$p->lastName ,'Product'=>$product->name,'policy_status'=>$p->status,'compliance'=>$p->compliance,'Kyc_status'=>$p->kyc_status,'created_at'=>Carbon::parse($p->created_at)->format('Y-m-d')]);
                }
            }
        }


        /***  Count for previous day */

        $yesterday_count=KYC::join('policies','customer_kyc.customer_id','=','policies.customer_id')
        ->join('customer','customer_kyc.customer_id','customer.id')
        ->where('policies.status',1)
        ->orderby('customer_kyc.id','desc')
        ->whereBetween(\Illuminate\Support\Facades\DB::raw('date(customer_kyc.updated_at)') , [Carbon::parse('today')
        ->format('Y-m-d')  , Carbon::parse('today')->subDay()
        ->format('Y-m-d') ])
        ->where(function ($query) {
            $query->where('customer_kyc.status','!=' ,'Unchecked')
            ->orWhere('customer_kyc.status','Renew');
          })

        ->where(function ($query2) {
            $query2->where('customer_kyc.approved_date',null)
            ->OrwhereBetween(\Illuminate\Support\Facades\DB::raw('date(customer_kyc.approved_date)') , [Carbon::parse('today')
            ->format('Y-m-d')  , Carbon::parse('today')->subDay()
            ->format('Y-m-d') ]);
          })
        ->get(array(
            'policies.product_id',
            'customer.firstName',
            'customer.lastName',
            'policies.policyNumber',
            'policies.status',
            'customer_kyc.status as kyc_status',
            'customer_kyc.omang',
            'customer_kyc.omangBack',
            'customer_kyc.passport',
            'customer_kyc.proof_residence',
            'customer_kyc.proof_income',
            'customer_kyc.driving_license',
              'customer_kyc.compliance'
    ));

        $yesterday_unchecked_count=[];
        foreach($yesterday_count as $key => $p)
        {

            if($p->product_id !=null && $p->product_id == 3)
            {
                if(($p->omang != null && $p->omangBack !=null || $p->passport !=null) && ($p->proof_residence != null) && ($p->proof_income != null) && ($p->driving_license != null) )
                {
                     if($p->product_id != null)
                     {
                         $product=Product::find($p->product_id);
                         //dd($product->name);
                     }
                    array_push($yesterday_unchecked_count,['policyNumber'=>$p->policyNumber,'Customer'=>$p->firstName.' '.$p->lastName ,'Product'=>$product->name,'policy_status'=>$p->status,'compliance'=>$p->compliance,'Kyc_status'=>$p->kyc_status]);
                }

            }else if($p->product_id != null && $p->product_id == 2)
            {
                if(($p->omang != null && $p->omangBack !=null  || ($p->passport!=null)) && ($p->proof_residence != null) && ($p->driving_license != null))
                {
                    if($p->product_id != null)
                     {
                         $product=Product::find($p->product_id);
                         //dd($product->name);
                     }

                     array_push($yesterday_unchecked_count,['policyNumber'=>$p->policyNumber,'Customer'=>$p->firstName.' '.$p->lastName ,'Product'=>$product->name,'policy_status'=>$p->status,'compliance'=>$p->compliance ,'Kyc_status'=>$p->kyc_status]);

                }

            }
            else
            {
                if(($p->omang != null && $p->omangBack !=null )|| ($p->passport !=null))
                {
                    if($p->product_id != null)
                     {
                         $product=Product::find($p->product_id);
                         //dd($product->name);
                     }
                     array_push($yesterday_unchecked_count,['policyNumber'=>$p->policyNumber,'Customer'=>$p->firstName.' '.$p->lastName ,'Product'=>$product->name,'policy_status'=>$p->status,'compliance'=>$p->compliance,'Kyc_status'=>$p->kyc_status]);
                }
            }
        }

        $count_array=[];
         array_push($count_array ,['today_unchecked'=>$today_unchecked_count,'total_upto_yesterday'=>count($yesterday_count_total), 'today_other_count'=>count($today_other_status_count) , 'total'=>count($total_count) ,'yesterday_unchecked_count'=>count($yesterday_unchecked_count)]);

        //dd('Working');

        // $todayDate = Carbon::now()->timestamp;
        // $path = 'DailyKycActivatedPolicyReport/'.$todayDate.'/dailykycactivatedpolicyreport.pdf';

        // libxml_use_internal_errors(true);
        // $pdf = PDF::loadView('admin.notes.DailyKycActivatedPolicy',['data' =>$data]);

        // //Storage::disk('s3')->put($path, $pdf->output(), 'public');
        // Storage::put('public/pdf/dailykycactivatedpolicyreport.pdf', $pdf->output());
        // $attachments = $path;

        //dd($attachments);



   /*** Agent Data ****/
        //   DB::connection()->enableQueryLog();
        $agentdata=KYC::join('policies','customer_kyc.customer_id','=','policies.customer_id')
        ->join('customer','customer_kyc.customer_id','customer.id')
        ->where('policies.status',1)
        ->where('performed_by','!=', null)
        ->orderby('customer_kyc.id','desc')
        ->whereBetween(\Illuminate\Support\Facades\DB::raw('date(customer_kyc.updated_at)') , [Carbon::parse('today')
            ->format('Y-m-d')  , Carbon::parse('today')
            ->format('Y-m-d') ])
            ->where(function ($query) {
                                        $query->where('customer_kyc.status','!=' ,'Unchecked')
                                        ->orWhere('customer_kyc.status','Renew');
                                      })

            // ->where(function ($query2) {
            //                             $query2->where('customer_kyc.approved_date',null)
            //                             ->OrwhereBetween(\Illuminate\Support\Facades\DB::raw('date(customer_kyc.approved_date)') , [Carbon::parse('today')
            //                             ->format('Y-m-d')  , Carbon::parse('today')
            //                             ->format('Y-m-d') ]);
            //                           })
            ->groupBy('performed_by')->get(array(
                'policies.product_id',
                'customer.firstName',
                'customer.lastName',
                'policies.policyNumber',
                'policies.status',
                'customer_kyc.status as kyc_status',
                'customer_kyc.omang',
                'customer_kyc.omangBack',
                'customer_kyc.passport',
                'customer_kyc.proof_residence',
                'customer_kyc.proof_income',
                'customer_kyc.driving_license',
                'customer_kyc.approved_date',
                'customer_kyc.performed_by',
                  'customer_kyc.compliance'
             ));

          ////   $queries = DB::getQueryLog();
           //     dd($queries);
//dd($agentdata);
        $agentdata_info=[];
        foreach($agentdata as $key => $p)
        {

            if($p->product_id !=null && $p->product_id == 3)
            {
                if(($p->omang != null && $p->omangBack !=null || $p->passport !=null) && ($p->proof_residence != null) && ($p->proof_income != null) && ($p->driving_license != null) )
                {
                     if($p->product_id != null)
                     {
                         $product=Product::find($p->product_id);
                         //dd($product->name);
                     }
                    array_push($agentdata_info,['policyNumber'=>$p->policyNumber,'Customer'=>$p->firstName.' '.$p->lastName ,'Product'=>$product->name,'policy_status'=>$p->status,'compliance'=>$p->compliance,'Kyc_status'=>$p->kyc_status, 'performed_by'=>$p->performed_by,'approved_date'=>$p->approved_date]);
                }

            }else if($p->product_id != null && $p->product_id == 2)
            {
                if(($p->omang != null && $p->omangBack !=null  || ($p->passport!=null)) && ($p->proof_residence != null) && ($p->driving_license != null))
                {
                    if($p->product_id != null)
                     {
                         $product=Product::find($p->product_id);
                         //dd($product->name);
                     }

                     array_push($agentdata_info,['policyNumber'=>$p->policyNumber,'Customer'=>$p->firstName.' '.$p->lastName ,'Product'=>$product->name,'policy_status'=>$p->status,'compliance'=>$p->compliance ,'Kyc_status'=>$p->kyc_status, 'performed_by'=>$p->performed_by,'approved_date'=>$p->approved_date]);

                }

            }
            else
            {
                if(($p->omang != null && $p->omangBack !=null )|| ($p->passport !=null))
                {
                    if($p->product_id != null)
                     {
                         $product=Product::find($p->product_id);
                         //dd($product->name);
                     }
                     array_push($agentdata_info,['policyNumber'=>$p->policyNumber,'Customer'=>$p->firstName.' '.$p->lastName ,'Product'=>$product->name,'policy_status'=>$p->status,'compliance'=>$p->compliance,'Kyc_status'=>$p->kyc_status ,'performed_by'=>$p->performed_by,'approved_date'=>$p->approved_date]);
                }
            }
        }

//dd($agentdata_info);

         //$agentdata_info=[['policyNumber'=>'MIS12365401' , 'Customer'=>'Akshay Balpure' , 'Product'=>'Faltu' , 'policy_status'=>'Active' ,'Kyc_status'=>'Unchecked','compliance'=>'yes', 'performed_by'=>1]];

        $date = \Carbon\Carbon::now()->timestamp;
        $path = 'Policy/KYC-'.$date.'/KYC.pdf';

        libxml_use_internal_errors(true);
        $pdf = PDF::loadView('admin.notes.DailyKycActivatedPolicy', ['data'=>$data , 'count'=>$count_array, 'agentdata'=>$agentdata_info ,'policy_numbers'=>$policy_numbers])->setPaper('a3', 'landscape');
         Storage::disk('s3')->put($path, $pdf->output(), 'public');
        //Storage::disk('local')->put('public/exampleRenew12.pdf', $pdf->output());


        $attachments = array();
        array_push($attachments, $path);
        if(count($data) > 0 || count($count_array) > 0 || count($agentdata_info) > 0 || count($policy_numbers) > 0){
            ////*************Email send new fuction **************/////
            $cronSendMail = new CronController();
            $hook = 'kyc_activated_policies';
            $cronSendMail->AllCronMail($attachments,$hook,$cron);
            ////*************Email send new fuction END **************/////
        }
        // $email = array();
        // if(env('APP_STATUS') == 'Production') {
        //     $email = array(
        //         'kkatolkar@alphadirect.co.bw',
        //         'satyajeetbcd@gmail.com'  /*
        //         'amunzara@alphadirect.co.bw',
        //         'pmaswibilili@alphadirect.co.bw',
        //         'arjuniyer@alphadirect.co.bw',
        //         'nbarot@theriskco.com',
        //         'pganesharajah@alphadirect.co.bw',
        //         'tmotlogelwa@alphadirect.co.bw',
        //         'lntabeni@alphadirect.co.bw',
        //         'kbotana@alphadirect.co.bw',
        //         'aiyer@alphadirect.co.bw' */
        //    );
        // }else{
        //     $email = array('satyajeetbcd@gmail.com');
        // }

        // if(count($email) > 0 && (count($data) > 0 || count($count_array) > 0 || count($agentdata_info) > 0 || count($policy_numbers) > 0)) {
        //     foreach($email as $d){
        //         if($d){
        //             $data = new \stdClass();
        //             $data->user_id = null;
        //             $data->hook = 'kyc_activated_policies';
        //             $data->customer_id = null;
        //             $data->attachment = $attachments;
        //             $emailTemplate = EmailBroadcasting::where('hook_slug', $data->hook)->first(array('subject'));
        //             $markdown = new MailTemplate($data);
        //             $html = $markdown->render('Mail.mailTemplate',['data'=>$data]);
        //             event(new \AlphaDirect\Events\SendMail($d,$emailTemplate->subject,"",$html,$attachments,['hook' => $data->hook]));

        //             //$sent = \Illuminate\Support\Facades\Mail::to($d)->send(new MailTemplate($data));
        //         }
        //     }
        //     $cron->mail_send = 1;
        //     $cron->save();
        // }

        Storage::disk('s3')->delete($path);
        $cron->end = Carbon::now();
        $cron->save();

    }
}
