<?php

namespace AlphaDirect;

use Illuminate\Database\Eloquent\Model;
use AlphaDirect\State;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class City extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;
    protected $auditTimestamps = true;

    protected $table = 'cities';
    protected $guarded = [];
    // Legacy V1 table has no created_at/updated_at columns.
    public $timestamps = false;

    public function state()
    {
        return $this->belongsTo(State::class);
    }
}
