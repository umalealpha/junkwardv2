<div>
    <x-slot name="breadcrum">
        <!--begin::Page title-->
        <div class="page-title d-flex flex-column justify-content-center flex-wrap me-3">
            <!--begin::Title-->
            <h1 class="page-heading d-flex text-dark fw-bold fs-3 flex-column justify-content-center my-0">Reinsurance Formula</h1>
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
                    <a href="" class="text-muted text-hover-primary">Reinsurance Formula</a>
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
    <div id="kt_app_content_container" class="app-container container-fluid" wire:loading.class="page-loading" x-data>
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
                    <!-- begin:: Content -->
                    <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
                        <!--begin::Portlet-->
                        <div class="kt-portlet">
                                <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                                <div class="kt-portlet__body">
                                    <div class="form-group row">
                                        <label for="example-text-input" class="col-3 col-form-label">Formula Name</label>
                                        <div class="col-9">
                                            <div class="form-floating mb-3">
                                                <x-form-input type="text" name="reinsurance_formula.formula_name"  placeholder="Formula Name" wire:model.defer='reinsurance_formula.formula_name'/>
                                                <x-form-label for="formula_name" required value="{{ __('Formula Name') }}"/>
                                                <x-form-input-error name="reinsurance_formula.formula_name"/>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label for="example-text-input" class="col-3 col-form-label">Formula Code</label>
                                        <div class="col-9">
                                            <div class="form-floating mb-3">
                                                <x-form-input type="number" name="reinsurance_formula.formula_code" placeholder="Formula Code" wire:model.defer='reinsurance_formula.formula_code'/>
                                                <x-form-label for="formula_code" required value="{{ __('Formula Code') }}"/>
                                                <x-form-input-error name="reinsurance_formula.formula_code"/>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label for="example-text-input" class="col-3 col-form-label">Product</label>
                                        <div class="col-9">
                                            <div class="form-floating mb-3">
                                                <x-select-search id="reinsurance_formula.product_id" wire:model="reinsurance_formula.product_id" aria-label="Please choose the product"
                                                    :options="$this->getProducts();" disabled="{{ !$this->editable }}"  />
                                                <x-form-label for="product_id" required value="{{ __('Please choose the product') }}"  />
                                                <x-form-input-error name="reinsurance_formula.product_id"/>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label for="example-text-input" class="col-3 col-form-label">Reinsurance Type</label>
                                        <div class="col-9">
                                            <div class="form-floating mb-3">
                                                <x-select-search id="reinsurance_formula.reinsurance_type_id" wire:model="reinsurance_formula.reinsurance_type_id" aria-label="Please choose the Reinsurance Type"
                                                    :options="$this->getReinsuranceType()" disabled="{{ !$this->editable }}" />
                                                <x-form-label for="reinsurance_type_id" required value="{{ __('Please choose the Reinsurance Type') }}"  />
                                                <x-form-input-error name="reinsurance_formula.reinsurance_type_id"/>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label for="example-text-input" class="col-3 col-form-label">Coverage Type</label>
                                        <div class="col-9">
                                            <div class="form-floating mb-3">
                                                <x-select-search id="reinsurance_formula.type_id" wire:model="reinsurance_formula.type_id" aria-label="Please choose the Types"
                                                    :options="$this->getTypes()" disabled="{{ !$this->editable }}" />
                                                <x-form-label for="type_id" required value="{{ __('Please choose the Types') }}"  />
                                                <x-form-input-error name="reinsurance_formula.type_id"/>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label for="example-text-input" class="col-3 col-form-label">Formula Type</label>
                                        <div class="col-9">
                                            <div class="form-floating mb-3">
                                                <x-select
                                                aria-label="Select Formula Types"
                                                :options="array('TSI'=>'TSI','SURPLUS'=>'SURPLUS','FACULTATIVE'=>'FACULTATIVE','OTHER'=>'OTHER','FACULATIVEPLACEMENT'=>'FACULATIVEPLACEMENT')"
                                                wire:model.defer='reinsurance_formula.s_FormulaType' />
                                                <x-form-label for="s_FormulaType" required value="{{ __('Please choose the Formula Types') }}"/>
                                                <x-form-input-error name="reinsurance_formula.s_FormulaType"/>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <div class="col-4">
                                            <div class="form-floating mb-3">
                                                <x-check-box label="{{ __('Status') }}" id="" wire:model.defer="reinsurance_formula.status"/>
                                                <x-form-input-error name="reinsurance_formula.status"/>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <div class="col-4">
                                            <div class="form-floating mb-3">
                                                <x-select-search id="group_id"   wire:model.defer="reinsurance_formula_details.group_id"  aria-label="REINSURANCE GROUP"
                                                :options="$this->allGroups ?? []"   listner="group_id" disabled="{{ !$this->editable }}"/>
                                                <x-form-label for="reinsurance_formula_details.group_id" required value="{{ __('REINSURANCE GROUP') }}"  />
                                                <x-form-input-error name="reinsurance_formula_details.group_id" value='{{ $this->reinsurance_formula_details->group_id }}'/>
                                            </div>
                                        </div>
                                        <div class="col-2">
                                            <div class="form-floating mb-3">
                                                <x-select-search id="vehicle_type" wire:model.lazy="reinsurance_formula.vehicle_type" aria-label="Please choose Motor Type"
                                                    :options="$this->allMotorType ?? []"  listner="vehicle_type"  disabled="{{ !$this->editable }}" value='{{$this->reinsurance_formula_details->vehicle_type }}' />
                                                <x-form-label for="vehicle_type" required value="{{ __('Please choose the motor type') }}"  />
                                                <x-form-input-error name="reinsurance_formula.vehicle_type"/>
                                            </div>
                                        </div>
                                        <div class="col-2">
                                            <div class="form-floating mb-3">
                                                <x-select
                                                aria-label="Select Operater"
                                                :options="array('1'=>'=','2'=>'<','3'=>'<=','4'=>'>','5'=>'>=','6'=>'!=','7'=>'Between','8'=>'Not Between','9'=>'*')"
                                                wire:model.defer='reinsurance_formula_details.operator' />
                                                <x-form-label for="operator" value="{{ __('OPERATOR') }}"/>
                                                <!-- <x-form-input-error name="reinsurance_formula_details.operator"/> -->
                                            </div>
                                        </div>
                                        <div class="col-2">
                                            <div class="form-floating mb-3">
                                                <x-form-input type="text" name="reinsurance_formula_details.si_allocation" placeholder="SI ALLOCATION" wire:model.defer='reinsurance_formula_details.si_allocation' x-mask:dynamic="$money($input)"/>
                                                <x-form-label for="si_allocation"  value="{{ __('SI ALLOCATION') }}"/>
                                                <!-- <x-form-input-error name="reinsurance_formula_details.si_allocation"/> -->
                                            </div>
                                        </div>
                                        <div class="col-2">
                                            <div class="form-floating mb-3">
                                                <x-form-input type="number" name="reinsurance_formula_details.percentage" placeholder="PERCENTAGE" wire:model.defer='reinsurance_formula_details.percentage'/>
                                                <x-form-label for="percentage"  value="{{ __('PERCENTAGE') }}"/>
                                                <!-- <x-form-input-error name="reinsurance_formula_details.percentage"/> -->
                                            </div>
                                        </div>
                                        <div class="col-2">
                                            <div class="form-floating mb-3">
                                                <x-form-date type="text" class="kt_datepicker_1" id="reinsurance_formula_details_date_from" name="date_from" placeholder="From" wire:model.defer='date_from'/>
                                                <x-form-label for="reinsurance_formula_details_date_from" required value="{{ __('From') }}"/>
                                                <x-form-input-error name="reinsurance_formula_details_date_from"/>
                                            </div>
                                        </div>
                                        <div class="col-2">
                                            <div class="form-floating mb-3">
                                                <x-form-date type="text" class="kt_datepicker_1" id="reinsurance_formula_details_date_to" name="date_to" placeholder="To" wire:model.defer='date_to'/>
                                                <x-form-label for="reinsurance_formula_details_date_to" required value="{{ __('To') }}"/>
                                                <x-form-input-error name="reinsurance_formula_details_date_to"/>
                                            </div>
                                        </div>
                                    </div>

                                </div>
                            <!--end::Form-->
                        </div>
                        <!--end::Portlet-->
                    </div>
                    <!-- end:: Content -->
                </div>
                <!--end::Card body-->
                <div class="card-footer text-center">
                    <input type="submit" class="btn btn-primary" value="Submit">
                    <a href="{{ route('reinsuranceFormula') }}" class="btn btn-secondary" value="Cancel">Cancel</a>
                </div>
            </div>
            <!--end::Card-->
        </form>
    </div>
    <!--end::Content container-->
</div>

