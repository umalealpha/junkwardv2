<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use AlphaDirect\KycFields;
use AlphaDirect\Product;
use AlphaDirect\Customer;
use AlphaDirect\Policy;
use Illuminate\Support\Facades\Mail;
use AlphaDirect\Mail\KycComplianceEmail;
use AlphaDirect\EmailSMSLogs;
use Carbon\Carbon;
use Log;
use AlphaDirect\Models\CronStatus;
class KycComplianceCheck extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'kyccompliancecheck:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check kyc compliance';

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
        $cron->name = "kyccompliancecheck:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        Log::info('Cron started to kyc compliance check');

        $products = Product::join('kyc_compliance','kyc_compliance.id','products.kyc_compliance')
        ->orderBy('products.id','DESC')
        ->get(array(
            'products.id',
            'products.name',
            'products.kyc_compliance',
            'kyc_compliance.fields'
            )
        );

        if (isset($products)) {
            foreach ($products as $key => $product) {
                if (isset($product) && isset($product->fields)) {
                    $fieldsData = json_decode($product->fields);
                    // dd($fieldsData);
                    if (isset($fieldsData)) {

                        $customers = Policy::join('customer','customer.id','policies.customer_id')
                                    ->join('customer_kyc','customer_kyc.customer_id','customer.id')
                                    ->where('policies.product_id',$product->id)
                                    // ->where('customer.id',5259)
                                    ->where('customer.id',5290)
                                    ->where('policies.status',1)
                                    ->where('customer.email','!=',"")
                                    ->whereNotNull('customer.email')
                                    ->orderBy('policies.id','DESC')
                                    ->groupBy('policies.customer_id')
                                    ->select(array(
                                        'policies.id',
                                        'policies.customer_id',
                                        'policies.policyNumber',
                                        'customer.firstName',
                                        'customer.lastName',
                                        'customer.email'
                                    ));


                        $customers->where(function($q) use ($fieldsData, $customers) {
                            $i = 0;
                            foreach ($fieldsData as $field) {
                                if($field->check == 1)
                                {
                                    $documentType = KycFields::where('name',$field->field)
                                    ->orwhere('name',$field->other)
                                    ->get(array('customer_kyc_column'));
                                    $customers->addSelect(
                                        'customer_kyc.'.$documentType[0]->customer_kyc_column
                                      );
                                    if($i == 0)
                                        $q->whereNull('customer_kyc.'.$documentType[0]->customer_kyc_column);
                                    else
                                        $q->orWhereNull('customer_kyc.'.$documentType[0]->customer_kyc_column);

                                    $i++;

                                }
                            }
                            echo "i".$i;
                        });

                        foreach ($fieldsData as $key => $field) {
                            // echo $key;
                            if (isset($field->check) && $field->check == 2) {
                                $documents = KycFields::where('name',$field->field)
                                                ->orwhere('name',$field->other)
                                                ->get(array('customer_kyc_column'));


                               if (isset($documents) && isset($documents[1]->customer_kyc_column)) {
                                    $customers = $customers->where(function($q) use ($documents){

                                        $q->whereNull('customer_kyc.'.$documents[0]->customer_kyc_column)
                                        ->orWhereNull('customer_kyc.'.$documents[1]->customer_kyc_column);
                                    })
                                    ->addSelect(
                                        'customer_kyc.'.$documents[0]->customer_kyc_column,
                                        'customer_kyc.'.$documents[1]->customer_kyc_column
                                    );
                               } else{
                                    $customers = $customers->whereNull('customer_kyc.'.$documents[0]->customer_kyc_column)
                                    ->addSelect(
                                        'customer_kyc.'.$documents[0]->customer_kyc_column
                                    );
                                }

                            }
                        }

                        // dd($customers);
                            $customers = $customers->get();
                            // dd($customers);
                            echo count($customers);


                        if (isset($customers) && count($customers) > 0) {
                            $customers = json_decode(json_encode($customers), true);
                            // dd($customers);
                            foreach ($customers as $key => $data) {
                                // dd($data);
                                if (isset($data)) {
                                    // dd($data->email);
                                    if(isset($data['email'])) {
                                        // echo $cust['email'];
                                        if(isset($data) && isset($data['email'])){
                                            // dd($cust);

                                            $markdown = new KycComplianceEmail($data);
                                            $html = $markdown->render('Mail.KycComplianceEmailView',['data'=>$data]);
                                            event(new \AlphaDirect\Events\SendMail($data['email'],"Alphadirect | Upload documents for Kyc completion","",$html,null,[]));

                                            $logData = [
                                                'customer_id'=>$data['customer_id'],
                                                'log_type'=>'email',
                                                'content_type'=>'kyc_compliance_email',
                                                'last_sent_date'=>Carbon::now()->format('Y-m-d'),
                                            ];

                                            $logData['next_send_date'] = Carbon::now()->addDays(1)->format('Y-m-d');
                                            $log = EmailSMSLogs::addLog($logData);

                                        }
                                    }
                                }
                            }
                        }

                        echo "send mail done";
                    }
                }
            }
        }
             $cron->end = \Carbon\Carbon::now();
             $cron->save();

    }
}
