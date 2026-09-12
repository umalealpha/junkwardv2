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
                        <h3 class="kt-subheader__title">View PPK Rating Configuration</h3>
                        <span class="kt-subheader__separator kt-hidden"></span>
                        <div class="kt-subheader__breadcrumbs">
                            <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                            <span class="kt-subheader__breadcrumbs-separator"></span>
                            <a href="{{ Route('admin-dashboard') }}" class="kt-subheader__breadcrumbs-link">Dashboard</a>
                            <span class="kt-subheader__breadcrumbs-separator"></span>
                            <a href="{{ route('ppk-rating-config.index') }}" class="kt-subheader__breadcrumbs-link">PPK Rating Config</a>
                            <span class="kt-subheader__breadcrumbs-separator"></span>
                            <a class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">View</a>
                        </div>
                    </div>
                    <div class="kt-subheader__toolbar">
                        <div class="kt-subheader__wrapper">
                            <a href="{{ route('ppk-rating-config.edit', $ppkRatingConfig->id) }}" class="btn btn-label-brand btn-bold">
                                <i class="la la-edit"></i> Edit Configuration
                            </a>
                        </div>
                    </div>
                </div>

                <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
                    <!-- Basic Configuration -->
                    <div class="kt-portlet">
                        <div class="kt-portlet__head">
                            <div class="kt-portlet__head-label">
                                <h3 class="kt-portlet__head-title">Basic Configuration</h3>
                            </div>
                            <div class="kt-portlet__head-toolbar">
                                @if($ppkRatingConfig->is_active)
                                    <span class="kt-badge kt-badge--success kt-badge--inline kt-badge--pill">Active</span>
                                @else
                                    <span class="kt-badge kt-badge--secondary kt-badge--inline kt-badge--pill">Inactive</span>
                                @endif
                            </div>
                        </div>
                        <div class="kt-portlet__body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="font-weight-bold">Configuration Name:</label>
                                        <p>{{ $ppkRatingConfig->config_name }}</p>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="font-weight-bold">Created:</label>
                                        <p>{{ $ppkRatingConfig->created_at->format('Y-m-d H:i:s') }}</p>
                                    </div>
                                </div>
                            </div>
                            @if($ppkRatingConfig->description)
                            <div class="form-group">
                                <label class="font-weight-bold">Description:</label>
                                <p>{{ $ppkRatingConfig->description }}</p>
                            </div>
                            @endif
                        </div>
                    </div>

                    <!-- Distance Range Configuration -->
                    <div class="kt-portlet">
                        <div class="kt-portlet__head">
                            <div class="kt-portlet__head-label">
                                <h3 class="kt-portlet__head-title">Distance Range Configuration</h3>
                            </div>
                            <div class="kt-portlet__head-toolbar">
                                @php
                                    $isEnabled = $ppkRatingConfig->section_enabled['distance_range_config'] ?? true;
                                @endphp
                                @if($isEnabled)
                                    <span class="kt-badge kt-badge--success kt-badge--inline kt-badge--pill">Section Enabled</span>
                                @else
                                    <span class="kt-badge kt-badge--secondary kt-badge--inline kt-badge--pill">Section Disabled</span>
                                @endif
                            </div>
                        </div>
                        <div class="kt-portlet__body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="font-weight-bold">Minimum Distance Range:</label>
                                        <p>{{ number_format($ppkRatingConfig->distance_range_min ?? 200, 0) }} km</p>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="font-weight-bold">Maximum Distance Range:</label>
                                        <p>{{ number_format($ppkRatingConfig->distance_range_max ?? 1000, 0) }} km</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Caps -->
                    <div class="kt-portlet">
                        <div class="kt-portlet__head">
                            <div class="kt-portlet__head-label">
                                <h3 class="kt-portlet__head-title">Caps Configuration</h3>
                            </div>
                            <div class="kt-portlet__head-toolbar">
                                @php
                                    $isEnabled = $ppkRatingConfig->section_enabled['caps_config'] ?? true;
                                @endphp
                                @if($isEnabled)
                                    <span class="kt-badge kt-badge--success kt-badge--inline kt-badge--pill">Section Enabled</span>
                                @else
                                    <span class="kt-badge kt-badge--secondary kt-badge--inline kt-badge--pill">Section Disabled</span>
                                @endif
                            </div>
                        </div>
                        <div class="kt-portlet__body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="font-weight-bold">Per Trip Cap:</label>
                                        <p>{{ number_format($ppkRatingConfig->per_trip_cap_min, 2) }} - {{ number_format($ppkRatingConfig->per_trip_cap_max, 2) }} BWP</p>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="font-weight-bold">Monthly Cap:</label>
                                        <p>{{ number_format($ppkRatingConfig->monthly_cap_min, 2) }} - {{ number_format($ppkRatingConfig->monthly_cap_max, 2) }} BWP</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Basic Risk Modifiers -->
                    <div class="kt-portlet">
                        <div class="kt-portlet__head">
                            <div class="kt-portlet__head-label">
                                <h3 class="kt-portlet__head-title">Basic Risk Modifiers</h3>
                            </div>
                            <div class="kt-portlet__head-toolbar">
                                @php
                                    $isEnabled = $ppkRatingConfig->section_enabled['basic_risk_modifiers'] ?? true;
                                @endphp
                                @if($isEnabled)
                                    <span class="kt-badge kt-badge--success kt-badge--inline kt-badge--pill">Section Enabled</span>
                                @else
                                    <span class="kt-badge kt-badge--secondary kt-badge--inline kt-badge--pill">Section Disabled</span>
                                @endif
                            </div>
                        </div>
                        <div class="kt-portlet__body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="font-weight-bold">Night Modifier:</label>
                                        <p>{{ number_format($ppkRatingConfig->night_modifier * 100, 2) }}%</p>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="font-weight-bold">Rain Modifier:</label>
                                        <p>{{ number_format($ppkRatingConfig->rain_modifier * 100, 2) }}%</p>
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
                            <div class="kt-portlet__head-toolbar">
                                @php
                                    $isEnabled = $ppkRatingConfig->section_enabled['optidrive_modifiers'] ?? true;
                                @endphp
                                @if($isEnabled)
                                    <span class="kt-badge kt-badge--success kt-badge--inline kt-badge--pill">Section Enabled</span>
                                @else
                                    <span class="kt-badge kt-badge--secondary kt-badge--inline kt-badge--pill">Section Disabled</span>
                                @endif
                            </div>
                        </div>
                        <div class="kt-portlet__body">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Category</th>
                                        <th>Score Range</th>
                                        <th>Surcharge</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php
                                        $categories = [
                                            'category_a' => ['name' => 'Category A', 'range' => '0.8 to 1.0'],
                                            'category_b' => ['name' => 'Category B', 'range' => '0.6 to <0.8'],
                                            'category_c' => ['name' => 'Category C', 'range' => '0.4 to <0.6'],
                                            'category_d' => ['name' => 'Category D', 'range' => '<0.4']
                                        ];
                                    @endphp
                                    @foreach($categories as $key => $category)
                                        @php
                                            $modifier = $ppkRatingConfig->optidrive_modifiers[$key] ?? null;
                                            $isEnabled = true;
                                            $value = 0;
                                            
                                            if ($modifier !== null) {
                                                if (is_array($modifier)) {
                                                    $isEnabled = $modifier['enabled'] ?? true;
                                                    $value = $modifier['value'] ?? 0;
                                                } else {
                                                    $value = $modifier;
                                                }
                                            }
                                        @endphp
                                        @if($modifier !== null)
                                        <tr>
                                            <td><strong>{{ $category['name'] }}</strong></td>
                                            <td>{{ $category['range'] }}</td>
                                            <td>{{ number_format($value * 100, 2) }}%</td>
                                            <td>
                                                @if($isEnabled)
                                                    <span class="kt-badge kt-badge--success kt-badge--inline kt-badge--pill">Enabled</span>
                                                @else
                                                    <span class="kt-badge kt-badge--secondary kt-badge--inline kt-badge--pill">Disabled</span>
                                                @endif
                                            </td>
                                        </tr>
                                        @endif
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Driver Age Modifiers -->
                    <div class="kt-portlet">
                        <div class="kt-portlet__head">
                            <div class="kt-portlet__head-label">
                                <h3 class="kt-portlet__head-title">Driver Age Modifiers</h3>
                            </div>
                            <div class="kt-portlet__head-toolbar">
                                @php
                                    $isEnabled = $ppkRatingConfig->section_enabled['driver_age_modifiers'] ?? true;
                                @endphp
                                @if($isEnabled)
                                    <span class="kt-badge kt-badge--success kt-badge--inline kt-badge--pill">Section Enabled</span>
                                @else
                                    <span class="kt-badge kt-badge--secondary kt-badge--inline kt-badge--pill">Section Disabled</span>
                                @endif
                            </div>
                        </div>
                        <div class="kt-portlet__body">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Category</th>
                                        <th>Modifier</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($ppkRatingConfig->driver_age_modifiers as $key => $value)
                                    <tr>
                                        <td>{{ ucfirst($key) }}</td>
                                        <td>{{ number_format($value * 100, 2) }}%</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Car Age Modifiers -->
                    <div class="kt-portlet">
                        <div class="kt-portlet__head">
                            <div class="kt-portlet__head-label">
                                <h3 class="kt-portlet__head-title">Car Age Modifiers</h3>
                            </div>
                            <div class="kt-portlet__head-toolbar">
                                @php
                                    $isEnabled = $ppkRatingConfig->section_enabled['car_age_modifiers'] ?? true;
                                @endphp
                                @if($isEnabled)
                                    <span class="kt-badge kt-badge--success kt-badge--inline kt-badge--pill">Section Enabled</span>
                                @else
                                    <span class="kt-badge kt-badge--secondary kt-badge--inline kt-badge--pill">Section Disabled</span>
                                @endif
                            </div>
                        </div>
                        <div class="kt-portlet__body">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Category</th>
                                        <th>Modifier</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($ppkRatingConfig->car_age_modifiers as $key => $value)
                                    <tr>
                                        <td>{{ ucfirst(str_replace('_', ' ', $key)) }}</td>
                                        <td>{{ number_format($value * 100, 2) }}%</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Vehicle Type Modifiers -->
                    <div class="kt-portlet">
                        <div class="kt-portlet__head">
                            <div class="kt-portlet__head-label">
                                <h3 class="kt-portlet__head-title">Vehicle Type Modifiers</h3>
                            </div>
                            <div class="kt-portlet__head-toolbar">
                                @php
                                    $isEnabled = $ppkRatingConfig->section_enabled['vehicle_type_modifiers'] ?? true;
                                @endphp
                                @if($isEnabled)
                                    <span class="kt-badge kt-badge--success kt-badge--inline kt-badge--pill">Section Enabled</span>
                                @else
                                    <span class="kt-badge kt-badge--secondary kt-badge--inline kt-badge--pill">Section Disabled</span>
                                @endif
                            </div>
                        </div>
                        <div class="kt-portlet__body">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Category</th>
                                        <th>Modifier</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($ppkRatingConfig->vehicle_type_modifiers as $key => $value)
                                    <tr>
                                        <td>{{ strtoupper($key) }}</td>
                                        <td>{{ number_format($value * 100, 2) }}%</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Driver Risk Modifiers -->
                    <div class="kt-portlet">
                        <div class="kt-portlet__head">
                            <div class="kt-portlet__head-label">
                                <h3 class="kt-portlet__head-title">Driver Risk Tier Modifiers</h3>
                            </div>
                            <div class="kt-portlet__head-toolbar">
                                @php
                                    $isEnabled = $ppkRatingConfig->section_enabled['driver_risk_modifiers'] ?? true;
                                @endphp
                                @if($isEnabled)
                                    <span class="kt-badge kt-badge--success kt-badge--inline kt-badge--pill">Section Enabled</span>
                                @else
                                    <span class="kt-badge kt-badge--secondary kt-badge--inline kt-badge--pill">Section Disabled</span>
                                @endif
                            </div>
                        </div>
                        <div class="kt-portlet__body">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Category</th>
                                        <th>Modifier</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($ppkRatingConfig->driver_risk_modifiers as $key => $value)
                                    <tr>
                                        <td>{{ ucfirst($key) }}</td>
                                        <td>{{ number_format($value * 100, 2) }}%</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Territory Modifiers -->
                    <div class="kt-portlet">
                        <div class="kt-portlet__head">
                            <div class="kt-portlet__head-label">
                                <h3 class="kt-portlet__head-title">Territory Modifiers</h3>
                            </div>
                            <div class="kt-portlet__head-toolbar">
                                @php
                                    $isEnabled = $ppkRatingConfig->section_enabled['territory_modifiers'] ?? true;
                                @endphp
                                @if($isEnabled)
                                    <span class="kt-badge kt-badge--success kt-badge--inline kt-badge--pill">Section Enabled</span>
                                @else
                                    <span class="kt-badge kt-badge--secondary kt-badge--inline kt-badge--pill">Section Disabled</span>
                                @endif
                            </div>
                        </div>
                        <div class="kt-portlet__body">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Category</th>
                                        <th>Modifier</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($ppkRatingConfig->territory_modifiers as $key => $value)
                                    <tr>
                                        <td>{{ ucfirst($key) }}</td>
                                        <td>{{ number_format($value * 100, 2) }}%</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Trip Type Modifiers -->
                    <div class="kt-portlet">
                        <div class="kt-portlet__head">
                            <div class="kt-portlet__head-label">
                                <h3 class="kt-portlet__head-title">Trip Type Modifiers</h3>
                            </div>
                            <div class="kt-portlet__head-toolbar">
                                @php
                                    $isEnabled = $ppkRatingConfig->section_enabled['trip_type_modifiers'] ?? true;
                                @endphp
                                @if($isEnabled)
                                    <span class="kt-badge kt-badge--success kt-badge--inline kt-badge--pill">Section Enabled</span>
                                @else
                                    <span class="kt-badge kt-badge--secondary kt-badge--inline kt-badge--pill">Section Disabled</span>
                                @endif
                            </div>
                        </div>
                        <div class="kt-portlet__body">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Category</th>
                                        <th>Modifier</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($ppkRatingConfig->trip_type_modifiers as $key => $value)
                                    <tr>
                                        <td>{{ ucfirst(str_replace('_', ' ', $key)) }}</td>
                                        <td>{{ number_format($value * 100, 2) }}%</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Usage Frequency Modifiers -->
                    <div class="kt-portlet">
                        <div class="kt-portlet__head">
                            <div class="kt-portlet__head-label">
                                <h3 class="kt-portlet__head-title">Usage Frequency Modifiers</h3>
                            </div>
                            <div class="kt-portlet__head-toolbar">
                                @php
                                    $isEnabled = $ppkRatingConfig->section_enabled['usage_frequency_modifiers'] ?? true;
                                @endphp
                                @if($isEnabled)
                                    <span class="kt-badge kt-badge--success kt-badge--inline kt-badge--pill">Section Enabled</span>
                                @else
                                    <span class="kt-badge kt-badge--secondary kt-badge--inline kt-badge--pill">Section Disabled</span>
                                @endif
                            </div>
                        </div>
                        <div class="kt-portlet__body">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Category</th>
                                        <th>Modifier</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($ppkRatingConfig->usage_frequency_modifiers as $key => $value)
                                    <tr>
                                        <td>{{ ucfirst(str_replace('_', ' ', $key)) }}</td>
                                        <td>{{ number_format($value * 100, 2) }}%</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Policy Tenure Modifiers -->
                    <div class="kt-portlet">
                        <div class="kt-portlet__head">
                            <div class="kt-portlet__head-label">
                                <h3 class="kt-portlet__head-title">Policy Tenure Modifiers</h3>
                            </div>
                            <div class="kt-portlet__head-toolbar">
                                @php
                                    $isEnabled = $ppkRatingConfig->section_enabled['policy_tenure_modifiers'] ?? true;
                                @endphp
                                @if($isEnabled)
                                    <span class="kt-badge kt-badge--success kt-badge--inline kt-badge--pill">Section Enabled</span>
                                @else
                                    <span class="kt-badge kt-badge--secondary kt-badge--inline kt-badge--pill">Section Disabled</span>
                                @endif
                            </div>
                        </div>
                        <div class="kt-portlet__body">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Category</th>
                                        <th>Modifier</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($ppkRatingConfig->policy_tenure_modifiers as $key => $value)
                                    <tr>
                                        <td>{{ ucfirst(str_replace('_', ' ', $key)) }}</td>
                                        <td>{{ number_format($value * 100, 2) }}%</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Claims History Modifiers -->
                    <div class="kt-portlet">
                        <div class="kt-portlet__head">
                            <div class="kt-portlet__head-label">
                                <h3 class="kt-portlet__head-title">Claims History Modifiers</h3>
                            </div>
                            <div class="kt-portlet__head-toolbar">
                                @php
                                    $isEnabled = $ppkRatingConfig->section_enabled['claims_history_modifiers'] ?? true;
                                @endphp
                                @if($isEnabled)
                                    <span class="kt-badge kt-badge--success kt-badge--inline kt-badge--pill">Section Enabled</span>
                                @else
                                    <span class="kt-badge kt-badge--secondary kt-badge--inline kt-badge--pill">Section Disabled</span>
                                @endif
                            </div>
                        </div>
                        <div class="kt-portlet__body">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Category</th>
                                        <th>Modifier</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($ppkRatingConfig->claims_history_modifiers as $key => $value)
                                    <tr>
                                        <td>{{ ucfirst(str_replace('_', ' ', $key)) }}</td>
                                        <td>{{ number_format($value * 100, 2) }}%</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Gender Modifiers -->
                    <div class="kt-portlet">
                        <div class="kt-portlet__head">
                            <div class="kt-portlet__head-label">
                                <h3 class="kt-portlet__head-title">Gender Modifiers</h3>
                            </div>
                            <div class="kt-portlet__head-toolbar">
                                @if($ppkRatingConfig->gender_enabled)
                                    <span class="kt-badge kt-badge--success kt-badge--inline kt-badge--pill">Enabled</span>
                                @else
                                    <span class="kt-badge kt-badge--danger kt-badge--inline kt-badge--pill">Disabled</span>
                                @endif
                            </div>
                        </div>
                        <div class="kt-portlet__body">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Gender</th>
                                        <th>Modifier</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($ppkRatingConfig->gender_modifiers as $key => $value)
                                    <tr>
                                        <td>{{ $key }}</td>
                                        <td>{{ number_format($value * 100, 2) }}%</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Event Penalty Rates -->
                    <div class="kt-portlet">
                        <div class="kt-portlet__head">
                            <div class="kt-portlet__head-label">
                                <h3 class="kt-portlet__head-title">Event Penalty Rates</h3>
                            </div>
                            <div class="kt-portlet__head-toolbar">
                                @php
                                    $isEnabled = $ppkRatingConfig->section_enabled['event_penalty_rates'] ?? true;
                                @endphp
                                @if($isEnabled)
                                    <span class="kt-badge kt-badge--success kt-badge--inline kt-badge--pill">Section Enabled</span>
                                @else
                                    <span class="kt-badge kt-badge--secondary kt-badge--inline kt-badge--pill">Section Disabled</span>
                                @endif
                            </div>
                        </div>
                        <div class="kt-portlet__body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="font-weight-bold">Speeding Penalty Per Event:</label>
                                        <p>{{ number_format($ppkRatingConfig->speeding_penalty_per_event, 2) }} BWP</p>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="font-weight-bold">Harsh Acceleration Rate:</label>
                                        <p>{{ number_format($ppkRatingConfig->harsh_acceleration_rate, 2) }} BWP/km</p>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="font-weight-bold">Idle Time Rate:</label>
                                        <p>{{ number_format($ppkRatingConfig->idle_time_rate, 2) }} BWP/km</p>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="font-weight-bold">High Revving Rate:</label>
                                        <p>{{ number_format($ppkRatingConfig->high_revving_rate, 2) }} BWP/km</p>
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
                            <div class="kt-portlet__head-toolbar">
                                @php
                                    $isEnabled = $ppkRatingConfig->section_enabled['weather_severity_modifiers'] ?? true;
                                @endphp
                                @if($isEnabled)
                                    <span class="kt-badge kt-badge--success kt-badge--inline kt-badge--pill">Section Enabled</span>
                                @else
                                    <span class="kt-badge kt-badge--secondary kt-badge--inline kt-badge--pill">Section Disabled</span>
                                @endif
                            </div>
                        </div>
                        <div class="kt-portlet__body">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Weather Type</th>
                                        <th>Modifier</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($ppkRatingConfig->weather_severity_modifiers as $key => $value)
                                    <tr>
                                        <td>{{ ucfirst(str_replace('_', ' ', $key)) }}</td>
                                        <td>{{ number_format($value * 100, 2) }}%</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Night Segment Modifiers -->
                    <div class="kt-portlet">
                        <div class="kt-portlet__head">
                            <div class="kt-portlet__head-label">
                                <h3 class="kt-portlet__head-title">Night Segment Modifiers</h3>
                            </div>
                            <div class="kt-portlet__head-toolbar">
                                @php
                                    $isEnabled = $ppkRatingConfig->section_enabled['night_segment_modifiers'] ?? true;
                                @endphp
                                @if($isEnabled)
                                    <span class="kt-badge kt-badge--success kt-badge--inline kt-badge--pill">Section Enabled</span>
                                @else
                                    <span class="kt-badge kt-badge--secondary kt-badge--inline kt-badge--pill">Section Disabled</span>
                                @endif
                            </div>
                        </div>
                        <div class="kt-portlet__body">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Time Segment</th>
                                        <th>Modifier</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($ppkRatingConfig->night_segment_modifiers as $key => $value)
                                    <tr>
                                        <td>{{ ucfirst(str_replace('_', ' ', $key)) }}</td>
                                        <td>{{ number_format($value * 100, 2) }}%</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Other Modifiers -->
                    <div class="kt-portlet">
                        <div class="kt-portlet__head">
                            <div class="kt-portlet__head-label">
                                <h3 class="kt-portlet__head-title">Other Modifiers</h3>
                            </div>
                            <div class="kt-portlet__head-toolbar">
                                @php
                                    $isEnabled = $ppkRatingConfig->section_enabled['other_modifiers'] ?? true;
                                @endphp
                                @if($isEnabled)
                                    <span class="kt-badge kt-badge--success kt-badge--inline kt-badge--pill">Section Enabled</span>
                                @else
                                    <span class="kt-badge kt-badge--secondary kt-badge--inline kt-badge--pill">Section Disabled</span>
                                @endif
                            </div>
                        </div>
                        <div class="kt-portlet__body">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label class="font-weight-bold">Congestion Modifier:</label>
                                        <p>{{ number_format($ppkRatingConfig->congestion_modifier * 100, 2) }}%</p>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label class="font-weight-bold">Seasonal Modifier:</label>
                                        <p>{{ number_format($ppkRatingConfig->seasonal_modifier * 100, 2) }}%</p>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label class="font-weight-bold">ADAS Discount:</label>
                                        <p>{{ number_format($ppkRatingConfig->adas_discount * 100, 2) }}%</p>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="font-weight-bold">Roadworthiness Discount:</label>
                                        <p>{{ number_format($ppkRatingConfig->roadworthiness_discount * 100, 2) }}%</p>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="font-weight-bold">Safe Driver Cashback:</label>
                                        <p>{{ number_format($ppkRatingConfig->safe_driver_cashback * 100, 2) }}%</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="kt-portlet">
                        <div class="kt-portlet__foot">
                            <div class="kt-form__actions">
                                <a href="{{ route('ppk-rating-config.edit', $ppkRatingConfig->id) }}" class="btn btn-primary">Edit Configuration</a>
                                <a href="{{ route('ppk-rating-config.index') }}" class="btn btn-secondary">Back to List</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>

    @include('admin.layouts.scripts')
</body>
</html>

