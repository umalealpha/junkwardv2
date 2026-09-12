<?php
namespace AlphaDirect\Http\Controllers\Admin;

use AlphaDirect\Coverage;
use AlphaDirect\Models\CoverageMaster;
use AlphaDirect\Product;
use AlphaDirect\Models\ProductCoverage;
use AlphaDirect\Models\ReinsuranceTreaty;
use AlphaDirect\Models\ReinsuranceFormula;
use Illuminate\Http\Request;
use AlphaDirect\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Yajra\DataTables\DataTables;
use Illuminate\Support\Facades\Redirect;

class ReinsuranceTreatyController extends Controller
{
    /**
     * Show a list of all Reinsurance Treaties.
     *
     * @return View Reinsurance Treaty listing page
     */
    public function index()
    {
        if (Auth::user()->hasPermissionTo('reinsurance-treaty-list')){
            return view('admin.reinsuranceTreaty.index');
        }
        else{
            return Redirect::back()
                ->with('error', 'Sorry! You do not have permission to access this page!');
        }
    }

    public function new_index()
    {
        if (Auth::user()->hasPermissionTo('reinsurance-treaty-list')){
            return view('admin.reinsuranceTreaty.new-index');
        }
        else{
            return Redirect::back()
                ->with('error', 'Sorry! You do not have permission to access this page!');
        }
    }

    /**
     * Show a page to create for new Reinsurance Treaty.
     *
     * @return View
     */
    public function create()
    {
        if (Auth::user()->hasPermissionTo('reinsurance-treaty-create')){
            $reinsuranceformula = ReinsuranceFormula::get();

            return view('admin.reinsuranceTreaty.create', compact('reinsuranceformula'));
        }
        else{
            return Redirect::back()->with('error', 'Sorry! You do not have permission to access this page!');
        }
    }

    public function createTreaty()
    {
        if (Auth::user()->hasPermissionTo('reinsurance-treaty-create')){
            $coverages = CoverageMaster::where('s_CoverageGroupCode','MAIN')->where('s_UsageType','PARENT')->orderBy('id','desc')->get(['id','s_CoverageName']);
            // SELECT * FROM tb_cvgpccoverages where s_CoverageGroupCode = 'MAIN' and s_UsageType = 'PARENT'
            return view('admin.reinsuranceTreaty.newcreate', compact('coverages'));
        }
        else{
            return Redirect::back()->with('error', 'Sorry! You do not have permission to access this page!');
        }
    }

    /**
     * method to store Reinsurance Treaty data from create page.
     *
     * @return View
     */
    public function store(Request $request)
    {
        $reinsurancetreaty = new ReinsuranceTreaty();

        $reinsurancetreaty->treaty_name = $request->treatyname;
        $reinsurancetreaty->treaty_number = $request->treatynumber;
        $reinsurancetreaty->effective_from = $request->effectivefrom;
        $reinsurancetreaty->effective_to = $request->effectiveto;
        $reinsurancetreaty->provisional_commission = $request->provisional_commission ?? 0;
        $reinsurancetreaty->proportional_share = $request->proportional_share ?? 0;
        $reinsurancetreaty->cash_loss_advise = $request->cash_loss_advise ?? 0;
        $reinsurancetreaty->event_limit = $request->event_limit ?? 0;
        $reinsurancetreaty->exclusions = $request->exclusions ?? '';
        $reinsurancetreaty->formula_attached = $request->formula ? implode(',', array_filter($request->formula)) : '';
        if ($request->status == NULL)
        {
            $reinsurancetreaty->status = "0";
        }
        else
        {
            $reinsurancetreaty->status = "1";
        }
        if ($reinsurancetreaty->save())
        {

            return Redirect::route('admin.reinsuranceTreaty.index')
                ->with('success', 'Reinsurance Treaty Created Successfully');
        }
        else
        {
            return Redirect::route('admin.reinsuranceTreaty.index')
                ->with('error', 'Something Went Wrong');
        }

    }

    /*
    * Pass data through ajax call
    */
    /**
     * @return mixed
     */
    public function data()
    {
        $reinsurancetreaty = ReinsuranceTreaty::get(array(
            'id',
            'treaty_name',
            'treaty_number',
            'effective_from',
            'effective_to',
            'formula_attached',
            'status',
            'created_at'
        ));

        return DataTables::of($reinsurancetreaty)
            ->addColumn('formula_attached', function ($reinsurancetreaty)
            {
                $formula = ReinsuranceFormula::wherein('id', explode(',', $reinsurancetreaty->formula_attached))
                    ->get(['formula_name']);
                $geting = array();
                foreach ($formula as $old)
                {
                    array_push($geting, $old->formula_name);
                }
                return implode(',', $geting) ? implode(',', $geting) : 'NA';
            })
            ->editColumn('treaty_name', function ($reinsurancetreaty)
            {
                if ($reinsurancetreaty->treaty_name)
                {
                   return ucwords($reinsurancetreaty->treaty_name);
                }
                else
                {
                    return '--';
                }
            })
            ->editColumn('effective_from', function ($reinsurancetreaty)
            {
                if ($reinsurancetreaty->effective_from)
                {
                   return Carbon::parse($reinsurancetreaty->effective_from)->format('Y-m-d');
                }
                else
                {
                    return '--';
                }
            })

            ->editColumn('effective_to', function ($reinsurancetreaty)
            {
                if ($reinsurancetreaty->effective_to)
                {
                   return Carbon::parse($reinsurancetreaty->effective_to)->format('Y-m-d');
                }
                else
                {
                    return '--';
                }
            })

            ->editColumn('status', function ($reinsurancetreaty)
            {
                if ($reinsurancetreaty->status)
                {
                    return 'Active';
                }
                else
                {
                    return 'In-Active';
                }
            })->editColumn('created_at', function ($reinsurancetreaty)
            {
                return $reinsurancetreaty
                    ->created_at
                    ->diffForHumans();
            })->addColumn('actions', function ($reinsurancetreaty)
            {
                $actions = '';
                if (Auth::user()->hasPermissionTo('reinsurance-treaty-edit'))
                {
                    $actions .= '<a href="' . route('admin.reinsuranceTreaty.edit', $reinsurancetreaty->id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Edit">
                                <i class="la la-edit"></i>
                            </a>';
                }
                else
                {
                    $actions .= '<a href="' . route('admin.reinsuranceTreaty.edit', $reinsurancetreaty->id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Edit">
                                <i class="flaticon-eye"></i>
                            </a>';
                }
                if (Auth::user()
                    ->hasPermissionTo('reinsurance-treaty-delete'))
                {
                    $actions .= '<a href="" value="' . $reinsurancetreaty->id . '" class="btn btn-sm btn-clean btn-icon btn-icon-md confirm-delete" title="Delete">
                                <i class="la la-trash"></i>
                            </a>';
                }

                return $actions;
            })->rawColumns(['actions', 'formula_attached'])
            ->make(true);
    }

    /**
     * Show a page to edit specific Reinsurance Treaty.
     *param: Reinsurance Treaty ID ($id)
     * @return View Reinsurance Treaty edit page
     */
    public function edit($id)
    {
        $reinsurancetreaty = ReinsuranceTreaty::where('id', $id)->first();

        $effective_from = Carbon::parse($reinsurancetreaty->effective_from)->format('Y-m-d');
        $effective_to =  Carbon::parse($reinsurancetreaty->effective_to)->format('Y-m-d');
        $reinsuranceformula = ReinsuranceFormula::get();

        if (Auth::user()->hasPermissionTo('reinsurance-treaty-edit'))
        {
            return view('admin.reinsuranceTreaty.edit', compact('reinsurancetreaty', 'reinsuranceformula','effective_from','effective_to'));
        }
        elseif (Auth::user()
            ->hasPermissionTo('reinsurance-treaty-list'))
        {
            return view('admin.reinsuranceTreaty.view', compact('reinsurancetreaty', 'reinsuranceformula','effective_from','effective_to'));
        }
        else
        {
            return Redirect::back()
                ->with('error', 'Sorry! You do not have permission to access this page!');
        }
    }

    /**
     * method to update Reinsurance Treaty data from edit page.
     *param: Reinsurance Treaty ID ($id)
     * @return View
     */
    public function update($id, Request $request)
    {
        $reinsurancetreaty = ReinsuranceTreaty::where('id', $id)->first();
        $reinsurancetreaty->treaty_name = $request->get('treatyname');
        $reinsurancetreaty->treaty_number = $request->get('treatynumber');
        $reinsurancetreaty->effective_from = $request->get('effectivefrom');
        $reinsurancetreaty->effective_to = $request->get('effectiveto');
        $reinsurancetreaty->provisional_commission = $request->get('provisional_commission') ?? 0;
        $reinsurancetreaty->proportional_share = $request->get('proportional_share') ?? 0;
        $reinsurancetreaty->cash_loss_advise = $request->get('cash_loss_advise') ?? 0;
        $reinsurancetreaty->event_limit = $request->get('event_limit') ?? 0;
        $reinsurancetreaty->exclusions = $request->get('exclusions') ?? '';
        if($request->get('formula') != null)
            $reinsurancetreaty->formula_attached = implode(',', array_filter($request->get('formula')));

        if ($request->status == NULL)
        {
            $reinsurancetreaty->status = "0";
        }
        else
        {
            $reinsurancetreaty->status = "1";
        }
        if ($reinsurancetreaty->save())
        {

            return Redirect::route('admin.reinsuranceTreaty.index')
                ->with('success', 'Reinsurance Treaty Updated Successfully');
        }
        else
        {
            return redirect()
                ->back()
                ->with('error', 'Something Went Wrong');
        }
    }

    /**
     * method to return modal body for confirm-delete.
     *
     * @return JSON
     */
    public function getModalDelete(Request $request)
    {
        $body = 'Are you sure you want to delete the Reinsurance Treaty?';
        return response()->json(['status' => 'success', 'id' => $request->get('id') , 'body' => $body]);

    }

    /**
     *deletes specific Reinsurance Treaty
     *param: Reinsurance Treaty id ($id)
     * @return Reinsurance Treaty listing page
     */
    public function destroy($id)
    {
        try
        {
            $delete = ReinsuranceTreaty::where('id', $id)->delete();

            return Redirect::route('admin.reinsuranceTreaty.index')
                ->with('success', 'Reinsurance Treaty Deleted Successfully');

        }
        catch(Exception $e)
        {
            return Redirect::route('admin.reinsuranceTreaty.index')->with('error', 'Something Went Wrong');
        }

    }
}

