<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Locks & Keys / Key Loss sub-claim. Bound to the V2 sub-claim table
 * `key_loss_claim` (singular). Distinct from the legacy
 * AlphaDirect\ClaimKeyLoss (no Models\ namespace) which is keyed on
 * the V1 `claim_id` flow.
 */
class KeyLossClaim extends Model
{
    use HasFactory;

    protected $table = 'key_loss_claim';
    protected $guarded = ['id'];
}
