<?php
/**
 * SCAFFOLD — not yet wired into the live app. Move to
 * backend/app/Http/Controllers/Api/V1/ and register the routes in
 * backend/routes/api_v1.php when deploying (needs Graphite deploy access).
 *
 * Smart Underwriting Upload — accepts a broker schedule (xlsx/xls/pdf),
 * stores it, queues extraction, returns a job id the frontend polls. The
 * extraction itself is delegated to the smart-uw-engine (see ../README.md):
 * either a Python sidecar (mirrors pdf-service) or a PHP port. The result is
 * Graphite-ready JSON the review screen replays through the EXISTING
 * create-policy endpoints — no new write paths.
 *
 * Mirrors conventions from ExcelImportController::upload + OcrController.
 */

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SmartUploadController extends Controller
{
    private const ACCEPT = ['xlsx', 'xls', 'pdf'];

    /** POST /api/v1/underwriting/smart-upload  (multipart: file) */
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|max:25600', // 25 MB
        ]);

        $file = $request->file('file');
        $ext  = strtolower($file->getClientOriginalExtension());
        if (!in_array($ext, self::ACCEPT, true)) {
            return response()->json(['error' => 'Only .xlsx, .xls, .pdf accepted'], 422);
        }

        // Store under a per-upload prefix (mirrors ExcelImportController path shape).
        $stamp = now()->format('YmdHis');
        $key   = "smart_uw_uploads/{$stamp}_" . Str::random(6) . "/" . $file->getClientOriginalName();
        Storage::put($key, file_get_contents($file->getRealPath()));

        $id = DB::table('smart_uw_uploads')->insertGetId([
            'uploaded_file' => $key,
            'original_name' => $file->getClientOriginalName(),
            'file_ext'      => $ext,
            'status'        => 'queued',
            'added_by'      => auth()->id(),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        // dispatch(new SmartUnderwritingExtractJob($id));   // Redis queue

        return response()->json([
            'job_id'  => $id,
            'status'  => 'queued',
            'message' => 'Schedule received. Extraction started.',
        ]);
    }

    /** GET /api/v1/underwriting/smart-upload/{id} — poll status + result */
    public function status(int $id): JsonResponse
    {
        $row = DB::table('smart_uw_uploads')->where('id', $id)->first();
        if (!$row) {
            return response()->json(['error' => 'not found'], 404);
        }
        $extractions = DB::table('smart_uw_extractions')
            ->where('upload_id', $id)
            ->get(['id', 'segment_name', 'extracted_json', 'confidence', 'provider', 'human_verified']);

        return response()->json([
            'job_id'      => $id,
            'status'      => $row->status,           // queued|processing|completed|failed
            'risks'       => $extractions,           // one per sheet/segment
            'message'     => $row->message ?? null,
        ]);
    }
}
