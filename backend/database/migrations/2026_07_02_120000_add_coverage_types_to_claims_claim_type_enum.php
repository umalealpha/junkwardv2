<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Append coverage-based claim types to the claims.claim_type ENUM (2026-07-02).
     *
     * Requested additions:
     *   PLANTALLRISKS, PROFESSIONALINDEMNITY, TRAVELINSURANCE,
     *   CONTRACTORSALLRISKS, ERECTIONALLRISK, MEDICALMALPRACTICE
     * TRAVELINSURANCE is already a member, so it is skipped automatically
     * (re-declaring it would be a duplicate-value error).
     *
     * Non-destructive + idempotent: reads the live column definition and only
     * APPENDS the missing values at the END of the list. Every existing value
     * keeps its position (and therefore its internal ENUM index, so no row
     * data changes), and the column's NULL-ability + default are preserved
     * exactly as-is. Safe to run more than once.
     */
    private const ADD = [
        'PLANTALLRISKS',
        'PROFESSIONALINDEMNITY',
        'TRAVELINSURANCE',
        'CONTRACTORSALLRISKS',
        'ERECTIONALLRISK',
        'MEDICALMALPRACTICE',
    ];

    // Values this migration is allowed to remove on rollback. TRAVELINSURANCE
    // pre-existed, so it is deliberately NOT here — down() must never drop it.
    private const REMOVABLE = [
        'PLANTALLRISKS',
        'PROFESSIONALINDEMNITY',
        'CONTRACTORSALLRISKS',
        'ERECTIONALLRISK',
        'MEDICALMALPRACTICE',
    ];

    public function up(): void
    {
        $col = $this->enumColumn();
        if (!$col) {
            return; // not the ENUM we expect — leave it alone
        }

        $existing      = $this->enumValues($col->Type);
        $existingUpper = array_map('strtoupper', $existing);

        $toAdd = array_values(array_filter(
            self::ADD,
            fn ($v) => !in_array(strtoupper($v), $existingUpper, true)
        ));
        if (empty($toAdd)) {
            return; // everything already present — no-op
        }

        $values = array_merge($existing, $toAdd);
        DB::statement(
            "ALTER TABLE `claims` MODIFY COLUMN `claim_type` ENUM(" . $this->quoteList($values) . ")" . $this->tail($col)
        );
    }

    public function down(): void
    {
        $col = $this->enumColumn();
        if (!$col) {
            return;
        }

        $removableUpper = array_map('strtoupper', self::REMOVABLE);

        // Never drop a value that rows still use — that would error / corrupt data.
        $inUse = DB::table('claims')
            ->whereIn(DB::raw('UPPER(`claim_type`)'), $removableUpper)
            ->exists();
        if ($inUse) {
            return;
        }

        $existing = $this->enumValues($col->Type);
        $kept     = array_values(array_filter(
            $existing,
            fn ($v) => !in_array(strtoupper($v), $removableUpper, true)
        ));
        if (count($kept) === count($existing)) {
            return; // nothing we added is present — no-op
        }

        DB::statement(
            "ALTER TABLE `claims` MODIFY COLUMN `claim_type` ENUM(" . $this->quoteList($kept) . ")" . $this->tail($col)
        );
    }

    private function enumColumn(): ?object
    {
        $c = DB::selectOne("SHOW COLUMNS FROM `claims` WHERE Field = 'claim_type'");
        return ($c && stripos($c->Type, 'enum(') === 0) ? $c : null;
    }

    /** Parse enum('a','b',...) into an array of values. */
    private function enumValues(string $type): array
    {
        preg_match_all("/'([^']*)'/", $type, $m);
        return $m[1] ?? [];
    }

    private function quoteList(array $values): string
    {
        return implode(',', array_map(fn ($v) => "'" . str_replace("'", "''", $v) . "'", $values));
    }

    /** Reproduce the column's NULL / DEFAULT clause exactly as it currently is. */
    private function tail(object $col): string
    {
        $tail = ($col->Null === 'YES') ? ' NULL' : ' NOT NULL';
        if ($col->Default !== null) {
            $tail .= " DEFAULT '" . str_replace("'", "''", $col->Default) . "'";
        }
        return $tail;
    }
};
