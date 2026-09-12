<?php

namespace AlphaDirect\Http\Controllers\Api;

use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Models\State;
use AlphaDirect\Models\City;
use AlphaDirect\Lookup;
use Illuminate\Http\Request;

class RiskAddressController extends Controller
{
    /**
     * Get all states for the risk address form
     */
    public function getStates()
    {
        try {
            $states = State::where('country_id', 28)
                ->select('id', 'name')
                ->orderBy('name')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $states
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch states: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get cities for a specific state
     */
    public function getCitiesByState($stateId)
    {
        try {
            $cities = City::where('state_id', $stateId)
                ->select('id', 'name')
                ->orderBy('name')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $cities
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch cities: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all construction types
     */
    public function getConstructionTypes()
    {
        try {
            $constructionTypes = Lookup::where('key', 'risk_construction_type')
                ->get()
                ->map(fn($item) => ['id' => $item->value, 'name' => $item->value])
                ->sortBy('name')
                ->values();

            return response()->json([
                'success' => true,
                'data' => $constructionTypes
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch construction types: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all structure types
     */
    public function getStructureTypes()
    {
        try {
            $structureTypes = Lookup::where('key', 'risk_structure_type')
                ->get()
                ->map(fn($item) => ['id' => $item->value, 'name' => $item->value])
                ->sortBy('name')
                ->values();

            return response()->json([
                'success' => true,
                'data' => $structureTypes
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch structure types: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all occupation types
     */
    public function getOccupationTypes()
    {
        try {
            $occupationTypes = Lookup::where('key', 'risk_occupation')
                ->get()
                ->map(fn($item) => ['id' => $item->value, 'name' => $item->value])
                ->sortBy('name')
                ->values();

            return response()->json([
                'success' => true,
                'data' => $occupationTypes
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch occupation types: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all occupancy types
     */
    public function getOccupancyTypes()
    {
        try {
            $occupancyTypes = Lookup::where('key', 'risk_occupancy_type')
                ->get()
                ->map(fn($item) => ['id' => $item->value, 'name' => $item->value])
                ->sortBy('name')
                ->values();

            return response()->json([
                'success' => true,
                'data' => $occupancyTypes
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch occupancy types: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all extensions
     */
    public function getExtensions()
    {
        try {
            $extensions = Lookup::where('key', 'extensions')
                ->get()
                ->map(fn($item) => ['id' => $item->value, 'name' => $item->value])
                ->sortBy('name')
                ->values();

            return response()->json([
                'success' => true,
                'data' => $extensions
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch extensions: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get hardcoded usage types
     */
    public function getUsageTypes()
    {
        try {
            $usageTypes = [
                ['id' => 'Administrative Office', 'name' => 'Administrative Office'],
                ['id' => 'Distribution Center', 'name' => 'Distribution Center'],
                ['id' => 'Manufacturing (Light)', 'name' => 'Manufacturing (Light)'],
                ['id' => 'Manufacturing (Heavy)', 'name' => 'Manufacturing (Heavy)'],
                ['id' => 'Retail (FMGG)', 'name' => 'Retail (FMGG)'],
                ['id' => 'Retail (High Value)', 'name' => 'Retail (High Value)'],
                ['id' => 'Stock Yard', 'name' => 'Stock Yard'],
            ];

            return response()->json([
                'success' => true,
                'data' => $usageTypes
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch usage types: ' . $e->getMessage()
            ], 500);
        }
    }
}
