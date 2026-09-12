<?php

namespace AlphaDirect\Http\Controllers\Admin;

use AlphaDirect\Documents;
use AlphaDirect\Product;
use Illuminate\Http\Request;
use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\MotorComprehensiveSchedule;
use Illuminate\Support\Facades\Storage;
use Redirect;
use Yajra\DataTables\DataTables;

class ScheduleController extends Controller
{
    public function index(){
        return view('admin.Schedule.listing');
    }

    public function create(){
        return view('admin.Schedule.create');
    }

    public function store(Request $request){
        $schedule = new MotorComprehensiveSchedule();
        $schedule->transaction_type = $request->transaction_type;
        $schedule->version_number = $request->version_number;
        $schedule->territorial_limits = $request->territorial_limits;
        $schedule->contract_type = $request->contract_type;
        $schedule->isssued_by = $request->isssued_by;
        $schedule->own_damage_min_perc	 = $request->own_damage_min_perc;
        $schedule->own_damage_min_amt = $request->own_damage_min_amt;
        $schedule->own_damage_fap_basis = $request->own_damage_fap_basis;
        $schedule->theft_min_perc = $request->theft_min_perc;
        $schedule->theft_min_amt = $request->theft_min_amt;
        $schedule->theft_min_fap_basis = $request->theft_min_fap_basis;
        $schedule->underage_min_perc = $request->underage_min_perc;
        $schedule->underage_min_amt = $request->underage_min_amt;
        $schedule->underage_fap_basis = $request->underage_fap_basis;
        $schedule->license_issue_min_perc = $request->license_issue_min_perc;
        $schedule->license_issue_min_amt = $request->license_issue_min_amt;
        $schedule->license_issue_fap_basis = $request->license_issue_fap_basis;
        $schedule->windscreen_min_perc = $request->windscreen_min_perc;
        $schedule->windscreen_min_amt = $request->windscreen_min_amt;
        $schedule->windscreen_fap_basis = $request->windscreen_fap_basis;
        $schedule->key_loss_min_perc = $request->key_loss_min_perc;
        $schedule->key_loss_min_amt = $request->key_loss_min_amt;
        $schedule->key_loss_fap_basis = $request->key_loss_fap_basis;
        $schedule->riots_strikes_included = $request->riots_strikes_included;
        $schedule->riots_strikes_sum_insured = $request->riots_strikes_sum_insured;
        $schedule->riots_strikes_premium = $request->riots_strikes_premium;
        $schedule->riots_strikes_fap_perc = $request->riots_strikes_fap_perc;
        $schedule->riots_strikes_fap_amt = $request->riots_strikes_fap_amt;
        $schedule->riots_strikes_fap_basis = $request->riots_strikes_fap_basis;
        $schedule->fire_explosion_included = $request->fire_explosion_included;
        $schedule->fire_explosion_sum_insured = $request->fire_explosion_sum_insured;
        $schedule->fire_explosion_premium = $request->fire_explosion_premium;
        $schedule->fire_explosion_fap_perc = $request->fire_explosion_fap_perc;
        $schedule->fire_explosion_fap_amt = $request->fire_explosion_fap_amt;
        $schedule->fire_explosion_fap_basis = $request->fire_explosion_fap_basis;
        $schedule->window_glass_included = $request->window_glass_included;
        $schedule->window_glass_sum_insured = $request->window_glass_sum_insured;
        $schedule->window_glass_premium = $request->window_glass_premium;
        $schedule->window_glass_fap_perc = $request->window_glass_fap_perc;
        $schedule->window_glass_fap_amt = $request->window_glass_fap_amt;
        $schedule->window_glass_fap_basis = $request->window_glass_fap_basis;
        $schedule->keylock_loss_included = $request->keylock_loss_included;
        $schedule->keylock_sum_insured = $request->keylock_sum_insured;
        $schedule->keylock_premium = $request->keylock_premium;
        $schedule->keylock_fap_perc = $request->keylock_fap_perc;
        $schedule->keylock_fap_amt = $request->keylock_fap_amt;
        $schedule->keylock_fap_basis = $request->keylock_fap_basis;
        $schedule->third_party_liability_included = $request->third_party_liability_included;
        $schedule->third_party_liability_sum_insured = $request->third_party_liability_sum_insured;
        $schedule->third_party_liability_premium = $request->third_party_liability_premium;
        $schedule->third_party_liability_fap_perc = $request->third_party_liability_fap_perc;
        $schedule->third_party_liability_fap_amt = $request->third_party_liability_fap_amt;
        $schedule->third_party_liability_fap_basis = $request->third_party_liability_fap_basis;

        $schedule->wreckage_removal_included = $request->wreckage_removal_included;
        $schedule->wreckage_removal_sum_insured = $request->wreckage_removal_sum_insured;
        $schedule->wreckage_removal_premium = $request->wreckage_removal_premium;
        $schedule->wreckage_removal_fap_perc = $request->wreckage_removal_fap_perc;
        $schedule->wreckage_removal_fap_amnt = $request->wreckage_removal_fap_amnt;
        $schedule->wreckage_removal_fap_basis = $request->wreckage_removal_fap_basis;

        $schedule->tow_in_cost_included = $request->tow_in_cost_included;
        $schedule->tow_in_cost_sum_insured = $request->tow_in_cost_sum_insured;
        $schedule->tow_in_cost_premium = $request->tow_in_cost_premium;
        $schedule->tow_in_cost_fap_perc = $request->tow_in_cost_fap_perc;
        $schedule->tow_in_cost_fap_amt = $request->tow_in_cost_fap_amt;
        $schedule->tow_in_cost_fap_basis = $request->tow_in_cost_fap_basis;
        $schedule->accessories_included = $request->accessories_included;
        $schedule->accessories_sum_insured = $request->accessories_sum_insured;
        $schedule->accessories_premium = $request->accessories_premium;
        $schedule->accessories_fap_perc = $request->accessories_fap_perc;
        $schedule->accessories_fap_amt = $request->accessories_fap_amt;
        $schedule->accessories_fap_basis = $request->accessories_fap_basis;
        $schedule->audio_accessories_included = $request->audio_accessories_included;
        $schedule->audio_accessories_sum_insured = $request->audio_accessories_sum_insured;
        $schedule->audio_accessories_premium = $request->audio_accessories_premium;
        $schedule->audio_accessories_fap_perc = $request->audio_accessories_fap_perc;
        $schedule->audio_accessories_fap_amt = $request->audio_accessories_fap_amt;
        $schedule->audio_accessories_fap_basis = $request->audio_accessories_fap_basis;
        $schedule->car_hire_included = $request->car_hire_included;
        $schedule->car_hire_sum_insured = $request->car_hire_sum_insured;
        $schedule->car_hire_premium = $request->car_hire_premium;
        $schedule->car_hire_fap_perc = $request->car_hire_fap_perc;
        $schedule->car_hire_fap_amt = $request->car_hire_fap_fap_amt;
        $schedule->car_hire_fap_basis = $request->car_hire_fap_basis;
        $schedule->medical_expense_included = $request->medical_expense_included;
        $schedule->medical_expense_sum_insured = $request->medical_expense_sum_insured;
        $schedule->medical_expense_premium = $request->medical_expense_premium;
        $schedule->medical_expense_fap_perc = $request->medical_expense_fap_perc;
        $schedule->medical_expense_fap_amt = $request->medical_expense_fap_amt;
        $schedule->medical_expense_fap_basis = $request->medical_expense_fap_basis;
        $schedule->memo = $request->memo;
        $schedule->save();

        return view('admin.Schedule.listing')->with('success', 'Schedule added successfully');
    }

    public function data()
    {
        $docs = MotorComprehensiveSchedule::get(array('id','version_number'));
        return DataTables::of($docs)
            ->addColumn('actions',function($docs) {
                $actions = '';

                    $actions .= '<a href="" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Edit">
                                <i class="la la-edit"></i>
                            </a>';
                return $actions;
            })
            ->rawColumns(['actions'])
            ->make(true);
    }


}
