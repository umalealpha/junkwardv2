<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Console\Commands\KycComplianceCheck;
use AlphaDirect\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class KycComplianceApiController extends Controller
{
    /**
     * GET /kyc-compliance — List all compliance rules with product assignments.
     */
    public function index(): JsonResponse
    {
        $rules = DB::table('kyc_compliance')
            ->orderBy('name')
            ->get(['id', 'name', 'fields', 'flow_id', 'created_at', 'updated_at']);

        // Decode fields JSON for each rule
        $rules = $rules->map(function ($r) {
            $r->fields = json_decode($r->fields, true) ?? [];
            return $r;
        });

        // Get products that reference each compliance rule
        $products = DB::table('products')
            ->whereNotNull('kyc_compliance')
            ->where('kyc_compliance', '>', 0)
            ->get(['id', 'name', 'kyc_compliance']);

        $productsByRule = $products->groupBy('kyc_compliance')->map(fn($g) => $g->map(fn($p) => [
            'id'   => $p->id,
            'name' => $p->name,
        ])->values());

        // Get all KYC fields for the form
        $kycFields = DB::table('kyc_fields')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'customer_kyc_column']);

        return response()->json([
            'data'      => $rules->map(fn($r) => [
                'id'        => $r->id,
                'name'      => $r->name,
                'fields'    => $r->fields,
                'flow_id'   => $r->flow_id,
                'products'  => $productsByRule[$r->id] ?? [],
                'createdAt' => $r->created_at,
                'updatedAt' => $r->updated_at,
            ]),
            'kycFields' => $kycFields,
        ]);
    }

    /**
     * POST /kyc-compliance — Create a new compliance rule.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'           => 'required|string|max:100',
            'flow_id'        => 'nullable|string|max:200',
            'fields'         => 'required|array',
            'fields.*.field' => 'required|string',
            'fields.*.check' => 'required|integer|in:1,2,3',
            'fields.*.other' => 'nullable|string',
            'product_ids'    => 'nullable|array',
            'product_ids.*'  => 'integer',
        ]);

        $id = DB::table('kyc_compliance')->insertGetId([
            'name'       => $data['name'],
            'flow_id'    => $data['flow_id'] ?? null,
            'fields'     => json_encode($data['fields']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Assign products to this rule
        if (!empty($data['product_ids'])) {
            DB::table('products')
                ->whereIn('id', $data['product_ids'])
                ->update(['kyc_compliance' => $id]);
        }

        return response()->json([
            'message' => 'KYC compliance rule created.',
            'data'    => ['id' => $id],
        ], 201);
    }

    /**
     * PUT /kyc-compliance/{id} — Update a compliance rule.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $rule = DB::table('kyc_compliance')->where('id', $id)->first();
        if (!$rule) {
            return response()->json(['message' => 'KYC compliance rule not found.'], 404);
        }

        $data = $request->validate([
            'name'           => 'required|string|max:100',
            'flow_id'        => 'nullable|string|max:200',
            'fields'         => 'required|array',
            'fields.*.field' => 'required|string',
            'fields.*.check' => 'required|integer|in:1,2,3',
            'fields.*.other' => 'nullable|string',
            'product_ids'    => 'nullable|array',
            'product_ids.*'  => 'integer',
        ]);

        DB::table('kyc_compliance')->where('id', $id)->update([
            'name'       => $data['name'],
            'flow_id'    => $data['flow_id'] ?? null,
            'fields'     => json_encode($data['fields']),
            'updated_at' => now(),
        ]);

        // Re-assign products: unlink old, link new
        DB::table('products')
            ->where('kyc_compliance', $id)
            ->update(['kyc_compliance' => null]);

        if (!empty($data['product_ids'])) {
            DB::table('products')
                ->whereIn('id', $data['product_ids'])
                ->update(['kyc_compliance' => $id]);
        }

        return response()->json(['message' => 'KYC compliance rule updated.']);
    }

    /**
     * DELETE /kyc-compliance/{id} — Delete a compliance rule.
     */
    public function destroy(int $id): JsonResponse
    {
        // Unlink products first
        DB::table('products')
            ->where('kyc_compliance', $id)
            ->update(['kyc_compliance' => null]);

        $deleted = DB::table('kyc_compliance')->where('id', $id)->delete();
        if (!$deleted) {
            return response()->json(['message' => 'KYC compliance rule not found.'], 404);
        }

        return response()->json(['message' => 'KYC compliance rule deleted.']);
    }

    /**
     * GET /kyc-compliance/products — MIS products for assignment dropdown.
     *
     * Scoped to MIS-tier products only (1-6, 9, 10). DOM/COM-tier
     * products (7,8,16-19,20,22) and Specialist products have their
     * own KYC flows (customer_kyc_dom_com / Rekyc / AdGroupKyc) which
     * don't read from kyc_compliance.fields — so surfacing them here
     * would let admins build dead rules. The MIS list mirrors
     * KycComplianceCheck::MIS_PRODUCT_IDS so the admin UI and the
     * downstream crons stay in sync.
     */
    public function products(): JsonResponse
    {
        $products = DB::table('products')
            ->where('status', 1)
            ->whereIn('id', KycComplianceCheck::MIS_PRODUCT_IDS)
            ->orderBy('name')
            ->get(['id', 'name', 'kyc_compliance']);

        return response()->json(['data' => $products]);
    }

    /**
     * GET /kyc-compliance/mati — read the global "Enable Mati" toggle.
     *
     * V8 parity: stored in the `config` table keyed by 'enable_mati'
     * (value '0' / '1'). Default to enabled (1) if the row is missing
     * so a fresh install behaves the same as V8 production.
     */
    public function getMatiStatus(): JsonResponse
    {
        $row = DB::table('config')->where('key', 'enable_mati')->first(['id', 'value', 'updated_at']);
        return response()->json([
            'enabled'   => $row ? ((string) $row->value === '1') : true,
            'value'     => $row->value ?? '1',
            'updatedAt' => $row->updated_at ?? null,
        ]);
    }

    /**
     * POST /kyc-compliance/mati — set the global "Enable Mati" toggle.
     * Mirrors V8 admin KycComplianceController::enableMati(). Upserts
     * the `config` row when missing.
     */
    public function setMatiStatus(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'enabled' => 'required|boolean',
        ]);

        $value = $validated['enabled'] ? '1' : '0';
        $exists = DB::table('config')->where('key', 'enable_mati')->exists();

        if ($exists) {
            DB::table('config')->where('key', 'enable_mati')->update([
                'value'      => $value,
                'updated_at' => now(),
            ]);
        } else {
            DB::table('config')->insert([
                'key'        => 'enable_mati',
                'value'      => $value,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Log::info('enable_mati config toggled', [
            'value' => $value,
            'by'    => optional(auth()->user())->id,
        ]);

        return response()->json([
            'message' => $validated['enabled'] ? 'Mati enabled' : 'Mati disabled',
            'enabled' => $validated['enabled'],
            'value'   => $value,
        ]);
    }
}
