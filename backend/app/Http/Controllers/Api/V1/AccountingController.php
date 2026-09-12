<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccountingController extends Controller
{
    // =========================================================================
    //  Chart of Accounts
    // =========================================================================

    public function accounts(Request $request): JsonResponse
    {
        $query = DB::table('accounts')->orderBy('account_name');
        if ($request->has('search')) {
            $s = $request->input('search');
            $query->where(fn($q) => $q->where('account_name', 'like', "%{$s}%")->orWhere('account_num', 'like', "%{$s}%"));
        }
        return response()->json(['data' => $query->get()]);
    }

    public function storeAccount(Request $request): JsonResponse
    {
        $data = $request->validate([
            'account_name' => 'required|string|max:200',
            'account_num'  => 'required|string|max:50',
            'branch_name'  => 'nullable|string|max:200',
            'branch_code'  => 'nullable|string|max:50',
        ]);
        $data['created_at'] = now();
        $data['updated_at'] = now();
        $id = DB::table('accounts')->insertGetId($data);
        return response()->json(['message' => 'Account created.', 'data' => ['id' => $id]], 201);
    }

    public function updateAccount(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'account_name' => 'required|string|max:200',
            'account_num'  => 'required|string|max:50',
            'branch_name'  => 'nullable|string|max:200',
            'branch_code'  => 'nullable|string|max:50',
        ]);
        $data['updated_at'] = now();
        DB::table('accounts')->where('id', $id)->update($data);
        return response()->json(['message' => 'Account updated.']);
    }

    public function destroyAccount(int $id): JsonResponse
    {
        DB::table('accounts')->where('id', $id)->delete();
        return response()->json(['message' => 'Account deleted.']);
    }

    // =========================================================================
    //  Accounting Rules
    // =========================================================================

    public function rules(Request $request): JsonResponse
    {
        $rules = DB::table('accounting_rules as r')
            ->leftJoin('products as p', 'p.id', '=', 'r.product_id')
            ->select('r.*', 'p.name as product_name')
            ->orderBy('r.id', 'desc')
            ->get();
        return response()->json(['data' => $rules]);
    }

    public function storeRule(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id'   => 'required|integer',
            'action'       => 'required|string|max:100',
            'action_type'  => 'required|integer',
            'account_name' => 'required|string|max:200',
            'entry_type'   => 'required|string|in:Credit,Debit',
        ]);
        $data['created_at'] = now();
        $data['updated_at'] = now();
        $id = DB::table('accounting_rules')->insertGetId($data);
        return response()->json(['message' => 'Rule created.', 'data' => ['id' => $id]], 201);
    }

    public function updateRule(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'product_id'   => 'required|integer',
            'action'       => 'required|string|max:100',
            'action_type'  => 'required|integer',
            'account_name' => 'required|string|max:200',
            'entry_type'   => 'required|string|in:Credit,Debit',
        ]);
        $data['updated_at'] = now();
        DB::table('accounting_rules')->where('id', $id)->update($data);
        return response()->json(['message' => 'Rule updated.']);
    }

    public function destroyRule(int $id): JsonResponse
    {
        DB::table('accounting_rules')->where('id', $id)->delete();
        return response()->json(['message' => 'Rule deleted.']);
    }

    // =========================================================================
    //  Sub Ledger
    // =========================================================================

    public function subLedger(Request $request): JsonResponse
    {
        $query = DB::table('policy_subledger as sl')
            ->leftJoin('accounts as a', 'a.id', '=', 'sl.account_id')
            ->orderByDesc('sl.id');

        if ($request->has('policy_id'))  $query->where('sl.policy_id', $request->input('policy_id'));
        if ($request->has('account_id')) $query->where('sl.account_id', $request->input('account_id'));
        if ($request->has('date_from'))  $query->whereDate('sl.created_at', '>=', $request->input('date_from'));
        if ($request->has('date_to'))    $query->whereDate('sl.created_at', '<=', $request->input('date_to'));

        $results = $query->select([
            'sl.*', 'a.account_name', 'a.account_num',
        ])->simplePaginate($request->input('per_page', 50));

        return response()->json([
            'data' => collect($results->items()),
            'meta' => [
                'current_page' => $results->currentPage(),
                'per_page'     => $results->perPage(),
                'has_more'     => $results->hasMorePages(),
            ],
        ]);
    }

    // =========================================================================
    //  Trial Balance (summary)
    // =========================================================================

    public function trialBalance(Request $request): JsonResponse
    {
        $dateFrom = $request->input('date_from', now()->startOfYear()->toDateString());
        $dateTo   = $request->input('date_to', now()->toDateString());

        $balance = DB::table('policy_subledger as sl')
            ->join('accounts as a', 'a.id', '=', 'sl.account_id')
            ->whereBetween('sl.created_at', [$dateFrom, $dateTo . ' 23:59:59'])
            ->groupBy('sl.account_id', 'a.account_name', 'a.account_num')
            ->selectRaw("
                sl.account_id,
                a.account_name,
                a.account_num,
                COALESCE(SUM(sl.debit), 0)  as total_debit,
                COALESCE(SUM(sl.credit), 0) as total_credit,
                COALESCE(SUM(sl.debit), 0) - COALESCE(SUM(sl.credit), 0) as balance
            ")
            ->orderBy('a.account_name')
            ->get();

        $totals = [
            'totalDebit'  => $balance->sum('total_debit'),
            'totalCredit' => $balance->sum('total_credit'),
        ];

        return response()->json([
            'data' => $balance,
            'totals' => $totals,
            'period' => ['from' => $dateFrom, 'to' => $dateTo],
        ]);
    }
}
