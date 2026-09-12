<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Add 'Open' to the claims.status ENUM (2026-06-30).
     *
     * claims.status is a MySQL ENUM in the live DB even though the original
     * create migration declared it as string() — the table predates the
     * migration set (same situation as claims.claim_type). It is currently
     * enum('Pending','Approved','Rejected','Closed','Reopen') NOT NULL
     * DEFAULT 'Pending', so the new 'Open' main status can't be stored
     * without this ALTER.
     *
     * Idempotent + environment-safe: we read whatever values the column
     * already has and just append 'Open', rather than hard-coding the list
     * (so we never drop a value some environment may have added).
     */
    public function up(): void
    {
        $values = $this->currentEnumValues();
        if (empty($values) || in_array('Open', $values, true)) {
            return; // not the expected enum, or 'Open' already present — no-op
        }

        $values[] = 'Open';
        DB::statement(
            "ALTER TABLE `claims` MODIFY COLUMN `status` ENUM(" . $this->quoteList($values) . ") NOT NULL DEFAULT 'Pending'"
        );
    }

    public function down(): void
    {
        $values = $this->currentEnumValues();
        if (empty($values) || !in_array('Open', $values, true)) {
            return;
        }

        // Refuse to drop 'Open' while rows still use it — that would error or
        // silently corrupt those rows. Leave the enum as-is in that case.
        if (DB::table('claims')->where('status', 'Open')->exists()) {
            return;
        }

        $values = array_values(array_filter($values, fn ($v) => $v !== 'Open'));
        DB::statement(
            "ALTER TABLE `claims` MODIFY COLUMN `status` ENUM(" . $this->quoteList($values) . ") NOT NULL DEFAULT 'Pending'"
        );
    }

    /** Parse the current enum('a','b',...) definition into an array of values. */
    private function currentEnumValues(): array
    {
        $col = DB::selectOne("SHOW COLUMNS FROM `claims` WHERE Field = 'status'");
        if (!$col || stripos($col->Type, 'enum(') !== 0) {
            return [];
        }
        preg_match_all("/'([^']*)'/", $col->Type, $m);

        return $m[1] ?? [];
    }

    /** Build a safely-quoted, comma-separated enum value list. */
    private function quoteList(array $values): string
    {
        return implode(',', array_map(fn ($v) => "'" . str_replace("'", "''", $v) . "'", $values));
    }
};
