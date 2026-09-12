<div>
    <x-slot name="breadcrum">
        <!--begin::Page title-->
        <div class="page-title d-flex flex-column justify-content-center flex-wrap me-3">
            <!--begin::Title-->
            <h1 class="page-heading d-flex text-dark fw-bold fs-3 flex-column justify-content-center my-0">Validation Rule Group</h1>
            <!--end::Title-->
            <!--begin::Breadcrumb-->
            <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 my-0 pt-1">
                <!--begin::Item-->
                <li class="breadcrumb-item text-muted">
                    <a href="{{route('admin-dashboard')}}" class="text-muted text-hover-primary">Home</a>
                </li>
                <!--end::Item-->
                <!--begin::Item-->
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-400 w-5px h-2px"></span>
                </li>
                <!--end::Item-->
                <!--begin::Item-->
                <li class="breadcrumb-item text-muted">
                    <a href="{{route('validationrulegroup')}}" class="text-muted text-hover-primary">Validation Rule Group</a>
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
    <div id="kt_app_content_container" class="app-container container-fluid" wire:loading.class="page-loading">
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
                        <div class="col-sm-3">
                            <div class="form-floating mb-3">
                                <x-form-input type="text" name="validation_rule_group.s_RuleCode"  placeholder="Rule Code" wire:model.defer='validation_rule_group.s_RuleCode'/>
                                <x-form-label for="validation_rule_group.s_RuleCode" required value="{{ __('Rule Code') }}"/>
                                <x-form-input-error name="validation_rule_group.s_RuleCode"/>
                            </div>
                        </div>

                        <div class="col-sm-3">
                            <div class="form-floating mb-3">
                                <x-form-input type="text" name="validation_rule_group.s_RuleDesc"  placeholder="Description of Rule" wire:model.defer='validation_rule_group.s_RuleDesc'/>
                                <x-form-label for="validation_rule_group.s_RuleDesc" required value="{{ __('Description Of Rule') }}"/>
                                <x-form-input-error name="validation_rule_group.s_RuleDesc"/>
                            </div>
                        </div>

                        <div class="col-sm-3">
                            <div class="form-floating mb-3">
                                <x-select
                                    aria-label="Select Product"
                                    :options="$this->getProduct()"
                                    wire:model.defer='validation_rule_group.n_Product_FK'
                                />
                                <x-form-label for="validation_rule_group.n_Product_FK" required value="{{ __('Select Product') }}"/>
                                <x-form-input-error name="validation_rule_group.n_Product_FK"/>
                            </div>
                        </div>
                    </div>
                    <hr>

                    @foreach($this->ricvGroups as $id => $name)
                        <div class="row">
                            <div class="col-sm-3">
                                <div class="form-floating mb-3">
                                    {{ $name }}
                                </div>
                            </div>
                            <div class="col-sm-3">
                                <div class="form-floating mb-3">
                                    <x-select2 id="seleced_rules_{{ $id }}"
                                               wire:model.defer="seleced_rules.{{ $id }}"
                                               aria-label="Select Vehicle Model"
                                               listner="vehicleDataMake"
                                               :options="$this->ValidationRules"
                                    />
                                    <x-form-label for="seleced_rules_{{ $id }}" value="{{ __('Select Rule') }}"/>
                                    <x-form-input-error name="seleced_rules.{{ $id }}"/>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
                <!--end::Card body-->
                <div class="card-footer text-center">
                    <input type="submit" class="btn btn-primary" value="Submit">
                    <a href="{{ route('validationrulegroup') }}" class="btn btn-secondary" value="Cancel">Cancel</a>
                </div>
            </div>
            <!--end::Card-->
        </form>
    </div>
    <!--end::Content container-->
</div>
