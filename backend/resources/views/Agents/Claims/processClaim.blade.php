<!DOCTYPE html>

<html lang="en" >
    <!-- begin::Head -->
    <head>
             @include('Agents.Layout.header')

    </head>
    <!-- end::Head -->
    <!-- begin::Body -->

        <!-- end:: Header Mobile -->
        <!-- begin:: Root -->
        <div class="kt-grid kt-grid--hor kt-grid--root">
            <!-- begin:: Page -->
            <div class="kt-grid__item kt-grid__item--fluid kt-grid kt-grid--ver kt-page">

                @include('Agents.Layout.sidebar')

                @include('Agents.Layout.topNav')

 <!-- check if is first time login -->
   
                    <!-- begin:: Content -->
                        <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
                                <div class="loading">Loading&#8230;</div>


                            <div class="kt-portlet">
                                <div class="kt-portlet__body kt-portlet__body--fit">
                                    <div class="kt-wizard-v3" id="kt_wizard_v3" data-ktwizard-state="step-first">
                                        <!--begin: Form Wizard Nav -->
                                        <div class="kt-wizard-v3__nav">
                                            <div class="kt-wizard-v3__nav-line"></div>
                                            <div class="kt-wizard-v3__nav-items">

                                                <a class="kt-wizard-v3__nav-item" href="#" data-ktwizard-type="step" data-ktwizard-state="current" id="showFirst">
                                                    <span>1</span> <i class="fa fa-check"></i> 
                                                    <div class="kt-wizard-v3__nav-label">Glass Claim Form</div>
                                                </a>
                                                <a class="kt-wizard-v3__nav-item" href="#" data-ktwizard-type="step" id="hideFirst" >
                                                    <span>2</span> <i class="fa fa-check"></i> 
                                                    <div class="kt-wizard-v3__nav-label">Agent Authorization</div>
                                                </a>
                                          
                                                <a class="kt-wizard-v3__nav-item" href="#" data-ktwizard-type="step" id="thirdStep">
                                                    <span>3</span> <i class="fa fa-check"></i> 
                                                    <div class="kt-wizard-v3__nav-label">Select Supplier</div>
                                                </a>
                                                <a class="kt-wizard-v3__nav-item" href="#" data-ktwizard-type="step" id="fourthStep">
                                                    <span>4</span> <i class="fa fa-check"></i> 
                                                    <div class="kt-wizard-v3__nav-label">Supplier Quote Totals </div>
                                                </a>
                                          
                                            </div>
                                        </div>
                                        <!--end: Form Wizard Nav -->
                                        <!--begin: Form Wizard Form-->

                                        <form id="kt_form" name="ClaimForm" class="kt-form claim-form" action="{{Route('ClaimForm')}}" method="POST" >
                                        {{csrf_field()}}
                                            <!--begin: Form Wizard Step 1-->
                                            <div class="kt-wizard-v3__content" data-ktwizard-type="step-content" data-ktwizard-state="current" style="width:80%">
                                               <h3 class="kt-section__title">Glass Claim Form</h3>
                                                <div class="kt-heading kt-heading--md">INSURED</div>
                                                <div class="kt-separator kt-separator--height-xs"></div>
                                                <div class="kt-form__section kt-form__section--first">
                                                  <div class="row">
                                                    <div class="col-lg-6">
                                                    <div class="form-group">
                                                        <label>Policy No.</label>
                                                        <input type="text" class="form-control" name="Policy" placeholder="Policy No" value="MIS-2019-{{$policyDetails->policyNumber}}" readonly> 
                                                    </div>
                                                    </div>
														<div class="col-lg-6">
															<div class="form-group">
																<label>Name of Insured:</label>
																<input type="text" class="form-control" name="customerName" placeholder="Name of Insured" value="{{$userDetails->firstName}} {{$userDetails->lastName}}" readonly> 
															</div>
														</div>
                                                    </div>
                                                  <div class="row">
                                                    	<div class="col-lg-6">
													

                                                    </div>
                                                   		
                                                    </div>
                                                  <div class="kt-separator kt-separator--border-dashed kt-separator--space-lg"></div>
                                                  <div class="kt-heading kt-heading--md">VEHICLE</div>
                                                  <div class="row">
                                                    	<div class="col-lg-3">
															<div class="form-group">
															<label>Registration No.</label>
															<input type="text" class="form-control" name="password" placeholder="Enter Registration No." value="{{$vehicleDetails->vehiclePlate}}" readonly> 
															</div>
                                                    	</div>
                                                    	<div class="col-lg-3">
                                                        
                                                        <div class="form-group">
                                                            <label>Make</label>
                                                            <input type="text" class="form-control"  value="{{$vehicleDetails->make}}" readonly>                                                        
                                                        </div>


                                                    	</div>
                                                    	<div class="col-lg-3">
                                                        <div class="form-group">
                                                            <label>Model</label>
                                                            <input type="text" class="form-control"  value="{{$vehicleDetails->model}}" readonly>                                                        
                                           
                                                        </div>
                                                    	</div>
                                                    	<div class="col-lg-3">
                                                        <div class="form-group">
                                                            <label>Year</label>
                                                            <input type="text" class="form-control"  value="{{$vehicleDetails->year}}" readonly>                                                        
                                                   
                                                            </div>
                                                    	</div>

                                                    </div>
                                                  <div class="row">
                                                    	<div class="col-lg-6">
                                                        <div class="form-group">
                                                         <label>Type of Glass</label>
                                                         <div></div>
                                                         @if($glass_claims->glassType != null)
                                                             <input id="glassType" name="glassType" class="form-control" value="{{$glass_claims->glassType}}" readonly >
                                                           
                                                         @else
                                                          <select class="custom-select form-control" name="glassType">
                                                             <option value="Door Window">Front Windscreen</option>
                                                             <option value="Door Window">Door Window</option>
                                                             <option value="Back Window">Back Window</option>
                                                             <option value="Mirrors">Mirrors</option>
                                                         </select>
     
                                                         @endif
                                                         </div>
                                                        </div>
                                                        
                                                    
                                                       
                                                    </div>
                                               
                                                  <div class="kt-separator kt-separator--border-dashed kt-separator--space-lg"></div>
                                                <div class="kt-heading kt-heading--md"> <a href="{{URL::route('supplierViewQuote',['id'=>$vehicleDetails->id,'supplierEmail'=> 'kabosedirwa@gmail.com'])}}"> DAMAGE</a></div>
                                                  <div class="row">
                                                    <div class="col-lg-6">
                                                        <div class="form-group">
														<label>Date of Damage</label>
                                                        @if($glass_claims->incidentDate != null)
														<input id="incidentDate" on class="form-control" type="text" name="incidentDate" value="{{$glass_claims->incidentDate}}" id="example-date-input" readonly>
                                                		@else
														<input id="incidentDate" class="form-control" type="date" name="incidentDate" value="{{$glass_claims->incidentDate}}" id="example-date-input" required>
                                                        @endif
                                                        </div>
                                                    </div>
                                                    <div class="col-lg-6">
                                                    <div class="form-group">
															<label>Damage Extent</label>
															<div class="kt-radio-inline">
																<label class="kt-radio">
                                                                @if($glass_claims->damageExtent == null)
																	<input type="radio" name="damageExtent" value="Shattered" >
																	Cracked <span></span> 
																</label>
                                                                	<label class="kt-radio">
																	<input type="radio" name="damageExtent" value="Cracked" >
																	Shattered <span></span> 
																</label>
                                                                @elseif($glass_claims->damageExtent == 'Cracked')
                                                                    <input type="radio" name="damageExtent" value="{{$glass_claims->damageExtent}}" checked readonly>
																	Cracked <span></span> 
																</label>
                                                                	<label class="kt-radio">
																	<input type="radio" name="damageExtent"  disabled>
																	Shattered <span></span> 
																</label>

                                                                @else
                                                                <input type="radio" name="damageExtent" value=""  disabled>
																	Cracked <span></span> 
																</label>
                                                                	<label class="kt-radio">
																	<input type="radio" name="damageExtent" value="{{$glass_claims->damageExtent}}" checked readonly>
																	Shattered <span></span> 
																</label>
															
                                                                @endif
															</div>
															</div>
						
                                                    </div>
                                                    </div>
                                                  <div class="row">
                                                    	<div class="col-lg-12">
															<div class="form-group">
															<label>Cause of Damage</label>
                                                            @if($glass_claims->causeOfDamage != null)
                                                            <div class="form-group form-group-last">
                                                                <textarea class="form-control" name="causeOfDamage" id="exampleTextarea" rows="3" value="{{$glass_claims->causeOfDamage}}" readonly ></textarea>
                                                            </div>
                                                            @else
                                                            <div class="form-group form-group-last">
                                                                <textarea class="form-control" name="causeOfDamage" id="exampleTextarea" rows="3" required></textarea>
                                                            </div>
                                                            @endif
                                                            </div>


                                                         
                                                    	</div>

                                                  </div>

                                              

                              
                                                  
                                                   <div class="row">
                                                    <div class="col-lg-12">
                                                    <input type="hidden" name="claim_id" value="{{$glass_claims->id}}">
													 <button id="submitForm" type="submit" value="submit" class="btn btn-success btn-lg btn-block">Submit Claim</button>
                                                     
 													</div>
                                                    </div>
                                                </form>

                                                    
                                                 
                                                </div>
                                            </div>
                                            <!--end: Form Wizard Step 1-->


                                            <!--begin: Form Wizard Step 2-->
                                         
                                            <div class="kt-form" id="step2">
                                                 {{--    {{csrf_field()}}
                                                        <input type="hidden" name="claim_id" value="{{$glass_claims->id}}" />
                                                        <input type="hidden" name="customerCell" value="{{$userDetails->cellphone}}" />
                                                        <input type="hidden" name="customerName" value="{{$userDetails->firstName}}" />
                                                        <input type="hidden" name="claimState" value="authorized"/>
                                            --}}
                                            <div class="kt-wizard-v3__content" data-ktwizard-type="step-content" style="width:80%;">
                                                <div class="kt-heading kt-heading--md">Step 2</div>
                                                <div class="kt-separator kt-separator--height-xs"></div>
                                                <div class="kt-form__section kt-form__section--first">

 <!-- begin:: Content -->
                        <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
                            <div class="row">
                                <div class="col-xl-12">
                                    <!--begin::Portlet-->
                                    
                                    <div class="kt-portlet">
                                        <div class="kt-invoice-v2">
                                            <div class="kt-invoice-v2__header grid">
                                                <div class="kt-invoice-v2__header-right">
                                                    <div class="kt-invoice-v2__logo thumb">
                                                     <h3>Before</h3>
                                                     <div class="row">

                                                     <div class="col-lg-3">
                                                            <a class="thumbnail" href="#" id="frontViewModal" data-image-id="frontView" data-toggle="modal" data-title="This is my title" data-caption="{{$vehicleDetails->make}} Front Windscreen" data-image="{{asset('images/windscreen.jpg')}}" data-target="#image-gallery">
                                                                @if($vehicleDetails->front == null)
                                                                <img src="{{asset('images/notAvailable.png')}}" width="100%" height="auto" class="gridimg" title="Invoice" alt="Invoice">

                                                                @else 
                                                                <img src="{{Storage::disk('s3')->url($vehicleDetails->front)}}" width="100%" height="auto" class="gridimg" title="Invoice" alt="Invoice">

                                                                @endif
                                                            </a>
                                                     </div>
                                                     <div class="col-lg-3">
                                                            <a class="thumbnail" href="#" id="backViewModal" data-image-id="backView" data-toggle="modal" data-title="This is my title" data-caption="{{$vehicleDetails->make}} Front Windscreen" data-image="{{asset('images/windscreen.jpg')}}" data-target="#image-gallery">
                                                                @if($vehicleDetails->back == null)
                                                                <img src="{{asset('images/notAvailable.png')}}" width="100%" height="auto" class="gridimg" title="Invoice" alt="Invoice">
                                                                @else 
                                                                <img src="{{Storage::disk('s3')->url($vehicleDetails->back)}}" width="100%" height="auto" class="gridimg" title="Invoice" alt="Invoice">
                                                                @endif
                                                            </a>
                                                     </div>
                                                     <div class="col-lg-3">
                                                            <a class="thumbnail" href="#" id="rightViewModal" data-image-id="rightView" data-toggle="modal" data-title="This is my title" data-caption="{{$vehicleDetails->make}} Front Windscreen" data-image="{{asset('images/windscreen.jpg')}}" data-target="#image-gallery">
                                                               @if($vehicleDetails->right == null)
                                                               <img src="{{asset('/images/notAvailable.png')}}" width="100%" height="auto" class="gridimg" title="Invoice" alt="Invoice">

                                                               @else 
                                                               <img src="{{Storage::disk('s3')->url($vehicleDetails->right)}}" width="100%" height="auto" class="gridimg" title="Invoice" alt="Invoice">

                                                               @endif
                                                            </a>
                                                     </div>
                                                     <div class="col-lg-3">
                                                            <a class="thumbnail" href="#" id="leftViewModal" data-image-id="leftView" data-toggle="modal" data-title="This is my title" data-caption="{{$vehicleDetails->make}} Front Windscreen" data-image="{{asset('images/windscreen.jpg')}}" data-target="#image-gallery">
                                                                @if($vehicleDetails->left == null)
                                                                <img src="{{asset('images/notAvailable.png')}}" width="100%" height="auto" class="gridimg" title="Invoice" alt="Invoice">

                                                                @else 
                                                                <img src="{{Storage::disk('s3')->url($vehicleDetails->left)}}" width="100%" height="auto" class="gridimg" title="Invoice" alt="Invoice">

                                                                @endif
                                                                
                                                            </a>
                                                     </div>

                                                       
                                                  

                                                   </div>
                                                 </div>
                                                </div>
                                                <h3>After</h3>
                                                <div class="kt-invoice-v2__header-left">
												    <div class="kt-invoice-v2__logo thumb">
                                                            <div class="row">
                                                                <div class="col-lg-3">

                                                                        @if($incidentPhotos->front == null)
                                                                        <span class="kt-switch kt-switch--outline kt-switch--icon kt-switch--metal" style="margin:35px">
                                                                                <label>
                                                                             <div id="uploadFrontButton" class="file btn btn-primary">
                                                                            <form action="{{Route('glassIncidentPhotoFront')}}" method="POST" accept="application/pdf , image/jpeg , image/bmp , image/png" enctype="multipart/form-data"  >

                                                                                {{csrf_field()}}
                                                                                <i class="la la-upload"></i>Upload Incident Photo Front
                                                                          
                                                                                <input type="hidden" name="claimId" value="{{$glass_claims->id}}" />
                
                                                                                <input id="incidentFrontImage" type="file" name="incidentFront" onchange="this.form.submit()" accept=".jpg,.jpeg,.png"  required/>
                                                                            </form>

                                                                             </div>

                                                                             @else
                                                                             <img src="{{Storage::disk('s3')->url($incidentPhotos->front)}}" width="100%" height="auto" class="gridimg" title="Front Image not found" alt="Invoice">
                                                                             @endif

                                                                            </div>


                                                                    <div class="col-lg-3">

                                                                        @if($incidentPhotos->back == null)
                                                                        <form action="{{Route('glassIncidentPhotoBack')}}" method="POST" accept="application/pdf , image/jpeg , image/bmp , image/png" enctype="multipart/form-data"  >

                                                                        <span class="kt-switch kt-switch--outline kt-switch--icon kt-switch--metal" style="margin:35px">
                                                                                <label>
                                                                             <div id="uploadBackButton" class="file btn btn-primary">
                                                                                {{csrf_field()}}
                                                                                <i class="la la-upload"></i>Upload Incident Photo Back
                                                                          
                                                                                <input type="hidden" name="claimId" value="{{$glass_claims->id}}" />
                
                                                                                <input id="incidentFrontImage" type="file" name="incidentBack" onchange="this.form.submit()" accept=".jpg,.jpeg,.png"  required/>
                                                                            </div>
                                                                        </form>

                                                                            @else 
                                                                            <img src="{{Storage::disk('s3')->url($incidentPhotos->back)}}" width="100%" height="auto" class="gridimg" title="Back incident image" alt="Back Image not found">

                                                                            @endif

                                                                            </div>




                                                                <div class="col-lg-3">
                                                                        @if($incidentPhotos->right == null)

                                                                        <form  action="{{Route('glassIncidentPhotoRight')}}" method="POST" accept="application/pdf , image/jpeg , image/bmp , image/png" enctype="multipart/form-data"  >

                                                                        <span class="kt-switch kt-switch--outline kt-switch--icon kt-switch--metal" style="margin:35px">
                                                                                <label>
                                                                             <div id="uploadFrontButton" class="file btn btn-primary">
                                                                                {{csrf_field()}}
                                                                                <i class="la la-upload"></i>Upload Incident Photo Right
                                                                          
                                                                                <input type="hidden" name="claimId" value="{{$glass_claims->id}}" />
                
                                                                                <input id="incidentFrontImage" type="file" name="incidentRight" onchange="this.form.submit()" accept=".jpg,.jpeg,.png"  required/>
                                                                            </div>

                                                                             </form>

                                                                             @else 
                                                                             <img src="{{Storage::disk('s3')->url($incidentPhotos->right)}}" width="100%" height="auto" class="gridimg" title="Incident Right" alt="Right incident photo not found">
                                                                             @endif
                                                                            </div>


                                                                <div class="col-lg-3">

                                                                    @if($incidentPhotos->left == null)
                                                                        <form  action="{{Route('glassIncidentPhotoLeft')}}" method="POST" accept="application/pdf , image/jpeg , image/bmp , image/png" enctype="multipart/form-data"  >

                                                                        <span class="kt-switch kt-switch--outline kt-switch--icon kt-switch--metal" style="margin:35px">
                                                                                <label>
                                                                             <div id="uploadFrontButton" class="file btn btn-primary">
                                                                                {{csrf_field()}}
                                                                                <i class="la la-upload"></i>Upload Incident Photo Left
                                                                          
                                                                                <input type="hidden" name="claimId" value="{{$glass_claims->id}}" />
                
                                                                                <input id="incidentFrontImage" type="file" name="incidentLeft" onchange="this.form.submit()" accept=".jpg,.jpeg,.png"  required/>
                                                                            </div>

                                                                             </form>

                                                                             @else 
                                                                             <img src="{{Storage::disk('s3')->url($incidentPhotos->left)}}" width="100%" height="auto" class="gridimg" title="Incident Right" alt="Left incident photo not found">

                                                                             @endif
                                                                            </div>
                                                                             
                                                                         </div>
                                                                          
                                                                </div>

                                                            
                                                            </div>

                                                          
                                                     
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <!--end::Portlet-->
                                </div>
                            </div>
                        </div>
                        <!-- end:: Content --> 
   


                                                @if($incidentPhotos->front != null && $incidentPhotos->back != null && $incidentPhotos->left != null && $incidentPhotos->right != null)
                                               
                                                <button id="authorizeClaim" type="button"  onclick="authorizeClaim()" class="btn btn-success btn-lg" >Authorize Claim</button>

                                                @else
                                                <button id="authorizeClaim" type="button"  onclick="authorizeClaim()" class="btn btn-success btn-lg" disabled>Authorize Claim</button>

                                                @endif

                                                @if($incidentPhotos->front != null && $incidentPhotos->back != null && $incidentPhotos->left != null && $incidentPhotos->right != null)
                                               
                                                <button id="denyClaim" type="button" onclick="denyClaim()"  class="btn btn-danger btn-lg">Deny Claim</button>

                                                @else
                                                <button id="denyClaim" type="button" onclick="denyClaim()"  class="btn btn-danger btn-lg" disabled>Deny Claim</button>

                                                @endif
                                              
                                                </div>
                                                </div>

                                            <!--end: Form Wizard Step 2-->


       


                                            <!--begin: Form Wizard Step 3-->

                                            <form class="kt-form" id="step3" >
                                            <div class="kt-wizard-v3__content" data-ktwizard-type="step-content" style="width:80%;margin-top:0px">
                                                <div class="kt-heading kt-heading--md">Step 3</div>
                                                <div class="kt-separator kt-separator--height-xs"></div>
                                                <div class="kt-form__section kt-form__section--first">
                                                
                                <div class="col-lg-12 col-xl-12 order-lg-12 order-xl-12">
                                    <!--begin::Portlet-->
                                    <div class="kt-portlet ">
                                        <div class="kt-portlet__head">
                                            <div class="kt-portlet__head-label">
                                                <h3 class="kt-portlet__head-title">
                                                    Glass Suppliers ({{$suppliersCount}})
                                                </h3>
                                            </div>
                                            <div class="kt-portlet__head-toolbar">
{{--                                             <div class="kt-portlet__head-actions"> <a href="{{Route('sendToAll',['licensePlate'=>$vehicleDetails->vehiclePlate])}}" class="btn btn-default btn-sm btn-bold btn-upper">Send To All</a> </div>
 --}}                                            </div>
                                        
                                            </div>
                                        </div>

                                        <div class="kt-portlet__body">
                                            <div class="kt-widget-6">
                                                <!-- begin::Tab Content -->
                                                <div class="kt-widget6__tab tab-content">
                                                    <div id="kt_personal_income_quater_15c7a028db607c" class="tab-pane fade active show">
                                                        <div class="kt-widget-6__items">
                                                         
                                                     
                                                            @foreach ($suppliers as $supply )
                                                                
                                                            <div class="kt-widget-6__item">
                                                                <div class="kt-widget-6__item-pic">
                                                                    <img class="" src="{{asset(''.$supply->image.'')}}" alt="" />
                                                                </div>
                                                                <div class="kt-widget-6__item-info">
                                                                    <div class="kt-widget-6__item-title">{{$supply->supplierName}}</div>
                                                                    <div class="kt-widget-6__item-desc">Supplier Email: {{$supply->email}} </div>
                                                                    <div class="kt-widget-6__item-desc">Supplier Telephone: {{$supply->telephone}} </div>
                                                                </div>
                                                                   <div class="kt-widget-6__item-icon kt-widget-6__item-icon--brand">
                                                                    <div class="dropdown dropdown-inline">
                                                                        <button type="button" class="btn btn-clean btn-sm btn-icon btn-icon-md" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"> <i class="flaticon-more-1"></i> </button>
                                                                        <div class="dropdown-menu dropdown-menu-right">
                                                                            <ul class="kt-nav">
                                                                                <li class="kt-nav__section kt-nav__section--first"> <span class="kt-nav__section-text">AGENT TOOLS</span> </li>
                                                                                <li class="kt-nav__item">
                                                                                    <a href="{{Route('sendQuote',['vehiclePlate'=>$vehicleDetails->vehiclePlate, 'supplierEmail'=>$supply->email , 'claimId'=>$glass_claims->id])}}" class="kt-nav__link"> <i class="kt-nav__link-icon la la-send"></i> <span class="kt-nav__link-text">Send Quote</span> </a>
                                                                                </li>
                                                                                <li class="kt-nav__item">
                                                                                    <a href="#" class="kt-nav__link"> <i class="kt-nav__link-icon la la-phone"></i> <span class="kt-nav__link-text">Call Supplier</span> </a>
                                                                                </li>
                                                                            
                                                                            </ul>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            @endforeach

                                                        </div>
                                                    </div>
                                                </div>
                                                <!-- end::Tab Content -->
                                    
                                            </div>
                                        </div>
                                    </div>
                                    <!--end::Portlet-->
                                </div>
                            </form>
                                            </div>
                                            <!--end: Form Wizard Step 3-->


                                            <!--begin: Form Wizard Step 4-->
                                            <form class="kt-form" id="step4" >

                                            <div class="kt-wizard-v3__content" data-ktwizard-type="step-content" >
                                                <div class="kt-heading kt-heading--md">Step 4</div>
                                                <div class="kt-separator kt-separator--height-xs"></div>
                                                <div class="kt-form__section kt-form__section--first">
                                              
                                              <div class="col-lg-12 col-xl-12 order-lg-1 order-xl-1">
                                    <!--begin::Portlet-->
                                    <div class="kt-portlet kt-portlet--height-fluid">
                                        <div class="kt-portlet__head">
                                            <div class="kt-portlet__head-label">
                                                <h3 class="kt-portlet__head-title">
                                                    Supplier Quotes Totals
                                                </h3>
                                            </div>
                                            <div class="kt-portlet__head-toolbar">
                                                <div class="kt-portlet__head-actions"> <a href="#" class="btn btn-default btn-sm btn-bold btn-upper">Supplier</a> </div>
                                            </div>
                                        </div>
                                        <div class="kt-portlet__body">
                                            <div class="kt-widget-4">
                                  

                                                @foreach ($supplierQuotes as $supply )
                                                    
                                                     <div class="kt-widget-4__item">
                                                    <div class="kt-widget-4__item-content">
                                                        <div class="kt-widget-4__item-section">
                                                            <div class="kt-widget-4__item-pic">
                                                                <img class="" src="{{asset(''.$supply->image.'')}}" alt="" />
                                                            </div>
                                                            <div class="kt-widget-4__item-info">
                                                                <a href="#" class="kt-widget-4__item-username">{{$supply->supplierName}}</a> 
                                                                <div class="kt-widget-4__item-desc">{{$supply->telephone}}</div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="kt-widget-4__item-content">
                                                        <div class="kt-widget-4__item-price"> <span class="kt-widget-4__item-badge">P</span> <span class="kt-widget-4__item-number">{{number_format($supply->total,2)}}</span> </div>
                                                    
                                                    </div>
                                                           <div class="kt-widget-6__item-icon kt-widget-6__item-icon--brand">
                                                                    <div class="dropdown dropdown-inline">
                                                                        <a href="{{Route('acceptQuote',['vehiclePlate'=>$userDetails->cellphone,'supplierEmail'=>$supply->email])}}"  class="btn btn-clean btn-sm btn-icon btn-icon-md"> <i class="la la-check"></i> </a>

                                                                    </div>
                                                                </div>

                                               
                                                </div>
                                                        

                                                @endforeach

                                            </div>
                                        </div>
                                    </div>
                                    <!--end::Portlet-->
                                                    
                                                    </div>
                                                </div>
                                            </div>
                                            <!--end: Form Wizard Step 4-->
                                            <!--begin: Form Wizard Step 5-->
                                            <div class="kt-wizard-v3__content" data-ktwizard-type="step-content">
                                                <div class="kt-heading kt-heading--md">Step 5</div>
                                                <div class="kt-separator kt-separator--height-xs"></div>
                                                <div class="kt-form__section kt-form__section--first">
                                                    <div class="form-group">
                                                        <label>Membership:</label>
                                                        <div class="row">
                                                            <div class="col-xl-6">
                                                                <label class="kt-option kt-option kt-option--plain">
                                                                    <span class="kt-option__control">
                                                                        <span class="kt-radio kt-radio--check-bold">
                                                                            <input type="radio" name="m_option_1" value="1" checked>
                                                                            <span></span> 
                                                                        </span>
                                                                    </span>
                                                                    <span class="kt-option__label">
                                                                        <span class="kt-option__head"> <span class="kt-option__title"> Premium Partner </span> </span>
                                                                        <span class="kt-option__body"> 30 days free trial and lifetime free updates </span> 
                                                                    </span>
                                                                </label>
                                                            </div>
                                                            <div class="col-xl-6">
                                                                <label class="kt-option kt-option kt-option--plain">
                                                                    <span class="kt-option__control">
                                                                        <span class="kt-radio kt-radio--check-bold">
                                                                            <input type="radio" name="m_option_1" value="1" checked>
                                                                            <span></span> 
                                                                        </span>
                                                                    </span>
                                                                    <span class="kt-option__label">
                                                                        <span class="kt-option__head"> <span class="kt-option__title"> Free Membership </span> </span>
                                                                        <span class="kt-option__body"> 24/7 support and Lifetime access </span> 
                                                                    </span>
                                                                </label>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="kt-separator kt-separator--border-dashed"></div>
                                                    <div class="kt-separator kt-separator--height-md"></div>
                                                    <div class="form-group">
                                                        <label class="kt-checkbox">
                                                            <input type="checkbox" name="promotions" value="1">
                                                            Click here to receive our latest promotions and news <span></span> 
                                                        </label>
                                                    </div>
                                                    <div class="form-group">
                                                        <label class="kt-checkbox">
                                                            <input type="checkbox" name="accept" value="1">
                                                            Click here to indicate that you have read and agree to the terms presented in the Terms and Conditions agreement <span></span> 
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                            <!--end: Form Wizard Step 5-->
                                            <!--begin: Form Wizard Step 6-->
                                            <div class="kt-wizard-v3__content" data-ktwizard-type="step-content">
                                                <div class="kt-heading kt-heading--md">Step 6</div>
                                                <div class="kt-separator kt-separator--height-xs"></div>
                                                <div class="kt-form__section kt-form__section--first">
                                                    <div class="form-group">
                                                        <label>Membership:</label>
                                                        <div class="row">
                                                            <div class="col-xl-6">
                                                                <label class="kt-option kt-option kt-option--plain">
                                                                    <span class="kt-option__control">
                                                                        <span class="kt-radio kt-radio--check-bold">
                                                                            <input type="radio" name="m_option_1" value="1" checked>
                                                                            <span></span> 
                                                                        </span>
                                                                    </span>
                                                                    <span class="kt-option__label">
                                                                        <span class="kt-option__head"> <span class="kt-option__title"> Premium Partner </span> </span>
                                                                        <span class="kt-option__body"> 30 days free trial and lifetime free updates </span> 
                                                                    </span>
                                                                </label>
                                                            </div>
                                                            <div class="col-xl-6">
                                                                <label class="kt-option kt-option kt-option--plain">
                                                                    <span class="kt-option__control">
                                                                        <span class="kt-radio kt-radio--check-bold">
                                                                            <input type="radio" name="m_option_1" value="1" checked>
                                                                            <span></span> 
                                                                        </span>
                                                                    </span>
                                                                    <span class="kt-option__label">
                                                                        <span class="kt-option__head"> <span class="kt-option__title"> Free Membership </span> </span>
                                                                        <span class="kt-option__body"> 24/7 support and Lifetime access </span> 
                                                                    </span>
                                                                </label>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="kt-separator kt-separator--border-dashed"></div>
                                                    <div class="kt-separator kt-separator--height-md"></div>
                                                    <div class="form-group">
                                                        <label class="kt-checkbox">
                                                            <input type="checkbox" name="promotions" value="1">
                                                            Click here to receive our latest promotions and news <span></span> 
                                                        </label>
                                                    </div>
                                                    <div class="form-group">
                                                        <label class="kt-checkbox">
                                                            <input type="checkbox" name="accept" value="1">
                                                            Click here to indicate that you have read and agree to the terms presented in the Terms and Conditions agreement <span></span> 
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                            <!--end: Form Wizard Step 6-->
                                            <!--begin: Form Wizard Step 7-->
                                            <div class="kt-wizard-v3__content" data-ktwizard-type="step-content">
                                                <div class="kt-heading kt-heading--md">Step 7</div>
                                                <div class="kt-separator kt-separator--height-xs"></div>
                                                <div class="kt-form__section kt-form__section--first">
                                                    <div class="form-group">
                                                        <label>Membership:</label>
                                                        <div class="row">
                                                            <div class="col-xl-6">
                                                                <label class="kt-option kt-option kt-option--plain">
                                                                    <span class="kt-option__control">
                                                                        <span class="kt-radio kt-radio--check-bold">
                                                                            <input type="radio" name="m_option_1" value="1" checked>
                                                                            <span></span> 
                                                                        </span>
                                                                    </span>
                                                                    <span class="kt-option__label">
                                                                        <span class="kt-option__head"> <span class="kt-option__title"> Premium Partner </span> </span>
                                                                        <span class="kt-option__body"> 30 days free trial and lifetime free updates </span> 
                                                                    </span>
                                                                </label>
                                                            </div>
                                                            <div class="col-xl-6">
                                                                <label class="kt-option kt-option kt-option--plain">
                                                                    <span class="kt-option__control">
                                                                        <span class="kt-radio kt-radio--check-bold">
                                                                            <input type="radio" name="m_option_1" value="1" checked>
                                                                            <span></span> 
                                                                        </span>
                                                                    </span>
                                                                    <span class="kt-option__label">
                                                                        <span class="kt-option__head"> <span class="kt-option__title"> Free Membership </span> </span>
                                                                        <span class="kt-option__body"> 24/7 support and Lifetime access </span> 
                                                                    </span>
                                                                </label>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="kt-separator kt-separator--border-dashed"></div>
                                                    <div class="kt-separator kt-separator--height-md"></div>
                                                    <div class="form-group">
                                                        <label class="kt-checkbox">
                                                            <input type="checkbox" name="promotions" value="1">
                                                            Click here to receive our latest promotions and news <span></span> 
                                                        </label>
                                                    </div>
                                                    <div class="form-group">
                                                        <label class="kt-checkbox">
                                                            <input type="checkbox" name="accept" value="1">
                                                            Click here to indicate that you have read and agree to the terms presented in the Terms and Conditions agreement <span></span> 
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                            <!--end: Form Wizard Step 7-->
                                            <!--begin: Form Wizard Step 8-->
                                            <div class="kt-wizard-v3__content" data-ktwizard-type="step-content">
                                                <div class="kt-heading kt-heading--md">Step 8</div>
                                                <div class="kt-separator kt-separator--height-xs"></div>
                                                <div class="kt-form__section kt-form__section--first">
                                                    <div class="form-group">
                                                        <label>Membership:</label>
                                                        <div class="row">
                                                            <div class="col-xl-6">
                                                                <label class="kt-option kt-option kt-option--plain">
                                                                    <span class="kt-option__control">
                                                                        <span class="kt-radio kt-radio--check-bold">
                                                                            <input type="radio" name="m_option_1" value="1" checked>
                                                                            <span></span> 
                                                                        </span>
                                                                    </span>
                                                                    <span class="kt-option__label">
                                                                        <span class="kt-option__head"> <span class="kt-option__title"> Premium Partner </span> </span>
                                                                        <span class="kt-option__body"> 30 days free trial and lifetime free updates </span> 
                                                                    </span>
                                                                </label>
                                                            </div>
                                                            <div class="col-xl-6">
                                                                <label class="kt-option kt-option kt-option--plain">
                                                                    <span class="kt-option__control">
                                                                        <span class="kt-radio kt-radio--check-bold">
                                                                            <input type="radio" name="m_option_1" value="1" checked>
                                                                            <span></span> 
                                                                        </span>
                                                                    </span>
                                                                    <span class="kt-option__label">
                                                                        <span class="kt-option__head"> <span class="kt-option__title"> Free Membership </span> </span>
                                                                        <span class="kt-option__body"> 24/7 support and Lifetime access </span> 
                                                                    </span>
                                                                </label>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="kt-separator kt-separator--border-dashed"></div>
                                                    <div class="kt-separator kt-separator--height-md"></div>
                                                    <div class="form-group">
                                                        <label class="kt-checkbox">
                                                            <input type="checkbox" name="promotions" value="1">
                                                            Click here to receive our latest promotions and news <span></span> 
                                                        </label>
                                                    </div>
                                                    <div class="form-group">
                                                        <label class="kt-checkbox">
                                                            <input type="checkbox" name="accept" value="1">
                                                            Click here to indicate that you have read and agree to the terms presented in the Terms and Conditions agreement <span></span> 
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                            <!--end: Form Wizard Step 8-->
                                            <!--begin: Form Actions -->
                                            <div class="kt-form__actions">
                                                <div class="btn btn-outline-brand btn-md btn-tall btn-wide btn-bold btn-upper" data-ktwizard-type="action-prev"> Previous </div>
                                                <div class="btn btn-brand btn-md btn-tall btn-wide btn-bold btn-upper" data-ktwizard-type="action-submit"> Submit </div>
                                                <div class="btn btn-brand btn-md btn-tall btn-wide btn-bold btn-upper" data-ktwizard-type="action-next"> Next Step </div>
                                            </div>
                                            <!--end: Form Actions -->
                                        </form>
                                        <!--end: Form Wizard Form-->
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- end:: Content -->
                    </div>
                    <!-- begin:: Footer -->
                    <div class="kt-footer kt-grid__item kt-grid kt-grid--desktop kt-grid--ver-desktop">
                        <div class="kt-footer__copyright"> 2018&nbsp;&copy;&nbsp;<a href="#" target="_blank" class="kt-link">Alpha Direct</a> </div>
                        <div class="kt-footer__menu"> <a href="#" target="_blank" class="kt-footer__menu-link kt-link">About</a> <a href="#" target="_blank" class="kt-footer__menu-link kt-link">Team</a> <a href="#" target="_blank" class="kt-footer__menu-link kt-link">Contact</a> </div>
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
                    </div>
                </div>
            </div>
        </div>
        <!-- end:: Quick Panel -->
        <!-- begin:: Scrolltop -->
        <div id="kt_scrolltop" class="kt-scrolltop"> <i class="la la-arrow-up"></i>
         </div>
        <!-- end:: Scrolltop -->
         
                
                
                
        <!-- begin::Global Config(global config for global JS sciprts) -->
        <script>
            let KTAppOptions = {

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
        </script>
        <!-- end::Global Config -->



     @include('Agents.Layout.scripts')
     <script src="{{asset('css/app/custom/general/components/keen-wizard/wizard-demo-v3.min.js')}}" type="text/javascript"></script>
     <script src="https://cdn.jsdelivr.net/jquery.validation/1.16.0/additional-methods.min.js"></script>
     <script src="https://cdn.jsdelivr.net/jquery.validation/1.16.0/jquery.validate.min.js"></script>



     <script type="text/javascript">
     /*    function enableDisableSend(oFld) {
            console.log('printin');
            causeOfDamage = oFld.form.causeOfDamage.value;

            if (causeOfDamage == '') {
                oFld.form.write.disabled = TRUE;
                document.getElementById("submitForm").className = 'comment_popup_button_disabled';
            } else {
                oFld.form.write.disabled = FALSE;
                document.getElementById("submitForm").className = 'comment_popup_button_active';
            }
        } */


      

        $(document).ready(function(){

            $( "#kt_form" ).validate({
                rules: {
                    field: {
                    required: true,
                    step: 10
                    },
                    omang: {
                    require_from_group: [1, ".id-type"]
                    },
                    passport: {
                    require_from_group: [1, ".id-type"]
                    },
                  }
                });
            

                document.getElementById("submitForm").addEventListener("click", function () {
          
          document.getElementById("kt_form").submit(); 
           
         });


                     $('.loading').css("display", "none");


                    $('#frontViewModal').on('click', function(){

                    $('#image-gallery-image').attr('src','data:image/;base64,'+ "{{$vehicleDetails->front}}");

                    });

                    $('#backViewModal').on('click', function(){

                    $('#image-gallery-image').attr('src','data:image/;base64,'+ "{{$vehicleDetails->back}}");

                    });

                    $('#rightViewModal').on('click', function(){

                    $('#image-gallery-image').attr('src','data:image/;base64,'+ "{{$vehicleDetails->right}}");

                    });

                    $('#leftViewModal').on('click', function(){

                    $('#image-gallery-image').attr('src','data:image/;base64,'+ "{{$vehicleDetails->left}}");

                    });
                });



                /*$("#submitForm").on("click",function(){
                    $("#kt_form").submit();
                });
*/

        
         
        function authorizeClaim() {

            var CSRF_TOKEN = $('meta[name="csrf-token"]').attr('content');

            $.ajax({
            type:'POST',
            url:'{{Route('authorizeClaim')}}',
            data:{_token: CSRF_TOKEN,claimAuthorization:"authorized",claim_id:"{{$glass_claims->id}}",customerCell:"{{$userDetails->cellphone}}"},
            success:function(data) {
                console.log('Completed');

                location.reload(); 
               }
              });
            }

        function denyClaim() {

            var CSRF_TOKEN = $('meta[name="csrf-token"]').attr('content');

            $.ajax({
            type:'POST',
            url:'{{Route('authorizeClaim')}}',
            data:{_token: CSRF_TOKEN,claimAuthorization:"authorized",claim_id:"{{$glass_claims->id}}",customerCell:"{{$userDetails->cellphone}}"},
            success:function(data) {
                console.log('Completed');

                location.reload(); 
               }
              });
            }

            $("#authorizeClaim").on("click", function(){

                $("#authorizeClaim").prop("disabled",true);
                $('.loading').css("display","block");


            });

        //console.log($(".kt-wizard-v3__nav-items").find(".kt-wizard-v3__nav-item"));
  
        @if(Session::has('submittedClaim'))
        Toastify({
            text: "{{ Session::get('submittedClaim') }}",
            duration: 4000,
            newWindow: true,
            gravity: "top", // `top` or `bottom`
            positionRight: true, // `true` or `false`
            backgroundColor: "#00C851",
            }).showToast();   
        @endif

        @if(Session::has('claimSuccess'))
        Toastify({
            text: "{{ Session::get('claimSuccess') }}",
            duration: 4000,
            newWindow: true,
            gravity: "top", // `top` or `bottom`
            positionRight: true, // `true` or `false`
            backgroundColor: "#00C851",
            }).showToast();   
        @endif
  
        @if(Session::has('quoteSent'))
        Toastify({
            text: "{{ Session::get('quoteSent') }}",
            duration: 4000,
            newWindow: true,
            gravity: "top", // `top` or `bottom`
            positionRight: true, // `true` or `false`
            backgroundColor: "#00C851",
            }).showToast();   
        @endif

        @if($glass_claims->claimStep == 1)
          

            $( document ).ready(function() {
                $($('#kt_form').find(".kt-wizard-v3__content")[0]).attr("data-ktwizard-state", "current");
                $($(".kt-wizard-v3__nav-items")
                .find(".kt-wizard-v3__nav-item")[0]).attr("data-ktwizard-state", "current");
                $($(".kt-wizard-v3__nav-items")
                .find(".kt-wizard-v3__nav-item")[1]).attr("data-ktwizard-state", "pending");
                $($(".kt-wizard-v3__nav-items")
                .find(".kt-wizard-v3__nav-item")[2]).attr("data-ktwizard-state", "pending");
            });
        @elseif($glass_claims->claimStep == 2)
        $( document ).ready(function() {
                $('#kt_form').hide();
                $($('#step2').find(".kt-wizard-v3__content")[0]).attr("data-ktwizard-state", "current");
                $($(".kt-wizard-v3__nav-items")
                .find(".kt-wizard-v3__nav-item")[0]).attr("data-ktwizard-state", "done");
                $($(".kt-wizard-v3__nav-items")
                .find(".kt-wizard-v3__nav-item")[1]).attr("data-ktwizard-state", "current");
                $($(".kt-wizard-v3__nav-items")
                .find(".kt-wizard-v3__nav-item")[2]).attr("data-ktwizard-state", "pending");
            });
        @elseif($glass_claims->claimStep == 3)
            $( document ).ready(function() {
                $('#kt_form').hide();
                $('#step2').hide();
                $($('#step3').find(".kt-wizard-v3__content")[0]).attr("data-ktwizard-state", "current");
                $($(".kt-wizard-v3__nav-items")
                .find(".kt-wizard-v3__nav-item")[0]).attr("data-ktwizard-state", "done");
                $($(".kt-wizard-v3__nav-items")
                .find(".kt-wizard-v3__nav-item")[1]).attr("data-ktwizard-state", "done");
                $($(".kt-wizard-v3__nav-items")
                .find(".kt-wizard-v3__nav-item")[2]).attr("data-ktwizard-state", "current");
            });
        @elseif($glass_claims->claimStep == 4)
            $( document ).ready(function() {
                $('#kt_form').hide();
                $('#step2').hide();
                $($('#step4').find(".kt-wizard-v3__content")[0]).attr("data-ktwizard-state", "current");
                $($(".kt-wizard-v3__nav-items")
                .find(".kt-wizard-v3__nav-item")[0]).attr("data-ktwizard-state", "done");
                $($(".kt-wizard-v3__nav-items")
                .find(".kt-wizard-v3__nav-item")[1]).attr("data-ktwizard-state", "done");
                $($(".kt-wizard-v3__nav-items")
                .find(".kt-wizard-v3__nav-item")[2]).attr("data-ktwizard-state", "done");
                $($(".kt-wizard-v3__nav-items")
                .find(".kt-wizard-v3__nav-item")[3]).attr("data-ktwizard-state", "current");
            });
        @endif
    </script>

     


    </body>
    <!-- end::Body -->
</html>