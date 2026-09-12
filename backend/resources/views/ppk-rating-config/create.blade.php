<!DOCTYPE html>
<html lang="en">

@include('admin.layouts.header')

<body class="kt-header--fixed kt-header-mobile--fixed kt-subheader--enabled kt-subheader--transparent kt-aside--enabled kt-aside--fixed kt-aside--minimize kt-page--loading">
    <div id="kt_header_mobile" class="kt-header-mobile kt-header-mobile--fixed">
        <div class="kt-header-mobile__logo">
            <a>
                <img alt="Logo" src="{{ asset('images/logo.png') }}" />
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

        @if (Auth::user()->password == null)
            @include('includes.reset')
        @else
            <div class="kt-grid__item kt-grid__item--fluid kt-grid kt-grid--hor">
                <div class="kt-subheader kt-grid__item" id="kt_subheader">
                    <div class="kt-subheader__main">
                        <h3 class="kt-subheader__title">Create PPK Rating Configuration</h3>
                        <span class="kt-subheader__separator kt-hidden"></span>
                        <div class="kt-subheader__breadcrumbs">
                            <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                            <span class="kt-subheader__breadcrumbs-separator"></span>
                            <a href="{{ Route('admin-dashboard') }}" class="kt-subheader__breadcrumbs-link">Dashboard</a>
                            <span class="kt-subheader__breadcrumbs-separator"></span>
                            <a href="{{ route('ppk-rating-config.index') }}" class="kt-subheader__breadcrumbs-link">PPK Rating Config</a>
                            <span class="kt-subheader__breadcrumbs-separator"></span>
                            <a class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">Create</a>
                        </div>
                    </div>
                </div>

                <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <ul>
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if(session('success'))
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            {{ session('success') }}
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    @endif

                    <form action="{{ route('ppk-rating-config.store') }}" method="POST">
                        @csrf

                        <!-- Basic Configuration -->
                        <div class="kt-portlet">
                            <div class="kt-portlet__head">
                                <div class="kt-portlet__head-label">
                                    <h3 class="kt-portlet__head-title">Basic Configuration</h3>
                                </div>
                            </div>
                            <div class="kt-portlet__body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Configuration Name <span class="text-danger">*</span></label>
                                            <input type="text" name="config_name" class="form-control" value="{{ old('config_name', $defaultConfig['config_name'] }}" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>
                                                <input type="checkbox" name="is_active" value="1" {{ old('is_active', $defaultConfig['is_active'] ? 'checked' : '' }}>
                                                Set as Active Configuration
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>Description</label>
                                    <textarea name="description" class="form-control" rows="3">{{ old('description', $defaultConfig['description'] }}</textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Distance Range Configuration -->
                        <div class="kt-portlet">
                            <div class="kt-portlet__head">
                                <div class="kt-portlet__head-label">
                                    <h3 class="kt-portlet__head-title">Distance Range Configuration</h3>
                                </div>
                            </div>
                            <div class="kt-portlet__body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Minimum Distance (km) <span class="text-danger">*</span></label>
                                            <input type="number" step="1" name="distance_range_min" class="form-control" value="{{ old('distance_range_min', 200) }}" required>
                                            <small class="form-text text-muted">Minimum distance range in kilometers</small>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Maximum Distance (km) <span class="text-danger">*</span></label>
                                            <input type="number" step="1" name="distance_range_max" class="form-control" value="{{ old('distance_range_max', 1000) }}" required>
                                            <small class="form-text text-muted">Maximum distance range in kilometers</small>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Hidden fields for backend validation -->
                                <input type="hidden" name="base_monthly_premium" value="{{ old('base_monthly_premium', $defaultConfig['base_monthly_premium'] ?? 0) }}">
                                <input type="hidden" name="base_rate_per_km" value="{{ old('base_rate_per_km', $defaultConfig['base_rate_per_km'] ?? 0) }}">
                            </div>
                        </div>

                        <!-- Caps -->
                        <div class="kt-portlet">
                            <div class="kt-portlet__head">
                                <div class="kt-portlet__head-label">
                                    <h3 class="kt-portlet__head-title">Caps Configuration</h3>
                                </div>
                            </div>
                            <div class="kt-portlet__body">
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Per Trip Cap Min (BWP) <span class="text-danger">*</span></label>
                                            <input type="number" step="0.01" name="per_trip_cap_min" class="form-control" value="{{ old('per_trip_cap_min', $defaultConfig['per_trip_cap_min'] }}" required>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Per Trip Cap Max (BWP) <span class="text-danger">*</span></label>
                                            <input type="number" step="0.01" name="per_trip_cap_max" class="form-control" value="{{ old('per_trip_cap_max', $defaultConfig['per_trip_cap_max'] }}" required>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Monthly Cap Min (BWP) <span class="text-danger">*</span></label>
                                            <input type="number" step="0.01" name="monthly_cap_min" class="form-control" value="{{ old('monthly_cap_min', $defaultConfig['monthly_cap_min'] }}" required>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Monthly Cap Max (BWP) <span class="text-danger">*</span></label>
                                            <input type="number" step="0.01" name="monthly_cap_max" class="form-control" value="{{ old('monthly_cap_max', $defaultConfig['monthly_cap_max'] }}" required>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Basic Risk Modifiers -->
                        <div class="kt-portlet">
                            <div class="kt-portlet__head">
                                <div class="kt-portlet__head-label">
                                    <h3 class="kt-portlet__head-title">Basic Risk Modifiers (Decimal Form)</h3>
                                </div>
                            </div>
                            <div class="kt-portlet__body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Night Modifier (e.g., 0.08 for +8%) <span class="text-danger">*</span></label>
                                            <input type="number" step="0.0001" name="night_modifier" class="form-control" value="{{ old('night_modifier', $defaultConfig['night_modifier'] }}" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Rain Modifier (e.g., 0.10 for +10%) <span class="text-danger">*</span></label>
                                            <input type="number" step="0.0001" name="rain_modifier" class="form-control" value="{{ old('rain_modifier', $defaultConfig['rain_modifier'] }}" required>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- OptiDrive Modifiers -->
                        <div class="kt-portlet">
                            <div class="kt-portlet__head">
                                <div class="kt-portlet__head-label">
                                    <h3 class="kt-portlet__head-title">OptiDrive Score Modifiers</h3>
                                </div>
                            </div>
                            <div class="kt-portlet__body">
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <label class="mb-0">Category A (0.8 to 1.0)</label>
                                                <label class="form-check form-switch form-check-custom form-check-solid mb-0">
                                                    <input type="checkbox" name="optidrive_enabled[category_a]" value="1" class="form-check-input optidrive-enable-toggle" data-category="category_a" {{ old('optidrive_enabled.category_a', true) ? 'checked' : '' }}>
                                                    <span class="form-check-label fw-semibold text-muted">Enable</span>
                                                </label>
                                            </div>
                                            @php
                                                $categoryAValue = old('optidrive_modifiers.category_a', 
                                                    is_array($defaultConfig['optidrive_modifiers']['category_a'] ?? null) 
                                                        ? ($defaultConfig['optidrive_modifiers']['category_a']['value'] ?? 0.0)
                                                        : ($defaultConfig['optidrive_modifiers']['category_a'] ?? 0.0)
                                                );
                                            @endphp
                                            <input type="number" step="0.0001" name="optidrive_modifiers[category_a]" class="form-control optidrive-value-input" data-category="category_a" value="{{ $categoryAValue }}" required>
                                            <small class="form-text text-muted">Surcharge: 0%</small>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <label class="mb-0">Category B (0.6 to &lt;0.8)</label>
                                                <label class="form-check form-switch form-check-custom form-check-solid mb-0">
                                                    <input type="checkbox" name="optidrive_enabled[category_b]" value="1" class="form-check-input optidrive-enable-toggle" data-category="category_b" {{ old('optidrive_enabled.category_b', true) ? 'checked' : '' }}>
                                                    <span class="form-check-label fw-semibold text-muted">Enable</span>
                                                </label>
                                            </div>
                                            @php
                                                $categoryBValue = old('optidrive_modifiers.category_b', 
                                                    is_array($defaultConfig['optidrive_modifiers']['category_b'] ?? null) 
                                                        ? ($defaultConfig['optidrive_modifiers']['category_b']['value'] ?? 0.20)
                                                        : ($defaultConfig['optidrive_modifiers']['category_b'] ?? 0.20)
                                                );
                                            @endphp
                                            <input type="number" step="0.0001" name="optidrive_modifiers[category_b]" class="form-control optidrive-value-input" data-category="category_b" value="{{ $categoryBValue }}" required>
                                            <small class="form-text text-muted">Surcharge: 20%</small>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <label class="mb-0">Category C (0.4 to &lt;0.6)</label>
                                                <label class="form-check form-switch form-check-custom form-check-solid mb-0">
                                                    <input type="checkbox" name="optidrive_enabled[category_c]" value="1" class="form-check-input optidrive-enable-toggle" data-category="category_c" {{ old('optidrive_enabled.category_c', true) ? 'checked' : '' }}>
                                                    <span class="form-check-label fw-semibold text-muted">Enable</span>
                                                </label>
                                            </div>
                                            @php
                                                $categoryCValue = old('optidrive_modifiers.category_c', 
                                                    is_array($defaultConfig['optidrive_modifiers']['category_c'] ?? null) 
                                                        ? ($defaultConfig['optidrive_modifiers']['category_c']['value'] ?? 0.40)
                                                        : ($defaultConfig['optidrive_modifiers']['category_c'] ?? 0.40)
                                                );
                                            @endphp
                                            <input type="number" step="0.0001" name="optidrive_modifiers[category_c]" class="form-control optidrive-value-input" data-category="category_c" value="{{ $categoryCValue }}" required>
                                            <small class="form-text text-muted">Surcharge: 40%</small>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <label class="mb-0">Category D (&lt;0.4)</label>
                                                <label class="form-check form-switch form-check-custom form-check-solid mb-0">
                                                    <input type="checkbox" name="optidrive_enabled[category_d]" value="1" class="form-check-input optidrive-enable-toggle" data-category="category_d" {{ old('optidrive_enabled.category_d', true) ? 'checked' : '' }}>
                                                    <span class="form-check-label fw-semibold text-muted">Enable</span>
                                                </label>
                                            </div>
                                            @php
                                                $categoryDValue = old('optidrive_modifiers.category_d', 
                                                    is_array($defaultConfig['optidrive_modifiers']['category_d'] ?? null) 
                                                        ? ($defaultConfig['optidrive_modifiers']['category_d']['value'] ?? 0.60)
                                                        : ($defaultConfig['optidrive_modifiers']['category_d'] ?? 0.60)
                                                );
                                            @endphp
                                            <input type="number" step="0.0001" name="optidrive_modifiers[category_d]" class="form-control optidrive-value-input" data-category="category_d" value="{{ $categoryDValue }}" required>
                                            <small class="form-text text-muted">Surcharge: 60%</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            // Handle enable/disable toggle for OptiDrive categories
                            document.querySelectorAll('.optidrive-enable-toggle').forEach(function(toggle) {
                                toggle.addEventListener('change', function() {
                                    const category = this.getAttribute('data-category');
                                    const input = document.querySelector('.optidrive-value-input[data-category="' + category + '"]');
                                    if (input) {
                                        input.disabled = !this.checked;
                                        if (!this.checked) {
                                            input.style.opacity = '0.5';
                                        } else {
                                            input.style.opacity = '1';
                                        }
                                    }
                                });
                                
                                // Trigger on page load
                                toggle.dispatchEvent(new Event('change'));
                            });
                        });
                        </script>

                        <!-- Driver Age Modifiers -->
                        <div class="kt-portlet">
                            <div class="kt-portlet__head">
                                <div class="kt-portlet__head-label">
                                    <h3 class="kt-portlet__head-title">Driver Age Modifiers</h3>
                                </div>
                            </div>
                            <div class="kt-portlet__body">
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Young (&lt;25)</label>
                                            <input type="number" step="0.0001" name="driver_age_modifiers[young]" class="form-control" value="{{ old('driver_age_modifiers.young', $defaultConfig['driver_age_modifiers']['young'] ?? 0.10) }}">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Adult (25-64)</label>
                                            <input type="number" step="0.0001" name="driver_age_modifiers[adult]" class="form-control" value="{{ old('driver_age_modifiers.adult', $defaultConfig['driver_age_modifiers']['adult'] ?? 0.0) }}">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Senior (65-74)</label>
                                            <input type="number" step="0.0001" name="driver_age_modifiers[senior]" class="form-control" value="{{ old('driver_age_modifiers.senior', $defaultConfig['driver_age_modifiers']['senior'] ?? 0.05) }}">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Elderly (≥75)</label>
                                            <input type="number" step="0.0001" name="driver_age_modifiers[elderly]" class="form-control" value="{{ old('driver_age_modifiers.elderly', $defaultConfig['driver_age_modifiers']['elderly'] ?? 0.15) }}">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Car Age Modifiers -->
                        <div class="kt-portlet">
                            <div class="kt-portlet__head">
                                <div class="kt-portlet__head-label">
                                    <h3 class="kt-portlet__head-title">Car Age Modifiers</h3>
                                </div>
                            </div>
                            <div class="kt-portlet__body">
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>New (0-5 years)</label>
                                            <input type="number" step="0.0001" name="car_age_modifiers[new]" class="form-control" value="{{ old('car_age_modifiers.new', $defaultConfig['car_age_modifiers']['new'] ?? 0.0) }}">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Mid (6-10 years)</label>
                                            <input type="number" step="0.0001" name="car_age_modifiers[mid]" class="form-control" value="{{ old('car_age_modifiers.mid', $defaultConfig['car_age_modifiers']['mid'] ?? 0.03) }}">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Old (11-15 years)</label>
                                            <input type="number" step="0.0001" name="car_age_modifiers[old]" class="form-control" value="{{ old('car_age_modifiers.old', $defaultConfig['car_age_modifiers']['old'] ?? 0.06) }}">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Very Old (16+ years)</label>
                                            <input type="number" step="0.0001" name="car_age_modifiers[very_old]" class="form-control" value="{{ old('car_age_modifiers.very_old', $defaultConfig['car_age_modifiers']['very_old'] ?? 0.08) }}">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Vehicle Type Modifiers -->
                        <div class="kt-portlet">
                            <div class="kt-portlet__head">
                                <div class="kt-portlet__head-label">
                                    <h3 class="kt-portlet__head-title">Vehicle Type Modifiers</h3>
                                </div>
                            </div>
                            <div class="kt-portlet__body">
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Sedan</label>
                                            <input type="number" step="0.0001" name="vehicle_type_modifiers[sedan]" class="form-control" value="{{ old('vehicle_type_modifiers.sedan', $defaultConfig['vehicle_type_modifiers']['sedan'] ?? 0.0) }}">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>SUV</label>
                                            <input type="number" step="0.0001" name="vehicle_type_modifiers[suv]" class="form-control" value="{{ old('vehicle_type_modifiers.suv', $defaultConfig['vehicle_type_modifiers']['suv'] ?? 0.05) }}">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Pickup</label>
                                            <input type="number" step="0.0001" name="vehicle_type_modifiers[pickup]" class="form-control" value="{{ old('vehicle_type_modifiers.pickup', $defaultConfig['vehicle_type_modifiers']['pickup'] ?? 0.07) }}">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>EV</label>
                                            <input type="number" step="0.0001" name="vehicle_type_modifiers[ev]" class="form-control" value="{{ old('vehicle_type_modifiers.ev', $defaultConfig['vehicle_type_modifiers']['ev'] ?? -0.03) }}">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Driver Risk Modifiers -->
                        <div class="kt-portlet">
                            <div class="kt-portlet__head">
                                <div class="kt-portlet__head-label">
                                    <h3 class="kt-portlet__head-title">Driver Risk Tier Modifiers</h3>
                                </div>
                            </div>
                            <div class="kt-portlet__body">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>Low</label>
                                            <input type="number" step="0.0001" name="driver_risk_modifiers[low]" class="form-control" value="{{ old('driver_risk_modifiers.low', $defaultConfig['driver_risk_modifiers']['low'] ?? 0.0) }}">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>Medium</label>
                                            <input type="number" step="0.0001" name="driver_risk_modifiers[medium]" class="form-control" value="{{ old('driver_risk_modifiers.medium', $defaultConfig['driver_risk_modifiers']['medium'] ?? 0.05) }}">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>High</label>
                                            <input type="number" step="0.0001" name="driver_risk_modifiers[high]" class="form-control" value="{{ old('driver_risk_modifiers.high', $defaultConfig['driver_risk_modifiers']['high'] ?? 0.10) }}">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Territory Modifiers -->
                        <div class="kt-portlet">
                            <div class="kt-portlet__head">
                                <div class="kt-portlet__head-label">
                                    <h3 class="kt-portlet__head-title">Territory Modifiers</h3>
                                </div>
                            </div>
                            <div class="kt-portlet__body">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>Urban</label>
                                            <input type="number" step="0.0001" name="territory_modifiers[urban]" class="form-control" value="{{ old('territory_modifiers.urban', $defaultConfig['territory_modifiers']['urban'] ?? 0.05) }}">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>Rural</label>
                                            <input type="number" step="0.0001" name="territory_modifiers[rural]" class="form-control" value="{{ old('territory_modifiers.rural', $defaultConfig['territory_modifiers']['rural'] ?? 0.0) }}">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>Off-road</label>
                                            <input type="number" step="0.0001" name="territory_modifiers[offroad]" class="form-control" value="{{ old('territory_modifiers.offroad', $defaultConfig['territory_modifiers']['offroad'] ?? 0.10) }}">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Trip Type Modifiers -->
                        <div class="kt-portlet">
                            <div class="kt-portlet__head">
                                <div class="kt-portlet__head-label">
                                    <h3 class="kt-portlet__head-title">Trip Type Modifiers</h3>
                                </div>
                            </div>
                            <div class="kt-portlet__body">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>Commute</label>
                                            <input type="number" step="0.0001" name="trip_type_modifiers[commute]" class="form-control" value="{{ old('trip_type_modifiers.commute', $defaultConfig['trip_type_modifiers']['commute'] ?? 0.0) }}">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>Long Trip</label>
                                            <input type="number" step="0.0001" name="trip_type_modifiers[long_trip]" class="form-control" value="{{ old('trip_type_modifiers.long_trip', $defaultConfig['trip_type_modifiers']['long_trip'] ?? 0.02) }}">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>Off-road</label>
                                            <input type="number" step="0.0001" name="trip_type_modifiers[offroad]" class="form-control" value="{{ old('trip_type_modifiers.offroad', $defaultConfig['trip_type_modifiers']['offroad'] ?? 0.08) }}">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Usage Frequency Modifiers -->
                        <div class="kt-portlet">
                            <div class="kt-portlet__head">
                                <div class="kt-portlet__head-label">
                                    <h3 class="kt-portlet__head-title">Usage Frequency Modifiers (Monthly KM)</h3>
                                </div>
                            </div>
                            <div class="kt-portlet__body">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>&lt;500 km</label>
                                            <input type="number" step="0.0001" name="usage_frequency_modifiers[lt_500]" class="form-control" value="{{ old('usage_frequency_modifiers.lt_500', $defaultConfig['usage_frequency_modifiers']['lt_500'] ?? -0.05) }}">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>500-1000 km</label>
                                            <input type="number" step="0.0001" name="usage_frequency_modifiers[btw_500_1000]" class="form-control" value="{{ old('usage_frequency_modifiers.btw_500_1000', $defaultConfig['usage_frequency_modifiers']['btw_500_1000'] ?? 0.0) }}">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>&gt;1000 km</label>
                                            <input type="number" step="0.0001" name="usage_frequency_modifiers[gt_1000]" class="form-control" value="{{ old('usage_frequency_modifiers.gt_1000', $defaultConfig['usage_frequency_modifiers']['gt_1000'] ?? 0.05) }}">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Policy Tenure Modifiers -->
                        <div class="kt-portlet">
                            <div class="kt-portlet__head">
                                <div class="kt-portlet__head-label">
                                    <h3 class="kt-portlet__head-title">Policy Tenure Modifiers</h3>
                                </div>
                            </div>
                            <div class="kt-portlet__body">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>&lt;2 years</label>
                                            <input type="number" step="0.0001" name="policy_tenure_modifiers[lt_2y]" class="form-control" value="{{ old('policy_tenure_modifiers.lt_2y', $defaultConfig['policy_tenure_modifiers']['lt_2y'] ?? 0.0) }}">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>&gt;2 years</label>
                                            <input type="number" step="0.0001" name="policy_tenure_modifiers[gt_2y]" class="form-control" value="{{ old('policy_tenure_modifiers.gt_2y', $defaultConfig['policy_tenure_modifiers']['gt_2y'] ?? -0.03) }}">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>&gt;5 years</label>
                                            <input type="number" step="0.0001" name="policy_tenure_modifiers[gt_5y]" class="form-control" value="{{ old('policy_tenure_modifiers.gt_5y', $defaultConfig['policy_tenure_modifiers']['gt_5y'] ?? -0.05) }}">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Claims History Modifiers -->
                        <div class="kt-portlet">
                            <div class="kt-portlet__head">
                                <div class="kt-portlet__head-label">
                                    <h3 class="kt-portlet__head-title">Claims History Modifiers</h3>
                                </div>
                            </div>
                            <div class="kt-portlet__body">
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>0 claims</label>
                                            <input type="number" step="0.0001" name="claims_history_modifiers[c0]" class="form-control" value="{{ old('claims_history_modifiers.c0', $defaultConfig['claims_history_modifiers']['c0'] ?? 0.0) }}">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>1 claim</label>
                                            <input type="number" step="0.0001" name="claims_history_modifiers[c1]" class="form-control" value="{{ old('claims_history_modifiers.c1', $defaultConfig['claims_history_modifiers']['c1'] ?? 0.03) }}">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>2 claims</label>
                                            <input type="number" step="0.0001" name="claims_history_modifiers[c2]" class="form-control" value="{{ old('claims_history_modifiers.c2', $defaultConfig['claims_history_modifiers']['c2'] ?? 0.06) }}">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>≥3 claims</label>
                                            <input type="number" step="0.0001" name="claims_history_modifiers[c3_plus]" class="form-control" value="{{ old('claims_history_modifiers.c3_plus', $defaultConfig['claims_history_modifiers']['c3_plus'] ?? 0.10) }}">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Gender Modifiers -->
                        <div class="kt-portlet">
                            <div class="kt-portlet__head">
                                <div class="kt-portlet__head-label">
                                    <h3 class="kt-portlet__head-title">Gender Modifiers</h3>
                                </div>
                            </div>
                            <div class="kt-portlet__body">
                                <div class="form-group">
                                    <label>
                                        <input type="checkbox" name="gender_enabled" value="1" {{ old('gender_enabled', $defaultConfig['gender_enabled'] ? 'checked' : '' }}>
                                        Enable Gender Factor
                                    </label>
                                </div>
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>Male</label>
                                            <input type="number" step="0.0001" name="gender_modifiers[M]" class="form-control" value="{{ old('gender_modifiers.M', $defaultConfig['gender_modifiers']['M'] ?? 0.02) }}">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>Female</label>
                                            <input type="number" step="0.0001" name="gender_modifiers[F]" class="form-control" value="{{ old('gender_modifiers.F', $defaultConfig['gender_modifiers']['F'] ?? 0.0) }}">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>Other</label>
                                            <input type="number" step="0.0001" name="gender_modifiers[X]" class="form-control" value="{{ old('gender_modifiers.X', $defaultConfig['gender_modifiers']['X'] ?? 0.0) }}">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Event Penalty Rates -->
                        <div class="kt-portlet">
                            <div class="kt-portlet__head">
                                <div class="kt-portlet__head-label">
                                    <h3 class="kt-portlet__head-title">Event Penalty Rates</h3>
                                </div>
                            </div>
                            <div class="kt-portlet__body">
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Speeding Penalty Per Event (BWP) <span class="text-danger">*</span></label>
                                            <input type="number" step="0.01" name="speeding_penalty_per_event" class="form-control" value="{{ old('speeding_penalty_per_event', $defaultConfig['speeding_penalty_per_event'] }}" required>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Harsh Acceleration Rate (BWP/km) <span class="text-danger">*</span></label>
                                            <input type="number" step="0.01" name="harsh_acceleration_rate" class="form-control" value="{{ old('harsh_acceleration_rate', $defaultConfig['harsh_acceleration_rate'] }}" required>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Idle Time Rate (BWP/km) <span class="text-danger">*</span></label>
                                            <input type="number" step="0.01" name="idle_time_rate" class="form-control" value="{{ old('idle_time_rate', $defaultConfig['idle_time_rate'] }}" required>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>High Revving Rate (BWP/km) <span class="text-danger">*</span></label>
                                            <input type="number" step="0.01" name="high_revving_rate" class="form-control" value="{{ old('high_revving_rate', $defaultConfig['high_revving_rate'] }}" required>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Weather Severity Modifiers -->
                        <div class="kt-portlet">
                            <div class="kt-portlet__head">
                                <div class="kt-portlet__head-label">
                                    <h3 class="kt-portlet__head-title">Weather Severity Modifiers</h3>
                                </div>
                            </div>
                            <div class="kt-portlet__body">
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Drizzle</label>
                                            <input type="number" step="0.0001" name="weather_severity_modifiers[drizzle]" class="form-control" value="{{ old('weather_severity_modifiers.drizzle', $defaultConfig['weather_severity_modifiers']['drizzle'] ?? 0.05) }}">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Rain</label>
                                            <input type="number" step="0.0001" name="weather_severity_modifiers[rain]" class="form-control" value="{{ old('weather_severity_modifiers.rain', $defaultConfig['weather_severity_modifiers']['rain'] ?? 0.10) }}">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Heavy Rain</label>
                                            <input type="number" step="0.0001" name="weather_severity_modifiers[heavy_rain]" class="form-control" value="{{ old('weather_severity_modifiers.heavy_rain', $defaultConfig['weather_severity_modifiers']['heavy_rain'] ?? 0.15) }}">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Thunderstorm</label>
                                            <input type="number" step="0.0001" name="weather_severity_modifiers[thunderstorm]" class="form-control" value="{{ old('weather_severity_modifiers.thunderstorm', $defaultConfig['weather_severity_modifiers']['thunderstorm'] ?? 0.15) }}">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Night Segment Modifiers -->
                        <div class="kt-portlet">
                            <div class="kt-portlet__head">
                                <div class="kt-portlet__head-label">
                                    <h3 class="kt-portlet__head-title">Night Segment Modifiers</h3>
                                </div>
                            </div>
                            <div class="kt-portlet__body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Evening (19:00-22:00)</label>
                                            <input type="number" step="0.0001" name="night_segment_modifiers[evening]" class="form-control" value="{{ old('night_segment_modifiers.evening', $defaultConfig['night_segment_modifiers']['evening'] ?? 0.05) }}">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Late Night (22:00-05:00)</label>
                                            <input type="number" step="0.0001" name="night_segment_modifiers[late_night]" class="form-control" value="{{ old('night_segment_modifiers.late_night', $defaultConfig['night_segment_modifiers']['late_night'] ?? 0.10) }}">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Other Modifiers -->
                        <div class="kt-portlet">
                            <div class="kt-portlet__head">
                                <div class="kt-portlet__head-label">
                                    <h3 class="kt-portlet__head-title">Other Modifiers</h3>
                                </div>
                            </div>
                            <div class="kt-portlet__body">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>Congestion Modifier <span class="text-danger">*</span></label>
                                            <input type="number" step="0.0001" name="congestion_modifier" class="form-control" value="{{ old('congestion_modifier', $defaultConfig['congestion_modifier'] }}" required>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>Seasonal Modifier <span class="text-danger">*</span></label>
                                            <input type="number" step="0.0001" name="seasonal_modifier" class="form-control" value="{{ old('seasonal_modifier', $defaultConfig['seasonal_modifier'] }}" required>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>ADAS Discount <span class="text-danger">*</span></label>
                                            <input type="number" step="0.0001" name="adas_discount" class="form-control" value="{{ old('adas_discount', $defaultConfig['adas_discount'] }}" required>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Roadworthiness Discount <span class="text-danger">*</span></label>
                                            <input type="number" step="0.0001" name="roadworthiness_discount" class="form-control" value="{{ old('roadworthiness_discount', $defaultConfig['roadworthiness_discount'] }}" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Safe Driver Cashback <span class="text-danger">*</span></label>
                                            <input type="number" step="0.0001" name="safe_driver_cashback" class="form-control" value="{{ old('safe_driver_cashback', $defaultConfig['safe_driver_cashback'] }}" required>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Form Actions -->
                        <div class="kt-portlet">
                            <div class="kt-portlet__foot">
                                <div class="kt-form__actions">
                                    <button type="submit" class="btn btn-primary">Create Configuration</button>
                                    <a href="{{ route('ppk-rating-config.index') }}" class="btn btn-secondary">Cancel</a>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </div>

    @include('admin.layouts.scripts')
</body>
</html>

