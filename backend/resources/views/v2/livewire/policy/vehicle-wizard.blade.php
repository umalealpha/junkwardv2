<div>
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
										<th style="text-align:center;">Action</th>
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
											<td>{{$vehicle->estimated_value ?? ""}}</td>
											<td>{{$vehicle->claim_count ?? ""}}</td>
											<td>
												<div class="flex space-x-1 justify-around">
												   <button wire:click="vehicleDetailsedit('{{\Crypt::encrypt($vehicle->id)}}')"  class="btn  btn-sm btn-outline btn-outline-dashed hover-scale btn-outline-primary btn-active-light-primary">
												        <span class="svg-icon svg-icon-2 m-0"><svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"></path></svg></span>
														<!-- Edit -->
													</button>
													<button wire:click="triggerVehicleDelete('{{\Crypt::encrypt($vehicle->id)}}')"  class="btn  btn-sm btn-outline btn-outline-dashed hover-scale btn-outline-danger btn-active-light-danger">
													    <span class="svg-icon svg-icon-2 m-0"><svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg></span>
														<!-- Delete -->
													</button>
												</div>
											</td>
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
                    <livewire:common.excel-import  :policy="$policies" :for="'vehicle'" :exportLinkName="'Vehicle Data'"/>
                    <br>
					<div class="row">
						<div class="col-sm-3">
							<div class="form-floating mb-3">
								<x-form-input type="text" name="vehiclePlate" maxlength="12"  wire:model.defer="vehicleData.vehiclePlate" placeholder="Vehicle Number"/>
								<x-form-label for="vehiclePlate" required value="{{ __('Vehicle Number') }}"/>
								<x-form-input-error name="vehicleData.vehiclePlate"/>
							</div>
						</div>

						<div class="col-sm-3">
							<div class="form-floating mb-3">
								<x-checkbox label="{{ __('Is it imported?') }}" id="vehicleData.is_imported" wire:model.lazy="vehicleData.is_imported"/>
								<x-form-input-error name="vehicleData.is_imported"/>
							</div>
						</div>

						<div class="col-sm-3">
							<div class="form-floating mb-3">
								<x-select-search id="vehicleDataMake"
								wire:model="vehicleData.make"
								aria-label="Select Vechicle Make"
								listner="is_imported"
								:options="$this->getVechicleMake()"
								/>
								<x-form-label for="make" required value="{{ __('Choose a make') }}"/>
								<x-form-input-error name="vehicleData.make"/>
							</div>
						</div>

						<div class="col-sm-3">
							<div class="form-floating mb-3">
								<x-select-search id="vehicleDataModel"
									wire:model.defer="vehicleData.model"
									aria-label="Select Vehicle Model"
									listner="vehicleDataMake"
									:options="[]"
								/>
								<x-form-label for="model" required value="{{ __('Choose a model') }}"/>
								<x-form-input-error name="vehicleData.model"/>
							</div>

						</div>

						<div class="col-sm-3">
							<div class="form-floating mb-3">
								<x-form-input type="text" name="engineNo" maxlength="25"  wire:model.defer="vehicleData.engineNo" placeholder="Engine Number"/>
								<x-form-label for="engineNo" required value="{{ __('Engine Number') }}"/>
								<x-form-input-error name="vehicleData.engineNo"/>
							</div>
						</div>

						<div class="col-sm-3">
							<div class="form-floating mb-3">
								<x-form-input type="text" name="chassisNo" maxlength="25"  wire:model.defer="vehicleData.chassisNo" placeholder="Engine Number"/>
								<x-form-label for="chassisNo" required value="{{ __('Chassis Number (VIN Code)') }}"/>
								<x-form-input-error name="vehicleData.chassisNo"/>
							</div>
						</div>
						<div class="col-sm-3">
							<div class="form-floating mb-3">
								<select class="form-control required @error('vehicleData.year') is-invalid @enderror" name="year" wire:model="vehicleData.year" id="year" data-live-search="true">
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
								<x-form-input type="text" name="estimated_value" maxlength="25"  wire:model.defer="vehicleData.estimated_value" placeholder="Please enter vechicle estimated value"/>
								<x-form-label for="estimated_value" required value="{{ __('Estimated Value of Vechicle') }}"/>
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
</div>

