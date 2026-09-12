<!DOCTYPE html>
<html lang="en" >

@include('admin.layouts.header')

<link href="{{ asset('css/rekyc-fixes.css') }}" rel="stylesheet" type="text/css" />

<link href="{{  asset('assets/vendors/custom/datatables/datatables.bundle.css') }}" rel="stylesheet" type="text/css" />
<link href="{{  asset('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css') }}" rel="stylesheet" type="text/css" />
<link href="{{  asset('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}" rel="stylesheet" type="text/css" />

<style>
/* AD Group KYC Campaign Custom Styles */
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

/* Table UI Fixes */
#kycLinksTable {
    font-size: 13px;
}

#kycLinksTable th {
    font-weight: 600;
    color: #74788d;
    border-bottom: 2px solid #e4e6ef;
    padding: 12px 8px;
}

#kycLinksTable td {
    padding: 10px 8px;
    vertical-align: middle;
    border-bottom: 1px solid #f4f5f8;
}

/* Customer column styling */
#kycLinksTable td:nth-child(3) {
    padding: 8px;
}

#kycLinksTable td:nth-child(3) strong {
    display: block;
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 2px;
}

#kycLinksTable td:nth-child(3) small {
    color: #6c757d;
    font-size: 11px;
    word-break: break-all;
    line-height: 1.2;
}

/* Cellphone column alignment */
#kycLinksTable td:nth-child(4) {
    text-align: center;
    vertical-align: middle;
}

#kycLinksTable td:nth-child(4) i {
    vertical-align: middle;
    margin-right: 4px;
}

/* Status badges */
#kycLinksTable .badge {
    font-size: 10px;
    padding: 4px 6px;
    border-radius: 3px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Status indicators alignment */
#kycLinksTable td:nth-child(6),
#kycLinksTable td:nth-child(7),
#kycLinksTable td:nth-child(8),
#kycLinksTable td:nth-child(9) {
    text-align: center;
    vertical-align: middle;
}

#kycLinksTable td:nth-child(6) i,
#kycLinksTable td:nth-child(7) i,
#kycLinksTable td:nth-child(8) i,
#kycLinksTable td:nth-child(9) i {
    font-size: 14px;
    vertical-align: middle;
}

/* Expires column */
#kycLinksTable td:nth-child(10) {
    text-align: center;
    font-size: 11px;
}

/* Actions column */
#kycLinksTable td:nth-child(11) {
    text-align: center;
    vertical-align: middle;
}

#kycLinksTable .btn-group .btn {
    margin: 0 1px;
    padding: 4px 6px;
    font-size: 11px;
}

/* Consistent action button width */
#kycLinksTable .btn-group {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 80px;
}

/* Checkbox alignment */
#kycLinksTable td:nth-child(1) {
    text-align: center;
    vertical-align: middle;
}

#kycLinksTable td:nth-child(1) input[type="checkbox"] {
    margin: 0;
    vertical-align: middle;
}

/* ID column */
#kycLinksTable td:nth-child(2) {
    text-align: center;
    font-weight: 600;
    color: #495057;
}

/* Responsive adjustments */
@media (max-width: 1200px) {
    #kycLinksTable {
        font-size: 12px;
    }
    
    #kycLinksTable th,
    #kycLinksTable td {
        padding: 8px 6px;
    }
}

/* Loading state */
#kycLinksTable tbody tr.loading {
    opacity: 0.6;
}

/* Empty state */
#kycLinksTable tbody tr.empty-state td {
    text-align: center;
    padding: 40px 20px;
    color: #6c757d;
    font-style: italic;
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
    <!-- check if is first time login -->
<!-- begin::Body -->
<div class="container-fluid" style="padding: 20px;">
    <div class="row">
        <div class="col-12">
            <!-- Campaign Header -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3 class="card-title mb-1">
                                <i class="fas fa-bullhorn mr-2"></i>
                                {{ $campaign->name }}
                </h3>
                            <p class="text-white-50 mb-0">{{ $campaign->description }}</p>
                        </div>
                        <div>
                            <span class="badge badge-{{ $campaign->status === 'active' ? 'success' : ($campaign->status === 'completed' ? 'info' : 'secondary') }} badge-lg">
                                {{ ucfirst($campaign->status) }}
                            </span>
                </div>
            </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <div class="info-box bg-light p-3 rounded">
                                <div class="info-box-icon bg-info text-white">
                                    <i class="fas fa-calendar-plus"></i>
            </div>
                                <div class="info-box-content">
                                    <span class="info-box-text">Created</span>
                                    <span class="info-box-number">{{ $campaign->created_at->format('M d, Y H:i') }}</span>
        </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="info-box bg-light p-3 rounded">
                                <div class="info-box-icon bg-warning text-white">
                                    <i class="fas fa-exclamation-triangle"></i>
                                </div>
                                <div class="info-box-content">
                                    <span class="info-box-text">Escalation Days</span>
                                    <span class="info-box-number">{{ $campaign->escalation_days ?? 30 }} days</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="info-box bg-light p-3 rounded">
                                <div class="info-box-icon bg-success text-white">
                                    <i class="fas fa-bell"></i>
                                </div>
                                <div class="info-box-content">
                                    <span class="info-box-text">Reminder Days</span>
                                    <span class="info-box-number">{{ is_array($campaign->reminder_days) ? implode(', ', $campaign->reminder_days) : $campaign->reminder_days ?? '3, 7, 14' }} days</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="info-box bg-light p-3 rounded">
                                <div class="info-box-icon bg-primary text-white">
                                    <i class="fas fa-cogs"></i>
                                </div>
                                <div class="info-box-content">
                                    <span class="info-box-text">Actions</span>
                                    <div class="btn-group mt-2" role="group">
                                        <button type="button" class="btn btn-sm btn-warning" 
                                                onclick="editCampaign({{ $campaign->id }})" title="Edit Campaign">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-success" 
                                                onclick="sendBulkNotifications({{ $campaign->id }})" title="Send Notifications">
                                            <i class="fas fa-paper-plane"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-info" 
                                                onclick="sendReminders({{ $campaign->id }})" title="Send Reminders">
                                            <i class="fas fa-bell"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-danger" 
                                                onclick="sendEscalations({{ $campaign->id }})" title="Send Escalations">
                                            <i class="fas fa-exclamation-triangle"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Campaign Statistics -->
            <div class="row mb-4">
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h3 class="mb-0">{{ $stats['total_links'] ?? 0 }}</h3>
                                    <p class="mb-0">Total Links</p>
                                    <small>Sent: {{ $stats['sent_links'] ?? 0 }}</small>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-link fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h3 class="mb-0">{{ $stats['completed_links'] ?? 0 }}</h3>
                                    <p class="mb-0">Completed</p>
                                    <small>OTP Verified: {{ $stats['otp_verified'] ?? 0 }}</small>
                                    </div>
                                <div class="align-self-center">
                                    <i class="fas fa-check-circle fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h3 class="mb-0">{{ $stats['pending_links'] ?? 0 }}</h3>
                                    <p class="mb-0">Pending</p>
                                    <small>Expired: {{ $stats['expired_links'] ?? 0 }}</small>
                                    </div>
                                <div class="align-self-center">
                                    <i class="fas fa-clock fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="card bg-danger text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h3 class="mb-0">{{ $stats['completion_rate'] ?? 0 }}%</h3>
                                    <p class="mb-0">Completion Rate</p>
                                    <small>Avg Response: {{ $stats['response_time'] ?? 0 }}m</small>
                                    </div>
                                <div class="align-self-center">
                                    <i class="fas fa-chart-pie fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Export Section -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body text-center">
                            <h5 class="card-title">
                                <i class="fas fa-download mr-2"></i>
                                Export Campaign Data
                            </h5>
                            <p class="card-text">Download campaign data and statistics for analysis</p>
                            <button class="btn btn-primary btn-lg" onclick="exportCampaignData({{ $campaign->id }})">
                                <i class="fas fa-download mr-2"></i>
                                Export Data
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Links Table -->
            <div class="card">
                <div class="card-header bg-light">
                    <div class="d-flex justify-content-between align-items-center">
                        <h3 class="card-title mb-0">
                            <i class="fas fa-list mr-2"></i>
                            All Employees - Employer Group Details
                        </h3>
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-sm btn-primary" onclick="selectAllLinks()">
                                <i class="fas fa-check-square mr-1"></i> Select All
                                </button>
                            <button type="button" class="btn btn-sm btn-secondary" onclick="deselectAllLinks()">
                                <i class="fas fa-square mr-1"></i> Deselect All
                                </button>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped mb-0" id="kycLinksTable" style="table-layout: fixed;">
                            <thead class="thead-light">
                                <tr>
                                    <th width="40" class="text-center">
                                        <input type="checkbox" id="selectAll" onchange="toggleAllLinks()">
                                    </th>
                                    <th width="60" class="text-center">ID</th>
                                    <th width="200">Customer</th>
                                    <th width="120" class="text-center">Cellphone</th>
                                    <th width="100" class="text-center">Status</th>
                                    <th width="100" class="text-center">Sent</th>
                                    <th width="80" class="text-center">Opened</th>
                                    <th width="80" class="text-center">OTP Verified</th>
                                    <th width="80" class="text-center">Completed</th>
                                    <th width="120" class="text-center">Expires</th>
                                    <th width="120" class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Data will be loaded via AJAX -->
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-light">
                    <div class="pagination-wrapper">
                        <!-- Pagination will be handled by DataTables -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Resend Notification Modal -->
<div class="modal fade" id="resendNotificationModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Resend Notification</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form id="resendNotificationForm">
                <div class="modal-body">
                    <input type="hidden" id="resend_link_id" name="link_id">
                    <div class="form-group">
                        <label>Channels</label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="channels[]" value="email" id="resend_email">
                            <label class="form-check-label" for="resend_email">Email</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="channels[]" value="whatsapp" id="resend_whatsapp">
                            <label class="form-check-label" for="resend_whatsapp">WhatsApp</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="channels[]" value="sms" id="resend_sms">
                            <label class="form-check-label" for="resend_sms">SMS</label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="resend_message">Custom Message (Optional)</label>
                        <textarea class="form-control" id="resend_message" name="message" rows="3" 
                                  placeholder="Add a custom message to the notification..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Send Notification</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bulk Notifications Modal -->
<div class="modal fade" id="bulkNotificationsModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Send Bulk Notifications</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form id="bulkNotificationsForm">
                <div class="modal-body">
                    <input type="hidden" id="bulk_campaign_id" name="campaign_id" value="{{ $campaign->id }}">
                    <div class="form-group">
                        <label>Selected Links: <span id="selectedCount">0</span></label>
                        <div id="selectedLinksList" class="form-control" style="height: 100px; overflow-y: auto;">
                            <!-- Selected links will be shown here -->
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Channels</label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="channels[]" value="email" id="bulk_email">
                            <label class="form-check-label" for="bulk_email">Email</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="channels[]" value="whatsapp" id="bulk_whatsapp">
                            <label class="form-check-label" for="bulk_whatsapp">WhatsApp</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="channels[]" value="sms" id="bulk_sms">
                            <label class="form-check-label" for="bulk_sms">SMS</label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="bulk_message">Custom Message (Optional)</label>
                        <textarea class="form-control" id="bulk_message" name="message" rows="3" 
                                  placeholder="Add a custom message to the notification..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Send Notifications</button>
                </div>
            </form>
        </div>
    </div>
</div>
<!-- end::Body -->
@include('includes.footer')
</div>
@include('admin.layouts.scripts')

<script src="{{  asset('assets/vendors/custom/datatables/datatables.bundle.js') }}" type="text/javascript"></script>

<script>
$(document).ready(function() {
    // Initialize DataTable
 

    // Resend notification form submission
    $('#resendNotificationForm').on('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const channels = $('input[name="channels[]"]:checked').map(function() {
            return this.value;
        }).get();
        
        if (channels.length === 0) {
            alert('Please select at least one channel');
            return;
        }
        
        // Send AJAX request
        $.ajax({
            url: '{{ route("admin.ad-group-kyc.resend-notification") }}',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                if (response.success) {
                    alert('Notification sent successfully!');
                    $('#resendNotificationModal').modal('hide');
                    location.reload();
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function(xhr) {
                const response = JSON.parse(xhr.responseText);
                alert('Error: ' + (response.message || 'Failed to send notification'));
            }
        });
    });

    // Bulk notifications form submission
    $('#bulkNotificationsForm').on('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const selectedLinks = $('.link-checkbox:checked').map(function() {
            return this.value;
        }).get();
        
        if (selectedLinks.length === 0) {
            alert('Please select at least one link');
            return;
        }
        
        const channels = $('input[name="channels[]"]:checked').map(function() {
            return this.value;
        }).get();
        
        if (channels.length === 0) {
            alert('Please select at least one channel');
            return;
        }
        
        // Add selected links to form data
        selectedLinks.forEach(linkId => {
            formData.append('link_ids[]', linkId);
        });

        // Send AJAX request
        $.ajax({
            url: '{{ route("admin.ad-group-kyc.bulk-notifications") }}',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                if (response.success) {
                    alert('Bulk notifications sent successfully!');
                    $('#bulkNotificationsModal').modal('hide');
                    location.reload();
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function(xhr) {
                const response = JSON.parse(xhr.responseText);
                alert('Error: ' + (response.message || 'Failed to send notifications'));
            }
        });
    });

    // Update selected links display
    $('.link-checkbox').on('change', function() {
        updateSelectedLinks();
    });
});

function resendNotification(linkId) {
    $('#resend_link_id').val(linkId);
    $('#resendNotificationModal').modal('show');
}

function sendBulkNotifications(campaignId) {
    $('#bulk_campaign_id').val(campaignId);
    updateSelectedLinks();
    $('#bulkNotificationsModal').modal('show');
}

function sendReminders(campaignId) {
    if (confirm('Send reminder notifications to all pending links in this campaign?')) {
        $.ajax({
            url: '{{ route("admin.ad-group-kyc.send-reminders") }}',
            type: 'POST',
            data: {
                campaign_id: campaignId,
                channels: ['email', 'whatsapp', 'sms'],
                _token: $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                if (response.success) {
                    alert('Reminder notifications sent successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function(xhr) {
                const response = JSON.parse(xhr.responseText);
                alert('Error: ' + (response.message || 'Failed to send reminders'));
            }
        });
    }
}

function sendEscalations(campaignId) {
    if (confirm('Send escalation notifications to all overdue links in this campaign?')) {
        $.ajax({
            url: '{{ route("admin.ad-group-kyc.send-escalations") }}',
            type: 'POST',
            data: {
                campaign_id: campaignId,
                channels: ['email', 'whatsapp', 'sms'],
                _token: $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                if (response.success) {
                    alert('Escalation notifications sent successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function(xhr) {
                const response = JSON.parse(xhr.responseText);
                alert('Error: ' + (response.message || 'Failed to send escalations'));
            }
        });
    }
}

function exportCampaignData(campaignId) {
    // Show loading indicator
    var button = event.target;
    var originalText = button.innerHTML;
    button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Exporting...';
    button.disabled = true;
    
    // Create a temporary link to trigger download
    var link = document.createElement('a');
    link.href = '{{ route("admin.ad-group-kyc.export") }}?campaign_id=' + campaignId + '&format=csv';
    link.download = 'ad_group_kyc_campaign_' + campaignId + '_' + new Date().toISOString().slice(0, 19).replace(/:/g, '-') + '.csv';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    // Reset button after a short delay
    setTimeout(function() {
        button.innerHTML = originalText;
        button.disabled = false;
    }, 2000);
}

function selectAllLinks() {
    $('.link-checkbox').prop('checked', true);
    updateSelectedLinks();
}

function deselectAllLinks() {
    $('.link-checkbox').prop('checked', false);
    updateSelectedLinks();
}

function toggleAllLinks() {
    const isChecked = $('#selectAll').is(':checked');
    $('.link-checkbox').prop('checked', isChecked);
    updateSelectedLinks();
}

function updateSelectedLinks() {
    const selectedLinks = $('.link-checkbox:checked');
    const count = selectedLinks.length;
    $('#selectedCount').text(count);
    
    const linksList = $('#selectedLinksList');
    linksList.empty();
    
    if (count > 0) {
        selectedLinks.each(function() {
            const row = $(this).closest('tr');
            const linkId = $(this).val();
            const customerName = row.find('td:eq(2) strong').text();
            const status = row.find('td:eq(4) .badge').text();
            
            const linkHtml = `<div class="badge badge-info mr-1 mb-1">${customerName} (${status})</div>`;
            linksList.append(linkHtml);
        });
    } else {
        linksList.html('<span class="text-muted">No links selected</span>');
    }
}

function editCampaign(campaignId) {
    // Redirect to edit page or show edit modal
    window.location.href = '{{ route("admin.ad-group-kyc.campaign.edit", ":id") }}'.replace(':id', campaignId);
}

// DataTable initialization moved to after library loading
</script>

<script src="{{ asset('assets/vendors/custom/datatables/datatables.bundle.js') }}" type="text/javascript"></script>
<script>
// Re-initialize DataTable after library is loaded
$(document).ready(function() {
    console.log('jQuery loaded:', typeof $);
    console.log('DataTable available:', typeof $.fn.DataTable);
    
    if (typeof $.fn.DataTable === 'undefined') {
        console.error('DataTables library not loaded');
        alert('DataTables library not loaded. Please refresh the page.');
        return;
    }

    try {
        var table = $('#kycLinksTable').DataTable({
            responsive: true,
            searchDelay: 500,
            processing: true,
            language: {
                processing: "<img src='{{asset('img/loading.gif')}}'>"
            },
            serverSide: true,
            ajax: {
                url: '{{ route("admin.ad-group-kyc.campaign.links-data", $campaign->id) }}',
                type: 'GET',
                error: function(xhr, error, thrown) {
                    console.error('DataTable AJAX error:', xhr, error, thrown);
                    console.error('Response:', xhr.responseText);
                    alert('Error loading data: ' + (xhr.responseJSON?.message || 'Unknown error'));
                }
            },
            order: [[1, 'DESC']],
            columns: [
                {data: 'checkbox', orderable: false, searchable: false},
                {data: 'id'},
                {data: 'customer'},
                {data: 'cellphone'},
                {data: 'status'},
                {data: 'sent'},
                {data: 'opened'},
                {data: 'otp_verified'},
                {data: 'completed'},
                {data: 'expires_at'},
                {data: 'actions', orderable: false, searchable: false}
            ],
            drawCallback: function(settings) {
                console.log('DataTable draw callback executed');
                // Re-initialize checkbox functionality after each draw
                $('#selectAll').off('change').on('change', toggleAllLinks);
                $('.link-checkbox').off('change').on('change', updateSelectedLinks);
            }
        });

        console.log('DataTable initialized successfully');

        // Update the select all functionality to work with DataTable
        $('#selectAll').on('change', function() {
            const isChecked = $(this).is(':checked');
            $('.link-checkbox').prop('checked', isChecked);
            updateSelectedLinks();
        });

        // Update individual checkbox functionality
        $(document).on('change', '.link-checkbox', function() {
            updateSelectedLinks();
        });

        // Handle action button clicks (now using direct links and onclick handlers like ReKYC)

        $(document).on('click', '.generate-link-btn', function() {
            const policyId = $(this).data('policy-id');
            const customerId = $(this).data('customer-id');
            
            if (confirm('Generate KYC link for this employee?')) {
                $.ajax({
                    url: '{{ route("admin.ad-group-kyc.campaign.generate-links", $campaign->id) }}',
                    type: 'POST',
                    data: {
                        policy_ids: [policyId],
                        _token: $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('KYC link generated successfully!');
                            table.ajax.reload();
                        } else {
                            alert('Error: ' + response.message);
                        }
                    },
                    error: function(xhr) {
                        const response = JSON.parse(xhr.responseText);
                        alert('Error: ' + (response.message || 'Failed to generate KYC link'));
                    }
                });
            }
        });
    } catch (error) {
        console.error('DataTable initialization error:', error);
        alert('Error initializing DataTable: ' + error.message);
    }
});
</script>
</html>