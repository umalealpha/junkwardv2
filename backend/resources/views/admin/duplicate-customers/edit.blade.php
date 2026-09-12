@extends('admin.layouts.app')

@section('styles')
<!-- SweetAlert2 CDN -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
@endsection

@section('title', 'Edit Duplicate Customer')

@section('content')
<div class="kt-container  kt-container--fluid  kt-grid__item kt-grid__item--fluid">
    <div class="kt-subheader   kt-grid__item" id="kt_subheader">
        <div class="kt-container  kt-container--fluid ">
            <div class="kt-subheader__main">
                <h3 class="kt-subheader__title">Edit Duplicate Customer</h3>
                <span class="kt-subheader__separator kt-subheader__separator--v"></span>
                <div class="kt-subheader__breadcrumbs">
                    <a href="{{ route('admin.duplicate-customers.index') }}" class="kt-subheader__breadcrumbs-home">
                        <i class="flaticon2-shelter"></i>
                    </a>
                    <span class="kt-subheader__breadcrumbs-separator"></span>
                    <a href="{{ route('admin.duplicate-customers.index') }}" class="kt-subheader__breadcrumbs-link">Duplicate Customers</a>
                    <span class="kt-subheader__breadcrumbs-separator"></span>
                    <a href="{{ route('admin.duplicate-customers.show', $duplicate->id) }}" class="kt-subheader__breadcrumbs-link">View Details</a>
                    <span class="kt-subheader__breadcrumbs-separator"></span>
                    <span class="kt-subheader__breadcrumbs-link">Edit</span>
                </div>
            </div>
        </div>
    </div>

    <div class="kt-container  kt-container--fluid  kt-grid__item kt-grid__item--fluid">
        <div class="kt-portlet kt-portlet--mobile">
            <div class="kt-portlet__head">
                <div class="kt-portlet__head-label">
                    <h3 class="kt-portlet__head-title">
                        Edit Duplicate Customer Record
                    </h3>
                </div>
                <div class="kt-portlet__head-toolbar">
                    <a href="{{ route('admin.duplicate-customers.show', $duplicate->id) }}" class="btn btn-secondary">
                        <i class="flaticon2-back"></i> Back to Details
                    </a>
                </div>
            </div>

            <div class="kt-portlet__body">
                @if ($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if (session('success'))
                    <div class="alert alert-success">
                        {{ session('success') }}
                    </div>
                @endif

                @if (session('error'))
                    <div class="alert alert-danger">
                        {{ session('error') }}
                    </div>
                @endif

                <form action="{{ route('admin.duplicate-customers.update', $duplicate->id) }}" method="POST" id="editForm">
                    @csrf
                    @method('PUT')

                    <div class="row">
                        <!-- Customer Information -->
                        <div class="col-md-6">
                            <div class="kt-section">
                                <h4 class="kt-section__title">Customer Information</h4>
                                <div class="kt-section__content">
                                    <div class="form-group">
                                        <label for="first_name">First Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="first_name" name="first_name" 
                                               value="{{ old('first_name', $duplicate->first_name) }}" required>
                                    </div>

                                    <div class="form-group">
                                        <label for="last_name">Last Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="last_name" name="last_name" 
                                               value="{{ old('last_name', $duplicate->last_name) }}" required>
                                    </div>

                                    <div class="form-group">
                                        <label for="email">Email</label>
                                        <input type="email" class="form-control" id="email" name="email" 
                                               value="{{ old('email', $duplicate->email) }}">
                                    </div>

                                    <div class="form-group">
                                        <label for="cellphone">Cellphone</label>
                                        <input type="text" class="form-control" id="cellphone" name="cellphone" 
                                               value="{{ old('cellphone', $duplicate->cellphone) }}">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Document Information -->
                        <div class="col-md-6">
                            <div class="kt-section">
                                <h4 class="kt-section__title">Document Information</h4>
                                <div class="kt-section__content">
                                    <div class="form-group">
                                        <label for="omang_number">Omang Number</label>
                                        <input type="text" class="form-control" id="omang_number" name="omang_number" 
                                               value="{{ old('omang_number', $duplicate->omang_number) }}">
                                    </div>

                                    <div class="form-group">
                                        <label for="passport_number">Passport Number</label>
                                        <input type="text" class="form-control" id="passport_number" name="passport_number" 
                                               value="{{ old('passport_number', $duplicate->passport_number) }}">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <!-- Banking Information -->
                        <div class="col-md-6">
                            <div class="kt-section">
                                <h4 class="kt-section__title">Banking Information</h4>
                                <div class="kt-section__content">
                                    <div class="form-group">
                                        <label for="bank_account_number">Account Number</label>
                                        <input type="text" class="form-control" id="bank_account_number" name="bank_account_number" 
                                               value="{{ old('bank_account_number', $duplicate->bank_account_number) }}">
                                    </div>

                                    <div class="form-group">
                                        <label for="bank_name">Bank Name</label>
                                        <input type="text" class="form-control" id="bank_name" name="bank_name" 
                                               value="{{ old('bank_name', $duplicate->bank_name) }}">
                                    </div>

                                    <div class="form-group">
                                        <label for="bank_branch">Bank Branch</label>
                                        <input type="text" class="form-control" id="bank_branch" name="bank_branch" 
                                               value="{{ old('bank_branch', $duplicate->bank_branch) }}">
                                    </div>

                                    <div class="form-group">
                                        <label for="billing">Billing</label>
                                        <input type="text" class="form-control" id="billing" name="billing" 
                                               value="{{ old('billing', $duplicate->billing) }}">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Duplicate Information -->
                        <div class="col-md-6">
                            <div class="kt-section">
                                <h4 class="kt-section__title">Duplicate Information</h4>
                                <div class="kt-section__content">
                                    <div class="form-group">
                                        <label for="duplicate_type">Duplicate Type <span class="text-danger">*</span></label>
                                        <select class="form-control" id="duplicate_type" name="duplicate_type" required>
                                            <option value="omang_passport" {{ old('duplicate_type', $duplicate->duplicate_type) == 'omang_passport' ? 'selected' : '' }}>
                                                Omang/Passport
                                            </option>
                                            <option value="cellphone_email" {{ old('duplicate_type', $duplicate->duplicate_type) == 'cellphone_email' ? 'selected' : '' }}>
                                                Cellphone/Email
                                            </option>
                                            <option value="multiple_accounts" {{ old('duplicate_type', $duplicate->duplicate_type) == 'multiple_accounts' ? 'selected' : '' }}>
                                                Multiple Accounts
                                            </option>
                                        </select>
                                    </div>

                                    <div class="form-group">
                                        <label for="duplicate_reason">Duplicate Reason <span class="text-danger">*</span></label>
                                        <textarea class="form-control" id="duplicate_reason" name="duplicate_reason" rows="3" required>{{ old('duplicate_reason', $duplicate->duplicate_reason) }}</textarea>
                                    </div>

                                    <div class="form-group">
                                        <label for="status">Status <span class="text-danger">*</span></label>
                                        <select class="form-control" id="status" name="status" required>
                                            <option value="pending" {{ old('status', $duplicate->status) == 'pending' ? 'selected' : '' }}>Pending</option>
                                            <option value="reviewed" {{ old('status', $duplicate->status) == 'reviewed' ? 'selected' : '' }}>Reviewed</option>
                                            <option value="resolved" {{ old('status', $duplicate->status) == 'resolved' ? 'selected' : '' }}>Resolved</option>
                                            <option value="ignored" {{ old('status', $duplicate->status) == 'ignored' ? 'selected' : '' }}>Ignored</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <!-- Additional Information -->
                        <div class="col-12">
                            <div class="kt-section">
                                <h4 class="kt-section__title">Additional Information</h4>
                                <div class="kt-section__content">
                                    <div class="form-group">
                                        <label for="resolution_notes">Resolution Notes</label>
                                        <textarea class="form-control" id="resolution_notes" name="resolution_notes" rows="3">{{ old('resolution_notes', $duplicate->resolution_notes) }}</textarea>
                                    </div>

                                    <div class="form-group">
                                        <label for="notes">Notes</label>
                                        <textarea class="form-control" id="notes" name="notes" rows="3">{{ old('notes', $duplicate->notes) }}</textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="kt-form__actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="flaticon2-check-mark"></i> Update Record
                        </button>
                        <a href="{{ route('admin.duplicate-customers.show', $duplicate->id) }}" class="btn btn-secondary">
                            <i class="flaticon2-close"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
.kt-section {
    margin-bottom: 30px;
}

.kt-section__title {
    font-size: 16px;
    font-weight: 600;
    color: #5d78ff;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 2px solid #f4f5f8;
}

.kt-section__content {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 6px;
    border: 1px solid #e9ecef;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    font-weight: 600;
    color: #495057;
    margin-bottom: 5px;
}

.form-control {
    border: 1px solid #e4e6ef;
    border-radius: 4px;
    padding: 10px 12px;
    font-size: 14px;
    transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
}

.form-control:focus {
    border-color: #5d78ff;
    box-shadow: 0 0 0 0.2rem rgba(93, 120, 255, 0.25);
}

.kt-form__actions {
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid #e9ecef;
    text-align: right;
}

.kt-form__actions .btn {
    margin-left: 10px;
}

.text-danger {
    color: #f64e60 !important;
}

.alert {
    border-radius: 6px;
    margin-bottom: 20px;
}

.alert ul {
    margin-bottom: 0;
}
</style>

<script>
$(document).ready(function() {
    // Form validation
    $('#editForm').on('submit', function(e) {
        var isValid = true;
        
        // Clear previous error states
        $('.form-control').removeClass('is-invalid');
        $('.invalid-feedback').remove();
        
        // Validate required fields
        $('[required]').each(function() {
            if (!$(this).val().trim()) {
                $(this).addClass('is-invalid');
                $(this).after('<div class="invalid-feedback">This field is required.</div>');
                isValid = false;
            }
        });
        
        // Validate email format
        var email = $('#email').val();
        if (email && !isValidEmail(email)) {
            $('#email').addClass('is-invalid');
            $('#email').after('<div class="invalid-feedback">Please enter a valid email address.</div>');
            isValid = false;
        }
        
        if (!isValid) {
            e.preventDefault();
            alert('Please fix the validation errors before submitting.');
        }
    });
    
    function isValidEmail(email) {
        var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }
    
    // Auto-save functionality (optional)
    $('.form-control').on('change', function() {
        // You can implement auto-save functionality here
        console.log('Field changed:', $(this).attr('name'), $(this).val());
    });
});
</script>
@endsection
