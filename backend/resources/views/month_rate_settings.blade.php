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
    <div class="kt-grid__item kt-grid__item--fluid kt-grid kt-grid--hor">
        <!-- begin:: Subheader -->
        <div class="kt-subheader kt-grid__item" id="kt_subheader">
            <div class="kt-subheader__main">
                <h3 class="kt-subheader__title">
                    Month Rate Settings
                </h3>
                <span class="kt-subheader__separator kt-hidden"></span>
                <div class="kt-subheader__breadcrumbs">
                    <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                    <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin-dashboard')}}" class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span class="kt-subheader__breadcrumbs-separator"></span>Config
                    <span class="kt-subheader__breadcrumbs-separator"></span>Month Rate Settings
                </div>
            </div>
        </div>
        <!-- end:: Subheader -->

        <!-- begin:: Content -->
        <div class="kt-content kt-grid__item kt-grid__item--fluid">

            <!--Begin::App-->
            <div class="kt-grid kt-grid--desktop kt-grid--ver kt-grid--ver-desktop kt-app">

                <!--Begin:: App Content-->
                <div class="kt-grid__item kt-grid__item--fluid kt-app__content">
                    <div class="row">
                        <div class="col-lg-12">

                            <!--begin::Portlet-->
                            <div class="kt-portlet kt-portlet--last kt-portlet--head-lg kt-portlet--responsive-mobile">
                                <div class="kt-portlet__head">
                                    <div class="kt-portlet__head-label">
                                        <h3 class="kt-portlet__head-title">Month Rate Settings Configuration</h3>
                                    </div>
                                    <div class="kt-portlet__head-toolbar">
                                        <div class="kt-portlet__head-wrapper">
                                            <div class="kt-portlet__head-actions">
                                                @if($data->isEmpty())
                                                <form action="{{ route('admin.initialize-default-month-rates') }}" method="POST" style="display: inline;">
                                                    @csrf
                                                    <button type="submit" class="btn btn-success btn-sm">
                                                        <i class="fa fa-plus"></i> Initialize Default Rates
                                                    </button>
                                                </form>
                                                @endif
                                                <button type="button" class="btn btn-primary btn-sm" onclick="addNewRate()">
                                                    <i class="fa fa-plus"></i> Add New Rate
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="kt-portlet__body">

                                    @if(session('success'))
                                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                                            {{ session('success') }}
                                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                                <span aria-hidden="true">&times;</span>
                                            </button>
                                        </div>
                                    @endif

                                    @if(session('error'))
                                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                            {{ session('error') }}
                                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                                <span aria-hidden="true">&times;</span>
                                            </button>
                                        </div>
                                    @endif

                                    @if(session('info'))
                                        <div class="alert alert-info alert-dismissible fade show" role="alert">
                                            {{ session('info') }}
                                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                                <span aria-hidden="true">&times;</span>
                                            </button>
                                        </div>
                                    @endif

                                    <form action="{{ route('admin.store-month-rate-setting') }}" method="POST" id="monthRateForm">
                                        @csrf
                                        
                                        <div class="alert alert-info">
                                            <h5><i class="icon fa fa-info"></i> Settings Information!</h5>
                                            Configure percentage rates based on month ranges. These rates will be applied based on the number of months specified.
                                            <br><strong>Example:</strong> 24-36 months = 2%, 36-48 months = 5%, etc.
                                        </div>

                                        <div id="ratesContainer">
                                            @if($data->isNotEmpty())
                                                @foreach($data as $index => $rate)
                                                <div class="rate-item border p-3 mb-3 rounded">
                                                    <div class="row">
                                                        <div class="col-md-2">
                                                            <div class="form-group">
                                                                <label>Min Months <span class="text-danger">*</span></label>
                                                                <input type="number" name="rates[{{$index}}][min_months]" 
                                                                       class="form-control" value="{{ $rate->min_months }}" required min="0">
                                                            </div>
                                                        </div>
                                                        <div class="col-md-2">
                                                            <div class="form-group">
                                                                <label>Max Months</label>
                                                                <input type="number" name="rates[{{$index}}][max_months]" 
                                                                       class="form-control" value="{{ $rate->max_months }}" min="1"
                                                                       placeholder="Leave empty for no limit">
                                                            </div>
                                                        </div>
                                                        <div class="col-md-2">
                                                            <div class="form-group">
                                                                <label>Rate (%) <span class="text-danger">*</span></label>
                                                                <input type="number" step="0.01" name="rates[{{$index}}][rate_percentage]" 
                                                                       class="form-control" value="{{ $rate->rate_percentage }}" required min="0" max="100">
                                                            </div>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <div class="form-group">
                                                                <label>Description</label>
                                                                <input type="text" name="rates[{{$index}}][description]" 
                                                                       class="form-control" value="{{ $rate->description }}"
                                                                       placeholder="Optional description">
                                                            </div>
                                                        </div>
                                                        <div class="col-md-2">
                                                            <div class="form-group">
                                                                <label>Active</label>
                                                                <div class="kt-checkbox-inline">
                                                                    <label class="kt-checkbox">
                                                                        <input type="checkbox" name="rates[{{$index}}][is_active]" 
                                                                               value="1" {{ $rate->is_active ? 'checked' : '' }}>
                                                                        <span></span>
                                                                    </label>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-1">
                                                            <div class="form-group">
                                                                <label>&nbsp;</label>
                                                                <button type="button" class="btn btn-danger btn-sm form-control" onclick="removeRate(this)">
                                                                    <i class="fa fa-trash"></i>
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                @endforeach
                                            @else
                                                <div class="alert alert-warning">
                                                    <h5><i class="icon fa fa-warning"></i> No Rates Configured!</h5>
                                                    No month rate settings have been configured yet. Click "Initialize Default Rates" to set up the default configuration or "Add New Rate" to create custom rates.
                                                </div>
                                            @endif
                                        </div>

                                        @if($data->isNotEmpty())
                                        <div class="form-group mt-4">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fa fa-save"></i> Save Settings
                                            </button>
                                            <button type="button" class="btn btn-secondary" onclick="window.location.reload()">
                                                <i class="fa fa-refresh"></i> Reset
                                            </button>
                                        </div>
                                        @endif
                                    </form>

                                </div>
                            </div>
                            <!--end::Portlet-->

                        </div>
                    </div>
                </div>
                <!--End:: App Content-->

            </div>
            <!--End::App-->

        </div>
        <!-- end:: Content -->
    </div>
</div>
<!-- end:: Page -->

@include('admin.layouts.scripts')

<script>
let rateIndex = {{ $data->count() }};

function addNewRate() {
    const container = document.getElementById('ratesContainer');
    const rateItem = document.createElement('div');
    rateItem.className = 'rate-item border p-3 mb-3 rounded';
    rateItem.innerHTML = `
        <div class="row">
            <div class="col-md-2">
                <div class="form-group">
                    <label>Min Months <span class="text-danger">*</span></label>
                    <input type="number" name="rates[${rateIndex}][min_months]" 
                           class="form-control" required min="0">
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-group">
                    <label>Max Months</label>
                    <input type="number" name="rates[${rateIndex}][max_months]" 
                           class="form-control" min="1" placeholder="Leave empty for no limit">
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-group">
                    <label>Rate (%) <span class="text-danger">*</span></label>
                    <input type="number" step="0.01" name="rates[${rateIndex}][rate_percentage]" 
                           class="form-control" required min="0" max="100">
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <label>Description</label>
                    <input type="text" name="rates[${rateIndex}][description]" 
                           class="form-control" placeholder="Optional description">
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-group">
                    <label>Active</label>
                    <div class="kt-checkbox-inline">
                        <label class="kt-checkbox">
                            <input type="checkbox" name="rates[${rateIndex}][is_active]" value="1" checked>
                            <span></span>
                        </label>
                    </div>
                </div>
            </div>
            <div class="col-md-1">
                <div class="form-group">
                    <label>&nbsp;</label>
                    <button type="button" class="btn btn-danger btn-sm form-control" onclick="removeRate(this)">
                        <i class="fa fa-trash"></i>
                    </button>
                </div>
            </div>
        </div>
    `;
    
    container.appendChild(rateItem);
    rateIndex++;
    
    // Show the save button if it was hidden
    const formGroup = document.querySelector('.form-group.mt-4');
    if (formGroup) {
        formGroup.style.display = 'block';
    }
}

function removeRate(button) {
    const rateItem = button.closest('.rate-item');
    rateItem.remove();
    
    // Hide save button if no rates left
    const remainingRates = document.querySelectorAll('.rate-item');
    if (remainingRates.length === 0) {
        const formGroup = document.querySelector('.form-group.mt-4');
        if (formGroup) {
            formGroup.style.display = 'none';
        }
    }
}

// Form validation before submit
document.getElementById('monthRateForm').addEventListener('submit', function(e) {
    const rateItems = document.querySelectorAll('.rate-item');
    
    for (let i = 0; i < rateItems.length; i++) {
        const minMonths = rateItems[i].querySelector('input[name*="[min_months]"]').value;
        const maxMonths = rateItems[i].querySelector('input[name*="[max_months]"]').value;
        const rate = rateItems[i].querySelector('input[name*="[rate_percentage]"]').value;
        
        if (!minMonths || !rate) {
            e.preventDefault();
            alert('Please fill in all required fields (Min Months and Rate).');
            return;
        }
        
        if (maxMonths && parseInt(maxMonths) <= parseInt(minMonths)) {
            e.preventDefault();
            alert('Max Months must be greater than Min Months.');
            return;
        }
    }
});
</script>

</body>
<!-- end::Body -->
</html>