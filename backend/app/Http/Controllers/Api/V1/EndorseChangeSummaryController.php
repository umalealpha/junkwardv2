<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Http\Livewire\Policy\EndorseChangeSummary;
use AlphaDirect\Models\PolicyAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * READ-ONLY Endorse / Cancel change-summary for the React Policy Actions panel.
 *
 * Thin API wrapper that reuses the SAME diagnostic logic as the Livewire
 * EndorseChangeSummary popup (backend edit-wizard). The React frontend and the
 * legacy Livewire UI therefore render identical rows + reconciliation math and
 * can never drift. Writes nothing — every value is read verbatim from what the
 * Rate engine already stamped.
 */
class EndorseChangeSummaryController extends Controller
{
    public function show(Request $request, $id, $actionId): JsonResponse
    {
        $action = PolicyAction::find($actionId);
        if (!$action || (int) $action->policy_id !== (int) $id) {
            return response()->json([
                'rows'  => [],
                'math'  => [],
                'error' => 'Action not found for this policy.',
            ], 404);
        }

        // Reuse the Livewire component's load() verbatim — same rows + math the
        // popup renders. No recomputation, no duplicated logic.
        $c = new EndorseChangeSummary();
        $c->policyId = (int) $id;
        $c->termId   = (int) ($action->term_id ?? 0);
        $c->actionId = (int) $actionId;

        try {
            $c->load();
        } catch (\Throwable $e) {
            Log::warning('EndorseChangeSummary API failed', [
                'policy' => $id, 'action' => $actionId, 'error' => $e->getMessage(),
            ]);
            return response()->json([
                'rows'  => [],
                'math'  => [],
                'error' => 'Unable to build the change summary for this action.',
            ]);
        }

        return response()->json([
            'rows'  => $c->rows,
            'math'  => $c->math,
            'error' => $c->error,
        ]);
    }
}
