<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RepairCenterController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = DB::table('repair_centers')->orderBy('name');
        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->input('search') . '%');
        }
        return response()->json(['data' => $query->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'     => 'required|string|max:200',
            'email'    => 'nullable|email|max:200',
            'mobile'   => 'nullable|string|max:50',
            'vat'      => 'nullable|string|max:50',
            'city_id'  => 'nullable|integer',
            'state_id' => 'nullable|integer',
        ]);
        $data['created_at'] = now();
        $data['updated_at'] = now();
        $id = DB::table('repair_centers')->insertGetId($data);
        return response()->json(['message' => 'Repair center created.', 'data' => ['id' => $id]], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'name'     => 'required|string|max:200',
            'email'    => 'nullable|email|max:200',
            'mobile'   => 'nullable|string|max:50',
            'vat'      => 'nullable|string|max:50',
            'city_id'  => 'nullable|integer',
            'state_id' => 'nullable|integer',
        ]);
        $data['updated_at'] = now();
        DB::table('repair_centers')->where('id', $id)->update($data);
        return response()->json(['message' => 'Repair center updated.']);
    }

    public function destroy(int $id): JsonResponse
    {
        DB::table('repair_centers')->where('id', $id)->delete();
        return response()->json(['message' => 'Repair center deleted.']);
    }
}
