<?php

namespace AlphaDirect;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use DB;
use Illuminate\Support\Arr;
use AlphaDirect\Policy;

class ClaimComplaintLog extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;
    protected $auditTimestamps = true;

    protected $table ='claim_complaint_log';
    protected $fillable = [];
    protected $guarded = ['id'];

    protected $casts = [
        'date_filed' => 'date:Y-m-d',
        'closed_at'  => 'date:Y-m-d',
    ];

}
