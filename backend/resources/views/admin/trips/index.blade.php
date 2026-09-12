<!DOCTYPE html>
<html lang="en">

@include('admin.layouts.header')
<link href="{{ asset('assets/vendors/custom/datatables/datatables.bundle.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ asset('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ asset('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}" rel="stylesheet" type="text/css" />

<!-- begin::Body -->
<body class="kt-header--fixed kt-header-mobile--fixed kt-subheader--enabled kt-subheader--transparent kt-aside--enabled kt-aside--fixed kt-aside--minimize kt-page--loading">
    <!-- begin:: Header Mobile -->
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
    <!-- end:: Header Mobile -->

    <!-- begin:: Root -->
    <div class="kt-grid kt-grid--hor kt-grid--root">
        <!-- begin:: Page -->
        <div class="kt-grid__item kt-grid__item--fluid kt-grid kt-grid--ver kt-page">
            @include('admin.layouts.sidebar')
            @include('admin.layouts.topNav')
        </div>

        <!--If Password default -->
        @if (Auth::user()->password == null)
            @include('includes.reset')
        @else
            <div class="kt-grid__item kt-grid__item--fluid kt-grid kt-grid--hor">
                <!-- begin:: Subheader -->
                <div class="kt-subheader kt-grid__item" id="kt_subheader">
                    <div class="kt-subheader__main">
                        <h3 class="kt-subheader__title">
                            Webfleet Trips
                        </h3>
                        <span class="kt-subheader__separator kt-hidden"></span>
                        <div class="kt-subheader__breadcrumbs">
                            <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                            <span class="kt-subheader__breadcrumbs-separator"></span>
                            <a href="{{ Route('admin-dashboard') }}" class="kt-subheader__breadcrumbs-link"> Dashboard </a>
                            <span class="kt-subheader__breadcrumbs-separator"></span>
                            <a href="{{ Route('admin.trips.index') }}" class="kt-subheader__breadcrumbs-link">
                                <span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">Trips</span>
                            </a>
                        </div>
                    </div>
                </div>
                <!-- end:: Subheader -->

                <!-- begin:: Content -->
                <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
                    <div class="kt-portlet kt-portlet--mobile">
                        <div class="kt-portlet__head">
                            <div class="kt-portlet__head-label">
                                <h3 class="kt-portlet__head-title">
                                    Trip Records
                                </h3>
                            </div>
                        </div>
                        <div class="kt-portlet__body">
                            <!--begin: Datatable -->
                            <table class="table table-striped table-bordered table-hover" id="trips_table" style="width:100%">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Trip ID</th>
                                        <th>Object</th>
                                        <th>Driver</th>
                                        <th>Start Time</th>
                                        <th>End Time</th>
                                        <th>Duration</th>
                                        <th>Distance</th>
                                        <th>Speed Info</th>
                                        <th>Idle Time</th>
                                        <th>Performance</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                            </table>
                            <!--end: Datatable -->
                        </div>
                    </div>
                </div>
                <!-- end:: Content -->
            </div>
        @endif
    </div>
    <!-- end:: Page -->
    <!-- end:: Root -->

    <!-- Trip Details Modal -->
    <div class="modal fade" id="tripDetailsModal" tabindex="-1" role="dialog" aria-labelledby="tripDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="tripDetailsModalLabel">Trip Details</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body" id="tripDetailsContent">
                    <div class="text-center">
                        <div class="spinner-border" role="status">
                            <span class="sr-only">Loading...</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    @include('admin.layouts.scripts')
    <script src="{{ asset('assets/vendors/custom/datatables/datatables.bundle.js') }}" type="text/javascript"></script>

    <script type="text/javascript">
        $(document).ready(function() {
            // Initialize DataTable
            var table = $('#trips_table').DataTable({
                processing: true,
                serverSide: true,
                ajax: "{{ route('admin.trips.data') }}",
                columns: [
                    { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
                    { data: 'ext_trip_id', name: 'ext_trip_id' },
                    { data: 'objectname', name: 'objectname', defaultContent: 'N/A' },
                    { data: 'drivername', name: 'drivername', defaultContent: 'N/A' },
                    { data: 'start_time', name: 'start_time' },
                    { data: 'end_time', name: 'end_time' },
                    { data: 'duration', name: 'duration', orderable: false },
                    { data: 'distance', name: 'distance', orderable: false },
                    { data: 'speed_info', name: 'speed_info', orderable: false },
                    { data: 'idle_duration', name: 'idle_duration', orderable: false },
                    { data: 'performance', name: 'performance', orderable: false },
                    { data: 'actions', name: 'actions', orderable: false, searchable: false }
                ],
                order: [[4, 'desc']], // Order by start_time descending
                responsive: true,
                pageLength: 25,
                lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]]
            });

            // View trip details
            $('#trips_table').on('click', '.view-trip', function() {
                var tripId = $(this).data('id');
                $('#tripDetailsModal').modal('show');
                $('#tripDetailsContent').html('<div class="text-center"><div class="spinner-border" role="status"><span class="sr-only">Loading...</span></div></div>');

                $.ajax({
                    url: "{{ url('admin/trips') }}/" + tripId,
                    type: 'GET',
                    success: function(trip) {
                        var html = '<div class="row">';
                        
                        // Trip Information
                        html += '<div class="col-md-6">';
                        html += '<h5 class="mb-3">Trip Information</h5>';
                        html += '<table class="table table-sm">';
                        html += '<tr><th width="40%">Trip ID:</th><td>' + (trip.ext_trip_id || 'N/A') + '</td></tr>';
                        html += '<tr><th>Object:</th><td>' + (trip.objectname || 'N/A') + ' (' + (trip.objectno || 'N/A') + ')</td></tr>';
                        html += '<tr><th>Driver:</th><td>' + (trip.drivername || 'N/A') + '</td></tr>';
                        html += '<tr><th>Trip Mode:</th><td>' + (trip.tripmode || 'N/A') + '</td></tr>';
                        html += '<tr><th>Fuel Type:</th><td>' + (trip.fueltype || 'N/A') + '</td></tr>';
                        html += '</table>';
                        html += '</div>';

                        // Time & Distance
                        html += '<div class="col-md-6">';
                        html += '<h5 class="mb-3">Time & Distance</h5>';
                        html += '<table class="table table-sm">';
                        html += '<tr><th width="40%">Start Time:</th><td>' + (trip.start_time || 'N/A') + '</td></tr>';
                        html += '<tr><th>End Time:</th><td>' + (trip.end_time || 'N/A') + '</td></tr>';
                        html += '<tr><th>Duration:</th><td>' + (trip.duration_s ? Math.floor(trip.duration_s / 60) + 'm ' + (trip.duration_s % 60) + 's' : 'N/A') + '</td></tr>';
                        html += '<tr><th>Idle Time:</th><td>' + (trip.idle_time ? Math.floor(trip.idle_time / 60) + 'm ' + (trip.idle_time % 60) + 's' : 'N/A') + '</td></tr>';
                        html += '<tr><th>Distance:</th><td>' + (trip.distance_m ? (trip.distance_m / 1000).toFixed(2) + ' km' : 'N/A') + '</td></tr>';
                        html += '</table>';
                        html += '</div>';

                        // Speed & Odometer
                        html += '<div class="col-md-6 mt-3">';
                        html += '<h5 class="mb-3">Speed & Odometer</h5>';
                        html += '<table class="table table-sm">';
                        html += '<tr><th width="40%">Avg Speed:</th><td>' + (trip.avg_speed || 0) + ' km/h</td></tr>';
                        html += '<tr><th>Max Speed:</th><td>' + (trip.max_speed || 0) + ' km/h</td></tr>';
                        html += '<tr><th>Start Odometer:</th><td>' + (trip.start_odometer ? trip.start_odometer.toLocaleString() : 'N/A') + '</td></tr>';
                        html += '<tr><th>End Odometer:</th><td>' + (trip.end_odometer ? trip.end_odometer.toLocaleString() : 'N/A') + '</td></tr>';
                        html += '</table>';
                        html += '</div>';

                        // Performance Indicators
                        html += '<div class="col-md-6 mt-3">';
                        html += '<h5 class="mb-3">Performance Indicators</h5>';
                        html += '<table class="table table-sm">';
                        html += '<tr><th width="40%">OptiDrive:</th><td>' + (trip.optidrive_indicator ? parseFloat(trip.optidrive_indicator).toFixed(3) : 'N/A') + '</td></tr>';
                        html += '<tr><th>Speeding:</th><td>' + (trip.speeding_indicator ? parseFloat(trip.speeding_indicator).toFixed(3) : 'N/A') + '</td></tr>';
                        html += '<tr><th>Driving Events:</th><td>' + (trip.drivingevents_indicator ? parseFloat(trip.drivingevents_indicator).toFixed(3) : 'N/A') + '</td></tr>';
                        html += '<tr><th>Idling:</th><td>' + (trip.idling_indicator ? parseFloat(trip.idling_indicator).toFixed(3) : 'N/A') + '</td></tr>';
                        html += '<tr><th>Constant Speed:</th><td>' + (trip.constant_speed_indicator ? parseFloat(trip.constant_speed_indicator).toFixed(3) : 'N/A') + '</td></tr>';
                        html += '<tr><th>High Revving:</th><td>' + (trip.high_revving_indicator ? parseFloat(trip.high_revving_indicator).toFixed(3) : 'N/A') + '</td></tr>';
                        html += '</table>';
                        html += '</div>';

                        // Location Information
                        html += '<div class="col-md-12 mt-3">';
                        html += '<h5 class="mb-3">Location Information</h5>';
                        html += '<table class="table table-sm">';
                        html += '<tr><th width="20%">Start Location:</th><td>' + (trip.start_postext || 'N/A') + '</td></tr>';
                        html += '<tr><th>End Location:</th><td>' + (trip.end_postext || 'N/A') + '</td></tr>';
                        html += '<tr><th>Start Coordinates:</th><td>Lat: ' + (trip.start_lat || 'N/A') + ', Lon: ' + (trip.start_lon || 'N/A') + '</td></tr>';
                        html += '<tr><th>End Coordinates:</th><td>Lat: ' + (trip.end_lat || 'N/A') + ', Lon: ' + (trip.end_lon || 'N/A') + '</td></tr>';
                        html += '</table>';
                        html += '</div>';

                        html += '</div>';
                        
                        $('#tripDetailsContent').html(html);
                    },
                    error: function() {
                        $('#tripDetailsContent').html('<div class="alert alert-danger">Error loading trip details.</div>');
                    }
                });
            });
        });
    </script>
</body>
</html>

