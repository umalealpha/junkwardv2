<?php
namespace AlphaDirect;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;
use DB;

class PolicySlip extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'policy_id',
        'slip_year',
        'sequence_no',
        'policy_slip_no',
    ];
}


?>