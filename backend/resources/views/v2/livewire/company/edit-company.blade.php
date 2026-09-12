<div>
    <div class="col-sm-12" x-data="{accordion_show : true }">
        <div class="accordion">
            <div class="accordion-item" >
                <h2 class="accordion-header" @click="accordion_show=!accordion_show">
                    <button class="accordion-button fs-4 fw-semibold " type="button" :class="(accordion_show)?'show':'collapsed' " >
                      Company Detail
                    </button>
                </h2>
                <div class="accordion-collapse collapse " :class="(accordion_show)?'show':''">
                    <div class="accordion-body">
                        <div wire:loading.class="page-loading">
                            <div class="page-loader flex-column bg-dark bg-opacity-25 ">
                                <span class="spinner-border text-primary" role="status"></span>
                                <span class="text-gray-800 fs-6 fw-semibold mt-5">Loading...</span>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-sm-12">
                                <div class='card card-custom gutter-b'>
                                    <div class="card-header">
                                        <div class="card-title">
                                            <h3 class="card-label">Edit Details For Company {{ $parentCompany->name }}</h3>
                                        </div>
                                    </div>
                                    <form wire:submit.prevent="submit" autocomplete="off">
                                        <!--begin::Card-->
                                        <div class="card">
                                            <!--begin::Card body-->
                                            <div class="card-body">
                                                <div class="row">
                                                    {{-- <div class="col-sm-3">
                                                        <div class="form-floating mb-3">
                                                            <x-select
                                                                aria-label="Select Parent Company"
                                                                :options="$this->Comapnies"
                                                                wire:model.defer='company.parent_id' />
                                                            <x-form-label for="company.parent_id" required value="{{ __('Select Main Company') }}"/>
                                                            <x-form-input-error name="company.parent_id"/>
                                                        </div>
                                                    </div> --}}
                                                    <div class="col-sm-3">
                                                        <div class="form-floating mb-3">
                                                            <x-form-input type="text" name="company.name"  placeholder="Company Name" wire:model.defer='company.name'/>
                                                            <x-form-label for="company.name" required value="{{ __('Company Name') }}"/>
                                                            <x-form-input-error name="company.name"/>
                                                        </div>
                                                    </div>

                                                    <div class="col-sm-3">
                                                        <div class="form-floating mb-3">
                                                            <x-form-input type="text" name="company.VAT_registration_number"  placeholder="VAT Registration Number" wire:model.defer='company.VAT_registration_number'/>
                                                            <x-form-label for="company.VAT_registration_number" required value="{{ __('VAT Registration Number') }}"/>
                                                            <x-form-input-error name="company.VAT_registration_number"/>
                                                        </div>
                                                    </div>

                                                    <div class="col-sm-3">
                                                        <div class="form-floating mb-3">
                                                            <x-form-input type="text" name="company.company_registration_number"  placeholder="Company Registration Number" wire:model.defer='company.company_registration_number'/>
                                                            <x-form-label for="company.company_registration_number" required value="{{ __('Company Registration Number') }}"/>
                                                            <x-form-input-error name="company.company_registration_number"/>
                                                        </div>
                                                    </div>
                                                    <div class="col-sm-3">
                                                        <div class="form-floating mb-3">
                                                            <x-form-input type="text" name="company.head_office_physical_address"  placeholder="Head Office Physical Address" wire:model.defer='company.head_office_physical_address'/>
                                                            <x-form-label for="company.head_office_physical_address" required value="{{ __('Head Office Physical Address') }}"/>
                                                            <x-form-input-error name="company.head_office_physical_address"/>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="row">

                                                    <div class="col-sm-3">
                                                        <div class="form-floating mb-3">
                                                            <x-form-input type="text" name="company.postal_address"  placeholder="Postal Address" wire:model.defer='company.postal_address'/>
                                                            <x-form-label for="company.postal_address" required value="{{ __('Postal Address') }}"/>
                                                            <x-form-input-error name="company.postal_address"/>
                                                        </div>
                                                    </div>
                                                    <div class="col-sm-3">
                                                        <div class="form-floating mb-3">
                                                            <x-select-search id='state_id' wire:model.lazy="company.state" aria-label="Select State"
                                                            :options="$this->getStates()" disabled="{{ !$this->editable }}"/>
                                                            <x-form-label for="state" required value="{{ __('Select State') }}" />
                                                            <x-form-input-error name="company.state"/>
                                                        </div>
                                                    </div>
                                                   {{-- @dd($this->allCities) --}}
                                                    <div class="col-sm-3">
                                                        <div class="form-floating mb-3">
                                                            <x-select-search id='city'
                                                                aria-label="Select City"
                                                                :options="$this->allCities ?? []"
                                                                listner="state_id"
                                                                wire:model.defer='company.city'
                                                                disabled="{{ !$this->editable }}"
                                                            />
                                                            <x-form-label for="company.city" required value="{{ __('Select City') }}"  />
                                                            <x-form-input-error name="company.city"/>
                                                        </div>
                                                    </div>
                                                    <div class="col-sm-3">
                                                        <div class="form-floating mb-3">
                                                            <!-- <x-form-input type="text" name="company.pincode"  placeholder="Pincode" wire:model.defer='company.pincode'/> -->
                                                            <x-form-input  type="text" name="company.pincode" placeholder="Pincode" maxlength="5" inputmode="numeric" pattern="[0-9]{5}" wire:model.defer="company.pincode" value="{{ $company['pincode'] ?? '00000' }}" />
                                                            <x-form-label for="company.pincode" required value="{{ __('Pincode') }}"/>
                                                            <x-form-input-error name="company.pincode"/>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="row">

                                                    {{-- <div class="col-sm-3">
                                                        <div class="form-floating mb-3">
                                                            <x-form-input type="text" name="company.contact_person"  placeholder="Contact Person" wire:model.defer='company.contact_person'/>
                                                            <x-form-label for="company.contact_person" required value="{{ __('Contact Person') }}"/>
                                                            <x-form-input-error name="company.contact_person"/>
                                                        </div>
                                                    </div> --}}
                                                    <div class="col-sm-3">
                                                        <div class="form-floating mb-3">
                                                            <x-form-input type="text" name="customerData.firstName"  placeholder="First Name" wire:model.defer='customerData.firstName'/>
                                                            <x-form-label for="customerData.firstName" required value="{{ __('First Name') }}"/>
                                                            <x-form-input-error name="customerData.firstName"/>
                                                        </div>
                                                    </div>
                                                    <div class="col-sm-3">
                                                        <div class="form-floating mb-3">
                                                            <x-form-input type="text" name="customerData.lastName"  placeholder="Last Name" wire:model.defer='customerData.lastName'/>
                                                            <x-form-label for="customerData.lastName" required value="{{ __('Last Name') }}"/>
                                                            <x-form-input-error name="customerData.lastName"/>
                                                        </div>
                                                    </div>
                                                    <div class="col-sm-3">
                                                        <div class="form-floating mb-3">
                                                            <!-- <x-form-input type="text" name="company.contact_person_number"  placeholder="Contact Person Number" wire:model.defer='company.contact_person_number'/> -->
                                                            <x-form-input
                                                                type="text"
                                                                name="company.contact_person_number"
                                                                placeholder="Contact Person Number"
                                                                wire:model.defer="."
                                                                oninput="this.value = this.value.replace(/[^0-9+]/g, '')"
                                                            />

                                                            <x-form-label for="company.contact_person_number" required value="{{ __('Contact Person Number') }}"/>
                                                            <x-form-input-error name="company.contact_person_number"/>
                                                        </div>
                                                    </div>
                                                    <div class="col-sm-3">
                                                        <div class="form-floating mb-3">
                                                            <!-- <x-form-input type="text" name="company.primary_email"  placeholder="Primary Email" wire:model.defer='company.primary_email'/> -->
                                                            <x-form-input
                                                                type="email"
                                                                name="company.primary_email"
                                                                placeholder="Primary Email"
                                                                wire:model.defer="company.primary_email"
                                                            />

                                                            <x-form-label for="company.primary_email" required value="{{ __('Primary Email') }}"/>
                                                            <x-form-input-error name="company.primary_email"/>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="row">

                                                    <div class="col-sm-3">
                                                        <div class="form-floating mb-3">
                                                            <!-- <x-form-input type="text" name="company.secondary_email"  placeholder="Secondary Email" wire:model.defer='company.secondary_email'/> -->
                                                            <x-form-input
                                                                type="email"
                                                                name="company.secondary_email"
                                                                placeholder="Secondary Email"
                                                                wire:model.defer="company.secondary_email"
                                                            />

                                                            <x-form-label for="company.secondary_email" required value="{{ __('Secondary Email') }}"/>
                                                            <x-form-input-error name="company.secondary_email"/>
                                                        </div>
                                                    </div>
                                                    <div class="col-sm-3">
                                                        <div class="form-floating mb-3">
                                                            <!-- <x-form-input type="text" name="company.broker_email"  placeholder="Brokers Email" wire:model.defer='company.broker_email'/> -->
                                                            <x-form-input
                                                                type="email"
                                                                name="company.broker_email"
                                                                placeholder="Brokers Email"
                                                                wire:model.defer="company.broker_email"
                                                            />

                                                            <x-form-label for="company.broker_email" required value="{{ __('Brokers Email') }}"/>
                                                            <x-form-input-error name="company.broker_email"/>
                                                        </div>
                                                    </div>
                                                    <div class="col-sm-3">
                                                        <div class="form-floating mb-3">
                                                            <x-check-box label="{{ __('Status') }}" id="" wire:model.defer="company.status"/>
                                                            <x-form-input-error name="company.status"/>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <!--end::Card body-->
                                            <div class="card-footer text-center">
                                                <input type="submit" class="btn btn-primary" value="Submit">
                                                <a href="{{ route('companies') }}" class="btn btn-secondary" value="Cancel">Cancel</a>
                                            </div>
                                        </div>
                                        <!--end::Card-->
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
