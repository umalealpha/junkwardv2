<!DOCTYPE html>
<html lang="en" >

@include('admin.layouts.header')
<link href="{{  asset('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ asset('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css') }}" rel="stylesheet" type="text/css" />

<!-- begin::Body -->
<body class="kt-header--fixed kt-header-mobile--fixed kt-subheader--enabled kt-subheader--transparent kt-aside--enabled kt-aside--fixed kt-aside--minimize kt-page--loading" >
<!-- begin:: Header Mobile -->
<div id="kt_header_mobile" class="kt-header-mobile kt-header-mobile--fixed " >
    <div class="kt-header-mobile__logo">
        <a>
            <img alt="Logo" src="{{asset('images/logo.png')}}"/>
        </a>
    </div>
    <div class="kt-header-mobile__toolbar">
        <button class="kt-header-mobile__toolbar-toggler kt-header-mobile__toolbar-toggler--left" id="kt_aside_mobile_toggler"><span></span></button>
        <button class="kt-header-mobile__toolbar-toggler" id="kt_header_mobile_toggler"><span></span></button>
        <button class="kt-header-mobile__toolbar-topbar-toggler" id="kt_header_mobile_topbar_toggler"><i class="flaticon-more"></i></button>
    </div>
</div>
<!-- end:: Header Mobile -->
<!-- begin:: Root -->
<div class="kt-grid kt-grid--hor kt-grid--root">
    <!-- begin:: Page -->
    <div class="kt-grid__item kt-grid__item--fluid kt-grid kt-grid--ver kt-page">

        @include('admin.layouts.sidebar')
        @include('admin.layouts.topNav')

    </div>
    <div class="kt-grid__item kt-grid__item--fluid kt-grid kt-grid--hor">
        <!-- begin:: Subheader -->
        <div class="kt-subheader kt-grid__item" id="kt_subheader">
            <div class="kt-subheader__main">
                <h3 class="kt-subheader__title">
                    Edit User
                </h3>
                <span class="kt-subheader__separator kt-hidden"></span>
                <div class="kt-subheader__breadcrumbs">
                    <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                    <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin-dashboard')}}" class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{  URL::to('admin/user') }}" class="kt-subheader__breadcrumbs-link"> User </a> <span class="kt-subheader__breadcrumbs-separator"></span>
                    <span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">Edit</span>
                </div>
            </div>
        </div>
        <!-- end:: Subheader -->
        <!-- begin:: Content -->
        <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
            <!--begin::Portlet-->
            <div class="kt-portlet">
                <!--begin::Form-->
            <div class="kt-portlet__body" style="padding-top:0px" >
                <div class="row">
                    <div class="kt-portlet kt-portlet--tabs">
                        <div class="kt-portlet__head">
                            <div class="kt-portlet__head-label">
                                <h3 class="kt-portlet__head-title">Profile Account
                                    @if($user->active == null)
                                        <span class="kt-badge  kt-badge--info kt-badge--inline kt-badge--pill">Account Not-Active</span>
                                    @elseif($user->active == 1)
                                        <span class="kt-badge  kt-badge--success kt-badge--inline kt-badge--pill">Account Active</span>
                                    @elseif($user->active == 2)
                                        <span class="kt-badge  kt-badge--warning kt-badge--inline kt-badge--pill">Account Suspended</span>
                                    @endif
                                </h3>
                            </div>
                            <div class="kt-portlet__head-toolbar">
                                <ul class="nav nav-tabs nav-tabs-line nav-tabs-line-brand nav-tabs-line-2x nav-tabs-line-right nav-tabs-bold" role="tablist">
                                    <li class="nav-item">
                                        <a class="nav-link active" data-toggle="tab" href="#kt_portlet_base_demo_3_1_personal_info" role="tab">Personal Info</a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" data-toggle="tab" href="#kt_portlet_base_demo_3_5_change_profile_picture" role="tab">Change Profile Picture</a>
                                    </li>
                                    @if(auth::user()->hasPermissionTo('password-reset')/* && $userRole != "Super Admin"*/)
                                    <li class="nav-item">
                                        <a class="nav-link" data-toggle="tab" href="#kt_portlet_base_demo_3_2_change_password" role="tab">Change Password </a>
                                    </li>
                                    @endif
                                    <li class="nav-item">
                                        <a class="nav-link" data-toggle="tab" href="#kt_portlet_base_demo_3_2_change_pin" role="tab">Change Pin </a>
                                    </li>
                                   {{--  <li class="nav-item">
                                        <a class="nav-link" data-toggle="tab" href="#kt_portlet_base_demo_3_3_claims_content" role="tab">General Interests and Skills </a>
                                    </li> --}}
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
                <div class="row">
                    <div class="col-3 pd-0">
                            <div class="kt-portlet__body">
                                  <div class="kt-user-card__wrapper text-center">
                                        <div class="kt-user-card__pic">
                                            @if(isset($user->profile->profile_photo) && $user->profile->profile_photo != null)

                                                     <img class="mt-5 rounded border border-light" src="{{\AlphaDirect\Helper::getCloudFrontURL($user->profile->profile_photo)}}" style="height:25%;width:55%;border-radius: 50%!important;margin: 0 auto;" >


                                            @else
                                                @if(isset($userProfile) && $userProfile->gender == '0')
                                                    <img alt="Pic" style="height:50%;width:50%;border-radius: 50%!important;margin: 0 auto;" src="http://cbs.iiit.ac.in/wp-content/uploads/2017/11/blank_profile_female-1.jpg" />
                                                @else
                                                <img alt="Pic" style="height:50%;width:50%;border-radius: 50%!important;margin: 0 auto;" src="https://www.instituteofphotography.in/wp-content/uploads/2015/05/dummy-profile-pic.jpg" />
                                                @endif
                                            @endif
                                        </div>
                                        <div class="kt-user-card__details mt-4">
                                                <h3>{{ $user->firstName }}  {{ $user->lastName }}</h3>
                                                <h4 class="mt-2 kt-font-info">@if(isset($user->profile->work_position)) {{ $user->profile->work_position }} @endif</h4>
                                                <h5><i class="fas fa-envelope"></i> {{ $user->email }}</h5>
                                                <h5><i class="mt-2  fas fa-mobile-alt"></i>@if(isset($user->profile->cellphone)) {{ $user->profile->cellphone }} @endif</h5>

                                        </div>
                                  </div>
                            </div>

                    </div>
                    <div class="col-9">
                        <div class="kt-portlet__body" style="padding-top:0px" >
                            <div class="tab-content">
                                 <div class="tab-pane active" id="kt_portlet_base_demo_3_1_personal_info" role="tabpanel">
                                        <div class="kt-portlet" id="labelDiv">
                                            <form id="userEdit" action="{{ route('admin.user.update', $user->id) }}" method="POST" enctype="multipart/form-data" class="kt-form">                                                                 <!-- CSRF Token -->
                                                <input type="hidden" name="_method" value="PUT">
                                                     <!-- CSRF Token -->
                                                    <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                                                        <div class="kt-portlet__body">
                                                            <div class="form-group row">
                                                                <label for="example-text-input" class="col-3 col-form-label">First Name</label>
                                                                <div class="col-9">
                                                                    <input type="text" class="form-control" name="first_name"  value="{{ $user->firstName }}" placeholder="Enter first name">
                                                                        <span class="form-text text-muted"></span>
                                                                        @if ($errors->has('first_name'))
                                                                            <span class="col error">
                                                                                <p class="text text-danger">{{ $errors->first('first_name') }}</p>
                                                                            </span>
                                                                        @endif
                                                                </div>
                                                            </div>

                                                            <div class="form-group row">
                                                                <label for="example-text-input" class="col-3 col-form-label">Last Name</label>
                                                                    <div class="col-9">
                                                                        <input type="text" class="form-control" name="last_name" value="{{ $user->lastName }}" placeholder="Enter last name">
                                                                        <span class="form-text text-muted"></span>
                                                                        @if ($errors->has('last_name'))
                                                                            <span class="col error">
                                                                                <p class="text text-danger">{{ $errors->first('last_name') }}</p>
                                                                            </span>
                                                                        @endif
                                                                    </div>
                                                            </div>

                                                            <div class="form-group row">
                                                                    <label for="example-text-input" class="col-3 col-form-label">Email</label>
                                                                    <div class="col-9">
                                                                        <input type="text" class="form-control emailField" id="cutomer_email" name="cutomer_email"  value="{{ $user->email }}" placeholder="Enter email">
                                                                        <span class="form-text text-muted"></span>
                                                                        <input class="hiddenEmail" type="hidden" value="{{ $user->email }}" >
                                                                        <span id="emailErrorMsg" style="color: red;display: none">Email already exists.</span>
                                                                        @if ($errors->has('email'))
                                                                            <span class="col error">
                                                                                <p class="text text-danger">{{ $errors->first('email') }}</p>
                                                                            </span>
                                                                         @endif
                                                                    </div>
                                                                </div>

                                                            <div class="form-group row">
                                                                <label for="example-date-input" class="col-3 col-form-label">Date of Birth</label>
                                                                    <div class="form-group col-9">
                                                                        <input type="text" class="form-control  kt_datepicker_1 restrictDate" @if(isset($userProfile->dob)) value="{{ $userProfile->dob }}" @else  value="" @endif autocomplete="off" id="example-date-input" name="dob">
                                                                        @if ($errors->has('dob'))
                                                                                <span class="col-12 text text-danger">
                                                                                    {{ $errors->first('dob') }}
                                                                                 </span>
                                                                        @endif
                                                                    </div>
                                                            </div>

                                                                 <div class="form-group row">
                                                                    <label for="example-text-input" class="col-3 col-form-label">Department</label>
                                                                    <div class="col-9">
                                                                        <select class="form-control kt_selectpicker" title="Please select department" data-live-search="true" name="dept" required>
                                                                            @foreach($depts as $dept)
                                                                                <option value="{{$dept->id}}" @if(isset($userProfile->department_id) && $userProfile->department_id == $dept->id) selected @endif>{{$dept->name}}</option>
                                                                            @endforeach
                                                                        </select>
                                                                    </div>
                                                                </div>
                                                                <div class="form-group row">
                                                                    <label for="example-text-input" class="col-3 col-form-label">Company</label>
                                                                    <div class="col-9">
                                                                        <select class="form-control kt_selectpicker" title="Please select Company" name="company_id" >
                                                                        <option value="">Please select Company</option>
                                                                        @if(isset($company))
                                                                            @foreach($company as $k)
                                                                                <option value="{{$k->id}}" @if(isset($user->company_id) && $user->company_id == $k->id) selected @endif>{{$k->name}}</option>
                                                                            @endforeach
                                                                         @endif   
                                                                        </select>
                                                                    </div>
                                                                </div>
                                                           @if(\Illuminate\Support\Facades\Auth::user()->hasPermissionTo('user-agencies_update'))
                                                            <div class="form-group row">
                                                                <label for="example-text-input" class="col-3 col-form-label">Agency</label>
                                                                <div class="col-9">
                                                                    <select class="form-control kt_selectpicker" title="Please select agency" data-live-search="true" name="agency" required>
                                                                        @foreach($agency as $ag)
                                                                                <option value="{{$ag->id}}" @if($user->agency_id == $ag->id) selected @endif @if($ag->status != 1) disabled @endif>{{$ag->name}}</option>
                                                                        @endforeach
                                                                    </select>
                                                                </div>
                                                            </div>
                                                            @endif

                                                                @if(\Illuminate\Support\Facades\Auth::user()->hasPermissionTo('user-update_role'))
                                                                    <div class="form-group row">
                                                                        <label for="example-text-input" class="col-3 col-form-label">Roles</label>
                                                                        <div class="col-9">
                                                                            <select class="form-control selectpicker kt_selectpicker" title="Please select role"
                                                                                    data-live-search="true" multiple name="role[]" required>

                                                                                @foreach($roles as $role)
                                                                                       <option value="{{$role->name}}" @if(in_array($role->name,$userRole)) selected @endif>{{$role->name}}</option>
                                                                                @endforeach

                                                                            </select>
                                                                        </div>
                                                                    </div>
                                                                @endif

                                                                    <div class="form-group row">
                                                                            <label for="example-text-input" class="col-3 col-form-label">User Account Status</label>
                                                                            <div class="col-9">
                                                                            <select class="form-control kt_selectpicker" title="User account status"
                                                                                    data-live-search="true" name="active" >
                                                                                    @if($user->active == null)
                                                                                        <option value="{{ $user->active }}"selected disabled>Account not Active</option>
                                                                                    @elseif($user->active == 1)
                                                                                         <option value="{{ $user->active }}" selected disabled>Active</option>
                                                                                    @elseif($user->active == 2)
                                                                                    <option value="{{ $user->active }}" selected disabled>Suspended</option>
                                                                                    @endif
                                                                               <option @if($user->active == '') selected @endif value="">Non-Active</option>
                                                                               <option @if($user->active == 1) selected @endif value="1">Active</option>
                                                                               <option @if($user->active == 2) selected @endif value="2">Suspend</option>

                                                                            </select>

                                                                            </div>
                                                                        </div>


                                                                    <div class="form-group row">
                                                                            <label for="example-text-input" class="col-3 col-form-label">Omang</label>
                                                                            <div class="col-9">
                                                                                <input type="text" class="form-control validateGroup1" name="omang"  @if(isset($userProfile->omang)) value="{{  $userProfile->omang }}" @else value="" @endif placeholder="Enter Omang" onkeypress="return isNumberKey(event)"  pattern="[0-9]{9}" maxlength="9">
                                                                                <span class="form-text text-muted"></span>
                                                                                <p class="OmangError" style="color:#e61c30;display:none;" >Please Input numbers only</p>
                                                                                @if ($errors->has('omang'))
                                                                                    <span class="col-12 text text-danger">
                                                                                        {{ $errors->first('omang') }}
                                                                                    </span>
                                                                                 @endif
                                                                            </div>
                                                                        </div>

                                                                    <div class="form-group row">
                                                                                <label for="example-text-input" class="col-3 col-form-label">Address</label>
                                                                                <div class="col-9">
                                                                                    <input type="textarea" class="form-control" name="address" @if(isset($userProfile->address)) value="{{$userProfile->address  }}" @else value="" @endif placeholder="Enter address">
                                                                                    <span class="form-text text-muted"></span>
                                                                                </div>
                                                                            </div>

                                                                        <div class="form-group row">
                                                                            <label for="example-text-input" class="col-3 col-form-label">Passport</label>
                                                                            <div class="col-9">
                                                                                <input type="textarea" class="form-control validateGroup1" name="passport" @if(isset($userProfile->passport))  value="{{ $userProfile->passport }}" @else  value=""  @endif placeholder="Enter passport number">
                                                                                <span class="form-text text-muted"></span>
                                                                                @if ($errors->has('passport'))
                                                                                    <span class="col-12 text text-danger">
                                                                                        {{ $errors->first('passport') }}
                                                                                    </span>
                                                                                 @endif
                                                                            </div>

                                                                        </div>

                                                                        <div class="form-group row">
                                                                            <label for="example-text-input" class="col-3 col-form-label">Mobile No.</label>
                                                                            <div class="col-9">
                                                                                <input type="text" class="form-control" name="cellphone" @if(isset($userProfile->cellphone)) value="{{  $userProfile->cellphone }}" @else value="" @endif  placeholder="Enter mobile no.">
                                                                                <span class="form-text text-muted"></span>
                                                                                @if ($errors->has('cellphone'))
                                                                                <span class="col-12 text text-danger">
                                                                                    {{ $errors->first('cellphone') }}
                                                                                </span>
                                                                                @endif
                                                                            </div>
                                                                        </div>

                                                                        <div class="form-group row">
                                                                            <label class="col-3 col-form-label">Gender</label>
                                                                            <div class="col-9">
                                                                                    <div class="kt-radio-inline">
                                                                                        <label class="kt-radio">
                                                                                            <input type="radio" name="gender" value="1" @if(isset($userProfile->gender) && $userProfile->gender == '1') checked @endif>Male <span></span>
                                                                                        </label>
                                                                                        <label class="kt-radio">
                                                                                            <input type="radio" name="gender" value="0" @if(isset($userProfile->gender) && $userProfile->gender == '0') checked @endif>Female <span></span>
                                                                                        </label>
                                                                                    </div>
                                                                            </div>
                                                                        </div>

                                                                        <div class="form-group row">
                                                                            <label for="example-text-input" class="col-3 col-form-label">Commission</label>
                                                                            <div class="col-9">
                                                                                <span class="kt-switch">
                                                                                <label>
                                                                                    <input id="switchValue" type="checkbox" @if($user->commission)
                                                                                    checked="checked" @endif name="commission" value="0"
                                                                                    onchange="statusMsg()">
                                                                                    <span style="margin-top: 10px;margin-left: 10px;"></span>
                                                                                    @if($user->commission)
                                                                                    <h4 id="switchMsg"
                                                                                        style="display:inline;float:left;margin-top: 14px;margin-left: 5px;color:cornflowerblue">
                                                                                        Yes</h4>
                                                                                    @else
                                                                                    <h4 id="switchMsg"
                                                                                        style="display:inline;float:left;margin-top: 14px;margin-left: 5px;color:#ff4d4d">
                                                                                        No</h4>
                                                                                    @endif
                                                                                </label>
                                                                                </span>
                                                                            </div>
                                                                        </div>

                                                            @if(auth()->user()->hasRole('Super Admin')|| auth()->user()->can('access-Graphite Login Option'))
                                                                <div class="form-group row">
                                                                    <label for="example-text-input" class="col-3 col-form-label">Allow Graphite Login</label>
                                                                    <div class="col-9">
                                    <span class="kt-switch">
                                        <label>
                                            <input id="loginValue" type="checkbox" @if($user->is_graphite_login)
                                            checked="checked" @endif name="graphite_login"
                                                   value="1" onchange="loginMsg()">
                                            <span style="margin-top: 10px;margin-left: 10px;"></span>
                                            @if($user->is_graphite_login)
                                            <h4 id="loginMsg"
                                                style="display:inline;float:left;margin-top: 14px;margin-left: 5px;color:cornflowerblue;">
                                                Yes</h4>
                                            @else
                                                <h4 id="loginMsg"
                                                    style="display:inline;float:left;margin-top: 14px;margin-left: 5px;color:#ff4d4d;">
                                                No</h4>
                                            @endif
                                        </label>
                                    </span>
                                                                    </div>
                                                                </div>
                                                            @endif

                                                            @if(auth()->user()->hasRole('Super Admin')|| auth()->user()->can('access-Reports Login Option'))
                                                                <div class="form-group row">
                                                                    <label for="example-text-input" class="col-3 col-form-label">Allow Reporting Dashboard Login</label>
                                                                    <div class="col-9">
                                    <span class="kt-switch">
                                        <label>
                                            <input id="reportValue" type="checkbox" @if($user->is_report_login)
                                            checked="checked" @endif name="report_login"
                                                   value="1" onchange="reportMsg()">
                                            <span style="margin-top: 10px;margin-left: 10px;"></span>
                                            @if($user->is_report_login)
                                                <h4 id="reportMsg"
                                                    style="display:inline;float:left;margin-top: 14px;margin-left: 5px;color:cornflowerblue;">
                                                Yes</h4>
                                                @else
                                                <h4 id="reportMsg"
                                                    style="display:inline;float:left;margin-top: 14px;margin-left: 5px;color:#ff4d4d">
                                                No</h4>
                                                @endif
                                        </label>
                                    </span>
                                    </div>
                                </div>
                            @endif

                            <div class="form-group row">
                                <label for="example-text-input" class="col-3 col-form-label">Allow bypass 500k validation</label>
                                <div class="col-9">
                                    <span class="kt-switch">
                                        <label>
                                            <input id="bypassValue" type="checkbox" @if($user->bypass_500k)
                                            checked="checked" @endif name="bypass_500k"
                                                value="1" onchange="bypass()">
                                            <span style="margin-top: 10px;margin-left: 10px;"></span>
                                            @if($user->bypass_500k)
                                                <h4 id="bypassMsg"
                                                    style="display:inline;float:left;margin-top: 14px;margin-left: 5px;color:cornflowerblue;">
                                                Yes</h4>
                                            @else
                                                <h4 id="bypassMsg"
                                                    style="display:inline;float:left;margin-top: 14px;margin-left: 5px;color:#ff4d4d">
                                                No</h4>
                                            @endif
                                        </label>
                                    </span>
                                </div>
                            </div>

                            <div class="form-group row">
                                <label for="example-text-input" class="col-3 col-form-label">Allow to create high risk customer quote</label>
                                <div class="col-9">
                                    <span class="kt-switch">
                                        <label>
                                            <input id="high_risk_quote" type="checkbox" @if($user->create_high_risk_quote)
                                            checked="checked" @endif name="create_high_risk_quote"
                                                value="1" onchange="createHighRiskQuote()">
                                            <span style="margin-top: 10px;margin-left: 10px;"></span>
                                            @if($user->create_high_risk_quote)
                                                <h4 id="highRiskQuoteMsg"
                                                    style="display:inline;float:left;margin-top: 14px;margin-left: 5px;color:cornflowerblue;">
                                                Yes</h4>
                                            @else
                                                <h4 id="highRiskQuoteMsg"
                                                    style="display:inline;float:left;margin-top: 14px;margin-left: 5px;color:#ff4d4d">
                                                No</h4>
                                            @endif
                                        </label>
                                    </span>
                                </div>
                            </div>
                            {{-- WhatsApp AI Access Control --}}
                            @if(auth()->user()->hasRole('Super Admin') || auth()->user()->hasRole('admin'))
                            <div class="form-group row" style="border-top:1px solid #f0f0f0;padding-top:16px;margin-top:8px;">
                                <label class="col-3 col-form-label font-weight-bold">
                                    <i class="fab fa-whatsapp" style="color:#25d366"></i> WhatsApp AI Access
                                </label>
                                <div class="col-9">
                                    <select class="form-control" name="whatsapp_access" style="width:200px;display:inline-block;">
                                        <option value="" @if(!isset($userProfile->whatsapp_access) || $userProfile->whatsapp_access === null) selected @endif>— Not set —</option>
                                        <option value="YES" @if(isset($userProfile->whatsapp_access) && $userProfile->whatsapp_access === 'YES') selected @endif>YES — Allow</option>
                                        <option value="NO"  @if(isset($userProfile->whatsapp_access) && $userProfile->whatsapp_access === 'NO')  selected @endif>NO — Block</option>
                                    </select>
                                    <span class="form-text text-muted" style="display:inline;margin-left:10px;">Allow or block this user from the WhatsApp AI bot.</span>
                                </div>
                            </div>
                            <div class="form-group row">
                                <label class="col-3 col-form-label font-weight-bold">
                                    <i class="fab fa-whatsapp" style="color:#25d366"></i> WhatsApp Visibility
                                </label>
                                <div class="col-9">
                                    <select class="form-control" name="whatsapp_visibility" style="width:220px;display:inline-block;">
                                        <option value="" @if(!isset($userProfile->whatsapp_visibility) || $userProfile->whatsapp_visibility === null) selected @endif>— Not set —</option>
                                        <option value="Admin"            @if(isset($userProfile->whatsapp_visibility) && $userProfile->whatsapp_visibility === 'Admin')            selected @endif>Admin — Full BI + DevOps</option>
                                        <option value="Full"             @if(isset($userProfile->whatsapp_visibility) && $userProfile->whatsapp_visibility === 'Full')             selected @endif>Full — All business data</option>
                                        <option value="Only department"  @if(isset($userProfile->whatsapp_visibility) && $userProfile->whatsapp_visibility === 'Only department')  selected @endif>Only department — Dept data only</option>
                                        <option value="Self"             @if(isset($userProfile->whatsapp_visibility) && $userProfile->whatsapp_visibility === 'Self')             selected @endif>Self — Own data only</option>
                                    </select>
                                    <span class="form-text text-muted" style="display:inline;margin-left:10px;">Controls what data the user can query via WhatsApp AI.</span>
                                </div>
                            </div>
                            @endif

                            {{-- @if(auth()->user()->hasRole('Super Admin'))
                            <div class="form-group row">
                                <label class="col-3 col-form-label">User Permissions</label>
                                <div class="col-9">
                                    <div class="col-md-4 kt-radio-inline @error('cellphone') is-invalid @enderror">
                                        @isset($userpermissions)
                                            @foreach($userpermissions as $userpermission)
                                                <input type="checkbox" name="UserPermissins[]" value="{{ $userpermission['name'] }}"
                                                 {{ $userpermission['checked'] }}>
                                                 <label for="vehicle1" style="margin-left: 2px;">
                                                    {{ $userpermission['name'] }}
                                                </label><br>
                                            @endforeach
                                        @endisset

                                    </div>
                                </div>
                            </div>
                            @endif --}}
                        @if(auth()->user()->hasRole('Super Admin'))
                            <div class="col-xs-12 col-sm-12 col-md-12">
                                <div class="form-group">
                                    <label class="col-form-label pt-0" for="permission"><strong>User Permissions: </strong></label>
                                    @isset($permission)
                                        @foreach($permission->groupBy('category') as $category => $group)
                                        <h6>{!! ($category) !!}</h6>
                                        <div class="kt-checkbox-inline">
                                            @foreach($group as $item)
                                            @php
                                            $exists = !empty(array_filter($userpermissions, function ($per) use ($item) {
                                                return $per['id'] === $item['id'] && $per['checked'] !== null;
                                            }));
                                            @endphp
                                           
                                                <label class="kt-checkbox checkbox" style="margin-bottom: 30px;">
                                                    {{ Form::checkbox('UserPermissins[]', $item['id'], in_array($item['id'], $role_permissions) || $exists ? true : false, array('class' => 'name')) }}
                                                    {!! explode("-", $item['name'])[count(explode("-", $item['name']))-1] !!}
                                                    <span></span>
                                                </label>
                                            @endforeach
                                        </div>
                                        @endforeach
                                    @endisset
                                </div>
                            </div>
                        @endif

                            </div>
                            <div class="kt-portlet__foot kt-portlet__foot--solid">
                                <div class="kt-form__actions">
                                    <div class="row">
                                        <div class="col-3"></div>
                                        <div class="col-9">

                                            <button type="submit" value="Submit" id="btn" class="btn btn-brand">Update</button>

                                            <button class="btn btn-brand" type="button" id="loadBtn" style="display:none" > <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Loading... </button>
                                            <a class="btn btn-secondary" href="{{ route('admin.user.index') }}" >Cancel</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            </form>
                        </div>

                        </div>
                                <div class="tab-pane" id="kt_portlet_base_demo_3_5_change_profile_picture" role="tabpanel">
                                    <form id="userEdit" action="{{ route('updateProfilePicture', $user->id) }}" method="POST" enctype="multipart/form-data" class="kt-form">
                                            <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                                        <div class="kt-portlet__body">
                                            <div class="form-group row">
                                                    <label for="example-text-input" class="col-3 col-form-label"></label>
                                                    <div class="col-9">
                                                        <div class="kt-avatar kt-avatar--circle" id="profile_picture">
                                                            @if(isset($user->profile->profile_photo) && \AlphaDirect\Helper::getCloudFrontURL($user->profile->profile_photo))
                                                                <div class="kt-avatar__holder" style="background-image:url({{  \AlphaDirect\Helper::getCloudFrontURL($user->profile->profile_photo) }})"></div>
                                                            @else
                                                                <div class="kt-avatar__holder" style="background-image:url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                                                            @endif
                                                                <label class="kt-avatar__upload" data-toggle="kt-tooltip" title="" data-original-title="Change Profile picture">
                                                                <i class="fa fa-pen"></i>
                                                                <input type="file" class="profile_picture" name="profile_picture">
                                                            </label>
                                                            <span class="kt-avatar__cancel" data-toggle="kt-tooltip" title="" data-original-title="Cancel avatar">
                                                                <i class="fa fa-times"></i>
                                                            </span>
                                                        </div>
                                                        <span class="col-12 text text-danger">
                                                            @if ($errors->has('profile_picture'))
                                                                <span class="col-12 text text-danger">
                                                                    {{ $errors->first('profile_picture') }}
                                                                </span>
                                                            @endif
                                                    </div>
                                                </div>
                                        </div>
                                        <div class="kt-portlet__foot kt-portlet__foot--solid">
                                                <div class="kt-form__actions">
                                                    <div class="row">
                                                        <div class="col-3"></div>
                                                        <div class="col-9">
                                                            @can('user-edit')
                                                            <button type="submit" value="Submit" id="btn" class="btn btn-brand">Update</button>
                                                            @endcan
                                                            <button class="btn btn-brand" type="button" id="loadBtn" style="display:none" > <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Loading... </button>
                                                            <a class="btn btn-secondary" href="{{ route('admin.user.index') }}" >Cancel</a>
                                                        </div>
                                                    </div>
                                                </div>
                                        </div>
                                    </form>
                                </div>
                                <div class="tab-pane" id="kt_portlet_base_demo_3_2_change_password" role="tabpanel">
                                    <form id="userEdit" action="{{ route('updatePassword', $user->id) }}" method="POST" enctype="multipart/form-data" class="kt-form">
                                                <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                                        <div class="kt-portlet__body" style="padding-top:0px !important">
                                            <p class="text text-warning">
                                                <i class="kt-menu__link-icon flaticon-warning"></i> If you don't want to change password... please leave them empty
                                            </p>
                                            {{-- <div class="form-group row">
                                                    <label for="example-text-input" class="col-3 col-form-label">Old Password</label>
                                                    <div class="col-9">
                                                        <input type="password" class="form-control" name="old_password"   placeholder="Enter previous password">
                                                        <span class="form-text text-muted"></span>
                                                        @if ($errors->has('old_password'))
                                                        <span class="col error">
                                                            <p class="text text-danger">{{ $errors->first('old_password') }}</p>
                                                        </span>
                                                        @endif
                                                    </div>
                                                </div> --}}
                                            <div class="form-group row">
                                                <label for="password" class="col-3 col-form-label">Password</label>
                                                <div class="col-7">
                                                    <input type="text" id="password"  class="form-control" name="password" autocomplete="off"  placeholder="Enter password">
                                                    <span class="form-text text-muted"></span>
                                                    @if ($errors->has('password'))
                                                    <span class="col error">
                                                        <p class="text text-danger">{{ $errors->first('password') }}</p>
                                                    </span>
                                                    @endif
                                                </div>
                                                <div class="col-2">
                                                    <span class="btn btn-brand" style="height: 40px;" id="passwordSuggestion">Suggestions</span>
                                                </div>
                                            </div>

                                            <div class="form-group row">
                                                <label for="example-text-input" class="col-3 col-form-label">Confirm Password</label>
                                                <div class="col-7">
                                                    <input type="text" class="form-control" name="confirm_password" id="confirm_password"  placeholder="Confirm password">
                                                    <span class="form-text text-muted"></span>
                                                    @if ($errors->has('confirm_password'))
                                                    <span class="col error">
                                                        <p class="text text-danger">{{ $errors->first('confirm_password') }}</p>
                                                    </span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                        <div class="kt-portlet__foot kt-portlet__foot--solid">
                                            <div class="kt-form__actions">
                                                <div class="row">
                                                    <div class="col-3"></div>
                                                    <div class="col-9">
                                                        @can('user-edit')
                                                        <button type="submit" value="Submit" id="btn" class="btn btn-brand">Update</button>
                                                        @endcan
                                                        <button class="btn btn-brand" type="button" id="loadBtn" style="display:none" > <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Loading... </button>
                                                        <a class="btn btn-secondary" href="{{ route('admin.user.index') }}" >Cancel</a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                                <div class="tab-pane" id="kt_portlet_base_demo_3_2_change_pin" role="tabpanel">
                                    <form id="userpin" action="{{ route('admin.user.userPinUpdate') }}" method="POST" enctype="multipart/form-data" class="kt-form">
                                        <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                                        <input type="hidden" name="id" value="{{ $user->id }}" />
                                        <div class="form-group row">
                                            <label for="example-text-input" class="col-3 col-form-label">Agent Pin:</label>
                                            <div class="col-5">
                                                <input type="text" class="form-control" name="pin" id="pin" value="{{ $user->pin }}"
                                                    placeholder="Enter pin" autocomplete="off">
                                            </div>
                                        </div>
                                        <div class="kt-portlet__foot kt-portlet__foot--solid">
                                            <div class="kt-form__actions">
                                                <div class="row">
                                                    <div class="col-3"></div>
                                                    <div class="col-9">

                                                        <button type="submit" value="Submit" id="btn" class="btn btn-brand">Update</button>

                                                        <button class="btn btn-brand" type="button" id="loadBtn" style="display:none" > <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Loading... </button>
                                                        <a class="btn btn-secondary" href="{{ route('admin.user.index') }}" >Cancel</a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!--end::Form-->
            </div>
            <!--end::Portlet-->
        </div>
        <!-- end:: Content -->
    </div>
    <!-- begin:: Footer -->
    @include('includes.footer')
    <!-- end:: Footer -->
</div>
<!-- end:: Wrapper -->

<!-- end:: Page -->

<!-- end:: Root -->



<!-- begin:: Scrolltop -->
<div id="kt_scrolltop" class="kt-scrolltop"> <i class="la la-arrow-up"></i> </div>
<!-- end:: Scrolltop -->

@include('admin.layouts.scripts')
<script src="{{  asset('assets/vendors/general/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}" type="text/javascript"></script>
<script src="{{  asset('assets/vendors/custom/components/vendors/bootstrap-datepicker/init.js') }}" type="text/javascript"></script>

{{--
<script src="{{ asset('assets/vendors/general/bootstrap-timepicker/js/bootstrap-timepicker.min.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/custom/components/vendors/bootstrap-timepicker/init.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/general/bootstrap-daterangepicker/daterangepicker.js') }}" type="text/javascript"></script>
--}}

<script src="{{ asset('assets/vendors/general/jquery-validation/dist/jquery.validate.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/custom/components/vendors/jquery-validation/init.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/general/jquery-validation/dist/additional-methods.js') }}" type="text/javascript"></script>
<script src="{{  asset('assets/vendors/general/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}" type="text/javascript"></script>
<script src="{{  asset('assets/vendors/custom/components/vendors/bootstrap-datepicker/init.js') }}" type="text/javascript"></script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap-select@1.13.14/dist/js/bootstrap-select.min.js"></script>

<script>
    "use strict";
    // Class definition

    var KTFormControls = function () {

        // Private functions
        jQuery.validator.addMethod("uploadFile", function (val, element) {
            var size = element.files[0].size;
            console.log(size);
            if (size > 1048576)// checks the file more than 1 MB
            {
                return false;
            } else {
                return true;
            }
        }, "File size too large, should be less than 1MB");


        var demo1 = function () {
            $( "#userEdit" ).validate({
            // define validation rules
                rules: {
                    first_name: {
                        required: true,
                    },
                    last_name: {
                        required: true,
                    },
                    cutomer_email: {
                        required: false,
                        cutomer_email:true,
                    },
                   /*  cellphone: {
                        required: true,
                    }, */
                    omang: {
                        require_from_group: [1, '.validateGroup1'],
                    },
                    passport: {
                        require_from_group: [1, '.validateGroup1'],
                    },
                    profile_picture:{
                       // required: true,
                        extension:'jpe?g,png',
                        uploadFile:true,

                    },
                    password: {
                        minlength:6,
                        maxlength:12,
                    },
                    confirm_password:{
                        minlength : 6,
                        maxlength:12,
                        equalTo : "#password"
                    },

                },
                groups: {
                    validateGroup1: "omang passport",
                },
                messages:{
                    first_name: {
                        required: "Enter first Name",
                    },
                    last_name: {
                        required: "Enter Last Name",
                    },
                    email: {
                        required: "Enter email",
                        email:true
                    },

                    cellphone: {
                        required: "Enter phone number",
                    },
                    omang: {
                        require_from_group: "Please provide either your Omang Id or Passport",
                    },
                    passport: {
                        require_from_group: "Please provide either your Omang Id or Passport",
                    },
                    profile_picture:{
                        extension:"Image format not supported",
                    },
                    password: {
                        minlength:"Password should at least be 6 characters long",
                        maxlength:"Password should not be more than 12 characters long",
                    },
                    confirm_password:{
                        minlength : "Confirm Password should at least be 6 characters long",
                        equalTo : "Confirm password and Password should match"
                    },
                },

                invalidHandler: function(event, validator) {
                    $('#btn').show();
                    KTUtil.scrollTo("#userEdit", -200);
                },

                submitHandler: function (form) {
                    form[0].submit(); // submit the form
                }
            });
        }

        return {
// public functions
            init: function() {
                demo1();
            }
        };
    }();

    jQuery.validator.addMethod("cutomer_email", function(value, element) {
            return this.optional( element ) || /^([a-zA-Z0-9_\.\-])+\@(([a-zA-Z0-9\-])+\.)+([a-zA-Z0-9]{2,4})+$/.test( value );
        }, 'Sorry ! This cutomer email is not valid');

      // Avatar Class definition
      var KTAvatarDemo = function() {

            return {
                // Init demos
                init: function() {
                    var avatar1 = new KTAvatar('profile_picture');
                }
            };
            }();
    jQuery(document).ready(function() {
         KTFormControls.init();
        KTAvatarDemo.init();


        $('#passwordSuggestion').on('click',function() {

            $('#password').val('');
            $('#confirm_password').val('');

            var ajaxRequest = setTimeout(function(sn) {
                $.ajax({
                    url: '{{ route('admin.user.generatePassword') }}',
                    data: {
                        "_token": "{{ csrf_token() }}",
                    },
                    type: 'post',
                    datatype : 'json',
                    success: function (data) {
                        $('#password').val(data);
                        $('#confirm_password').val(data);
                    }
                });
            }, 200);
        });
    });

    function bypass(){
        var isChecked=document.getElementById("bypassValue").checked;
        if (isChecked){
            document.getElementById("bypassMsg").innerHTML="Yes";
            document.getElementById("bypassMsg").style.color="cornflowerblue";
            document.getElementById("bypassValue").value = 1;
        }
        else {
            document.getElementById("bypassMsg").innerHTML="No";
            document.getElementById("bypassMsg").style.color="#ff4d4d";
            document.getElementById("bypassValue").value = 0;
        }

    }

    function createHighRiskQuote(){
        var isChecked=document.getElementById("high_risk_quote").checked;
        if (isChecked){
            document.getElementById("highRiskQuoteMsg").innerHTML="Yes";
            document.getElementById("highRiskQuoteMsg").style.color="cornflowerblue";
            document.getElementById("high_risk_quote").value = 1;
        }
        else {
            document.getElementById("highRiskQuoteMsg").innerHTML="No";
            document.getElementById("highRiskQuoteMsg").style.color="#ff4d4d";
            document.getElementById("high_risk_quote").value = 0;
        }

    }

    function loginMsg(){
        var isChecked=document.getElementById("loginValue").checked;
        if (isChecked){
            document.getElementById("loginMsg").innerHTML="Yes";
            document.getElementById("loginMsg").style.color="cornflowerblue";
            document.getElementById("loginValue").value = 1;
        }
        else {
            document.getElementById("loginMsg").innerHTML="No";
            document.getElementById("loginMsg").style.color="#ff4d4d";
            document.getElementById("loginValue").value = 0;
        }

    }
    function reportMsg(){
        var isChecked=document.getElementById("reportValue").checked;
        if (isChecked){
            document.getElementById("reportMsg").innerHTML="Yes";
            document.getElementById("reportMsg").style.color="cornflowerblue";
            document.getElementById("reportValue").value = 1;
        }
        else {
            document.getElementById("reportMsg").innerHTML="No";
            document.getElementById("reportMsg").style.color="#ff4d4d";
            document.getElementById("reportValue").value = 0;
        }
    }
</script>

<script>
     var KTBootstrapDatepicker = function () {
        var arrows;
        if (KTUtil.isRTL()) {
            arrows = {
                leftArrow: '<i class="la la-angle-right"></i>',
                rightArrow: '<i class="la la-angle-left"></i>'
            }
        } else {
            arrows = {
                leftArrow: '<i class="la la-angle-left"></i>',
                rightArrow: '<i class="la la-angle-right"></i>'
            }
        }
        // Private functions
        var demos = function () {
            // minimum setup
            $('.kt_datepicker_1').datepicker({
                rtl: KTUtil.isRTL(),
                templates: arrows,
                /* todayHighlight: true,
                minDate: new Date(1900,1-1,1),  */
                maxDate: '-18Y',
                orientation: "bottom left",
                endDate: '-18y',
                startDate: '-110y',
                format: "yyyy-mm-dd",
                changeMonth: true,
                changeYear: true,
                defaultDate: null,
                yearRange: "-100:+0",
                autoclose:true
            });
        }
        return {
            // public functions
            init: function() {
                demos();
            }
        };
    }();
    jQuery(document).ready(function() {
        KTBootstrapDatepicker.init();
    });

$('#userEdit').submit(function(){
   var pass = $('#password').val();
   var cpass = $('#confirm_password').val();
   if( pass && cpass != null){
       if(pass != cpass){
           $('span.passMsg').show();
           $('span.cpassMsg').show();
           $("button#subbtn").css("display", "inline");
           $("button#LoadBtn").css("display", "none");
           $('html, body').animate({
               scrollTop: $("#last_name").offset().top
           }, 500);
               return false;
       }
       else{
           $("button#subbtn").css("display", "none");
           $("button#LoadBtn").css("display", "inline");
           return true;
       }
   }
});

    $('#password,#confirm_password').click(function() {
        $('span.passMsg').hide();
        $('span.cpassMsg').hide();
    });

    function isNumberKey(evt)
    {
        var charCode = (evt.which) ? evt.which : evt.keyCode;
        if (charCode != 46 && charCode > 31
                && (charCode < 48 || charCode > 57)){
                    $('.OmangError').show();
                    return false;
                }

        return true;
    }

    /* $('.restrictDate').datepicker({
        rtl: KTUtil.isRTL(),
        templates: arrows,
        /* todayHighlight: true,
        minDate: new Date(1900,1-1,1),
        maxDate: '-18Y',
        orientation: "bottom left",
        endDate: '-18y',
        startDate: '-110y',
        dateFormat: "yyyy-mm-dd",
        changeMonth: true,
        changeYear: true,
        defaultDate: null,
        yearRange: "-100:+0",
        autoclose:true

    }); */

    $(document).ready(function() {
        var ajaxRequest;
        $('.emailField').on('keyup',function() {
            var email = $(this).val();
            var oldEmail = $('.hiddenEmail').val();
            if(email != oldEmail){
                ajaxRequest = setTimeout(function(sn) {
                    $.ajax({
                        url: '{{ route('admin.user.checkEmail') }}',
                        data: {
                            "_token": "{{ csrf_token() }}",
                            "email": email,
                        },
                        type: 'post',
                        datatype : 'json',
                        success: function (data) {
                            if(data.count > 0) {
                                $('#emailErrorMsg').css('display','block');
                                $("#btn").attr("disabled", true);
                            } else {
                                $('#emailErrorMsg').css('display','none');
                                $("#btn").attr("disabled", false);
                            }
                        }
                    });
                }, 200);
            }

        });
    });

</script>
<script>
//    if($("#switchValue").val() == 1){
//         $(this).prop( "checked", true );
//         document.getElementById("switchMsg").innerHTML="Active";
//             document.getElementById("switchMsg").style.color="cornflowerblue";
//             document.getElementById("switchValue").value = 1;
//     }
//     else{
//         $(this).prop( "checked", false );
//         document.getElementById("switchMsg").innerHTML="Inactive";
//             document.getElementById("switchMsg").style.color="#ff4d4d";
//             document.getElementById("switchValue").value = 0;
//     }

    function statusMsg(){
        var isChecked=document.getElementById("switchValue").checked;
        if (isChecked){
            document.getElementById("switchMsg").innerHTML="Active";
            document.getElementById("switchMsg").style.color="cornflowerblue";
            document.getElementById("switchValue").value = 1;
        }
        else {
            document.getElementById("switchMsg").innerHTML="Inactive";
            document.getElementById("switchMsg").style.color="#ff4d4d";
            document.getElementById("switchValue").value = 0;
        }

    }
</script>
<script>
        "use strict";
    // Class definition

    var KTFormControls = function () {
// Private functions

        var demopin = function () {
            $( "#userpin" ).validate({
// define validation rules
                rules: {
                    pin: {
                        required: true,
                        number:true,
                        minlength: 4,
                        maxlength: 4,
                    }
                   },
                messages:{
                    pin:{
                        required: "Enter Pin",
                        number:"Pin should be number",
                        minlength:"Pin should at least be 4 characters long",
                        maxlength:"pin should not be more than 4 characters long",
                    }

                },

                submitHandler: function (form) {
                    /* $('#btn').hide();
                     $('#loadBtn').show();*/
                    form[0].submit(); // submit the form
                }
            });
        }

        return {
// public functions
            init: function() {
                demopin();
            }
        };
    }();

    </script>
</body>
<!-- end::Body -->
</html>
