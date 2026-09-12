<?php

namespace AlphaDirect;

use Illuminate\Database\Eloquent\Model;

use Carbon\Carbon;
use DB;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Region extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;
    protected $auditTimestamps = true;

    protected $table = 'regions';
    protected $fillable = [];
    protected $guarded = ['id'];
}
