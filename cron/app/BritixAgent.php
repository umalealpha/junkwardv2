<?php
namespace AlphaDirect;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class BritixAgent extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;
    protected $auditTimestamps = true;

    protected $fillable = ['bitrixId', 'commercial', 'domestic'];

    public function bitrix_agent()
    {
        return $this->belongsTo('AlphaDirect\User', 'bitrixId');

    }

    protected $table ="bitrix_agents";
}
