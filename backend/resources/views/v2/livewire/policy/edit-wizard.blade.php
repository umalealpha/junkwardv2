<div>
    <x-slot name="breadcrum">
	    <!--begin::Page title-->
		<div class="page-title d-flex flex-column justify-content-center flex-wrap me-3">
			<!--begin::Title-->
			<h1 class="page-heading d-flex text-dark fw-bold fs-3 flex-column justify-content-center my-0"> Policy Details</h1>
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
					<a href="{{route('policy')}}" class="text-muted text-hover-primary">Policy</a>
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

        <div class="d-flex align-items-center gap-2 gap-lg-3">
                <!--begin::Filter menu-->

		</div>
	</x-slot>
	<div class="app-container container-fluid" wire:loading.class="page-loading"
	x-data="{
		'tab':@entangle('tab'),
		'step':1,
		hasRiskAddress: @entangle('hasRiskAddress'),
		hasMember: @entangle('hasMember'),
		hasVehicle: @entangle('hasVehicle'),
		hasDevice: @entangle('hasDevice'),
		hasCoverage: @entangle('hasCoverage'),
		hasNewCoverage: @entangle('hasNewCoverage'),
        {{-- 'legaltab':@entangle('legaltab') --}}

	}"
	>
		<!--begin::Page loading(append to body)-->
		<div class="page-loader flex-column bg-dark bg-opacity-25 ">
			<span class="spinner-border text-primary" role="status"></span>
			<span class="text-gray-800 fs-6 fw-semibold mt-5">Loading...</span>
		</div>

		<!--begin::Navbar-->
			<div class="card mb-6 mb-xl-9">
			<div class="card-body pt-9 pb-0">
					<!--begin::Details-->
					<div class="d-flex flex-wrap flex-sm-nowrap mb-6">
						<!--begin::Image-->
						<!-- <div class="d-flex flex-center flex-shrink-0 bg-light rounded w-100px h-100px w-lg-150px h-lg-150px me-7 mb-4">
							<img class="mw-50px mw-lg-75px" src="assets/media/svg/brand-logos/volicity-9.svg" alt="image" />
						</div> -->
						<!--end::Image-->
						<!--begin::Wrapper-->
						<div class="flex-grow-1">
							<!--begin::Head-->
							<div class="d-flex justify-content-between align-items-start flex-wrap mb-2">
								<!--begin::Details-->
								<div class="d-flex flex-column">
									<!--begin::Status-->
									<div class="d-flex align-items-center mb-1">
										<a href="#" class="text-gray-800 text
										-hover-primary fs-2 fw-bold me-3">{{$this->policies->policyNumber ?? "" }}</a>
										<!-- <span class="badge badge-light-danger me-auto">status</span> -->
                                        {{-- <a href="{{ route('admin.policy.policy_schedule',['policyId'=>$this->policies->id,'termId'=>$this->selectedTermId ,'actionId'=>$actionId]) }}" target="_blank" class="btn btn-sm btn-primary align-self-center">Policy Schedule</a> --}}
									</div>
									<!--end::Status-->
									<!--begin::Description-->
									<div class="d-flex flex-wrap fw-semibold mb-4 fs-5 text-gray-400">
									    <!-- {!!$this->policies->status_details ?? ''!!} -->

									    @if(($this->transaction)!= null)
									     	@if(($this->policies->trans->referenceNumber)!= null)
											    <span class="badge badge-light-info fw-bold px-4 py-3 me-6" style="color:black;">Ref: {{$this->policies->trans->referenceNumber??""}}</span>
											@else
												<span class="badge badge-light-info fw-bold px-4 py-3 me-6" style="color:black;">Ref: Not yet assigned</span>
											@endif
										@else
										    <span class="badge badge-light-info fw-bold px-4 py-3 me-6" style="color:black;">Ref: Not yet assigned</span>
										@endif

										@if(($this->newActionDates->premium)!= null)
											<span class="badge badge-light-info fw-bold px-4 py-3 me-6" style="color:black;">Premium: P {{number_format((float)$this->newActionDates?->premium ?? "", 2, '.', ',')}}</span>
										@endif

										@if ($this->action->status != "ISSUED" && ($this->action->premium)!= null)
											<span class="badge badge-light-info fw-bold px-4 py-3 me-6" style="color:black;">Estimated Premium: P {{number_format((float)$this->action?->premium ?? "", 2, '.', ',')}}</span>
										@endif
										@if ($this->action->transaction_type == "CANCEL" && ($this->previousPremium->premium)!= null)
											<span class="badge badge-light-info fw-bold px-4 py-3 me-6" style="color:red;">Estimated Previous Premium: P {{number_format((float)$this->previousPremium?->premium ?? "", 2, '.', ',')}}</span>
										@endif
										@if(($this->policies->premium_freq)!= null)
											<span class="badge badge-light-info fw-bold px-4 py-3 me-6" style="color:black;">Premium Frequency:
											@if($this->policies->premium_freq==1)
											   MONTHLY
											@elseif($this->policies->premium_freq==2)
											   3 INSTALLMENTS
											@elseif($this->policies->premium_freq==3)
											   ANNUAL
											@elseif($this->policies->premium_freq==4)
											   SEMIANNUAL
											@elseif($this->policies->premium_freq==5)
											   QUARTERLY
											@endif
										</span>
										@endif
										@if(($this->newActionDates->current_frequency_id)!= null)
											<span class="badge badge-light-info fw-bold px-4 py-3 me-6" style="color:black;">Current Premium Frequency:
											@if($this->newActionDates->current_frequency_id==1)
											   MONTHLY
											@elseif($this->newActionDates->current_frequency_id==2)
											   3 INSTALLMENTS
											@elseif($this->newActionDates->current_frequency_id==3)
											   ANNUAL
											@elseif($this->newActionDates->current_frequency_id==4)
											   SEMIANNUAL
											@elseif($this->newActionDates->current_frequency_id==5)
											   QUARTERLY
											@endif
										</span>
										@endif
                                        @if(($this->policies->gfs_policy_no)!= null)
											<span class="badge badge-light-info fw-bold px-4 py-3 me-6" style="color:black;">
                                             GFS Policy No : {{ $this->policies->gfs_policy_no }}</span>
										@endif

									</div>
									<!--end::Description-->
								</div>
								<!--end::Details-->
								<!--begin::Actions-->
								<div class="d-flex mb-4">
									@if(($this->kyc) == null)
										<span class="badge badge-light-danger fw-bold px-4 py-3 me-6"><span style="color:black;">Compliance: &nbsp;</span> KYC Non Compliant</span>
									@else
									    @if($this->kyc != null && $this->kyc->compliance == 1)
											<span class="badge badge-light-success fw-bold px-4 py-3 me-6"><span style="color:black;">Compliance: &nbsp;</span> KYC Compliant</span>
										@elseif($this->kyc != null && $this->kyc->compliance == 0)
											<span class="badge badge-light-info fw-bold px-4 py-3 me-6"><span style="color:black;">Compliance: &nbsp;</span> KYC Verification Pending</span>
										@elseif($this->kyc != null && $this->kyc->compliance == 3)
											<span class="badge badge-light-warning fw-bold px-4 py-3 me-6" style="color:#fd9107;"><span style="color:black;">Compliance: &nbsp;</span> No ID - No Documents</span>
										@else
											<span class="badge badge-light-danger fw-bold px-4 py-3 me-6"><span style="color:black;">Compliance: &nbsp;</span> KYC Non Compliant</span>
                                         @endif
									@endif

									@if ($this->policies->status == 1 && $this->kyc != null && $this->kyc->compliance == 1)
										<span class="badge badge-light-success fw-bold px-4 py-3 me-6"><span style="color:black;">Policy Status: &nbsp;</span>
											Activated,
											@if($this->transaction && ($this->transaction->status == 'SUCCESS' || $this->transaction->status == 'Success'))
											    <span style="color:var(--kt-text-success);"> Payment Success</span>
											@elseif($this->transaction && ($this->transaction->status == 'PENDING' || $this->transaction->status == 'A'))
											    <span style="color:#fd9107;"> Payment Awaiting</span>
											@elseif($this->transaction && $this->transaction->status == 'PROCESSING')
											    <span style="color:var(--kt-info);">  Payment Processing</span>
											@else
											    <span style="color:var(--kt-text-danger);"> Payment Failed</span>
											@endif
										</span>
									@elseif ($this->policies->status == 1 && $this->kyc != null && $this->kyc->compliance != 1 || $this->kyc == NULL)
										<span class="badge badge-light-danger fw-bold px-4 py-3 me-6"><span style="color:black;">Policy Status: &nbsp;</span>
										    Deactivated - KYC Pending,
											@if($this->transaction && ($this->transaction->status == 'SUCCESS' || $this->transaction->status == 'Success'))
											    <span style="color:var(--kt-text-success);"> Payment Success</span>
											@elseif($this->transaction && ($this->transaction->status == 'PENDING' || $this->transaction->status == 'A'))
											    <span style="color:#fd9107;"> Payment Awaiting</span>
											@elseif($this->transaction && $this->transaction->status == 'PROCESSING')
											    <span style="color:var(--kt-info);">  Payment Processing</span>
											@else
											    <span style="color:var(--kt-text-danger);"> Payment Failed</span>
											@endif
									    </span>
									@elseif ($this->policies->status == 2)
										<span class="badge badge-light-danger fw-bold px-4 py-3 me-6"><span style="color:black;">Policy Status: &nbsp;</span>
										    Cancelled,
											@if($this->transaction && ($this->transaction->status == 'SUCCESS' || $this->transaction->status == 'Success'))
											    <span style="color:var(--kt-text-success);"> Payment Success</span>
											@elseif($this->transaction && ($this->transaction->status == 'PENDING' || $this->transaction->status == 'A'))
											    <span style="color:#fd9107;"> Payment Pending</span>
											@elseif($this->transaction && $this->transaction->status == 'PROCESSING')
											    <span style="color:var(--kt-info);">  Payment Processing</span>
											@else
											    <span style="color:var(--kt-text-danger);"> Payment Failed</span>
											@endif
									    </span>
										<span class="badge badge-light-info fw-bold px-4 py-3 me-6" style="color:#1591DB;">
											@if($this->feedback != null)
											   {{ $this->feedback->reason.'-'. $this->feedback->circumstances.' '.$this->feedback->other_company }}
											@else
											   No Feedback Found
											@endif
										</span>
									@elseif ($this->policies->status == 3)
										<span class="badge badge-light-danger fw-bold px-4 py-3 me-6"><span style="color:black;">Policy Status: &nbsp;</span>
										    Expired,
											@if ($this->transaction && ($this->transaction->status == 'SUCCESS' || $this->transaction->status == 'Success'))
											    <span style="color:var(--kt-text-success);"> Payment Success</span>
											@elseif($this->transaction && ($this->transaction->status == 'PENDING' || $this->transaction->status == 'A'))
											    <span style="color:#fd9107;"> Payment Pending</span>
											@elseif($this->transaction && $this->transaction->status == 'PROCESSING')
											    <span style="color:var(--kt-info);">  Payment Processing</span>
											@else
											    <span style="color:var(--kt-text-danger);"> Payment Failed</span>
											@endif
									    </span>

										<span class="badge badge-light-info fw-bold px-4 py-3 me-6" style="color:#1591DB;">
											@if ($this->feedback != null)
													{{ $this->feedback->reason . '-' . $this->feedback->circumstances . ' ' . $this->feedback->other_company }}
											@else
												No Feedback Found
											@endif
										</span>

									@else
										<span class="badge badge-light-danger fw-bold px-4 py-3 me-6"><span style="color:black;">Policy Status: &nbsp;</span>
										    Deactivated,
											@if($this->transaction && ($this->transaction->status == 'SUCCESS' || $this->transaction->status == 'Success'))
											   <span style="color:var(--kt-text-success);"> Payment Success</span>
											@elseif($this->transaction && ($this->transaction->status == 'PENDING' || $this->transaction->status == 'A'))
											   <span style="color:#fd9107;"> Payment Pending</span>
											@elseif($this->transaction && $this->transaction->status == 'PROCESSING')
											   <span style="color:var(--kt-info);"> Payment Processing</span>
											@else
											   <span style="color:var(--kt-text-danger);"> Payment Failed</span>
											@endif
									    </span>
									@endif

									@if($this->policies->agent_id != NULL)
										@if($u = $this->policies->user)
											<span class="badge badge-light-success fw-bold px-4 py-3 me-6"><span style="color:black;">Agent: &nbsp;</span> {!! $u->full_name ?? " -- " !!}</span>
										@else
											<span class="badge badge-light-danger fw-bold px-4 py-3 me-6"><span style="color:black;">Agent: &nbsp;</span> Name not fetched </span>
										@endif
									@else
										<span class="badge badge-light-warning fw-bold px-4 py-3 me-6" style="color:#fd9107;"><span style="color:black;">Agent: &nbsp;</span> Not assigned </span>
									@endif

									<!-- <a href="#" class="btn btn-sm btn-bg-light btn-active-color-primary me-3" data-bs-toggle="modal" data-bs-target="#kt_modal_users_search">Add User</a> -->
									<!-- <a href="#" class="btn btn-sm btn-primary me-3" data-bs-toggle="modal" data-bs-target="#kt_modal_new_target">Add Target</a> -->
									<!--begin::Menu-->
									<!-- <div class="me-0"> -->
										<!-- <button class="btn btn-sm btn-icon btn-bg-light btn-active-color-primary" data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
											<i class="bi bi-three-dots fs-3"></i>
										</button> -->
										<!--begin::Menu 3-->
										<!-- <div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-800 menu-state-bg-light-primary fw-semibold w-200px py-3" data-kt-menu="true"> -->
											<!--begin::Heading-->
											<!-- <div class="menu-item px-3">
												<div class="menu-content text-muted pb-2 px-3 fs-7 text-uppercase">Payments</div>
											</div> -->
											<!--end::Heading-->
											<!--begin::Menu item-->
											<!-- <div class="menu-item px-3">
												<a href="#" class="menu-link px-3">Create Invoice</a>
											</div> -->
											<!--end::Menu item-->
											<!--begin::Menu item-->
											<!-- <div class="menu-item px-3">
												<a href="#" class="menu-link flex-stack px-3">Create Payment
												<i class="fas fa-exclamation-circle ms-2 fs-7" data-bs-toggle="tooltip" title="Specify a target name for future usage and reference"></i></a>
											</div> -->
											<!--end::Menu item-->
											<!--begin::Menu item-->
											<!-- <div class="menu-item px-3">
												<a href="#" class="menu-link px-3">Generate Bill</a>
											</div> -->
											<!--end::Menu item-->
											<!--begin::Menu item-->
											<!-- <div class="menu-item px-3" data-kt-menu-trigger="hover" data-kt-menu-placement="right-end">
												<a href="#" class="menu-link px-3">
													<span class="menu-title">Subscription</span>
													<span class="menu-arrow"></span>
												</a> -->
												<!--begin::Menu sub-->
												<!-- <div class="menu-sub menu-sub-dropdown w-175px py-4"> -->
													<!--begin::Menu item-->
													<!-- <div class="menu-item px-3">
														<a href="#" class="menu-link px-3">Plans</a>
													</div> -->
													<!--end::Menu item-->
													<!--begin::Menu item-->
													<!-- <div class="menu-item px-3">
														<a href="#" class="menu-link px-3">Billing</a>
													</div> -->
													<!--end::Menu item-->
													<!--begin::Menu item-->
													<!-- <div class="menu-item px-3">
														<a href="#" class="menu-link px-3">Statements</a>
													</div> -->
													<!--end::Menu item-->
													<!--begin::Menu separator-->
													<!-- <div class="separator my-2"></div> -->
													<!--end::Menu separator-->
													<!--begin::Menu item-->
													<!-- <div class="menu-item px-3"> -->
														<!-- <div class="menu-content px-3"> -->
															<!--begin::Switch-->
															<!-- <label class="form-check form-switch form-check-custom form-check-solid"> -->
																<!--begin::Input-->
																<!-- <input class="form-check-input w-30px h-20px" type="checkbox" value="1" checked="checked" name="notifications" /> -->
																<!--end::Input-->
																<!--end::Label-->
																<!-- <span class="form-check-label text-muted fs-6">Recuring</span> -->
																<!--end::Label-->
															<!-- </label> -->
															<!--end::Switch-->
														<!-- </div> -->
													<!-- </div> -->
													<!--end::Menu item-->
												<!-- </div> -->
												<!--end::Menu sub-->
											<!-- </div> -->
											<!--end::Menu item-->
											<!--begin::Menu item-->
											<!-- <div class="menu-item px-3 my-1">
												<a href="#" class="menu-link px-3">Settings</a>
											</div> -->
											<!--end::Menu item-->
										<!-- </div> -->
										<!--end::Menu 3-->
									<!-- </div> -->
									<!--end::Menu-->
								</div>
								<!--end::Actions-->
							</div>
							<!--end::Head-->
							<!--begin::Info-->
							<div class="d-flex flex-wrap justify-content-start">
								<!--begin::Stats-->
								<!-- <div class="d-flex flex-wrap"> -->
									<!--begin::Stat-->
									<!-- <div class="border border-gray-300 border-dashed rounded min-w-125px py-3 px-4 me-6 mb-3"> -->
										<!--begin::Number-->
										<!-- <div class="d-flex align-items-center">
											<div class="fs-4 fw-bold"></div>
										</div> -->
										<!--end::Number-->
										<!--begin::Label-->
										<!-- <div class="fw-semibold fs-6 text-gray-400"> Date</div> -->
										<!--end::Label-->
									<!-- </div> -->
									<!--end::Stat-->
									@if(($this->policies->created_at)!=null)
									<!--begin::Stat-->
									<div class="border border-gray-300 border-dashed rounded min-w-125px py-3 px-4 me-6 mb-3">
										<!--begin::Number-->
										<div class="d-flex align-items-center">
											<!--begin::Svg Icon -->
											<span class="svg-icon svg-icon-2 svg-icon-info me-3">
												<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
													<path opacity="0.3" d="M21 22H3C2.4 22 2 21.6 2 21V5C2 4.4 2.4 4 3 4H21C21.6 4 22 4.4 22 5V21C22 21.6 21.6 22 21 22Z" fill="currentColor"></path>
													<path d="M6 6C5.4 6 5 5.6 5 5V3C5 2.4 5.4 2 6 2C6.6 2 7 2.4 7 3V5C7 5.6 6.6 6 6 6ZM11 5V3C11 2.4 10.6 2 10 2C9.4 2 9 2.4 9 3V5C9 5.6 9.4 6 10 6C10.6 6 11 5.6 11 5ZM15 5V3C15 2.4 14.6 2 14 2C13.4 2 13 2.4 13 3V5C13 5.6 13.4 6 14 6C14.6 6 15 5.6 15 5ZM19 5V3C19 2.4 18.6 2 18 2C17.4 2 17 2.4 17 3V5C17 5.6 17.4 6 18 6C18.6 6 19 5.6 19 5Z" fill="currentColor"></path>
													<path d="M8.8 13.1C9.2 13.1 9.5 13 9.7 12.8C9.9 12.6 10.1 12.3 10.1 11.9C10.1 11.6 10 11.3 9.8 11.1C9.6 10.9 9.3 10.8 9 10.8C8.8 10.8 8.59999 10.8 8.39999 10.9C8.19999 11 8.1 11.1 8 11.2C7.9 11.3 7.8 11.4 7.7 11.6C7.6 11.8 7.5 11.9 7.5 12.1C7.5 12.2 7.4 12.2 7.3 12.3C7.2 12.4 7.09999 12.4 6.89999 12.4C6.69999 12.4 6.6 12.3 6.5 12.2C6.4 12.1 6.3 11.9 6.3 11.7C6.3 11.5 6.4 11.3 6.5 11.1C6.6 10.9 6.8 10.7 7 10.5C7.2 10.3 7.49999 10.1 7.89999 10C8.29999 9.90003 8.60001 9.80003 9.10001 9.80003C9.50001 9.80003 9.80001 9.90003 10.1 10C10.4 10.1 10.7 10.3 10.9 10.4C11.1 10.5 11.3 10.8 11.4 11.1C11.5 11.4 11.6 11.6 11.6 11.9C11.6 12.3 11.5 12.6 11.3 12.9C11.1 13.2 10.9 13.5 10.6 13.7C10.9 13.9 11.2 14.1 11.4 14.3C11.6 14.5 11.8 14.7 11.9 15C12 15.3 12.1 15.5 12.1 15.8C12.1 16.2 12 16.5 11.9 16.8C11.8 17.1 11.5 17.4 11.3 17.7C11.1 18 10.7 18.2 10.3 18.3C9.9 18.4 9.5 18.5 9 18.5C8.5 18.5 8.1 18.4 7.7 18.2C7.3 18 7 17.8 6.8 17.6C6.6 17.4 6.4 17.1 6.3 16.8C6.2 16.5 6.10001 16.3 6.10001 16.1C6.10001 15.9 6.2 15.7 6.3 15.6C6.4 15.5 6.6 15.4 6.8 15.4C6.9 15.4 7.00001 15.4 7.10001 15.5C7.20001 15.6 7.3 15.6 7.3 15.7C7.5 16.2 7.7 16.6 8 16.9C8.3 17.2 8.6 17.3 9 17.3C9.2 17.3 9.5 17.2 9.7 17.1C9.9 17 10.1 16.8 10.3 16.6C10.5 16.4 10.5 16.1 10.5 15.8C10.5 15.3 10.4 15 10.1 14.7C9.80001 14.4 9.50001 14.3 9.10001 14.3C9.00001 14.3 8.9 14.3 8.7 14.3C8.5 14.3 8.39999 14.3 8.39999 14.3C8.19999 14.3 7.99999 14.2 7.89999 14.1C7.79999 14 7.7 13.8 7.7 13.7C7.7 13.5 7.79999 13.4 7.89999 13.2C7.99999 13 8.2 13 8.5 13H8.8V13.1ZM15.3 17.5V12.2C14.3 13 13.6 13.3 13.3 13.3C13.1 13.3 13 13.2 12.9 13.1C12.8 13 12.7 12.8 12.7 12.6C12.7 12.4 12.8 12.3 12.9 12.2C13 12.1 13.2 12 13.6 11.8C14.1 11.6 14.5 11.3 14.7 11.1C14.9 10.9 15.2 10.6 15.5 10.3C15.8 10 15.9 9.80003 15.9 9.70003C15.9 9.60003 16.1 9.60004 16.3 9.60004C16.5 9.60004 16.7 9.70003 16.8 9.80003C16.9 9.90003 17 10.2 17 10.5V17.2C17 18 16.7 18.4 16.2 18.4C16 18.4 15.8 18.3 15.6 18.2C15.4 18.1 15.3 17.8 15.3 17.5Z" fill="currentColor"></path>
												</svg>
											</span>
											<!--end::Svg Icon-->
											<!-- <div class="fs-4 fw-bold" data-kt-countup="true" data-kt-countup-value="75">0</div> -->
											<div class="fs-4 fw-bold" >
                                                {{ Carbon\Carbon::parse($this->policies->created_at)->format('d/m/Y') }}
                                            </div>
										</div>
										<!--end::Number-->
										<!--begin::Label-->
										<div class="fw-semibold fs-6 text-gray-400">Created Date</div>
										<!--end::Label-->
									</div>
									<!--end::Stat-->
									@endif

									{{-- @if(($this->term_start_date)!=null)
									<!--begin::Stat-->
									<div class="border border-gray-300 border-dashed rounded min-w-125px py-3 px-4 me-6 mb-3">
										<!--begin::Number-->
										<div class="d-flex align-items-center">
											<!--begin::Svg Icon -->
											<!-- <span class="svg-icon svg-icon-6 svg-icon-danger me-3">
												<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
													<path d="M22 12C22 17.5 17.5 22 12 22C6.5 22 2 17.5 2 12C2 6.5 6.5 2 12 2C17.5 2 22 6.5 22 12ZM12 6C8.7 6 6 8.7 6 12C6 15.3 8.7 18 12 18C15.3 18 18 15.3 18 12C18 8.7 15.3 6 12 6Z" fill="currentColor"></path>
												</svg>
											</span> -->
											<span class="svg-icon svg-icon-2 svg-icon-danger me-3" style="color:#fd9107;">
												<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
													<path opacity="0.3" d="M21 22H3C2.4 22 2 21.6 2 21V5C2 4.4 2.4 4 3 4H21C21.6 4 22 4.4 22 5V21C22 21.6 21.6 22 21 22Z" fill="currentColor"></path>
													<path d="M6 6C5.4 6 5 5.6 5 5V3C5 2.4 5.4 2 6 2C6.6 2 7 2.4 7 3V5C7 5.6 6.6 6 6 6ZM11 5V3C11 2.4 10.6 2 10 2C9.4 2 9 2.4 9 3V5C9 5.6 9.4 6 10 6C10.6 6 11 5.6 11 5ZM15 5V3C15 2.4 14.6 2 14 2C13.4 2 13 2.4 13 3V5C13 5.6 13.4 6 14 6C14.6 6 15 5.6 15 5ZM19 5V3C19 2.4 18.6 2 18 2C17.4 2 17 2.4 17 3V5C17 5.6 17.4 6 18 6C18.6 6 19 5.6 19 5Z" fill="currentColor"></path>
													<path d="M8.8 13.1C9.2 13.1 9.5 13 9.7 12.8C9.9 12.6 10.1 12.3 10.1 11.9C10.1 11.6 10 11.3 9.8 11.1C9.6 10.9 9.3 10.8 9 10.8C8.8 10.8 8.59999 10.8 8.39999 10.9C8.19999 11 8.1 11.1 8 11.2C7.9 11.3 7.8 11.4 7.7 11.6C7.6 11.8 7.5 11.9 7.5 12.1C7.5 12.2 7.4 12.2 7.3 12.3C7.2 12.4 7.09999 12.4 6.89999 12.4C6.69999 12.4 6.6 12.3 6.5 12.2C6.4 12.1 6.3 11.9 6.3 11.7C6.3 11.5 6.4 11.3 6.5 11.1C6.6 10.9 6.8 10.7 7 10.5C7.2 10.3 7.49999 10.1 7.89999 10C8.29999 9.90003 8.60001 9.80003 9.10001 9.80003C9.50001 9.80003 9.80001 9.90003 10.1 10C10.4 10.1 10.7 10.3 10.9 10.4C11.1 10.5 11.3 10.8 11.4 11.1C11.5 11.4 11.6 11.6 11.6 11.9C11.6 12.3 11.5 12.6 11.3 12.9C11.1 13.2 10.9 13.5 10.6 13.7C10.9 13.9 11.2 14.1 11.4 14.3C11.6 14.5 11.8 14.7 11.9 15C12 15.3 12.1 15.5 12.1 15.8C12.1 16.2 12 16.5 11.9 16.8C11.8 17.1 11.5 17.4 11.3 17.7C11.1 18 10.7 18.2 10.3 18.3C9.9 18.4 9.5 18.5 9 18.5C8.5 18.5 8.1 18.4 7.7 18.2C7.3 18 7 17.8 6.8 17.6C6.6 17.4 6.4 17.1 6.3 16.8C6.2 16.5 6.10001 16.3 6.10001 16.1C6.10001 15.9 6.2 15.7 6.3 15.6C6.4 15.5 6.6 15.4 6.8 15.4C6.9 15.4 7.00001 15.4 7.10001 15.5C7.20001 15.6 7.3 15.6 7.3 15.7C7.5 16.2 7.7 16.6 8 16.9C8.3 17.2 8.6 17.3 9 17.3C9.2 17.3 9.5 17.2 9.7 17.1C9.9 17 10.1 16.8 10.3 16.6C10.5 16.4 10.5 16.1 10.5 15.8C10.5 15.3 10.4 15 10.1 14.7C9.80001 14.4 9.50001 14.3 9.10001 14.3C9.00001 14.3 8.9 14.3 8.7 14.3C8.5 14.3 8.39999 14.3 8.39999 14.3C8.19999 14.3 7.99999 14.2 7.89999 14.1C7.79999 14 7.7 13.8 7.7 13.7C7.7 13.5 7.79999 13.4 7.89999 13.2C7.99999 13 8.2 13 8.5 13H8.8V13.1ZM15.3 17.5V12.2C14.3 13 13.6 13.3 13.3 13.3C13.1 13.3 13 13.2 12.9 13.1C12.8 13 12.7 12.8 12.7 12.6C12.7 12.4 12.8 12.3 12.9 12.2C13 12.1 13.2 12 13.6 11.8C14.1 11.6 14.5 11.3 14.7 11.1C14.9 10.9 15.2 10.6 15.5 10.3C15.8 10 15.9 9.80003 15.9 9.70003C15.9 9.60003 16.1 9.60004 16.3 9.60004C16.5 9.60004 16.7 9.70003 16.8 9.80003C16.9 9.90003 17 10.2 17 10.5V17.2C17 18 16.7 18.4 16.2 18.4C16 18.4 15.8 18.3 15.6 18.2C15.4 18.1 15.3 17.8 15.3 17.5Z" fill="currentColor"></path>
												</svg>
											</span>
											<!--end::Svg Icon-->
											<!-- <div class="fs-4 fw-bold" data-kt-countup="true" data-kt-countup-value="75">0</div> -->
											<div class="fs-4 fw-bold" >
                                                {{ $this->term_start_date }}
											</div>
										</div>
										<!--end::Number-->
										<!--begin::Label-->
										<div class="fw-semibold fs-6 text-gray-400">Effective From Date</div>
										<!--end::Label-->
									</div>
									<!--end::Stat-->
									@endif


									@if(($this->policies_expiry_date)!=null)
									<!--begin::Stat-->
									<div class="border border-gray-300 border-dashed rounded min-w-125px py-3 px-4 me-6 mb-3">
										<!--begin::Number-->
										<div class="d-flex align-items-center">
											<!--begin::Svg Icon -->
											<!-- <span class="svg-icon svg-icon-6 svg-icon-danger me-3">
												<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
													<path d="M22 12C22 17.5 17.5 22 12 22C6.5 22 2 17.5 2 12C2 6.5 6.5 2 12 2C17.5 2 22 6.5 22 12ZM12 6C8.7 6 6 8.7 6 12C6 15.3 8.7 18 12 18C15.3 18 18 15.3 18 12C18 8.7 15.3 6 12 6Z" fill="currentColor"></path>
												</svg>
											</span> -->
											<span class="svg-icon svg-icon-2 svg-icon-danger me-3" style="color:#3295d0;">
												<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
													<path opacity="0.3" d="M21 22H3C2.4 22 2 21.6 2 21V5C2 4.4 2.4 4 3 4H21C21.6 4 22 4.4 22 5V21C22 21.6 21.6 22 21 22Z" fill="currentColor"></path>
													<path d="M6 6C5.4 6 5 5.6 5 5V3C5 2.4 5.4 2 6 2C6.6 2 7 2.4 7 3V5C7 5.6 6.6 6 6 6ZM11 5V3C11 2.4 10.6 2 10 2C9.4 2 9 2.4 9 3V5C9 5.6 9.4 6 10 6C10.6 6 11 5.6 11 5ZM15 5V3C15 2.4 14.6 2 14 2C13.4 2 13 2.4 13 3V5C13 5.6 13.4 6 14 6C14.6 6 15 5.6 15 5ZM19 5V3C19 2.4 18.6 2 18 2C17.4 2 17 2.4 17 3V5C17 5.6 17.4 6 18 6C18.6 6 19 5.6 19 5Z" fill="currentColor"></path>
													<path d="M8.8 13.1C9.2 13.1 9.5 13 9.7 12.8C9.9 12.6 10.1 12.3 10.1 11.9C10.1 11.6 10 11.3 9.8 11.1C9.6 10.9 9.3 10.8 9 10.8C8.8 10.8 8.59999 10.8 8.39999 10.9C8.19999 11 8.1 11.1 8 11.2C7.9 11.3 7.8 11.4 7.7 11.6C7.6 11.8 7.5 11.9 7.5 12.1C7.5 12.2 7.4 12.2 7.3 12.3C7.2 12.4 7.09999 12.4 6.89999 12.4C6.69999 12.4 6.6 12.3 6.5 12.2C6.4 12.1 6.3 11.9 6.3 11.7C6.3 11.5 6.4 11.3 6.5 11.1C6.6 10.9 6.8 10.7 7 10.5C7.2 10.3 7.49999 10.1 7.89999 10C8.29999 9.90003 8.60001 9.80003 9.10001 9.80003C9.50001 9.80003 9.80001 9.90003 10.1 10C10.4 10.1 10.7 10.3 10.9 10.4C11.1 10.5 11.3 10.8 11.4 11.1C11.5 11.4 11.6 11.6 11.6 11.9C11.6 12.3 11.5 12.6 11.3 12.9C11.1 13.2 10.9 13.5 10.6 13.7C10.9 13.9 11.2 14.1 11.4 14.3C11.6 14.5 11.8 14.7 11.9 15C12 15.3 12.1 15.5 12.1 15.8C12.1 16.2 12 16.5 11.9 16.8C11.8 17.1 11.5 17.4 11.3 17.7C11.1 18 10.7 18.2 10.3 18.3C9.9 18.4 9.5 18.5 9 18.5C8.5 18.5 8.1 18.4 7.7 18.2C7.3 18 7 17.8 6.8 17.6C6.6 17.4 6.4 17.1 6.3 16.8C6.2 16.5 6.10001 16.3 6.10001 16.1C6.10001 15.9 6.2 15.7 6.3 15.6C6.4 15.5 6.6 15.4 6.8 15.4C6.9 15.4 7.00001 15.4 7.10001 15.5C7.20001 15.6 7.3 15.6 7.3 15.7C7.5 16.2 7.7 16.6 8 16.9C8.3 17.2 8.6 17.3 9 17.3C9.2 17.3 9.5 17.2 9.7 17.1C9.9 17 10.1 16.8 10.3 16.6C10.5 16.4 10.5 16.1 10.5 15.8C10.5 15.3 10.4 15 10.1 14.7C9.80001 14.4 9.50001 14.3 9.10001 14.3C9.00001 14.3 8.9 14.3 8.7 14.3C8.5 14.3 8.39999 14.3 8.39999 14.3C8.19999 14.3 7.99999 14.2 7.89999 14.1C7.79999 14 7.7 13.8 7.7 13.7C7.7 13.5 7.79999 13.4 7.89999 13.2C7.99999 13 8.2 13 8.5 13H8.8V13.1ZM15.3 17.5V12.2C14.3 13 13.6 13.3 13.3 13.3C13.1 13.3 13 13.2 12.9 13.1C12.8 13 12.7 12.8 12.7 12.6C12.7 12.4 12.8 12.3 12.9 12.2C13 12.1 13.2 12 13.6 11.8C14.1 11.6 14.5 11.3 14.7 11.1C14.9 10.9 15.2 10.6 15.5 10.3C15.8 10 15.9 9.80003 15.9 9.70003C15.9 9.60003 16.1 9.60004 16.3 9.60004C16.5 9.60004 16.7 9.70003 16.8 9.80003C16.9 9.90003 17 10.2 17 10.5V17.2C17 18 16.7 18.4 16.2 18.4C16 18.4 15.8 18.3 15.6 18.2C15.4 18.1 15.3 17.8 15.3 17.5Z" fill="currentColor"></path>
												</svg>
											</span>
											<!--end::Svg Icon-->
											<!-- <div class="fs-4 fw-bold" data-kt-countup="true" data-kt-countup-value="75">0</div> -->
											<div class="fs-4 fw-bold" >
                                                {{ $this->policies_expiry_date }}
											</div>
										</div>
										<!--end::Number-->
										<!--begin::Label-->
										<div class="fw-semibold fs-6 text-gray-400">Effective To Date</div>
										<!--end::Label-->
									</div>
									<!--end::Stat-->
									@endif --}}

									@if(($this->customer_profile->binder_date)!=null)
									<!--begin::Stat-->
									<div class="border border-gray-300 border-dashed rounded min-w-125px py-3 px-4 me-6 mb-3">
										<!--begin::Number-->
										<div class="d-flex align-items-center">
											<!--begin::Svg Icon -->
											<!-- <span class="svg-icon svg-icon-6 svg-icon-danger me-3">
												<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
													<path d="M22 12C22 17.5 17.5 22 12 22C6.5 22 2 17.5 2 12C2 6.5 6.5 2 12 2C17.5 2 22 6.5 22 12ZM12 6C8.7 6 6 8.7 6 12C6 15.3 8.7 18 12 18C15.3 18 18 15.3 18 12C18 8.7 15.3 6 12 6Z" fill="currentColor"></path>
												</svg>
											</span> -->
											<span class="svg-icon svg-icon-2 svg-icon-danger me-3" style="color:#c11da8;">
												<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
													<path opacity="0.3" d="M21 22H3C2.4 22 2 21.6 2 21V5C2 4.4 2.4 4 3 4H21C21.6 4 22 4.4 22 5V21C22 21.6 21.6 22 21 22Z" fill="currentColor"></path>
													<path d="M6 6C5.4 6 5 5.6 5 5V3C5 2.4 5.4 2 6 2C6.6 2 7 2.4 7 3V5C7 5.6 6.6 6 6 6ZM11 5V3C11 2.4 10.6 2 10 2C9.4 2 9 2.4 9 3V5C9 5.6 9.4 6 10 6C10.6 6 11 5.6 11 5ZM15 5V3C15 2.4 14.6 2 14 2C13.4 2 13 2.4 13 3V5C13 5.6 13.4 6 14 6C14.6 6 15 5.6 15 5ZM19 5V3C19 2.4 18.6 2 18 2C17.4 2 17 2.4 17 3V5C17 5.6 17.4 6 18 6C18.6 6 19 5.6 19 5Z" fill="currentColor"></path>
													<path d="M8.8 13.1C9.2 13.1 9.5 13 9.7 12.8C9.9 12.6 10.1 12.3 10.1 11.9C10.1 11.6 10 11.3 9.8 11.1C9.6 10.9 9.3 10.8 9 10.8C8.8 10.8 8.59999 10.8 8.39999 10.9C8.19999 11 8.1 11.1 8 11.2C7.9 11.3 7.8 11.4 7.7 11.6C7.6 11.8 7.5 11.9 7.5 12.1C7.5 12.2 7.4 12.2 7.3 12.3C7.2 12.4 7.09999 12.4 6.89999 12.4C6.69999 12.4 6.6 12.3 6.5 12.2C6.4 12.1 6.3 11.9 6.3 11.7C6.3 11.5 6.4 11.3 6.5 11.1C6.6 10.9 6.8 10.7 7 10.5C7.2 10.3 7.49999 10.1 7.89999 10C8.29999 9.90003 8.60001 9.80003 9.10001 9.80003C9.50001 9.80003 9.80001 9.90003 10.1 10C10.4 10.1 10.7 10.3 10.9 10.4C11.1 10.5 11.3 10.8 11.4 11.1C11.5 11.4 11.6 11.6 11.6 11.9C11.6 12.3 11.5 12.6 11.3 12.9C11.1 13.2 10.9 13.5 10.6 13.7C10.9 13.9 11.2 14.1 11.4 14.3C11.6 14.5 11.8 14.7 11.9 15C12 15.3 12.1 15.5 12.1 15.8C12.1 16.2 12 16.5 11.9 16.8C11.8 17.1 11.5 17.4 11.3 17.7C11.1 18 10.7 18.2 10.3 18.3C9.9 18.4 9.5 18.5 9 18.5C8.5 18.5 8.1 18.4 7.7 18.2C7.3 18 7 17.8 6.8 17.6C6.6 17.4 6.4 17.1 6.3 16.8C6.2 16.5 6.10001 16.3 6.10001 16.1C6.10001 15.9 6.2 15.7 6.3 15.6C6.4 15.5 6.6 15.4 6.8 15.4C6.9 15.4 7.00001 15.4 7.10001 15.5C7.20001 15.6 7.3 15.6 7.3 15.7C7.5 16.2 7.7 16.6 8 16.9C8.3 17.2 8.6 17.3 9 17.3C9.2 17.3 9.5 17.2 9.7 17.1C9.9 17 10.1 16.8 10.3 16.6C10.5 16.4 10.5 16.1 10.5 15.8C10.5 15.3 10.4 15 10.1 14.7C9.80001 14.4 9.50001 14.3 9.10001 14.3C9.00001 14.3 8.9 14.3 8.7 14.3C8.5 14.3 8.39999 14.3 8.39999 14.3C8.19999 14.3 7.99999 14.2 7.89999 14.1C7.79999 14 7.7 13.8 7.7 13.7C7.7 13.5 7.79999 13.4 7.89999 13.2C7.99999 13 8.2 13 8.5 13H8.8V13.1ZM15.3 17.5V12.2C14.3 13 13.6 13.3 13.3 13.3C13.1 13.3 13 13.2 12.9 13.1C12.8 13 12.7 12.8 12.7 12.6C12.7 12.4 12.8 12.3 12.9 12.2C13 12.1 13.2 12 13.6 11.8C14.1 11.6 14.5 11.3 14.7 11.1C14.9 10.9 15.2 10.6 15.5 10.3C15.8 10 15.9 9.80003 15.9 9.70003C15.9 9.60003 16.1 9.60004 16.3 9.60004C16.5 9.60004 16.7 9.70003 16.8 9.80003C16.9 9.90003 17 10.2 17 10.5V17.2C17 18 16.7 18.4 16.2 18.4C16 18.4 15.8 18.3 15.6 18.2C15.4 18.1 15.3 17.8 15.3 17.5Z" fill="currentColor"></path>
												</svg>
											</span>
											<!--end::Svg Icon-->
											<!-- <div class="fs-4 fw-bold" data-kt-countup="true" data-kt-countup-value="75">0</div> -->
											{{-- <div class="fs-4 fw-bold" >{{ Carbon\Carbon::parse($this->policies->binder_date)->format('d/m/Y') }}</div> --}}
                                            <div class="fs-4 fw-bold">{{ $this->customer_profile->binder_date }}</div>
                                        </div>
										<!--end::Number-->
										<!--begin::Label-->
										<div class="fw-semibold fs-6 text-gray-400">Binder Date</div>
										<!--end::Label-->
									</div>
									<!--end::Stat-->
									@endif

									@if(($this->policies->policyActivatedDate)!=null)
									<!--begin::Stat-->
									<div class="border border-gray-300 border-dashed rounded min-w-125px py-3 px-4 me-6 mb-3">
										<!--begin::Number-->
										<div class="d-flex align-items-center">
											<!--begin::Svg Icon -->
											<!-- <span class="svg-icon svg-icon-6 svg-icon-danger me-3">
												<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
													<path d="M22 12C22 17.5 17.5 22 12 22C6.5 22 2 17.5 2 12C2 6.5 6.5 2 12 2C17.5 2 22 6.5 22 12ZM12 6C8.7 6 6 8.7 6 12C6 15.3 8.7 18 12 18C15.3 18 18 15.3 18 12C18 8.7 15.3 6 12 6Z" fill="currentColor"></path>
												</svg>
											</span> -->
											<span class="svg-icon svg-icon-2 svg-icon-danger me-3">
												<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
													<path opacity="0.3" d="M21 22H3C2.4 22 2 21.6 2 21V5C2 4.4 2.4 4 3 4H21C21.6 4 22 4.4 22 5V21C22 21.6 21.6 22 21 22Z" fill="currentColor"></path>
													<path d="M6 6C5.4 6 5 5.6 5 5V3C5 2.4 5.4 2 6 2C6.6 2 7 2.4 7 3V5C7 5.6 6.6 6 6 6ZM11 5V3C11 2.4 10.6 2 10 2C9.4 2 9 2.4 9 3V5C9 5.6 9.4 6 10 6C10.6 6 11 5.6 11 5ZM15 5V3C15 2.4 14.6 2 14 2C13.4 2 13 2.4 13 3V5C13 5.6 13.4 6 14 6C14.6 6 15 5.6 15 5ZM19 5V3C19 2.4 18.6 2 18 2C17.4 2 17 2.4 17 3V5C17 5.6 17.4 6 18 6C18.6 6 19 5.6 19 5Z" fill="currentColor"></path>
													<path d="M8.8 13.1C9.2 13.1 9.5 13 9.7 12.8C9.9 12.6 10.1 12.3 10.1 11.9C10.1 11.6 10 11.3 9.8 11.1C9.6 10.9 9.3 10.8 9 10.8C8.8 10.8 8.59999 10.8 8.39999 10.9C8.19999 11 8.1 11.1 8 11.2C7.9 11.3 7.8 11.4 7.7 11.6C7.6 11.8 7.5 11.9 7.5 12.1C7.5 12.2 7.4 12.2 7.3 12.3C7.2 12.4 7.09999 12.4 6.89999 12.4C6.69999 12.4 6.6 12.3 6.5 12.2C6.4 12.1 6.3 11.9 6.3 11.7C6.3 11.5 6.4 11.3 6.5 11.1C6.6 10.9 6.8 10.7 7 10.5C7.2 10.3 7.49999 10.1 7.89999 10C8.29999 9.90003 8.60001 9.80003 9.10001 9.80003C9.50001 9.80003 9.80001 9.90003 10.1 10C10.4 10.1 10.7 10.3 10.9 10.4C11.1 10.5 11.3 10.8 11.4 11.1C11.5 11.4 11.6 11.6 11.6 11.9C11.6 12.3 11.5 12.6 11.3 12.9C11.1 13.2 10.9 13.5 10.6 13.7C10.9 13.9 11.2 14.1 11.4 14.3C11.6 14.5 11.8 14.7 11.9 15C12 15.3 12.1 15.5 12.1 15.8C12.1 16.2 12 16.5 11.9 16.8C11.8 17.1 11.5 17.4 11.3 17.7C11.1 18 10.7 18.2 10.3 18.3C9.9 18.4 9.5 18.5 9 18.5C8.5 18.5 8.1 18.4 7.7 18.2C7.3 18 7 17.8 6.8 17.6C6.6 17.4 6.4 17.1 6.3 16.8C6.2 16.5 6.10001 16.3 6.10001 16.1C6.10001 15.9 6.2 15.7 6.3 15.6C6.4 15.5 6.6 15.4 6.8 15.4C6.9 15.4 7.00001 15.4 7.10001 15.5C7.20001 15.6 7.3 15.6 7.3 15.7C7.5 16.2 7.7 16.6 8 16.9C8.3 17.2 8.6 17.3 9 17.3C9.2 17.3 9.5 17.2 9.7 17.1C9.9 17 10.1 16.8 10.3 16.6C10.5 16.4 10.5 16.1 10.5 15.8C10.5 15.3 10.4 15 10.1 14.7C9.80001 14.4 9.50001 14.3 9.10001 14.3C9.00001 14.3 8.9 14.3 8.7 14.3C8.5 14.3 8.39999 14.3 8.39999 14.3C8.19999 14.3 7.99999 14.2 7.89999 14.1C7.79999 14 7.7 13.8 7.7 13.7C7.7 13.5 7.79999 13.4 7.89999 13.2C7.99999 13 8.2 13 8.5 13H8.8V13.1ZM15.3 17.5V12.2C14.3 13 13.6 13.3 13.3 13.3C13.1 13.3 13 13.2 12.9 13.1C12.8 13 12.7 12.8 12.7 12.6C12.7 12.4 12.8 12.3 12.9 12.2C13 12.1 13.2 12 13.6 11.8C14.1 11.6 14.5 11.3 14.7 11.1C14.9 10.9 15.2 10.6 15.5 10.3C15.8 10 15.9 9.80003 15.9 9.70003C15.9 9.60003 16.1 9.60004 16.3 9.60004C16.5 9.60004 16.7 9.70003 16.8 9.80003C16.9 9.90003 17 10.2 17 10.5V17.2C17 18 16.7 18.4 16.2 18.4C16 18.4 15.8 18.3 15.6 18.2C15.4 18.1 15.3 17.8 15.3 17.5Z" fill="currentColor"></path>
												</svg>
											</span>
											<!--end::Svg Icon-->
											<!-- <div class="fs-4 fw-bold" data-kt-countup="true" data-kt-countup-value="75">0</div> -->
											<div class="fs-4 fw-bold" >{{ Carbon\Carbon::parse($this->policies->policyActivatedDate)->format('d/m/Y') }}</div>
										</div>
										<!--end::Number-->
										<!--begin::Label-->
										<div class="fw-semibold fs-6 text-gray-400">Activated Date</div>
										<!--end::Label-->
									</div>
									<!--end::Stat-->
									@endif

                                    @if(($this->policies->billingStartDate)!=null)
									<!--begin::Stat-->
									<div class="border border-gray-300 border-dashed rounded min-w-125px py-3 px-4 me-6 mb-3">
										<!--begin::Number-->
										<div class="d-flex align-items-center">
											<!--begin::Svg Icon -->
											<span class="svg-icon svg-icon-2 svg-icon-success me-3">
												<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
													<path opacity="0.3" d="M21 22H3C2.4 22 2 21.6 2 21V5C2 4.4 2.4 4 3 4H21C21.6 4 22 4.4 22 5V21C22 21.6 21.6 22 21 22Z" fill="currentColor"></path>
													<path d="M6 6C5.4 6 5 5.6 5 5V3C5 2.4 5.4 2 6 2C6.6 2 7 2.4 7 3V5C7 5.6 6.6 6 6 6ZM11 5V3C11 2.4 10.6 2 10 2C9.4 2 9 2.4 9 3V5C9 5.6 9.4 6 10 6C10.6 6 11 5.6 11 5ZM15 5V3C15 2.4 14.6 2 14 2C13.4 2 13 2.4 13 3V5C13 5.6 13.4 6 14 6C14.6 6 15 5.6 15 5ZM19 5V3C19 2.4 18.6 2 18 2C17.4 2 17 2.4 17 3V5C17 5.6 17.4 6 18 6C18.6 6 19 5.6 19 5Z" fill="currentColor"></path>
													<path d="M8.8 13.1C9.2 13.1 9.5 13 9.7 12.8C9.9 12.6 10.1 12.3 10.1 11.9C10.1 11.6 10 11.3 9.8 11.1C9.6 10.9 9.3 10.8 9 10.8C8.8 10.8 8.59999 10.8 8.39999 10.9C8.19999 11 8.1 11.1 8 11.2C7.9 11.3 7.8 11.4 7.7 11.6C7.6 11.8 7.5 11.9 7.5 12.1C7.5 12.2 7.4 12.2 7.3 12.3C7.2 12.4 7.09999 12.4 6.89999 12.4C6.69999 12.4 6.6 12.3 6.5 12.2C6.4 12.1 6.3 11.9 6.3 11.7C6.3 11.5 6.4 11.3 6.5 11.1C6.6 10.9 6.8 10.7 7 10.5C7.2 10.3 7.49999 10.1 7.89999 10C8.29999 9.90003 8.60001 9.80003 9.10001 9.80003C9.50001 9.80003 9.80001 9.90003 10.1 10C10.4 10.1 10.7 10.3 10.9 10.4C11.1 10.5 11.3 10.8 11.4 11.1C11.5 11.4 11.6 11.6 11.6 11.9C11.6 12.3 11.5 12.6 11.3 12.9C11.1 13.2 10.9 13.5 10.6 13.7C10.9 13.9 11.2 14.1 11.4 14.3C11.6 14.5 11.8 14.7 11.9 15C12 15.3 12.1 15.5 12.1 15.8C12.1 16.2 12 16.5 11.9 16.8C11.8 17.1 11.5 17.4 11.3 17.7C11.1 18 10.7 18.2 10.3 18.3C9.9 18.4 9.5 18.5 9 18.5C8.5 18.5 8.1 18.4 7.7 18.2C7.3 18 7 17.8 6.8 17.6C6.6 17.4 6.4 17.1 6.3 16.8C6.2 16.5 6.10001 16.3 6.10001 16.1C6.10001 15.9 6.2 15.7 6.3 15.6C6.4 15.5 6.6 15.4 6.8 15.4C6.9 15.4 7.00001 15.4 7.10001 15.5C7.20001 15.6 7.3 15.6 7.3 15.7C7.5 16.2 7.7 16.6 8 16.9C8.3 17.2 8.6 17.3 9 17.3C9.2 17.3 9.5 17.2 9.7 17.1C9.9 17 10.1 16.8 10.3 16.6C10.5 16.4 10.5 16.1 10.5 15.8C10.5 15.3 10.4 15 10.1 14.7C9.80001 14.4 9.50001 14.3 9.10001 14.3C9.00001 14.3 8.9 14.3 8.7 14.3C8.5 14.3 8.39999 14.3 8.39999 14.3C8.19999 14.3 7.99999 14.2 7.89999 14.1C7.79999 14 7.7 13.8 7.7 13.7C7.7 13.5 7.79999 13.4 7.89999 13.2C7.99999 13 8.2 13 8.5 13H8.8V13.1ZM15.3 17.5V12.2C14.3 13 13.6 13.3 13.3 13.3C13.1 13.3 13 13.2 12.9 13.1C12.8 13 12.7 12.8 12.7 12.6C12.7 12.4 12.8 12.3 12.9 12.2C13 12.1 13.2 12 13.6 11.8C14.1 11.6 14.5 11.3 14.7 11.1C14.9 10.9 15.2 10.6 15.5 10.3C15.8 10 15.9 9.80003 15.9 9.70003C15.9 9.60003 16.1 9.60004 16.3 9.60004C16.5 9.60004 16.7 9.70003 16.8 9.80003C16.9 9.90003 17 10.2 17 10.5V17.2C17 18 16.7 18.4 16.2 18.4C16 18.4 15.8 18.3 15.6 18.2C15.4 18.1 15.3 17.8 15.3 17.5Z" fill="currentColor"></path>
												</svg>
											</span>
											<!--end::Svg Icon-->
											<!-- <div class="fs-4 fw-bold" data-kt-countup="true" data-kt-countup-value="15000" data-kt-countup-prefix="$">0</div> -->
											<div class="fs-4 fw-bold" >{{ Carbon\Carbon::parse($this->policies->billingStartDate)->format('d/m/Y') }}</div>
										</div>
										<!--end::Number-->
										<!-- begin::Label -->
										<div class="fw-semibold fs-6 text-gray-400">Billing Start Date</div>
										<!--end::Label-->
									</div>
									<!--end::Stat-->
									@endif
									@if(isset($this->newActionDates))
										<!--begin::Stat-->
										<div class="border border-gray-300 border-dashed rounded min-w-125px py-3 px-4 me-6 mb-3">
										<!--begin::Number-->
										<div class="d-flex align-items-center">
											<!--begin::Svg Icon -->
											<span class="svg-icon svg-icon-2 svg-icon-success me-3">
												<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
													<path opacity="0.3" d="M21 22H3C2.4 22 2 21.6 2 21V5C2 4.4 2.4 4 3 4H21C21.6 4 22 4.4 22 5V21C22 21.6 21.6 22 21 22Z" fill="currentColor"></path>
													<path d="M6 6C5.4 6 5 5.6 5 5V3C5 2.4 5.4 2 6 2C6.6 2 7 2.4 7 3V5C7 5.6 6.6 6 6 6ZM11 5V3C11 2.4 10.6 2 10 2C9.4 2 9 2.4 9 3V5C9 5.6 9.4 6 10 6C10.6 6 11 5.6 11 5ZM15 5V3C15 2.4 14.6 2 14 2C13.4 2 13 2.4 13 3V5C13 5.6 13.4 6 14 6C14.6 6 15 5.6 15 5ZM19 5V3C19 2.4 18.6 2 18 2C17.4 2 17 2.4 17 3V5C17 5.6 17.4 6 18 6C18.6 6 19 5.6 19 5Z" fill="currentColor"></path>
													<path d="M8.8 13.1C9.2 13.1 9.5 13 9.7 12.8C9.9 12.6 10.1 12.3 10.1 11.9C10.1 11.6 10 11.3 9.8 11.1C9.6 10.9 9.3 10.8 9 10.8C8.8 10.8 8.59999 10.8 8.39999 10.9C8.19999 11 8.1 11.1 8 11.2C7.9 11.3 7.8 11.4 7.7 11.6C7.6 11.8 7.5 11.9 7.5 12.1C7.5 12.2 7.4 12.2 7.3 12.3C7.2 12.4 7.09999 12.4 6.89999 12.4C6.69999 12.4 6.6 12.3 6.5 12.2C6.4 12.1 6.3 11.9 6.3 11.7C6.3 11.5 6.4 11.3 6.5 11.1C6.6 10.9 6.8 10.7 7 10.5C7.2 10.3 7.49999 10.1 7.89999 10C8.29999 9.90003 8.60001 9.80003 9.10001 9.80003C9.50001 9.80003 9.80001 9.90003 10.1 10C10.4 10.1 10.7 10.3 10.9 10.4C11.1 10.5 11.3 10.8 11.4 11.1C11.5 11.4 11.6 11.6 11.6 11.9C11.6 12.3 11.5 12.6 11.3 12.9C11.1 13.2 10.9 13.5 10.6 13.7C10.9 13.9 11.2 14.1 11.4 14.3C11.6 14.5 11.8 14.7 11.9 15C12 15.3 12.1 15.5 12.1 15.8C12.1 16.2 12 16.5 11.9 16.8C11.8 17.1 11.5 17.4 11.3 17.7C11.1 18 10.7 18.2 10.3 18.3C9.9 18.4 9.5 18.5 9 18.5C8.5 18.5 8.1 18.4 7.7 18.2C7.3 18 7 17.8 6.8 17.6C6.6 17.4 6.4 17.1 6.3 16.8C6.2 16.5 6.10001 16.3 6.10001 16.1C6.10001 15.9 6.2 15.7 6.3 15.6C6.4 15.5 6.6 15.4 6.8 15.4C6.9 15.4 7.00001 15.4 7.10001 15.5C7.20001 15.6 7.3 15.6 7.3 15.7C7.5 16.2 7.7 16.6 8 16.9C8.3 17.2 8.6 17.3 9 17.3C9.2 17.3 9.5 17.2 9.7 17.1C9.9 17 10.1 16.8 10.3 16.6C10.5 16.4 10.5 16.1 10.5 15.8C10.5 15.3 10.4 15 10.1 14.7C9.80001 14.4 9.50001 14.3 9.10001 14.3C9.00001 14.3 8.9 14.3 8.7 14.3C8.5 14.3 8.39999 14.3 8.39999 14.3C8.19999 14.3 7.99999 14.2 7.89999 14.1C7.79999 14 7.7 13.8 7.7 13.7C7.7 13.5 7.79999 13.4 7.89999 13.2C7.99999 13 8.2 13 8.5 13H8.8V13.1ZM15.3 17.5V12.2C14.3 13 13.6 13.3 13.3 13.3C13.1 13.3 13 13.2 12.9 13.1C12.8 13 12.7 12.8 12.7 12.6C12.7 12.4 12.8 12.3 12.9 12.2C13 12.1 13.2 12 13.6 11.8C14.1 11.6 14.5 11.3 14.7 11.1C14.9 10.9 15.2 10.6 15.5 10.3C15.8 10 15.9 9.80003 15.9 9.70003C15.9 9.60003 16.1 9.60004 16.3 9.60004C16.5 9.60004 16.7 9.70003 16.8 9.80003C16.9 9.90003 17 10.2 17 10.5V17.2C17 18 16.7 18.4 16.2 18.4C16 18.4 15.8 18.3 15.6 18.2C15.4 18.1 15.3 17.8 15.3 17.5Z" fill="currentColor"></path>
												</svg>
											</span>
											<!--end::Svg Icon-->
											<!-- <div class="fs-4 fw-bold" data-kt-countup="true" data-kt-countup-value="15000" data-kt-countup-prefix="$">0</div> -->
											<div class="fs-4 fw-bold" >{{ \Carbon\Carbon::parse($this->newActionDates->effective_from)->format('d/m/Y') }} - {{ \Carbon\Carbon::parse($this->newActionDates->effective_to)->format('d/m/Y') }}
											</div>
										</div>
										<!--end::Number-->
										<!-- begin::Label -->
										<div class="fw-semibold fs-6 text-gray-400">Term</div>
										<!--end::Label-->
									</div>
                                        <!-- <div class="col-sm-2" style="width: 20%;">
                                            <div class="form-floating m-3">
											<x-form-label for="term_id" value="Select Term"/>

                                            </div>
                                        </div> -->
                                    @elseif($this->policyTerms->isNotEmpty())
                                        <div class="col-sm-2" style="width: 20%;">
                                            <div class="form-floating m-3">
                                                <select class="form-select form-select-solid" id="term_id" aria-label="Select Term" wire:model="selectedTermId">
                                                    <option value="" disabled> -select- </option>
                                                    @foreach($this->policyTerms as $index => $term)
                                                        <option value="{{ $term->id }}">{{ \Carbon\Carbon::parse($term->term_start_date)->format('d/m/Y') }} - {{ \Carbon\Carbon::parse($term->term_end_date)->format('d/m/Y') }}</option>
                                                    @endforeach
                                                </select>
                                                <x-form-label for="term_id" value="Select Term"/>
                                                <x-form-input-error name="term_id"/>
                                            </div>
                                        </div>
                                    @endif

                                    @if($this->PolicyActions->count())
                                        <div class="col-sm-2"  style="width: 35%;">
                                            <div class="form-floating m-3">
                                                <select class="form-select form-select-solid" id="actionId" aria-label="Select Term" wire:model="actionId">
                                                    <option value="" disabled> -select- </option>
                                                    @foreach($this->PolicyActions as $index => $action)
                                                        <option value="{{ $action->id }}" >{{ $action->transaction_type }} - {{ $action->status }} ({{ \Carbon\Carbon::parse($action->effective_from)->format('d/m/Y') }} - {{ \Carbon\Carbon::parse($action->effective_to)->format('d/m/Y') }})</option>
                                                    @endforeach
                                                </select>
                                                <x-form-label for="actionId" value="Select Policy Action At"/>
                                                <x-form-input-error name="actionId"/>
                                            </div>
                                        </div>
                                    @endif
                                   @if(!$this->editable)
								   @if($this->restricted_editable==0)
                                    {{-- New Transaction button only when the policy's latest action is ISSUED
                                         (or LAPSED). While the last action is still in the approval pipeline
                                         (QUOTE / IN_APPROVAL / APPROVED / REJECTED) no new transaction may be created. --}}
                                    @if($this->canAddTransaction)
                                        <div class="col-sm-2">
                                            @livewire('policy.add-transaction', ['policy'=>$this->policies,'termId'=>$this->selectedTermId,'actionId'=>$actionId,'previousActionId' => $this->previousActionId], key('add-transaction-' . $selectedTermId."-".$actionId))
                                        </div>
                                    @endif
                                    @endif
									@endif
									@php 
									$rFlag =0;
           							 $checkDate  = config('constants.policy.restrictionDate');
									 $policyDate = Carbon::parse($this->newActionDates->effective_from)->format('Y-m-d');
									 	if (strtotime($policyDate) < strtotime($checkDate)) {
											$rFlag = 1;
										}
									@endphp
									@if(!in_array($this->newActionDates->status, ['LAPSED', 'QUOTE']) &&  $this->newActionDates->transaction_type!='RENEW')
                                    @can('policy_unissue')
                                        <div class="col-sm-2">
										<a wire:click="submitToUnissued" class="btn btn-danger" style="height: 35px;
    									margin: 10px 20px 0 0;font-size:12px;width:134px;">UNISSUE</a>
                                        </div>
									@endcan  	
									@endif
									@if($this->newActionDates->deleted_at == null  && $this->newActionDates->status == 'QUOTE' && $this->newActionDates->transaction_type=='ANNIVERSARY-RENEW')
									@if (Auth::user()->hasPermissionTo('policy_refersh_endorse'))
                                    <div class="col-sm-2">
                                      <a wire:click="submitToLapse"
											class="btn"
											style="background-color:#ff9800; border-color:#ff9800; color:#fff;
													height:35px; margin:10px 20px 0 0; font-size:12px; width:134px;">
												LAPSE
											</a>
                                        </div>
                                    
                                    @endif
                                    @endif
									@if($this->newActionDates->deleted_at == null  && $this->newActionDates->status == 'QUOTE' && $this->newActionDates->transaction_type=='ENDORSE')
										@can('policy_delete_endorse')
										<div class="col-sm-2">
											<a wire:click="deleteActionRelatedData" 
											class="btn btn-danger" 
											style="height:55px;margin:10px 20px 0 0;font-size:12px;width:134px;">
												DELETE ENDORSEMENT 
											</a>
										</div>
										@endcan
									@endif
									@if($this->newActionDates->deleted_at == null  && $this->newActionDates->status == 'QUOTE' && $this->newActionDates->transaction_type=='CANCEL')
										@can('policy_delete_cancel')
										<div class="col-sm-2">
											<a wire:click="deleteActionRelatedData"
											class="btn btn-danger"
											style="height:55px;margin:10px 20px 0 0;font-size:12px;width:134px;">
												Delete CANCEL-QUOTE
											</a>
										</div>
										@endcan
									@endif
									{{-- Super Admin only: discard a wrongly batch-created RENEW
									     (e.g. annual policy renewed monthly by the cron) together
									     with its invoice. --}}
									@if($this->newActionDates->deleted_at == null && $this->newActionDates->transaction_type=='RENEW')
										@if (Auth::user()->hasRole('Super Admin'))
										<div class="col-sm-2">
											<a wire:click="deleteRenewTransaction"
											onclick="if(!confirm('Delete this RENEW transaction and its invoice? This cannot be undone from the UI.')) { event.stopImmediatePropagation(); }"
											class="btn btn-danger"
											style="height:55px;margin:10px 20px 0 0;font-size:12px;width:134px;">
												DELETE RENEW
											</a>
										</div>
										@endif
									@endif
									{{-- Super Admin only: re-rate a RENEW that is already ISSUED and
									     push the rated premium into this action's invoice amount only.
									     Nothing else on the policy changes. --}}
									@if($this->newActionDates->deleted_at == null && $this->newActionDates->transaction_type=='RENEW' && $this->newActionDates->status=='ISSUED')
										@if (Auth::user()->hasRole('Super Admin'))
										<div class="col-sm-2">
											<a wire:click="rateRenewInvoice"
											onclick="if(!confirm('Re-rate this RENEW and update its invoice amount to the rated premium? Nothing else changes.')) { event.stopImmediatePropagation(); }"
											class="btn"
											style="background-color:#F4A623;border-color:#F4A623;color:#0D1B2A;height:55px;margin:10px 20px 0 0;font-size:12px;width:134px;">
												RATE
											</a>
										</div>
										@endif
									@endif
									@if($this->restricted_editable==0)
									 @if(!in_array($this->newActionDates->status, ['LAPSED']) && $this->newActionDates->transaction_type!='RENEW')
                                        <div class="col-sm-2">
                                            @livewire('policy.edit-transaction', ['policy'=>$this->policies,'termId'=>$this->selectedTermId,'actionId'=>$actionId,'previousActionId' => $this->previousActionId], key('edit-transaction-' . $selectedTermId."-".$actionId))
                                        </div>
									@endif
									@endif
									{{-- Admin / Super Admin read-only diagnostic: what an ENDORSE/CANCEL
									     added, changed or deleted + the pro-rata math. Writes nothing. --}}
									@if(in_array($this->newActionDates->transaction_type, ['ENDORSE','CANCEL']) && Auth::user()->hasAnyRole(['Super Admin','admin']))
                                        <div class="col-sm-2">
                                            @livewire('policy.endorse-change-summary', ['policyId'=>$this->policies->id,'termId'=>$this->selectedTermId,'actionId'=>$actionId], key('endorse-change-summary-' . $selectedTermId."-".$actionId))
                                        </div>
									@endif
								<!-- </div> -->
								<!--end::Stats-->
								<!--begin::Users-->
								<!-- <div class="symbol-group symbol-hover mb-3"> -->
									<!--begin::User-->
									<!-- <div class="symbol symbol-35px symbol-circle" data-bs-toggle="tooltip" title="Alan Warden">
										<span class="symbol-label bg-warning text-inverse-warning fw-bold">A</span>
									</div> -->
									<!--end::User-->
									<!--begin::User-->
									<!-- <div class="symbol symbol-35px symbol-circle" data-bs-toggle="tooltip" title="Michael Eberon">
										<img alt="Pic" src="assets/media/avatars/300-11.jpg" />
									</div> -->
									<!--end::User-->
									<!--begin::User-->
									<!-- <div class="symbol symbol-35px symbol-circle" data-bs-toggle="tooltip" title="Michelle Swanston">
										<img alt="Pic" src="assets/media/avatars/300-7.jpg" />
									</div> -->
									<!--end::User-->
									<!--begin::User-->
									<!-- <div class="symbol symbol-35px symbol-circle" data-bs-toggle="tooltip" title="Francis Mitcham">
										<img alt="Pic" src="assets/media/avatars/300-20.jpg" />
									</div> -->
									<!--end::User-->
									<!--begin::User-->
									<!-- <div class="symbol symbol-35px symbol-circle" data-bs-toggle="tooltip" title="Susan Redwood">
										<span class="symbol-label bg-primary text-inverse-primary fw-bold">S</span>
									</div> -->
									<!--end::User-->
									<!--begin::User-->
									<!-- <div class="symbol symbol-35px symbol-circle" data-bs-toggle="tooltip" title="Melody Macy">
										<img alt="Pic" src="assets/media/avatars/300-2.jpg" />
									</div> -->
									<!--end::User-->
									<!--begin::User-->
									<!-- <div class="symbol symbol-35px symbol-circle" data-bs-toggle="tooltip" title="Perry Matthew">
										<span class="symbol-label bg-info text-inverse-info fw-bold">P</span>
									</div> -->
									<!--end::User-->
									<!--begin::User-->
									<!-- <div class="symbol symbol-35px symbol-circle" data-bs-toggle="tooltip" title="Barry Walter">
										<img alt="Pic" src="assets/media/avatars/300-12.jpg" />
									</div> -->
									<!--end::User-->
									<!--begin::All users-->
									<!-- <a href="#" class="symbol symbol-35px symbol-circle" data-bs-toggle="modal" data-bs-target="#kt_modal_view_users">
										<span class="symbol-label bg-dark text-inverse-dark fs-8 fw-bold" data-bs-toggle="tooltip" data-bs-trigger="hover" title="View more users">+42</span>
									</a> -->
									<!--end::All users-->
								<!-- </div> -->
								<!--end::Users-->
							</div>
							<!--end::Info-->
						</div>
						<!--end::Wrapper-->
					</div>
					<!--end::Details-->
					<div class="separator"></div>
				</div>
			</div>
		<!-- end::Navbar -->

        {{-- start messages --}}
        @if (\Session::has('success'))
        <div class="alert alert-success" style="padding:5px;">
            <ul>
                <li>{!! \Session::get('success') !!}</li>
            </ul>
        </div>
        @endif
        @if (\Session::has('error'))
        <div class="alert alert-danger" style="padding:5px;">
            <ul>
                <li>{!! \Session::get('error') !!}</li>
            </ul>
        </div>
        @endif
        @if (session()->has('popup'))
        {{-- echo '<script type="text/javascript">alert("Sorry! You do not have permission")</script>'; --}}
        <script>
         var popup = true;
         //$('#ajaxModel').modal('show');
        </script>
        @endif
        {{-- end messages --}}

		<div class="rounded bg-gray-200 d-flex flex-stack flex-wrap mb-9 p-2" data-kt-sticky="true" data-kt-sticky-name="sticky-profile-navs" data-kt-sticky-offset="{default: false, lg: '200px'}" data-kt-sticky-width="" data-kt-sticky-left="auto" data-kt-sticky-top="70px" data-kt-sticky-animation="false">
			<!--begin::Nav-->
			<ul class="nav flex-wrap border-transparent">
				<li class="nav-item my-1">
					<a @click="tab=1" class="btn btn-sm btn-color-gray-600 bg-state-body btn-active-color-gray-800 fw-bolder fw-bold fs-6 fs-lg-base nav-link px-3 px-lg-4 mx-1" :class="(tab==1)?'active':''" >Policy Details</a>
				</li>
				<li class="nav-item my-1">
					<a @click="tab=2" class="btn btn-sm btn-color-gray-600 bg-state-body btn-active-color-gray-800 fw-bolder fw-bold fs-6 fs-lg-base nav-link px-3 px-lg-4 mx-1" :class="(tab==2)?'active':''">Applicant Informations</a>
				</li>
				<li class="nav-item my-1" x-show="hasRiskAddress">
					<a @click="tab=3" class="btn btn-sm btn-color-gray-600 bg-state-body btn-active-color-gray-800 fw-bolder fw-bold fs-6 fs-lg-base nav-link px-3 px-lg-4 mx-1" :class="(tab==3)?'active':''">Risk Address</a>
				</li>
                <li class="nav-item my-1" x-show="hasCoverage">
                    <a @click="tab=4" class="btn btn-sm btn-color-gray-600 bg-state-body btn-active-color-gray-800 fw-bolder fw-bold fs-6 fs-lg-base nav-link px-3 px-lg-4 mx-1" :class="(tab==4)?'active':''">Coverages</a>
                </li>
				<li class="nav-item my-1" >
                    <a @click="tab=19" class="btn btn-sm btn-color-gray-600 bg-state-body btn-active-color-gray-800 fw-bolder fw-bold fs-6 fs-lg-base nav-link px-3 px-lg-4 mx-1" :class="(tab==19)?'active':''">New Coverages</a>
                </li>
                <li class="nav-item my-1">
					<a @click="tab=5" class="btn btn-sm btn-color-gray-600 bg-state-body btn-active-color-gray-800 fw-bolder fw-bold fs-6 fs-lg-base nav-link px-3 px-lg-4 mx-1" :class="(tab==5)?'active':''">Submit</a>
				</li>
				<li class="nav-item my-1">
					<a @click="tab=6" class="btn btn-sm btn-color-gray-600 bg-state-body btn-active-color-gray-800 fw-bolder fw-bold fs-6 fs-lg-base nav-link px-3 px-lg-4 mx-1" :class="(tab==6)?'active':''">Activity Log </a>
				</li>
				<li class="nav-item my-1">
					<a @click="tab=7" class="btn btn-sm btn-color-gray-600 bg-state-body btn-active-color-gray-800 fw-bolder fw-bold fs-6 fs-lg-base nav-link px-3 px-lg-4 mx-1" :class="(tab==7)?'active':''">Reinsurance </a>
				</li>
				<li class="nav-item my-1">
					<a @click="tab=8" class="btn btn-sm btn-color-gray-600 bg-state-body btn-active-color-gray-800 fw-bolder fw-bold fs-6 fs-lg-base nav-link px-3 px-lg-4 mx-1" :class="(tab==8)?'active':''">Ledger </a>
				</li>
                <li class="nav-item my-1">
					<a @click="tab=9" class="btn btn-sm btn-color-gray-600 bg-state-body btn-active-color-gray-800 fw-bolder fw-bold fs-6 fs-lg-base nav-link px-3 px-lg-4 mx-1" :class="(tab==9)?'active':''">Policy Documents </a>
				</li>
                @if($this->policies->product_id == 3)
                <li class="nav-item my-1">
					<a @click="tab=10" class="btn btn-sm btn-color-gray-600 bg-state-body btn-active-color-gray-800 fw-bolder fw-bold fs-6 fs-lg-base nav-link px-3 px-lg-4 mx-1" :class="(tab==10)?'active':''">Rerate Premium</a>
				</li>
                @endif
                <li class="nav-item my-1">
					<a @click="tab=11" class="btn btn-sm btn-color-gray-600 bg-state-body btn-active-color-gray-800 fw-bolder fw-bold fs-6 fs-lg-base nav-link px-3 px-lg-4 mx-1" :class="(tab==11)?'active':''">Earned Premium </a>
				</li>
                <li class="nav-item my-1">
					<a @click="tab=12" class="btn btn-sm btn-color-gray-600 bg-state-body btn-active-color-gray-800 fw-bolder fw-bold fs-6 fs-lg-base nav-link px-3 px-lg-4 mx-1" :class="(tab==12)?'active':''">Facutatlve Placement </a>
				</li>
				<li class="nav-item my-1">
					<a @click="tab=13" class="btn btn-sm btn-color-gray-600 bg-state-body btn-active-color-gray-800 fw-bolder fw-bold fs-6 fs-lg-base nav-link px-3 px-lg-4 mx-1" :class="(tab==13)?'active':''">KYC</a>
				</li>
                <li class="nav-item my-1">
					<a @click="tab=14" class="btn btn-sm btn-color-gray-600 bg-state-body btn-active-color-gray-800 fw-bolder fw-bold fs-6 fs-lg-base nav-link px-3 px-lg-4 mx-1" :class="(tab==14)?'active':''">Attachment</a>
				</li>
				<li class="nav-item my-1">
					<a @click="tab=15" class="btn btn-sm btn-color-gray-600 bg-state-body btn-active-color-gray-800 fw-bolder fw-bold fs-6 fs-lg-base nav-link px-3 px-lg-4 mx-1" :class="(tab==15)?'active':''">Add Offline Payments</a>
				</li>
                <li class="nav-item my-1">
					<a @click="tab=16" class="btn btn-sm btn-color-gray-600 bg-state-body btn-active-color-gray-800 fw-bolder fw-bold fs-6 fs-lg-base nav-link px-3 px-lg-4 mx-1" :class="(tab==16)?'active':''">Transaction Logs</a>
				</li>
                <li class="nav-item my-1">
					<a @click="tab=17" class="btn btn-sm btn-color-gray-600 bg-state-body btn-active-color-gray-800 fw-bolder fw-bold fs-6 fs-lg-base nav-link px-3 px-lg-4 mx-1" :class="(tab==17)?'active':''">Realpay Contract Lists</a>
				</li>
                <li class="nav-item my-1">
					<a @click="tab=21" class="btn btn-sm btn-color-gray-600 bg-state-body btn-active-color-gray-800 fw-bolder fw-bold fs-6 fs-lg-base nav-link px-3 px-lg-4 mx-1" :class="(tab==21)?'active':''">Add Realpay Contract</a>
				</li>
                <li class="nav-item my-1">
					<a @click="tab=18" class="btn btn-sm btn-color-gray-600 bg-state-body btn-active-color-gray-800 fw-bolder fw-bold fs-6 fs-lg-base nav-link px-3 px-lg-4 mx-1" :class="(tab==18)?'active':''">Realpay Transactions</a>
				</li>
				<li class="nav-item my-1">
					<a @click="tab=20" class="btn btn-sm btn-color-gray-600 bg-state-body btn-active-color-gray-800 fw-bolder fw-bold fs-6 fs-lg-base nav-link px-3 px-lg-4 mx-1" :class="(tab==20)?'active':''">Endorsement Log</a>
				</li>
			</ul>
		</div>

		<div class="card mb-5 mb-xl-10" x-show="tab===1">
			<div class="card-header cursor-pointer">
				<div class="card-title m-0">
					<h3 class="fw-bold m-0">Policy Details </h3>
				</div>
				<!-- <a class="btn btn-sm btn-primary align-self-center">Edit Policy Details</a> -->
			</div>
			<div class="card-body p-9">
                @if($this->editable)
				
				@if($this->newActionDates->transaction_type == 'NEWBUSINESS' || $this->newActionDates->transaction_type == 'ANNIVERSARY-RENEW')
                <div class="row">
                    <div class="col-sm-12">
                        @livewire('common.excel-import', ['policy' => $policies,'selectedType'=>'edit_policy','termId'=>$selectedTermId,'actionId'=>$actionId,'previousActionId' => $this->previousActionId,'exportLinkName'=>'Coverage Data'], key('import-export-policy-'.$selectedTermId."-".$actionId))
                        <br>
                    </div>
                </div>
                <div class="row">
                    <div class="col-sm-12">
                        @livewire('common.excel-import', ['policy' => $policies,'selectedType'=>'specified_items','termId'=>$selectedTermId,'actionId'=>$actionId,'previousActionId' => $this->previousActionId,'exportLinkName'=>'Specified Items'], key('import-export-specified-items-'.$selectedTermId."-".$actionId))
                        <br>
                    </div>
                </div>
				@endif
                @endif
				<div class="row">
					<div class="col-sm-12">
						<div class="accordion">
							<div class="accordion-item" x-show="hasMember">
								<h2 class="accordion-header" @click="step=1">
									<button class="accordion-button fs-4 fw-semibold " type="button" :class="(step==1)?'show':'collapsed' ">
										Beneficiary Details
									</button>
								</h2>
								<div class="accordion-collapse collapse " :class="(step==1)?'show':''">
									<div class="accordion-body">

                                        @livewire('policy.add-member', ['policy' => $policies,'isPrevious'=>false,'termId'=>$selectedTermId,'actionId'=>$actionId,'previousActionId' => $this->previousActionId,'editable'=>$this->editable], key('add-member-'.$selectedTermId."-".$actionId))
                                    </div>
								</div>
							</div>
							<hr>
							<div class="accordion-item" x-show="hasVehicle">
								<h2 class="accordion-header" @click="step=2">
									<button class="accordion-button fs-4 fw-semibold show" type="button"  :class="(step==2)?'show':'collapsed' ">
										Vehicle Details
									</button>
								</h2>
								
								<div class="accordion-collapse collapse " :class="(step==2)?'show':''">
									<div class="accordion-body">
                                        @livewire('policy.add-vehicle', ['policy' => $policies,'isPrevious'=>false,'termId'=>$this->selectedTermId,'actionId'=>$actionId,'previousActionId' => $this->previousActionId,'editable'=>$this->editable], key('add-vehice-'.$selectedTermId."-".$actionId))
									</div>
									</br>
								</div>
							
							</div>
							<hr>
							<div class="accordion-item" x-show="hasDevice">
								<h2 class="accordion-header" @click="step=3">
									<button class="accordion-button fs-4 fw-semibold " type="button" :class="(step==3)?'show':'collapsed' " >
										Device Details
									</button>
								</h2>
								<div  class="accordion-collapse collapse " :class="(step==3)?'show':''">
									<div class="accordion-body">
                                        @livewire('policy.add-device', ['policy' => $policies,'isPrevious'=>false,'termId'=>$selectedTermId,'actionId'=>$actionId,'previousActionId' => $this->previousActionId,'editable'=>$this->editable], key('add-device-' . $selectedTermId."-".$actionId))
									</div>
									</br>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>

		<div class="card mb-5 mb-xl-10" x-show="tab===2">
			<div class="card-header cursor-pointer">
				<div class="card-title m-0">
					<h3 class="fw-bold m-0">Applicant Informations</h3>
				</div>
				<!-- <a class="btn btn-sm btn-primary align-self-center">Edit Applicant Informations</a> -->
			</div>
			<div class="card-body p-9">
				@if($tab==2)
					@include('v2.livewire.policy.applicant-information',['policy' => $policies,'restricted_editable'=>$this->restricted_editable])
				@endif
			</div>
		</div>

		<div class="card mb-5 mb-xl-10" x-show="tab===3">
			<div class="card-header cursor-pointer">
				<div class="card-title m-0">
					<h3 class="fw-bold m-0">Risk Details</h3>
				</div>
				<!-- <a class="btn btn-sm btn-primary align-self-center">Edit Risk Details</a> -->
			</div>
			<div class="card-body p-9" x-show="hasRiskAddress">
				@if($tab==3)
                    @livewire('policy.add-risk-address', ['policy' => $policies,'isPrevious'=>false,'termId'=>$this->selectedTermId,'inSide' => 'edit','actionId' => $actionId,'previousActionId' => $this->previousActionId,'editable'=>$this->editable], key('add-risk-address-' . $selectedTermId."-".$actionId))
				@endif
            </div>
		</div>
        <div class="card mb-5 mb-xl-10" x-show="tab===4" x-show="hasCoverage">
            <div class="card-header cursor-pointer">
                <div class="card-title m-0">
                    <h3 class="fw-bold m-0">Coverages Details</h3>
                </div>
                <!-- <a class="btn btn-sm btn-primary align-self-center">Edit Coverages Details</a> -->
            </div>
            <div class="card-body p-9">
                @if($tab==4)
					@if($this->editable==1 && $this->restricted_editable==0)
                        @livewire('policy.manage-coverages', ['policy'=>$this->policies,'editmode'=>true,'isPrevious'=>false,'termId'=>$this->selectedTermId,'actionId'=>$actionId,'editable'=>$this->editable,'selectedCompany'=>$customer_profile->company ?? null,'previousActionId' => $this->previousActionId], key('add-coverage-' . $selectedTermId."-".$actionId))
					@else
						@livewire('policy.coverages-details', ['policy'=>$this->policies,'termId'=>$this->selectedTermId,'actionId'=>$actionId,'previousActionId' => $this->previousActionId,'policyAction'=>$this->policyAction], key('coverages-details-' . $selectedTermId."-".$actionId))
					@endif
				@endif
            </div>
        </div>

        <div class="card mb-5 mb-xl-10" x-show="tab===5" x-show="hasCoverage">
            <div class="card-header cursor-pointer">
                <div class="card-title m-0">
                    <h3 class="fw-bold m-0">Submit</h3>
                </div>
			</div>

            <div class="card mb-5 mb-xl-10">
                @if($tab==5)
                 {{--   @include('v2.livewire.policy.submit')--}}
                    @livewire('policy.submit',['policy' => $policies, 'termId'=>$this->selectedTermId,'actionId'=>$actionId,'previousActionId' => $this->previousActionId], key('submit-' . $selectedTermId."-".$actionId))
                @endif
            </div>
        </div>
        <div class="card mb-5 mb-xl-10" x-show="tab===6">
			<div class="card-body p-9">
                @if($tab==6)
                {{-- @livewire('policy.activity-log.view',['policy'=>$this->policies]) --}}
                @livewire('policy.activity-log.table',['policy'=>$this->policies])
                @endif
			</div>
		</div>
        <div class="card mb-5 mb-xl-10" x-show="tab===7">
            <div class="card-body p-9">
            @if($tab==7)
                @livewire('policy.reinsurance.view',['policy' => $policies, 'termId'=>$this->selectedTermId,'actionId'=>$actionId,'previousActionId' => $this->previousActionId], key('reinsurancce-' . $selectedTermId."-".$actionId))
            @endif
            </div>
        </div>

        <div class="card mb-5 mb-xl-10" x-show="tab===8">
            <div class="card-body p-9">
                @if($tab==8)
                    @livewire('policy.ledger.views',['policy' => $this->policies,'policyNumber' => $this->policies->policyNumber,'termId'=>$this->selectedTermId,'actionId'=>$actionId,'previousActionId' => $this->previousActionId], key('ledger-' . $selectedTermId."-".$actionId))
                @endif
            </div>
        </div>

        <div class="card mb-5 mb-xl-10" x-show="tab===9">
            <div class="card-body p-9">
                @if($tab==9)
                @livewire('policy.documents.view',['policy'=>$this->policies,'customer'=>$this->customer,'termId'=>$this->selectedTermId,'actionId'=>$actionId,'previousActionId' => $this->previousActionId], key('document-view-' . $selectedTermId."-".$actionId))
                @endif
            </div>
        </div>

        @if($this->policies->product_id == 3)
        <div class="card mb-5 mb-xl-10" x-show="tab===10">
			<div class="card-body p-9">
                @if($tab==10)
                <x-policy.rerate-premium :policy="$this->policies"/>
                @endif
			</div>
		</div>
        @endif

        <div class="card mb-5 mb-xl-10" x-show="tab===11">
			<div class="card-body p-9">
                @if($tab==11)
                @livewire('policy.earned-premium.view',['policy'=>$this->policies,'actionId'=>$actionId,'previousActionId' => $this->previousActionId])
                @endif
			</div>
		</div>

        <div class="card mb-5 mb-xl-10" x-show="tab===12">
			<div class="card-body p-9">
                @if($tab==12)
                @livewire('policy.facutatlve-placement.view',['policy'=>$this->policies,'actionId'=>$actionId,'previousActionId' => $this->previousActionId])
                @endif
			</div>
		</div>

		<div class="card mb-5 mb-xl-10" x-show="tab===13">
			<div class="card-body p-9">
                @if($tab==13)
                @livewire('policy.kyc.view',['policy'=>$this->policies, 'actionId'=>$actionId], key('kyc-view-' . $selectedTermId."-".$actionId))
                @endif
			</div>
		</div>

        <div class="card mb-5 mb-xl-10" x-show="tab===14">
			<div class="card-body p-9">
                @if($tab==14)
                @livewire('policy.attachment.view',['policy'=>$this->policies])
                @endif
			</div>
		</div>

        <div class="card mb-5 mb-xl-10" x-show="tab===15">
            <div class="card-body p-9">
                @if($tab==15)
                    @livewire('policy.add-offline-payments',['policy' => $this->policies,'policyNumber' => $this->policies->policyNumber,'termId'=>$this->selectedTermId,'actionId'=>$actionId,'previousActionId' => $this->previousActionId], key('add-offline-payments-' . $selectedTermId."-".$actionId))
                @endif
            </div>
        </div>

        <div class="card mb-5 mb-xl-10" x-show="tab===16">
            <div class="card-body p-9">
                @if($tab==16)
					@livewire('policy.transaction-logs.refund-money',['policy' => $this->policies,'policyNumber' => $this->policies->policyNumber,'termId'=>$this->selectedTermId,'actionId'=>$actionId,'previousActionId' => $this->previousActionId, 'restricted' => $this->restricted_editable], key('refund-money-' . $selectedTermId."-".$actionId))
                    <br><br>
                    @livewire('policy.transaction-logs.transaction-logs-table',['policy' => $this->policies,'policyNumber' => $this->policies->policyNumber,'termId'=>$this->selectedTermId,'actionId'=>$actionId,'previousActionId' => $this->previousActionId ,'restricted' => $this->restricted_editable], key('transaction-logs-' . $selectedTermId."-".$actionId))
                @endif
            </div>
        </div>

        <div class="card mb-5 mb-xl-10" x-show="tab===17">
            <div class="card-body p-9">
                @if($tab==17)
                @livewire('policy.realpay.realpay-contract-lists',['policy' => $this->policies,'policyNumber' => $this->policies->policyNumber,'termId'=>$this->selectedTermId,'actionId'=>$actionId,'previousActionId' => $this->previousActionId], key('realpay-contract-lists-' . $selectedTermId."-".$actionId))
                @endif
            </div>
        </div>

        <div class="card mb-5 mb-xl-10" x-show="tab===18">
            <div class="card-body p-9">
                @if($tab==18)
                @livewire('policy.realpay.realpay-transactions',['policy' => $this->policies,'policyNumber' => $this->policies->policyNumber,'termId'=>$this->selectedTermId,'actionId'=>$actionId,'previousActionId' => $this->previousActionId], key('realpay-transactions-' . $selectedTermId."-".$actionId))
                @endif
            </div>
        </div>

        <div class="card mb-5 mb-xl-10" x-show="tab===21">
            <div class="card-body p-9">
                @if($tab==21)
                @livewire('policy.realpay.add-realpay-contract',['policy' => $this->policies,'policyNumber' => $this->policies->policyNumber,'termId'=>$this->selectedTermId,'actionId'=>$actionId,'previousActionId' => $this->previousActionId], key('add-realpay-contract-' . $selectedTermId."-".$actionId))
                @endif
            </div>
        </div>

		<div class="card mb-5 mb-xl-10" x-show="tab===19">
            <div class="card-header cursor-pointer">
                <div class="card-title m-0">
                    <h3 class="fw-bold m-0">New Coverages Details</h3>
                </div>
                <!-- <a class="btn btn-sm btn-primary align-self-center">Edit Coverages Details</a> -->
            </div>
            <div class="card-body p-9">
                @if($tab==19)
					@if($this->editable)
					@if($_SERVER['REMOTE_ADDR']==$ip_address && $this->riskAddressId != null)
						@livewire('policy.manage-new-coverages', ['policy'=>$this->policies,
						'editmode'=>true,
						'isPrevious'=>false,
						'termId'=>$this->selectedTermId,
						'actionId'=>$actionId,
						'editable'=>$this->editable,
						'selectedCompany'=>$customer_profile->company ?? null,
						'previousActionId' => $this->previousActionId,
						'riskAddressId' => $this->riskAddressId,
						], key('add-coverage-' . $selectedTermId."-".$actionId))
					@else
						@livewire('policy.manage-coverages', ['policy'=>$this->policies,'editmode'=>true,'isPrevious'=>false,'termId'=>$this->selectedTermId,'actionId'=>$actionId,'editable'=>$this->editable,'selectedCompany'=>$customer_profile->company ?? null,'previousActionId' => $this->previousActionId], key('add-coverage-' . $selectedTermId."-".$actionId))
					@endif

					@else
						@livewire('policy.coverages-details', ['policy'=>$this->policies,'termId'=>$this->selectedTermId,'actionId'=>$actionId,'previousActionId' => $this->previousActionId,'policyAction'=>$this->policyAction], key('coverages-details-' . $selectedTermId."-".$actionId))
					@endif
				@endif
            </div>
        </div>

		@if($this->policyAction->transaction_type == 'ENDORSE')
		<div class="card mb-5 mb-xl-10" x-show="tab===20">
			<div class="card-body p-9">
                @if($tab==20)
                @livewire('audit-data-table',['policy'=>$this->policies,'actionId'=>$actionId])
                @endif
			</div>
		</div>
		@endif


	</div>
    <div wire:ignore.self class="modal fade" tabindex="-1" data-backdrop="static" id="ajaxModel" role="dialog" aria-labelledby="updateModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title">Sorry! You do not have permission</h3>
                    <!--begin::Close-->
                    <div class="btn btn-icon btn-sm btn-active-light-primary ms-2" data-bs-dismiss="modal" aria-label="Close">
                        <span class="svg-icon svg-icon-2x"></span>
                    </div>
                    <!--end::Close-->
                </div>
                {{-- <div class="modal-body">
                    <div wire:loading.remove>
                        <div class="row">
                            <div class="col-sm-12 m-2">
                                <label class="form-check form-check-custom form-check-solid">
                                    <h5 class="modal-title">Sorry! You do not have permission</h5>
                                </label>
                            </div>
                        </div>
                    </div>
                </div> --}}
                <div class="modal-footer">
                    <button type="button"
                            wire:loading.attr="disabled"
                            wire:offline.attr="disabled"
                            class="btn btn-primary"
                            data-bs-dismiss="modal">
                        Ok
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

@livewire('policy.transaction-logs.reverse-transaction-modal')


@push('scripts')
<script>
//  $(document).ready(function(){
//     $(document).on('click', '#loadNext', function (e) {
//         e.preventDefault(); // Prevent any default behavior
//         var riskAddressId = $(this).attr('data-id'); // Get latest data-id dynamically
//         var nextId = $(this).attr('data-next-id'); // Get latest data-id dynamically
//         console.log("Clicked Risk Address ID:", riskAddressId); // Debugging
//         console.log("Clicked next Risk Address ID:", nextId); // Debugging
//         // Ensure value is set properly in Livewire
//         Livewire.emit('selectItem', riskAddressId,'notdropdown');
//         @this.set('riskAddressId', riskAddressId);
//         @this.set('policyCoverageRiskAddress.riskAddressId', nextId);

//     });

// });

document.addEventListener("DOMContentLoaded", function () {
    function initSelect2() {
        $('#riskAddressId').select2().on('change', function () {
            var value = $(this).val();
            $(this).attr('data-id', value);
            console.log("Clicked dropdown:", value); // Debugging
			Livewire.emit('riskAddressSelected', value);
        });
    }

    // Initialize Select2 on page load
    initSelect2();

    // Reinitialize Select2 after Livewire updates
    Livewire.hook('message.processed', (message, component) => {
        initSelect2();
    });

    // Sync Select2 with Livewire when value changes
    Livewire.on('refreshSelect2', (value) => {
        let currentValue = $('#riskAddressId').val();
		console.log("Current Value:", currentValue); // Debugging
        if (currentValue !== value) {
            $('#riskAddressId').val(value).trigger('change.select2');
			@this.set('riskAddressId', value);
			@this.set('policyCoverageRiskAddress.riskAddressId', value);
        }
    });
});


var insideCoverage = false;
    document.addEventListener("DOMContentLoaded", () => {
		Livewire.hook('component.initialized', (component) => {
            if (component.name=="policy.add-coverage"){
                insideCoverage = true;
            }
            if (component.name=="policy.add-risk-address"){
                if (insideCoverage){
                    insideCoverage = false;
                    initAutocomplete('coverage');
                }else{
                    initAutocomplete('edit');
                }
            }
			if (component.name=="common.image-upload"){
                // Driving License
                $("#imageUpload_driving_license").change(function () {
					readImageUploadComponent(this, "imagePreview_driving_license", "imageUpload_driving_license", "linkImagePreview_driving_license");
				});

				// Omang ID Front
				$("#imageUpload_omang").change(function () {
					readImageUploadComponent(this, "imagePreview_omang", "imageUpload_omang", "linkImagePreview_omang");
				});

				// Omang ID Back
				$("#imageUpload_omangBack").change(function () {
					readImageUploadComponent(this, "imagePreview_omangBack", "imageUpload_omangBack", "linkImagePreview_omangBack");
				});

                // Proof of Residence
				$("#imageUpload_proof_residence").change(function () {
					readImageUploadComponent(this, "imagePreview_proof_residence", "imageUpload_proof_residence", "linkImagePreview_proof_residence");
				});

				// Proof Of Income
				$("#imageUpload_proof_income").change(function () {
					readImageUploadComponent(this, "imagePreview_proof_income", "imageUpload_proof_income", "linkImagePreview_proof_income");
				});

				// Passport
				$("#imageUpload_passport").change(function () {
					readImageUploadComponent(this, "imagePreview_passport", "imageUpload_passport", "linkImagePreview_passport");
				});
            }
        })


    });
</script>

@endpush

@push('scripts')
<script>
    window.addEventListener('show-modal', function (event) {
        const target = event.detail.target;

        if (target) {
            $(target).modal('show');
        }
    });

    window.addEventListener('hide-modal', function () {
        $('#transactionLogDeleteModal').modal('hide');
    });
</script>
@endpush
{{--
@push('scripts')
	<script>
		document.addEventListener('livewire:load', function () {
			$('.kt_datepicker_1').datepicker({
				format: 'yyyy-mm-dd'
			}).on('changeDate', function (e) {
				Livewire.emit('dateSelected', e.format(0, 'yyyy-mm-dd'));
			});
		});
	</script>
@endpush --}}

@push('scripts')
<script>
    document.addEventListener('livewire:load', function () {
        $('.kt_datepicker_123').datepicker({
            rtl: KTUtil.isRTL(),
            format: 'yyyy-mm-dd',
            autoclose: true,
            todayHighlight: true
        }).on('changeDate', function (e) {
            let selectedDate = $(this).datepicker('getFormattedDate');
            Livewire.emit('updateDateOfRefund', selectedDate);
        });

		// // Listen for the browser event to close the modal
        // window.addEventListener('refund-processed', function () {
        //     $('#refundModal').modal('hide');

        //     // Optional: clear the form manually if needed
        //     document.getElementById('refundForm').reset();
        // });
    });
</script>
<script>
    window.addEventListener('show-modal', event => {
        const target = event.detail.target;
        if (target) {
            $(target).modal('show');
        }
    });

    window.addEventListener('hide-modal', event => {
        const target = event.detail?.target ?? '#refundModal'; // fallback
        $(target).modal('hide');
    });
	
</script>

<script>
 window.addEventListener('reload-page', event => {
    if (event.detail && event.detail.message) {
        toastr.success(event.detail.message);
    }
    window.location.reload();
});
</script>

@endpush


