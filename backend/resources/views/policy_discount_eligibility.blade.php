<!DOCTYPE html>
<html lang="en">

@include('admin.layouts.header')
<link href="{{  asset('assets/vendors/custom/datatables/datatables.bundle.css') }}" rel="stylesheet" type="text/css" />
<meta name="csrf-token" content="{{ csrf_token() }}">

<style>
/* Custom styles for policy discount eligibility table */
#policy_discount_table {
    width: 100% !important;
}

#policy_discount_table th,
#policy_discount_table td {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    vertical-align: middle;
}

/* Ensure table container doesn't overflow */
.table-responsive {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}

/* Specific column adjustments */
#policy_discount_table td:nth-child(1), /* Policy Number */
#policy_discount_table th:nth-child(1) {
    min-width: 120px;
    max-width: 120px;
}

#policy_discount_table td:nth-child(2), /* Customer */
#policy_discount_table th:nth-child(2) {
    min-width: 120px;
    max-width: 120px;
}

#policy_discount_table td:nth-child(3), /* Product */
#policy_discount_table th:nth-child(3) {
    min-width: 100px;
    max-width: 100px;
}

#policy_discount_table td:nth-child(11), /* Action */
#policy_discount_table th:nth-child(11) {
    min-width: 120px;
    max-width: 120px;
}

/* Responsive adjustments for smaller screens */
@media (max-width: 768px) {
    #policy_discount_table th,
    #policy_discount_table td {
        font-size: 12px;
        padding: 8px 4px;
    }
    
    .kt-portlet__body {
        padding: 15px;
    }
}

/* DataTable responsive details styling */
table.dataTable.dtr-inline.collapsed > tbody > tr > td.child,
table.dataTable.dtr-inline.collapsed > tbody > tr > th.child,
table.dataTable.dtr-inline.collapsed > tbody > tr > td.dataTables_empty {
    cursor: default !important;
}

table.dataTable.dtr-inline.collapsed > tbody > tr[role="row"] > td:first-child,
table.dataTable.dtr-inline.collapsed > tbody > tr[role="row"] > th:first-child {
    position: relative;
    padding-left: 30px;
    cursor: pointer;
}

table.dataTable.dtr-inline.collapsed > tbody > tr[role="row"] > td:first-child:before,
table.dataTable.dtr-inline.collapsed > tbody > tr[role="row"] > th:first-child:before {
    top: 50%;
    left: 4px;
    height: 1em;
    width: 1em;
    margin-top: -9px;
    display: block;
    position: absolute;
    color: white;
    border: 2px solid white;
    border-radius: 3px;
    box-shadow: 0 0 3px #444;
    box-sizing: content-box;
    text-align: center;
    text-indent: 0 !important;
    font-family: 'Courier New', Courier, monospace;
    line-height: 1em;
    content: '+';
    background-color: #31b131;
}
</style>

<!-- begin::Body -->
<body class="kt-header--fixed kt-header-mobile--fixed kt-subheader--enabled kt-subheader--transparent kt-aside--enabled kt-aside--fixed kt-aside--minimize kt-page--loading">
<!-- begin:: Header Mobile -->
<div id="kt_header_mobile" class="kt-header-mobile kt-header-mobile--fixed">
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
<!-- end:: Header Mobile -->
<!-- begin:: Root -->
<div class="kt-grid kt-grid--hor kt-grid--root">
    <!-- begin:: Page -->
    <div class="kt-grid__item kt-grid__item--fluid kt-grid kt-grid--ver kt-page">

        @include('admin.layouts.sidebar')
        @include('admin.layouts.topNav')

    </div>
    <div class="kt-grid__item kt-grid__item--fluid kt-grid kt-grid--hor">
        <!-- begin:: Subheader -->
        <div class="kt-subheader kt-grid__item" id="kt_subheader">
            <div class="kt-subheader__main">
                <h3 class="kt-subheader__title">
                    Policy Discount Eligibility
                </h3>
                <span class="kt-subheader__separator kt-hidden"></span>
                <div class="kt-subheader__breadcrumbs">
                    <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                    <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin-dashboard')}}" class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span class="kt-subheader__breadcrumbs-separator"></span>Policies
                    <span class="kt-subheader__breadcrumbs-separator"></span>Discount Eligibility
                </div>
            </div>
            <div class="kt-subheader__toolbar">
                <div class="kt-subheader__wrapper">
                    <a href="{{ route('admin.month-rate-settings') }}" class="btn btn-brand btn-bold">
                        <i class="fa fa-cog"></i> Rate Settings
                    </a>
                </div>
            </div>
        </div>
        <!-- end:: Subheader -->

        <!-- begin:: Content -->
        <div class="kt-content kt-grid__item kt-grid__item--fluid">

            <!--Begin::App-->
            <div class="kt-grid kt-grid--desktop kt-grid--ver kt-grid--ver-desktop kt-app">

                <!--Begin:: App Content-->
                <div class="kt-grid__item kt-grid__item--fluid kt-app__content">
                    <div class="row">
                        <div class="col-lg-12">

                            <!--begin::Portlet-->
                            <div class="kt-portlet kt-portlet--last kt-portlet--head-lg kt-portlet--responsive-mobile">
                                <div class="kt-portlet__head">
                                    <div class="kt-portlet__head-label">
                                        <h3 class="kt-portlet__head-title">Policies Eligible for Month Rate Discounts</h3>
                                    </div>
                                </div>
                                <div class="kt-portlet__body">

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

                                    <div class="alert alert-info">
                                        <h5><i class="icon fa fa-info"></i> Eligibility Criteria!</h5>
                                        <p><strong>Product IDs 1,2,4,5,9,10:</strong> Count 24+ successful transactions as months directly.</p>
                                        <p><strong>Product IDs 3,7,8:</strong> Check premium frequency:</p>
                                        <ul>
                                            <li><strong>Monthly (freq=1 or null):</strong> 24+ transactions = 24+ months</li>
                                            <li><strong>Quarterly (freq=2):</strong> 6+ transactions = 24+ months</li>
                                            <li><strong>Yearly (freq=3):</strong> 2+ transactions = 24+ months</li>
                                        </ul>
                                    </div>

                                    <div class="table-responsive">
                                        <table class="table table-striped table-bordered" id="policy_discount_table">
                                            <thead>
                                                <tr>
                                                    <th>Policy Number</th>
                                                    <th>Customer</th>
                                                    <th>Product</th>
                                                    <th>Premium Frequency</th>
                                                    <th>Current Premium</th>
                                                    <th>Successful Transactions</th>
                                                    <th>Calculated Months</th>
                                                    <th>Discount Rate</th>
                                                    <th>Discount Amount</th>
                                                    <th>Policy Activated</th>
                                                    <th>Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <!-- Data will be loaded via AJAX -->
                                            </tbody>
                                        </table>
                                    </div>

                                </div>
                            </div>
                            <!--end::Portlet-->

                        </div>
                    </div>
                </div>
                <!--End:: App Content-->

            </div>
            <!--End::App-->

        </div>
        <!-- end:: Content -->
    </div>
</div>
<!-- end:: Page -->

<!-- Apply Discount Modal -->
<div class="modal fade" id="applyDiscountModal" tabindex="-1" role="dialog" aria-labelledby="applyDiscountModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="applyDiscountModalLabel">Apply Month Rate Discount</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Policy Number:</strong> <span id="modal-policy-number"></span></p>
                        <p><strong>Calculated Months:</strong> <span id="modal-calculated-months"></span></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Discount Rate:</strong> <span id="modal-discount-rate"></span>%</p>
                        <p><strong>Discount Amount:</strong> P<span id="modal-discount-amount"></span></p>
                    </div>
                </div>
                <div class="alert alert-info">
                    <p class="mb-0"><strong>Note:</strong> The actual discount application functionality will be implemented in the next phase as requested.</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-success" id="confirmApplyDiscount">
                    <i class="fa fa-percentage"></i> Apply Discount
                </button>
            </div>
        </div>
    </div>
</div>

@include('admin.layouts.scripts')
<script src="{{  asset('assets/vendors/custom/datatables/datatables.bundle.js') }}" type="text/javascript"></script>

<script>
var KTDatatablesDataSourceAjaxServer = function() {
    var table = '';
    
    var initTable1 = function() {
        // begin first table
        table = $('#policy_discount_table').DataTable({
            responsive: {
                details: {
                    type: 'column',
                    target: 'tr'
                }
            },
            processing: true,
            serverSide: false, // We're getting all data at once for now
            language: { 
                processing: "<img src='{{asset('img/loading.gif')}}'>"
            },
            ajax: {
                url: '{!! route('admin.policy-discount-eligibility.data') !!}',
                type: 'GET'
            },
            columns: [
                {
                    data: 'policy_number', 
                    name: 'policy_number',
                    responsivePriority: 1,
                    width: '120px'
                },
                {
                    data: 'customer', 
                    name: 'customer',
                    responsivePriority: 3,
                    width: '120px'
                },
                {
                    data: 'product', 
                    name: 'product',
                    responsivePriority: 5,
                    width: '100px'
                },
                {
                    data: 'premium_freq', 
                    name: 'premium_freq',
                    responsivePriority: 7,
                    width: '80px',
                    className: 'text-center'
                },
                {
                    data: 'premium', 
                    name: 'premium',
                    responsivePriority: 4,
                    width: '90px',
                    className: 'text-right'
                },
                {
                    data: 'successful_transactions', 
                    name: 'successful_transactions',
                    responsivePriority: 6,
                    width: '80px',
                    className: 'text-center'
                },
                {
                    data: 'calculated_months', 
                    name: 'calculated_months',
                    responsivePriority: 2,
                    width: '100px',
                    className: 'text-center'
                },
                {
                    data: 'discount_rate', 
                    name: 'discount_rate',
                    responsivePriority: 3,
                    width: '80px',
                    className: 'text-center'
                },
                {
                    data: 'discount_amount', 
                    name: 'discount_amount',
                    responsivePriority: 2,
                    width: '100px',
                    className: 'text-right'
                },
                {
                    data: 'activated_date', 
                    name: 'activated_date',
                    responsivePriority: 8,
                    width: '90px',
                    className: 'text-center'
                },
                {
                    data: 'action', 
                    name: 'action', 
                    orderable: false, 
                    searchable: false,
                    responsivePriority: 1,
                    width: '120px',
                    className: 'text-center'
                }
            ],
            order: [[6, 'desc']], // Order by calculated_months descending
            pageLength: 25,
            lengthMenu: [10, 25, 50, 100],
            scrollX: false,
            autoWidth: false,
            dom: `<'row'<'col-sm-6 text-left'f><'col-sm-6 text-right'B>>
            <'row'<'col-sm-12'tr>>
            <'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7 dataTables_pager'lp>>`,
            drawCallback: function() {
                // Reattach event handlers after each draw
                attachDiscountButtonEvents();
            }
        });
    };

    return {
        //main function to initiate the module
        init: function() {
            initTable1();
        },
        draw: function(){
            table.draw();
        }
    };
}();

function attachDiscountButtonEvents() {
    // Handle Apply Discount button click
    $('.apply-discount-btn').off('click').on('click', function() {
        const policyId = $(this).data('policy-id');
        const policyNumber = $(this).data('policy-number');
        const discountRate = $(this).data('discount-rate');
        const discountAmount = $(this).data('discount-amount');
        const calculatedMonths = $(this).data('calculated-months');
        
        // Store data for later use
        $(this).closest('tr').data('apply-discount-data', {
            policyId: policyId,
            policyNumber: policyNumber,
            discountRate: discountRate,
            discountAmount: discountAmount,
            calculatedMonths: calculatedMonths
        });
        
        // Populate modal
        $('#modal-policy-number').text(policyNumber);
        $('#modal-calculated-months').text(calculatedMonths + ' months');
        $('#modal-discount-rate').text(discountRate);
        $('#modal-discount-amount').text(discountAmount);
        
        // Store policy data in confirm button
        $('#confirmApplyDiscount').data('policy-data', {
            policyId: policyId,
            policyNumber: policyNumber,
            discountRate: discountRate,
            discountAmount: discountAmount,
            calculatedMonths: calculatedMonths
        });
        
        // Show modal
        $('#applyDiscountModal').modal('show');
    });
    
    // Handle Confirm Apply Discount button click
    $('#confirmApplyDiscount').off('click').on('click', function() {
        const policyData = $(this).data('policy-data');
        
        if (!policyData) {
            alert('Error: Policy data not found');
            return;
        }
        
        // Disable button and show loading
        $(this).prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Applying...');
        
        // Make AJAX request to apply discount
        $.ajax({
            url: '{!! route('admin.apply-policy-discount') !!}',
            type: 'POST',
            data: {
                _token: $('meta[name="csrf-token"]').attr('content'),
                policy_id: policyData.policyId,
                policy_number: policyData.policyNumber,
                discount_rate: policyData.discountRate,
                discount_amount: policyData.discountAmount,
                original_premium: (policyData.discountAmount * 100 / policyData.discountRate), // Calculate original premium
                calculated_months: policyData.calculatedMonths,
                notes: 'Discount applied via Policy Discount Eligibility system'
            },
            success: function(response) {
                if (response.success) {
                    // Close modal immediately
                    $('#applyDiscountModal').modal('hide');
                    
                    // Refresh the table data
                    KTDatatablesDataSourceAjaxServer.draw();
                }
                
                // Re-enable button after all operations
                $('#confirmApplyDiscount').prop('disabled', false).html('<i class="fa fa-percentage"></i> Apply Discount');
            },
            error: function(xhr) {
                // Re-enable button first
                $('#confirmApplyDiscount').prop('disabled', false).html('<i class="fa fa-percentage"></i> Apply Discount');
            }
        });
    });
}

jQuery(document).ready(function() {
    KTDatatablesDataSourceAjaxServer.init();
    
    // Reset button state when modal is closed
    $('#applyDiscountModal').on('hidden.bs.modal', function () {
        $('#confirmApplyDiscount').prop('disabled', false).html('<i class="fa fa-percentage"></i> Apply Discount');
    });
    
    // Auto-dismiss alerts after 5 seconds
    setTimeout(function() {
        $('.alert').fadeOut('slow');
    }, 5000);
});
</script>

</body>
<!-- end::Body -->
</html>