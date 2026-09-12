<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Policy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

/**
 * DOM/COM Batch Renew — manual trigger for the three DOM/COM renewal
 * crons from the V2 UI. Super Admin / Admin only.
 *
 * Runs the SAME backend artisan commands the scheduler runs, so every
 * eligibility gate, the CronStatus run tracking and the cron-context log
 * lines behave exactly like a scheduled run — the Cron Logs page picks
 * the lines up under the same cron name.
 *
 * With a policy supplied the command runs with --policy=<ref> (single
 * policy, batch type auto-detected from premium_freq unless explicitly
 * selected). Without a policy it runs the FULL batch for the selected type.
 */
class DomComBatchRenewController extends Controller
{
    /** batch type → artisan command (backend commands support --policy=) */
    private const COMMANDS = [
        'monthly'                => 'DomComMonthlyAutoRenew:cron',
        'quarterly'              => 'DomComQuaterlyAutoRenew:cron',
        'anniversary'            => 'policy:renew-annual',
        // Specialist (non DOM/COM motor) renewals. Separate commands because
        // they clone the specialist coverage tables via
        // newPolicyActionReplaceSpecialist(), which the DOM/COM commands do not.
        'monthly_specialist'     => 'SpecialistMonthlyAutoRenew:cron',
        'quarterly_specialist'   => 'SpecialistQuaterlyAutoRenew:cron',
        'anniversary_specialist' => 'policy:renew-annual-specialist',
        // MANUAL INPUT (premium_freq 6) — specialist only. No cadence to step,
        // so the command repeats the period's own date difference. There is no
        // DOM/COM twin: the "Renewable Policy" opt-in flag it gates on lives
        // only on the specialist coverage tables.
        'manual_specialist'      => 'SpecialistManualAutoRenew:cron',
    ];

    /** DOM/COM premium_freq → batch type */
    private const FREQ_TO_TYPE = [
        1 => 'monthly',
        5 => 'quarterly',
        3 => 'anniversary',
        6 => 'manual',
    ];

    /** DOM/COM motor products — monthly / quarterly / anniversary. */
    private const DOMCOM_PRODUCTS = [7, 8];

    /**
     * Specialist products (Engineering COM/DOM, Specialist COM/DOM, Commercial
     * Liabilities, Marine). Renew monthly / quarterly / on anniversary via the
     * dedicated specialist commands. See AlphaDirect\Support\KycDomComProducts.
     */
    private const SPECIALIST_PRODUCTS = [16, 17, 18, 19, 20, 22];

    public function run(Request $request): JsonResponse
    {
        $user = auth()->user();
        $roleNames = $user ? $user->getRoleNames()->map(fn ($r) => strtolower((string) $r)) : collect();
        if (!$roleNames->contains('admin') && !$roleNames->contains('super admin')) {
            return response()->json(['error' => 'Only Admin / Super Admin can run a batch renew.'], 403);
        }

        $request->validate([
            'policy' => 'nullable|string|max:50',
            'type'   => 'nullable|in:monthly,quarterly,anniversary,monthly_specialist,quarterly_specialist,anniversary_specialist,manual_specialist',
        ]);

        $policyRef = trim((string) $request->input('policy', ''));
        $type      = $request->input('type');
        $policy    = null;

        if ($policyRef !== '') {
            $policy = Policy::where('policyNumber', $policyRef)
                ->orWhere('id', is_numeric($policyRef) ? (int) $policyRef : 0)
                ->first();
            if (!$policy) {
                return response()->json(['error' => "Policy '{$policyRef}' not found."], 404);
            }

            $productId    = (int) $policy->product_id;
            $isDomCom     = in_array($productId, self::DOMCOM_PRODUCTS, true);
            $isSpecialist = in_array($productId, self::SPECIALIST_PRODUCTS, true);

            if (!$isDomCom && !$isSpecialist) {
                return response()->json(['error' => 'Batch renew is for DOM/COM (7/8) or specialist (16,17,18,19,20,22) policies only.'], 422);
            }

            // Determine the cadence (monthly / quarterly / anniversary) — from
            // the explicitly selected type (family suffix stripped) or, when no
            // type is selected, auto-detected from the policy's premium_freq
            // (1=monthly, 5=quarterly, 3=anniversary).
            if ($type) {
                $cadence = str_replace('_specialist', '', $type);
            } else {
                $cadence = self::FREQ_TO_TYPE[(int) $policy->premium_freq] ?? null;
                if (!$cadence) {
                    return response()->json(['error' => "Cannot auto-detect a batch for premium_freq={$policy->premium_freq}. Select the batch type explicitly."], 422);
                }
            }

            // Normalise the cadence to the policy's product family so a
            // specialist policy never runs a DOM/COM command (and vice-versa).
            //   DOM/COM    → monthly / quarterly / anniversary
            //   Specialist → monthly_specialist / quarterly_specialist / anniversary_specialist
            $type = $isSpecialist ? "{$cadence}_specialist" : $cadence;
        }

        if (!$type) {
            return response()->json(['error' => 'Enter a policy or select a batch type.'], 422);
        }

        // MANUAL INPUT exists for specialist products only, so a DOM/COM policy
        // normalised to a bare 'manual' has no command to run. Say so instead of
        // dying on an undefined COMMANDS key.
        if (!isset(self::COMMANDS[$type])) {
            return response()->json(['error' => "No batch renew cron exists for '{$type}'. MANUAL INPUT auto-renew is available on specialist products only."], 422);
        }

        $command = self::COMMANDS[$type];
        $params  = [];
        if ($policy) {
            $params['--policy'] = $policy->policyNumber ?: (string) $policy->id;
        }

        // Tag every log line like the scheduler does so the Cron Logs page
        // can filter this manual run by cron name, and stamp who ran it.
        Log::withContext(['cron' => $command, 'manual_batch_renew_by' => $user->id]);
        Log::info('Manual DOM/COM batch renew started', [
            'type'    => $type,
            'command' => $command,
            'policy'  => $policy->policyNumber ?? null,
            'user'    => $user->email ?? (string) $user->id,
        ]);
        activity('DOM/COM Batch Renew')
            ->causedBy($user)
            ->log("Manual {$type} batch renew" . ($policy ? " for {$policy->policyNumber}" : ' (full batch)'));

        set_time_limit(600); // a full batch can run for several minutes

        try {
            $exit   = Artisan::call($command, $params);
            $output = Artisan::output();

            Log::info('Manual DOM/COM batch renew finished', ['command' => $command, 'exit' => $exit]);

            $label = ucfirst(str_replace('_', ' ', $type));

            return response()->json([
                'message' => $label . ' batch renew finished' . ($policy ? " for {$policy->policyNumber}." : '.'),
                'type'    => $type,
                'command' => $command . ($policy ? " --policy={$params['--policy']}" : ''),
                'exit'    => $exit,
                'output'  => $output,
            ]);
        } catch (\Throwable $e) {
            Log::error('Manual DOM/COM batch renew failed', [
                'command' => $command,
                'error'   => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);
            return response()->json(['error' => 'Batch renew failed: ' . $e->getMessage()], 500);
        }
    }
}
