<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Services\CreditNotes\InvoiceCreditNoteService;
use AlphaDirect\Services\Refunds\RefundWorkflowException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Invoice Credit Note — the V2 replacement for the legacy CR screen
 * (/admin/policy/creditNoteView/{id}), which is unreachable on the V2 backend
 * because the whole /admin/* tree 404s behind BlockV1AdminPanel.
 *
 * Three endpoints, matching the legacy screen's three steps:
 *   GET    …/credit-note            → the screen's data (policy, invoice, any
 *                                     credit note already raised)
 *   POST   …/credit-note/calculate  → the "Calculate" button (read-only)
 *   POST   …/credit-note            → "GENERATE CREDIT NOTE" (posts to the ledger)
 *
 * All arithmetic and every write live in InvoiceCreditNoteService.
 */
class CreditNoteController extends Controller
{
    /** The screen's initial payload for one invoice row. */
    public function show(int $policyId, int $ledgerId): JsonResponse
    {
        try {
            return response()->json((new InvoiceCreditNoteService())->preview($policyId, $ledgerId));
        } catch (RefundWorkflowException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Log::error('creditNote show failed: ' . $e->getMessage(), [
                'policy_id' => $policyId, 'ledger_id' => $ledgerId,
            ]);
            return response()->json(['error' => 'Could not load the credit note screen.'], 500);
        }
    }

    /**
     * Preview the numbers without writing anything — the legacy "Calculate"
     * button, moved server-side so the figures that get posted are the figures
     * the user was shown (the legacy screen computed them in the browser and
     * posted them back as hidden inputs).
     */
    public function calculate(Request $request, int $policyId, int $ledgerId): JsonResponse
    {
        $input = $this->validatedInput($request);

        try {
            return response()->json(
                (new InvoiceCreditNoteService())->calculateFor($policyId, $ledgerId, $input)
            );
        } catch (RefundWorkflowException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Log::error('creditNote calculate failed: ' . $e->getMessage(), [
                'policy_id' => $policyId, 'ledger_id' => $ledgerId,
            ]);
            return response()->json(['error' => 'Could not calculate the credit note.'], 500);
        }
    }

    /** Post the credit note. */
    public function store(Request $request, int $policyId, int $ledgerId): JsonResponse
    {
        $input = $this->validatedInput($request);

        try {
            $result = (new InvoiceCreditNoteService())
                ->post($policyId, $ledgerId, $input, $request->user());
            return response()->json($result, 201);
        } catch (RefundWorkflowException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Log::error('creditNote store failed: ' . $e->getMessage(), [
                'policy_id' => $policyId, 'ledger_id' => $ledgerId,
            ]);
            return response()->json(['error' => 'Could not generate the credit note.'], 500);
        }
    }

    /**
     * Re-date a credit note that is already posted.
     *
     * Every note raised before the posting date became selectable was stamped
     * with now(), so the wrong dates already in the ledger can only be fixed
     * here. No money moves — see InvoiceCreditNoteService::updateDate().
     */
    public function updateDate(Request $request, int $policyId, int $ledgerId): JsonResponse
    {
        $data = $request->validate(['credit_note_date' => 'required|date']);

        try {
            return response()->json((new InvoiceCreditNoteService())
                ->updateDate($policyId, $ledgerId, $data['credit_note_date'], $request->user()));
        } catch (RefundWorkflowException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Log::error('creditNote updateDate failed: ' . $e->getMessage(), [
                'policy_id' => $policyId, 'ledger_id' => $ledgerId,
            ]);
            return response()->json(['error' => 'Could not update the credit note date.'], 500);
        }
    }

    /**
     * The credit note DOCUMENT.
     *
     * Served through the API rather than linked straight at CloudFront so a note
     * whose PDF never reached S3 still opens — the service re-renders it from
     * the stored figures. Streamed inline, same shape as the invoice PDF
     * endpoint, so the browser opens it in a tab instead of downloading it.
     */
    public function pdf(int $policyId, int $creditNoteId)
    {
        try {
            $doc = (new InvoiceCreditNoteService())->pdfFor($policyId, $creditNoteId);

            return response($doc['content'], 200, [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => 'inline; filename="' . $doc['filename'] . '"',
            ]);
        } catch (RefundWorkflowException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Log::error('creditNote pdf failed: ' . $e->getMessage(), [
                'policy_id' => $policyId, 'credit_note_id' => $creditNoteId,
            ]);
            return response()->json(['error' => 'Could not open the credit note document.'], 500);
        }
    }

    /**
     * Only the PERIOD is client-supplied. Earned/unearned are never accepted
     * from the request — the service derives them, which is what closes the
     * legacy hole where the posted amounts were whatever the browser sent.
     */
    private function validatedInput(Request $request): array
    {
        $data = $request->validate([
            'complete_term' => 'nullable|boolean',
            'months'        => 'nullable|integer|min:1|max:120',
            'end_date'      => 'nullable|date',
            // The date the note is RECORDED under (the Statement of Account's
            // Date column). Both the legacy screen and the first V2 cut
            // hardcoded now(), so a note raised for a closed month still
            // printed under today's date. Omitted = today, as before.
            'credit_note_date' => 'nullable|date',
        ]);

        return [
            'complete_term' => (bool) ($data['complete_term'] ?? false),
            'months'        => (int) ($data['months'] ?? 1),
            'end_date'      => $data['end_date'] ?? null,
            'credit_note_date' => $data['credit_note_date'] ?? null,
        ];
    }
}
