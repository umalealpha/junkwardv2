<?php

namespace AlphaDirect\Services;

use AlphaDirect\Http\Controllers\SmsMessaging;
use AlphaDirect\Models\MisRecurringPaymentFailureSms;
use AlphaDirect\Sms;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Failed-recurring-payment SMS for MIS (Instant Insurance) policies.
 *
 * Single entry point for both recurring gateways:
 *   - DPO     : the recurring token crons (policy:chargeRecurrentToken,
 *               policy:chargePendingRecurrentToken)
 *   - RealPay : the installment webhook (RealPayController::updateInstallment)
 *
 * Three guarantees the call sites rely on:
 *
 *  1. MIS ONLY. Anything that is not an MIS policy number is dropped here, so
 *     call sites don't have to know the prefix rules. DOM/COM/DOMG/COMG and
 *     unknown prefixes get nothing.
 *
 *  2. ACTIVE POLICIES ONLY. A policy the system does not show as active gets
 *     nothing — an arrears message would tell that customer to fund cover we do
 *     not show as in force. Recorded as POLICY_NOT_ACTIVE rather than sent.
 *
 *  3. AT MOST ONE SMS PER FAILED COLLECTION. Enforced by the UNIQUE index on
 *     mis_recurring_payment_failure_sms (gateway, event_reference) — an
 *     application-level "already sent?" SELECT would not be enough: RealPay
 *     webhooks are replayed by ProcessWebhookBuffer every minute and the DPO
 *     crons get re-run by hand. We claim the key BEFORE sending, so a
 *     concurrent replay loses the insert and sends nothing.
 *
 *  4. NEVER THROWS. Every call site is either a webhook write path or a
 *     money-moving cron; a notification failure must not roll either back.
 *     Failures are logged and reported as `false`.
 *
 * The SMS body, recipient, status and timestamp are recorded in `sms_email_log`
 * by SendSmsFired (the SMS/Email Logs screen). This service additionally links
 * that log row back to the failed collection it belongs to.
 */
class MisRecurringPaymentFailureNotifier
{
    /** policies.status: 1 = active, 0 = deactivated, 2 = cancelled. */
    private const POLICY_ACTIVE  = 1;

    public const GATEWAY_DPO     = 'DPO';
    public const GATEWAY_REALPAY = 'RealPay';

    /** sms_templates.slug — Ops-editable body, seeded by migration. */
    public const TEMPLATE_SLUG = 'mis_recurring_payment_failed_sms';

    /**
     * Used only if the template row is missing (fresh env, or someone deleted
     * it). Sending the signed-off wording beats sending nothing.
     */
    private const FALLBACK_TEXT = 'Dear [CUSTOMER_NAME], your premium payment for Policy [POLICY_NUMBER] was unsuccessful. Status: Payment Failed. Please ensure sufficient funds are available or update your payment details to keep your cover active.';

    private SmsMessaging $sms;

    public function __construct(SmsMessaging $sms)
    {
        $this->sms = $sms;
    }

    // ──────────────────────────────────────────────────────────────
    // Event reference builders — one per gateway, so the uniqueness key
    // is defined in one place instead of inline at each call site.
    // ──────────────────────────────────────────────────────────────

    /**
     * DPO recurring charge: the scheduled_transactions row plus which
     * installment and which retry it was. retry_count increments on every
     * attempt, so each genuine retry is a new event (and gets its own SMS),
     * while a re-run of the same cron pass is not.
     */
    public static function dpoEventReference($scheduleId, $installment, $retryCount): string
    {
        return 'DPO:' . $scheduleId . ':' . $installment . ':' . $retryCount;
    }

    /**
     * RealPay installment: the reference number RealPay issued for the debit
     * plus its sequence. Stable across webhook replays of the same debit.
     */
    public static function realpayEventReference($instalmentReferenceNumber, $sequence): string
    {
        return 'RP:' . $instalmentReferenceNumber . ':' . $sequence;
    }

    /** MIS policy numbers are the MIS-prefixed series (e.g. MIS2024115097). */
    public static function isMisPolicy(?string $policyNumber): bool
    {
        return strpos(strtoupper(trim((string) $policyNumber)), 'MIS') === 0;
    }

    // ──────────────────────────────────────────────────────────────

    /**
     * Notify the customer that a recurring premium collection failed.
     *
     * @param  string $policyNumber   MIS policy number; anything else is a no-op.
     * @param  string $gateway        self::GATEWAY_DPO | self::GATEWAY_REALPAY
     * @param  string $eventReference From dpoEventReference()/realpayEventReference()
     * @return bool   true only when an SMS was actually dispatched.
     */
    public function notify(string $policyNumber, string $gateway, string $eventReference): bool
    {
        $policyNumber   = trim($policyNumber);
        $eventReference = trim($eventReference);

        if ($policyNumber === '' || $eventReference === '' || !self::isMisPolicy($policyNumber)) {
            return false;
        }

        try {
            $recipient = $this->resolveRecipient($policyNumber);

            // Claim the uniqueness key first: if a replay is already in flight
            // (or finished), we lose the insert and send nothing.
            $ledger = $this->claim($gateway, $eventReference, $policyNumber, $recipient);
            if ($ledger === null) {
                Log::info('mis.recurring_payment_failed_sms.duplicate_suppressed', [
                    'policyNumber'    => $policyNumber,
                    'gateway'         => $gateway,
                    'event_reference' => $eventReference,
                ]);
                return false;
            }

            if ($recipient === null) {
                $this->close($ledger, 'POLICY_NOT_FOUND', 'No policy row for ' . $policyNumber);
                return false;
            }

            // ACTIVE POLICIES ONLY (Pramod, 2026-09-07). Measured on PROD before
            // this guard existed: 2,713 of 5,503 messages — 49.3% — went to
            // policies the system does not consider active (2,654 deactivated,
            // 59 cancelled), telling those customers to fund their premium "to
            // keep your cover active". Several were still being debited. That is
            // a conduct problem, not a cosmetic one.
            //
            // The event key is already claimed above, so this failure is
            // deliberately never notified. A later failure on a reactivated
            // policy carries a different event reference, so it is a new key and
            // will send — nothing is permanently silenced by this.
            if ((int) ($recipient->policy_status ?? -1) !== self::POLICY_ACTIVE) {
                $this->close($ledger, 'POLICY_NOT_ACTIVE',
                    'Policy status ' . var_export($recipient->policy_status ?? null, true)
                    . ' is not active — an arrears message would tell this customer to fund '
                    . 'cover the system does not show as in force.');
                return false;
            }

            $cellphone = $this->normaliseMsisdn($recipient->cellphone ?? null);
            if ($cellphone === null) {
                $this->close($ledger, 'NO_CELLPHONE', 'Customer has no usable cellphone on file');
                return false;
            }

            $customerName = trim(($recipient->firstName ?? '') . ' ' . ($recipient->lastName ?? ''));
            $message      = $this->renderMessage($customerName, $policyNumber);

            $response = $this->sms->sendMisRecurringPaymentFailedSMS($cellphone, $message, $policyNumber);

            if ($response === false) {
                // SmsControls toggle for this sender is off.
                $this->close($ledger, 'SUPPRESSED', 'sendMisRecurringPaymentFailedSMS disabled in sms_controls', $message, $cellphone);
                return false;
            }

            $this->close($ledger, 'SENT', null, $message, $cellphone);
            return true;
        } catch (\Throwable $e) {
            // Deliberately swallowed — see class docblock guarantee 3.
            Log::error('mis.recurring_payment_failed_sms.failed', [
                'policyNumber'    => $policyNumber,
                'gateway'         => $gateway,
                'event_reference' => $eventReference,
                'error'           => $e->getMessage(),
            ]);
            return false;
        }
    }

    // ──────────────────────────────────────────────────────────────
    // Internals
    // ──────────────────────────────────────────────────────────────

    /**
     * Insert the ledger row that owns this failed collection.
     *
     * @return MisRecurringPaymentFailureSms|null null when another pass already
     *         claimed (gateway, event_reference).
     */
    private function claim(string $gateway, string $eventReference, string $policyNumber, $recipient): ?MisRecurringPaymentFailureSms
    {
        try {
            return MisRecurringPaymentFailureSms::create([
                'policyNumber'    => $policyNumber,
                'policy_id'       => $recipient->policy_id ?? null,
                'customer_id'     => $recipient->customer_id ?? null,
                'gateway'         => $gateway,
                'event_reference' => $eventReference,
                'status'          => 'PENDING',
            ]);
        } catch (QueryException $e) {
            if ($this->isDuplicateKey($e)) {
                return null;
            }
            throw $e;
        }
    }

    private function isDuplicateKey(QueryException $e): bool
    {
        return isset($e->errorInfo[1]) && (int) $e->errorInfo[1] === 1062;
    }

    /**
     * Finalise the ledger row and point it at the sms_email_log entry that
     * SendSmsFired just wrote.
     */
    private function close(
        MisRecurringPaymentFailureSms $ledger,
        string $status,
        ?string $note = null,
        ?string $message = null,
        ?string $cellphone = null
    ): void {
        $ledger->status = $status;
        $ledger->note   = $note;

        if ($message !== null) {
            $ledger->message = $message;
        }
        if ($cellphone !== null) {
            $ledger->to_cellphone = '+267' . $cellphone;
        }
        if ($status === 'SENT' && $message !== null && $cellphone !== null) {
            $ledger->sms_email_log_id = $this->findSmsEmailLogId($ledger->policyNumber, '+267' . $cellphone, $message);
        }

        $ledger->save();
    }

    /**
     * Best-effort link to the sms_email_log row for this send. Matched on
     * recipient + body rather than an id because SendSmsFired writes the log
     * itself and does not hand the id back through the event.
     */
    private function findSmsEmailLogId(string $policyNumber, string $to, string $message): ?int
    {
        $id = DB::table('sms_email_log')
            ->where('policyNumber', $policyNumber)
            ->where('to_cellphone', $to)
            ->where('message', $message)
            ->orderByDesc('id')
            ->value('id');

        return $id !== null ? (int) $id : null;
    }

    /**
     * Policy + customer in one hit. Returns null when the policy number does
     * not resolve (bad webhook payload, policy purged).
     */
    private function resolveRecipient(string $policyNumber)
    {
        return DB::table('policies')
            ->leftJoin('customer', 'customer.id', '=', 'policies.customer_id')
            ->where('policies.policyNumber', $policyNumber)
            ->orderByDesc('policies.id')
            ->first([
                'policies.id as policy_id',
                'policies.customer_id',
                'policies.status as policy_status',
                'customer.firstName',
                'customer.lastName',
                'customer.cellphone',
            ]);
    }

    /**
     * Body from sms_templates so Ops can reword without a deploy; falls back to
     * the signed-off wording if the row is gone.
     */
    private function renderMessage(string $customerName, string $policyNumber): string
    {
        $template = Sms::where('slug', self::TEMPLATE_SLUG)
            ->orWhere('hook_slug', self::TEMPLATE_SLUG)
            ->first(['text']);

        $text = ($template && trim((string) $template->text) !== '')
            ? $template->text
            : self::FALLBACK_TEXT;

        $text = str_replace('&nbsp;', ' ', $text);
        $text = str_replace(
            ['[CUSTOMER_NAME]', '[POLICY_NUMBER]', '[POLICYNUMBER]'],
            [$customerName !== '' ? $customerName : 'Customer', $policyNumber, $policyNumber],
            $text
        );

        return trim(strip_tags($text));
    }

    /**
     * Digits only, with the country code stripped if the number was stored
     * with it — SmsMessaging prefixes '+267' on send, so a stored '267…' would
     * otherwise go out as '+267267…'.
     */
    private function normaliseMsisdn(?string $cellphone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $cellphone);

        if ($digits === '' || $digits === null) {
            return null;
        }
        if (strlen($digits) > 8 && strpos($digits, '267') === 0) {
            $digits = substr($digits, 3);
        }

        return strlen($digits) >= 7 ? $digits : null;
    }
}
