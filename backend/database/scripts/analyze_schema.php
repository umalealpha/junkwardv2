<?php

/**
 * Database Schema Analyzer
 * 
 * This script analyzes the database schema to identify:
 * 1. Tables that have policyNumber column
 * 2. Tables that should have policy_id column
 * 3. Foreign key relationships
 * 4. Indexes on policyNumber vs policy_id
 */

require __DIR__ . '/../../vendor/autoload.php';

$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "=== Database Schema Analysis ===\n\n";

// Get all tables
$tables = DB::select("SHOW TABLES");
$dbName = DB::getDatabaseName();
$tableKey = "Tables_in_{$dbName}";

$tablesWithPolicyNumber = [];
$tablesWithPolicyId = [];
$joinAnalysis = [];

foreach ($tables as $table) {
    $tableName = $table->$tableKey;
    
    // Check for policyNumber column
    $hasPolicyNumber = DB::select("
        SELECT COUNT(*) as count 
        FROM information_schema.COLUMNS 
        WHERE TABLE_SCHEMA = ? 
        AND TABLE_NAME = ? 
        AND COLUMN_NAME = 'policyNumber'
    ", [$dbName, $tableName]);
    
    // Check for policy_id column
    $hasPolicyId = DB::select("
        SELECT COUNT(*) as count 
        FROM information_schema.COLUMNS 
        WHERE TABLE_SCHEMA = ? 
        AND TABLE_NAME = ? 
        AND COLUMN_NAME = 'policy_id'
    ", [$dbName, $tableName]);
    
    if ($hasPolicyNumber[0]->count > 0) {
        $tablesWithPolicyNumber[] = $tableName;
        
        // Get column details
        $columnInfo = DB::select("
            SELECT COLUMN_TYPE, IS_NULLABLE, COLUMN_KEY
            FROM information_schema.COLUMNS 
            WHERE TABLE_SCHEMA = ? 
            AND TABLE_NAME = ? 
            AND COLUMN_NAME = 'policyNumber'
        ", [$dbName, $tableName]);
        
        $joinAnalysis[$tableName] = [
            'has_policyNumber' => true,
            'has_policy_id' => $hasPolicyId[0]->count > 0,
            'column_type' => $columnInfo[0]->COLUMN_TYPE ?? 'unknown',
            'is_indexed' => ($columnInfo[0]->COLUMN_KEY ?? '') !== '',
            'nullable' => $columnInfo[0]->IS_NULLABLE ?? 'YES'
        ];
    }
    
    if ($hasPolicyId[0]->count > 0) {
        $tablesWithPolicyId[] = $tableName;
    }
}

echo "Tables with policyNumber column: " . count($tablesWithPolicyNumber) . "\n";
echo "Tables with policy_id column: " . count($tablesWithPolicyId) . "\n\n";

echo "=== Tables Using policyNumber (Should Migrate to policy_id) ===\n";
foreach ($joinAnalysis as $table => $info) {
    $status = $info['has_policy_id'] ? '✓ HAS policy_id' : '✗ MISSING policy_id';
    $indexed = $info['is_indexed'] ? 'INDEXED' : 'NOT INDEXED';
    echo sprintf(
        "%-40s | Type: %-20s | %s | %s\n",
        $table,
        $info['column_type'],
        $status,
        $indexed
    );
}

echo "\n=== Recommended Actions ===\n";
echo "1. Add policy_id column to tables that only have policyNumber\n";
echo "2. Create indexes on policy_id columns\n";
echo "3. Update foreign key relationships\n";
echo "4. Migrate data from policyNumber to policy_id\n";
echo "5. Update application code to use policy_id joins\n";

// Generate migration suggestions
echo "\n=== Migration Suggestions ===\n";
foreach ($joinAnalysis as $table => $info) {
    if (!$info['has_policy_id']) {
        echo "-- Add policy_id to {$table}\n";
        echo "ALTER TABLE {$table} ADD COLUMN policy_id INT UNSIGNED NULL AFTER policyNumber;\n";
        echo "CREATE INDEX idx_{$table}_policy_id ON {$table}(policy_id);\n";
        echo "-- Migrate data\n";
        echo "UPDATE {$table} t JOIN policies p ON t.policyNumber = p.policyNumber SET t.policy_id = p.id;\n";
        echo "-- Add foreign key\n";
        echo "ALTER TABLE {$table} ADD CONSTRAINT fk_{$table}_policy_id FOREIGN KEY (policy_id) REFERENCES policies(id) ON DELETE CASCADE;\n\n";
    }
}

// Check for indexes
echo "\n=== Index Analysis ===\n";
foreach ($tablesWithPolicyNumber as $table) {
    $indexes = DB::select("SHOW INDEXES FROM {$table} WHERE Column_name = 'policyNumber'");
    if (empty($indexes)) {
        echo "⚠ {$table}.policyNumber is NOT INDEXED - This will cause slow joins!\n";
    } else {
        echo "✓ {$table}.policyNumber is indexed\n";
    }
}

foreach ($tablesWithPolicyId as $table) {
    $indexes = DB::select("SHOW INDEXES FROM {$table} WHERE Column_name = 'policy_id'");
    if (empty($indexes)) {
        echo "⚠ {$table}.policy_id is NOT INDEXED - Add index for better performance!\n";
    } else {
        echo "✓ {$table}.policy_id is indexed\n";
    }
}

echo "\n=== Analysis Complete ===\n";
