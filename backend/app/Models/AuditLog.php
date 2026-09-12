<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use HasFactory;

    protected $fillable = ['actor','kyc_case_id','event','details'];
    protected $casts = ['details' => 'encrypted:array'];

    public static function write(string $actor, ?int $caseId, string $event, array $details = [])
    {
        self::create(['actor'=>$actor,'kyc_case_id'=>$caseId,'event'=>$event,'details'=>$details]);
    }
}
