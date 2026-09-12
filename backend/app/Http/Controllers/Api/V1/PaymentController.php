<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Services\StorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * PaymentController
 *
 * Provides read/write API endpoints for payment data consumed by the
 * Graphite v2 React admin UI: DPO transactions, RealPay logs,
 * Orange Money transactions, scheduled transactions, and offline payments.
 *
 * Routes (read):
 *   GET  /api/v1/payments/dpo
 *   GET  /api/v1/payments/realpay
 *   GET  /api/v1/payments/orange-money
 *   GET  /api/v1/payments/schedule
 *
 * Routes (write):
 *   POST /api/v1/payments/{policyId}/offline
 */
class PaymentController extends Controller
{
    // ──────────────────────────────────────────────────────────────
    // GET /api/v1/payments/dpo
    // ──────────────────────────────────────────────────────────────

    /**
     * List DPO payment transactions with optional search, status,
     * and date-range filters. Returns paginated JSON (50 per page).
     */
    public function dpoTransactions(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search'     => 'nullable|string|max:100',
            'status'     => 'nullable|string|max:30',
            'date_from'  => 'nullable|date',
            'date_to'    => 'nullable|date',
            'per_page'   => 'nullable|integer|min:5|max:100',
        ]);

        $query = DB::table('payment_transactions')
            ->whereNotNull('id')
            ->when($validated['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($validated['date_from'] ?? null, fn ($q, $v) => $q->whereDate('new_payment_date', '>=', $v))
            ->when($validated['date_to'] ?? null, fn ($q, $v) => $q->whereDate('new_payment_date', '<=', $v))
            ->when($validated['search'] ?? null, function ($q, $search) {
                $q->where(function ($q) use ($search) {
                    $q->where('policyNumber', 'like', "%{$search}%")
                      ->orWhere('referenceNumber', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('id');

        $perPage = $validated['per_page'] ?? 50;
        $results = $query->paginate($perPage);

        // Batch-load policy info (customer names) to avoid JOINs on remote DB
        $items = collect($results->items());
        $policyNumbers = $items->pluck('policyNumber')->filter()->unique()->values()->toArray();
        $policyMap = [];
        if (!empty($policyNumbers)) {
            $policyMap = DB::table('policies')
                ->join('customer', 'customer.id', '=', 'policies.customer_id')
                ->whereIn('policies.policyNumber', $policyNumbers)
                ->select([
                    'policies.policyNumber',
                    'policies.id as policy_id',
                    DB::raw("CONCAT(COALESCE(customer.firstName, ''), ' ', COALESCE(customer.lastName, '')) as customerName"),
                ])
                ->get()
                ->keyBy('policyNumber')
                ->toArray();
        }

        return response()->json([
            'data' => $items->map(function ($row) use ($policyMap) {
                $policy = $policyMap[$row->policyNumber] ?? null;
                return [
                    'id'              => $row->id,
                    'policyId'        => $row->policy_id ?? ($policy->policy_id ?? null),
                    'policyNumber'    => $row->policyNumber,
                    'customerName'    => $policy->customerName ?? null,
                    'amount'          => $row->amount,
                    'status'          => $row->status,
                    'paymentMethod'   => $row->paymentMethod,
                    'paymentDate'     => $row->new_payment_date ?? $row->paymentDate,
                    'referenceNumber' => $row->referenceNumber,
                    'createdAt'       => $row->created_at,
                ];
            }),
            'meta' => $this->paginationMeta($results),
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // GET /api/v1/payments/realpay
    // ──────────────────────────────────────────────────────────────

    /**
     * List RealPay event logs with optional search by policy_id.
     * Returns paginated JSON (50 per page).
     */
    public function realpayLogs(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search'   => 'nullable|string|max:100',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $query = DB::table('realpay_logs')
            ->when($validated['search'] ?? null, function ($q, $search) {
                $q->where(function ($q) use ($search) {
                    $q->where('policy_id', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('id');

        $perPage = $validated['per_page'] ?? 50;
        $results = $query->paginate($perPage);

        // Batch-load policy numbers from policy_id
        $items = collect($results->items());
        $policyIds = $items->pluck('policy_id')->filter()->unique()->values()->toArray();
        $policyMap = [];
        if (!empty($policyIds)) {
            $policyMap = DB::table('policies')
                ->whereIn('id', $policyIds)
                ->pluck('policyNumber', 'id')
                ->toArray();
        }

        return response()->json([
            'data' => $items->map(function ($row) use ($policyMap) {
                return [
                    'id'              => $row->id,
                    'policyId'        => $row->policy_id,
                    'policyNumber'    => $policyMap[$row->policy_id] ?? null,
                    'eventType'       => $row->event ?? null,
                    'status'          => $row->status ?? null,
                    'responseMessage' => $row->input_data ?? null,
                    'createdAt'       => $row->created_at,
                ];
            }),
            'meta' => $this->paginationMeta($results),
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // GET /api/v1/payments/orange-money
    // ──────────────────────────────────────────────────────────────

    /**
     * List Orange Money transactions (payment_transactions where
     * paymentMethod is Orange Money). Same structure as dpoTransactions.
     */
    public function orangeMoneyTransactions(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search'     => 'nullable|string|max:100',
            'status'     => 'nullable|string|max:30',
            'date_from'  => 'nullable|date',
            'date_to'    => 'nullable|date',
            'per_page'   => 'nullable|integer|min:5|max:100',
        ]);

        $query = DB::table('payment_transactions')
            ->where(function ($q) {
                $q->where('paymentMethod', 'ORANGE_MONEY')
                  ->orWhere('paymentMethod', 'Orange Money')
                  ->orWhere('paymentMethod', 'like', '%orange%');
            })
            ->when($validated['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($validated['date_from'] ?? null, fn ($q, $v) => $q->whereDate('new_payment_date', '>=', $v))
            ->when($validated['date_to'] ?? null, fn ($q, $v) => $q->whereDate('new_payment_date', '<=', $v))
            ->when($validated['search'] ?? null, function ($q, $search) {
                $q->where(function ($q) use ($search) {
                    $q->where('policyNumber', 'like', "%{$search}%")
                      ->orWhere('referenceNumber', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('id');

        $perPage = $validated['per_page'] ?? 50;
        $results = $query->paginate($perPage);

        // Batch-load customer names
        $items = collect($results->items());
        $policyNumbers = $items->pluck('policyNumber')->filter()->unique()->values()->toArray();
        $policyMap = [];
        if (!empty($policyNumbers)) {
            $policyMap = DB::table('policies')
                ->join('customer', 'customer.id', '=', 'policies.customer_id')
                ->whereIn('policies.policyNumber', $policyNumbers)
                ->select([
                    'policies.policyNumber',
                    'policies.id as policy_id',
                    DB::raw("CONCAT(COALESCE(customer.firstName, ''), ' ', COALESCE(customer.lastName, '')) as customerName"),
                ])
                ->get()
                ->keyBy('policyNumber')
                ->toArray();
        }

        return response()->json([
            'data' => $items->map(function ($row) use ($policyMap) {
                $policy = $policyMap[$row->policyNumber] ?? null;
                return [
                    'id'              => $row->id,
                    'policyId'        => $row->policy_id ?? ($policy->policy_id ?? null),
                    'policyNumber'    => $row->policyNumber,
                    'customerName'    => $policy->customerName ?? null,
                    'amount'          => $row->amount,
                    'status'          => $row->status,
                    'paymentMethod'   => $row->paymentMethod,
                    'paymentDate'     => $row->new_payment_date ?? $row->paymentDate,
                    'referenceNumber' => $row->referenceNumber,
                    'createdAt'       => $row->created_at,
                ];
            }),
            'meta' => $this->paginationMeta($results),
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // GET /api/v1/payments/schedule
    // ──────────────────────────────────────────────────────────────

    /**
     * List scheduled transactions with optional search by policy number.
     * Returns paginated JSON (50 per page).
     */
    public function scheduleTransactions(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search'   => 'nullable|string|max:100',
            'status'   => 'nullable|string|max:30',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $query = DB::table('scheduled_transactions')
            ->when($validated['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($validated['search'] ?? null, function ($q, $search) {
                $q->where(function ($q) use ($search) {
                    $q->where('policy_number', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('id');

        $perPage = $validated['per_page'] ?? 50;
        $results = $query->paginate($perPage);

        return response()->json([
            'data' => collect($results->items())->map(fn ($row) => [
                'id'              => $row->id,
                'policyId'        => $row->policy_id,
                'policyNumber'    => $row->policy_number,
                'amount'          => $row->premium,
                'frequency'       => $row->installment ?? null,
                'nextPaymentDate' => $row->billing_date,
                'status'          => $row->status,
                'createdAt'       => $row->created_at,
            ]),
            'meta' => $this->paginationMeta($results),
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // POST /api/v1/payments/{policyId}/offline    (multipart/form-data)
    //
    // Records an offline (CASH) payment against a policy. Mirrors the
    // graphiteBWV8 AddOfflinePayments Livewire form. Accepts either the
    // legacy minimal payload (amount, payment_date, receipt_number,
    // notes) or the full V8 payload with paymentRecievedBy,
    // numberOfInstalmentsPaid, and a payment_image proof upload.
    //
    // Gated by Spatie permission `offline-payments-edit`.
    // ──────────────────────────────────────────────────────────────

    public function offlinePayment(Request $request, int $policyId): JsonResponse
    {
        $user = auth()->user();
        if (!$user || (method_exists($user, 'can') && !$user->can('offline-payments-edit'))) {
            return response()->json(['message' => 'Permission denied.'], 403);
        }

        $validated = $request->validate([
            'amount'                      => 'required|numeric|min:0.01',
            'payment_date'                => 'required|date',
            'receipt_number'              => 'required|string|max:100',
            'notes'                       => 'nullable|string|max:500',
            // V8 parity — extended fields. All optional so old callers
            // (the standalone /payments/{policyId}/offline endpoint that
            // existed before this tab landed) keep working unchanged.
            'payment_received_by'         => 'nullable|string|max:120',
            'number_of_instalments_paid'  => 'nullable|integer|min:0|max:120',
            'payment_image'               => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);

        $policy = DB::table('policies')->where('id', $policyId)->first(['id', 'policyNumber', 'customer_id']);
        if (!$policy) {
            return response()->json(['message' => 'Policy not found.'], 404);
        }

        // ── Optional proof upload (S3 with local fallback) ────────────
        $proofPath = null;
        if ($request->hasFile('payment_image')) {
            try {
                $storage = app(StorageService::class);
                $dir = 'PolicyPayment/' . $policy->policyNumber;
                $stored = $storage->putFileWithFallback($dir, $request->file('payment_image'), ['visibility' => 'private']);
                $proofPath = $stored['path'] ?? null;
            } catch (\Throwable $e) {
                return response()->json([
                    'message' => 'Proof upload failed: ' . $e->getMessage(),
                ], 500);
            }
        }

        // Reference number follows the graphiteBWV8 format:
        //   {unix_timestamp}/{receiptNumber}
        // The timestamp prefix keeps the stored reference unique even when
        // operators reuse the same receipt number across policies.
        $referenceNumber = Carbon::now()->timestamp . '/' . $validated['receipt_number'];

        $id = DB::table('payment_transactions')->insertGetId([
            'policy_id'              => $policy->id,
            'policyNumber'           => $policy->policyNumber,
            'amount'                 => $validated['amount'],
            'status'                 => 'SUCCESS',
            'paymentMethod'          => 'CASH',
            'paymentDate'            => $validated['payment_date'],
            'new_payment_date'       => $validated['payment_date'],
            'referenceNumber'        => $referenceNumber,
            'note'                   => $validated['notes'] ?? null,
            'cashRecipient'          => $validated['payment_received_by'] ?? null,
            'numberOfInstalmentsPaid'=> $validated['number_of_instalments_paid'] ?? null,
            'payment_proof_link'     => $proofPath,
            'paymentLoggedBy'        => auth()->id(),
            'is_ledger'              => 0,
            'created_at'             => now(),
            'updated_at'             => now(),
        ]);

        // Reflect the payment on the ledger NOW rather than at the next cron.
        //
        // This row used to be left at is_ledger = 0 for the nightly poster —
        // 03:30 for DOM/COM and Specialist (LedgerPaymentTransDomCom), 20:40 for
        // everything else (PolicyLedgerDaily). So money taken at the counter did
        // not reach the Statement of Account, the Account View or the Balance
        // Owing tile until the following day, and the policy kept reading as in
        // arrears. PaymentLedgerPoster makes the SAME write the owning cron
        // would, then stamps is_ledger = 1 so the cron skips the row.
        //
        // It never throws: a payment it cannot post stays at is_ledger = 0 and
        // the cron remains the backstop. Recorded money is never rolled back
        // because of a ledger hiccup, which is why this sits outside any
        // transaction wrapping the insert above.
        $ledgerPosted = (new \AlphaDirect\Services\Ledger\PaymentLedgerPoster())->post($id);

        try {
            $policyModel = \AlphaDirect\Policy::find($policy->id);
            if ($policyModel) {
                activity('Offline payment recorded')
                    ->performedOn($policyModel)
                    ->causedBy($user)
                    ->log('Offline (CASH) payment of P ' . number_format((float) $validated['amount'], 2, '.', '')
                        . ' on ' . $validated['payment_date']
                        . ' (ref ' . $referenceNumber . ')'
                        . ($validated['payment_received_by'] ?? null ? ', received by ' . $validated['payment_received_by'] : '')
                        . ' [ledger: ' . $ledgerPosted . ']');
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('offline payment activity log failed: ' . $e->getMessage());
        }

        return response()->json([
            'message' => 'Offline payment recorded successfully.',
            'data'    => [
                'id'                  => $id,
                'policyId'            => $policy->id,
                'policyNumber'        => $policy->policyNumber,
                'amount'              => $validated['amount'],
                'status'              => 'SUCCESS',
                'paymentMethod'       => 'CASH',
                'paymentDate'         => $validated['payment_date'],
                'receiptNumber'       => $validated['receipt_number'],
                'referenceNumber'     => $referenceNumber,
                'paymentReceivedBy'   => $validated['payment_received_by'] ?? null,
                'paymentProofPath'    => $proofPath,
                // 'posted'  = on the ledger now; 'already' = a ledger row for this
                // reference already existed; 'skipped' = left for the nightly
                // poster (the payment itself is recorded either way).
                'ledgerStatus'        => $ledgerPosted,
            ],
        ], 201);
    }

    // ──────────────────────────────────────────────────────────────
    // GET /api/v1/policies/{policyId}/transaction-logs
    //
    // Backs the Transaction Logs tab. V8 unions payment_transactions
    // with payment_transaction_archive; in v2 the archive table does
    // not exist so we read from payment_transactions only (excluding
    // soft-deleted, cancelled, and reversal-pointer rows to match V8
    // visibility rules).
    //
    // Per-row action flags (`canDeleteBeforeLedger`, `canDeleteAfterLedger`)
    // are computed from Spatie permissions + the row's is_ledger state
    // so the FE can render buttons without re-checking permissions.
    // ──────────────────────────────────────────────────────────────
    public function policyTransactionLogs(Request $request, int $policyId): JsonResponse
    {
        $policy = DB::table('policies')->where('id', $policyId)->first(['id', 'policyNumber']);
        if (!$policy) return response()->json(['message' => 'Policy not found.'], 404);

        $user = auth()->user();
        // Reverse rights: Super Admin OR the payment_reversal permission. The
        // per-row before/after-ledger distinction is decided by is_ledger below,
        // so both flags share the single reversal check.
        $canReverse = \AlphaDirect\Services\AuthGate::canReversePayment($user);
        $canBefore = $canReverse;
        $canAfter  = $canReverse;

        $rows = DB::table('payment_transactions as pt')
            ->leftJoin('users as u', 'u.id', '=', 'pt.paymentLoggedBy')
            ->where('pt.policyNumber', $policy->policyNumber)
            ->where('pt.status', '!=', 'CANCELLED')
            ->whereNull('pt.deleted_at')
            ->whereNull('pt.reveral_transaction_id')
            ->orderByDesc('pt.created_at')
            ->get([
                'pt.id', 'pt.policyNumber', 'pt.referenceNumber',
                'pt.amount', 'pt.paymentMethod', 'pt.paymentDate',
                'pt.new_payment_date', 'pt.numberOfInstalmentsPaid',
                'pt.note', 'pt.status', 'pt.cashRecipient',
                'pt.payment_proof_link', 'pt.is_ledger',
                'pt.CompanyRef', 'pt.is_reverse', 'pt.is_refund',
                'pt.created_at',
                // users table on v2 stores firstName + lastName (no `name` column)
                DB::raw("TRIM(CONCAT(COALESCE(u.firstName, ''), ' ', COALESCE(u.lastName, ''))) as logged_by_name"),
            ]);

        // ── Summary aggregates (V8 RefundMoney.blade.php parity) ──────
        // Reference numbers of refund rows — used to exclude the original
        // payment that was later refunded from the "success" set, same as V8.
        $refundedRefs = DB::table('payment_transactions')
            ->where('policyNumber', $policy->policyNumber)
            ->where('is_refund', 1)
            ->whereNull('deleted_at')
            ->pluck('referenceNumber')
            ->filter()
            ->values()
            ->toArray();

        $successQ = DB::table('payment_transactions')
            ->where('policyNumber', $policy->policyNumber)
            ->whereIn('status', ['Success', 'SUCCESS', 'success'])
            ->whereNull('deleted_at')
            ->where(function ($q) {
                $q->whereNull('CompanyRef')->orWhere('CompanyRef', '!=', 'Reversed');
            })
            ->where(function ($q) {
                $q->whereNull('is_reverse')->orWhere('is_reverse', '!=', 1);
            })
            ->whereNull('reveral_transaction_id');
        if (!empty($refundedRefs)) {
            $successQ->whereNotIn('referenceNumber', $refundedRefs);
        }
        $successCount  = (clone $successQ)->count();
        $successAmount = (float) (clone $successQ)->sum('amount');

        $failedQ = DB::table('payment_transactions')
            ->where('policyNumber', $policy->policyNumber)
            ->whereNotIn('status', ['Success', 'SUCCESS', 'success'])
            ->whereNull('deleted_at');
        $failedCount  = (clone $failedQ)->count();
        $failedAmount = (float) (clone $failedQ)->sum('amount');

        $refundQ = DB::table('payment_transactions')
            ->where('policyNumber', $policy->policyNumber)
            ->where('is_refund', 1)
            ->whereNull('deleted_at');
        $refundCount  = (clone $refundQ)->count();
        $refundAmount = (float) (clone $refundQ)->sum('amount');

        // ── Ledger-only refunds (MIS) ─────────────────────────────────
        // This tab reads payment_transactions, so it only ever sees refunds
        // raised through refundTransaction(), which writes BOTH a
        // payment_transactions row (is_refund=1) and a policy_ledger row.
        // Legacy and backlog refunds were posted straight to policy_ledger
        // with no payment row at all — reported on MIS2024079959, where two
        // refunds (P 98.00 under 'REFUND-BACKLOG-2026' and P 49.00 with an
        // empty reference) show in the Account View and Account Statement but
        // were absent here, and were missing from the Refund totals with them.
        //
        // Those rows are surfaced as read-only entries marked source='ledger'.
        // MIS only, matching the Account Statement refund fix — see
        // AccountStatementService::isDomCom().
        $ledgerRefunds = collect();
        if (!\AlphaDirect\Services\AccountStatementService::isDomCom($policyId)) {
            // refundTransaction() stores the reference as typed on the payment
            // row and upper-cased on the ledger row, so match case-insensitively
            // or an app-raised refund is listed twice.
            $paidRefundRefs = DB::table('payment_transactions')
                ->where('policyNumber', $policy->policyNumber)
                ->where('is_refund', 1)
                ->whereNull('deleted_at')
                ->pluck('referenceNumber')
                ->filter(fn($ref) => $ref !== null && trim($ref) !== '')
                ->map(fn($ref) => strtoupper(trim($ref)))
                ->all();

            $cols = ['id', 'trans_ref', 'debit', 'accounting_date', 'description', 'status'];

            $ledgerRefunds = DB::table('policy_ledger')
                ->where('policy_id', $policyId)
                ->where('trans_type', 'Refund')
                ->whereNull('deleted_at')
                ->get($cols)
                ->each(fn($l) => $l->origin = 'ledger');

            $liveRefundSignatures = $ledgerRefunds->map(fn($l) => self::refundSignature($l))->all();

            // Older refunds (the reported 'REFUND-BACKLOG-2026' among them) sit
            // in the ARCHIVE, which is a separate database — graphite_archive,
            // table policy_ledger, on the 'mysql3' connection — and never a
            // 'policy_ledger_archive' table on this one. Guarding on
            // Schema::hasTable('policy_ledger_archive') was therefore false in
            // every environment, so this merge silently never ran and archived
            // refunds stayed invisible here even after the live-ledger fix.
            // AccountStatementService::archiveQuery() resolves the real one.
            if ($archiveQ = \AlphaDirect\Services\AccountStatementService::archiveQuery()) {
                try {
                    // The archive is a copy of the same table, so its ids run
                    // in the SAME range as the live one — the two must be told
                    // apart by origin, not id, or the UI keys collide.
                    $archiveRows = $archiveQ->where('policy_id', $policyId)
                        ->where('trans_type', 'Refund');
                    if ($archiveQ->getConnection()->getSchemaBuilder()
                            ->hasColumn(\AlphaDirect\Services\AccountStatementService::ARCHIVE_TABLE, 'deleted_at')) {
                        $archiveRows->whereNull('deleted_at');
                    }
                    $ledgerRefunds = $ledgerRefunds->concat(
                        $archiveRows->get($cols)->each(fn($l) => $l->origin = 'ledger-archive')
                    );
                } catch (\Throwable $e) {
                    \Log::warning('archived ledger refund read failed: ' . $e->getMessage());
                }
            }

            $ledgerRefunds = $ledgerRefunds
                ->reject(function ($l) use ($paidRefundRefs) {
                    $ref = trim((string) ($l->trans_ref ?? ''));

                    return $ref !== '' && in_array(strtoupper($ref), $paidRefundRefs, true);
                })
                // Archiving copies rather than moves, so the same refund can
                // sit in both tables. Ids are not comparable across the two
                // databases, so an archived row is matched to its live twin on
                // what identifies the refund itself: reference + date + amount.
                //
                // Deliberately one-directional: only ARCHIVE rows are dropped.
                // A blanket unique() would also collapse two genuinely distinct
                // live refunds that happen to share a date and an amount with no
                // reference between them — exactly the reference-less shape this
                // whole fix exists to surface.
                ->reject(fn($l) => ($l->origin ?? '') === 'ledger-archive'
                    && in_array(self::refundSignature($l), $liveRefundSignatures, true))
                ->sortByDesc(fn($l) => (string) $l->accounting_date)
                ->values();

            $refundCount  += $ledgerRefunds->count();
            $refundAmount += (float) $ledgerRefunds->sum(fn($l) => (float) ($l->debit ?? 0));
        }

        $reverseQ = DB::table('payment_transactions')
            ->where('policyNumber', $policy->policyNumber)
            ->whereNull('deleted_at')
            ->whereNull('reveral_transaction_id')
            ->where(function ($q) {
                $q->where('is_reverse', 1)->orWhere('CompanyRef', 'Reversed');
            });
        $reverseCount  = (clone $reverseQ)->count();
        $reverseAmount = (float) (clone $reverseQ)->sum('amount');

        // V8 formula: success - refund - failed - reversal
        $totalBalance = $successAmount - $refundAmount - $failedAmount - $reverseAmount;

        return response()->json([
            'data' => $rows->map(function ($r) use ($canBefore, $canAfter) {
                $reversed = $r->is_reverse == 1 || $r->CompanyRef === 'Reversed';
                $refunded = $r->is_refund == 1;
                $isLedger = $r->is_ledger == 1;

                return [
                    'id'                       => $r->id,
                    'policyNumber'             => $r->policyNumber,
                    'referenceNumber'          => $r->referenceNumber,
                    'amount'                   => $r->amount,
                    'paymentMethod'            => $r->paymentMethod,
                    'paymentDate'              => $r->paymentDate,
                    'paymentSettlementDate'    => $r->new_payment_date,
                    'numberOfInstalmentsPaid'  => $r->numberOfInstalmentsPaid,
                    'note'                     => $r->note,
                    'status'                   => $r->status,
                    'paymentReceivedBy'        => $r->cashRecipient,
                    'paymentLoggedByName'      => $r->logged_by_name,
                    'paymentProofPath'         => $r->payment_proof_link,
                    'isLedger'                 => $isLedger,
                    'isReversed'               => $reversed,
                    'isRefunded'               => $refunded,
                    'canReverseBeforeLedger'   => $canBefore && !$isLedger && !$reversed && !$refunded,
                    'canReverseAfterLedger'    => $canAfter  && $isLedger && !$reversed && !$refunded,
                    'source'                   => 'payment',
                ];
            })->concat($ledgerRefunds->map(fn($l) => [
                'id'                       => $l->id,
                'policyNumber'             => $policy->policyNumber,
                'referenceNumber'          => trim((string) ($l->trans_ref ?? '')) ?: null,
                'amount'                   => $l->debit,
                'paymentMethod'            => null,
                'paymentDate'              => $l->accounting_date,
                'paymentSettlementDate'    => null,
                'numberOfInstalmentsPaid'  => null,
                'note'                     => $l->description,
                'status'                   => $l->status ?: 'Refunded',
                'paymentReceivedBy'        => null,
                'paymentLoggedByName'      => null,
                'paymentProofPath'         => null,
                'isLedger'                 => true,
                'isReversed'               => false,
                'isRefunded'               => true,
                // Read-only: there is no payment transaction behind these, so
                // there is nothing for the reverse endpoints to act on.
                'canReverseBeforeLedger'   => false,
                'canReverseAfterLedger'    => false,
                'source'                   => $l->origin ?? 'ledger',
            ]))->values(),
            'summary' => [
                'successCount'   => $successCount,
                'successAmount'  => round($successAmount, 2),
                'failedCount'    => $failedCount,
                'failedAmount'   => round($failedAmount, 2),
                'refundCount'    => $refundCount,
                'refundAmount'   => round($refundAmount, 2),
                'reverseCount'   => $reverseCount,
                'reverseAmount'  => round($reverseAmount, 2),
                'totalBalance'   => round($totalBalance, 2),
            ],
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // POST /api/v1/policies/{policyId}/transaction-logs/{txnId}/reverse
    //
    // Reverses an existing payment transaction. Mirrors the graphiteBWV8
    // ReverseTransactionModal behaviour:
    //   1. Marks the original row: is_reverse=1, reversal_date, reversal_by,
    //      reversal_comment.
    //   2. Inserts a duplicate row with reveral_transaction_id pointing
    //      to the original (the duplicate is filtered out of the listing
    //      via whereNull('reveral_transaction_id')).
    //   3. For after-ledger reversals (is_ledger=1), inserts a policy_ledger
    //      row with trans_type='Reverse Payment', status='Reversed',
    //      debit=amount, and updates the original ledger row to status
    //      'Reversed'. Before-ledger reversals skip the ledger writes
    //      (no ledger entries have been posted yet to offset).
    //
    // Required permission:
    //   - is_ledger=1 → `payment_delete_after_ledger`
    //   - is_ledger=0 → `payment_delete_before_ledger`
    // (Names preserved from V8 even though the action is reverse, not delete.)
    // ──────────────────────────────────────────────────────────────
    public function reverseTransaction(Request $request, int $policyId, int $txnId): JsonResponse
    {
        $validated = $request->validate([
            'reversal_date'    => 'required|date_format:Y-m-d|before_or_equal:today',
            'comments'         => 'required|string|max:500',
            'reference_number' => 'nullable|string|max:100',
        ]);

        $user = auth()->user();
        if (!$user) return response()->json(['message' => 'Unauthenticated.'], 401);

        return DB::transaction(function () use ($policyId, $txnId, $validated, $user) {
            $txn = DB::table('payment_transactions')
                ->where('id', $txnId)
                ->where('policy_id', $policyId)
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->first();
            if (!$txn) return response()->json(['message' => 'Transaction not found.'], 404);
            if ($txn->is_reverse == 1 || $txn->CompanyRef === 'Reversed') {
                return response()->json(['message' => 'Transaction already reversed.'], 409);
            }
            if ($txn->is_refund == 1) {
                return response()->json(['message' => 'Refund transactions cannot be reversed.'], 409);
            }

            $isAfterLedger = (int) $txn->is_ledger === 1;
            // Reversal is gated on the dedicated payment_reversal permission
            // (Super Admin bypass only) — fail-closed. Replaces the previous
            // payment_delete_* check which was shared/over-broad and fail-open.
            if (!\AlphaDirect\Services\AuthGate::canReversePayment($user)) {
                return response()->json(['message' => 'Permission denied.', 'required' => 'payment_reversal'], 403);
            }

            $now = Carbon::now();
            $reversalDate = $validated['reversal_date'];

            // 1) Flag the original row as reversed
            DB::table('payment_transactions')
                ->where('id', $txnId)
                ->update([
                    'is_reverse'       => 1,
                    'reversal_date'    => $reversalDate,
                    'reversal_by'      => $user->id,
                    'reversal_comment' => $validated['comments'],
                    'updated_at'       => $now,
                ]);

            // 2) Insert a duplicate row pointing back at the original.
            //    Listing query excludes rows where reveral_transaction_id IS NOT NULL.
            $original = (array) $txn;
            unset($original['id'], $original['created_at'], $original['updated_at'], $original['deleted_at']);
            $original['reveral_transaction_id'] = $txnId;
            $original['referenceNumber'] = $validated['reference_number'] ?? $txn->referenceNumber;
            $original['paymentLoggedBy'] = $user->id;
            $original['created_at'] = $now;
            $original['updated_at'] = $now;
            // Reset reversal metadata on the duplicate so it doesn't appear "reversed" itself
            $original['is_reverse'] = 0;
            $original['reversal_date'] = null;
            $original['reversal_by'] = null;
            $original['reversal_comment'] = null;
            $duplicateId = DB::table('payment_transactions')->insertGetId($original);

            // 3) Ledger writes — only for after-ledger reversals
            if ($isAfterLedger) {
                $policy = DB::table('policies')->where('id', $policyId)->first(['customer_id', 'premium']);

                // Running balance: the last ledger row's balance for this policy
                $lastLedger = DB::table('policy_ledger')
                    ->where('policy_id', $policyId)
                    ->whereNull('deleted_at')
                    ->orderByDesc('id')
                    ->first(['balance']);
                $prevBalance = (float) ($lastLedger->balance ?? 0);
                $amount = (float) $txn->amount;
                // Reverse Payment is a debit (money "un-received"), so balance moves up
                $newBalance = $prevBalance + $amount;

                DB::table('policy_ledger')->insert([
                    'customer_id'     => $policy->customer_id ?? null,
                    'policy_id'       => $policyId,
                    'accounting_date' => $reversalDate,
                    'trans_type'      => 'Reverse Payment',
                    'trans_ref'       => strtoupper((string) $txn->referenceNumber),
                    'orig_trans'      => strtoupper((string) $txn->referenceNumber),
                    'system_date'     => $now->format('Y-m-d'),
                    'eff_date'        => $reversalDate,
                    'premium'         => $policy->premium ?? null,
                    'status'          => 'Reversed',
                    'odoo_status'     => 'pending',
                    'debit'           => number_format($amount, 2, '.', ''),
                    'credit'          => null,
                    'balance'         => number_format($newBalance, 2, '.', ''),
                    'description'    => 'Reversal: ' . $validated['comments'],
                    'action_by'       => $user->id,
                    'action_at'       => $now,
                    'created_at'      => $now,
                ]);

                // Mark the original Payment ledger row(s) as Reversed too, so age
                // analyst / aging reports correctly exclude them.
                DB::table('policy_ledger')
                    ->where('policy_id', $policyId)
                    ->where('trans_type', 'Payment')
                    ->where('trans_ref', (string) $txn->referenceNumber)
                    ->whereNull('deleted_at')
                    ->update([
                        'status'    => 'Reversed',
                        'action_by' => $user->id,
                        'action_at' => $now,
                    ]);
            }

            try {
                $policyModel = \AlphaDirect\Policy::find($policyId);
                if ($policyModel) {
                    activity('Transaction Reversed')
                        ->performedOn($policyModel)
                        ->causedBy($user)
                        ->log('Transaction reversed: Reference Number - ' . $txn->referenceNumber
                            . ', Amount - ' . number_format((float) $txn->amount, 2, '.', '')
                            . ', Reversal Date - ' . $reversalDate
                            . ', Mode - ' . ($isAfterLedger ? 'after-ledger' : 'before-ledger')
                            . ', Comments - ' . $validated['comments']);
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('reversal activity log failed: ' . $e->getMessage());
            }

            return response()->json([
                'message' => $isAfterLedger
                    ? 'Transaction reversed (after-ledger): ledger entries adjusted.'
                    : 'Transaction reversed (before-ledger): no ledger impact.',
                'data' => [
                    'originalTransactionId'  => $txnId,
                    'duplicateTransactionId' => $duplicateId,
                    'mode'                   => $isAfterLedger ? 'after' : 'before',
                ],
            ]);
        });
    }

    /**
     * What identifies a ledger refund across the live and archive databases:
     * reference + accounting date + amount. Ids cannot be used — the archive is
     * a copy of the same table in another database, so its ids run in the same
     * range and mean something different.
     */
    private static function refundSignature(object $l): string
    {
        return strtoupper(trim((string) ($l->trans_ref ?? '')))
            . '|' . (string) $l->accounting_date
            . '|' . number_format((float) ($l->debit ?? 0), 2, '.', '');
    }

    // ──────────────────────────────────────────────────────────────
    // POST /api/v1/policies/{policyId}/transaction-logs/refund
    //
    // Records a manual cash refund against the policy (V8 RefundMoney
    // behaviour — NOT a DPO card refund). Inserts a new payment_transactions
    // row with is_refund=1, paymentMethod='Cash', status='Success', and
    // a matching policy_ledger row with trans_type='Refund', status='Paid'.
    //
    // No external API is called. This records that money was paid back
    // to the customer through some manual channel (cash, EFT, etc.).
    //
    // Policy must be active (status != 0) — matches V8's gate.
    // ──────────────────────────────────────────────────────────────
    public function refundTransaction(Request $request, int $policyId): JsonResponse
    {
        $validated = $request->validate([
            'reference_number' => 'required|string|max:30',
            'date_of_refund'   => 'required|date_format:Y-m-d|before_or_equal:today',
            'amount'           => 'required|numeric|min:1',
            'reason'           => 'required|string|max:300',
            'refunded_by'      => 'required|string|max:60',
        ]);

        $user = auth()->user();
        if (!$user) return response()->json(['message' => 'Unauthenticated.'], 401);

        // Refund is sensitive money-movement — same lock as reversal
        // (Super Admin OR payment_reversal). Previously ungated entirely.
        if (!\AlphaDirect\Services\AuthGate::canReversePayment($user)) {
            return response()->json(['message' => 'Permission denied.', 'required' => 'payment_reversal'], 403);
        }

        $policy = DB::table('policies')->where('id', $policyId)->first(['id', 'policyNumber', 'customer_id', 'status', 'premium']);
        if (!$policy) return response()->json(['message' => 'Policy not found.'], 404);
        if ((int) $policy->status === 0) {
            return response()->json(['message' => 'Deactivated policy is not allowed to refund.'], 422);
        }

        // Reject duplicate refund reference for same policy
        $dupRef = DB::table('payment_transactions')
            ->where('policyNumber', $policy->policyNumber)
            ->where('referenceNumber', $validated['reference_number'])
            ->where('is_refund', 1)
            ->whereNull('deleted_at')
            ->exists();
        if ($dupRef) {
            return response()->json([
                'message' => 'A refund with reference "' . $validated['reference_number'] . '" already exists for this policy.',
            ], 409);
        }

        return DB::transaction(function () use ($policy, $validated, $user) {
            $now = Carbon::now();
            $amount = (float) $validated['amount'];

            // 1) Insert refund payment_transactions row
            $refundId = DB::table('payment_transactions')->insertGetId([
                'policy_id'       => $policy->id,
                'policyNumber'    => $policy->policyNumber,
                'referenceNumber' => $validated['reference_number'],
                'amount'          => $amount,
                'status'          => 'Success',
                'paymentMethod'   => 'Cash',
                'paymentDate'     => $validated['date_of_refund'],
                'new_payment_date'=> $validated['date_of_refund'],
                'is_refund'       => 1,
                'is_ledger'       => 1,
                'paymentFrequency'=> 1,
                'reason'          => $validated['reason'],
                'refunded_by'     => $user->id,
                'cashRecipient'   => $validated['refunded_by'],
                'paymentLoggedBy' => $user->id,
                'created_at'      => $now,
                'updated_at'      => $now,
            ]);

            // 2) Insert policy_ledger row
            $lastLedger = DB::table('policy_ledger')
                ->where('policy_id', $policy->id)
                ->whereNull('deleted_at')
                ->orderByDesc('id')
                ->first(['balance']);
            $prevBalance = (float) ($lastLedger->balance ?? 0);
            // V8 logic: if previous balance negative, balance = -(amount + abs(prev)); else prev - amount
            $newBalance = $prevBalance < 0
                ? -1 * ($amount + abs($prevBalance))
                : $prevBalance - $amount;

            DB::table('policy_ledger')->insert([
                'customer_id'     => $policy->customer_id,
                'policy_id'       => $policy->id,
                'accounting_date' => $validated['date_of_refund'],
                'trans_type'      => 'Refund',
                'trans_ref'       => strtoupper($validated['reference_number']),
                'orig_trans'      => strtoupper($validated['reference_number']),
                'system_date'     => $now->format('Y-m-d'),
                'eff_date'        => $validated['date_of_refund'],
                'premium'         => $policy->premium,
                'status'          => 'Paid',
                'odoo_status'     => 'pending',
                'debit'           => number_format($amount, 2, '.', ''),
                'credit'          => null,
                'balance'         => number_format($newBalance, 2, '.', ''),
                'description'    => 'Refund: ' . $validated['reason'],
                'action_by'       => $user->id,
                'action_at'       => $now,
                'created_at'      => $now,
            ]);

            try {
                $policyModel = \AlphaDirect\Policy::find($policy->id);
                if ($policyModel) {
                    activity('Made refund')
                        ->performedOn($policyModel)
                        ->causedBy($user)
                        ->log('Refund made of amount P ' . number_format($amount, 2, '.', '') . ' (ref ' . $validated['reference_number'] . ')');
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('refund activity log failed: ' . $e->getMessage());
            }

            return response()->json([
                'message' => 'Refund recorded successfully.',
                'data' => [
                    'refundTransactionId' => $refundId,
                    'policyNumber'        => $policy->policyNumber,
                    'amount'              => $amount,
                    'referenceNumber'     => $validated['reference_number'],
                    'dateOfRefund'        => $validated['date_of_refund'],
                ],
            ], 201);
        });
    }

    // ──────────────────────────────────────────────────────────────
    // GET /api/v1/policies/{policyId}/transaction-logs/{txnId}/proof
    //
    // Returns a short-lived signed URL for the payment proof file when
    // the underlying disk supports it; otherwise returns the public URL
    // (local 'public' disk in dev). Either way the FE can <a href=…>
    // directly to download.
    // ──────────────────────────────────────────────────────────────
    public function transactionProofUrl(Request $request, int $policyId, int $txnId): JsonResponse
    {
        $txn = DB::table('payment_transactions')
            ->where('id', $txnId)
            ->where('policy_id', $policyId)
            ->whereNull('deleted_at')
            ->first(['payment_proof_link']);
        if (!$txn || !$txn->payment_proof_link) {
            return response()->json(['message' => 'No proof available.'], 404);
        }

        $path = $txn->payment_proof_link;
        // Try S3 signed URL first, fall back to public disk.
        try {
            if (Storage::disk('s3')->exists($path)) {
                $url = Storage::disk('s3')->temporaryUrl($path, now()->addMinutes(15));
                return response()->json(['url' => $url, 'disk' => 's3']);
            }
        } catch (\Throwable $e) {
            // S3 not configured or temporaryUrl unsupported; fall through.
        }
        if (Storage::disk('public')->exists($path)) {
            return response()->json(['url' => Storage::disk('public')->url($path), 'disk' => 'public']);
        }
        return response()->json(['message' => 'Proof file missing on disk.'], 404);
    }

    // ──────────────────────────────────────────────────────────────
    // DPO online payment flow (mirrors legacy DpoPaymentController)
    // ──────────────────────────────────────────────────────────────

    /**
     * GET /payments/online/find-policy?search=MIS2026210548
     * Mirrors legacy DpoPaymentController::findPolicyForOnlinePayment:51
     * Resolves a policy for the customer-facing online-payment flow.
     */
    public function findPolicyForOnlinePayment(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', $request->query('searchValue', '')));
        if ($search === '') return response()->json(['error' => 'search required'], 422);

        $policy = DB::table('policies')
            ->where('policyNumber', $search)
            ->orWhere('id', ctype_digit($search) ? (int) $search : 0)
            ->first(['id', 'policyNumber', 'customer_id', 'product_id', 'status', 'premium', 'first_premium', 'billing']);
        if (!$policy) return response()->json(['error' => 'Policy not found.'], 404);

        $customer = DB::table('customer')->where('id', $policy->customer_id)
            ->first(['id', 'firstName', 'lastName', 'email', 'mobileNumber']);
        $banking  = DB::table('customer_banking')->where('customer_id', $policy->customer_id)
            ->orderByDesc('id')->first();
        $nextSch  = DB::table('scheduled_transactions')
            ->where('policy_number', $policy->policyNumber)
            ->whereIn('status', [0, 3])
            ->orderBy('billing_date')->first();

        return response()->json([
            'data' => [
                'policy'   => $policy,
                'customer' => $customer,
                'banking'  => $banking,
                'next_due' => $nextSch,
            ],
        ]);
    }

    /**
     * POST /payments/online/save
     * Mirrors legacy saveOnlinePayment:863. Persists the DPO response,
     * marks the scheduled row as done, updates policy.status=1 on first payment.
     */
    public function saveOnlinePayment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'policy_number'     => 'required|string',
            'amount'            => 'required|numeric|min:0',
            'transaction_token' => 'required|string|max:100',
            'transaction_ref'   => 'nullable|string|max:100',
            'status'            => 'nullable|string|max:30',
            'billing_start'     => 'nullable|date',
            'payment_method'    => 'nullable|string|max:30',
            'lead_source'       => 'nullable|string|max:50',
        ]);
        $policy = DB::table('policies')->where('policyNumber', $validated['policy_number'])->first();
        if (!$policy) return response()->json(['error' => 'Policy not found.'], 404);

        DB::beginTransaction();
        try {
            $txId = DB::table('payment_transactions')->insertGetId([
                'policy_id'       => $policy->id,
                'policyNumber'    => $policy->policyNumber,
                'amount'          => $validated['amount'],
                'status'          => strtoupper($validated['status'] ?? 'SUCCESS'),
                'paymentMethod'   => $validated['payment_method'] ?? 'DPO',
                'paymentDate'     => now(),
                'new_payment_date'=> now(),
                'referenceNumber' => $validated['transaction_token'],
                'note'            => $validated['transaction_ref'] ?? null,
                'send_sms_email'  => 1,
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);

            // Mark first pending scheduled_transaction as succeeded
            DB::table('scheduled_transactions')
                ->where('policy_number', $policy->policyNumber)
                ->whereIn('status', [0, 3])
                ->orderBy('billing_date')
                ->limit(1)
                ->update(['status' => 2, 'updated_at' => now()]);

            // Activate policy on first successful payment
            if ((int) $policy->status !== 1) {
                DB::table('policies')->where('id', $policy->id)->update([
                    'status'      => 1,
                    'updated_at'  => now(),
                ]);
            }

            DB::commit();

            // Audit trail — kept outside the DB transaction so a logging failure
            // can't roll back a successful payment.
            try {
                $policyModel = \AlphaDirect\Policy::find($policy->id);
                if ($policyModel) {
                    activity('Online payment recorded')
                        ->performedOn($policyModel)
                        ->causedBy(auth()->user())
                        ->log('Online payment of P ' . number_format((float) $validated['amount'], 2, '.', '')
                            . ' via ' . ($validated['payment_method'] ?? 'DPO')
                            . ' (token ' . $validated['transaction_token']
                            . ($validated['transaction_ref'] ?? null ? ', ref ' . $validated['transaction_ref'] : '')
                            . ')');
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('online payment activity log failed: ' . $e->getMessage());
            }

            return response()->json(['message' => 'Payment recorded.', 'id' => $txId, 'policy_id' => $policy->id], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /payments/online/verify
     * Mirrors legacy verifyPayment:1254. Queries local record for a token;
     * returns status + amount. Does not call DPO external API (that stays
     * on legacy pending credentials migration).
     */
    public function verifyPayment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token'         => 'required|string|max:100',
            'policy_number' => 'nullable|string',
        ]);
        $tx = DB::table('payment_transactions')
            ->where('referenceNumber', $validated['token'])
            ->when($validated['policy_number'] ?? null, fn($q, $n) => $q->where('policyNumber', $n))
            ->orderByDesc('id')->first();
        if (!$tx) return response()->json(['status' => 'unknown', 'verified' => false]);
        return response()->json([
            'status'   => $tx->status,
            'verified' => in_array(strtoupper($tx->status ?? ''), ['SUCCESS', 'COMPLETE', 'SUCCESSFUL']),
            'amount'   => $tx->amount,
            'date'     => $tx->paymentDate,
        ]);
    }

    /**
     * POST /payments/online/charge-recurrent
     * Iterates pending scheduled_transactions and dispatches the legacy
     * ChargeTokenRecurrentEvent (if the event class is available). Otherwise
     * marks them as pending-retry with a message.
     */
    public function chargeTokenRecurrent(Request $request): JsonResponse
    {
        $limit = (int) $request->input('limit', 10);
        $due = DB::table('scheduled_transactions')
            ->whereIn('status', [0, 3])
            ->whereDate('billing_date', '<=', now()->toDateString())
            ->orderBy('billing_date')
            ->limit($limit)
            ->get();

        $dispatched = 0; $skipped = 0;
        foreach ($due as $row) {
            if (class_exists('\\AlphaDirect\\Events\\ChargeTokenRecurrentEvent')) {
                try {
                    event(new \AlphaDirect\Events\ChargeTokenRecurrentEvent($row));
                    $dispatched++;
                } catch (\Throwable $e) {
                    \Log::warning("ChargeTokenRecurrent failed for sch #{$row->id}: " . $e->getMessage());
                    $skipped++;
                }
            } else {
                $skipped++;
            }
        }
        return response()->json([
            'message'    => "Processed {$due->count()} due rows",
            'dispatched' => $dispatched,
            'skipped'    => $skipped,
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────

    /**
     * Build a standard pagination meta array from a LengthAwarePaginator.
     */
    private function paginationMeta($paginator): array
    {
        return [
            'total'        => $paginator->total(),
            'per_page'     => $paginator->perPage(),
            'current_page' => $paginator->currentPage(),
            'last_page'    => $paginator->lastPage(),
            'from'         => $paginator->firstItem(),
            'to'           => $paginator->lastItem(),
        ];
    }
}
