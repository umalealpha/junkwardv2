<div>
    <x-slot name="breadcrum">
        <!--begin::Page title-->
        <div class="page-title d-flex flex-column justify-content-center flex-wrap me-3">
            <!--begin::Title-->
            <h1 class="page-heading d-flex text-dark fw-bold fs-3 flex-column justify-content-center my-0">Reinsurance Data</h1>
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
                <li class="breadcrumb-item text-muted">Reinsurance Data</li>
                <!--end::Item-->
            </ul>
            <!--end::Breadcrumb-->
        </div>
        <!--end::Page title-->
    </x-slot>
    
    <!--begin::Content container-->
    <div class="app-container container-fluid" wire:loading.class="page-loading">
        <!--begin::Page loading(append to body)-->
        <div class="page-loader flex-column bg-dark bg-opacity-25">
            <span class="spinner-border text-primary" role="status"></span>
            <span class="text-gray-800 fs-6 fw-semibold mt-5">Loading...</span>
        </div>
        <!--end::Page loading-->
        
        <form wire:submit.prevent="submit" autocomplete="off">
            <!--begin::Card-->
            <div class="card">
                <!--begin::Card header-->
                <div class="card-header">
                    <h3 class="card-title">Reinsurance Data Form</h3>
                </div>
                <!--end::Card header-->
                <input type="hidden" wire:model="policyId" value="{{ $policyId }}">
                <input type="hidden" wire:model="actionId" value="{{ $actionId }}">
                <input type="hidden" wire:model="groupId" value="{{ $groupId }}">
                <!--begin::Card body-->
                <div class="card-body">
                    <!--begin::Select Box-->
                    <div class="row mb-5">
                        <div class="col-md-6">
                            <div class="form-floating mb-3">
                                <x-select
                                    aria-label="Select Reinsurer"
                                    :options="$reinsurers"
                                    wire:model.defer="selectedReinsurer" />
                                <x-form-label for="selectedReinsurer" required value="{{ __('Select Reinsurer') }}"/>
                                <x-form-input-error name="selectedReinsurer"/>
                            </div>
                        </div>
                    </div>
                    <!--end::Select Box-->
                    
                    <!--begin::Text Boxes-->
                    <div class="row mb-5">
                        <div class="col-md-6">
                            <div class="form-floating mb-3">
                                <x-form-input 
                                    type="text" 
                                    name="reinsurersBroker"  
                                    placeholder="Reinsurers Broker/Agent DIRECT" 
                                    wire:model.defer="reinsurersBroker"/>
                                <x-form-label for="reinsurersBroker" required value="{{ __('Reinsurers Broker/Agent DIRECT') }}"/>
                                <x-form-input-error name="reinsurersBroker"/>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-floating mb-3">
                                <x-form-input 
                                    type="text" 
                                    name="reinsurersCover"  
                                    placeholder="Basis of cover" 
                                    wire:model.defer="reinsurersCover"/>
                                <x-form-label for="reinsurersCover" required value="{{ __('Basis of cover') }}"/>
                                <x-form-input-error name="reinsurersCover"/>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-5">
                        <div class="col-md-6">
                            <div class="form-floating mb-3">
                                <x-form-input
                                    type="text" 
                                    name="ReinsuranceCommission"  
                                    placeholder="Reinsurance Commission %" 
                                    wire:model.defer="ReinsuranceCommission"/>
                                <x-form-label for="ReinsuranceCommission" value="{{ __('Reinsurance Commission') }}"/>
                                <x-form-input-error name="ReinsuranceCommission"/>
                            </div>
                        </div>
                        
                    </div>
                    
                    
                    <!--end::Text Boxes-->
                </div>
                <!--end::Card body-->
                
                <!--begin::Card footer-->
                <div class="card-footer">
                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary">
                            <span wire:loading.remove wire:target="submit">Submit</span>
                            <span wire:loading wire:target="submit">
                                <span class="spinner-border spinner-border-sm" role="status"></span>
                                Processing...
                            </span>
                        </button>
                    </div>
                </div>
                <!--end::Card footer-->
            </div>
            <!--end::Card-->
        </form>
        
        @if (session()->has('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif
        
        @if (session()->has('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif
    </div>
    <!--end::Content container-->
</div>
