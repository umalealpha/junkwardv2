<!DOCTYPE html>
<html lang="en" >
@include('admin.layouts.header')
<link href="{{ asset('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ asset('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}" rel="stylesheet" type="text/css" />
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
    <div class="kt-grid__item kt-grid__item--fluid kt-grid--hor">
        <div class="kt-subheader kt-grid__item" id="kt_subheader">
            <div class="kt-subheader__main">
                <h3 class="kt-subheader__title">Edit Customer Benefits</h3>
                <span class="kt-subheader__separator kt-hidden"></span>
                <div class="kt-subheader__breadcrumbs">
                    <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                    <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin-dashboard')}}" class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span class="kt-subheader__breadcrumbs-separator"></span>
                                            <a href="{{route('customer-rewards.index')}}" class="kt-subheader__breadcrumbs-link"> Customer Rewards </a>
                    <span class="kt-subheader__breadcrumbs-separator"></span>
                    <span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">Edit Benefits</span>
                </div>
            </div>
        </div>
        <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
            <div class="kt-portlet">
                <div class="kt-portlet__head">
                    <div class="kt-portlet__head-label">
                        <h3 class="kt-portlet__head-title">
                            Customer: {{ $customer->firstName }} {{ $customer->lastName }}
                        </h3>
                    </div>
                </div>
                <div class="kt-portlet__body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>Email:</strong></td>
                                    <td>{{ $customer->email }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Points:</strong></td>
                                    <td>{{ number_format($customer->point) }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Current Tier:</strong></td>
                                    <td>{{ $customer->rewardTier ? $customer->rewardTier->name : 'No Tier Assigned' }}</td>
                                </tr>
                            </table>
                        </div>
                    </div>

                                            <form action="{{ route('customer-rewards.update', $customer->id) }}" method="POST">
                        @csrf
                        @method('PUT')
                        
                        <div class="kt-portlet__head">
                            <div class="kt-portlet__head-label">
                                <h3 class="kt-portlet__head-title">
                                    Customer Rewards & Benefits
                                </h3>
                            </div>
                            <div class="kt-portlet__head-toolbar">
                                <button type="button" class="btn btn-sm btn-brand" id="add-benefit">
                                    <i class="la la-plus"></i> Add Reward
                                </button>
                            </div>
                        </div>

                        <div class="kt-portlet__body">
                            <div class="alert alert-info">
                                <strong>Current Rewards:</strong> {{ $customerRewards->count() }} reward(s) assigned to this customer.
                                <br>
                                <strong>Note:</strong> You can add multiple rewards/benefits for this customer. Each reward can have different status, expiry dates, and claim dates.
                            </div>
                            <div id="benefits-container">
                                @if($customerRewards->count() > 0)
                                    @foreach($customerRewards as $index => $customerBenefit)
                                    <div class="benefit-row" data-index="{{ $index }}">
                                        <div class="row">
                                            <!-- Hidden ID field for existing records -->
                                            <input type="hidden" name="benefits[{{ $index }}][id]" value="{{ $customerBenefit->id }}">
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label>Benefit</label>
                                                    <select name="benefits[{{ $index }}][benefit_id]" class="form-control" required>
                                                        <option value="">Select Benefit</option>
                                                        @foreach($benefits as $benefit)
                                                            <option value="{{ $benefit->id }}" {{ $customerBenefit->benefit_id == $benefit->id ? 'selected' : '' }}>
                                                                {{ $benefit->tag }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-2">
                                                <div class="form-group">
                                                    <label>Status</label>
                                                    <select name="benefits[{{ $index }}][status]" class="form-control" required>
                                                        <option value="active" {{ $customerBenefit->status == 'active' ? 'selected' : '' }}>Active</option>
                                                        <option value="claimed" {{ $customerBenefit->status == 'claimed' ? 'selected' : '' }}>Claimed</option>
                                                        <option value="expired" {{ $customerBenefit->status == 'expired' ? 'selected' : '' }}>Expired</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-2">
                                                <div class="form-group">
                                                    <label>Expiry Date</label>
                                                    <input type="date" name="benefits[{{ $index }}][expiry_date]" class="form-control" value="{{ $customerBenefit->expiry_date }}">
                                                </div>
                                            </div>
                                            <div class="col-md-2">
                                                <div class="form-group">
                                                    <label>Claim Date</label>
                                                    <input type="date" name="benefits[{{ $index }}][claim_date]" class="form-control" value="{{ $customerBenefit->claim_date }}">
                                                </div>
                                            </div>
                                            <div class="col-md-2">
                                                <div class="form-group">
                                                    <label>&nbsp;</label>
                                                    <button type="button" class="btn btn-sm btn-danger remove-benefit">
                                                        <i class="la la-trash"></i> Remove
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    @endforeach
                                @else
                                    <div class="benefit-row" data-index="0">
                                        <div class="row">
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label>Benefit</label>
                                                    <select name="benefits[0][benefit_id]" class="form-control" required>
                                                        <option value="">Select Benefit</option>
                                                        @foreach($benefits as $benefit)
                                                            <option value="{{ $benefit->id }}">{{ $benefit->tag }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-2">
                                                <div class="form-group">
                                                    <label>Status</label>
                                                    <select name="benefits[0][status]" class="form-control" required>
                                                        <option value="active">Active</option>
                                                        <option value="claimed">Claimed</option>
                                                        <option value="expired">Expired</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-2">
                                                <div class="form-group">
                                                    <label>Expiry Date</label>
                                                    <input type="date" name="benefits[0][expiry_date]" class="form-control">
                                                </div>
                                            </div>
                                            <div class="col-md-2">
                                                <div class="form-group">
                                                    <label>Claim Date</label>
                                                    <input type="date" name="benefits[0][claim_date]" class="form-control">
                                                </div>
                                            </div>
                                            <div class="col-md-2">
                                                <div class="form-group">
                                                    <label>&nbsp;</label>
                                                    <button type="button" class="btn btn-sm btn-danger remove-benefit">
                                                        <i class="la la-trash"></i> Remove
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <div class="kt-portlet__foot">
                            <div class="kt-form__actions">
                                <div class="row">
                                    <div class="col-2"></div>
                                    <div class="col-10">
                                        <button type="submit" class="btn btn-brand">Update Benefits</button>
                                                                                    <a href="{{ route('customer-rewards.index') }}" class="btn btn-secondary">Cancel</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    @include('includes.footer')
</div>
@include('admin.layouts.scripts')
<script>
    $(document).ready(function() {
        let benefitIndex = {{ $customerRewards->count() > 0 ? $customerRewards->count() : 1 }};
        
        // Store benefits data for JavaScript use
        const benefitsData = @json($benefits);

        // Add new benefit row
        $('#add-benefit').click(function() {
            console.log('Adding new benefit row, index:', benefitIndex);
            console.log('Benefits data:', benefitsData);
            
            let benefitsOptions = '<option value="">Select Benefit</option>';
            benefitsData.forEach(function(benefit) {
                benefitsOptions += `<option value="${benefit.id}">${benefit.tag}</option>`;
            });
            
            const newRow = `
                <div class="benefit-row" data-index="${benefitIndex}">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Benefit</label>
                                <select name="benefits[${benefitIndex}][benefit_id]" class="form-control" required>
                                    ${benefitsOptions}
                                </select>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Status</label>
                                <select name="benefits[${benefitIndex}][status]" class="form-control" required>
                                    <option value="active">Active</option>
                                    <option value="claimed">Claimed</option>
                                    <option value="expired">Expired</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Expiry Date</label>
                                <input type="date" name="benefits[${benefitIndex}][expiry_date]" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Claim Date</label>
                                <input type="date" name="benefits[${benefitIndex}][claim_date]" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>&nbsp;</label>
                                <button type="button" class="btn btn-sm btn-danger remove-benefit">
                                    <i class="la la-trash"></i> Remove
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            $('#benefits-container').append(newRow);
            benefitIndex++;
            console.log('New row added, next index will be:', benefitIndex);
        });

        // Remove benefit row
        $(document).on('click', '.remove-benefit', function() {
            $(this).closest('.benefit-row').remove();
        });
    });
</script>
</body>
</html> 
</html> 