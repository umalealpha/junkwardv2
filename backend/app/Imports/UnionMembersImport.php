<?php

namespace AlphaDirect\Imports;

use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Reads an uploaded Union Members spreadsheet into an array of heading-keyed
 * rows. Validation, de-duplication and the union mapping are done in
 * UnionSchemeController::importMembers so the same rules cover preview + commit.
 *
 * WithHeadingRow slugifies the template headers to:
 *   ID Number → id_number, Name → name, Type → type,
 *   Date Of Birth → date_of_birth, Gender → gender, Contact No → contact_no,
 *   Email Address → email_address, Nationality → nationality.
 */
class UnionMembersImport implements ToArray, WithHeadingRow
{
    /** @var array<int,array<string,mixed>> */
    public array $rows = [];

    public function array(array $rows): void
    {
        $this->rows = $rows;
    }
}
