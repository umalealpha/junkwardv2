<?php

namespace AlphaDirect\Http\Controllers;

use AlphaDirect\PpkRatingConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PpkRatingConfigController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $configs = PpkRatingConfig::orderBy('created_at', 'desc')->paginate(10);
        return view('ppk-rating-config.index', compact('configs'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $defaultConfig = PpkRatingConfig::getDefaultConfig();
        return view('ppk-rating-config.create', compact('defaultConfig'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'config_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'base_monthly_premium' => 'required|numeric|min:0',
            'base_rate_per_km' => 'required|numeric|min:0',
            'distance_range_min' => 'required|integer|min:0',
            'distance_range_max' => 'required|integer|min:0|gte:distance_range_min',
            'per_trip_cap_min' => 'required|numeric|min:0',
            'per_trip_cap_max' => 'required|numeric|min:0',
            'monthly_cap_min' => 'required|numeric|min:0',
            'monthly_cap_max' => 'required|numeric|min:0',
            'night_modifier' => 'required|numeric',
            'rain_modifier' => 'required|numeric',
            'gender_enabled' => 'boolean',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        try {
            DB::beginTransaction();

            // If setting as active, deactivate others
            if ($request->is_active) {
                PpkRatingConfig::where('is_active', true)->update(['is_active' => false]);
            }

            $config = new PpkRatingConfig();
            $config->config_name = $request->config_name;
            $config->description = $request->description;
            $config->base_monthly_premium = $request->base_monthly_premium;
            $config->base_rate_per_km = $request->base_rate_per_km;
            $config->distance_range_min = $request->distance_range_min;
            $config->distance_range_max = $request->distance_range_max;
            $config->per_trip_cap_min = $request->per_trip_cap_min;
            $config->per_trip_cap_max = $request->per_trip_cap_max;
            $config->monthly_cap_min = $request->monthly_cap_min;
            $config->monthly_cap_max = $request->monthly_cap_max;
            $config->night_modifier = $request->night_modifier;
            $config->rain_modifier = $request->rain_modifier;
            
            // Section enabled status - convert checkbox values to boolean
            $sectionEnabled = [];
            $enabledSections = $request->input('section_enabled', []);
            $allSections = [
                'distance_range_config',
                'caps_config',
                'basic_risk_modifiers',
                'optidrive_modifiers',
                'driver_age_modifiers',
                'car_age_modifiers',
                'vehicle_type_modifiers',
                'driver_risk_modifiers',
                'territory_modifiers',
                'trip_type_modifiers',
                'usage_frequency_modifiers',
                'policy_tenure_modifiers',
                'claims_history_modifiers',
                'event_penalty_rates',
                'weather_severity_modifiers',
                'night_segment_modifiers',
                'other_modifiers'
            ];
            
            foreach ($allSections as $section) {
                $sectionEnabled[$section] = isset($enabledSections[$section]) && $enabledSections[$section] == '1';
            }
            $config->section_enabled = $sectionEnabled;
            
            // JSON fields
            $config->optidrive_modifiers = $this->parseModifiers($request->input('optidrive_modifiers', []));
            $config->driver_age_modifiers = $this->parseModifiers($request->input('driver_age_modifiers', []));
            $config->car_age_modifiers = $this->parseModifiers($request->input('car_age_modifiers', []));
            $config->vehicle_type_modifiers = $this->parseModifiers($request->input('vehicle_type_modifiers', []));
            $config->driver_risk_modifiers = $this->parseModifiers($request->input('driver_risk_modifiers', []));
            $config->territory_modifiers = $this->parseModifiers($request->input('territory_modifiers', []));
            $config->trip_type_modifiers = $this->parseModifiers($request->input('trip_type_modifiers', []));
            $config->usage_frequency_modifiers = $this->parseModifiers($request->input('usage_frequency_modifiers', []));
            $config->policy_tenure_modifiers = $this->parseModifiers($request->input('policy_tenure_modifiers', []));
            $config->claims_history_modifiers = $this->parseModifiers($request->input('claims_history_modifiers', []));
            
            $config->gender_enabled = $request->has('gender_enabled');
            $config->gender_modifiers = $this->parseModifiers($request->input('gender_modifiers', []));
            
            $config->speeding_penalty_per_event = $request->speeding_penalty_per_event;
            $config->harsh_acceleration_rate = $request->harsh_acceleration_rate;
            $config->idle_time_rate = $request->idle_time_rate;
            $config->high_revving_rate = $request->high_revving_rate;
            
            $config->weather_severity_modifiers = $this->parseModifiers($request->input('weather_severity_modifiers', []));
            $config->night_segment_modifiers = $this->parseModifiers($request->input('night_segment_modifiers', []));
            
            $config->congestion_modifier = $request->congestion_modifier;
            $config->seasonal_modifier = $request->seasonal_modifier;
            $config->adas_discount = $request->adas_discount;
            $config->roadworthiness_discount = $request->roadworthiness_discount;
            $config->safe_driver_cashback = $request->safe_driver_cashback;
            
            $config->is_active = $request->has('is_active');
            $config->save();

            DB::commit();

            return redirect()->route('ppk-rating-config.index')
                ->with('success', 'PPK Rating Configuration created successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()
                ->with('error', 'Error creating configuration: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(PpkRatingConfig $ppkRatingConfig)
    {
        return view('ppk-rating-config.show', compact('ppkRatingConfig'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(PpkRatingConfig $ppkRatingConfig)
    {
        return view('ppk-rating-config.edit', compact('ppkRatingConfig'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, PpkRatingConfig $ppkRatingConfig)
    {
        $validator = Validator::make($request->all(), [
            'config_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'base_monthly_premium' => 'required|numeric|min:0',
            'base_rate_per_km' => 'required|numeric|min:0',
            'distance_range_min' => 'required|integer|min:0',
            'distance_range_max' => 'required|integer|min:0|gte:distance_range_min',
            'per_trip_cap_min' => 'required|numeric|min:0',
            'per_trip_cap_max' => 'required|numeric|min:0',
            'monthly_cap_min' => 'required|numeric|min:0',
            'monthly_cap_max' => 'required|numeric|min:0',
            'night_modifier' => 'required|numeric',
            'rain_modifier' => 'required|numeric',
            'gender_enabled' => 'boolean',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        try {
            DB::beginTransaction();

            // If setting as active, deactivate others
            if ($request->is_active && !$ppkRatingConfig->is_active) {
                PpkRatingConfig::where('is_active', true)
                    ->where('id', '!=', $ppkRatingConfig->id)
                    ->update(['is_active' => false]);
            }

            $ppkRatingConfig->config_name = $request->config_name;
            $ppkRatingConfig->description = $request->description;
            $ppkRatingConfig->base_monthly_premium = $request->base_monthly_premium;
            $ppkRatingConfig->base_rate_per_km = $request->base_rate_per_km;
            $ppkRatingConfig->distance_range_min = $request->distance_range_min;
            $ppkRatingConfig->distance_range_max = $request->distance_range_max;
            $ppkRatingConfig->per_trip_cap_min = $request->per_trip_cap_min;
            $ppkRatingConfig->per_trip_cap_max = $request->per_trip_cap_max;
            $ppkRatingConfig->monthly_cap_min = $request->monthly_cap_min;
            $ppkRatingConfig->monthly_cap_max = $request->monthly_cap_max;
            $ppkRatingConfig->night_modifier = $request->night_modifier;
            $ppkRatingConfig->rain_modifier = $request->rain_modifier;
            
            // Section enabled status - convert checkbox values to boolean
            $sectionEnabled = [];
            $enabledSections = $request->input('section_enabled', []);
            $allSections = [
                'distance_range_config',
                'caps_config',
                'basic_risk_modifiers',
                'optidrive_modifiers',
                'driver_age_modifiers',
                'car_age_modifiers',
                'vehicle_type_modifiers',
                'driver_risk_modifiers',
                'territory_modifiers',
                'trip_type_modifiers',
                'usage_frequency_modifiers',
                'policy_tenure_modifiers',
                'claims_history_modifiers',
                'event_penalty_rates',
                'weather_severity_modifiers',
                'night_segment_modifiers',
                'other_modifiers'
            ];
            
            foreach ($allSections as $section) {
                $sectionEnabled[$section] = isset($enabledSections[$section]) && $enabledSections[$section] == '1';
            }
            $ppkRatingConfig->section_enabled = $sectionEnabled;
            
            // JSON fields
            $ppkRatingConfig->optidrive_modifiers = $this->parseModifiers($request->input('optidrive_modifiers', []));
            $ppkRatingConfig->driver_age_modifiers = $this->parseModifiers($request->input('driver_age_modifiers', []));
            $ppkRatingConfig->car_age_modifiers = $this->parseModifiers($request->input('car_age_modifiers', []));
            $ppkRatingConfig->vehicle_type_modifiers = $this->parseModifiers($request->input('vehicle_type_modifiers', []));
            $ppkRatingConfig->driver_risk_modifiers = $this->parseModifiers($request->input('driver_risk_modifiers', []));
            $ppkRatingConfig->territory_modifiers = $this->parseModifiers($request->input('territory_modifiers', []));
            $ppkRatingConfig->trip_type_modifiers = $this->parseModifiers($request->input('trip_type_modifiers', []));
            $ppkRatingConfig->usage_frequency_modifiers = $this->parseModifiers($request->input('usage_frequency_modifiers', []));
            $ppkRatingConfig->policy_tenure_modifiers = $this->parseModifiers($request->input('policy_tenure_modifiers', []));
            $ppkRatingConfig->claims_history_modifiers = $this->parseModifiers($request->input('claims_history_modifiers', []));
            
            $ppkRatingConfig->gender_enabled = $request->has('gender_enabled');
            $ppkRatingConfig->gender_modifiers = $this->parseModifiers($request->input('gender_modifiers', []));
            
            $ppkRatingConfig->speeding_penalty_per_event = $request->speeding_penalty_per_event;
            $ppkRatingConfig->harsh_acceleration_rate = $request->harsh_acceleration_rate;
            $ppkRatingConfig->idle_time_rate = $request->idle_time_rate;
            $ppkRatingConfig->high_revving_rate = $request->high_revving_rate;
            
            $ppkRatingConfig->weather_severity_modifiers = $this->parseModifiers($request->input('weather_severity_modifiers', []));
            $ppkRatingConfig->night_segment_modifiers = $this->parseModifiers($request->input('night_segment_modifiers', []));
            
            $ppkRatingConfig->congestion_modifier = $request->congestion_modifier;
            $ppkRatingConfig->seasonal_modifier = $request->seasonal_modifier;
            $ppkRatingConfig->adas_discount = $request->adas_discount;
            $ppkRatingConfig->roadworthiness_discount = $request->roadworthiness_discount;
            $ppkRatingConfig->safe_driver_cashback = $request->safe_driver_cashback;
            
            $ppkRatingConfig->is_active = $request->has('is_active');
            $ppkRatingConfig->save();

            DB::commit();

            return redirect()->route('ppk-rating-config.index')
                ->with('success', 'PPK Rating Configuration updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()
                ->with('error', 'Error updating configuration: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PpkRatingConfig $ppkRatingConfig)
    {
        try {
            $ppkRatingConfig->delete();
            return redirect()->route('ppk-rating-config.index')
                ->with('success', 'PPK Rating Configuration deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Error deleting configuration: ' . $e->getMessage());
        }
    }

    /**
     * Parse modifier arrays from request.
     */
    private function parseModifiers($modifiers)
    {
        if (is_array($modifiers)) {
            $result = [];
            foreach ($modifiers as $key => $value) {
                if (!empty($value)) {
                    $result[$key] = (float) $value;
                }
            }
            return $result;
        }
        return [];
    }


    /**
     * Activate a configuration.
     */
    public function activate(PpkRatingConfig $ppkRatingConfig)
    {
        try {
            DB::beginTransaction();
            
            // Deactivate all configs
            PpkRatingConfig::where('is_active', true)->update(['is_active' => false]);
            
            // Activate this one
            $ppkRatingConfig->is_active = true;
            $ppkRatingConfig->save();
            
            DB::commit();

            return redirect()->route('ppk-rating-config.index')
                ->with('success', 'Configuration activated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()
                ->with('error', 'Error activating configuration: ' . $e->getMessage());
        }
    }

    /**
     * Duplicate a configuration.
     */
    public function duplicate(PpkRatingConfig $ppkRatingConfig)
    {
        try {
            $newConfig = $ppkRatingConfig->replicate();
            $newConfig->config_name = $ppkRatingConfig->config_name . ' (Copy)';
            $newConfig->is_active = false;
            $newConfig->save();

            return redirect()->route('ppk-rating-config.edit', $newConfig)
                ->with('success', 'Configuration duplicated successfully.');
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Error duplicating configuration: ' . $e->getMessage());
        }
    }
}

