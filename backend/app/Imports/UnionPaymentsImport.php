<?php

namespace AlphaDirect\Imports;

use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Reads a union's monthly payment list into heading-keyed rows. Matching to
 * members and the paid/unpaid outcome is decided in UnionPaymentsController so
 * preview and commit share one set of rules.
 *
 * WithHeadingRow slugifies the template headers to:
 *   ID Number → id_number, Name → name, Amount → amount,
 *   Payment Date → payment_date, Reference → reference.
 */
class UnionPaymentsImport implements ToArray, WithHeadingRow
{
    /** @var array<int,array<string,mixed>> */
    public array $rows = [];

    public function array(array $rows): void
    {
        $this->rows = $rows;
    }
}
