<?php

namespace AlphaDirect;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use DB;
use Illuminate\Support\Arr;
use AlphaDirect\Policy;
use AlphaDirect\Jobs\ImportUploadedCsv;
use Log;
class VcsTransactionDummyData extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;
    protected $auditTimestamps = true;

    protected $table ='vcs_transaction_dummy_data';
    protected $fillable = [];
    protected $guarded = ['id'];


    public function importData()
    {
        $path = public_path('pending-files/*.csv');
        $file_arr = glob($path);

        $fileData = array_map('str_getcsv',file($file_arr[0]));

        $header = array_slice($fileData,0,1);

        $escapeheader   = [];

        foreach($header[0] as $key=>$value){
            $lowerHeader    = strtolower($value);
            $escapedItems   = preg_replace("/[^a-z]/", "", $lowerHeader);
            $noSpaceString  = preg_replace("/\s+/", "", $escapedItems);
            array_push($escapeheader,$noSpaceString);
        }

        foreach ($file_arr as $key=>$file) {
            Log::info('Vcs csv dispatching job....'.$file.'----'.$key);
            dd($file,$key,$escapeheader);
            ImportUploadedCsv::dispatch($file,$key,$escapeheader);
        }
    }

}
