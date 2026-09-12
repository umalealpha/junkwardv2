<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Professional Indemnity sub-claim. Distinct from `ProfessionalIndemnity`
 * (already present in V2 as a policy-side coverage model). Named
 * ProfessionalIndemnityClaim to avoid colliding with that policy model.
 */
class ProfessionalIndemnityClaim extends Model
{
    use HasFactory;

    protected $table = 'professional_indemnity_claims';
    protected $guarded = ['id'];
}
