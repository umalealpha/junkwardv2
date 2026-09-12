<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The motor-accident parity migration added several free-text columns too narrow
 * for what a claimant actually writes — speed_kmh(20), repair_estimate(100),
 * declaration_capacity(100) and friends — and the connection runs with strict
 * mode off, so an over-length value is SILENTLY TRUNCATED on the claim record
 * ("approximately 60 km/h" = 21 chars into a 20-char column). Widen them to 255.
 *
 * Guarded + idempotent: only widens a column that already exists AND is currently
 * narrower. Runs harmlessly (no-op) if the parity columns are not present yet, and
 * touches only claim_accidents (~4k accident claims) — no live policy/financial
 * table.
 */
return new class extends Migration
{
    /** column => target length, on claim_accidents */
    private array $cols = [
        'speed_kmh'            => 255,
        'gross_mass'           => 255,
        'vehicle_colour'       => 255,
        'repair_estimate'      => 255,
        'witness_telephone'    => 255,
        'declaration_capacity' => 255,
    ];

    private function currentLength(string $table, string $col): ?int
    {
        $row = DB::selectOne(
            'SELECT CHARACTER_MAXIMUM_LENGTH AS len FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $col]
        );
        return $row && $row->len !== null ? (int) $row->len : null;
    }

    public function up(): void
    {
        if (!Schema::hasTable('claim_accidents')) {
            return;
        }
        foreach ($this->cols as $col => $len) {
            $cur = $this->currentLength('claim_accidents', $col);
            if ($cur !== null && $cur < $len) {
                DB::statement("ALTER TABLE `claim_accidents` MODIFY `{$col}` VARCHAR({$len}) NULL");
            }
        }
    }

    public function down(): void
    {
        // No-op: narrowing back could truncate data written while widened. The
        // widening is forward-safe and there is nothing to reverse cleanly.
    }
};
