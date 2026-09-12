<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use AlphaDirect\KycFields;
use AlphaDirect\Product;
use AlphaDirect\Customer;
use AlphaDirect\Policy;
use AlphaDirect\KYC;
use Log;
use AlphaDirect\Models\CronStatus;
class UpdateCustomerKycCompliance extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'updateCustomerKycCompliance:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update customer kyc compliance';

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
        $cron->name = "updateCustomerKycCompliance:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();

        Log::info('Cron started to update customer kyc compliance');

        $products = Product::join('kyc_compliance','kyc_compliance.id','products.kyc_compliance')
        ->orderBy('products.id','DESC')
        ->get(array(
            'products.id',
            'products.name',
            'products.kyc_compliance',
            'kyc_compliance.fields'
            )
        );

        $result = array();

        if (isset($products)) {
            foreach ($products as $key => $product) {
                if (isset($product) && isset($product->fields)) {
                    $fieldsData = json_decode($product->fields);
                    if (isset($fieldsData)) {
                        $customers = Policy::join('customer','customer.id','policies.customer_id')
                                    ->join('customer_kyc','customer_kyc.customer_id','customer.id')
                                    ->where('policies.product_id',$product->id)
                                    ->where('customer_kyc.status','!=',"Unchecked")
                                    ->orderBy('policies.id','DESC')
                                    ->groupBy('policies.customer_id')
                                    ->get(array(
                                        'customer_kyc.id',
                                        'customer_kyc.customer_id',
                                        'customer_kyc.omangFrontStatus',
                                        'customer_kyc.omangBackStatus',
                                        'customer_kyc.driving_licenseStatus',
                                        'customer_kyc.proof_residenceStatus',
                                        'customer_kyc.proof_incomeStatus',
                                        'customer_kyc.passportStatus'
                                    ));

                        // dd(count($customers));

                        if (isset($customers)) {

                            foreach ($customers as $key => $customer) {

                                $documentStatusCheck = KYC::where('customer_id',$customer->customer_id)->select(array('customer_id'));

                                $documentStatusCheck = $documentStatusCheck->where(function($q) use ($fieldsData,$documentStatusCheck,$customer){

                                    $documentStatusCheck->where(function($q) use ($fieldsData,$documentStatusCheck,$customer){

                                        $documentStatusCheck->where(function($q) use ($fieldsData,$documentStatusCheck,$customer){

                                            foreach ($fieldsData as $key => $field) {
                                                if($field->check == 1)
                                                {
                                                    $documentName = KycFields::where('name',$field->field)
                                                    ->orwhere('name',$field->other)
                                                    ->get(array('customer_kyc_column'));


                                                    switch ($documentName[0]->customer_kyc_column) {
                                                        case 'omang':
                                                                $q->where('omangFrontStatus','!=',1);
                                                                if ($customer->omangFrontStatus == 0) {
                                                                    $documentStatusCheck->addSelect(
                                                                        'omangFrontStatus'
                                                                    );
                                                                }
                                                            break;

                                                        case 'omangBack':
                                                                $q->where('omangBackStatus','!=',1);
                                                                if ($customer->omangBackStatus == 0) {
                                                                    $documentStatusCheck->addSelect(
                                                                        'omangBackStatus'
                                                                    );
                                                                }
                                                            break;

                                                        case 'driving_license':
                                                                $q->where('driving_licenseStatus','!=',1);
                                                                if ($customer->driving_licenseStatus == 0) {
                                                                    $documentStatusCheck->addSelect(
                                                                        'driving_licenseStatus'
                                                                    );
                                                                }
                                                            break;

                                                        case 'proof_residence':
                                                                $q->where('proof_residenceStatus','!=',1);
                                                                if ($customer->proof_residenceStatus == 0) {
                                                                    $documentStatusCheck->addSelect(
                                                                        'proof_residenceStatus'
                                                                    );
                                                                }
                                                            break;

                                                        case 'proof_income':
                                                                $q->where('proof_incomeStatus','!=',1);
                                                                if ($customer->proof_incomeStatus == 0) {
                                                                    $documentStatusCheck->addSelect(
                                                                        'proof_incomeStatus'
                                                                    );
                                                                }
                                                            break;

                                                        case 'passport':
                                                                $q->where('passportStatus','!=',1);
                                                                if ($customer->passportStatus == 0) {
                                                                    $documentStatusCheck->addSelect(
                                                                        'passportStatus'
                                                                    );
                                                                }
                                                            break;

                                                        default:
                                                            break;
                                                    }

                                                }

                                            }

                                            $q->orWhere(function($r) use ($fieldsData,$documentStatusCheck,$customer){
                                                foreach ($fieldsData as $key => $field) {
                                                    if($field->check == 1)
                                                    {
                                                        $documentName = KycFields::where('name',$field->field)
                                                        ->orwhere('name',$field->other)
                                                        ->get(array('customer_kyc_column'));


                                                        switch ($documentName[0]->customer_kyc_column) {
                                                            case 'omang':
                                                                    $r->orWhere('omangFrontStatus',0);
                                                                    if ($customer->omangFrontStatus == 0) {
                                                                        $documentStatusCheck->addSelect(
                                                                            'omangFrontStatus'
                                                                        );
                                                                    }
                                                                break;

                                                            case 'omangBack':
                                                                    $r->orWhere('omangBackStatus',0);
                                                                    if ($customer->omangBackStatus == 0) {
                                                                        $documentStatusCheck->addSelect(
                                                                            'omangBackStatus'
                                                                        );
                                                                    }
                                                                break;

                                                            case 'driving_license':
                                                                    $r->orWhere('driving_licenseStatus',0);
                                                                    if ($customer->driving_licenseStatus == 0) {
                                                                        $documentStatusCheck->addSelect(
                                                                            'driving_licenseStatus'
                                                                        );
                                                                    }
                                                                break;

                                                            case 'proof_residence':
                                                                    $r->orWhere('proof_residenceStatus',0);
                                                                    if ($customer->proof_residenceStatus == 0) {
                                                                        $documentStatusCheck->addSelect(
                                                                            'proof_residenceStatus'
                                                                        );
                                                                    }
                                                                break;

                                                            case 'proof_income':
                                                                    $r->orWhere('proof_incomeStatus',0);
                                                                    if ($customer->proof_incomeStatus == 0) {
                                                                        $documentStatusCheck->addSelect(
                                                                            'proof_incomeStatus'
                                                                        );
                                                                    }
                                                                break;

                                                            case 'passport':
                                                                    $r->orWhere('passportStatus',0);
                                                                    if ($customer->passportStatus == 0) {
                                                                        $documentStatusCheck->addSelect(
                                                                            'passportStatus'
                                                                        );
                                                                    }
                                                                break;

                                                            default:
                                                                break;
                                                        }

                                                    }

                                                }
                                            });

                                            $q->orWhere(function($s) use ($fieldsData,$documentStatusCheck,$customer){
                                                foreach ($fieldsData as $key => $field) {
                                                    if($field->check == 2)
                                                    {
                                                        $documentName = KycFields::where('name',$field->field)
                                                            ->orwhere('name',$field->other)
                                                            ->get(array('customer_kyc_column'));
                                                            // $q->addSelect(
                                                            //     $documentName[0]->customer_kyc_column
                                                            // );
                                                        if(isset($documentName) && isset($documentName[1]->customer_kyc_column)){
                                                            switch ($documentName[0]->customer_kyc_column) {
                                                                case 'omang':
                                                                        $s->where('omangFrontStatus',0);
                                                                        if ($customer->omangFrontStatus == 0) {
                                                                            $documentStatusCheck->addSelect(
                                                                                'omangFrontStatus'
                                                                            );
                                                                        }
                                                                    break;

                                                                case 'omangBack':
                                                                        $s->where('omangBackStatus',0);
                                                                        if ($customer->omangBackStatus == 0) {
                                                                            $documentStatusCheck->addSelect(
                                                                                'omangBackStatus'
                                                                            );
                                                                        }
                                                                    break;

                                                                case 'driving_license':
                                                                        $s->where('driving_licenseStatus',0);
                                                                        if ($customer->driving_licenseStatus == 0) {
                                                                            $documentStatusCheck->addSelect(
                                                                                'driving_licenseStatus'
                                                                            );
                                                                        }
                                                                    break;

                                                                case 'proof_residence':
                                                                        $s->where('proof_residenceStatus',0);
                                                                        if ($customer->proof_residenceStatus == 0) {
                                                                            $documentStatusCheck->addSelect(
                                                                                'proof_residenceStatus'
                                                                            );
                                                                        }
                                                                    break;

                                                                case 'proof_income':
                                                                        $s->where('proof_incomeStatus',0);
                                                                        if ($customer->proof_incomeStatus == 0) {
                                                                            $documentStatusCheck->addSelect(
                                                                                'proof_incomeStatus'
                                                                            );
                                                                        }
                                                                    break;

                                                                case 'passport':
                                                                        $s->where('passportStatus',0);
                                                                        if ($customer->passportStatus == 0) {
                                                                            $documentStatusCheck->addSelect(
                                                                                'passportStatus'
                                                                            );
                                                                        }
                                                                    break;

                                                                default:
                                                                    break;
                                                            }

                                                            switch ($documentName[1]->customer_kyc_column) {
                                                                case 'omang':
                                                                        $s->where('omangFrontStatus',0);
                                                                        if ($customer->omangFrontStatus == 0) {
                                                                            $documentStatusCheck->addSelect(
                                                                                'omangFrontStatus'
                                                                            );
                                                                        }
                                                                    break;

                                                                case 'omangBack':
                                                                        $s->where('omangBackStatus',0);
                                                                        if ($customer->omangBackStatus == 0) {
                                                                            $documentStatusCheck->addSelect(
                                                                                'omangBackStatus'
                                                                            );
                                                                        }
                                                                    break;

                                                                case 'driving_license':
                                                                        $s->where('driving_licenseStatus',0);
                                                                        if ($customer->driving_licenseStatus == 0) {
                                                                            $documentStatusCheck->addSelect(
                                                                                'driving_licenseStatus'
                                                                            );
                                                                        }
                                                                    break;

                                                                case 'proof_residence':
                                                                        $s->where('proof_residenceStatus',0);
                                                                        if ($customer->proof_residenceStatus == 0) {
                                                                            $documentStatusCheck->addSelect(
                                                                                'proof_residenceStatus'
                                                                            );
                                                                        }
                                                                    break;

                                                                case 'proof_income':
                                                                        $s->where('proof_incomeStatus',0);
                                                                        if ($customer->proof_incomeStatus == 0) {
                                                                            $documentStatusCheck->addSelect(
                                                                                'proof_incomeStatus'
                                                                            );
                                                                        }
                                                                    break;

                                                                case 'passport':
                                                                        $s->where('passportStatus',0);
                                                                        if ($customer->passportStatus == 0) {
                                                                            $documentStatusCheck->addSelect(
                                                                                'passportStatus'
                                                                            );
                                                                        }
                                                                    break;

                                                                default:
                                                                    break;
                                                            }

                                                        } else {
                                                            switch ($documentName[0]->customer_kyc_column) {
                                                                case 'omang':
                                                                        $documentStatusCheck->where('omangFrontStatus',0);
                                                                        if ($customer->omangFrontStatus == 0) {
                                                                            $documentStatusCheck->addSelect(
                                                                                'omangFrontStatus'
                                                                            );
                                                                        }
                                                                    break;

                                                                case 'omangBack':
                                                                        $documentStatusCheck->where('omangBackStatus',0);
                                                                        if ($customer->omangBackStatus == 0) {
                                                                            $documentStatusCheck->addSelect(
                                                                                'omangBackStatus'
                                                                            );
                                                                        }
                                                                    break;

                                                                case 'driving_license':
                                                                        $documentStatusCheck->where('driving_licenseStatus',0);
                                                                        if ($customer->driving_licenseStatus == 0) {
                                                                            $documentStatusCheck->addSelect(
                                                                                'driving_licenseStatus'
                                                                            );
                                                                        }
                                                                    break;

                                                                case 'proof_residence':
                                                                        $documentStatusCheck->where('proof_residenceStatus',0);
                                                                        if ($customer->proof_residenceStatus == 0) {
                                                                            $documentStatusCheck->addSelect(
                                                                                'proof_residenceStatus'
                                                                            );
                                                                        }
                                                                    break;

                                                                case 'proof_income':
                                                                        $documentStatusCheck->where('proof_incomeStatus',0);
                                                                        if ($customer->proof_incomeStatus == 0) {
                                                                            $documentStatusCheck->addSelect(
                                                                                'proof_incomeStatus'
                                                                            );
                                                                        }
                                                                    break;

                                                                case 'passport':
                                                                        $documentStatusCheck->where('passportStatus',0);
                                                                        if ($customer->passportStatus == 0) {
                                                                            $documentStatusCheck->addSelect(
                                                                                'passportStatus'
                                                                            );
                                                                        }
                                                                    break;

                                                                default:
                                                                    break;
                                                            }
                                                        }
                                                    }
                                                }
                                            });
                                        });
                                    });
                                });

                                $documentStatusCheck = $documentStatusCheck->get();
                                if (isset($documentStatusCheck) && count($documentStatusCheck) > 0) {
                                    array_push($result,$documentStatusCheck);
                                }
                            }
                        }
                    }
                }

            }
        }

        // dd(count($result));
        // dd($result);

        if (isset($result)) {

            foreach ($result as $key => $customerDocuments) {

                foreach ($customerDocuments as $key => $documentStatus) {

                    $data = KYC::where('customer_id',$documentStatus->customer_id)->first();

                    if (isset($data)) {
                        if ($documentStatus == NULL) {
                            $reason = '';
                            $reason .= 'Approved';
                            $data->compliance = 1;
                            $data->status = 'Approve';
                            $data->reason = $reason;
                            $data->save();
                            // return 1;  // 1- compliant , 2 - non-compliant // if empty customers then compliant else non-compliant
                        } else {
                            $reason = '';
                            $reason .= 'Your KYC document(s) is unapproved due to the following reason : <br>';
                            $reasonKyc = '';

                            $documentStatusKeys = array_keys($documentStatus->toArray());
                            foreach ($documentStatusKeys as $key => $value) {

                                if ($value != "customer_id") {
                                    $remarkCol = '';
                                    $docCOl = '';
                                    $docName = '';

                                    $remarkCol = str_replace('Status', 'Remark', $value);
                                    $docCOl = strstr($value, 'Status', true);

                                    if ($value == 'omangFrontStatus') {
                                        $docCOl = strstr($value, 'FrontStatus', true);
                                    }
                                    $docName = str_replace('_', ' ', $docCOl);

                                    if(!empty($data->$remarkCol)) {
                                        $reasonKyc .= $docName . " : " . $data->$remarkCol . "<br>";
                                    }elseif($data->$docCOl == null){
                                        $reasonKyc .= $docName ." : Not Uploaded<br>";
                                    }else{
                                        $reasonKyc .=  $docName .': Reason  Not Mentioned'. "<br>";
                                    }
                                }
                            }

                            $reason = $reason . $reasonKyc;
                            $data->compliance = 2;
                            $data->status = 'rejected';
                            $data->reason = $reason;
                            $data->save();
                            // return 2; // 1- compliant , 2 - non-compliant // if empty customers then compliant else non-compliant
                        }
                    }
                }
            }
        }
          $cron->end = \Carbon\Carbon::now();
        $cron->save();
    }
}
