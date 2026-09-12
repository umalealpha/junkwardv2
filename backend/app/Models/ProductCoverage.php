<?php

namespace AlphaDirect\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
class ProductCoverage extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;
    protected $auditTimestamps = true;

    protected $table = 'product_coverage';

    public function coverage()
    {
        return $this->hasOne('AlphaDirect\Models\CoverageMaster', 'id', 'coverage_id');
    }

    public static function getProductCoverages($product_id)
    {
        return ProductCoverage::join('tb_cvgpccoverages','product_coverage.coverage_id','tb_cvgpccoverages.id')
                                ->where('product_coverage.product_id',$product_id)
                                ->where('tb_cvgpccoverages.s_UsageType', 'PARENT')
                                ->orderby('tb_cvgpccoverages.id', 'asc' )
                                ->pluck('tb_cvgpccoverages.s_CoverageCode','tb_cvgpccoverages.id')
                                ->toArray();
    }

    public static function getProductCov($product_id)
    {
        if ($product_id == 7) {
            return ProductCoverage::join('tb_cvgpccoverages','product_coverage.coverage_id','tb_cvgpccoverages.id')
                                ->where('product_coverage.product_id',$product_id)
                                ->where('tb_cvgpccoverages.s_UsageType', 'PARENT')
                                ->where('tb_cvgpccoverages.s_DISPLAYTOUSER','=','1')
                                ->whereNotIn('product_coverage.id', [217,218,219,220,221,222,223,224,225,226,227,228])
                                // ->orderBy('tb_cvgpccoverages.n_DisplaySequence','asc')
                                ->orderby('tb_cvgpccoverages.id', 'asc' )
                                ->select('tb_cvgpccoverages.*','product_coverage.product_id')
                                ->get();
        } elseif ($product_id == 8) {
            return ProductCoverage::join('tb_cvgpccoverages','product_coverage.coverage_id','tb_cvgpccoverages.id')
                                ->where('product_coverage.product_id',$product_id)
                                ->where('tb_cvgpccoverages.s_UsageType', 'PARENT')
                                ->where('tb_cvgpccoverages.s_DISPLAYTOUSER','=','1')
                                ->whereNotIn('product_coverage.id', [217,218,219,220,221,222,223,224,225,226,227,228])
                                ->orderBy('tb_cvgpccoverages.n_DisplaySequence','asc')
                                // ->orderby('tb_cvgpccoverages.id', 'asc' )
                                ->select('tb_cvgpccoverages.*','product_coverage.product_id')
                                ->get();
        }
        else if($product_id == 16){
            return ProductCoverage::join('tb_cvgpccoverages','product_coverage.coverage_id','tb_cvgpccoverages.id')
                                ->where('product_coverage.product_id',$product_id)
                                ->where('tb_cvgpccoverages.s_UsageType', 'PARENT')
                                ->where('tb_cvgpccoverages.s_DISPLAYTOUSER','=','1')
                                ->orderBy('tb_cvgpccoverages.n_DisplaySequence','asc')
                                ->select('tb_cvgpccoverages.*','product_coverage.product_id')
                                ->get();
        }
        else if($product_id == 17){
            return ProductCoverage::join('tb_cvgpccoverages','product_coverage.coverage_id','tb_cvgpccoverages.id')
                                ->where('product_coverage.product_id',$product_id)
                                ->where('tb_cvgpccoverages.s_UsageType', 'PARENT')
                                ->where('tb_cvgpccoverages.s_DISPLAYTOUSER','=','1')
                                ->orderBy('tb_cvgpccoverages.n_DisplaySequence','asc')
                                ->select('tb_cvgpccoverages.*','product_coverage.product_id')
                                ->get();
        }
    }

}
