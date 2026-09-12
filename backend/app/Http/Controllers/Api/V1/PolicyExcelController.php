<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Exports\BeneficiaryTemplateExport;
use AlphaDirect\Exports\EditPolicyExport;
use AlphaDirect\Exports\RiskAddressTemplateExport;
use AlphaDirect\Exports\SpecifiedItemsExport;
use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Imports\EditPolicyImport;
use AlphaDirect\Imports\MemberImport;
use AlphaDirect\Imports\RiskAddressImport;
use AlphaDirect\Imports\SpecifiedItemsImport;
use AlphaDirect\Models\PolicyAction;
use AlphaDirect\Policy;
use AlphaDirect\PolicyBeneficiary;
use AlphaDirect\PolicyTerm;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

/**
 * PolicyExcelController
 *
 * Handles Excel template downloads and data imports for large DOM/COM policies
 * (policies with 20–30 risk addresses, each with many coverages, specified items,
 * beneficiaries, etc.).
 *
 * Endpoints:
 *   GET  /api/v1/policies/{id}/excel-template/{type}
 *   POST /api/v1/policies/{id}/excel-import/{type}
 *
 * Supported types: coverages | specified-items | beneficiaries
 */
class PolicyExcelController extends Controller
{
    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Get the term/action IDs a template/import should target.
     *
     * When $requestedActionId is given (e.g. the action_id an import request
     * was made against, from the page URL), and it identifies a real,
     * non-deleted action belonging to THIS policy, that action (and its own
     * term_id) is used — so importing while viewing an older action attaches
     * rows to that action instead of silently jumping to whatever is
     * currently "latest". Falls back to the latest term/action (previous,
     * still-used behaviour) when no valid action_id is supplied, which keeps
     * every other caller (template downloads, full export) unchanged.
     */
    private function getCurrentTermAction(Policy $policy, ?int $requestedActionId = null): array
    {
        if ($requestedActionId) {
            // PolicyAction uses SoftDeletes, so this already excludes
            // soft-deleted actions via the model's default global scope.
            $action = PolicyAction::where('id', $requestedActionId)
                ->where('policy_id', $policy->id)
                ->first();

            if ($action) {
                return [
                    'term_id'   => $action->term_id,
                    'action_id' => $action->id,
                ];
            }
            // Requested action doesn't exist / doesn't belong to this policy
            // / is soft-deleted — fall through to the "latest" behaviour below.
        }

        $term   = PolicyTerm::where('policy_id', $policy->id)->orderBy('id', 'desc')->first();
        $action = PolicyAction::where('policy_id', $policy->id)->orderBy('id', 'desc')->first();

        return [
            'term_id'   => $term?->id,
            'action_id' => $action?->id,
        ];
    }

    /**
     * Sanitise policy number for use as a filename component.
     */
    private function safeFilename(Policy $policy): string
    {
        return preg_replace('/[^a-zA-Z0-9_\-]/', '_', $policy->policyNumber ?? (string) $policy->id);
    }

    // ─── Template Downloads ────────────────────────────────────────────────────

    /**
     * GET /api/v1/policies/{id}/excel-template/{type}
     *
     * Download a blank (or risk-address pre-populated) Excel template.
     * The frontend passes type = coverages | specified-items | beneficiaries
     */
    public function downloadTemplate(int $id, string $type)
    {
        $policy = Policy::findOrFail($id);
        $ta     = $this->getCurrentTermAction($policy);
        $termId   = $ta['term_id'];
        $actionId = $ta['action_id'];
        $base = $this->safeFilename($policy);

        switch ($type) {
            case 'risk-address':
                // Single-sheet template with all risk address columns
                return Excel::download(
                    new RiskAddressTemplateExport($policy, $termId, $actionId),
                    "{$base}_risk_addresses_template.xlsx"
                );

            case 'coverages':
                // Multi-sheet workbook: ApplicantInfo + SubCompany + RiskAddress + one sheet per coverage
                // Risk addresses are pre-populated from the database so agents just fill amounts.
                return Excel::download(
                    new EditPolicyExport($policy, $termId, $actionId),
                    "{$base}_coverage_template.xlsx"
                );

            case 'specified-items':
                // Multi-sheet workbook: one sheet per coverage type
                return Excel::download(
                    new SpecifiedItemsExport($policy, $termId, $actionId),
                    "{$base}_specified_items_template.xlsx"
                );

            case 'beneficiaries':
                // Empty single-sheet template with the correct column headers
                return Excel::download(
                    new BeneficiaryTemplateExport(),
                    "{$base}_beneficiaries_template.xlsx"
                );

            default:
                return response()->json(['error' => 'Invalid template type.'], 400);
        }
    }

    /**
     * GET /api/v1/policies/{id}/export-full
     *
     * Export the FULL policy (all coverages, sub-coverages, extensions, specified items,
     * risk addresses, applicant info) as an Excel workbook that can be re-imported back
     * to recreate the policy in another environment or as a backup.
     *
     * Same multi-sheet structure as the coverage template, but with all values filled in.
     */
    public function exportFullPolicy(int $id)
    {
        $policy = Policy::with(['customer', 'profile.company', 'product'])->findOrFail($id);
        $ta     = $this->getCurrentTermAction($policy);
        $termId   = $ta['term_id'];
        $actionId = $ta['action_id'];
        $base     = $this->safeFilename($policy);
        $filename = "{$base}_full_policy_export_" . now()->format('Y-m-d_His') . '.xlsx';

        return Excel::download(
            new EditPolicyExport($policy, $termId, $actionId),
            $filename
        );
    }

    // ─── Data Imports ──────────────────────────────────────────────────────────

    /**
     * POST /api/v1/policies/{id}/excel-import/{type}
     *
     * Accept an uploaded Excel file and import data into the policy.
     * Returns a JSON response indicating success or failure with details.
     */
    public function importData(Request $request, int $id, string $type): JsonResponse
    {
        $request->validate([
            'file'      => 'required|file|mimes:xlsx,xls|max:51200', // max 50 MB
            // Optional: target a specific (e.g. older) action instead of
            // whatever is currently "latest" for this policy — see
            // getCurrentTermAction(). Sent by the frontend when the wizard
            // was opened against an explicit action_id from the page URL.
            'action_id' => 'nullable|integer',
        ]);

        if (!in_array($type, ['risk-address', 'coverages', 'specified-items', 'beneficiaries'])) {
            return response()->json(['error' => 'Invalid import type.'], 400);
        }

        $policy = Policy::findOrFail($id);
        $requestedActionId = $request->input('action_id');
        $ta     = $this->getCurrentTermAction($policy, $requestedActionId !== null ? (int) $requestedActionId : null);
        $termId   = $ta['term_id'];
        $actionId = $ta['action_id'];

        if (!$termId || !$actionId) {
            return response()->json([
                'error' => 'Policy has no term or action record — cannot import. Save the policy first.',
            ], 422);
        }

        // Store the uploaded file temporarily on local disk (only for reading during import)
        $file = $request->file('file');
        $path = $file->store('excel-policy-imports', 'local');
        $fullPath = Storage::disk('local')->path($path);

        try {
            switch ($type) {
                case 'risk-address':
                    // All-or-nothing, mirroring the specified-items branch
                    // below: run inside a transaction, collect every row
                    // error (blank required fields via onFailure(), unknown
                    // Risk City/District via getErrors()), and if ANY exist
                    // roll back (import nothing) and return them so the
                    // operator can fix them all at once and re-upload.
                    // Without this, a bad row later in the file left earlier
                    // valid rows permanently committed while the operator was
                    // told "nothing was imported" (false).
                    $riskAddressImporter = new RiskAddressImport($policy, $termId, $actionId);
                    DB::beginTransaction();
                    try {
                        Excel::import($riskAddressImporter, $fullPath);
                    } catch (\Throwable $t) {
                        DB::rollBack();
                        throw $t; // handled by the outer catch (generic failure)
                    }
                    $rowErrors = $riskAddressImporter->getErrors();
                    if (!empty($rowErrors)) {
                        DB::rollBack();
                        Storage::disk('local')->delete($path);

                        Log::warning('PolicyExcel: risk-address import rejected (row errors)', [
                            'policy_id'   => $id,
                            'error_count' => count($rowErrors),
                        ]);

                        return response()->json([
                            'error'     => 'Import cancelled — ' . count($rowErrors) . ' row error(s) found. Nothing was imported; fix these and re-upload.',
                            'rowErrors' => $rowErrors,
                        ], 422);
                    }
                    DB::commit();
                    $message = 'Risk addresses imported successfully.';
                    break;

                case 'coverages':
                    // Wrapped in a transaction, mirroring the risk-address/specified-items
                    // branches above: EditPolicyImport's per-sheet importers save() each row
                    // as it is read, so without this a failure partway through the workbook
                    // (e.g. an unknown coverage on a later sheet) left earlier sheets'
                    // rows already committed while the operator was told "nothing was
                    // imported". EditPolicyImport doesn't expose a getErrors()-style
                    // collection like the other importers, so there's no row-error report
                    // here — only the all-or-nothing rollback-on-failure guarantee.
                    $importer = new EditPolicyImport($policy, $termId, $actionId);
                    $importer->loadSheetNamesFromFile($fullPath);
                    DB::beginTransaction();
                    try {
                        Excel::import($importer, $fullPath);
                    } catch (\Throwable $t) {
                        DB::rollBack();
                        throw $t; // handled by the outer catch (generic failure)
                    }
                    DB::commit();
                    $message = 'Coverage data imported successfully.';
                    break;

                case 'specified-items':
                    // All-or-nothing: run inside a transaction, collect every
                    // row error across all sheets, and if ANY exist roll back
                    // (import nothing) and return them grouped by sheet/coverage
                    // so the operator can fix them all at once and re-upload.
                    $specifiedImporter = new SpecifiedItemsImport($policy, $termId, $actionId);
                    DB::beginTransaction();
                    try {
                        Excel::import($specifiedImporter, $fullPath);
                    } catch (\Throwable $t) {
                        DB::rollBack();
                        throw $t; // handled by the outer catch (generic failure)
                    }
                    $rowErrors = $specifiedImporter->getErrors();
                    if (!empty($rowErrors)) {
                        DB::rollBack();
                        Storage::disk('local')->delete($path);

                        // Group by sheet (= coverage code) for the frontend.
                        $sheetErrors = [];
                        foreach ($rowErrors as $err) {
                            $sheet = ($err['sheet'] ?? '') !== '' ? $err['sheet'] : 'Unknown';
                            $sheetErrors[$sheet][] = [
                                'row'     => $err['row'],
                                'message' => $err['message'],
                            ];
                        }

                        Log::warning('PolicyExcel: specified-items import rejected (row errors)', [
                            'policy_id'   => $id,
                            'error_count' => count($rowErrors),
                            'sheets'      => array_keys($sheetErrors),
                        ]);

                        return response()->json([
                            'error'       => 'Import cancelled — ' . count($rowErrors) . ' row error(s) found across ' . count($sheetErrors) . ' sheet(s). Nothing was imported; fix these and re-upload.',
                            'sheetErrors' => $sheetErrors,
                        ], 422);
                    }
                    DB::commit();
                    $message = 'Specified items imported successfully.';
                    break;

                case 'beneficiaries':
                    // Replace all existing beneficiaries for this term/action
                    PolicyBeneficiary::where('policy_id', $policy->id)
                        ->where('term_id', $termId)
                        ->where('action_id', $actionId)
                        ->delete();

                    Excel::import(new MemberImport($policy, $termId, $actionId), $fullPath);
                    $message = 'Beneficiary data imported successfully.';
                    break;

                default:
                    Storage::disk('local')->delete($path);
                    return response()->json(['error' => 'Invalid import type.'], 400);
            }

            Storage::disk('local')->delete($path);

            Log::info("PolicyExcel: {$type} import succeeded", [
                'policy_id' => $id,
                'term_id'   => $termId,
                'action_id' => $actionId,
                'file'      => $file->getClientOriginalName(),
            ]);

            return response()->json(['message' => $message]);

        } catch (\Maatwebsite\Excel\Validators\ValidationException $ve) {
            Storage::disk('local')->delete($path);

            $failures = collect($ve->failures())->map(fn ($f) => [
                'row'    => $f->row(),
                'errors' => $f->errors(),
            ])->values()->toArray();

            return response()->json([
                'error'    => 'Validation errors in the uploaded file.',
                'failures' => $failures,
            ], 422);

        } catch (\Throwable $e) {
            // Catch \Throwable (not just \Exception) so low-level \Error types
            // (e.g. TypeError) rethrown from the inner block are handled here
            // and the transaction stays rolled back. The raw technical message
            // is logged server-side only — the user gets a generic message,
            // never internal error details.
            Storage::disk('local')->delete($path);

            Log::error("PolicyExcel: {$type} import failed", [
                'policy_id' => $id,
                'error'     => $e->getMessage(),
                'trace'     => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Import failed due to an unexpected error. Nothing was imported. Please try again, and contact support if it persists.',
            ], 422);
        }
    }
}
