<!DOCTYPE html>
<html lang="en" >

@include('admin.layouts.header')

<link href="{{  asset('assets/vendors/custom/datatables/datatables.bundle.css') }}" rel="stylesheet" type="text/css" />
<link href="{{  asset('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css') }}" rel="stylesheet" type="text/css" />
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
                    Cellphone Delete Records
                </h3>
                <span class="kt-subheader__separator kt-hidden"></span>
                <div class="kt-subheader__breadcrumbs">
                    <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                    <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin-dashboard')}}" class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span class="kt-subheader__breadcrumbs-separator"></span>
                    <a href="{{Route('admin.customerVehicleInspection')}}" class="kt-subheader__breadcrumbs-link"> Cellphone Inspection </a> <span class="kt-subheader__breadcrumbs-separator"></span>
                    <span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">Cellphone Delete Data</span> </a>
                </div>
            </div>
            <div class="kt-subheader__toolbar">
                <div class="kt-subheader__wrapper">
                   {{-- <a href="{{ URL::to('admin/region/create') }}" class="btn btn-sm btn-elevate btn-brand btn-elevate" id="" data-toggle="kt-tooltip" title="" data-placement="left" data-original-title="Add New"> <span class="kt-opacity-11" id="">Add New</span>&nbsp; <i class="flaticon2-add-1 kt-padding-l-5 kt-padding-r-0"></i> </a>--}}
                </div>
            </div>
        </div>
        <!-- end:: Subheader -->

        <!-- begin:: Content -->
        <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
            <div class="kt-portlet kt-portlet--mobile">
                <div class="kt-portlet__body">
                    @if(isset($data) && $data->policy_id != null)
                    @php 
                        $policy = \AlphaDirect\Policy::where('id',$data->policy_id)->first(['policyNumber']);
                    @endphp
                    @if($policy)
                    <h5>PolicyNumber:  {{ $policy->policyNumber }}</h5>
                    @endif
                    @endif
                    <br>
                    <!--begin: Datatable -->
                    @if($data->cell_phone_front != null)
                    <table class="table table-striped table-bordered table-hover " >
                       
                        <tr>
                        <th>Cellphone Front</th>
                        <th>Imei</th>
                        
                       
                        <th>Performed By</th>
                        <th>Deleted At</th>
                        <th>
                        @foreach(json_decode($data->cell_phone_front) as $passdata)
                        <tr>
                         <td>
                         <a href ="{{ \AlphaDirect\Helper::getCloudFrontURL($passdata[0]->cell_phone_front) }}" target= "_blank">
                                      
                                      @if(pathinfo($passdata[0]->cell_phone_front,
                                  PATHINFO_EXTENSION) == 'pdf')
                                          <img src="{{asset('images/pdf.ico')}}" width="70px"
                                              height="auto">
                                      @elseif(pathinfo($passdata[0]->cell_phone_front,
                                      PATHINFO_EXTENSION) == 'docx' ||
                                      pathinfo($passdata[0]->cell_phone_front, PATHINFO_EXTENSION)
                                      == 'doc' || pathinfo($passdata[0]->cell_phone_front,
                                      PATHINFO_EXTENSION) == 'docm')
                                          <img src="{{asset('images/word.ico')}}" width="70px"
                                              height="auto">
                                      @elseif(pathinfo($passdata[0]->cell_phone_front,
                                      PATHINFO_EXTENSION) == 'xls' ||
                                      pathinfo($passdata[0]->cell_phone_front, PATHINFO_EXTENSION)
                                      == 'xlsx' || pathinfo($passdata[0]->cell_phone_front,
                                      PATHINFO_EXTENSION) == 'csv')
                                          <img src="{{asset('images/excel.png')}}"
                                              width="70px" height="auto">
                                      @elseif(pathinfo($passdata[0]->cell_phone_front,
                                      PATHINFO_EXTENSION) == 'jpeg' ||
                                      pathinfo($passdata[0]->cell_phone_front, PATHINFO_EXTENSION)
                                      == 'jpg' || pathinfo($passdata[0]->cell_phone_front,
                                      PATHINFO_EXTENSION) == 'png')
                                          <img src="{!! \AlphaDirect\Helper::getCloudFrontURL($passdata[0]->cell_phone_front) !!}"
                                              width="70px" height="auto">
                                      @else
                                          <img src="{{asset('images/doc.png')}}" width="70px"
                                              height="auto">
                                      @endif

                                          </a>



                         </td>
                         <td>{{ $passdata[0]->imei }}</td>

                        
                        
                         <td>@php
                             if($passdata[0] != null &&$passdata[0]->performed_by !=null)
                              {
                                $user=AlphaDirect\User::where('id',$passdata[0]->performed_by)->first(array('firstName' , 'lastName'));
                              }
                             @endphp
                            @if(isset($user) && ($user->firstName || $user->lastName))
                                <b>{{ $user->firstName }}  {{ $user->lastName }}</b>
                            @endif   



                         </td>
                         <td>{{ \Carbon\Carbon::parse($passdata[0]->updated_at)->format('Y-m-d')  }}</td>
                         </tr>
                        @endforeach
                         </th>
                        </tr>


                       
                        
                       
                    </table>
                    @endif
                    @if($data->cell_phone_back != null)
                    <table class="table table-striped table-bordered table-hover " >
                       
                        <tr>
                        <th>Cellphone Back</th>
                       
                      
                        <th>Imei</th>
                      
                       
                        <th>Performed By</th>
                        <th>Deleted At</th>
                        <th>
                        @foreach(json_decode($data->cell_phone_back) as $passdata)
                        <tr>
                         <td>
                         <a href ="{{ \AlphaDirect\Helper::getCloudFrontURL($passdata[0]->cell_phone_back) }}" target= "_blank">
                                      
                                      @if(pathinfo($passdata[0]->cell_phone_back,
                                  PATHINFO_EXTENSION) == 'pdf')
                                          <img src="{{asset('images/pdf.ico')}}" width="70px"
                                              height="auto">
                                      @elseif(pathinfo($passdata[0]->cell_phone_back,
                                      PATHINFO_EXTENSION) == 'docx' ||
                                      pathinfo($passdata[0]->cell_phone_back, PATHINFO_EXTENSION)
                                      == 'doc' || pathinfo($passdata[0]->cell_phone_back,
                                      PATHINFO_EXTENSION) == 'docm')
                                          <img src="{{asset('images/word.ico')}}" width="70px"
                                              height="auto">
                                      @elseif(pathinfo($passdata[0]->cell_phone_back,
                                      PATHINFO_EXTENSION) == 'xls' ||
                                      pathinfo($passdata[0]->cell_phone_back, PATHINFO_EXTENSION)
                                      == 'xlsx' || pathinfo($passdata[0]->cell_phone_back,
                                      PATHINFO_EXTENSION) == 'csv')
                                          <img src="{{asset('images/excel.png')}}"
                                              width="70px" height="auto">
                                      @elseif(pathinfo($passdata[0]->cell_phone_back,
                                      PATHINFO_EXTENSION) == 'jpeg' ||
                                      pathinfo($passdata[0]->cell_phone_back, PATHINFO_EXTENSION)
                                      == 'jpg' || pathinfo($passdata[0]->cell_phone_back,
                                      PATHINFO_EXTENSION) == 'png')
                                          <img src="{!! \AlphaDirect\Helper::getCloudFrontURL($passdata[0]->cell_phone_back) !!}"
                                              width="70px" height="auto">
                                      @else
                                          <img src="{{asset('images/doc.png')}}" width="70px"
                                              height="auto">
                                      @endif

                                          </a>



                         </td>
                         <td>{{ $passdata[0]->imei }}</td>
 
                         <td>@php
                             if($passdata[0] != null &&$passdata[0]->performed_by !=null)
                              {
                                $user=AlphaDirect\User::where('id',$passdata[0]->performed_by)->first(array('firstName' , 'lastName'));
                              }
                             @endphp
                            @if(isset($user) && ($user->firstName || $user->lastName))
                                <b>{{ $user->firstName }}  {{ $user->lastName }}</b>
                            @endif   



                         </td>
                         <td>{{ \Carbon\Carbon::parse($passdata[0]->updated_at)->format('Y-m-d')  }}</td>
                         </tr>
                        @endforeach
                         </th>
                        </tr>


                       
                        
                       
                    </table>
                    @endif
                    @if($data->cell_phone_left != null)
                    <table class="table table-striped table-bordered table-hover " >
                       
                        <tr>
                        <th>Cellphone Left</th>
                     
                      
                        <th>Imei</th>
                     
                       
                        <th>Performed By</th>
                        <th>Deleted At</th>
                        <th>
                        @foreach(json_decode($data->cell_phone_left) as $passdata)
                        <tr>
                         <td>
                         <a href ="{{ \AlphaDirect\Helper::getCloudFrontURL($passdata[0]->cell_phone_left) }}" target= "_blank">
                                      
                                      @if(pathinfo($passdata[0]->cell_phone_left,
                                  PATHINFO_EXTENSION) == 'pdf')
                                          <img src="{{asset('images/pdf.ico')}}" width="70px"
                                              height="auto">
                                      @elseif(pathinfo($passdata[0]->cell_phone_left,
                                      PATHINFO_EXTENSION) == 'docx' ||
                                      pathinfo($passdata[0]->cell_phone_left, PATHINFO_EXTENSION)
                                      == 'doc' || pathinfo($passdata[0]->cell_phone_left,
                                      PATHINFO_EXTENSION) == 'docm')
                                          <img src="{{asset('images/word.ico')}}" width="70px"
                                              height="auto">
                                      @elseif(pathinfo($passdata[0]->cell_phone_left,
                                      PATHINFO_EXTENSION) == 'xls' ||
                                      pathinfo($passdata[0]->cell_phone_left, PATHINFO_EXTENSION)
                                      == 'xlsx' || pathinfo($passdata[0]->cell_phone_left,
                                      PATHINFO_EXTENSION) == 'csv')
                                          <img src="{{asset('images/excel.png')}}"
                                              width="70px" height="auto">
                                      @elseif(pathinfo($passdata[0]->cell_phone_left,
                                      PATHINFO_EXTENSION) == 'jpeg' ||
                                      pathinfo($passdata[0]->cell_phone_left, PATHINFO_EXTENSION)
                                      == 'jpg' || pathinfo($passdata[0]->cell_phone_left,
                                      PATHINFO_EXTENSION) == 'png')
                                          <img src="{!! \AlphaDirect\Helper::getCloudFrontURL($passdata[0]->cell_phone_left) !!}"
                                              width="70px" height="auto">
                                      @else
                                          <img src="{{asset('images/doc.png')}}" width="70px"
                                              height="auto">
                                      @endif

                                          </a>



                         </td>
                        

                        
                         <td>{{ $passdata[0]->imei }}</td>
                        
                         <td>@php
                             if($passdata[0] != null &&$passdata[0]->performed_by !=null)
                              {
                                $user=AlphaDirect\User::where('id',$passdata[0]->performed_by)->first(array('firstName' , 'lastName'));
                              }
                             @endphp
                            @if(isset($user) && ($user->firstName || $user->lastName))
                                <b>{{ $user->firstName }}  {{ $user->lastName }}</b>
                            @endif   



                         </td>
                         <td>{{ \Carbon\Carbon::parse($passdata[0]->updated_at)->format('Y-m-d')  }}</td>
                         </tr>
                        @endforeach
                         </th>
                        </tr>


                       
                        
                       
                    </table>
                    @endif
                    @if($data->cell_phone_right != null)
                    <table class="table table-striped table-bordered table-hover " >
                       
                        <tr>
                        <th>Cellphone Right</th>
                        <th>Imei</th>
                        <th>Performed By</th>
                        <th>Deleted At</th>
                        <th>
                        @foreach(json_decode($data->cell_phone_right) as $passdata)
                        <tr>
                         <td>
                         <a href ="{{ \AlphaDirect\Helper::getCloudFrontURL($passdata[0]->cell_phone_right) }}" target= "_blank">
                                      
                                      @if(pathinfo($passdata[0]->cell_phone_right,
                                  PATHINFO_EXTENSION) == 'pdf')
                                          <img src="{{asset('images/pdf.ico')}}" width="70px"
                                              height="auto">
                                      @elseif(pathinfo($passdata[0]->cell_phone_right,
                                      PATHINFO_EXTENSION) == 'docx' ||
                                      pathinfo($passdata[0]->cell_phone_right, PATHINFO_EXTENSION)
                                      == 'doc' || pathinfo($passdata[0]->cell_phone_right,
                                      PATHINFO_EXTENSION) == 'docm')
                                          <img src="{{asset('images/word.ico')}}" width="70px"
                                              height="auto">
                                      @elseif(pathinfo($passdata[0]->cell_phone_right,
                                      PATHINFO_EXTENSION) == 'xls' ||
                                      pathinfo($passdata[0]->cell_phone_right, PATHINFO_EXTENSION)
                                      == 'xlsx' || pathinfo($passdata[0]->cell_phone_right,
                                      PATHINFO_EXTENSION) == 'csv')
                                          <img src="{{asset('images/excel.png')}}"
                                              width="70px" height="auto">
                                      @elseif(pathinfo($passdata[0]->cell_phone_right,
                                      PATHINFO_EXTENSION) == 'jpeg' ||
                                      pathinfo($passdata[0]->cell_phone_right, PATHINFO_EXTENSION)
                                      == 'jpg' || pathinfo($passdata[0]->cell_phone_right,
                                      PATHINFO_EXTENSION) == 'png')
                                          <img src="{!! \AlphaDirect\Helper::getCloudFrontURL($passdata[0]->cell_phone_right) !!}"
                                              width="70px" height="auto">
                                      @else
                                          <img src="{{asset('images/doc.png')}}" width="70px"
                                              height="auto">
                                      @endif

                                          </a>



                         </td>
                         

                        
                         <td>{{ $passdata[0]->imei }}</td>
                       
                         <td>@php
                             if($passdata[0] != null &&$passdata[0]->performed_by !=null)
                              {
                                $user=AlphaDirect\User::where('id',$passdata[0]->performed_by)->first(array('firstName' , 'lastName'));
                              }
                             @endphp
                            @if(isset($user) && ($user->firstName || $user->lastName))
                                <b>{{ $user->firstName }}  {{ $user->lastName }}</b>
                            @endif   



                         </td>
                         <td>{{ \Carbon\Carbon::parse($passdata[0]->updated_at)->format('Y-m-d')  }}</td>
                         </tr>
                        @endforeach
                         </th>
                        </tr>


                       
                        
                       
                    </table>
                    @endif
                    @if($data->cell_phone_top != null)
                    <table class="table table-striped table-bordered table-hover " >
                       
                        <tr>
                        <th>Cellphone  Top</th>
                       
                      
                        <th>Imei</th>
                   
                       
                        <th>Performed By</th>
                        <th>Deleted At</th>
                        <th>
                        @foreach(json_decode($data->cell_phone_top) as $passdata)
                        <tr>
                         <td>
                         <a href ="{{ \AlphaDirect\Helper::getCloudFrontURL($passdata[0]->cell_phone_top) }}" target= "_blank">
                                      
                                      @if(pathinfo($passdata[0]->cell_phone_top,
                                  PATHINFO_EXTENSION) == 'pdf')
                                          <img src="{{asset('images/pdf.ico')}}" width="70px"
                                              height="auto">
                                      @elseif(pathinfo($passdata[0]->cell_phone_top,
                                      PATHINFO_EXTENSION) == 'docx' ||
                                      pathinfo($passdata[0]->cell_phone_top, PATHINFO_EXTENSION)
                                      == 'doc' || pathinfo($passdata[0]->cell_phone_top,
                                      PATHINFO_EXTENSION) == 'docm')
                                          <img src="{{asset('images/word.ico')}}" width="70px"
                                              height="auto">
                                      @elseif(pathinfo($passdata[0]->cell_phone_top,
                                      PATHINFO_EXTENSION) == 'xls' ||
                                      pathinfo($passdata[0]->cell_phone_top, PATHINFO_EXTENSION)
                                      == 'xlsx' || pathinfo($passdata[0]->cell_phone_top,
                                      PATHINFO_EXTENSION) == 'csv')
                                          <img src="{{asset('images/excel.png')}}"
                                              width="70px" height="auto">
                                      @elseif(pathinfo($passdata[0]->cell_phone_top,
                                      PATHINFO_EXTENSION) == 'jpeg' ||
                                      pathinfo($passdata[0]->cell_phone_top, PATHINFO_EXTENSION)
                                      == 'jpg' || pathinfo($passdata[0]->cell_phone_top,
                                      PATHINFO_EXTENSION) == 'png')
                                          <img src="{!! \AlphaDirect\Helper::getCloudFrontURL($passdata[0]->cell_phone_top) !!}"
                                              width="70px" height="auto">
                                      @else
                                          <img src="{{asset('images/doc.png')}}" width="70px"
                                              height="auto">
                                      @endif

                                          </a>



                         </td>
                        

                        
                         <td>{{ $passdata[0]->imei }}</td>
                      
                         <td>@php
                             if($passdata[0] != null &&$passdata[0]->performed_by !=null)
                              {
                                $user=AlphaDirect\User::where('id',$passdata[0]->performed_by)->first(array('firstName' , 'lastName'));
                              }
                             @endphp
                            @if(isset($user) && ($user->firstName || $user->lastName))
                                <b>{{ $user->firstName }}  {{ $user->lastName }}</b>
                            @endif   



                         </td>
                         <td>{{ \Carbon\Carbon::parse($passdata[0]->updated_at)->format('Y-m-d')  }}</td>
                         </tr>
                        @endforeach
                         </th>
                        </tr>


                       
                        
                       
                    </table>
                    @endif
                    @if($data->cell_phone_bottom != null)
                    <table class="table table-striped table-bordered table-hover " >
                       
                        <tr>
                        <th>Cellphone Bottom</th>
                       
                      
                        <th>Imei</th>
                       
                       
                        <th>Performed By</th>
                        <th>Deleted At</th>
                        <th>
                        @foreach(json_decode($data->cell_phone_bottom) as $passdata)
                        <tr>
                         <td>
                         <a href ="{{ \AlphaDirect\Helper::getCloudFrontURL($passdata[0]->cell_phone_bottom) }}" target= "_blank">
                                      
                                      @if(pathinfo($passdata[0]->cell_phone_bottom,
                                  PATHINFO_EXTENSION) == 'pdf')
                                          <img src="{{asset('images/pdf.ico')}}" width="70px"
                                              height="auto">
                                      @elseif(pathinfo($passdata[0]->cell_phone_bottom,
                                      PATHINFO_EXTENSION) == 'docx' ||
                                      pathinfo($passdata[0]->cell_phone_bottom, PATHINFO_EXTENSION)
                                      == 'doc' || pathinfo($passdata[0]->cell_phone_bottom,
                                      PATHINFO_EXTENSION) == 'docm')
                                          <img src="{{asset('images/word.ico')}}" width="70px"
                                              height="auto">
                                      @elseif(pathinfo($passdata[0]->cell_phone_bottom,
                                      PATHINFO_EXTENSION) == 'xls' ||
                                      pathinfo($passdata[0]->cell_phone_bottom, PATHINFO_EXTENSION)
                                      == 'xlsx' || pathinfo($passdata[0]->cell_phone_bottom,
                                      PATHINFO_EXTENSION) == 'csv')
                                          <img src="{{asset('images/excel.png')}}"
                                              width="70px" height="auto">
                                      @elseif(pathinfo($passdata[0]->cell_phone_bottom,
                                      PATHINFO_EXTENSION) == 'jpeg' ||
                                      pathinfo($passdata[0]->cell_phone_bottom, PATHINFO_EXTENSION)
                                      == 'jpg' || pathinfo($passdata[0]->cell_phone_bottom,
                                      PATHINFO_EXTENSION) == 'png')
                                          <img src="{!! \AlphaDirect\Helper::getCloudFrontURL($passdata[0]->cell_phone_bottom) !!}"
                                              width="70px" height="auto">
                                      @else
                                          <img src="{{asset('images/doc.png')}}" width="70px"
                                              height="auto">
                                      @endif

                                          </a>



                         </td>
                        

                        
                         <td>{{ $passdata[0]->imei }}</td>
                       
                         <td>@php
                             if($passdata[0] != null &&$passdata[0]->performed_by !=null)
                              {
                                $user=AlphaDirect\User::where('id',$passdata[0]->performed_by)->first(array('firstName' , 'lastName'));
                              }
                             @endphp
                            @if(isset($user) && ($user->firstName || $user->lastName))
                                <b>{{ $user->firstName }}  {{ $user->lastName }}</b>
                            @endif   



                         </td>
                         <td>{{ \Carbon\Carbon::parse($passdata[0]->updated_at)->format('Y-m-d')  }}</td>
                         </tr>
                        @endforeach
                         </th>
                        </tr>


                       
                        
                       
                    </table>
                    @endif
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

<!-- begin:: Scrolltop -->
<div id="kt_scrolltop" class="kt-scrolltop"> <i class="la la-arrow-up"></i> </div>
<!-- end:: Scrolltop -->

@include('admin.layouts.scripts')

<div class="modal fade" id="delete_confirm" tabindex="-1" role="dialog" aria-labelledby="user_delete_confirm_title" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content" id="modal-content">
        </div>
    </div>
</div>
<script>
    $(function () {
        $('body').on('hidden.bs.modal', '.modal', function () {
            $(this).removeData('bs.modal');
        });
    });



</script>

<script src="{{  asset('assets/vendors/custom/datatables/datatables.bundle.js') }}" type="text/javascript"></script>

<script src="{{  asset('assets/vendors/general/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}" type="text/javascript"></script>
<script src="{{  asset('assets/vendors/custom/components/vendors/bootstrap-datepicker/init.js') }}" type="text/javascript"></script>


</body>
<!-- end::Body -->
</html>
