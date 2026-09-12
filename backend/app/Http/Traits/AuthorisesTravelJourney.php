<?php

namespace AlphaDirect\Http\Traits;

use AlphaDirect\Services\PublicOtpService;
use AlphaDirect\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * The two gates every Travel Insurance endpoint in the Start journey shares:
 * the selling AGENT must be active and hold the Travel permission, and the
 * state-changing steps must additionally carry the CUSTOMER's OTP session.
 *
 * Extracted from PublicTravelController when the digital proposal form
 * (PublicTravelProposalController) became the journey's second writer. Both
 * controllers gate the same product with the same rules, so the rules live in
 * one place — the journey has already been bitten once by two screens drifting
 * apart on a shared payload key.
 *
 * The agent PIN is never seen here: it is accepted only by
 * /public/travel/verify-agent and downstream calls carry the resolved agent id.
 */
trait AuthorisesTravelJourney
{
    /**
     * Spatie permission that authorises an agent to sell Travel Insurance.
     * Seeded (and granted to the default admin roles) by
     * database/migrations/2026_08_19_000000_seed_travel_insurance_sell_permission.php.
     */
    public const TRAVEL_PERMISSION = 'travel-insurance-sell';

    /** 403 unless `agent_id` resolves to an active, Travel-permitted agent. */
    protected function denyUnlessTravelAgent(Request $request): ?JsonResponse
    {
        $agent = User::where('id', $request->input('agent_id'))->first(['id', 'active']);

        if (!$agent || (int) $agent->active !== 1) {
            return response()->json([
                'ok'      => false,
                'error'   => 'agent_not_authorised',
                'message' => 'This agent is not active. Re-verify your Agent ID and PIN.',
            ], 403);
        }

        if (!$this->hasTravelPermission($agent)) {
            return response()->json([
                'ok'      => false,
                'error'   => 'travel_permission_denied',
                'message' => 'This agent is not authorised to sell Travel Insurance.',
            ], 403);
        }

        return null;
    }

    /**
     * Does this agent hold the Travel Insurance permission (directly or via a
     * role)? Spatie throws when the permission row hasn't been seeded yet, so
     * an un-migrated environment reads as "no permission" (fail closed) rather
     * than a 500.
     */
    protected function hasTravelPermission(User $agent): bool
    {
        try {
            return $agent->hasPermissionTo(self::TRAVEL_PERMISSION);
        } catch (\Throwable $e) {
            Log::warning('public_travel.permission_check_failed', [
                'agent_id' => $agent->id,
                'msg'      => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Validate the customer's OTP Bearer session.
     *
     * @return array|JsonResponse the session row, or a 401
     */
    protected function requireOtpSession(Request $request)
    {
        $auth = $request->header('Authorization', '');
        if (!preg_match('/^Bearer\s+(.+)$/i', $auth, $m)) {
            return response()->json(['ok' => false, 'error' => 'session_required'], 401);
        }

        $session = app(PublicOtpService::class)->validateToken(trim($m[1]));
        if (!$session) {
            return response()->json(['ok' => false, 'error' => 'session_invalid_or_expired'], 401);
        }

        return $session;
    }

    protected function reject(string $error, string $message, int $status = 422): JsonResponse
    {
        return response()->json(['ok' => false, 'error' => $error, 'message' => $message], $status);
    }
}
