<?php

namespace AlphaDirect\Listeners;

use OwenIt\Auditing\Events\Auditing;

/**
 * Suppress empty-diff audit rows at the source.
 *
 * OwenIt fires the `Auditing` event (dispatched via Event::until()) just
 * before every audit row is written. Any listener that returns a non-null
 * value halts the dispatch; returning `false` cancels the write entirely.
 *
 * The policy Logs tab was cluttered with rows like "TheftNotes Updated"
 * rendering `[] → []`. Those fire when an Auditable model is re-saved but no
 * audited attribute actually changed — e.g. a note saved with the same/blank
 * value, or the only dirty columns are in the model's $auditExclude
 * (updated_at, pro_rate_premium, endors_flag …). They carry no information,
 * so we drop them here instead of writing a meaningless row.
 *
 * Deliberately narrow: only an `updated` event whose resolved old AND new
 * value sets are BOTH empty is cancelled. `created` (new_values populated),
 * `deleted` (old_values populated) and `restored` always pass through — each
 * records a real state transition even when one side is empty.
 */
class SkipEmptyAudit
{
    public function handle(Auditing $event): ?bool
    {
        try {
            $data = $event->model->toAudit();
        } catch (\Throwable $e) {
            // Never block a legitimate audit because the diff could not be
            // resolved — let OwenIt proceed exactly as it would have.
            return null;
        }

        // Only updates produce the blank [] → [] rows. Leave every other
        // event untouched.
        if (($data['event'] ?? null) !== 'updated') {
            return null;
        }

        $old = $data['old_values'] ?? [];
        $new = $data['new_values'] ?? [];

        // Both sides empty → nothing audited actually changed → drop the row.
        // Returning false halts until() and cancels the audit write.
        if (empty($old) && empty($new)) {
            return false;
        }

        return null;
    }
}
