<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Bound to policy_kyc_documents. Sparse table holding variable-length
 * KYC documents per policy (director IDs, shareholder IDs, etc.) that
 * the flat customer_kyc / customer_kyc_dom_com columns can't represent.
 */
class PolicyKycDocument extends Model
{
    use HasFactory;

    protected $table = 'policy_kyc_documents';
    protected $guarded = ['id'];
}
