<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Services\Claims\ClaimBulkImportService;
use AlphaDirect\Services\IntegrationSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Bulk claim import (Claims → Admin → Import).
 *
 * Three steps:
 *   1. POST analyze  — sniff the file: headers + sample + target fields.
 *   2. POST preview  — DRY-RUN: validate + de-dupe every mapped row, create nothing.
 *   3. POST commit   — create valid rows through the EXISTING claim-create path.
 *
 * Gating: the `claims_bulk_import` flag defaults OFF. Analyze + preview (dry-run)
 * work for admins regardless, so they can prepare + validate a file; COMMIT is
 * refused unless the flag is ON AND the caller passes confirm=true. Role-gated
 * (Admin | Claims Manager | Super Admin) at the route + re-checked here.
 * Commit is rate-limited at the route.
 */
class ClaimBulkImportController extends Controller
{
    private const MANAGE_ROLES = ['Admin', 'admin', 'Super Admin', 'Claims Manager'];
    private const FLAG = 'claims_bulk_import';

    public function __construct(private ClaimBulkImportService $service)
    {
    }

    public function analyze(Request $request): JsonResponse
    {
        if (!$this->canManage()) {
            return response()->json(['message' => 'Not authorised.'], 403);
        }

        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:10240'],
        ]);

        try {
            $data = $this->service->analyze($request->file('file'));
        } catch (\Throwable $e) {
            Log::warning('[ClaimBulkImport] analyze failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Could not read the file: ' . $e->getMessage()], 422);
        }

        return response()->json([
            'data'   => $data,
            'flagOn' => IntegrationSettings::isEnabled(self::FLAG, false),
        ]);
    }

    public function preview(Request $request): JsonResponse
    {
        if (!$this->canManage()) {
            return response()->json(['message' => 'Not authorised.'], 403);
        }

        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:10240'],
        ]);
        $mapping = $this->resolveMapping($request);
        if (empty($mapping)) {
            return response()->json(['message' => 'A column mapping is required.'], 422);
        }

        try {
            $result = $this->service->dryRun($request->file('file'), $mapping);
        } catch (\Throwable $e) {
            Log::warning('[ClaimBulkImport] preview failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Preview failed: ' . $e->getMessage()], 422);
        }

        return response()->json([
            'data'   => $result,
            'flagOn' => IntegrationSettings::isEnabled(self::FLAG, false),
        ]);
    }

    public function commit(Request $request): JsonResponse
    {
        if (!$this->canManage()) {
            return response()->json(['message' => 'Not authorised.'], 403);
        }

        // Hard gate: import may only create claims when the flag is ON.
        if (!IntegrationSettings::isEnabled(self::FLAG, false)) {
            return response()->json([
                'message' => 'Bulk import is disabled. Enable "Claims Bulk Import" in Admin → Integrations to commit.',
            ], 403);
        }

        $validated = $request->validate([
            'file'    => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:10240'],
            'confirm' => ['required'],
        ]);
        if (!filter_var($validated['confirm'], FILTER_VALIDATE_BOOLEAN)) {
            return response()->json(['message' => 'Explicit confirmation is required to import.'], 422);
        }

        $mapping = $this->resolveMapping($request);
        if (empty($mapping)) {
            return response()->json(['message' => 'A column mapping is required.'], 422);
        }

        try {
            $result = $this->service->commit($request->file('file'), $mapping);
        } catch (\Throwable $e) {
            Log::error('[ClaimBulkImport] commit failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Import failed: ' . $e->getMessage()], 500);
        }

        Log::info('[ClaimBulkImport] commit complete', [
            'by'      => optional(Auth::user())->email,
            'summary' => $result['summary'] ?? null,
        ]);

        return response()->json(['data' => $result, 'message' => 'Import complete.']);
    }

    /**
     * Accept the mapping as either a JSON string (multipart form) or an array.
     * Shape: { target_field: header_name, ... }.
     */
    private function resolveMapping(Request $request): array
    {
        $mapping = $request->input('mapping');
        if (is_string($mapping)) {
            $decoded = json_decode($mapping, true);
            $mapping = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($mapping)) {
            return [];
        }
        // Keep only known target fields with a non-empty header.
        $clean = [];
        foreach ($mapping as $field => $header) {
            if (isset(ClaimBulkImportService::TARGET_FIELDS[$field]) && is_string($header) && trim($header) !== '') {
                $clean[$field] = trim($header);
            }
        }
        return $clean;
    }

    private function canManage(): bool
    {
        $user = Auth::user();
        if (!$user) {
            return false;
        }
        try {
            return $user->hasAnyRole(self::MANAGE_ROLES);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
