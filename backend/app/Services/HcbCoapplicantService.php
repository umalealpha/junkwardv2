<?php

namespace AlphaDirect\Services;

use AlphaDirect\CustomerBanking;
use AlphaDirect\Exceptions\HcbCoapplicantException;
use AlphaDirect\HospitalCashbackCoapplicants;
use AlphaDirect\Http\Controllers\Admin\DocumentController;
use AlphaDirect\Policy;
use AlphaDirect\ScheduleTransaction;
use AlphaDirect\UpdateRealpayClientContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Single write path for Hospital Cashback Insurance (product_id=9)
 * co-applicants. Used by both the customer-facing (START) and staff admin
 * (Graphite) endpoints, plus the direct-creation flow, so there is only
 * ever one place that writes hospital_Cashback_coapplicants and one place
 * that recomputes premium from it — the exact two-divergent-paths problem
 * that left co-applicant creation and display disconnected in the first
 * place (see docs/HCB-001-coapplicant-findings.md).
 */
class HcbCoapplicantService
{
    public const PRODUCT_ID = 9;

    public static function add(Policy $policy, array $data, ?Model $actor, string $actorLabel, bool $recalculate = true): HospitalCashbackCoapplicants
    {
        self::assertHcb($policy);
        $relation = self::normaliseRelation($data['relation'] ?? null);
        self::assertWithinLimits($policy->id, $relation, null);

        $row = new HospitalCashbackCoapplicants();
        $row->policy_id   = $policy->id;
        $row->relation    = $relation;
        $row->first_name  = $data['first_name']  ?? null;
        $row->middle_name = $data['middle_name'] ?? null;
        $row->last_name   = $data['last_name']   ?? null;
        $row->gender      = self::genderCode($data['gender'] ?? null);
        $row->dob         = self::normaliseDate($data['dob'] ?? null);
        $row->omang       = $data['omang']    ?? null;
        $row->passport    = $data['passport'] ?? null;
        $row->save();

        if ($recalculate) {
            self::recalculateAndApply($policy, $actor, $actorLabel, "Co-applicant added ({$relation})");
        }

        return $row;
    }

    public static function update(Policy $policy, int $coapplicantId, array $data, ?Model $actor, string $actorLabel): HospitalCashbackCoapplicants
    {
        self::assertHcb($policy);
        $row = HospitalCashbackCoapplicants::where('id', $coapplicantId)->where('policy_id', $policy->id)->first();
        if (!$row) {
            throw new HcbCoapplicantException('coapplicant_not_found', 404);
        }

        $relation = array_key_exists('relation', $data) ? self::normaliseRelation($data['relation']) : $row->relation;
        if ($relation !== $row->relation) {
            self::assertWithinLimits($policy->id, $relation, $row->id);
        }
        $row->relation = $relation;

        if (array_key_exists('first_name', $data))  $row->first_name  = $data['first_name'];
        if (array_key_exists('middle_name', $data)) $row->middle_name = $data['middle_name'];
        if (array_key_exists('last_name', $data))   $row->last_name   = $data['last_name'];
        if (array_key_exists('gender', $data))      $row->gender      = self::genderCode($data['gender']);
        if (array_key_exists('dob', $data))         $row->dob         = self::normaliseDate($data['dob']);
        if (array_key_exists('omang', $data))        $row->omang    = $data['omang'];
        if (array_key_exists('passport', $data))     $row->passport = $data['passport'];
        $row->save();

        self::recalculateAndApply($policy, $actor, $actorLabel, 'Co-applicant updated');

        return $row;
    }

    public static function delete(Policy $policy, int $coapplicantId, ?Model $actor, string $actorLabel): void
    {
        self::assertHcb($policy);
        $row = HospitalCashbackCoapplicants::where('id', $coapplicantId)->where('policy_id', $policy->id)->first();
        if (!$row) {
            throw new HcbCoapplicantException('coapplicant_not_found', 404);
        }
        $relation = $row->relation;
        $row->delete();

        self::recalculateAndApply($policy, $actor, $actorLabel, "Co-applicant removed ({$relation})");
    }

    /**
     * Recompute premium from the current spouse/child counts and apply it
     * everywhere it needs to land: policies row, schedule PDF, RealPay
     * debit amendment (best-effort), audit trail.
     */
    public static function recalculateAndApply(Policy $policy, ?Model $actor, string $actorLabel, string $logMessage): float
    {
        $spouseCount = HospitalCashbackCoapplicants::where('policy_id', $policy->id)->where('relation', 'spouse')->count();
        $childCount  = HospitalCashbackCoapplicants::where('policy_id', $policy->id)->where('relation', 'child')->count();
        $breakdown   = HcbPremiumLadder::compute($spouseCount, $childCount);
        $newPremium  = (float) $breakdown['total_premium'];
        $oldPremium  = (float) $policy->premium;

        $policy->premium        = $newPremium;
        $policy->annual_premium = $newPremium * 12;
        $policy->first_premium  = $newPremium;
        $policy->has_member     = ($spouseCount + $childCount) > 0 ? 1 : 0;
        $policy->save();

        // Best-effort — a PDF failure must never undo the premium change
        // that already committed above.
        try {
            (new DocumentController())->generatePolicyDocument($policy->id);
        } catch (\Throwable $e) {
            Log::error('HcbCoapplicantService: schedule regeneration failed', [
                'policy' => $policy->id, 'message' => $e->getMessage(),
            ]);
        }

        self::syncRealpayContractAmount($policy, $newPremium);
        self::syncDpoScheduledAmount($policy, $newPremium);

        activity('Policy HCB Co-applicant')
            ->performedOn($policy)
            ->causedBy($actor)
            ->log(sprintf(
                '%s by %s — premium P%s -> P%s',
                $logMessage,
                $actorLabel,
                number_format($oldPremium, 2),
                number_format($newPremium, 2)
            ));

        return $newPremium;
    }

    /**
     * If the policy bills via RealPay, queue a debit-order amendment so the
     * recurring collection amount matches the new premium. Mirrors the
     * existing manual call site (RealPayController::logUpdateDataRealpay) —
     * a row with status=0 is picked up by RealPayController::updateContract's
     * cron and pushed to RealPay's "maintain contract" API. Best-effort: the
     * premium itself is already updated on the policy and will be billed
     * correctly even if this queueing step fails.
     */
    private static function syncRealpayContractAmount(Policy $policy, float $newPremium): void
    {
        if (strtolower((string) $policy->billing) !== 'realpay') return;

        try {
            $contract = DB::table('realpay_client_contracts')
                ->where('policy_id', $policy->id)
                ->orderByDesc('id')
                ->first(['client_number', 'contract_number']);
            if (!$contract) return;

            $update = new UpdateRealpayClientContract();
            $update->product         = $policy->product_id;
            $update->client_number   = $contract->client_number;
            $update->contract_number = $contract->contract_number;
            $update->premium         = $newPremium;
            $update->action          = 1; // Update Premium
            $update->status          = 0; // Pending — RealPayController::updateContract cron picks this up
            $update->save();
        } catch (\Throwable $e) {
            Log::error('HcbCoapplicantService: RealPay contract sync failed', [
                'policy' => $policy->id, 'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * If the policy bills via DPO, re-price its FUTURE (uncollected) scheduled
     * debits so the next recurring collection matches the new premium. Only
     * status = 0 rows (payment not yet initiated) are touched — already-charged
     * (2), in-progress/locked (1/9), failed-retry (3) and canceled (4) rows are
     * left as-is. Best-effort: the premium is already committed on the policy;
     * a failure here must not roll that back. The live `dpo:pay` cron re-mints
     * its DPO token from this column each run, so this is sufficient to change
     * the amount DPO actually debits. Mirrors MobileAppController::handleDpo.
     */
    private static function syncDpoScheduledAmount(Policy $policy, float $newPremium): void
    {
        // Authoritative billing source used by the existing mobile dispatcher
        // (MobileAppController::updatePolicyPaymentContract): latest
        // CustomerBanking row, falling back to policies.billing.
        $billing = strtolower((string) (
            CustomerBanking::where('policy_id', $policy->id)->orderByDesc('id')->value('billing')
            ?: $policy->billing
        ));
        if ($billing !== 'dpo') return;

        try {
            $updated = ScheduleTransaction::where('policy_number', $policy->policyNumber)
                ->where('status', 0)
                ->update(['premium' => $newPremium]);

            if ($updated === 0) { // fallback: some rows key on policy_id only
                $updated = ScheduleTransaction::where('policy_id', $policy->id)
                    ->where('status', 0)
                    ->update(['premium' => $newPremium]);
            }

            Log::info('HcbCoapplicantService: DPO schedule re-priced', [
                'policy' => $policy->id, 'rows' => $updated, 'premium' => $newPremium,
            ]);
        } catch (\Throwable $e) {
            Log::error('HcbCoapplicantService: DPO schedule sync failed', [
                'policy' => $policy->id, 'message' => $e->getMessage(),
            ]);
        }
    }

    private static function assertHcb(Policy $policy): void
    {
        if ((int) $policy->product_id !== self::PRODUCT_ID) {
            throw new HcbCoapplicantException('not_hospital_cashback_policy', 422);
        }
    }

    private static function assertWithinLimits(int $policyId, string $relation, ?int $excludeId): void
    {
        $query = HospitalCashbackCoapplicants::where('policy_id', $policyId)->where('relation', $relation);
        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }
        $count = $query->count();
        $max   = $relation === 'spouse' ? HcbPremiumLadder::MAX_SPOUSE : HcbPremiumLadder::MAX_CHILDREN;
        if ($count >= $max) {
            throw new HcbCoapplicantException($relation === 'spouse' ? 'max_spouse_exceeded' : 'max_children_exceeded', 422);
        }
    }

    /** Accepts "spouse"/"child" or the bundle path's legacy numeric (2=spouse, 3=child). */
    private static function normaliseRelation($value): string
    {
        if ($value === null) {
            throw new HcbCoapplicantException('invalid_relation', 422);
        }
        if (is_numeric($value)) {
            return (int) $value === 2 ? 'spouse' : 'child';
        }
        $v = strtolower((string) $value);
        if (!in_array($v, ['spouse', 'child'], true)) {
            throw new HcbCoapplicantException('invalid_relation', 422);
        }
        return $v;
    }

    /** customer_profile / hospital_Cashback_coapplicants gender is int: 1=Male, 0=Female. */
    private static function genderCode($value): ?int
    {
        if ($value === null || $value === '') return null;
        if (is_numeric($value)) return (int) $value === 1 ? 1 : 0;
        return match (strtoupper(trim((string) $value))) {
            'M', 'MALE'   => 1,
            'F', 'FEMALE' => 0,
            default       => null,
        };
    }

    /** Accepts Y-m-d or d/m/Y, returns Y-m-d or null. */
    private static function normaliseDate($value): ?string
    {
        if (empty($value)) return null;
        try {
            return str_contains((string) $value, '/')
                ? Carbon::createFromFormat('d/m/Y', $value)->format('Y-m-d')
                : Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }
}
