<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Services\PublicOtpService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * POST /api/v1/public/kyc/submit
 *
 * Finalises a Customer KYC submission started via /public/uploads/chunk.
 *
 * The chunked uploader has already pushed each file to S3 and inserted a
 * `public_uploaded_files` row keyed by sha256 + cellphone + purpose. This
 * endpoint:
 *   1. Validates the Bearer session token (purpose=kyc_update enforced).
 *   2. Resolves the customer by the session's verified cellphone.
 *   3. For each {purpose, sha256, s3_path} the FE claims, looks up the
 *      matching `public_uploaded_files` row scoped to the session's
 *      cellphone — prevents a holder of one customer's session from
 *      claiming another customer's uploaded files.
 *   4. Upserts the customer's `customer_kyc` row, mapping each purpose to
 *      its document column (omang, proof_residence, etc.) and setting the
 *      per-document status to 0 (pending) so the KYC team's queue picks
 *      it up. Top-level status is set to "Unchecked" — matches the
 *      enum already used by admin Approve / Unapprove / Recheck flows.
 *   5. Returns { ok, submission_id, message }.
 */
class PublicKycController extends Controller
{
    public function __construct(private PublicOtpService $otp) {}

    /**
     * Map FE-side `purpose` to (document-path column, status column).
     * Mirrors PublicUploadService::PURPOSES and the admin
     * CustomerKycController doc map.
     */
    private const PURPOSE_TO_COL = [
        'omang_kyc'          => ['col' => 'omang',           'status' => 'omangFrontStatus'],
        'passport_kyc'       => ['col' => 'passport',        'status' => 'passportStatus'],
        'license_kyc'        => ['col' => 'driving_license', 'status' => 'driving_licenseStatus'],
        'proof_of_residence' => ['col' => 'proof_residence', 'status' => 'proof_residenceStatus'],
        'employment_letter'  => ['col' => 'proof_income',    'status' => 'proof_incomeStatus'],
    ];

    public function submit(Request $request): JsonResponse
    {
        $request->validate([
            'uploaded'                => 'required|array|min:1|max:20',
            'uploaded.*.purpose'      => 'required|string|in:' . implode(',', array_keys(self::PURPOSE_TO_COL)),
            'uploaded.*.s3_path'      => 'required|string|max:512',
            'uploaded.*.sha256'       => 'required|string|size:64',
            'uploaded.*.size_bytes'   => 'required|integer|min:1|max:10485760',
            'cellphone'               => 'required|string|max:24',
            'consent_id'              => 'nullable|integer|min:1',
        ]);

        $bearer = $this->extractBearer($request);
        if (!$bearer) {
            return response()->json(['ok' => false, 'error' => 'session_required'], 401);
        }
        $session = $this->otp->validateToken($bearer);
        if (!$session) {
            return response()->json(['ok' => false, 'error' => 'session_invalid_or_expired'], 401);
        }
        // The Bearer is the source of truth; the FE-supplied cellphone is
        // informational only. Refuse if they disagree to surface FE bugs.
        $sessionCell = $this->normalize((string) ($session['cellphone'] ?? ''));
        $payloadCell = $this->normalize((string) $request->input('cellphone'));
        if ($sessionCell === '' || $sessionCell !== $payloadCell) {
            return response()->json(['ok' => false, 'error' => 'session_cellphone_mismatch'], 403);
        }

        $customer = DB::table('customer')->where('cellphone', $sessionCell)->first(['id']);
        if (!$customer) {
            // Without a customer row there's nowhere to hang the KYC. The
            // legacy app auto-created one here, but on V2 the customer
            // must exist before KYC submission — fail loudly so the FE
            // funnels the user back through onboarding.
            return response()->json(['ok' => false, 'error' => 'customer_not_found'], 404);
        }

        $uploaded = $request->input('uploaded');

        // Verify every claimed upload actually belongs to this cellphone +
        // matches the FE-supplied sha256. Stops a malicious FE from
        // pointing customer_kyc at someone else's S3 object.
        $kycUpdates = [];
        foreach ($uploaded as $doc) {
            $row = DB::table('public_uploaded_files')
                ->where('cellphone', $sessionCell)
                ->where('purpose',   $doc['purpose'])
                ->where('sha256',    $doc['sha256'])
                ->first(['id', 's3_path']);
            if (!$row) {
                return response()->json([
                    'ok'    => false,
                    'error' => 'upload_not_found',
                    'detail' => "No upload matches purpose={$doc['purpose']} for this session.",
                ], 422);
            }
            // Trust the server-side row, not the FE-supplied s3_path —
            // the FE only ever needs to identify the upload, not dictate
            // where on S3 it landed.
            $map = self::PURPOSE_TO_COL[$doc['purpose']];
            $kycUpdates[$map['col']]    = $row->s3_path;
            $kycUpdates[$map['status']] = 0; // 0=pending, 1=approved, 2=rejected
        }

        // Upsert customer_kyc row. If the customer has never submitted KYC
        // before there's no row yet — create one. Otherwise update in place
        // so re-submitting just overwrites the document paths.
        $now = Carbon::now();
        $kycUpdates['status']     = 'Unchecked';
        $kycUpdates['updated_at'] = $now;

        if ($request->filled('consent_id')) {
            // Audit trail — record the DPA consent that backed this submission.
            $kycUpdates['data_protection_consent'] = (int) $request->input('consent_id');
        }

        $existing = DB::table('customer_kyc')->where('customer_id', $customer->id)->first(['id']);
        if ($existing) {
            DB::table('customer_kyc')->where('id', $existing->id)->update($kycUpdates);
            $kycId = $existing->id;
        } else {
            $kycUpdates['customer_id'] = $customer->id;
            $kycUpdates['created_at']  = $now;
            $kycId = DB::table('customer_kyc')->insertGetId($kycUpdates);
        }

        Log::info('public_kyc.submitted', [
            'customer_id' => $customer->id,
            'kyc_id'      => $kycId,
            'docs'        => array_column($uploaded, 'purpose'),
            'ip'          => $request->ip(),
        ]);

        return response()->json([
            'ok'            => true,
            'submission_id' => 'KYC-' . $kycId,
            'message'       => 'Submitted ' . count($uploaded) . ' document(s). The KYC team will review within 1 business day.',
        ]);
    }

    private function extractBearer(Request $request): ?string
    {
        $auth = $request->header('Authorization', '');
        if (preg_match('/^Bearer\s+(.+)$/i', $auth, $m)) return trim($m[1]);
        return null;
    }

    private function normalize(string $cellphone): string
    {
        $digits = preg_replace('/\D/', '', $cellphone);
        if (str_starts_with($digits, '267') && strlen($digits) === 11) return substr($digits, 3);
        return $digits;
    }
}
