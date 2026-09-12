<!DOCTYPE html>
<html lang="en" >
@include('admin.layouts.header')
<link href="{{  asset('assets/vendors/custom/datatables/datatables.bundle.css') }}" rel="stylesheet" type="text/css" />
<link href="{{  asset('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}" rel="stylesheet" type="text/css" />

<style>
/* Enhanced UI Styles for View Page */
.kyc-view-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 12px;
    padding: 18px 30px;
    margin-bottom: 25px;
    color: white;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
}

.kyc-view-header h3 {
    margin: 0;
    font-size: 14px;
    font-weight: 600;
    color: white;
}

.kyc-view-header .kt-subheader__breadcrumbs {
    margin-top: 4px;
    font-size: 11px;
}

.kyc-view-header .kt-subheader__breadcrumbs a,
.kyc-view-header .kt-subheader__breadcrumbs-link {
    font-size: 11px;
}

.compliance-badge-top {
    display: inline-block;
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    background: rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(8px);
    border: 1px solid rgba(255, 255, 255, 0.3);
    white-space: nowrap;
}

.kt-portlet {
    border-radius: 12px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.08);
    border: 1px solid #e4e6ef;
    overflow: hidden;
}

.kt-portlet__head {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-bottom: 2px solid #667eea;
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
    background-color: #f8f9ff;
    box-shadow: 0 2px 4px rgba(102, 126, 234, 0.1);
}

#policy_table tbody tr th {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    color: #495057;
    font-weight: 600;
    font-size: 13px;
    padding: 16px 24px;
    width: 30%;
    border-right: 3px solid #667eea;
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
    background: linear-gradient(180deg, #667eea 0%, #764ba2 100%);
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
    background-color: #f0f4ff;
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
    border-color: #667eea;
    background: linear-gradient(135deg, #f0f4ff 0%, #ffffff 100%);
    transform: translateY(-2px);
    box-shadow: 0 6px 12px rgba(102, 126, 234, 0.25);
}

.document-preview-box img {
    max-width: 100px;
    max-height: 100px;
    border-radius: 6px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.status-indicator {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 14px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
}

.status-indicator:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}

.status-indicator i {
    font-size: 13px;
}

.status-indicator.pending {
    background: linear-gradient(135deg, #ffc107 0%, #ffb300 100%);
    color: #856404;
}

.status-indicator.completed {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    color: white;
}

.status-indicator.unchecked {
    background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
    color: white;
}

.btn-brand {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    border-radius: 6px;
    padding: 10px 20px;
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn-brand:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

.btn-secondary {
    background: #6c757d;
    border: none;
    border-radius: 6px;
    padding: 10px 20px;
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn-secondary:hover {
    background: #5a6268;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(108, 117, 125, 0.4);
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
    border-left: 4px solid #667eea;
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
                    <div class="kyc-view-header">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 15px;">
                            <div style="flex: 1; min-width: 0;">
                                <h3>
                                    <i class="flaticon-eye" style="margin-right: 10px;"></i>
                                    View Employer Group KYC Data
                                </h3>
                                <div class="kt-subheader__breadcrumbs" style="margin-top: 8px;">
                                    <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                                    <span class="kt-subheader__breadcrumbs-separator"></span> 
                                    <a href="{{Route('admin-dashboard')}}" class="kt-subheader__breadcrumbs-link"> Dashboard </a> 
                                    <span class="kt-subheader__breadcrumbs-separator"></span>
                                    <a href="{{Route('admin.employerGroupKyc')}}" class="kt-subheader__breadcrumbs-link"> Health Employer Groups Kyc </a>
                                    <span class="kt-subheader__breadcrumbs-separator"></span>
                                    <span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">View Data</span>
                                </div>
                            </div>
                            <div style="text-align: right; display: flex; flex-direction: column; align-items: flex-end; gap: 8px; flex-shrink: 0;">
                                <span class="compliance-badge-top">
                                    <i class="la la-shield-alt"></i> KYC Verification Pending
                                </span>
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
                                <i class="flaticon-file-1" style="margin-right: 10px; color: #667eea;"></i>
                                KYC Submission Details
                            </h3>
                        </div>
                    </div>
                    <div class="kt-portlet__body clearfix">
                        @if($submission)
                        <div class="info-section">
                            <h4><i class="la la-building" style="margin-right: 8px;"></i>Employer Group Information</h4>
                            <div class="row">
                                <div class="col-md-6">
                                    <strong>Name:</strong> {{ $employerGroup->name ?? 'N/A' }}
                                </div>
                                <div class="col-md-6">
                                    <strong>ID:</strong> {{ $employerGroup->employer_group_id ?? 'N/A' }}
                                </div>
                            </div>
                        </div>
                        @endif

                        <table class="table table-hover table-checkable" id="policy_table" style="border: none;">
                            <tbody>
                            <tr>
                                <th>Employer Group Name:</th>
                                <td style="width:50%">
                                    <strong>{{ $employerGroup->name ?? 'N/A' }}</strong>
                                </td>
                            </tr>
                            <tr>
                                <th>Employer Group ID:</th>
                                <td style="width:50%">
                                    <strong>{{ $employerGroup->employer_group_id ?? 'N/A' }}</strong>
                                </td>
                            </tr>
                            <tr>
                                <th>Certificate of Incorporation:</th>
                                @if($submission && $submission->certificate_of_incorporation_url)
                                    <td>
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
                                    </td>
                                @else
                                    <td><span class="text-muted"><i class="la la-times-circle"></i> Not Uploaded</span></td>
                                @endif
                            </tr>
                            <tr>
                                <th>Tax/VAT Registration:</th>
                                @if($submission && $submission->tax_vat_registration_url)
                                    <td>
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
                                    </td>
                                @else
                                    <td><span class="text-muted"><i class="la la-times-circle"></i> Not Uploaded</span></td>
                                @endif
                            </tr>
                            <tr>
                                <th>Proof Source Funds:</th>
                                @if($submission && $submission->proof_source_funds_url)
                                    <td>
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
                                    </td>
                                @else
                                    <td><span class="text-muted"><i class="la la-times-circle"></i> Not Uploaded</span></td>
                                @endif
                            </tr>
                            <tr>
                                <th>Bank Confirmation Letter:</th>
                                @if($submission && $submission->bank_confirmation_letter_url)
                                    <td>
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
                                    </td>
                                @else
                                    <td><span class="text-muted"><i class="la la-times-circle"></i> Not Uploaded</span></td>
                                @endif
                            </tr>
                            <tr>
                                <th>Tax Clearance Certificate:</th>
                                @if($submission && $submission->tax_clearance_certificate_url)
                                    <td>
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
                                    </td>
                                @else
                                    <td><span class="text-muted"><i class="la la-times-circle"></i> Not Uploaded</span></td>
                                @endif
                            </tr>
                            <tr>
                                <th>Document Uploaded On:</th>
                                @if($submission && $submission->created_at)
                                    <td><i class="la la-calendar"></i> {{ $submission->created_at }}</td>
                                @else
                                    <td><span class="text-muted">N/A</span></td>
                                @endif
                            </tr>
                            <tr>
                                <th>Document Updated On:</th>
                                @if($submission && $submission->updated_at)
                                    <td><i class="la la-calendar-check"></i> {{ $submission->updated_at }}</td>
                                @else
                                    <td><span class="text-muted">Never Updated</span></td>
                                @endif
                            </tr>
                            <tr>
                                <th>Compliance:</th>
                                <td>
                                    <span class="status-indicator pending">
                                        <i class="la la-clock"></i> Pending
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <th>Status:</th>
                                <td>
                                    @if($submission && $submission->form_last_completed)
                                        <span class="status-indicator completed">
                                            <i class="la la-check-circle"></i> Completed
                                        </span>
                                    @else
                                        <span class="status-indicator unchecked">
                                            <i class="la la-hourglass-half"></i> Unchecked
                                        </span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <th>Action performed by:</th>
                                <td><span class="text-muted">--</span></td>
                            </tr>
                            <tr>
                                <th>Action performed at:</th>
                                <td>
                                    @if($submission && $submission->updated_at)
                                        <i class="la la-clock"></i> {{ $submission->updated_at }}
                                    @else
                                        <span class="text-muted">--</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <th>Remark:</th>
                                <td style="width:50%"><span class="text-muted">--</span></td>
                            </tr>
                            </tbody>
                        </table>
                        <div class="navigation-buttons">
                            @php
                                $prevId = \AlphaDirect\Models\EmployerGroup::where('id', '<', $employerGroup->id)->orderBy('id', 'DESC')->value('id');
                                $nextId = \AlphaDirect\Models\EmployerGroup::where('id', '>', $employerGroup->id)->orderBy('id', 'ASC')->value('id');
                            @endphp
                            <div>
                                @if($prevId)
                                    <a href="{{ route('admin.employerGroupKyc.view', $prevId) }}" class="btn btn-brand">
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
                                    <a href="{{ route('admin.employerGroupKyc.view', $nextId) }}" class="btn btn-brand">
                                        Next <i class="la la-arrow-right"></i>
                                    </a>
                                @else
                                    <a href="#" class="btn btn-brand" disabled style="opacity: 0.5; cursor: not-allowed;">
                                        Next <i class="la la-arrow-right"></i>
                                    </a>
                                @endif
                            </div>
                        </div>
                        <!--end: Content -->
                    </div>
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

</body>
<!-- end::Body -->
</html>
