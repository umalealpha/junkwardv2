<div>
    <x-slot name="breadcrum">
        <!--begin::Page title-->
        <div class="page-title d-flex flex-column justify-content-center flex-wrap me-3">
            <!--begin::Title-->
            <h1 class="page-heading d-flex text-dark fw-bold fs-3 flex-column justify-content-center my-0">Edit Reinsurer</h1>
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
                    <a href="{{ route('reinsurance-type') }}" class="text-muted text-hover-primary">Reinsurer</a>
                </li>
                <!--end::Item-->
                <!--begin::Item-->
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-400 w-5px h-2px"></span>
                </li>
                <!--end::Item-->
                <!--begin::Item-->
                <li class="breadcrumb-item text-muted">Edit</li>
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
                        <div class="{{ $colSize }}">
                            <div class="form-floating mb-3">
                                <x-form-input type="text" name="reinsurer.company_name"  placeholder="Company Name" wire:model.defer='reinsurer.company_name'/>
                                <x-form-label for="reinsurer.company_name" required value="{{ __('Company Name') }}"/>
                                <x-form-input-error name="reinsurer.company_name"/>
                            </div>
                        </div>

                        <div class="{{ $colSize }}">
                            <div class="form-floating mb-3">
                                <x-form-input type="text" name="reinsurer.email"  placeholder="Email" wire:model.defer='reinsurer.email'/>
                                <x-form-label for="reinsurer.email" required value="{{ __('Email') }}"/>
                                <x-form-input-error name="reinsurer.email"/>
                            </div>
                        </div>


                        <div class="col-sm-6">
                            <div class="form-floating mb-3">
                                <x-form-input type="text" name="reinsurer.cellphone"  placeholder="Phone" wire:model.defer='reinsurer.cellphone'/>
                                <x-form-label for="reinsurer.cellphone" required value="{{ __('Phone') }}"/>
                                <x-form-input-error name="reinsurer.cellphone"/>
                            </div>
                        </div>
                    </div>
                </div>
                <!--end::Card body-->

                <div class="card-footer text-center">
                    <input type="submit" class="btn btn-primary" value="Submit">
                    <a href="{{ route('reinsurer') }}" class="btn btn-secondary" value="Cancel">Cancel</a>
                </div>
            </div>
            <!--end::Card-->
        </form>
    </div>
    <!--end::Content container-->
</div>
