<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Policy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SpecialistCoverageController extends Controller
{
    /**
     * Map URL type slugs → table name + allowed columns.
     */
    /**
     * Specialist types where a policy_coverage_id legitimately maps to
     * MULTIPLE rows (e.g. marine cargo declaration schedules — see
     * PolicyAction::replicateSpecialistCoveragesIfMissing(), "MULTIPLE
     * declaration rows per coverage"). store() must NOT dedupe/upsert by
     * policy_coverage_id for these — every other specialist type is 1:1.
     */
    private const MULTI_ROW_TYPES = ['marine-cargo-open', 'marine-cargo-once-off'];

    private const TYPE_MAP = [
        'ear' => [
            'table'  => 'ear_coverages',
            'label'  => 'EAR Coverage',
            'fields' => [
                'policy_coverage_id','coverage_id','action_id','term_id',
                'name_of_insured','site_of_erection','project_name','policy_period_months',
                'authorization_management','is_renewable','is_project_specific','maintenance_period_months',
                'reinsurance_fire_treaty','period_from','period_to','weeks_of_testing',
                'section1_total_sum_insured','section1_total_premium',
                'risk_earthquake_covered','risk_earthquake_limit_indemnity','risk_earthquake_deductible','risk_earthquake_premium',
                'risk_storm_covered','risk_storm_limit_indemnity','risk_storm_deductible','risk_storm_premium',
                'section3_total_limit','section3_total_premium',
                'total_premium','executed_at','execution_date','signature','additional_notes',
                'endorsement_1','endorsement_2','endorsement_3','endorsement_4',
                'section1_items','section3_items','policy_wording_path',
            ],
            'json'   => ['section1_items','section3_items'],
        ],
        'car' => [
            'table'  => 'car_coverages',
            'label'  => 'CAR Coverage',
            'fields' => [
                'policy_coverage_id','coverage_id','action_id','term_id',
                'branch','policy_no','currency','declaration_no','insured_name','insured_street','insured_postal_code',
                'title_of_contract','risk_street','risk_postal_code','city_town_village','project_name',
                'policy_inception_date','policy_expiry_date','today_date','new_altered',
                'policy_period_months','authorization_management','is_renewable','is_project_specific',
                'maintenance_period_months','reinsurance_fire_treaty',
                'section1_contract_works_sum_insured','section1_contract_works_deductible','section1_contract_works_premium',
                'section1_contract_price','section1_materials_supplied',
                'section1_plant_equipment_sum_insured','section1_plant_equipment_deductible','section1_plant_equipment_premium',
                'section1_machinery_sum_insured','section1_machinery_deductible','section1_machinery_premium',
                'section1_clearance_debris_sum_insured','section1_clearance_debris_deductible','section1_clearance_debris_premium',
                'section1_total_sum_insured','section1_total_premium',
                'section1_risk_earthquake','section1_earthquake_limit_indemnity','section1_earthquake_deductible','section1_earthquake_premium',
                'section1_risk_storm','section1_storm_limit_indemnity','section1_storm_deductible','section1_storm_premium',
                'section2_bodily_injury_one_person','section2_bodily_injury_one_person_deductible','section2_bodily_injury_one_person_premium',
                'section2_bodily_injury_total','section2_property_damage','section2_property_damage_deductible','section2_property_damage_premium',
                'section2_total_limit','section2_total_premium',
                'section3_annual_sum_insured','section3_sum_insured_max_indemnity','section3_premium',
                'section3_period_insurance_from','section3_period_insurance_to','section3_maximum_indemnity',
                'section3_time_excess','section3_insured_interest','section3_rate',
                'section3_limit_indemnity_each_loss','section3_limit_indemnity_one_accident',
                'section3_scheduled_date_completion','section3_scheduled_date_commencement',
                'section3_gross_profit_annual_sum_insured','section3_gross_profit_rate','section3_gross_profit_premium',
                'section3_gross_profit_insured_interest','section3_increased_cost_insured_interest',
                'section3_increased_cost_sum_insured','section3_increased_cost_rate','section3_increased_cost_premium',
                'section3_total_annual_sum','section3_total_premium',
                'section56_item_no','section56_description','section56_loss_minimization',
                'endorsement_1','endorsement_2','endorsement_3','endorsement_4',
                'additional_notes','executed_at','execution_date','signature',
                'section1_items','section2_items','section3_items','section3_contract_works','plant_list_items',
                'policy_wording_path',
            ],
            'json'   => ['section1_items','section2_items','section3_items','section3_contract_works','plant_list_items'],
        ],
        'par' => [
            'table'  => 'par_coverages',
            'label'  => 'PAR Coverage',
            'fields' => [
                'policy_coverage_id','coverage_id','action_id','term_id',
                'project_name','policy_period_months','authorization_management','is_renewable','is_project_specific',
                'maintenance_period_months','reinsurance_fire_treaty',
                'allow_addition','total_sum_insured','total_premium',
                'section2_total_limit','section2_total_premium',
                'executed_at','execution_date','signature','additional_notes',
                'insured_items','section2_items','policy_wording_path',
            ],
            'json'   => ['insured_items','section2_items'],
        ],
        'travel-insurance' => [
            'table'  => 'travel_coverages',
            'label'  => 'Travel Insurance',
            'fields' => [
                'policy_coverage_id','coverage_id','action_id','term_id',
                'policyholder','passport','phone_num','policy_number','number_passengers',
                'effective_from','expiry','policy_period','policy_period_months','is_renewable',
                'policy_amount','vat','total','approved_by','approved_at',
                'destination_area','country_of_origin','product','code',
                'insurance_company','company_location',
                'benefits','custom_benefits','policy_wording_path',
            ],
            'json'   => ['benefits','custom_benefits'],
        ],
        'medical-malpractice' => [
            'table'  => 'medical_malpractice_coverages',
            'label'  => 'Medical Malpractice',
            'fields' => [
                // Identity columns — without these the saved row has
                // policy_coverage_id=NULL, which makes getMedicalTotal()'s
                // "where policy_coverage_id != null" filter skip it and the
                // V2 quote sheet shows P 0.00 for MM. EAR/PAR/CAR all carry
                // the same set; MM was missing it.
                'policy_coverage_id','coverage_id','action_id','term_id',
                'policy_number','type_of_document','insured','insured_vat_number','company_registration_number',
                'insured_business_description','insured_postal_address','intermediary',
                'period_of_insurance','anniversary_renewal_date','retroactive_date',
                'policy_inception_date','policy_expiry_date','today_date',
                'type_of_contract','payment_frequency','annual_premium',
                'limit_of_indemnity','basis_of_limit','cumulative_limit',
                'automatic_reinstatement','additional_reporting_period',
                'new_altered','is_renewable','is_project_specific',
                'standard_policy_conditions','policy_wording','notes',
                'extensions','specific_deductibles','risk_details','policy_wording_path',
            ],
            'json'   => ['extensions','specific_deductibles','risk_details'],
        ],
        'professional-indemnity' => [
            'table'  => 'professional_indemnity_coverages',
            'label'  => 'Professional Indemnity',
            'fields' => [
                // Identity columns — getProfessionalIndemnityTotal filters
                // `where policy_coverage_id != null`, so without this the row
                // never contributes to the Rate / V2 Quote / Policy Doc totals.
                'policy_coverage_id','coverage_id','action_id','term_id',
                'insured','profession_business','basis_of_cover','period_of_insurance','retroactive_date',
                'policy_inception_date','policy_expiry_date','today_date',
                'new_altered','is_renewable','is_project_specific',
                'free_text_area','notes',
                // Premium — read by calculatePremium API ($specialistTables)
                // and getProfessionalIndemnityTotal. Was missing here, so the
                // Rate button summed P 0 for PI.
                'premium',
                'approved_by','approved_at',
                'policy_wording','policy_wording_path','policy_wording_filename',
                'descriptions','insured_persons','extensions','additional_extensions','excesses',
            ],
            'json'   => ['descriptions','insured_persons','extensions','additional_extensions','excesses'],
        ],
        'machinery-breakdown' => [
            'table'  => 'machinery_breakdown_coverages',
            'label'  => 'Machinery Breakdown',
            'fields' => [
                // Identity columns — without these the saved row has
                // policy_coverage_id / coverage_id / action_id / term_id = NULL,
                // which makes getMachineryBreakdownTotal() / getPremium() and the
                // engineering quote sheet skip the row, so MB shows P 0.00.
                // Same pattern as MM / PI / Marine entries above.
                'policy_coverage_id','coverage_id','action_id','term_id',
                'policy_number','company_name','company_address','inception_date','expiry_date','today_date',
                'limit_of_liability','sum_insured','currency','premium',
                'period_of_insurance','new_altered','is_renewable','is_project_specific',
                'approved_by','approved_at',
                'previous_insurer_name','previous_policy_type','previous_policyholder',
                'previous_policy_number','previous_policy_period',
                'backdated_continuity_date','jurisdictional_cover',
                'section1_items','section2_items','section3_items',
                'machinery_listing','extra_cover_section1','extra_cover_section2',
                'extra_cover_section3','extra_cover_all_sections','excess_details',
                'insuring_clauses','extensions','coverage_extensions',
                'endorsements','notes','policy_wording_path',
            ],
            'json'   => [
                'section1_items','section2_items','section3_items',
                'machinery_listing','extra_cover_section1','extra_cover_section2',
                'extra_cover_section3','extra_cover_all_sections','excess_details',
                'insuring_clauses','extensions','coverage_extensions',
            ],
        ],
        'marine-cargo-once-off' => [
            'table'  => 'marine_cargo_once_off_coverages',
            'label'  => 'Marine Cargo Once-Off',
            'fields' => [
                // Identity columns — without these the saved row has
                // policy_coverage_id=NULL, which makes the V2 quote sheet's
                // marine_cargo_once_off_pdf partial (whereNotNull filter) and
                // getMarineCargoOnceOffTotal both skip the row, so the schedule
                // renders blank and the Index of Sections shows P 0.00.
                'policy_coverage_id','coverage_id','action_id','term_id',
                'policy_number','assured_name','assured_address','agent_broker_code_no',
                'conveyance','voyage_from','voyage_to',
                'commodities_covered','nature_of_packing','terms_of_cover',
                'annual_estimated_turnover','location_limit','premium_rate','sum_insured','premium',
                'today_date','currency',
                'clauses','survey_claim_settlement','claim_payable_at','claim_payable_by',
                'place','examined_by','signing_date','basis_of_valuation',
                'per_conveyance_limits',
                'per_conveyance_rail','per_conveyance_road','per_conveyance_air',
                'per_conveyance_post','per_conveyance_vessel',
                'deductible','notice_of_cancellation','refund','notes',
                'policy_period_from','policy_period_to',
                'approved_by','approved_at','policy_wording_path',
            ],
            'json'   => ['clauses','per_conveyance_limits'],
        ],
        'marine-cargo-open' => [
            'table'  => 'marine_cargo_open_coverages',
            'label'  => 'Marine Cargo Open',
            'fields' => [
                // Identity columns — same reason as marine-cargo-once-off above.
                'policy_coverage_id','coverage_id','action_id','term_id',
                'assured_name','assured_address','open_policy_no','agent_broker_code',
                'conveyance','voyage_from','voyage_to',
                'commodities_covered','nature_of_packing','terms_of_cover',
                'annual_estimated_turnover','location_limit','premium_rate','sum_insured','premium',
                'clauses','survey_claim_settlement','claim_payable_at','claim_payable_by',
                'place','signing_date','examined_by','declaration','basis_of_valuation',
                'per_conveyance_rail','per_conveyance_road','per_conveyance_air',
                'per_conveyance_post','per_conveyance_vessel',
                'policy_period_from','policy_period_to',
                'deductible','notice_of_cancellation','refund','over_declaration','notes',
                'today_date',
                'approved_by','approved_at','policy_wording_path',
            ],
            'json'   => ['clauses'],
        ],
        'marine-directors-officers' => [
            'table'  => 'marine_directors_officers_coverages',
            'label'  => 'Marine Directors & Officers',
            'fields' => [
                // Identity columns — same reason as MB/MM/PI/Marine Cargo
                // entries above. Without these the saved row has
                // policy_coverage_id / coverage_id / action_id / term_id NULL,
                // which makes downstream totals and the V2 quote sheet skip
                // the row. Also covers DIRECTORSOFFICERSLIABILITY since the
                // frontend routes DOL to this same schedule + table.
                'policy_coverage_id','coverage_id','action_id','term_id',
                'policy_number','company_name','company_address','inception_date','expiry_date','today_date',
                'new_altered','period_of_insurance','is_renewable','is_project_specific',
                'limit_of_liability','currency','premium',
                'insuring_clauses','extensions','coverage_extensions',
                'previous_insurer_name','previous_policy_type','previous_policyholder',
                'previous_policy_number','previous_policy_period',
                'backdated_continuity_date','jurisdictional_cover',
                'approved_by','approved_at','notes','policy_wording_path',
            ],
            'json'   => ['insuring_clauses','extensions','coverage_extensions'],
        ],
        'medical-evacuation' => [
            'table'  => 'medical_evacuation_coverages',
            'label'  => 'Medical Evacuation',
            'fields' => [
                // Identity columns — without these the saved row has
                // policy_coverage_id / coverage_id / action_id / term_id = NULL,
                // which makes getMedicalEvacuationTotal()/getPremium() and the
                // V2 quote sheet skip the row, so MEDEVAC shows P 0.00. Same
                // pattern as MM/PI/MB/Marine entries above.
                'policy_coverage_id','coverage_id','action_id','term_id',
                'policy_number','company_name','company_address','inception_date','expiry_date','today_date',
                'type_of_cover','original_insured_scheme','territorial_limit','broker',
                'sum_insured','aggregate_limit','currency','premium','is_renewable',
                'description_items','extension_items','notes',
                'approved_by','approved_at',
                'policy_wording','policy_wording_path','policy_wording_filename',
            ],
            'json'   => ['description_items','extension_items'],
        ],
        'commercial-crime' => [
            'table'  => 'commercial_crime_coverages',
            'label'  => 'Commercial Crime',
            'fields' => [
                // Identity columns — without these the saved row has
                // policy_coverage_id / coverage_id / action_id / term_id = NULL,
                // which makes getCommercialCrimeTotal()/getPremium() and the
                // V2 quote sheet skip the row, so COMMERCIALCRIME shows P 0.00.
                // Same pattern as MM/PI/MB/Marine/Medical Evacuation entries above.
                'policy_coverage_id','coverage_id','action_id','term_id',
                'policy_number','company_name','company_address','inception_date','expiry_date','today_date',
                'insured_group','coverage_basis','cover_type','industry_sector','retroactive_date',
                'total_limit','excess','annual_aggregate_limit','broker','currency','premium','is_renewable',
                'insuring_clauses','excess_layers','endorsements_extensions','notes',
                'approved_by','approved_at',
                'policy_wording','policy_wording_path','policy_wording_filename',
            ],
            'json'   => ['insuring_clauses','excess_layers','endorsements_extensions'],
        ],
        'environmental-liability' => [
            'table'  => 'environmental_liability_coverages',
            'label'  => 'Environmental Liability',
            'fields' => [
                // Identity columns — without these the saved row has
                // policy_coverage_id / coverage_id / action_id / term_id = NULL,
                // which makes getEnvironmentalLiabilityTotal()/getPremium() and
                // the V2 quote sheet skip the row, so ENVIRONMENTALLIABILITY
                // shows P 0.00. Same pattern as MM/PI/MB/Marine/Medical
                // Evacuation/Commercial Crime entries above.
                'policy_coverage_id','coverage_id','action_id','term_id',
                'policy_number','company_name','company_address','nature_of_business',
                'contact_person','telephone','email',
                'inception_date','expiry_date','today_date','retroactive_date',
                'basis_of_cover','policy_duration','is_renewable','currency','broker',
                'overall_annual_aggregate_limit','premium','tax_levies','total_premium_payable',
                'premium_payment_terms','premium_due_date',
                'sites','coverage_sections','deductibles','pollutants_covered','key_exclusions','endorsements','notes',
                'approved_by','approved_at',
                'policy_wording','policy_wording_path','policy_wording_filename',
            ],
            'json'   => ['sites','coverage_sections','deductibles','pollutants_covered','key_exclusions','endorsements'],
        ],
        'bonds' => [
            'table'  => 'bonds_coverages',
            'label'  => 'Bonds and Guarantees',
            'fields' => [
                // Identity columns — without these the saved row has
                // policy_coverage_id / coverage_id / action_id / term_id = NULL,
                // which makes getBondsTotal()/getPremium() and the V2 quote
                // sheet skip the row, so BONDSANDGUARANTEES shows P 0.00. Same
                // pattern as MM/PI/MB/Marine/Medical Evacuation/Commercial
                // Crime/Environmental Liability entries above.
                'policy_coverage_id','coverage_id','action_id','term_id',
                'policy_number','company_name','company_address','risk_address',
                'type_of_bond','limit_insured',
                'inception_date','expiry_date','today_date',
                'premium','excess','currency','broker','is_renewable',
                'bond_schedule',
                'insuring_agreement','trigger_event','subrogation_right',
                'non_cancellable_clause','collateral_security','exclusions','dispute_resolution',
                // Collateral capture (UW 2026-08-27). `collateral_security`
                // above stays the policy-wording clause and is pre-filled with
                // boilerplate, so it can never gate issue. These four carry the
                // actual security held. The confirmation trio
                // (collateral_confirmed / _by / _at) is deliberately ABSENT —
                // its single writer is confirmCollateral(), which requires the
                // `bonds-approve` permission. See Services\Bonds\BondsIssuanceGate.
                'collateral_type','collateral_value','collateral_reference','collateral_expiry_date',
                'extensions_endorsements',
                'notes',
                'approved_by','approved_at',
                'policy_wording','policy_wording_path','policy_wording_filename',
            ],
            'json'   => ['bond_schedule', 'extensions_endorsements'],
        ],
    ];

    /**
     * GET /policies/{id}/specialist-coverages/{type}
     * Return existing record(s) for the given type.
     */
    public function show(int $policyId, string $type, Request $request): JsonResponse
    {
        Policy::findOrFail($policyId);
        $spec = self::TYPE_MAP[$type] ?? null;
        if (!$spec) return response()->json(['error' => "Unknown specialist type: {$type}"], 422);

        try {
            $query = DB::table($spec['table'])->where('policy_id', $policyId);
            if ($request->filled('policy_coverage_id') && in_array('policy_coverage_id', $spec['fields'])) {
                $query->where('policy_coverage_id', (int) $request->input('policy_coverage_id'));
            }
            $rows = $query->get()->map(fn($r) => (array) $r)->values();
            return response()->json(['data' => $rows, 'label' => $spec['label']]);
        } catch (\Exception $e) {
            return response()->json(['data' => [], 'label' => $spec['label']]);
        }
    }

    /**
     * POST /policies/{id}/specialist-coverages/{type}
     * Create a new specialist coverage record.
     */
    public function store(Request $request, int $policyId, string $type): JsonResponse
    {
        $policy = Policy::findOrFail($policyId);
        $spec   = self::TYPE_MAP[$type] ?? null;
        if (!$spec) return response()->json(['error' => "Unknown specialist type: {$type}"], 422);

        $payload = $this->buildPayload($request, $spec, $policyId, $policy->customer_id ?? null, null);

        // Machinery Breakdown: Section 1 has three regulator-fixed coverage
        // items. Seed them on create when the request didn't supply rows so
        // records created via the API outside the form still match the
        // schedule template.
        if ($type === 'machinery-breakdown'
            && in_array('section1_items', $spec['fields'], true)
            && (empty($payload['section1_items']) || $payload['section1_items'] === '[]')
        ) {
            $payload['section1_items'] = json_encode([
                ['name' => 'Damage to insured property (per occurrence)', 'limit_status' => '', 'premium' => ''],
                ['name' => 'Cost of replacing undamaged non-compatible parts', 'limit_status' => '', 'premium' => ''],
                ['name' => 'Total insured value', 'limit_status' => '', 'premium' => ''],
            ]);
        }

        try {
            // Guard against duplicate rows: the frontend resolves "does a row
            // already exist for this policy_coverage_id?" asynchronously
            // (SpecialistCoveragePage.tsx, the ?policy_coverage_id=N lookup
            // effect) before switching the form into edit mode. If the user
            // saves while that lookup is still in flight, recordId is still
            // null client-side and a CREATE request is sent even though a
            // row for this policy_id + policy_coverage_id already exists —
            // producing a duplicate record (re-save inserts instead of
            // replacing). Re-check server-side and upsert instead of blindly
            // inserting whenever the payload carries a policy_coverage_id.
            $existingId = null;
            if (!in_array($type, self::MULTI_ROW_TYPES, true)
                && in_array('policy_coverage_id', $spec['fields'], true)
                && !empty($payload['policy_coverage_id'])
            ) {
                $tableColumns = \Illuminate\Support\Facades\Schema::getColumnListing($spec['table']);
                $existingId = DB::table($spec['table'])
                    ->where('policy_id', $policyId)
                    ->where('policy_coverage_id', $payload['policy_coverage_id'])
                    ->when(in_array('deleted_at', $tableColumns, true), function ($q) {
                        $q->whereNull('deleted_at');
                    })
                    ->orderBy('id')
                    ->value('id');
            }

            if ($existingId) {
                $updatePayload = $payload;
                unset($updatePayload['created_at']);
                DB::table($spec['table'])->where('id', $existingId)->update($updatePayload);
                activity('Specialist Coverage')->performedOn($policy)->causedBy(auth()->user())->log("{$spec['label']} Updated (duplicate create avoided)");
                $resp = ['message' => "{$spec['label']} updated.", 'id' => $existingId];
            } else {
                $id = DB::table($spec['table'])->insertGetId($payload);
                activity('Specialist Coverage')->performedOn($policy)->causedBy(auth()->user())->log("{$spec['label']} Created");
                $resp = ['message' => "{$spec['label']} created.", 'id' => $id];
            }
            if (!empty($this->lastDropped)) {
                $resp['warning'] = "Saved, but these fields were ignored because the column does not exist on {$spec['table']}: " . implode(', ', $this->lastDropped) . '. Ask an admin to run migrations.';
                $resp['dropped_fields'] = $this->lastDropped;
            }
            return response()->json($resp, 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * PUT /policies/{id}/specialist-coverages/{type}/{recordId}
     * Update an existing specialist coverage record.
     */
    public function update(Request $request, int $policyId, string $type, int $recordId): JsonResponse
    {
        $policy = Policy::findOrFail($policyId);
        $spec   = self::TYPE_MAP[$type] ?? null;
        if (!$spec) return response()->json(['error' => "Unknown specialist type: {$type}"], 422);

        $exists = DB::table($spec['table'])->where('id', $recordId)->where('policy_id', $policyId)->exists();
        if (!$exists) return response()->json(['error' => 'Record not found.'], 404);

        $payload = $this->buildPayload($request, $spec, $policyId, null, $recordId);
        unset($payload['created_at']); // don't overwrite

        // Bonds: changing the security on file revokes its confirmation. Without
        // this, an EXCO confirmation of a P5m bank guarantee would still stand
        // after the row was edited down to a P50k cash deposit, and the issue
        // gate would wave it through. Re-confirmation is required.
        if ($spec['table'] === 'bonds_coverages') {
            $this->revokeCollateralConfirmationIfChanged($recordId, $payload);
        }

        try {
            DB::table($spec['table'])->where('id', $recordId)->update($payload);
            activity('Specialist Coverage')->performedOn($policy)->causedBy(auth()->user())->log("{$spec['label']} Updated");
            $resp = ['message' => "{$spec['label']} updated."];
            if (!empty($this->lastDropped)) {
                $resp['warning'] = "Saved, but these fields were ignored because the column does not exist on {$spec['table']}: " . implode(', ', $this->lastDropped) . '. Ask an admin to run migrations.';
                $resp['dropped_fields'] = $this->lastDropped;
            }
            return response()->json($resp);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * DELETE /policies/{id}/specialist-coverages/{type}/{recordId}
     */
    public function destroy(int $policyId, string $type, int $recordId): JsonResponse
    {
        $policy = Policy::findOrFail($policyId);
        $spec   = self::TYPE_MAP[$type] ?? null;
        if (!$spec) return response()->json(['error' => "Unknown specialist type: {$type}"], 422);

        $deleted = DB::table($spec['table'])->where('id', $recordId)->where('policy_id', $policyId)->delete();
        if (!$deleted) return response()->json(['error' => 'Record not found.'], 404);

        activity('Specialist Coverage')->performedOn($policy)->causedBy(auth()->user())->log("{$spec['label']} Deleted");
        return response()->json(['message' => "{$spec['label']} deleted."]);
    }

    /**
     * POST /policies/{id}/specialist-coverages/{type}/{recordId}/approve-period
     * Record authorization for a 24 or 36-month policy period.
     */
    public function approvePeriod(int $policyId, string $type, int $recordId): JsonResponse
    {
        Policy::findOrFail($policyId);
        $spec = self::TYPE_MAP[$type] ?? null;
        if (!$spec) return response()->json(['error' => "Unknown specialist type: {$type}"], 422);

        if (!in_array('policy_period_months', $spec['fields'])) {
            return response()->json(['error' => 'Policy period approval not supported for this type.'], 422);
        }

        $exists = DB::table($spec['table'])->where('id', $recordId)->where('policy_id', $policyId)->exists();
        if (!$exists) return response()->json(['error' => 'Record not found.'], 404);

        DB::table($spec['table'])->where('id', $recordId)->update([
            'approved_by' => auth()->id(),
            'approved_at' => now(),
            'updated_at'  => now(),
        ]);

        return response()->json(['message' => 'Policy period approved.', 'approved_by' => auth()->id(), 'approved_at' => now()->toDateTimeString()]);
    }

    /**
     * POST /policies/{id}/specialist-coverages/bonds/{recordId}/confirm-collateral
     *
     * Confirm the collateral recorded on a bond schedule is actually held.
     * This is the second half of the Bonds issue gate — until it is stamped,
     * BondsIssuanceGate blocks Issue.
     *
     * Single writer for collateral_confirmed / _by / _at: the schedule save
     * form cannot set them (they are absent from the bonds field list), and
     * editing the collateral revokes the stamp (see update()).
     *
     * Requires the same one permission as bond approval — `bonds-approve`,
     * held by EXCO. Pass `revoke=1` to withdraw a confirmation.
     */
    public function confirmCollateral(Request $request, int $policyId, string $type, int $recordId): JsonResponse
    {
        $policy = Policy::findOrFail($policyId);
        $spec   = self::TYPE_MAP[$type] ?? null;
        if (!$spec || $spec['table'] !== 'bonds_coverages') {
            return response()->json(['error' => 'Collateral confirmation applies to Bonds and Guarantees only.'], 422);
        }

        if (!\AlphaDirect\Services\Bonds\BondsIssuanceGate::userMayApprove()) {
            return response()->json([
                'error' => 'Bond collateral may only be confirmed by EXCO. You do not hold the bond approval right.',
            ], 403);
        }

        $row = DB::table($spec['table'])->where('id', $recordId)->where('policy_id', $policyId)->first();
        if (!$row) return response()->json(['error' => 'Record not found.'], 404);

        $revoke = filter_var($request->input('revoke', false), FILTER_VALIDATE_BOOLEAN);

        if (!$revoke) {
            // Nothing to confirm unless the security is actually captured.
            if (trim((string) ($row->collateral_type ?? '')) === '' || (float) ($row->collateral_value ?? 0) <= 0) {
                return response()->json([
                    'error' => 'Capture the collateral type and value on the bond schedule before confirming it.',
                ], 422);
            }
        }

        DB::table($spec['table'])->where('id', $recordId)->update([
            'collateral_confirmed'    => $revoke ? 0 : 1,
            'collateral_confirmed_by' => $revoke ? null : auth()->id(),
            'collateral_confirmed_at' => $revoke ? null : now(),
            'updated_at'              => now(),
        ]);

        activity('Specialist Coverage')->performedOn($policy)->causedBy(auth()->user())
            ->withProperties([
                'collateral_type'      => $row->collateral_type ?? null,
                'collateral_value'     => $row->collateral_value ?? null,
                'collateral_reference' => $row->collateral_reference ?? null,
            ])
            ->log($revoke ? 'Bond Collateral Confirmation Revoked' : 'Bond Collateral Confirmed');

        return response()->json([
            'message'                 => $revoke ? 'Collateral confirmation revoked.' : 'Collateral confirmed.',
            'collateral_confirmed'    => $revoke ? 0 : 1,
            'collateral_confirmed_by' => $revoke ? null : auth()->id(),
            'collateral_confirmed_at' => $revoke ? null : now()->toDateTimeString(),
        ]);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Clear a bond's collateral confirmation when the security on file is
     * edited. Called from update() before the row is written.
     */
    private function revokeCollateralConfirmationIfChanged(int $recordId, array $payload): void
    {
        $watched = ['collateral_type', 'collateral_value', 'collateral_reference', 'collateral_expiry_date'];
        $submitted = array_intersect($watched, array_keys($payload));
        if (empty($submitted)) return;

        $row = DB::table('bonds_coverages')->where('id', $recordId)->first();
        if (!$row || (int) ($row->collateral_confirmed ?? 0) !== 1) return;

        foreach ($submitted as $col) {
            $old = $row->{$col} ?? null;
            $new = $payload[$col];

            // Compare on value, not on formatting: '5000.00' vs 5000 and
            // '2026-09-01' vs '2026-09-01 00:00:00' are not edits.
            $same = in_array($col, ['collateral_value'], true)
                ? abs((float) $old - (float) $new) < 0.005
                : trim((string) $old) === trim((string) $new)
                    || ($old && $new && substr((string) $old, 0, 10) === substr((string) $new, 0, 10));

            if (!$same) {
                DB::table('bonds_coverages')->where('id', $recordId)->update([
                    'collateral_confirmed'    => 0,
                    'collateral_confirmed_by' => null,
                    'collateral_confirmed_at' => null,
                ]);
                return;
            }
        }
    }

    /** Fields dropped by the last buildPayload() call — surfaced in the response. */
    private array $lastDropped = [];

    /**
     * s_CoverageCode aliases per specialist table. Used ONLY to recover the
     * parent policy_coverage when the caller supplied no policy_coverage_id.
     *
     * The Policy Actions tab opens these schedules through an "Open … Schedule"
     * button that passes no query params until a record exists
     * (PolicyDetailPage.tsx), so the first save arrived with policy_coverage_id
     * NULL. An orphaned row is not merely cosmetic: getMedicalEvacuationTotal()
     * / getCommercialCrimeTotal() / getBondsTotal(), the V2 quote sheet, the
     * Rate button's specialist bucket (sumSpecialistPremium scopes by
     * policy_coverage_id) and every SpecialistCoverageRegistry path (delete
     * cascade, action replication, refresher, pro-rata writer) all key on it —
     * so the schedule's whole premium silently reads P 0.00.
     *
     * Mirrors TYPE_COVERAGE_CODES in SpecialistCoveragePage.tsx, which does the
     * same recovery client-side for rows already saved without a parent. Alias
     * sets follow PolicyCreateController::singletonCoverageFamilies() and
     * PolicyCoverage::ONE_PER_ADDRESS_COVERAGE_GROUPS.
     */
    private const TABLE_COVERAGE_CODES = [
        'ear_coverages'                       => ['ERECTIONALLRISKS', 'EAR'],
        'car_coverages'                       => ['CONTRACTORSALLRISKS', 'CAR'],
        'par_coverages'                       => ['PLANTALLRISKS', 'PAR'],
        'machinery_breakdown_coverages'       => ['MACHINERYBREAKDOWN'],
        'medical_malpractice_coverages'       => ['MEDICAMALPRACTICEINSURANCE', 'MEDICALMALPRACTICE', 'MM'],
        'professional_indemnity_coverages'    => ['PROFESSIONALINDEMNITY', 'PROFESSIONAL_INDEMNITY', 'PI'],
        'travel_coverages'                    => ['TRAVELINSURANCE', 'TRAVEL'],
        'marine_cargo_open_coverages'         => ['MARINEOPENCOVER'],
        'marine_cargo_once_off_coverages'     => ['MARINEONCEOFFCOVER', 'MARINECARGOONCEOFF'],
        'marine_directors_officers_coverages' => ['MARINEDIRECTORSOFFICERS', 'DIRECTORSOFFICERSLIABILITY'],
        'medical_evacuation_coverages'        => ['MEDICALEVACUATION'],
        'commercial_crime_coverages'          => ['COMMERCIALCRIME'],
        'environmental_liability_coverages'   => ['ENVIRONMENTALLIABILITY'],
        'bonds_coverages'                     => ['BONDSANDGUARANTEES'],
    ];

    /**
     * The policy's own policy_coverage for this specialist table, or null.
     *
     * Returns null unless exactly ONE non-deleted coverage matches. These covers
     * are one-per-address, but a policy may legitimately carry the same cover on
     * two risk addresses — attaching the schedule to an arbitrary one of them
     * would be worse than leaving it unlinked, since the wrong parent silently
     * moves premium onto the wrong coverage.
     */
    private static function resolveParentPolicyCoverageId(int $policyId, string $table): ?int
    {
        $codes = self::TABLE_COVERAGE_CODES[$table] ?? null;
        if (!$codes) return null;

        $ids = DB::table('policy_coverages as pc')
            ->join('tb_cvgpccoverages as cv', 'cv.id', '=', 'pc.coverage_id')
            ->where('pc.policy_id', $policyId)
            ->whereNull('pc.deleted_at')
            ->whereIn(DB::raw('UPPER(cv.s_CoverageCode)'), $codes)
            ->pluck('pc.id');

        return $ids->count() === 1 ? (int) $ids->first() : null;
    }

    private function buildPayload(Request $request, array $spec, int $policyId, $customerId, ?int $recordId): array
    {
        $data = $request->only($spec['fields']);

        // Recover the parent coverage when the caller didn't supply one, so the
        // row is never written orphaned. Runs BEFORE the back-fill below, which
        // then seeds coverage_id / action_id / term_id from it. See
        // TABLE_COVERAGE_CODES for why an orphan is a premium bug, not cosmetic.
        if (in_array('policy_coverage_id', $spec['fields'], true)
            && empty($data['policy_coverage_id'])
        ) {
            $recovered = self::resolveParentPolicyCoverageId($policyId, $spec['table']);
            if ($recovered) {
                $data['policy_coverage_id'] = $recovered;
            }
        }

        // When the request carries policy_coverage_id, copy coverage_id /
        // action_id / term_id from the parent policy_coverages row so the
        // specialist record is scoped to the exact same action/term as its
        // parent. This is more accurate than the "latest action on policy"
        // fallback below (a policy can have multiple open actions), and it
        // also seeds coverage_id, which the latest-action fallback never
        // touches — so without this, specialist rows save with coverage_id
        // NULL even though policy_coverage_id is set.
        if (in_array('policy_coverage_id', $spec['fields'], true) && !empty($data['policy_coverage_id'])) {
            $parent = DB::table('policy_coverages')->where('id', $data['policy_coverage_id'])->first();
            if ($parent) {
                if (in_array('coverage_id', $spec['fields'], true) && empty($data['coverage_id'])) {
                    $data['coverage_id'] = $parent->coverage_id;
                }
                if (in_array('action_id', $spec['fields'], true) && empty($data['action_id'])) {
                    $data['action_id'] = $parent->action_id;
                }
                if (in_array('term_id', $spec['fields'], true) && empty($data['term_id'])) {
                    $data['term_id'] = $parent->term_id;
                }
            }
        }

        // Auto-populate action_id / term_id from the policy's latest action
        // when the schema supports them. Engineering tables (CAR/PAR/EAR) need
        // these so each schedule row is scoped to a specific policy action +
        // term, mirroring policy_coverages. Caller payload still wins if it
        // explicitly supplied them.
        if (in_array('action_id', $spec['fields'], true) && empty($data['action_id'])) {
            $latestAction = \AlphaDirect\Models\PolicyAction::where('policy_id', $policyId)
                ->orderBy('id', 'desc')->first();
            if ($latestAction) {
                $data['action_id'] = $latestAction->id;
                if (in_array('term_id', $spec['fields'], true) && empty($data['term_id'])) {
                    $data['term_id'] = $latestAction->term_id;
                }
            }
        }
        if (in_array('term_id', $spec['fields'], true) && empty($data['term_id'])) {
            $latestTerm = \AlphaDirect\PolicyTerm::where('policy_id', $policyId)
                ->orderBy('id', 'desc')->first();
            if ($latestTerm) $data['term_id'] = $latestTerm->id;
        }

        // Normalize JSON columns. MySQL JSON columns enforce a CHECK(json_valid)
        // constraint, so an empty string or scalar from the request would 4025
        // on save. Always coerce to a valid JSON document.
        foreach ($spec['json'] as $col) {
            if (!array_key_exists($col, $data)) {
                continue;
            }
            $val = $data[$col];
            if (is_array($val)) {
                $data[$col] = json_encode($val);
            } elseif ($val === '' || $val === null) {
                $data[$col] = '[]';
            } elseif (is_string($val)) {
                json_decode($val);
                $data[$col] = (json_last_error() === JSON_ERROR_NONE) ? $val : '[]';
            } else {
                $data[$col] = '[]';
            }
        }

        // Machinery Breakdown: `premium` is a DERIVED total, not a free-typed
        // figure. It is the sum of the item premiums across SECTION 1, the
        // Machinery Listing, SECTION 2 and SECTION 3 — see
        // MachineryBreakdownCoverage::SECTION_PREMIUM_COLUMNS. The capture form
        // shows it read-only, but recomputing it server-side as well is what
        // actually guarantees the invariant: everything downstream that prices
        // or prints MB (the Rate banner's specialist bucket, the V2 quote
        // sheet, the engineering Policy Document, the pro-rata writer) reads
        // this one scalar, so an operator who priced every section but never
        // retyped the header box used to get a Rate Sheet total that didn't
        // match their own schedule.
        //
        // Only recompute when the request carried ALL FOUR section columns —
        // that's a full form save, where a zero total is a real answer. A
        // partial/API post that omits them leaves the stored premium alone
        // rather than zeroing it.
        if ($spec['table'] === 'machinery_breakdown_coverages') {
            $sectionCols = \AlphaDirect\Models\MachineryBreakdownCoverage::SECTION_PREMIUM_COLUMNS;
            $submitted   = array_intersect($sectionCols, array_keys($data));
            if (count($submitted) === count($sectionCols)) {
                $data['premium'] = \AlphaDirect\Models\MachineryBreakdownCoverage::sectionPremiumTotal($data);
            }
        }

        // PAR: total_premium is the sum of the Specification of Insured Items
        // Premium column, not a free-typed figure — same rule as MB above. The
        // capture page recomputes it client-side, but only when the items grid
        // is touched: open a PAR record, change something else, save, and the
        // stale scalar is written straight back. That matters because the
        // specialist ENDORSE charge is a coverage-level delta (this action's
        // annual minus the baseline action's), so a scalar that disagrees with
        // its own schedule by P1.08 charges that P1.08 to whatever row the
        // operator adds next. Rebuilding it here makes the stored figure match
        // the schedule no matter which client wrote it.
        //
        // Only when insured_items was actually submitted AND prices to
        // something — a partial/API post that omits the schedule leaves the
        // stored total alone rather than zeroing it (mirrors the Bonds guard).
        if ($spec['table'] === 'par_coverages' && array_key_exists('insured_items', $data)) {
            $derived = \AlphaDirect\Models\ParCoverage::insuredItemsPremiumTotal(
                ['insured_items' => $data['insured_items']]
            );
            if ($derived > 0) {
                $data['total_premium'] = round($derived, 2);
            }
        }

        // Bonds: same rule for bonds_coverages.premium — it is the sum of the
        // Bond Coverage Schedule's Annual Premium column, not a hand-typed
        // figure. getBondsTotal()/getPremium() read only this column, so the
        // Rate Sheet and V2 quote sheet would otherwise show a total the
        // operator's own schedule doesn't support. The form sends it read-only;
        // this closes the same gap for a direct API post.
        //
        // Only recompute when bond_schedule was actually submitted, and only
        // when it prices to something — mirrors the frontend guard so a record
        // captured before this change keeps its hand-entered premium instead of
        // being zeroed by a save that never touched the schedule.
        if ($spec['table'] === 'bonds_coverages' && array_key_exists('bond_schedule', $data)) {
            $schedule = $data['bond_schedule'];
            if (is_string($schedule)) {
                $schedule = json_decode($schedule, true);
            }
            if (is_array($schedule)) {
                $total = 0.0;
                foreach ($schedule as $row) {
                    if (!is_array($row)) continue;
                    $total += (float) str_replace(',', '', (string) ($row['annual_premium'] ?? 0));
                }
                if ($total > 0) {
                    $data['premium'] = round($total, 2);
                }
            }

            // Collateral: an empty box means "not captured", not "0" / "0000-00-00".
            // A decimal / date column in strict mode rejects '' outright, so
            // normalise before the payload is written.
            foreach (['collateral_value', 'collateral_expiry_date', 'collateral_reference', 'collateral_type'] as $col) {
                if (array_key_exists($col, $data) && $data[$col] === '') {
                    $data[$col] = null;
                }
            }
        }

        // Handle policy wording file upload.
        // Wrapped so a broken S3 config (empty bucket, bad creds) no longer
        // 500s the ENTIRE store()/update() call. If storage is unavailable
        // we log + skip the file — the rest of the form fields still save
        // and the UI shows a warning.
        if ($request->hasFile('policy_wording_file')) {
            $file = $request->file('policy_wording_file');
            $policyNumber = \AlphaDirect\Policy::where('id', $policyId)->value('policyNumber') ?? $policyId;
            try {
                $path = self::storeWithFallback($file, "policy_wording/{$policyNumber}");
                $data['policy_wording_path'] = $path;
            } catch (\Throwable $e) {
                \Log::error('buildPayload upload failed, falling back to local: ' . $e->getMessage());
                try {
                    $data['policy_wording_path'] = $file->storeAs(
                        "policy_wording/{$policyNumber}",
                        uniqid('', true) . '.' . $file->getClientOriginalExtension(),
                        'public'
                    );
                } catch (\Throwable $e2) {
                    \Log::error('Local fallback in buildPayload failed: ' . $e2->getMessage());
                    // Don't poison the save — just skip the file field
                }
            }
        }

        // Filter out fields that don't exist as columns in the actual DB table,
        // but remember what got dropped so the UI can surface "missing column"
        // warnings. This was the root cause of "Policy Number not persisted"
        // on fresh envs where a later ALTER migration hadn't been run.
        $tableColumns = \Illuminate\Support\Facades\Schema::getColumnListing($spec['table']);
        $dropped = array_diff_key($data, array_flip($tableColumns));
        $this->lastDropped = array_keys($dropped);
        if (!empty($this->lastDropped)) {
            \Log::warning("SpecialistCoverage: dropped fields missing from {$spec['table']}", [
                'fields' => $this->lastDropped,
                'hint'   => "Run the ALTER migration for {$spec['table']} to add these columns.",
            ]);
        }
        $data = array_intersect_key($data, array_flip($tableColumns));

        $payload = array_merge($data, [
            'policy_id'  => $policyId,
            'updated_at' => now(),
        ]);

        if ($recordId === null) {
            $payload['created_at'] = now();
        }

        return $payload;
    }

    /**
     * POST /policies/{policyId}/specialist-coverages/{type}/upload-wording
     * Upload policy wording PDF for any specialist coverage.
     */
    public function uploadWording(Request $request, int $policyId, string $type): JsonResponse
    {
        $policy = Policy::findOrFail($policyId);
        $spec   = self::TYPE_MAP[$type] ?? null;
        if (!$spec) return response()->json(['error' => "Unknown specialist type: {$type}"], 422);

        // Diagnostic log so "uploaded but path not in DB" reports can be
        // pin-pointed to the failure mode (validation? record lookup? storage?).
        \Log::info('SpecialistCoverage::uploadWording incoming', [
            'policy_id'          => $policyId,
            'type'               => $type,
            'has_file'           => $request->hasFile('policy_wording_file'),
            'all_files'          => array_keys($request->allFiles()),
            'record_id'          => $request->input('record_id'),
            'content_type'       => $request->header('Content-Type'),
        ]);

        $request->validate([
            'policy_wording_file' => 'required|file|mimes:pdf|max:20480',
            'record_id'          => 'required|integer',
        ]);

        $recordId = (int) $request->input('record_id');
        $record = DB::table($spec['table'])->where('id', $recordId)->where('policy_id', $policyId)->first();
        if (!$record) {
            \Log::warning('SpecialistCoverage::uploadWording record_id not found', [
                'policy_id' => $policyId, 'type' => $type, 'record_id' => $recordId,
            ]);
            return response()->json(['error' => "Record not found (id={$recordId} on {$spec['table']})."], 404);
        }

        $file = $request->file('policy_wording_file');

        // IMPORTANT: wrap the storage call to prevent raw AWS SDK messages
        // ("The PutObject operation requires non-empty parameter: Bucket")
        // from ever leaking to the FE alert(). When S3 is mis-configured in
        // an env, the StorageService should fall back to `public`; but if
        // both disks fail we now do a last-resort direct write to the
        // storage/app/public filesystem so upload never 500s with AWS noise.
        try {
            $path = self::storeWithFallback($file, "policy_wording/{$policy->policyNumber}");
            $disk = self::resolveDisk($path);
        } catch (\Throwable $e) {
            \Log::error('SpecialistCoverage uploadWording storage failed: ' . $e->getMessage(), [
                'policy_id' => $policyId, 'type' => $type, 'record_id' => $recordId,
            ]);
            // Last-ditch: write directly to local public disk, bypass s3 entirely
            try {
                $stored = $file->storeAs(
                    "policy_wording/{$policy->policyNumber}",
                    uniqid('', true) . '.' . $file->getClientOriginalExtension(),
                    'public'
                );
                $path = $stored;
                $disk = 'public';
            } catch (\Throwable $e2) {
                \Log::error('Local fallback also failed: ' . $e2->getMessage());
                return response()->json([
                    'error'   => 'Upload storage is temporarily unavailable. Your record was NOT updated. Please contact support.',
                    'message' => 'Upload storage is temporarily unavailable. Your record was NOT updated. Please contact support.',
                ], 503);
            }
        }

        // If the column doesn't exist on this env, an unfiltered update()
        // throws QueryException → the alert just shows a generic "upload
        // failed" but the file IS already in S3. Probe the schema first so
        // the FE gets a clear "run migrations" hint instead of a SQL error.
        $hasColumn = \Illuminate\Support\Facades\Schema::hasColumn($spec['table'], 'policy_wording_path');
        if (!$hasColumn) {
            \Log::error("SpecialistCoverage::uploadWording: '{$spec['table']}.policy_wording_path' column missing — file uploaded to {$disk} ({$path}) but DB not updated.");
            return response()->json([
                'error'   => "Column 'policy_wording_path' missing on {$spec['table']}. Run: php artisan migrate",
                'message' => "File stored at {$path} but the schedule row was not updated — your DB is missing the policy_wording_path column. Ask an admin to run migrations.",
                'path'    => $path,
            ], 500);
        }

        // Persist the original filename when the schema supports it — lets
        // the FE display "claims-policy-wording.pdf" instead of the opaque
        // storage path "policy_wording/COMG-2026/uniqid.pdf".
        $originalName = $file->getClientOriginalName();
        $updates = [
            'policy_wording_path' => $path,
            'updated_at'          => now(),
        ];
        if (\Illuminate\Support\Facades\Schema::hasColumn($spec['table'], 'policy_wording_filename')) {
            $updates['policy_wording_filename'] = $originalName;
        }
        if (\Illuminate\Support\Facades\Schema::hasColumn($spec['table'], 'policy_wording')) {
            $updates['policy_wording'] = $originalName;
        }
        $rowsAffected = DB::table($spec['table'])->where('id', $recordId)->update($updates);
        \Log::info('SpecialistCoverage::uploadWording DB update', [
            'table'         => $spec['table'],
            'record_id'     => $recordId,
            'path'          => $path,
            'filename'      => $originalName,
            'rows_affected' => $rowsAffected,
        ]);

        activity('Specialist Coverage')
            ->performedOn($policy)
            ->causedBy(auth()->user())
            ->log("{$spec['label']} Policy Wording uploaded: {$file->getClientOriginalName()} (disk={$disk})");

        // URL lookup is also wrapped — if S3 url() throws (empty bucket)
        // we still return a successful response with the path so the FE
        // can show "uploaded" without blowing up on a secondary error.
        $url = null;
        try {
            $url = \Illuminate\Support\Facades\Storage::disk($disk)->url($path);
        } catch (\Throwable $e) {
            \Log::warning('url() failed for disk ' . $disk . ': ' . $e->getMessage());
        }

        return response()->json([
            'message'  => 'Policy wording uploaded.',
            'path'     => $path,
            'filename' => $originalName,
            'disk'    => $disk,
            'url'     => $url,
        ]);
    }

    /**
     * Store a file using StorageService (S3 primary, local fallback).
     * Returns the storage path only (for DB column).
     */
    private static function storeWithFallback($file, string $directory): string
    {
        $result = app(\AlphaDirect\Services\StorageService::class)
            ->putFileWithFallback($directory, $file);
        return $result['path'];
    }

    /**
     * Guess which disk a stored file lives on. Delegates to StorageService.
     */
    private static function resolveDisk(string $path): string
    {
        return app(\AlphaDirect\Services\StorageService::class)->resolveDisk($path) ?? 's3';
    }

    /**
     * GET /policies/{policyId}/specialist-coverages/{type}/download-wording/{recordId}
     * Download/view policy wording PDF.
     */
    public function downloadWording(int $policyId, string $type, int $recordId)
    {
        $spec = self::TYPE_MAP[$type] ?? null;
        if (!$spec) return response()->json(['error' => "Unknown type: {$type}"], 422);

        $record = DB::table($spec['table'])->where('id', $recordId)->where('policy_id', $policyId)->first();
        if (!$record || empty($record->policy_wording_path)) {
            return response()->json(['error' => 'No policy wording found.'], 404);
        }

        // Try local 'public' disk first, fall back to S3
        foreach (['public', 's3'] as $diskName) {
            try {
                $disk = \Illuminate\Support\Facades\Storage::disk($diskName);
                if ($disk->exists($record->policy_wording_path)) {
                    return response($disk->get($record->policy_wording_path), 200, [
                        'Content-Type'        => 'application/pdf',
                        'Content-Disposition' => 'inline; filename="policy_wording.pdf"',
                    ]);
                }
            } catch (\Throwable $e) {
                \Log::warning("Disk {$diskName} check failed: {$e->getMessage()}");
            }
        }

        return response()->json(['error' => 'File not found in storage.'], 404);
    }
}
