<?php

namespace AlphaDirect\Services\CreditNotes;

use AlphaDirect\Services\Refunds\RefundWorkflowException;
use Illuminate\Support\Facades\DB;

/**
 * Sequential CR###### allocation, shared by every credit-note writer.
 *
 * Lifted verbatim out of RefundCreditNoteService so the invoice flow gets the
 * same race-safe generator instead of a second copy. The legacy
 * read-max-then-increment in Admin\PolicyController::creditNoteStatement
 * (:2038-2042) races: PROD already carries duplicates (CR000385 appears three
 * times), and a duplicate number means two policies share one trans_ref AND
 * the second PDF overwrites the first in S3.
 */
trait AllocatesCreditNoteNumber
{
    /**
     * Next CR number, allocated safely. Takes the highest existing number under
     * a row lock inside its own short transaction, then verifies uniqueness;
     * on a collision (another writer, or the pre-existing duplicates already in
     * PROD) it walks forward until it finds a free number.
     */
    protected function nextCreditNoteNo(): string
    {
        return DB::transaction(function () {
            $row = DB::table('credit_note')
                ->select('credit_note_no')
                ->orderByRaw('CAST(SUBSTRING(credit_note_no, 3) AS UNSIGNED) DESC')
                ->lockForUpdate()
                ->first();
            $next = $row === null ? 1 : ((int) substr((string) $row->credit_note_no, 2)) + 1;
            for ($i = 0; $i < 50; $i++, $next++) {
                $candidate = 'CR' . str_pad((string) $next, 6, '0', STR_PAD_LEFT);
                if (!DB::table('credit_note')->where('credit_note_no', $candidate)->exists()) {
                    return $candidate;
                }
            }
            throw new RefundWorkflowException('Could not allocate a unique credit note number — please retry.');
        });
    }
}
