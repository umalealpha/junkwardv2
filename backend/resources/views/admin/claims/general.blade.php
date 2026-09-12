<!DOCTYPE html>
<html lang="en" >
@include('admin.layouts.header')
<link href="{{  asset('assets/vendors/custom/datatables/datatables.bundle.css') }}" rel="stylesheet" type="text/css" />
<link href="{{  asset('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css') }}" rel="stylesheet" type="text/css" />
<link href="{{  asset('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}" rel="stylesheet" type="text/css" />

<link href="{{  asset('assets/vendors/general/bootstrap-datetime-picker/css/bootstrap-datetimepicker.css') }}" rel="stylesheet" type="text/css" />
<link href="{{  asset('assets/vendors/general/bootstrap-timepicker/css/bootstrap-timepicker.css') }}" rel="stylesheet" type="text/css" />

<style>
    #coverage_table .highlighted-row {
        background-color: yellow !important; /* Add !important to override other rules */
    }

    .search-container-reserves {
      display: flex;
      justify-content: flex-end; /* Aligns the search box to the right */
      margin-bottom: 10px;
    }

    .search-input-reserves {
      width: 200px; /* Makes the search box smaller */
      padding: 5px 10px;
      font-size: 14px;
      border: 1px solid #ccc;
      border-radius: 4px;
      outline: none;
      transition: border-color 0.3s;
    }

    .search-input-reserves:focus {
      border-color: #007bff;
    }
</style>

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
            {{-- @if ($claims && $claims->status == 'Approved' || $claims && $claims->status == 'Rejected')
                <div class="pull-right">
                    <button class="btn btn-success btn-brand btn-elevate" id="closedBtn" data-toggle="collapse" data-target="#closedCollapse" aria-expanded="false" aria-controls="collapseExample" data-toggle="kt-tooltip" title="" data-placement="right" data-original-title="Close"><span class="kt-opacity-11" id=""> Close Claim</span>&nbsp; </button>
                </div>
            @endif --}}
            {{-- @if($claims->claim_type != 'Accident') --}}
                {{-- @if($claims && $claims->status == 'Pending' && $claimAssessmentCount != NULL && count($claimAssessmentCount) > 0) --}}
                @if($claims && $claims->status == 'Pending')
                    <div class="pull-right">
                        <a href="{!! route('admin.claims.approved',[$claims->id]) !!}" class="btn btn-sm btn-success btn-brand btn-elevate" id="" data-toggle="kt-tooltip" title="" data-placement="right" data-original-title="Approved"> <span class="kt-opacity-11" id="">Approve</span>&nbsp; </a>
                        <a href="{!! route('admin.claims.rejects',[$claims->id]) !!}" class="btn btn-sm btn-danger btn-brand btn-elevate" id="rejectBtn" data-toggle="kt-tooltip" title="" data-placement="right" data-original-title="Reject"> <span class="kt-opacity-11">Reject</span>&nbsp; </a>
                    </div>
                @endif
                @if($claims && $claims->status == 'Approved')
                    <button class="btn btn-success btn-brand btn-elevate" id="closedBtn" data-toggle="collapse" data-target="#closedCollapse" aria-expanded="false" aria-controls="collapseExample" data-toggle="kt-tooltip" title="" data-placement="right" data-original-title="Close"><span class="kt-opacity-11" id=""> Close Claim</span>&nbsp; </button>
                    {{-- <a href="{!! route('admin.claims.rejects',[$claims->id]) !!}" class="btn btn-sm btn-danger btn-brand btn-elevate" id="" data-toggle="kt-tooltip" title="" data-placement="right" data-original-title="Reject"> <span class="kt-opacity-11" id=""> Reject</span>&nbsp; </a> --}}
                    @endif
                @if($claims && $claims->status == 'Rejected')
                    <button class="btn btn-success btn-brand btn-elevate" id="closedBtn" data-toggle="collapse" data-target="#closedCollapse" aria-expanded="false" aria-controls="collapseExample" data-toggle="kt-tooltip" title="" data-placement="right" data-original-title="Close"><span class="kt-opacity-11" id=""> Close Claim</span>&nbsp; </button>
                    {{-- <a href="{!! route('admin.claims.approved',[$claims->id]) !!}" class="btn btn-sm btn-success btn-brand btn-elevate" id="" data-toggle="kt-tooltip" title="" data-placement="right" data-original-title="Approve" hidden> <span class="kt-opacity-11" id="">Approve</span>&nbsp; </a> --}}
                @endif
                @if($claims && $claims->status == 'Closed')
                    <button class="btn btn-success btn-brand btn-elevate" id="reopenBtn" data-toggle="collapse" data-target="#reopenCollapse" aria-expanded="false" aria-controls="collapseExample" data-toggle="kt-tooltip" title="" data-placement="right" data-original-title="Reopen"><span class="kt-opacity-11" id=""> Re - Open Claim</span>&nbsp; </button>
                @endif
            {{-- @endif --}}
        </div>
        <!-- end:: Subheader -->
        <!-- begin:: Content -->

        <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">

            <div class="form-group row collapse kt-portlet kt-portlet__head " id="reopenCollapse">
                <div class="col-md-12">
                    <div class="kt-checkbox-inline">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="kt-portlet">
                                    <div class="kt-portlet__head-label col-lg-12">
                                        <div class="col-lg-10">
                                            <h5 class="kt-portlet__head-title" style="padding-top: 20px;">
                                                Re - Open Claim
                                            </h5>
                                        </div>
                                    </div>

                                    <form id="reopenStoreClaim"  action="{{ url('admin/claims/reopenStoreClaim/'.$claims->id) }}" method="POST" enctype="multipart/form-data" class="kt-form">
                                        <!-- CSRF Token -->
                                        <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                                        <div class="kt-portlet_body">
                                            <div class="kt-section">
                                                <div class="kt-section__content" style="padding: 20px 20px 0 20px;">
                                                    <input id="policy_id" class="form-control" type="hidden" name="policy_id" value="{!! $policy->id !!}">
                                                    <div class="form-group row">
                                                        <div class="col-lg-3">
                                                            <label for="example-text-input" class="form-label">Claim Sub Status</label>
                                                        </div>
                                                        <div class="col-lg-8">
                                                            <select class="form-control" title="Please choose product" data-live-search="true" name="reopen_claim_substatus" id="reopenSubStatus">
                                                                <option value="">Please select claim sub status</option>
                                                                <option value="0">Open</option>
                                                                <option value="1">File with accounts</option>
                                                                <option value="2">Excess to be paid</option>
                                                                <option value="3">Recovery from third party</option>
                                                                <option value="4">Client not responding</option>
                                                                <option value="5">Awaiting excess payment</option>
                                                                <option value="6">Duplicate claim</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="kt-portlet__foot kt-portlet__foot--solid col">
                                            <div class="kt-form__actions">
                                                <div class="row">
                                                        <div class="col-5"></div>
                                                        <div class="col-7">
                                                            <button type="submit" value="submitClosedBtn" id="submitClosedBtn"  class="btn btn-brand">Submit</button>
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
            </div>
            <div class="form-group row collapse kt-portlet kt-portlet__head " id="closedCollapse">
                <div class="col-md-12">
                    <div class="kt-checkbox-inline">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="kt-portlet">
                                    <div class="kt-portlet__head-label col-lg-12">
                                        <div class="col-lg-10">
                                            <h5 class="kt-portlet__head-title" style="padding-top: 20px;">
                                                Close Claim
                                            </h5>
                                        </div>
                                    </div>

                                    <form id="closeStore"  action="{{ url('admin/claims/closeClaimStore/'.$claims->id) }}" method="POST" enctype="multipart/form-data" class="kt-form">
                                        <!-- CSRF Token -->
                                        <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                                        <div class="kt-portlet_body">
                                            <div class="kt-section">
                                                <div class="kt-section__content" style="padding: 20px 20px 0 20px;">
                                                    <div class="form-group row">
                                                        <input id="policy_id" class="form-control" type="hidden" name="policy_id" value="{!! $policy->id !!}">
                                                        <label for="example-text-input" class="col-3 col-form-label">Document 1</label>
                                                        <div class="col-2">
                                                            <div class="kt-avatar" id="document_1" style="float: left; clear: left;">
                                                                {{-- @if($claims->po == NULL) --}}
                                                                    <div class="kt-avatar__holder" style="background-image:url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                                                                {{-- @else
                                                                    <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($claims->po) !!}" target="_blank" download>
                                                                        @if(pathinfo($claims->po, PATHINFO_EXTENSION) == 'pdf')
                                                                            <img src="{{asset('images/pdf.ico')}}" width="100%" height="auto" >
                                                                        @elseif(pathinfo($claims->po, PATHINFO_EXTENSION) == 'docx' || pathinfo($claims->po, PATHINFO_EXTENSION) == 'doc' || pathinfo($claims->po, PATHINFO_EXTENSION) == 'docm')
                                                                            <img src="{{asset('images/word.ico')}}" width="100%" height="auto" >
                                                                        @elseif(pathinfo($claims->po, PATHINFO_EXTENSION) == 'xls' || pathinfo($claims->po, PATHINFO_EXTENSION) == 'xlsx' || pathinfo($claims->po, PATHINFO_EXTENSION) == 'csv')
                                                                            <img src="{{asset('images/excel.png')}}" width="100%" height="auto" >
                                                                        @elseif(pathinfo($claims->po, PATHINFO_EXTENSION) == 'jpeg' || pathinfo($claims->po, PATHINFO_EXTENSION) == 'jpg' || pathinfo($claims->po, PATHINFO_EXTENSION) == 'png')
                                                                            <img src="{{\AlphaDirect\Helper::getCloudFrontURL($claims->po)}}" width="100%" height="auto" >
                                                                        @else
                                                                            <img src="{{asset('images/doc.png')}}" width="100%" height="auto" >
                                                                        @endif
                                                                    </a>
                                                                @endif --}}
                                                                <label class="kt-avatar__upload" data-toggle="kt-tooltip" title="Select Image">
                                                                    <i class="fa fa-pen"></i>
                                                                    <input type='file'  name="document_1" id="document1" value="" required <?php echo config('app.accept_attr'); ?> <?php echo config('app.accept_msg'); ?> />
                                                                </label>
                                                                    <span class="kt-avatar__cancel" data-toggle="kt-tooltip" title="Cancel Image"> <i class="fa fa-times"></i> </span>
                                                            </div>

                                                        </div>
                                                    </div>
                                                    <div class="form-group row">
                                                        <input id="policy_id" class="form-control" type="hidden" name="policy_id" value="{!! $policy->id !!}">
                                                        <label for="example-text-input" class="col-3 col-form-label">Document 2</label>
                                                        <div class="col-2">
                                                            <div class="kt-avatar" id="document_2" style="float: left; clear: left;">
                                                                {{-- @if($claims->po == NULL) --}}
                                                                    <div class="kt-avatar__holder" style="background-image:url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                                                                {{-- @else
                                                                    <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($claims->po) !!}" target="_blank" download>
                                                                        @if(pathinfo($claims->po, PATHINFO_EXTENSION) == 'pdf')
                                                                            <img src="{{asset('images/pdf.ico')}}" width="100%" height="auto" >
                                                                        @elseif(pathinfo($claims->po, PATHINFO_EXTENSION) == 'docx' || pathinfo($claims->po, PATHINFO_EXTENSION) == 'doc' || pathinfo($claims->po, PATHINFO_EXTENSION) == 'docm')
                                                                            <img src="{{asset('images/word.ico')}}" width="100%" height="auto" >
                                                                        @elseif(pathinfo($claims->po, PATHINFO_EXTENSION) == 'xls' || pathinfo($claims->po, PATHINFO_EXTENSION) == 'xlsx' || pathinfo($claims->po, PATHINFO_EXTENSION) == 'csv')
                                                                            <img src="{{asset('images/excel.png')}}" width="100%" height="auto" >
                                                                        @elseif(pathinfo($claims->po, PATHINFO_EXTENSION) == 'jpeg' || pathinfo($claims->po, PATHINFO_EXTENSION) == 'jpg' || pathinfo($claims->po, PATHINFO_EXTENSION) == 'png')
                                                                            <img src="{{\AlphaDirect\Helper::getCloudFrontURL($claims->po)}}" width="100%" height="auto" >
                                                                        @else
                                                                            <img src="{{asset('images/doc.png')}}" width="100%" height="auto" >
                                                                        @endif
                                                                    </a>
                                                                @endif --}}
                                                                <label class="kt-avatar__upload" data-toggle="kt-tooltip" title="Select Image">
                                                                    <i class="fa fa-pen"></i>
                                                                    <input type='file'  name="document_2" value=""  <?php echo config('app.accept_attr'); ?> <?php echo config('app.accept_msg'); ?> />
                                                                </label>
                                                                    <span class="kt-avatar__cancel" data-toggle="kt-tooltip" title="Cancel Image"> <i class="fa fa-times"></i> </span>
                                                            </div>

                                                        </div>
                                                    </div>
                                                    <div class="form-group row">
                                                        <input id="policy_id" class="form-control" type="hidden" name="policy_id" value="{!! $policy->id !!}">
                                                        <label for="example-text-input" class="col-3 col-form-label">Document 3</label>
                                                        <div class="col-2">
                                                            <div class="kt-avatar" id="document_3" style="float: left; clear: left;">
                                                                {{-- @if($claims->po == NULL) --}}
                                                                    <div class="kt-avatar__holder" style="background-image:url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                                                                {{-- @else
                                                                    <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($claims->po) !!}" target="_blank" download>
                                                                        @if(pathinfo($claims->po, PATHINFO_EXTENSION) == 'pdf')
                                                                            <img src="{{asset('images/pdf.ico')}}" width="100%" height="auto" >
                                                                        @elseif(pathinfo($claims->po, PATHINFO_EXTENSION) == 'docx' || pathinfo($claims->po, PATHINFO_EXTENSION) == 'doc' || pathinfo($claims->po, PATHINFO_EXTENSION) == 'docm')
                                                                            <img src="{{asset('images/word.ico')}}" width="100%" height="auto" >
                                                                        @elseif(pathinfo($claims->po, PATHINFO_EXTENSION) == 'xls' || pathinfo($claims->po, PATHINFO_EXTENSION) == 'xlsx' || pathinfo($claims->po, PATHINFO_EXTENSION) == 'csv')
                                                                            <img src="{{asset('images/excel.png')}}" width="100%" height="auto" >
                                                                        @elseif(pathinfo($claims->po, PATHINFO_EXTENSION) == 'jpeg' || pathinfo($claims->po, PATHINFO_EXTENSION) == 'jpg' || pathinfo($claims->po, PATHINFO_EXTENSION) == 'png')
                                                                            <img src="{{\AlphaDirect\Helper::getCloudFrontURL($claims->po)}}" width="100%" height="auto" >
                                                                        @else
                                                                            <img src="{{asset('images/doc.png')}}" width="100%" height="auto" >
                                                                        @endif
                                                                    </a>
                                                                @endif --}}
                                                                <label class="kt-avatar__upload" data-toggle="kt-tooltip" title="Select Image">
                                                                    <i class="fa fa-pen"></i>
                                                                    <input type='file'  name="document_3" value=""  <?php echo config('app.accept_attr'); ?> <?php echo config('app.accept_msg'); ?> />
                                                                </label>
                                                                    <span class="kt-avatar__cancel" data-toggle="kt-tooltip" title="Cancel Image"> <i class="fa fa-times"></i> </span>
                                                            </div>

                                                        </div>
                                                    </div>
                                                    <div class="form-group row">
                                                        <div class="col-lg-3">
                                                            <label for="example-text-input" class="form-label">Close Claim Notes</label>
                                                        </div>
                                                        <div class="col-lg-8">
                                                            <textarea  class="form-control" name="closed_note" id="clnote" value=""></textarea>
                                                            <span class="form-text text-muted"></span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="kt-portlet__foot kt-portlet__foot--solid col">
                                            <div class="kt-form__actions">
                                                <div class="row">
                                                    {{-- @can('claim-edit') --}}
                                                        <div class="col-5"></div>
                                                        <div class="col-7">
                                                            <button type="submit" value="submitClosedBtn" id="submitClosedBtn"  class="btn btn-brand">Submit</button>
                                                        </div>
                                                    {{-- @endcan --}}
                                                </div>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            @if($claims->claim_type != 'Accident')
                @if ($claims && $claims->status == 'Reopen')
                    <div class="kt-portlet" style="border:1px solid black;">
                        <div class="kt-portlet__head-label col-lg-12">
                            <div class="col-lg-10">
                                <h5 class="kt-portlet__head-title" style="padding-top: 20px;">
                                    Re - Open Claim
                                </h5>
                            </div>
                        </div>

                        <div class="kt-portlet_body">
                            <div class="kt-section">
                                <div class="kt-section__content">
                                    <table class="table table-striped m-table">
                                        <tbody>
                                        <tr>
                                            <th>Claim Status</th>
                                            @isset($claims->status)
                                                <td>{!! $claims->status !!}</td>
                                            @endisset
                                            <th>Claim Sub Status </th>
                                            @isset($claims->reopen_claim_sub_status)
                                                @if ($claims->reopen_claim_sub_status == 0)
                                                    <td>Open</td>
                                                @elseif ($claims->reopen_claim_sub_status == 1)
                                                    <td>File with accounts</td>
                                                @elseif ($claims->reopen_claim_sub_status == 2)
                                                    <td>Excess to be paid</td>
                                                @elseif ($claims->reopen_claim_sub_status == 3)
                                                    <td>Recovery from third party</td>
                                                @elseif ($claims->reopen_claim_sub_status == 4)
                                                    <td>Client not responding</td>
                                                @elseif ($claims->reopen_claim_sub_status == 5)
                                                    <td>Awaiting excess payment</td>
                                                @elseif ($claims->reopen_claim_sub_status == 6)
                                                    <td>Duplicate claim</td>
                                                @else
                                                    <td>N/A</td>
                                                @endif
                                            @endisset

                                        </tr>

                                        </tbody>
                                    </table>
                                    <br>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            @endif

            @if($claims->claim_type == 'Accident' || $claims->claim_type == 'MOTORTRADERSEXTERNAL' || $claims->claim_type == 'MOTORTRADERSINTERNAL')
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
                            @if ($claims->status != 'Closed')
                                @can('claim-edit')
                                {{--  @if($claimAssessmentReport && $claimAssessmentReport->assessment_report != null) --}}
                                        <div class="col-lg-2">
                                            <button class="btn btn-brand"  style="margin-left: 40%;" id="claimStatusEditBtn" onclick="setStatusToEdit()" >Set to edit</button>
                                        </div>
                                    {{-- @endif--}}
                                @endcan
                            @endif
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
                                        <td>{!!\Carbon\Carbon::parse($claimAccident->claim_allocated_on)->format('d-m-Y')  !!}</td>

                                        <th>Total Reserve Amount</th>
                                        <td>P {!! $reserve_amt !!}</td>
                                    </tr>
                                    <tr>
                                        <th>Total Payment Amount</th>
                                        <td>P {!! $payment_amt !!}</td>
                                        <th>Claim Status</th>
                                        @if($claimAccident->claim_status == "Approved")
                                            <td>Approved</td>
                                        @elseif($claimAccident->claim_status == "Rejected")
                                            <td>Rejected <a href="#" title="Claim Reject Status Details" id="claimStatusRejectBtn"><i class="fas fa-info-circle"></i></a>
                                            </td>
                                        @elseif ($claims && $claims->status == "Closed")
                                            <td>Closed</td>
                                        @elseif ($claims && $claims->status == "Reopen")
                                            <td>Reopen</td>
                                        @else
                                            <td>Pending</td>
                                        @endif
                                        @if($claimAccident->claim_status == "Approved")
                                            <th>Claim Sub Status</th>
                                            <td>{{ $claimAccident->claim_sub_status }} @if( $claimAccident->claim_sub_status != "Repair")
                                                <a href="#" title="Claim Sub Status Details" id="claimStatusBtn">
                                                  <i class="fas fa-info-circle"></i>
                                                </a>@endif
                                            </td>
                                        @endif
                                        @if ($claims && $claims->status == "Reopen")
                                            <th>Claim Sub Status</th>
                                            @isset($claims->reopen_claim_sub_status)
                                                @if ($claims->reopen_claim_sub_status == 0)
                                                    <td>Open</td>
                                                @elseif ($claims->reopen_claim_sub_status == 1)
                                                    <td>File with accounts</td>
                                                @elseif ($claims->reopen_claim_sub_status == 2)
                                                    <td>Excess to be paid</td>
                                                @elseif ($claims->reopen_claim_sub_status == 3)
                                                    <td>Recovery from third party</td>
                                                @elseif ($claims->reopen_claim_sub_status == 4)
                                                    <td>Client not responding</td>
                                                @elseif ($claims->reopen_claim_sub_status == 5)
                                                    <td>Awaiting excess payment</td>
                                                @elseif ($claims->reopen_claim_sub_status == 6)
                                                    <td>Duplicate claim</td>
                                                @else
                                                    <td>N/A</td>
                                                @endif
                                            @endisset
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

            @if($claims->claim_type == 'BUSINESSINTERRUPTION' || $claims->claim_type == 'BUSINESSALLRISKS' || $claims->claim_type == 'ELECTRONICEQUIPMENT' || $claims->claim_type == 'PERSONALALLRISKS' || $claims->claim_type == 'THEFT' || $claims->claim_type == 'MONEY'
            || $claims->claim_type == 'WORKERSCOMPENSATION' || $claims->claim_type == 'STATEDBENEFITS' || $claims->claim_type == 'FIDELITYGUARANTEE' || $claims->claim_type == 'TRAVELINSURANCE'
            || $claims->claim_type == 'GOODSINTRANSIT' || $claims->claim_type == 'FIRE' || $claims->claim_type == 'LIABILITY'
            || $claims->claim_type == 'PROPERTYDAMAGE' || $claims->claim_type == 'ACCIDENTALDAMAGE' || $claims->claim_type == 'DEFECTIVEWORKMANSHIP' || $claims->claim_type == 'MOBILEELECTRONICDEVICES' || $claims->claim_type == 'OFFICECONTENTS')
                {{--claim status labelDiv starts--}}
                <div class="kt-portlet" id="newClaimStatusLabelDiv" style="border:1px solid black;">
                    <div class="kt-portlet__head row">
                        <div class="kt-portlet__head-label col-lg-12">
                            <div class="col-lg-10">
                                <h3 class="kt-portlet__head-title">
                                    Claim Status Details
                                </h3>
                            </div>
                            {{-- @if($claim_assessment && $claim_assessment->assessment_report != NULL)--}}
                            @if ($claims->status != 'Closed')
                                @can('claim-edit')
                                {{--  @if($claimAssessmentReport && $claimAssessmentReport->assessment_report != null) --}}
                                        <div class="col-lg-2">
                                            <button class="btn btn-brand"  style="margin-left: 40%;" id="newClaimStatusEditBtn" onclick="setNewClaimStatusToEdit()" >Set to edit</button>
                                        </div>
                                    {{-- @endif--}}
                                @endcan
                            @endif
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
                                        @if(isset($users))
                                            @foreach($users as $user)
                                                @if( $newclaim->claim_allocated_to == $user->id)
                                                    <td>{{$user->firstName}} {{$user->lastName}}</td>
                                                @endif
                                            @endforeach
                                        @else
                                            <td>NA</td>
                                        @endif

                                        <th>Claim allocated on</th>
                                        <td>{!!\Carbon\Carbon::parse($newclaim->claims_allocated_on)->format('d-m-Y')  !!}</td>

                                        <th>Total Reserve Amount</th>
                                        <td>P {!! $reserve_amt !!}</td>

                                    </tr>
                                    <tr>
                                        <th>Total Payment Amount</th>
                                        <td>P {!! $payment_amt !!}</td>

                                        <th>Claim Status</th>
                                        @if($newclaim->status == "Approved")
                                            <td>Approved</td>
                                        @elseif($newclaim->status == "Rejected")
                                            <td>Rejected <a href="#" title="Claim Reject Status Details" id="newClaimStatusRejectBtn"><i class="fas fa-info-circle"></i></a>
                                            </td>
                                        @elseif ($claims && $claims->status == "Closed")
                                            <td>Closed</td>
                                        @elseif ($claims && $claims->status == "Reopen")
                                            <td>Reopen</td>
                                        @elseif ($claims && $claims->status == "Repudiated")
                                            <td>Repudiated</td>
                                        @elseif ($claims && $claims->status == "Appeal")
                                            <td>Appeal</td>
                                        @elseif ($claims && $claims->status == "Position Maintained")
                                            <td>Position Maintained</td>
                                        @elseif ($claims && $claims->status == "Recovery")
                                            <td>Recovery</td>
                                        @else
                                            <td>Pending</td>
                                        @endif

                                        @if($newclaim->status == "Approved")
                                            <th>Claim Sub Status</th>
                                            <td>@if(isset($newclaim->claim_sub_status)) {{ $newclaim->claim_sub_status }} @else NA @endif</td>
                                        @endif

                                    </tr>
                                        @if($newclaim->status == "Approved")
                                            <th>Claim Approved</th>
                                            <td>@if(isset($newclaim->claim_approved)) {{ $newclaim->claim_approved }} @else NA @endif</td>
                                        @endif
                                        @if ($claims && $claims->status == "Reopen")
                                            <th>Claim Sub Status</th>
                                            @isset($claims->reopen_claim_sub_status)
                                                @if ($claims->reopen_claim_sub_status == 0)
                                                    <td>Open</td>
                                                @elseif ($claims->reopen_claim_sub_status == 1)
                                                    <td>File with accounts</td>
                                                @elseif ($claims->reopen_claim_sub_status == 2)
                                                    <td>Excess to be paid</td>
                                                @elseif ($claims->reopen_claim_sub_status == 3)
                                                    <td>Recovery from third party</td>
                                                @elseif ($claims->reopen_claim_sub_status == 4)
                                                    <td>Client not responding</td>
                                                @elseif ($claims->reopen_claim_sub_status == 5)
                                                    <td>Awaiting excess payment</td>
                                                @elseif ($claims->reopen_claim_sub_status == 6)
                                                    <td>Duplicate claim</td>
                                                @else
                                                    <td>N/A</td>
                                                @endif
                                            @endisset
                                        @endif
                                    </tbody>
                                </table>
                                <br>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <div id="newClaimStatusEditDiv" style="display:none;">
                @if($claims->claim_type == 'BUSINESSINTERRUPTION' || $claims->claim_type == 'BUSINESSALLRISKS' || $claims->claim_type == 'ELECTRONICEQUIPMENT' || $claims->claim_type == 'PERSONALALLRISKS' || $claims->claim_type == 'THEFT' || $claims->claim_type == 'MONEY' || $claims->claim_type == 'WORKERSCOMPENSATION' || $claims->claim_type == 'STATEDBENEFITS' || $claims->claim_type == 'FIDELITYGUARANTEE' || $claims->claim_type == 'TRAVELINSURANCE' || $claims->claim_type == 'GOODSINTRANSIT' || $claims->claim_type == 'FIRE' || $claims->claim_type == 'LIABILITY'  || $claims->claim_type == 'PROPERTYDAMAGE' || $claims->claim_type == 'ACCIDENTALDAMAGE' || $claims->claim_type == 'DEFECTIVEWORKMANSHIP' || $claims->claim_type == 'MOBILEELECTRONICDEVICES' || $claims->claim_type == 'OFFICECONTENTS')
                    <div class="row">
                        <div class="col-md-12">
                            <div class="kt-portlet">
                                <div class="kt-portlet__head-label col-lg-12">
                                    <div class="col-lg-10">
                                        <h5 class="kt-portlet__head-title" style="padding-top: 20px;">
                                            Claim Status Details
                                        </h5>
                                    </div>
                                    <div class="col-lg-2" style="margin-left: 90%;">
                                        <button class="btn btn-warning"  id="newClaimStatusLabelBtn" onclick="setNewClaimStatusToView()">Set to View</button>
                                    </div>
                                </div>

                                <form id="statusUpdate"  action="{{ route('admin.claims.newClaimStatusUpdate',$claims->id) }}" method="POST" enctype="multipart/form-data" class="kt-form">
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
                                                                @isset($users)
                                                                    @foreach($users as $user)
                                                                        <option value="{{$user->id}}"  @if( $newclaim->claim_allocated_to == $user->id) selected @endif>
                                                                            {{$user->firstName}} {{$user->lastName}}
                                                                        </option>
                                                                    @endforeach
                                                                @endisset
                                                            </select>

                                                        </div>
                                                    </div>
                                                    <div class="col-lg-6">
                                                        <div class="form-group">
                                                            <label>Claim allocated on:</label>
                                                            <input class="form-control kt_datepicker_1"  placeholder="Please Select Date" name="claim_allocated_on" value="{!! $newclaim->claims_allocated_on !!}" required>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="row">
                                                    <div class="col-lg-6">
                                                        <div class="form-group">
                                                            <label for="exampleSelect1">Total Reserve Amount:</label>
                                                            <input type="text" class="form-control" value="{!! $reserve_amt !!}"  title="Please enter the amount" id="reserve_amount" name="reserve_amount" aria-describedby="emailHelp"  placeholder="P0.00" required>
                                                            <input type="hidden" value="{{ $policy->id }}" id="policyId">
                                                            <p style="display:none; color:red;" id="reserveMsg"></p>
                                                        </div>
                                                    </div>
                                                    <div class="col-lg-6">
                                                        <div class="form-group">
                                                            <label for="exampleSelect1">Total Payment Amount:</label>
                                                            <input type="text" class="form-control" id="paid_amount" value="{!! $payment_amt !!}" title="Please enter the amount" name="paid_amount" aria-describedby="emailHelp"  placeholder="P0.00" required>
                                                            <p style="display:none; color:red;" id="paidmsg"></p>
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
                                                                <option value="Repudiated" @if($claims->status == 'Repudiated') selected @endif>Repudiated</option>
                                                                <option value="Appeal" @if($claims->status == 'Appeal') selected @endif>Appeal</option>
                                                                <option value="Position Maintained" @if($claims->status == 'Position Maintained') selected @endif>Position Maintained</option>
                                                                <option value="Recovery" @if($claims->status == 'Recovery') selected @endif>Recovery</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="col-lg-6" @if($claims->status != 'Approved')style="display:none;"@endif id="newClaimSub_statusDiv">
                                                        <div class="form-group">
                                                            <label for="exampleSelect1" style="padding-right: 15px;">Claim Sub-status:</label>
                                                            <select class="form-control kt_selectpicker" data-live-search="true"  title="Please select claim sub status" id="selectValue" name="claim_sub_status">
                                                                <option value="File with management for review" @if($newclaim->claim_sub_status == 'File with management for review') selected @endif>File with management for review</option>
                                                                <option value="Order sent" @if($newclaim->claim_sub_status == 'Order sent') selected @endif>Order sent</option>
                                                                <option value="AOL/CIL/Ex-Gratia sent to client" @if($newclaim->claim_sub_status == 'AOL/CIL/Ex-Gratia sent to client') selected @endif>AOL/CIL/Ex-Gratia sent to client</option>
                                                                <option value="File with Account for payment" @if($newclaim->claim_sub_status == 'File with Account for payment') selected @endif>File with Account for payment</option>
                                                                <option value="Subrogation" @if($newclaim->claim_sub_status == 'Excess to be paid') selected @endif>Excess to be paid</option>
                                                                <option value="client not responsive" @if($newclaim->claim_sub_status == 'client not responsive') selected @endif>client not responsive</option>
                                                                <option value="Claim repudiated" @if($newclaim->claim_sub_status == 'Claim repudiated') selected @endif>Claim repudiated</option>
                                                                <option value="Subrogation Claim" @if($newclaim->claim_sub_status == 'Subrogation Claim') selected @endif>Subrogation Claim</option>
                                                                <option value="Await claim documents" @if($newclaim->claim_sub_status == 'Await claim documents') selected @endif>Await claim documents</option>
                                                                <option value="Await premium confirmation" @if($newclaim->claim_sub_status == 'Await premium confirmation') selected @endif>Await premium confirmation</option>
                                                                <option value="Agreement Of Loss AOL" @if($newclaim->claim_sub_status == 'Agreement Of Loss AOL') selected @endif>Agreement Of Loss AOL</option>
                                                                <option value="Form of release" @if($newclaim->claim_sub_status == 'Form of release') selected @endif>Form of release</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="row" @if($claims->status != 'Approved')style="display:none;"@endif id="claim_approved_div">
                                                    <div class="col-lg-6">
                                                        <div class="form-group">
                                                            <label>Claim Approved:</label>
                                                            <select class="form-control kt_selectpicker" data-live-search="true" title="Please select claim approved" name="claim_approved">
                                                                <option value="Yes" @if($newclaim->claim_approved == 'Yes') selected @endif>Yes</option>
                                                                <option value="Repudiated" @if($newclaim->claim_approved == 'Repudiated') selected @endif>Repudiated</option>
                                                                <option value="Exgartia" @if($newclaim->claim_approved == 'Exgartia') selected @endif>Exgartia</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div id="selectStatusSubrogation" style="display: none;">
                                                    <div class="row">
                                                        <div class="col-lg-6">
                                                            <h3 class="col-form-label" style="float: left;">Notice of Demand</h3>
                                                            <div class="kt-avatar" id="notice_of_demand"style="float: left; clear: left;">
                                                                @if($newclaim->notice_of_demand == NULL)
                                                                    <div class="kt-avatar__holder"style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                                                                @else
                                                                    <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($newclaim->notice_of_demand) !!}"target="_blank" download>
                                                                        <div class="kt-avatar__holder" style="background-image:url({!! \AlphaDirect\Helper::getCloudFrontURL($newclaim->notice_of_demand) !!})"></div>
                                                                    </a>
                                                                @endif
                                                                    <label class="kt-avatar__upload" data-toggle="kt-tooltip" title="notice_of_demand">
                                                                     <i class="fa fa-pen"></i>
                                                                        <input type='file' class="notice_of_demand"name="notice_of_demand"<?php echo config('app.accept_attr'); ?><?php echo config('app.accept_msg'); ?> />
                                                                    </label>
                                                                    <span class="kt-avatar__cancel" data-toggle="kt-tooltip" title="Cancel Image">
                                                                        <i class="fa fa-times"></i>
                                                                    </span>
                                                            </div>
                                                        </div>

                                                        <div class="col-lg-6">
                                                            <h3 class="col-form-label" style="float: left;">Final Demand Letter</h3>
                                                            <div class="kt-avatar" id="final_demand_letter"style="float: left; clear: left;">
                                                                @if($newclaim->final_demand_letter == NULL)
                                                                    <div class="kt-avatar__holder"style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                                                                @else
                                                                    <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($newclaim->final_demand_letter) !!}"target="_blank" download>
                                                                        <div class="kt-avatar__holder" style="background-image:url({!! \AlphaDirect\Helper::getCloudFrontURL($newclaim->final_demand_letter) !!})"></div>
                                                                    </a>
                                                                @endif
                                                                    <label class="kt-avatar__upload" data-toggle="kt-tooltip" title="final_demand_letter">
                                                                     <i class="fa fa-pen"></i>
                                                                        <input type='file' class="final_demand_letter"name="final_demand_letter"<?php echo config('app.accept_attr'); ?><?php echo config('app.accept_msg'); ?> />
                                                                    </label>
                                                                    <span class="kt-avatar__cancel" data-toggle="kt-tooltip" title="Cancel Image">
                                                                        <i class="fa fa-times"></i>
                                                                    </span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <br>
                                                    <div class="row">
                                                        <div class="col-lg-6">
                                                            <h3 class="col-form-label" style="float: left;">Debt Acknowledgment form</h3>
                                                            <div class="kt-avatar" id="debt_acknowledgment"style="float: left; clear: left;">
                                                                @if($newclaim->debt_acknowledgment == NULL)
                                                                    <div class="kt-avatar__holder"style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                                                                @else
                                                                    <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($newclaim->debt_acknowledgment) !!}"target="_blank" download>
                                                                        <div class="kt-avatar__holder" style="background-image:url({!! \AlphaDirect\Helper::getCloudFrontURL($newclaim->debt_acknowledgment) !!})"></div>
                                                                    </a>
                                                                @endif
                                                                    <label class="kt-avatar__upload" data-toggle="kt-tooltip" title="debt_acknowledgment">
                                                                     <i class="fa fa-pen"></i>
                                                                        <input type='file' class="debt_acknowledgment"name="debt_acknowledgment"<?php echo config('app.accept_attr'); ?><?php echo config('app.accept_msg'); ?> />
                                                                    </label>
                                                                    <span class="kt-avatar__cancel" data-toggle="kt-tooltip" title="Cancel Image">
                                                                        <i class="fa fa-times"></i>
                                                                    </span>
                                                            </div>
                                                        </div>
                                                        <div class="col-lg-6">
                                                            <h3 class="col-form-label" style="float: left;">KYC form</h3>
                                                            <div class="kt-avatar" id="kyc_form"style="float: left; clear: left;">
                                                                @if($newclaim->kyc_form == NULL)
                                                                    <div class="kt-avatar__holder"style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                                                                @else
                                                                    <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($newclaim->kyc_form) !!}"target="_blank" download>
                                                                        <div class="kt-avatar__holder" style="background-image:url({!! \AlphaDirect\Helper::getCloudFrontURL($newclaim->kyc_form) !!})"></div>
                                                                    </a>
                                                                @endif
                                                                    <label class="kt-avatar__upload" data-toggle="kt-tooltip" title="kyc_form">
                                                                     <i class="fa fa-pen"></i>
                                                                        <input type='file' class="kyc_form"name="kyc_form"<?php echo config('app.accept_attr'); ?><?php echo config('app.accept_msg'); ?> />
                                                                    </label>
                                                                    <span class="kt-avatar__cancel" data-toggle="kt-tooltip" title="Cancel Image">
                                                                        <i class="fa fa-times"></i>
                                                                    </span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div id="selectStatus" @if($claims->claim_status == 'Rejected') style="display:block;" @else style="display:none;" @endif>
                                                    <div class="row">
                                                        <div class="col-lg-6">
                                                            <div class="form-group">
                                                                <label for="exampleSelect1">Reason</label>
                                                                <input type="text" class="form-control" value="{{ $newclaim->reason }}" placeholder="Please provide valid Reason" title="Please provide valid Reason" required name="reason">
                                                            </div>
                                                        </div>
                                                        <div class="col-lg-6">
                                                            <div class="form-group">
                                                                <label for="exampleSelect1">Note</label>
                                                                <input type="text" class="form-control" value="{{ $newclaim->note }}" placeholder="Please provide Note" title="Please provide Note" required name="note">
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>


                                                <div style="border:1px solid black;margin-bottom:30px; display:none;" id="substatus_repeater" class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>

                                                {{-- <div>
                                                     <button type="button" id="addbeneficiary_btn" style="display:none;" class="btn btn-brand"><i class="la la-plus"></i>Add Other Details </button>
                                                 </div>--}}

                                                {{--repeater code starts--}}
                                                <div class="row" id="add_details" style="display: none;">
                                                    <div class="col-lg-12">
                                                        <div class="kt-repeater">
                                                            <div class="kt-repeater__data-set">
                                                                <div data-repeater-list="beneficiary_status_details" id="beneficiary_status_details">
                                                                    <div data-repeater-item class="kt-repeater__item">
                                                                        <div class="form-group row select_beneficiary" id="new_check_beneficiary">

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

                                                                            <div class="col-lg-6 nextclass">
                                                                                <div class="form-group new_otherparty_dropdown"  style="display:none;">
                                                                                    {{-- <label for="exampleSelect1" style="padding-right: 15px">Other Party</label>
                                                                                     <select class="form-control kt_selectpicker"   id="otherparty_list" class="otherparty_list" name="otherparty_list" title="Please select other party" >
                                                                                     </select>--}}
                                                                                </div>
                                                                            </div>

                                                                        </div>
                                                                        <div class="details_div" style="display:none; margin-top:-3%;">
                                                                            <h5 class="bankh5" style="color:black;padding-top:30px;">Bank Details</h5>
                                                                            <br>
                                                                            <div class="row">
                                                                                <div class="col-lg-6">
                                                                                    <div class="form-group">
                                                                                        <label for="exampleSelect1" class="demo">Myzaka/Orange Money Cell</label>
                                                                                        <input type="text" class="form-control accident_billingCell" name="accident_billingCell" aria-describedby="emailHelp" title="Enter orange money cell"
                                                                                               placeholder="Myzaka/Orange Cell"  minlength="8" maxlength="8" pattern="[0-9]{8}" >
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
                                                                            <br>
                                                                            {{--<div class="row">
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
                                                                            </div>--}}
                                                                            <h5 style="color:black;padding-top:30px;">KYC Details</h5>
                                                                            <br>
                                                                            <div class="form-group row">
                                                                                <div class="col-md-2">
                                                                                    <h3 class="col-form-label" style="float: left;">Driving License</h3>
                                                                                    <div class="kt-avatar" id="driving_license" style="float: left; clear: left;">
                                                                                        <div class="kt-avatar__holder" style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                                                                                        <label class="kt-avatar__upload" data-toggle="kt-tooltip" title="Change Image">
                                                                                            <i class="fa fa-pen"></i>
                                                                                            <input type='file' class="driving_license" name="driving_license" <?php echo config('app.accept_attr'); ?>/>

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
                                                                                            <input type='file' class="omang" name="omang" <?php echo config('app.accept_attr'); ?> <?php echo config('app.accept_msg'); ?> />
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
                                                                                            <input type='file' class="proof_residence" name="proof_residence" <?php echo config('app.accept_attr'); ?> <?php echo config('app.accept_msg'); ?> />
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
                                                                @isset($salvageUser)
                                                                    @foreach($salvageUser as $user)
                                                                        <option value="{{$user->id}}" >{{$user->firstName}} {{$user->lastName}}</option>
                                                                    @endforeach
                                                                @endisset

                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="form-group row" @if($claims->claim_status == 'Rejected') style="display:block;" @else style="display:none;" @endif id="uploadFile">
                                                    <label for="example-text-input" class="col-3 col-form-label">Additional Form</label>
                                                    <div class="col-lg-9">
                                                        <div class="input-group">
                                                            <div class="kt-avatar" style="float: left; clear: left;" id="claimStatusFile">
                                                                @if($newclaim->document  == NULL)
                                                                    <div class="kt-avatar__holder" style="background-image:url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                                                                @else
                                                                    <div class="kt-avatar__holder" style="background-image:url({!! \AlphaDirect\Helper::getCloudFrontURL($newclaim->document) !!})"></div>
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
                                    <div class="kt-portlet__foot kt-portlet__foot--solid">
                                        <div class="kt-form__actions">
                                            <div class="row">
                                                @can('claim-edit')
                                                    <div class="col-5"></div>
                                                    <div class="col-7">
                                                        <button type="submit" value="Submit" id="submitBtn"  class="btn btn-brand">Update Claim</button>
                                                    </div>
                                                @endcan
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            <div id="StatusEditDiv" style="display:none;">
                @if($claims->claim_type == 'Accident' || $claims->claim_type == 'MOTORTRADERSEXTERNAL' || $claims->claim_type == 'MOTORTRADERSINTERNAL')
                    <div class="row">
                        <div class="col-md-12">
                            <div class="kt-portlet">
                                <div class="kt-portlet__head-label col-lg-12">
                                    <div class="col-lg-10">
                                        <h5 class="kt-portlet__head-title" style="padding-top: 20px;">
                                            Claim Status Details
                                        </h5>
                                    </div>
                                    <div class="col-lg-2" style="margin-left: 90%;">
                                        <button class="btn btn-warning"  id="claimStatusLabelBtn" onclick="setStatusToView()">Set to View</button>
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
                                                                @isset($users)
                                                                    @foreach($users as $user)
                                                                        <option value="{{$user->firstName}} {{$user->lastName}}"  @if( $claimAccident->claim_allocated_to == $user->firstName.' '.$user->lastName) selected @endif>
                                                                            {{$user->firstName}} {{$user->lastName}}
                                                                        </option>
                                                                    @endforeach
                                                                @endisset
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
                                                            <label for="exampleSelect1">Total Reserve Amount:</label>
                                                            <input type="text" class="form-control" value="{!! $reserve_amt !!}"  title="Please enter the amount" id="reserve_amount" name="reserve_amount" aria-describedby="emailHelp"  placeholder="P0.00" required>
                                                            <input type="hidden" value="{{ $policy->id }}" id="policyId">
                                                            <p style="display:none; color:red;" id="reserveMsg"></p>
                                                        </div>
                                                    </div>
                                                    <div class="col-lg-6">
                                                        <div class="form-group">
                                                            <label for="exampleSelect1">Total Payment Amount:</label>
                                                            <input type="text" class="form-control" id="paid_amount" value="{!! $payment_amt !!}" title="Please enter the amount" name="paid_amount" aria-describedby="emailHelp"  placeholder="P0.00" required>
                                                            <p style="display:none; color:red;" id="paidmsg"></p>
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
                                                                <option value="Subrogation" @if($claimAccident->claim_sub_status == 'Subrogation') selected @endif>Subrogation</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div id="selectStatusSubrogation" style="display: none;">
                                                    <div class="row">
                                                        <div class="col-lg-6">
                                                            <h3 class="col-form-label" style="float: left;">Notice of Demand</h3>
                                                            <div class="kt-avatar" id="notice_of_demand"style="float: left; clear: left;">
                                                                @if($claimAccident->notice_of_demand == NULL)
                                                                    <div class="kt-avatar__holder"style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                                                                @else
                                                                    <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($claimAccident->notice_of_demand) !!}"target="_blank" download>
                                                                        <div class="kt-avatar__holder" style="background-image:url({!! \AlphaDirect\Helper::getCloudFrontURL($claimAccident->notice_of_demand) !!})"></div>
                                                                    </a>
                                                                @endif
                                                                    <label class="kt-avatar__upload" data-toggle="kt-tooltip" title="notice_of_demand">
                                                                     <i class="fa fa-pen"></i>
                                                                        <input type='file' class="notice_of_demand"name="notice_of_demand"<?php echo config('app.accept_attr'); ?><?php echo config('app.accept_msg'); ?> />
                                                                    </label>
                                                                    <span class="kt-avatar__cancel" data-toggle="kt-tooltip" title="Cancel Image">
                                                                        <i class="fa fa-times"></i>
                                                                    </span>
                                                            </div>
                                                        </div>

                                                        <div class="col-lg-6">
                                                            <h3 class="col-form-label" style="float: left;">Final Demand Letter</h3>
                                                            <div class="kt-avatar" id="final_demand_letter"style="float: left; clear: left;">
                                                                @if($claimAccident->final_demand_letter == NULL)
                                                                    <div class="kt-avatar__holder"style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                                                                @else
                                                                    <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($claimAccident->final_demand_letter) !!}"target="_blank" download>
                                                                        <div class="kt-avatar__holder" style="background-image:url({!! \AlphaDirect\Helper::getCloudFrontURL($claimAccident->final_demand_letter) !!})"></div>
                                                                    </a>
                                                                @endif
                                                                    <label class="kt-avatar__upload" data-toggle="kt-tooltip" title="final_demand_letter">
                                                                     <i class="fa fa-pen"></i>
                                                                        <input type='file' class="final_demand_letter"name="final_demand_letter"<?php echo config('app.accept_attr'); ?><?php echo config('app.accept_msg'); ?> />
                                                                    </label>
                                                                    <span class="kt-avatar__cancel" data-toggle="kt-tooltip" title="Cancel Image">
                                                                        <i class="fa fa-times"></i>
                                                                    </span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <br>
                                                    <div class="row">
                                                        <div class="col-lg-6">
                                                            <h3 class="col-form-label" style="float: left;">Debt Acknowledgment form</h3>
                                                            <div class="kt-avatar" id="debt_acknowledgment"style="float: left; clear: left;">
                                                                @if($claimAccident->debt_acknowledgment == NULL)
                                                                    <div class="kt-avatar__holder"style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                                                                @else
                                                                    <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($claimAccident->debt_acknowledgment) !!}"target="_blank" download>
                                                                        <div class="kt-avatar__holder" style="background-image:url({!! \AlphaDirect\Helper::getCloudFrontURL($claimAccident->debt_acknowledgment) !!})"></div>
                                                                    </a>
                                                                @endif
                                                                    <label class="kt-avatar__upload" data-toggle="kt-tooltip" title="debt_acknowledgment">
                                                                     <i class="fa fa-pen"></i>
                                                                        <input type='file' class="debt_acknowledgment"name="debt_acknowledgment"<?php echo config('app.accept_attr'); ?><?php echo config('app.accept_msg'); ?> />
                                                                    </label>
                                                                    <span class="kt-avatar__cancel" data-toggle="kt-tooltip" title="Cancel Image">
                                                                        <i class="fa fa-times"></i>
                                                                    </span>
                                                            </div>
                                                        </div>
                                                        <div class="col-lg-6">
                                                            <h3 class="col-form-label" style="float: left;">KYC form</h3>
                                                            <div class="kt-avatar" id="kyc_form"style="float: left; clear: left;">
                                                                @if($claimAccident->kyc_form == NULL)
                                                                    <div class="kt-avatar__holder"style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                                                                @else
                                                                    <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($claimAccident->kyc_form) !!}"target="_blank" download>
                                                                        <div class="kt-avatar__holder" style="background-image:url({!! \AlphaDirect\Helper::getCloudFrontURL($claimAccident->kyc_form) !!})"></div>
                                                                    </a>
                                                                @endif
                                                                    <label class="kt-avatar__upload" data-toggle="kt-tooltip" title="kyc_form">
                                                                     <i class="fa fa-pen"></i>
                                                                        <input type='file' class="kyc_form"name="kyc_form"<?php echo config('app.accept_attr'); ?><?php echo config('app.accept_msg'); ?> />
                                                                    </label>
                                                                    <span class="kt-avatar__cancel" data-toggle="kt-tooltip" title="Cancel Image">
                                                                        <i class="fa fa-times"></i>
                                                                    </span>
                                                            </div>
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

                                                {{-- <div>
                                                     <button type="button" id="addbeneficiary_btn" style="display:none;" class="btn btn-brand"><i class="la la-plus"></i>Add Other Details </button>
                                                 </div>--}}

                                                {{--repeater code starts--}}
                                                <div class="row" id="add_details" style="display: none;">
                                                    <div class="col-lg-12">
                                                        <div class="kt-repeater">
                                                            <div class="kt-repeater__data-set">
                                                                <div data-repeater-list="beneficiary_status_details" id="beneficiary_status_details">
                                                                    <div data-repeater-item class="kt-repeater__item">
                                                                        <div class="form-group row select_beneficiary" id="new_check_beneficiary">

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

                                                                            <div class="col-lg-6 nextclass">
                                                                                <div class="form-group new_otherparty_dropdown"  style="display:none;">
                                                                                    {{-- <label for="exampleSelect1" style="padding-right: 15px">Other Party</label>
                                                                                     <select class="form-control kt_selectpicker"   id="otherparty_list" class="otherparty_list" name="otherparty_list" title="Please select other party" >
                                                                                     </select>--}}
                                                                                </div>
                                                                            </div>

                                                                        </div>
                                                                        <div class="details_div" style="display:none; margin-top:-3%;">
                                                                            <h5 class="bankh5" style="color:black;padding-top:30px;">Bank Details</h5>
                                                                            <br>
                                                                            <div class="row">
                                                                                <div class="col-lg-6">
                                                                                    <div class="form-group">
                                                                                        <label for="exampleSelect1" class="demo">Myzaka/Orange Money Cell</label>
                                                                                        <input type="text" class="form-control accident_billingCell" name="accident_billingCell" aria-describedby="emailHelp" title="Enter orange money cell"
                                                                                               placeholder="Myzaka/Orange Cell"  minlength="8" maxlength="8" pattern="[0-9]{8}" >
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
                                                                            <br>
                                                                            {{--<div class="row">
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
                                                                            </div>--}}
                                                                            <h5 style="color:black;padding-top:30px;">KYC Details</h5>
                                                                            <br>
                                                                            <div class="form-group row">
                                                                                <div class="col-md-2">
                                                                                    <h3 class="col-form-label" style="float: left;">Driving License</h3>
                                                                                    <div class="kt-avatar" id="driving_license" style="float: left; clear: left;">
                                                                                        <div class="kt-avatar__holder" style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                                                                                        <label class="kt-avatar__upload" data-toggle="kt-tooltip" title="Change Image">
                                                                                            <i class="fa fa-pen"></i>
                                                                                            <input type='file' class="driving_license" name="driving_license" <?php echo config('app.accept_attr'); ?>/>

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
                                                                                            <input type='file' class="omang" name="omang" <?php echo config('app.accept_attr'); ?> <?php echo config('app.accept_msg'); ?> />
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
                                                                                            <input type='file' class="proof_residence" name="proof_residence" <?php echo config('app.accept_attr'); ?> <?php echo config('app.accept_msg'); ?> />
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
                                                                @isset($salvageUser)
                                                                    @foreach($salvageUser as $user)
                                                                        <option value="{{$user->id}}" >{{$user->firstName}} {{$user->lastName}}</option>
                                                                    @endforeach
                                                                @endisset

                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="form-group row" @if($claims->claim_status == 'Rejected') style="display:block;" @else style="display:none;" @endif id="uploadFile">
                                                    <label for="example-text-input" class="col-3 col-form-label">Additional Form</label>
                                                    <div class="col-lg-9">
                                                        <div class="input-group">
                                                            <div class="kt-avatar" style="float: left; clear: left;" id="claimStatusFile">
                                                                @if($claimAccident->document  == NULL)
                                                                    <div class="kt-avatar__holder" style="background-image:url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                                                                @else
                                                                    <div class="kt-avatar__holder" style="background-image:url({!! \AlphaDirect\Helper::getCloudFrontURL($claimAccident->document) !!})"></div>
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
                                    <div class="kt-portlet__foot kt-portlet__foot--solid">
                                        <div class="kt-form__actions">
                                            <div class="row">
                                                @can('claim-edit')
                                                    <div class="col-5"></div>
                                                    <div class="col-7">
                                                        <button type="submit" value="Submit" id="submitBtn"  class="btn btn-brand">Update Claim</button>
                                                    </div>
                                                @endcan
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
                            {{-- @if($claims->claim_type == 'Life:Q') --}}
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
                            @if(trim($claims->claim_type) == 'Hospital CashBack' || trim($claims->claim_type) == 'Hospital Cash')
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
                            @if($claims->claim_type == 'Key Loss' && ($policy->product_id != 7 && $policy->product_id != 8))
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
                            @if($claims->claim_type == 'Cellphone')
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

                                @can('reserves_payments_tab')
                                <li class="nav-item">
                                    <a class="nav-link @if(session()->has('step') == 10) active @endif" data-toggle="tab" href="#kt_portlet_base_demo_3_10_tab_content" role="tab"> <i class="flaticon2-heart-rate-monitor" aria-hidden="true"></i>Reserves/Payments</a>
                                </li>
                                @endcan

                                @can('repair_centers_tab')
                                <li class="nav-item">
                                    <a class="nav-link @if(session()->has('step') == 7) active @endif" data-toggle="tab" href="#kt_portlet_base_demo_3_7_tab_content" role="tab"> <i class="flaticon2-heart-rate-monitor" aria-hidden="true"></i>Repair Centers</a>
                                </li>
                                @endcan

                                @can('invoice_tab')
                                <li class="nav-item">
                                    <a class="nav-link @if(session()->get('step') == 4) active @endif" data-toggle="tab" href="#kt_portlet_base_demo_3_4_tab_content" role="tab"> <i class="flaticon2-chronometer" aria-hidden="true"></i>Invoice </a>
                                </li>
                                @endcan
                            @endif
                            @if($claims->claim_type == 'Glass' && ($policy->product_id != 7 && $policy->product_id != 8))

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
                            @if($claims->claim_type == 'Accident' && ($policy->product_id != 7 && $policy->product_id != 8))
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
                            @if($claims->claim_type == 'BUSINESSINTERRUPTION' || $claims->claim_type == 'BUSINESSALLRISKS' || $claims->claim_type == 'ELECTRONICEQUIPMENT' || $claims->claim_type == 'PERSONALALLRISKS' || $claims->claim_type == 'THEFT' || $claims->claim_type == 'MONEY' || $claims->claim_type == 'WORKERSCOMPENSATION' || $claims->claim_type == 'STATEDBENEFITS' || $claims->claim_type == 'FIDELITYGUARANTEE' || $claims->claim_type == 'TRAVELINSURANCE' || $claims->claim_type == 'GOODSINTRANSIT' || $claims->claim_type == 'FIRE' || $claims->claim_type == 'LIABILITY'  || $claims->claim_type == 'PROPERTYDAMAGE' || $claims->claim_type == 'ACCIDENTALDAMAGE' || $claims->claim_type == 'DEFECTIVEWORKMANSHIP' || $claims->claim_type == 'MOBILEELECTRONICDEVICES' || $claims->claim_type == 'OFFICECONTENTS')
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

                                @can('reserves_payments_tab')
                                <li class="nav-item">
                                    <a class="nav-link @if(session()->has('step') == 10) active @endif" data-toggle="tab" href="#kt_portlet_base_demo_3_10_tab_content" role="tab"> <i class="flaticon2-heart-rate-monitor" aria-hidden="true"></i>Reserves/Payments</a>
                                </li>
                                @endcan

                                @can('supplier_tab')
                                <li class="nav-item">
                                    <a class="nav-link @if(session()->has('step') == 7) active @endif" data-toggle="tab" href="#kt_portlet_base_demo_3_7_tab_content" role="tab"> <i class="flaticon2-heart-rate-monitor" aria-hidden="true"></i>Suppliers</a>
                                </li>
                                @endcan
                                @can('invoice_tab')
                                    <li class="nav-item">
                                        <a class="nav-link @if(session()->get('step') == 4) active @endif" data-toggle="tab" href="#kt_portlet_base_demo_3_4_tab_content" role="tab"> <i class="flaticon2-chronometer" aria-hidden="true"></i>Invoice </a>
                                    </li>
                                @endcan
                                <li class="nav-item">
                                    <a class="nav-link @if(session()->get('step') == 5) active @endif" data-toggle="tab" href="#kt_portlet_base_demo_3_9_tab_content" role="tab"> <i class="flaticon2-pie-chart-2" aria-hidden="true"></i>Activity Log  </a>
                                </li>

                                <li class="nav-item">
                                    <a class="nav-link @if(session()->get('step') == 11) active @endif" data-toggle="tab" href="#kt_portlet_base_demo_3_11_tab_content" role="tab"> <i class="flaticon2-pie-chart-2" aria-hidden="true"></i>Complaint Log  </a>
                                </li>
                            @endif
                            @if(($claims->claim_type == 'Accident' || $claims->claim_type == 'MOTORTRADERSEXTERNAL' || $claims->claim_type == 'MOTORTRADERSINTERNAL') && ($policy->product_id == 7 || $policy->product_id == 8))
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

                                @can('reserves_payments_tab')
                                <li class="nav-item">
                                    <a class="nav-link @if(session()->has('step') == 10) active @endif" data-toggle="tab" href="#kt_portlet_base_demo_3_10_tab_content" role="tab"> <i class="flaticon2-heart-rate-monitor" aria-hidden="true"></i>Reserves/Payments</a>
                                </li>
                                @endcan

                                @can('supplier_tab')
                                <li class="nav-item">
                                    <a class="nav-link @if(session()->has('step') == 7) active @endif" data-toggle="tab" href="#kt_portlet_base_demo_3_7_tab_content" role="tab"> <i class="flaticon2-heart-rate-monitor" aria-hidden="true"></i>Suppliers</a>
                                </li>
                                @endcan
                                @can('invoice_tab')
                                    <li class="nav-item">
                                        <a class="nav-link @if(session()->get('step') == 4) active @endif" data-toggle="tab" href="#kt_portlet_base_demo_3_4_tab_content" role="tab"> <i class="flaticon2-chronometer" aria-hidden="true"></i>Invoice </a>
                                    </li>
                                @endcan
                                <li class="nav-item">
                                    <a class="nav-link @if(session()->get('step') == 5) active @endif" data-toggle="tab" href="#kt_portlet_base_demo_3_9_tab_content" role="tab"> <i class="flaticon2-pie-chart-2" aria-hidden="true"></i>Activity Log  </a>
                                </li>

                                <li class="nav-item">
                                    <a class="nav-link @if(session()->get('step') == 11) active @endif" data-toggle="tab" href="#kt_portlet_base_demo_3_11_tab_content" role="tab"> <i class="flaticon2-pie-chart-2" aria-hidden="true"></i>Complaint Log  </a>
                                </li>
                            @endif
                            @if($claims->claim_type == 'Glass' && ($policy->product_id == 7 || $policy->product_id == 8))
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

                                @can('reserves_payments_tab')
                                <li class="nav-item">
                                    <a class="nav-link @if(session()->has('step') == 10) active @endif" data-toggle="tab" href="#kt_portlet_base_demo_3_10_tab_content" role="tab"> <i class="flaticon2-heart-rate-monitor" aria-hidden="true"></i>Reserves/Payments</a>
                                </li>
                                @endcan

                                @can('supplier_tab')
                                <li class="nav-item">
                                    <a class="nav-link @if(session()->has('step') == 7) active @endif" data-toggle="tab" href="#kt_portlet_base_demo_3_7_tab_content" role="tab"> <i class="flaticon2-heart-rate-monitor" aria-hidden="true"></i>Suppliers</a>
                                </li>
                                @endcan
                                @can('invoice_tab')
                                    <li class="nav-item">
                                        <a class="nav-link @if(session()->get('step') == 4) active @endif" data-toggle="tab" href="#kt_portlet_base_demo_3_4_tab_content" role="tab"> <i class="flaticon2-chronometer" aria-hidden="true"></i>Invoice </a>
                                    </li>
                                @endcan
                                <li class="nav-item">
                                    <a class="nav-link @if(session()->get('step') == 5) active @endif" data-toggle="tab" href="#kt_portlet_base_demo_3_9_tab_content" role="tab"> <i class="flaticon2-pie-chart-2" aria-hidden="true"></i>Activity Log  </a>
                                </li>

                                <li class="nav-item">
                                    <a class="nav-link @if(session()->get('step') == 11) active @endif" data-toggle="tab" href="#kt_portlet_base_demo_3_11_tab_content" role="tab"> <i class="flaticon2-pie-chart-2" aria-hidden="true"></i>Complaint Log  </a>
                                </li>
                            @endif

                            @if($claims->claim_type == 'Key Loss' && ($policy->product_id == 7 || $policy->product_id == 8))
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

                                @can('reserves_payments_tab')
                                <li class="nav-item">
                                    <a class="nav-link @if(session()->has('step') == 10) active @endif" data-toggle="tab" href="#kt_portlet_base_demo_3_10_tab_content" role="tab"> <i class="flaticon2-heart-rate-monitor" aria-hidden="true"></i>Reserves/Payments</a>
                                </li>
                                @endcan

                                @can('supplier_tab')
                                <li class="nav-item">
                                    <a class="nav-link @if(session()->has('step') == 7) active @endif" data-toggle="tab" href="#kt_portlet_base_demo_3_7_tab_content" role="tab"> <i class="flaticon2-heart-rate-monitor" aria-hidden="true"></i>Suppliers</a>
                                </li>
                                @endcan
                                @can('invoice_tab')
                                    <li class="nav-item">
                                        <a class="nav-link @if(session()->get('step') == 4) active @endif" data-toggle="tab" href="#kt_portlet_base_demo_3_4_tab_content" role="tab"> <i class="flaticon2-chronometer" aria-hidden="true"></i>Invoice </a>
                                    </li>
                                @endcan
                                <li class="nav-item">
                                    <a class="nav-link @if(session()->get('step') == 5) active @endif" data-toggle="tab" href="#kt_portlet_base_demo_3_9_tab_content" role="tab"> <i class="flaticon2-pie-chart-2" aria-hidden="true"></i>Activity Log  </a>
                                </li>

                                <li class="nav-item">
                                    <a class="nav-link @if(session()->get('step') == 11) active @endif" data-toggle="tab" href="#kt_portlet_base_demo_3_11_tab_content" role="tab"> <i class="flaticon2-pie-chart-2" aria-hidden="true"></i>Complaint Log  </a>
                                </li>
                            @endif

                            {{-- @if($claims->claim_type == 'BUSINESSALLRISKS')
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

                                @can('reserves_payments_tab')
                                <li class="nav-item">
                                    <a class="nav-link @if(session()->has('step') == 10) active @endif" data-toggle="tab" href="#kt_portlet_base_demo_3_10_tab_content" role="tab"> <i class="flaticon2-heart-rate-monitor" aria-hidden="true"></i>Reserves/Payments</a>
                                </li>
                                @endcan

                                @can('supplier_tab')
                                <li class="nav-item">
                                    <a class="nav-link @if(session()->has('step') == 7) active @endif" data-toggle="tab" href="#kt_portlet_base_demo_3_7_tab_content" role="tab"> <i class="flaticon2-heart-rate-monitor" aria-hidden="true"></i>Suppliers</a>
                                </li>
                                @endcan
                                @can('invoice_tab')
                                    <li class="nav-item">
                                        <a class="nav-link @if(session()->get('step') == 4) active @endif" data-toggle="tab" href="#kt_portlet_base_demo_3_4_tab_content" role="tab"> <i class="flaticon2-chronometer" aria-hidden="true"></i>Invoice </a>
                                    </li>
                                @endcan
                                <li class="nav-item">
                                    <a class="nav-link @if(session()->get('step') == 5) active @endif" data-toggle="tab" href="#kt_portlet_base_demo_3_9_tab_content" role="tab"> <i class="flaticon2-pie-chart-2" aria-hidden="true"></i>Activity Log  </a>
                                </li>

                                <li class="nav-item">
                                    <a class="nav-link @if(session()->get('step') == 11) active @endif" data-toggle="tab" href="#kt_portlet_base_demo_3_11_tab_content" role="tab"> <i class="flaticon2-pie-chart-2" aria-hidden="true"></i>Complaint Log  </a>
                                </li>

                            @endif
                            @if($claims->claim_type == 'THEFT')
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

                                @can('reserves_payments_tab')
                                <li class="nav-item">
                                    <a class="nav-link @if(session()->has('step') == 10) active @endif" data-toggle="tab" href="#kt_portlet_base_demo_3_10_tab_content" role="tab"> <i class="flaticon2-heart-rate-monitor" aria-hidden="true"></i>Reserves/Payments</a>
                                </li>
                                @endcan

                                @can('supplier_tab')
                                <li class="nav-item">
                                    <a class="nav-link @if(session()->has('step') == 7) active @endif" data-toggle="tab" href="#kt_portlet_base_demo_3_7_tab_content" role="tab"> <i class="flaticon2-heart-rate-monitor" aria-hidden="true"></i>Suppliers</a>
                                </li>
                                @endcan
                                @can('invoice_tab')
                                    <li class="nav-item">
                                        <a class="nav-link @if(session()->get('step') == 4) active @endif" data-toggle="tab" href="#kt_portlet_base_demo_3_4_tab_content" role="tab"> <i class="flaticon2-chronometer" aria-hidden="true"></i>Invoice </a>
                                    </li>
                                @endcan
                                <li class="nav-item">
                                    <a class="nav-link @if(session()->get('step') == 5) active @endif" data-toggle="tab" href="#kt_portlet_base_demo_3_9_tab_content" role="tab"> <i class="flaticon2-pie-chart-2" aria-hidden="true"></i>Activity Log  </a>
                                </li>

                                <li class="nav-item">
                                    <a class="nav-link @if(session()->get('step') == 11) active @endif" data-toggle="tab" href="#kt_portlet_base_demo_3_11_tab_content" role="tab"> <i class="flaticon2-pie-chart-2" aria-hidden="true"></i>Complaint Log  </a>
                                </li>
                            @endif
                            @if($claims->claim_type == 'WORKERSCOMPENSATION' || $claims->claim_type == 'STATEDBENEFITS')
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

                                @can('reserves_payments_tab')
                                <li class="nav-item">
                                    <a class="nav-link @if(session()->has('step') == 10) active @endif" data-toggle="tab" href="#kt_portlet_base_demo_3_10_tab_content" role="tab"> <i class="flaticon2-heart-rate-monitor" aria-hidden="true"></i>Reserves/Payments</a>
                                </li>
                                @endcan

                                @can('supplier_tab')
                                <li class="nav-item">
                                    <a class="nav-link @if(session()->has('step') == 7) active @endif" data-toggle="tab" href="#kt_portlet_base_demo_3_7_tab_content" role="tab"> <i class="flaticon2-heart-rate-monitor" aria-hidden="true"></i>Suppliers</a>
                                </li>
                                @endcan
                                @can('invoice_tab')
                                    <li class="nav-item">
                                        <a class="nav-link @if(session()->get('step') == 4) active @endif" data-toggle="tab" href="#kt_portlet_base_demo_3_4_tab_content" role="tab"> <i class="flaticon2-chronometer" aria-hidden="true"></i>Invoice </a>
                                    </li>
                                @endcan
                                <li class="nav-item">
                                    <a class="nav-link @if(session()->get('step') == 5) active @endif" data-toggle="tab" href="#kt_portlet_base_demo_3_9_tab_content" role="tab"> <i class="flaticon2-pie-chart-2" aria-hidden="true"></i>Activity Log  </a>
                                </li>

                                <li class="nav-item">
                                    <a class="nav-link @if(session()->get('step') == 11) active @endif" data-toggle="tab" href="#kt_portlet_base_demo_3_11_tab_content" role="tab"> <i class="flaticon2-pie-chart-2" aria-hidden="true"></i>Complaint Log  </a>
                                </li>
                            @endif
                            @if($claims->claim_type == 'PUBLICLIABILITY')
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

                                @can('reserves_payments_tab')
                                <li class="nav-item">
                                    <a class="nav-link @if(session()->has('step') == 10) active @endif" data-toggle="tab" href="#kt_portlet_base_demo_3_10_tab_content" role="tab"> <i class="flaticon2-heart-rate-monitor" aria-hidden="true"></i>Reserves/Payments</a>
                                </li>
                                @endcan

                                @can('supplier_tab')
                                <li class="nav-item">
                                    <a class="nav-link @if(session()->has('step') == 7) active @endif" data-toggle="tab" href="#kt_portlet_base_demo_3_7_tab_content" role="tab"> <i class="flaticon2-heart-rate-monitor" aria-hidden="true"></i>Suppliers</a>
                                </li>
                                @endcan
                                @can('invoice_tab')
                                    <li class="nav-item">
                                        <a class="nav-link @if(session()->get('step') == 4) active @endif" data-toggle="tab" href="#kt_portlet_base_demo_3_4_tab_content" role="tab"> <i class="flaticon2-chronometer" aria-hidden="true"></i>Invoice </a>
                                    </li>
                                @endcan
                                <li class="nav-item">
                                    <a class="nav-link @if(session()->get('step') == 5) active @endif" data-toggle="tab" href="#kt_portlet_base_demo_3_9_tab_content" role="tab"> <i class="flaticon2-pie-chart-2" aria-hidden="true"></i>Activity Log  </a>
                                </li>

                                <li class="nav-item">
                                    <a class="nav-link @if(session()->get('step') == 11) active @endif" data-toggle="tab" href="#kt_portlet_base_demo_3_11_tab_content" role="tab"> <i class="flaticon2-pie-chart-2" aria-hidden="true"></i>Complaint Log  </a>
                                </li>
                            @endif

                            @if($claims->claim_type == 'PROPERTYLOSSDAMAGE')
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

                                @can('reserves_payments_tab')
                                <li class="nav-item">
                                    <a class="nav-link @if(session()->has('step') == 10) active @endif" data-toggle="tab" href="#kt_portlet_base_demo_3_10_tab_content" role="tab"> <i class="flaticon2-heart-rate-monitor" aria-hidden="true"></i>Reserves/Payments</a>
                                </li>
                                @endcan

                                @can('supplier_tab')
                                <li class="nav-item">
                                    <a class="nav-link @if(session()->has('step') == 7) active @endif" data-toggle="tab" href="#kt_portlet_base_demo_3_7_tab_content" role="tab"> <i class="flaticon2-heart-rate-monitor" aria-hidden="true"></i>Suppliers</a>
                                </li>
                                @endcan
                                @can('invoice_tab')
                                    <li class="nav-item">
                                        <a class="nav-link @if(session()->get('step') == 4) active @endif" data-toggle="tab" href="#kt_portlet_base_demo_3_4_tab_content" role="tab"> <i class="flaticon2-chronometer" aria-hidden="true"></i>Invoice </a>
                                    </li>
                                @endcan
                                <li class="nav-item">
                                    <a class="nav-link @if(session()->get('step') == 5) active @endif" data-toggle="tab" href="#kt_portlet_base_demo_3_9_tab_content" role="tab"> <i class="flaticon2-pie-chart-2" aria-hidden="true"></i>Activity Log  </a>
                                </li>

                                <li class="nav-item">
                                    <a class="nav-link @if(session()->get('step') == 11) active @endif" data-toggle="tab" href="#kt_portlet_base_demo_3_11_tab_content" role="tab"> <i class="flaticon2-pie-chart-2" aria-hidden="true"></i>Complaint Log  </a>
                                </li>
                            @endif

                            @if($claims->claim_type == 'FIDELITYGUARANTEE')
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

                                @can('reserves_payments_tab')
                                <li class="nav-item">
                                    <a class="nav-link @if(session()->has('step') == 10) active @endif" data-toggle="tab" href="#kt_portlet_base_demo_3_10_tab_content" role="tab"> <i class="flaticon2-heart-rate-monitor" aria-hidden="true"></i>Reserves/Payments</a>
                                </li>
                                @endcan

                                @can('supplier_tab')
                                <li class="nav-item">
                                    <a class="nav-link @if(session()->has('step') == 7) active @endif" data-toggle="tab" href="#kt_portlet_base_demo_3_7_tab_content" role="tab"> <i class="flaticon2-heart-rate-monitor" aria-hidden="true"></i>Suppliers</a>
                                </li>
                                @endcan
                                @can('invoice_tab')
                                    <li class="nav-item">
                                        <a class="nav-link @if(session()->get('step') == 4) active @endif" data-toggle="tab" href="#kt_portlet_base_demo_3_4_tab_content" role="tab"> <i class="flaticon2-chronometer" aria-hidden="true"></i>Invoice </a>
                                    </li>
                                @endcan
                                <li class="nav-item">
                                    <a class="nav-link @if(session()->get('step') == 5) active @endif" data-toggle="tab" href="#kt_portlet_base_demo_3_9_tab_content" role="tab"> <i class="flaticon2-pie-chart-2" aria-hidden="true"></i>Activity Log  </a>
                                </li>

                                <li class="nav-item">
                                    <a class="nav-link @if(session()->get('step') == 11) active @endif" data-toggle="tab" href="#kt_portlet_base_demo_3_11_tab_content" role="tab"> <i class="flaticon2-pie-chart-2" aria-hidden="true"></i>Complaint Log  </a>
                                </li>

                            @endif
                            @if($claims->claim_type == 'TRAVELINSURANCE')
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

                                @can('reserves_payments_tab')
                                <li class="nav-item">
                                    <a class="nav-link @if(session()->has('step') == 10) active @endif" data-toggle="tab" href="#kt_portlet_base_demo_3_10_tab_content" role="tab"> <i class="flaticon2-heart-rate-monitor" aria-hidden="true"></i>Reserves/Payments</a>
                                </li>
                                @endcan

                                @can('supplier_tab')
                                <li class="nav-item">
                                    <a class="nav-link @if(session()->has('step') == 7) active @endif" data-toggle="tab" href="#kt_portlet_base_demo_3_7_tab_content" role="tab"> <i class="flaticon2-heart-rate-monitor" aria-hidden="true"></i>Suppliers</a>
                                </li>
                                @endcan
                                @can('invoice_tab')
                                    <li class="nav-item">
                                        <a class="nav-link @if(session()->get('step') == 4) active @endif" data-toggle="tab" href="#kt_portlet_base_demo_3_4_tab_content" role="tab"> <i class="flaticon2-chronometer" aria-hidden="true"></i>Invoice </a>
                                    </li>
                                @endcan
                                <li class="nav-item">
                                    <a class="nav-link @if(session()->get('step') == 5) active @endif" data-toggle="tab" href="#kt_portlet_base_demo_3_9_tab_content" role="tab"> <i class="flaticon2-pie-chart-2" aria-hidden="true"></i>Activity Log  </a>
                                </li>

                                <li class="nav-item">
                                    <a class="nav-link @if(session()->get('step') == 11) active @endif" data-toggle="tab" href="#kt_portlet_base_demo_3_11_tab_content" role="tab"> <i class="flaticon2-pie-chart-2" aria-hidden="true"></i>Complaint Log  </a>
                                </li>

                            @endif --}}
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
                                                    @if ($policy->product_id == 7)
                                                        @if (isset($company))
                                                            <td>{{ $company->name }}</td>
                                                        @else
                                                            @if($userInfo != NULL)
                                                                @if($userInfo->firstName || $userInfo->lastName)
                                                                    <td>{!! ucwords($userInfo->firstName)!!}  {!!ucwords($userInfo->lastName) !!}</td>
                                                                @else
                                                                    <td>N/A</td>
                                                                @endif
                                                            @else
                                                                <td>N/A</td>
                                                            @endif
                                                        @endif
                                                    @elseif ($policy->product_id == 8)
                                                        @if (isset($company))
                                                            <td>{{ $company->name }}</td>
                                                        @else
                                                            @if($userInfo != NULL)
                                                                @if($userInfo->firstName || $userInfo->lastName)
                                                                    <td>{!! ucwords($userInfo->firstName)!!}  {!!ucwords($userInfo->lastName) !!}</td>
                                                                @else
                                                                    <td>N/A</td>
                                                                @endif
                                                            @else
                                                                <td>N/A</td>
                                                            @endif
                                                        @endif
                                                    @else
                                                        @if($userInfo != NULL)
                                                            @if($userInfo->firstName || $userInfo->lastName)
                                                                <td>{!! ucwords($userInfo->firstName)!!}  {!!ucwords($userInfo->lastName) !!}</td>
                                                            @else
                                                                <td>Name not found</td>
                                                            @endif
                                                        @else
                                                            <td>User not found</td>
                                                        @endif
                                                    @endif

                                                    <th>Email</th>
                                                    <td>{!! $userInfo->email !!}</td>
                                                </tr>
                                                <tr>
                                                    <th>Cellphone</th>
                                                    <td>{!! $userInfo->cellphone !!}</td>
                                                    <th>Address</th>
                                                    <td>{!! ucwords($userInfo->profile->address) !!}</td>
                                                </tr>
                                                <tr>
                                                    <th>Omang ID</th>
                                                    <td>
                                                        @if($userInfo->profile->omang != null)
                                                            {!! $userInfo->profile->omang !!}
                                                        @else
                                                            N/A
                                                        @endif
                                                    </td>
                                                    <th>Passport</th>
                                                    <td>
                                                        @if($userInfo->profile->passport != null)
                                                            {!! $userInfo->profile->passport !!}
                                                        @else
                                                            N/A
                                                        @endif
                                                    </td>

                                                </tr>

                                                <tr>
                                                    <th>Date of Birth</th>
                                                    <td>{!! \Carbon\Carbon::parse($userInfo->profile->dob)->format('d-m-Y') !!}</td>
                                                    @if($userInfo->banking != NULL)
                                                        <th>Billing Start Date</th>
                                                        <td>{!! \Carbon\Carbon::parse($userInfo->banking->billingStartDate)->format('d-m-Y') !!}</td>
                                                    @endif
                                                </tr>
                                                <tr>
                                                <th>Gender</th>
                                                    @if($userInfo->profile->gender == 1)
                                                        <td> Male </td>
                                                    @else
                                                        <td> Female </td>
                                                    @endif

                                                 <th>Maritial Status</th>
                                                    @if($userInfo->profile->maritalstatus == 1)
                                                    <td> Single </td>
                                                    @elseif($userInfo->profile->maritalstatus == 2)
                                                    <td> Married </td>
                                                    @elseif($userInfo->profile->maritalstatus == 3)
                                                    <td> Divorced </td>
                                                    @elseif($userInfo->profile->maritalstatus == 4)
                                                    <td> Widowed </td>
                                                    @elseif($userInfo->profile->maritalstatus == 5)
                                                    <td> Living Together(NOT Married) </td>
                                                    @elseif($userInfo->profile->maritalstatus == 6)
                                                    <td> Living Together(NOT Married) </td>
                                                    @else
                                                    <td>N/A</td>
                                                    @endif
                                                </tr>
                                                <tr>
                                                    <th>Driving License Number</th>
                                                    <td>
                                                        @isset($userInfo->profile->driving_license_number)
                                                        @if($userInfo->profile->driving_license_number != null)
                                                          {!! $userInfo->profile->driving_license_number !!}
                                                       @else
                                                            N/A
                                                        @endif
                                                        @endisset
                                                      </td>
                                                    <th>License Valid Till</th>
                                                    <td>
                                                        @isset($userInfo->profile->license_valid_till)
                                                            @if($userInfo->profile->license_valid_till != null)
                                                                {!! \Carbon\Carbon::parse($userInfo->profile->license_valid_till)->format('d-m-Y') !!}
                                                            @else

                                                            N/A
                                                            @endif
                                                        @endisset
                                                    </td>
                                                </tr>

                                                <tr>
                                                    <th>City/Town</th>
                                                    @if($cityName != null)
                                                        <td>{!! $cityName !!}</td>
                                                    @else
                                                        <td>N/A</td>
                                                    @endif

                                                    <th>State/District</th>
                                                    @if($stateName != null)
                                                        <td>{!! $stateName !!}</td>
                                                    @else
                                                        <td>N/A</td>
                                                    @endif
                                                </tr>
                                                <tr>
                                                       <th>Passport Issuing Country</th>
                                                            @if($passpostIssueCountry != null)
                                                                <td>{!! $passpostIssueCountry->name !!}</td>
                                                            @else
                                                                <td>N/A</td>
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
                                            KYC
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
                                                        @if(isset($kyc->driving_license) && $kyc->driving_license == NULL)
                                                            <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="100%" height="auto" >
                                                        @else
                                                            <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($kyc->driving_license) !!}" target="_blank" download>
                                                                    @if(pathinfo($kyc->driving_license, PATHINFO_EXTENSION) == 'pdf')
                                                                        <img src="{{asset('images/pdf.ico')}}" width="100%" height="auto" >
                                                                    @elseif(pathinfo($kyc->driving_license, PATHINFO_EXTENSION) == 'docx' || pathinfo($kyc->driving_license, PATHINFO_EXTENSION) == 'doc' || pathinfo($kyc->driving_license, PATHINFO_EXTENSION) == 'docm')
                                                                        <img src="{{asset('images/word.ico')}}" width="100%" height="auto" >
                                                                    @elseif(pathinfo($kyc->driving_license, PATHINFO_EXTENSION) == 'xls' || pathinfo($kyc->driving_license, PATHINFO_EXTENSION) == 'xlsx' || pathinfo($kyc->driving_license, PATHINFO_EXTENSION) == 'csv')
                                                                        <img src="{{asset('images/excel.png')}}" width="100%" height="auto" >
                                                                    @elseif(pathinfo($kyc->driving_license, PATHINFO_EXTENSION) == 'jpeg' || pathinfo($kyc->driving_license, PATHINFO_EXTENSION) == 'jpg' || pathinfo($kyc->driving_license, PATHINFO_EXTENSION) == 'png')
                                                                        <img src="{{\AlphaDirect\Helper::getCloudFrontURL($kyc->driving_license)}}" width="100%" height="auto" >
                                                                    @else
                                                                        <img src="{{asset('images/doc.png')}}" width="100%" height="auto" >
                                                                    @endif
                                                            </a>
                                                        @endif
                                                        <div class="kt-avatar" style="float: left; clear: left;"></div>
                                                    </div>

                                                    <div class="col-md-2">
                                                        <h3 class="col-form-label" style="float: left;">Omang ID</h3>
                                                        @if(isset($kyc->omang) && $kyc->omang == NULL)
                                                            <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="100%" height="auto" >
                                                        @else
                                                            <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($kyc->omang) !!}" target="_blank" download>
                                                                @if(pathinfo($kyc->omang, PATHINFO_EXTENSION) == 'pdf')
                                                                    <img src="{{asset('images/pdf.ico')}}" width="100%" height="auto" >
                                                                @elseif(pathinfo($kyc->omang, PATHINFO_EXTENSION) == 'docx' || pathinfo($kyc->omang, PATHINFO_EXTENSION) == 'doc' || pathinfo($kyc->omang, PATHINFO_EXTENSION) == 'docm')
                                                                    <img src="{{asset('images/word.ico')}}" width="100%" height="auto" >
                                                                @elseif(pathinfo($kyc->omang, PATHINFO_EXTENSION) == 'xls' || pathinfo($kyc->omang, PATHINFO_EXTENSION) == 'xlsx' || pathinfo($kyc->omang, PATHINFO_EXTENSION) == 'csv')
                                                                    <img src="{{asset('images/excel.png')}}" width="100%" height="auto" >
                                                                @elseif(pathinfo($kyc->omang, PATHINFO_EXTENSION) == 'jpeg' || pathinfo($kyc->omang, PATHINFO_EXTENSION) == 'jpg' || pathinfo($kyc->omang, PATHINFO_EXTENSION) == 'png')
                                                                    <img src="{{\AlphaDirect\Helper::getCloudFrontURL($kyc->omang)}}" width="100%" height="auto" >
                                                                @else
                                                                    <img src="{{asset('images/doc.png')}}" width="100%" height="auto" >
                                                                @endif
                                                            </a>
                                                        @endif
                                                        <div class="kt-avatar" style="float: left; clear: left;"></div>
                                                    </div>

                                                    <div class="col-md-2">
                                                        <h3 class="col-form-label" style="float: left;">Omang ID Back</h3>
                                                        @if(isset($kyc->omangBack) && $kyc->omangBack == NULL)
                                                            <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="100%" height="auto" >
                                                        @else
                                                            <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($kyc->omangBack) !!}" target="_blank" download>
                                                                @if(pathinfo($kyc->omangBack, PATHINFO_EXTENSION) == 'pdf')
                                                                    <img src="{{asset('images/pdf.ico')}}" width="100%" height="auto" >
                                                                @elseif(pathinfo($kyc->omangBack, PATHINFO_EXTENSION) == 'docx' || pathinfo($kyc->omangBack, PATHINFO_EXTENSION) == 'doc' || pathinfo($kyc->omang, PATHINFO_EXTENSION) == 'docm')
                                                                    <img src="{{asset('images/word.ico')}}" width="100%" height="auto" >
                                                                @elseif(pathinfo($kyc->omangBack, PATHINFO_EXTENSION) == 'xls' || pathinfo($kyc->omangBack, PATHINFO_EXTENSION) == 'xlsx' || pathinfo($kyc->omang, PATHINFO_EXTENSION) == 'csv')
                                                                    <img src="{{asset('images/excel.png')}}" width="100%" height="auto" >
                                                                @elseif(pathinfo($kyc->omangBack, PATHINFO_EXTENSION) == 'jpeg' || pathinfo($kyc->omangBack, PATHINFO_EXTENSION) == 'jpg' || pathinfo($kyc->omang, PATHINFO_EXTENSION) == 'png')
                                                                    <img src="{{\AlphaDirect\Helper::getCloudFrontURL($kyc->omangBack)}}" width="100%" height="auto" >
                                                                @else
                                                                    <img src="{{asset('images/doc.png')}}" width="100%" height="auto" >
                                                                @endif
                                                            </a>
                                                        @endif
                                                        <div class="kt-avatar" style="float: left; clear: left;"></div>
                                                    </div>

                                                    <div class="col-md-2">
                                                        <h3 class="col-form-label" style="float: left;">Proof of Residence</h3>
                                                        @if(isset($kyc->proof_residence) && $kyc->proof_residence == NULL)
                                                            <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="100%" height="auto" >
                                                        @else
                                                            <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($kyc->proof_residence) !!}" target="_blank" download>
                                                                @if(pathinfo($kyc->proof_residence, PATHINFO_EXTENSION) == 'pdf')
                                                                    <img src="{{asset('images/pdf.ico')}}" width="100%" height="auto" >
                                                                @elseif(pathinfo($kyc->proof_residence, PATHINFO_EXTENSION) == 'docx' || pathinfo($kyc->proof_residence, PATHINFO_EXTENSION) == 'doc' || pathinfo($kyc->proof_residence, PATHINFO_EXTENSION) == 'docm')
                                                                    <img src="{{asset('images/word.ico')}}" width="100%" height="auto" >
                                                                @elseif(pathinfo($kyc->proof_residence, PATHINFO_EXTENSION) == 'xls' || pathinfo($kyc->proof_residence, PATHINFO_EXTENSION) == 'xlsx' || pathinfo($kyc->proof_residence, PATHINFO_EXTENSION) == 'csv')
                                                                    <img src="{{asset('images/excel.png')}}" width="100%" height="auto" >
                                                                @elseif(pathinfo($kyc->proof_residence, PATHINFO_EXTENSION) == 'jpeg' || pathinfo($kyc->proof_residence, PATHINFO_EXTENSION) == 'jpg' || pathinfo($kyc->proof_residence, PATHINFO_EXTENSION) == 'png')
                                                                    <img src="{{\AlphaDirect\Helper::getCloudFrontURL($kyc->proof_residence)}}" width="100%" height="auto" >
                                                                @else
                                                                    <img src="{{asset('images/doc.png')}}" width="100%" height="auto" >
                                                                @endif
                                                            </a>
                                                        @endif
                                                        <div class="kt-avatar" style="float: left; clear: left;">
                                                        </div>
                                                    </div>

                                                    <div class="col-md-2">
                                                        <h3 class="col-form-label" style="float: left;">Proof of Income</h3>
                                                        @if(isset($kyc->proof_income) && $kyc->proof_income == NULL)
                                                            <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="100%" height="auto" >
                                                        @else
                                                            <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($kyc->proof_income) !!}" target="_blank" download>
                                                                @if(pathinfo($kyc->proof_income, PATHINFO_EXTENSION) == 'pdf')
                                                                    <img src="{{asset('images/pdf.ico')}}" width="100%" height="auto" >
                                                                @elseif(pathinfo($kyc->proof_income, PATHINFO_EXTENSION) == 'docx' || pathinfo($kyc->proof_income, PATHINFO_EXTENSION) == 'doc' || pathinfo($kyc->proof_income, PATHINFO_EXTENSION) == 'docm')
                                                                    <img src="{{asset('images/word.ico')}}" width="100%" height="auto" >
                                                                @elseif(pathinfo($kyc->proof_income, PATHINFO_EXTENSION) == 'xls' || pathinfo($kyc->proof_income, PATHINFO_EXTENSION) == 'xlsx' || pathinfo($kyc->proof_income, PATHINFO_EXTENSION) == 'csv')
                                                                    <img src="{{asset('images/excel.png')}}" width="100%" height="auto" >
                                                                @elseif(pathinfo($kyc->proof_income, PATHINFO_EXTENSION) == 'jpeg' || pathinfo($kyc->proof_income, PATHINFO_EXTENSION) == 'jpg' || pathinfo($kyc->proof_income, PATHINFO_EXTENSION) == 'png')
                                                                    <img src="{{\AlphaDirect\Helper::getCloudFrontURL($kyc->proof_income)}}" width="100%" height="auto" >
                                                                @else
                                                                    <img src="{{asset('images/doc.png')}}" width="100%" height="auto" >
                                                                @endif
                                                            </a>
                                                        @endif
                                                        <div class="kt-avatar" style="float: left; clear: left;">
                                                        </div>
                                                    </div>

                                                    <div class="col-md-2">
                                                        <h3 class="col-form-label" style="float: left;">Passport</h3>
                                                        @if(isset($kyc->passport) && $kyc->passport == NULL)
                                                            <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="100%" height="auto" >
                                                        @else
                                                            <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($kyc->passport) !!}" target="_blank" download>
                                                                @if(pathinfo($kyc->passport, PATHINFO_EXTENSION) == 'pdf')
                                                                    <img src="{{asset('images/pdf.ico')}}" width="100%" height="auto" >
                                                                @elseif(pathinfo($kyc->passport, PATHINFO_EXTENSION) == 'docx' || pathinfo($kyc->passport, PATHINFO_EXTENSION) == 'doc' || pathinfo($kyc->passport, PATHINFO_EXTENSION) == 'docm')
                                                                    <img src="{{asset('images/word.ico')}}" width="100%" height="auto" >
                                                                @elseif(pathinfo($kyc->passport, PATHINFO_EXTENSION) == 'xls' || pathinfo($kyc->passport, PATHINFO_EXTENSION) == 'xlsx' || pathinfo($kyc->passport, PATHINFO_EXTENSION) == 'csv')
                                                                    <img src="{{asset('images/excel.png')}}" width="100%" height="auto" >
                                                                @elseif(pathinfo($kyc->passport, PATHINFO_EXTENSION) == 'jpeg' || pathinfo($kyc->passport, PATHINFO_EXTENSION) == 'jpg')
                                                                    <img src="{{\AlphaDirect\Helper::getCloudFrontURL($kyc->passport)}}" width="100%" height="auto" >
                                                                @elseif(pathinfo($kyc->passport, PATHINFO_EXTENSION) == 'png')
                                                                    <img src="{{asset('images/doc.png')}}" width="100%" height="auto" >
                                                                @else
                                                                    <img src="{{asset('images/doc.png')}}" width="100%" height="auto" >
                                                                @endif
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
                                            Product Details:
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

                                                <tr>
                                                   <th>Sum Assured / Insured</th>
                                                   <td>P{!! number_format($policy->sum_assured,0,'.',',') !!}</td>
                                                </tr>

                                                <tr>
                                                   <th>Store Name</th>
                                                    @if($storeName != null)
                                                     <td>{!! $storeName !!}</td>
                                                     @else
                                                     <td>N/A</td>
                                                     @endif
                                                </tr>

                                                @isset($productFactors)
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
                                                @endisset

                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>

                                <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>
                                    @if($policy->product_id == 4)
                                        <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>
                                        <div class="kt-portlet__head">
                                            <div class="kt-portlet__head-label">
                                                <h3 class="kt-portlet__head-title">
                                                Employer Details
                                                </h3>
                                            </div>
                                        </div>
                                        <div class="kt-portlet_body">
                                            <div class="kt-section">
                                                <div class="kt-section__content" style="padding-left:20px; padding-right: 20px;">
                                                    <table class="table table-striped m-table">
                                                        <tbody>
                                                            <tr style="border-top: solid 2px #666;">
                                                                <th>Employer Name</th>
                                                                @if($userInfo->profile && $userInfo->profile->e_name != null)
                                                                <td>{!! ucwords($userInfo->profile->e_name) !!}</td>
                                                                @else
                                                                <td>N/A</td>
                                                                @endif

                                                                <th>Employee Number</th>
                                                                @if($userInfo->profile && $userInfo->profile->emp_no != null)
                                                                <td>{!! $userInfo->profile->emp_no !!}</td>
                                                                @else
                                                                <td>N/A</td>
                                                                @endif
                                                            </tr>
                                                            <tr>
                                                                <th>Cellphone</th>
                                                                @if($userInfo->profile && $userInfo->profile->emp_phone  != null)
                                                                <td>{!! $userInfo->profile->emp_phone !!}</td>
                                                                @else
                                                                <td>N/A</td>
                                                                @endif

                                                                <th>Salary Pay Date</th>
                                                                <td>
                                                                    @if($userInfo->profile && $userInfo->profile->salary_pay_date != null)
                                                                    {!! Carbon::parse(str_replace("/", "-", $userInfo->profile->salary_pay_date))->format('d-m-Y') !!}
                                                                    @else
                                                                    N/A
                                                                    @endif
                                                                </td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>
                                @if($policy->product_id == 4)
                                    @if($beneficiaries != null)
                                        <div class="kt-portlet__head">
                                            <div class="kt-portlet__head-label">
                                                <h3 class="kt-portlet__head-title">
                                                    Spouse / Life Partner Details
                                                </h3>
                                            </div>
                                        </div>
                                        <div class="kt-portlet_body">
                                            <div class="kt-section">
                                                <div class="kt-section__content"
                                                    style="padding-left:20px; padding-right: 20px;">
                                                    <table class="table table-striped m-table">
                                                        <tbody>
                                                            @foreach ($beneficiaries as $key => $beneficiarys)
                                                                @if($beneficiarys->relation == 'Spouse')
                                                                    <tr style="border-top: solid 2px #666;">
                                                                        <th>Name</th>
                                                                        <td>{!! ucwords($beneficiarys->first_name) !!}
                                                                            @if ($beneficiarys->middle_name) {!! ucwords($beneficiarys->middle_name) !!} @endif
                                                                            {!! ucwords($beneficiarys->last_name) !!}
                                                                        </td>

                                                                        <th>Date of Birth</th>
                                                                        @if (isset($beneficiarys->dob) && ($beneficiarys->dob != null) || ($beneficiarys->dob !=''))
                                                                        <td>{!! Carbon::parse(str_replace("/", "-", $beneficiarys->dob))->format('d-m-Y') !!}</td>
                                                                        {{-- <td>{!! $beneficiarys->dob !!}</td> --}}
                                                                        @else
                                                                            <td>N/A</td>
                                                                        @endif
                                                                    </tr>
                                                                    <tr>
                                                                        <th>Omang</th>
                                                                        @if (isset($beneficiarys->omang) && ($beneficiarys->omang != null) || ($beneficiarys->omang !=''))
                                                                            <td> {{ $beneficiarys->omang }}</td>
                                                                        @else
                                                                            <td>N/A</td>
                                                                        @endif

                                                                        <th>Gender</th>
                                                                        @if ($beneficiarys && ($beneficiarys->gender != null) || ($beneficiarys->gender !=''))
                                                                            @if ($beneficiarys->gender == 0)
                                                                            <td>Female </td>
                                                                            @else
                                                                                <td>Male</td>
                                                                            @endif
                                                                        @else
                                                                        <td>N/A</td>
                                                                        @endif
                                                                    </tr>
                                                                    <tr>
                                                                        <th>Passport</th>
                                                                        @if ($beneficiarys && ($beneficiarys->passport != null) || ($beneficiarys->passport !=''))
                                                                            <td>{{ $beneficiarys->passport }}</td>
                                                                        @else
                                                                            <td>N/A</td>
                                                                        @endif

                                                                        <th>Email</th>
                                                                        @if ($beneficiarys && ($beneficiarys->email != null) || ($beneficiarys->email !=''))
                                                                            <td>{{ $beneficiarys->email }}</td>
                                                                        @else
                                                                            <td>N/A</td>
                                                                        @endif
                                                                    </tr>

                                                                    <tr>
                                                                        <th>Mobile Number</th>
                                                                        @if ($beneficiarys && ($beneficiarys->cellphone != null) || ($beneficiarys->cellphone !=''))
                                                                            <td>{{ $beneficiarys->cellphone }}</td>
                                                                        @else
                                                                            <td>N/A</td>
                                                                        @endif
                                                                    </tr>
                                                                    <tr>
                                                                        <th>Omang Expiry </th>
                                                                        @if ($beneficiarys && ($beneficiarys->legalOmangExpiry != null) || ($beneficiarys->legalOmangExpiry !=''))
                                                                            <td> {!! Carbon::parse(str_replace("/", "-", $beneficiarys->legalOmangExpiry))->format('d-m-Y') !!}</td>
                                                                        @else
                                                                            <td>N/A</td>
                                                                        @endif
                                                                    </tr>
                                                                    <tr>
                                                                        <th>Passport Expiry</th>
                                                                        @if ($beneficiarys && ($beneficiarys->legalPassportExpiry != null) || ($beneficiarys->legalPassportExpiry !=''))
                                                                        <td> {!! Carbon::parse(str_replace("/", "-", $beneficiarys->legalPassportExpiry))->format('d-m-Y') !!}</td>
                                                                        @else
                                                                            <td>N/A</td>
                                                                        @endif
                                                                    </tr>
                                                                @endif
                                                            @endforeach

                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                @endif
                                <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>
                                @isset($policy_cellphone)
                                @foreach($policy_cellphone as $key => $pok)
                                <div class="kt-portlet__head">
                                    <div class="kt-portlet__head-label">
                                        <h3 class="kt-portlet__head-title">
                                          Device Details:{!! $key+1 !!}
                                        </h3>
                                    </div>
                                </div>
                                <div class="kt-portlet_body">
                                    <div class="kt-section">
                                        <div class="kt-section__content" style="padding-left:20px; padding-right: 20px;">
                                            <table class="table table-striped m-table">
                                                <tbody>

                                                <tr>
                                                    <th>Device Type</th>
                                                    <td>{!! $pok->device_type !!}</td>
                                                    @if($pok->device_type == 'cellphone')
                                                        <th>IMEI</th>
                                                    @else
                                                        <th>Serial Number</th>
                                                    @endif
                                                    <td>{!! $pok->imei !!}</td>
                                                </tr>
                                                <tr>
                                                    <th>Device Make</th>
                                                    <td>{!! $pok->cell_phone_make !!}</td>
                                                    <th>Device Model</th>
                                                    <td>{!! $pok->cell_phone_model !!}</td>
                                                </tr>
                                                <tr>
                                                    <th>Device Value</th>
                                                    <td>P{!! number_format($pok->phone_value,0,'.',',') !!}</td>
                                                    <th></th>
                                                    <td></td>
                                                </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>

                                <div class="kt-portlet_body">
                                    <div class="kt-section">
                                        <div class="kt-section__content" style="padding-left:20px; padding-right: 20px;">
                                        <h3 class="kt-heading kt-heading&#45;&#45;md">
                                          Cellphone Pre-inspection Photos
                                       </h3>
                                        <div class="form-group row">
                                                                    <div class="col-md-2">
                                                                        <h3 class="col-form-label" style="float: left;">Cell Phone Front</h3>
                                                                        <div class="kt-avatar"
                                                                            style="float: left; clear: left;">
                                                                            @if($pok->cell_phone_front == NULL)
                                                                                <div class="kt-avatar__holder"
                                                                                    style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)">
                                                                                </div>
                                                                            @else
                                                                            <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($pok->cell_phone_front) !!}" target="_blank" download>
                                                                                <div class="kt-avatar__holder"
                                                                                    style="background-image:url({{ \AlphaDirect\Helper::getCloudFrontURL($pok->cell_phone_front) }}) !important;">
                                                                                </div>
                                                                            </a>
                                                                            @endif

                                                                        </div>
                                                                    </div>
                                                                    <div class="col-md-2">
                                                                        <h3 class="col-form-label" style="float: left;">Cell Phone Back</h3>
                                                                        <div class="kt-avatar"
                                                                            style="float: left; clear: left;">
                                                                            @if($pok->cell_phone_back == NULL)
                                                                                <div class="kt-avatar__holder"
                                                                                    style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)">
                                                                                </div>
                                                                            @else
                                                                            <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($pok->cell_phone_back) !!}" target="_blank" download>
                                                                                <div class="kt-avatar__holder"style="background-image:url({{ \AlphaDirect\Helper::getCloudFrontURL($pok->cell_phone_back) }}) !important;">
                                                                               </div>
                                                                            </a>
                                                                            @endif

                                                                        </div>
                                                                    </div>
                                                                    <div class="col-md-2">
                                                                    <h3 class="col-form-label" style="float: left;">Cell Phone Left</h3>
                                                                        <div class="kt-avatar"
                                                                            style="float: left; clear: left;">
                                                                            @if($pok->cell_phone_left == NULL)
                                                                                <div class="kt-avatar__holder"
                                                                                    style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)">
                                                                                </div>
                                                                            @else
                                                                            <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($pok->cell_phone_left) !!}" target="_blank" download>
                                                                                <div class="kt-avatar__holder"
                                                                                    style="background-image:url({{ \AlphaDirect\Helper::getCloudFrontURL($pok->cell_phone_left) }}) !important;">
                                                                                </div>
                                                                            </a>
                                                                            @endif

                                                                        </div>
                                                                    </div>
                                                                    <div class="col-md-2">
                                                                    <h3 class="col-form-label" style="float: left;">Cell Phone Right</h3>
                                                                        <div class="kt-avatar"
                                                                            style="float: left; clear: left;">
                                                                            @if($pok->cell_phone_right == NULL)
                                                                                <div class="kt-avatar__holder"
                                                                                    style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)">
                                                                                </div>
                                                                            @else
                                                                            <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($pok->cell_phone_right) !!}" target="_blank" download>
                                                                                <div class="kt-avatar__holder"
                                                                                    style="background-image:url({{ \AlphaDirect\Helper::getCloudFrontURL($pok->cell_phone_right) }}) !important;">
                                                                                </div>
                                                                            </a>
                                                                            @endif

                                                                        </div>
                                                                    </div>
                                                                    <div class="col-md-2">
                                                                    <h3 class="col-form-label" style="float: left;">Cell Phone Top</h3>
                                                                        <div class="kt-avatar"
                                                                            style="float: left; clear: left;">
                                                                            @if($pok->cell_phone_top == NULL)
                                                                                <div class="kt-avatar__holder"
                                                                                    style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)">
                                                                                </div>
                                                                            @else
                                                                            <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($pok->cell_phone_top) !!}" target="_blank" download>
                                                                                <div class="kt-avatar__holder"
                                                                                    style="background-image:url({{ \AlphaDirect\Helper::getCloudFrontURL($pok->cell_phone_top) }}) !important;">
                                                                                </div>
                                                                            </a>
                                                                            @endif

                                                                        </div>
                                                                    </div>
                                                                    <div class="col-md-2">
                                                                    <h3 class="col-form-label" style="float: left;">Cell Phone Bottom</h3>
                                                                        <div class="kt-avatar"
                                                                            style="float: left; clear: left;">
                                                                            @if($pok->cell_phone_bottom == NULL)
                                                                                <div class="kt-avatar__holder"
                                                                                    style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)">
                                                                                </div>
                                                                            @else
                                                                            <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($pok->cell_phone_bottom) !!}" target="_blank" download>
                                                                                <div class="kt-avatar__holder"
                                                                                    style="background-image:url({{ \AlphaDirect\Helper::getCloudFrontURL($pok->cell_phone_bottom) }}) !important;">
                                                                                </div>
                                                                            </a>
                                                                            @endif

                                                                        </div>
                                                                    </div>
                                                                </div>
                                        </div>
                                    </div>
                                </div>
                               @endforeach


                                @endisset

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
                                                            @isset($vehicle_purpose)
                                                                @foreach($vehicle_purpose as $purpose)
                                                                    @if($purpose->id == $vehicle->purpose)
                                                                        {!! $purpose->value !!}
                                                                    @endif
                                                                @endforeach
                                                            @endisset

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
                                                                    <img src="{{\AlphaDirect\Helper::getCloudFrontURL($vehicle->front) }}" width="100%" height="auto" >
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

                                    <!-- <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>
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
                                                    @isset($policyCover)
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
                                                    @endisset

                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div> -->
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
                                                    @isset($policyMotorItems)
                                                        @foreach($policyMotorItems as $policyMotorItem)
                                                        @if(!empty($motor_items))
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
                                                    @endisset

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
                                                    @isset($members)
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
                                                    @endisset

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
                                            Bank  Details
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
                                    <div class="row">
                                        <div class="col-12">
                                            <a class="btn btn-secondary" href="{{ route('admin.policy.index') }}" >Cancel</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                @if($claims->claim_type == 'Accident' && ($policy->product_id != 7 && $policy->product_id != 8))
                    <div class="tab-pane @if(session()->get('step') == 5) active @endif" id="kt_portlet_base_demo_3_5_tab_content" role="tabpanel">
                        @include('admin.claims.claim_details')
                    </div>
                    <div class="tab-pane @if(session()->get('step') == 6) active @endif" id="kt_portlet_base_demo_3_6_tab_content" role="tabpanel">
                        @include('admin.claims.accident')
                    </div>
                    <div class="tab-pane @if(session()->get('step') == 7) active @endif" id="kt_portlet_base_demo_3_7_tab_content" role="tabpanel">
                        @include('admin.claims.accident_supplier')
                    </div>
                @endif
                @if($claims->claim_type == 'Cellphone')
                    <div class="tab-pane @if(session()->get('step') == 2) active @endif" id="kt_portlet_base_demo_3_2_tab_content" role="tabpanel">
                        @include('admin.claims.claims_cellphone')
                    </div>
                    <div class="tab-pane @if(session()->get('step') == 6) active @endif" id="kt_portlet_base_demo_3_6_tab_content" role="tabpanel">
                        @include('admin.claims.claims_cellphone_assessor')
                    </div>
                    <div class="tab-pane @if(session()->get('step') == 7) active @endif" id="kt_portlet_base_demo_3_7_tab_content" role="tabpanel">
                        @include('admin.claims.claims_cellphone_repaircenter')
                    </div>
                @endif
                @if($claims->claim_type == 'BUSINESSALLRISKS' || $claims->claim_type == 'ELECTRONICEQUIPMENT' || $claims->claim_type == 'PERSONALALLRISKS' || $claims->claim_type == 'BUSINESSINTERRUPTION' ||  $claims->claim_type == 'THEFT' || $claims->claim_type == 'MONEY' || $claims->claim_type == 'WORKERSCOMPENSATION' || $claims->claim_type == 'STATEDBENEFITS' || $claims->claim_type == 'FIDELITYGUARANTEE' || $claims->claim_type == 'TRAVELINSURANCE' || $claims->claim_type == 'GOODSINTRANSIT' || $claims->claim_type == 'FIRE' || $claims->claim_type == 'LIABILITY'  || $claims->claim_type == 'PROPERTYDAMAGE' || $claims->claim_type == 'ACCIDENTALDAMAGE' || $claims->claim_type == 'DEFECTIVEWORKMANSHIP' || $claims->claim_type == 'MOBILEELECTRONICDEVICES' || $claims->claim_type == 'OFFICECONTENTS')
                    <div class="tab-pane @if(session()->get('step') == 5) active @endif" id="kt_portlet_base_demo_3_5_tab_content" role="tabpanel">
                        @include('admin.claims.newClaims.claim_details')
                    </div>
                    <div class="tab-pane @if(session()->get('step') == 6) active @endif" id="kt_portlet_base_demo_3_6_tab_content" role="tabpanel">
                        @include('admin.claims.accident')
                    </div>
                    <div class="tab-pane @if(session()->get('step') == 7) active @endif" id="kt_portlet_base_demo_3_7_tab_content" role="tabpanel">
                        @include('admin.claims.accident_supplier')
                    </div>
                @endif
                @if($claims->claim_type == 'Glass' && ($policy->product_id == 7 || $policy->product_id == 8))
                    <div class="tab-pane @if(session()->get('step') == 5) active @endif" id="kt_portlet_base_demo_3_5_tab_content" role="tabpanel">
                        @include('admin.claims.newClaims.claim_details')
                    </div>
                    <div class="tab-pane @if(session()->get('step') == 6) active @endif" id="kt_portlet_base_demo_3_6_tab_content" role="tabpanel">
                        @include('admin.claims.accident')
                    </div>
                    <div class="tab-pane @if(session()->get('step') == 7) active @endif" id="kt_portlet_base_demo_3_7_tab_content" role="tabpanel">
                        @include('admin.claims.accident_supplier')
                    </div>
                @endif
                @if($claims->claim_type == 'Key Loss' && ($policy->product_id == 7 || $policy->product_id == 8))
                    <div class="tab-pane @if(session()->get('step') == 5) active @endif" id="kt_portlet_base_demo_3_5_tab_content" role="tabpanel">
                        @include('admin.claims.newClaims.claim_details')
                    </div>
                    <div class="tab-pane @if(session()->get('step') == 6) active @endif" id="kt_portlet_base_demo_3_6_tab_content" role="tabpanel">
                        @include('admin.claims.accident')
                    </div>
                    <div class="tab-pane @if(session()->get('step') == 7) active @endif" id="kt_portlet_base_demo_3_7_tab_content" role="tabpanel">
                        @include('admin.claims.accident_supplier')
                    </div>
                @endif
                @if(($claims->claim_type == 'Accident' || $claims->claim_type == 'MOTORTRADERSEXTERNAL' || $claims->claim_type == 'MOTORTRADERSINTERNAL') && ($policy->product_id == 7 || $policy->product_id == 8))
                    <div class="tab-pane @if(session()->get('step') == 5) active @endif" id="kt_portlet_base_demo_3_5_tab_content" role="tabpanel">
                        @include('admin.claims.newClaims.claim_details')
                    </div>
                    <div class="tab-pane @if(session()->get('step') == 6) active @endif" id="kt_portlet_base_demo_3_6_tab_content" role="tabpanel">
                        @include('admin.claims.accident')
                    </div>
                    <div class="tab-pane @if(session()->get('step') == 7) active @endif" id="kt_portlet_base_demo_3_7_tab_content" role="tabpanel">
                        @include('admin.claims.accident_supplier')
                    </div>
                @endif
                {{-- @if($claims->claim_type == 'THEFT')
                    <div class="tab-pane @if(session()->get('step') == 5) active @endif" id="kt_portlet_base_demo_3_5_tab_content" role="tabpanel">
                        @include('admin.claims.newClaims.claim_details')
                    </div>
                    <div class="tab-pane @if(session()->get('step') == 6) active @endif" id="kt_portlet_base_demo_3_6_tab_content" role="tabpanel">
                        @include('admin.claims.accident')
                    </div>
                    <div class="tab-pane @if(session()->get('step') == 7) active @endif" id="kt_portlet_base_demo_3_7_tab_content" role="tabpanel">
                        @include('admin.claims.accident_supplier')
                    </div>
                @endif
                @if($claims->claim_type == 'BUSINESSINTERRUPTION')
                    <div class="tab-pane @if(session()->get('step') == 5) active @endif" id="kt_portlet_base_demo_3_5_tab_content" role="tabpanel">
                        @include('admin.claims.newClaims.claim_details')
                    </div>
                    <div class="tab-pane @if(session()->get('step') == 6) active @endif" id="kt_portlet_base_demo_3_6_tab_content" role="tabpanel">
                        @include('admin.claims.accident')
                    </div>
                    <div class="tab-pane @if(session()->get('step') == 7) active @endif" id="kt_portlet_base_demo_3_7_tab_content" role="tabpanel">
                        @include('admin.claims.accident_supplier')
                    </div>
                @endif
                @if($claims->claim_type == 'WORKERSCOMPENSATION' || $claims->claim_type == 'STATEDBENEFITS')
                    <div class="tab-pane @if(session()->get('step') == 5) active @endif" id="kt_portlet_base_demo_3_5_tab_content" role="tabpanel">
                        @include('admin.claims.newClaims.claim_details')
                    </div>
                    <div class="tab-pane @if(session()->get('step') == 6) active @endif" id="kt_portlet_base_demo_3_6_tab_content" role="tabpanel">
                        @include('admin.claims.accident')
                    </div>
                    <div class="tab-pane @if(session()->get('step') == 7) active @endif" id="kt_portlet_base_demo_3_7_tab_content" role="tabpanel">
                        @include('admin.claims.accident_supplier')
                    </div>
                @endif
                @if($claims->claim_type == 'PUBLICLIABILITY')
                    <div class="tab-pane @if(session()->get('step') == 5) active @endif" id="kt_portlet_base_demo_3_5_tab_content" role="tabpanel">
                        @include('admin.claims.newClaims.claim_details')
                    </div>
                    <div class="tab-pane @if(session()->get('step') == 6) active @endif" id="kt_portlet_base_demo_3_6_tab_content" role="tabpanel">
                        @include('admin.claims.accident')
                    </div>
                    <div class="tab-pane @if(session()->get('step') == 7) active @endif" id="kt_portlet_base_demo_3_7_tab_content" role="tabpanel">
                        @include('admin.claims.accident_supplier')
                    </div>
                @endif

                @if($claims->claim_type == 'PROPERTYLOSSDAMAGE')
                    <div class="tab-pane @if(session()->get('step') == 5) active @endif" id="kt_portlet_base_demo_3_5_tab_content" role="tabpanel">
                        @include('admin.claims.newClaims.claim_details')
                    </div>
                    <div class="tab-pane @if(session()->get('step') == 6) active @endif" id="kt_portlet_base_demo_3_6_tab_content" role="tabpanel">
                        @include('admin.claims.accident')
                    </div>
                    <div class="tab-pane @if(session()->get('step') == 7) active @endif" id="kt_portlet_base_demo_3_7_tab_content" role="tabpanel">
                        @include('admin.claims.accident_supplier')
                    </div>
                @endif
                @if($claims->claim_type == 'FIDELITYGUARANTEE')
                    <div class="tab-pane @if(session()->get('step') == 5) active @endif" id="kt_portlet_base_demo_3_5_tab_content" role="tabpanel">
                        @include('admin.claims.newClaims.claim_details')
                    </div>
                    <div class="tab-pane @if(session()->get('step') == 6) active @endif" id="kt_portlet_base_demo_3_6_tab_content" role="tabpanel">
                        @include('admin.claims.accident')
                    </div>
                    <div class="tab-pane @if(session()->get('step') == 7) active @endif" id="kt_portlet_base_demo_3_7_tab_content" role="tabpanel">
                        @include('admin.claims.accident_supplier')
                    </div>
                @endif
                @if($claims->claim_type == 'TRAVELINSURANCE')
                    <div class="tab-pane @if(session()->get('step') == 5) active @endif" id="kt_portlet_base_demo_3_5_tab_content" role="tabpanel">
                        @include('admin.claims.newClaims.claim_details')
                    </div>
                    <div class="tab-pane @if(session()->get('step') == 6) active @endif" id="kt_portlet_base_demo_3_6_tab_content" role="tabpanel">
                        @include('admin.claims.accident')
                    </div>
                    <div class="tab-pane @if(session()->get('step') == 7) active @endif" id="kt_portlet_base_demo_3_7_tab_content" role="tabpanel">
                        @include('admin.claims.accident_supplier')
                    </div>
                @endif --}}
                <div class="tab-pane @if(session()->get('step') == 10) active @endif" id="kt_portlet_base_demo_3_10_tab_content" role="tabpanel">
                    @include('admin.claims.reserves')
                </div>
                <div class="tab-pane @if(session()->get('step') == 8) active @endif" id="kt_portlet_base_demo_3_8_tab_content" role="tabpanel">
                    @include('admin.claims.attachment')
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
                                        <table class="table table-striped table-bordered table-hover table-checkable" id="region_table">
                        <thead>
                            <tr>

                        <th>Id</th>
                        <th>Activity By</th>
                        <th>IP address</th>
                        <th>Activity Tag</th>
                        <th>Old Values</th>
                        <th>New data</th>
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


                <div class="tab-pane" id="kt_portlet_base_demo_3_11_tab_content" role="tabpanel">
                    @if($claims->claim_type == 'BUSINESSINTERRUPTION' || $claims->claim_type == 'BUSINESSALLRISKS' || $claims->claim_type == 'ELECTRONICEQUIPMENT' || $claims->claim_type == 'PERSONALALLRISKS' || $claims->claim_type == 'THEFT' || $claims->claim_type == 'MONEY' || $claims->claim_type == 'WORKERSCOMPENSATION' || $claims->claim_type == 'STATEDBENEFITS' || $claims->claim_type == 'FIDELITYGUARANTEE' || $claims->claim_type == 'TRAVELINSURANCE' || $claims->claim_type == 'GOODSINTRANSIT' || $claims->claim_type == 'FIRE' || $claims->claim_type == 'LIABILITY'  || $claims->claim_type == 'PROPERTYDAMAGE' || $claims->claim_type == 'ACCIDENTALDAMAGE' || $claims->claim_type == 'DEFECTIVEWORKMANSHIP' || $claims->claim_type == 'MOBILEELECTRONICDEVICES' || $claims->claim_type == 'OFFICECONTENTS')
                        @include('admin.claims.newClaims.complaintLog')
                    @elseif($claims->claim_type == 'Glass' && ($policy->product_id == 7 || $policy->product_id == 8))
                        @include('admin.claims.newClaims.complaintLog')
                    @elseif($claims->claim_type == 'Key Loss' && ($policy->product_id == 7 || $policy->product_id == 8))
                        @include('admin.claims.newClaims.complaintLog')
                    @elseif (($claims->claim_type == 'Accident' || $claims->claim_type == 'MOTORTRADERSEXTERNAL' || $claims->claim_type == 'MOTORTRADERSINTERNAL') && ($policy->product_id == 7 || $policy->product_id == 8))
                        @include('admin.claims.newClaims.complaintLog')
                    @endif
                </div>


                <div class="tab-pane @if(session()->get('step') == 2) active @endif" id="kt_portlet_base_demo_3_2_tab_content" role="tabpanel">
                    @if($claims->claim_type == 'Glass' && ($policy->product_id != 7 && $policy->product_id != 8))
                        @include('admin.claims.vehicle')
                    @endif
                    @if($claims->claim_type == 'Key Loss' && ($policy->product_id != 7 && $policy->product_id != 8))
                        @include('admin.claims.key_loss')
                    @endif
                    @if($claims->claim_type == 'Cellphone')
                        @include('admin.claims.claims_cellphone')
                    @endif
                    @if($claims->claim_type == 'Life')
                        @include('admin.claims.life')
                    @endif
                    @if($claims->claim_type == 'Legal')
                        @include('admin.claims.legal')
                    @endif
                    @if(trim($claims->claim_type) == 'Hospital CashBack' || trim($claims->claim_type) == 'Hospital Cash')
                        @include('admin.claims.hospital_cash_edit')
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
                            <div class="form-group row" id="poDiv">
                                <div class="col-md-12">
                                    <div class="kt-checkbox-inline">
                                        <form id="POUpdate"  action="{{ url('admin/claims/poUpload/'.$claims->id) }}" method="POST" enctype="multipart/form-data" class="kt-form">

                                            <!-- CSRF Token -->
                                            <input type="hidden" name="_token" value="{{ csrf_token() }}" />

                                            <div class="kt-portlet__body">
                                                <div class="kt-widget-4">
                                                    <div class="form-group row">
                                                        <input class="form-control" type="hidden" name="policy_id" value="{!! $policy->id !!}">
                                                        <input id="product_id" class="form-control" type="hidden" name="product_id" value="{!! $product->id !!}">
                                                        <label for="example-text-input" class="col-3 col-form-label">Upload PO</label>
                                                        <div class="col-2">
                                                            <div class="kt-avatar" id="po" style="float: left; clear: left;">
                                                                @if($claims->po == NULL)
                                                                    <div class="kt-avatar__holder" style="background-image:url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                                                                @else
                                                                    <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($claims->po) !!}" target="_blank" download>
                                                                        @if(pathinfo($claims->po, PATHINFO_EXTENSION) == 'pdf')
                                                                            <img src="{{asset('images/pdf.ico')}}" width="100%" height="auto" >
                                                                        @elseif(pathinfo($claims->po, PATHINFO_EXTENSION) == 'docx' || pathinfo($claims->po, PATHINFO_EXTENSION) == 'doc' || pathinfo($claims->po, PATHINFO_EXTENSION) == 'docm')
                                                                            <img src="{{asset('images/word.ico')}}" width="100%" height="auto" >
                                                                        @elseif(pathinfo($claims->po, PATHINFO_EXTENSION) == 'xls' || pathinfo($claims->po, PATHINFO_EXTENSION) == 'xlsx' || pathinfo($claims->po, PATHINFO_EXTENSION) == 'csv')
                                                                            <img src="{{asset('images/excel.png')}}" width="100%" height="auto" >
                                                                        @elseif(pathinfo($claims->po, PATHINFO_EXTENSION) == 'jpeg' || pathinfo($claims->po, PATHINFO_EXTENSION) == 'jpg' || pathinfo($claims->po, PATHINFO_EXTENSION) == 'png')
                                                                            <img src="{{\AlphaDirect\Helper::getCloudFrontURL($claims->po)}}" width="100%" height="auto" >
                                                                        @else
                                                                            <img src="{{asset('images/doc.png')}}" width="100%" height="auto" >
                                                                        @endif
                                                                    </a>
                                                                @endif
                                                                <label class="kt-avatar__upload" data-toggle="kt-tooltip" title="Change Image">
                                                                    <i class="fa fa-pen"></i>
                                                                    <input type='file'  name="po" id="POimage" <?php echo config('app.accept_attr'); ?> <?php echo config('app.accept_msg'); ?>/>
                                                                </label>

                                                                <span class="kt-avatar__cancel" data-toggle="kt-tooltip" title="Cancel Image"> <i class="fa fa-times"></i> </span>
                                                                <span class="form-group notiMsg" style="color:red;display:none;">Please upload a file.</span>
                                                            </div>

                                                        </div>
                                                    </div>


                                                </div>
                                            </div>
                                            <div class="kt-portlet__foot kt-portlet__foot--solid">
                                                <div class="kt-form__actions">
                                                    <div class="row">
                                                        <div class="col-3"></div>
                                                        <div class="col-9" style="margin-left: 300px">
                                                            <button class="btn btn-brand" type="submit" id="PosbtBtn">Submit</button>
                                                            <button class="btn btn-brand" type="button" id="PoloadBtn" style="display:none" > <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Loading... </button>
                                                            <a class="btn btn-secondary cancel" href="{{ route('admin.claims.index') }}" >Cancel</a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
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
                                        @if(!empty($supplierQuotes))
                                            @foreach($supplierQuotes as $key => $quote)
                                                <tr>
                                                    <th scope="row">{!! $key+1 !!}</th>
                                                    @if($claims->claim_type == 'Cellphone')
                                                        <td>{!! $quote->repair_center->supplierName !!}</td>
                                                    @else
                                                        <td>{!! $quote->supplier->supplierName !!}</td>
                                                    @endif
                                                    <td>@if($quote->total != NULL)P {!! $quote->total !!} @else - @endif</td>
                                                    <td style="width: 40%;">
                                                        @if($quote->total == NULL)
                                                            <span class="kt-font-bold kt-font-danger">Not Received</span>
                                                        @elseif($quote->total != NULL && $quote->status == 0)
                                                            <span class="kt-font-bold kt-font-brand">Quote Received</span>
                                                        @else
                                                            <span class="kt-font-bold kt-font-success">PO SENT</span><br>@if($lowestQuote && $lowestQuote->id != $quote->id && $claims->po != NULL)<p><b>Reason : </b>{!! $quote->select_reason !!}</p>@endif
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
                @if($claims->claim_type == 'Accident' || $claims->claim_type == 'MOTORTRADERSEXTERNAL' || $claims->claim_type == 'MOTORTRADERSINTERNAL' || $claims->claim_type == 'BUSINESSINTERRUPTION' || $claims->claim_type == 'BUSINESSALLRISKS' || $claims->claim_type == 'ELECTRONICEQUIPMENT' || $claims->claim_type == 'PERSONALALLRISKS' || $claims->claim_type == 'THEFT' || $claims->claim_type == 'MONEY' || $claims->claim_type == 'WORKERSCOMPENSATION' || $claims->claim_type == 'STATEDBENEFITS' || $claims->claim_type == 'FIDELITYGUARANTEE' || $claims->claim_type == 'TRAVELINSURANCE' || $claims->claim_type == 'GOODSINTRANSIT' || $claims->claim_type == 'FIRE' || $claims->claim_type == 'LIABILITY'  || $claims->claim_type == 'PROPERTYDAMAGE' || $claims->claim_type == 'ACCIDENTALDAMAGE' || $claims->claim_type == 'DEFECTIVEWORKMANSHIP' || $claims->claim_type == 'MOBILEELECTRONICDEVICES' || $claims->claim_type == 'OFFICECONTENTS')
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
                                            @if(!empty($supplierQuotes))
                                                @foreach($supplierQuotes as $key => $quote)
                                                    <tr>
                                                        <th scope="row">{!! $key+1 !!}</th>
                                                        @if($claims->claim_type == 'Cellphone')
                                                            <td>{!! $quote->repair_center->supplierName !!}</td>
                                                        @else
                                                            <td>{!! $quote->supplier->supplierName !!}</td>
                                                        @endif
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
                                                            <td>
                                                                @if($quote->total != NULL)
                                                                    <a href="{!! $quote->id !!}" class="btn btn-brand resentInvoiceRequest">Resend Invoice Request</a>
                                                                @endif
                                                            </td>
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
                                        <input class="form-control" type="hidden" name="policy_id" value="{!! $policy->id !!}">
                                        <label for="example-text-input" class="col-3 col-form-label">Upload Invoice</label>
                                        <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($claims->invoice) !!}" target="_blank" download>
                                        <div class="kt-avatar">
                                            <div class="kt-avatar__holder"
                                                @if(pathinfo($claims->invoice, PATHINFO_EXTENSION) == 'pdf')
                                                    style="background-image:url({{asset('images/pdf.ico')}})"
                                                @elseif(pathinfo($claims->invoice, PATHINFO_EXTENSION) == 'docx' || pathinfo($claims->invoice, PATHINFO_EXTENSION) == 'doc' || pathinfo($claims->invoice, PATHINFO_EXTENSION) == 'docm')
                                                    style="background-image:url({{asset('images/word.ico')}})"
                                                @elseif(pathinfo($claims->invoice, PATHINFO_EXTENSION) == 'xls' || pathinfo($claims->invoice, PATHINFO_EXTENSION) == 'xlsx' || pathinfo($claims->invoice, PATHINFO_EXTENSION) == 'csv')
                                                    style="background-image:url({{asset('images/excel.png')}})"
                                                @elseif(pathinfo($claims->invoice, PATHINFO_EXTENSION) == 'jpeg' || pathinfo($claims->invoice, PATHINFO_EXTENSION) == 'jpg' || pathinfo($claims->invoice, PATHINFO_EXTENSION) == 'png')
                                                    style="background-image:url({{\AlphaDirect\Helper::getCloudFrontURL($claims->invoice)}})"
                                                @else
                                                    style="background-image:url({{asset('images/doc.png')}})"
                                                @endif style="background-image:url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)">
                                            </div>
                                            @if($claims->status == 'Pending' || $claims->status == 'Reopen')
                                                <label class="kt-avatar__upload" data-toggle="kt-tooltip" title="Select Invoice">
                                                    <i class="fa fa-pen"></i>
                                                    <input type='file' name="invoice" <?php echo config('app.accept_attr'); ?> <?php echo config('app.accept_msg'); ?>/>
                                                </label>
                                            @endif
                                            <span class="kt-avatar__cancel" data-toggle="kt-tooltip" title="Remove Invoice"> <i class="fa fa-times"></i> </span>
                                            <span class="form-group notiMsg" style="color:red;display:none;">Please upload a file.</span>
                                        </div>

                                    </a>
                                    </div>
                                    <div class="form-group row">
                                        <label for="example-text-input" class="col-3 col-form-label">Note</label>
                                        <div class="col-7">
                                        @if($claims->status == 'Pending' || $claims->status == 'Reopen')
                                            <textarea class="form-control" name="note" placeholder="Add Notes" id="description" rows="3" required>{!! $claims->note !!}</textarea>
                                        @else
                                            <p>{!! $claims->note !!}</p>
                                        @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @if($claims->claim_type == 'Cellphone')
                                <label class="col-3 col-form-label"><h4>After Repair Images</h4></label>
                                <table class="table table-striped m-table">
                                    <tbody>

                                    <tr>
                                        <th> Front Image</th>
                                        <td>
                                            <div class="col-md-4">
                                                <div class="kt-avatar" id="" style="float: left; clear: left;">
                                                    @if($claimCellphone != null && $claimCellphone->after_repair_front == NULL)
                                                        <div class="kt-avatar__holder" style="background-image: url(http://placehold.it/200x200)"></div>
                                                        @if($claims->status == 'Pending')
                                                            <label class="kt-avatar__upload" data-toggle="kt-tooltip" title="Change Image">
                                                                <i class="fa fa-pen"></i>
                                                                <input type='file' class="" name="after_repair_front"
                                                                <?php echo config('app.accept_attr'); ?>
                                                                    <?php echo config('app.accept_msg'); ?> />
                                                            </label>
                                                            <span class="kt-avatar__cancel" data-toggle="kt-tooltip" title="Cancel Image">
                                                                <i class="fa fa-times"></i>
                                                            </span>
                                                        @endif
                                                    @else
                                                        <a href="{!! Storage::disk('s3')->url($claimCellphone->after_repair_front) !!}" target="_blank" download>
                                                            @if(pathinfo($claimCellphone->after_repair_front,PATHINFO_EXTENSION) == 'pdf')
                                                                <img src="{{asset('images/pdf.ico')}}" width="100%" height="auto">
                                                            @elseif(pathinfo($claimCellphone->after_repair_front,
                                                            PATHINFO_EXTENSION) == 'docx' ||
                                                            pathinfo($claimCellphone->after_repair_front, PATHINFO_EXTENSION)
                                                            == 'doc' || pathinfo($claimCellphone->after_repair_front,PATHINFO_EXTENSION) == 'docm')
                                                                <img src="{{asset('images/word.ico')}}" width="100%" height="auto">
                                                            @elseif(pathinfo($claimCellphone->after_repair_front,
                                                            PATHINFO_EXTENSION) == 'xls' ||
                                                            pathinfo($claimCellphone->after_repair_front, PATHINFO_EXTENSION)
                                                            == 'xlsx' || pathinfo($claimCellphone->after_repair_front,PATHINFO_EXTENSION) == 'csv')
                                                                <img src="{{asset('images/excel.png')}}" width="100%" height="auto">
                                                            @elseif(pathinfo($claimCellphone->after_repair_front,
                                                            PATHINFO_EXTENSION) == 'jpeg' || pathinfo($claimCellphone->after_repair_front,
                                                            PATHINFO_EXTENSION) == 'JPEG' ||
                                                            pathinfo($claimCellphone->after_repair_front, PATHINFO_EXTENSION)
                                                            == 'jpg' || pathinfo($claimCellphone->after_repair_front,
                                                            PATHINFO_EXTENSION) == 'png')
                                                                <img src="{{str_replace(env('AWS_URL'),env('AWS_CLOUDFRONT'),Storage::disk('s3')->url($claimCellphone->after_repair_front))}}" width="100px" height="auto">
                                                            @else
                                                                <img src="{{asset('images/doc.png')}}" width="100px" height="auto">
                                                            @endif
                                                        </a>
                                                    @endif
                                                </div>
                                            </div>
                                        </td>
                                        <th> Back Image</th>
                                        <td>
                                            <div class="col-md-4">
                                                <div class="kt-avatar" id="" style="float: left; clear: left;">
                                                    @if($claimCellphone != null && $claimCellphone->after_repair_back == NULL)
                                                        <div class="kt-avatar__holder" style="background-image: url(http://placehold.it/200x200)"></div>
                                                        @if($claims->status == 'Pending')
                                                            <label class="kt-avatar__upload" data-toggle="kt-tooltip" title="Change Image">
                                                                <i class="fa fa-pen"></i>
                                                                <input type='file' class="" name="after_repair_back"
                                                                <?php echo config('app.accept_attr'); ?>
                                                                    <?php echo config('app.accept_msg'); ?> />
                                                            </label>
                                                            <span class="kt-avatar__cancel" data-toggle="kt-tooltip" title="Cancel Image">
                                                                <i class="fa fa-times"></i>
                                                            </span>
                                                        @endif
                                                    @else
                                                        <a href="{!! Storage::disk('s3')->url($claimCellphone->after_repair_back) !!}" target="_blank" download>
                                                            @if(pathinfo($claimCellphone->after_repair_back,PATHINFO_EXTENSION) == 'pdf')
                                                                <img src="{{asset('images/pdf.ico')}}" width="100%" height="auto">
                                                            @elseif(pathinfo($claimCellphone->after_repair_back,
                                                            PATHINFO_EXTENSION) == 'docx' ||
                                                            pathinfo($claimCellphone->after_repair_back, PATHINFO_EXTENSION)
                                                            == 'doc' || pathinfo($claimCellphone->after_repair_back,PATHINFO_EXTENSION) == 'docm')
                                                                <img src="{{asset('images/word.ico')}}" width="100%" height="auto">
                                                            @elseif(pathinfo($claimCellphone->after_repair_back,
                                                            PATHINFO_EXTENSION) == 'xls' ||
                                                            pathinfo($claimCellphone->after_repair_back, PATHINFO_EXTENSION)
                                                            == 'xlsx' || pathinfo($claimCellphone->after_repair_back,PATHINFO_EXTENSION) == 'csv')
                                                                <img src="{{asset('images/excel.png')}}" width="100%" height="auto">
                                                            @elseif(pathinfo($claimCellphone->after_repair_back,
                                                            PATHINFO_EXTENSION) == 'jpeg' || pathinfo($claimCellphone->after_repair_back,
                                                            PATHINFO_EXTENSION) == 'JPEG' ||
                                                            pathinfo($claimCellphone->after_repair_back, PATHINFO_EXTENSION)
                                                            == 'jpg' || pathinfo($claimCellphone->after_repair_back,
                                                            PATHINFO_EXTENSION) == 'png')
                                                                <img src="{{str_replace(env('AWS_URL'),env('AWS_CLOUDFRONT'),Storage::disk('s3')->url($claimCellphone->after_repair_back))}}" width="100px" height="auto">
                                                            @else
                                                                <img src="{{asset('images/doc.png')}}" width="100px" height="auto">
                                                            @endif
                                                        </a>
                                                    @endif
                                                </div>
                                            </div>
                                        </td>

                                        <th> Left Image</th>
                                        <td>
                                            <div class="col-md-4">
                                                <div class="kt-avatar" id="" style="float: left; clear: left;">
                                                    @if($claimCellphone != null && $claimCellphone->after_repair_left == NULL)
                                                        <div class="kt-avatar__holder" style="background-image: url(http://placehold.it/200x200)"></div>
                                                        @if($claims->status == 'Pending')
                                                            <label class="kt-avatar__upload" data-toggle="kt-tooltip" title="Change Image">
                                                                <i class="fa fa-pen"></i>
                                                                <input type='file' class="" name="after_repair_left"
                                                                <?php echo config('app.accept_attr'); ?>
                                                                    <?php echo config('app.accept_msg'); ?> />
                                                            </label>
                                                            <span class="kt-avatar__cancel" data-toggle="kt-tooltip" title="Cancel Image">
                                                                <i class="fa fa-times"></i>
                                                            </span>
                                                        @endif
                                                    @else
                                                        <a href="{!! Storage::disk('s3')->url($claimCellphone->after_repair_left) !!}" target="_blank" download>
                                                            @if(pathinfo($claimCellphone->after_repair_left,PATHINFO_EXTENSION) == 'pdf')
                                                                <img src="{{asset('images/pdf.ico')}}" width="100%" height="auto">
                                                            @elseif(pathinfo($claimCellphone->after_repair_left,
                                                            PATHINFO_EXTENSION) == 'docx' ||
                                                            pathinfo($claimCellphone->after_repair_left, PATHINFO_EXTENSION)
                                                            == 'doc' || pathinfo($claimCellphone->after_repair_left,PATHINFO_EXTENSION) == 'docm')
                                                                <img src="{{asset('images/word.ico')}}" width="100%" height="auto">
                                                            @elseif(pathinfo($claimCellphone->after_repair_left,
                                                            PATHINFO_EXTENSION) == 'xls' ||
                                                            pathinfo($claimCellphone->after_repair_left, PATHINFO_EXTENSION)
                                                            == 'xlsx' || pathinfo($claimCellphone->after_repair_left,PATHINFO_EXTENSION) == 'csv')
                                                                <img src="{{asset('images/excel.png')}}" width="100%" height="auto">
                                                            @elseif(pathinfo($claimCellphone->after_repair_left,
                                                            PATHINFO_EXTENSION) == 'jpeg' || pathinfo($claimCellphone->after_repair_left,
                                                            PATHINFO_EXTENSION) == 'JPEG' ||
                                                            pathinfo($claimCellphone->after_repair_left, PATHINFO_EXTENSION)
                                                            == 'jpg' || pathinfo($claimCellphone->after_repair_left,
                                                            PATHINFO_EXTENSION) == 'png')
                                                                <img src="{{str_replace(env('AWS_URL'),env('AWS_CLOUDFRONT'),Storage::disk('s3')->url($claimCellphone->after_repair_left))}}" width="100px" height="auto">
                                                            @else
                                                                <img src="{{asset('images/doc.png')}}" width="100px" height="auto">
                                                            @endif
                                                        </a>
                                                    @endif
                                                </div>
                                            </div>
                                        </td>
                                    </tr>

                                    <tr>
                                        <th> Right Image</th>
                                        <td>
                                            <div class="col-md-4">
                                                <div class="kt-avatar" id="" style="float: left; clear: left;">
                                                    @if($claimCellphone != null && $claimCellphone->after_repair_right == NULL)
                                                        <div class="kt-avatar__holder" id="" style="background-image: url(http://placehold.it/200x200)"></div>
                                                        @if($claims->status == 'Pending')
                                                            <label class="kt-avatar__upload" data-toggle="kt-tooltip" title="Change Image">
                                                                <i class="fa fa-pen"></i>
                                                                <input type='file' class="" name="after_repair_right"
                                                                <?php echo config('app.accept_attr'); ?>
                                                                    <?php echo config('app.accept_msg'); ?> />
                                                            </label>
                                                            <span class="kt-avatar__cancel" data-toggle="kt-tooltip" title="Cancel Image">
                                                                <i class="fa fa-times"></i>
                                                            </span>
                                                        @endif
                                                    @else
                                                        <a href="{!! Storage::disk('s3')->url($claimCellphone->after_repair_right) !!}" target="_blank" download>
                                                            @if(pathinfo($claimCellphone->after_repair_right,PATHINFO_EXTENSION) == 'pdf')
                                                                <img src="{{asset('images/pdf.ico')}}" width="100%" height="auto">
                                                            @elseif(pathinfo($claimCellphone->after_repair_right,
                                                            PATHINFO_EXTENSION) == 'docx' ||
                                                            pathinfo($claimCellphone->after_repair_right, PATHINFO_EXTENSION)
                                                            == 'doc' || pathinfo($claimCellphone->after_repair_right,PATHINFO_EXTENSION) == 'docm')
                                                                <img src="{{asset('images/word.ico')}}" width="100%" height="auto">
                                                            @elseif(pathinfo($claimCellphone->after_repair_right,
                                                            PATHINFO_EXTENSION) == 'xls' ||
                                                            pathinfo($claimCellphone->after_repair_right, PATHINFO_EXTENSION)
                                                            == 'xlsx' || pathinfo($claimCellphone->after_repair_right,PATHINFO_EXTENSION) == 'csv')
                                                                <img src="{{asset('images/excel.png')}}" width="100%" height="auto">
                                                            @elseif(pathinfo($claimCellphone->after_repair_right,
                                                            PATHINFO_EXTENSION) == 'jpeg' || pathinfo($claimCellphone->after_repair_right,
                                                            PATHINFO_EXTENSION) == 'JPEG' ||
                                                            pathinfo($claimCellphone->after_repair_right, PATHINFO_EXTENSION)
                                                            == 'jpg' || pathinfo($claimCellphone->after_repair_right,
                                                            PATHINFO_EXTENSION) == 'png')
                                                                <img src="{{str_replace(env('AWS_URL'),env('AWS_CLOUDFRONT'),Storage::disk('s3')->url($claimCellphone->after_repair_right))}}" width="100px" height="auto">
                                                            @else
                                                                <img src="{{asset('images/doc.png')}}" width="100px" height="auto">
                                                            @endif
                                                        </a>
                                                    @endif
                                                </div>
                                            </div>
                                        </td>
                                        <th> Top Image</th>
                                        <td>
                                            <div class="col-md-4">
                                                <div class="kt-avatar" id="" style="float: left; clear: left;">
                                                    @if($claimCellphone != null && $claimCellphone->after_repair_top == NULL)
                                                        <div class="kt-avatar__holder" style="background-image: url(http://placehold.it/200x200)"></div>
                                                        @if($claims->status == 'Pending')
                                                            <label class="kt-avatar__upload" data-toggle="kt-tooltip" title="Change Image">
                                                                <i class="fa fa-pen"></i>
                                                                <input type='file' class="" name="after_repair_top"
                                                                <?php echo config('app.accept_attr'); ?>
                                                                    <?php echo config('app.accept_msg'); ?> />
                                                            </label>
                                                            <span class="kt-avatar__cancel" data-toggle="kt-tooltip" title="Cancel Image">
                                                                <i class="fa fa-times"></i>
                                                            </span>
                                                        @endif
                                                    @else
                                                        <a href="{!! Storage::disk('s3')->url($claimCellphone->after_repair_top) !!}" target="_blank" download>
                                                            @if(pathinfo($claimCellphone->after_repair_top,PATHINFO_EXTENSION) == 'pdf')
                                                                <img src="{{asset('images/pdf.ico')}}" width="100%" height="auto">
                                                            @elseif(pathinfo($claimCellphone->after_repair_top,
                                                            PATHINFO_EXTENSION) == 'docx' ||
                                                            pathinfo($claimCellphone->after_repair_top, PATHINFO_EXTENSION)
                                                            == 'doc' || pathinfo($claimCellphone->after_repair_top,PATHINFO_EXTENSION) == 'docm')
                                                                <img src="{{asset('images/word.ico')}}" width="100%" height="auto">
                                                            @elseif(pathinfo($claimCellphone->after_repair_top,
                                                            PATHINFO_EXTENSION) == 'xls' ||
                                                            pathinfo($claimCellphone->after_repair_top, PATHINFO_EXTENSION)
                                                            == 'xlsx' || pathinfo($claimCellphone->after_repair_top,PATHINFO_EXTENSION) == 'csv')
                                                                <img src="{{asset('images/excel.png')}}" width="100%" height="auto">
                                                            @elseif(pathinfo($claimCellphone->after_repair_top,
                                                            PATHINFO_EXTENSION) == 'jpeg' || pathinfo($claimCellphone->after_repair_top,
                                                            PATHINFO_EXTENSION) == 'JPEG' ||
                                                            pathinfo($claimCellphone->after_repair_top, PATHINFO_EXTENSION)
                                                            == 'jpg' || pathinfo($claimCellphone->after_repair_top,
                                                            PATHINFO_EXTENSION) == 'png')
                                                                <img src="{{str_replace(env('AWS_URL'),env('AWS_CLOUDFRONT'),Storage::disk('s3')->url($claimCellphone->after_repair_top))}}" width="100px" height="auto">
                                                            @else
                                                                <img src="{{asset('images/doc.png')}}" width="100px" height="auto">
                                                            @endif
                                                        </a>
                                                    @endif
                                                </div>
                                            </div>
                                        </td>


                                        <th> Bottom Image</th>

                                        <td>
                                            <div class="col-md-4">
                                                <div class="kt-avatar" id="" style="float: left; clear: left;">
                                                    @if($claimCellphone != null && $claimCellphone->after_repair_bottom == NULL)
                                                        <div class="kt-avatar__holder" style="background-image: url(http://placehold.it/200x200)"></div>
                                                        @if($claims->status == 'Pending')
                                                            <label class="kt-avatar__upload" data-toggle="kt-tooltip" title="Change Image">
                                                                <i class="fa fa-pen"></i>
                                                                <input type='file' class="" name="after_repair_bottom"
                                                                <?php echo config('app.accept_attr'); ?>
                                                                    <?php echo config('app.accept_msg'); ?> />
                                                            </label>
                                                            <span class="kt-avatar__cancel" data-toggle="kt-tooltip" title="Cancel Image">
                                                                <i class="fa fa-times"></i>
                                                            </span>
                                                        @endif
                                                    @else
                                                        <a href="{!! Storage::disk('s3')->url($claimCellphone->after_repair_bottom) !!}" target="_blank" download>
                                                            @if(pathinfo($claimCellphone->after_repair_bottom,PATHINFO_EXTENSION) == 'pdf')
                                                                <img src="{{asset('images/pdf.ico')}}" width="100%" height="auto">
                                                            @elseif(pathinfo($claimCellphone->after_repair_bottom,
                                                            PATHINFO_EXTENSION) == 'docx' ||
                                                            pathinfo($claimCellphone->after_repair_bottom, PATHINFO_EXTENSION)
                                                            == 'doc' || pathinfo($claimCellphone->after_repair_bottom,PATHINFO_EXTENSION) == 'docm')
                                                                <img src="{{asset('images/word.ico')}}" width="100%" height="auto">
                                                            @elseif(pathinfo($claimCellphone->after_repair_bottom,
                                                            PATHINFO_EXTENSION) == 'xls' ||
                                                            pathinfo($claimCellphone->after_repair_bottom, PATHINFO_EXTENSION)
                                                            == 'xlsx' || pathinfo($claimCellphone->after_repair_bottom,PATHINFO_EXTENSION) == 'csv')
                                                                <img src="{{asset('images/excel.png')}}" width="100%" height="auto">
                                                            @elseif(pathinfo($claimCellphone->after_repair_bottom,
                                                            PATHINFO_EXTENSION) == 'jpeg' || pathinfo($claimCellphone->after_repair_bottom,
                                                            PATHINFO_EXTENSION) == 'JPEG' ||
                                                            pathinfo($claimCellphone->after_repair_bottom, PATHINFO_EXTENSION)
                                                            == 'jpg' || pathinfo($claimCellphone->after_repair_bottom,
                                                            PATHINFO_EXTENSION) == 'png')
                                                                <img src="{{str_replace(env('AWS_URL'),env('AWS_CLOUDFRONT'),Storage::disk('s3')->url($claimCellphone->after_repair_bottom))}}" width="100px" height="auto">
                                                            @else
                                                                <img src="{{asset('images/doc.png')}}" width="100px" height="auto">
                                                            @endif
                                                        </a>
                                                    @endif
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    </tbody>
                                </table>
                            @endif
                            @if($claims->status == 'Pending' || $claims->status == 'Reopen')
                                <div class="kt-portlet__foot kt-portlet__foot--solid">
                                    <div class="kt-form__actions">
                                        <div class="row">
                                            <div class="col-3"></div>
                                            <div class="col-9" style="margin-left: 300px">
                                                <button class="btn btn-brand" type="submit">Submit</button>
                                                <a class="btn btn-secondary" href="{{ route('admin.claims.index') }}" >Cancel</a>

                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif
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
@if($claims->claim_type == 'Accident' || $claims->claim_type == 'MOTORTRADERSEXTERNAL' || $claims->claim_type == 'MOTORTRADERSINTERNAL')
    <div class="modal fade" id="claimStatus" tabindex="-1" role="dialog" aria-labelledby="user_delete_confirm_title" aria-hidden="true">
        <div class="modal-dialog" role="document" style="max-width:700px;">
            <div class="modal-content" id="modal-content" style="width:700px;">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">Claim Sub Status Information</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"> <span aria-hidden="true">&times;</span> </button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-lg-3">
                            <h3 class="col-form-label" style="float: left;">Notice of Demand</h3>
                            <div class="kt-avatar" id="notice_of_demand"style="float: left; clear: left;">
                                @if($claimAccident->notice_of_demand == NULL)
                                <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="100%" height="auto" >
                                @else
                                <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($claimAccident->notice_of_demand) !!}"target="_blank" download>
                                    <div class="kt-avatar__holder" style="background-image:url({!! \AlphaDirect\Helper::getCloudFrontURL($claimAccident->notice_of_demand) !!})"></div>
                                </a>
                                @endif
                            </div>
                        </div>
                        <div class="col-lg-3">
                            <h3 class="col-form-label" style="float: left;">Final Demand Letter</h3>
                            <div class="kt-avatar" id="final_demand_letter"style="float: left; clear: left;">
                                @if($claimAccident->final_demand_letter == NULL)
                                <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="100%" height="auto" >
                                @else
                                <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($claimAccident->final_demand_letter) !!}"target="_blank" download>
                                    <div class="kt-avatar__holder" style="background-image:url({!! \AlphaDirect\Helper::getCloudFrontURL($claimAccident->final_demand_letter) !!})"></div>
                                </a>
                                @endif
                            </div>
                        </div>
                        <div class="col-lg-3">
                            <h3 class="col-form-label" style="float: left;">Debt Acknowledgment form</h3>
                            <div class="kt-avatar" id="debt_acknowledgment"style="float: left; clear: left;">
                                @if($claimAccident->debt_acknowledgment == NULL)
                                <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="100%" height="auto" >
                                @else
                                <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($claimAccident->debt_acknowledgment) !!}"target="_blank" download>
                                    <div class="kt-avatar__holder" style="background-image:url({!! \AlphaDirect\Helper::getCloudFrontURL($claimAccident->debt_acknowledgment) !!})"></div>
                                </a>
                                @endif
                            </div>
                        </div>
                        <div class="col-lg-3">
                            <h3 class="col-form-label" style="float: left;">KYC form</h3>
                            <div class="kt-avatar" id="kyc_form"style="float: left; clear: left;">
                                @if($claimAccident->kyc_form == NULL)
                                <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="100%" height="auto" >
                                @else
                                <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($claimAccident->kyc_form) !!}"target="_blank" download>
                                    <div class="kt-avatar__holder" style="background-image:url({!! \AlphaDirect\Helper::getCloudFrontURL($claimAccident->kyc_form) !!})"></div>
                                </a>
                                @endif
                            </div>
                        </div>
                    </div>
                    <br>
                    {{--cash in lieu--}}
                    @if($claimAccident->claim_sub_status == "Cash In Lieu")
                        <h5>Cash in Lieu Sub Status Details</h5>
                        <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>
                        <div class="kt-portlet_body">
                            <div class="kt-section">
                                <div class="kt-section__content" style="padding-left:20px; padding-right: 20px;">
                                    <table class="table table-striped m-table">
                                        @if($claimAccident->approved_customer == $kyc->customer_id)
                                            <h5>Customer KYC</h5>
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
                                                            @if(pathinfo($kyc->omang, PATHINFO_EXTENSION) == 'pdf')
                                                                <img src="{{asset('images/pdf.ico')}}" width="100%" height="auto" >
                                                            @elseif(pathinfo($kyc->omang, PATHINFO_EXTENSION) == 'docx' || pathinfo($kyc->omang, PATHINFO_EXTENSION) == 'doc' || pathinfo($kyc->omang, PATHINFO_EXTENSION) == 'docm')
                                                                <img src="{{asset('images/word.ico')}}" width="100%" height="auto" >
                                                            @elseif(pathinfo($kyc->omang, PATHINFO_EXTENSION) == 'xls' || pathinfo($kyc->omang, PATHINFO_EXTENSION) == 'xlsx' || pathinfo($kyc->omang, PATHINFO_EXTENSION) == 'csv')
                                                                <img src="{{asset('images/excel.png')}}" width="100%" height="auto" >
                                                            @elseif(pathinfo($kyc->omang, PATHINFO_EXTENSION) == 'jpeg' || pathinfo($kyc->omang, PATHINFO_EXTENSION) == 'jpg' || pathinfo($kyc->omang, PATHINFO_EXTENSION) == 'png')
                                                                <img src="{{\AlphaDirect\Helper::getCloudFrontURL($kyc->omang)}}" width="100%" height="auto" >
                                                            @else
                                                                <img src="{{asset('images/doc.png')}}" width="100%" height="auto" >
                                                            @endif
{{--                                                            <img src="{{\AlphaDirect\Helper::getCloudFrontURL($kyc->omang)}}" width="100%" height="auto" >--}}
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
                                        @endif

                                        {{--other party kyc--}}
                                            @if(!empty($partykyc))
                                                <h5>Other Party KYC</h5>
                                                @foreach($partykyc as $kyc)
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
                                                        @if($kyc->omang_pic == NULL)
                                                            <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="100%" height="auto" >
                                                        @else
                                                            <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($kyc->omang_pic) !!}" target="_blank" download>
                                                                @if(pathinfo($kyc->omang_pic, PATHINFO_EXTENSION) == 'pdf')
                                                                    <img src="{{asset('images/pdf.ico')}}" width="100%" height="auto" >
                                                                @elseif(pathinfo($kyc->omang_pic, PATHINFO_EXTENSION) == 'docx' || pathinfo($kyc->omang_pic, PATHINFO_EXTENSION) == 'doc' || pathinfo($kyc->omang_pic, PATHINFO_EXTENSION) == 'docm')
                                                                    <img src="{{asset('images/word.ico')}}" width="100%" height="auto" >
                                                                @elseif(pathinfo($kyc->omang_pic, PATHINFO_EXTENSION) == 'xls' || pathinfo($kyc->omang_pic, PATHINFO_EXTENSION) == 'xlsx' || pathinfo($kyc->omang_pic, PATHINFO_EXTENSION) == 'csv')
                                                                    <img src="{{asset('images/excel.png')}}" width="100%" height="auto" >
                                                                @elseif(pathinfo($kyc->omang_pic, PATHINFO_EXTENSION) == 'jpeg' || pathinfo($kyc->omang_pic, PATHINFO_EXTENSION) == 'jpg' || pathinfo($kyc->omang_pic, PATHINFO_EXTENSION) == 'png')
                                                                    <img src="{{\AlphaDirect\Helper::getCloudFrontURL($kyc->omang_pic)}}" width="100%" height="auto" >
                                                                @else
                                                                    <img src="{{asset('images/doc.png')}}" width="100%" height="auto" >
                                                                @endif
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
                                                                <img src="{{\AlphaDirect\Helper::getCloudFrontURL($kyc->proof_residence) }}" width="100%" height="auto" >
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
                                                                <img src="{{\AlphaDirect\Helper::getCloudFrontURL($kyc->proof_income) }}" width="100%" height="auto" >
                                                            </a>
                                                        @endif
                                                        <div class="kt-avatar" style="float: left; clear: left;">
                                                        </div>
                                                    </div>

                                                    <div class="col-md-2">
                                                        <h3 class="col-form-label" style="float: left;">Passport</h3>
                                                        @if($kyc->passport_pic == NULL)
                                                            <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="100%" height="auto" >
                                                        @else
                                                            <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($kyc->passport_pic) !!}" target="_blank" download>
                                                                <img src="{{\AlphaDirect\Helper::getCloudFrontURL($kyc->passport_pic) }}" width="100%" height="auto" >
                                                            </a>
                                                        @endif
                                                        <div class="kt-avatar" style="float: left; clear: left;">
                                                        </div>
                                                    </div>
                                                </div>
                                                @endforeach
                                            @endif
                                        </table>
                                </div>
                            </div>
                        </div>

                        <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>
                        <div class="kt-portlet_body">
                            <div class="kt-section">
                                <div class="kt-section__content" style="padding-left:20px; padding-right: 20px;">
                                    <table class="table table-striped m-table">
                                        <tbody>
                                        @if($claimAccident->approved_customer == $banking_details->customer_id)
                                            <h5>Customer Bank  Details</h5>
                                        <tr>
                                            <th>Myzaka/Orange Money Cell</th>
                                            <td>{!! $banking_details->billingCell !!}</td>
                                            <th>Bank Name</th>
                                            <td>{!! $banking_details->bankName !!} </td>
                                        </tr>
                                        <tr>
                                            <th>Branch Code</th>
                                            <td>{!! $banking_details->branchCode !!}</td>
                                            <th>Account Number</th>
                                            @if($banking_details->accountNumber != NULL)
                                                <td>{!! str_repeat("*", strlen($banking_details->accountNumber)-4) . substr($banking_details->accountNumber, -4) !!}</td>
                                            @endif
                                        </tr>
                                            @endif
                                        </tbody>
                                    </table>
                                    <table>
                                        <br>
                                        <tbody class="table table-striped m-table">
                                        @if(!empty($partyBanking))
                                            <h5>Other Party Bank Details</h5>
                                            @foreach($partyBanking as $banking)
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
                                            @endforeach
                                        @endif
                                        </tbody>
                                    </table>

                                </div>
                            </div>
                        </div>
                        <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>
                        <div class="col-md-2">
                            <h5 class="col-form-label" style="float: left;">Additional Form</h5>
                            @if($claimAccident->document == NULL)
                                <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="50%" height="auto" >
                            @else
                                <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($claimAccident->document) !!}" target="_blank" download>
                                    <img src="{{\AlphaDirect\Helper::getCloudFrontURL($claimAccident->document) }}" width="50%" height="auto" >
                                </a>
                            @endif
                            <div class="kt-avatar" style="float: left; clear: left;"></div>
                        </div>


                        {{-- Write Off --}}
                    @elseif($claimAccident->claim_sub_status == "Write Off")
                        <h5>Write Off Claim Sub Status Details</h5>
                        <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>
                        <div class="kt-portlet_body">
                            <div class="kt-section">
                                <div class="kt-section__content" style="padding-left:20px; padding-right: 20px;">
                                    <table class="table table-striped m-table">
                                        <tbody>
                                                @if($claimAccident->approved_customer == $kyc->customer_id)
                                                    <h5>Customer KYC</h5>
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
                                                        @if(pathinfo($kyc->omang, PATHINFO_EXTENSION) == 'pdf')
                                                            <img src="{{asset('images/pdf.ico')}}" width="100%" height="auto" >
                                                        @elseif(pathinfo($kyc->omang, PATHINFO_EXTENSION) == 'docx' || pathinfo($kyc->omang, PATHINFO_EXTENSION) == 'doc' || pathinfo($kyc->omang, PATHINFO_EXTENSION) == 'docm')
                                                            <img src="{{asset('images/word.ico')}}" width="100%" height="auto" >
                                                        @elseif(pathinfo($kyc->omang, PATHINFO_EXTENSION) == 'xls' || pathinfo($kyc->omang, PATHINFO_EXTENSION) == 'xlsx' || pathinfo($kyc->omang, PATHINFO_EXTENSION) == 'csv')
                                                            <img src="{{asset('images/excel.png')}}" width="100%" height="auto" >
                                                        @elseif(pathinfo($kyc->omang, PATHINFO_EXTENSION) == 'jpeg' || pathinfo($kyc->omang, PATHINFO_EXTENSION) == 'jpg' || pathinfo($kyc->omang, PATHINFO_EXTENSION) == 'png')
                                                            <img src="{{\AlphaDirect\Helper::getCloudFrontURL($kyc->omang)}}" width="100%" height="auto" >
                                                        @else
                                                            <img src="{{asset('images/doc.png')}}" width="100%" height="auto" >
                                                    @endif
<!--                                                        <img src="{{\AlphaDirect\Helper::getCloudFrontURL($kyc->omang)}}" width="100%" height="auto" >-->
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
                                                        <img src="{{\AlphaDirect\Helper::getCloudFrontURL($kyc->proof_income) }}" width="100%" height="auto" >
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
                                                        <img src="{{\AlphaDirect\Helper::getCloudFrontURL($kyc->passport) }}" width="100%" height="auto" >
                                                    </a>
                                                @endif
                                                <div class="kt-avatar" style="float: left; clear: left;">
                                                </div>
                                            </div>
                                        </div>
                                            @endif

                                                {{--other party kyc--}}
                                                @if(!empty($partykyc))
                                                    <h5>Other Party KYC</h5>
                                                    @foreach($partykyc as $kyc)
                                                        <div class="form-group row">
                                                            <div class="col-md-2">
                                                                <h3 class="col-form-label" style="float: left;">Driving License</h3>
                                                                @if($kyc->driving_license == NULL)
                                                                    <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="100%" height="auto" >
                                                                @else
                                                                    <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($kyc->driving_license) !!}" target="_blank" download>
                                                                        <img src="{{ \AlphaDirect\Helper::getCloudFrontURL($kyc->driving_license)}}" width="100%" height="auto" >
                                                                    </a>
                                                                @endif
                                                                <div class="kt-avatar" style="float: left; clear: left;"></div>
                                                            </div>

                                                            <div class="col-md-2">
                                                                <h3 class="col-form-label" style="float: left;">Omang ID</h3>
                                                                @if($kyc->omang_pic == NULL)
                                                                    <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="100%" height="auto" >
                                                                @else
                                                                    <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($kyc->omang_pic) !!}" target="_blank" download>
                                                                        <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($kyc->omang_pic) !!}" target="_blank" download>
                                                                            @if(pathinfo($kyc->omang_pic, PATHINFO_EXTENSION) == 'pdf')
                                                                                <img src="{{asset('images/pdf.ico')}}" width="100%" height="auto" >
                                                                            @elseif(pathinfo($kyc->omang_pic, PATHINFO_EXTENSION) == 'docx' || pathinfo($kyc->omang_pic, PATHINFO_EXTENSION) == 'doc' || pathinfo($kyc->omang_pic, PATHINFO_EXTENSION) == 'docm')
                                                                                <img src="{{asset('images/word.ico')}}" width="100%" height="auto" >
                                                                            @elseif(pathinfo($kyc->omang_pic, PATHINFO_EXTENSION) == 'xls' || pathinfo($kyc->omang_pic, PATHINFO_EXTENSION) == 'xlsx' || pathinfo($kyc->omang_pic, PATHINFO_EXTENSION) == 'csv')
                                                                                <img src="{{asset('images/excel.png')}}" width="100%" height="auto" >
                                                                            @elseif(pathinfo($kyc->omang_pic, PATHINFO_EXTENSION) == 'jpeg' || pathinfo($kyc->omang_pic, PATHINFO_EXTENSION) == 'jpg' || pathinfo($kyc->omang_pic, PATHINFO_EXTENSION) == 'png')
                                                                                <img src="{{\AlphaDirect\Helper::getCloudFrontURL($kyc->omang_pic)}}" width="100%" height="auto" >
                                                                            @else
                                                                                <img src="{{asset('images/doc.png')}}" width="100%" height="auto" >
                                                                            @endif
                                                                        </a>
{{--                                                                        <img src="{{\AlphaDirect\Helper::getCloudFrontURL($kyc->omang_pic)}}" width="100%" height="auto" >--}}
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
                                                                @if($kyc->passport_pic == NULL)
                                                                    <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="100%" height="auto" >
                                                                @else
                                                                    <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($kyc->passport_pic) !!}" target="_blank" download>
                                                                        <img src="{{\AlphaDirect\Helper::getCloudFrontURL($kyc->passport_pic)}}" width="100%" height="auto" >
                                                                    </a>
                                                                @endif
                                                                <div class="kt-avatar" style="float: left; clear: left;">
                                                                </div>
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                @endif
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>
                        <div class="kt-portlet_body">
                            <div class="kt-section">
                                <div class="kt-section__content" style="padding-left:20px; padding-right: 20px;">
                                    <table class="table table-striped m-table">
                                        <h5>Customer Bank  Details</h5>
                                        <tbody>
                                        @if($claimAccident->approved_customer == $banking_details->customer_id)
                                            <tr>
                                                <th>Myzaka/Orange Money Cell</th>
                                                <td>{!! $banking_details->billingCell !!}</td>
                                                <th>Bank Name</th>
                                                <td>{!! $banking_details->bankName !!} </td>
                                            </tr>
                                            <tr>
                                                <th>Branch Code</th>
                                                <td>{!! $banking_details->branchCode !!}</td>
                                                <th>Account Number</th>
                                                @if($banking_details->accountNumber != NULL)
                                                    <td>{!! str_repeat("*", strlen($banking_details->accountNumber)-4) . substr($banking_details->accountNumber, -4) !!}</td>
                                                @endif
                                            </tr>
                                        @endif
                                        </tbody>
                                    </table>
                                    <table>
                                        <br>
                                        <tbody class="table table-striped m-table">
                                        @if(!empty($partyBanking))
                                            <h5>Other Party Bank Details</h5>
                                            @foreach($partyBanking as $banking)
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
                                            @endforeach
                                        @endif
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>
                        <div class="col-md-2">
                            <h5 class="col-form-label" style="float: left;">Additional Form</h5>
                            @if($claimAccident->document == NULL)
                                <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="50%" height="auto" >
                            @else
                                <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($claimAccident->document) !!}" target="_blank" download>
                                    <img src="{{\AlphaDirect\Helper::getCloudFrontURL($claimAccident->document)}}" width="50%" height="auto" >
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
                                    @if($selectedSalvage != NULL && $claimAccident->is_salvage_yard == 1)
                                        <tr>
                                            <th>Name of Salvage Yard</th>
                                            <td>{{$selectedSalvage->firstName}} {{$selectedSalvage->lastName}}</td>
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
                        <div class="kt-portlet_body">
                            <div class="kt-section">
                                <div class="kt-section__content" style="padding-left:20px; padding-right: 20px;">
                                    <table class="table table-striped m-table">
                                        @if($claimAccident->approved_customer == $kyc->customer_id)
                                            <h5>Customer KYC</h5>
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
                                                        <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($kyc->omang_pic) !!}" target="_blank" download>
                                                                @if(pathinfo($kyc->omang_pic, PATHINFO_EXTENSION) == 'pdf')
                                                                    <img src="{{asset('images/pdf.ico')}}" width="100%" height="auto" >
                                                                @elseif(pathinfo($kyc->omang_pic, PATHINFO_EXTENSION) == 'docx' || pathinfo($kyc->omang_pic, PATHINFO_EXTENSION) == 'doc' || pathinfo($kyc->omang_pic, PATHINFO_EXTENSION) == 'docm')
                                                                    <img src="{{asset('images/word.ico')}}" width="100%" height="auto" >
                                                                @elseif(pathinfo($kyc->omang_pic, PATHINFO_EXTENSION) == 'xls' || pathinfo($kyc->omang_pic, PATHINFO_EXTENSION) == 'xlsx' || pathinfo($kyc->omang_pic, PATHINFO_EXTENSION) == 'csv')
                                                                    <img src="{{asset('images/excel.png')}}" width="100%" height="auto" >
                                                                @elseif(pathinfo($kyc->omang_pic, PATHINFO_EXTENSION) == 'jpeg' || pathinfo($kyc->omang_pic, PATHINFO_EXTENSION) == 'jpg' || pathinfo($kyc->omang_pic, PATHINFO_EXTENSION) == 'png')
                                                                    <img src="{{\AlphaDirect\Helper::getCloudFrontURL($kyc->omang)}}" width="100%" height="auto" >
                                                                @else
                                                                    <img src="{{asset('images/doc.png')}}" width="100%" height="auto" >
                                                                @endif
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
                                        @endif

                                            {{--other party kyc--}}
                                            @if(!empty($partykyc))
                                                <h5>Other Party KYC</h5>
                                                @foreach($partykyc as $kyc)
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
                                                            @if($kyc->omang_pic == NULL)
                                                                <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="100%" height="auto" >
                                                            @else
                                                                <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($kyc->omang_pic) !!}" target="_blank" download>
                                                                    @if(pathinfo($kyc->omang_pic, PATHINFO_EXTENSION) == 'pdf')
                                                                        <img src="{{asset('images/pdf.ico')}}" width="100%" height="auto" >
                                                                    @elseif(pathinfo($kyc->omang_pic, PATHINFO_EXTENSION) == 'docx' || pathinfo($kyc->omang_pic, PATHINFO_EXTENSION) == 'doc' || pathinfo($kyc->omang_pic, PATHINFO_EXTENSION) == 'docm')
                                                                        <img src="{{asset('images/word.ico')}}" width="100%" height="auto" >
                                                                    @elseif(pathinfo($kyc->omang_pic, PATHINFO_EXTENSION) == 'xls' || pathinfo($kyc->omang_pic, PATHINFO_EXTENSION) == 'xlsx' || pathinfo($kyc->omang_pic, PATHINFO_EXTENSION) == 'csv')
                                                                        <img src="{{asset('images/excel.png')}}" width="100%" height="auto" >
                                                                    @elseif(pathinfo($kyc->omang_pic, PATHINFO_EXTENSION) == 'jpeg' || pathinfo($kyc->omang_pic, PATHINFO_EXTENSION) == 'jpg' || pathinfo($kyc->omang_pic, PATHINFO_EXTENSION) == 'png')
                                                                        <img src="{{\AlphaDirect\Helper::getCloudFrontURL($kyc->omang_pic)}}" width="100%" height="auto" >
                                                                    @else
                                                                        <img src="{{asset('images/doc.png')}}" width="100%" height="auto" >
                                                                    @endif
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
                                                            @if($kyc->passport_pic == NULL)
                                                                <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="100%" height="auto" >
                                                            @else
                                                                <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($kyc->passport_pic) !!}" target="_blank" download>
                                                                    <img src="{{\AlphaDirect\Helper::getCloudFrontURL($kyc->passport_pic)}}" width="100%" height="auto" >
                                                                </a>
                                                            @endif
                                                            <div class="kt-avatar" style="float: left; clear: left;">
                                                            </div>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            @endif
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>
                        <div class="kt-portlet_body">
                            <div class="kt-section">
                                <div class="kt-section__content" style="padding-left:20px; padding-right: 20px;">
                                    <table class="table table-striped m-table">
                                        @if($claimAccident->approved_customer == $banking_details->customer_id)
                                            <h5>Customer Bank Details</h5>
                                        <tbody>
                                        <tr>
                                            <th>Myzaka/Orange Money Cell</th>
                                            <td>{!! $banking_details->billingCell !!}</td>
                                            <th>Bank Name</th>
                                            <td>{!! $banking_details->bankName !!} </td>
                                        </tr>
                                        <tr>
                                            <th>Branch Code</th>
                                            <td>{!! $banking_details->branchCode !!}</td>
                                            <th>Account Number</th>
                                            @if($banking_details->accountNumber != NULL)
                                                <td>{!! str_repeat("*", strlen($banking_details->accountNumber)-4) . substr($banking_details->accountNumber, -4) !!}</td>
                                            @endif
                                        </tr>
                                        </tbody>
                                            @endif
                                    </table>
                                    <table>
                                        <br>
                                        <tbody class="table table-striped m-table">
                                        @if(!empty($partyBanking))
                                            <h5>Other Party Bank Details</h5>
                                            @foreach($partyBanking as $banking)
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
                                            @endforeach
                                        @endif
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>
                        <div class="col-md-2">
                            <h5 class="col-form-label" style="float: left;">Additional Form</h5>
                            @if($claimAccident->document == NULL)
                                <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="50%" height="auto" >
                            @else
                                <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($claimAccident->document) !!}" target="_blank" download>
                                    <img src="{{\AlphaDirect\Helper::getCloudFrontURL($claimAccident->document)}}" width="50%" height="auto" >
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
@if($claims->claim_type == 'Accident' || $claims->claim_type == 'MOTORTRADERSEXTERNAL' || $claims->claim_type == 'MOTORTRADERSINTERNAL')
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
                                    <tbody>
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


{{--claims reject status model div--}}
@if($claims->claim_type == 'BUSINESSINTERRUPTION' || $claims->claim_type == 'BUSINESSALLRISKS' || $claims->claim_type == 'ELECTRONICEQUIPMENT' || $claims->claim_type == 'PERSONALALLRISKS' || $claims->claim_type == 'THEFT' || $claims->claim_type == 'MONEY' || $claims->claim_type == 'WORKERSCOMPENSATION' || $claims->claim_type == 'STATEDBENEFITS' || $claims->claim_type == 'FIDELITYGUARANTEE' || $claims->claim_type == 'TRAVELINSURANCE' || $claims->claim_type == 'GOODSINTRANSIT' || $claims->claim_type == 'FIRE' || $claims->claim_type == 'LIABILITY'  || $claims->claim_type == 'PROPERTYDAMAGE' || $claims->claim_type == 'ACCIDENTALDAMAGE' || $claims->claim_type == 'DEFECTIVEWORKMANSHIP' || $claims->claim_type == 'MOBILEELECTRONICDEVICES' || $claims->claim_type == 'OFFICECONTENTS')
    <div class="modal fade" id="newClaimRejectStatus" tabindex="-1" role="dialog" aria-labelledby="user_delete_confirm_title" aria-hidden="true">
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
                                    <tbody>
                                    <tr>
                                        <th>Reason</th>
                                        <td>{{ $newclaim->reason }}</td>
                                    </tr>
                                    <tr>
                                        <th>Note</th>
                                        <td>{{ $newclaim->note }}</td>
                                    </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <h5 class="col-form-label" style="float: left;">Additional Form</h5>
                        @if($newclaim->document == NULL)
                            <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="100%" height="auto" >
                        @else
                            <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($newclaim->document) !!}" target="_blank" download>
                                <img src="{{\AlphaDirect\Helper::getCloudFrontURL($newclaim->document)}}" width="100%" height="auto" >
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

    <div class="modal" tabindex="-1" role="dialog" id="delete_confirm">
        <div class="modal-dialog" role="document">
        <div class="modal-content" id="modal-content-attachment">

        </div>
        </div>
    </div>



@include('admin.layouts.scripts')
<script src="{{ asset('assets/vendors/general/bootstrap-select/dist/js/bootstrap-select.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/app/custom/general/components/forms/widgets/bootstrap-select.js') }}" type="text/javascript"></script>

<script src="{{  asset('assets/vendors/general/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}" type="text/javascript"></script>
<script src="{{  asset('assets/vendors/custom/components/vendors/bootstrap-datepicker/init.js') }}" type="text/javascript"></script>

<script src="{{ asset('assets/app/custom/general/components/forms/widgets/bootstrap-timepicker.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/general/bootstrap-datetime-picker/js/bootstrap-datetimepicker.min.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/general/bootstrap-timepicker/js/bootstrap-timepicker.min.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/custom/components/vendors/bootstrap-timepicker/init.js') }}" type="text/javascript"></script>


<script src="{{asset('assets/vendors/general/jquery.repeater/src/lib.js')}}" type="text/javascript"></script>
<script src="{{asset('assets/vendors/general/jquery.repeater/src/jquery.input.js')}}" type="text/javascript"></script>
<script src="{{asset('assets/vendors/general/jquery.repeater/src/repeater.js')}}" type="text/javascript"></script>
<script src="{{asset('assets/app/custom/general/components/forms/layouts/repeater.js')}}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/general/jquery-validation/dist/jquery.validate.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/custom/components/vendors/jquery-validation/init.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/general/jquery-validation/dist/additional-methods.js') }}" type="text/javascript"></script>
<script src="{{  asset('assets/vendors/custom/datatables/datatables.bundle.js') }}" type="text/javascript"></script>

@if ($policy->product_id == 7 || $policy->product_id == 8)
    <script>
        // Using jQuery for convenience
        $("#reserveForm").on("submit", function(e) {
    // Initialize arrays
    var coverage_ids   = [];
    var coverage_names = [];
    var coverageLimits = [];
    var amts           = [];

    // Iterate over each table row containing coverage data
    $("tr.table-row").each(function(){
        var cid    = $(this).find("input[name='coverage_id[]']").val();
        var cname  = $(this).find("input[name='coverage_name[]']").val();
        var climit = $(this).find("input[name='coverageLimit[]']").val();
        var amt    = $(this).find("input[name='amt[]']").val();

        // Push the values; if amt is empty, push "0" (or any default you choose)
        coverage_ids.push(cid);
        coverage_names.push(cname);
        coverageLimits.push(climit);
        amts.push(amt === "" || amt === null ? "0" : amt);
    });

    // Build the combined data object in the same format
    var combinedData = {
        coverage_id:   coverage_ids,
        coverage_name: coverage_names,
        coverageLimit: coverageLimits,
        amt:           amts
    };

    // Optional: Log for debugging
    console.log("Combined Data:", JSON.stringify(combinedData));

    // Set the JSON string into the hidden input
    $("#combined_data").val(JSON.stringify(combinedData));

    // Optionally disable the individual inputs so only the combined data is submitted:
    $("input[name='coverage_id[]'], input[name='coverage_name[]'], input[name='coverageLimit[]'], input[name='amt[]']").prop("disabled", true);
});

    </script>
@endif

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

<script>
    var legalValue = $('.legaloptionValue').val();
    console.log(legalValue);
    if(legalValue == 1){
            $(".ownlawyer").show();
            $(".legal_yes_active").addClass('active');
            $(".legal_no_active").removeClass('active');
            $('.representingMember').addClass("required");
            $('.lawyerTarrif').addClass("required");
        }else{
            $(".ownlawyer").hide();
            $(".legal_no_active").addClass('active');
            $(".legal_yes_active").removeClass('active');
            $('.representingMember').removeClass("required");
            $('.lawyerTarrif').removeClass("required");
        }

     $(".legaloption").on("change",function(){
        var legal = this.value;

        if(legal == "1"){
            $(".ownlawyer").show();
            $('.representingMember').addClass("required");
            $('.lawyerTarrif').addClass("required");
        }else{
            $(".ownlawyer").hide();
            $('.representingMember').removeClass("required");
            $('.lawyerTarrif').removeClass("required");
        }
    });
</script>

@if($claims->claim_type == 'Legal')
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
                        legal_firm: {
                            required: true,
                        },
                        lawyer_name: {
                            required: true
                        },
                        legal_tel: {
                            required: true
                        },
                        legal_email: {
                            required: true
                        },
                    },
                    messages: {
                        legal_firm: "Please enter Legal Firm",
                        lawyer_name: "Please enter Lawyer Name",
                        legal_tel: "Please enter Legal Number",
                        legal_email: "Please enter Legal Email",
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

        jQuery(document).ready(function() {
            // assessorControl.init();
            reserveControl.init();
        });
    </script>
@endif
<script>
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
                // payee :{
                //     required: true
                // },
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
                form.submit(); // submit the form
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
</script>
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

@if($claims->claim_type == 'Accident' || $claims->claim_type == 'MOTORTRADERSEXTERNAL' || $claims->claim_type == 'MOTORTRADERSINTERNAL' || $claims->claim_type == 'BUSINESSINTERRUPTION' || $claims->claim_type == 'BUSINESSALLRISKS' || $claims->claim_type == 'ELECTRONICEQUIPMENT' || $claims->claim_type == 'PERSONALALLRISKS' || $claims->claim_type == 'THEFT' || $claims->claim_type == 'MONEY' || $claims->claim_type == 'WORKERSCOMPENSATION' || $claims->claim_type == 'STATEDBENEFITS' || $claims->claim_type == 'FIDELITYGUARANTEE' || $claims->claim_type == 'TRAVELINSURANCE' || $claims->claim_type == 'GOODSINTRANSIT' || $claims->claim_type == 'FIRE' || $claims->claim_type == 'LIABILITY'  || $claims->claim_type == 'PROPERTYDAMAGE' || $claims->claim_type == 'ACCIDENTALDAMAGE' || $claims->claim_type == 'DEFECTIVEWORKMANSHIP' || $claims->claim_type == 'MOBILEELECTRONICDEVICES' || $claims->claim_type == 'OFFICECONTENTS')
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
                    ignore: [],
                    ignore: ":hidden",
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
                            required: false,
                            future:true
                        },
                        attorney_assigned_date: {
                            required: false,
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
                        }
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
        append += '<div class="kt-portlet__body" data-attachment-block="true">' +
            '<div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>' +
            ' <div class="form-group row">'+
            '<label for="example-text-input" class="col-3 col-form-label">Name</label>'+
            '<div class="col-6">'+
            '<input type="text" class="form-control" name="name['+ count +']"  title="Name is required" placeholder="Enter Name" required>'+
            '</div>'+
            '</div>'+
            '<div class="form-group row DivCountAttachment">' +
            '<div class="form-group row">'+
            '<label for="example-text-input" class="col-3 col-form-label">Document Type Name</label>'+
            '<div class="col-6">'+
            '<input type="text" class="form-control" name="document_type_name['+ count +']"  title="Document type name is required" placeholder="Enter Document Type Name" required>'+
            '</div>'+
            '</div>'+
            '<label for="example-text-input" class="col-3 col-form-label">Document Type</label>' +
            '<div class="col-6">' +
            '<select class="form-control kt_selectpicker" data-live-search="true" title="Please Select document type" name="type['+ count +']" required>' +
            '@foreach($fileNames as $fileName)' +
            '<option value="{!! $fileName->value !!}">{!! $fileName->value !!}</option>' +
            '@endforeach' +
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


@if($claims->claim_type == 'Cellphone')
    <script>
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
        $(document).on("click", "#addSupplierButton_repair", function() {
            var count = $('.DivCount').length;
            var append = '';
            append += '<div class="kt-portlet__body">' +
                '<div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>' +
                '<div class="form-group row DivCount">' +
                '<label for="example-text-input" class="col-3 col-form-label">Repair Centers</label>' +
                '<div class="col-6">' +
                '<select class="form-control kt_selectpicker" data-live-search="true" title="Please Select Repair Center" name="supplier_id['+ count +']" required>' +
                '@foreach($repairCenters as $repaircenter)' +
                '<option value="{!! $repaircenter->id !!}">{!! $repaircenter->name !!}</option>' +
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
                '<input type="file" class="repair_accident_file" name="repair_accident_file['+ count +'][]" />' +
                '</label>' +
                '<span class="kt-avatar__cancel" data-toggle="kt-tooltip" title="Cancel Image"> <i class="fa fa-times"></i> </span>' +
                '</div></div>' +
                '<br>'+
                '<div class="kt-separator kt-separator--space-sm"></div>' +
                '<div>' +
                '<span class="btn btn-info btn-sm addSupplierFile_repair"> <i class="la la-plus"></i> Add </span>' +
                '</div></div>' +
                '<div>' +
                '<input type="button" class="btn btn-warning btn-sm" id="removeSupplierFileRepair" value="Remove">' +
                '</div>' +
                '</div></div>';
            $('#supplierDiv').append(append);
            KTAvatarDemo.init();
        });


        jQuery(document).ready(function() {
            //assessorControl.init();
            reserveControl.init();
            poControl.init();
            $('#SuppliercollapseRepair').on('click', '.addSupplierFile_repair', function() {
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
                    '<input type="file" class="repair_accident_file" name="repair_accident_file['+ count +'][]" />' +
                    '</label>' +
                    '<span class="kt-avatar__cancel" data-toggle="kt-tooltip" title="Cancel Image"> <i class="fa fa-times"></i> </span>' +
                    '</div>';
                $(this).parent().parent().children(':first-child').prepend(append);
                KTAvatarDemo.init();
            });
        });
    </script>

@endif

@if($claims->claim_type == 'Accident' || $claims->claim_type == 'MOTORTRADERSEXTERNAL' || $claims->claim_type == 'MOTORTRADERSINTERNAL' || $claims->claim_type == 'BUSINESSINTERRUPTION' || $claims->claim_type == 'BUSINESSALLRISKS' || $claims->claim_type == 'ELECTRONICEQUIPMENT' || $claims->claim_type == 'PERSONALALLRISKS' || $claims->claim_type == 'THEFT' || $claims->claim_type == 'MONEY' || $claims->claim_type == 'WORKERSCOMPENSATION' || $claims->claim_type == 'STATEDBENEFITS' || $claims->claim_type == 'FIDELITYGUARANTEE' || $claims->claim_type == 'TRAVELINSURANCE' || $claims->claim_type == 'GOODSINTRANSIT' || $claims->claim_type == 'FIRE' || $claims->claim_type == 'LIABILITY'  || $claims->claim_type == 'PROPERTYDAMAGE' || $claims->claim_type == 'ACCIDENTALDAMAGE' || $claims->claim_type == 'DEFECTIVEWORKMANSHIP' || $claims->claim_type == 'MOBILEELECTRONICDEVICES' || $claims->claim_type == 'OFFICECONTENTS')
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

            $('.child_dob').datepicker({
                rtl: KTUtil.isRTL(),
                todayHighlight: true,
                orientation: "bottom left",
                // templates: arrows,
                format: 'dd-mm-yyyy',
                endDate: "today",
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
                var avatar16 = new KTAvatar('repair_accident_file');
                var avatar16 = new KTAvatar('repair_accident_file2');
                var avatar17 = new KTAvatar('invoice');
                var avatar18 = new KTAvatar('accident_driving_license');
                var avatar19 = new KTAvatar('invoiceAccident');
                var avatar20 = new KTAvatar('claimStatusFile');
                var avatar21 = new KTAvatar('typeDiv');
                var avatar22 = new KTAvatar('incidentFront');
                var avatar23 = new KTAvatar('death_certificate');
                var avatar24 = new KTAvatar('repair_po');

                var avatar25 = new KTAvatar('notice_of_demand_id');
                var avatar26 = new KTAvatar('final_demand_letter');
                var avatar27 = new KTAvatar('debt_acknowledgment');
                var avatar28 = new KTAvatar('kyc_form');
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
                ignore: ":hidden",
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
        // Initialize KTFormControls only if it exists (to prevent errors)
        if (typeof KTFormControls !== 'undefined') {
            KTFormControls.init();
        }
        // Class initialization on page load
        KTAvatarDemo.init();
        // Initialize repeater for all claim types including Hospital CashBack
        if (typeof KTRepeaterDemo !== 'undefined') {
            KTRepeaterDemo.init();
        }
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
        $('#SuppliercollapseRepair').on('click', '#removeSupplierFileRepair', function() {
            $(this).parent().parent().parent().delay(100).slideUp(500);
            $(this).parent().parent().parent().remove();
        });
        $(document).on('click', '#attachmentDiv .removeAttachmentDiv', function(e) {
            e.preventDefault();
            e.stopPropagation();
            // Find the parent block with data-attachment-block attribute
            var $blockToRemove = $(this).closest('[data-attachment-block="true"]');
            // If not found, try parent().parent() approach
            if ($blockToRemove.length === 0) {
                $blockToRemove = $(this).parent().parent();
                // Make sure it's not #attachmentDiv itself
                if ($blockToRemove.attr('id') === 'attachmentDiv') {
                    $blockToRemove = $(this).parents('.kt-portlet__body').not('#attachmentDiv').first();
                }
            }
            // Remove the block
            if ($blockToRemove.length > 0) {
                $blockToRemove.fadeOut(300, function() {
                    $(this).remove();
                });
            }
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
        $('#uploadPO3').on('click', function() {
            if($('#SuppliercollapseRepair').hasClass('show'))
                $('#SuppliercollapseRepair').collapse("show");
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
        $('#complaintLog').on('click', function() {
            if($('#complaintLogCollapse').hasClass('show'))
                $('#complaintLogCollapse').collapse("hide");
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
    /*function to switch for edit to view screen*/
    function setToEdit()
    {
        var x = document.getElementById("claimEditDiv");
        var y = document.getElementById("claimLabelDiv");
        x.style.display = "block";
        y.style.display = "none";
    }
    function setToView()
    {
        var x = document.getElementById("claimEditDiv");
        var y = document.getElementById("claimLabelDiv");
        x.style.display = "none";
        y.style.display = "block";
    }
</script>
<script>
    const searchInput = document.getElementById('searchInputReserves');
    const table = document.getElementById('coverageTable');
    const rows = table.querySelectorAll('tbody tr');

    searchInput.addEventListener('keyup', function () {
      const query = this.value.toLowerCase();

      rows.forEach(row => {
        const cells = row.querySelectorAll('td');
        const rowText = Array.from(cells).map(cell => cell.textContent.toLowerCase()).join(' ');
        row.style.display = rowText.includes(query) ? '' : 'none';
      });
    });
  </script>
<script>
    function TransactionType(e)
    {
        var type_id= e.options[e.selectedIndex].value;
        /* Transaction type for Loss Payment from lookup data*/
        $("#coverageTable").show();
        $(".search-container-reserves").show();
        // $('#coverageTableDomCom').css('display', 'table');
        $('.allocation').attr("required","true");
        if((type_id == 43) || (type_id == 90) || (type_id == 92)) // Loss Payment, TP Liability Payment, Salvage Payment
        {
            $("#subTypeDiv").show();
            $("#allocationText").html("Payment Allocation");
            $('#invoiceDate').attr("required","true");
            $('#dueDate').attr("required", "true");
            $('#invoiceNum').attr("required", "true");
            $('#memoOnCheck').attr("required", "true");
            $('#trans_subType').attr("required", "true");
            $('#description').attr("required", "true");
            $("#lossReserveDiv").hide();
            if (type_id == 90)
            {
                $('#trans_subType').find('option[value!=95]').hide();
                $('#trans_subType').find('option[value=95]').show();
                $('#trans_subType').find('option[value=96]').show();
            } else if (type_id == 92)
            {
                $('#trans_subType').find('option[value!=99]').hide();
                $('#trans_subType').find('option[value=99]').show();
                $('#trans_subType').find('option[value=100]').show();
            } else {
                $('#trans_subType').find('option').show();
                $('#trans_subType').find('option[value=95]').hide();
                $('#trans_subType').find('option[value=96]').hide();
                $('#trans_subType').find('option[value=99]').hide();
                $('#trans_subType').find('option[value=100]').hide();
                $('#trans_subType').find('option[value=93]').hide();
                $('#trans_subType').find('option[value=94]').hide();
                $('#trans_subType').find('option[value=97]').hide();
                $('#trans_subType').find('option[value=98]').hide();
            }
            $('#trans_subType').selectpicker('refresh');
        }
        else if ((type_id == 42) || (type_id == 89) || (type_id == 91)) { // Loss Reserves, TP Liability Reserves, Salvage Reserves
            $("#lossReserveDiv").show();
            $("#subTypeDiv").hide();
            $('#lossreserve_trans_subType').attr("required", "true");
            $("#allocationText").html("Reserve Allocation");
            $('#invoiceDate').removeAttr("required");
            $('#dueDate').removeAttr("required");
            $('#invoiceNum').removeAttr("required");
            $('#memoOnCheck').removeAttr("required");
            $('#trans_subType').removeAttr("required");
            $('#description').removeAttr("required");
            if (type_id == 89)
            {
                $('#lossreserve_trans_subType').find('option[value!=93]').hide();
                $('#lossreserve_trans_subType').find('option[value=93]').show();
                $('#lossreserve_trans_subType').find('option[value=94]').show();
            } else if (type_id == 91)
            {
                $('#lossreserve_trans_subType').find('option[value!=97]').hide();
                $('#lossreserve_trans_subType').find('option[value=97]').show();
                $('#lossreserve_trans_subType').find('option[value=98]').show();
            } else {
                $('#lossreserve_trans_subType').find('option').show();
                $('#lossreserve_trans_subType').find('option[value=93]').hide();
                $('#lossreserve_trans_subType').find('option[value=94]').hide();
                $('#lossreserve_trans_subType').find('option[value=97]').hide();
                $('#lossreserve_trans_subType').find('option[value=98]').hide();
                $('#lossreserve_trans_subType').find('option[value=95]').hide();
                $('#lossreserve_trans_subType').find('option[value=96]').hide();
                $('#lossreserve_trans_subType').find('option[value=99]').hide();
                $('#lossreserve_trans_subType').find('option[value=100]').hide();
            }
            $('#lossreserve_trans_subType').selectpicker('refresh');
        }
        else
        {
            $("#subTypeDiv").hide();
            $("#allocationText").html("Reserve Allocation");
            $('#invoiceDate').removeAttr("required");
            $('#dueDate').removeAttr("required");
            $('#invoiceNum').removeAttr("required");
            $('#memoOnCheck').removeAttr("required");
            $('#trans_subType').removeAttr("required");
            $('#description').removeAttr("required");
            $("#lossReserveDiv").hide();
        }
    };
    function setVehicleToEdit()
    {
        var x = document.getElementById("vehicleEditDiv");
        var y = document.getElementById("vehicleLabelDiv");
        x.style.display = "block";
        y.style.display = "none";
        // Class initialization on page load
        KTAvatarDemo.init();
    }
    function setVehicleToView()
    {
        var x = document.getElementById("vehicleEditDiv");
        var y = document.getElementById("vehicleLabelDiv");
        x.style.display = "none";
        y.style.display = "block";
    }
</script>

<script>
    /* function for switching on claim edit screen*/
    function setClaimToEdit()
    {
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

        /* function for switching on claim edit screen*/
        function setNewClaimToEdit()
    {
        var x = document.getElementById("newClaimEditDiv");
        var y = document.getElementById("newClaimLabelDiv");
        // var z = document.getElementById("recipientDiv");
        // var w = document.getElementById("otherInfoDiv");
        x.style.display = "block";
        // z.style.display = "block";
        // w.style.display = "block";
        y.style.display = "none";
        // Class initialization on page load
        KTAvatarDemo.init();
    }
    /* function for switching on status edit screen*/
    function setStatusToEdit()
    {
        var d = document.getElementById("StatusEditDiv");
        var c = document.getElementById("StatusLabelDiv");
        c.style.display = "none";
        d.style.display = "block";
    }
    /* function for switching on status view screen*/
    function setStatusToView()
    {
        var d = document.getElementById("StatusEditDiv");
        var c = document.getElementById("StatusLabelDiv");
        c.style.display = "block";
        d.style.display = "none";
    }

    /* function for switching on status edit screen*/
    function setNewClaimStatusToEdit()
    {
        var d = document.getElementById("newClaimStatusEditDiv");
        var c = document.getElementById("newClaimStatusLabelDiv");
        c.style.display = "none";
        d.style.display = "block";
    }
    /* function for switching on status view screen*/
    function setNewClaimStatusToView()
    {
        var d = document.getElementById("newClaimStatusEditDiv");
        var c = document.getElementById("newClaimStatusLabelDiv");
        c.style.display = "block";
        d.style.display = "none";
    }
    /* function for switching on claim view screen*/
    function setClaimToView()
    {
        var x = document.getElementById("claimEditDiv");
        var y = document.getElementById("claimLabelDiv");
        var z = document.getElementById("recipientDiv");
        var w = document.getElementById("otherInfoDiv");
        x.style.display = "none";
        z.style.display = "none";
        w.style.display = "none";
        y.style.display = "block";
    }

    function setNewClaimToView()
    {
        var x = document.getElementById("newClaimEditDiv");
        var y = document.getElementById("newClaimLabelDiv");
        // var z = document.getElementById("recipientDiv");
        // var w = document.getElementById("otherInfoDiv");
        x.style.display = "none";
        // z.style.display = "none";
        // w.style.display = "none";
        y.style.display = "block";
    }

    $(document).ready(function() {

        var val = $('input[name="loss_cause"]:checked').val();
        if(val == '0') {
        $('#otherCauseInput').show();
        } else {
        $('#otherCauseInput').hide();
        }

        $('.lossCause').click(function() {
        var Checkval = $(this).val();
        if(Checkval == '0') {
            $('#otherCauseInput').show();
        } else {
            $('#otherCauseInput').hide();
        }
        });
    });

    $(document).ready(function() {
        var selectedValue = $('#your_direct_employ option:selected').val();
        if(selectedValue == '0') {
            $('#your_direct_employ_div').show();
        } else {
            $('#your_direct_employ_div').hide();
        }

        $('.your_direct_employ').change(function() {
            var selectedValue = $('#your_direct_employ option:selected').val();
            if(selectedValue == '0') {
                $('#your_direct_employ_div').show();
            } else {
                $('#your_direct_employ_div').hide();
            }
        });
    });

    $(document).ready(function() {
        var selectedValue = $('#driver_as_insured option:selected').val();
        if(selectedValue == '0') {
            $('#driver_as_insured_div').show();
        } else {
            $('#driver_as_insured_div').hide();
        }

        $('.driver_as_insured').change(function() {
            var selectedValue = $('#driver_as_insured option:selected').val();
            if(selectedValue == '0') {
                $('#driver_as_insured_div').show();
            } else {
                $('#driver_as_insured_div').hide();
            }
        });
    });

    $(document).ready(function() {
        var selectedValue = $('#police_been_notified option:selected').val();
        if(selectedValue == '1') {
            $('#police_been_notified_div').show();
        } else {
            $('#police_been_notified_div').hide();
        }

        $('.police_been_notified').change(function() {
            var selectedValue = $('#police_been_notified option:selected').val();
            if(selectedValue == '1') {
                $('#police_been_notified_div').show();
            } else {
                $('#police_been_notified_div').hide();
            }
        });
    });

    $(document).ready(function() {
        var selectedValue = $('#third_party_insured_elsewhere option:selected').val();
        if(selectedValue == '1') {
            $('#tp_insured_elsewhere_email_div').show();
        } else {
            $('#tp_insured_elsewhere_email_div').hide();
        }

        $('.third_party_insured_elsewhere').change(function() {
            var selectedValue = $('#third_party_insured_elsewhere option:selected').val();
            if(selectedValue == '1') {
                $('#tp_insured_elsewhere_email_div').show();
            } else {
                $('#tp_insured_elsewhere_email_div').hide();
            }
        });
    });

    $(document).ready(function() {

        var val = $('input[name="is_sole_owner_of_property"]:checked').val();
        if(val == '0') {
        $('#soleOwnerDiv').show();
        } else {
        $('#soleOwnerDiv').hide();
        }

        $('.soleOwner').click(function() {
        var Checkval = $(this).val();
        if(Checkval == '0') {
            $('#soleOwnerDiv').show();
        } else {
            $('#soleOwnerDiv').hide();
        }
        });
    });


    $(document).ready(function() {

        var val = $('input[name="property_stolen_damaged"]:checked').val();
        if(val == '0') {
        $('#classStolen').show();
        } else {
        $('#classStolen').hide();
        }

        $('.propertyStolenDamaged').click(function() {
        var Checkval = $(this).val();
        if(Checkval == '0') {
            $('#classStolen').show();
        } else {
            $('#classStolen').hide();
        }
        });
    });

    $(document).ready(function() {
        var val = $('input[name="anyone_on_premises"]:checked').val();
        if(val == '1') {
            $('#detailsInBriefInput').show();
        } else {
            $('#detailsInBriefInput').hide();
        }

        $('.anyone_on_premises').click(function() {
        var Checkval = $(this).val();
        if(Checkval == '1') {
                $('#detailsInBriefInput').show();
        } else {
                $('#detailsInBriefInput').hide();
        }
        });
    });

    $(document).ready(function() {
        var val = $('input[name="is_carrier_contracted"]:checked').val();
        if(val == '1') {
            $('#is_carrier_contracted_div').show();
        } else {
            $('#is_carrier_contracted_div').hide();
        }

        $('.is_carrier_contracted').click(function() {
        var Checkval = $(this).val();
        if(Checkval == '1') {
                $('#is_carrier_contracted_div').show();
        } else {
                $('#is_carrier_contracted_div').hide();
        }
        });
    });

    $(document).ready(function() {
        var val = $('input[name="other_insurance_against_theft"]:checked').val();
        if(val == '1') {
            $('#other_insurance_against_theft_div').show();
        } else {
            $('#other_insurance_against_theft_div').hide();
        }

        $('.other_insurance_against_theft').click(function() {
        var Checkval = $(this).val();
        if(Checkval == '1') {
                $('#other_insurance_against_theft_div').show();
        } else {
                $('#other_insurance_against_theft_div').hide();
        }
        });
    });

    $(document).ready(function() {
        var val = $('input[name="anyone_during_burglary"]:checked').val();
        if(val == '1') {
            $('#anyone_during_burglary_div').show();
        } else {
            $('#anyone_during_burglary_div').hide();
        }

        $('.anyone_during_burglary').click(function() {
        var Checkval = $(this).val();
        if(Checkval == '1') {
                $('#anyone_during_burglary_div').show();
        } else {
                $('#anyone_during_burglary_div').hide();
        }
        });
    });

    $(document).ready(function() {
        var val = $('input[name="premises_guarded_by_watchman"]:checked').val();
        if(val == '1') {
            $('#premises_guarded_by_watchman_div').show();
        } else {
            $('#premises_guarded_by_watchman_div').hide();
        }

        $('.premises_guarded_by_watchman').click(function() {
        var Checkval = $(this).val();
        if(Checkval == '1') {
                $('#premises_guarded_by_watchman_div').show();
        } else {
                $('#premises_guarded_by_watchman_div').hide();
        }
        });
    });

    $(document).ready(function() {
        var val = $('input[name="suspect_any_person"]:checked').val();
        if(val == '1') {
            $('#suspect_any_person_div').show();
        } else {
            $('#suspect_any_person_div').hide();
        }

        $('.suspect_any_person').click(function() {
        var Checkval = $(this).val();
        if(Checkval == '1') {
                $('#suspect_any_person_div').show();
        } else {
                $('#suspect_any_person_div').hide();
        }
        });
    });

    $(document).ready(function() {
        var val = $('input[name="other_insurance_against_fire"]:checked').val();
        if(val == '1') {
            $('#other_insurance_against_fire_div').show();
        } else {
            $('#other_insurance_against_fire_div').hide();
        }

        $('.other_insurance_against_fire').click(function() {
        var Checkval = $(this).val();
        if(Checkval == '1') {
                $('#other_insurance_against_fire_div').show();
        } else {
                $('#other_insurance_against_fire_div').hide();
        }
        });
    });

    $(document).ready(function() {
        // $('#otherCauseInput').hide();
        $('.lossCause').click(function() {
            var Checkval = $(this).val();
            if(Checkval == '0') {
                    $('#otherCauseInput').show();
            } else {
                    $('#otherCauseInput').hide();
            }
        });
    });

    $(document).ready(function() {
        // $('#otherCauseInput').hide();
        $('.soleOwner').click(function() {
            var Checkval = $(this).val();
            if(Checkval == '0') {
                    $('#soleOwnerDiv').show();
            } else {
                    $('#soleOwnerDiv').hide();
            }
        });
    });

    $(document).ready(function() {
        // $('#otherCauseInput').hide();
        $('.propertyStolenDamaged').click(function() {
            var Checkval = $(this).val();
            if(Checkval == '0') {
                    $('#classStolen').show();
            } else {
                    $('#classStolen').hide();
            }
        });
    });

    $(document).ready(function() {
        // $('#detailsInBriefInput').hide();
        $('.anyone_on_premises').click(function() {
            var Checkval = $(this).val();
            if(Checkval == '1') {
                    $('#detailsInBriefInput').show();
            } else {
                    $('#detailsInBriefInput').hide();
            }
        });
    });

    $(document).ready(function() {
    var val = $('input[name="AttorneyInvolved"]:checked').val();
    if(val == '1') {
        $('#primary_attorney_assigned').show();
        $('#primary_attorney_assigned_date').show();
    } else {
        $('#primary_attorney_assigned').hide();
        $('#primary_attorney_assigned_date').hide();
    }

    $('.attorney_radio').click(function() {
        var Checkval = $(this).val();
        if(Checkval == '1') {
            $('#primary_attorney_assigned').show();
            $('#primary_attorney_assigned_date').show();
        } else {
            $('#primary_attorney_assigned').hide();
            $('#primary_attorney_assigned_date').hide();
        }
    });
});

$(document).ready(function() {
    var val = $('input[name="Isthismotorclaim"]:checked').val();
    if(val == '1') {
        $('#vehiclePlateDiv').show();
    } else {
        $('#vehiclePlateDiv').hide();
    }

    $('.Isthismotorclaim').click(function() {
        var Checkval = $(this).val();
        if(Checkval == '1') {
            $('#vehiclePlateDiv').show();
            $('#vehiclePlate').attr('required', true);
        } else {
            $('#vehiclePlateDiv').hide();
            $('#vehiclePlate').removeAttr('required');
        }
    });
});

$(document).ready(function() {
    var val = $('input[name="co_attorney_involved"]:checked').val();
    if(val == '1') {
        $('#co_attorney_assigned').show();
        $('#co_attorney_assigned_date').show();
    } else {
        $('#co_attorney_assigned').hide();
        $('#co_attorney_assigned_date').hide();
    }

    $('.co_attorney_radio').click(function() {
        var Checkval = $(this).val();
        if(Checkval == '1') {
            $('#co_attorney_assigned').show();
            $('#co_attorney_assigned_date').show();
        } else {
            $('#co_attorney_assigned').hide();
            $('#co_attorney_assigned_date').hide();
        }
    });
});

</script>
<script type="text/javascript">
    $(document).ready(function(){
        var maxField = 30; //Input fields increment limitation
        var addButton = $('.add_button');
        var removeButton = $('.remove_button');  //Add button selector
        var wrapper = $('.Production'); //Input field wrapper
        var fieldHTML = '<div class="form-group row"><input type="text" class="form-control col-6" name="defaulting_employees_name[]" value="" placeholder="Enter Defaulting Employees Name"><input type="text" class="form-control col-5" name="defaulting_employees_position[]" value="" placeholder="Enter Defaulting Employees Position"><a href="javascript:void(0);" class="remove_button col-1"><i class="fas fa-minus " style="font-size:23px; color:red;"></a></div>'; //New input field html
        var x = 1; //Initial field counter is 1

        //Once add button is clicked
        $(addButton).click(function(){
            //Check maximum number of input fields
            if(x < maxField){
                x++; //Increment field counter
                $(wrapper).append(fieldHTML); //Add field html
            }
        });

        //Once remove button is clicked
        $(wrapper).on('click', '.remove_button', function(e){
            e.preventDefault();
            $(this).parent('div').remove(); //Remove field html
            x--; //Decrement field counter
        });
    });
    $(document).ready(function(){
        var maxField = 30; //Input fields increment limitation
        var addButton2 = $('.add_button2');
        var removeButton2 = $('.remove_button2');  //Add button selector
        var wrapper2 = $('.Production2'); //Input field wrapper
        var fieldHTML2 = '<div class="form-group row"> <input type="text" class="form-control col-6" name="defaulting_employees_name[]" value="" placeholder="Enter Defaulting Employees Name"><input type="text" class="form-control col-5" name="defaulting_employees_position[]" value="" placeholder="Enter Defaulting Employees Position"><a href="javascript:void(0);" class="remove_button2 col-1"><i class="fas fa-minus " style="font-size:23px; color:red;"></a></div>'; //New input field html
        var x = 1; //Initial field counter is 1

        //Once add button is clicked
        $(addButton2).click(function(){
            //Check maximum number of input fields
            if(x < maxField){
                x++; //Increment field counter
                $(wrapper2).append(fieldHTML2); //Add field html
            }
        });

        //Once remove button is clicked
        $(wrapper2).on('click', '.remove_button2', function(e){
            e.preventDefault();
            $(this).parent('div').remove(); //Remove field html
            x--; //Decrement field counter
        });
    });
</script>
<script>
    function vehicleMsg()
    {
        var isChecked=document.getElementById("vehicleValue").checked;
        if (isChecked)
        {
            document.getElementById("Msg").innerHTML="Yes";
            document.getElementById("Msg").style.color="cornflowerblue";
            var x = document.getElementById("sup");
            x.style.display = "block";
            $('#sup').delay(100).slideDown(500);
            $('#sup').rules('add',  { required: true, messages: { required: "Please Select Coverages" } });
        }
        else
        {
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
    $('.kt-repeater__add-data').on('click',".btn-success", function()
    {
        var arrows;
        if (KTUtil.isRTL())
        {
            arrows = {
                leftArrow: '<i class="la la-angle-right"></i>',
                rightArrow: '<i class="la la-angle-left"></i>'
            }
        }
        else
        {
            arrows = {
                leftArrow: '<i class="la la-angle-left"></i>',
                rightArrow: '<i class="la la-angle-right"></i>'
            }
        }
    });
</script>

<script>
    function isRecoveryInvolved(){
        var isChecked=document.getElementById("recovery_involved").checked;
        if (isChecked)
        {
            $('#recovery_involved_div').delay(100).slideDown(500);
        }
        else
        {
            $('#recovery_involved_div').delay(100).slideUp(500);
        }
    }

    $(document).ready(function (){
            $(document).on('click', '.thirdPartyInsured', function () {
        // var isChecked=document.getElementsByClassName("thirdPartyInsured").checked;
        // if ("input:checkbox[class=thirdPartyInsured]:checked")
            var isChecked=$(this).is(":checked");
            // var thisVal = $(this);
            if (isChecked == true) {
                $(this).parent().find('.tpInsuredMsg').html("Yes");
                $(this).parent().find('.tpInsuredMsg').css('color','cornflowerblue');
                $(this).parent().parent().parent().parent().parent().parent().parent().find('.tpInsuredDetailsDiv').delay(100).slideDown(500);
                // $(this).parent().find('.tpInsuredDetailsDiv').delay(100).slideDown(500);
                // $(this).$(".tpInsuredMsg").html("Yes");
            } else {
                $(this).parent().find('.tpInsuredMsg').html("No");
                $(this).parent().find('.tpInsuredMsg').css('color','#ff4d4d');
                $(this).parent().parent().parent().parent().parent().parent().parent().find('.tpInsuredDetailsDiv').delay(100).slideUp(500);
                // $(this).$(".tpInsuredMsg").html("No");
            }
        });
    });

    function thirdPartyMsg()
    {
        var isChecked=document.getElementById("thirdPartyValue").checked;
        if (isChecked)
        {
            document.getElementById("Msg").innerHTML="Yes";
            document.getElementById("Msg").style.color="cornflowerblue";
            $('#detailDiv').delay(100).slideDown(500);
            $('.coverage').rules('add',  { required: true, messages: { required: "Please Select Coverages" } });
        }
        else
        {
            document.getElementById("Msg").innerHTML="No";
            document.getElementById("Msg").style.color="#ff4d4d";
            $('#details').delay(100).slideUp(500);
            $('#detailDiv').delay(100).slideUp(500);
            $('.coverage').rules('remove',  'required');
        }
    }
    function checkAttorney()
    {
        var attorneyChecked=document.getElementById("attorneyValue").checked;
        if (attorneyChecked)
        {
            $('#attorneyDiv').delay(100).slideDown(500);
        }
        else
        {
            $('#attorneyDiv').delay(100).slideUp(500);
        }
    }
    function checkSalvageYard()
    {
        var salvageChecked=document.getElementById("salvage").checked;
        if (salvageChecked)
        {
            $('#SalvageList').delay(100).slideDown(500);
            $(".salvage_yard").prop('required',true);
        }
        else
        {
            $('#SalvageList').delay(100).slideUp(500);
            $(".salvage_yard").prop('required',false);
        }
        KTFormControlsStatus.init();
    }
    function openSubStatus(sel)
    {
        var value = sel.options[sel.selectedIndex].text;

        var claimType = '{{$claims->claim_type}}';

        var isSpecialClaimType = ['BUSINESSINTERRUPTION', 'BUSINESSALLRISKS', 'ELECTRONICEQUIPMENT','PERSONALALLRISKS', 'THEFT', 'WORKERSCOMPENSATION', 'STATEDBENEFITS' , 'FIDELITYGUARANTEE', 'TRAVELINSURANCE', 'GOODSINTRANSIT', 'FIRE', 'LIABILITY', 'PROPERTYDAMAGE', 'ACCIDENTALDAMAGE', 'DEFECTIVEWORKMANSHIP', 'MOBILEELECTRONICDEVICES', 'OFFICECONTENTS'].includes(claimType);

        console.log("test", claimType);

        if (value == "Approved")
        {
            $('#sub_statusDiv').delay(100).slideDown(500);
            $('#uploadFile').delay(100).slideUp(500);
            $('#selectStatus').delay(100).slideUp(500);
            $('#dashSeparator').delay(100).slideUp(500);
            $('#SalvageDiv').delay(100).slideUp(500);
            //Reject Validations
            $('input[name="reason"]').prop('required',false);
            $('input[name="note"]').prop('required',false);
            $('#selectStatusSubrogation').delay(100).slideUp(500);
            if (isSpecialClaimType) {
                $('#newClaimSub_statusDiv').delay(100).slideDown(500);
                $('#claim_approved_div').delay(100).slideDown(500);
            }
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
            $('#selectStatusSubrogation').delay(100).slideUp(500);
            if (isSpecialClaimType) {
                $('#newClaimSub_statusDiv').delay(100).slideUp(500);
                $('#claim_approved_div').delay(100).slideUp(500);
            }
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
            $('#selectStatusSubrogation').delay(100).slideUp(500);
            if (isSpecialClaimType) {
                $('#newClaimSub_statusDiv').delay(100).slideUp(500);
                $('#claim_approved_div').delay(100).slideUp(500);
            }
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
            $('#selectStatusSubrogation').delay(100).slideUp(500);

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
            $('#substatus_repeater').delay(100).slideDown(500);
            $('#add_details').delay(100).slideDown(500);
            $('#accident_billingCell').attr("required", "true");
            $('#accident_bankNumDropDown').attr("required", "true");
            $('#accident_bankBranchDropDown').attr("required", "true");
            $('#accident_accountNumber').attr("required", "true");
            $('#accountType').attr("required", "true");
            $('input[name="reason"]').prop('required',false);
            $('input[name="note"]').prop('required',false);
            $('#selectStatusSubrogation').delay(100).slideUp(500);

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
            $('#substatus_repeater').delay(100).slideDown(500);
            $('#add_details').delay(100).slideDown(500);
            $('#accident_billingCell').attr("required", "true");
            $('#accident_bankNumDropDown').attr("required", "true");
            $('#accident_bankBranchDropDown').attr("required", "true");
            $('#accident_accountNumber').attr("required", "true");
            $('#accountType').attr("required", "true");
            $('input[name="reason"]').prop('required',false);
            $('input[name="note"]').prop('required',false);
            $('#selectStatusSubrogation').delay(100).slideUp(500);

        }
        else if(isSelected == "Exgratia")
        {
            $('#cashform').css('display','none');
            $('#dashSeparator').delay(100).slideDown(500);
            $('#selectCash').delay(100).slideDown(500);
            $('#selectStatus').delay(100).slideUp(500);
            $('#SalvageDiv').delay(100).slideUp(500);
            $('#selectWrite').delay(100).slideDown(500);
            $('#SalvageList').delay(100).slideUp(500);
            $('#uploadFile').delay(100).slideDown(500);
            $('.check_beneficiary').delay(100).slideDown(500);
            $('#substatus_repeater').delay(100).slideDown(500);
            $('#add_details').delay(100).slideDown(500);
            $('#accident_billingCell').attr("required", "true");
            $('#accident_bankNumDropDown').attr("required", "true");
            $('#accident_bankBranchDropDown').attr("required", "true");
            $('#accident_accountNumber').attr("required", "true");
            $('#accountType').attr("required", "true");
            $('input[name="reason"]').prop('required',false);
            $('input[name="note"]').prop('required',false);
            $('#selectStatusSubrogation').delay(100).slideUp(500);

        } else if (isSelected == "Subrogation") {
            $('#selectStatusSubrogation').delay(100).slideDown(500);
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
    }
    $(document).ready(function ()
    {
        $('#selectValue').trigger('change');
    });
    $('#addBeneficiary').click(function ()
    {
        KTBootstrapDatepicker.init();
        $('#addBeneficiaryDiv').toggle();
        $('#addBeneficiary').toggle();
    });
    $('#addPassenger').click(function()
    {
        KTBootstrapDatepicker.init();
        $('#addPassengerDiv').toggle();
        $('#addPassenger').toggle();
    });
    $('#claimStatusBtn').click(function ()
    {
        $('#claimStatus').modal('show')
    });
    $('#claimStatusRejectBtn').click(function ()
    {
        $('#claimRejectStatus').modal('show')
    });
    $('#newClaimStatusRejectBtn').click(function ()
    {
        $('#newClaimRejectStatus').modal('show')
    });
    $(".SupplierInvoiceTable").on("click", ".resentInvoiceRequest" , function(event)
    {
        event.preventDefault();
        var url = '{{ route('admin.claims.acceptQuote', ":slug") }}';
        url = url.replace(':slug', $(this).attr("href"));
        $("#resentForm").attr('action', url);
        $('#resent_model').modal('show');
    });
    $(".col-12").on('click', '.kt-repeater__add-data', function(){
        KTBootstrapDatepicker.init();
        // Re-initialize repeater after adding new item
        if (typeof KTRepeaterDemo !== 'undefined') {
            KTRepeaterDemo.init();
        }
    });
    
    // Ensure repeater is initialized for Hospital CashBack claims
    @if(trim($claims->claim_type) == 'Hospital CashBack' || trim($claims->claim_type) == 'Hospital Cash')
    $(document).ready(function() {
        // Initialize repeater after a short delay to ensure DOM is ready
        setTimeout(function() {
            if (typeof KTRepeaterDemo !== 'undefined') {
                KTRepeaterDemo.init();
            }
        }, 500);
        
        // Re-initialize repeater when tabs are switched (in case repeater is in a tab)
        $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
            setTimeout(function() {
                if (typeof KTRepeaterDemo !== 'undefined') {
                    KTRepeaterDemo.init();
                }
            }, 100);
        });
    });
    @endif
    $('#detailsBtn').click(function ()
    {
        $('#detailDiv').toggle();
        $('#detailsBtn').toggle();
    });
    $('input[name="customer_selected"]').click(function()
    {
        if($(this).prop("checked") == true)
        {
            $('#slocation').attr("required");
            $('#sname').attr("required", "true");
            $('#stype').attr("required", "true");
            $('#vat').attr("required", "true");
        }
        else if($(this).prop("checked") == false)
        {
            $('#slocation').removeAttr("required");
            $('#sname').removeAttr("required");
            $('#stype').removeAttr("required");
            $('#vat').removeAttr("required");
        }
    });
</script>

<script>
    "use strict";
    var KTDatatablesDataSourceAjaxServer = function()
    {
        var initTable2 = function()
        {
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
                ajax: '{!! route("admin.claims.attachmentData",$claims->id) !!}',
                columns: [
                    {data: 'name'},
                    {data: 'document_type_name'},
                    {data: 'type'},
                    {data: 'attachment'},
                    {data: 'actions'},
                ],
            });
        };
        var initTable4 = function()
        {
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
                ajax: '{!! route("admin.claims.coverageData", $claims->id) !!}',
                columns: [
                    {data: 'date'},
                    {data: 'transaction_type'},
                    {data: 'transaction_sub_type'},
                    {data: 'reserve_amts'},
                    {data: 'payment_amts'},
                    {data: 'baln'},
                    {data: 'payee'},
                    {data: 'actions'},
                ],
                "initComplete": function(settings, json) {
                    $.each(json.data, function(index, rowData) {
                        var highlightedRowId = rowData.highlightedRowId;
                        if (typeof highlightedRowId !== 'undefined' && highlightedRowId !== null) {
                            table.DataTable().rows().every(function(rowIdx, tableLoop, rowLoop) {
                                var data = this.data();
                                if (data && data.id == highlightedRowId) {
                                    $(this.node()).addClass('highlighted-row');
                                }
                            });
                        }
                    });
                }
            });
        };
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
    $("form").submit(function()
    {
        $("#sbtBtn").hide();
        $("#loadBtn").show();
    });
</script>

<script>
    $(function () {
        $('body').on('hidden.bs.modal', '.modal', function () {
            $(this).removeData('bs.modal');
        });
    });

    $("#attatchment_table").on("click", "a.confirm-delete" , function(event) {
        event.preventDefault();
        var id = $(this).attr('value');
        $.ajax({
            url: '{{ route('admin.claims.confirm-delete') }}',
            data: {
                "_token": "{{ csrf_token() }}",
                "id":id
            },
            type: 'post',
            datatype : 'json',
            success: function(data) {

                var append = '<div class="modal-header"><h5 class="modal-title" id="exampleModalLabel">Delete </h5>' +
                    '<button type="button" class="close" data-dismiss="modal" aria-label="Close"> <span aria-hidden="true">&times;</span> </button> ' +
                    '</div> ' +
                    '<div class="modal-body"> ' +
                    '<p>'+data.body+'</p> ' +
                    '</div> ' +
                    '<div class="modal-footer"> ' +
                    '<button type="button" class="btn btn-outline-brand" data-dismiss="modal">Close</button> ';
                $('#modal-content-attachment').html(append);
                if(data.status == 'success'){

                    var url = '{{ route("admin.claims.delete", ":id") }}';
                    url = url.replace(':id', data.id);

                    append += '<a href="'+url+'" type="button" class="btn btn-brand">Delete</a></div>';

                }

                append += '</div>';
                $('#modal-content-attachment').empty();
                $('#modal-content-attachment').html(append);
                $('#delete_confirm').modal('show');
            },
        });
    });

</script>

<script>
    /* function for checking sum_assured value */
    $(document).ready(function()
    {
        var ajaxRequest;
        var length = $('.amt').length;

        var productId = parseInt('{{ $policy->product_id }}');

        // var lossreserveTransSubType = $("#lossreserve_trans_subType").val();
        // var transSubType = $("#trans_subType").val();

        if (productId == 7 || productId == 8) {
            $('.amt').keyup(function()
            {
                // if($('#trans_type').val() == '43') // For Loss Payment Only
                // {
                    // var inputElement = this; // Current input field
                    validatePaymentAllocation(this); // Call the validation function
                // }
            });
        } else {
            $('.amt').keyup(function()
            {
                if($('#trans_type').val() == '43') // For Loss Payment Only
                {
                    if (productId === 7 || productId === 8) {
                        // var inputElement = this; // Current input field
                        validatePaymentAllocation(this); // Call the validation function
                    } else {
                        var coverage_count = '{!! json_encode(count($coverages)) !!}';
                        //If Single coverage then balance is Total Balance. For Third Party
                        if(coverage_count == 1)
                            var balance = '{!! json_encode($balance_amt) !!}';
                        else
                            var balance = parseInt($(this).closest('td').prev('td').text());
                        if(parseInt($(this).val()) != "" && parseInt($(this).val()) > balance)
                        {
                            $('#checkSum2').css('display','block');
                            $('.amt').addClass('inputControl');
                            $('#submitReserve').attr("disabled",true);
                            return false;
                        } else {
                            $('#checkSum2').css('display','none');
                            $('#submitReserve').attr("disabled",false);
                            $('.amt').removeClass('inputControl');
                        }
                    }
                }
                var sum = 0;
                $(".amt").each(function()
                {
                    if($(this).val() != "")
                        sum += parseInt($(this).val());
                });
                var value = sum;
                var policy_id = $("#policy_id").val();
                let coverageId = $(this).data('coverage-id') ?? null;

                clearTimeout(ajaxRequest);
                ajaxRequest = setTimeout(function(sn)
                {
                    $.ajax({
                        url: '{{ route('admin.claims.checkSumAssured') }}',
                        data: {
                            "_token": "{{ csrf_token() }}",
                            "sum": value,
                            "policy_id": policy_id,
                            "lossreserveTransSubType": $("#lossreserve_trans_subType").val(),
                            "transSubType": $("#trans_subType").val(),
                            "claim_type" : '{{$claims->claim_type}}',
                            "coverage_id" : coverageId
                        },
                        type: 'post',
                        datatype : 'json',
                        success: function (data)
                        {
                            if(data.error)
                            {
                                $('.sum_value').html(data.value);
                                if(length == 1)
                                {
                                    $('#sumError').css('display','block');
                                } else {
                                    $('#sumError').css('display','none');
                                    $('#sumError2').css('display','block');
                                    $('.amt').addClass('inputControl');
                                }
                                $('#submitReserve').attr("disabled",true);
                            }
                            else
                            {
                                $('#sumError').css('display','none');
                                $('#sumError2').css('display','none');
                                $('#submitReserve').attr("disabled",false);
                                $('.amt').removeClass('inputControl');
                            }
                        }
                    });
                }, 50, value);
            });
        }
    });

    function validatePaymentAllocation(inputElement) {
        // console.log("inputElement",inputElement);

        var closestTr = $(inputElement).closest('tr');
        // console.log("closestTr",closestTr);

        // Extract and clean up the coverage limit value
        var coverageLimitText = closestTr.find('.coverage-limit').text().trim();
        // console.log("coverageLimitText",coverageLimitText);

        var coverageLimit = parseFloat(coverageLimitText.replace(/[^\d.-]/g, ''));
        // console.log("coverageLimit",coverageLimit);

        // Get Payment Allocation entered by the user
        var paymentAllocation = parseFloat($(inputElement).val());

        // Perform validation only for product_id 7 and 8
        var productId = parseInt('{{ $policy->product_id }}'); // Assuming this is set dynamically
        if (productId === 7 || productId === 8) {
            // console.log("paymentAllocation > coverageLimit ",paymentAllocation > coverageLimit);
            // console.log("paymentAllocation, coverageLimit",paymentAllocation, coverageLimit);


            if (paymentAllocation > coverageLimit) {
                // console.log("fun if",inputElement);

                $(inputElement).addClass('inputControl'); // Highlight invalid input
                $('#submitReserve').attr("disabled", true); // Disable submit button
                closestTr.find('.payment-error').html("Payment Allocation cannot exceed Coverage Limit").show();
                $('#checkSum2').css('display','block');
                $('.amt').addClass('inputControl');
            } else {
                // console.log("fun else",inputElement);
                $(inputElement).removeClass('inputControl'); // Remove error highlight
                $('#submitReserve').attr("disabled", false); // Enable submit button
                closestTr.find('.payment-error').html("").hide();
                $('#checkSum2').css('display','none');
                $('.amt').removeClass('inputControl');
            }
        }
    }
</script>

<script>
    /*ajax on radio button for customer */
    $(document).ready(function ()
    {
        var count = 1;
        $('.kt-repeater__add-data').on('click',function()
        {
            count++;
        });
        $('#beneficiary_status_details').on('click', '.check_beneficiary', function()
        {
            var customerbanking ='';
            var otherparty = '{!! json_encode($otherparty) !!}';
            var append='';
            var ajaxRequest;
            var Checkval = $(this).val();
            var claim_id = $('#claim_id').val();
            if (Checkval == 'customer')
            {
                var customer_id = $('#customer_id').val();
                $(this).parent().parent().next().children().children().selectpicker('refresh');            /* refreshing otherparty_list */
                $(this).parent().parent().next().children().empty();                                    /* new_otherparty dropdown is getting empty*/
                var t = $(this);
                ajaxRequest = setTimeout(function(sn)
                {
                    $.ajax({
                        url: '{{ route('admin.claims.getCustomerData') }}',
                        data: {
                            "_token": "{{ csrf_token() }}",
                            "customer_id": customer_id,
                            "claim_id": claim_id,
                        },
                        type: 'post',
                        datatype : 'json',
                        success: function (data)
                        {
                            if( data.length != 0)
                            {
                                customerbanking = data;
                            }
                            t.parent().parent().parent().next().css('display', 'block');               /*Displaying detail_div*/
                            t.parent().parent().parent().next().children().children().children().find("input.accident_billingCell").val(customerbanking.customerbanking.billingCell);
                            t.parent().parent().parent().next().children().children().children().find("input.accident_bankName").val(customerbanking.customerbanking.bankName);
                            t.parent().parent().parent().next().children().children().children().find("input.accident_branchCode").val(customerbanking.customerbanking.branchCode);
                            t.parent().parent().parent().next().children().children().children().find("input.accident_accountNumber").val(customerbanking.customerbanking.accountNumber);
                            var driving_license = customerbanking.kyc.driving_license;
                            var omang = customerbanking.kyc.omang;
                            var proof_residence = customerbanking.kyc.proof_residence;
                            var proof_income = customerbanking.kyc.proof_income;
                            var passport = customerbanking.kyc.passport;
                            if(driving_license != null)
                            {
                                t.parent().parent().parent().next().children().next().next().next().next().next().next().children().find('div.driving_license').empty().html("<div class=\"kt-avatar__holder\" style=\"background-image:url('{!! env('AWS_CLOUDFRONT') !!}'"+driving_license+")\"></div>"+
                                    " <label class=\"kt-avatar__upload\" data-toggle=\"kt-tooltip\" title=\"Change Image\">"+
                                    " <i class=\"fa fa-pen\"></i>"+
                                    " <input type='file' class=\"driving_license\" name=\"driving_license\" accept=\"image/*,application/pdf \" data-msg-accept=\"Only Images and PDF files are allowed\"/>"+
                                    "  </label>"+
                                    " <span class=\"kt-avatar__cancel\" data-toggle=\"kt-tooltip\" title=\"Cancel Image\"> <i class=\"fa fa-times\"></i> </span>\n");
                            }
                            else
                            {
                                t.parent().parent().parent().next().children().next().next().next().next().next().next().children().find("div.driving_license").empty().html("  <div class=\"kt-avatar__holder\" id=\"driving_license\" style=\"background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)\";></div>");
                            }
                            if(omang != null)
                            {
                                t.parent().parent().parent().next().children().next().next().next().next().next().next().children().find("div.omang_pic").empty().html("<div class=\"kt-avatar__holder\" style=\"background-image:url('{!! env('AWS_CLOUDFRONT') !!}'"+omang+")\"></div>"+
                                    " <label class=\"kt-avatar__upload\" data-toggle=\"kt-tooltip\" title=\"Change Image\">"+
                                    " <i class=\"fa fa-pen\"></i>"+
                                    " <input type='file' class=\"driving_license\" name=\"driving_license\" accept=\"image/*,application/pdf \" data-msg-accept=\"Only Images and PDF files are allowed\" />"+
                                    "  </label>"+
                                    " <span class=\"kt-avatar__cancel\" data-toggle=\"kt-tooltip\" title=\"Cancel Image\"> <i class=\"fa fa-times\"></i> </span>\n");
                            }
                            else
                            {
                                t.parent().parent().parent().next().children().next().next().next().next().next().next().children().find("div.omang_pic").empty().html("<div class=\"kt-avatar__holder\" id=\"driving_license\" style=\"background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)\";></div>");
                            }
                            if(proof_residence != null)
                            {
                                t.parent().parent().parent().next().children().next().next().next().next().next().next().children().find("div.proof_residence").empty().html("<div class=\"kt-avatar__holder\" style=\"background-image:url('{!! env('AWS_CLOUDFRONT') !!}'"+proof_residence+")\"></div>"+
                                    " <label class=\"kt-avatar__upload\" data-toggle=\"kt-tooltip\" title=\"Change Image\">"+
                                    " <i class=\"fa fa-pen\"></i>"+
                                    " <input type='file' class=\"driving_license\" name=\"driving_license\" accept=\"image/*,application/pdf \" data-msg-accept=\"Only Images and PDF files are allowed\" />"+
                                    "  </label>"+
                                    " <span class=\"kt-avatar__cancel\" data-toggle=\"kt-tooltip\" title=\"Cancel Image\"> <i class=\"fa fa-times\"></i> </span>\n");
                            }
                            else
                            {
                                t.parent().parent().parent().next().children().next().next().next().next().next().next().children().find("div.proof_residence").empty().html("  <div class=\"kt-avatar__holder\" id=\"driving_license\" style=\"background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)\";></div>");
                            }
                            if(proof_income != null)
                            {
                                t.parent().parent().parent().next().children().next().next().next().next().next().next().children().find("div.proof_income").empty().html("<div class=\"kt-avatar__holder\" style=\"background-image:url('{!! env('AWS_CLOUDFRONT') !!}'"+proof_income+")\"></div>"+
                                    " <label class=\"kt-avatar__upload\" data-toggle=\"kt-tooltip\" title=\"Change Image\">"+
                                    " <i class=\"fa fa-pen\"></i>"+
                                    " <input type='file' class=\"driving_license\" name=\"driving_license\"accept=\"image/*,application/pdf \" data-msg-accept=\"Only Images and PDF files are allowed\"/>"+
                                    "  </label>"+
                                    " <span class=\"kt-avatar__cancel\" data-toggle=\"kt-tooltip\" title=\"Cancel Image\"> <i class=\"fa fa-times\"></i> </span>\n");
                            }
                            else
                            {
                                t.parent().parent().parent().next().children().next().next().next().next().next().next().children().find("div.proof_income").empty().html("  <div class=\"kt-avatar__holder\" id=\"driving_license\" style=\"background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)\";></div>");
                            }
                            if(passport != null)
                            {
                                t.parent().parent().parent().next().children().next().next().next().next().next().next().children().find("div.passport_pic").empty().html("<div class=\"kt-avatar__holder\" style=\"background-image:url('{!! env('AWS_CLOUDFRONT') !!}'"+passport+")\"></div>"+
                                    " <label class=\"kt-avatar__upload\" data-toggle=\"kt-tooltip\" title=\"Change Image\">"+
                                    " <i class=\"fa fa-pen\"></i>"+
                                    " <input type='file' class=\"driving_license\" name=\"driving_license\"accept=\"image/*,application/pdf \" data-msg-accept=\"Only Images and PDF files are allowed\" />"+
                                    "  </label>"+
                                    " <span class=\"kt-avatar__cancel\" data-toggle=\"kt-tooltip\" title=\"Cancel Image\"> <i class=\"fa fa-times\"></i> </span>\n");
                            }
                            else
                            {
                                t.parent().parent().parent().next().children().next().next().next().next().next().next().children().find("div.passport_pic").empty().html("<div class=\"kt-avatar__holder\" id=\"driving_license\" style=\"background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)\";></div>");
                            }
                        }
                    });
                }, 200);
            }
            else
            {
                $(this).parent().parent().next().children().empty();             /*new_otherparty dropdown row is going to empty*/
                append += "<label for='exampleSelect1' style='padding-right: 15px'>Other Party </label>";
                append += "<select class='form-control kt_selectpicker otherparty_list" + count + "'  name='beneficiary_status_details[" + (count-1) + "][otherparty_list]' title='Please select other party'>";
                $.each(JSON.parse(otherparty), function(val, text)
                {
                    append += '<option value="' + text.id + '">' + text.first_name +' '+ text.last_name + '</option>';
                });
                append += '</select>';
                $(this).parent().parent().next().children().append(append);          /* select is appending on new_otherparty dropdown */
                $(this).parent().parent().next().children().css('display','block');     /* Display new_otherparty dropdown*/
                $(this).parent().parent().next().children().children(':last').selectpicker('refresh');   /*refreshing new_otherparty dropdown with count*/
                $(this).parent().parent().parent().next().css('display','none');
            }
        });
        /*ajax on dropdown for otherparty */
        $('#beneficiary_status_details').on('change', '.new_otherparty_dropdown', function()
        {
            var ajaxRequest;
            var claim_id = $('#claim_id').val();
            var otherparty_id = $(this).children().children().children("option:selected").val();
            var t= $(this);
            ajaxRequest = setTimeout(function(sn)
            {
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
                        t.parent().parent().next().children().children().children().find("input.accident_billingCell").val(otherPartyBanking.otherPartyBanking.billingCell);
                        t.parent().parent().next().children().children().children().find("input.accident_bankName").val(otherPartyBanking.otherPartyBanking.bankName);
                        t.parent().parent().next().children().children().children().find("input.accident_branchCode").val(otherPartyBanking.otherPartyBanking.branchCode);
                        t.parent().parent().next().children().children().children().find("input.accident_accountNumber").val(otherPartyBanking.otherPartyBanking.accountNumber);
                        var driving_license = otherPartyBanking.kyc.driving_license;
                        var omang_pic = otherPartyBanking.kyc.omang_pic;
                        var proof_residence = otherPartyBanking.kyc.proof_residence;
                        var proof_income = otherPartyBanking.kyc.proof_income;
                        var passport_pic = otherPartyBanking.kyc.passport_pic;
                        if(driving_license != null)
                        {
                            t.parent().parent().next().children().next().next().next().next().next().next().children().find('div.driving_license').empty().html("<div class=\"kt-avatar__holder\" style=\"background-image:url('{!! env('AWS_CLOUDFRONT') !!}'"+driving_license+")\"></div>"+
                                " <label class=\"kt-avatar__upload\" data-toggle=\"kt-tooltip\" title=\"Change Image\">"+
                                " <i class=\"fa fa-pen\"></i>"+
                                " <input type='file' class=\"driving_license\" name=\"driving_license\"accept=\"image/*,application/pdf \" data-msg-accept=\"Only Images and PDF files are allowed\"/>"+
                                "  </label>"+
                                " <span class=\"kt-avatar__cancel\" data-toggle=\"kt-tooltip\" title=\"Cancel Image\"> <i class=\"fa fa-times\"></i> </span>\n");
                        }
                        else
                        {
                            t.parent().parent().next().children().next().next().next().next().next().next().children().find('div.driving_license').empty().html("  <div class=\"kt-avatar__holder\" id=\"driving_license\" style=\"background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)\";></div>");
                        }
                        if(omang_pic != null)
                        {
                            t.parent().parent().next().children().next().next().next().next().next().next().children().find('div.omang_pic').empty().html("<div class=\"kt-avatar__holder\" style=\"background-image:url('{!! env('AWS_CLOUDFRONT') !!}'"+omang_pic+")\"></div>"+
                                " <label class=\"kt-avatar__upload\" data-toggle=\"kt-tooltip\" title=\"Change Image\">"+
                                " <i class=\"fa fa-pen\"></i>"+
                                " <input type='file' class=\"driving_license\" name=\"driving_license\"accept=\"image/*,application/pdf \" data-msg-accept=\"Only Images and PDF files are allowed\"/>"+
                                "  </label>"+
                                " <span class=\"kt-avatar__cancel\" data-toggle=\"kt-tooltip\" title=\"Cancel Image\"> <i class=\"fa fa-times\"></i> </span>\n");
                        }
                        else
                        {
                            t.parent().parent().next().children().next().next().next().next().next().next().children().find('div.omang_pic').empty().html("  <div class=\"kt-avatar__holder\" id=\"driving_license\" style=\"background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)\";></div>");
                        }
                        if(proof_residence != null)
                        {
                            t.parent().parent().next().children().next().next().next().next().next().next().children().find('div.proof_residence').empty().html("<div class=\"kt-avatar__holder\" style=\"background-image:url('{!! env('AWS_CLOUDFRONT') !!}'"+proof_residence+")\"></div>"+
                                " <label class=\"kt-avatar__upload\" data-toggle=\"kt-tooltip\" title=\"Change Image\">"+
                                " <i class=\"fa fa-pen\"></i>"+
                                " <input type='file' class=\"driving_license\" name=\"driving_license\"accept=\"image/*,application/pdf \" data-msg-accept=\"Only Images and PDF files are allowed\"/>"+
                                "  </label>"+
                                " <span class=\"kt-avatar__cancel\" data-toggle=\"kt-tooltip\" title=\"Cancel Image\"> <i class=\"fa fa-times\"></i> </span>\n");
                        }
                        else
                        {
                            t.parent().parent().next().children().next().next().next().next().next().next().children().find('div.proof_residence').empty().html("  <div class=\"kt-avatar__holder\" id=\"driving_license\" style=\"background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)\";></div>");
                        }
                        if(proof_income != null)
                        {
                            t.parent().parent().next().children().next().next().next().next().next().next().children().find('div.proof_income').empty().html("<div class=\"kt-avatar__holder\" style=\"background-image:url('{!! env('AWS_CLOUDFRONT') !!}'"+proof_income+")\"></div>"+
                                " <label class=\"kt-avatar__upload\" data-toggle=\"kt-tooltip\" title=\"Change Image\">"+
                                " <i class=\"fa fa-pen\"></i>"+
                                " <input type='file' class=\"driving_license\" name=\"driving_license\"accept=\"image/*,application/pdf \" data-msg-accept=\"Only Images and PDF files are allowed\" />"+
                                "  </label>"+
                                " <span class=\"kt-avatar__cancel\" data-toggle=\"kt-tooltip\" title=\"Cancel Image\"> <i class=\"fa fa-times\"></i> </span>\n");
                        }
                        else
                        {
                            t.parent().parent().next().children().next().next().next().next().next().next().children().find('div.proof_income').empty().html("<div class=\"kt-avatar__holder\" id=\"driving_license\" style=\"background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)\";></div>");
                        }
                        if(passport_pic != null)
                        {
                            t.parent().parent().next().children().next().next().next().next().next().next().children().find('div.passport_pic').empty().html("<div class=\"kt-avatar__holder\" style=\"background-image:url('{!! env('AWS_CLOUDFRONT') !!}'"+passport_pic+")\"></div>"+
                                " <label class=\"kt-avatar__upload\" data-toggle=\"kt-tooltip\" title=\"Change Image\">"+
                                " <i class=\"fa fa-pen\"></i>"+
                                " <input type='file' class=\"driving_license\" name=\"driving_license\"accept=\"image/*,application/pdf \" data-msg-accept=\"Only Images and PDF files are allowed\"/>"+
                                "  </label>"+
                                " <span class=\"kt-avatar__cancel\" data-toggle=\"kt-tooltip\" title=\"Cancel Image\"> <i class=\"fa fa-times\"></i> </span>\n");
                        }
                        else
                        {
                            t.parent().parent().next().children().next().next().next().next().next().next().children().find('div.passport_pic').empty().html(" <div class=\"kt-avatar__holder\" id=\"driving_license\" style=\"background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)\";></div>");
                        }
                    }
                });
            }, 200);
            $(this).parent().parent().next().css('display', 'block');               /* Displaying detail_div */
        });
    });
    $( "#attachmentForm" ).submit(function( event ) {
        $('#attachmentloadBtn').show();
        $('#attachmentloadBtn').prop('disabled','true');
        $('#attachmentsbtBtn').hide();
    });
    $("#accidentSupplierForm").submit(function( event ) {
        $('#quoteSbtBtn').hide();
        $('#quoteloadBtn').show();
        $('#quoteloadBtn').prop('disabled','true');
    });
    $("#accidentSupplierFormRepair").submit(function( event ) {
        $('#quoteSbtBtn2').hide();
        $('#quoteloadBtn2').show();
        $('#quoteloadBtn2').prop('disabled','true');
    });
    $( "#reserveForm" ).submit(function( event ) {
        var sum = 0;
        $(".amt").each(function(){
            if($(this).val() != "")
                sum += parseInt($(this).val());
        });
        if(sum == 0)
        {
            $('#checkSum').show();
            $('#submitReserve').css('hidden',false);
            $('#ReserveloadBtn').prop('hidden',true);
            return false;
        }
        else
        {
            $('#submitReserve').hide();
            $('#ReserveloadBtn').show();
            $('#ReserveloadBtn').prop('disabled',true);
            return true;
        }
    });
    $( "#POUpdate" ).submit(function( event ) {
        $('#poSbtBtn').hide();
        $('#toshow').hide();
        $('#poloadBtn').show();
        $('#poloadBtn').prop('disabled','true');
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
    /*validation for paid amount should be less than reserve amount*/
    $(document).ready(function() {
    $('#paid_amount').keyup(function () {
        var reserve_amount = $('#reserve_amount').val();
        var paid_amount = $('#paid_amount').val();
        if(paid_amount > reserve_amount){
            $( "#submitBtn" ).prop( "disabled", true );
            $('#paidmsg').html('Paid amount should be less than reserve amount');
            $('#paidmsg').css('display', 'block');
        }
        else{
            $( "#submitBtn" ).prop( "disabled", false );
            $('#paidmsg').css('display', 'none');
        }
    });
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
        $('#detailDiv').on('change','.make', function(e) {
            /* alert($(this).parent().parent().next().children().attr('class'));*/
            var make = $(this).val();
            var $c = $(this);
            var append = '';
            ajaxRequest = setTimeout(function(sn) {
                $.ajax({
                    url: '{{ route('admin.claims.checkMakeModel') }}',
                    async: false,
                    cache: false,
                    data: {
                        "_token": "{{ csrf_token() }}",
                        "make": make,
                    },
                    type: 'post',
                    datatype : 'json',
                    success: function (data) {
                        if (data) {
                            console.log(data);
                            /*$('.makerow').empty();*/
                            append += '<div class="form-group">';
                            append += '<label>Modelll </label>';
                            append += '<select class="form-control kt_selectpicker model" name="thirdMemberModel[]" data-live-search="true" title="Please choose model">';
                            $.each(data.count, function(key, modal)
                            {
                                append += '<option value="' + modal + '">' + modal + '</option>';
                            });
                            append += '</select></div>';
                            $c.parent().parent().next().children().replaceWith(append);
                        }
                        else {
                            append += '<h3>No data is available</h3>';
                        }
                    }
                });
                e.stopImmediatePropagation();
                return false;
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
                            $('#mail').prop("disabled",true);
                        }
                        else {
                            $('.attorneyMsg').attr("hidden",false);
                            $('.attorneyMsg').html(data.msg);
                            $('#mail').prop("disabled",false);
                        }
                        if($('.attorneyValue').prop("checked") == false){
                            $('.attorneyMsg').attr("hidden",true);
                            $('#mail').prop("disabled",false);
                        }
                    }
                });
            }, 50);
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
                    url: '{{ route('admin.claims.get_oldMakeModel') }}',
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

<script>
    $(document).ready(function() {
        $("#POUpdate").on("submit",function(e) {
            var length  = $('#POimage').val();
            if (length == 0) {
                $('.notiMsg').css('display','block');
                return false; // cancel submit
            }else{
                $('.cancel ').css('display','none');
                $('.notiMsg').css('display','none');
                $('#PosbtBtn').css('display','none');
                $('#PoloadBtn').prop('disabled',true);
                $('#PoloadBtn').css('display','block');
                return true; // allow submit
            }
        });
    });


    $(document).ready(function () {

    $('#closeStore').validate({ // initialize the plugin
        ignore: [],
        ignore: ":hidden",
        rules: {
            document_1: {
                required: true,
            },

        }
    });

});

      /*assessor validation*/
    //   var poControl = function () {
    //         var po = function () {
            //     $( "#closeStore" ).validate({
            //         // define validation rules
            //         rules: {
            //             document_1:{
            //                 required: true
            //             },
            //         },
            //         //display error alert on form submit
            //         invalidHandler: function(event, validator) {
            //             $('html, body').animate({
            //                 scrollTop: $(validator.errorList[0].element).offset().top - 200
            //             }, 1000);
            //             $('#poSbtBtn').show();
            //             $('#poloadBtn').hide();
            //         },
            //         submitHandler: function (form) {
            //             $('#poSbtBtn').hide();
            //             $('#poloadBtn').show();
            //             form[0].submit(); // submit the form
            //         }
            //     });
            // }
            // return {
            //     // public functions
            //     init: function() {
            //         po();
            //     }
            // };
        // }();
</script>
<script>
 jQuery(document).ready(function() {
    "use strict";
    var KTDatatablesDataSourceAjaxServer = function() {
        var table = '';
        var initTable1 = function() {
            table = $('#region_table').DataTable({
                responsive: true,
                searchDelay: 500,
                language:{
                    processing : "<img src='{{asset('img/loading.gif')}}'>"
                },
                processing: true,
                serverSide: true,
                columnDefs: [
                    {
                        "targets": [ 0 ],
                        "visible": false,
                        "searchable": false
                    }
                ],
               ajax: {
                    url: '{!! route('admin.getclaimsActivityLogRecordes', $claims->policy_id) !!}',
               },
                order: [0, 'DESC'],
                columns: [
                        {
                            data: 'id'
                        },
                        {
                            data: 'user_id'
                        },
                        {
                            data: 'ip_address'
                        },
                        {
                            data: 'tag'
                        },
                        {
                            data: 'old_values'
                        },
                        {
                            data: 'new_values'
                        },
                        {
                            data: 'created_at'
                        },
                    ],
            });
        };
        return {
            //main function to initiate the module
            init: function() {
                initTable1();
            },
            draw: function(){
                table.draw();
            }
        };
    }();

    jQuery(document).ready(function() {
        KTDatatablesDataSourceAjaxServer.init();
    });
});

$(document).ready(function() {

$('#is_imported').on('change', function() {
    $("#make_rerate").html('');
    $("#make_rerate").empty();
    $("#make_rerate").selectpicker('refresh');

    $("#model_rerate").html('');
    $("#model_rerate").empty();
    $("#model_rerate").selectpicker('refresh');

    getVehicleMakes(this.value);
});

function getVehicleMakes(e) {
    var baseURL = '{{ env('GRAPHITE_URL') }}';
    if (e == 'Yes') {
        var fetchURL = baseURL + 'api/frontendpay/vehicleMake'
    } else {
        var fetchURL = baseURL + 'api/frontendpay/getTTVehicleMakes'
    }

    $.ajax({
        type: "POST",
        datatype: 'json',
        url: fetchURL,
        dataType: "json",
        beforeSend: function() {
            // $("#loader").show();
        },
        success: function(data) {
            $("#loader").hide();
            $("#make_rerate").html('');
            $("#make_rerate").empty();
            $("#make_rerate").append('<option value="">Select vehicle make</option>');
            if (e == 'Yes') {
                $.each(data.makes, function() {
                    $("#make_rerate").append('<option value="' + this.s_Make +
                        '">' + this.s_Make + '</option>')
                });
                console.log(data.makes);
            } else {
                var makes = data.Makes;
                $.each(makes, function(key, val) {
                    var makes = $('<option value="' + val + '">' + val +
                        '</option>');
                    $("#make_rerate").append(makes);
                });
            }
            //$('#year_rerate').prop('selectIndex', 0);
            $('#make_rerate').selectpicker('refresh');
            $('#year_rerate').selectpicker('refresh');
        }
    });
}

$('#year_rerate').on('change', function() {
    var status = $('#is_imported').val();
    var make = $('#make_rerate').val();
    var year = this.value;

    $("#model_rerate").html('');
    $("#model_rerate").empty();
    $("#model_rerate").selectpicker('refresh');
    //document.getElementById("year_rerate").selectedIndex = "0";
    $('#estimatedValue').val('');

    getVehicleModels(status, make, year);
});

$('#make_rerate').on('change', function() {
    $("#model_rerate").html('');
    $("#model_rerate").empty();
    $("#model_rerate").selectpicker('refresh');
    //$("select#year_rerate")[0].selectedIndex = 0;
    $('#estimatedValue').val('');

    var status = $('#is_imported').val();
    var make = this.value;
    var year = $('#year_rerate').val();
    //getVehicleModels(status,make,year);
});

function getVehicleModels(status, make, year) {
    $('#estimatedValue').val('');
    var baseURL = '{{ env('GRAPHITE_URL') }}';
    if (status == 'Yes') {
        var fetchURL = baseURL + 'api/frontendpay/vehicleModel'
    } else {
        var fetchURL = baseURL + 'api/frontendpay/getTTVehicleModels'
    }
    if (status == 'Yes') {
        $.ajax({
            type: "POST",
            datatype: 'json',
            url: fetchURL,
            data: {
                vehicle_make: make,
            },
            dataType: "json",
            beforeSend: function() {
                $("#loader").show();
                $("#model_rerate").empty();
                $('#make_rerate').selectpicker('refresh');
            },
            success: function(responseData) {
                $("#loader").hide();
                $("#model_rerate").html('');
                $("#model_rerate").empty();
                $('#make_rerate').selectpicker('refresh');
                $("#model_rerate").append('<option value="">Select Model</option>');
                $.each(responseData.makes, function() {
                    $option = $('<option value="' + this.s_Variant + '">' + this
                        .s_Variant + '</option>');
                    $("#model_rerate").append($option);
                });
                $("#model_rerate").selectpicker('refresh');
                $('#year_rerate').val(year);
                $("#year_rerate").selectpicker('refresh');
            },
            complete: function() {
                $("#loader").hide();
            }
        });
    } else {
        $.ajax({
            type: "POST",
            datatype: 'json',
            url: fetchURL,
            data: {

                make: make,
                manufacturing_year: year
            },
            dataType: "json",
            beforeSend: function() {
                $("#loader").show();
                $("#model_rerate").empty();
                $("#model_rerate").selectpicker('refresh');
            },
            success: function(responseData) {
                $("#loader").hide();
                $("#model_rerate").html('');
                $("#model_rerate").empty();
                $("#model_rerate").append('<option value="">Select vehicle model</option>');
                $.each(responseData.Models, function() {
                    $option = $('<option value="' + this.Model +
                        '" data-vehicle="' + this.IntroYear +
                        '" data-vehicle-disc="' + this.DisconYear + '" >' + this
                        .Model + '</option>');
                    $("#model_rerate").append($option);
                });
                $("#model_rerate").selectpicker('refresh');
            },
            error: function(data) {
                $option = $('<option value="" disabled>No vehicle found</option>');
                $("#model_rerate").append($option);
            },
            complete: function() {
                $("#loader").hide();
            }
        });
    }
}

$('#model_rerate').on('change', function() {

    $('#estimatedValue').val('');

    var baseURL = '{{ env('GRAPHITE_URL') }}';
    $('#estimatedValue').val('');
    if (this.value && $('#is_imported').val() == 'No') {
        $.ajax({
            type: "POST",
            datatype: 'json',
            url: baseURL + 'api/frontendpay/getTTValue',
            data: {
                vehicleMake: $('#make_rerate').val(),
                vehicleModel: $(this).val(),
                manufacturing_year: $('#year_rerate').val(),
                condition: 'EX',
                mileage: 'LO',
            },
            dataType: "json",
            beforeSend: function() {
                $('#valueLoader').show();
                $('#estimatedValue').hide();
                $('#ratingsCalculation').prop('disabled', true);
            },
            success: function(data) {
                $('#valueLoader').hide();
                $('#estimatedValue').hide();
                if (data.value) {
                    var val = (data.value).toFixed(2)
                    $('#estimatedValue').val(val);
                    $('#ratingsCalculation').prop('disabled', false);
                    if (data.value > 500000) {
                        $('#ratingsCalculation').prop('disabled', true);
                        $("#ratingsCalculation").hide();
                        $("#requestcallback").show();
                    }
                }

            },



            error: function() {
                $('#valueLoader').hide();
                $('#estimatedValue').show();
                $('#estimatedValue').val('');
                $('#ratingsCalculation').prop('disabled', false);
            },
            complete: function() {
                $('#valueLoader').hide();
                $('#ratingsCalculation').prop('disabled', false);
                $('#estimatedValue').show();
            },
        });
    }
});

//$('#year_rerate').prop('value', vehicle_year);
$('#year_rerate').selectpicker('refresh');
});
</script>
<script>

    $(document).on("click", ".voidPaymentBtn", function() {
        var rowId = $(this).data("row-id");

        var routeUrl = "{!! route('admin.claims.getReserveDeatils', ':rowId') !!}";
        routeUrl = routeUrl.replace(':rowId', rowId);

        $.ajax({
            url: routeUrl,
            type: 'GET',
            dataType: 'json',
            beforeSend: function() {
                $("#loader").show();
            },
            success: function(response) {
                console.log("data ",response);
                $("#claimReserveCoverageId").val(response.data.id);
                $("#reference_no").text(response.data.claimNumber);
                $("#date_of_payment").text(response.data.date);
                $("#transaction_type").text(response.data.transaction_type);
                $("#transaction_sub_type").text(response.data.transaction_sub_type);
                $("#description_of_payment").text(response.data.description);
                $("#memo_on_check").text(response.data.memo);
                $("#payee_name").text(response.data.payee_name);
                // $("#check_number").text(response.data);
                // $("#printed").text(response.data);
                // $("#printed_date").text(response.data);
                // $("#printed_by").text(response.data);
                // $("#payment_status").text(response.data);
                // $("#approved_by").text(response.data.approved_by);
                // $("#approved_date").text(response.data);
                $("#voidPaymentDetailsModal").modal("show");
                $("#loader").hide();
            },
            error: function(xhr, status, error) {
                console.error(xhr.responseText);
            }
        });
    });

    $(document).on("click", ".confirmVoidPaymentBtn", function() {
        $("#voidPaymentStore").modal("show");
    });

    // $(document).on("click", ".voidPaymentInfoBtn", function() {
    //     $.ajax({
    //         url: "{{ route('admin.claims.voidPayment') }}",
    //         type: 'POST',
    //         dataType: 'json',
    //         beforeSend: function() {
    //             $("#loader").show();
    //         },
    //         success: function(response) {
    //             // $("#referenceNo").text(response.data.claimNumber);
    //             // $("#payment_void_date").text(response.data.payment_void_date);
    //             // $("#voided_amount").text(response.data.amount);
    //             // $("#payment_void_by").text(response.data.payment_void_by);
    //             // $("#voidedPaymentInfoModal").modal("show");
    //             $("#loader").hide();
    //         },
    //         error: function(xhr, status, error) {
    //             console.error(xhr.responseText);
    //         }
    //     });
    // });

    $(document).on("click", ".voidPaymentInfoBtn", function() {
        var rowId = $(this).data("row-id");

        var routeUrl = "{!! route('admin.claims.getVoidPaymentInfo', ':rowId') !!}";
        routeUrl = routeUrl.replace(':rowId', rowId);

        $.ajax({
            url: routeUrl,
            type: 'GET',
            dataType: 'json',
            beforeSend: function() {
                $("#loader").show();
            },
            success: function(response) {
                $("#referenceNo").text(response.data.claimNumber);
                $("#payment_void_date").text(response.data.payment_void_date);
                $("#voided_amount").text(response.data.amount);
                $("#payment_void_by").text(response.data.payment_void_by);
                $("#reason_for_void_payment").text(response.data.reason_for_void);
                $("#voidedPaymentInfoModal").modal("show");
                $("#loader").hide();
            },
            error: function(xhr, status, error) {
                console.error(xhr.responseText);
            }
        });
    });

    $(document).ready(function () {
        $('#addComplaintLog').validate({ // initialize the plugin
            ignore: [],
            ignore: ":hidden",
            rules: {
                complaint_of: {
                    required: true,
                },
                complaint_details: {
                    required: true,
                },
            }
        });
    });

    jQuery(document).ready(function() {
       "use strict";
       var KTDatatablesDataSourceAjaxServer = function() {
           var table = '';
           var initClaimComplaintTable = function() {
               table = $('#complaint_table').DataTable({
                   responsive: true,
                   searchDelay: 500,
                   language:{
                       processing : "<img src='{{asset('img/loading.gif')}}'>"
                   },
                   processing: true,
                   serverSide: true,
                   columnDefs: [
                       {
                           "targets": [ 0 ],
                           "visible": false,
                           "searchable": false
                       }
                   ],
                  ajax: {
                       url: '{!! route('admin.getClaimComplaintLogs', $claims->id) !!}',
                  },
                   order: [0, 'DESC'],
                   columns: [
                           {
                               data: 'id'
                           },
                           {
                               data: 'added_by'
                           },
                           {
                               data: 'complaint_of'
                           },
                           {
                               data: 'complaint_details'
                           },
                           {
                               data: 'created_at'
                           },
                       ],
               });
           };
           return {
               //main function to initiate the module
               init: function() {
                   initClaimComplaintTable();
               },
               draw: function(){
                   table.draw();
               }
           };
       }();

       jQuery(document).ready(function() {
           KTDatatablesDataSourceAjaxServer.init();
       });
   });

</script>
</body>
</html>
