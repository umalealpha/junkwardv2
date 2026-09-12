<!DOCTYPE html>
<html lang="en">

@include('admin.layouts.header')

<link href="{{ asset('css/rekyc-fixes.css') }}" rel="stylesheet" type="text/css" />

<style>
/* DeduplicationChecks Show Page Styles */
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
    font-size: 1.5rem;
    margin-bottom: 5px;
    font-weight: 700;
}

.kt-widget1__desc {
    font-size: 14px;
    opacity: 0.8;
}

.status-badge {
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 14px;
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

.info-card {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 15px;
    border-left: 4px solid #5d78ff;
}

.activity-item {
    border-left: 3px solid #5d78ff;
    padding-left: 15px;
    margin-bottom: 15px;
    position: relative;
}

.activity-item::before {
    content: '';
    position: absolute;
    left: -6px;
    top: 8px;
    width: 10px;
    height: 10px;
    background: #5d78ff;
    border-radius: 50%;
}

.activity-item.completed {
    border-left-color: #1dc9b7;
}

.activity-item.completed::before {
    background: #1dc9b7;
}

.activity-item.warning {
    border-left-color: #ffb822;
}

.activity-item.warning::before {
    background: #ffb822;
}

.activity-item.danger {
    border-left-color: #fd27eb;
}

.activity-item.danger::before {
    background: #fd27eb;
}

.file-info {
    background: #e8f4fd;
    border: 1px solid #bee5eb;
    border-radius: 8px;
    padding: 15px;
    margin: 10px 0;
}

.btn-download {
    background: linear-gradient(45deg, #1dc9b7, #5d78ff);
    border: none;
    color: white;
    padding: 10px 20px;
    border-radius: 25px;
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn-download:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(29, 201, 183, 0.4);
    color: white;
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
                        Deduplication Check Details - #{{ $deduplicationCheck->id }}
                    </h3>
                    <span class="kt-subheader__separator kt-hidden"></span>
                    <div class="kt-subheader__breadcrumbs">
                        <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                        <span class="kt-subheader__breadcrumbs-separator"></span> 
                        <a href="{{Route('admin-dashboard')}}" class="kt-subheader__breadcrumbs-link"> Dashboard </a> 
                        <span class="kt-subheader__breadcrumbs-separator"></span>
                        <a href="{{ route('admin.deduplication.index') }}" class="kt-subheader__breadcrumbs-link">Deduplication Management</a>
                        <span class="kt-subheader__breadcrumbs-separator"></span>
                        <span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">Check #{{ $deduplicationCheck->id }}</span>
                    </div>
                </div>
                <div class="kt-subheader__toolbar">
                    <div class="kt-subheader__wrapper">
                        <a href="{{ route('admin.deduplication.edit', $deduplicationCheck->id) }}" class="btn btn-sm btn-elevate btn-brand btn-elevate">
                            <span class="kt-opacity-11">Edit Check</span>&nbsp; 
                            <i class="flaticon2-edit kt-padding-l-5 kt-padding-r-0"></i>
                        </a>
                        <a href="{{ route('admin.deduplication.index') }}" class="btn btn-sm btn-elevate btn-secondary btn-elevate ml-2">
                            <span class="kt-opacity-11">Back to List</span>&nbsp; 
                            <i class="flaticon2-left-arrow kt-padding-l-5 kt-padding-r-0"></i>
                        </a>
                    </div>
                </div>
            </div>
            <!-- end:: Subheader -->
            <!-- begin:: Content -->
            <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
                <div class="row">
                    <!-- Left Column - Main Info -->
                    <div class="col-lg-8">
                        <!-- Customer Information -->
                        <div class="kt-portlet">
                            <div class="kt-portlet__head">
                                <div class="kt-portlet__head-label">
                                    <h3 class="kt-portlet__head-title">
                                        <i class="fas fa-user mr-2"></i>Customer Information
                                    </h3>
                                </div>
                            </div>
                            <div class="kt-portlet__body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="info-card">
                                            <h6 class="text-muted mb-2">Customer Name</h6>
                                            <h5 class="mb-0">
                                                {{ $deduplicationCheck->customer ? $deduplicationCheck->customer->firstName . ' ' . $deduplicationCheck->customer->lastName : 'N/A' }}
                                            </h5>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="info-card">
                                            <h6 class="text-muted mb-2">Customer ID</h6>
                                            <h5 class="mb-0">{{ $deduplicationCheck->customer_id ?? 'N/A' }}</h5>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="info-card">
                                            <h6 class="text-muted mb-2">Email</h6>
                                            <h5 class="mb-0">{{ $deduplicationCheck->email ?? 'N/A' }}</h5>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="info-card">
                                            <h6 class="text-muted mb-2">Phone</h6>
                                            <h5 class="mb-0">{{ $deduplicationCheck->cellphone ?? 'N/A' }}</h5>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Document Information -->
                        <div class="kt-portlet">
                            <div class="kt-portlet__head">
                                <div class="kt-portlet__head-label">
                                    <h3 class="kt-portlet__head-title">
                                        <i class="fas fa-file-upload mr-2"></i>Document Information
                                    </h3>
                                </div>
                            </div>
                            <div class="kt-portlet__body">
                                @if($deduplicationCheck->bank_statement_upload_url)
                                    <div class="file-info">
                                        <div class="row">
                                            <div class="col-md-8">
                                                <h6 class="text-primary mb-2">
                                                    <i class="fas fa-file-pdf mr-2"></i>
                                                    {{ $deduplicationCheck->bank_statement_file_name ?? 'Bank Statement' }}
                                                </h6>
                                                <p class="mb-1">
                                                    <strong>File Size:</strong> {{ $deduplicationCheck->bank_statement_file_size ? number_format($deduplicationCheck->bank_statement_file_size / 1024, 2) . ' KB' : 'N/A' }}
                                                </p>
                                                <p class="mb-1">
                                                    <strong>File Type:</strong> {{ $deduplicationCheck->bank_statement_mime_type ?? 'N/A' }}
                                                </p>
                                                <p class="mb-0">
                                                    <strong>Uploaded:</strong> {{ $deduplicationCheck->updated_at->format('M d, Y H:i:s') }}
                                                </p>
                                            </div>
                                            <div class="col-md-4 text-right">
                                                <a href="{{ $deduplicationCheck->bank_statement_upload_url }}" 
                                                   target="_blank" class="btn btn-download">
                                                    <i class="fas fa-download mr-2"></i>Download
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                @else
                                    <div class="alert alert-warning">
                                        <i class="fas fa-exclamation-triangle mr-2"></i>
                                        No bank statement uploaded yet.
                                    </div>
                                @endif
                            </div>
                        </div>

                        <!-- Bank Information -->
                        @if($deduplicationCheck->bank_name || $deduplicationCheck->bank_account_number)
                        <div class="kt-portlet">
                            <div class="kt-portlet__head">
                                <div class="kt-portlet__head-label">
                                    <h3 class="kt-portlet__head-title">
                                        <i class="fas fa-university mr-2"></i>Bank Information
                                    </h3>
                                </div>
                            </div>
                            <div class="kt-portlet__body">
                                <div class="row">
                                    @if($deduplicationCheck->bank_name)
                                    <div class="col-md-6">
                                        <div class="info-card">
                                            <h6 class="text-muted mb-2">Bank Name</h6>
                                            <h5 class="mb-0">{{ $deduplicationCheck->bank_name }}</h5>
                                        </div>
                                    </div>
                                    @endif
                                    @if($deduplicationCheck->bank_account_number)
                                    <div class="col-md-6">
                                        <div class="info-card">
                                            <h6 class="text-muted mb-2">Account Number</h6>
                                            <h5 class="mb-0">****{{ substr($deduplicationCheck->bank_account_number, -4) }}</h5>
                                        </div>
                                    </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                        @endif

                        <!-- Activity Timeline -->
                        <div class="kt-portlet">
                            <div class="kt-portlet__head">
                                <div class="kt-portlet__head-label">
                                    <h3 class="kt-portlet__head-title">
                                        <i class="fas fa-history mr-2"></i>Activity Timeline
                                    </h3>
                                </div>
                            </div>
                            <div class="kt-portlet__body">
                                @forelse($activityLogs as $log)
                                <div class="activity-item {{ $log['action'] === 'verified' ? 'completed' : ($log['action'] === 'document_uploaded' ? 'completed' : ($log['action'] === 'opened' ? 'completed' : 'warning')) }}">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-1 text-dark">{{ $log['description'] }}</h6>
                                            <p class="mb-0 text-muted small">{{ $log['timestamp']->format('M d, Y H:i:s') }}</p>
                                        </div>
                                        <i class="fas fa-{{ $log['icon'] }} text-{{ $log['action'] === 'verified' ? 'success' : ($log['action'] === 'document_uploaded' ? 'success' : ($log['action'] === 'opened' ? 'info' : 'warning')) }}"></i>
                                    </div>
                                </div>
                                @empty
                                <div class="text-center text-muted py-4">
                                    <i class="fas fa-clock fa-2x mb-3"></i>
                                    <p>No activity logs available</p>
                                </div>
                                @endforelse
                            </div>
                        </div>
                    </div>

                    <!-- Right Column - Status & Actions -->
                    <div class="col-lg-4">
                        <!-- Status Overview -->
                        <div class="kt-portlet">
                            <div class="kt-portlet__head">
                                <div class="kt-portlet__head-label">
                                    <h3 class="kt-portlet__head-title">
                                        <i class="fas fa-info-circle mr-2"></i>Status Overview
                                    </h3>
                                </div>
                            </div>
                            <div class="kt-portlet__body">
                                <div class="text-center mb-4">
                                    <span class="status-badge status-{{ $deduplicationCheck->status }}">
                                        {{ strtoupper($deduplicationCheck->status) }}
                                    </span>
                                </div>
                                
                                <div class="kt-widget1 mb-3">
                                    <div class="kt-widget1__item">
                                        <div class="kt-widget1__info">
                                            <h3 class="kt-widget1__title" style="color: #5d78ff; font-weight: 600;">{{ $stats['days_since_created'] ?? 0 }}</h3>
                                            <span class="kt-widget1__desc" style="color: #74788d; font-size: 14px;">Days Since Created</span>
                                        </div>
                                        <span class="kt-widget1__number kt-font-brand" style="font-size: 2rem;">
                                            <i class="flaticon2-calendar"></i>
                                        </span>
                                    </div>
                                </div>

                                <div class="kt-widget1 mb-3">
                                    <div class="kt-widget1__item">
                                        <div class="kt-widget1__info">
                                            <h3 class="kt-widget1__title" style="color: #1dc9b7; font-weight: 600;">{{ $stats['otp_attempts'] ?? 0 }}</h3>
                                            <span class="kt-widget1__desc" style="color: #74788d; font-size: 14px;">OTP Attempts</span>
                                        </div>
                                        <span class="kt-widget1__number kt-font-success" style="font-size: 2rem;">
                                            <i class="flaticon2-lock"></i>
                                        </span>
                                    </div>
                                </div>

                                @if($stats['file_size'])
                                <div class="kt-widget1 mb-3">
                                    <div class="kt-widget1__item">
                                        <div class="kt-widget1__info">
                                            <h3 class="kt-widget1__title" style="color: #ffb822; font-weight: 600;">{{ number_format($stats['file_size'] / 1024, 2) }} KB</h3>
                                            <span class="kt-widget1__desc" style="color: #74788d; font-size: 14px;">File Size</span>
                                        </div>
                                        <span class="kt-widget1__number kt-font-warning" style="font-size: 2rem;">
                                            <i class="flaticon2-file"></i>
                                        </span>
                                    </div>
                                </div>
                                @endif
                            </div>
                        </div>

                        <!-- Verification Status -->
                        <div class="kt-portlet">
                            <div class="kt-portlet__head">
                                <div class="kt-portlet__head-label">
                                    <h3 class="kt-portlet__head-title">
                                        <i class="fas fa-check-double mr-2"></i>Verification Status
                                    </h3>
                                </div>
                            </div>
                            <div class="kt-portlet__body">
                                <div class="text-center mb-3">
                                    <span class="badge badge-{{ $deduplicationCheck->manual_verification_status === 'approved' ? 'success' : ($deduplicationCheck->manual_verification_status === 'rejected' ? 'danger' : 'warning') }} badge-lg">
                                        {{ strtoupper($deduplicationCheck->manual_verification_status) }}
                                    </span>
                                </div>
                                
                                @if($deduplicationCheck->verification_notes)
                                <div class="info-card">
                                    <h6 class="text-muted mb-2">Verification Notes</h6>
                                    <p class="mb-0">{{ $deduplicationCheck->verification_notes }}</p>
                                </div>
                                @endif

                                @if($deduplicationCheck->verified_by && $deduplicationCheck->verifier)
                                <div class="info-card">
                                    <h6 class="text-muted mb-2">Verified By</h6>
                                    <p class="mb-0">{{ $deduplicationCheck->verifier->name ?? 'Admin' }}</p>
                                    <small class="text-muted">{{ $deduplicationCheck->verified_at ? $deduplicationCheck->verified_at->format('M d, Y H:i:s') : 'N/A' }}</small>
                                </div>
                                @endif
                            </div>
                        </div>

                        <!-- Quick Actions -->
                        <div class="kt-portlet">
                            <div class="kt-portlet__head">
                                <div class="kt-portlet__head-label">
                                    <h3 class="kt-portlet__head-title">
                                        <i class="fas fa-bolt mr-2"></i>Quick Actions
                                    </h3>
                                </div>
                            </div>
                            <div class="kt-portlet__body">
                                <div class="d-grid gap-2">
                                    <a href="{{ route('admin.deduplication.edit', $deduplicationCheck->id) }}" 
                                       class="btn btn-warning btn-block">
                                        <i class="fas fa-edit mr-2"></i>Edit Check
                                    </a>
                                    
                                    @if($deduplicationCheck->status === 'active' && !$deduplicationCheck->otp_verified_at)
                                    <button type="button" class="btn btn-info btn-block" 
                                            onclick="resendNotification({{ $deduplicationCheck->id }})">
                                        <i class="fas fa-paper-plane mr-2"></i>Resend Notification
                                    </button>
                                    @endif

                                    @if($deduplicationCheck->bank_statement_upload_url)
                                    <a href="{{ $deduplicationCheck->bank_statement_upload_url }}" 
                                       target="_blank" class="btn btn-success btn-block">
                                        <i class="fas fa-download mr-2"></i>Download Document
                                    </a>
                                    @endif

                                    <a href="{{ route('admin.deduplication.index') }}" 
                                       class="btn btn-secondary btn-block">
                                        <i class="fas fa-arrow-left mr-2"></i>Back to List
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

   
    <!-- end:: Footer -->
</div>
@include('includes.footer')
<!-- end:: Wrapper -->
</div>

<!-- end:: Page -->
</div>
<!-- end:: Root -->

@include('admin.layouts.scripts')

<script>
function resendNotification(checkId) {
    if (confirm('Resend notification to customer?')) {
        $.ajax({
            url: '{{ route("admin.deduplication.resend-notification", ":id") }}'.replace(':id', checkId),
            type: 'POST',
            data: {
                _token: $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                if (response.success) {
                    alert('Notification sent successfully!');
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
    }
}
</script>
</body>
</html>
