<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Per-year policy number counters, plus the column that carries a Travel
 * policy number on a bound MAPFRE contract.
 *
 * Travel numbers are TRVL{YYYY}{NNNNNN} and must restart at 000001 every year,
 * which the MIS pattern (max(policies.id) + 1) cannot express — it follows the
 * global auto-increment and races. AlphaDirect\Services\Travel\
 * TravelPolicyNumberGenerator hands numbers out from these rows instead, one
 * row per (prefix, year), claimed with a single atomic
 * "SET last_sequence = LAST_INSERT_ID(last_sequence + 1)" statement.
 *
 * Deliberately generic: prefix is a column, not a TRVL-only table, so the MIS
 * book can move onto the same mechanism later without another migration.
 *
 * The counters live on the DEFAULT connection, beside policies — the generator
 * takes the counter lock and checks policies.policyNumber in one transaction,
 * which only holds if both sit on the same connection.
 *
 * Uniqueness is enforced by the database, not just by the generator:
 *   - UNIQUE (prefix, period_year)                 one counter per year
 *   - UNIQUE policies.policyNumber                 already present; asserted here
 *   - UNIQUE mapfre_quote_submissions.policy_number  added here
 *
 * Guarded per the self-healing migration standard — safe to re-run.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('policy_number_sequences')) {
            Schema::create('policy_number_sequences', function (Blueprint $table) {
                $table->id();

                // Book prefix — 'TRVL' for Travel, room for 'MIS' etc. later.
                $table->string('prefix', 8);

                // The year the sequence belongs to. A new year starts its own
                // row, which is what makes the sequence restart at 000001.
                $table->unsignedSmallInteger('period_year');

                // Last sequence handed out. 0 means "nothing issued yet", so
                // the first number of the year is 000001.
                $table->unsignedInteger('last_sequence')->default(0);

                $table->timestamps();

                // One counter per book per year. The generator's insertOrIgnore
                // seeding leans on this key when two requests race to open a
                // year, so it must be UNIQUE and not merely indexed.
                $table->unique(['prefix', 'period_year'], 'policy_number_sequences_book_year_unique');
            });
        }

        // The audit row for a bound MAPFRE contract now also carries OUR policy
        // number, so the travel journey has an Alpha Direct reference to quote
        // back to the customer alongside MAPFRE's contractNumber.
        $system = Schema::connection('mysql_system');

        if ($system->hasTable('mapfre_quote_submissions')
            && !$system->hasColumn('mapfre_quote_submissions', 'policy_number')) {
            $system->table('mapfre_quote_submissions', function (Blueprint $table) {
                $table->string('policy_number', 20)->nullable()->after('mapfre_contract_number');
                // NULL repeats freely in a MySQL unique index, so unbound and
                // failed submissions are unaffected — but no two bound travel
                // contracts can ever share a number.
                $table->unique('policy_number', 'mapfre_quote_submissions_policy_number_unique');
            });
        }

        $this->assertPoliciesPolicyNumberIsUnique();
    }

    public function down(): void
    {
        $system = Schema::connection('mysql_system');

        if ($system->hasColumn('mapfre_quote_submissions', 'policy_number')) {
            $system->table('mapfre_quote_submissions', function (Blueprint $table) {
                $table->dropUnique('mapfre_quote_submissions_policy_number_unique');
                $table->dropColumn('policy_number');
            });
        }

        Schema::dropIfExists('policy_number_sequences');
    }

    /**
     * policies.policyNumber already carries a UNIQUE index in every environment
     * we know of (idx_policies_policyNumber_unique). Add it only if some
     * environment is missing it, and only when the data is actually clean — a
     * 200k-row table with duplicates must fail loudly with the duplicate count
     * rather than half-apply an index.
     */
    private function assertPoliciesPolicyNumberIsUnique(): void
    {
        if (!Schema::hasTable('policies')) {
            return;
        }

        $unique = DB::selectOne(
            'SELECT COUNT(*) AS c FROM information_schema.STATISTICS
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = ?
                AND COLUMN_NAME = ?
                AND NON_UNIQUE = 0
                AND INDEX_NAME <> ?',
            ['policies', 'policyNumber', 'PRIMARY']
        );

        if ((int) ($unique->c ?? 0) > 0) {
            return;
        }

        $duplicates = DB::selectOne(
            'SELECT COUNT(*) AS c FROM (
                SELECT policyNumber FROM policies
                 WHERE policyNumber IS NOT NULL AND policyNumber <> ""
                 GROUP BY policyNumber HAVING COUNT(*) > 1
             ) d'
        );

        $count = (int) ($duplicates->c ?? 0);

        if ($count > 0) {
            throw new RuntimeException(
                "Cannot add UNIQUE index on policies.policyNumber: {$count} duplicated "
                . 'policy numbers must be resolved first.'
            );
        }

        Schema::table('policies', function (Blueprint $table) {
            $table->unique('policyNumber', 'idx_policies_policyNumber_unique');
        });
    }
};
