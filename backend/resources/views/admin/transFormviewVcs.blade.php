<!DOCTYPE html>
<html lang="en" >

@include('admin.layouts.header')
<link href="{{ asset('assets/vendors/general/tether/dist/css/tether.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ asset('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}" rel="stylesheet" type="text/css" />
<link href="{{  asset('assets/vendors/custom/datatables/datatables.bundle.css') }}" rel="stylesheet" type="text/css" />
<link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">

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
     <!-- check if is first time login -->

    <div class="kt-grid__item kt-grid__item--fluid kt-grid kt-grid--hor">
        <!-- begin:: Subheader -->
        <div class="kt-subheader kt-grid__item" id="kt_subheader">
            <div class="kt-subheader__main">
                <h3 class="kt-subheader__title">
                    VCS Reconsilation
                </h3>
                <span class="kt-subheader__separator kt-hidden"></span>
                <div class="kt-subheader__breadcrumbs">
                    <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                    <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin-dashboard')}}" class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{  URL::to('admin/user') }}" class="kt-subheader__breadcrumbs-link"> Sales </a> <span class="kt-subheader__breadcrumbs-separator"></span>
                    <span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">CSV Import</span>
                </div>
            </div>
        </div>
        <!-- end:: Subheader -->
        <!-- begin:: Content -->
        <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
            <!--begin::Portlet-->
            <div class="kt-portlet">
                <!--begin::Form-->
                <form id="transFormVcsSave" action="{{ route('storeTransFormVcs') }}" method="POST" enctype="multipart/form-data" class="kt-form">
                    <!-- CSRF Token -->
                    <input type="hidden" name="_token" value="{{ csrf_token() }}" />


                    <div class="kt-portlet__body">
                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Sample File:</label>
                            <div class="col-9">
                                <a href="{{ URL::to('admin/reconsilation/vcsExport') }}" class="btn btn-sm btn-elevate btn-brand btn-elevate" id="" data-toggle="kt-tooltip" title="" data-placement="left" data-original-title="Download"> <span class="kt-opacity-11" id="">Download</span></a>
                            </div>
                        </div>
                    </div>

                    <div class="kt-portlet__body">
                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">VCS Reconsilation Import</label>
                            <div class="col-9">
                                <input type="file" class="form-control" name="transfile">
                                <span class="form-text text-muted"></span>
                                @if ($errors->has('target_no'))
                                    <span class="col-12 text text-danger">
                                        {{ $errors->first('target_no') }}
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
                                    <button type="submit" value="Submit" id="btn" class="btn btn-brand">Submit</button>
                                    <button class="btn btn-brand" type="button" id="loadBtn" style="display:none" > <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Loading... </button>
                                    <a class="btn btn-secondary" href="{{ route('admin.user.index') }}" >Cancel</a>
                                </div>
                            </div>
                        </div>
                    </div>


                </form>
                <!--end::Form-->

            </div>
            <!--end::Portlet-->
            <div class="tab-pane"  role="tabpanel">
        <div class="kt-portlet__body">
            <!--begin: Datatable -->

            {{-- <table class="table" id="DPOPaymenttable">
                <thead>
                <tr>
                    <th>Ref</th>
                    <th>Company Name</th>
                    <th>Date</th>
                    <th>Booking Ref</th>
                    <th>Service Date</th>
                    <th>Customer Name</th>
                    <th>Customer Address</th>
                    <th>Customer Email</th>
                    <th>Customer Phone Number</th>
                    <th>Type</th>

                    <th>Status</th>
                    <th>Total</th>
                    <th>DPO Fee</th>
                    <th>Currency</th>
                    <th>Approval</th>
                    <th>Payment Date</th>
                    <th>Payment Method</th>
                    <th>Bank Name</th>
                    <th>MNO name</th>
                    <th>Card holder</th>

                    <th>User</th>
                    <th>Final Payment</th>
                    <th>Final Currency</th>
                    <th>MCCc</th>
                    <th>Token</th>
                    <th>Net Amount</th>
                    <th>Payment Method</th>
                    <th>Gross Amount USD</th>
                    <th>Info status</th>
                    <th>Action</th>
                 </tr>
                </thead>
                <tbody>
                    @if($data != null)
                    @foreach($data as $trx)

                    @php

                       $paymenttrx = \AlphaDirect\PaymentTransaction::where('TransactionToken',$data['token'])
                       ->first(['status','amount','policyNumber']);
                       $infostatus = "Not Found";
                       if($paymenttrx != null){
                        if($paymenttrx->status == "SUCCESS" || $paymenttrx->status == "S" || $paymenttrx->status == "Success"){
                            $status = "Paid";
                        }else{
                            $status = "Cancelled";
                        }
                        if(($status == $data['status']) && (number_format((float)$paymenttrx->amount, 2, '.', '') == number_format((float)$data['total'], 2, '.', '')) ){
                            $infostatus = "Found";
                        }else{
                            $infostatus = "Not Found";
                        }
                       }
                    @endphp
                    <tr @if($infostatus == "Found") @else style="background: #FF7F50 !important; color:aliceblue;" @endif >

                    <td>{{ $data['ref'] }}</td>
                    <td>{{ $data['companyname'] }}</td>
                    <td>{{ $data['date'] }}</td>
                    <td>{{ $data['bookingref'] }}</td>
                    <td>{{ $data['servicedate'] }}</td>
                    <td>{{ $data['customername'] }}</td>
                    <td>{{ $data['customeraddress'] }}</td>
                    <td>{{ $data['customeremail'] }}</td>
                    <td>{{ $data['customerphonenumber'] }}</td>
                    <td>{{ $data['type'] }}</td>

                    <td>{{ $data['status'] }}</td>
                    <td>{{ $data['total'] }}</td>
                    <td>{{ $data['dpofee'] }}</td>
                    <td>{{ $data['currency'] }}</td>
                    <td>{{ $data['approval'] }}</td>
                    <td>{{ $data['paymentdate'] }}</td>
                    <td>{{ $data['paymentmethod'] }}</td>
                    <td>{{ $data['bankname'] }}</td>
                    <td>{{ $data['mnoname'] }}</td>
                    <td>{{ $data['cardholder'] }}</td>

                    <td>{{ $data['user'] }}</td>
                    <td>{{ $data['finalpayment'] }}</td>
                    <td>{{ $data['finalcurrency'] }}</td>
                    <td>{{ $data['mcc'] }}</td>
                    <td>{{ $data['token'] }}</td>
                    <td>{{ $data['netamount'] }}</td>
                    <td>{{ $data['paymentmethod'] }}</td>
                    <td>{{ $data['grossamountusd'] }}</td>
                    <td>

                       {{ $infostatus }}

                    </td>
                    <td>
                        @if ($infostatus == "Not Found")
                            <a href="{{ route('lead.transDataStore', $data['token']) }}" class="btn btn-primary">Add Transaction</a>
                        @endif
                    </td>
                 </tr>
                    @endforeach
                    @endif
                </tbody>
            </table> --}}
            {{-- <div class="row">
                <div class="col-2">
                    <label>Filter By Status</label>
                    <select name="status_filter" class="select2 form-control" id="status_filter">
                        <option value="-1">All</option>
                        <option style="text-transform: capitalize" value="PROCESSING">PROCESSING</option>
                        <option style="text-transform: capitalize" value="SUCCESSFUL">SUCCESSFUL</option>
                        <option style="text-transform: capitalize" value="FAILED">FAILED</option>
                        <option style="text-transform: capitalize" value="CANCELLED">CANCELLED</option>
                        <option style="text-transform: capitalize" value="RETRY">RETRY</option>
                    </select>
                </div>
            </div> --}}
            <br>
            <table class="table table-striped table-bordered table-hover table-checkable" id="VcsPaymenttable">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Reference</th>
                    <th>Original Reference</th>
                    <th>Name</th>
                    <th>Goods</th>
                    <th>Amount</th>
                    <th>Bp</th>
                    <th>Code</th>
                    <th>Response</th>
                    <th>Settlement Date</th>
                    <th>Transaction Date</th>
                    <th>Settlement Reference</th>
                    <th>Interface</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
                </thead>
            </table>
            <!--end: Datatable -->
        </div>
    </div>
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



<!-- begin:: Scrolltop -->
<div id="kt_scrolltop" class="kt-scrolltop"> <i class="la la-arrow-up"></i> </div>
<!-- end:: Scrolltop -->

@include('admin.layouts.scripts')
<script src="{{ asset('assets/vendors/general/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/custom/components/vendors/bootstrap-datepicker/init.js') }}" type="text/javascript"></script>


<script src="{{  asset('assets/vendors/general/bootstrap-select/dist/js/bootstrap-select.js') }}" type="text/javascript"></script>
<script src="{{  asset('assets/app/custom/general/components/forms/widgets/bootstrap-select.js') }}" type="text/javascript"></script>

<script src="{{ asset('assets/vendors/general/jquery-validation/dist/jquery.validate.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/custom/components/vendors/jquery-validation/init.js') }}" type="text/javascript"></script>

<script src="{{  asset('assets/vendors/custom/datatables/datatables.bundle.js') }}" type="text/javascript"></script>
<script type="text/javascript" src="https://cdn.jsdelivr.net/npm/toastify-js"></script>

<script>
    "use strict";
    var status_filter = -1;

    var KTDatatablesDataSourceAjaxServer = function() {
        var table = '';
        var initTable1 = function() {
            table = $('#VcsPaymenttable').DataTable({
                responsive: true,
                searchDelay: 500,
                language:{
                    processing : "<img src='{{asset('img/loading.gif')}}'>"
                },
                processing: true,
                serverSide: true,
                ajax: {
                    url: '{!! route('getTransFormviewVcs') !!}',
                    // data: function (d) {
                    //     d.status_filter = status_filter;
                    // }
                },
                order: [0, 'DESC'],
                columns: [
                    {
                        data: 'id'
                    },
                    {
                        data: 'reference'
                    },
                    {
                        data: 'originalreference'
                    },
                    {
                        data: 'name'
                    },
                    {
                        data: 'goods'
                    },
                    {
                        data: 'amount'
                    },
                    {
                        data: 'bp'
                    },
                    {
                        data: 'code'
                    },
                    {
                        data: 'response'
                    },
                    {
                        data: 'settlementdate'
                    },
                    {
                        data: 'transactiondate'
                    },
                    {
                        data: 'settlementreference'
                    },
                    {
                        data: 'interface'
                    },
                    {
                        data: 'status'
                    },
                    {
                        data: 'actions'
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

    $('#status_filter').on('change',function(){
        status_filter = $(this).val();
        KTDatatablesDataSourceAjaxServer.draw();
    });

    function addVcsTransactionInTable(e){
        var idGet = $(e).attr("id");
        var isChecked=document.getElementById(idGet).checked;
        if (isChecked){
            ajaxRequest = setTimeout(function (sn) {
                $.ajax({
                    url: '{{ route('lead.vcsTransDataStore') }}',
                    data: {
                        "_token": "{{ csrf_token() }}",
                        "id": e.value,
                    },
                    type: 'post',
                    datatype: 'json',
                    success: function (data) {
                        Toastify({
                            text: data.message,
                            duration: 6000,
                            newWindow: true,
                            gravity: "top", // `top` or `bottom`
                            center: true, // `true` or `false`
                            backgroundColor: "#1dc9b7",
                        }).showToast();
                    },
                    error: function(data) {
                        Toastify({
                            text: data.responseJSON.message,
                            duration: 6000,
                            newWindow: true,
                            gravity: "top", // `top` or `bottom`
                            center: true, // `true` or `false`
                            backgroundColor: "red",
                        }).showToast();
                    }
                });
            }, 200);
        }
    }
</script>

{{-- <script>
    "use strict";
    // Class definition

    var initTable101 = function() {
                    var table = $('#DPOPaymenttable');
                    // begin first table
                    table.DataTable({
                        responsive: true,
                        searchDelay: 500,
                        processing: false,
                        serverSide: true,
                        ajax: "{!! route('transFormview') !!}",
                        order: [0, 'DESC'],
                        columns: [
                            {
                                data: 'id'
                            },
                            {
                                data: 'ref'
                            }, {
                                data: 'companyname'
                            }, {
                                data: 'date'
                            }, {
                                data: 'bookingref'
                            }, {
                                data: 'servicedate'
                            }, {
                                data: 'customername'
                            }, {
                                data: 'customeraddress'
                            },
                            {
                                data: 'customeremail'
                            }, {
                                data: 'customerphonenumber'
                            }, {
                                data: 'type'
                            }, {
                                data: 'status'
                            }, {
                                data: 'total'
                            }, {
                                data: 'dpofee'
                            }, {
                                data: 'currency'
                            },
                            {
                                data: 'approval'
                            }, {
                                data: 'paymentdate'
                            }, {
                                data: 'paymentmethod'
                            }, {
                                data: 'bankname'
                            }, {
                                data: 'mnoname'
                            }, {
                                data: 'cardholder'
                            }, {
                                data: 'user'
                            },
                            {
                                data: 'finalpayment'
                            }, {
                                data: 'finalcurrency'
                            }, {
                                data: 'mcc'
                            }, {
                                data: 'token'
                            }, {
                                data: 'netamount'
                            }, {
                                data: 'grossamountusd'
                            },

                        ],
                        error: function(error) {
                            console.log(error);
                        },

                    });

                return {
                    init: function() {
                        initTable101();
                    }
                };
            }();

</script> --}}



</body>

</html>
