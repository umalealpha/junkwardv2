<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\EmailBroadcasting;
use AlphaDirect\Http\Controllers\CronController;
use AlphaDirect\Models\CronStatus;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Console\Helper\ProgressBar;

class PolicyPaymentComprehensiveReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'policy:comprehensive-payment-report';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a comprehensive policy payment CSV report and upload it to S3';

    /**
     * Handle the console command execution.
     */
    public function handle(): int
    {
        ini_set('memory_limit', '1024M');

        // $cron = new CronStatus();
        // $cron->name = $this->signature;
        // $cron->start = Carbon::now();
        // $cron->save();

        $timestamp = Carbon::now()->format('Y-m-d_H-i-s');
        $tempDir = storage_path('app/temp');

        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        try {
            // Optimize: Count directly from policies table (much faster)
            $totalRows = DB::table('policies')->count();
            
            if ($totalRows === 0) {
                $this->warn('No policy records found.');
                return Command::SUCCESS;
            }

            $numberOfParts = 5;
            $rowsPerPart = (int) ceil($totalRows / $numberOfParts);
            $attachments = [];
            $partFiles = [];

            $this->info("Total policies to process: {$totalRows}");
            $this->info("Splitting into {$numberOfParts} parts (~{$rowsPerPart} rows per part)");
            $this->output->newLine();

            // Get min and max policy IDs directly (much faster)
            $minMaxIds = DB::table('policies')
                ->selectRaw('MIN(id) as min_id, MAX(id) as max_id')
                ->first();

            if (!$minMaxIds || !$minMaxIds->min_id || !$minMaxIds->max_id) {
                $this->error('Could not determine policy ID range.');
                return Command::FAILURE;
            }

            $minId = $minMaxIds->min_id;
            $maxId = $minMaxIds->max_id;
            $idRange = $maxId - $minId;
            $idStep = (int) ceil($idRange / $numberOfParts);

            // Process each part
            for ($part = 1; $part <= $numberOfParts; $part++) {
                $this->info("Processing Part {$part}/{$numberOfParts}...");

                $partMinId = $minId + (($part - 1) * $idStep);
                $partMaxId = ($part === $numberOfParts) ? $maxId : ($minId + ($part * $idStep) - 1);

                $partFilename = "policy-payment-report_part{$part}_of_{$numberOfParts}_{$timestamp}.csv";
                $partTempPath = "{$tempDir}/{$partFilename}";

                $handle = fopen($partTempPath, 'w');
                fputcsv($handle, $this->headings());

                // Optimize: Count directly from policies table (much faster)
                $partTotalRows = DB::table('policies')
                    ->whereBetween('id', [$partMinId, $partMaxId])
                    ->count();

                if ($partTotalRows > 0) {
                    $progressBar = $this->output->createProgressBar($partTotalRows);
                    $progressBar->setFormat("Part {$part}: [%bar%] %percent:3s%% (%current%/%max%)");
                    $progressBar->start();

                    // Build query for this part
                    $partQuery = $this->buildReportQuery()->whereBetween('p.id', [$partMinId, $partMaxId]);
                    $this->streamRows($handle, $partQuery, $progressBar);

                    $progressBar->finish();
                    $this->output->newLine();
                }

                fclose($handle);

                // Upload individual part to S3
                $s3Path = "reports/policy-payment/{$partFilename}";
                $stream = fopen($partTempPath, 'r');
                Storage::disk('s3')->put($s3Path, $stream, 'public');
                fclose($stream);

                $attachments[] = $s3Path;
                $partFiles[] = $partTempPath; // Keep track for combining
                $s3Url = config('app.S3_BASE_URL') . '/' . $s3Path;
                $this->info("Part {$part} uploaded: {$s3Url}");
                $this->output->newLine();

                // Memory cleanup between parts
                if (function_exists('gc_collect_cycles')) {
                    gc_collect_cycles();
                }
            }

            // Combine all parts into one final report
            $this->info("Combining all parts into final report...");
            $finalFilename = "policy-payment-report_{$timestamp}.csv";
            $finalTempPath = "{$tempDir}/{$finalFilename}";
            $finalHandle = fopen($finalTempPath, 'w');

            // Write headers once
            fputcsv($finalHandle, $this->headings());

            // Combine all part files
            $combinedRows = 0;
            foreach ($partFiles as $partFile) {
                if (file_exists($partFile)) {
                    $partHandle = fopen($partFile, 'r');
                    // Skip header row (first line)
                    $headerSkipped = false;
                    while (($line = fgetcsv($partHandle)) !== false) {
                        if (!$headerSkipped) {
                            $headerSkipped = true;
                            continue; // Skip header
                        }
                        fputcsv($finalHandle, $line);
                        $combinedRows++;
                    }
                    fclose($partHandle);
                }
            }

            fclose($finalHandle);

            // Upload combined file to S3
            $finalS3Path = "reports/policy-payment/{$finalFilename}";
            $finalStream = fopen($finalTempPath, 'r');
            Storage::disk('s3')->put($finalS3Path, $finalStream, 'public');
            fclose($finalStream);

            // Clean up temp files
            foreach ($partFiles as $partFile) {
                if (file_exists($partFile)) {
                    Storage::disk('local')->delete("temp/" . basename($partFile));
                }
            }
            Storage::disk('local')->delete("temp/{$finalFilename}");

            // Add combined file to attachments (first in list)
            array_unshift($attachments, $finalS3Path);
            $finalS3Url = config('app.S3_BASE_URL') . '/' . $finalS3Path;
            $this->info("Combined report uploaded: {$finalS3Url} ({$combinedRows} rows)");
            $this->output->newLine();

            // $cron->end = Carbon::now();
            // $cron->save();

            $hook = 'policy_payment_report';

            if (EmailBroadcasting::where('hook_slug', $hook)->exists()) {
                $cronMailer = new CronController();
                // $cronMailer->AllCronMail($attachments, $hook, $cron);
            } else {
                Log::warning("Email template {$hook} not found. Skipping cron mail dispatch.");
            }

            $this->info("All {$numberOfParts} parts generated and combined successfully!");
            $this->info("Files uploaded to S3:");
            $this->line("  - COMBINED REPORT: " . config('app.S3_BASE_URL') . '/' . $finalS3Path);
            $this->line("  - Individual parts:");
            foreach (array_slice($attachments, 1) as $attachment) {
                $this->line("    - " . config('app.S3_BASE_URL') . '/' . $attachment);
            }

            Log::info('Policy payment report generated', [
                'parts' => $numberOfParts,
                'total_rows' => $totalRows,
                'combined_rows' => $combinedRows,
                'combined_file' => $finalS3Path,
                'attachments' => $attachments
            ]);

            return Command::SUCCESS;
        } catch (\Throwable $th) {
            // $cron->end = Carbon::now();
            // $cron->save();

            Log::error('Policy payment report generation failed', [
                'message' => $th->getMessage(),
                'trace' => $th->getTraceAsString(),
            ]);

            $this->error('Failed to generate policy payment report: ' . $th->getMessage());

            return Command::FAILURE;
        }
    }

    /**
     * Stream query rows into the CSV handle - optimized for large datasets.
     */
    protected function streamRows($handle, $query, ProgressBar $progressBar): void
    {
        // Use cursor for better memory efficiency on large datasets
        // Cursor processes one row at a time without loading all into memory
        $query->orderBy('p.id')
            ->chunk(2000, function ($rows) use ($handle, $progressBar) {
                foreach ($rows as $row) {
                    fputcsv($handle, $this->mapRow($row));
                    $progressBar->advance();
                }
                // Free memory after each chunk
                unset($rows);
            });
    }

    /**
     * Build the base query for the report - optimized for large databases.
     * Uses efficient derived tables with proper indexing for 250k+ records.
     * 
     * IMPORTANT: For best performance, ensure these indexes exist:
     * - payment_transactions: INDEX(policyNumber, id), INDEX(policyNumber, status, amount)
     * - customer_banking: INDEX(policy_id, id)
     * - policies: PRIMARY KEY(id), INDEX(customer_id), INDEX(agent_id), INDEX(product_id)
     */
    protected function buildReportQuery()
    {
        // Optimized: Use derived tables with efficient MAX subqueries
        // This approach is much faster on large tables as MySQL can use indexes better
        // The derived tables are materialized once, then joined efficiently
        
        // Create efficient derived tables for latest records
        // Using a pattern that MySQL can optimize with indexes
        $latestPayments = DB::table(DB::raw('(
            SELECT pt1.id, pt1.policyNumber, pt1.amount, pt1.status, pt1.paymentMethod, pt1.referenceNumber
            FROM payment_transactions pt1
            INNER JOIN (
                SELECT policyNumber, MAX(id) as max_id
                FROM payment_transactions
                WHERE deleted_at IS NULL
                GROUP BY policyNumber
            ) pt2 ON pt1.policyNumber = pt2.policyNumber AND pt1.id = pt2.max_id
            WHERE pt1.deleted_at IS NULL
        ) as latest_payments'));

        // Calculate total successful payments and arrears from payment_transactions
        // Exclude refunds (is_refund = 1) and deleted records
        $paymentSummary = DB::table(DB::raw('(
            SELECT 
                policyNumber,
                SUM(CASE 
                    WHEN (status = 1 OR UPPER(status) = \'SUCCESS\' OR status = \'Success\' OR status = \'success\')
                    AND (is_refund IS NULL OR is_refund = 0)
                    THEN CAST(amount AS DECIMAL(10,2))
                    ELSE 0
                END) as total_successful_payments
            FROM payment_transactions
            WHERE deleted_at IS NULL
            GROUP BY policyNumber
        ) as payment_summary'));

        $latestBanking = DB::table(DB::raw('(
            SELECT cb1.id, cb1.policy_id, cb1.billing, cb1.accountNumber, cb1.accountType, 
                   cb1.branchCode, cb1.bankName, cb1.created_at
            FROM customer_banking cb1
            INNER JOIN (
                SELECT policy_id, MAX(id) as max_id
                FROM customer_banking
                GROUP BY policy_id
            ) cb2 ON cb1.policy_id = cb2.policy_id AND cb1.id = cb2.max_id
        ) as latest_banking'));

        return DB::table('policies as p')
            ->select([
                'p.id',
                'p.policyNumber',
                'p.premium',
                'p.annual_premium',
                'p.billingStartDate',
                'p.status as policy_status',
                'p.policyActivatedDate',
                'p.created_at as policy_created_at',
                'c.firstName as customer_first_name',
                'c.middleName as customer_middle_name',
                'c.lastName as customer_last_name',
                'c.cellphone',
                'u.firstName as agent_first_name',
                'u.lastName as agent_last_name',
                'prod.name as product_name',
                'lb.billing as billing_method',
                'lb.accountNumber as account_number',
                'lb.accountType as account_type',
                'lb.branchCode as branch_code',
                'lb.bankName as bank_number',
                'lb.created_at as banking_created_at',
                'bank.bank_name',
                'branch.name as branch_name',
                // Calculate arrears: Premium due - Total successful payments
                DB::raw('COALESCE(p.annual_premium, p.premium, 0) - COALESCE(ps.total_successful_payments, 0) as arrears_value'),
                'lp.amount as last_payment_amount',
                'lp.status as last_payment_status',
                'lp.paymentMethod as last_payment_method',
                'lp.referenceNumber as payment_reference',
            ])
            ->leftJoin('customer as c', 'c.id', '=', 'p.customer_id')
            ->leftJoin('users as u', 'u.id', '=', 'p.agent_id')
            ->leftJoin('products as prod', 'prod.id', '=', 'p.product_id')
            ->leftJoinSub($latestBanking, 'lb', function ($join) {
                $join->on('lb.policy_id', '=', 'p.id');
            })
            ->leftJoin('banks as bank', 'bank.bank_number', '=', 'lb.bankName')
            ->leftJoin('bankBranches as branch', 'branch.branch_id', '=', 'lb.branchCode')
            ->leftJoinSub($paymentSummary, 'ps', function ($join) {
                $join->on('ps.policyNumber', '=', 'p.policyNumber');
            })
            ->leftJoinSub($latestPayments, 'lp', function ($join) {
                $join->on('lp.policyNumber', '=', 'p.policyNumber');
            });
    }

    /**
     * Report headings.
     */
    protected function headings(): array
    {
        return [
            'Policy Number',
            'Customer Name',
            'Contact Number',
            'Agent Name',
            'Product',
            'Annual Premium',
            'Premium Value',
            'Arrears Value',
            'DPO/VCS Contract Status',
            'Payment Method',
            'Payment Status',
            'Bank Name',
            'Account Number',
            'Account Type',
            'Branch Code',
            'Branch Name',
            'Date of Real Pay Addition',
            'Policy Status',
            'Billing Date',
            'Policy Inception Date',
        ];
    }

    /**
     * Map a database row to a CSV row.
     */
    protected function mapRow(object $row): array
    {
        $customerNameParts = array_filter([
            $row->customer_first_name,
            $row->customer_middle_name,
            $row->customer_last_name,
        ], fn ($value) => !is_null($value) && $value !== '');
        $customerName = $customerNameParts ? implode(' ', $customerNameParts) : 'N/A';

        $agentNameParts = array_filter([
            $row->agent_first_name,
            $row->agent_last_name,
        ], fn ($value) => !is_null($value) && $value !== '');
        $agentName = $agentNameParts ? implode(' ', $agentNameParts) : 'N/A';

        $billingMethod = $row->billing_method ? strtoupper($row->billing_method) : null;
        $paymentMethod = $row->last_payment_method
            ? strtoupper($row->last_payment_method)
            : ($billingMethod ?? 'N/A');

        $paymentStatus = $row->last_payment_status ?? 'N/A';
        
        // Format payment status: Check if status is 1 or any variant of 'success'
        if ($paymentStatus === 'N/A') {
            $paymentStatusFormatted = 'N/A';
        } elseif ($paymentStatus == 1 || 
                  strtoupper($paymentStatus) === 'SUCCESS' || 
                  $paymentStatus === 'Success' || 
                  $paymentStatus === 'success') {
            $paymentStatusFormatted = 'Success';
        } else {
            $paymentStatusFormatted = $paymentStatus;
        }

        $contractStatus = match ($billingMethod) {
            'DPO', 'VCS' => "Active ({$billingMethod})",
            default => $billingMethod ?? 'N/A',
        };

        $realPayAdded = ($billingMethod && Str::contains(strtolower($billingMethod), 'realpay'))
            ? $this->formatDateTime($row->banking_created_at)
            : 'N/A';

        return [
            $row->policyNumber,
            $customerName,
            $row->cellphone ?? 'N/A',
            $agentName,
            $row->product_name ?? 'N/A',
            $this->formatAmount($row->annual_premium ?? $row->premium),
            $this->formatAmount($row->last_payment_amount ?? $row->premium),
            $this->formatAmount($row->arrears_value),
            $contractStatus,
            $paymentMethod,
            $paymentStatusFormatted,
            $row->bank_name ?? 'N/A',
            $row->account_number ?? 'N/A',
            $row->account_type ?? 'N/A',
            $row->branch_code ?? 'N/A',
            $row->branch_name ?? 'N/A',
            $realPayAdded,
            $this->mapPolicyStatus($row->policy_status),
            $this->formatDate($row->billingStartDate),
            $this->formatDate($row->policyActivatedDate ?? $row->policy_created_at),
        ];
    }

    /**
     * Format monetary values.
     */
    protected function formatAmount($value): string
    {
        if (is_null($value) || $value === '') {
            return '0.00';
        }

        return number_format((float) $value, 2, '.', '');
    }

    /**
     * Format dates.
     */
    protected function formatDate($value): string
    {
        if (!$value) {
            return 'N/A';
        }

        return Carbon::parse($value)->format('Y-m-d');
    }

    /**
     * Format date time values.
     */
    protected function formatDateTime($value): string
    {
        if (!$value) {
            return 'N/A';
        }

        return Carbon::parse($value)->format('Y-m-d H:i:s');
    }

    /**
     * Convert the policy status code to a label.
     */
    protected function mapPolicyStatus($status): string
    {
        return match ((int) $status) {
            1 => 'Activated',
            2 => 'Cancelled',
            3 => 'Expired',
            default => 'Deactivated',
        };
    }
}

