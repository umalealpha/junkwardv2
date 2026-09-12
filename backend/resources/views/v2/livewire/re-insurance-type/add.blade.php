<div>
    <x-slot name="breadcrum">
        <!--begin::Page title-->
        <div class="page-title d-flex flex-column justify-content-center flex-wrap me-3">
            <!--begin::Title-->
            <h1 class="page-heading d-flex text-dark fw-bold fs-3 flex-column justify-content-center my-0">Create Reinsurance Type</h1>
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
                    <a href="{{ route('reinsurance-type') }}" class="text-muted text-hover-primary">Reinsurance Type</a>
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
                        <div class="{{ $colSize }}">
                            <div class="form-floating mb-3">
                                <x-form-input type="text" name="reinsurancetype.type_code"  placeholder="Type Code" wire:model.defer='reinsurancetype.type_code'/>
                                <x-form-label for="reinsurancetype.type_code" required value="{{ __('Type Code') }}"/>
                                <x-form-input-error name="reinsurancetype.type_code"/>
                            </div>
                        </div>

                        <div class="{{ $colSize }}">
                            <div class="form-floating mb-3">
                                <x-form-input type="text" name="reinsurancetype.type_name"  placeholder="Type Name" wire:model.defer='reinsurancetype.type_name'/>
                                <x-form-label for="reinsurancetype.type_name" required value="{{ __('Type Name') }}"/>
                                <x-form-input-error name="reinsurancetype.type_name"/>
                            </div>
                        </div>

                        <div class="col-sm-6">
                            <div class="form-floating mb-3">
                                <x-form-input type="text" name="reinsurancetype.type_description"  placeholder="Type Description" wire:model.defer='reinsurancetype.type_description'/>
                                <x-form-label for="reinsurancetype.type_description" required value="{{ __('Type Description') }}"/>
                                <x-form-input-error name="reinsurancetype.type_description"/>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="{{ $colSize }}">
                            <div class="form-floating mb-3">
                                <x-check-box label="{{ __('Status') }}" id="reinsurancetype.status" wire:model.defer="reinsurancetype.status"/>
                                <x-form-input-error name="reinsurancetype.status"/>
                            </div>
                        </div>
                    </div>
                </div>
                <!--end::Card body-->

                <div class="card-footer text-center">
                    <input type="submit" class="btn btn-primary" value="Submit">
                    <a href="{{ route('reinsurance-type') }}" class="btn btn-secondary" value="Cancel">Cancel</a>
                </div>
            </div>
            <!--end::Card-->
        </form>
    </div>
    <!--end::Content container-->
</div>
