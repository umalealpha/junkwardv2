<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Jobs\RealpaySettlementImportJob;
use AlphaDirect\Models\RealpaySettlementImport;
use AlphaDirect\Services\AuthGate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Dedicated RealPay settlement import screen (preview -> confirm).
 *
 *   POST   realpay-settlement/upload            upload xlsx -> create record -> dispatch PREVIEW (dry-run) job
 *   GET    realpay-settlement/imports           paginated list
 *   GET    realpay-settlement/imports/{id}      one import + preview/commit summary
 *   POST   realpay-settlement/imports/{id}/confirm   dispatch COMMIT job (only when preview_ready)
 *
 * Admin-gated (money import): Super Admin / Manager / Admin only.
 */
class RealpaySettlementImportController extends Controller
{
    private function denyIfNotAdmin(): ?JsonResponse
    {
        $u = auth()->user();
        if (!$u || !method_exists($u, 'hasAnyRole') || !$u->hasAnyRole(AuthGate::ADMIN_ROLES)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }
        return null;
    }

    public function upload(Request $request): JsonResponse
    {
        if ($deny = $this->denyIfNotAdmin()) return $deny;

        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:20480',
        ]);

        $file = $request->file('file');
        $path = $file->store('realpay-settlement-imports');
        $user = auth()->user();

        $import = RealpaySettlementImport::create([
            'file_name'   => $file->getClientOriginalName(),
            'file_path'   => $path,
            'uploaded_by' => trim(($user->firstName ?? '') . ' ' . ($user->lastName ?? $user->email ?? '')),
            'status'      => RealpaySettlementImport::STATUS_UPLOADED,
        ]);

        // Auto-run the dry-run preview in the background.
        RealpaySettlementImportJob::dispatch($import->id, false);

        return response()->json([
            'message' => 'File uploaded. A dry-run preview is now running — refresh to see the summary, then Confirm to import.',
            'data'    => $this->present($import->fresh()),
        ], 201);
    }

    public function index(Request $request): JsonResponse
    {
        if ($deny = $this->denyIfNotAdmin()) return $deny;

        $validated = $request->validate([
            'status'   => 'nullable|string|max:20',
            'search'   => 'nullable|string|max:100',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $results = RealpaySettlementImport::query()
            ->when($validated['status'] ?? null, fn($q, $v) => $q->where('status', $v))
            ->when($validated['search'] ?? null, fn($q, $v) => $q->where(fn($w) =>
                $w->where('file_name', 'like', "%{$v}%")->orWhere('uploaded_by', 'like', "%{$v}%")))
            ->orderByDesc('id')
            ->paginate($validated['per_page'] ?? 25);

        return response()->json([
            'data' => collect($results->items())->map(fn($r) => $this->present($r)),
            'meta' => [
                'total' => $results->total(), 'per_page' => $results->perPage(),
                'current_page' => $results->currentPage(), 'last_page' => $results->lastPage(),
                'from' => $results->firstItem(), 'to' => $results->lastItem(),
            ],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        if ($deny = $this->denyIfNotAdmin()) return $deny;

        $import = RealpaySettlementImport::find($id);
        if (!$import) return response()->json(['error' => 'Import not found'], 404);

        return response()->json(['data' => $this->present($import, true)]);
    }

    public function confirm(int $id): JsonResponse
    {
        if ($deny = $this->denyIfNotAdmin()) return $deny;

        $import = RealpaySettlementImport::find($id);
        if (!$import) return response()->json(['error' => 'Import not found'], 404);

        if ($import->status !== RealpaySettlementImport::STATUS_PREVIEW_READY) {
            return response()->json([
                'error' => "Cannot confirm — import is '{$import->status}'. Only a completed preview can be committed.",
            ], 422);
        }

        $import->update(['status' => RealpaySettlementImport::STATUS_COMMITTING]);
        RealpaySettlementImportJob::dispatch($import->id, true);

        return response()->json([
            'message' => 'Import confirmed — committing in the background. Refresh to see the result.',
            'data'    => $this->present($import->fresh()),
        ]);
    }

    private function present(RealpaySettlementImport $r, bool $withLog = false): array
    {
        $preview = $r->preview_summary ?? [];
        $commit  = $r->commit_summary ?? [];
        if (!$withLog) {
            unset($preview['log_sample'], $commit['log_sample']);
        }
        return [
            'id'             => $r->id,
            'fileName'       => $r->file_name,
            'uploadedBy'     => $r->uploaded_by,
            'status'         => $r->status,
            'rowCount'       => $r->row_count,
            'previewSummary' => $preview ?: null,
            'commitSummary'  => $commit ?: null,
            'error'          => $r->error,
            'createdAt'      => optional($r->created_at)->toDateTimeString(),
            'updatedAt'      => optional($r->updated_at)->toDateTimeString(),
        ];
    }
}
