<div>
    <x-slot name="breadcrum">
        <!--begin::Page title-->
        <div class="page-title d-flex flex-column justify-content-center flex-wrap me-3">
            <!--begin::Title-->
            <h1 class="page-heading d-flex text-dark fw-bold fs-3 flex-column justify-content-center my-0">Reinsurance Treaty</h1>
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
                    <a href="" class="text-muted text-hover-primary">Reinsurance Treaty</a>
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
                    <!-- begin:: Content -->
                    <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
                        <!--begin::Portlet-->
                        <div class="kt-portlet">
                                <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                                <div class="kt-portlet__body">
                                    <div class="form-group row">
                                        <label for="example-text-input" class="col-3 col-form-label">Treaty Name</label>
                                        <div class="col-9">
                                            <div class="form-floating mb-3">
                                                <x-form-input type="text" name="treaty.treaty_name"  placeholder="Treaty Name" wire:model.defer='treaty.treaty_name'/>
                                                <x-form-label for="Treatyname" required value="{{ __('Treaty Name') }}"/>
                                                <x-form-input-error name="treaty.treaty_name"/>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label for="example-text-input" class="col-3 col-form-label">Treaty Number</label>
                                        <div class="col-9">
                                            <div class="form-floating mb-3">
                                                <x-form-input type="number" name="treaty.treaty_number" placeholder="Treaty Number" wire:model.defer='treaty.treaty_number'/>
                                                <x-form-label for="Treatynumber" required value="{{ __('Treaty Number') }}"/>
                                                <x-form-input-error name="treaty.treaty_number"/>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-group row">
                                        <label for="example-text-input" class="col-3 col-form-label">Provisional Commission</label>
                                        <div class="col-9">
                                            <div class="form-floating mb-3">
                                                <x-form-input type="text" name="treaty.provisional_commission" placeholder="Provisional Commission" wire:model.defer='treaty.provisional_commission'/>
                                                <x-form-label for="provisional_commission" required value="{{ __('Provisional Commission') }}"/>
                                                <x-form-input-error name="treaty.provisional_commission"/>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label for="example-text-input" class="col-3 col-form-label">Proportional Share</label>
                                        <div class="col-9">
                                            <div class="form-floating mb-3">
                                                <x-form-input type="text" name="treaty.proportional_share"  placeholder="Proportional Share" wire:model.defer='treaty.proportional_share'/>
                                                <x-form-label for="proportional_share" required value="{{ __('Proportional Share') }}"/>
                                                <x-form-input-error name="treaty.proportional_share"/>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label for="example-text-input" class="col-3 col-form-label">Cash Loss Advise</label>
                                        <div class="col-9">
                                            <div class="form-floating mb-3">
                                                <x-form-input type="text" name="treaty.cash_loss_advise"  placeholder="Cash Loss Advise" wire:model.defer='treaty.cash_loss_advise'/>
                                                <x-form-label for="cash_loss_advise" required value="{{ __('Cash Loss Advise') }}"/>
                                                <x-form-input-error name="treaty.cash_loss_advise"/>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label for="example-text-input" class="col-3 col-form-label">Event Limit
                                        </label>
                                        <div class="col-9">
                                            <div class="form-floating mb-3">
                                                <x-form-input type="text" name="treaty.event_limit"  placeholder="Event Limit" wire:model.defer='treaty.event_limit'/>
                                                <x-form-label for="event_limit" required value="{{ __('Event Limit') }}"/>
                                                <x-form-input-error name="treaty.event_limit"/>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label for="example-text-input" class="col-3 col-form-label">Exclusions</label>
                                        <div class="col-9">
                                            <div class="form-floating mb-3">
                                                <x-form-input type="text" name="treaty.exclusions"  placeholder="Exclusions" wire:model.defer='treaty.exclusions'/>
                                                <x-form-label for="exclusions" required value="{{ __('Exclusions') }}"/>
                                                <x-form-input-error name="treaty.exclusions"/>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label for="example-text-input" class="col-3 col-form-label">Formula Attached</label>
                                        <div class="col-9">
                                            <div class="form-floating mb-3" wire:ignore>
                                                <select class="form-select mb-2" id="formula" data-placeholder="Select an option" data-allow-clear="true"
                                                 multiple="multiple">
                                                    <option></option>
                                                    @foreach($this->getReinsuranceFormula() as $reinsuranceformula)
                                                    <option value="{{ $reinsuranceformula['id'] }}" >{{ $reinsuranceformula['name'] }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label for="example-text-input" class="col-3 col-form-label">Period</label>
                                        <div class="col-sm-4">
                                            <div class="form-floating mb-3">
                                                <x-form-date type="text" class="kt_datepicker_1" id="treaty_effective_from" name="effective_from"  placeholder="Effective From" wire:model.defer='effective_from'/>
                                                <x-form-label for="treaty_effective_from" required value="{{ __('Effective From') }}"/>
                                                <x-form-input-error name="treaty_effective_from"/>
                                            </div>
                                        </div>
                                        <div class="col-sm-4">
                                            <div class="form-floating mb-3">
                                                <x-form-date type="text" class="kt_datepicker_1" id="treaty_effective_to" name="effective_to" placeholder="Effective To" wire:model.defer='effective_to'/>
                                                <x-form-label for="treaty_effective_to" required value="{{ __('Effective To') }}"/>
                                                <x-form-input-error name="treaty_effective_to"/>
                                            </div>
                                        </div>
                                    </div>
                                    <br>
                                    <div class="form-group row">
                                        <div class="col-4">
                                            <div class="form-floating mb-3">
                                                <x-check-box label="{{ __('Status Active') }}" id="" wire:model.defer="treaty.status"/>
                                                <x-form-input-error name="treaty.status"/>
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
                    <a href="{{ route('reinsuranceTreaty') }}" class="btn btn-secondary" value="Cancel">Cancel</a>
                </div>
            </div>
            <!--end::Card-->
        </form>

    </div>
    <!--end::Content container-->
</div>
@push('scripts')
<script>
        $('#formula').select2({});

        $('#formula').select2().on('select2:select', function (e) {
           Livewire.emit('formulaInput',e.params.data.id);
        });


        $("#formula").on("select2:unselect", function (e) {
             var value = e.params.data.id;
             Livewire.emit('formulaRemove',e.params.data.id);
        });

</script>
@endpush
