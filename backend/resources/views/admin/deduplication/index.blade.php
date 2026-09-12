<!DOCTYPE html>
<html lang="en">

@include('admin.layouts.header')

<link href="{{ asset('css/rekyc-fixes.css') }}" rel="stylesheet" type="text/css" />


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

.kt-widget4__progress {
    margin-top: 5px;
}

.kt-widget4__progress-wrapper {
    position: relative;
    height: 6px;
    background: #f4f5f8;
    border-radius: 3px;
    overflow: hidden;
}

.kt-widget4__progress-value {
    position: absolute;
    top: 0;
    left: 0;
    height: 100%;
    border-radius: 3px;
    transition: width 0.3s ease;
}

.kt-widget4__progress-label {
    font-size: 12px;
    font-weight: 600;
    margin-top: 5px;
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

.kt-badge--secondary {
    background-color: #74788d;
    color: #fff;
}

.kt-badge--brand {
    background-color: #5867dd;
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

.kt-badge--inline {
    display: inline-block;
    margin-right: 5px;
}

.kt-datatable__cell {
    white-space: nowrap;
}

.kt-datatable__cell-wrapper {
    display: flex;
    align-items: center;
    gap: 5px;
}

.btn-clean {
    background: transparent;
    border: none;
    padding: 8px;
    border-radius: 4px;
    transition: all 0.3s ease;
}

.btn-clean:hover {
    background: #f4f5f8;
}

table th {
    background: #f8f9fa;
    border-bottom: 2px solid #e4e6ef;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-size: 12px;
}

table td {
    vertical-align: middle;
    border-bottom: 1px solid #f4f5f8;
}

table tbody tr:hover {
    background: #f8f9fa;
}

.status-badge {
    padding: 6px 12px;
    border-radius: 15px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.status-pending {
    background-color: #ffb822;
    color: #fff;
}

.status-completed {
    background-color: #1dc9b7;
    color: #fff;
}

.status-suspended {
    background-color: #fd27eb;
    color: #fff;
}

.status-expired {
    background-color: #74788d;
    color: #fff;
}

.status-active {
    background-color: #5d78ff;
    color: #fff;
}
</style>

<!-- begin::Body -->
<body class="kt-header--fixed kt-header-mobile--fixed kt-subheader--enabled kt-subheader--transparent kt-aside--enabled kt-aside--fixed kt-aside--minimize kt-page--loading">
    <!-- begin:: Header Mobile -->
    <div id="kt_header_mobile" class="kt-header-mobile kt-header-mobile--fixed">
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
                        Bank Statement Deduplication Management Dashboard
                    </h3>
                    <span class="kt-subheader__separator kt-hidden"></span>
                    <div class="kt-subheader__breadcrumbs">
                        <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                        <span class="kt-subheader__breadcrumbs-separator"></span> 
                        <a href="{{Route('admin-dashboard')}}" class="kt-subheader__breadcrumbs-link"> Dashboard </a> 
                        <span class="kt-subheader__breadcrumbs-separator"></span>
                        <span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">Deduplication Management</span>
                    </div>
                </div>
                <div class="kt-subheader__toolbar">
                    <div class="kt-subheader__wrapper">
                        <button type="button" class="btn btn-sm btn-elevate btn-secondary btn-elevate" onclick="testAjax()">
                            <span class="kt-opacity-11">Test AJAX</span>&nbsp; 
                            <i class="flaticon2-refresh kt-padding-l-5 kt-padding-r-0"></i>
                        </button>
                        <a href="{{ route('admin.deduplication.export') }}" class="btn btn-sm btn-elevate btn-brand btn-elevate ml-2">
                            <span class="kt-opacity-11">Export Data</span>&nbsp; 
                            <i class="flaticon2-download kt-padding-l-5 kt-padding-r-0"></i>
                        </a>
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
                                            <h3 class="kt-widget1__title" style="color: #5867dd; font-weight: 600;">{{ $stats['total_checks'] ?? 0 }}</h3>
                                            <span class="kt-widget1__desc" style="color: #74788d; font-size: 14px;">Total Checks</span>
                                        </div>
                                        <span class="kt-widget1__number kt-font-brand" style="font-size: 2.5rem;">
                                            <i class="flaticon2-bell-2"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-3 col-6">
                                <div class="kt-widget1">
                                    <div class="kt-widget1__item">
                                        <div class="kt-widget1__info">
                                            <h3 class="kt-widget1__title" style="color: #1dc9b7; font-weight: 600;">{{ $stats['completed_checks'] ?? 0 }}</h3>
                                            <span class="kt-widget1__desc" style="color: #74788d; font-size: 14px;">Completed</span>
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
                                            <h3 class="kt-widget1__title" style="color: #ffb822; font-weight: 600;">{{ $stats['uploaded_documents'] ?? 0 }}</h3>
                                            <span class="kt-widget1__desc" style="color: #74788d; font-size: 14px;">Documents Uploaded</span>
                                        </div>
                                        <span class="kt-widget1__number kt-font-warning" style="font-size: 2.5rem;">
                                            <i class="flaticon2-upload"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-3 col-6">
                                <div class="kt-widget1">
                                    <div class="kt-widget1__item">
                                        <div class="kt-widget1__info">
                                            <h3 class="kt-widget1__title" style="color: #fd27eb; font-weight: 600;">{{ $stats['completion_rate'] ?? 0 }}%</h3>
                                            <span class="kt-widget1__desc" style="color: #74788d; font-size: 14px;">Completion Rate</span>
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
                                            <h3 class="kt-widget1__title" style="color: #5867dd; font-weight: 600;">{{ $stats['active_checks'] ?? 0 }}</h3>
                                            <span class="kt-widget1__desc" style="color: #74788d; font-size: 12px;">Active</span>
                                        </div>
                                        <span class="kt-widget1__number kt-font-brand" style="font-size: 1.8rem;">
                                            <i class="flaticon2-play"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="kt-widget1">
                                    <div class="kt-widget1__item">
                                        <div class="kt-widget1__info">
                                            <h3 class="kt-widget1__title" style="color: #1dc9b7; font-weight: 600;">{{ $stats['verified_documents'] ?? 0 }}</h3>
                                            <span class="kt-widget1__desc" style="color: #74788d; font-size: 12px;">Verified</span>
                                        </div>
                                        <span class="kt-widget1__number kt-font-success" style="font-size: 1.8rem;">
                                            <i class="flaticon2-check-mark"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="kt-widget1">
                                    <div class="kt-widget1__item">
                                        <div class="kt-widget1__info">
                                            <h3 class="kt-widget1__title" style="color: #ffb822; font-weight: 600;">{{ $stats['pending_verification'] ?? 0 }}</h3>
                                            <span class="kt-widget1__desc" style="color: #74788d; font-size: 12px;">Pending Verification</span>
                                        </div>
                                        <span class="kt-widget1__number kt-font-warning" style="font-size: 1.8rem;">
                                            <i class="flaticon2-time"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="kt-widget1">
                                    <div class="kt-widget1__item">
                                        <div class="kt-widget1__info">
                                            <h3 class="kt-widget1__title" style="color: #fd27eb; font-weight: 600;">{{ $stats['suspended_checks'] ?? 0 }}</h3>
                                            <span class="kt-widget1__desc" style="color: #74788d; font-size: 12px;">Suspended</span>
                                        </div>
                                        <span class="kt-widget1__number kt-font-danger" style="font-size: 1.8rem;">
                                            <i class="flaticon2-warning"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="kt-widget1">
                                    <div class="kt-widget1__item">
                                        <div class="kt-widget1__info">
                                            <h3 class="kt-widget1__title" style="color: #74788d; font-weight: 600;">{{ $stats['expired_checks'] ?? 0 }}</h3>
                                            <span class="kt-widget1__desc" style="color: #74788d; font-size: 12px;">Expired</span>
                                        </div>
                                        <span class="kt-widget1__number kt-font-dark" style="font-size: 1.8rem;">
                                            <i class="flaticon2-time-1"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="kt-widget1">
                                    <div class="kt-widget1__item">
                                        <div class="kt-widget1__info">
                                            <h3 class="kt-widget1__title" style="color: #74788d; font-weight: 600;">{{ $stats['avg_response_time'] ?? 0 }}m</h3>
                                            <span class="kt-widget1__desc" style="color: #74788d; font-size: 12px;">Avg Response</span>
                                        </div>
                                        <span class="kt-widget1__number kt-font-dark" style="font-size: 1.8rem;">
                                            <i class="flaticon2-time-1"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- DeduplicationChecks Table -->
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered table-hover table-checkable" id="deduplicationTable">
                                <thead>
                                    <tr>
                                        <th style="color: #74788d; font-weight: 600;">ID</th>
                                        <th style="color: #74788d; font-weight: 600;">Customer</th>
                                        <th style="color: #74788d; font-weight: 600;">Status</th>
                                        <th style="color: #74788d; font-weight: 600;">Document Status</th>
                                        <th style="color: #74788d; font-weight: 600;">Verification</th>
                                        <th style="color: #74788d; font-weight: 600;">Bank Info</th>
                                        <th style="color: #74788d; font-weight: 600;">Created</th>
                                        <th style="color: #74788d; font-weight: 600;">Actions</th>
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

        <!-- Recent Activities -->
        @if($recentActivities->count() > 0)
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-light">
                        <div class="d-flex justify-content-between align-items-center">
                            <h3 class="card-title mb-0">
                                <i class="fas fa-history mr-2 text-primary"></i>
                                Recent Activities
                            </h3>
                            <span class="badge badge-primary">{{ $recentActivities->count() }} activities</span>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                            @foreach($recentActivities as $activity)
                            <div class="list-group-item border-0 py-3">
                                <div class="d-flex align-items-start">
                                    <div class="flex-shrink-0 me-3">
                                        <div class="bg-{{ $activity->activity === 'Document uploaded' ? 'success' : ($activity->activity === 'Link opened' ? 'info' : ($activity->activity === 'Document verified' ? 'primary' : 'warning')) }} rounded-circle d-flex align-items-center justify-content-center" 
                                             style="width: 40px; height: 40px;">
                                            <i class="fas fa-{{ $activity->icon }} text-white"></i>
                                        </div>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="d-flex justify-content-between align-items-start mb-1">
                                            <h6 class="mb-0 text-dark">
                                                {{ $activity->activity }}
                                                <span class="text-muted">- {{ $activity->customer_name }}</span>
                                            </h6>
                                            <small class="text-muted">
                                                <i class="fas fa-clock mr-1"></i>
                                                {{ $activity->created_at->format('M d, H:i') }}
                                            </small>
                                        </div>
                                        <p class="mb-0 text-muted small">Check #{{ $activity->id }}</p>
                                    </div>
                                </div>
                            </div>
                            @if(!$loop->last)
                                <hr class="my-0">
                            @endif
                            @endforeach
                        </div>
                    </div>
                    <div class="card-footer bg-light text-center">
                        <small class="text-muted">
                            <i class="fas fa-info-circle mr-1"></i>
                            Showing latest {{ $recentActivities->count() }} activities
                        </small>
                    </div>
                </div>
            </div>
        </div>
        @endif
        @include('includes.footer')
        <!-- end:: Footer -->
    </div>
    <!-- end:: Wrapper -->
</div>
<!-- end:: Page -->
</div>
<!-- end:: Root -->

<!-- Resend Notification Modal -->
<div class="modal fade" id="resendNotificationModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Resend Bank Statement Upload Notification</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form id="resendNotificationForm">
                <div class="modal-body">
                    <input type="hidden" id="resend_check_id" name="check_id">
                    <div class="form-group">
                        <label>Customer Information</label>
                        <div id="customerInfo" class="alert alert-info">
                            <!-- Customer info will be loaded here -->
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Notification Channels</label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="channels[]" value="email" id="resend_email" checked>
                            <label class="form-check-label" for="resend_email">
                                <i class="fas fa-envelope mr-2"></i>Email
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="channels[]" value="whatsapp" id="resend_whatsapp">
                            <label class="form-check-label" for="resend_whatsapp">
                                <i class="fab fa-whatsapp mr-2" style="color: #25D366;"></i>WhatsApp
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="channels[]" value="sms" id="resend_sms">
                            <label class="form-check-label" for="resend_sms">
                                <i class="fas fa-sms mr-2" style="color: #007bff;"></i>SMS
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="resend_message">Custom Message (Optional)</label>
                        <textarea class="form-control" id="resend_message" name="message" rows="3" 
                                  placeholder="Add a custom message to the notification..."></textarea>
                        <small class="form-text text-muted">This message will be included in the notification sent to the customer.</small>
                    </div>
                    <div class="form-group">
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle mr-2"></i>
                            <strong>Note:</strong> This will resend the bank statement upload link to the customer. Make sure the customer's contact information is correct.
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="sendNotificationBtn">
                        <i class="fas fa-paper-plane mr-2"></i>Send Notification
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@include('admin.layouts.scripts')

<script src="{{  asset('assets/vendors/custom/datatables/datatables.bundle.js') }}" type="text/javascript"></script>
<script src="{{  asset('assets/vendors/general/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}" type="text/javascript"></script>
<script src="{{  asset('assets/vendors/custom/components/vendors/bootstrap-datepicker/init.js') }}" type="text/javascript"></script>
<script>
    $(function () {
        $('body').on('hidden.bs.modal', '.modal', function () {
            $(this).removeData('bs.modal');
        });
    });

    function testAjax() {
        console.log('Testing AJAX endpoint...');
        $.ajax({
            url: '{{ route("admin.deduplication.data") }}',
            type: 'GET',
            data: {
                draw: 1,
                start: 0,
                length: 10
            },
            success: function(response) {
                console.log('AJAX Success:', response);
                alert('AJAX Success! Check console for details.');
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                console.error('Status:', xhr.status);
                console.error('Response:', xhr.responseText);
                alert('AJAX Error: ' + error + ' (Status: ' + xhr.status + ')');
            }
        });
    }

    function resendNotification(checkId) {
        // Load customer information
        $.ajax({
            url: '{{ route("admin.deduplication.show", ":id") }}'.replace(':id', checkId),
            type: 'GET',
            success: function(response) {
                // Extract customer info from the response (you may need to adjust this based on your show method)
                $('#resend_check_id').val(checkId);
                $('#customerInfo').html('<strong>Loading customer information...</strong>');
                $('#resendNotificationModal').modal('show');
                
                // Load customer details via AJAX
                loadCustomerInfo(checkId);
            },
            error: function() {
                // Fallback if show method fails
                $('#resend_check_id').val(checkId);
                $('#customerInfo').html('<strong>Check ID:</strong> ' + checkId);
                $('#resendNotificationModal').modal('show');
            }
        });
    }

    function loadCustomerInfo(checkId) {
        $.ajax({
            url: '{{ route("admin.deduplication.data") }}',
            type: 'GET',
            data: {
                draw: 1,
                start: 0,
                length: 1,
                search: { value: checkId }
            },
            success: function(response) {
                if (response.data && response.data.length > 0) {
                    const check = response.data[0];
                    const customerInfo = `
                        <strong>Customer:</strong> ${check.customer_name}<br>
                        <strong>Email:</strong> ${check.email}<br>
                        <strong>Phone:</strong> ${check.phone}<br>
                        <strong>Status:</strong> ${check.status}
                    `;
                    $('#customerInfo').html(customerInfo);
                } else {
                    $('#customerInfo').html('<strong>Check ID:</strong> ' + checkId);
                }
            },
            error: function() {
                $('#customerInfo').html('<strong>Check ID:</strong> ' + checkId);
            }
        });
    }

    // Handle form submission
    $('#resendNotificationForm').on('submit', function(e) {
        e.preventDefault();
        
        const checkId = $('#resend_check_id').val();
        const channels = $('input[name="channels[]"]:checked').map(function() {
            return this.value;
        }).get();
        const message = $('#resend_message').val();
        
        if (channels.length === 0) {
            alert('Please select at least one notification channel.');
            return;
        }
        
        // Disable submit button
        $('#sendNotificationBtn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-2"></i>Sending...');
        
        $.ajax({
            url: '{{ route("admin.deduplication.resend-notification", ":id") }}'.replace(':id', checkId),
            type: 'POST',
            data: {
                channels: channels,
                message: message,
                _token: $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                if (response.success) {
                    alert('Notification sent successfully via: ' + channels.join(', '));
                    $('#resendNotificationModal').modal('hide');
                    // Reload the DataTable
                    $('#deduplicationTable').DataTable().ajax.reload();
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function(xhr) {
                const response = JSON.parse(xhr.responseText);
                alert('Error: ' + (response.message || 'Failed to send notification'));
            },
            complete: function() {
                // Re-enable submit button
                $('#sendNotificationBtn').prop('disabled', false).html('<i class="fas fa-paper-plane mr-2"></i>Send Notification');
            }
        });
    });
</script>

<script>
    "use strict";
    var KTDatatablesDataSourceAjaxServer = function() {
        var initTable1 = function() {
            var table = $('#deduplicationTable');
            // begin first table
            table.DataTable({
                responsive: true,
                searchDelay: 500, 
                language:{ 
                    processing : "<img src='{{asset('img/loading.gif')}}'>"
                },
                processing: true,
                serverSide: true,
                ajax: '{!! route('admin.deduplication.data') !!}',
                order: [0, 'DESC'],
                columns: [
                    {data: 'id'},
                    {
                        data: 'customer_name',
                        render: function(data, type, row) {
                            return '<strong style="color: #2c3e50; font-weight: 600;">' + data + '</strong>' +
                                   (row.email !== 'N/A' ? '<br><small style="color: #74788d;">' + row.email + '</small>' : '') +
                                   (row.phone !== 'N/A' ? '<br><small style="color: #74788d;">' + row.phone + '</small>' : '');
                        }
                    },
                    {data: 'status'},
                    {data: 'document_status'},
                    {data: 'verification_status'},
                    {data: 'bank_info'},
                    {
                        data: 'created_at',
                        render: function(data, type, row) {
                            return data + (row.days_since_created > 0 ? '<br><small style="color: #ffb822;">' + row.days_since_created + ' days ago</small>' : '');
                        }
                    },
                    {data: 'actions'},
                ],
            });
        };
        return {
            //main function to initiate the module
            init: function() {
                initTable1();
            }
        };
    }();
    jQuery(document).ready(function() {
        KTDatatablesDataSourceAjaxServer.init();
    });
</script>
</body>
</html>
