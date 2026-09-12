<?php

namespace AlphaDirect\Observers;

use AlphaDirect\Policy;
use AlphaDirect\Services\CacheService;
use AlphaDirect\Services\CustomerNotificationService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PolicyObserver
{
    public function saved(Policy $policy): void
    {
        CacheService::forgetPolicy($policy->id);
        // forgetPolicy() only clears the by-id key (policy_{id}); the
        // rememberPolicyByNumber readers (Policy::findByNumber et al.) cache
        // under policy_number_{num}, so a plan/premium change (e.g. a TP→TP
        // Gold upgrade) would otherwise keep serving the stale snapshot.
        if (!empty($policy->policyNumber)) {
            Cache::forget("policy_number_{$policy->policyNumber}");
        }
        Cache::forget('dashboard_policy_counts');
    }

    public function updated(Policy $policy): void
    {
        if ($policy->isDirty('status')) {
            $newStatus = (int) $policy->status;
            $policyNumber = $policy->policyNumber ?? '';

            // Auto-cancel RealPay contracts when policy is cancelled
            if ($newStatus === 2) {
                $this->cancelRealPayContracts($policy);
            }

            // Customer notifications on status change
            try {
                $notifier = new CustomerNotificationService();
                if ($newStatus === 1) {
                    $notifier->notifyPolicyActivated($policy->id, $policyNumber);
                } elseif ($newStatus === 2) {
                    $notifier->notifyPolicyCancelled($policy->id, $policyNumber);
                }
            } catch (\Exception $e) {
                Log::error("Policy notification failed: {$e->getMessage()}", ['policy_id' => $policy->id]);
            }
        }
    }

    public function deleted(Policy $policy): void
    {
        CacheService::forgetPolicy($policy->id);
        if (!empty($policy->policyNumber)) {
            Cache::forget("policy_number_{$policy->policyNumber}");
        }
        Cache::forget('dashboard_policy_counts');
    }

    /**
     * When a policy is cancelled, mark all active RealPay contracts as cancelled.
     * This prevents the "cancelled_but_collecting" anomaly from occurring.
     */
    private function cancelRealPayContracts(Policy $policy): void
    {
        try {
            $contracts = DB::table('realpay_client_contracts')
                ->where('policy_id', $policy->id)
                ->where('status', '1')
                ->get(['id', 'contract_number', 'client_number']);

            if ($contracts->isEmpty()) return;

            // Mark contracts as cancelled locally
            DB::connection('mysql_write')->table('realpay_client_contracts')
                ->whereIn('id', $contracts->pluck('id'))
                ->update(['status' => '0', 'updated_at' => now()]);

            // Log for audit trail
            foreach ($contracts as $c) {
                Log::info("Auto-cancelled RealPay contract {$c->contract_number} (client {$c->client_number}) — policy {$policy->policyNumber} cancelled");
            }

            // Auto-resolve any open cancelled_but_collecting anomaly for this policy
            DB::connection('mysql_write')->table('reconciliation_anomalies')
                ->where('policy_id', $policy->id)
                ->where('anomaly_type', 'cancelled_but_collecting')
                ->where('status', 'open')
                ->update([
                    'status' => 'resolved',
                    'resolved_at' => now(),
                    'resolution_notes' => 'Auto-resolved: RealPay contract cancelled via PolicyObserver',
                    'updated_at' => now(),
                ]);
        } catch (\Exception $e) {
            Log::error("Failed to auto-cancel RealPay for policy {$policy->id}: " . $e->getMessage());
        }
    }
}
