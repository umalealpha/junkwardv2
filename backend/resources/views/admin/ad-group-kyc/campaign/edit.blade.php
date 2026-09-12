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
    <!-- check if is first time login -->
<!-- begin::Body -->
<div class="container-fluid" style="padding: 20px;">
    <div class="row">
        <div class="col-12">
            <!-- Edit Campaign Form -->
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3 class="card-title mb-1">
                                <i class="fas fa-edit mr-2"></i>
                                Edit AD Group KYC Campaign
                            </h3>
                            <p class="text-white-50 mb-0">Update campaign details and settings</p>
                        </div>
                        <div>
                            <a href="{{ route('admin.ad-group-kyc.campaign.show', $campaign->id) }}" class="btn btn-light btn-sm">
                                <i class="fas fa-arrow-left mr-1"></i> Back to Campaign
                            </a>
                        </div>
                    </div>
                </div>
                <div class="card-body">
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

                    <form method="POST" action="{{ route('admin.ad-group-kyc.campaign.update', $campaign->id) }}">
                        @csrf
                        @method('PUT')
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="name">Campaign Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="name" name="name" 
                                           value="{{ old('name', $campaign->name) }}" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="status">Status <span class="text-danger">*</span></label>
                                    <select class="form-control" id="status" name="status" required>
                                        <option value="active" {{ old('status', $campaign->status) == 'active' ? 'selected' : '' }}>Active</option>
                                        <option value="completed" {{ old('status', $campaign->status) == 'completed' ? 'selected' : '' }}>Completed</option>
                                        <option value="paused" {{ old('status', $campaign->status) == 'paused' ? 'selected' : '' }}>Paused</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="description">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3">{{ old('description', $campaign->description) }}</textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="escalation_days">Escalation Days</label>
                                    <input type="number" class="form-control" id="escalation_days" name="escalation_days" 
                                           value="{{ old('escalation_days', $campaign->escalation_days ?? 30) }}" min="1" max="365">
                                    <small class="form-text text-muted">Days after which to send escalation notifications</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="reminder_days">Reminder Days</label>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="reminder_days[]" value="3" 
                                               {{ in_array(3, old('reminder_days', $campaign->reminder_days ?? [3, 7, 14])) ? 'checked' : '' }}>
                                        <label class="form-check-label">3 days</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="reminder_days[]" value="7" 
                                               {{ in_array(7, old('reminder_days', $campaign->reminder_days ?? [3, 7, 14])) ? 'checked' : '' }}>
                                        <label class="form-check-label">7 days</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="reminder_days[]" value="14" 
                                               {{ in_array(14, old('reminder_days', $campaign->reminder_days ?? [3, 7, 14])) ? 'checked' : '' }}>
                                        <label class="form-check-label">14 days</label>
                                    </div>
                                    <small class="form-text text-muted">Days after sending to send reminder notifications</small>
                                </div>
                            </div>
                        </div>

                        <div class="form-group mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save mr-2"></i> Update Campaign
                            </button>
                            <a href="{{ route('admin.ad-group-kyc.campaign.show', $campaign->id) }}" class="btn btn-secondary ml-2">
                                <i class="fas fa-times mr-2"></i> Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- end::Body -->
@include('includes.footer')
</div>
@include('admin.layouts.scripts')
</html>
