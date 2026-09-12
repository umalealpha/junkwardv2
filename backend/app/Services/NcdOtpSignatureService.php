<?php

namespace AlphaDirect\Services;

use AlphaDirect\Events\SendSms;
use AlphaDirect\Policy;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * OTP e-signature for the No Claims Declaration.
 *
 * Flow: staff press "Generate" on the policy → we SMS a 6-digit code to the
 * customer's registered cellphone, with wording that sharing the code with
 * the agent is consent to sign → the agent keys the code in → on a match we
 * record the signature (cellphone + timestamp) and the caller renders the
 * signed PDF.
 *
 * Security properties (same bar as PublicOtpService):
 *  - code stored as sha256(code . APP_KEY) only, never plaintext;
 *  - 5-minute validity, 60-second resend cooldown, 5 wrong attempts locks
 *    the code (a fresh one must be sent);
 *  - constant-time compare; every open code for the policy is voided when a
 *    new one is issued, so only the latest is ever valid;
 *  - the destination number is read from the customer record server-side —
 *    the client never supplies it.
 */
class NcdOtpSignatureService
{
    public const TABLE = 'policy_ncd_otp_signatures';

    public const VALIDITY_SECONDS   = 300;
    public const RESEND_COOLDOWN_SEC = 60;
    public const MAX_ATTEMPTS       = 5;

    /**
     * Issue and send an OTP for the given policy.
     *
     * @return array{ok:bool, error?:string, message?:string, wait_s?:int, cellphoneMasked?:string, expiresAt?:string, id?:int}
     */
    public function send(Policy $policy, ?int $requestedBy, ?string $ip): array
    {
        $customer = $policy->customer;
        $cellphone = $this->normaliseCellphone((string) ($customer->cellphone ?? ''));
        if ($cellphone === null) {
            return ['ok' => false, 'error' => 'no_cellphone',
                'message' => 'The customer has no valid registered cellphone number. Update the customer record first.'];
        }

        // Resend cooldown against the newest code for this policy.
        $latest = DB::table(self::TABLE)
            ->where('policy_id', $policy->id)
            ->orderByDesc('id')
            ->first();
        if ($latest && $latest->signed_at === null) {
            $age = Carbon::parse($latest->created_at)->diffInSeconds(now());
            if ($age < self::RESEND_COOLDOWN_SEC) {
                $wait = self::RESEND_COOLDOWN_SEC - $age;
                return ['ok' => false, 'error' => 'cooldown', 'wait_s' => $wait,
                    'message' => "An OTP was sent moments ago. Wait {$wait}s before sending another."];
            }
        }

        // Only one live code per policy: void anything still open.
        DB::table(self::TABLE)
            ->where('policy_id', $policy->id)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now(), 'updated_at' => now()]);

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiresAt = now()->addSeconds(self::VALIDITY_SECONDS);

        $id = DB::table(self::TABLE)->insertGetId([
            'policy_id'    => $policy->id,
            'customer_id'  => $policy->customer_id,
            'cellphone'    => $cellphone,
            'code_hash'    => $this->hash($code),
            'expires_at'   => $expiresAt,
            'attempts'     => 0,
            'requested_by' => $requestedBy,
            'request_ip'   => $ip,
            'sms_status'   => 'queued',
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        $message = $this->smsText($policy, $code);
        $status = 'sent';
        try {
            event(new SendSms('+' . $cellphone, $message, [
                'policyNumber' => $policy->policyNumber,
                'hook'         => 'ncd_otp_signature',
            ]));
        } catch (\Throwable $e) {
            $status = 'failed';
            Log::warning('ncd_otp.sms_failed', ['policy_id' => $policy->id, 'error' => $e->getMessage()]);
        }
        DB::table(self::TABLE)->where('id', $id)->update(['sms_status' => $status, 'updated_at' => now()]);

        if ($status === 'failed') {
            return ['ok' => false, 'error' => 'sms_failed',
                'message' => 'The OTP SMS could not be sent. Try again shortly.'];
        }

        // Never return the code. Outside production it is logged for QA only.
        if (!app()->environment('production')) {
            Log::info('ncd_otp.code_for_testing', ['policy_id' => $policy->id, 'code' => $code]);
        }

        return [
            'ok'              => true,
            'id'              => (int) $id,
            'cellphoneMasked' => self::maskCellphone($cellphone),
            'expiresAt'       => $expiresAt->toIso8601String(),
            'validitySeconds' => self::VALIDITY_SECONDS,
        ];
    }

    /**
     * Verify the code the agent keyed in. On success the row is marked signed
     * and returned; the caller then generates the PDF and links it via
     * markDocumentGenerated().
     *
     * @return array{ok:bool, error?:string, message?:string, attempts_left?:int, row?:object}
     */
    public function verify(Policy $policy, string $code): array
    {
        $row = DB::table(self::TABLE)
            ->where('policy_id', $policy->id)
            ->whereNull('consumed_at')
            ->orderByDesc('id')
            ->first();

        if (!$row) {
            return ['ok' => false, 'error' => 'no_active_otp',
                'message' => 'No active OTP for this policy. Send a new one.'];
        }

        if (Carbon::parse($row->expires_at)->isPast()) {
            DB::table(self::TABLE)->where('id', $row->id)->update(['consumed_at' => now(), 'updated_at' => now()]);
            return ['ok' => false, 'error' => 'expired', 'message' => 'The OTP has expired. Send a new one.'];
        }

        if ((int) $row->attempts >= self::MAX_ATTEMPTS) {
            DB::table(self::TABLE)->where('id', $row->id)->update(['consumed_at' => now(), 'updated_at' => now()]);
            return ['ok' => false, 'error' => 'locked', 'message' => 'Too many incorrect attempts. Send a new OTP.'];
        }

        if (!hash_equals($row->code_hash, $this->hash(preg_replace('/\D/', '', $code)))) {
            DB::table(self::TABLE)->where('id', $row->id)->increment('attempts', 1, ['updated_at' => now()]);
            $left = self::MAX_ATTEMPTS - ((int) $row->attempts + 1);
            if ($left <= 0) {
                DB::table(self::TABLE)->where('id', $row->id)->update(['consumed_at' => now(), 'updated_at' => now()]);
                return ['ok' => false, 'error' => 'locked', 'message' => 'Too many incorrect attempts. Send a new OTP.'];
            }
            return ['ok' => false, 'error' => 'invalid', 'attempts_left' => $left,
                'message' => "Incorrect OTP. {$left} attempt" . ($left === 1 ? '' : 's') . ' left.'];
        }

        $signedAt = now();
        DB::table(self::TABLE)->where('id', $row->id)->update([
            'consumed_at' => $signedAt,
            'signed_at'   => $signedAt,
            'updated_at'  => $signedAt,
        ]);
        $row->signed_at = $signedAt->toDateTimeString();
        $row->consumed_at = $row->signed_at;

        return ['ok' => true, 'row' => $row];
    }

    /**
     * Undo a verification whose PDF could not be rendered or stored, so the
     * agent can retry with the SAME code (while it is still within expiry)
     * instead of putting the customer through another SMS. The code was
     * correct — only the downstream step failed.
     */
    public function releaseAfterStorageFailure(int $signatureId): void
    {
        DB::table(self::TABLE)->where('id', $signatureId)->whereNull('attachment_id')->update([
            'consumed_at' => null,
            'signed_at'   => null,
            'updated_at'  => now(),
        ]);
    }

    public function markDocumentGenerated(int $signatureId, int $attachmentId, string $pdfPath): void
    {
        DB::table(self::TABLE)->where('id', $signatureId)->update([
            'attachment_id' => $attachmentId,
            'pdf_path'      => $pdfPath,
            'updated_at'    => now(),
        ]);
    }

    /** Signature evidence for a given attachment, or null if it was a manual upload. */
    public function signatureForAttachment(?int $attachmentId): ?array
    {
        if (!$attachmentId) {
            return null;
        }
        try {
            $row = DB::table(self::TABLE)
                ->where('attachment_id', $attachmentId)
                ->whereNotNull('signed_at')
                ->orderByDesc('id')
                ->first();
        } catch (\Throwable $e) {
            return null;
        }
        if (!$row) {
            return null;
        }
        return [
            'method'          => 'OTP',
            'cellphoneMasked' => self::maskCellphone($row->cellphone),
            'signedAt'        => $row->signed_at,
        ];
    }

    /**
     * SMS body. The consent sentence is a requirement from EXCO: sharing the
     * code with the agent IS the customer's signature, and the message must
     * say so in plain words.
     */
    public function smsText(Policy $policy, string $code): string
    {
        $mins = (int) (self::VALIDITY_SECONDS / 60);
        return "Alpha Direct: Your OTP to sign the No Claims Declaration for policy {$policy->policyNumber} is {$code}. "
            . "By sharing this OTP with your sales agent you consent to digitally signing the declaration. "
            . "Valid for {$mins} minutes. Do not share it for any other purpose.";
    }

    /** Botswana-aware normalisation to E.164 digits (no '+'). Null if unusable. */
    public function normaliseCellphone(string $raw): ?string
    {
        $digits = preg_replace('/\D/', '', $raw);
        if ($digits === '') {
            return null;
        }
        if (strlen($digits) === 8 && $digits[0] === '7') {
            return '267' . $digits;
        }
        if (strlen($digits) === 11 && str_starts_with($digits, '267')) {
            return $digits;
        }
        if (strlen($digits) === 12 && str_starts_with($digits, '0267')) {
            return substr($digits, 1);
        }
        // International dialling prefix typed as 00.
        if (str_starts_with($digits, '00') && strlen($digits) >= 12 && strlen($digits) <= 17) {
            $digits = substr($digits, 2);
        }
        // Foreign number: accept 10–15 digits as already international.
        if (strlen($digits) >= 10 && strlen($digits) <= 15) {
            return $digits;
        }
        return null;
    }

    public static function maskCellphone(?string $digits): string
    {
        $digits = (string) $digits;
        if (strlen($digits) < 4) {
            return '****';
        }
        return '+' . substr($digits, 0, 3) . ' *** ' . substr($digits, -3);
    }

    private function hash(string $value): string
    {
        return hash('sha256', $value . config('app.key'));
    }
}
