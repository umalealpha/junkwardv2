<div>
    <x-slot name="breadcrum">
        <!--begin::Page title-->
        <div class="page-title d-flex flex-column justify-content-center flex-wrap me-3">
            <!--begin::Title-->
            <h1 class="page-heading d-flex text-dark fw-bold fs-3 flex-column justify-content-center my-0">Coverages</h1>
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
                    <a href="" class="text-muted text-hover-primary">Coverages</a>
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
                                <x-form-input type="text" name="coverage.s_CoverageName"  placeholder="Coverage Name" wire:model.defer='coverage.s_CoverageName'/>
                                <x-form-label for="s_CoverageName" required value="{{ __('Coverage Name') }}"/>
                                <x-form-input-error name="coverage.s_CoverageName"/>
                            </div>
                        </div>

                        <div class="col-sm-3">
                            <div class="form-floating mb-3">
                                <x-form-input type="text" name="coverage.s_ScreenName"  placeholder="Screen Name" wire:model.defer='coverage.s_ScreenName'/>
                                <x-form-label for="s_ScreenName" required value="{{ __('Screen Name') }}"/>
                                <x-form-input-error name="coverage.s_ScreenName"/>
                            </div>
                        </div>

                        <div class="col-sm-6">
                            <div class="form-floating mb-6">
                                <x-form-input type="text" name="coverage.s_CoverageDesc"  placeholder="Description" wire:model.defer='coverage.s_CoverageDesc'/>
                                <x-form-label for="s_CoverageDesc" required value="{{ __('Description') }}"/>
                                <x-form-input-error name="coverage.s_CoverageDesc"/>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-sm-3">
                            <div class="form-floating mb-3">
                                <x-form-input type="text" name="coverage.n_PrintSequence"  placeholder="Print Sequence" wire:model.defer='coverage.n_PrintSequence'/>
                                <x-form-label for="n_PrintSequence" required value="{{ __('Print Sequence') }}"/>
                                <x-form-input-error name="coverage.n_PrintSequence"/>
                            </div>
                        </div>
                        <div class="col-sm-3">
                            <div class="form-floating mb-3">
                                <x-form-input type="text" name="coverage.n_DisplaySequence"  placeholder="Display Sequence" wire:model.defer='coverage.n_DisplaySequence'/>
                                <x-form-label for="n_DisplaySequence" required value="{{ __('Display Sequence') }}"/>
                                <x-form-input-error name="coverage.n_DisplaySequence"/>
                            </div>
                        </div>
                        <div class="col-sm-3">
                            <div class="form-floating mb-3">
                                <x-form-input type="text" name="coverage.n_RateSequence"  placeholder="Rate Sequence" wire:model.defer='coverage.n_RateSequence'/>
                                <x-form-label for="n_RateSequence" required value="{{ __('Rate Sequence') }}"/>
                                <x-form-input-error name="coverage.n_RateSequence"/>
                            </div>
                        </div>
                        <div class="col-sm-3">
                            <div class="form-floating mb-3">
                                <x-select
                                    aria-label="Select Rating Method"
                                    :options="array('PERCENT'=>'PERCENT','MANUAL'=>'MANUAL')"
                                    wire:model.defer='coverage.s_RatingMethod' />
                                <x-form-label for="s_RatingMethod" required value="{{ __('Select Rating Method') }}"/>
                                <x-form-input-error name="coverage.s_RatingMethod"/>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-sm-3">
                            <div class="form-floating mb-3">
                                <x-form-date type="text" class="kt_datepicker_1" id="coverage_d_EffectiveDt" name="coverage.d_EffectiveDt"  placeholder="Effective From" wire:model.defer='d_EffectiveDt'/>
                                <x-form-label for="coverage_d_EffectiveDt" required value="{{ __('Effective From') }}"/>
                                <x-form-input-error name="coverage_d_EffectiveDt"/>
                            </div>
                        </div>
                        <div class="col-sm-3">
                            <div class="form-floating mb-3">
                                <x-form-date type="text" class="kt_datepicker_1" id="coverage_d_ExpirationDt" name="coverage.d_ExpirationDt" placeholder="Effective To" wire:model.defer='d_ExpirationDt'/>
                                <x-form-label for="coverage_d_ExpirationDt" required value="{{ __('Effective To') }}"/>
                                <x-form-input-error name="coverage_d_ExpirationDt"/>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-sm-3">
                            <div class="form-floating mb-3">
                                <x-check-box label="{{ __('Display to User') }}" id="" wire:model.defer="coverage.s_DISPLAYTOUSER"/>
                                <x-form-input-error name="coverage.s_DISPLAYTOUSER"/>
                            </div>
                        </div>
                        <div class="col-sm-3">
                            <div class="form-floating mb-3">
                                <x-checkbox label="{{ __('Vehicle') }}" id="" wire:model.defer="coverage.has_vehicle"/>
                                <x-form-input-error name="coverage.has_vehicle"/>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-sm-3">
                            <div class="form-floating mb-3">
                                <x-check-box label="{{ __('Member') }}" id="" wire:model.defer="coverage.has_member"/>
                                <x-form-input-error name="coverage.has_member"/>
                            </div>
                        </div>
                        <div class="col-sm-3">
                            <div class="form-floating mb-3">
                                <x-check-box label="{{ __('Device') }}" id="" wire:model.defer="coverage.has_device"/>
                                <x-form-input-error name="coverage.has_device"/>
                            </div>
                        </div>
                    </div>

                </div>
                <!--end::Card body-->
                <div class="card-footer text-center">
                    <input type="submit" class="btn btn-primary" value="Submit">
                    <a href="{{ route('coverage') }}" class="btn btn-secondary" value="Cancel">Cancel</a>
                </div>
            </div>
            <!--end::Card-->
        </form>
    </div>
    <!--end::Content container-->
</div>
