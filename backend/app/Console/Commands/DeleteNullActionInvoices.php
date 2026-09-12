<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;
use Log;

/**
 * Cleans two kinds of wrong invoice on the action-wise products
 * (DomCom 7/8 and Specialist 16-20/22-24):
 *
 *   --mode=null       Invoices carrying NO action_id.
 *                     invoice:generate, policyledger, policyledgerdaily and
 *                     policyledgerarchive bill off the CALENDAR and never stamp
 *                     action_id. Both invoice:generate and PolicyLedgerDaily now
 *                     exclude these products, but everything written before that
 *                     exclusion is still live. They also defeat the duplicate
 *                     guard in every action-wise writer, which reads
 *                     ->where('action_id', $actionId)->count() == 0 — a NULL row
 *                     is invisible to it, so the renew cron bills the period twice.
 *
 *   --mode=duplicate  One action carrying MORE THAN ONE live invoice.
 *                     Keeps a single invoice per action (newest ledger id by
 *                     default, --keep=first for oldest) and removes the rest.
 *                     ONLY when every copy is for the SAME amount — a group whose
 *                     amounts differ is a re-rate or a partial, not a duplicate,
 *                     so it is reported and left alone.
 *
 *   --mode=both       Both passes in one run.
 *
 * Safety model:
 *   - DRY RUN BY DEFAULT. Nothing is written without --commit.
 *   - Deletes the whole triplet (Invoice + Invoice Premium + Invoice VAT) so the
 *     ledger stays balanced. A triplet whose legs do not add up to the Invoice
 *     row is SKIPPED, not guessed at.
 *   - Any invoice that is not 'Pending', or that has pmts_adjust set, is SKIPPED
 *     — money has been receipted against it and it needs a credit note.
 *   - 'Reversed' invoices are never candidates; they already have a credit note.
 *   - Every affected row is written to a CSV under storage/app/ledger-cleanup/
 *     before it is touched.
 *   - policy_ledger is soft deleted. policy_subledger is soft deleted when it has
 *     a deleted_at column and hard deleted otherwise (warned at runtime).
 */
class DeleteNullActionInvoices extends Command
{
    protected $signature = 'ledger:delete-null-action-invoices
        {--mode=null : null | duplicate | both}
        {--products=7,8,16,17,18,19,20,22,23,24 : Comma-separated product ids to clean}
        {--policy= : Restrict to one policy id}
        {--from-file= : File of policy ids, one per line}
        {--from= : Only invoices with invoice_date >= this date (Y-m-d)}
        {--to= : Only invoices with invoice_date <= this date (Y-m-d), inclusive}
        {--keep=last : Duplicate mode — which copy survives: last (newest id) or first (oldest id)}
        {--allow-amount-mismatch : Duplicate mode — also remove groups whose amounts differ (DANGEROUS)}
        {--include-test : Also clean policies flagged is_test_policy}
        {--include-paid : Also delete invoices that are not Pending (DANGEROUS)}
        {--limit=0 : Stop after this many invoices (0 = no limit)}
        {--commit : Actually write. Without this the command only reports.}';

    protected $description = 'Soft-delete orphan (no action_id) and duplicate-per-action invoices on DomCom/Specialist products';

    /** Legs may differ from the Invoice row by this much and still reconcile. */
    private const RECONCILE_TOLERANCE = 0.02;

    /** Amounts within this much of each other count as the same money. */
    private const AMOUNT_TOLERANCE = 0.01;

    public function handle()
    {
        $commit = (bool) $this->option('commit');
        $mode   = strtolower((string) $this->option('mode'));

        if (!in_array($mode, ['null', 'duplicate', 'both'], true)) {
            $this->error('--mode must be null, duplicate or both.');
            return 1;
        }

        $keep = strtolower((string) $this->option('keep'));
        if (!in_array($keep, ['last', 'first'], true)) {
            $this->error('--keep must be last or first.');
            return 1;
        }

        $products = array_values(array_filter(array_map(
            'intval',
            explode(',', (string) $this->option('products'))
        )));

        if (empty($products)) {
            $this->error('--products resolved to an empty list.');
            return 1;
        }

        $policyIds = $this->resolvePolicyFilter();
        if ($policyIds === false) {
            return 1;
        }

        $this->info(($commit ? 'COMMIT' : 'DRY RUN')
            . ' — mode ' . $mode
            . ' — products ' . implode(',', $products)
            . ' — invoice_date ' . ($this->option('from') ?: 'any') . ' .. ' . ($this->option('to') ?: 'any')
            . ($mode !== 'null' ? ' — keep ' . $keep : '')
            . ($this->option('include-test') ? ' — test policies INCLUDED' : '')
            . ($policyIds ? ' — ' . count($policyIds) . ' policy filter' : ''));

        $candidates = collect();

        if ($mode === 'null' || $mode === 'both') {
            $nullRows = $this->fetchNullActionCandidates($products, $policyIds);
            $this->info($nullRows->count() . ' live Invoice row(s) with no action_id.');
            $candidates = $candidates->concat($nullRows);
        }

        if ($mode === 'duplicate' || $mode === 'both') {
            $dupRows = $this->fetchDuplicateCandidates($products, $policyIds, $keep);
            $this->info($dupRows->where('is_keeper', false)->count() . ' surplus invoice row(s) across '
                . $dupRows->pluck('dup_group')->unique()->count() . ' duplicated action(s).');
            $candidates = $candidates->concat($dupRows);
        }

        // A row can only ever be one or the other (duplicate groups require an
        // action_id), but de-dupe defensively so 'both' can never plan a row twice.
        $candidates = $candidates
            ->unique('id')
            ->sortBy([['policy_id', 'asc'], ['id', 'asc']])
            ->values();

        if ($candidates->isEmpty()) {
            $this->warn('Nothing matched.');
            return 0;
        }

        $limit      = (int) $this->option('limit');
        $usedLegIds = [];
        $planned    = [];
        $skipped    = [];

        foreach ($candidates as $invoice) {
            // Legs are claimed for EVERY candidate, keepers included, and always
            // in ascending id order. A keeper that did not claim its own legs
            // would let the next invoice in the group steal them.
            $legs = $this->findLegs($invoice, $usedLegIds);
            if (count($legs) === 2) {
                foreach ($legs as $leg) {
                    $usedLegIds[$leg->id] = true;
                }
            }

            if (!empty($invoice->is_keeper)) {
                continue;   // the copy that survives — legs claimed, nothing planned
            }

            if ($limit > 0 && count($planned) >= $limit) {
                $this->warn('--limit reached; remaining candidates NOT processed.');
                break;
            }

            if (!$this->option('include-paid') && !$this->isUnpaid($invoice)) {
                $skipped[] = [$invoice, 'not Pending (status ' . $invoice->status
                    . ', pmts_adjust ' . ($invoice->pmts_adjust ?? 'NULL') . ') — needs a credit note'];
                continue;
            }

            // Duplicate mode: the copies must be for the same money. Anything else
            // is a re-rate or a partial and needs a human.
            if (!empty($invoice->dup_group) && empty($invoice->amounts_identical)
                && !$this->option('allow-amount-mismatch')) {
                $skipped[] = [$invoice, 'action ' . $invoice->action_id . ' has AMOUNTS DIFFERING by '
                    . number_format((float) $invoice->amount_spread, 2) . ' — not a clean duplicate'];
                continue;
            }

            if (count($legs) !== 2) {
                $skipped[] = [$invoice, 'could not match both legs (found ' . count($legs)
                    . ') — delete by hand or check the accounting_date'];
                continue;
            }

            if (!$this->reconciles($invoice, $legs)) {
                $skipped[] = [$invoice, 'legs do not add up to the Invoice row — not touching it'];
                continue;
            }

            $planned[] = ['invoice' => $invoice, 'legs' => $legs];
        }

        $this->report($planned, $skipped);

        if (empty($planned)) {
            $this->warn('Nothing to delete.');
            return 0;
        }

        $csv = $this->writeBackup($planned);
        $this->info('Backup written to ' . $csv);

        if (!$commit) {
            $this->warn('DRY RUN — no rows changed. Re-run with --commit to apply.');
            return 0;
        }

        return $this->applyDeletes($planned, $csv);
    }

    // ── candidate selection ────────────────────────────────────────────────

    /**
     * "Has no action_id" on a STRING action_id column.
     *
     * policy_ledger.action_id is a varchar (see SoftDeleteOrphanedDomComLedger),
     * so a bare `->where('action_id', 0)` would be a numeric comparison and MySQL
     * would cast ANY non-numeric string to 0 — matching rows that DO carry an
     * action reference. Match the three real "empty" spellings explicitly instead.
     */
    private function whereNoAction($query, string $column = 'action_id')
    {
        return $query->where(function ($q) use ($column) {
            $q->whereNull($column)
              ->orWhere($column, '')
              ->orWhere($column, '0');
        });
    }

    private function whereHasAction($query, string $column = 'action_id')
    {
        return $query->whereNotNull($column)
            ->where($column, '<>', '')
            ->where($column, '<>', '0');
    }

    private function hasNoAction($invoice): bool
    {
        return $invoice->action_id === null
            || $invoice->action_id === ''
            || $invoice->action_id === '0'
            || $invoice->action_id === 0;
    }

    /** Columns every candidate row must carry, whichever pass produced it. */
    private function candidateColumns(): array
    {
        return [
            'pl.id', 'pl.policy_id', 'pl.action_id', 'pl.trans_type', 'pl.invoice_no',
            'pl.invoice_date', 'pl.accounting_date', 'pl.invoice_amount', 'pl.debit',
            'pl.pmts_adjust', 'pl.status', 'pl.created_at',
            'p.policyNumber', 'p.product_id',
        ];
    }

    /** Shared WHERE for anything this command is allowed to consider. */
    private function baseCandidateQuery(array $products, $policyIds, string $alias = 'pl')
    {
        $query = DB::table('policy_ledger as ' . $alias)
            ->join('policies as p', 'p.id', '=', $alias . '.policy_id')
            ->whereIn('p.product_id', $products)
            ->whereNull($alias . '.deleted_at')
            ->where($alias . '.trans_type', 'Invoice')
            // A reversed invoice has already been backed out by a credit note —
            // soft-deleting it again would double-reverse the GL.
            ->where(function ($q) use ($alias) {
                $q->whereNull($alias . '.status')->orWhere($alias . '.status', '<>', 'Reversed');
            });

        if ($policyIds) {
            $query->whereIn($alias . '.policy_id', $policyIds);
        }

        // Test policies are excluded by default so a cleanup run cannot be
        // inflated by fixture data. The column is guarded in case an older
        // schema does not carry it.
        if (!$this->option('include-test') && Schema::hasColumn('policies', 'is_test_policy')) {
            $query->where(function ($q) {
                $q->whereNull('p.is_test_policy')->orWhere('p.is_test_policy', 0);
            });
        }

        return $query;
    }

    private function fetchNullActionCandidates(array $products, $policyIds)
    {
        $query = $this->baseCandidateQuery($products, $policyIds)
            ->select($this->candidateColumns())
            ->orderBy('pl.policy_id')
            ->orderBy('pl.id');

        $this->whereNoAction($query, 'pl.action_id');
        $this->applyDateWindow($query, 'pl');

        return $query->get()->each(function ($row) {
            $row->is_keeper         = false;
            $row->dup_group         = null;
            $row->amounts_identical = null;
            $row->amount_spread     = null;
            $row->keep_id           = null;
            $row->reason            = 'no action_id';
        });
    }

    /**
     * Actions carrying more than one live invoice.
     *
     * Grouped by policy_id + action_id, not action_id alone: action_id is a
     * varchar, so grouping on it by itself relies on the string never repeating
     * across policies, and a stray non-numeric value would collapse unrelated
     * policies into one group.
     *
     * The --from/--to window selects which GROUPS are in play, but the keeper is
     * chosen across ALL live copies in the group. Date-filtering the members
     * could otherwise hide the surviving copy and delete the whole group.
     */
    private function fetchDuplicateCandidates(array $products, $policyIds, string $keep)
    {
        $groupQuery = $this->baseCandidateQuery($products, $policyIds, 'pl')
            ->select('pl.policy_id', 'pl.action_id', DB::raw('COUNT(*) as n_invoices'))
            ->groupBy('pl.policy_id', 'pl.action_id')
            ->havingRaw('COUNT(*) > 1');

        $this->whereHasAction($groupQuery, 'pl.action_id');

        $groups = $groupQuery->get();
        if ($groups->isEmpty()) {
            return collect();
        }

        $groupKeys = $groups->map(fn($g) => $g->policy_id . '|' . $g->action_id)->flip();

        // Pull every live invoice for the policies involved, then keep only the
        // rows whose policy+action is one of the duplicated groups.
        $memberQuery = $this->baseCandidateQuery($products, $groups->pluck('policy_id')->unique()->all(), 'pl')
            ->select($this->candidateColumns())
            ->orderBy('pl.policy_id')
            ->orderBy('pl.id');

        $this->whereHasAction($memberQuery, 'pl.action_id');

        $members = $memberQuery->get()
            ->filter(fn($r) => $groupKeys->has($r->policy_id . '|' . $r->action_id))
            ->groupBy(fn($r) => $r->policy_id . '|' . $r->action_id);

        [$from, $to] = $this->dateWindow();
        $out = collect();

        foreach ($members as $key => $rows) {
            if ($rows->count() < 2) {
                continue;
            }

            // Group must touch the requested window to be in play at all.
            if ($from || $to) {
                $inWindow = $rows->contains(function ($r) use ($from, $to) {
                    if (!$r->invoice_date) {
                        return false;
                    }
                    $d = Carbon::parse($r->invoice_date)->toDateString();
                    return (!$from || $d >= $from) && (!$to || $d <= $to);
                });
                if (!$inWindow) {
                    continue;
                }
            }

            $amounts = $rows->map(fn($r) => (float) str_replace(',', '', (string) $r->invoice_amount));
            $spread  = $amounts->max() - $amounts->min();
            $keepId  = $keep === 'first' ? $rows->min('id') : $rows->max('id');

            foreach ($rows as $row) {
                $row->is_keeper         = ((int) $row->id === (int) $keepId);
                $row->dup_group         = $key;
                $row->n_invoices        = $rows->count();
                $row->keep_id           = $keepId;
                $row->amounts_identical = ($spread <= self::AMOUNT_TOLERANCE);
                $row->amount_spread     = $spread;
                $row->reason            = 'duplicate on action ' . $row->action_id
                    . ' (' . $rows->count() . ' copies, keeping ledger ' . $keepId . ')';
                $out->push($row);
            }
        }

        return $out;
    }

    /** --from / --to as plain Y-m-d strings, either may be null. */
    private function dateWindow(): array
    {
        return [
            $this->option('from') ? Carbon::parse($this->option('from'))->toDateString() : null,
            $this->option('to')   ? Carbon::parse($this->option('to'))->toDateString()   : null,
        ];
    }

    private function applyDateWindow($query, string $alias = 'pl'): void
    {
        [$from, $to] = $this->dateWindow();

        if ($from) {
            $query->whereDate($alias . '.invoice_date', '>=', $from);
        }
        if ($to) {
            $query->whereDate($alias . '.invoice_date', '<=', $to);
        }
    }

    /**
     * --policy / --from-file into a plain id list. Returns null for "no filter",
     * false when the caller passed something unusable.
     */
    private function resolvePolicyFilter()
    {
        $ids = [];

        if ($this->option('policy')) {
            $ids[] = (int) $this->option('policy');
        }

        if ($this->option('from-file')) {
            $path = $this->option('from-file');
            if (!is_readable($path)) {
                $this->error('--from-file is not readable: ' . $path);
                return false;
            }
            foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                $line = trim($line);
                if ($line !== '' && ctype_digit($line)) {
                    $ids[] = (int) $line;
                }
            }
        }

        return empty($ids) ? null : array_values(array_unique($ids));
    }

    // ── leg matching ───────────────────────────────────────────────────────

    private function isUnpaid($invoice): bool
    {
        return strcasecmp((string) $invoice->status, 'Pending') === 0
            && (float) ($invoice->pmts_adjust ?? 0) == 0.0;
    }

    /**
     * The Premium and VAT legs carry no invoice_no, so the only things tying them
     * to their Invoice row are the policy, the action reference, the accounting
     * date, and the fact that all three are inserted together — which puts their
     * ids just below the Invoice row's id. Match on all of those, nearest id
     * first, and never reuse a leg already claimed by an earlier invoice.
     */
    private function findLegs($invoice, array $usedLegIds): array
    {
        $query = DB::table('policy_ledger')
            ->where('policy_id', $invoice->policy_id)
            ->whereNull('deleted_at')
            ->whereIn('trans_type', ['Invoice Premium', 'Invoice VAT']);

        if ($this->hasNoAction($invoice)) {
            $this->whereNoAction($query);
        } else {
            $query->where('action_id', $invoice->action_id);
        }

        $rows = $query
            ->whereRaw('DATE(accounting_date) = DATE(?)', [$invoice->accounting_date])
            ->where('id', '<', $invoice->id)
            ->orderBy('id', 'DESC')
            ->select('id', 'policy_id', 'action_id', 'trans_type', 'accounting_date',
                'debit', 'premium', 'status')
            ->limit(10)
            ->get();

        $legs = [];
        foreach ($rows as $row) {
            if (isset($usedLegIds[$row->id]) || isset($legs[$row->trans_type])) {
                continue;
            }
            $legs[$row->trans_type] = $row;
        }

        return $legs;
    }

    /** Premium leg + VAT leg must equal the Invoice row, or we leave it alone. */
    private function reconciles($invoice, array $legs): bool
    {
        $sum = (float) str_replace(',', '', (string) ($legs['Invoice Premium']->debit ?? 0))
             + (float) str_replace(',', '', (string) ($legs['Invoice VAT']->debit ?? 0));

        return abs($sum - (float) str_replace(',', '', (string) $invoice->debit)) <= self::RECONCILE_TOLERANCE;
    }

    // ── reporting / writing ────────────────────────────────────────────────

    private function report(array $planned, array $skipped): void
    {
        if (!empty($skipped)) {
            $this->warn(count($skipped) . ' invoice(s) SKIPPED:');
            foreach ($skipped as [$invoice, $reason]) {
                $this->line(sprintf('  policy %s (%s) · ledger %d · inv %s · %s · %s',
                    $invoice->policy_id, $invoice->policyNumber, $invoice->id,
                    $invoice->invoice_no, $invoice->invoice_amount, $reason));
            }
        }

        if (empty($planned)) {
            return;
        }

        $this->info(count($planned) . ' invoice(s) to delete ('
            . (count($planned) * 3) . ' ledger rows):');

        $rows = [];
        foreach ($planned as $p) {
            $inv = $p['invoice'];
            $rows[] = [
                $inv->policy_id,
                $inv->policyNumber,
                $inv->product_id,
                $inv->action_id ?? 'NULL',
                $inv->id,
                $inv->invoice_no,
                $inv->invoice_date,
                $inv->invoice_amount,
                $p['legs']['Invoice Premium']->id . '/' . $p['legs']['Invoice VAT']->id,
                $inv->reason ?? '',
            ];
        }
        $this->table(
            ['policy', 'number', 'prod', 'action', 'ledger_id', 'invoice_no',
                'inv_date', 'amount', 'legs', 'why'],
            $rows
        );

        $total = array_sum(array_map(fn($p) => (float) str_replace(',', '', (string) $p['invoice']->invoice_amount), $planned));
        $this->info('Total invoiced value being reversed: ' . number_format($total, 2));
    }

    private function writeBackup(array $planned): string
    {
        $dir = storage_path('app/ledger-cleanup');
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $path = $dir . '/invoice-cleanup-' . Carbon::now()->format('Ymd-His') . '.csv';
        $fh   = fopen($path, 'w');

        fputcsv($fh, ['table', 'id', 'policy_id', 'policyNumber', 'product_id', 'action_id',
            'trans_type', 'invoice_no', 'trans_ref', 'invoice_date', 'accounting_date',
            'invoice_amount', 'debit', 'credit', 'status', 'account_name', 'reason']);

        foreach ($planned as $p) {
            $invoice = $p['invoice'];
            $reason  = $invoice->reason ?? '';

            foreach (array_merge([$invoice], array_values($p['legs'])) as $row) {
                fputcsv($fh, ['policy_ledger', $row->id, $invoice->policy_id, $invoice->policyNumber,
                    $invoice->product_id, $row->action_id ?? '', $row->trans_type,
                    $row->invoice_no ?? '', '', $row->invoice_date ?? '', $row->accounting_date ?? '',
                    $row->invoice_amount ?? '', $row->debit ?? '', '', $row->status ?? '', '', $reason]);
            }

            foreach ($this->subLedgerRows($invoice) as $sub) {
                fputcsv($fh, ['policy_subledger', $sub->id, $invoice->policy_id, $invoice->policyNumber,
                    $invoice->product_id, $sub->action_id ?? '', $sub->trans_type, '', $sub->trans_ref,
                    '', $sub->accounting_date, '', $sub->debit, $sub->credit, '',
                    $sub->account_name, $reason]);
            }
        }

        fclose($fh);
        return $path;
    }

    /** Sub-ledger rows are tied to the invoice by trans_ref = invoice_no. */
    private function subLedgerRows($invoice)
    {
        if ($invoice->invoice_no === null || $invoice->invoice_no === '') {
            return collect();
        }

        return DB::table('policy_subledger')
            ->where('policy_id', $invoice->policy_id)
            ->where('trans_ref', $invoice->invoice_no)
            ->select('id', 'policy_id', 'action_id', 'trans_type', 'trans_ref',
                'accounting_date', 'account_name', 'debit', 'credit')
            ->get();
    }

    private function applyDeletes(array $planned, string $csv): int
    {
        $now                 = Carbon::now();
        $ledgerKilled        = 0;
        $subKilled           = 0;
        $failed              = 0;
        $softDeleteSubLedger = Schema::hasColumn('policy_subledger', 'deleted_at');

        if (!$softDeleteSubLedger) {
            $this->warn('policy_subledger has no deleted_at column — those rows will be HARD deleted. '
                . 'They are in the CSV backup.');
        }

        foreach ($planned as $p) {
            $invoice = $p['invoice'];
            $ids     = array_merge([$invoice->id], array_map(fn($l) => $l->id, array_values($p['legs'])));

            DB::beginTransaction();
            try {
                $ledgerKilled += DB::table('policy_ledger')
                    ->whereIn('id', $ids)
                    ->whereNull('deleted_at')
                    ->update(['deleted_at' => $now, 'updated_at' => $now]);

                if ($invoice->invoice_no !== null && $invoice->invoice_no !== '') {
                    $sub = DB::table('policy_subledger')
                        ->where('policy_id', $invoice->policy_id)
                        ->where('trans_ref', $invoice->invoice_no);

                    // Prefer a soft delete so the sub-ledger stays recoverable and
                    // matches how the ledger side is handled. The column is not
                    // guaranteed to exist (no migration adds it) — fall back to a
                    // hard delete, which is why the CSV backup is written first.
                    $subKilled += $softDeleteSubLedger
                        ? $sub->whereNull('deleted_at')->update(['deleted_at' => $now])
                        : $sub->delete();
                }

                DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();
                $failed++;
                $this->error('policy ' . $invoice->policy_id . ' invoice ' . $invoice->invoice_no
                    . ' FAILED — ' . $e->getMessage());
                Log::error('ledger:delete-null-action-invoices row failed', [
                    'policy_id'  => $invoice->policy_id,
                    'action_id'  => $invoice->action_id,
                    'invoice_no' => $invoice->invoice_no,
                    'ledger_ids' => $ids,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        $this->info("Done. {$ledgerKilled} ledger row(s) soft-deleted, {$subKilled} sub-ledger row(s) removed"
            . ($failed ? ", {$failed} invoice(s) FAILED" : '') . '.');
        $this->line('Restore data: ' . $csv);

        Log::info('ledger:delete-null-action-invoices completed', [
            'mode'           => $this->option('mode'),
            'invoices'       => count($planned),
            'ledger_rows'    => $ledgerKilled,
            'subledger_rows' => $subKilled,
            'failed'         => $failed,
            'backup'         => $csv,
        ]);

        return $failed ? 1 : 0;
    }
}
