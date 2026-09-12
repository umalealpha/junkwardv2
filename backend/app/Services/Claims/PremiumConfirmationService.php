<?php

namespace AlphaDirect\Services\Claims;

use AlphaDirect\Events\SendMail;
use AlphaDirect\Services\IntegrationSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * PremiumConfirmationService — raises, releases and tracks the premium
 * confirmation that Finance does by hand today.
 *
 * THE ASYMMETRY IS THE WHOLE DESIGN (CFO, 11-Aug-2026):
 *   green (paid up) -> released automatically, nobody is involved. We are only
 *                      ever approving a claim we would have approved anyway.
 *   amber / red     -> ALWAYS a person. Never an automatic decline.
 *
 * Keetile Mokhendo asked for a human step so that "we do not repudiate payable
 * claims on the basis of broken internal processes or system issues", and she is
 * right — 72% of bank statement lines were once duplicated by our own daily
 * feed, and P399k was paid twice. Our posting is imperfect, so arrears in our
 * system is not proof of arrears in the customer's bank account. Hence: automate
 * the yes, never the no.
 *
 * Dark behind the `premium_confirmation` runtime flag. Never throws into claim
 * registration.
 */
class PremiumConfirmationService
{
    public const FLAG = 'premium_confirmation';

    /** Finance's service level. Calendar hours, by CFO decision. */
    public const SLA_HOURS = 24;

    /** Where an arrears confirmation lands. */
    public const FINANCE_INBOX = 'debtors@alphadirect.co.bw';

    public function __construct(private PremiumStatusEngine $engine)
    {
    }

    public static function enabled(): bool
    {
        return IntegrationSettings::isEnabled(self::FLAG, (bool) config('claims.premium_confirmation_enabled', false));
    }

    /**
     * Raise the confirmation for a claim. Called automatically on claim
     * registration — the CFO's design has the customer's form and Finance's
     * check going out at the same moment, not one after the other.
     *
     * Idempotent per claim: an existing open row is returned rather than
     * duplicated, so a re-fired event cannot double-ask Finance.
     */
    public function raiseForClaim(int $claimId, string $actor = 'system'): ?array
    {
        if (!self::enabled()) {
            return null;
        }

        try {
            // ANY existing row wins, not just an open one. ClaimEvent is
            // dispatched from two separate store() call sites, so an
            // already-released claim would otherwise collect a second
            // auto_released row on a re-fire and be counted twice on the tracker.
            $existing = DB::table('claim_premium_confirmations')
                ->where('claim_id', $claimId)
                ->orderByDesc('id')
                ->first();
            if ($existing) {
                return ['id' => $existing->id, 'status' => $existing->status, 'reused' => true];
            }

            $claim = DB::table('claims')->where('id', $claimId)->first(['id', 'policy_id', 'claim_number']);
            if (!$claim) {
                return null;
            }

            $a   = $this->engine->assess($claimId);
            $now = now();

            // Green releases itself. Anything else — including an assessment we
            // could not complete — waits for a person. Failing to read the
            // ledger must never look like a pass.
            $autoRelease = ($a['ok'] ?? false) && ($a['light'] ?? '') === 'green';

            $id = DB::table('claim_premium_confirmations')->insertGetId([
                'claim_id'             => $claimId,
                'policy_id'            => $claim->policy_id ?? null,
                'status'               => $autoRelease ? 'auto_released' : 'pending_finance',
                'light'                => $a['light'] ?? 'amber',
                'premium_status'       => $a['status'] ?? null,
                'balance'              => $a['balance'] ?? null,
                'premium'              => $a['premium'] ?? null,
                'premiums_outstanding' => $a['premiums_outstanding'] ?? 0,
                'unpaid_from'          => $a['unpaid_from'] ?? null,
                'last_payment_date'    => $a['last_payment_date'] ?? null,
                'settlement_hold'      => ($a['premiums_outstanding'] ?? 0) >= 3,
                'assessment'           => json_encode($a),
                'raised_at'            => $now,
                'due_at'               => $now->copy()->addHours(self::SLA_HOURS),
                'released_at'          => $autoRelease ? $now : null,
                'released_by'          => $autoRelease ? 'system (paid up)' : null,
                'created_at'           => $now,
                'updated_at'           => $now,
            ]);

            if (!$autoRelease) {
                $this->notifyFinance($id, $claim, $a);
            }

            Log::info('[PremiumConfirmation] raised', [
                'claim_id' => $claimId, 'id' => $id,
                'light' => $a['light'] ?? null, 'auto' => $autoRelease,
            ]);

            return ['id' => $id, 'status' => $autoRelease ? 'auto_released' : 'pending_finance', 'reused' => false];
        } catch (\Throwable $e) {
            Log::error('[PremiumConfirmation] raise failed', ['claim_id' => $claimId, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Finance signs off, or queries it back. This is the ONLY thing a person is
     * asked to do — the two fields that were ever really theirs.
     *
     * NOTE, and it must not be overstated: confirming the premium deliberately
     * does NOT clear `settlement_hold`. The hold is a separate management
     * decision about repudiation, and Finance confirming that a balance is real
     * is not that decision.
     *
     * HONEST LIMIT: today the hold is RECORDED and shown on the tracker, but
     * nothing enforces it at payment time, because the claims settlement path
     * is a later piece of work. Do not describe this as blocking a payout until
     * that wiring exists.
     */
    public function record(int $id, string $decision, string $comment, string $actor): array
    {
        if (!in_array($decision, ['confirmed', 'queried'], true)) {
            return ['ok' => false, 'reason' => 'unknown_decision'];
        }
        if ($decision === 'queried' && trim($comment) === '') {
            return ['ok' => false, 'reason' => 'comment_required'];
        }

        $row = DB::table('claim_premium_confirmations')->where('id', $id)->first();
        if (!$row) {
            return ['ok' => false, 'reason' => 'not_found'];
        }
        if (in_array($row->status, ['confirmed', 'auto_released'], true)) {
            return ['ok' => false, 'reason' => 'already_closed'];
        }

        // Guard the update on the still-open status too, so two Finance users
        // acting on the same confirmation at once cannot both win — the second
        // write matches zero rows and is told it was already signed off, instead
        // of silently overwriting the first decision (read-then-update race).
        $affected = DB::table('claim_premium_confirmations')
            ->where('id', $id)
            ->whereNotIn('status', ['confirmed', 'auto_released'])
            ->update([
                'status'          => $decision,
                'finance_comment' => trim($comment) ?: null,
                'released_at'     => $decision === 'confirmed' ? now() : null,
                'released_by'     => $actor,
                'updated_at'      => now(),
            ]);

        if ($affected === 0) {
            return ['ok' => false, 'reason' => 'already_closed'];
        }

        return ['ok' => true, 'status' => $decision];
    }

    /**
     * Draft the collection letter for genuine arrears — to the AGENT on direct
     * business, to the UNDERWRITER on broker business, which is exactly how
     * Finance does it today. The draft is returned for a person to read and
     * send; nothing goes out from here.
     */
    public function draftCollection(int $id): array
    {
        $row = DB::table('claim_premium_confirmations')->where('id', $id)->first();
        if (!$row) {
            return ['ok' => false, 'reason' => 'not_found'];
        }

        $claim  = DB::table('claims')->where('id', $row->claim_id)->first(['claim_number', 'policy_id']);
        $policy = $claim && $claim->policy_id
            ? DB::table('policies')->where('id', $claim->policy_id)->first(['policyNumber', 'agent_id'])
            : null;

        $isBroker = !empty($policy->agent_id);

        DB::table('claim_premium_confirmations')->where('id', $id)
            ->update(['collection_drafted_at' => now(), 'updated_at' => now()]);

        return [
            'ok'        => true,
            'audience'  => $isBroker ? 'underwriter' : 'agent',
            'subject'   => 'Outstanding premium — policy ' . ($policy->policyNumber ?? '') . ', claim ' . ($claim->claim_number ?? ''),
            'body'      => $this->collectionBody($row, $claim, $policy, $isBroker),
        ];
    }

    // ── internals ───────────────────────────────────────────────────────────

    /**
     * Tell Finance there is one waiting. Goes to the debtors inbox; the tracker
     * is what actually measures the 24 hours, not this email.
     */
    private function notifyFinance(int $id, $claim, array $a): void
    {
        try {
            $red = ($a['light'] ?? '') === 'red';

            $html = '<div style="font-family:Arial,Helvetica,sans-serif;color:#0D1B2A;max-width:640px;line-height:1.55;">'
                . '<div style="background:#0D1B2A;padding:15px 20px;border-radius:8px 8px 0 0;">'
                . '<span style="color:#F4A623;font-size:16px;font-weight:bold;">Premium confirmation needed</span></div>'
                . '<div style="border:1px solid #e4e7ec;border-top:none;padding:20px;background:#fff;border-radius:0 0 8px 8px;">'
                . '<p style="margin:0 0 12px;">A claim has been registered and the premium is not showing as up to date. '
                . 'Everything we hold is already filled in — please check and confirm.</p>'
                . '<table style="border-collapse:collapse;font-size:14px;margin:0 0 14px;">'
                . $this->row('Claim', $claim->claim_number ?? '')
                . $this->row('Status', $a['status'] ?? '')
                . $this->row('Balance', 'P ' . number_format((float) ($a['balance'] ?? 0), 2))
                . $this->row('Unpaid from', $a['unpaid_from'] ?? 'not established')
                . $this->row('Last payment', $a['last_payment_date'] ?? 'none found')
                . '</table>'
                . ($red
                    ? '<p style="margin:0 0 12px;padding:10px 12px;background:#fdeeee;border-left:3px solid #c0392b;">'
                      . 'This one shows three or more premiums outstanding, so settlement is held for a management '
                      . 'decision. It is <strong>not</strong> a decline — please check for payments we may not have '
                      . 'posted before anything else happens.</p>'
                    : '')
                . '<p style="margin:0 0 12px;">Please reply within 24 hours.</p>'
                . '<p style="margin:0;font-size:12px;color:#55606e;">If the money is in but not posted, that is what '
                . 'this check is for — please fix the posting rather than reporting arrears.</p>'
                . '</div></div>';

            event(new SendMail(
                self::FINANCE_INBOX,
                'Premium confirmation needed — claim ' . ($claim->claim_number ?? ''),
                '',
                $html,
                '',
                ['hook' => 'claim_premium_confirmation', 'claim_id' => $claim->id ?? null, 'confirmation_id' => $id]
            ));
        } catch (\Throwable $e) {
            // Finance can still see it on the tracker; a mail hiccup must not
            // lose the confirmation itself.
            Log::warning('[PremiumConfirmation] notify failed', ['id' => $id]);
        }
    }

    private function row(string $label, $value): string
    {
        return '<tr><td style="padding:3px 14px 3px 0;color:#55606e;">' . htmlspecialchars($label, ENT_QUOTES)
            . '</td><td style="padding:3px 0;font-weight:bold;">' . htmlspecialchars((string) $value, ENT_QUOTES) . '</td></tr>';
    }

    private function collectionBody($row, $claim, $policy, bool $isBroker): string
    {
        $who = $isBroker ? 'the underwriter' : 'the agent';

        return "Good day\n\n"
            . 'We have a claim on policy ' . ($policy->policyNumber ?? '') . ' (claim ' . ($claim->claim_number ?? '') . ").\n\n"
            . 'The premium on this policy is showing as outstanding: P '
            . number_format((float) $row->balance, 2)
            . ($row->unpaid_from ? ', unpaid from ' . $row->unpaid_from : '') . ".\n\n"
            . 'Please arrange collection of the outstanding premium, or send us the proof of payment if it has '
            . "already been paid so we can post it.\n\n"
            . "Regards\nAlpha Direct Finance\n\n"
            . "-- draft for {$who}; please read it before sending --";
    }
}
