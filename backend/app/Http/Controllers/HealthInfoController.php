<?php

namespace AlphaDirect\Http\Controllers;

use AlphaDirect\Models\HealthInfo;
use Illuminate\Http\Request;

class HealthInfoController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'fullName' => 'required|string|max:255',
            'designation' => 'nullable|string|max:255',
            'company' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255|unique:health_infos,email',
            'employees' => 'nullable|integer|min:1',
            'cellPhone' => 'nullable|string|max:15',
            'captcha' => 'required|in:8', // Captcha must equal 8
        ]);

        $healthInfo = HealthInfo::create([
            'full_name' => $request->input('fullName'),
            'designation' => $request->input('designation'),
            'company' => $request->input('company'),
            'email' => $request->input('email'),
            'employees' => $request->input('employees'),
            'cell_phone' => $request->input('cellPhone'),
        ]);

        return response()->json([
            'message' => 'Health info saved successfully!',
            'data' => $healthInfo,
        ], 201);
    }
}
