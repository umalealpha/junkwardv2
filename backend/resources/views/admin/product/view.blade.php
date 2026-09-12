<!DOCTYPE html>
<html lang="en" >



@include('admin.layouts.header')
<link href="{{  asset('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css') }}" rel="stylesheet" type="text/css" />

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
    <!-- check if is first time login -->

    <div class="kt-grid__item kt-grid__item--fluid kt-grid kt-grid--hor">
        <!-- begin:: Subheader -->
        <div class="kt-subheader kt-grid__item" id="kt_subheader">
            <div class="kt-subheader__main">
                <h3 class="kt-subheader__title">
                    View Product
                </h3>
                <span class="kt-subheader__separator kt-hidden"></span>
                <div class="kt-subheader__breadcrumbs">
                    <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                    <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin-dashboard')}}" class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin.product.index')}}" class="kt-subheader__breadcrumbs-link">Product </a> <span class="kt-subheader__breadcrumbs-separator"></span>
                    <span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">View</span>
                </div>
            </div>
        </div>
        <!-- end:: Subheader -->
        <!-- begin:: Content -->
        <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
            <!--begin::Portlet-->
            <div class="kt-portlet">
                    <div class="kt-portlet__body">

                        <table class="table table-striped m-table">
                            <tbody>
                            <tr>
                                <th>Product Name</th>
                                <td>{!! $products->name !!}</td>
                            </tr>
                            <tr>
                                <th>Premium Type</th>
                                @foreach($premiumType as $type)
                                    @if($products->premium_type_id == $type->id)
                                <td>{{$type->value}}</td>
                                    @endif
                                    @endforeach
                            </tr>

                            <tr>
                                <th>Product Name</th>
                                <td>{{ $products->name }}</td>
                            </tr>
                            <tr>
                                <th>Premium Type</th>
                                @foreach($premiumType as $type)
                                    @if($products->premium_type_id == $type->id)
                                <td>{{$type->value}}</td>
                                    @endif
                                    @endforeach
                            </tr>
                            <tr>
                                <th>Product Type</th>
                                @foreach($productType as $productTypes)
                                    @if($products->product_type_id == $productTypes->id)
                                        <td>{{$productTypes->name}}</td>
                                    @endif
                                @endforeach
                            </tr>
                            <tr>
                                <th>Billing Cycle</th>
                                @foreach($billingCycles as $billingCycle)
                                    @if($products->billing_cycle == $billingCycle->value)
                                        <td>{{$billingCycle->value}}</td>
                                    @endif
                                @endforeach
                            </tr>
                            <tr><th>Sum Insured/Asssured</th>
                            <td>{!! $products->sum_insured !!}</td>
                            </tr>
                            <tr>
                                <th>Region</th>
                                @foreach($regions as $region)
                                    @if($products->region_id == $region->id)
                                        <td>{{$region->name}}</td>
                                    @endif
                                @endforeach
                            </tr>
                            <tr>
                                <th>Kyc Compliance</th>
                                @isset($kycCompliance)
                                    @foreach($kycCompliance as $kycCompliance)
                                        @if($products->kyc_compliance == $kycCompliance->id)
                                            <td>{{$kycCompliance->name}}</td>
                                        @endif
                                    @endforeach
                                @endisset
                            </tr>
                            <tr>
                                <th>KYC for Customer</th>
                                        @if($products->kyc_customer)
                                            <td>Yes</td>
                                        @else
                                        <td>No</td>
                                    @endif
                            </tr>

                            <tr>
                                <th>KYC for Recipient</th>
                                @if($products->kyc_recipient)
                                    <td>Yes</td>
                                        @else
                                    <td>No</td>
                                        @endif
                            </tr>
                            <tr>
                                <th>Vehicle Insurance</th>
                                        @if($products->has_vehicle)
                                    <td>Yes</td>
                                        @else
                                    <td>No</td>
                                        @endif
                            </tr>
                            <tr>
                                <th>Coverages</th>
                                            @foreach($coverages as $coverage)
                                    @if(in_array($coverage->id, $prodCov))
                                        <td>{!! $coverage->name !!}</td>
                                                @endif
                                            @endforeach
                            </tr>
                            <tr>
                                <th>Life Insurance</th>
                                        @if($products->has_member)
                                            <td>Yes</td>
                                        @else
                                    <td>No</td>
                                        @endif

                            </tr>
                            <tr>
                                <th>Sub Applicant</th>
                                         @if($products->has_subApplicant)
                                    <td>Yes</td>
                                        @else
                                    <td>No</td>
                                @endif

                            </tr>
                            <tr>
                                <th>Has Activation Code</th>
                                        @if($products->has_activation_code)
                                    <td>Yes</td>
                                        @else
                                    <td>No</td>
                                @endif
                            </tr>
                            <tr>
                                <th>Specified Motor Items</th>
                                @if($products->is_motor_items)
                                    <td>Yes</td>
                                        @else
                                    <td>No</td>
                                        @endif

                            </tr>
                            <tr>
                                <th>Preinspection</th>
                                           @if($products->preinspection)
                                    <td>Yes</td>
                                        @else
                                    <td>No</td>
                                        @endif
                            </tr>
                            <tr>
                                <th>Status</th>


                     @if($products->status)
                                    <td>Active</td>
                                @else
                                    td>Inactive</td>
                                @endif

                            </tr>
                            </tbody>
                        </table>

                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Upload Product Image</label>
                            <div class="col-2">
                                <div class="kt-avatar" id="product_image" style="float: left; clear: left;">

                                    @if($products && $products->image == NULL)
                                        <div class="kt-avatar__holder" style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                                        <span style="color:darkred" >Product image not uploaded</span>
                                    @else
                                        <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($products->image) !!}" target="_blank" download>
                                            <div class="kt-avatar__holder" style="background-image: url({!! \AlphaDirect\Helper::getCloudFrontURL($products->image) !!})"></div>
                                        </a>
                                    @endif
                                </div>
                            </div>

                        </div>

                        <div class="form-group row"  id="inspectDiv" @if(!$products->preinspection) style="display: none" @endif  >
                            <input type="hidden" value="{!! $products->preinspection !!}" id="inspectionValue">
                            <input type="hidden" value="{!! $products->limit !!}" id="limitValue">
                            <label for="example-text-input" class="col-3 col-form-label">Cost Limit:</label>
                            <div class="col-9">
                                <p class="form-control">{!! $products->limit !!}</p>
                            </div>
                        </div>

                    </div>
                    <div class="kt-portlet__foot kt-portlet__foot--solid">
                        <div class="kt-form__actions">
                            <div class="row">
                                <div class="col-3"></div>
                                <div class="col-9">
                                    <a class="btn btn-secondary" href="{{ route('admin.product.index') }}" >Back</a>
                                </div>
                            </div>
                        </div>
                    </div>

                <!--end::Form-->
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
<!-- end:: Root -->



<!-- begin:: Scrolltop -->
<div id="kt_scrolltop" class="kt-scrolltop"> <i class="la la-arrow-up"></i> </div>
<!-- end:: Scrolltop -->

@include('admin.layouts.scripts')

<script src="{{  asset('assets/vendors/general/bootstrap-select/dist/js/bootstrap-select.js') }}" type="text/javascript"></script>
<script src="{{  asset('assets/app/custom/general/components/forms/widgets/bootstrap-select.js') }}" type="text/javascript"></script>

<script src="{{ asset('assets/vendors/general/jquery-validation/dist/jquery.validate.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/custom/components/vendors/jquery-validation/init.js') }}" type="text/javascript"></script>

{{-- Member Checkbox ends--}}

<script>

    $(document).ready(function(){
        var append = '';
        var region_id = this.value;
        var checkedLics = '{!! json_encode($checkedLicense) !!}';
        $.ajax({
            url: '{{ route('admin.product.regionLicense') }}',
            data: {
                "_token": "{{ csrf_token() }}",
                "id": region_id
            },
            type: 'post',
            datatype: 'json',
            success: function (data) {
                if(data.regionLicenses.length != 0)
                {
                    data.regionLicenses.forEach(function ($regionLicense) {
                        append += '<label class="kt-checkbox col-3"><input type="checkbox" class="license"  if(in_array('+ $regionLicense.id +', checkedLics)){ checked } name="license[]" value='+ $regionLicense.id + '>'+ $regionLicense.license_name +'  :   '+ $regionLicense.license_number +'<span></span></label>';
                        //append += '<input style="margin-left:2%;margin-right:1%" class=" kt-checkbox--brand" type="checkbox" name="license[]" value="'+ $regionLicense.id +'" data-live-search="true" title="Please choose region license"/>'+ $regionLicense.license_name +'  :   '+ $regionLicense.license_number;
                    });
                } else {
                    append += '<label>No Licenses Added for this Region</label>';
                }

                $('#licenseData').html(append);
                if(data.regionLicenses.length != 0)
                    $('.license').rules('add',  { required: true, messages: { required: "Please Select License" } });
                else
                    $('.license').rules('remove',  'required');

            },
        });

        $('#licenseDiv').delay(100).slideDown(500);
        //$("#licenseData").selectpicker('refresh');

    });


    $(window).ready( function () {
    $(".coverage").prop("disabled", true);
});



</script>


</body>
<!-- end::Body -->
</html>