<!DOCTYPE html>
<html lang="en">

@include('admin.layouts.header')
<link href="{{ asset('assets/vendors/custom/datatables/datatables.bundle.css') }}" rel="stylesheet" type="text/css" />

<body class="kt-header--fixed kt-header-mobile--fixed kt-subheader--enabled kt-subheader--transparent kt-aside--enabled kt-aside--fixed kt-aside--minimize kt-page--loading">
    <div id="kt_header_mobile" class="kt-header-mobile kt-header-mobile--fixed">
        <div class="kt-header-mobile__logo">
            <a>
                <img alt="Logo" src="{{ asset('images/logo.png') }}" />
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

        @if (Auth::user()->password == null)
            @include('includes.reset')
        @else
            <div class="kt-grid__item kt-grid__item--fluid kt-grid kt-grid--hor">
                <div class="kt-subheader kt-grid__item" id="kt_subheader">
                    <div class="kt-subheader__main">
                        <h3 class="kt-subheader__title">PPK Rating Configuration</h3>
                        <span class="kt-subheader__separator kt-hidden"></span>
                        <div class="kt-subheader__breadcrumbs">
                            <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                            <span class="kt-subheader__breadcrumbs-separator"></span>
                            <a href="{{ Route('admin-dashboard') }}" class="kt-subheader__breadcrumbs-link">Dashboard</a>
                            <span class="kt-subheader__breadcrumbs-separator"></span>
                            <a class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">PPK Rating Config</a>
                        </div>
                    </div>
                    <div class="kt-subheader__toolbar">
                        <div class="kt-subheader__wrapper">
                            <a href="{{ route('ppk-rating-config.create') }}" class="btn btn-label-brand btn-bold">
                                <i class="la la-plus"></i> Create New Configuration
                            </a>
                        </div>
                    </div>
                </div>

                <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
                    @if(session('success'))
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            {{ session('success') }}
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    @endif

                    @if(session('error'))
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            {{ session('error') }}
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    @endif

                    <div class="kt-portlet kt-portlet--mobile">
                        <div class="kt-portlet__head kt-portlet__head--lg">
                            <div class="kt-portlet__head-label">
                                <span class="kt-portlet__head-icon">
                                    <i class="kt-font-brand flaticon2-line-chart"></i>
                                </span>
                                <h3 class="kt-portlet__head-title">Configuration List</h3>
                            </div>
                        </div>
                        <div class="kt-portlet__body">
                            <div class="table-responsive">
                                <table class="table table-striped table-bordered table-hover" id="config_table">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Name</th>
                                            <th>Distance Range (Min)</th>
                                            <th>Distance Range (Max)</th>
                                            <th>Status</th>
                                            <th>Created</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($configs as $config)
                                            <tr>
                                                <td>{{ $config->id }}</td>
                                                <td>{{ $config->config_name }}</td>
                                                <td>{{ number_format($config->distance_range_min ?? 0, 0) }} km</td>
                                                <td>{{ number_format($config->distance_range_max ?? 0, 0) }} km</td>
                                                <td>
                                                    @if($config->is_active)
                                                        <span class="kt-badge kt-badge--success kt-badge--inline kt-badge--pill">Active</span>
                                                    @else
                                                        <span class="kt-badge kt-badge--secondary kt-badge--inline kt-badge--pill">Inactive</span>
                                                    @endif
                                                </td>
                                                <td>{{ $config->created_at->format('Y-m-d H:i') }}</td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <a href="{{ route('ppk-rating-config.show', $config->id) }}" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="View">
                                                            <i class="la la-eye"></i>
                                                        </a>
                                                        <a href="{{ route('ppk-rating-config.edit', $config->id) }}" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Edit">
                                                            <i class="la la-edit"></i>
                                                        </a>
                                                        @if(!$config->is_active)
                                                            <form action="{{ route('ppk-rating-config.activate', $config->id) }}" method="POST" style="display:inline;">
                                                                @csrf
                                                                @method('PATCH')
                                                                <button type="submit" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Activate">
                                                                    <i class="la la-check-circle"></i>
                                                                </button>
                                                            </form>
                                                        @endif
                                                        <form action="{{ route('ppk-rating-config.duplicate', $config->id) }}" method="POST" style="display:inline;">
                                                            @csrf
                                                            <button type="submit" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Duplicate">
                                                                <i class="la la-copy"></i>
                                                            </button>
                                                        </form>
                                                        <form action="{{ route('ppk-rating-config.destroy', $config->id) }}" method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this configuration?');">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Delete">
                                                                <i class="la la-trash"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <div class="mt-3">
                                {{ $configs->links() }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>

    @include('admin.layouts.scripts')
    <script src="{{ asset('assets/vendors/custom/datatables/datatables.bundle.js') }}" type="text/javascript"></script>
    <script>
        $(document).ready(function() {
            $('#config_table').DataTable({
                responsive: true,
                paging: false,
                searching: true,
                ordering: true,
                info: false
            });
        });
    </script>
</body>
</html>

