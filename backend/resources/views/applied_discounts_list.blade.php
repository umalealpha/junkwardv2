<!DOCTYPE html>
<html lang="en">

@include('admin.layouts.header')
<link href="{{  asset('assets/vendors/custom/datatables/datatables.bundle.css') }}" rel="stylesheet" type="text/css" />

<style>
/* Custom styles for applied discounts table */
#applied_discounts_table {
    width: 100% !important;
}

#applied_discounts_table th,
#applied_discounts_table td {
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

/* Responsive adjustments for smaller screens */
@media (max-width: 768px) {
    #applied_discounts_table th,
    #applied_discounts_table td {
        font-size: 12px;
        padding: 8px 4px;
    }
    
    .kt-portlet__body {
        padding: 15px;
    }
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
                    Applied Discounts List
                </h3>
                <span class="kt-subheader__separator kt-hidden"></span>
                <div class="kt-subheader__breadcrumbs">
                    <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                    <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin-dashboard')}}" class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span class="kt-subheader__breadcrumbs-separator"></span>Policies
                    <span class="kt-subheader__breadcrumbs-separator"></span>Applied Discounts
                </div>
            </div>
            <div class="kt-subheader__toolbar">
                <div class="kt-subheader__wrapper">
                    <a href="{{ route('admin.policy-discount-eligibility') }}" class="btn btn-brand btn-bold">
                        <i class="fa fa-list"></i> Eligibility List
                    </a>
                    <a href="{{ route('admin.month-rate-settings') }}" class="btn btn-secondary btn-bold ml-2">
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
                                        <h3 class="kt-portlet__head-title">Applied Month Rate Discounts</h3>
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
                                        <h5><i class="icon fa fa-info"></i> Applied Discounts Information!</h5>
                                        <p><strong>Applied:</strong> Discount is currently active on the policy.</p>
                                        <p><strong>Superseded:</strong> Discount was replaced by a higher rate discount.</p>
                                        <p><strong>Cancelled:</strong> Discount was manually cancelled by an administrator.</p>
                                    </div>

                                    <div class="table-responsive">
                                        <table class="table table-striped table-bordered" id="applied_discounts_table">
                                            <thead>
                                                <tr>
                                                    <th>Policy Number</th>
                                                    <th>Customer</th>
                                                    <th>Product</th>
                                                    <th>Premium Frequency</th>
                                                    <th>Original Premium</th>
                                                    <th>Calculated Months</th>
                                                    <th>Discount Rate</th>
                                                    <th>Discount Amount</th>
                                                    <th>Status</th>
                                                    <th>Applied By</th>
                                                    <th>Applied Date</th>
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

<!-- Cancel Discount Modal -->
<div class="modal fade" id="cancelDiscountModal" tabindex="-1" role="dialog" aria-labelledby="cancelDiscountModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="cancelDiscountModalLabel">Cancel Applied Discount</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-12">
                        <p><strong>Policy Number:</strong> <span id="cancel-modal-policy-number"></span></p>
                        <p><strong>Are you sure you want to cancel this discount?</strong></p>
                        <p class="text-muted">This action cannot be undone. The discount will be marked as cancelled and will no longer apply to the policy.</p>
                    </div>
                </div>
                <div class="form-group">
                    <label for="cancellation-reason">Cancellation Reason (Optional):</label>
                    <textarea class="form-control" id="cancellation-reason" rows="3" placeholder="Enter reason for cancelling this discount..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-danger" id="confirmCancelDiscount">
                    <i class="fa fa-times"></i> Cancel Discount
                </button>
            </div>
        </div>
    </div>
</div>

@include('admin.layouts.scripts')
<script src="{{  asset('assets/vendors/custom/datatables/datatables.bundle.js') }}" type="text/javascript"></script>

<script>
var KTDatatablesAppliedDiscounts = function() {
    var table = '';
    
    var initTable = function() {
        table = $('#applied_discounts_table').DataTable({
            responsive: {
                details: {
                    type: 'column',
                    target: 'tr'
                }
            },
            processing: true,
            serverSide: false,
            language: { 
                processing: "<img src='{{asset('img/loading.gif')}}'>"
            },
            ajax: {
                url: '{!! route('admin.applied-discounts-list.data') !!}',
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
                    responsivePriority: 4,
                    width: '120px'
                },
                {
                    data: 'product', 
                    name: 'product',
                    responsivePriority: 6,
                    width: '100px'
                },
                {
                    data: 'premium_freq', 
                    name: 'premium_freq',
                    responsivePriority: 8,
                    width: '80px',
                    className: 'text-center'
                },
                {
                    data: 'original_premium', 
                    name: 'original_premium',
                    responsivePriority: 5,
                    width: '90px',
                    className: 'text-right'
                },
                {
                    data: 'calculated_months', 
                    name: 'calculated_months',
                    responsivePriority: 7,
                    width: '100px',
                    className: 'text-center'
                },
                {
                    data: 'discount_rate', 
                    name: 'discount_rate',
                    responsivePriority: 2,
                    width: '80px',
                    className: 'text-center'
                },
                {
                    data: 'discount_amount', 
                    name: 'discount_amount',
                    responsivePriority: 3,
                    width: '100px',
                    className: 'text-right'
                },
                {
                    data: 'status', 
                    name: 'status',
                    responsivePriority: 2,
                    width: '80px',
                    className: 'text-center'
                },
                {
                    data: 'applied_by', 
                    name: 'applied_by',
                    responsivePriority: 6,
                    width: '100px'
                },
                {
                    data: 'applied_date', 
                    name: 'applied_date',
                    responsivePriority: 5,
                    width: '90px',
                    className: 'text-center'
                },
                {
                    data: 'action', 
                    name: 'action', 
                    orderable: false, 
                    searchable: false,
                    responsivePriority: 1,
                    width: '100px',
                    className: 'text-center'
                }
            ],
            order: [[10, 'desc']], // Order by applied_date descending
            pageLength: 25,
            lengthMenu: [10, 25, 50, 100],
            scrollX: false,
            autoWidth: false,
            dom: `<'row'<'col-sm-6 text-left'f><'col-sm-6 text-right'B>>
            <'row'<'col-sm-12'tr>>
            <'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7 dataTables_pager'lp>>`,
            drawCallback: function() {
                attachCancelButtonEvents();
            }
        });
    };

    return {
        init: function() {
            initTable();
        },
        draw: function(){
            table.draw();
        }
    };
}();

function attachCancelButtonEvents() {
    $('.cancel-discount-btn').off('click').on('click', function() {
        const discountId = $(this).data('discount-id');
        const policyNumber = $(this).data('policy-number');
        
        $('#cancel-modal-policy-number').text(policyNumber);
        $('#confirmCancelDiscount').data('discount-id', discountId);
        $('#confirmCancelDiscount').data('policy-number', policyNumber);
        
        $('#cancelDiscountModal').modal('show');
    });
}

jQuery(document).ready(function() {
    KTDatatablesAppliedDiscounts.init();
    
    // Auto-dismiss alerts after 5 seconds
    setTimeout(function() {
        $('.alert').fadeOut('slow');
    }, 5000);
});
</script>

</body>
<!-- end::Body -->
</html>