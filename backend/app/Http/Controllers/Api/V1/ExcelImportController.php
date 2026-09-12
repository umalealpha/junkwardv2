<?php
namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Imports\ExcelImportPolicyActivation;
use AlphaDirect\Imports\ExcelImportPolicyCancellation;
use AlphaDirect\Jobs\ExcelImportForPolicyActivateJob;
use AlphaDirect\Jobs\ExcelImportPolicyCancellationJob;
use AlphaDirect\Models\UploadedExcelFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class ExcelImportController extends Controller
{
    private const STATUS_REMARKS = [
        1 => 'Policy Activation',
        2 => 'Policy Cancellation',
        3 => 'DPO Refund',
    ];

    /**
     * List uploaded Excel files (used for policy activation, cancellation, batch processing).
     * Table: uploaded_excel_files
     * Filter by status: 1=Activation, 2=Cancellation, 3=DPO Refund
     */
    public function activities(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'remarks'  => 'nullable|string|max:100',
            'status'   => 'nullable|integer|in:1,2,3',
            'search'   => 'nullable|string|max:100',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $query = DB::table('uploaded_excel_files')
            ->when($validated['remarks'] ?? null, fn($q, $v) => $q->where('remarks', $v))
            ->when($validated['status'] ?? null, fn($q, $v) => $q->where('remarks', self::STATUS_REMARKS[$v] ?? ''))
            ->when($validated['search'] ?? null, function ($q, $search) {
                $q->where(function ($q) use ($search) {
                    $q->where('file_name', 'like', "%{$search}%")
                      ->orWhere('uploaded_by', 'like', "%{$search}%");
                });
            })
            ->orderBy('id', 'desc');

        $results = $query->paginate($validated['per_page'] ?? 25);

        return response()->json([
            'data' => collect($results->items())->map(fn($r) => [
                'id'           => $r->id,
                'uploadedFile' => $r->file_name,
                'filePath'     => $r->file_path,
                'performFile'  => $r->report_file,
                'status'       => $r->status,
                'statusLabel'  => $r->remarks ?? $r->status ?? 'Unknown',
                'addedBy'      => $r->uploaded_by,
                'createdAt'    => $r->created_at,
            ]),
            'meta' => [
                'total'        => $results->total(),
                'per_page'     => $results->perPage(),
                'current_page' => $results->currentPage(),
                'last_page'    => $results->lastPage(),
                'from'         => $results->firstItem(),
                'to'           => $results->lastItem(),
            ],
        ]);
    }

    /**
     * Upload an Excel/CSV file for bulk policy activation or cancellation.
     * 1. Stores the file to local disk
     * 2. Runs the import class to queue ExcelImportForPolicy records
     * 3. Dispatches the processing job
     * 4. Returns the created UploadedExcelFile record
     */
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:20480',
            'type' => 'required|in:activation,cancellation',
        ]);

        $file     = $request->file('file');
        $fileName = $file->getClientOriginalName();
        $path     = $file->store('excel-imports');
        $user     = auth()->user();
        $remarks  = $request->type === 'activation' ? 'Policy Activation' : 'Policy Cancellation';

        $uploaded = UploadedExcelFile::create([
            'file_name'   => $fileName,
            'file_path'   => $path,
            'uploaded_by' => trim(($user->firstName ?? '') . ' ' . ($user->lastName ?? $user->email)),
            'status'      => 0,
            'remarks'     => $remarks,
        ]);

        try {
            if ($request->type === 'activation') {
                Excel::import(new ExcelImportPolicyActivation, $path);
                ExcelImportForPolicyActivateJob::dispatch();
            } else {
                Excel::import(new ExcelImportPolicyCancellation, $path);
                ExcelImportPolicyCancellationJob::dispatch();
            }
        } catch (\Exception $e) {
            $uploaded->update(['remarks' => $remarks . ' [Error]', 'status' => 99]);
            return response()->json([
                'error'   => 'Import failed: ' . $e->getMessage(),
                'file_id' => $uploaded->id,
            ], 422);
        }

        return response()->json([
            'message'  => 'File uploaded and queued for processing.',
            'id'       => $uploaded->id,
            'fileName' => $fileName,
            'remarks'  => $remarks,
        ], 201);
    }
}
