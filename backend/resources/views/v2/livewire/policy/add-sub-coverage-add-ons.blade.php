<div>
    @if($coverage['has_vehicle'])
        <h4>
            Vehicle
        </h4>
        <hr>
        <div class="row">
            <div class="col-sm-3">
                <div class="form-floating mb-3">
                    <select class = 'form-select form-select-solid' aria-label="Select Vehicle"
                            wire:model="vehicleSelected.{{ $coverType->id }}" wire:change="handleSubVehicleChange($event.target.value,{{ $coverType->id }},{{$subCoverage['id']}})" wire:target="vehicleSelected.{{ $coverType->id }}">
                        <option value="">- Select -</option>
                        @foreach($this->AllVehicles as $id => $vehiclePlate)
                            <option value="{{ $id }}"> {{ $vehiclePlate }} </option>
                        @endforeach
                    </select>
                    <x-form-label for="policyCoverageEntity.{{ $coverType->id }}.Vehicle" required value="{{ __('Select Vehicle') }}"/>
                    <x-form-input-error name="policyCoverageEntity.{{ $coverType->id }}.Vehicle"/>
                </div>
            </div>
            <div class="col-sm-1">
                <a href="#" x-on:click="Modaltitle = 'Vehicle'" class="btn btn-icon btn-sm btn-success flex-shrink-0 ms-4" data-bs-toggle="modal" data-bs-target="#kt_modal_create_campaign">
                    <span class="svg-icon svg-icon-2">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <rect opacity="0.5" x="11.364" y="20.364" width="16" height="2" rx="1" transform="rotate(-90 11.364 20.364)" fill="currentColor" />
                            <rect x="4.36396" y="11.364" width="16" height="2" rx="1" fill="currentColor" />
                        </svg>
                    </span>
                </a>
            </div>
        </div>
    @endif
    @if($coverage['has_member'])
        <h4>Member</h4>
        <hr>
        <div class="row">
            <div class="col-sm-3">
                <div class="form-floating mb-3">
                    <select class = 'form-select form-select-solid' aria-label="Select Member"
                            wire:model="policyCoverageEntity.{{ $coverType->id }}.Member">
                        <option value="">- Select -</option>
                        @foreach($this->AllBeneficiaries as $id => $name)
                            <option value="{{ $id }}"> {{ $name }}</option>
                        @endforeach
                    </select>
                    <x-form-label for="policyCoverageEntity.{{ $coverType->id }}.Member" required value="{{ __('Select Member') }}"/>
                    <x-form-input-error name="policyCoverageEntity.{{ $coverType->id }}.Member"/>
                </div>
            </div>
            <div class="col-sm-1">
                <a href="#" x-on:click="Modaltitle = 'Member'" class="btn btn-icon btn-sm btn-success flex-shrink-0 ms-4" data-bs-toggle="modal" data-bs-target="#kt_modal_create_campaign">
                    <span class="svg-icon svg-icon-2">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <rect opacity="0.5" x="11.364" y="20.364" width="16" height="2" rx="1" transform="rotate(-90 11.364 20.364)" fill="currentColor" />
                            <rect x="4.36396" y="11.364" width="16" height="2" rx="1" fill="currentColor" />
                        </svg>
                    </span>
                </a>
            </div>
        </div>
    @endif
    @if($coverage['has_device'])
        <h4>Device</h4>
        <hr>
        <div class="row">
            <div class="col-sm-3">
                <div class="form-floating mb-3">
                    <select class = 'form-select form-select-solid' aria-label="Select Device"
                            wire:model="policyCoverageEntity.{{ $coverType->id }}.Device">
                        <option value="">- Select -</option>
                        @foreach($this->AllDevices as $id => $name)
                            <option value="{{ $id }}"> {{ $name }} </option>
                        @endforeach
                    </select>
                    <x-form-label for="policyCoverageEntity.{{ $coverType->id }}.Device" required value="{{ __('Select Device') }}"/>
                    <x-form-input-error name="policyCoverageEntity.{{ $coverType->id }}.Device" />
                </div>
            </div>
            <div class="col-sm-1">
                <a href="#" x-on:click="Modaltitle = 'Device'" class="btn btn-icon btn-sm btn-success flex-shrink-0 ms-4" data-bs-toggle="modal" data-bs-target="#kt_modal_create_campaign">
                    <span class="svg-icon svg-icon-2">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <rect opacity="0.5" x="11.364" y="20.364" width="16" height="2" rx="1" transform="rotate(-90 11.364 20.364)" fill="currentColor" />
                            <rect x="4.36396" y="11.364" width="16" height="2" rx="1" fill="currentColor" />
                        </svg>
                    </span>
                </a>
            </div>
        </div>
    @endif
</div>
