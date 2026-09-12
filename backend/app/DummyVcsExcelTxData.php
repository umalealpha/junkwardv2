<?php

namespace AlphaDirect;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use DB;
use Illuminate\Support\Arr;
use AlphaDirect\Policy;

class DummyVcsExcelTxData extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;
    protected $auditTimestamps = true;

    protected $table ='dummy_vcs_excel_tx_data';
    protected $fillable = [];
    protected $guarded = ['id'];

}
