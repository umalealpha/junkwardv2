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
                        <h3 class="kt-subheader__title">Edit PPK Rating Configuration</h3>
                        <span class="kt-subheader__separator kt-hidden"></span>
                        <div class="kt-subheader__breadcrumbs">
                            <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                            <span class="kt-subheader__breadcrumbs-separator"></span>
                            <a href="{{ Route('admin-dashboard') }}" class="kt-subheader__breadcrumbs-link">Dashboard</a>
                            <span class="kt-subheader__breadcrumbs-separator"></span>
                            <a href="{{ route('ppk-rating-config.index') }}" class="kt-subheader__breadcrumbs-link">PPK Rating Config</a>
                            <span class="kt-subheader__breadcrumbs-separator"></span>
                            <a class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">Edit</a>
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

                    <form action="{{ route('ppk-rating-config.update', $ppkRatingConfig->id) }}" method="POST">
                        @csrf
                        @method('PUT')

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
                                            <input type="text" name="config_name" class="form-control" value="{{ old('config_name', $ppkRatingConfig->config_name) }}" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>
                                                <input type="checkbox" name="is_active" value="1" {{ old('is_active', $ppkRatingConfig->is_active) ? 'checked' : '' }}>
                                                Set as Active Configuration
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>Description</label>
                                    <textarea name="description" class="form-control" rows="3">{{ old('description', $ppkRatingConfig->description) }}</textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Distance Range Configuration -->
                        <div class="kt-portlet" data-section="distance_range_config">
                            <div class="kt-portlet__head">
                                <div class="kt-portlet__head-label">
                                    <h3 class="kt-portlet__head-title">Distance Range Configuration</h3>
                                </div>
                                <div class="kt-portlet__head-toolbar">
                                    <label class="form-check form-switch form-check-custom form-check-solid">
                                        <input type="checkbox" name="section_enabled[distance_range_config]" value="1" class="form-check-input section-enable-toggle" data-section="distance_range_config" {{ old('section_enabled.distance_range_config', $ppkRatingConfig->section_enabled['distance_range_config'] ?? true) ? 'checked' : '' }}>
                                        <span class="form-check-label fw-semibold">Enable Section</span>
                                    </label>
                                </div>
                            </div>
                            <div class="kt-portlet__body section-body" data-section="distance_range_config">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Minimum Distance (km) <span class="text-danger">*</span></label>
                                            <input type="number" step="1" name="distance_range_min" class="form-control section-input" data-section="distance_range_config" value="{{ old('distance_range_min', $ppkRatingConfig->distance_range_min ?? 200) }}" required>
                                            <small class="form-text text-muted">Minimum distance range in kilometers</small>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Maximum Distance (km) <span class="text-danger">*</span></label>
                                            <input type="number" step="1" name="distance_range_max" class="form-control section-input" data-section="distance_range_config" value="{{ old('distance_range_max', $ppkRatingConfig->distance_range_max ?? 1000) }}" required>
                                            <small class="form-text text-muted">Maximum distance range in kilometers</small>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Hidden fields for backend validation -->
                                <input type="hidden" name="base_monthly_premium" value="{{ old('base_monthly_premium', $ppkRatingConfig->base_monthly_premium ?? 0) }}">
                                <input type="hidden" name="base_rate_per_km" value="{{ old('base_rate_per_km', $ppkRatingConfig->base_rate_per_km ?? 0) }}">
                            </div>
                        </div>

                        <!-- Caps -->
                        <div class="kt-portlet" data-section="caps_config">
                            <div class="kt-portlet__head">
                                <div class="kt-portlet__head-label">
                                    <h3 class="kt-portlet__head-title">Caps Configuration</h3>
                                </div>
                                <div class="kt-portlet__head-toolbar">
                                    <label class="form-check form-switch form-check-custom form-check-solid">
                                        <input type="checkbox" name="section_enabled[caps_config]" value="1" class="form-check-input section-enable-toggle" data-section="caps_config" {{ old('section_enabled.caps_config', $ppkRatingConfig->section_enabled['caps_config'] ?? true) ? 'checked' : '' }}>
                                        <span class="form-check-label fw-semibold">Enable Section</span>
                                    </label>
                                </div>
                            </div>
                            <div class="kt-portlet__body section-body" data-section="caps_config">
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Per Trip Cap Min (BWP) <span class="text-danger">*</span></label>
                                            <input type="number" step="0.01" name="per_trip_cap_min" class="form-control section-input" data-section="caps_config" value="{{ old('per_trip_cap_min', $ppkRatingConfig->per_trip_cap_min) }}" required>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Per Trip Cap Max (BWP) <span class="text-danger">*</span></label>
                                            <input type="number" step="0.01" name="per_trip_cap_max" class="form-control section-input" data-section="caps_config" value="{{ old('per_trip_cap_max', $ppkRatingConfig->per_trip_cap_max) }}" required>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Monthly Cap Min (BWP) <span class="text-danger">*</span></label>
                                            <input type="number" step="0.01" name="monthly_cap_min" class="form-control section-input" data-section="caps_config" value="{{ old('monthly_cap_min', $ppkRatingConfig->monthly_cap_min) }}" required>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Monthly Cap Max (BWP) <span class="text-danger">*</span></label>
                                            <input type="number" step="0.01" name="monthly_cap_max" class="form-control section-input" data-section="caps_config" value="{{ old('monthly_cap_max', $ppkRatingConfig->monthly_cap_max) }}" required>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Basic Risk Modifiers -->
                        <div class="kt-portlet" data-section="basic_risk_modifiers">
                            <div class="kt-portlet__head">
                                <div class="kt-portlet__head-label">
                                    <h3 class="kt-portlet__head-title">Basic Risk Modifiers (Decimal Form)</h3>
                                </div>
                                <div class="kt-portlet__head-toolbar">
                                    <label class="form-check form-switch form-check-custom form-check-solid">
                                        <input type="checkbox" name="section_enabled[basic_risk_modifiers]" value="1" class="form-check-input section-enable-toggle" data-section="basic_risk_modifiers" {{ old('section_enabled.basic_risk_modifiers', $ppkRatingConfig->section_enabled['basic_risk_modifiers'] ?? true) ? 'checked' : '' }}>
                                        <span class="form-check-label fw-semibold">Enable Section</span>
                                    </label>
                                </div>
                            </div>
                            <div class="kt-portlet__body section-body" data-section="basic_risk_modifiers">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Night Modifier (e.g., 0.08 for +8%) <span class="text-danger">*</span></label>
                                            <input type="number" step="0.0001" name="night_modifier" class="form-control section-input" data-section="basic_risk_modifiers" value="{{ old('night_modifier', $ppkRatingConfig->night_modifier) }}" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Rain Modifier (e.g., 0.10 for +10%) <span class="text-danger">*</span></label>
                                            <input type="number" step="0.0001" name="rain_modifier" class="form-control section-input" data-section="basic_risk_modifiers" value="{{ old('rain_modifier', $ppkRatingConfig->rain_modifier) }}" required>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- OptiDrive Modifiers -->
                        <div class="kt-portlet" data-section="optidrive_modifiers">
                            <div class="kt-portlet__head">
                                <div class="kt-portlet__head-label">
                                    <h3 class="kt-portlet__head-title">OptiDrive Score Modifiers</h3>
                                </div>
                                <div class="kt-portlet__head-toolbar">
                                    <label class="form-check form-switch form-check-custom form-check-solid">
                                        <input type="checkbox" name="section_enabled[optidrive_modifiers]" value="1" class="form-check-input section-enable-toggle" data-section="optidrive_modifiers" {{ old('section_enabled.optidrive_modifiers', $ppkRatingConfig->section_enabled['optidrive_modifiers'] ?? true) ? 'checked' : '' }}>
                                        <span class="form-check-label fw-semibold">Enable Section</span>
                                    </label>
                                </div>
                            </div>
                            <div class="kt-portlet__body section-body" data-section="optidrive_modifiers">
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Category A (0.8 to 1.0) <span class="text-danger">*</span></label>
                                            @php
                                                $categoryAValue = old('optidrive_modifiers.category_a', 
                                                    is_array($ppkRatingConfig->optidrive_modifiers['category_a'] ?? null) 
                                                        ? ($ppkRatingConfig->optidrive_modifiers['category_a']['value'] ?? 0.0)
                                                        : ($ppkRatingConfig->optidrive_modifiers['category_a'] ?? 0.0)
                                                );
                                            @endphp
                                            <input type="number" step="0.0001" name="optidrive_modifiers[category_a]" class="form-control section-input" data-section="optidrive_modifiers" value="{{ $categoryAValue }}" required>
                                            <small class="form-text text-muted">Surcharge: 0%</small>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Category B (0.6 to &lt;0.8) <span class="text-danger">*</span></label>
                                            @php
                                                $categoryBValue = old('optidrive_modifiers.category_b', 
                                                    is_array($ppkRatingConfig->optidrive_modifiers['category_b'] ?? null) 
                                                        ? ($ppkRatingConfig->optidrive_modifiers['category_b']['value'] ?? 0.20)
                                                        : ($ppkRatingConfig->optidrive_modifiers['category_b'] ?? 0.20)
                                                );
                                            @endphp
                                            <input type="number" step="0.0001" name="optidrive_modifiers[category_b]" class="form-control section-input" data-section="optidrive_modifiers" value="{{ $categoryBValue }}" required>
                                            <small class="form-text text-muted">Surcharge: 20%</small>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Category C (0.4 to &lt;0.6) <span class="text-danger">*</span></label>
                                            @php
                                                $categoryCValue = old('optidrive_modifiers.category_c', 
                                                    is_array($ppkRatingConfig->optidrive_modifiers['category_c'] ?? null) 
                                                        ? ($ppkRatingConfig->optidrive_modifiers['category_c']['value'] ?? 0.40)
                                                        : ($ppkRatingConfig->optidrive_modifiers['category_c'] ?? 0.40)
                                                );
                                            @endphp
                                            <input type="number" step="0.0001" name="optidrive_modifiers[category_c]" class="form-control section-input" data-section="optidrive_modifiers" value="{{ $categoryCValue }}" required>
                                            <small class="form-text text-muted">Surcharge: 40%</small>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Category D (&lt;0.4) <span class="text-danger">*</span></label>
                                            @php
                                                $categoryDValue = old('optidrive_modifiers.category_d', 
                                                    is_array($ppkRatingConfig->optidrive_modifiers['category_d'] ?? null) 
                                                        ? ($ppkRatingConfig->optidrive_modifiers['category_d']['value'] ?? 0.60)
                                                        : ($ppkRatingConfig->optidrive_modifiers['category_d'] ?? 0.60)
                                                );
                                            @endphp
                                            <input type="number" step="0.0001" name="optidrive_modifiers[category_d]" class="form-control section-input" data-section="optidrive_modifiers" value="{{ $categoryDValue }}" required>
                                            <small class="form-text text-muted">Surcharge: 60%</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Driver Age Modifiers -->
                        <div class="kt-portlet" data-section="driver_age_modifiers">
                            <div class="kt-portlet__head">
                                <div class="kt-portlet__head-label">
                                    <h3 class="kt-portlet__head-title">Driver Age Modifiers</h3>
                                </div>
                                <div class="kt-portlet__head-toolbar">
                                    <label class="form-check form-switch form-check-custom form-check-solid">
                                        <input type="checkbox" name="section_enabled[driver_age_modifiers]" value="1" class="form-check-input section-enable-toggle" data-section="driver_age_modifiers" {{ old('section_enabled.driver_age_modifiers', $ppkRatingConfig->section_enabled['driver_age_modifiers'] ?? true) ? 'checked' : '' }}>
                                        <span class="form-check-label fw-semibold">Enable Section</span>
                                    </label>
                                </div>
                            </div>
                            <div class="kt-portlet__body section-body" data-section="driver_age_modifiers">
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Young (&lt;25)</label>
                                            <input type="number" step="0.0001" name="driver_age_modifiers[young]" class="form-control section-input" data-section="driver_age_modifiers" value="{{ old('driver_age_modifiers.young', $ppkRatingConfig->driver_age_modifiers['young'] ?? 0.10) }}">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Adult (25-64)</label>
                                            <input type="number" step="0.0001" name="driver_age_modifiers[adult]" class="form-control section-input" data-section="driver_age_modifiers" value="{{ old('driver_age_modifiers.adult', $ppkRatingConfig->driver_age_modifiers['adult'] ?? 0.0) }}">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Senior (65-74)</label>
                                            <input type="number" step="0.0001" name="driver_age_modifiers[senior]" class="form-control section-input" data-section="driver_age_modifiers" value="{{ old('driver_age_modifiers.senior', $ppkRatingConfig->driver_age_modifiers['senior'] ?? 0.05) }}">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Elderly (≥75)</label>
                                            <input type="number" step="0.0001" name="driver_age_modifiers[elderly]" class="form-control section-input" data-section="driver_age_modifiers" value="{{ old('driver_age_modifiers.elderly', $ppkRatingConfig->driver_age_modifiers['elderly'] ?? 0.15) }}">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Car Age Modifiers -->
                        <div class="kt-portlet" data-section="car_age_modifiers">
                            <div class="kt-portlet__head">
                                <div class="kt-portlet__head-label">
                                    <h3 class="kt-portlet__head-title">Car Age Modifiers</h3>
                                </div>
                                <div class="kt-portlet__head-toolbar">
                                    <label class="form-check form-switch form-check-custom form-check-solid">
                                        <input type="checkbox" name="section_enabled[car_age_modifiers]" value="1" class="form-check-input section-enable-toggle" data-section="car_age_modifiers" {{ old('section_enabled.car_age_modifiers', $ppkRatingConfig->section_enabled['car_age_modifiers'] ?? true) ? 'checked' : '' }}>
                                        <span class="form-check-label fw-semibold">Enable Section</span>
                                    </label>
                                </div>
                            </div>
                            <div class="kt-portlet__body section-body" data-section="car_age_modifiers">
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>New (0-5 years)</label>
                                            <input type="number" step="0.0001" name="car_age_modifiers[new]" class="form-control section-input" data-section="car_age_modifiers" value="{{ old('car_age_modifiers.new', $ppkRatingConfig->car_age_modifiers['new'] ?? 0.0) }}">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Mid (6-10 years)</label>
                                            <input type="number" step="0.0001" name="car_age_modifiers[mid]" class="form-control section-input" data-section="car_age_modifiers" value="{{ old('car_age_modifiers.mid', $ppkRatingConfig->car_age_modifiers['mid'] ?? 0.03) }}">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Old (11-15 years)</label>
                                            <input type="number" step="0.0001" name="car_age_modifiers[old]" class="form-control section-input" data-section="car_age_modifiers" value="{{ old('car_age_modifiers.old', $ppkRatingConfig->car_age_modifiers['old'] ?? 0.06) }}">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Very Old (16+ years)</label>
                                            <input type="number" step="0.0001" name="car_age_modifiers[very_old]" class="form-control section-input" data-section="car_age_modifiers" value="{{ old('car_age_modifiers.very_old', $ppkRatingConfig->car_age_modifiers['very_old'] ?? 0.08) }}">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Vehicle Type Modifiers -->
                        <div class="kt-portlet" data-section="vehicle_type_modifiers">
                            <div class="kt-portlet__head">
                                <div class="kt-portlet__head-label">
                                    <h3 class="kt-portlet__head-title">Vehicle Type Modifiers</h3>
                                </div>
                                <div class="kt-portlet__head-toolbar">
                                    <label class="form-check form-switch form-check-custom form-check-solid">
                                        <input type="checkbox" name="section_enabled[vehicle_type_modifiers]" value="1" class="form-check-input section-enable-toggle" data-section="vehicle_type_modifiers" {{ old('section_enabled.vehicle_type_modifiers', $ppkRatingConfig->section_enabled['vehicle_type_modifiers'] ?? true) ? 'checked' : '' }}>
                                        <span class="form-check-label fw-semibold">Enable Section</span>
                                    </label>
                                </div>
                            </div>
                            <div class="kt-portlet__body section-body" data-section="vehicle_type_modifiers">
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Sedan</label>
                                            <input type="number" step="0.0001" name="vehicle_type_modifiers[sedan]" class="form-control section-input" data-section="vehicle_type_modifiers" value="{{ old('vehicle_type_modifiers.sedan', $ppkRatingConfig->vehicle_type_modifiers['sedan'] ?? 0.0) }}">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>SUV</label>
                                            <input type="number" step="0.0001" name="vehicle_type_modifiers[suv]" class="form-control section-input" data-section="vehicle_type_modifiers" value="{{ old('vehicle_type_modifiers.suv', $ppkRatingConfig->vehicle_type_modifiers['suv'] ?? 0.05) }}">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Pickup</label>
                                            <input type="number" step="0.0001" name="vehicle_type_modifiers[pickup]" class="form-control section-input" data-section="vehicle_type_modifiers" value="{{ old('vehicle_type_modifiers.pickup', $ppkRatingConfig->vehicle_type_modifiers['pickup'] ?? 0.07) }}">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>EV</label>
                                            <input type="number" step="0.0001" name="vehicle_type_modifiers[ev]" class="form-control section-input" data-section="vehicle_type_modifiers" value="{{ old('vehicle_type_modifiers.ev', $ppkRatingConfig->vehicle_type_modifiers['ev'] ?? -0.03) }}">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Driver Risk Modifiers -->
                        <div class="kt-portlet" data-section="driver_risk_modifiers">
                            <div class="kt-portlet__head">
                                <div class="kt-portlet__head-label">
                                    <h3 class="kt-portlet__head-title">Driver Risk Tier Modifiers</h3>
                                </div>
                                <div class="kt-portlet__head-toolbar">
                                    <label class="form-check form-switch form-check-custom form-check-solid">
                                        <input type="checkbox" name="section_enabled[driver_risk_modifiers]" value="1" class="form-check-input section-enable-toggle" data-section="driver_risk_modifiers" {{ old('section_enabled.driver_risk_modifiers', $ppkRatingConfig->section_enabled['driver_risk_modifiers'] ?? true) ? 'checked' : '' }}>
                                        <span class="form-check-label fw-semibold">Enable Section</span>
                                    </label>
                                </div>
                            </div>
                            <div class="kt-portlet__body section-body" data-section="driver_risk_modifiers">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>Low</label>
                                            <input type="number" step="0.0001" name="driver_risk_modifiers[low]" class="form-control section-input" data-section="driver_risk_modifiers" value="{{ old('driver_risk_modifiers.low', $ppkRatingConfig->driver_risk_modifiers['low'] ?? 0.0) }}">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>Medium</label>
                                            <input type="number" step="0.0001" name="driver_risk_modifiers[medium]" class="form-control section-input" data-section="driver_risk_modifiers" value="{{ old('driver_risk_modifiers.medium', $ppkRatingConfig->driver_risk_modifiers['medium'] ?? 0.05) }}">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>High</label>
                                            <input type="number" step="0.0001" name="driver_risk_modifiers[high]" class="form-control section-input" data-section="driver_risk_modifiers" value="{{ old('driver_risk_modifiers.high', $ppkRatingConfig->driver_risk_modifiers['high'] ?? 0.10) }}">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Territory Modifiers -->
                        <div class="kt-portlet" data-section="territory_modifiers">
                            <div class="kt-portlet__head">
                                <div class="kt-portlet__head-label">
                                    <h3 class="kt-portlet__head-title">Territory Modifiers</h3>
                                </div>
                                <div class="kt-portlet__head-toolbar">
                                    <label class="form-check form-switch form-check-custom form-check-solid">
                                        <input type="checkbox" name="section_enabled[territory_modifiers]" value="1" class="form-check-input section-enable-toggle" data-section="territory_modifiers" {{ old('section_enabled.territory_modifiers', $ppkRatingConfig->section_enabled['territory_modifiers'] ?? true) ? 'checked' : '' }}>
                                        <span class="form-check-label fw-semibold">Enable Section</span>
                                    </label>
                                </div>
                            </div>
                            <div class="kt-portlet__body section-body" data-section="territory_modifiers">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>Urban</label>
                                            <input type="number" step="0.0001" name="territory_modifiers[urban]" class="form-control section-input" data-section="territory_modifiers" value="{{ old('territory_modifiers.urban', $ppkRatingConfig->territory_modifiers['urban'] ?? 0.05) }}">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>Rural</label>
                                            <input type="number" step="0.0001" name="territory_modifiers[rural]" class="form-control section-input" data-section="territory_modifiers" value="{{ old('territory_modifiers.rural', $ppkRatingConfig->territory_modifiers['rural'] ?? 0.0) }}">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>Off-road</label>
                                            <input type="number" step="0.0001" name="territory_modifiers[offroad]" class="form-control section-input" data-section="territory_modifiers" value="{{ old('territory_modifiers.offroad', $ppkRatingConfig->territory_modifiers['offroad'] ?? 0.10) }}">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Trip Type Modifiers -->
                        <div class="kt-portlet" data-section="trip_type_modifiers">
                            <div class="kt-portlet__head">
                                <div class="kt-portlet__head-label">
                                    <h3 class="kt-portlet__head-title">Trip Type Modifiers</h3>
                                </div>
                                <div class="kt-portlet__head-toolbar">
                                    <label class="form-check form-switch form-check-custom form-check-solid">
                                        <input type="checkbox" name="section_enabled[trip_type_modifiers]" value="1" class="form-check-input section-enable-toggle" data-section="trip_type_modifiers" {{ old('section_enabled.trip_type_modifiers', $ppkRatingConfig->section_enabled['trip_type_modifiers'] ?? true) ? 'checked' : '' }}>
                                        <span class="form-check-label fw-semibold">Enable Section</span>
                                    </label>
                                </div>
                            </div>
                            <div class="kt-portlet__body section-body" data-section="trip_type_modifiers">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>Commute</label>
                                            <input type="number" step="0.0001" name="trip_type_modifiers[commute]" class="form-control section-input" data-section="trip_type_modifiers" value="{{ old('trip_type_modifiers.commute', $ppkRatingConfig->trip_type_modifiers['commute'] ?? 0.0) }}">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>Long Trip</label>
                                            <input type="number" step="0.0001" name="trip_type_modifiers[long_trip]" class="form-control section-input" data-section="trip_type_modifiers" value="{{ old('trip_type_modifiers.long_trip', $ppkRatingConfig->trip_type_modifiers['long_trip'] ?? 0.02) }}">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>Off-road</label>
                                            <input type="number" step="0.0001" name="trip_type_modifiers[offroad]" class="form-control section-input" data-section="trip_type_modifiers" value="{{ old('trip_type_modifiers.offroad', $ppkRatingConfig->trip_type_modifiers['offroad'] ?? 0.08) }}">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Usage Frequency Modifiers -->
                        <div class="kt-portlet" data-section="usage_frequency_modifiers">
                            <div class="kt-portlet__head">
                                <div class="kt-portlet__head-label">
                                    <h3 class="kt-portlet__head-title">Usage Frequency Modifiers (Monthly KM)</h3>
                                </div>
                                <div class="kt-portlet__head-toolbar">
                                    <label class="form-check form-switch form-check-custom form-check-solid">
                                        <input type="checkbox" name="section_enabled[usage_frequency_modifiers]" value="1" class="form-check-input section-enable-toggle" data-section="usage_frequency_modifiers" {{ old('section_enabled.usage_frequency_modifiers', $ppkRatingConfig->section_enabled['usage_frequency_modifiers'] ?? true) ? 'checked' : '' }}>
                                        <span class="form-check-label fw-semibold">Enable Section</span>
                                    </label>
                                </div>
                            </div>
                            <div class="kt-portlet__body section-body" data-section="usage_frequency_modifiers">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>&lt;500 km</label>
                                            <input type="number" step="0.0001" name="usage_frequency_modifiers[lt_500]" class="form-control section-input" data-section="usage_frequency_modifiers" value="{{ old('usage_frequency_modifiers.lt_500', $ppkRatingConfig->usage_frequency_modifiers['lt_500'] ?? -0.05) }}">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>500-1000 km</label>
                                            <input type="number" step="0.0001" name="usage_frequency_modifiers[btw_500_1000]" class="form-control section-input" data-section="usage_frequency_modifiers" value="{{ old('usage_frequency_modifiers.btw_500_1000', $ppkRatingConfig->usage_frequency_modifiers['btw_500_1000'] ?? 0.0) }}">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>&gt;1000 km</label>
                                            <input type="number" step="0.0001" name="usage_frequency_modifiers[gt_1000]" class="form-control section-input" data-section="usage_frequency_modifiers" value="{{ old('usage_frequency_modifiers.gt_1000', $ppkRatingConfig->usage_frequency_modifiers['gt_1000'] ?? 0.05) }}">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Policy Tenure Modifiers -->
                        <div class="kt-portlet" data-section="policy_tenure_modifiers">
                            <div class="kt-portlet__head">
                                <div class="kt-portlet__head-label">
                                    <h3 class="kt-portlet__head-title">Policy Tenure Modifiers</h3>
                                </div>
                                <div class="kt-portlet__head-toolbar">
                                    <label class="form-check form-switch form-check-custom form-check-solid">
                                        <input type="checkbox" name="section_enabled[policy_tenure_modifiers]" value="1" class="form-check-input section-enable-toggle" data-section="policy_tenure_modifiers" {{ old('section_enabled.policy_tenure_modifiers', $ppkRatingConfig->section_enabled['policy_tenure_modifiers'] ?? true) ? 'checked' : '' }}>
                                        <span class="form-check-label fw-semibold">Enable Section</span>
                                    </label>
                                </div>
                            </div>
                            <div class="kt-portlet__body section-body" data-section="policy_tenure_modifiers">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>&lt;2 years</label>
                                            <input type="number" step="0.0001" name="policy_tenure_modifiers[lt_2y]" class="form-control section-input" data-section="policy_tenure_modifiers" value="{{ old('policy_tenure_modifiers.lt_2y', $ppkRatingConfig->policy_tenure_modifiers['lt_2y'] ?? 0.0) }}">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>&gt;2 years</label>
                                            <input type="number" step="0.0001" name="policy_tenure_modifiers[gt_2y]" class="form-control section-input" data-section="policy_tenure_modifiers" value="{{ old('policy_tenure_modifiers.gt_2y', $ppkRatingConfig->policy_tenure_modifiers['gt_2y'] ?? -0.03) }}">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>&gt;5 years</label>
                                            <input type="number" step="0.0001" name="policy_tenure_modifiers[gt_5y]" class="form-control section-input" data-section="policy_tenure_modifiers" value="{{ old('policy_tenure_modifiers.gt_5y', $ppkRatingConfig->policy_tenure_modifiers['gt_5y'] ?? -0.05) }}">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Claims History Modifiers -->
                        <div class="kt-portlet" data-section="claims_history_modifiers">
                            <div class="kt-portlet__head">
                                <div class="kt-portlet__head-label">
                                    <h3 class="kt-portlet__head-title">Claims History Modifiers</h3>
                                </div>
                                <div class="kt-portlet__head-toolbar">
                                    <label class="form-check form-switch form-check-custom form-check-solid">
                                        <input type="checkbox" name="section_enabled[claims_history_modifiers]" value="1" class="form-check-input section-enable-toggle" data-section="claims_history_modifiers" {{ old('section_enabled.claims_history_modifiers', $ppkRatingConfig->section_enabled['claims_history_modifiers'] ?? true) ? 'checked' : '' }}>
                                        <span class="form-check-label fw-semibold">Enable Section</span>
                                    </label>
                                </div>
                            </div>
                            <div class="kt-portlet__body section-body" data-section="claims_history_modifiers">
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>0 claims</label>
                                            <input type="number" step="0.0001" name="claims_history_modifiers[c0]" class="form-control section-input" data-section="claims_history_modifiers" value="{{ old('claims_history_modifiers.c0', $ppkRatingConfig->claims_history_modifiers['c0'] ?? 0.0) }}">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>1 claim</label>
                                            <input type="number" step="0.0001" name="claims_history_modifiers[c1]" class="form-control section-input" data-section="claims_history_modifiers" value="{{ old('claims_history_modifiers.c1', $ppkRatingConfig->claims_history_modifiers['c1'] ?? 0.03) }}">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>2 claims</label>
                                            <input type="number" step="0.0001" name="claims_history_modifiers[c2]" class="form-control section-input" data-section="claims_history_modifiers" value="{{ old('claims_history_modifiers.c2', $ppkRatingConfig->claims_history_modifiers['c2'] ?? 0.06) }}">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>≥3 claims</label>
                                            <input type="number" step="0.0001" name="claims_history_modifiers[c3_plus]" class="form-control section-input" data-section="claims_history_modifiers" value="{{ old('claims_history_modifiers.c3_plus', $ppkRatingConfig->claims_history_modifiers['c3_plus'] ?? 0.10) }}">
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
                                        <input type="checkbox" name="gender_enabled" value="1" {{ old('gender_enabled', $ppkRatingConfig->gender_enabled) ? 'checked' : '' }}>
                                        Enable Gender Factor
                                    </label>
                                </div>
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>Male</label>
                                            <input type="number" step="0.0001" name="gender_modifiers[M]" class="form-control" value="{{ old('gender_modifiers.M', $ppkRatingConfig->gender_modifiers['M'] ?? 0.02) }}">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>Female</label>
                                            <input type="number" step="0.0001" name="gender_modifiers[F]" class="form-control" value="{{ old('gender_modifiers.F', $ppkRatingConfig->gender_modifiers['F'] ?? 0.0) }}">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>Other</label>
                                            <input type="number" step="0.0001" name="gender_modifiers[X]" class="form-control" value="{{ old('gender_modifiers.X', $ppkRatingConfig->gender_modifiers['X'] ?? 0.0) }}">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Event Penalty Rates -->
                        <div class="kt-portlet" data-section="event_penalty_rates">
                            <div class="kt-portlet__head">
                                <div class="kt-portlet__head-label">
                                    <h3 class="kt-portlet__head-title">Event Penalty Rates</h3>
                                </div>
                                <div class="kt-portlet__head-toolbar">
                                    <label class="form-check form-switch form-check-custom form-check-solid">
                                        <input type="checkbox" name="section_enabled[event_penalty_rates]" value="1" class="form-check-input section-enable-toggle" data-section="event_penalty_rates" {{ old('section_enabled.event_penalty_rates', $ppkRatingConfig->section_enabled['event_penalty_rates'] ?? true) ? 'checked' : '' }}>
                                        <span class="form-check-label fw-semibold">Enable Section</span>
                                    </label>
                                </div>
                            </div>
                            <div class="kt-portlet__body section-body" data-section="event_penalty_rates">
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Speeding Penalty Per Event (BWP) <span class="text-danger">*</span></label>
                                            <input type="number" step="0.01" name="speeding_penalty_per_event" class="form-control section-input" data-section="event_penalty_rates" value="{{ old('speeding_penalty_per_event', $ppkRatingConfig->speeding_penalty_per_event) }}" required>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Harsh Acceleration Rate (BWP/km) <span class="text-danger">*</span></label>
                                            <input type="number" step="0.01" name="harsh_acceleration_rate" class="form-control section-input" data-section="event_penalty_rates" value="{{ old('harsh_acceleration_rate', $ppkRatingConfig->harsh_acceleration_rate) }}" required>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Idle Time Rate (BWP/km) <span class="text-danger">*</span></label>
                                            <input type="number" step="0.01" name="idle_time_rate" class="form-control section-input" data-section="event_penalty_rates" value="{{ old('idle_time_rate', $ppkRatingConfig->idle_time_rate) }}" required>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>High Revving Rate (BWP/km) <span class="text-danger">*</span></label>
                                            <input type="number" step="0.01" name="high_revving_rate" class="form-control section-input" data-section="event_penalty_rates" value="{{ old('high_revving_rate', $ppkRatingConfig->high_revving_rate) }}" required>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Weather Severity Modifiers -->
                        <div class="kt-portlet" data-section="weather_severity_modifiers">
                            <div class="kt-portlet__head">
                                <div class="kt-portlet__head-label">
                                    <h3 class="kt-portlet__head-title">Weather Severity Modifiers</h3>
                                </div>
                                <div class="kt-portlet__head-toolbar">
                                    <label class="form-check form-switch form-check-custom form-check-solid">
                                        <input type="checkbox" name="section_enabled[weather_severity_modifiers]" value="1" class="form-check-input section-enable-toggle" data-section="weather_severity_modifiers" {{ old('section_enabled.weather_severity_modifiers', $ppkRatingConfig->section_enabled['weather_severity_modifiers'] ?? true) ? 'checked' : '' }}>
                                        <span class="form-check-label fw-semibold">Enable Section</span>
                                    </label>
                                </div>
                            </div>
                            <div class="kt-portlet__body section-body" data-section="weather_severity_modifiers">
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Drizzle</label>
                                            <input type="number" step="0.0001" name="weather_severity_modifiers[drizzle]" class="form-control section-input" data-section="weather_severity_modifiers" value="{{ old('weather_severity_modifiers.drizzle', $ppkRatingConfig->weather_severity_modifiers['drizzle'] ?? 0.05) }}">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Rain</label>
                                            <input type="number" step="0.0001" name="weather_severity_modifiers[rain]" class="form-control section-input" data-section="weather_severity_modifiers" value="{{ old('weather_severity_modifiers.rain', $ppkRatingConfig->weather_severity_modifiers['rain'] ?? 0.10) }}">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Heavy Rain</label>
                                            <input type="number" step="0.0001" name="weather_severity_modifiers[heavy_rain]" class="form-control section-input" data-section="weather_severity_modifiers" value="{{ old('weather_severity_modifiers.heavy_rain', $ppkRatingConfig->weather_severity_modifiers['heavy_rain'] ?? 0.15) }}">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Thunderstorm</label>
                                            <input type="number" step="0.0001" name="weather_severity_modifiers[thunderstorm]" class="form-control section-input" data-section="weather_severity_modifiers" value="{{ old('weather_severity_modifiers.thunderstorm', $ppkRatingConfig->weather_severity_modifiers['thunderstorm'] ?? 0.15) }}">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Night Segment Modifiers -->
                        <div class="kt-portlet" data-section="night_segment_modifiers">
                            <div class="kt-portlet__head">
                                <div class="kt-portlet__head-label">
                                    <h3 class="kt-portlet__head-title">Night Segment Modifiers</h3>
                                </div>
                                <div class="kt-portlet__head-toolbar">
                                    <label class="form-check form-switch form-check-custom form-check-solid">
                                        <input type="checkbox" name="section_enabled[night_segment_modifiers]" value="1" class="form-check-input section-enable-toggle" data-section="night_segment_modifiers" {{ old('section_enabled.night_segment_modifiers', $ppkRatingConfig->section_enabled['night_segment_modifiers'] ?? true) ? 'checked' : '' }}>
                                        <span class="form-check-label fw-semibold">Enable Section</span>
                                    </label>
                                </div>
                            </div>
                            <div class="kt-portlet__body section-body" data-section="night_segment_modifiers">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Evening (19:00-22:00)</label>
                                            <input type="number" step="0.0001" name="night_segment_modifiers[evening]" class="form-control section-input" data-section="night_segment_modifiers" value="{{ old('night_segment_modifiers.evening', $ppkRatingConfig->night_segment_modifiers['evening'] ?? 0.05) }}">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Late Night (22:00-05:00)</label>
                                            <input type="number" step="0.0001" name="night_segment_modifiers[late_night]" class="form-control section-input" data-section="night_segment_modifiers" value="{{ old('night_segment_modifiers.late_night', $ppkRatingConfig->night_segment_modifiers['late_night'] ?? 0.10) }}">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Other Modifiers -->
                        <div class="kt-portlet" data-section="other_modifiers">
                            <div class="kt-portlet__head">
                                <div class="kt-portlet__head-label">
                                    <h3 class="kt-portlet__head-title">Other Modifiers</h3>
                                </div>
                                <div class="kt-portlet__head-toolbar">
                                    <label class="form-check form-switch form-check-custom form-check-solid">
                                        <input type="checkbox" name="section_enabled[other_modifiers]" value="1" class="form-check-input section-enable-toggle" data-section="other_modifiers" {{ old('section_enabled.other_modifiers', $ppkRatingConfig->section_enabled['other_modifiers'] ?? true) ? 'checked' : '' }}>
                                        <span class="form-check-label fw-semibold">Enable Section</span>
                                    </label>
                                </div>
                            </div>
                            <div class="kt-portlet__body section-body" data-section="other_modifiers">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>Congestion Modifier <span class="text-danger">*</span></label>
                                            <input type="number" step="0.0001" name="congestion_modifier" class="form-control section-input" data-section="other_modifiers" value="{{ old('congestion_modifier', $ppkRatingConfig->congestion_modifier) }}" required>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>Seasonal Modifier <span class="text-danger">*</span></label>
                                            <input type="number" step="0.0001" name="seasonal_modifier" class="form-control section-input" data-section="other_modifiers" value="{{ old('seasonal_modifier', $ppkRatingConfig->seasonal_modifier) }}" required>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>ADAS Discount <span class="text-danger">*</span></label>
                                            <input type="number" step="0.0001" name="adas_discount" class="form-control section-input" data-section="other_modifiers" value="{{ old('adas_discount', $ppkRatingConfig->adas_discount) }}" required>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Roadworthiness Discount <span class="text-danger">*</span></label>
                                            <input type="number" step="0.0001" name="roadworthiness_discount" class="form-control section-input" data-section="other_modifiers" value="{{ old('roadworthiness_discount', $ppkRatingConfig->roadworthiness_discount) }}" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Safe Driver Cashback <span class="text-danger">*</span></label>
                                            <input type="number" step="0.0001" name="safe_driver_cashback" class="form-control section-input" data-section="other_modifiers" value="{{ old('safe_driver_cashback', $ppkRatingConfig->safe_driver_cashback) }}" required>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            // Handle enable/disable toggle for all sections
                            document.querySelectorAll('.section-enable-toggle').forEach(function(toggle) {
                                const section = toggle.getAttribute('data-section');
                                
                                function updateSectionState() {
                                    const isEnabled = toggle.checked;
                                    const sectionBody = document.querySelector('.section-body[data-section="' + section + '"]');
                                    const sectionInputs = document.querySelectorAll('.section-input[data-section="' + section + '"]');
                                    
                                    if (sectionBody) {
                                        if (isEnabled) {
                                            sectionBody.style.opacity = '1';
                                        } else {
                                            sectionBody.style.opacity = '0.5';
                                        }
                                    }
                                    
                                    sectionInputs.forEach(function(input) {
                                        input.disabled = !isEnabled;
                                        if (!isEnabled) {
                                            input.style.opacity = '0.5';
                                        } else {
                                            input.style.opacity = '1';
                                        }
                                    });
                                }
                                
                                toggle.addEventListener('change', updateSectionState);
                                
                                // Trigger on page load
                                updateSectionState();
                            });
                        });
                        </script>

                        <!-- Form Actions -->
                        <div class="kt-portlet">
                            <div class="kt-portlet__foot">
                                <div class="kt-form__actions">
                                    <button type="submit" class="btn btn-primary">Update Configuration</button>
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

