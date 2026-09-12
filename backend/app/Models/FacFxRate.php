<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * An exchange rate WITH its date and its source. 1 unit of `currency` = `rate` BWP.
 *
 * Exists because the master workbook's Rates tab is empty and June's USD rate was
 * typed by hand with no source and no date, which is unauditable.
 */
class FacFxRate extends Model
{
    protected $table = 'fac_fx_rates';
    protected $guarded = ['id'];

    protected $casts = [
        'rate_date' => 'date',
        'rate'      => 'decimal:8',
    ];
}
