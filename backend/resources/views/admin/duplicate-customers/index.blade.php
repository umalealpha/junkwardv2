<!DOCTYPE html>
<html lang="en" >

@include('admin.layouts.header')
<link href="{{ asset('css/rekyc-fixes.css') }}" rel="stylesheet" type="text/css" />

<link href="{{  asset('assets/vendors/custom/datatables/datatables.bundle.css') }}" rel="stylesheet" type="text/css" />
<link href="{{  asset('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css') }}" rel="stylesheet" type="text/css" />
<link href="{{  asset('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}" rel="stylesheet" type="text/css" />

<style>
/* Duplicate Customers Admin Custom Styles */
.kt-widget1 {
    background: #fff;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    border: 1px solid #e4e6ef;
    transition: all 0.3s ease;
}

/* New columns styling */
#duplicates_table td:nth-child(8), /* Duplicate Count column */
#duplicates_table td:nth-child(9) { /* Policy Count column */
    vertical-align: top;
    padding: 8px 4px;
}

/* Policy count badge styling */
#duplicates_table td:nth-child(9) .kt-badge {
    font-size: 12px;
    padding: 4px 8px;
    font-weight: 600;
    display: inline-block;
}

/* Duplicate count badge styling */
#duplicates_table td:nth-child(8) .kt-badge {
    font-size: 12px;
    padding: 4px 8px;
    font-weight: 600;
}

.kt-widget1:hover {
    box-shadow: 0 4px 20px rgba(0,0,0,0.15);
    transform: translateY(-2px);
}

.kt-widget1__title {
    font-size: 2rem;
    margin-bottom: 5px;
    font-weight: 700;
}

.kt-widget1__desc {
    font-size: 14px;
    opacity: 0.8;
}

.kt-widget1__number {
    font-size: 2.5rem;
    opacity: 0.3;
}

.kt-badge {
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.kt-badge--success {
    background-color: #1dc9b7;
    color: #fff;
}

.kt-badge--info {
    background-color: #5d78ff;
    color: #fff;
}

.kt-badge--warning {
    background-color: #ffb822;
    color: #fff;
}

.kt-badge--danger {
    background-color: #fd27eb;
    color: #fff;
}

.kt-badge--secondary {
    background-color: #74788d;
    color: #fff;
}

.kt-badge--brand {
    background-color: #5867dd;
    color: #fff;
}

.duplicate-type-badge {
    font-size: 10px;
    padding: 2px 6px;
}

.status-pending { color: #ffb822; }
.status-reviewed { color: #5d78ff; }
.status-resolved { color: #1dc9b7; }
.status-ignored { color: #74788d; }

/* Action Buttons Styling */
.btn-group .btn {
    margin-right: 2px;
    padding: 4px 8px;
    border-radius: 4px;
    border: 1px solid #e4e6ef;
    background: #fff;
    color: #74788d;
    transition: all 0.3s ease;
}

.btn-group .btn:hover {
    background: #f4f5f8;
    color: #5867dd;
    border-color: #5867dd;
}

.btn-group .btn:last-child {
    margin-right: 0;
}

.btn-group .btn i {
    font-size: 14px;
}

/* Fix for DataTable action column */
#duplicates_table td:last-child {
    text-align: center;
    vertical-align: middle;
    white-space: nowrap;
}

/* Ensure action buttons are visible */
.btn-clean {
    background: transparent !important;
    border: 1px solid #e4e6ef !important;
    color: #74788d !important;
}

.btn-clean:hover {
    background: #f4f5f8 !important;
    color: #5867dd !important;
    border-color: #5867dd !important;
}

/* Fix for duplicate type text visibility */
.duplicate-type-badge {
    font-size: 10px;
    padding: 2px 6px;
    margin: 1px;
    display: inline-block;
    white-space: nowrap;
    overflow: visible;
    text-overflow: clip;
    line-height: 1.2;
    height: auto;
    min-height: 20px;
}

/* Fix for table cell content */
#duplicates_table td {
    vertical-align: middle;
    padding: 8px;
}

/* Ensure proper button display */
.btn-group {
    display: inline-flex;
    flex-wrap: nowrap;
}

.btn-group .btn {
    display: inline-block;
    min-width: 32px;
    height: 32px;
    line-height: 1;
    text-align: center;
}

/* Fix for action column width */
#duplicates_table th:last-child,
#duplicates_table td:last-child {
    width: 120px;
    min-width: 120px;
    max-width: 120px;
}

/* Ensure icons are visible */
.btn i {
    display: inline-block;
    width: 14px;
    height: 14px;
    line-height: 14px;
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
     @endif
    <div class="kt-grid__item kt-grid__item--fluid kt-grid kt-grid--hor">
        <!-- begin:: Subheader -->
        <div class="kt-subheader kt-grid__item" id="kt_subheader">
            <div class="kt-subheader__main">
                <h3 class="kt-subheader__title">
                    Duplicate Customers Management Dashboard
                </h3>
                <span class="kt-subheader__separator kt-hidden"></span>
                <div class="kt-subheader__breadcrumbs">
                    <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                    <span class="kt-subheader__breadcrumbs-separator"></span> 
                    <a href="{{Route('admin-dashboard')}}" class="kt-subheader__breadcrumbs-link"> Dashboard </a> 
                    <span class="kt-subheader__breadcrumbs-separator"></span>
                    <span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">Duplicate Customers</span>
                </div>
            </div>
            <div class="kt-subheader__toolbar">
                <div class="kt-subheader__wrapper">
                    <button onclick="exportDuplicates()" class="btn btn-sm btn-elevate btn-brand btn-elevate">
                        <span class="kt-opacity-11">Export Data</span>&nbsp; 
                        <i class="flaticon2-download kt-padding-l-5 kt-padding-r-0"></i>
                    </button>
                </div>
            </div>
        </div>
        <!-- end:: Subheader -->
        <!-- begin:: Content -->
        <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
            <div class="kt-portlet kt-portlet--mobile">
                <div class="kt-portlet__body">
                    <!-- Statistics Cards -->
                    <div class="row kt-margin-b-20">
                        <div class="col-lg-3 col-6">
                            <div class="kt-widget1">
                                <div class="kt-widget1__item">
                                    <div class="kt-widget1__info">
                                        <h3 class="kt-widget1__title" style="color: #5867dd; font-weight: 600;">{{ $stats['total_duplicates'] ?? 0 }}</h3>
                                        <span class="kt-widget1__desc" style="color: #74788d; font-size: 14px;">Total Duplicates</span>
                                    </div>
                                    <span class="kt-widget1__number kt-font-brand" style="font-size: 2.5rem;">
                                        <i class="flaticon2-warning"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-6">
                            <div class="kt-widget1">
                                <div class="kt-widget1__item">
                                    <div class="kt-widget1__info">
                                        <h3 class="kt-widget1__title" style="color: #ffb822; font-weight: 600;">{{ $stats['pending_duplicates'] ?? 0 }}</h3>
                                        <span class="kt-widget1__desc" style="color: #74788d; font-size: 14px;">Pending Review</span>
                                    </div>
                                    <span class="kt-widget1__number kt-font-warning" style="font-size: 2.5rem;">
                                        <i class="flaticon2-hourglass"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-6">
                            <div class="kt-widget1">
                                <div class="kt-widget1__item">
                                    <div class="kt-widget1__info">
                                        <h3 class="kt-widget1__title" style="color: #1dc9b7; font-weight: 600;">{{ $stats['resolved_duplicates'] ?? 0 }}</h3>
                                        <span class="kt-widget1__desc" style="color: #74788d; font-size: 14px;">Resolved</span>
                                    </div>
                                    <span class="kt-widget1__number kt-font-success" style="font-size: 2.5rem;">
                                        <i class="flaticon2-check-mark"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-6">
                            <div class="kt-widget1">
                                <div class="kt-widget1__item">
                                    <div class="kt-widget1__info">
                                        <h3 class="kt-widget1__title" style="color: #fd27eb; font-weight: 600;">{{ $stats['resolution_rate'] ?? 0 }}%</h3>
                                        <span class="kt-widget1__desc" style="color: #74788d; font-size: 14px;">Resolution Rate</span>
                                    </div>
                                    <span class="kt-widget1__number kt-font-danger" style="font-size: 2.5rem;">
                                        <i class="flaticon2-pie-chart"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Additional Stats -->
                    <div class="row kt-margin-b-20">
                        <div class="col-md-2">
                            <div class="kt-widget1">
                                <div class="kt-widget1__item">
                                    <div class="kt-widget1__info">
                                        <h3 class="kt-widget1__title" style="color: #5867dd; font-weight: 600;">{{ $stats['omang_passport_duplicates'] ?? 0 }}</h3>
                                        <span class="kt-widget1__desc" style="color: #74788d; font-size: 12px;">Omang/Passport</span>
                                    </div>
                                    <span class="kt-widget1__number kt-font-brand" style="font-size: 1.8rem;">
                                        <i class="flaticon2-user"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="kt-widget1">
                                <div class="kt-widget1__item">
                                    <div class="kt-widget1__info">
                                        <h3 class="kt-widget1__title" style="color: #1dc9b7; font-weight: 600;">{{ $stats['cellphone_email_duplicates'] ?? 0 }}</h3>
                                        <span class="kt-widget1__desc" style="color: #74788d; font-size: 12px;">Contact Info</span>
                                    </div>
                                    <span class="kt-widget1__number kt-font-success" style="font-size: 1.8rem;">
                                        <i class="flaticon2-phone"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="kt-widget1">
                                <div class="kt-widget1__item">
                                    <div class="kt-widget1__info">
                                        <h3 class="kt-widget1__title" style="color: #ffb822; font-weight: 600;">{{ $stats['multiple_accounts_duplicates'] ?? 0 }}</h3>
                                        <span class="kt-widget1__desc" style="color: #74788d; font-size: 12px;">Multiple Accounts</span>
                                    </div>
                                    <span class="kt-widget1__number kt-font-warning" style="font-size: 1.8rem;">
                                        <i class="flaticon2-bank"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="kt-widget1">
                                <div class="kt-widget1__item">
                                    <div class="kt-widget1__info">
                                        <h3 class="kt-widget1__title" style="color: #5d78ff; font-weight: 600;">{{ $stats['today_duplicates'] ?? 0 }}</h3>
                                        <span class="kt-widget1__desc" style="color: #74788d; font-size: 12px;">Today</span>
                                    </div>
                                    <span class="kt-widget1__number kt-font-info" style="font-size: 1.8rem;">
                                        <i class="flaticon2-calendar"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="kt-widget1">
                                <div class="kt-widget1__item">
                                    <div class="kt-widget1__info">
                                        <h3 class="kt-widget1__title" style="color: #1dc9b7; font-weight: 600;">{{ $stats['this_week_duplicates'] ?? 0 }}</h3>
                                        <span class="kt-widget1__desc" style="color: #74788d; font-size: 12px;">This Week</span>
                                    </div>
                                    <span class="kt-widget1__number kt-font-success" style="font-size: 1.8rem;">
                                        <i class="flaticon2-calendar-2"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="kt-widget1">
                                <div class="kt-widget1__item">
                                    <div class="kt-widget1__info">
                                        <h3 class="kt-widget1__title" style="color: #fd27eb; font-weight: 600;">{{ $stats['this_month_duplicates'] ?? 0 }}</h3>
                                        <span class="kt-widget1__desc" style="color: #74788d; font-size: 12px;">This Month</span>
                                    </div>
                                    <span class="kt-widget1__number kt-font-danger" style="font-size: 1.8rem;">
                                        <i class="flaticon2-calendar-3"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Filters -->
                    <div class="row kt-margin-b-20">
                        <div class="col-12">
                            <div class="kt-portlet kt-portlet--mobile">
                                <div class="kt-portlet__head">
                                    <div class="kt-portlet__head-label">
                                        <h3 class="kt-portlet__head-title">
                                            Filters
                                        </h3>
                                    </div>
                                </div>
                                <div class="kt-portlet__body">
                                    <form id="filterForm">
                                        <div class="row">
                                            <div class="col-md-2">
                                                <div class="form-group">
                                                    <label>Status</label>
                                                    <select name="status" class="form-control">
                                                        <option value="">All Statuses</option>
                                                        <option value="pending">Pending</option>
                                                        <option value="reviewed">Reviewed</option>
                                                        <option value="resolved">Resolved</option>
                                                        <option value="ignored">Ignored</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-2">
                                                <div class="form-group">
                                                    <label>Duplicate Type</label>
                                                    <select name="duplicate_type" class="form-control">
                                                        <option value="">All Types</option>
                                                        <option value="omang_passport">Omang/Passport</option>
                                                        <option value="cellphone_email">Cellphone/Email</option>
                                                        <option value="multiple_accounts">Multiple Accounts</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-2">
                                                <div class="form-group">
                                                    <label>Date From</label>
                                                    <input type="date" name="date_from" class="form-control">
                                                </div>
                                            </div>
                                            <div class="col-md-2">
                                                <div class="form-group">
                                                    <label>Date To</label>
                                                    <input type="date" name="date_to" class="form-control">
                                                </div>
                                            </div>
                                            <div class="col-md-2">
                                                <div class="form-group">
                                                    <label>Search</label>
                                                    <input type="text" name="search" class="form-control" placeholder="Search...">
                                                </div>
                                            </div>
                                            <div class="col-md-2">
                                                <div class="form-group">
                                                    <label>&nbsp;</label>
                                                    <div>
                                                        <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                                                        <button type="button" id="clearFilters" class="btn btn-secondary btn-sm">Clear</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Duplicates Table -->
                    <div class="row">
                        <div class="col-12">
                            <div class="kt-portlet kt-portlet--mobile">
                                <div class="kt-portlet__head">
                                    <div class="kt-portlet__head-label">
                                        <h3 class="kt-portlet__head-title">
                                            Duplicate Customers
                                        </h3>
                                    </div>
                                    <div class="kt-portlet__head-toolbar">
                                        <div class="kt-portlet__head-wrapper">
                                            <button onclick="exportDuplicates()" class="btn btn-sm btn-elevate btn-brand">
                                                <i class="flaticon2-download"></i> Export Data
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="kt-portlet__body">
                                    <div class="table-responsive">
                                        <table class="table table-striped table-bordered table-hover" id="duplicates_table">
                                            <thead>
                                                <tr>
                                                    <th>ID</th>
                                                    <th>Customer</th>
                                                    <th>Contact Info</th>
                                                    <th>Documents</th>
                                                    <th>Banking</th>
                                                    <th>Type</th>
                                                    <th>Status</th>
                                                    <th>Duplicate Count</th>
                                                    <th>Policy Count</th>
                                                    <th>Created</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <!-- Data will be loaded via AJAX -->
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
  
<!-- end:: Root -->

@include('includes.footer')
</div>
<!-- end:: Wrapper -->
</div>
<!-- end:: Page -->
</div>
<div id="kt_scrolltop" class="kt-scrolltop"> <i class="la la-arrow-up"></i> </div>
@include('admin.layouts.scripts')

<!-- SweetAlert2 CDN -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link rel="stylesheet" href="{{ asset('admin/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css') }}">
<link rel="stylesheet" href="{{ asset('admin/plugins/datatables-responsive/css/responsive.bootstrap4.min.css') }}">

<script src="{{ asset('admin/plugins/datatables/jquery.dataTables.min.js') }}"></script>
<script src="{{ asset('admin/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js') }}"></script>
<script src="{{ asset('admin/plugins/datatables-responsive/js/dataTables.responsive.min.js') }}"></script>
<script src="{{ asset('admin/plugins/datatables-responsive/js/responsive.bootstrap4.min.js') }}"></script>
<script src="{{  asset('assets/vendors/custom/datatables/datatables.bundle.js') }}" type="text/javascript"></script>

<script>
$(document).ready(function() {
    // Initialize DataTable
    var table = $('#duplicates_table').DataTable({
        responsive: true,
        searchDelay: 500,
        processing: true,
        language: {
            processing: "<img src='{{asset('img/loading.gif')}}'>",
            emptyTable: "No duplicate customers found",
            zeroRecords: "No matching records found"
        },
        serverSide: true,
        ajax: {
            url: '{{ route("admin.duplicate-customers.data") }}',
            data: function (d) {
                // Add filter parameters
                d.status = $('select[name="status"]').val();
                d.duplicate_type = $('select[name="duplicate_type"]').val();
                d.search_filter = $('input[name="search"]').val();
                d.date_from = $('input[name="date_from"]').val();
                d.date_to = $('input[name="date_to"]').val();
            },
            error: function(xhr, error, thrown) {
                console.error('DataTable AJAX error:', xhr, error, thrown);
                alert('Error loading data. Please refresh the page.');
            }
        },
        order: [[0, 'DESC']],
        pageLength: 25,
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
        dom: 'lfrtip',
        columns: [
            {data: 'id', width: '5%', className: 'text-center'},
            {data: 'name', width: '12%'},
            {data: 'contact_info', width: '12%'},
            {data: 'documents', width: '12%'},
            {data: 'banking', width: '12%'},
            {data: 'duplicate_type', width: '8%', className: 'text-center'},
            {data: 'status', width: '8%', className: 'text-center'},
            {data: 'duplicate_count', width: '8%', className: 'text-center'},
            {data: 'policy_numbers', width: '10%', className: 'text-center'},
            {data: 'created_at', width: '8%', className: 'text-center'},
            {data: 'actions', orderable: false, searchable: false, width: '120px', className: 'text-center'}
        ],
        drawCallback: function(settings) {
            // Re-initialize any custom functionality after each draw
            console.log('DataTable draw callback executed');
        }
    });

    // Handle filter form submission
    $('#filterForm').on('submit', function(e) {
        e.preventDefault();
        table.draw();
    });

    // Handle clear filters
    $('#clearFilters').on('click', function(e) {
        e.preventDefault();
        $('#filterForm')[0].reset();
        table.draw();
    });

    // Auto-submit on filter change
    $('select[name="status"], select[name="duplicate_type"]').on('change', function() {
        table.draw();
    });

    // Debounced search
    var searchTimeout;
    $('input[name="search"]').on('keyup', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(function() {
            table.draw();
        }, 500);
    });
});

// Action button functions
function editDuplicate(id) {
    // Navigate to edit page
    window.location.href = '{{ route("admin.duplicate-customers.index") }}/' + id + '/edit';
}

// Export functionality
function exportDuplicates() {
    // Show loading state
    Swal.fire({
        title: 'Exporting Data...',
        text: 'Please wait while we prepare your export file.',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    // Create a temporary link to trigger download
    const link = document.createElement('a');
    link.href = '{{ route("admin.duplicate-customers.export") }}';
    link.download = 'duplicate_customers_' + new Date().toISOString().slice(0, 19).replace(/:/g, '-') + '.csv';
    
    // Trigger download
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);

    // Show success message after a short delay
    setTimeout(() => {
        Swal.fire({
            title: 'Export Complete!',
            text: 'Your duplicate customers data has been downloaded successfully.',
            icon: 'success',
            timer: 3000,
            showConfirmButton: false
        });
    }, 1000);
}

function deleteDuplicate(id) {
    Swal.fire({
        title: 'Are you sure?',
        text: "You won't be able to revert this!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete it!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            // Show loading
            Swal.fire({
                title: 'Deleting...',
                text: 'Please wait while we delete the record.',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            $.ajax({
                url: '{{ route("admin.duplicate-customers.destroy", ":id") }}'.replace(':id', id),
                type: 'DELETE',
                data: {
                    _token: '{{ csrf_token() }}'
                },
                success: function(response) {
                    if (response.success) {
                        $('#duplicates_table').DataTable().draw();
                        Swal.fire({
                            title: 'Deleted!',
                            text: 'Duplicate record has been deleted successfully.',
                            icon: 'success',
                            timer: 2000,
                            showConfirmButton: false
                        });
                    } else {
                        Swal.fire({
                            title: 'Error!',
                            text: 'Error deleting record: ' + response.message,
                            icon: 'error'
                        });
                    }
                },
                error: function(xhr) {
                    let errorMessage = 'Error deleting record. Please try again.';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMessage = xhr.responseJSON.message;
                    }
                    Swal.fire({
                        title: 'Error!',
                        text: errorMessage,
                        icon: 'error'
                    });
                }
            });
        }
    });
}
</script>

</body>
</html>
