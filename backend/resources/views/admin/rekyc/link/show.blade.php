<!DOCTYPE html>
<html lang="en" >

@include('admin.layouts.header')

<link href="{{  asset('assets/vendors/custom/datatables/datatables.bundle.css') }}" rel="stylesheet" type="text/css" />
<link href="{{  asset('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css') }}" rel="stylesheet" type="text/css" />
<link href="{{  asset('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}" rel="stylesheet" type="text/css" />

<style>
/* Custom CSS for Re-KYC Link Details Page */
.timeline {
    position: relative;
    padding: 0;
    list-style: none;
}

.timeline:before {
    content: '';
    position: absolute;
    top: 0;
    bottom: 0;
    left: 20px;
    width: 2px;
    background: #007bff;
    border-radius: 1px;
}

.timeline > div {
    position: relative;
    margin-bottom: 20px;
    padding-left: 50px;
}

.timeline > div:before {
    content: '';
    position: absolute;
    left: 12px;
    top: 8px;
    width: 16px;
    height: 16px;
    background: #007bff;
    border-radius: 50%;
    border: 3px solid #fff;
    box-shadow: 0 0 0 2px #007bff;
}

.timeline > div:last-child:before {
    background: #fff;
    border: 3px solid #007bff;
}

.timeline-item {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 15px;
    margin-left: 10px;
    position: relative;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.timeline-item:before {
    content: '';
    position: absolute;
    left: -8px;
    top: 20px;
    width: 0;
    height: 0;
    border-top: 8px solid transparent;
    border-bottom: 8px solid transparent;
    border-right: 8px solid #e9ecef;
}

.timeline-item:after {
    content: '';
    position: absolute;
    left: -7px;
    top: 21px;
    width: 0;
    height: 0;
    border-top: 7px solid transparent;
    border-bottom: 7px solid transparent;
    border-right: 7px solid #f8f9fa;
}

.timeline-header {
    font-weight: 600;
    color: #495057;
    margin-bottom: 8px;
    font-size: 16px;
}

.timeline-body {
    color: #6c757d;
    font-size: 14px;
    line-height: 1.4;
}

.time {
    color: #6c757d;
    font-size: 12px;
    font-weight: 500;
    display: block;
    margin-bottom: 5px;
}

.time i {
    margin-right: 5px;
}

.time-label {
    margin-bottom: 20px;
}

.time-label span {
    background: #007bff;
    color: white;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
}

.card {
    border: none;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    border-radius: 12px;
    margin-bottom: 20px;
}

.card-header {
    background: #f8f9fa;
    border-bottom: 1px solid #e9ecef;
    border-radius: 12px 12px 0 0 !important;
    padding: 20px;
}

.card-title {
    color: #495057;
    font-weight: 600;
    margin: 0;
    font-size: 18px;
}

.card-title i {
    color: #007bff;
    margin-right: 8px;
}

.btn-group .btn {
    border-radius: 6px;
    margin-right: 5px;
    transition: all 0.3s ease;
}

.btn-group .btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}

.btn-success {
    background: #28a745;
    border-color: #28a745;
}

.btn-info {
    background: #007bff;
    border-color: #007bff;
}

.btn-outline-secondary {
    color: #6c757d;
    border-color: #dee2e6;
}

.btn-outline-secondary:hover {
    background: #6c757d;
    border-color: #6c757d;
    color: white;
}

.badge {
    font-size: 11px;
    padding: 6px 12px;
    border-radius: 12px;
    font-weight: 500;
}

.badge-lg {
    font-size: 12px;
    padding: 8px 16px;
}

.table-borderless td {
    border: none;
    padding: 8px 0;
    vertical-align: top;
}

.table-borderless td:first-child {
    width: 140px;
    color: #6c757d;
    font-weight: 500;
}

code {
    background: #f8f9fa;
    color: #e83e8c;
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 13px;
}

.text-muted {
    color: #6c757d !important;
}

.text-danger {
    color: #dc3545 !important;
}

.text-warning {
    color: #ffc107 !important;
}

/* Responsive improvements */
@media (max-width: 768px) {
    .timeline:before {
        left: 15px;
    }
    
    .timeline > div {
        padding-left: 40px;
    }
    
    .timeline > div:before {
        left: 7px;
        width: 14px;
        height: 14px;
    }
    
    .timeline-item {
        margin-left: 5px;
        padding: 15px;
    }
    
    .card-header {
        padding: 15px;
    }
    
    .card-title {
        font-size: 18px;
    }
    
    .btn-group .btn {
        margin-bottom: 5px;
        padding: 6px 10px;
        font-size: 12px;
    }
    
    .info-item {
        margin-bottom: 20px;
    }
    
    .info-label {
        font-size: 12px;
    }
    
    .info-value {
        font-size: 13px;
    }
    
    .badge {
        font-size: 10px;
        padding: 6px 12px;
    }
    
    .badge-lg {
        font-size: 11px;
        padding: 8px 16px;
    }
}

@media (max-width: 576px) {
    .container-fluid {
        padding: 10px;
    }
    
    .card {
        margin-bottom: 15px;
    }
    
    .card-header {
        padding: 12px;
    }
    
    .card-body {
        padding: 15px;
    }
    
    .timeline-item {
        padding: 12px;
        margin-left: 0;
    }
    
    .timeline > div {
        padding-left: 30px;
    }
    
    .timeline:before {
        left: 10px;
    }
    
    .timeline > div:before {
        left: 2px;
        width: 12px;
        height: 12px;
    }
    
    .btn-group {
        display: flex;
        flex-direction: column;
        gap: 5px;
    }
    
    .btn-group .btn {
        margin-right: 0;
        margin-bottom: 0;
    }
}

/* Status badge colors */
.badge-success {
    background: #28a745;
}

.badge-warning {
    background: #ffc107;
    color: #212529;
}

.badge-danger {
    background: #dc3545;
}

.badge-info {
    background: #17a2b8;
}

/* Icon colors for timeline */
.fas.fa-plus.bg-primary {
    background: #007bff !important;
}

.fas.fa-paper-plane.bg-success {
    background: #28a745 !important;
}

.fas.fa-eye.bg-info {
    background: #17a2b8 !important;
}

.fas.fa-key.bg-warning {
    background: #ffc107 !important;
    color: #212529;
}

.fas.fa-check-circle.bg-success {
    background: #28a745 !important;
}

/* Info item styling */
.info-item {
    margin-bottom: 15px;
}

.info-label {
    display: block;
    font-weight: 600;
    color: #6c757d;
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 5px;
}

.info-value {
    color: #495057;
    font-size: 14px;
    line-height: 1.4;
}

.info-value a {
    color: #007bff;
    text-decoration: none;
    font-weight: 500;
}

.info-value a:hover {
    color: #0056b3;
    text-decoration: underline;
}

/* Enhanced card styling */
.card-header {
    border-bottom: 2px solid #e9ecef;
    background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
}

.card-title {
    display: flex;
    align-items: center;
    color: #495057;
    font-weight: 700;
    margin: 0;
    font-size: 20px;
}

.card-title i {
    color: #007bff;
    margin-right: 10px;
    font-size: 18px;
}

/* Button enhancements */
.btn-group .btn {
    border-radius: 8px;
    margin-right: 8px;
    padding: 8px 12px;
    font-weight: 500;
    transition: all 0.3s ease;
    border: none;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.btn-group .btn:last-child {
    margin-right: 0;
}

.btn-group .btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.btn-success {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    color: white;
}

.btn-success:hover {
    background: linear-gradient(135deg, #218838 0%, #1ea085 100%);
    color: white;
}

.btn-info {
    background: linear-gradient(135deg, #007bff 0%, #6610f2 100%);
    color: white;
}

.btn-info:hover {
    background: linear-gradient(135deg, #0056b3 0%, #520dc2 100%);
    color: white;
}

/* Badge improvements */
.badge {
    font-size: 11px;
    padding: 8px 16px;
    border-radius: 20px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.badge-lg {
    font-size: 12px;
    padding: 10px 20px;
}

/* Timeline improvements */
.timeline-item {
    background: #ffffff;
    border: 1px solid #e9ecef;
    border-radius: 12px;
    padding: 20px;
    margin-left: 15px;
    position: relative;
    box-shadow: 0 4px 6px rgba(0,0,0,0.07);
    transition: all 0.3s ease;
}

.timeline-item:hover {
    box-shadow: 0 6px 12px rgba(0,0,0,0.1);
    transform: translateY(-1px);
}

.timeline-header {
    font-weight: 700;
    color: #495057;
    margin-bottom: 10px;
    font-size: 16px;
    display: flex;
    align-items: center;
}

.timeline-body {
    color: #6c757d;
    font-size: 14px;
    line-height: 1.5;
}

/* Status colors */
.bg-success {
    background: #28a745 !important;
}

.bg-info {
    background: #007bff !important;
}

.bg-warning {
    background: #ffc107 !important;
    color: #212529 !important;
}

.bg-danger {
    background: #dc3545 !important;
}

/* Additional styling to match the image */
.badge-info {
    background: #007bff !important;
    color: white !important;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Ensure empty fields show properly */
.table-borderless td:empty::after {
    content: '';
}

/* Improve the overall card styling to match the image */
.card {
    border: 1px solid #e9ecef;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.card-header {
    background: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
}

/* Timeline improvements to match the image */
.timeline:before {
    background: #007bff;
    width: 2px;
}

.timeline > div:before {
    background: #007bff;
    border: 2px solid #fff;
    box-shadow: 0 0 0 2px #007bff;
}

/* Button styling to match the image */
.btn-group .btn {
    border-radius: 4px;
    font-size: 12px;
    padding: 6px 10px;
}

.btn-success {
    background: #28a745;
    border-color: #28a745;
}

.btn-info {
    background: #007bff;
    border-color: #007bff;
}

/* Document viewer modal styles */
#documentViewerModal .modal-dialog {
    max-width: 95vw;
    width: 95vw;
}

#documentViewerModal .modal-content {
    height: 90vh;
    display: flex;
    flex-direction: column;
}

#documentViewerModal .modal-body {
    flex: 1;
    padding: 0;
    overflow: hidden;
}

.document-viewer-container {
    height: 100%;
    position: relative;
}

.document-viewer-container iframe {
    width: 100%;
    height: 100%;
    border: none;
}

.document-viewer-container img {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
    display: block;
    margin: 0 auto;
}

/* Loading spinner styles */
.spinner-border {
    width: 3rem;
    height: 3rem;
}

/* Toast notification improvements */
.toast-notification {
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    border-radius: 8px;
    font-size: 14px;
    font-weight: 500;
}

/* Document card hover effects */
.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    transition: all 0.3s ease;
}

/* Button hover effects for document actions */
.btn-outline-primary:hover,
.btn-outline-success:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
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
<!-- begin::Body -->
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <!-- Link Header -->
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="flex-grow-1">
                            <h3 class="card-title mb-2">
                                <i class="fas fa-link mr-2"></i>
                                Link Details #{{ $link->id }}
                            </h3>
                            <p class="text-muted mb-0">
                                Customer: <strong>{{ $link->customer ? $link->customer->fullName : 'Unknown' }}</strong>
                                @if($link->customer && $link->customer->email)
                                    ({{ $link->customer->email }})
                                @endif
                            </p>
                        </div>
                        <div class="ml-3">
                            <span class="badge badge-info badge-lg">
                                SENT
                            </span>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <div class="info-item">
                                <label class="info-label">Campaign:</label>
                                <div class="info-value">
                                    <a href="{{ route('admin.rekyc.campaign.show', $link->campaign_id) }}" class="text-primary">
                                        {{ $link->campaign->name ?? 'Auto Re-KYC Campaign' }}
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="info-item">
                                <label class="info-label">Created:</label>
                                <div class="info-value">{{ $link->created_at->format('M d, Y H:i') }}</div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="info-item">
                                <label class="info-label">Expires:</label>
                                <div class="info-value">
                                    @if($link->expires_at)
                                        <span class="text-{{ $link->expires_at->isPast() ? 'danger' : ($link->expires_at->isToday() ? 'warning' : 'muted') }}">
                                            {{ $link->expires_at->format('M d, Y H:i') }}
                                        </span>
                                    @else
                                        <span class="text-muted">Never</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="info-item">
                                <label class="info-label">Actions:</label>
                                <div class="info-value">
                                    <div class="btn-group" role="group">
                                        <button type="button" class="btn btn-sm btn-success" 
                                                onclick="resendNotification({{ $link->id }})" title="Resend Notification">
                                            <i class="fas fa-paper-plane"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-info" 
                                                onclick="viewLinkDetails()" title="View Link">
                                            <i class="fas fa-external-link-alt"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Link Status Timeline -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-timeline mr-2"></i>
                        Link Status Timeline
                    </h3>
                </div>
                <div class="card-body">
                    <div class="timeline">
                        <div class="time-label">
                            <span class="bg-{{ $link->created_at->isToday() ? 'success' : 'info' }}">
                                {{ $link->created_at->format('M d, Y') }}
                            </span>
                        </div>
                        
                        <!-- Link Created -->
                        <div>
                            <div class="timeline-item">
                                <span class="time">
                                    <i class="fas fa-clock"></i> {{ $link->created_at->format('H:i') }}
                                </span>
                                <h3 class="timeline-header">Link Created</h3>
                                <div class="timeline-body">
                                    Re-KYC link generated for customer
                                </div>
                            </div>
                        </div>

                        <!-- Link Sent -->
                        @if($link->sent_at)
                        <div>
                            <div class="timeline-item">
                                <span class="time">
                                    <i class="fas fa-clock"></i> {{ $link->sent_at->format('H:i') }}
                                </span>
                                <h3 class="timeline-header">Link Sent</h3>
                                <div class="timeline-body">
                                    Notification sent via {{ $link->delivery_method ?? 'whatsapp' }}
                                    @if($link->delivery_reference)
                                        <br><small class="text-muted">Reference: {{ $link->delivery_reference }}</small>
                                    @endif
                                </div>
                            </div>
                        </div>
                        @endif

                        <!-- Link Opened -->
                        @if($link->opened_at)
                        <div>
                            <div class="timeline-item">
                                <span class="time">
                                    <i class="fas fa-clock"></i> {{ $link->opened_at->format('H:i') }}
                                </span>
                                <h3 class="timeline-header">Link Opened</h3>
                                <div class="timeline-body">
                                    Customer accessed the Re-KYC link
                                    @if($link->opened_at && $link->sent_at)
                                        <br><small class="text-muted">
                                            Response time: {{ $link->sent_at->diffInMinutes($link->opened_at) }} minutes
                                        </small>
                                    @endif
                                </div>
                            </div>
                        </div>
                        @endif

                        <!-- OTP Verified -->
                        @if($link->otp_verified_at)
                        <div>
                            <div class="timeline-item">
                                <span class="time">
                                    <i class="fas fa-clock"></i> {{ $link->otp_verified_at->format('H:i') }}
                                </span>
                                <h3 class="timeline-header">OTP Verified</h3>
                                <div class="timeline-body">
                                    Customer successfully verified OTP
                                </div>
                            </div>
                        </div>
                        @endif

                        <!-- Link Completed -->
                        @if($link->completed_at)
                        <div>
                            <div class="timeline-item">
                                <span class="time">
                                    <i class="fas fa-clock"></i> {{ $link->completed_at->format('H:i') }}
                                </span>
                                <h3 class="timeline-header">Link Completed</h3>
                                <div class="timeline-body">
                                    Customer completed the Re-KYC process
                                </div>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Link Details -->
            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-info-circle mr-2"></i>
                                Link Information
                            </h3>
                        </div>
                        <div class="card-body">
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>Link ID:</strong></td>
                                    <td>{{ $link->id }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Token:</strong></td>
                                    <td>
                                        <code>{{ Str::limit($link->unique_token, 20) }}...</code>
                                        <button class="btn btn-sm btn-outline-secondary ml-2" 
                                                onclick="copyToClipboard('{{ $link->unique_token }}')">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Delivery Method:</strong></td>
                                    <td>
                                        @if($link->delivery_method)
                                            <span class="badge badge-info">{{ strtoupper($link->delivery_method) }}</span>
                                        @else
                                            <span class="badge badge-info">WHATSAPP</span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Delivery Reference:</strong></td>
                                    <td>{{ $link->delivery_reference ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>IP Address:</strong></td>
                                    <td>{{ $link->ip_address ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>User Agent:</strong></td>
                                    <td>
                                        @if($link->user_agent)
                                            <small class="text-muted">{{ Str::limit($link->user_agent, 50) }}</small>
                                        @else
                                            N/A
                                        @endif
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-user mr-2"></i>
                                Customer Information
                            </h3>
                        </div>
                        <div class="card-body">
                            @if($link->customer)
                                <table class="table table-borderless">
                                    <tr>
                                        <td><strong>Name:</strong></td>
                                        <td>{{ $link->customer ? $link->customer->fullName : 'N/A' }}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Email:</strong></td>
                                        <td>{{ $link->customer->email }}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Phone:</strong></td>
                                        <td>{{ $link->customer->cellphone ?? 'N/A' }}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Customer ID:</strong></td>
                                        <td>{{ $link->customer->id }}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Created:</strong></td>
                                        <td>{{ $link->customer->created_at->format('M d, Y') }}</td>
                                    </tr>
                                </table>
                            @else
                                <p class="text-muted">Customer information not available</p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- Activities -->
            @if($activities->count() > 0)
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-history mr-2"></i>
                        Activities ({{ $activities->count() }})
                    </h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Time</th>
                                    <th>Action</th>
                                    <th>Description</th>
                                    <th>IP Address</th>
                                    <th>Metadata</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($activities as $activity)
                                <tr>
                                    <td>{{ $activity->created_at->format('M d, Y H:i:s') }}</td>
                                    <td>
                                        <span class="badge badge-{{ 
                                            $activity->action === 'link_sent' ? 'success' : 
                                            ($activity->action === 'link_opened' ? 'info' : 
                                            ($activity->action === 'otp_verified' ? 'warning' : 'primary')) 
                                        }}">
                                            {{ ucwords(str_replace('_', ' ', $activity->action)) }}
                                        </span>
                                    </td>
                                    <td>{{ $activity->description }}</td>
                                    <td>{{ $activity->ip_address ?? 'N/A' }}</td>
                                    <td>
                                        @if($activity->metadata)
                                            <button class="btn btn-sm btn-outline-info" 
                                                    onclick="showMetadata('{{ $activity->id }}')">
                                                <i class="fas fa-info-circle"></i>
                                            </button>
                                        @else
                                            N/A
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endif

            <!-- Documents -->
            @if($documents->count() > 0)
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-file-alt mr-2"></i>
                        Uploaded Documents ({{ $documents->count() }})
                    </h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        @foreach($documents as $document)
                        <div class="col-md-4 mb-3">
                            <div class="card">
                                <div class="card-body">
                                    <h6 class="card-title">
                                        <i class="fas fa-file-{{ $document->mime_type === 'application/pdf' ? 'pdf' : 'image' }} mr-1"></i>
                                        {{ ucwords(str_replace('_', ' ', $document->document_type)) }}
                                    </h6>
                                    <p class="card-text">
                                        <small class="text-muted">
                                            Size: {{ number_format($document->file_size / 1024, 2) }} KB<br>
                                            Type: {{ $document->mime_type }}<br>
                                            Status: 
                                            <span class="badge badge-{{ 
                                                $document->status === 'approved' ? 'success' : 
                                                ($document->status === 'rejected' ? 'danger' : 'warning') 
                                            }}">
                                                {{ ucfirst($document->status) }}
                                            </span>
                                        </small>
                                    </p>
                                    <div class="btn-group" role="group">
                                        <button class="btn btn-sm btn-outline-primary" 
                                                onclick="viewDocument({{ $document->id }})">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                      
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
            @endif
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
                    <input type="hidden" id="resend_link_id" name="link_id" value="{{ $link->id }}">
                    <div class="form-group">
                        <label>Channels</label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="channels[]" value="email" id="resend_email" checked>
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

<!-- Metadata Modal -->
<div class="modal fade" id="metadataModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Activity Metadata</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <pre id="metadataContent" class="bg-light p-3"></pre>
            </div>
        </div>
    </div>
</div>
<!-- end::Body -->

@include('includes.footer')
<!-- end:: Footer -->
</div>
<!-- end:: Wrapper -->
</div>
<!-- end:: Page -->
</div>
<!-- end:: Root -->

@include('admin.layouts.scripts')

<script>
$(document).ready(function() {
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
            url: '{{ route("admin.rekyc.resend-notification") }}',
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
});

function resendNotification(linkId) {
    $('#resend_link_id').val(linkId);
    $('#resendNotificationModal').modal('show');
}

function viewLinkDetails() {
    //alert(linkId);
    // This would open the actual Re-KYC link in a new tab
    const linkUrl = '{{ env("START_URL")."rekyc/access" }}/' + '{{ $link->unique_token }}';
    window.open(linkUrl, '_blank');
}

function copyToClipboard(text) {
    // Check if clipboard API is supported
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(function() {
            // Show success message
            showToast('Token copied to clipboard!', 'success');
        }).catch(function(err) {
            console.error('Could not copy text: ', err);
            // Fallback to old method
            fallbackCopyTextToClipboard(text);
        });
    } else {
        // Fallback for older browsers
        fallbackCopyTextToClipboard(text);
    }
}

function fallbackCopyTextToClipboard(text) {
    var textArea = document.createElement("textarea");
    textArea.value = text;
    
    // Avoid scrolling to bottom
    textArea.style.top = "0";
    textArea.style.left = "0";
    textArea.style.position = "fixed";
    textArea.style.opacity = "0";
    
    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();
    
    try {
        var successful = document.execCommand('copy');
        if (successful) {
            showToast('Token copied to clipboard!', 'success');
        } else {
            showToast('Failed to copy token', 'error');
        }
    } catch (err) {
        console.error('Fallback: Could not copy text: ', err);
        showToast('Failed to copy token', 'error');
    }
    
    document.body.removeChild(textArea);
}

function showToast(message, type) {
    // Create toast element
    var toast = document.createElement('div');
    toast.className = 'toast-notification toast-' + type;
    toast.textContent = message;
    
    // Style the toast
    toast.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 12px 20px;
        border-radius: 6px;
        color: white;
        font-weight: 500;
        z-index: 9999;
        animation: slideIn 0.3s ease;
        max-width: 300px;
        word-wrap: break-word;
    `;
    
    // Set background color based on type
    if (type === 'success') {
        toast.style.backgroundColor = '#28a745';
    } else if (type === 'error') {
        toast.style.backgroundColor = '#dc3545';
    } else {
        toast.style.backgroundColor = '#007bff';
    }
    
    // Add animation styles
    var style = document.createElement('style');
    style.textContent = `
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        @keyframes slideOut {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(100%); opacity: 0; }
        }
    `;
    document.head.appendChild(style);
    
    // Add to page
    document.body.appendChild(toast);
    
    // Remove after 3 seconds
    setTimeout(function() {
        toast.style.animation = 'slideOut 0.3s ease';
        setTimeout(function() {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 300);
    }, 3000);
}

function showMetadata(activityId) {
    // This would load and display the metadata for the activity
    $('#metadataContent').text('Loading metadata...');
    $('#metadataModal').modal('show');
    
    // In a real implementation, you would fetch the metadata via AJAX
    setTimeout(() => {
        $('#metadataContent').text('Metadata not available');
    }, 1000);
}

function viewDocument(documentId) {
    // Show loading state
    showToast('Opening document...', 'info');
    
    // Use the web route directly for viewing
    const viewUrl = '/admin/rekyc/document/' + documentId + '/download';
    
    // Open document in new window/tab for viewing
    window.open(viewUrl, '_blank');
    
    showToast('Document opened in new window', 'success');
}

function downloadDocument(documentId) {
    // Show loading state
    showToast('Starting download...', 'info');
    
    // Use the web route for direct download
    const downloadUrl = '/rekyc/document/' + documentId + '/download';
    
    // Create a temporary link and trigger download
    const link = document.createElement('a');
    link.href = downloadUrl;
    link.download = '';
    link.style.display = 'none';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    showToast('Download started', 'success');
}



function showError() {
    $('#documentLoading').html('<div class="alert alert-danger">Failed to load document preview</div>');
}
</script>
</html>
