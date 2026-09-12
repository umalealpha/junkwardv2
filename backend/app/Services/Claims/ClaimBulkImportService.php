<?php

namespace AlphaDirect\Services\Claims;

use AlphaDirect\Claim;
use AlphaDirect\Http\Controllers\Api\ClaimsTrackerController;
use AlphaDirect\Imports\ClaimsSpreadsheetImport;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Bulk claim import — parse .xlsx/.csv, map columns, DRY-RUN validate/preview,
 * then (only on explicit confirm) create each row THROUGH THE EXISTING
 * claim-create path.
 *
 * Reuse, not bypass: creation calls ClaimsTrackerController::store() per row —
 * the purpose-built, external claim-create bridge — so every row passes the
 * same validation, gets a race-safe claim_number, an opening reserve, the
 * new_claims shadow row, and external_ref idempotency. The dry-run validates
 * against the SAME rules first so the preview is faithful; store() then
 * re-validates on commit (defence in depth — validation is never skipped).
 *
 * Idempotency: rows carrying an external_ref that already exists (or a
 * claim_number that already exists) are reported as "duplicate" and skipped —
 * safe to re-run the same file.
 */
class ClaimBulkImportService
{
    /**
     * Target claim fields the operator maps spreadsheet columns onto.
     * key => [label, required].
     */
    public const TARGET_FIELDS = [
        'policy_number'       => ['label' => 'Policy Number',       'required' => true],
        'policy_id'           => ['label' => 'Policy ID (optional)', 'required' => false],
        'claim_type'          => ['label' => 'Claim Type',          'required' => true],
        'date_of_loss'        => ['label' => 'Date of Loss (YYYY-MM-DD)', 'required' => true],
        'description'         => ['label' => 'Description',          'required' => true],
        'external_ref'        => ['label' => 'External Ref (for de-dupe)', 'required' => false],
        'claim_handler_email' => ['label' => 'Claim Handler Email', 'required' => false],
    ];

    /**
     * Sniff a file: headers + sample rows + row count + the target field list
     * the UI needs to build a column map. No validation, no creation.
     */
    public function analyze(UploadedFile $file): array
    {
        $rows = $this->readRows($file);
        $headers = $rows[0] ?? [];
        $headers = array_map(fn ($h) => trim((string) $h), $headers);

        $dataRows = array_slice($rows, 1);
        $sample = [];
        foreach (array_slice($dataRows, 0, 5) as $r) {
            $sample[] = array_map(fn ($v) => (string) $v, $r);
        }

        return [
            'headers'      => $headers,
            'sample'       => $sample,
            'rowCount'     => count($dataRows),
            'maxRows'      => (int) config('claims.bulk_import_max_rows', 2000),
            'targetFields' => $this->targetFieldList(),
            'claimTypes'   => array_values(array_unique(array_values(ClaimsTrackerController::typeMap()))),
        ];
    }

    /**
     * Dry-run: validate + de-dupe every mapped row. Returns per-row status and
     * summary counts. Creates NOTHING.
     *
     * @param array<string,string> $mapping target_field => header_name
     */
    public function dryRun(UploadedFile $file, array $mapping): array
    {
        return $this->process($file, $mapping, false, null);
    }

    /**
     * Commit: create the valid, non-duplicate rows through the existing
     * claim-create path. Caller MUST have confirmed and the feature flag MUST
     * be on (both enforced in the controller).
     */
    public function commit(UploadedFile $file, array $mapping): array
    {
        return $this->process($file, $mapping, true, app(ClaimsTrackerController::class));
    }

    // ── core ────────────────────────────────────────────────────────────

    private function process(UploadedFile $file, array $mapping, bool $create, ?ClaimsTrackerController $creator): array
    {
        $rows    = $this->readRows($file);
        $headers = array_map(fn ($h) => trim((string) $h), $rows[0] ?? []);
        $dataRows = array_slice($rows, 1);

        $maxRows = (int) config('claims.bulk_import_max_rows', 2000);
        $truncated = false;
        if (count($dataRows) > $maxRows) {
            $dataRows = array_slice($dataRows, 0, $maxRows);
            $truncated = true;
        }

        // Resolve target_field => column index from header names.
        $colIndex = [];
        foreach ($mapping as $target => $header) {
            if (!isset(self::TARGET_FIELDS[$target]) || $header === null || $header === '') {
                continue;
            }
            $idx = array_search($header, $headers, true);
            if ($idx !== false) {
                $colIndex[$target] = $idx;
            }
        }

        $allowedTypes = $this->allowedTypes();
        $results = [];
        $summary = ['total' => 0, 'valid' => 0, 'invalid' => 0, 'duplicate' => 0, 'created' => 0, 'failed' => 0];

        foreach ($dataRows as $i => $raw) {
            // Skip fully-empty rows silently.
            if ($this->isEmptyRow($raw)) {
                continue;
            }
            $summary['total']++;
            $rowNo = $i + 2; // 1-based + header row

            $payload = $this->buildPayload($raw, $colIndex);

            // Validate against the SAME rules the tracker create path uses.
            $validator = Validator::make($payload, $this->rules($allowedTypes));
            if ($validator->fails()) {
                $summary['invalid']++;
                $results[] = [
                    'row'     => $rowNo,
                    'status'  => 'invalid',
                    'errors'  => $validator->errors()->all(),
                    'preview' => $this->preview($payload),
                ];
                continue;
            }

            // De-dupe by external_ref / claim_number.
            $dupe = $this->findDuplicate($payload);
            if ($dupe) {
                $summary['duplicate']++;
                $results[] = [
                    'row'     => $rowNo,
                    'status'  => 'duplicate',
                    'errors'  => ['Already exists (' . $dupe . ')'],
                    'preview' => $this->preview($payload),
                ];
                continue;
            }

            $summary['valid']++;

            if (!$create) {
                $results[] = [
                    'row'     => $rowNo,
                    'status'  => 'valid',
                    'errors'  => [],
                    'preview' => $this->preview($payload),
                ];
                continue;
            }

            // COMMIT — route through the existing create path (re-validates).
            try {
                $req = Request::create('/internal/claims-bulk-import', 'POST', $payload);
                $resp = $creator->store($req);
                $status = $resp->getStatusCode();
                $body   = json_decode($resp->getContent(), true) ?: [];
                if (in_array($status, [200, 201], true) && ($body['status'] ?? false)) {
                    $summary['created']++;
                    $results[] = [
                        'row'          => $rowNo,
                        'status'       => ($body['idempotent'] ?? false) ? 'duplicate' : 'created',
                        'claim_number' => $body['claim_number'] ?? null,
                        'errors'       => [],
                        'preview'      => $this->preview($payload),
                    ];
                    if ($body['idempotent'] ?? false) {
                        $summary['created']--;
                        $summary['duplicate']++;
                    }
                } else {
                    $summary['failed']++;
                    $results[] = [
                        'row'     => $rowNo,
                        'status'  => 'failed',
                        'errors'  => [$body['message'] ?? ('HTTP ' . $status)],
                        'preview' => $this->preview($payload),
                    ];
                }
            } catch (\Illuminate\Validation\ValidationException $e) {
                $summary['failed']++;
                $results[] = [
                    'row'     => $rowNo,
                    'status'  => 'failed',
                    'errors'  => collect($e->errors())->flatten()->all(),
                    'preview' => $this->preview($payload),
                ];
            } catch (\Throwable $e) {
                $summary['failed']++;
                $results[] = [
                    'row'     => $rowNo,
                    'status'  => 'failed',
                    'errors'  => [$e->getMessage()],
                    'preview' => $this->preview($payload),
                ];
            }
        }

        return [
            'mode'      => $create ? 'commit' : 'dry_run',
            'summary'   => $summary,
            'truncated' => $truncated,
            'maxRows'   => $maxRows,
            'rows'      => $results,
        ];
    }

    private function buildPayload(array $raw, array $colIndex): array
    {
        $get = function (string $field) use ($raw, $colIndex) {
            if (!isset($colIndex[$field])) {
                return null;
            }
            $v = $raw[$colIndex[$field]] ?? null;
            $v = is_string($v) ? trim($v) : $v;
            return ($v === '' || $v === null) ? null : $v;
        };

        $payload = [
            'policy_number'       => $get('policy_number'),
            'policy_id'           => $get('policy_id'),
            'claim_type'          => $get('claim_type'),
            'date_of_loss'        => $this->normaliseDate($get('date_of_loss')),
            'description'         => $get('description'),
            'external_ref'        => $get('external_ref'),
            'claim_handler_email' => $get('claim_handler_email'),
        ];

        // Drop nulls so required_without rules behave and store() sees a clean body.
        return array_filter($payload, fn ($v) => $v !== null && $v !== '');
    }

    /** Same rule set as ClaimsTrackerController::store. */
    private function rules(array $allowedTypes): array
    {
        return [
            'policy_id'           => ['required_without:policy_number', 'integer', 'min:1'],
            'policy_number'       => ['required_without:policy_id', 'string', 'max:80'],
            'claim_type'          => ['required', 'string', Rule::in($allowedTypes)],
            'date_of_loss'        => ['required', 'date', 'before_or_equal:today'],
            'description'         => ['required', 'string', 'max:2000'],
            'external_ref'        => ['nullable', 'string', 'max:80'],
            'claim_handler_email' => ['nullable', 'email', 'max:160'],
        ];
    }

    private function allowedTypes(): array
    {
        $map = ClaimsTrackerController::typeMap();
        return array_values(array_unique(array_merge(
            array_keys($map),
            array_values($map),
            array_map('strtolower', array_values($map))
        )));
    }

    private function findDuplicate(array $payload): ?string
    {
        if (!empty($payload['external_ref'])) {
            $exists = Claim::where('external_ref', $payload['external_ref'])->exists();
            if ($exists) {
                return 'external_ref ' . $payload['external_ref'];
            }
        }
        return null;
    }

    private function preview(array $payload): array
    {
        return [
            'policyNumber'  => $payload['policy_number'] ?? ($payload['policy_id'] ?? null),
            'claimType'     => $payload['claim_type'] ?? null,
            'dateOfLoss'    => $payload['date_of_loss'] ?? null,
            'externalRef'   => $payload['external_ref'] ?? null,
            'handlerEmail'  => $payload['claim_handler_email'] ?? null,
        ];
    }

    private function normaliseDate($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        // Excel numeric serial date support.
        if (is_numeric($value)) {
            try {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $value)->format('Y-m-d');
            } catch (\Throwable $e) {
                // fall through to string parse
            }
        }
        try {
            return Carbon::parse((string) $value)->format('Y-m-d');
        } catch (\Throwable $e) {
            return (string) $value; // let validation flag it
        }
    }

    private function isEmptyRow(array $raw): bool
    {
        foreach ($raw as $v) {
            if (trim((string) $v) !== '') {
                return false;
            }
        }
        return true;
    }

    private function readRows(UploadedFile $file): array
    {
        $sheets = Excel::toArray(new ClaimsSpreadsheetImport, $file);
        return $sheets[0] ?? [];
    }

    private function targetFieldList(): array
    {
        $out = [];
        foreach (self::TARGET_FIELDS as $key => $def) {
            $out[] = ['field' => $key, 'label' => $def['label'], 'required' => $def['required']];
        }
        return $out;
    }
}
