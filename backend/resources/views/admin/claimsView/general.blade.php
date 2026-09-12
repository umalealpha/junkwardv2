<!DOCTYPE html>
<html lang="en" >
@include('admin.layouts.header')
<link href="{{  asset('assets/vendors/custom/datatables/datatables.bundle.css') }}" rel="stylesheet" type="text/css" />
<link href="{{  asset('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css') }}" rel="stylesheet" type="text/css" />
<link href="{{  asset('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}" rel="stylesheet" type="text/css" />
<!-- begin::Body -->
<body  class="kt-header--fixed kt-header-mobile--fixed kt-subheader--enabled kt-subheader--transparent kt-aside--enabled kt-aside--fixed kt-aside--minimize kt-page--loading" >
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
        <input type="hidden" id="file" name="file">
        <div class="kt-subheader kt-grid__item" id="kt_subheader">
            <div class="kt-subheader__main">
                <h3 class="kt-subheader__title">
                    Claim No # {{$claims->claim_number}}
                </h3>

                <span class="kt-subheader__separator kt-hidden"></span>
                <div class="kt-subheader__breadcrumbs">
                    <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                    <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin-dashboard')}}" class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{  URL::to('admin/claims') }}" class="kt-subheader__breadcrumbs-link"> Claim </a> <span class="kt-subheader__breadcrumbs-separator"></span>
                </div>
            </div>
        </div>
        <!-- end:: Subheader -->
        <!-- begin:: Content -->
        <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">

            @if($claims->claim_type == 'Accident')
                {{--claim status labelDiv starts--}}
                <div class="kt-portlet" id="StatusLabelDiv" style="border:1px solid black;">
                    <div class="kt-portlet__head row">
                        <div class="kt-portlet__head-label col-lg-12">
                            <div class="col-lg-10">
                                <h3 class="kt-portlet__head-title">
                                    Claim Status Details
                                </h3>
                            </div>
                            {{-- @if($claim_assessment && $claim_assessment->assessment_report != NULL)--}}


                            {{-- @endif--}}
                        </div>
                    </div>

                    <div class="kt-portlet_body">
                        <div class="kt-section">
                            <div class="kt-section__content">
                                <table class="table table-striped m-table">
                                    <tbody>
                                    <tr>
                                        <th>Claim allocated to</th>
                                        <td>{!! $claimAccident->claim_allocated_to !!}</td>
                                        <th>Claim allocated on</th>
                                        <td>{!! \Carbon\Carbon::parse($claimAccident->claim_allocated_on)->format('d-m-Y')   !!}</td>

                                        <th>Total Reserve amount</th>
                                        <td>{!! $claimAccident->reserve_amount !!}</td>
                                    </tr>
                                    <tr>
                                        <th>Total Paid amount</th>
                                        <td>{!! $claimAccident->paid_amount !!}</td>
                                        <th>Claim Status</th>
                                        @if($claimAccident->claim_status == "Approved")
                                            <td>Approved</td>
                                        @elseif($claimAccident->claim_status == "Rejected")
                                            <td>Rejected <a href="#" title="Claim Reject Status Details" id="claimStatusRejectBtn"><i class="fas fa-info-circle"></i></a>
                                            </td>
                                        @else
                                            <td>Pending</td>
                                        @endif
                                        @if($claimAccident->claim_status == "Approved")
                                            <th>Claim Sub Status</th>
                                            <td>{{ $claimAccident->claim_sub_status }} @if( $claimAccident->claim_sub_status != "Repair")<a href="#" title="Claim Sub Status Details" id="claimStatusBtn"><i class="fas fa-info-circle"></i></a>@endif</td>
                                        @endif
                                    </tr>
                                    </tbody>
                                </table>
                                <br>
                            </div>
                        </div>
                    </div>
                </div>
            @endif


            <div id="StatusEditDiv" style="display:none;">
                @if($claims->claim_type == 'Accident')
                    <div class="row">
                        <div class="col-md-12">
                            <div class="kt-portlet">
                                <div class="kt-portlet__head-label col-lg-12">
                                    <div class="col-lg-10">
                                        <h5 class="kt-portlet__head-title" style="padding-top: 20px;">
                                            Claim Status Details
                                        </h5>
                                    </div>
                                </div>

                                <form id="statusUpdate"  action="{{ route('admin.claims.statusUpdate',$claims->id) }}" method="POST" enctype="multipart/form-data" class="kt-form">
                                    <!-- CSRF Token -->
                                    <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                                    <div class="kt-portlet_body">
                                        <div class="kt-section">
                                            <div class="kt-section__content" style="padding: 20px 20px 0 20px;">
                                                <div class="row">
                                                    <div class="col-lg-6">
                                                        <div class="form-group">
                                                            <label for="exampleSelect1">Claim allocated to:</label>
                                                            <select class="form-control kt_selectpicker" data-live-search="true" title="Please select claim allocated to" name="claim_allocated_to" required>
                                                                {{--  <option value="roy" @if($claimAccident->claim_allocated_to == "roy") selected @endif>Roy</option>
                                                                  <option value="andrew" @if($claimAccident->claim_allocated_to == "andrew") selected @endif>Andrew</option>--}}
                                                                @foreach($users as $user)
                                                                    <option value="{{$user->firstName}} {{$user->lastName}}"  @if( $claimAccident->claim_allocated_to == $user->firstName.' '.$user->lastName) selected @endif>
                                                                        {{$user->firstName}} {{$user->lastName}}
                                                                    </option>
                                                                @endforeach
                                                            </select>

                                                        </div>
                                                    </div>
                                                    <div class="col-lg-6">
                                                        <div class="form-group">
                                                            <label>Claim allocated on:</label>
                                                            <input class="form-control kt_datepicker_1"  placeholder="Please Select Date" name="claim_allocated_on" value="{!! $claimAccident->claim_allocated_on !!}" required>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="row">
                                                    <div class="col-lg-6">
                                                        <div class="form-group">
                                                            <label for="exampleSelect1">Total Reserve amount:</label>
                                                            <input type="text" class="form-control" value="{!! $claimAccident->reserve_amount !!}"  title="Please enter the amount" id="reserve_amount" name="reserve_amount" aria-describedby="emailHelp"  placeholder="P0.00" required>
                                                            <input type="hidden" value="{{ $policy->id }}" id="policyId">
                                                            <p style="display:none; color:red;" id="reserveMsg"></p>
                                                        </div>
                                                    </div>
                                                    <div class="col-lg-6">
                                                        <div class="form-group">
                                                            <label for="exampleSelect1">Total Paid amount:</label>
                                                            <input type="text" class="form-control" value="{!! $claimAccident->paid_amount !!}" title="Please enter the amount" name="paid_amount" aria-describedby="emailHelp"  placeholder="P0.00" required>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="row">
                                                    <div class="col-lg-6">
                                                        <div class="form-group">
                                                            <label for="exampleSelect1" style="padding-right: 15px">Claim Status:</label>
                                                            <select class="form-control kt_selectpicker" data-live-search="true" title="Please select claim status" name="claim_status" onchange="openSubStatus(this)">
                                                                <option value="Pending" @if($claims->status == 'Pending') selected @endif>Pending</option>
                                                                <option value="Approved" @if($claims->status == 'Approved') selected @endif>Approved</option>
                                                                <option value="Rejected" @if($claims->status == 'Rejected') selected @endif>Rejected</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="col-lg-6" @if($claims->status != 'Approved')style="display:none;"@endif id="sub_statusDiv">
                                                        <div class="form-group">
                                                            <label for="exampleSelect1" style="padding-right: 15px;">Claim Sub-status:</label>
                                                            <select class="form-control kt_selectpicker" data-live-search="true"  title="Please select claim sub status" id="selectValue" name="claim_sub_status" onchange="selectStatus(this)">
                                                                <option value="Repair" @if($claimAccident->claim_sub_status == 'Repair') selected @endif>Repair</option>
                                                                <option value="Cash In Lieu" @if($claimAccident->claim_sub_status == 'Cash In Lieu') selected @endif>Cash In Lieu</option>
                                                                <option value="Write Off" @if($claimAccident->claim_sub_status == 'Write Off') selected @endif>Write Off</option>
                                                                <option value="Exgratia" @if($claimAccident->claim_sub_status == 'Exgratia') selected @endif>Exgratia</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div id="selectStatus" @if($claims->claim_status == 'Rejected') style="display:block;" @else style="display:none;" @endif>
                                                    <div class="row">
                                                        <div class="col-lg-6">
                                                            <div class="form-group">
                                                                <label for="exampleSelect1">Reason</label>
                                                                <input type="text" class="form-control" value="{{ $claimAccident->reason }}" placeholder="Please provide valid Reason" title="Please provide valid Reason" required name="reason">
                                                            </div>
                                                        </div>
                                                        <div class="col-lg-6">
                                                            <div class="form-group">
                                                                <label for="exampleSelect1">Note</label>
                                                                <input type="text" class="form-control" value="{{ $claimAccident->note }}" placeholder="Please provide Note" title="Please provide Note" required name="note">
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>


                                                <div style="border:1px solid black;margin-bottom:30px; display:none;" id="substatus_repeater" class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>

                                                <div>
                                                    <button type="button" id="addbeneficiary_btn" style="display:none;" class="btn btn-brand"><i class="la la-plus"></i>Add Other Details </button>
                                                </div>

                                                {{--repeater code starts--}}
                                                <div class="row" id="add_details" style="display: none;">
                                                    <div class="col-lg-12">
                                                        <div class="kt-repeater">
                                                            <div class="kt-repeater__data-set">
                                                                <div data-repeater-list="beneficiary_status_details" id="beneficiary_status_details">
                                                                    <div data-repeater-item class="kt-repeater__item">
                                                                        <div class="form-group row" id="new_check_beneficiary">

                                                                            <div class="col-lg-6" style="padding-left: 20px;">
                                                                                <h6 style="padding-bottom: 15px;">Select beneficiary</h6>
                                                                                <input type="hidden" id="claim_id" name="claim_id" value="{{ $claims->id }}" >
                                                                                <input type="hidden" id="customer_id" name="customer_id" value="{{ $claims->customer_id }}" >


                                                                                <label class="kt-radio">
                                                                                    <input type="radio" name="check_beneficiary" class="premiumField condition check_beneficiary"
                                                                                           value="customer">Customer<span></span>

                                                                                </label>
                                                                                <label class="kt-radio" style="margin-left: 10px;">
                                                                                    <input type="radio" name="check_beneficiary"  class="premiumField condition check_beneficiary"
                                                                                           value="other_party">Other Party<span></span>
                                                                                </label>
                                                                            </div>

                                                                            <div class="col-lg-6">
                                                                                <div class="form-group new_otherparty_dropdown"  style="display:none;">
                                                                                    <label for="exampleSelect1" style="padding-right: 15px">Other Party</label>
                                                                                    <select class="form-control kt_selectpicker"  id="otherparty_list" name="otherparty_list" title="Please select other party" >
                                                                                        @foreach($otherparty as $other)
                                                                                            <option value="{{ $other->id }}">{{$other->first_name}} {{$other->last_name }}</option>
                                                                                        @endforeach
                                                                                    </select>
                                                                                </div>
                                                                            </div>

                                                                        </div>
                                                                        <div class="details_div" style="display:none; margin-top:-3%;">
                                                                            <h5 style="color:black;padding-top:30px;">Bank Details</h5>
                                                                            <br>
                                                                            <div class="row">
                                                                                <div class="col-lg-6">
                                                                                    <div class="form-group">
                                                                                        <label for="exampleSelect1">Myzaka/Orange Money Cell</label>
                                                                                        <input type="text" class="form-control accident_billingCell" name="accident_billingCell" aria-describedby="emailHelp" title="Enter orange money cell"
                                                                                               placeholder="Myzaka/Orange Cell" value="" minlength="8" maxlength="8" pattern="[0-9]{8}" >
                                                                                    </div>
                                                                                </div>
                                                                                <div class="col-lg-6">
                                                                                    <div class="form-group">
                                                                                        <label for="exampleSelect1">Bank Name:</label>
                                                                                        <input type="text" class="form-control  accident_bankName" title="Please enter customers bank" name="accident_bankName" >
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                            <div class="row">
                                                                                <div class="col-lg-6">
                                                                                    <div class="form-group">
                                                                                        <label>Branch Code:</label>
                                                                                        <input type="text" class="form-control  accident_branchCode" title="Please enter customers branch" name="accident_branchCode">
                                                                                    </div>
                                                                                </div>
                                                                                <div class="col-lg-6">
                                                                                    <div class="form-group">
                                                                                        <label>Account Number:</label>
                                                                                        <input type="text" class="form-control accident_accountNumber" name="accident_accountNumber" title="Please enter customer account number"  placeholder="Account Number" pattern="[0-9]{1,25}"  maxlength="25">
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                            <div class="row">
                                                                                <div class="col-lg-6">
                                                                                    <div class="form-group">
                                                                                        <label>Account Type:</label>
                                                                                        <select class="form-control kt_selectpicker" name="accident_bankAccountType">
                                                                                            <option>Please Select the Bank Account Type</option>
                                                                                            <option value="1" selected>Cheque</option>
                                                                                            <option value="2" >Savings</option>
                                                                                        </select>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                            <h5 style="color:black;padding-top:30px;">KYC Details</h5>
                                                                            <br>
                                                                            <div class="form-group row">
                                                                                <div class="col-md-2">
                                                                                    <h3 class="col-form-label" style="float: left;">Driving License</h3>
                                                                                    <div class="kt-avatar" id="driving_license" style="float: left; clear: left;">
                                                                                        <div class="kt-avatar__holder" style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                                                                                        <label class="kt-avatar__upload" data-toggle="kt-tooltip" title="Change Image">
                                                                                            <i class="fa fa-pen"></i>
                                                                                            <input type='file' class="driving_license" name="driving_license"  <?php echo config('app.accept_attr'); ?> <?php echo config('app.accept_msg'); ?>/>
                                                                                        </label>
                                                                                        <span class="kt-avatar__cancel" data-toggle="kt-tooltip" title="Cancel Image"> <i class="fa fa-times"></i> </span>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="col-md-2">
                                                                                    <h3 class="col-form-label" style="float: left;">Omang ID</h3>
                                                                                    <div class="kt-avatar" id="omang_pic" style="float: left; clear: left;">
                                                                                        <div class="kt-avatar__holder" style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                                                                                        <label class="kt-avatar__upload" data-toggle="kt-tooltip" title="Change Image">
                                                                                            <i class="fa fa-pen"></i>
                                                                                            <input type='file' class="omang" name="omang" <?php echo config('app.accept_attr'); ?> <?php echo config('app.accept_msg'); ?>/>
                                                                                        </label>
                                                                                        <span class="kt-avatar__cancel" data-toggle="kt-tooltip" title="Cancel Image"> <i class="fa fa-times"></i> </span>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="col-md-2">
                                                                                    <h3 class="col-form-label" style="float: left;">Proof of Residence</h3>
                                                                                    <div class="kt-avatar" id="proof_residence" style="float: left; clear: left;">
                                                                                        <div class="kt-avatar__holder" style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                                                                                        <label class="kt-avatar__upload" data-toggle="kt-tooltip" title="Change Image">
                                                                                            <i class="fa fa-pen"></i>
                                                                                            <input type='file' class="proof_residence" name="proof_residence" <?php echo config('app.accept_attr'); ?> <?php echo config('app.accept_msg'); ?>/>
                                                                                        </label>
                                                                                        <span class="kt-avatar__cancel" data-toggle="kt-tooltip" title="Cancel Image"> <i class="fa fa-times"></i> </span>

                                                                                    </div>
                                                                                </div>
                                                                                <div class="col-md-2">
                                                                                    <h3 class="col-form-label" style="float: left;">Proof Of Income</h3>
                                                                                    <div class="kt-avatar" id="proof_income" style="float: left; clear: left;">

                                                                                        <div class="kt-avatar__holder" style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                                                                                        <label class="kt-avatar__upload" data-toggle="kt-tooltip" title="Change Image">
                                                                                            <i class="fa fa-pen"></i>
                                                                                            <input type='file' class="proof_income" id="proof_income_id" name="proof_income" <?php echo config('app.accept_attr'); ?> <?php echo config('app.accept_msg'); ?>/>
                                                                                        </label>
                                                                                        <span class="kt-avatar__cancel" data-toggle="kt-tooltip" title="Cancel Image"> <i class="fa fa-times"></i> </span>

                                                                                    </div>
                                                                                </div>
                                                                                <div class="col-md-2">
                                                                                    <h3 class="col-form-label" style="float: left;">Passport</h3>
                                                                                    <div class="kt-avatar" id="passport_pic" style="float: left; clear: left;">
                                                                                        <div class="kt-avatar__holder" style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                                                                                        <label class="kt-avatar__upload" data-toggle="kt-tooltip" title="Change Image">
                                                                                            <i class="fa fa-pen"></i>
                                                                                            <input type='file' class="passport" name="passport" <?php echo config('app.accept_attr'); ?> <?php echo config('app.accept_msg'); ?>/>
                                                                                        </label>
                                                                                        <span class="kt-avatar__cancel" data-toggle="kt-tooltip" title="Cancel Image"> <i class="fa fa-times"></i> </span>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>

                                                                        <div class="kt-repeater__data form-group">
                                                                            <span data-repeater-delete="" class="btn btn-warning btn-sm"> <i class="la la-close"></i> Remove </span>
                                                                        </div>

                                                                        <div style="border:1px solid black;" class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>
                                                                        <br>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="kt-repeater__add-data">
                                                                <span data-repeater-create="" class="btn btn-info btn-sm"> <i class="la la-plus"></i> Add Other Details </span>
                                                            </div>

                                                        </div>
                                                    </div>
                                                </div>
                                                {{--repeater code ends--}}
                                                <br>
                                                <br>
                                                <div class="form-group row" style="display:none;" id="SalvageDiv" >
                                                    <label for="example-text-input" class="col-3 col-form-label"> Send mail to Salvage Yard ?</label>
                                                    <div class="col-9">
                                                        <label class="kt-checkbox kt-checkbox--brand">
                                                            <input id="salvage" type="checkbox" name="salvage" value="1"  onchange="checkSalvageYard()">
                                                            <span></span>
                                                        </label>
                                                    </div>
                                                </div>
                                                <div class="row" style=" display:none;" id="SalvageList">
                                                    <div class="col-lg-12">
                                                        <div class="form-group">
                                                            <label> Salvage Yard List</label>
                                                            <select class="form-control kt_selectpicker salvage_yard" title="Please select salvage yard " name="salvage_yard">
                                                                @foreach($salvageUser as $user)
                                                                    <option value="{{$user->id}}" >{{$user->firstName}} {{$user->lastName}}</option>
                                                                @endforeach
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="form-group row" @if($claims->claim_status == 'Rejected') style="display:block;" @else style="display:none;" @endif id="uploadFile" >
                                                    <label for="example-text-input" class="col-3 col-form-label">Additional Form</label>
                                                    <div class="col-lg-9">
                                                        <div class="input-group">
                                                            <div class="kt-avatar" style="float: left; clear: left;" id="claimStatusFile">
                                                                @if($claimAccident->document  == NULL)
                                                                    <div class="kt-avatar__holder" style="background-image:url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                                                                @else
                                                                    <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($claimAccident->document) !!}" target="_blank" download>
                                                                        @if(pathinfo($claimAccident->document, PATHINFO_EXTENSION) == 'pdf')
                                                                            <img src="{{asset('images/pdf.ico')}}" width="100%" height="auto" >
                                                                        @elseif(pathinfo($claimAccident->document, PATHINFO_EXTENSION) == 'docx' || pathinfo($claimAccident->document, PATHINFO_EXTENSION) == 'doc' || pathinfo($claimAccident->document, PATHINFO_EXTENSION) == 'docm')
                                                                            <img src="{{asset('images/word.ico')}}" width="100%" height="auto" >
                                                                        @elseif(pathinfo($claimAccident->document, PATHINFO_EXTENSION) == 'xls' || pathinfo($claimAccident->document, PATHINFO_EXTENSION) == 'xlsx' || pathinfo($claimAccident->document, PATHINFO_EXTENSION) == 'csv')
                                                                            <img src="{{asset('images/excel.png')}}" width="100%" height="auto" >
                                                                        @elseif(pathinfo($claimAccident->document, PATHINFO_EXTENSION) == 'jpeg' || pathinfo($claimAccident->document, PATHINFO_EXTENSION) == 'jpg' || pathinfo($claimAccident->document, PATHINFO_EXTENSION) == 'png')
                                                                            <img src="{{\AlphaDirect\Helper::getCloudFrontURL($claimAccident->document )}}" width="100%" height="auto" >
                                                                        @else
                                                                            <img src="{{asset('images/doc.png')}}" width="100%" height="auto" >
                                                                        @endif
                                                                    </a>
                                                                @endif
                                                                <label class="kt-avatar__upload" data-toggle="kt-tooltip" title="Change Image">
                                                                    <i class="fa fa-pen"></i>
                                                                    <input type='file' name="document" <?php echo config('app.accept_attr'); ?> <?php echo config('app.accept_msg'); ?>/>
                                                                </label>
                                                                <span class="kt-avatar__cancel" data-toggle="kt-tooltip" title="Cancel Image"> <i class="fa fa-times"></i> </span>
                                                            </div>
                                                        </div>
                                                        <div class="kt-separator kt-separator--space-sm"></div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                </form>
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            <!--begin::Portlet-->
            <div class="kt-portlet kt-portlet--tabs">
                <div class="kt-portlet__head">
                    <div class="kt-portlet__head-toolbar">
                        <ul class="nav nav-tabs nav-tabs-line nav-tabs-line-brand nav-tabs-line-2x nav-tabs-line-right nav-tabs-bold" role="tablist">
                            @if($claims->claim_type == 'Life')
                              @can('policy_details_tab')
                                <li class="nav-item">
                                    <a class="nav-link @if(!session()->has('step')) active @endif" data-toggle="tab" href="#kt_portlet_base_demo_3_1_tab_content" role="tab"> <i class="flaticon2-heart-rate-monitor" aria-hidden="true"></i>Policy Details </a>
                                </li>
                              @endcan

                              @can('process_claim_tab')
                                <li class="nav-item">
                                    <a class="nav-link @if(session()->get('step') == 2) active @endif" data-toggle="tab" href="#kt_portlet_base_demo_3_2_tab_content" role="tab"> <i class="flaticon2-pie-chart-2" aria-hidden="true"></i>Process Claim  </a>
                                </li>
                              @endcan

                              @can('attachments_tab')
                                <li class="nav-item">
                                    <a class="nav-link @if(session()->get('step') == 8) active @endif" data-toggle="tab" href="#kt_portlet_base_demo_3_8_tab_content" role="tab"> <i class="flaticon2-pie-chart-2" aria-hidden="true"></i>Attachments </a>
                                </li>
                              @endcan

                              @can('reserves_payments_tab')
                                <li class="nav-item">
                                    <a class="nav-link @if(session()->has('step') == 10) active @endif" data-toggle="tab" href="#kt_portlet_base_demo_3_10_tab_content" role="tab"> <i class="flaticon2-heart-rate-monitor" aria-hidden="true"></i>Reserves/Payments</a>
                                </li>
                              @endcan
                            @endif

                            @if($claims->claim_type == 'Legal')
                                @can('policy_details_tab')
                                <li class="nav-item">
                                    <a class="nav-link @if(!session()->has('step')) active @endif" data-toggle="tab" href="#kt_portlet_base_demo_3_1_tab_content" role="tab"> <i class="flaticon2-heart-rate-monitor" aria-hidden="true"></i>Policy Details </a>
                                </li>
                                @endcan

                                @can('process_claim_tab')
                                <li class="nav-item">
                                    <a class="nav-link @if(session()->get('step') == 2) active @endif" data-toggle="tab" href="#kt_portlet_base_demo_3_2_tab_content" role="tab"> <i class="flaticon2-pie-chart-2" aria-hidden="true"></i>Process Claim  </a>
                                </li>
                                @endcan

                                @can('attachments_tab')
                                <li class="nav-item">
                                    <a class="nav-link @if(session()->get('step') == 8) active @endif" data-toggle="tab" href="#kt_portlet_base_demo_3_8_tab_content" role="tab"> <i class="flaticon2-pie-chart-2" aria-hidden="true"></i>Attachments </a>
                                </li>
                                @endcan

                                @can('reserves_payments_tab')
                                <li class="nav-item">
                                    <a class="nav-link @if(session()->has('step') == 10) active @endif" data-toggle="tab" href="#kt_portlet_base_demo_3_10_tab_content" role="tab"> <i class="flaticon2-heart-rate-monitor" aria-hidden="true"></i>Reserves/Payments</a>
                                </li>
                                @endcan
                            @endif

                            @if($claims->claim_type == 'Glass')
                                @can('policy_details_tab')
                                <li class="nav-item">
                                    <a class="nav-link @if(!session()->has('step')) active @endif" data-toggle="tab" href="#kt_portlet_base_demo_3_1_tab_content" role="tab"> <i class="flaticon2-heart-rate-monitor" aria-hidden="true"></i>Policy Details </a>
                                </li>
                                @endcan

                                @can('process_claim_tab')
                                <li class="nav-item">
                                    <a class="nav-link @if(session()->get('step') == 2) active @endif" data-toggle="tab" href="#kt_portlet_base_demo_3_2_tab_content" role="tab"> <i class="flaticon2-pie-chart-2" aria-hidden="true"></i>Process Claim  </a>
                                </li>
                                @endcan

                                @can('supplier_tab')
                                <li class="nav-item">
                                    <a class="nav-link @if(session()->get('step') == 3) active @endif" data-toggle="tab" href="#kt_portlet_base_demo_3_3_tab_content" role="tab"> <i class="flaticon2-chronometer" aria-hidden="true"></i>Supplier </a>
                                </li>
                                @endcan

                                @can('attachments_tab')
                                <li class="nav-item">
                                    <a class="nav-link @if(session()->get('step') == 8) active @endif" data-toggle="tab" href="#kt_portlet_base_demo_3_8_tab_content" role="tab"> <i class="flaticon2-pie-chart-2" aria-hidden="true"></i>Attachments </a>
                                </li>
                                @endcan

                                @can('invoice_tab')
                                <li class="nav-item">
                                    <a class="nav-link @if(session()->get('step') == 4) active @endif" data-toggle="tab" href="#kt_portlet_base_demo_3_4_tab_content" role="tab"> <i class="flaticon2-chronometer" aria-hidden="true"></i>Invoice </a>
                                </li>
                                @endcan

                                @can('reserves_payments_tab')
                                <li class="nav-item">
                                    <a class="nav-link @if(session()->has('step') == 10) active @endif" data-toggle="tab" href="#kt_portlet_base_demo_3_10_tab_content" role="tab"> <i class="flaticon2-heart-rate-monitor" aria-hidden="true"></i>Reserves/Payments</a>
                                </li>
                                @endcan
                            @endif
                            @if($claims->claim_type == 'Accident')
                              @can('policy_details_tab')
                                <li class="nav-item">
                                    <a class="nav-link @if(!session()->has('step')) active @endif" data-toggle="tab" href="#kt_portlet_base_demo_3_1_tab_content" role="tab"> <i class="flaticon2-heart-rate-monitor" aria-hidden="true"></i>Policy Details </a>
                                </li>
                              @endcan

                              @can('claim_details_tab')
                                <li class="nav-item">
                                    <a class="nav-link @if(session()->get('step') == 5) active @endif" data-toggle="tab" href="#kt_portlet_base_demo_3_5_tab_content" role="tab"> <i class="flaticon2-heart-rate-monitor" aria-hidden="true"></i>Claim Details </a>
                                </li>
                              @endcan

                              @can('assessor_tab')
                                <li class="nav-item">
                                    <a class="nav-link @if(session()->has('step') == 6) active @endif" data-toggle="tab" href="#kt_portlet_base_demo_3_6_tab_content" role="tab"> <i class="flaticon2-heart-rate-monitor" aria-hidden="true"></i>Assessor</a>
                                </li>
                              @endcan

                              @can('attachments_tab')
                                <li class="nav-item">
                                    <a class="nav-link @if(session()->get('step') == 8) active @endif" data-toggle="tab" href="#kt_portlet_base_demo_3_8_tab_content" role="tab"> <i class="flaticon2-pie-chart-2" aria-hidden="true"></i>Attachments </a>
                                </li>
                              @endcan

                              @can('supplier_tab')
                                <li class="nav-item">
                                    <a class="nav-link @if(session()->has('step') == 7) active @endif" data-toggle="tab" href="#kt_portlet_base_demo_3_7_tab_content" role="tab"> <i class="flaticon2-heart-rate-monitor" aria-hidden="true"></i>Suppliers</a>
                                </li>
                              @endcan

                              @can('reserves_payments_tab')
                                <li class="nav-item">
                                    <a class="nav-link @if(session()->has('step') == 10) active @endif" data-toggle="tab" href="#kt_portlet_base_demo_3_10_tab_content" role="tab"> <i class="flaticon2-heart-rate-monitor" aria-hidden="true"></i>Reserves/Payments</a>
                                </li>
                              @endcan

                                @if($claimAccident->claim_sub_status == "Repair")
                                  @can('invoice_tab')
                                    <li class="nav-item">
                                        <a class="nav-link @if(session()->get('step') == 4) active @endif" data-toggle="tab" href="#kt_portlet_base_demo_3_4_tab_content" role="tab"> <i class="flaticon2-chronometer" aria-hidden="true"></i>Invoice </a>
                                    </li>
                                  @endcan
                                @endif

                            @endif
                        </ul>
                    </div>
                </div>
            </div>
            <div class="tab-content">
                <div class="tab-pane @if(!session()->has('step')) active @endif" id="kt_portlet_base_demo_3_1_tab_content" role="tabpanel">
                    <div class="row">
                        <div class="col-md-12">
                            <div class="kt-portlet" id="labelDiv">
                                <div class="kt-portlet__head">
                                    <div class="kt-portlet__head-label">
                                        <h3 class="kt-portlet__head-title">
                                            Customer Details
                                        </h3>
                                    </div>
                                </div>
                                <div class="kt-portlet_body">
                                    <div class="kt-section">
                                        <div class="kt-section__content" style="padding-left:20px; padding-right: 20px;">
                                            <table class="table table-striped m-table">
                                                <tbody>
                                                <tr>
                                                    <th>Name</th>
                                                    <td>{!! $userInfo->firstName !!} {!! $userInfo->lastName !!}</td>
                                                    <th>Email</th>
                                                    <td>{!! $userInfo->email !!}</td>
                                                </tr>
                                                <tr>
                                                    <th>Cellphone</th>
                                                    <td>{!! $userInfo->cellphone !!}</td>
                                                    <th>Address</th>
                                                    <td style="width:40%;">{!! $userInfo->profile->address !!}</td>
                                                </tr>
                                                <tr>
                                                    <th>Omang ID</th>
                                                    <td>{!! $userInfo->profile->omang !!}</td>
                                                    <th>Passport</th>
                                                    <td>{!! $userInfo->profile->passport !!}</td>
                                                </tr>
                                                <tr>
                                                    <th>Date of Birth</th>
                                                    <td>{!! $userInfo->profile->dob !!}</td>
                                                </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                                <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>
                                <div class="kt-portlet__head">
                                    <div class="kt-portlet__head-label">
                                        <h3 class="kt-portlet__head-title">
                                            Customer KYC
                                        </h3>
                                    </div>
                                </div>
                                <div class="kt-portlet_body">
                                    <div class="kt-section">
                                        <div class="kt-section__content" style="padding-left:20px; padding-right: 20px;">
                                            <table class="table table-striped m-table">
                                                <tbody>
                                                <div class="form-group row">
                                                    <div class="col-md-2">
                                                        <h3 class="col-form-label" style="float: left;">Driving License</h3>
                                                        @if($kyc->driving_license == NULL)
                                                            <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="100%" height="auto" >
                                                        @else
                                                            <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($kyc->driving_license) !!}" target="_blank" download>
                                                                <img src="{{\AlphaDirect\Helper::getCloudFrontURL($kyc->driving_license)}}" width="100%" height="auto" >
                                                            </a>
                                                        @endif
                                                        <div class="kt-avatar" style="float: left; clear: left;"></div>
                                                    </div>

                                                    <div class="col-md-2">
                                                        <h3 class="col-form-label" style="float: left;">Omang ID</h3>
                                                        @if($kyc->omang == NULL)
                                                            <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="100%" height="auto" >
                                                        @else
                                                            <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($kyc->omang) !!}" target="_blank" download>
                                                                <img src="{{\AlphaDirect\Helper::getCloudFrontURL($kyc->omang)}}" width="100%" height="auto" >
                                                            </a>
                                                        @endif
                                                        <div class="kt-avatar" style="float: left; clear: left;"></div>
                                                    </div>

                                                    <div class="col-md-2">
                                                        <h3 class="col-form-label" style="float: left;">Proof of Residence</h3>
                                                        @if($kyc->proof_residence == NULL)
                                                            <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="100%" height="auto" >
                                                        @else
                                                            <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($kyc->proof_residence) !!}" target="_blank" download>
                                                                <img src="{{\AlphaDirect\Helper::getCloudFrontURL($kyc->proof_residence)}}" width="100%" height="auto" >
                                                            </a>
                                                        @endif
                                                        <div class="kt-avatar" style="float: left; clear: left;">
                                                        </div>
                                                    </div>

                                                    <div class="col-md-2">
                                                        <h3 class="col-form-label" style="float: left;">Proof of Income</h3>
                                                        @if($kyc->proof_income == NULL)
                                                            <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="100%" height="auto" >
                                                        @else
                                                            <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($kyc->proof_income) !!}" target="_blank" download>
                                                                <img src="{{\AlphaDirect\Helper::getCloudFrontURL($kyc->proof_income)}}" width="100%" height="auto" >
                                                            </a>
                                                        @endif
                                                        <div class="kt-avatar" style="float: left; clear: left;">
                                                        </div>
                                                    </div>

                                                    <div class="col-md-2">
                                                        <h3 class="col-form-label" style="float: left;">Passport</h3>
                                                        @if($kyc->passport == NULL)
                                                            <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="100%" height="auto" >
                                                        @else
                                                            <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($kyc->passport) !!}" target="_blank" download>
                                                                <img src="{{\AlphaDirect\Helper::getCloudFrontURL($kyc->passport)}}" width="100%" height="auto" >
                                                            </a>
                                                        @endif
                                                        <div class="kt-avatar" style="float: left; clear: left;">
                                                        </div>
                                                    </div>
                                                </div>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>

                                <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>
                                <div class="kt-portlet__head">
                                    <div class="kt-portlet__head-label">
                                        <h3 class="kt-portlet__head-title">
                                            Product Details
                                        </h3>
                                    </div>
                                </div>
                                <div class="kt-portlet_body">
                                    <div class="kt-section">
                                        <div class="kt-section__content" style="padding-left:20px; padding-right: 20px;">
                                            <table class="table table-striped m-table">
                                                <tbody>
                                                <tr>
                                                    <th>Product Name</th>
                                                    <td>{!! $product->name !!}</td>
                                                </tr>
                                                <tr>
                                                    <th>Premium</th>
                                                    <td>P{!! $policy->premium !!}</td>
                                                </tr>
                                                @if($policy->plan_id != NULL)
                                                    <tr>
                                                        <th>product Plan</th>
                                                        <td>{!! $productPlan->name !!}</td>
                                                    </tr>
                                                @endif
                                                @foreach($productFactors as $productFactor)
                                                    @if($productFactor->type == 'Select')
                                                        <tr>
                                                            <th>{!! $productFactor->name !!}</th>
                                                            <td>{!! implode(',', $productFactor->policyFactorsValueName) !!}</td>
                                                        </tr>
                                                    @elseif($productFactor->type == 'Radio')
                                                        <tr>
                                                            <th>{!! $productFactor->name !!}</th>
                                                            <td>{!! implode(',', $productFactor->policyFactorsValueName) !!}</td>
                                                        </tr>
                                                    @elseif($productFactor->type == 'Checkbox')
                                                        <tr>
                                                            <th>{!! $productFactor->name !!}</th>
                                                            <td>{!! implode(', ', $productFactor->policyFactorsValueName) !!}</td>
                                                        </tr>
                                                    @elseif($productFactor->type == 'Input Field')
                                                        <tr>
                                                            <th>{!! $productFactor->name !!}</th>
                                                            <td>{!! implode(',', $productFactor->policyFactorsValueName) !!}</td>
                                                        </tr>
                                                    @endif
                                                @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                                @if($policy->has_vehicle != 0)
                                    <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>
                                    <div class="kt-portlet__head">
                                        <div class="kt-portlet__head-label">
                                            <h3 class="kt-portlet__head-title">
                                                Vehicle Details
                                            </h3>
                                        </div>
                                    </div>
                                    <div class="kt-portlet_body">
                                        <div class="kt-section">
                                            <div class="kt-section__content" style="padding-left:20px; padding-right: 20px;">
                                                <table class="table table-striped m-table">
                                                    <tbody>
                                                    <tr>
                                                        <th>Vehicle Number</th>
                                                        <td>{!! $vehicle->vehiclePlate !!}</td>
                                                        <th>Chassis Number(VIN)</th>
                                                        <td>{!! $vehicle->chassisNo !!}</td>
                                                    </tr>
                                                    <tr>
                                                        <th>Odometer</th>
                                                        <td>{!! $vehicle->odometer !!}</td>
                                                        <th>Purpose</th>
                                                        <td>
                                                            @foreach($vehicle_purpose as $purpose)
                                                                @if($purpose->id == $vehicle->purpose)
                                                                    {!! $purpose->value !!}
                                                                @endif
                                                            @endforeach
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <th>Condition</th>
                                                        <td style="width:40%;">{!! $vehicle->condition !!}</td>
                                                        <th>Year of Manufacturing</th>
                                                        <td>{!! $vehicle->year !!}</td>
                                                    </tr>
                                                    <tr>
                                                        <th>Make</th>
                                                        <td>{!! $vehicle->make !!}</td>
                                                        <th>Model</th>
                                                        <td>{!! $vehicle->model !!}</td>
                                                    </tr>
                                                    <tr>
                                                        <th>Engine Number</th>
                                                        <td>{!! $vehicle->engineNo !!}</td>
                                                        <th>Number of Seats</th>
                                                        <td>{!! $vehicle->seats !!}</td>
                                                    </tr>
                                                    <tr>
                                                        <th>Number of Cylinder</th>
                                                        <td>{!! $vehicle->cylinders !!}</td>
                                                        <th>Cubic Capacity</th>
                                                        <td>{!! $vehicle->cubic_capacity !!}</td>
                                                    </tr>
                                                    <tr>
                                                        <th>Is it imported?</th>
                                                        @if($vehicle->is_imported == 0)
                                                            <td>No</td>
                                                        @else
                                                            <td>Yes</td>
                                                        @endif
                                                        <th>Is the vehicle fitted with tracking device?</th>
                                                        @if($vehicle->is_tracking == 0)
                                                            <td>No</td>
                                                        @else
                                                            <td>Yes</td>
                                                        @endif
                                                    </tr>
                                                    <tr>
                                                        <th>Is the vehicle used for private use?</th>
                                                        @if($vehicle->is_private == 0)
                                                            <td>No</td>
                                                        @else
                                                            <td>Yes</td>
                                                        @endif
                                                        <th>Is the vehicle modified in any way?</th>
                                                        @if($vehicle->is_modified == 0)
                                                            <td>No</td>
                                                        @else
                                                            <td>Yes</td>
                                                        @endif
                                                    </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>
                                    <div class="kt-portlet__head">
                                        <div class="kt-portlet__head-label">
                                            <h3 class="kt-portlet__head-title">
                                                Vehicle Images
                                            </h3>
                                        </div>
                                    </div>
                                    <div class="kt-portlet_body">
                                        <div class="kt-section">
                                            <div class="kt-section__content" style="padding-left:20px; padding-right: 20px;">
                                                <table class="table table-striped m-table">
                                                    <tbody>
                                                    <div class="form-group row">
                                                        <div class="col-md-2" style="margin-left: 30px;">
                                                            <h3 class="col-form-label" style="float: left;">Left</h3>
                                                            @if($vehicle->left == NULL)
                                                                <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="100%" height="auto" >
                                                            @else
                                                                <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($vehicle->left) !!}" target="_blank" download>
                                                                    <img src="{{\AlphaDirect\Helper::getCloudFrontURL($vehicle->left)}}" width="100%" height="auto" >
                                                                </a>
                                                            @endif
                                                            <div class="kt-avatar" style="float: left; clear: left;"></div>
                                                        </div>
                                                        <div class="col-md-2">
                                                            <h3 class="col-form-label" style="float: left;">Right</h3>
                                                            @if($vehicle->right == NULL)
                                                                <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="100%" height="auto" >
                                                            @else
                                                                <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($vehicle->right) !!}" target="_blank" download>
                                                                    <img src="{{\AlphaDirect\Helper::getCloudFrontURL($vehicle->right)}}" width="100%" height="auto" >
                                                                </a>

                                                            @endif
                                                            <div class="kt-avatar" style="float: left; clear: left;"></div>
                                                        </div>
                                                        <div class="col-md-2">
                                                            <h3 class="col-form-label" style="float: left;">Back</h3>
                                                            @if($vehicle->back == NULL)
                                                                <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="100%" height="auto" >
                                                            @else
                                                                <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($vehicle->back) !!}" target="_blank" download>
                                                                    <img src="{{\AlphaDirect\Helper::getCloudFrontURL($vehicle->back)}}" width="100%" height="auto" >
                                                                </a>
                                                            @endif
                                                            <div class="kt-avatar" style="float: left; clear: left;"></div>
                                                        </div>
                                                        <div class="col-md-2">
                                                            <h3 class="col-form-label" style="float: left;">Front</h3>
                                                            @if($vehicle->front == NULL)
                                                                <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="100%" height="auto" >
                                                            @else
                                                                <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($vehicle->front) !!}" target="_blank" download>
                                                                    <img src="{{\AlphaDirect\Helper::getCloudFrontURL($vehicle->front)}}" width="100%" height="auto" >
                                                                </a>

                                                            @endif
                                                            <div class="kt-avatar" style="float: left; clear: left;"></div>
                                                        </div>


                                                        <div class="col-md-2">
                                                            <h3 class="col-form-label" style="float: left;">Vehicle Registration</h3>
                                                            @if($vehicle->vehicleRegistration == NULL)
                                                                <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="100%" height="auto" >
                                                            @else
                                                                <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($vehicle->vehicleRegistration) !!}" target="_blank" download>

                                                                    @if(pathinfo($vehicle->vehicleRegistration,
                                                                    PATHINFO_EXTENSION) == 'pdf')
                                                                        <img src="{{asset('images/pdf.ico')}}" width="100%"
                                                                             height="auto">
                                                                    @elseif(pathinfo($vehicle->vehicleRegistration,
                                                                    PATHINFO_EXTENSION) == 'docx' ||
                                                                    pathinfo($kyc->driving_license, PATHINFO_EXTENSION)
                                                                    == 'doc' || pathinfo($vehicle->vehicleRegistration,
                                                                    PATHINFO_EXTENSION) == 'docm')
                                                                        <img src="{{asset('images/word.ico')}}" width="100%"
                                                                             height="auto">
                                                                    @elseif(pathinfo($vehicle->vehicleRegistration,
                                                                    PATHINFO_EXTENSION) == 'xls' ||
                                                                    pathinfo($vehicle->vehicleRegistration, PATHINFO_EXTENSION)
                                                                    == 'xlsx' || pathinfo($vehicle->vehicleRegistration,
                                                                    PATHINFO_EXTENSION) == 'csv')
                                                                        <img src="{{asset('images/excel.png')}}"
                                                                             width="100%" height="auto">
                                                                    @elseif(pathinfo($vehicle->vehicleRegistration,
                                                                    PATHINFO_EXTENSION) == 'jpeg' ||
                                                                    pathinfo($vehicle->vehicleRegistration, PATHINFO_EXTENSION)
                                                                    == 'jpg' || pathinfo($vehicle->vehicleRegistration,
                                                                    PATHINFO_EXTENSION) == 'png')
                                                                        <img src="{{\AlphaDirect\Helper::getCloudFrontURL($vehicle->vehicleRegistration)}}"
                                                                             width="100%" height="auto">
                                                                    @else
                                                                        <img src="{{asset('images/doc.png')}}" width="100%"
                                                                             height="auto">
                                                                    @endif

                                                                </a>


                                                            @endif
                                                            <div class="kt-avatar" style="float: left; clear: left;"></div>

                                                        </div>
                                                    </div>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>
                                    <div class="kt-portlet__head">
                                        <div class="kt-portlet__head-label">
                                            <h3 class="kt-portlet__head-title">
                                                Coverages
                                            </h3>
                                        </div>
                                    </div>
                                    <div class="kt-portlet_body">
                                        <div class="kt-section">
                                            <div class="kt-section__content" style="padding-left:20px; padding-right: 20px;">
                                                <table class="table table-striped m-table">
                                                    <tbody>
                                                    <th>Main</th>
                                                    <th>Coverage Value</th>
                                                    <th>Disc/Surcharge</th>
                                                    <th>Flat/%</th>
                                                    <th>Value</th>
                                                    @foreach($policyCover as $key => $cover)
                                                        <tr>
                                                            <td>{!! $cover->main !!}</td>
                                                            @if($cover->coverage_value != NULL)
                                                                <td>{!! $cover->coverage_value  !!}</td>
                                                            @else
                                                                <td>-</td>
                                                            @endif

                                                            @if($cover->discount != NULL && $cover->discount != 0)
                                                                @if($cover->discount == 1)
                                                                    <td>Discount</td>
                                                                @elseif($cover->discount == 2)
                                                                    <td>Surcharge</td>
                                                                @endif
                                                            @else
                                                                <td>-</td>
                                                            @endif

                                                            @if($cover->type != NULL)
                                                                @if($cover->type == 1)
                                                                    <td>Flat</td>
                                                                @elseif($cover->type == 2)
                                                                    <td>%</td>
                                                                @endif
                                                            @else
                                                                <td>-</td>
                                                            @endif

                                                            @if($cover->value != NULL)
                                                                <td>{!! $cover->value !!}</td>
                                                            @else
                                                                <td>-</td>
                                                            @endif

                                                        </tr>
                                                    @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>
                                    <div class="kt-portlet__head">
                                        <div class="kt-portlet__head-label">
                                            <h3 class="kt-portlet__head-title">
                                                Specified Motor Items
                                            </h3>
                                        </div>
                                    </div>
                                    <div class="kt-portlet_body">
                                        <div class="kt-section">
                                            <div class="kt-section__content" style="padding-left:20px; padding-right: 20px;">
                                                <table class="table table-striped m-table">
                                                    <tbody>
                                                    <th>Description Of Items</th>
                                                    <th>Sum Insured</th>
                                                    @foreach($policyMotorItems as $policyMotorItem)
                                                        @if(count($motor_items))
                                                            @foreach($motor_items as $motor_item)
                                                                @if( $policyMotorItem->item_name == $motor_item->n_PRAppsSuppPersprop_PK)
                                                                    <tr>
                                                                        <td>{{ $motor_item->s_PersPropScreenName }}</td>
                                                                        <td>{{ $policyMotorItem->item_value }}</td>
                                                                    </tr>
                                                                @endif
                                                            @endforeach
                                                        @else
                                                            <tr><td colspan="2">No Motor Items</td></tr>
                                                        @endif
                                                    @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>
                                    <div class="kt-portlet__head">
                                        <div class="kt-portlet__head-label">
                                            <h3 class="kt-portlet__head-title">
                                                Note
                                            </h3>
                                        </div>
                                    </div>
                                    <div class="kt-portlet_body">
                                        <div class="kt-section">
                                            <div class="kt-section__content" style="padding-left:20px; padding-right: 20px;">
                                                <table class="table ">
                                                    <tbody>
                                                    <tr>
                                                        <td>{!! $policy->note !!} </td>
                                                    </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                                @if($policy->has_member != 0)
                                    <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>
                                    <div class="kt-portlet__head">
                                        <div class="kt-portlet__head-label">
                                            <h3 class="kt-portlet__head-title">
                                                Family Details
                                            </h3>
                                        </div>
                                    </div>
                                    <div class="kt-portlet_body">
                                        <div class="kt-section">
                                            <div class="kt-section__content" style="padding-left:20px; padding-right: 20px;">
                                                <table class="table table-striped m-table">
                                                    <tbody>
                                                    @foreach($members as $key => $member)
                                                        <tr style="border-top: solid 2px #666;">
                                                            <th>Relation</th>
                                                            <td>{!! $member->relation !!}</td>
                                                            <th>Name</th>
                                                            <td>{!! $member->first_name !!} {!! $member->last_name !!}</td>
                                                        </tr>
                                                        <tr style="border-bottom: solid 2px #666;">
                                                            <th>Date of Birth</th>
                                                            <td>{!! $member->dob !!}</td>
                                                            <th>Gender</th>
                                                            @if($member->gender == 0)
                                                                <td>Female </td>
                                                            @else
                                                                <td>Male</td>
                                                            @endif
                                                        </tr>
                                                    @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                                @if($policy->has_member != 0)
                                    <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>
                                    <div class="kt-portlet__head">
                                        <div class="kt-portlet__head-label">
                                            <h3 class="kt-portlet__head-title">
                                                Beneficiary Details:
                                            </h3>
                                        </div>
                                    </div>
                                    <div class="kt-portlet_body">
                                        <div class="kt-section">
                                            <div class="kt-section__content" style="padding-left:20px; padding-right: 20px;">
                                                <table class="table table-striped m-table">
                                                    <tbody>
                                                        @isset($beneficiaries)
                                                    @foreach($beneficiaries as $key => $beneficiary)
                                                        <tr style="border-top: solid 2px #666;">
                                                            <th>Relation with beneficiary</th>
                                                            <td>{!! $beneficiary->relation !!}</td>
                                                            <th>Name</th>
                                                            <td>{!! $beneficiary->first_name !!} {!! $beneficiary->last_name !!}</td>
                                                        </tr>
                                                        <tr >
                                                            <th>Date of Birth</th>
                                                            <td>{!! $beneficiary->dob !!}</td>
                                                            <th>Gender</th>
                                                            @if($beneficiary->gender == 0)
                                                                <td>Female </td>
                                                            @else
                                                                <td>Male</td>
                                                            @endif
                                                        </tr>
                                                        <tr style="border-bottom: solid 2px #666;">
                                                            <th>Payment</th>
                                                            <td>{!! $beneficiary->payment !!} %</td>
                                                            <th>&nbsp</th>
                                                            <td></td>
                                                        </tr>
                                                    @endforeach
                                                    @endisset
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                                <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>
                                <div class="kt-portlet__head">
                                    <div class="kt-portlet__head-label">
                                        <h3 class="kt-portlet__head-title">
                                            Customer Bank  Details
                                        </h3>
                                    </div>
                                </div>
                                <div class="kt-portlet_body">
                                    <div class="kt-section">
                                        <div class="kt-section__content" style="padding-left:20px; padding-right: 20px;">
                                            {{--<table class="table table-striped m-table">
                                                <tbody>
                                                <tr>
                                                    <th>Myzaka/Orange Money Cell</th>
                                                    <td>{!! $banking->billingCell !!}</td>
                                                    <th>Bank Name</th>
                                                    <td>{!! $banking->bankName !!} </td>
                                                </tr>
                                                <tr>
                                                    <th>Branch Code</th>
                                                    <td>{!! $banking->branchCode !!}</td>
                                                    <th>Account Number</th>
                                                    @if($banking->accountNumber != NULL)
                                                        <td>{!! str_repeat("*", strlen($banking->accountNumber)-4) . substr($banking->accountNumber, -4) !!}</td>
                                                    @endif
                                                </tr>
                                                </tbody>
                                            </table>--}}
                                        </div>
                                    </div>
                                </div>
                                <div class="kt-portlet__foot">

                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                @if($claims->claim_type == 'Accident')
                    <div class="tab-pane @if(session()->get('step') == 5) active @endif" id="kt_portlet_base_demo_3_5_tab_content" role="tabpanel">
                        @include('admin.claimsView.claim_details')
                    </div>
                    <div class="tab-pane @if(session()->get('step') == 6) active @endif" id="kt_portlet_base_demo_3_6_tab_content" role="tabpanel">
                        @include('admin.claimsView.accident')
                    </div>
                    <div class="tab-pane @if(session()->get('step') == 7) active @endif" id="kt_portlet_base_demo_3_7_tab_content" role="tabpanel">
                        @include('admin.claimsView.accident_supplier')
                    </div>
                @endif
                <div class="tab-pane @if(session()->get('step') == 10) active @endif" id="kt_portlet_base_demo_3_10_tab_content" role="tabpanel">
                    @include('admin.claimsView.reserves')
                </div>
                <div class="tab-pane @if(session()->get('step') == 8) active @endif" id="kt_portlet_base_demo_3_8_tab_content" role="tabpanel">
                    @include('admin.claimsView.attachment')
                </div>

                <div class="tab-pane" id="kt_portlet_base_demo_3_9_tab_content" role="tabpanel">
                    <div class="kt-portlet kt-portlet--tabs kt-portlet--height-fluid">
                        <div class="kt-portlet__head">
                            <div class="kt-portlet__head-label">
                                <h3 class="kt-portlet__head-title">
                                    Activity Log
                                </h3>
                            </div>
                            <div class="kt-portlet__head-toolbar">
                                <ul class="nav nav-tabs nav-tabs-line nav-tabs-line-brand nav-tabs-bold" role="tablist">
                                    <li class="nav-item"> <a class="nav-link active" data-toggle="tab" href="#kt_portlet_tabs_1_1_1_content" role="tab"> Today </a></li>
                                </ul>
                            </div>
                        </div>
                        <div class="kt-portlet__body">
                            <div class="tab-content">
                                <div class="tab-pane fade active show" id="kt_portlet_tabs_1_1_1_content" role="tabpanel">
                                    <div class="kt-scroll" data-scroll="true" style="height: 420px;" data-mobile-height="350">
                                        <!--Begin::Timeline -->
                                        <div class="kt-timeline">
                                        </div>
                                        <table class="table table-striped table-bordered table-hover table-checkable" id="claim_activity_table">
                                            <thead>
                                            <tr>
                                                <th>Activity on</th>
                                                <th>Description</th>
                                                <th>Activity By</th>
                                                <th>Activity Done</th>
                                            </tr>
                                            </thead>
                                        </table>
                                        <!--End::Timeline 1 -->
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>



                <div class="tab-pane" id="kt_portlet_base_demo_3_9_tab_content" role="tabpanel">
                    <div class="kt-portlet kt-portlet--tabs kt-portlet--height-fluid">
                        <div class="kt-portlet__head">
                            <div class="kt-portlet__head-label">
                                <h3 class="kt-portlet__head-title">
                                    Activity Log
                                </h3>
                            </div>
                            <div class="kt-portlet__head-toolbar">
                                <ul class="nav nav-tabs nav-tabs-line nav-tabs-line-brand nav-tabs-bold" role="tablist">
                                    <li class="nav-item"> <a class="nav-link active" data-toggle="tab" href="#kt_portlet_tabs_1_1_1_content" role="tab"> Today </a></li>
                                </ul>
                            </div>
                        </div>
                        <div class="kt-portlet__body">
                            <div class="tab-content">
                                <div class="tab-pane fade active show" id="kt_portlet_tabs_1_1_1_content" role="tabpanel">
                                    <div class="kt-scroll" data-scroll="true" style="height: 420px;" data-mobile-height="350">
                                        <!--Begin::Timeline -->
                                        <div class="kt-timeline">
                                        </div>
                                        <table class="table table-striped table-bordered table-hover table-checkable" id="claim_activity_table">
                                            <thead>
                                            <tr>
                                                <th>Activity on</th>
                                                <th>Description</th>
                                                <th>Activity By</th>
                                                <th>Activity Done</th>
                                            </tr>
                                            </thead>
                                        </table>
                                        <!--End::Timeline 1 -->
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="tab-pane @if(session()->get('step') == 2) active @endif" id="kt_portlet_base_demo_3_2_tab_content" role="tabpanel">
                    @if($claims->claim_type == 'Glass')
                        @include('admin.claimsView.vehicle')
                    @endif
                    @if($claims->claim_type == 'Life')
                        @include('admin.claimsView.life')
                    @endif
                    @if($claims->claim_type == 'Legal')
                       @include('admin.claims.legal')
                    @endif
                </div>

                <div class="tab-pane @if(session()->get('step') == 3) active @endif" id="kt_portlet_base_demo_3_3_tab_content" role="tabpanel">
                    <!--begin::Portlet-->
                    <div class="kt-portlet kt-portlet--height-fluid">
                        <div class="kt-portlet__head" >
                            <div class="kt-portlet__head-label">
                                <h3 class="kt-portlet__head-title" id="quotes">
                                    Supplier Quotes
                                </h3>
                            </div>
                        </div>
                        <div class="kt-portlet__body">
                            <!--begin::Section-->
                            <div class="kt-section">
                                <div class="kt-section__content">
                                    <table class="table supplierTable table-head-noborder">
                                        <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Supplier</th>
                                            <th>Amount</th>
                                            <th>Status</th>
                                            <th>Action</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        @if(count($supplierQuotes))
                                            @foreach($supplierQuotes as $key => $quote)
                                                <tr>
                                                    <th scope="row">{!! $key+1 !!}</th>
                                                    <td>{!! $quote->supplier->supplierName !!}</td>
                                                    <td>@if($quote->total != NULL)P {!! $quote->total !!} @else - @endif</td>
                                                    <td>
                                                        @if($quote->total == NULL)
                                                            <span class="kt-font-bold kt-font-danger">Not Received</span>
                                                        @elseif($quote->total != NULL && $quote->status == 0)
                                                            <span class="kt-font-bold kt-font-brand">Quote Received</span>
                                                        @else
                                                            <span class="kt-font-bold kt-font-success">PO SENT</span></br>@if($lowestQuote && $lowestQuote->id != $quote->id && $claims->po != NULL)<p><b>Reason : </b>{!! $quote->select_reason !!}</p>@endif
                                                        @endif
                                                        @if($lowestQuote && $lowestQuote->id == $quote->id && $claims->customer_selected == 0)
                                                            <span class="kt-badge  kt-badge--success kt-badge--inline kt-badge--pill">Lowest Quote</span>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        @if($quote->total != NULL)
                                                            @if($claims->supplier_id == NULL && $quote->status != 1 && $claims->customer_selected == 0)
                                                                <a href="{!! route('admin.claims.acceptQuote',['quote_id'=>$quote->id]) !!}" class="btn btn-sm btn-success @if($claims->po == NULL) POModal @endif" @if($lowestQuote && $lowestQuote->id != $quote->id && $claims->po != NULL) id="selectOther" @endif>Send PO</a>
                                                            @endif
                                                            @if($claims->customer_selected == 1 && $quote->status != 1)
                                                                <a href="{!! route('admin.claims.acceptQuote',['quote_id'=>$quote->id]) !!}" class="btn btn-sm btn-success @if($claims->po == NULL) POModal  @endif">Send PO</a>
                                                            @endif
                                                            <a target="_blank" href="{!! route('admin.supplier.quoteView',['quote_id'=>$quote->id]) !!}" class="btn btn-sm btn-danger">View</a>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        @else
                                            <tr>
                                                <td colspan="5" style="text-align: center;">No Quote Request Sent</td>
                                            </tr>
                                        @endif
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <!--end::Section-->
                        </div>
                    </div>
                    <!--end::Portlet-->
                </div>
                <div class="tab-pane @if(session()->get('step') == 4) active @endif" id="kt_portlet_base_demo_3_4_tab_content" role="tabpanel">
                @if($claims->claim_type == 'Accident')
                    <!--begin::Portlet-->
                        <div class="kt-portlet kt-portlet--height-fluid">
                            <div class="kt-portlet__head">
                                <div class="kt-portlet__head-label">
                                    <h3 class="kt-portlet__head-title">
                                        Supplier Invoices
                                    </h3>
                                </div>
                            </div>
                            <div class="kt-portlet__body">
                                <!--begin::Section-->
                                <div class="kt-section">
                                    <div class="kt-section__content">
                                        <table class="table SupplierInvoiceTable table-head-noborder">
                                            <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Supplier</th>
                                                <th>Invoice</th>
                                                <th>Notes</th>
                                                <th>Action</th>
                                            </tr>
                                            </thead>
                                            <tbody>
                                            @if(count($supplierQuotes))
                                                @foreach($supplierQuotes as $key => $quote)
                                                    <tr>
                                                        <th scope="row">{!! $key+1 !!}</th>
                                                        <td>{!! $quote->supplier->supplierName !!}</td>
                                                        <form action="{{ url('admin/claims/accidentSupplierInvoice/'.$claims->id) }}" method="POST" enctype="multipart/form-data" class="kt-form">
                                                            <!-- CSRF Token -->
                                                            <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                                                            <input type="hidden" name="supplier_id" value="{{ $quote->supplier_id }}" />
                                                            <td>
                                                                @if($quote->invoice  == NULL)
                                                                    <div class="kt-avatar__holder" style="background-image:url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                                                                @else
                                                                    <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($quote->invoice) !!}" target="_blank" download>
                                                                        <img src="{!! AlphaDirect\Helper::getImageSrc($quote->invoice) !!}" style="max-width: 150px;" height="auto" class="gridimg" title="Invoice" alt="Invoice">
                                                                    </a>
                                                                @endif
                                                            </td>
                                                            <td><p>{!! $quote->invoice_notes !!}</p></td>
                                                            <td><a href="{!! $quote->id !!}" class="btn btn-brand resentInvoiceRequest">Resend Invoice Request</a></td>
                                                        </form>
                                                    </tr>
                                                @endforeach
                                            @else
                                                <tr>
                                                    <td colspan="5" style="text-align: center;">No Quote Request Sent</td>
                                                </tr>
                                            @endif
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <!--end::Section-->


                                {{--Resent quote request model--}}
                                <div class="modal fade" id="resent_model" tabindex="-1" role="dialog" aria-labelledby="user_delete_confirm_title" aria-hidden="true">
                                    <form id="resentForm" class="modal-dialog kt-form" method="POST" enctype="multipart/form-data" action="" role="document">
                                        <input type="hidden" name="_token" value="{{ csrf_token() }}" />

                                        <div class="modal-content" id="modal-content">
                                            <div class="modal-header"><h5 class="modal-title">Resend Invoice Request</h5>
                                                <button type="button" class="close" data-dismiss="modal" aria-label="Close"> <span aria-hidden="true">&times;</span> </button>
                                            </div>

                                            <div class="modal-body">
                                                <div class="kt-portlet_body">
                                                    <div class="kt-section">
                                                        <div class="form-group row">
                                                            <div class="col-12">
                                                                <label for="example-text-input" class="col-form-label">Note for Supplier to Resend Invoice</label>
                                                                <textarea  class="form-control" name="resent_note"></textarea>
                                                                <span class="form-text text-muted"></span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button class="btn btn-brand" type="submit">Submit</button>
                                                <button type="button" class="btn btn-outline-brand" data-dismiss="modal">Close</button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>

                        </div>
                </div>
                <!--end::Portlet-->
                @else
                <!--begin::Portlet-->
                    <div class="kt-portlet kt-portlet--height-fluid">
                        <div class="kt-portlet__head">
                            <div class="kt-portlet__head-label">
                                <h3 class="kt-portlet__head-title">
                                    Upload Supplier Invoice
                                </h3>
                            </div>
                        </div>
                        <form id="invoiceUpdate"  action="{{ url('admin/claims/invoiceUpload/'.$claims->id) }}" method="POST" enctype="multipart/form-data" class="kt-form">

                            <!-- CSRF Token -->
                            <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                            <div class="kt-portlet__body">
                                <div class="kt-widget-4">
                                    <div class="form-group row">
                                        <input id="policy_id" class="form-control" type="hidden" name="policy_id" value="{!! $policy->id !!}">
                                        <label for="example-text-input" class="col-3 col-form-label">Upload Invoice</label>
                                        <div class="col-2">
                                            @if($claims->invoice  == NULL)
                                                <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="100%" height="auto" class="gridimg" title="Invoice" alt="Invoice">
                                            @else
                                                <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($claims->invoice) !!}" target="_blank" download>
                                                    <img src="{!! AlphaDirect\Helper::getImageSrc($claims->invoice) !!}" width="100%" height="auto" class="gridimg" title="Invoice" alt="Invoice">
                                                </a>
                                            @endif
                                        </div>
                                        <div class="col-7">
                                            <textarea disabled class="form-control" name="note" placeholder="Add Notes" id="description" rows="3" required>{!! $claims->note !!}</textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </form>
                    </div>
                    <!--end::Portlet-->
                @endif
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
</div>
<!-- end:: Page -->
</div>
<!-- end:: Root -->
<input type="hidden" value="{!! $claims->status !!}" id="statusField">


<!-- begin:: Scrolltop -->
<div id="kt_scrolltop" class="kt-scrolltop"> <i class="la la-arrow-up"></i> </div>
<!-- end:: Scrolltop -->

<div class="modal fade" id="select_supplier" tabindex="-1" role="dialog" aria-labelledby="supplier_confirm_title" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content" id="modal-content">
            <div class="modal-header"><h5 class="modal-title" id="exampleModalLabel">Select Supplier Manually</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"> <span aria-hidden="true">&times;</span> </button>
            </div>
            <form id="acceptQuote" action="" method="POST" enctype="multipart/form-data" class="kt-form">
                <!-- CSRF Token -->
                <input type="hidden" name="_token" value="{{ csrf_token() }}" >
                <input type="hidden" name="sendsubmit" id="sendsubmit" value="" >
                <div class="modal-body">
                    <h6>Why do you want to overwrite system selection ?</h6>
                    <textarea class="form-control" name="reason"></textarea>
                </div>
                <div class="modal-footer">
                    <button type="submit" value="Submit" class="btn btn-brand">Select Supplier</button>
                    <button type="button" class="btn btn-outline-brand" data-dismiss="modal">Close</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="POModal" tabindex="-1" role="dialog" aria-labelledby="user_delete_confirm_title" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content" id="modal-content">
            <div class="modal-header"><h5 class="modal-title" id="exampleModalLabel">PO Upload</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"> <span aria-hidden="true">&times;</span> </button>
            </div>
            <div class="modal-body">
                <h5>Please Upload PO to Send PO to Supplier</h5>
            </div>
            <div class="modal-footer">
                <a  class="btn btn-success collapsed" data-dismiss="modal" style="float:right; color:white; margin-right: 10px;" id="uploadPO2" data-toggle="collapse" data-target="#POcollapse" aria-expanded="false"  aria-controls="collapseExample"> Submit & Send  </a>
                <button type="button" class="btn btn-outline-brand" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

{{--claims status model div--}}
@if($claims->claim_type == 'Accident')
    <div class="modal fade" id="claimStatus" tabindex="-1" role="dialog" aria-labelledby="user_delete_confirm_title" aria-hidden="true">
        <div class="modal-dialog" role="document" style="max-width:700px;">
            <div class="modal-content" id="modal-content" style="width:700px;">
                <div class="modal-header"><h5 class="modal-title" id="exampleModalLabel">Claim Sub Status Information</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"> <span aria-hidden="true">&times;</span> </button>
                </div>
                <div class="modal-body">

                    {{--cash in lieu--}}
                    @if($claimAccident->claim_sub_status == "Cash In Lieu")
                        <h5>Cash in Lieu Sub Status Details</h5>
                        <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>

                        <div class="kt-portlet__head">
                            <div class="kt-portlet__head-label">
                                <h5 class="kt-portlet__head-title">
                                    Customer KYC
                                </h5>
                            </div>
                        </div>
                        <div class="kt-portlet_body">
                            <div class="kt-section">
                                <div class="kt-section__content" style="padding-left:20px; padding-right: 20px;">
                                    {{-- <table class="table table-striped m-table">
                                         <tbody>
                                         <div class="form-group row">
                                             <div class="col-md-2">
                                                 <h3 class="col-form-label" style="float: left;">Driving License</h3>
                                                 @if($kyc->driving_license == NULL)
                                                     <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="100%" height="auto" >
                                                 @else
                                                     <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($kyc->driving_license) !!}" target="_blank" download>
                                                         <img src="{{\AlphaDirect\Helper::getCloudFrontURL($kyc->driving_license)}}" width="100%" height="auto" >
                                                     </a>
                                                 @endif
                                                 <div class="kt-avatar" style="float: left; clear: left;"></div>
                                             </div>

                                             <div class="col-md-2">
                                                 <h3 class="col-form-label" style="float: left;">Omang ID</h3>
                                                 @if($kyc->omang == NULL)
                                                     <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="100%" height="auto" >
                                                 @else
                                                     <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($kyc->omang) !!}" target="_blank" download>
                                                         <img src="{{\AlphaDirect\Helper::getCloudFrontURL($kyc->omang)}}" width="100%" height="auto" >
                                                     </a>
                                                 @endif
                                                 <div class="kt-avatar" style="float: left; clear: left;"></div>
                                             </div>

                                             <div class="col-md-2">
                                                 <h3 class="col-form-label" style="float: left;">Proof of Residence</h3>
                                                 @if($kyc->proof_residence == NULL)
                                                     <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="100%" height="auto" >
                                                 @else
                                                     <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($kyc->proof_residence) !!}" target="_blank" download>
                                                         <img src="{{\AlphaDirect\Helper::getCloudFrontURL($kyc->proof_residence)}}" width="100%" height="auto" >
                                                     </a>
                                                 @endif
                                                 <div class="kt-avatar" style="float: left; clear: left;">
                                                 </div>
                                             </div>

                                             <div class="col-md-2">
                                                 <h3 class="col-form-label" style="float: left;">Proof of Income</h3>
                                                 @if($kyc->proof_income == NULL)
                                                     <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="100%" height="auto" >
                                                 @else
                                                     <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($kyc->proof_income) !!}" target="_blank" download>
                                                         <img src="{{\AlphaDirect\Helper::getCloudFrontURL($kyc->proof_income)}}" width="100%" height="auto" >
                                                     </a>
                                                 @endif
                                                 <div class="kt-avatar" style="float: left; clear: left;">
                                                 </div>
                                             </div>

                                             <div class="col-md-2">
                                                 <h3 class="col-form-label" style="float: left;">Passport</h3>
                                                 @if($kyc->passport == NULL)
                                                     <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="100%" height="auto" >
                                                 @else
                                                     <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($kyc->passport) !!}" target="_blank" download>
                                                         <img src="{{\AlphaDirect\Helper::getCloudFrontURL($kyc->passport)}}" width="100%" height="auto" >
                                                     </a>
                                                 @endif
                                                 <div class="kt-avatar" style="float: left; clear: left;">
                                                 </div>
                                             </div>
                                         </div>
                                         </tbody>
                                     </table>--}}
                                </div>
                            </div>
                        </div>

                        <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>
                        <div class="kt-portlet__head">
                            <div class="kt-portlet__head-label">
                                <h5 class="kt-portlet__head-title">
                                    Customer Bank  Details
                                </h5>
                            </div>
                        </div>
                        <div class="kt-portlet_body">
                            <div class="kt-section">
                                <div class="kt-section__content" style="padding-left:20px; padding-right: 20px;">
                                    {{--<table class="table table-striped m-table">
                                        <tbody>
                                        <tr>
                                            <th>Myzaka/Orange Money Cell</th>
                                            <td>{!! $banking->billingCell !!}</td>
                                            <th>Bank Name</th>
                                            <td>{!! $banking->bankName !!} </td>
                                        </tr>
                                        <tr>
                                            <th>Branch Code</th>
                                            <td>{!! $banking->branchCode !!}</td>
                                            <th>Account Number</th>
                                            @if($banking->accountNumber != NULL)
                                                <td>{!! str_repeat("*", strlen($banking->accountNumber)-4) . substr($banking->accountNumber, -4) !!}</td>
                                            @endif
                                        </tr>
                                        </tbody>
                                    </table>--}}
                                </div>
                            </div>
                        </div>
                        <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>
                        <div class="col-md-2">
                            <h5 class="col-form-label" style="float: left;">Additional Form</h5>
                            @if($claimAccident->document == NULL)
                                <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="100%" height="auto" >
                            @else
                                <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($claimAccident->document) !!}" target="_blank" download>
                                    <img src="{{\AlphaDirect\Helper::getCloudFrontURL($claimAccident->document)}}" width="100%" height="auto" >
                                </a>
                            @endif
                            <div class="kt-avatar" style="float: left; clear: left;"></div>
                        </div>


                        {{-- Write Off --}}
                    @elseif($claimAccident->claim_sub_status == "Write Off")
                        <h5>Write Off Claim Sub Status Details</h5>
                        <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>

                        <div class="kt-portlet__head">
                            <div class="kt-portlet__head-label">
                                <h5 class="kt-portlet__head-title">
                                    Customer KYC
                                </h5>
                            </div>
                        </div>
                        <div class="kt-portlet_body">
                            <div class="kt-section">
                                <div class="kt-section__content" style="padding-left:20px; padding-right: 20px;">
                                    {{-- <table class="table table-striped m-table">
                                         <tbody>
                                         <div class="form-group row">
                                             <div class="col-md-2">
                                                 <h3 class="col-form-label" style="float: left;">Driving License</h3>
                                                 @if($kyc->driving_license == NULL)
                                                     <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="100%" height="auto" >
                                                 @else
                                                     <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($kyc->driving_license) !!}" target="_blank" download>
                                                         <img src="{{\AlphaDirect\Helper::getCloudFrontURL($kyc->driving_license)}}" width="100%" height="auto" >
                                                     </a>
                                                 @endif
                                                 <div class="kt-avatar" style="float: left; clear: left;"></div>
                                             </div>

                                             <div class="col-md-2">
                                                 <h3 class="col-form-label" style="float: left;">Omang ID</h3>
                                                 @if($kyc->omang == NULL)
                                                     <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="100%" height="auto" >
                                                 @else
                                                     <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($kyc->omang) !!}" target="_blank" download>
                                                         <img src="{{\AlphaDirect\Helper::getCloudFrontURL($kyc->omang)}}" width="100%" height="auto" >
                                                     </a>
                                                 @endif
                                                 <div class="kt-avatar" style="float: left; clear: left;"></div>
                                             </div>

                                             <div class="col-md-2">
                                                 <h3 class="col-form-label" style="float: left;">Proof of Residence</h3>
                                                 @if($kyc->proof_residence == NULL)
                                                     <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="100%" height="auto" >
                                                 @else
                                                     <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($kyc->proof_residence) !!}" target="_blank" download>
                                                         <img src="{{\AlphaDirect\Helper::getCloudFrontURL($kyc->proof_residence)}}" width="100%" height="auto" >
                                                     </a>
                                                 @endif
                                                 <div class="kt-avatar" style="float: left; clear: left;">
                                                 </div>
                                             </div>

                                             <div class="col-md-2">
                                                 <h3 class="col-form-label" style="float: left;">Proof of Income</h3>
                                                 @if($kyc->proof_income == NULL)
                                                     <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="100%" height="auto" >
                                                 @else
                                                     <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($kyc->proof_income) !!}" target="_blank" download>
                                                         <img src="{{\AlphaDirect\Helper::getCloudFrontURL($kyc->proof_income)}}" width="100%" height="auto" >
                                                     </a>
                                                 @endif
                                                 <div class="kt-avatar" style="float: left; clear: left;">
                                                 </div>
                                             </div>

                                             <div class="col-md-2">
                                                 <h3 class="col-form-label" style="float: left;">Passport</h3>
                                                 @if($kyc->passport == NULL)
                                                     <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="100%" height="auto" >
                                                 @else
                                                     <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($kyc->passport) !!}" target="_blank" download>
                                                         <img src="{{\AlphaDirect\Helper::getCloudFrontURL($kyc->passport)}}" width="100%" height="auto" >
                                                     </a>
                                                 @endif
                                                 <div class="kt-avatar" style="float: left; clear: left;">
                                                 </div>
                                             </div>
                                         </div>
                                         </tbody>
                                     </table>--}}
                                </div>
                            </div>
                        </div>

                        <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>
                        <div class="kt-portlet__head">
                            <div class="kt-portlet__head-label">
                                <h5 class="kt-portlet__head-title">
                                    Customer Bank  Details
                                </h5>
                            </div>
                        </div>
                        <div class="kt-portlet_body">
                            <div class="kt-section">
                                <div class="kt-section__content" style="padding-left:20px; padding-right: 20px;">
                                    {{--<table class="table table-striped m-table">
                                        <tbody>
                                        <tr>
                                            <th>Myzaka/Orange Money Cell</th>
                                            <td>{!! $banking->billingCell !!}</td>
                                            <th>Bank Name</th>
                                            <td>{!! $banking->bankName !!} </td>
                                        </tr>
                                        <tr>
                                            <th>Branch Code</th>
                                            <td>{!! $banking->branchCode !!}</td>
                                            <th>Account Number</th>
                                            @if($banking->accountNumber != NULL)
                                                <td>{!! str_repeat("*", strlen($banking->accountNumber)-4) . substr($banking->accountNumber, -4) !!}</td>
                                            @endif
                                        </tr>
                                        </tbody>
                                    </table>--}}
                                </div>
                            </div>
                        </div>
                        <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>
                        <div class="col-md-2">
                            <h5 class="col-form-label" style="float: left;">Additional Form</h5>
                            @if($claimAccident->document == NULL)
                                <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="100%" height="auto" >
                            @else
                                <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($claimAccident->document) !!}" target="_blank" download>
                                    <img src="{{\AlphaDirect\Helper::getCloudFrontURL($claimAccident->document)}}" width="100%" height="auto" >
                                </a>
                            @endif
                            <div class="kt-avatar" style="float: left; clear: left;"></div>
                        </div>
                        <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>

                        <br>
                        <div class="kt-portlet__head">
                            <div class="kt-portlet__head-label">
                                <h5 class="kt-portlet__head-title">
                                    Salvage Yard Details
                                </h5>
                            </div>
                        </div>
                        <br>
                        <div class="row">
                            <div class="col-md-12">
                                <table class="table table-striped m-table">
                                    <tbody>
                                    <tr>
                                        <th>Send mail to Salvage Yard ?</th>
                                        @if($claimAccident->is_salvage_yard == 0)
                                            <td>No</td>
                                        @else
                                            <td>Yes</td>
                                        @endif
                                    </tr>
                                    @if($salvageUser != NULL && $claimAccident->is_salvage_yard == 1)
                                        <tr>
                                            <th>Name of Salvage Yard</th>
                                            <td>{{$salvageUser->firstName}} {{$salvageUser->lastName}}</td>
                                        </tr>
                                    @endif
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        {{-- Exgratia --}}
                    @elseif($claimAccident->claim_sub_status == "Exgratia")
                        <h5>Exgratia Claim Sub Status Details</h5>
                        <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>

                        <div class="kt-portlet__head">
                            <div class="kt-portlet__head-label">
                                <h5 class="kt-portlet__head-title">
                                    Customer KYC
                                </h5>
                            </div>
                        </div>
                        <div class="kt-portlet_body">
                            <div class="kt-section">
                                <div class="kt-section__content" style="padding-left:20px; padding-right: 20px;">
                                    {{--  <table class="table table-striped m-table">
                                          <tbody>
                                          <div class="form-group row">
                                              <div class="col-md-2">
                                                  <h3 class="col-form-label" style="float: left;">Driving License</h3>
                                                  @if($kyc->driving_license == NULL)
                                                      <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="100%" height="auto" >
                                                  @else
                                                      <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($kyc->driving_license) !!}" target="_blank" download>
                                                          <img src="{{\AlphaDirect\Helper::getCloudFrontURL($kyc->driving_license)}}" width="100%" height="auto" >
                                                      </a>
                                                  @endif
                                                  <div class="kt-avatar" style="float: left; clear: left;"></div>
                                              </div>

                                              <div class="col-md-2">
                                                  <h3 class="col-form-label" style="float: left;">Omang ID</h3>
                                                  @if($kyc->omang == NULL)
                                                      <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="100%" height="auto" >
                                                  @else
                                                      <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($kyc->omang) !!}" target="_blank" download>
                                                          <img src="{{\AlphaDirect\Helper::getCloudFrontURL($kyc->omang)}}" width="100%" height="auto" >
                                                      </a>
                                                  @endif
                                                  <div class="kt-avatar" style="float: left; clear: left;"></div>
                                              </div>

                                              <div class="col-md-2">
                                                  <h3 class="col-form-label" style="float: left;">Proof of Residence</h3>
                                                  @if($kyc->proof_residence == NULL)
                                                      <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="100%" height="auto" >
                                                  @else
                                                      <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($kyc->proof_residence) !!}" target="_blank" download>
                                                          <img src="{{\AlphaDirect\Helper::getCloudFrontURL($kyc->proof_residence)}}" width="100%" height="auto" >
                                                      </a>
                                                  @endif
                                                  <div class="kt-avatar" style="float: left; clear: left;">
                                                  </div>
                                              </div>

                                              <div class="col-md-2">
                                                  <h3 class="col-form-label" style="float: left;">Proof of Income</h3>
                                                  @if($kyc->proof_income == NULL)
                                                      <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="100%" height="auto" >
                                                  @else
                                                      <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($kyc->proof_income) !!}" target="_blank" download>
                                                          <img src="{{\AlphaDirect\Helper::getCloudFrontURL($kyc->proof_income)}}" width="100%" height="auto" >
                                                      </a>
                                                  @endif
                                                  <div class="kt-avatar" style="float: left; clear: left;">
                                                  </div>
                                              </div>

                                              <div class="col-md-2">
                                                  <h3 class="col-form-label" style="float: left;">Passport</h3>
                                                  @if($kyc->passport == NULL)
                                                      <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="100%" height="auto" >
                                                  @else
                                                      <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($kyc->passport) !!}" target="_blank" download>
                                                          <img src="{{\AlphaDirect\Helper::getCloudFrontURL($kyc->passport)}}" width="100%" height="auto" >
                                                      </a>
                                                  @endif
                                                  <div class="kt-avatar" style="float: left; clear: left;">
                                                  </div>
                                              </div>
                                          </div>
                                          </tbody>
                                      </table>--}}
                                </div>
                            </div>
                        </div>

                        <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>
                        <div class="kt-portlet__head">
                            <div class="kt-portlet__head-label">
                                <h5 class="kt-portlet__head-title">
                                    Customer Bank  Details
                                </h5>
                            </div>
                        </div>
                        <div class="kt-portlet_body">
                            <div class="kt-section">
                                <div class="kt-section__content" style="padding-left:20px; padding-right: 20px;">
                                    {{-- <table class="table table-striped m-table">
                                         <tbody>
                                         <tr>
                                             <th>Myzaka/Orange Money Cell</th>
                                             <td>{!! $banking->billingCell !!}</td>
                                             <th>Bank Name</th>
                                             <td>{!! $banking->bankName !!} </td>
                                         </tr>
                                         <tr>
                                             <th>Branch Code</th>
                                             <td>{!! $banking->branchCode !!}</td>
                                             <th>Account Number</th>
                                             @if($banking->accountNumber != NULL)
                                                 <td>{!! str_repeat("*", strlen($banking->accountNumber)-4) . substr($banking->accountNumber, -4) !!}</td>
                                             @endif
                                         </tr>
                                         </tbody>
                                     </table>--}}
                                </div>
                            </div>
                        </div>
                        <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>
                        <div class="col-md-2">
                            <h5 class="col-form-label" style="float: left;">Additional Form</h5>
                            @if($claimAccident->document == NULL)
                                <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="100%" height="auto" >
                            @else
                                <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($claimAccident->document) !!}" target="_blank" download>
                                    <img src="{{\AlphaDirect\Helper::getCloudFrontURL($claimAccident->document)}}" width="100%" height="auto" >
                                </a>
                            @endif
                            <div class="kt-avatar" style="float: left; clear: left;"></div>
                        </div>
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-brand" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
@endif

{{--claims reject status model div--}}
@if($claims->claim_type == 'Accident')
    <div class="modal fade" id="claimRejectStatus" tabindex="-1" role="dialog" aria-labelledby="user_delete_confirm_title" aria-hidden="true">
        <div class="modal-dialog" role="document" style="max-width:700px;">
            <div class="modal-content" id="modal-content" style="width:700px;">
                <div class="modal-header"><h5 class="modal-title" id="exampleModalLabel">Claim Reject Status Information</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"> <span aria-hidden="true">&times;</span> </button>
                </div>
                <div class="modal-body">
                    <h5>Reject Status Details</h5>
                    <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>
                    <div class="kt-portlet_body">
                        <div class="kt-section">
                            <div class="kt-section__content" style="padding-left:20px; padding-right: 20px;">
                                <table class="table table-striped m-table">
                                    <tr>
                                        <th>Reason</th>
                                        <td>{{ $claimAccident->reason }}</td>
                                    </tr>
                                    <tr>
                                        <th>Note</th>
                                        <td>{{ $claimAccident->note }}</td>
                                    </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <h5 class="col-form-label" style="float: left;">Additional Form</h5>
                        @if($claimAccident->document == NULL)
                            <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="100%" height="auto" >
                        @else
                            <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($claimAccident->document) !!}" target="_blank" download>
                                <img src="{{\AlphaDirect\Helper::getCloudFrontURL($claimAccident->document)}}" width="100%" height="auto" >
                            </a>
                        @endif
                        <div class="kt-avatar" style="float: left; clear: left;"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-brand" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
@endif




@include('admin.layouts.scripts')
<script src="{{ asset('assets/vendors/general/bootstrap-select/dist/js/bootstrap-select.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/app/custom/general/components/forms/widgets/bootstrap-select.js') }}" type="text/javascript"></script>

<script src="{{  asset('assets/vendors/general/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}" type="text/javascript"></script>
<script src="{{  asset('assets/vendors/custom/components/vendors/bootstrap-datepicker/init.js') }}" type="text/javascript"></script>

<script src="{{asset('assets/vendors/general/jquery.repeater/src/lib.js')}}" type="text/javascript"></script>
<script src="{{asset('assets/vendors/general/jquery.repeater/src/jquery.input.js')}}" type="text/javascript"></script>
<script src="{{asset('assets/vendors/general/jquery.repeater/src/repeater.js')}}" type="text/javascript"></script>
<script src="{{asset('assets/app/custom/general/components/forms/layouts/repeater.js')}}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/general/jquery-validation/dist/jquery.validate.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/custom/components/vendors/jquery-validation/init.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/general/jquery-validation/dist/additional-methods.js') }}" type="text/javascript"></script>
<script src="{{  asset('assets/vendors/custom/datatables/datatables.bundle.js') }}" type="text/javascript"></script>


@if($claims->claim_type == 'Life')
    <script>
        "use strict";
        // Class definition
        var KTFormControls = function () {
// Private functions
            jQuery.validator.addMethod("future", function(value, element) {
                return this.optional(element) || Date.parse(value) < new Date().getTime(); //use Date.now() if you can instead of getTime, it's nicer
            }, "Please enter only past dates");

            var demo1 = function () {
                $( "#storeClaim" ).validate({
                    ignore: [],
                    // define validation rules
                    rules: {
                        date_of_death: {
                            required: true,
                            future: true
                        },
                        description: {
                            required: true
                        },

                    },
                    messages: {
                        date_of_death: {
                            required: "Please enter Date of Death",
                            future: "Please enter only past dates"
                        },
                        description: "Please enter Description",
                    },
                    //display error alert on form submit
                    invalidHandler: function(event, validator) {
                        $('html, body').animate({
                            scrollTop: $(validator.errorList[0].element).offset().top - 200
                        }, 1000);
                    },
                    submitHandler: function (form) {
                        form.submit(); // submit the form
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
    </script>
@endif

@if($claims->claim_type == 'Glass')
    <script>
        "use strict";
        // Class definition
        var KTFormControls = function () {
            // Private functions
            jQuery.validator.addMethod("future", function(value, element) {
                return this.optional(element) || Date.parse(value) < new Date().getTime(); //use Date.now() if you can instead of getTime, it's nicer
            }, "Please enter only past dates");
            //Vehicle Registration
            jQuery.validator.addMethod("license", function(value, element) {
                return this.optional( element ) || /^[Bb]{1}\d{3}[a-zA-Z]{3}$/.test( value );
            }, 'Sorry, We only accept Botswana registered vehicles Eg: B123ABC');
            var demo1 = function () {
                $( "#storeClaim" ).validate({
                    ignore: [],
                    // define validation rules
                    rules: {
                        incidentDate: {
                            required: true,
                            future: true
                        },
                        cause: {
                            required: true
                        },

                    },
                    messages: {
                        incidentDate: {
                            required: "Please enter Date of Damage",
                            future: "Please enter only past dates"
                        },
                        cause: "Please enter Description",
                    },
                    //display error alert on form submit
                    invalidHandler: function(event, validator) {
                        $('html, body').animate({
                            scrollTop: $(validator.errorList[0].element).offset().top - 200
                        }, 1000);
                    },
                    submitHandler: function (form) {
                        form.submit(); // submit the form
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


    </script>
@endif

@if($claims->claim_type == 'Accident')
    <script>
        "use strict";
        // Class definition
        var KTFormControls = function () {
            // Private functions
            jQuery.validator.addMethod("future", function(value, element) {
                return this.optional(element) || Date.parse(value) < new Date().getTime(); //use Date.now() if you can instead of getTime, it's nicer
            }, "Please enter only past dates");
            //Vehicle Registration
            jQuery.validator.addMethod("license", function(value, element) {
                return this.optional( element ) || /^[Bb]{1}\d{3}[a-zA-Z]{3}$/.test( value );
            }, 'Sorry, We only accept Botswana registered vehicles Eg: B123ABC');

            var demo2 = function () {
                $( "#claimsUpdate" ).validate({
                    // define validation rules
                    rules: {
                        claim_number: {
                            required: true
                        },
                        location: {
                            required: true
                        },
                        claim_sub_status: {
                            required: true
                        },
                        claim_status: {
                            required: true
                        },
                        paid_amount: {
                            required: true
                        },
                        first_visit: {
                            required: true,
                            future:true
                        },
                        claim_allocated_on: {
                            required: true
                        },
                        claim_allocated_to: {
                            required: true
                        },
                        co_attorney_assigned_date: {
                            required: true,
                            future:true
                        },
                        attorney_assigned_date: {
                            required: true,
                            future:true

                        },
                        co_attorney: {
                            required: true
                        },
                        primary_attorney: {
                            required: true
                        },
                        event_name: {
                            required: true
                        },
                        representative: {
                            required: true
                        },
                        incident_date: {
                            required: true,
                            future:true
                        },
                        claim_type: {
                            required: true
                        },
                        loss_type:{
                            required: true
                        },
                        claim_sub_type: {
                            required: true
                        },
                        reported_by: {
                            required: true
                        },
                        loss_description: {
                            required: true
                        },
                        date_of_accident:{
                            required: true,
                            future:true
                        },
                        place_of_accident:{
                            required: true,
                        },
                        driver_dob:{
                            future:true
                        },
                        time_of_accident:{
                            required: true
                        }
                    },

                    //display error alert on form submit
                    invalidHandler: function(event, validator) {
                        $('html, body').animate({
                            scrollTop: $(validator.errorList[0].element).offset().top - 200
                        }, 1000);
                    },

                    submitHandler: function (form) {
                        form[0].submit(); // submit the form
                    }
                });
            }
            return {
                // public functions
                init: function() {
                    demo2();
                }
            };
        }();

        /*assessor validation*/
        var assessorControl = function () {
            var assessor = function () {
                $( "#assessorForm" ).validate({
                    // define validation rules
                    rules: {
                        assessor:{
                            required: true
                        },
                        attachFile:{
                            required: true
                        },
                    },

                    //display error alert on form submit
                    invalidHandler: function(event, validator) {
                        $('html, body').animate({
                            scrollTop: $(validator.errorList[0].element).offset().top - 200
                        }, 1000);
                    },

                    submitHandler: function (form) {
                        $('#mail').hide();
                        $('#Btn').show();
                        form[0].submit(); // submit the form
                    }
                });
            }
            return {
                // public functions
                init: function() {
                    assessor();
                }
            };

        }();

        /*assessor validation*/
        var poControl = function () {
            var po = function () {
                $( "#POUpdate" ).validate({
                    // define validation rules
                    rules: {
                        po:{
                            required: true
                        },

                    },

                    //display error alert on form submit
                    invalidHandler: function(event, validator) {
                        $('html, body').animate({
                            scrollTop: $(validator.errorList[0].element).offset().top - 200
                        }, 1000);
                        $('#poSbtBtn').show();
                        $('#poloadBtn').hide();
                    },

                    submitHandler: function (form) {
                        $('#poSbtBtn').hide();
                        $('#poloadBtn').show();
                        form[0].submit(); // submit the form
                    }
                });
            }
            return {
                // public functions
                init: function() {
                    po();
                }
            };

        }();

        /*Reserves Validation*/

        var reserveControl = function () {
            var reserve = function () {
                $( "#reserveForm" ).validate({
                    // define validation rules
                    rules: {
                        date  :{
                            required: true
                        },
                        trans_type  :{
                            required: true
                        },
                        address :{
                            required: true
                        },
                        payee :{
                            required: true
                        },
                    },

                    //display error alert on form submit
                    invalidHandler: function(event, validator) {
                        $('html, body').animate({
                            scrollTop: $(validator.errorList[0].element).offset().top - 200
                        }, 1000);
                        $('#submitReserve').show();
                        $('#ReserveloadBtn').hide();
                    },

                    submitHandler: function (form) {

                        $('#submitReserve').hide();
                        $('#ReserveloadBtn').show();
                        form[0].submit(); // submit the form
                    }
                });
            }
            return {
                // public functions
                init: function() {
                    reserve();
                }
            };

        }();

        jQuery(document).ready(function() {
            assessorControl.init();
            reserveControl.init();
            poControl.init();

            $('#Suppliercollapse').on('click', '.addSupplierFile', function() {
                var count = $(this).parent().parent().children().children(':first-child').attr('class').split(' ').pop();
                if(!$.isNumeric(count))
                {
                    var array = $(this).parent().parent().children().children(':first-child').attr('class').split(' ');
                    count = array[array.length - 2];
                }
                var append = '';
                append += '<div class="kt-avatar multiple_file_avatar '+ count +'" style="float: left; clear: left; margin: 0 20px 20px 0;">' +
                    '<div class="kt-avatar__holder" style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>' +
                    '<label class="kt-avatar__upload" data-toggle="kt-tooltip" title="Change Image">' +
                    '<i class="fa fa-pen fileupload"></i>' +
                    '<input type="file" class="accident_file" name="accident_file['+ count +'][]" />' +
                    '</label>' +
                    '<span class="kt-avatar__cancel" data-toggle="kt-tooltip" title="Cancel Image"> <i class="fa fa-times"></i> </span>' +
                    '</div>';

                $(this).parent().parent().children(':first-child').prepend(append);
                KTAvatarDemo.init();
            });
        });

    </script>
@endif
<script>
    $(document).on("click", "#addButton", function() {
        var count = $('.DivCountAttachment').length;
        var append = '';
        append += '<div class="kt-portlet__body">' +

            '<div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>' +
            ' <div class="form-group row">'+
            '<label for="example-text-input" class="col-3 col-form-label">Name</label>'+
            '<div class="col-6">'+
            '<input type="text" class="form-control" name="name['+ count +']"  title="Name is required" placeholder="Enter Name" required>'+
            '</div>'+
            '</div>'+
            '<div class="form-group row DivCountAttachment">' +
            '<label for="example-text-input" class="col-3 col-form-label">Document Type</label>' +
            '<div class="col-6">' +
            '<select class="form-control kt_selectpicker" data-live-search="true" title="Please Select document type" name="type['+ count +']" required>' +
            '@isset($fileNames) @foreach($fileNames as $fileName)' +
            '<option value="{!! $fileName->value !!}">{!! $fileName->value !!}</option>' +
            '@endforeach @endisset' +
            '</select>' +
            '</div></div>' +
            '<div class="form-group row">' +
            '<label for="example-text-input" class="col-3 col-form-label">Attachment</label>' +
            '<div class="col-lg-9">' +
            '<div class="input-group appendFileDiv '+ count +'">' +
            '<div class="kt-avatar multiple_file_avatar" style="float: left; clear: left;">' +
            '<div class="kt-avatar__holder" style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>' +
            '<label class="kt-avatar__upload" data-toggle="kt-tooltip" title="Change Image">' +
            '<i class="fa fa-pen"></i>' +
            '<input type="file" class="attachment_file" name="attachment_file['+ count +'][]" />' +
            '</label>' +
            '<span class="kt-avatar__cancel" data-toggle="kt-tooltip" title="Cancel Image"> <i class="fa fa-times"></i> </span>' +
            '</div></div>' +
            '<br>'+
            '<div class="kt-separator kt-separator--space-sm"></div>' +
            '<div>' +
            '<span class="btn btn-info btn-sm addAttachment"> <i class="la la-plus"></i> Add </span>' +
            '</div></div>' +
            '<div>' +
            '<input type="button" class="btn btn-warning btn-sm removeAttachmentDiv" value="Remove">' +
            '</div>'

        $('#attachmentDiv').append(append);
        KTAvatarDemo.init();
    });
</script>

@if($claims->claim_type == 'Accident')
    <script>
        $(document).on("click", "#addSupplierButton", function() {
            var count = $('.DivCount').length;
            var append = '';
            append += '<div class="kt-portlet__body">' +
                '<div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>' +
                '<div class="form-group row DivCount">' +
                '<label for="example-text-input" class="col-3 col-form-label">Suppliers</label>' +
                '<div class="col-6">' +
                '<select class="form-control kt_selectpicker" data-live-search="true" title="Please Select Supplier" name="supplier_id['+ count +']" required>' +
                '@foreach($suppliers as $supplier)' +
                '<option value="{!! $supplier->id !!}">{!! $supplier->supplierName !!}</option>' +
                '@endforeach' +
                '</select>' +
                '</div></div>' +
                '<div class="form-group row">' +
                '<label for="example-text-input" class="col-3 col-form-label">Additional File</label>' +
                '<div class="col-lg-9">' +
                '<div class="input-group appendFileDiv">' +
                '<div class="kt-avatar multiple_file_avatar '+ count +'" style="float: left; clear: left;">' +
                '<div class="kt-avatar__holder" style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>' +
                '<label class="kt-avatar__upload" data-toggle="kt-tooltip" title="Change Image">' +
                '<i class="fa fa-pen"></i>' +
                '<input type="file" class="accident_file" name="accident_file['+ count +'][]" />' +
                '</label>' +
                '<span class="kt-avatar__cancel" data-toggle="kt-tooltip" title="Cancel Image"> <i class="fa fa-times"></i> </span>' +
                '</div></div>' +
                '<br>'+
                '<div class="kt-separator kt-separator--space-sm"></div>' +
                '<div>' +
                '<span class="btn btn-info btn-sm addSupplierFile"> <i class="la la-plus"></i> Add </span>' +
                '</div></div>' +
                '<div>' +
                '<input type="button" class="btn btn-warning btn-sm" id="removeSupplierFile" value="Remove">' +
                '</div>' +
                '</div></div>';

            $('#supplierDiv').append(append);
            KTAvatarDemo.init();
        });
    </script>

@endif

<script>

    $(".supplierTable").on("click", "a#selectOther" , function(event) {
        event.preventDefault();
        $("#acceptQuote").attr('action', $(this).attr("href"));
        $('#select_supplier').modal('show');
    });


    $(".supplierTable").on("click", "a.POModal" , function(event) {
        event.preventDefault();
        $('#POModal').modal('show');
    });


    "use strict";
    // Class definition
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
                todayHighlight: true,
                orientation: "bottom left",
                templates: arrows,
                format: 'yyyy-mm-dd'
            });
        }
        return {
            // public functions
            init: function() {
                demos();
            }
        };
    }();

    // Avatar Class definition
    var KTAvatarDemo = function() {
        return {
            // Init demos
            init: function() {
                var avatar1 = new KTAvatar('driving_license');
                var avatar2 = new KTAvatar('omang_pic');
                var avatar3 = new KTAvatar('proof_residence');
                var avatar4 = new KTAvatar('proof_income');
                var avatar5 = new KTAvatar('passport_pic');
                var avatar7 = new KTAvatar('incidentBack');
                var avatar8 = new KTAvatar('incidentRight');
                var avatar9 = new KTAvatar('incidentLeft');
                var avatar10 = new KTAvatar('po');
                var avatar11 = new KTAvatar('multiple_file_avatar');

                // accident blade avatar variable
                var avatar12 = new KTAvatar('accident_omang_pic');
                var avatar13 = new KTAvatar('accident_proof_residence');
                var avatar14 = new KTAvatar('accident_proof_income');
                var avatar15 = new KTAvatar('accident_passport_pic');
                var avatar16 = new KTAvatar('accident_file');
                var avatar17 = new KTAvatar('invoice');
                var avatar18 = new KTAvatar('accident_driving_license');
                var avatar19 = new KTAvatar('invoiceAccident');
                var avatar20 = new KTAvatar('claimStatusFile');
                var avatar21 = new KTAvatar('typeDiv');
                var avatar22 = new KTAvatar('incidentFront');

                var avatar23 = new KTAvatar('death_certificate');

            }
        };
    }();

    //Status Update Validation
    "use strict";
    // Class definition
    var KTFormControlsStatus = function () {
        jQuery.validator.addMethod("future", function(value, element) {
            return this.optional(element) || Date.parse(value) < new Date().getTime(); //use Date.now() if you can instead of getTime, it's nicer
        }, "Please enter only past dates");
        var demoStatus = function () {
            $( "#statusUpdate" ).validate({
                ignore: [],
                // define validation rules
                rules: {
                    claim_allocated_on: {
                        required: true,
                        future: true
                    },
                    claim_allocated_to: {
                        required: true
                    },
                    reserve_amount: {
                        required: true
                    },
                    paid_amount: {
                        required: true
                    },

                },
                messages: {
                    claim_allocated_on: {
                        required: "Please enter Claim Allocated On",
                        future: "Please enter only past dates"
                    },
                    claim_allocated_to: "Please enter Claim Allocated To",
                    reserve_amount: "Please enter Reserve Amount",
                    paid_amount: "Please enter Paid Amount",
                },
                //display error alert on form submit
                invalidHandler: function(event, validator) {
                    $('html, body').animate({
                        scrollTop: $(validator.errorList[0].element).offset().top - 200
                    }, 1000);
                },
                submitHandler: function (form) {
                    form.submit(); // submit the form
                }
            });
        }
        return {
            // public functions
            init: function() {
                demoStatus();
            }
        };
    }();

    jQuery(document).ready(function() {
        KTBootstrapDatepicker.init();
        KTFormControlsStatus.init();
        KTFormControls.init();
        // Class initialization on page load
        KTAvatarDemo.init();
        $('#attachmentDiv').on('click', '.addAttachment', function() {
            var count = $(this).parent().parent().children(':first-child').attr('class').split(' ').pop();
            var append = '';
            append += '<div class="kt-avatar multiple_file_avatar" style="float: left; clear: left; margin: 0 20px 20px 0;">' +
                '<div class="kt-avatar__holder" style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>' +
                '<label class="kt-avatar__upload" data-toggle="kt-tooltip" title="Change Image">' +
                '<i class="fa fa-pen"></i>' +
                '<input type="file" class="attachment_file" name="attachment_file['+ count +'][]" />' +
                '</label>' +
                '<span class="kt-avatar__cancel" data-toggle="kt-tooltip" title="Cancel Image"> <i class="fa fa-times"></i> </span>' +
                '</div>';

            $(this).parent().parent().children(':first-child').prepend(append);
            KTAvatarDemo.init();
        });

        $('#Suppliercollapse').on('click', '#removeSupplierFile', function() {
            $(this).parent().parent().parent().delay(100).slideUp(500);
            $(this).parent().parent().parent().remove();
        });

        $('#attachmentDiv').on('click', '.removeAttachmentDiv', function() {

            $(this).parent().parent().parent().remove();
        });

        $('#quoteRequest').on('click', function() {
            if($('#POcollapse').hasClass('show'))
                $('#POcollapse').collapse("hide");
        });
        $('#attachmentUpload').on('click', function() {
            if($('#attachment').hasClass('show'))
                $('#attachment').collapse("hide");
        });

        $('#uploadPO').on('click', function() {
            if($('#Suppliercollapse').hasClass('show'))
                $('#Suppliercollapse').collapse("hide");
        });
        $('#uploadPO2').on('click', function() {
            if($('#Suppliercollapse').hasClass('show'))
                $('#Suppliercollapse').collapse("show");
        });
        $('#uploadPO2').click(function() {
            $('html, body').animate({
                scrollTop: $("#quotes").offset().top -400
            }, 1000);
        });
        $('#SendSubmit').on('click', function() {
            if($('#Suppliercollapse').hasClass('show'))
                $('#Suppliercollapse').collapse("hide");
        });


        $('a.POModal').on('click', function() {
            var Url = $(this). attr("href");
            var parts = Url.split("/");
            var last_part = parts[parts.length-1];
            $("#dynamicSupplierId").val(last_part);
        });


        $('#uploadPO2').on('click', function() {
            $("#toshow").show();
            $("#send_submit").val('1');
        });


        $('#addButton').on('click', function() {
            if($('#reserveCollapse').hasClass('show'))
                $('#reserveCollapse').collapse("hide");
        });



    });



    @if(session()->has('message'))
    Toastify({
        text: "{{session()->get('message')}}",
        duration: 4000,
        newWindow: true,
        gravity: "top", // `top` or `bottom`
        positionRight: true, // `true` or `false`
        backgroundColor: "#00C851",
    }).showToast();


    @endif
</script>

<script>
    function setToEdit() {
        var x = document.getElementById("claimEditDiv");
        var y = document.getElementById("claimLabelDiv");

        x.style.display = "block";
        y.style.display = "none";
    }

    function setToView(){
        var x = document.getElementById("claimEditDiv");
        var y = document.getElementById("claimLabelDiv");
        x.style.display = "none";
        y.style.display = "block";
    }

</script>

<script>
    function TransactionType(e){
        var type_id= e.options[e.selectedIndex].value;
        /* Transaction type for Loss Payment from lookup data*/
        $("#coverageTable").show();
        $('.allocation').attr("required","true");
        if(type_id == 43){
            $("#subTypeDiv").show();
            $("#allocationText").html("Payment Allocation");
            $('#invoiceDate').attr("required","true");
            $('#dueDate').attr("required", "true");
            $('#invoiceNum').attr("required", "true");
            $('#memoOnCheck').attr("required", "true");
            $('#trans_subType').attr("required", "true");
            $('#description').attr("required", "true");
        }
        else{
            $("#subTypeDiv").hide();
            $("#allocationText").html("Reserve Allocation");

            $('#invoiceDate').removeAttr("required");
            $('#dueDate').removeAttr("required");
            $('#invoiceNum').removeAttr("required");
            $('#memoOnCheck').removeAttr("required");
            $('#trans_subType').removeAttr("required");
            $('#description').removeAttr("required");
        }
    };

    function setVehicleToEdit() {
        var x = document.getElementById("vehicleEditDiv");
        var y = document.getElementById("vehicleLabelDiv");
        x.style.display = "block";
        y.style.display = "none";

        // Class initialization on page load
        KTAvatarDemo.init();
    }

    function setVehicleToView(){
        var x = document.getElementById("vehicleEditDiv");
        var y = document.getElementById("vehicleLabelDiv");
        x.style.display = "none";
        y.style.display = "block";
    }

</script>

<script>

    function setClaimToEdit() {
        var x = document.getElementById("claimEditDiv");
        var y = document.getElementById("claimLabelDiv");
        var z = document.getElementById("recipientDiv");
        var w = document.getElementById("otherInfoDiv");
        x.style.display = "block";
        z.style.display = "block";
        w.style.display = "block";
        y.style.display = "none";

        // Class initialization on page load
        KTAvatarDemo.init();
    }

    function setStatusToEdit()
    {
        var d = document.getElementById("StatusEditDiv");
        var c = document.getElementById("StatusLabelDiv");

        c.style.display = "none";

        d.style.display = "block";
    }

    function setStatusToView(){
        var d = document.getElementById("StatusEditDiv");
        var c = document.getElementById("StatusLabelDiv");
        c.style.display = "block";
        d.style.display = "none";
    }
    function setClaimToView(){
        var x = document.getElementById("claimEditDiv");
        var y = document.getElementById("claimLabelDiv");
        var z = document.getElementById("recipientDiv");
        var w = document.getElementById("otherInfoDiv");
        x.style.display = "none";
        z.style.display = "none";
        w.style.display = "none";
        y.style.display = "block";
    }

</script>


<script>
    function vehicleMsg(){
        var isChecked=document.getElementById("vehicleValue").checked;
        if (isChecked){
            document.getElementById("Msg").innerHTML="Yes";
            document.getElementById("Msg").style.color="cornflowerblue";
            var x = document.getElementById("sup");
            x.style.display = "block";

            $('#sup').delay(100).slideDown(500);
            $('#sup').rules('add',  { required: true, messages: { required: "Please Select Coverages" } });
        }
        else {
            document.getElementById("Msg").innerHTML="No";
            document.getElementById("Msg").style.color="#ff4d4d";

            var x = document.getElementById("sup");
            x.style.display = "none";

            $('#sup').delay(100).slideUp(500);
            $('#sup').rules('remove',  'required');
        }

    }
</script>
<script>
    $('.kt-repeater__add-data').on('click',".btn-success", function(){

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

    });
</script>

<script>
    function thirdPartyMsg(){
        var isChecked=document.getElementById("thirdPartyValue").checked;
        if (isChecked){
            document.getElementById("Msg").innerHTML="Yes";
            document.getElementById("Msg").style.color="cornflowerblue";
            $('#detailDiv').delay(100).slideDown(500);
            $('.coverage').rules('add',  { required: true, messages: { required: "Please Select Coverages" } });
        }
        else {
            document.getElementById("Msg").innerHTML="No";
            document.getElementById("Msg").style.color="#ff4d4d";
            $('#details').delay(100).slideUp(500);
            $('#detailDiv').delay(100).slideUp(500);
            $('.coverage').rules('remove',  'required');
        }

    }

    function checkAttorney(){
        var attorneyChecked=document.getElementById("attorneyValue").checked;
        if (attorneyChecked){
            $('#attorneyDiv').delay(100).slideDown(500);
        }
        else {
            $('#attorneyDiv').delay(100).slideUp(500);
        }
    }

    function checkSalvageYard(){
        var salvageChecked=document.getElementById("salvage").checked;
        if (salvageChecked){
            $('#SalvageList').delay(100).slideDown(500);
            $(".salvage_yard").prop('required',true);
        }
        else {
            $('#SalvageList').delay(100).slideUp(500);
            $(".salvage_yard").prop('required',false);
        }
        KTFormControlsStatus.init();
    }

    function openSubStatus(sel) {
        var value = sel.options[sel.selectedIndex].text;
        if (value == "Approved") {
            $('#sub_statusDiv').delay(100).slideDown(500);
            $('#uploadFile').delay(100).slideUp(500);
            $('#selectStatus').delay(100).slideUp(500);
            $('#dashSeparator').delay(100).slideUp(500);
            $('#SalvageDiv').delay(100).slideUp(500);

            //Reject Validations
            $('input[name="reason"]').prop('required',false);
            $('input[name="note"]').prop('required',false);

        }
        else if(value == "Pending")
        {
            $('#selectStatus').delay(100).slideUp(500);
            $('#sub_statusDiv').delay(100).slideUp(500);
            $('#dashSeparator').delay(100).slideUp(500);
            $('#selectCash').delay(100).slideUp(500);
            $('#selectWrite').delay(100).slideUp(500);
            $('#uploadFile').delay(100).slideUp(500);
            $('#SalvageDiv').delay(100).slideUp(500);
            $('.check_beneficiary').delay(100).slideUp(500);
            $('#add_details').delay(100).slideUp(500);
            $('#beneficiary_status_details').delay(100).slideUp(500);
            $('#substatus_repeater').delay(100).slideUp(500);

            //Reject Validations
            $('input[name="reason"]').removeAttr("required");
            $('input[name="note"]').removeAttr("required");

            //Approve Validations
            $('#accident_billingCell').removeAttr("required");
            $('#accident_bankNumDropDown').removeAttr("required");
            $('#accident_bankBranchDropDown').removeAttr("required");
            $('#accident_accountNumber').removeAttr("required");
            $('#accountType').removeAttr("required");

        }
        else
        {
            $('#sub_statusDiv').delay(100).slideUp(500);
            $('#dashSeparator').delay(100).slideDown(500);
            $('#selectStatus').delay(100).slideDown(500);
            $('#selectCash').delay(100).slideUp(500);
            $('#selectWrite').delay(100).slideUp(500);
            $('#uploadFile').delay(100).slideDown(500);
            $('#SalvageDiv').delay(100).slideUp(500);
            $('.check_beneficiary').delay(100).slideUp(500);
            $('#add_details').delay(100).slideUp(500);
            $('#addbeneficiary_btn').delay(100).slideUp(500);
            $('#substatus_repeater').delay(100).slideUp(500);



            //Reject Validations
            $('input[name="reason"]').prop('required',true);
            $('input[name="note"]').prop('required',true);

            //Approve Validations
            $('#accident_billingCell').removeAttr("required");
            $('#accident_bankNumDropDown').removeAttr("required");
            $('#accident_bankBranchDropDown').removeAttr("required");
            $('#accident_accountNumber').removeAttr("required");
            $('#accountType').removeAttr("required");
        }
    }


    /* select status function*/
    function selectStatus(sel)
    {
        var isSelected=sel.options[sel.selectedIndex].text;

        if (isSelected == "Repair")
        {
            $('#cashform').css('display','none');
            $('#dashSeparator').delay(100).slideUp(500);
            $('#selectStatus').delay(100).slideUp(500);
            $('#selectCash').delay(100).slideUp(500);
            $('#selectWrite').delay(100).slideUp(500);
            $('#uploadFile').delay(100).slideUp(500);
            $('#SalvageDiv').delay(100).slideUp(500);
            $('#SalvageList').delay(100).slideUp(500);
            $('.check_beneficiary').delay(100).slideUp(500);
            $('#add_details').delay(100).slideUp(500);
            $('#substatus_repeater').delay(100).slideUp(500);


            //Approve Validations
            $('#accident_billingCell').prop('required',false);
            $('#accident_bankNumDropDown').prop('required',false);
            $('#accident_bankBranchDropDown').prop('required',false);
            $('#accident_accountNumber').prop('required',false);
            $('#accountType').prop('required',false);
            $('input[name="reason"]').prop('required',false);
            $('input[name="note"]').prop('required',false);
        }
        else if(isSelected == "Cash In Lieu")
        {
            $('#cashform').css('display','block');
            $('#dashSeparator').delay(100).slideDown(500);
            $('#selectCash').delay(100).slideDown(500);
            $('#selectStatus').delay(100).slideUp(500);
            $('#selectWrite').delay(100).slideUp(500);
            $('#uploadFile').delay(100).slideDown(500);
            $('#SalvageDiv').delay(100).slideUp(500);
            $('#SalvageList').delay(100).slideUp(500);
            $('.check_beneficiary').delay(100).slideDown(500);
            /*
                        $('#addbeneficiary_btn').delay(100).slideDown(500);
            */
            $('#substatus_repeater').delay(100).slideDown(500);
            $('#add_details').delay(100).slideDown(500);





            $('#accident_billingCell').attr("required", "true");
            $('#accident_bankNumDropDown').attr("required", "true");
            $('#accident_bankBranchDropDown').attr("required", "true");
            $('#accident_accountNumber').attr("required", "true");
            $('#accountType').attr("required", "true");
            $('input[name="reason"]').prop('required',false);
            $('input[name="note"]').prop('required',false);

        }
        else if(isSelected == "Write Off")
        {
            $('#cashform').css('display','none');
            $('#selectStatus').delay(100).slideUp(500);
            $('#dashSeparator').delay(100).slideDown(500);
            $('#uploadFile').delay(100).slideDown(500);
            $('#selectCash').delay(100).slideDown(500);
            $('#selectWrite').delay(100).slideDown(500);
            $('#SalvageDiv').delay(100).slideDown(500);
            $('.check_beneficiary').delay(100).slideDown(500);
            /*
                        $('#addbeneficiary_btn').delay(100).slideDown(500);
            */
            $('#substatus_repeater').delay(100).slideDown(500);
            $('#add_details').delay(100).slideDown(500);



            $('#accident_billingCell').attr("required", "true");
            $('#accident_bankNumDropDown').attr("required", "true");
            $('#accident_bankBranchDropDown').attr("required", "true");
            $('#accident_accountNumber').attr("required", "true");
            $('#accountType').attr("required", "true");
            $('input[name="reason"]').prop('required',false);
            $('input[name="note"]').prop('required',false);


        }
        else if(isSelected == "Exgratia"){
            $('#cashform').css('display','none');
            $('#dashSeparator').delay(100).slideDown(500);
            $('#selectCash').delay(100).slideDown(500);
            $('#selectStatus').delay(100).slideUp(500);
            $('#SalvageDiv').delay(100).slideUp(500);
            $('#selectWrite').delay(100).slideDown(500);
            $('#SalvageList').delay(100).slideUp(500);
            $('#uploadFile').delay(100).slideDown(500);
            $('.check_beneficiary').delay(100).slideDown(500);
            /*
                        $('#addbeneficiary_btn').delay(100).slideDown(500);
            */
            $('#substatus_repeater').delay(100).slideDown(500);
            $('#add_details').delay(100).slideDown(500);


            $('#accident_billingCell').attr("required", "true");
            $('#accident_bankNumDropDown').attr("required", "true");
            $('#accident_bankBranchDropDown').attr("required", "true");
            $('#accident_accountNumber').attr("required", "true");
            $('#accountType').attr("required", "true");
            $('input[name="reason"]').prop('required',false);
            $('input[name="note"]').prop('required',false);


        }
    }

    $(document).ready(function () {
        $('#selectValue').trigger('change');
    });

    $('#addBeneficiary').click(function () {
        KTBootstrapDatepicker.init();
        $('#addBeneficiaryDiv').toggle();
        $('#addBeneficiary').toggle();
    });

    $('#addPassenger').click(function () {
        KTBootstrapDatepicker.init();
        $('#addPassengerDiv').toggle();
        $('#addPassenger').toggle();
    });

    $('#addbeneficiary_btn').click(function () {
        KTBootstrapDatepicker.init();
        $('#add_details').toggle();
        $('#addbeneficiary_btn').toggle();
        $('#otherparty_list').selectpicker('render');
        $('#otherparty_list').selectpicker('refresh');
    });

    $('#claimStatusBtn').click(function () {
        $('#claimStatus').modal('show')
    });

    $('#claimStatusRejectBtn').click(function () {
        $('#claimRejectStatus').modal('show')
    });

    $(".SupplierInvoiceTable").on("click", ".resentInvoiceRequest" , function(event) {
        event.preventDefault();
        var url = '{{ route('admin.claims.acceptQuote', ":slug") }}';
        url = url.replace(':slug', $(this).attr("href"));
        $("#resentForm").attr('action', url);
        $('#resent_model').modal('show');
    });


    $(".col-12").on('click', '.kt-repeater__add-data', function(){
        KTBootstrapDatepicker.init();
    });

    $('#detailsBtn').click(function () {
        $('#detailDiv').toggle();
        $('#detailsBtn').toggle();
    });


    $('input[name="customer_selected"]').click(function(){
        if($(this).prop("checked") == true){
            $('#slocation').attr("required");
            $('#sname').attr("required", "true");
            $('#stype').attr("required", "true");
            $('#vat').attr("required", "true");
        }
        else if($(this).prop("checked") == false){
            $('#slocation').removeAttr("required");
            $('#sname').removeAttr("required");
            $('#stype').removeAttr("required");
            $('#vat').removeAttr("required");
        }
    });

</script>

<script>
    "use strict";
    var KTDatatablesDataSourceAjaxServer = function() {

        var initTable2 = function() {
            var table = $('#attatchment_table');

            // begin first table
            table.DataTable({
                responsive: true,
                searchDelay: 500,
                language:{
                    processing : "<img src='{{asset('img/loading.gif')}}'>"
                },
                processing: true,
                serverSide: true,
                ajax: '{!! route('admin.claims.attachmentData',$claims->id) !!}',
                columns: [
                    {data: 'name'},
                    {data: 'type'},
                    {data: 'attachment'},
                ],
            });
        };


        var initTable4 = function() {
            var table = $('#coverage_table');

            // begin first table
            table.DataTable({
                responsive: true,
                searchDelay: 500,
                language:{
                    processing : "<img src='{{asset('img/loading.gif')}}'>"
                },
                processing: true,
                serverSide: true,
                ajax: '{!! route('admin.claims.coverageData', $claims->id) !!}',
                columns: [
                    {data: 'date'},
                    {data: 'transaction_type'},
                    {data: 'transaction_sub_type'},
                    {data: 'reserve_amts'},
                    {data: 'payment_amts'},
                    {data: 'baln'},
                    {data: 'payee'},
                ],
            });
        };

        /*Activity log table */
        /* var initTable5 = function() {
             var table = $('#claim_activity_table');

             table.DataTable({
                 responsive: true,
                 searchDelay: 500,
                 processing: true,
                 serverSide: true,
                 columnDefs: [ { type: 'date', 'targets': [3] } ], order: [[ 3, 'desc' ]],
                 ajax: 'route('admin.claims.activity',$claims->id)'
                 columns: [
                     {data: 'subject_type'},
                     {data: 'description'},
                     {data: 'causer_id'},
                     {data: 'created_at'},
                 ],

             });
         };*/
        return {
            //main function to initiate the module
            init: function() {
                initTable2();
                //initTable3();
                initTable4();
            }
        };
    }();

    jQuery(document).ready(function() {
        KTDatatablesDataSourceAjaxServer.init();
    });

    $("form").submit(function(){
        $("#sbtBtn").hide();
        $("#loadBtn").show();
    });


</script>

<script>
    $(document).ready(function() {
        var ajaxRequest;
        var length = $('.amt').length;
        $('.amt').keyup(function() {
            var sum = 0;
            $(".amt").each(function(){
                if($(this).val() != "")
                    sum += parseInt($(this).val());
            });
            var value = sum;
            var policy_id = $("#policy_id").val();
            clearTimeout(ajaxRequest);
            ajaxRequest = setTimeout(function(sn) {
                $.ajax({
                    url: '{{ route('admin.claims.checkSumAssured') }}',
                    data: {
                        "_token": "{{ csrf_token() }}",
                        "sum": value,
                        "policy_id": policy_id
                    },
                    type: 'post',
                    datatype : 'json',
                    success: function (data) {
                        if(data.error) {
                            $('.sum_value').html(data.value);
                            if(length == 1){
                                $('#sumError').css('display','block');
                            }

                            else{
                                $('#sumError').css('display','none');
                                $('#sumError2').css('display','block');
                                $('.amt').addClass('inputControl');
                            }
                            $('#submitReserve').attr("disabled",true);
                        } else {
                            $('#sumError').css('display','none');
                            $('#sumError2').css('display','none');
                            $('#submitReserve').attr("disabled",false);
                            $('.amt').removeClass('inputControl');
                        }
                    }
                });


            }, 500, value);
        });
    });
</script>

<script>

    /*ajax on radio button for customer */
    $('#beneficiary_status_details').on('click', '.check_beneficiary', function() {
        var customerbanking;
        var ajaxRequest;
        var Checkval = $(this).val();
        var claim_id = $('#claim_id').val();
        if (Checkval == 'customer') {
            var customer_id = $('#customer_id').val();
            $('.new_otherparty_dropdown').css('display', 'none');

            ajaxRequest = setTimeout(function(sn) {
                $.ajax({
                    url: '{{ route('admin.claims.getCustomerData') }}',
                    data: {
                        "_token": "{{ csrf_token() }}",
                        "customer_id": customer_id,
                        "claim_id": claim_id,
                    },
                    type: 'post',
                    datatype : 'json',
                    success: function (data) {
                        if( data.length != 0) {
                            customerbanking = data;
                        }

                        $('.details_div').css('display', 'block');
                        $(".accident_billingCell").val(customerbanking.customerbanking.billingCell);
                        $(".accident_bankName").val(customerbanking.customerbanking.bankName);
                        $(".accident_branchCode").val(customerbanking.customerbanking.branchCode);
                        $(".accident_accountNumber").val(customerbanking.customerbanking.accountNumber);
                        var driving_license = customerbanking.kyc.driving_license;
                        var omang = customerbanking.kyc.omang;
                        var proof_residence = customerbanking.kyc.proof_residence;
                        var proof_income = customerbanking.kyc.proof_income;
                        var passport = customerbanking.kyc.passport;

                        if(driving_license != null)
                        {
                            $("#driving_license").empty().html("<div class=\"kt-avatar__holder\" style=\"background-image:url('{!! env('AWS_CLOUDFRONT') !!}'"+driving_license+")\"></div>"+
                                " <label class=\"kt-avatar__upload\" data-toggle=\"kt-tooltip\" title=\"Change Image\">"+
                                " <i class=\"fa fa-pen\"></i>"+
                                " <input type='file' class=\"driving_license\" name=\"driving_license\"accept=\"image/*,application/pdf \" data-msg-accept=\"Only Images and PDF files are allowed\" />"+
                                "  </label>"+
                                " <span class=\"kt-avatar__cancel\" data-toggle=\"kt-tooltip\" title=\"Cancel Image\"> <i class=\"fa fa-times\"></i> </span>\n");
                        }
                        else{
                            $("#driving_license").empty().html("  <div class=\"kt-avatar__holder\" id=\"driving_license\" style=\"background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)\";></div>");
                        }

                        if(omang != null)
                        {
                            $("#omang_pic").empty().html("<div class=\"kt-avatar__holder\" style=\"background-image:url('{!! env('AWS_CLOUDFRONT') !!}'"+omang+")\"></div>"+
                                " <label class=\"kt-avatar__upload\" data-toggle=\"kt-tooltip\" title=\"Change Image\">"+
                                " <i class=\"fa fa-pen\"></i>"+
                                " <input type='file' class=\"driving_license\" name=\"driving_license\"accept=\"image/*,application/pdf\" data-msg-accept=\"Only Images and PDF files are allowed\"/>"+
                                "  </label>"+
                                " <span class=\"kt-avatar__cancel\" data-toggle=\"kt-tooltip\" title=\"Cancel Image\"> <i class=\"fa fa-times\"></i> </span>\n");
                        }
                        else{
                            $("#omang_pic").empty().html("  <div class=\"kt-avatar__holder\" id=\"driving_license\" style=\"background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)\";></div>");
                        }

                        if(proof_residence != null)
                        {
                            $("#proof_residence").empty().html("<div class=\"kt-avatar__holder\" style=\"background-image:url('{!! env('AWS_CLOUDFRONT') !!}'"+proof_residence+")\"></div>"+
                                " <label class=\"kt-avatar__upload\" data-toggle=\"kt-tooltip\" title=\"Change Image\">"+
                                " <i class=\"fa fa-pen\"></i>"+
                                " <input type='file' class=\"driving_license\" name=\"driving_license\"accept=\"image/*,application/pdf\" data-msg-accept=\"Only Images and PDF files are allowed\"/>"+
                                "  </label>"+
                                " <span class=\"kt-avatar__cancel\" data-toggle=\"kt-tooltip\" title=\"Cancel Image\"> <i class=\"fa fa-times\"></i> </span>\n");
                        }
                        else{
                            $("#proof_residence").empty().html("  <div class=\"kt-avatar__holder\" id=\"driving_license\" style=\"background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)\";></div>");
                        }

                        if(proof_income != null)
                        {
                            $("#proof_income").empty().html("<div class=\"kt-avatar__holder\" style=\"background-image:url('{!! env('AWS_CLOUDFRONT') !!}'"+proof_income+")\"></div>"+
                                " <label class=\"kt-avatar__upload\" data-toggle=\"kt-tooltip\" title=\"Change Image\">"+
                                " <i class=\"fa fa-pen\"></i>"+
                                " <input type='file' class=\"driving_license\" name=\"driving_license\"accept=\"image/*,application/pdf\" data-msg-accept=\"Only Images and PDF files are allowed\"/>"+
                                "  </label>"+
                                " <span class=\"kt-avatar__cancel\" data-toggle=\"kt-tooltip\" title=\"Cancel Image\"> <i class=\"fa fa-times\"></i> </span>\n");
                        }
                        else{
                            $("#proof_income").empty().html("  <div class=\"kt-avatar__holder\" id=\"driving_license\" style=\"background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)\";></div>");
                        }

                        if(passport != null)
                        {
                            $("#passport_pic").empty().html("<div class=\"kt-avatar__holder\" style=\"background-image:url('{!! env('AWS_CLOUDFRONT') !!}'"+passport+")\"></div>"+
                                " <label class=\"kt-avatar__upload\" data-toggle=\"kt-tooltip\" title=\"Change Image\">"+
                                " <i class=\"fa fa-pen\"></i>"+
                                " <input type='file' class=\"driving_license\" name=\"driving_license\"accept=\"image/*,application/pdf\" data-msg-accept=\"Only Images and PDF files are allowed\"/>"+
                                "  </label>"+
                                " <span class=\"kt-avatar__cancel\" data-toggle=\"kt-tooltip\" title=\"Cancel Image\"> <i class=\"fa fa-times\"></i> </span>\n");
                        }
                        else{
                            $("#passport_pic").empty().html("  <div class=\"kt-avatar__holder\" id=\"driving_license\" style=\"background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)\";></div>");
                        }

                    }
                });
            }, 200);

        }
        else {
            $('.new_otherparty_dropdown').css('display', 'block');
            $('.details_div').css('display', 'none');
        }
    });

    $( "#attachmentForm" ).submit(function( event ) {


        $('#attachmentloadBtn').show();
        $('#attachmentloadBtn').prop('disabled','true');
        $('#attachmentsbtBtn').hide();

    });

    $( "#accidentSupplierForm" ).submit(function( event ) {
        $('#quoteSbtBtn').hide();
        $('#quoteloadBtn').show();
        $('#quoteloadBtn').prop('disabled','true');

    });

    $( "#reserveForm" ).submit(function( event ) {
        $('#submitReserve').hide();
        $('#ReserveloadBtn').show();
        $('#ReserveloadBtn').prop('disabled','true');

    });

    $( "#POUpdate" ).submit(function( event ) {
        $('#poSbtBtn').hide();
        $('#toshow').hide();
        $('#poloadBtn').show();
        $('#poloadBtn').prop('disabled','true');

    });


    /*ajax on dropdown for otherparty */

    $(".new_otherparty_dropdown").on("change", function () {

        var ajaxRequest;
        var claim_id = $('#claim_id').val();
        var otherparty_id = $('.new_otherparty_dropdown option:selected').val();
        ajaxRequest = setTimeout(function(sn) {
            $.ajax({
                url: '{{ route('admin.claims.getOtherPartyData') }}',
                data: {
                    "_token": "{{ csrf_token() }}",
                    "otherparty_id": otherparty_id,
                    "claim_id": claim_id,
                },
                type: 'post',
                datatype : 'json',
                success: function (data) {
                    if( data.length != 0) {
                        otherPartyBanking = data;

                    }

                    $('.details_div').css('display', 'block');
                    $(".accident_billingCell").val(otherPartyBanking.otherPartyBanking.billingCell);
                    $(".accident_bankName").val(otherPartyBanking.otherPartyBanking.bankName);
                    $(".accident_branchCode").val(otherPartyBanking.otherPartyBanking.branchCode);
                    $(".accident_accountNumber").val(otherPartyBanking.otherPartyBanking.accountNumber);


                    var driving_license = otherPartyBanking.kyc.driving_license;
                    var omang_pic = otherPartyBanking.kyc.omang_pic;
                    var proof_residence = otherPartyBanking.kyc.proof_residence;
                    var proof_income = otherPartyBanking.kyc.proof_income;
                    var passport_pic = otherPartyBanking.kyc.passport_pic;



                    if(driving_license != null)
                    {
                        $("#driving_license").empty().html("<div class=\"kt-avatar__holder\" style=\"background-image:url('{!! env('AWS_CLOUDFRONT') !!}'"+driving_license+")\"></div>"+
                            " <label class=\"kt-avatar__upload\" data-toggle=\"kt-tooltip\" title=\"Change Image\">"+
                            " <i class=\"fa fa-pen\"></i>"+
                            " <input type='file' class=\"driving_license\" name=\"driving_license\"accept=\"image/*,application/pdf\" data-msg-accept=\"Only Images and PDF files are allowed\"/>"+
                            "  </label>"+
                            " <span class=\"kt-avatar__cancel\" data-toggle=\"kt-tooltip\" title=\"Cancel Image\"> <i class=\"fa fa-times\"></i> </span>\n");
                    }
                    else{
                        $("#driving_license").empty().html("  <div class=\"kt-avatar__holder\" id=\"driving_license\" style=\"background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)\";></div>");
                    }


                    if(omang_pic != null)
                    {
                        $("#omang_pic").empty().html("<div class=\"kt-avatar__holder\" style=\"background-image:url('{!! env('AWS_CLOUDFRONT') !!}'"+omang_pic+")\"></div>"+
                            " <label class=\"kt-avatar__upload\" data-toggle=\"kt-tooltip\" title=\"Change Image\">"+
                            " <i class=\"fa fa-pen\"></i>"+
                            " <input type='file' class=\"driving_license\" name=\"driving_license\"accept=\"image/*,application/pdf\" data-msg-accept=\"Only Images and PDF files are allowed\"/>"+
                            "  </label>"+
                            " <span class=\"kt-avatar__cancel\" data-toggle=\"kt-tooltip\" title=\"Cancel Image\"> <i class=\"fa fa-times\"></i> </span>\n");
                    }
                    else{
                        $("#omang_pic").empty().html("  <div class=\"kt-avatar__holder\" id=\"driving_license\" style=\"background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)\";></div>");
                    }

                    if(proof_residence != null)
                    {
                        $("#proof_residence").empty().html("<div class=\"kt-avatar__holder\" style=\"background-image:url('{!! env('AWS_CLOUDFRONT') !!}'"+proof_residence+")\"></div>"+
                            " <label class=\"kt-avatar__upload\" data-toggle=\"kt-tooltip\" title=\"Change Image\">"+
                            " <i class=\"fa fa-pen\"></i>"+
                            " <input type='file' class=\"driving_license\" name=\"driving_license\"accept=\"image/*,application/pdf\" data-msg-accept=\"Only Images and PDF files are allowed\"/>"+
                            "  </label>"+
                            " <span class=\"kt-avatar__cancel\" data-toggle=\"kt-tooltip\" title=\"Cancel Image\"> <i class=\"fa fa-times\"></i> </span>\n");
                    }
                    else{
                        $("#proof_residence").empty().html("  <div class=\"kt-avatar__holder\" id=\"driving_license\" style=\"background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)\";></div>");
                    }

                    if(proof_income != null)
                    {
                        $("#proof_income").empty().html("<div class=\"kt-avatar__holder\" style=\"background-image:url('{!! env('AWS_CLOUDFRONT') !!}'"+proof_income+")\"></div>"+
                            " <label class=\"kt-avatar__upload\" data-toggle=\"kt-tooltip\" title=\"Change Image\">"+
                            " <i class=\"fa fa-pen\"></i>"+
                            " <input type='file' class=\"driving_license\" name=\"driving_license\"accept=\"image/*,application/pdf\" data-msg-accept=\"Only Images and PDF files are allowed\"/>"+
                            "  </label>"+
                            " <span class=\"kt-avatar__cancel\" data-toggle=\"kt-tooltip\" title=\"Cancel Image\"> <i class=\"fa fa-times\"></i> </span>\n");
                    }
                    else{
                        $("#proof_income").empty().html("  <div class=\"kt-avatar__holder\" id=\"driving_license\" style=\"background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)\";></div>");
                    }

                    if(passport_pic != null)
                    {
                        $("#passport_pic").empty().html("<div class=\"kt-avatar__holder\" style=\"background-image:url('{!! env('AWS_CLOUDFRONT') !!}'"+passport_pic+")\"></div>"+
                            " <label class=\"kt-avatar__upload\" data-toggle=\"kt-tooltip\" title=\"Change Image\">"+
                            " <i class=\"fa fa-pen\"></i>"+
                            " <input type='file' class=\"driving_license\" name=\"driving_license\"accept=\"image/*,application/pdf\" data-msg-accept=\"Only Images and PDF files are allowed\"/>"+
                            "  </label>"+
                            " <span class=\"kt-avatar__cancel\" data-toggle=\"kt-tooltip\" title=\"Cancel Image\"> <i class=\"fa fa-times\"></i> </span>\n");
                    }
                    else{
                        $("#passport_pic").empty().html("  <div class=\"kt-avatar__holder\" id=\"driving_license\" style=\"background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)\";></div>");
                    }



                }
            });
        }, 200);

        $('.details_div').css('display', 'block');

    });

</script>


<script>
    /*validation for total reserve amount*/
    function delay(callback, ms) {
        var timer = 0;
        return function() {
            var context = this, args = arguments;
            clearTimeout(timer);
            timer = setTimeout(function () {
                callback.apply(context, args);
            }, ms || 0);
        };
    }

    $(document).ready(function() {
        var ajaxRequest;
        $('#reserve_amount').keyup(delay(function (e) {
            var reserve_amount = $('#reserve_amount').val();
            var policyId = $('#policyId').val();
            ajaxRequest = setTimeout(function(sn) {
                $.ajax({
                    url: '{{ route('admin.claims.checkreserve_amount') }}',
                    data: {
                        "_token": "{{ csrf_token() }}",
                        "reserve_amount": reserve_amount,
                        "policyId":policyId
                    },
                    type: 'post',
                    datatype : 'json',
                    success: function (data) {
                        if( data.error == 0) {
                            $( "#submitBtn" ).prop( "disabled", false );
                            $('#reserveMsg').css('display', 'none');
                        }
                        else
                        {
                            $('#reserveMsg').html('Please enter reserve amount less than '+ ' '+data.sum_assured);
                            $('#reserveMsg').css('display', 'block');
                            $('#submitBtn').prop('disabled',true);
                        }
                    }
                });
            }, 200);
        },400));
    });
</script>

<script>
    {{-- script for vehicle make dropdown starts--}}
    $(document).ready(function(){
        var  ajaxRequest;
        $(".addOtherDetails").on("click",function(){
            $('.make').selectpicker("refresh");
        });
    });
    /* script for vehicle make dropdown ends*/


    /*script for new vehicle model dropdown starts*/
    $(document).ready(function() {
        var ajaxRequest;
        $('#detailDiv').on('change','.make', function() {
            var make = $(this).val();
            var element_make = $(this);
            var append = '';
            //alert(element_make.parent().parent().next(":first").attr('class'));
            ajaxRequest = setTimeout(function(sn) {
                $.ajax({
                    url: '{{ route('admin.claims.checkMakeModel') }}',
                    data: {
                        "_token": "{{ csrf_token() }}",
                        "make": make,
                    },
                    type: 'post',
                    datatype : 'json',
                    success: function (data) {
                        if (data) {
                            $('.makerow').empty();
                            append += '<div class="form-group">';
                            append += '<label>Model </label>';
                            append += '<select class="form-control kt_selectpicker model" name="thirdMemberModel[] " data-live-search="true" title="Please choose model">';
                            $.each(data.count, function(key, modal)
                            {
                                append += '<option value="' + modal + '">' + modal + '</option>';

                            });
                            append += '</select></div>';
                            $('.makerow').append(append);

                        }

                        else {
                            append += '<h3>No data is available</h3>';
                        }
                    }
                });
            }, 200);
        });
    });

    /*script to check Attorney*/

    $(document).ready(function() {
        var ajaxRequest;
        $('.attorneyValue').on('click', function() {
            var claim_id = $('#claim_id').val();
            ajaxRequest = setTimeout(function(sn) {
                $.ajax({
                    url: '{{ route('admin.claims.checkAttorney') }}',
                    data: {
                        "_token": "{{ csrf_token() }}",
                        "claim_id": claim_id,
                    },
                    type: 'post',
                    datatype : 'json',
                    success: function (data) {
                        if (data.error == 0) {
                            $('.attorneyMsg').attr("hidden",false);/*.attr("hidden",true);*/
                        }
                        else {
                            $('.attorneyMsg').attr("hidden",true);
                        }

                        if($('.attorneyValue').prop("checked") == false){
                            $('.attorneyMsg').attr("hidden",true);
                        }
                    }
                });
            }, 200);
        });
    });


    /*script for old make selectpicker starts */

    $(document).ready(function() {
        var ajaxRequest;
        var previous_make = '';
        var append = '';
        $('#details').on('click','.oldmake', function() {
            var $t = $(this);
            ajaxRequest = setTimeout(function(sn) {
                $.ajax({
                    url: '{{ route('admin.claims.checkMakeModel') }}',
                    data: {
                        "_token": "{{ csrf_token() }}",
                    },
                    type: 'post',
                    datatype : 'json',
                    context: this,
                    success: function (data) {
                        if (data) {
                            var replace = '';
                            /* $t.empty();*/
                            replace += '<select class="form-control kt_selectpicker previous_make" name="old_thirdMemberMake[]" data-live-search="true" title="Please choose product">';
                            $.each(data.vehicle, function(key, make)
                            {
                                replace += '<option value="' + make + '">' + make + '</option>';

                            });
                            replace += '</select>';
                            $t.replaceWith(replace);
                        }
                        else {
                            replace += '<h3>No data is available</h3>';
                        }
                    }
                });
            }, 200);
        });

        /* for model */
        $('.rowformake').on('change', '.previous_make',function() {
            var $p = $(this).val();
            var element =$(this) ;
            /* alert(element.parent().parent().next(":first").attr('class'));*/
            $.ajax({
                url: '{{ route('admin.claims.getpreviousModel') }}',
                data: {
                    "_token": "{{ csrf_token() }}",
                    "previous_make":$p,
                },
                type: 'post',
                datatype : 'json',
                context: this,
                success: function (data) {
                    //console.log(data);
                    if (data) {
                        var replace = '';
                        element.parent().parent().next(":first").empty().attr('class');

                        replace += '<div class="form-group">';
                        replace += '<label>Model </label>';
                        replace += '<select class="form-control previous_model" name="old_thirdMemberModel[]" data-live-search="true" title="Please choose model">';
                        $.each(data.make_values, function(key, value)
                        {
                            replace += '<option value="' + value + '">' + value + '</option>';
                        });
                        replace += '</select></div>';
                        element.parent().parent().next(":first").append(replace).attr('class');
                    }
                    else {
                        replace += '<h3>No data is available</h3>';
                    }
                }
            });
        });


        $('.rowOfmodel').on('click', '.oldmodel',function() {
            var $oldmodel = $(".oldmodel").val();
            var replace1 = '';
            var element =$(this);
            $.ajax({
                url: '{{ route('admin.claims.getModelValues') }}',
                data: {
                    "_token": "{{ csrf_token() }}",
                    "oldmodel":$oldmodel,
                },
                type: 'post',
                datatype : 'json',
                context: this,
                success: function (data) {
                    console.log(data.model_values);
                    if (data) {
                        var element2 = element.parent().parent();
                        element.parent().parent().empty();
                        replace1 += '<label>Model </label>';
                        replace1 += '<select class="form-control" name="old_thirdMemberModel[]" data-live-search="true" title="Please choose model">';
                        $.each(data.model_values, function(key, value)
                        {
                            replace1 += '<option value="' + value + '">' + value + '</option>';
                        });
                        replace1 += '</select>';

                        element2.append(replace1);
                    }
                    else {
                        replace1 += '<h3>No data is available</h3>';
                    }
                }
            });

        });
    });

    /*script for old make selectpicker ends */

</script>


</body>
</html>
