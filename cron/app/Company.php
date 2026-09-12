<?php

namespace AlphaDirect;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Company extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;
    protected $auditTimestamps = true;

    protected $table ='companies';

    protected $fillable = [
        'companyName','companyLocation','email','telephone','ratio'
    ];


    public function Treaty(){

        $this->belongsTo('AlphaDirect\Treaty');
    }
}
