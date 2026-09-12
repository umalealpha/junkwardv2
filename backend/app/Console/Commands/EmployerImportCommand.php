<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Models\ADGroupUploadedExcelFile;
use AlphaDirect\Models\EmployerGroup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class EmployerImportCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:employer';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import Employer Groups from uploaded Excel files (S3)';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $imports = ADGroupUploadedExcelFile::where('type', 'employer')->where('status', 'pending')->where('processed', 0)->get();

        if ($imports->isEmpty()) {
            $this->info("No pending employer import files found.");
            return Command::SUCCESS;
        }

        $totalFiles = $imports->count();
        $this->info("Found {$totalFiles} employer file(s) to process.");

        foreach ($imports as $import) {
            $this->info("Processing Employer file: " . $import->file_path);

            // Create a local temp file
            $tmpFile = tempnam(sys_get_temp_dir(), 'import_');

            try {
                // Download from S3
                Storage::disk('s3')->getDriver()->getAdapter()->getClient()->getObject([
                    'Bucket' => config('filesystems.disks.s3.bucket'),
                    'Key'    => $import->file_path,
                    'SaveAs' => $tmpFile,
                ]);

                // Now Excel can read it
                $rows = Excel::toArray([], $tmpFile)[0];
                
                $processedCount = 0;
                $skippedCount = 0;
                $errorCount = 0;

                // Skip header row
                foreach (array_slice($rows, 1) as $index => $row) {
                    try {
                        // Validate required fields
                        if (empty($row[0]) || empty($row[1])) {
                            $this->warn("Skipping row " . ($index + 2) . ": Missing required fields (SchemeNumber or DebtorName)");
                            $skippedCount++;
                            continue;
                        }

                        // Ensure we have enough columns
                        if (count($row) < 13) {
                            $this->warn("Skipping row " . ($index + 2) . ": Insufficient columns");
                            $skippedCount++;
                            continue;
                        }

                        EmployerGroup::updateOrCreate(
                            ['employer_group_id' => $row[0]], // SchemeNumber
                            [
                                'name'            => $row[1], // DebtorName
                                'industry'        => $row[2] ?? null, // IndustrySector
                                'address      '   => $row[3] ?? null, // AddressLine1
                                'town'            => $row[4] ?? null, // Town
                                'postal_code'     => $row[5] ?? null, // PostalCode
                                // 'address'         => trim(($row[3] ?? '') . ', ' . ($row[4] ?? '') . ', ' . ($row[5] ?? '')), // Combined address
                                'contact_name'    => $row[6] ?? null, // ContactName
                                'contact_email'   => $row[7] ?? null, // ContactEmail
                                'contact_phone'   => $row[8] ?? null, // ContactPhone
                                'broker'          => $row[9] ?? null, // PrimaryAgentName
                                'payment_method'  => $row[10] ?? null, // PaymentMethod
                                'status'          => $row[11] ?? null, // PolicyStatus
                                'no_of_employees' => is_numeric($row[12]) ? (int)$row[12] : null, // NumberOfInsured
                            ]
                        );
                        
                        $processedCount++;
                        $this->info("Processed row " . ($index + 2) . ": " . $row[1]);
                    } catch (\Exception $e) {
                        $this->error("Error processing row " . ($index + 2) . ": " . $e->getMessage());
                        $errorCount++;
                        continue;
                    }
                }

                // Summary for this file
                $this->info("File processing complete:");
                $this->info("  - Processed: {$processedCount} rows");
                $this->info("  - Skipped: {$skippedCount} rows");
                $this->info("  - Errors: {$errorCount} rows");

                unlink($tmpFile); // cleanup
                $import->update(['processed' => 1, 'status' => 'processed']);
                $this->info("Employer file imported successfully.");
                
            } catch (\Exception $e) {
                $this->error("Error processing file {$import->file_path}: " . $e->getMessage());
                if (file_exists($tmpFile)) {
                    unlink($tmpFile);
                }
                continue;
            }
        }

        $this->info("All employer import files processed.");
        return Command::SUCCESS;
    }
}
