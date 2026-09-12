<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Services\Claims\PremiumConfirmationService;
use AlphaDirect\Services\Claims\PremiumStatusEngine;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The Premium Confirmation Tracker, plus the two actions Finance actually takes.
 *
 * The tracker exists to answer one question the CFO asked: is Finance releasing
 * premium confirmations within 24 hours? So `overdue` and `hoursOpen` are
 * computed here from the stored clock rather than left to a screen to work out —
 * two screens deriving the same figure is how they end up disagreeing.
 */
class PremiumConfirmationController extends Controller
{
    public function __construct(
        private PremiumConfirmationService $service,
        private PremiumStatusEngine $engine,
    ) {
    }

    /** GET — the tracker. Open items first, oldest first. */
    public function index(Request $request): JsonResponse
    {
        if (!PremiumConfirmationService::enabled()) {
            return response()->json(['message' => 'Premium confirmation is not switched on.'], 404);
        }

        $validated = $request->validate([
            'status'   => 'nullable|string|max:30',
            'light'    => 'nullable|in:green,amber,red',
            'overdue'  => 'nullable|boolean',
            'page'     => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|in:15,25,50',
        ]);

        $perPage = (int) ($validated['per_page'] ?? 25);
        $now     = Carbon::now();

        $q = DB::table('claim_premium_confirmations as c')
            ->leftJoin('claims', 'claims.id', '=', 'c.claim_id')
            ->select([
                'c.id', 'c.claim_id', 'c.status', 'c.light', 'c.premium_status',
                'c.balance', 'c.premium', 'c.premiums_outstanding', 'c.unpaid_from',
                'c.last_payment_date', 'c.settlement_hold', 'c.raised_at', 'c.due_at',
                'c.released_at', 'c.released_by', 'c.finance_comment',
                'c.collection_drafted_at', 'c.collection_sent_at',
                'claims.claim_number', 'claims.claim_type',
            ]);

        if (!empty($validated['status'])) {
            $q->where('c.status', $validated['status']);
        }
        if (!empty($validated['light'])) {
            $q->where('c.light', $validated['light']);
        }
        if (!empty($validated['overdue'])) {
            $q->whereNull('c.released_at')->where('c.due_at', '<', $now);
        }

        $page  = (int) ($validated['page'] ?? 1);
        $total = (clone $q)->count();
        $rows  = $q->orderByRaw('c.released_at IS NOT NULL')  // open first
            ->orderBy('c.due_at')
            ->forPage($page, $perPage)
            ->get();

        $data = $rows->map(function ($r) use ($now) {
            $raised = $r->raised_at ? Carbon::parse($r->raised_at) : null;
            $end    = $r->released_at ? Carbon::parse($r->released_at) : $now;
            $due    = $r->due_at ? Carbon::parse($r->due_at) : null;

            return (array) $r + [
                // Calendar hours, by CFO decision. A breach that lands on a
                // weekend or a public holiday is LABELLED rather than excluded,
                // so "Finance missed 40 this month" stays readable.
                'hoursOpen'      => $raised ? round($raised->floatDiffInHours($end), 1) : null,
                'overdue'        => $due && !$r->released_at && $now->greaterThan($due),
                'breachedOnRest' => $due && !$r->released_at && $now->greaterThan($due)
                    ? $due->isWeekend()
                    : false,
            ];
        });

        $openQ = DB::table('claim_premium_confirmations')->whereNull('released_at');

        return response()->json([
            'data' => $data,
            'meta' => [
                'total'        => $total,
                'per_page'     => $perPage,
                'current_page' => $page,
                'last_page'    => (int) ceil($total / max($perPage, 1)),
            ],
            'summary' => [
                'open'          => (clone $openQ)->count(),
                'overdue'       => (clone $openQ)->where('due_at', '<', $now)->count(),
                'autoReleased'  => DB::table('claim_premium_confirmations')->where('status', 'auto_released')->count(),
                'onHold'        => DB::table('claim_premium_confirmations')->where('settlement_hold', 1)->whereNull('released_at')->count(),
                'slaHours'      => PremiumConfirmationService::SLA_HOURS,
            ],
        ]);
    }

    /** GET — the confirmation on one claim, for the claim screen. */
    public function forClaim(int $claimId): JsonResponse
    {
        if (!PremiumConfirmationService::enabled()) {
            return response()->json(['message' => 'Premium confirmation is not switched on.'], 404);
        }

        $row = DB::table('claim_premium_confirmations')
            ->where('claim_id', $claimId)->orderByDesc('id')->first();

        // No row yet (claim predates the feature, or was registered while it was
        // off): show what the ledger says right now, clearly marked as a live
        // look rather than something Finance has signed.
        if (!$row) {
            return response()->json(['data' => [
                'exists'     => false,
                'assessment' => $this->engine->assess($claimId),
            ]]);
        }

        return response()->json(['data' => [
            'exists'     => true,
            'confirmation' => $row,
            'assessment' => json_decode($row->assessment ?? 'null', true),
        ]]);
    }

    /** POST — Finance confirms, or queries it back. */
    public function record(Request $request, int $id): JsonResponse
    {
        if (!PremiumConfirmationService::enabled()) {
            return response()->json(['message' => 'Premium confirmation is not switched on.'], 404);
        }

        $validated = $request->validate([
            'decision' => 'required|in:confirmed,queried',
            'comment'  => 'nullable|string|max:2000',
        ]);

        $actor = optional($request->user())->email ?: 'finance';

        $result = $this->service->record($id, $validated['decision'], (string) ($validated['comment'] ?? ''), $actor);

        if (empty($result['ok'])) {
            $messages = [
                'unknown_decision' => 'Choose confirm or query.',
                'comment_required' => 'Please say what needs checking.',
                'not_found'        => 'That confirmation no longer exists.',
                'already_closed'   => 'That one has already been signed off.',
            ];
            $reason = $result['reason'] ?? 'not_found';

            return response()->json(['message' => $messages[$reason] ?? 'Could not save it.'],
                $reason === 'not_found' ? 404 : 422);
        }

        return response()->json(['data' => $result]);
    }

    /** POST — draft the collection letter. Returns the draft; sends nothing. */
    public function draftCollection(int $id): JsonResponse
    {
        if (!PremiumConfirmationService::enabled()) {
            return response()->json(['message' => 'Premium confirmation is not switched on.'], 404);
        }

        $result = $this->service->draftCollection($id);
        if (empty($result['ok'])) {
            return response()->json(['message' => 'That confirmation no longer exists.'], 404);
        }

        return response()->json(['data' => $result]);
    }
}
