<?php

namespace AlphaDirect;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

use OwenIt\Auditing\Contracts\Auditable;
use SoapClient;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class VcsModel extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;
    protected $auditTimestamps = true;

    protected $table = 'vcs_data_dump';


    public function GetTransactionByTIDAndReference ($referenceNumber){

        $client = new  SoapClient("https://www.vcs.co.za/vOnlineWs/VCSQuery/VCSQuery.svc?wsdl",
        array('trace' => true, 'exceptions' => true, "cache_wsdl" => 0));

        try{

            $status = $client->GetTransactionByTIDAndReference(array(
            "UserID" => "kamleshk3",
            "Password" => "M6iRVcTAMwD5b5d",
            "TerminalID" => "3385",
            "ReferenceNumber" => $referenceNumber
            ))->GetTransactionByTIDAndReferenceResult;
                dd($status);
            return response()->json($status);


        } catch(SOAPFault $fault ) {

            return response()->json($fault);

        }
    }

    public function GetTransactionByTIDAndCardNumber ($cardNumber){

        $client = new  SoapClient("https://www.vcs.co.za/vOnlineWs/VCSQuery/VCSQuery.svc?wsdl",
        array('trace' => true, 'exceptions' => true, "cache_wsdl" => 0));

        try{

            $status = $client->GetTransactionByTIDAndCardNumber(array(
            "UserID" => "kamleshk3",
            "Password" => "M6iRVcTAMwD5b5d",
            "TerminalID" => "3385",
            "CardNumber" => '481783***8990'
            ))->GetTransactionByTIDAndCardNumberResult;;

            return response()->json($status);


        } catch(SOAPFault $fault ) {

            return response()->json($fault);

        }
    }

    public function GetTransactionByDateRange ($startDate , $endDate){

        $client = new  SoapClient("https://www.vcs.co.za/vOnlineWs/VCSQuery/VCSQuery.svc?wsdl",
        array('trace' => true, 'exceptions' => true, "cache_wsdl" => 0));

        try{

            $status = $client->GetTransactionByDateRange(array(
            "UserID" => "kamleshk3",
            "Password" => "M6iRVcTAMwD5b5d",
            "StartDate" => $startDate,
            "EndDate" => $endDate
            ))->GetTransactionByDateRangeResult;

            return response()->json($status);

        } catch(SOAPFault $fault ) {

            return response()->json($fault);

        }
    }
}
