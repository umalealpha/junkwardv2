<div>
    <div class="row">
		<div class="col-sm-12">
			<div class='card card-custom gutter-b'>
				<div class="card-header">
					<div class="card-title">
						<h3 class="card-label">Device Details</h3>
					</div>
				</div>
				<div class="card-body">
					<div class="col-sm-12">
						<div class="table-responsive">
							<table class="table align-middle table-row-dashed fs-6 gy-5 dataTable no-footer">
								<thead>
									<tr class="text-start fw-bold fs-7 text-uppercase gs-0">
										<th>Sr.No.</th>
										<th>Device Type</th>
										<th>IMEI</th>
										<th>Make</th>
										<th>Model</th>
										<th>Value Of The Phone</th>
										<th style="text-align:center;">Action</th>
									</tr>
								</thead>
								<tbody class="fw-semibold text-gray-600">
									@php $i=1;@endphp
									@forelse($this->getallDevices() as $device)
										<tr class="{!! ($loop->iteration % 2 == 0)?'even':'odd' !!}">
											<td>{{$i++}}</td>
											<td style="text-transform: uppercase">{{$device->device_type ?? ""}}</td>
											<td>{{$device->imei ?? ""}}</td>
											<td>{{$device->cell_phone_make ?? ""}}</td>
											<td>{{$device->cell_phone_model ?? ""}}</td>
											<td>{{$device->phone_value ?? ""}}</td>
											<td>
												<div class="flex space-x-1 justify-around">
													<span class="svg-icon svg-icon-2 m-0"><button wire:click="deviceDetailsedit('{{\Crypt::encrypt($device->id)}}')"  class="btn  btn-sm btn-outline btn-outline-dashed hover-scale btn-outline-primary btn-active-light-primary">
														<span class="svg-icon svg-icon-2 m-0"> <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"></path></svg></span>
														<!-- Edit -->
													</button>
													<button wire:click="triggerDeviceDelete('{{\Crypt::encrypt($device->id)}}')"  class="btn  btn-sm btn-outline btn-outline-dashed hover-scale btn-outline-danger btn-active-light-danger">
														<span class="svg-icon svg-icon-2 m-0"> <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg></span>
														<!-- Delete -->
													</button>
												</div>
											</td>
										</tr>
									@empty
										<td colspan=7>Device Details Not Present ..</td>
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
						@if($D_isUpdate)
							Update
						@else
							Add
						@endif	Device Details</h3>
					</div>
				</div>
				<div class="card-body" x-data="{
					device_type:@entangle('deviceData.device_type')
				}">
                    <livewire:common.excel-import  :policy="$policies" :for="'device'" :exportLinkName="'Device Data'"/>
                    <br>
					<div class="row">
						<div class="col-sm-3">
							<div class="form-floating mb-3">
								<x-select x-model="device_type" id="deviceData.device_type" wire:model.lazy="deviceData.device_type"
									aria-label="Device Type"
									:options="array('Cellphone'=>'Cellphone','Tablet'=>'Tablet','Laptop'=>'Laptop')"
								/>
								<x-form-label for="device_type" required value="{{ __('Device Type') }}"/>
								<x-form-input-error name="deviceData.device_type"/>
							</div>
						</div>
						<div class="col-sm-3">
							<div class="form-floating mb-3">
								<x-form-input type="text" wire:model.defer="deviceData.imei" placeholder="IMEI"/>
								<label for="deviceData.imei">
									<span x-show="device_type=='Cellphone'">EMEI</span>
									<span x-show="device_type=='Tablet' || device_type=='Laptop'">Serial Number</span>
									<span class="req"><strong> *</strong></span>
								</label>
								<x-form-input-error name="deviceData.imei"/>
							</div>
						</div>
						<div class="col-sm-3">
							<div class="form-floating mb-3">
								<x-select-search id="cell_phone_make" name="cell_phone_make"
									aria-label="Select"
									:options="[]"
									listner="cell_phone_make"
									wire:model.lazy='deviceData.cell_phone_make'
									value='{{ $this->deviceData->cell_phone_make }}'
								/>
								<label for="deviceData.cell_phone_make">
									<span x-text="(device_type!=null)?device_type:''"></span> Make
									<span class="req"><strong> *</strong></span>
								</label>
								<x-form-input-error name="deviceData.cell_phone_make"/>
							</div>
						</div>
						<div class="col-sm-3">
							<div class="form-floating mb-3">
								<x-select-search id="cell_phone_model"
									aria-label="Select"
									:options="[]"
									listner="cell_phone_model"
									wire:model.defer='deviceData.cell_phone_model'
								/>
								<label for="deviceData.cell_phone_model">
									<span x-text="(device_type!=null)?device_type:''"></span> Model
									<span class="req"><strong> *</strong></span>
								</label>
								<x-form-input-error name="deviceData.cell_phone_model"/>
							</div>
						</div>
					</div>
					<div class="row">
						<div class="col-sm-3">
							<div class="form-floating mb-3">
								<x-form-input type="text" wire:model.defer="deviceData.phone_value" placeholder="Please enter the amount."/>
								<x-form-label for="deviceData" required value="{{ __('Value Of The Phone') }}"/>
								<x-form-input-error name="deviceData.phone_value"/>
							</div>
						</div>
					</div>
					<div class="row">
						<div class="col-sm-6" style="text-align: right;">
							@if($D_isUpdate)
								<button type="button" wire:click.prevent="deviceDetailsEditCancel" class="btn btn-danger pull-right font-weight-bolder text-uppercase px-6 py-4" wire:loading.class="spinner spinner-right spinner-white pr-15"  wire:offline.attr="disabled" wire:loading.attr="disabled">
									Cancel
								</button>
							@endif
						</div>
						<div class="col-sm-6">
							<button type="button" wire:click.prevent="addDevice" class="btn btn-primary pull-right font-weight-bolder text-uppercase px-6 py-4" wire:loading.class="spinner spinner-right spinner-white pr-15"  wire:offline.attr="disabled" wire:loading.attr="disabled">
								@if($D_isUpdate)
									Update
								@else
								<i class="la la-plus"></i>Save
								@endif
							</button>
						</div>
					</div>

				</div>
				@if($isPrevious)
				<div class="card-footer">
					<div class="row mt-5">
						<div class="col-sm-12">
							<button type="button" wire:target="backToStep5" wire:loading.attr="disabled" wire:offline.attr="disabled" wire:click="backToStep5" class="btn btn-danger hover-rotate-end" style="float:left;margin-left:15px;">
								<span>Previous</span>
								<span wire:loading wire:target="backToStep5" class="indicator-progress">
									<span class="spinner-border spinner-border-sm align-middle ms-2"></span>
								</span>
							</button>

							<button type="button" wire:target="saveStep6" wire:loading.attr="disabled" wire:offline.attr="disabled" wire:click="saveStep6" class="btn btn-success hover-rotate-end" style="float:right;margin-right:15px;">
								<span>Save & Continue</span>
								<span wire:loading wire:target="saveStep6" class="indicator-progress">
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
