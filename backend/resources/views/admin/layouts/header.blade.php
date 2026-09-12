<!DOCTYPE html>
<html lang="en" >
<!-- begin::Head -->
<head>
    <meta charset="utf-8"/>
    <title>Alpha Direct</title>
    <title>
        @section('title')
            | Alpha Direct
        @show
    </title>
    <meta name="description" content="Server-side processing examples">
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
    <!--end::Fonts -->
    <!--end::Page Vendors Styles -->
    <!--begin:: Global Mandatory Vendors -->
    <link href="{{asset('css/vendors/general/perfect-scrollbar/css/perfect-scrollbar.css')}}" rel="stylesheet" type="text/css" />
    <!--end:: Global Mandatory Vendors -->

    <!--begin:: Global Optional Vendors -->
    <link href="{{asset('css/vendors/custom/vendors/line-awesome/css/line-awesome.css')}}" rel="stylesheet" type="text/css" />
    <link href="{{asset('css/vendors/custom/vendors/flaticon/flaticon.css')}}" rel="stylesheet" type="text/css" />
    <link href="{{asset('css/vendors/custom/vendors/flaticon2/flaticon.css')}}" rel="stylesheet" type="text/css" />
    <link href="{{asset('css/vendors/custom/vendors/fontawesome5/css/all.min.css')}}" rel="stylesheet" type="text/css" />
    {{--<link href="{{asset('css/vendors/general/sweetalert2/dist/sweetalert2.css')}}" rel="stylesheet" type="text/css" />--}}



    <!--end:: Global Optional Vendors -->

    <!--begin::Global Theme Styles(used by all pages) -->

    <link href="{{asset('css/demo/default/base/style.bundle.min.css')}}" rel="stylesheet" type="text/css" />
    <link href="{{  asset('assets/vendors/general/loader/loader.css') }}" rel="stylesheet" type="text/css" />
    <!--end::Global Theme Styles -->
    <!--begin::Layout Skins(used by all pages) -->
    <link href="{{asset('css/demo/default/skins/header/base/light.css')}}" rel="stylesheet" type="text/css" />
    <link href="{{asset('css/demo/default/skins/header/menu/light.css')}}" rel="stylesheet" type="text/css" />
    <link href="{{asset('css/demo/default/skins/brand/light.css')}}" rel="stylesheet" type="text/css" />
    <link href="{{asset('css/demo/default/skins/aside/light.css')}}" rel="stylesheet" type="text/css" />
    <!--end::Layout Skins -->
    <link rel="shortcut icon" href="{{asset('media/logos/favicon.ico')}}" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />

    <link rel="stylesheet" type="text/css" href="{{ asset('css\GraphiteLoader.css') }}">
    <link href="{{ asset('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css') }}" rel="stylesheet"
    type="text/css" />
    <style>
        table, tbody {
            font-size: 14px;
        }
        .inputControl{
        border: 1px solid red;
        }

        .dim{
            opacity: 0.5 !important;
        }

        .graydot {
            height: 11px;
            width: 11px;
            background-color: #8699a6;
            border-radius: 50%;
            display: inline-block;
        }

        .blackdot {
            height: 11px;
            width: 11px;
            background-color: #171717;
            border-radius: 50%;
            display: inline-block;
        }

        .greendot {
            height: 11px;
            width: 11px;
            background-color: #6ECB63;
            border-radius: 50%;
            display: inline-block;
        }
        
        .red-star{
            color:#f22c2c;
        }

        /* ══════════════════════════════════════════════════════════ */
        /* Alpha Direct Branding Override — Navy + Orange            */
        /* ══════════════════════════════════════════════════════════ */

        :root {
            --ad-navy: #1e3a5f;
            --ad-navy-dark: #152d4a;
            --ad-navy-light: #2a4d7a;
            --ad-orange: #f97316;
            --ad-orange-dark: #ea580c;
            --ad-orange-light: #fb923c;
            --ad-bg: #f1f5f9;
            --ad-card: #ffffff;
            --ad-text: #1e293b;
            --ad-text-muted: #64748b;
            --ad-border: #e2e8f0;
        }

        /* Sidebar — Navy gradient */
        .kt-aside {
            background: linear-gradient(180deg, var(--ad-navy) 0%, var(--ad-navy-dark) 100%) !important;
        }
        .kt-aside__brand {
            background: var(--ad-navy-dark) !important;
            border-bottom: 1px solid rgba(255,255,255,0.08) !important;
            padding: 16px 20px !important;
        }
        .kt-aside__brand-logo img {
            max-height: 36px;
            filter: brightness(0) invert(1);
        }
        .kt-aside__brand-aside-toggler span,
        .kt-aside__brand-aside-toggler span::before,
        .kt-aside__brand-aside-toggler span::after {
            background: rgba(255,255,255,0.5) !important;
        }

        /* Sidebar menu items */
        .kt-aside-menu .kt-menu__nav > .kt-menu__item > .kt-menu__link {
            border-radius: 8px !important;
            margin: 2px 12px !important;
            padding: 10px 16px !important;
            transition: all 0.2s ease !important;
        }
        .kt-aside-menu .kt-menu__nav > .kt-menu__item > .kt-menu__link .kt-menu__link-text {
            color: rgba(255,255,255,0.75) !important;
            font-size: 13px !important;
            font-weight: 500 !important;
        }
        .kt-aside-menu .kt-menu__nav > .kt-menu__item > .kt-menu__link .kt-menu__link-icon {
            color: rgba(255,255,255,0.4) !important;
        }
        .kt-aside-menu .kt-menu__nav > .kt-menu__item:hover > .kt-menu__link,
        .kt-aside-menu .kt-menu__nav > .kt-menu__item.kt-menu__item--open > .kt-menu__link {
            background: rgba(255,255,255,0.08) !important;
        }
        .kt-aside-menu .kt-menu__nav > .kt-menu__item:hover > .kt-menu__link .kt-menu__link-text,
        .kt-aside-menu .kt-menu__nav > .kt-menu__item.kt-menu__item--open > .kt-menu__link .kt-menu__link-text {
            color: #fff !important;
        }
        .kt-aside-menu .kt-menu__nav > .kt-menu__item:hover > .kt-menu__link .kt-menu__link-icon,
        .kt-aside-menu .kt-menu__nav > .kt-menu__item.kt-menu__item--open > .kt-menu__link .kt-menu__link-icon {
            color: var(--ad-orange) !important;
        }
        .kt-aside-menu .kt-menu__nav > .kt-menu__item.kt-menu__item--active > .kt-menu__link {
            background: var(--ad-orange) !important;
            box-shadow: 0 4px 12px rgba(249,115,22,0.3) !important;
        }
        .kt-aside-menu .kt-menu__nav > .kt-menu__item.kt-menu__item--active > .kt-menu__link .kt-menu__link-text,
        .kt-aside-menu .kt-menu__nav > .kt-menu__item.kt-menu__item--active > .kt-menu__link .kt-menu__link-icon {
            color: #fff !important;
        }

        /* Sidebar section headers */
        .kt-menu__section .kt-menu__section-text {
            color: rgba(255,255,255,0.35) !important;
            font-size: 11px !important;
            font-weight: 700 !important;
            letter-spacing: 1px !important;
            text-transform: uppercase !important;
        }

        /* Sidebar submenu */
        .kt-aside-menu .kt-menu__nav > .kt-menu__item .kt-menu__submenu .kt-menu__item > .kt-menu__link .kt-menu__link-text {
            color: #1e3a5f !important;
            font-size: 12.5px !important;
        }
        .kt-aside-menu .kt-menu__nav > .kt-menu__item .kt-menu__submenu .kt-menu__item:hover > .kt-menu__link .kt-menu__link-text,
        .kt-aside-menu .kt-menu__nav > .kt-menu__item .kt-menu__submenu .kt-menu__item.kt-menu__item--active > .kt-menu__link .kt-menu__link-text {
            color: var(--ad-orange-light) !important;
        }
        .kt-aside-menu .kt-menu__nav > .kt-menu__item .kt-menu__submenu .kt-menu__item > .kt-menu__link .kt-menu__link-bullet.kt-menu__link-bullet--dot > span {
            background: rgba(255,255,255,0.3) !important;
        }
        .kt-aside-menu .kt-menu__nav > .kt-menu__item .kt-menu__submenu .kt-menu__item.kt-menu__item--active > .kt-menu__link .kt-menu__link-bullet.kt-menu__link-bullet--dot > span {
            background: var(--ad-orange) !important;
        }
        .kt-menu__nav > .kt-menu__item > .kt-menu__link > .kt-menu__link-icon .kt-menu__link-bullet {
            color: rgba(255,255,255,0.4) !important;
        }

        /* Top header bar */
        .kt-header {
            background: var(--ad-card) !important;
            border-bottom: 1px solid var(--ad-border) !important;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04) !important;
        }

        /* Page background */
        .kt-content {
            background: var(--ad-bg) !important;
        }
        .kt-wrapper {
            background: var(--ad-bg) !important;
        }

        /* Cards and portlets */
        .kt-portlet {
            border-radius: 12px !important;
            border: 1px solid var(--ad-border) !important;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04) !important;
            overflow: hidden;
        }
        .kt-portlet .kt-portlet__head {
            border-bottom: 1px solid var(--ad-border) !important;
            background: var(--ad-card) !important;
        }
        .kt-portlet .kt-portlet__head .kt-portlet__head-label .kt-portlet__head-title {
            color: var(--ad-navy) !important;
            font-weight: 600 !important;
        }

        /* Buttons — primary = orange */
        .btn-brand,
        .btn-primary {
            background: var(--ad-orange) !important;
            border-color: var(--ad-orange) !important;
            color: #fff !important;
            border-radius: 8px !important;
            font-weight: 600 !important;
            transition: all 0.2s ease !important;
        }
        .btn-brand:hover,
        .btn-primary:hover {
            background: var(--ad-orange-dark) !important;
            border-color: var(--ad-orange-dark) !important;
            box-shadow: 0 4px 12px rgba(249,115,22,0.3) !important;
        }

        /* Secondary buttons = navy */
        .btn-secondary,
        .btn-outline-brand {
            border-color: var(--ad-navy) !important;
            color: var(--ad-navy) !important;
            border-radius: 8px !important;
        }
        .btn-outline-brand:hover {
            background: var(--ad-navy) !important;
            color: #fff !important;
        }

        /* Tables */
        .table thead th {
            background: var(--ad-bg) !important;
            color: var(--ad-navy) !important;
            font-weight: 600 !important;
            font-size: 12px !important;
            text-transform: uppercase !important;
            letter-spacing: 0.5px !important;
            border-bottom: 2px solid var(--ad-border) !important;
        }
        .table tbody tr:hover {
            background: #f8fafc !important;
        }

        /* Badges and labels */
        .kt-badge--brand,
        .badge-primary {
            background: var(--ad-orange) !important;
        }
        .kt-badge--info {
            background: var(--ad-navy) !important;
        }

        /* Form inputs */
        .form-control:focus {
            border-color: var(--ad-orange) !important;
            box-shadow: 0 0 0 3px rgba(249,115,22,0.12) !important;
        }

        /* Pagination */
        .page-item.active .page-link {
            background: var(--ad-orange) !important;
            border-color: var(--ad-orange) !important;
        }

        /* Scrollbar */
        .kt-aside-menu-wrapper::-webkit-scrollbar {
            width: 4px;
        }
        .kt-aside-menu-wrapper::-webkit-scrollbar-thumb {
            background: rgba(255,255,255,0.15);
            border-radius: 4px;
        }

        /* User profile dropdown */
        .kt-header__topbar-icon i {
            color: var(--ad-navy) !important;
        }

        /* DataTables search */
        .dataTables_filter input {
            border-radius: 8px !important;
            border: 1px solid var(--ad-border) !important;
            padding: 6px 12px !important;
        }

        /* Subheader */
        .kt-subheader {
            background: transparent !important;
        }
        .kt-subheader__title {
            color: var(--ad-navy) !important;
            font-weight: 700 !important;
        }

        /* Sweet alert brand override */
        .swal2-confirm {
            background: var(--ad-orange) !important;
            border-radius: 8px !important;
        }

        /* Loading spinner */
        .kt-spinner--brand::before {
            border-top-color: var(--ad-orange) !important;
        }

        /* Tabs */
        .nav-tabs .nav-link.active {
            border-bottom: 2px solid var(--ad-orange) !important;
            color: var(--ad-orange) !important;
        }

        /* Close button on aside */
        .kt-aside-close {
            background: var(--ad-navy) !important;
        }
    </style>

    {{-- FE-matching sidebar skin — loaded LAST so it wins against
         every prior rule (Keen skins + the inline navy <style> block
         above). Mirrors the look of the React SPA sidebar. --}}
    <link href="{{asset('css/sidebar-fe-style.css')}}?v={{ filemtime(public_path('css/sidebar-fe-style.css')) }}" rel="stylesheet" type="text/css" />
</head>
