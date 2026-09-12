<!DOCTYPE html>
<html lang="en" >
@include('admin.layouts.header')
<link href="{{ asset('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ asset('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}" rel="stylesheet" type="text/css" />
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
    <div class="kt-grid__item kt-grid__item--fluid kt-grid--hor">
        <div class="kt-subheader kt-grid__item" id="kt_subheader">
            <div class="kt-subheader__main">
                <h3 class="kt-subheader__title">Add Customer Reward</h3>
                <span class="kt-subheader__separator kt-hidden"></span>
                <div class="kt-subheader__breadcrumbs">
                    <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                    <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin-dashboard')}}" class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span class="kt-subheader__breadcrumbs-separator"></span>
                    <span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">Add Customer Reward</span>
                </div>
            </div>
        </div>
        <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
            <div class="kt-portlet kt-portlet--mobile">
                <div class="kt-portlet__body">
                    <form class="kt-form" method="POST" action="{{ route('customer-rewards.store') }}">
                        @csrf
                        <div class="form-group row">
                            <label class="col-3 col-form-label">Customer ID</label>
                            <div class="col-8">
                                <input type="text" class="form-control" name="customer_id" value="{{ old('customer_id') }}" placeholder="Enter customer ID" required>
                            </div>
                        </div>
                        <div class="form-group row">
                            <label class="col-3 col-form-label">Benefit</label>
                            <div class="col-8">
                                <select class="form-control" name="benefit_id" required>
                                    <option value="">Select benefit</option>
                                    @foreach($benefits as $benefit)
                                        <option value="{{ $benefit->id }}" {{ old('benefit_id') == $benefit->id ? 'selected' : '' }}>{{ $benefit->tag }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="form-group row">
                            <label class="col-3 col-form-label">Status</label>
                            <div class="col-8">
                                <select class="form-control" name="status" id="status-select" required>
                                    <option value="active" {{ old('status') == 'active' ? 'selected' : '' }}>Active</option>
                                    <option value="claimed" {{ old('status') == 'claimed' ? 'selected' : '' }}>Claimed</option>
                                    <option value="expired" {{ old('status') == 'expired' ? 'selected' : '' }}>Expired</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group row" id="claim-date-row" style="display: none;">
                            <label class="col-3 col-form-label">Claim Date</label>
                            <div class="col-8">
                                <input type="date" class="form-control" name="claim_date" value="{{ old('claim_date') }}">
                            </div>
                        </div>
                        <div class="form-group row">
                            <label class="col-3 col-form-label">Expiry Date</label>
                            <div class="col-8">
                                <input type="date" class="form-control" name="expiry_date" value="{{ old('expiry_date') }}">
                            </div>
                        </div>
                        <div class="form-group row">
                            <div class="col-8 offset-3">
                                <button type="submit" class="btn btn-success">Create</button>
                                <a href="{{ route('customer-rewards.index') }}" class="btn btn-secondary">Cancel</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    @include('includes.footer')
</div>
@include('admin.layouts.scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        function toggleClaimDate() {
            var status = document.getElementById('status-select').value;
            document.getElementById('claim-date-row').style.display = (status === 'claimed') ? '' : 'none';
        }
        document.getElementById('status-select').addEventListener('change', toggleClaimDate);
        toggleClaimDate();
    });
</script>
</body>
</html> 