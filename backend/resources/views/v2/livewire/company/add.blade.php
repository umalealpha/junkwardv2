<div>
    <x-slot name="breadcrum">
        <!--begin::Page title-->
        <div class="page-title d-flex flex-column justify-content-center flex-wrap me-3">
            <!--begin::Title-->
            <h1 class="page-heading d-flex text-dark fw-bold fs-3 flex-column justify-content-center my-0">Company</h1>
            <!--end::Title-->
            <!--begin::Breadcrumb-->
            <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 my-0 pt-1">
                <!--begin::Item-->
                <li class="breadcrumb-item text-muted">
                    <a href="" class="text-muted text-hover-primary">Home</a>
                </li>
                <!--end::Item-->
                <!--begin::Item-->
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-400 w-5px h-2px"></span>
                </li>
                <!--end::Item-->
                <!--begin::Item-->
                <li class="breadcrumb-item text-muted">
                    <a href="" class="text-muted text-hover-primary">Company</a>
                </li>
                <!--end::Item-->
                <!--begin::Item-->
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-400 w-5px h-2px"></span>
                </li>
                <!--end::Item-->
                <!--begin::Item-->
                <li class="breadcrumb-item text-muted">Add</li>
                <!--end::Item-->
            </ul>
            <!--end::Breadcrumb-->
        </div>
        <!--end::Page title-->
    </x-slot>
    <!--begin::Content container-->
    <div class="app-container container-fluid" wire:loading.class="page-loading">
        <!--begin::Page loading(append to body)-->
        <div class="page-loader flex-column bg-dark bg-opacity-25 ">
            <span class="spinner-border text-primary" role="status"></span>
            <span class="text-gray-800 fs-6 fw-semibold mt-5">Loading...</span>
        </div>
        <!--end::Page loading-->
        <form wire:submit.prevent="submit" autocomplete="off">
            <!--begin::Card-->
            <div class="card">
                <!--begin::Card body-->
                <div class="card-body">
                    <div class="row">
                        @if(!$modalId)
                        <div class="col-md-6">
                            <div class="form-floating mb-3">
                                <x-select
                                    aria-label="Select Parent Company"
                                    :options="$this->Comapnies"
                                    wire:model.defer='company.parent_id' />
                                <x-form-label for="company.parent_id" required value="{{ __('Select Main Comapny') }}"/>
                                <x-form-input-error name="company.parent_id"/>
                            </div>
                        </div>
                        @endif
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-floating mb-3">
                                <x-form-input type="text" name="company.name"  placeholder="Company Name" wire:model.defer='company.name'/>
                                <x-form-label for="company.name" required value="{{ __('Company Name') }}"/>
                                <x-form-input-error name="company.name"/>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-floating mb-3">
                                <x-form-input type="text" name="company.VAT_registration_number"  placeholder="VAT Registration Number" wire:model.defer='company.VAT_registration_number'/>
                                <x-form-label for="company.VAT_registration_number" required value="{{ __('VAT Registration Number') }}"/>
                                <x-form-input-error name="company.VAT_registration_number"/>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-floating mb-3">
                                <x-form-input type="text" name="company.company_registration_number"  placeholder="Company Registration Number" wire:model.defer='company.company_registration_number'/>
                                <x-form-label for="company.company_registration_number" required value="{{ __('Company Registration Number') }}"/>
                                <x-form-input-error name="company.company_registration_number"/>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-floating mb-3">
                                <x-form-input type="text" name="company.head_office_physical_address"  placeholder="Head Office Physical Address" wire:model.defer='company.head_office_physical_address'/>
                                <x-form-label for="company.head_office_physical_address" required value="{{ __('Head Office Physical Address') }}"/>
                                <x-form-input-error name="company.head_office_physical_address"/>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-floating mb-3">
                                <x-form-input type="text" name="company.postal_address"  placeholder="Postal Address" wire:model.defer='company.postal_address'/>
                                <x-form-label for="company.postal_address" required value="{{ __('Postal Address') }}"/>
                                <x-form-input-error name="company.postal_address"/>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-floating mb-3">
                                <x-select-search id='state_id' wire:model.lazy="company.state" aria-label="Select State"
                                :options="$this->getStates()" disabled="{{ !$this->editable }}"/>
                                <x-form-label for="state" required value="{{ __('Select State') }}" />
                                <x-form-input-error name="company.state"/>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
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
                        <div class="col-md-6">
                            <div class="form-floating mb-3">
                                <!-- <x-form-input type="text" name="company.pincode"  placeholder="Pincode add" wire:model.defer='company.pincode'/> -->
                                <x-form-input
                                type="text"
                                name="company.pincode"
                                placeholder="Pincode add"
                                maxlength="5"
                                inputmode="numeric"
                                pattern="[0-9]{5}"
                                wire:model.defer="company.pincode"
                                oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0,5)"
                            />

                                <x-form-label for="company.pincode" required value="{{ __('Pincode') }}"/>
                                <x-form-input-error name="company.pincode"/>
                            </div>
                        </div>
                    </div>
                    <div class="row"><hr>
                        <h2  class="mb-5 pt-3 pb-3" style="font-size:15px;color: var(--bs-accordion-active-color);background-color: var(--bs-accordion-active-bg);">
                           Contact Person
                        </h2>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-floating mb-3">
                                <x-form-input type="text" name="customerData.firstName"  placeholder="First Name" wire:model.defer='customerData.firstName'/>
                                <x-form-label for="customerData.firstName" required value="{{ __('First Name') }}"/>
                                <x-form-input-error name="customerData.firstName"/>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-floating mb-3">
                                <x-form-input type="text" name="customerData.lastName"  placeholder="Last Name" wire:model.defer='customerData.lastName'/>
                                <x-form-label for="customerData.lastName" required value="{{ __('Last Name') }}"/>
                                <x-form-input-error name="customerData.lastName"/>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-floating mb-3">
                                <!-- <x-form-input type="text" name="company.contact_person_number"  placeholder="Contact Person Number" wire:model.defer='company.contact_person_number'/> -->
                                <x-form-input
                                    type="text"
                                    name="company.contact_person_number"
                                    placeholder="Contact Person Number"
                                    wire:model.defer="company.contact_person_number"
                                    oninput="this.value = this.value.replace(/[^0-9+]/g, '')"
                                />

                                <x-form-label for="company.contact_person_number" required value="{{ __('Contact Person Number') }}"/>
                                <x-form-input-error name="company.contact_person_number"/>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-floating mb-3">
                                <!-- <x-form-input type="text" name="company.primary_email"  placeholder="Primary Email" wire:model.defer='company.primary_email'/> -->
                                <x-form-input type="email" name="company.primary_email"  placeholder="Primary Email" wire:model.defer='company.primary_email'/>
                                <x-form-label for="company.primary_email" required value="{{ __('Primary Email') }}"/>
                                <x-form-input-error name="company.primary_email"/>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-floating mb-3">
                                <!-- <x-form-input type="text" name="company.secondary_email"  placeholder="Secondary Email" wire:model.defer='company.secondary_email'/> -->
                                <x-form-input type="email" name="company.secondary_email"  placeholder="Secondary Email" wire:model.defer='company.secondary_email'/>
                                <x-form-label for="company.secondary_email" required value="{{ __('Secondary Email') }}"/>
                                <x-form-input-error name="company.secondary_email"/>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-floating mb-3">
                                <!-- <x-form-input type="text" name="company.broker_email"  placeholder="Brokers Email" wire:model.defer='company.broker_email'/> -->
                                <x-form-input type="email" name="company.broker_email"  placeholder="Brokers Email" wire:model.defer='company.broker_email'/>
                                <x-form-label for="company.broker_email" required value="{{ __('Brokers Email') }}"/>
                                <x-form-input-error name="company.broker_email"/>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
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
                    @if($modalId)
                        <a href="#" class="btn btn-secondary" data-bs-toggle="modal" data-bs-target="#{{ $modalId }}">Cancel</a>
                    @else
                        <a href="{{ route('companies') }}" class="btn btn-secondary" value="Cancel">Cancel</a>
                    @endif
                </div>
            </div>
            <!--end::Card-->
        </form>
    </div>
    <!--end::Content container-->
</div>
