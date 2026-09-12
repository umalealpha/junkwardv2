<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


use Illuminate\Database\Eloquent\SoftDeletes;

class MisReportCalculation extends Model
{
    use SoftDeletes;

    protected $table = 'mis_report_calculation';

    protected $fillable = [
        'coverage_id',
        's_CoverageCode',
        's_ScreenName',
        'SCL',
        'AFCL',
        'QSCL',
        'TCL',
        'financial_year_start',
        'financial_year',
        'treaty_id',
    ];

    protected $casts = [
        'SCL' => 'decimal:2',
        'AFCL' => 'decimal:2',
        'QSCL' => 'decimal:2',
        'TCL' => 'decimal:2',
    ];
}