<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seed the claim assessors master list so the new-claim (FNOL) Assessor dropdown
 * populates. The `assessors` table was empty on prod, so the dropdown rendered
 * blank (claims-team v5). Source: the legacy Claims Tracker's own master_data
 * lists (`nonMotorAssessors` + `assessors`) — exactly what the claims team used.
 *
 * Idempotent (skips names that already exist). The claims team maintains the list
 * going forward via the Assessors admin screen. Placeholder entries ("Non-Motor
 * Assessor", "Others", "N/A", "TBA") are intentionally excluded.
 */
return new class extends Migration {
    private const NON_MOTOR = [
        'LMCI',
        'Loss Adjusters Botswana',
        'ANAK Risk Consultant International',
        'Southern Sky',
        'Claim Consult',
        'Nexus',
    ];

    private const MOTOR = [
        'Tumiso Motseko',
        'Lesego Kobe',
        'David Judd - Southern Sky',
    ];

    public function up(): void
    {
        if (!Schema::hasTable('assessors')) {
            return;
        }
        $now = now();

        $seed = function (array $names, string $category) use ($now) {
            $inserted = 0;
            foreach ($names as $name) {
                $name = trim($name);
                if ($name === '') {
                    continue;
                }
                if (DB::table('assessors')->where('name', $name)->exists()) {
                    continue;
                }
                DB::table('assessors')->insert([
                    'name'       => $name,
                    'category'   => $category,
                    'is_active'  => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $inserted++;
            }
            echo "[seed-assessors] {$category}: inserted {$inserted}.\n";
        };

        $seed(self::NON_MOTOR, 'non_motor');
        $seed(self::MOTOR, 'motor');
    }

    public function down(): void
    {
        if (!Schema::hasTable('assessors')) {
            return;
        }
        DB::table('assessors')
            ->whereIn('name', array_merge(self::NON_MOTOR, self::MOTOR))
            ->delete();
    }
};
