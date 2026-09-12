<div>
    <x-slot name="breadcrum">
        <!--begin::Page title-->
        <div class="page-title d-flex flex-column justify-content-center flex-wrap me-3">
            <!--begin::Title-->
            <h1 class="page-heading d-flex text-dark fw-bold fs-3 flex-column justify-content-center my-0">Specified Items</h1>
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
                    <a href="{{route('specifiedcoverage')}}" class="text-muted text-hover-primary">Specified Items</a>
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
        <form wire:submit.prevent="submit" x-data autocomplete="off">
            <!--begin::Card-->
            <div class="card">
                <!--begin::Card body-->
                <div class="card-body">
                    <div class="row">
                        <div class="col-sm-3">
                            <div class="form-floating mb-3">
                                <x-select-search wire:model.lazy="specidied_coverages.coverage_id" aria-label="Select Coverage"
                                                 :options="$this->coveragesMaster" />
                                <x-form-label for="specidied_coverages.coverage_id" required value="{{ __('Select Coverage') }}" />
                                <x-form-input-error name="specidied_coverages.coverage_id"/>
                            </div>
                        </div>
                        <div class="col-sm-3">
                            <div class="form-floating mb-3">
                                <x-form-input type="text" name="specidied_coverages.specified_name"  placeholder="Specified Name" wire:model.defer='specidied_coverages.specified_name'/>
                                <x-form-label for="specidied_coverages.specified_name" required value="{{ __('Specified Name') }}"/>
                                <x-form-input-error name="specidied_coverages.specified_name"/>
                            </div>
                        </div>
                        <div class="col-sm-3">
                            <div class="form-floating mb-3">
                                <x-form-input type="number" step="any" name="specidied_coverages.rate"  placeholder="Rate" wire:model.defer='specidied_coverages.rate'/>
                                <x-form-label for="specidied_coverages.rate" required value="{{ __('Rate') }}"/>
                                <x-form-input-error name="specidied_coverages.rate"/>
                            </div>
                        </div>
                        <div class="col-sm-3" wire:ignore >
                            <div class="form-floating mb-3">
                                <x-form-date type="text"  class="kt_datepicker_1" id="specidied_coverages_effective_from" name="specidied_coverages.effective_from"  placeholder="Effective From" wire:model.defer='effective_from'/>
                                <x-form-label for="specidied_coverages_effective_from" required value="{{ __('Effective From') }}"/>
                                <x-form-input-error name="specidied_coverages_effective_from"/>
                            </div>
                        </div>

                        <div class="col-sm-3">
                            <div class="form-floating mb-3">
                                <x-form-date type="text" class="kt_datepicker_1" id="specidied_coverages_effective_to" name="specidied_coverages.effective_to" placeholder="Effective To" wire:model.defer='effective_to'/>
                                <x-form-label for="specidied_coverages_effective_to" required value="{{ __('Effective To') }}"/>
                                <x-form-input-error name="specidied_coverages_effective_to"/>
                            </div>
                        </div>

                    </div>
                </div>
                <!--end::Card body-->
                <div class="card-footer text-center">
                    <input type="submit" class="btn btn-primary" value="Submit">
                    <a href="{{ route('specifiedcoverage') }}" class="btn btn-secondary" value="Cancel">Cancel</a>
                </div>
            </div>
            <!--end::Card-->
        </form>
    </div>
    <!--end::Content container-->
</div>
