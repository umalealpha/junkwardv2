<?php

namespace AlphaDirect\Http\Controllers\Payment\RealPay;

use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use AlphaDirect\Customer;
use AlphaDirect\Banks;
use AlphaDirect\BankBranches;
use Redirect;

class RealPayTestController extends Controller
{


    public function addClientContract(Request $request)
    {

        $client = new \GuzzleHttp\Client();
        $url = "https://www.realpaycollect.com:7774/RP_WS-RP_WS-WS/RP_WSSoap12HttpPort";

        $xml = '
            <soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope" xmlns:rp="http://realpay_trlp/RP_WS.wsdl">
               <soap:Header/>
               <soap:Body>
                  <rp:addEditClientCrdElement>
                     <rp:pUsername>intgalpha</rp:pUsername>
                     <rp:pPassword>61efb93ac14777d47a59d400fdfff45b</rp:pPassword>
                     <rp:pMerchantNumber>16244</rp:pMerchantNumber>
                     <!--Zero or more repetitions:-->
                     <rp:pRequestdata>
                        <rp:cardExpDt>' . $request->cardExp . '</rp:cardExpDt>
                        <rp:bankAccountType>1</rp:bankAccountType>
                        <rp:clientNumber>' . $request->referenceNumber . '</rp:clientNumber>
                        <rp:bankNum>' . $request->bankNum . '</rp:bankNum>
                        <rp:empCode>OT</rp:empCode>
                        <rp:clientName>' . $request->name . '</rp:clientName>
                        <rp:bankBranchNum>' . $request->bankBranchNum . '</rp:bankAccountNum>
                        <rp:idNumber>' . $request->idNumber . '</rp:idNumber>
                        <rp:bankAccountName>' . $request->name . '</rp:bankAccountName>
                        <rp:cardNum>' . $request->cardNum . '</rp:cardNum>
                     </rp:pRequestdata>
                  </rp:addEditClientCrdElement>
               </soap:Body>
            </soap:Envelope>
            ';

        $options = [
            'headers' => [
                'Content-Type' => 'application/soap+xml',
            ],
            'body' => $xml,
        ];
        try {

            $apiRequest = $client->request('POST', $url, $options);
            $response = $apiRequest->getBody()->getContents();
            return $response;
        } catch (RequestException $re) {
            // For handling exception.
            return response()->json([
                "code" => "401",
                "message" => "An error has occured",
            ]);
        }
    }
}
