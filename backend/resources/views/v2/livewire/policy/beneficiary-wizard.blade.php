<div>
    <div class="row">
		<div class="col-sm-12">
			<div class='card card-custom gutter-b'>
				<div class="card-header">
					<div class="card-title">
						<h3 class="card-label">Beneficiary Details</h3>
					</div>
				</div>
				<div class="card-body">
					<div class="col-sm-12">
						<div class="table-responsive">
							<table class="table align-middle table-row-dashed fs-6 gy-5 dataTable no-footer">
								<thead>
									<tr class="text-start fw-bold fs-7 text-uppercase gs-0">
                                        <th>Sr.No.</th>
										<th>Name</th>
										<th>Relation</th>
										<th>Gender</th>
										<th>Payment</th>
										<th>Passport</th>
										<th>Omang</th>
										<th>DOB</th>
										<th style="text-align:center;">Action</th>
									</tr>
								</thead>
								<tbody class="fw-semibold text-gray-600">
								    @php $i=1;@endphp
									@forelse($this->getallBeneficiaries() as $bri)
										<tr class="{!! ($loop->iteration % 2 == 0)?'even':'odd' !!}">
											<td>{{$i++}}</td>
											<td>{{$bri->first_name ?? ""}} {{$bri->middle_name ?? ""}} {{$bri->last_name ?? ""}}</td>
											<td>{{$bri->relation ?? ""}}</td>
											<td>
												@if(isset($bri->gender))
												{{($bri->gender==1)?'Male':'Female';}}
												@endif
											</td>
											<td>{{$bri->payment ?? ""}}</td>
											<td style="text-transform: uppercase">{{$bri->passport ?? ""}}</td>
											<td style="text-transform: uppercase">{{$bri->omang ?? ""}}</td>
											<td>
												@if(isset($bri->dob))
												{{ \Carbon\Carbon::createFromFormat('Y-m-d', $bri->dob)->format('d-m-Y')   }}
												@endif
											</td>
											<td>
												<div class="flex space-x-1 justify-around">
												    <button wire:click="beneficiaryDetailsedit('{{\Crypt::encrypt($bri->id)}}')"  class="btn  btn-sm btn-outline btn-outline-dashed hover-scale btn-outline-primary btn-active-light-primary">
                                                        <span class="svg-icon svg-icon-2 m-0"><svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"></path></svg></span>
                                                        <!-- Edit -->
													</button>
													<button wire:click="beneficiaryTriggerDelete('{{\Crypt::encrypt($bri->id)}}')"  class="btn  btn-sm btn-outline btn-outline-dashed hover-scale btn-outline-danger btn-active-light-danger">
                                                        <span class="svg-icon svg-icon-2 m-0"><svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg></span>
                                                        <!-- Delete -->
													</button>
												</div>
											</td>
										</tr>
									@empty
										<tr><td colspan=9>Beneficiary Details Not Present ..</td></tr>
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
						@if($B_isUpdate)
							Update
						@else
							Add
						@endif	Beneficiary Details</h3>
					</div>
				</div>
                <div class="card-body">
                    <livewire:common.excel-import  :policy="$policies" :for="'member'" :exportLinkName="'Beneficiary Data'"/>
                    <br>
                    <div class="row">
                        <div class="col-sm-3">
                            <div class="form-floating mb-3">
                                <x-form-input type="text" name="first_name" wire:model.defer="beneficiaryData.first_name" placeholder="First Name"/>
                                <x-form-label for="first_name" required value="{{ __('First Name') }}"/>
                                <x-form-input-error name="beneficiaryData.first_name"/>
                            </div>
                        </div>
                        <div class="col-sm-3">
                            <div class="form-floating mb-3">
                                <x-form-input type="text"  name="middle_name" wire:model.defer="beneficiaryData.middle_name" placeholder="Middle Name"/>
                                <x-form-label for="middle_name" required value="{{ __('Middle Name') }}"/>
                                <x-form-input-error name="beneficiaryData.middle_name"/>
                            </div>
                        </div>
                        <div class="col-sm-3">
                            <div class="form-floating mb-3">
                                <x-form-input type="text"  name="last_name" wire:model.defer="beneficiaryData.last_name" placeholder="Beneficiary Last Name"/>
                                <x-form-label for="last_name" required value="{{ __('Last Name') }}"/>
                                <x-form-input-error name="beneficiaryData.last_name"/>
                            </div>
                        </div>
                        <div class="col-sm-3">
                            <div class="form-floating mb-3">
                                <x-form-input type="text" name="relation" wire:model.defer="beneficiaryData.relation" placeholder="beneficiary relation"/>
                                <x-form-label for="relation" required value="{{ __('Which beneficiary would you like to add ?') }}"/>
                                <x-form-input-error name="beneficiaryData.relation"/>
                            </div>
                        </div>
                        <div class="col-sm-3">
                            <div class="form-floating mb-3">
                                <x-select aria-label="Select Gender" name="gender" wire:model.defer="beneficiaryData.gender"
                                :options="array('1'=>'Male','0'=>'Female')"/>
                                <x-form-label for="gender" required value="{{ __('Select Gender') }}"/>
                                <x-form-input-error name="beneficiaryData.gender"/>
                            </div>
                        </div>
                        <div class="col-sm-3">
                            <div class="form-floating mb-3">
                                <x-form-input type="number"  name="beneficiaryPayment" wire:model.defer="beneficiaryData.payment" placeholder="Payment(%)"/>
                                <x-form-label for="beneficiaryPayment" required value="{{ __('Payment %') }}"/>
                                <x-form-input-error name="beneficiaryData.payment"/>
                            </div>
                        </div>
                        <div class="col-sm-3">
                            <div class="form-floating mb-3">
                                <x-form-input type="text" name="beneficiaryOmang" wire:model.defer="beneficiaryData.omang" placeholder="Beneficiary Omang"/>
                                <x-form-label for="beneficiaryOmang" required value="{{ __('Beneficiary Omang') }}"/>
                                <x-form-input-error name="beneficiaryData.omang"/>
                            </div>
                        </div>
                        <div class="col-sm-3">
                            <div class="form-floating mb-3">
                                <x-form-input type="text" name="passport" wire:model.defer="beneficiaryData.passport" placeholder="Beneficiary Passport"/>
                                <x-form-label for="passport" required value="{{ __('Beneficiary Passport') }}"/>
                                <x-form-input-error name="beneficiaryData.passport"/>
                            </div>
                        </div>
                        {{-- <div class="col-sm-3">
                            <div class="form-floating mb-3">
                                <x-form-input type="date"  name="dob" wire:model.defer="beneficiaryData.dob" placeholder="Date Of Birth"/>
                                <x-form-label for="dob" required value="{{ __('Date Of Birth') }}"/>
                                <x-form-input-error name="beneficiaryData.dob"/>
                            </div>
                        </div> --}}
                        <div class="col-sm-3">
                            <div class="form-floating mb-3">
                                <x-form-date type="text" class="kt_datepicker_1" id="beneficiaryData_dob" name="beneficiaryData_dob" wire:model="beneficiary_date_of_birth" placeholder="Date Of Birth"/>
                                <x-form-label for="beneficiaryData_dob" required value="{{ __('Date Of Birth') }}"/>
                                <x-form-input-error name="beneficiaryData_dob"/>
                            </div>
                        </div>
                        <!-- <div wire:loading >
                            <div class="overlay-layer  rounded ">
                                <div class="spinner-grow spinner-grow-sm bg-danger" style="width: 5rem; height: 5rem;"></div>
                            </div>
                        </div> -->
                        <div class="row">
                            <div class="col-sm-6" style="text-align: right;">
                                @if($B_isUpdate)
                                    <button type="button" wire:click.prevent="beneficiaryDetailseditCancel" class="btn btn-danger pull-right font-weight-bolder text-uppercase px-6 py-4" wire:loading.class="spinner spinner-right spinner-white pr-15"  wire:offline.attr="disabled" wire:loading.attr="disabled">
                                        Cancel
                                    </button>
                                @endif
                            </div>
                            <div class="col-sm-6">
                                <button type="button" wire:click.prevent="addBeneficiary" class="btn btn-primary pull-right font-weight-bolder text-uppercase px-6 py-4" wire:loading.class="spinner spinner-right spinner-white pr-15"  wire:offline.attr="disabled" wire:loading.attr="disabled">
                                    @if($B_isUpdate)
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
				<div class="card-footer">
					<div class="row mt-5">
                        <div class="col-sm-12">
                            <button type="button" wire:target="backToStep3" wire:loading.attr="disabled" wire:offline.attr="disabled" wire:click="backToStep3" class="btn btn-danger hover-rotate-end" style="float:left;margin-left:15px;">
                                <span>Previous</span>
                                <span wire:loading wire:target="backToStep3" class="indicator-progress">
                                    <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                                </span>
                            </button>

                            <button type="button" wire:target="saveStep4" wire:loading.attr="disabled" wire:offline.attr="disabled" wire:click="saveStep4" class="btn btn-success hover-rotate-end" style="float:right;margin-right:15px;">
                                <span>Save & Continue</span>
                                <span wire:loading wire:target="saveStep4" class="indicator-progress">
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

