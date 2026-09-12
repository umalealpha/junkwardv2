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
                            <div class="kt-portlet">
                                <div class="kt-portlet__body kt-portlet__body--fit">
                                    <div class="kt-wizard-v3" id="kt_wizard_v3" data-ktwizard-state="step-first">
                                        <!--begin: Form Wizard Nav -->
                                        <div class="kt-wizard-v3__nav">
                                            <div class="kt-wizard-v3__nav-line"></div>
                                            <div class="kt-wizard-v3__nav-items">
                                                <a class="kt-wizard-v3__nav-item" href="#" data-ktwizard-type="step" data-ktwizard-state="current">
                                                    <span>1</span> <i class="fa fa-check"></i> 
                                                    <div class="kt-wizard-v3__nav-label">Glass Claim from</div>
                                                </a>
                                                <a class="kt-wizard-v3__nav-item" href="#" data-ktwizard-type="step">
                                                    <span>2</span> <i class="fa fa-check"></i> 
                                                    <div class="kt-wizard-v3__nav-label">Agent Authorization</div>
                                                </a>
                                       
                                          
                                                <a class="kt-wizard-v3__nav-item" href="#" data-ktwizard-type="step">
                                                    <span>3</span> <i class="fa fa-check"></i> 
                                                    <div class="kt-wizard-v3__nav-label">Select Supplier</div>
                                                </a>
                                                <a class="kt-wizard-v3__nav-item" href="#" data-ktwizard-type="step">
                                                    <span>4</span> <i class="fa fa-check"></i> 
                                                    <div class="kt-wizard-v3__nav-label">Client & Supplier Informed</div>
                                                </a>
                                                <a class="kt-wizard-v3__nav-item" href="#" data-ktwizard-type="step">
                                                    <span>5</span> <i class="fa fa-check"></i> 
                                                    <div class="kt-wizard-v3__nav-label">Recieved Invoice</div>
                                                </a>
                                                <a class="kt-wizard-v3__nav-item" href="#" data-ktwizard-type="step">
                                                    <span>6</span> <i class="fa fa-check"></i> 
                                                    <div class="kt-wizard-v3__nav-label">Accounting Dpt. Transfer</div>
                                                </a>
                                            </div>
                                        </div>
                                        <!--end: Form Wizard Nav -->
                                        <!--begin: Form Wizard Form-->
                                        <form class="kt-form" id="kt_form" method="POST">
                                            <!--begin: Form Wizard Step 1-->
                                            <div class="kt-wizard-v3__content" data-ktwizard-type="step-content" data-ktwizard-state="current">
                                               <h3 class="kt-section__title">Glass Claim from</h3>
                                                <div class="kt-heading kt-heading--md">INSURED</div>
                                                <div class="kt-separator kt-separator--height-xs"></div>
                                                <div class="kt-form__section kt-form__section--first">
                                                  <div class="row">
                                                   <div class="col-lg-6">
                                                    <div class="form-group">
                                                        <label>Policy No.</label>
                                                        <input type="text" class="form-control" name="Policy" placeholder="Policy No" value="MIS-2019-{{$policyDetails->policyNumber}}" disabled> 
                                                    </div>
                                                    </div>
														<div class="col-lg-6">
															<div class="form-group">
																<label>Name of Insured:</label>
																<input type="text" class="form-control" name="customerName" placeholder="Name of Insured" value="{{$userDetails->firstName}} {{$userDetails->lastName}}" disabled> 
															</div>
														</div>
                                                    </div>
                                                  <div class="row">
                                                    	<div class="col-lg-6">
														<div class="kt-repeater">
                                                            <div data-repeater-list="demo1">
                                                               <label>Address</label>
                                                                <div data-repeater-item class="kt-repeater__item">
                                                                    <div class="input-group">
                                                                        <input type="text" class="form-control" placeholder="Address">
                                                                        <div class="input-group-append">
                                                                            <button type="button" data-repeater-delete="" class="btn btn-danger btn-icon"> <i class="la la-close kt-font-light"></i> </button>
                                                                        </div>
                                                                    </div>
                                                                    <div class="kt-separator kt-separator--space-sm"></div>
                                                                </div>
                                                            </div>
                                                            <div class="kt-repeater__add-data">
                                                                <span data-repeater-create="" class="btn btn-info btn-sm"> <i class="la la-plus"></i></span>
                                                            </div>
                                                        </div>

                                                    </div>
                                                   		<div class="col-lg-6">
															<div class="form-group">
																<label>Occupation</label>
																<input type="text" class="form-control" name="password" placeholder="Occupation" value="Occupation"> 
															</div>
														</div>
                                                    </div>
                                                  <div class="kt-separator kt-separator--border-dashed kt-separator--space-lg"></div>
                                                  <div class="kt-heading kt-heading--md">VEHICLE</div>
                                                  <div class="row">
                                                    	<div class="col-lg-6">
															<div class="form-group">
															<label>Registration No.</label>
															<input type="text" class="form-control" name="password" placeholder="Enter Registration No." value="{{$vehicleDetails->vehiclePlate}}" disabled> 
															</div>
                                                    	</div>
                                                    	<div class="col-lg-6">
                                                        <div class="form-group">
                                                    <label>Make, year and Model</label>
                                                    <input type="text" class="form-control" name="vehicleDescription" placeholder="Enter Registration No." value="{{$vehicleDetails->make}} {{$vehicleDetails->model}} {{$vehicleDetails->year}}" disabled> 

                                                 
                                                </div>
                                                    	</div>
                                                    </div>
                                                  <div class="row">
                                                    	<div class="col-lg-6">
                                                        <div class="form-group">
                                                    <label>Type of Glass</label>
                                                    <div></div>
                                                    <select class="custom-select form-control" name="glassType" disabled>
                                                        <option selected value="{{$glass_claims->glassType}}">{{$glass_claims->glassType}}</option>
                                                        <option value="Door Window">Door Window</option>
                                                        <option value="Back Window">Back Window</option>
                                                        <option value="Mirrors">Mirrors</option>
                                                    </select>
                                                </div>
                                                    	</div>
                                                         	<div class="col-lg-6">
															<div class="form-group">
															<label>Chassis No</label>
															<input type="text" class="form-control" name="password" placeholder="Chassis No" value="{{$vehicleDetails->chassisNo}}" disabled> 
															</div>
                                                    	</div>
                                                    </div>
                                                  <div class="row">
                                                   
                                                   	<div class="col-lg-6">
													<div class="form-group">
                                                    <label>Purpose of use</label>
                                                    <div class="kt-radio-inline">
                                                     @if($vehicleDetails->purpose == 'Business')

                                                        <label class="kt-radio">
                                                            <input type="radio" name="purpose" checked disabled>
                                                            Business <span></span> 
                                                        </label>
                                                         <label class="kt-radio">
                                                            <input type="radio" name="purpose" disabled>
                                                            Private <span></span> 
                                                        </label>
                                                        @else
                                                           <label class="kt-radio">
                                                            <input type="radio" name="purpose" disabled>
                                                            Business <span></span> 
                                                        </label>
                                                         <label class="kt-radio">
                                                            <input type="radio" name="purpose" checked disabled>
                                                            Private <span></span> 
                                                        </label>
                                                       
                                                        @endif
                                                    </div>
                                                </div>
                                                    	</div>
                                                    </div>
                                                  <div class="kt-separator kt-separator--border-dashed kt-separator--space-lg"></div>
                                                  <div class="kt-heading kt-heading--md">DAMAGE</div>
                                                  <div class="row">
                                                    <div class="col-lg-6">
                                                        <div class="form-group">
														<label>Date of Damage</label>
														<input class="form-control" type="text" value="{{$glass_claims->incidentDate}}" id="example-date-input" disabled>
                                                		</div>
                                                    </div>
                                                    <div class="col-lg-6">
                                                    <div class="form-group">
															<label>Glass Type</label>
															<div class="kt-radio-inline">
																<label class="kt-radio">
                                                                @if($glass_claims->damageExtent == 'Shattered')
																	<input type="radio" name="radio2" value="{{$glass_claims->damageExtent}}"  disabled>
																	Cracked <span></span> 
																</label>
                                                                	<label class="kt-radio">
																	<input type="radio" name="radio2" checked disabled>
																	Shattered <span></span> 
																</label>
                                                                @else
                                                                    <input type="radio" name="radio2" value="{{$glass_claims->damageExtent}}" checked disabled>
																	Cracked <span></span> 
																</label>
                                                                	<label class="kt-radio">
																	<input type="radio" name="radio2"  disabled>
																	Shattered <span></span> 
																</label>
															
                                                                @endif
															</div>
															</div>
						
                                                    </div>
                                                    </div>
                                                  <div class="row">
                                                    	<div class="col-lg-6">
															<div class="form-group">
															<label>Cause of Damage</label>
															<input type="text" class="form-control" name="password" placeholder="Cause of Damage" value="Cause of Damage"> 
															</div>
                                                    	</div>

                                                    	<div class="col-lg-6">
															<div class="form-group">
															<div class="kt-radio-inline">
                                                            	<label>Was he in any way employed by the Insured?</label>

																<label class="kt-radio">
                                                                @if($glass_claims->damageExtent == 'Shattered')
																	<input type="radio" name="" value="{{$glass_claims->damageExtent}}"  disabled>
																	Yes <span></span> 
																</label>
                                                                	<label class="kt-radio">
																	<input type="radio" name="" checked disabled>
																	No <span></span> 
																</label>
                                                                @else
                                                                    <input type="radio" name="" value="{{$glass_claims->damageExtent}}" checked disabled>
																	Yes <span></span> 
																</label>
                                                                	<label class="kt-radio">
																	<input type="radio" name=""  disabled>
																	No <span></span> 
																</label>
															
                                                                @endif
															</div>
                                                            
                                                            </div>
                                                    	</div>
                                                   		

                                                  </div>
                                                  <div class="row">
                                                 

                                                    	<div class="col-lg-12">
															<div class="form-group">
															<div class="kt-radio-inline">
                                                            	<label>Third party involved?</label>

																<label class="kt-radio">
																	<input type="radio" name="thirdParty" id="openThird" >
																	Yes <span></span> 
																</label>
                                                                	<label class="kt-radio">
																	<input type="radio" name="thirdParty" id="closeThird">
																	No <span></span> 
																</label>
                                                      
															</div>
                                                            
                                                            </div>
                                                    	</div>
                                                   		

                                                  </div>
                                                  <div class="row" id="thirdParty" >
                                                    	
                                                    	<div class="col-lg-6">
															<div class="kt-repeater">
                                                            <div data-repeater-list="demo1">
                                                               <label>Name</label>
                                                                <div data-repeater-item class="kt-repeater__item">
                                                                    <div class="input-group">
                                                                        <input type="text" class="form-control" placeholder="Name and address">
                                                                        <div class="input-group-append">
                                                                            <button data-repeater-delete="" class="btn btn-danger btn-icon"> <i class="la la-close kt-font-light"></i> </button>
                                                                        </div>
                                                                    </div>
                                                                    <div class="kt-separator kt-separator--space-sm"></div>
                                                                </div>

                                                                
                                                            </div>
                                                          
                                                        </div>
                                                    	</div>
                                                    	<div class="col-lg-6">
															<div class="kt-repeater">
                                                            <div data-repeater-list="demo1">
                                                               <label>Address</label>
                                                                <div data-repeater-item class="kt-repeater__item">
                                                                    <div class="input-group">
                                                                        <input type="text" class="form-control" placeholder="Name and address">
                                                                        <div class="input-group-append">
                                                                            <button data-repeater-delete="" class="btn btn-danger btn-icon"> <i class="la la-close kt-font-light"></i> </button>
                                                                        </div>
                                                                    </div>
                                                                    <div class="kt-separator kt-separator--space-sm"></div>
                                                                </div>

                                                                
                                                            </div>
                                                       
                                                        </div>
                                                    	</div>
                                                    </div>
                                                  <div class="kt-separator kt-separator--border-dashed kt-separator--space-lg"></div>
                                                  <div class="kt-heading kt-heading--md">NON MOTOR</div>
                                                  <div class="row">
                                                    <div class="col-lg-6">
                                                        <div class="form-group">
                                                        <label>Address where glass is situated</label>
                                                        <input type="text" class="form-control" name="Policy" placeholder="Please state the precise position of the glass" value="Address where glass">
                                                		</div>
                                                    </div>
                                                    <div class="col-lg-6">
                                                        <div class="form-group">
                                                        <label>Size of the plate broken</label>
                                                        <input type="text" class="form-control" name="Policy" placeholder="Size of the plate broken" value="Size of the plate broken">
                                                		</div>
                                                    </div>
                                                    </div>
                                                  
                                                   <div class="row">
                                                    <div class="col-lg-6">
														<div class="form-group">
														<label>File Browser</label>
														<div></div>
														<div class="custom-file">
															<input type="file" class="custom-file-input" id="customFile">
															<label class="custom-file-label" for="customFile">Choose file</label>
														</div>
														</div>
													</div>
                                                    </div>
                                                  <div class="kt-separator kt-separator--border-dashed kt-separator--space-lg"></div>
                                                  <div class="row">
                                                    <div class="col-lg-12">
													<div class="form-group">
                                                        <label class="kt-checkbox">
                                                            <input type="checkbox" name="accept" value="1">
                                                            I hereby declare that the foregoing statements are made by myself and are true in all respects and that I have not attempted to conceal from the Company anything with which it ought to be made acquainted.<span></span> 
                                                        </label>
                                                    </div>
                                                    </div>
                                                    </div>
                                                    
                                                  <div class="kt-separator kt-separator--border-dashed kt-separator--space-lg"></div>
                                                  <div class="row">
                                                  	<div class="col-lg-8 newsearch1">
													<div class="form-group ">
                                                    <label>Right Icon Input</label>
													<div class="dropdown bootstrap-select show-tick form-control dropsearch">
                                                   <select class="form-control" id="kt_bootstrap_select" multiple="" name="select" tabindex="-98">
                                                    <optgroup label="Picnic" data-max-options="2">
                                                        <option>Mustard</option>
                                                        <option>Ketchup</option>
                                                        <option>Relish</option>
                                                    </optgroup>
                                                    <optgroup label="Camping" data-max-options="2">
                                                        <option>Tent</option>
                                                        <option>Flashlight</option>
                                                        <option>Toilet Paper</option>
                                                    </optgroup>
                                                </select><button type="button" class="btn dropdown-toggle bs-placeholder btn-light" data-toggle="dropdown" role="button" data-id="kt_bootstrap_select" title="Nothing selected" aria-expanded="false"><div class="filter-option"><div class="filter-option-inner"><div class="filter-option-inner-inner">Nothing selected</div></div> </div></button><div class="dropdown-menu" role="combobox" x-placement="bottom-start" style="max-height: 285.438px; overflow: hidden; min-height: 144px; min-width: 316px; position: absolute; will-change: transform; top: 0px; left: 0px; transform: translate3d(0px, 38px, 0px);"><div class="inner show" role="listbox" aria-expanded="false" tabindex="-1" style="max-height: 259.438px; overflow-y: auto; min-height: 118px;"><ul class="dropdown-menu inner show"><li class="dropdown-header optgroup-1"><span class="text">Picnic</span></li><li class="optgroup-1"><a role="option" class="opt dropdown-item" aria-disabled="false" tabindex="0" aria-selected="false"><span class=" bs-ok-default check-mark"></span><span class="text">Mustard</span></a></li><li class="optgroup-1"><a role="option" class="opt dropdown-item" aria-disabled="false" tabindex="0" aria-selected="false"><span class=" bs-ok-default check-mark"></span><span class="text">Ketchup</span></a></li><li class="optgroup-1"><a role="option" class="opt dropdown-item" aria-disabled="false" tabindex="0" aria-selected="false"><span class=" bs-ok-default check-mark"></span><span class="text">Relish</span></a></li><li class="dropdown-divider optgroup-2div"></li><li class="dropdown-header optgroup-2"><span class="text">Camping</span></li><li class="optgroup-2"><a role="option" class="opt dropdown-item" aria-disabled="false" tabindex="0" aria-selected="false"><span class=" bs-ok-default check-mark"></span><span class="text">Tent</span></a></li><li class="optgroup-2"><a role="option" class="opt dropdown-item" aria-disabled="false" tabindex="0" aria-selected="false"><span class=" bs-ok-default check-mark"></span><span class="text">Flashlight</span></a></li><li class="optgroup-2"><a role="option" class="opt dropdown-item" aria-disabled="false" tabindex="0" aria-selected="false"><span class=" bs-ok-default check-mark"></span><span class="text">Toilet Paper</span></a></li></ul></div></div></div>
                                                    <div class="input-group-append">
                                                        <span class="input-group-text newsearch"><i class="la la-search"></i></span>
                                                    </div>
                                                	</div>

                                                  </div>
                                                  </div>
                                                </div>
                                            </div>
                                            <!--end: Form Wizard Step 1-->


                                            <!--begin: Form Wizard Step 2-->
                                            <div class="kt-wizard-v3__content" data-ktwizard-type="step-content">
                                                <div class="kt-heading kt-heading--md">Step 2</div>
                                                <div class="kt-separator kt-separator--height-xs"></div>
                                                <div class="kt-form__section kt-form__section--first">
                                               
                                          
                                                <div class="row">
                                                        <div class="col-xl-12">
                                                        <h3>Car State Before</h3>
                                                        <div class="col-xl-3">
                                                        <img src="{{asset('images/body4.jpg')}}" height="250px" alt="Image Not found">
                                                        </div>
                                                        <div class="col-xl-3">
                                                        <img src="{{asset('images/body4.jpg')}}" height="250px" alt="Image Not found">
                                                        </div>

                                                        <button id="authorizeClaim" class="btn btn-success" onclick="document.getElementById('claimForm').submit();"> Authorize Claim </button>
                        
    
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <!--end: Form Wizard Step 2-->
                                            <!--begin: Form Wizard Step 3-->
                                            <div class="kt-wizard-v3__content" data-ktwizard-type="step-content">
                                                <div class="kt-heading kt-heading--md">Step 3</div>
                                                <div class="kt-separator kt-separator--height-xs"></div>
                                                <div class="kt-form__section kt-form__section--first">
                                                    <div class="row">
                                                        <div class="col-xl-6">
                                                            <div class="form-group">
                                                                <label>Company Name:</label>
                                                                <input type="text" class="form-control" name="company_name" placeholder="Enter company name" value="Google Inc.">
                                                                <span class="form-text text-muted">Please enter your company name</span> 
                                                            </div>
                                                        </div>
                                                        <div class="col-xl-6">
                                                            <div class="form-group">
                                                                <label>Company Registered ID:</label>
                                                                <input type="text" class="form-control" name="company_id" placeholder="Enter your company registered ID" value="123456">
                                                                <span class="form-text text-muted">Please enter your company registered ID</span> 
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="row">
                                                        <div class="col-xl-6">
                                                            <div class="form-group">
                                                                <label>Company Email:</label>
                                                                <input type="text" class="form-control" name="company_email" placeholder="Enter company email" value="company@email.com">
                                                                <span class="form-text text-muted">Please enter your company email</span> 
                                                            </div>
                                                        </div>
                                                        <div class="col-xl-6">
                                                            <div class="form-group">
                                                                <label>Company Contact:</label>
                                                                <input type="tel" class="form-control" name="company_tel" placeholder="Enter comapny contact" value="1-541-754-3010">
                                                                <span class="form-text text-muted">Please enter your comapny contact</span> 
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="form-group">
                                                        <label>Communication:</label>
                                                        <div class="kt-checkbox-list">
                                                            <label class="kt-checkbox">
                                                                <input type="checkbox" name="account_communication[]" value="email" checked>
                                                                Email <span></span> 
                                                            </label>
                                                            <label class="kt-checkbox">
                                                                <input type="checkbox" name="account_communication[]" value="sms">
                                                                SMS <span></span> 
                                                            </label>
                                                            <label class="kt-checkbox">
                                                                <input type="checkbox" name="account_communication[]" value="phone">
                                                                Phone <span></span> 
                                                            </label>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <!--end: Form Wizard Step 3-->
                                            <!--begin: Form Wizard Step 4-->
                                            <div class="kt-wizard-v3__content" data-ktwizard-type="step-content">
                                                <div class="kt-heading kt-heading--md">Step 4</div>
                                                <div class="kt-separator kt-separator--height-xs"></div>
                                                <div class="kt-form__section kt-form__section--first">
                                                    <div class="row">
                                                        <div class="col-xl-6">
                                                            <div class="form-group">
                                                                <label>Cardholder Name:</label>
                                                                <input type="text" class="form-control" name="billing_card_name" placeholder="" value="Nick Stone">
                                                                <span class="form-text text-muted">Please enter the cardholder name</span> 
                                                            </div>
                                                        </div>
                                                        <div class="col-xl-6">
                                                            <div class="form-group">
                                                                <label>Card number:</label>
                                                                <input type="number" class="form-control" name="billing_card_number" placeholder="" value="372955886840581">
                                                                <span class="form-text text-muted">Enter the card number</span> 
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="row">
                                                        <div class="col-xl-4">
                                                            <div class="form-group">
                                                                <label>Exp Month:</label>
                                                                <select class="form-control" name="billing_card_exp_month">
                                                                    <option value="">Select</option>
                                                                    <option value="01">01</option>
                                                                    <option value="02">02</option>
                                                                    <option value="03">03</option>
                                                                    <option value="04" selected>04</option>
                                                                    <option value="05">05</option>
                                                                    <option value="06">06</option>
                                                                    <option value="07">07</option>
                                                                    <option value="08">08</option>
                                                                    <option value="09">09</option>
                                                                    <option value="10">10</option>
                                                                    <option value="11">11</option>
                                                                    <option value="12">12</option>
                                                                </select>
                                                            </div>
                                                        </div>
                                                        <div class="col-xl-4">
                                                            <div class="form-group">
                                                                <label>Exp Year:</label>
                                                                <select class="form-control" name="billing_card_exp_year">
                                                                    <option value="">Select</option>
                                                                    <option value="2018">2018</option>
                                                                    <option value="2019">2019</option>
                                                                    <option value="2020">2020</option>
                                                                    <option value="2021" selected>2021</option>
                                                                    <option value="2022">2022</option>
                                                                    <option value="2023">2023</option>
                                                                    <option value="2024">2024</option>
                                                                </select>
                                                            </div>
                                                        </div>
                                                        <div class="col-xl-4">
                                                            <div class="form-group">
                                                                <label>CVV:</label>
                                                                <input type="password" autocomplete="off" class="form-control" name="billing_card_cvv" placeholder="" value="450">
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="kt-heading kt-heading--md">Billing Address</div>
                                                    <div class="row">
                                                        <div class="col-lg-6">
                                                            <div class="form-group">
                                                                <label>Address Line 1:</label>
                                                                <input type="text" class="form-control" name="billing_address1" placeholder="" value="Headquarters 1120 N Street Sacramento 916-654-5266">
                                                            </div>
                                                            <div class="form-group">
                                                                <label>City:</label>
                                                                <input type="text" class="form-control" name="billing_city" placeholder="" value="Polo Alto" >
                                                            </div>
                                                            <div class="form-group">
                                                                <label>State:</label>
                                                                <input type="text" class="form-control" name="billing_state" placeholder="" value="California">
                                                            </div>
                                                        </div>
                                                        <div class="col-lg-6">
                                                            <div class="form-group">
                                                                <label>Address Line 2:</label>
                                                                <input type="text" class="form-control" name="billing_address2" placeholder="" value="P.O. Box 942873 Sacramento, CA 94273-0001">
                                                            </div>
                                                            <div class="form-group">
                                                                <label>Zip Code:</label>
                                                                <input type="number" class="form-control" name="billing_zip" placeholder="" value="942873">
                                                            </div>
                                                            <div class="form-group">
                                                                <label>Country:</label>
                                                                <select name="billing_country" class="form-control">
                                                                    <option value="">Select</option>
                                                                    <option value="AF">Afghanistan</option>
                                                                    <option value="AX">Åland Islands</option>
                                                                    <option value="AL">Albania</option>
                                                                    <option value="DZ">Algeria</option>
                                                                    <option value="AS">American Samoa</option>
                                                                    <option value="AD">Andorra</option>
                                                                    <option value="AO">Angola</option>
                                                                    <option value="AI">Anguilla</option>
                                                                    <option value="AQ">Antarctica</option>
                                                                    <option value="AG">Antigua and Barbuda</option>
                                                                    <option value="AR">Argentina</option>
                                                                    <option value="AM">Armenia</option>
                                                                    <option value="AW">Aruba</option>
                                                                    <option value="AU">Australia</option>
                                                                    <option value="AT">Austria</option>
                                                                    <option value="AZ">Azerbaijan</option>
                                                                    <option value="BS">Bahamas</option>
                                                                    <option value="BH">Bahrain</option>
                                                                    <option value="BD">Bangladesh</option>
                                                                    <option value="BB">Barbados</option>
                                                                    <option value="BY">Belarus</option>
                                                                    <option value="BE">Belgium</option>
                                                                    <option value="BZ">Belize</option>
                                                                    <option value="BJ">Benin</option>
                                                                    <option value="BM">Bermuda</option>
                                                                    <option value="BT">Bhutan</option>
                                                                    <option value="BO">Bolivia, Plurinational State of</option>
                                                                    <option value="BQ">Bonaire, Sint Eustatius and Saba</option>
                                                                    <option value="BA">Bosnia and Herzegovina</option>
                                                                    <option value="BW">Botswana</option>
                                                                    <option value="BV">Bouvet Island</option>
                                                                    <option value="BR">Brazil</option>
                                                                    <option value="IO">British Indian Ocean Territory</option>
                                                                    <option value="BN">Brunei Darussalam</option>
                                                                    <option value="BG">Bulgaria</option>
                                                                    <option value="BF">Burkina Faso</option>
                                                                    <option value="BI">Burundi</option>
                                                                    <option value="KH">Cambodia</option>
                                                                    <option value="CM">Cameroon</option>
                                                                    <option value="CA">Canada</option>
                                                                    <option value="CV">Cape Verde</option>
                                                                    <option value="KY">Cayman Islands</option>
                                                                    <option value="CF">Central African Republic</option>
                                                                    <option value="TD">Chad</option>
                                                                    <option value="CL">Chile</option>
                                                                    <option value="CN">China</option>
                                                                    <option value="CX">Christmas Island</option>
                                                                    <option value="CC">Cocos (Keeling) Islands</option>
                                                                    <option value="CO">Colombia</option>
                                                                    <option value="KM">Comoros</option>
                                                                    <option value="CG">Congo</option>
                                                                    <option value="CD">Congo, the Democratic Republic of the</option>
                                                                    <option value="CK">Cook Islands</option>
                                                                    <option value="CR">Costa Rica</option>
                                                                    <option value="CI">Côte d'Ivoire</option>
                                                                    <option value="HR">Croatia</option>
                                                                    <option value="CU">Cuba</option>
                                                                    <option value="CW">Curaçao</option>
                                                                    <option value="CY">Cyprus</option>
                                                                    <option value="CZ">Czech Republic</option>
                                                                    <option value="DK">Denmark</option>
                                                                    <option value="DJ">Djibouti</option>
                                                                    <option value="DM">Dominica</option>
                                                                    <option value="DO">Dominican Republic</option>
                                                                    <option value="EC">Ecuador</option>
                                                                    <option value="EG">Egypt</option>
                                                                    <option value="SV">El Salvador</option>
                                                                    <option value="GQ">Equatorial Guinea</option>
                                                                    <option value="ER">Eritrea</option>
                                                                    <option value="EE">Estonia</option>
                                                                    <option value="ET">Ethiopia</option>
                                                                    <option value="FK">Falkland Islands (Malvinas)</option>
                                                                    <option value="FO">Faroe Islands</option>
                                                                    <option value="FJ">Fiji</option>
                                                                    <option value="FI">Finland</option>
                                                                    <option value="FR">France</option>
                                                                    <option value="GF">French Guiana</option>
                                                                    <option value="PF">French Polynesia</option>
                                                                    <option value="TF">French Southern Territories</option>
                                                                    <option value="GA">Gabon</option>
                                                                    <option value="GM">Gambia</option>
                                                                    <option value="GE">Georgia</option>
                                                                    <option value="DE">Germany</option>
                                                                    <option value="GH">Ghana</option>
                                                                    <option value="GI">Gibraltar</option>
                                                                    <option value="GR">Greece</option>
                                                                    <option value="GL">Greenland</option>
                                                                    <option value="GD">Grenada</option>
                                                                    <option value="GP">Guadeloupe</option>
                                                                    <option value="GU">Guam</option>
                                                                    <option value="GT">Guatemala</option>
                                                                    <option value="GG">Guernsey</option>
                                                                    <option value="GN">Guinea</option>
                                                                    <option value="GW">Guinea-Bissau</option>
                                                                    <option value="GY">Guyana</option>
                                                                    <option value="HT">Haiti</option>
                                                                    <option value="HM">Heard Island and McDonald Islands</option>
                                                                    <option value="VA">Holy See (Vatican City State)</option>
                                                                    <option value="HN">Honduras</option>
                                                                    <option value="HK">Hong Kong</option>
                                                                    <option value="HU">Hungary</option>
                                                                    <option value="IS">Iceland</option>
                                                                    <option value="IN">India</option>
                                                                    <option value="ID">Indonesia</option>
                                                                    <option value="IR">Iran, Islamic Republic of</option>
                                                                    <option value="IQ">Iraq</option>
                                                                    <option value="IE">Ireland</option>
                                                                    <option value="IM">Isle of Man</option>
                                                                    <option value="IL">Israel</option>
                                                                    <option value="IT">Italy</option>
                                                                    <option value="JM">Jamaica</option>
                                                                    <option value="JP">Japan</option>
                                                                    <option value="JE">Jersey</option>
                                                                    <option value="JO">Jordan</option>
                                                                    <option value="KZ">Kazakhstan</option>
                                                                    <option value="KE">Kenya</option>
                                                                    <option value="KI">Kiribati</option>
                                                                    <option value="KP">Korea, Democratic People's Republic of</option>
                                                                    <option value="KR">Korea, Republic of</option>
                                                                    <option value="KW">Kuwait</option>
                                                                    <option value="KG">Kyrgyzstan</option>
                                                                    <option value="LA">Lao People's Democratic Republic</option>
                                                                    <option value="LV">Latvia</option>
                                                                    <option value="LB">Lebanon</option>
                                                                    <option value="LS">Lesotho</option>
                                                                    <option value="LR">Liberia</option>
                                                                    <option value="LY">Libya</option>
                                                                    <option value="LI">Liechtenstein</option>
                                                                    <option value="LT">Lithuania</option>
                                                                    <option value="LU">Luxembourg</option>
                                                                    <option value="MO">Macao</option>
                                                                    <option value="MK">Macedonia, the former Yugoslav Republic of</option>
                                                                    <option value="MG">Madagascar</option>
                                                                    <option value="MW">Malawi</option>
                                                                    <option value="MY">Malaysia</option>
                                                                    <option value="MV">Maldives</option>
                                                                    <option value="ML">Mali</option>
                                                                    <option value="MT">Malta</option>
                                                                    <option value="MH">Marshall Islands</option>
                                                                    <option value="MQ">Martinique</option>
                                                                    <option value="MR">Mauritania</option>
                                                                    <option value="MU">Mauritius</option>
                                                                    <option value="YT">Mayotte</option>
                                                                    <option value="MX">Mexico</option>
                                                                    <option value="FM">Micronesia, Federated States of</option>
                                                                    <option value="MD">Moldova, Republic of</option>
                                                                    <option value="MC">Monaco</option>
                                                                    <option value="MN">Mongolia</option>
                                                                    <option value="ME">Montenegro</option>
                                                                    <option value="MS">Montserrat</option>
                                                                    <option value="MA">Morocco</option>
                                                                    <option value="MZ">Mozambique</option>
                                                                    <option value="MM">Myanmar</option>
                                                                    <option value="NA">Namibia</option>
                                                                    <option value="NR">Nauru</option>
                                                                    <option value="NP">Nepal</option>
                                                                    <option value="NL">Netherlands</option>
                                                                    <option value="NC">New Caledonia</option>
                                                                    <option value="NZ">New Zealand</option>
                                                                    <option value="NI">Nicaragua</option>
                                                                    <option value="NE">Niger</option>
                                                                    <option value="NG">Nigeria</option>
                                                                    <option value="NU">Niue</option>
                                                                    <option value="NF">Norfolk Island</option>
                                                                    <option value="MP">Northern Mariana Islands</option>
                                                                    <option value="NO">Norway</option>
                                                                    <option value="OM">Oman</option>
                                                                    <option value="PK">Pakistan</option>
                                                                    <option value="PW">Palau</option>
                                                                    <option value="PS">Palestinian Territory, Occupied</option>
                                                                    <option value="PA">Panama</option>
                                                                    <option value="PG">Papua New Guinea</option>
                                                                    <option value="PY">Paraguay</option>
                                                                    <option value="PE">Peru</option>
                                                                    <option value="PH">Philippines</option>
                                                                    <option value="PN">Pitcairn</option>
                                                                    <option value="PL">Poland</option>
                                                                    <option value="PT">Portugal</option>
                                                                    <option value="PR">Puerto Rico</option>
                                                                    <option value="QA">Qatar</option>
                                                                    <option value="RE">Réunion</option>
                                                                    <option value="RO">Romania</option>
                                                                    <option value="RU">Russian Federation</option>
                                                                    <option value="RW">Rwanda</option>
                                                                    <option value="BL">Saint Barthélemy</option>
                                                                    <option value="SH">Saint Helena, Ascension and Tristan da Cunha</option>
                                                                    <option value="KN">Saint Kitts and Nevis</option>
                                                                    <option value="LC">Saint Lucia</option>
                                                                    <option value="MF">Saint Martin (French part)</option>
                                                                    <option value="PM">Saint Pierre and Miquelon</option>
                                                                    <option value="VC">Saint Vincent and the Grenadines</option>
                                                                    <option value="WS">Samoa</option>
                                                                    <option value="SM">San Marino</option>
                                                                    <option value="ST">Sao Tome and Principe</option>
                                                                    <option value="SA">Saudi Arabia</option>
                                                                    <option value="SN">Senegal</option>
                                                                    <option value="RS">Serbia</option>
                                                                    <option value="SC">Seychelles</option>
                                                                    <option value="SL">Sierra Leone</option>
                                                                    <option value="SG">Singapore</option>
                                                                    <option value="SX">Sint Maarten (Dutch part)</option>
                                                                    <option value="SK">Slovakia</option>
                                                                    <option value="SI">Slovenia</option>
                                                                    <option value="SB">Solomon Islands</option>
                                                                    <option value="SO">Somalia</option>
                                                                    <option value="ZA">South Africa</option>
                                                                    <option value="GS">South Georgia and the South Sandwich Islands</option>
                                                                    <option value="SS">South Sudan</option>
                                                                    <option value="ES">Spain</option>
                                                                    <option value="LK">Sri Lanka</option>
                                                                    <option value="SD">Sudan</option>
                                                                    <option value="SR">Suriname</option>
                                                                    <option value="SJ">Svalbard and Jan Mayen</option>
                                                                    <option value="SZ">Swaziland</option>
                                                                    <option value="SE">Sweden</option>
                                                                    <option value="CH">Switzerland</option>
                                                                    <option value="SY">Syrian Arab Republic</option>
                                                                    <option value="TW">Taiwan, Province of China</option>
                                                                    <option value="TJ">Tajikistan</option>
                                                                    <option value="TZ">Tanzania, United Republic of</option>
                                                                    <option value="TH">Thailand</option>
                                                                    <option value="TL">Timor-Leste</option>
                                                                    <option value="TG">Togo</option>
                                                                    <option value="TK">Tokelau</option>
                                                                    <option value="TO">Tonga</option>
                                                                    <option value="TT">Trinidad and Tobago</option>
                                                                    <option value="TN">Tunisia</option>
                                                                    <option value="TR">Turkey</option>
                                                                    <option value="TM">Turkmenistan</option>
                                                                    <option value="TC">Turks and Caicos Islands</option>
                                                                    <option value="TV">Tuvalu</option>
                                                                    <option value="UG">Uganda</option>
                                                                    <option value="UA">Ukraine</option>
                                                                    <option value="AE">United Arab Emirates</option>
                                                                    <option value="GB">United Kingdom</option>
                                                                    <option value="US" selected>United States</option>
                                                                    <option value="UM">United States Minor Outlying Islands</option>
                                                                    <option value="UY">Uruguay</option>
                                                                    <option value="UZ">Uzbekistan</option>
                                                                    <option value="VU">Vanuatu</option>
                                                                    <option value="VE">Venezuela, Boliletian Republic of</option>
                                                                    <option value="VN">Viet Nam</option>
                                                                    <option value="VG">Virgin Islands, British</option>
                                                                    <option value="VI">Virgin Islands, U.S.</option>
                                                                    <option value="WF">Wallis and Futuna</option>
                                                                    <option value="EH">Western Sahara</option>
                                                                    <option value="YE">Yemen</option>
                                                                    <option value="ZM">Zambia</option>
                                                                    <option value="ZW">Zimbabwe</option>
                                                                </select>
                                                            </div>
                                                        </div>
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
 <div class="modal fade bd-example-modal-xl" tabindex="-1" role="dialog" aria-labelledby="myLargeModalLabel" aria-hidden="true">
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

     <script>
        $(function(){
        $('#closeThird').on('click',function(){
        $('#thirdParty').hide();
        });
        $('#openThird').on('click',function(){
        $('#thirdParty').show();
        });
        });


     </script>

       <script>
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
  
</script>

     <form id="claimForm" action="{{Route('authorizeClaim')}}" method="POST">
     {{csrf_field()}}
        <input type="hidden" name="claim_id" value="{{$glass_claims->id}}" />
        <input type="hidden" name="customerCell" value="{{$userDetails->cellphone}}" />
        <input type="hidden" name="customerName" value="{{$userDetails->firstName}}" />
        <input type="hidden" name="claimState" value="authorized"/>
     </form>



    </body>
    <!-- end::Body -->
</html>