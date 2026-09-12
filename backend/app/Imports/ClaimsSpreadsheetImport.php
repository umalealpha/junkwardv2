<?php

namespace AlphaDirect\Imports;

use Maatwebsite\Excel\Concerns\ToArray;

/**
 * Minimal reader for the bulk-claim import — returns the sheet as a raw 2-D
 * array (row 0 = headers) so the ClaimBulkImportService can drive an explicit
 * admin column-map. We deliberately do NOT use WithHeadingRow: the operator
 * maps arbitrary spreadsheet columns to claim fields in the UI, so we need the
 * literal header row.
 *
 * Reads .xlsx/.xls/.csv (reader type inferred from the file extension by
 * maatwebsite/excel). No model creation happens here — creation is routed
 * through the existing claim-create path in the service.
 */
class ClaimsSpreadsheetImport implements ToArray
{
    public function array(array $array): array
    {
        return $array;
    }
}
