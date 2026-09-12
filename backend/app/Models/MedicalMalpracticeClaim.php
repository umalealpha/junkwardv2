<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Medical Malpractice sub-claim. Named *Claim to avoid colliding with
 * the policy-side MedicalMalpracticeCoverage model already present
 * in V2.
 */
class MedicalMalpracticeClaim extends Model
{
    use HasFactory;

    protected $table = 'medical_malpractice_claims';
    protected $guarded = ['id'];
}
