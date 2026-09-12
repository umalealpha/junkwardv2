<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FixDatabaseDataQuality extends Command
{
    protected $signature = 'db:fix-data-quality
                            {--dry-run : Show what would be fixed without making changes}
                            {--fix=all : Which fix to run: all|realpay_policy_id|billing_date|payment_amount|scheduled_premium}';

    protected $description = 'Fix known data quality issues: realpay_client_contracts.policy_id (VARCHAR→INT), policies.billingStartDate (mixed formats→DATE), payment_transactions.amount (VARCHAR→DECIMAL), scheduled_transactions.premium (VARCHAR→DECIMAL)';

    private bool $dryRun = false;

    public function handle(): int
    {
        $this->dryRun = $this->option('dry-run');
        $fix = $this->option('fix');

        $this->info("=================================================");
        $this->info("DB DATA QUALITY FIX" . ($this->dryRun ? ' [DRY RUN — no changes]' : ''));
        $this->info("=================================================\n");

        if ($this->dryRun) {
            $this->warn("DRY RUN mode: analysis only, nothing will be changed.\n");
        }

        try {
            if ($fix === 'all' || $fix === 'realpay_policy_id') {
                $this->fixRealpayPolicyId();
            }
            if ($fix === 'all' || $fix === 'billing_date') {
                $this->fixBillingStartDate();
            }
            if ($fix === 'all' || $fix === 'payment_amount') {
                $this->fixPaymentTransactionsAmount();
            }
            if ($fix === 'all' || $fix === 'scheduled_premium') {
                $this->fixScheduledTransactionsPremium();
            }

            $this->info("\n=================================================");
            $this->info("DONE" . ($this->dryRun ? ' (dry run — no changes made)' : ''));
            $this->info("=================================================");
            return 0;

        } catch (\Exception $e) {
            $this->error("FAILED: " . $e->getMessage());
            Log::error('FixDatabaseDataQuality failed: ' . $e->getMessage());
            return 1;
        }
    }

    // ─────────────────────────────────────────────────────────────
    // Fix 1: realpay_client_contracts.policy_id
    // Problem: VARCHAR column storing integer IDs AND policy number
    //          strings like 'MIS2021020969' mixed together.
    // Fix:     Match policy number strings to correct integer IDs,
    //          null out unmatched rows, convert column to INT UNSIGNED.
    // ─────────────────────────────────────────────────────────────
    private function fixRealpayPolicyId(): void
    {
        $this->info("── Fix 1: realpay_client_contracts.policy_id ──────────");

        // Check current column type
        $col = DB::selectOne("
            SELECT COLUMN_TYPE FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'realpay_client_contracts'
              AND COLUMN_NAME = 'policy_id'
        ");
        $this->line("  Current type: " . ($col->COLUMN_TYPE ?? 'unknown'));

        // Count bad rows
        $bad = DB::selectOne("
            SELECT COUNT(*) as cnt
            FROM realpay_client_contracts
            WHERE policy_id NOT REGEXP '^[0-9]+$'
              AND policy_id IS NOT NULL AND policy_id != ''
        ");
        $badCount = (int) ($bad->cnt ?? 0);
        $this->line("  Bad rows (non-numeric policy_id): {$badCount}");

        if ($badCount === 0) {
            $this->info("  -> Nothing to fix.\n");
        } else {
            // Show samples
            $samples = DB::select("
                SELECT id, policy_id, contract_number, status
                FROM realpay_client_contracts
                WHERE policy_id NOT REGEXP '^[0-9]+$'
                  AND policy_id IS NOT NULL AND policy_id != ''
                LIMIT 10
            ");
            $this->table(['id', 'policy_id', 'contract_number', 'status'],
                array_map(fn($r) => [(string)$r->id, $r->policy_id, $r->contract_number, $r->status], $samples)
            );

            if (!$this->dryRun) {
                // Step 1: match policy number strings to correct integer id
                $matched = DB::affectingStatement("
                    UPDATE realpay_client_contracts rcc
                    JOIN policies p ON p.policyNumber = rcc.policy_id
                    SET rcc.policy_id = p.id
                    WHERE rcc.policy_id NOT REGEXP '^[0-9]+$'
                      AND rcc.policy_id IS NOT NULL AND rcc.policy_id != ''
                ");
                $this->line("  Matched to correct integer id: {$matched} rows");

                // Step 2: null out anything still unmatched
                $nulled = DB::affectingStatement("
                    UPDATE realpay_client_contracts
                    SET policy_id = NULL
                    WHERE policy_id NOT REGEXP '^[0-9]+$'
                      AND policy_id IS NOT NULL AND policy_id != ''
                ");
                $this->line("  Nulled out unmatched rows: {$nulled} rows");

                // Step 3: convert column type
                $this->line("  Converting column type to INT UNSIGNED...");
                DB::statement("ALTER TABLE realpay_client_contracts MODIFY COLUMN policy_id INT UNSIGNED NULL");
                $this->info("  -> Column converted to INT UNSIGNED.");
            }
        }

        // Verify final type
        if (!$this->dryRun) {
            $col = DB::selectOne("
                SELECT COLUMN_TYPE FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'realpay_client_contracts'
                  AND COLUMN_NAME = 'policy_id'
            ");
            $this->line("  Final type: " . ($col->COLUMN_TYPE ?? 'unknown') . "\n");
        }
    }

    // ─────────────────────────────────────────────────────────────
    // Fix 2: policies.billingStartDate
    // Problem: VARCHAR with mixed formats: DD/MM/YYYY, DD-MM-YYYY,
    //          YYYY-MM-DD, DD Mon YYYY, YYYY-MM-DD HH:MM:SS, 0000-00-00, empty.
    // Fix:     Normalise all to DATE, convert column type.
    // ─────────────────────────────────────────────────────────────
    private function fixBillingStartDate(): void
    {
        $this->info("── Fix 2: policies.billingStartDate ───────────────────");

        $col = DB::selectOne("
            SELECT COLUMN_TYPE FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'policies'
              AND COLUMN_NAME = 'billingStartDate'
        ");
        $this->line("  Current type: " . ($col->COLUMN_TYPE ?? 'unknown'));

        // Show format breakdown
        $formats = DB::select("
            SELECT
                CASE
                    WHEN billingStartDate REGEXP '^[0-9]{2}/[0-9]{2}/[0-9]{4}$' THEN 'DD/MM/YYYY'
                    WHEN billingStartDate REGEXP '^[0-9]{2}-[0-9]{2}-[0-9]{4}$' THEN 'DD-MM-YYYY'
                    WHEN billingStartDate REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$'
                         AND billingStartDate != '0000-00-00'                    THEN 'YYYY-MM-DD (valid)'
                    WHEN billingStartDate REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}$' THEN 'YYYY-MM-DD HH:MM:SS (datetime)'
                    WHEN billingStartDate REGEXP '^[0-9]{2} [A-Za-z]{3} [0-9]{4}$' THEN 'DD Mon YYYY'
                    WHEN billingStartDate IN ('0000-00-00','00/00/0000','00-00-0000') THEN 'zero-date'
                    WHEN billingStartDate IS NULL OR billingStartDate = ''        THEN 'empty/null'
                    ELSE CONCAT('UNKNOWN: ', LEFT(billingStartDate, 20))
                END AS format,
                COUNT(*) AS cnt
            FROM policies
            GROUP BY 1
            ORDER BY 2 DESC
        ");
        $this->table(['Format', 'Count'],
            array_map(fn($r) => [$r->format, number_format($r->cnt)], $formats)
        );

        if ($this->dryRun) {
            $this->info("  [DRY RUN] Would convert all formats to DATE.\n");
            return;
        }

        // Check if already a DATE column — skip if so
        if (stripos($col->COLUMN_TYPE ?? '', 'date') !== false && stripos($col->COLUMN_TYPE ?? '', 'varchar') === false) {
            $this->info("  -> Already a DATE column, skipping.\n");
            return;
        }

        // Add clean column
        $this->line("  Adding billingStartDate_clean DATE column...");
        DB::statement("ALTER TABLE policies ADD COLUMN billingStartDate_clean DATE NULL AFTER billingStartDate");

        // Populate
        $this->line("  Converting all formats...");
        DB::statement("
            UPDATE policies SET billingStartDate_clean =
                CASE
                    WHEN billingStartDate REGEXP '^[0-9]{2}/[0-9]{2}/[0-9]{4}$'
                        THEN STR_TO_DATE(billingStartDate, '%d/%m/%Y')
                    WHEN billingStartDate REGEXP '^[0-9]{2}-[0-9]{2}-[0-9]{4}$'
                        THEN STR_TO_DATE(billingStartDate, '%d-%m-%Y')
                    WHEN billingStartDate REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$'
                         AND billingStartDate != '0000-00-00'
                        THEN CAST(billingStartDate AS DATE)
                    WHEN billingStartDate REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}$'
                        THEN DATE(billingStartDate)
                    WHEN billingStartDate REGEXP '^[0-9]{2} [A-Za-z]{3} [0-9]{4}$'
                        THEN STR_TO_DATE(billingStartDate, '%d %b %Y')
                    ELSE NULL
                END
        ");

        // Report conversion results
        $result = DB::selectOne("
            SELECT
                COUNT(*) as total,
                SUM(billingStartDate_clean IS NOT NULL) as converted,
                SUM(billingStartDate_clean IS NULL
                    AND billingStartDate IS NOT NULL
                    AND billingStartDate NOT IN ('','0000-00-00','00/00/0000','00-00-0000')
                    AND billingStartDate REGEXP '^[0-9]') as failed
            FROM policies
        ");
        $this->line("  Converted: {$result->converted} / {$result->total} (failed: {$result->failed})");

        if ((int)$result->failed > 0) {
            $unknown = DB::select("
                SELECT id, policyNumber, billingStartDate
                FROM policies
                WHERE billingStartDate_clean IS NULL
                  AND billingStartDate IS NOT NULL
                  AND billingStartDate NOT IN ('','0000-00-00','00/00/0000','00-00-0000')
                  AND billingStartDate REGEXP '^[0-9]'
                LIMIT 10
            ");
            $this->warn("  Unrecognised formats (sample):");
            $this->table(['id','policyNumber','billingStartDate'],
                array_map(fn($r) => [$r->id, $r->policyNumber, $r->billingStartDate], $unknown)
            );
        }

        // Swap columns
        $this->line("  Dropping old column and renaming...");
        DB::statement("ALTER TABLE policies DROP COLUMN billingStartDate");
        DB::statement("ALTER TABLE policies RENAME COLUMN billingStartDate_clean TO billingStartDate");

        $this->info("  -> billingStartDate is now a DATE column.\n");
    }

    // ─────────────────────────────────────────────────────────────
    // Fix 3: payment_transactions.amount
    // Problem: VARCHAR storing decimal amounts — requires CAST on
    //          every query, prevents index use, causes errors.
    // Fix:     Convert to DECIMAL(10,2).
    // ─────────────────────────────────────────────────────────────
    private function fixPaymentTransactionsAmount(): void
    {
        $this->info("── Fix 3: payment_transactions.amount ─────────────────");

        $col = DB::selectOne("
            SELECT COLUMN_TYPE FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'payment_transactions'
              AND COLUMN_NAME = 'amount'
        ");
        $this->line("  Current type: " . ($col->COLUMN_TYPE ?? 'unknown'));

        if (stripos($col->COLUMN_TYPE ?? '', 'decimal') !== false || stripos($col->COLUMN_TYPE ?? '', 'float') !== false) {
            $this->info("  -> Already numeric, skipping.\n");
            return;
        }

        // Check bad values
        $bad = DB::selectOne("
            SELECT COUNT(*) as cnt FROM payment_transactions
            WHERE amount NOT REGEXP '^[0-9]+(\\.[0-9]+)?$'
              AND amount IS NOT NULL AND amount != ''
        ");
        $this->line("  Non-numeric values: " . ($bad->cnt ?? 0));

        $samples = DB::select("
            SELECT amount, COUNT(*) as cnt FROM payment_transactions
            WHERE amount NOT REGEXP '^[0-9]+(\\.[0-9]+)?$'
              AND amount IS NOT NULL AND amount != ''
            GROUP BY amount ORDER BY cnt DESC LIMIT 10
        ");
        if ($samples) {
            $this->table(['amount','count'], array_map(fn($r) => [$r->amount, $r->cnt], $samples));
        }

        if ($this->dryRun) {
            $this->info("  [DRY RUN] Would convert to DECIMAL(10,2).\n");
            return;
        }

        $this->line("  Adding amount_clean DECIMAL(10,2) column...");
        DB::statement("ALTER TABLE payment_transactions ADD COLUMN amount_clean DECIMAL(10,2) NULL AFTER amount");

        $this->line("  Populating...");
        DB::statement("
            UPDATE payment_transactions
            SET amount_clean = CASE
                WHEN amount REGEXP '^[0-9]+(\\.[0-9]+)?$' THEN CAST(amount AS DECIMAL(10,2))
                ELSE NULL
            END
        ");

        $result = DB::selectOne("
            SELECT SUM(amount_clean IS NOT NULL) as converted,
                   SUM(amount_clean IS NULL) as nulled,
                   COUNT(*) as total
            FROM payment_transactions
        ");
        $this->line("  Converted: {$result->converted}, Nulled: {$result->nulled}");

        $this->line("  Swapping columns...");
        DB::statement("ALTER TABLE payment_transactions DROP COLUMN amount");
        DB::statement("ALTER TABLE payment_transactions RENAME COLUMN amount_clean TO amount");

        $this->info("  -> amount is now DECIMAL(10,2).\n");
    }

    // ─────────────────────────────────────────────────────────────
    // Fix 4: scheduled_transactions.premium
    // Same issue as payment_transactions.amount.
    // ─────────────────────────────────────────────────────────────
    private function fixScheduledTransactionsPremium(): void
    {
        $this->info("── Fix 4: scheduled_transactions.premium ──────────────");

        $col = DB::selectOne("
            SELECT COLUMN_TYPE FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'scheduled_transactions'
              AND COLUMN_NAME = 'premium'
        ");
        $this->line("  Current type: " . ($col->COLUMN_TYPE ?? 'unknown'));

        if (stripos($col->COLUMN_TYPE ?? '', 'decimal') !== false || stripos($col->COLUMN_TYPE ?? '', 'float') !== false) {
            $this->info("  -> Already numeric, skipping.\n");
            return;
        }

        $bad = DB::selectOne("
            SELECT COUNT(*) as cnt FROM scheduled_transactions
            WHERE premium NOT REGEXP '^[0-9]+(\\.[0-9]+)?$'
              AND premium IS NOT NULL AND premium != ''
        ");
        $this->line("  Non-numeric values: " . ($bad->cnt ?? 0));

        if ($this->dryRun) {
            $this->info("  [DRY RUN] Would convert to DECIMAL(10,2).\n");
            return;
        }

        $this->line("  Adding premium_clean DECIMAL(10,2) column...");
        DB::statement("ALTER TABLE scheduled_transactions ADD COLUMN premium_clean DECIMAL(10,2) NULL AFTER premium");

        $this->line("  Populating...");
        DB::statement("
            UPDATE scheduled_transactions
            SET premium_clean = CASE
                WHEN premium REGEXP '^[0-9]+(\\.[0-9]+)?$' THEN CAST(premium AS DECIMAL(10,2))
                ELSE NULL
            END
        ");

        $result = DB::selectOne("
            SELECT SUM(premium_clean IS NOT NULL) as converted,
                   SUM(premium_clean IS NULL) as nulled,
                   COUNT(*) as total
            FROM scheduled_transactions
        ");
        $this->line("  Converted: {$result->converted}, Nulled: {$result->nulled}");

        $this->line("  Swapping columns...");
        DB::statement("ALTER TABLE scheduled_transactions DROP COLUMN premium");
        DB::statement("ALTER TABLE scheduled_transactions RENAME COLUMN premium_clean TO premium");

        $this->info("  -> premium is now DECIMAL(10,2).\n");
    }
}
