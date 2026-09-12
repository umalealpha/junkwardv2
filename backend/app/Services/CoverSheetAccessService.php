<?php

namespace AlphaDirect\Services;

use AlphaDirect\Models\V2PdfJob;
use AlphaDirect\Policy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Secure client access to the full policy document from the QR code printed
 * on the one-page policy cover sheet.
 *
 * The problem it solves: we post a 10–20 page pack to every client. The cover
 * sheet replaces the pack in the envelope, so the client has to be able to
 * reach the real document themselves — but the pack carries their name and
 * addresses, vehicle registrations, named drivers and beneficiary details, so
 * the QR cannot simply be a link. A photograph of the sheet must be worthless.
 *
 * The flow, and where each step lives:
 *
 *   1. resolve()        the QR names a policy. Nothing is disclosed yet.
 *   2. sendOtp()        a code goes to the number ON THE POLICY — never a
 *      / grantByLogin() number supplied by the caller — or the client signs
 *                       in to the portal and we check they own the policy.
 *   3. a signed URL     proof is bound to ONE policy for a short window.
 *   4. latestDocument() the stored PDF for that policy AND action.
 *
 * Every step is written to policy_document_access_logs through
 * CoverSheetAccessLog — the DPA record of who opened what. Nothing here
 * discloses a document without leaving a row behind.
 *
 * Deliberate reuse rather than reinvention:
 *   - the OTP pipeline is PublicOtpService (purpose customer_auth), already
 *     hardened for KYC and consent: hashed codes, 5-minute validity, resend
 *     cooldown, attempt lockout, WhatsApp → SMS → email delivery;
 *   - the post-verification grant is a Laravel temporary SIGNED URL rather
 *     than a bespoke session table — the framework already gives us an
 *     unforgeable, self-expiring, policy-bound capability, and the audit log
 *     is the record of use;
 *   - the cellphone is normalised by NcdOtpSignatureService, which already
 *     encodes the Botswana numbering rules;
 *   - the document is the one GenerateQuotationPdfJob already produces, read
 *     from the same local → public disk → s3 chain that job checks.
 */
class CoverSheetAccessService
{
    /** V2-owned table; the default connection is V1's read-only replica. */
    public const CONNECTION   = 'mysql_system';
    public const TABLE_TOKENS = 'policy_cover_sheet_tokens';

    /** Purpose on the shared OTP pipeline. Must exist in PublicOtpService::PURPOSES. */
    public const OTP_PURPOSE = 'customer_auth';

    /**
     * Which flow asked for the code. Drives the wording the customer reads in
     * the SMS — must exist in OtpReason::REASONS, or it silently falls back to
     * the generic "Verifying customer identity".
     */
    public const OTP_SOURCE = 'cover_sheet_document';

    /**
     * How long proof of identity lasts. Generous rather than tight: the
     * client is on mobile data in Botswana and a 20-page pack is several
     * megabytes, so a short window risks expiring mid-download.
     */
    public const ACCESS_TTL_SECONDS = 900;

    /** Where GenerateQuotationPdfJob writes, relative to the storage root. */
    public const DOCUMENT_DIR = 'quote_sheet';

    /** document_title the job stamps on a policy document (vs a quote sheet). */
    public const POLICY_DOCUMENT_TITLE = 'POLICY DOCUMENT';

    /**
     * document_title for the one-page cover sheet itself. It is stored as a
     * v2_pdf_jobs row so it lists and downloads like any other policy
     * document — which means the untitled fallback in latestDocument() must
     * exclude it, or a client scanning the QR would be served the cover sheet
     * back instead of their policy.
     */
    public const COVER_SHEET_TITLE = 'POLICY COVER SHEET';

    // ─── Token lifecycle ─────────────────────────────────────────────────

    /**
     * Mint the token that gets printed as the QR on a cover sheet, revoking
     * any previous one for the policy so an old printed sheet stops working.
     *
     * Returns the printed value "<lookup>.<secret>" — the ONLY moment it
     * exists in the clear. Nothing stores it; the caller must render it into
     * the QR immediately.
     */
    public function issueToken(Policy $policy, ?int $actionId = null, ?int $issuedBy = null, string $reason = 'print'): string
    {
        $this->revokeTokens($policy->id, $issuedBy, 'superseded by reissue');

        $lookup = $this->freshLookup();
        $secret = Str::random(40);

        $id = DB::connection(self::CONNECTION)->table(self::TABLE_TOKENS)->insertGetId([
            'policy_id'    => $policy->id,
            'action_id'    => $actionId,
            'customer_id'  => $policy->customer_id,
            'lookup'       => $lookup,
            'secret_hash'  => $this->hash($secret),
            'issued_by'    => $issuedBy,
            'issue_reason' => $reason,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        CoverSheetAccessLog::ok(CoverSheetAccessLog::EVENT_TOKEN_ISSUED, [
            'policy_id' => $policy->id,
            'token_id'  => $id,
            'action_id' => $actionId,
            'user_id'   => $issuedBy,
            'meta'      => ['reason' => $reason],
        ]);

        return $lookup . '.' . $secret;
    }

    /** Kill every live token for a policy. Safe to call when there are none. */
    public function revokeTokens(int $policyId, ?int $by = null, string $reason = 'revoked'): int
    {
        $live = DB::connection(self::CONNECTION)->table(self::TABLE_TOKENS)
            ->where('policy_id', $policyId)
            ->whereNull('revoked_at')
            ->pluck('id');

        if ($live->isEmpty()) {
            return 0;
        }

        DB::connection(self::CONNECTION)->table(self::TABLE_TOKENS)
            ->whereIn('id', $live)
            ->update([
                'revoked_at'    => now(),
                'revoked_by'    => $by,
                'revoke_reason' => mb_substr($reason, 0, 120),
                'updated_at'    => now(),
            ]);

        foreach ($live as $tokenId) {
            CoverSheetAccessLog::ok(CoverSheetAccessLog::EVENT_TOKEN_REVOKED, [
                'policy_id' => $policyId,
                'token_id'  => $tokenId,
                'user_id'   => $by,
                'meta'      => ['reason' => $reason],
            ]);
        }

        return $live->count();
    }

    /**
     * Resolve a scanned token. Returns the token row on success, null on any
     * failure — an unknown token and a revoked one are indistinguishable to
     * the caller on purpose, so the QR cannot be probed.
     *
     * $recordScan must be true ONLY for the initial page view. Every step of
     * the flow re-resolves the token, so counting each one as a scan would
     * record four scans and a scan_count of four for a single client visit.
     * Rejections are always logged, whatever the flag: a refused token is
     * worth seeing even when it arrives on a later step.
     */
    public function resolve(?string $printed, array $req = [], bool $recordScan = false): ?object
    {
        $ctx = $this->reqCtx($req);

        [$lookup, $secret] = $this->split($printed);
        if ($lookup === null) {
            CoverSheetAccessLog::denied(CoverSheetAccessLog::EVENT_SCAN_REJECTED, 'malformed_token', $ctx);
            return null;
        }

        $row = DB::connection(self::CONNECTION)->table(self::TABLE_TOKENS)
            ->where('lookup', $lookup)
            ->first();

        if (!$row) {
            CoverSheetAccessLog::denied(CoverSheetAccessLog::EVENT_SCAN_REJECTED, 'unknown_token', $ctx);
            return null;
        }

        // Constant-time: a timing difference here would leak the secret.
        if (!hash_equals((string) $row->secret_hash, $this->hash($secret))) {
            CoverSheetAccessLog::denied(CoverSheetAccessLog::EVENT_SCAN_REJECTED, 'bad_secret', $ctx + [
                'policy_id' => $row->policy_id,
                'token_id'  => $row->id,
            ]);
            return null;
        }

        if ($row->revoked_at !== null) {
            CoverSheetAccessLog::denied(CoverSheetAccessLog::EVENT_SCAN_REJECTED, 'token_revoked', $ctx + [
                'policy_id' => $row->policy_id,
                'token_id'  => $row->id,
                'meta'      => ['revoked_at' => (string) $row->revoked_at],
            ]);
            return null;
        }

        if ($recordScan) {
            DB::connection(self::CONNECTION)->table(self::TABLE_TOKENS)
                ->where('id', $row->id)
                ->update([
                    'scan_count'   => DB::raw('scan_count + 1'),
                    'last_scan_at' => now(),
                    'updated_at'   => now(),
                ]);

            CoverSheetAccessLog::ok(CoverSheetAccessLog::EVENT_SCAN, $ctx + [
                'policy_id' => $row->policy_id,
                'token_id'  => $row->id,
                'action_id' => $row->action_id,
            ]);
        }

        return $row;
    }

    // ─── Step 2a: one-time code to the registered number ─────────────────

    /**
     * Send the code. The destination is read from the customer record here —
     * the client never supplies a number, so a scanned sheet cannot be used
     * to redirect a code to the scanner's own phone.
     *
     * @return array{ok:bool, error?:string, message?:string, cellphoneMasked?:string, expiresIn?:int, devCode?:?string}
     */
    public function sendOtp(object $token, array $req = []): array
    {
        $ctx = $this->reqCtx($req) + ['policy_id' => $token->policy_id, 'token_id' => $token->id];
        CoverSheetAccessLog::ok(CoverSheetAccessLog::EVENT_OTP_REQUESTED, $ctx);

        $policy = $this->policyFor($token);
        if (!$policy) {
            CoverSheetAccessLog::error(CoverSheetAccessLog::EVENT_OTP_SEND_FAILED, 'policy_missing', $ctx);
            return ['ok' => false, 'error' => 'policy_missing',
                'message' => 'We could not find this policy. Please call us on +267 392 8264.'];
        }

        $cellphone = $this->registeredCellphone($policy);
        if ($cellphone === null) {
            // The single most likely support call: the number on the policy is
            // missing or unusable, so this client cannot self-serve at all.
            CoverSheetAccessLog::denied(CoverSheetAccessLog::EVENT_OTP_SEND_FAILED, 'no_registered_number', $ctx + [
                'customer_id' => $policy->customer_id,
            ]);
            return ['ok' => false, 'error' => 'no_registered_number',
                'message' => 'There is no mobile number on this policy. Please call us on +267 392 8264 and we will send your document.'];
        }

        $masked = CoverSheetAccessLog::maskCellphone($cellphone);

        try {
            $res = app(PublicOtpService::class)->send(
                $cellphone,
                self::OTP_PURPOSE,
                $req['ip'] ?? null,
                $req['user_agent'] ?? null,
                // Names the flow so the SMS says why the code was sent. The
                // customer_auth fallback reads "Verifying customer identity",
                // which means nothing to someone who just scanned a page we
                // posted them — and a vague OTP is exactly what a phishing
                // message looks like.
                ['source' => self::OTP_SOURCE]
            );
        } catch (\Throwable $e) {
            CoverSheetAccessLog::error(CoverSheetAccessLog::EVENT_OTP_SEND_FAILED, 'pipeline_threw', $ctx + [
                'customer_id'      => $policy->customer_id,
                'cellphone_masked' => $masked,
                'meta'             => ['error' => $e->getMessage()],
            ]);
            return ['ok' => false, 'error' => 'send_failed',
                'message' => 'We could not send your code just now. Please try again shortly.'];
        }

        if (!($res['ok'] ?? false)) {
            $error = (string) ($res['error'] ?? 'send_failed');
            CoverSheetAccessLog::denied(CoverSheetAccessLog::EVENT_OTP_SEND_FAILED, $error, $ctx + [
                'customer_id'      => $policy->customer_id,
                'cellphone_masked' => $masked,
                'meta'             => ['wait_s' => $res['wait_s'] ?? null],
            ]);
            return [
                'ok'      => false,
                'error'   => $error,
                'message' => $this->sendFailureMessage($error, $res['wait_s'] ?? null),
                'wait_s'  => $res['wait_s'] ?? null,
            ];
        }

        CoverSheetAccessLog::ok(CoverSheetAccessLog::EVENT_OTP_SENT, $ctx + [
            'customer_id'      => $policy->customer_id,
            'cellphone_masked' => $masked,
            'meta'             => ['channel' => $res['channel'] ?? null],
        ]);

        return [
            'ok'              => true,
            'cellphoneMasked' => $masked,
            'expiresIn'       => (int) ($res['expires_in'] ?? PublicOtpService::VALIDITY_SECONDS),
            // Staging convenience only — null in production. See devCode().
            'devCode'         => $this->devCode($cellphone),
        ];
    }

    /**
     * Check the code and, on a match, hand back a signed download URL.
     *
     * The code is verified against the policy's own registered number, so a
     * success proves the holder of the sheet also holds the client's phone.
     *
     * @return array{ok:bool, error?:string, message?:string, downloadUrl?:string, expiresIn?:int}
     */
    public function verifyOtp(object $token, string $printed, ?string $code, array $req = []): array
    {
        $ctx = $this->reqCtx($req) + ['policy_id' => $token->policy_id, 'token_id' => $token->id];

        $code = trim((string) $code);
        if ($code === '') {
            CoverSheetAccessLog::denied(CoverSheetAccessLog::EVENT_OTP_FAILED, 'empty_code', $ctx);
            return ['ok' => false, 'error' => 'empty_code', 'message' => 'Enter the code we sent you.'];
        }

        $policy    = $this->policyFor($token);
        $cellphone = $policy ? $this->registeredCellphone($policy) : null;
        if (!$policy || $cellphone === null) {
            CoverSheetAccessLog::error(CoverSheetAccessLog::EVENT_OTP_FAILED, 'no_registered_number', $ctx);
            return ['ok' => false, 'error' => 'no_registered_number',
                'message' => 'We cannot verify this policy. Please call us on +267 392 8264.'];
        }

        $masked = CoverSheetAccessLog::maskCellphone($cellphone);

        try {
            $res = app(PublicOtpService::class)->verify($cellphone, self::OTP_PURPOSE, $code, $req['ip'] ?? null);
        } catch (\Throwable $e) {
            CoverSheetAccessLog::error(CoverSheetAccessLog::EVENT_OTP_FAILED, 'pipeline_threw', $ctx + [
                'cellphone_masked' => $masked,
                'meta'             => ['error' => $e->getMessage()],
            ]);
            return ['ok' => false, 'error' => 'verify_failed',
                'message' => 'We could not check your code just now. Please try again.'];
        }

        if (!($res['ok'] ?? false)) {
            // Wrong / expired / locked out — all expected, all recorded.
            $error = (string) ($res['error'] ?? 'invalid');
            CoverSheetAccessLog::denied(CoverSheetAccessLog::EVENT_OTP_FAILED, $error, $ctx + [
                'customer_id'      => $policy->customer_id,
                'cellphone_masked' => $masked,
                'meta'             => ['attempts_left' => $res['attempts_left'] ?? null],
            ]);
            return [
                'ok'            => false,
                'error'         => $error,
                'message'       => $this->verifyFailureMessage($error, $res['attempts_left'] ?? null),
                'attempts_left' => $res['attempts_left'] ?? null,
            ];
        }

        CoverSheetAccessLog::ok(CoverSheetAccessLog::EVENT_OTP_VERIFIED, $ctx + [
            'customer_id'      => $policy->customer_id,
            'cellphone_masked' => $masked,
        ]);

        return $this->grantDownload($token, $printed, 'otp', [
            'customer_id'      => $policy->customer_id,
            'cellphone_masked' => $masked,
        ], $req);
    }

    // ─── Step 2b: the client signs in instead ────────────────────────────

    /**
     * Accept a signed-in portal user, if the policy is actually theirs.
     *
     * Ownership is checked two ways because the portal's own listing matches
     * on policies.user_id, and on commercial policies that column is not
     * always the client: a match on the customer's registered cellphone or
     * email is accepted too, and which test passed is recorded so the rule
     * can be audited rather than assumed.
     *
     * @return array{ok:bool, error?:string, message?:string, downloadUrl?:string, expiresIn?:int}
     */
    public function grantByLogin(object $token, $user, string $printed, array $req = []): array
    {
        $ctx = $this->reqCtx($req) + [
            'policy_id' => $token->policy_id,
            'token_id'  => $token->id,
            'user_id'   => $user->id ?? null,
        ];

        if (!$user || empty($user->id)) {
            CoverSheetAccessLog::denied(CoverSheetAccessLog::EVENT_LOGIN_REJECTED, 'not_signed_in', $ctx);
            return ['ok' => false, 'error' => 'not_signed_in', 'message' => 'Please sign in first.'];
        }

        $policy = $this->policyFor($token);
        if (!$policy) {
            CoverSheetAccessLog::error(CoverSheetAccessLog::EVENT_LOGIN_REJECTED, 'policy_missing', $ctx);
            return ['ok' => false, 'error' => 'policy_missing',
                'message' => 'We could not find this policy. Please call us on +267 392 8264.'];
        }

        $match = $this->ownershipMatch($policy, $user);
        if ($match === null) {
            // Signed in, but this is not their policy. Worth reviewing: it is
            // either a data problem or someone else's sheet.
            CoverSheetAccessLog::denied(CoverSheetAccessLog::EVENT_LOGIN_REJECTED, 'not_policy_holder', $ctx + [
                'customer_id' => $policy->customer_id,
            ]);
            return ['ok' => false, 'error' => 'not_policy_holder',
                'message' => 'This policy is not linked to the account you signed in with. Use the one-time code instead, or call us on +267 392 8264.'];
        }

        CoverSheetAccessLog::ok(CoverSheetAccessLog::EVENT_LOGIN_VERIFIED, $ctx + [
            'customer_id' => $policy->customer_id,
            'meta'        => ['matched_on' => $match],
        ]);

        return $this->grantDownload($token, $printed, 'login', [
            'customer_id' => $policy->customer_id,
            'user_id'     => $user->id,
        ], $req);
    }

    /** Does this signed-in user own the policy behind the token?
     *
     * Read-only and unlogged, unlike grantByLogin: it exists so the
     * verification page can decide whether to OFFER the one-tap route
     * without granting anything or writing an audit row on a page view.
     */
    public function ownedBySignedInUser(object $token, $user): bool
    {
        if (!$user || empty($user->id)) {
            return false;
        }

        $policy = $this->policyFor($token);

        return $policy ? $this->ownershipMatch($policy, $user) !== null : false;
    }

    // ─── Step 3: the grant, as a signed URL ──────────────────────────────

    /**
     * Bind proof of identity to ONE policy for a short window.
     *
     * A Laravel temporary signed URL rather than a session row: the signature
     * covers the whole URL — including the QR token, so it is bound to that
     * one policy — carries its own expiry, and cannot be forged without
     * APP_KEY. That is exactly what a session table would have provided, with
     * no table to write, read, expire or prune. The audit log remains the
     * record of what was actually opened.
     *
     * @return array{ok:bool, downloadUrl:string, expiresIn:int}
     */
    private function grantDownload(object $token, string $printed, string $method, array $who, array $req): array
    {
        $ref = CoverSheetAccessLog::newSessionRef();

        $url = URL::temporarySignedRoute(
            'coverSheet.file',
            now()->addSeconds(self::ACCESS_TTL_SECONDS),
            [
                'token' => $printed,
                // Carried so the download log records who verified and how
                // without a lookup. Inside the signature, so neither can be
                // tampered with.
                'cid'   => $who['customer_id'] ?? null,
                'uid'   => $who['user_id'] ?? null,
                'm'     => $method,
                'ref'   => $ref,
            ]
        );

        CoverSheetAccessLog::ok(CoverSheetAccessLog::EVENT_SESSION_ISSUED, $this->reqCtx($req) + [
            'policy_id'        => $token->policy_id,
            'token_id'         => $token->id,
            'customer_id'      => $who['customer_id'] ?? null,
            'user_id'          => $who['user_id'] ?? null,
            'cellphone_masked' => $who['cellphone_masked'] ?? null,
            'session_ref'      => $ref,
            'meta'             => ['method' => $method, 'ttl_s' => self::ACCESS_TTL_SECONDS],
        ]);

        return [
            'ok'          => true,
            'downloadUrl' => $url,
            'expiresIn'   => self::ACCESS_TTL_SECONDS,
        ];
    }

    /** Record that the document was served on this token. */
    public function recordDownload(object $token): void
    {
        try {
            DB::connection(self::CONNECTION)->table(self::TABLE_TOKENS)
                ->where('id', $token->id)
                ->update([
                    'download_count'   => DB::raw('download_count + 1'),
                    'last_download_at' => now(),
                    'updated_at'       => now(),
                ]);
        } catch (\Throwable $e) {
            // The client already has the file; never fail the response here.
            Log::warning('cover_sheet.download_counter_failed', [
                'token_id' => $token->id ?? null,
                'error'    => $e->getMessage(),
            ]);
        }
    }

    // ─── Step 4: the document itself ─────────────────────────────────────

    /**
     * The stored policy document for a policy — the newest completed one.
     *
     * Never generated here. GenerateQuotationPdfJob puts DOM/COM policies
     * through the queue precisely because rendering a large policy exceeds
     * the gateway timeout, and a client standing at their phone will not wait
     * for a queue.
     *
     * Scoped to ONE action when the cover sheet was printed for one: the
     * sheet summarises a specific transaction, so it must open that
     * transaction's document and never a different action's. Within the
     * action we take the newest completed job by id, so a regenerated
     * document supersedes the earlier one — the client always gets the
     * latest for that policy and action.
     *
     * Resolution order, most specific first:
     *   1. POLICY DOCUMENT for this policy AND action  — the exact match;
     *   2. any completed document for this policy AND action (untitled rows
     *      predate document_title), never the cover sheet itself;
     *   3. POLICY DOCUMENT for this policy, newest by id, any action;
     *   4. any completed document for this policy, newest by id.
     *
     * Steps 3 and 4 are a deliberate fallback: rather than dead-end a client
     * who has already proved who they are, serve the most recent document the
     * policy has. A superseded schedule is more use to them than nothing, and
     * the caller records `doc_action_id` on the DOWNLOAD_SERVED row, so any
     * download that fell back to another action is visible in the audit.
     *
     * Never returns the cover sheet, which would hand the client back the
     * page they just scanned.
     *
     * Mirrors listGeneratedPolicyDocuments (the Stored Policy Documents
     * card), so what the client gets is what staff see.
     */
    public function latestDocument(int $policyId, ?int $actionId = null): ?V2PdfJob
    {
        foreach ($this->documentCandidates($policyId, $actionId) as $query) {
            $hit = $query->first();
            if ($hit) {
                return $hit;
            }
        }

        return null;
    }

    /**
     * The candidate queries for latestDocument(), in priority order.
     *
     * Split out so the resolution order is verifiable without a database:
     * with an action there are four candidates (that action titled, that
     * action any, whole policy titled, whole policy any); without one there
     * are two.
     *
     * @return array<int,\Illuminate\Database\Eloquent\Builder>
     */
    private function documentCandidates(int $policyId, ?int $actionId): array
    {
        $base = fn (?int $scopeAction) => V2PdfJob::where('policy_id', $policyId)
            ->where('status', 'completed')
            ->whereNotNull('file_name')
            ->when($scopeAction, fn ($q) => $q->where('action_id', $scopeAction))
            ->orderByDesc('id');

        $candidates = [];

        // This action first, then the whole policy. When the sheet carries no
        // action there is only one scope to try.
        foreach ($actionId ? [$actionId, null] : [null] as $scope) {
            // The explicit policy document for this scope.
            $candidates[] = $base($scope)->where('document_title', self::POLICY_DOCUMENT_TITLE);

            // Then anything else completed — untitled rows predate
            // document_title and are real policy documents — but never the
            // cover sheet, which would hand the client back the page they
            // just scanned.
            $candidates[] = $base($scope)->where(function ($inner) {
                $inner->whereNull('document_title')
                      ->orWhere('document_title', '!=', self::COVER_SHEET_TITLE);
            });
        }

        return $candidates;
    }

    /**
     * Read the stored PDF bytes, trying the same three places
     * GenerateQuotationPdfJob checks after it writes: the local storage
     * path, the 'public' disk, then 's3'.
     *
     * Disks are always named. The default disk points at a dead legacy
     * bucket, so an unnamed Storage call here would silently return nothing.
     */
    public function readDocument(string $fileName, array &$tried = []): ?string
    {
        $path = self::DOCUMENT_DIR . '/' . ltrim($fileName, '/');

        $abs = storage_path('app/public/' . $path);
        if (is_file($abs)) {
            $bytes = @file_get_contents($abs);
            if ($bytes !== false && $bytes !== '') {
                $tried[] = 'local:hit';
                return $bytes;
            }
            $tried[] = 'local:unreadable';
        } else {
            $tried[] = 'local:absent';
        }

        // 'documents' carries the record copies on this estate, so it is worth
        // trying after the working disks — a job processed on another
        // container may only have landed there.
        foreach (['public', 's3', 'documents'] as $disk) {
            try {
                if (Storage::disk($disk)->exists($path)) {
                    $bytes = Storage::disk($disk)->get($path);
                    if ($bytes !== null && $bytes !== '') {
                        $tried[] = $disk . ':hit';
                        return $bytes;
                    }
                    $tried[] = $disk . ':empty';
                } else {
                    $tried[] = $disk . ':absent';
                }
            } catch (\Throwable $e) {
                // A disk that is not configured on this estate throws rather
                // than returning false; that is not a failure of the others.
                $tried[] = $disk . ':error';
                Log::warning('cover_sheet.document_read_failed', [
                    'disk'  => $disk,
                    'path'  => $path,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return null;
    }

    // ─── Internals ───────────────────────────────────────────────────────

    /**
     * The policy behind a token, with the relations every step needs.
     * Memoised per request: the flow resolves the same policy several times.
     */
    private function policyFor(object $token): ?Policy
    {
        static $cache = [];
        $id = (int) $token->policy_id;

        if (!array_key_exists($id, $cache)) {
            $cache[$id] = Policy::with('customer')->find($id);
        }

        return $cache[$id];
    }

    /**
     * The registered mobile number for a policy, normalised, or null when
     * there is nothing usable. Read server-side only — never from input.
     */
    private function registeredCellphone(Policy $policy): ?string
    {
        $raw = (string) ($policy->customer->cellphone ?? '');
        if ($raw === '') {
            return null;
        }

        return app(NcdOtpSignatureService::class)->normaliseCellphone($raw);
    }

    /**
     * Which test proves this signed-in user owns the policy, or null if none
     * does. Returns the name of the test so the decision is auditable.
     */
    private function ownershipMatch(Policy $policy, $user): ?string
    {
        if ((int) ($policy->user_id ?? 0) !== 0 && (int) $policy->user_id === (int) $user->id) {
            return 'policy_user_id';
        }

        $customer = $policy->customer;
        if (!$customer) {
            return null;
        }

        $normaliser = app(NcdOtpSignatureService::class);
        $userCell = $normaliser->normaliseCellphone((string) ($user->cellphone ?? ''));
        $custCell = $normaliser->normaliseCellphone((string) ($customer->cellphone ?? ''));
        if ($userCell !== null && $custCell !== null && hash_equals($custCell, $userCell)) {
            return 'cellphone';
        }

        $userEmail = strtolower(trim((string) ($user->email ?? '')));
        $custEmail = strtolower(trim((string) ($customer->email ?? '')));
        if ($userEmail !== '' && $custEmail !== '' && hash_equals($custEmail, $userEmail)) {
            return 'email';
        }

        return null;
    }

    /**
     * Is this the production estate?
     *
     * Uses APP_STATUS, not APP_ENV — in this estate APP_ENV is 'local' even
     * in production, so an environment() check would happily leak OTP codes
     * to real clients. Read through config() so it stays correct under
     * `config:cache`. Same test PublicOtpService applies before it writes
     * code_plain, and it must stay in step with it.
     */
    private function isProduction(): bool
    {
        return strcasecmp((string) config('values.APP_STATUS'), 'Production') === 0;
    }

    /**
     * Hosts that are known NON-production deployments.
     *
     * This estate identifies staging by hostname, not by APP_ENV or
     * APP_STATUS — see the frontend's EnvironmentBanner TEST_HOSTNAMES, which
     * drives the red "TEST ENVIRONMENT" banner off exactly this. Production
     * answers on different names (graphite-v2-prod-be / graphite.alphadirect),
     * so an allow-list of test hosts is fail-closed by construction: a host
     * nobody listed here shows nothing.
     *
     * Following the existing convention rather than adding an env var also
     * means testers get this with no container change — env edits on this
     * estate need AWS access, not a file edit.
     */
    public const TEST_HOSTS = [
        'graphite-v2-be.alphadirect.co.bw',
        'graphite-v2-fe.alphadirect.co.bw',
        'localhost',
        '127.0.0.1',
    ];

    /**
     * May this environment print the OTP on screen?
     *
     * Fail-closed, and it takes TWO conditions:
     *
     *  1. APP_STATUS must not say Production. Absolute veto — nothing below
     *     can override it.
     *  2. AND either the request arrived on a known test host, or
     *     values.COVER_SHEET_SHOW_OTP was explicitly switched on (the escape
     *     hatch for a test host not on the list, e.g. a preview deployment).
     *
     * The reason condition 1 is not the whole test: "anything that is not the
     * string Production" treats an unset or misspelt APP_STATUS as
     * non-production, and APP_STATUS defaults to null on this estate. That
     * alone would have printed a customer's OTP for anyone holding their
     * cover sheet. Requiring a recognised host as well closes that.
     */
    private function mayShowCodeOnScreen(): bool
    {
        if ($this->isProduction()) {
            return false;
        }

        if (filter_var(config('values.COVER_SHEET_SHOW_OTP', false), FILTER_VALIDATE_BOOLEAN)) {
            return true;
        }

        return $this->onTestHost();
    }

    /** Is the current request being served on a known test hostname? */
    private function onTestHost(): bool
    {
        try {
            $host = strtolower((string) request()->getHost());
        } catch (\Throwable $e) {
            // No request context (console, queue worker) — nothing to show.
            return false;
        }

        return $host !== '' && in_array($host, self::TEST_HOSTS, true);
    }

    /**
     * The latest cover-sheet OTP for a policy, for the STAGING policy screen.
     *
     * Lets a tester read the code beside the Cover Sheet button instead of
     * needing the handset the SMS went to — which on a real policy belongs to
     * the customer, not the tester.
     *
     * Same fail-closed gate as the on-page panel: nothing outside an
     * environment that explicitly opted in, and never on production. The
     * caller must ALSO enforce the cover-sheet permission — this method does
     * not check who is asking.
     *
     * @return array{code:string, sentAt:?string, expiresAt:?string, usedAt:?string, sentTo:?string}|null
     */
    public function latestOtpForStaging(Policy $policy): ?array
    {
        if (!$this->mayShowCodeOnScreen()) {
            return null;
        }

        $cellphone = $this->registeredCellphone($policy);
        if ($cellphone === null) {
            return null;
        }

        try {
            $row = DB::connection('mysql_system')->table('public_otps')
                ->whereIn('cellphone', $this->cellphoneForms($cellphone))
                ->where('purpose', self::OTP_PURPOSE)
                ->orderByDesc('id')
                ->first(['code_plain', 'created_at', 'expires_at', 'consumed_at']);
        } catch (\Throwable $e) {
            Log::info('cover_sheet.staging_otp_unavailable', ['error' => $e->getMessage()]);
            return null;
        }

        if (!$row || !$row->code_plain) {
            return null;
        }

        return [
            'code'      => (string) $row->code_plain,
            'sentAt'    => $row->created_at ? (string) $row->created_at : null,
            'expiresAt' => $row->expires_at ? (string) $row->expires_at : null,
            // Set once the code has been used, so a tester can tell a stale
            // code from a live one.
            'usedAt'    => $row->consumed_at ? (string) $row->consumed_at : null,
            'sentTo'    => CoverSheetAccessLog::maskCellphone($cellphone),
        ];
    }

    /**
     * The OTP in clear, for the staging verification page only.
     *
     * On staging there is no point paying for an SMS to a test number, so the
     * code is shown on screen under the form. It is read from the
     * TESTING-ONLY `code_plain` column that PublicOtpService already writes
     * outside production — this adds no new place where a code is stored.
     *
     * Returns null anywhere that has not opted in, and null if the column is
     * absent, so the page simply has nothing to show.
     */
    private function devCode(string $cellphone): ?string
    {
        if (!$this->mayShowCodeOnScreen()) {
            return null;
        }

        try {
            $code = DB::connection('mysql_system')->table('public_otps')
                // Two normalisers are in play and they disagree, so match
                // either form. NcdOtpSignatureService returns E.164 without
                // the plus (26776623136); PublicOtpService::normalize strips
                // the 267 and STORES the local 8-digit number (76623136).
                // Querying only the form we passed to send() found nothing,
                // which silently hid the staging panel even though the SMS
                // and the verify both worked.
                ->whereIn('cellphone', $this->cellphoneForms($cellphone))
                ->where('purpose', self::OTP_PURPOSE)
                ->whereNull('consumed_at')
                ->orderByDesc('id')
                ->value('code_plain');

            return $code ? (string) $code : null;
        } catch (\Throwable $e) {
            // The column is documented as droppable once QA no longer needs
            // it, so its absence must not break the flow.
            Log::info('cover_sheet.dev_code_unavailable', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Client-facing copy for a send failure. PublicOtpService returns codes
     * only, so the wording lives here rather than leaking a code to a
     * customer standing at their phone.
     */
    private function sendFailureMessage(string $error, $waitSeconds = null): string
    {
        switch ($error) {
            case 'cooldown':
                $wait = (int) ($waitSeconds ?? PublicOtpService::RESEND_COOLDOWN_SEC);
                return "We have just sent you a code. Please wait {$wait} seconds before asking for another.";
            case 'delivery_failed':
                return 'We could not deliver your code. Please try again, or call us on +267 392 8264.';
            default:
                return 'We could not send your code just now. Please try again shortly.';
        }
    }

    /** Client-facing copy for a verification failure. */
    private function verifyFailureMessage(string $error, $attemptsLeft = null): string
    {
        switch ($error) {
            case 'expired':
                return 'That code has expired. Ask for a new one.';
            case 'locked':
                return 'Too many incorrect attempts. Ask for a new code.';
            case 'no_pending_otp':
                return 'There is no code waiting for this policy. Ask for a new one.';
            case 'invalid':
                $left = $attemptsLeft === null ? null : (int) $attemptsLeft;
                if ($left !== null && $left > 0) {
                    return "That code is not right. You have {$left} more attempts.";
                }
                return 'That code is not right. Check it and try again.';
            default:
                return 'We could not check your code just now. Please try again.';
        }
    }

    /** Split "<lookup>.<secret>"; null lookup means it was not that shape. */
    private function split(?string $printed): array
    {
        $printed = trim((string) $printed);
        if ($printed === '' || !str_contains($printed, '.')) {
            return [null, ''];
        }

        [$lookup, $secret] = explode('.', $printed, 2);
        if ($lookup === '' || $secret === '' || !preg_match('/^[a-z0-9]{8,24}$/', $lookup)) {
            return [null, ''];
        }

        return [$lookup, $secret];
    }

    /** A lookup handle not already taken. */
    private function freshLookup(): string
    {
        for ($i = 0; $i < 5; $i++) {
            // Lower-cased so the printed token stays case-insensitive to read.
            $candidate = strtolower(Str::random(16));
            $taken = DB::connection(self::CONNECTION)->table(self::TABLE_TOKENS)
                ->where('lookup', $candidate)
                ->exists();
            if (!$taken) {
                return $candidate;
            }
        }

        // Astronomically unlikely; fail loudly rather than reuse a handle.
        throw new \RuntimeException('Could not allocate a unique cover-sheet token lookup.');
    }

    /**
     * Both spellings of a Botswana number, because the two normalisers in
     * this codebase disagree and public_otps can hold either:
     *
     *   NcdOtpSignatureService::normaliseCellphone  76623136 → 26776623136
     *   PublicOtpService::normalize                26776623136 → 76623136
     *
     * PublicOtpService is what actually writes the row, so its local form is
     * the one that usually matches — but a row written by another caller may
     * carry the 267 prefix, so we look for both rather than pick a side.
     *
     * @return array<int,string>
     */
    private function cellphoneForms(string $cellphone): array
    {
        $digits = preg_replace('/\D/', '', $cellphone) ?: '';
        $forms  = [$digits];

        if (str_starts_with($digits, '267') && strlen($digits) === 11) {
            $forms[] = substr($digits, 3);
        } elseif (strlen($digits) === 8) {
            $forms[] = '267' . $digits;
        }

        return array_values(array_unique(array_filter($forms)));
    }

    /** Salted hash, same shape as the OTP services use. */
    private function hash(string $value): string
    {
        return hash('sha256', $value . (string) config('app.key'));
    }

    /** Normalise request context for the log calls. */
    private function reqCtx(array $req): array
    {
        return [
            'ip'         => $req['ip'] ?? null,
            'user_agent' => isset($req['user_agent']) ? mb_substr((string) $req['user_agent'], 0, 255) : null,
        ];
    }
}
