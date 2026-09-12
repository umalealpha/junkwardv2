<!DOCTYPE html>
<html lang="en" >

@include('admin.layouts.header')

<link href="{{ asset('css/rekyc-fixes.css') }}" rel="stylesheet" type="text/css" />

<style>
.duplicate-details {
    background: #fff;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    border: 1px solid #e4e6ef;
    margin-bottom: 20px;
}

.info-row {
    display: flex;
    margin-bottom: 15px;
    padding-bottom: 15px;
    border-bottom: 1px solid #f4f5f8;
}

.info-row:last-child {
    border-bottom: none;
    margin-bottom: 0;
}

.info-label {
    font-weight: 600;
    color: #74788d;
    width: 150px;
    flex-shrink: 0;
}

.info-value {
    color: #2c3e50;
    flex: 1;
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
    background-color:rgb(17, 81, 75);
    color:rgb(20, 13, 13);
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

.status-pending { color: #ffb822; }
.status-reviewed { color: #5d78ff; }
.status-resolved { color: #1dc9b7; }
.status-ignored { color: #74788d; }

.json-data {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 4px;
    padding: 10px;
    font-family: monospace;
    font-size: 12px;
    max-height: 200px;
    overflow-y: auto;
}

/* Duplicate Details Formatted Styling */
.duplicate-details-formatted {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 6px;
    padding: 15px;
    margin-top: 10px;
}

.detail-item {
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid #e9ecef;
}

.detail-item:last-child {
    margin-bottom: 0;
    padding-bottom: 0;
    border-bottom: none;
}

.detail-item strong {
    color: #495057;
    font-size: 13px;
    display: block;
    margin-bottom: 5px;
}

.duplicate-value {
    background: #fff3cd;
    color: #856404;
    padding: 4px 8px;
    border-radius: 4px;
    font-family: monospace;
    font-size: 12px;
    border: 1px solid #ffeaa7;
    display: inline-block;
    margin-top: 3px;
}

.record-list, .account-list {
    margin-top: 5px;
}

.record-list .kt-badge, .account-list .kt-badge {
    margin-right: 5px;
    margin-bottom: 3px;
    font-size: 10px;
    padding: 2px 6px;
}

/* Enhanced styling for better readability */
.duplicate-details-formatted .detail-item:nth-child(even) {
    background: #f8f9fa;
    margin: 0 -15px;
    padding: 10px 15px;
    border-radius: 4px;
}

/* Duplicate Customer Cards Styling */
.duplicate-customer-card {
    background: #fff;
    border: 1px solid #e4e6ef;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
    height: 100%;
    display: flex;
    flex-direction: column;
}

.duplicate-customer-card:hover {
    box-shadow: 0 4px 16px rgba(0,0,0,0.15);
    transform: translateY(-2px);
}

.duplicate-customer-card .card-header {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-bottom: 1px solid #e4e6ef;
    padding: 15px 20px;
    border-radius: 8px 8px 0 0;
    position: relative;
    z-index: 1;
}

.duplicate-customer-card .card-title {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
    color: #2c3e50;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: transparent;
    padding: 0;
    border: none;
    box-shadow: none;
}

.duplicate-customer-card .card-title small {
    font-size: 12px;
    color: #6c757d;
    font-weight: 400;
    background: transparent;
    padding: 0;
    margin: 0;
    border: none;
    box-shadow: none;
}

/* Fix for card header text elements */
.duplicate-customer-card .card-header h1,
.duplicate-customer-card .card-header h2,
.duplicate-customer-card .card-header h3,
.duplicate-customer-card .card-header h4,
.duplicate-customer-card .card-header h5,
.duplicate-customer-card .card-header h6,
.duplicate-customer-card .card-header p,
.duplicate-customer-card .card-header span,
.duplicate-customer-card .card-header strong,
.duplicate-customer-card .card-header small {
    background: transparent !important;
    padding: 0 !important;
    margin: 0 !important;
    border: none !important;
    box-shadow: none !important;
}

.duplicate-customer-card .card-body {
    padding: 20px;
    flex: 1;
    display: flex;
    flex-direction: column;
}

.duplicate-customer-card .customer-info {
    margin-bottom: 15px;
}

.duplicate-customer-card .info-item {
    display: flex;
    align-items: center;
    margin-bottom: 8px;
    font-size: 13px;
}

.duplicate-customer-card .info-item i {
    width: 16px;
    margin-right: 8px;
    color: #5d78ff;
    font-size: 12px;
}

.duplicate-customer-card .info-item span {
    color: #495057;
    font-weight: 500;
}

/* Policies Section */
.policies-section {
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid #e9ecef;
}

.policies-title {
    font-size: 14px;
    font-weight: 600;
    color: #495057;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
}

.policies-title i {
    margin-right: 6px;
    color: #5d78ff;
    font-size: 12px;
}

.policies-list {
    max-height: 200px;
    overflow-y: auto;
}

.policy-item {
    display: flex;
    align-items: center;
    padding: 8px 12px;
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 6px;
    margin-bottom: 6px;
    transition: all 0.2s ease;
}

.policy-item:hover {
    background: #e9ecef;
    border-color: #5d78ff;
}

.policy-item:last-child {
    margin-bottom: 0;
}

.policy-number {
    flex: 1;
    margin-right: 10px;
}

.policy-number strong {
    color: #2c3e50;
    font-size: 13px;
    font-family: monospace;
}

.policy-details {
    flex: 1;
    margin-right: 10px;
}

.policy-details small {
    font-size: 11px;
    color: #6c757d;
}

.policy-status {
    flex-shrink: 0;
}

.no-policies {
    text-align: center;
    padding: 20px;
    color: #6c757d;
    font-style: italic;
}

.no-policies i {
    margin-right: 5px;
    color: #adb5bd;
}

/* Card Footer */
.duplicate-customer-card .card-footer {
    background: #f8f9fa;
    border-top: 1px solid #e9ecef;
    padding: 15px 20px;
    border-radius: 0 0 8px 8px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.duplicate-status {
    flex: 1;
}

.duplicate-actions {
    flex-shrink: 0;
}

.duplicate-actions .btn {
    padding: 4px 12px;
    font-size: 12px;
    border-radius: 4px;
}

/* Fix for all status badges */
.kt-badge {
    font-size: 11px;
    font-weight: 600;
    padding: 6px 12px;
    border-radius: 20px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    white-space: nowrap;
    min-width: 70px;
    text-align: center;
    display: inline-block;
    border: 1px solid transparent;
}

.kt-badge--success {
    background-color: #1bc5bd;
    color:rgb(12, 9, 9);
    border-color: #1bc5bd;
}

.kt-badge--secondary {
    background-color: #e4e6ef;
    color: #6c757d;
    border-color: #e4e6ef;
}

.kt-badge--info {
    background-color: #5d78ff;
    color: #ffffff;
    border-color: #5d78ff;
}

.kt-badge--warning {
    background-color: #ffb822;
    color: #ffffff;
    border-color: #ffb822;
}

.kt-badge--danger {
    background-color: #f64e60;
    color: #ffffff;
    border-color: #f64e60;
}

/* Ensure status badges are always visible */
.policy-card .policy-status .kt-badge,
.duplicate-customer-card .kt-badge {
    position: relative;
    z-index: 10;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

/* Fix for status text visibility */
.policy-card .policy-status .kt-badge span,
.duplicate-customer-card .kt-badge span {
    display: block;
    width: 100%;
    text-align: center;
}

/* Main Customer Policies Grid */
.policies-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.policy-card {
    background: #fff;
    border: 1px solid #e4e6ef;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
    overflow: hidden;
}

.policy-card:hover {
    box-shadow: 0 4px 16px rgba(0,0,0,0.15);
    transform: translateY(-2px);
}

.policy-card .policy-header {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    padding: 15px 20px;
    border-bottom: 1px solid #e4e6ef;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    min-height: 60px;
    position: relative;
    z-index: 1;
}

.policy-card .policy-number {
    flex: 1;
    min-width: 0;
    overflow: hidden;
    background: transparent;
    padding: 0;
    border: none;
    box-shadow: none;
}

.policy-card .policy-number strong {
    color: #2c3e50;
    font-size: 16px;
    font-family: monospace;
    font-weight: 600;
    word-break: break-all;
    line-height: 1.2;
    background: transparent;
    padding: 0;
    margin: 0;
    border: none;
    box-shadow: none;
}

/* Fix for policy card header text elements */
.policy-card .policy-header h1,
.policy-card .policy-header h2,
.policy-card .policy-header h3,
.policy-card .policy-header h4,
.policy-card .policy-header h5,
.policy-card .policy-header h6,
.policy-card .policy-header p,
.policy-card .policy-header span,
.policy-card .policy-header strong,
.policy-card .policy-header small {
    background: transparent !important;
    padding: 0 !important;
    margin: 0 !important;
    border: none !important;
    box-shadow: none !important;
}

/* Ensure card headers have proper background display */
.duplicate-customer-card .card-header,
.policy-card .policy-header {
    background: linear-gradient(135deg,rgb(88, 167, 247) 0%,rgb(215, 232, 249) 100%) !important;
    background-clip: padding-box !important;
    -webkit-background-clip: padding-box !important;
}

/* Override any inherited background styles for card headers */
.duplicate-customer-card .card-header *,
.policy-card .policy-header * {
    background: transparent !important;
    background-color: transparent !important;
    background-image: none !important;
}

/* Ensure text visibility in card headers */
.duplicate-customer-card .card-header,
.policy-card .policy-header {
    color: #2c3e50 !important;
}

.duplicate-customer-card .card-title,
.policy-card .policy-number strong {
    color: #2c3e50 !important;
    text-shadow: none !important;
    -webkit-text-stroke: none !important;
}

.duplicate-customer-card .card-title small {
    color: #6c757d !important;
    text-shadow: none !important;
    -webkit-text-stroke: none !important;
}

.policy-card .policy-status {
    flex-shrink: 0;
    margin-left: 15px;
}

.policy-card .policy-status .kt-badge {
    font-size: 11px;
    font-weight: 600;
    padding: 6px 12px;
    border-radius: 20px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    white-space: nowrap;
    min-width: 70px;
    text-align: center;
    display: inline-block;
}

.policy-card .policy-status .kt-badge--success {
    background-color: #1bc5bd;
    color:rgb(7, 58, 16);
    border: 1px solid #1bc5bd;
}

.policy-card .policy-status .kt-badge--secondary {
    background-color: #e4e6ef;
    color:rgb(38, 48, 56);
    border: 1px solid #e4e6ef;
}

.policy-card .policy-body {
    padding: 20px;
}

.policy-card .policy-info {
    display: flex;
    align-items: center;
    margin-bottom: 12px;
    font-size: 13px;
}

.policy-card .policy-info:last-child {
    margin-bottom: 0;
}

.policy-card .policy-info i {
    width: 16px;
    margin-right: 10px;
    color: #5d78ff;
    font-size: 12px;
    flex-shrink: 0;
}

.policy-card .policy-info span {
    color: #495057;
    font-weight: 500;
}

.policy-card .policy-footer {
    background: #f8f9fa;
    padding: 15px 20px;
    border-top: 1px solid #e9ecef;
}

.policy-card .policy-actions {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
}

.policy-card .policy-actions .btn {
    padding: 6px 12px;
    font-size: 12px;
    border-radius: 4px;
    text-decoration: none;
    transition: all 0.2s ease;
}

.policy-card .policy-actions .btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .duplicate-customer-card .card-title {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .duplicate-customer-card .card-title small {
        margin-top: 4px;
    }
    
    .policy-item {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .policy-number,
    .policy-details,
    .policy-status {
        margin-right: 0;
        margin-bottom: 4px;
    }
    
    .policy-status {
        align-self: flex-end;
    }
    
    .policies-grid {
        grid-template-columns: 1fr;
        gap: 15px;
    }
    
    .policy-card .policy-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    
    .policy-card .policy-actions {
        flex-direction: column;
        gap: 8px;
    }
    
    .policy-card .policy-actions .btn {
        width: 100%;
        text-align: center;
    }
    
    .policy-card .policy-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
        min-height: auto;
    }
    
    .policy-card .policy-status {
        margin-left: 0;
        align-self: flex-end;
    }
    
    .policy-card .policy-number {
        width: 100%;
    }
    
    .kt-badge {
        font-size: 10px;
        padding: 4px 8px;
        min-width: 60px;
    }
}
</style>

<body class="kt-header--fixed kt-header-mobile--fixed kt-subheader--fixed kt-subheader--enabled kt-subheader--solid kt-aside--enabled kt-aside--fixed kt-page--loading">

<!-- begin:: Header Mobile -->
<div id="kt_header_mobile" class="kt-header-mobile kt-header-mobile--fixed">
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
                    Duplicate Customer Details
                </h3>
                <span class="kt-subheader__separator kt-hidden"></span>
                <div class="kt-subheader__breadcrumbs">
                    <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                    <span class="kt-subheader__breadcrumbs-separator"></span> 
                    <a href="{{Route('admin-dashboard')}}" class="kt-subheader__breadcrumbs-link"> Dashboard </a> 
                    <span class="kt-subheader__breadcrumbs-separator"></span>
                    <a href="{{ route('admin.duplicate-customers.index') }}" class="kt-subheader__breadcrumbs-link">Duplicate Customers</a>
                    <span class="kt-subheader__breadcrumbs-separator"></span>
                    <span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">Details</span>
                </div>
            </div>
            <div class="kt-subheader__toolbar">
                <div class="kt-subheader__wrapper">
                    <a href="{{ route('admin.duplicate-customers.index') }}" class="btn btn-sm btn-elevate btn-secondary">
                        <span class="kt-opacity-11">Back to List</span>&nbsp; 
                        <i class="flaticon2-back kt-padding-l-5 kt-padding-r-0"></i>
                    </a>
                </div>
            </div>
        </div>
        <!-- end:: Subheader -->
        <!-- begin:: Content -->
        <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
            <div class="kt-portlet kt-portlet--mobile">
                <div class="kt-portlet__body">
                    <div class="row">
                        <!-- Customer Information -->
                        <div class="col-md-6">
                            <div class="duplicate-details">
                                <h4 class="kt-portlet__head-title mb-4">
                                    <i class="flaticon2-user"></i> Customer Information
                                    @if($mainCustomerPolicies->count() > 0)
                                    <span class="kt-badge kt-badge--success ml-2">{{ $mainCustomerPolicies->count() }} Policies</span>
                                    @endif
                                </h4>
                                
                                <div class="info-row">
                                    <div class="info-label">Customer ID:</div>
                                    <div class="info-value">{{ $duplicate->customer_id }}</div>
                                </div>
                                
                                <div class="info-row">
                                    <div class="info-label">Name:</div>
                                    <div class="info-value">{{ $duplicate->first_name }} {{ $duplicate->last_name }}</div>
                                </div>
                                
                                <div class="info-row">
                                    <div class="info-label">Email:</div>
                                    <div class="info-value">{{ $duplicate->email ?: 'N/A' }}</div>
                                </div>
                                
                                <div class="info-row">
                                    <div class="info-label">Cellphone:</div>
                                    <div class="info-value">{{ $duplicate->cellphone ?: 'N/A' }}</div>
                                </div>
                                
                                <div class="info-row">
                                    <div class="info-label">Omang Number:</div>
                                    <div class="info-value">{{ $duplicate->omang_number ?: 'N/A' }}</div>
                                </div>
                                
                                <div class="info-row">
                                    <div class="info-label">Passport Number:</div>
                                    <div class="info-value">{{ $duplicate->passport_number ?: 'N/A' }}</div>
                                </div>
                            </div>
                        </div>

                        <!-- Banking Information -->
                        <div class="col-md-6">
                            <div class="duplicate-details">
                                <h4 class="kt-portlet__head-title mb-4">
                                    <i class="flaticon2-bank"></i> Banking Information
                                </h4>
                                
                                <div class="info-row">
                                    <div class="info-label">Account Number:</div>
                                    <div class="info-value">{{ $duplicate->bank_account_number ?: 'N/A' }}</div>
                                </div>
                                
                                <div class="info-row">
                                    <div class="info-label">Bank Name:</div>
                                    <div class="info-value">{{ $duplicate->bank_name ?: 'N/A' }}</div>
                                </div>
                                
                                <div class="info-row">
                                    <div class="info-label">Bank Branch:</div>
                                    <div class="info-value">{{ $duplicate->bank_branch ?: 'N/A' }}</div>
                                </div>
                                
                                <div class="info-row">
                                    <div class="info-label">Billing:</div>
                                    <div class="info-value">{{ $duplicate->billing ?: 'N/A' }}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                    <!-- Duplicate Information -->
                    <div class="col-md-6">
                        <div class="duplicate-details">
                            <h4 class="kt-portlet__head-title mb-4">
                                <i class="flaticon2-warning"></i> Duplicate Information
                                @if($relatedDuplicates->count() > 0)
                                <span class="kt-badge kt-badge--info ml-2">{{ $relatedDuplicates->count() }} Related Found</span>
                                @endif
                            </h4>
                                
                                <div class="info-row">
                                    <div class="info-label">Duplicate Type:</div>
                                    <div class="info-value">
                                        <span class="kt-badge kt-badge--{{ $duplicate->duplicate_type == 'omang_passport' ? 'info' : ($duplicate->duplicate_type == 'cellphone_email' ? 'success' : 'warning') }}">
                                            {{ ucfirst(str_replace('_', ' ', $duplicate->duplicate_type)) }}
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="info-row">
                                    <div class="info-label">Status:</div>
                                    <div class="info-value">
                                        <span class="status-{{ $duplicate->status }}">
                                            <i class="flaticon2-{{ $duplicate->status == 'pending' ? 'hourglass' : ($duplicate->status == 'reviewed' ? 'eye' : ($duplicate->status == 'resolved' ? 'check-mark' : 'close')) }}"></i>
                                            {{ ucfirst($duplicate->status) }}
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="info-row">
                                    <div class="info-label">Duplicate Reason:</div>
                                    <div class="info-value">{{ $duplicate->duplicate_reason }}</div>
                                </div>
                                
                                @if($duplicate->duplicate_details)
                                <div class="info-row">
                                    <div class="info-label">Duplicate Details:</div>
                                    <div class="info-value">
                                        <div class="duplicate-details-formatted">
                                            @if(isset($duplicate->duplicate_details['existing_records']))
                                            <div class="detail-item">
                                                <strong>Existing Records:</strong>
                                                <div class="record-list">
                                                    @foreach($duplicate->duplicate_details['existing_records'] as $recordId)
                                                    <span class="kt-badge kt-badge--info">Record #{{ $recordId }}</span>
                                                    @endforeach
                                                </div>
                                            </div>
                                            @endif
                                            
                                            @if(isset($duplicate->duplicate_details['cellphone']))
                                            <div class="detail-item">
                                                <strong>Duplicate Cellphone:</strong>
                                                <span class="duplicate-value">{{ $duplicate->duplicate_details['cellphone'] }}</span>
                                            </div>
                                            @endif
                                            
                                            @if(isset($duplicate->duplicate_details['email']))
                                            <div class="detail-item">
                                                <strong>Duplicate Email:</strong>
                                                <span class="duplicate-value">{{ $duplicate->duplicate_details['email'] }}</span>
                                            </div>
                                            @endif
                                            
                                            @if(isset($duplicate->duplicate_details['omang_number']))
                                            <div class="detail-item">
                                                <strong>Duplicate Omang:</strong>
                                                <span class="duplicate-value">{{ $duplicate->duplicate_details['omang_number'] }}</span>
                                            </div>
                                            @endif
                                            
                                            @if(isset($duplicate->duplicate_details['passport_number']))
                                            <div class="detail-item">
                                                <strong>Duplicate Passport:</strong>
                                                <span class="duplicate-value">{{ $duplicate->duplicate_details['passport_number'] }}</span>
                                            </div>
                                            @endif
                                            
                                            @if(isset($duplicate->duplicate_details['existing_accounts']))
                                            <div class="detail-item">
                                                <strong>Existing Accounts:</strong>
                                                <div class="account-list">
                                                    @foreach($duplicate->duplicate_details['existing_accounts'] as $account)
                                                    <span class="kt-badge kt-badge--warning">{{ $account }}</span>
                                                    @endforeach
                                                </div>
                                            </div>
                                            @endif
                                            
                                            @if(isset($duplicate->duplicate_details['duplicate_account']))
                                            <div class="detail-item">
                                                <strong>Duplicate Account:</strong>
                                                <span class="duplicate-value">{{ $duplicate->duplicate_details['duplicate_account'] }}</span>
                                            </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                @endif
                            </div>
                        </div>

                        <!-- Processing Information -->
                        <div class="col-md-6">
                            <div class="duplicate-details">
                                <h4 class="kt-portlet__head-title mb-4">
                                    <i class="flaticon2-settings"></i> Processing Information
                                </h4>
                                
                                <div class="info-row">
                                    <div class="info-label">Created At:</div>
                                    <div class="info-value">{{ $duplicate->created_at->format('M d, Y H:i:s') }}</div>
                                </div>
                                
                                <div class="info-row">
                                    <div class="info-label">Updated At:</div>
                                    <div class="info-value">{{ $duplicate->updated_at->format('M d, Y H:i:s') }}</div>
                                </div>
                                
                                @if($duplicate->resolved_by)
                                <div class="info-row">
                                    <div class="info-label">Resolved By:</div>
                                    <div class="info-value">{{ $duplicate->resolver ? $duplicate->resolver->name : 'User #' . $duplicate->resolved_by }}</div>
                                </div>
                                @endif
                                
                                @if($duplicate->resolved_at)
                                <div class="info-row">
                                    <div class="info-label">Resolved At:</div>
                                    <div class="info-value">{{ $duplicate->resolved_at->format('M d, Y H:i:s') }}</div>
                                </div>
                                @endif
                                
                                @if($duplicate->resolution_notes)
                                <div class="info-row">
                                    <div class="info-label">Resolution Notes:</div>
                                    <div class="info-value">{{ $duplicate->resolution_notes }}</div>
                                </div>
                                @endif
                                
                                @if($duplicate->notes)
                                <div class="info-row">
                                    <div class="info-label">Notes:</div>
                                    <div class="info-value">{{ $duplicate->notes }}</div>
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Main Customer Policies -->
                    @if($mainCustomerPolicies->count() > 0)
                    <div class="row">
                        <div class="col-12">
                            <div class="duplicate-details">
                                <h4 class="kt-portlet__head-title mb-4">
                                    <i class="flaticon2-file"></i> All Policies for {{ $duplicate->first_name }} {{ $duplicate->last_name }}
                                    <span class="kt-badge kt-badge--info ml-2">{{ $mainCustomerPolicies->count() }} Total</span>
                                </h4>
                                
                                <div class="policies-grid">
                                    @foreach($mainCustomerPolicies as $policy)
                                    <div class="policy-card">
                                        <div class="policy-header">
                                            <div class="policy-number">
                                                <strong>{{ $policy->policyNumber }}</strong>
                                            </div>
                                            <div class="policy-status">
                                                @if($policy->status)
                                                <span class="kt-badge kt-badge--success">Active</span>
                                                @else
                                                <span class="kt-badge kt-badge--secondary">Inactive</span>
                                                @endif
                                            </div>
                                        </div>
                                        
                                        <div class="policy-body">
                                            @if($policy->product)
                                            <div class="policy-info">
                                                <i class="flaticon2-tag"></i>
                                                <span>{{ $policy->product->name ?? 'N/A' }}</span>
                                            </div>
                                            @endif
                                            
                                            <div class="policy-info">
                                                <i class="flaticon2-calendar"></i>
                                                <span>Created: {{ $policy->created_at ? $policy->created_at->format('M d, Y') : 'N/A' }}</span>
                                            </div>
                                            
                                            @if($policy->policyActivatedDate)
                                            <div class="policy-info">
                                                <i class="flaticon2-check-mark"></i>
                                                <span>Activated: {{ $policy->policyActivatedDate }}</span>
                                            </div>
                                            @endif
                                            
                                            @if($policy->policyType)
                                            <div class="policy-info">
                                                <i class="flaticon2-shield"></i>
                                                <span>Type: {{ $policy->policyType }}</span>
                                            </div>
                                            @endif
                                            
                                            @if($policy->leadSource)
                                            <div class="policy-info">
                                                <i class="flaticon2-source"></i>
                                                <span>Source: {{ $policy->leadSource }}</span>
                                            </div>
                                            @endif
                                        </div>
                                        
                                        <div class="policy-footer">
                                            <div class="policy-actions">
                                                <a href="#" class="btn btn-sm btn-primary" onclick="viewPolicy('{{ $policy->policyNumber }}')">
                                                    <i class="flaticon2-eye"></i> View Details
                                                </a>
                                                @if($policy->status)
                                                <a href="#" class="btn btn-sm btn-warning" onclick="managePolicy('{{ $policy->policyNumber }}')">
                                                    <i class="flaticon2-settings"></i> Manage
                                                </a>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                    @endif

                    <!-- Related Duplicate Customers -->
                    @if($relatedDuplicates->count() > 0)
                    <div class="row">
                        <div class="col-12">
                            <div class="duplicate-details">
                                <h4 class="kt-portlet__head-title mb-4">
                                    <i class="flaticon2-users"></i> Related Duplicate Customers
                                    <span class="kt-badge kt-badge--info ml-2">{{ $relatedDuplicates->count() }} Found</span>
                                    @php
                                        $totalPolicies = $customerPolicies->flatten()->count();
                                    @endphp
                                    @if($totalPolicies > 0)
                                    <span class="kt-badge kt-badge--success ml-2">{{ $totalPolicies }} Total Policies</span>
                                    @endif
                                </h4>
                                
                                <div class="row">
                                    @foreach($relatedDuplicates as $relatedDuplicate)
                                    <div class="col-md-6 col-lg-4 mb-4">
                                        <div class="duplicate-customer-card">
                                            <div class="card-header">
                                                <h5 class="card-title">
                                                    {{ $relatedDuplicate->first_name }} {{ $relatedDuplicate->last_name }}
                                                    <small class="text-muted">ID: {{ $relatedDuplicate->customer_id }}</small>
                                                </h5>
                                                <span style="padding-left:35px !important; color:#2c3e50 !important;" class="kt-badge kt-badge--{{ $relatedDuplicate->duplicate_type == 'omang_passport' ? 'info' : ($relatedDuplicate->duplicate_type == 'cellphone_email' ? 'success' : 'warning') }}">
                                                    {{ ucfirst(str_replace('_', ' ', $relatedDuplicate->duplicate_type)) }}
                                                </span>
                                            </div>
                                            
                                            <div class="card-body">
                                                <div class="customer-info">
                                                    @if($relatedDuplicate->email)
                                                    <div class="info-item">
                                                        <i class="flaticon2-mail"></i>
                                                        <span>{{ $relatedDuplicate->email }}</span>
                                                    </div>
                                                    @endif
                                                    
                                                    @if($relatedDuplicate->cellphone)
                                                    <div class="info-item">
                                                        <i class="flaticon2-phone"></i>
                                                        <span>{{ $relatedDuplicate->cellphone }}</span>
                                                    </div>
                                                    @endif
                                                    
                                                    @if($relatedDuplicate->omang_number)
                                                    <div class="info-item">
                                                        <i class="flaticon2-id-card"></i>
                                                        <span>Omang: {{ $relatedDuplicate->omang_number }}</span>
                                                    </div>
                                                    @endif
                                                    
                                                    @if($relatedDuplicate->passport_number)
                                                    <div class="info-item">
                                                        <i class="flaticon2-id-card"></i>
                                                        <span>Passport: {{ $relatedDuplicate->passport_number }}</span>
                                                    </div>
                                                    @endif
                                                    
                                                    @if($relatedDuplicate->bank_account_number)
                                                    <div class="info-item">
                                                        <i class="flaticon2-bank"></i>
                                                        <span>{{ $relatedDuplicate->bank_account_number }}</span>
                                                    </div>
                                                    @endif
                                                </div>
                                                
                                                <!-- Policies for this customer -->
                                                @if(isset($customerPolicies[$relatedDuplicate->customer_id]) && $customerPolicies[$relatedDuplicate->customer_id]->count() > 0)
                                                <div class="policies-section">
                                                    <h6 class="policies-title">
                                                        <i class="flaticon2-file"></i> Policies ({{ $customerPolicies[$relatedDuplicate->customer_id]->count() }})
                                                    </h6>
                                                    <div class="policies-list">
                                                        @foreach($customerPolicies[$relatedDuplicate->customer_id] as $policy)
                                                        <div class="policy-item">
                                                            <div class="policy-number">
                                                                <strong>{{ $policy->policyNumber }}</strong>
                                                            </div>
                                                            <div class="policy-details">
                                                                @if($policy->product)
                                                                <small class="text-muted">{{ $policy->product->name ?? 'N/A' }}</small>
                                                                @endif
                                                                <small class="text-muted d-block">
                                                                    {{ $policy->created_at ? $policy->created_at->format('M d, Y') : 'N/A' }}
                                                                </small>
                                                            </div>
                                                            <div class="policy-status">
                                                                @if($policy->status)
                                                                <span class="kt-badge kt-badge--success">Active</span>
                                                                @else
                                                                <span class="kt-badge kt-badge--secondary">Inactive</span>
                                                                @endif
                                                            </div>
                                                        </div>
                                                        @endforeach
                                                    </div>
                                                </div>
                                                @else
                                                <div class="no-policies">
                                                    <small class="text-muted">
                                                        <i class="flaticon2-information"></i> No policies found
                                                    </small>
                                                </div>
                                                @endif
                                            </div>
                                            
                                            <div class="card-footer">
                                                <div class="duplicate-status">
                                                    <span class="status-{{ $relatedDuplicate->status }}">
                                                        <i class="flaticon2-{{ $relatedDuplicate->status == 'pending' ? 'hourglass' : ($relatedDuplicate->status == 'reviewed' ? 'eye' : ($relatedDuplicate->status == 'resolved' ? 'check-mark' : 'close')) }}"></i>
                                                        {{ ucfirst($relatedDuplicate->status) }}
                                                    </span>
                                                </div>
                                                <div class="duplicate-actions">
                                                    <a href="{{ route('admin.duplicate-customers.show', $relatedDuplicate->id) }}" class="btn btn-sm btn-primary">
                                                        <i class="flaticon2-eye"></i> View
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                    @endif

                    <!-- Actions -->
                    <div class="row">
                        <div class="col-12">
                            <div class="duplicate-details">
                                <h4 class="kt-portlet__head-title mb-4">
                                    <i class="flaticon2-gear"></i> Actions
                                </h4>
                                
                                <div class="row">
                                    <div class="col-md-2">
                                        <button type="button" class="btn btn-warning btn-block" onclick="updateStatus('reviewed')">
                                            <i class="flaticon2-eye"></i> Mark as Reviewed
                                        </button>
                                    </div>
                                    <div class="col-md-2">
                                        <button type="button" class="btn btn-success btn-block" onclick="updateStatus('resolved')">
                                            <i class="flaticon2-check-mark"></i> Mark as Resolved
                                        </button>
                                    </div>
                                    <div class="col-md-2">
                                        <button type="button" class="btn btn-secondary btn-block" onclick="updateStatus('ignored')">
                                            <i class="flaticon2-close"></i> Mark as Ignored
                                        </button>
                                    </div>
                                    <div class="col-md-2">
                                        <button type="button" class="btn btn-info btn-block" onclick="editDuplicate({{ $duplicate->id }})">
                                            <i class="flaticon2-edit"></i> Edit Record
                                        </button>
                                    </div>
                                    <div class="col-md-2">
                                        <button type="button" class="btn btn-danger btn-block" onclick="deleteDuplicate({{ $duplicate->id }})">
                                            <i class="flaticon2-trash"></i> Delete Record
                                        </button>
                                    </div>
                                    <div class="col-md-2">
                                        <a href="{{ route('admin.duplicate-customers.index') }}" class="btn btn-primary btn-block">
                                            <i class="flaticon2-back"></i> Back to List
                                        </a>
                                    </div>
                                </div>
                                
                                <!-- Status Update Form -->
                                <div class="row mt-4" id="statusUpdateForm" style="display: none;">
                                    <div class="col-12">
                                        <div class="card">
                                            <div class="card-header">
                                                <h5>Update Status</h5>
                                            </div>
                                            <div class="card-body">
                                                <form id="updateStatusForm">
                                                    <div class="form-group">
                                                        <label for="newStatus">New Status:</label>
                                                        <select class="form-control" id="newStatus" name="status">
                                                            <option value="pending">Pending</option>
                                                            <option value="reviewed">Reviewed</option>
                                                            <option value="resolved">Resolved</option>
                                                            <option value="ignored">Ignored</option>
                                                        </select>
                                                    </div>
                                                    <div class="form-group">
                                                        <label for="resolutionNotes">Resolution Notes:</label>
                                                        <textarea class="form-control" id="resolutionNotes" name="resolution_notes" rows="3" placeholder="Enter resolution notes..."></textarea>
                                                    </div>
                                                    <div class="form-group">
                                                        <label for="notes">Additional Notes:</label>
                                                        <textarea class="form-control" id="notes" name="notes" rows="2" placeholder="Enter additional notes..."></textarea>
                                                    </div>
                                                    <button type="submit" class="btn btn-success">
                                                        <i class="flaticon2-check-mark"></i> Update Status
                                                    </button>
                                                    <button type="button" class="btn btn-secondary" onclick="cancelStatusUpdate()">
                                                        <i class="flaticon2-close"></i> Cancel
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
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

<script>
function updateStatus(status) {
    // Set the status in the form
    document.getElementById('newStatus').value = status;
    
    // Show the status update form
    document.getElementById('statusUpdateForm').style.display = 'block';
    
    // Scroll to the form
    document.getElementById('statusUpdateForm').scrollIntoView({ behavior: 'smooth' });
}

function cancelStatusUpdate() {
    document.getElementById('statusUpdateForm').style.display = 'none';
    document.getElementById('updateStatusForm').reset();
}

function editDuplicate(id) {
    // Navigate to edit page
    window.location.href = '{{ route("admin.duplicate-customers.index") }}/' + id + '/edit';
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
                        Swal.fire({
                            title: 'Deleted!',
                            text: 'Duplicate record has been deleted successfully.',
                            icon: 'success',
                            timer: 2000,
                            showConfirmButton: false
                        }).then(() => {
                            window.location.href = '{{ route("admin.duplicate-customers.index") }}';
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

// Handle status update form submission
$(document).ready(function() {
    $('#updateStatusForm').on('submit', function(e) {
        e.preventDefault();
        
        var formData = {
            status: $('#newStatus').val(),
            resolution_notes: $('#resolutionNotes').val(),
            notes: $('#notes').val(),
            _token: '{{ csrf_token() }}'
        };
        
        $.ajax({
            url: '{{ route("admin.duplicate-customers.index") }}/{{ $duplicate->id }}/update-status',
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    alert('Status updated successfully');
                    location.reload();
                } else {
                    alert('Error updating status: ' + response.message);
                }
            },
            error: function(xhr) {
                alert('Error updating status. Please try again.');
            }
        });
    });
});

// Policy action functions
function viewPolicy(policyNumber) {
    // You can implement policy viewing functionality here
    // For now, we'll show an alert with the policy number
    Swal.fire({
        title: 'View Policy',
        text: 'Viewing policy: ' + policyNumber,
        icon: 'info',
        confirmButtonText: 'OK'
    });
    
    // You can redirect to policy details page:
    // window.location.href = '/admin/policies/' + policyNumber;
}

function managePolicy(policyNumber) {
    // You can implement policy management functionality here
    Swal.fire({
        title: 'Manage Policy',
        text: 'Managing policy: ' + policyNumber,
        icon: 'settings',
        confirmButtonText: 'OK'
    });
    
    // You can redirect to policy management page:
    // window.location.href = '/admin/policies/' + policyNumber + '/manage';
}
</script>

</body>
</html>
