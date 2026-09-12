<?php

namespace AlphaDirect\Http\Controllers\Api;

use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Http\Controllers\Payment\VCS\PaymentController;
use AlphaDirect\Product;
use AlphaDirect\Productplan;
use AlphaDirect\PolicyBundled;
use AlphaDirect\Config;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;
use AlphaDirect\Customer;
use AlphaDirect\CustomerProfile;
use AlphaDirect\Policy;
use AlphaDirect\PolicyMember;
use AlphaDirect\PolicyBeneficiary;
use AlphaDirect\KYC;
use AlphaDirect\Models\CompanyName;
use AlphaDirect\HospitalCashbackCoapplicants;
use AlphaDirect\PolicyCellPhone;
use AlphaDirect\DeviceMakeModel;
use AlphaDirect\Repositories\PolicyCellPhone\PolicyCellPhoneInterface;
use AlphaDirect\Vehicle;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use AlphaDirect\AccidentDriver;
use AlphaDirect\KycCompliance;
use AlphaDirect\Activation;
use AlphaDirect\ADGroupedBeneficiary;
use AlphaDirect\Billing;
use AlphaDirect\City;
use AlphaDirect\EmailBroadcasting;
use AlphaDirect\Claim;
use AlphaDirect\ClaimAccident;
use AlphaDirect\ClaimAccidentPassenger;
use AlphaDirect\ClaimLife;
use AlphaDirect\ClaimThirdParty;
use AlphaDirect\ClaimVehicle;
use AlphaDirect\CustomerBanking;
use AlphaDirect\CustomerGeneratedActivationCode;
use AlphaDirect\FactorMain;
use AlphaDirect\Http\Controllers\Admin\PolicyController;
use AlphaDirect\Http\Controllers\FrontendPay\CustomerController;
use AlphaDirect\PolicyCoveredPerson;
use AlphaDirect\State;
use AlphaDirect\Stores;
use AlphaDirect\Master;
use AlphaDirect\PolicyLead;
use AlphaDirect\FactorSubType;
use AlphaDirect\Http\Controllers\Payment\OrangeMoney\OrangeMoneyController;
use AlphaDirect\Http\Controllers\Payment\VCS\VcsController;
use AlphaDirect\Http\Controllers\Payment\Flutterwave\FlutterwaveController;
use AlphaDirect\Http\Controllers\SmsMessaging;
use AlphaDirect\Lookup;
use AlphaDirect\Country;
use AlphaDirect\OTP;
use AlphaDirect\Models\PolicyCoverage;
use AlphaDirect\PolicyFactor;
use AlphaDirect\Models\ProductCoverage;
use AlphaDirect\Transaction;
use AlphaDirect\VcsTransaction;
use AlphaDirect\VcsNewTransaction;
use AlphaDirect\Region;
use AlphaDirect\VehicleMake;
use AlphaDirect\PolicyTyreRim;
use AlphaDirect\CustomerConsent;
use AlphaDirect\CustomerMati;
use AlphaDirect\PaymentTransaction;
use AlphaDirect\Helper;
use Illuminate\Support\Facades\Auth;
use Crypt;
use File;
use Hash;
use Http\Client\Exception;
use Illuminate\Support\Facades\Mail;
use Pnlinh\InfobipSms\Facades\InfobipSms;
use Redirect;
use DB;
use AlphaDirect\Repositories\Customer\CustomerInterface;
use AlphaDirect\Repositories\CustomerBanking\CustomerBankingInterface;
use AlphaDirect\Repositories\CustomerKyc\CustomerKycInterface;
use AlphaDirect\Repositories\CustomerProfile\CustomerProfileInterface;
use AlphaDirect\Events\CommissionPolicyEvent;
use AlphaDirect\Events\DecrementCounterIfPolicySellsEvent as DecrementCounter;
use AlphaDirect\Http\Controllers\Admin\CustomerController as AdminCustomerController;
use AlphaDirect\Http\Controllers\Admin\DocumentController;
use AlphaDirect\Http\Controllers\DpoPaymentController;
use AlphaDirect\UserPassword;
use Illuminate\Support\Str;
use AlphaDirect\Mail\MailTemplate;
use AlphaDirect\User;
use AlphaDirect\Events\ScheduleTransactionEvent;
use AlphaDirect\MobileAppErrorLog;
use AlphaDirect\Http\Controllers\NgeniusPaymentController;
use AlphaDirect\PolicyTerm;
use AlphaDirect\RealpayPaymentRequest;
use AlphaDirect\Models\PaymentEmail;
use AlphaDirect\Http\Controllers\LlmApiCrontroller;
use AlphaDirect\MotorComprehensiveQuotes;
use AlphaDirect\QuoteSettings;
use AlphaDirect\Http\Controllers\PayM8Controller;
use AlphaDirect\Jobs\UpdateRealpayInstallmentJob;
use AlphaDirect\RealpayClientContracts;
use AlphaDirect\RealpayContractDetails;
use AlphaDirect\RealpayContractInstallments;
use AlphaDirect\ScheduleTransaction;

class BundleProductController extends Controller
{
    protected $policy_cell_phone_interface;

    public function __construct(PolicyCellPhoneInterface $policy_cell_phone_interface)
    {
        $this->policy_cell_phone_interface = $policy_cell_phone_interface;
    }
    /**
     * Get all bundle products with their plans and features
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getProducts(Request $request): JsonResponse
    {
        try {
            // Get active products with their plans
            $products = Product::with(['plans' => function($query) {
                $query->where('status', 1); // Only active plans
            }])
            ->where('status', 1)->whereIn('id',[1,2,4,5,9])
            // Only active products
            ->get();

            $formattedProducts = $products->map(function($product) {
                return [
                    'product_id' => $product->id,
                    'product_name' => $product->name ?? 'N/A',
                    'product_code' => $product->code ?? 'N/A',
                    'product_image' => $product->image ? \AlphaDirect\Helper::getCloudFrontURL($product->image) : null,
                    'product_description' => $product->description ?? 'N/A',
                    'product_type' => $product->type->name ?? 'N/A',
                    'is_active' => (bool) $product->status,
                    'has_vehicle' => (bool) $product->has_vehicle,
                    'has_member' => (bool) $product->has_member,
                    'min_age' => $product->min_age ?? 18,
                    'max_age' => $product->max_age ?? 65,
                    'plans' => $product->plans->map(function($plan) use ($product) {
                        // Calculate premium based on product type
                        $premium = $this->calculatePremiumForPlan($product, $plan);
                        
                        return [
                            'plan_id' => $plan->id,
                            'plan_name' => $plan->name ?? 'N/A',
                            'plan_image' =>$product->image ? \AlphaDirect\Helper::getCloudFrontURL($product->image) : null,
                            'plan_code' => $plan->code ?? 'N/A',
                            'plan_description' => $plan->description ?? 'N/A',
                            'premium' => $premium,
                            'currency' => $plan->currency ?? 'BWP',
                            'billing_frequency' => $plan->billing_frequency ?? 'monthly',
                            'coverage_amount' => (float) ($plan->coverage_amount ?? 0.00),
                            'deductible' => (float) ($plan->deductible ?? 0.00),
                            'is_active' => (bool) $plan->status,
                            'features' => $this->getPlanFeatures($plan)
                        ];
                    })
                ];
            });

            // Calculate metadata
            $totalProducts = $products->count();
            $totalPlans = $products->sum(function($product) {
                return $product->plans->count();
            });

            // Get unique billing frequencies from plans
            $billingFrequencies = $products->flatMap(function($product) {
                return $product->plans->pluck('billing_frequency')->filter();
            })->unique()->values()->toArray();

            $response = [
                'success' => true,
                'message' => 'Products retrieved successfully',
                'data' => [
                    'products' => $formattedProducts,
                    'metadata' => [
                        'total_products' => $totalProducts,
                        'total_plans' => $totalPlans,
                        'currency' => 'BWP',
                        'billing_frequencies' => $billingFrequencies,
                        'last_updated' => Carbon::now()->toISOString()
                    ]
                ]
            ];

            return response()->json($response, 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving products: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * Get features for a specific plan
     * 
     * @param Productplan $plan
     * @return array
     */
    private function getPlanFeatures(Productplan $plan): array
    {
        // Default features based on plan type
        $defaultFeatures = [
            'Basic coverage',
            'Standard benefits',
            'Customer support'
        ];

        // You can extend this to pull from a features table or configuration
        // For now, returning default features
        return $defaultFeatures;
    }

    /**
     * Calculate premium for a plan based on product type
     * 
     * @param Product $product
     * @param Productplan $plan
     * @return mixed
     */
    private function calculatePremiumForPlan(Product $product, Productplan $plan)
    {
        $premiumPayment = new PaymentController();
        
        if($product->type == Product::PRODUCT_TYPE_HEALTH)
        {
            $productPlan = Productplan::where('id', $plan->id)->first();
            $premiumMeta = json_decode($productPlan->premiumAndRelation, true);
            $premium = $premiumPayment->getHibPremiumByProductPlan([], $productPlan, $premiumMeta);
        } elseif ($product->id == 9) {
            // For product ID 9, always use hospital cashback premium calculation
            $premium = $premiumPayment->getHospitalCashbackPremiumByProductPlan($plan->id);
        }
        else
        {
            $premium = $premiumPayment->getPremiumByProductPlan($plan->id);
        }

        return $premium;
    }

    // /**
    //  * Get a specific bundle product by ID
    //  * 
    //  * @param Request $request
    //  * @param int $id
    //  * @return JsonResponse
    //  */
    // public function getProductById(Request $request, int $id): JsonResponse
    // {
    //     try {
    //         $product = Product::with(['plans' => function($query) {
    //             $query->where('status', 1);
    //         }])
    //         ->where('id', $id)
    //         ->where('status', 1)
    //         ->whereNotIn('id',[3,7,8])
    //         ->first();

    //         if (!$product) {
    //             return response()->json([
    //                 'success' => false,
    //                 'message' => 'Product not found',
    //                 'data' => null
    //             ], 404);
    //         }

    //         $formattedProduct = [
    //             'product_id' => $product->id,
    //             'product_name' => $product->name ?? 'N/A',
    //             'product_code' => $product->code ?? 'N/A',
    //             'product_description' => $product->description ?? 'N/A',
    //             'product_type' => $product->type->name ?? 'N/A',
    //             'is_active' => (bool) $product->status,
    //             'has_vehicle' => (bool) $product->has_vehicle,
    //             'has_member' => (bool) $product->has_member,
    //             'min_age' => $product->min_age ?? 18,
    //             'max_age' => $product->max_age ?? 65,
    //             'plans' => $product->plans->map(function($plan) use ($product) {
    //                 // Calculate premium based on product type
    //                 $premium = $this->calculatePremiumForPlan($product, $plan);
                    
    //                 // Handle premium data structure for product ID 9
    //                 $premiumData = $premium;
    //                 if ($product->id == 9 && is_array($premium)) {
    //                     // For product ID 9, premium is an array with relation-based premiums
    //                     $premiumData = [
    //                         'type' => 'relation_based',
    //                         'relations' => $premium,
    //                         'base_premium' => $premium[0]['premium'] ?? 0 // Use first relation as base
    //                     ];
    //                 } else {
    //                     // For other products, premium is a single value
    //                     $premiumData = (float) ($premium ?? 0.00);
    //                 }
                    
    //                 return [
    //                     'plan_id' => $plan->id,
    //                     'plan_name' => $plan->name ?? 'N/A',
    //                     'plan_code' => $plan->code ?? 'N/A',
    //                     'plan_description' => $plan->description ?? 'N/A',
    //                     'premium' => $premiumData,
    //                     'currency' => $plan->currency ?? 'BWP',
    //                     'billing_frequency' => $plan->billing_frequency ?? 'monthly',
    //                     'coverage_amount' => (float) ($plan->coverage_amount ?? 0.00),
    //                     'deductible' => (float) ($plan->deductible ?? 0.00),
    //                     'is_active' => (bool) $plan->status,
    //                     'features' => $this->getPlanFeatures($plan)
    //                 ];
    //             })
    //         ];

    //         return response()->json([
    //             'success' => true,
    //             'message' => 'Product retrieved successfully',
    //             'data' => $formattedProduct
    //         ], 200);

    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Error retrieving product: ' . $e->getMessage(),
    //             'data' => null
    //         ], 500);
    //     }
    // }

    /**
     * Get products by type
     * 
     * @param Request $request
     * @param string $type
     * @return JsonResponse
     */
    public function getProductsByType(Request $request, string $type): JsonResponse
    {
        try {
            $products = Product::with(['plans' => function($query) {
                $query->where('status', 1);
            }])
            ->whereHas('type', function($query) use ($type) {
                $query->where('name', 'like', '%' . $type . '%');
            })
            ->where('status', 1)
            ->whereNotIn('id',[3,7,8])
            ->get();

            if ($products->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No products found for the specified type',
                    'data' => null
                ], 404);
            }

            $formattedProducts = $products->map(function($product) {
                return [
                    'product_id' => $product->id,
                    'product_name' => $product->name ?? 'N/A',
                    'product_code' => $product->code ?? 'N/A',
                    'product_description' => $product->description ?? 'N/A',
                    'product_type' => $product->type->name ?? 'N/A',
                    'is_active' => (bool) $product->status,
                    'has_vehicle' => (bool) $product->has_vehicle,
                    'has_member' => (bool) $product->has_member,
                    'min_age' => $product->min_age ?? 18,
                    'max_age' => $product->max_age ?? 65,
                    'plans' => $product->plans->map(function($plan) use ($product) {
                        // Calculate premium based on product type
                        $premium = $this->calculatePremiumForPlan($product, $plan);
                        
                        return [
                            'plan_id' => $plan->id,
                            'plan_name' => $plan->name ?? 'N/A',
                            'plan_code' => $plan->code ?? 'N/A',
                            'plan_description' => $plan->description ?? 'N/A',
                            'premium' => $premium,
                            'currency' => $plan->currency ?? 'BWP',
                            'billing_frequency' => $plan->billing_frequency ?? 'monthly',
                            'coverage_amount' => (float) ($plan->coverage_amount ?? 0.00),
                            'deductible' => (float) ($plan->deductible ?? 0.00),
                            'is_active' => (bool) $plan->status,
                            'features' => $this->getPlanFeatures($plan)
                        ];
                    })
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Products retrieved successfully',
                'data' => [
                    'products' => $formattedProducts,
                    'total_count' => $products->count()
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving products: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }
    
    /**
     * Validate bundled products and customer information
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function validateBundledProducts(Request $request): JsonResponse
    {
        try {
            // Validate the request payload
            // $validatedData = $request->validate([
            //     'products' => 'required|array|min:1',
            //     'products.*.productId' => 'required|integer|exists:products,id',
            //     'products.*.planId' => 'required|integer|exists:productplans,id',
            //     'products.*.planName' => 'required|string',
            //     'products.*.premium' => 'required|numeric|min:0',
            //     'customer_info' => 'required|array',
            //     'customer_info.id_type' => 'required|string|in:Omang,Passport,Driver\'s License',
            //     'customer_info.id_number' => 'required|string|max:20',
            //     'customer_info.email' => 'required|email',
            //     'customer_info.phone' => 'required|string|regex:/^\+267\d{8}$/'
            // ]);

            // // Validate each product and plan
            // foreach ($validatedData['products'] as $product) {
            //     $productModel = Product::find($product['productId']);
            //     $planModel = Productplan::find($product['planId']);

            //     if (!$productModel || !$planModel || !$productModel->plans->contains($planModel)) {
            //         return response()->json([
            //             'success' => false,
            //             'message' => 'Invalid product or plan details',
            //             'data' => null
            //         ], 400);
            //     }

            //     // Additional validation for premium
            //     $calculatedPremium = $this->calculatePremiumForPlan($productModel, $planModel);
            //     if ((float) $product['premium'] !== $calculatedPremium) {
            //         return response()->json([
            //             'success' => false,
            //             'message' => 'Premium mismatch for product ID ' . $product['productId'],
            //             'data' => null
            //         ], 400);
            //     }
            // }

            // If all validations pass
            return response()->json([
                'success' => true,
                'message' => 'Validation successful',
                'data' => null
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error: ' . $e->getMessage(),
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error validating bundled products: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }
    
    /**
     * Get bundle settings including discount rates
     * 
     * @return JsonResponse
     */
    public function getBundleSettings(): JsonResponse
    {
        try {
            $bundleSettings = \AlphaDirect\Config::where('key', 'bundled_products_settings')->first();
            
            if (!$bundleSettings) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bundle settings not found',
                    'data' => null
                ], 404);
            }

            $settings = json_decode($bundleSettings->value, true);
            
            // Get the first (and typically only) settings object
            $bundleData = $settings[0] ?? null;
            
            if (!$bundleData) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid bundle settings format',
                    'data' => null
                ], 500);
            }

            $response = [
                'success' => true,
                'message' => 'Bundle settings retrieved successfully',
                'data' => [
                    'discount_rates' => [
                        'two_products' => (float) ($bundleData['two_products'] ?? 0),
                        'three_products' => (float) ($bundleData['three_products'] ?? 0),
                        'four_products' => (float) ($bundleData['four_products'] ?? 0),
                        'more_than_four_products' => (float) ($bundleData['more_than_four'] ?? 0)
                    ],
                    'configuration' => [
                        'motor_comprehensive_included' => (bool) ($bundleData['motor_comprehensive'] ?? false),
                        'show_on_policy_creation' => (bool) ($bundleData['bundled_show'] ?? false),
                        'apply_discount_for_motor_comprehensive' => (bool) ($bundleData['basediscount'] ?? false)
                    ],
                    'metadata' => [
                        'last_updated' => $bundleSettings->updated_at ? $bundleSettings->updated_at->toISOString() : null,
                        'settings_id' => $bundleSettings->id
                    ]
                ]
            ];

            return response()->json($response, 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving bundle settings: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * Calculate discount for bundled products based on count
     * 
     * @param int $productCount
     * @return array
     */
    public function calculateBundleDiscount(int $productCount): array
    {
        try {
            $bundleSettings = \AlphaDirect\Config::where('key', 'bundled_products_settings')->first();
            
            if (!$bundleSettings) {
                return [
                    'success' => false,
                    'message' => 'Bundle settings not found',
                    'discount_rate' => 0,
                    'discount_applicable' => false
                ];
            }

            $settings = json_decode($bundleSettings->value, true);
            $bundleData = $settings[0] ?? null;
            
            if (!$bundleData) {
                return [
                    'success' => false,
                    'message' => 'Invalid bundle settings format',
                    'discount_rate' => 0,
                    'discount_applicable' => false
                ];
            }

            $discountRate = 0;
            $discountType = '';

            // Determine discount rate based on product count
            if ($productCount == 2) {
                $discountRate = (float) ($bundleData['two_products'] ?? 0);
                $discountType = 'two_products';
            } elseif ($productCount == 3) {
                $discountRate = (float) ($bundleData['three_products'] ?? 0);
                $discountType = 'three_products';
            } elseif ($productCount == 4) {
                $discountRate = (float) ($bundleData['four_products'] ?? 0);
                $discountType = 'four_products';
            } elseif ($productCount > 4) {
                $discountRate = (float) ($bundleData['more_than_four'] ?? 0);
                $discountType = 'more_than_four_products';
            }

            return [
                'success' => true,
                'message' => 'Discount calculated successfully',
                'data' => [
                    'product_count' => $productCount,
                    'discount_rate' => $discountRate,
                    'discount_type' => $discountType,
                    'discount_applicable' => $discountRate > 0,
                    'discount_percentage' => $discountRate,
                    'configuration' => [
                        'motor_comprehensive_included' => (bool) ($bundleData['motor_comprehensive'] ?? false),
                        'show_on_policy_creation' => (bool) ($bundleData['bundled_show'] ?? false),
                        'apply_discount_for_motor_comprehensive' => (bool) ($bundleData['basediscount'] ?? false)
                    ]
                ]
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error calculating bundle discount: ' . $e->getMessage(),
                'discount_rate' => 0,
                'discount_applicable' => false
            ];
        }
    }

    /**
     * Validate bundle product request and check customer/policy existence
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function validateBundleProductRequest(Request $request): JsonResponse
    {
        try {
            // Validate the request payload
            $validatedData = $request->validate([
                'products' => 'required|array|min:1',
                'products.*.productId' => 'required|integer|exists:products,id',
                'products.*.planId' => 'required|integer|exists:product_plans,id',
                'products.*.planName' => 'required|string',
                'products.*.premium' => 'required|numeric|min:0',
                'discount_rate' => 'required|numeric|min:0',
                'discount_amount' => 'required|numeric|min:0',
                'final_total' => 'required|numeric|min:0',
                'discount_type' => 'required|string',
                'product_count' => 'required|string',
                'original_total' => 'required|numeric|min:0',
                'id_type' => 'nullable|string|in:Omang,Passport,Driver\'s License',
                'id_number' => 'nullable|string|max:50',
                'email' => 'required|email',
                'phone' => 'required|string'
            ]);

            // Check if customer exists based on ID type and number, cellphone, or email
            $customer = null;
            $customerProfile = null;

            // First try to find customer by ID type and number (if provided)
            if (!empty($validatedData['id_type']) && !empty($validatedData['id_number'])) {
                if ($validatedData['id_type'] === 'Omang') {
                    $customerProfile = CustomerProfile::where('omang', $validatedData['id_number'])->first();
                } elseif ($validatedData['id_type'] === 'Passport') {
                    $customerProfile = CustomerProfile::where('passport', $validatedData['id_number'])->first();
                } elseif ($validatedData['id_type'] === 'Driver\'s License') {
                    $customerProfile = CustomerProfile::where('driving_license_number', $validatedData['id_number'])->first();
                }
                
                if ($customerProfile) {
                    // Found customer by ID, get customer details
                    $customer = Customer::find($customerProfile->customer_id);
                }
            }

            // If not found by ID, try to find by cellphone or email
            if (!$customer) {
                $customer = Customer::where('cellphone', $validatedData['phone'])
                    ->orWhere('email', $validatedData['email'])
                    ->first();
                
                if ($customer) {
                    // Found customer by cellphone or email, get their profile
                    $customerProfile = CustomerProfile::where('customer_id', $customer->id)->first();
                }
            }

            if (!$customer || !$customerProfile) {
                // Customer not found - this is a new customer who can create policies
                return response()->json([
                    'success' => true,
                    'message' => 'New customer - can proceed to create policies for requested products',
                    'data' => [
                        'customer_id' => null,
                        'customer_name' => null,
                        'customer_email' => $validatedData['email'],
                        'customer_phone' => $validatedData['phone'],
                        'validation_status' => 'new_customer',
                        'bundle_details' => [
                            'products' => $validatedData['products'],
                            'discount_rate' => $validatedData['discount_rate'],
                            'discount_amount' => $validatedData['discount_amount'],
                            'final_total' => $validatedData['final_total'],
                            'discount_type' => $validatedData['discount_type'],
                            'product_count' => $validatedData['product_count'],
                            'original_total' => $validatedData['original_total']
                        ],
                        'customer_info' => [
                            'id_type' => $validatedData['id_type'] ?? null,
                            'id_number' => $validatedData['id_number'] ?? null,
                            'email' => $validatedData['email'],
                            'phone' => $validatedData['phone']
                        ]
                    ]
                ], 200);
            }

            // Check if any of the requested products already exist for this customer
            $existingPolicies = [];
            $productAlreadyExists = false;
            $activePolicyExists = false;
            $deactivatedPolicyExists = false;

            foreach ($validatedData['products'] as $product) {
                $existingPolicy = Policy::where('customer_id', $customer->id)
                    ->where('product_id', $product['productId'])
                   // ->whereIn('status', [0, 1]) // Check for all relevant statuses
                    ->first();

                if ($existingPolicy) {
                    $productAlreadyExists = true;
                    $existingPolicies[] = [
                        'product_id' => $product['productId'],
                        'plan_id' => $product['planId'],
                        'plan_name' => $product['planName'],
                        'policy_id' => $existingPolicy->id,
                        'policy_number' => $existingPolicy->policyNumber,
                        'status' => $existingPolicy->status,
                        'status_text' => $this->getPolicyStatusText($existingPolicy->status)
                    ];

                    // Check policy status specifically
                    if ($existingPolicy->status == 1) {
                        // Status 1: Activated - Active policy exists, cannot proceed
                        $activePolicyExists = true;
                    } elseif ($existingPolicy->status == 0) {
                        // Status 0: Deactivated - Deactivated policy exists, can proceed but should inform
                        $deactivatedPolicyExists = true;
                    }
                    // Status 2 (Cancelled) and 3 (Expired) are treated as no conflict
                }
            }

            // Prepare response based on validation results
            if ($productAlreadyExists) {
                if ($activePolicyExists) {
                    // Active policy exists - cannot proceed
                    return response()->json([
                        'success' => false,
                        'message' => 'Active policy already exists for one or more products',
                        'data' => [
                            'customer_id' => $customer->id,
                            'customer_name' => $customer->fullName,
                            'customer_email' => $customer->email,
                            'customer_phone' => $customer->cellphone,
                            'existing_policies' => $existingPolicies,
                            'validation_status' => 'active_policy_exists',
                            'policy_status_details' => [
                                'active_policies' => array_filter($existingPolicies, function($policy) {
                                    return $policy['status'] == 1;
                                }),
                                'deactivated_policies' => array_filter($existingPolicies, function($policy) {
                                    return $policy['status'] == 0;
                                }),
                                'cancelled_policies' => array_filter($existingPolicies, function($policy) {
                                    return $policy['status'] == 2;
                                }),
                                'expired_policies' => array_filter($existingPolicies, function($policy) {
                                    return $policy['status'] == 3;
                                })
                            ]
                        ]
                    ], 409); // Conflict - active policy exists
                } elseif ($deactivatedPolicyExists) {
                    // Deactivated policy exists - customer should activate existing policy instead of creating new one
                    return response()->json([
                        'success' => false,
                        'message' => 'Please activate your existing policy before creating a new one',
                        'data' => [
                            'customer_id' => $customer->id,
                            'customer_name' => $customer->fullName,
                            'customer_email' => $customer->email,
                            'customer_phone' => $customer->cellphone,
                            'validation_status' => 'deactivated_policy_exists',
                            'existing_policies' => $existingPolicies,
                            'policy_status_details' => [
                                'deactivated_policies' => array_filter($existingPolicies, function($policy) {
                                    return $policy['status'] == 0;
                                }),
                                'cancelled_policies' => array_filter($existingPolicies, function($policy) {
                                    return $policy['status'] == 2;
                                }),
                                'expired_policies' => array_filter($existingPolicies, function($policy) {
                                    return $policy['status'] == 3;
                                })
                            ],
                            'action_required' => 'activate_existing_policy',
                            'message_details' => 'You have existing deactivated policies for the requested products. Please activate these policies instead of creating new ones.'
                        ]
                    ], 409); // Conflict - deactivated policy exists, should activate instead
                } else {
                    // Only cancelled/expired policies exist - can proceed
                    return response()->json([
                        'success' => true,
                        'message' => 'Validation successful - existing cancelled/expired policies found, can proceed with new policies',
                        'data' => [
                            'customer_id' => $customer->id,
                            'customer_name' => $customer->fullName,
                            'customer_email' => $customer->email,
                            'customer_phone' => $customer->cellphone,
                            'validation_status' => 'valid_with_cancelled_expired_policies',
                            'existing_policies' => $existingPolicies,
                            'policy_status_details' => [
                                'cancelled_policies' => array_filter($existingPolicies, function($policy) {
                                    return $policy['status'] == 2;
                                }),
                                'expired_policies' => array_filter($existingPolicies, function($policy) {
                                    return $policy['status'] == 3;
                                })
                            ],
                            'bundle_details' => [
                                'products' => $validatedData['products'],
                                'discount_rate' => $validatedData['discount_rate'],
                                'discount_amount' => $validatedData['discount_amount'],
                                'final_total' => $validatedData['final_total'],
                                'discount_type' => $validatedData['discount_type'],
                                'product_count' => $validatedData['product_count'],
                                'original_total' => $validatedData['original_total']
                            ]
                        ]
                    ], 200); // Success - can proceed
                }
            }

            // If we reach here, validation passed - no existing products/policies
            return response()->json([
                'success' => true,
                'message' => 'Validation successful - no existing products found',
                'data' => [
                    'customer_id' => $customer->id,
                    'customer_name' => $customer->fullName,
                    'customer_email' => $customer->email,
                    'customer_phone' => $customer->cellphone,
                    'validation_status' => 'valid',
                    'bundle_details' => [
                        'products' => $validatedData['products'],
                        'discount_rate' => $validatedData['discount_rate'],
                        'discount_amount' => $validatedData['discount_amount'],
                        'final_total' => $validatedData['final_total'],
                        'discount_type' => $validatedData['discount_type'],
                        'product_count' => $validatedData['product_count'],
                        'original_total' => $validatedData['original_total']
                    ]
                ]
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error: ' . $e->getMessage(),
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error validating bundle product request: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * Get policy status text based on status code
     * 
     * @param int $status
     * @return string
     */
    private function getPolicyStatusText(int $status): string
    {
        switch ($status) {
            case 0:
                return 'Deactivated';
            case 1:
                return 'Activated';
            case 2:
                return 'Cancelled';
            case 3:
                return 'Expired';
            default:
                return 'Unknown';
        }
    }

    /**
     * Create bundled policy with multiple products
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function createBundledPolicy(Request $request): JsonResponse
    {
        try {
            // Validate the request payload
            $validatedData = $request->validate([
                'data' => 'required|array',
                'data.products' => 'required|array|min:1',
                'data.products.*.productId' => 'required|string',
                'data.products.*.planId' => 'required|string',
                'data.products.*.planName' => 'required|string',
                'data.products.*.premium' => 'required|string',
                'data.id_type' => 'required|string|in:Omang,Passport,Driver\'s License',
                'data.id_number' => 'required|string|max:50',
                'data.email' => 'required|email',
                'data.phone' => 'required|string',
                'data.firstname' => 'required|string|max:100',
                'data.lastname' => 'required|string|max:100',
                'data.gender' => 'required|string|in:0,1',
                'data.dob' => 'required|string',
                'data.address' => 'required|string',
                'data.state' => 'required|string',
                'data.city' => 'required|string',
                'data.sourceOfIncome' => 'required|string',
                'data.billing_date' => 'required|string',
                'data.Payment_method' => 'required|string',
                'data.pay_email' => 'required|email',
                'data.is_consent_yes' => 'required|string|in:0,1',
                'data.is_consent_to_process_yes' => 'required|string|in:0,1',
                'data.bundled_products' => 'required|array|min:1',
                'data.bundled_products.*.devices' => 'nullable|array',
                'data.bundled_products.*.devices.*.device_type' => 'required_with:data.bundled_products.*.devices|string',
                'data.bundled_products.*.devices.*.imei' => 'required_with:data.bundled_products.*.devices|string',
                'data.bundled_products.*.devices.*.phone_value' => 'required_with:data.bundled_products.*.devices|numeric',
                'data.bundled_products.*.devices.*.cell_phone_make' => 'required_with:data.bundled_products.*.devices|string',
                'data.bundled_products.*.devices.*.cell_phone_model' => 'required_with:data.bundled_products.*.devices|string',
                'data.bundled_products.*.devices.*.other_make' => 'nullable|string',
                'data.bundled_products.*.devices.*.other_model' => 'nullable|string',
                'data.bundled_products.*.devices.*.cell_phone_front' => 'nullable|string',
                'data.bundled_products.*.devices.*.cell_phone_back' => 'nullable|string',
                'data.bundled_products.*.devices.*.cell_phone_left' => 'nullable|string',
                'data.bundled_products.*.devices.*.cell_phone_right' => 'nullable|string',
                'data.bundled_products.*.devices.*.cell_phone_top' => 'nullable|string',
                'data.bundled_products.*.devices.*.cell_phone_bottom' => 'nullable|string',
                'data.is_imported' => 'nullable|string|in:Yes,No',
                'data.make' => 'nullable|string|max:100',
                'data.year' => 'nullable|string|max:4',
                'data.model' => 'nullable|string|max:100',
                'data.purpose' => 'nullable|string|max:50',
                'data.tyreVehiclePlate' => 'nullable|string|max:20',
                'data.maritalstatus' => 'nullable|string|in:1,2',
                'data.legalFName' => 'nullable|string|max:100',
                'data.legalMName' => 'nullable|string|max:100',
                'data.legalLName' => 'nullable|string|max:100',
                'data.legalPhone' => 'nullable|string|max:20',
                'data.legalEmail' => 'nullable|email|max:200',
                'data.legalGender' => 'nullable|string|in:0,1',
                'data.legalDOB' => 'nullable|string',
                'data.legalOmang' => 'nullable|string|max:45',
                'data.legalPassport' => 'nullable|string|max:45',
                'data.omangExpiry' => 'nullable|string',
                'data.passportExpiry' => 'nullable|string',
                'data.discount_rate' => 'nullable|string',
                'data.discount_amount' => 'nullable|string',
                'data.final_total' => 'nullable|string',
                'data.discount_type' => 'nullable|string',
                'data.product_count' => 'nullable|string',
                'data.original_total' => 'nullable|string',
                'data.total_final_premium' => 'nullable|string',
                'data.store' => 'nullable|string',
                'data.agent_id' => 'nullable|string'
            ]);

            $data = $validatedData['data'];

            // Check if customer exists based on ID type and number, cellphone, or email
            $customer = null;
            $customerProfile = null;
            $customer_id = null;

            // First try to find customer by ID type and number
            if ($data['id_type'] === 'Omang') {
                $customerProfile = CustomerProfile::where('omang', $data['id_number'])->first();
            } elseif ($data['id_type'] === 'Passport') {
                $customerProfile = CustomerProfile::where('passport', $data['id_number'])->first();
            } elseif ($data['id_type'] === 'Driver\'s License') {
                $customerProfile = CustomerProfile::where('driving_license_number', $data['id_number'])->first();
            }
            
            if ($customerProfile) {
                $customer = Customer::find($customerProfile->customer_id);
                $customer_id = $customer->id;
            }

            // If not found by ID, try to find by cellphone or email
            if (!$customer) {
                $customer = Customer::where('cellphone', $data['phone'])
                    ->orWhere('email', $data['email'])
                    ->first();
                
                if ($customer) {
                    $customer_id = $customer->id;
                    $customerProfile = CustomerProfile::where('customer_id', $customer->id)->first();
                }
            }

            // Create new customer if not exists
            if (!$customer) {
                $user = new Customer();
                $user->firstName = $data['firstname'];
                $user->middlename = $data['middlename'] ?? null;
                $user->lastName = $data['lastname'];
                $user->email = $data['email'];
                $user->cellphone = $data['phone'];
                
                // Check if MATI verification is required
                if (isset($data['mati-identityId']) && !empty($data['mati-identityId']) && $data['mati-identityId'] !== 'null') {
                    $user->mati_identity = $data['mati-identityId'];
                } else {
                    // return response()->json([
                    //     'success' => false,
                    //     'message' => 'KYC is Mandatory - Please Complete Mati Verification'
                    // ], 411);
                }
                
                $user->save();
                $customer_id = $user->id;

                // Create customer profile
                $profile = new CustomerProfile();
                $profile->customer_id = $user->id;
                $profile->gender = $data['gender'];
                $profile->address = $data['address'];
                $profile->city = $data['city'] ?? '';
                $profile->state = $data['state'];
                $profile->omang = $data['id_type'] === 'Omang' ? $data['id_number'] : '';
                $profile->passport = $data['id_type'] === 'Passport' ? $data['id_number'] : '';
                $profile->countryId = $data['passportIssuingCountry'] ?? null;
                $profile->maritalstatus = $data['maritalstatus'] ?? '';
                $profile->dob = date('Y-m-d', strtotime(str_replace('/', '-', $data['dob'])));
                $profile->sourceOfIncome = json_encode($data['sourceOfIncome']);
                $profile->save();

                $customer = $user;
                $customerProfile = $profile;
            } else {
                // Update existing customer if needed
                if (isset($data['mati-identityId']) && !empty($data['mati-identityId']) && $data['mati-identityId'] !== 'null') {
                    $customer->mati_identity = $data['mati-identityId'];
                    $customer->save();
                }
            }
            // Validate and calculate premiums for bundled products
            $totalPremium = 0;
            $totalSumAssured = 0;
            $bundledProducts = [];
            $apiTotalPremium = 0;
            $originalTotalPremium = 0;
            
            // First pass: validate products and calculate API total premium
            foreach ($data['bundled_products'] as $bundledProduct) {
                $productId = $bundledProduct['productId'];
                $planId = $bundledProduct['planId'];
                $apiPremium = (float) $bundledProduct['premium'];
                
                // Get product details
                $product = Product::find($productId);
                if (!$product) {
                    return response()->json([
                        'success' => false,
                        'message' => "Product with ID {$productId} not found"
                    ], 404);
                }
                
                // Get plan details
                $plan = Productplan::find($planId);
                if (!$plan) {
                    return response()->json([
                        'success' => false,
                        'message' => "Plan with ID {$planId} not found"
                    ], 404);
                }
                
                // Calculate actual premium using the existing calculation method
                $calculatedPremium = $this->calculatePremiumForPlan($product, $plan);
                
                // Convert calculated premium to float for comparison
                $calculatedPremiumFloat = $this->convertPremiumToFloat($calculatedPremium, $productId, $bundledProduct);
                
                // Store original calculated premium for discount validation
                $originalTotalPremium += $calculatedPremiumFloat;
                
                // For bundled products, we need to handle the case where premiums might be discounted
                // The API premium should match the calculated premium (or be within tolerance)
                $tolerance = 0.01; // 1 cent tolerance for floating point precision
                if (abs($apiPremium - $calculatedPremiumFloat) > $tolerance) {
                    return response()->json([
                        'success' => false,
                        'message' => "Premium mismatch for product ID {$productId}. API premium: {$apiPremium}, Calculated premium: {$calculatedPremiumFloat}",
                        'data' => [
                            'product_id' => $productId,
                            'plan_id' => $planId,
                            'api_premium' => $apiPremium,
                            'calculated_premium' => $calculatedPremiumFloat,
                            'difference' => abs($apiPremium - $calculatedPremiumFloat),
                            'debug_info' => [
                                'is_array_premium' => is_array($calculatedPremium),
                                'premium_type' => gettype($calculatedPremium),
                                'relations_count' => is_array($calculatedPremium) ? count($calculatedPremium) : 0
                            ]
                        ]
                    ], 422);
                }
                
                $apiTotalPremium += $apiPremium;
                $totalSumAssured += ($product->sum_assured ?? 0);
            }
            
            // Validate discount calculations if provided
            if (isset($data['original_total']) && isset($data['total_final_premium']) && isset($data['discount_amount'])) {
                $apiOriginalTotal = (float) $data['original_total'];
                $apiFinalTotal = (float) $data['total_final_premium'];
                $apiDiscountAmount = (float) $data['discount_amount'];
                $productCount = count($data['bundled_products']);
                
                // Validate that original total matches calculated total
                $originalTotalDiff = abs($apiOriginalTotal - $originalTotalPremium);
                if ($originalTotalDiff > 0.01) {
                    return response()->json([
                        'success' => false,
                        'message' => "Original total mismatch. API original total: {$apiOriginalTotal}, Calculated total: {$originalTotalPremium}",
                        'data' => [
                            'api_original_total' => $apiOriginalTotal,
                            'calculated_original_total' => $originalTotalPremium,
                            'difference' => $originalTotalDiff
                        ]
                    ], 422);
                }
                
                // Calculate expected discount based on product count
                $expectedDiscount = $this->calculateBundleDiscountAndTotal($apiOriginalTotal, $productCount);
                
                // Validate that discount amount is correct
                $calculatedDiscountAmount = $apiOriginalTotal - $apiFinalTotal;
                $discountDiff = abs($apiDiscountAmount - $calculatedDiscountAmount);
                if ($discountDiff > 0.01) {
                    return response()->json([
                        'success' => false,
                        'message' => "Discount amount mismatch. API discount: {$apiDiscountAmount}, Calculated discount: {$calculatedDiscountAmount}",
                        'data' => [
                            'api_discount_amount' => $apiDiscountAmount,
                            'calculated_discount_amount' => $calculatedDiscountAmount,
                            'difference' => $discountDiff,
                            'expected_discount_info' => $expectedDiscount
                        ]
                    ], 422);
                }
                
                // Validate that final total is correct
                // Calculate the correct final total by applying discount to original total
                $calculatedFinalTotal = $apiOriginalTotal - $apiDiscountAmount;
                $finalTotalDiff = abs($apiFinalTotal - $calculatedFinalTotal);
                if ($finalTotalDiff > 0.01) {
                    return response()->json([
                        'success' => false,
                        'message' => "Final total mismatch. API final total: {$apiFinalTotal}, Calculated final total: {$calculatedFinalTotal}",
                        'data' => [
                            'api_final_total' => $apiFinalTotal,
                            'calculated_final_total' => $calculatedFinalTotal,
                            'difference' => $finalTotalDiff,
                            'debug_info' => [
                                'api_original_total' => $apiOriginalTotal,
                                'api_discount_amount' => $apiDiscountAmount,
                                'calculation' => "{$apiOriginalTotal} - {$apiDiscountAmount} = {$calculatedFinalTotal}",
                                'expected_discount_info' => $expectedDiscount
                            ]
                        ]
                    ], 422);
                }
            }
            
            // Get the first product for main policy settings
            $firstProduct = Product::find($data['bundled_products'][0]['productId']);
            if (!$firstProduct) {
                return response()->json([
                    'success' => false,
                    'message' => 'First product not found'
                ], 404);
            }
            $firstPlan = Productplan::find($data['bundled_products'][0]['planId']);
            if (!$firstPlan) {
                return response()->json([
                    'success' => false,
                    'message' => 'First plan not found'
                ], 404);
            }
            
            // Create main bundled policy
            $latest = Policy::orderBy('id', 'desc')->first();
            $policyNumber = 'MIS' . Carbon::now()->year . str_pad(($latest ? $latest->id + 1 : 1), 6, '0', STR_PAD_LEFT);

            $mainPolicy = new Policy();
            $mainPolicy->customer_id = $customer_id;
            $mainPolicy->agent_id = $data['agent_id'] ?? 0;
            $mainPolicy->storeID = $data['store'] ?? null;
            $mainPolicy->product_id = $data['bundled_products'][0]['productId'];
            $mainPolicy->plan_id = $data['bundled_products'][0]['planId'];
            $mainPolicy->policyNumber = $policyNumber;
            $mainPolicy->status = 0; // Deactivated initially
            $mainPolicy->leadSource = $data['leadSource'] ?? 'start.alphadirect.co.bw';
            $mainPolicy->has_vehicle = $firstProduct->has_vehicle ?? 0;
            $mainPolicy->has_member = $firstProduct->has_member ?? 0;
            $mainPolicy->is_bundled = 1;
            $mainPolicy->billingStartDate = date('Y-m-d', strtotime(str_replace('/', '-', $data['billing_date'])));
            $mainPolicy->BillingStart = $data['BillingStart'] ?? 'Immediate';
            $mainPolicy->premium =$calculatedFinalTotal;
            $mainPolicy->sum_assured =  $firstPlan->sum_assured;
            
            // Store discount information if available
            if (isset($data['discount_rate'])) {
                $mainPolicy->bundled_discount_rate = $data['discount_rate'];
            }
            if (isset($data['discount_amount'])) {
                $mainPolicy->bundled_discount_amount = $data['discount_amount'];
            }
            if (isset($data['discount_type'])) {
                $mainPolicy->bundled_discount_type = $data['discount_type'];
            }
            
            $mainPolicy->save();

            // Handle company policy if applicable


            if(isset($data['company_policy']) && $data['company_policy'] == 'yes'){
                if(isset($data['company_id']) && !empty($data['company_id'])){
                    $companyPolicy = new \AlphaDirect\Models\CompanyPolicy();
                    $companyPolicy->policyNumber = $mainPolicy->policyNumber;

                $companyName = CompanyName::where('id', $data['company_id'])->first();
                if(!$companyName){
                    $companyName = new CompanyName();
                    $companyName->name = $data['new_company_name'];
                    $companyName->address = $data['new_company_address'];
                    $companyName->email = $data['new_company_email'];
                    $companyName->vat_no = $data['new_company_vat_no'];
                    $companyName->status = 'active';
                    $companyName->save();
                    $companyPolicy->company_id = $companyName->id;
                }else{
                    $companyPolicy->company_id = $data['company_id'];
                }
              
               
                $companyPolicy->save();

            }
           }

            
            // Store customer banking data
            $this->storeCustomerBankingData($customer_id, $mainPolicy->id, $data);
            //main product extra data
            

            // Process each bundled product (second pass: create records)
            $bundledProducts = [];

            foreach ($data['bundled_products'] as $bundledProduct) {
                $plan = Productplan::find($bundledProduct['planId']);
                
                $productId = $bundledProduct['productId'];
                $planId = $bundledProduct['planId'];
                $premium = (float) $bundledProduct['premium'];
                if( $productId != $mainPolicy->product_id){
                                // Create bundled product record
                $policyBundled = new PolicyBundled();
                $policyBundled->policy_id = $mainPolicy->id;
                $policyBundled->product_id = $productId;
                $policyBundled->sum_assured =  $plan->sum_assured;
                $policyBundled->plan_name = $planId; // Store plan_id in plan_name field as per DB schema
                $policyBundled->premium = $premium;
                $policyBundled->frequency_mc = ''; // Default to monthly frequency
                $policyBundled->subtotal = $data['original_total']; // Total premium before discount
                $policyBundled->final_premium = $data['total_final_premium']; // Total premium after discount
               
                $policyBundled->save();
                }

                // Handle relation-based products (like Hospital Cash Assurance)
                if (isset($bundledProduct['isRelationBased']) && $bundledProduct['isRelationBased'] == '1') {
                    $this->processRelationBasedProduct($mainPolicy, $bundledProduct);
                }
                if ($productId == 1) {
                  //  Log::info('Processing Life Insurance beneficiaries');
                    $this->processBeneficiaries($mainPolicy, $bundledProduct['beneficiaries']);
                }
                if (isset($bundledProduct['devices']) && !empty($bundledProduct['devices'])) {
                    $this->processDevices($mainPolicy, $bundledProduct['devices']);
                }

                // Handle vehicle data for Third Party Car Insurance (Product ID 2)
                if ($productId == 2 && isset($data['is_imported']) && isset($data['make']) && isset($data['year']) && isset($data['model']) && isset($data['purpose']) && isset($data['tyreVehiclePlate'])) {
                    $mainPolicy->has_vehicle = 1;
                    $mainPolicy->save();
                    $vehicleData = [
                        'is_imported' => $data['is_imported'],
                        'make' => $data['make'],
                        'year' => $data['year'],
                        'model' => $data['model'],
                        'purpose' => $data['purpose'],
                        'tyreVehiclePlate' => $data['tyreVehiclePlate']
                    ];
                    $this->processVehicle($mainPolicy, $vehicleData);
                }

                // Handle legal beneficiary for Legal Insurance (Product ID 4)
                if ($productId == 4 && isset($data['maritalstatus']) && $data['maritalstatus'] == '2') {
                    $legalData = [
                        'legalFName' => $data['legalFName'] ?? '',
                        'legalMName' => $data['legalMName'] ?? '',
                        'legalLName' => $data['legalLName'] ?? '',
                        'legalPhone' => $data['legalPhone'] ?? '',
                        'legalEmail' => $data['legalEmail'] ?? '',
                        'legalGender' => $data['legalGender'] ?? '',
                        'legalDOB' => $data['legalDOB'] ?? '',
                        'legalOmang' => $data['legalOmang'] ?? '',
                        'legalPassport' => $data['legalPassport'] ?? '',
                        'omangExpiry' => $data['omangExpiry'] ?? '',
                        'passportExpiry' => $data['passportExpiry'] ?? ''
                    ];
                    $this->processLegalBeneficiary($mainPolicy, $legalData);
                }
           
                $bundledProducts[] = [
                    'product_id' => $productId,
                    'plan_id' => $planId,
                    'plan_name' => $bundledProduct['planName'],
                    'premium' => $premium,
                    'policy_id' => $mainPolicy->id,
                    'policy_number' => $mainPolicy->policyNumber
                ];
            }

            // Create KYC record if not exists
            $customerKYC = KYC::where('customer_id', $customer_id)->first();
            if (!$customerKYC) {
                $customerKYC = new KYC();
                $customerKYC->customer_id = $customer_id;
                $customerKYC->save();
            }
            //policydocuments
            $d = new DocumentController();
            $verificationDoc = $d->generateInformationDocument($mainPolicy->id);

            if ($verificationDoc != null) {
                $mainPolicy->verification_doc = $verificationDoc;
                $saved = $mainPolicy->save();
            }

           
            if ($customer->cellphone && $mainPolicy->save()) {
                $smsMessaging = new SmsMessaging;
                $smsMessaging->sendTsosologoSMS(1, $customer->cellphone, $mainPolicy->policyNumber,  $mainPolicy->plan->slug, '', '', '');
            }
            if ($customer->email != null && $mainPolicy->save()) {

                if ($mainPolicy->product_id != 3) {
                    $isGenerated = $d->generatePolicyDocument($mainPolicy->id);
                    if ($isGenerated != null && $mainPolicy->status == 1) {
                        $sent = $d->sendPolicyDocument($mainPolicy->id, "Agent");
                    }
                }


            $emailData = new \stdClass();
            $emailData->hook = 'create_policy';
            $emailData->customer_id =  $customer->id;
            $emailData->policy_id = $mainPolicy->id;
            $emailData->user_id = null;
            $emailData->attachment = null;
            $emailTemplate = EmailBroadcasting::where('hook_slug', $emailData->hook)->first(array('subject'));
            $markdown = new MailTemplate($emailData);
            $html = $markdown->render('Mail.mailTemplate',['data'=>$emailData]);
            event(new \AlphaDirect\Events\SendMail($customer->email,$emailTemplate->subject,"",$html,null,['policyNumber' => $mainPolicy->policyNumber,'hook' => $emailData->hook]));
            //Mail::to($user->email)->send(new MailTemplate($data));
        }

        $customerConsent = new CustomerConsent();
        $customerConsent->policy_id = $mainPolicy->id;
        $customerConsent->customer_id = $mainPolicy->customer_id;
        $customerConsent->ip_address = $request->ip();
        $customerConsent->browser_name = $request->header('User-Agent');
        $customerConsent->is_consent_yes = $data['is_consent_yes'] ?? null;
        $customerConsent->is_consent_to_process_yes = $data['is_consent_to_process_yes'] ?? null;
        $customerConsent->save();
            //end policydocuments

            // Handle payment method specific logic
            if ($data['Payment_method'] === 'DPO') {
                // DPO specific logic can be added here
                // For now, just ensure email is provided
                if (empty($data['pay_email'])) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Email is mandatory for DPO payment method'
                    ], 412);
                }
            }

            // Fire events for policy lifecycle
            event(new \AlphaDirect\Events\policyLifecycle($mainPolicy->id, "Create"));
            switch ($request->data['Payment_method']) {
                
                case 'DPO':

                        if(isset($request->data['pay_email']) && $request->data['pay_email'] != null){
                            $payemail               = new PaymentEmail();
                            $payemail->policyNumber =  $mainPolicy->policyNumber;
                            $payemail->pay_email =  $request->data['pay_email'];
                            $payemail->save();
                        }
                        $dpo = new DpoPaymentController;
                    // if (isset($request->data['BillingStart'])) {
                        $request->merge([
                            'filter' => 'PolicyNumber',
                            'searchValue' => $mainPolicy->policyNumber,
                            'leadSource' => $mainPolicy->leadSource,
                            'email' => $request->data['email']
                        ]);
                        $pay = $dpo->findPolicyForOnlinePayment($request);
                        return $pay;
                    // }
                    // return response()->json(['success' => 1, 'policyNumber' => $mainPolicy->policyNumber, 'product_id' => $mainPolicy->product_id], 200);

                    break;
               
               

                case 'RealPay':
                    if(isset($request->data['instant_activate_policy'])) {
                        if(isset($request->data['pay_email']) && $request->data['pay_email'] != null){
                            $payemail               = new PaymentEmail();
                            $payemail->policyNumber =  $mainPolicy->policyNumber;
                            $payemail->pay_email =  $request->data['pay_email'];
                            $payemail->save();
                        }
                        $dpo = new DpoPaymentController;
                        $request->merge([
                            'filter' => 'PolicyNumber',
                            'searchValue' => $mainPolicy->policyNumber,
                            'leadSource' => $mainPolicy->leadSource,
                            'email' => $request->data['email'],
                            'requestType' => 'instantActivatePolicy'
                        ]);
                        $pay = $dpo->findPolicyForOnlinePayment($request);
                        return $pay;
                        break;
                    } else {
                        $addEvent = $this->realpayPayment($mainPolicy);
                        return response()->json(['status' => 'success', 'message' => 'Policy created successfully', 'policyNumber' => $mainPolicy->policyNumber], 200);
                        break;
                    }
                case 'PayM8':
                            $paym8 = new PayM8Controller();
                            $addEvent = $paym8->createAdHocPayment($mainPolicy->id);
                            return response()->json(['status' => 'success', 'message' => 'Policy created successfully', 'policyNumber' => $mainPolicy->policyNumber], 200);
                            break;

                case 'DEFER':
                            // Create-only: mint the policy and return its number +
                            // amount so the customer can choose a gateway on the next
                            // screen (parity with the instant-product create→pay flow).
                            // No payment gateway is initiated here. Opt-in: only used
                            // when the FE defers payment to a follow-up step.
                            return response()->json([
                                'status' => 'success',
                                'message' => 'Policy created successfully',
                                'policyNumber' => $mainPolicy->policyNumber,
                                'amount_to_pay' => (float) ($data['total_final_premium'] ?? $apiTotalPremium),
                            ], 200);
                            break;


                default:
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Please select a payment vendor'
                    ], 400);
            }

            return response()->json([
                'success' => true,
                'message' => 'Bundled Policy Created Successfully',
                'data' => [
                                    'main_policy' => [
                    'policy_id' => $mainPolicy->id,
                    'policy_number' => $mainPolicy->policyNumber,
                    'total_premium' => $apiTotalPremium,
                    'total_sum_assured' => $totalSumAssured,
                    'status' => 'Deactivated',
                    'discount_info' => [
                        'discount_rate' => $data['discount_rate'] ?? null,
                        'discount_amount' => $data['discount_amount'] ?? null,
                        'discount_type' => $data['discount_type'] ?? null,
                        'original_total' => $data['original_total'] ?? null,
                        'final_total' => $data['total_final_premium'] ?? null
                    ]
                ],
                    'bundled_products' => $bundledProducts,
                    'customer' => [
                        'customer_id' => $customer_id,
                        'name' => $customer->firstName . ' ' . $customer->lastName,
                        'email' => $customer->email,
                        'phone' => $customer->cellphone
                    ],
                    'payment_method' => $data['Payment_method'],
                    'billing_date' => $data['billing_date']
                ]
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error: ' . $e->getMessage(),
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating bundled policy: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Process relation-based product (e.g., Hospital Cash Assurance)
     * 
     * @param Policy $policy
     * @param array $bundledProduct
     * @return void
     */
    private function processRelationBasedProduct(Policy $policy, array $bundledProduct): void
    {
        try {
            $productId = $bundledProduct['productId'];
            
            if ($productId == 9) {
                // For Product ID 9 (Hospital Cash Assurance), use HospitalCashbackCoapplicants
                $this->processHospitalCashCoapplicants($policy, $bundledProduct);
            } else {

                // For other products, use PolicyMember
                // if (isset($bundledProduct['relations'])) {
                //     $relations = json_decode($bundledProduct['relations'], true);
                    
                //     foreach ($relations as $relation) {
                //         if (isset($relation['relation']) && $relation['relation'] === 'Spouse/Immediate Dependent') {
                //             if (isset($bundledProduct['spouse'])) {
                //                 $this->createPolicyMember($policy, $bundledProduct['spouse'], 'Spouse');
                //             }
                //         } elseif (isset($relation['relation']) && $relation['relation'] === 'Children (Max Up to 6)') {
                //             if (isset($bundledProduct['children']) && is_array($bundledProduct['children'])) {
                //                 foreach ($bundledProduct['children'] as $child) {
                //                     $this->createPolicyMember($policy, $child, 'Child');
                //                 }
                //             }
                //         }
                //     }
                // }
            }
        } catch (\Exception $e) {
            Log::error('Error processing relation-based product: ' . $e->getMessage(), [
                'policy_id' => $policy->id,
                'product_id' => $bundledProduct['productId'] ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            // Continue processing other products even if this one fails
        }
    }

    /**
     * Process Hospital Cash Assurance co-applicants (Product ID 9)
     * 
     * @param Policy $policy
     * @param array $bundledProduct
     * @return void
     */
    private function processHospitalCashCoapplicants(Policy $policy, array $bundledProduct): void
    {
        try {
            // First, store the policy holder data (relation = 1)
            $this->createHospitalCashCoapplicant($policy, $bundledProduct, 'PolicyHolder', 1);
            
            // Process spouse as co-applicant (relation = 2)
            if (isset($bundledProduct['spouse']) && !empty($bundledProduct['spouse'])) {
                $this->createHospitalCashCoapplicant($policy, $bundledProduct['spouse'], 'Spouse', 2);
            }
            
            // Process children as co-applicants (relation = 3)
            if (isset($bundledProduct['children']) && is_array($bundledProduct['children'])) {
                foreach ($bundledProduct['children'] as $child) {
                    $this->createHospitalCashCoapplicant($policy, $child, 'Child', 3);
                }
            }
        } catch (\Exception $e) {
            Log::error('Error processing Hospital Cash co-applicants: ' . $e->getMessage(), [
                'policy_id' => $policy->id,
                'product_id' => $bundledProduct['productId'] ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            // Continue processing other products even if this one fails
        }
    }

    /**
     * Create Hospital Cash co-applicant
     * 
     * @param Policy $policy
     * @param array $coapplicantData
     * @param string $relationType
     * @param int $relationId
     * @return void
     */
    private function createHospitalCashCoapplicant(Policy $policy, array $coapplicantData, string $relationType, int $relationId): void
    {
        try {
            $coapplicant = new HospitalCashbackCoapplicants();
            $coapplicant->policy_id = $policy->id;
            $coapplicant->relation = $relationId; // Store numeric relation (1=Policy Holder, 2=Spouse, 3=Child)
            
            if ($relationType === 'PolicyHolder') {
                // For policy holder, get data from the main customer data
                $customer = Customer::find($policy->customer_id);
                $customerProfile = CustomerProfile::where('customer_id', $policy->customer_id)->first();
                
                $coapplicant->first_name = $customer->firstName ?? '';
                $coapplicant->last_name = $customer->lastName ?? '';
                $coapplicant->gender = $customerProfile->gender ?? '';
                $coapplicant->omang = $customerProfile->omang ?? null;
                $coapplicant->passport = $customerProfile->passport ?? null;
                
                // Handle date of birth for policy holder
                if (isset($customerProfile->dob) && !empty($customerProfile->dob)) {
                    $coapplicant->dob = $customerProfile->dob;
                }
            } else {
                // For spouse and children, get data from the provided data
                $coapplicant->first_name = $coapplicantData['fname'] ?? '';
                $coapplicant->last_name = $coapplicantData['lname'] ?? '';
                $coapplicant->gender = $coapplicantData['gender'] ?? '';
                $coapplicant->omang = $coapplicantData['id_number'] ?? null;
                $coapplicant->passport = $coapplicantData['id_number'] ?? null;
                
                // Handle date of birth for spouse/children
                if (isset($coapplicantData['dob']) && !empty($coapplicantData['dob'])) {
                    $dob = $coapplicantData['dob'];
                    $pos = strpos($dob, '/');
                    if ($pos !== false) {
                        $coapplicant->dob = \Carbon\Carbon::createFromFormat('d/m/Y', $dob)->format('Y-m-d');
                    } else {
                        $coapplicant->dob = \Carbon\Carbon::parse($dob)->format('Y-m-d');
                    }
                }
            }
            
            $coapplicant->save();
        } catch (\Exception $e) {
            Log::error('Error creating Hospital Cash co-applicant: ' . $e->getMessage(), [
                'policy_id' => $policy->id,
                'relation_type' => $relationType,
                'relation_id' => $relationId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            // Continue processing other co-applicants even if this one fails
        }
    }

    /**
     * Create policy member
     * 
     * @param Policy $policy
     * @param array $memberData
     * @param string $relation
     * @return void
     */
    private function createPolicyMember(Policy $policy, array $memberData, string $relation): void
    {
        try {
            $member = new PolicyMember();
            $member->policy_id = $policy->id;
            $member->relation = $relation;
            $member->first_name = $memberData['fname'] ?? '';
            $member->middle_name = $memberData['mname'] ?? null;
            $member->last_name = $memberData['lname'] ?? '';
            $member->gender = $memberData['gender'] ?? '';
            $member->dob = date('Y-m-d', strtotime(str_replace('/', '-', $memberData['dob'])));
            $member->save();
        } catch (\Exception $e) {
            Log::error('Error creating policy member: ' . $e->getMessage(), [
                'policy_id' => $policy->id,
                'relation' => $relation,
                'member_data' => $memberData,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            // Continue processing other members even if this one fails
        }
    }

    /**
     * Process beneficiaries for products
     * 
     * @param Policy $policy
     * @param array $beneficiaries
     * @return void
     */
    private function processBeneficiaries(Policy $policy, array $beneficiaries): void
    {
        //Log::info('Processing beneficiaries for policy ID: ' . $policy->id);
        try {
            if(count($beneficiaries) > 0){
                Log::info('Processing beneficiaries for policy ID11: ' . $policy->id); 
         
            foreach ($beneficiaries as $beneficiary) {
                try {
                //Log::info('Processing beneficiaries for policy ID111: ' . $policy->id);
                        $b = new PolicyBeneficiary();
                        $b->policy_id = $policy->id;
                       
                        $b->relation = htmlspecialchars(strip_tags($beneficiary['beneficiaryRelation']));
                        $b->first_name = htmlspecialchars(strip_tags($beneficiary['beneficiaryFName'] ?? ''));
                        $b->middle_name = htmlspecialchars(strip_tags($beneficiary['beneficiaryMName'] ?? ''));
                        $b->last_name = htmlspecialchars(strip_tags($beneficiary['beneficiaryLName'] ?? ''));
                        
                        // Handle date of birth with proper format conversion
                        if (isset($beneficiary['beneficiaryDOB']) && !empty($beneficiary['beneficiaryDOB'])) {
                            $dob = $beneficiary['beneficiaryDOB'];
                            $pos = strpos($dob, '/');
                            if ($pos !== false) {
                                $b->dob = \Carbon\Carbon::createFromFormat('d/m/Y', $dob)->format('Y-m-d');
                            } else {
                                $b->dob = \Carbon\Carbon::parse($dob)->format('Y-m-d');
                            }
                        }
                        
                        // Gender - convert to integer if needed
                        if (isset($beneficiary['beneficiaryGender'])) {
                            $gender = $beneficiary['beneficiaryGender'];
                            if (is_string($gender)) {
                                $b->gender = ($gender === 'Male' || $gender === '1' || $gender === '1') ? 1 : 0;
                            } else {
                                $b->gender = (int)$gender;
                            }
                        }
                        
                        if (isset($beneficiary['beneficiaryPayment'])) {
                            $b->payment = (int)$beneficiary['beneficiaryPayment'];
                        }
                        
                       
                        $b->omang = htmlspecialchars(strip_tags($beneficiary['beneficiaryOmang'] ?? ''));
                        $b->passport = htmlspecialchars(strip_tags($beneficiary['beneficiaryPassport'] ?? ''));
                        $b->save();
                        //Log::info('Processing beneficiaries for dddpolicy ID: ' . $policy->id);
                } catch (\Exception $e) {
                    Log::error('Error creating individual beneficiary: ' . $e->getMessage(), [
                        'policy_id' => $policy->id,
                        'beneficiary_data' => $beneficiary,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    // Continue processing other beneficiaries even if this one fails
                }
            }
        }
        } catch (\Exception $e) {
            Log::error('Error processing beneficiaries: ' . $e->getMessage(), [
                'policy_id' => $policy->id,
                'beneficiaries_count' => count($beneficiaries),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            // Continue processing other products even if beneficiaries fail
        }
    }

    /**
     * Validate premium for bundled products
     * 
     * @param array $bundledProducts
     * @return array
     */
    public function validateBundledProductPremiums(array $bundledProducts): array
    {
        $validationResults = [
            'is_valid' => true,
            'total_premium' => 0,
            'total_sum_assured' => 0,
            'errors' => [],
            'product_details' => []
        ];

        foreach ($bundledProducts as $index => $bundledProduct) {
            $productId = $bundledProduct['productId'];
            $planId = $bundledProduct['planId'];
            $apiPremium = (float) $bundledProduct['premium'];

            // Get product details
            $product = Product::find($productId);
            if (!$product) {
                $validationResults['errors'][] = "Product with ID {$productId} not found";
                $validationResults['is_valid'] = false;
                continue;
            }

            // Get plan details
            $plan = Productplan::find($planId);
            if (!$plan) {
                $validationResults['errors'][] = "Plan with ID {$planId} not found";
                $validationResults['is_valid'] = false;
                continue;
            }

            // Calculate actual premium using the existing calculation method
            $calculatedPremium = $this->calculatePremiumForPlan($product, $plan);

            // Convert calculated premium to float for comparison
            $calculatedPremiumFloat = $this->convertPremiumToFloat($calculatedPremium, $productId, $bundledProduct);

            // Validate that API premium matches calculated premium (with tolerance)
            $tolerance = 0.01; // 1 cent tolerance for floating point precision
            $isPremiumValid = abs($apiPremium - $calculatedPremiumFloat) <= $tolerance;

            if (!$isPremiumValid) {
                $validationResults['errors'][] = "Premium mismatch for product ID {$productId}. API premium: {$apiPremium}, Calculated premium: {$calculatedPremiumFloat}";
                $validationResults['is_valid'] = false;
            }

            $validationResults['total_premium'] += $apiPremium;
            $validationResults['total_sum_assured'] += ($product->sum_assured ?? 0);

            $validationResults['product_details'][] = [
                'product_id' => $productId,
                'plan_id' => $planId,
                'plan_name' => $bundledProduct['planName'] ?? 'N/A',
                'api_premium' => $apiPremium,
                'calculated_premium' => $calculatedPremiumFloat,
                'is_valid' => $isPremiumValid,
                'difference' => abs($apiPremium - $calculatedPremiumFloat),
                'product_name' => $product->name ?? 'N/A',
                'plan_name_from_db' => $plan->name ?? 'N/A'
            ];
        }

        return $validationResults;
    }

    /**
     * Validate bundled product premiums with discount calculations
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function validateBundledProductPremiumsWithDiscounts(Request $request): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'bundled_products' => 'required|array|min:1',
                'bundled_products.*.productId' => 'required|string',
                'bundled_products.*.planId' => 'required|string',
                'bundled_products.*.premium' => 'required|string',
                'original_total' => 'nullable|string',
                'total_final_premium' => 'nullable|string',
                'discount_amount' => 'nullable|string',
                'discount_rate' => 'nullable|string',
                'discount_type' => 'nullable|string'
            ]);

            $bundledProducts = $validatedData['bundled_products'];
            $originalTotal = isset($validatedData['original_total']) ? (float) $validatedData['original_total'] : null;
            $finalTotal = isset($validatedData['total_final_premium']) ? (float) $validatedData['total_final_premium'] : null;
            $discountAmount = isset($validatedData['discount_amount']) ? (float) $validatedData['discount_amount'] : null;

            // Validate individual product premiums
            $validationResults = $this->validateBundledProductPremiums($bundledProducts);
            
            if (!$validationResults['is_valid']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Premium validation failed',
                    'data' => $validationResults
                ], 422);
            }

            // Validate discount calculations if provided
            if ($originalTotal !== null && $finalTotal !== null && $discountAmount !== null) {
                $calculatedOriginalTotal = $validationResults['total_premium'];
                $calculatedDiscountAmount = $originalTotal - $finalTotal;
                $calculatedFinalTotal = $originalTotal - $calculatedDiscountAmount;

                $tolerance = 0.01;

                // Validate original total
                if (abs($originalTotal - $calculatedOriginalTotal) > $tolerance) {
                    $validationResults['errors'][] = "Original total mismatch. API: {$originalTotal}, Calculated: {$calculatedOriginalTotal}";
                    $validationResults['is_valid'] = false;
                }

                // Validate discount amount
                if (abs($discountAmount - $calculatedDiscountAmount) > $tolerance) {
                    $validationResults['errors'][] = "Discount amount mismatch. API: {$discountAmount}, Calculated: {$calculatedDiscountAmount}";
                    $validationResults['is_valid'] = false;
                }

                // Validate final total
                if (abs($finalTotal - $calculatedFinalTotal) > $tolerance) {
                    $validationResults['errors'][] = "Final total mismatch. API: {$finalTotal}, Calculated: {$calculatedFinalTotal}";
                    $validationResults['is_valid'] = false;
                }

                // Add discount validation results
                $validationResults['discount_validation'] = [
                    'original_total_valid' => abs($originalTotal - $calculatedOriginalTotal) <= $tolerance,
                    'discount_amount_valid' => abs($discountAmount - $calculatedDiscountAmount) <= $tolerance,
                    'final_total_valid' => abs($finalTotal - $calculatedFinalTotal) <= $tolerance,
                    'api_values' => [
                        'original_total' => $originalTotal,
                        'discount_amount' => $discountAmount,
                        'final_total' => $finalTotal
                    ],
                    'calculated_values' => [
                        'original_total' => $calculatedOriginalTotal,
                        'discount_amount' => $calculatedDiscountAmount,
                        'final_total' => $calculatedFinalTotal
                    ]
                ];
            }

            return response()->json([
                'success' => true,
                'message' => 'Premium validation completed',
                'data' => $validationResults
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error: ' . $e->getMessage(),
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error validating premiums: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store customer banking data
     * 
     * @param int $customerId
     * @param int $policyId
     * @param array $data
     * @return void
     */
    private function storeCustomerBankingData(int $customerId, int $policyId, array $data): void
    {
        try {
            // Check if customer banking data already exists
            $existingBanking = \AlphaDirect\CustomerBanking::where('customer_id', $customerId)
                ->where('policy_id', $policyId)
                ->first();

            if ($existingBanking) {
                // Update existing banking data
                $existingBanking->bankName = $data['bankName'] ?? null;
                $existingBanking->branchCode = $data['branchCode'] ?? null;
                $existingBanking->accountNumber = $data['accountNumber'] ?? null;
                //$existingBanking->bankingMethod = $data['Payment_method'] ?? null;
                $existingBanking->billing = $data['Payment_method'] ?? null;
                $existingBanking->save();
            } else {
                // Create new banking data
                $customerBanking = new \AlphaDirect\CustomerBanking();
                $customerBanking->customer_id = $customerId;
                $customerBanking->policy_id = $policyId;
                $customerBanking->bankName = $data['bankName'] ?? null;
                $customerBanking->branchCode = $data['branchCode'] ?? null;
                $customerBanking->accountNumber = $data['accountNumber'] ?? null;
                //$customerBanking->bankingMethod = $data['Payment_method'] ?? null;
                $customerBanking->billing = $data['Payment_method'] ?? null;
                $customerBanking->save();
            }
        } catch (\Exception $e) {
            // Log error but don't fail the entire process
            Log::error('Error storing customer banking data: ' . $e->getMessage(), [
                'customer_id' => $customerId,
                'policy_id' => $policyId,
                'data' => $data,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            // Continue processing even if banking data fails
        }
    }

    /**
     * Convert premium to float based on product type
     * 
     * @param mixed $calculatedPremium
     * @param int $productId
     * @param array $bundledProduct
     * @return float
     */
    private function convertPremiumToFloat($calculatedPremium, int $productId, array $bundledProduct): float
    {
        if (is_array($calculatedPremium)) {
            if ($productId == 9) {
                // For Product ID 9 (Hospital Cash Assurance), calculate total from relations
                return $this->calculateHospitalCashTotalPremium($calculatedPremium, $bundledProduct);
            } else {
                // For other products with array premiums, take first element
                return (float) ($calculatedPremium[0]['premium'] ?? 0);
            }
        } else {
            // For products with single premium value
            return (float) $calculatedPremium;
        }
    }

    /**
     * Calculate total premium for Hospital Cash Assurance (Product ID 9)
     * 
     * @param array $relations
     * @param array $bundledProduct
     * @return float
     */
    private function calculateHospitalCashTotalPremium(array $relations, array $bundledProduct): float
    {
        $totalPremium = 0;
        
        foreach ($relations as $relation) {
            $relationType = $relation['relation'] ?? '';
            $premium = (float) ($relation['premium'] ?? 0);
            
            if ($relationType === 'Policy Holder') {
                // Always add policy holder premium
                $totalPremium += $premium;
            } elseif ($relationType === 'Spouse/Immediate Dependent') {
                // Add spouse premium if spouse data exists
                if (isset($bundledProduct['spouse']) && !empty($bundledProduct['spouse'])) {
                    $totalPremium += $premium;
                }
            } elseif ($relationType === 'Children (Max Up to 6)') {
                // Add children premium based on children count
                $childrenCount = 0;
                if (isset($bundledProduct['children']) && is_array($bundledProduct['children'])) {
                    $childrenCount = count($bundledProduct['children']);
                }
                $totalPremium += ($premium * $childrenCount);
            }
        }
        
        return $totalPremium;
    }

    /**
     * Calculate bundle discount and final total
     * 
     * @param float $originalTotal
     * @param int $productCount
     * @return array
     */
    private function calculateBundleDiscountAndTotal(float $originalTotal, int $productCount): array
    {
        $discountInfo = $this->calculateBundleDiscount($productCount);
        
        if (!$discountInfo['success']) {
            return [
                'success' => false,
                'message' => $discountInfo['message'],
                'discount_rate' => 0,
                'discount_amount' => 0,
                'final_total' => $originalTotal
            ];
        }
        
        $discountRate = $discountInfo['data']['discount_rate'];
        $discountAmount = ($originalTotal * $discountRate) / 100;
        $finalTotal = $originalTotal - $discountAmount;
        
        return [
            'success' => true,
            'discount_rate' => $discountRate,
            'discount_amount' => round($discountAmount, 2),
            'final_total' => round($finalTotal, 2),
            'original_total' => $originalTotal
        ];
    }

    /**
     * Process devices for mobile insurance products (Product ID 5)
     * 
     * @param Policy $policy
     * @param array $devices
     * @return void
     */
    private function processDevices(Policy $policy, array $devices): void
    {
        try {
            $sumAssuredCellphone = 0;
            
            foreach ($devices as $device) {
                try {
                    $data = array();
                    $policy_id = $policy->id;
                    $customer_id = $policy->customer_id;
                    $device_type = htmlspecialchars(strip_tags($device['device_type']));
                    $imei = htmlspecialchars(strip_tags($device['imei']));
                    $phone_value = htmlspecialchars(strip_tags($device['phone_value']));
                    $cell_phone_make = htmlspecialchars(strip_tags($device['cell_phone_make']));
                    
                    // Handle make - check if it's "Other" or a specific make ID
                    if ($cell_phone_make == 'Other') {
                        $cell_phone_make = htmlspecialchars(strip_tags($device['other_make']));
                    } else {
                        $make = DeviceMakeModel::where('id', $cell_phone_make)->first(array('name'));
                        if ($make != null) {
                            $cell_phone_make = $make->name;
                        }
                    }
                    
                    $cell_phone_model = htmlspecialchars(strip_tags($device['cell_phone_model']));
                    
                    // Handle model - check if it's "Other" or a specific model ID
                    if ($cell_phone_model == 'Other') {
                        $cell_phone_model = htmlspecialchars(strip_tags($device['other_model']));
                    } else {
                        $model = DeviceMakeModel::where('id', $cell_phone_model)->first(array('name'));
                        if ($model != null) {
                            $cell_phone_model = $model->name;
                        }
                    }
                    
                    $data = [
                        'policy_id'        => $policy_id,
                        'customer_id'      => $customer_id,
                        'device_type'      => $device_type,
                        'imei'             => $imei,
                        'phone_value'      => $phone_value,
                        'cell_phone_make'  => $cell_phone_make,
                        'cell_phone_model' => $cell_phone_model,
                    ];

                    // Handle device images - move from temp to final location
                    if (isset($device['cell_phone_front']) && $device['cell_phone_front'] != null) {
                        $fileName = explode('/', $device['cell_phone_front']);
                        $name = array_pop($fileName);
                        $filePath = 'device/' . $policy_id . '/' . 'Cellphone/' . $customer_id . '/front' . '/' . $name;
                        Storage::disk('s3')->move($device['cell_phone_front'], $filePath);
                        $data['cell_phone_front'] = $filePath;
                    }
                    
                    if (isset($device['cell_phone_back']) && $device['cell_phone_back'] != null) {
                        $fileName = explode('/', $device['cell_phone_back']);
                        $name = array_pop($fileName);
                        $filePath = 'device/' . $policy_id . '/' . 'Cellphone/' . $customer_id . '/back' . '/' . $name;
                        Storage::disk('s3')->move($device['cell_phone_back'], $filePath);
                        $data['cell_phone_back'] = $filePath;
                    }
                    
                    if (isset($device['cell_phone_left']) && $device['cell_phone_left'] != null) {
                        $fileName = explode('/', $device['cell_phone_left']);
                        $name = array_pop($fileName);
                        $filePath = 'device/' . $policy_id . '/' . 'Cellphone/' . $customer_id . '/left' . '/' . $name;
                        Storage::disk('s3')->move($device['cell_phone_left'], $filePath);
                        $data['cell_phone_left'] = $filePath;
                    }
                    
                    if (isset($device['cell_phone_right']) && $device['cell_phone_right'] != null) {
                        $fileName = explode('/', $device['cell_phone_right']);
                        $name = array_pop($fileName);
                        $filePath = 'device/' . $policy_id . '/' . 'Cellphone/' . $customer_id . '/right' . '/' . $name;
                        Storage::disk('s3')->move($device['cell_phone_right'], $filePath);
                        $data['cell_phone_right'] = $filePath;
                    }
                    
                    if (isset($device['cell_phone_top']) && $device['cell_phone_top'] != null) {
                        $fileName = explode('/', $device['cell_phone_top']);
                        $name = array_pop($fileName);
                        $filePath = 'device/' . $policy_id . '/' . 'Cellphone/' . $customer_id . '/top' . '/' . $name;
                        Storage::disk('s3')->move($device['cell_phone_top'], $filePath);
                        $data['cell_phone_top'] = $filePath;
                    }
                    
                    if (isset($device['cell_phone_bottom']) && $device['cell_phone_bottom'] != null) {
                        $fileName = explode('/', $device['cell_phone_bottom']);
                        $name = array_pop($fileName);
                        $filePath = 'device/' . $policy_id . '/' . 'Cellphone/' . $customer_id . '/bottom' . '/' . $name;
                        Storage::disk('s3')->move($device['cell_phone_bottom'], $filePath);
                        $data['cell_phone_bottom'] = $filePath;
                    }

                    // Create PolicyCellPhone record
                    $policyCellPhone = $this->policy_cell_phone_interface->add_new_policy_cell_phone($data);
                    $sumAssuredCellphone = $sumAssuredCellphone + $phone_value;
                } catch (\Exception $e) {
                    Log::error('Error creating individual device: ' . $e->getMessage(), [
                        'policy_id' => $policy->id,
                        'device_data' => $device,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    // Continue processing other devices even if this one fails
                }
            }
            
            // Update policy sum assured with total device values
            if ($sumAssuredCellphone > 0) {
                // $policy->sum_assured = $sumAssuredCellphone;
                // $policy->save();
            }
        } catch (\Exception $e) {
            Log::error('Error processing devices: ' . $e->getMessage(), [
                'policy_id' => $policy->id,
                'devices_count' => count($devices),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            // Continue processing other products even if devices fail
        }
    }

    /**
     * Process vehicle data for Third Party Car Insurance (Product ID 2)
     * 
     * @param Policy $policy
     * @param array $vehicleData
     * @return void
     */
    private function processVehicle(Policy $policy, array $vehicleData): void
    {
        try {
            $vehicle = new Vehicle();
            $vehicle->customer_id = $policy->customer_id;
            $vehicle->policy_id = $policy->id;
            
            // Handle is_imported field - convert "Yes"/"No" to 1/0
            if (isset($vehicleData['is_imported'])) {
                if ($vehicleData['is_imported'] == 'Yes') {
                    $vehicle->is_imported = 1;
                } elseif ($vehicleData['is_imported'] == 'No') {
                    $vehicle->is_imported = 0;
                } else {
                    $vehicle->is_imported = null;
                }
            }
            
            // Set vehicle details
            $vehicle->make = htmlspecialchars(strip_tags($vehicleData['make'] ?? ''));
            $vehicle->year = htmlspecialchars(strip_tags($vehicleData['year'] ?? ''));
            $vehicle->model = htmlspecialchars(strip_tags($vehicleData['model'] ?? ''));
            $vehicle->purpose = htmlspecialchars(strip_tags($vehicleData['purpose'] ?? ''));
            $vehicle->vehiclePlate = htmlspecialchars(strip_tags($vehicleData['tyreVehiclePlate'] ?? ''));
            
            $vehicle->save();
        } catch (\Exception $e) {
            Log::error('Error processing vehicle data: ' . $e->getMessage(), [
                'policy_id' => $policy->id,
                'vehicle_data' => $vehicleData,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            // Continue processing other products even if vehicle fails
        }
    }

    /**
     * Process legal beneficiary for Legal Insurance (Product ID 4)
     * 
     * @param Policy $policy
     * @param array $legalData
     * @return void
     */
    private function processLegalBeneficiary(Policy $policy, array $legalData): void
    {
        try {
            $b = new PolicyBeneficiary();
            $b->policy_id = $policy->id;
            $b->relation = 'Spouse'; // Legal insurance beneficiary is always spouse
            
            // Basic beneficiary information
            $b->first_name = htmlspecialchars(strip_tags($legalData['legalFName'] ?? ''));
            $b->middle_name = htmlspecialchars(strip_tags($legalData['legalMName'] ?? ''));
            $b->last_name = htmlspecialchars(strip_tags($legalData['legalLName'] ?? ''));
            $b->cellphone = htmlspecialchars(strip_tags($legalData['legalPhone'] ?? ''));
            $b->email = htmlspecialchars(strip_tags($legalData['legalEmail'] ?? ''));
            $b->passport = htmlspecialchars(strip_tags($legalData['legalPassport'] ?? ''));
            $b->omang = htmlspecialchars(strip_tags($legalData['legalOmang'] ?? ''));
            
            // Handle gender - convert to integer if needed
            if (isset($legalData['legalGender']) && !empty($legalData['legalGender'])) {
                $gender = $legalData['legalGender'];
                if (is_string($gender)) {
                    $b->gender = ($gender === 'Male' || $gender === '1') ? 1 : 0;
                } else {
                    $b->gender = (int)$gender;
                }
            }
            
            // Handle date of birth with proper format conversion
            if (isset($legalData['legalDOB']) && !empty($legalData['legalDOB'])) {
                $dob = $legalData['legalDOB'];
                $pos = strpos($dob, '/');
                if ($pos !== false) {
                    $b->dob = \Carbon\Carbon::createFromFormat('d/m/Y', $dob)->format('Y-m-d');
                } else {
                    $b->dob = \Carbon\Carbon::parse($dob)->format('Y-m-d');
                }
            }
            
            // Legal document expiry dates
            if (isset($legalData['omangExpiry']) && !empty($legalData['omangExpiry'])) {
                $expiry = $legalData['omangExpiry'];
                $pos = strpos($expiry, '/');
                if ($pos !== false) {
                    $b->legalOmangExpiry = \Carbon\Carbon::createFromFormat('d/m/Y', $expiry)->format('Y-m-d');
                } else {
                    $b->legalOmangExpiry = \Carbon\Carbon::parse($expiry)->format('Y-m-d');
                }
            }
            
            if (isset($legalData['passportExpiry']) && !empty($legalData['passportExpiry'])) {
                $expiry = $legalData['passportExpiry'];
                $pos = strpos($expiry, '/');
                if ($pos !== false) {
                    $b->legalPassportExpiry = \Carbon\Carbon::createFromFormat('d/m/Y', $expiry)->format('Y-m-d');
                } else {
                    $b->legalPassportExpiry = \Carbon\Carbon::parse($expiry)->format('Y-m-d');
                }
            }
            
            $b->save();
        } catch (\Exception $e) {
            Log::error('Error processing legal beneficiary: ' . $e->getMessage(), [
                'policy_id' => $policy->id,
                'legal_data' => $legalData,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            // Continue processing other products even if legal beneficiary fails
        }
    }

    /**
     * Get all company names with status 1 (active)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getActiveCompanyNames(): JsonResponse
    {
        try {
            // Get all company names with status active
            $companies = CompanyName::where('status', 'active')->get();

            $formattedCompanies = $companies->map(function($company) {
                return [
                    'id' => $company->id,
                    'name' => $company->name ?? 'N/A',
                    'address' => $company->address ?? null,
                    'email' => $company->email ?? null,
                    'vat_no' => $company->vat_no ?? null
                ];
            });

            $response = [
                'success' => true,
                'message' => 'Active company names retrieved successfully',
                'data' => $formattedCompanies
            ];

            return response()->json($response, 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving company names: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }
    public function realpayPayment($policy)
    {
        // dd($policy);
        $log = new \AlphaDirect\Http\Controllers\Admin\RealPayController();
        $addLog = $log->logEvent($policy->id, 1);
        $responseArr = array('ClientCreated' => 0, 'ContractCreated' => 0);
        $stringArr = \Opis\Closure\serialize($responseArr);

        $transaction = new Transaction();
        $transaction->policyNumber = $policy->policyNumber;
        $transaction->amount = $policy->premium;
        $transaction->customer_id = $policy->customer_id;
        $transaction->realPayTransaction_id = $policy->id;
        $transaction->referenceNumber = $policy->policyNumber;
        $transaction->status = "PENDING";
        $transaction->save();

        if ($addLog == true) {
            $payRequest = new RealpayPaymentRequest();
            $payRequest->policy_id = $policy->id;
            // $payRequest->first_premium = $policy->leftout_premium;
            $payRequest->first_premium = $policy->first_premium;
            $payRequest->premium = $policy->premium;
            $payRequest->billing_day = $policy->billing_day;
            $payRequest->billing_date = $policy->billingStartDate;
            $payRequest->first_premium_contract = null;
            $payRequest->contract = null;
            $payRequest->status = 0;
            $payRequest->response = $stringArr;
            $payRequest->frequency = $policy->premium_freq;
            $payRequest->clientCreated = 0;
            $payRequest->contractCreated = 0;
            $payRequest->save();

            return response()->json(['status' => '200', 'message' => 'Payment successful', 'PolicyNumber' => $policy->policyNumber], 200);
        } else {
            return response()->json(['status' => '401', 'message' => 'Payment log unsuccessful'], 401);
        }
    }
}
