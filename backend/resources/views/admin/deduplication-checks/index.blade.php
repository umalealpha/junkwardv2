<!DOCTYPE html>
<html lang="en" >

@include('admin.layouts.header')

<link href="{{ asset('css/deduplication-checks-fixes.css') }}" rel="stylesheet" type="text/css" />
<link href="{{  asset('assets/vendors/custom/datatables/datatables.bundle.css') }}" rel="stylesheet" type="text/css" />
<link href="{{  asset('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css') }}" rel="stylesheet" type="text/css" />
<link href="{{  asset('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}" rel="stylesheet" type="text/css" />

<style>
/* DeduplicationChecks Admin Custom Styles */
.kt-widget1 {
    background: #fff;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    border: 1px solid #e4e6ef;
    transition: all 0.3s ease;
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

.kt-badge--success { background-color: #1dc9b7; color: #fff; }
.kt-badge--info { background-color: #5d78ff; color: #fff; }
.kt-badge--secondary { background-color: #74788d; color: #fff; }
.kt-badge--brand { background-color: #5867dd; color: #fff; }
.kt-badge--warning { background-color: #ffb822; color: #fff; }
.kt-badge--danger { background-color: #fd27eb; color: #fff; }

.kt-badge--inline {
    display: inline-block;
    margin-right: 5px;
}

.stats-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 20px;
}

.stats-card.success {
    background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
}

.stats-card.warning {
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
}

.stats-card.info {
    background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
}

.stats-card.danger {
    background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
}

.activity-item {
    padding: 10px 0;
    border-bottom: 1px solid #f0f0f0;
}

.activity-item:last-child {
    border-bottom: none;
}

.activity-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 15px;
}

.activity-content {
    flex: 1;
}

.activity-title {
    font-weight: 600;
    margin-bottom: 5px;
}

.activity-desc {
    color: #666;
    font-size: 14px;
}

.activity-time {
    color: #999;
    font-size: 12px;
}
</style>

<body class="kt-header--fixed kt-header-mobile--fixed kt-subheader--fixed kt-subheader--enabled kt-subheader--solid kt-aside--enabled kt-aside--fixed kt-page--loading">

<!-- begin:: Header Mobile -->
<div class="kt-header-mobile kt-header-mobile--fixed" id="kt_header_mobile">
    <div class="kt-header-mobile__logo">
        <a href="{{Route('admin-dashboard')}}">
            <img alt="Logo" src="{{ asset('assets/media/logos/logo-1.png') }}" />
        </a>
    </div>
    <div class="kt-header-mobile__toolbar">
        <button class="kt-header-mobile__toggler kt-header-mobile__toggler--left" id="kt_aside_mobile_toggler"><span></span></button>
        <button class="kt-header-mobile__toggler" id="kt_header_mobile_toggler"><span></span></button>
        <button class="kt-header-mobile__topbar-toggler" id="kt_header_mobile_topbar_toggler"><i class="flaticon2-more"></i></button>
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

    @if (Auth::user()->password == null)
        @include('includes.reset')
    @endif

    <div class="kt-grid__item kt-grid__item--fluid kt-grid kt-grid--hor">
        <!-- begin:: Subheader -->
        <div class="kt-subheader kt-grid__item" id="kt_subheader">
            <div class="kt-subheader__main">
                <h3 class="kt-subheader__title">
                    Bank Statement Upload Management Dashboard
                </h3>
                <span class="kt-subheader__separator kt-hidden"></span>
                <div class="kt-subheader__breadcrumbs">
                    <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                    <span class="kt-subheader__breadcrumbs-separator"></span> 
                    <a href="{{Route('admin-dashboard')}}" class="kt-subheader__breadcrumbs-link"> Dashboard </a> 
                    <span class="kt-subheader__breadcrumbs-separator"></span>
                    <span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">Bank Statement Upload Management</span>
                </div>
            </div>
            <div class="kt-subheader__toolbar">
                <div class="kt-subheader__wrapper">
                    <button type="button" class="btn btn-sm btn-elevate btn-brand btn-elevate" data-toggle="modal" data-target="#generateLinksModal">
                        <span class="kt-opacity-11">Generate Upload Links</span>&nbsp; 
                        <i class="flaticon2-add-1 kt-padding-l-5 kt-padding-r-0"></i>
                    </button>
                </div>
            </div>
        </div>
        <!-- end:: Subheader -->

        <!-- begin:: Content -->
        <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
            <div class="row">
                <!-- Statistics Cards -->
                <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6">
                    <div class="kt-widget1">
                        <div class="kt-widget1__item">
                            <div class="kt-widget1__info">
                                <h3 class="kt-widget1__title">Total Requests</h3>
                                <p class="kt-widget1__desc">All upload requests</p>
                            </div>
                            <span class="kt-widget1__number kt-font-brand">{{ $stats['total_requests'] ?? 0 }}</span>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6">
                    <div class="kt-widget1">
                        <div class="kt-widget1__item">
                            <div class="kt-widget1__info">
                                <h3 class="kt-widget1__title">Active Requests</h3>
                                <p class="kt-widget1__desc">Currently active</p>
                            </div>
                            <span class="kt-widget1__number kt-font-info">{{ $stats['active_requests'] ?? 0 }}</span>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6">
                    <div class="kt-widget1">
                        <div class="kt-widget1__item">
                            <div class="kt-widget1__info">
                                <h3 class="kt-widget1__title">Completed Uploads</h3>
                                <p class="kt-widget1__desc">Successfully uploaded</p>
                            </div>
                            <span class="kt-widget1__number kt-font-success">{{ $stats['completed_uploads'] ?? 0 }}</span>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6">
                    <div class="kt-widget1">
                        <div class="kt-widget1__item">
                            <div class="kt-widget1__info">
                                <h3 class="kt-widget1__title">Verified Documents</h3>
                                <p class="kt-widget1__desc">Manually verified</p>
                            </div>
                            <span class="kt-widget1__number kt-font-warning">{{ $stats['verified_uploads'] ?? 0 }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- DataTable -->
            <div class="kt-portlet kt-portlet--mobile">
                <div class="kt-portlet__head kt-portlet__head--lg">
                    <div class="kt-portlet__head-label">
                        <span class="kt-portlet__head-icon">
                            <i class="kt-font-brand flaticon2-line-chart"></i>
                        </span>
                        <h3 class="kt-portlet__head-title">
                            Bank Statement Upload Requests
                        </h3>
                    </div>
                    <div class="kt-portlet__head-toolbar">
                        <div class="kt-portlet__head-wrapper">
                            <div class="kt-portlet__head-actions">
                                <button class="btn btn-brand btn-elevate btn-icon-sm" onclick="refreshTable()">
                                    <i class="la la-refresh"></i>
                                    Refresh
                                </button>
                                <button class="btn btn-success btn-elevate btn-icon-sm" onclick="exportData()">
                                    <i class="la la-download"></i>
                                    Export
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="kt-portlet__body">
                    <table class="table table-striped- table-bordered table-hover table-checkable" id="deduplication_checks_table">
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="selectAll"></th>
                                <th>ID</th>
                                <th>Customer</th>
                                <th>Phone</th>
                                <th>Omang</th>
                                <th>Passport</th>
                                <th>Bank Account</th>
                                <th>Status</th>
                                <th>Upload Status</th>
                                <th>Verification</th>
                                <th>OTP Verified</th>
                                <th>Link Opened</th>
                                <th>File Uploaded</th>
                                <th>Expires At</th>
                                <th>Created At</th>
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
        <!-- end:: Content -->
    </div>
</div>
<!-- end:: Root -->

<!-- Generate Links Modal -->
<div class="modal fade" id="generateLinksModal" tabindex="-1" role="dialog" aria-labelledby="generateLinksModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="generateLinksModalLabel">Generate Upload Links</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="generateLinksForm">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="customer_ids">Customer IDs (comma-separated)</label>
                        <input type="text" class="form-control" id="customer_ids" name="customer_ids" placeholder="1,2,3,4,5" required>
                        <small class="form-text text-muted">Enter customer IDs separated by commas</small>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="omang_numbers">Omang Numbers (optional)</label>
                                <input type="text" class="form-control" id="omang_numbers" name="omang_numbers" placeholder="123456789,987654321">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="passport_numbers">Passport Numbers (optional)</label>
                                <input type="text" class="form-control" id="passport_numbers" name="passport_numbers" placeholder="A1234567,B9876543">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="bank_account_numbers">Bank Account Numbers (optional)</label>
                                <input type="text" class="form-control" id="bank_account_numbers" name="bank_account_numbers" placeholder="1234567890,0987654321">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="cellphones">Phone Numbers (optional)</label>
                                <input type="text" class="form-control" id="cellphones" name="cellphones" placeholder="+26712345678,+26787654321">
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="emails">Email Addresses (optional)</label>
                        <input type="text" class="form-control" id="emails" name="emails" placeholder="john@example.com,jane@example.com">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Generate Links</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Update Verification Status Modal -->
<div class="modal fade" id="updateVerificationModal" tabindex="-1" role="dialog" aria-labelledby="updateVerificationModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="updateVerificationModalLabel">Update Verification Status</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="updateVerificationForm">
                <input type="hidden" id="verification_check_id" name="check_id">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="verification_status">Status</label>
                        <select class="form-control" id="verification_status" name="status" required>
                            <option value="">Select Status</option>
                            <option value="approved">Approved</option>
                            <option value="rejected">Rejected</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="verification_notes">Notes</label>
                        <textarea class="form-control" id="verification_notes" name="notes" rows="3" placeholder="Enter verification notes..."></textarea>
                    </div>
                    <div class="form-group">
                        <label for="verified_by">Verified By</label>
                        <select class="form-control" id="verified_by" name="verified_by" required>
                            <option value="">Select User</option>
                            @foreach(\AlphaDirect\User::all() as $user)
                                <option value="{{ $user->id }}">{{ $user->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Status</button>
                </div>
            </form>
  
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

<script src="{{ asset('assets/vendors/custom/datatables/datatables.bundle.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/general/bootstrap-select/dist/js/bootstrap-select.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/general/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}" type="text/javascript"></script>

<script>
$(document).ready(function() {
    // Initialize DataTable
    var table = $('#deduplication_checks_table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "{{ route('admin.deduplication-checks.datatable') }}",
            type: "POST",
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        },
        columns: [
            { data: 'checkbox', orderable: false, searchable: false },
            { data: 'id' },
            { data: 'customer' },
            { data: 'cellphone' },
            { data: 'omang' },
            { data: 'passport' },
            { data: 'bank_account' },
            { data: 'status' },
            { data: 'upload_status' },
            { data: 'verification_status' },
            { data: 'otp_verified' },
            { data: 'link_opened' },
            { data: 'file_uploaded' },
            { data: 'expires_at' },
            { data: 'created_at' },
            { data: 'actions', orderable: false, searchable: false }
        ],
        order: [[1, 'desc']],
        pageLength: 25,
        responsive: true,
        dom: 'Bfrtip',
        buttons: [
            'copy', 'csv', 'excel', 'pdf', 'print'
        ]
    });

    // Generate Links Form
    $('#generateLinksForm').on('submit', function(e) {
        e.preventDefault();
        
        var formData = {
            customer_ids: $('#customer_ids').val().split(',').map(id => parseInt(id.trim())),
            omang_numbers: $('#omang_numbers').val() ? $('#omang_numbers').val().split(',') : [],
            passport_numbers: $('#passport_numbers').val() ? $('#passport_numbers').val().split(',') : [],
            bank_account_numbers: $('#bank_account_numbers').val() ? $('#bank_account_numbers').val().split(',') : [],
            cellphones: $('#cellphones').val() ? $('#cellphones').val().split(',') : [],
            emails: $('#emails').val() ? $('#emails').val().split(',') : []
        };

        $.ajax({
            url: "{{ route('admin.deduplication-checks.generate-links') }}",
            type: 'POST',
            data: formData,
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                if (response.success) {
                    alert('Successfully generated ' + response.data.total_generated + ' links');
                    $('#generateLinksModal').modal('hide');
                    table.ajax.reload();
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function(xhr) {
                var errors = xhr.responseJSON.errors;
                var errorMessage = 'Validation errors:\n';
                for (var field in errors) {
                    errorMessage += field + ': ' + errors[field].join(', ') + '\n';
                }
                alert(errorMessage);
            }
        });
    });

    // Update Verification Status Form
    $('#updateVerificationForm').on('submit', function(e) {
        e.preventDefault();
        
        var formData = {
            check_id: $('#verification_check_id').val(),
            status: $('#verification_status').val(),
            notes: $('#verification_notes').val(),
            verified_by: $('#verified_by').val()
        };

        $.ajax({
            url: "{{ route('admin.deduplication-checks.update-verification', '') }}/" + formData.check_id,
            type: 'PUT',
            data: formData,
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                if (response.success) {
                    alert('Verification status updated successfully');
                    $('#updateVerificationModal').modal('hide');
                    table.ajax.reload();
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function(xhr) {
                var errors = xhr.responseJSON.errors;
                var errorMessage = 'Validation errors:\n';
                for (var field in errors) {
                    errorMessage += field + ': ' + errors[field].join(', ') + '\n';
                }
                alert(errorMessage);
            }
        });
    });
});

function refreshTable() {
    $('#deduplication_checks_table').DataTable().ajax.reload();
}

function exportData() {
    // Implement export functionality
    alert('Export functionality will be implemented');
}

function updateVerificationStatus(checkId) {
    $('#verification_check_id').val(checkId);
    $('#updateVerificationModal').modal('show');
}

function resendNotification(checkId) {
    if (confirm('Are you sure you want to resend notification for this request?')) {
        // Implement resend notification functionality
        alert('Resend notification functionality will be implemented');
    }
}
</script>

</body>
</html>
