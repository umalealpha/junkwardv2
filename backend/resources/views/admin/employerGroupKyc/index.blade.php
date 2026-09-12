<!DOCTYPE html>
<html lang="en" >

@include('admin.layouts.header')
<link href="{{  asset('assets/vendors/custom/datatables/datatables.bundle.css') }}" rel="stylesheet" type="text/css" />
<link href="{{  asset('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}" rel="stylesheet" type="text/css" />

<style>
/* Enhanced UI Styles for Employer Group KYC List */
.kyc-header-section {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 12px;
    padding: 18px 30px;
    margin-bottom: 25px;
    color: white;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
}

.kyc-header-section h3 {
    margin: 0;
    font-size: 14px;
    font-weight: 600;
    color: white;
}

.kyc-header-section .kt-subheader__breadcrumbs {
    margin-top: 4px;
    font-size: 11px;
}

.kyc-header-section .kt-subheader__breadcrumbs a,
.kyc-header-section .kt-subheader__breadcrumbs-link {
    color: rgba(255, 255, 255, 0.9) !important;
    font-size: 11px;
}

.kyc-header-section .kt-subheader__breadcrumbs-link--active {
    color: white !important;
    font-weight: 600;
    font-size: 11px;
}

.filter-card {
    background: white;
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    border: 1px solid #e4e6ef;
}

.filter-card label {
    font-weight: 600;
    color: #495057;
    margin-bottom: 8px;
    font-size: 14px;
}

.filter-card .form-control {
    border-radius: 6px;
    border: 1px solid #e4e6ef;
    padding: 10px 15px;
    transition: all 0.3s ease;
}

.filter-card .form-control:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
}

.kt-portlet {
    border-radius: 12px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.08);
    border: 1px solid #e4e6ef;
    overflow: hidden;
}

.kt-portlet__body {
    padding: 25px;
}

#employer_group_kyc_table {
    border-collapse: separate;
    border-spacing: 0;
}

#employer_group_kyc_table thead th {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    color: #495057;
    font-weight: 600;
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 15px;
    border: none;
    border-bottom: 2px solid #667eea;
}

#employer_group_kyc_table tbody td {
    padding: 12px 15px;
    vertical-align: middle;
    border-bottom: 1px solid #f1f1f2;
}

#employer_group_kyc_table tbody td .status-badge,
#employer_group_kyc_table tbody td .compliance-badge {
    display: inline-flex;
    vertical-align: middle;
}

#employer_group_kyc_table tbody tr {
    transition: all 0.3s ease;
}

#employer_group_kyc_table tbody tr:hover {
    background-color: #f8f9ff;
    transform: translateX(2px);
    box-shadow: 0 2px 8px rgba(102, 126, 234, 0.1);
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    line-height: 1.4;
    white-space: nowrap;
}

.status-badge i {
    font-size: 11px;
    line-height: 1;
}

.status-badge.pending {
    background-color: #ffc107;
    color: #856404;
}

.status-badge.completed {
    background-color: #28a745;
    color: white;
}

.status-badge.verification-pending {
    background-color: #17a2b8;
    color: white;
}

.compliance-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    line-height: 1.4;
    white-space: nowrap;
}

.compliance-badge i {
    font-size: 11px;
    line-height: 1;
}

.compliance-badge.verified {
    background-color: #28a745;
    color: white;
}

.compliance-badge.pending {
    background-color: #ffc107;
    color: #856404;
}

.action-buttons {
    display: flex;
    gap: 8px;
}

.action-buttons .btn {
    padding: 8px 12px;
    border-radius: 6px;
    transition: all 0.3s ease;
    border: none;
}

.action-buttons .btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}

.action-buttons .btn i {
    font-size: 16px;
}

.dataTables_wrapper {
    margin-top: 20px;
}

.dataTables_wrapper .dataTables_filter input {
    border-radius: 6px;
    border: 1px solid #e4e6ef;
    padding: 8px 15px;
    margin-left: 10px;
}

.dataTables_wrapper .dataTables_length select {
    border-radius: 6px;
    border: 1px solid #e4e6ef;
    padding: 6px 10px;
    margin: 0 8px;
}

.dataTables_wrapper .dataTables_paginate .paginate_button {
    border-radius: 6px;
    margin: 0 2px;
    padding: 6px 12px;
}

.dataTables_wrapper .dataTables_paginate .paginate_button.current {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
    border: none !important;
    color: white !important;
}

.dataTables_wrapper .dataTables_paginate .paginate_button:hover {
    background: #667eea !important;
    border: none !important;
    color: white !important;
}

/* Loading overlay */
.dataTables_processing {
    background: rgba(255, 255, 255, 0.95) !important;
    border-radius: 8px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

/* Responsive improvements */
@media (max-width: 768px) {
    .kyc-header-section {
        padding: 20px;
    }
    
    .filter-card {
        padding: 15px;
    }
    
    #employer_group_kyc_table thead th {
        padding: 10px 8px;
        font-size: 11px;
    }
    
    #employer_group_kyc_table tbody td {
        padding: 10px 8px;
        font-size: 13px;
    }
}
</style>

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
        <div class="kt-subheader kt-grid__item" id="kt_subheader" style="padding: 0;">
            <div class="kt-content kt-grid__item kt-grid__item--fluid" style="padding: 20px 20px 0;">
                <div class="kyc-header-section">
                    <h3>
                        <i class="flaticon-users" style="margin-right: 10px;"></i>
                        Health Employer Groups KYC
                    </h3>
                    <div class="kt-subheader__breadcrumbs">
                        <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                        <span class="kt-subheader__breadcrumbs-separator"></span> 
                        <a href="{{Route('admin-dashboard')}}" class="kt-subheader__breadcrumbs-link"> Dashboard </a> 
                        <span class="kt-subheader__breadcrumbs-separator"></span>
                        <a class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">Health Employer Groups KYC</a>
                    </div>
                </div>
            </div>
        </div>
        <!-- end:: Subheader -->
        <!-- begin:: Content -->
        <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content" style="padding: 20px;">
            <div class="kt-portlet kt-portlet--mobile">
                <div class="kt-portlet__head" style="background: #f8f9fa; border-bottom: 2px solid #e4e6ef; padding: 20px 25px;">
                    <div class="kt-portlet__head-label">
                        <h3 class="kt-portlet__head-title" style="margin: 0; font-size: 18px; font-weight: 600; color: #495057;">
                            <i class="flaticon-list-2" style="margin-right: 10px; color: #667eea;"></i>
                            Employer Groups List
                        </h3>
                    </div>
                </div>
                <div class="kt-portlet__body">
                    <div class="filter-card">
                        <div class="row">
                            <div class="col-md-4">
                                <label><i class="flaticon-filter" style="margin-right: 5px;"></i>Filter by Status</label>
                                <select name="status" id="status" class="form-control id-type validateGroup1">
                                    <option style="text-transform: capitalize" value="">All Status</option>
                                    <option style="text-transform: capitalize" value="Pending">Pending</option>
                                    <option style="text-transform: capitalize" value="Completed">Completed</option>
                                </select>
                            </div>
                        </div>
                    </div>
                <!--begin: Datatable -->
                    <table class="table table-striped table-bordered table-hover table-checkable" id="employer_group_kyc_table">
                        <thead>
                        <tr>
                            <th><i class="flaticon-user" style="margin-right: 5px;"></i>Employer Group Name</th>
                            <th><i class="flaticon-check" style="margin-right: 5px;"></i>Compliance</th>
                            <th><i class="flaticon-interface-7" style="margin-right: 5px;"></i>Status</th>
                            <th><i class="flaticon-calendar" style="margin-right: 5px;"></i>Updated At</th>
                            <th><i class="flaticon-settings" style="margin-right: 5px;"></i>Action</th>
                        </tr>
                        </thead>
                    </table>
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

@include('admin.layouts.scripts')

<script src="{{  asset('assets/vendors/custom/datatables/datatables.bundle.js') }}" type="text/javascript"></script>
<script>

    "use strict";
    var status = $('#status').val();
    var KTDatatablesDataSourceAjaxServer = function() {
        var table = '';
        var initTable1 = function() {
            table = $('#employer_group_kyc_table').DataTable({
                responsive: true,
                searchDelay: 500,
                processing: true,
                language:{
                    processing : '<div style="text-align:center;padding:20px;"><i class="fa fa-spinner fa-spin fa-3x" style="color:#667eea;"></i><br><br>Loading data...</div>'
                },
                serverSide: true,
                ajax: {
                    url: "{!! route('admin.employerGroupKyc.data') !!}",
                    data: function (d) {
                        d.status = status;
                    }

                },
                order: [0, 'DESC'],
                columns: [
                    {data: 'employer_group_name'},
                    {data: 'compliance'},
                    {data: 'status'},
                    {data:'updated_at'},
                    {data: 'action', orderable: false, searchable: false},
                ],
                pageLength: 25,
                lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
                dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
                     '<"row"<"col-sm-12"tr>>' +
                     '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
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

    $('#status').on('change',function(){
        status = $(this).val();
        KTDatatablesDataSourceAjaxServer.draw();
    });
</script>
<script src="{{  asset('assets/vendors/general/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}" type="text/javascript"></script>
<script src="{{  asset('assets/vendors/custom/components/vendors/bootstrap-datepicker/init.js') }}" type="text/javascript"></script>

</body>
<!-- end::Body -->
</html>
