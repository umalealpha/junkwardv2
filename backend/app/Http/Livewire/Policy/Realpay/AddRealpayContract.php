<?php

namespace AlphaDirect\Http\Livewire\Policy\Realpay;

use Livewire\Component;
use AlphaDirect\Policy;
use AlphaDirect\Banks;
use AlphaDirect\BankBranches;
use AlphaDirect\Customer;
use AlphaDirect\CustomerProfile;
use AlphaDirect\RealpayPaymentRequest;
use AlphaDirect\RealpayClientContracts;
use AlphaDirect\CustomerBanking;
use AlphaDirect\RealpayContractDetails;
use AlphaDirect\RealpayContractInstallments;
use AlphaDirect\PolicyPremiumReratingLog;
use AlphaDirect\Models\User;
use Carbon\Carbon;
use Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AddRealpayContract extends Component
{
    public $policy;
    public $policyNumber;
    public $termId;
    public $actionId;
    public $previousActionId;

    // Form fields
    public $customerName;
    public $idTypeAndNumber;
    public $email;
    public $cellphone;
    public $paymentFrequency;
    public $isFirstInstalmentSameAsPremium = false; // premium mapping
    public $isFirstCollectionSameAsBillingDate = false; // date mapping
    public $isLoadingFields = false;
    public $firstCollectionDate;
    public $firstInstalmentAmount;
    public $billingDate;
    public $premium;
    public $isQuoteStage = false;
    public $isCancellationIssued = false;
    public $blockModalTitle = 'Policy Not Issued';
    public $blockModalMessage = 'The contract cannot be created as the policy is not issued. Please issue the policy first, then try again.';
    public $bankId;
    public $branchId;
    public $accountNumber;
    public $accountType;
    
    // Prevent double submission
    public $isSubmitting = false;

    // Options
    public $banks = [];
    public $branches = [];

    protected function rules()
    {
        $rules = [
            'paymentFrequency' => 'required',
            'premium' => 'required|numeric|min:0',
            'billingDate' => [
                'required',
                function ($attribute, $value, $fail) {
                    if (!$value) {
                        $fail('The billing date is required.');
                        return;
                    }
                    try {
                        $date = Carbon::createFromFormat('d/m/Y', $value)->startOfDay();
                        if ($date->lt(Carbon::today())) {
                            $fail('The billing date cannot be in the past.');
                        }
                    } catch (\Exception $e) {
                        $fail('The billing date must be in dd/mm/yyyy format.');
                    }
                },
            ],
            'bankId' => 'required',
            'branchId' => 'required',
            'accountNumber' => 'required|string',
            'accountType' => 'required',
        ];

        if (!$this->isFirstCollectionSameAsBillingDate) {
            // Require manual first collection details only when not copying from billing
            $rules['firstCollectionDate'] = [
                'required',
                function ($attribute, $value, $fail) {
                    if (!$value) {
                        $fail('The first collection date is required.');
                        return;
                    }
                    try {
                        $date = Carbon::createFromFormat('d/m/Y', $value)->startOfDay();
                        if ($date->lt(Carbon::today())) {
                            $fail('The first collection date cannot be in the past.');
                        }
                    } catch (\Exception $e) {
                        $fail('The first collection date must be in dd/mm/yyyy format.');
                    }
                },
            ];
        }

        if (!$this->isFirstInstalmentSameAsPremium) {
            $rules['firstInstalmentAmount'] = 'required|numeric|min:0';
        }

        return $rules;
    }

    public function mount()
    {
        // Normalize branches to a collection
        $this->branches = collect([]);

        // Load customer data from policy
        if ($this->policy && $this->policy->customer) {
            $customer = $this->policy->customer;
            $profile = $customer->profile;
            
            $this->customerName = trim($customer->firstName . ' ' . ($customer->middleName ?? '') . ' ' . $customer->lastName);
            
            // Get omang and passport from profile
            $omang = $profile->omang ?? null;
            $passport = $profile->passport ?? null;
            
            $idParts = [];
            if ($omang) {
                $idParts[] = 'Omang: ' . $omang;
            }
            if ($passport) {
                $idParts[] = 'Passport: ' . $passport;
            }
            if (empty($idParts)) {
                $idParts[] = 'N/A';
            }
            $this->idTypeAndNumber = implode(' - ', $idParts);
            
            $this->email = $customer->email ?? '';
            $this->cellphone = $customer->cellphone ?? '';
        }

        // For COMG (product_id == 7), use company name in customer name
        if ($this->policy && isset($this->policy->product_id) && (int)$this->policy->product_id === 7) {
            $companyName = $this->policy->profile->company->name
                ?? $this->policy->profile->company_name
                ?? null;
            if ($companyName) {
                $this->customerName = $companyName;
            }
        }

        // Set default payment frequency from policy and keep it read-only in the UI
        $this->paymentFrequency = $this->policy->premium_freq ?? null;

        // Set default billing date from policy (convert to dd/mm/YYYY for UI)
        if (!empty($this->policy->billingStartDate)) {
            $this->billingDate = $this->getNextBillingDateFormatted($this->policy->billingStartDate);
        } else {
            // Fallback: today
            $this->billingDate = Carbon::today()->format('d/m/Y');
        }

        // Determine blocking conditions (quote stage / cancellation issued)
        $this->refreshBlockingState();

        // Load banks
        $this->loadBanks();

        // Load existing banking info if available
        $this->loadExistingBankingInfo();
    }

    public function loadBanks()
    {
        $this->banks = Banks::get(array('id','bank_number','bank_name'));
    }

    public function loadBranches($preserveBranchId = false)
    {
        if ($this->bankId) {
            // Store current branchId to preserve selection if needed
            $currentBranchId = $preserveBranchId ? $this->branchId : null;
            
            $this->branches = BankBranches::where('bank_id', $this->bankId)
                ->select('branch_id', 'name')
                ->get();
            
            // Only restore branchId if we're preserving it and it exists in the new branches list
            if ($preserveBranchId && $currentBranchId) {
                $branchExists = $this->branches->contains(function($branch) use ($currentBranchId) {
                    return (string)$branch->branch_id === (string)$currentBranchId;
                });
                if (!$branchExists) {
                    $this->branchId = null;
                }
            }
        } else {
            $this->branches = collect([]);
            if (!$preserveBranchId) {
                $this->branchId = null;
            }
        }
    }

    public function updatedBankId($value)
    {
        // Reset branchId when bank changes
        $this->branchId = null;
        $this->loadBranches();
    }

    public function updatedBranchId($value)
    {
        // Ensure branchId is stored as string to match option values
        // Don't do anything else that might cause re-renders
        if ($value !== null && $value !== '') {
            $this->branchId = (string)$value;
        } else {
            $this->branchId = null;
        }
        // Don't reload branches or do anything that might reset the value
    }

    public function updatedIsFirstInstalmentSameAsPremium($value)
    {
        // Small delay to ensure loader is visible
        usleep(300000); // 300ms delay
        if ($value) {
            $this->firstInstalmentAmount = $this->premium;
        }
    }

    public function updatedIsFirstCollectionSameAsBillingDate($value)
    {
        usleep(300000); // allow loader to show
        if ($value) {
            $this->firstCollectionDate = $this->billingDate;
        }
    }

    public function loadExistingBankingInfo()
    {
        if ($this->policy) {
            $banking = \AlphaDirect\CustomerBanking::where('policy_id', $this->policy->id)->first();
            if ($banking) {
                $this->bankId = $banking->bankName;
                $this->loadBranches();
                // Ensure branchId is set as string to match option values
                $this->branchId = $banking->branchCode ? (string)$banking->branchCode : null;
                $this->accountNumber = $banking->accountNumber;
                $this->accountType = $banking->accountType ?? '';
            }
        }
    }

    public function submit()
    {
        // Prevent double submission
        if ($this->isSubmitting) {
            return;
        }
        $this->isSubmitting = true;
        
        // If user chose to mirror billing date/premium, copy values before validation
        if ($this->isFirstCollectionSameAsBillingDate) {
            $this->firstCollectionDate = $this->billingDate;
        }
        if ($this->isFirstInstalmentSameAsPremium) {
            $this->firstInstalmentAmount = $this->premium;
        }

        $this->validate();

        // if (!Auth::user()->hasPermissionTo('realpay-contract-create')) {
        //     $this->isSubmitting = false;
        //     $this->dispatchBrowserEvent('alert', ['type' => 'error', 'message' => 'Sorry! You do not have permission to create Realpay contracts.']);
        //     return;
        // }

        try {
            // Step 1: Cancel existing contracts first
            $existingContracts = $this->getExistingContracts();
            if (!empty($existingContracts)) {
                $cancelResult = $this->cancelExistingContracts($existingContracts);
                if ($cancelResult === false) {
                    $this->isSubmitting = false;
                    $this->dispatchBrowserEvent('alert', ['type' => 'error', 'message' => 'Failed to cancel existing contracts. Please try again.']);
                    return;
                }
            }

            // Step 2: Check if client exists, then create or update accordingly
            $clientExists = $this->checkClientExists();
            if ($clientExists === null) {
                $this->isSubmitting = false;
                $this->dispatchBrowserEvent('alert', ['type' => 'error', 'message' => 'Failed to check client existence. Please try again.']);
                return;
            } elseif ($clientExists === false) {
                // Client does not exist, create it
                $clientCreated = $this->createRealpayClient();
                if (!$clientCreated) {
                    $this->isSubmitting = false;
                    $this->dispatchBrowserEvent('alert', ['type' => 'error', 'message' => 'Failed to create Realpay client. Please check the logs for details.']);
                    return;
                }
            } else {
                // Client exists, update it with new banking details
                $clientUpdated = $this->updateRealpayClient();
                if (!$clientUpdated) {
                    $this->isSubmitting = false;
                    $this->dispatchBrowserEvent('alert', ['type' => 'error', 'message' => 'Failed to update Realpay client. Please check the logs for details.']);
                    return;
                }
            }

            // Step 3: Authenticate with Realpay API
            $token = $this->getRealpayAuthToken();
            if (!$token) {
                $this->isSubmitting = false;
                $this->dispatchBrowserEvent('alert', ['type' => 'error', 'message' => 'Failed to authenticate with Realpay API. Please try again.']);
                return;
            }

            // Prepare dates/amounts based on individual toggles
            $billingDate = Carbon::createFromFormat('d/m/Y', $this->billingDate)->format('Y-m-d');
            $firstCollectionDate = $this->isFirstCollectionSameAsBillingDate
                ? $billingDate
                : Carbon::createFromFormat('d/m/Y', $this->firstCollectionDate)->format('Y-m-d');

            $firstCollectionAmount = $this->isFirstInstalmentSameAsPremium
                ? $this->premium
                : $this->firstInstalmentAmount;

            // instalment start/amount align with billing/premium
            $instalmentAmount = $this->premium;
            
            $billingDay = Carbon::createFromFormat('Y-m-d', $billingDate)->format('d');
            // Calculate frequency and installments
            if ($this->paymentFrequency == 1) {
                // Monthly
                $frequency = 'MNTH';
                $numberOfInstallments = '12';
            } elseif ($this->paymentFrequency == 3) {
                // Annual
                $frequency = 'YEAR';
                $numberOfInstallments = '1';
            } elseif ($this->paymentFrequency == 5) {
                // Quarterly
                $frequency = 'QURT';
                $numberOfInstallments = '4';
            } else {
                // Default to monthly
                $frequency = 'MNTH';
                $numberOfInstallments = '12';
            }
            
            // Handle billing day edge cases
            if (($billingDay == 31 || $billingDay == 30 || $billingDay == 29) && $this->paymentFrequency == 1) {
                $billingDay = 99;
            }
            
            // Get contract number
            $contractNumber = RealpayClientContracts::getContractNumber($this->policy->id);
            
            // Create contract via Realpay API
            $response = $this->createRealpayContract($token, [
                'clientNumber' => $this->policy->policyNumber,
                'contractNumber' => $contractNumber,
                'frequency' => $frequency,
                'collectionDay' => $billingDay,
                'firstCollectionDate' => $firstCollectionDate,
                'firstCollectionAmount' => $firstCollectionAmount,
                'instalmentStartDate' => $billingDate,
                'instalmentAmount' => $instalmentAmount,
                'numberOfInstallments' => $numberOfInstallments,
            ]);
            
            if (!$response) {
                $this->isSubmitting = false;
                $this->dispatchBrowserEvent('alert', ['type' => 'error', 'message' => 'Failed to create Realpay contract. Please check the logs for details.']);
                return;
            }
            
            // Parse response
            $responseData = json_decode($response, true);
            
            // Check if contract was created successfully
            if (isset($responseData['ContractPostResponse'][0]['Successful']) && 
                count($responseData['ContractPostResponse'][0]['Successful']) > 0) {
                
                // Start database transaction
                DB::beginTransaction();
                
                try {
                    // Log contract creation
                    $logData = [
                        'policy_id' => $this->policy->id,
                        'client_number' => $this->policy->policyNumber,
                        'contract_number' => $contractNumber,
                        'rate_id' => null,
                        'status' => 1,
                    ];
                    RealpayClientContracts::addLog($logData);
                    
                    // Update or create customer banking details
                    $banking = CustomerBanking::where('policy_id', $this->policy->id)->first();
                    if (!$banking) {
                        $banking = new CustomerBanking();
                        $banking->policy_id = $this->policy->id;
                    }
                    $banking->bankName = $this->bankId;
                    $banking->branchCode = $this->branchId;
                    $banking->accountType = $this->accountType;
                    $banking->billingStartDate = $billingDate;
                    $banking->billing_day = $billingDay;
                    $banking->billing = "RealPay";
                    $banking->accountNumber = $this->accountNumber;
                    $banking->save();
                    
                    // Update policy
                    // $this->policy->premium_freq = $this->paymentFrequency;
                    if ($this->isFirstInstalmentSameAsPremium) {
                        $this->policy->first_premium_wvat = $this->firstInstalmentAmount;
                        $this->policy->premium = $this->firstInstalmentAmount;
                    } else {
                        $this->policy->first_premium_wvat = $this->premium;
                        $this->policy->premium = $this->premium;
                    }
                    $this->policy->billingStartDate = $billingDate;
                    // $this->policy->policyDocument = null;
                    $this->policy->save();
                    
                    // Store contract details and installments
                    $successfulContract = $responseData['ContractPostResponse'][0]['Successful'][0];
                    if (isset($successfulContract['ContractInstalments']) && 
                        count($successfulContract['ContractInstalments']) > 0) {
                        $this->storeContractDetails($successfulContract);
                        $this->storeInstallments($successfulContract);
                    }
                    
                    DB::commit();
                    
                    // Log activity
                    activity('Realpay Contract')
                        ->performedOn($this->policy)
                        ->causedBy(User::where('id', auth()->user()->id)->first())
                        ->log('Realpay Contract Created.');
                    
                    $this->dispatchBrowserEvent('alert', ['type' => 'success', 'message' => 'Realpay contract created successfully.']);
                    
                    // Reset form
                    $this->resetForm();
                    
                    // Emit event to refresh contract lists
                    $this->emit('realpayContractCreated');
                    
                } catch (\Exception $e) {
                    DB::rollBack();
                    Log::error('Realpay Contract Database Update Error: ' . $e->getMessage());
                    $this->isSubmitting = false;
                    $this->dispatchBrowserEvent('alert', ['type' => 'error', 'message' => 'Contract created but failed to update database. Error: ' . $e->getMessage()]);
                    return;
                }
                
            } else {
                // Contract creation failed
                $errorMessage = 'Failed to create Realpay contract.';
                
                // Check for Failed response structure
                if (isset($responseData['ContractPostResponse'][0]['Failed'])) {
                    $failed = $responseData['ContractPostResponse'][0]['Failed'];
                    
                    // Handle array of failures
                    if (is_array($failed) && isset($failed[0])) {
                        if (isset($failed[0]['Failures']) && is_array($failed[0]['Failures'])) {
                            $failures = $failed[0]['Failures'];
                            if (isset($failures[0]['Message'])) {
                                $errorMessage .= ' ' . $failures[0]['Message'];
                            } elseif (isset($failures[0]['Description'])) {
                                $errorMessage .= ' ' . $failures[0]['Description'];
                            }
                        } elseif (isset($failed[0]['Message'])) {
                            $errorMessage .= ' ' . $failed[0]['Message'];
                        } elseif (isset($failed[0]['Description'])) {
                            $errorMessage .= ' ' . $failed[0]['Description'];
                        }
                    }
                }
                
                // Also check for error messages in other possible locations
                if (isset($responseData['message'])) {
                    $errorMessage .= ' ' . $responseData['message'];
                }
                if (isset($responseData['error'])) {
                    $errorMessage .= ' ' . $responseData['error'];
                }
                
                Log::error('Realpay Contract Creation Failed: ' . json_encode($responseData));
                Log::error('Full Response: ' . $response);
                $this->isSubmitting = false;
                $this->dispatchBrowserEvent('alert', ['type' => 'error', 'message' => $errorMessage]);
                return;
            }
            
        } catch (\Exception $e) {
            Log::error('Realpay Contract Creation Error: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            $this->isSubmitting = false;
            $this->dispatchBrowserEvent('alert', ['type' => 'error', 'message' => 'Error: ' . $e->getMessage()]);
        } finally {
            $this->isSubmitting = false;
        }
    }

    /**
     * Get Realpay authentication token
     */
    private function getRealpayAuthToken()
    {
        try {
            $curl = curl_init();
            
            curl_setopt_array($curl, array(
                CURLOPT_URL => config('realpay.base_url') . '/oauth/token?grant_type=client_credentials',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_HTTPHEADER => array(
                    'Authorization: Basic ' . config('realpay.client_auth'),
                ),
            ));
            
            $response = curl_exec($curl);
            $data = json_decode($response, true);
            curl_close($curl);
            
            if (isset($data['token_type']) && isset($data['access_token'])) {
                return $data['token_type'] . ' ' . $data['access_token'];
            }
            
            return null;
        } catch (\Exception $e) {
            Log::error('Realpay Auth Error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Create Realpay contract via API
     */
    private function createRealpayContract($token, $contractData)
    {
        try {
            // Determine product URL and tracking code based on bank (FNB = 12)
            $product = ($this->bankId == 12) ? config('realpay.fnb_product') : config('realpay.product');
            $trackingCode = ($this->bankId == 12) ? "B3" : "44";
            $url = config('realpay.base_url') . "/maintain/contracts/" . $product . 
                   "?BeneficiaryUser=" . config('realpay.merchant') . "&Version=" . config('realpay.version');
            
            $postData = json_encode([
                "ContractPostRequest" => [
                    [
                        "ClientNumber" => $contractData['clientNumber'],
                        "ContractNumber" => $contractData['contractNumber'],
                        "FrequencyCode" => $contractData['frequency'],
                        "CollectionDay" => $contractData['collectionDay'],
                        "TrackingCode" => $trackingCode,
                        "FirstCollectionDate" => $contractData['firstCollectionDate'],
                        "FirstCollectionAmount" => (string)$contractData['firstCollectionAmount'],
                        "InstalmentStartDate" => $contractData['instalmentStartDate'],
                        "InstalmentAmount" => $contractData['instalmentAmount'],
                        "NumberOfInstalments" => $contractData['numberOfInstallments'],
                        "CTCPercentage" => 1
                    ]
                ]
            ]);
            
            // Log the request for debugging
            Log::info('Realpay Contract Creation Request', [
                'url' => $url,
                'bankId' => $this->bankId,
                'product' => $product,
                'trackingCode' => $trackingCode,
                'postData' => $postData
            ]);
            
            $curl = curl_init();
            
            curl_setopt_array($curl, array(
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS => $postData,
                CURLOPT_HTTPHEADER => array(
                    "Content-Type: application/json",
                    "Accept: application/json",
                    "Authorization: " . $token
                ),
            ));
            
            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $curlError = curl_error($curl);
            curl_close($curl);
            
            // Log curl errors if any
            if ($curlError) {
                Log::error('Realpay Contract cURL Error: ' . $curlError);
                return null;
            }
            
            // Check HTTP status code
            if ($httpCode >= 200 && $httpCode < 300) {
                // Even with 200 status, check if response indicates failure
                $responseData = json_decode($response, true);
                if (isset($responseData['ContractPostResponse'][0]['Failed']) && 
                    !empty($responseData['ContractPostResponse'][0]['Failed'])) {
                    // API returned 200 but contract creation failed
                    Log::error('Realpay Contract Creation Failed (HTTP 200): ' . json_encode($responseData));
                    return $response; // Return response so we can parse the error message
                }
                return $response;
            }
            
            Log::error('Realpay Contract API Error - HTTP Code: ' . $httpCode . ', Response: ' . $response);
            return null;
            
        } catch (\Exception $e) {
            Log::error('Realpay Contract API Exception: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Store contract details in database
     */
    private function storeContractDetails($data)
    {
        try {
            $saveData = new RealpayContractDetails();
            $saveData->ContractSequence = $data['ContractSequence'] ?? null;
            $saveData->ClientNumber = $data['ClientNumber'];
            $saveData->ContractNumber = $data['ContractNumber'];
            $saveData->CTCPercentage = $data['CTCPercentage'] ?? 1;
            $saveData->InstalmentStartDate = $data['InstalmentStartDate'];
            $saveData->TrackingCode = $data['TrackingCode'] ?? '44';
            $saveData->FrequencyCode = $data['FrequencyCode'];
            $saveData->CollectionDay = $data['CollectionDay'];
            $saveData->NumberOfInstalments = $data['NumberOfInstalments'];
            $saveData->save();
            
            return true;
        } catch (\Exception $e) {
            Log::error('Store Contract Details Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Store contract installments in database
     */
    private function storeInstallments($data)
    {
        try {
            if (isset($data['ContractInstalments']) && is_array($data['ContractInstalments'])) {
                foreach ($data['ContractInstalments'] as $installment) {
                    $new = new RealpayContractInstallments();
                    $new->clientNumber = $data['ClientNumber'];
                    $new->contractNumber = $data['ContractNumber'];
                    $new->InstalmentReferenceNumber = $installment['InstalmentReferenceNumber'] ?? null;
                    $new->InstalmentSequence = $installment['InstalmentSequence'] ?? null;
                    $new->CTCAmount = $installment['CTCAmount'] ?? 0;
                    $new->InstalmentActionDate = $installment['InstalmentActionDate'] ?? null;
                    $new->TrackingCode = $installment['TrackingCode'] ?? '44';
                    $new->InstalmentAmount = $installment['InstalmentAmount'] ?? 0;
                    $new->InstalmentStatus = $installment['InstalmentStatus'] ?? 'A';
                    $new->save();
                }
                return true;
            }
            return false;
        } catch (\Exception $e) {
            Log::error('Store Installments Error: ' . $e->getMessage());
            return false;
        }
    }

    public function resetForm()
    {
        $this->paymentFrequency = $this->policy->premium_freq ?? null;
        $this->isFirstInstalmentSameAsPremium = false;
        $this->isFirstCollectionSameAsBillingDate = false;
        $this->firstCollectionDate = null;
        $this->firstInstalmentAmount = null;
        if (!empty($this->policy->billingStartDate)) {
            $this->billingDate = $this->getNextBillingDateFormatted($this->policy->billingStartDate);
        } else {
            $this->billingDate = Carbon::today()->format('d/m/Y');
        }
        $this->premium = null;
        $this->bankId = null;
        $this->branchId = null;
        $this->accountNumber = null;
        $this->accountType = null;
        $this->branches = collect([]);
    }

    public function hydrate()
    {
        // On every hydration, ensure branches are available for the selected bank
        if ($this->bankId) {
            $this->loadBranches(true);
        }

        // If mirroring billing/premium, ensure the values stay in sync on re-render
        if ($this->isFirstCollectionSameAsBillingDate) {
            $this->firstCollectionDate = $this->billingDate;
        }
        if ($this->isFirstInstalmentSameAsPremium) {
            $this->firstInstalmentAmount = $this->premium;
        }

        // Refresh blocking state and show warning popup if needed
        $this->refreshBlockingState();
        if ($this->isQuoteStage || $this->isCancellationIssued) {
            $this->dispatchBrowserEvent('quote-policy-warning');
        }
    }

    private function parseDateFlexible($date)
    {
        if (!$date) {
            return null;
        }

        $date = trim($date);

        // Handle ranges like "03/12/2024 - 02/12/2025" by taking the first part
        if (preg_match('/\s+-\s+/', $date)) {
            $parts = preg_split('/\s+-\s+/', $date);
            $date = $parts[0] ?? $date;
        }

        $formats = [
            'd/m/Y',
            'd-m-Y',
            'Y-m-d',
            'Y/m/d',
            'm/d/Y',
            'd.m.Y',
        ];

        foreach ($formats as $fmt) {
            try {
                return Carbon::createFromFormat($fmt, $date);
            } catch (\Exception $e) {
                // continue
            }
        }

        try {
            return Carbon::parse($date);
        } catch (\Exception $e) {
            return null;
        }
    }

    private function getNextBillingDateFormatted($date)
    {
        try {
            $parsed = $this->parseDateFlexible($date);
            $today = Carbon::today();

            // If we couldn't parse, use today
            if (!$parsed) {
                return $today->format('d/m/Y');
            }

            // Move to next month keeping the day where possible
            $next = $parsed->copy()->addMonthNoOverflow()->startOfDay();

            // If next date is still in the past, build a future date using:
            // - day from billingStartDate
            // - month/year from (today + 1 month)
            if ($next->lt($today)) {
                $futureBase = $today->copy()->addMonthNoOverflow();
                $day = min($parsed->day, $futureBase->daysInMonth);
                $futureBase->day($day)->startOfDay();

                // Safety: if still in the past (edge cases), fallback to today
                if ($futureBase->lt($today)) {
                    $futureBase = $today;
                }

                return $futureBase->format('d/m/Y');
            }

            return $next->format('d/m/Y');
        } catch (\Exception $e) {
            return Carbon::today()->format('d/m/Y');
        }
    }

    public function cancel()
    {
        $this->resetForm();
    }

    /**
     * Check if client exists in Realpay
     */
    private function checkClientExists()
    {
        try {
            $token = $this->getRealpayAuthToken();
            if (!$token) {
                return null;
            }

            // Check customer banking to determine product URL
            $customerBanking = CustomerBanking::where('policy_id', $this->policy->id)
                ->orderBy('id', 'DESC')
                ->first();

            if (!$customerBanking) {
                $customerBanking = CustomerBanking::where('customer_id', $this->policy->customer_id)
                    ->orderBy('id', 'DESC')
                    ->first();
            }

            // Determine URL based on bank (FNB = 12) or use form bankId
            $bankId = $this->bankId ?? ($customerBanking ? $customerBanking->bankName : null);
            $url = '';
            if ($bankId == 12) {
                $url = config('realpay.base_url') . '/maintain/clients/' . config('realpay.fnb_product') . 
                       "?ClientNumber=" . $this->policy->policyNumber . 
                       "&BeneficiaryUser=" . config('realpay.merchant') . 
                       "&Version=" . config('realpay.version');
            } else {
                $url = config('realpay.base_url') . '/maintain/clients/' . config('realpay.product') . 
                       "?ClientNumber=" . $this->policy->policyNumber . 
                       "&BeneficiaryUser=" . config('realpay.merchant') . 
                       "&Version=" . config('realpay.version');
            }

            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "GET",
                CURLOPT_HTTPHEADER => array(
                    "Content-Type: application/json",
                    "Accept: application/json",
                    "Authorization: " . $token
                ),
            ));

            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);

            if ($httpCode >= 200 && $httpCode < 300) {
                $data = json_decode($response, true);
                if (empty($data['ClientGetResponse'])) {
                    return false; // Client does not exist
                } else {
                    return true; // Client exists
                }
            }

            Log::error('Check Client Exists API Error - HTTP Code: ' . $httpCode . ', Response: ' . $response);
            return null;

        } catch (\Exception $e) {
            Log::error('Check Client Exists Error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Create Realpay client
     */
    private function createRealpayClient()
    {
        try {
            $token = $this->getRealpayAuthToken();
            if (!$token) {
                return false;
            }

            // Get customer and profile
            $customer = $this->policy->customer;
            if (!$customer) {
                Log::error('Customer not found for policy: ' . $this->policy->id);
                return false;
            }

            $profile = $customer->profile;
            if (!$profile) {
                $profile = CustomerProfile::where('customer_id', $customer->id)->first();
            }

            if (!$profile) {
                Log::error('Customer profile not found for customer: ' . $customer->id);
                return false;
            }

            // Determine ID type and number
            if ($profile->omang != null) {
                $id = $profile->omang;
                $idType = 'I';
            } else {
                $id = $profile->passport;
                $idType = 'P';
            }

            if (!$id) {
                Log::error('No ID (Omang or Passport) found for customer: ' . $customer->id);
                return false;
            }

            // Determine URL based on bank (FNB = 12)
            $url = '';
            if ($this->bankId == 12) {
                $url = config('realpay.base_url') . "/maintain/clients/" . config('realpay.fnb_product') . 
                       "?BeneficiaryUser=" . config('realpay.merchant') . 
                       "&Version=" . config('realpay.version');
            } else {
                $url = config('realpay.base_url') . "/maintain/clients/" . config('realpay.product') . 
                       "?BeneficiaryUser=" . config('realpay.merchant') . 
                       "&Version=" . config('realpay.version');
            }

            // Prepare client data
            $clientName = trim($customer->firstName . ' ' . ($customer->middleName ?? '') . ' ' . $customer->lastName);
            
            $postData = json_encode([
                "ClientPostRequest" => [
                    [
                        "ClientNumber" => $this->policy->policyNumber,
                        "ClientName" => $clientName,
                        "IDType" => $idType ?? null,
                        "IDNumber" => $id ?? null,
                        "CellphoneNumber" => $customer->cellphone ?? null,
                        "EMail" => $customer->email ?? null,
                        "BankCode" => (string)$this->bankId,
                        "BranchCode" => (string)$this->branchId,
                        "AccountType" => (string)$this->accountType,
                        "AccountNumber" => $this->accountNumber,
                        "AccountHolderName" => $clientName,
                        "EmployeeGroupCode" => "OT"
                    ]
                ]
            ]);

            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS => $postData,
                CURLOPT_HTTPHEADER => array(
                    "Content-Type: application/json",
                    "Accept: application/json",
                    "Authorization: " . $token
                ),
            ));

            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);

            if ($httpCode >= 200 && $httpCode < 300) {
                $data = json_decode($response, true);
                
                // Check if client was created successfully
                if (isset($data['ClientPostResponse'][0]['Successful']) && 
                    count($data['ClientPostResponse'][0]['Successful']) > 0) {
                    return true;
                } else {
                    Log::error('Create Client Failed: ' . json_encode($data));
                    return false;
                }
            }

            Log::error('Create Client API Error - HTTP Code: ' . $httpCode . ', Response: ' . $response);
            return false;

        } catch (\Exception $e) {
            Log::error('Create Realpay Client Error: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return false;
        }
    }

    /**
     * Update Realpay client details
     */
    private function updateRealpayClient()
    {
        try {
            $token = $this->getRealpayAuthToken();
            if (!$token) {
                return false;
            }

            // Get customer and profile
            $customer = $this->policy->customer;
            if (!$customer) {
                Log::error('Customer not found for policy: ' . $this->policy->id);
                return false;
            }

            $profile = $customer->profile;
            if (!$profile) {
                $profile = CustomerProfile::where('customer_id', $customer->id)->first();
            }

            if (!$profile) {
                Log::error('Customer profile not found for customer: ' . $customer->id);
                return false;
            }

            // Determine ID type and number
            if ($profile->omang != null) {
                $id = $profile->omang;
                $idType = 'I';
            } else {
                $id = $profile->passport;
                $idType = 'P';
            }

            if (!$id) {
                Log::error('No ID (Omang or Passport) found for customer: ' . $customer->id);
                return false;
            }

            // Determine URL based on bank (FNB = 12)
            $product = ($this->bankId == 12) ? config('realpay.fnb_product') : config('realpay.product');
            $url = config('realpay.base_url') . "/maintain/clients/" . $product . 
                   "?BeneficiaryUser=" . config('realpay.merchant') . 
                   "&Version=" . config('realpay.version');

            // Prepare client data
            $clientName = trim($customer->firstName . ' ' . ($customer->middleName ?? '') . ' ' . $customer->lastName);
            
            $postData = json_encode([
                "ClientPutRequest" => [
                    [
                        "ClientNumber" => $this->policy->policyNumber,
                        "ClientName" => $clientName,
                        "IDType" => $idType,
                        "IDNumber" => $id,
                        "CellphoneNumber" => $customer->cellphone ?? '',
                        "EMail" => $customer->email ?? '',
                        "BankCode" => (string)$this->bankId,
                        "BranchCode" => (string)$this->branchId,
                        "AccountType" => (string)$this->accountType,
                        "AccountNumber" => $this->accountNumber,
                        "AccountHolderName" => $clientName,
                        "EmployeeGroupCode" => "OT"
                    ]
                ]
            ]);

            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "PUT",
                CURLOPT_POSTFIELDS => $postData,
                CURLOPT_HTTPHEADER => array(
                    "Content-Type: application/json",
                    "Accept: application/json",
                    "Authorization: " . $token
                ),
            ));

            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);

            if ($httpCode >= 200 && $httpCode < 300) {
                $data = json_decode($response, true);
                
                // Check if client was updated successfully
                if (isset($data['ClientPutResponse'][0]['Successful']) && 
                    count($data['ClientPutResponse'][0]['Successful']) > 0) {
                    return true;
                } else {
                    Log::error('Update Client Failed: ' . json_encode($data));
                    return false;
                }
            }

            Log::error('Update Client API Error - HTTP Code: ' . $httpCode . ', Response: ' . $response);
            return false;

        } catch (\Exception $e) {
            Log::error('Update Realpay Client Error: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return false;
        }
    }

    /**
     * Get existing contracts for this policy
     */
    private function getExistingContracts()
    {
        try {
            // Get contracts from database
            $contracts = RealpayClientContracts::where('policy_id', $this->policy->id)
                ->where('status', 1) // Only active contracts
                ->orderBy('id', 'desc')
                ->get();

            // Also check via API to get all contracts
            $token = $this->getRealpayAuthToken();
            if ($token) {
                $apiContracts = $this->getContractsFromAPI($token);
                if ($apiContracts) {
                    // Merge API contracts with database contracts
                    foreach ($apiContracts as $apiContract) {
                        $exists = $contracts->contains(function($contract) use ($apiContract) {
                            return $contract->contract_number == $apiContract['ContractNumber'];
                        });
                        if (!$exists) {
                            // Add to collection as a new object
                            $newContract = new \stdClass();
                            $newContract->contract_number = $apiContract['ContractNumber'];
                            $newContract->client_number = $apiContract['ClientNumber'] ?? $this->policy->policyNumber;
                            $contracts->push($newContract);
                        }
                    }
                }
            }

            return $contracts;
        } catch (\Exception $e) {
            Log::error('Get Existing Contracts Error: ' . $e->getMessage());
            return collect([]);
        }
    }

    /**
     * Get contracts from Realpay API
     */
    private function getContractsFromAPI($token)
    {
        try {
            // Determine URL based on bank
            $customerBanking = CustomerBanking::where('policy_id', $this->policy->id)
                ->orderBy('id', 'DESC')
                ->first();

            if (!$customerBanking) {
                $customerBanking = CustomerBanking::where('customer_id', $this->policy->customer_id)
                    ->orderBy('id', 'DESC')
                    ->first();
            }

            $bankId = $this->bankId ?? ($customerBanking ? $customerBanking->bankName : null);
            $url = '';
            if ($bankId == 12) {
                $url = config('realpay.base_url') . "/maintain/contracts/" . config('realpay.fnb_product') . 
                       "?ClientNumber=" . $this->policy->policyNumber . 
                       "&BeneficiaryUser=" . config('realpay.merchant') . 
                       "&Version=" . config('realpay.version');
            } else {
                $url = config('realpay.base_url') . "/maintain/contracts/" . config('realpay.product') . 
                       "?ClientNumber=" . $this->policy->policyNumber . 
                       "&BeneficiaryUser=" . config('realpay.merchant') . 
                       "&Version=" . config('realpay.version');
            }

            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "GET",
                CURLOPT_HTTPHEADER => array(
                    "Content-Type: application/json",
                    "Accept: application/json",
                    "Authorization: " . $token
                ),
            ));

            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);

            if ($httpCode >= 200 && $httpCode < 300) {
                $data = json_decode($response, true);
                if (isset($data['ContractGetResponse']) && !empty($data['ContractGetResponse'])) {
                    return $data['ContractGetResponse'];
                }
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Get Contracts From API Error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Cancel existing contracts
     */
    private function cancelExistingContracts($contracts)
    {
        try {
            $token = $this->getRealpayAuthToken();
            if (!$token) {
                return false;
            }

            // Determine URL base based on bank from customer_banking table (existing contract's bank)
            // Use the bank from existing contract, not the new request bankId
            $customerBanking = CustomerBanking::where('policy_id', $this->policy->id)
                ->orderBy('id', 'DESC')
                ->first();

            if (!$customerBanking) {
                $customerBanking = CustomerBanking::where('customer_id', $this->policy->customer_id)
                    ->orderBy('id', 'DESC')
                    ->first();
            }

            // Use bankName from customer_banking table for cancelling existing contracts
            $bankId = $customerBanking ? $customerBanking->bankName : null;
            $successCount = 0;
            $failedCount = 0;

            foreach ($contracts as $contract) {
                $contractNumber = $contract->contract_number ?? $contract['ContractNumber'] ?? null;
                if (!$contractNumber) {
                    continue;
                }
                // Build URL
                $url = '';
                if ($bankId == 12) {
                    $url = config('realpay.base_url') . "/maintain/contracts/" . config('realpay.fnb_product') . 
                           "?ClientNumber=" . $this->policy->policyNumber . 
                           "&ContractNumber=" . $contractNumber . 
                           "&BeneficiaryUser=" . config('realpay.merchant') . 
                           "&Version=" . config('realpay.version');
                } else {
                    $url = config('realpay.base_url') . "/maintain/contracts/" . config('realpay.product') . 
                           "?ClientNumber=" . $this->policy->policyNumber . 
                           "&ContractNumber=" . $contractNumber . 
                           "&BeneficiaryUser=" . config('realpay.merchant') . 
                           "&Version=" . config('realpay.version');
                }

                $curl = curl_init();
                curl_setopt_array($curl, array(
                    CURLOPT_URL => $url,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => "",
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 0,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => "DELETE",
                    CURLOPT_POSTFIELDS => "{}", // Empty JSON body required by API
                    CURLOPT_HTTPHEADER => array(
                        "Content-Type: application/json",
                        "Accept: application/json",
                        "Authorization: " . $token
                    ),
                ));

                $response = curl_exec($curl);
                $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
                curl_close($curl);
                if ($httpCode >= 200 && $httpCode < 300) {
                    $data = json_decode($response, true);
                    if (isset($data['ContractDeleteResponse'][0]['Successful']) && 
                        count($data['ContractDeleteResponse'][0]['Successful']) > 0) {
                        $successCount++;
                        
                        // Update contract status in database
                        if (isset($contract->id)) {
                            RealpayClientContracts::where('id', $contract->id)
                                ->update(['status' => 0]);
                        } else {
                            RealpayClientContracts::where('contract_number', $contractNumber)
                                ->where('policy_id', $this->policy->id)
                                ->update(['status' => 0]);
                        }
                    } else {
                        $failedCount++;
                        Log::warning('Failed to cancel contract: ' . $contractNumber . ' - Response: ' . json_encode($data));
                    }
                } else {
                    $failedCount++;
                    Log::warning('Failed to cancel contract: ' . $contractNumber . ' - HTTP Code: ' . $httpCode);
                }
            }

            // Log activity
            if ($successCount > 0) {
                activity('Realpay Contract')
                    ->performedOn($this->policy)
                    ->causedBy(User::where('id', auth()->user()->id)->first())
                    ->log('Cancelled ' . $successCount . ' existing Realpay contract(s) before creating new one.');
            }

            // Return true if at least one was cancelled successfully, or if none existed
            return $successCount > 0 || $failedCount == 0;

        } catch (\Exception $e) {
            Log::error('Cancel Existing Contracts Error: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return false;
        }
    }

    public function render()
    {
        // Re-check blocking conditions on every render to ensure they're current
        $this->refreshBlockingState();

        // Ensure branches exist if a bank is already selected; preserve selection
        if ($this->bankId && (empty($this->branches) || (method_exists($this->branches, 'count') && $this->branches->count() === 0))) {
            $this->loadBranches(true);
        }

        // If blocked (quote stage or cancellation issued), disable form and trigger modal
        if ($this->isQuoteStage || $this->isCancellationIssued) {
            $this->dispatchBrowserEvent('quote-policy-warning');
            return view('v2.livewire.policy.realpay.add-realpay-contract', [
                'disableForm' => true,
                'showOnlyModal' => true,
                'modalTitle' => $this->blockModalTitle,
                'modalMessage' => $this->blockModalMessage,
            ]);
        }

        return view('v2.livewire.policy.realpay.add-realpay-contract', [
            'disableForm' => false,
            'showOnlyModal' => false,
            'modalTitle' => $this->blockModalTitle,
            'modalMessage' => $this->blockModalMessage,
        ]);
    }

    /**
     * Refreshes flags and modal text for cases where the form must be blocked.
     */
    private function refreshBlockingState(): void
    {
        // Default state
        $this->blockModalTitle = 'Policy Not Issued';
        $this->blockModalMessage = 'The contract cannot be created as the policy is not issued. Please issue the policy first, then try again.';

        // Check quote stage
        try {
            $this->isQuoteStage = DB::table('policy_actions')
                ->where('policy_id', $this->policy->id)
                ->whereIn('status', ['QUOTE', 'LAPSED'])
                ->orderBy('id', 'DESC')
                ->exists();
        } catch (\Exception $e) {
            $this->isQuoteStage = false;
        }

        // Check cancellation with issued status
        try {
            $this->isCancellationIssued = DB::table('policy_actions')
                ->where('policy_id', $this->policy->id)
                ->where('transaction_type', 'CANCEL')
                // ->where('status', 'ISSUED')
                ->orderBy('id','DESC')
                ->exists();
        } catch (\Exception $e) {
            $this->isCancellationIssued = false;
        }

        // Update modal messaging based on priority: cancellation > quote
        if ($this->isCancellationIssued) {
            $this->blockModalTitle = 'Policy Cancellation';
            $this->blockModalMessage = 'The contract cannot be created because this policy is already cancelled (status: ISSUED).';
        } elseif ($this->isQuoteStage) {
            $this->blockModalTitle = 'Policy Not Issued';
            $this->blockModalMessage = 'The contract cannot be created as the policy is not issued. Please issue the policy first, then try again.';
        }
    }
}

