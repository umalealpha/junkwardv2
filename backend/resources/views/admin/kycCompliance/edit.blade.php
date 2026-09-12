<!DOCTYPE html>
<html lang="en" >
@include('admin.layouts.header')
<link href="{{  asset('assets/vendors/custom/datatables/datatables.bundle.css') }}" rel="stylesheet" type="text/css" />
<link href="{{  asset('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}" rel="stylesheet" type="text/css" />

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
    <!--If Password default, show edit details -->
    @if (Auth::user()->password == null)
        @include('includes.reset')
    @else
        <div class="kt-grid__item kt-grid__item--fluid kt-grid kt-grid--hor">
            <!-- begin:: Subheader -->
            <div class="kt-subheader kt-grid__item" id="kt_subheader">
                <div class="kt-subheader__main">
                    <h3 class="kt-subheader__title">
                        KYC compliance
                    </h3>
                    <span class="kt-subheader__separator kt-hidden"></span>
                    <div class="kt-subheader__breadcrumbs">
                        <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                        <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin-dashboard')}}" class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span class="kt-subheader__breadcrumbs-separator"></span>
                        <a class="kt-subheader__breadcrumbs-separator"><span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">KYC compliance</span> </a>
                        <a class="kt-subheader__breadcrumbs-separator"><span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">View Data</span> </a>
                    </div>
                </div>
            </div>
            <!-- end:: Subheader -->

            <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
                <div class="kt-portlet kt-portlet--mobile">
                    <div class="kt-portlet__body">
                        <!--begin: Datatable -->
                        <!-- begin:: Content -->
                        <form id="productEdit" action="{{ route('admin.kycCompliance.update',$kycCompliance->id) }}" method="POST"
                            enctype="multipart/form-data" class="kt-form">
                            <input type="hidden" name="_method" value="PUT">
                             <!-- CSRF Token -->
                             <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                            <input type="hidden" name="data_id" value="" />

                            {{-- <div class="form-group row">
                                <label for="example-text-input"  class="col-3 col-form-label"><h5>Name:</h5></label>
                                <div class="col-6">
                                    <input type="text" class="form-control @error('compliance_name') is-invalid @enderror" name="compliance_name"
                                           placeholder="Please Enter Name" title="Please Enter Name" />
                                       @error('compliance_name')
                                           <div class="text-danger">{{ $message }}</div>
                                       @enderror
                                </div>
                            </div> --}}

                            <div class="form-group row">
                                <label for="example-text-input" class="col-3 col-form-label"><h5>Name:</h5></label>
                                <div class="col-6">
                                    <input type="text" class="form-control @error('compliance_name') is-invalid @enderror" name="compliance_name" value="{{ $kycCompliance->name }}"
                                           placeholder="Please Enter Name" title="Please Enter Name"  />
                                       @error('compliance_name')
                                           <div class="text-danger">{{ $message }}</div>
                                       @enderror
                                </div>
                            </div>
                            <div class="form-group row">
                                <label for="example-text-input"  class="col-3 col-form-label"><h5>Flow id:</h5></label>
                                <div class="col-6">
                                    <input type="text" class="form-control @error('flow_id') is-invalid @enderror" name="flow_id" value="{{ $kycCompliance->flow_id }}"
                                           placeholder="Please Enter flow id" title="Please Enter Flow id"  />
                                       @error('flow_id')
                                           <div class="text-danger">{{ $message }}</div>
                                       @enderror
                                </div>
                            </div>

                            <table class="table table-striped table-bordered table-hover table-checkable" id="policy_table">
                                <thead>
                                    @foreach (json_decode($kycCompliance->fields) as $k => $kyc_field)

                                        <tr id="tr_{{ $k }}">
                                            <th>
                                                <input class="form-control" type="hidden" name="data[{{ $k}}][field]" value="{{ $kyc_field->field }}">
                                                {{  $kyc_field->field }}
                                            </th>
                                            <td>
                                                <select name="data[{{ $k}}][check]" class="form-control @error('data.'.$k.'.check') is-invalid @enderror field" id="field_{{ $k }}" >
                                                    <option value="0" @if($kyc_field->check == 0) {{'selected'}} @endif>Select</option>
                                                    <option value="1" @if($kyc_field->check == 1) {{'selected'}} @endif>Mandatory</option>
                                                    <option value="2" @if($kyc_field->check == 2) {{'selected'}} @endif>Mandatory with Other field</option>
                                                    <option value="3" @if($kyc_field->check == 3) {{'selected'}} @endif>Optional</option>
                                                </select>
                                                    @error('data.'.$k.'.check')
                                                            <div class="text-danger">{{ $message }}</div>
                                                    @enderror
                                            </td>
                                            <td>
                                                <select class="form-control @error('data.*.other') is-invalid @enderror kycField" name="data[{{ $k}}][other]" id="kycField_{{ $k }}"
                                                    @if ($kyc_field->check != 2) style="display: none"
                                                    @endif>
                                                    <option value="">Select Mandatory field</option>
                                                    @foreach ($kyc_others as $kyc_other)
                                                        <option value="{{$kyc_other->slug}}" {{ $kyc_other->slug == $kyc_field->other ? 'selected': '' }}>{{ $kyc_other->name}}</option>
                                                    @endforeach
                                                </select>
                                                    @error('data.*.other')
                                                    <div class="text-danger">{{ $message }}</div>
                                                    @enderror
                                            </td>
                                            <td>
                                                {{-- <select class="form-control @error('data.'.$k.'.other') is-invalid @enderror kycField" name="data[{{ $k}}][other]" id="kycField_{{ $k }}"
                                                        @error('data.'.$k.'.other') style="display: block" @elseif (old('data.'. $k .'.check') == 2) style="display: block"  @else style="display: none" @enderror > --}}

                                                {{-- <select class="form-control @error('data.'.$k.'.other') is-invalid @enderror kycField" name="data[{{ $k}}][other]" id="kycField_{{ $k }}"
                                                    @if ($kyc_field->check != 2) style="display: block" @endif>

                                                    <option value="">Select Mandatory field</option>
                                                    @foreach ($kyc_others as $kyc_other)
                                                      <option @if( old('data.'.$k.'other') == $kyc_other->slug)  {{ 'selected'}} @endif value="{{$kyc_other->slug}}" {{ $kyc_other->slug == $kyc_field->other ? 'selected': '' }}> {{ $kyc_other->name }}</option>
                                                    @endforeach
                                                </select>
                                                    @error('data.'.$k.'.other')
                                                        <div class="text-danger">{{ $message }}</div>
                                                    @enderror --}}
                                            </td>
                                        </tr>
                                    @endforeach
                                </thead>
                            </table>

                            <div class="kt-portlet__foot kt-portlet__foot--solid">
                                <div class="kt-form__actions">
                                    <div class="row">
                                        <div class="col-5"></div>
                                        <div class="col-7">
                                            <button type="submit" value="Submit" id="btn" class="btn btn-brand">Submit</button>
                                            <button class="btn btn-brand" type="button" id="loadBtn" style="display:none" > <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Loading... </button>
                                            <a class="btn btn-secondary" href="{{ URL::to('admin/KycCompliance') }}" >Cancel</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                        <!--end: Datatable -->
                    </div>
                </div>
            </div>
            <!-- end:: Content -->
        </div>
@endif
<!-- begin:: Footer -->
@include('includes.footer')
<!-- end:: Footer -->
</div>
<!-- end:: Wrapper -->
</div>
<!-- end:: Page -->
</div>
<!-- end:: Root -->

@include('admin.layouts.scripts')

<script src="{{  asset('assets/vendors/custom/datatables/datatables.bundle.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/general/jquery-validation/dist/jquery.validate.js') }}" type="text/javascript"></script>

<script>
    $(document).ready(function(){

        $('#kycField').hide();
        $('.field').on('change',function(){
            var val = $(this).find(":selected"). val();
            var val_id = $(this).attr('id');
            var id_arr = val_id.split("_");
            // console.log(id_arr);
            var numPart = id_arr[1];
            var selct_var = $('select[id=kycField_' + numPart + ']');
            // console.log(selct_var);
            if(val == "2") {
                selct_var.addClass("required");
                selct_var.show();
            }else{
                selct_var.hide();
            }
        })
    })

</script>

</body>
<!-- end::Body -->
</html>
