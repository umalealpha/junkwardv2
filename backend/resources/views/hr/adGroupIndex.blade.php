@extends('hr.layouts.app')

@section('title', 'Policy Management')
@section('page-title', 'Policy Management')
{{-- @section('page-description', 'View, search, and manage AD Group Policies') --}}

@section('content')
<!-- Policy List Card -->
<div class="hr-card">
    <div class="hr-card-header">
        <h3 class="hr-card-title">
            <i class="flaticon2-copy" style="margin-right: 8px; color: #3b82f6;"></i>
            Policy List
        </h3>
        <div class="hr-card-actions">
            <button type="button" class="btn btn-primary" id="generateQuoteBtn" data-toggle="kt-tooltip" title="" data-placement="left" data-original-title="Generate Quote" style="background-color: #3b82f6; border-color: #3b82f6; color: white; padding: 10px 20px; border-radius: 6px; font-weight: 600;">
                <i class="flaticon2-file" style="margin-right: 6px;"></i>
                Generate Quote
            </button>
        </div>
    </div>

    <div class="hr-card-body">
        <!-- Filter Form -->
        <div class="mb-4">
            <div class="d-flex gap-4 align-items-end flex-wrap">
                <div class="hr-form-group" style="flex: 1; min-width: 350px; max-width: 400px;">
                    <label class="hr-form-label">
                        <i class="la la-cube" style="margin-right: 6px; color: #1e40af;"></i>
                        Filter by Plan
                    </label>
                    <select class="hr-form-control kt-select2" id="product_plan_filter" name="product_plan_filter" style="border-radius: 8px; border: 1px solid #e2e8f0; padding: 12px 16px; font-size: 16px; height: 48px;">
                        <option value="">All Plans</option>
                        @foreach($product_plans as $productPlan)
                            @if($productPlan->product_id == 12)
                                <option value="{{ $productPlan->id }}">{{ $productPlan->slug }}</option>
                            @endif
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <!-- Data Table -->
        <div class="hr-table-container">
                <table class="hr-table" id="policy_table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Policy Number</th>
                            <th>Customer Name</th>
                            <th>Agent Name</th>
                            <th>CellPhone Number</th>
                            <th>Product</th>
                            <th>Plan Name</th>
                            <th>Premium</th>
                            <th>Status</th>
                            <th>Created At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Data will be loaded here -->
                    </tbody>
                </table>
        </div>
    </div>
</div>

<!-- Generate Quote Modal -->
<div class="modal fade" id="generateQuoteModal" tabindex="-1" role="dialog" aria-labelledby="generateQuoteModalLabel" aria-hidden="true" style="display: none;">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="generateQuoteModalLabel">Generate Quote Report</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                    <div class="row mb-3">
                        @if($employerGroups && $employerGroups->count() > 0)
                        <div class="col-md-6">
                            <label>Employer Group</label>
                            <input type="text" class="form-control" value="{{ $employerGroups->first()->name }} ({{ $employerGroups->first()->employer_group_id }})" readonly>
                            <input type="hidden" id="employerGroupFilter" value="{{ $employerGroups->first()->employer_group_id }}">
                            <small class="text-muted">Debug: Group ID {{ $employerGroups->first()->id }}, Code: {{ $employerGroups->first()->employer_group_id }}</small>
                        </div>
                        @else
                        <div class="col-md-6">
                            <label>Employer Group</label>
                            <input type="text" class="form-control" value="No employer group assigned" readonly>
                            <small class="text-danger">No employer group found for this HR user</small>
                        </div>
                        @endif
                        <div class="col-md-6">
                            <label>Filter By Policy Status</label>
                            <select class="form-control" id="quotePolicyStatusFilter">
                                <option value="">All Statuses</option>
                                <option value="0">Deactivated</option>
                                <option value="1">Activated</option>
                                <option value="2">Cancel</option>
                                <option value="3">Expired</option>
                            </select>
                        </div>
                    </div>
                <div class="table-responsive">
                    <table class="table table-striped table-bordered" id="quoteTable">
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="selectAllQuote"></th>
                                <th>Principle FullNames</th>
                                <th>Age Group</th>
                                <th>Gender/Sex</th>
                                <th>No of Dependents</th>
                                <th>Health Insurance Plan</th>
                                <th>Premium</th>
                            </tr>
                        </thead>
                        <tbody id="quoteTableBody">
                            <!-- Data will be loaded here -->
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="generateQuoteReport">Generate Quote Report</button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')


<script src="{{  asset('assets/vendors/custom/datatables/datatables.bundle.js') }}" type="text/javascript"></script>
<script src="{{  asset('assets/vendors/general/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}" type="text/javascript"></script>
<script src="{{  asset('assets/vendors/custom/components/vendors/bootstrap-datepicker/init.js') }}" type="text/javascript"></script>


<style>
    /* Modal centering and styling */
    .modal-dialog-centered {
        display: flex;
        align-items: center;
        min-height: calc(100% - 1rem);
    }
    
    .modal-content {
        border-radius: 12px;
        border: none;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
    }
    
    .modal-header {
        background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
        color: white;
        border-radius: 12px 12px 0 0;
        border-bottom: none;
    }
    
    .modal-title {
        font-weight: 600;
        font-size: 1.25rem;
    }
    
    .close {
        color: white;
        opacity: 0.8;
    }
    
    .close:hover {
        color: white;
        opacity: 1;
    }
    
    .modal-body {
        padding: 24px;
    }
    
    .modal-footer {
        border-top: 1px solid #e5e7eb;
        padding: 16px 24px;
    }
</style>

<script>
    $(function () {
        var policyStatus_filter = -1;
        var product_filter = -1;
        var FilterBy = -1;
        var value_filter = '';
        var agent_filter = -1;
        var lead_agent = -1;
        var paymentMethod_filter = -1;
        var product_plan_filter = -1;

        var KTDatatablesDataSourceAjaxServer = function() {
            var table = '';
            var initTable1 = function() {
                table = $('#policy_table').DataTable({
                    responsive: true,
                    searchDelay: 500,
                    processing: true,
                    language:{
                        processing : "Loading data..."
                    },
                    serverSide: true,
                    ajax: {
                        url: '{{ route('hr.ad-group-policy-data') }}',
                        data: function (d) {
                            d.policyStatus_filter = policyStatus_filter;
                            d.product_filter = product_filter;
                            d.FilterBy = FilterBy;
                            d.value_filter = value_filter;
                            d.agent_filter = agent_filter;
                            d.lead_agent = lead_agent;
                            d.paymentMethod_filter = paymentMethod_filter;
                            d.product_plan_filter = product_plan_filter;
                        },
                        error: function(xhr, error, thrown) {
                            console.error('DataTable AJAX Error:', { xhr: xhr, error: error, thrown: thrown });
                        }
                    },
                    order: [0, 'DESC'],
                    columnDefs: [
                        { width: 200, targets: 5},
                        { width: 180, targets: 9}
                    ],
                    columns: [
                        {data: 'id'},
                        {data: 'view'},
                        {data: 'name'},
                        {data: 'agentName'},
                        {data: 'cellphone'},
                        {data: 'product_name'},
                        {data: 'productPlanName'},
                        {data: 'premium'},
                        {data: 'status'},
                        {data: 'created_at'}
                    ]
                });
            };

            return {
                init: function() {
                    initTable1();
                },
                draw: function(){
                    table.draw();
                }
            };
        }();

        jQuery(document).ready(function() {
            KTDatatablesDataSourceAjaxServer.init();
            
            // Initialize modal events
            $('#generateQuoteModal').on('hidden.bs.modal', function () {
                // Clear the table when modal is closed
                $('#quoteTableBody').empty();
            });
        });

        // Wire up product plan filter
        $('#product_plan_filter').on('change', function(){
            product_plan_filter = $(this).val() || -1;
            KTDatatablesDataSourceAjaxServer.draw();
        });

        // Generate Quote functionality
        $('#generateQuoteBtn').on('click', function() {
            $('#generateQuoteModal').modal('show');
            loadQuoteData();
        });

        $('#debugHrUserBtn').on('click', function() {
            $.ajax({
                url: '{{ route("hr.debug-user") }}',
                method: 'GET',
                success: function(response) {
                    console.log('Debug HR User Response:', response);
                    alert('Debug info logged to console. Check browser console for details.');
                },
                error: function(xhr, status, error) {
                    console.error('Debug error:', error);
                    alert('Error getting debug info: ' + error);
                }
            });
        });

        $('#quotePolicyStatusFilter').on('change', function() {
            loadQuoteData();
        });

        $('#selectAllQuote').on('change', function() {
            $('.quote-checkbox').prop('checked', $(this).is(':checked'));
        });

        function loadQuoteData() {
            var employerGroupId = $('#employerGroupFilter').val();
            var policyStatus = $('#quotePolicyStatusFilter').val();
            
            console.log('Loading quote data with:', { employerGroupId, policyStatus });
            console.log('AJAX URL:', '{{ route("hr.get-quote-data") }}');
            
            // If no employer group is selected, don't load data
            if (!employerGroupId) {
                console.log('No employer group selected, skipping data load');
                $('#quoteTableBody').html('<tr><td colspan="7" class="text-center text-warning">No employer group assigned to your account</td></tr>');
                return;
            }
            
            $.ajax({
                url: '{{ route("hr.get-quote-data") }}',
                method: 'GET',
                data: {
                    employer_group_id: employerGroupId,
                    policy_status: policyStatus,
                    _t: new Date().getTime() // Cache busting
                },
                beforeSend: function() {
                    $('#quoteTableBody').html('<tr><td colspan="7" class="text-center">Loading...</td></tr>');
                },
                success: function(response) {
                    console.log('Success response:', response);
                    if (response.debug) {
                        console.log('Debug info:', response.debug);
                    }
                    var tbody = $('#quoteTableBody');
                    tbody.empty();
                    
                    if (response && response.data && response.data.length > 0) {
                        $.each(response.data, function(index, policy) {
                            var row = `
                                <tr>
                                    <td><input type="checkbox" class="quote-checkbox" value="${policy.id}"></td>
                                    <td>${policy.employee_name || 'N/A'}</td>
                                    <td>${policy.age_group || 'N/A'}</td>
                                    <td>${policy.gender || 'N/A'}</td>
                                    <td>${policy.total_beneficiaries || '0'}</td>
                                    <td>${policy.health_plan || 'N/A'}</td>
                                    <td>P ${parseFloat(policy.premium || 0).toFixed(2)}</td>
                                </tr>
                            `;
                            tbody.append(row);
                        });
                    } else {
                        tbody.append('<tr><td colspan="7" class="text-center">No data available</td></tr>');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error loading quote data:', error);
                    console.error('Status:', status);
                    console.error('Response:', xhr.responseText);
                    console.error('Status Code:', xhr.status);
                    
                    var errorMessage = 'Error loading data';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMessage = xhr.responseJSON.message;
                    } else if (xhr.status === 404) {
                        errorMessage = 'Route not found. Please check if the route is properly defined.';
                    } else if (xhr.status === 500) {
                        errorMessage = 'Server error. Please check the logs for more details.';
                    } else if (xhr.status === 0) {
                        errorMessage = 'Network error. Please check your connection.';
                    }
                    
                    $('#quoteTableBody').html('<tr><td colspan="7" class="text-center text-danger">' + errorMessage + '</td></tr>');
                }
            });
        }


        $('#generateQuoteReport').on('click', function() {
            var selectedPolicies = [];
            $('.quote-checkbox:checked').each(function() {
                selectedPolicies.push($(this).val());
            });
            
            if (selectedPolicies.length === 0) {
                alert('Please select at least one policy to generate the quote report.');
                return;
            }
            
            // Generate the quote report
            var form = $('<form>', {
                'method': 'POST',
                'action': '{{ route("hr.generate-quote-report") }}',
                'target': '_blank'
            });
            
            form.append($('<input>', {
                'type': 'hidden',
                'name': '_token',
                'value': '{{ csrf_token() }}'
            }));
            
            $.each(selectedPolicies, function(index, policyId) {
                form.append($('<input>', {
                    'type': 'hidden',
                    'name': 'policy_ids[]',
                    'value': policyId
                }));
            });
            
            $('body').append(form);
            form.submit();
            form.remove();
            
            $('#generateQuoteModal').modal('hide');
        });
    });
</script>
@endsection
