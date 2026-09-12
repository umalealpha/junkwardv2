<?php

namespace AlphaDirect\Http\Controllers;

use DB;
use Illuminate\Http\Request;

class VehicleController extends Controller
{

    public function getMakes()
    {

        $carMake = DB::table('tb_prmotormakemodels')
            ->selectRaw('DISTINCT s_Make')
            ->get();

        return response()->json($carMake);

    }
    public function getCarModel(Request $request)
    {

        $carModel = DB::table('tb_prmotormakemodels')
            ->select('s_Variant')
            ->where('s_Make', '=', $request->make)
            ->get();

        return response()->json($carModel);
    }

    public function getMotorItems(Request $request)
    {
        $motorItems = DB::table('tb_prappssupppersprops')->where('n_Coverage_FK', 148)->select();
        return response()->json($motorItems);
    }
}
