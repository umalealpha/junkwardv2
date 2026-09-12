<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddPolicyIdToTables extends Migration
{
    /**
     * Tables that need policy_id column added
     * These tables currently join on policyNumber (alphanumeric)
     */
    private $tablesToUpdate = [
        'payment_transactions' => ['policyNumber', 'policy_id'],
        'transactions' => ['policyNumber', 'policy_id'],
        'policy_renewals' => ['policyNumber', 'policy_id'],
        'employer_group_policy' => ['policyNumber', 'policy_id'],
        'company_policies' => ['policyNumber', 'policy_id'],
        'cancel_policy_requests' => ['policyNumber', 'policy_id'],
        'vcs_new_transactions' => ['policyNumber', 'policy_id'],
        'policy_applied_discounts' => ['policyNumber', 'policy_id'],
        'policy_payment_schedules' => ['policy_number', 'policy_id'],
        'sms_logs' => ['policyNumber', 'policy_id'],
    ];

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        foreach ($this->tablesToUpdate as $table => $columns) {
            $policyNumberColumn = $columns[0];
            $policyIdColumn = $columns[1];
            
            if (!Schema::hasTable($table)) {
                echo "Table {$table} does not exist, skipping...\n";
                continue;
            }

            // Check if policy_id column already exists
            if (Schema::hasColumn($table, $policyIdColumn)) {
                echo "Column {$table}.{$policyIdColumn} already exists, skipping...\n";
                continue;
            }

            // Add policy_id column
            Schema::table($table, function (Blueprint $table) use ($policyIdColumn, $policyNumberColumn) {
                $table->unsignedBigInteger($policyIdColumn)->nullable()->after($policyNumberColumn);
                $table->index($policyIdColumn, "idx_{$table}_{$policyIdColumn}");
            });

            // Migrate data from policyNumber to policy_id
            try {
                DB::statement("
                    UPDATE {$table} t
                    INNER JOIN policies p ON t.{$policyNumberColumn} = p.policyNumber
                    SET t.{$policyIdColumn} = p.id
                    WHERE t.{$policyIdColumn} IS NULL
                ");
                
                echo "Migrated data for {$table}\n";
            } catch (\Exception $e) {
                echo "Error migrating {$table}: " . $e->getMessage() . "\n";
            }

            // Add foreign key constraint (optional, can be commented out if needed)
            // try {
            //     Schema::table($table, function (Blueprint $table) use ($policyIdColumn) {
            //         $table->foreign($policyIdColumn)
            //               ->references('id')
            //               ->on('policies')
            //               ->onDelete('cascade');
            //     });
            //     echo "Added foreign key for {$table}\n";
            // } catch (\Exception $e) {
            //     echo "Error adding foreign key for {$table}: " . $e->getMessage() . "\n";
            // }
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        foreach ($this->tablesToUpdate as $table => $columns) {
            $policyIdColumn = $columns[1];
            
            if (!Schema::hasTable($table)) {
                continue;
            }

            if (Schema::hasColumn($table, $policyIdColumn)) {
                // Drop foreign key first if exists
                try {
                    Schema::table($table, function (Blueprint $table) use ($policyIdColumn) {
                        $table->dropForeign([$policyIdColumn]);
                    });
                } catch (\Exception $e) {
                    // Foreign key might not exist
                }

                // Drop column
                Schema::table($table, function (Blueprint $table) use ($policyIdColumn) {
                    $table->dropColumn($policyIdColumn);
                });
            }
        }
    }
}
