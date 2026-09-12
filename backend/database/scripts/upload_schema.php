<?php

/**
 * Schema Upload Helper
 * 
 * This script helps analyze your database schema.
 * You can either:
 * 1. Export your schema using mysqldump and save it as schema.sql
 * 2. Or run this script which will analyze the current database
 */

require __DIR__ . '/../../vendor/autoload.php';

$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

echo "=== Database Schema Analyzer ===\n\n";

try {
    $dbName = DB::getDatabaseName();
    echo "Connected to database: {$dbName}\n\n";
    
    // Get all tables
    $tables = DB::select("SHOW TABLES");
    $tableKey = "Tables_in_{$dbName}";
    
    $schema = [
        'database' => $dbName,
        'tables' => [],
        'analyzed_at' => date('Y-m-d H:i:s')
    ];
    
    foreach ($tables as $table) {
        $tableName = $table->$tableKey;
        echo "Analyzing table: {$tableName}...\n";
        
        // Get table structure
        $columns = DB::select("DESCRIBE {$tableName}");
        
        $tableInfo = [
            'name' => $tableName,
            'columns' => [],
            'indexes' => [],
            'has_policyNumber' => false,
            'has_policy_id' => false,
            'policyNumber_column' => null,
            'policy_id_column' => null
        ];
        
        foreach ($columns as $column) {
            $colInfo = [
                'name' => $column->Field,
                'type' => $column->Type,
                'null' => $column->Null,
                'key' => $column->Key,
                'default' => $column->Default,
                'extra' => $column->Extra
            ];
            
            $tableInfo['columns'][] = $colInfo;
            
            // Check for policyNumber
            if (strtolower($column->Field) === 'policynumber' || strtolower($column->Field) === 'policy_number') {
                $tableInfo['has_policyNumber'] = true;
                $tableInfo['policyNumber_column'] = $column->Field;
            }
            
            // Check for policy_id
            if (strtolower($column->Field) === 'policy_id') {
                $tableInfo['has_policy_id'] = true;
                $tableInfo['policy_id_column'] = $column->Field;
            }
        }
        
        // Get indexes
        $indexes = DB::select("SHOW INDEXES FROM {$tableName}");
        foreach ($indexes as $index) {
            $tableInfo['indexes'][] = [
                'name' => $index->Key_name,
                'column' => $index->Column_name,
                'unique' => $index->Non_unique == 0
            ];
        }
        
        $schema['tables'][] = $tableInfo;
    }
    
    // Save schema to JSON file
    $schemaFile = storage_path('app/schema_analysis_' . date('Y-m-d_His') . '.json');
    File::put($schemaFile, json_encode($schema, JSON_PRETTY_PRINT));
    
    echo "\n=== Analysis Complete ===\n";
    echo "Schema saved to: {$schemaFile}\n\n";
    
    // Generate summary
    $tablesWithPolicyNumber = array_filter($schema['tables'], function($table) {
        return $table['has_policyNumber'];
    });
    
    $tablesNeedingPolicyId = array_filter($tablesWithPolicyNumber, function($table) {
        return !$table['has_policy_id'];
    });
    
    echo "=== Summary ===\n";
    echo "Total tables: " . count($schema['tables']) . "\n";
    echo "Tables with policyNumber: " . count($tablesWithPolicyNumber) . "\n";
    echo "Tables needing policy_id: " . count($tablesNeedingPolicyId) . "\n\n";
    
    if (count($tablesNeedingPolicyId) > 0) {
        echo "=== Tables That Need policy_id Column ===\n";
        foreach ($tablesNeedingPolicyId as $table) {
            echo "- {$table['name']} (has {$table['policyNumber_column']})\n";
        }
    }
    
    // Generate migration suggestions
    echo "\n=== Migration Suggestions ===\n";
    $migrationFile = database_path('migrations/2025_01_27_000003_add_policy_id_based_on_analysis.php');
    
    $migrationContent = "<?php\n\nuse Illuminate\\Database\\Migrations\\Migration;\nuse Illuminate\\Database\\Schema\\Blueprint;\nuse Illuminate\\Support\\Facades\\Schema;\nuse Illuminate\\Support\\Facades\\DB;\n\nclass AddPolicyIdBasedOnAnalysis extends Migration\n{\n    public function up()\n    {\n";
    
    foreach ($tablesNeedingPolicyId as $table) {
        $tableName = $table['name'];
        $policyNumberCol = $table['policyNumber_column'];
        
        $migrationContent .= "\n        // Add policy_id to {$tableName}\n";
        $migrationContent .= "        if (Schema::hasTable('{$tableName}') && !Schema::hasColumn('{$tableName}', 'policy_id')) {\n";
        $migrationContent .= "            Schema::table('{$tableName}', function (Blueprint \$table) {\n";
        $migrationContent .= "                \$table->unsignedBigInteger('policy_id')->nullable()->after('{$policyNumberCol}');\n";
        $migrationContent .= "                \$table->index('policy_id', 'idx_{$tableName}_policy_id');\n";
        $migrationContent .= "            });\n\n";
        $migrationContent .= "            // Migrate data\n";
        $migrationContent .= "            DB::statement(\"UPDATE {$tableName} t INNER JOIN policies p ON t.{$policyNumberCol} = p.policyNumber SET t.policy_id = p.id WHERE t.policy_id IS NULL\");\n";
        $migrationContent .= "        }\n";
    }
    
    $migrationContent .= "    }\n\n    public function down()\n    {\n        // Rollback if needed\n    }\n}\n";
    
    File::put($migrationFile, $migrationContent);
    echo "Migration file generated: {$migrationFile}\n";
    
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
