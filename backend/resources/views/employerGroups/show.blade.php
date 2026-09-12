<!DOCTYPE html>
<html lang="en">
@include('admin.layouts.header')

<style>
    .detail-card {
        background: #fff;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        margin-bottom: 20px;
    }
    .detail-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 20px;
        border-radius: 8px 8px 0 0;
    }
    .detail-body {
        padding: 25px;
    }
    .detail-row {
        display: flex;
        margin-bottom: 15px;
        border-bottom: 1px solid #f0f0f0;
        padding-bottom: 15px;
    }
    .detail-row:last-child {
        border-bottom: none;
        margin-bottom: 0;
    }
    .detail-label {
        font-weight: 600;
        color: #333;
        min-width: 200px;
        flex-shrink: 0;
    }
    .detail-value {
        color: #666;
        flex: 1;
    }
    .status-badge {
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
    }
    .status-active {
        background: #d4edda;
        color: #155724;
    }
    .status-pending {
        background: #fff3cd;
        color: #856404;
    }
    .status-inactive {
        background: #f8d7da;
        color: #721c24;
    }
    .file-link {
        color: #007bff;
        text-decoration: none;
    }
    .file-link:hover {
        text-decoration: underline;
    }
    .metadata-section {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 5px;
        margin-top: 10px;
    }
    .metadata-title {
        font-weight: 600;
        color: #495057;
        margin-bottom: 10px;
    }
    .metadata-content {
        font-family: 'Courier New', monospace;
        font-size: 12px;
        color: #6c757d;
        white-space: pre-wrap;
        max-height: 200px;
        overflow-y: auto;
    }
    .action-buttons {
        margin-top: 20px;
        padding-top: 20px;
        border-top: 1px solid #e9ecef;
    }
    .btn-group .btn {
        margin-right: 10px;
    }
    .btn-group .btn:last-child {
        margin-right: 0;
    }
</style>

<body class="kt-header--fixed kt-header-mobile--fixed kt-subheader--enabled kt-subheader--transparent kt-aside--enabled kt-aside--fixed kt-aside--minimize kt-page--loading">
<div id="kt_header_mobile" class="kt-header-mobile kt-header-mobile--fixed ">
    <div class="kt-header-mobile__logo">
        <a>
            <img alt="Logo" src="{{ asset('images/logo.png') }}"/>
        </a>
    </div>
    <div class="kt-header-mobile__toolbar">
        <button class="kt-header-mobile__toolbar-toggler kt-header-mobile__toolbar-toggler--left" id="kt_aside_mobile_toggler"><span></span></button>
        <button class="kt-header-mobile__toolbar-toggler" id="kt_header_mobile_toggler"><span></span></button>
        <button class="kt-header-mobile__toolbar-topbar-toggler" id="kt_header_mobile_topbar_toggler"><i class="flaticon-more"></i></button>
    </div>
</div>

<div class="kt-grid kt-grid--hor kt-grid--root">
    <div class="kt-grid__item kt-grid__item--fluid kt-grid kt-grid--ver kt-page">
        @include('admin.layouts.sidebar')
        @include('admin.layouts.topNav')
    </div>

    <div class="kt-grid__item kt-grid__item--fluid kt-grid kt-grid--hor">
        <!-- Subheader -->
        <div class="kt-subheader kt-grid__item" id="kt_subheader">
            <div class="kt-subheader__main">
                <h3 class="kt-subheader__title">Employer Group Details</h3>
                <span class="kt-subheader__separator kt-hidden"></span>
                <div class="kt-subheader__breadcrumbs">
                    <a href="{{ route('admin-dashboard') }}" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                    <span class="kt-subheader__breadcrumbs-separator"></span>
                    <a href="{{ route('admin.employer-groups.index') }}" class="kt-subheader__breadcrumbs-link">Employer Groups</a>
                    <span class="kt-subheader__breadcrumbs-separator"></span>
                    <span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">View Details</span>
                </div>
            </div>
            <div class="kt-subheader__toolbar">
                <div class="kt-subheader__wrapper">
                    <a href="{{ route('admin.employer-groups.index') }}" class="btn btn-sm btn-elevate btn-secondary" data-toggle="kt-tooltip" title="Back to List">
                        <span class="kt-opacity-11">Back to List</span>&nbsp;
                        <i class="flaticon2-left-arrow kt-padding-l-5 kt-padding-r-0"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- Content -->
        <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
            <div class="kt-container kt-container--fluid">
                <div class="row">
                    <div class="col-12">
                        <!-- Basic Information Card -->
                        <div class="detail-card">
                            <div class="detail-header">
                                <h4 class="mb-0">
                                    <i class="fas fa-building"></i>
                                    Basic Information
                                </h4>
                            </div>
                            <div class="detail-body">
                                <div class="detail-row">
                                    <div class="detail-label">Employer Group ID:</div>
                                    <div class="detail-value">{{ $employerGroup->employer_group_id ?? $employerGroup->id }}</div>
                                </div>
                                <div class="detail-row">
                                    <div class="detail-label">Employer Group Name:</div>
                                    <div class="detail-value">{{ $employerGroup->employer_group_name ?? $employerGroup->name }}</div>
                                </div>
                                <div class="detail-row">
                                    <div class="detail-label">Industry Section:</div>
                                    <div class="detail-value">
        {{ ucwords(str_replace('_', ' ', $employerGroup->industry_section ?? $employerGroup->industry ?? 'N/A')) }}
    </div>
                                    <!-- <div class="detail-value">{{ $employerGroup->industry_section ?? $employerGroup->industry ?? 'N/A' }}</div> -->
                                </div>
                                @if($employerGroup->other_industry)
                                <div class="detail-row">
                                    <div class="detail-label">Other Industry:</div>
                                    <div class="detail-value">{{ $employerGroup->other_industry }}</div>
                                </div>
                                @endif
                                <div class="detail-row">
                                    <div class="detail-label">Status:</div>
                                    <div class="detail-value">
                                        <span class="status-badge status-{{ strtolower($employerGroup->status ?? 'pending') }}">
                                            {{ $employerGroup->status ?? 'Pending' }}
                                        </span>
                                    </div>
                                </div>
                                <div class="detail-row">
                                    <div class="detail-label">Number of Employees:</div>
                                    <div class="detail-value">{{ $employerGroup->no_of_employees ?? 'N/A' }}</div>
                                </div>
                            </div>
                        </div>

                        <!-- Address Information Card -->
                        <div class="detail-card">
                            <div class="detail-header">
                                <h4 class="mb-0">
                                    <i class="fas fa-map-marker-alt"></i>
                                    Address Information
                                </h4>
                            </div>
                            <div class="detail-body">
                                <div class="detail-row">
                                    <div class="detail-label">Street Address:</div>
                                    <div class="detail-value">{{ $employerGroup->street_address ?? $employerGroup->address ?? 'N/A' }}</div>
                                </div>
                                <div class="detail-row">
                                    <div class="detail-label">Postal Address:</div>
                                    <div class="detail-value">{{ $employerGroup->postal_address ?? 'N/A' }}</div>
                                </div>
                                <div class="detail-row">
                                    <div class="detail-label">City/Town:</div>
                                    <div class="detail-value">{{ $employerGroup->city_town ?? $employerGroup->town ?? 'N/A' }}</div>
                                </div>
                                @if($employerGroup->postal_code)
                                <div class="detail-row">
                                    <div class="detail-label">Postal Code:</div>
                                    <div class="detail-value">{{ $employerGroup->postal_code }}</div>
                                </div>
                                @endif
                            </div>
                        </div>

                        <!-- Contact Information Card -->
                        <div class="detail-card">
                            <div class="detail-header">
                                <h4 class="mb-0">
                                    <i class="fas fa-user"></i>
                                    Contact Information
                                </h4>
                            </div>
                            <div class="detail-body">
                                <div class="detail-row">
                                    <div class="detail-label">Contact Name:</div>
                                    <div class="detail-value">{{ $employerGroup->contact_name ?? 'N/A' }}</div>
                                </div>
                                <div class="detail-row">
                                    <div class="detail-label">Contact Email:</div>
                                    <div class="detail-value">
                                        @if($employerGroup->contact_email)
                                            <a href="mailto:{{ $employerGroup->contact_email }}" class="file-link">{{ $employerGroup->contact_email }}</a>
                                        @else
                                            N/A
                                        @endif
                                    </div>
                                </div>
                                <div class="detail-row">
                                    <div class="detail-label">Contact Phone:</div>
                                    <div class="detail-value">
                                        @if($employerGroup->contact_phone)
                                            <a href="tel:{{ $employerGroup->contact_phone }}" class="file-link">{{ $employerGroup->contact_phone }}</a>
                                        @else
                                            N/A
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Broker & Billing Information Card -->
                        <div class="detail-card">
                            <div class="detail-header">
                                <h4 class="mb-0">
                                    <i class="fas fa-handshake"></i>
                                    Broker & Billing Information
                                </h4>
                            </div>
                            <div class="detail-body">
                                <div class="detail-row">
                                    <div class="detail-label">Broker Referral:</div>
                                    <div class="detail-value">
        {{ Str::title(str_replace('_', ' ', $employerGroup->broker_referral ?? $employerGroup->broker ?? 'N/A')) }}
    </div>
                                    <!-- <div class="detail-value">{{ $employerGroup->broker_referral ?? $employerGroup->broker ?? 'N/A' }}</div> -->
                                </div>
                                <div class="detail-row">
                                    <div class="detail-label">Payment Method:</div>
                                    <div class="detail-value">
        {{ Str::title(str_replace('_', ' ', $employerGroup->payment_method ?? 'N/A')) }}
    </div>
                                    <!-- <div class="detail-value">{{ $employerGroup->payment_method ?? 'N/A' }}</div> -->
                                </div>
                                @if($employerGroup->account_name)
                                <div class="detail-row">
                                    <div class="detail-label">Account Name:</div>
                                    <div class="detail-value">{{ $employerGroup->account_name }}</div>
                                </div>
                                @endif
                                @if($employerGroup->account_number)
                                <div class="detail-row">
                                    <div class="detail-label">Account Number:</div>
                                    <div class="detail-value">{{ $employerGroup->account_number }}</div>
                                </div>
                                @endif
                                @if($employerGroup->bank_name_branch)
                                <div class="detail-row">
                                    <div class="detail-label">Bank Name / Branch:</div>
                                    <div class="detail-value">{{ $employerGroup->bank_name_branch }}</div>
                                </div>
                                @endif
                            </div>
                        </div>

                        <!-- Documents Card -->
                        <div class="detail-card">
                            <div class="detail-header">
                                <h4 class="mb-0">
                                    <i class="fas fa-file-upload"></i>
                                    Documents
                                </h4>
                            </div>
                            <div class="detail-body">
                                @if($employerGroup->certificate_file)
                                <div class="detail-row">
                                    <div class="detail-label">Certificate of Incorporation:</div>
                                    <div class="detail-value">
                                        @if($employerGroup->certificate_file)
                                            <a href="{{ Storage::disk('s3')->url($employerGroup->certificate_file) }}" target="_blank" class="file-link">
                                                <i class="fas fa-download"></i> {{ $employerGroup->certificate_filename ?? 'Download File' }}
                                            </a>
                                        @else
                                            N/A
                                        @endif
                                    </div>
                                </div>
                                @endif

                                @if($employerGroup->tax_certificate_file)
                                <div class="detail-row">
                                    <div class="detail-label">Tax Clearance Certificate:</div>
                                    <div class="detail-value">
                                        @if($employerGroup->tax_certificate_file)
                                            <a href="{{ Storage::disk('s3')->url($employerGroup->tax_certificate_file) }}" target="_blank" class="file-link">
                                                <i class="fas fa-download"></i> {{ $employerGroup->tax_certificate_filename ?? 'Download File' }}
                                            </a>
                                        @else
                                            N/A
                                        @endif
                                    </div>
                                </div>
                                @endif

                                @if($employerGroup->proof_address_file)
                                <div class="detail-row">
                                    <div class="detail-label">Proof of Address:</div>
                                    <div class="detail-value">
                                        @if($employerGroup->proof_address_file)
                                            <a href="{{ Storage::disk('s3')->url($employerGroup->proof_address_file) }}" target="_blank" class="file-link">
                                                <i class="fas fa-download"></i> {{ $employerGroup->proof_address_filename ?? 'Download File' }}
                                            </a>
                                        @else
                                            N/A
                                        @endif
                                    </div>
                                </div>
                                @endif
                            </div>
                        </div>

                        <!-- Agreement Information Card -->
                        <div class="detail-card">
                            <div class="detail-header">
                                <h4 class="mb-0">
                                    <i class="fas fa-file-contract"></i>
                                    Agreement Information
                                </h4>
                            </div>
                            <div class="detail-body">
                                <div class="detail-row">
                                    <div class="detail-label">Terms Agreed:</div>
                                    <div class="detail-value">
                                        @if($employerGroup->terms_agreement)
                                            <span class="status-badge status-active">Yes</span>
                                        @else
                                            <span class="status-badge status-inactive">No</span>
                                        @endif
                                    </div>
                                </div>
                                @if($employerGroup->initials)
                                <div class="detail-row">
                                    <div class="detail-label">Initials:</div>
                                    <div class="detail-value">{{ $employerGroup->initials }}</div>
                                </div>
                                @endif
                            </div>
                        </div>

                        <!-- HR Emails Card -->
                        @if($employerGroup->bulk_emails)
                        <div class="detail-card">
                            <div class="detail-header">
                                <h4 class="mb-0">
                                    <i class="fas fa-envelope"></i>
                                    HR Emails
                                </h4>
                            </div>
                            <div class="detail-body">
                                <div class="detail-row">
                                    <div class="detail-label">HR Email Addresses:</div>
                                    <div class="detail-value">
                                        @php
                                            $emails = [];
                                            if (!empty($employerGroup->bulk_emails)) {
                                                if (is_string($employerGroup->bulk_emails)) {
                                                    $decoded = json_decode($employerGroup->bulk_emails, true);
                                                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                                                        $emails = $decoded;
                                                    } else {
                                                        $emails = array_filter(array_map('trim', explode(',', $employerGroup->bulk_emails)));
                                                    }
                                                } else {
                                                    $emails = $employerGroup->bulk_emails;
                                                }
                                            }
                                        @endphp
                                        @if(count($emails) > 0)
                                            <ul class="list-unstyled mb-0">
                                                @foreach($emails as $email)
                                                    <li><a href="mailto:{{ $email }}" class="file-link">{{ $email }}</a></li>
                                                @endforeach
                                            </ul>
                                        @else
                                            N/A
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endif

                        <!-- Metadata Card -->
                        @if($employerGroup->metadata)
                        <div class="detail-card">
                            <div class="detail-header">
                                <h4 class="mb-0">
                                    <i class="fas fa-database"></i>
                                    Application Metadata
                                </h4>
                            </div>
                            <div class="detail-body">
                                <div class="metadata-section">
                                    <div class="metadata-title">Raw Metadata (JSON):</div>
                                    <div class="metadata-content">{{ $employerGroup->metadata }}</div>
                                </div>
                            </div>
                        </div>
                        @endif

                        <!-- Notes Card -->
                        @if($employerGroup->notes)
                        <div class="detail-card">
                            <div class="detail-header">
                                <h4 class="mb-0">
                                    <i class="fas fa-sticky-note"></i>
                                    Notes
                                </h4>
                            </div>
                            <div class="detail-body">
                                <div class="detail-row">
                                    <div class="detail-label">Notes:</div>
                                    <div class="detail-value">{{ $employerGroup->notes }}</div>
                                </div>
                            </div>
                        </div>
                        @endif

                        <!-- KYC Form Data Card -->
                        @if($kycSubmission)
                        <div class="detail-card">
                            <div class="detail-header">
                                <h4 class="mb-0">
                                    <i class="fas fa-clipboard-check"></i>
                                    KYC Form Submission
                                </h4>
                            </div>
                            <div class="detail-body">
                                <!-- Company Information -->
                                <h5 class="mb-3" style="color: #667eea; border-bottom: 2px solid #667eea; padding-bottom: 10px;">Company Information</h5>
                                @if($kycSubmission->company_name)
                                <div class="detail-row">
                                    <div class="detail-label">Company Name:</div>
                                    <div class="detail-value">{{ $kycSubmission->company_name }}</div>
                                </div>
                                @endif
                                @if($kycSubmission->registration_no)
                                <div class="detail-row">
                                    <div class="detail-label">Registration Number:</div>
                                    <div class="detail-value">{{ $kycSubmission->registration_no }}</div>
                                </div>
                                @endif
                                @if($kycSubmission->tin_number)
                                <div class="detail-row">
                                    <div class="detail-label">TIN Number:</div>
                                    <div class="detail-value">{{ $kycSubmission->tin_number }}</div>
                                </div>
                                @endif
                                @if($kycSubmission->vat_number)
                                <div class="detail-row">
                                    <div class="detail-label">VAT Number:</div>
                                    <div class="detail-value">{{ $kycSubmission->vat_number }}</div>
                                </div>
                                @endif
                                @if($kycSubmission->country_of_incorporation)
                                <div class="detail-row">
                                    <div class="detail-label">Country of Incorporation:</div>
                                    <div class="detail-value">{{ $kycSubmission->country_of_incorporation }}</div>
                                </div>
                                @endif
                                @if($kycSubmission->corporate_email)
                                <div class="detail-row">
                                    <div class="detail-label">Corporate Email:</div>
                                    <div class="detail-value"><a href="mailto:{{ $kycSubmission->corporate_email }}" class="file-link">{{ $kycSubmission->corporate_email }}</a></div>
                                </div>
                                @endif
                                @if($kycSubmission->corporate_telephone)
                                <div class="detail-row">
                                    <div class="detail-label">Corporate Telephone:</div>
                                    <div class="detail-value"><a href="tel:{{ $kycSubmission->corporate_telephone }}" class="file-link">{{ $kycSubmission->corporate_telephone }}</a></div>
                                </div>
                                @endif
                                @if($kycSubmission->website)
                                <div class="detail-row">
                                    <div class="detail-label">Website:</div>
                                    <div class="detail-value"><a href="{{ $kycSubmission->website }}" target="_blank" class="file-link">{{ $kycSubmission->website }}</a></div>
                                </div>
                                @endif
                                @if($kycSubmission->postal_address)
                                <div class="detail-row">
                                    <div class="detail-label">Postal Address:</div>
                                    <div class="detail-value">{{ $kycSubmission->postal_address }}</div>
                                </div>
                                @endif
                                @if($kycSubmission->corporate_physical_address)
                                <div class="detail-row">
                                    <div class="detail-label">Corporate Physical Address:</div>
                                    <div class="detail-value">{{ $kycSubmission->corporate_physical_address }}</div>
                                </div>
                                @endif
                                @if($kycSubmission->type_of_business)
                                <div class="detail-row">
                                    <div class="detail-label">Type of Business:</div>
                                    <div class="detail-value">{{ $kycSubmission->type_of_business }}</div>
                                </div>
                                @endif
                                @if($kycSubmission->business_description)
                                <div class="detail-row">
                                    <div class="detail-label">Business Description:</div>
                                    <div class="detail-value">{{ $kycSubmission->business_description }}</div>
                                </div>
                                @endif

                                <!-- Primary Contact Information -->
                                <h5 class="mb-3 mt-4" style="color: #667eea; border-bottom: 2px solid #667eea; padding-bottom: 10px;">Primary Contact</h5>
                                @if($kycSubmission->contact_title || $kycSubmission->contact_names || $kycSubmission->contact_surname)
                                <div class="detail-row">
                                    <div class="detail-label">Contact Name:</div>
                                    <div class="detail-value">{{ trim(($kycSubmission->contact_title ?? '') . ' ' . ($kycSubmission->contact_names ?? '') . ' ' . ($kycSubmission->contact_surname ?? '')) }}</div>
                                </div>
                                @endif
                                @if($kycSubmission->contact_date_of_birth)
                                <div class="detail-row">
                                    <div class="detail-label">Date of Birth:</div>
                                    <div class="detail-value">{{ $kycSubmission->contact_date_of_birth->format('Y-m-d') }}</div>
                                </div>
                                @endif
                                @if($kycSubmission->contact_national_id)
                                <div class="detail-row">
                                    <div class="detail-label">National ID:</div>
                                    <div class="detail-value">{{ $kycSubmission->contact_national_id }}</div>
                                </div>
                                @endif
                                @if($kycSubmission->contact_nationality)
                                <div class="detail-row">
                                    <div class="detail-label">Nationality:</div>
                                    <div class="detail-value">{{ $kycSubmission->contact_nationality }}</div>
                                </div>
                                @endif
                                @if($kycSubmission->contact_position)
                                <div class="detail-row">
                                    <div class="detail-label">Position:</div>
                                    <div class="detail-value">{{ $kycSubmission->contact_position }}</div>
                                </div>
                                @endif
                                @if($kycSubmission->contact_email)
                                <div class="detail-row">
                                    <div class="detail-label">Contact Email:</div>
                                    <div class="detail-value"><a href="mailto:{{ $kycSubmission->contact_email }}" class="file-link">{{ $kycSubmission->contact_email }}</a></div>
                                </div>
                                @endif
                                @if($kycSubmission->contact_telephone)
                                <div class="detail-row">
                                    <div class="detail-label">Contact Telephone:</div>
                                    <div class="detail-value"><a href="tel:{{ $kycSubmission->contact_telephone }}" class="file-link">{{ $kycSubmission->contact_telephone }}</a></div>
                                </div>
                                @endif
                                @if($kycSubmission->contact_physical_address)
                                <div class="detail-row">
                                    <div class="detail-label">Contact Physical Address:</div>
                                    <div class="detail-value">{{ $kycSubmission->contact_physical_address }}</div>
                                </div>
                                @endif

                                <!-- Banking Information -->
                                <h5 class="mb-3 mt-4" style="color: #667eea; border-bottom: 2px solid #667eea; padding-bottom: 10px;">Banking Information</h5>
                                @if($kycSubmission->account_name)
                                <div class="detail-row">
                                    <div class="detail-label">Account Name:</div>
                                    <div class="detail-value">{{ $kycSubmission->account_name }}</div>
                                </div>
                                @endif
                                @if($kycSubmission->account_number)
                                <div class="detail-row">
                                    <div class="detail-label">Account Number:</div>
                                    <div class="detail-value">{{ $kycSubmission->account_number }}</div>
                                </div>
                                @endif
                                @if($kycSubmission->bank_name)
                                <div class="detail-row">
                                    <div class="detail-label">Bank Name:</div>
                                    <div class="detail-value">{{ $kycSubmission->bank_name }}</div>
                                </div>
                                @endif
                                @if($kycSubmission->bank_branch)
                                <div class="detail-row">
                                    <div class="detail-label">Bank Branch:</div>
                                    <div class="detail-value">{{ $kycSubmission->bank_branch }}</div>
                                </div>
                                @endif
                                @if($kycSubmission->branch_code)
                                <div class="detail-row">
                                    <div class="detail-label">Branch Code:</div>
                                    <div class="detail-value">{{ $kycSubmission->branch_code }}</div>
                                </div>
                                @endif

                                <!-- Directors -->
                                @if($kycSubmission->directors && $kycSubmission->directors->count() > 0)
                                <h5 class="mb-3 mt-4" style="color: #667eea; border-bottom: 2px solid #667eea; padding-bottom: 10px;">Directors</h5>
                                @foreach($kycSubmission->directors as $index => $director)
                                <div class="detail-row">
                                    <div class="detail-label">Director {{ $index + 1 }}:</div>
                                    <div class="detail-value">
                                        <strong>{{ $director->full_name }}</strong><br>
                                        @if($director->date_of_birth) DOB: {{ $director->date_of_birth->format('Y-m-d') }}<br> @endif
                                        @if($director->nationality) Nationality: {{ $director->nationality }}<br> @endif
                                        @if($director->residential_address) Address: {{ $director->residential_address }}<br> @endif
                                    </div>
                                </div>
                                @endforeach
                                @endif

                                <!-- Shareholders -->
                                @if($kycSubmission->shareholders && $kycSubmission->shareholders->count() > 0)
                                <h5 class="mb-3 mt-4" style="color: #667eea; border-bottom: 2px solid #667eea; padding-bottom: 10px;">Shareholders</h5>
                                @foreach($kycSubmission->shareholders as $index => $shareholder)
                                <div class="detail-row">
                                    <div class="detail-label">Shareholder {{ $index + 1 }}:</div>
                                    <div class="detail-value">
                                        <strong>{{ $shareholder->full_name }}</strong><br>
                                        @if($shareholder->ownership_percentage) Ownership: {{ $shareholder->ownership_percentage }}%<br> @endif
                                        @if($shareholder->date_of_birth) DOB: {{ $shareholder->date_of_birth->format('Y-m-d') }}<br> @endif
                                        @if($shareholder->nationality) Nationality: {{ $shareholder->nationality }}<br> @endif
                                        @if($shareholder->residential_address) Address: {{ $shareholder->residential_address }}<br> @endif
                                    </div>
                                </div>
                                @endforeach
                                @endif

                                <!-- Documents -->
                                @if($kycSubmission->documents && $kycSubmission->documents->count() > 0)
                                <h5 class="mb-3 mt-4" style="color: #667eea; border-bottom: 2px solid #667eea; padding-bottom: 10px;">Documents</h5>
                                @foreach($kycSubmission->documents as $document)
                                <div class="detail-row">
                                    <div class="detail-label">{{ ucwords(str_replace('_', ' ', $document->field_key)) }}:</div>
                                    <div class="detail-value">
                                        @if($document->url)
                                            <a href="{{ $document->url }}" target="_blank" class="file-link">
                                                <i class="fas fa-download"></i> {{ $document->original_name ?? 'Download File' }}
                                            </a>
                                        @elseif($document->path)
                                            <a href="{{ Storage::disk('s3')->url($document->path) }}" target="_blank" class="file-link">
                                                <i class="fas fa-download"></i> {{ $document->original_name ?? 'Download File' }}
                                            </a>
                                        @else
                                            N/A
                                        @endif
                                    </div>
                                </div>
                                @endforeach
                                @endif

                                <!-- Declaration -->
                                @if($kycSubmission->declaration_full_name)
                                <h5 class="mb-3 mt-4" style="color: #667eea; border-bottom: 2px solid #667eea; padding-bottom: 10px;">Declaration</h5>
                                <div class="detail-row">
                                    <div class="detail-label">Declared By:</div>
                                    <div class="detail-value">{{ $kycSubmission->declaration_full_name }}</div>
                                </div>
                                @if($kycSubmission->declaration_designation)
                                <div class="detail-row">
                                    <div class="detail-label">Designation:</div>
                                    <div class="detail-value">{{ $kycSubmission->declaration_designation }}</div>
                                </div>
                                @endif
                                @if($kycSubmission->declaration_date)
                                <div class="detail-row">
                                    <div class="detail-label">Date:</div>
                                    <div class="detail-value">{{ $kycSubmission->declaration_date->format('Y-m-d') }}</div>
                                </div>
                                @endif
                                @if($kycSubmission->declaration_place)
                                <div class="detail-row">
                                    <div class="detail-label">Place:</div>
                                    <div class="detail-value">{{ $kycSubmission->declaration_place }}</div>
                                </div>
                                @endif
                                @endif
                            </div>
                        </div>
                        @endif

                        <!-- Timestamps Card -->
                        <div class="detail-card">
                            <div class="detail-header">
                                <h4 class="mb-0">
                                    <i class="fas fa-clock"></i>
                                    Timestamps
                                </h4>
                            </div>
                            <div class="detail-body">
                                <div class="detail-row">
                                    <div class="detail-label">Created At:</div>
                                    <div class="detail-value">{{ $employerGroup->created_at ? $employerGroup->created_at->format('Y-m-d H:i:s') : 'N/A' }}</div>
                                </div>
                                <div class="detail-row">
                                    <div class="detail-label">Updated At:</div>
                                    <div class="detail-value">{{ $employerGroup->updated_at ? $employerGroup->updated_at->format('Y-m-d H:i:s') : 'N/A' }}</div>
                                </div>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="action-buttons">
                            <div class="btn-group">
                                <!-- <a href="{{ route('admin.employer-groups.edit', $employerGroup->id) }}" class="btn btn-primary">
                                    <i class="fas fa-edit"></i> Edit Employer Group
                                </a>
                                <button type="button" class="btn btn-info" onclick="sendOnboardingEmail({{ $employerGroup->id }})">
                                    <i class="fas fa-paper-plane"></i> Send Onboarding Email
                                </button>
                                <button type="button" class="btn btn-success" onclick="sendHrCredentials({{ $employerGroup->id }})">
                                    <i class="fas fa-envelope"></i> Send HR Access
                                </button> -->
                                <a href="{{ route('admin.employer-groups.index') }}" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Back to List
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @include('includes.footer')
</div>

@include('admin.layouts.scripts')

<script>
    function sendOnboardingEmail(id) {
        if (confirm('Are you sure you want to send onboarding email to the contact email for this employer group?')) {
            // Show loading state
            const button = event.target;
            const originalText = button.innerHTML;
            button.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Sending...';
            button.disabled = true;
            button.classList.add('btn-warning');
            button.classList.remove('btn-info');
            
            // Send AJAX request
            $.ajax({
                url: '{{ route("admin.employer-groups.send-onboarding-email", ":id") }}'.replace(':id', id),
                type: 'POST',
                data: {
                    _token: '{{ csrf_token() }}'
                },
                success: function(response) {
                    if (response.success) {
                        alert('Onboarding email sent successfully to: ' + response.data.contact_email);
                    } else {
                        alert('Error: ' + response.message);
                    }
                },
                error: function(xhr) {
                    const response = xhr.responseJSON;
                    alert('Error: ' + (response.message || 'Something went wrong'));
                },
                complete: function() {
                    // Reset button state
                    button.innerHTML = originalText;
                    button.disabled = false;
                    button.classList.remove('btn-warning');
                    button.classList.add('btn-info');
                }
            });
        }
    }

    function sendHrCredentials(id) {
        if (confirm('Are you sure you want to send HR login credentials to all HR emails for this employer group?')) {
            // Show loading state
            const button = event.target;
            const originalText = button.innerHTML;
            button.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Sending...';
            button.disabled = true;
            button.classList.add('btn-warning');
            button.classList.remove('btn-success');
            
            // Send AJAX request
            $.ajax({
                url: '{{ route("admin.employer-groups.send-hr-credentials", ":id") }}'.replace(':id', id),
                type: 'POST',
                data: {
                    _token: '{{ csrf_token() }}'
                },
                success: function(response) {
                    if (response.success) {
                        // Show success message with more details
                        let message = 'Success: ' + response.message;
                        if (response.sent_emails && response.sent_emails.length > 0) {
                            message += '\n\nEmails sent to: ' + response.sent_emails.join(', ');
                        }
                        if (response.failed_emails && response.failed_emails.length > 0) {
                            message += '\n\nFailed emails:\n';
                            response.failed_emails.forEach(function(failed) {
                                message += '- ' + failed.email + ': ' + failed.error + '\n';
                            });
                        }
                        alert(message);
                    } else {
                        alert('Error: ' + response.message);
                    }
                },
                error: function(xhr) {
                    const response = xhr.responseJSON;
                    alert('Error: ' + (response.message || 'Something went wrong'));
                },
                complete: function() {
                    // Reset button state
                    button.innerHTML = originalText;
                    button.disabled = false;
                    button.classList.remove('btn-warning');
                    button.classList.add('btn-success');
                }
            });
        }
    }
</script>

</body>
</html>
