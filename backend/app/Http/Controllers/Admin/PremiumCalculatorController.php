<?php

namespace AlphaDirect\Http\Controllers\Admin;

use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Models\Trip;
use AlphaDirect\Models\PolicyPremiumCalculation;
use AlphaDirect\PpkRatingConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class PremiumCalculatorController extends Controller
{
    /**
     * Display the premium calculator page
     */
    public function index()
    {
        return view('admin.calculator.premium-calculator');
    }

    /**
     * Calculate premium based on policy number and month/year
     */
    public function calculateFromPolicy(Request $request)
    {
        try {
            $request->validate([
                'policy_number' => 'required|string',
                'year' => 'required|integer|min:2000|max:2100',
                'month' => 'required|integer|min:1|max:12',
            ]);

            // Get policy details
            $policy = \AlphaDirect\Policy::with(['vehicle', 'product'])
                ->where('policyNumber', $request->policy_number)
                ->first();

            if (!$policy) {
                return response()->json([
                    'success' => false,
                    'message' => 'Policy not found with this policy number.'
                ], 404);
            }

            // Get vehicle registration number
            if (!$policy->vehicle) {
                return response()->json([
                    'success' => false,
                    'message' => 'No vehicle found for this policy.'
                ], 404);
            }

            $vehiclePlate = $policy->vehicle->vehiclePlate;

            // Calculate date range from year and month (first day to last day of the month)
            $year = (int) $request->year;
            $month = (int) $request->month;
            $startDate = Carbon::create($year, $month, 1)->startOfDay();
            $endDate = $startDate->copy()->endOfMonth()->endOfDay();

            // Get monthly premium from policy
            // Since we're calculating from an existing policy, we use the policy's premium
            $monthlyPremium = $policy->premium ?? 500.00;
            
            Log::info('Using premium from policy table:', [
                'policy_number' => $request->policy_number,
                'product_id' => $policy->product_id,
                'premium' => $monthlyPremium
            ]);

            // Calculate base rate per km (Monthly Premium / 500km - fixed)
            $baseRatePerKm = $monthlyPremium / 500;

            // Get trip data with auto behaviour category
            $tripData = $this->getTripDataWithBehaviourCategory(
                $vehiclePlate,
                $startDate->format('Y-m-d'),
                $endDate->format('Y-m-d')
            );

            if ($tripData['trips_count'] == 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'No trip data found for this vehicle in the selected date range.'
                ], 404);
            }

            $distanceKm = $tripData['distance_km'];
            $behaviourCategory = $tripData['behaviour_category'];
            $averageOptidrive = $tripData['average_optidrive'];

            Log::info('Trip data retrieved for policy:', [
                'policy_number' => $request->policy_number,
                'vehicle_plate' => $vehiclePlate,
                'trips_count' => $tripData['trips_count'],
                'distance_km' => $distanceKm,
                'behaviour_category' => $behaviourCategory,
                'average_optidrive' => $averageOptidrive
            ]);

            // Get active PPK rating config
            $config = PpkRatingConfig::getActiveConfig();
            
            // Get behaviour surcharge multiplier from config if OptiDrive section is enabled
            $behaviourSurcharge = $this->getBehaviourSurcharge($behaviourCategory, $config);
            
            // Calculate final rate per km with surcharge
            $finalRatePerKm = $baseRatePerKm * (1 + $behaviourSurcharge);

            // Apply distance range logic only if Distance Range Configuration section is enabled
            $actualDistance = $distanceKm;
            $billableDistance = $distanceKm;
            $distanceAdjustment = null;
            $distanceRangeMin = null;
            $distanceRangeMax = null;
            
            // Check if distance_range_config section is enabled
            $distanceRangeEnabled = $config && isset($config->section_enabled['distance_range_config']) && $config->section_enabled['distance_range_config'];
            
            if ($distanceRangeEnabled) {
                $distanceRangeMin = $config->distance_range_min ?? 200;
                $distanceRangeMax = $config->distance_range_max ?? 1000;
                
                if ($distanceKm < $distanceRangeMin) {
                    $billableDistance = $distanceRangeMin;
                    $distanceAdjustment = "Distance below minimum ({$distanceRangeMin} km), using minimum for calculation";
                } elseif ($distanceKm > $distanceRangeMax) {
                    $billableDistance = $distanceRangeMax;
                    $distanceAdjustment = "Distance above maximum ({$distanceRangeMax} km), using maximum for calculation";
                }
            } else {
                // If disabled, use actual distance without adjustment
                $distanceAdjustment = "Distance Range Configuration is disabled, using actual distance";
            }

            // Calculate total premium using billable distance
            $totalPremium = $billableDistance * $finalRatePerKm;

            // Calculate premium breakdown using billable distance
            $basePremium = $billableDistance * $baseRatePerKm;
            $surchargeAmount = $totalPremium - $basePremium;

            // Build behaviour category description
            $optidriveEnabled = $config && isset($config->section_enabled['optidrive_modifiers']) && $config->section_enabled['optidrive_modifiers'];
            $optidriveSource = $optidriveEnabled ? " (from PPK Config)" : " (default)";
            $behaviourCategoryDescription = "Category {$behaviourCategory} = " . ($behaviourSurcharge * 100) . "%{$optidriveSource} (Auto-calculated from trip data: Avg OptiDrive = " . number_format($averageOptidrive, 4) . ")";

            // Check for duplicate entry (same policy, same month/year)
            $existingCalculation = PolicyPremiumCalculation::where('policy_id', $policy->id)
                ->where('start_date', $startDate->format('Y-m-d'))
                ->where('end_date', $endDate->format('Y-m-d'))
                ->first();

            if ($existingCalculation) {
                return response()->json([
                    'success' => false,
                    'message' => 'A premium calculation already exists for this policy for ' . $startDate->format('F Y') . '. Please select a different month.',
                    'duplicate' => true,
                    'existing_calculation_id' => $existingCalculation->id
                ], 409);
            }

            // Calculate days count (always one month)
            $daysCount = $startDate->diffInDays($endDate) + 1; // +1 to include both start and end days

            // Save calculation to database
            PolicyPremiumCalculation::create([
                'policy_id' => $policy->id,
                'policy_number' => $policy->policyNumber,
                'vehicle_id' => $policy->vehicle->id ?? null,
                'vehicle_plate' => $vehiclePlate,
                'product_id' => $policy->product_id,
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'days_count' => $daysCount,
                'trips_count' => $tripData['trips_count'],
                'actual_distance_km' => round($actualDistance, 2),
                'billable_distance_km' => round($billableDistance, 2),
                'distance_range_min' => $distanceRangeMin,
                'distance_range_max' => $distanceRangeMax,
                'distance_adjustment' => $distanceAdjustment,
                'behaviour_category' => $behaviourCategory,
                'average_optidrive' => $averageOptidrive,
                'behaviour_surcharge_percentage' => ($behaviourSurcharge * 100),
                'behaviour_category_auto_calculated' => true,
                'monthly_premium' => round($monthlyPremium, 2),
                'premium_source' => 'Policy Table',
                'base_rate_per_km' => round($baseRatePerKm, 4),
                'final_rate_per_km' => round($finalRatePerKm, 4),
                'base_premium' => round($basePremium, 2),
                'surcharge_amount' => round($surchargeAmount, 2),
                'total_premium' => round($totalPremium, 2),
                'calculation_breakdown' => [
                    'step_1' => "Policy Number: {$policy->policyNumber} (Product ID: {$policy->product_id})",
                    'step_2' => "Monthly Premium: P" . number_format($monthlyPremium, 2) . " (Source: Policy Table)",
                    'step_3' => "Base Rate per KM: P" . number_format($monthlyPremium, 2) . " ÷ 500km = P" . number_format($baseRatePerKm, 4),
                    'step_4' => "Behaviour Surcharge: " . $behaviourCategoryDescription,
                    'step_5' => "Final Rate per KM: P" . number_format($baseRatePerKm, 4) . " × " . (1 + $behaviourSurcharge) . " = P" . number_format($finalRatePerKm, 4),
                    'step_6' => $distanceRangeEnabled 
                        ? ($distanceAdjustment && strpos($distanceAdjustment, 'disabled') === false
                            ? "Distance Adjustment: Actual " . number_format($actualDistance, 2) . "km → Billable " . number_format($billableDistance, 2) . "km (Range: {$distanceRangeMin}-{$distanceRangeMax} km)"
                            : "Distance: " . number_format($billableDistance, 2) . "km (within range {$distanceRangeMin}-{$distanceRangeMax} km)")
                        : "Distance: " . number_format($billableDistance, 2) . "km (Distance Range Configuration disabled, using actual distance)",
                    'step_7' => "Total Premium: " . number_format($billableDistance, 2) . "km × P" . number_format($finalRatePerKm, 4) . " = P" . number_format($totalPremium, 2)
                ],
                'calculated_by' => Auth::id(),
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'policy_number' => $policy->policyNumber,
                    'policy_product_id' => $policy->product_id,
                    'vehicle_plate' => $vehiclePlate,
                    'year' => $year,
                    'month' => $month,
                    'month_name' => $startDate->format('F'),
                    'date_range' => [
                        'start' => $startDate->format('Y-m-d'),
                        'end' => $endDate->format('Y-m-d'),
                        'days' => $daysCount
                    ],
                    'monthly_premium' => round($monthlyPremium, 2),
                    'premium_source' => 'Policy Table',
                    'base_rate_per_km' => round($baseRatePerKm, 4),
                    'behaviour_category' => $behaviourCategory,
                    'behaviour_surcharge_percentage' => ($behaviourSurcharge * 100),
                    'behaviour_category_auto_calculated' => true,
                    'average_optidrive' => $averageOptidrive,
                    'trips_count' => $tripData['trips_count'],
                    'final_rate_per_km' => round($finalRatePerKm, 4),
                    'actual_distance_km' => round($actualDistance, 2),
                    'billable_distance_km' => round($billableDistance, 2),
                    'distance_range_min' => $distanceRangeMin,
                    'distance_range_max' => $distanceRangeMax,
                    'distance_adjustment' => $distanceAdjustment,
                    'distance_km' => round($billableDistance, 2),
                    'base_premium' => round($basePremium, 2),
                    'surcharge_amount' => round($surchargeAmount, 2),
                    'total_premium' => round($totalPremium, 2),
                    'calculation_breakdown' => [
                        'step_1' => "Policy Number: {$policy->policyNumber} (Product ID: {$policy->product_id})",
                        'step_2' => "Monthly Premium: P" . number_format($monthlyPremium, 2) . " (Source: Policy Table)",
                        'step_3' => "Base Rate per KM: P" . number_format($monthlyPremium, 2) . " ÷ 500km = P" . number_format($baseRatePerKm, 4),
                        'step_4' => "Behaviour Surcharge: " . $behaviourCategoryDescription,
                        'step_5' => "Final Rate per KM: P" . number_format($baseRatePerKm, 4) . " × " . (1 + $behaviourSurcharge) . " = P" . number_format($finalRatePerKm, 4),
                        'step_6' => $distanceRangeEnabled 
                            ? ($distanceAdjustment && strpos($distanceAdjustment, 'disabled') === false
                                ? "Distance Adjustment: Actual " . number_format($actualDistance, 2) . "km → Billable " . number_format($billableDistance, 2) . "km (Range: {$distanceRangeMin}-{$distanceRangeMax} km)"
                                : "Distance: " . number_format($billableDistance, 2) . "km (within range {$distanceRangeMin}-{$distanceRangeMax} km)")
                            : "Distance: " . number_format($billableDistance, 2) . "km (Distance Range Configuration disabled, using actual distance)",
                        'step_7' => "Total Premium: " . number_format($billableDistance, 2) . "km × P" . number_format($finalRatePerKm, 4) . " = P" . number_format($totalPremium, 2)
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Policy Premium Calculator Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error calculating premium: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calculate premium based on trip data and behaviour category
     */
    public function calculate(Request $request)
    {
        //try {
            $request->validate([
                'vehicle_plate' => 'nullable|string',
                'distance_km' => 'required|numeric|min:0',
                'behaviour_category' => 'required|in:A,B,C,D',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
            ]);

            // Get monthly premium from rate.alphadirect.co.bw
            $monthlyPremium = $this->fetchMonthlyPremium($request);
            
            if (!$monthlyPremium || $monthlyPremium <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unable to fetch monthly premium from rating system. Using default value.',
                    'monthlyPremium' => 500.00, // Default fallback
                ], 400);
            }

            // Calculate base rate per km (Monthly Premium / 500km)
            $baseRatePerKm = $monthlyPremium / 500;

            // Get distance and behaviour category
            $distanceKm = floatval($request->distance_km);
            $behaviourCategory = $request->behaviour_category;
            $tripDataUsed = false;
            $averageOptidrive = null;
            
            // If vehicle plate and dates provided, fetch from trip table with auto behaviour category
            if ($request->vehicle_plate && $request->start_date && $request->end_date) {
                $tripData = $this->getTripDataWithBehaviourCategory(
                    $request->vehicle_plate,
                    $request->start_date,
                    $request->end_date
                );
                $distanceKm = $tripData['distance_km'];
                $behaviourCategory = $tripData['behaviour_category'];
                $averageOptidrive = $tripData['average_optidrive'];
                $tripDataUsed = true;
                
                Log::info('Auto-calculated behaviour category from trip data:', [
                    'vehicle_plate' => $request->vehicle_plate,
                    'trips_count' => $tripData['trips_count'],
                    'average_optidrive' => $averageOptidrive,
                    'behaviour_category' => $behaviourCategory
                ]);
            }

            // Get active PPK rating config
            $config = PpkRatingConfig::getActiveConfig();
            
            // Get behaviour surcharge multiplier from config if OptiDrive section is enabled
            $behaviourSurcharge = $this->getBehaviourSurcharge($behaviourCategory, $config);
            
            // Calculate final rate per km with surcharge
            $finalRatePerKm = $baseRatePerKm * (1 + $behaviourSurcharge);

            // Apply distance range logic only if Distance Range Configuration section is enabled
            $actualDistance = $distanceKm;
            $billableDistance = $distanceKm;
            $distanceAdjustment = null;
            $distanceRangeMin = null;
            $distanceRangeMax = null;
            
            // Check if distance_range_config section is enabled
            $distanceRangeEnabled = $config && isset($config->section_enabled['distance_range_config']) && $config->section_enabled['distance_range_config'];
            
            if ($distanceRangeEnabled) {
                $distanceRangeMin = $config->distance_range_min ?? 200;
                $distanceRangeMax = $config->distance_range_max ?? 1000;
                
                if ($distanceKm < $distanceRangeMin) {
                    $billableDistance = $distanceRangeMin;
                    $distanceAdjustment = "Distance below minimum ({$distanceRangeMin} km), using minimum for calculation";
                } elseif ($distanceKm > $distanceRangeMax) {
                    $billableDistance = $distanceRangeMax;
                    $distanceAdjustment = "Distance above maximum ({$distanceRangeMax} km), using maximum for calculation";
                }
            } else {
                // If disabled, use actual distance without adjustment
                $distanceAdjustment = "Distance Range Configuration is disabled, using actual distance";
            }

            // Calculate total premium using billable distance
            $totalPremium = $billableDistance * $finalRatePerKm;

            // Calculate premium breakdown using billable distance
            $basePremium = $billableDistance * $baseRatePerKm;
            $surchargeAmount = $totalPremium - $basePremium;

            // Build behaviour category description
            $optidriveEnabled = $config && isset($config->section_enabled['optidrive_modifiers']) && $config->section_enabled['optidrive_modifiers'];
            $optidriveSource = $optidriveEnabled ? " (from PPK Config)" : " (default)";
            $behaviourCategoryDescription = "Category {$behaviourCategory} = " . ($behaviourSurcharge * 100) . "%{$optidriveSource}";
            if ($tripDataUsed && $averageOptidrive !== null) {
                $behaviourCategoryDescription .= " (Auto-calculated from trip data: Avg OptiDrive = " . number_format($averageOptidrive, 4) . ")";
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'monthly_premium' => round($monthlyPremium, 2),
                    'base_rate_per_km' => round($baseRatePerKm, 4),
                    'behaviour_category' => $behaviourCategory,
                    'behaviour_surcharge_percentage' => ($behaviourSurcharge * 100),
                    'behaviour_category_auto_calculated' => $tripDataUsed,
                    'average_optidrive' => $averageOptidrive,
                    'final_rate_per_km' => round($finalRatePerKm, 4),
                    'actual_distance_km' => round($actualDistance, 2),
                    'billable_distance_km' => round($billableDistance, 2),
                    'distance_range_min' => $distanceRangeMin,
                    'distance_range_max' => $distanceRangeMax,
                    'distance_adjustment' => $distanceAdjustment,
                    'distance_km' => round($billableDistance, 2), // For backward compatibility
                    'base_premium' => round($basePremium, 2),
                    'surcharge_amount' => round($surchargeAmount, 2),
                    'total_premium' => round($totalPremium, 2),
                    'calculation_breakdown' => [
                        'step_1' => "Monthly Premium: P" . number_format($monthlyPremium, 2),
                        'step_2' => "Base Rate per KM: P" . number_format($monthlyPremium, 2) . " ÷ 500km = P" . number_format($baseRatePerKm, 4),
                        'step_3' => "Behaviour Surcharge: " . $behaviourCategoryDescription,
                        'step_4' => "Final Rate per KM: P" . number_format($baseRatePerKm, 4) . " × " . (1 + $behaviourSurcharge) . " = P" . number_format($finalRatePerKm, 4),
                        'step_5' => $distanceRangeEnabled 
                            ? ($distanceAdjustment && strpos($distanceAdjustment, 'disabled') === false
                                ? "Distance Adjustment: Actual " . number_format($actualDistance, 2) . "km → Billable " . number_format($billableDistance, 2) . "km (Range: {$distanceRangeMin}-{$distanceRangeMax} km)"
                                : "Distance: " . number_format($billableDistance, 2) . "km (within range {$distanceRangeMin}-{$distanceRangeMax} km)")
                            : "Distance: " . number_format($billableDistance, 2) . "km (Distance Range Configuration disabled, using actual distance)",
                        'step_6' => "Total Premium: " . number_format($billableDistance, 2) . "km × P" . number_format($finalRatePerKm, 4) . " = P" . number_format($totalPremium, 2),
                    ]
                ]
            ]);

        // } catch (\Exception $e) {
        //     Log::error('Premium Calculator Error: ' . $e->getMessage(), [
        //         'trace' => $e->getTraceAsString()
        //     ]);

        //     return response()->json([
        //         'success' => false,
        //         'message' => 'Error calculating premium: ' . $e->getMessage()
        //     ], 500);
        // }
    }

    /**
     * Fetch monthly premium from rate.alphadirect.co.bw
     */
    private function fetchMonthlyPremium(Request $request)
    {
        try {
            // Get active PPK rating config as fallback
            $config = PpkRatingConfig::getActiveConfig();
            $defaultPremium = $config ? $config->base_monthly_premium : 500.00;

            // Try to fetch from rate.alphadirect.co.bw API
            $rateUrl = env('RATE_URL', 'https://rate.alphadirect.co.bw/') . 'api/calculation';
            
            // Get import status and convert to Yes/No format (matching motorComprehensive format)
            $importStatus = $request->input('import_status');
            $status = ($importStatus == '1' || $importStatus == 1) ? 'Yes' : 'No';
            
            // Get gender and convert to Male/Female format
            $genderInput = $request->input('gender');
            if ($genderInput === '1' || $genderInput === 1 || strtolower($genderInput) === 'male') {
                $gender = 'Male';
            } else {
                $gender = 'Female';
            }
            
            // Prepare marital status
            $maritalStatusInput = $request->input('marital_status', 'Never Married');
            if ($maritalStatusInput === '1' || $maritalStatusInput === '5' || $maritalStatusInput === '6') {
                $marital_status = 'Never Married';
            } elseif ($maritalStatusInput === '2' || $maritalStatusInput === '3' || $maritalStatusInput === '4') {
                $marital_status = 'Married Before';
            } else {
                $marital_status = $maritalStatusInput;
            }
            
            // Format date of birth to d/m/Y format (e.g., 12/11/2007)
            $dobInput = $request->input('dob', '1990-01-01');
            try {
                $dobDate = Carbon::parse($dobInput);
                $dob = $dobDate->format('d/m/Y');
            } catch (\Exception $e) {
                $dob = '01/01/1990'; // Default fallback
            }
            
            // Prepare data for rating API - EXACTLY as motorComprehensive does it
            $ratingData = [
                'make'               => $request->input('make', 'Toyota'),
                'manufacturing_year' => $request->input('year', date('Y')),
                'sum_insured'        => $request->input('sum_insured', 25000),
                'status'             => $status,
                'marital_status'     => $marital_status,
                'gender'             => $gender,
                'dob'                => $dob,
                'claim_count'        => $request->input('claim_count', 0),
                'omang'              => $request->input('omang', ''),
                'passport'           => $request->input('passport', ''),
                'model'              => $request->input('model', ''),
            ];
            // Try to fetch from API using cURL - Send as form data, not JSON
            $ch = curl_init($rateUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($ratingData)); // Form data, not JSON
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            Log::info('Rating API Request:', [
                'url' => $rateUrl,
                'data' => $ratingData,
                'http_code' => $httpCode,
                'response' => substr($response, 0, 500),
                'curl_error' => $curlError
            ]);
            
            if ($httpCode === 200 && !empty($response)) {
                $responseData = json_decode($response, true);
                
                if (isset($responseData['success']) && $responseData['success'] == 1) {
                    // Use monthly_premium from API if available, otherwise calculate from result
                    if (isset($responseData['monthly_premium'])) {
                        $monthlyPremium = floatval($responseData['monthly_premium']);
                        Log::info('Monthly Premium from API:', [
                            'monthly_premium' => $monthlyPremium,
                            'annual_premium' => $responseData['result'] ?? null
                        ]);
                        return $monthlyPremium;
                    } elseif (isset($responseData['result'])) {
                        $annualPremium = floatval($responseData['result']);
                        $monthlyPremium = $annualPremium / 12;
                        Log::info('Monthly Premium calculated from annual:', [
                            'annual_premium' => $annualPremium,
                            'monthly_premium' => $monthlyPremium
                        ]);
                        return $monthlyPremium;
                    }
                }
                
                Log::warning('Rating API response invalid:', ['response' => $responseData]);
            }

            // If API fails, return default from config
            Log::warning('Rating API failed, using default premium', [
                'http_code' => $httpCode,
                'error' => $curlError
            ]);
            return $defaultPremium;

        } catch (\Exception $e) {
            Log::warning('Failed to fetch premium from rate API: ' . $e->getMessage());
            
            // Return default from config
            $config = PpkRatingConfig::getActiveConfig();
            return $config ? $config->base_monthly_premium : 500.00;
        }
    }

    /**
     * Get behaviour surcharge percentage based on category
     * Uses PPK rating config if OptiDrive Score Modifiers section is enabled
     */
    private function getBehaviourSurcharge($category, $config = null)
    {
        // Default surcharges (fallback if config not available or section disabled)
        $defaultSurcharges = [
            'A' => 0.00,  // Normal rate (0% surcharge)
            'B' => 0.20,  // 20% surcharge
            'C' => 0.40,  // 40% surcharge
            'D' => 0.60,  // 60% surcharge
        ];

        // Check if OptiDrive Score Modifiers section is enabled
        $optidriveEnabled = $config && isset($config->section_enabled['optidrive_modifiers']) && $config->section_enabled['optidrive_modifiers'];
        
        if ($optidriveEnabled && $config && isset($config->optidrive_modifiers)) {
            $optidriveModifiers = $config->optidrive_modifiers;
            
            // Map category to config key
            $categoryMap = [
                'A' => 'category_a',
                'B' => 'category_b',
                'C' => 'category_c',
                'D' => 'category_d',
            ];
            
            $configKey = $categoryMap[$category] ?? null;
            
            if ($configKey && isset($optidriveModifiers[$configKey])) {
                $modifier = $optidriveModifiers[$configKey];
                
                // Handle both old format (direct value) and new format (array with value)
                if (is_array($modifier) && isset($modifier['value'])) {
                    return (float) $modifier['value'];
                } elseif (is_numeric($modifier)) {
                    return (float) $modifier;
                }
            }
        }

        // Fallback to default surcharges
        return $defaultSurcharges[$category] ?? 0.00;
    }

    /**
     * Get total trip distance from trips table
     */
    private function getTripDistance($vehiclePlate, $startDate, $endDate)
    {
        try {
            $start = Carbon::parse($startDate)->startOfDay();
            $end = Carbon::parse($endDate)->endOfDay();

            // Get total distance from trips table (distance_m field is in meters)
            $totalDistanceMeters = Trip::where('objectname', $vehiclePlate)
                ->whereBetween('start_time', [$start, $end])
                ->sum('distance_m');

            // Convert meters to kilometers
            $totalDistanceKm = $totalDistanceMeters / 1000;

            return round($totalDistanceKm, 2);

        } catch (\Exception $e) {
            Log::error('Error fetching trip distance: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get trip data and calculate behaviour category from optidrive_indicator
     */
    private function getTripDataWithBehaviourCategory($vehiclePlate, $startDate, $endDate)
    {
        try {
            $start = Carbon::parse($startDate)->startOfDay();
            $end = Carbon::parse($endDate)->endOfDay();

            // Get trips with distance and optidrive_indicator
            $trips = Trip::where('objectname', $vehiclePlate)
                ->whereBetween('start_time', [$start, $end])
                ->select('distance_m', 'optidrive_indicator')
                ->get();

            if ($trips->isEmpty()) {
                return [
                    'distance_km' => 0,
                    'behaviour_category' => 'D',
                    'average_optidrive' => 0,
                    'trips_count' => 0
                ];
            }

            // Calculate total distance
            $totalDistanceMeters = $trips->sum('distance_m');
            $totalDistanceKm = $totalDistanceMeters / 1000;

            // Calculate average optidrive_indicator
            $averageOptidrive = $trips->avg('optidrive_indicator');

            // Determine behaviour category based on average optidrive_indicator
            $behaviourCategory = $this->getBehaviourCategoryFromOptidrive($averageOptidrive);

            return [
                'distance_km' => round($totalDistanceKm, 2),
                'behaviour_category' => $behaviourCategory,
                'average_optidrive' => round($averageOptidrive, 4),
                'trips_count' => $trips->count()
            ];

        } catch (\Exception $e) {
            Log::error('Error fetching trip data with behaviour category: ' . $e->getMessage());
            return [
                'distance_km' => 0,
                'behaviour_category' => 'D',
                'average_optidrive' => 0,
                'trips_count' => 0
            ];
        }
    }

    /**
     * Map optidrive_indicator average to behaviour category
     * 
     * @param float $averageOptidrive
     * @return string Category (A, B, C, or D)
     */
    private function getBehaviourCategoryFromOptidrive($averageOptidrive)
    {
        if ($averageOptidrive >= 0.8 && $averageOptidrive <= 1.0) {
            return 'A';  // Excellent Driver (0.8 to 1.0)
        } elseif ($averageOptidrive >= 0.6 && $averageOptidrive < 0.8) {
            return 'B';  // Good Driver (0.6 to <0.8)
        } elseif ($averageOptidrive >= 0.4 && $averageOptidrive < 0.6) {
            return 'C';  // Average Driver (0.4 to <0.6)
        } else {
            return 'D';  // High-Risk Driver (<0.4)
        }
    }

    /**
     * Get trip data for a vehicle
     */
    public function getTripData(Request $request)
    {
        try {
            $request->validate([
                'vehicle_plate' => 'required|string',
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
            ]);

            $start = Carbon::parse($request->start_date)->startOfDay();
            $end = Carbon::parse($request->end_date)->endOfDay();

            $trips = Trip::where('objectname', $request->vehicle_plate)
                ->whereBetween('start_time', [$start, $end])
                ->select([
                    'start_time',
                    'end_time',
                    'distance_m',
                    'duration_s',
                    'start_postext',
                    'end_postext',
                    'avg_speed',
                    'max_speed',
                    'optidrive_indicator'
                ])
                ->orderBy('start_time', 'desc')
                ->get();

            $totalDistanceKm = $trips->sum('distance_m') / 1000;
            $totalTrips = $trips->count();
            
            // Calculate average optidrive_indicator
            $averageOptidrive = $trips->avg('optidrive_indicator');
            
            // Determine behaviour category
            $behaviourCategory = $this->getBehaviourCategoryFromOptidrive($averageOptidrive);

            return response()->json([
                'success' => true,
                'data' => [
                    'trips' => $trips,
                    'summary' => [
                        'total_trips' => $totalTrips,
                        'total_distance_km' => round($totalDistanceKm, 2),
                        'average_trip_distance_km' => $totalTrips > 0 ? round($totalDistanceKm / $totalTrips, 2) : 0,
                        'average_optidrive' => round($averageOptidrive, 4),
                        'behaviour_category' => $behaviourCategory
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching trip data: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error fetching trip data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Fetch previous premium calculations for a policy
     */
    public function getPolicyCalculations(Request $request)
    {
        try {
            $request->validate([
                'policy_number' => 'required|string',
            ]);

            $calculations = PolicyPremiumCalculation::where('policy_number', $request->policy_number)
                ->orderBy('created_at', 'desc')
                ->limit(50)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $calculations->map(function ($calc) {
                    return [
                        'id' => $calc->id,
                        'policy_number' => $calc->policy_number,
                        'vehicle_plate' => $calc->vehicle_plate,
                        'date_range' => [
                            'start' => $calc->start_date->format('Y-m-d'),
                            'end' => $calc->end_date->format('Y-m-d'),
                            'days' => $calc->days_count
                        ],
                        'trips_count' => $calc->trips_count,
                        'actual_distance_km' => (float) $calc->actual_distance_km,
                        'billable_distance_km' => (float) $calc->billable_distance_km,
                        'distance_range_min' => $calc->distance_range_min,
                        'distance_range_max' => $calc->distance_range_max,
                        'distance_adjustment' => $calc->distance_adjustment,
                        'behaviour_category' => $calc->behaviour_category,
                        'average_optidrive' => (float) $calc->average_optidrive,
                        'behaviour_surcharge_percentage' => (float) $calc->behaviour_surcharge_percentage,
                        'behaviour_category_auto_calculated' => $calc->behaviour_category_auto_calculated,
                        'monthly_premium' => (float) $calc->monthly_premium,
                        'premium_source' => $calc->premium_source,
                        'base_rate_per_km' => (float) $calc->base_rate_per_km,
                        'final_rate_per_km' => (float) $calc->final_rate_per_km,
                        'base_premium' => (float) $calc->base_premium,
                        'surcharge_amount' => (float) $calc->surcharge_amount,
                        'total_premium' => (float) $calc->total_premium,
                        'calculation_breakdown' => $calc->calculation_breakdown,
                        'policy_product_id' => $calc->product_id,
                        'created_at' => $calc->created_at->format('Y-m-d H:i:s'),
                    ];
                })
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching policy calculations: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error fetching calculations: ' . $e->getMessage()
            ], 500);
        }
    }
}

