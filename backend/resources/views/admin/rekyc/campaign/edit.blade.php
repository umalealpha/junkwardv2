<!DOCTYPE html>
<html lang="en">

@include('admin.layouts.header')

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

<!-- Main Content Area -->
<div class="main-content-wrapper" style="background-color: #f8f9fa; min-height: 100vh; padding: 20px;">
    <div class="container-fluid">
        <div class="row justify-content-center">
            <div class="col-12">
                <!-- Page Header -->
                <div class="page-header" style="margin-bottom: 30px;">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h1 class="page-title" style="font-size: 28px; font-weight: 700; color: #2c3e50; margin: 0;">
                                Edit Campaign: {{ $campaign->name }}
                            </h1>
                            <div class="page-navigation" style="margin-top: 8px;">
                                <a href="{{ route('admin.rekyc.campaign.show', $campaign->id) }}" 
                                   style="color: #6c757d; text-decoration: none; font-size: 14px;">
                                    <i class="fas fa-arrow-left" style="margin-right: 5px;"></i>
                                    Back to Campaign
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Main Card -->
                <div class="main-card" style="background: white; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); overflow: hidden;">
                    <form action="{{ route('admin.rekyc.campaign.update', $campaign->id) }}" method="POST">
                        @csrf
                        @method('PUT')
                        
                        <div class="card-content" style="padding: 40px;">
                            <div class="row">
                                <div class="col-md-8">
                                    <!-- Campaign Details Section -->
                                    <div class="campaign-details-section" style="margin-bottom: 40px;">
                                        <div class="form-group" style="margin-bottom: 25px;">
                                            <label for="name" style="display: block; font-weight: 600; color: #2c3e50; margin-bottom: 8px; font-size: 14px;">
                                                Campaign Name <span style="color: #e74c3c;">*</span>
                                            </label>
                                            <input type="text" 
                                                   class="form-control @error('name') is-invalid @enderror" 
                                                   id="name" 
                                                   name="name" 
                                                   value="{{ old('name', $campaign->name) }}" 
                                                   required
                                                   style="border: 1px solid #e1e5e9; border-radius: 8px; padding: 12px 16px; font-size: 14px; transition: border-color 0.3s ease;">
                                            @error('name')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="form-group" style="margin-bottom: 25px;">
                                            <label for="description" style="display: block; font-weight: 600; color: #2c3e50; margin-bottom: 8px; font-size: 14px;">
                                                Description
                                            </label>
                                            <textarea class="form-control @error('description') is-invalid @enderror" 
                                                      id="description" 
                                                      name="description" 
                                                      rows="3" 
                                                      placeholder="Enter campaign description..."
                                                      style="border: 1px solid #e1e5e9; border-radius: 8px; padding: 12px 16px; font-size: 14px; resize: vertical; min-height: 80px;">{{ old('description', $campaign->description) }}</textarea>
                                            @error('description')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="form-group" style="margin-bottom: 25px;">
                                            <label for="status" style="display: block; font-weight: 600; color: #2c3e50; margin-bottom: 8px; font-size: 14px;">
                                                Status <span style="color: #e74c3c;">*</span>
                                            </label>
                                            <select class="form-control @error('status') is-invalid @enderror" 
                                                    id="status" 
                                                    name="status" 
                                                    required
                                                    style="border: 1px solid #e1e5e9; border-radius: 8px; padding: 12px 16px; font-size: 14px; background-color: white;">
                                                <option value="draft" {{ old('status', $campaign->status) === 'draft' ? 'selected' : '' }}>Draft</option>
                                                <option value="active" {{ old('status', $campaign->status) === 'active' ? 'selected' : '' }}>Active</option>
                                                <option value="paused" {{ old('status', $campaign->status) === 'paused' ? 'selected' : '' }}>Paused</option>
                                                <option value="completed" {{ old('status', $campaign->status) === 'completed' ? 'selected' : '' }}>Completed</option>
                                                <option value="cancelled" {{ old('status', $campaign->status) === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                                            </select>
                                            @error('status')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <!-- Campaign Summary Box -->
                                    <div class="campaign-summary-box" style="background: linear-gradient(135deg, #e3f2fd 0%, #f3e5f5 100%); border-radius: 12px; padding: 24px; margin-bottom: 40px; border: 1px solid #e1e5e9;">
                                        <h6 style="font-weight: 600; color: #2c3e50; margin-bottom: 20px; font-size: 16px;">Campaign Summary</h6>
                                        
                                        <div class="summary-item" style="display: flex; align-items: center; margin-bottom: 16px;">
                                            <div class="summary-icon" style="width: 32px; height: 32px; background: #2196f3; border-radius: 6px; display: flex; align-items: center; justify-content: center; margin-right: 12px;">
                                                <i class="fas fa-link" style="color: white; font-size: 14px;"></i>
                                            </div>
                                            <div>
                                                <div style="font-size: 12px; color: #6c757d; margin-bottom: 2px;">Total Links</div>
                                                <div style="font-size: 18px; font-weight: 700; color: #2c3e50;">{{ $campaign->links_count ?? 0 }}</div>
                                            </div>
                                        </div>

                                        <div class="summary-item" style="display: flex; align-items: center; margin-bottom: 16px;">
                                            <div class="summary-icon" style="width: 32px; height: 32px; background: #4caf50; border-radius: 6px; display: flex; align-items: center; justify-content: center; margin-right: 12px;">
                                                <i class="fas fa-check" style="color: white; font-size: 14px;"></i>
                                            </div>
                                            <div>
                                                <div style="font-size: 12px; color: #6c757d; margin-bottom: 2px;">Completed</div>
                                                <div style="font-size: 18px; font-weight: 700; color: #2c3e50;">{{ $campaign->links()->where('status', 'completed')->count() }}</div>
                                            </div>
                                        </div>

                                        <div class="summary-item" style="display: flex; align-items: center;">
                                            <div class="summary-icon" style="width: 32px; height: 32px; background: #ff9800; border-radius: 6px; display: flex; align-items: center; justify-content: center; margin-right: 12px;">
                                                <i class="fas fa-clock" style="color: white; font-size: 14px;"></i>
                                            </div>
                                            <div>
                                                <div style="font-size: 12px; color: #6c757d; margin-bottom: 2px;">Pending</div>
                                                <div style="font-size: 18px; font-weight: 700; color: #2c3e50;">{{ $campaign->links()->where('status', 'pending')->count() }}</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Campaign Settings Section -->
                            <div class="campaign-settings-section" style="margin-bottom: 40px;">
                                <div class="section-header" style="display: flex; align-items: center; margin-bottom: 25px;">
                                    <i class="fas fa-cog" style="color: #6c757d; margin-right: 10px; font-size: 16px;"></i>
                                    <h5 style="margin: 0; font-weight: 600; color: #2c3e50; font-size: 18px;">Campaign Settings</h5>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group" style="margin-bottom: 25px;">
                                            <label for="escalation_days" style="display: block; font-weight: 600; color: #2c3e50; margin-bottom: 8px; font-size: 14px;">
                                                Escalation Days
                                            </label>
                                            <input type="number" 
                                                   class="form-control @error('escalation_days') is-invalid @enderror" 
                                                   id="escalation_days" 
                                                   name="escalation_days" 
                                                   value="{{ old('escalation_days', $campaign->escalation_days ?? 30) }}" 
                                                   min="1" 
                                                   max="30"
                                                   style="border: 1px solid #e1e5e9; border-radius: 8px; padding: 12px 16px; font-size: 14px;">
                                            <small style="color: #6c757d; font-size: 12px; margin-top: 5px; display: block;">
                                                Days after which to escalate pending links
                                            </small>
                                            @error('escalation_days')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="form-group" style="margin-bottom: 25px;">
                                            <label for="reminder_days" style="display: block; font-weight: 600; color: #2c3e50; margin-bottom: 8px; font-size: 14px;">
                                                Reminder Days
                                            </label>
                                            <input type="text" 
                                                   class="form-control @error('reminder_days') is-invalid @enderror" 
                                                   id="reminder_days" 
                                                   name="reminder_days" 
                                                   value="{{ old('reminder_days', is_array($campaign->reminder_days) ? implode(',', $campaign->reminder_days) : $campaign->reminder_days ?? '3,7,14') }}" 
                                                   placeholder="3,7,14"
                                                   style="border: 1px solid #e1e5e9; border-radius: 8px; padding: 12px 16px; font-size: 14px;">
                                            <small style="color: #6c757d; font-size: 12px; margin-top: 5px; display: block;">
                                                Comma-separated days for sending reminders
                                            </small>
                                            @error('reminder_days')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Advanced Settings Section -->
                            <div class="advanced-settings-section" style="margin-bottom: 40px;">
                                <div class="section-header" style="display: flex; align-items: center; margin-bottom: 25px;">
                                    <i class="fas fa-sliders-h" style="color: #6c757d; margin-right: 10px; font-size: 16px;"></i>
                                    <h5 style="margin: 0; font-weight: 600; color: #2c3e50; font-size: 18px;">Advanced Settings</h5>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group" style="margin-bottom: 25px;">
                                            <div class="form-check" style="display: flex; align-items: flex-start;">
                                                <input class="form-check-input" 
                                                       type="checkbox" 
                                                       id="otp_required" 
                                                       name="settings[otp_required]" 
                                                       value="1" 
                                                       {{ old('settings.otp_required', $campaign->settings['otp_required'] ?? true) ? 'checked' : '' }}
                                                       style="margin-top: 4px; margin-right: 10px; transform: scale(1.2);">
                                                <div>
                                                    <label class="form-check-label" for="otp_required" style="font-weight: 600; color: #2c3e50; font-size: 14px; margin-bottom: 5px; display: block;">
                                                        OTP Required
                                                    </label>
                                                    <small style="color: #6c757d; font-size: 12px; display: block;">
                                                        Require OTP verification for link access
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-4">
                                        <div class="form-group" style="margin-bottom: 25px;">
                                            <div class="form-check" style="display: flex; align-items: flex-start;">
                                                <input class="form-check-input" 
                                                       type="checkbox" 
                                                       id="document_upload_required" 
                                                       name="settings[document_upload_required]" 
                                                       value="1" 
                                                       {{ old('settings.document_upload_required', $campaign->settings['document_upload_required'] ?? false) ? 'checked' : '' }}
                                                       style="margin-top: 4px; margin-right: 10px; transform: scale(1.2);">
                                                <div>
                                                    <label class="form-check-label" for="document_upload_required" style="font-weight: 600; color: #2c3e50; font-size: 14px; margin-bottom: 5px; display: block;">
                                                        Document Upload Required
                                                    </label>
                                                    <small style="color: #6c757d; font-size: 12px; display: block;">
                                                        Require document upload for completion
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-4">
                                        <div class="form-group" style="margin-bottom: 25px;">
                                            <div class="form-check" style="display: flex; align-items: flex-start;">
                                                <input class="form-check-input" 
                                                       type="checkbox" 
                                                       id="ocr_enabled" 
                                                       name="settings[ocr_enabled]" 
                                                       value="1" 
                                                       {{ old('settings.ocr_enabled', $campaign->settings['ocr_enabled'] ?? false) ? 'checked' : '' }}
                                                       style="margin-top: 4px; margin-right: 10px; transform: scale(1.2);">
                                                <div>
                                                    <label class="form-check-label" for="ocr_enabled" style="font-weight: 600; color: #2c3e50; font-size: 14px; margin-bottom: 5px; display: block;">
                                                        OCR Enabled
                                                    </label>
                                                    <small style="color: #6c757d; font-size: 12px; display: block;">
                                                        Enable OCR processing for uploaded documents
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group" style="margin-bottom: 25px;">
                                            <label for="max_attempts" style="display: block; font-weight: 600; color: #2c3e50; margin-bottom: 8px; font-size: 14px;">
                                                Max Attempts
                                            </label>
                                            <input type="number" 
                                                   class="form-control" 
                                                   id="max_attempts" 
                                                   name="settings[max_attempts]" 
                                                   value="{{ old('settings.max_attempts', $campaign->settings['max_attempts'] ?? 3) }}" 
                                                   min="1" 
                                                   max="10"
                                                   style="border: 1px solid #e1e5e9; border-radius: 8px; padding: 12px 16px; font-size: 14px;">
                                            <small style="color: #6c757d; font-size: 12px; margin-top: 5px; display: block;">
                                                Maximum number of attempts per link
                                            </small>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="form-group" style="margin-bottom: 25px;">
                                            <label for="link_expiry_days" style="display: block; font-weight: 600; color: #2c3e50; margin-bottom: 8px; font-size: 14px;">
                                                Link Expiry Days
                                            </label>
                                            <input type="number" 
                                                   class="form-control" 
                                                   id="link_expiry_days" 
                                                   name="settings[link_expiry_days]" 
                                                   value="{{ old('settings.link_expiry_days', $campaign->settings['link_expiry_days'] ?? 30) }}" 
                                                   min="1" 
                                                   max="90"
                                                   style="border: 1px solid #e1e5e9; border-radius: 8px; padding: 12px 16px; font-size: 14px;">
                                            <small style="color: #6c757d; font-size: 12px; margin-top: 5px; display: block;">
                                                Days before link expires
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Notification Settings Section -->
                            <div class="notification-settings-section" style="margin-bottom: 40px;">
                                <div class="section-header" style="display: flex; align-items: center; margin-bottom: 25px;">
                                    <i class="fas fa-bell" style="color: #6c757d; margin-right: 10px; font-size: 16px;"></i>
                                    <h5 style="margin: 0; font-weight: 600; color: #2c3e50; font-size: 18px;">Notification Settings</h5>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group" style="margin-bottom: 25px;">
                                            <div class="form-check" style="display: flex; align-items: center;">
                                                <input class="form-check-input" 
                                                       type="checkbox" 
                                                       id="email_enabled" 
                                                       name="settings[email_enabled]" 
                                                       value="1" 
                                                       {{ old('settings.email_enabled', $campaign->settings['email_enabled'] ?? true) ? 'checked' : '' }}
                                                       style="margin-right: 10px; transform: scale(1.2);">
                                                <label class="form-check-label" for="email_enabled" style="font-weight: 600; color: #2c3e50; font-size: 14px;">
                                                    Email Notifications
                                                </label>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-4">
                                        <div class="form-group" style="margin-bottom: 25px;">
                                            <div class="form-check" style="display: flex; align-items: center;">
                                                <input class="form-check-input" 
                                                       type="checkbox" 
                                                       id="whatsapp_enabled" 
                                                       name="settings[whatsapp_enabled]" 
                                                       value="1" 
                                                       {{ old('settings.whatsapp_enabled', $campaign->settings['whatsapp_enabled'] ?? true) ? 'checked' : '' }}
                                                       style="margin-right: 10px; transform: scale(1.2);">
                                                <label class="form-check-label" for="whatsapp_enabled" style="font-weight: 600; color: #2c3e50; font-size: 14px;">
                                                    WhatsApp Notifications
                                                </label>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-4">
                                        <div class="form-group" style="margin-bottom: 25px;">
                                            <div class="form-check" style="display: flex; align-items: center;">
                                                <input class="form-check-input" 
                                                       type="checkbox" 
                                                       id="sms_enabled" 
                                                       name="settings[sms_enabled]" 
                                                       value="1" 
                                                       {{ old('settings.sms_enabled', $campaign->settings['sms_enabled'] ?? true) ? 'checked' : '' }}
                                                       style="margin-right: 10px; transform: scale(1.2);">
                                                <label class="form-check-label" for="sms_enabled" style="font-weight: 600; color: #2c3e50; font-size: 14px;">
                                                    SMS Notifications
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Footer Actions -->
                        <div class="card-footer" style="background: #f8f9fa; border-top: 1px solid #e1e5e9; padding: 20px 40px; display: flex; justify-content: space-between; align-items: center;">
                            <a href="{{ route('admin.rekyc.campaign.show', $campaign->id) }}" 
                               style="display: inline-flex; align-items: center; padding: 10px 20px; background: transparent; color: #6c757d; text-decoration: none; border: 1px solid #e1e5e9; border-radius: 8px; font-weight: 500; transition: all 0.3s ease;">
                                <i class="fas fa-times" style="margin-right: 8px;"></i>
                                Cancel
                            </a>
                            <button type="submit" 
                                    style="display: inline-flex; align-items: center; padding: 12px 24px; background: #007bff; color: white; border: none; border-radius: 8px; font-weight: 600; font-size: 14px; cursor: pointer; transition: all 0.3s ease; box-shadow: 0 2px 4px rgba(0,123,255,0.3);">
                                <i class="fas fa-save" style="margin-right: 8px;"></i>
                                Update Campaign
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Footer -->
<div class="page-footer" style="background: #f8f9fa; padding: 20px 0; margin-top: 40px; border-top: 1px solid #e1e5e9;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-12 text-center">
                <p style="color: #6c757d; font-size: 14px; margin: 0;">
                    Copyright © Risk AI 2025
                </p>
            </div>
        </div>
    </div>
</div>

<!-- Scroll to Top Button -->
<div id="kt_scrolltop" class="kt-scrolltop" style="position: fixed; bottom: 20px; right: 20px; width: 40px; height: 40px; background: #007bff; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; box-shadow: 0 2px 10px rgba(0,123,255,0.3); transition: all 0.3s ease;">
    <i class="la la-arrow-up" style="color: white; font-size: 16px;"></i>
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

<style>
/* Enhanced form styling */
.form-control:focus {
    border-color: #007bff;
    box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
    outline: 0;
}

.form-check-input:checked {
    background-color: #007bff;
    border-color: #007bff;
}

.form-check-input:focus {
    border-color: #007bff;
    box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
}

/* Button hover effects */
.btn:hover, button:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}

/* Summary box hover effect */
.campaign-summary-box:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
}

/* Scroll to top button hover */
#kt_scrolltop:hover {
    background: #0056b3;
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0,123,255,0.4);
}

/* Input focus animations */
input[type="text"]:focus,
input[type="number"]:focus,
textarea:focus,
select:focus {
    transform: translateY(-1px);
    transition: all 0.3s ease;
}

/* Section header animations */
.section-header:hover i {
    color: #007bff;
    transition: color 0.3s ease;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .main-content-wrapper {
        padding: 10px;
    }
    
    .card-content {
        padding: 20px !important;
    }
    
    .page-title {
        font-size: 24px !important;
    }
    
    .campaign-summary-box {
        margin-top: 20px;
    }
}
</style>

<script>
$(document).ready(function() {
    // Handle reminder days input
    $('#reminder_days').on('blur', function() {
        const value = $(this).val();
        const numbers = value.split(',').map(n => parseInt(n.trim())).filter(n => !isNaN(n));
        $(this).val(numbers.join(','));
    });

    // Handle settings checkboxes
    $('input[name^="settings["]').on('change', function() {
        const name = $(this).attr('name');
        const value = $(this).is(':checked') ? 1 : 0;
        
        // Update hidden input if needed
        if (!$(this).is(':checked')) {
            $(this).after(`<input type="hidden" name="${name}" value="0">`);
        } else {
            $(this).next('input[type="hidden"]').remove();
        }
    });

    // Form validation
    $('form').on('submit', function(e) {
        const escalationDays = parseInt($('#escalation_days').val());
        const reminderDays = $('#reminder_days').val().split(',').map(n => parseInt(n.trim())).filter(n => !isNaN(n));
        
        if (reminderDays.length > 0) {
            const maxReminderDay = Math.max(...reminderDays);
            if (maxReminderDay >= escalationDays) {
                e.preventDefault();
                alert('Reminder days should be less than escalation days');
                return false;
            }
        }
    });
});
</script>
</html>
