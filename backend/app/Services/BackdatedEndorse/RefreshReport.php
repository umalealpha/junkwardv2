<?php

namespace AlphaDirect\Services\BackdatedEndorse;

/**
 * Plain value object returned by BackdatedEndorseRefresher::refresh() and
 * dryRun(). Carries enough detail for both UI preview (Livewire component)
 * and audit / debugging via Laravel log.
 *
 * Counters are aggregate; per-action / per-row detail lives in `decisions`
 * for the Livewire preview. `errors` collects non-fatal problems (e.g. one
 * future RENEW failed invoice update — others still processed).
 */
class RefreshReport
{
    /** Was this a dry-run (no DB writes)? */
    public bool $dryRun = false;

    /** Source backdated endorse action_id. */
    public ?int $sourceActionId = null;

    /** Target future actions processed (count). */
    public int $targetActionsProcessed = 0;

    /** Counts per future-batch type. */
    public int $endorsesUpdated = 0;
    public int $renewsUpdated   = 0;

    /** Field-level totals. */
    public int $fieldsUpdated         = 0;
    public int $fieldsSkippedProtected = 0;

    /** Rows skipped because the future batch had soft-deleted (cancelled) them. */
    public int $cancelledRowsSkipped = 0;

    /** Ledger adjustments written (live mode only). */
    public int $ledgerAdjustmentsWritten = 0;

    /** Sum of |delta_amount| across all ledger adjustments. */
    public float $totalPremiumDelta = 0.0;

    /** Idempotency: was this refresh already applied (matching hash)? */
    public bool $alreadyApplied = false;

    /** Per-action / per-row decisions for UI preview. */
    public array $decisions = [];

    /** Non-fatal errors encountered (rows or actions that failed). */
    public array $errors = [];

    public function pushDecision(array $row): void
    {
        $this->decisions[] = $row;
    }

    public function pushError(string $msg, array $ctx = []): void
    {
        $this->errors[] = ['message' => $msg, 'context' => $ctx];
    }

    public function toArray(): array
    {
        return [
            'dry_run'                    => $this->dryRun,
            'source_action_id'           => $this->sourceActionId,
            'target_actions_processed'   => $this->targetActionsProcessed,
            'endorses_updated'           => $this->endorsesUpdated,
            'renews_updated'             => $this->renewsUpdated,
            'fields_updated'             => $this->fieldsUpdated,
            'fields_skipped_protected'   => $this->fieldsSkippedProtected,
            'cancelled_rows_skipped'     => $this->cancelledRowsSkipped,
            'ledger_adjustments_written' => $this->ledgerAdjustmentsWritten,
            'total_premium_delta'        => $this->totalPremiumDelta,
            'already_applied'            => $this->alreadyApplied,
            'decisions'                  => $this->decisions,
            'errors'                     => $this->errors,
        ];
    }
}
