<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Models\WordingFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Wordings Manager — single canonical store for org-wide policy wording PDFs.
 *
 * Lives at s3://graphite-documents/static-pdfs/policy-wordings/<category>/<uuid>.pdf
 * Per the org-wide standard at D:\ADRisk\Wordings_Policy.html.
 *
 * Roles (Spatie):
 *   - "Super Admin"            — view, upload, deactivate, reactivate, hard-delete
 *   - "admin"                  — view, upload, deactivate, reactivate
 *   - "developer"              — view, upload, deactivate, reactivate
 *   - everyone else (authed)   — view, download
 */
class WordingsController extends Controller
{
    private const DISK            = 'documents';
    private const S3_PREFIX       = 'static-pdfs/policy-wordings';
    private const MAX_BYTES       = 25 * 1024 * 1024;
    private const SIGNED_URL_TTL  = 15; // minutes

    // ─── Reads (all authed users) ─────────────────────────────────────────────

    public function categories(): JsonResponse
    {
        $counts = WordingFile::alive()
            ->selectRaw('category, COUNT(*) as total, SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active')
            ->groupBy('category')
            ->pluck('total', 'category')
            ->all();

        $activeCounts = WordingFile::alive()
            ->selectRaw('category, COUNT(*) as active')
            ->where('is_active', true)
            ->groupBy('category')
            ->pluck('active', 'category')
            ->all();

        $out = [];
        foreach (WordingFile::CATEGORIES as $key => $label) {
            $out[] = [
                'key'    => $key,
                'label'  => $label,
                'total'  => (int) ($counts[$key] ?? 0),
                'active' => (int) ($activeCounts[$key] ?? 0),
            ];
        }

        return response()->json([
            'categories' => $out,
            'can_manage' => $this->canManage(),
            'can_hard_delete' => $this->canHardDelete(),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $category = $request->query('category');
        $activeOnly = filter_var($request->query('active_only', '0'), FILTER_VALIDATE_BOOLEAN);

        $q = WordingFile::alive()->orderBy('uploaded_at', 'desc');
        if ($category) {
            if (!array_key_exists($category, WordingFile::CATEGORIES)) {
                return response()->json(['error' => 'Unknown category'], Response::HTTP_BAD_REQUEST);
            }
            $q->where('category', $category);
        }
        if ($activeOnly) {
            $q->where('is_active', true);
        }

        $rows = $q->with('uploader:id,email')->paginate(50);

        return response()->json([
            'items' => $rows->items(),
            'meta'  => [
                'current_page' => $rows->currentPage(),
                'last_page'    => $rows->lastPage(),
                'per_page'     => $rows->perPage(),
                'total'        => $rows->total(),
            ],
            'can_manage'      => $this->canManage(),
            'can_hard_delete' => $this->canHardDelete(),
        ]);
    }

    public function download(int $id): JsonResponse
    {
        $row = WordingFile::alive()->find($id);
        if (!$row) {
            return response()->json(['error' => 'Not found'], Response::HTTP_NOT_FOUND);
        }

        try {
            $url = Storage::disk(self::DISK)->temporaryUrl(
                $row->s3_key,
                now()->addMinutes(self::SIGNED_URL_TTL)
            );
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Could not generate download URL',
                'detail' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return response()->json([
            'url'        => $url,
            'expires_in' => self::SIGNED_URL_TTL * 60,
            'filename'   => $row->display_name,
        ]);
    }

    // ─── Writes (admin/dev/super-admin) ───────────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        if (!$this->canManage()) {
            return response()->json(['error' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validate([
            'category'     => ['required', 'string', 'in:' . implode(',', array_keys(WordingFile::CATEGORIES))],
            'product_code' => ['nullable', 'string', 'max:32'],
            'notes'        => ['nullable', 'string', 'max:1000'],
            'file'         => ['required', 'file'],
        ]);

        /** @var UploadedFile $file */
        $file = $request->file('file');

        if ($file->getSize() > self::MAX_BYTES) {
            return response()->json([
                'error' => 'File too large. Max ' . round(self::MAX_BYTES / 1024 / 1024) . ' MB',
            ], Response::HTTP_BAD_REQUEST);
        }

        $mime = $file->getMimeType() ?: $file->getClientMimeType();
        if ($mime !== 'application/pdf' && !str_ends_with(strtolower($file->getClientOriginalName()), '.pdf')) {
            return response()->json(['error' => 'Only PDF files are accepted'], Response::HTTP_BAD_REQUEST);
        }

        $category = $validated['category'];
        $uuid = (string) Str::uuid();
        $s3Key = self::S3_PREFIX . '/' . $category . '/' . $uuid . '.pdf';

        try {
            $stream = fopen($file->getRealPath(), 'rb');
            Storage::disk(self::DISK)->put($s3Key, $stream, ['visibility' => 'private']);
            if (is_resource($stream)) {
                fclose($stream);
            }
        } catch (\Throwable $e) {
            return response()->json([
                'error'  => 'S3 upload failed',
                'detail' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $row = WordingFile::create([
            'category'      => $category,
            'product_code'  => $validated['product_code'] ?? null,
            'display_name'  => $file->getClientOriginalName(),
            's3_key'        => $s3Key,
            'content_hash'  => hash_file('sha256', $file->getRealPath()),
            'size_bytes'    => $file->getSize(),
            'mime_type'     => 'application/pdf',
            'is_active'     => true,
            'notes'         => $validated['notes'] ?? null,
            'uploaded_by'   => Auth::id(),
            'uploaded_at'   => now(),
        ]);

        $this->log('wording.upload', $row, "Uploaded {$row->display_name} → {$category}");

        return response()->json($row->fresh()->load('uploader:id,email'), Response::HTTP_CREATED);
    }

    public function deactivate(int $id): JsonResponse
    {
        if (!$this->canManage()) {
            return response()->json(['error' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }
        $row = WordingFile::alive()->find($id);
        if (!$row) return response()->json(['error' => 'Not found'], Response::HTTP_NOT_FOUND);

        if (!$row->is_active) {
            return response()->json(['error' => 'Already deactivated'], Response::HTTP_CONFLICT);
        }

        $row->update([
            'is_active'      => false,
            'deactivated_by' => Auth::id(),
            'deactivated_at' => now(),
        ]);

        $this->log('wording.deactivate', $row, "Deactivated {$row->display_name}");

        return response()->json($row->fresh()->load('uploader:id,email'));
    }

    public function reactivate(int $id): JsonResponse
    {
        if (!$this->canManage()) {
            return response()->json(['error' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }
        $row = WordingFile::alive()->find($id);
        if (!$row) return response()->json(['error' => 'Not found'], Response::HTTP_NOT_FOUND);

        if ($row->is_active) {
            return response()->json(['error' => 'Already active'], Response::HTTP_CONFLICT);
        }

        $row->update([
            'is_active'      => true,
            'deactivated_by' => null,
            'deactivated_at' => null,
        ]);

        $this->log('wording.reactivate', $row, "Reactivated {$row->display_name}");

        return response()->json($row->fresh()->load('uploader:id,email'));
    }

    public function destroy(int $id): JsonResponse
    {
        if (!$this->canHardDelete()) {
            return response()->json(['error' => 'Only Super Admin can hard-delete'], Response::HTTP_FORBIDDEN);
        }
        $row = WordingFile::alive()->find($id);
        if (!$row) return response()->json(['error' => 'Not found'], Response::HTTP_NOT_FOUND);

        // Soft-mark in DB; S3 versioning keeps the object recoverable.
        $row->update([
            'is_active'  => false,
            'deleted_by' => Auth::id(),
            'deleted_at' => now(),
        ]);

        $this->log('wording.delete', $row, "Hard-deleted {$row->display_name}");

        return response()->json(['ok' => true]);
    }

    // ─── RBAC helpers ─────────────────────────────────────────────────────────

    private function canManage(): bool
    {
        $u = Auth::user();
        if (!$u) return false;
        return $u->hasRole('Super Admin') || $u->hasRole('admin') || $u->hasRole('developer');
    }

    private function canHardDelete(): bool
    {
        $u = Auth::user();
        if (!$u) return false;
        return $u->hasRole('Super Admin');
    }

    private function log(string $action, WordingFile $row, string $message): void
    {
        try {
            activity('Wording')
                ->performedOn($row)
                ->causedBy(Auth::user())
                ->withProperties([
                    'action'   => $action,
                    'category' => $row->category,
                    's3_key'   => $row->s3_key,
                ])
                ->log($message);
        } catch (\Throwable $e) {
            // best-effort audit; never block the user action
            \Log::warning('Wordings audit log failed', ['err' => $e->getMessage()]);
        }
    }
}
