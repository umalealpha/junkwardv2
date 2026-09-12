<?php

namespace AlphaDirect;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use DB;

class PolicyReinstate extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;
    protected $auditTimestamps = true;

    protected $table ='policy_reinstate';
    protected $fillable = [];
    protected $guarded = ['id'];


    public static function addPolicyReinstated($data){
        $policy_reinstate = DB::table('policy_reinstate')->insertGetId($data);
        return $policy_reinstate;
    }
}
