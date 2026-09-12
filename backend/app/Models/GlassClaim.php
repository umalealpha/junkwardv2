<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GlassClaim extends Model
{
    use HasFactory;

    // `glass_claim` (singular) is the V2 sub-claim table created by the
    // 2026_05_18 migration. The legacy `glass_claims` (plural) is bound
    // to AlphaDirect\GlassClaim (no Models\ namespace) and stays as-is.
    protected $table = 'glass_claim';
    protected $guarded = ['id'];
}
