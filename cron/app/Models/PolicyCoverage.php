<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Model;
use DB;
use Carbon\Carbon;
use AlphaDirect\Lookup;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use AlphaDirect\PolicyTerm;
use AlphaDirect\Policy;
class PolicyCoverage extends Model
{
    use SoftDeletes;

    protected static function booted()
    {
        parent::boot();
        self::deleting(function ($policyCoverage) {
            $policyCoverage->coverageDetail()->delete();
            $policyCoverage->extentionDetail()->delete();
            $policyCoverage->specifedItems()->delete();
            $policyCoverage->entities()->delete();
            $policyCoverage->note()->delete();
            $policyCoverage->motorInternal()->delete();
            $policyCoverage->motorExteranal()->delete();
            $policyCoverage->motor()->delete();

        });
    }
    public function riskAddress()
    {
        return $this->belongsTo(RiskAddress::class);
    }

    public function coverage()
    {
        return $this->belongsTo(CoverageMaster::class);
    }

    public function coverageDetail()
    {
        return $this->hasMany(PolicyCoverageDetail::class);
    }

    public function extentionDetail()
    {
        return $this->hasMany(PolicyExtentionDetails::class);
    }

    public function specifedItems()
    {
        return $this->hasMany(PolicySpecifiedItem::class)->withTrashed();
    }

    public function entities()
    {
        return $this->hasMany(PolicyCoverageEntity::class);
    }

    // Coverage-level note (motor_id NULL or 0), latest('id') — kept identical
    // to backend/app/Models/PolicyCoverage::note().
    //
    // This was an UNSCOPED hasOne, which is the divergence the backend's
    // replicateMotorNotesIfMissing() docblock flags as "the cron copy
    // mis-assigns motor_id": for a Motor coverage whose only note is a
    // PER-VEHICLE one, $coverage->note returned that vehicle note, so
    // replicateRecords() cloned it onto the renewed coverage carrying the
    // SOURCE action's stale motor_id (the old re-pointing loop that used to
    // clean this up was removed — see PolicyAction ~line 1010). Per-vehicle
    // notes are carried by replicateMotorNotesIfMissing(), which maps each
    // note onto its own new motor by registration_no, so scoping this to the
    // coverage-level row loses nothing and stops the stray clone.
    public function note()
    {
        return $this->hasOne(PolicyCoverageNote::class)->coverageLevel()->latest('id');
    }

    public function motorInternal()
    {
        return $this->hasMany(MotorTradersInternal::class);
    }
    public function policyCoveragesData()
    {
        return $this->hasMany(PolicyCoveragesData::class);
    }

    public function motorExteranal()
    {
        return $this->hasMany(MotorTraders::class);
    }

    public function motor()
    {
        return $this->hasMany(Motor::class);
    }

    public function coverageDataFidelity()
    {
        return $this->hasMany(PolicyCoveragesData::class, 'policyCoverageID');
    }

    public function carCoverage()
    {
        return $this->hasOne(CarCoverage::class, 'policy_coverage_id');
    }

    public function earCoverage()
    {
        return $this->hasOne(EarCoverage::class, 'policy_coverage_id');
    }

    public function parCoverage()
    {
        return $this->hasOne(ParCoverage::class, 'policy_coverage_id');
    }

    public function travelCoverage()
    {
        return $this->hasOne(TravelCoverage::class, 'policy_coverage_id');
    }
    public function professionalIndemnityCoverage()
    {
        return $this->hasOne(ProfessionalIndemnityCoverage::class, 'policy_coverage_id');
    }

    public function scopePolicy($query, $policy_id)
    {
        $query->where('policy_id', $policy_id);
    }

    public function scopeCoverage($query, $coverage_id)
    {
        return $query->where('coverage_id', $coverage_id);
    }

    public function scopeTerm($query, $term_id)
    {
        return $query->where('term_id', $term_id);
    }

    public function scopeAction($query, $action_id)
    {
        return $query->where('action_id', $action_id);
    }

    public function scopeRisk($query, $risk_address_id)
    {
        return $query->where('risk_address_id', $risk_address_id);
    }


    public static function getReinsuranceCoverageCalculations($policyId, $termId, $actionId)
    {
        $currentDate = Carbon::now()->format('Y-m-d');
        $currentDateTime = Carbon::now()->format('Y-m-d H:i:s');
        $policy = Policy::find($policyId);
        $productId = $policy->product_id;

        $checkMotorTrailerDatapresentorNot = DB::table('motor')
            ->join('vehicle', 'vehicle.vehiclePlate', '=', 'motor.registration_no')
            ->join('motor_type', 'motor_type.id', '=', 'vehicle.vehicle_type')
            ->join('policy_coverages', 'policy_coverages.id', '=', 'motor.policy_coverage_id')
            ->select('motor.type_of_cover_main', 'motor_type.id')
            ->whereIn('policy_coverages.coverage_id', [22, 27])
            ->where('motor_type.motor_name', 'Motor Trailer')
            ->where('policy_coverages.policy_id', $policyId)
            ->whereNull('policy_coverages.deleted_at')
            ->where('vehicle.action_id', $actionId)
            ->get();

        $checkMotorDatapresentorNot = DB::table('motor')
            ->join('vehicle', 'vehicle.vehiclePlate', '=', 'motor.registration_no')
            ->join('motor_type', 'motor_type.id', '=', 'vehicle.vehicle_type')
            ->join('policy_coverages', 'policy_coverages.id', '=', 'motor.policy_coverage_id')
            ->select('motor.type_of_cover_main', 'motor_type.id')
            ->whereIn('policy_coverages.coverage_id', [22, 27])
            ->where('motor_type.motor_name', '!=', 'Motor Trailer')
            ->where('policy_coverages.policy_id', $policyId)
            ->whereNull('policy_coverages.deleted_at')
            ->where('vehicle.action_id', $actionId)
            ->get();

        //old by sonali
        //     $checkMotorTradersDatapresentorNot = DB::table('motor_traders')
        //     ->leftJoin('policy_coverages', 'policy_coverages.id', '=', 'motor_traders.policy_coverage_id')
        //     ->leftJoin('policy_coverages', 'policy_coverages.id', '=', 'motor_traders_internal.policy_coverage_id')
        //     ->select('motor_traders.type_of_cover')
        //     ->whereIn('policy_coverages.coverage_id', [15,16])
        //     ->where('policy_coverages.policy_id', $policyId)
        //     ->whereNull('policy_coverages.deleted_at')
        //     ->where('policy_coverages.action_id', $actionId)
        //    ->get();

        $checkMotorTradersDatapresentorNot = DB::table('policy_coverages')
            ->leftJoin('motor_traders', 'motor_traders.policy_coverage_id', '=', 'policy_coverages.id')
            ->leftJoin('motor_traders_internal', 'motor_traders_internal.policy_coverage_id', '=', 'policy_coverages.id')
            ->select(
                DB::raw('COALESCE(motor_traders.type_of_cover, motor_traders_internal.type_of_cover) as type_of_cover')
            )
            ->whereIn('policy_coverages.coverage_id', [15, 16])
            ->where('policy_coverages.policy_id', $policyId)
            ->whereNull('policy_coverages.deleted_at')
            ->where('policy_coverages.action_id', $actionId)
            ->get();


        $checkFidelityGuaranteepresentorNot = DB::table('policy_coverages')
            ->where('policy_id', $policyId)
            ->where('coverage_id', 9)
            ->where('action_id', $actionId)
            ->whereNull('deleted_at')
            ->get();

        $checkWorkerCompensationpresentorNot = DB::table('policy_coverages')
            ->where('policy_id', $policyId)
            ->where('coverage_id', 20)
            ->where('action_id', $actionId)
            ->whereNull('deleted_at')
            ->get();

        $checkPersonalAccidentpresentorNot = DB::table('policy_coverages')
            ->where('policy_id', $policyId)
            ->where('coverage_id', 14)
            ->where('action_id', $actionId)
            ->whereNull('deleted_at')
            ->get();

        $checkPublicLaibilitypresentorNot = DB::table('policy_coverages')
            ->where('policy_id', $policyId)
            ->whereIn('coverage_id', [21, 448])
            ->where('action_id', $actionId)
            ->whereNull('deleted_at')
            ->get();


        $excludedCoverageIds = [21, 20, 14, 9, 22, 27, 15, 16];

        $checkOtherCoverpresentorNot = DB::table('policy_coverages')
            ->select('coverage_id')
            ->where('policy_id', $policyId)
            ->whereNotIn('coverage_id', $excludedCoverageIds)
            ->where('action_id', $actionId)
            ->whereNull('deleted_at')
            ->get()->toArray();
// dd($checkOtherCoverpresentorNot);
        $checkGoodItCoverpresentorNot = DB::table('policy_coverages')
            ->select('coverage_id')
            ->where('policy_id', $policyId)
            ->where('coverage_id', 10)
            ->where('action_id', $actionId)
            ->whereNull('deleted_at')
            ->get()->toArray();
                                        
        $userId = Auth::user()->id;

        $deleteDetails = PolicyReinsuranceDetails::where('action_id', $actionId)->delete();
        if (isset($checkMotorDatapresentorNot) && count($checkMotorDatapresentorNot) > 0) {
              if ($productId == 8) {
                   self::MotorReinsurance($typeOfCoverMain=0, 15, $currentDate, $actionId, $typeOfCoverID=0, $policyId);
                } else {
                    self::MotorReinsurance($typeOfCoverMain=0, 14, $currentDate, $actionId, $typeOfCoverID=0, $policyId);
                }


            // foreach ($checkMotorDatapresentorNot as $MTData) {
            //     $typeOfCoverMain = $MTData->type_of_cover_main;
            //     $typeOfCoverID = $MTData->id;
            //     if ($productId == 8) {
            //         self::MotorReinsurance($typeOfCoverMain, 15, $currentDate, $actionId, $typeOfCoverID, $policyId);
            //     } else {
            //         self::MotorReinsurance($typeOfCoverMain, 14, $currentDate, $actionId, $typeOfCoverID, $policyId);
            //     }

            // }
        }


        if (isset($checkMotorTradersDatapresentorNot) && count($checkMotorTradersDatapresentorNot) > 0) {
            foreach ($checkMotorTradersDatapresentorNot as $MTData) {
                $typeOfCoverMain = $MTData->type_of_cover;
                self::MotorTradersReinsurance($typeOfCoverMain, 13, $currentDate, $actionId);
            }

        }


        if (isset($checkMotorTrailerDatapresentorNot) && count($checkMotorTrailerDatapresentorNot) > 0) {
            foreach ($checkMotorTrailerDatapresentorNot as $MTData) {
                $typeOfCoverMain = $MTData->type_of_cover_main;
                $typeOfCoverID = $MTData->id;
                if ($productId == 8) {
                    self::MotorMotorTrailerReinsurance($typeOfCoverMain, 29, $currentDate, $actionId, $typeOfCoverID, $policyId);
                } else {
                    self::MotorMotorTrailerReinsurance($typeOfCoverMain, 28, $currentDate, $actionId, $typeOfCoverID, $policyId);
                }

            }
        }

        if (isset($checkFidelityGuaranteepresentorNot) && count($checkFidelityGuaranteepresentorNot) > 0) {
            self::FidelityGuarantee(9, $currentDate, $actionId);
        }
        if (isset($checkWorkerCompensationpresentorNot) && count($checkWorkerCompensationpresentorNot) > 0) {

            self::ExcessOfLossCoverage(20, $currentDate, $actionId);
        }

        if (isset($checkPersonalAccidentpresentorNot) && count($checkPersonalAccidentpresentorNot) > 0) {
            self::ExcessOfLossCoverage(14, $currentDate, $actionId);
        }

        if (isset($checkPublicLaibilitypresentorNot) && count($checkPublicLaibilitypresentorNot) > 0) {

            self::ExcessOfLossCoverage(21, $currentDate, $actionId);
        }

        if (isset($checkOtherCoverpresentorNot) && count($checkOtherCoverpresentorNot) > 0) {
            $i=0;
            $coverage_is_check=$checkOtherCoverpresentorNot[$i]->coverage_id;

            $DetailQuery = "select pot.policy_id,cvgm.id as cvgmId,cvgm.coverage_id as mainCoverID, COALESCE(veh.vehicle_type,0) as n_TypeOfMotor, 
            risk.id, cvgsm.id as pocoverage_detail_id,
         
            CASE 
            WHEN cvgsm.coverage_value != 0.00 THEN cvgsm.coverage_value 
            ELSE 
                CASE 
                    WHEN cvgsm.ratefactor_value != 0 THEN cvgsm.ratefactor_value 
                    ELSE REPLACE(cvgsm.ratefactor_AnnualWages , ',', '')
                END         
            END AS coverage_value,      
            cvgsm.calculated_value, 
            cvgsm.coverage_id as subcoverage_id,
            cvgm.risk_address_id,T.*
            from policy_actions  pot
            left join policies pol on pol.id=pot.policy_id
            left join policy_coverages cvgm on cvgm.action_id = pot.id
            left join policy_coverage_detail cvgsm ON cvgsm.policy_coverage_id=cvgm.id
            left join policy_coverage_entities cvget ON cvget.policy_coverage_id=cvgm.id
            left join vehicle veh ON cvget.entity_id=veh.id
            left join risk_address risk on risk.id = cvgm.risk_address_id
            left join policy_specified_items psi ON psi.policy_coverage_id=cvgm.id
            LEFT JOIN             
            (SELECT tc.id as tID,gd.coverage_id,tm.id as n_TreatyMaster_PK,
            gm.product_id,fm.product_id as n_Product_FK_fm,
            gm.id as n_GroupMaster_PK,gm.group_name,fm.id as n_FormulaMaster_PK, fm.type_id,fm.formula_name, fm.s_FormulaType,fd.operator,
            fd.si_allocation,fd.n_ValueLimitsBetween,fd.percentage, gd.si_premium, gd.ri_limit, gd.limit_value, COALESCE(fd.vehicle_type,0)  as n_MotorType
            FROM reinsurance_treaty tm
            LEFT JOIN reinsurance_treaty_details td ON tm.id = td.treaty_id
            LEFT JOIN reinsurance_formula fm ON td.formula_attached = fm.id
            LEFT JOIN reinsurance_formula_details fd ON fm.id = fd.formula_id
            LEFT JOIN reinsurance_group gm ON fd.group_id = gm.id
            LEFT JOIN reinsurance_group_coverage gd ON gm.id = gd.group_id
            INNER JOIN tb_cvgpccoverages tc ON tc.s_CoverageCode = gd.coverage_name
            WHERE tm.effective_from <= '" . $currentDate . "'
                    AND tm.effective_to >= '" . $currentDate . "'
                   #AND gm.group_name = 'MISC_COM'
            ) as T
            ON T.tID=cvgsm.coverage_id
            AND T.n_MotorType = COALESCE(veh.vehicle_type,0)
            where pot.id= '" . $actionId . "'
           #and group_name = 'MISC_COM'
           AND pol.product_id=T.product_id
           AND cvgm.deleted_at IS NULL
         
            GROUP BY pocoverage_detail_id,n_TreatyMaster_PK,
            -- GROUP BY pot.policy_id, pot.id, risk.id,cvgm.coverage_id,
            case
                when  T.type_id = 35 then cvgsm.coverage_id
                else ''       
                end
            order by risk.id, cvgm.coverage_id, T.group_name";

// $DetailQuery="
//         SELECT 
//     pot.policy_id,
//     cvgm.id AS cvgmId,
//     cvgm.coverage_id AS mainCoverID,
//     COALESCE(veh.vehicle_type, 0) AS n_TypeOfMotor,
//     risk.id AS risk_id,

//     -- coverage detail fields (safe if NULL)
//     cvgsm.id AS pocoverage_detail_id,

//     CASE 
//         WHEN COALESCE(cvgsm.coverage_value, 0) != 0 THEN cvgsm.coverage_value
//         WHEN COALESCE(cvgsm.ratefactor_value, 0) != 0 THEN cvgsm.ratefactor_value
//         ELSE REPLACE(COALESCE(cvgsm.ratefactor_AnnualWages, '0'), ',', '')
//     END AS coverage_value,

//     COALESCE(cvgsm.calculated_value, 0) AS calculated_value,
//     cvgsm.coverage_id AS subcoverage_id,

//     cvgm.risk_address_id,

//     T.*
// FROM policy_actions pot
// LEFT JOIN policies pol 
//     ON pol.id = pot.policy_id

// LEFT JOIN policy_coverages cvgm 
//     ON cvgm.action_id = pot.id
//     AND cvgm.deleted_at IS NULL

// -- IMPORTANT: keep LEFT JOIN truly optional
// LEFT JOIN policy_coverage_detail cvgsm 
//     ON cvgsm.policy_coverage_id = cvgm.id
//     AND cvgsm.deleted_at IS NULL

// LEFT JOIN policy_coverage_entities cvget 
//     ON cvget.policy_coverage_id = cvgm.id

// LEFT JOIN vehicle veh 
//     ON cvget.entity_id = veh.id

// LEFT JOIN risk_address risk 
//     ON risk.id = cvgm.risk_address_id

// LEFT JOIN policy_specified_items psi 
//     ON psi.policy_coverage_id = cvgm.id
//     AND psi.deleted_at IS NULL

// LEFT JOIN (
//     SELECT 
//         tc.id AS tID,
//         gd.coverage_id,
//         tm.id AS n_TreatyMaster_PK,
//         gm.product_id,
//         fm.product_id AS n_Product_FK_fm,
//         gm.id AS n_GroupMaster_PK,
//         gm.group_name,
//         fm.id AS n_FormulaMaster_PK,
//         fm.type_id,
//         fm.formula_name,
//         fm.s_FormulaType,
//         fd.operator,
//         fd.si_allocation,
//         fd.n_ValueLimitsBetween,
//         fd.percentage,
//         gd.si_premium,
//         gd.ri_limit,
//         gd.limit_value,
//         COALESCE(fd.vehicle_type, 0) AS n_MotorType
//     FROM reinsurance_treaty tm
//     LEFT JOIN reinsurance_treaty_details td 
//         ON tm.id = td.treaty_id
//     LEFT JOIN reinsurance_formula fm 
//         ON td.formula_attached = fm.id
//     LEFT JOIN reinsurance_formula_details fd 
//         ON fm.id = fd.formula_id
//     LEFT JOIN reinsurance_group gm 
//         ON fd.group_id = gm.id
//     LEFT JOIN reinsurance_group_coverage gd 
//         ON gm.id = gd.group_id
//     INNER JOIN tb_cvgpccoverages tc 
//         ON tc.s_CoverageCode = gd.coverage_name
//     WHERE tm.effective_from <= '" . $currentDate . "'
//       AND tm.effective_to >= '" . $currentDate . "'
// ) T
//     ON T.tID = cvgm.coverage_id
//    AND T.n_MotorType = COALESCE(veh.vehicle_type, 0)

// WHERE pot.id = '" . $actionId . "'
//   AND pol.product_id = T.product_id

// GROUP BY 
//     cvgm.id,
//     T.n_TreatyMaster_PK,
//     risk.id,
//     CASE 
//         WHEN T.type_id = 35 THEN cvgsm.coverage_id
//         ELSE cvgm.coverage_id
//     END

// ORDER BY risk.id, cvgm.coverage_id, T.group_name";

            $DetailDataArray = DB::select(DB::raw($DetailQuery));
        // dd($DetailDataArray,$DetailQuery);
            $coverageIds = array_map(fn($data) => $data->cvgmId, $DetailDataArray);
        if (empty($coverageIds))
        {

            $coverageIds = DB::table('policy_coverages')
            ->where('action_id', $actionId)
            ->where('coverage_id', $coverage_is_check)
            ->whereNull('deleted_at')
            ->pluck('id')
            ->toArray();
        }
        // dd($coverageIds);
            // Fetch sum_insured and calculated_value for all policy_coverage_ids in one query
            $misItemsData = DB::select(DB::raw("
                select policy_specified_items.policy_coverage_id,coverage_id, SUM(sum_insured) as sum_insured, 
                SUM(calculated_value) as calculated_value from policy_specified_items
                INNER JOIN policy_coverages ON policy_coverages.id = policy_specified_items.policy_coverage_id
                where policy_coverage_id IN ('" . implode("','", $coverageIds) . "')
                AND policy_specified_items.deleted_at IS NULL
                GROUP BY policy_specified_items.policy_coverage_id
            "));
            // dd("
            //     select policy_specified_items.policy_coverage_id,coverage_id, SUM(sum_insured) as sum_insured, 
            //     SUM(calculated_value) as calculated_value from policy_specified_items
            //     INNER JOIN policy_coverages ON policy_coverages.id = policy_specified_items.policy_coverage_id
            //     where policy_coverage_id IN ('" . implode("','", $coverageIds) . "')
            //     AND policy_specified_items.deleted_at IS NULL
            //     GROUP BY policy_specified_items.policy_coverage_id
            // ");
          //  dd($misItemsData);
            // Loop through query results and organize data
            foreach ($misItemsData as $misItem) {
                $coverageId = $misItem->coverage_id;
                // Ensure sum_insured is treated as a float.
                $misItemsMap[$coverageId] = [
                    'coverageId' => $coverageId,
                    'sum_insured' => (float) $misItem->sum_insured,
                    'calculated_value' => (float) $misItem->calculated_value,
                ];
            }
            // dd($DetailDataArray,"hi");
            $processedCovers = []; // Array to track processed mainCoverIDs
            foreach ($DetailDataArray as $key => $DetailData) {
                $SumInsured = 0.00;
                $SumInsuredPre = 0;
                $sum_insured = 0;
                $mainCoverID = $misItemsMap[$DetailData->mainCoverID]['coverageId'] ?? 0;
                $sum_insured = $misItemsMap[$DetailData->mainCoverID]['sum_insured'] ?? 0;
                $calculated_value = $misItemsMap[$DetailData->mainCoverID]['calculated_value'] ?? 0;

                if ($DetailData->ri_limit == 1) {

                    if (!isset($processedCovers[$DetailData->mainCoverID])) {

                        // First time encountering this mainCoverID, include sum_insured
                        $SumInsured = $DetailData->coverage_value + $sum_insured;
                        $SumInsuredPre = $DetailData->calculated_value + $calculated_value;

                        // Mark this mainCoverID as processed
                        $processedCovers[$DetailData->mainCoverID] = true;

                    } else {
                        // Already processed, do not add sum_insured again
                        $SumInsured = $DetailData->coverage_value;
                        $SumInsuredPre = $DetailData->calculated_value ?? "";
                    }

                } else {

                    if (!isset($processedCovers[$DetailData->mainCoverID])) {
                        if (is_numeric($DetailData->limit_value)) {
                            $SumInsured = (float) $DetailData->limit_value + (float) $sum_insured;
                        } else {
                            $SumInsured = (float) $sum_insured;
                        }


                        // Mark this mainCoverID as processed
                        $processedCovers[$DetailData->mainCoverID] = true;
                    } else {

                        // Already processed, do not add sum_insured again
                        $SumInsured = $DetailData->limit_value;
                    }
                }


                $policyReinsurrance = array(
                    'product_id' => $DetailData->product_id ?? "",
                    'coverage_id' => $DetailData->coverage_id ?? "",
                    'subcoverage_id' => $DetailData->subcoverage_id ?? "",
                    'pocoverage_detail_id' => $DetailData->pocoverage_detail_id ?? "",
                    'action_id' => $actionId ?? "",
                    'policy_id' => $DetailData->policy_id ?? "",
                    'group_id' => $DetailData->n_GroupMaster_PK ?? "",
                    'treaty_id' => $DetailData->n_TreatyMaster_PK ?? "",
                    'formula_id' => $DetailData->n_FormulaMaster_PK ?? "",
                    'risk_address_id' => $DetailData->risk_address_id ?? "",
                    's_FormulaType' => $DetailData->s_FormulaType ?? "",
                    'n_SumInsured' => $SumInsured,
                    'n_Premium' => $SumInsuredPre,
                );
                //print_r($policyReinsurrance);
                $d = PolicyReinsuranceDetails::insert($policyReinsurrance);
            }

        }

       $deleteMaster = PolicyReinsurance::where('action_id', $actionId)->delete();

        $MasterDataArray = '';
        if (isset($checkMotorDatapresentorNot) && count($checkMotorDatapresentorNot) > 0) {
            $productId = $policy->product_id;
            self::MotorComReinsurance($currentDate, $actionId, $currentDateTime, $policyId, $termId, $productId);

        }
        if (isset($checkMotorTradersDatapresentorNot) && count($checkMotorTradersDatapresentorNot) > 0) {
            $productId = $policy->product_id;
            self::MotorComTradersReinsurance($currentDate, $actionId, $currentDateTime, $policyId, $termId, $productId);

        }
        if (isset($checkMotorTrailerDatapresentorNot) && count($checkMotorTrailerDatapresentorNot) > 0) {
            $productId = $policy->product_id;
            self::MotorComTrailerReinsurance($currentDate, $actionId, $currentDateTime, $policyId, $termId, $productId);

        }
        //  if(isset($checkMotorTrailerDatapresentorNot) && count($checkMotorTrailerDatapresentorNot) > 0)
        // {
        //     $productId = $policy->product_id;
        //     self::MotorComTrailerReinsurance($currentDate,$actionId,$currentDateTime,$policyId,$termId,$productId);

        // }
        if (isset($checkFidelityGuaranteepresentorNot) && count($checkFidelityGuaranteepresentorNot) > 0) {

            self::FidelityGuaranteeReinsurance($currentDate, $actionId, $currentDateTime, $policyId, $termId, $productId);
        }
        if (isset($checkWorkerCompensationpresentorNot) && count($checkWorkerCompensationpresentorNot) > 0) {

            self::ExcessOfLossCoverageReinsurance1($currentDate, $actionId, $currentDateTime, $policyId, $termId, $productId, 20);
        }
        if (isset($checkPersonalAccidentpresentorNot) && count($checkPersonalAccidentpresentorNot) > 0) {
            self::ExcessOfLossCoverageReinsurance1($currentDate, $actionId, $currentDateTime, $policyId, $termId, $productId, 14);
        }

        if (isset($checkPublicLaibilitypresentorNot) && count($checkPublicLaibilitypresentorNot) > 0) {
            self::ExcessOfLossCoverageReinsurance1($currentDate, $actionId, $currentDateTime, $policyId, $termId, $productId, 21);
        }


        if (isset($checkOtherCoverpresentorNot) && count($checkOtherCoverpresentorNot) > 0) {

            $MasterQuery = "select fm.reinsurance_type_id,gm.group_code,fm.type_id,fm.s_FormulaType  as formula_type, fd.formula_id, COALESCE(veh.vehicle_type,0) as n_TypeOfMotor ,
                fd.operator,fd.si_allocation,fd.n_ValueLimitsBetween,fd.percentage,
                prid.*
                ,SUM(prid.n_SumInsured) AS TotalSumInsured, SUM(prid.n_Premium) AS TotalPremium,tm.id
                from policy_reinsurance_details prid
                left join reinsurance_group gm on gm.id = prid.group_id
                left join reinsurance_formula_details fd on fd.group_id = gm.id
  
                # commented  by snehal on 30-07-25
                # left join policy_coverage_detail sub on sub.id = prid.pocoverage_detail_id
                # left join motor mot on mot.policy_coverage_id = prid.pocoverage_detail_id
                 
                # added by snehal on 30-07-25
                left join policy_coverage_detail sub on sub.id = gm.id
                left join motor mot on mot.policy_coverage_id = sub.id

                left join policy_coverage_entities cvget ON cvget.policy_coverage_id=sub.policy_coverage_id
                left join vehicle veh ON cvget.entity_id=veh.id
                left join reinsurance_formula fm on fm.id = fd.formula_id
                left join reinsurance_treaty_details td on td.formula_attached = fm.id
                left join reinsurance_treaty tm on tm.id = td.treaty_id
                where
                # COALESCE(veh.vehicle_type,0)  = COALESCE(fd.vehicle_type,0)
                #and 
                prid.action_id = '" . $actionId . "'
                AND fd.operator IS NOT NULL
                AND gm.group_code NOT IN ('MOTOR_COM','MOTOR_TRADERS_COM_EXT','MOTOR_TRADERS_COM_INT','MOTOR_TRAILERS_COM','MOTOR_TRAILERS_DOM','MOTOR_DOM','FIDELITYG_COM','WC_DOM','WC_COM','GROUPPERSONALACCIDENT_DOM','GROUPPERSONALACCIDENT_COM','PUBLICLIABANDDEFECTIVEWORKMAN_DOM','PUBLICLIABANDDEFECTIVEWORKMAN_COM')
                AND tm.effective_from <= '" . $currentDate . "'
                AND tm.effective_to >= '" . $currentDate . "'
                
                GROUP BY prid.policy_id,prid.action_id,prid.risk_address_id,prid.group_id,fd.formula_id,
                case
                        when  fm.type_id = 35 then prid.pocoverage_detail_id
                        else ''
                    end
                ORDER BY fd.formula_id";

            // dd( $MasterQuery); 
            // echo $MasterQuery;die();
            $MasterDataArray = DB::select(DB::raw($MasterQuery));
            // dd( $MasterDataArray);  // vvvvvvvv44                  
            $TSI_Excess = '';
            $golbalNet = 0;
            $golbalQuota = 0;
            $FacPlaceAmt = 0;
            $QSCShare = 0;
            $QSCNoNMotorShare = 0;
            $FacPlaceFinalAMT = 0;

            // dd($MasterDataArray);

            foreach ($MasterDataArray as $MasterDataArrayVale) {

                if ($MasterDataArrayVale->reinsurance_type_id != NULL) {
                    $TreatySI = 0;
                    $TreatyPercentage = 0;
                    $TreatyPremium = 0;
                    $ExpressionSign = null;
                    if ($MasterDataArrayVale->operator == 1) {
                        $ExpressionSign = '=';
                    } elseif ($MasterDataArrayVale->operator == 2) {
                        $ExpressionSign = '<';
                    } elseif ($MasterDataArrayVale->operator == 3) {
                        $ExpressionSign = '<=';
                    } elseif ($MasterDataArrayVale->operator == 4) {
                        $ExpressionSign = '>';
                    } elseif ($MasterDataArrayVale->operator == 5) {
                        $ExpressionSign = '>=';
                    } elseif ($MasterDataArrayVale->operator == 6) {
                        $ExpressionSign = '!=';
                    } elseif ($MasterDataArrayVale->operator == 7) {
                        $ExpressionSign = 'Between';
                    } elseif ($MasterDataArrayVale->operator == 8) {
                        $ExpressionSign = 'Not Between';
                    } elseif ($MasterDataArrayVale->operator == 9) {
                        $ExpressionSign = '*';
                    }
                    $UsedFormula = 'N';
                    $type = Lookup::where('id', $MasterDataArrayVale->type_id)->where('key', 'reinsurance_formula_key')->first()?->value;


                    // if($MasterDataArrayVale->formula_type == 'OTHER') {
                    //             if($ExpressionSign=='<')
                    //             {  
                    //                 if($MasterDataArrayVale->TotalSumInsured < $MasterDataArrayVale->si_allocation) {
                    //                     $TreatySI = round((($MasterDataArrayVale->TotalSumInsured*$MasterDataArrayVale->percentage)/100),4);
                    //                     $TreatyPercentage = $MasterDataArrayVale->percentage;
                    //                 }else{
                    //                     $TreatySI = round((($MasterDataArrayVale->si_allocation*$MasterDataArrayVale->percentage)/100),4);
                    //                     $TreatyPercentage = round((($TreatySI/$MasterDataArrayVale->TotalSumInsured)*100),4);
                    //                 }
                    //             }
                    //             $TreatyPremium = round((($MasterDataArrayVale->TotalPremium*$TreatyPercentage)/100),4);
                    //             $UsedFormula = 'Y';
                    // }
                    // else
                    if ($MasterDataArrayVale->formula_type == 'TSI') {

                        $total_sum_insured = $MasterDataArrayVale->TotalSumInsured;
                        $QSC = (float) str_replace(",", "", $MasterDataArrayVale->si_allocation);
                        // dd( $total_sum_insured,$QSC);
                        if ($total_sum_insured < $QSC) {
                            // $TSI_Excess = 0.00;
                            $TSI_Excess = $total_sum_insured;
                        } else {

                            $TSI_Excess = round((float) $QSC, 4);

                        }

                        if ($MasterDataArrayVale->TotalSumInsured > $QSC) {
                            $PQSC = round((($MasterDataArrayVale->TotalPremium * $QSC) / $MasterDataArrayVale->TotalSumInsured), 4);
                        } else {
                            $PQSC = round((($MasterDataArrayVale->TotalPremium)), 4);
                        }

                        $TreatyPercentage = round((($MasterDataArrayVale->percentage) / 100), 4);

                        $TreatyPremium = round((($PQSC * $TreatyPercentage)), 4);
                        $TreatySI = round((($TSI_Excess * $TreatyPercentage)), 4);

                        $UsedFormula = 'Y';

                    } elseif ($MasterDataArrayVale->formula_type == 'SURPLUS') {

                        $SCL = (float)str_replace(",", "", $MasterDataArrayVale->si_allocation);
                        $TotalSumInsured = (float) $MasterDataArrayVale->TotalSumInsured;
                        $TotalPremium = (float) $MasterDataArrayVale->TotalPremium;
                        $group_id = $MasterDataArrayVale->group_id;
                        $QSCNoNMotorShare = DB::table('reinsurance_formula_details')
                            ->where('group_id', $group_id)
                            ->where('percentage', 70)
                            ->value('si_allocation');

                        $QSCNoNMotorShare = (float) str_replace(",", "", $QSCNoNMotorShare);
                     //   $QSCNoNMotorShare = str_replace(",", "", $QSCNoNMotorShare);
                        // dd($MasterDataArrayVale);
                        $QSCL = ($TotalSumInsured <= $QSCNoNMotorShare) ? $TotalSumInsured : $QSCNoNMotorShare;
                        $AFCL = ((float) $SCL + (float) $QSCNoNMotorShare);
                        $TreatyPercentage = 0;
                        $TreatyPremium = 0;
                    // dd($TotalSumInsured,$QSCL,$SCL,$QSCNoNMotorShare);
                        if ($TotalSumInsured > $QSCL) {
                            if ($TotalSumInsured <= $SCL) {
                                $FinalPremium = $TotalSumInsured - $QSCNoNMotorShare;
                                $TreatyPercentage = ((float) $FinalPremium / (float) $TotalSumInsured);
                                $TreatyPremium = round((($TotalPremium * $TreatyPercentage)), 2);
                                // old code need to check
                             //   $TreatySI = round((($SCL)), 2);
                              $TreatySI = round((($TotalSumInsured-$QSCL)), 2);
                                $UsedFormula = 'Y';
                            } else {
                              
                                if (($TotalSumInsured - $QSCNoNMotorShare) <= $SCL) {

                                    $FinalPremium = $TotalSumInsured - $QSCNoNMotorShare;
                                    //$TreatyPercentage = round((((float)$FinalPremium/(float)$TotalSumInsured)*100),2);
                                    $TreatyPercentage = ((float) $FinalPremium / (float) $TotalSumInsured);
                                    $TreatyPremium = round((($TotalPremium * $TreatyPercentage)), 2);
                                    $TreatySI = round((($FinalPremium)), 2);
                                    $UsedFormula = 'Y';
                                } else {

                                    $FinalPremium = $SCL;
                                    $TreatyPercentage = ((float) $FinalPremium / (float) $TotalSumInsured);
                                    $TreatyPremium = round((($TotalPremium * $TreatyPercentage)), 2);
                                    $TreatySI = round((($SCL)), 2);
                                    $UsedFormula = 'Y';
                                }

                            }

                        } else {
                            $TreatyPercentage = 0;
                            $TreatyPremium = 0;
                            $TreatySI = 0;
                            $UsedFormula = 'N';
                        }
                        // $golbalQuota=$SCL ;


                    } elseif ($MasterDataArrayVale->formula_type == 'FACULTATIVE') {

                        $TotalSumInsured = $MasterDataArrayVale->TotalSumInsured;
                        $TotalPremium = $MasterDataArrayVale->TotalPremium;
                        $TreatySITotal = (float) str_replace(",", "", $MasterDataArrayVale->si_allocation);
                       
                        $group_id = $MasterDataArrayVale->group_id;
                       
                        $QSCL = DB::table('reinsurance_formula_details')
                            ->where('group_id', $group_id)
                            ->where('percentage', 70)
                            ->value('si_allocation');

                        $AFCL = $TreatySITotal; 
                        // old code giving issue sometime
                        // $SCL = DB::table('reinsurance_formula_details as rfd')
                        //     ->join('reinsurance_group as rg', 'rg.id', '=', 'rfd.group_id')
                        //     ->join('reinsurance_formula as rf', 'rf.id', '=', 'rfd.formula_id')
                        //     ->where('rg.group_id', $group_id)
                        //     ->where('rf.s_FormulaType', 'SURPLUS')
                        //    ->where('rf.reinsurance_type_id', '1') // 4
                        //   ->value('rfd.si_allocation');               
                           $SCL = (float)str_replace(",", "", $MasterDataArrayVale->si_allocation);
                       
                        if (is_null($SCL)) {
                            $SCL = 0;
                        } 
                        $QSCL = (float) str_replace(",", "", $QSCL);
                        $SCL = (float) str_replace(",", "", $SCL);

                        $TCL = (float) $QSCL + $SCL + $TreatySITotal;
                      
                      //  dd($TotalSumInsured,$QSCL,$SCL);
                        if ($TotalSumInsured > ($QSCL + $SCL)) {
                            if ($TotalSumInsured <= $TCL) {
                                $TreatySI = $TotalSumInsured - ($QSCL + $SCL);
                            } else {
                                $TreatySI = $TreatySITotal;
                            }
                        } else {
                            $TreatySI = 0;
                        }
                        $TreatyPremium = round(($TotalPremium * $TreatySI / $TotalSumInsured), 4);
                        $UsedFormula = 'Y';


                    } elseif ($MasterDataArrayVale->formula_type == 'FACULATIVEPLACEMENT') {


                        $TreatyPremiumData = (float) ($MasterDataArrayVale->TotalPremium);
                        $TotalSumInsuredData = (float) ($MasterDataArrayVale->TotalSumInsured);
                        $QSCData = (float) str_replace(",", "", $MasterDataArrayVale->si_allocation);

                        if ($TotalSumInsuredData > $QSCData) {

                            $FPQ = $TotalSumInsuredData - $QSCData;

                            //  $TreatyPremium = round((($TreatyPremiumData*$FPQ)/$TotalSumInsuredData),2);
                            $TreatySI = round((($FPQ)), 4);

                            $TreatyPercentage = ($TreatySI / (float) $TotalSumInsuredData);


                            $TreatyPremium = ($TreatyPremiumData * $TreatyPercentage);
                            //       dd($TreatyPremiumData,$TreatyPercentage,$TreatyPremium);
                            $UsedFormula = 'Y';
                        }
                    }

                    $policyReinsurrance = array(
                        'product_id' => $policy->product_id ?? "",
                        'risk_id' => $MasterDataArrayVale->risk_address_id ?? "",
                        'policy_id' => $policy->id ?? "",
                        'term_id' => $termId ?? "",
                        'action_id' => $actionId ?? "",
                        'group_id' => $MasterDataArrayVale->group_id ?? "",
                        'formula_id' => $MasterDataArrayVale->formula_id ?? "",
                        'coverage_id' => $MasterDataArrayVale->coverage_id ?? "",
                        'treaty_id' => $MasterDataArrayVale->treaty_id ?? "",
                        'type_id' => $MasterDataArrayVale->reinsurance_type_id ?? "",
                        'totalSumInsured' => $MasterDataArrayVale->TotalSumInsured,
                        'totalPremium' => $MasterDataArrayVale->TotalPremium ?? "",
                        'treatySI' => $TreatySI ?? "",
                        'treatyPercentage' => $TreatyPercentage ?? "",
                        'treatyPremium' => $TreatyPremium ?? "",
                        'UsedFormula' => $UsedFormula ?? "",
                        'added_by' => $userId ?? "",
                        'created_at' => $currentDateTime
                    );

                    PolicyReinsurance::insert($policyReinsurrance);


                }
            }//Master Foreach End

        }
        //-----------------------------------

        // if (isset($checkGoodItCoverpresentorNot) && count($checkGoodItCoverpresentorNot) > 0) {
        // //     $DetailQuerygoods = "select pot.policy_id,cvgm.id as cvgmId,cvgm.coverage_id as mainCoverID, COALESCE(veh.vehicle_type,0) as n_TypeOfMotor, 
        // //     risk.id, cvgsm.id as pocoverage_detail_id,
         
        // //     CASE 
        // //     WHEN cvgsm.coverage_value != 0.00 THEN cvgsm.coverage_value 
        // //     ELSE 
        // //         CASE 
        // //             WHEN cvgsm.ratefactor_value != 0 THEN cvgsm.ratefactor_value 
        // //             ELSE REPLACE(cvgsm.ratefactor_AnnualWages , ',', '')
        // //         END         
        // //     END AS coverage_value,      
        // //     cvgsm.calculated_value, 
        // //     cvgsm.coverage_id as subcoverage_id,
        // //     cvgm.risk_address_id,T.*
        // //     from policy_actions  pot
        // //     left join policies pol on pol.id=pot.policy_id
        // //     left join policy_coverages cvgm on cvgm.action_id = pot.id
        // //     left join policy_coverage_detail cvgsm ON cvgsm.policy_coverage_id=cvgm.id
        // //     left join policy_coverage_entities cvget ON cvget.policy_coverage_id=cvgm.id
        // //     left join vehicle veh ON cvget.entity_id=veh.id
        // //     left join risk_address risk on risk.id = cvgm.risk_address_id
        // //     left join policy_specified_items psi ON psi.policy_coverage_id=cvgm.id
        // //     LEFT JOIN             
        // //     (SELECT tc.id as tID,gd.coverage_id,tm.id as n_TreatyMaster_PK,
        // //     gm.product_id,fm.product_id as n_Product_FK_fm,
        // //     gm.id as n_GroupMaster_PK,gm.group_name,fm.id as n_FormulaMaster_PK, fm.type_id,fm.formula_name, fm.s_FormulaType,fd.operator,
        // //     fd.si_allocation,fd.n_ValueLimitsBetween,fd.percentage, gd.si_premium, gd.ri_limit, gd.limit_value, COALESCE(fd.vehicle_type,0)  as n_MotorType
        // //     FROM reinsurance_treaty tm
        // //     LEFT JOIN reinsurance_treaty_details td ON tm.id = td.treaty_id
        // //     LEFT JOIN reinsurance_formula fm ON td.formula_attached = fm.id
        // //     LEFT JOIN reinsurance_formula_details fd ON fm.id = fd.formula_id
        // //     LEFT JOIN reinsurance_group gm ON fd.group_id = gm.id
        // //     LEFT JOIN reinsurance_group_coverage gd ON gm.id = gd.group_id
        // //     INNER JOIN tb_cvgpccoverages tc ON tc.s_CoverageCode = gd.coverage_name
        // //     WHERE tm.effective_from <= '" . $currentDate . "'
        // //             AND tm.effective_to >= '" . $currentDate . "'
        // //            #AND gm.group_name = 'MISC_COM'
        // //     ) as T
        // //     ON T.tID=cvgsm.coverage_id
        // //     AND T.n_MotorType = COALESCE(veh.vehicle_type,0)
        // //     where pot.id= '" . $actionId . "'
        // //    and group_name = 'GOODSINTRANSIT_COM'
        // //    AND pol.product_id=T.product_id
        // //    AND cvgm.deleted_at IS NULL
         
        // //     GROUP BY pocoverage_detail_id,n_TreatyMaster_PK,
        // //     -- GROUP BY pot.policy_id, pot.id, risk.id,cvgm.coverage_id,
        // //     case
        // //         when  T.type_id = 35 then cvgsm.coverage_id
        // //         else ''       
        // //         end
        // //     order by risk.id, cvgm.coverage_id, T.group_name";

        // //     $DetailDataArray = DB::select(DB::raw($DetailQuerygoods));
        // //     // dd($DetailDataArray);
        // //     $coverageIds = array_map(fn($data) => $data->cvgmId, $DetailDataArray);
        // //     // dd($coverageIds);
        // //     // Fetch sum_insured and calculated_value for all policy_coverage_ids in one query
        // //     $misItemsData = DB::select(DB::raw("
        // //         select policy_specified_items.policy_coverage_id,coverage_id, SUM(sum_insured) as sum_insured, 
        // //         SUM(calculated_value) as calculated_value from policy_specified_items
        // //         INNER JOIN policy_coverages ON policy_coverages.id = policy_specified_items.policy_coverage_id
        // //         where policy_coverage_id IN ('" . implode("','", $coverageIds) . "')
        // //         AND policy_specified_items.deleted_at IS NULL
        // //         GROUP BY policy_specified_items.policy_coverage_id
        // //     "));

        // //     // Loop through query results and organize data
        // //     foreach ($misItemsData as $misItem) {
        // //         $coverageId = $misItem->coverage_id;
        // //         // Ensure sum_insured is treated as a float.
        // //         $misItemsMap[$coverageId] = [
        // //             'coverageId' => $coverageId,
        // //             'sum_insured' => (float) $misItem->sum_insured,
        // //             'calculated_value' => (float) $misItem->calculated_value,
        // //         ];
        // //     }

        // //     $processedCovers = []; // Array to track processed mainCoverIDs
        // //     foreach ($DetailDataArray as $key => $DetailData) {
        // //         // echo "<pre>";
        // //         // print_r($DetailData);
        // //         $SumInsured = 0.00;
        // //         $SumInsuredPre = 0;
        // //         $sum_insured = 0;
        // //         $mainCoverID = $misItemsMap[$DetailData->mainCoverID]['coverageId'] ?? 0;
        // //         $sum_insured = $misItemsMap[$DetailData->mainCoverID]['sum_insured'] ?? 0;
        // //         $calculated_value = $misItemsMap[$DetailData->mainCoverID]['calculated_value'] ?? 0;

        // //         if ($DetailData->ri_limit == 1) {

        // //             if (!isset($processedCovers[$DetailData->mainCoverID])) {

        // //                 // First time encountering this mainCoverID, include sum_insured
        // //                 $SumInsured = $DetailData->coverage_value + $sum_insured;
        // //                 $SumInsuredPre = $DetailData->calculated_value + $calculated_value;

        // //                 // Mark this mainCoverID as processed
        // //                 $processedCovers[$DetailData->mainCoverID] = true;

        // //             } else {
        // //                 // Already processed, do not add sum_insured again
        // //                 $SumInsured = $DetailData->coverage_value;
        // //                 $SumInsuredPre = $DetailData->calculated_value ?? "";
        // //             }

        // //         } else {

        // //             if (!isset($processedCovers[$DetailData->mainCoverID])) {
        // //                 if (is_numeric($DetailData->limit_value)) {
        // //                     $SumInsured = (float) $DetailData->limit_value + (float) $sum_insured;
        // //                 } else {
        // //                     $SumInsured = (float) $sum_insured;
        // //                 }


        // //                 // Mark this mainCoverID as processed
        // //                 $processedCovers[$DetailData->mainCoverID] = true;
        // //             } else {

        // //                 // Already processed, do not add sum_insured again
        // //                 $SumInsured = $DetailData->limit_value;
        // //             }
        // //         }


        // //         $policyReinsurrance = array(
        // //             'product_id' => $DetailData->product_id ?? "",
        // //             'coverage_id' => $DetailData->coverage_id ?? "",
        // //             'subcoverage_id' => $DetailData->subcoverage_id ?? "",
        // //             'pocoverage_detail_id' => $DetailData->pocoverage_detail_id ?? "",
        // //             'action_id' => $actionId ?? "",
        // //             'policy_id' => $DetailData->policy_id ?? "",
        // //             'group_id' => $DetailData->n_GroupMaster_PK ?? "",
        // //             'treaty_id' => $DetailData->n_TreatyMaster_PK ?? "",
        // //             'formula_id' => $DetailData->n_FormulaMaster_PK ?? "",
        // //             'risk_address_id' => $DetailData->risk_address_id ?? "",
        // //             's_FormulaType' => $DetailData->s_FormulaType ?? "",
        // //             'n_SumInsured' => $SumInsured,
        // //             'n_Premium' => $SumInsuredPre,
        // //         );
        // //         // echo "<pre>";
        // //         // print_r($policyReinsurrance);
        // //         $d = PolicyReinsuranceDetails::insert($policyReinsurrance);
        // //     }
        //     // dd("done");
        //      $deleteMaster = PolicyReinsurance::where('action_id', operator: $actionId)->where('group_id',8)->delete();

        //     //-------------------------------------
          
        //     $MasterQuery = "SELECT DISTINCT fm.reinsurance_type_id,gm.group_code,fm.type_id,fm.s_FormulaType  as formula_type, fd.formula_id,
        //         fd.operator,fd.si_allocation,fd.n_ValueLimitsBetween,fd.percentage,
        //         prid.*
        //         ,prid.n_SumInsured AS TotalSumInsured, prid.n_Premium AS TotalPremium,tm.id
        //         from policy_reinsurance_details prid
        //         left join reinsurance_group gm on gm.id = prid.group_id
        //         left join reinsurance_formula_details fd on fd.group_id = gm.id
        //         left join policy_coverage_detail sub on sub.id = prid.pocoverage_detail_id
        //         left join motor mot on mot.policy_coverage_id = prid.pocoverage_detail_id
        //         left join reinsurance_formula fm on fm.id = fd.formula_id
        //         left join reinsurance_treaty_details td on td.formula_attached = fm.id
        //         left join reinsurance_treaty tm on tm.id = td.treaty_id
        //         where prid.action_id = '" . $actionId . "'
        //         AND fd.operator IS NOT NULL
        //         AND gm.group_code IN ('GOODSINTRANSIT_COM')
        //         AND tm.effective_from <= '" . $currentDate . "'
        //         AND tm.effective_to >= '" . $currentDate . "'";

        //     $MasterDataArray = DB::select(DB::raw($MasterQuery));
        //     // dd($MasterQuery);
        //     $TSI_Excess = '';
        //     $golbalNet = 0;
        //     $golbalQuota = 0;
        //     $FacPlaceAmt = 0;
        //     $QSCShare = 0;
        //     $QSCNoNMotorShare = 0;
        //     $FacPlaceFinalAMT = 0;
        //     foreach ($MasterDataArray as $MasterDataArrayVale) {

        //         if ($MasterDataArrayVale->reinsurance_type_id != NULL) {
        //             $TreatySI = 0;
        //             $TreatyPercentage = 0;
        //             $TreatyPremium = 0;
        //             $ExpressionSign = null;
        //             if ($MasterDataArrayVale->operator == 1) {
        //                 $ExpressionSign = '=';
        //             } elseif ($MasterDataArrayVale->operator == 2) {
        //                 $ExpressionSign = '<';
        //             } elseif ($MasterDataArrayVale->operator == 3) {
        //                 $ExpressionSign = '<=';
        //             } elseif ($MasterDataArrayVale->operator == 4) {
        //                 $ExpressionSign = '>';
        //             } elseif ($MasterDataArrayVale->operator == 5) {
        //                 $ExpressionSign = '>=';
        //             } elseif ($MasterDataArrayVale->operator == 6) {
        //                 $ExpressionSign = '!=';
        //             } elseif ($MasterDataArrayVale->operator == 7) {
        //                 $ExpressionSign = 'Between';
        //             } elseif ($MasterDataArrayVale->operator == 8) {
        //                 $ExpressionSign = 'Not Between';
        //             } elseif ($MasterDataArrayVale->operator == 9) {
        //                 $ExpressionSign = '*';
        //             }
        //             $UsedFormula = 'N';
        //             $type = Lookup::where('id', $MasterDataArrayVale->type_id)->where('key', 'reinsurance_formula_key')->first()?->value;


        //             // if ($MasterDataArrayVale->formula_type == 'OTHER') {
        //             //     if ($ExpressionSign == '<') {
        //             //         if ($MasterDataArrayVale->TotalSumInsured < $MasterDataArrayVale->si_allocation) {
        //             //             $TreatySI = round((($MasterDataArrayVale->TotalSumInsured * $MasterDataArrayVale->percentage) / 100), 4);
        //             //             $TreatyPercentage = $MasterDataArrayVale->percentage;
        //             //         } else {
        //             //             $TreatySI = round((($MasterDataArrayVale->si_allocation * $MasterDataArrayVale->percentage) / 100), 4);
        //             //             $TreatyPercentage = round((($TreatySI / $MasterDataArrayVale->TotalSumInsured) * 100), 4);
        //             //         }
        //             //     }
        //             //     $TreatyPremium = round((($MasterDataArrayVale->TotalPremium * $TreatyPercentage) / 100), 4);
        //             //     $UsedFormula = 'Y';
        //             // }
        //             //  else 
        //                 if ($MasterDataArrayVale->formula_type == 'TSI') {


        //                 $QSC = str_replace(",", "", $MasterDataArrayVale->si_allocation);
        //                 $QSCNoNMotorShare = str_replace(",", "", $MasterDataArrayVale->si_allocation);

        //                 if ($MasterDataArrayVale->TotalSumInsured < $MasterDataArrayVale->si_allocation) {
        //                     $TSI_Excess = 0.00;
        //                     //$TreatySI = $TSI_Excess;
        //                 } else {
        //                     $TSI_Excess = round(((float) $MasterDataArrayVale->TotalSumInsured - (float) $MasterDataArrayVale->si_allocation), 4);
        //                     //$TreatySI = $TSI_Excess;
        //                 }

        //                 if ($MasterDataArrayVale->TotalSumInsured > $QSC) {
        //                     $PQSC = round((($MasterDataArrayVale->TotalPremium * $QSC) / $MasterDataArrayVale->TotalSumInsured), 4);
        //                 } else {
        //                     $PQSC = round((($MasterDataArrayVale->TotalPremium)), 4);
        //                 }
        //                 //echo $PQSC;
        //                 $TreatyPercentage = round((($MasterDataArrayVale->percentage) / 100), 4);
        //                 // dd($TreatyPercentage,$PQSC,($PQSC*$TreatyPercentage));
        //                 $TreatyPremium = round((($PQSC * $TreatyPercentage)), 4);
        //                 $TreatySI = round((($QSC * $TreatyPercentage)), 4);
        //                 $UsedFormula = 'Y';
        //                 //  dd($TreatyPremium,$TreatySI);
        //             } 
        //             elseif ($MasterDataArrayVale->formula_type == 'SURPLUS') {
        //                 $SCL = str_replace(",", "", $MasterDataArrayVale->si_allocation);
        //                 $TotalSumInsured = $MasterDataArrayVale->TotalSumInsured;
        //                 $TotalPremium = $MasterDataArrayVale->TotalPremium;
        //                 $QSCL = ($TotalSumInsured <= $QSCNoNMotorShare) ? $STotalSumInsuredI : $QSCNoNMotorShare;
        //                 $AFCL = ($SCL + $QSCNoNMotorShare);
        //                 if ($TSI_Excess < $MasterDataArrayVale->si_allocation) {
        //                     $TreatySI = $TSI_Excess;
        //                 } else {
        //                     $TreatySI = $MasterDataArrayVale->si_allocation;
        //                 }
        //                 $TreatyPercentage = 0;
        //                 $TreatyPremium = 0;

        //                 if ($TotalSumInsured > $QSCL) {
        //                     if ($TotalSumInsured <= $SCL) {
        //                         $FinalPremium = $TotalSumInsured - $QSCNoNMotorShare;
        //                         $TreatyPercentage = ((float) $FinalPremium / (float) $TotalSumInsured);
        //                         $TreatyPremium = round((($TotalPremium * $TreatyPercentage)), 2);
        //                         $TreatySI = round((($SCL)), 2);
        //                         $UsedFormula = 'Y';
        //                     } else {
        //                         if (($TotalSumInsured - $QSCNoNMotorShare) <= $SCL) {

        //                             $FinalPremium = $TotalSumInsured - $QSCNoNMotorShare;
        //                             //$TreatyPercentage = round((((float)$FinalPremium/(float)$TotalSumInsured)*100),2);
        //                             $TreatyPercentage = ((float) $FinalPremium / (float) $TotalSumInsured);
        //                             $TreatyPremium = round((($TotalPremium * $TreatyPercentage)), 2);
        //                             $TreatySI = round((($FinalPremium)), 2);
        //                             $UsedFormula = 'Y';
        //                         } else {

        //                             $FinalPremium = $SCL;
        //                             $TreatyPercentage = ((float) $FinalPremium / (float) $TotalSumInsured);
        //                             $TreatyPremium = round((($TotalPremium * $TreatyPercentage)), 2);
        //                             $TreatySI = round((($SCL)), 2);
        //                             $UsedFormula = 'Y';
        //                         }
        //                     }
        //                 }

        //                 //dd($TreatyPremium,$TreatySI);

        //             } elseif ($MasterDataArrayVale->formula_type == 'FACULTATIVE') {
        //                 $TotalSumInsured = $MasterDataArrayVale->TotalSumInsured;
        //                 $TotalPremium = $MasterDataArrayVale->TotalPremium;
        //                 $TreatySI = str_replace(",", "", $MasterDataArrayVale->si_allocation);
        //                 if ($TotalSumInsured >= $TreatySI) {
        //                     $finalTotalPer = ($TotalSumInsured - $TreatySI);
        //                     if ($finalTotalPer >= $TreatySI) {
        //                         $TreatyPremium = round(($TotalPremium * $TreatySI / $TotalSumInsured), 4);
        //                         $TreatySI = round((($TreatySITotal)), 4);
        //                         $UsedFormula = 'Y';
        //                     } else {
        //                         $TreatyPremium = round(($TotalPremium * $finalTotalPer / $TotalSumInsured), 4);
        //                         $TreatySI = round((($finalTotalPer)), 4);
        //                         $UsedFormula = 'Y';
        //                     }


        //                 }
        //                 //dd($TreatyPremium,$TreatySI);      
        //             } elseif ($MasterDataArrayVale->formula_type == 'FACULATIVEPLACEMENT') {
        //                 $TreatyPremiumData = round($MasterDataArrayVale->TotalPremium);
        //                 $TotalSumInsuredData = round($MasterDataArrayVale->TotalSumInsured);
        //                 $QSCData = str_replace(",", "", $MasterDataArrayVale->si_allocation);
        //                 if ($TotalSumInsuredData > $QSCData) {
        //                     $FPQ = $TotalSumInsuredData - $QSCData;
        //                     $TreatyPremium = round((($TreatyPremiumData * $FPQ) / $TotalSumInsuredData), 2);
        //                     $TreatySI = round((($FPQ)), 4);
        //                     $UsedFormula = 'Y';
        //                 }
        //             }

        //             $policyReinsurrance = array(
        //                 'product_id' => $policy->product_id ?? "",
        //                 'risk_id' => $MasterDataArrayVale->risk_address_id ?? "",
        //                 'policy_id' => $policy->id ?? "",
        //                 'term_id' => $termId ?? "",
        //                 'action_id' => $actionId ?? "",
        //                 'group_id' => $MasterDataArrayVale->group_id ?? "",
        //                 'formula_id' => $MasterDataArrayVale->formula_id ?? "",
        //                 'coverage_id' => $MasterDataArrayVale->coverage_id ?? "",
        //                 'treaty_id' => $MasterDataArrayVale->treaty_id ?? "",
        //                 'type_id' => $MasterDataArrayVale->reinsurance_type_id ?? "",
        //                 'totalSumInsured' => $MasterDataArrayVale->TotalSumInsured,
        //                 'totalPremium' => $MasterDataArrayVale->TotalPremium ?? "",
        //                 'treatySI' => $TreatySI ?? "",
        //                 'treatyPercentage' => $TreatyPercentage ?? "",
        //                 'treatyPremium' => $TreatyPremium ?? "",
        //                 'UsedFormula' => $UsedFormula ?? "",
        //                 'added_by' => $userId ?? "",
        //                 'created_at' => $currentDateTime
        //             );
        //             //     echo "<pre>";
        //             //  print_r($policyReinsurrance);
        //             //  echo "</pre>";
        //             PolicyReinsurance::insert($policyReinsurrance);


        //         }
        //     }//Master Foreach End
        //     // dd("done");

        // }

        //$deleteMaster = PolicyReinsurance::where('action_id', $actionId)->where('n_TreatyPremium', 0)->where('n_TreatyPercentage', 0)->where('n_TreatySI', 0)->delete();
        // return true;

        // Hear We Check Fac Placement is Exisist in System For Particular Transaction.
        // $TbPorifacmaster = new TbPorifacmaster();
        $CheckFacPlacement = TbPorifacmaster::select('id', 'n_FacShare', 'policy_id', 's_AllocationBasis')
            ->where('action_id', $actionId)
            ->where('s_Approved', 'Y')
            ->get();

        if (count($CheckFacPlacement) > 0) {
            // Delete Entry Of TSI,SURPLUS,FACULTATIVE From RI Master For This Transaction.
            $FormulaDeleteQuery = "delete rim.* from  policy_reinsurance rim
                                    left join reinsurance_formula fm
                                    on fm.id = rim.formula_id
                                    where rim.action_id = $actionId
                                    and (rim.formula_id = 0
                                    or fm.s_FormulaType in('TSI','SURPLUS','FACULTATIVE'))";

            $FormulaQuery = DB::select(DB::raw($FormulaDeleteQuery));

            // Now Read Remaining Data From RI Master & Update Data Base On Fac Placement.
            $RI_Master_Query = "select rim.id,rim.product_id,rim.risk_id,rim.policy_id,rim.term_id,rim.action_id,rim.group_id,
                                                               rim.formula_id,rim.coverage_id,rim.treaty_id,rim.totalSumInsured,rim.totalPremium,
                                                               fm.s_FormulaType,fm.reinsurance_type_id,fd.percentage
                                                               from policy_reinsurance rim
                                                               left join reinsurance_formula fm
                                                               on fm.id = rim.formula_id
                                                               left join reinsurance_formula_details fd
                                                               on fd.formula_id = fm.id
                                                               where rim.action_id = $actionId
                                                               AND treatyPremium!=0";

            $RI_MasterQuery = DB::select(DB::raw($RI_Master_Query));

            if (count($RI_MasterQuery) > 0) {
                foreach ($RI_MasterQuery as $RI_MasterQueryVal) {
                    // Read Data From FAC Placement base on Transacrion, risk, RIGroup,  n_PoCoverageSubMaster_FK
                    $Fac_MasterQueryResult = [];
                    $Fac_MasterQueryCount = TbPorifacmaster::leftJoin('tb_porifacdetails', 'tb_porifacmasters.id', '=', 'tb_porifacdetails.porifacmasters_id')
                        ->where('tb_porifacdetails.n_PORiskMaster_FK', $RI_MasterQueryVal->risk_id)
                        ->where('tb_porifacdetails.group_id', $RI_MasterQueryVal->group_id)
                        ->where('tb_porifacdetails.n_PoCoverageSubMaster_FK', $RI_MasterQueryVal->coverage_id)
                        ->where('tb_porifacdetails.n_FacPremium', '!=', '0')->whereNotNull('tb_porifacdetails.n_FacPremium')
                        ->select('tb_porifacmasters.id', 'tb_porifacmasters.n_FacShare', 'tb_porifacmasters.policy_id', 'tb_porifacmasters.s_AllocationBasis')
                        ->count();


                    if ($Fac_MasterQueryCount == 0) {
                        //insert into Fac details.

                        $SharePercent = 0.00;
                        if ($CheckFacPlacement->s_AllocationBasis == 'POLICY') {
                            $SharePercent = $CheckFacPlacement->n_FacShare;
                        }

                        $FacSumInsured = 0.00;
                        $FacPremium = 0.00;

                        $FacSumInsured = (($RI_MasterQueryVal->totalSumInsured * $SharePercent) / 100);
                        $FacPremium = (($RI_MasterQueryVal->totalPremium * $SharePercent) / 100);

                        $InsertFacDetails = array(
                            'porifacmasters_id' => $CheckFacPlacement->id ?? "",
                            'n_PORiskMaster_FK' => $RI_MasterQueryVal->risk_id ?? "",
                            'group_id' => $RI_MasterQueryVal->group_id ?? "",
                            's_RIGroupCode' => NULL,
                            'n_PoCoverageSubMaster_FK' => $RI_MasterQueryVal->coverage_id ?? "",
                            'n_SharePercent' => $SharePercent ?? "",
                            's_PolicyCurrency' => 'PULA',
                            'n_PolicySumInsured' => $RI_MasterQueryVal->totalSumInsured ?? "",
                            'n_PolicyPremium' => $RI_MasterQueryVal->totalPremium ?? "",
                            'n_AverageRate' => NULL,
                            's_FacCurrency' => 'PULA',
                            'n_FacRate' => NULL ?? "",
                            'n_FacSumInsured' => $FacSumInsured ?? "",
                            'n_FacPremium' => $FacPremium ?? "",
                            'added_by' => $userId ?? "",
                            'created_at' => $currentDateTime
                        );
                        TbPorifacdetail::insert($InsertFacDetails);

                    }

                    $Fac_MasterQueryResult = TbPorifacmaster::leftJoin('tb_porifacdetails', 'tb_porifacmasters.id', '=', 'tb_porifacdetails.porifacmasters_id')
                        ->where('tb_porifacmasters.action_id', $actionId)
                        ->where('tb_porifacdetails.n_PORiskMaster_FK', $RI_MasterQueryVal->risk_id)
                        ->where('tb_porifacdetails.group_id', $RI_MasterQueryVal->group_id)
                        ->where('tb_porifacdetails.n_PoCoverageSubMaster_FK', $RI_MasterQueryVal->coverage_id)
                        ->whereNot('coalesce(tb_porifacdetails.n_FacPremium,0)', '0')
                        ->select(
                            'tb_porifacmasters.id',
                            'tb_porifacmasters.policy_id',
                            'tb_porifacmasters.action_id',
                            'tb_porifacmasters.s_AllocationBasis',
                            'tb_porifacmasters.s_FacPlacmentNo',
                            'tb_porifacdetails.n_FacSumInsured',
                            'tb_porifacdetails.n_FacPremium',
                            'tb_porifacdetails.n_SharePercent'
                        )
                        ->first();


                    if ($Fac_MasterQueryResult) {
                        // Update RI Master Table By Following Calculation Based On Fac Placement.
                        $BalanceSumInsured = 0.00;
                        $FacSumInsured = 0.00;
                        $FacPremium = 0.00;

                        $FacSumInsured = (($RI_MasterQueryVal->totalSumInsured * $Fac_MasterQueryResult->n_SharePercent) / 100);
                        $FacPremium = (($RI_MasterQueryVal->totalPremium * $Fac_MasterQueryResult->n_SharePercent) / 100);

                        $BalanceSumInsured = $RI_MasterQueryVal->totalSumInsured - $FacSumInsured;
                        $BalancePremium = $RI_MasterQueryVal->totalPremium - $FacPremium;
                        $Treaty_SI = ($BalanceSumInsured * ($RI_MasterQueryVal->percentage / 100));
                        $Treaty_Premium = ($BalancePremium * ($RI_MasterQueryVal->percentage / 100));
                        $TreatyPercentage = (($Treaty_Premium / $RI_MasterQueryVal->totalPremium) * 100);
                        //$TreatyPercentage = (($Treaty_SI/$RI_MasterQueryVal->totalSumInsured)*100);


                        $UpdateRIMaster = "update policy_reinsurance
                                            set treatySI =$Treaty_SI,
                                                treatyPercentage=$TreatyPercentage,
                                                treatyPremium=$Treaty_Premium,
                                                porifacmasters=" . $Fac_MasterQueryResult->id . ",
                                                added_by=" . $userId . ",
                                                updated_at='" . $currentDateTime . "'
                                            where id=" . $RI_MasterQueryVal->id . "";

                        $RIMasterQuery = DB::select(DB::raw($UpdateRIMaster));
                    }

                }
            }

            // Insert Fac Entry into RI Masters From Fac Placement.
            $RI_Master_Query = array();
            $RI_Master_Query = "select rim.id,rim.product_id,rim.risk_id,rim.policy_id,rim.term_id,rim.action_id,rim.group_id,
                                                               rim.formula_id,rim.coverage_id,rim.treaty_id,rim.totalSumInsured,rim.totalPremium,
                                                               fm.s_FormulaType,fm.reinsurance_type_id,fd.percentage
                                                               from policy_reinsurance rim
                                                               left join reinsurance_formula fm
                                                               on fm.id = rim.formula_id
                                                               left join reinsurance_formula_details fd
                                                               on fd.formula_id = fm.id
                                                               where rim.action_id = $actionId
                                                                AND rim.n_TreatyPremium!=0
                                                                group by rim.risk_id,rim.group_id,rim.coverage_id";

            $RI_MasterQuery = DB::select(DB::raw($RI_Master_Query));

            $queryPart = '';
            $facDetailData = array();

            if (count($RI_MasterQuery) > 0) {
                foreach ($RI_MasterQuery as $RI_MasterQueryValue) {
                    $facDetailQuery = array();

                    $facDetailData = TbPorifacmaster::leftJoin('tb_porifacdetails', 'tb_porifacmasters.id', '=', 'tb_porifacdetails.porifacmasters_id')
                        ->leftJoin('policies', 'policies.id', '=', 'tb_porifacmasters.policy_id')
                        ->where('tb_porifacmasters.action_id', $actionId)
                        ->where('tb_porifacdetails.n_PORiskMaster_FK', $RI_MasterQueryVal->risk_id)
                        ->where('tb_porifacdetails.group_id', $RI_MasterQueryVal->group_id)
                        ->where('tb_porifacdetails.n_PoCoverageSubMaster_FK', $RI_MasterQueryVal->coverage_id)
                        ->whereNot('coalesce(tb_porifacdetails.n_FacPremium,0)', '0')
                        ->select(
                            'policies.product_id',
                            'tb_porifacmasters.n_PORiskMaster_FK',
                            'tb_porifacmasters.policy_id',
                            'tb_porifacmasters.action_id',
                            'tb_porifacmasters.group_id',
                            'tb_porifacmasters.n_PoCoverageSubMaster_FK',
                            'tb_porifacmasters.n_PolicySumInsured',
                            'tb_porifacmasters.n_PolicyPremium',
                            'tb_porifacmasters.n_FacSumInsured',
                            'tb_porifacmasters.n_SharePercent',
                            'tb_porifacmasters.n_FacPremium',
                            'tb_porifacmasters.id',
                            'tb_porifacmasters.id'
                        )
                        ->first();

                    $SharePercent = 0.00;
                    $SharePercent = $facDetailData->n_SharePercent;


                    $FacSumInsured = 0.00;
                    $FacPremium = 0.00;

                    $FacSumInsured = (($RI_MasterQueryValue->totalSumInsured * $SharePercent) / 100);
                    $FacPremium = (($RI_MasterQueryValue->totalPremium * $SharePercent) / 100);


                    $policyReinsurranceResult = array(
                        'product_id' => $facDetailData->product_id ?? "",
                        'risk_id' => $facDetailData->n_PORiskMaster_FK ?? "",
                        'policy_id' => $facDetailData->policy_id ?? "",
                        'term_id' => $term_id ?? "",
                        'action_id' => $facDetailData->action_id ?? "",
                        'group_id' => $facDetailData->group_id ?? "",
                        'formula_id' => 0,
                        'coverage_id' => $facDetailData->n_PoCoverageSubMaster_FK ?? "",
                        'treaty_id' => NULL, //$ResultData[0]['T']['n_TreatyMaster_PK']??"",
                        'type_id' => NULL,
                        'totalSumInsured' => $RI_MasterQueryValue->totalSumInsured,
                        'totalPremium' => $RI_MasterQueryValue->totalPremium ?? "",
                        'treatySI' => $FacSumInsured ?? "",
                        'treatyPercentage' => $facDetailData->n_SharePercent ?? "",
                        'treatyPremium' => $FacPremium ?? "",
                        'UsedFormula' => 'Y',
                        'PORIFacMasters' => $facDetailData->id ?? "",
                        'PORIFacDetails' => $facDetailData->id ?? "",
                        'added_by' => $userId ?? "",
                        'created_at' => $currentDateTime,
                        'updated_at' => $currentDateTime
                    );
                    PolicyReinsurance::insert($policyReinsurranceResult);
                }
            }
        }
        //END FAC Placement Code.
    }

    public static function MotorComReinsurance($currentDate, $actionId, $currentDateTime, $policyId, $termId, $productId)
    {
        if ($productId == 8) {
            $group_code = 'MOTOR_DOM';
            $groupby = '';
        } else {
            $group_code = 'MOTOR_COM';
            $groupby = 'GROUP BY fd.formula_id';
        }
        $MasterQuery = "WITH SummedInsured AS (
            SELECT
                fd.formula_id,
                (prid.n_SumInsured) as SumInsured,
                (prid.n_Premium) as Premium 
            FROM policy_reinsurance_details prid
            LEFT JOIN reinsurance_group gm ON gm.id = prid.group_id
            LEFT JOIN reinsurance_formula_details fd ON fd.group_id = gm.id
            WHERE prid.action_id = '" . $actionId . "'
            and coverage_id IN (22,27) 
            " . $groupby . "
        ) 
        SELECT DISTINCT
            fm.reinsurance_type_id,
            gm.group_code,
            gm.id as group_id,
            fm.type_id,
            fm.s_FormulaType AS formula_type,
            fm.reinsurance_type_id AS reinsurance_type,
            fd.formula_id,
            COALESCE(veh.vehicle_type,0) AS n_TypeOfMotor,
            fd.operator,
            fd.si_allocation,
            fd.n_ValueLimitsBetween,
            fd.percentage,            
            prid.n_Premium AS TotalPremium,
            prid.n_SumInsured AS TotalSumInsured,
            tm.id as treaty_id,
            prid.policy_id,
            prid.risk_address_id,
            prid.pocoverage_detail_id
        FROM policy_reinsurance_details prid
        INNER JOIN reinsurance_group gm ON gm.id = prid.group_id
        INNER JOIN reinsurance_formula_details fd ON fd.group_id = gm.id
        LEFT JOIN SummedInsured si ON si.formula_id = fd.formula_id
        LEFT JOIN policy_coverage_detail sub ON sub.id = prid.pocoverage_detail_id
        LEFT JOIN motor mot ON mot.policy_coverage_id = prid.pocoverage_detail_id
        LEFT JOIN policy_coverage_entities cvget ON cvget.policy_coverage_id = sub.policy_coverage_id
        LEFT JOIN vehicle veh ON cvget.entity_id = veh.id
        INNER JOIN reinsurance_formula fm ON fm.id = fd.formula_id
        INNER JOIN reinsurance_treaty_details td ON td.formula_attached = fm.id
        INNER JOIN reinsurance_treaty tm ON tm.id = td.treaty_id
        WHERE COALESCE(veh.vehicle_type,0) = COALESCE(fd.vehicle_type,0)
        AND prid.action_id = '" . $actionId . "'
        AND gm.group_code = '" . $group_code . "'
        AND fd.operator IS NOT NULL
        AND tm.effective_from <= '" . $currentDate . "'
        AND tm.effective_to >= '" . $currentDate . "'
        GROUP BY prid.policy_id,prid.action_id,prid.risk_address_id,prid.group_id,fd.formula_id,
        case
            when  fm.type_id = 35 then prid.pocoverage_detail_id
            else ''
        end 
        ORDER BY fd.formula_id
        ";
        // echo  $MasterQuery;
        $MasterDataArray = DB::select(DB::raw($MasterQuery));

        $TSI_Excess = '';
        $golbalNet = []; // Initialize array
        $golbalQuota = []; // Initialize array
        $FacPlaceAmt = 0;
        $QSCShare = 0;
        $FacPlaceFinalAMT = 0;
        $j = 0;                 
        foreach ($MasterDataArray as $key => $MasterDataArrayVale) {

            if ($MasterDataArrayVale->reinsurance_type_id != NULL) {
                $TreatySI = 0;
                $TreatyPercentage = 0;
                $TreatyPremium = 0;
                $ExpressionSign = null;
                if ($MasterDataArrayVale->operator == 1) {
                    $ExpressionSign = '=';
                } elseif ($MasterDataArrayVale->operator == 2) {
                    $ExpressionSign = '<';
                } elseif ($MasterDataArrayVale->operator == 3) {
                    $ExpressionSign = '<=';
                } elseif ($MasterDataArrayVale->operator == 4) {
                    $ExpressionSign = '>';
                } elseif ($MasterDataArrayVale->operator == 5) {
                    $ExpressionSign = '>=';
                } elseif ($MasterDataArrayVale->operator == 6) {
                    $ExpressionSign = '!=';
                } elseif ($MasterDataArrayVale->operator == 7) {
                    $ExpressionSign = 'Between';
                } elseif ($MasterDataArrayVale->operator == 8) {
                    $ExpressionSign = 'Not Between';
                } elseif ($MasterDataArrayVale->operator == 9) {
                    $ExpressionSign = '*';
                }
                $UsedFormula = 'N';
                $type = Lookup::where('id', $MasterDataArrayVale->type_id)->where('key', 'reinsurance_formula_key')->first()?->value;


                $ADNetLimit = 300000.0;

                // For MOTOR
                if ($MasterDataArrayVale->formula_type == 'OTHER') {
                    if ($ExpressionSign == '<') {
                        if ($MasterDataArrayVale->TotalSumInsured < $MasterDataArrayVale->si_allocation) {
                            $TreatySI = round((((float) $MasterDataArrayVale->TotalSumInsured * (float) $MasterDataArrayVale->percentage) / 100), 4);
                            $TreatyPercentage = $MasterDataArrayVale->percentage;
                            $UsedFormula = 'Y';
                        }
                    }
                    if ($ExpressionSign == 'Between') {
                        if (($MasterDataArrayVale->TotalSumInsured >= $MasterDataArrayVale->si_allocation) && ($MasterDataArrayVale->TotalSumInsured <= $MasterDataArrayVale->n_ValueLimitsBetween)) {
                            $TreatySI = round((((float) $MasterDataArrayVale->TotalSumInsured * (float) $MasterDataArrayVale->percentage) / 100), 4);
                            $TreatyPercentage = $MasterDataArrayVale->percentage;
                            $UsedFormula = 'Y';
                        }
                    }
                    if ($ExpressionSign == '>') {
                        if ($MasterDataArrayVale->TotalSumInsured > $MasterDataArrayVale->si_allocation) {
                            $TreatySI = round((((float) $MasterDataArrayVale->si_allocation * (float) $MasterDataArrayVale->percentage) / 100), 4);
                            $TreatyPercentage = round((float) $TreatySI / (float) $MasterDataArrayVale->TotalSumInsured * 100, 4);
                            //$TreatyPercentage = $MasterDataArrayVale->percentage;
                            $UsedFormula = 'Y';
                        }
                    }
                    $TreatyPremium = round((((float) $MasterDataArrayVale->TotalPremium * (float) $TreatyPercentage) / 100), 4);

                } elseif ($MasterDataArrayVale->formula_type == 'TSI') { 

                    $TreatyPercentage = 0;
                    $siAllocationAmt = str_replace(",", "", $MasterDataArrayVale->si_allocation);
                    $QSCShare = $siAllocationAmt;
                    $TotalSumInsured = $MasterDataArrayVale->TotalSumInsured;
                    if ($siAllocationAmt > $TotalSumInsured) {
                        $QSC = (float) $TotalSumInsured;

                    } else {
                        $QSC = (float) str_replace(",", "", $MasterDataArrayVale->si_allocation);
                    } 
// dd($MasterDataArrayVale );
                    $TotalPremium = $MasterDataArrayVale->TotalPremium;
                    $reinsuranceType = $MasterDataArrayVale->reinsurance_type;
                    if ($MasterDataArrayVale->TotalSumInsured < $MasterDataArrayVale->si_allocation) {
                        $TSI_Excess = 0.00;
                        //$TreatySI = $TSI_Excess;
                    } else {
                        $TSI_Excess = round(((float) $MasterDataArrayVale->TotalSumInsured - (float) $MasterDataArrayVale->si_allocation), 4);
                        //$TreatySI = $TSI_Excess;
                    }

                    if ($QSC * 0.2 <= $ADNetLimit) {
                        $NetRet = $QSC * 0.2;
                    } else {
                        $NetRet = $ADNetLimit;
                    }

                    $TreatyPercentage = ($NetRet / $TotalSumInsured);
                    $TreatyPremium = round(($TotalPremium * $TreatyPercentage), 2);
                    // $TreatySI = round((($QSC*$TreatyPercentage)),4);
                    $TreatySI = round((($NetRet)), 4);
                    $UsedFormula = 'Y';
                    if (!array_key_exists($TotalSumInsured, $golbalNet)) {
                        $golbalNet[$TotalSumInsured] = $NetRet;
                    }

                    if ($reinsuranceType != 3) {
                        $QuotaPer = $QSC * 0.8;
                        // dd($QSC,$QuotaPer);
                        //$golbalQuota = $QuotaPer;
                        $TreatyPercentage = $QuotaPer / $TotalSumInsured;
                        $TreatyPremium = round(($TotalPremium * $TreatyPercentage), 2);
                        // $TreatySI = round((($QSC*$TreatyPercentage)),4);
                        $TreatySI = round((($QuotaPer)), 4);
                        $UsedFormula = 'Y';

                        if (!in_array($QuotaPer, $golbalQuota)) {
                            $golbalQuota[$TotalSumInsured] = $QuotaPer;  // Push unique values
                        }
                    }

                    //$golbalNet = $TreatyPremium;
                    //echo " ** ".$golbalNet += $TreatyPremium;



                } elseif ($MasterDataArrayVale->formula_type == 'SURPLUS') {
                    $QSC = (float) str_replace(",", "", $MasterDataArrayVale->si_allocation);

                    if ($TSI_Excess < $MasterDataArrayVale->si_allocation) {
                        $TreatySI = $TSI_Excess;
                    } else {
                        $TreatySI = $MasterDataArrayVale->si_allocation;
                    }

                    $TreatyPercentage = 0;
                    if ($TreatySI != 0.0 && $MasterDataArrayVale->TotalSumInsured != 0.0) {
                        $TreatyPercentage = round((((float) $TreatySI / (float) $MasterDataArrayVale->TotalSumInsured) * 100), 4);
                    }
                    if ($MasterDataArrayVale->TotalSumInsured > $QSC) {
                        //$QSC  = str_replace(",","",$MasterDataArrayVale->si_allocation);
                        //echo $MasterDataArrayVale->TotalPremium . " => ".$QSC." => ".$MasterDataArrayVale->TotalSumInsured;
                        $TreatyPremium = round((($MasterDataArrayVale->TotalPremium * $QSC) / $MasterDataArrayVale->TotalSumInsured), 4);
                        $UsedFormula = 'Y';
                    }
                } elseif ($MasterDataArrayVale->formula_type == 'FACULTATIVE') {
                    $siAllocation = str_replace(",", "", $MasterDataArrayVale->si_allocation);
                    $FacPlaceFinalAMT = $siAllocation;
                    $TotalSumInsured = $MasterDataArrayVale->TotalSumInsured;
                    $TotalPremium = $MasterDataArrayVale->TotalPremium;
                    $reinsuranceType = $MasterDataArrayVale->reinsurance_type;
                    // if($MasterDataArrayVale->TotalSumInsured < $MasterDataArrayVale->si_allocation) {
                    //     $TreatySI = 0.00;
                    // }else{
                    //     $TreatySI = round(((float)$MasterDataArrayVale->TotalSumInsured - (float)$MasterDataArrayVale->si_allocation),4);
                    // }

                    for ($i = 0; $i < count($golbalNet); $i++) {

                        if (isset($golbalNet[$TotalSumInsured]) && $golbalNet[$TotalSumInsured] == $TotalSumInsured) {
                            $FACAuto = ($golbalNet[$TotalSumInsured] + $golbalQuota[$TotalSumInsured]);
                        } else {
                            $FACAuto = ($golbalNet[$TotalSumInsured] + $golbalQuota[$TotalSumInsured]);
                        }
                        //echo " => ".$FACAuto;
                        $FacAmt = 0;
                        #IF(SI > (NR+Q+SCL), IF(SI <= TCL, SI- (NR+Q+SCL), AFCL),0)

                        if ($TotalSumInsured > ($FACAuto) && $FACAuto != 0) {
                            if ($TotalSumInsured <= '6500000') {
                                $FacAmt = $TotalSumInsured - ($FACAuto);
                            } else {
                                $FacAmt = $siAllocation;
                            }
                        }
                        $FacPlaceAmt = $FacAmt;
                        $TreatyPercentage = ($FacAmt / $TotalSumInsured);
                        $TreatyPremium = round(($TotalPremium * $TreatyPercentage), 2);
                        $TreatySI = round((($FacPlaceAmt)), 4);
                        $UsedFormula = 'Y';


                    }

                } elseif ($MasterDataArrayVale->formula_type == 'FACULATIVEPLACEMENT') {
                    $siAllocation = (float)str_replace(",", "", $MasterDataArrayVale->si_allocation);
                    $TotalSumInsured = $MasterDataArrayVale->TotalSumInsured;
                    $TotalPremium = $MasterDataArrayVale->TotalPremium;
                    for ($i = 0; $i < count($golbalNet); $i++) {
                        //$FACAuto = ($golbalNet[$TotalSumInsured] + $golbalQuota[$TotalSumInsured]);  
                        if (isset($golbalNet[$TotalSumInsured]) && $golbalNet[$TotalSumInsured] == $TotalSumInsured) {
                            $FACAuto = ($golbalNet[$TotalSumInsured] + $golbalQuota[$TotalSumInsured]);
                        } else {
                            $FACAuto = ($golbalNet[$TotalSumInsured] + $golbalQuota[$TotalSumInsured]);
                        }
                        if ($TotalSumInsured > '6500000') {
                            $FacAmt = $TotalSumInsured - '6500000' + $QSCShare - $FACAuto;
                        } else {
                            $FacAmt = 0;
                        }
                        // if($MasterDataArrayVale->TotalSumInsured < $MasterDataArrayVale->si_allocation) {
                        //     $TreatySI = 0.00;
                        // }else{
                        //     $TreatySI = round(((float)$MasterDataArrayVale->TotalSumInsured - (float)$MasterDataArrayVale->si_allocation),4);
                        // }

                        $TreatyPercentage = ($FacAmt / $TotalSumInsured);
                        $TreatyPremium = round(($TotalPremium * $TreatyPercentage), 2);
                        $TreatySI = round((($FacAmt)), 4);
                        $UsedFormula = 'Y';
                    }
                }

                $policyReinsurrance = array(
                    'product_id' => $productId ?? "",
                    'risk_id' => $MasterDataArrayVale->risk_address_id ?? "",
                    'policy_id' => $policyId ?? "",
                    'term_id' => $termId ?? "",
                    'action_id' => $actionId ?? "",
                    'group_id' => $MasterDataArrayVale->group_id ?? "",
                    'formula_id' => $MasterDataArrayVale->formula_id ?? "",
                    'coverage_id' => $MasterDataArrayVale->pocoverage_detail_id ?? "",
                    'treaty_id' => $MasterDataArrayVale->treaty_id ?? "",
                    'type_id' => $MasterDataArrayVale->reinsurance_type_id ?? "",
                    'totalSumInsured' => $MasterDataArrayVale->TotalSumInsured,
                    'totalPremium' => $MasterDataArrayVale->TotalPremium ?? "",
                    'treatySI' => $TreatySI ?? "",
                    'treatyPercentage' => $TreatyPercentage ?? "",
                    'treatyPremium' => $TreatyPremium ?? "",
                    'UsedFormula' => $UsedFormula ?? "",
                    'added_by' => $userId ?? "",
                    'created_at' => $currentDateTime
                );
                PolicyReinsurance::insert($policyReinsurrance);


            }
        }
    }

    public static function FidelityGuaranteeReinsurance($currentDate, $actionId, $currentDateTime, $policyId, $termId, $productId)
    {
        $MasterQuery = "
        SELECT 
            fm.reinsurance_type_id,
            gm.group_code,
            fm.type_id,
            fm.s_FormulaType AS formula_type,
            fd.formula_id,
            COALESCE(veh.vehicle_type, 0) AS n_TypeOfMotor,
            fd.operator,
            fd.si_allocation,
            fd.n_ValueLimitsBetween,
            fd.percentage,
            prid.*,
            SUM(prid.n_SumInsured) AS TotalSumInsured,
            SUM(prid.n_Premium) AS TotalPremium,
            tm.id
        FROM policy_reinsurance_details prid
        LEFT JOIN reinsurance_group gm ON gm.id = prid.group_id
        LEFT JOIN reinsurance_formula_details fd ON fd.group_id = gm.id
        
        -- Updated by Snehal on 30-07-25
        LEFT JOIN policy_coverage_detail sub ON sub.id = gm.id
        LEFT JOIN motor mot ON mot.policy_coverage_id = sub.id
 
        LEFT JOIN policy_coverage_entities cvget ON cvget.policy_coverage_id = sub.policy_coverage_id
        LEFT JOIN vehicle veh ON cvget.entity_id = veh.id
        LEFT JOIN reinsurance_formula fm ON fm.id = fd.formula_id
        LEFT JOIN reinsurance_treaty_details td ON td.formula_attached = fm.id
        LEFT JOIN reinsurance_treaty tm ON tm.id = td.treaty_id
        WHERE COALESCE(veh.vehicle_type, 0) = COALESCE(fd.vehicle_type, 0)
          AND prid.action_id = '" . $actionId . "'
          AND fd.operator IS NOT NULL
          AND gm.group_code IN ('FIDELITYG_COM' )
          AND tm.effective_from <= '" . $currentDate . "'
          AND tm.effective_to >= '" . $currentDate . "'
        GROUP BY 
            prid.policy_id,
            prid.action_id,
            prid.risk_address_id,
            prid.group_id,
            fd.formula_id,
            CASE WHEN fm.type_id = 35 THEN prid.pocoverage_detail_id ELSE '' END
        ORDER BY fd.formula_id
    ";

        $MasterDataArray = DB::select(DB::raw($MasterQuery));

        $TSI_Excess = '';
        $golbalNet = 0;
        $golbalQuota = 0;
        $FacPlaceAmt = 0;
        $QSCShare = 0;
        $QSCNoNMotorShare = 0;
        $FacPlaceFinalAMT = 0;

        foreach ($MasterDataArray as $MasterDataArrayVale) {
            if ($MasterDataArrayVale->reinsurance_type_id === null) {
                continue;
            }

            $TreatySI = 0;
            $TreatyPercentage = 0;
            $TreatyPremium = 0;
            $ExpressionSign = null;

            switch ($MasterDataArrayVale->operator) {
                case 1:
                    $ExpressionSign = '=';
                    break;
                case 2:
                    $ExpressionSign = '<';
                    break;
                case 3:
                    $ExpressionSign = '<=';
                    break;
                case 4:
                    $ExpressionSign = '>';
                    break;
                case 5:
                    $ExpressionSign = '>=';
                    break;
                case 6:
                    $ExpressionSign = '!=';
                    break;
                case 7:
                    $ExpressionSign = 'Between';
                    break;
                case 8:
                    $ExpressionSign = 'Not Between';
                    break;
                case 9:
                    $ExpressionSign = '*';
                    break;
            }

            $UsedFormula = 'N';
            $type = Lookup::where('id', $MasterDataArrayVale->type_id)
                ->where('key', 'reinsurance_formula_key')
                ->first()?->value;

            // ========== FORMULA TYPE HANDLING ==========
            if ($MasterDataArrayVale->formula_type === 'OTHER') {
                if ($ExpressionSign == '<') {
                    if ($MasterDataArrayVale->TotalSumInsured < $MasterDataArrayVale->si_allocation) {
                        $TreatySI = round(($MasterDataArrayVale->TotalSumInsured * $MasterDataArrayVale->percentage) / 100, 4);
                        $TreatyPercentage = $MasterDataArrayVale->percentage;
                    } else {
                        $TreatySI = round(($MasterDataArrayVale->si_allocation * $MasterDataArrayVale->percentage) / 100, 4);
                        $TreatyPercentage = round(($TreatySI / $MasterDataArrayVale->TotalSumInsured) * 100, 4);
                    }
                }
                $TreatyPremium = round(($MasterDataArrayVale->TotalPremium * $TreatyPercentage) / 100, 4);
                $UsedFormula = 'Y';

            } elseif ($MasterDataArrayVale->formula_type === 'TSI') {

                $total_sum_insured = $MasterDataArrayVale->TotalSumInsured;
                $QSC = (float) str_replace(",", "", $MasterDataArrayVale->si_allocation);

                if ($total_sum_insured < $QSC) {
                    // $TSI_Excess = 0.00;
                    $TSI_Excess = $total_sum_insured;
                } else {

                    $TSI_Excess = round((float) $QSC, 4);

                }

                if ($MasterDataArrayVale->TotalSumInsured > $QSC) {
                    $PQSC = round((($MasterDataArrayVale->TotalPremium * $QSC) / $MasterDataArrayVale->TotalSumInsured), 4);
                } else {
                    $PQSC = round((($MasterDataArrayVale->TotalPremium)), 4);
                }

                $TreatyPercentage = round((($MasterDataArrayVale->percentage) / 100), 4);

                $TreatyPremium = round((($PQSC * $TreatyPercentage)), 4);
                $TreatySI = round((($TSI_Excess * $TreatyPercentage)), 4);

                $UsedFormula = 'Y';

            } elseif ($MasterDataArrayVale->formula_type === 'SURPLUS') {
                // SURPLUS logic unchanged, just cleaned up
                $TotalSumInsured = 0;
                $SCL = str_replace(",", "", $MasterDataArrayVale->si_allocation);
                $TotalSumInsured = $MasterDataArrayVale->TotalSumInsured;
                $TotalPremium = $MasterDataArrayVale->TotalPremium;
                $group_id = $MasterDataArrayVale->group_id;

                $QSCNoNMotorShare = DB::table('reinsurance_formula_details')
                    ->where('group_id', $group_id)
                    ->where('percentage', 70)
                    ->value('si_allocation');

                $QSCNoNMotorShare = str_replace(",", "", $QSCNoNMotorShare);
                $QSCNoNMotorShare = (float) $QSCNoNMotorShare;
                $QSCL = ($TotalSumInsured <= $QSCNoNMotorShare) ? $TotalSumInsured : $QSCNoNMotorShare;

                $TreatyPercentage = 0;
                $TreatyPremium = 0;
                if ($TotalSumInsured > $QSCL) {
                    if ($TotalSumInsured <= $SCL) {
                        $FinalPremium = (float) $TotalSumInsured - (float) $QSCNoNMotorShare;
                        $TreatyPercentage = ((float) $FinalPremium / (float) $TotalSumInsured);
                        $TreatyPremium = round($TotalPremium * $TreatyPercentage, 2);
                        $TreatySI = round($SCL, 2);
                        $UsedFormula = 'Y';
                    } elseif (($TotalSumInsured - $QSCNoNMotorShare) <= $SCL) {
                        $FinalPremium = $TotalSumInsured - $QSCNoNMotorShare;
                        $TreatyPercentage = ((float) $FinalPremium / (float) $TotalSumInsured);
                        $TreatyPremium = round($TotalPremium * $TreatyPercentage, 2);
                        $TreatySI = round($FinalPremium, 2);
                        $UsedFormula = 'Y';
                    } else {
                        $FinalPremium = $SCL;
                        $TreatyPercentage = ((float) $FinalPremium / (float) $TotalSumInsured);
                        $TreatyPremium = round($TotalPremium * $TreatyPercentage, 2);
                        $TreatySI = round($SCL, 2);
                        $UsedFormula = 'Y';
                    }
                }

            } elseif ($MasterDataArrayVale->formula_type === 'FACULTATIVE') {
                $TotalSumInsured = 0;
                $TotalSumInsured = $MasterDataArrayVale->TotalSumInsured;
                $TotalPremium = $MasterDataArrayVale->TotalPremium;
                $TreatySITotal = str_replace(",", "", $MasterDataArrayVale->si_allocation);

                if ($TotalSumInsured >= $TreatySITotal) {
                    $finalTotalPer = $TotalSumInsured - $TreatySITotal;

                    if ($finalTotalPer >= $TreatySITotal) {
                        $TreatyPremium = round(($TotalPremium * $TreatySITotal / $TotalSumInsured), 4);
                        $TreatySI = round($TreatySITotal, 4);
                    } else {
                        $TreatyPremium = round(($TotalPremium * $finalTotalPer / $TotalSumInsured), 4);
                        $TreatySI = round($finalTotalPer, 4);
                    }
                    $UsedFormula = 'Y';
                }

            } elseif ($MasterDataArrayVale->formula_type === 'FACULATIVEPLACEMENT') {
                $TreatyPremiumData = (float) ($MasterDataArrayVale->TotalPremium);
                $TotalSumInsuredData = (float) ($MasterDataArrayVale->TotalSumInsured);
                $QSCData = (float) str_replace(",", "", $MasterDataArrayVale->si_allocation);

                if ($TotalSumInsuredData > $QSCData) {
                    $FPQ = $TotalSumInsuredData - $QSCData;

                    $percent = $FPQ / $TotalSumInsuredData;

                    $TreatyPremium = round(($percent * $TreatyPremiumData), 2);
                    $TreatySI = round(($FPQ), 4);
                    $UsedFormula = 'Y';


                }
            }

            // ========== SAVE INTO POLICY REINSURANCE ==========
            $policyReinsurrance = [
                'product_id' => $productId ?? "",
                'risk_id' => $MasterDataArrayVale->risk_address_id ?? "",
                'policy_id' => $policyId ?? "",
                'term_id' => $termId ?? "",
                'action_id' => $actionId ?? "",
                'group_id' => $MasterDataArrayVale->group_id ?? "",
                'formula_id' => $MasterDataArrayVale->formula_id ?? "",
                'coverage_id' => $MasterDataArrayVale->coverage_id ?? "",
                'treaty_id' => $MasterDataArrayVale->treaty_id ?? "",
                'type_id' => $MasterDataArrayVale->reinsurance_type_id ?? "",
                'totalSumInsured' => $MasterDataArrayVale->TotalSumInsured,
                'totalPremium' => $MasterDataArrayVale->TotalPremium ?? "",
                'treatySI' => $TreatySI ?? "",
                'treatyPercentage' => $TreatyPercentage ?? "",
                'treatyPremium' => $TreatyPremium ?? "",
                'UsedFormula' => $UsedFormula ?? "",
                'added_by' => $userId ?? "",
                'created_at' => $currentDateTime,
            ];

            PolicyReinsurance::insert($policyReinsurrance);
        }
    }


    public static function ExcessOfLossCoverageReinsurance1($currentDate, $actionId, $currentDateTime, $policyId, $termId, $productId, $int = "")
    {

        // Set group code based on product
        // dd("iiiiiiiiiiiiiiii");
        $groupMap = [
            8 => [20 => 'WC_DOM', 14 => 'GROUPPERSONALACCIDENT_DOM', 21 => 'PUBLICLIABANDDEFECTIVEWORKMAN_DOM'],
            7 => [20 => 'WC_COM', 14 => 'GROUPPERSONALACCIDENT_COM', 21 => 'PUBLICLIABANDDEFECTIVEWORKMAN_COM'],
            ];

        $group_code = $groupMap[$productId][$int] ?? null;

        // Build query
        $MasterQuery = "
        SELECT 
            fm.reinsurance_type_id,
            gm.group_code,
            fm.type_id,
            fm.s_FormulaType AS formula_type,
            fd.formula_id,
            fd.operator,
            fd.si_allocation,
            fd.n_ValueLimitsBetween,
            fd.percentage,
            prid.*,
            SUM(prid.n_SumInsured) AS TotalSumInsured,
            SUM(prid.n_Premium) AS TotalPremium,
            tm.id
        FROM policy_reinsurance_details prid
        LEFT JOIN reinsurance_group gm ON gm.id = prid.group_id
        LEFT JOIN reinsurance_formula_details fd ON fd.group_id = gm.id
        -- commented by Snehal on 30-07-25
        -- LEFT JOIN policy_coverage_detail sub ON sub.id = prid.pocoverage_detail_id
        -- LEFT JOIN motor mot ON mot.policy_coverage_id = prid.pocoverage_detail_id
        -- added by Snehal on 30-07-25
        LEFT JOIN policy_coverage_detail sub ON sub.id = gm.id
        LEFT JOIN policy_coverage_entities cvget ON cvget.policy_coverage_id = sub.policy_coverage_id
        LEFT JOIN reinsurance_formula fm ON fm.id = fd.formula_id
        LEFT JOIN reinsurance_treaty_details td ON td.formula_attached = fm.id
        LEFT JOIN reinsurance_treaty tm ON tm.id = td.treaty_id
        WHERE prid.action_id = '" . $actionId . "'
         AND gm.group_code = '" . $group_code . "'
          " . (!empty($int) ? " AND prid.coverage_id = '" . $int . "'" : "") . "
          AND fd.operator IS NOT NULL
           AND tm.effective_from <= '" . $currentDate . "'
          AND tm.effective_to >= '" . $currentDate . "'
        GROUP BY 
            prid.policy_id,
            prid.action_id,
            prid.risk_address_id,
            prid.group_id,
            fd.formula_id,   
            CASE
                WHEN fm.type_id = 35 THEN prid.pocoverage_detail_id
                ELSE ''
            END
        ORDER BY fd.formula_id";

        $MasterDataArray = DB::select(DB::raw($MasterQuery));
        // dd($MasterDataArray);
        // Initialize variables
        $TSI_Excess = '';
        $golbalNet = 0;
        $golbalQuota = 0;
        $FacPlaceAmt = 0;
        $QSCShare = 0;
        $QSCNoNMotorShare = 0;
        $FacPlaceFinalAMT = 0;

        $ExcessOfLoss = DB::table('reinsurance_formula_details as rfd')
            ->join('reinsurance_group as rg', 'rg.id', '=', 'rfd.group_id')
            ->join('reinsurance_formula as rf', 'rf.id', '=', 'rfd.formula_id')
            ->where('rg.group_code', $group_code)
            ->where('rf.s_FormulaType', 'TSI')
            ->value('rfd.si_allocation');
        $ExcessOfLoss = (float) str_replace(",", "", $ExcessOfLoss);

        $Quotashare = DB::table('reinsurance_formula_details as rfd')
            ->join('reinsurance_group as rg', 'rg.id', '=', 'rfd.group_id')
            ->join('reinsurance_formula as rf', 'rf.id', '=', 'rfd.formula_id')
            ->where('rg.group_code', $group_code)
            ->where('rf.s_FormulaType', 'SURPLUS')
            ->value('rfd.si_allocation');
        $Quotashare = (float) str_replace(",", "", $Quotashare);

        foreach ($MasterDataArray as $MasterDataArrayVale) {
            if ($MasterDataArrayVale->reinsurance_type_id != null) {
                $TreatySI = 0;
                $TreatyPercentage = 0;
                $TreatyPremium = 0;
                $ExpressionSign = null;


                // Operator mapping
                $operatorMap = [
                    1 => '=',
                    2 => '<',
                    3 => '<=',
                    4 => '>',
                    5 => '>=',
                    6 => '!=',
                    7 => 'Between',
                    8 => 'Not Between',
                    9 => '*',
                ];
                $ExpressionSign = $operatorMap[$MasterDataArrayVale->operator] ?? null;

                $UsedFormula = 'N';
                $type = Lookup::where('id', $MasterDataArrayVale->type_id)
                    ->where('key', 'reinsurance_formula_key')
                    ->first()?->value;

                /*
                 * Formula type handling
                 */
// dd($MasterDataArrayVale);
                if ($MasterDataArrayVale->formula_type == 'OTHER') {
                    if ($ExpressionSign == '<') {
                        if ($MasterDataArrayVale->TotalSumInsured < $MasterDataArrayVale->si_allocation) {
                            $TreatySI = round(($MasterDataArrayVale->TotalSumInsured * $MasterDataArrayVale->percentage) / 100, 4);
                            $TreatyPercentage = $MasterDataArrayVale->percentage;
                        } else {
                            $TreatySI = round(($MasterDataArrayVale->si_allocation * $MasterDataArrayVale->percentage) / 100, 4);
                            $TreatyPercentage = round(($TreatySI / $MasterDataArrayVale->TotalSumInsured) * 100, 4);
                        }
                    }
                    $TreatyPremium = round(($MasterDataArrayVale->TotalPremium * $TreatyPercentage) / 100, 4);
                    $UsedFormula = 'Y';
                } 
                elseif ($MasterDataArrayVale->formula_type == 'TSI') {
                    $globalnet = 0;
                    $total_sum_insured = 0;
                    $total_premium = 0;
                    $QSC = 0;
                    $TreatySI = 0;
                    $TreatyPercentage = 0;
                    $TreatyPremium = 0;
                    $total_sum_insured = round(($MasterDataArrayVale->TotalSumInsured), 4);
                    $total_premium = round(($MasterDataArrayVale->TotalPremium), 4);
                    $QSC = (float) str_replace(",", "", $MasterDataArrayVale->si_allocation);

                    if ($total_sum_insured <= $QSC) {
                        $TreatySI = $total_sum_insured;
                    } else {
                        $TreatySI = $QSC;
                    }

                    if ($TreatySI > 0) {
                        $TreatyPercentage = $TreatySI / $total_sum_insured * 100;
                        $TreatyPremium = ($total_premium * $TreatyPercentage) / 100;
                    } else {
                        $TreatyPercentage = 0;
                        $TreatyPremium = 0;
                    }

                    $TreatySI = round($TreatySI, 4);
                    $UsedFormula = 'Y';
                    $globalnet = $QSC;
                }
                 elseif ($MasterDataArrayVale->formula_type == 'SURPLUS') {
                    $SCL = 0;
                    $TotalSumInsured = 0;
                    $TotalPremium = 0;
                    $TSI_Excess = 0;
                    $TreatySI = 0;
                    $TreatyPercentage = 0;
                    $TreatyPremium = 0;

                    $SCL = (float) str_replace(",", "", $MasterDataArrayVale->si_allocation);
                    $TotalSumInsured = (float) $MasterDataArrayVale->TotalSumInsured;
                    $TotalPremium = $MasterDataArrayVale->TotalPremium;

                    $TSI_Excess = ($TotalSumInsured < $ExcessOfLoss) ? $MasterDataArrayVale->si_allocation : $ExcessOfLoss;

                    if ($TotalSumInsured < $ExcessOfLoss) {
                        $TreatySI = 0;
                    } elseif ($TotalSumInsured < $SCL) {

                        $TreatySI = $TotalSumInsured - $ExcessOfLoss;
                    } else {
                        $TreatySI = $SCL - $ExcessOfLoss;
                    }

                    if ($TreatySI > 0) {
                        $TreatyPercentage = $TreatySI / $TotalSumInsured * 100;
                        $TreatyPremium = ($TotalPremium * $TreatyPercentage) / 100;
                    } else {
                        $TreatyPercentage = 0;
                        $TreatyPremium = 0;
                    }

                    $TreatySI = round($TreatySI, 4);
                    $UsedFormula = 'Y';


                } 
                elseif ($MasterDataArrayVale->formula_type == 'FACULTATIVE') {
                    $TCL = 0;
                    $TotalSumInsured = 0;
                    $TotalPremium = 0;
                    $TreatySITotal = 0;
                    $TreatySI = 0;
                    $TreatyPremium = 0;

                    //temp change formula by bonno and paul beka
                    //$TCL=$Quotashare;

                    $TotalSumInsured = (float) $MasterDataArrayVale->TotalSumInsured;
                    $TotalPremium = $MasterDataArrayVale->TotalPremium;
                    $TreatySITotal = (float) str_replace(",", "", $MasterDataArrayVale->si_allocation);

                    if ($TotalSumInsured <= $TCL) {
                        $TreatySI = 0;
                    } else if ($TotalSumInsured <= ($TreatySITotal + $TCL)) {
                        $TreatySI = $TotalSumInsured - $TCL;
                    } else {
                        $TreatySI = $TreatySITotal;
                    }

                    $TreatyPremium = round(($TotalPremium * $TreatySI / $TotalSumInsured), 4);
                    $UsedFormula = 'Y';
                    // dd($TreatySI,"hi");
                } 
                elseif ($MasterDataArrayVale->formula_type == 'FACULATIVEPLACEMENT') {

                    $TreatyPremiumData = (float) ($MasterDataArrayVale->TotalPremium);
                    $TotalSumInsuredData = (float) ($MasterDataArrayVale->TotalSumInsured);
                    $QSCData = (float) str_replace(",", "", $MasterDataArrayVale->si_allocation);
                   
                    if ($TotalSumInsuredData > $QSCData) {

                        if ($group_code == "PUBLICLIABANDDEFECTIVEWORKMAN_DOM" || $group_code == "PUBLICLIABANDDEFECTIVEWORKMAN_COM") {
                            $FPQ = $TotalSumInsuredData - ($QSCData + $ExcessOfLoss);//old code
                              $FPQ = $TotalSumInsuredData - ($QSCData); // new
                        } else {
                            $FPQ = $TotalSumInsuredData - ($QSCData);
                        }
                        $percent = $FPQ / $TotalSumInsuredData;
                        // dd($FPQ,$percent);

                        $TreatyPremium = round(($percent * $TreatyPremiumData), 2);
                        $TreatySI = round(($FPQ), 4);
                        $UsedFormula = 'Y';
                    } else {

                        $TreatySI = 0;
                    }

                }

                // Save result
                $policyReinsurrance = [
                    'product_id' => $productId ?? "",
                    'risk_id' => $MasterDataArrayVale->risk_address_id ?? "",
                    'policy_id' => $policyId ?? "",
                    'term_id' => $termId ?? "",
                    'action_id' => $actionId ?? "",
                    'group_id' => $MasterDataArrayVale->group_id ?? "",
                    'formula_id' => $MasterDataArrayVale->formula_id ?? "",
                    'coverage_id' => $MasterDataArrayVale->coverage_id ?? "",
                    'treaty_id' => $MasterDataArrayVale->treaty_id ?? "",
                    'type_id' => $MasterDataArrayVale->reinsurance_type_id ?? "",
                    'totalSumInsured' => $MasterDataArrayVale->TotalSumInsured,
                    'totalPremium' => $MasterDataArrayVale->TotalPremium ?? "",
                    'treatySI' => $TreatySI ?? "",
                    'treatyPercentage' => $TreatyPercentage ?? "",
                    'treatyPremium' => $TreatyPremium ?? "",
                    'UsedFormula' => $UsedFormula ?? "",
                    'added_by' => $userId ?? "",
                    'created_at' => $currentDateTime,
                ];
                   // print_r($policyReinsurrance);
                PolicyReinsurance::insert($policyReinsurrance);
            }
         } //dd("done");
    }

    public static function MotorComTradersReinsurance($currentDate, $actionId, $currentDateTime, $policyId, $termId, $productId)
    {
        $MasterQuery = "WITH SummedInsured AS (
            SELECT
                fd.formula_id,
                SUM(DISTINCT prid.n_SumInsured) as TotalSumInsured,
                SUM(DISTINCT prid.n_Premium) as TotalPremium
            FROM policy_reinsurance_details prid
            LEFT JOIN reinsurance_group gm ON gm.id = prid.group_id
            LEFT JOIN reinsurance_formula_details fd ON fd.group_id = gm.id
            WHERE prid.action_id = '" . $actionId . "'
            and coverage_id IN (15,16)
            GROUP BY fd.formula_id
        )
        SELECT DISTINCT
            fm.reinsurance_type_id,
            gm.group_code,
            gm.id as group_id,
            fm.type_id,
            fm.s_FormulaType AS formula_type,
            fm.reinsurance_type_id AS reinsurance_type,
            fd.formula_id,
            COALESCE(veh.vehicle_type,0) AS n_TypeOfMotor,
            fd.operator,
            fd.si_allocation,
            fd.n_ValueLimitsBetween,
            fd.percentage,
            si.TotalSumInsured,
            si.TotalPremium,
            prid.n_Premium AS Premium,
            tm.id as treaty_id,
            prid.policy_id,
            prid.risk_address_id,
            prid.pocoverage_detail_id
        FROM policy_reinsurance_details prid
        INNER JOIN reinsurance_group gm ON gm.id = prid.group_id
        INNER JOIN reinsurance_formula_details fd ON fd.group_id = gm.id
        LEFT JOIN SummedInsured si ON si.formula_id = fd.formula_id
        LEFT JOIN policy_coverage_detail sub ON sub.id = prid.pocoverage_detail_id
        LEFT JOIN motor mot ON mot.policy_coverage_id = prid.pocoverage_detail_id
        LEFT JOIN policy_coverage_entities cvget ON cvget.policy_coverage_id = sub.policy_coverage_id
        LEFT JOIN vehicle veh ON cvget.entity_id = veh.id
        INNER JOIN reinsurance_formula fm ON fm.id = fd.formula_id
        INNER JOIN reinsurance_treaty_details td ON td.formula_attached = fm.id
        INNER JOIN reinsurance_treaty tm ON tm.id = td.treaty_id
        WHERE COALESCE(veh.vehicle_type,0) = COALESCE(fd.vehicle_type,0)
        AND prid.action_id = '" . $actionId . "'
      AND gm.group_code IN ('MOTOR_TRADERS_COM_EXT', 'MOTOR_TRADERS_COM_INT')
        AND fd.operator IS NOT NULL
        AND tm.effective_from <= '" . $currentDate . "'
        AND tm.effective_to >= '" . $currentDate . "'
        GROUP BY prid.policy_id,prid.action_id,prid.risk_address_id,prid.group_id,fd.formula_id,
        case
            when  fm.type_id = 35 then prid.pocoverage_detail_id
            else ''
        end";

        $MasterDataArray = DB::select(DB::raw($MasterQuery));

        $TSI_Excess = '';
        // $golbalNet = 0;
        // $golbalQuota = 0;
        $FacPlaceAmt = 0;
        $QSCShare = 0;
        $FacPlaceFinalAMT = 0;
    //    dd($MasterDataArray);
        foreach ($MasterDataArray as $MasterDataArrayVale) {

            if ($MasterDataArrayVale->reinsurance_type_id != NULL) {
                $TreatySI = 0;
                $TreatyPercentage = 0;
                $TreatyPremium = 0;
                $ExpressionSign = null;
                if ($MasterDataArrayVale->operator == 1) {
                    $ExpressionSign = '=';
                } elseif ($MasterDataArrayVale->operator == 2) {
                    $ExpressionSign = '<';
                } elseif ($MasterDataArrayVale->operator == 3) {
                    $ExpressionSign = '<=';
                } elseif ($MasterDataArrayVale->operator == 4) {
                    $ExpressionSign = '>';
                } elseif ($MasterDataArrayVale->operator == 5) {
                    $ExpressionSign = '>=';
                } elseif ($MasterDataArrayVale->operator == 6) {
                    $ExpressionSign = '!=';
                } elseif ($MasterDataArrayVale->operator == 7) {
                    $ExpressionSign = 'Between';
                } elseif ($MasterDataArrayVale->operator == 8) {
                    $ExpressionSign = 'Not Between';
                } elseif ($MasterDataArrayVale->operator == 9) {
                    $ExpressionSign = '*';
                }
                $UsedFormula = 'N';
                $type = Lookup::where('id', $MasterDataArrayVale->type_id)->where('key', 'reinsurance_formula_key')->first()?->value;


                $ADNetLimit = '300000';

                // For MOTOR
                if ($MasterDataArrayVale->formula_type == 'OTHER') {
                    if ($ExpressionSign == '<') {
                        if ($MasterDataArrayVale->TotalSumInsured < $MasterDataArrayVale->si_allocation) {
                            $TreatySI = round((((float) $MasterDataArrayVale->TotalSumInsured * (float) $MasterDataArrayVale->percentage) / 100), 4);
                            $TreatyPercentage = $MasterDataArrayVale->percentage;
                            $UsedFormula = 'Y';
                        }
                    }
                    if ($ExpressionSign == 'Between') {
                        if (($MasterDataArrayVale->TotalSumInsured >= $MasterDataArrayVale->si_allocation) && ($MasterDataArrayVale->TotalSumInsured <= $MasterDataArrayVale->n_ValueLimitsBetween)) {
                            $TreatySI = round((((float) $MasterDataArrayVale->TotalSumInsured * (float) $MasterDataArrayVale->percentage) / 100), 4);
                            $TreatyPercentage = $MasterDataArrayVale->percentage;
                            $UsedFormula = 'Y';
                        }
                    }
                    if ($ExpressionSign == '>') {
                        if ($MasterDataArrayVale->TotalSumInsured > $MasterDataArrayVale->si_allocation) {
                            $TreatySI = round((((float) $MasterDataArrayVale->si_allocation * (float) $MasterDataArrayVale->percentage) / 100), 4);
                            $TreatyPercentage = round((float) $TreatySI / (float) $MasterDataArrayVale->TotalSumInsured * 100, 4);
                            //$TreatyPercentage = $MasterDataArrayVale->percentage;
                            $UsedFormula = 'Y';
                        }
                    }
                    $TreatyPremium = round((((float) $MasterDataArrayVale->TotalPremium * (float) $TreatyPercentage) / 100), 4);

                } elseif ($MasterDataArrayVale->formula_type == 'TSI') {
                    // dd("tsi");
                    $TreatySI = 0;
                    $TSI_Excess = 0;
                    $TreatyPercentage = 0;
                    $siAllocationAmt = str_replace(",", "", $MasterDataArrayVale->si_allocation);
                    $QSCShare = $siAllocationAmt;
                    $TotalSumInsured = $MasterDataArrayVale->TotalSumInsured;
                    if ($siAllocationAmt > $TotalSumInsured) {
                        $QSC = (float)$TotalSumInsured;
                    } else {
                        $QSC = (float)str_replace(",", "", $MasterDataArrayVale->si_allocation);
                    } 
                    
                     $QuotaPer = $QSC * 0.8;
                  
                    $golbalQuota = (float) $QuotaPer;
                     if ($QSC * 0.2 <= $ADNetLimit) {
                                $NetRet = $QSC * 0.2;
                            } else {
                                $NetRet = $ADNetLimit;
                            }
                    $golbalNet = (float) $NetRet;

                    $TotalPremium = $MasterDataArrayVale->TotalPremium;
                    $reinsuranceType = $MasterDataArrayVale->reinsurance_type;
                    
                    if ($MasterDataArrayVale->TotalSumInsured < $MasterDataArrayVale->si_allocation) {
                        $TSI_Excess = 0.00;
                        $TreatySI = $TSI_Excess;
                    } else {
                        $TSI_Excess = round(((float) $MasterDataArrayVale->TotalSumInsured - (float) $MasterDataArrayVale->si_allocation), 4);
                        $TreatySI = $TSI_Excess;
                    }
                    if ($TotalSumInsured >= $QSC) {
                        if (intval($reinsuranceType) === 3) {
                            if ($QSC * 0.2 <= $ADNetLimit) {
                                $NetRet = $QSC * 0.2;
                            } else {
                                $NetRet = $ADNetLimit;
                            }
                            $TreatyPercentage = ($NetRet / $TotalSumInsured);
                            $TreatyPremium = round(($TotalPremium * $TreatyPercentage), 2);
                            $UsedFormula = 'Y';
                            $golbalNet = (float) $NetRet;
                            $TreatySI = $NetRet;

                        } else {
                            $QuotaPer = $QSC * 0.8;
                            $golbalQuota = (float) $QuotaPer;
                            $TreatyPercentage = $QuotaPer / $TotalSumInsured;
                            $TreatyPremium = round(($TotalPremium * $TreatyPercentage), 2);
                            $UsedFormula = 'Y';
                            $TreatySI = $golbalQuota;
                        }

                    } else {

                        if ($TotalSumInsured < $QSC) {
                            if (intval($reinsuranceType) === 3) {
                                if ($QSC * 0.2 <= $ADNetLimit) {
                                    $NetRet = $QSC * 0.2;
                                } else {
                                    $NetRet = $ADNetLimit;
                                }
                                $golbalNet = (float) $NetRet;
                                $TreatyPercentage = $NetRet / $TotalSumInsured;
                                $TreatyPremium = round(($TotalPremium * $TreatyPercentage), 2);
                                $UsedFormula = 'Y';
                                $TreatySI = $NetRet;

                            } elseif ($reinsuranceType != 3) {
                                $QuotaPer = $QSC * 0.8;
                                $golbalQuota = (float) $QuotaPer;
                                $TreatyPercentage = $QuotaPer / $TotalSumInsured;
                                $TreatyPremium = round(($TotalPremium * $TreatyPercentage), 2);
                                $UsedFormula = 'Y';
                                $TreatySI = $golbalQuota;
                            }
                        }


                    }
                } elseif ($MasterDataArrayVale->formula_type == 'SURPLUS') {

                  
                } elseif ($MasterDataArrayVale->formula_type == 'FACULTATIVE') {
                    $siAllocation = (float)str_replace(",", "", $MasterDataArrayVale->si_allocation);


                    $FacPlaceFinalAMT = (float) $siAllocation;
                    $TotalSumInsured = (float) $MasterDataArrayVale->TotalSumInsured;
                  
                    $TotalPremium = $MasterDataArrayVale->TotalPremium;
                    $reinsuranceType = $MasterDataArrayVale->reinsurance_type;

                    // get quota share and NT

                      $group_id = $MasterDataArrayVale->group_id;
                        $QSCNoNMotorShare = DB::table('reinsurance_formula_details')
                            ->where('group_id', $group_id)
                            ->where('percentage', 80)
                            ->value('si_allocation');
                          $QSCNoNMotorShare= (float)str_replace(",", "", $QSCNoNMotorShare);
                         
                if ( $TotalSumInsured<=$QSCNoNMotorShare) {
                        $QSC = (float)$TotalSumInsured;
                    } else {
                        $QSC = (float)str_replace(",", "", $QSCNoNMotorShare);
                    } 
                   
                        if ($QSC * 0.2 <= $ADNetLimit) {
                                $golbalNet = (float)$QSC * 0.2;
                            } else {
                                $golbalNet = (float)$ADNetLimit;
                            }
                  
                     $golbalQuota = $QSC * 0.8;
                  
                  //  dd($golbalQuota,$golbalNet);
                    $FACAuto = ($golbalQuota + $golbalNet);
                    // dd($FACAuto);
                    $finalFacAuto = $TotalSumInsured - $FACAuto;
                    if ($finalFacAuto > $siAllocation) {
                        $FacAmt = $siAllocation;
                    } else {
                        $FacAmt = $TotalSumInsured - $FACAuto;
                    }
                    // dd($FacAmt);
                    $FacPlaceAmt = $FacAmt;
                    $TreatySI = $FacPlaceAmt;
                    $TreatyPercentage = ($FacAmt / $TotalSumInsured);
                    $TreatyPremium = round(($TotalPremium * $TreatyPercentage), 2);
                    $UsedFormula = 'Y';
                  //  dd($TreatySI, $TreatyPremium);
                }         
                 elseif ($MasterDataArrayVale->formula_type == 'FACULATIVEPLACEMENT') {
                    $siAllocation = (float) str_replace(",", "", $MasterDataArrayVale->si_allocation);
                    $TotalSumInsured = (float) $MasterDataArrayVale->TotalSumInsured;
                    $TotalPremium = $MasterDataArrayVale->TotalPremium;
                    $reinsuranceType = $MasterDataArrayVale->reinsurance_type;
                    
                     // get quota share and NT

                      $group_id = $MasterDataArrayVale->group_id;
                        $QSCNoNMotorShare = DB::table('reinsurance_formula_details')
                            ->where('group_id', $group_id)
                            ->where('percentage', 80)
                            ->value('si_allocation');
                          $QSCNoNMotorShare= (float)str_replace(",", "", $QSCNoNMotorShare);
                         
                            if ( $TotalSumInsured<=$QSCNoNMotorShare) {
                                $QSC = (float)$TotalSumInsured;
                            } else {
                                $QSC = (float)str_replace(",", "", $QSCNoNMotorShare);
                            } 

                                if ($QSC * 0.2 <= $ADNetLimit) {
                                        $golbalNet = (float)$QSC * 0.2;
                                    } else {
                                        $golbalNet = (float)$ADNetLimit;
                                    }

                            $golbalQuota = $QSC * 0.8;

                 
                    if ($TotalSumInsured >= $siAllocation) { 
                        $TreatySI = $TotalSumInsured - $siAllocation + $QSC - $golbalNet - $golbalQuota;
                        $TreatyPercentage = ($TreatySI / $TotalSumInsured);
                        $TreatyPremium = round(($TotalPremium * $TreatyPercentage), 2);
                        $UsedFormula = 'Y';
                    } else {
                        $TreatySI = 0.00;
                        $TreatyPremium = 0.00;
                        $UsedFormula = 'Y';
                    }


                }
  
                $policyReinsurrance = array(
                    'product_id' => $productId ?? "",
                    'risk_id' => $MasterDataArrayVale->risk_address_id ?? "",
                    'policy_id' => $policyId ?? "",
                    'term_id' => $termId ?? "",
                    'action_id' => $actionId ?? "",
                    'group_id' => $MasterDataArrayVale->group_id ?? "",
                    'formula_id' => $MasterDataArrayVale->formula_id ?? "",
                    'coverage_id' => $MasterDataArrayVale->coverage_id ?? "",
                    'treaty_id' => $MasterDataArrayVale->treaty_id ?? "",
                    'type_id' => $MasterDataArrayVale->reinsurance_type_id ?? "",
                    'totalSumInsured' => $MasterDataArrayVale->TotalSumInsured,
                    'totalPremium' => $MasterDataArrayVale->TotalPremium ?? "",
                    'treatySI' => $TreatySI ?? "",
                    'treatyPercentage' => $TreatyPercentage ?? "",
                    'treatyPremium' => $TreatyPremium ?? "",
                    'UsedFormula' => $UsedFormula ?? "",
                    'added_by' => $userId ?? "",
                    'created_at' => $currentDateTime
                ); 
                PolicyReinsurance::insert($policyReinsurrance);  
            }
        } 
    }
    public static function MotorComTrailerReinsurance($currentDate, $actionId, $currentDateTime, $policyId, $termId, $productId)
    {
        if ($productId == 8) {
            $group_code = 'MOTOR_TRAILERS_DOM';
        } else {
            $group_code = 'MOTOR_TRAILERS_COM';
        }
        $MasterQuery = "WITH SummedInsured AS (
            SELECT
                fd.formula_id,
                (prid.n_SumInsured) as SumInsured,
                (prid.n_Premium) as Premium
            FROM policy_reinsurance_details prid
            LEFT JOIN reinsurance_group gm ON gm.id = prid.group_id
            LEFT JOIN reinsurance_formula_details fd ON fd.group_id = gm.id
            WHERE prid.action_id = '" . $actionId . "'
            and coverage_id IN (22,27)
            GROUP BY fd.formula_id
        )
        SELECT DISTINCT
            fm.reinsurance_type_id,
            gm.group_code,
            gm.id as group_id,
            fm.type_id,
            fm.s_FormulaType AS formula_type,
            fm.reinsurance_type_id AS reinsurance_type,
            fd.formula_id,
            COALESCE(veh.vehicle_type,0) AS n_TypeOfMotor,
            fd.operator,
            fd.si_allocation,
            fd.n_ValueLimitsBetween,
            fd.percentage,
            prid.n_Premium AS TotalPremium,
            prid.n_SumInsured AS TotalSumInsured,
            tm.id as treaty_id,
            prid.policy_id,
            prid.risk_address_id,
            prid.pocoverage_detail_id
        FROM policy_reinsurance_details prid
        INNER JOIN reinsurance_group gm ON gm.id = prid.group_id
        INNER JOIN reinsurance_formula_details fd ON fd.group_id = gm.id
        LEFT JOIN SummedInsured si ON si.formula_id = fd.formula_id
        LEFT JOIN policy_coverage_detail sub ON sub.id = prid.pocoverage_detail_id
        LEFT JOIN motor mot ON mot.policy_coverage_id = prid.pocoverage_detail_id
        LEFT JOIN policy_coverage_entities cvget ON cvget.policy_coverage_id = sub.policy_coverage_id
        LEFT JOIN vehicle veh ON cvget.entity_id = veh.id
        INNER JOIN reinsurance_formula fm ON fm.id = fd.formula_id
        INNER JOIN reinsurance_treaty_details td ON td.formula_attached = fm.id
        INNER JOIN reinsurance_treaty tm ON tm.id = td.treaty_id
        WHERE COALESCE(veh.vehicle_type,0) = COALESCE(fd.vehicle_type,0)
        AND prid.action_id = '" . $actionId . "'
        AND gm.group_code = '" . $group_code . "'
        AND fd.operator IS NOT NULL
        AND tm.effective_from <= '" . $currentDate . "'
        AND tm.effective_to >= '" . $currentDate . "'
        GROUP BY prid.policy_id,prid.action_id,prid.risk_address_id,prid.group_id,fd.formula_id,
        case
            when  fm.type_id = 35 then prid.pocoverage_detail_id
            else ''
        end";

        $MasterDataArray = DB::select(DB::raw($MasterQuery));

        $TSI_Excess = '';
        $golbalNet = 0;
        $golbalQuota = 0;
        $FacPlaceAmt = 0;
        $QSCShare = 0;
        $FacPlaceFinalAMT = 0;
        foreach ($MasterDataArray as $MasterDataArrayVale) {

            if ($MasterDataArrayVale->reinsurance_type_id != NULL) {
                $TreatySI = 0;
                $TreatyPercentage = 0;
                $TreatyPremium = 0;
                $ExpressionSign = null;
                if ($MasterDataArrayVale->operator == 1) {
                    $ExpressionSign = '=';
                } elseif ($MasterDataArrayVale->operator == 2) {
                    $ExpressionSign = '<';
                } elseif ($MasterDataArrayVale->operator == 3) {
                    $ExpressionSign = '<=';
                } elseif ($MasterDataArrayVale->operator == 4) {
                    $ExpressionSign = '>';
                } elseif ($MasterDataArrayVale->operator == 5) {
                    $ExpressionSign = '>=';
                } elseif ($MasterDataArrayVale->operator == 6) {
                    $ExpressionSign = '!=';
                } elseif ($MasterDataArrayVale->operator == 7) {
                    $ExpressionSign = 'Between';
                } elseif ($MasterDataArrayVale->operator == 8) {
                    $ExpressionSign = 'Not Between';
                } elseif ($MasterDataArrayVale->operator == 9) {
                    $ExpressionSign = '*';
                }
                $UsedFormula = 'N';
                $type = Lookup::where('id', $MasterDataArrayVale->type_id)->where('key', 'reinsurance_formula_key')->first()?->value;


                $ADNetLimit = '300000';

                // For MOTOR
                if ($MasterDataArrayVale->formula_type == 'OTHER') {
                    if ($ExpressionSign == '<') {
                        if ($MasterDataArrayVale->TotalSumInsured < $MasterDataArrayVale->si_allocation) {
                            $TreatySI = round((((float) $MasterDataArrayVale->TotalSumInsured * (float) $MasterDataArrayVale->percentage) / 100), 4);
                            $TreatyPercentage = $MasterDataArrayVale->percentage;
                            $UsedFormula = 'Y';
                        }
                    }
                    if ($ExpressionSign == 'Between') {
                        if (($MasterDataArrayVale->TotalSumInsured >= $MasterDataArrayVale->si_allocation) && ($MasterDataArrayVale->TotalSumInsured <= $MasterDataArrayVale->n_ValueLimitsBetween)) {
                            $TreatySI = round((((float) $MasterDataArrayVale->TotalSumInsured * (float) $MasterDataArrayVale->percentage) / 100), 4);
                            $TreatyPercentage = $MasterDataArrayVale->percentage;
                            $UsedFormula = 'Y';
                        }
                    }
                    if ($ExpressionSign == '>') {
                        if ($MasterDataArrayVale->TotalSumInsured > $MasterDataArrayVale->si_allocation) {
                            $TreatySI = round((((float) $MasterDataArrayVale->si_allocation * (float) $MasterDataArrayVale->percentage) / 100), 4);
                            $TreatyPercentage = round((float) $TreatySI / (float) $MasterDataArrayVale->TotalSumInsured * 100, 4);
                            //$TreatyPercentage = $MasterDataArrayVale->percentage;
                            $UsedFormula = 'Y';
                        }
                    }
                    $TreatyPremium = round((((float) $MasterDataArrayVale->TotalPremium * (float) $TreatyPercentage) / 100), 4);

                } elseif ($MasterDataArrayVale->formula_type == 'TSI') {
                    $TreatyPercentage = 0;
                    $siAllocationAmt = str_replace(",", "", $MasterDataArrayVale->si_allocation);
                    $QSCShare = $siAllocationAmt;
                    $TotalSumInsured = $MasterDataArrayVale->TotalSumInsured;
                    if ($siAllocationAmt > $TotalSumInsured) {
                        $QSC = $TotalSumInsured;

                    } else {
                        $QSC = str_replace(",", "", $MasterDataArrayVale->si_allocation);
                    }
                    //echo $QSC;

                    $TotalPremium = $MasterDataArrayVale->TotalPremium;
                    $reinsuranceType = $MasterDataArrayVale->reinsurance_type;
                    if ($MasterDataArrayVale->TotalSumInsured < $MasterDataArrayVale->si_allocation) {
                        $TSI_Excess = 0.00;
                        //$TreatySI = $TSI_Excess;
                    } else {
                        $TSI_Excess = round(((float) $MasterDataArrayVale->TotalSumInsured - (float) $MasterDataArrayVale->si_allocation), 4);
                        //$TreatySI = $TSI_Excess;
                    }
                    if ($TotalSumInsured >= $QSC && intval($reinsuranceType) === 3) {
                        if ($QSC * 0.2 <= $ADNetLimit) {
                            $NetRet = $QSC * 0.2;
                        } else {
                            $NetRet = $ADNetLimit;
                        }
                        //echo $NetRet;

                        $TreatyPercentage = ($NetRet / $TotalSumInsured);
                        $TreatyPremium = round(($TotalPremium * $TreatyPercentage), 2);
                        $TreatySI = round((($NetRet)), 4);
                        $UsedFormula = 'Y';
                        $golbalNet = $NetRet;
                        //return $golbalNet;
                    } else {

                        if ($TotalSumInsured < $QSC && intval($reinsuranceType) === 3) {
                            if ($QSC * 0.2 <= $ADNetLimit) {
                                $NetRet = $QSC * 0.2;
                            } else {
                                $NetRet = $ADNetLimit;
                            }
                            $golbalNet = $NetRet;
                            $TreatyPercentage = $NetRet / $TotalSumInsured;
                            $TreatyPremium = round(($TotalPremium * $TreatyPercentage), 2);
                            $TreatySI = round((($NetRet)), 4);
                            $UsedFormula = 'Y';
                        }
                        if ($reinsuranceType != 3) {
                            $QuotaPer = $TotalSumInsured * 0.8;
                            $golbalQuota = $QuotaPer;
                            $TreatyPercentage = $QuotaPer / $TotalSumInsured;
                            $TreatyPremium = round(($TotalPremium * $TreatyPercentage), 2);
                            $TreatySI = round((($QuotaPer)), 4);
                            $UsedFormula = 'Y';
                        }
                    }


                } elseif ($MasterDataArrayVale->formula_type == 'SURPLUS') {
                    $QSC = str_replace(",", "", $MasterDataArrayVale->si_allocation);
                    if ($TSI_Excess < $MasterDataArrayVale->si_allocation) {
                        $TreatySITotal = $TSI_Excess;
                    } else {
                        $TreatySITotal = $MasterDataArrayVale->si_allocation;
                    }

                    $TreatyPercentage = 0;
                    if ($TreatySITotal != 0.0 && $MasterDataArrayVale->TotalSumInsured != 0.0) {
                        $TreatyPercentage = round((((float) $TreatySITotal / (float) $MasterDataArrayVale->TotalSumInsured) * 100), 4);
                    }
                    if ($MasterDataArrayVale->TotalSumInsured > $QSC) {
                        //$QSC  = str_replace(",","",$MasterDataArrayVale->si_allocation);
                        //echo $MasterDataArrayVale->TotalPremium . " => ".$QSC." => ".$MasterDataArrayVale->TotalSumInsured;
                        $TreatyPremium = round((($MasterDataArrayVale->TotalPremium * $QSC) / $MasterDataArrayVale->TotalSumInsured), 4);
                        $TreatySI = round((($QSC)), 4);
                        $UsedFormula = 'Y';
                    }
                } elseif ($MasterDataArrayVale->formula_type == 'FACULTATIVE') {
                    $siAllocation = str_replace(",", "", $MasterDataArrayVale->si_allocation);

                    //echo "siAllocationAmt".$MasterDataArrayVale->si_allocation;
                    $FacPlaceFinalAMT = $siAllocation;
                    $TotalSumInsured = $MasterDataArrayVale->TotalSumInsured;
                    $TotalPremium = $MasterDataArrayVale->TotalPremium;
                    $reinsuranceType = $MasterDataArrayVale->reinsurance_type;
                    // if($MasterDataArrayVale->TotalSumInsured < $MasterDataArrayVale->si_allocation) {
                    //     $TreatySI = 0.00;
                    // }else{
                    //     $TreatySI = round(((float)$MasterDataArrayVale->TotalSumInsured - (float)$MasterDataArrayVale->si_allocation),4);
                    // }
                    // if($MasterDataArrayVale->TotalSumInsured == 0)
                    //     $MasterDataArrayVale->TotalSumInsured = 1;

                    $FACAuto = ($golbalQuota + $golbalNet);
                    $finalFacAuto = $TotalSumInsured - $FACAuto;
                    if ($finalFacAuto > $siAllocation) {
                        $FacAmt = $siAllocation;
                    } else {
                        $FacAmt = $TotalSumInsured - $FACAuto;
                    }

                    $FacPlaceAmt = $FacAmt;
                    $TreatyPercentage = ($FacAmt / $TotalSumInsured);
                    $TreatyPremium = round(($TotalPremium * $TreatyPercentage), 2);
                    $TreatySI = round((($FacPlaceAmt)), 4);
                    $UsedFormula = 'Y';
                } elseif ($MasterDataArrayVale->formula_type == 'FACULATIVEPLACEMENT') {
                    $siAllocation = str_replace(",", "", $MasterDataArrayVale->si_allocation);

                    if ($FacPlaceAmt <= $siAllocation) {

                        $TotalSumInsured = $MasterDataArrayVale->TotalSumInsured;
                        $TotalPremium = $MasterDataArrayVale->TotalPremium;
                        $reinsuranceType = $MasterDataArrayVale->reinsurance_type;

                        // echo "golbalNet".$golbalNet;
                        $FACAuto = ($golbalQuota + $golbalNet);
                        $FACAutoFinal = $QSCShare;
                        //echo $TotalSumInsured."=>".$siAllocation."=>".$QSCShare."=>".$golbalNet."=>".$golbalQuota;
                        $FacAmt = $TotalSumInsured - $siAllocation + $QSCShare - $golbalNet - $golbalQuota;
                        // if($MasterDataArrayVale->TotalSumInsured < $MasterDataArrayVale->si_allocation) {
                        //     $TreatySI = 0.00;
                        // }else{
                        //     $TreatySI = round(((float)$MasterDataArrayVale->TotalSumInsured - (float)$MasterDataArrayVale->si_allocation),4);
                        // }

                        $TreatyPercentage = ($FacAmt / $TotalSumInsured);
                        $TreatyPremium = round(($TotalPremium * $TreatyPercentage), 2);
                        $TreatySI = round((($FacAmt)), 4);
                        $UsedFormula = 'Y';

                    }


                }

                $policyReinsurrance = array(
                    'product_id' => $productId ?? "",
                    'risk_id' => $MasterDataArrayVale->risk_address_id ?? "",
                    'policy_id' => $policyId ?? "",
                    'term_id' => $termId ?? "",
                    'action_id' => $actionId ?? "",
                    'group_id' => $MasterDataArrayVale->group_id ?? "",
                    'formula_id' => $MasterDataArrayVale->formula_id ?? "",
                    'coverage_id' => $MasterDataArrayVale->pocoverage_detail_id ?? "",
                    'treaty_id' => $MasterDataArrayVale->treaty_id ?? "",
                    'type_id' => $MasterDataArrayVale->reinsurance_type_id ?? "",
                    'totalSumInsured' => $MasterDataArrayVale->TotalSumInsured,
                    'totalPremium' => $MasterDataArrayVale->TotalPremium ?? "",
                    'treatySI' => $TreatySI ?? "",
                    'treatyPercentage' => $TreatyPercentage ?? "",
                    'treatyPremium' => $TreatyPremium ?? "",
                    'UsedFormula' => $UsedFormula ?? "",
                    'added_by' => $userId ?? "",
                    'created_at' => $currentDateTime
                );
                PolicyReinsurance::insert($policyReinsurrance);

      
            }
        }
    }
    public static function MotorReinsurance($typeOfCoverMain, $int, $currentDate, $actionId, $typeOfCoverID, $policyId)
    {

        $DetailMotorCommQuery = "select pot.policy_id,cvgm.id as cvgmId,cvgm.coverage_id as mainCoverID, veh.vehicle_type as n_TypeOfMotor,veh.vehicle_type as n_TypeOfMotor, risk.id, cvgsm.id as pocoverage_detail_id,
            cvgsm.coverage_value_main AS  coverage_value,  
           cvgsm.calculated_value_main AS calculated_value, cvgsm.policy_coverage_id as subcoverage_id,cvgm.risk_address_id,T.*
            from policy_actions  pot
            left join policies pol on pol.id=pot.policy_id
            left join policy_coverages cvgm on cvgm.action_id = pot.id
            left join motor cvgsm ON cvgsm.policy_coverage_id=cvgm.id
            left join vehicle veh ON cvgsm.registration_no=veh.vehiclePlate
            inner join motor_type mt ON veh.vehicle_type=mt.id
            left join risk_address risk on risk.id = cvgm.risk_address_id
            LEFT JOIN
            (SELECT gd.coverage_id,tm.id as n_TreatyMaster_PK,
            gm.product_id,fm.product_id as n_Product_FK_fm,
            gm.id as n_GroupMaster_PK,gm.group_name,fm.id as n_FormulaMaster_PK, fm.type_id,fm.formula_name, fm.s_FormulaType,fd.operator,
            fd.si_allocation,fd.n_ValueLimitsBetween,fd.percentage, gd.si_premium, gd.ri_limit, gd.limit_value
            FROM reinsurance_treaty tm
            LEFT JOIN reinsurance_treaty_details td ON tm.id = td.treaty_id
            LEFT JOIN reinsurance_formula fm 	ON td.formula_attached = fm.id
            LEFT JOIN reinsurance_formula_details fd ON fm.id = fd.formula_id
            LEFT JOIN reinsurance_group gm ON fd.group_id = gm.id
            LEFT JOIN reinsurance_group_coverage gd ON gm.id = gd.group_id
            WHERE tm.effective_from <= '" . $currentDate . "'
            AND tm.effective_to >= '" . $currentDate . "'
            ORDER BY fm.id, gd.coverage_id) as T
            ON T.coverage_id=cvgm.coverage_id
            #  AND T.n_MotorType = COALESCE(veh.vehicle_type,0)
            where pot.id='" . $actionId . "'
            and n_GroupMaster_PK = '" . $int . "'
            AND pol.product_id=T.product_id
            and cvgm.coverage_id IN (22,27)
            AND cvgm.deleted_at IS NULL
            #and cvgsm.type_of_cover_main = '" . $typeOfCoverMain . "'
             #and mt.id = '" . $typeOfCoverID . "'
            and veh.policy_id = '" . $policyId . "'
            GROUP BY pocoverage_detail_id,n_FormulaMaster_PK";

        $DetailDataMotorCommArray = DB::select(DB::raw($DetailMotorCommQuery));
// dd($DetailDataMotorCommArray);
        $coverageIds = array_map(fn($data) => $data->cvgmId, $DetailDataMotorCommArray);

        // Fetch sum_insured and calculated_value for all policy_coverage_ids in one query
        $misItemsData = DB::select(DB::raw("
                select policy_specified_items.policy_coverage_id,coverage_id, SUM(sum_insured) as sum_insured, 
                SUM(calculated_value) as calculated_value from policy_specified_items
                INNER JOIN policy_coverages ON policy_coverages.id = policy_specified_items.policy_coverage_id
                where policy_coverage_id IN ('" . implode("','", $coverageIds) . "')
                AND policy_specified_items.deleted_at IS NULL
                GROUP BY policy_specified_items.policy_coverage_id
            "));

        // Loop through query results and organize data
        foreach ($misItemsData as $misItem) {
            $coverageId = $misItem->coverage_id;
            // Ensure sum_insured is treated as a float.
            $misItemsMap[$coverageId] = [
                'coverageId' => $coverageId,
                'sum_insured' => (float) $misItem->sum_insured,
                'calculated_value' => (float) $misItem->calculated_value,
            ];
        }
        $processedCovers = []; // Array to track processed mainCoverIDs
        foreach ($DetailDataMotorCommArray as $DetailData) {

            $SumInsured = 0.00;
            $SumInsuredPre = 0;
            $sum_insured = 0;
            $mainCoverID = $misItemsMap[$DetailData->mainCoverID]['coverageId'] ?? 0;
            $sum_insured = $misItemsMap[$DetailData->mainCoverID]['sum_insured'] ?? 0;
            $calculated_value = $misItemsMap[$DetailData->mainCoverID]['calculated_value'] ?? 0;

            $SumInsured = 0;
            if ($DetailData->ri_limit == 1) {
                //$SumInsured = $DetailData->coverage_value;
                if (!isset($processedCovers[$DetailData->mainCoverID])) {

                    // First time encountering this mainCoverID, include sum_insured
                    $SumInsured = $DetailData->coverage_value + $sum_insured;
                    $SumInsuredPre = $DetailData->calculated_value + $calculated_value;

                    // Mark this mainCoverID as processed
                    $processedCovers[$DetailData->mainCoverID] = true;

                } else {
                    // Already processed, do not add sum_insured again
                    $SumInsured = $DetailData->coverage_value;
                    $SumInsuredPre = $DetailData->calculated_value ?? "";
                }
            } else {
                //  $SumInsured = $DetailData->limit_value;
                if (!isset($processedCovers[$DetailData->mainCoverID])) {

                    // First time encountering this mainCoverID, include sum_insured

                    if (is_numeric($DetailData->limit_value)) {
                        $SumInsured = (float) $DetailData->limit_value + (float) $sum_insured;
                    } else {
                        $SumInsured = (float) $sum_insured;
                    }


                    // Mark this mainCoverID as processed
                    $processedCovers[$DetailData->mainCoverID] = true;
                } else {

                    // Already processed, do not add sum_insured again
                    $SumInsured = $DetailData->limit_value;
                }
            }

            $policyReinsurrance = array(
                'product_id' => $DetailData->product_id ?? "",
                'coverage_id' => $DetailData->coverage_id ?? "",
                'subcoverage_id' => $DetailData->subcoverage_id ?? "",
                'pocoverage_detail_id' => $DetailData->pocoverage_detail_id ?? "",
                'action_id' => $actionId ?? "",
                'policy_id' => $DetailData->policy_id ?? "",
                'group_id' => $DetailData->n_GroupMaster_PK ?? "",
                'treaty_id' => $DetailData->n_TreatyMaster_PK ?? "",
                'formula_id' => $DetailData->n_FormulaMaster_PK ?? "",
                'risk_address_id' => $DetailData->risk_address_id ?? "",
                's_FormulaType' => $DetailData->s_FormulaType ?? "",
                'n_SumInsured' => $SumInsured,
                'n_Premium' => $DetailData->calculated_value ?? "",
            );

            $d = PolicyReinsuranceDetails::insert($policyReinsurrance);

        }
    }
    public static function MotorTradersReinsurance($typeOfCoverMain, $int, $currentDate, $actionId)
    {
        $DetailMotorCommQueryext = "
    SELECT 
        pot.policy_id,
        cvgm.coverage_id AS mainCoverID,
        risk.id,
        cvgm.id AS cvgmId,
        COALESCE(cvgsm.id, cvgsmin.id) AS pocoverage_detail_id,

        COALESCE(
            (cvgsm.loss_or_damage_coverage_value + cvgsm.third_party_liability_coverage_value + cvgsm.medical_benefits_coverage_value),
            (cvgsmin.loss_or_damage_coverage_value + cvgsmin.third_party_liability_coverage_value + cvgsmin.medical_benefits_coverage_value)
        ) AS coverage_value,

        COALESCE(
            (cvgsm.loss_or_damage_calculated_value + cvgsm.third_party_liability_calculated_value + cvgsm.medical_benefits_calculated_value),
            (cvgsmin.loss_or_damage_calculated_value + cvgsmin.third_party_liability_calculated_value + cvgsmin.medical_benefits_calculated_value)
        ) AS calculated_value,

        cvgm.id AS subcoverage_id,
        cvgm.risk_address_id,
        T.*
    FROM policy_actions pot
    LEFT JOIN policies pol ON pol.id = pot.policy_id
    LEFT JOIN policy_coverages cvgm ON cvgm.action_id = pot.id
    LEFT JOIN motor_traders cvgsm ON cvgsm.policy_coverage_id = cvgm.id
    LEFT JOIN motor_traders_internal cvgsmin ON cvgsmin.policy_coverage_id = cvgm.id
    LEFT JOIN risk_address risk ON risk.id = cvgm.risk_address_id

    LEFT JOIN (
        SELECT 
            gd.coverage_id,
            tm.id AS n_TreatyMaster_PK,
            gm.product_id,
            fm.product_id AS n_Product_FK_fm,
            gm.id AS n_GroupMaster_PK,
            gm.group_name,
            fm.id AS n_FormulaMaster_PK,
            fm.type_id,
            fm.formula_name,
            fm.s_FormulaType,
            fd.operator,
            fd.si_allocation,
            fd.n_ValueLimitsBetween,
            fd.percentage,
            gd.si_premium,
            gd.ri_limit,
            gd.limit_value
        FROM reinsurance_treaty tm
        LEFT JOIN reinsurance_treaty_details td ON tm.id = td.treaty_id
        LEFT JOIN reinsurance_formula fm ON td.formula_attached = fm.id
        LEFT JOIN reinsurance_formula_details fd ON fm.id = fd.formula_id
        LEFT JOIN reinsurance_group gm ON fd.group_id = gm.id
        LEFT JOIN reinsurance_group_coverage gd ON gm.id = gd.group_id
        WHERE tm.effective_from <= '" . $currentDate . "'
        AND tm.effective_to >= '" . $currentDate . "'
        ORDER BY fm.id, gd.coverage_id
    ) AS T ON T.coverage_id = cvgm.coverage_id

    WHERE pot.id = '" . $actionId . "'
    AND n_GroupMaster_PK IN (13,31)
    AND pol.product_id = T.product_id
    AND cvgm.deleted_at IS NULL
    AND cvgm.coverage_id IN (15,16)
    GROUP BY 
        pot.policy_id,
        cvgm.coverage_id,
        risk.id,
        cvgm.id,
        pocoverage_detail_id,
        T.n_TreatyMaster_PK,
        T.coverage_id";

        //     $DetailMotorCommQueryext = "SELECT pot.policy_id,
        //     cvgm.coverage_id as mainCoverID,
        //     risk.id,
        //     cvgm.id as cvgmId,

        //     COALESCE(cvgsm.id, cvgsmin.id) AS pocoverage_detail_id,

        //     SUM(
        //         DISTINCT COALESCE(
        //             (cvgsm.loss_or_damage_coverage_value + cvgsm.third_party_liability_coverage_value + cvgsm.medical_benefits_coverage_value),
        //             (cvgsmin.loss_or_damage_coverage_value + cvgsmin.third_party_liability_coverage_value + cvgsmin.medical_benefits_coverage_value)
        //         )
        //     ) AS coverage_value,

        //     SUM(
        //         DISTINCT COALESCE(
        //             (cvgsm.loss_or_damage_calculated_value + cvgsm.third_party_liability_calculated_value + cvgsm.medical_benefits_calculated_value),
        //             (cvgsmin.loss_or_damage_calculated_value + cvgsmin.third_party_liability_calculated_value + cvgsmin.medical_benefits_calculated_value)
        //         )
        //     ) AS calculated_value,

        //     cvgm.id AS subcoverage_id,
        //     cvgm.risk_address_id,
        //     T.*
        // FROM policy_actions pot
        // LEFT JOIN policies pol ON pol.id = pot.policy_id
        // LEFT JOIN policy_coverages cvgm ON cvgm.action_id = pot.id
        // LEFT JOIN motor_traders cvgsm ON cvgsm.policy_coverage_id = cvgm.id
        // LEFT JOIN motor_traders_internal cvgsmin ON cvgsmin.policy_coverage_id = cvgm.id
        // LEFT JOIN risk_address risk ON risk.id = cvgm.risk_address_id

        // LEFT JOIN (
        //     SELECT 
        //         gd.coverage_id,
        //         tm.id AS n_TreatyMaster_PK,
        //         gm.product_id,
        //         fm.product_id AS n_Product_FK_fm,
        //         gm.id AS n_GroupMaster_PK,
        //         gm.group_name,
        //         fm.id AS n_FormulaMaster_PK,
        //         fm.type_id,
        //         fm.formula_name,
        //         fm.s_FormulaType,
        //         fd.operator,
        //         fd.si_allocation,
        //         fd.n_ValueLimitsBetween,
        //         fd.percentage,
        //         gd.si_premium,
        //         gd.ri_limit,
        //         gd.limit_value
        //     FROM reinsurance_treaty tm
        //     LEFT JOIN reinsurance_treaty_details td ON tm.id = td.treaty_id
        //     LEFT JOIN reinsurance_formula fm ON td.formula_attached = fm.id
        //     LEFT JOIN reinsurance_formula_details fd ON fm.id = fd.formula_id
        //     LEFT JOIN reinsurance_group gm ON fd.group_id = gm.id
        //     LEFT JOIN reinsurance_group_coverage gd ON gm.id = gd.group_id
        //     WHERE tm.effective_from <= '".$currentDate."'
        //     AND tm.effective_to >= '".$currentDate."'
        //     ORDER BY fm.id, gd.coverage_id
        // ) AS T ON T.coverage_id = cvgm.coverage_id

        // WHERE pot.id = '".$actionId."'
        // #AND n_GroupMaster_PK = '".$int."'
        // AND n_GroupMaster_PK in (13,31)
        // AND pol.product_id = T.product_id
        // AND cvgm.deleted_at IS NULL
        // AND cvgm.coverage_id IN (15,16)";

        //old code by sonali  

        //  $DetailMotorCommQueryext = "select pot.policy_id,cvgm.id as cvgmId,cvgm.coverage_id as mainCoverID, 
        // risk.id, cvgsm.id as pocoverage_detail_id,
        // SUM(distinct cvgsm.loss_or_damage_coverage_value + cvgsm.third_party_liability_coverage_value + cvgsm.medical_benefits_coverage_value) as coverage_value,
        // SUM(distinct cvgsm.loss_or_damage_calculated_value + cvgsm.third_party_liability_calculated_value + cvgsm.medical_benefits_calculated_value) as calculated_value,		
        //     cvgm.id as subcoverage_id,cvgm.risk_address_id,T.*
        //     from policy_actions  pot
        //     left join policies pol on pol.id=pot.policy_id
        //     left join policy_coverages cvgm on cvgm.action_id = pot.id            
        //     left join motor_traders cvgsm ON cvgsm.policy_coverage_id=cvgm.id
        //     left join motor_traders_internal cvgsmin ON cvgsmin.policy_coverage_id=cvgm.id
        //     # left join policy_coverage_entities cvget ON cvget.policy_coverage_id=cvgm.id
        //     # left join vehicle veh ON cvget.entity_id=veh.id
        //     left join risk_address risk on risk.id = cvgm.risk_address_id
        //     LEFT JOIN
        //     (SELECT gd.coverage_id,tm.id as n_TreatyMaster_PK,
        //     gm.product_id,fm.product_id as n_Product_FK_fm,
        //     gm.id as n_GroupMaster_PK,gm.group_name,fm.id as n_FormulaMaster_PK, fm.type_id,fm.formula_name, fm.s_FormulaType,fd.operator,
        //     fd.si_allocation,fd.n_ValueLimitsBetween,fd.percentage, gd.si_premium, gd.ri_limit, gd.limit_value
        //     FROM reinsurance_treaty tm
        //     LEFT JOIN reinsurance_treaty_details td ON tm.id = td.treaty_id
        //     LEFT JOIN reinsurance_formula fm 	ON td.formula_attached = fm.id
        //     LEFT JOIN reinsurance_formula_details fd ON fm.id = fd.formula_id
        //     LEFT JOIN reinsurance_group gm ON fd.group_id = gm.id
        //     LEFT JOIN reinsurance_group_coverage gd ON gm.id = gd.group_id
        //     WHERE tm.effective_from <= '".$currentDate."'
        //     AND tm.effective_to >= '".$currentDate."'
        //     ORDER BY fm.id, gd.coverage_id) as T
        //     ON T.coverage_id=cvgm.coverage_id            
        //     where pot.id= '".$actionId."'
        //      and n_GroupMaster_PK = '".$int."'
        //     AND pol.product_id=T.product_id
        //     AND cvgm.deleted_at IS NULL
        //     and cvgm.coverage_id IN (15,16)";

        $DetailDataMotorCommArrayext = DB::select(DB::raw($DetailMotorCommQueryext));

        // Fetch all unique coverage IDs
        $coverageIds = array_map(fn($data) => $data->cvgmId, $DetailDataMotorCommArrayext);

        // Fetch sum_insured and calculated_value for all policy_coverage_ids in one query
        $misItemsData = DB::select(DB::raw("
                select policy_specified_items.policy_coverage_id,coverage_id, SUM(sum_insured) as sum_insured, 
                SUM(calculated_value) as calculated_value from policy_specified_items
                INNER JOIN policy_coverages ON policy_coverages.id = policy_specified_items.policy_coverage_id
                where policy_coverage_id IN ('" . implode("','", $coverageIds) . "')
                AND policy_specified_items.deleted_at IS NULL
                GROUP BY policy_specified_items.policy_coverage_id
            "));

        // Loop through query results and organize data
        foreach ($misItemsData as $misItem) {

            $coverageId = $misItem->coverage_id;
            // Ensure sum_insured is treated as a float.
            $misItemsMap[$coverageId] = [
                'coverageId' => $coverageId,
                'sum_insured' => (float) $misItem->sum_insured,
                'calculated_value' => (float) $misItem->calculated_value,
            ];

        }

        $processedCovers = []; // Array to track processed mainCoverIDs
        foreach ($DetailDataMotorCommArrayext as $DetailData) {

            $SumInsured = 0.00;
            $SumInsuredPre = 0;
            $sum_insured = 0;
            $mainCoverID = $misItemsMap[$DetailData->mainCoverID]['coverageId'] ?? 0;
            $sum_insured = $misItemsMap[$DetailData->mainCoverID]['sum_insured'] ?? 0;
            $calculated_value = $misItemsMap[$DetailData->mainCoverID]['calculated_value'] ?? 0;

            if ($DetailData->ri_limit == 1) {
                if (!isset($processedCovers[$DetailData->mainCoverID])) {

                    // First time encountering this mainCoverID, include sum_insured
                    $SumInsured = $DetailData->coverage_value + $sum_insured;
                    $SumInsuredPre = $DetailData->calculated_value + $calculated_value;

                    // Mark this mainCoverID as processed
                    $processedCovers[$DetailData->mainCoverID] = true;

                } else {
                    // Already processed, do not add sum_insured again
                    $SumInsured = $DetailData->coverage_value;
                    $SumInsuredPre = $DetailData->calculated_value ?? "";
                }
            } else {
                if (!isset($processedCovers[$DetailData->mainCoverID])) {

                    // First time encountering this mainCoverID, include sum_insured

                    if (is_numeric($DetailData->limit_value)) {
                        $SumInsured = (float) $DetailData->limit_value + (float) $sum_insured;
                    } else {
                        $SumInsured = (float) $sum_insured;
                    }


                    // Mark this mainCoverID as processed
                    $processedCovers[$DetailData->mainCoverID] = true;
                } else {

                    // Already processed, do not add sum_insured again
                    $SumInsured = $DetailData->limit_value;
                }
            }

            $policyReinsurrance = array(
                'product_id' => $DetailData->product_id ?? "",
                'coverage_id' => $DetailData->coverage_id ?? "",
                'subcoverage_id' => $DetailData->subcoverage_id ?? "",
                'pocoverage_detail_id' => $DetailData->pocoverage_detail_id ?? "",
                'action_id' => $actionId ?? "",
                'policy_id' => $DetailData->policy_id ?? "",
                'group_id' => $DetailData->n_GroupMaster_PK ?? "",
                'treaty_id' => $DetailData->n_TreatyMaster_PK ?? "",
                'formula_id' => $DetailData->n_FormulaMaster_PK ?? "",
                'risk_address_id' => $DetailData->risk_address_id ?? "",
                's_FormulaType' => $DetailData->s_FormulaType ?? "",
                'n_SumInsured' => $SumInsured,
                'n_Premium' => $DetailData->calculated_value ?? "",
            );

            $d = PolicyReinsuranceDetails::insert($policyReinsurrance);

        }
    }

    public static function MotorMotorTrailerReinsurance($typeOfCoverMain, $int, $currentDate, $actionId, $typeOfCoverID, $policyId)
    {
        $DetailMotorCommQuery = "select pot.policy_id,cvgm.coverage_id, veh.vehicle_type as n_TypeOfMotor,veh.vehicle_type as n_TypeOfMotor, risk.id, cvgsm.id as pocoverage_detail_id,
            cvgsm.coverage_value_main AS  coverage_value,      
            cvgsm.calculated_value_main AS calculated_value, cvgsm.policy_coverage_id as subcoverage_id,cvgm.risk_address_id,T.*
            from policy_actions  pot
            left join policies pol on pol.id=pot.policy_id
            left join policy_coverages cvgm on cvgm.action_id = pot.id
            left join motor cvgsm ON cvgsm.policy_coverage_id=cvgm.id
            left join vehicle veh ON cvgsm.registration_no=veh.vehiclePlate
            inner join motor_type mt ON veh.vehicle_type=mt.id
            left join risk_address risk on risk.id = cvgm.risk_address_id
            LEFT JOIN
            (SELECT gd.coverage_id,tm.id as n_TreatyMaster_PK,
            gm.product_id,fm.product_id as n_Product_FK_fm,
            gm.id as n_GroupMaster_PK,gm.group_name,fm.id as n_FormulaMaster_PK, fm.type_id,fm.formula_name, fm.s_FormulaType,fd.operator,
            fd.si_allocation,fd.n_ValueLimitsBetween,fd.percentage, gd.si_premium, gd.ri_limit, gd.limit_value
            FROM reinsurance_treaty tm
            LEFT JOIN reinsurance_treaty_details td ON tm.id = td.treaty_id
            LEFT JOIN reinsurance_formula fm 	ON td.formula_attached = fm.id
            LEFT JOIN reinsurance_formula_details fd ON fm.id = fd.formula_id
            LEFT JOIN reinsurance_group gm ON fd.group_id = gm.id
            LEFT JOIN reinsurance_group_coverage gd ON gm.id = gd.group_id
            WHERE tm.effective_from <= '" . $currentDate . "'
            AND tm.effective_to >= '" . $currentDate . "'
            ORDER BY fm.id, gd.coverage_id) as T
            ON T.coverage_id=cvgm.coverage_id
            #  AND T.n_MotorType = COALESCE(veh.vehicle_type,0)
            where pot.id= '" . $actionId . "'
            and n_GroupMaster_PK = '" . $int . "'
            AND pol.product_id=T.product_id
            and cvgm.coverage_id IN (22,27)
            AND cvgm.deleted_at IS NULL
            and cvgsm.type_of_cover_main = '" . $typeOfCoverMain . "'
            and mt.id = '" . $typeOfCoverID . "'
            and veh.policy_id = '" . $policyId . "'
           GROUP BY pocoverage_detail_id,n_FormulaMaster_PK";

        $DetailDataMotorCommArray = DB::select(DB::raw($DetailMotorCommQuery));

        foreach ($DetailDataMotorCommArray as $DetailData) {
            $SumInsured = 0;
            if ($DetailData->ri_limit == 1) {
                $SumInsured = $DetailData->coverage_value;
            } else {
                $SumInsured = $DetailData->limit_value;
            }
            $policyReinsurrance = array(
                'product_id' => $DetailData->product_id ?? "",
                'coverage_id' => $DetailData->coverage_id ?? "",
                'subcoverage_id' => $DetailData->subcoverage_id ?? "",
                'pocoverage_detail_id' => $DetailData->pocoverage_detail_id ?? "",
                'action_id' => $actionId ?? "",
                'policy_id' => $DetailData->policy_id ?? "",
                'group_id' => $DetailData->n_GroupMaster_PK ?? "",
                'treaty_id' => $DetailData->n_TreatyMaster_PK ?? "",
                'formula_id' => $DetailData->n_FormulaMaster_PK ?? "",
                'risk_address_id' => $DetailData->risk_address_id ?? "",
                's_FormulaType' => $DetailData->s_FormulaType ?? "",
                'n_SumInsured' => $SumInsured,
                'n_Premium' => $DetailData->calculated_value ?? "",
            );

            $d = PolicyReinsuranceDetails::insert($policyReinsurrance);

        }
    }
    public static function FidelityGuarantee($int, $currentDate, $actionId)
    {
        $DetailFidelityGuaranteeCommQuery = "select pot.policy_id,cvgm.coverage_id, 
       cvgm.id as cvgmId,COALESCE(veh.vehicle_type,0) as n_TypeOfMotor, risk.id, cvgsm.id as pocoverage_detail_id,
         cvgsm.premium AS calculated_value,  
         cvgsm.amount_to_be_guaranteed AS coverage_value, 
         cvgsm.policyCoverageID as subcoverage_id,cvgsm.risk_address as risk_address_id,T.*
        from policy_actions  pot
        left join policies pol on pol.id=pot.policy_id
        left join policy_coverages cvgm on cvgm.action_id = pot.id
        # left join policy_coverage_detail cvgsm ON cvgsm.policy_coverage_id=cvgm.id
         left join policy_coverages_data cvgsm ON cvgsm.policyCoverageID=cvgm.id
        left join policy_coverage_entities cvget ON cvget.policy_coverage_id=cvgm.id
        left join vehicle veh ON cvget.entity_id=veh.id
        left join risk_address risk on risk.id = cvgsm.risk_address
        LEFT JOIN
        (SELECT gd.coverage_id,tm.id as n_TreatyMaster_PK,
        gm.product_id,fm.product_id as n_Product_FK_fm,
        gm.id as n_GroupMaster_PK,gm.group_name,fm.id as n_FormulaMaster_PK, fm.type_id,fm.formula_name, fm.s_FormulaType,fd.operator,
        fd.si_allocation,fd.n_ValueLimitsBetween,fd.percentage, gd.si_premium, gd.ri_limit, gd.limit_value, COALESCE(fd.vehicle_type,0)  as n_MotorType
        FROM reinsurance_treaty tm
        LEFT JOIN reinsurance_treaty_details td ON tm.id = td.treaty_id
        LEFT JOIN reinsurance_formula fm 	ON td.formula_attached = fm.id
        LEFT JOIN reinsurance_formula_details fd ON fm.id = fd.formula_id
        LEFT JOIN reinsurance_group gm ON fd.group_id = gm.id
        LEFT JOIN reinsurance_group_coverage gd ON gm.id = gd.group_id
        WHERE tm.effective_from <= '" . $currentDate . "'
        AND tm.effective_to >= '" . $currentDate . "'
        ORDER BY fm.id, gd.coverage_id) as T
        ON T.coverage_id=cvgm.coverage_id
        AND T.n_MotorType = COALESCE(veh.vehicle_type,0)
        where pot.id='" . $actionId . "'
        and cvgm.coverage_id = '" . $int . "'
        AND pol.product_id=T.product_id
        AND cvgm.deleted_at IS NULL
        AND cvgsm.risk_address IS NOT NULL
        AND cvgsm.premium IS NOT NULL
        AND cvgsm.amount_to_be_guaranteed IS NOT NULL
        GROUP BY pocoverage_detail_id,n_TreatyMaster_PK,
        -- GROUP BY pot.policy_id, pot.id, risk.id,cvgm.coverage_id,
        case
            when  T.type_id = 35 then cvgsm.policyCoverageID
            else ''
            end
        order by risk.id, cvgm.coverage_id, T.group_name";

        $DetailFidelityGuaranteeCommArray = DB::select(DB::raw($DetailFidelityGuaranteeCommQuery));

        foreach ($DetailFidelityGuaranteeCommArray as $DetailData) {
            $misItemsData = DB::table('policy_specified_items')
                ->select(
                    'policy_specified_items.policy_coverage_id',
                    'policy_coverages.coverage_id',
                    DB::raw('SUM(sum_insured) as sum_insured'),
                    DB::raw('SUM(calculated_value) as calculated_value')
                )
                ->join('policy_coverages', 'policy_coverages.id', '=', 'policy_specified_items.policy_coverage_id')
                ->where('policy_specified_items.policy_coverage_id', $DetailData->subcoverage_id)
                ->whereNull('policy_specified_items.deleted_at')
                ->groupBy('policy_specified_items.policy_coverage_id')
                ->first();


            // Ensure safe access with optional chaining (null coalescing)
            $sum_insured = $misItemsData->sum_insured ?? 0;
            $calculated_value = $misItemsData->calculated_value ?? 0;
            $SumInsured = 0;
            if ($DetailData->ri_limit == 1) {
                $SumInsured = $DetailData->coverage_value + $sum_insured;
            } else {
                $SumInsured = $DetailData->limit_value;
            }
            $calculatedValue = $DetailData->calculated_value + $calculated_value;
            $policyReinsurrance = array(
                'product_id' => $DetailData->product_id ?? "",
                'coverage_id' => $DetailData->coverage_id ?? "",
                'subcoverage_id' => $DetailData->subcoverage_id ?? "",
                'pocoverage_detail_id' => $DetailData->pocoverage_detail_id ?? "",
                'action_id' => $actionId ?? "",
                'policy_id' => $DetailData->policy_id ?? "",
                'group_id' => $DetailData->n_GroupMaster_PK ?? "",
                'treaty_id' => $DetailData->n_TreatyMaster_PK ?? "",
                'formula_id' => $DetailData->n_FormulaMaster_PK ?? "",
                'risk_address_id' => $DetailData->risk_address_id ?? "",
                's_FormulaType' => $DetailData->s_FormulaType ?? "",
                'n_SumInsured' => $SumInsured,
                'n_Premium' => $calculatedValue ?? "",
            );

            $d = PolicyReinsuranceDetails::insert($policyReinsurrance);

        }
    }

    public static function ExcessOfLossCoverage($int, $currentDate, $actionId)
    {

        $DetailQuery = "select pot.policy_id,cvgm.id as cvgmId,cvgm.coverage_id as mainCoverID,risk.id, cvgsm.id as pocoverage_detail_id,
         
             # added by snehal 
            CASE 
                WHEN cvgsm.coverage_value != 0.00 THEN cvgsm.coverage_value 
                WHEN cvgsm.ratefactor_value != 0 THEN cvgsm.ratefactor_value 
                WHEN REPLACE(cvgsm.ratefactor_AnnualWages, ',', '') != '' 
                    AND REPLACE(cvgsm.ratefactor_AnnualWages, ',', '') != '0' 
                    THEN REPLACE(cvgsm.ratefactor_AnnualWages, ',', '')
                ELSE cvgsm.coverage_value_string
            END AS coverage_value,     
            cvgsm.calculated_value, 
            cvgsm.coverage_id as subcoverage_id,
            cvgm.risk_address_id,T.*
            from policy_actions  pot
            left join policies pol on pol.id=pot.policy_id
            left join policy_coverages cvgm on cvgm.action_id = pot.id
            left join policy_coverage_detail cvgsm ON cvgsm.policy_coverage_id=cvgm.id
            left join risk_address risk on risk.id = cvgm.risk_address_id
            LEFT JOIN
            (SELECT tc.id as tID,gd.coverage_id,tm.id as n_TreatyMaster_PK,
            gm.product_id,fm.product_id as n_Product_FK_fm,
            gm.id as n_GroupMaster_PK,gm.group_name,fm.id as n_FormulaMaster_PK, fm.type_id,fm.formula_name, fm.s_FormulaType,fd.operator,
            fd.si_allocation,fd.n_ValueLimitsBetween,fd.percentage, gd.si_premium, gd.ri_limit, gd.limit_value, COALESCE(fd.vehicle_type,0)  as n_MotorType
            FROM reinsurance_treaty tm
            LEFT JOIN reinsurance_treaty_details td ON tm.id = td.treaty_id
            LEFT JOIN reinsurance_formula fm ON td.formula_attached = fm.id
            LEFT JOIN reinsurance_formula_details fd ON fm.id = fd.formula_id
            LEFT JOIN reinsurance_group gm ON fd.group_id = gm.id
            LEFT JOIN reinsurance_group_coverage gd ON gm.id = gd.group_id

           INNER JOIN tb_cvgpccoverages tc ON tc.id = gd.coverage_id

            WHERE tm.effective_from <= '" . $currentDate . "'
                    AND tm.effective_to >= '" . $currentDate . "'
            ) as T
            ON T.tID=cvgsm.coverage_id
       
            where pot.id= '" . $actionId . "'
        
            AND pol.product_id=T.product_id
            AND cvgm.deleted_at IS NULL
            # condition added by snehal for specific coverage for which function is calling
            AND cvgm.coverage_id='" . $int . "'
            GROUP BY pocoverage_detail_id,n_TreatyMaster_PK,
       
            case
                when  T.type_id = 35 then cvgsm.coverage_id
                else ''
                end
            order by risk.id, cvgm.coverage_id, T.group_name";
        // dd($DetailQuery);
        $DetailExcessOfLossCoverageCommArray = DB::select(DB::raw($DetailQuery));
       // dd($DetailExcessOfLossCoverageCommArray);
        // Fetch all unique coverage IDs
        $coverageIds = array_map(fn($data) => $data->cvgmId, $DetailExcessOfLossCoverageCommArray);
        $coverageIds = array_unique($coverageIds);
       
  $misItemsData = DB::select(DB::raw("
                select policy_specified_items.policy_coverage_id,coverage_id, SUM(sum_insured) as sum_insured, 
                SUM(calculated_value) as calculated_value from policy_specified_items
                INNER JOIN policy_coverages ON policy_coverages.id = policy_specified_items.policy_coverage_id
                where policy_coverage_id IN ('" . implode("','", $coverageIds) . "')
                AND policy_specified_items.deleted_at IS NULL
                GROUP BY policy_specified_items.policy_coverage_id
            "));
                foreach ($misItemsData as $misItem) {
                $coverageId = $misItem->coverage_id;
                // Ensure sum_insured is treated as a float.
                $misItemsMap[$coverageId] = [
                    'coverageId' => $coverageId,
                    'sum_insured' => (float) $misItem->sum_insured,
                    'calculated_value' => (float) $misItem->calculated_value,
                ];
            }

        $misItemsData1 = 0;
        $SumInsured = 0;

            //    $misItemsData1 = DB::table('policy_specified_items')
            //     ->select(
            //         'policy_specified_items.policy_coverage_id',
            //         'policy_coverages.coverage_id',
            //         DB::raw('SUM(sum_insured) as sum_insured'),
            //         DB::raw('SUM(calculated_value) as calculated_value')
            //     )
            //    ->join('policy_coverages', 'policy_coverages.id', '=', 'policy_specified_items.policy_coverage_id')
            //  //   ->where('policy_specified_items.policy_coverage_id', $DetailData->cvgmId)
            //    ->whereIn('policy_specified_items.policy_coverage_id', $coverageIds)  
            //  ->whereNull('policy_specified_items.deleted_at')
            //     ->groupBy('policy_specified_items.policy_coverage_id')
            //     ->first();
            // // Ensure safe access with optional chaining (null coalescing)
            // $sum_insured = $misItemsData1->sum_insured ?? 0;
            // $calculated_value = $misItemsData1->calculated_value ?? 0;
            // dd($sum_insured);
        // dd($DetailExcessOfLossCoverageCommArray);
 $processedCovers = []; // Array to track processed mainCoverIDs
        foreach ($DetailExcessOfLossCoverageCommArray as $DetailData) {
          
                $SumInsured = 0.00;
                $SumInsuredPre = 0;
                $sum_insured = 0;
                $mainCoverID = $misItemsMap[$DetailData->mainCoverID]['coverageId'] ?? 0;
                $sum_insured = $misItemsMap[$DetailData->mainCoverID]['sum_insured'] ?? 0;
                $calculated_value = $misItemsMap[$DetailData->mainCoverID]['calculated_value'] ?? 0;



         //    dd($sum_insured);
        //    if (isset($DetailData->coverage_value) && (float)$DetailData->coverage_value === 0.0) {
        //       $SumInsured = $DetailData->limit_value + (float) $sum_insured;
              
        //     } else {
               
        //         $SumInsured = (float) $DetailData->coverage_value + (float) $sum_insured;
        //     }
            // dd($SumInsured);
            // if ($DetailData->ri_limit == 1) {
            //     $SumInsured = (float) $DetailData->coverage_value + (float) $sum_insured;
            // } else {
            //     $SumInsured = $DetailData->limit_value;
            // }
          
            $calculatedValue = $DetailData->calculated_value + $calculated_value;

                if ($DetailData->ri_limit == 1) {

                    if (!isset($processedCovers[$DetailData->mainCoverID])) {

                        // First time encountering this mainCoverID, include sum_insured
                        $SumInsured = $DetailData->coverage_value + $sum_insured;
                        $SumInsuredPre = $DetailData->calculated_value + $calculated_value;

                        // Mark this mainCoverID as processed
                        $processedCovers[$DetailData->mainCoverID] = true;

                    } else {
                        // Already processed, do not add sum_insured again
                        $SumInsured = $DetailData->coverage_value;
                        $SumInsuredPre = $DetailData->calculated_value ?? "";
                    }

                } else {
               
                    if (!isset($processedCovers[$DetailData->mainCoverID])) {
                        if (is_numeric($DetailData->limit_value)) {
                            $SumInsured = (float) $DetailData->limit_value + (float) $sum_insured;
                        } else {
                            $SumInsured = (float) $sum_insured;
                        }


                        // Mark this mainCoverID as processed
                        $processedCovers[$DetailData->mainCoverID] = true;
                    } else {

                        // Already processed, do not add sum_insured again
                        $SumInsured = $DetailData->limit_value;
                    }
                }
            
            $policyReinsurrance = array(
                'product_id' => $DetailData->product_id ?? "",
                'coverage_id' => $DetailData->mainCoverID ?? "",
                'subcoverage_id' => $DetailData->subcoverage_id ?? "",
                'pocoverage_detail_id' => $DetailData->pocoverage_detail_id ?? "",
                'action_id' => $actionId ?? "",
                'policy_id' => $DetailData->policy_id ?? "",
                'group_id' => $DetailData->n_GroupMaster_PK ?? "",
                'treaty_id' => $DetailData->n_TreatyMaster_PK ?? "",
                'formula_id' => $DetailData->n_FormulaMaster_PK ?? "",
                'risk_address_id' => $DetailData->risk_address_id ?? "",
                's_FormulaType' => $DetailData->s_FormulaType ?? "",
                'n_SumInsured' => $SumInsured,
                'n_Premium' => $calculatedValue ?? "",
            );
          
            $d = PolicyReinsuranceDetails::insert($policyReinsurrance);
           

        }

    }

    public static function CalculateEarnedPremium($PolicyNo, $PolicyId, $actionId, $TermId, $TransactionType)
    {

        $currentDateTime = Carbon::now()->format('Y-m-d H:i:s');
        $userId = Auth::user()->id;

        $TbEarnedpremiumDaypremiummaster = new TbEarnedpremiumDaypremiummaster();
        if ($TransactionType == 'NEWBUSINESS') {

            $termInfo = PolicyTerm::where('id', $TermId)
                ->select('term_start_date', 'term_end_date')
                ->first();

            $TermStartDate = $termInfo->term_start_date;
            $TermEndDate = $termInfo->term_start_date;
            // $TermSequence = $termInfo->n_TermSequence;

            $TransactionInfo = PolicyAction::where('id', $actionId)
                ->select('premium', 'transaction_type', 'effective_from', 'effective_to', 'transaction_date')
                ->first();

            $annualPremium = $TransactionInfo->premium;//this varibable override below
            $writtenPremium = $TransactionInfo->premium;
            $TransEffectiveFrom = $TransactionInfo->effective_from;
            $TransEffectiveTo = $TransactionInfo->effective_to;
            $TransDate = explode(" ", $TransactionInfo->transaction_date);
            $TransDate = $TransDate[0];


            $NoOfDays = DB::select(DB::raw("SELECT DATEDIFF(?, ?) AS NoOfDays"), [$TransEffectiveTo, $TransEffectiveFrom])[0]->NoOfDays;

            /*if($NoOfDays==366)
            {
                $NoOfDays = 365;
            }*/

            $perDayPremium = bcdiv($writtenPremium, $NoOfDays, 4);

            $CurrentDate = $TransDate;
            $BookedDate = $TransDate;

            if (date($CurrentDate) > date($TransEffectiveFrom)) {
                //making the adjustment entry
                $nratedays = DB::select(DB::raw("SELECT DATEDIFF(?, ?) AS Date_Diff"), [$CurrentDate, $TransEffectiveFrom])[0]->Date_Diff;
                $reverseAmt = bcmul($nratedays, $perDayPremium, 4);

                $TbEarnedpremiumDaypremiummaster->policyNumber = $PolicyNo;
                $TbEarnedpremiumDaypremiummaster->policy_id = $PolicyId;
                // $TbEarnedpremiumDaypremiummaster->d_Date = $CurrentDate;
                // $TbEarnedpremiumDaypremiummaster->d_TermStartDate = $TermStartDate;
                // $TbEarnedpremiumDaypremiummaster->d_TermEndDate = $TermEndDate;
                // $TbEarnedpremiumDaypremiummaster->n_TermSequence = $TermSequence;
                $TbEarnedpremiumDaypremiummaster->term_id = $TermId;
                $TbEarnedpremiumDaypremiummaster->action_id = $actionId;
                $TbEarnedpremiumDaypremiummaster->s_TransactionType = $TransactionType; //'-ADJ';
                // $TbEarnedpremiumDaypremiummaster->d_TransactionDate = $TransDate;
                // $TbEarnedpremiumDaypremiummaster->d_BookingDate= $BookedDate;
                $TbEarnedpremiumDaypremiummaster->d_StartDate = $TransEffectiveFrom;
                $TbEarnedpremiumDaypremiummaster->d_EndDate = $TransEffectiveTo;
                $TbEarnedpremiumDaypremiummaster->n_NoOfDays = $nratedays;
                // $TbEarnedpremiumDaypremiummaster->n_NoOfDaysRemain = 0;
                $TbEarnedpremiumDaypremiummaster->n_WrittenPremium = 0;
                // $TbEarnedpremiumDaypremiummaster->annualPremium = 0;
                $TbEarnedpremiumDaypremiummaster->n_PerDayPremium = $perDayPremium;
                // $TbEarnedpremiumDaypremiummaster->earnedPremium = $reverseAmt;
                // $TbEarnedpremiumDaypremiummaster->unearnedPremium = 0;
                $TbEarnedpremiumDaypremiummaster->s_CalculationMethod = 'Total';
                $TbEarnedpremiumDaypremiummaster->s_IsActive = 'Y';
                $TbEarnedpremiumDaypremiummaster->s_IsPosted = 'N';
                $TbEarnedpremiumDaypremiummaster->created_at = $currentDateTime;
                $TbEarnedpremiumDaypremiummaster->added_by = $userId;
                $TbEarnedpremiumDaypremiummaster->save();

                $Date = explode("-", $TransDate);
                $eachDate = $TransDate;
            } else {
                $Date = explode("-", $TransEffectiveFrom);
                $eachDate = $TransEffectiveFrom;
            }

            $BookedDate = $TransDate;
            $queryPart = '';
            for ($i = 1; $i <= $NoOfDays; $i++) {
                $NoofDaysremain = $NoOfDays - $i;
                // $earnedPremium = $perDayPremium;
                // $unearnedPremium = $perDayPremium*$NoofDaysremain;

                $tb_EarnedpremiumDaypremiummaster = array(
                    'policyNumber' => $PolicyNo ?? "",
                    'policy_id' => $PolicyId ?? "",
                    // 'd_Date'       => $eachDate??"",
                    'd_StartDate' => $TermStartDate ?? "",
                    'd_EndDate' => $TermEndDate ?? "",
                    // 'n_TermSequence'        => $TermSequence??"",
                    'term_id' => $TermId,
                    'action_id' => $actionId ?? "",
                    's_TransactionType' => $TransactionType,
                    // 'd_TransactionDate'         => $TransDate,
                    // 'd_BookingDate' => $BookedDate,
                    'd_StartDate' => $TransEffectiveFrom ?? "",
                    'd_EndDate' => $TransEffectiveTo ?? "",
                    'n_NoOfDays' => $NoOfDays ?? "",
                    // 'n_NoOfDaysRemain' => $NoofDaysremain??"",
                    'n_WrittenPremium' => $writtenPremium,
                    // 'n_AnnualPremium' => $annualPremium??"",
                    // 'n_PerDayPremium' => $perDayPremium??"",
                    // 'n_EarnedPremium' => $earnedPremium??"",
                    // 'n_UnEarnedPremium' => $unearnedPremium,
                    's_CalculationMethod' => 'Total',
                    's_IsActive' => 'Y',
                    's_IsPosted' => 'N',
                    'created_at' => $currentDateTime,
                    'added_by' => $userId

                );
                TbEarnedpremiumDaypremiummaster::insert($tb_EarnedpremiumDaypremiummaster);
                // $eachDate = $this->GetDateTime_Hp("Y-m-d",'',mktime(0,0,0,$Date[1],$Date[2]+$i,$Date[0]));
            }

        }
        if ($TransactionType == 'RENEW') {
            $termInfo = PolicyTerm::where('id', $TermId)
                ->select('term_start_date', 'term_end_date')
                ->first();

            $TermStartDate = $termInfo->term_start_date;
            $TermEndDate = $termInfo->term_end_date;
            // $TermSequence = $termInfo->n_TermSequence;

            $TransactionInfo = PolicyAction::where('id', $actionId)
                ->select('premium', 'transaction_type', 'effective_from', 'effective_to', 'transaction_date')
                ->first();

            $annualPremium = $TransactionInfo->premium; //this varibable override below
            $writtenPremium = $TransactionInfo->premium;
            $TransEffectiveFrom = $TransactionInfo->effective_from;
            $TransEffectiveTo = $TransactionInfo->effective_to;
            $TransDate = explode(" ", $TransactionInfo->transaction_date);
            $TransDate = $TransDate[0];
            // $CommTRatePrimary	=  $TransactionInfo->n_CommTRatePrimary;

            $NoOfDays = DB::select(DB::raw("SELECT DATEDIFF(?, ?) AS NoOfDays"), [$TransEffectiveTo, $TransEffectiveFrom])[0]->NoOfDays;

            /*if($NoOfDays==366)
            {
                $NoOfDays = 365;
            }*/

            $perDayPremium = bcdiv($writtenPremium, $NoOfDays, 4);

            $CurrentDate = $TransDate;
            $BookedDate = $TransDate;

            if (date($CurrentDate) > date($TransEffectiveFrom)) {
                //making the adjustment entry
                $nratedays = DB::select(DB::raw("SELECT DATEDIFF(?, ?) AS Date_Diff"), [$CurrentDate, $TransEffectiveFrom])[0]->Date_Diff;
                // $reverseAmt = bcmul($nratedays,$perDayPremium,4);

                $TbEarnedpremiumDaypremiummaster->policyNumber = $PolicyNo;
                $TbEarnedpremiumDaypremiummaster->policy_id = $PolicyId;
                // $TbEarnedpremiumDaypremiummaster->d_Date = $CurrentDate;
                // $TbEarnedpremiumDaypremiummaster->d_TermStartDate = $TermStartDate;
                // $TbEarnedpremiumDaypremiummaster->d_TermEndDate = $TermEndDate;
                // $TbEarnedpremiumDaypremiummaster->n_TermSequence = $TermSequence;
                $TbEarnedpremiumDaypremiummaster->term_id = $TermId;
                $TbEarnedpremiumDaypremiummaster->action_id = $actionId;
                $TbEarnedpremiumDaypremiummaster->s_TransactionType = $TransactionType; //'-ADJ';
                // $TbEarnedpremiumDaypremiummaster->d_TransactionDate = $TransDate;
                // $TbEarnedpremiumDaypremiummaster->d_BookingDate= $BookedDate;
                $TbEarnedpremiumDaypremiummaster->d_StartDate = $TransEffectiveFrom;
                $TbEarnedpremiumDaypremiummaster->d_EndDate = $TransEffectiveTo;
                $TbEarnedpremiumDaypremiummaster->n_NoOfDays = $nratedays;
                // $TbEarnedpremiumDaypremiummaster->n_NoOfDaysRemain = 0;
                $TbEarnedpremiumDaypremiummaster->n_WrittenPremium = 0;
                // $TbEarnedpremiumDaypremiummaster->annualPremium = 0;
                // $TbEarnedpremiumDaypremiummaster->n_PerDayPremium = $perDayPremium;
                // $TbEarnedpremiumDaypremiummaster->earnedPremium = $reverseAmt;
                // $TbEarnedpremiumDaypremiummaster->unearnedPremium = 0;
                $TbEarnedpremiumDaypremiummaster->s_CalculationMethod = 'Total';
                $TbEarnedpremiumDaypremiummaster->s_IsActive = 'Y';
                $TbEarnedpremiumDaypremiummaster->s_IsPosted = 'N';
                $TbEarnedpremiumDaypremiummaster->created_at = $currentDateTime;
                $TbEarnedpremiumDaypremiummaster->added_by = $userId;
                $TbEarnedpremiumDaypremiummaster->save();

                $Date = explode("-", $TransDate);
                $eachDate = $TransDate;
            } else {
                $Date = explode("-", $TransEffectiveFrom);
                $eachDate = $TransEffectiveFrom;
            }

            $BookedDate = $TransDate;

            $queryPart = '';
            for ($i = 1; $i <= $NoOfDays; $i++) {
                $NoofDaysremain = $NoOfDays - $i;
                // $earnedPremium = $perDayPremium;
                // $unearnedPremium = $perDayPremium*$NoofDaysremain;

                $tb_EarnedpremiumDaypremiummaster = array(
                    'policyNumber' => $PolicyNo ?? "",
                    'policy_id' => $PolicyId ?? "",
                    // 'd_Date'       => $eachDate??"",
                    // 'd_TermStartDate'         => $TermStartDate??"",
                    // 'd_TermEndDate'       => $TermEndDate??"",
                    // 'n_TermSequence'        => $TermSequence??"",
                    'term_id' => $TermId,
                    'action_id' => $actionId ?? "",
                    's_TransactionType' => $TransactionType,
                    // 'd_TransactionDate'         => $TransDate,
                    // 'd_BookingDate' => $BookedDate,
                    'd_StartDate' => $TransEffectiveFrom ?? "",
                    'd_EndDate' => $TransEffectiveTo ?? "",
                    'n_NoOfDays' => $NoOfDays ?? "",
                    // 'n_NoOfDaysRemain' => $NoofDaysremain??"",
                    'n_WrittenPremium' => $writtenPremium,
                    // 'n_AnnualPremium' => $annualPremium??"",
                    'n_PerDayPremium' => $perDayPremium ?? "",
                    // 'n_EarnedPremium' => $earnedPremium??"",
                    // 'n_UnEarnedPremium' => $unearnedPremium,
                    's_CalculationMethod' => 'Total',
                    's_IsActive' => 'Y',
                    's_IsPosted' => 'N'
                );
                TbEarnedpremiumDaypremiummaster::insert($tb_EarnedpremiumDaypremiummaster);

                // $eachDate = $this->GetDateTime_Hp("Y-m-d",'',mktime(0,0,0,$Date[1],$Date[2]+$i,$Date[0]));
            }

        } elseif ($TransactionType == 'CANCEL') {
            $termInfo = PolicyTerm::where('id', $TermId)
                ->select('term_start_date', 'term_end_date')
                ->first();

            $TermStartDate = $termInfo->term_start_date;
            $TermEndDate = $termInfo->term_end_date;
            // $TermSequence = $termInfo->n_TermSequence;

            $TransactionInfo = PolicyAction::where('id', $actionId)
                ->select('premium', 'transaction_type', 'effective_from', 'effective_to', 'transaction_date')
                ->first();

            $annualPremium = $TransactionInfo->premium;//this varibable override below
            $writtenPremium = $TransactionInfo->premium;
            $TransEffectiveFrom = $TransactionInfo->effective_from;
            $TransEffectiveTo = $TransactionInfo->effective_to;
            $TransDate = explode(" ", $TransactionInfo->transaction_date);
            $TransDate = $TransDate[0];
            // $CommTRatePrimary	=  $TransactionInfo->n_CommTRatePrimary;
            // $CancelMethodCode	=  $TransactionInfo->s_CancelMethodCode;
            // $PrevTransaction_FK	=  $TransactionInfo->n_PrevTransaction_FK;
            // $CommTRatePrimary	=  $TransactionInfo->n_CommTRatePrimary;
            $TransEffectiveFromPrevoius = $TransactionInfo->transaction_type;
            $CurrentDate = $TransDate;
            $BookedDate = $TransDate;
            if (date($CurrentDate) > date($TransEffectiveFrom)) {
                //making the adjustment entry
                $nratedays = DB::select(DB::raw("SELECT DATEDIFF(?, ?) AS Date_Diff"), [$CurrentDate, $TransEffectiveFrom])[0]->Date_Diff;

                $previousperEarnedPremium = DB::select(DB::raw("SELECT  SUM(n_DayPremium) as n_EarnedPremium FROM    tb_earnedpremium_daypremiummasters WHERE    policyNumber='$PolicyNo' AND term_id='$TermId' AND d_EndDate >= '" . $TransEffectiveFrom . "' AND d_EndDate <= '" . $CurrentDate . "'"));
                $reverseAmt = $previousperEarnedPremium[0]->n_EarnedPremium * -1;
                $previousperDayPremium = bcdiv($previousperEarnedPremium[0]->n_EarnedPremium, $nratedays, 4);
                //now make entry for endorse for previous transaction
                // $reverseAmt = bcmul($nratedays,$previousperDayPremium,4)*-1;
                $TransactionTypePrevious = $TransactionInfo->transaction_type;

                DB::select(DB::raw("DELETE FROM tb_earnedpremium_daypremiummasters WHERE policyNumber='" . $PolicyNo . "' AND term_id='$TermId' AND d_StartDate > '" . $CurrentDate . "'"));

                $TbEarnedpremiumDaypremiummaster->policyNumber = $PolicyNo;
                $TbEarnedpremiumDaypremiummaster->policy_id = $PolicyId;
                // $TbEarnedpremiumDaypremiummaster->d_Date = $CurrentDate;
                // $TbEarnedpremiumDaypremiummaster->d_TermStartDate = $TermStartDate;
                // $TbEarnedpremiumDaypremiummaster->d_TermEndDate = $TermEndDate;
                // $TbEarnedpremiumDaypremiummaster->n_TermSequence = $TermSequence;
                $TbEarnedpremiumDaypremiummaster->term_id = $TermId;
                $TbEarnedpremiumDaypremiummaster->action_id = $actionId;
                $TbEarnedpremiumDaypremiummaster->s_TransactionType = $TransactionType; //'-ADJ';
                // $TbEarnedpremiumDaypremiummaster->d_TransactionDate = $TransDate;
                // $TbEarnedpremiumDaypremiummaster->d_BookingDate= $BookedDate;
                $TbEarnedpremiumDaypremiummaster->d_StartDate = $TransEffectiveFrom;
                $TbEarnedpremiumDaypremiummaster->d_EndDate = $TransEffectiveTo;
                $TbEarnedpremiumDaypremiummaster->n_NoOfDays = $nratedays;
                // $TbEarnedpremiumDaypremiummaster->n_NoOfDaysRemain = 0;
                $TbEarnedpremiumDaypremiummaster->n_WrittenPremium = $writtenPremium;
                // $TbEarnedpremiumDaypremiummaster->annualPremium = 0;
                $TbEarnedpremiumDaypremiummaster->n_DayPremium = $previousperDayPremium;
                // $TbEarnedpremiumDaypremiummaster->earnedPremium = $reverseAmt;
                // $TbEarnedpremiumDaypremiummaster->unearnedPremium = 0;
                $TbEarnedpremiumDaypremiummaster->s_CalculationMethod = 'Total';
                $TbEarnedpremiumDaypremiummaster->s_IsActive = 'Y';
                $TbEarnedpremiumDaypremiummaster->s_IsPosted = 'N';
                $TbEarnedpremiumDaypremiummaster->created_at = $currentDateTime;
                $TbEarnedpremiumDaypremiummaster->added_by = $userId;
                $TbEarnedpremiumDaypremiummaster->save();
            } elseif (date($CurrentDate) <= date($TransEffectiveFrom)) {
                //remove all the entries after Transaction Date (NOTE THIS SHOULD BE ABOVE ADJ ENTRY BECAUSE IT WILL DELETE ADJ ENTRY TO)
                DB::select(DB::raw("DELETE FROM tb_earnedpremium_daypremiummasters WHERE policyNumber='" . $PolicyNo . "' AND TermMaster_FK='$TermId' AND d_StartDate >= '" . $TransEffectiveFrom . "'"));
            }
        } elseif ($TransactionType == 'ENDORSE') {
            $termInfo = PolicyTerm::where('id', $TermId)->select('term_start_date', 'term_end_date')->first();

            $TermStartDate = $termInfo->term_start_date;
            $TermEndDate = $termInfo->term_end_date;
            // $TermSequence = $termInfo->n_TermSequence;

            $TransactionInfo = PolicyAction::where('id', $actionId)
                ->select('premium', 'transaction_type', 'effective_from', 'effective_to', 'transaction_date')
                ->first();

            $annualPremium = $TransactionInfo->premium;//this varibable override below
            $writtenPremium = $TransactionInfo->premium;
            $TransEffectiveFrom = $TransactionInfo->effective_from;
            $TransEffectiveTo = $TransactionInfo->effective_to;
            $TransDate = explode(" ", $TransactionInfo->transaction_date);
            $TransDate = $TransDate[0];
            // $CommTRatePrimary	=  $TransactionInfo->n_CommTRatePrimary;
            // $CancelMethodCode	=  $TransactionInfo->s_CancelMethodCode;
            // $PrevTransaction_FK	=  $TransactionInfo->n_PrevTransaction_FK;
            $CurrentDate = $TransDate;
            $BookedDate = $TransDate;
            $TransactionTypePrevious = $TransactionInfo->transaction_type;

            if (date($CurrentDate) >= date($TransEffectiveFrom)) {
                //making the adjustment entry

                $nratedays = DB::select(DB::raw("SELECT DATEDIFF(?, ?) AS Date_Diff"), [$CurrentDate, $TransEffectiveFrom])[0]->Date_Diff;
                $previousperDayPremium = 0;
                $previousperEarnedPremium = DB::select(DB::raw("SELECT SUM(n_DayPremium) as n_EarnedPremium FROM tb_earnedpremium_daypremiummasters WHERE policyNumber='$PolicyNo' AND term_id='$TermId' AND d_StartDate >= '" . $TransEffectiveFrom . "' AND d_StartDate <= '" . $CurrentDate . "'"));
                $reverseAmt = $previousperEarnedPremium[0]->n_EarnedPremium * -1;
                //now make entry for endorse for previous transaction
                $reverseAmt = bcmul($nratedays, $previousperDayPremium, 4) * -1;
                $previousperDayPremium = abs(bcdiv($reverseAmt, $nratedays, 4));

                $nratedaysNew = DB::select(DB::raw("SELECT DATEDIFF(?, ?) AS Date_Diff"), [$TransEffectiveTo, $CurrentDate])[0]->Date_Diff;
                $nratedaysNewTerm = DB::select(DB::raw("SELECT DATEDIFF(?, ?) AS Date_Diff"), [$TermEndDate, $TermStartDate])[0]->Date_Diff;
                /*if($nratedaysNewTerm==366)
                {
                    $nratedaysNewTerm = 365;
                }*/
                $newPerDayPremium = bcdiv($annualPremium, $nratedaysNewTerm, 4);

                //remove all the entries after Transaction Date (NOTE THIS SHOULD BE ABOVE ADJ ENTRY BECAUSE IT WILL DELETE ADJ ENTRY TO)
                DB::select(DB::raw("DELETE    FROM    tb_earnedpremium_daypremiummasters WHERE    policyNumber='" . $PolicyNo . "' AND term_id='$TermId' AND  d_StartDate >= '" . $CurrentDate . "' AND s_TransactionType NOT LIKE '%-ADJ%'"));

                $TbEarnedpremiumDaypremiummaster->policyNumber = $PolicyNo;
                $TbEarnedpremiumDaypremiummaster->policy_id = $PolicyId;
                // $TbEarnedpremiumDaypremiummaster->d_Date = $CurrentDate;
                // $TbEarnedpremiumDaypremiummaster->d_TermStartDate = $TermStartDate;
                // $TbEarnedpremiumDaypremiummaster->d_TermEndDate = $TermEndDate;
                // $TbEarnedpremiumDaypremiummaster->n_TermSequence = $TermSequence;
                $TbEarnedpremiumDaypremiummaster->term_id = $TermId;
                $TbEarnedpremiumDaypremiummaster->action_id = $actionId;
                $TbEarnedpremiumDaypremiummaster->s_TransactionType = $TransactionTypePrevious; //'-ADJ';
                // $TbEarnedpremiumDaypremiummaster->d_TransactionDate = $TransDate;
                // $TbEarnedpremiumDaypremiummaster->d_BookingDate= $BookedDate;
                $TbEarnedpremiumDaypremiummaster->d_EndDate = $TransEffectiveFrom;
                $TbEarnedpremiumDaypremiummaster->d_EndDate = $TransEffectiveTo;
                // $TbEarnedpremiumDaypremiummaster-> = $nratedays;
                $TbEarnedpremiumDaypremiummaster->n_NoOfDays = $nratedays;
                // $TbEarnedpremiumDaypremiummaster->n_NoOfDaysRemain = 0;
                $TbEarnedpremiumDaypremiummaster->n_WrittenPremium = 0;
                // $TbEarnedpremiumDaypremiummaster->annualPremium = 0;
                $TbEarnedpremiumDaypremiummaster->n_DayPremium = $previousperDayPremium;
                // $TbEarnedpremiumDaypremiummaster->earnedPremium = $reverseAmt;
                // $TbEarnedpremiumDaypremiummaster->unearnedPremium = 0;
                $TbEarnedpremiumDaypremiummaster->s_CalculationMethod = 'Total';
                $TbEarnedpremiumDaypremiummaster->s_IsActive = 'Y';
                $TbEarnedpremiumDaypremiummaster->s_IsPosted = 'N';
                $TbEarnedpremiumDaypremiummaster->created_at = $currentDateTime;
                $TbEarnedpremiumDaypremiummaster->added_by = $userId;
                $TbEarnedpremiumDaypremiummaster->save();


                //now make entry for endorse for current transaction
                $reverseAmtNew = bcmul($nratedays, $newPerDayPremium, 4);

                $Tb_Earned_premiumDaypremiummaster = new TbEarnedpremiumDaypremiummaster();
                $Tb_Earned_premiumDaypremiummaster->policyNumber = $PolicyNo;
                $Tb_Earned_premiumDaypremiummaster->policy_id = $PolicyId;
                // $Tb_Earned_premiumDaypremiummaster->d_Date = $CurrentDate;
                // $Tb_Earned_premiumDaypremiummaster->d_TermStartDate = $TermStartDate;
                // $Tb_Earned_premiumDaypremiummaster->d_TermEndDate = $TermEndDate;
                // $Tb_Earned_premiumDaypremiummaster->n_TermSequence = $TermSequence;
                $Tb_Earned_premiumDaypremiummaster->term_id = $TermId;
                $Tb_Earned_premiumDaypremiummaster->action_id = $actionId;
                $Tb_Earned_premiumDaypremiummaster->s_TransactionType = $TransactionType;
                // $Tb_Earned_premiumDaypremiummaster-> = '-ADJ';
                // $Tb_Earned_premiumDaypremiummaster->d_TransactionDate = $TransDate;
                // $Tb_Earned_premiumDaypremiummaster->d_BookingDate= $BookedDate;
                $Tb_Earned_premiumDaypremiummaster->d_StartDate = $TransEffectiveFrom;
                $Tb_Earned_premiumDaypremiummaster->d_EndDate = $TransEffectiveTo;
                // $Tb_Earned_premiumDaypremiummaster-> = $nratedays;
                $Tb_Earned_premiumDaypremiummaster->n_NoOfDays = $NoOfDays ?? "";
                // $Tb_Earned_premiumDaypremiummaster->n_NoOfDaysRemain = 0;
                $Tb_Earned_premiumDaypremiummaster->n_WrittenPremium = 0;
                // $Tb_Earned_premiumDaypremiummaster->annualPremium = 0;
                $Tb_Earned_premiumDaypremiummaster->n_DayPremium = $previousperDayPremium;
                // $Tb_Earned_premiumDaypremiummaster->earnedPremium = $reverseAmtNew;
                // $Tb_Earned_premiumDaypremiummaster->unearnedPremium = 0;
                $Tb_Earned_premiumDaypremiummaster->s_CalculationMethod = 'Total';
                $Tb_Earned_premiumDaypremiummaster->s_IsActive = 'Y';
                $Tb_Earned_premiumDaypremiummaster->s_IsPosted = 'N';
                $Tb_Earned_premiumDaypremiummaster->created_at = $currentDateTime;
                $Tb_Earned_premiumDaypremiummaster->added_by = $userId;
                $Tb_Earned_premiumDaypremiummaster->save();

                $eachDate = $CurrentDate;
                $Date = explode("-", $CurrentDate);
                $queryPart = '';
                for ($i = 1; $i <= $nratedaysNew; $i++) {
                    $NoofDaysremain = $nratedaysNew - $i;
                    $earnedPremium = $newPerDayPremium;
                    $unearnedPremium = $newPerDayPremium * $NoofDaysremain;

                    $tb_EarnedpremiumDaypremiummaster = array(
                        'policyNumber' => $PolicyNo ?? "",
                        'policy_id' => $PolicyId ?? "",
                        // 'd_Date'       => $eachDate??"",
                        // 'd_TermStartDate'         => $TermStartDate??"",
                        // 'd_TermEndDate'       => $TermEndDate??"",
                        // 'n_TermSequence'        => $TermSequence??"",
                        'term_id' => $TermId,
                        'action_id' => $actionId ?? "",
                        's_TransactionType' => $TransactionType,
                        // 'd_TransactionDate'         => $TransDate,
                        // 'd_BookingDate' => $BookedDate,
                        'd_StartDate' => $TransEffectiveFrom ?? "",
                        'd_EndDate' => $TransEffectiveTo ?? "",
                        'n_NoOfDays' => $nratedaysNewTerm ?? "",
                        // 'n_NoOfDaysRemain' => $NoofDaysremain??"",
                        'n_WrittenPremium' => $writtenPremium,
                        // 'n_AnnualPremium' => $annualPremium??"",
                        'n_DayPremium' => $perDayPremium ?? "",
                        // 'n_EarnedPremium' => $earnedPremium??"",
                        // 'n_UnEarnedPremium' => $unearnedPremium,
                        's_CalculationMethod' => 'Total',
                        's_IsActive' => 'Y',
                        's_IsPosted' => 'N'
                    );
                    TbEarnedpremiumDaypremiummaster::insert($tb_EarnedpremiumDaypremiummaster);
                    // $eachDate = $this->GetDateTime_Hp("Y-m-d",'',mktime(0,0,0,$Date[1],$Date[2]+$i,$Date[0]));
                }

            } elseif ($CurrentDate < $TransEffectiveFrom) {
                $nratedaysNew = DB::select(DB::raw("SELECT DATEDIFF(?, ?) AS Date_Diff"), [$TermEndDate, $TermStartDate])[0]->Date_Diff;

                $nratedaysNewTerm = DB::select(DB::raw("SELECT DATEDIFF(?, ?) AS Date_Diff"), [$TransEffectiveTo, $TransEffectiveFrom])[0]->Date_Diff;
                //this is midterm endorsement
                //if($nratedaysNewTerm<365)
                //{
                $PrevTransactionMax_FK = DB::select(DB::raw("SELECT COALESCE(MAX(id),0) as n_potransaction_PK
                                                                FROM  policy_actions
                                                                WHERE policy_id= '$PolicyId'
                                                                AND   term_id= '$TermId'
                                                                AND   status = 'ISSUED'
                                                                AND   id != '$actionId'
                                                                AND   transaction_type NOT IN('CANCEL','REINSTATE')"));
                $PrevTransactionMax_FK = $PrevTransactionMax_FK[0]->n_potransaction_PK;
                $previousperDayPremium = TbEarnedpremiumDaypremiummaster::where('action_id', $PrevTransactionMax_FK)->first()?->n_DayPremium;
                //  DB::select(DB::raw("SELECT  n_DayPremium FROM    tb_earnedpremium_daypremiummasters WHERE    action_id='$PrevTransactionMax_FK' LIMIT 1"));
                if ($previousperDayPremium == '') {
                    $previousperDayPremium = 0;
                }

                $newPerDayPremium = bcdiv($writtenPremium, $nratedaysNewTerm, 4);
                $newPerDayPremium = $newPerDayPremium + $previousperDayPremium;
                DB::select(DB::raw("DELETE    FROM    tb_earnedpremium_daypremiummasters WHERE    policyNumber='" . $PolicyNo . "' AND term_id='$TermId' AND d_StartDate >= '" . $TransEffectiveFrom . "'"));
                /* }
                 else
                 {
                     $newPerDayPremium = bcdiv($annualPremium,$nratedaysNew,4);
                 }*/



                $eachDate = $TransEffectiveFrom;
                $Date = explode("-", $TransEffectiveFrom);

                $queryPart = '';
                for ($i = 1; $i <= $nratedaysNewTerm; $i++) {
                    $NoofDaysremain = $nratedaysNewTerm - $i;
                    $earnedPremium = $newPerDayPremium;
                    $unearnedPremium = $newPerDayPremium * $NoofDaysremain;
                    $tb_EarnedpremiumDaypremiummaster = array(
                        'policyNumber' => $PolicyNo ?? "",
                        'policy_id' => $PolicyId ?? "",
                        // 'd_Date'       => $eachDate??"",
                        // 'd_TermStartDate'         => $TermStartDate??"",
                        // 'd_TermEndDate'       => $TermEndDate??"",
                        // 'n_TermSequence'        => $TermSequence??"",
                        'term_id' => $TermId,
                        'action_id' => $actionId ?? "",
                        's_TransactionType' => $TransactionType,
                        // 'd_TransactionDate'         => $TransDate,
                        // 'd_BookingDate' => $BookedDate,
                        'd_StartDate' => $TransEffectiveFrom ?? "",
                        'd_EndDate' => $TransEffectiveTo ?? "",
                        'n_NoOfDays' => $nratedaysNew ?? "",
                        // 'n_NoOfDaysRemain' => $NoofDaysremain??"",
                        'n_WrittenPremium' => $writtenPremium,
                        // 'n_AnnualPremium' => $annualPremium??"",
                        'n_DayPremium' => $newPerDayPremium ?? "",
                        // 'n_EarnedPremium' => $earnedPremium??"",
                        // 'n_UnEarnedPremium' => $unearnedPremium,
                        's_CalculationMethod' => 'Total',
                        's_IsActive' => 'Y',
                        's_IsPosted' => 'N'
                    );
                    TbEarnedpremiumDaypremiummaster::insert($tb_EarnedpremiumDaypremiummaster);
                    // $eachDate = $this->GetDateTime_Hp("Y-m-d",'',mktime(0,0,0,$Date[1],$Date[2]+$i,$Date[0]));
                }
            }



            //Transaction Date is in future
            /*if($CurrentDate < $TransEffectiveFrom)
            {

                echo "Transaction Date is in past";
            }
            elseif($CurrentDate > $TransEffectiveFrom)
            {

                echo "Transaction Date is in future";
            }
            else
            {
                echo "Equals";
            }*/


        } elseif ($TransactionType == 'REINSTATE' || $TransactionType == 'REISSUE') {
            $termInfo = PolicyTerm::where('id', $TermId)
                ->select('term_start_date', 'term_end_date')
                ->first();

            $TermStartDate = $termInfo->term_start_date;
            $TermEndDate = $termInfo->term_end_date;
            // $TermSequence = $termInfo->n_TermSequence;

            $TransactionInfo = PolicyAction::where('id', $actionId)
                ->select('premium', 'transaction_type', 'effective_from', 'effective_to', 'transaction_date')
                ->first();

            $annualPremium = $TransactionInfo->premium;//this varibable override below
            $writtenPremium = $TransactionInfo->premium;
            $TransEffectiveFrom = $TransactionInfo->effective_from;
            $TransEffectiveTo = $TransactionInfo->effective_to;
            $TransDate = explode(" ", $TransactionInfo->transaction_date);
            $TransDate = $TransDate[0];
            // $CommTRatePrimary	=  $TransactionInfo->n_CommTRatePrimary;
            // $CancelMethodCode	=  $TransactionInfo->s_CancelMethodCode;
            // $PrevTransaction_FK	=  $TransactionInfo->n_PrevTransaction_FK;

            $TransEffectiveFromPrevoius = $TransEffectiveFrom;
            $TransEffectiveToPrevoius = $TransEffectiveTo;


            //check first Transaction Date is previous date or future date from current date
            //$CurrentDate =  $this->GetDateTime_Hp("Y-m-d");
            $CurrentDate = $TransDate;
            $BookedDate = $TransDate;
            if ($CurrentDate > $TransEffectiveFrom) {
                // we have to make the adjustment entry for reinstate working policy HO320120000044

                //making the adjustment entry
                $nratedays = DB::select(DB::raw('SELECT DATEDIFF("' . $CurrentDate . '", "' . $TransEffectiveFromPrevoius . '") as Date_Diff LIMIT 1'));
                //this is to get the previous transaction per day premium
                /*$PrevTransactionMax_FK = $this->TbPotransaction->query("SELECT    COALESCE(MAX(n_potransaction_PK),0) as n_potransaction_PK
                                                                                  FROM      tb_potransactions
                                                                                  WHERE     n_PolicyMaster_FK= '$PolicyId'
                                                                                  AND       term_id= '$TermId'
                                                                                  AND       s_TransactionCycleCode = 'ISSUED'
                                                                                  AND       s_PRTranTypeCode NOT IN('CANCEL','REINSTATE')");

                $PrevTransactionMax_FK = $PrevTransactionMax_FK[0][0]['n_potransaction_PK'];

                $previousperDayPremium = $this->TbPolicy->query("SELECT  n_DayPremium FROM    tb_earnedpremium_daypremiummasters WHERE    action_id='$PrevTransactionMax_FK' LIMIT 1");
                $previousperDayPremium = $previousperDayPremium[0]['tb_earnedpremium_daypremiummasters']['n_DayPremium'];
                */
                $previousperDayPremium = bcdiv($annualPremium, 365, 4);

                // $reverseAmt = bcmul($nratedays,$previousperDayPremium,4);

                $TbEarnedpremiumDaypremiummaster->policyNumber = $PolicyNo;
                $TbEarnedpremiumDaypremiummaster->policy_id = $PolicyId;

                // $TbEarnedpremiumDaypremiummaster->d_Date = $CurrentDate;
                // $TbEarnedpremiumDaypremiummaster->d_TermStartDate = $TermStartDate;
                // $TbEarnedpremiumDaypremiummaster->d_TermEndDate = $TermEndDate;
                // $TbEarnedpremiumDaypremiummaster->n_TermSequence = $TermSequence;
                $TbEarnedpremiumDaypremiummaster->term_id = $TermId;
                $TbEarnedpremiumDaypremiummaster->action_id = $actionId;
                $TbEarnedpremiumDaypremiummaster->s_TransactionType = $TransactionType; //'-ADJ';
                // $TbEarnedpremiumDaypremiummaster->d_TransactionDate = $TransDate;
                // $TbEarnedpremiumDaypremiummaster->d_BookingDate= $BookedDate;
                $TbEarnedpremiumDaypremiummaster->d_StartDate = $TransEffectiveFrom;
                $TbEarnedpremiumDaypremiummaster->d_EndDate = $TransEffectiveTo;
                // $TbEarnedpremiumDaypremiummaster-> = $nratedays;
                $TbEarnedpremiumDaypremiummaster->n_NoOfDays = $nratedays;
                // $TbEarnedpremiumDaypremiummaster->n_NoOfDaysRemain = 0;
                $TbEarnedpremiumDaypremiummaster->n_WrittenPremium = 0;
                // $TbEarnedpremiumDaypremiummaster->annualPremium = 0;
                $TbEarnedpremiumDaypremiummaster->n_DayPremium = $previousperDayPremium;
                // $TbEarnedpremiumDaypremiummaster->earnedPremium = $reverseAmtNew;
                // $TbEarnedpremiumDaypremiummaster->unearnedPremium = 0;
                $TbEarnedpremiumDaypremiummaster->s_CalculationMethod = 'Total';
                $TbEarnedpremiumDaypremiummaster->s_IsActive = 'Y';
                $TbEarnedpremiumDaypremiummaster->s_IsPosted = 'N';
                $TbEarnedpremiumDaypremiummaster->created_at = $currentDateTime;
                $TbEarnedpremiumDaypremiummaster->added_by = $userId;
                $TbEarnedpremiumDaypremiummaster->save();

                $nratedays = DB::select(DB::raw("SELECT DATEDIFF(?, ?) AS Date_Diff"), [$TermEndDate, $TermStartDate])[0]->Date_Diff;
                $nratedaysNewForPremium = $nratedays;
                /*if($nratedaysNewForPremium==366)
                {
                    $nratedaysNewForPremium=365;
                }*/

                $nratedays = DB::select(DB::raw("SELECT DATEDIFF(?, ?) AS Date_Diff"), [$TransEffectiveTo, $CurrentDate])[0]->Date_Diff;
                $nratedaysNew = $nratedays;

                $newPerDayPremium = bcdiv($annualPremium, $nratedaysNewForPremium, 4);

                $eachDate = $CurrentDate;
                $Date = explode("-", $CurrentDate);
                $queryPart = '';
                for ($i = 1; $i <= $nratedays; $i++) {
                    $NoofDaysremain = $nratedays - $i;
                    $earnedPremium = $newPerDayPremium;
                    $unearnedPremium = $newPerDayPremium * $NoofDaysremain;

                    $tb_EarnedpremiumDaypremiummaster = array(
                        'policyNumber' => $PolicyNo ?? "",
                        'policy_id' => $PolicyId ?? "",
                        // 'd_Date'       => $eachDate??"",
                        // 'd_TermStartDate'         => $TermStartDate??"",
                        // 'd_TermEndDate'       => $TermEndDate??"",
                        // 'n_TermSequence'        => $TermSequence??"",
                        'term_id' => $TermId,
                        'action_id' => $actionId ?? "",
                        's_TransactionType' => $TransactionType,
                        // 'd_TransactionDate'         => $TransDate,
                        // 'd_BookingDate' => $BookedDate,
                        'd_StartDate' => $TransEffectiveFrom ?? "",
                        'd_EndDate' => $TransEffectiveTo ?? "",
                        'n_NoOfDays' => $nratedaysNew ?? "",
                        // 'n_NoOfDaysRemain' => $NoofDaysremain??"",
                        'n_WrittenPremium' => $writtenPremium,
                        // 'n_AnnualPremium' => $annualPremium??"",
                        // 'n_DayPremium' => $newPerDayPremium??"",
                        // 'n_EarnedPremium' => $earnedPremium??"",
                        // 'n_UnEarnedPremium' => $unearnedPremium,
                        's_CalculationMethod' => 'Total',
                        's_IsActive' => 'Y',
                        's_IsPosted' => 'N'
                    );
                    TbEarnedpremiumDaypremiummaster::insert($tb_EarnedpremiumDaypremiummaster);
                    // $eachDate = $this->GetDateTime_Hp("Y-m-d",'',mktime(0,0,0,$Date[1],$Date[2]+$i,$Date[0]));
                }
            } elseif ($CurrentDate <= $TransEffectiveFrom) {
                //this is for loop
                $nratedaysNewLoop = DB::select(DB::raw("SELECT DATEDIFF(?, ?) AS Date_Diff"), [$TermEndDate, $TransEffectiveFrom])[0]->Date_Diff;

                $nratedaysNew = DB::select(DB::raw("SELECT DATEDIFF(?, ?) AS Date_Diff"), [$TermEndDate, $TermStartDate])[0]->Date_Diff;
                $newPerDayPremium = bcdiv($annualPremium, $nratedaysNew, 4);
                $eachDate = $TransEffectiveFrom;
                $Date = explode("-", $TransEffectiveFrom);
                // this is future date so just make entry for reinstate
                $queryPart = '';
                for ($i = 1; $i <= $nratedaysNewLoop; $i++) {
                    $NoofDaysremain = $nratedaysNewLoop - $i;
                    // $earnedPremium = $newPerDayPremium;
                    // $unearnedPremium = $newPerDayPremium*$NoofDaysremain;

                    $tb_EarnedpremiumDaypremiummaster = array(
                        'policyNumber' => $PolicyNo ?? "",
                        'policy_id' => $PolicyId ?? "",
                        // 'd_Date'       => $eachDate??"",
                        // 'd_TermStartDate'         => $TermStartDate??"",
                        // 'd_TermEndDate'       => $TermEndDate??"",
                        // 'n_TermSequence'        => $TermSequence??"",
                        'term_id' => $TermId,
                        'action_id' => $actionId ?? "",
                        's_TransactionType' => $TransactionType,
                        // 'd_TransactionDate'         => $TransDate,
                        // 'd_BookingDate' => $BookedDate,
                        'd_StartDate' => $TransEffectiveFrom ?? "",
                        'd_EndDate' => $TransEffectiveTo ?? "",
                        'n_NoOfDays' => $nratedaysNewLoop ?? "",
                        // 'n_NoOfDaysRemain' => $NoofDaysremain??"",
                        'n_WrittenPremium' => $writtenPremium,
                        // 'n_AnnualPremium' => $annualPremium??"",
                        // 'n_DayPremium' => $newPerDayPremium??"",
                        // 'n_EarnedPremium' => $earnedPremium??"",
                        // 'n_UnEarnedPremium' => $unearnedPremium,
                        's_CalculationMethod' => 'Total',
                        's_IsActive' => 'Y',
                        's_IsPosted' => 'N'
                    );
                    TbEarnedpremiumDaypremiummaster::insert($tb_EarnedpremiumDaypremiummaster);

                    $eachDate = $this->GetDateTime_Hp("Y-m-d", '', mktime(0, 0, 0, $Date[1], $Date[2] + $i, $Date[0]));
                }
            }

        }
        return true;
    }

}