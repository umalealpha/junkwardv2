<?php

namespace AlphaDirect\Http\Requests\Api\V1;

use AlphaDirect\Http\Controllers\Api\V1\ThirdPartyCarController;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation contract for POST /api/v1/public/policies/create-third-party-car.
 *
 * Extracted from ThirdPartyCarController::createPolicy() for testability and to
 * keep the controller focused on persistence. Behaviour is preserved:
 *   - Same field rules and same error shape on validation failure.
 *   - The omang_or_passport cross-field guard remains in the controller so its
 *     {ok:false, error:'omang_or_passport_required'} response shape stays as-is
 *     (a FormRequest validator-after callback would emit Laravel's standard
 *     {message, errors:{}} shape instead and break existing FE handling).
 *
 * Plan whitelist is sourced from ThirdPartyCarController::ALLOWED_PLAN_IDS so
 * the rules track the controller's source of truth.
 */
class CreateThirdPartyCarRequest extends FormRequest
{
    /**
     * Bearer / session-phone match are enforced by the controller after this
     * FormRequest passes. Authorization here is intentionally a no-op so we
     * don't double-handle 401 vs 422 response semantics.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $allowedPlans = implode(',', ThirdPartyCarController::ALLOWED_PLAN_IDS);
        $minYear      = (int) (date('Y') - 40);
        $maxYear      = (int) (date('Y') + 1);

        return [
            'firstName'               => ['required', 'string', 'max:60', 'regex:/^[A-Za-z. \'-]+$/'],
            'lastName'                => ['required', 'string', 'max:60', 'regex:/^[A-Za-z. \'-]+$/'],
            'middleName'              => ['nullable', 'string', 'max:60', 'regex:/^[A-Za-z. \'-]+$/'],
            'omang'                   => ['nullable', 'string', 'regex:/^[0-9]{9}$/'],
            'passport'                => ['nullable', 'string', 'min:5', 'max:20', 'regex:/^[A-Za-z0-9\-]+$/'],
            'idType'                  => ['nullable', 'string', 'in:Omang,Passport'],
            'dob'                     => ['required', 'date'],
            'gender'                  => ['required', 'string', 'in:Male,Female,M,F'],
            'maritalStatus'           => ['nullable', 'string', 'max:32'],
            'sourceOfIncome'          => ['nullable', 'string', 'in:unemployed,employment,pensioner_retired,bussiness,inheritance,gifts,investments,dividends,rental,other'],
            'sourceOfIncomeDetails'   => ['nullable', 'array'],
            'sourceOfIncomeDetails.*' => ['nullable', 'string', 'max:255'],
            'phone'                   => ['required', 'string', 'regex:/^[0-9]{8}$/'],
            'email'                   => ['nullable', 'email', 'max:160'],
            'address'                 => ['nullable', 'string', 'max:160'],
            'state'                   => ['nullable'],
            'city'                    => ['nullable'],
            'nationality'             => ['nullable', 'string', 'max:100', new \AlphaDirect\Rules\NotSanctionedCountry()],
            'occupation'              => ['nullable', 'string', 'max:100'],
            'occupationLevel'         => ['nullable', 'string', 'in:Senior,Middle,Junior,Unemployed'],
            'employerName'            => ['nullable', 'string', 'max:50'],
            'country'                 => ['nullable', 'string', 'max:100', new \AlphaDirect\Rules\NotSanctionedCountry()],
            'plotNumber'              => ['nullable', 'string', 'max:120'],
            'isPep'                   => ['nullable', 'boolean'],
            'pepType'                 => ['nullable', 'string', 'max:255'],
            'isPepRelated'            => ['nullable', 'boolean'],
            'pepRelationship'         => ['nullable', 'string', 'max:50'],
            'pepRelationshipSpecify'  => ['nullable', 'string', 'max:255'],
            'planId'                  => ['required', 'integer', 'in:' . $allowedPlans],
            'paymentMethod'           => ['required', 'string', 'in:DPO,RealPay,VCS,Orange,Flutterwave'],

            // Broker assist — Agent ID is persisted to policies.agent_id and the
            // selected store to policies.storeID; the PIN is a credential and is
            // accepted but never stored (mirrors LegalInsuranceController).
            'assistedByBroker'        => ['nullable', 'boolean'],
            'brokerAgentId'           => ['nullable', 'string', 'max:16'],
            'brokerAgentPin'          => ['nullable', 'string', 'max:16'],
            'storeId'                 => ['nullable', 'integer'],

            // Driver's licence (BUG-033 / 034 / 041 / 042) — persisted to
            // customer_profile (driving_license / license_class /
            // license_valid_from / license_valid_to). All optional so the
            // create path stays backward-compatible with callers that omit them.
            'drivingLicenseNumber'    => ['nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9\- ]+$/'],
            'licenseClass'            => ['nullable', 'string', 'max:20'],
            'licenseValidFrom'        => ['nullable', 'date'],
            'licenseValidTo'          => ['nullable', 'date'],

            // Employer details (BUG-035) — persisted to customer_profile
            // (e_name / emp_no / emp_phone / salary_pay_date), mirroring
            // LegalInsuranceController.
            'employer'                => ['nullable', 'array'],
            'employer.employerName'   => ['nullable', 'string', 'max:50'],
            'employer.employeeNo'     => ['nullable', 'string', 'max:12'],
            'employer.employerTel'    => ['nullable', 'string', 'max:12'],
            'employer.salaryPayDate'  => ['nullable', 'string', 'max:10'],

            // Spouse / life partner (BUG-036) — captured as a policy_beneficiary
            // row when supplied, mirroring LegalInsuranceController. Optional for
            // TP car (unlike Legal, where every policy carries a spouse).
            'spouse'                  => ['nullable', 'array'],
            'spouse.firstName'        => ['required_with:spouse', 'string', 'max:60'],
            'spouse.lastName'         => ['required_with:spouse', 'string', 'max:60'],
            'spouse.middleName'       => ['nullable', 'string', 'max:60'],
            'spouse.dob'              => ['required_with:spouse', 'date'],
            'spouse.gender'           => ['required_with:spouse', 'string', 'in:Male,Female,M,F'],
            'spouse.omang'            => ['nullable', 'string', 'regex:/^[0-9]{9}$/'],
            'spouse.passport'         => ['nullable', 'string'],
            'spouse.cellphone'        => ['nullable', 'string'],
            'spouse.email'            => ['nullable', 'email', 'max:160'],
            'spouse.omangExpiry'      => ['nullable', 'date'],
            'spouse.passportExpiry'   => ['nullable', 'date'],

            'vehicle'                 => ['required', 'array'],
            'vehicle.plate'           => ['required', 'string', 'max:20', 'regex:/^[Bb]\s?\d{3}\s?[A-Za-z]{3}$/'],
            'vehicle.make'            => ['required', 'string', 'max:60'],
            'vehicle.model'           => ['nullable', 'string', 'max:80'],
            'vehicle.year'            => ['required', 'digits:4', 'integer', 'min:' . $minYear, 'max:' . $maxYear],
        ];
    }
}
