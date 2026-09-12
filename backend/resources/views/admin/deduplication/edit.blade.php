<!DOCTYPE html>
<html lang="en">

@include('admin.layouts.header')

<link href="{{ asset('css/rekyc-fixes.css') }}" rel="stylesheet" type="text/css" />

<style>
/* DeduplicationChecks Edit Page Styles */
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

.info-card {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 15px;
    border-left: 4px solid #5d78ff;
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

.form-section {
    background: #fff;
    border-radius: 8px;
    padding: 25px;
    margin-bottom: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    border: 1px solid #e4e6ef;
}

.section-title {
    color: #2c3e50;
    font-weight: 600;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 2px solid #e4e6ef;
}

.btn-save {
    background: linear-gradient(45deg, #1dc9b7, #5d78ff);
    border: none;
    color: white;
    padding: 12px 30px;
    border-radius: 25px;
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn-save:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(29, 201, 183, 0.4);
    color: white;
}

.btn-cancel {
    background: #6c757d;
    border: none;
    color: white;
    padding: 12px 30px;
    border-radius: 25px;
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn-cancel:hover {
    background: #5a6268;
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
                        Edit Deduplication Check - #{{ $deduplicationCheck->id }}
                    </h3>
                    <span class="kt-subheader__separator kt-hidden"></span>
                    <div class="kt-subheader__breadcrumbs">
                        <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                        <span class="kt-subheader__breadcrumbs-separator"></span> 
                        <a href="{{Route('admin-dashboard')}}" class="kt-subheader__breadcrumbs-link"> Dashboard </a> 
                        <span class="kt-subheader__breadcrumbs-separator"></span>
                        <a href="{{ route('admin.deduplication.index') }}" class="kt-subheader__breadcrumbs-link">Deduplication Management</a>
                        <span class="kt-subheader__breadcrumbs-separator"></span>
                        <a href="{{ route('admin.deduplication.show', $deduplicationCheck->id) }}" class="kt-subheader__breadcrumbs-link">Check #{{ $deduplicationCheck->id }}</a>
                        <span class="kt-subheader__breadcrumbs-separator"></span>
                        <span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">Edit</span>
                    </div>
                </div>
                <div class="kt-subheader__toolbar">
                    <div class="kt-subheader__wrapper">
                        <a href="{{ route('admin.deduplication.show', $deduplicationCheck->id) }}" class="btn btn-sm btn-elevate btn-secondary btn-elevate">
                            <span class="kt-opacity-11">View Details</span>&nbsp; 
                            <i class="flaticon2-eye kt-padding-l-5 kt-padding-r-0"></i>
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
                <form action="{{ route('admin.deduplication.update', $deduplicationCheck->id) }}" method="POST">
                    @csrf
                    @method('PUT')
                    
                    <div class="row">
                        <!-- Left Column - Form -->
                        <div class="col-lg-8">
                            <!-- Current Status -->
                            <div class="form-section">
                                <h4 class="section-title">
                                    <i class="fas fa-info-circle mr-2"></i>Current Status
                                </h4>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="info-card">
                                            <h6 class="text-muted mb-2">Overall Status</h6>
                                            <span class="status-badge status-{{ $deduplicationCheck->status }}">
                                                {{ strtoupper($deduplicationCheck->status) }}
                                            </span>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="info-card">
                                            <h6 class="text-muted mb-2">Document Upload Status</h6>
                                            <span class="badge badge-{{ $deduplicationCheck->document_upload_status === 'uploaded' ? 'success' : ($deduplicationCheck->document_upload_status === 'pending' ? 'warning' : 'secondary') }}">
                                                {{ strtoupper($deduplicationCheck->document_upload_status) }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Verification Settings -->
                            <div class="form-section">
                                <h4 class="section-title">
                                    <i class="fas fa-check-double mr-2"></i>Verification Settings
                                </h4>
                                
                                <div class="form-group">
                                    <label for="manual_verification_status" class="form-label">
                                        <strong>Manual Verification Status</strong>
                                        <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-control @error('manual_verification_status') is-invalid @enderror" 
                                            id="manual_verification_status" name="manual_verification_status" required>
                                        <option value="pending" {{ $deduplicationCheck->manual_verification_status === 'pending' ? 'selected' : '' }}>
                                            Pending
                                        </option>
                                        <option value="approved" {{ $deduplicationCheck->manual_verification_status === 'approved' ? 'selected' : '' }}>
                                            Approved
                                        </option>
                                        <option value="rejected" {{ $deduplicationCheck->manual_verification_status === 'rejected' ? 'selected' : '' }}>
                                            Rejected
                                        </option>
                                    </select>
                                    @error('manual_verification_status')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="form-group">
                                    <label for="verification_notes" class="form-label">
                                        <strong>Verification Notes</strong>
                                    </label>
                                    <textarea class="form-control @error('verification_notes') is-invalid @enderror" 
                                              id="verification_notes" name="verification_notes" rows="4" 
                                              placeholder="Add notes about the verification process...">{{ old('verification_notes', $deduplicationCheck->verification_notes) }}</textarea>
                                    @error('verification_notes')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <small class="form-text text-muted">Maximum 1000 characters</small>
                                </div>

                                <div class="form-group" id="suspension_reason_group" style="display: none;">
                                    <label for="suspension_reason" class="form-label">
                                        <strong>Suspension Reason</strong>
                                    </label>
                                    <textarea class="form-control @error('suspension_reason') is-invalid @enderror" 
                                              id="suspension_reason" name="suspension_reason" rows="3" 
                                              placeholder="Explain why this check is being suspended...">{{ old('suspension_reason', $deduplicationCheck->suspension_reason) }}</textarea>
                                    @error('suspension_reason')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <small class="form-text text-muted">Required when status is set to rejected</small>
                                </div>
                            </div>

                            <!-- Additional Notes -->
                            <div class="form-section">
                                <h4 class="section-title">
                                    <i class="fas fa-sticky-note mr-2"></i>Additional Notes
                                </h4>
                                
                                <div class="form-group">
                                    <label for="notes" class="form-label">
                                        <strong>General Notes</strong>
                                    </label>
                                    <textarea class="form-control @error('notes') is-invalid @enderror" 
                                              id="notes" name="notes" rows="4" 
                                              placeholder="Add any additional notes or comments...">{{ old('notes', $deduplicationCheck->notes) }}</textarea>
                                    @error('notes')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <small class="form-text text-muted">Maximum 1000 characters</small>
                                </div>
                            </div>
                        </div>

                        <!-- Right Column - Info & Actions -->
                        <div class="col-lg-4">
                            <!-- Customer Information -->
                            <div class="kt-portlet">
                                <div class="kt-portlet__head">
                                    <div class="kt-portlet__head-label">
                                        <h3 class="kt-portlet__head-title">
                                            <i class="fas fa-user mr-2"></i>Customer Info
                                        </h3>
                                    </div>
                                </div>
                                <div class="kt-portlet__body">
                                    <div class="info-card">
                                        <h6 class="text-muted mb-2">Customer Name</h6>
                                        <h5 class="mb-0">
                                            {{ $deduplicationCheck->customer ? $deduplicationCheck->customer->firstName . ' ' . $deduplicationCheck->customer->lastName : 'N/A' }}
                                        </h5>
                                    </div>
                                    
                                    <div class="info-card">
                                        <h6 class="text-muted mb-2">Email</h6>
                                        <h5 class="mb-0">{{ $deduplicationCheck->email ?? 'N/A' }}</h5>
                                    </div>
                                    
                                    <div class="info-card">
                                        <h6 class="text-muted mb-2">Phone</h6>
                                        <h5 class="mb-0">{{ $deduplicationCheck->cellphone ?? 'N/A' }}</h5>
                                    </div>
                                </div>
                            </div>

                            <!-- Document Information -->
                            <div class="kt-portlet">
                                <div class="kt-portlet__head">
                                    <div class="kt-portlet__head-label">
                                        <h3 class="kt-portlet__head-title">
                                            <i class="fas fa-file-upload mr-2"></i>Document Info
                                        </h3>
                                    </div>
                                </div>
                                <div class="kt-portlet__body">
                                    @if($deduplicationCheck->bank_statement_upload_url)
                                        <div class="info-card">
                                            <h6 class="text-muted mb-2">File Name</h6>
                                            <h5 class="mb-0">{{ $deduplicationCheck->bank_statement_file_name ?? 'Bank Statement' }}</h5>
                                        </div>
                                        
                                        <div class="info-card">
                                            <h6 class="text-muted mb-2">File Size</h6>
                                            <h5 class="mb-0">{{ $deduplicationCheck->bank_statement_file_size ? number_format($deduplicationCheck->bank_statement_file_size / 1024, 2) . ' KB' : 'N/A' }}</h5>
                                        </div>
                                        
                                        <div class="text-center mt-3">
                                            <a href="{{ $deduplicationCheck->bank_statement_upload_url }}" 
                                               target="_blank" class="btn btn-success btn-block">
                                                <i class="fas fa-download mr-2"></i>Download Document
                                            </a>
                                        </div>
                                    @else
                                        <div class="alert alert-warning">
                                            <i class="fas fa-exclamation-triangle mr-2"></i>
                                            No document uploaded
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="kt-portlet">
                                <div class="kt-portlet__head">
                                    <div class="kt-portlet__head-label">
                                        <h3 class="kt-portlet__head-title">
                                            <i class="fas fa-bolt mr-2"></i>Actions
                                        </h3>
                                    </div>
                                </div>
                                <div class="kt-portlet__body">
                                    <div class="d-grid gap-2">
                                        <button type="submit" class="btn btn-save btn-block">
                                            <i class="fas fa-save mr-2"></i>Save Changes
                                        </button>
                                        
                                        <a href="{{ route('admin.deduplication.show', $deduplicationCheck->id) }}" 
                                           class="btn btn-cancel btn-block">
                                            <i class="fas fa-times mr-2"></i>Cancel
                                        </a>
                                        
                                        <a href="{{ route('admin.deduplication.index') }}" 
                                           class="btn btn-secondary btn-block">
                                            <i class="fas fa-arrow-left mr-2"></i>Back to List
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
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
$(document).ready(function() {
    // Show/hide suspension reason based on verification status
    function toggleSuspensionReason() {
        const status = $('#manual_verification_status').val();
        const suspensionGroup = $('#suspension_reason_group');
        const suspensionField = $('#suspension_reason');
        
        if (status === 'rejected') {
            suspensionGroup.show();
            suspensionField.prop('required', true);
        } else {
            suspensionGroup.hide();
            suspensionField.prop('required', false);
        }
    }
    
    // Initial check
    toggleSuspensionReason();
    
    // Listen for changes
    $('#manual_verification_status').on('change', toggleSuspensionReason);
    
    // Form validation
    $('form').on('submit', function(e) {
        const status = $('#manual_verification_status').val();
        const suspensionReason = $('#suspension_reason').val();
        
        if (status === 'rejected' && !suspensionReason.trim()) {
            e.preventDefault();
            alert('Suspension reason is required when status is set to rejected.');
            $('#suspension_reason').focus();
            return false;
        }
    });
});
</script>
</body>
</html>
