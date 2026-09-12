<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Unique index on employer_groups.employer_group_id (the short code, e.g.
 * D5RSCM). The code generator is a check-then-insert loop with no DB
 * constraint behind it — two concurrent creates can race to the same code.
 * The API layer also joins campaigns/KYC/policies on this code, so
 * uniqueness is a correctness requirement, not an optimisation.
 *
 * Idempotent: skips if the index already exists. Fail-soft: if legacy data
 * already contains duplicate codes, the index is NOT added (logged loudly)
 * so the deploy doesn't die — dedupe manually, then re-run.
 */
return new class extends Migration
{
    private const INDEX = 'employer_groups_employer_group_id_unique';

    public function up(): void
    {
        if (!Schema::hasTable('employer_groups') || $this->indexExists()) {
            return;
        }

        $dupes = DB::table('employer_groups')
            ->select('employer_group_id', DB::raw('COUNT(*) as cnt'))
            ->whereNotNull('employer_group_id')
            ->groupBy('employer_group_id')
            ->having('cnt', '>', 1)
            ->pluck('employer_group_id');

        if ($dupes->isNotEmpty()) {
            Log::warning('employer_groups unique index SKIPPED — duplicate codes exist; dedupe then re-run migration', [
                'duplicate_codes' => $dupes->all(),
            ]);
            return;
        }

        Schema::table('employer_groups', function ($table) {
            $table->unique('employer_group_id', self::INDEX);
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('employer_groups') && $this->indexExists()) {
            Schema::table('employer_groups', function ($table) {
                $table->dropUnique(self::INDEX);
            });
        }
    }

    private function indexExists(): bool
    {
        return !empty(DB::select(
            'SELECT 1 FROM information_schema.statistics
             WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ? LIMIT 1',
            ['employer_groups', self::INDEX]
        ));
    }
};
