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
 * POST /api/v1/public/vehicle-inspections/submit
 *
 * Finalises a vehicle pre-inspection submission started via plate→OTP
 * (OtpController::sendForPlate / verifyForPlate) + photo uploads via
 * /public/uploads/chunk (purpose=vehicle_inspection).
 *
 * Authentication: Bearer session token (purpose=vehicle_inspect). The
 * token's cellphone is the source of truth — the FE only ever sees a
 * masked '57****46' so we ignore the request body cellphone.
 *
 * Authorisation: the plate must belong to a policy whose customer
 * matches the session's cellphone. Without this, anybody with a valid
 * session could submit inspection photos against any plate.
 *
 * Upload verification: every photo's s3_path must exist in
 * public_uploaded_files for this cellphone + purpose=vehicle_inspection.
 * Stops a malicious FE from claiming someone else's uploaded image.
 *
 * Workflow row: inserts an agentpreinspection row with the four primary
 * angle paths (front/back/left/right) and a JSON blob in
 * vehicle_registration covering any extras (interior/odometer).
 */
class PublicVehicleInspectionController extends Controller
{
    public function __construct(private PublicOtpService $otp) {}

    public function submit(Request $request): JsonResponse
    {
        $request->validate([
            'plate'             => ['required', 'string', 'regex:/^[A-Z0-9 \-]{3,15}$/i'],
            'photos'            => 'required|array|min:1|max:12',
            'photos.*.angle'    => 'required|string|in:front,back,left,right,interior,odometer',
            'photos.*.s3_path'  => 'required|string|max:512',
            'cellphone'         => 'nullable|string|max:24', // ignored; BE uses session
            'consent_id'        => 'nullable|integer|min:1',
        ]);

        // ─── Auth ────────────────────────────────────────────────────────
        $bearer = $this->extractBearer($request);
        if (!$bearer) {
            return response()->json(['ok' => false, 'error' => 'session_required'], 401);
        }
        $session = $this->otp->validateToken($bearer);
        if (!$session) {
            return response()->json(['ok' => false, 'error' => 'session_invalid_or_expired'], 401);
        }
        $sessionCell = $this->normalize((string) ($session['cellphone'] ?? ''));
        if ($sessionCell === '') {
            return response()->json(['ok' => false, 'error' => 'session_cellphone_missing'], 401);
        }

        // ─── Plate must belong to this customer ─────────────────────────
        $plate = strtoupper(preg_replace('/\s+/', '', $request->input('plate')));
        $vehicle = \AlphaDirect\Vehicle::where('vehiclePlate', $plate)->first();
        if (!$vehicle) {
            return response()->json(['ok' => false, 'error' => 'plate_not_found'], 404);
        }
        $policy = \AlphaDirect\Policy::with('customer:id,cellphone')->where('id', $vehicle->policy_id)->first();
        if (!$policy || !$policy->customer) {
            return response()->json(['ok' => false, 'error' => 'policy_or_customer_missing'], 404);
        }
        $ownerCell = $this->normalize((string) $policy->customer->cellphone);
        if ($ownerCell !== $sessionCell) {
            Log::warning('public_vehicle_inspection.plate_mismatch', [
                'plate' => $plate,
                'session_hash' => substr(hash('sha256', $sessionCell), 0, 8),
                'owner_hash'   => substr(hash('sha256', $ownerCell), 0, 8),
            ]);
            return response()->json(['ok' => false, 'error' => 'plate_not_yours'], 403);
        }

        // ─── Verify each photo belongs to this session ──────────────────
        $photos = $request->input('photos');
        $verifiedPaths = []; // angle => s3_path (server-canonical)
        foreach ($photos as $p) {
            $row = DB::table('public_uploaded_files')
                ->where('cellphone', $sessionCell)
                ->where('purpose',   'vehicle_inspection')
                ->where('s3_path',   $p['s3_path'])
                ->first(['id', 's3_path']);
            if (!$row) {
                return response()->json([
                    'ok'    => false,
                    'error' => 'upload_not_found',
                    'detail' => "No upload matches angle={$p['angle']} for this session.",
                ], 422);
            }
            // Server-canonical path — never trust the FE-supplied string
            // beyond using it to identify the row.
            $verifiedPaths[$p['angle']] = $row->s3_path;
        }

        // ─── Insert the workflow row ────────────────────────────────────
        $now = Carbon::now();
        $extras = [];
        if (isset($verifiedPaths['interior'])) $extras['interior'] = $verifiedPaths['interior'];
        if (isset($verifiedPaths['odometer'])) $extras['odometer'] = $verifiedPaths['odometer'];

        $insertId = DB::table('agentpreinspection')->insertGetId([
            'vehiclePlate'         => $plate,
            'front'                => $verifiedPaths['front'] ?? null,
            'back'                 => $verifiedPaths['back']  ?? null,
            'left'                 => $verifiedPaths['left']  ?? null,
            'right'                => $verifiedPaths['right'] ?? null,
            // Repurpose vehicle_registration to carry interior/odometer
            // paths until a proper schema migration adds dedicated columns.
            // text column → keep it parseable JSON so future migrations
            // can lift values into named columns.
            'vehicle_registration' => $extras ? json_encode($extras) : null,
            'agentId'              => 0,  // public submission, no agent
            'front_status'         => 0,
            'back_status'          => 0,
            'left_status'          => 0,
            'right_status'         => 0,
            'vehicle_registration_status' => 0,
            'compliance'           => 0,
            'status'               => 0,  // pending review
            'created_at'           => $now,
            'updated_at'           => $now,
        ]);

        // A customer re-upload after an Unapproved (2) review moves the
        // inspection back to Recheck (3 = awaiting re-review), so the ops
        // portal badge stops showing "Unapproved". Mirrors the agent-app path
        // (UploadController::agentAppUploadPreInspection). Only 2 -> 3; Pending
        // (0), Approved (1) and already-Recheck (3) are left unchanged. The
        // re-uploaded photos still land in agentpreinspection (unchanged);
        // routing them into the vehicle row the reviewer reads is a separate,
        // larger change and intentionally out of scope here.
        $recheck = false;
        if ((int) $vehicle->status === 2) {
            $vehicle->status = 3;
            $vehicle->save();
            $recheck = true;
        }

        Log::info('public_vehicle_inspection.submitted', [
            'submission_id' => $insertId,
            'plate'         => $plate,
            'policy_id'     => $vehicle->policy_id,
            'photos'        => count($photos),
            'cellphone_hash'=> substr(hash('sha256', $sessionCell), 0, 8),
            'consent_id'    => $request->input('consent_id'),
            'recheck'       => $recheck,
        ]);

        return response()->json([
            'ok'            => true,
            'submission_id' => 'VI-' . $insertId,
            'message'       => "Submitted {$photos[0]['angle']}+ photos for plate {$plate}. Underwriter review within 1 business day.",
        ], 201);
    }

    private function extractBearer(Request $request): ?string
    {
        $auth = $request->header('Authorization', '');
        if (preg_match('/^Bearer\s+(.+)$/i', $auth, $m)) return trim($m[1]);
        return null;
    }

    private function normalize(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);
        if (strlen($digits) >= 11 && str_starts_with($digits, '267')) $digits = substr($digits, 3);
        return $digits;
    }
}
