<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use AlphaDirect\Policy;
use AlphaDirect\Vehicle;
use AlphaDirect\Models\Trip;
use AlphaDirect\Models\ObdScheduler;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CalculateObdPremium extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'obd:calculate-premium {--month= : Month to calculate (default: current month)} {--year= : Year to calculate (default: current year)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calculate OBD device policy premiums based on monthly vehicle travel distance';

    /**
     * Rate per kilometer for OBD policy (P2/Km)
     *
     * @var float
     */
    protected $ratePerKm = 2.00;

    /**
     * OBD Product ID
     *
     * @var int
     */
    protected $obdProductId = 13;

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
     * @return mixed
     */
    public function handle()
    {
        try {
            // Get month and year from options or use current
            $month = $this->option('month') ?? Carbon::now()->month;
            $year = $this->option('year') ?? Carbon::now()->year;

            $this->info("Starting OBD Premium Calculation for {$year}-{$month}");
            
            // Get all active OBD policies
            $obdPolicies = Policy::where('product_id', $this->obdProductId)
                ->where('status', 1) // Active policies only
                ->with('vehicle')
                ->get();

            if ($obdPolicies->isEmpty()) {
                $this->warn("No active OBD policies found.");
                return 0;
            }

            $this->info("Found {$obdPolicies->count()} active OBD policies.");

            $successCount = 0;
            $errorCount = 0;

            foreach ($obdPolicies as $policy) {
                try {
                    $this->processPolicy($policy, $year, $month);
                    $successCount++;
                } catch (\Exception $e) {
                    $errorCount++;
                    $this->error("Error processing policy {$policy->policyNumber}: " . $e->getMessage());
                    Log::error("OBD Premium Calculation Error", [
                        'policy_id' => $policy->id,
                        'policy_number' => $policy->policyNumber,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }

            $this->info("\n=== Calculation Complete ===");
            $this->info("Successfully processed: {$successCount}");
            $this->info("Errors: {$errorCount}");
            $this->info("============================\n");

            return 0;
        } catch (\Exception $e) {
            $this->error("Fatal error: " . $e->getMessage());
            Log::error("OBD Premium Calculation Fatal Error", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }

    /**
     * Process individual policy premium calculation
     *
     * @param Policy $policy
     * @param int $year
     * @param int $month
     * @return void
     */
    protected function processPolicy($policy, $year, $month)
    {
        // Get vehicle associated with the policy
        $vehicle = $policy->vehicle;

        if (!$vehicle) {
            $this->warn("Policy {$policy->policyNumber} has no vehicle associated.");
            return;
        }

        if (!$vehicle->vehiclePlate) {
            $this->warn("Policy {$policy->policyNumber} vehicle has no plate number.");
            return;
        }

        $vehiclePlate = $vehicle->vehiclePlate;
        $this->line("Processing Policy: {$policy->policyNumber} | Vehicle: {$vehiclePlate}");

        // Calculate date range for the month
        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = Carbon::create($year, $month, 1)->endOfMonth();

        // Get total distance from trips table
        // The trips table uses 'objectname' field for vehicle plate
        $totalDistanceMeters = Trip::where('objectname', $vehiclePlate)
            ->whereBetween('start_time', [$startDate, $endDate])
            ->sum('distance_m');

        // Convert meters to kilometers
        $totalDistanceKm = $totalDistanceMeters / 1000;
        $totalDistanceKm = round($totalDistanceKm, 2);

        // Calculate premium
        $calculatedPremium = ObdScheduler::calculatePremium($totalDistanceKm, $this->ratePerKm);

        // Prepare calculation details
        $calculationDetails = [
            'calculation_date' => Carbon::now()->toDateTimeString(),
            'period' => "{$year}-{$month}",
            'vehicle_plate' => $vehiclePlate,
            'total_distance_meters' => $totalDistanceMeters,
            'total_distance_km' => $totalDistanceKm,
            'rate_per_km' => $this->ratePerKm,
            'calculated_premium' => $calculatedPremium,
        ];

        // Store or update in obd_schedulers table
        $obdScheduler = ObdScheduler::updateOrCreate(
            [
                'policy_id' => $policy->id,
                'year' => $year,
                'month' => $month,
            ],
            [
                'policy_number' => $policy->policyNumber,
                'vehicle_plate' => $vehiclePlate,
                'distance_km' => $totalDistanceKm,
                'rate_per_km' => $this->ratePerKm,
                'calculated_premium' => $calculatedPremium,
                'calculation_details' => $calculationDetails,
            ]
        );

        // Update policy premium in policies table
        $policy->premium = $calculatedPremium;
        $policy->save();

        $this->info("  ✓ Distance: {$totalDistanceKm} km | Premium: P{$calculatedPremium}");

        // Log the activity
        Log::info("OBD Premium Calculated", [
            'policy_id' => $policy->id,
            'policy_number' => $policy->policyNumber,
            'vehicle_plate' => $vehiclePlate,
            'year' => $year,
            'month' => $month,
            'distance_km' => $totalDistanceKm,
            'premium' => $calculatedPremium,
        ]);
    }
}

