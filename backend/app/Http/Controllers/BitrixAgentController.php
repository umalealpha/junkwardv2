<?php

namespace AlphaDirect\Http\Controllers;

use AlphaDirect\BritixAgent;
use Illuminate\Http\Request;
use Response;
use Yajra\DataTables\DataTables;

class BitrixAgentController extends Controller
{

    public function index()
    {

        try {
            $bitrixAgentProduct = BritixAgent::get('product');
            $result = (array) $bitrixAgentProduct;

            return view('/bitrixAgent/index', compact('bitrixAgentProduct'));
        } catch (Exception $ex) {
            return response()->json(['error' => $ex->getMessage()]);
        }
    }

    public function create()
    {
        return view('/bitrixAgent/create');
    }

    public function data(Request $request)
    {
        try {

            $query = BritixAgent::orderBy('id', 'desc');

            if ($request->bitrix_agent_product != -1) {
                $query->where('product', $request->bitrix_agent_product);
            }
            $bitrixAgent = $query->get(array('id', 'email', 'department', 'product', 'active'));

            return DataTables::of($bitrixAgent)

                ->addColumn('commercial', function ($bitrixAgent) {
                    if ($bitrixAgent->product == 'Commercial') {
                        return '<input name="agent_type" type="checkbox" data-agent-type="' . $bitrixAgent->product . '" data-agentID ="' . $bitrixAgent->id . '" class="input-group agent_type" checked value="Commercial" >';
                    } else {
                        return '<input name="agent_type" type="checkbox" data-agent-type="' . $bitrixAgent->product . '" data-agentID ="' . $bitrixAgent->id . '" class="input-group agent_type"  value="Commercial" >';
                    }
                })
                ->addColumn('domestic', function ($bitrixAgent) {
                    if ($bitrixAgent->product == 'Domestic') {
                        return '<input name="agent_type" type="checkbox"  data-agent-type="' . $bitrixAgent->product . '"  data-agentID ="' . $bitrixAgent->id . '" class="input-group agent_type" checked value="Domestic">';
                    } else {
                        return '<input name="agent_type" type="checkbox" data-agent-type="' . $bitrixAgent->product . '" data-agentID ="' . $bitrixAgent->id . '" class="input-group agent_type"  value="Domestic" >';
                    }
                })
                ->editColumn('active', function ($bitrixAgent) {
                    if ($bitrixAgent->active == null) {
                        return '<span class="kt-badge  kt-badge--brand kt-badge--inline kt-badge--pill">Not-active</span>';
                    } elseif ($bitrixAgent->active == 1) {
                        return '<span class="kt-badge  kt-badge--success kt-badge--inline kt-badge--pill">Active</span>';
                    } elseif ($bitrixAgent->active == 2) {
                        return '<span class="kt-badge  kt-badge--warning kt-badge--inline kt-badge--pill">Suspended</span>';
                    }
                })
                ->rawColumns(['commercial', 'domestic', 'active'])
                ->make(true);
        } catch (\Excpetion $ex) {
            //throw $th;
            return response()->json($ex->getMessage);
        }

    }

    public function store(Request $request)
    {
        try {

            $bitrixAgent = new BritixAgent();
            $bitrixAgent->email = $request->email;
            $bitrixAgent->work_phone = $request->phone;
            $bitrixAgent->department = $request->department;
            $bitrixAgent->product = $request->product;
            $bitrixAgent->save();

            return redirect()->route('bitrixAgent.index')->with('success', 'Bitrix Agent created');
        } catch (Exception $ex) {
            return response()->json([
                'error' => $ex->getMessage(),

            ]);
        }
    }

    public function getbitrixAgents(Request $request)
    {
        try {

            $bitrixAgents = BritixAgent::where('product', $request->agent_type)->where('active', '1')->get('id');
            return response()->json($bitrixAgents);

        } catch (Exception $ex) {
            return response()->json($ex->getMessage());
        }

    }

    public function updateBitrixAgent(Request $request)
    {
        try {

            $bitrixAgent = BritixAgent::findOrFail($request->id);
            $agent_type = $request->agent_type;
            $selectedAgentChange = $request->selectedAgentType;

            switch ($selectedAgentChange) {
                case 'Commercial':
                    if ($request->checkedVal == '1') { //1 means checked, which means trying to set the agen as Commercial

                        $bitrixAgent->product = 'Commercial';
                        $bitrixAgent->active = '1';
                        $bitrixAgent->save();

                    } elseif ($request->checkedVal == '0') { //0 means has been unchecked, the commercial agent is disabled
                        $bitrixAgent->product = '';
                        $bitrixAgent->active = '';
                        $bitrixAgent->save();
                    }
                    return response()->json(['status' => 200]);
                    break;
                case 'Domestic':
                    if ($request->checkedVal == '1') { //

                        $bitrixAgent->product = 'Domestic';
                        $bitrixAgent->active = '1';
                        $bitrixAgent->save();

                    } elseif ($request->checkedVal == '0') {
                        $bitrixAgent->product = '';
                        $bitrixAgent->active = '';
                        $bitrixAgent->save();
                    }
                    return response()->json(['status' => 200]);
                    break;
                default:
                    return null;
                    break;
            }

        } catch (Exception $ex) {
            return response()->json($ex->getMessage());
        }
    }
}
