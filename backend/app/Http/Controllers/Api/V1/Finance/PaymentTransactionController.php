<?php

namespace AlphaDirect\Http\Controllers\Api\V1\Finance;

use AlphaDirect\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Read-only Finance API for the alpha-finance ERP and any other downstream
 * consumer that needs to pull payment transactions on a schedule.
 *
 * Auth: Sanctum personal access token with ability `finance:read`. Tokens are
 * minted per service account (not per human user). See
 * `php artisan finance:mint-erp-token` for the mint flow.
 *
 * Safety choices documented here so future maintainers don't roll them back:
 *
 *  1. Cursor pagination, not offset pagination. Payment volume can grow into
 *     millions of rows; OFFSET 1_000_000 makes MariaDB sad. cursorPaginate()
 *     emits a WHERE id < N + LIMIT instead.
 *  2. Mandatory date window with a 31-day cap. ERP can sweep month-by-month
 *     without ever asking for the whole table.
 *  3. Default page size 500, hard cap 1000. JSON response stays well below
 *     the api container's memory limit even on the largest pages.
 *  4. Case-insensitive status match. `payment_transactions.status` has shipped
 *     historically as 'Success' / 'SUCCESS' / 'success'; we normalise on input
 *     so callers don't need to know.
 *  5. SELECT-only. No write methods on this controller — the whole class is
 *     read-only by design; write paths live elsewhere.
 *  6. DB query builder, not Eloquent. We don't need model events, audits, or
 *     accessors here, and skipping them keeps each row cheap.
 */
class PaymentTransactionController extends Controller
{
    /**
     * Hard ceiling on the date window any single request can ask for.
     * 31 days is the natural Finance unit (month-by-month sweeps). If a caller
     * needs more, they paginate with the cursor or issue another month.
     */
    private const MAX_WINDOW_DAYS = 31;

    /**
     * Per-page hard cap. 1000 keeps the JSON payload under a few MB even with
     * all the joined columns; the default of 500 leaves headroom.
     */
    private const MAX_LIMIT = 1000;
    private const DEFAULT_LIMIT = 500;

    /**
     * GET /api/v1/finance/payment-transactions
     *
     * Cursor-paginated list of payment_transactions filtered by date window
     * and optional facets. Response is stable JSON — the ERP can rely on this
     * shape across deploys.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $this->validateIndexRequest($request);

        $rows = DB::connection('mysql')
            ->table('payment_transactions as t')
            ->leftJoin('policies as p',     'p.policyNumber',  '=', 't.policyNumber')
            ->leftJoin('customer as c',     'c.id',            '=', 'p.customer_id')
            ->leftJoin('products as pr',    'pr.id',           '=', 'p.product_id')
            ->leftJoin('product_plans as pp','pp.id',          '=', 'p.plan_id')
            ->leftJoin('users as u',        'u.id',            '=', 'p.agent_id')
            ->select([
                't.id',
                't.policyNumber                                              as policy_number',
                't.referenceNumber                                           as reference_number',
                't.amount                                                    as amount',
                't.paymentMethod                                             as payment_method',
                't.status                                                    as status',
                't.is_refund                                                 as is_refund',
                't.is_reverse                                                as is_reverse',
                't.paymentDate                                               as paid_at',
                't.new_payment_date                                          as paid_on',
                't.created_at                                                as recorded_at',
                't.updated_at                                                as updated_at',
                't.TransID                                                   as dpo_trans_id',
                't.CompanyRef                                                as dpo_company_ref',
                't.TransactionToken                                          as dpo_token',
                't.note                                                      as note',
                't.paymentFrequency                                          as payment_frequency',
                'p.id                                                        as policy_id',
                'p.product_id                                                as product_id',
                'p.plan_id                                                   as plan_id',
                'pr.name                                                     as product_name',
                'pp.name                                                     as plan_name',
                DB::raw("TRIM(CONCAT(IFNULL(c.firstName,''),' ',IFNULL(c.lastName,''))) as customer_name"),
                DB::raw("TRIM(CONCAT(IFNULL(u.firstName,''),' ',IFNULL(u.lastName,''))) as agent_name"),
            ])
            ->whereBetween('t.created_at', [
                $validated['date_from'].' 00:00:00',
                $validated['date_to'].'   23:59:59',
            ])
            ->when($validated['partner'] ?? null, function ($q, $v) {
                $q->where('t.paymentMethod', $v);
            })
            ->when($validated['policy_number'] ?? null, function ($q, $v) {
                $q->where('t.policyNumber', $v);
            })
            ->when($validated['reference_number'] ?? null, function ($q, $v) {
                $q->where('t.referenceNumber', $v);
            })
            ->when(array_key_exists('product_id', $validated), function ($q) use ($validated) {
                $q->where('p.product_id', $validated['product_id']);
            })
            ->when($validated['status'] ?? null, function ($q, $v) {
                // Case-insensitive status match — historical rows shipped with
                // mixed casing ('Success','SUCCESS','success'). Normalise here
                // so callers can send any casing.
                $q->whereRaw('LOWER(t.status) = ?', [strtolower($v)]);
            })
            ->when(array_key_exists('is_refund', $validated), function ($q) use ($validated) {
                $q->where('t.is_refund', $validated['is_refund'] ? 1 : 0);
            })
            ->whereNull('t.deleted_at')
            ->orderBy('t.id', 'desc')
            ->cursorPaginate(
                perPage: $validated['limit'],
                cursorName: 'cursor',
            );

        return response()->json([
            'data' => collect($rows->items())->map(fn ($row) => $this->shapeRow($row))->all(),
            'meta' => [
                'count'       => $rows->count(),
                'per_page'    => $rows->perPage(),
                'next_cursor' => $rows->nextCursor()?->encode(),
                'window'      => [
                    'from' => $validated['date_from'],
                    'to'   => $validated['date_to'],
                ],
                'has_more'    => $rows->hasMorePages(),
            ],
        ]);
    }

    /**
     * GET /api/v1/finance/payment-transactions/{id}
     *
     * Single transaction by primary key. Returns 404 when missing or
     * soft-deleted so callers don't accidentally resurrect refunded rows.
     */
    public function show(int $id): JsonResponse
    {
        $row = DB::connection('mysql')
            ->table('payment_transactions as t')
            ->leftJoin('policies as p',      'p.policyNumber',  '=', 't.policyNumber')
            ->leftJoin('customer as c',      'c.id',            '=', 'p.customer_id')
            ->leftJoin('products as pr',     'pr.id',           '=', 'p.product_id')
            ->leftJoin('product_plans as pp','pp.id',           '=', 'p.plan_id')
            ->leftJoin('users as u',         'u.id',            '=', 'p.agent_id')
            ->select([
                't.id',
                't.policyNumber       as policy_number',
                't.referenceNumber    as reference_number',
                't.amount             as amount',
                't.paymentMethod      as payment_method',
                't.status             as status',
                't.is_refund          as is_refund',
                't.is_reverse         as is_reverse',
                't.paymentDate        as paid_at',
                't.new_payment_date   as paid_on',
                't.created_at         as recorded_at',
                't.updated_at         as updated_at',
                't.TransID            as dpo_trans_id',
                't.CompanyRef         as dpo_company_ref',
                't.TransactionToken   as dpo_token',
                't.note               as note',
                't.paymentFrequency   as payment_frequency',
                'p.id                 as policy_id',
                'p.product_id         as product_id',
                'p.plan_id            as plan_id',
                'pr.name              as product_name',
                'pp.name              as plan_name',
                DB::raw("TRIM(CONCAT(IFNULL(c.firstName,''),' ',IFNULL(c.lastName,''))) as customer_name"),
                DB::raw("TRIM(CONCAT(IFNULL(u.firstName,''),' ',IFNULL(u.lastName,''))) as agent_name"),
            ])
            ->where('t.id', $id)
            ->whereNull('t.deleted_at')
            ->first();

        if (!$row) {
            return response()->json(['error' => 'not_found'], 404);
        }

        return response()->json(['data' => $this->shapeRow($row)]);
    }

    /**
     * Validate the index-request facets and normalise dates / booleans.
     *
     * Throws ValidationException (Laravel turns it into a 422 with field
     * errors) — never returns a partially-valid payload.
     */
    private function validateIndexRequest(Request $request): array
    {
        $validated = $request->validate([
            'date_from'        => ['required', 'date_format:Y-m-d'],
            'date_to'          => ['required', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'partner'          => ['nullable', 'string', 'max:50'],
            'policy_number'    => ['nullable', 'string', 'max:40'],
            'reference_number' => ['nullable', 'string', 'max:191'],
            'product_id'       => ['nullable', 'integer', 'min:1'],
            'status'           => ['nullable', 'string', 'max:30'],
            'is_refund'        => ['nullable', 'boolean'],
            'limit'            => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_LIMIT],
        ]);

        // Enforce the 31-day window manually — Laravel's stock validators do
        // not have a "max date diff" rule and we want an explicit 422 here
        // rather than letting a 90-day query run and time out.
        $from = Carbon::createFromFormat('Y-m-d', $validated['date_from'])->startOfDay();
        $to   = Carbon::createFromFormat('Y-m-d', $validated['date_to'])->endOfDay();
        if ($from->diffInDays($to) >= self::MAX_WINDOW_DAYS) {
            throw ValidationException::withMessages([
                'date_to' => 'Date window may not exceed '.self::MAX_WINDOW_DAYS.' days.',
            ]);
        }

        $validated['limit'] = $validated['limit'] ?? self::DEFAULT_LIMIT;

        return $validated;
    }

    /**
     * Normalise a single DB row into the stable public JSON schema.
     *
     * Centralises type coercion (string → number, 0/1 → bool) so the response
     * shape is consistent across `index` and `show` and across deploys.
     */
    private function shapeRow(object $row): array
    {
        return [
            'id'                => (int)    $row->id,
            'policy_number'     =>          $row->policy_number,
            'reference_number'  =>          $row->reference_number,
            'amount'            => (float)  str_replace(',', '.', (string) $row->amount),
            'payment_method'    =>          $row->payment_method,
            'status'            =>          $row->status,
            'is_refund'         => (bool)   $row->is_refund,
            'is_reverse'        => (bool)   $row->is_reverse,
            'paid_at'           =>          $row->paid_at,
            'paid_on'           =>          $row->paid_on,
            'recorded_at'       =>          $row->recorded_at,
            'updated_at'        =>          $row->updated_at,
            'note'              =>          $row->note,
            'payment_frequency' =>          $row->payment_frequency,
            'policy' => [
                'id'           => $row->policy_id  !== null ? (int) $row->policy_id  : null,
                'product_id'   => $row->product_id !== null ? (int) $row->product_id : null,
                'plan_id'      => $row->plan_id    !== null ? (int) $row->plan_id    : null,
                'product_name' => $row->product_name,
                'plan_name'    => $row->plan_name,
            ],
            'customer_name' => $row->customer_name !== '' ? $row->customer_name : null,
            'agent_name'    => $row->agent_name    !== '' ? $row->agent_name    : null,
            'dpo' => [
                'trans_id'    => $row->dpo_trans_id,
                'company_ref' => $row->dpo_company_ref,
                'token'       => $row->dpo_token,
            ],
        ];
    }
}
