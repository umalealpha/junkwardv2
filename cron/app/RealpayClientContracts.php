<?php

namespace AlphaDirect;

use Illuminate\Database\Eloquent\Model;
use DB;
use OwenIt\Auditing\Contracts\Auditable;
use function GuzzleHttp\Psr7\str;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class RealpayClientContracts extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;
    protected $auditTimestamps = true;

    protected $table = 'realpay_client_contracts';
    protected $fillable = [];
    protected $guarded = ['id'];

    public static function addLog($data){
        DB::table('realpay_client_contracts')->insert($data);
        return array('Status'=>'Success');
    }

    public static function getContractNumber($policy_id){
        $data = RealpayClientContracts::where('policy_id',$policy_id)->get();
        $count = count($data);

        return (string)$policy_id.'/'.(string)($count+1);
    }
}
