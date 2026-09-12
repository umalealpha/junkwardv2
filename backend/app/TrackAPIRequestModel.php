<?php

namespace AlphaDirect;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class TrackAPIRequestModel extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;
    protected $auditTimestamps = true;

    protected $connection = 'mysql2';

    protected $table = 'track_a_p_i_requests';

    protected $fillable = ['url', 'method', 'header', 'input', 'output', 'ip_address', 'ajax_call', 'start_time', 'end_time', 'time', 'duration'];
}
