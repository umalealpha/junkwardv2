<!DOCTYPE html>
<!--
Theme: Keen - The Ultimate Bootstrap Admin Theme
Author: KeenThemes
Website: http://www.keenthemes.com/
Contact: support@keenthemes.com
Follow: www.twitter.com/keenthemes
Dribbble: www.dribbble.com/keenthemes
Like: www.facebook.com/keenthemes
License: You must have a valid license purchased only from https://themes.getbootstrap.com/product/keen-the-ultimate-bootstrap-admin-theme/ in order to legally use the theme for your project.
-->
<html lang="en" >
<!-- begin::Head -->
<head>
    <meta charset="utf-8"/>
    <title>AlphaDirect | Reset Password</title>
    <meta name="description" content="User login example">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <!--begin::Fonts -->
    <script src="https://ajax.googleapis.com/ajax/libs/webfont/1.6.16/webfont.js"></script>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.1/jquery.min.js"></script>
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
    <!--end::Fonts -->
    <!--begin::Page Custom Styles(used by this page) -->
    <link href="{{asset('assets/app/custom/user/login-v2.default.css')}}" rel="stylesheet" type="text/css" />
    <!--end::Page Custom Styles -->
    <!--begin:: Global Mandatory Vendors -->
    <link href="{{asset('assets/vendors/general/perfect-scrollbar/css/perfect-scrollbar.css')}}" rel="stylesheet" type="text/css" />
    <!--end:: Global Mandatory Vendors -->

    <!--begin:: Global Optional Vendors -->
    <link href="{{asset('assets/vendors/general/tether/dist/css/tether.css')}}" rel="stylesheet" type="text/css" />
    <link href="{{asset('assets/vendors/general/bootstrap-touchspin/dist/jquery.bootstrap-touchspin.css')}}" rel="stylesheet" type="text/css" />
    <link href="{{asset('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css')}}" rel="stylesheet" type="text/css" />
    <link href="{{asset('assets/vendors/general/nouislider/distribute/nouislider.css')}}" rel="stylesheet" type="text/css" />
    <link href="{{asset('assets/vendors/general/owl.carousel/dist/assets/owl.carousel.css')}}" rel="stylesheet" type="text/css" />
    <link href="{{asset('assets/vendors/general/owl.carousel/dist/assets/owl.theme.default.css')}}" rel="stylesheet" type="text/css" />

    <!--end:: Global Optional Vendors -->

    <!--begin::Global Theme Styles(used by all pages) -->

    <link href="{{asset('assets/demo/default/base/style.bundle.css')}}" rel="stylesheet" type="text/css" />
    <!--end::Global Theme Styles -->
    <!--begin::Layout Skins(used by all pages) -->
    <link href="{{asset('assets/demo/default/skins/header/base/light.css')}}" rel="stylesheet" type="text/css" />
    <link href="{{asset('assets/demo/default/skins/header/menu/light.css')}}" rel="stylesheet" type="text/css" />
    <link href="{{asset('assets/demo/default/skins/brand/navy.css')}}" rel="stylesheet" type="text/css" />
    <link href="{{asset('assets/demo/default/skins/aside/navy.css')}}" rel="stylesheet" type="text/css" />
    <!--end::Layout Skins -->
    <link rel="shortcut icon" href="{{asset('images/alpha-logo.png')}}" />
    <style>
        .invalid-feedback {
            display: none;
            width: 100%;
            margin-top: 0.25rem;
            font-size: 100% !important;
            color: #fd397a;
        }
    </style>
</head>
<!-- end::Head -->
<!-- begin::Body -->
<body class="kt-login-v2--enabled kt-header--fixed kt-header-mobile--fixed kt-subheader--enabled kt-subheader--transparent kt-aside--enabled kt-aside--fixed kt-page--loading" >
<!-- begin:: Page -->
<div class="kt-grid kt-grid--ver kt-grid--root">
    <div class="kt-grid__item kt-grid__item--fluid kt-grid kt-grid kt-grid--hor kt-login-v2" id="kt_login_v2">
        <!--begin::Item-->
        <div class="kt-grid__item kt-grid--hor">
            <!--begin::Heade-->
            <div class="kt-login-v2__head">
                <div class="kt-login-v2__logo">
                    <a href="#">
                        <img src="{{asset('images/logo.png')}}" alt="" />
                    </a>
                </div>
            </div>
            <!--begin::Head-->
        </div>
        <!--end::Item-->
        <!--begin::Item-->
        <div class="kt-grid__item kt-grid kt-grid--ver kt-grid__item--fluid">
            <!--begin::Body-->
            <div class="kt-login-v2__body">
                <!--begin::Wrapper-->
                <div class="kt-login-v2__wrapper">
                    <div class="kt-login-v2__container">
                        <div class="kt-login-v2__title">
                            <h3>
                                Reset Password
                            </h3>
                        </div>
                        <!--begin::Form-->
                        <form class="kt-login-v2__form kt-form" id="reset" method="post" action="{{ url('/api/frontendpay/updatePassword') }}">
                            <input type="hidden" name="id" value="{{$id}}">
                            <div class="form-group">
                                <input class="form-control" autocomplete="off" type="password" id="password" placeholder="Enter new password" name="password">
                            </div>
                            <div class="form-group">
                                <input autocomplete="off" class="form-control" type="password" placeholder="Please re-enter new password" name="cpassword">
                            </div>
                            <!--begin::Action-->
                            <div class="kt-login-v2__actions">
                                <button type="submit" id="submit" class="btn btn-brand btn-pill">Reset</button>
                            </div>
                        </form>
                    </div>
                </div>
                <!--end::Wrapper-->
                <!--begin::Image-->
                <div class="kt-login-v2__image">
                    <img src="{{asset('assets/media/misc/bg_icon.svg')}}" alt="">
                </div>
                <!--begin::Image-->
            </div>
            <!--begin::Body-->
        </div>
        <!--end::Item-->
        <!--begin::Item-->
        <div class="kt-grid__item">
            <div class="kt-login-v2__footer">
                <div class="kt-login-v2__info"> <a href="#" class="kt-link">&copy; 2020 AlphaDirect</a> </div>
            </div>
        </div>
        <!--end::Item-->
    </div>
</div>
<!-- end:: Page -->
<!-- begin::Global Config(global config for global JS sciprts) -->
<script>
    var KTAppOptions = {

        "colors": {

            "state": {

                "brand": "#5d78ff",

                "metal": "#c4c5d6",

                "light": "#ffffff",

                "accent": "#00c5dc",

                "primary": "#5867dd",

                "success": "#34bfa3",

                "info": "#36a3f7",

                "warning": "#ffb822",

                "danger": "#fd3995",

                "focus": "#9816f4"
            },

            "base": {

                "label": [

                    "#c5cbe3",

                    "#a1a8c3",

                    "#3d4465",

                    "#3e4466"
                ],

                "shape": [

                    "#f0f3ff",

                    "#d9dffa",

                    "#afb4d4",

                    "#646c9a"
                ]
            }
        }
    };
</script>
<!-- end::Global Config -->
<!--begin:: Global Mandatory Vendors -->
<script>
    $(document).ready(function(){
        $("#reset").validate({
            rules: {
                password: {
                    required: true,
                },
                cpassword: {
                    required: true,
                    equalTo: "#password",
                },
            },
            messages: {
                password: {
                    required: "Please enter password"
                },
                cpassword: {
                    required: "Please re-enter password",
                    equalTo: "Password and confirm password should be same",
                },
            },
        });
    });
</script>
<script src="{{asset('assets/vendors/general/jquery/dist/jquery.js')}}" type="text/javascript"></script>
<script src="{{asset('assets/vendors/general/popper.js/dist/umd/popper.js')}}" type="text/javascript"></script>
<script src="{{asset('assets/vendors/general/bootstrap/dist/js/bootstrap.min.js')}}" type="text/javascript"></script>
<script src="{{asset('assets/vendors/general/js-cookie/src/js.cookie.js')}}" type="text/javascript"></script>
<script src="{{asset('assets/vendors/general/moment/min/moment.min.js')}}" type="text/javascript"></script>
<script src="{{asset('assets/vendors/general/tooltip.js/dist/umd/tooltip.min.js')}}" type="text/javascript"></script>
<script src="{{asset('assets/vendors/general/perfect-scrollbar/dist/perfect-scrollbar.js')}}" type="text/javascript"></script>
<script src="{{asset('assets/vendors/general/sticky-js/dist/sticky.min.js')}}" type="text/javascript"></script>
<script src="{{asset('assets/vendors/general/wnumb/wNumb.js')}}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/general/jquery-validation/dist/jquery.validate.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/custom/components/vendors/jquery-validation/init.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/general/jquery-validation/dist/additional-methods.js') }}" type="text/javascript"></script>

<!--end:: Global Mandatory Vendors -->

<!--begin:: Global Optional Vendors -->
<script src="{{asset('assets/vendors/general/jquery-form/dist/jquery.form.min.js')}}" type="text/javascript"></script>

<!--end:: Global Optional Vendors -->

<!--begin::Global Theme Bundle(used by all pages) -->

<script src="{{asset('assets/demo/default/base/scripts.bundle.js')}}" type="text/javascript"></script>
<!--end::Global Theme Bundle -->
<!--begin::Page Scripts(used by this page) -->
<script src="{{asset('assets/app/custom/general/custom/login/login.js')}}" type="text/javascript"></script>
<!--end::Page Scripts -->
<!--begin::Global App Bundle(used by all pages) -->
<script src="{{asset('assets/app/bundle/app.bundle.js')}}" type="text/javascript"></script>
<!--end::Global App Bundle -->
</body>
<!-- end::Body -->
</html>