<!DOCTYPE html>
<html lang="en">
@include('admin.layouts.header')

<link href="{{ asset('assets/vendors/custom/datatables/datatables.bundle.css') }}" rel="stylesheet" type="text/css" />

<body class="kt-header--fixed kt-header-mobile--fixed kt-subheader--enabled kt-subheader--transparent kt-aside--enabled kt-aside--fixed kt-aside--minimize kt-page--loading">
<div id="kt_header_mobile" class="kt-header-mobile kt-header-mobile--fixed ">
    <div class="kt-header-mobile__logo">
        <a>
            <img alt="Logo" src="{{ asset('images/logo.png') }}"/>
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
        <!-- Subheader -->
        <div class="kt-subheader kt-grid__item" id="kt_subheader">
            <div class="kt-subheader__main">
                <h3 class="kt-subheader__title">Add Employer Group</h3>
                <div class="kt-subheader__breadcrumbs">
                    <a href="{{ route('admin-dashboard') }}" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                    <span class="kt-subheader__breadcrumbs-separator"></span>
                    <a href="{{ route('admin.employer-groups.index') }}" class="kt-subheader__breadcrumbs-link">Employer Groups</a>
                    <span class="kt-subheader__breadcrumbs-separator"></span>
                    <span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">Add Employer Group</span>
                </div>
            </div>
        </div>

        <!-- Content -->
        <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
            {{-- <div class="container" style="max-width: 900px;"> --}}
                <div class="kt-portlet">
                    <div class="kt-portlet__head">
                        <div class="kt-portlet__head-label">
                            <h3 class="kt-portlet__head-title">Employer Group Details</h3>
                        </div>
                    </div>

                    <form action="{{ route('admin.employer-groups.store') }}" method="POST" class="kt-form kt-form--label-right">
                        @csrf
                        <div class="kt-portlet__body">
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label>Employer Group Name</label>
                                        <input type="text" name="name" class="form-control" required>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Industry</label>
                                        <input type="text" name="industry" class="form-control">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Address</label>
                                        <input type="text" name="address" class="form-control">
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Town</label>
                                        <input type="text" name="town" class="form-control">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Postal Code</label>
                                        <input type="text" name="postal_code" class="form-control">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Contact Name</label>
                                        <input type="text" name="contact_name" class="form-control">
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Contact Phone</label>
                                        <input type="text" name="contact_phone" class="form-control">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Contact Email</label>
                                        <input type="email" name="contact_email" class="form-control">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                               <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Payment Method</label>
                                        <input type="text" name="payment_method" class="form-control">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Primary Agent Name</label>
                                        <input type="text" name="broker" class="form-control">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>No. of Employees</label>
                                        <input type="number" name="no_of_employees" class="form-control">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Status</label>
                                        <select name="status" class="form-control">
                                            <option value="Active">Active</option>
                                            <option value="Suspended">Suspended</option>
                                            <option value="Cancelled">Cancelled</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label>HR Emails</label>
                                        <textarea name="bulk_emails" class="form-control" placeholder="Enter emails separated by commas or new lines"></textarea>
                                        <small class="form-text text-muted">You can add multiple emails separated by commas or new lines for sending bulk emails.</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="kt-portlet__foot text-center">
                            <button type="submit" class="btn btn-primary">Save</button>
                            <a href="{{ route('admin.employer-groups.index') }}" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            {{-- </div> --}}
        </div>
    </div>

    @include('includes.footer')
</div>

@include('admin.layouts.scripts')
</body>
</html>
