<?php

namespace AlphaDirect;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class OTP extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;
    protected $auditTimestamps = true;
    protected $connection  = 'mysql::write';

    protected $table = 'otp';

    protected $fillable = [
        'cellphone', 'otp',
    ];

    //generate OTP

    // authenticateOTP

    public function scopeauthenticateOTPCodeUsingOtpCodeAndCellphone($query, $otp_code, $cellphone)
    {
        return $query->where('otp', $otp_code)->where('cellphone', $cellphone)->exists();
    }

    public function scopegetOTPDataUsingOTPCode($query, $otp_code)
    {
        return $query->where('otp', $otp_code)->first();
    }

    public function scopedeleteOTP($query, $otp_id)
    {
        return $query->findOrFail($otp_id)->delete();
    }

    public function scopeOTPStore($query, $cellphone)
    {
        try {
            //code...
            $otp = new OTP();
            $otp_generate = Helper::gen_ustring(100000, 999999);
            $otp->cellphone = $cellphone;
            $otp->otp = $otp_generate;
            $otp->save();
            return response()->json(['id'=>$otp->id,'otp_code' => $otp->otp], 200);
        } catch (\Excpetion $ex) {
            //throw $th;
            return response()->json(['error' => $ex->getMessage(), 'line' => $ex->line()], 500);
        }

    }
}
