<?php

namespace AlphaDirect\Services\Refunds;

use AlphaDirect\Models\RefundAccountingEntry;
use AlphaDirect\Models\RefundRequest;
use AlphaDirect\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * RefundNotifier — in-app bell notifications for Customer Refund Engine
 * state changes, written to the same mysql_system.notifications table the
 * header bell reads (NotificationController::index).
 *
 * Recipient resolution is permission-based (Spatie `permission()` scope, which
 * includes grants via roles), always intersected with the request's AREA
 * permission — MIS events never notify D&C staff and vice-versa. Best-effort:
 * a notification failure never breaks a workflow transition.
 */
class RefundNotifier
{
    private const MAX_RECIPIENTS = 50;

    public function submitted(RefundRequest $r): void
    {
        $this->toPermission($r, 'refund-review',
            'Refund submitted', "{$r->graphite_ref} · {$r->policy_number} · P" . number_format((float) $r->refund_amount, 2)
            . ($r->after_cutoff ? ' (after 15:00 — next business day)' : ''));
    }

    public function rejected(RefundRequest $r): void
    {
        $this->toUsers($r, array_filter([$r->created_by]),
            'Refund rejected — fix and resubmit', "{$r->graphite_ref}: {$r->rejected_reason}");
    }

    public function escalated(RefundRequest $r): void
    {
        $this->toPermission($r, 'refund-approve',
            'Refund escalated', "{$r->graphite_ref} · {$r->policy_number}: {$r->escalated_reason}");
    }

    public function readyForApproval(RefundRequest $r): void
    {
        $this->toPermission($r, 'refund-approve',
            'Refund under review', "{$r->graphite_ref} · {$r->policy_number} · P" . number_format((float) $r->refund_amount, 2));
    }

    public function readyForSecondApproval(RefundRequest $r): void
    {
        $this->toPermission($r, 'refund-approve',
            'Refund needs a 2nd approver (>P5,000)', "{$r->graphite_ref} · {$r->policy_number} · P" . number_format((float) $r->refund_amount, 2)
            . ' — first approval recorded; a different approver must confirm.');
    }

    public function cfoPending(RefundRequest $r): void
    {
        $this->toPermission($r, 'refund-cfo-approve',
            'Refund needs CFO approval (>P50k)', "{$r->graphite_ref} · {$r->policy_number} · P" . number_format((float) $r->refund_amount, 2),
            skipAreaFilter: true);
    }

    public function approved(RefundRequest $r): void
    {
        $this->toUsers($r, array_filter([$r->created_by]),
            'Refund approved', "{$r->graphite_ref} is approved and queued for the Omni money leg.");
    }

    public function paid(RefundRequest $r): void
    {
        $this->toUsers($r, array_filter(array_unique([$r->created_by, $r->approved_by])),
            'Refund paid', "{$r->graphite_ref} · P" . number_format((float) $r->refund_amount, 2)
            . ' paid via Omni/FNB' . ($r->omni_paid_ref ? " (ref {$r->omni_paid_ref})" : '') . ' and posted to the policy.');
    }

    public function assigned(RefundRequest $r, int $assigneeId, string $assignerName): void
    {
        $this->toUsers($r, [$assigneeId],
            'Refund assigned to you', "{$r->graphite_ref} · {$r->policy_number} — assigned by {$assignerName}.");
    }

    public function accountingEnqueued(RefundRequest $r, RefundAccountingEntry $entry): void
    {
        $this->toPermission($r, 'refund-accounting-post',
            'Credit note ready for Finance review', "{$r->graphite_ref} · {$r->policy_number} · P"
            . number_format((float) $entry->refund_amount, 2) . ' — review and post the premium reversal.');
    }

    // ── internals ────────────────────────────────────────────────────────────

    private function toPermission(RefundRequest $r, string $permission, string $title, string $message, bool $skipAreaFilter = false): void
    {
        try {
            $q = User::permission($permission);
            if (!$skipAreaFilter) {
                $q = $q->permission($r->areaPermission());
            }
            $ids = $q->limit(self::MAX_RECIPIENTS)->pluck('id')->all();
            $this->insert($r, $ids, $title, $message);
        } catch (\Throwable $e) {
            Log::warning('RefundNotifier permission resolve failed: ' . $e->getMessage());
        }
    }

    private function toUsers(RefundRequest $r, array $userIds, string $title, string $message): void
    {
        try {
            $this->insert($r, $userIds, $title, $message);
        } catch (\Throwable $e) {
            Log::warning('RefundNotifier insert failed: ' . $e->getMessage());
        }
    }

    private function insert(RefundRequest $r, array $userIds, string $title, string $message): void
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));
        if (!$userIds) {
            return;
        }
        $now  = now();
        $rows = array_map(fn (int $uid) => [
            'user_id'    => $uid,
            'type'       => 'refund_engine',
            'data'       => json_encode([
                'title'         => $title,
                'message'       => $message,
                'graphite_ref'  => $r->graphite_ref,
                'policy_number' => $r->policy_number,
            ]),
            'action'     => '/finance/refund-engine/' . $r->id,
            'read_at'    => null,
            'created_at' => $now,
            'updated_at' => $now,
        ], $userIds);
        DB::connection('mysql_system')->table('notifications')->insert($rows);
    }
}
