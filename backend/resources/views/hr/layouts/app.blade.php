<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>HR Portal - @yield('title', 'Policy Management')</title>
    <meta name="description" content="HR Portal for Policy Management" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />

    <!--begin::Fonts-->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Inter:300,400,500,600,700&display=swap" />
    <!--end::Fonts-->

    <!--begin::Page Vendors Styles-->
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-xOolHFLEh07PJGoPkLv1IbcEPTNtaed2xpHsD9ESMhqIYd0nLMwNLD69Npy4HI+N" crossorigin="anonymous">
    <link href="{{ asset('assets/vendors/custom/datatables/datatables.bundle.css') }}" rel="stylesheet" type="text/css" />
    <link href="{{ asset('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css') }}" rel="stylesheet" type="text/css" />
    <link href="{{ asset('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}" rel="stylesheet" type="text/css" />
    <!--end::Page Vendors Styles-->

    <!--begin::LineAwesome Icons-->
    <link href="{{ asset('assets/vendors/general/lineawesome/css/line-awesome.min.css') }}" rel="stylesheet" type="text/css" />
    <!--end::LineAwesome Icons-->

    <!--begin::Global Theme Styles-->
    <!-- Removed missing vendors.bundle.css to avoid 404 -->
    <link href="{{ asset('assets/demo/default/base/style.bundle.css') }}" rel="stylesheet" type="text/css" />
    <!--end::Global Theme Styles-->

    <!--begin::Custom HR Styles-->
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            color: #334155;
            line-height: 1.6;
            min-height: 100vh;
        }

        .hr-container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .hr-sidebar {
            width: 280px;
            background: #ffffff;
            color: #374151;
            position: fixed;
            height: 100vh;
            left: 0;
            top: 0;
            z-index: 1000;
            overflow-y: auto;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.06);
            border-right: 1px solid #e5e7eb;
            transition: width .2s ease;
        }

        .hr-sidebar-header {
            padding: 25px 20px;
            border-bottom: 1px solid #e2e8f0;
            text-align: center;
            background: rgba(255, 255, 255, 0.5);
            backdrop-filter: blur(10px);
        }

        .hr-sidebar-header img {
            max-height: 40px;
            filter: none;
        }

        .hr-sidebar-nav { padding: 12px 0; }

        .hr-nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 18px;
            color: #6b7280;
            text-decoration: none;
            transition: all 0.2s ease;
            border-left: 3px solid transparent;
            margin: 4px 10px;
            border-radius: 8px;
        }

        .hr-nav-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(30, 64, 175, 0.1), transparent);
            transition: left 0.5s ease;
        }

        .hr-nav-item:hover::before {
            left: 100%;
        }

        .hr-nav-item:hover {
            background: #f1f5f9;
            color: #1e40af;
            border-left-color: #1e40af;
        }

        .hr-nav-item.active {
            background: #eef2ff;
            color: #1e40af;
            border-left-color: #1e40af;
        }

        .hr-nav-item i {
            width: 20px;
            text-align: center;
            color: #94a3b8;
            font-size: 18px;
        }

        .hr-nav-item:hover i,
        .hr-nav-item.active i {
            color: #1e40af;
        }

        /* Main Content */
        .hr-main {
            flex: 1;
            margin-left: 280px;
            display: flex;
            flex-direction: column;
        }

        .hr-header {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(226, 232, 240, 0.6);
            padding: 20px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            position: relative;
        }

        .hr-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, transparent, #1e40af, transparent);
        }

        .hr-user-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .hr-user-avatar {
            width: 42px;
            height: 42px;
            background: linear-gradient(135deg, #1e40af, #1e3a8a);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 16px;
            box-shadow: 0 4px 15px rgba(30, 64, 175, 0.3);
            border: 3px solid rgba(255, 255, 255, 0.2);
        }

        .hr-user-details h6 {
            margin: 0;
            font-size: 14px;
            font-weight: 600;
            color: #1e293b;
        }

        .hr-user-details small {
            color: #64748b;
            font-size: 12px;
        }

        .hr-logout-btn {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(220, 38, 38, 0.2);
            position: relative;
            overflow: hidden;
        }

        .hr-logout-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s ease;
        }

        .hr-logout-btn:hover::before {
            left: 100%;
        }

        .hr-logout-btn:hover {
            background: linear-gradient(135deg, #b91c1c, #991b1b);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);
        }

        .hr-content {
            flex: 1;
            padding: 30px;
            background: transparent;
        }

        .hr-page-title {
            font-size: 32px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 8px;
        }

        .hr-page-subtitle {
            color: #64748b;
            font-size: 18px;
            margin-bottom: 30px;
            font-weight: 500;
        }

        /* Cards */
        .hr-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid #e2e8f0;
            overflow: hidden;
            position: relative;
        }

        .hr-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #1e40af, #3b82f6);
        }

        .hr-card-header {
            padding: 25px 30px;
            border-bottom: 1px solid #e2e8f0;
            background: #f8fafc;
            position: relative;
        }

        .hr-card-title {
            font-size: 20px;
            font-weight: 700;
            color: #1e293b;
            margin: 0;
        }

        .hr-card-body {
            padding: 30px;
        }

        /* Stats Cards */
        .hr-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .hr-stat-card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border: 1px solid #e2e8f0;
            text-align: center;
            transition: all 0.2s ease;
        }

        .hr-stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .hr-stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            font-size: 24px;
            color: white;
        }

        .hr-stat-icon.blue { background: #3b82f6; }
        .hr-stat-icon.green { background: #10b981; }
        .hr-stat-icon.purple { background: #8b5cf6; }
        .hr-stat-icon.orange { background: #f59e0b; }

        .hr-stat-title {
            font-size: 16px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 4px;
        }

        .hr-stat-subtitle {
            font-size: 14px;
            color: #64748b;
        }

        /* Forms */
        .hr-form-group {
            margin-bottom: 20px;
        }

        .hr-form-label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            color: #374151;
            margin-bottom: 6px;
        }

        .hr-form-control {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.2s ease;
            background: white;
        }

        .hr-form-control:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .hr-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 16px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
        }

        .hr-btn-primary {
            background: linear-gradient(135deg, #1e40af, #1e3a8a);
            color: white;
            box-shadow: 0 2px 8px rgba(30, 64, 175, 0.2);
            position: relative;
            overflow: hidden;
        }

        .hr-btn-primary::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s ease;
        }

        .hr-btn-primary:hover::before {
            left: 100%;
        }

        .hr-btn-primary:hover {
            background: linear-gradient(135deg, #1e3a8a, #1e40af);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(30, 64, 175, 0.3);
        }

        .hr-btn-secondary {
            background: #f8fafc;
            color: #475569;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .hr-btn-secondary:hover {
            background: #e2e8f0;
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        /* Data Table */
        .hr-table-container {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
        }

        .hr-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .hr-table th {
            background: #f8fafc;
            padding: 18px 20px;
            text-align: left;
            font-weight: 600;
            color: #374151;
            border-bottom: 1px solid #e2e8f0; /* light lines */
            border-right: 1px solid #e2e8f0; /* light lines */
            border-top: 1px solid #e2e8f0; /* top line for header */
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .hr-table th:last-child { border-right: 0; }

        .hr-table td {
            padding: 18px 20px;
            border-bottom: 1px solid #e2e8f0; /* light lines */
            border-right: 1px solid #e2e8f0; /* light lines */
            font-size: 14px;
            color: #374151;
            transition: all 0.2s ease;
        }
        .hr-table td:last-child { border-right: 0; }

        .hr-table tbody tr:hover {
            background: #f8fafc;
            transform: scale(1.005);
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.05);
        }

        .hr-table tbody tr:last-child td {
            border-bottom: none;
        }

        /* Ensure buttons in actions column are visible */
        .hr-table td:last-child {
            white-space: nowrap;
            overflow: visible;
        }

        .hr-table td:last-child .btn {
            display: inline-block !important;
            margin: 0 4px;
            vertical-align: middle;
        }

        /* Footer */
        .hr-footer {
            background: linear-gradient(135deg, #f8fafc, #e2e8f0);
            color: #6b7280;
            text-align: center;
            padding: 25px;
            font-size: 14px;
            box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.05);
            position: relative;
            border-top: 1px solid #e2e8f0;
        }

        .hr-footer::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, #1e40af, transparent);
        }

        /* DataTables UI polish: length dropdown and search */
        .dataTables_wrapper .dataTables_length {
            margin: 10px 16px;
        }
        .dataTables_wrapper .dataTables_length label {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
            color: #475569;
        }
        .dataTables_wrapper .dataTables_length select {
            padding: 8px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            background: #fff;
            color: #334155;
        }
        .dataTables_wrapper .dataTables_filter {
            margin: 10px 16px;
        }
        .dataTables_wrapper .dataTables_filter label { font-weight: 500; color: #475569; }
        .dataTables_wrapper .dataTables_filter input {
            padding: 10px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            background: #fff;
            color: #334155;
            margin-left: 8px;
        }

        /* Bottom info and pagination */
        .dataTables_wrapper .dataTables_info {
            padding: 12px 16px;
            color: #64748b;
        }
        .dataTables_wrapper .dataTables_paginate {
            padding: 8px 16px;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button {
            border: 1px solid #cbd5e1 !important;
            border-radius: 8px !important;
            padding: 6px 10px !important;
            margin: 0 4px !important;
            color: #334155 !important;
            background: #ffffff !important;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button.current,
        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            border-color: #94a3b8 !important;
            background: #f1f5f9 !important;
            color: #1e293b !important;
        }

        /* Collapsible Sidebar */
        .hr-sidebar-toggle {
            position: absolute;
            left: 260px;
            top: 16px;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #fff;
            border: 1px solid #e5e7eb;
            box-shadow: 0 4px 10px rgba(0,0,0,0.08);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            z-index: 1100;
        }
        .hr-sidebar.collapsed { width: 70px; }
        .hr-sidebar.collapsed .hr-sidebar-header { padding: 20px 12px; }
        .hr-sidebar.collapsed .hr-sidebar-nav .hr-nav-item { padding: 12px; text-align: center; }
        .hr-sidebar.collapsed .hr-sidebar-nav .hr-nav-item i { margin-right: 0; }
        .hr-sidebar.collapsed .hr-sidebar-nav .hr-nav-item { white-space: nowrap; }
        .hr-sidebar.collapsed .hr-sidebar-nav .hr-nav-item::before { display: none; }
        .hr-sidebar.collapsed .hr-nav-item { overflow: visible; }
        .hr-sidebar.collapsed .hr-nav-item span { display: none; }
        .hr-sidebar.collapsed + .hr-main { margin-left: 90px; }
        .hr-main { transition: margin-left .2s ease; }

        /* Hover-to-expand behavior like admin sidebar */
        .hr-sidebar.hoverable { width: 70px; }
        .hr-sidebar.hoverable + .hr-main { margin-left: 90px; }
        .hr-sidebar.hoverable:hover { width: 280px; transition: width .2s ease; }
        .hr-sidebar.hoverable:hover + .hr-main { margin-left: 280px; }
        /* collapsed state: show only icons centered */
        .hr-sidebar.hoverable .hr-sidebar-nav .hr-nav-item {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .hr-sidebar.hoverable .hr-nav-item i { display: inline-block; margin-right: 0; font-size: 18px; }
        .hr-sidebar.hoverable .hr-nav-item span { display: none; }
        /* expanded on hover: show labels and left align */
        .hr-sidebar.hoverable:hover .hr-sidebar-nav .hr-nav-item { justify-content: flex-start; }
        .hr-sidebar.hoverable:hover .hr-nav-item span { display: inline; }
        .hr-sidebar.hoverable:hover .hr-nav-item i { margin-right: 10px; }

        /* Responsive */
        @media (max-width: 768px) {
            .hr-sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }

            .hr-sidebar.open {
                transform: translateX(0);
            }

            .hr-main {
                margin-left: 0;
            }

            .hr-stats-grid {
                grid-template-columns: 1fr;
            }

            .hr-content {
                padding: 20px;
            }
        }

        /* Utility Classes */
        .mb-4 { margin-bottom: 1.5rem; }
        .mb-3 { margin-bottom: 1rem; }
        .text-center { text-align: center; }
        .d-flex { display: flex; }
        .align-items-center { align-items: center; }
        .justify-content-between { justify-content: space-between; }
        .gap-3 { gap: 1rem; }
    </style>
    <!--end::Custom HR Styles-->

    <link rel="shortcut icon" href="{{ asset('assets/media/logos/favicon.ico') }}" />

     <link href="{{  asset('assets/vendors/custom/datatables/datatables.bundle.css') }}" rel="stylesheet" type="text/css" />
 <link href="{{  asset('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css') }}" rel="stylesheet" type="text/css" />
 <link href="{{  asset('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}" rel="stylesheet" type="text/css" />

</head>

<body>
    <div class="hr-container">
        <!-- Sidebar -->
        <div class="hr-sidebar hoverable" id="hrSidebar">
            <div class="hr-sidebar-toggle" id="hrSidebarToggle" title="Toggle sidebar">
                <i class="la la-bars"></i>
            </div>
            <div class="hr-sidebar-header">
                <img src="{{ asset('images/logo.png') }}" alt="Logo" />
            </div>
            <nav class="hr-sidebar-nav">
                <a href="{{ route('hr.ad-group-policy') }}" class="hr-nav-item {{ request()->routeIs('hr.ad-group-policy') ? 'active' : '' }}">
                    <i class="flaticon2-copy"></i>
                    <span>AD Group Policy</span>
                </a>
                <a href="{{ route('hr.policy-update') }}" class="hr-nav-item {{ request()->routeIs('hr.policy-update') ? 'active' : '' }}">
                    <i class="flaticon2-edit"></i>
                    <span>Update Policies</span>
                </a>
                <a href="{{ route('hr.logout') }}" class="hr-nav-item" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                    <i class="flaticon2-logout"></i>
                    <span>Logout</span>
                </a>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="hr-main">
            <!-- Header -->
            <header class="hr-header">
                <div class="hr-user-info">
                    <div class="hr-user-avatar">
                        {{ substr(auth()->guard('hr')->user()->email, 0, 1) }}
                    </div>
                    <div class="hr-user-details">
                        <h6>{{ auth()->guard('hr')->user()->email }}</h6>
                        <small>HR User</small>
                    </div>
                </div>
                <form method="POST" action="{{ route('hr.logout') }}" class="d-inline">
                    @csrf
                    <button type="submit" class="hr-logout-btn">
                        <i class="flaticon2-logout"></i> Logout
                    </button>
                </form>
            </header>

            <!-- Content -->
            <main class="hr-content">
                <div class="mb-4">
                    <h1 class="hr-page-title">@yield('page-title', 'Policy Management')</h1>
                    {{-- <p class="hr-page-subtitle">@yield('page-description', 'Manage and view policies efficiently')</p> --}}
                </div>

                @yield('content')
            </main>

            <!-- Footer -->
            <footer class="hr-footer">
                2025 © HR Portal - Professional Policy Management
            </footer>
        </div>
    </div>

    <!--begin::Global Theme Bundle-->
    <!-- Ensure jQuery is loaded before DataTables -->
    <script src="{{ asset('css/vendors/general/jquery/dist/jquery.js') }}" type="text/javascript"></script>
    <script src="{{ asset('assets/demo/default/base/scripts.bundle.js') }}" type="text/javascript"></script>
    <!--end::Global Theme Bundle-->

    <!--begin::Page Vendors-->
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-Fy6S3B9q64WdZWQUiU+q4/2Lc9npb8tCaSX9FK7E8HnRr0Jz8D6OP9dO5Vg3Q9ct" crossorigin="anonymous"></script>
    <script src="{{ asset('assets/vendors/custom/datatables/datatables.bundle.js') }}" type="text/javascript"></script>
    <script src="{{ asset('assets/vendors/general/bootstrap-select/dist/js/bootstrap-select.js') }}" type="text/javascript"></script>
    <script src="{{ asset('assets/vendors/general/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}" type="text/javascript"></script>
    <!--end::Page Vendors-->

    <script>
        (function(){
            var toggle = document.getElementById('hrSidebarToggle');
            var sidebar = document.getElementById('hrSidebar');
            if(toggle && sidebar){
                toggle.addEventListener('click', function(){
                    sidebar.classList.toggle('collapsed');
                });
            }
        })();
    </script>

    @yield('scripts')

    <form id="logout-form" action="{{ route('hr.logout') }}" method="POST" style="display: none;">
        @csrf
    </form>
</body>
</html>
