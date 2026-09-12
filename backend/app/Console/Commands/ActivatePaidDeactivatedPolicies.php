<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Policy;
use AlphaDirect\Services\CacheService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Data correction: re-activate DOM/COM policies that are sitting at
 * policies.status = 0 ("Deactivated") even though they were never cancelled,
 * the customer has paid, and KYC is compliant.
 *
 * Root cause of the bad data is the Policy Details save path hardcoding
 * status = 0 on every save (EditWizard::saveStep1), so a live, paid, issued
 * policy silently drops out of monthly invoicing and shows as "Deactivated"
 * in the UI. This command only corrects the status flag — no dates, premiums,
 * schedules or RealPay/DPO contracts are touched, so nothing re-charges.
 *
 * The CSV is a WORK LIST, not a source of truth: every row is re-verified
 * against the live database before anything is written. A row is activated
 * only when ALL FOUR hold at run time:
 *
 *   1. policies.status = 0                 (still deactivated)
 *   2. not cancelled                       (status not 2/3 AND no ISSUED CANCEL action)
 *   3. payment done                        (a successful payment_transactions row,
 *                                           or a live non-reversed policy_ledger Payment credit)
 *   4. KYC compliant                       (customer_kyc.compliance = 1)
 *
 * Anything failing a guard is reported with a reason and left untouched.
 *
 * MIS (Instant Insurance) — opt in with --allow-mis
 *   MIS policies were switched off in bulk by mis:deactivate-nonpaying, whose
 *   population is the PREFIX (policyNumber LIKE 'MIS%'), not a product id.
 *   Their UI status is derived cover-level by
 *   PolicyController::checkMotorpolicyStatus($productId, $policyId, $set):
 *   for product 3 it reads 1 when a Success payment_transactions row exists
 *   ($set = 1) or an active `vehicle` row exists ($set = 2). A status flip is
 *   therefore only truthful when BOTH hold — otherwise the flag says Active
 *   while the derived cover state says otherwise. So on top of the four guards
 *   above, an MIS row must also clear:
 *
 *   5. a Success payment_transactions row  (the gateway/RealPay channel —
 *                                           ledger-only is NOT enough here)
 *   6. an active `vehicle` row             (vehicle.status = 1)
 *
 *   Everything else — dates, premium, schedules, RealPay contracts — is left
 *   alone exactly as for DOM/COM, so no policy re-rates and nothing re-charges.
 *
 * Deliberately uses the query builder (NOT $policy->save()) for the status
 * write so PolicyObserver::updated() does NOT fire — otherwise every one of
 * these customers gets a "policy activated" SMS/email for a policy they have
 * been holding all along. The caches the observer would normally clear are
 * cleared here by hand instead.
 *
 * Usage:
 *   php artisan policies:activate-paid-deactivated --dry-run
 *   php artisan policies:activate-paid-deactivated
 *
 *   MIS work list (dry run first, always):
 *     php artisan policies:activate-paid-deactivated \
 *       --file=mis_cancelled_still_collecting.csv --allow-mis --dry-run
 */
class ActivatePaidDeactivatedPolicies extends Command
{
    protected $signature = 'policies:activate-paid-deactivated
        {--file=activePolicyNeedtoScript.csv : CSV of policy numbers — relative to storage/app/, or an absolute path}
        {--dry-run : Verify every row and print the decision table without writing}
        {--allow-lapsed : Also activate policies whose latest action is LAPSED (blocked by default)}
        {--allow-mis : Also activate MIS (Instant Insurance) policies, under the extra cover-level guards}
        {--report= : Filename for the decision CSV under storage/app/ (default: activate_paid_deactivated_result[_dryrun]_<date>.csv)}
        {--no-report : Suppress the decision CSV}';

    protected $description = 'Re-activate deactivated-but-uncancelled DOM/COM (and, with --allow-mis, MIS) policies that are paid and KYC compliant';

    /**
     * DOM (8) / COM (7) only. Motor products 3/5 derive their status through
     * PolicyController::checkMotorpolicyStatus (cover-level, not a flat flag),
     * so a blanket status = 1 would be wrong for them — they are skipped unless
     * --allow-mis is given, which adds the cover-level guards those products
     * need instead of waiving them.
     */
    private const ALLOWED_PRODUCTS = [7, 8];

    /**
     * MIS scope is the policy-number PREFIX, matching mis:deactivate-nonpaying
     * (policyNumber LIKE 'MIS%'). MIB is the same Instant Insurance book but was
     * never in that deactivation population, so it stays out of this correction.
     */
    private const MIS_PREFIX = 'MIS';

    /** @var array<int,array<string,string>> */
    private array $rows = [];

    private int $activated = 0;
    private int $skipped   = 0;

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $path = $this->resolveCsvPath((string) $this->option('file'));
        if ($path === null) {
            $this->error('CSV not found: ' . $this->option('file'));
            $this->line('Place the work-list CSV at ' . storage_path('app') . ' on the box you are running against, or pass an absolute path via --file.');
            return self::FAILURE;
        }

        $policyNumbers = $this->readPolicyNumbers($path);
        if (empty($policyNumbers)) {
            $this->error('No policy numbers read from ' . $path . ' (expected a "policyNumber" column).');
            return self::FAILURE;
        }

        $this->info(($dryRun ? '[DRY RUN] ' : '')
            . 'Checking ' . count($policyNumbers) . ' policies from ' . basename($path) . '...');

        foreach ($policyNumbers as $number) {
            $this->processPolicy($number, $dryRun);
        }

        $this->table(
            ['policyNumber', 'product', 'status', 'paid', 'kyc', 'result', 'reason'],
            $this->rows
        );

        // Always leave a CSV audit trail of what was decided and why — the run
        // is a bulk money-affecting correction, so "which 40 did it touch?"
        // must be answerable after the fact without re-querying.
        if (!$this->option('no-report')) {
            $this->writeReport((string) ($this->option('report') ?: sprintf(
                'activate_paid_deactivated_result%s_%s.csv',
                $dryRun ? '_dryrun' : '',
                Carbon::now()->format('Y-m-d_His')
            )));
        }

        if ($dryRun) {
            $this->warn("Dry run — nothing written. Would activate: {$this->activated}. Would skip: {$this->skipped}.");
        } else {
            $this->info("Done. Activated: {$this->activated}. Skipped: {$this->skipped}.");
        }

        return self::SUCCESS;
    }

    // ──────────────────────────────────────────────────────────────────
    // Per-policy verification + write
    // ──────────────────────────────────────────────────────────────────

    private function processPolicy(string $number, bool $dryRun): void
    {
        /** @var Policy|null $policy */
        $policy = Policy::where('policyNumber', $number)->first();

        if (!$policy) {
            $this->record($number, '—', '—', '—', '—', 'skipped', 'policy not found');
            return;
        }

        $productId = (int) $policy->product_id;
        $status    = (int) $policy->status;

        // Guard 1 — already active. Idempotent re-runs land here.
        if ($status === 1) {
            $this->record($number, $productId, $status, '—', '—', 'no change', 'already active');
            return;
        }

        // Guard 2 — cancelled / expired at policy level.
        if (in_array($status, [2, 3], true)) {
            $this->record($number, $productId, $status, '—', '—', 'skipped',
                $status === 2 ? 'policy is CANCELLED' : 'policy is EXPIRED');
            return;
        }

        if ($status !== 0) {
            $this->record($number, $productId, $status, '—', '—', 'skipped', 'unexpected status');
            return;
        }

        // Guard 3 — product scope. MIS rides in on the prefix, not the product
        // id, because that is how the deactivation run selected them.
        $isMis = $this->isMisPolicy($policy);

        if (!in_array($productId, self::ALLOWED_PRODUCTS, true) && !$isMis) {
            $this->record($number, $productId, $status, '—', '—', 'skipped',
                'product out of scope (DOM/COM only)');
            return;
        }

        if ($isMis && !$this->option('allow-mis')) {
            $this->record($number, $productId, $status, '—', '—', 'skipped',
                'MIS policy — pass --allow-mis to include it');
            return;
        }

        // Guard 4 — an ISSUED CANCEL transaction outranks the status flag.
        if ($this->hasIssuedCancel($policy->id)) {
            $this->record($number, $productId, $status, '—', '—', 'skipped',
                'has ISSUED CANCEL action');
            return;
        }

        // Guard 5 — a lapsed policy is not simply mis-flagged; it needs a
        // REINSTATE transaction, not a status flip. Blocked unless opted in.
        if (!$this->option('allow-lapsed') && ($lapsed = $this->latestLapsedAction($policy->id))) {
            $this->record($number, $productId, $status, '—', '—', 'skipped',
                "latest action is LAPSED ({$lapsed})");
            return;
        }

        // Guard 6 — payment done.
        $lastPaymentDate = $this->lastSuccessfulPaymentDate($policy);
        if ($lastPaymentDate === null) {
            $this->record($number, $productId, $status, 'no', '—', 'skipped', 'no successful payment');
            return;
        }

        // Guard 7 — KYC compliant.
        if (!$this->isKycCompliant($policy)) {
            $this->record($number, $productId, $status, 'yes', 'no', 'skipped', 'KYC not compliant');
            return;
        }

        // Guard 8 (MIS only) — the flat flag must agree with the cover-level
        // status checkMotorpolicyStatus derives, or the UI and the flag diverge.
        if ($isMis && ($misReason = $this->misCoverGuardFailure($policy)) !== null) {
            $this->record($number, $productId, $status, 'yes', 'yes', 'skipped', $misReason);
            return;
        }

        if ($dryRun) {
            $this->record($number, $productId, $status, 'yes', 'yes', 'would activate',
                'last payment ' . $lastPaymentDate);
            $this->activated++;
            return;
        }

        $this->activate($policy, $lastPaymentDate);

        $this->record($number, $productId, $status, 'yes', 'yes', 'activated',
            'last payment ' . $lastPaymentDate);
        $this->activated++;
    }

    /**
     * Write status = 1 without waking PolicyObserver::updated(), which would
     * fire notifyPolicyActivated() and SMS/email every customer on the list.
     */
    private function activate(Policy $policy, string $lastPaymentDate): void
    {
        $update = [
            'status'     => 1,
            'updated_at' => Carbon::now(),
        ];

        // Only fill the activation date when it was never stamped (the
        // "NEVER ACTIVATED" rows). An existing date is history — leave it.
        if (empty($policy->policyActivatedDate)) {
            $update['policyActivatedDate'] = $lastPaymentDate;
        }

        DB::transaction(function () use ($policy, $update) {
            DB::table('policies')->where('id', $policy->id)->update($update);

            // Clear what PolicyObserver::saved() normally would, so the detail
            // page and dashboard stop serving the stale Deactivated row.
            CacheService::forgetPolicy($policy->id);
            Cache::forget("policy_number_{$policy->policyNumber}");
            Cache::forget('dashboard_policy_counts');

            activity('Policy')
                ->performedOn($policy)
                ->log('Reactivated (status 0 → 1): paid, KYC compliant and never cancelled — '
                    . 'correcting policies.status set to 0 by the Policy Details save path');
        });

        Log::info('ActivatePaidDeactivatedPolicies: activated ' . $policy->policyNumber, [
            'policy_id'            => $policy->id,
            'product_id'           => $policy->product_id,
            'policyActivatedDate'  => $update['policyActivatedDate'] ?? (string) $policy->policyActivatedDate,
        ]);
    }

    // ──────────────────────────────────────────────────────────────────
    // Guards
    // ──────────────────────────────────────────────────────────────────

    private function hasIssuedCancel(int $policyId): bool
    {
        return DB::table('policy_actions')
            ->where('policy_id', $policyId)
            ->where('transaction_type', 'CANCEL')
            ->where('status', 'ISSUED')
            ->whereNull('deleted_at')
            ->exists();
    }

    /**
     * Latest action by chronology (effective_from, then id) — returns its
     * "TYPE / date" label when that action is LAPSED, otherwise null.
     */
    private function latestLapsedAction(int $policyId): ?string
    {
        $latest = DB::table('policy_actions')
            ->where('policy_id', $policyId)
            ->whereNull('deleted_at')
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first(['transaction_type', 'status', 'effective_from']);

        if (!$latest || strtoupper((string) $latest->status) !== 'LAPSED') {
            return null;
        }

        return trim($latest->transaction_type . ' ' . $latest->effective_from);
    }

    /**
     * "Payment done" across both channels this book uses:
     *   - payment_transactions (+ archive) with a success status — the MIS /
     *     gateway path, same test the legacy policiesDeactivatedPaymentSuccess
     *     cron applies.
     *   - policy_ledger Payment credits — the DOM/COM path, where collections
     *     land as ledger rows rather than gateway transactions.
     *
     * Reversed rows never count. Returns the newest payment date (Y-m-d) or
     * null when nothing was ever collected.
     */
    private function lastSuccessfulPaymentDate(Policy $policy): ?string
    {
        $dates = [];

        $ledger = DB::table('policy_ledger')
            ->where('policy_id', $policy->id)
            ->where('trans_type', 'Payment')
            ->where('status', '!=', 'Reversed')
            ->whereNull('deleted_at')
            ->where('credit', '>', 0)
            ->max('accounting_date');

        if ($ledger) {
            $dates[] = $ledger;
        }

        $gateway = DB::table('payment_transactions')
            ->where('policyNumber', $policy->policyNumber)
            ->where('status', 'like', '%success%')
            ->max('new_payment_date');

        if ($gateway) {
            $dates[] = $gateway;
        }

        $dates = array_filter($dates);
        if (empty($dates)) {
            return null;
        }

        $latest = null;
        foreach ($dates as $date) {
            try {
                $parsed = Carbon::parse($date)->format('Y-m-d');
            } catch (\Exception $e) {
                continue;
            }
            if ($latest === null || $parsed > $latest) {
                $latest = $parsed;
            }
        }

        return $latest;
    }

    private function isKycCompliant(Policy $policy): bool
    {
        if (empty($policy->customer_id)) {
            return false;
        }

        return DB::table('customer_kyc')
            ->where('customer_id', $policy->customer_id)
            ->where('compliance', 1)
            ->exists();
    }

    /** Same scope test as mis:deactivate-nonpaying — the policy-number prefix. */
    private function isMisPolicy(Policy $policy): bool
    {
        return strpos((string) $policy->policyNumber, self::MIS_PREFIX) === 0;
    }

    /**
     * Mirrors PolicyController::checkMotorpolicyStatus for the Instant book: a
     * Success payment_transactions row ($set = 1) AND an active `vehicle` row
     * ($set = 2). Both must hold, so the flag we write agrees with the status
     * the UI derives. Returns the failure reason, or null when the row is clear.
     */
    private function misCoverGuardFailure(Policy $policy): ?string
    {
        $hasGatewayPayment = DB::table('payment_transactions')
            ->where('policyNumber', $policy->policyNumber)
            ->where('status', 'like', '%success%')
            ->exists();

        if (!$hasGatewayPayment) {
            return 'MIS: no Success payment_transactions row (ledger credit alone will not derive Active)';
        }

        $hasActiveVehicle = DB::table('vehicle')
            ->where('policy_id', $policy->id)
            ->where('status', '1')
            ->exists();

        if (!$hasActiveVehicle) {
            return 'MIS: no active vehicle on the policy';
        }

        return null;
    }

    // ──────────────────────────────────────────────────────────────────
    // CSV in / report out
    // ──────────────────────────────────────────────────────────────────

    private function resolveCsvPath(string $file): ?string
    {
        foreach ([$file, storage_path('app/' . ltrim($file, '/\\'))] as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Reads the "policyNumber" column. Falls back to the first column when the
     * file has no header, so a bare one-number-per-line list also works.
     *
     * @return array<int,string>
     */
    private function readPolicyNumbers(string $path): array
    {
        $numbers = [];
        $column  = 0;

        $handle = fopen($path, 'r');
        if ($handle === false) {
            return [];
        }

        $first = fgetcsv($handle);
        if ($first === false) {
            fclose($handle);
            return [];
        }

        $header = array_map(fn($h) => strtolower(trim((string) $h, " \t\n\r\0\x0B\"\xEF\xBB\xBF")), $first);
        $index  = array_search('policynumber', $header, true);

        if ($index === false) {
            // No header — treat the first line as data.
            $numbers[] = trim((string) $first[0]);
        } else {
            $column = (int) $index;
        }

        while (($row = fgetcsv($handle)) !== false) {
            $value = trim((string) ($row[$column] ?? ''));
            if ($value !== '') {
                $numbers[] = $value;
            }
        }

        fclose($handle);

        return array_values(array_unique(array_filter($numbers)));
    }

    private function record(string $number, $product, $status, string $paid, string $kyc, string $result, string $reason): void
    {
        if ($result === 'skipped' || $result === 'no change') {
            $this->skipped++;
        }

        $this->rows[] = [
            'policyNumber' => $number,
            'product'      => (string) $product,
            'status'       => (string) $status,
            'paid'         => $paid,
            'kyc'          => $kyc,
            'result'       => $result,
            'reason'       => $reason,
        ];
    }

    private function writeReport(string $name): void
    {
        $path   = storage_path('app/' . ltrim($name, '/\\'));
        $handle = fopen($path, 'w');
        if ($handle === false) {
            $this->warn('Could not write report to ' . $path);
            return;
        }

        fputcsv($handle, ['policyNumber', 'product', 'status', 'paid', 'kyc', 'result', 'reason']);
        foreach ($this->rows as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);

        $this->info('Report written to ' . $path);
    }
}
