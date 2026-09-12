<!DOCTYPE html>
<html lang="en" >

@include('admin.layouts.header')
<link href="{{ asset('assets/vendors/custom/datatables/datatables.bundle.css') }}" rel="stylesheet" type="text/css" />

<!-- begin::Body -->
<body class="kt-header--fixed kt-header-mobile--fixed kt-subheader--enabled kt-subheader--transparent kt-aside--enabled kt-aside--fixed kt-aside--minimize kt-page--loading" >

<!-- begin:: Page -->
<div class="kt-grid kt-grid--hor kt-grid--root">
    <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
        <div class="kt-portlet kt-portlet--mobile">
            <div class="kt-portlet__head">
                <div class="kt-portlet__head-label">
                    <h3 class="kt-portlet__head-title">
                        <i class="la la-shield text-warning"></i> Sanctioned Customers
                    </h3>
                </div>
                <div class="kt-portlet__head-toolbar">
                    <a href="{{ route('admin.customerKyc') }}" class="btn btn-secondary">
                        <i class="la la-arrow-left"></i> Back to KYC
                    </a>
                </div>
            </div>
            <div class="kt-portlet__body">
                <div class="row">
                    <div class="col-sm-12">
                        <div class="alert alert-info">
                            <i class="la la-info-circle"></i>
                            This page shows all customers who have been flagged as sanctioned by the OpenSanctions API.
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-sm-12">
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered table-hover" id="sanctionedCustomersTable">
                                <thead class="thead-light">
                                    <tr>
                                        <th>Customer Name</th>
                                        <th>Sanctioned in Countries</th>
                                        <th>Program IDs</th>
                                        <th>Max Score</th>
                                        <th>Target</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($sanctionedCustomers as $customer)
                                        <tr>
                                            <td>
                                                @if($customer['kyc_id'])
                                                    <a href="{{ route('admin.viewCustomerKycData', $customer['kyc_id']) }}" class="text-primary font-weight-bold" style="text-decoration: none;">
                                                        <i class="la la-user"></i> {{ $customer['customer_name'] }}
                                                    </a>
                                                @else
                                                    <strong>{{ $customer['customer_name'] }}</strong>
                                                    <small class="text-muted d-block">No KYC data available</small>
                                                @endif
                                            </td>
                                            <td>
                                                @if(!empty($customer['countries']))
                                                    <div class="d-flex flex-wrap">
                                                        @foreach($customer['countries'] as $country)
                                                            <span class="badge badge-warning mr-1 mb-1">{{ $country }}</span>
                                                        @endforeach
                                                    </div>
                                                @else
                                                    <span class="text-muted">No data available</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if(!empty($customer['programIds']))
                                                    <div class="d-flex flex-wrap">
                                                        @foreach($customer['programIds'] as $programId)
                                                            <span class="badge badge-info mr-1 mb-1">{{ $programId }}</span>
                                                        @endforeach
                                                    </div>
                                                @else
                                                    <span class="text-muted">No data available</span>
                                                @endif
                                            </td>
                                            <td>
                                                <span class="badge badge-{{ $customer['max_score'] > 0.7 ? 'danger' : ($customer['max_score'] > 0.5 ? 'warning' : 'secondary') }}">
                                                    {{ number_format($customer['max_score'], 3) }}
                                                </span>
                                            </td>
                                            <td>
                                                @if($customer['target'] === 'true' || $customer['target'] === true)
                                                    <span class="badge badge-danger">True</span>
                                                @elseif($customer['target'] === 'false' || $customer['target'] === false)
                                                    <span class="badge badge-success">False</span>
                                                @else
                                                    <span class="badge badge-secondary">{{ $customer['target'] ?? 'N/A' }}</span>
                                                @endif
                                            </td>
                                            <td>
                                                <span class="badge badge-{{ $customer['status'] === 'rejected' ? 'danger' : ($customer['status'] === 'review' ? 'warning' : 'success') }}">
                                                    {{ ucfirst($customer['status']) }}
                                                </span>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="text-center">
                                                <div class="alert alert-info mb-0">
                                                    <i class="la la-info-circle"></i>
                                                    No sanctioned customers found.
                                                </div>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                @if($sanctionedCustomers->count() > 0)
                    <div class="row mt-4">
                        <div class="col-sm-12">
                            <div class="alert alert-danger" style="border: 2px solid #dc3545; font-size: 16px; padding: 15px;">
                                <div class="d-flex align-items-center">
                                    <i class="la la-shield-alt" style="font-size: 24px; margin-right: 10px;"></i>
                                    <div>
                                        <strong style="font-size: 18px;">Total Sanctioned Customers:</strong>
                                        <span class="badge badge-light" style="font-size: 20px; margin-left: 10px; padding: 8px 12px; background-color: #fff; color: #dc3545; border: 2px solid #dc3545;">
                                            {{ $sanctionedCustomers->count() }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

@include('includes.footer')

<script>
$(document).ready(function() {
    // Initialize DataTable if there are records
    @if($sanctionedCustomers->count() > 0)
        $('#sanctionedCustomersTable').DataTable({
            "pageLength": 25,
            "order": [[ 3, "desc" ]], // Sort by Max Score descending
            "columnDefs": [
                { "orderable": false, "targets": [1, 2] } // Disable sorting on Countries and Program IDs columns
            ],
            "language": {
                "emptyTable": "No sanctioned customers found",
                "zeroRecords": "No matching records found"
            }
        });
    @endif
});
</script>

</body>
<!-- end::Body -->
</html>
