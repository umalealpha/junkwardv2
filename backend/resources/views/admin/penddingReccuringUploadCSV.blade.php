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
                    Pendding Reccuring CSV Import
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
                <form id="transFormsave" action="{{ route('admin.penddingReccuringUploadCSV') }}" method="POST" enctype="multipart/form-data" class="kt-form">
                    <!-- CSRF Token -->
                    <input type="hidden" name="_token" value="{{ csrf_token() }}" />

                    <div class="kt-portlet__body"><h3>Step 1</h3>
                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Pendding Reccuring CSV Upload</label>
                            <div class="col-9">
                                <input type="file" class="form-control" name="penddingReccuring">
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
                                    <button type="submit" value="Submit" id="btn" class="btn btn-brand">Upload</button>
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
            @can('pendingRecurringPay')
            <div class="kt-portlet">
                <!--begin::Form-->
                <form id="transFormsave" action="{{ route('GenerateReccuringToken') }}" method="POST" enctype="multipart/form-data" class="kt-form">
                    <!-- CSRF Token -->
                    <input type="hidden" name="_token" value="{{ csrf_token() }}" />

                    <div class="kt-portlet__body"><h3>Step 2</h3>
                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Make Pending Reccuring Payment</label>
                            <div class="col-9">
                                    <button type="submit" value="Submit" id="btn2" class="btn btn-success">Make payment now</button>
                                    <button class="btn btn-success" type="button" id="loadBtn2" style="display:none" > <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>Generateing... </button>
                                   
                                </div>
                        </div>


                    </div>
                  


                </form>
                <!--end::Form-->

            </div>
          @endcan
        
<div class="tab-pane"  role="tabpanel">
   <div class="kt-portlet__body"> <a href="{{ route('admin.pendingRecurringdatadelete') }}"><button class="btn btn-danger" type="button" > Clear Table Data </button> </a><br><br>
      <table class="table table-striped table-bordered table-hover table-checkable" id="DPOPaymenttable">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Policy Number</th>
                    <th>Installment</th>
                    <th>Retry Count</th>
                    <th>Premium</th>
                    <th>Email</th>
                    <th>City</th>
                    <th>Token</th>
                    <th>Subscription Token</th>
                    <th>Customer Token</th>
                    <th>Billing Date</th>
                    <th>Reason</th>
                    <th>Status</th>
                    
                  </tr>
                </thead>
            </table>
      </div>
        </div>
        <!-- end:: Content -->
    </div>
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
<script src="https://cdn.datatables.net/buttons/1.6.0/js/dataTables.buttons.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/1.6.0/js/buttons.html5.min.js"></script>
<script>
    "use strict";
    var status_filter = -1;

    var KTDatatablesDataSourceAjaxServer = function() {
        var table = '';
        var initTable1 = function() {
            table = $('#DPOPaymenttable').DataTable({
                "lengthMenu": [[10, 25, 100, -1], [10, 25, 100, "All"]],
                responsive: true,
                searchDelay: 500,
                language:{
                    processing : "<img src='{{asset('img/loading.gif')}}'>"
                },
                processing: true,
                serverSide: true,
                dom: 'Blfrtip',
                buttons: [
                    'excelHtml5',
                    'csvHtml5',

                ],
                ajax: {
                    url: '{!! route("getPendingRecurringData") !!}',
                    data: function (d) {
                        d.status_filter = status_filter;
                    }
                },
                order: [0, 'DESC'],
                columns: [
                    { data: 'id' },
                    { data: 'policy_number'},
                    
                    { data: 'installment'},
                    { data: 'retry_count' },
                    { data: 'premium'},
                    { data: 'email' },
                    { data: 'city'},
                    { data: 'token' },
                    { data: 'subscription_token'},
                    { data: 'customer_token' },
                    { data: 'billing_date'},
                    { data: 'reason'},
                    { data: 'status' },
                   
                   
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

   
</script>




</body>

</html>
