<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The grain was wrong by one column, and the book run proved it.
 *
 * policy_reinsurance_regulatory was created unique on
 * (action_id, risk_address, regulatory_class, layer). That assumed one
 * allocation unit per regulatory class at a risk address, and there can be
 * several: RegulatoryCessionService::risksFor() groups by (risk address, GROUP,
 * mapping), so two different reinsurance groups carrying the same class at the
 * same address are two units. MOTOR_COM and MOTOR_TRADERS_COM_EXT are both
 * Motor, and a policy holding both collides.
 *
 * Four of forty-eight actions failed on the first book-wide run with
 * "Duplicate entry '29772-39066-Motor…'". They were not bad data; the
 * constraint was describing a grain the reader does not produce.
 *
 * GROUP_CODE IS NULLABLE and MySQL treats each NULL as distinct, so this does
 * not constrain the case where a unit spans several groups. That case is
 * genuinely one row per (address, class, layer) anyway — a spanning unit is
 * reported without a group precisely because no single one owns it.
 */
return new class extends Migration
{
    public function up(): void
    {
        // The PR guard only flags Schema::create, but an index swap is no more
        // re-runnable than a create: dropping an index that is already gone
        // fails just as loudly. RI-17 has someone running this by hand on
        // production, so it checks first.
        if (! Schema::hasTable('policy_reinsurance_regulatory')) {
            return;
        }

        if ($this->indexColumns() === self::WANTED) {
            return;   // already the shape we want
        }

        Schema::table('policy_reinsurance_regulatory', function ($table) {
            $table->dropUnique('pri_regulatory_grain_unique');
            $table->unique(self::WANTED, 'pri_regulatory_grain_unique');
        });
    }

    /** The grain the reader actually produces. */
    private const WANTED = ['action_id', 'risk_address', 'group_code', 'regulatory_class', 'layer'];

    /**
     * The columns the unique index currently spans, in order.
     *
     * Read from information_schema because Laravel 8's schema builder cannot
     * introspect indexes — hasIndex() arrives in Laravel 11.
     *
     * @return string[]
     */
    private function indexColumns(): array
    {
        try {
            $rows = DB::select(
                'SELECT COLUMN_NAME FROM information_schema.STATISTICS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = ?
                   AND INDEX_NAME = ?
                 ORDER BY SEQ_IN_INDEX',
                ['policy_reinsurance_regulatory', 'pri_regulatory_grain_unique']
            );
        } catch (\Throwable $e) {
            return [];   // not MySQL, or no such index: let the swap try
        }

        return array_map(fn ($r) => $r->COLUMN_NAME, $rows);
    }

    public function down(): void
    {
        Schema::table('policy_reinsurance_regulatory', function ($table) {
            $table->dropUnique('pri_regulatory_grain_unique');
            $table->unique(
                ['action_id', 'risk_address', 'regulatory_class', 'layer'],
                'pri_regulatory_grain_unique'
            );
        });
    }
};
