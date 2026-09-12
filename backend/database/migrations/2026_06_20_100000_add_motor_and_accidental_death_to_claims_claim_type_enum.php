<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds 'Motor' and 'Accidental Death' to the `claims.claim_type` ENUM.
 *
 * The claim-type registration flow was changed so MIS motor claims store
 * claim_type = 'Motor' (was 'Accident') and ADI / HIB / Group AD claims
 * store 'Accidental Death' (was 'Life'). The `claims.claim_type` column is
 * an ENUM, and neither new value was a member — MySQL silently coerces an
 * invalid ENUM insert to '' (or throws under strict mode), so without this
 * migration newly-registered Motor / Accidental Death claims would lose
 * their type. (This also retroactively fixes product-1 ADI, which already
 * stored 'Accidental Death' against an ENUM that never listed it.)
 *
 * Additive only: every existing member — including the legacy 'Accident'
 * and 'Life' — is preserved so historical rows stay valid and remain
 * filterable. The column is read first and the two values appended, making
 * the migration idempotent and safe to re-run.
 *
 * Raw ALTER (not doctrine/dbal change()) mirrors the rest of this repo's
 * enum/column-type changes — the `claims` table predates Laravel migrations
 * here, and dbal mishandles large ENUMs.
 */
class AddMotorAndAccidentalDeathToClaimsClaimTypeEnum extends Migration
{
    /** Values this migration introduces. */
    private array $additions = ['Motor', 'Accidental Death'];

    public function up(): void
    {
        $values = $this->currentEnumValues();
        if ($values === null) {
            return; // column/table absent in this environment — nothing to do
        }

        foreach ($this->additions as $v) {
            if (! in_array($v, $values, true)) {
                $values[] = $v;
            }
        }

        DB::statement(
            'ALTER TABLE claims MODIFY claim_type ' . $this->enumDefinition($values)
        );
    }

    public function down(): void
    {
        $values = $this->currentEnumValues();
        if ($values === null) {
            return;
        }

        // Drop the two values we added. Any rows still holding them would be
        // coerced to '' by MySQL — acceptable for a rollback, but noted.
        $values = array_values(array_filter(
            $values,
            fn ($v) => ! in_array($v, $this->additions, true)
        ));

        DB::statement(
            'ALTER TABLE claims MODIFY claim_type ' . $this->enumDefinition($values)
        );
    }

    /**
     * Read the live ENUM members of claims.claim_type, or null if the
     * column doesn't exist.
     */
    private function currentEnumValues(): ?array
    {
        if (! Schema::hasColumn('claims', 'claim_type')) {
            return null;
        }

        $col = DB::selectOne("SHOW COLUMNS FROM claims LIKE 'claim_type'");
        if (! $col || ! preg_match('/^enum\((.*)\)$/i', $col->Type, $m)) {
            return null;
        }

        // Members are single-quoted and comma-separated; '' escapes a quote.
        return array_map(
            fn ($v) => str_replace("''", "'", $v),
            str_getcsv($m[1], ',', "'")
        );
    }

    /** Build `ENUM('a','b',...) NULL` from a list of members. */
    private function enumDefinition(array $values): string
    {
        $list = implode(',', array_map(
            fn ($v) => "'" . str_replace("'", "''", $v) . "'",
            $values
        ));

        // Column is nullable (matches existing DDL); leave default implicit NULL.
        return "ENUM($list) NULL";
    }
}
