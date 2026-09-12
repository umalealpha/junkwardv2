<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Services\PublicOtpService;
use AlphaDirect\Services\PublicUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public chunked upload — used by Customer KYC, Vehicle Inspection,
 * and any other tile that pushes files larger than the typical Apache
 * POST limit. Replaces the legacy KycUploadController.
 *
 * Requires a Bearer session token from a successful OTP / magic-link
 * verify. Token's cellphone binds the upload to a verified customer.
 *
 * POST /api/v1/public/uploads/chunk    (multipart)
 *   file          — the chunk binary (≤1MB)
 *   chunkIndex    — 0-based
 *   totalChunks   — total number of chunks expected
 *   originalFileName — for display (sanitised before storage)
 *   purpose       — one of PublicUploadService::PURPOSES
 *   session_uuid  — server-generated on first chunk; pass for subsequent
 *   expected_size — full file size for early too-large rejection
 *   mime_type     — client's claim; sniffed again on assembly
 */
class UploadController extends Controller
{
    public function __construct(
        private PublicOtpService    $otp,
        private PublicUploadService $uploads,
    ) {}

    public function chunk(Request $request): JsonResponse
    {
        $request->validate([
            'file'             => 'required|file|max:1024', // KB → 1MB chunk cap
            'chunkIndex'       => 'required|integer|min:0',
            'totalChunks'      => 'required|integer|min:1|max:5000',
            'originalFileName' => 'required|string|max:255',
            'purpose'          => 'required|string|in:' . implode(',', PublicUploadService::PURPOSES),
            'session_uuid'     => 'nullable|string|size:36',
            'expected_size'    => 'nullable|integer|min:1|max:104857600', // 100MB ceiling
            'mime_type'        => 'nullable|string|max:100',
            // Optional: which product this upload belongs to. When set,
            // the file is filed under that product's folder by the
            // materialisation worker. Validated against the FE-known
            // ID range (max ~30 in the products table).
            'product_id'       => 'nullable|integer|min:1|max:255',
        ]);

        $bearer = $this->extractBearer($request);
        if (!$bearer) {
            \Log::warning('public_upload.auth.no_bearer', [
                'purpose' => $request->input('purpose'),
                'ip'      => $request->ip(),
            ]);
            return response()->json(['error' => 'session_required'], 401);
        }
        $session = $this->otp->validateToken($bearer);
        if (!$session) {
            // Log why so debugging the "Upload failed" message isn't guess-and-check.
            // The DB row tells us: missing / revoked / expired / wrong-purpose.
            $row = \DB::table('public_session_tokens')
                ->where('token_hash', hash('sha256', $bearer))
                ->first();
            $reason = !$row ? 'not_found'
                    : ($row->revoked_at ? 'revoked'
                    : ($row->expires_at && $row->expires_at < now() ? 'expired'
                    : 'unknown'));
            \Log::warning('public_upload.auth.invalid_token', [
                'purpose' => $request->input('purpose'),
                'reason'  => $reason,
                'token_hash' => substr(hash('sha256', $bearer), 0, 8),
                'expires_at' => $row->expires_at ?? null,
            ]);
            return response()->json(['error' => 'session_invalid_or_expired'], 401);
        }

        $result = $this->uploads->receiveChunk(
            chunk:            $request->file('file'),
            purpose:          $request->input('purpose'),
            originalFileName: $request->input('originalFileName'),
            chunkIndex:       (int) $request->input('chunkIndex'),
            totalChunks:      (int) $request->input('totalChunks'),
            session:          $session,
            expectedSize:     $request->input('expected_size') ? (int) $request->input('expected_size') : null,
            sessionUuid:      $request->input('session_uuid'),
            mimeType:         $request->input('mime_type'),
            ip:               $request->ip(),
            ua:               $request->userAgent(),
            productId:        $request->input('product_id') ? (int) $request->input('product_id') : null,
        );

        $status = $result['ok']
            ? (($result['status'] ?? null) === 'completed' ? 201 : 200)
            : 400;

        return response()->json($result, $status);
    }

    /**
     * Auth-less chunked variant for the customer-facing document
     * scanner. The file is never persisted to S3 — chunks are
     * assembled in /tmp, OCR runs against the assembled bytes, the
     * file is deleted and only the extracted fields come back.
     *
     * Same shape on the wire as the authed `chunk` endpoint, just no
     * Bearer header required and no DB session row created.
     *
     * POST /api/v1/public/uploads/scan-chunk
     */
    public function scanChunk(Request $request): JsonResponse
    {
        $request->validate([
            'file'             => 'required|file|max:1024', // 1MB chunk cap
            'chunkIndex'       => 'required|integer|min:0',
            'totalChunks'      => 'required|integer|min:1|max:5000',
            'originalFileName' => 'required|string|max:255',
            'purpose'          => 'required|string|in:auto,omang_id,passport,driver_license,vehicle_bluebook,vehicle_valuation,employment_letter,proof_of_residence',
            'session_uuid'     => 'nullable|string|size:36',
            'expected_size'    => 'nullable|integer|min:1|max:20971520', // 20MB ceiling for scans
            'mime_type'        => 'nullable|string|max:100',
        ]);

        $result = $this->uploads->receiveScanChunk(
            chunk:            $request->file('file'),
            purpose:          $request->input('purpose'),
            originalFileName: $request->input('originalFileName'),
            chunkIndex:       (int) $request->input('chunkIndex'),
            totalChunks:      (int) $request->input('totalChunks'),
            expectedSize:     $request->input('expected_size') ? (int) $request->input('expected_size') : null,
            sessionUuid:      $request->input('session_uuid'),
            mimeType:         $request->input('mime_type'),
            ip:               $request->ip(),
        );

        $status = $result['ok']
            ? (($result['status'] ?? null) === 'completed' ? 200 : 200)
            : (($result['error'] ?? '') === 'ocr_failed' ? 502 : 400);

        return response()->json($result, $status);
    }

    private function extractBearer(Request $request): ?string
    {
        $auth = $request->header('Authorization', '');
        if (preg_match('/^Bearer\s+(.+)$/i', $auth, $m)) return trim($m[1]);
        return null;
    }
}
