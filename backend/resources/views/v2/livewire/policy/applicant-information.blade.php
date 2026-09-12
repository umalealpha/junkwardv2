<div>
    @if($Application_Update)
    <div class="">
        <div class="row g-5 g-xl-10 mb-5 mb-xl-0">
            <div class="col-md-4 col-lg-4 col-xl-4 col-xxl-3">
                <div class="card card-flush h-md-50 mb-5 mb-xl-10">
                    <div class="card-body pt-5">
                        <div class="d-flex flex-stack">
                            <p class="fw-bold fs-6">Term Start Date : </p>
                            <div class="justify-content-end">
                                <p>{{ $this->term_start_date ?? ""}}</p>
                            </div>
                        </div>
                        <div class="separator separator-dashed my-3"></div>
                        <div class="d-flex flex-stack">
                            <p class="fw-bold fs-6">Transaction Eff. Dt : </p>
                            <div class="justify-content-end">
                                <p>
                                    @if($this->policyAction != null && $this->policyAction->effective_from != null)
                                    {{ (new Carbon($this->policyAction->effective_from))->format(config('constants.date.format')) }}
                                    @endif
                                </p>
                            </div>
                        </div>
                        <div class="separator separator-dashed my-3"></div>
                        <div class="d-flex flex-stack">
                            <p class="fw-bold fs-6">Transaction Type : </p>
                            <div class="justify-content-end">
                                <p>{{ $this->policyAction->transaction_type ?? ""}}</p>
                            </div>
                        </div>
                        <div class="separator separator-dashed my-3"></div>
                        <div class="d-flex flex-stack">
                            <p class="fw-bold fs-6">Transaction Reason : </p>
                            <div class="justify-content-end">
                                    <p>{{ $this->policyAction->transaction_reason ?? ""}}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4 col-lg-4 col-xl-4 col-xxl-3">
                <div class="card card-flush h-md-50 mb-5 mb-xl-10">
                    <div class="card-body pt-5">
                        <div class="d-flex flex-stack">
                            <p class="fw-bold fs-6">Term End Date : </p>
                            <div class="justify-content-end">
                                <p>{{ $this->policies_expiry_date ?? ""}}</p>
                            </div>
                        </div>
                        <div class="separator separator-dashed my-3"></div>
                        <div class="d-flex flex-stack">
                            <p class="fw-bold fs-6">Transaction Exp. Dt. : </p>
                            <div class="justify-content-end">
                                <p>
                                    @if($this->policyAction != null && $this->policyAction->effective_to != null)
                                    {{ (new Carbon($this->policyAction->effective_to))->format(config('constants.date.format')) }}
                                    @endif
                                </p>
                            </div>
                        </div>
                        <div class="separator separator-dashed my-3"></div>
                        <div class="d-flex flex-stack">
                            <p class="fw-bold fs-6">Transaction Status : </p>
                            <div class="justify-content-end">
                                <p>{{ $this->policyAction->status ?? ""}}</p>
                            </div>
                        </div>
                        <div class="separator separator-dashed my-3"></div>
                        <div class="d-flex flex-stack">
                            <p class="fw-bold fs-6">Transaction Note : </p>
                            <div class="justify-content-end">
                                <p>{{ $this->policyAction->note ?? ""}}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4 col-lg-4 col-xl-4 col-xxl-3">
                <div class="card card-flush h-md-50 mb-5 mb-xl-10">
                    <div class="card-body pt-5">
                        <div class="d-flex flex-stack">
                            <p class="fw-bold fs-6">Transaction Date : </p>
                            <div class="justify-content-end">
                                <p>
                                    @if($this->policyAction != null && $this->policyAction->transaction_date != null)
                                    {{ (new Carbon($this->policyAction->transaction_date))->format(config('constants.date.format')) }}
                                    @endif
                                </p>
                            </div>
                        </div>

                        <div class="separator separator-dashed my-3"></div>
                        <div class="d-flex flex-stack">
                            <p class="fw-bold fs-6">Premium : </p>
                            <div class="justify-content-end">
                                <p> P {{ number_format((float)$this->policyAction->premium ?? "", 2, '.', ',') }}</p>
                            </div>
                        </div>
                        <div class="separator separator-dashed my-3"></div>
                        <div class="d-flex flex-stack">
                            <p class="fw-bold fs-6">Premium Change : </p>
                            <div class="justify-content-end">
                                @php
                                    $premium_changed = ($this->policies->annual_premium) - ($this->policyAction->premium);
                                @endphp
                                <p>P {{ number_format((float)$premium_changed ?? "", 2, '.', ',') }} </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- ($this->newActionDates->status == "QUOTE" && in_array($this->newActionDates->transaction_type, ["ANNIVERSARY-RENEW", "REINSTATE", "REISSUE"])) -->

            @if(($this->policyAction->status == "QUOTE" && in_array($this->policyAction->transaction_type, ["NEWBUSINESS", "REINSTATE", "REISSUE","ANNIVERSARY-RENEW"])) || ($this->newActionDates->status == "QUOTE" && in_array($this->newActionDates->transaction_type, ["ANNIVERSARY-RENEW", "REINSTATE", "REISSUE"])))
            <div class="col-sm-3">
                <div class="form-floating mb-7">
                    @php
                        $premiumFreqOptions = $this->getPremiumFreqPropert();
                        if (in_array((int) ($policies->product_id ?? 0), [7, 8], true)) {
                            $premiumFreqOptions = ["1" => "MONTHLY","3" => "ANNUAL","5" => "QUARTERLY"];
                        }
                        // Company-only products (20 Commercial Liabilities /
                        // 23 Guarantee / 24 Miscellaneous) are ANNUAL term
                        // only — they previously fell through to the MANUAL
                        // INPUT default below, which is not a valid term for
                        // them. See Support\CompanyOnlyProducts.
                        elseif (\AlphaDirect\Support\CompanyOnlyProducts::includes($policies->product_id ?? null)) {
                            $premiumFreqOptions = \AlphaDirect\Support\CompanyOnlyProducts::freqOptions($policies->product_id);
                        }
                        else {
                            $premiumFreqOptions = ["6" => "MANUAL INPUT"];
                        }
                    @endphp
                    <x-select
                        wire:model.lazy="policies.premium_freq"
                        id="pfreq"
                        :options="$premiumFreqOptions"
                        disabled="{{ !$this->editable }}"
                    />
                    <x-form-label for="premium_freq" required value="{{ __('Renewal Plan:') }}"  autocomplete="policies.premium_freq" />
                    <x-form-input-error name="policies.premium_freq"/>
                </div>
            </div>
           
            <div class="col-sm-3">
                <div class="form-floating mb-3">
                    <x-form-date type="text" class="kt_datepicker_1" id="term_start_date" wire:model.lazy="term_start_date"  placeholder="Effective From" disabled="{{ !$this->editable }}" autocomplete="off"/>
                    <x-form-label for="term_start_date" required value="{{ __('Effective From') }}"/>
                    <x-form-input-error name="term_start_date"/>
                </div>
            </div>
            <div class="col-sm-3">
                <div class="form-floating mb-3">
                    @if(($policies->premium_freq ?? '') == "6")
                        <x-form-date type="text" class="kt_datepicker_1" id="term_end_date" wire:model.lazy="policies_expiry_date" placeholder="Effective To" disabled="{{ !$this->editable }}" autocomplete="off"/>
                    @else
                        <x-form-input type="text" readonly wire:model.defer="policies_expiry_date" placeholder="Effective To" disabled="{{ !$this->editable }}" autocomplete="off"/>
                    @endif
                    <x-form-label for="policies_expiry_date" required value="{{ __('Effective To') }}"/>
                    <x-form-input-error name="policies_expiry_date"/>
                </div>
            </div>
            @endif
            {{-- <div class="col-sm-3">
                <div class="form-floating mb-3">
                    <x-select id="select_product" data-placeholder="Select an option"
                        aria-label="Select Product" wire:model.defer="customer_profile.select_product" wire:model.lazy="customer_profile.select_product"
                        :options="array('Commercial All Risk'=>'Commercial All Risk','Domestic All Risk'=>'Domestic All Risk')" disabled="{{ !$this->editable }}"
                    />
                    <x-form-label for="select_product" required value="{{ __('Select Product') }}"/>
                    <x-form-input-error name="customer_profile.select_product"/>
                </div>
            </div> --}}
            <div class="col-sm-3">
                <div class="form-floating mb-3">
                    <x-select-search id="selectedAgency" wire:model.lazy="policies.agency_id" aria-label="Select Agency"
                        :options="$this->getAgency()"  disabled="{{ !$this->editable }}" />
                    <x-form-label for="selectedAgency" required value="{{ __('Select Agency') }}" />
                    <x-form-input-error name="selectedAgency"/>
                </div>
            </div>
            <div class="col-sm-3">
                <div class="form-floating mb-3">
                    <x-select-search id="agent_id"
                        aria-label="Select Agent"
                        :options="$this->agents"
                        listner="selectedAgency"
                        wire:model.defer='policies.agent_id'
                        disabled="{{ !$this->editable }}"
                    />
                    <x-form-label for="policies.agent_id" required value="{{ __('Select Agent') }}" />
                    <x-form-input-error name="policies.agent_id"/>
                </div>
            </div>
            <div class="col-sm-3">
                <div class="form-floating mb-3">
                    <x-select id="uw_app_status" wire:model.defer="policies.uw_app_status"
                        aria-label="UW. App. Status"
                        :options="array(
                            'APPWITHDRAWNUN'=>'App withdrawn, unacceptable risk',
                            'APPWITHDRAWNPREUP'=>'App withdrawn, premium uprate',
                            'AGENTSUBERROR'=>'Agent submitted in error',
                            'APPDIDNOTACCEPT'=>'Applicant did not accept the quote',
                            'AGENTREQUESTCHANGE'=>'Agent requests changes to quote',
                            'PENDINGUW'=>'Pending UW Review',
                            'APPROVEDUW'=>'Approved by UW',
                            'NEEDIFNO'=>'Needs Info',
                            'UNACCEPTABLE'=>'Unacceptable',
                            'UWOPEN'=>'Open'
                            )" disabled="{{ !$this->editable }}"
                    />
                    <x-form-label for="uw_app_status" value="{{ __('UW. App. Status') }}"/>
                    <x-form-input-error name="policies.uw_app_status"/>
                </div>
            </div>

            <div class="col-sm-3">
                <div class="form-floating mb-3">
                    <x-form-input type="text" name="gfs_policy_no" wire:model.defer="policies.gfs_policy_no" placeholder="GFS Policy No" disabled="{{ !$this->editable }}" autocomplete="off"/>
                    <x-form-label for="gfs_policy_no" value="{{ __('GFS Policy No:') }}"/>
                    <x-form-input-error name="policies.gfs_policy_no"/>
                </div>
            </div>

        </div>
    </div>
    <hr>
    @endif

    <div x-data="{
            'entity_type':@entangle('customer_profile.entity_type'),
            {{-- 'select_product':@entangle('customer_profile.select_product'), --}}
            'product_id':@entangle('policies.product_id'),
            'sourceOfIncome':@entangle('sourceOfIncome'),
        }">
        @php
            $productOptions = $this->getProducts();

            if (($this->policies->premium_freq ?? '') === '6') {
                $productOptions = $productOptions->reject(fn ($p) => in_array((int)$p['id'], [7, 8], true));
            } else {
                $productOptions = $productOptions->reject(fn ($p) => in_array((int)$p['id'], [16, 17,18,19], true));
            }
            
        @endphp

        <div class="row mt-5">
            <div class="col-sm-6">
                <div class="form-floating mb-3">
                    <x-select-search id="product_id" wire:model="policies.product_id" aria-label="Please choose the product"
                        :options="$productOptions" disabled="{{ !$this->editable }}" />
                    <x-form-label for="product_id" required value="{{ __('Please choose the product') }}"  />
                    <x-form-input-error name="policies.product_id"/>
                </div>
            </div>
            <div class="col-sm-6">
                <div class="form-floating mb-3">
                    <x-select-search id="plan_id"
                        aria-label="Select Product Plans"
                        :options="$this->allPlan ?? []"
                        listner="product_id"
                        wire:model.defer='policies.plan_id' disabled="{{ !$this->editable }}" />
                    <x-form-label for="plan_id" required value="{{ __('Select Product Plans') }}"  />
                    <x-form-input-error name="policies.plan_id"/>
                </div>
            </div>
        </div>
        <div>
            <div class="row">
                <div class="col-sm-6">
                    <div class="form-floating mb-3">
                        @php
                            // Guarantee (23) / Miscellaneous (24) are held by an
                            // Organisation — Person is not offered at all. The
                            // individual-detail blocks below are already keyed off
                            // x-show="entity_type=='Person'", so locking the type
                            // here also removes the customer-details capture.
                            // Commercial Liabilities (20) is NOT locked: per UW
                            // (2026-08-26) it takes an Individual or an
                            // Organisation, so read entityLocked() not includes().
                            $entityTypeOptions = \AlphaDirect\Support\CompanyOnlyProducts::entityLocked($policies->product_id ?? null)
                                ? array('Organisation'=>'Organisation')
                                : array('Organisation'=>'Organisation','Person'=>'Person');
                        @endphp
                        <x-select wire:model.lazy="customer_profile.entity_type" aria-label="Entity Type"
                            :options="$entityTypeOptions" disabled="{{ !$this->editable }}"
                        />
                        <x-form-label for="entity_type" required value="{{ __('Entity Type:') }}"/>
                        <x-form-input-error name="customer_profile.entity_type"/>
                    </div>
                </div>
                <div class="col-sm-5" x-show="entity_type=='Organisation'">
                    <div class="form-floating mb-3">
                        <x-select wire:model.lazy="customer_profile.company_id" aria-label="company"
                                  :options="$this->companies" disabled="{{ !$this->editable }}" />
                        <x-form-label for="customer_profile.company_id" required value="{{ __('Organisation Name') }}"/>
                        <x-form-input-error name="customer_profile.company_id"/>
                    </div>
                    {{--                    <div class="form-floating mb-3">--}}
                    {{--                        <x-form-input type="text" wire:model.defer="customer_profile.org_name" placeholder="Organisation Name" disabled="{{ !$this->editable }}" />--}}
                    {{--                        <x-form-label for="org_name" required value="{{ __('Organisation Name:') }}"/>--}}
                    {{--                        <x-form-input-error name="customer_profile.org_name"/>--}}
                    {{--                    </div>--}}
                </div>
                @if($this->restricted_editable==0)
                <div class="col-sm-1" x-show="entity_type=='Organisation'">
                    <div class="form-floating mb-3">
                        @livewire('common.add-via-popup', ['title'=>'Add Company','componentName'=>'company.add','modalId'=>'add-company'])
                    </div>
                </div>
                @endif
            </div>
            @if($parentCompany = \AlphaDirect\Models\Company::find($customer_profile->company_id))
                <div class="row m-2"  x-show="entity_type=='Organisation'">
                    @livewire('company.edit-company',['parentCompany'=>$parentCompany,'policy' => $policies], key('edit-company-'.$customer_profile->company_id))
                    @livewire('company.sub-add',['parentCompany'=>$parentCompany], key('sub-add-'.$customer_profile->company_id))
                </div>
            @endif
            <div class="row" x-show="entity_type=='Person'">
                <div class="col-sm-4">
                    <div class="form-floating mb-3">
                        <x-form-input type="text" name="first_name" wire:model.defer="customer.firstName" placeholder="First Name" disabled="{{ !$this->editable }}" autocomplete="off"/>
                        <x-form-label for="first_name" required value="{{ __('First Name:') }}"/>
                        <x-form-input-error name="customer.firstName"/>
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="form-floating mb-3">
                        <x-form-input type="text" name="middle_name" wire:model.defer="customer.middleName" placeholder="Middle Name" disabled="{{ !$this->editable }}" autocomplete="off"/>
                        <x-form-label for="middle_name" value="{{ __('Middle Name:') }}"/>
                        <x-form-input-error name="customer.middleName"/>
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="form-floating mb-3">
                        <x-form-input type="text"  name="last_name" wire:model.defer="customer.lastName" placeholder="Last Name" disabled="{{ !$this->editable }}" autocomplete="off"/>
                        <x-form-label for="last_name" required value="{{ __('Last Name:') }}"/>
                        <x-form-input-error name="customer.lastName"/>
                    </div>
                </div>
            </div>
            <div class="row" x-show="entity_type=='Person'">
                <div class="col-sm-4">
                    <div class="form-floating mb-3">
                        <x-select wire:model.lazy="customer_profile.gender" aria-label="gender"
                        :options="array('0'=>'Female','1'=>'Male')" disabled="{{ !$this->editable }}" />
                        <x-form-label for="gender" required value="{{ __('Gender:') }}"/>
                        <x-form-input-error name="customer_profile.gender"/>
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="form-floating mb-3">
                        <x-select wire:model.lazy="customer_profile.maritalstatus" aria-label="maritalstatus"
                        :options="array('1'=>'Single','2'=>'Married','3'=>'Divorced','4'=>'Widowed','5'=>'Living Together (Not Married)','6'=>'Living Separately')" disabled="{{ !$this->editable }}" />
                        <x-form-label for="maritalstatus" required value="{{ __('Select a Marital Status:') }}"/>
                        <x-form-input-error name="customer_profile.maritalstatus"/>
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="form-floating mb-3">
                        <x-form-date type="text" class="kt_datepicker_1" id="customer_profile_dob" wire:model.defer="customer_profile_dob" placeholder="Date Of Birth" disabled="{{ !$this->editable }}" autocomplete="off"/>
                        <x-form-label for="customer_profile_dob" required value="{{ __('Date Of Birth') }}"/>
                        <x-form-input-error name="customer_profile_dob"/>
                    </div>
                </div>
            </div>
            <div class="row" x-show="entity_type=='Person'">
                <div class="col-sm-4">
                    <div class="form-floating mb-3">
                        <x-select x-model="sourceOfIncome" aria-label="sourceOfIncome"
                        :options="$this->getSourceOfIncome()" disabled="{{ !$this->editable }}" />
                        <x-form-label for="sourceOfIncome" required value="{{ __('Source of Income/Funds:') }}"/>
                        <x-form-input-error name="sourceOfIncome"/>
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="form-floating mb-3">
                        <x-form-input type="text" wire:model.defer="customer_profile.omang" placeholder="Omang ID" disabled="{{ !$this->editable }}" autocomplete="off"/>
                        <x-form-label for="omang" required value="{{ __('Omang ID:') }}"/>
                        <x-form-input-error name="customer_profile.omang"/>
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="form-floating mb-3">
                        <x-form-input type="text"  wire:model.defer="customer_profile.passport" placeholder="Passport" disabled="{{ !$this->editable }}" autocomplete="off"/>
                        <x-form-label for="passport" required value="{{ __('Passport:') }}"/>
                        <x-form-input-error name="customer_profile.passport"/>
                    </div>
                </div>
            </div>
        </div>

        <div class="row" x-show="sourceOfIncome=='employment'">
            <div class="col-sm-6">
                <div class="form-floating mb-3">
                    <x-form-input type="text" wire:model.defer="employment.where_are_you_employed_?"
                    placeholder="Where are you employed ?" disabled="{{ !$this->editable }}" autocomplete="off"/>
                    <x-form-label for="employment.where_are_you_employed_?" required value="{{ __('Where are you employed ?') }}"/>
                </div>
            </div>
            <div class="col-sm-6">
                <div class="form-floating mb-3">
                    <x-form-input type="text"  wire:model.defer="employment.what_is_your_monthly_salary_?" placeholder="What is your monthly salary ?" disabled="{{ !$this->editable }}" autocomplete="off"/>
                    <x-form-label for="employment.what_is_your_monthly_salary_?" required
                    value="{{ __('What is your monthly salary ? ') }}"/>
                </div>
            </div>
        </div>

        <div class="row" x-show="sourceOfIncome=='pensioner_retired'">
            <div class="col-sm-6">
                <div class="form-floating mb-3">
                    <x-form-input type="text" wire:model.defer="employment.how_much_is_your_monthly_pension_"
                    placeholder="How much is your monthly Pension ?" disabled="{{ !$this->editable }}" autocomplete="off"/>
                    <x-form-label for="employment.how_much_is_your_monthly_pension_" required value="{{ __('How much is your monthly Pension ?') }}"/>
                </div>
            </div>
        </div>

        <div class="row" x-show="sourceOfIncome=='bussiness'">
            <div class="col-sm-4">
                <div class="form-floating mb-3">
                    <x-form-input type="text" wire:model.defer="employment.name_of_your_bussiness"
                    placeholder="Name of your business" disabled="{{ !$this->editable }}" autocomplete="off"/>
                    <x-form-label for="employment.name_of_your_bussiness" required value="{{ __('Name of your business') }}"/>
                </div>
            </div>
            <div class="col-sm-4">
                <div class="form-floating mb-3">
                    <x-form-input type="text"  wire:model.defer="employment.bussiness_address" placeholder="Business location/address" disabled="{{ !$this->editable }}" autocomplete="off"/>
                    <x-form-label for="employment.bussiness_address" required
                    value="{{ __('Business location/address') }}"/>
                </div>
            </div>
            <div class="col-sm-4">
                <div class="form-floating mb-3">
                    <x-form-input type="text"  wire:model.defer="employment.what_is_your_monthly_income_?" placeholder="What is your monthly income ?" disabled="{{ !$this->editable }}" autocomplete="off"/>
                    <x-form-label for="employment.what_is_your_monthly_income_?" required
                    value="{{ __('What is your monthly income ?') }}"/>
                </div>
            </div>
        </div>

        <div class="row" x-show="sourceOfIncome=='inheritance'">
            <div class="col-sm-6">
                <div class="form-floating mb-3">
                    <x-form-input type="text" wire:model.defer="employment.who_did_you_inherit_these_funds_from_?"
                    placeholder="Who did you inherit these funds from?" disabled="{{ !$this->editable }}" autocomplete="off"/>
                    <x-form-label for="employment.who_did_you_inherit_these_funds_from_?" required value="{{ __('Who did you inherit these funds from?') }}"/>
                </div>
            </div>
        </div>

        <div class="row" x-show="sourceOfIncome=='gifts'">
            <div class="col-sm-4">
                <div class="form-floating mb-3">
                    <x-form-input type="text" wire:model.defer="employment.who_gifted_you_these_funds_?"
                    placeholder="Who gifted you these funds?" disabled="{{ !$this->editable }}" autocomplete="off"/>
                    <x-form-label for="employment.who_gifted_you_these_funds_?" required value="{{ __('Who gifted you these funds?') }}"/>
                </div>
            </div>
            <div class="col-sm-4">
                <div class="form-floating mb-3">
                    <x-form-input type="text" wire:model.defer="employment.how_much_were_you_gifted_?"
                    placeholder="How much were you gifted?" disabled="{{ !$this->editable }}" autocomplete="off"/>
                    <x-form-label for="employment.how_much_were_you_gifted_?" required value="{{ __('How much were you gifted?') }}"/>
                </div>
            </div>
            <div class="col-sm-4">
                <div class="form-floating mb-3">
                    <x-select id="gift_frequency" data-placeholder="Select an option"
                        aria-label="Select gift frequency" wire:model.defer="employment.gift_frequency"
                        wire:model.lazy="employment.gift_frequency"
                        :options="array('Once off gift'=>'Once off gift','Every month'=>'Every month','Every year'=>'Every year')"
                        disabled="{{ !$this->editable }}"
                    />
                    <x-form-label for="gift_frequency" required value="{{ __('Gift frequency') }}"/>
                    <x-form-input-error name="employment.gift_frequency"/>
                </div>
            </div>
        </div>

        <div class="row" x-show="sourceOfIncome=='investments'">
            <div class="col-sm-6">
                <div class="form-floating mb-3">
                    <x-form-input type="text" wire:model.defer="employment.what_is_the_amount_of_funds_invested_?"
                    placeholder="What is the amount of funds invested?" disabled="{{ !$this->editable }}" autocomplete="off"/>
                    <x-form-label for="employment.what_is_the_amount_of_funds_invested_?" required value="{{ __('What is the amount of funds invested?') }}"/>
                </div>
            </div>
            <div class="col-sm-6">
                <div class="form-floating mb-3">
                    <x-form-input type="text" wire:model.defer="employment.how_much_do_you_earn_from_these_investments_per_month_?"
                    placeholder="How much do you earn from these investments per month?" disabled="{{ !$this->editable }}" autocomplete="off"/>
                    <x-form-label for="employment.how_much_do_you_earn_from_these_investments_per_month_?" required value="{{ __('How much do you earn from these investments per month?') }}"/>
                </div>
            </div>
        </div>
        <div class="row" x-show="entity_type=='Person'">
            <div class="col-sm-6">
                <div class="form-floating mb-3">
                    <x-select-search id='state_id' wire:model.lazy="customer_profile.state" aria-label="Select State"
                    :options="$this->getStates()" disabled="{{ !$this->editable }}" autocomplete="off"/>
                    <x-form-label for="state" required value="{{ __('Select State') }}" />
                    <x-form-input-error name="customer_profile.state"/>
                </div>
            </div>
            <div class="col-sm-6">
                <div class="form-floating mb-3">
                    <x-select-search id='city'
                        aria-label="Select City"
                        :options="$this->allCities ?? []"
                        listner="state_id"
                        wire:model.defer='customer_profile.city'
                        disabled="{{ !$this->editable }}" autocomplete="off"/>
                    <x-form-label for="city" required value="{{ __('Select City') }}"  />
                    <x-form-input-error name="customer_profile.city"/>
                </div>
            </div>
        </div>

        <div class="row" x-show="entity_type=='Person'">
            <div class="col-sm-6">
                <div class="form-floating mb-3">
                    <x-form-input type="text" name="email" wire:model.defer="customer.email" placeholder="Email" disabled="{{ !$this->editable }}" disabled="{{ !$this->editable }}"/>
                    <x-form-label for="email" required value="{{ __('Email:') }}"/>
                    <x-form-input-error name="customer.email"/>
                </div>
            </div>

            <div class="col-sm-6">
                <div class="form-floating mb-3">
                    <x-form-input type="text" wire:model.defer="customer.cellphone" placeholder="Phone No" disabled="{{ !$this->editable }}" autocomplete="off"/>
                    <x-form-label for="customer.cellphone" required value="{{ __('Phone No:') }}"/>
                    <x-form-input-error name="customer.cellphone"/>
                </div>
            </div>
        </div>
        <div class="row" x-show="entity_type=='Person'">
            {{-- <div class="col-sm-6">
                <div class="form-floating mb-3">
                    <x-form-input type="text"  wire:model.defer="customer_profile.know_name" placeholder="Known Name" disabled="{{ !$this->editable }}" autocomplete="off"/>
                    <x-form-label for="know_name" value="{{ __('Known Name:') }}"/>
                    <x-form-input-error name="customer_profile.know_name"/>
                </div>
            </div> --}}
            <div class="col-sm-6">
                <div class="form-floating mb-3">
                    <x-form-input type="text"  wire:model.defer="customer_profile.post_address" placeholder="Postal Address" disabled="{{ !$this->editable }}" autocomplete="off"/>
                    <x-form-label for="post_address" value="{{ __('Postal Address:') }}"/>
                    <x-form-input-error name="customer_profile.post_address"/>
                </div>
            </div>
        </div>
        <hr>
        <div class="row">
            <h2  class="mb-5 pt-3 pb-3" style="font-size:15px;color: var(--bs-accordion-active-color);background-color: var(--bs-accordion-active-bg);">
                General Questions
            </h2>
            <div class="col-sm-6"  >
                {{-- <div x-show="select_product=='Commercial All Risk'"> --}}
                    <div x-show="product_id == 7">
                    <div class="row">
                        <div class="form-floating mb-3 col-sm-6">
                            <x-form-date type="text" class="kt_datepicker_1" id="customer_profile_date"  wire:model.lazy="customer_profile_date" placeholder="Date Business Established?" disabled="{{ !$this->editable }}" autocomplete="off"/>
                            <x-form-label for="customer_profile_date" required value="{{ __('Date Business Established?') }}"/>
                            <x-form-input-error name="customer_profile_date"/>
                        </div>
                        <div class="col-sm-6">
                            <x-form-input type="text" name="business_note" readonly wire:model.lazy="customer_profile.business_note" placeholder="" disabled="{{ !$this->editable }}" autocomplete="off"/>
                        </div>
                    </div>
                </div>

                <div>
                    <p style="font-weight:bold;">Has any insurer ever?</p>
                    <div class="form-floating mb-3">
                        <x-check-box label="{{ __('(a) Declined any proposal?') }}" id="customer_profile.decline_proposal" wire:model.defer="customer_profile.decline_proposal" disabled="{{ !$this->editable }}" autocomplete="off"/>
                        <x-form-input-error name="customer_profile.decline_proposal"/>
                    </div>
                </div>

                <div class="form-floating mb-3">
                    <x-check-box label="{{ __('(b) Refused to renew any policy?') }}" id="customer_profile.refused_policy" wire:model.defer="customer_profile.refused_policy" disabled="{{ !$this->editable }}" autocomplete="off"/>
                    <x-form-input-error name="customer_profile.refused_policy"/>
                </div>

                <div class="form-floating mb-3">
                    <x-check-box label="{{ __('(c) Cancelled any policy?') }}" id="customer_profile.cancel_policy" wire:model.defer="customer_profile.cancel_policy" disabled="{{ !$this->editable }}" autocomplete="off"/>
                    <x-form-input-error name="customer_profile.cancel_policy"/>
                </div>
            </div>

            <div class="col-sm-6">

                <div class="form-floating mb-3">
                    <x-select-search id="insure" aria-label="Are you currently insured, if so who is your insurer?"
                    :options="$this->getCurrentlyInsure()" wire:model.defer="customer_profile.insure"
                              disabled="{{ !$this->editable }}" />
                    <x-form-label for="insure" required value="{{ __('Are you currently insured, if so who is your insurer?') }}" />
                    <x-form-input-error name="customer_profile.insure"/>
                </div>

                <div class="form-floating mb-3" x-show="product_id == 7">
                    <x-chec-kbox label="{{ __('Have you or any member of your firm ever made a compromise with creditors or been declared insolvent?') }}" id="customer_profile.firm_member" wire:model.defer="customer_profile.firm_member" disabled="{{ !$this->editable }}" />
                    <x-form-input-error name="customer_profile.firm_member"/>
                </div>

                <div class="form-floating mb-3" x-show="product_id == 7">
                    <x-check-box  label="{{ __('Do you keep a complete set of books showing a true and accurate record of business transacted?') }}" id="customer_profile.books" wire:model.defer="customer_profile.books" disabled="{{ !$this->editable }}" />
                    <x-form-input-error name="customer_profile.books"/>
                </div>

                <div class="form-floating mb-3">
                    <x-select-search id="about_alpha" aria-label="How did you hear about Alpha Direct?"
                    :options="$this->getHearAboutAlphadirect()" wire:model.defer="customer_profile.about_alpha"
                              disabled="{{ !$this->editable }}" />
                    <x-form-label for="about_alpha" required value="{{ __('How did you hear about Alpha Direct?') }}" />
                    <x-form-input-error name="customer_profile.about_alpha"/>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-sm-12 text-center">
                @if($Application_Update and $this->editable)
                <button type="button" wire:click.prevent="saveStep1" class="btn btn-primary pull-right font-weight-bolder text-uppercase px-6 py-4" wire:loading.class="spinner spinner-right spinner-white pr-15"  wire:offline.attr="disabled" wire:loading.attr="disabled">
                        Update
                </button>
                @endif
            </div>
        </div>
        @if($isPrevious)
            <div class="row" style="padding:4px;">
                <div class="col-sm-12">
                    <button type="button" wire:target="saveStep1" wire:loading.attr="disabled" wire:offline.attr="disabled" wire:click="saveStep1" class="btn btn-success hover-rotate-end" style="float: right;">
                        <span>Save & Continue (Step 1)</span>
                        <span wire:loading wire:target="saveStep1" class="indicator-progress">
                            <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                        </span>
                    </button>
                </div>
            </div>
        @endif

    </div>
</div>
