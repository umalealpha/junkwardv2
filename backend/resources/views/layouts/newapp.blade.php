<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700&display=swap">

        <!-- Styles -->
        <link rel="stylesheet" href="{{ mix('css/app.css') }}">
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
		<link href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css" rel="stylesheet">
    
		<!--end::Layout Skins -->
		<link rel="shortcut icon" href="{{asset('media/logos/favicon.ico')}}" />
		<meta name="csrf-token" content="{{ csrf_token() }}" />
			
		<link rel="stylesheet" type="text/css" href="{{ asset('css\GraphiteLoader.css') }}">
        @livewireStyles
		@stack('css')

		{{-- FE-matching sidebar skin — mirrors the React SPA. --}}
		<link href="{{asset('css/sidebar-fe-style.css')}}?v={{ filemtime(public_path('css/sidebar-fe-style.css')) }}" rel="stylesheet" type="text/css" />
    </head>
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
		<div class="kt-grid__item kt-grid__item--fluid kt-grid kt-grid--hor">
			@if (isset($breadcrum))
				{{ $breadcrum }}
			@endif
			<div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
				<livewire:flash-container />
				{{ $slot }}
			</div>
		</div>
	</div>
	@stack('modals')
	<!-- begin:: Scrolltop -->
	<div id="kt_scrolltop" class="kt-scrolltop"> <i class="la la-arrow-up"></i> </div>
	<!-- end:: Scrolltop -->

	<!-- Scripts -->
	<script src="{{ mix('js/app.js') }}" defer></script>
	<!--begin:: Global Mandatory Vendors -->
	<script src="{{asset('css/vendors/general/jquery/dist/jquery.js')}}" type="text/javascript"></script>
	<script src="{{asset('css/vendors/general/popper.js/dist/umd/popper.js')}}" type="text/javascript"></script>
	<script src="{{asset('css/vendors/general/bootstrap/dist/js/bootstrap.min.js')}}" type="text/javascript"></script>
	<script src="{{asset('css/vendors/general/perfect-scrollbar/dist/perfect-scrollbar.js')}}" type="text/javascript"></script>
	<!--end::Global Theme Bundle -->
	
	<script>
    var KTAppOptions = {"colors": {"state": {"brand": "#5d78ff","metal": "#c4c5d6","light": "#ffffff","accent": "#00c5dc","primary": "#5867dd","success": "#34bfa3","info": "#36a3f7","warning": "#ffb822","danger": "#fd3995","focus": "#9816f4"},"base": {"label": ["#c5cbe3","#a1a8c3","#3d4465","#3e4466"],"shape": ["#f0f3ff","#d9dffa","#afb4d4","#646c9a"]}}};
	</script>
	<script src="{{asset('css/demo/default/base/scripts.bundle.js')}}" type="text/javascript"></script>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>

	@livewireScripts
	<script>
		document.onreadystatechange = () =>{
			if(document.readyState==='complete'){
				$("#loader").hide();
				
			}
		}
		window.addEventListener('alert', event => { 
			toastr[event.detail.type](event.detail.message, 
			event.detail.title ?? ''), toastr.options = {
				"closeButton": true,
				"progressBar": true,
			}
		});
	</script>
	@stack('js')
    </body>
</html>
