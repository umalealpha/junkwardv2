<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Soft Delete Item (Super Admin only).
 *
 * Soft-deletes a single record from a given table by ID — i.e. stamps the
 * table's `deleted_at` column with the current time instead of removing the
 * row, so it is hidden from normal queries but remains recoverable.
 */
class SoftDeleteController extends Controller
{
    /**
     * POST /api/v1/admin/soft-delete
     * Body: { table_name: string, record_id: int }
     */
    public function softDelete(Request $request): JsonResponse
    {
        // Authorization: Super Admin only.
        $user = auth()->user();
        if (!$user || !$user->hasRole('Super Admin')) {
            return response()->json(['error' => 'Super Admin role required.'], 403);
        }

        // Validation: table name must be a plain identifier, record id a positive integer.
        $validated = $request->validate([
            'table_name' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_]+$/'],
            'record_id'  => ['required', 'integer', 'min:1'],
        ], [
            'table_name.regex' => 'Table name may only contain letters, numbers and underscores.',
        ]);

        $tableName = $validated['table_name'];
        $recordId  = (int) $validated['record_id'];

        // 1. The table must actually exist.
        if (!Schema::hasTable($tableName)) {
            return response()->json(['error' => 'Table "' . $tableName . '" does not exist.'], 404);
        }

        // 2. The table must support soft deletes (have a `deleted_at` column).
        if (!Schema::hasColumn($tableName, 'deleted_at')) {
            return response()->json([
                'error' => 'Table "' . $tableName . '" does not support soft deletes (no "deleted_at" column).',
            ], 422);
        }

        // 3. Determine the primary key column. Default to `id`.
        if (!Schema::hasColumn($tableName, 'id')) {
            return response()->json([
                'error' => 'Table "' . $tableName . '" has no "id" column to identify the record.',
            ], 422);
        }

        // 4. The record must exist.
        $record = DB::table($tableName)->where('id', $recordId)->first();
        if (!$record) {
            return response()->json([
                'error' => 'No record with ID ' . $recordId . ' was found in "' . $tableName . '".',
            ], 404);
        }

        // 5. Guard against re-deleting an already soft-deleted record.
        if (!empty($record->deleted_at)) {
            return response()->json([
                'error' => 'Record ID ' . $recordId . ' in "' . $tableName . '" is already soft deleted.',
            ], 422);
        }

        // 6. Perform the soft delete.
        $now = Carbon::now();
        $update = ['deleted_at' => $now];
        if (Schema::hasColumn($tableName, 'updated_at')) {
            $update['updated_at'] = $now;
        }

        DB::table($tableName)->where('id', $recordId)->update($update);

        // 7. Audit trail. Never let an audit-log failure undo or mask a
        //    successful soft delete — the update above has already committed.
        try {
            activity('SoftDelete')
                ->causedBy($user)
                ->withProperties([
                    'table_name' => $tableName,
                    'record_id'  => $recordId,
                ])
                ->log('Soft deleted record ID ' . $recordId . ' from table "' . $tableName . '"');
        } catch (\Throwable $e) {
            \Log::warning('Soft delete audit log failed: ' . $e->getMessage());
        }

        return response()->json([
            'message' => 'Record ID ' . $recordId . ' in "' . $tableName . '" has been successfully soft deleted.',
            'data'    => ['table_name' => $tableName, 'record_id' => $recordId],
        ], 200);
    }
}
