<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One reinsurer's share of one statement line — BR-SEC-08.
 *
 * BOTH BASES ARE STORED because the slips express participations either way and
 * the conversion is where mistakes live. GIC Re's own manuscript note on the
 * Motor slip reads "17% of cession or 11.90% of 100%" — the same line written
 * twice. A table holding only one of them would make every reader redo that
 * arithmetic, and on a 70% cession the two differ by a factor of 1.43.
 *
 * NOTHING IS WRITTEN HERE UNTIL A PANEL REACHES THE CESSION. General is 34.00
 * points short and Motor 33.10. An empty table says "not yet"; provisional
 * splits that do not sum to the cession would say something false.
 */
class TreatyStatementShare extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'treaty_statement_shares';

    protected $guarded = [];

    protected $casts = [
        'share_of_cession_pct' => 'float',
        'share_of_hundred_pct' => 'float',
        'amount'               => 'float',
    ];

    public function item()
    {
        return $this->belongsTo(TreatyStatementItem::class, 'treaty_statement_item_id');
    }
}
