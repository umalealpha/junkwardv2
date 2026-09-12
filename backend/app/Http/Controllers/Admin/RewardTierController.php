<?php

namespace AlphaDirect\Http\Controllers\Admin;

use AlphaDirect\Http\Controllers\Controller;
use Illuminate\Http\Request;
use AlphaDirect\Models\RewardTier;
use AlphaDirect\Config;
use Illuminate\Support\Facades\Storage;

class RewardTierController extends Controller
{
    public function index()
    {
        
        $tiers = RewardTier::all();
        return view('admin.reward_tiers.index', compact('tiers'));
    }

    public function create()
    {
        return view('admin.reward_tiers.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|unique:tiers,name',
            'label' => 'nullable',
            'description' => 'nullable',
            'condition1' => 'nullable|integer|between:0,40',
            'condition2' => 'nullable|boolean',
            'condition3' => 'nullable|boolean',
            'condition4' => 'nullable|boolean',
            'level_point' => 'required|integer|between:1000,4000',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'status' => 'required|in:0,1',
        ]);

        $data = $request->only(['name', 'label', 'description', 'condition1', 'condition2', 'condition3', 'condition4', 'level_point', 'status']);

        // Handle image upload
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $name = time() . '_' . $file->getClientOriginalName();
            $filePath = 'RewardTiers/' . $name;
            Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
            $data['image'] = $filePath;
        }

        RewardTier::create($data);
        return redirect()->route('reward-tiers.index')->with('success', 'Reward Tier created successfully.');
    }

    public function show($id)
    {
        $tier = RewardTier::findOrFail($id);
        return view('admin.reward_tiers.show', compact('tier'));
    }

    public function edit($id)
    {
        $tier = RewardTier::findOrFail($id);
        return view('admin.reward_tiers.edit', compact('tier'));
    }

    public function update(Request $request, $id)
    {
        $tier = RewardTier::findOrFail($id);
        $request->validate([
            'name' => 'required|unique:tiers,name,' . $id,
            'label' => 'nullable',
            'description' => 'nullable',
            'condition1' => 'nullable|integer|between:0,40',
            'condition2' => 'nullable|boolean',
            'condition3' => 'nullable|boolean',
            'condition4' => 'nullable|boolean',
            'level_point' => 'required|integer|between:1000,4000',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'status' => 'required|in:0,1',
        ]);

        $data = $request->only(['name', 'label', 'description', 'condition1', 'condition2', 'condition3', 'condition4', 'level_point', 'status']);

        // Handle image upload
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $name = time() . '_' . $file->getClientOriginalName();
            $filePath = 'RewardTiers/' . $name;
            Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
            $data['image'] = $filePath;
        }

        $tier->update($data);
        return redirect()->route('reward-tiers.index')->with('success', 'Reward Tier updated successfully.');
    }

    public function destroy($id)
    {
        $tier = RewardTier::findOrFail($id);
        $tier->delete();
        return redirect()->route('reward-tiers.index')->with('success', 'Reward Tier deleted successfully.');
    }

    /**
     * Update customer point expiry days configuration
     */
    public function updateExpiryDays(Request $request)
    {
        if (auth()->user()->hasPermissionTo('customer-edit')) {
            $request->validate([
                'expiry_days' => 'required|integer|min:1|max:3650' // Max 10 years
            ]);

            try {
                $config = Config::where('key', 'customer_point_expire_in')->first();
                
                if (!$config) {
                    // Create new config if it doesn't exist
                    $config = new Config();
                    $config->key = 'customer_point_expire_in';
                }
                
                $config->value = $request->expiry_days;
                $config->save();

                return response()->json([
                    'success' => true,
                    'message' => 'Customer point expiry days updated successfully to ' . $request->expiry_days . ' days.',
                    'expiry_days' => $request->expiry_days
                ]);
                
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error updating expiry days: ' . $e->getMessage()
                ], 500);
            }
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Sorry! You do not have permission to perform this action!'
            ], 403);
        }
    }

    /**
     * Get current expiry days configuration
     */
    public function getExpiryDays()
    {
        $config = Config::where('key', 'customer_point_expire_in')->first();
        $expiryDays = $config ? $config->value : 1095; // Default 1095 days (3 years)
        
        return response()->json([
            'expiry_days' => (int) $expiryDays
        ]);
    }
} 