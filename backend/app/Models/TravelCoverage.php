<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TravelCoverage extends Model
{
    use HasFactory;
    
    protected $table = 'travel_coverages';
    
    protected $fillable = [];
    
    protected $guarded = ['id'];
    
    protected $casts = [
        'benefits' => 'array',
        'custom_benefits' => 'array',
    ];
    public static function getTravelTotal($policyId)
    {
        // Aggregate premium for the Rate button + V2 Quote / Policy Doc.
        // Sources `total` (policy_amount + vat) — the VAT-inclusive figure
        // actually charged to the customer — not `policy_amount`, which is
        // pre-VAT and previously caused Travel premiums to display without VAT.
        // Sums across all Travel rows on the policy so endorsements/multiple
        // travel schedules aggregate correctly (matches getMedicalTotal pattern).
        try {
            $rows = TravelCoverage::where('policy_id', $policyId)
                ->whereNotNull('policy_coverage_id')
                ->get();
            $total = 0.0;
            foreach ($rows as $row) {
                $val = $row->total;
                if (is_string($val)) {
                    $val = (float) preg_replace('/[^0-9.\-]/', '', $val);
                }
                $total += (float) $val;
            }
            return $total;
        } catch (\Exception $e) {
            return 0;
        }
    }
}

