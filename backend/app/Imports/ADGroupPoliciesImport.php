<?php

namespace AlphaDirect\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use AlphaDirect\Policy;
use AlphaDirect\Customer;
use AlphaDirect\CustomerProfile;
use AlphaDirect\KYC;
use AlphaDirect\Product;
use AlphaDirect\Region;
use AlphaDirect\CustomerBanking;
use AlphaDirect\ADGroupedBeneficiary;
use AlphaDirect\Models\EmployerGroup;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use AlphaDirect\Helper;
use AlphaDirect\Http\Controllers\PricingController;
use AlphaDirect\Models\EmployerGroupPolicy;
use AlphaDirect\Services\AdGroupKycService;
use AlphaDirect\Models\AdGroupKycCampaign;
use Illuminate\Http\Request;
use Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;

class ADGroupPoliciesImport implements ToModel, WithHeadingRow, WithChunkReading, WithCalculatedFormulas
{
    protected $upload_id;
    protected $policiesCache = [];
    protected $mainApplicantPolicies = []; // Track main applicant policies for KYC

    public function __construct($upload_id)
    {
        $this->upload_id = $upload_id;
    }

    public function model(array $row)
    {
        try {
            // Normalize column names to handle new Excel format
            $row = $this->normalizeColumnNames($row);
            if ($row['schemenumber'] == null || $row['schemenumber'] == '') {
                return;
            }
            // $persontype = strtolower(trim($row['persontype'] ?? 'employee'));
            $subdependantId = $this->getCellValue($row['subdependantid'] ?? null);

            if (!empty($subdependantId)) {
                // \Log::info('Processing DEPENDANT row', ['persontype' => $persontype]);
                // ===== DEPENDANT PROCESSING USING SUBDEPENDANTID =====
                // For dependants, we use the subdependantid column to:
                // 1. Find the main applicant's OMANG or Passport number (subdependantid = ID number of main applicant)
                // 2. Look up the main applicant's policy using that ID number in customer profile
                // 3. Add this dependant to that policy as a beneficiary

                // Check if subdependantid field exists
                if (empty($subdependantId)) {
                    \Log::warning('Dependant row missing subdependantid field', ['row' => $row]);
                    return null; // Skip if no subdependantid specified
                }

                // Find the main applicant's policy first
                $mainApplicantPolicy = $this->findMainApplicantPolicy($row,$subdependantId);

                \Log::info('Main applicant policy lookup result', ['policy' => $mainApplicantPolicy ? $mainApplicantPolicy->toArray() : 'null']);

                if ($mainApplicantPolicy) {
                    $this->addBeneficiary($row, $mainApplicantPolicy, $subdependantId);

                    // Calculate total premium for the policy (employee + all dependants)
                    $this->calculatePremiumFromDB($mainApplicantPolicy);
                } else {
                    \Log::warning('Could not find main applicant policy for dependant', [
                        'subdependantid' => $subdependantId,
                        'employer_group_id' => $row['schemenumber']
                    ]);
                }

                return null; // Dependants don't create policies
            } else {

                // \Log::info('Processing EMPLOYEE row', ['persontype' => $persontype]);
                // For employees (main applicants), create customer, policy, etc.
                // Employees should have empty/null subdependantid field

                if (!empty($subdependantId)) {
                    \Log::warning('Row marked as employee but has subdependantid field - should be dependant', ['row' => $row]);
                    return null; // Skip this row as it's likely a dependant with wrong persontype
                }

                $customer = $this->customerCreate($row);
                \Log::info('Customer created', ['customer_id' => $customer ? $customer->id : 'null']);

                $policy = $this->policyCreate($row, $customer);
                \Log::info('Policy created', ['policy_id' => $policy ? $policy->id : 'null', 'policy_number' => $policy ? $policy->policyNumber : 'null']);

                // 🔹 If policy is null → skip this row completely
                if (!$policy) {
                    \Log::warning('Policy creation failed, skipping row');
                    return null;
                }

                $company = $this->employerGroupPolicyCreate($row, $policy);
                \Log::info('Employer group policy created', ['employer_group_policy_id' => $company ? $company->id : 'null']);

                // // --- Add dependant only if policy is new (old format) ---
                // if (!empty($row['dependantfirstname']) && !empty($row['dependantdateofbirth_yyyy_mm_dd'])) {
                //     $this->addBeneficiary($row, $policy);
                // }

                // Calculate total premium for the policy (employee + all dependants)
                $this->calculatePremiumFromDB($policy);
                \Log::info('EMPLOYEE processing completed', ['policy_id' => $policy->id, 'policy_number' => $policy->policyNumber]);

                // Track main applicant policy for KYC generation
                $this->mainApplicantPolicies[] = $policy;

                return $policy;
            }

        } catch (\Exception $e) {
            // dd($e);
            \Log::error('ADGroupPoliciesImport Error: ' . $e->getMessage(), ['row' => $row]);
            return null;
        }
    }

    private function addBeneficiary($row, $policy, $subdependantId)
    {
        // Safety check: Ensure policy is not cancelled
        if ($policy->status == 2) {
            \Log::warning('Attempted to add beneficiary to cancelled policy, skipping', [
                'policy_id' => $policy->id,
                'policy_number' => $policy->policyNumber,
                'policy_status' => $policy->status,
                'beneficiary_name' => $row['beneficiaryfullname'] ?? 'Unknown'
            ]);
            return; // Skip adding beneficiary to cancelled policy
        }

        // Check for existing beneficiary (same policy + same ID number)
        $b = ADGroupedBeneficiary::where('policy_id', $policy->id)
            ->where(function($q) use ($row) {
                $q->where('omang', $row['idnumber'] ?? '')
                ->orWhere('passport', $row['passportnumber'] ?? '');
            })->first();

        if (!$b) {
            // Create new beneficiary if not exists
            $b = new ADGroupedBeneficiary();
            $b->policy_id = $policy->id;
            \Log::info('Creating new beneficiary', [
                'policy_id' => $policy->id,
                'id_number' => $row['idnumber'] ?? 'none',
                'name' => $row['beneficiaryfullname'] ?? 'Unknown'
            ]);
        } else {
            \Log::info('Updating existing beneficiary', [
                'beneficiary_id' => $b->id,
                'policy_id' => $policy->id,
                'id_number' => $row['idnumber'] ?? 'none',
                'name' => $row['beneficiaryfullname'] ?? 'Unknown'
            ]);
        }

        // Update beneficiary details
        // Handle BeneficiaryFullName field - parse into first and last names
        if (isset($row['beneficiaryfullname']) && !empty($row['beneficiaryfullname'])) {
            $fullName = trim($row['beneficiaryfullname']);
            $nameParts = explode(' ', $fullName, 2);
            $b->first_name = $nameParts[0];
            $b->last_name = isset($nameParts[1]) ? $nameParts[1] : '';
        } else {
            // Only set empty names if this is a new beneficiary
            if (!$b->exists) {
                $b->first_name = '';
                $b->last_name  = '';
            }
        }

        $b->relation = $row['beneficiaryrelationshiptomember'] ?? 'dependant';

        // Update ID information if provided
        if (strtolower($row['idtype'] ?? '') === 'omang' && !empty($row['idnumber'])) {
            $b->omang = $row['idnumber'];
        } elseif (strtolower($row['idtype'] ?? '') === 'passport' && !empty($row['passportnumber'])) {
            $b->passport = $row['passportnumber'];
        }

        // Update date of birth if provided
        $depDobParsed = $this->parseDateFlexible($row['beneficiarydateofbirth'] ?? '');
        if ($depDobParsed) {
            $b->dob = $depDobParsed;
        }

        // Update other details
        $b->gender = strtoupper(substr(trim($row['beneficiarygender'] ?? ''), 0, 1)) === 'M' ? 1 : 0;
        $b->email = $row['contactemail'] ?? null;

        // Clean and update phone number
        $cleanPhone = preg_replace('/^267/', '', str_replace(' ', '', ltrim($row['contactmobile'] ?? '', '+')));
        $b->cellphone = $cleanPhone;

        // Update address
        $newAddress = $this->combineAddress($row);
        $b->address = $newAddress;
        $b->city = $row['town'] ?? '';
        $b->status = $row['policystatus'] ?? 'Active';

        // Store persontype, subdependantid in ADGroupedBeneficiary table
        if (isset($row['persontype'])) {
            $b->person_type = $row['persontype'];
        }
        if (isset($subdependantId)) {
            $b->dependant_of = $subdependantId;
        }

        // Store the main applicant's employee ID for dependants
        // Find the main applicant's employee ID from their employer group policy
        $mainApplicantEmployeeId = null;
        if (isset($subdependantId) && !empty($subdependantId)) {
            $mainApplicantPolicy = $this->findMainApplicantPolicy($row,$subdependantId);
            if ($mainApplicantPolicy) {
                $employerGroupPolicy = EmployerGroupPolicy::where('policy_id', $mainApplicantPolicy->id)->first();
                if ($employerGroupPolicy) {
                    $mainApplicantEmployeeId = $employerGroupPolicy->employee_id;
                    \Log::info('Found main applicant employee ID for dependant', [
                        'dependant_name' => $row['beneficiaryfullname'] ?? 'Unknown',
                        'main_applicant_employee_id' => $mainApplicantEmployeeId,
                        'policy_id' => $mainApplicantPolicy->id
                    ]);
                } else {
                    \Log::warning('Could not find employer group policy for main applicant', [
                        'policy_id' => $mainApplicantPolicy->id,
                        'dependant_name' => $row['beneficiaryfullname'] ?? 'Unknown'
                    ]);
                }
            } else {
                \Log::warning('Could not find main applicant policy to get employee ID', [
                    'subdependantid' => $subdependantId,
                    'dependant_name' => $row['beneficiaryfullname'] ?? 'Unknown'
                ]);
            }
        }

        $b->employee_id = $mainApplicantEmployeeId;

        $b->save();
    }

    private function calculatePremiumFromDB($policy)
    {
        $customer = $policy->customer;
        $profile  = $customer->profile;

        $subData = [[
            'age'       => $profile->dob ? Carbon::parse($profile->dob)->age : null,
            'gender'    => $profile->gender ? 'M' : 'F',
            'applicant' => 'main'
        ]];

        $beneficiaries = ADGroupedBeneficiary::where('policy_id', $policy->id)->get();
        foreach ($beneficiaries as $b) {
            $subData[] = [
                'age'       => $b->dob ? Carbon::parse($b->dob)->age : null,
                'gender'    => $b->gender ? 'M' : 'F',
                'applicant' => 'sub'
            ];
        }

        $payload = [
            'product_id' => 12,
            'product'    => $policy->plan_id,
            'subData'    => $subData
        ];

        $request = new Request($payload);
        $pricingController = app()->make(PricingController::class);
        $response = $pricingController->calculatePremium($request);

        if ($response instanceof \Illuminate\Http\JsonResponse) {
            $premiumData = $response->getData(true);
            $premiumCharge = $premiumData['total_premium'] ?? 0;

            // Get additional fees from the policy's employer group policy record
            $employerGroupPolicy = EmployerGroupPolicy::where('policy_id', $policy->id)->first();
            $documentationFee = $employerGroupPolicy->documentation_fee ?? 0;
            $interactionFee = $employerGroupPolicy->interaction_fee ?? 0;
            $levyFee = $employerGroupPolicy->levy_fee ?? 0;

            // Calculate total premium: PremiumCharge + DocumentationFee + InteractionFee + LevyFee
            $totalPremium = $premiumCharge + $documentationFee + $interactionFee + $levyFee;

            $product   = Product::find(12);
            $regionVat = Region::where('id', $product->region_id)->value('vat');

            $policy->premium     = $totalPremium;
            $policy->vat         = round($totalPremium * ($regionVat / 100), 2);
            $policy->vat_percent = $regionVat;
            $policy->save();
        }
    }

    private function customerCreate($row)
    {
        // Clean phone
        $phone = trim($row['contactmobile'] ?? '');
        $phone = str_replace(' ', '', $phone);             // remove all spaces
        $phone = ltrim($phone, '+');                       // remove leading +
        $phone = preg_replace('/^267/', '', $phone);       // remove prefix 267

        // 🔹 Find existing customer (same phone + email)
        $customer = Customer::where('cellphone', $phone)
            ->where('email', $row['contactemail'])
            ->first();

        if (!$customer) {
            // Create new customer only if not exists
            $customer = new Customer();
            \Log::info('Creating new customer', ['phone' => $phone, 'email' => $row['contactemail']]);
        } else {
            \Log::info('Updating existing customer', ['customer_id' => $customer->id, 'phone' => $phone, 'email' => $row['contactemail']]);
        }

        // Update customer details (both new and existing)
        // Handle BeneficiaryFullName field - parse into first and last names
        if (isset($row['subscribername']) && !empty($row['subscribername'])) {
            $fullName = trim($row['subscribername']);
            $nameParts = explode(' ', $fullName, 2);
            $customer->firstName = $nameParts[0];
            $customer->lastName = isset($nameParts[1]) ? $nameParts[1] : '';
        } else {
            // Only set empty names if this is a new customer
            if (!$customer->exists) {
                $customer->firstName = '';
                $customer->lastName  = '';
            }
        }

        $customer->email     = $row['contactemail'] ?? null;
        $customer->cellphone = $phone;
        $customer->save();

        // Get or create customer profile
        $profile = CustomerProfile::where('customer_id', $customer->id)->first();
        if (!$profile) {
            $profile = new CustomerProfile();
            $profile->customer_id = $customer->id;
            \Log::info('Creating new customer profile', ['customer_id' => $customer->id]);
        } else {
            \Log::info('Updating existing customer profile', ['customer_id' => $customer->id]);
        }

        // Update profile details
        $profile->gender = strtoupper($row['beneficiarygender'] ?? '') === 'M' ? 1 : 0;

        // Date of Birth
        if (!empty($row['beneficiarydateofbirth'])) {
            $parsed = $this->parseDateFlexible(trim($row['beneficiarydateofbirth']));
            if ($parsed) {
                $profile->dob = $parsed;
                \Log::info('Updated DOB', ['customer_id' => $customer->id]);
            }
        }

        // Address - combine all address lines and postal code
        $newAddress = $this->combineAddress($row);
        if ($newAddress !== $profile->address) {
            $profile->address = $newAddress;
            \Log::info('Updated address', ['customer_id' => $customer->id]);
        }

        $profile->city = $row['town'] ?? '';

        // IDs - update if provided
        if (strtolower($row['idtype'] ?? '') === 'omang' && !empty($row['idnumber'])) {
            $profile->omang = $row['idnumber'];
            \Log::info('Updated OMANG', ['customer_id' => $customer->id]);
        } elseif (strtolower($row['idtype'] ?? '') === 'passport' && !empty($row['passportnumber'])) {
            $profile->passport = $row['passportnumber'];
            \Log::info('Updated Passport', ['customer_id' => $customer->id]);
        }

        $profile->save();

        // Create KYC record if it doesn't exist
        KYC::firstOrNew(['customer_id' => $customer->id])->save();

        return $customer;
    }


    private function policyCreate($row, $customer)
    {
        $planMap = [
            'ad lite'      => 24,
            'ad essential' => 25,
            'ad core'      => 26,
            'ad premier'   => 27,
            'ad status'    => 28,
        ];

        $planRaw = strtolower(trim($row['plan'] ?? ''));
        $planId  = $planMap[$planRaw] ?? null;

        // Use database transaction with locking to prevent race conditions
        // Check for existing policy with same customer and plan
        return DB::transaction(function () use ($row, $customer, $planId) {
            $schemenumber = $row['schemenumber'] ?? null;
            
            // Lock customer's policies to prevent concurrent access
            // Check for existing policy with same customer and plan
            $existingPolicy = Policy::where('customer_id', $customer->id)
                ->where('plan_id', $planId)
                ->where('leadSource', 'excel-ad-group') // Only check AD Group policies
                ->lockForUpdate() // Lock to prevent concurrent access
                ->first();

            if ($existingPolicy) {
                // Check if it's in the same employer group
                if ($schemenumber) {
                    $employerGroupPolicy = EmployerGroupPolicy::where('policy_id', $existingPolicy->id)
                        ->where('employer_group_id', $schemenumber)
                        ->first();
                    
                    if ($employerGroupPolicy) {
                        // Same employer group - check status
                        if ($existingPolicy->status == 2) {
                            // Canceled - allow creating new policy
                            \Log::info('Existing policy is canceled (status=2), creating new policy', [
                                'existing_policy_id' => $existingPolicy->id,
                                'existing_policy_number' => $existingPolicy->policyNumber,
                                'customer_id' => $customer->id,
                                'plan_id' => $planId,
                                'schemenumber' => $schemenumber
                            ]);
                            // Continue to create new policy below
                        } else {
                            // Active policy in same employer group - skip
                            \Log::info('Policy already exists in same employer group and is not canceled, skipping row', [
                                'existing_policy_id' => $existingPolicy->id,
                                'existing_policy_number' => $existingPolicy->policyNumber,
                                'existing_policy_status' => $existingPolicy->status,
                                'customer_id' => $customer->id,
                                'plan_id' => $planId,
                                'schemenumber' => $schemenumber
                            ]);
                            return null;
                        }
                    } else {
                        // Different employer group - allow creating new policy
                        \Log::info('Existing policy found but in different employer group, allowing new policy creation', [
                            'existing_policy_id' => $existingPolicy->id,
                            'customer_id' => $customer->id,
                            'plan_id' => $planId,
                            'schemenumber' => $schemenumber
                        ]);
                        // Continue to create new policy below
                    }
                } else {
                    // No schemenumber provided - check status
                    if ($existingPolicy->status == 2) {
                        \Log::info('Existing policy is canceled (status=2), creating new policy', [
                            'existing_policy_id' => $existingPolicy->id,
                            'customer_id' => $customer->id,
                            'plan_id' => $planId
                        ]);
                        // Continue to create new policy below
                    } else {
                        \Log::info('Policy already exists and is not canceled, skipping row', [
                            'existing_policy_id' => $existingPolicy->id,
                            'existing_policy_number' => $existingPolicy->policyNumber,
                            'existing_policy_status' => $existingPolicy->status,
                            'customer_id' => $customer->id,
                            'plan_id' => $planId
                        ]);
                        return null;
                    }
                }
            }

            // Double-check after lock to handle race conditions
            // Re-check one more time to ensure no other process created it while we were processing
            $doubleCheck = Policy::where('customer_id', $customer->id)
                ->where('plan_id', $planId)
                ->where('leadSource', 'excel-ad-group')
                ->where('status', '!=', 2) // Not canceled
                ->first();

            if ($doubleCheck && $schemenumber) {
                // Verify it's not in the same employer group
                $doubleCheckEmployerGroup = EmployerGroupPolicy::where('policy_id', $doubleCheck->id)
                    ->where('employer_group_id', $schemenumber)
                    ->first();
                
                if ($doubleCheckEmployerGroup) {
                    \Log::warning('Duplicate policy detected after lock in same employer group, skipping row', [
                        'existing_policy_id' => $doubleCheck->id,
                        'existing_policy_number' => $doubleCheck->policyNumber,
                        'customer_id' => $customer->id,
                        'plan_id' => $planId,
                        'schemenumber' => $schemenumber
                    ]);
                    return null;
                }
            } elseif ($doubleCheck && !$schemenumber) {
                \Log::warning('Duplicate policy detected after lock, skipping row', [
                    'existing_policy_id' => $doubleCheck->id,
                    'existing_policy_number' => $doubleCheck->policyNumber,
                    'customer_id' => $customer->id,
                    'plan_id' => $planId
                ]);
                return null;
            }

            // New policy
            $product   = Product::find(12);
            $regionVat = Region::where('id', $product->region_id)->value('vat');

            $policy = new Policy();
            $policy->customer_id       = $customer->id;
            $policy->product_id        = 12;

            // Calculate TotalPremium from components: PremiumCharge + DocumentationFee + InteractionFee + LevyFee
            $premiumCharge = 0; // Will be calculated via API
            $documentationFee = $row['documentationfee'] ?? 0;
            $interactionFee = $row['interactionfee'] ?? 0;
            $levyFee = $row['levyfee'] ?? 0;

            // Store the calculated total premium (will be updated after API call)
            $policy->premium = $documentationFee + $interactionFee + $levyFee; // Will add PremiumCharge after API call
            $policy->vat               = 0;
            $policy->vat_percent       = $regionVat;
            $policy->status            = 0;
            $policy->leadSource        = 'excel-ad-group';
            $policy->has_vehicle          = $product->has_vehicle;
            $policy->has_member           = $product->has_member;
            $policy->preinspection        = $product->preinspection;
            $policy->is_motor_items       = $product->is_motor_items;
            $policy->limit                = $product->limit;
            $policy->kyc_customer         = $product->kyc_customer;
            $policy->kyc_recipient        = $product->kyc_recipient;

            // Use PolicyStartDate if available, otherwise use current date
            if (isset($row['policystartdate']) && !empty($row['policystartdate'])) {
                $policyStartDate = $this->parseDateFlexible($row['policystartdate']);
                $policy->policyActivatedDate = $policyStartDate ?: Carbon::now()->format('Y-m-d');
            } else {
                $policy->policyActivatedDate = Carbon::now()->format('Y-m-d');
            }

            $policy->plan_id           = $planId;
            $policy->save();

            // Generate policy number in format: HG{schemenumber}{year}{policyid}
            $policy->policyNumber = $this->generatePolicyNumber($row['schemenumber'], $policy->id);
            $policy->save();

            $banking = new CustomerBanking();
            $banking->customer_id = $customer->id;
            $banking->policy_id   = $policy->id;
            $banking->billing     = "CASH";
            $banking->save();

            return $policy;
        }, 5); // 5 second timeout for transaction
    }

    public function employerGroupPolicyCreate($row,$policy)
    {
        $employer = EmployerGroup::where('employer_group_id', $row['schemenumber'])->first();
        // if(!$employer){
        //         $comName = new EmployerGroup();
        //         $comName->employer_group_id = $row['schemenumber'];
        //         $comName->name = $row['debtorname'] ?? 'Unknown Employer';
        //         $comName->save();
        //         $employer = $comName;
        // }
        $employerPolicy = new EmployerGroupPolicy();
        $employerPolicy->employer_group_id = $row['schemenumber'];
        $employerPolicy->policy_id = $policy->id;

        // Generate EmployeeID if not provided in Excel
        // if (isset($row['employeeid']) && !empty($row['employeeid'])) {
        //     $employerPolicy->employee_id = $row['employeeid'];
        // } else {
            $employerPolicy->employee_id = $this->generateEmployeeId($row['schemenumber']);
        // }

        $employerPolicy->policyNumber = $policy->policyNumber;
        $employerPolicy->upload_id = $this->upload_id;

        // Store additional fields if they exist in the table
        if (isset($row['numberofinsured'])) {
            $employerPolicy->number_of_insured = $row['numberofinsured'];
        }

        if (isset($row['agentcode'])) {
            $employerPolicy->agent_code = $row['agentcode'];
        }

        if (isset($row['primaryagentname'])) {
            $employerPolicy->agent_name = $row['primaryagentname'];
        }

        // Store fee components for total premium calculation
        if (isset($row['documentationfee'])) {
            $employerPolicy->documentation_fee = $row['documentationfee'];
        }

        if (isset($row['interactionfee'])) {
            $employerPolicy->interaction_fee = $row['interactionfee'];
        }

        if (isset($row['levyfee'])) {
            $employerPolicy->levy_fee = $row['levyfee'];
        }

        // Store date fields
        // Always store today's date for application signed date (data not passed in Excel)
        $applicationSignedDate = Carbon::now()->format('Y-m-d');
        $employerPolicy->applicationsigneddate = $applicationSignedDate;

        // Policy Start Date will be set from WaitingEffectiveTo (when waiting period ends)
        // If WaitingEffectiveTo is not provided, use Application Signed Date
        $policyStartDate = $applicationSignedDate; // Default to application signed date
        $waitingEffectiveToDate = null;
        
        if (isset($row['waitingeffectiveto']) && !empty($row['waitingeffectiveto'])) {
            // Parse WaitingEffectiveTo date from Excel - this becomes the Policy Start Date
            $waitingEffectiveToDate = $this->parseDateFlexible($row['waitingeffectiveto']);
            if ($waitingEffectiveToDate) {
                $policyStartDate = Carbon::parse($waitingEffectiveToDate)->format('Y-m-d');
            }
        }
        
        $employerPolicy->policystartdate = $policyStartDate;

        // Policy Issued Effective Date: Date when first payment is done and policy is activated (status = 1)
        // This date will not be passed in Excel - always set to today's date
        $employerPolicy->policyissuedeffectivedate = Carbon::now()->format('Y-m-d');

        // Renewal Period: Start date is when policy was registered (today), end date is one year later
        // These dates will not be passed in Excel - calculated automatically
        $renewalPeriodStartDate = Carbon::now()->format('Y-m-d');
        $renewalPeriodEndDate = Carbon::parse($renewalPeriodStartDate)->addYear()->format('Y-m-d');
        
        $employerPolicy->renewalperiodstartdate = $renewalPeriodStartDate;
        $employerPolicy->renewalperiodenddate = $renewalPeriodEndDate;

        // Store waiting period fields
        if (isset($row['waitingcategory'])) {
            $employerPolicy->waitingcategory = $row['waitingcategory'];
        }

        // Parse waiting period dates from Excel (dates are provided, not number of days)
        if (isset($row['waitingeffectivefrom']) && !empty($row['waitingeffectivefrom'])) {
            $waitingEffectiveFromDate = $this->parseDateFlexible($row['waitingeffectivefrom']);
            if ($waitingEffectiveFromDate) {
                $employerPolicy->waitingeffectivefrom = Carbon::parse($waitingEffectiveFromDate)->format('Y-m-d');
            } else {
                // Fallback to application signed date if parsing fails
                $employerPolicy->waitingeffectivefrom = $applicationSignedDate;
            }
        } else {
            // Default to application signed date if not provided
            $employerPolicy->waitingeffectivefrom = $applicationSignedDate;
        }

        if ($waitingEffectiveToDate) {
            // Use the already parsed date
            $employerPolicy->waitingeffectiveto = Carbon::parse($waitingEffectiveToDate)->format('Y-m-d');
        } else {
            // Default to policy start date if not provided or parsing failed
            $employerPolicy->waitingeffectiveto = $policyStartDate;
        }

        $employerPolicy->save();
        return $employerPolicy;
    }

    public function chunkSize(): int
    {
        // Update progress counter safely on every chunk via cache
        try {
            $processed = (int) Cache::get('adgroup_progress_processed', 0);
            $processed += 100; // rough increment per chunk; UI will cap at total
            Cache::put('adgroup_progress_processed', $processed, 3600);
        } catch (\Throwable $e) {}
        return 100;
    }

    /**
     * Normalize column names to handle new Excel format
     */
    private function normalizeColumnNames($row)
    {
        $normalized = [];

        foreach ($row as $key => $value) {
            $normalizedKey = $this->getNormalizedKey($key);
            $normalized[$normalizedKey] = $value;
        }

        return $normalized;
    }

    /**
     * Generate EmployeeID in the format shown in Excel image
     */
    private function generateEmployeeId($schemenumber)
    {
        // Generate a random 4-digit number
        $randomNumber = str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);

        // Format: {SCHEMENUMBER}{RANDOM4DIGITS}
        return $schemenumber . $randomNumber;
    }

    /**
     * Generate Policy Number in format: ADH{year_last_two_digits}{3_digit_sequence}
     * Example: ADH25001
     * This method ensures uniqueness by checking the database and using proper locking
     */
    private function generatePolicyNumber($schemenumber, $policyId)
    {
        // Prefix for all policies
        $prefix = 'ADH';

        // Get current 2-digit year
        $year = Carbon::now()->format('y');

        // Use database lock to prevent race conditions
        $maxRetries = 10;
        $retryCount = 0;

        while ($retryCount < $maxRetries) {
            try {
                // Use database transaction with lock to ensure atomicity
                $policyNumber = DB::transaction(function () use ($prefix, $year, $policyId) {
                    // Get all existing policy numbers for this prefix and year
                    // We need to check all of them to find the true maximum (base36 doesn't sort correctly as strings)
                    $existingPolicies = Policy::where('policyNumber', 'like', $prefix . $year . '%')
                        ->lockForUpdate() // Lock the rows to prevent concurrent access
                        ->pluck('policyNumber')
                        ->toArray();

                    $sequenceNumber = 1; // Start from 1
                    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
                    $base = strlen($characters);

                    // Find the maximum sequence number by converting all sequences to numbers
                    foreach ($existingPolicies as $existingNumber) {
                        if (strlen($existingNumber) >= 6) {
                            $yearPart = substr($existingNumber, 3, 2); // Extract year (e.g., "25")
                            if ($yearPart === $year) {
                                $sequencePart = substr($existingNumber, 5); // Extract sequence (e.g., "001")
                                
                                // Convert base36 sequence back to number
                                $num = 0;
                                for ($i = 0; $i < strlen($sequencePart); $i++) {
                                    $char = $sequencePart[$i];
                                    $charIndex = strpos($characters, $char);
                                    if ($charIndex !== false) {
                                        $num = $num * $base + $charIndex;
                                    }
                                }
                                
                                // Keep track of the maximum sequence number found
                                if ($num >= $sequenceNumber) {
                                    $sequenceNumber = $num + 1; // Increment for next number
                                }
                            }
                        }
                    }

                    // Convert numeric counter to base36-like string (max 3 chars)
                    $sequence = '';
                    $num = $sequenceNumber;

                    for ($i = 0; $i < 3; $i++) {
                        $sequence = $characters[$num % $base] . $sequence;
                        $num = intdiv($num, $base);
                    }

                    // Pad if less than 3 chars
                    $sequence = str_pad($sequence, 3, '0', STR_PAD_LEFT);

                    // Combine all parts
                    $generatedNumber = $prefix . $year . $sequence;

                    // Double-check uniqueness (in case another process created it)
                    $exists = Policy::where('policyNumber', $generatedNumber)->exists();
                    
                    if ($exists) {
                        // If exists, increment and try again
                        $sequenceNumber++;
                        $num = $sequenceNumber;
                        $sequence = '';
                        for ($i = 0; $i < 3; $i++) {
                            $sequence = $characters[$num % $base] . $sequence;
                            $num = intdiv($num, $base);
                        }
                        $sequence = str_pad($sequence, 3, '0', STR_PAD_LEFT);
                        $generatedNumber = $prefix . $year . $sequence;
                    }

                    return $generatedNumber;
                }, 5); // 5 second timeout for transaction

                // Verify the generated number is unique one more time
                $finalCheck = Policy::where('policyNumber', $policyNumber)->exists();
                if (!$finalCheck) {
                    return $policyNumber;
                }

                // If still exists, increment retry and try again
                $retryCount++;
                \Log::warning('Policy number collision detected, retrying', [
                    'policy_number' => $policyNumber,
                    'retry_count' => $retryCount,
                    'policy_id' => $policyId
                ]);

            } catch (\Exception $e) {
                $retryCount++;
                \Log::error('Error generating policy number', [
                    'error' => $e->getMessage(),
                    'retry_count' => $retryCount,
                    'policy_id' => $policyId
                ]);

                if ($retryCount >= $maxRetries) {
                    // Fallback: use policy ID to ensure uniqueness
                    \Log::warning('Max retries reached, using fallback policy number with policy ID', [
                        'policy_id' => $policyId
                    ]);
                    $fallbackSequence = str_pad((string)$policyId, 6, '0', STR_PAD_LEFT);
                    return $prefix . $year . substr($fallbackSequence, -3);
                }

                // Small delay before retry
                usleep(100000); // 0.1 second
            }
        }

        // Final fallback: use policy ID
        $fallbackSequence = str_pad((string)$policyId, 6, '0', STR_PAD_LEFT);
        return $prefix . $year . substr($fallbackSequence, -3);
    }


    /**
     * Combine address lines 1, 2, 3 and postal code into a single address string
     */
    private function combineAddress($row)
    {
        $addressParts = [];

        // Add address line 1 if exists
        if (!empty($row['addressline1'])) {
            $addressParts[] = trim($row['addressline1']);
        }

        // Add address line 2 if exists
        if (!empty($row['addressline2'])) {
            $addressParts[] = trim($row['addressline2']);
        }

        // Add address line 3 if exists
        if (!empty($row['addressline3'])) {
            $addressParts[] = trim($row['addressline3']);
        }

        // Add postal code if exists
        if (!empty($row['postalcode'])) {
            $addressParts[] = trim($row['postalcode']);
        }

        // Join all parts with comma and space, then clean up extra spaces
        $combinedAddress = implode(', ', $addressParts);

        // Clean up any double commas or extra spaces
        $combinedAddress = preg_replace('/,\s*,/', ',', $combinedAddress);
        $combinedAddress = preg_replace('/\s+/', ' ', $combinedAddress);

        $finalAddress = trim($combinedAddress);

        // Log the address combination for debugging
        \Log::info('Address combination', [
            'addressline1' => $row['addressline1'] ?? 'empty',
            'addressline2' => $row['addressline2'] ?? 'empty',
            'addressline3' => $row['addressline3'] ?? 'empty',
            'postalcode' => $row['postalcode'] ?? 'empty',
            'combined_address' => $finalAddress
        ]);

        return $finalAddress;
    }

    /**
     * Map Excel column names to internal field names for Premium_Registry_File format
     */
    private function getNormalizedKey($key)
    {
        $key = trim($key);

        // Mapping from Premium_Registry_File Excel format to internal field names
        $mapping = [
            // Basic identifiers
            'schemenumber' => 'schemenumber',
            'scheme number' => 'schemenumber',

            'debtorname' => 'debtorname',
            'debtor name' => 'debtorname',

            'employeeid' => 'employeeid',
            'employee id' => 'employeeid',

            'persontype' => 'persontype',
            'person type' => 'persontype',

            'subdependantid' => 'subdependantid',
            'sub dependant id' => 'subdependantid',

            // Names
            'subscribername' => 'subscribername',
            'subscriber name' => 'subscribername',

            'beneficiaryfullname' => 'beneficiaryfullname',
            'beneficiary full name' => 'beneficiaryfullname',

            'beneficiaryrelationshiptomember' => 'beneficiaryrelationshiptomember',
            'beneficiary relationship to member' => 'beneficiaryrelationshiptomember',

            'beneficiarygender' => 'beneficiarygender',
            'beneficiary gender' => 'beneficiarygender',

            'beneficiarydateofbirth' => 'beneficiarydateofbirth',
            'beneficiary date of birth' => 'beneficiarydateofbirth',

            // ID details
            'idtype' => 'idtype',
            'id type' => 'idtype',

            'idnumber' => 'idnumber',
            'id number' => 'idnumber',

            'passportnumber' => 'passportnumber',
            'passport number' => 'passportnumber',

            'title' => 'title',

            // Contact details
            'contactemail' => 'contactemail',
            'contact email' => 'contactemail',

            'contactmobile' => 'contactmobile',
            'contact mobile' => 'contactmobile',

            // Address details
            'addressline1' => 'addressline1',
            'address line 1' => 'addressline1',

            'addressline2' => 'addressline2',
            'address line 2' => 'addressline2',

            'addressline3' => 'addressline3',
            'address line 3' => 'addressline3',

            'town' => 'town',

            'postalcode' => 'postalcode',
            'postal code' => 'postalcode',

            // Plan details
            'plan' => 'plan',

            'planbenefitlevel' => 'planbenefitlevel',
            'plan benefit level' => 'planbenefitlevel',

            // Dates
            'applicationsigneddate' => 'applicationsigneddate',
            'application signed date' => 'applicationsigneddate',

            'policystartdate' => 'policystartdate',
            'policy start date' => 'policystartdate',

            'policyissuedeffectivedate' => 'policyissuedeffectivedate',
            'policy issued effective date' => 'policyissuedeffectivedate',

            'renewalperiodstartdate' => 'renewalperiodstartdate',
            'renewal period start date' => 'renewalperiodstartdate',

            'renewalperiodenddate' => 'renewalperiodenddate',
            'renewal period end date' => 'renewalperiodenddate',

            // Status
            'policystatus' => 'policystatus',
            'policy status' => 'policystatus',

            'terminationreasoncode' => 'terminationreasoncode',
            'termination reason code' => 'terminationreasoncode',

            'resignationdate' => 'resignationdate',
            'resignation date' => 'resignationdate',

            'resignationreason' => 'resignationreason',
            'resignation reason' => 'resignationreason',

            'suspension' => 'suspension',

            'suspensiondate' => 'suspensiondate',
            'suspension date' => 'suspensiondate',

            // Waiting periods
            'waitingcategory' => 'waitingcategory',
            'waiting category' => 'waitingcategory',

            'waitingeffectivefrom' => 'waitingeffectivefrom',
            'waiting effective from' => 'waitingeffectivefrom',

            'waitingeffectiveto' => 'waitingeffectiveto',
            'waiting effective to' => 'waitingeffectiveto',

            // Premium details
            'documentationfee' => 'documentationfee',
            'documentation fee' => 'documentationfee',

            'interactionfee' => 'interactionfee',
            'interaction fee' => 'interactionfee',

            'levyfee' => 'levyfee',
            'levy fee' => 'levyfee',

            'premiumcharge' => 'premiumcharge',
            'premium charge' => 'premiumcharge',

            'totalpremium' => 'totalpremium',
            'total premium' => 'totalpremium',

            'numberofinsured' => 'numberofinsured',
            'number of insured' => 'numberofinsured',

            // Agent details
            'agentcode' => 'agentcode',
            'agent code' => 'agentcode',

            'primaryagentname' => 'primaryagentname',
            'primary agent name' => 'primaryagentname',
        ];

        // Convert key to lowercase and remove extra spaces
        $normalizedKey = strtolower(trim($key));

        // Return mapped key or original key if no mapping found
        return $mapping[$normalizedKey] ?? $key;
    }

    /**
     * Find the main applicant's policy for dependants using subdependantid (OMANG/Passport number)
     * The subdependantid field contains the OMANG or Passport number of the main applicant
     */
    private function findMainApplicantPolicy($row,$subdependantId)
    {
        // Find the employer group first
        $employer = EmployerGroup::where('employer_group_id', $row['schemenumber'])->first();

        if (!$employer) {
            \Log::warning('Employer group not found', ['employer_group_id' => $row['schemenumber']]);
            return null;
        }

        // Use subdependantid field to find the main applicant's OMANG or passport number
        $mainApplicantIdNumber = $subdependantId ?? null;

        if (!$mainApplicantIdNumber) {
            \Log::warning('SubDependantID field is empty', ['row' => $row]);
            return null;
        }

        \Log::info('Searching for main applicant policy', [
            'subdependantid' => $mainApplicantIdNumber,
            'employer_group_id' => $employer->id,
            'employer_group_name' => $employer->name ?? 'Unknown'
        ]);

        // $policies = Policy::whereHas('employerGroupPolicies', function($query) use ($employer) {
        //     $query->where('employer_group_id', $employer->employer_group_id);
        // })->get();
        // dd($policies);
        // Find the main applicant's policy using OMANG or passport number
        // Exclude cancelled policies (status = 2) to ensure we get the active policy
        // Order by created_at DESC to get the most recent policy if multiple exist
        $mainApplicantPolicy = Policy::whereHas('employerGroupPolicies', function($query) use ($employer) {
            $query->where('employer_group_id', $employer->employer_group_id);
        })->whereHas('customer.profile', function($query) use ($mainApplicantIdNumber) {
            $query->where('omang', $mainApplicantIdNumber)
                  ->orWhere('passport', $mainApplicantIdNumber);
        })->where('status', '!=', 2) // Exclude cancelled policies
          ->orderBy('created_at', 'DESC') // Get the most recent policy
          ->first();
        // dd($mainApplicantPolicy);
        if (!$mainApplicantPolicy) {
            \Log::warning('Main applicant policy not found', [
                'subdependantid' => $mainApplicantIdNumber,
                'employer_group_id' => $employer->id,
                'employer_group_name' => $employer->name ?? 'Unknown',
                'search_criteria' => 'OMANG or Passport number'
            ]);
        } else {
            \Log::info('Found main applicant policy', [
                'policy_id' => $mainApplicantPolicy->id,
                'policy_number' => $mainApplicantPolicy->policyNumber,
                'subdependantid' => $mainApplicantIdNumber,
                'main_applicant_name' => $mainApplicantPolicy->customer->firstName . ' ' . $mainApplicantPolicy->customer->lastName
            ]);
        }

        return $mainApplicantPolicy;
    }

    private function parseDateFlexible($value)
    {
        if ($value === null || $value === '') { return null; }

        // Excel serial date number
        if (is_numeric($value)) {
            try {
                return Carbon::createFromTimestamp(((int)$value - 25569) * 86400)->format('Y-m-d');
            } catch (\Throwable $e) {}
        }

        $value = trim((string)$value);

        // Common formats
        $formats = [
            'd/m/Y', 'd-m-Y', 'Y-m-d', 'm/d/Y', 'm-d-Y', 'd.m.Y', 'Y/m/d'
        ];
        foreach ($formats as $fmt) {
            try {
                $dt = Carbon::createFromFormat($fmt, $value);
                if ($dt !== false) { return $dt->format('Y-m-d'); }
            } catch (\Throwable $e) { /* try next */ }
        }

        // Fallback to Carbon parser (best-effort)
        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable $e) {
            \Log::warning('Unparseable date value', ['value' => $value]);
            return null;
        }
    }

    private function getCellValue($value)
{
    // If it's a PhpSpreadsheet Cell, handle formula
    if ($value instanceof Cell) {
        if ($value->isFormula()) {
            return $value->getCalculatedValue();
        }
        return $value->getValue();
    }

    // If it's not a Cell, just return as-is
    return $value;
}

    /**
     * Generate KYC links for main applicant policies after import completion
     */
    public function generateKycLinksForMainApplicants()
    {
        try {
            if (empty($this->mainApplicantPolicies)) {
                \Log::info('No main applicant policies found for KYC generation');
                return;
            }

            \Log::info('Generating KYC links for main applicant policies', [
                'count' => count($this->mainApplicantPolicies),
                'upload_id' => $this->upload_id
            ]);

            // Get the KYC service
            $kycService = app(AdGroupKycService::class);

            // Group policies by employer group
            $policiesByGroup = collect($this->mainApplicantPolicies)->groupBy(function($policy) {
                $employerGroupPolicy = EmployerGroupPolicy::where('policy_id', $policy->id)->first();
                return $employerGroupPolicy ? $employerGroupPolicy->employer_group_id : 'unknown';
            });

            foreach ($policiesByGroup as $employerGroupId => $groupPolicies) {
                if ($employerGroupId === 'unknown') {
                    \Log::warning('Skipping policies with unknown employer group for KYC generation');
                    continue;
                }

                \Log::info('Processing KYC for employer group: ' . $employerGroupId . ' with ' . $groupPolicies->count() . ' policies');

                // Create or get existing campaign for this employer group
                $campaign = $this->getOrCreateKycCampaign($employerGroupId);

                if (!$campaign) {
                    \Log::error('Failed to create KYC campaign for employer group: ' . $employerGroupId);
                    continue;
                }

                // Generate KYC links for main applicant policies
                $policyIds = $groupPolicies->pluck('id')->toArray();
                $links = $kycService->generateLinksForPolicies($campaign, $policyIds);

                \Log::info('Generated ' . count($links) . ' KYC links for employer group: ' . $employerGroupId);

                // Log individual employees who will receive KYC emails
                foreach ($links as $link) {
                    if ($link->customer) {
                        \Log::info('KYC email will be sent to main applicant: ' . $link->customer->firstName . ' ' . $link->customer->lastName . ' (' . $link->customer->email . ') for policy: ' . $link->policy->policyNumber);
                    }
                }

                // Send KYC links immediately to individual main applicants
                $linkIds = collect($links)->pluck('id')->toArray();

                \Log::info('About to send KYC links', [
                    'link_ids' => $linkIds,
                    'channels' => ['email', 'sms'],
                    'immediate' => true
                ]);

                // Force log driver for testing
                \Config::set('mail.default', 'log');
                \Log::info('Mail driver set to: ' . \Config::get('mail.default'));

                // Send emails directly using Mail facade for immediate delivery
                $emailResults = [];
                foreach ($links as $link) {
                    if ($link->customer && $link->customer->email) {
                        \Log::info('Attempting to send email to: ' . $link->customer->email);

                        try {
                            // Simple text email for testing
                            \Mail::raw("Hello {$link->customer->firstName},\n\nYour AD Group insurance policy requires KYC verification.\n\nPolicy Number: {$link->policy->policyNumber}\nOTP Code: {$link->otp_code}\nKYC URL: {$link->kyc_url}\n\nPlease complete your KYC verification as soon as possible.\n\nBest regards,\nAlphaDirect Insurance Team", function ($message) use ($link) {
                                $message->to($link->customer->email)
                                        ->subject('AD Group Insurance - KYC Verification Required');
                            });

                            $emailResults[$link->id] = ['success' => true, 'method' => 'simple_mail'];
                            \Log::info('✅ KYC email sent successfully to: ' . $link->customer->email);

                        } catch (\Exception $e) {
                            $emailResults[$link->id] = ['success' => false, 'error' => $e->getMessage()];
                            \Log::error('❌ Failed to send KYC email to: ' . $link->customer->email . ' - ' . $e->getMessage());
                            \Log::error('Error details: ' . $e->getTraceAsString());
                        }
                    } else {
                        \Log::warning('Skipping email - customer or email not found for link: ' . $link->id);
                    }
                }

                // Also try the service method for comparison
                $sendResults = $kycService->sendKycLinks($linkIds, ['email', 'sms'], true);

                \Log::info('KYC send results', [
                    'direct_email_results' => $emailResults,
                    'service_results' => $sendResults,
                    'total_links' => count($linkIds)
                ]);

                $directEmailSuccess = collect($emailResults)->where('success', true)->count();
                $directEmailFailure = collect($emailResults)->where('success', false)->count();

                $serviceSuccess = collect($sendResults)->where('success', true)->count();
                $serviceFailure = collect($sendResults)->where('success', false)->count();

                \Log::info('KYC email sending results - Direct Mail Success: ' . $directEmailSuccess . ', Direct Mail Failed: ' . $directEmailFailure . ', Service Success: ' . $serviceSuccess . ', Service Failed: ' . $serviceFailure);

                // Log detailed results
                $employeeDetails = [];
                foreach ($links as $link) {
                    if ($link->customer) {
                        $employeeDetails[] = [
                            'employee_name' => $link->customer->firstName . ' ' . $link->customer->lastName,
                            'employee_email' => $link->customer->email,
                            'policy_number' => $link->policy->policyNumber,
                            'link_id' => $link->id
                        ];
                    }
                }

                \Log::info('AD Group KYC links generated and sent to main applicants', [
                    'upload_id' => $this->upload_id,
                    'employer_group_id' => $employerGroupId,
                    'campaign_id' => $campaign->id,
                    'policies_count' => count($policyIds),
                    'links_generated' => count($links),
                    'links_sent_success' => $successCount,
                    'links_sent_failed' => $failureCount,
                    'main_applicants_notified' => $employeeDetails
                ]);
            }

            \Log::info('KYC link generation completed for upload: ' . $this->upload_id);

        } catch (\Exception $e) {
            \Log::error('Error generating KYC links for main applicants: ' . $e->getMessage(), [
                'upload_id' => $this->upload_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Get or create KYC campaign for employer group
     */
    private function getOrCreateKycCampaign($employerGroupId)
    {
        try {
            // Check if campaign already exists for this employer group
            $existingCampaign = AdGroupKycCampaign::where('employer_group_id', $employerGroupId)
                ->where('status', 'active')
                ->first();

            if ($existingCampaign) {
                return $existingCampaign;
            }

            // Get employer group details
            $employerGroup = EmployerGroup::where('employer_group_id', $employerGroupId)->first();
            $employerGroupName = $employerGroup ? $employerGroup->name : $employerGroupId;

            // Create new campaign
            $kycService = app(AdGroupKycService::class);
            $campaign = $kycService->createDefaultAdGroupCampaign($employerGroupId, $employerGroupName);

            \Log::info('Created new KYC campaign for employer group: ' . $employerGroupId);

            return $campaign;

        } catch (\Exception $e) {
            \Log::error('Error creating KYC campaign for employer group ' . $employerGroupId . ': ' . $e->getMessage());
            return null;
        }
    }

    // public function __destruct()
    // {
    //     foreach ($this->policiesCache as $groupId => $cache) {
    //         $policy  = $cache['policy'];
    //         $plan_id = $cache['plan_id'];
    //         $subData = $cache['subData'];

    //         $payload = [
    //             'product_id' => 12,
    //             'product'    => $plan_id,
    //             'subData'    => $subData
    //         ];

    //         $pricingController = new PricingController();
    //         $response = $pricingController->calculatePremium($payload);
    //         // $response = Http::post(url('/api/calculate-premium'), $payload);

    //         if ($response->ok()) {
    //             $premiumData = $response->json();
    //             $premium     = $premiumData['total_premium'] ?? 0;

    //             $product   = Product::find(12);
    //             $regionVat = Region::where('id', $product->region_id)->value('vat');

    //             $policy->premium     = $premium;
    //             $policy->vat         = round($premium * ($regionVat / 100), 2);
    //             $policy->vat_percent = $regionVat;
    //             $policy->save();

    //             Helper::addInvoice($policy->id, $premium, Carbon::now()->format('Y-m-d'));
    //         } else {
    //             \Log::error("Premium calculation failed for group_id $groupId", [
    //                 'payload'  => $payload,
    //                 'response' => $response->body()
    //             ]);
    //         }
    //     }
    // }
}
