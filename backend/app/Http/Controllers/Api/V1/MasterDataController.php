<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MasterDataController extends Controller
{
    public function validationRules(Request $request): JsonResponse
    {
        $query = DB::table('tb_prvalidationrulemasters');
        if ($s = $request->query('search')) {
            $query->where(function ($q) use ($s) {
                $q->where('s_RuleCode', 'like', "%$s%")
                  ->orWhere('s_RuleDesc', 'like', "%$s%");
            });
        }
        $perPage = (int) ($request->query('per_page', 25));
        // tb_prvalidationrulemasters has no `id` column — its PK is
        // n_PrValidationRuleMaster_PK. Old orderBy('id') threw a 1054
        // column-not-found and the rules list / role-group dropdown
        // came back empty everywhere.
        $result = $query->orderBy('n_PrValidationRuleMaster_PK', 'desc')->paginate($perPage);
        return response()->json($result);
    }

    public function validationGroups(Request $request): JsonResponse
    {
        // V2's products table is `products` — not `tb_products`. The
        // legacy Hungarian-notation `tb_` prefix applies to the GFS
        // validation tables, not to products. Old leftJoin threw
        // SQLSTATE[42S02] (Base table not found) so /policy-validation/
        // groups returned 500 and the Role → Rule Group Assignment
        // dropdown showed only the placeholder.
        $query = DB::table('tb_prvalidationrulegroupmasters as g')
            ->leftJoin('products as p', 'p.id', '=', 'g.n_Product_FK')
            ->select(['g.*', 'p.name as product_name']);
        if ($s = $request->query('search')) {
            $query->where(function ($q) use ($s) {
                $q->where('g.s_RuleCode', 'like', "%$s%")
                  ->orWhere('g.s_RuleDesc', 'like', "%$s%");
            });
        }
        $perPage = (int) ($request->query('per_page', 25));
        // tb_prvalidationrulegroupmasters PK is
        // n_PrValidationRuleGroupMasters_PK — no `id` column. Same
        // bug as validationRules above: this 500'd the
        // /policy-validation/groups endpoint, leaving the Role →
        // Rule Group Assignment dropdown empty for every admin.
        $result = $query->orderBy('g.n_PrValidationRuleGroupMasters_PK', 'desc')->paginate($perPage);
        return response()->json($result);
    }

    public function coverageMaster(Request $request): JsonResponse
    {
        $query = DB::table('tb_cvgpccoverages');
        if ($s = $request->query('search')) {
            $query->where(function ($q) use ($s) {
                $q->where('s_CoverageCode', 'like', "%$s%")
                  ->orWhere('s_CoverageName', 'like', "%$s%")
                  ->orWhere('s_CoverageGroupName', 'like', "%$s%");
            });
        }
        $perPage = (int) ($request->query('per_page', 25));
        $result = $query->orderBy('n_DisplaySequence')->paginate($perPage);
        return response()->json($result);
    }

    public function reinsurers(Request $request): JsonResponse
    {
        $perPage = (int) ($request->query('per_page', 25));
        $query = DB::table('reinsurer');
        if (\Schema::hasColumn('reinsurer', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }
        if ($s = $request->query('search')) {
            $query->where(function ($q) use ($s) {
                $q->where('company_name', 'like', "%$s%")
                  ->orWhere('email', 'like', "%$s%")
                  ->orWhere('cellphone', 'like', "%$s%");
            });
        }
        $result = $query->orderBy('id', 'desc')->paginate($perPage);
        return response()->json($result);
    }

    // ─── Specified-Coverage Items (master) ──────────────────────────────
    // Mirrors graphiteBWV8 Livewire/Coverage/SpecifiedCoverage/{Table,Add,Edit}.
    // Table: specified_coverage_items  (one row per "misc item" available to a coverage).

    public function specifiedCoverageItemsIndex(Request $request): JsonResponse
    {
        $query = DB::table('specified_coverage_items as sci')
            ->leftJoin('tb_cvgpccoverages as cov', 'cov.id', '=', 'sci.coverage_id')
            ->select([
                'sci.id', 'sci.specified_code', 'sci.specified_name',
                'sci.coverage_id', 'sci.rate',
                'sci.effective_from', 'sci.effective_to', 'sci.added_by',
                'cov.s_CoverageName as coverage_name',
                'cov.s_CoverageCode as coverage_code',
            ]);

        if ($s = $request->query('search')) {
            $query->where(function ($q) use ($s) {
                $q->where('sci.specified_name', 'like', "%$s%")
                  ->orWhere('sci.specified_code', 'like', "%$s%")
                  ->orWhere('cov.s_CoverageName', 'like', "%$s%")
                  ->orWhere('cov.s_CoverageCode', 'like', "%$s%");
            });
        }
        if ($cid = $request->query('coverage_id')) {
            $query->where('sci.coverage_id', $cid);
        }

        $perPage = (int) ($request->query('per_page', 25));
        return response()->json($query->orderByDesc('sci.id')->paginate($perPage));
    }

    public function specifiedCoverageItemsStore(Request $request): JsonResponse
    {
        $v = $request->validate([
            'coverage_id'    => 'required|integer|exists:tb_cvgpccoverages,id',
            'specified_name' => 'required|string|max:255',
            'rate'           => 'required|numeric|min:0',
            'effective_from' => 'nullable|date',
            'effective_to'   => 'nullable|date|after_or_equal:effective_from',
        ]);

        $code = strtoupper(preg_replace('/[^A-Za-z0-9]+/', '_', trim($v['specified_name'])));

        $id = DB::table('specified_coverage_items')->insertGetId([
            'coverage_id'    => $v['coverage_id'],
            'specified_name' => $v['specified_name'],
            'specified_code' => $code,
            'rate'           => $v['rate'],
            'effective_from' => $v['effective_from'] ?? null,
            'effective_to'   => $v['effective_to']   ?? null,
            'added_by'       => auth()->id(),
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        return response()->json(['message' => 'Created.', 'id' => $id], 201);
    }

    public function specifiedCoverageItemsShow(int $id): JsonResponse
    {
        $row = DB::table('specified_coverage_items')->where('id', $id)->first();
        if (!$row) return response()->json(['error' => 'Not found.'], 404);
        return response()->json(['data' => $row]);
    }

    public function specifiedCoverageItemsUpdate(Request $request, int $id): JsonResponse
    {
        $row = DB::table('specified_coverage_items')->where('id', $id)->first();
        if (!$row) return response()->json(['error' => 'Not found.'], 404);

        $v = $request->validate([
            'coverage_id'    => 'sometimes|integer|exists:tb_cvgpccoverages,id',
            'specified_name' => 'sometimes|string|max:255',
            'rate'           => 'sometimes|numeric|min:0',
            'effective_from' => 'nullable|date',
            'effective_to'   => 'nullable|date|after_or_equal:effective_from',
        ]);

        if (isset($v['specified_name'])) {
            $v['specified_code'] = strtoupper(preg_replace('/[^A-Za-z0-9]+/', '_', trim($v['specified_name'])));
        }
        $v['updated_at'] = now();

        DB::table('specified_coverage_items')->where('id', $id)->update($v);
        return response()->json(['message' => 'Updated.']);
    }

    public function specifiedCoverageItemsDestroy(int $id): JsonResponse
    {
        $row = DB::table('specified_coverage_items')->where('id', $id)->first();
        if (!$row) return response()->json(['error' => 'Not found.'], 404);

        // Soft-delete if the column exists; else hard delete. Legacy uses soft delete.
        if (\Schema::hasColumn('specified_coverage_items', 'deleted_at')) {
            DB::table('specified_coverage_items')->where('id', $id)->update(['deleted_at' => now()]);
        } else {
            DB::table('specified_coverage_items')->where('id', $id)->delete();
        }
        return response()->json(['message' => 'Deleted.']);
    }

    // Helper for the Add/Edit modal — returns the list of main coverages so
    // the user can pick which coverage a specified-item belongs to.
    public function coverageMasterList(Request $request): JsonResponse
    {
        $rows = DB::table('tb_cvgpccoverages')
            ->where('s_UsageType', 'PARENT')
            ->where('s_CoverageGroupCode', 'MAIN')
            ->select('id', 's_CoverageName as name', 's_CoverageCode as code')
            ->orderBy('s_CoverageName')
            ->get();
        return response()->json(['data' => $rows]);
    }

    // ─── Coverage Master write ops ───────────────────────────────────────
    // Legacy parity: Livewire/Coverage/{Add,Edit,Table}. Writes to
    // tb_cvgpccoverages where s_UsageType=PARENT, s_CoverageGroupCode=MAIN.

    public function coverageMasterStore(Request $request): JsonResponse
    {
        $v = $request->validate([
            's_CoverageName'       => 'required|string|max:255',
            's_CoverageCode'       => 'required|string|max:100',
            's_CoverageGroupName'  => 'nullable|string|max:100',
            'rate'                 => 'nullable|numeric|min:0',
            'n_DisplaySequence'    => 'nullable|integer',
            's_CoverageDesc'       => 'nullable|string|max:1000',
            'has_risk_address'     => 'nullable|boolean',
        ]);
        $v['s_UsageType'] = 'PARENT';
        $v['s_CoverageGroupCode'] = 'MAIN';
        $v['s_CoverageSection'] = 'MAIN';
        $v['created_at'] = now();
        $v['updated_at'] = now();
        $id = DB::table('tb_cvgpccoverages')->insertGetId($v);
        return response()->json(['message' => 'Created.', 'id' => $id], 201);
    }

    public function coverageMasterUpdate(Request $request, int $id): JsonResponse
    {
        $row = DB::table('tb_cvgpccoverages')->where('id', $id)->first();
        if (!$row) return response()->json(['error' => 'Not found.'], 404);
        $v = $request->validate([
            's_CoverageName'       => 'sometimes|string|max:255',
            's_CoverageCode'       => 'sometimes|string|max:100',
            's_CoverageGroupName'  => 'nullable|string|max:100',
            'rate'                 => 'nullable|numeric|min:0',
            'n_DisplaySequence'    => 'nullable|integer',
            's_CoverageDesc'       => 'nullable|string|max:1000',
            'has_risk_address'     => 'nullable|boolean',
        ]);
        $v['updated_at'] = now();
        DB::table('tb_cvgpccoverages')->where('id', $id)->update($v);
        return response()->json(['message' => 'Updated.']);
    }

    public function coverageMasterDestroy(int $id): JsonResponse
    {
        $row = DB::table('tb_cvgpccoverages')->where('id', $id)->first();
        if (!$row) return response()->json(['error' => 'Not found.'], 404);
        if (\Schema::hasColumn('tb_cvgpccoverages', 'deleted_at')) {
            DB::table('tb_cvgpccoverages')->where('id', $id)->update(['deleted_at' => now()]);
        } else {
            DB::table('tb_cvgpccoverages')->where('id', $id)->delete();
        }
        return response()->json(['message' => 'Deleted.']);
    }

    // ─── Sub Coverages ──────────────────────────────────────────────────
    // Legacy parity: Livewire/SubCoverage/{Table,Add,Edit}. Shares
    // tb_cvgpccoverages with Coverage Master but with s_UsageType=CHILD,
    // s_CoverageGroupCode=SUB, s_ParentCoverageID=<parent>.

    public function subCoveragesIndex(Request $request): JsonResponse
    {
        $query = DB::table('tb_cvgpccoverages as sc')
            ->leftJoin('tb_cvgpccoverages as pc', 'pc.id', '=', 'sc.s_ParentCoverageID')
            ->where('sc.s_UsageType', 'CHILD')
            ->where('sc.s_CoverageGroupCode', 'SUB')
            ->select([
                'sc.id', 'sc.s_CoverageName', 'sc.s_CoverageCode', 'sc.s_ScreenName',
                'sc.s_CoverageGroupName', 'sc.s_SubCoverageMainName',
                'sc.rate', 'sc.n_DisplaySequence',
                'sc.s_ParentCoverageID', 'sc.s_DISPLAYTOUSER',
                'sc.d_EffectiveDt', 'sc.d_ExpirationDt',
                'pc.s_CoverageName as parent_name', 'pc.s_CoverageCode as parent_code',
            ]);
        if ($s = $request->query('search')) {
            $query->where(function ($q) use ($s) {
                $q->where('sc.s_CoverageName', 'like', "%$s%")
                  ->orWhere('sc.s_ScreenName', 'like', "%$s%")
                  ->orWhere('sc.s_CoverageGroupName', 'like', "%$s%")
                  ->orWhere('pc.s_CoverageName', 'like', "%$s%");
            });
        }
        if ($cid = $request->query('coverage_id')) {
            $query->where('sc.s_ParentCoverageID', $cid);
        }
        $perPage = (int) ($request->query('per_page', 25));
        return response()->json($query->orderBy('sc.n_DisplaySequence')->paginate($perPage));
    }

    public function subCoveragesStore(Request $request): JsonResponse
    {
        $v = $request->validate([
            's_ParentCoverageID'      => 'required|integer|exists:tb_cvgpccoverages,id',
            's_CoverageName'          => 'required|string|max:255',
            's_ScreenName'            => 'required|string|max:255',
            's_CoverageGroupName'     => 'nullable|string|max:100',
            's_SubCoverageMainName'   => 'nullable|string|max:100',
            'rate'                    => 'required|numeric|min:0',
            'n_DisplaySequence'       => 'nullable|integer',
            's_CoverageDesc'          => 'nullable|string|max:1000',
            's_RatingMethod'          => 'nullable|string|max:50',
            's_DISPLAYTOUSER'         => 'nullable|boolean',
            'd_EffectiveDt'           => 'nullable|date',
            'd_ExpirationDt'          => 'nullable|date',
        ]);
        $parent = DB::table('tb_cvgpccoverages')->where('id', $v['s_ParentCoverageID'])->first();
        $v['s_ParentCoverageCode'] = $parent ? str_replace(' ', '', (string) $parent->s_CoverageCode) : null;
        $v['s_CoverageCode']       = strtoupper($v['s_ScreenName']);
        $v['s_UsageType']          = 'CHILD';
        $v['s_CoverageGroupCode']  = 'SUB';
        $v['s_CoverageSection']    = 'SUB';
        $v['s_GroupRowType']       = 'COVERAGE';
        $v['s_CoveragePart']       = 'PROPERTY';
        $v['s_DISPLAYTOUSER']      = !empty($v['s_DISPLAYTOUSER']) ? 1 : 0;
        $v['created_at'] = now();
        $v['updated_at'] = now();
        $id = DB::table('tb_cvgpccoverages')->insertGetId($v);
        return response()->json(['message' => 'Created.', 'id' => $id], 201);
    }

    public function subCoveragesUpdate(Request $request, int $id): JsonResponse
    {
        $row = DB::table('tb_cvgpccoverages')->where('id', $id)->first();
        if (!$row) return response()->json(['error' => 'Not found.'], 404);
        $v = $request->validate([
            's_ParentCoverageID'      => 'sometimes|integer|exists:tb_cvgpccoverages,id',
            's_CoverageName'          => 'sometimes|string|max:255',
            's_ScreenName'            => 'sometimes|string|max:255',
            's_CoverageGroupName'     => 'nullable|string|max:100',
            's_SubCoverageMainName'   => 'nullable|string|max:100',
            'rate'                    => 'sometimes|numeric|min:0',
            'n_DisplaySequence'       => 'nullable|integer',
            's_CoverageDesc'          => 'nullable|string|max:1000',
            's_RatingMethod'          => 'nullable|string|max:50',
            's_DISPLAYTOUSER'         => 'nullable|boolean',
            'd_EffectiveDt'           => 'nullable|date',
            'd_ExpirationDt'          => 'nullable|date',
        ]);
        if (isset($v['s_ScreenName'])) $v['s_CoverageCode'] = strtoupper($v['s_ScreenName']);
        if (isset($v['s_DISPLAYTOUSER'])) $v['s_DISPLAYTOUSER'] = $v['s_DISPLAYTOUSER'] ? 1 : 0;
        $v['updated_at'] = now();
        DB::table('tb_cvgpccoverages')->where('id', $id)->update($v);
        return response()->json(['message' => 'Updated.']);
    }

    public function subCoveragesDestroy(int $id): JsonResponse
    {
        $row = DB::table('tb_cvgpccoverages')->where('id', $id)->first();
        if (!$row) return response()->json(['error' => 'Not found.'], 404);
        if (\Schema::hasColumn('tb_cvgpccoverages', 'deleted_at')) {
            DB::table('tb_cvgpccoverages')->where('id', $id)->update(['deleted_at' => now()]);
        } else {
            DB::table('tb_cvgpccoverages')->where('id', $id)->delete();
        }
        return response()->json(['message' => 'Deleted.']);
    }

    // ─── Extensions / Excess / Misc ─────────────────────────────────────
    // Legacy parity: Livewire/Extentions/{Table,Add,Edit}. Writes to
    // `extentions` table. `type` discriminates Extention vs Perils vs Excess.

    public function extensionsIndex(Request $request): JsonResponse
    {
        $query = DB::table('extentions as e')
            ->leftJoin('tb_cvgpccoverages as pc', 'pc.id', '=', 'e.s_ParentCoverageID')
            ->leftJoin('tb_cvgpccoverages as sc', 'sc.id', '=', 'e.s_SubCoverageID')
            ->select([
                'e.id', 'e.s_CoverageName', 'e.s_ScreenName', 'e.s_CoverageCode',
                'e.rate', 'e.n_DisplaySequence',
                'e.extention_type', 'e.type', 'e.s_ExtensionsGroupName',
                'e.s_ParentCoverageID', 'e.s_SubCoverageID',
                'e.d_EffectiveDt', 'e.d_ExpirationDt', 'e.s_DISPLAYTOUSER',
                'pc.s_CoverageName as parent_name', 'pc.s_CoverageCode as parent_code',
                'sc.s_CoverageName as sub_name',
            ]);
        if ($s = $request->query('search')) {
            $query->where(function ($q) use ($s) {
                $q->where('e.s_CoverageName', 'like', "%$s%")
                  ->orWhere('e.s_ScreenName', 'like', "%$s%")
                  ->orWhere('e.s_ExtensionsGroupName', 'like', "%$s%")
                  ->orWhere('pc.s_CoverageName', 'like', "%$s%");
            });
        }
        if ($cid  = $request->query('coverage_id'))     $query->where('e.s_ParentCoverageID', $cid);
        if ($type = $request->query('type'))            $query->where('e.type', $type);
        $perPage = (int) ($request->query('per_page', 25));
        return response()->json($query->orderBy('e.n_DisplaySequence')->paginate($perPage));
    }

    public function extensionsStore(Request $request): JsonResponse
    {
        $v = $request->validate([
            's_ParentCoverageID'       => 'required|integer|exists:tb_cvgpccoverages,id',
            's_SubCoverageID'          => 'nullable|integer|exists:tb_cvgpccoverages,id',
            's_CoverageName'           => 'required|string|max:255',
            's_ScreenName'             => 'required|string|max:255',
            'rate'                     => 'required|numeric|min:0',
            'n_DisplaySequence'        => 'nullable|integer',
            'extention_type'           => 'required|in:NOEDIT,NUMBER,RADIO,DROPDOWN',
            'type'                     => 'required|in:Extention,Perils,Excess,Misc',
            's_ExtensionsGroupName'    => 'nullable|string|max:100',
            's_CoverageDesc'           => 'nullable|string|max:1000',
            's_RatingMethod'           => 'nullable|string|max:50',
            'd_EffectiveDt'            => 'nullable|date',
            'd_ExpirationDt'           => 'nullable|date',
            's_DISPLAYTOUSER'          => 'nullable|boolean',
        ]);
        $parent = DB::table('tb_cvgpccoverages')->where('id', $v['s_ParentCoverageID'])->first();
        $v['s_ParentCoverageCode'] = $parent ? str_replace(' ', '', (string) $parent->s_CoverageCode) : null;
        $v['s_CoverageCode']       = strtoupper($v['s_ScreenName']);
        $v['s_DISPLAYTOUSER']      = !empty($v['s_DISPLAYTOUSER']) ? 1 : 0;
        $v['created_at'] = now();
        $v['updated_at'] = now();
        $id = DB::table('extentions')->insertGetId($v);
        return response()->json(['message' => 'Created.', 'id' => $id], 201);
    }

    public function extensionsUpdate(Request $request, int $id): JsonResponse
    {
        $row = DB::table('extentions')->where('id', $id)->first();
        if (!$row) return response()->json(['error' => 'Not found.'], 404);
        $v = $request->validate([
            's_ParentCoverageID'       => 'sometimes|integer|exists:tb_cvgpccoverages,id',
            's_SubCoverageID'          => 'nullable|integer|exists:tb_cvgpccoverages,id',
            's_CoverageName'           => 'sometimes|string|max:255',
            's_ScreenName'             => 'sometimes|string|max:255',
            'rate'                     => 'sometimes|numeric|min:0',
            'n_DisplaySequence'        => 'nullable|integer',
            'extention_type'           => 'sometimes|in:NOEDIT,NUMBER,RADIO,DROPDOWN',
            'type'                     => 'sometimes|in:Extention,Perils,Excess,Misc',
            's_ExtensionsGroupName'    => 'nullable|string|max:100',
            's_CoverageDesc'           => 'nullable|string|max:1000',
            's_RatingMethod'           => 'nullable|string|max:50',
            'd_EffectiveDt'            => 'nullable|date',
            'd_ExpirationDt'           => 'nullable|date',
            's_DISPLAYTOUSER'          => 'nullable|boolean',
        ]);
        if (isset($v['s_ScreenName'])) $v['s_CoverageCode'] = strtoupper($v['s_ScreenName']);
        if (isset($v['s_DISPLAYTOUSER'])) $v['s_DISPLAYTOUSER'] = $v['s_DISPLAYTOUSER'] ? 1 : 0;
        $v['updated_at'] = now();
        DB::table('extentions')->where('id', $id)->update($v);
        return response()->json(['message' => 'Updated.']);
    }

    public function extensionsDestroy(int $id): JsonResponse
    {
        $row = DB::table('extentions')->where('id', $id)->first();
        if (!$row) return response()->json(['error' => 'Not found.'], 404);
        if (\Schema::hasColumn('extentions', 'deleted_at')) {
            DB::table('extentions')->where('id', $id)->update(['deleted_at' => now()]);
        } else {
            DB::table('extentions')->where('id', $id)->delete();
        }
        return response()->json(['message' => 'Deleted.']);
    }

    // ─── Reinsurer full CRUD ─────────────────────────────────────────
    // Legacy parity: Livewire/Reinsurer/{Table,Add,Edit}. Table: reinsurer.

    public function reinsurersStore(Request $request): JsonResponse
    {
        $v = $request->validate([
            'company_name' => 'required|string|max:255',
            'email'        => 'required|email|max:255',
            'cellphone'    => 'required|string|max:50',
        ]);
        $v['created_at'] = now();
        $v['updated_at'] = now();
        $id = DB::table('reinsurer')->insertGetId($v);
        return response()->json(['message' => 'Created.', 'id' => $id], 201);
    }

    public function reinsurersUpdate(Request $request, int $id): JsonResponse
    {
        $row = DB::table('reinsurer')->where('id', $id)->first();
        if (!$row) return response()->json(['error' => 'Not found.'], 404);
        $v = $request->validate([
            'company_name' => 'sometimes|string|max:255',
            'email'        => 'sometimes|email|max:255',
            'cellphone'    => 'sometimes|string|max:50',
        ]);
        $v['updated_at'] = now();
        DB::table('reinsurer')->where('id', $id)->update($v);
        return response()->json(['message' => 'Updated.']);
    }

    public function reinsurersDestroy(int $id): JsonResponse
    {
        $row = DB::table('reinsurer')->where('id', $id)->first();
        if (!$row) return response()->json(['error' => 'Not found.'], 404);
        if (\Schema::hasColumn('reinsurer', 'deleted_at')) {
            DB::table('reinsurer')->where('id', $id)->update(['deleted_at' => now()]);
        } else {
            DB::table('reinsurer')->where('id', $id)->delete();
        }
        return response()->json(['message' => 'Deleted.']);
    }

    // ─── Companies admin (full list + CRUD incl. sub-companies) ──────
    // /lookups/companies is the policy-wizard picker (limit 50, status=1, no
    // parents). Admin index returns everything paginated.

    public function companiesAdminIndex(Request $request): JsonResponse
    {
        $query = DB::table('companies as c')
            ->leftJoin('companies as p', 'p.id', '=', 'c.parent_id')
            ->select([
                'c.id', 'c.name', 'c.parent_id', 'c.VAT_registration_number',
                'c.company_registration_number', 'c.head_office_physical_address',
                'c.postal_address', 'c.city', 'c.state', 'c.pincode',
                'c.primary_email', 'c.secondary_email', 'c.broker_email',
                'c.contact_person', 'c.contact_person_number', 'c.status',
                'c.created_at', 'c.updated_at',
                'p.name as parent_name',
            ]);
        if ($s = $request->query('search')) {
            $query->where(function ($q) use ($s) {
                $q->where('c.name', 'like', "%$s%")
                  ->orWhere('c.VAT_registration_number', 'like', "%$s%")
                  ->orWhere('c.company_registration_number', 'like', "%$s%")
                  ->orWhere('c.primary_email', 'like', "%$s%");
            });
        }
        if (($scope = $request->query('scope')) === 'parent') {
            $query->whereNull('c.parent_id');
        } elseif ($scope === 'sub') {
            $query->whereNotNull('c.parent_id');
        }
        if ($pid = $request->query('parent_id')) {
            $query->where('c.parent_id', $pid);
        }

        $perPage = (int) ($request->query('per_page', 25));
        return response()->json($query->orderByDesc('c.id')->paginate($perPage));
    }

    public function companiesAdminStore(Request $request): JsonResponse
    {
        // Accepts both parent companies and sub-companies (parent_id nullable).
        // Kept lighter than LookupController::createCompany — that method
        // additionally spawns a linked Customer, which is only relevant when
        // the company is being added from the policy wizard.
        $v = $request->validate([
            'name'                         => 'required|string|max:255',
            'parent_id'                    => 'nullable|integer|exists:companies,id',
            'VAT_registration_number'      => 'nullable|string|max:50',
            'company_registration_number'  => 'nullable|string|max:50',
            'head_office_physical_address' => 'nullable|string|max:500',
            'postal_address'               => 'nullable|string|max:500',
            'city'                         => 'nullable|string|max:100',
            'state'                        => 'nullable|string|max:100',
            'pincode'                      => 'nullable|string|max:20',
            'primary_email'                => 'nullable|email|max:255',
            'secondary_email'              => 'nullable|email|max:255',
            'broker_email'                 => 'nullable|email|max:255',
            'contact_person'               => 'nullable|string|max:255',
            'contact_person_number'        => 'nullable|string|max:50',
            'status'                       => 'nullable|integer|in:0,1',
        ]);
        if (!isset($v['status'])) $v['status'] = 1;
        // address column is NOT NULL on legacy schema — mirror head_office_physical_address
        if (\Schema::hasColumn('companies', 'address') && !empty($v['head_office_physical_address'])) {
            $v['address'] = $v['head_office_physical_address'];
        }
        $v['created_at'] = now();
        $v['updated_at'] = now();
        $id = DB::table('companies')->insertGetId($v);

        if (class_exists(\AlphaDirect\Services\CacheService::class)) {
            \Illuminate\Support\Facades\Cache::forget('login_lookups_v2');
            \AlphaDirect\Services\CacheService::forgetLookups('policy_create_data');
        }

        return response()->json(['message' => 'Created.', 'id' => $id], 201);
    }

    public function companiesAdminUpdate(Request $request, int $id): JsonResponse
    {
        $row = DB::table('companies')->where('id', $id)->first();
        if (!$row) return response()->json(['error' => 'Not found.'], 404);
        $v = $request->validate([
            'name'                         => 'sometimes|string|max:255',
            'parent_id'                    => 'nullable|integer|exists:companies,id',
            'VAT_registration_number'      => 'nullable|string|max:50',
            'company_registration_number'  => 'nullable|string|max:50',
            'head_office_physical_address' => 'nullable|string|max:500',
            'postal_address'               => 'nullable|string|max:500',
            'city'                         => 'nullable|string|max:100',
            'state'                        => 'nullable|string|max:100',
            'pincode'                      => 'nullable|string|max:20',
            'primary_email'                => 'nullable|email|max:255',
            'secondary_email'              => 'nullable|email|max:255',
            'broker_email'                 => 'nullable|email|max:255',
            'contact_person'               => 'nullable|string|max:255',
            'contact_person_number'        => 'nullable|string|max:50',
            'status'                       => 'nullable|integer|in:0,1',
        ]);
        if (\Schema::hasColumn('companies', 'address') && array_key_exists('head_office_physical_address', $v)) {
            $v['address'] = $v['head_office_physical_address'];
        }
        $v['updated_at'] = now();
        DB::table('companies')->where('id', $id)->update($v);

        if (class_exists(\AlphaDirect\Services\CacheService::class)) {
            \Illuminate\Support\Facades\Cache::forget('login_lookups_v2');
            \AlphaDirect\Services\CacheService::forgetLookups('policy_create_data');
        }

        return response()->json(['message' => 'Updated.']);
    }

    // ─── Validation Rules (master) — full CRUD ───────────────────────
    // Legacy parity: Livewire/ValidationRule/{Table,Add,Edit}.
    // Table: tb_prvalidationrulemasters (PK: n_PrValidationRuleMaster_PK).
    // Detail rows live in tb_prvalidationruledetails but are not edited here;
    // rule-level fields drive the gate that ReinsuranceValidator reads on
    // Submit-to-Approval.

    public function validationRulesStore(Request $request): JsonResponse
    {
        $v = $request->validate([
            's_RuleCode'         => 'required|string|max:100',
            's_Description'      => 'nullable|string|max:500',
            's_ScreenErrorMsg'   => 'nullable|string|max:500',
            'n_Product_FK'       => 'required|integer',
            's_RuleApplyOn'      => 'nullable|string|max:50',
            's_CanRate'          => 'nullable|in:YES,NO',
            's_CanPrintQuote'    => 'nullable|in:YES,NO',
            's_CanPrintApp'      => 'nullable|in:YES,NO',
            's_CanBindApp'       => 'nullable|in:YES,NO',
            's_CanUnBoundApp'    => 'nullable|in:YES,NO',
            's_CanIssue'         => 'nullable|in:YES,NO',
            'd_EffectiveDateFrom'=> 'required|date',
            'd_EffectiveDateTo'  => 'required|date|after_or_equal:d_EffectiveDateFrom',
            's_RuleStatus'       => 'nullable|in:ACTIVE,INACTIVE',
        ]);
        $v['s_RuleStatus'] = $v['s_RuleStatus'] ?? 'ACTIVE';
        $v['n_CreatedUser'] = auth()->id();
        $v['d_CreatedDate'] = now();
        $id = DB::table('tb_prvalidationrulemasters')->insertGetId($v);
        return response()->json(['message' => 'Created.', 'id' => $id], 201);
    }

    public function validationRulesUpdate(Request $request, int $id): JsonResponse
    {
        $row = DB::table('tb_prvalidationrulemasters')->where('n_PrValidationRuleMaster_PK', $id)->first();
        if (!$row) return response()->json(['error' => 'Not found.'], 404);
        $v = $request->validate([
            's_RuleCode'         => 'sometimes|string|max:100',
            's_Description'      => 'nullable|string|max:500',
            's_ScreenErrorMsg'   => 'nullable|string|max:500',
            'n_Product_FK'       => 'sometimes|integer',
            's_RuleApplyOn'      => 'nullable|string|max:50',
            's_CanRate'          => 'nullable|in:YES,NO',
            's_CanPrintQuote'    => 'nullable|in:YES,NO',
            's_CanPrintApp'      => 'nullable|in:YES,NO',
            's_CanBindApp'       => 'nullable|in:YES,NO',
            's_CanUnBoundApp'    => 'nullable|in:YES,NO',
            's_CanIssue'         => 'nullable|in:YES,NO',
            'd_EffectiveDateFrom'=> 'sometimes|date',
            'd_EffectiveDateTo'  => 'sometimes|date|after_or_equal:d_EffectiveDateFrom',
            's_RuleStatus'       => 'nullable|in:ACTIVE,INACTIVE',
        ]);
        $v['n_UpdatedUser'] = auth()->id();
        $v['d_UpdatedDate'] = now();
        DB::table('tb_prvalidationrulemasters')->where('n_PrValidationRuleMaster_PK', $id)->update($v);
        return response()->json(['message' => 'Updated.']);
    }

    public function validationRulesDestroy(int $id): JsonResponse
    {
        $row = DB::table('tb_prvalidationrulemasters')->where('n_PrValidationRuleMaster_PK', $id)->first();
        if (!$row) return response()->json(['error' => 'Not found.'], 404);
        if (\Schema::hasColumn('tb_prvalidationrulemasters', 'deleted_at')) {
            DB::table('tb_prvalidationrulemasters')->where('n_PrValidationRuleMaster_PK', $id)->update(['deleted_at' => now()]);
        } else {
            DB::table('tb_prvalidationrulemasters')->where('n_PrValidationRuleMaster_PK', $id)->delete();
        }
        return response()->json(['message' => 'Deleted.']);
    }

    // ─── Validation Rule Groups — full CRUD + role binding ───────────
    // Legacy parity: Livewire/ValidationRuleGroup/{Table,Add,Edit}.
    // Table: tb_prvalidationrulegroupmasters (PK: n_PrValidationRuleGroupMasters_PK).
    // Role binding: roles.rule_group stores the group PK the role inherits.

    public function validationGroupsStore(Request $request): JsonResponse
    {
        $v = $request->validate([
            's_RuleCode'   => 'required|string|max:100',
            's_RuleDesc'   => 'nullable|string|max:500',
            'n_Product_FK' => 'required|integer',
            's_Status'     => 'nullable|in:ACTIVE,INACTIVE',
        ]);
        $v['s_Status'] = $v['s_Status'] ?? 'ACTIVE';
        $v['n_CreatedUser'] = auth()->id();
        $v['d_CreatedDate'] = now();
        $id = DB::table('tb_prvalidationrulegroupmasters')->insertGetId($v);
        return response()->json(['message' => 'Created.', 'id' => $id], 201);
    }

    public function validationGroupsUpdate(Request $request, int $id): JsonResponse
    {
        $row = DB::table('tb_prvalidationrulegroupmasters')->where('n_PrValidationRuleGroupMasters_PK', $id)->first();
        if (!$row) return response()->json(['error' => 'Not found.'], 404);
        $v = $request->validate([
            's_RuleCode'   => 'sometimes|string|max:100',
            's_RuleDesc'   => 'nullable|string|max:500',
            'n_Product_FK' => 'sometimes|integer',
            's_Status'     => 'nullable|in:ACTIVE,INACTIVE',
        ]);
        $v['n_UpdatedUser'] = auth()->id();
        $v['d_UpdatedDate'] = now();
        DB::table('tb_prvalidationrulegroupmasters')->where('n_PrValidationRuleGroupMasters_PK', $id)->update($v);
        return response()->json(['message' => 'Updated.']);
    }

    public function validationGroupsDestroy(int $id): JsonResponse
    {
        $row = DB::table('tb_prvalidationrulegroupmasters')->where('n_PrValidationRuleGroupMasters_PK', $id)->first();
        if (!$row) return response()->json(['error' => 'Not found.'], 404);
        // Check if any roles are still bound
        $boundRoles = DB::table('roles')->where('rule_group', $id)->count();
        if ($boundRoles > 0) {
            return response()->json([
                'error' => "Cannot delete: {$boundRoles} role(s) still bound to this group. Reassign them first.",
            ], 422);
        }
        if (\Schema::hasColumn('tb_prvalidationrulegroupmasters', 'deleted_at')) {
            DB::table('tb_prvalidationrulegroupmasters')->where('n_PrValidationRuleGroupMasters_PK', $id)->update(['deleted_at' => now()]);
        } else {
            DB::table('tb_prvalidationrulegroupmasters')->where('n_PrValidationRuleGroupMasters_PK', $id)->delete();
        }
        return response()->json(['message' => 'Deleted.']);
    }

    // Role → ValidationRuleGroup mapping. Legacy reads roles.rule_group in
    // PolicyController::submitToApproval to pick which rule set to evaluate.
    public function rolesWithRuleGroup(Request $request): JsonResponse
    {
        $rows = DB::table('roles as r')
            ->leftJoin('tb_prvalidationrulegroupmasters as g',
                'g.n_PrValidationRuleGroupMasters_PK', '=', 'r.rule_group')
            ->select([
                'r.id', 'r.name', 'r.guard_name', 'r.rule_group',
                'g.s_RuleCode as group_code', 'g.s_RuleDesc as group_desc',
            ]);
        if ($s = $request->query('search')) {
            $rows->where('r.name', 'like', "%$s%");
        }
        return response()->json(['data' => $rows->orderBy('r.id')->get()]);
    }

    public function rolesAssignRuleGroup(Request $request, int $roleId): JsonResponse
    {
        $v = $request->validate([
            'rule_group' => 'nullable|integer',
        ]);
        $role = DB::table('roles')->where('id', $roleId)->first();
        if (!$role) return response()->json(['error' => 'Role not found.'], 404);
        if (!empty($v['rule_group'])) {
            $exists = DB::table('tb_prvalidationrulegroupmasters')
                ->where('n_PrValidationRuleGroupMasters_PK', $v['rule_group'])->exists();
            if (!$exists) {
                return response()->json(['error' => 'rule_group does not exist.'], 422);
            }
        }
        DB::table('roles')->where('id', $roleId)->update([
            'rule_group' => $v['rule_group'] ?? null,
        ]);
        return response()->json(['message' => 'Role rule group updated.']);
    }

    // ─── Validation Rule preview — diagnostic, read-only ─────────────
    // Returns the 6-action permission map for (policyId, actionId, current user).
    // Frontend uses this to pre-emptively grey out buttons (Rate, Issue, etc.)
    // before the user clicks. Each action endpoint can ALSO call this server-side
    // to gate itself. Failure-safe: any internal error returns a permissive
    // default so a bug in the engine never blocks legitimate UW work.
    public function validationPreview(\Illuminate\Http\Request $request, int $policyId): JsonResponse
    {
        $actionId = (int) $request->query('action_id', 0);

        // If no action_id supplied, pick the latest non-deleted action for this policy.
        if ($actionId <= 0) {
            $latest = DB::table('policy_actions')
                ->where('policy_id', $policyId)
                ->whereNull('deleted_at')
                ->orderByDesc('id')
                ->first(['id']);
            if (!$latest) {
                return response()->json([
                    'error' => 'Policy has no actions to evaluate against.',
                ], 422);
            }
            $actionId = (int) $latest->id;
        }

        $result = \AlphaDirect\Services\Validation\PolicyValidationRuleEngine::evaluateActions(
            $policyId,
            $actionId,
            auth()->id()
        );

        return response()->json([
            'data' => [
                'policy_id'      => $policyId,
                'action_id'      => $actionId,
                'allowed'        => $result['allowed'],
                'blocking_rules' => $result['blockingRules'],
            ],
        ]);
    }

    public function companiesAdminDestroy(int $id): JsonResponse
    {
        $row = DB::table('companies')->where('id', $id)->first();
        if (!$row) return response()->json(['error' => 'Not found.'], 404);
        // Refuse to delete a parent with children — would orphan the sub-companies.
        $hasChildren = DB::table('companies')->where('parent_id', $id)->count();
        if ($hasChildren > 0) {
            return response()->json([
                'error' => "Cannot delete: this company has {$hasChildren} sub-compan" . ($hasChildren === 1 ? 'y' : 'ies') . ". Reassign or delete those first.",
            ], 422);
        }
        if (\Schema::hasColumn('companies', 'deleted_at')) {
            DB::table('companies')->where('id', $id)->update(['deleted_at' => now()]);
        } else {
            DB::table('companies')->where('id', $id)->update(['status' => 0, 'updated_at' => now()]);
        }
        if (class_exists(\AlphaDirect\Services\CacheService::class)) {
            \Illuminate\Support\Facades\Cache::forget('login_lookups_v2');
            \AlphaDirect\Services\CacheService::forgetLookups('policy_create_data');
        }
        return response()->json(['message' => 'Deleted.']);
    }

    // ─── High-Risk Countries (AML watch-list CRUD) ─────────────────────────
    // Drives the "High Risk Customer" badge on the policy view: a customer whose
    // customer_profile.countryId is in this list is flagged high-risk. Compliance
    // manages the list here so countries can be added/removed without a deploy.

    public function highRiskCountriesIndex(Request $request): JsonResponse
    {
        $query = DB::table('high_risk_countries as h')
            ->leftJoin('countries as c', 'c.id', '=', 'h.country_id')
            ->select([
                'h.id', 'h.country_id', 'h.created_by', 'h.created_at',
                'c.name as country_name', 'c.sortname as country_code',
            ]);
        if ($s = $request->query('search')) {
            $query->where('c.name', 'like', "%$s%");
        }
        $perPage = (int) ($request->query('per_page', 25));
        return response()->json($query->orderBy('c.name')->paginate($perPage));
    }

    /**
     * Countries available to add — every country not already on the list.
     * Feeds the "Add Country" dropdown so an operator can't add a duplicate.
     */
    public function highRiskCountriesOptions(): JsonResponse
    {
        $taken = DB::table('high_risk_countries')->pluck('country_id')->all();
        $rows = DB::table('countries')
            ->when($taken, fn ($q) => $q->whereNotIn('id', $taken))
            ->orderBy('name')
            ->get(['id', 'name', 'sortname as code']);
        return response()->json(['data' => $rows]);
    }

    public function highRiskCountriesStore(Request $request): JsonResponse
    {
        $v = $request->validate([
            'country_id' => 'required|integer|exists:countries,id',
        ]);
        if (DB::table('high_risk_countries')->where('country_id', $v['country_id'])->exists()) {
            return response()->json(['error' => 'This country is already on the high-risk list.'], 422);
        }
        $id = DB::table('high_risk_countries')->insertGetId([
            'country_id' => $v['country_id'],
            'created_by' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return response()->json(['message' => 'Country added to high-risk list.', 'id' => $id], 201);
    }

    public function highRiskCountriesDestroy(int $id): JsonResponse
    {
        $row = DB::table('high_risk_countries')->where('id', $id)->first();
        if (!$row) return response()->json(['error' => 'Not found.'], 404);
        DB::table('high_risk_countries')->where('id', $id)->delete();
        return response()->json(['message' => 'Country removed from high-risk list.']);
    }
}
