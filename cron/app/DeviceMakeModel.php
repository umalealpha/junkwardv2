<?php

namespace AlphaDirect;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DeviceMakeModel extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;
    protected $auditTimestamps = true;

    protected $table ='device_make_and_model';
    protected $fillable = [];
    protected $guarded = ['id'];

    public function getDeviceTypeAttribute($value)
    {
        return ucwords(strtolower($value));
    }

    public function getnameAttribute($value)
    {
        return ucwords(strtolower($value));
    }

    public function scopeMakeDetail($query, $value)
    {
        return $query
            ->where('id', $value)
            ->orWhere('name', $value)
            ->first(['name', 'id']);
    }
}
