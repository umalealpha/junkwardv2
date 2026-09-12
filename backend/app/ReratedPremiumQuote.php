<?php

namespace AlphaDirect;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ReratedPremiumQuote extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;
    protected $auditTimestamps = true;

    protected $table = 'rerated_premium_quotes';

    // No `id` writes from user input; every column on the row is
    // populated from the re-rate engine output, so the safer guard
    // here is $guarded = ['id'] (all other columns fillable). This
    // fixes the MassAssignmentException on quote_number that the
    // POST /api/v1/quotes/{id}/update-premium endpoint was throwing.
    protected $guarded = ['id'];

    public function scopeRateId($query,$rate_id){
        $query->where('rate_id',$rate_id);
    }
}
