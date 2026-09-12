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
    protected $fillable = [];

    public function state()
    {
        return $this->belongsTo(State::class);
    }
}
