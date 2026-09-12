<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Extend the Reconciliation Exceptions module (recon_exception_* tables — created in
 * 2026_06_23_000000_create_recon_exception_tables.php) to carry a NEW exception TYPE:
 * the payment -> ledger -> statement reflection tie-out (flag_code 'STATEMENT_REFLECTION',
 * written by recon:statement-reflection-exceptions).
 *
 * The module was seeded with two tightly-scoped enums:
 *   recon_exceptions.flag_code  enum('A','B','C','D')       — RealPay-mandate flags only
 *   recon_exceptions.product    enum('DOMG','COMG')         — mandate reconciliation only
 *
 * The reflection check runs across every product (not just DOMG/COMG) and needs a new
 * flag_code, so we widen both columns to VARCHAR. This is additive and non-destructive:
 * every existing 'A'..'D' / 'DOMG'/'COMG' value is preserved, and the columns become
 * reusable for any future exception type without another enum migration. Same table,
 * same columns, same row shape — only the storage type is relaxed.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('recon_exceptions')) {
            return;
        }
        // Widen enum -> varchar so 'STATEMENT_REFLECTION' and arbitrary product names fit.
        // MODIFY is idempotent; safe to re-run.
        DB::statement("ALTER TABLE `recon_exceptions` MODIFY `flag_code` VARCHAR(40) NOT NULL");
        // product holds a product NAME for reflection exceptions (not just DOMG/COMG),
        // so give it comfortable headroom to avoid silent truncation under strict=false.
        DB::statement("ALTER TABLE `recon_exceptions` MODIFY `product` VARCHAR(120) NOT NULL");
    }

    public function down(): void
    {
        if (!Schema::hasTable('recon_exceptions')) {
            return;
        }
        // Restore the original tightly-scoped enums. Any rows outside the original set
        // (e.g. STATEMENT_REFLECTION reflection exceptions) should be removed first.
        DB::table('recon_exceptions')->where('flag_code', 'STATEMENT_REFLECTION')->delete();
        DB::statement("ALTER TABLE `recon_exceptions` MODIFY `flag_code` ENUM('A','B','C','D') NOT NULL");
        DB::statement("ALTER TABLE `recon_exceptions` MODIFY `product` ENUM('DOMG','COMG') NOT NULL");
    }
};
