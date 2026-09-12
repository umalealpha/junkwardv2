<?php

namespace AlphaDirect\Http\Controllers\Admin;

use AlphaDirect\Http\Controllers\Controller;
use Illuminate\Http\Request;
use AlphaDirect\Models\CustomerBenefit;

class RewardController extends Controller
{
    public function info($rewardId)
    {
        $reward = CustomerBenefit::with('benefit')->findOrFail($rewardId);
        return response()->json([
            'id' => $reward->id,
            'tag' => $reward->benefit->tag,
            'status' => $reward->status,
            'expiry_date' => $reward->expiry_date,
            'claim_date' => $reward->claim_date,
            'instructions' => $reward->instructions,
            'created_at' => $reward->created_at,
        ]);
    }

    public function claim(Request $request, $rewardId)
    {
        $reward = CustomerBenefit::findOrFail($rewardId);
        if ($reward->status !== 'active') {
            return response()->json(['message' => 'Reward cannot be claimed.'], 400);
        }
        $reward->status = 'claimed';
        $reward->claim_date = now();
        $reward->save();

        return response()->json([
            'message' => 'Reward claimed successfully.',
            'instructions' => $reward->instructions,
        ]);
    }

    public function analytics()
    {
        $data = [
            'active' => CustomerBenefit::where('status', 'active')->count(),
            'claimed' => CustomerBenefit::where('status', 'claimed')->count(),
            'expired' => CustomerBenefit::where('status', 'expired')->count(),
        ];
        return response()->json($data);
    }
} 