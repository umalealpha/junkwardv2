<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Ledger;
use AlphaDirect\PaymentTransaction;
use AlphaDirect\Policy;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * On-demand version of PolicyLedgerDaily:cron, scoped to a single policy.
 *
 * Reuses every bit of PolicyLedgerDaily's invoice/payment/refund generation
 * logic (the per-policy loop, the orphaned-payment pass, the orphaned-refund
 * pass) untouched — it only overrides which policy(ies) and which
 * payments/refunds feed into that logic, via the hook methods PolicyLedgerDaily
 * exposes for this purpose. This is for support/finance to repair a specific
 * MIS policy whose Account Statement, Balance Owing or Total Dues aren't
 * reflecting correctly, without re-running (or risking) the full daily batch.
 */
class PolicyLedgerByPolicy extends PolicyLedgerDaily
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'PolicyLedgerByPolicy:cron {policy : Policy ID or policy number, e.g. 12345 or MIS2026214867}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Regenerate missing ledger/sub-ledger entries (invoices, payments, refunds) for a single MIS policy by ID or policy number';

    /**
     * Resolved by selectPolicies(); reused by selectOrphanPayments()/selectOrphanRefunds()
     * to scope those passes to this one policy.
     */
    protected ?Policy $targetPolicy = null;

    public function handle()
    {
        $result = parent::handle();

        // parent::handle() only logs failures (it's normally unattended) —
        // surface it on the console too since this command is run
        // interactively by support/finance.
        if ($result !== 0) {
            $this->error("PolicyLedgerByPolicy failed for '{$this->argument('policy')}' — see the application log for details.");
            return $result;
        }

        // Report the post-run state — the Account Statement / Balance Owing /
        // Total Dues views (AccountStatementService, PolicyController::ledger)
        // all compute live off policy_ledger, so once these rows are correct
        // those views are automatically correct too; nothing else to update.
        if ($this->targetPolicy !== null) {
            $invoiceCount = Ledger::where('policy_id', $this->targetPolicy->id)->where('trans_type', 'Invoice')->count();
            $closingBalance = Ledger::where('policy_id', $this->targetPolicy->id)->orderBy('id', 'DESC')->value('balance') ?? 0;

            $summary = "PolicyLedgerByPolicy summary for {$this->targetPolicy->policyNumber} (id={$this->targetPolicy->id}): "
                . "{$invoiceCount} invoice(s) on ledger, closing balance = {$closingBalance}";

            $this->info($summary);
            Log::info($summary);
        }

        return $result;
    }

    protected function cronName(): string
    {
        return 'PolicyLedgerByPolicy:cron';
    }

    protected function shouldSendDigestEmail(): bool
    {
        // A single manually-triggered policy fix shouldn't show up in the
        // finance team's daily ledger digest email.
        return false;
    }

    protected function selectPolicies(Carbon $today)
    {
        $identifier = trim((string) $this->argument('policy'));

        $columns = ['id', 'customer_id', 'product_id', 'plan_id', 'premium_freq',
            'created_at', 'updated_at', 'first_premium', 'premium', 'vat',
            'vat_percent', 'policyNumber', 'policyActivatedDate', 'is_sys_act_generated',
            'billingStartDate', 'ori_billingStartDate', 'status'];

        $policy = ctype_digit($identifier)
            ? Policy::where('id', $identifier)->first($columns)
            : Policy::where('policyNumber', $identifier)->first($columns);

        if ($policy === null) {
            throw new \RuntimeException("PolicyLedgerByPolicy: no policy found for '{$identifier}'");
        }

        // DOM/COM (V2) products regenerate their ledger through the V2 flow,
        // not this MIS/legacy + Motor Comp logic — same product gate
        // PolicyLedgerDaily's default selectPolicies() applies.
        if (in_array((int) $policy->product_id, [7, 8], true)) {
            throw new \RuntimeException("PolicyLedgerByPolicy: policy {$policy->policyNumber} (product_id={$policy->product_id}) is a DOM/COM product and isn't supported by this command");
        }

        $this->targetPolicy = $policy;

        $this->info("Processing ledger for policy {$policy->policyNumber} (id={$policy->id})");
        Log::info("PolicyLedgerByPolicy: resolved '{$identifier}' to policy {$policy->policyNumber} (id={$policy->id})");

        return collect([$policy])->chunk(1000);
    }

    protected function selectOrphanPayments()
    {
        return PaymentTransaction::where('policyNumber', $this->targetPolicy->policyNumber)
            ->where('is_ledger', 0)
            ->where('amount', '!=', 1)
            ->where('is_refund', 0)
            ->get();
    }

    protected function selectOrphanRefunds()
    {
        return PaymentTransaction::where('policyNumber', $this->targetPolicy->policyNumber)
            ->where('is_ledger', 0)
            ->where('is_refund', 1)
            ->get();
    }
}
