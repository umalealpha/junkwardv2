<?php
namespace AlphaDirect;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use  AlphaDirect\Country;
use Illuminate\Support\Arr;
use OwenIt\Auditing\Contracts\Auditable;
use DB;
class MobileAppErrorLog extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;
    protected $auditTimestamps = true;

    protected $table = 'mobileApp_error_log';
    protected $fillable = [];
    protected $guarded = ['id'];

    public static function addLog($data){
        DB::table('mobileApp_error_log')->insert($data);
        return array('Status'=>'Success');
    }
}
