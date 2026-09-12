<div>
    <div wire:loading.class="page-loading">
        <!--begin::Page loading(append to body)-->
        <div class="page-loader flex-column bg-dark bg-opacity-25 ">
            <span class="spinner-border text-primary" role="status"></span>
            <span class="text-gray-800 fs-6 fw-semibold mt-5">Loading...</span>
        </div>
        <!--end::Page loading(append to body)-->

        <div class="accordion" id="accordionExample">
            <div class="accordion-item">
                <h2 class="accordion-header" id="headingOne">
                    <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="true" aria-controls="collapseOne">
                        Import From Excel
                    </button>
                </h2>
                <div id="collapseOne" class="accordion-collapse collapse show" aria-labelledby="headingOne" data-bs-parent="#accordionExample">
                    <div class="accordion-body">
                        @if(!empty($this->successMessage))
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <strong>Success!</strong> {{ $this->successMessage }}
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close" wire:click="refreshCurrent"></button>
                            </div>
                        @endif
                        
                        @if(!empty($this->errorMessage))
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <strong>Error!</strong> {!! $this->errorMessage !!}
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close" wire:click="refreshCurrent"></button>
                            </div>
                        @endif
                        
                        @if(!empty($this->errors))
                            @if(empty($this->errorMessage))
                                <div class="alert alert-danger" role="alert">
                                    <strong>Validation Errors:</strong> Please check the errors below.
                                </div>
                            @endif
                            @foreach($this->errors as $error)
                                <div id="validationInfo">
                                    <div class="alert alert-danger" role="alert">
                                        <h4 class="alert-heading">ops error on row number {{ $error->row() }} for {{ $error->attribute() }}</h4>
                                        <hr>
                                        @foreach($error->errors() as $errorMessage)
                                            <p class="mb-0">{{ $errorMessage }}</p>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                            <input type="button" value="Cancel" class="btn btn-primary btn-sm" wire:click="refreshCurrent">
                        @else
                            <div id="uploadForm">
                                <div class="row">
                                    <a href="#" wire:click.prevent="downloadExcel">Download Excel Template to Import {{$exportLinkName}}</a>
                                </div>
                                <div class="row justify-content-md-center">
                                    <div class="col-sm-3">
                                        <div class="">
                                            <input type="file" wire:model.defer="file" accept=".csv, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/vnd.ms-excel">
                                            <x-form-input-error name="file"/>
                                        </div>
                                    </div>
                                    <div class="col-sm-2">
                                        <div class="">
                                            <input type="button" value="Import File" class="btn btn-primary btn-sm" wire:click="importExcel">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
