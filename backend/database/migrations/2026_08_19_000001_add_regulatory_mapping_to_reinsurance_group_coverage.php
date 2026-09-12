<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the regulatory mapping that the 2026/27 treaties allocate on.
 *
 * Confirmed by Tlamelo Chimidza on 17 August 2026: "We use regulatory mapping for
 * treaties instead of the coverages." The treaty route, and whether a sum insured
 * test applies at all, both hang off this one value — see RegulatoryCessionCalculator.
 *
 * WHY THE COLUMN SITS ON reinsurance_group_coverage, NOT reinsurance_group.
 * The mapping annexed on 17 August is keyed on the PAIR (s_CoverageCode, group_code),
 * and it has to be: MOTORTRADERSEXTERNAL is mapped to Property against
 * MOTOR_TRADERS_COM and to Motor against MOTOR_TRAILERS_COM. A single coverage
 * therefore carries two different regulatory classes depending on the group it sits
 * in, so the mapping cannot be keyed on the coverage alone, and a group-level column
 * would silently pick one and lose the other. reinsurance_group_coverage is exactly
 * that pair grain.
 *
 * NOTHING READS THIS YET. Adding reference data changes no cession. The engine in
 * PolicyCoverage::getReinsuranceCoverageCalculations() is not cut over until RI-10
 * 'Points to Check' items 10 to 15 are answered in writing — in particular whether
 * the treaty provides capacity above the Schedule A class limits, which is worth
 * 316,182,000 of reported cession on one test policy.
 *
 * Idempotent, and safe to run before the mapping is agreed: the column is nullable,
 * and a NULL mapping is treated by the calculator as unplaced, which is the
 * conservative answer.
 */
return new class extends Migration
{
    private const TABLE  = 'reinsurance_group_coverage';
    private const COLUMN = 'regulatory_mapping';
    private const INDEX  = 'idx_regulatory_mapping';

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        if (! Schema::hasColumn(self::TABLE, self::COLUMN)) {
            // Raw DDL rather than Schema::table() — these legacy reinsurance_* tables
            // predate the schema builder's conventions and the other reinsurance
            // migrations in this directory treat them the same way.
            DB::statement(
                'ALTER TABLE ' . self::TABLE . ' ADD COLUMN ' . self::COLUMN . ' VARCHAR(40) NULL'
                . " COMMENT 'Regulatory class the treaty allocates on. Confirmed 17 Aug 2026.'"
            );
        }

        // The calculator groups a whole policy by this column, so it is a filter on
        // every recompute, not just a reporting field.
        if (! $this->hasIndex(self::TABLE, self::INDEX)) {
            DB::statement('CREATE INDEX ' . self::INDEX . ' ON ' . self::TABLE . ' (' . self::COLUMN . ')');
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        if ($this->hasIndex(self::TABLE, self::INDEX)) {
            DB::statement('ALTER TABLE ' . self::TABLE . ' DROP INDEX ' . self::INDEX);
        }

        if (Schema::hasColumn(self::TABLE, self::COLUMN)) {
            DB::statement('ALTER TABLE ' . self::TABLE . ' DROP COLUMN ' . self::COLUMN);
        }
    }

    private function hasIndex(string $table, string $name): bool
    {
        $row = DB::selectOne(
            'SELECT COUNT(*) AS c FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?',
            [$table, $name]
        );

        return $row && (int) $row->c > 0;
    }
};
