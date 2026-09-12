<?php

namespace AlphaDirect;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use DB;
use Illuminate\Support\Arr;
use AlphaDirect\Policy;

class CustomerKycDomComHistory extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;
    protected $auditTimestamps = true;

    protected $table ='customer_kyc_dom_com_histories';
    protected $fillable = [];
    protected $guarded = ['id'];

    public function transformAudit(array $data): array
    {

        return $data;

    }

    public function generateTags(): array
    {
        return [
            'customer kyc dom com history'.' '.$this->auditEvent,
        ];
    }

}
