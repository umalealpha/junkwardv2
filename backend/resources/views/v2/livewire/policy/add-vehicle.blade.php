<div wire:loading.class="page-loading">
    <div class="page-loader flex-column bg-dark bg-opacity-25 ">
        <span class="spinner-border text-primary" role="status"></span>
        <span class="text-gray-800 fs-6 fw-semibold mt-5">Loading...</span>
    </div>
    <div class="card mt-5">
    <div class="card-header">
        <h3 class="card-title">Pending Vehicle Approvals</h3>
    </div>

    <div class="card-body">
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Vehicle Number</th>
                    <th>Requested By</th>
                    <th>Reason</th>
                    <th>Status</th>
                    <th>Approved By</th>
                    <th>Action</th>
                </tr>
            </thead>

            <tbody>
                @forelse($this->pendingVehicles as $vehicle)
                    <tr>
                        <td>{{ $vehicle->vehiclePlate }}</td>
                        <td>{{ $vehicle->requested_first_name ?? '' }} {{ $vehicle->requested_last_name ?? ''  }}</td>
                        <td>{{ $vehicle->approval_request_comment }}</td>
                        <td>
                            <span class="badge bg-warning">{{ $vehicle->approval_status }}</span>
                        </td>
                        <td>{{ $vehicle->approved_first_name ?? '' }} {{ $vehicle->approved_last_name ?? ''  }}</td>
                        <td>
                             @can('policy_unissue')
                                @if($vehicle->approval_status === 'APPROVED')
                                    <span class="text-success">-</span>
                                @else 
                                <button class="btn btn-success btn-sm"
                                        wire:click="approveVehicle({{ $vehicle->id }})">
                                    Approve
                                </button>
                                @endif
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center">
                            No pending approvals
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
    <div class="row">
        <div class="col-sm-12">
            <div class='card card-custom gutter-b'>
                <div class="card-header">
                    <div class="card-title">
                        <h3 class="card-label">Vehicle Details</h3>
                    </div>
                </div>
                <div class="card-body">
                    <div class="col-sm-12">
                        <div class="table-responsive">
                            <table class="table align-middle table-row-dashed fs-6 gy-5 dataTable no-footer">
                                <thead>
                                <tr class="text-start fw-bold fs-7 text-uppercase gs-0">
                                    <th>Sr.No.</th>
                                    <th>Vehicle Plate</th>
                                    <th>Car imported</th>
                                    <th>Make</th>
                                    <th>Model</th>
                                    <th>Engine No.</th>
                                    <th>Chassis No.</th>
                                    <th>Year</th>
                                    <th>Seats</th>
                                    <th>Estimated Value</th>
                                    <th>No. of Accidents</th>
                                    <th>Risk Address</th>
                                    @if($editable)
                                        <th style="text-align:center;">Action</th>
                                    @endif
                                </tr>
                                </thead>
                                <tbody class="fw-semibold text-gray-600">
                                @php $i=1;@endphp
                                @forelse($this->getallVehicles() as $vehicle)
                             
                                    <tr class="{!! ($loop->iteration % 2 == 0)?'even':'odd' !!}">
                                        <td>{{$i++}}</td>
                                        <td style="text-transform: uppercase">{{$vehicle->vehiclePlate ?? ""}}</td>
                                        <td>
                                            @if(isset($vehicle->is_imported))
                                                {{($vehicle->is_imported==1)?'Yes':'No';}}
                                            @endif
                                        </td>
                                        <td>{{$vehicle->make ?? ""}}</td>
                                        <td>{{$vehicle->model ?? ""}}</td>
                                        <td>{{$vehicle->engineNo ?? ""}}</td>
                                        <td>{{$vehicle->chassisNo ?? ""}}</td>
                                        <td>{{$vehicle->year ?? ""}}</td>
                                        <td>{{$vehicle->seats ?? ""}}</td>
                                        <td>{{ number_format((float)$vehicle->estimated_value ?? "", 2, '.', ',')}}</td>
                                        <td>{{ number_format((float)$vehicle->claim_count ?? "", 2, '.', ',')}}</td>
                                        {{-- <td>{{$vehicle->claim_count ?? ""}}</td> --}}
                                        <td>{{$vehicle->risk->address_name ?? ""}}</td>
                                        @if($editable)
                                        <td>
                                            <div class="flex space-x-1 justify-around">
                                                <button wire:click.prevent="vehicleDetailsedit('{{\Crypt::encrypt($vehicle->id)}}')"  class="btn  btn-sm btn-outline btn-outline-dashed hover-scale btn-outline-primary btn-active-light-primary">
                                                    <span class="svg-icon svg-icon-2 m-0"><svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"></path></svg></span>
                                                    <!-- Edit -->
                                                </button>
                                                <button onclick="deleteRow('triggerVehicleDelete','{{$vehicle->id}}')" class="btn  btn-sm btn-outline btn-outline-dashed hover-scale btn-outline-danger btn-active-light-danger">
                                                    <span class="svg-icon svg-icon-2 m-0"><svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg></span>
                                                    <!-- Delete -->
                                                </button>
                                            </div>
                                        </td>
                                        @endif
                                    </tr>
                                @empty
                                    <tr><td colspan=7>Vehicle Details Not Present ..</td></tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @if($editable)
        <hr>
        <div class="row">
            <div class="col-sm-12">
                <div class='card card-custom gutter-b'>
                    <div class="card-header">
                        <div class="card-title">
                            <h3 class="card-label">
                                @if($V_isUpdate)
                                    Update
                                @else
                                    Add
                                @endif	Vehicle Details</h3>
                        </div>
                    </div>
                    <div class="card-body">
                       
                        @if($this->actionTransactionType == 'NEWBUSINESS' || $this->actionTransactionType == 'ANNIVERSARY-RENEW')
                        <livewire:common.excel-import  :policy="$policy" :selectedType="'vehicle'" :termId="$termId" :actionId="$actionId" :exportLinkName="'Vehicle Data'"/>
                        <br>
                        @endif
                        <div class="row">
                            <div class="col-sm-3">
                                <div class="form-floating mb-3">
                                     @if($V_isUpdate)
                                    <x-form-input class="vehicleregNum"  type="text" name="vehiclePlate" maxlength="10"  wire:model.defer="vehicleData.vehiclePlate"  placeholder="Vehicle Number"  readonly="readonly" />
                                     @else
                                    <x-form-input class="vehicleregNum"  type="text" name="vehiclePlate" maxlength="10"  wire:model.defer="vehicleData.vehiclePlate"  placeholder="Vehicle Number" />
                                    @endif
                                    <x-form-label for="vehiclePlate" required value="{{ __('Vehicle Number') }}"/>
                                    <x-form-input-error name="vehicleData.vehiclePlate"/>
                                      <!-- NEW :: Link to change plate -->
                                        @if($V_isUpdate)
                                            <a href="#" wire:click.prevent="openPlateChangeModal"
                                            style="font-size: 12px; color:#0d6efd;">
                                                Change Vehicle Plate?
                                            </a>
                                        @endif
                                    <span style="color: red" id="vehicleMsg" class="vehicleMsg" hidden>Sorry, We only
                                        accept Botswana registered vehicles Eg: B123ABC</span>
                                    {{-- <span style="color: red" id="vehicleMsg2" class="vehicleMsg2">Policy is already
                                        active with this vehicle plate</span> --}}
                                </div>
                            </div>

                            <div class="col-sm-3">
                                <div class="form-floating mb-3">
                                    <x-check-box label="{{ __('Is it imported?') }}" id="vehicleData.is_imported" wire:model.lazy="vehicleData.is_imported"/>
                                    <x-form-input-error name="vehicleData.is_imported"/>
                                </div>
                            </div>

                            <div class="col-sm-3">
                                <div class="form-floating mb-3">
                                    <x-select-search id="vehicleDataMake"
                                        wire:model.lazy="vehicleData.make_id"
                                        aria-label="Select Vechicle Make"
                                        listner="is_imported"
                                        class="form-select form-select-solid"
                                        style="{{ $errors->has('vehicleData.make') ? 'border:1px solid #dc3545 !important; box-shadow:0 0 0 0.2rem rgba(220,53,69,.25);' : '' }}"
                                         :options="$this->getVechicleMake()"
                                    />
                                    <x-form-label for="make" required value="{{ __('Choose a make') }}" />
                                    <x-form-input-error name="vehicleData.make_id"/>                                
                                </div>
                            </div>

                            <div class="col-sm-3">
                                <div class="form-floating mb-3">
                                    <x-select-search id="vehicleDataModel"
                                                     wire:model.defer="vehicleData.model_id"
                                                     aria-label="Select Vehicle Model"
                                                     listner="vehicleDataMake"
                                                    style="{{ $errors->has('vehicleData.model') ? 'border:1px solid #dc3545 !important; box-shadow:0 0 0 0.2rem rgba(220,53,69,.25);' : '' }}"
                                                     :options="$this->vechicleModels"
                                    />
                                    <x-form-label for="model" required value="{{ __('Choose a model') }}" />
                                    <x-form-input-error name="vehicleData.model_id"/>
                                </div>
                            </div>

                            <div class="col-sm-3">
                                <div class="form-floating mb-3">
                                    <x-form-input type="text" name="engineNo" maxlength="25"  wire:model.defer="vehicleData.engineNo" placeholder="Engine Number"/>
                                    <x-form-label for="engineNo"  value="{{ __('Engine Number') }}"/>
                                    <x-form-input-error name="vehicleData.engineNo"/>
                                </div>
                            </div>

                            <div class="col-sm-3">
                                <div class="form-floating mb-3">
                                    <x-form-input type="text" name="chassisNo" maxlength="25"  wire:model.defer="vehicleData.chassisNo" placeholder="Engine Number"/>
                                    <x-form-label for="chassisNo"  value="{{ __('Chassis Number (VIN Code)') }}"/>
                                    <x-form-input-error name="vehicleData.chassisNo"/>
                                </div>
                            </div>
                            <div class="col-sm-3">
                                <div class="form-floating mb-3">
                                    <select class="form-control required @error('vehicleData.year') is-invalid @enderror" name="year" wire:model.defer="vehicleData.year" id="year" data-live-search="true">
                                        <option value="">Choose a year</option>
                                        @foreach($this->getYear() as $k=>$y)
                                            <option value={{ $k }}>{{ $y }}</option>
                                        @endforeach
                                    </select>
                                    <x-form-label for="year" required value="{{ __('Choose a year') }}"/>
                                    <x-form-input-error name="vehicleData.year"/>
                                </div>
                            </div>
                            <div class="col-sm-3">
                                <div class="form-floating mb-3">
                                    <x-form-input type="text" name="seats" maxlength="25"  wire:model.defer="vehicleData.seats" placeholder="No Of Seats"/>
                                    <x-form-label for="seats" required value="{{ __('No Of Seats') }}"/>
                                    <x-form-input-error name="vehicleData.seats"/>
                                </div>
                            </div>
                            <div class="col-sm-3">
                                <div class="form-floating mb-3">
                                    <x-form-input type="text" name="estimated_value" maxlength="25"  wire:model.defer="vehicleData.estimated_value" placeholder="Please enter vechicle estimated value" x-mask:dynamic="$money($input)"/>
                                    <x-form-label for="estimated_value" required value="{{ __('Estimated Value of Vehicle') }}"/>
                                    <x-form-input-error name="vehicleData.estimated_value"/>
                                </div>
                            </div>
                            <div class="col-sm-3">
                                <div class="form-floating mb-3">
                                    <x-form-input type="text" name="claim_count" maxlength="25"  wire:model.defer="vehicleData.claim_count" placeholder="Please enter no of accidents"/>
                                    <x-form-label for="claim_count" required value="{{ __('No of Accidents') }}"/>
                                    <x-form-input-error name="vehicleData.claim_count"/>
                                </div>
                            </div>
                            <div class="col-sm-3">
                                <div class="form-floating mb-3">
                                    <x-select-search wire:model.defer="vehicleData.risk_id"  id="risk_id" style="{{ $errors->has('vehicleData.risk_id') ? 'border:1px solid #dc3545 !important; box-shadow:0 0 0 0.2rem rgba(220,53,69,.25);' : '' }}"
                                        aria-label="Select Risk Address" :options="$this->getallRiskAddress()??[]"	/>
                                    <x-form-label for="vehicleData.risk_id" required value="{{ __('Select Risk Address') }}" />
                                    <x-form-input-error name="vehicleData.risk_id"/>
                                </div>
                            </div>
                            <div class="col-sm-3">
                                <div class="form-floating mb-3">
                                    <x-select-search id="vehicle_type" wire:model.defer="vehicleData.vehicle_type" aria-label="Please choose Motor Type"
                                        :options="$this->allMotorType ?? []"  listner="vehicle_type"  style="{{ $errors->has('vehicleData.vehicle_type') ? 'border:1px solid #dc3545 !important; box-shadow:0 0 0 0.2rem rgba(220,53,69,.25);' : '' }}" disabled="{{ !$this->editable }}" />
                                    <x-form-label for="vehicle_type" required value="{{ __('Please choose the motor type') }}"/>
                                    <x-form-input-error name="vehicleData.vehicle_type"/>
                                </div>
                            </div>
                            <!-- <div wire:loading >
                                <div class="overlay-layer  rounded ">
                                    <div class="spinner-grow spinner-grow-sm bg-danger" style="width: 5rem; height: 5rem;"></div>
                                </div>
                            </div> -->
                            <div class="row">
                                <div class="col-sm-6" style="text-align: right;">
                                    @if($V_isUpdate)
                                        <button type="button" wire:click.prevent="vehicleDetailseditCancel" class="btn btn-danger pull-right font-weight-bolder text-uppercase px-6 py-4" wire:loading.class="spinner spinner-right spinner-white pr-15"  wire:offline.attr="disabled" wire:loading.attr="disabled">
                                            Cancel
                                        </button>
                                    @endif
                                </div>
                                <div class="col-sm-6">
                                    <button type="button" wire:click.prevent="addVehicle" class="btn btn-primary pull-right font-weight-bolder text-uppercase px-6 py-4" wire:loading.class="spinner spinner-right spinner-white pr-15"  wire:offline.attr="disabled" wire:loading.attr="disabled">
                                        @if($V_isUpdate)
                                            Update
                                        @else
                                            <i class="la la-plus"></i>Save
                                        @endif
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    @if($isPrevious)
                        <div class="card-body">
                            <div class="row">
                                <div class="col-sm-12">
                                    <button type="button" wire:target="backToStep4" wire:loading.attr="disabled" wire:offline.attr="disabled" wire:click="backToStep4" class="btn btn-danger hover-rotate-end" style="float:left;margin-left:15px;">
                                        <span>Previous</span>
                                        <span wire:loading wire:target="backToStep4" class="indicator-progress">
                                        <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                                    </span>
                                    </button>

                                    <button type="button" wire:target="saveStep5" wire:loading.attr="disabled" wire:offline.attr="disabled" wire:click="saveStep5" class="btn btn-success hover-rotate-end" style="float:right;margin-right:15px;">
                                        <span>Save & Continue</span>
                                        <span wire:loading wire:target="saveStep5" class="indicator-progress">
                                        <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                                    </span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif
    <!-- Modal -->
<div wire:ignore.self class="modal fade" id="plateChangeModal" tabindex="-1">
    <div class="modal-dialog">
        <form wire:submit.prevent="submitPlateChange" autocomplete="off">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Change Vehicle Plate</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">

                    <label>New Vehicle Plate</label>
                    <input type="text" class="form-control"
                           wire:model.defer="newPlate">

                    <label class="mt-3">Reason for Change</label>
                    <textarea class="form-control"
                              wire:model.defer="plateChangeComment"></textarea>

                    @error('newPlate') <span class="text-danger">{{ $message }}</span> @enderror
                    @error('plateChangeComment') <span class="text-danger">{{ $message }}</span> @enderror
                </div>

                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Submit</button>
                </div>

            </div>
        </form>
    </div>
</div>
<div wire:ignore.self class="modal fade" id="VehicleModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header bg-warning">
                <h5 class="modal-title">Approval Required</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <p>
                    This vehicle number is <strong>not a BW registered vehicle</strong>.
                    Manager approval is required.
                </p>

                <label>Vehicle Plate</label>
                <input type="text" class="form-control mb-3"
                       wire:model.defer="approvalPlate" readonly>

                <label>Reason</label>
                <textarea class="form-control"
                          wire:model.defer="approvalComment"
                          placeholder="Enter reason for approval"></textarea>

                @error('approvalComment')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>

            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary"
                        wire:click.prevent="requestVehicleApproval">
                    Request Approval
                </button>
            </div>

        </div>
    </div>
</div>


    <script>
       
      /* $('.vehicleregNum').on('keyup', function() {
    this.value = this.value.toUpperCase();
    var val = $(this).val();
    var test = /^[Bb]{1}\d{3}[a-zA-Z]{3}$/.test(val);
       
    if (val !== '' && test) {

        $.ajax({
            type: "POST",
            url: "https://graphite.alphadirect.co.bw/api/frontendpay/checkVehiclePlate",
            data: {
                vehiclePlate: val,
                _token: $('meta[name="csrf-token"]').attr('content')
            },
            dataType: "json",

            success: function(response) {
                 if (response.success) {
                    // Vehicle DOES NOT exist → allow adding
                    $('#getOTP').attr('disabled', false);
                    $('.vehicleMsg2').hide();
                } else {
                    // Vehicle EXISTS → block adding
                   $('#getOTP').attr('disabled', true);
                   $('.vehicleMsg2').show();
                    toastr.error(response.message);
                }
            },

            error: function(xhr) {
                console.log(xhr.responseText);
                toastr.error("Server error. Try again.");
            }
        });
    }
});*/
</script>
<script>
window.addEventListener('open-modal', event => {
    $('#' + event.detail.id).modal('show');
});

window.addEventListener('close-modal', event => {
    $('#' + event.detail.id).modal('hide');
});
</script>
<script>
window.addEventListener('open-modal', event => {
    const modalEl = document.getElementById(event.detail.id);
    const modal = new bootstrap.Modal(modalEl);
    modal.show();
});

window.addEventListener('close-modal', event => {
    const modalEl = document.getElementById(event.detail.id);
    const modal = bootstrap.Modal.getInstance(modalEl);
    if (modal) modal.hide();
});

window.addEventListener('reload-page', () => {
    location.reload();
});
</script>
</div>
