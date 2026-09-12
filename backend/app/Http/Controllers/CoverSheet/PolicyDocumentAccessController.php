<?php

namespace AlphaDirect\Http\Controllers\CoverSheet;

use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Policy;
use AlphaDirect\Services\CoverSheetAccessLog;
use AlphaDirect\Services\CoverSheetAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * What the client reaches when they scan the QR on their policy cover sheet.
 *
 * Deliberately server-rendered rather than an SPA route: the client is on a
 * phone browser opened straight from a camera app, often on slow mobile data,
 * and this page must work with no build step, no bundle download and no
 * client-side routing. One small Blade page, three fetch calls.
 *
 * The gate, in order:
 *   show()       the token names a policy. NOTHING personal is rendered —
 *                not the insured's name, not the address. Only the policy
 *                number (already printed on the sheet in the client's hand)
 *                and a masked hint of which number the code will go to.
 *   sendOtp()    a code to the number ON THE POLICY, read server-side.
 *   verifyOtp()  on a match, a short-lived SIGNED download URL.
 *   useLogin()   the alternative: an already-signed-in portal user who owns
 *                the policy skips the code.
 *   download()   verifies the signature, then streams the stored document for
 *                that policy AND action as an attachment so the phone saves it.
 *
 * Only show() counts as a scan. Every step re-resolves the token, so counting
 * each one would record four scans for a single client visit.
 *
 * Every branch, including every refusal, is written to
 * policy_document_access_logs by CoverSheetAccessService. This controller adds
 * no logging of its own except the download outcomes, which only it can see.
 */
class PolicyDocumentAccessController extends Controller
{
    public function __construct(private CoverSheetAccessService $access)
    {
    }

    /**
     * GET /p/d/{token} — the verification page.
     *
     * An unknown, malformed or revoked token renders the same neutral page,
     * so a scanned sheet cannot be used to work out whether a token is real.
     */
    public function show(Request $request, string $token)
    {
        // The one place a scan is recorded.
        $row = $this->access->resolve($token, $this->req($request), true);
        if (!$row) {
            return response()->view('coversheet.invalid', [], 404);
        }

        $policy = Policy::with('customer')->find($row->policy_id);
        if (!$policy) {
            return response()->view('coversheet.invalid', [], 404);
        }

        return response()->view('coversheet.access', [
            'token'        => $token,
            'policyNumber' => $policy->policyNumber,
            'cellHint'     => CoverSheetAccessLog::maskCellphone((string) ($policy->customer->cellphone ?? '')),
            // Whether to OFFER the one-tap route. Read-only on purpose: a page
            // view must not grant anything or write an access row, so the
            // actual grant happens on the explicit POST to useLogin().
            'signedInOwner' => $this->access->ownedBySignedInUser($row, Auth::user()),
        ]);
    }

    /**
     * POST /p/d/{token}/login — the client is already signed in to the
     * portal and this policy is theirs, so no code is needed.
     */
    public function useLogin(Request $request, string $token): JsonResponse
    {
        $row = $this->access->resolve($token, $this->req($request));
        if (!$row) {
            return $this->deadLink();
        }

        $res = $this->access->grantByLogin($row, Auth::user(), $token, $this->req($request));

        return response()->json([
            'ok'          => (bool) $res['ok'],
            'message'     => $res['message'] ?? null,
            'downloadUrl' => $res['downloadUrl'] ?? null,
        ], $res['ok'] ? 200 : 403);
    }

    /** POST /p/d/{token}/otp/send */
    public function sendOtp(Request $request, string $token): JsonResponse
    {
        $row = $this->access->resolve($token, $this->req($request));
        if (!$row) {
            return $this->deadLink();
        }

        $res = $this->access->sendOtp($row, $this->req($request));

        return response()->json([
            'ok'      => (bool) $res['ok'],
            'message' => $res['message'] ?? null,
            'sentTo'  => $res['cellphoneMasked'] ?? null,
            'waitS'   => $res['wait_s'] ?? null,
            // Staging only — the service returns null in production, so the
            // page has nothing to display there. See CoverSheetAccessService::devCode().
            'devCode' => $res['devCode'] ?? null,
        ], $res['ok'] ? 200 : 422);
    }

    /** POST /p/d/{token}/otp/verify */
    public function verifyOtp(Request $request, string $token): JsonResponse
    {
        $row = $this->access->resolve($token, $this->req($request));
        if (!$row) {
            return $this->deadLink();
        }

        $res = $this->access->verifyOtp($row, $token, (string) $request->input('code'), $this->req($request));

        return response()->json([
            'ok'           => (bool) $res['ok'],
            'message'      => $res['message'] ?? null,
            'downloadUrl'  => $res['downloadUrl'] ?? null,
            'attemptsLeft' => $res['attempts_left'] ?? null,
        ], $res['ok'] ? 200 : 422);
    }

    /**
     * GET /p/d/{token}/file — the signed download.
     *
     * The signature is minted only after the client proved who they are, and
     * covers the whole URL including the QR token, so it is bound to that one
     * policy and cannot be re-pointed at another. It carries its own expiry.
     *
     * Streams the document for the policy AND the action the sheet was printed
     * for, as an attachment so the phone stores the file rather than only
     * rendering it in a tab the client may lose.
     */
    public function download(Request $request, string $token)
    {
        $reqCtx = $this->req($request);

        $row = $this->access->resolve($token, $reqCtx);
        if (!$row) {
            return response()->view('coversheet.invalid', [], 404);
        }

        $ctx = $reqCtx + [
            'policy_id'   => $row->policy_id,
            'token_id'    => $row->id,
            'action_id'   => $row->action_id,
            'session_ref' => $request->query('ref'),
            'customer_id' => $request->query('cid') ?: null,
            'user_id'     => $request->query('uid') ?: null,
        ];

        // Validated by hand rather than with the `signed` middleware so the
        // refusal is logged and the client gets the "start again" page instead
        // of a bare 403.
        if (!$request->hasValidSignature()) {
            CoverSheetAccessLog::denied(
                CoverSheetAccessLog::EVENT_SESSION_REJECTED,
                'bad_or_expired_signature',
                $ctx
            );
            return response()->view('coversheet.expired', ['token' => $token], 403);
        }

        CoverSheetAccessLog::ok(CoverSheetAccessLog::EVENT_DOWNLOAD_STARTED, $ctx);

        // Action-scoped: the sheet summarises one transaction, so it opens
        // that transaction's document and never a neighbouring action's.
        $job = $this->access->latestDocument((int) $row->policy_id, $row->action_id ? (int) $row->action_id : null);
        if (!$job) {
            CoverSheetAccessLog::error(CoverSheetAccessLog::EVENT_DOWNLOAD_FAILED, 'no_document_for_action', $ctx);
            return response()->view('coversheet.unavailable', [], 503);
        }

        $tried = [];
        $bytes = $this->access->readDocument((string) $job->file_name, $tried);
        if ($bytes === null || $bytes === '') {
            // The job says completed but the file is not readable on any
            // disk — an operational fault, not a client error. `tried` records
            // what each location said, so the cause is in the audit row rather
            // than needing a repro.
            CoverSheetAccessLog::error(CoverSheetAccessLog::EVENT_DOWNLOAD_FAILED, 'file_missing_on_disk', $ctx + [
                'meta' => [
                    'pdf_job_id' => $job->id,
                    'file_name'  => $job->file_name,
                    'tried'      => $tried,
                ],
            ]);
            return response()->view('coversheet.unavailable', [], 503);
        }

        $this->access->recordDownload($row);

        CoverSheetAccessLog::ok(CoverSheetAccessLog::EVENT_DOWNLOAD_SERVED, $ctx + [
            'meta' => [
                'pdf_job_id'    => $job->id,
                'doc_action_id' => $job->action_id,
                'bytes'         => strlen($bytes),
                'method'        => $request->query('m'),
            ],
        ]);

        $policyNumber = Policy::where('id', $row->policy_id)->value('policyNumber');

        return response($bytes, 200, [
            'Content-Type'           => 'application/pdf',
            'Content-Length'         => (string) strlen($bytes),
            'Content-Disposition'    => 'attachment; filename="' . $this->downloadName($policyNumber) . '"',
            // A policy document must never sit in a shared or proxy cache.
            'Cache-Control'          => 'no-store, no-cache, must-revalidate, private',
            'Pragma'                 => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    // ─── Internals ───────────────────────────────────────────────────────

    /** Request context every log line carries. */
    private function req(Request $request): array
    {
        return [
            'ip'         => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
        ];
    }

    private function deadLink(): JsonResponse
    {
        return response()->json(['ok' => false, 'message' => 'This link is no longer valid.'], 404);
    }

    /**
     * A filename the client will recognise on their phone.
     *
     * Dots are stripped rather than kept: allowing them let a value like
     * "../.." survive sanitising as ".._..", which has no business in a
     * Content-Disposition header even though policyNumber comes from our own
     * table and not from the request. The only dot in the result is the one
     * this method appends.
     */
    private function downloadName(?string $policyNumber): string
    {
        $safe = preg_replace('/[^A-Za-z0-9_-]+/', '_', (string) $policyNumber);
        $safe = trim((string) preg_replace('/_+/', '_', (string) $safe), '_-');

        return 'Alpha_Direct_Policy_' . ($safe !== '' ? $safe : 'Document') . '.pdf';
    }
}
