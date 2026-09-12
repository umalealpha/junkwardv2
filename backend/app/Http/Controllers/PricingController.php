<?php

namespace AlphaDirect\Http\Controllers;

use Illuminate\Http\Request;
use AlphaDirect\Models\ADPricing;
use AlphaDirect\Product;
use AlphaDirect\Region;
use AlphaDirect\Services\OpenSanctionsClient;
use Yajra\DataTables\Facades\DataTables;

class PricingController extends Controller
{
         protected $client;

    public function __construct(OpenSanctionsClient $client)
    {
        $this->client = $client;
    }

    // List all records + total main premium
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $data = ADPricing::select([
                'id','gender','product','age_from','age_to',
                'adult_dependent','child_dependent','main'
            ]);

            return DataTables::of($data)
                ->addColumn('action', function($row){
                    $editBtn = '<a href="'.route('pricings.edit', $row->id).'" class="btn btn-sm btn-warning">Edit</a>';
                    $deleteBtn = '<form action="'.route('pricings.destroy',$row->id).'" method="POST" style="display:inline;">
                                    '.csrf_field().method_field("DELETE").'
                                    <button class="btn btn-sm btn-danger" onclick="return confirm(\'Delete this record?\')">Delete</button>
                                </form>';
                    return $editBtn.' '.$deleteBtn;
                })
                ->rawColumns(['action'])
                ->make(true);
        }

        // ✅ Sum "main" instead of total_premium
        $totalPremium = ADPricing::sum('main');
        return view('pricings.index', compact('totalPremium'));
    }

    // Show create form
    public function create()
    {
        return view('pricings.create');
    }

    // Store new record
    public function store(Request $request)
    {
        $request->validate([
            'product' => 'required|string|max:255',
            'gender' => 'required|string',
            'age_from' => 'required|integer',
            'age_to' => 'required|integer',
            'adult_dependent' => 'required|numeric',
            'child_dependent' => 'required|numeric',
            'main' => 'required|numeric',
        ]);

        ADPricing::create([
            'gender' => $request->gender,
            'product' => $request->product,
            'age_from' => $request->age_from,
            'age_to' => $request->age_to,
            'adult_dependent' => $request->adult_dependent,
            'child_dependent' => $request->child_dependent,
            'main' => $request->main,
        ]);

        return redirect()->route('pricings.index')->with('success', 'Record created successfully.');
    }

    // Show edit form
    public function edit(ADPricing $pricing)
    {
        return view('pricings.edit', compact('pricing'));
    }

    // Update record
    public function update(Request $request, ADPricing $pricing)
    {
        $request->validate([
            'product' => 'required|string|max:255',
            'gender' => 'required|string',
            'age_from' => 'required|integer',
            'age_to' => 'required|integer',
            'adult_dependent' => 'required|numeric',
            'child_dependent' => 'required|numeric',
            'main' => 'required|numeric',
        ]);

        $pricing->update([
            'gender' => $request->gender,
            'product' => $request->product,
            'age_from' => $request->age_from,
            'age_to' => $request->age_to,
            'adult_dependent' => $request->adult_dependent,
            'child_dependent' => $request->child_dependent,
            'main' => $request->main,
        ]);

        return redirect()->route('pricings.index')->with('success', 'Record updated successfully.');
    }

    // Delete record
    public function destroy(ADPricing $pricing)
    {
        $pricing->delete();
        return redirect()->route('pricings.index')->with('success', 'Record deleted successfully.');
    }

    // Calculate premium based on product and member data
    public function calculatePremium(Request $request)
    {
        $data = $request->all();

        // Validate request
        if (!isset($data['product']) || !isset($data['subData']) || !is_array($data['subData'])) {
            return response()->json([
                'error' => 'Invalid request format'
            ], 400);
        }

        $product = $data['product'];
        $members = $data['subData'];

        $result = [];
        $grandTotal = 0;
        $subGrandTotal = 0;

        foreach ($members as $member) {
            $age = $member['age'];
            $gender = $member['gender'];
            $applicant = strtolower($member['applicant']); // "main" or "sub"

            // Find matching pricing row
            $pricing = ADPricing::where('product', $product)
                ->where('gender', $gender)
                ->where('age_from', '<=', $age)
                ->where('age_to', '>=', $age)
                ->first();

            if (!$pricing) {
                $result[] = [
                    'age' => $age,
                    'gender' => $gender,
                    'premium' => 0,
                    'error' => 'No matching pricing found'
                ];
                continue;
            }

            // ✅ Calculate premium based on applicant type
            $premium = 0;

            if ($applicant === 'main') {
                $premium = $pricing->main;
            } elseif ($applicant === 'sub') {
                if ($age <= 19) {
                    $premium = $pricing->child_dependent;
                } else {
                    $premium = $pricing->adult_dependent;
                }
            }

            $result[] = [
                'age' => $age,
                'gender' => $gender,
                'applicant' => $applicant,
                'premium' => round($premium, 2)
            ];

            $subGrandTotal += $premium;
        }

        $product = Product::where('id', $data['product_id'])->first('region_id');
        $regionVat = Region::where('id', $product->region_id)->first(array('vat'))->vat;
        $grandTotal = isset($subGrandTotal)? ($subGrandTotal * ($regionVat / 100)) + $subGrandTotal : 0;

        return response()->json([
            'members' => $result,
            'total_premium' => round($grandTotal, 2)
        ]);
    }




    public function match(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'dob' => 'nullable|date',
            'nationality' => 'nullable|string',
        ]);

        $response = $this->client->match(
            $request->input('name'),
            $request->input('dob'),
            $request->input('nationality')
        );

        return response()->json($response);
    }
}
