<div>
	<x-slot name="breadcrum">
	<!--begin::Page title-->
		<div class="page-title d-flex flex-column justify-content-center flex-wrap me-3">
			<!--begin::Title-->
			<h1 class="page-heading d-flex text-dark fw-bold fs-3 flex-column justify-content-center my-0">Policy</h1>
			<!--end::Title-->
			<!--begin::Breadcrumb-->
			<ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 my-0 pt-1">
				<!--begin::Item-->
				<li class="breadcrumb-item text-muted">
					<a href="{{route('admin-dashboard')}}" class="text-muted text-hover-primary">Home</a>
				</li>
				<!--end::Item-->
				<!--begin::Item-->
				<li class="breadcrumb-item">
					<span class="bullet bg-gray-400 w-5px h-2px"></span>
				</li>
				<!--end::Item-->
				<!--begin::Item-->
				<li class="breadcrumb-item text-muted">
					<a href="{{route('policy')}}" class="text-muted text-hover-primary">Policy Detail's</a>
				</li>
				<!--end::Item-->
				<!--begin::Item-->
				<li class="breadcrumb-item">
					<span class="bullet bg-gray-400 w-5px h-2px"></span>
				</li>
				<!--end::Item-->
				<!--begin::Item-->
				<li class="breadcrumb-item text-muted">Policy</li>
				<!--end::Item-->
			</ul>
			<!--end::Breadcrumb-->
		</div>
		<!--end::Page title-->
	</x-slot>
	<!--begin::Content container-->
	<div id="kt_app_content_container" class="app-container container-fluid" wire:loading.class="page-loading"
	x-data="{
		'step':@entangle('step'),
		hasRiskAddress: @entangle('hasRiskAddress'),
		hasMember: @entangle('hasMember'),
		hasVehicle: @entangle('hasVehicle'),
		hasDevice: @entangle('hasDevice'),
		premiumFreq: @entangle('policies.premium_freq'),
		get isManualInput() {
			return this.premiumFreq == '6';
		}
	}"
	>
		<!--begin::Page loading(append to body)-->
		<div class="page-loader flex-column bg-dark bg-opacity-25 ">
			<span class="spinner-border text-primary" role="status"></span>
			<span class="text-gray-800 fs-6 fw-semibold mt-5">Loading...</span>
		</div>
		<!--end::Page loading-->
		<!--begin::Card-->
		<div class="card">
			<!--begin::Card body-->
			<div class="card-body">
				<div class="row">
					<div class="col-sm-6">
						<div class="form-floating mb-7">
							<x-select
								wire:model.lazy="policies.premium_freq"
								id="pfreq"
								:options="$this->getPremiumFreqPropert()"
							/>
							<x-form-label for="premium_freq" required value="{{ __('Renewal Plan:') }}"  autocomplete="policies.premium_freq" />
							<x-form-input-error name="policies.premium_freq"/>
						</div>
					</div>
					<div class="col-sm-6">
					</div>
				</div>
				<div class="row">
					<div class="col-sm-3">
						<div class="form-floating mb-3">
							<x-form-date type="text" class="kt_datepicker_1" id="term_start_date" wire:model.lazy="term_start_date"  placeholder="Effective From" autocomplete="off"/>
							<x-form-label for="term_start_date" required value="{{ __('Effective From') }}"/>
							<x-form-input-error name="term_start_date"/>
						</div>
					</div>
					<div class="col-sm-3">
						<div class="form-floating mb-3">
						    @if($policies->premium_freq == "6")
								<x-form-date type="text" class="kt_datepicker_1" id="term_end_date" wire:model.lazy="policies_expiry_date" placeholder="Effective To" autocomplete="off"/>
							@else
								<x-form-input type="text" readonly wire:model.defer="policies_expiry_date" placeholder="Effective To" autocomplete="off"/>
							@endif
							<x-form-label for="policies_expiry_date" required value="{{ __('Effective To') }}"/>
							<x-form-input-error name="policies_expiry_date"/>
						</div>
					</div>
					<div class="col-sm-3">
						<div class="form-floating mb-3">
							<x-form-date type="text" class="kt_datepicker_1" id="binder_date" name="binder_date" placeholder="Binder Date"  wire:model.defer="binder_date" autocomplete="off"/>
							<x-form-label for="binder_date" value="{{ __('Binder Date') }}"/>
							<x-form-input-error name="binder_date"/>
						</div>
					</div>

                    <!-- Dropdown coming from db -->
					<div class="col-sm-3">
						<div class="form-floating mb-3">
							<x-select-search id="selectedAgency" wire:model.lazy="policies.agency_id" aria-label="Select Agency"
								:options="$this->getAgency()" />
							<x-form-label for="selectedAgency" value="{{ __('Select Agency') }}" />
							<x-form-input-error name="policies.agency_id"/>
						</div>
					</div>
					{{-- <div class="col-sm-3">
						<div class="form-floating mb-3">
							<x-select id="select_product" data-placeholder="Select an option"
								aria-label="Select Product" wire:model.defer="customer_profile.select_product"
								wire:model.lazy="customer_profile.select_product"
								:options="array('Commercial All Risk'=>'Commercial All Risk','Domestic All Risk'=>'Domestic All Risk')"
							/>
							<x-form-label for="select_product" value="{{ __('Select Product') }}"/>
							<x-form-input-error name="customer_profile.select_product"/>
						</div>
					</div> --}}
				</div>
				<div class="row">
					{{-- <div class="col-sm-3">
						<div class="form-floating mb-3">
							<x-form-input type="text" name="policy_no" wire:model.defer="policies.policyNumber"  placeholder="Policy No" autocomplete="off"/>
							<x-form-label for="policy_no" required value="{{ __('Policy No') }}"/>
							<x-form-input-error name="policies.policyNumber"/>
						</div>
					</div> --}}
					{{-- <div class="col-sm-3">
						<div class="form-floating mb-3">
							<x-form-input type="text" name="estm"  wire:model.defer="customer_profile.estm" placeholder="Estm Prem" autocomplete="off"/>
							<x-form-label for="estm" required value="{{ __('Estm Prem') }}"/>
							<x-form-input-error name="customer_profile.estm"/>
						</div>
					</div> --}}

					<!-- Dropdown coming from db -->
					<div class="col-sm-3">
						<div class="form-floating mb-3">
							<x-select-search id="agent_id"
								aria-label="Select Agent"
								:options="$this->agents"
								listner="selectedAgency"
								wire:model.defer='policies.agent_id' />
							<x-form-label for="policies.agent_id" value="{{ __('Select Agent') }}" />
							<x-form-input-error name="policies.agent_id"/>
						</div>
					</div>
				<!-- </div> -->
				<!-- <div class="row"> -->
					<!-- Fixed Dropdown -->
					<div class="col-sm-3">
						<div class="form-floating mb-3">
							<x-select name="uw_app_status" wire:model.defer="policies.uw_app_status"
								aria-label="UW. App. Status"
								:options="array(
                                    'APPWITHDRAWNUN'=>'App withdrawn, unacceptable risk',
                                    'APPWITHDRAWNPREUP'=>'App withdrawn, premium uprate',
                                    'AGENTSUBERROR'=>'Agent submitted in error',
                                    'APPDIDNOTACCEPT'=>'Applicant did not accept the quote',
                                    'AGENTREQUESTCHANGE'=>'Agent requests changes to quote',
                                    'PENDINGUW'=>'Pending UW Review',
                                    'APPROVEDUW'=>'Approved by UW',
                                    'NEEDIFNO'=>'Needs Info',
                                    'UNACCEPTABLE'=>'Unacceptable',
                                    'UWOPEN'=>'Open'
                                    )"
							/>
							<x-form-label for="uw_app_status" value="{{ __('UW. App. Status') }}"/>
							<x-form-input-error name="policies.uw_app_status"/>
						</div>
					</div>
                    <div class="col-sm-3">
                        <div class="form-floating mb-3">
                            <x-form-input type="text" name="gfs_policy_no" wire:model.defer="policies.gfs_policy_no" placeholder="GFS Policy No" disabled="{{ !$this->editable }}" autocomplete="off"/>
                            <x-form-label for="gfs_policy_no" value="{{ __('GFS Policy No:') }}"/>
                            <x-form-input-error name="policies.gfs_policy_no"/>
                        </div>
                    </div>
				</div>
				<hr>
				<div class="accordion">
					<div class="accordion-item" >
						<h2 class="accordion-header">
							<button class="accordion-button fs-4 fw-semibold"  :class="(step==1)?'show':'collapsed'" type="button">
								Applicant Information
							</button>
						</h2>
						<div class="accordion-collapse collapse " :class="(step==1)?'show':''">
							<div class="accordion-body">
								<!--begin::Applicant Form-->
								@if($step==1)
									@include('v2.livewire.policy.applicant-information')
								@endif
								<!--end::Applicant Form-->
							</div>
							<hr>
							</br>
						</div>
					</div>
					<hr>
                    {{-- <div class="accordion-item" x-show="hasRiskAddress"> --}}
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button fs-4 fw-semibold" :class="(step==2)?'show':'collapsed'" type="button"  >
                                Risk Address
                            </button>
                        </h2>
                        <div class="accordion-collapse collapse" :class="(step==2)?'show':''">
                            <div class="accordion-body">
                                @if($step==2)
                                    @livewire('policy.add-risk-address', ['policy' => $policies,'isPrevious'=>true,'termId'=>$selectedTermId,'inSide' => 'add','actionId'=>$actionId], key('add-risk-address-' . $selectedTermId))
                                @endif
                            </div>
                        </div>
                    </div>

                    @if(!empty($this->AvailableCoveragesMaster))
					<div class="accordion-item" >
						<h2 class="accordion-header">
							<button class="accordion-button fs-4 fw-semibold"  :class="(step==3)?'show':'collapsed'" type="button">
								Coverages
							</button>
						</h2>
						<div class="accordion-collapse collapse " :class="(step==3)?'show':''">
							<div class="accordion-body">
								@if($step==3)
                                    @livewire('policy.manage-coverages', ['policy'=>$policies,'editmode'=>false,'isPrevious'=>true,'termId'=>$selectedTermId,'actionId'=>$actionId,'selectedCompany'=>$customer_profile->company ?? null], key('add-coverage-'.$selectedTermId))
								@endif
							</div>
						</div>
					</div>
                    <hr>
                    @endif

                    @if(!empty($this->AvailableCoveragesMaster))

                        {{-- <hr x-show="hasRiskAddress"> --}}
                        <div class="accordion-item" x-show="hasMember">
                            <h2 class="accordion-header">
                                <button class="accordion-button fs-4 fw-semibold" :class="(step==4)?'show':'collapsed'" type="button"  >
                                    Beneficiary Details
                                </button>
                            </h2>
                            <div class="accordion-collapse collapse" :class="(step==4)?'show':''">
                                <div class="accordion-body">
                                    @if($step==4)
                                        @livewire('policy.add-member', ['policy' => $policies,'isPrevious'=>true,'termId'=>$selectedTermId,'actionId'=>$actionId], key('add-member-'.$selectedTermId))
                                    @endif
                                </div>
                            </div>
                        </div>
                        <hr x-show="hasMember">

                        <div class="accordion-item" x-show="hasVehicle">
                            <h2 class="accordion-header">
                                <button class="accordion-button fs-4 fw-semibold" :class="(step==5)?'show':'collapsed'" type="button"  >
                                    Vehicle Details
                                </button>
                            </h2>
                            <div  class="accordion-collapse collapse" :class="(step==5)?'show':''">
                                <div class="accordion-body">
                                    @if($step==5)
                                        @livewire('policy.add-vehicle', ['policy' => $policies,'isPrevious'=>true,'termId'=>$selectedTermId,'actionId'=>$actionId], key('add-vehice-'.$selectedTermId))
                                    @endif
                                </div>
                                </br>
                            </div>
                        </div>
                        <hr x-show="hasVehicle">

                        <div class="accordion-item" x-show="hasDevice">
                            <h2 class="accordion-header">
                                <button class="accordion-button fs-4 fw-semibold" :class="(step==6)?'show':'collapsed'" type="button"  >
                                    Device Details
                                </button>
                            </h2>
                            <div  class="accordion-collapse collapse" :class="(step==6)?'show':''">
                                <div class="accordion-body">
                                    @if($step==6)
                                        @livewire('policy.add-device', ['policy' => $policies,'isPrevious'=>true,'termId'=>$selectedTermId,'actionId'=>$actionId], key('add-device-' . $selectedTermId))
                                    @endif
                                </div>
                                </br>
                            </div>
                        </div>
                    @endif
				</div>
			</div>
			<!--end::Card body-->
		</div>
		<!--end::Card-->
	</div>
	<!--end::Content container-->

	<!--- Model for Exitiging costomer -->
	<div wire:ignore.self class="modal fade" tabindex="-1" data-backdrop="static" id="customemodels" role="dialog" aria-labelledby="updateModalLabel" aria-hidden="true">
		<div class="modal-dialog modal-dialog-scrollable">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title">Customer Detail's</h5>

					<!--begin::Close-->
					<div class="btn btn-icon btn-sm btn-active-light-primary ms-2" data-bs-dismiss="modal" aria-label="Close">
						<span class="svg-icon svg-icon-2x"></span>
					</div>
					<!--end::Close-->
				</div>

				<div class="modal-body">
					<div wire:loading.remove>
						<h6><span ><b>Name :</b> </span>{{$this->extingCostomer->full_name ?? ''}} </h6>
					</div>
				</div>

				<div class="modal-footer">
					<div wire:offline>
						You are now offline.
					</div>
					<button type="button"
						wire:loading.attr="disabled"
						wire:offline.attr="disabled"
						class="btn btn-light"
						data-bs-dismiss="modal">
						Close
					</button>
					<button type="button"
						wire:loading.attr="disabled"
						wire:offline.attr="disabled"
						wire:click="seletedExitingCostomer"
						class="btn btn-success" data-bs-dismiss="modal">
						Select This
					</button>
				</div>
			</div>
		</div>
	</div>
</div>
<!--- End Model for Exitiging costomer -->
@push('scripts')
<script type="text/javascript">
	document.addEventListener("DOMContentLoaded", () => {
		var modalForm = new bootstrap.Modal(document.getElementById('customemodels'), {
			keyboard: false
		})

		window.addEventListener('openModalCostomerDetails', event => {
			modalForm.show()
		});

	});
</script>
<script>
    // document.addEventListener("DOMContentLoaded", () => {
    //     Livewire.hook('component.initialized', (component) => {
    //         if (component.name=="policy.add-risk-address"){
    //             initAutocomplete();
    //         }
    //     })
    // });

    var insideCoverage = false;
    document.addEventListener("DOMContentLoaded", () => {
        Livewire.hook('component.initialized', (component) => {
            // if (component.name=="policy.add-coverage"){
            //     insideCoverage = true;
            // }

            if (component.name=="policy.add-risk-address"){
                if (insideCoverage){
                    insideCoverage = false;
                    initAutocomplete('coverage');
                }else{
                    initAutocomplete('edit');
                }
            }

			if (component.name=="policy.manage-coverage"){
                insideCoverage = true;
            }
        })
    });

    // Reinitialize datepicker for Effective To when switching to manual input
    function initTermEndDatePicker() {
        setTimeout(function() {
            var termEndDateField = document.getElementById('term_end_date');
            if (termEndDateField && !$(termEndDateField).hasClass('hasDatepicker')) {
                var arrows = {
                    leftArrow: '<i class="la la-angle-right"></i>',
                    rightArrow: '<i class="la la-angle-left"></i>'
                };
                $(termEndDateField).datepicker({
                    todayHighlight: true,
                    orientation: "bottom left",
                    templates: arrows,
                    format: "{{ config('constants.date.js_format') }}",
                });
            }
        }, 100);
    }

    document.addEventListener("DOMContentLoaded", () => {
        // Initialize on page load
        initTermEndDatePicker();

        // Initialize after Livewire updates
        Livewire.hook('message.processed', (message, component) => {
            initTermEndDatePicker();
        });
    });
</script>
@endpush


