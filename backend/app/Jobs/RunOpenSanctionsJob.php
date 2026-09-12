<?php

namespace AlphaDirect\Jobs;

use AlphaDirect\Customer;
use AlphaDirect\Policy;
use AlphaDirect\Models\AmlResult;
use AlphaDirect\Models\AuditLog;
use AlphaDirect\Models\KycCase;
use AlphaDirect\Services\OpenSanctionsClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RunOpenSanctionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @param int|null $kycCaseId Optional specific KYC case ID to process.
     *                            If null (default), processes all customers with activated policies.
     */
    public function __construct(public ?int $kycCaseId = null)
    {
        //
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(OpenSanctionsClient $os)
    {
        try {
            // If specific KYC case ID provided, process only that case
            if ($this->kycCaseId) {
                Log::info('RunOpenSanctionsJob: Processing specific KYC case', ['kyc_case_id' => $this->kycCaseId]);
                $this->processSingleCase($os, $this->kycCaseId);
                return;
            }

            // Default behavior: Process all customers with activated policies (for cron execution)
            Log::info('RunOpenSanctionsJob: Processing all customers with activated policies');
            $this->processActivatedPolicyCustomers($os);

        } catch (\Throwable $e) {
            Log::error('RunOpenSanctionsJob failed: ' . $e->getMessage(), [
                'kyc_case_id' => $this->kycCaseId,
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Process a single KYC case
     */
    private function processSingleCase(OpenSanctionsClient $os, int $kycCaseId)
    {
        $case = KycCase::findOrFail($kycCaseId);
        $customer = Customer::with('profile')->findOrFail($case->customer_id);

        // Verify customer has activated policies
        $hasActivatedPolicy = Policy::where('customer_id', $customer->id)
            ->where('status', 1) // Activated
            ->exists();

        if (!$hasActivatedPolicy) {
            AuditLog::write('system', $case->id, 'skipped', [
                'reason' => 'No activated policies found for customer',
                'customer_id' => $customer->id
            ]);
            return;
        }

        $this->runSanctionsCheck($os, $case, $customer);
    }

    /**
     * Process all customers with activated policies created today
     */
    private function processActivatedPolicyCustomers(OpenSanctionsClient $os)
    {
        // Get unique customers who have activated policies created today
        $customers = Customer::with('profile')->whereHas('policies', function ($query) {
            $query->where('status', 1) // Activated
                  ->whereDate('created_at', today()); // Created today
        })->get();

        Log::info('Processing OpenSanctions for daily activated policy customers', [
            'date' => today()->format('Y-m-d'),
            'total_customers' => $customers->count()
        ]);

        foreach ($customers as $customer) {
            try {
                // Check if KYC case already exists for this customer
                $existingCase = KycCase::where('customer_id', $customer->id)->first();
                
                if ($existingCase) {
                    // Update existing case
                    $this->runSanctionsCheck($os, $existingCase, $customer);
                } else {
                    // Create new KYC case
                    $case = KycCase::create([
                        'customer_id' => $customer->id,
                        'status' => 'created',
                        'sanctions_max' => null,
                        'decision' => null
                    ]);

                    AuditLog::write('system', $case->id, 'kyc_case_created', [
                        'customer_id' => $customer->id,
                        'reason' => 'Customer has activated policies'
                    ]);

                    $this->runSanctionsCheck($os, $case, $customer);
                }
            } catch (\Throwable $e) {
                Log::error('Failed to process customer for OpenSanctions', [
                    'customer_id' => $customer->id,
                    'error' => $e->getMessage()
                ]);
                
                // Create audit log for failed case
                $case = KycCase::where('customer_id', $customer->id)->first();
                if ($case) {
                    AuditLog::write('system', $case->id, 'error', [
                        'msg' => $e->getMessage(),
                        'customer_id' => $customer->id
                    ]);
                }
            }
        }
    }

    /**
     * Run sanctions check for a specific case and customer
     */
    private function runSanctionsCheck(OpenSanctionsClient $os, KycCase $case, Customer $customer)
    {
        try {
            // Construct full name from available fields
            $fullName = trim($customer->firstName . ' ' . $customer->middleName . ' ' . $customer->lastName);
            
            // Get DOB from customer profile only (dob column exists only in customerprofile table)
            $dob = null;
            if ($customer->profile && $customer->profile->dob) {
                $dob = \Carbon\Carbon::parse($customer->profile->dob)->format('Y-m-d');
            }
            
            // Try to get nationality from customer profile or use default
            $nationalityCode = null;
            if ($customer->profile) {
                $nationalityCode = $customer->profile->nationality ?? null;
            }
            
            $res = $os->match($fullName, $dob, $nationalityCode);
            
            $results = data_get($res, 'responses.q1.results', []);
            $max = collect($results)->pluck('score')->max() ?? 0;

            // Extract datasets from individual results
            $allDatasets = [];
            foreach ($results as $result) {
                if (isset($result['datasets']) && is_array($result['datasets'])) {
                    foreach ($result['datasets'] as $dataset) {
                        if (!in_array($dataset, $allDatasets)) {
                            $allDatasets[] = $dataset;
                        }
                    }
                }
            }
            
            // Extract target from individual results (store exactly as received)
            // The target field is within each result in the top_matches array
            $target = false; // Default to false
            if (!empty($results)) {
                // Get target from the first result (highest scoring match)
                $firstResult = $results[0];
                $target = data_get($firstResult, 'target', false);
            }
            
            // Also check if there's a top-level target field as fallback
            if ($target === false) {
                $target = data_get($res, 'target', false);
            }
            
            // Debug: Log the target value for troubleshooting
            \Log::info('OpenSanctions target value:', [
                'target' => $target,
                'target_type' => gettype($target),
                'full_response' => $res
            ]);
            
            // Extract programId from individual results
            $allProgramIds = [];
            foreach ($results as $result) {
                if (isset($result['properties']['programId']) && is_array($result['properties']['programId'])) {
                    foreach ($result['properties']['programId'] as $programId) {
                        if (!in_array($programId, $allProgramIds)) {
                            $allProgramIds[] = $programId;
                        }
                    }
                }
            }
            
            // Limit to reasonable numbers
            $datasets = array_slice($allDatasets, 0, 20);
            $programIds = array_slice($allProgramIds, 0, 20);

            // Store AML result with limited results data to avoid size constraints
            AmlResult::create([
                'kyc_case_id' => $case->id,
                'query'   => [
                    'name' => $fullName,
                    'dob' => $dob,
                    'nat' => $nationalityCode
                ],
                'results' => [
                    'total_results' => count($results),
                    'top_matches' => array_slice($results, 0, 5), // Store only top 5 matches
                    'api_response' => [
                        'status' => data_get($res, 'status', 'unknown'),
                        'total' => data_get($res, 'total', 0)
                    ]
                ],
                'max_score'=> $max,
                'datasets' => $datasets,
                'target' => $target,
                'programId' => $programIds,
            ]);

            // Update case status based on score
            $case->sanctions_max = $max;
            $case->status = $max >= 0.9 ? 'rejected' : ($max >= 0.7 ? 'review' : 'approved');
            $case->decision = [
                'max_score' => $max,
                'final' => $case->status,
                'checked_at' => now()->toISOString()
            ];
            $case->save();

            // Log successful completion
            AuditLog::write('opensanctions', $case->id, 'aml_done', [
                'max' => $max,
                'customer_id' => $customer->id,
                'status' => $case->status,
                'datasets_count' => count($datasets)
            ]);
            
            AuditLog::write('system', $case->id, 'decision', $case->decision);

            Log::info('OpenSanctions check completed', [
                'kyc_case_id' => $case->id,
                'customer_id' => $customer->id,
                'max_score' => $max,
                'status' => $case->status,
                'datasets_count' => count($datasets)
            ]);

        } catch (\Throwable $e) {
            AuditLog::write('system', $case->id, 'error', [
                'msg' => $e->getMessage(),
                'customer_id' => $customer->id
            ]);
            
            Log::error('OpenSanctions check failed', [
                'kyc_case_id' => $case->id,
                'customer_id' => $customer->id,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }
}
