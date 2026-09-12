<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\CustomerBanking;
use AlphaDirect\RealpayCancelRequests;
use Illuminate\Console\Command;
use AlphaDirect\Http\Controllers\Payment\RealPay\RealPayController;
use AlphaDirect\Models\CronStatus;

class CancelRealPayContract extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cancelrealpaycontract:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cancels RealPay Active Payments';

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
        $cron->name = "cancelrealpaycontract:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
       try{
           $req = RealpayCancelRequests::where('cancel_status',0)->first();
            if($req != null){
//                foreach($requests as $req){
                    $banking = CustomerBanking::where('policy_id', $req->policy_id)->first();
                    $update =  RealpayCancelRequests::where('id',$req->id)->first(array('cancel_status'));
                    $update->cancel_status = 1;
                    $update->save();

                    $RealPayController = new RealPayController();
                    $installments = $RealPayController->getInstallments($banking);

//            $is_merged = $banking->merge_ref;
//
//            if ($is_merged){
//                $data = Policy::where('policyNumber', $banking->client_number)->first();
//                if($data)
//                    $amountToDeduct = $data->premium + $data->vat;
//            }


                    if ($installments != null) {
                        foreach ($installments as $i) {
                            $xml = '';
                            $xml .= '<soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope">
                      <soap:Body xmlns:ns1="http://realpay_trlp/RP_WS.wsdl">
                         <ns1:editInstallmentsElement>
                            <ns1:pUsername>intgalpha</ns1:pUsername>
                            <ns1:pPassword>61efb93ac14777d47a59d400fdfff45b</ns1:pPassword>
                            <ns1:pMerchantNumber>16244</ns1:pMerchantNumber>
                            <ns1:pRequestdata>
                                <ns1:installmentReferenceNumber>' . $i['refNum'] . '</ns1:installmentReferenceNumber>
                                <ns1:ctcAmount></ns1:ctcAmount>
                                <ns1:actionDate></ns1:actionDate>
                                <ns1:tracking></ns1:tracking><ns1:status>I</ns1:status>
                                <ns1:installmentAmount></ns1:installmentAmount>
                                </ns1:pRequestdata>
                            </ns1:editInstallmentsElement>
                        </soap:Body>
                        </soap:Envelope>';

                            $options = [
                                'headers' => [
                                    'Content-Type' => 'application/soap+xml',
                                ],
                                'body' => $xml,
                            ];

                            $client = new \GuzzleHttp\Client();
                            $apiRequest = $client->request('POST', 'https://www.realpaycollect.com:7774/RP_WS-RP_WS-WS/RP_WSSoap12HttpPort', $options);
                        }
                        $response = $apiRequest->getBody()->getContents();
                    }
//                }
            }

       }catch(Exception $ex){
           activity('FAILED')
               ->performedOn($banking)
               ->causedBy(User::where('id',auth()->user()->id)->first())
               ->log('RealPay Payment cancellation failed: '.$ex->getMessage());

           return Redirect::route('admin.accounts.index')->with('success', 'Account Created Successfully');
       }
        $cron->end = \Carbon\Carbon::now();
       $cron->save();
    }
}
