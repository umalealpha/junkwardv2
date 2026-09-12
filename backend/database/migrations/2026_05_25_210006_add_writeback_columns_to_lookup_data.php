<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds write-back metadata to lookup_data so external apps (BizSure first)
 * can contribute new dropdown options without losing audit context:
 *
 *   - label         human-readable label as the user typed it. Existing
 *                   rows keep label NULL — GET falls back to value, so
 *                   the on-the-wire response is unchanged for them.
 *   - submitted_at  server timestamp at write time
 *   - submitted_by  free-text identifier (email / quote ref); nullable
 *   - source        origin tag ("bizsure", "manual", ...). Lets ops slice
 *                   pending entries by source without joining audits.
 *
 * Additive + nullable. Existing callers untouched. POST /lookup/{key}
 * (BizSure write-back endpoint) populates these.
 */
class AddWritebackColumnsToLookupData extends Migration
{
    public function up(): void
    {
        Schema::table('lookup_data', function (Blueprint $table) {
            if (! Schema::hasColumn('lookup_data', 'label')) {
                $table->string('label', 120)->nullable();
            }
            if (! Schema::hasColumn('lookup_data', 'submitted_at')) {
                $table->timestamp('submitted_at')->nullable();
            }
            if (! Schema::hasColumn('lookup_data', 'submitted_by')) {
                $table->string('submitted_by', 160)->nullable();
            }
            if (! Schema::hasColumn('lookup_data', 'source')) {
                $table->string('source', 40)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('lookup_data', function (Blueprint $table) {
            foreach (['label', 'submitted_at', 'submitted_by', 'source'] as $col) {
                if (Schema::hasColumn('lookup_data', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
}
