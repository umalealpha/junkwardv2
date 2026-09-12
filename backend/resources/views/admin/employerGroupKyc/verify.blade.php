<!DOCTYPE html>
<html lang="en" >
@include('admin.layouts.header')
<link href="{{  asset('assets/vendors/custom/datatables/datatables.bundle.css') }}" rel="stylesheet" type="text/css" />
<link href="{{  asset('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}" rel="stylesheet" type="text/css" />

<style>
/* Enhanced UI Styles for Verify/Edit Page */
.kyc-verify-header {
    background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
    border-radius: 12px;
    padding: 18px 30px;
    margin-bottom: 25px;
    color: white;
    box-shadow: 0 4px 15px rgba(17, 153, 142, 0.3);
}

.kyc-verify-header h3 {
    margin: 0;
    font-size: 14px;
    font-weight: 600;
    color: white;
}

.kyc-verify-header .kt-subheader__breadcrumbs {
    margin-top: 4px;
    font-size: 11px;
}

.kyc-verify-header .kt-subheader__breadcrumbs a,
.kyc-verify-header .kt-subheader__breadcrumbs-link {
    font-size: 11px;
}

.kt-portlet {
    border-radius: 12px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.08);
    border: 1px solid #e4e6ef;
    overflow: hidden;
}

.kt-portlet__head {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-bottom: 2px solid #11998e;
    padding: 20px 25px;
}

.kt-portlet__head-title {
    font-size: 18px;
    font-weight: 600;
    color: #495057;
    margin: 0;
}

.kt-portlet__body {
    padding: 25px;
    background: white;
}

#policy_table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    background: white;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

#policy_table thead {
    display: none;
}

#policy_table tbody tr {
    border-bottom: 1px solid #e9ecef;
    transition: all 0.3s ease;
    background: white;
}

#policy_table tbody tr:last-child {
    border-bottom: none;
}

#policy_table tbody tr:hover {
    background-color: #f0fff4;
    box-shadow: 0 2px 4px rgba(17, 153, 142, 0.1);
}

#policy_table tbody tr th {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    color: #495057;
    font-weight: 600;
    font-size: 13px;
    padding: 16px 24px;
    width: 30%;
    border-right: 3px solid #11998e;
    vertical-align: middle;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    position: relative;
}

#policy_table tbody tr th::after {
    content: '';
    position: absolute;
    right: -3px;
    top: 0;
    bottom: 0;
    width: 3px;
    background: linear-gradient(180deg, #11998e 0%, #38ef7d 100%);
}

#policy_table tbody tr td {
    padding: 16px 24px;
    color: #212529;
    font-size: 14px;
    vertical-align: middle;
    background: white;
    line-height: 1.6;
}

#policy_table tbody tr td strong {
    color: #495057;
    font-weight: 600;
    font-size: 15px;
}

#policy_table tbody tr td .text-muted {
    color: #6c757d;
    font-style: italic;
}

#policy_table tbody tr:nth-child(even) th {
    background: linear-gradient(135deg, #f1f3f5 0%, #e9ecef 100%);
}

#policy_table tbody tr:nth-child(even) td {
    background: #fafbfc;
}

#policy_table tbody tr:nth-child(even):hover {
    background-color: #e8f5e9;
}

.document-preview-box {
    display: inline-block;
    padding: 12px;
    background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
    border-radius: 8px;
    border: 2px solid #e9ecef;
    transition: all 0.3s ease;
    text-align: center;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.document-preview-box:hover {
    border-color: #11998e;
    background: linear-gradient(135deg, #e8f5e9 0%, #ffffff 100%);
    transform: translateY(-2px);
    box-shadow: 0 6px 12px rgba(17, 153, 142, 0.25);
}

.document-preview-box img {
    max-width: 100px;
    max-height: 100px;
    border-radius: 6px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.form-control {
    border-radius: 6px;
    border: 1px solid #e4e6ef;
    padding: 10px 15px;
    transition: all 0.3s ease;
    font-size: 14px;
}

.form-control:focus {
    border-color: #11998e;
    box-shadow: 0 0 0 0.2rem rgba(17, 153, 142, 0.25);
    outline: none;
}

.form-control select {
    cursor: pointer;
}

textarea.form-control {
    min-height: 80px;
    resize: vertical;
}

.kt-radio {
    font-size: 13px;
    display: inline-flex;
    align-items: center;
    cursor: pointer;
    padding: 10px 18px;
    border-radius: 25px;
    transition: all 0.3s ease;
    border: 1px solid #e9ecef;
    background: #f8f9fa;
    min-width: 110px;
    text-align: center;
    white-space: nowrap;
    justify-content: center;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    font-weight: 500;
    margin-right: 10px;
}

.kt-radio:hover {
    background: #e9ecef;
    border-color: #ced4da;
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}

.kt-radio input[type="radio"] {
    margin: 0;
    margin-right: 8px;
    width: 14px;
    height: 14px;
    accent-color: #11998e;
    flex-shrink: 0;
}

.kt-radio:has(input[type="radio"]:checked) {
    background: #e0f7f4;
    border-color: #11998e;
    color: #11998e;
    box-shadow: 0 4px 12px rgba(17, 153, 142, 0.3);
}

.btn-brand {
    background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
    border: none;
    border-radius: 6px;
    padding: 10px 20px;
    font-weight: 600;
    transition: all 0.3s ease;
    color: white;
}

.btn-brand:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(17, 153, 142, 0.4);
    color: white;
}

.btn-secondary {
    background: #6c757d;
    border: none;
    border-radius: 6px;
    padding: 10px 20px;
    font-weight: 600;
    transition: all 0.3s ease;
    color: white;
}

.btn-secondary:hover {
    background: #5a6268;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(108, 117, 125, 0.4);
    color: white;
}

.btn-danger {
    border-radius: 6px;
    transition: all 0.3s ease;
}

.btn-danger:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(220, 53, 69, 0.3);
}

.navigation-buttons {
    display: flex;
    justify-content: space-between;
    margin-top: 30px;
    padding-top: 20px;
    border-top: 2px solid #e4e6ef;
}

.info-section {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
    border-left: 4px solid #11998e;
}

.info-section h4 {
    color: #495057;
    font-weight: 600;
    margin-bottom: 15px;
    font-size: 16px;
}

.no-data-message {
    text-align: center;
    padding: 40px;
    color: #6c757d;
    font-size: 16px;
    background: #f8f9fa;
    border-radius: 8px;
    border: 2px dashed #dee2e6;
}

.no-data-message i {
    font-size: 48px;
    color: #adb5bd;
    margin-bottom: 15px;
    display: block;
}

.kt-portlet__foot {
    background: #f8f9fa;
    border-top: 2px solid #e4e6ef;
    padding: 20px 25px;
}

.header-back-btn {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 6px 14px;
    background: rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(8px);
    border: 1px solid rgba(255, 255, 255, 0.3);
    border-radius: 5px;
    color: white;
    font-size: 12px;
    font-weight: 500;
    text-decoration: none;
    transition: all 0.2s ease;
    white-space: nowrap;
    line-height: 1.4;
    height: auto;
}

.header-back-btn:hover {
    background: rgba(255, 255, 255, 0.3);
    border-color: rgba(255, 255, 255, 0.5);
    color: white;
    transform: translateY(-1px);
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
    text-decoration: none;
}

.header-back-btn i {
    font-size: 13px;
    line-height: 1;
}

/* Banking Details Styling */
.banking-details-container {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 20px;
    margin: 10px 0;
}

.banking-info-section {
    background: white;
    border-radius: 6px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.banking-field {
    padding: 8px 0;
}

.banking-label {
    display: block;
    font-weight: 600;
    color: #495057;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 4px;
}

.banking-value {
    color: #212529;
    font-size: 14px;
    font-weight: 500;
    padding: 6px 0;
}

.account-number {
    font-family: 'Courier New', monospace;
    background: #e9ecef;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 13px;
}

.badge {
    font-size: 11px;
    padding: 4px 8px;
    border-radius: 12px;
    font-weight: 500;
}

.badge-primary {
    background-color: #007bff;
    color: white;
}

.badge-info {
    background-color: #17a2b8;
    color: white;
}

/* Document Review Section */
.document-review-section {
    margin-top: 20px;
}

.document-card {
    background: white;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    margin-bottom: 15px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    transition: box-shadow 0.2s ease;
}

.document-card:hover {
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.document-header {
    background: #f8f9fa;
    border-bottom: 1px solid #e9ecef;
    padding: 12px 16px;
    border-radius: 8px 8px 0 0;
}

.document-title {
    margin: 0;
    color: #495057;
    font-size: 14px;
    font-weight: 600;
}

.document-title i {
    margin-right: 8px;
    color: #6c757d;
}

.document-content {
    padding: 16px;
    display: flex;
    gap: 16px;
}

.document-preview {
    flex: 0 0 80px;
    text-align: center;
}

.document-preview a {
    display: block;
    text-decoration: none;
}

.document-icon {
    width: 60px;
    height: 60px;
    object-fit: contain;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    padding: 8px;
    background: white;
}

.document-image {
    width: 80px;
    height: 80px;
    object-fit: cover;
    border-radius: 4px;
    border: 1px solid #dee2e6;
}

.document-actions {
    flex: 1;
}

.status-section {
    margin-bottom: 12px;
}

.status-label {
    font-size: 12px;
    font-weight: 600;
    color: #6c757d;
    margin-bottom: 4px;
    display: block;
}

.status-badge {
    font-size: 10px;
    padding: 2px 6px;
    border-radius: 10px;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.status-rejected {
    background-color: #dc3545;
    color: white;
}

.status-pending {
    background-color: #ffc107;
    color: #212529;
}

.radio-group {
    margin-bottom: 15px;
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
    align-items: center;
}

.kt-radio {
    font-size: 13px;
    display: inline-flex;
    align-items: center;
    cursor: pointer;
    padding: 10px 18px;
    border-radius: 25px;
    transition: all 0.3s ease;
    border: 1px solid #e9ecef;
    background: #f8f9fa;
    min-width: 110px;
    text-align: center;
    white-space: nowrap;
    justify-content: center;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    font-weight: 500;
}

.kt-radio:hover {
    background: #e9ecef;
    border-color: #ced4da;
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}

.kt-radio input[type="radio"] {
    margin: 0;
    margin-right: 8px;
    width: 14px;
    height: 14px;
    accent-color: #007bff;
    flex-shrink: 0;
}

.kt-radio input[type="radio"]:checked + .radio-text {
    color: #007bff;
    font-weight: 600;
}

.kt-radio:has(input[type="radio"]:checked) {
    background: #e7f3ff;
    border-color: #007bff;
    color: #007bff;
    box-shadow: 0 4px 12px rgba(0,123,255,0.3);
}

.radio-text {
    margin: 0;
    color: #495057;
    font-weight: 500;
    font-size: 13px;
    line-height: 1.3;
    white-space: nowrap;
}

.remark-textarea {
    font-size: 12px;
    border: 1px solid #ced4da;
    border-radius: 4px;
    resize: vertical;
    min-height: 60px;
}

.remark-textarea:focus {
    border-color: #80bdff;
    box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25);
}

.no-document {
    text-align: center;
    padding: 40px 20px;
    color: #6c757d;
}

.no-document i {
    font-size: 48px;
    margin-bottom: 12px;
    display: block;
    color: #dee2e6;
}

.no-document span {
    font-size: 14px;
    font-weight: 500;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .banking-info-section .row {
        margin: 0;
    }
    
    .banking-info-section .col-md-4 {
        padding: 0 8px;
    }
    
    .document-content {
        flex-direction: column;
        gap: 12px;
    }
    
    .document-preview {
        flex: none;
        text-align: left;
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
                    <div class="kyc-verify-header">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 15px;">
                            <div style="flex: 1; min-width: 0;">
                                <h3>
                                    <i class="flaticon-edit" style="margin-right: 10px;"></i>
                                    Verify Employer Group KYC Data
                                </h3>
                                <div class="kt-subheader__breadcrumbs" style="margin-top: 8px;">
                                    <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                                    <span class="kt-subheader__breadcrumbs-separator"></span> 
                                    <a href="{{Route('admin-dashboard')}}" class="kt-subheader__breadcrumbs-link"> Dashboard </a> 
                                    <span class="kt-subheader__breadcrumbs-separator"></span>
                                    <a href="{{Route('admin.employerGroupKyc')}}" class="kt-subheader__breadcrumbs-link"> Health Employer Groups Kyc </a> 
                                    <span class="kt-subheader__breadcrumbs-separator"></span>
                                    <span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">Verify Data</span>
                                </div>
                                <div style="margin-top: 10px; padding: 8px 16px; background: rgba(255, 255, 255, 0.2); backdrop-filter: blur(8px); border-radius: 6px; border: 1px solid rgba(255, 255, 255, 0.3); font-size: 12px;">
                                    <i class="la la-info-circle" style="margin-right: 6px; font-size: 13px;"></i>
                                    <strong>NOTE:</strong> All required documents must be uploaded for KYC compliance
                                </div>
                            </div>
                            <div style="text-align: right; display: flex; flex-direction: column; align-items: flex-end; gap: 8px; flex-shrink: 0;">
                                <a href="{{Route('admin.employerGroupKyc')}}" class="header-back-btn">
                                    <i class="la la-arrow-left"></i> Back
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- end:: Subheader -->

            <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content" style="padding: 20px;">
                <div class="kt-portlet kt-portlet--mobile">
                    <div class="kt-portlet__head">
                        <div class="kt-portlet__head-label">
                            <h3 class="kt-portlet__head-title">
                                <i class="flaticon-file-1" style="margin-right: 10px; color: #11998e;"></i>
                                KYC Verification & Update
                            </h3>
                        </div>
                    </div>
                    <div class="kt-portlet__body clearfix">
                        @if(isset($submission) && $submission != null)
                        <div class="info-section">
                            <h4><i class="la la-info-circle" style="margin-right: 8px;"></i>KYC Status</h4>
                            <div style="display: flex; align-items: center; gap: 15px;">
                                <span style="font-size: 16px; font-weight: 600; color: {{ $submission->form_last_completed ? '#28a745' : '#17a2b8' }};">
                                    <i class="la la-{{ $submission->form_last_completed ? 'check-circle' : 'clock' }}" style="margin-right: 5px;"></i>
                                    {{ $submission->form_last_completed ? 'Completed' : 'Pending' }}
                                </span>
                                <span class="kt-badge kt-badge--info kt-badge--inline kt-badge--pill" style="font-size:13px; padding: 8px 16px;">
                                    <i class="la la-shield-alt"></i> KYC Verification Pending
                                </span>
                            </div>
                        </div>
                        @endif
                        <!--begin: Datatable -->
                        <!-- begin:: Content -->
                        <form id="updateEmployerGroupKYC" action="#"
                              method="POST" enctype="multipart/form-data" class="kt-form">
                            <!-- CSRF Token -->
                            <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                            @if($submission)
                                <input type="hidden" name="submission_id" value="{{ $submission->id }}" />
                            @endif
                            <input type="hidden" name="employer_group_id" value="{{ $employerGroup->id }}" />
                        <table class="table table-hover table-checkable" id="policy_table" style="border: none;">
                            <tbody>
                            <tr>
                                <th>Employer Group Name:</th>
                                <td>{{ $employerGroup->name ?? 'N/A' }}</td>
                            </tr>
                            <tr>
                                <th>Employer Group ID:</th>
                                <td>{{ $employerGroup->employer_group_id ?? 'N/A' }}</td>
                            </tr>
                            <tr>
                                <th>Certificate of Incorporation:</th>
                                @if($submission && $submission->certificate_of_incorporation_url)
                                    <td>
                                        <div class="row">
                                            <div class="col-3">
                                                <div class="document-preview-box">
                                                    <a href="{{ \AlphaDirect\Helper::getCloudFrontURL($submission->certificate_of_incorporation_url) }}" target="_blank">
                                                        @php
                                                            $ext = pathinfo($submission->certificate_of_incorporation_url, PATHINFO_EXTENSION);
                                                        @endphp
                                                        @if($ext == 'pdf')
                                                            <img src="{{asset('images/pdf.ico')}}" width="100%" height="auto">
                                                        @elseif(in_array($ext, ['docx', 'doc', 'docm']))
                                                            <img src="{{asset('images/word.ico')}}" width="100%" height="auto">
                                                        @elseif(in_array($ext, ['xls', 'xlsx', 'csv']))
                                                            <img src="{{asset('images/excel.png')}}" width="100%" height="auto">
                                                        @elseif(in_array($ext, ['jpeg', 'jpg', 'png']))
                                                            <img src="{!! \AlphaDirect\Helper::getCloudFrontURL($submission->certificate_of_incorporation_url) !!}" width="100%" height="auto">
                                                        @else
                                                            <img src="{{asset('images/doc.png')}}" width="100%" height="auto">
                                                        @endif
                                                    </a>
                                                </div>
                                            </div>
                                            <div class="col-3">
                                                <h6>Update Document Status:</h6>
                                                <label class="kt-radio">
                                                    <input type="radio" name="certificate_incorporation_status" value="1">Approve<span></span>
                                                </label>
                                                <label class="kt-radio" style="margin-left: 10px;">
                                                    <input type="radio" name="certificate_incorporation_status" value="0">Unapprove<span></span>
                                                </label>
                                            </div>
                                            <div class="col-5">
                                                <textarea placeholder="Please provide certificate of incorporation remark" class="form-control" name="certificate_incorporation_remark"></textarea>
                                            </div>
                                            <div class="col-1" style="text-align: end;">
                                                <input type="hidden" name="certificate_incorporation" id="certificate_incorporation" value="certificate_incorporation">
                                                <a href="#" role="button" id="certificate_incorporation_delete" style="background-color:#dc3545;padding-right: 7px" class="btn btn-danger" data-id="1" data-toggle="modal" title="Delete">
                                                    <i class="la la-trash"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </td>
                                @else
                                    <td>
                                        <div class="row">
                                            <div class="col-3">
                                                <span class="text-muted"><i class="la la-times-circle"></i> Not Uploaded</span>
                                            </div>
                                            <div class="col-3">
                                                <h6>Update Document Status:</h6>
                                                <label class="kt-radio">
                                                    <input type="radio" name="certificate_incorporation_status" value="1">Approve<span></span>
                                                </label>
                                                <label class="kt-radio" style="margin-left: 10px;">
                                                    <input type="radio" name="certificate_incorporation_status" value="0">Unapprove<span></span>
                                                </label>
                                            </div>
                                            <div class="col-5">
                                                <textarea placeholder="Please provide certificate of incorporation remark" class="form-control" name="certificate_incorporation_remark"></textarea>
                                            </div>
                                            <div class="col-1" style="text-align: end;">
                                                <input type="hidden" name="certificate_incorporation" id="certificate_incorporation" value="certificate_incorporation">
                                                <a href="#" role="button" id="certificate_incorporation_delete" style="background-color:#dc3545;padding-right: 7px" class="btn btn-danger" data-id="1" data-toggle="modal" title="Delete" style="display:none;">
                                                    <i class="la la-trash"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </td>
                                @endif
                            </tr>
                            <tr>
                                <th>Tax/VAT Registration:</th>
                                @if($submission && $submission->tax_vat_registration_url)
                                    <td>
                                        <div class="row">
                                            <div class="col-3">
                                                <div class="document-preview-box">
                                                    <a href="{{ \AlphaDirect\Helper::getCloudFrontURL($submission->tax_vat_registration_url) }}" target="_blank">
                                                    @php
                                                        $ext = pathinfo($submission->tax_vat_registration_url, PATHINFO_EXTENSION);
                                                    @endphp
                                                    @if($ext == 'pdf')
                                                        <img src="{{asset('images/pdf.ico')}}" width="100%" height="auto">
                                                    @elseif(in_array($ext, ['docx', 'doc', 'docm']))
                                                        <img src="{{asset('images/word.ico')}}" width="100%" height="auto">
                                                    @elseif(in_array($ext, ['xls', 'xlsx', 'csv']))
                                                        <img src="{{asset('images/excel.png')}}" width="100%" height="auto">
                                                    @elseif(in_array($ext, ['jpeg', 'jpg', 'png']))
                                                        <img src="{!! \AlphaDirect\Helper::getCloudFrontURL($submission->tax_vat_registration_url) !!}" width="100%" height="auto">
                                                    @else
                                                        <img src="{{asset('images/doc.png')}}" width="100%" height="auto">
                                                    @endif
                                                    </a>
                                                </div>
                                            </div>
                                            <div class="col-3">
                                                <h6>Update Document Status:</h6>
                                                <label class="kt-radio">
                                                    <input type="radio" name="tax_vat_registration_status" value="1">Approve<span></span>
                                                </label>
                                                <label class="kt-radio" style="margin-left: 10px;">
                                                    <input type="radio" name="tax_vat_registration_status" value="0">Unapprove<span></span>
                                                </label>
                                            </div>
                                            <div class="col-5">
                                                <textarea placeholder="Please provide tax/VAT registration remark" class="form-control" name="tax_vat_registration_remark"></textarea>
                                            </div>
                                            <div class="col-1" style="text-align: end;">
                                                <input type="hidden" name="tax_vat_registration" id="tax_vat_registration" value="tax_vat_registration">
                                                <a href="#" role="button" id="tax_vat_registration_delete" style="background-color:#dc3545;padding-right: 7px" class="btn btn-danger" data-id="1" data-toggle="modal" title="Delete">
                                                    <i class="la la-trash"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </td>
                                @else
                                    <td>
                                        <div class="row">
                                            <div class="col-3">
                                                <span class="text-muted"><i class="la la-times-circle"></i> Not Uploaded</span>
                                            </div>
                                            <div class="col-3">
                                                <h6>Update Document Status:</h6>
                                                <label class="kt-radio">
                                                    <input type="radio" name="tax_vat_registration_status" value="1">Approve<span></span>
                                                </label>
                                                <label class="kt-radio" style="margin-left: 10px;">
                                                    <input type="radio" name="tax_vat_registration_status" value="0">Unapprove<span></span>
                                                </label>
                                            </div>
                                            <div class="col-5">
                                                <textarea placeholder="Please provide tax/VAT registration remark" class="form-control" name="tax_vat_registration_remark"></textarea>
                                            </div>
                                            <div class="col-1" style="text-align: end;">
                                                <input type="hidden" name="tax_vat_registration" id="tax_vat_registration" value="tax_vat_registration">
                                                <a href="#" role="button" id="tax_vat_registration_delete" style="background-color:#dc3545;padding-right: 7px" class="btn btn-danger" data-id="1" data-toggle="modal" title="Delete" style="display:none;">
                                                    <i class="la la-trash"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </td>
                                @endif
                            </tr>
                            <tr>
                                <th>Proof Source Funds:</th>
                                @if($submission && $submission->proof_source_funds_url)
                                    <td>
                                        <div class="row">
                                            <div class="col-3">
                                                <div class="document-preview-box">
                                                    <a href="{{ \AlphaDirect\Helper::getCloudFrontURL($submission->proof_source_funds_url) }}" target="_blank">
                                                    @php
                                                        $ext = pathinfo($submission->proof_source_funds_url, PATHINFO_EXTENSION);
                                                    @endphp
                                                    @if($ext == 'pdf')
                                                        <img src="{{asset('images/pdf.ico')}}" width="100%" height="auto">
                                                    @elseif(in_array($ext, ['docx', 'doc', 'docm']))
                                                        <img src="{{asset('images/word.ico')}}" width="100%" height="auto">
                                                    @elseif(in_array($ext, ['xls', 'xlsx', 'csv']))
                                                        <img src="{{asset('images/excel.png')}}" width="100%" height="auto">
                                                    @elseif(in_array($ext, ['jpeg', 'jpg', 'png']))
                                                        <img src="{!! \AlphaDirect\Helper::getCloudFrontURL($submission->proof_source_funds_url) !!}" width="100%" height="auto">
                                                    @else
                                                        <img src="{{asset('images/doc.png')}}" width="100%" height="auto">
                                                    @endif
                                                    </a>
                                                </div>
                                            </div>
                                            <div class="col-3">
                                                <h6>Update Document Status:</h6>
                                                <label class="kt-radio">
                                                    <input type="radio" name="proof_source_funds_status" value="1">Approve<span></span>
                                                </label>
                                                <label class="kt-radio" style="margin-left: 10px;">
                                                    <input type="radio" name="proof_source_funds_status" value="0">Unapprove<span></span>
                                                </label>
                                            </div>
                                            <div class="col-5">
                                                <textarea placeholder="Please provide proof source funds remark" class="form-control" name="proof_source_funds_remark"></textarea>
                                            </div>
                                            <div class="col-1" style="text-align: end;">
                                                <input type="hidden" name="proof_source_funds" id="proof_source_funds" value="proof_source_funds">
                                                <a href="#" role="button" id="proof_source_funds_delete" style="background-color:#dc3545;padding-right: 7px" class="btn btn-danger" data-id="1" data-toggle="modal" title="Delete">
                                                    <i class="la la-trash"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </td>
                                @else
                                    <td>
                                        <div class="row">
                                            <div class="col-3">
                                                <span class="text-muted"><i class="la la-times-circle"></i> Not Uploaded</span>
                                            </div>
                                            <div class="col-3">
                                                <h6>Update Document Status:</h6>
                                                <label class="kt-radio">
                                                    <input type="radio" name="proof_source_funds_status" value="1">Approve<span></span>
                                                </label>
                                                <label class="kt-radio" style="margin-left: 10px;">
                                                    <input type="radio" name="proof_source_funds_status" value="0">Unapprove<span></span>
                                                </label>
                                            </div>
                                            <div class="col-5">
                                                <textarea placeholder="Please provide proof source funds remark" class="form-control" name="proof_source_funds_remark"></textarea>
                                            </div>
                                            <div class="col-1" style="text-align: end;">
                                                <input type="hidden" name="proof_source_funds" id="proof_source_funds" value="proof_source_funds">
                                                <a href="#" role="button" id="proof_source_funds_delete" style="background-color:#dc3545;padding-right: 7px" class="btn btn-danger" data-id="1" data-toggle="modal" title="Delete" style="display:none;">
                                                    <i class="la la-trash"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </td>
                                @endif
                            </tr>
                            <tr>
                                <th>Bank Confirmation Letter:</th>
                                @if($submission && $submission->bank_confirmation_letter_url)
                                    <td>
                                        <div class="row">
                                            <div class="col-3">
                                                <div class="document-preview-box">
                                                    <a href="{{ \AlphaDirect\Helper::getCloudFrontURL($submission->bank_confirmation_letter_url) }}" target="_blank">
                                                    @php
                                                        $ext = pathinfo($submission->bank_confirmation_letter_url, PATHINFO_EXTENSION);
                                                    @endphp
                                                    @if($ext == 'pdf')
                                                        <img src="{{asset('images/pdf.ico')}}" width="100%" height="auto">
                                                    @elseif(in_array($ext, ['docx', 'doc', 'docm']))
                                                        <img src="{{asset('images/word.ico')}}" width="100%" height="auto">
                                                    @elseif(in_array($ext, ['xls', 'xlsx', 'csv']))
                                                        <img src="{{asset('images/excel.png')}}" width="100%" height="auto">
                                                    @elseif(in_array($ext, ['jpeg', 'jpg', 'png']))
                                                        <img src="{!! \AlphaDirect\Helper::getCloudFrontURL($submission->bank_confirmation_letter_url) !!}" width="100%" height="auto">
                                                    @else
                                                        <img src="{{asset('images/doc.png')}}" width="100%" height="auto">
                                                    @endif
                                                    </a>
                                                </div>
                                            </div>
                                            <div class="col-3">
                                                <h6>Update Document Status:</h6>
                                                <label class="kt-radio">
                                                    <input type="radio" name="bank_confirmation_letter_status" value="1">Approve<span></span>
                                                </label>
                                                <label class="kt-radio" style="margin-left: 10px;">
                                                    <input type="radio" name="bank_confirmation_letter_status" value="0">Unapprove<span></span>
                                                </label>
                                            </div>
                                            <div class="col-5">
                                                <textarea placeholder="Please provide bank confirmation letter remark" class="form-control" name="bank_confirmation_letter_remark"></textarea>
                                            </div>
                                            <div class="col-1" style="text-align: end;">
                                                <input type="hidden" name="bank_confirmation_letter" id="bank_confirmation_letter" value="bank_confirmation_letter">
                                                <a href="#" role="button" id="bank_confirmation_letter_delete" style="background-color:#dc3545;padding-right: 7px" class="btn btn-danger" data-id="1" data-toggle="modal" title="Delete">
                                                    <i class="la la-trash"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </td>
                                @else
                                    <td>
                                        <div class="row">
                                            <div class="col-3">
                                                <span class="text-muted"><i class="la la-times-circle"></i> Not Uploaded</span>
                                            </div>
                                            <div class="col-3">
                                                <h6>Update Document Status:</h6>
                                                <label class="kt-radio">
                                                    <input type="radio" name="bank_confirmation_letter_status" value="1">Approve<span></span>
                                                </label>
                                                <label class="kt-radio" style="margin-left: 10px;">
                                                    <input type="radio" name="bank_confirmation_letter_status" value="0">Unapprove<span></span>
                                                </label>
                                            </div>
                                            <div class="col-5">
                                                <textarea placeholder="Please provide bank confirmation letter remark" class="form-control" name="bank_confirmation_letter_remark"></textarea>
                                            </div>
                                            <div class="col-1" style="text-align: end;">
                                                <input type="hidden" name="bank_confirmation_letter" id="bank_confirmation_letter" value="bank_confirmation_letter">
                                                <a href="#" role="button" id="bank_confirmation_letter_delete" style="background-color:#dc3545;padding-right: 7px" class="btn btn-danger" data-id="1" data-toggle="modal" title="Delete" style="display:none;">
                                                    <i class="la la-trash"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </td>
                                @endif
                            </tr>
                            <tr>
                                <th>Tax Clearance Certificate:</th>
                                @if($submission && $submission->tax_clearance_certificate_url)
                                    <td>
                                        <div class="row">
                                            <div class="col-3">
                                                <div class="document-preview-box">
                                                    <a href="{{ \AlphaDirect\Helper::getCloudFrontURL($submission->tax_clearance_certificate_url) }}" target="_blank">
                                                    @php
                                                        $ext = pathinfo($submission->tax_clearance_certificate_url, PATHINFO_EXTENSION);
                                                    @endphp
                                                    @if($ext == 'pdf')
                                                        <img src="{{asset('images/pdf.ico')}}" width="100%" height="auto">
                                                    @elseif(in_array($ext, ['docx', 'doc', 'docm']))
                                                        <img src="{{asset('images/word.ico')}}" width="100%" height="auto">
                                                    @elseif(in_array($ext, ['xls', 'xlsx', 'csv']))
                                                        <img src="{{asset('images/excel.png')}}" width="100%" height="auto">
                                                    @elseif(in_array($ext, ['jpeg', 'jpg', 'png']))
                                                        <img src="{!! \AlphaDirect\Helper::getCloudFrontURL($submission->tax_clearance_certificate_url) !!}" width="100%" height="auto">
                                                    @else
                                                        <img src="{{asset('images/doc.png')}}" width="100%" height="auto">
                                                    @endif
                                                    </a>
                                                </div>
                                            </div>
                                            <div class="col-3">
                                                <h6>Update Document Status:</h6>
                                                <label class="kt-radio">
                                                    <input type="radio" name="tax_clearance_certificate_status" value="1">Approve<span></span>
                                                </label>
                                                <label class="kt-radio" style="margin-left: 10px;">
                                                    <input type="radio" name="tax_clearance_certificate_status" value="0">Unapprove<span></span>
                                                </label>
                                            </div>
                                            <div class="col-5">
                                                <textarea placeholder="Please provide tax clearance certificate remark" class="form-control" name="tax_clearance_certificate_remark"></textarea>
                                            </div>
                                            <div class="col-1" style="text-align: end;">
                                                <input type="hidden" name="tax_clearance_certificate" id="tax_clearance_certificate" value="tax_clearance_certificate">
                                                <a href="#" role="button" id="tax_clearance_certificate_delete" style="background-color:#dc3545;padding-right: 7px" class="btn btn-danger" data-id="1" data-toggle="modal" title="Delete">
                                                    <i class="la la-trash"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </td>
                                @else
                                    <td>
                                        <div class="row">
                                            <div class="col-3">
                                                <span class="text-muted"><i class="la la-times-circle"></i> Not Uploaded</span>
                                            </div>
                                            <div class="col-3">
                                                <h6>Update Document Status:</h6>
                                                <label class="kt-radio">
                                                    <input type="radio" name="tax_clearance_certificate_status" value="1">Approve<span></span>
                                                </label>
                                                <label class="kt-radio" style="margin-left: 10px;">
                                                    <input type="radio" name="tax_clearance_certificate_status" value="0">Unapprove<span></span>
                                                </label>
                                            </div>
                                            <div class="col-5">
                                                <textarea placeholder="Please provide tax clearance certificate remark" class="form-control" name="tax_clearance_certificate_remark"></textarea>
                                            </div>
                                            <div class="col-1" style="text-align: end;">
                                                <input type="hidden" name="tax_clearance_certificate" id="tax_clearance_certificate" value="tax_clearance_certificate">
                                                <a href="#" role="button" id="tax_clearance_certificate_delete" style="background-color:#dc3545;padding-right: 7px" class="btn btn-danger" data-id="1" data-toggle="modal" title="Delete" style="display:none;">
                                                    <i class="la la-trash"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </td>
                                @endif
                            </tr>
                            <tr>
                                <th>Document Uploaded On:</th>
                                @if($submission && $submission->created_at)
                                    <td>{{ $submission->created_at }}</td>
                                @else
                                    <td>N/A</td>
                                @endif
                            </tr>
                            <tr>
                                <th>Document Updated On:</th>
                                @if($submission && $submission->updated_at)
                                    <td>{{ $submission->updated_at }}</td>
                                @else
                                    <td>Never Updated</td>
                                @endif
                            </tr>
                            <tr>
                                <th>Compliance:</th>
                                <td>
                                    <select class="form-control" name="compliance">
                                        <option value="0" selected>Pending Verification</option>
                                        <option value="1">Compliant</option>
                                        <option value="2">Non-Compliant</option>
                                        <option value="3">No ID - No Documents</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th>Status:</th>
                                <td>
                                    <select class="form-control" name="status">
                                        <option value="Unchecked" selected>Unchecked</option>
                                        <option value="Approve">Approved</option>
                                        <option value="Unapprove">Rejected</option>
                                        <option value="Recheck">Recheck</option>
                                        <option value="Renew">Renew</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th>Action performed by:</th>
                                <td>
                                    <span>{{ Auth::user()->firstName ?? '' }} {{ Auth::user()->lastName ?? '' }}</span>
                                </td>
                            </tr>
                            <tr>
                                <th>Action performed at:</th>
                                <td>
                                    <span>{{ now() }}</span>
                                </td>
                            </tr>
                            <tr>
                                <th>Remark:</th>
                                <td>
                                    <textarea class="form-control" name="remark" placeholder="Please mention remark if any"></textarea>
                                </td>
                            </tr>
                            </tbody>
                        </table>
                        <div class="kt-portlet__foot kt-portlet__foot--solid">
                            <div class="kt-form__actions">
                                <div class="row">
                                    <div class="col-5"></div>
                                    <div class="col-7">
                                        <button type="submit" value="Submit" id="submitbtn" class="btn btn-brand">Update</button>
                                        <button class="btn btn-brand" type="button" id="loadBtn" style="display:none" > <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Loading... </button>
                                        <a class="btn btn-secondary" href="{{ URL::to('admin/employerGroupKyc') }}" >Cancel</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        </form>
                        <div class="navigation-buttons">
                            @php
                                $prevId = \AlphaDirect\Models\EmployerGroup::where('id', '<', $employerGroup->id)->orderBy('id', 'DESC')->value('id');
                                $nextId = \AlphaDirect\Models\EmployerGroup::where('id', '>', $employerGroup->id)->orderBy('id', 'ASC')->value('id');
                            @endphp
                            <div>
                                @if($prevId)
                                    <a href="{{ route('admin.employerGroupKyc.verify', $prevId) }}" class="btn btn-brand">
                                        <i class="la la-arrow-left"></i> Previous
                                    </a>
                                @else
                                    <a href="#" class="btn btn-brand" disabled style="opacity: 0.5; cursor: not-allowed;">
                                        <i class="la la-arrow-left"></i> Previous
                                    </a>
                                @endif
                            </div>
                            <div>
                                @if($nextId)
                                    <a href="{{ route('admin.employerGroupKyc.verify', $nextId) }}" class="btn btn-brand">
                                        Next <i class="la la-arrow-right"></i>
                                    </a>
                                @else
                                    <a href="#" class="btn btn-brand" disabled style="opacity: 0.5; cursor: not-allowed;">
                                        Next <i class="la la-arrow-right"></i>
                                    </a>
                                @endif
                            </div>
                        </div>
                        <!--end: Datatable -->
                    </div>
                </div>
            </div>
            <!-- end:: Content -->
        </div>

        {{-- Delete Modal --}}
        <div id="myModal" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form id="" action="#"
                          method="POST" enctype="multipart/form-data" class="kt-form">
                        <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                        <input type="hidden" name="image_data" value="" id="image_data" />
                        <div class="modal-header">
                            <h3 class="modal-title" id="myModalLabel">Delete Employer Group KYC Document</h3>
                            <button type="button" class="close" data-dismiss="modal" aria-hidden="true"></button>
                        </div>
                        <div class="modal-body">
                            <p>Do you want to delete this document?</p>
                        </div>
                        <div class="modal-footer">
                            <button class="btn" data-dismiss="modal" aria-hidden="true">Close</button>
                            <button class="btn btn-primary" type="submit">Delete</button>
                        </div>
                    </form>
                </div>
            </div>
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
    // Delete document handlers
    $('#certificate_incorporation_delete').on('click', function(e) {
        e.preventDefault();
        var id = $(this).data('id');
        $('#image_data').val('certificate_incorporation');
        $('#myModal').data('id', id).modal('show');
    });
    $('#tax_vat_registration_delete').on('click', function(e) {
        e.preventDefault();
        var id = $(this).data('id');
        $('#image_data').val('tax_vat_registration');
        $('#myModal').data('id', id).modal('show');
    });
    $('#proof_source_funds_delete').on('click', function(e) {
        e.preventDefault();
        var id = $(this).data('id');
        $('#image_data').val('proof_source_funds');
        $('#myModal').data('id', id).modal('show');
    });
    $('#bank_confirmation_letter_delete').on('click', function(e) {
        e.preventDefault();
        var id = $(this).data('id');
        $('#image_data').val('bank_confirmation_letter');
        $('#myModal').data('id', id).modal('show');
    });
    $('#tax_clearance_certificate_delete').on('click', function(e) {
        e.preventDefault();
        var id = $(this).data('id');
        $('#image_data').val('tax_clearance_certificate');
        $('#myModal').data('id', id).modal('show');
    });

    $(function () {
        $('body').on('hidden.bs.modal', '.modal', function () {
            $(this).removeData('bs.modal');
        });
    });
</script>
<script src="{{  asset('assets/vendors/general/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}" type="text/javascript"></script>
<script src="{{  asset('assets/vendors/custom/components/vendors/bootstrap-datepicker/init.js') }}" type="text/javascript"></script>

</body>
<!-- end::Body -->
</html>
