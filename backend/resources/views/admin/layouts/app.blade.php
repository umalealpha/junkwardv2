@include('admin.layouts.header')

<body class="kt-header--fixed kt-header-mobile--fixed kt-subheader--enabled kt-subheader--transparent kt-aside--enabled kt-aside--fixed kt-page--loading">

<!-- begin:: Root -->
<div class="kt-grid kt-grid--hor kt-grid--root">
    <!-- begin:: Page -->
    <div class="kt-grid__item kt-grid__item--fluid kt-grid kt-grid--ver kt-page">

        @include('admin.layouts.sidebar')
        @include('admin.layouts.topNav')

        @if(Auth::user()->password == null)
            @include('includes.reset')
        @else
        <div class="kt-grid__item kt-grid__item--fluid kt-grid kt-grid--hor">
            @yield('content')

            @include('includes.footer')
        </div>
        @endif

    </div>
    <!-- end:: Page -->
</div>
<!-- end:: Root -->

@include('admin.layouts.scripts')
@stack('scripts')

</body>
</html>
