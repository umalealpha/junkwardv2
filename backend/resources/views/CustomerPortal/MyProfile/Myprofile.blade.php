<!DOCTYPE html>

<html lang="en" >
    <!-- begin::Head -->
          @include('CustomerPortal.Layout.header')
          <link rel="stylesheet" type="text/css" href="{{ asset('css\GraphiteLoader.css') }}">


    <!-- end::Head -->
    <!-- begin::Body -->

        <!-- end:: Header Mobile -->
        <!-- begin:: Root -->
        <div class="kt-grid kt-grid--hor kt-grid--root">
            <!-- begin:: Page -->

                         @include('CustomerPortal.Layout.sidebar')    
                         @include('CustomerPortal.Layout.topNav')





                        <!-- begin:: Content -->
                        <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">

                            <!--begin::Dashboard 4-->
                            <!--begin::Row-->
                            <div class="row">
                            <br>
                            <div class="col-lg-9 text-center policyno">
                             <h1>{{Auth::user()->firstName}} {{Auth::user()->lastName}}</h1>
                            </div>
                       
                         
                                <!--begin::policy-->
                                <div class="col-lg-6 col-xl-6 order-lg-1 order-xl-1">
                                    <!--begin::Portlet-->
                                    <div class="kt-portlet kt-portlet--height-fluid kt-widget-17">
                                        <div class="kt-portlet__head">
                                            <div class="kt-portlet__head-label">
                                                <h3 class="kt-portlet__head-title">
                                                   Policy
                                                </h3>
                                            </div>
                                        </div>
                                        <div class="kt-portlet__body">
                                             <div class="row">
                                             	<table class="table table-hover">
							<tr>
								<th>Name:</th>
								<td>{{$userDetails->firstName}} {{$userDetails->lastName}}</td>
							  </tr>
							  <tr>
								<th>Cellphone:</th>
								<td>{{$userDetails->cellphone}}</td>
							  </tr>
							  <tr>
                                <th>Email:</th>
                                
                                @if($userDetails->email == null)
								<td>N/A</td>

                                @else 
								<td>{{$userDetails->email}}</td>

                                @endif
							  </tr>
							
							  <tr>
								<th>Omang ID:</th>
								<td>{{$userDetails->omang}}</td>
							  </tr>
							  <tr>
								<th>DOB:</th>
								<td>{{$userDetails->dob->format('d M Y')}}</td>
							  </tr>
						  </table>
                                              </div>
                                        </div>
                                        <div class="kt-portlet__foot kt-portlet__foot--md">
                                            <div class="kt-widget-17__foot">
                                                <div class="kt-widget-17__foot-info"></div>
                                                <div class="kt-widget-17__foot-toolbar"> </div>
                                            </div>
                                        </div>
                                    </div>
                                    <!--end::Portlet-->
                                </div>
                                <!--end::policy-->
                                
                                <div class="col-lg-6 col-xl-6 order-lg-1 order-xl-1">
                                    <!--begin::Portlet-->
                                    <div class="kt-portlet kt-portlet--height-fluid kt-widget-17">
                                        <div class="kt-portlet__head">
                                            <div class="kt-portlet__head-label">
                                                <h3 class="kt-portlet__head-title">
                                                    @if($userKYC->compliance == 0)
                                                    KYC Status: <span style="color:#CC0000"> Non Compliant</span>

                                                    @else 
                                                    KYC Status: <span style="color:#00C851"> KYC Compliant</span>

                                                    @endif
                                                </h3>
                                            </div>
                                        </div>
                                        <div class="kt-portlet__body">
                                            <div class="row">
                                                <table class="table table-hover table-striped">
                                                    <tr>
                                                        <th>Drivers License:</th>
                                                        <td><span class="kt-switch kt-switch--outline kt-switch--icon kt-switch--success">
                                                                <label>
                                                                    @if ($userKYC->driversLicense == 'Not Submitted')
            
                                                                    <div class="file btn  btn-primary">
                                                                        <form action="{{Route('uploadDriversLicense')}}" method="POST" accept="application/pdf , image/jpeg , image/bmp , image/png" enctype="multipart/form-data">
                                                                            {{csrf_field()}}
                                                                            <i class="la la-upload"></i>Upload Drivers License
                                                                            <input type="hidden" name="firstName" value="{{$userDetails->firstName}}" />
                                                                            <input type="hidden" name="lastName" value="{{$userDetails->lastName}}" />
                                                                            <input type="hidden" name="id" value="{{$userDetails->id}}" />
            
                                                                            <input type="file" name="driversLicense" accept=".jpg,.jpeg,.png" onchange="this.form.submit()" />
            
                                                                        </form>
                                                                    </div>
            
                                                                    @else
                                                                    <input type="checkbox" checked="checked" disabled>
                                                                    <button id="driversPreview" data-toggle="modal" class="btn btn-info" data-target="#image-gallery">View {{$userDetails->firstName}}'s Drivers License</button>
            
            
                                                                    @endif
                                                                    <span></span>
                                                                </label>
                                                            </span></td>
                                                    </tr>
                                                    <tr>
                                                        <th>Omang ID:</th>
                                                        <td><span class="kt-switch kt-switch--outline kt-switch--icon kt-switch--success">
                                                                <label>
                                                                    @if ($userKYC->omang == 'Not Submitted')
            
            
                                                                    <div class="file btn  btn-primary">
                                                                        <form action="{{Route('uploadOmang')}}" method="POST" accept="application/pdf , image/jpeg , image/bmp , image/png" enctype="multipart/form-data">
                                                                            {{csrf_field()}}
                                                                            <i class="la la-upload"></i>Upload Omang
                                                                            <input type="hidden" name="firstName" value="{{$userDetails->firstName}}" />
                                                                            <input type="hidden" name="lastName" value="{{$userDetails->lastName}}" />
                                                                            <input type="hidden" name="id" value="{{$userDetails->id}}" />
            
                                                                            <input type="file" name="omang" accept=".jpg,.jpeg,.png" onchange="this.form.submit()" />
            
                                                                        </form>
                                                                    </div>
            
                                                                    @else
                                                                    <input type="checkbox" checked="checked" disabled>
                                                                    <button id="omangPreview" data-toggle="modal" class="btn btn-info" data-target="#image-gallery">View {{$userDetails->firstName}}'s Omang</button>
            
                                                                    @endif
                                                                    <span></span>
                                                                </label>
                                                            </span></td>
                                                    </tr>
                                                    <tr>
                                                        <th>Vehicle Registration:</th>
                                                        <td><span class="kt-switch kt-switch--outline kt-switch--icon kt-switch--success">
                                                                <label>
                                                                    @if ($userKYC->vehicleRegistration == 'Not Submitted')
            
                                                                    <div class="file btn  btn-primary">
                                                                        <form action="{{Route('uploadBlueBook')}}" method="POST" accept="application/pdf , image/jpeg , image/bmp , image/png" enctype="multipart/form-data">
                                                                            {{csrf_field()}}
                                                                            <i class="la la-upload"></i>Upload Vehicle Registration Document
                                                                            <input type="hidden" name="firstName" value="{{$userDetails->firstName}}" />
                                                                            <input type="hidden" name="lastName" value="{{$userDetails->lastName}}" />
                                                                            <input type="hidden" name="id" value="{{$userDetails->id}}" />
            
                                                                            <input type="file" name="bluebook" accept=".jpg,.jpeg,.png" onchange="this.form.submit()" />
            
                                                                        </form>
                                                                    </div>
            
            
            
                                                                    @else
                                                                    <input type="checkbox" checked="checked" disabled>
                                                                    <button id="bluebookPreview" data-toggle="modal" class="btn btn-info" data-target="#image-gallery">View {{$userDetails->firstName}}'s Vehicle Registration</button>
            
                                                                    @endif
                                                                    <span></span>
                                                                </label>
                                                            </span></td>
                                                    </tr>
                                                    <tr>
                                                            <th>Proof Of Residence:</th>
                                                            <td><span class="kt-switch kt-switch--outline kt-switch--icon kt-switch--success">
                                                                    <label>
                                                                        @if ($userKYC->proofResidence == 'Not Submitted')
                
                                                                        <div class="file btn  btn-primary">
                                                                            <form action="{{Route('uploadProofOfResidence')}}" method="POST" accept="application/pdf , image/jpeg , image/bmp , image/png" enctype="multipart/form-data">
                                                                                 {{csrf_field()}}
                                                                                <i class="la la-upload"></i>Upload Proof Of Residence
                                                                                <input type="hidden" name="firstName" value="{{$userDetails->firstName}}" />
                                                                                <input type="hidden" name="lastName" value="{{$userDetails->lastName}}" />
                                                                                <input type="hidden" name="id" value="{{$userDetails->id}}" />
                                                                                <input type="file" name="residence" accept=".jpg,.jpeg,.png" onchange="this.form.submit()" />
                                                                            
                                                                            </form>
                                                                        </div>
                
                                                                        @else
                                                                        <input type="checkbox" checked="checked" disabled>
                                                                        <button id="porPreview" data-toggle="modal" class="btn btn-info" data-target="#image-gallery">View {{$userDetails->firstName}}'s Proof Of Residence</button>
                
                                                                        @endif
                                                                        <span></span>
                                                                    </label>
                                                                </span></td>
                                                        </tr>
                                                        <tr>
                                                            <th>Proof Of Income:</th>
                                                            <td><span class="kt-switch kt-switch--outline kt-switch--icon kt-switch--success">
                                                                    <label>
                                                                        @if ($userKYC->proofIncome == 'Not Submitted')
                
                                                                        <div class="file btn  btn-primary">
                                                                            <form action="{{Route('uploadProofOfIncome')}}" method="POST" accept="application/pdf , image/jpeg , image/bmp , image/png" enctype="multipart/form-data">
                                                                                {{csrf_field()}}
                                                                                <i class="la la-upload"></i>Upload Proof Of Income
                                                                                <input type="hidden" name="firstName" value="{{$userDetails->firstName}}" />
                                                                                <input type="hidden" name="lastName" value="{{$userDetails->lastName}}" />
                                                                                <input type="hidden" name="id" value="{{$userDetails->id}}" />
                
                                                                                <input type="file" name="income" accept=".jpg,.jpeg,.png" onchange="this.form.submit()" />
                
                                                                            </form>
                                                                        </div>
                                                                        @else
                                                                        <input type="checkbox" checked="checked" disabled>
                                                                        <button id="poiPreview" data-toggle="modal" class="btn btn-info" data-target="#image-gallery">View {{$userDetails->firstName}}'s Proof Of Income</button>
                
                                                                        @endif
                                                                        <span></span>
                                                                    </label>
                                                                </span></td>
                                                        </tr>
                
            
            
                                                </table>
                                            </div>
                                        </div>
                                        <div class="kt-portlet__foot kt-portlet__foot--md">
                                            <div class="kt-widget-17__foot">
                                                <div class="kt-widget-17__foot-info"></div>
                                                <div class="kt-widget-17__foot-toolbar">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <!--end::Portlet-->
                                </div>
                                <!--end::KYC-->
                                


                                <!--begin::Biling Date-->
								<div class="col-lg-6 col-xl-6 order-lg-1 order-xl-1">
                                    <!--begin::Portlet-->
                                    <div class="kt-portlet kt-portlet--height-fluid kt-widget-17">
                                        <div class="kt-portlet__head">
                                            <div class="kt-portlet__head-label">
                                                <h3 class="kt-portlet__head-title">
                                                   Biling Information
                                                </h3>
                                            </div>
                                        </div>
                                        <div class="kt-portlet__body">
                                             <div class="row">
                                             <table class="table table-hover">
							<tr>
								<th>Billing Date</th>
								<td>{{$userBanking->created_at->format('d M Y')}}</td>
							  </tr>
							  <tr>
								<th>Billing Cell</th>
								<td>Monthly</td>
							  </tr>
							  <tr>
								<th>Billing Activation Date</th>
								<td>{{$userBanking->billingStartDate->format('d M Y')}}</td>
							  </tr>
						
							
							  <tr>
								<th>Premium</th>
								<td>
									<table class="preminum">
									   @if($userBanking->prefered == 'Bank')

                                         	<tr>
											<th>Bank Name</th>
											<td id="realpayBankName"> </td>
										</tr>
										<tr>
											<th>Branch Code</th>
											<td>{{$userBanking->branchCode}}</td>
										</tr>
                                        	<tr>
											<th>Account Number</th>
											<td>{{str_repeat("*", strlen($userBanking->accountNumber)-4).substr($userBanking->accountNumber,-4)}}</td>
										</tr>   
                                        

                                        @else

	                                    <tr>
											<th>Billing Method</th>
											<td>{{$userBanking->prefered}}</td>
										</tr>
										<tr>
											<th>Billing Cell</th>
											<td>{{$userBanking->billingCell}}</td>
										</tr>
                                          
                                        @endif
                                        
									</table>
								</td>
							  </tr>
						
						  </table>
                                              </div>
                                        </div>
                                        <div class="kt-portlet__foot kt-portlet__foot--md">
                                            <div class="kt-widget-17__foot">
                                                <div class="kt-widget-17__foot-info"></div>
                                                <div class="kt-widget-17__foot-toolbar"> </div>
                                            </div>
                                        </div>
                                    </div>
                                    <!--end::Portlet-->
                                </div>
                                <!--end::Biling Date-->

                                <!--begin::Account Stats-->
								<div class="col-lg-6 col-xl-6 order-lg-1 order-xl-1">
                                    <!--begin::Portlet-->
                                    <div class="kt-portlet kt-portlet--height-fluid kt-widget-17">
                                        <div class="kt-portlet__head">
                                            <div class="kt-portlet__head-label">
                                                <h3 class="kt-portlet__head-title">
                                                   Account Stats
                                                </h3>
                                            </div>
                                        </div>
                                        <div class="kt-portlet__body">
                                             <div class="row">
                                             <table class="table table-hover">
							<tr>
								<th>Number of policies</th>
								<td>{{$userPoliciesCount}}</td>
							  </tr>
							  <tr>
								<th>Number of Vehicles</th>
								<td>{{$userCarCount}}</td>
							  </tr>
						
						
						  </table>
                                              </div>
                                        </div>
                                        <div class="kt-portlet__foot kt-portlet__foot--md">
                                            <div class="kt-widget-17__foot">
                                                <div class="kt-widget-17__foot-info"></div>
                                                <div class="kt-widget-17__foot-toolbar"> </div>
                                            </div>
                                        </div>
                                    </div>
                                    <!--end::Portlet-->
                                </div>
                                <!--end::Biling Date-->

                              
                                
                            </div>
                            <!--end::Row-->
                            <!--end::Dashboard 4-->
                        </div>
                        <!-- end:: Content -->
                    </div>
                    <!-- begin:: Footer -->
                    <div class="kt-footer kt-grid__item kt-grid kt-grid--desktop kt-grid--ver-desktop">
                    <div class="kt-footer__copyright " align="center"> 2019&nbsp;&copy;&nbsp;<a href="#" target="_blank" class="kt-link">Alpha Direct in Partnership with : </a><a href="https://www.aig.com/individual" target="_blank"><img src="{{ asset('img/aig7.png') }}" alt="not found"></a> </div>
                    </div>
                    <!-- end:: Footer -->
                </div>
                <!-- end:: Wrapper -->
            </div>
            <!-- end:: Page -->
        </div>
        <!-- end:: Root -->
        <!-- begin:: Topbar Offcanvas Panels -->
        <!-- begin::Offcanvas Toolbar Quick Actions -->
        <div id="kt_offcanvas_toolbar_quick_actions" class="kt-offcanvas-panel">
            <div class="kt-offcanvas-panel__head">
                <h3 class="kt-offcanvas-panel__title">
                    Quick Actions 
                </h3>
                <a href="#" class="kt-offcanvas-panel__close" id="kt_offcanvas_toolbar_quick_actions_close"><i class="flaticon2-delete"></i></a>
            </div>
            <div class="kt-offcanvas-panel__body">
                <div class="kt-grid-nav-v2">
                    <a href="#" class="kt-grid-nav-v2__item">
                        <div class="kt-grid-nav-v2__item-icon"><i class="flaticon2-box"></i></div>
                        <div class="kt-grid-nav-v2__item-title">Orders</div>
                    </a>
                    <a href="#" class="kt-grid-nav-v2__item">
                        <div class="kt-grid-nav-v2__item-icon"><i class="flaticon-download-1"></i></div>
                        <div class="kt-grid-nav-v2__item-title">Uploades</div>
                    </a>
                    <a href="#" class="kt-grid-nav-v2__item">
                        <div class="kt-grid-nav-v2__item-icon"><i class="flaticon2-supermarket"></i></div>
                        <div class="kt-grid-nav-v2__item-title">Products</div>
                    </a>
                    <a href="#" class="kt-grid-nav-v2__item">
                        <div class="kt-grid-nav-v2__item-icon"><i class="flaticon2-avatar"></i></div>
                        <div class="kt-grid-nav-v2__item-title">Customers</div>
                    </a>
                    <a href="#" class="kt-grid-nav-v2__item">
                        <div class="kt-grid-nav-v2__item-icon"><i class="flaticon2-list"></i></div>
                        <div class="kt-grid-nav-v2__item-title">Blog Posts</div>
                    </a>
                    <a href="#" class="kt-grid-nav-v2__item">
                        <div class="kt-grid-nav-v2__item-icon"><i class="flaticon2-settings"></i></div>
                        <div class="kt-grid-nav-v2__item-title">Settings</div>
                    </a>
                </div>
            </div>
        </div>
        <!-- end::Offcanvas Toolbar Quick Actions -->
        <!-- end:: Topbar Offcanvas Panels -->
        <!-- begin:: Quick Panel -->
        <div id="kt_quick_panel" class="kt-offcanvas-panel">
            <div class="kt-offcanvas-panel__nav">
                <ul class="nav nav-pills" role="tablist">
                    <li class="nav-item active"> <a class="nav-link active" data-toggle="tab" href="#kt_quick_panel_tab_notifications" role="tab">Notifications</a> </li>
                    <li class="nav-item"> <a class="nav-link" data-toggle="tab" href="#kt_quick_panel_tab_actions" role="tab">Actions</a> </li>
                    <li class="nav-item"> <a class="nav-link" data-toggle="tab" href="#kt_quick_panel_tab_settings" role="tab">Settings</a> </li>
                </ul>
                <button class="kt-offcanvas-panel__close" id="kt_quick_panel_close_btn"><i class="flaticon2-delete"></i></button>
            </div>
            <div class="kt-offcanvas-panel__body">
                <div class="tab-content">
                    <div class="tab-pane fade show kt-offcanvas-panel__content kt-scroll active" id="kt_quick_panel_tab_notifications" role="tabpanel">
                        <!--Begin::Timeline -->
                        <div class="kt-timeline">
                            <!--Begin::Item -->
                            <div class="kt-timeline__item kt-timeline__item--success">
                                <div class="kt-timeline__item-section">
                                    <div class="kt-timeline__item-section-border">
                                        <div class="kt-timeline__item-section-icon"> <i class="flaticon-feed kt-font-success"></i> </div>
                                    </div>
                                    <span class="kt-timeline__item-datetime">02:30 PM</span> 
                                </div>
                                <a href="" class="kt-timeline__item-text"> KeenThemes created new layout whith tens of new options for Keen Admin panel </a> 
                                <div class="kt-timeline__item-info"> HTML,CSS,VueJS </div>
                            </div>
                            <!--End::Item -->
                            <!--Begin::Item -->
                            <div class="kt-timeline__item kt-timeline__item--danger">
                                <div class="kt-timeline__item-section">
                                    <div class="kt-timeline__item-section-border">
                                        <div class="kt-timeline__item-section-icon"> <i class="flaticon-safe-shield-protection kt-font-danger"></i> </div>
                                    </div>
                                    <span class="kt-timeline__item-datetime">01:20 AM</span> 
                                </div>
                                <a href="" class="kt-timeline__item-text"> New secyrity alert by Firewall & order to take aktion on User Preferences </a> 
                                <div class="kt-timeline__item-info"> Security, Fieewall </div>
                            </div>
                            <!--End::Item -->
                            <!--Begin::Item -->
                            <div class="kt-timeline__item kt-timeline__item--brand">
                                <div class="kt-timeline__item-section">
                                    <div class="kt-timeline__item-section-border">
                                        <div class="kt-timeline__item-section-icon"> <i class="flaticon2-box kt-font-brand"></i> </div>
                                    </div>
                                    <span class="kt-timeline__item-datetime">Yestardey</span> 
                                </div>
                                <a href="" class="kt-timeline__item-text"> FlyMore design mock-ups been uploadet by designers Bob, Naomi, Richard </a> 
                                <div class="kt-timeline__item-info"> PSD, Sketch, AJ </div>
                            </div>
                            <!--End::Item -->
                            <!--Begin::Item -->
                            <div class="kt-timeline__item kt-timeline__item--warning">
                                <div class="kt-timeline__item-section">
                                    <div class="kt-timeline__item-section-border">
                                        <div class="kt-timeline__item-section-icon"> <i class="flaticon-pie-chart-1 kt-font-warning"></i> </div>
                                    </div>
                                    <span class="kt-timeline__item-datetime">Aug 13,2018</span> 
                                </div>
                                <a href="" class="kt-timeline__item-text">
                                    Meeting with Ken Digital Corp ot Unit14, 3 Edigor Buildings, George Street, Loondon
                                    <br>
                                    England, BA12FJ 
                                </a>
                                <div class="kt-timeline__item-info"> Meeting, Customer </div>
                            </div>
                            <!--End::Item -->
                            <!--Begin::Item -->
                            <div class="kt-timeline__item kt-timeline__item--info">
                                <div class="kt-timeline__item-section">
                                    <div class="kt-timeline__item-section-border">
                                        <div class="kt-timeline__item-section-icon"> <i class="flaticon-notepad kt-font-info"></i> </div>
                                    </div>
                                    <span class="kt-timeline__item-datetime">May 09, 2018</span> 
                                </div>
                                <a href="" class="kt-timeline__item-text"> KeenThemes created new layout whith tens of new options for Keen Admin panel </a> 
                                <div class="kt-timeline__item-info"> HTML,CSS,VueJS </div>
                            </div>
                            <!--End::Item -->
                            <!--Begin::Item -->
                            <div class="kt-timeline__item kt-timeline__item--accent">
                                <div class="kt-timeline__item-section">
                                    <div class="kt-timeline__item-section-border">
                                        <div class="kt-timeline__item-section-icon" > <i class="flaticon-bell kt-font-success"></i> </div>
                                    </div>
                                    <span class="kt-timeline__item-datetime">01:20 AM</span> 
                                </div>
                                <a href="" class="kt-timeline__item-text"> New secyrity alert by Firewall & order to take aktion on User Preferences </a> 
                                <div class="kt-timeline__item-info"> Security, Fieewall </div>
                            </div>
                            <!--End::Item -->
                        </div>
                        <!--End::Timeline -->
                    </div>
                    <div class="tab-pane fade kt-offcanvas-panel__content kt-scroll" id="kt_quick_panel_tab_actions" role="tabpanel">
                        <!--begin::Portlet-->
                        <div class="kt-portlet kt-portlet--solid-success">
                            <div class="kt-portlet__head">
                                <div class="kt-portlet__head-label">
                                    <span class="kt-portlet__head-icon kt-hide"><i class="flaticon-stopwatch"></i></span>
                                    <h3 class="kt-portlet__head-title">
                                        Recent Bills
                                    </h3>
                                </div>
                                <div class="kt-portlet__head-toolbar">
                                    <div class="kt-portlet__head-group">
                                        <div class="dropdown dropdown-inline">
                                            <button type="button" class="btn btn-sm btn-font-light btn-outline-hover-light btn-circle btn-icon" data-toggle="dropdown" aria-haspopup="true" aria-expanded="true"> <i class="flaticon-more"></i> </button>
                                            <div class="dropdown-menu dropdown-menu-right">
                                                <a class="dropdown-item" href="#">Action</a> <a class="dropdown-item" href="#">Another action</a> <a class="dropdown-item" href="#">Something else here</a> 
                                                <div class="dropdown-divider"></div>
                                                <a class="dropdown-item" href="#">Separated link</a> 
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="kt-portlet__body">
                                <div class="kt-portlet__content"> Lorem Ipsum is simply dummy text of the printing and typesetting simply dummy text of the printing industry. </div>
                            </div>
                            <div class="kt-portlet__foot kt-portlet__foot--sm kt-align-right"> <a href="#" class="btn btn-bold btn-upper btn-sm btn-font-light btn-outline-hover-light">Dismiss</a> <a href="#" class="btn btn-bold btn-upper btn-sm btn-font-light btn-outline-hover-light">View</a> </div>
                        </div>
                        <!--end::Portlet-->
                        <!--begin::Portlet-->
                        <div class="kt-portlet kt-portlet--solid-focus">
                            <div class="kt-portlet__head">
                                <div class="kt-portlet__head-label">
                                    <span class="kt-portlet__head-icon kt-hide"><i class="flaticon-stopwatch"></i></span>
                                    <h3 class="kt-portlet__head-title">
                                        Latest Orders
                                    </h3>
                                </div>
                                <div class="kt-portlet__head-toolbar">
                                    <div class="kt-portlet__head-group">
                                        <div class="dropdown dropdown-inline">
                                            <button type="button" class="btn btn-sm btn-font-light btn-outline-hover-light btn-circle btn-icon" data-toggle="dropdown" aria-haspopup="true" aria-expanded="true"> <i class="flaticon-more"></i> </button>
                                            <div class="dropdown-menu dropdown-menu-right">
                                                <a class="dropdown-item" href="#">Action</a> <a class="dropdown-item" href="#">Another action</a> <a class="dropdown-item" href="#">Something else here</a> 
                                                <div class="dropdown-divider"></div>
                                                <a class="dropdown-item" href="#">Separated link</a> 
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="kt-portlet__body">
                                <div class="kt-portlet__content"> Lorem Ipsum is simply dummy text of the printing and typesetting simply dummy text of the printing industry. </div>
                            </div>
                            <div class="kt-portlet__foot kt-portlet__foot--sm kt-align-right"> <a href="#" class="btn btn-bold btn-upper btn-sm btn-font-light btn-outline-hover-light">Dismiss</a> <a href="#" class="btn btn-bold btn-upper btn-sm btn-font-light btn-outline-hover-light">View</a> </div>
                        </div>
                        <!--end::Portlet-->
                        <!--begin::Portlet-->
                        <div class="kt-portlet kt-portlet--solid-info">
                            <div class="kt-portlet__head">
                                <div class="kt-portlet__head-label">
                                    <h3 class="kt-portlet__head-title">
                                        Latest Invoices
                                    </h3>
                                </div>
                                <div class="kt-portlet__head-toolbar">
                                    <div class="kt-portlet__head-group">
                                        <div class="dropdown dropdown-inline">
                                            <button type="button" class="btn btn-sm btn-font-light btn-outline-hover-light btn-circle btn-icon" data-toggle="dropdown" aria-haspopup="true" aria-expanded="true"> <i class="flaticon-more"></i> </button>
                                            <div class="dropdown-menu dropdown-menu-right">
                                                <a class="dropdown-item" href="#">Action</a> <a class="dropdown-item" href="#">Another action</a> <a class="dropdown-item" href="#">Something else here</a> 
                                                <div class="dropdown-divider"></div>
                                                <a class="dropdown-item" href="#">Separated link</a> 
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="kt-portlet__body">
                                <div class="kt-portlet__content"> Lorem Ipsum is simply dummy text of the printing and typesetting simply dummy text of the printing industry. </div>
                            </div>
                            <div class="kt-portlet__foot kt-portlet__foot--sm kt-align-right"> <a href="#" class="btn btn-bold btn-upper btn-sm btn-font-light btn-outline-hover-light">Dismiss</a> <a href="#" class="btn btn-bold btn-upper btn-sm btn-font-light btn-outline-hover-light">View</a> </div>
                        </div>
                        <!--end::Portlet-->
                        <!--begin::Portlet-->
                        <div class="kt-portlet kt-portlet--solid-warning">
                            <div class="kt-portlet__head">
                                <div class="kt-portlet__head-label">
                                    <h3 class="kt-portlet__head-title">
                                        New Comments
                                    </h3>
                                </div>
                                <div class="kt-portlet__head-toolbar">
                                    <div class="kt-portlet__head-group">
                                        <div class="dropdown dropdown-inline">
                                            <button type="button" class="btn btn-sm btn-font-light btn-outline-hover-light btn-circle btn-icon" data-toggle="dropdown" aria-haspopup="true" aria-expanded="true"> <i class="flaticon-more"></i> </button>
                                            <div class="dropdown-menu dropdown-menu-right">
                                                <a class="dropdown-item" href="#">Action</a> <a class="dropdown-item" href="#">Another action</a> <a class="dropdown-item" href="#">Something else here</a> 
                                                <div class="dropdown-divider"></div>
                                                <a class="dropdown-item" href="#">Separated link</a> 
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="kt-portlet__body">
                                <div class="kt-portlet__content"> Lorem Ipsum is simply dummy text of the printing and typesetting simply dummy text of the printing industry. </div>
                            </div>
                            <div class="kt-portlet__foot kt-portlet__foot--sm kt-align-right"> <a href="#" class="btn btn-bold btn-upper btn-sm btn-font-light btn-outline-hover-light">Dismiss</a> <a href="#" class="btn btn-bold btn-upper btn-sm btn-font-light btn-outline-hover-light">View</a> </div>
                        </div>
                        <!--end::Portlet-->
                        <!--begin::Portlet-->
                        <div class="kt-portlet kt-portlet--solid-brand">
                            <div class="kt-portlet__head">
                                <div class="kt-portlet__head-label">
                                    <h3 class="kt-portlet__head-title">
                                        Recent Posts
                                    </h3>
                                </div>
                                <div class="kt-portlet__head-toolbar">
                                    <div class="kt-portlet__head-group">
                                        <div class="dropdown dropdown-inline">
                                            <button type="button" class="btn btn-sm btn-font-light btn-outline-hover-light btn-circle btn-icon" data-toggle="dropdown" aria-haspopup="true" aria-expanded="true"> <i class="flaticon-more"></i> </button>
                                            <div class="dropdown-menu dropdown-menu-right">
                                                <a class="dropdown-item" href="#">Action</a> <a class="dropdown-item" href="#">Another action</a> <a class="dropdown-item" href="#">Something else here</a> 
                                                <div class="dropdown-divider"></div>
                                                <a class="dropdown-item" href="#">Separated link</a> 
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="kt-portlet__body">
                                <div class="kt-portlet__content"> Lorem Ipsum is simply dummy text of the printing and typesetting simply dummy text of the printing industry. </div>
                            </div>
                            <div class="kt-portlet__foot kt-portlet__foot--sm kt-align-right"> <a href="#" class="btn btn-bold btn-upper btn-sm btn-font-light btn-outline-hover-light">Dismiss</a> <a href="#" class="btn btn-bold btn-upper btn-sm btn-font-light btn-outline-hover-light">View</a> </div>
                        </div>
                        <!--end::Portlet-->
                    </div>
                    <div class="tab-pane fade kt-offcanvas-panel__content kt-scroll" id="kt_quick_panel_tab_settings" role="tabpanel">
                        <form class="kt-form">
                            <div class="kt-heading kt-heading--space-sm">Notifications</div>
                            <div class="form-group form-group-xs row">
                                <label class="col-8 col-form-label">Enable notifications:</label>
                                <div class="col-4 kt-align-right">
                                    <span class="kt-switch kt-switch--sm">
                                        <label>
                                            <input type="checkbox" checked="checked" name="quick_panel_notifications_1">
                                            <span></span> 
                                        </label>
                                    </span>
                                </div>
                            </div>
                            <div class="form-group form-group-xs row">
                                <label class="col-8 col-form-label">Enable audit log:</label>
                                <div class="col-4 kt-align-right">
                                    <span class="kt-switch kt-switch--sm">
                                        <label>
                                            <input type="checkbox" name="quick_panel_notifications_2">
                                            <span></span> 
                                        </label>
                                    </span>
                                </div>
                            </div>
                            <div class="form-group form-group-last form-group-xs row">
                                <label class="col-8 col-form-label">Notify on new orders:</label>
                                <div class="col-4 kt-align-right">
                                    <span class="kt-switch kt-switch--sm">
                                        <label>
                                            <input type="checkbox" checked="checked" name="quick_panel_notifications_2">
                                            <span></span> 
                                        </label>
                                    </span>
                                </div>
                            </div>
                            <div class="kt-separator kt-separator--space-md kt-separator--border-dashed"></div>
                            <div class="kt-heading kt-heading--space-sm">Orders</div>
                            <div class="form-group form-group-xs row">
                                <label class="col-8 col-form-label">Enable order tracking:</label>
                                <div class="col-4 kt-align-right">
                                    <span class="kt-switch kt-switch--sm kt-switch--danger">
                                        <label>
                                            <input type="checkbox" checked="checked" name="quick_panel_notifications_3">
                                            <span></span> 
                                        </label>
                                    </span>
                                </div>
                            </div>
                            <div class="form-group form-group-xs row">
                                <label class="col-8 col-form-label">Enable orders reports:</label>
                                <div class="col-4 kt-align-right">
                                    <span class="kt-switch kt-switch--sm kt-switch--danger">
                                        <label>
                                            <input type="checkbox" name="quick_panel_notifications_3">
                                            <span></span> 
                                        </label>
                                    </span>
                                </div>
                            </div>
                            <div class="form-group form-group-last form-group-xs row">
                                <label class="col-8 col-form-label">Allow order status auto update:</label>
                                <div class="col-4 kt-align-right">
                                    <span class="kt-switch kt-switch--sm kt-switch--danger">
                                        <label>
                                            <input type="checkbox" checked="checked" name="quick_panel_notifications_4">
                                            <span></span> 
                                        </label>
                                    </span>
                                </div>
                            </div>
                            <div class="kt-separator kt-separator--space-md kt-separator--border-dashed"></div>
                            <div class="kt-heading kt-heading--space-sm">Customers</div>
                            <div class="form-group form-group-xs row">
                                <label class="col-8 col-form-label">Enable customer singup:</label>
                                <div class="col-4 kt-align-right">
                                    <span class="kt-switch kt-switch--sm kt-switch--success">
                                        <label>
                                            <input type="checkbox" checked="checked" name="quick_panel_notifications_5">
                                            <span></span> 
                                        </label>
                                    </span>
                                </div>
                            </div>
                            <div class="form-group form-group-xs row">
                                <label class="col-8 col-form-label">Enable customers reporting:</label>
                                <div class="col-4 kt-align-right">
                                    <span class="kt-switch kt-switch--sm kt-switch--success">
                                        <label>
                                            <input type="checkbox" name="quick_panel_notifications_5">
                                            <span></span> 
                                        </label>
                                    </span>
                                </div>
                            </div>
                            <div class="form-group form-group-last form-group-xs row">
                                <label class="col-8 col-form-label">Notifiy on new customer registration:</label>
                                <div class="col-4 kt-align-right">
                                    <span class="kt-switch kt-switch--sm kt-switch--success">
                                        <label>
                                            <input type="checkbox" checked="checked" name="quick_panel_notifications_6">
                                            <span></span> 
                                        </label>
                                    </span>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <!-- end:: Quick Panel -->
        <!-- begin:: Scrolltop -->
        <div id="kt_scrolltop" class="kt-scrolltop"> <i class="la la-arrow-up"></i> </div>
        <!-- end:: Scrolltop -->
<!-- Large Modal1 -->
 <div id="omangModal" class="modal fade bd-example-modal-xl" tabindex="-1" role="dialog" aria-labelledby="myLargeModalLabel" aria-hidden="true">
   <div class="modal-dialog modal-xl">
     <div class="modal-content">
        <div class="modal-header">
        <h5 class="modal-title">Omang Upload</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"> <span aria-hidden="true">&times;</span> </button>
        </div>
        <div class="modal-body">
         <div class="row">

         <!-- OMANG UPLOAD-->
		 </div>
        </div>
       <div class="modal-footer">
       <button type="button" class="btn btn-outline-brand" data-dismiss="modal">Close</button>
       <button type="button" class="btn btn-outline-brand">Upload Omang</button>
    </div>
  </div>
</div>
</div>
<!-- /Large Modal1 -->
<!-- Large Modal 2-->
 <div class="modal fade bd-example-modal-xl1" tabindex="-1" role="dialog" aria-labelledby="myLargeModalLabel" aria-hidden="true">
   <div class="modal-dialog modal-xl">
     <div class="modal-content">
        <div class="modal-header">
        <h5 class="modal-title">Add Policy</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"> <span aria-hidden="true">&times;</span> </button>
        </div>
        <div class="modal-body">
                                             <div class="row">
												  <div class="col-md-3 form-group">
														<label>First Name :</label>
														<input type="text" class="form-control" aria-describedby="emailHelp" placeholder="First Name"> 
													</div>
												  <div class="col-md-3 form-group">
														<label>Last Name :</label>
														<input type="text" class="form-control" aria-describedby="emailHelp" placeholder="Last Name"> 
													</div>
												  <div class="col-md-3 form-group">
														<label>Policy No :</label>
														<input type="text" class="form-control" aria-describedby="emailHelp" placeholder="Policy No"> 
													</div>
												  <div class="col-md-3 form-group">
														<label>Agency Name :</label>
														<input type="text" class="form-control" aria-describedby="emailHelp" placeholder="Agency Name"> 
													</div>
                                              </div>
                                             <div class="row">
												  <div class="col-md-3 form-group">
														<label for="exampleSelect1">App. Status</label>
															<select class="form-control" id="exampleSelect1">
																<option >Select</option>
																<option value="1">Open</option>
																<option value="1">Pending</option>
																<option value="1">Closed</option>
															</select>
													</div>
												  <div class="col-md-3 form-group">
														<label>Motor Reg. No :</label>
														<input type="text" class="form-control" aria-describedby="emailHelp" placeholder="Motor Reg. No"> 
													</div>
												  <div class="col-md-3 form-group">
														<label>Agency Code :</label>
														<input type="text" class="form-control" aria-describedby="emailHelp" placeholder="Agency Code"> 
													</div>
												  <div class="col-md-3 form-group">
														<label>Submitted By :</label>
														<input type="text" class="form-control" aria-describedby="emailHelp" placeholder="Submitted By"> 
													</div>
                                              </div>
        </div>
       <div class="modal-footer">
       <button type="button" class="btn btn-outline-brand" data-dismiss="modal">Close</button>
       <button type="button" class="btn btn-outline-brand">Save changes</button>
    </div>
  </div>
</div>
</div>
<!-- /Large Modal 2-->           
                
                
                
        <!-- begin::Global Config(global config for global JS sciprts) -->
        <script>
            var KTAppOptions = {

    "colors": {

        "state": {

            "brand": "#5d78ff",

            "metal": "#c4c5d6",

            "light": "#ffffff",

            "accent": "#00c5dc",

            "primary": "#5867dd",

            "success": "#34bfa3",

            "info": "#36a3f7",

            "warning": "#ffb822",

            "danger": "#fd3995",

            "focus": "#9816f4"
        },

        "base": {

            "label": [

                "#c5cbe3",

                "#a1a8c3",

                "#3d4465",

                "#3e4466"
            ],

            "shape": [

                "#f0f3ff",

                "#d9dffa",

                "#afb4d4",

                "#646c9a"
            ]
        }
    }
};
     
        $(document).ready(function(){

            var urlValue = '{{ \Config::get('values.graphite_url') }}' 
            $.ajax({
                /* the route pointing to the post function */
                url: urlValue+'realpay/getBanks',
                type: 'GET',
                /* send the csrf-token and the input to the controller */
                data: {},
                dataType: 'JSON',
                /* remind that 'data' is the response of the AjaxController */
                success: function (data) { 
                    if(data){

                    console.log(data);
            
                    $.each(data, function(key, value){

                        if(value["ns0:bankNum"] == {{$userBanking->bankName}}){

                            $('#realpayBankName').text(value["ns0:bankDesc"]);
                        }
                    });
                  }
                }
            });

        });


        </script>
        <!-- end::Global Config -->
          @include('CustomerPortal.Layout.scripts')

    </body>
    <!-- end::Body -->
</html>