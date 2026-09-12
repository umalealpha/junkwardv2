<!DOCTYPE html>
<html lang="en" >
@include('admin.layouts.header')

<link href="{{  asset('assets/vendors/custom/datatables/datatables.bundle.css') }}" rel="stylesheet" type="text/css" />
<link href="https://cdn.datatables.net/buttons/1.6.0/css/buttons.dataTables.min.css" rel="stylesheet" type="text/css" />
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
                        Apply Custom Rate
                    </h3>
                    <span class="kt-subheader__separator kt-hidden"></span>
                    <div class="kt-subheader__breadcrumbs">
                        <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                        <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin-dashboard')}}" class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span class="kt-subheader__breadcrumbs-separator"></span>
                        <a class="kt-subheader__breadcrumbs-separator"><span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">Policies</span> </a>
                        <span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">Reinstate Policy</span> </a>
                    </div>
                    <span class="kt-subheader__separator kt-hidden"></span>
                    <div>
                        <a class="btn btn-info" href="{{ URL::previous() }}" style="margin-left: 1130px;" >Back</a>
                    </div>
                </div>
            </div>
            <!-- end:: Subheader -->

            <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
                <div class="kt-portlet kt-portlet--mobile">
                    <div class="kt-portlet__body">
                        <div style="margin-bottom:2%" id="rerate_div">
                            <form id="updatePremiumDiscSurc" action="{{ route('admin.policy.customPolicyDiscountSurcharge') }}"
                                  method="POST" enctype="multipart/form-data" class="kt-form">
                                <!-- CSRF Token -->
                                <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                                <input type="hidden" name="policyId" value="{{ $policy->id }}" />
                                <input type="hidden" name="user" value="{{auth()->user()->id}}" />
                                @if (isset($premium->new_value))
                                    <input type="hidden" name="annual_premium_rerate" value="{{$premium->new_value}}" />
                                @else
                                    <input type="hidden" name="annual_premium_rerate" value="{{$total_premium}}" />
                                @endif

                                <table class="table table-striped table-bordered table-hover table-checkable" id="custom_discount_surcharge_div">
                                    <thead>
                                        <tr>
                                            <th>Please select value type:</th>
                                            <td width="50%">
                                                <select class="form-control kt_selectpicker" name="value_type"
                                                    title="Please select value type" data-live-search="true"
                                                    id="cus_dis_sur_value_type">
                                                    <option value="1">Flat value</option>
                                                    <option value="2">Percent(%) value</option>
                                                </select>
                                                <span id="cus_dis_sur_value_typeError" class="error"
                                                    style="display: none; font-size: 12px; color:red;">This field is
                                                    required.</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Value:</th>
                                            <td width="50%">
                                                <input type="text" class="form-control" name="value" id="cus_dis_sur_value"
                                                    title="Enter the value" placeholder="Enter value" />
                                                <span id="cus_dis_sur_valueError" class="error"
                                                style="display: none; font-size: 12px; color:red;">This field is
                                                required.</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Reason:</th>
                                            <td width="50%">
                                                <input type="text" class="form-control" name="reason" id="cus_dis_sur_reason"
                                                    title="Please provide the reason" placeholder="Please provide reason" />
                                                <span id="cus_dis_sur_reasonError" class="error"
                                                style="display: none; font-size: 12px; color:red;">This field is
                                                required.</span>
                                                {{-- <span id="reasonError" class="error" style="display: none; font-size: 12px; color:red;">This field is required.</span> --}}
                                            </td>
                                        </tr>
                                    </thead>
                                </table>
                                <div class="kt-form__actions">
                                    <div class="row">
                                        <div class="col-5"></div>
                                        <div class="col-7">
                                            <button type="submit" class="btn btn-info">Submit</button>
                                            <p class="btn btn-secondary" id="rerate_hide" style="margin-top:2%">Cancel</p>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <!--begin::Section-->
                        <table class="table table-striped table-bordered table-hover table-checkable" id="discount_surcharge_policy_table">
                            <thead>
                            <tr>
                                <th>Policy Number</th>
                                <th>Custom Rate Percentage</th>
                                <th>Custom Rate Flat</th>
                                <th>Old Value</th>
                                <th>New Value</th>
                                <th>User Name</th>
                                <th>Created At</th>
                            </tr>
                            </thead>
                        </table>

                    </div>
                </div>
            </div>

        </div>
</div>
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

@include('admin.layouts.scripts')


<script src="{{  asset('assets/vendors/custom/datatables/datatables.bundle.js') }}" type="text/javascript"></script>
<script src="https://cdn.datatables.net/buttons/1.6.0/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/1.6.0/js/buttons.html5.min.js"></script>

<script src="{{  asset('assets/vendors/general/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}" type="text/javascript"></script>
<script src="{{  asset('assets/vendors/custom/components/vendors/bootstrap-datepicker/init.js') }}" type="text/javascript"></script>

<script>
 "use strict";
    var KTDatatablesDataSourceAjaxServer = function() {

        var table = '';
        var initTable235 = function()
        {
            var table = $('#discount_surcharge_policy_table');
            // begin first table
            table.DataTable({
                responsive: true,
                searchDelay: 500,
                language:{
                    processing : "<img src='{{asset('img/loading.gif')}}'>"
                },
                processing: true,
                serverSide: true,
                ajax: "{!! route('admin.policy.discountSurchargePolicyTable',$policy->id) !!}",
                columns: [
                    {data: 'policy_id'},
                    {data: 'custom_rate_per'},
                    {data: 'custom_rate_flat'},
                    {data: 'old_value'},
                    {data: 'new_value'},
                    {data: 'user_id'},
                    {data: 'created_at'},
                ],
                error: function(error) {
                    console.log(error);
                },
            });
        };

        return {
                //main function to initiate the module
                init: function () {
                    initTable235();
                },
                draw: function () {
                    table.draw();
                }
            };
    }();

    jQuery(document).ready(function() {
        KTDatatablesDataSourceAjaxServer.init();
    });


        $("#discount_surcharge_policy_table").on("click", "a.discount-surcharge-confirm-delete" , function(event) {
        event.preventDefault();
        var id = $(this).attr('value');
        $.ajax({
            url: '{{ route('admin.policy.discount-surcharge-policy-confirm-delete') }}',
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
                $('#modal-content-discount-surcharge-policy').html(append);
                if(data.status == 'success'){
                    var url = '{{ route("admin.policy.discount_surcharge_policy_delete", ":id") }}';
                    url = url.replace(':id', data.id);

                    append += '<a href="'+url+'" type="button" class="btn btn-brand">Delete</a></div>';
                }
                append += '</div>';
                $('#modal-content-discount-surcharge-policy').empty();
                $('#modal-content-discount-surcharge-policy').html(append);
                $('#delete_confirm_discount_surcharge').modal('show');
            },
        });
    });
</script>

</body>
<!-- end::Body -->
</html>
