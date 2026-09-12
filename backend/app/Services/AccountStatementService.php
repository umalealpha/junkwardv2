<?php

namespace AlphaDirect\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Builds the data behind the Account Statement — the filtered/merged/sorted
 * ledger rows and the running Closing Balance.
 *
 * This started as a deliberate, self-contained COPY of the ledger logic in
 * PolicyCreateController::generateAccountStatementPdf (which renders the
 * Export Account Statement PDF), so the on-screen Account View and the Balance
 * Owing widget could show the SAME numbers as the PDF without touching it.
 *
 * The copy drifted and cost us a bug (refunds missing from the exported PDF),
 * so the two reference sets the filtering turns on — validPaymentRefs() and
 * refundExcludedRefs() — now live here and the PDF endpoint calls them. The
 * remaining row/archive queries are still duplicated: change both together.
 */
class AccountStatementService
{
    /**
     * payment_transactions.status casing for a successful payment is not
     * consistent across gateways — VCS/Flutterwave/DPO write 'SUCCESS',
     * Orange Money predominantly writes 'Success', and legacy rows use the
     * short code 'S' (same set PolicyLedgerDaily's ledger-posting cron
     * already accepts). An exact `where('status', 'SUCCESS')` silently
     * dropped any payment recorded with a different casing from the
     * statement and the Total Dues calculation, even though it was a real,
     * successful payment visible in Transaction Logs.
     */
    public const SUCCESS_STATUSES = ['Success', 'SUCCESS', 'success', 'S'];

    /**
     * Product ids that render the expanded DomCom account statement. Mirrors
     * the $isDomCom literal in PolicyCreateController::generateAccountStatementPdf
     * and PolicyController::ledger — anything NOT in this list is the MIS family
     * (legacy retail: Accident Death, TP Car, Legal, Mobile/Electronic, Hospital
     * Cashback) and renders the admin.notes.account_statement blade.
     */
    public const DOMCOM_PRODUCT_IDS = [7, 8, 16, 17, 18, 19, 20, 21, 22, 23];

    /**
     * Every raw policy_ledger.trans_type spelling that belongs on a statement.
     *
     * 'Invoices' (plural) is NOT a typo in this list — missingPayment and
     * PolicyLedgerDailySonaliM both post real invoices under that spelling
     * (missingPayment :178/:266, PolicyLedgerDailySonaliM :354/:454). A
     * whereIn on 'Invoice' alone silently dropped every one of them, so a
     * policy invoiced by those commands showed no invoices at all and its
     * balance read as fully paid.
     *
     * Case variants ('invoice', used by GenerateYearlyInvoiceCountReport's
     * query) need no entry: the ledger's collation is *_general_ci, so the
     * comparison is already case-insensitive.
     */
    public const STATEMENT_TRANS_TYPES = [
        'Invoice',
        'Invoices',
        'Payment',
        'Credit Note',
        'Refund',
        'Reverse Payment',
    ];

    /** Raw spellings that are invoice debits. */
    public const INVOICE_TRANS_TYPES = ['Invoice', 'Invoices'];

    /**
     * The canonical trans_type for a row, so every consumer branches on one
     * spelling instead of repeating the alias list. Unknown types come back
     * unchanged and contribute nothing to a balance (see signedAmount()).
     */
    public static function canonicalTransType($transType): string
    {
        switch (strtolower(trim((string) $transType))) {
            case 'invoice':
            case 'invoices':
                return 'Invoice';
            case 'payment':
            case 'payments':
                return 'Payment';
            case 'credit note':
            case 'creditnote':
                return 'Credit Note';
            case 'refund':
                return 'Refund';
            case 'reverse payment':
                return 'Reverse Payment';
            default:
                return (string) $transType;
        }
    }

    /** Connection holding the archived ledger (config/database.php 'mysql3' → graphite_archive). */
    public const ARCHIVE_CONNECTION = 'mysql3';

    /** Archived ledger table name ON that connection — NOT 'policy_ledger_archive'. */
    public const ARCHIVE_TABLE = 'policy_ledger';

    /**
     * Which statement tier a policy belongs to. Defaults to MIS (false) when the
     * product can't be resolved — the policies table is absent in the isolated
     * sqlite test harnesses, and an unknown product has never been DomCom.
     */
    public static function isDomCom(int $policyId): bool
    {
        if (!Schema::hasTable('policies')) {
            return false;
        }

        $productId = DB::table('policies')->where('id', $policyId)->value('product_id');

        return $productId !== null && in_array((int) $productId, self::DOMCOM_PRODUCT_IDS, true);
    }

    /**
     * CR numbers on this policy whose credited INVOICE no longer exists: the
     * invoice's policy_ledger row was discarded (soft-deleted) by an un-issue,
     * a post-cancel cleanup or an action delete, or is gone from the table
     * altogether.
     *
     * A credit note is nothing but the reversal of one invoice. Discarding the
     * invoice takes its debit off the statement but left the note's own
     * 'Credit Note' debit standing, so the note kept subtracting from the
     * Closing Balance / Total Dues with nothing left to reverse, and still
     * printed on the Account View, the statement PDF, the Credit Notes tab and
     * the Sub Ledger. Every reader now drops these notes. Nothing is deleted,
     * so a note reappears by itself if its invoice is ever restored.
     *
     * Liveness is tested on credit_note.invoice_id first (what the V2 flow
     * stamps) and on invoice_no as a fallback (notes raised on the legacy
     * /admin screen recorded only the number) — so a re-issue that re-creates
     * the same invoice number re-adopts its note instead of orphaning it.
     * policy_ledger_archive counts as live history, the same way
     * InvoiceCreditNoteService::resolve() reads it. A note carrying NEITHER key
     * cannot be judged and is kept.
     *
     * @return array<int,string> CR numbers to exclude (empty = nothing to hide)
     */
    public static function orphanCreditNoteRefs(int $policyId): array
    {
        // Deliberately NOT memoised: an invoice can be discarded inside the same
        // process that later reads the statement (queue workers, the un-issue
        // request itself), and a cached answer would be stale. The lookup is
        // three indexed reads.
        $refs = [];
        try {
            // credit_note is a legacy table with no migration of its own — a
            // missing table or column must never take a statement down.
            if (!Schema::hasTable('credit_note')) {
                return [];
            }

            $notes = DB::table('credit_note')
                ->where('policy_id', $policyId)
                ->whereNotNull('credit_note_no')
                ->where('credit_note_no', '!=', '')
                ->get(['credit_note_no', 'invoice_id', 'invoice_no']);

            if ($notes->isEmpty()) {
                return [];
            }

            $ids = $notes->pluck('invoice_id')->filter()
                ->map(fn($v) => (int) $v)->unique()->values()->all();
            $nos = $notes->pluck('invoice_no')
                ->filter(fn($v) => $v !== null && $v !== '')
                ->map(fn($v) => (string) $v)->unique()->values()->all();

            $liveIds = [];
            $liveNos = [];
            if (!empty($ids)) {
                $liveIds = DB::table('policy_ledger')->whereIn('id', $ids)
                    ->whereNull('deleted_at')->pluck('id')->map(fn($v) => (int) $v)->all();
            }
            if (!empty($nos)) {
                $liveNos = DB::table('policy_ledger')->where('policy_id', $policyId)
                    ->whereIn('invoice_no', $nos)->whereNull('deleted_at')
                    ->pluck('invoice_no')->map(fn($v) => (string) $v)->all();
            }
            if (Schema::hasTable('policy_ledger_archive')) {
                if (!empty($ids)) {
                    $liveIds = array_merge($liveIds, DB::table('policy_ledger_archive')
                        ->whereIn('id', $ids)->pluck('id')->map(fn($v) => (int) $v)->all());
                }
                if (!empty($nos)) {
                    $liveNos = array_merge($liveNos, DB::table('policy_ledger_archive')
                        ->where('policy_id', $policyId)->whereIn('invoice_no', $nos)
                        ->pluck('invoice_no')->map(fn($v) => (string) $v)->all());
                }
            }

            foreach ($notes as $note) {
                $invoiceId = empty($note->invoice_id) ? null : (int) $note->invoice_id;
                $invoiceNo = ($note->invoice_no === null || $note->invoice_no === '')
                    ? null
                    : (string) $note->invoice_no;

                if ($invoiceId === null && $invoiceNo === null) {
                    continue;
                }

                $live = ($invoiceId !== null && in_array($invoiceId, $liveIds, true))
                    || ($invoiceNo !== null && in_array($invoiceNo, $liveNos, true));

                if (!$live) {
                    $refs[] = (string) $note->credit_note_no;
                }
            }
        } catch (\Throwable $e) {
            // Fail OPEN: on any lookup error nothing is hidden, which is the
            // pre-existing behaviour.
            Log::warning('orphan credit note lookup failed: ' . $e->getMessage());
            $refs = [];
        }

        return array_values(array_unique($refs));
    }

    /**
     * Reference numbers of the payments that count as MONEY RECEIVED for this
     * policy — the same set PaymentController::policyTransactionLogs totals into
     * the Transaction Logs tab's "Successful transactions" / "Total Balance"
     * (reversed, refunded, failed, cancelled and soft-deleted payments excluded).
     *
     * Returns NULL when the policy has no payment_transactions rows at all. That
     * means "there is no basis to filter" — callers must leave their rows
     * untouched rather than emptying the view for legacy/migrated policies whose
     * receipts only ever existed in policy_ledger.
     *
     * Matched on policy_id OR policyNumber on purpose: the statement/ledger code
     * keys payments by policy_id while the Transaction Logs tab keys them by
     * policyNumber, and real rows exist with only one of the two populated.
     * Using both makes this a superset, so it can never drop a genuine payment.
     */
    public static function receivedPaymentRefs(int $policyId, ?string $policyNumber): ?array
    {
        $scope = function ($q) use ($policyId, $policyNumber) {
            $q->where('policy_id', $policyId);
            if ($policyNumber) {
                $q->orWhere('policyNumber', $policyNumber);
            }
        };

        $hasPayments = DB::table('payment_transactions')
            ->where($scope)
            ->whereNull('deleted_at')
            ->exists();
        if (!$hasPayments) {
            return null;
        }

        // A refunded payment is money given back, so its original receipt is
        // excluded — same rule as policyTransactionLogs' $refundedRefs.
        $refundedRefs = DB::table('payment_transactions')
            ->where($scope)
            ->where('is_refund', 1)
            ->whereNull('deleted_at')
            ->pluck('referenceNumber')->filter()->values()->all();

        $q = DB::table('payment_transactions')
            ->where($scope)
            ->whereIn('status', self::SUCCESS_STATUSES)
            ->whereNull('deleted_at')
            ->where(function ($w) {
                $w->whereNull('CompanyRef')->orWhere('CompanyRef', '!=', 'Reversed');
            })
            ->where(function ($w) {
                $w->whereNull('is_reverse')->orWhere('is_reverse', '!=', 1);
            })
            ->whereNull('reveral_transaction_id');

        if (!empty($refundedRefs)) {
            $q->whereNotIn('referenceNumber', $refundedRefs);
        }

        return $q->pluck('referenceNumber')->filter()->values()->all();
    }

    /**
     * References a Refund row must NOT be booked against — payment references
     * that exist on this policy but failed the "valid payment" test (reversed,
     * soft-deleted, the P1 gateway test charge, a non-success status).
     *
     * The Refund branch used to be `whereIn('trans_ref', $validPayments)`, i.e.
     * a refund only survived when its reference was itself a valid payment
     * reference. Real refunds are routinely booked with an empty trans_ref, or
     * with an operational reference of their own (backlog imports write things
     * like 'REFUND-BACKLOG-2026'), and every one of those was silently dropped
     * from the statement dataset — the Refund printed in the on-screen Account
     * View (which filters refunds on trans_type alone) but vanished from the
     * exported PDF, and its debit never reached the Closing Balance.
     *
     * Inverting the test keeps the original intent — don't print a refund that
     * hangs off a payment we already threw away — without requiring a refund to
     * name a payment at all. Reported on policy 79959 (product 4): a P 49.00
     * refund with trans_ref '' never reached the PDF.
     *
     * @return array<int,string>
     */
    public static function refundExcludedRefs(int $policyId, Collection|array|null $validPayments = null): array
    {
        $valid = $validPayments === null
            ? self::validPaymentRefs($policyId)
            : collect($validPayments);

        return DB::table('payment_transactions')
            ->where('policy_id', $policyId)
            ->pluck('referenceNumber')
            ->filter(fn($ref) => $ref !== null && $ref !== '')
            ->reject(fn($ref) => $valid->contains($ref))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * The payment references that count as valid receipts on the statement.
     * Extracted so rows() and PolicyCreateController::generateAccountStatementPdf
     * cannot drift apart on the definition.
     */
    public static function validPaymentRefs(int $policyId): Collection
    {
        return DB::table('payment_transactions')
            ->where('policy_id', $policyId)
            ->whereNull('deleted_at')
            ->whereIn('status', self::SUCCESS_STATUSES)
            // CompanyRef is an overloaded gateway-reference field (DPO's
            // TransactionBookRef, Ngenius's reference, or — on plenty of real
            // rows — simply the policy's own number) and only ever means
            // "reversed" when it is literally the string 'Reversed'.
            ->where(function ($q) {
                $q->whereNull('CompanyRef')->orWhere('CompanyRef', '!=', 'Reversed');
            })
            ->where('amount', '!=', 1)
            ->whereNull('reveral_transaction_id')
            ->pluck('referenceNumber');
    }

    /**
     * A query builder over the ARCHIVED ledger, or null when this environment
     * has no archive reachable.
     *
     * The archive is not a `policy_ledger_archive` table on the primary
     * connection — it never has been. It is the table `policy_ledger` inside
     * the separate `graphite_archive` database, reached on the `mysql3`
     * connection (see config/database.php and AlphaDirect\Models\LedgerArchive,
     * which sets exactly that connection/table pair; the reporting commands
     * address it as `graphite_archive.policy_ledger` in raw SQL).
     *
     * Every V2 read of the archive guarded itself with
     * `Schema::hasTable('policy_ledger_archive')` on the DEFAULT connection.
     * That table does not exist, so the guard was false in every environment
     * and the archive merge was dead code — silently. Archived rows, refunds
     * among them, never reached the Account View, the Account Statement PDF
     * or the Transaction Logs tab, which is why refunds still went missing on
     * MIS policies after the 4114e720 fix: that fix only reached the live
     * `policy_ledger`.
     *
     * Returns null (rather than throwing) when the connection is not
     * configured or unreachable, so a missing archive degrades to "live rows
     * only" exactly as the old guard intended.
     */
    public static function archiveQuery(): ?\Illuminate\Database\Query\Builder
    {
        try {
            $conn = DB::connection(self::ARCHIVE_CONNECTION);
        } catch (\Throwable $e) {
            self::$archiveAvailable[self::ARCHIVE_CONNECTION] = false;
            Log::warning('archived ledger connection unavailable: ' . $e->getMessage());

            return null;
        }

        // Resolved once per database: a statement render calls this from the
        // rows() query AND the transaction-logs merge, and on an environment
        // with no archive each attempt costs a failed connection plus a log
        // line. Keyed on the database name rather than a bare flag so that
        // re-pointing the connection (the test harness does) re-checks.
        $key = (string) $conn->getDatabaseName();

        if (!array_key_exists($key, self::$archiveAvailable)) {
            try {
                self::$archiveAvailable[$key] = $conn->getSchemaBuilder()->hasTable(self::ARCHIVE_TABLE);
            } catch (\Throwable $e) {
                self::$archiveAvailable[$key] = false;
                Log::warning('archived ledger unavailable on connection '
                    . self::ARCHIVE_CONNECTION . ': ' . $e->getMessage());
            }
        }

        return self::$archiveAvailable[$key] ? $conn->table(self::ARCHIVE_TABLE) : null;
    }

    /** Per-database memo for archiveQuery(); see the comment there. */
    private static array $archiveAvailable = [];

    /** Drops the archiveQuery() memo. For test harnesses that re-point mysql3. */
    public static function forgetArchiveAvailability(): void
    {
        self::$archiveAvailable = [];
    }

    /**
     * Filtered + archive-merged + chronologically-sorted ledger rows — the
     * exact dataset the statement renders (reversed / refunded / test payments
     * excluded).
     *
     * @param bool $refundsRaiseBalance  DEPRECATED no-op, kept so the existing
     *   call sites keep working unchanged. Refunds now raise the balance via
     *   their own debit on EVERY tier (see closingBalance), which means the
     *   original Payment must ALWAYS stay in the row set — excluding it as well
     *   would move the balance twice for a refund booked under the same
     *   reference as its payment.
     */
    public static function rows(int $policyId, $actionId = null, bool $refundsRaiseBalance = false): Collection
    {
        // whereNull('CompanyRef') used to exclude any payment where the gateway
        // happened to populate that field at all, dropping genuine successful
        // payments from the statement. Matches PaymentController::
        // policyTransactionLogs' successQ, which already gets this right.
        $validPayments = self::validPaymentRefs($policyId);

        // MIS ONLY: refunds are filtered by EXCLUSION, not inclusion — see
        // refundExcludedRefs(). DomCom deliberately keeps the original inclusion
        // test; scoping the fix to the MIS family is what keeps the Total Dues
        // shift off the DomCom book. Revisit only with finance sign-off.
        $isDomCom          = self::isDomCom($policyId);
        $refundExcludedRefs = $isDomCom ? [] : self::refundExcludedRefs($policyId, $validPayments);

        // Refunds raise the balance via their OWN debit on every tier now, so a
        // refund's trans_ref must never exclude the original Payment: doing both
        // would remove the payment credit AND add the refund debit, moving the
        // balance by 2× for a refund booked under the same reference as its
        // payment. Reversal exclusions ($reversedRefs / $reversalTransRefs) are
        // unaffected — they always apply and keep reversals correct.
        $refundedTransRefs = [];

        $reversedRefs = DB::table('payment_transactions')
            ->where('policy_id', $policyId)
            ->where(function ($q) {
                $q->where('status', 'Reversed')->orWhereNotNull('reveral_transaction_id');
            })
            ->pluck('referenceNumber')->all();

        $reversalTransRefs = DB::table('payment_transactions')
            ->where('policy_id', $policyId)
            ->whereNotNull('reveral_transaction_id')
            ->pluck('reveral_transaction_id')->all();

        $allExcludedRefs = array_unique(array_merge($reversedRefs, $reversalTransRefs, $refundedTransRefs));

        // Credit notes left behind by a discarded invoice are not part of the
        // statement at all — see orphanCreditNoteRefs().
        $orphanCreditNotes = self::orphanCreditNoteRefs($policyId);

        $q = DB::table('policy_ledger')->where('policy_id', $policyId)
            ->whereIn('trans_type', self::STATEMENT_TRANS_TYPES)
            ->whereNull('deleted_at')
            // status='Reversed' is excluded for EVERY type, not just Payment.
            // The reversal writer stamps it on both the original Payment row and
            // the 'Reverse Payment' row it inserts, so a reversal pair nets to
            // zero by exclusion — which is what keeps Reverse Payment's +debit
            // from double-counting (see signedAmount()).
            ->where(function ($w) {
                $w->whereNull('status')->orWhere('status', '!=', 'Reversed');
            })
            ->where(function ($outer) use ($validPayments, $allExcludedRefs, $refundExcludedRefs, $isDomCom) {
                $outer->whereIn('trans_type', array_merge(self::INVOICE_TRANS_TYPES, ['Credit Note']))
                    ->orWhere(function ($q) use ($validPayments, $allExcludedRefs) {
                        $q->where('trans_type', 'Payment')
                          ->whereIn('trans_ref', $validPayments)
                          ->where('status', '!=', 'Reversed')
                          ->whereNotIn('trans_ref', $allExcludedRefs);
                    })
                    ->orWhere(function ($q) use ($validPayments, $refundExcludedRefs, $isDomCom) {
                        $q->where('trans_type', 'Refund');

                        if ($isDomCom) {
                            // Unchanged: a DomCom refund still has to name a
                            // valid payment to print.
                            $q->whereIn('trans_ref', $validPayments);
                            return;
                        }

                        // MIS: a Refund is an accounting entry in its own right
                        // (like a Credit Note) and does not have to name a
                        // payment. Only a refund booked against a payment we
                        // already discarded is dropped. NULL/'' must be spelled
                        // out: in SQL `NULL NOT IN (...)` is NULL, i.e. false,
                        // which is exactly how un-referenced refunds went missing.
                        $q->where(function ($w) use ($refundExcludedRefs) {
                            $w->whereNull('trans_ref')
                              ->orWhere('trans_ref', '')
                              ->orWhereNotIn('trans_ref', $refundExcludedRefs);
                        });
                    })
                    // A 'Reverse Payment' is an accounting entry in its own
                    // right and does not have to name a valid payment. Without
                    // this branch the type sat in STATEMENT_TRANS_TYPES but was
                    // silently dropped by the branch list above, so its +debit
                    // could never reach the balance on any policy.
                    ->orWhere('trans_type', 'Reverse Payment');
            });
        // trans_ref carries the CR number on a 'Credit Note' row. NULL must be
        // spelled out: in SQL `NULL NOT IN (...)` is NULL, i.e. false, which
        // would drop every row that has no reference.
        if (!empty($orphanCreditNotes)) {
            $q->where(function ($w) use ($orphanCreditNotes) {
                $w->where('trans_type', '!=', 'Credit Note')
                  ->orWhereNull('trans_ref')
                  ->orWhereNotIn('trans_ref', $orphanCreditNotes);
            });
        }
        if ($actionId) {
            $q->where('action_id', $actionId);
        }
        $ledger = $q->orderBy('accounting_date', 'asc')->get();

        // The archive is optional — some envs have no archive connection.
        // It lives on a SEPARATE database (graphite_archive.policy_ledger),
        // not as a 'policy_ledger_archive' table here — see archiveQuery().
        $archive = collect();
        if ($archiveQ = self::archiveQuery()) {
            try {
                $archive = $archiveQ->where('policy_id', $policyId)
                    ->where(function ($outer) use ($allExcludedRefs) {
                        $outer->whereNotNull('invoice_file')
                            ->orWhere('trans_type', 'Refund')
                            ->orWhere(function ($q) use ($allExcludedRefs) {
                                $q->whereNotNull('credit')
                                  ->where(function ($inner) use ($allExcludedRefs) {
                                      $inner->where('trans_type', '!=', 'Payment')
                                            ->orWhereNotIn('trans_ref', $allExcludedRefs);
                                  });
                            });
                    })
                    ->when(!empty($orphanCreditNotes), fn($qq) => $qq->where(function ($w) use ($orphanCreditNotes) {
                        $w->where('trans_type', '!=', 'Credit Note')
                          ->orWhereNull('trans_ref')
                          ->orWhereNotIn('trans_ref', $orphanCreditNotes);
                    }))
                    ->orderBy('id', 'asc')->orderBy('accounting_date', 'asc')->get();
            } catch (\Throwable $e) {
                Log::warning('archived ledger read failed: ' . $e->getMessage());
            }
        }

        // Deterministic order: COALESCE(invoice_date, accounting_date,
        // system_date) ASC, then id ASC.
        //
        // The id tie-break is not cosmetic. An invoice and the payment that
        // settles it routinely share a date, and with a date-only sort those
        // rows shuffle between runs — the same statement then prints different
        // intermediate Running Balance figures each time it is exported. The
        // closing balance survives that; the audit trail does not.
        //
        // The old key also read accounting_date for non-invoice rows with no
        // further fallback, so a row carrying only system_date sorted to the
        // epoch and dragged the running balance out of sequence.
        return $archive->merge($ledger)
            ->sortBy(fn($r) => sprintf(
                '%011d|%011d',
                (int) strtotime(self::rowSortDate($r)),
                (int) ($r->id ?? 0)
            ))
            ->values();
    }

    /**
     * The date a statement row sorts on: invoice_date, else accounting_date,
     * else system_date. Empty strings count as absent — these columns are
     * nullable dates that legacy write paths sometimes fill with ''.
     */
    public static function rowSortDate($row): string
    {
        foreach (['invoice_date', 'accounting_date', 'system_date'] as $col) {
            $v = $row->{$col} ?? null;
            if ($v !== null && $v !== '' && $v !== '0000-00-00') {
                return (string) $v;
            }
        }

        return '1970-01-01';
    }

    /**
     * A ledger row's amount, resolved PER TRANSACTION TYPE rather than from the
     * debit / credit pair.
     *
     * WHY THIS EXISTS: policy_ledger.debit is not trustworthy on 'Invoice' rows.
     * On COMG2024129977 the nine invoices carry debit 1,985,434.37 against
     * invoice_amount 2,271,458.67 — a 286,024.30 shortfall — and the matching
     * 'Invoice Premium' / 'Invoice VAT' split rows repeat it (VAT of 451.23 on
     * ~1.98M of premium is self-evidently wrong). Every balance derived from
     * debit therefore under-billed the client, which is why the Closing Balance,
     * Total Dues and Balance Owing all disagreed with the ageing report: the
     * ageing procedures sum invoice_amount (see
     * cron/app/Console/Commands/Com_ageing_procedure_20251111.sql), and on the
     * invoice side that column is the canonical one.
     *
     * Source per trans_type, matching what each write path actually populates:
     *   Invoice          invoice_amount   — the invoiced figure; debit is stale
     *   Payment          invoice_amount   — LedgerPaymentTransDomCom writes the
     *                                       receipt into invoice_amount, premium
     *                                       AND credit, so all three agree and
     *                                       invoice_amount needs no debit/credit
     *   Credit Note      debit            — InvoiceCreditNoteService leaves
     *   Refund           debit              invoice_amount explicitly NULL on
     *   Reverse Payment  debit              these rows, so debit is the ONLY
     *                                       carrier; same fallback order the
     *                                       Credit Notes tab already uses
     *                                       (PolicyController::ledger :2232).
     *
     * Commas are stripped before casting: several write paths pass
     * number_format() output into these decimal columns, and "19,353.60" casts
     * to 19.0 — the same class of defect as the sub-ledger credit-note bug.
     */
    public static function rowAmount($row): float
    {
        $num = static function ($v): ?float {
            if ($v === null || $v === '') {
                return null;
            }
            return (float) str_replace(',', '', (string) $v);
        };

        $transType = self::canonicalTransType($row->trans_type ?? '');

        // Invoice: invoice_amount is authoritative. It is corroborated by
        // `premium` and `due_amount` on the same row, while debit disagrees on
        // 93 of 37,578 live DOM/COM invoice rows across 66 policies.
        if ($transType === 'Invoice') {
            return $num($row->invoice_amount ?? null)
                ?? $num($row->debit ?? null)
                ?? 0.0;
        }

        // Payment: `credit` — NOT invoice_amount. credit is populated on all
        // 40,752 live Payment rows; invoice_amount on only 22,893 of them, so
        // sourcing receipts from invoice_amount under-counts money received on
        // 44% of rows. (LedgerPaymentTransDomCom does write both, but plenty of
        // gateway-written rows carry only credit.)
        if ($transType === 'Payment') {
            return $num($row->credit ?? null)
                ?? $num($row->invoice_amount ?? null)
                ?? 0.0;
        }

        // Credit Note / Refund / Reverse Payment: debit. invoice_amount is null
        // on 145 of 157 Credit Note rows and is never populated on Refund or
        // Reverse Payment rows, so debit is the only carrier. Credit stays as a
        // fallback for a legacy row that booked the entry the other way round.
        return $num($row->debit ?? null)
            ?? $num($row->credit ?? null)
            ?? 0.0;
    }

    /**
     * The signed amount a row contributes to the running balance.
     *
     * The question asked of each type is: after this event, does the client owe
     * more or less?
     *
     *   Invoice          + premium billed
     *   Refund           + money went back, so the cover is unpaid again
     *   Reverse Payment  + a receipt was undone, so the debt it settled returns
     *   Credit Note      − premium withdrawn
     *   Payment          − money received
     *
     * A 'Reverse Payment' row therefore ADDS its debit. There is no double-count
     * risk from the reversal writer, which stamps status='Reversed' on BOTH the
     * original Payment row and the Reverse Payment row it inserts
     * (PaymentController :765-790) — and rows() drops every status='Reversed'
     * row, so a reversal pair nets to zero by exclusion rather than by sign.
     * Where a legacy Reverse Payment row survives with another status, the debit
     * correctly re-opens the debt.
     *
     * Unknown types (Invoice Premium / Invoice VAT split rows, Payment Failed,
     * Cancellation, NA) contribute nothing.
     */
    public static function signedAmount($row): float
    {
        $amount = self::rowAmount($row);

        switch (self::canonicalTransType($row->trans_type ?? '')) {
            case 'Invoice':
            case 'Refund':
            case 'Reverse Payment':
                return $amount;
            case 'Payment':
            case 'Credit Note':
                return -$amount;
            default:
                return 0.0;
        }
    }

    /**
     * The header aggregates for a statement, derived from the SAME rows the
     * statement prints — never from a separate query. That independence is what
     * let the header and the body disagree in the first place.
     *
     * @param  iterable  $rows            rows() output (or the PDF endpoint's merge)
     * @param  float     $openingBalance  running balance of everything before the period
     * @return array{opening:float, invoiced:float, paid:float, creditNotes:float, refunds:float, closing:float}
     */
    public static function headerTotals(iterable $rows, float $openingBalance = 0.0): array
    {
        $t = [
            'opening'     => round($openingBalance, 2),
            'invoiced'    => 0.0,
            'paid'        => 0.0,
            'creditNotes' => 0.0,
            'refunds'     => 0.0,
            'closing'     => 0.0,
        ];

        $running = $openingBalance;
        foreach ($rows as $row) {
            $amount = self::rowAmount($row);
            switch (self::canonicalTransType($row->trans_type ?? '')) {
                case 'Invoice':
                    $t['invoiced'] += $amount;
                    break;
                case 'Payment':
                    $t['paid'] += $amount;
                    break;
                case 'Credit Note':
                    $t['creditNotes'] += $amount;
                    break;
                case 'Refund':
                case 'Reverse Payment':
                    $t['refunds'] += $amount;
                    break;
            }
            $running += self::signedAmount($row);
        }

        foreach (['invoiced', 'paid', 'creditNotes', 'refunds'] as $k) {
            $t[$k] = round($t[$k], 2);
        }
        $t['closing'] = round($running, 2);

        return $t;
    }

    /**
     * The two identities that must hold on every statement:
     *
     *   Closing == Opening + Invoiced - Paid - CreditNotes + Refunds
     *   Closing == the running balance of the last printed line
     *
     * A statement that cannot balance should raise rather than quietly mislead
     * a client, so this returns the reason it failed and the caller refuses to
     * render. Returns null when both identities hold.
     *
     * The second identity is satisfied by construction — headerTotals() derives
     * both figures in one pass over one row set — so a failure here means the
     * per-type sign map and the header buckets have drifted apart in code, which
     * is exactly the regression worth blocking a render for. A tolerance of half
     * a thebe absorbs per-row rounding.
     */
    public static function assertBalances(array $totals): ?string
    {
        $expected = $totals['opening']
            + $totals['invoiced']
            - $totals['paid']
            - $totals['creditNotes']
            + $totals['refunds'];

        if (abs(round($expected, 2) - $totals['closing']) > 0.005) {
            return sprintf(
                'Statement does not balance: opening %.2f + invoiced %.2f - paid %.2f '
                . '- credit notes %.2f + refunds %.2f = %.2f, but the running balance '
                . 'closed at %.2f.',
                $totals['opening'],
                $totals['invoiced'],
                $totals['paid'],
                $totals['creditNotes'],
                $totals['refunds'],
                round($expected, 2),
                $totals['closing']
            );
        }

        return null;
    }

    /**
     * Closing Balance exactly as the statement template accumulates it:
     * running total that adds invoice debits and subtracts payment credits and
     * credit-note debits. Mirrors the account_statement_domcom blade's
     * $tbalance loop.
     *
     * @param bool $refundsRaiseBalance  DEPRECATED no-op, kept so the existing
     *   call sites keep working unchanged. A refund returns premium to the
     *   customer and re-opens the amount owed, so its debit is now added on
     *   EVERY tier — DOM/COM included. Previously only MIS did this, which left
     *   DOM/COM statements understating the balance by the refunded amount
     *   whenever a refund was paid out (the refund printed in the Refund column
     *   but hit no accumulator, because a Refund row carries its value in
     *   `debit` and `debit` was only ever read for Invoice / Credit Note rows).
     */
    public static function closingBalance(int $policyId, $actionId = null, bool $refundsRaiseBalance = false): float
    {
        $tbalance = 0.0;
        // Pass $refundsRaiseBalance into rows() so the row set is consistent with
        // the balance math: on MIS the original Payment is kept and the refund
        // nets via its own debit (below); on DOM/COM the historical exclusion is
        // preserved. This is what stops the MIS same-ref refund double-count.
        foreach (self::rows($policyId, $actionId, $refundsRaiseBalance) as $r) {
            // One signed amount per row, resolved per trans_type by
            // signedAmount() / rowAmount() instead of read off the debit/credit
            // pair. The old loop did `-= credit` on EVERY row and then read
            // `debit` for Invoice / Credit Note / Refund, so a stale debit on an
            // invoice fed straight through to the balance.
            $tbalance += self::signedAmount($r);
        }

        return round($tbalance, 2);
    }
}
