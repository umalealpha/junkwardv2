<!DOCTYPE html>
<html lang="en" >

@include('admin.layouts.header')
<link href="{{  asset('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ asset('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ asset('assets/vendors/general/bootstrap-datetime-picker/css/bootstrap-datetimepicker.css')}}" rel="stylesheet" type="text/css" />
<link href="{{ asset('assets/vendors/general/bootstrap-timepicker/css/bootstrap-timepicker.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ asset('assets/vendors/general/bootstrap-daterangepicker/daterangepicker.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ asset('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}" rel="stylesheet" type="text/css" />

<!-- begin::Body -->
<body class="kt-header--fixed kt-header-mobile--fixed kt-subheader--enabled kt-subheader--transparent kt-aside--enabled kt-aside--fixed kt-aside--minimize kt-page--loading" >
<!-- begin:: Header Mobile -->
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
                    View Customer
                </h3>
                <span class="kt-subheader__separator kt-hidden"></span>
                <div class="kt-subheader__breadcrumbs">
                    <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                    <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin-dashboard')}}" class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{route('admin.customer.index')}}" class="kt-subheader__breadcrumbs-link"> Customer </a> <span class="kt-subheader__breadcrumbs-separator"></span>
                    <span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">View</span>
                </div>
            </div>
        </div>
        <!-- end:: Subheader -->
        <!-- begin:: Content -->
        <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
            <!--begin::Portlet-->
            <div class="kt-portlet">
                <!--begin::Form-->
                    <div class="kt-portlet__body">
                        <div class="kt-section">
                            <div class="kt-section__content">
                                <table class="table table-striped m-table">
                                    <tbody>
                                    @if($customerProfile->entity_type == 'Organisation')
                                        <tr>
                                            <th>Organisation Name:</th>
                                            @if($customerProfile != NULL)
                                                <td>{!! $customerProfile->org_name !!}</td>
                                            @else
                                                <td>Unavailable</td>
                                            @endif
                                        </tr>
                                        <tr>
                                            <th>VAT Registration Number:</th>
                                            @if($customerProfile != NULL)
                                                <td>{!! $customerProfile->vat_reg !!}</td>
                                            @else
                                                <td>Unavailable</td>
                                            @endif
                                        </tr>
                                        <tr>
                                            <th>Company Registration Number:</th>
                                            @if($customerProfile != NULL)
                                                <td>{!! $customerProfile->reg_no !!}</td>
                                            @else
                                                <td>Unavailable</td>
                                            @endif
                                        </tr>
                                    @else
                                        <tr>
                                            <th>First Name:</th>
                                            @if($customer != NULL)
                                                <td>{!! $customer->firstName !!}</td>
                                            @else
                                                <td>Unavailable</td>
                                            @endif

                                        </tr>
                                        <tr>
                                            <th>Last Name:</th>
                                            @if($customer != NULL)
                                                <td>{!! $customer->lastName !!}</td>
                                            @else
                                                <td>Unavailable</td>
                                            @endif
                                        </tr>
                                    @endif
                                    
                                    <tr>
                                        <th>Email:</th>
                                        @if($customer != NULL)
                                            <td>{!! \AlphaDirect\Helpers\PiiMask::ifEmail($customer->email) !!}</td>
                                        @else
                                            <td>Unavailable</td>
                                        @endif

                                    </tr>
                                    <tr>
                                        <th>Date of Birth:</th>
                                        @if($customer != NULL)
                                            <td>{!! \AlphaDirect\Helpers\PiiMask::ifHidden($customerProfile->dob) !!}</td>
                                        @else
                                            <td>Date of birth Unavailable</td>
                                        @endif
                                    </tr>
                                    <tr>
                                        <th>Address:</th>
                                        @if($customer != NULL)
                                            <td>{!! \AlphaDirect\Helpers\PiiMask::ifHidden($customerProfile->address) !!}</td>
                                        @else
                                            <td>Unavailable</td>
                                        @endif
                                    </tr>
                                    <tr>
                                        <th>Omang:</th>
                                        @if($customerProfile->omang != NULL)
                                            <td>{!! \AlphaDirect\Helpers\PiiMask::ifId($customerProfile->omang) !!}</td>
                                        @else
                                            <td>-</td>
                                        @endif

                                    </tr>

                                    <tr>
                                        <th>Passport:</th>
                                        @if($customer != NULL)
                                            <td>{!! \AlphaDirect\Helpers\PiiMask::ifId($customerProfile->passport) !!}</td>
                                        @else
                                            <td>Unavailable</td>
                                        @endif
                                    </tr>
                                    <tr>
                                        <th>Mobile Number:</th>
                                        @if($customer != NULL)
                                            <td>{!! $customer->cellphone !!}</td>
                                        @else
                                            <td>Unavailable</td>
                                        @endif
                                    </tr>
                                    <tr>
                                        <th>Gender:</th>
                                        @if($customer != NULL)
                                            @if($customerProfile->gender == '1')
                                                <td>Male</td>
                                            @elseif($customerProfile->gender == '0')
                                                <td>Female</td>
                                            @else
                                                <td>Unavailable</td>
                                            @endif

                                        @else
                                            <td>Unavailable</td>
                                        @endif
                                    </tr>


                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>
                        <h3 class="kt-heading kt-heading--md">
                            Customer KYC
                        </h3>
                        <div class="form-group row">

                            <div class="col-md-2">
                                <h3 class="col-form-label" style="float: left;">Driving License</h3>
                                <div class="kt-avatar" id="driversLicense" style="float: left; clear: left;">
                                @if($kyc == NULL)  <!-- first check if kyc object is null, if null display default image-->
                                    <div class="kt-avatar__holder" style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                                @else  <!--If not null, get the property -->
                                @if($kyc->driving_license == null)    <!-- Check if the property is null, if null display default image -->
                                    <div class="kt-avatar__holder" style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                                @else  <!-- If not null display the the image from S3 -->
                                    <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($kyc->driving_license) !!}" target="_blank" download>
                                        <div class="kt-avatar__holder" style="background-image: url({!! \AlphaDirect\Helper::getCloudFrontURL($kyc->driving_license) !!})"></div>
                                    </a>
                                    @endif
                                    @endif

                                </div>
                            </div>


                            <div class="col-md-2">
                                <h3 class="col-form-label" style="float: left;">Omang Id</h3>
                                <div class="kt-avatar" id="omang" style="float: left; clear: left;">
                                    @if($kyc == NULL)
                                        <div class="kt-avatar__holder" style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                                    @else
                                        @if($kyc->omang == NULL)
                                            <div class="kt-avatar__holder" style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                                        @else
                                            <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($kyc->omang) !!}" target="_blank" download>
                                                <div class="kt-avatar__holder" style="background-image: url({!! \AlphaDirect\Helper::getCloudFrontURL($kyc->omang) !!})"></div>
                                            </a>
                                        @endif
                                    @endif

                                </div>
                            </div>


                            <div class="col-md-2">
                                <h3 class="col-form-label" style="float: left;">Proof of Residence</h3>
                                <div class="kt-avatar" id="proofResidence" style="float: left; clear: left;">
                                @if($kyc == NULL) <!-- first check if kyc object is null, if null display default image-->
                                    <div class="kt-avatar__holder" style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                                @else  <!--If not null, get the Proof of residence property and check  -->
                                @if($kyc->proof_residence == NULL)    <!-- Check if it is null, if null display default image -->
                                    <div class="kt-avatar__holder" style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                                @else  <!-- If not null display the the image from S3 -->
                                    <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($kyc->proof_residence) !!}" target="_blank" download>
                                        <div class="kt-avatar__holder" style="background-image: url({!! \AlphaDirect\Helper::getCloudFrontURL($kyc->proof_residence) !!})"></div>
                                    </a>
                                    @endif
                                    @endif

                                </div>
                            </div>



                            <div class="col-md-2">
                                <h3 class="col-form-label" style="float: left;">Proof of Income</h3>
                                <div class="kt-avatar" id="proofIncome" style="float: left; clear: left;">
                                    @if($kyc == NULL)
                                        <div class="kt-avatar__holder" style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                                    @else
                                        @if($kyc->proof_income == NULL)
                                            <div class="kt-avatar__holder" style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                                        @else
                                            <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($kyc->proof_income) !!}" target="_blank" download>
                                                <div class="kt-avatar__holder" style="background-image: url({!! \AlphaDirect\Helper::getCloudFrontURL($kyc->proof_income) !!})"></div>
                                            </a>
                                        @endif

                                    @endif

                                </div>
                            </div>


                            <div class="col-md-2">
                                <h3 class="col-form-label" style="float: left;">Passport</h3>
                                <div class="kt-avatar" id="passport" style="float: left; clear: left;">
                                    @if($kyc == null)
                                        <div class="kt-avatar__holder" style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                                    @else
                                        @if($kyc->passport == null)
                                            <div class="kt-avatar__holder" style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                                        @else
                                            <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($kyc->passport) !!}" target="_blank" download>
                                                <div class="kt-avatar__holder" style="background-image: url({!! \AlphaDirect\Helper::getCloudFrontURL($kyc->passport) !!})"></div>
                                            </a>
                                        @endif
                                    @endif

                                </div>
                            </div>

                        </div>

                        <div class="kt-portlet__foot kt-portlet__foot--solid">
                            <div class="kt-form__actions">
                                <div class="row">
                                    <div class="col-3"></div>


                                    <div class="col-9">
                                        @can('customer-edit')
                                            <button type="submit" id="btn"  class="btn btn-brand">Update</button>
                                        @endcan
                                        <button class="btn btn-brand" type="button" id="loadBtn" style="display:none" > <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Loading... </button>
                                        <a class="btn btn-secondary" href="{{ route('admin.customer.index') }}" >Back</a>
                                    </div>


                                </div>
                            </div>
                        </div>
                    </div>

                <!--end::Portlet-->
            </div>
            <!-- end:: Content -->
        </div>

        <!-- begin:: Footer -->
    @include('includes.footer')
    <!-- end:: Footer -->
    </div>
    <!-- end:: Wrapper -->
</div>
<!-- end:: Page -->
<!-- end:: Root -->



<!-- begin:: Scrolltop -->
<div id="kt_scrolltop" class="kt-scrolltop"> <i class="la la-arrow-up"></i> </div>
<!-- end:: Scrolltop -->

@include('admin.layouts.scripts')






</body>
<!-- end::Body -->
</html>
