<?php

namespace AlphaDirect\Services\Refunds;

use AlphaDirect\Models\RefundAccountingEntry;
use AlphaDirect\Models\RefundRequest;
use AlphaDirect\Models\RefundRequestEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * RefundAccountingService — the Finance review-and-post queue.
 *
 * Enqueue: when a RETURN-PREMIUM refund reaches 'posted' (money confirmed
 * out), an entry is PREPARED with defaults (earned = the refunded amount,
 * unearned = 0, effective = paid date) — never auto-posted (CFO decision,
 * 2026-07-26). Plain-reason refunds never enqueue.
 *
 * Post: Finance (refund-accounting-post) reviews — may adjust the
 * earned/unearned split and dates, bounded by the refund amount — and posts,
 * which raises the Credit Note + ledger + sub-ledger reversal via
 * RefundCreditNoteService. Dismiss requires a reason and is audited.
 */
class RefundAccountingService
{
    public function __construct(
        private RefundCreditNoteService $creditNotes,
        private RefundNotifier $notifier,
    ) {}

    /** Idempotent — one entry per refund request. Called from the paid leg. */
    public function enqueueIfReturnPremium(RefundRequest $r): ?RefundAccountingEntry
    {
        if (!$r->isReturnPremium()) {
            return null;
        }
        // Only a LIVE entry blocks a re-enqueue. A voided one is kept for the
        // record and must not suppress the genuine credit note — that is exactly
        // how three real return-premium refunds (RFND-000010, -000019, -000022)
        // came to be silently blocked by rows written directly to PROD.
        $existing = RefundAccountingEntry::where('refund_request_id', $r->id)
            ->whereNull('voided_at')
            ->first();
        if ($existing) {
            return $existing;
        }

        $entry = RefundAccountingEntry::create([
            'refund_request_id' => $r->id,
            'graphite_ref'      => $r->graphite_ref,
            'policy_id'         => $r->policy_id,
            'policy_number'     => $r->policy_number,
            'customer_id'       => $r->customer_id,
            'area'              => $r->area,
            'reason_code'       => $r->reason_code,
            'entry_type'        => 'credit_note',
            'refund_amount'     => $r->refund_amount,
            // Prepared defaults — Finance may adjust at post time.
            'earned_premium'    => $r->refund_amount,
            'unearned_premium'  => 0,
            'effective_date'    => ($r->omni_paid_at ?? now())->format('Y-m-d'),
            'end_date'          => ($r->omni_paid_at ?? now())->format('Y-m-d'),
            'status'            => RefundAccountingEntry::STATUS_PENDING,
        ]);

        RefundRequestEvent::create([
            'refund_request_id' => $r->id,
            'from_status'       => $r->status,
            'to_status'         => $r->status,
            'action'            => 'accounting_enqueued',
            'actor_name'        => 'system',
            'meta'              => ['entry_id' => $entry->id, 'reason_code' => $r->reason_code],
            'created_at'        => now(),
        ]);

        $this->notifier->accountingEnqueued($r, $entry);
        Log::info('Refund engine: credit-note entry queued for Finance review', [
            'graphite_ref' => $r->graphite_ref, 'entry_id' => $entry->id,
        ]);
        return $entry;
    }

    /**
     * Finance posts the reviewed entry → Credit Note + ledger reversal.
     * @param array{earned_premium?:mixed, unearned_premium?:mixed, effective_date?:string, end_date?:string} $overrides
     */
    public function post(RefundAccountingEntry $entry, $user, array $overrides = []): RefundAccountingEntry
    {
        // Claim the entry under a row lock BEFORE doing any work. The old
        // check-then-post read a stale model with no lock and only marked the
        // entry posted after the credit note had already committed — so a
        // double-click or two Finance users posting together produced TWO credit
        // notes and two ledger debits (a P40,000 refund reversing P80,000 of
        // premium). The lock + re-read makes the second caller lose the race.
        $entry = DB::transaction(function () use ($entry) {
            $fresh = RefundAccountingEntry::whereKey($entry->getKey())->lockForUpdate()->first();
            if (!$fresh) {
                throw new RefundWorkflowException('Accounting entry not found.');
            }
            if ($fresh->status !== RefundAccountingEntry::STATUS_PENDING) {
                throw new RefundWorkflowException(
                    "Entry is {$fresh->status} — only a pending entry can be posted (it may have just been posted by someone else).");
            }
            // Mark it claimed within the lock so a concurrent caller sees a
            // non-pending row and stops.
            $fresh->status = RefundAccountingEntry::STATUS_POSTING;
            $fresh->save();
            return $fresh;
        });

        foreach (['earned_premium', 'unearned_premium'] as $f) {
            if (array_key_exists($f, $overrides) && $overrides[$f] !== null && $overrides[$f] !== '') {
                if (!is_numeric($overrides[$f]) || (float) $overrides[$f] < 0) {
                    throw new RefundWorkflowException("{$f} must be a non-negative number.");
                }
                $entry->{$f} = round((float) $overrides[$f], 2);
            }
        }
        foreach (['effective_date', 'end_date'] as $f) {
            if (!empty($overrides[$f])) {
                $entry->{$f} = $overrides[$f];
            }
        }

        try {
            $result = $this->creditNotes->post($entry, $user);
        } catch (\Throwable $e) {
            // Release the claim so Finance can legitimately retry after fixing
            // whatever the posting complained about (bounds, missing policy).
            $entry->status = RefundAccountingEntry::STATUS_PENDING;
            $entry->save();
            throw $e;
        }

        $entry->status         = RefundAccountingEntry::STATUS_POSTED;
        $entry->credit_note_no = $result['credit_note_no'];
        $entry->credit_note_id = $result['credit_note_id'];
        $entry->posted_by      = $user?->id;
        $entry->posted_at      = now();
        $entry->save();

        RefundRequestEvent::create([
            'refund_request_id' => $entry->refund_request_id,
            'action'            => 'accounting_posted',
            'actor_id'          => $user?->id,
            'actor_name'        => $user?->name ?? $user?->email,
            'meta'              => [
                'credit_note_no'   => $result['credit_note_no'],
                'earned_premium'   => (float) $entry->earned_premium,
                'unearned_premium' => (float) $entry->unearned_premium,
            ],
            'created_at'        => now(),
        ]);
        return $entry;
    }

    public function dismiss(RefundAccountingEntry $entry, $user, string $reason): RefundAccountingEntry
    {
        if ($entry->status !== RefundAccountingEntry::STATUS_PENDING) {
            throw new RefundWorkflowException("Entry is {$entry->status} — only a pending entry can be dismissed.");
        }
        $reason = trim($reason);
        if ($reason === '') {
            throw new RefundWorkflowException('A dismissal reason is required.');
        }

        $entry->status         = RefundAccountingEntry::STATUS_DISMISSED;
        $entry->dismissed_by   = $user?->id;
        $entry->dismissed_at   = now();
        $entry->dismiss_reason = mb_substr($reason, 0, 500);
        $entry->save();

        RefundRequestEvent::create([
            'refund_request_id' => $entry->refund_request_id,
            'action'            => 'accounting_dismissed',
            'actor_id'          => $user?->id,
            'actor_name'        => $user?->name ?? $user?->email,
            'note'              => $reason,
            'created_at'        => now(),
        ]);
        return $entry;
    }

    /**
     * Void an accounting entry that should never have existed.
     *
     * NOT the same as dismiss(). Dismiss is Finance's decision on a real entry it
     * has reviewed, and only applies before posting. Void says the entry is not
     * evidence of anything — for rows that no code path could have produced.
     *
     * The row is NEVER deleted. It is the only trace of how it got there. What
     * the void does is release the refund's slot (void_marker moves from 0 to the
     * row's own id) so enqueueIfReturnPremium() can raise the credit note that
     * the bad row was silently swallowing.
     *
     * Deliberately does NOT reverse anything in the ledger. These entries never
     * reached it — credit_note_id is null on every one. An entry that DID post a
     * real credit note must be corrected by a reversing document, not voided.
     */
    public function void(RefundAccountingEntry $entry, $user, string $reason): RefundAccountingEntry
    {
        if ($entry->voided_at !== null) {
            throw new RefundWorkflowException('This entry is already voided.', 409);
        }
        if (!empty($entry->credit_note_id)) {
            throw new RefundWorkflowException(
                'This entry raised credit note ' . ($entry->credit_note_no ?: '(unnumbered)')
                . ', so it cannot be voided — a posted credit note must be corrected by a '
                . 'reversing document, not erased.', 409);
        }
        $reason = trim($reason);
        if (mb_strlen($reason) < 10) {
            throw new RefundWorkflowException(
                'Please record why this entry is not valid (at least 10 characters). It stays on '
                . 'file and this note is the only explanation of it.');
        }

        $entry->status      = RefundAccountingEntry::STATUS_VOIDED;
        $entry->voided_by   = $user?->id;
        $entry->voided_at   = now();
        $entry->void_reason = mb_substr($reason, 0, 500);
        $entry->void_marker = $entry->id;   // releases the unique slot
        $entry->save();

        try {
            activity('refund_engine')
                ->causedBy($user)
                ->withProperties([
                    'graphite_ref'      => $entry->graphite_ref,
                    'refund_request_id' => $entry->refund_request_id,
                    'amount'            => (float) $entry->refund_amount,
                    'previous_status'   => RefundAccountingEntry::STATUS_POSTED,
                    'reason'            => $reason,
                ])
                ->log('Refund accounting entry voided');
        } catch (\Throwable $e) {
            Log::warning('void activity log failed: ' . $e->getMessage());
        }

        return $entry;
    }
}
