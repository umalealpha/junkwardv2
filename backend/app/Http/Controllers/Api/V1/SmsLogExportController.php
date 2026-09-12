<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Services\InfobipSmsExportService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * GRA-0155 — self-service download of the daily Infobip SMS-log exports.
 *
 * Companion to ExportInfobipSmsLogsDaily (the nightly cron that writes the
 * CSVs) and InfobipSmsExportService (the shared CSV builder). Lets authorized
 * users:
 *   - list the CSV exports already sitting on S3 (reports/infobip-sms-logs/…),
 *   - get a short-lived presigned download URL for a chosen file, and
 *   - request an on-demand date-range export (generated on the fly, capped),
 *     returning a presigned URL to the freshly-written CSV.
 *
 * Read-only w.r.t. business tables — the only write is the CSV object on the
 * export disk (S3), same as the cron.
 *
 * Authorisation is enforced by the route middleware
 * `permission:sms-logs-download` (Spatie), NOT in-controller — same pattern as
 * the KYC Access Report (permission:kyc-access-report). Granting is per-user
 * delegable via Roles & Permissions, seeded to Super Admin + Admin by default
 * (see 2026_07_01_000000_seed_sms_logs_download_permission).
 *
 *   GET  system/sms-exports                 index()     — list files
 *   GET  system/sms-exports/download        download()  — presigned URL for ?path=
 *   POST system/sms-exports/generate        generate()  — on-demand range export
 */
class SmsLogExportController extends Controller
{
    public function __construct(private InfobipSmsExportService $exporter)
    {
    }

    /**
     * List the available daily SMS-log exports on the export disk.
     */
    public function index(): JsonResponse
    {
        $disk   = $this->exporter->disk();
        $prefix = $this->exporter->prefix();

        try {
            $paths = Storage::disk($disk)->files($prefix);
        } catch (\Throwable $e) {
            return response()->json([
                'error'  => 'Could not list SMS log exports',
                'detail' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $files = [];
        foreach ($paths as $path) {
            if (!str_ends_with(strtolower($path), '.csv')) {
                continue;
            }
            $size     = null;
            $modified = null;
            try {
                $size     = Storage::disk($disk)->size($path);
                $modified = Storage::disk($disk)->lastModified($path);
            } catch (\Throwable $e) {
                // best-effort metadata; still list the file
            }
            $files[] = [
                'path'          => $path,
                'filename'      => basename($path),
                'size_bytes'    => $size,
                'last_modified' => $modified ? Carbon::createFromTimestamp($modified)->toIso8601String() : null,
            ];
        }

        // Newest first.
        usort($files, fn ($a, $b) => strcmp((string) $b['last_modified'], (string) $a['last_modified']));

        return response()->json([
            'items'          => $files,
            'max_range_days' => (int) config('infobip-sms.max_range_days', 92),
        ]);
    }

    /**
     * Return a short-lived presigned download URL for a chosen export file.
     * Path must sit under the export prefix (no arbitrary S3 key access).
     */
    public function download(Request $request): JsonResponse
    {
        $path = (string) $request->query('path', '');
        if ($path === '') {
            return response()->json(['error' => 'path is required'], Response::HTTP_BAD_REQUEST);
        }

        // Hard containment: only files under our export prefix, only .csv, no
        // traversal. Prevents this endpoint being used as a generic S3 reader.
        $prefix = $this->exporter->prefix();
        if (str_contains($path, '..') || !str_starts_with($path, $prefix . '/') || !str_ends_with(strtolower($path), '.csv')) {
            return response()->json(['error' => 'Invalid path'], Response::HTTP_BAD_REQUEST);
        }

        $disk = $this->exporter->disk();
        if (!Storage::disk($disk)->exists($path)) {
            return response()->json(['error' => 'Not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->presign($disk, $path, basename($path));
    }

    /**
     * On-demand date-range export. Generates a CSV for [start .. end] (capped)
     * and returns a presigned download URL. Reuses the shared service so the
     * output matches the nightly file exactly.
     */
    public function generate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date'   => ['required', 'date'],
        ]);

        $from = Carbon::parse($validated['start_date'])->startOfDay();
        $to   = Carbon::parse($validated['end_date'])->endOfDay();

        if ($to->lessThan($from)) {
            return response()->json(['error' => 'end_date must be on or after start_date'], Response::HTTP_BAD_REQUEST);
        }

        $maxDays = (int) config('infobip-sms.max_range_days', 92);
        // +1 because the range is inclusive of both endpoints.
        if ($from->diffInDays($to) + 1 > $maxDays) {
            return response()->json([
                'error' => "Date range too large. Max {$maxDays} days per export.",
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $result = $this->exporter->exportRange($from, $to);
        } catch (\Throwable $e) {
            return response()->json([
                'error'  => 'Export failed',
                'detail' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        if ($result['rows'] === 0) {
            return response()->json([
                'message' => 'No SMS log records found for the selected date range.',
                'rows'    => 0,
            ], Response::HTTP_OK);
        }

        return $this->presign($this->exporter->disk(), $result['path'], $result['filename'], $result['rows']);
    }

    // ─── helpers ──────────────────────────────────────────────────────────────

    /**
     * Build a presigned-URL JSON response. Falls back to the disk's plain url()
     * when the driver doesn't support temporaryUrl (local dev fallback disk).
     */
    private function presign(string $disk, string $path, string $filename, ?int $rows = null): JsonResponse
    {
        $ttl = (int) config('infobip-sms.signed_url_ttl_minutes', 15);

        try {
            $url = Storage::disk($disk)->temporaryUrl($path, now()->addMinutes($ttl));
        } catch (\Throwable $e) {
            // Local fallback disk (AWS_BUCKET unset) has no temporaryUrl — use
            // the public url instead so dev still works.
            try {
                $url = Storage::disk($disk)->url($path);
                $ttl = 0;
            } catch (\Throwable $e2) {
                return response()->json([
                    'error'  => 'Could not generate download URL',
                    'detail' => $e->getMessage(),
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }

        $payload = [
            'url'        => $url,
            'expires_in' => $ttl * 60,
            'filename'   => $filename,
            'path'       => $path,
        ];
        if ($rows !== null) {
            $payload['rows'] = $rows;
        }

        return response()->json($payload);
    }
}
