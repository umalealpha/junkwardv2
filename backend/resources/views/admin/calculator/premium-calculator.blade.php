<!DOCTYPE html>
<html lang="en">

@include('admin.layouts.header')
<link href="{{ asset('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ asset('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}" rel="stylesheet" type="text/css" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

<!-- begin::Body -->
<body class="kt-header--fixed kt-header-mobile--fixed kt-subheader--enabled kt-subheader--transparent kt-aside--enabled kt-aside--fixed kt-aside--minimize kt-page--loading">
    <!-- begin:: Header Mobile -->
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
    <!-- end:: Header Mobile -->

    <!-- begin:: Root -->
    <div class="kt-grid kt-grid--hor kt-grid--root">
        <!-- begin:: Page -->
        <div class="kt-grid__item kt-grid__item--fluid kt-grid kt-grid--ver kt-page">
            @include('admin.layouts.sidebar')
            @include('admin.layouts.topNav')
        </div>

        <!--If Password default -->
        @if (Auth::user()->password == null)
            @include('includes.reset')
        @else
            <div class="kt-grid__item kt-grid__item--fluid kt-grid kt-grid--hor">
                <!-- begin:: Subheader -->
                <div class="kt-subheader kt-grid__item" id="kt_subheader">
                    <div class="kt-subheader__main">
                        <h3 class="kt-subheader__title">
                            Premium Calculator - Trip Based Rating
                        </h3>
                        <span class="kt-subheader__separator kt-hidden"></span>
                        <div class="kt-subheader__breadcrumbs">
                            <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                            <span class="kt-subheader__breadcrumbs-separator"></span>
                            <a href="{{ Route('admin-dashboard') }}" class="kt-subheader__breadcrumbs-link"> Dashboard </a>
                            <span class="kt-subheader__breadcrumbs-separator"></span>
                            <a href="{{ Route('admin.calculator.index') }}" class="kt-subheader__breadcrumbs-link">
                                <span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">Premium Calculator</span>
                            </a>
                        </div>
                    </div>
                </div>
                <!-- end:: Subheader -->

                <!-- begin:: Content -->
                <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
                    <div class="kt-portlet kt-portlet--mobile">
                        <div class="kt-portlet__head">
                            <div class="kt-portlet__head-label">
                                <h3 class="kt-portlet__head-title">
                                    <i class="fas fa-calculator"></i> Premium Calculator - Trip Based Rating
                                </h3>
                            </div>
                        </div>
                        <div class="kt-portlet__body">
                            <!-- Calculation Method Selection -->
                            <div class="row mb-4">
                        <div class="col-12">
                            <div class="btn-group btn-group-toggle w-100" data-toggle="buttons">
                                <label class="btn btn-outline-primary active">
                                    <input type="radio" name="calculation_method" id="method_manual" value="manual" checked> Manual Entry
                                </label>
                                <label class="btn btn-outline-primary">
                                    <input type="radio" name="calculation_method" id="method_trip" value="trip"> From Trip Data
                                </label>
                            </div>
                        </div>
                    </div>

                    <form id="calculatorForm">
                        @csrf
                        
                        <!-- Manual Entry Section -->
                        <div id="manualSection">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="distance_km" class="form-label">Distance (KM) <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="distance_km" name="distance_km" 
                                           step="0.01" min="0" placeholder="Enter distance in kilometers" required>
                                    <small class="form-text text-muted">Enter the total distance traveled in kilometers</small>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="behaviour_category" class="form-label">Behaviour Category <span class="text-danger">*</span></label>
                                    <select class="form-control" id="behaviour_category" name="behaviour_category" required>
                                        <option value="">-- Select Category --</option>
                                        <option value="A">Category A - Normal Rate (0% Surcharge)</option>
                                        <option value="B">Category B - 20% Surcharge</option>
                                        <option value="C">Category C - 40% Surcharge</option>
                                        <option value="D">Category D - 60% Surcharge</option>
                                    </select>
                                    <small class="form-text text-muted">Select driver behaviour risk category</small>
                                </div>
                            </div>
                        </div>

                        <!-- Trip Data Section -->
                        <div id="tripSection" style="display: none;">
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="vehicle_plate" class="form-label">Vehicle Plate <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="vehicle_plate" name="vehicle_plate" 
                                           placeholder="e.g., B123ABC">
                                    <small class="form-text text-muted">Enter vehicle registration plate</small>
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="start_date" class="form-label">Start Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="start_date" name="start_date">
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="end_date" class="form-label">End Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="end_date" name="end_date">
                                </div>

                                <div class="col-md-12 mb-3">
                                    <button type="button" class="btn btn-info" id="fetchTripDataBtn">
                                        <i class="fas fa-search"></i> Fetch Trip Data
                                    </button>
                                    <span id="tripDataLoading" style="display: none;">
                                        <i class="fas fa-spinner fa-spin"></i> Fetching trip data...
                                    </span>
                                </div>

                                <div class="col-md-12 mb-3" id="tripDataSummary" style="display: none;">
                                    <div class="alert alert-info">
                                        <h6 class="mb-2"><i class="fas fa-info-circle"></i> Trip Data Summary</h6>
                                        <div id="tripSummaryContent"></div>
                                    </div>
                                </div>

                                <div class="col-md-12 mb-3" id="tripDetailsTable" style="display: none;">
                                    <h6 class="mb-2"><i class="fas fa-list"></i> Trip Details</h6>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-bordered">
                                            <thead class="thead-light">
                                                <tr>
                                                    <th>Start Time</th>
                                                    <th>End Time</th>
                                                    <th>Distance (km)</th>
                                                    <th>Duration</th>
                                                    <th>Avg Speed</th>
                                                    <th>OptiDrive</th>
                                                    <th>From</th>
                                                    <th>To</th>
                                                </tr>
                                            </thead>
                                            <tbody id="tripDetailsBody">
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <div class="col-md-6 mb-3" id="tripDistanceDisplay" style="display: none;">
                                    <label for="trip_distance_display" class="form-label">Total Distance (KM) <span class="text-success">✓</span></label>
                                    <input type="text" class="form-control bg-light" id="trip_distance_display" readonly>
                                    <small class="form-text text-success">Distance automatically calculated from trip data</small>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="behaviour_category_trip" class="form-label">Behaviour Category <span class="text-danger">*</span></label>
                                    <select class="form-control" id="behaviour_category_trip" name="behaviour_category">
                                        <option value="">-- Select Category --</option>
                                        <option value="A">Category A - Normal Rate (0% Surcharge)</option>
                                        <option value="B">Category B - 20% Surcharge</option>
                                        <option value="C">Category C - 40% Surcharge</option>
                                        <option value="D">Category D - 60% Surcharge</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Vehicle Details Section -->
                        <div class="row mt-4">
                            <div class="col-12">
                                <h5 class="mb-3"><i class="fas fa-car"></i> Vehicle Details</h5>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label>Is your car imported? <span class="text-danger">*</span></label>
                                <div class="btn-group btn-group-toggle d-flex" data-toggle="buttons">
                                    <label class="btn btn-outline-primary">
                                        <input type="radio" name="import_status" value="1" autocomplete="off"> Yes
                                    </label>
                                    <label class="btn btn-outline-primary">
                                        <input type="radio" name="import_status" value="0" autocomplete="off"> No
                                    </label>
                                </div>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label>Vehicle Make <span class="text-danger">*</span></label>
                                <div style="text-align: center; display: none;" id="makeLoader">
                                    <div class="spinner-border spinner-border-sm"></div>
                                </div>
                                <select class="form-control" id="make" name="make">
                                    <option value="">Select Make</option>
                                </select>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label>Vehicle Model <span class="text-danger">*</span></label>
                                <div style="text-align: center; display: none;" id="modelLoader">
                                    <div class="spinner-border spinner-border-sm"></div>
                                </div>
                                <select class="form-control" id="model" name="model">
                                    <option value="">Select Model</option>
                                </select>
                            </div>

                            <div class="col-md-6 mb-3" id="yearSection">
                                <label>Manufacturing Year <span class="text-danger">*</span></label>
                                <div style="text-align: center; display: none;" id="yearLoader">
                                    <div class="spinner-border spinner-border-sm"></div>
                                </div>
                                <select class="form-control" id="year" name="year">
                                    <option value="">Select Manufacturing Year</option>
                                    @for ($i = date("Y"); $i >= 1970; $i--)
                                        <option value="{{ $i }}">{{ $i }}</option>
                                    @endfor
                                </select>
                            </div>

                            <div class="col-md-6 mb-3 Variant" id="variantSection" style="display: none;">
                                <label>Variant <span class="text-danger">*</span></label>
                                <div style="text-align: center; display: none;" id="variantLoader">
                                    <div class="spinner-border spinner-border-sm"></div>
                                </div>
                                <select class="form-control" id="variant" name="variant">
                                    <option value="">Select Variant</option>
                                </select>
                            </div>
                        </div>

                        <!-- Advanced Options (Optional) -->
                        <div class="row mt-3">
                            <div class="col-12">
                                <div class="card bg-light">
                                    <div class="card-header">
                                        <a class="collapsed" data-toggle="collapse" href="#advancedOptions">
                                            <i class="fas fa-cog"></i> Additional Parameters (Optional)
                                        </a>
                                    </div>
                                    <div id="advancedOptions" class="collapse">
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-md-4 mb-3">
                                                    <label for="sum_insured" class="form-label">Sum Insured</label>
                                                    <input type="number" class="form-control" id="sum_insured" name="sum_insured" 
                                                           placeholder="100000" value="100000">
                                                </div>

                                                <div class="col-md-4 mb-3">
                                                    <label for="gender" class="form-label">Gender</label>
                                                    <select class="form-control" id="gender" name="gender">
                                                        <option value="Male">Male</option>
                                                        <option value="Female">Female</option>
                                                    </select>
                                                </div>

                                                <div class="col-md-4 mb-3">
                                                    <label for="dob" class="form-label">Date of Birth</label>
                                                    <input type="date" class="form-control" id="dob" name="dob" value="1990-01-01">
                                                </div>

                                                <div class="col-md-4 mb-3">
                                                    <label for="marital_status" class="form-label">Marital Status</label>
                                                    <select class="form-control" id="marital_status" name="marital_status">
                                                        <option value="Never Married">Never Married</option>
                                                        <option value="Married Before">Married Before</option>
                                                    </select>
                                                </div>

                                                <div class="col-md-4 mb-3">
                                                    <label for="claim_count" class="form-label">Claim Count</label>
                                                    <input type="number" class="form-control" id="claim_count" name="claim_count" 
                                                           placeholder="0" value="0" min="0">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Calculate Button -->
                        <div class="row mt-4">
                            <div class="col-12 text-center">
                                <button type="submit" class="btn btn-primary btn-lg" id="calculateBtn">
                                    <i class="fas fa-calculator"></i> Calculate Premium
                                </button>
                                <button type="button" class="btn btn-secondary btn-lg" id="resetBtn">
                                    <i class="fas fa-redo"></i> Reset
                                </button>
                            </div>
                        </div>
                    </form>

                    <!-- Loading Indicator -->
                    <div id="calculationLoading" class="text-center mt-4" style="display: none;">
                        <div class="spinner-border text-primary" role="status">
                            <span class="sr-only">Calculating...</span>
                        </div>
                        <p class="mt-2">Calculating premium...</p>
                    </div>

                    <!-- Results Section -->
                    <div id="resultsSection" class="mt-5" style="display: none;">
                        <hr>
                        <h4 class="text-primary mb-4"><i class="fas fa-chart-line"></i> Calculation Results</h4>
                        
                        <!-- Summary Cards -->
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <div class="card border-info">
                                    <div class="card-body text-center">
                                        <h6 class="text-muted">Monthly Premium</h6>
                                        <h3 class="text-info" id="result_monthly_premium">P 0.00</h3>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-3 mb-3">
                                <div class="card border-warning">
                                    <div class="card-body text-center">
                                        <h6 class="text-muted">Base Rate/KM</h6>
                                        <h3 class="text-warning" id="result_base_rate">P 0.00</h3>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-3 mb-3">
                                <div class="card border-danger">
                                    <div class="card-body text-center">
                                        <h6 class="text-muted">Final Rate/KM</h6>
                                        <h3 class="text-danger" id="result_final_rate">P 0.00</h3>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-3 mb-3">
                                <div class="card border-success">
                                    <div class="card-body text-center">
                                        <h6 class="text-muted">Total Premium</h6>
                                        <h3 class="text-success" id="result_total_premium">P 0.00</h3>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Detailed Breakdown -->
                        <div class="row mt-4">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header bg-light">
                                        <h5 class="mb-0"><i class="fas fa-list"></i> Calculation Details</h5>
                                    </div>
                                    <div class="card-body">
                                        <table class="table table-sm">
                                            <tbody>
                                                <tr>
                                                    <th>Distance:</th>
                                                    <td id="result_distance">-</td>
                                                </tr>
                                                <tr>
                                                    <th>Behaviour Category:</th>
                                                    <td id="result_category">-</td>
                                                </tr>
                                                <tr>
                                                    <th>Surcharge %:</th>
                                                    <td id="result_surcharge_pct">-</td>
                                                </tr>
                                                <tr>
                                                    <th>Base Premium:</th>
                                                    <td id="result_base_premium">-</td>
                                                </tr>
                                                <tr>
                                                    <th>Surcharge Amount:</th>
                                                    <td id="result_surcharge_amount">-</td>
                                                </tr>
                                                <tr class="table-success">
                                                    <th>Total Premium:</th>
                                                    <th id="result_total_premium_detail">-</th>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header bg-light">
                                        <h5 class="mb-0"><i class="fas fa-calculator"></i> Step-by-Step Breakdown</h5>
                                    </div>
                                    <div class="card-body">
                                        <ol id="calculation_steps" class="pl-3">
                                            <!-- Steps will be populated here -->
                                        </ol>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                            <!-- Error Message -->
                            <div id="errorMessage" class="alert alert-danger mt-4" style="display: none;">
                                <i class="fas fa-exclamation-triangle"></i> <span id="errorText"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Behaviour Category Information -->
                    <div class="kt-portlet kt-portlet--mobile mt-4">
                        <div class="kt-portlet__head">
                            <div class="kt-portlet__head-label">
                                <h3 class="kt-portlet__head-title">
                                    <i class="fas fa-info-circle"></i> Behaviour Category Information
                                </h3>
                            </div>
                        </div>
                        <div class="kt-portlet__body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6>How it Works:</h6>
                                    <ol>
                                        <li>Monthly Premium is fetched from <code>rate.alphadirect.co.bw</code></li>
                                        <li>Base Rate per KM is calculated: <strong>Monthly Premium ÷ 500km</strong></li>
                                        <li>Behaviour surcharge is applied based on driver category</li>
                                        <li>Final Premium = Distance × Final Rate per KM</li>
                                    </ol>
                                </div>
                                <div class="col-md-6">
                                    <h6>Behaviour Categories:</h6>
                                    <table class="table table-sm table-bordered">
                                        <thead class="thead-light">
                                            <tr>
                                                <th>Category</th>
                                                <th>Description</th>
                                                <th>Surcharge</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr class="table-success">
                                                <td><strong>A</strong></td>
                                                <td>Excellent Driver</td>
                                                <td>0% (Normal Rate)</td>
                                            </tr>
                                            <tr class="table-warning">
                                                <td><strong>B</strong></td>
                                                <td>Good Driver</td>
                                                <td>20% Surcharge</td>
                                            </tr>
                                            <tr class="table-warning">
                                                <td><strong>C</strong></td>
                                                <td>Average Driver</td>
                                                <td>40% Surcharge</td>
                                            </tr>
                                            <tr class="table-danger">
                                                <td><strong>D</strong></td>
                                                <td>High-Risk Driver</td>
                                                <td>60% Surcharge</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- end:: Content -->
            </div>
        @endif
    </div>
    <!-- end:: Page -->
    <!-- end:: Root -->

@include('admin.layouts.scripts')

<script>
$(document).ready(function() {
    var API_URL = "{{env('API_URL')}}";
    
    console.log('API_URL:', API_URL);
    
    // Initialize form - disable trip section fields by default (manual mode is active)
    $('#behaviour_category_trip').prop('disabled', true);
    $('#vehicle_plate').prop('disabled', true);
    $('#start_date').prop('disabled', true);
    $('#end_date').prop('disabled', true);

    // Reusable JSON parsing function
    function parseJSON(response) {
        var data;
        
        // Try multiple parsing strategies
        var parseStrategies = [
            // Strategy 1: Direct parse
            function() {
                return JSON.parse(response);
            },
            // Strategy 2: Trim whitespace and parse
            function() {
                return JSON.parse(response.trim());
            },
            // Strategy 3: Extract JSON object (greedy match)
            function() {
                var match = response.match(/\{(?:[^{}]|(?:\{(?:[^{}]|(?:\{[^{}]*\}))*\}))*\}/);
                if(match) return JSON.parse(match[0]);
                throw new Error('No JSON found');
            },
            // Strategy 4: Find first { and try to parse from there
            function() {
                var startIdx = response.indexOf('{');
                if(startIdx >= 0) {
                    // Try to find matching closing brace
                    var depth = 0;
                    var inString = false;
                    var escape = false;
                    
                    for(var i = startIdx; i < response.length; i++) {
                        var char = response[i];
                        
                        if(escape) {
                            escape = false;
                            continue;
                        }
                        
                        if(char === '\\') {
                            escape = true;
                            continue;
                        }
                        
                        if(char === '"') {
                            inString = !inString;
                            continue;
                        }
                        
                        if(!inString) {
                            if(char === '{') depth++;
                            if(char === '}') {
                                depth--;
                                if(depth === 0) {
                                    // Found complete JSON
                                    var jsonStr = response.substring(startIdx, i + 1);
                                    return JSON.parse(jsonStr);
                                }
                            }
                        }
                    }
                }
                throw new Error('Could not find complete JSON');
            }
        ];
        
        var parsed = false;
        for(var i = 0; i < parseStrategies.length; i++) {
            try {
                data = parseStrategies[i]();
                console.log('✓ JSON parsed successfully using strategy', i + 1);
                parsed = true;
                break;
            } catch(e) {
                console.log('Strategy', i + 1, 'failed:', e.message);
            }
        }
        
        if(!parsed) {
            console.error('✗ All parsing strategies failed');
            return null;
        }
        
        return data;
    }

    // Import Status Change - Fetch Vehicle Makes
    $('input[name="import_status"]').on('change', function() {
        var importStatus = $(this).val();
        console.log('Import status selected:', importStatus);
        
        $('#make').empty().append('<option value="">Select Make</option>');
        $('#model').empty().append('<option value="">Select Model</option>');
        $('#variant').empty().append('<option value="">Select Variant</option>');
        
        // Reset year dropdown based on import status
        if(importStatus == '1') {
            // For imported: Keep static year dropdown
            $('#year').prop('disabled', false);
            $('.Variant').hide();
        } else {
            // For non-imported: Year will be populated from API
            $('#year').empty().append('<option value="">Select Year</option>');
            $('.Variant').show();
        }
        
        $('#makeLoader').show();
        $('#make').hide();
        
        var url = importStatus == '1' ? API_URL + 'frontendpay/vehicleMake' : API_URL + 'frontendpay/getTTVehicleMakes';
        console.log('Fetching makes from URL:', url);
        
        getMake(url, importStatus);
    });

    // Function to fetch vehicle makes
    function getMake(url, imported) {
        console.log('getMake called with URL:', url, 'imported:', imported);
        
        $.ajax({
            type: "POST",
            url: url,
            data: {
                _token: '{{ csrf_token() }}'
            },
            dataType: "text", // Changed from "json" to handle response manually
            beforeSend: function() {
                console.log('Sending request to:', url);
                $('#makeLoader').show();
                $('#make').hide();
            },
            success: function(response, status, xhr) {
                console.log('✓ Raw response received');
                console.log('Response length:', response.length);
                console.log('First 200 chars:', response.substring(0, 200));
                
                var data;
                
                // Try multiple parsing strategies
                var parseStrategies = [
                    // Strategy 1: Direct parse
                    function() {
                        return JSON.parse(response);
                    },
                    // Strategy 2: Trim whitespace and parse
                    function() {
                        return JSON.parse(response.trim());
                    },
                    // Strategy 3: Extract JSON object (greedy match)
                    function() {
                        var match = response.match(/\{(?:[^{}]|(?:\{(?:[^{}]|(?:\{[^{}]*\}))*\}))*\}/);
                        if(match) return JSON.parse(match[0]);
                        throw new Error('No JSON found');
                    },
                    // Strategy 4: Find first { and try to parse from there
                    function() {
                        var startIdx = response.indexOf('{');
                        if(startIdx >= 0) {
                            // Try to find matching closing brace
                            var depth = 0;
                            var inString = false;
                            var escape = false;
                            
                            for(var i = startIdx; i < response.length; i++) {
                                var char = response[i];
                                
                                if(escape) {
                                    escape = false;
                                    continue;
                                }
                                
                                if(char === '\\') {
                                    escape = true;
                                    continue;
                                }
                                
                                if(char === '"') {
                                    inString = !inString;
                                    continue;
                                }
                                
                                if(!inString) {
                                    if(char === '{') depth++;
                                    if(char === '}') {
                                        depth--;
                                        if(depth === 0) {
                                            // Found complete JSON
                                            var jsonStr = response.substring(startIdx, i + 1);
                                            return JSON.parse(jsonStr);
                                        }
                                    }
                                }
                            }
                        }
                        throw new Error('Could not find complete JSON');
                    }
                ];
                
                var parsed = false;
                for(var i = 0; i < parseStrategies.length; i++) {
                    try {
                        data = parseStrategies[i]();
                        console.log('✓ JSON parsed successfully using strategy', i + 1);
                        parsed = true;
                        break;
                    } catch(e) {
                        console.log('Strategy', i + 1, 'failed:', e.message);
                    }
                }
                
                if(!parsed) {
                    console.error('✗ All parsing strategies failed');
                    console.error('Response preview:', response.substring(0, 1000));
                    $('#makeLoader').hide();
                    $('#make').show();
                    alert('Could not parse API response. The response may be corrupted or too large.\n\nCheck console for details.');
                    return;
                }
                
                $("#make").empty();
                var str = '<option value="">Select Make</option>';
                
                if(imported == '1') {
                    console.log('Processing imported vehicle makes');
                    if(data.makes && data.makes.length > 0) {
                        console.log('Found', data.makes.length, 'makes');
                        $.each(data.makes, function() {
                            if(this.s_Make != '') {
                                str += '<option value="'+ this.s_Make +'">'+ this.s_Make +'</option>';
                            }
                        });
                    } else {
                        console.warn('No makes found in response');
                    }
                } else {
                    console.log('Processing non-imported vehicle makes');
                    if(data.Makes && data.Makes.length > 0) {
                        console.log('Found', data.Makes.length, 'makes');
                        $.each(data.Makes, function(key, val) {
                            if(val != '') {
                                str += '<option value="' + val + '">' + val + '</option>';
                            }
                        });
                    } else {
                        console.warn('No Makes found in response');
                    }
                }
                
                $('#make').html(str);
                $('#makeLoader').hide();
                $('#make').show();
                console.log('Make dropdown populated');
            },
            error: function(xhr, status, error) {
                console.error('✗ AJAX Error Details:');
                console.error('Status:', status);
                console.error('Error:', error);
                console.error('Status Code:', xhr.status);
                console.error('Response Text (first 500 chars):', xhr.responseText.substring(0, 500));
                console.error('URL attempted:', url);
                
                $('#makeLoader').hide();
                $('#make').show();
                
                alert('Error fetching vehicle makes. Check console for details.');
            }
        });
    }

    // On Make Change - Fetch Models
    $('#make').on('change', function() {
        var status = $('input[name="import_status"]:checked').val();
        
        if($(this).val()) {
            $('#model').empty().append('<option value="">Select Model</option>');
            
            // Only clear year for non-imported (imported has static year list)
            if(status == '0') {
                $('#year').empty().append('<option value="">Select Year</option>');
                $('#variantSection').hide();
            }
            
            $('#variant').empty().append('<option value="">Select Variant</option>');
            
            getVehicleModel(status);
        }
    });

    // Function to fetch vehicle models
    function getVehicleModel(status) {
        var url, data;
        
        if(status == '1') {
            url = API_URL + 'frontendpay/vehicleModel';
            data = { 
                vehicle_make: $('#make option:selected').val(),
                _token: '{{ csrf_token() }}'
            };
        } else {
            url = API_URL + 'frontendpay/getTTVehicleModels';
            data = { 
                make: $('#make option:selected').val(),
                _token: '{{ csrf_token() }}'
            };
        }
        
        $('#modelLoader').show();
        $('#model').hide();
        
        $.ajax({
            type: "POST",
            url: url,
            data: data,
            dataType: "text",
            success: function(response) {
                console.log('✓ Model response received, length:', response.length);
                
                var responseData = parseJSON(response);
                if(!responseData) {
                    $('#modelLoader').hide();
                    $('#model').show();
                    alert('Error parsing model data');
                    return;
                }
                
                console.log('✓ Model data parsed:', responseData);
                $("#model").empty();
                $("#model").append('<option value="">Select Model</option>');
                
                if(status == '1') {
                    if(responseData.makes && responseData.makes.length > 0) {
                        console.log('Found', responseData.makes.length, 'models for imported');
                        $.each(responseData.makes, function() {
                            if(this.s_Variant != null) {
                                var option = $('<option value="' + this.s_Variant + '">' + this.s_Variant + '</option>');
                                $("#model").append(option);
                            }
                        });
                    }
                } else {
                    if(responseData.Models && responseData.Models.length > 0) {
                        console.log('Found', responseData.Models.length, 'models for non-imported');
                        $.each(responseData.Models, function() {
                            if(this.Model != '') {
                                var option = $('<option value="' + this.Model + '">' + this.Model + '</option>');
                                $("#model").append(option);
                            }
                        });
                    }
                }
                
                $('#modelLoader').hide();
                $('#model').show();
                console.log('✓ Model dropdown populated');
            },
            error: function(xhr) {
                console.error('Error fetching models:', xhr.responseText);
                $('#modelLoader').hide();
                $('#model').show();
                alert('Error fetching vehicle models.');
            }
        });
    }

    // On Model Change - Fetch Years (for non-imported only)
    $('#model').on('change', function() {
        var status = $('input[name="import_status"]:checked').val();
        var val = $(this).val();
        
        if(status == '0' && val != null && val != '') {
            $('#suminsured').val('');
            $('#year').empty().append('<option value="">Select Year</option>');
            $('#variant').empty().append('<option value="">Select Variant</option>');
            getVehicleYear(status);
        }
    });

    // Function to fetch vehicle years
    function getVehicleYear(status) {
        var url = API_URL + 'frontendpay/getTTVehicleYear';
        var data = {
            make: $('#make option:selected').val(),
            vehicleModel: $('#model option:selected').val(),
            _token: '{{ csrf_token() }}'
        };
        
        $.ajax({
            type: "POST",
            url: url,
            data: data,
            dataType: "text",
            beforeSend: function() {
                $('#yearLoader').show();
                $('#year').hide();
            },
            success: function(response) {
                console.log('✓ Year response received, length:', response.length);
                
                var responseData = parseJSON(response);
                if(!responseData) {
                    $('#yearLoader').hide();
                    $('#year').show();
                    return;
                }
                
                console.log('✓ Year data parsed:', responseData);
                $("#year").empty();
                $("#year").append('<option value="">Select Year</option>');
                
                if(responseData.year && responseData.year.length > 0) {
                    console.log('Found', responseData.year.length, 'years');
                    $.each(responseData.year, function(index, year) {
                        if(year !== '') {
                            var option = $('<option value="' + year + '">' + year + '</option>');
                            $("#year").append(option);
                        }
                    });
                }
                
                $('#yearLoader').hide();
                $('#year').show();
                console.log('✓ Year dropdown populated');
            },
            error: function(xhr) {
                console.error('Error fetching years:', xhr.responseText);
                $('#yearLoader').hide();
                $('#year').show();
            }
        });
    }

    // On Year Change - Fetch Variants (for non-imported only)
    $('#year').on('change', function() {
        var status = $('input[name="import_status"]:checked').val();
        var val = $(this).val();
        
        if(status == '0') {
            $('#suminsured').val('');
            $('#variant').empty().append('<option value="">Select Variant</option>');
            
            if(val != null && val != '') {
                getVehicleVariant(status);
            }
        }
    });

    // Function to fetch vehicle variants
    function getVehicleVariant(status) {
        var url = API_URL + 'frontendpay/getTTVehicleVariant';
        var data = {
            make: $('#make option:selected').val(),
            vehicleModel: $('#model option:selected').val(),
            manufacturing_year: $('#year option:selected').val(),
            _token: '{{ csrf_token() }}'
        };
        
        $.ajax({
            type: "POST",
            url: url,
            data: data,
            dataType: "text",
            beforeSend: function() {
                $('#variantLoader').show();
                $('#variant').hide();
            },
            success: function(response) {
                console.log('✓ Variant response received, length:', response.length);
                
                var responseData = parseJSON(response);
                if(!responseData) {
                    $('#variantLoader').hide();
                    $('#variant').show();
                    return;
                }
                
                console.log('✓ Variant data parsed:', responseData);
                $("#variant").empty();
                $("#variant").append('<option value="">Select Variant</option>');
                
                if(responseData.variant && responseData.variant.length > 0) {
                    console.log('Found', responseData.variant.length, 'variants');
                    $.each(responseData.variant, function(index, variant) {
                        if(variant !== '') {
                            var option = $('<option value="' + variant + '">' + variant + '</option>');
                            $("#variant").append(option);
                        }
                    });
                }
                
                $('#variantLoader').hide();
                $('#variant').show();
                console.log('✓ Variant dropdown populated');
            },
            error: function(xhr) {
                console.error('Error fetching variants:', xhr.responseText);
                $('#variantLoader').hide();
                $('#variant').show();
            }
        });
    }

    // On Variant Change - Fetch Vehicle Value (for non-imported only)
    $('#variant').on('change', function() {
        var status = $('input[name="import_status"]:checked').val();
        var val = $(this).val();
        
        if(status == '0' && val != null && val != '') {
            $('#sum_insured').val('');
            
            $.ajax({
                type: "POST",
                datatype: 'json',
                url: API_URL + 'frontendpay/getTTValue',
                data: {
                    vehicleMake: $('#make option:selected').val(),
                    vehicleModel: $('#model option:selected').val(),
                    manufacturing_year: $('#year option:selected').val(),
                    variant: $('#variant option:selected').val(),
                    _token: '{{ csrf_token() }}'
                },
                dataType: "text",
                beforeSend: function () {
                    console.log('Fetching vehicle value...');
                },
                success: function (response) {
                    var data = parseJSON(response);
                    if(data && data.value){
                        var val = parseFloat(data.value).toFixed(2);
                        $('#sum_insured').val(val);
                        console.log('✓ Vehicle value fetched:', val);
                    }
                },
                error: function () {
                    console.error('Error fetching vehicle value');
                    $('#sum_insured').val('');
                }
            });
        }
    });

    // Toggle between manual and trip-based calculation
    $('input[name="calculation_method"]').change(function() {
        if ($(this).val() === 'manual') {
            $('#manualSection').show();
            $('#tripSection').hide();
            
            // Enable manual section fields
            $('#distance_km').attr('required', true).prop('disabled', false);
            $('#behaviour_category').attr('required', true).prop('disabled', false);
            
            // Disable trip section fields so they don't get submitted
            $('#vehicle_plate').attr('required', false).prop('disabled', true);
            $('#start_date').attr('required', false).prop('disabled', true);
            $('#end_date').attr('required', false).prop('disabled', true);
            $('#behaviour_category_trip').attr('required', false).prop('disabled', true);
        } else {
            $('#manualSection').hide();
            $('#tripSection').show();
            
            // Keep distance_km enabled (but hidden) - it will be auto-filled from trip data
            // Don't disable it so it gets submitted with the form
            $('#distance_km').attr('required', true).prop('disabled', false);
            
            // Disable manual behaviour_category (trip mode uses behaviour_category_trip)
            $('#behaviour_category').attr('required', false).prop('disabled', true);
            
            // Enable trip section fields
            $('#vehicle_plate').attr('required', true).prop('disabled', false);
            $('#start_date').attr('required', true).prop('disabled', false);
            $('#end_date').attr('required', true).prop('disabled', false);
            
            // Note: behaviour_category_trip will be auto-filled and made readonly when trip data is loaded
            // Initially keep it enabled but it will be set to readonly after fetching trip data
            $('#behaviour_category_trip').attr('required', true).prop('disabled', false).prop('readonly', false).removeClass('bg-light');
            $('#behaviour_category_trip').next('.form-text').remove(); // Remove auto-calc notice if exists
        }
        // Reset results
        $('#resultsSection').hide();
        $('#errorMessage').hide();
        $('#tripDataSummary').hide();
        $('#tripDetailsTable').hide();
        $('#tripDistanceDisplay').hide();
    });

    // Fetch Trip Data
    $('#fetchTripDataBtn').click(function() {
        var vehiclePlate = $('#vehicle_plate').val();
        var startDate = $('#start_date').val();
        var endDate = $('#end_date').val();

        if (!vehiclePlate || !startDate || !endDate) {
            alert('Please fill in all trip data fields');
            return;
        }

        $('#tripDataLoading').show();
        $('#tripDataSummary').hide();

        $.ajax({
            url: '{{ route("admin.calculator.trip-data") }}',
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                vehicle_plate: vehiclePlate,
                start_date: startDate,
                end_date: endDate
            },
            success: function(response) {
                $('#tripDataLoading').hide();
                if (response.success) {
                    var summary = response.data.summary;
                    var trips = response.data.trips;
                    
                    // Display summary with optidrive and behaviour category
                    var categoryLabel = {
                        'A': 'Category A - Excellent (0% Surcharge)',
                        'B': 'Category B - Good (20% Surcharge)',
                        'C': 'Category C - Average (40% Surcharge)',
                        'D': 'Category D - High Risk (60% Surcharge)'
                    };
                    
                    var content = `
                        <p class="mb-1"><strong>Total Trips:</strong> ${summary.total_trips}</p>
                        <p class="mb-1"><strong>Total Distance:</strong> ${summary.total_distance_km} km</p>
                        <p class="mb-1"><strong>Average Trip:</strong> ${summary.average_trip_distance_km} km</p>
                        <p class="mb-1"><strong>Avg OptiDrive:</strong> ${summary.average_optidrive} <span class="text-success">(${summary.behaviour_category})</span></p>
                        <p class="mb-0"><strong>Auto-Calculated Category:</strong> <span class="badge badge-info">${categoryLabel[summary.behaviour_category]}</span></p>
                    `;
                    $('#tripSummaryContent').html(content);
                    $('#tripDataSummary').show();
                    
                    // Display trip details in table
                    if (trips && trips.length > 0) {
                        var tableRows = '';
                        trips.forEach(function(trip) {
                            var durationMinutes = Math.round(trip.duration_s / 60);
                            var durationHours = Math.floor(durationMinutes / 60);
                            var durationMins = durationMinutes % 60;
                            var durationStr = durationHours > 0 ? 
                                durationHours + 'h ' + durationMins + 'm' : 
                                durationMins + 'm';
                            
                            var distanceKm = (trip.distance_m / 1000).toFixed(2);
                            var avgSpeed = trip.avg_speed || 'N/A';
                            var startTime = new Date(trip.start_time).toLocaleString();
                            var endTime = new Date(trip.end_time).toLocaleString();
                            var startPos = trip.start_postext || 'N/A';
                            var endPos = trip.end_postext || 'N/A';
                            
                            // Format OptiDrive indicator with color coding
                            var optidrive = trip.optidrive_indicator || 0;
                            var optidriveFormatted = parseFloat(optidrive).toFixed(4);
                            var optidriveClass = '';
                            var optidriveCategory = '';
                            
                            if (optidrive >= 0.8) {
                                optidriveClass = 'text-success font-weight-bold';
                                optidriveCategory = 'A';
                            } else if (optidrive >= 0.6) {
                                optidriveClass = 'text-info font-weight-bold';
                                optidriveCategory = 'B';
                            } else if (optidrive >= 0.4) {
                                optidriveClass = 'text-warning font-weight-bold';
                                optidriveCategory = 'C';
                            } else {
                                optidriveClass = 'text-danger font-weight-bold';
                                optidriveCategory = 'D';
                            }
                            
                            tableRows += `
                                <tr>
                                    <td>${startTime}</td>
                                    <td>${endTime}</td>
                                    <td>${distanceKm} km</td>
                                    <td>${durationStr}</td>
                                    <td>${avgSpeed} km/h</td>
                                    <td class="${optidriveClass}">
                                        ${optidriveFormatted}
                                        <span class="badge badge-sm badge-secondary">${optidriveCategory}</span>
                                    </td>
                                    <td><small>${startPos}</small></td>
                                    <td><small>${endPos}</small></td>
                                </tr>
                            `;
                        });
                        $('#tripDetailsBody').html(tableRows);
                        $('#tripDetailsTable').show();
                    }
                    
                    // Auto-fill distance (hidden field for form submission)
                    $('#distance_km').val(summary.total_distance_km);
                    
                    // Show visible distance display
                    $('#trip_distance_display').val(summary.total_distance_km + ' km');
                    $('#tripDistanceDisplay').show();
                    
                    // Auto-fill behaviour category (it will be auto-calculated on server side, but show for reference)
                    $('#behaviour_category_trip').val(summary.behaviour_category);
                    $('#behaviour_category_trip').prop('readonly', true).addClass('bg-light');
                    
                    // Add notice that category is auto-calculated
                    if (!$('#behaviour_category_trip').next('.form-text').length) {
                        $('#behaviour_category_trip').after('<small class="form-text text-info"><i class="fas fa-robot"></i> Auto-calculated from trip OptiDrive data</small>');
                    }
                    
                    console.log('✓ Trip data fetched - Distance:', summary.total_distance_km, 'km, Category:', summary.behaviour_category, '(OptiDrive:', summary.average_optidrive + ')');
                } else {
                    alert('Error fetching trip data: ' + response.message);
                }
            },
            error: function(xhr) {
                $('#tripDataLoading').hide();
                var message = xhr.responseJSON?.message || 'Error fetching trip data';
                alert(message);
            }
        });
    });

    // Calculate Premium
    $('#calculatorForm').submit(function(e) {
        e.preventDefault();
        
        var formData = $(this).serialize();
        
        // Debug: Log the form data being sent
        var calculationMethod = $('input[name="calculation_method"]:checked').val();
        console.log('=== CALCULATING PREMIUM ===');
        console.log('Calculation Method:', calculationMethod);
        console.log('Distance KM:', $('#distance_km').val());
        console.log('Behaviour Category:', calculationMethod === 'manual' ? $('#behaviour_category').val() : $('#behaviour_category_trip').val());
        console.log('Vehicle Plate:', $('#vehicle_plate').val());
        console.log('Date Range:', $('#start_date').val(), 'to', $('#end_date').val());
        console.log('Form Data:', formData);
        console.log('===========================');
        
        $('#calculationLoading').show();
        $('#resultsSection').hide();
        $('#errorMessage').hide();

        $.ajax({
            url: '{{ route("admin.calculator.calculate") }}',
            type: 'POST',
            data: formData,
            success: function(response) {
                $('#calculationLoading').hide();
                
                if (response.success) {
                    var data = response.data;
                    
                    // Update summary cards
                    $('#result_monthly_premium').text('P ' + data.monthly_premium.toFixed(2));
                    $('#result_base_rate').text('P ' + data.base_rate_per_km.toFixed(4));
                    $('#result_final_rate').text('P ' + data.final_rate_per_km.toFixed(4));
                    $('#result_total_premium').text('P ' + data.total_premium.toFixed(2));
                    
                    // Update detailed breakdown
                    // Show distance with adjustment if applicable
                    var distanceText = '';
                    if (data.distance_adjustment) {
                        distanceText = '<span class="text-muted">Actual: ' + data.actual_distance_km.toFixed(2) + ' km</span><br>' +
                                     '<strong>Billable: ' + data.billable_distance_km.toFixed(2) + ' km</strong>' +
                                     '<br><small class="text-info"><i class="fas fa-info-circle"></i> ' + data.distance_adjustment + '</small>';
                    } else {
                        distanceText = data.distance_km.toFixed(2) + ' km' +
                                     '<br><small class="text-success"><i class="fas fa-check-circle"></i> Within range (' + 
                                     data.distance_range_min + '-' + data.distance_range_max + ' km)</small>';
                    }
                    $('#result_distance').html(distanceText);
                    
                    // Show behaviour category with auto-calculation notice if applicable
                    var categoryText = 'Category ' + data.behaviour_category;
                    if (data.behaviour_category_auto_calculated && data.average_optidrive !== null) {
                        categoryText += '<br><small class="text-info"><i class="fas fa-robot"></i> Auto-calculated from OptiDrive: ' + 
                                      data.average_optidrive.toFixed(4) + '</small>';
                    }
                    $('#result_category').html(categoryText);
                    
                    $('#result_surcharge_pct').text(data.behaviour_surcharge_percentage + '%');
                    $('#result_base_premium').text('P ' + data.base_premium.toFixed(2));
                    $('#result_surcharge_amount').text('P ' + data.surcharge_amount.toFixed(2));
                    $('#result_total_premium_detail').text('P ' + data.total_premium.toFixed(2));
                    
                    // Update step-by-step breakdown
                    var stepsHtml = '';
                    $.each(data.calculation_breakdown, function(key, value) {
                        stepsHtml += '<li>' + value + '</li>';
                    });
                    $('#calculation_steps').html(stepsHtml);
                    
                    // Show results
                    $('#resultsSection').show();
                    
                    // Scroll to results
                    $('html, body').animate({
                        scrollTop: $('#resultsSection').offset().top - 100
                    }, 500);
                } else {
                    showError(response.message);
                }
            },
            error: function(xhr) {
                $('#calculationLoading').hide();
                console.error('Calculation error:', xhr);
                
                var message = 'Error calculating premium';
                
                // Handle validation errors
                if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                    var errors = xhr.responseJSON.errors;
                    var errorMessages = [];
                    
                    $.each(errors, function(field, messages) {
                        errorMessages.push(messages.join(', '));
                    });
                    
                    message = 'Validation Error: ' + errorMessages.join(' | ');
                } else if (xhr.responseJSON && xhr.responseJSON.message) {
                    message = xhr.responseJSON.message;
                }
                
                showError(message);
            }
        });
    });

    // Reset Form
    $('#resetBtn').click(function() {
        $('#calculatorForm')[0].reset();
        $('#resultsSection').hide();
        $('#errorMessage').hide();
        $('#tripDataSummary').hide();
        $('#tripDetailsTable').hide();
        $('#tripDistanceDisplay').hide();
        
        // Reset behaviour category trip field
        $('#behaviour_category_trip').prop('readonly', false).removeClass('bg-light');
        $('#behaviour_category_trip').next('.form-text').remove();
        
        // Reset dropdowns
        $('#make').empty().append('<option value="">Select Make</option>');
        $('#model').empty().append('<option value="">Select Model</option>');
        $('#variant').empty().append('<option value="">Select Variant</option>');
    });

    // Show error message
    function showError(message) {
        $('#errorText').text(message);
        $('#errorMessage').show();
        $('html, body').animate({
            scrollTop: $('#errorMessage').offset().top - 100
        }, 500);
    }
});
</script>

</body>
</html>

