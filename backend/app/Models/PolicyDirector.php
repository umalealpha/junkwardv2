<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Pty Ltd director / shareholder metadata captured during BizSure quote
 * creation. Sole-prop policies have zero rows. KYC document image for
 * each director flows separately via policy_kyc_documents (doc_type =
 * 'director_id', doc_index = this row's director_index).
 */
class PolicyDirector extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'policy_directors';
    protected $guarded = [];
}
