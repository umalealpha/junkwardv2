<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ReinsuranceGroupCoverage extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;
    protected $auditTimestamps = true;

    protected $table = 'reinsurance_group_coverage';
    protected $fillable = [];
    protected $guarded = ['id'];
}
