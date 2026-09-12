<?php
namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Models\UploadedExcelFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BatchProcessingController extends Controller
{
    public function report(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'remarks' => 'nullable|string|in:Policy Create,Policy Cancellation',
            'search' => 'nullable|string|max:100',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $query = UploadedExcelFile::query()
            ->when($validated['remarks'] ?? null, fn($q, $v) => $q->where('remarks', $v))
            ->when($validated['search'] ?? null, fn($q, $search) => $q->where('file_name', 'like', "%{$search}%"))
            ->orderBy('id', 'desc');

        $results = $query->paginate($validated['per_page'] ?? 25);

        return response()->json([
            'data' => $results->map(fn($f) => [
                'id' => $f->id,
                'fileName' => $f->file_name,
                'filePath' => $f->file_path,
                'uploadedBy' => $f->uploaded_by,
                'status' => $f->status,
                'remarks' => $f->remarks,
                'reportFile' => $f->report_file,
                'createdAt' => optional($f->created_at)->toIso8601String(),
            ]),
            'meta' => [
                'total' => $results->total(),
                'per_page' => $results->perPage(),
                'current_page' => $results->currentPage(),
                'last_page' => $results->lastPage(),
                'from' => $results->firstItem(),
                'to' => $results->lastItem(),
            ],
        ]);
    }
}
