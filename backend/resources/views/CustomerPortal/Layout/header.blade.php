   <!-- begin::Head -->
    <head>
        <meta charset="utf-8"/>
        <title>Alpha Direct</title>
        <meta name="description" content="Latest updates and statistic charts">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <meta http-equiv="X-UA-Compatible" content="IE=edge" />
        <!--begin::Fonts -->
        <script src="https://ajax.googleapis.com/ajax/libs/webfont/1.6.16/webfont.js"></script>
        <script>
            WebFont.load({

                google: {
"families":[
"Poppins:300,400,500,600,700"]},

                active: function() {

                    sessionStorage.fonts = true;
                }
            });
        </script>
         <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
        <!--end::Fonts -->
                <link href="{{asset('css/app/custom/invoice/invoice-v2.default.css')}}" rel="stylesheet" type="text/css" />
                <link href="{{asset('css/vendors/custom/datatables/datatables.bundle.css')}}" rel="stylesheet" type="text/css" />


        <!--begin::Page Vendors Styles(used by this page) -->
        <link href="{{asset('css/vendors/custom/fullcalendar/fullcalendar.bundle.css')}}" rel="stylesheet" type="text/css" />
        <!--end::Page Vendors Styles -->
        <!--begin:: Global Mandatory Vendors -->
<link href="{{asset('css/vendors/general/perfect-scrollbar/css/perfect-scrollbar.css')}}" rel="stylesheet" type="text/css" />
<!--end:: Global Mandatory Vendors -->

<!--begin:: Global Optional Vendors -->
<link href="{{asset('css/vendors/general/tether/dist/css/tether.css')}}" rel="stylesheet" type="text/css" />
<link href="{{asset('css/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css')}}" rel="stylesheet" type="text/css" />
<link href="{{asset('css/vendors/general/bootstrap-datetime-picker/css/bootstrap-datetimepicker.css')}}" rel="stylesheet" type="text/css" />
<link href="{{asset('css/vendors/general/bootstrap-timepicker/css/bootstrap-timepicker.css')}}" rel="stylesheet" type="text/css" />
<link href="{{asset('css/vendors/general/bootstrap-daterangepicker/daterangepicker.css')}}" rel="stylesheet" type="text/css" />
<link href="{{asset('css/vendors/general/bootstrap-touchspin/dist/jquery.bootstrap-touchspin.css')}}" rel="stylesheet" type="text/css" />
<link href="{{asset('css/vendors/general/bootstrap-select/dist/css/bootstrap-select.css')}}" rel="stylesheet" type="text/css" />
<link href="{{asset('css/vendors/general/nouislider/distribute/nouislider.css')}}" rel="stylesheet" type="text/css" />
<link href="{{asset('css/vendors/general/owl.carousel/dist/assets/owl.carousel.css')}}" rel="stylesheet" type="text/css" />
<link href="{{asset('css/vendors/general/owl.carousel/dist/assets/owl.theme.default.css')}}" rel="stylesheet" type="text/css" />
<link href="{{asset('css/vendors/general/dropzone/dist/dropzone.css')}}" rel="stylesheet" type="text/css" />
<link href="{{asset('css/vendors/general/summernote/dist/summernote.css')}}" rel="stylesheet" type="text/css" />
<link href="{{asset('css/vendors/general/bootstrap-markdown/css/bootstrap-markdown.min.css')}}" rel="stylesheet" type="text/css" />
<link href="{{asset('css/vendors/general/animate.css/animate.css')}}" rel="stylesheet" type="text/css" />
<link href="{{asset('css/vendors/general/toastr/build/toastr.css')}}" rel="stylesheet" type="text/css" />
<link href="{{asset('css/vendors/general/morris.js/morris.css')}}" rel="stylesheet" type="text/css" />
<link href="{{asset('css/vendors/general/sweetalert2/dist/sweetalert2.css')}}" rel="stylesheet" type="text/css" />
<link href="{{asset('css/vendors/general/socicon/css/socicon.css')}}" rel="stylesheet" type="text/css" />
<link href="{{asset('css/vendors/custom/vendors/line-awesome/css/line-awesome.css')}}" rel="stylesheet" type="text/css" />
<link href="{{asset('css/vendors/custom/vendors/flaticon/flaticon.css')}}" rel="stylesheet" type="text/css" />
<link href="{{asset('css/vendors/custom/vendors/flaticon2/flaticon.css')}}" rel="stylesheet" type="text/css" />
<link href="{{asset('css/vendors/custom/vendors/fontawesome5/css/all.min.css')}}" rel="stylesheet" type="text/css" />
<!--end:: Global Optional Vendors -->

<!--begin::Global Theme Styles(used by all pages) -->
        
        <link href="{{asset('css/demo/default/base/style.bundle.css')}}" rel="stylesheet" type="text/css" />
        <!--end::Global Theme Styles -->
        <!--begin::Layout Skins(used by all pages) -->
        <link href="{{asset('css/demo/default/skins/header/base/light.css')}}" rel="stylesheet" type="text/css" />
        <link href="{{asset('css/demo/default/skins/header/menu/light.css')}}" rel="stylesheet" type="text/css" />
        <link href="{{asset('css/demo/default/skins/brand/light.css')}}" rel="stylesheet" type="text/css" />
        <link href="{{asset('css/demo/default/skins/aside/light.css')}}" rel="stylesheet" type="text/css" />
        <!--end::Layout Skins -->
        <link rel="shortcut icon" href="{{asset('media/logos/favicon.ico')}}" />

        <meta name="csrf-token" content="{{ csrf_token() }}" />
    </head>
    <!-- end::Head -->