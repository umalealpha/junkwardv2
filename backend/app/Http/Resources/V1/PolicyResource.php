<?php

namespace AlphaDirect\Http\Resources\V1;

use AlphaDirect\City;
use AlphaDirect\Helpers\PiiMask;
use AlphaDirect\Models\PolicyAction;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

class PolicyResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'              => $this->id,
            'policyNumber'    => $this->policyNumber,
            // GFS / Portal policy number — the originating portal reference.
            // Data dept needs it on the policy view to build accurate claims
            // history against the source system (Babusi, 2026-06-15 #1).
            'gfsPolicyNo'     => $this->gfs_policy_no,
            'status'          => $this->status,
            'is_draft'        => (bool) $this->is_draft,
            'statusLabel'     => $this->statusLabel(),
            'premium'         => $this->premium,
            'firstPremium'    => $this->first_premium,
            'sumAssured'      => $this->sum_assured,
            // POLICY-level frequency — the authoritative policies.premium_freq
            // column, written straight from the Edit Policy screen. The frequency
            // in force on any single transaction is a separate, per-action value
            // (policy_actions.current_frequency_id) and is served by
            // PolicyController::actions, not from here.
            'premiumFreq'     => $this->premium_freq,
            'premiumFreqLabel'=> PolicyAction::frequencyLabel($this->premium_freq),
            'vat'             => $this->vat,
            // start_date/end_date columns are only populated on some
            // legacy flows; MIS + newer V2 policies leave them NULL and
            // store the real dates on term_start_date / expiry_date /
            // billingStartDate / policyActivatedDate. Fall through the
            // chain so the Policy Detail card always shows something
            // meaningful.
            //
            // These columns are NOT in Policy::$dates (only policyActivatedDate
            // is) and PolicyController::show hydrates via setRawAttributes(),
            // so they arrive here as plain strings ('2026-08-16'). optional()
            // returns null for any non-object, so the previous
            // optional($string)->toIso8601String() silently emitted null for
            // every policy that HAS a term — which is why the Policy Actions
            // tab fell through to the action's own effective_to and showed the
            // wrong expiry year. Normalise through Carbon instead.
            'startDate'       => $this->isoDate(
                $this->start_date
                ?? $this->term_start_date
                ?? $this->billingStartDate
                ?? $this->policyActivatedDate
            ),
            'endDate'         => $this->isoDate(
                $this->end_date
                ?? $this->expiry_date
                ?? (
                    ($s = $this->start_date ?? $this->term_start_date ?? $this->billingStartDate ?? $this->policyActivatedDate)
                        ? \Carbon\Carbon::parse($s)->addYear()->subDay()
                        : null
                )
            ),
            'billingStartDate'=> $this->billingStartDate,
            'policyActivatedDate' => optional($this->policyActivatedDate)->toIso8601String(),
            'latestAction'    => $this->latest_action_id ? [
                'id'              => $this->latest_action_id,
                'effectiveFrom'   => $this->latest_action_effective_from,
                'effectiveTo'     => $this->latest_action_effective_to,
                'transactionType' => $this->latest_action_transaction_type,
            ] : null,
            'createdAt'       => optional($this->created_at)->toIso8601String(),
            'updatedAt'       => optional($this->updated_at)->toIso8601String(),
            'quoteNumber'     => $this->quoteNumber,
            'activationCode'  => $this->activation_code,
            'note'            => $this->note,
            'isBundled'       => (bool) $this->is_bundled,
            'bundledDiscountRate'   => $this->bundled_discount_rate,
            'bundledDiscountAmount' => $this->bundled_discount_amount,
            'isReinstate'     => (bool) $this->is_reinstate,
            'hasVehicle'      => (bool) $this->has_vehicle,
            'hasMember'       => (bool) $this->has_member,

            // Additional fields from Blade detail view
            'leadSource'      => $this->leadSource,
            'policyType'      => $this->policyType,
            'agencyName'      => $this->whenLoaded('agency', fn() => $this->agency->name ?? null),
            'storeName'       => $this->whenLoaded('store', fn() => $this->store->name ?? null),
            'cancelledDate'   => $this->cancelled_date_value ?? null,
            'cancelledBy'     => $this->cancelled_by_value ?? null,
            'complianceLabel' => $this->whenLoaded('kyc', fn() => $this->complianceLabel()),
            'billingType'     => $this->whenLoaded('customer', function () {
                return $this->customer->banking->billing ?? null;
            }),
            // True if the policy has ANY DPO scheduled transactions (current OR
            // historical) — drives the Schedule Transactions tab so the history
            // stays visible after a customer switches payment method. Detail-only
            // resource, so this single indexed exists() is not an N+1 risk.
            'hasDpoSchedule'  => DB::table('scheduled_transactions')
                ->where('policy_number', $this->policyNumber)
                ->exists(),
            'policyCreatedBy' => $this->whenLoaded('user', fn() =>
                trim(($this->user->firstName ?? '') . ' ' . ($this->user->lastName ?? ''))
            ),

            // Relationships
            'customer'        => new CustomerResource($this->whenLoaded('customer')),
            'product'         => new ProductResource($this->whenLoaded('product')),
            'plan'            => $this->whenLoaded('plan', fn() => [
                'id'          => $this->plan->id,
                'name'        => $this->plan->name,
                'sumAssured'  => $this->plan->sum_assured,
                'premium'     => $this->plan->premium ? round($this->plan->premium * 1.14, 2) : $this->plan->premium,
            ]),
            'agent'           => $this->whenLoaded('user', fn() => [
                'id'          => $this->user->id,
                'name'        => trim(($this->user->firstName ?? '') . ' ' . ($this->user->lastName ?? '')),
                'email'       => $this->user->email, // internal staff/agent email — not customer PII, keep full
            ]),
            'vehicle'         => $this->whenLoaded('vehicle', fn() => $this->formatVehicle($this->vehicle)),
            'vehicles'        => $this->whenLoaded('PolicyVehicle', fn() =>
                $this->PolicyVehicle->map(fn($v) => $this->formatVehicle($v))
            ),
            'members'         => $this->whenLoaded('policyMembers', fn() =>
                $this->policyMembers->map(fn($m) => [
                    'id'       => $m->id,
                    'relation' => $m->relation,
                    'firstName'=> PiiMask::ifName($m->first_name),
                    'lastName' => PiiMask::ifName($m->last_name),
                    'dob'      => PiiMask::ifHidden($m->dob),
                    'gender'   => $m->gender == 0 ? 'Female' : 'Male',
                ])
            ),
            'beneficiaries'   => $this->whenLoaded('policyBeneficiaries', fn() =>
                $this->policyBeneficiaries->map(fn($b) => [
                    'id'       => $b->id,
                    'firstName'=> PiiMask::ifName($b->first_name),
                    'lastName' => PiiMask::ifName($b->last_name),
                    'relation' => $b->relation,
                    'dob'      => PiiMask::ifHidden($b->dob),
                    'gender'   => $b->gender == 0 ? 'Female' : 'Male',
                ])
            ),
            'devices'         => $this->whenLoaded('devices', fn() =>
                $this->devices->map(fn($d) => [
                    'id'        => $d->id,
                    'deviceType'=> $d->device_type,
                    'imei'      => $d->imei,
                    'make'      => $d->cell_phone_make,
                    'model'     => $d->cell_phone_model,
                    'value'     => $d->phone_value,
                    'status'    => $d->status,
                ])
            ),
            'coverages'       => $this->whenLoaded('coverage', fn() =>
                $this->coverage->map(fn($c) => [
                    'id'       => $c->id,
                    'vehicleId'=> $c->vehicle_id,
                    'main'     => $c->main,
                    'value'    => $c->coverage_value,
                    'discount' => $c->discount,
                    'type'     => $c->type,
                ])
            ),
            'claims'          => $this->whenLoaded('claim', fn() =>
                ClaimResource::collection($this->claim)
            ),
            'transactions'    => $this->whenLoaded('transactions', fn() =>
                $this->transactions->take(50)->map(fn($t) => [
                    'id'        => $t->id,
                    'reference' => $t->reference ?? $t->transactionId,
                    'amount'    => $t->amount,
                    'status'    => $t->status,
                    'date'      => optional($t->created_at)->toIso8601String(),
                    'method'    => $t->payment_method ?? $t->paymentMethod ?? null,
                ])
            ),
            'riskAddresses'   => $this->whenLoaded('riskAddress', fn() =>
                $this->riskAddress->map(fn($r) => [
                    'id'       => $r->id,
                    'address'  => $r->address ?? $r->risk_address,
                    'city'     => $r->city,
                    'state'    => $r->state,
                    'zipCode'  => $r->zip_code,
                ])
            ),
            'kyc'             => $this->whenLoaded('kyc', fn() => [
                'compliance'       => $this->kyc?->compliance,
                'complianceLabel'  => $this->complianceLabel(),
                'omang'            => (bool) $this->kyc?->omang,
                'omangBack'        => (bool) $this->kyc?->omangBack,
                'passport'         => (bool) $this->kyc?->passport,
                'passportBack'     => (bool) $this->kyc?->passport_back,
                'drivingLicense'   => (bool) $this->kyc?->driving_license,
                'drivingLicenseBack' => (bool) $this->kyc?->driving_license_back,
                'proofOfResidence' => (bool) $this->kyc?->proof_residence,
                'proofOfIncome'    => (bool) $this->kyc?->proof_income,
                'debitAuthorizationForm' => (bool) $this->kyc?->debit_authorization_form,
                'bankStatement'    => (bool) $this->kyc?->bank_statement_file_path,
            ]),
            'profile'         => $this->whenLoaded('profile', fn() => [
                'address'     => PiiMask::ifHidden($this->profile->address),
                'omang'       => PiiMask::ifId($this->profile->omang),
                'dob'         => PiiMask::ifHidden($this->profile->dob),
                'passport'    => PiiMask::ifId($this->profile->passport),
                'maritalStatus' => $this->maritalStatusLabel($this->profile->maritalstatus),
                'drivingLicense'=> PiiMask::ifId($this->profile->driving_license_number),
                'licenseValidTill' => $this->profile->license_valid_till,
                'city'        => $this->resolveCityName($this->profile->city),
                'state'       => $this->resolveStateName($this->profile->city),
                // Raw FK ids so the Customer-tab edit form can prefill the
                // State/District + City dropdowns. stateId is derived from the
                // stored city's parent state (the display 'state' above uses the
                // same source), falling back to the profile's own state column.
                'cityId'      => is_numeric($this->profile->city) ? (int) $this->profile->city : null,
                'stateId'     => $this->resolveStateId($this->profile->city, $this->profile->state),
                'gender'      => $this->profile->gender == 1 ? 'Male' : 'Female',
                // Nationality now has its own column; fall back to the legacy
                // countryId for records created before the column existed.
                'nationality' => $this->profile->nationality ?: $this->profile->countryId,
                'occupation'      => $this->profile->occupation,
                'occupationLevel' => $this->profile->occupation_level,
                'country'         => $this->profile->country,
                'sourceOfIncome'      => $this->formatSourceOfIncome($this->profile->sourceOfIncome),
                'sourceOfIncomeKey'   => $this->extractSourceOfIncomeKey($this->profile->sourceOfIncome),
                'sourceOfIncomeRaw'   => $this->profile->sourceOfIncome,
                'taxIdNumber' => PiiMask::ifId($this->profile->tax_id),
                'employerName'=> $this->profile->e_name,
                // Prominent/Influential Person (PEP) declarations.
                'isPep'                  => (bool) $this->profile->is_pep,
                'pepType'                => $this->profile->pep_type,
                'isPepRelated'           => (bool) $this->profile->is_pep_related,
                'pepRelationship'        => $this->profile->pep_relationship,
                'pepRelationshipSpecify' => $this->profile->pep_relationship_specify,
                // High-risk country (AML watch-list) — see PolicyController::show.
                'isHighRiskCountry'      => (bool) ($this->profile->is_high_risk_country ?? false),
                'highRiskCountryName'    => $this->profile->high_risk_country_name ?? null,
                'employeeNo'  => $this->profile->emp_no,
                'employerPhone'=> PiiMask::ifPhone($this->profile->emp_phone),
                'salaryPayDate'=> $this->profile->salary_pay_date,
                'entityType'  => $this->profile->entity_type ?? null,
                'companyName' => $this->profile->company->name ?? null,
                'companyVatNumber'          => $this->profile->company->VAT_registration_number ?? null,
                'companyRegistrationNumber' => $this->profile->company->company_registration_number ?? null,

                // KYC check fields (underlying DB columns: insure, about_alpha,
                // decline_proposal, refused_policy, cancel_policy). Before
                // this they weren't surfaced — the Customer tab showed blank
                // even when operators had filled them in on the edit wizard.
                'currentlyInsured' => $this->profile->insure ?? null,
                'hearAboutAlpha'   => $this->profile->about_alpha ?? null,
                'declineProposal'  => (bool) ($this->profile->decline_proposal ?? false),
                'refusedPolicy'    => (bool) ($this->profile->refused_policy ?? false),
                'cancelPolicy'     => (bool) ($this->profile->cancel_policy ?? false),
            ]),
            'banking'         => $this->whenLoaded('customer', function () {
                $banking = $this->customer->banking ?? null;
                if (!$banking) return null;
                return [
                    'bankName'        => PiiMask::ifBankName($banking->bankName ?? $banking->bank_name),
                    'accountHolder'   => $banking->accountHolderName,
                    'accountNumber'   => PiiMask::ifBankAccount($banking->accountNumber),
                    'accountType'     => $this->accountTypeLabel($banking->accountType),
                    'branchCode'      => PiiMask::ifBranchCode($banking->branchCode ?? $banking->bank_branch),
                    'billing'         => $banking->billing,
                    'billingCell'     => $banking->billingCell,
                    'cardType'        => $banking->cardType,
                    'cardHolderName'  => $banking->cardHolderName,
                    'subscriptionId'  => $banking->subscriptionId,
                    'orangeMoney'     => $banking->orangeMoney,
                    'myzaka'          => $banking->myzaka,
                ];
            }),
        ];
    }

    /**
     * Normalise a policy date to ISO-8601, whatever shape it arrives in.
     *
     * Policy date columns are un-cast (see Policy::$dates) and the detail
     * endpoint hydrates with setRawAttributes(), so a value can be a Carbon,
     * a 'Y-m-d' / 'Y-m-d H:i:s' string, or the zero-date '0000-00-00' that
     * legacy MIS rows carry. Returns null for anything unparseable rather
     * than throwing — callers treat null as "no term recorded".
     */
    private function isoDate($value): ?string
    {
        if (empty($value)) {
            return null;
        }
        if ($value instanceof \DateTimeInterface) {
            return \Carbon\Carbon::instance($value)->toIso8601String();
        }
        if (str_starts_with((string) $value, '0000-00-00')) {
            return null;
        }
        try {
            return \Carbon\Carbon::parse($value)->toIso8601String();
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function statusLabel(): string
    {
        return match((int) $this->status) {
            1       => 'active',
            2       => 'cancelled',
            3       => 'expired',
            default => 'in-active',
        };
    }

    private function maritalStatusLabel($status): string
    {
        return match((int) $status) {
            1 => 'Single',
            2 => 'Married',
            3 => 'Divorced',
            4 => 'Widowed',
            5, 6 => 'Living Together',
            default => 'Unknown',
        };
    }

    private function complianceLabel(): string
    {
        return match((int) ($this->kyc?->compliance ?? -1)) {
            0       => 'KYC Verification Pending',
            1       => 'KYC Compliant',
            3       => 'No ID - No Documents',
            default => 'KYC Non Compliant',
        };
    }

    private function accountTypeLabel($type): string
    {
        return match((string) $type) {
            '1'     => 'Cheque Account',
            '2'     => 'Savings Account',
            default => $type ?? 'Unknown',
        };
    }

    /**
     * Return the raw category key — "employment" / "bussiness" / "unemployed"
     * — so the frontend can match it against the lookup option id and
     * pre-select the dropdown. formatSourceOfIncome is for human display;
     * this is for form hydration.
     */
    private function extractSourceOfIncomeKey($value): ?string
    {
        if (empty($value)) return null;
        if (is_string($value)) {
            $trim = trim($value);
            if ($trim === '') return null;
            if ($trim[0] === '{' || $trim[0] === '[') {
                $decoded = json_decode($trim, true);
                if (is_array($decoded) && !empty($decoded)) {
                    return array_key_first($decoded);
                }
            }
            return $value;
        }
        if (is_array($value) && !empty($value)) {
            return array_key_first($value);
        }
        return null;
    }

    private function formatSourceOfIncome($value): ?string
    {
        if (empty($value)) return null;

        // Strings can be either a plain category (e.g. "unemployed") or a
        // JSON-encoded object like '{"employment":{"monthly_salary":"5000"}}'.
        // Decode first if the string parses as JSON, otherwise return it as
        // the display label directly.
        if (is_string($value)) {
            $trim = trim($value);
            if ($trim !== '' && ($trim[0] === '{' || $trim[0] === '[')) {
                $decoded = json_decode($trim, true);
                if (is_array($decoded)) {
                    $value = $decoded;
                } else {
                    return ucfirst(str_replace('_', ' ', $value));
                }
            } else {
                return ucfirst(str_replace('_', ' ', $value));
            }
        }

        // JSON object with one top-level key (the category) and nested details.
        $data  = is_array($value) ? $value : (array) $value;
        $parts = [];
        foreach ($data as $category => $details) {
            $label = ucfirst(str_replace('_', ' ', (string) $category));
            if (is_array($details) || is_object($details)) {
                $answers = [];
                foreach ((array) $details as $q => $a) {
                    if ($a !== null && $a !== '') $answers[] = (string) $a;
                }
                $parts[] = $answers ? ($label . ': ' . implode(', ', $answers)) : $label;
            } else {
                $parts[] = ($details === null || $details === '')
                    ? $label
                    : "$label: $details";
            }
        }
        return implode(' — ', $parts) ?: null;
    }

    private function resolveCityName($cityId): ?string
    {
        if (empty($cityId) || !is_numeric($cityId)) {
            return $cityId;
        }

        $city = City::find($cityId);
        return $city->name ?? $cityId;
    }

    private function resolveStateName($cityId): ?string
    {
        if (empty($cityId) || !is_numeric($cityId)) {
            return null;
        }

        $city = City::with('state')->find($cityId);
        return $city->state->name ?? null;
    }

    /**
     * Parent-state id of the stored city (so the edit form can preselect the
     * State/District dropdown), falling back to the profile's own state column
     * when the city has no resolvable state.
     */
    private function resolveStateId($cityId, $fallbackStateId = null): ?int
    {
        if (is_numeric($cityId)) {
            $city = City::with('state')->find($cityId);
            if ($city && $city->state) {
                return (int) $city->state->id;
            }
        }
        return is_numeric($fallbackStateId) ? (int) $fallbackStateId : null;
    }

    private function formatVehicle($v): array
    {
        return [
            'id'                => $v->id,
            'vehiclePlate'      => $v->vehiclePlate,
            'chassisNo'         => $v->vinnumber ?? $v->chassisNo,
            'odometer'          => $v->odometer,
            'purpose'           => $v->purpose,
            'condition'         => $v->condition,
            'make'              => $v->make,
            'model'             => $v->model,
            'engineNo'          => $v->engineNo,
            'seats'             => $v->seats,
            'cylinders'         => $v->cylinders,
            'year'              => $v->year,
            'colour'            => $v->colour,
            'bodyType'          => $v->bodyType,
            'fuelType'          => $v->fuelType,
            'transmission'      => $v->transmission,
            'estimatedValue'    => $v->estimatedValue,
            'registeredOwner'   => $v->registeredOwner ?? $v->registeredowner,
            'registrationNumber'=> $v->registrationNumber ?? $v->registrationnumber,
            'antiTheftDevice'   => $v->antiTheftDevice,
            'trackerDevice'     => $v->trackerDevice ?? $v->trackerdevice,
            'trackerDeviceType' => $v->trackerDeviceType,
            'isImported'        => (bool) $v->is_imported,
            'status'            => $v->status,
        ];
    }
}
