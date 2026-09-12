<?php

namespace AlphaDirect;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class VcsNewTransaction extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;
    protected $auditTimestamps = true;

    protected $table = 'vcs_new_transactions';

    protected $fillable = ['reference','name','goods','policyNumber','transType', 'terminal_id','amount',
                            'statusRef','status','originalReferenceNumber','authorision_Date','settlement_Date'];

}
