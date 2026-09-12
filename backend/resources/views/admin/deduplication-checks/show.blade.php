<!DOCTYPE html>
<html lang="en" >
@include('admin.layouts.header')

<style>
.activity-timeline {
    position: relative;
    padding-left: 30px;
}

.activity-timeline::before {
    content: '';
    position: absolute;
    left: 15px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #e4e6ef;
}

.activity-item {
    position: relative;
    margin-bottom: 30px;
    padding-left: 30px;
}

.activity-item::before {
    content: '';
    position: absolute;
    left: -7px;
    top: 5px;
    width: 14px;
    height: 14px;
    border-radius: 50%;
    background: #fff;
    border: 3px solid #e4e6ef;
}

.activity-item.success::before {
    border-color: #1dc9b7;
}

.activity-item.info::before {
    border-color: #5d78ff;
}

.activity-item.warning::before {
    border-color: #ffb822;
}

.activity-item.danger::before {
    border-color: #fd27eb;
}

.file-preview {
    border: 1px solid #e4e6ef;
    border-radius: 8px;
    padding: 15px;
    background: #f8f9fa;
}

.status-badge {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
}

.status-badge.success {
    background: #d4edda;
    color: #155724;
}

.status-badge.warning {
    background: #fff3cd;
    color: #856404;
}

.status-badge.danger {
    background: #f8d7da;
    color: #721c24;
}

.status-badge.info {
    background: #d1ecf1;
    color: #0c5460;
}
</style>

<body class="kt-header--fixed kt-header-mobile--fixed kt-subheader--fixed kt-subheader--enabled kt-subheader--solid kt-aside--enabled kt-aside--fixed kt-page--loading">

@include('admin.layouts.sidebar')
@include('admin.layouts.topNav')

<div class="kt-grid__item kt-grid__item--fluid kt-grid kt-grid--hor">
    <!-- begin:: Subheader -->
    <div class="kt-subheader kt-grid__item" id="kt_subheader">
        <div class="kt-subheader__main">
            <h3 class="kt-subheader__title">
                Bank Statement Upload Details
            </h3>
            <span class="kt-subheader__separator kt-hidden"></span>
            <div class="kt-subheader__breadcrumbs">
                <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                <span class="kt-subheader__breadcrumbs-separator"></span> 
                <a href="{{Route('admin-dashboard')}}" class="kt-subheader__breadcrumbs-link"> Dashboard </a> 
                <span class="kt-subheader__breadcrumbs-separator"></span>
                <a href="{{ route('admin.deduplication-checks.index') }}" class="kt-subheader__breadcrumbs-link">Bank Statement Uploads</a>
                <span class="kt-subheader__breadcrumbs-separator"></span>
                <span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">Details</span>
            </div>
        </div>
    </div>
    <!-- end:: Subheader -->

    <!-- begin:: Content -->
    <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
        <div class="row">
            <!-- Customer Information -->
            <div class="col-xl-4 col-lg-6 col-md-6 col-sm-6">
                <div class="kt-portlet">
                    <div class="kt-portlet__head">
                        <div class="kt-portlet__head-label">
                            <h3 class="kt-portlet__head-title">Customer Information</h3>
                        </div>
                    </div>
                    <div class="kt-portlet__body">
                        @if($deduplicationCheck->customer)
                            <div class="kt-widget1">
                                <div class="kt-widget1__item">
                                    <div class="kt-widget1__info">
                                        <h3 class="kt-widget1__title">{{ $deduplicationCheck->customer->firstName }} {{ $deduplicationCheck->customer->lastName }}</h3>
                                        <p class="kt-widget1__desc">Customer ID: {{ $deduplicationCheck->customer->id }}</p>
                                    </div>
                                </div>
                            </div>
                            <div class="kt-separator kt-separator--space-lg"></div>
                            <div class="kt-widget1">
                                <div class="kt-widget1__item">
                                    <div class="kt-widget1__info">
                                        <h3 class="kt-widget1__title">Contact Details</h3>
                                        <p class="kt-widget1__desc">
                                            <strong>Email:</strong> {{ $deduplicationCheck->customer->email ?? 'N/A' }}<br>
                                            <strong>Phone:</strong> {{ $deduplicationCheck->customer->cellphone ?? 'N/A' }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        @else
                            <p class="text-muted">Customer information not available</p>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Upload Status -->
            <div class="col-xl-4 col-lg-6 col-md-6 col-sm-6">
                <div class="kt-portlet">
                    <div class="kt-portlet__head">
                        <div class="kt-portlet__head-label">
                            <h3 class="kt-portlet__head-title">Upload Status</h3>
                        </div>
                    </div>
                    <div class="kt-portlet__body">
                        <div class="kt-widget1">
                            <div class="kt-widget1__item">
                                <div class="kt-widget1__info">
                                    <h3 class="kt-widget1__title">Overall Status</h3>
                                    <p class="kt-widget1__desc">
                                        <span class="status-badge {{ $deduplicationCheck->status === 'completed' ? 'success' : ($deduplicationCheck->status === 'active' ? 'info' : 'warning') }}">
                                            {{ ucfirst($deduplicationCheck->status) }}
                                        </span>
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="kt-separator kt-separator--space-lg"></div>
                        <div class="kt-widget1">
                            <div class="kt-widget1__item">
                                <div class="kt-widget1__info">
                                    <h3 class="kt-widget1__title">Document Status</h3>
                                    <p class="kt-widget1__desc">
                                        <span class="status-badge {{ $deduplicationCheck->document_upload_status === 'uploaded' ? 'success' : 'warning' }}">
                                            {{ ucfirst($deduplicationCheck->document_upload_status) }}
                                        </span>
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="kt-separator kt-separator--space-lg"></div>
                        <div class="kt-widget1">
                            <div class="kt-widget1__item">
                                <div class="kt-widget1__info">
                                    <h3 class="kt-widget1__title">Verification Status</h3>
                                    <p class="kt-widget1__desc">
                                        <span class="status-badge {{ $deduplicationCheck->manual_verification_status === 'approved' ? 'success' : ($deduplicationCheck->manual_verification_status === 'rejected' ? 'danger' : 'warning') }}">
                                            {{ ucfirst($deduplicationCheck->manual_verification_status) }}
                                        </span>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- File Information -->
            <div class="col-xl-4 col-lg-6 col-md-6 col-sm-6">
                <div class="kt-portlet">
                    <div class="kt-portlet__head">
                        <div class="kt-portlet__head-label">
                            <h3 class="kt-portlet__head-title">File Information</h3>
                        </div>
                    </div>
                    <div class="kt-portlet__body">
                        @if($deduplicationCheck->hasDocumentUploaded())
                            <div class="file-preview">
                                <h4>{{ $deduplicationCheck->bank_statement_file_name }}</h4>
                                <p><strong>Size:</strong> {{ number_format($deduplicationCheck->bank_statement_file_size / 1024, 2) }} KB</p>
                                <p><strong>Type:</strong> {{ $deduplicationCheck->bank_statement_mime_type }}</p>
                                <p><strong>Uploaded:</strong> {{ $deduplicationCheck->updated_at->format('M d, Y H:i') }}</p>
                                @if($deduplicationCheck->bank_statement_upload_url)
                                    <a href="{{ $deduplicationCheck->bank_statement_upload_url }}" target="_blank" class="btn btn-sm btn-primary">
                                        <i class="fas fa-download"></i> Download File
                                    </a>
                                @endif
                            </div>
                        @else
                            <p class="text-muted">No file uploaded yet</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Document Details -->
        <div class="row">
            <div class="col-xl-6 col-lg-12">
                <div class="kt-portlet">
                    <div class="kt-portlet__head">
                        <div class="kt-portlet__head-label">
                            <h3 class="kt-portlet__head-title">Document Details</h3>
                        </div>
                    </div>
                    <div class="kt-portlet__body">
                        <table class="table table-borderless">
                            <tr>
                                <td><strong>Omang Number:</strong></td>
                                <td>{{ $deduplicationCheck->omang_number ?? 'N/A' }}</td>
                            </tr>
                            <tr>
                                <td><strong>Passport Number:</strong></td>
                                <td>{{ $deduplicationCheck->passport_number ?? 'N/A' }}</td>
                            </tr>
                            <tr>
                                <td><strong>Bank Account:</strong></td>
                                <td>{{ $deduplicationCheck->bank_account_number ?? 'N/A' }}</td>
                            </tr>
                            <tr>
                                <td><strong>Unique Customer ID:</strong></td>
                                <td>{{ $deduplicationCheck->unique_customer_id }}</td>
                            </tr>
                            <tr>
                                <td><strong>Access Token:</strong></td>
                                <td><code>{{ $deduplicationCheck->unique_access_token }}</code></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Timeline -->
            <div class="col-xl-6 col-lg-12">
                <div class="kt-portlet">
                    <div class="kt-portlet__head">
                        <div class="kt-portlet__head-label">
                            <h3 class="kt-portlet__head-title">Activity Timeline</h3>
                        </div>
                    </div>
                    <div class="kt-portlet__body">
                        <div class="activity-timeline">
                            @foreach($activities as $activity)
                                <div class="activity-item {{ $activity['color'] }}">
                                    <div class="d-flex align-items-start">
                                        <div class="activity-icon bg-{{ $activity['color'] }}">
                                            <i class="{{ $activity['icon'] }} text-white"></i>
                                        </div>
                                        <div class="activity-content">
                                            <div class="activity-title">{{ $activity['action'] }}</div>
                                            <div class="activity-desc">{{ $activity['description'] }}</div>
                                            <div class="activity-time">{{ $activity['timestamp']->format('M d, Y H:i') }}</div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Verification Notes -->
        @if($deduplicationCheck->verification_notes)
            <div class="row">
                <div class="col-12">
                    <div class="kt-portlet">
                        <div class="kt-portlet__head">
                            <div class="kt-portlet__head-label">
                                <h3 class="kt-portlet__head-title">Verification Notes</h3>
                            </div>
                        </div>
                        <div class="kt-portlet__body">
                            <p>{{ $deduplicationCheck->verification_notes }}</p>
                            @if($deduplicationCheck->verifier)
                                <small class="text-muted">Verified by: {{ $deduplicationCheck->verifier->name }} on {{ $deduplicationCheck->verified_at->format('M d, Y H:i') }}</small>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <!-- Actions -->
        <div class="row">
            <div class="col-12">
                <div class="kt-portlet">
                    <div class="kt-portlet__head">
                        <div class="kt-portlet__head-label">
                            <h3 class="kt-portlet__head-title">Actions</h3>
                        </div>
                    </div>
                    <div class="kt-portlet__body">
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-primary" onclick="resendNotification({{ $deduplicationCheck->id }})">
                                <i class="fas fa-paper-plane"></i> Resend Notification
                            </button>
                            <button type="button" class="btn btn-warning" onclick="updateVerificationStatus({{ $deduplicationCheck->id }})">
                                <i class="fas fa-check-circle"></i> Update Verification
                            </button>
                            <a href="{{ route('admin.deduplication-checks.index') }}" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Back to List
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- end:: Content -->
</div>

@include('admin.layouts.footer')

<script>
function updateVerificationStatus(checkId) {
    // Redirect to update verification page or show modal
    window.location.href = "{{ route('admin.deduplication-checks.index') }}?action=update_verification&id=" + checkId;
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
