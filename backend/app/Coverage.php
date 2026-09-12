<?php

namespace AlphaDirect;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use Yajra\DataTables\DataTables;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Coverage extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;
    protected $auditTimestamps = true;

    protected $table = 'coverages';

    protected $fillable = ['name', 'limit', 'risk_type', 'status'];

    protected function risk()
    {
        return $this->belongsTo('AlphaDirect\RiskType', 'risk_type');
    }

    public function scopecoverageData($query)
    {
        $coverage = Coverage::get(array('id', 'name', 'status', 'risk_type', 'created_at'));
        return DataTables::of($coverage)

        ->editColumn('created_at', function ($coverage) {
            if ($coverage->created_at != null) {
                return   \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $coverage->created_at)->format('Y-m-d H:i') ;
            }
        })

            ->editColumn('status', function ($coverage) {
                if ($coverage->status) {
                    return 'Active';
                } else {
                    return 'In-Active';
                }

            })

            ->editColumn('risk_type', function ($coverage) {
                $riskName = RiskType::where('id', $coverage->risk_type)->first(array('name'));

                return $riskName ? $riskName->name : '';

            })

            ->addColumn('actions', function ($coverage) {
                $actions = '<a href="' . route('coverage.edit', $coverage->id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Edit">
                                <i class="la la-edit"></i>
                            </a>
                            <a href="" value="' . $coverage->id . '" class="btn btn-sm btn-clean btn-icon btn-icon-md confirm-delete" title="Delete">
                                <i class="la la-trash"></i>
                            </a>';
                return $actions;
            })
            ->rawColumns(['actions'])
            ->make(true);
    }
    public function storeCoverage($coverage)
    {
        try {
            //code...
            $new_coverage = new Coverage();
            $new_coverage->name = $coverage->name;
            //check status before submitting
            if ($coverage->status == null) {
                $new_coverage->status = "0";
            } else {
                $new_coverage->status = "1";
            }
            $new_coverage->limit = $coverage->limit;
            $new_coverage->risk_type = $coverage->risk_type;
            $new_coverage->save();
            return response()->json(['status' => 'success'], 200);
        } catch (\Exception $ex) {
            return response()->json(['error' => $ex->getMessage()], 500);
        }

    }

    public function updateCoverage($id, $coverage)
    {

    }

    public function destroyCoverage($id)
    {

    }

    /**
     * Check the status is activated or not
     */
    public function scopeActivated($query)
    {
        return $query->where('status', 1);
    }

}
