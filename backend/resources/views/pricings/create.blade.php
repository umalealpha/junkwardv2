<!DOCTYPE html>
<html lang="en">
@include('admin.layouts.header')
<link href="{{ asset('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ asset('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}" rel="stylesheet" type="text/css" />

<body class="kt-header--fixed kt-header-mobile--fixed kt-subheader--enabled kt-subheader--transparent kt-aside--enabled kt-aside--fixed kt-aside--minimize kt-page--loading">

<div id="kt_header_mobile" class="kt-header-mobile kt-header-mobile--fixed">
    <div class="kt-header-mobile__logo">
        <a>
            <img alt="Logo" src="{{ asset('images/logo.png') }}"/>
        </a>
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
                <h3 class="kt-subheader__title">Create Pricing</h3>
                <span class="kt-subheader__separator kt-hidden"></span>
                <div class="kt-subheader__breadcrumbs">
                    <a href="{{ route('admin-dashboard') }}" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                    <span class="kt-subheader__breadcrumbs-separator"></span>
                    <a href="{{ route('pricings.index') }}" class="kt-subheader__breadcrumbs-link">Pricings</a>
                    <span class="kt-subheader__breadcrumbs-separator"></span>
                    <span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">Create</span>
                </div>
            </div>
        </div>

        <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
            <div class="kt-portlet">
                <form action="{{ route('pricings.store') }}" method="POST">
                    @csrf
                    <div class="kt-portlet__body">
                        {{-- Validation Errors --}}
                        @if ($errors->any())
                            <div class="alert alert-danger">
                                <ul>
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        {{-- Gender --}}
                        <div class="form-group row">
                            <label class="col-3 col-form-label">Gender</label>
                            <div class="col-8">
                                <select name="gender" class="form-control" required>
                                    <option value="">Select Gender</option>
                                    <option value="Male" {{ old('gender') == 'Male' ? 'selected' : '' }}>Male</option>
                                    <option value="Female" {{ old('gender') == 'Female' ? 'selected' : '' }}>Female</option>
                                </select>
                            </div>
                        </div>

                        {{-- Age From --}}
                        <div class="form-group row">
                            <label class="col-3 col-form-label">Age From</label>
                            <div class="col-8">
                                <input type="number" name="age_from" value="{{ old('age_from') }}" class="form-control" required>
                            </div>
                        </div>

                        {{-- Age To --}}
                        <div class="form-group row">
                            <label class="col-3 col-form-label">Age To</label>
                            <div class="col-8">
                                <input type="number" name="age_to" value="{{ old('age_to') }}" class="form-control" required>
                            </div>
                        </div>

                        {{-- Age --}}
                        {{-- <div class="form-group row">
                            <label class="col-3 col-form-label">Age</label>
                            <div class="col-8">
                                <input type="number" name="age" value="{{ old('age') }}" class="form-control" required>
                            </div>
                        </div> --}}

                        {{-- Product --}}
                        <div class="form-group row">
                            <label class="col-3 col-form-label">Product</label>
                            <div class="col-8">
                                <input type="text" name="product" value="{{ old('product') }}" class="form-control" required>
                            </div>
                        </div>

                        {{-- Adult Dependent --}}
                        <div class="form-group row">
                            <label class="col-3 col-form-label">Adult Dependent</label>
                            <div class="col-8">
                                <input
                                    type="text"
                                    name="adult_dependent"
                                    value="{{ old('adult_dependent', 0) }}"
                                    class="form-control"
                                    pattern="^\d+(\.\d{1,2})?$"
                                    title="Please enter a valid decimal number (up to 2 decimal places)"
                                    required
                                >
                            </div>
                        </div>

                        {{-- Child Dependent --}}
                        <div class="form-group row">
                            <label class="col-3 col-form-label">Child Dependent</label>
                            <div class="col-8">
                                <input
                                    type="text"
                                    name="child_dependent"
                                    value="{{ old('child_dependent', 0) }}"
                                    class="form-control"
                                    pattern="^\d+(\.\d{1,2})?$"
                                    title="Please enter a valid decimal number (up to 2 decimal places)"
                                    required
                                >
                            </div>
                        </div>


                        {{-- Premium --}}
                        {{-- <div class="form-group row">
                            <label class="col-3 col-form-label">Premium</label>
                            <div class="col-8">
                                <input type="number" step="0.01" name="premium" value="{{ old('premium') }}" class="form-control" required>
                            </div>
                        </div> --}}

                        {{-- Premium --}}
                        <div class="form-group row">
                            <label class="col-3 col-form-label">Main</label>
                            <div class="col-8">
                                <input
                                    type="text"
                                    id="main"
                                    name="main"
                                    value="{{ old('premium', 0) }}"
                                    class="form-control"
                                    pattern="^\d+(\.\d{1,2})?$"
                                    title="Please enter a valid decimal number (up to 2 decimal places)"
                                    required
                                >
                            </div>
                        </div>

                        {{-- Total Premium (readonly, auto-calculated) --}}
                        {{-- <div class="form-group row">
                            <label class="col-3 col-form-label">Total Premium</label>
                            <div class="col-8">
                                <input type="number" step="0.01" id="total_premium" name="total_premium" value="{{ old('total_premium') }}" class="form-control" readonly>
                            </div>
                        </div> --}}

                    </div>

                    <div class="kt-portlet__foot kt-portlet__foot--solid">
                        <div class="kt-form__actions">
                            <div class="row">
                                <div class="col-3"></div>
                                <div class="col-9">
                                    <button type="submit" class="btn btn-brand">Save</button>
                                    <a class="btn btn-secondary" href="{{ route('pricings.index') }}">Cancel</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @include('includes.footer')
</div>

@include('admin.layouts.scripts')
<script>
    function calculateTotalPremium() {
        let premium = parseFloat(document.getElementById('premium').value) || 0;
        let adult = parseFloat(document.querySelector('[name="adult_dependent"]').value) || 0;
        let child = parseFloat(document.querySelector('[name="child_dependent"]').value) || 0;

        let total = premium + adult + child;
        document.getElementById('total_premium').value = total.toFixed(2);
    }

    // Attach events
    document.getElementById('premium').addEventListener('input', calculateTotalPremium);
    document.querySelector('[name="adult_dependent"]').addEventListener('input', calculateTotalPremium);
    document.querySelector('[name="child_dependent"]').addEventListener('input', calculateTotalPremium);

    // Run on page load (for edit form case)
    window.onload = calculateTotalPremium;
</script>

</body>
</html>
