<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Model;

class AdGroupKycDocument extends Model
{
    protected $table = 'ad_group_kyc_documents';

    protected $fillable = [
        'submission_id',
        'field_key',
        'original_name',
        'mime_type',
        'size',
        'path',
        'url',
    ];
}


