<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Append MARINECARGOONCEOFF to the claims.claim_type ENUM (2026-07-21).
     * Same non-destructive, idempotent append approach as the earlier
     * claim-type enum migrations. Safe to re-run.
     */
    private const ADD = [
        'MARINECARGOONCEOFF',
    ];

    public function up(): void
    {
        $col = $this->enumColumn();
        if (!$col) {
            return;
        }

        $existing      = $this->enumValues($col->Type);
        $existingUpper = array_map('strtoupper', $existing);

        $toAdd = array_values(array_filter(
            self::ADD,
            fn ($v) => !in_array(strtoupper($v), $existingUpper, true)
        ));
        if (empty($toAdd)) {
            return;
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

        $removableUpper = array_map('strtoupper', self::ADD);

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
            return;
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

    private function enumValues(string $type): array
    {
        preg_match_all("/'([^']*)'/", $type, $m);
        return $m[1] ?? [];
    }

    private function quoteList(array $values): string
    {
        return implode(',', array_map(fn ($v) => "'" . str_replace("'", "''", $v) . "'", $values));
    }

    private function tail(object $col): string
    {
        $tail = ($col->Null === 'YES') ? ' NULL' : ' NOT NULL';
        if ($col->Default !== null) {
            $tail .= " DEFAULT '" . str_replace("'", "''", $col->Default) . "'";
        }
        return $tail;
    }
};
