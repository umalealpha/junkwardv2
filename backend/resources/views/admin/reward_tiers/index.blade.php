<!DOCTYPE html>
<html lang="en" >

@include('admin.layouts.header')
<link href="{{  asset('assets/vendors/custom/datatables/datatables.bundle.css') }}" rel="stylesheet" type="text/css" />
<link href="{{  asset('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css') }}" rel="stylesheet" type="text/css" />
<link href="{{  asset('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}" rel="stylesheet" type="text/css" />

<body class="kt-header--fixed kt-header-mobile--fixed kt-subheader--enabled kt-subheader--transparent kt-aside--enabled kt-aside--fixed kt-aside--minimize kt-page--loading" >
<div id="kt_header_mobile" class="kt-header-mobile kt-header-mobile--fixed " >
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
<div class="kt-grid kt-grid--hor kt-grid--root">
    <div class="kt-grid__item kt-grid__item--fluid kt-grid kt-grid--ver kt-page">
        @include('admin.layouts.sidebar')
        @include('admin.layouts.topNav')
    </div>
    <div class="kt-grid__item kt-grid__item--fluid kt-grid kt-grid--hor">
        <div class="kt-subheader kt-grid__item" id="kt_subheader">
            <div class="kt-subheader__main">
                <h3 class="kt-subheader__title">Reward Tiers List</h3>
                <span class="kt-subheader__separator kt-hidden"></span>
                <div class="kt-subheader__breadcrumbs">
                    <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                    <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin-dashboard')}}" class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span class="kt-subheader__breadcrumbs-separator"></span>
                    <span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">Reward Tiers</span>
                </div>
            </div>
            <div class="kt-subheader__toolbar">
                <div class="kt-subheader__wrapper">
                    <a href="{{ route('reward-tiers.create') }}" class="btn btn-sm btn-elevate btn-brand btn-elevate" data-toggle="kt-tooltip" title="Add Reward Tier" data-placement="left"> <span class="kt-opacity-11">Add Reward Tier</span>&nbsp; <i class="flaticon2-add-1 kt-padding-l-5 kt-padding-r-0"></i> </a>
                </div>
            </div>
        </div>
        <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
            <!-- begin:: Expiry Days Configuration -->
            <div class="kt-portlet">
                <div class="kt-portlet__head">
                    <div class="kt-portlet__head-label">
                        <h3 class="kt-portlet__head-title">
                            Customer Points Expiry Configuration
                        </h3>
                    </div>
                </div>
                <div class="kt-portlet__body">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Current Expiry Days: <span id="current_expiry_days" class="kt-font-bold kt-font-success">Loading...</span> days</label>
                                <div class="kt-input-icon">
                                    <input type="number" class="form-control" id="expiry_days_input" placeholder="Enter expiry days" min="1" max="3650">
                                    <span class="kt-input-icon__icon kt-input-icon__icon--right">
                                        <span><i class="la la-calendar"></i></span>
                                    </span>
                                </div>
                                <span class="form-text text-muted">Enter the number of days after which customer points will expire (1-3650 days)</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>&nbsp;</label>
                                <div>
                                    <button type="button" class="btn btn-brand" id="update_expiry_days_btn">
                                        <i class="la la-save"></i>
                                        Update Expiry Days
                                    </button>
                                    <button type="button" class="btn btn-secondary ml-2" id="reset_expiry_days_btn">
                                        <i class="la la-refresh"></i>
                                        Reset
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- end:: Expiry Days Configuration -->

            <div class="kt-portlet kt-portlet--mobile">
                <div class="kt-portlet__body">
                    <table class="table table-striped table-bordered table-hover table-checkable" id="reward_tiers_table">
                        <thead>
                        <tr>
                            <th>Image</th>
                            <th>Name</th>
                            <th>Label</th>
                            <th>Description</th>
                            <th>Number of Months (condition1)</th>
                            <th>Is Bundled (condition2)</th>
                            <th>Is DomCom (condition3)</th>
                            <th>Multi Policy Holder (condition4)</th>
                            <th>Level Point</th>
                            <th>Status</th>
                            <th style="min-width: 180px;">Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($tiers as $tier)
                            <tr>
                                <td>
                                    @if($tier->image)
                                        <img src="{!! \AlphaDirect\Helper::getCloudFrontURL($tier->image) !!}" alt="{{ $tier->name }}" style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px;">
                                    @else
                                        <div style="width: 50px; height: 50px; background-color: #f5f5f5; border-radius: 4px; display: flex; align-items: center; justify-content: center; color: #999;">
                                            <i class="fa fa-image"></i>
                                        </div>
                                    @endif
                                </td>
                                <td>{{ $tier->name }}</td>
                                <td>{{ $tier->label }}</td>
                                <td>{{ $tier->description }}</td>
                                <td>{{ $tier->condition1 }}</td>
                                <td>{{ $tier->condition2 == 1 ? 'Yes' : 'No' }}</td>
                                <td>{{ $tier->condition3 == 1 ? 'Yes' : 'No' }}</td>
                                <td>{{ $tier->condition4 == 1 ? 'Yes' : 'No' }}</td>
                                <td>{{ $tier->level_point }}</td>
                                <td>
                                    @if($tier->status == 1)
                                        <span class="badge badge-success">Active</span>
                                    @else
                                        <span class="badge badge-danger">Inactive</span>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route('reward-tiers.edit', $tier->id) }}" class="btn btn-warning btn-sm">Edit</a>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    @include('includes.footer')
</div>
@include('admin.layouts.scripts')
<script src="{{  asset('assets/vendors/custom/datatables/datatables.bundle.js') }}" type="text/javascript"></script>
<script>
    $(document).ready(function() {
        $('#reward_tiers_table').DataTable();
        initExpiryDaysManagement();
    });

    // Expiry Days Management Functions
    function initExpiryDaysManagement() {
        console.log('Initializing expiry days management...');
        loadCurrentExpiryDays();
        
        // Update expiry days button click
        $('#update_expiry_days_btn').on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            console.log('Update button clicked');
            updateExpiryDays();
            return false;
        });
        
        // Reset button click
        $('#reset_expiry_days_btn').on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            console.log('Reset button clicked');
            loadCurrentExpiryDays();
            return false;
        });
        
        // Enter key press in input field
        $('#expiry_days_input').on('keypress', function(e) {
            if (e.which === 13) { // Enter key
                e.preventDefault();
                console.log('Enter key pressed');
                updateExpiryDays();
                return false;
            }
        });
        
        console.log('Expiry days management initialized');
    }

    function loadCurrentExpiryDays() {
        console.log('Loading current expiry days...');
        $.ajax({
            url: '{{ route("reward-tiers.expiry-days") }}',
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                console.log('Load success response:', response);
                $('#current_expiry_days').text(response.expiry_days);
                $('#expiry_days_input').val(response.expiry_days);
            },
            error: function(xhr, status, error) {
                console.error('Error loading expiry days:', xhr, status, error);
                $('#current_expiry_days').text('Error');
                if (typeof toastr !== 'undefined') {
                    toastr.error('Failed to load current expiry days');
                }
            }
        });
    }

    function updateExpiryDays() {
        var expiryDays = $('#expiry_days_input').val();
        
        // Debugging
        console.log('updateExpiryDays called with value:', expiryDays);
        
        // Validation
        if (!expiryDays || expiryDays < 1 || expiryDays > 3650) {
            if (typeof toastr !== 'undefined') {
                toastr.error('Please enter a valid number of days between 1 and 3650');
            }
            return;
        }
        
        // Disable button and show loading
        var $btn = $('#update_expiry_days_btn');
        var originalText = $btn.html();
        $btn.prop('disabled', true).html('<i class="la la-spinner la-spin"></i> Updating...');
        
        $.ajax({
            url: '{{ route("reward-tiers.update-expiry-days") }}',
            type: 'POST',
            data: {
                expiry_days: expiryDays,
                _token: '{{ csrf_token() }}'
            },
            dataType: 'json',
            success: function(response) {
                console.log('Success response:', response);
                if (response.success) {
                    $('#current_expiry_days').text(response.expiry_days);
                    if (typeof toastr !== 'undefined') {
                        toastr.success(response.message);
                    }
                } else {
                    if (typeof toastr !== 'undefined') {
                        toastr.error(response.message || 'Failed to update expiry days');
                    }
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', xhr, status, error);
                var errorMessage = 'Failed to update expiry days';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = xhr.responseJSON.message;
                }
                if (typeof toastr !== 'undefined') {
                    toastr.error(errorMessage);
                }
            },
            complete: function() {
                // Re-enable button and restore text
                $btn.prop('disabled', false).html(originalText);
            }
        });
    }
</script>
</body>
</html> 