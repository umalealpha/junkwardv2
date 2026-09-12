<!DOCTYPE html>
<html lang="en" >
@include('admin.layouts.header')
<link href="{{  asset('assets/vendors/custom/datatables/datatables.bundle.css') }}" rel="stylesheet" type="text/css" />
<link href="{{  asset('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}" rel="stylesheet" type="text/css" />


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
    <!--If Password default, show edit details -->
    @if (Auth::user()->password == null)
        @include('includes.reset')
    @else
        <div class="kt-grid__item kt-grid__item--fluid kt-grid kt-grid--hor">
            <!-- begin:: Subheader -->
            <div class="kt-subheader kt-grid__item" id="kt_subheader">
                <div class="kt-subheader__main">
                    <h3 class="kt-subheader__title">
                        Policy Schedule
                    </h3>
                    <span class="kt-subheader__separator kt-hidden"></span>
                    <div class="kt-subheader__breadcrumbs">
                        <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                        <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin-dashboard')}}" class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span class="kt-subheader__breadcrumbs-separator"></span>
                        <a class="kt-subheader__breadcrumbs-separator"><span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">Schedule</span> </a>
                        <a class="kt-subheader__breadcrumbs-separator"><span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">Motor Comprehensive</span> </a>
                    </div>
                </div>
            </div>
            <!-- end:: Subheader -->

            <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
                <div class="kt-portlet kt-portlet--mobile">
                    <div class="kt-portlet__body">
                        <!--begin: Datatable -->
                        <!-- begin:: Content -->
                        <form id="updateCustomerKYC" action="{{ route('admin.customer.verifyKYCInfo') }}"
                              method="POST" enctype="multipart/form-data" class="kt-form">
                            <!-- CSRF Token -->
                            <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                            <input type="hidden" name="data_id" value="{{ $data->id }}" />
                            <table class="table table-striped table-bordered table-hover table-checkable" id="policy_table">
                                <thead>
                                <tr>
                                    <th>Name:</th>
                                    <td>{{ $customer->firstName.' '.$customer->middleName.' '.$customer->lastName }}</td>
                                </tr>
                                <tr>
                                    <th>Omang Number:</th>
                                    @if($data->omangNumber)
                                        <td>{{ $data->omangNumber }}</td>
                                    @else
                                        <td>N/A</td>
                                    @endif
                                </tr>
                                <tr>
                                    <th>Passport Number:</th>
                                    @if($data->passportNumber)
                                        <td>{{ $data->passportNumber }}</td>
                                    @else
                                        <td>N/A</td>
                                    @endif

                                </tr>
                                <tr>
                                    <th>Omang Front:</th>
                                    @if($data->omang)
                                        {{--<td>
                                            <a href ="{{ $data->omang }}" target= "_blank">
                                                <div style="height:100px;width:100px; background-image: url('{{ $data->omang }}')">

                                                </div>
                                            </a>
                                        </td>--}}
                                        <td><a href ="{{ $data->omang }}" target= "_blank"> Omang Front Picture Download link </a></td>
                                    @else
                                        <td>Not Uploaded</td>
                                    @endif
                                </tr>
                                <tr>
                                    <th>Omang Back:</th>
                                    @if($data->omangBack)
                                        {{-- <td>
                                             <a href ="{{ $data->omang }}" target= "_blank">
                                                 <div style="height:100px;width:100px; background-image: url('{{ $data->omang }}')">

                                                 </div>
                                             </a>
                                         </td>--}}
                                        <td><a href ="{{ $data->omangBack }}" target= "_blank"> Omang Back Picture Download link </a></td>
                                    @else
                                        <td>Not Uploaded</td>
                                    @endif
                                </tr>
                                <tr>
                                    <th>Passport:</th>
                                    @if($data->passport)
                                        <td><a href ="{{ $data->passport }}" target= "_blank"> Passport Picture Download link </a></td>
                                    @else
                                        <td>Not Uploaded</td>
                                    @endif
                                </tr>
                                <tr>
                                    <th>Drivers License Front:</th>
                                    @if($data->driversLicense)
                                        <td><a href ="{{ $data->driversLicense }}" target= "_blank"> Drivers License Front Picture Download link </a></td>
                                    @else
                                        <td>Not Uploaded</td>
                                    @endif
                                </tr>
                                <tr>
                                    <th>Drivers License Back:</th>
                                    @if($data->driversLicense_back)
                                        <td><a href ="{{ $data->driversLicense_back }}" target= "_blank"> Drivers License Back Picture Download link </a></td>
                                    @else
                                        <td>Not Uploaded</td>
                                    @endif
                                </tr>
                                <tr>
                                    <th>Proof Of Residence:</th>
                                    @if($data->proofResidence)
                                        <td><a href ="{{ $data->proofResidence }}" target= "_blank"> Proof Of Residence Picture Download link </a></td>
                                    @else
                                        <td>Not Uploaded</td>
                                    @endif
                                </tr>
                                <tr>
                                    <th>Proof Of Income:</th>
                                    @if($data->proofIncome)
                                        <td><a href ="{{ $data->proofIncome }}" target= "_blank"> Proof Of Income Picture Download link </a></td>
                                    @else
                                        <td>Not Uploaded</td>
                                    @endif
                                </tr>
                                <tr>
                                    <th>Compliance:</th>
                                    @if($data->compliance == 0)
                                        <td style="color:red">No</td>
                                    @endif
                                    @if($data->compliance == 1)
                                        <td style="color:green">Yes</td>
                                    @endif

                                </tr>
                                <tr>
                                    <th>Document Uploaded On:</th>
                                    @if($data->created_at)
                                        <td>{{ $data->created_at }}</td>
                                    @else
                                        <td>N/A</td>
                                    @endif
                                </tr>
                                <tr>
                                    <th>Document Updated On:</th>
                                    @if($data->updated_at)
                                        <td>{{ $data->updated_at }}</td>
                                    @else
                                        <td>Never Updated</td>
                                    @endif
                                </tr>
                                <tr>
                                    <th>Status:</th>
                                    <td>
                                        <select class="form-control kt_selectpicker"
                                                title="Please choose status"
                                                name="status">
                                            <option value="Approve" @if($data->compliance != 1) disabled @endif>Approve</option>
                                            <option value="Unapprove">Unapprove</option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Remark:</th>
                                    <td>
                                        <textarea  class="form-control" name="remark" placeholder="Please mention remark if any">@if($data->remark != null) {{ $data->remark }} @endif</textarea>
                                    </td>
                                </tr>
                                </thead>
                            </table>
                            <div class="kt-portlet__foot kt-portlet__foot--solid">
                                <div class="kt-form__actions">
                                    <div class="row">
                                        <div class="col-5"></div>
                                        <div class="col-7">
                                            <button type="submit" value="Submit" id="submitbtn" class="btn btn-brand">Update</button>
                                            <button class="btn btn-brand" type="button" id="loadBtn" style="display:none" > <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Loading... </button>
                                            <a class="btn btn-secondary" href="{{ URL::to('admin/customerKyc') }}" >Cancel</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                        <!--end: Datatable -->
                    </div>
                </div>
            </div>
            <!-- end:: Content -->
        </div>
@endif
<!-- begin:: Footer -->
@include('includes.footer')
<!-- end:: Footer -->
</div>
<!-- end:: Wrapper -->
</div>
<!-- end:: Page -->
</div>
<!-- end:: Root -->



<!-- begin:: Scrolltop -->
<div id="kt_scrolltop" class="kt-scrolltop"> <i class="la la-arrow-up"></i> </div>
<!-- end:: Scrolltop -->
<div class="modal fade" id="claimTypeModal" tabindex="-1" role="dialog" aria-labelledby="user_delete_confirm_title" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content" id="modal-content">
            <div class="modal-header"><h5 class="modal-title" id="exampleModalLabel">Type of Claim</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"> <span aria-hidden="true">&times;</span> </button>
            </div>
            <div class="modal-body">
                <h5>What type of Claim you want to process ?</h5>
                <select name="type" class="form-control" id="type">
                    <option value="0">Please select claim type</option>
                </select>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-brand" data-dismiss="modal">Close</button>
                <a href="" id="submitType" type="button" class="btn btn-brand">Confirm</a></div>
        </div>
    </div>
</div>
</div>
@include('admin.layouts.scripts')


<script src="{{  asset('assets/vendors/custom/datatables/datatables.bundle.js') }}" type="text/javascript"></script>
<script>

    $(function () {
        $('body').on('hidden.bs.modal', '.modal', function () {
            $(this).removeData('bs.modal');
        });

    });
    $("#policy_table").on("click", "a.claimTypeModal" , function(event) {
        event.preventDefault();

        $("#type").find("option:gt(0)").remove();
        var count = 0;
        var type;

        if($(this).is(".life")) {
            $("#type").append($("<option></option>").attr("value", "Life").text("Life"));
            count = count + 1;
            type = 'Life';
        }
        if($(this).is(".glass")) {
            $("#type").append($("<option></option>").attr("value", "Glass").text("Glass"));
            count = count + 1;
            type = 'Glass';
        }
        if($(this).is(".accident")) {
            $("#type").append($("<option></option>").attr("value", "Accident").text("Motor Accident"));
            count = count + 1;
            type = 'Accident';
        }

        //If Only one option then don't open modal pop up
        if(count == 1)
        {
            window.location.href = $(this).attr('href')+ '/' + type;
        } else {
            $("#submitType").attr("href", $(this).attr('href'));
            $('#claimTypeModal').modal('show');
        }
    });

    $('#type').on('change', function() {

    });

    $('#submitType').click(function(e) {

        if($('#type').val() == 0)
        {
            alert('Please select Claim Type');
            return false;
        }
        var _href = $("#submitType").attr("href");
        $("#submitType").attr("href", _href + '/' + $('#type').val());

    });

    jQuery(document).ready(function() {
        KTDatatablesDataSourceAjaxServer.init();
    });
    $('#policyStatus_filter').on('change',function(){
        policyStatus_filter = $(this).val();
        KTDatatablesDataSourceAjaxServer.draw();
    });

    $('#product_filter').on('change',function(){
        product_filter = $(this).val();
        KTDatatablesDataSourceAjaxServer.draw();
    });

</script>
<script src="{{  asset('assets/vendors/general/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}" type="text/javascript"></script>
<script src="{{  asset('assets/vendors/custom/components/vendors/bootstrap-datepicker/init.js') }}" type="text/javascript"></script>
<script>
    $(document).ready(function () {
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
        jQuery(document).ready(function() {
            KTBootstrapDatepicker.init();
        });
    });
</script>


</body>
<!-- end::Body -->
</html>
