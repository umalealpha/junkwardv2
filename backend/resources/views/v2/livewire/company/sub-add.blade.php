<div>
    <div class="col-sm-12" x-data="{accordion_show : true }">
        <div class="accordion">
            <div class="accordion-item" >
                <h2 class="accordion-header" @click="accordion_show=!accordion_show">
                    <button class="accordion-button fs-4 fw-semibold " type="button" :class="(accordion_show)?'show':'collapsed' " >
                        Sub Company Detail
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
                                            <h3 class="card-label">Sub Companies For {{ $parentCompany->name }}</h3>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div class="col-sm-12">
                                            <div class="table-responsive">
                                                <table class="table align-middle table-row-dashed fs-6 gy-5 dataTable no-footer">
                                                    <thead>
                                                    <tr class="text-start fw-bold fs-7 text-uppercase gs-0">
                                                        <th>Sr.No.</th>
                                                        <th>Name</th>
                                                        <th>Vat Registration Number</th>
                                                        <th>Company Registration Number</th>
                                                        <th>Status</th>
                                                        <th style="text-align:center;">Action</th>
                                                    </tr>
                                                    </thead>
                                                    <tbody class="fw-semibold text-gray-600">
                                                    @forelse($this->parentCompany->subCompanies as $index => $company)
                                                        <tr class="{!! ($loop->iteration % 2 == 0)?'even':'odd' !!}">
                                                            <td>{{ $index+1 }}</td>
                                                            <td>{{ $company->name }}</td>
                                                            <td>{{ $company->VAT_registration_number }}</td>
                                                            <td>{{ $company->company_registration_number }}</td>
                                                            <td>{{ $company->getStatus() }}</td>
                                                            <td>
                                                                <div class="flex space-x-1 justify-around">
                                                                    <button wire:click.prevent="edit({{ $company->id }})"  class="btn  btn-sm btn-outline btn-outline-dashed hover-scale btn-outline-primary btn-active-light-primary">
                                                                        <span class="svg-icon svg-icon-2 m-0"><svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"></path></svg></span>
                                                                    </button>
                                                                    <button  onclick="deleteRow('delete','{{$company->id}}')"  class="btn  btn-sm btn-outline btn-outline-dashed hover-scale btn-outline-danger btn-active-light-danger">
                                                                        <span class="svg-icon svg-icon-2 m-0"><svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg></span>
                                                                    </button>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    @empty
                                                        <tr><td colspan=9>Sub Companies Details Not Present ..</td></tr>
                                                    @endforelse
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <hr>
                        <div class="row">
                            <div class="col-sm-12">
                                <div class='card card-custom gutter-b'>
                                    <div class="card-header">
                                        <div class="card-title">
                                            <h3 class="card-label">
                                                @if($forUpdate)
                                                    Update
                                                @else
                                                    Add
                                                @endif	Sub Company For {{ $parentCompany->name }}</h3>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-sm-3">
                                                <div class="form-floating mb-3">
                                                    <x-form-input type="text" name="subCompany.name"  placeholder="Sub Company Name" wire:model.defer='subCompany.name'/>
                                                    <x-form-label for="subCompany.name" required value="{{ __('Sub Company Name') }}"/>
                                                    <x-form-input-error name="subCompany.name"/>
                                                </div>
                                            </div>
                                            <div class="col-sm-3">
                                                <div class="form-floating mb-3">
                                                    <x-form-input type="text" name="subCompany.VAT_registration_number"  placeholder="VAT Registration Number" wire:model.defer='subCompany.VAT_registration_number'/>
                                                    <x-form-label for="subCompany.VAT_registration_number" required value="{{ __('VAT Registration Number') }}"/>
                                                    <x-form-input-error name="subCompany.VAT_registration_number"/>
                                                </div>
                                            </div>
                                            <div class="col-sm-3">
                                                <div class="form-floating mb-3">
                                                    <x-form-input type="text" name="subCompany.company_registration_number"  placeholder="Company Registration Number" wire:model.defer='subCompany.company_registration_number'/>
                                                    <x-form-label for="subCompany.company_registration_number" required value="{{ __('Company Registration Number') }}"/>
                                                    <x-form-input-error name="subCompany.company_registration_number"/>
                                                </div>
                                            </div>
                                            <div class="col-sm-3">
                                                <div class="form-floating mb-3">
                                                    <x-form-input type="text" name="subCompany.head_office_physical_address"  placeholder="Head Office Physical Address" wire:model.defer='subCompany.head_office_physical_address'/>
                                                    <x-form-label for="subCompany.head_office_physical_address" required value="{{ __('Head Office Physical Address') }}"/>
                                                    <x-form-input-error name="subCompany.head_office_physical_address"/>
                                                </div>
                                            </div>
                                            <div class="col-sm-3">
                                                <div class="form-floating mb-3">
                                                    <x-form-input type="text" name="subCompany.postal_address"  placeholder="Postal Address" wire:model.defer='subCompany.postal_address'/>
                                                    <x-form-label for="subCompany.postal_address" required value="{{ __('Postal Address') }}"/>
                                                    <x-form-input-error name="subCompany.postal_address"/>
                                                </div>
                                            </div>
                                            <div class="col-sm-3">
                                                <div class="form-floating mb-3">
                                                    <x-select-search id='state_id' wire:model.lazy="subCompany.state" aria-label="Select State"
                                                    :options="$this->getStates()" disabled="{{ !$this->editable }}"/>
                                                    <x-form-label for="state" required value="{{ __('Select State') }}" />
                                                    <x-form-input-error name="subCompany.state"/>
                                                </div>
                                            </div>
                                            <div class="col-sm-3">
                                                <div class="form-floating mb-3">
                                                    <x-select-search id='city'
                                                        aria-label="Select City"
                                                        :options="$this->allCities ?? []"
                                                        listner="state_id"
                                                        wire:model.defer='subCompany.city'
                                                        disabled="{{ !$this->editable }}"
                                                    />
                                                    <x-form-label for="subCompany.city" required value="{{ __('Select City') }}"  />
                                                    <x-form-input-error name="subCompany.city"/>
                                                </div>
                                            </div>
                                            <div class="col-sm-3">
                                                <div class="form-floating mb-3">
                                                    <!-- <x-form-input type="text" name="subCompany.pincode"  placeholder="Pincode" wire:model.defer='subCompany.pincode'/> -->
                                                    <x-form-input
                                                        type="text"
                                                        name="subCompany.pincode"
                                                        placeholder="Pincode"
                                                        maxlength="5"
                                                        inputmode="numeric"
                                                        pattern="[0-9]{5}"
                                                        wire:model.defer="subCompany.pincode"
                                                        oninput="this.value=this.value.replace(/[^0-9]/g,'').slice(0,5)"
                                                    />

                                                    <x-form-label for="subCompany.pincode" required value="{{ __('Pincode') }}"/>
                                                    <x-form-input-error name="subCompany.pincode"/>
                                                </div>
                                            </div>
                                            <div class="col-sm-3">
                                                <div class="form-floating mb-3">
                                                    <x-form-input type="text" name="subCompany.contact_person"  placeholder="Contact Person" wire:model.defer='subCompany.contact_person'/>
                                                    <x-form-label for="subCompany.contact_person" required value="{{ __('Contact Person') }}"/>
                                                    <x-form-input-error name="subCompany.contact_person"/>
                                                </div>
                                            </div>
                                            <div class="col-sm-3">
                                                <div class="form-floating mb-3">
                                                    <!-- <x-form-input type="text" name="subCompany.contact_person_number"  placeholder="Contact Person Number" wire:model.defer='subCompany.contact_person_number'/> -->
                                                    <x-form-input
                                                            type="text"
                                                            name="subCompany.contact_person_number"
                                                            placeholder="Contact Person Number"
                                                            wire:model.defer="subCompany.contact_person_number"
                                                            oninput="this.value = this.value.replace(/[^0-9+]/g, '')"
                                                        />

                                                    <x-form-label for="subCompany.contact_person_number" required value="{{ __('Contact Person Number') }}"/>
                                                    <x-form-input-error name="subCompany.contact_person_number"/>
                                                </div>
                                            </div>
                                            <div class="col-sm-3">
                                                <div class="form-floating mb-3">
                                                    <!-- <x-form-input type="text" name="subCompany.primary_email"  placeholder="Primary Email" wire:model.defer='subCompany.primary_email'/> -->
                                                    <x-form-input type="email" name="subCompany.primary_email"  placeholder="Primary Email" wire:model.defer='subCompany.primary_email'/>
                                                    <x-form-label for="subCompany.primary_email" required value="{{ __('Primary Email') }}"/>
                                                    <x-form-input-error name="subCompany.primary_email"/>
                                                </div>
                                            </div>
                                            <div class="col-sm-3">
                                                <div class="form-floating mb-3">
                                                    <!-- <x-form-input type="text" name="subCompany.secondary_email"  placeholder="Secondary Email" wire:model.defer='subCompany.secondary_email'/> -->
                                                    <x-form-input type="email" name="subCompany.secondary_email"  placeholder="Secondary Email" wire:model.defer='subCompany.secondary_email'/>
                                                    <x-form-label for="subCompany.secondary_email" required value="{{ __('Secondary Email') }}"/>
                                                    <x-form-input-error name="subCompany.secondary_email"/>
                                                </div>
                                            </div>
                                            <div class="col-sm-3">
                                                <div class="form-floating mb-3">
                                                    <!-- <x-form-input type="text" name="subCompany.broker_email"  placeholder="Brokers Email" wire:model.defer='subCompany.broker_email'/> -->
                                                    <x-form-input type="email" name="subCompany.broker_email"  placeholder="Brokers Email" wire:model.defer='subCompany.broker_email'/>
                                                    <x-form-label for="subCompany.broker_email" required value="{{ __('Brokers Email') }}"/>
                                                    <x-form-input-error name="subCompany.broker_email"/>
                                                </div>
                                            </div>
                                            <div class="col-sm-3">
                                                <div class="form-floating mb-3">
                                                    <x-check-box label="{{ __('Status') }}" id="" wire:model.defer="subCompany.status"/>
                                                    <x-form-input-error name="subCompany.status"/>
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="col-sm-6" style="text-align: right;">
                                                    @if($forUpdate)
                                                        <button type="button" wire:click.prevent="cancel" class="btn btn-danger pull-right font-weight-bolder text-uppercase px-6 py-4" wire:loading.class="spinner spinner-right spinner-white pr-15"  wire:offline.attr="disabled" wire:loading.attr="disabled">
                                                            Cancel
                                                        </button>
                                                    @endif
                                                </div>
                                                <div class="col-sm-6">
                                                    <button type="button" wire:click.prevent="submit" class="btn btn-primary pull-right font-weight-bolder text-uppercase px-6 py-4" wire:loading.class="spinner spinner-right spinner-white pr-15"  wire:offline.attr="disabled" wire:loading.attr="disabled">
                                                        @if($forUpdate)
                                                            Update
                                                        @else
                                                            <i class="la la-plus"></i>Save
                                                        @endif
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
