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


                    <!-- begin:: Content -->
                        <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
                                <div class="loading">Loading&#8230;</div>

                            <div class="kt-portlet">
                                <div class="kt-portlet__body kt-portlet__body--fit">
                                    <div class="kt-wizard-v3" id="kt_wizard_v3" data-ktwizard-state="step-first">

                                       
                                        <!--begin: Form Wizard Form-->

                                        <form id="kt_form" name="ClaimForm" class="kt-form" action="{{Route('admin-savePolicy')}}" method="POST" accept="image/jpeg , image/bmp , image/png" enctype="multipart/form-data">
                                        {{csrf_field()}}
                                            <!--begin: Form Wizard Step 1-->
                                            <div class="kt-wizard-v3__content" data-ktwizard-type="step-content" data-ktwizard-state="current" style="width:80%">
                                               <h3 class="kt-section__title" >New Policy Form</h3>
                                                <div class="kt-heading kt-heading--md">Customer Details</div>
                                                <div class="kt-separator kt-separator--height-xs"></div>
                                                <div class="kt-form__section kt-form__section--first">
                                                  <div class="row">
                                                    <div class="col-lg-12">
                                                    <div class="form-group">
                                                        <label>First Name</label>
                                                        <input type="text" class="form-control" name="fname" placeholder="First Name" title="Customer first name must only contain alphabets" pattern="[A-Za-z]{1,25}" maxlength="25" value="{{ old('fname') }}"  required> 
                                                    </div>
                                                
                                               
                                                    </div>
                                                    <div class="col-lg-12">
                                                        <div class="form-group">
                                                            <label>Last Name</label>
                                                            <input type="text" class="form-control" name="lname" title="Customer last name must only contain alphabets" placeholder="Last Name" value="{{ old('lname') }}" pattern="[A-Za-z]{1,25}" maxlength="25" required> 
                                                       </div>
                                                    </div>

                                                    <div class="col-lg-12">
                                                        <div class="form-group">
                                                            <label>Cellphone Number</label>
                                                            <input type="text" class="form-control" name="cellphone" title="Customer cellhone should only contain digits Eg: 72394940 " placeholder="Please enter customers cellphone" value="{{old('cellphone')}}" pattern="[0-9]{1,25}" minlength="8" maxlength="8"  required> 
                                                       </div>
                                                    </div>

                                                    <div class="col-lg-12">
                                                        <div class="form-group">
                                                            <label>Email</label>
                                                            <input type="email" class="form-control" name="email" placeholder="Please enter customer email address" title="Customers Email" value="{{old('email')}}"> 
                                                        </div>
    
                                                    </div>

                                                    <div class="col-lg-12">
                                                        <div class="form-group">
                                                            <label>Physical Address</label>
                                                            <input type="text" class="form-control" name="address" title="Physical address can contain alphabets and numeric characters" placeholder="Please enter customers physical address" value="{{old('address')}}"   maxlength="60" required> 
                                                          </div>
                                                    </div>

                                                    <div class="col-lg-12">
                                                        <div class="form-group">
                                                            <label>Omang ID</label>
                                                            <input id="omang" type="text" class="form-control id-type" name="omang" placeholder="Please enter omang number" title="Omang must have 9 digits only" pattern="[0-9]{9}" minlength="9" maxlength="9" value="{{old('omang')}}"  required> 
                                                        </div>
    
                                                    </div>

                                                    <div class="col-lg-12">
                                                        <div class="form-group">
                                                            <label>Passport Number</label>
                                                            <input id="passport" type="text" class="form-control id-type" name="passport" placeholder="Please enter passport number" title="Passport Number" maxlength="12" value="{{old('passport')}}"  required> 
                                                        </div>
    
                                                    </div>
                                                    <div class="col-lg-12">
                                                        <div class="form-group">
                                                            <label>Date Of Birth</label>
                                                            <input  type="date" class="form-control" id="dob" name="dob" value="{{old('dob')}}" title="Customers age, must be 18 years or older" placeholder="DD/MM/YYYY"
                                                                                                   onclick="datePicker(event)" data-relmax="-18" required>
                                                       </div>
                                                    </div>
                                                    </div>
                                               
                                                   

                                                  <div class="kt-separator kt-separator--border-dashed kt-separator--space-lg"></div>
                                                  <div class="kt-heading kt-heading--md">Vehicle Details</div>
                                                  <div class="row">
                                                    	<div class="col-lg-6">
                                                    
                                                        <div class="form-group">
                                                            <label>Vehicle Make</label>

                                                            <select class="form-control kt_selectpicker" name="make" title="Please choose vehicle make..." data-live-search="true" id="carMakeDropDown" required>
                                                             @foreach ($carMake as $make)

                                                                @if(old('make') == $make->s_Make)
                                                                  <option value="{{$make->s_Make}}" selected>{{$make->s_Make}}</option>
                                                                @else 
                                                                <option value="{{$make->s_Make}}">{{$make->s_Make}}</option>

                                                                @endif
                                                             @endforeach
                                                            </select>
                                                       </div>
                                                    	</div>
                                                    	<div class="col-lg-6">
                                                            <div class="form-group">
                                                                <label>Vehicle Model</label>
                                                                <style>
                                                                    .kt-spinner.kt-spinner--input.kt-spinner--left::before {
                                                                        z-index:9999;
                                                                        margin-left: 24px;
                                                                    }
                                                                </style>
                                                                <div id="carSpinner" style="display:contents;" class="form-group kt-spinner kt-spinner--sm kt-spinner--success kt-spinner--left kt-spinner--input"></div>
                                                                <select class="form-control kt_selectpicker " name="model" title="Please choose vehicle model..." data-live-search="true" id="carModelDropDown" disabled>
                                                                    @if(old('model')!= null)
                                                                <option value="{{old('model')}}"> {{old('model')}}</option>
                                                                @endif
                                                                </select>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="row">

                                                    	<div class="col-lg-6">
                                                            <div class="form-group">
                                                                <label>Vehicle Year</label>
                                                                 <select id="years" name="year"  title="Please select vehicle year" data-live-search="true" class="form-control kt_selectpicker" required>
                                                                    @if(old('year')!= null)
                                                                    <option value="{{old('year')}}" selected > {{old('year')}}</option>
                                                                    @endif

                                                                 </select>
                                                            </div>
                                                        </div>
                                                        
                                                        <div class="col-lg-6">
                                                                <div class="form-group">
                                                                    <label>Registration Vehicle No.</label>
                                                                    <input type="text" class="form-control" name="license" title="Required format : B 121 ABC"  placeholder="Eg. B 111 AAA"  pattern="^[Bb]{1}\d{3}[a-zA-Z]{3}$"  minlength="7" maxlength="7" value="{{old('license')}}" style="text-transform: uppercase" required> 
                                                                    </div>
                                                            </div>
                                                    </div>

                                                             
                                                    <div class="row">
                                                            <div class="col-lg-6">
                                                                    <div class="form-group">
                                                                        <label>Odometer</label>
                                                                        <input type="text" class="form-control" name="odometer" title="Odometer should only have numeric values" value="{{old('odometer')}}" placeholder="Please enter Odometer reading" maxlength="9"> 
                                                                   </div>
                                                                </div>
                                                                <div class="col-lg-6">
                                                                    <div class="form-group">
                                                                        <label>Condition</label>

                                                                        <select class="form-control kt_selectpicker" title="Please select the condition of the vehicle" name="condition" id="exampleSelect1">
                                                                           

                                                                            @if(old('condition')== 'Very Good')
                                                                             <option value="Very Good" selected>Very Good</option>
                                                                             <option value="Good">Good</option>
                                                                             <option value="Poor">Poor</option>
                                                                             <option value="Very Poor">Very Poor</option>
                                                                             @elseif(old('condition')== 'Good')
                                                                             <option value="Very Good" >Very Good</option>
                                                                             <option value="Good" selected>Good</option>
                                                                             <option value="Poor">Poor</option>
                                                                             <option value="Very Poor">Very Poor</option>

                                                                             @elseif(old('condition')== 'Poor')

                                                                             <option value="Very Good" >Very Good</option>
                                                                             <option value="Good" selected>Good</option>
                                                                             <option value="Poor" selected>Poor</option>
                                                                             <option value="Very Poor">Very Poor</option>

                                                                             @elseif(old('condition')== 'Very Poor')
                                                                             <option value="Very Good" >Very Good</option>
                                                                             <option value="Good" selected>Good</option>
                                                                             <option value="Poor" selected>Poor</option>
                                                                             <option value="Very Poor">Very Poor</option>

                                                                             @elseif(old('condition')== null)
                                                                             <option value="Very Good" >Very Good</option>
                                                                             <option value="Good">Good</option>
                                                                             <option value="Poor">Poor</option>
                                                                             <option value="Very Poor">Very Poor</option> 

                                                                             @endif
                                                                            
                                                                            </select>
                                                                        </div>
                                                                </div>
                                                            </div>

                                                  <div class="kt-separator kt-separator--border-dashed kt-separator--space-lg"></div>
                                                  <div class="kt-heading kt-heading--md">Vehicle Images</div>
                                                  <div class="row">
                                                    	<div class="col-lg-6">

                                                            <div class="form-group">
                                                                <label>Front View</label>
                                                                <div></div>
                                                                <div class="custom-file">
                                                                    <input type="file" class="custom-file-input" name="front" id="frontViewUpload" >
                                                                    <label class="custom-file-label" for="customFile">Choose file</label>
                                                                </div>
                                                            </div>
                                                    	</div>
                                                    	<div class="col-lg-6">
                                                            <div class="thumbnail">
                                                                <a class="front" href="#" id="frontViewModal" data-toggle="modal" data-title="This is my title" data-caption="Some lovely red flowers" data-image="" data-target="#image-gallery">
                                                                    <img id="frontView" src="#" alt="Front Car Image" width="280px" height="155px" />
                                                                </a>
                                                            </div>

                                                    	</div>
                                                        </div>
                                                  <div class="row">
                                                    	<div class="col-lg-6">
                                                                <div class="form-group">
                                                                        <label>Back View</label>
                                                                        <div></div>
                                                                        <div class="custom-file">
                                                                            <input type="file" class="custom-file-input" name="back" id="backViewUpload" accept=".jpg,.jpeg,.png" onchange="validateFileTypeBack()" >
                                                                            <label class="custom-file-label" for="customFile">Choose file</label>
                                                                        </div>
                                                                    </div>
                                                                
                                                    	</div>
                                                    	<div class="col-lg-6">
                                                                <a class="thumbnail" href="#" id="backViewModal" data-toggle="modal" data-title="This is my title" data-caption="Some lovely red flowers" data-image="" data-target="#image-gallery">
                                                                    <img id="backView" src="#" class="gridimg" alt="Back Car Image" width="280px" height="155px"/>
                                                                </a>

                                                    	</div>
                                                        </div>
                                                  <div class="row">
                                                        <div class="col-lg-6">

                                                        <div class="form-group">
                                                                <label>Right Side</label>
                                                                <div></div>
                                                                <div class="custom-file">
                                                                    <input type="file" class="custom-file-input" name="right" id="rightViewUpload" accept=".jpg,.jpeg,.png" onchange="validateFileTypeRight()" >
                                                                    <label class="custom-file-label" for="customFile">Choose file</label>
                                                                </div>
                                                            </div>
                                                            </div>
                                                    	<div class="col-lg-6">
                                                                <a class="thumbnail" href="#" id="rightViewModal" data-toggle="modal" data-title="This is my title" data-caption="Some lovely red flowers" data-image="" data-target="#image-gallery">
                                                                    <img id="rightView" src="#" class="gridimg" alt="Right Car Image"  width="280px" height="155px" />
                                                                </a>

                                                    	</div>
                                                        </div>
                                                  <div class="row">
                                                        <div class="col-lg-6">

                                                        <div class="form-group">
                                                                <label>Left Side</label>
                                                                <div></div>
                                                                <div class="custom-file">
                                                                    <input type="file" class="custom-file-input" name="left" id="leftViewUpload" accept=".jpg,.jpeg,.png" onchange="validateFileTypeLeft()">
                                                                    <label class="custom-file-label" for="customFile">Choose file</label>
                                                                </div>
                                                            </div>
                                                            </div>
                                                    	<div class="col-lg-6">
                                                                <a class="thumbnail" href="#" id="leftViewModal" data-toggle="modal" data-title="This is my title" data-caption="Some lovely red flowers" data-image="{{asset('images/cracked windscreen.jpg')}}" data-target="#image-gallery">
                                                                    <img id="leftView" src="#" class="gridimg" alt="Right Car Image" width="280px" height="155px" />
                                                                </a>

                                                        	</div>
                                                        </div>

                                     
                                    
                                                    


                                                  <div class="kt-separator kt-separator--border-dashed kt-separator--space-lg"></div>
                                                  <div class="kt-heading kt-heading--md">Policy Plan</div>
                                                  <div class="row">
                                                    	<div class="col-lg-6">
                                                        <label>Policy Plan:</label>

                                                                <select class="form-control kt_selectpicker" title="Please select the policy plan the customer prefers" name="policyPlan" id="exampleSelect1" required>
                                                                    @foreach ($policyPlan as $plan )

                                                                    @if(old('policyPlan') == $plan->premium)
                                                                    <option value="{{$plan->premium}}" selected>{{$plan->plan}}</option>
                                                                     @else 
                                                                     <option value="{{$plan->premium}}">{{$plan->plan}}</option>
                                                                    
                                                                     @endif


                                                                    @endforeach
                                                                </select>
                                                    	</div>
                                                    

                                                    </div>
                                               
                                                 
                                                  <div class="kt-separator kt-separator--border-dashed kt-separator--space-lg"></div>
                                                  <div class="kt-heading kt-heading--md">Customer Bank Details</div>
                                                  <div class="row">
                                                    <div class="col-lg-6">
                                                        <div class="form-group">

                                                        <label for="exampleSelect1">Billing Method:</label>
                                                            <select id="billing" class="form-control kt_selectpicker" title="Please select customers preferred billing method" name="billing" id="exampleSelect1" onchange="disableOptions(event)">
                                                                @if(old('billing')== null)
                                                                <option value="Bank">Bank</option>
                                                                <option value="MyZaka">MyZaka</option>
                                                                <option value="orangeMoney">Orange Money</option>
                                                                @elseif(old('billing')== 'Bank')
                                                                <option value="Bank" selected>Bank</option>
                                                                <option value="MyZaka">MyZaka</option>
                                                                <option value="orangeMoney">Orange Money</option>
                                                                @elseif(old('billing')== 'MyZaka')
                                                                <option value="Bank" >Bank</option>
                                                                <option value="MyZaka" selected>MyZaka</option>
                                                                <option value="orangeMoney">Orange Money</option>
                                                                @elseif(old('billing') == 'orangeMoney')
                                                                <option value="Bank" >Bank</option>
                                                                <option value="MyZaka" >MyZaka</option>
                                                                <option value="orangeMoney" selected>Orange Money</option>
                                                                @endif
                                                                
                                                            </select>
                                                        
                                                        
                                                        </div>
                                                    </div>
                                                    <div class="col-lg-6">
                                                    <div class="form-group">
                                                    <label>Myzaka/Orange Money Cell:</label>
					                            	<input id="billingCell" type="text" class="form-control" name="billingCell" aria-describedby="emailHelp" title="Billing number should only have 8 numbers" placeholder="Myzaka/Orange Cell" value="{{old('billingCell')}}" minlength="8" maxlength="8" pattern="[0-9]{8}"   disabled>
															</div>
						
                                                    </div>
                                                    </div>
                                                  <div class="row">
                                                    	<div class="col-lg-6">
															<div class="form-group">
                                                          
                                                            <label for="exampleSelect1">Bank Name:</label>
							                                    <select id="bankNumDropDown" class="form-control kt_selectpicker" title="Please select customers bank" name="bankName" ></select>
                                                            </div>

                                                        </div>

                                                        <div class="col-lg-6">
															<div class="form-group">
                                                            <label>Branch Code:</label>
                                                            <select id="bankBranchDropDown" class="form-control kt_selectpicker" title="Please select customers branch" name="branchCode" required></select>

                                                            </div>
                                                    	</div>
                                                    	<div class="col-lg-6">
															<div class="form-group">
                                                                <label>Account Number:</label>
					                                        	<input id="accountNumber" type="text" class="form-control" name="accountNumber" title="account number should only be numeric characters" value="{{old('accountNumber')}}" aria-describedby="emailHelp" placeholder="Account Number"pattern="[0-9]{1,25}"  maxlength="25" required>
                                                            </div>
                                                    	</div>
                                                    	<div class="col-lg-6">
															<div class="form-group">
                                                                <label>Account Type:</label>
                                                                <select id="accountType" class="form-control kt_selectpicker" name="bankAccountType"  required>
                                                                        <option>Please Select the Bank Account Type</option>

                                                                        @if(old('bankAccountType') == 1)
                                                                        <option value="1" selected>Cheque</option>
                                                                        <option value="2" >Savings</option>
                                                                        @else 
                                                                        <option value="1" >Cheque</option>
                                                                        <option value="2" selected >Savings</option>
                                                                        @endif
                                                                       
                                                                        
                                                                    </select>
                                                            </div>
                                                    	</div>

                                                  </div>
                                                  <div class="row">
  
                                                    	<div class="col-lg-3">
                                                    	</div>
                                                    	<div class="col-lg-3">
                                                    	</div>
                                                    	<div class="col-lg-3">
                                                                <button id="resetForm" type="button" class="btn btn-info btn-lg pull-right">Reset Form</button>
                                                    	</div>
                                                    	<div class="col-lg-3">
                                                                <button id="submitForm" type="submit" class="btn btn-success btn-lg pull-right">Submit Policy</button>
                                                    	</div>

                                                  </div>

                                                     </form>
                                                     
 													</div>
                                                    </div>
                                        </form>
                                                </div>
                                            </div>
                                            <!--end: Form Wizard Step 1-->

                                            <!--begin: Form Actions -->
                                     
                                            <!--end: Form Actions -->
                                       
                                        <!--end: Form Wizard Form-->
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- end:: Content -->
                    </div>
                    <!-- begin:: Footer -->
                    <div class="kt-footer kt-grid__item kt-grid kt-grid--desktop kt-grid--ver-desktop">
                        <div class="kt-footer__copyright"> 2019&nbsp;&copy;&nbsp;<a href="#" target="_blank" class="kt-link">Alpha Direct</a> </div>
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

        <div class="modal fade bd-example-modal-xl" id="image-gallery" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-xl">
                    <div class="modal-content">
                        <div class="modal-header">                           
                             <h4 class="modal-title" id="image-gallery-title">Uploaded Image</h4>

                            <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">×</span><span class="sr-only">Close</span></button>
                        </div>
                        <div class="modal-body">
                            <img id="image-gallery-image" class="img-responsive" style='height:80vh' width="100%" src="">
                        </div>
                        <div class="modal-footer">
            
                          
                        </div>
                    </div>
                </div>
            </div>
        <!-- end:: Quick Panel -->
        <!-- begin:: Scrolltop -->
        <div id="kt_scrolltop" class="kt-scrolltop"> <i class="la la-arrow-up"></i> </div>
        <!-- end:: Scrolltop -->                
                
        <!-- begin::Global Config(global config for global JS sciprts) -->
        <script>

        function datePicker(evt) {
			$('input[data-relmax]').each(function () {
				let oldVal = $(this).prop('value');
				let relmax = $(this).data('relmax');
				let max = new Date();
				max.setFullYear(max.getFullYear() + relmax);
				$.prop(this, 'max', $(this).prop('valueAsDate', max).val());
				$.prop(this, 'value', oldVal);
			});
		}
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

    <script src="{{asset('css/app/custom/general/components/forms/widgets/bootstrap-select.js')}}" type="text/javascript"></script>

    <script src="{{asset('css/app/custom/general/components/extended/sweetalert2.min.js')}}" type="text/javascript"></script>
<script src="https://cdn.jsdelivr.net/jquery.validation/1.16.0/jquery.validate.min.js"></script>
<script src="https://cdn.jsdelivr.net/jquery.validation/1.16.0/additional-methods.min.js"></script>
 
    <script>
            $( document ).ready(function() {
                $('.loading').css("display","none"); 
                
            });
    </script>
    <script>
        $(document).ready(function(){
            $("#carSpinner").css("display", "none");
            var CSRF_TOKEN = $('meta[name="csrf-token"]').attr('content');
            $("#carMakeDropDown").change(function(){
            
                $("#carSpinner").css("display", "contents");
          
                $.ajax({
                    /* the route pointing to the post function */
                    url: '{{Route('getCarModel')}}',
                    type: 'POST',
                    /* send the csrf-token and the input to the controller */
                    data: { 
                        _token: CSRF_TOKEN,
                        make:$('#carMakeDropDown option:selected').val() 
                    },
                    dataType: 'JSON',
                    /* remind that 'data' is the response of the AjaxController */
                    success: function (data) { 
                        if(data) {
                        $("#carModelDropDown").removeAttr('title');
                        $("#carModelDropDown").removeAttr('disabled');
                        $('#carModelDropDown').empty();
                        
                        $.each(data, function(key, value){
                            $('#carModelDropDown').append('<option value="'+value.s_Variant+'">' + value.s_Variant +'</option>');
                            $("#carModelDropDown").selectpicker('refresh');
                        });
                        $("#carSpinner").css("display", "none");
                    } else {
                        $('#carModelDropDown').empty();
                     }
                    }
                });
            });

            // Upload front image 
            
       });   

    $(document).ready(function(){
       // Sweetalert Demo 3
       $('#resetForm').click(function(e){
           
            swal({
                title:"Are you sure?",
                text:"This will reset the form",
                type:"warning",}).then((reset)=>{
                $("#kt_form")[0].reset();
                location.hash = '#kt_form';
                })
            });

            $( "#kt_form" ).validate({
                rules: {
                  /*   field: {
                    required: true,
                    }, */
                    
                    omang: {
                    require_from_group: [1, ".id-type"]
                    },
                    passport: {
                    require_from_group: [1, ".id-type"]
                    },
                    //front: {required: true , accept: "image/*"},
                    //frontViewUpload: {required: true , accept: "image/*"},
                 }
                });
       /* $('#submitForm').click(function(e){
          e.preventDefault();
        $('#kt_form').each(function() {
             
            if ( $(this).val() === '' ) {
            return false;
          
            }else {
                $('.loading').css("display","block");
            document.getElementById('kt_form').submit();
            return true;
            }
        });
       
        
    }); */
        
            let branches = []; 
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
                        $('#bankNumDropDown').append('<option value="'+value["ns0:bankNum"]+'">' + value["ns0:bankDesc"] +'</option>');
                        $("#bankNumDropDown").selectpicker('refresh');
                    });
                }
                }
            }); 
            var urlValue = '{{ \Config::get('values.graphite_url') }}' 
            $.ajax({
                /* the route pointing to the post function */
                url: urlValue+'realpay/getBankBranches',
                type: 'GET',
                /* send the csrf-token and the input to the controller */
                data: {},
                dataType: 'JSON',
                /* remind that 'data' is the response of the AjaxController */
                success: function (data) { 
                    if(data) {
                        console.log(data);
                        branches = data;
                    }
                }
            });
            $("#bankNumDropDown").change(function(){
                const bankNum = $(this).val();
                let filterArray = [];
                branches.forEach((bank)=>{
                    if(bank['ns0:bankNum']==bankNum) {
                        filterArray.push(bank);
                    }
                });
                
                    $('#bankBranchDropDown').empty();
                filterArray.forEach((value) => {
                    $('#bankBranchDropDown').append('<option value="'+value["ns0:bankBranchNum"]+'">' + value["ns0:bankBranchDesc"] +'</option>');
                    $("#bankBranchDropDown").selectpicker('refresh');
                });
            });
       });  
    </script>

     <script type="text/javascript">
        function alphaOnly(event) {
        var key = event.keyCode;
        return ((key >= 65 && key <= 90) || key == 8);
        };
        function isNumberKey(evt){
				var charCode = (evt.which) ? evt.which : event.keyCode
				if (charCode > 31 && (charCode < 48 || charCode > 57))
					return false;
				return true;
			}    
        function clearForm(evt){	
            document.getElementById('kt_form').reset();
            document.getElementById('kt_form').scrollIntoView();
		}    
        function validateFileTypeFront(){
            var fileName = document.getElementById("front").value;
            var idxDot = fileName.lastIndexOf(".") + 1;
            var extFile = fileName.substr(idxDot, fileName.length).toLowerCase();
            if (extFile=="jpg" || extFile=="jpeg" || extFile=="png"){
                //TO DO
            }else{
                alert("Only jpg/jpeg and png files are allowed!");
            }   
    }
        function validateFileTypeBack(){
            var fileName = document.getElementById("back").value;
            var idxDot = fileName.lastIndexOf(".") + 1;
            var extFile = fileName.substr(idxDot, fileName.length).toLowerCase();
            if (extFile=="jpg" || extFile=="jpeg" || extFile=="png"){
                //TO DO
            }else{
                alert("Only jpg/jpeg and png files are allowed!");
            }   
    }
        function validateFileTypeRight(){
            var fileName = document.getElementById("right").value;
            var idxDot = fileName.lastIndexOf(".") + 1;
            var extFile = fileName.substr(idxDot, fileName.length).toLowerCase();
            if (extFile=="jpg" || extFile=="jpeg" || extFile=="png"){
                //TO DO
            }else{
                alert("Only jpg/jpeg and png files are allowed!");
            }   
    }
        function validateFileTypeLeft(){
            var fileName = document.getElementById("left").value;
            var idxDot = fileName.lastIndexOf(".") + 1;
            var extFile = fileName.substr(idxDot, fileName.length).toLowerCase();
            if (extFile=="jpg" || extFile=="jpeg" || extFile=="png"){
                //TO DO
            }else{
                alert("Only jpg/jpeg and png files are allowed!");
            }   
    }
    
        
    $(document).ready(function(){
            $(function(){
                $("#frontViewModal").hide();
                 $("#frontViewUpload").change(function(){
                    $("#frontViewModal").show();
                });
            });
            $(function(){
                $("#backViewModal").hide();
                 $("#backViewUpload").change(function(){
                    $("#backViewModal").show();
                });
            });
            $(function(){
                $("#rightViewModal").hide();
                 $("#rightViewUpload").change(function(){
                    $("#rightViewModal").show();
                });
            });
            
            $(function(){
                $("#leftViewModal").hide();
                 $("#leftViewUpload").change(function(){
                    $("#leftViewModal").show();
                });
            });
    });
        function enableDisableSend(oFld) {
            console.log('printin');
            causeOfDamage = oFld.form.causeOfDamage.value;
            if (causeOfDamage == '') {
                oFld.form.write.disabled = TRUE;
                document.getElementById("submitForm").className = 'comment_popup_button_disabled';
            } else {
                oFld.form.write.disabled = FALSE;
                document.getElementById("submitForm").className = 'comment_popup_button_active';
            }
            }
            var nowY = new Date().getFullYear(),
            options = "";
            for(var Y=nowY; Y>=1995; Y--) {
            options += "<option>"+ Y +"</option>";
            }
            $("#years").append( options );
            function readURL(input) {
                     if (input.files && input.files[0]) {
                     var reader = new FileReader();
                     reader.onload = function(e) {
                         $('#frontView').attr('src', e.target.result);
                         $('#frontViewModal').on('click', function(){
                            $('#image-gallery-image').attr('src', e.target.result);
                            });
                     }
                   
                 
                     reader.readAsDataURL(input.files[0]);
                     }
                }
            function readURLBack(input) {
                     if (input.files && input.files[0]) {
                     var reader = new FileReader();
                     reader.onload = function(e) {
                         $('#backView').attr('src', e.target.result);
                         $('#image-gallery-image').attr('src', e.target.result);
                         $('#backViewModal').on('click', function(){
                        $('#image-gallery-image').attr('src', e.target.result);
                        });
                         
                     }
                 
                     reader.readAsDataURL(input.files[0]);
                     }
                }
            function readURLRight(input) {
                     if (input.files && input.files[0]) {
                     var reader = new FileReader();
                     reader.onload = function(e) {
                         $('#rightView').attr('src', e.target.result);
                         $('#image-gallery-image').attr('src', e.target.result);
                         $('#rightViewModal').on('click', function(){
                        $('#image-gallery-image').attr('src', e.target.result);
                        });
                     }
                     
                 
                     reader.readAsDataURL(input.files[0]);
                     }
                }
            function readURLLeft(input) {
                     if (input.files && input.files[0]) {
                     var reader = new FileReader();
                     reader.onload = function(e) {
                         $('#leftView').attr('src', e.target.result);
                         $('#image-gallery-image').attr('src', e.target.result);
                         $('#leftViewModal').on('click', function(){
                            $('#image-gallery-image').attr('src', e.target.result);
                            });
                     }
                     
                     reader.readAsDataURL(input.files[0]);
                     }
                }
            //Front view preview    
            $("#frontViewUpload").change(function() {
            readURL(this);
            });
            //Back view preview    
            $("#backViewUpload").change(function() {
            readURLBack(this);
            });
            //Right view preview    
            $("#rightViewUpload").change(function() {
             readURLRight(this);
            });
            //Left view preview    
            $("#leftViewUpload").change(function() {
                readURLLeft(this);
            });
            //Passport and ID
            $("#omang").change(function() {
                $('#passport').removeAttr('required');
            });
            $("#passport").change(function() {
                $('#omang').removeAttr('required');
            });
        @if(Session::has('policySaved'))
        Toastify({
            text: "{{ Session::get('submittedClaim') }}",
            duration: 4000,
            newWindow: true,
            gravity: "top", // `top` or `bottom`
            positionRight: true, // `true` or `false`
            backgroundColor: "#00C851",
            }).showToast();   
        @endif
        @if(Session::has('userAccountExists'))
        Toastify({
            text: "{{ Session::get('userAccountExists') }}",
            duration: 4000,
            newWindow: true,
            gravity: "top", // `top` or `bottom`
            positionRight: true, // `true` or `false`
            backgroundColor: "#FF8800",
            }).showToast();   
        @endif

        function disableOptions(evt) {
            var billingMethod = document.getElementById("billing").value;
            if(billingMethod == 'Bank') {
                    document.getElementById("billingCell").disabled = true; 
                    document.getElementById("bankNumDropDown").disabled = false; 
                    document.getElementById("accountNumber").disabled = false; 
                    document.getElementById("bankBranchDropDown").disabled = false; 
                    document.getElementById("accountType").disabled = false; 
            } else {
                    document.getElementById("billingCell").disabled = false; 
                    $("#bankName").prop('disabled', true);
                    document.getElementById("bankNumDropDown").disabled = true; 
                    document.getElementById("bankBranchDropDown").disabled = true; 
                    document.getElementById("accountNumber").disabled = true; 
                    document.getElementById("accountType").disabled = true; 
            }
            
         /*    if (billingMethod === 'MyZaka'){
                    document.getElementById("billingCell").disabled = false; 
                    document.getElementById("bankName").disabled = true; 
                    document.getElementById("branchCode").disabled = true; 
                    document.getElementById("accountNumber").disabled = true; 
               }
            if (billingMethod === 'orangeMoney'){
                    document.getElementById("billingCell").disabled = false; 
                    document.getElementById("bankName").disabled = true; 
                    document.getElementById("branchCode").disabled = true; 
                    document.getElementById("accountNumber").disabled = true; 
               } */
            }
        function disableOmang(evt){
            document.getElementById("omang").disabled = true; 
        }
        function disablePassport(evt){
            document.getElementById("passport").disabled = true; 
            
            var charCode = (evt.which) ? evt.which : event.keyCode
				if (charCode > 31 && (charCode < 48 || charCode > 57))
					return false;
				return true;
        
        }
   
/* 
>>>>>>> e3995658d4aa280f47d0416abf5a2d4c6334bbfb
$.ajax({
        type: "GET",
        headers: {
            'Access-Control-Allow-Headers': '*',
            'Authorization':'Basic QkQ1MERGNUUhNDJCQS00NjoGLUFC4jItN0EzQjg3ODkwMzk0OjI0NDI='
        },
        url: 'https://www.imagin8.co.za/api/json/sandbox/evalue8/GetMakes',
        dataType: "json",
       
        success: function(data)
        {
            helpers.buildDropdown(
                jQuery.parseJSON(data),
                $('#dropdown'),
                'Select an option'
            );
          }
        }); */
        
        function requireOmangOrPass() {
            let omang = $("#omang");
            let pass = $("#passport");

            if(omang.val().trim()=="") {
                pass.attr("required", "")
            } else if(pass.val().trim()=="") {
                pass.attr("required", "")
                omang.attr("required", "")
            } else {
                pass.attr("required", "")
            }
        }

        requireOmangOrPass();

    </script>

     


    </body>
    <!-- end::Body -->
</html>