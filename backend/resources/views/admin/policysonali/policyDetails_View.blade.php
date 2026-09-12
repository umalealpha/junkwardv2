<!DOCTYPE html>
<html lang="en">
@include('admin.layouts.header')

<link href="{{  asset('assets/vendors/custom/datatables/datatables.bundle.css') }}" rel="stylesheet" type="text/css" />
<link href="{{  asset('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css') }}" rel="stylesheet"
    type="text/css" />
<link href="{{  asset('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}"
    rel="stylesheet" type="text/css" />
<link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
<link type="text/css" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
<link type="text/css" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.css">
<link type="text/css" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/css/toastr.css">
<link type="text/css" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/css/toastr.min.css">
<link rel="stylesheet" href="//cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">

<!-- begin::Body -->

<body
    class="kt-header--fixed kt-header-mobile--fixed kt-subheader--enabled kt-subheader--transparent kt-aside--enabled kt-aside--fixed kt-aside--minimize kt-page--loading">
    <!-- begin:: Header Mobile -->
    <div id="kt_header_mobile" class="kt-header-mobile kt-header-mobile--fixed ">
        <div class="kt-header-mobile__logo">
            <a>
                <img alt="Logo" src="{{asset('images/logo.png')}}" />
            </a>
        </div>
        <div class="kt-header-mobile__toolbar">
            <button class="kt-header-mobile__toolbar-toggler kt-header-mobile__toolbar-toggler--left"
                id="kt_aside_mobile_toggler"><span></span></button>
            <button class="kt-header-mobile__toolbar-toggler" id="kt_header_mobile_toggler"><span></span></button>
            <button class="kt-header-mobile__toolbar-topbar-toggler" id="kt_header_mobile_topbar_toggler"><i
                    class="flaticon-more"></i></button>
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
        <!-- check if is first time login -->
        <div class="kt-grid__item kt-grid__item--fluid kt-grid kt-grid--hor">
            <!-- begin:: Subheader -->
            @if ($autoRenewedPolicy == 1)
                <h5 style="color: red; margin: 0px 0px 0px 25px; font-size: x-large;">This policy will be autorenewed, do not renew manually.</h5>
            @endif

            @if (isset($renewPolicyManually) && isset($policyRenewalCheck)  && $autoRenewedPolicy != 1)
                <h5 style="color: red; margin: 0px 0px 0px 25px; font-size: x-large;">This policy will not be autorenewed, it needs to be renew manually.</h5>
            @endif

            <div class="kt-subheader kt-grid__item" id="kt_subheader">
                <div class="kt-subheader__main">
                    <h3 class="kt-subheader__title">
                        View Policy
                    </h3>
                    <span class="kt-subheader__separator kt-hidden"></span>
                    <div class="kt-subheader__breadcrumbs">
                        <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                        <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin-dashboard')}}"
                            class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span
                            class="kt-subheader__breadcrumbs-separator"></span> <a href="{{  URL::to('admin/policy') }}"
                            class="kt-subheader__breadcrumbs-link"> Policy </a> <span
                            class="kt-subheader__breadcrumbs-separator"></span>
                        <span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">View</span>
                    </div>

                    <div style="right: 0px; position: absolute; margin-right: 40px;">
                        @if($policy->status == 1 || $policy->status == 0 || $policy->status == 3)
                            <!-- @if($policy->status == 1  && $product->id == 3)
                               <a href="{{--{{ route('admin.policy.renew_policy') }} --}}#" id="MoveToRenewFormTop" class="btn btn-success">Move to Renew Table</a>
                            @endif -->
                            {{-- @if($product->id == 3 && $policy->status == 1 && !isset($policyRenewalButton) && isset($activatedDiffDays))
                                @if($activatedDiffDays >= 90) --}}
                                @if(auth::user()->hasPermissionTo('policy-move-to-renew'))
                                    @if ($policy->product_id == 3)
                                        <a href="#" id="MoveToRenewFormTop" class="btn btn-success">Move to Renew Table</a>
                                    @endif
                                @endif
                               {{-- @endif

                            @endif --}}

                            @if(auth::user()->hasPermissionTo('policy-cancel') || (auth()->user()->id == $policy->agent_id) || (auth()->user()->id == null) || (auth()->user()->id == ''))
                                <a href="{{--{{ route('admin.policy.action', [$policy->id, 2]) }}--}}#"
                                    @if(auth::user()->hasPermissionTo('policy-Cancel Policy without OTP')) id="feedbackForm"  @else   id="CancelPolicy"  @endif  class="btn btn-danger">Cancel</a>
                            @endif
                            {{-- @if($product->id == 3 && isset($is_renewal->is_renewed)  && $is_renewal->is_renewed== 0 && isset($activatedDiffDays)) --}}
                            @if($product->id == 3 && isset($activatedDiffDays))
                                {{-- @if($activatedDiffDays <= 90) --}}
                                    <a href="{{ route('admin.policy.renew', $policy->id) }}" class="btn btn-success">Renew</a>
                                {{-- @endif --}}
                            @endif
                        @endif

                        @if($policy->status==2 &&  $user->is_blocked != 1)
                            @if($checkCancelDate)
                                @can('policy_reinstate')
                                    <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#reinstate" style="height: 40px; margin-top: 10px;">
                                        Reinstate
                                    </button>
                                @endcan
                            @endif
                        @endif
                    </div>
                </div>
            </div>
            <!-- end:: Subheader -->
            <!-- begin:: Content -->
            <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">

                <div class="row ol-lg-12">
                    <div class=" col-lg-3 text-left policyno"><br>
                        <h5>Policy No # {{$policy->policyNumber}} @if(isset($policyUpgrade) && $policyUpgrade != null && $policyUpgrade->status == 1)<b style="font-size:15px;color:#FFAC33;">Upgraded</b> @endif<br>

                            @if($transaction !=null)
                            <span class="kt-badge  kt-badge--primary kt-badge--inline kt-badge--pill"
                                style="font-size:15px">Ref: {{$transaction->referenceNumber}}</span>
                            @else
                            <span class="kt-badge  kt-badge--primary kt-badge--inline kt-badge--pill"
                                style="font-size:15px">Ref: Not yet assigned</span>
                            @endif
                        </h5>
                    </div>
                    @if($kyc == NULL)
                    <div class="col-lg-3 text-center">
                        <br>
                        <label>
                            <h5>Compliance: <span class="kt-badge  kt-badge--danger kt-badge--inline kt-badge--pill"
                                    style="font-size:15px"> KYC Non Compliant</span></h5>
                        </label>

                    </div>
                    @else
                    <div class="col-lg-3">
                        <br>
                        <label>
                            {{-- @if($kyc->compliance == 1) --}}
                            @if($kyc != null && $kyc->compliance == 1)
                            <h5>Compliance: <span class="kt-badge  kt-badge--success kt-badge--inline kt-badge--pill"
                                    style="font-size:15px"> KYC Compliant</span></h5>
                            @elseif($kyc != null && $kyc->compliance == 0)
                            <h5>Compliance: <span class="kt-badge  kt-badge--info kt-badge--inline kt-badge--pill"
                                    style="font-size:15px"> KYC Verification Pending</span></h5>
                            @elseif($kyc != null && $kyc->compliance == 3)
                                <h5>Compliance: <span class="kt-badge  kt-badge--danger kt-badge--inline kt-badge--pill" style="font-size:15px"> No ID - No Documents</span></h5>
                            @else
                            <h5>Compliance: <span class="kt-badge  kt-badge--danger kt-badge--inline kt-badge--pill"
                                    style="font-size:15px"> KYC Non Compliant</span></h5>
                            @endif
                        </label>
                    </div>
                    @endif
                    <div class="col-lg-4 text-center">
                        <br>
                        <label>
                            @if ($policy->status == 1 && $kyc != null && $kyc->compliance == 1)
                            <h5>Policy Status: <span class="kt-badge  kt-badge--success kt-badge--inline kt-badge--pill"
                                    style="font-size:15px"> Activated</span>@if($transaction && ($transaction->status ==
                                'SUCCESS' || $transaction->status == 'Success' || $transaction->status == 'SUCCESSFUL'))<span
                                    class="kt-badge  kt-badge--success kt-badge--inline kt-badge--pill"
                                    style="font-size:15px"> Payment Success</span>
                                @elseif($transaction && ($transaction->status == 'PENDING' || $transaction->status ==
                                'A'))<span class="kt-badge  kt-badge--info kt-badge--inline kt-badge--pill"
                                    style="font-size:15px"> Payment Awaiting</span>

                                @elseif($transaction && $transaction->status == 'PROCESSING')<span
                                    class="kt-badge  kt-badge--info kt-badge--inline kt-badge--pill"
                                    style="font-size:15px"> Payment Processing</span>
                                @else<span class="kt-badge  kt-badge--danger kt-badge--inline kt-badge--pill"
                                    style="font-size:15px"> Payment Failed</span>@endif</h5>
                            {{-- @elseif (($policy->status == 1 && $kyc != null && $kyc->compliance != 1) || $kyc == null) --}}
                            @elseif ($policy->status == 1 && $kyc == null)
                            <h5>Policy Status: <span class="kt-badge  kt-badge--focus kt-badge--inline kt-badge--pill"
                                    style="font-size:15px"> Deactivated - KYC Pending</span>@if($transaction && ($transaction->status ==
                                'SUCCESS' || $transaction->status == 'Success' || $transaction->status == 'SUCCESSFUL'))<span
                                    class="kt-badge  kt-badge--success kt-badge--inline kt-badge--pill"
                                    style="font-size:15px"> Payment Success</span>
                                @elseif($transaction && ($transaction->status == 'PENDING' || $transaction->status ==
                                'A'))<span class="kt-badge  kt-badge--info kt-badge--inline kt-badge--pill"
                                    style="font-size:15px"> Payment Awaiting</span>

                                @elseif($transaction && $transaction->status == 'PROCESSING')<span
                                    class="kt-badge  kt-badge--info kt-badge--inline kt-badge--pill"
                                    style="font-size:15px"> Payment Processing</span>
                                @else<span class="kt-badge  kt-badge--danger kt-badge--inline kt-badge--pill"
                                    style="font-size:15px"> Payment Failed</span>@endif</h5>
                            @elseif ($policy->status == 2)
                            <h5>Policy Status: <span class="kt-badge  kt-badge--danger kt-badge--inline kt-badge--pill"
                                    style="font-size:15px"> Cancelled</span>@if($transaction && ($transaction->status ==
                                'SUCCESS' || $transaction->status == 'Success' || $transaction->status == 'SUCCESSFUL'))<span
                                    class="kt-badge  kt-badge--success kt-badge--inline kt-badge--pill"
                                    style="font-size:15px"> Payment Success</span>
                                @elseif($transaction && ($transaction->status == 'PENDING' || $transaction->status ==
                                'A'))<span class="kt-badge  kt-badge--info kt-badge--inline kt-badge--pill"
                                    style="font-size:15px"> Payment Pending</span>

                                @elseif($transaction && $transaction->status == 'PROCESSING')<span
                                    class="kt-badge  kt-badge--info kt-badge--inline kt-badge--pill"
                                    style="font-size:15px"> Payment Processing</span>
                                @else<span class="kt-badge  kt-badge--danger kt-badge--inline kt-badge--pill"
                                    style="font-size:15px"> Payment Failed</span>@endif</h5>
                            <span class="kt-badge  kt-badge--danger kt-badge--inline kt-badge--pill"
                                style="font-size:15px">
                                @if($feedback != null)
                                {{ $feedback->reason.'-'. $feedback->circumstances.' '.$feedback->other_company }}
                                @else
                                No Feedback Found
                                @endif
                            </span>
                            @elseif ($policy->status == 3)
                                <h5>Policy Status: <span
                                    class="kt-badge  kt-badge--danger kt-badge--inline kt-badge--pill"
                                        style="font-size:15px"> Expired</span>
                                    @if ($transaction && ($transaction->status == 'SUCCESS' || $transaction->status == 'Success' || $transaction->status == 'SUCCESSFUL'))<span
                                            class="kt-badge  kt-badge--success kt-badge--inline kt-badge--pill"
                                            style="font-size:15px"> Payment Success</span>
                                    @elseif($transaction && ($transaction->status == 'PENDING' || $transaction->status == 'A'))<span
                                            class="kt-badge  kt-badge--info kt-badge--inline kt-badge--pill"
                                            style="font-size:15px"> Payment Pending</span>

                                    @elseif($transaction && $transaction->status == 'PROCESSING')<span
                                            class="kt-badge  kt-badge--info kt-badge--inline kt-badge--pill"
                                            style="font-size:15px"> Payment Processing</span>
                                    @else<span class="kt-badge  kt-badge--danger kt-badge--inline kt-badge--pill"
                                            style="font-size:15px"> Payment Failed</span>@endif
                                </h5>

                                <span class="kt-badge  kt-badge--danger kt-badge--inline kt-badge--pill"
                                    style="font-size:15px">
                                    @if ($feedback != null)
                                        {{ $feedback->reason . '-' . $feedback->circumstances . ' ' . $feedback->other_company }}
                                    @else
                                        No Feedback Found
                                    @endif
                                </span>
                            @else
                            <h5>Policy Status:
                                <span class="kt-badge  kt-badge--focus kt-badge--inline kt-badge--pill"
                                    style="font-size:15px"> Deactivated</span>
                                @if($transaction && ($transaction->status == 'SUCCESS' || $transaction->status ==
                                'Success' || $transaction->status == 'SUCCESSFUL'))<span class="kt-badge  kt-badge--success kt-badge--inline kt-badge--pill"
                                    style="font-size:15px"> Payment Success </span>
                                @elseif($transaction && ($transaction->status == 'PENDING' || $transaction->status ==
                                'A'))<span class="kt-badge  kt-badge--info kt-badge--inline kt-badge--pill"
                                    style="font-size:15px"> Payment Pending</span>

                                @elseif($transaction && $transaction->status == 'PROCESSING')<span
                                    class="kt-badge  kt-badge--info kt-badge--inline kt-badge--pill"
                                    style="font-size:15px"> Payment Processing</span>

                                @else
                                <span class="kt-badge  kt-badge--danger kt-badge--inline kt-badge--pill"
                                    style="font-size:15px"> Payment Failed</span>@endif</h5>
                            @endif
                            @if(\AlphaDirect\Models\NgeniusTransection::where('policy_number',$policy->policyNumber)->where('recurring_data','!=',null)->where('status',2)->exists())
                            <span class="kt-badge  kt-badge--danger kt-badge--inline kt-badge--pill"
                                            style="font-size:15px">N-Genius Payment Contract Cancelled</span>
                            @endif
                        </label>
                    </div>
                    <div class=" col-lg-2 "><br>
                        @if($policy->agent_id != NULL)
                        @if($agent_name != null)
                        @if($agent_name->firstName && $agent_name->lastName)
                        <h5>Agent: {!! ucwords(strtolower($agent_name->firstName)) !!} {!! ucwords(strtolower($agent_name->lastName)) !!}</h5>
                        @else
                        <h5>Agent: Name not found</h5>
                        @endif
                        @else
                        <h5>Agent: Name not fetched</h5>
                        @endif

                        @else
                        <h5>Agent: Not assigned</h5>
                        @endif
                    </div>

                </div>

                <div class="kt-portlet kt-portlet--tabs">

                    <div class="kt-portlet__head">
                        <div class="kt-portlet__head-toolbar">
                            <ul class="nav nav-tabs nav-tabs-line nav-tabs-line-brand nav-tabs-line-2x nav-tabs-line-right nav-tabs-bold"
                                role="tablist">
                                <li class="nav-item">
                                    <a class="nav-link active policyDetailsTab" data-toggle="tab"
                                        href="#kt_portlet_base_demo_3_1_policy_content" role="tab"> <i
                                            class="flaticon2-heart-rate-monitor" aria-hidden="true"></i>Policy Details
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" data-toggle="tab" href="#kt_portlet_base_demo_3_5_bank_content"
                                        role="tab"> <i class="flaticon-piggy-bank" aria-hidden="true"></i>Banking
                                        Details </a>
                                </li>
                                <li class="nav-item" id="ledger-tab">
                                    <a class="nav-link" data-toggle="tab"
                                        href="#kt_portlet_base_demo_3_2_transactions_content" role="tab"> <i
                                            class="flaticon2-pie-chart-2" aria-hidden="true"></i>Ledger </a>
                                </li>

                                <li class="nav-item" id="claim-tab">
                                    <a class="nav-link" data-toggle="tab"
                                        href="#kt_portlet_base_demo_3_3_claims_content" role="tab"> <i
                                            class="flaticon2-chronometer" aria-hidden="true"></i>Claims </a>
                                </li>
                                <li class="nav-item" id="mati-tab">
                                    <a class="nav-link" data-toggle="tab"
                                        href="#kt_portlet_base_demo_3_5_mati_verification_content" role="tab"> <i
                                            class="flaticon2-heart-rate-monitor" aria-hidden="true"></i>Mati
                                        Verification </a>
                                </li>
                                <li class="nav-item" id="loss_history_tab">
                                    <a class="nav-link" data-toggle="tab"
                                        href="#kt_portlet_base_demo_3_8_loss_history" role="tab"> <i
                                            class="flaticon2-chronometer" aria-hidden="true"></i>Loss History</a>
                                </li>
                                @can('action-log-list')
                                <li class="nav-item" id="activity-tab">
                                    <a class="nav-link" data-toggle="tab"
                                        href="#kt_portlet_base_demo_3_4_actionlog_content" role="tab"> <i
                                            class="flaticon2-chronometer" aria-hidden="true"></i>Action Log </a>
                                </li>
                                @endcan
                                @can('generate-payment-url-list')
                                <li class="{{ empty($tabName) || $tabName == 'kt_generatePaymentUrl' ? 'active' : '' }} nav-item" id="generate-payment-url">
                                    <a class="nav-link generatePaymentUrlClass" data-toggle="tab"
                                        href="#kt_portlet_base_demo_3_4_generateURL_content" role="tab"> <i
                                            class="flaticon-upload-1" aria-hidden="true"></i>Generate Payment URL </a>
                                </li>
                                @endcan

                                <li class="nav-item" id="transaction-tab">
                                    <a class="nav-link" data-toggle="tab"
                                        href="#kt_portlet_base_demo_3_6_transaction_log" role="tab">
                                        <i class="flaticon2-chronometer" aria-hidden="true"></i>Transaction Log </a>
                                </li>

                                @if ($policy->product_id == 3)
                                    @can('change_preminum_frequency')
                                        <li class="nav-item" id="change-premFrequency-tab">
                                            <a class="nav-link" data-toggle="tab"
                                                href="#kt_portlet_base_demo_3_4_change_preminum_frequency" role="tab"> <i
                                                    class="flaticon-signs-1" aria-hidden="true"></i>Change Premium Frequency</a>
                                        </li>
                                    @endcan
                                @endif

                                {{-- @if ($policy->product_id == 3) --}}
                                    @if($banking && $banking->billing != null && $banking->billing == 'RealPay')
                                        <li class="nav-item" id="realpay-contract-list-tab">
                                            <a class="nav-link" data-toggle="tab"
                                                href="#kt_portlet_base_demo_3_4_client_contract_list" role="tab"> <i
                                                    class="flaticon-signs-1" aria-hidden="true"></i>Realpay Contract List</a>
                                        </li>
                                    @endif
                                {{-- @endif --}}

                                @can('policy-documents-list')
                                <li class="nav-item" id="send-policy-document">
                                    <a class="nav-link" data-toggle="tab" href="#kt_portlet_base_demo_3_6_documents"
                                        role="tab"> <i class="flaticon-signs-1" aria-hidden="true"></i>Policy
                                        Documents</a>
                                </li>
                                @endcan

                                @if($banking && $banking->billing != null && $banking->billing == 'RealPay')
                                <li class="nav-item" id="realPay-tab">
                                    <a class="nav-link" data-toggle="tab"
                                        href="#kt_portlet_base_demo_3_4_realpay_translog" role="tab"> <i
                                            class="flaticon-signs-1" aria-hidden="true"></i>RealPay Transactions</a>
                                </li>
                                @endif

                                @if($banking && $banking->billing != null && $banking->billing == 'RealPay')
                                <li class="nav-item">
                                    <a class="nav-link" data-toggle="tab"
                                        href="#kt_portlet_base_demo_3_9_add_realpay_client_contract" role="tab"> <i
                                            class="flaticon-signs-1" aria-hidden="true"></i>Add Realpay Client Contract</a>
                                </li>
                                @endif

                                <li class="nav-item" id="policyTerm-tab">
                                    <a class="nav-link" data-toggle="tab"
                                        href="#kt_portlet_base_demo_3_4_term_table_data" role="tab"> <i
                                            class="flaticon-signs-1" aria-hidden="true"></i>Terms</a>
                                </li>

                                @if (isset($policy->is_reinstate) && $policy->is_reinstate == 1)
                                    <li class="nav-item" id="policyReinstate-tab">
                                        <a class="nav-link" data-toggle="tab"
                                            href="#kt_portlet_base_demo_3_4_policy_reinstate_table_data" role="tab"> <i
                                                class="flaticon-signs-1" aria-hidden="true"></i>Policy Reinstate</a>
                                    </li>
                                @endif

                                @if ($product->id == 3)
                                <li class="nav-item">
                                    <a class="nav-link" data-toggle="tab"
                                        href="#kt_portlet_base_demo_3_7_rerate_premium" role="tab"> <i
                                            class="flaticon-signs-1" aria-hidden="true"></i>Rerate Premium</a>
                                </li>
                               @endif

                                @can('calculate-per-day-premium-list')
                                @if($product->id == 3 && $policy->quoteNumber != null)
                                <li class="nav-item">
                                    <a class="nav-link" data-toggle="tab"
                                        href="#kt_portlet_base_demo_3_6_per_day_premium" role="tab"> <i
                                            class="flaticon-signs-1" aria-hidden="true"></i>Calculate Per Day
                                        Premium</a>
                                </li>
                                @endif
                                @endcan

                                @can('offline-payments-list')

                                @if ($policy->status != 2)
                                <li
                                    class="{{ empty($tabName) || $tabName == 'kt_portlet_base_demo_3_7_payments' ? 'active' : '' }} nav-item">
                                    <a class="nav-link" data-toggle="tab" href="#kt_portlet_base_demo_3_7_payments"
                                        role="tab"> <i class="flaticon-signs-1" aria-hidden="true"></i>Add offline
                                        payments</a>
                                </li>
                                @endif
                                @endcan

                                @can('assign-agent-policy-list')
                                <li class="nav-item">
                                    <a class="nav-link" data-toggle="tab" href="#kt_portlet_base_demo_3_8_assign_agent"
                                        role="tab"> <i class="flaticon-signs-1" aria-hidden="true"></i>Assign Agent</a>
                                </li>
                                @endcan

                                <li class="nav-item" id="attachment-tab">
                                    <a class="nav-link" data-toggle="tab"
                                        href="#kt_portlet_base_demo_3_8_attachment_agent" role="tab"> <i
                                            class="flaticon-signs-1" aria-hidden="true"></i>Attachment</a>
                                </li>

                                {{-- @can('sms-email-list') --}}
                                <li class="nav-item" id="sms-email-log-tab">
                                    <a class="nav-link" data-toggle="tab" href="#kt_portlet_base_demo_3_8_sms_email_log" role="tab"> <i class="flaticon-signs-1" aria-hidden="true"></i>SMS/Email Logs</a>
                                </li>
                                {{-- @endcan --}}
                                @can('policy_discount_surcharge')
                                <li class="nav-item" id="discount-surcharge-tab">
                                    <a class="nav-link" data-toggle="tab" href="#kt_portlet_base_demo_3_8_discount_surcharge" role="tab"> <i class="flaticon-signs-1" aria-hidden="true"></i>Discount/Surcharge</a>
                                </li>
                                @endcan
                                @can('policy_earned_premium')
                                <li class="nav-item" id="earned_premiums-tab">
                                    <a class="nav-link" data-toggle="tab"
                                        href="#kt_portlet_base_demo_3_2_earned_premiums" role="tab"> <i
                                            class="flaticon2-pie-chart-2" aria-hidden="true"></i>Earned Premiums </a>
                                </li>
                                @endcan
                                @if(isset($scheduleTransactionCount) && $scheduleTransactionCount > 0)
                                    <li class="nav-item" id="scheduled-transaction-tab">
                                        <a class="nav-link" data-toggle="tab"
                                            href="#kt_portlet_scheduled_trasactions_logs" role="tab">
                                            <i class="flaticon-signs-1" aria-hidden="true"></i>Scheduled Transactions</a>
                                    </li>

                                    <li class="nav-item">
                                        <a class="nav-link" data-toggle="tab"
                                            href="#kt_portlet_cancel_duplicate_scheduled_trasactions" role="tab">
                                            <i class="flaticon-signs-1" aria-hidden="true"></i>Cancel Scheduled Transactions</a>
                                    </li>
                                @endif

                                {{-- @if ($policy->status == 2)
                                <li class="nav-item">
                                    <a class="nav-link" data-toggle="tab"href="#kt_portlet_credit_note" role="tab">
                                        <i class="flaticon-signs-1" aria-hidden="true"></i>Credit Note</a>
                                </li>
                                @endif --}}

                                <!-- @can('policy-documents-list') -->
                                <li class="nav-item">
                                    <a class="nav-link" data-toggle="tab" href="#kt_portlet_base_demo_3_6_linked_policy"
                                        role="tab"> <i class="flaticon-signs-1" aria-hidden="true"></i>Linked Policy </a>
                                </li>
                                <!-- @endcan -->

                                @if(\AlphaDirect\PaymentTransaction::where('policyNumber',$policy->policyNumber)->where('paymentMethod','DPO')->exists())
                                <li class="nav-item">
                                    <a class="nav-link" data-toggle="tab" href="#kt_portlet_base_demo_3_6_DPO_Payment"
                                        role="tab"> <i class="flaticon-signs-1" aria-hidden="true"></i>DPO Payment Transactions List</a>
                                </li>
                                @endif
                                @can('change_policy_frequency')
                                    @if ($banking && $banking->billing != null)
                                        @if ($banking->billing == 'DPO' || $banking->billing == 'RealPay' || $banking->billing == 'Realpay')
                                            <li class="nav-item" id="change_policy_frequency-tab">
                                                <a class="nav-link" data-toggle="tab"
                                                    href="#kt_portlet_base_demo_3_7_change_policy_frequency" role="tab"> <i
                                                        class="flaticon2-pie-chart-2" aria-hidden="true"></i>Change Frequency</a>
                                            </li>
                                        @endif
                                    @endif
                                @endcan

                            </ul>

                        </div>

                    </div>

                     <!-- Modal for reinsant  start -->
                 <div class="modal fade" id="reinstate" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
                    <div class="modal-dialog" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                        <h5 class="modal-title" id="exampleModalLabel">Reinstate </h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                        </div>
                        <div class="modal-body">
                                <div class="container ml-2">
                                    <div class="row ml-4">
                                        @if ($policy->product_id == 3)
                                                <div class="col-sm-6">
                                                    <a class="btn btn-primary"
                                                        href="{{ route('admin.policy.reinstate_areas', $policy->id) }}"
                                                        @if ($reinstate_days > $days_to_reinstate['value']) disabled  @endif>
                                                        Reinstate With Arears
                                                    </a>
                                                </div>
                                                <div class="col-sm-6">
                                                    <a class="btn btn-primary"
                                                        href="{{ route('admin.policy.reinstate', [$policy->id, $functionality]) }}">
                                                        Reinstate Fresh
                                                    </a>
                                                </div>
                                                <div class="text-center mt-2">
                                                    @if ($reinstate_days < 16)
                                                        <a class="text-center" style="margin-left:80px;"
                                                            href="{{ route('admin.policy.reinstate_accidentally', [$policy->id, $functionality]) }}">Policy
                                                            was
                                                            cancelled accidently</a>
                                                    @endif
                                                </div>
                                            @else
                                                <div class="col-sm-6">
                                                    <a class="btn btn-primary"
                                                        href="{{ route('admin.policy.reinstate_areas', $policy->id) }}"
                                                        @if ($reinstate_days > $days_to_reinstate['value']) disabled  @endif>
                                                        Reinstate With Arears
                                                    </a>
                                                </div>
                                                <div class="text-center mt-2">
                                                    @if ($reinstate_days < 16)
                                                        <a class="text-center" style="margin-left:80px;"
                                                            href="{{ route('admin.policy.reinstate_accidentally', [$policy->id, $functionality]) }}">Policy
                                                            was
                                                            cancelled accidently</a>
                                                    @endif
                                                </div>
                                            @endif
                                    </div>
                                </div>
                        </div>

                    </div>
                    </div>
                 </div>

                 <!--Reinstant Modal end -->

                    <div class="kt-portlet__body" style="padding-top:0px">
                        <div class="tab-content">
                            <div class="tab-pane active" id="kt_portlet_base_demo_3_1_policy_content" role="tabpanel">
                                <div class="kt-portlet" id="labelDiv">
                                    <div class="kt-portlet__head">
                                        <div class="kt-portlet__head-label">
                                            <h3 class="kt-portlet__head-title">
                                                Customer Details
                                            </h3>
                                        </div>

                                        @if (\Illuminate\Support\Facades\Auth::user()->hasPermissionTo('kyc-update'))
                                            @isset($kyc)
                                                <div class="kt-portlet__head-label">
                                                    <h5 class="kt-portlet__head-title">
                                                        <a href="{{ route('admin.viewCustomerKycData', $kyc->id) }}" target="_blank" >Edit Customer KYC</a>
                                                    </h5>
                                                </div>
                                            @endisset
                                        @endif

                                        <div class="kt-portlet__head-label">
                                            <h5 class="kt-portlet__head-title">
                                                Mati status :
                                                @if(isset($user) && $user->mati_identity == 0)
                                                <h5 class="kt-portlet__head-title" style="color: #fd397a"> Disable </h5>
                                                @else
                                                <h5 class="kt-portlet__head-title" style="color: #5867dd"> Enable </h5>
                                                @endif
                                            </h5>
                                        </div>

                                        @if($policy->product_id == 3 && $quote_id != null)
                                            @if(\Illuminate\Support\Facades\Auth::user()->hasPermissionTo('quote-edit'))
                                            <div class="kt-portlet__head-label"><h5 class="kt-portlet__head-title"><a href="{{ route('quote.edit',$quote_id->id) }}" target="_blank">Quote No # {{$policy->quoteNumber}}</a></h5></div>
                                            @else
                                            <div class="kt-portlet__head-label"><h3 class="kt-portlet__head-title"> Quote No # {{$policy->quoteNumber}} </h3></div>
                                            @endif
                                        @else
                                            <div class="kt-portlet__head-label"><h5 class="kt-portlet__head-title">Quote No # -</h5></div>
                                        @endif
                                    </div>
                                    <div class="kt-portlet_body">
                                        <div class="kt-section">
                                            <div class="kt-section__content" style="padding-left:20px; padding-right: 20px;">
                                                <table class="table table-striped m-table">
                                                    <tbody>
                                                        <tr>
                                                            <th>Name</th>
                                                            @if($user)
                                                            @if($user->firstName || $user->middleName ||
                                                            $user->lastName)
                                                            <td><a href="{{ route('admin.customer.edit', $policy->customer_id) }}" target="_blank" >
                                                                {!! ucwords($user->firstName).'
                                                                '.ucwords($user->middleName).'
                                                                '.ucwords($user->lastName) !!}</a></td>
                                                            @else
                                                            <td>Name not found</td>
                                                            @endif
                                                            @else
                                                            <td>User not found</td>
                                                            @endif
                                                            <th>Email</th>
                                                            <td>{!! $user->email !!}</td>
                                                        </tr>
                                                        <tr>
                                                            <th>Cellphone</th>
                                                            <td>{!! $user->cellphone !!}</td>
                                                            <th>Address</th>
                                                            <td>{!! ucwords($user->profile->address) !!}</td>
                                                        </tr>
                                                        <tr>
                                                            <th>Omang ID</th>
                                                            <td>
                                                                @if($user->profile->omang != null)
                                                                {!! $user->profile->omang !!}
                                                                @else
                                                                N/A
                                                                @endif
                                                            </td>
                                                            <th>Date of Birth</th>
                                                            <td>
                                                                @if($user->profile->dob != null)
                                                                {!! $user->profile->dob !!}
                                                                @else
                                                                N/A
                                                                @endif
                                                            </td>
                                                        </tr>
                                                        <tr>
                                                            <th>Passport</th>
                                                            <td>
                                                                @if($user->profile->passport != null)
                                                                {!! $user->profile->passport !!}
                                                                @else
                                                                N/A
                                                                @endif
                                                            </td>
                                                            <th>Passport Issuing Country</th>
                                                            <td>
                                                                @if($passpostIssueCountry != null && $passpostIssueCountry->name!=null)
                                                                {!! $passpostIssueCountry->name !!}
                                                                @else
                                                                N/A
                                                                @endif
                                                            </td>

                                                        </tr>
                                                        <tr>
                                                            <th>Payment Vendor</th>
                                                            @if ($banking && $banking->billing != null)
                                                                @if ($banking->billing == 'orangeMoney')
                                                                    <td>Orange USSD</td>
                                                                @else
                                                                    <td>{!! $banking->billing !!} </td>
                                                                @endif
                                                            @else
                                                                <td>N/A</td>
                                                            @endif

                                                            <th>Billing Start Date</th>
                                                            <td>
                                                                @if($policy->billingStartDate != null)
                                                                    {{  $policy->billingStartDate }}
                                                                @else
                                                                   N/A
                                                                @endif
                                                            </td>
                                                        </tr>
                                                        <tr>
                                                            <th>Billing Frequency</th>
                                                             @if($policy->premium_freq == null)
                                                            <td> Monthly </td><!-- monthly on null -->
                                                            @elseif($policy->premium_freq == 1)
                                                            <td> Monthly </td>
                                                            @elseif($policy->premium_freq == 2)
                                                            <td> Three Installments </td>
                                                            @elseif($policy->premium_freq == 3)
                                                            <td> Annual </td>
                                                            @endif

                                                            <th>Maritial Status</th>
                                                            @if($user->profile->maritalstatus == 1)
                                                            <td> Single </td>
                                                            @elseif($user->profile->maritalstatus == 2)
                                                            <td> Married </td>
                                                            @elseif($user->profile->maritalstatus == 3)
                                                            <td> Divorced </td>
                                                            @elseif($user->profile->maritalstatus == 4)
                                                            <td> Widowed </td>
                                                            @elseif($user->profile->maritalstatus == 5)
                                                            <td> Living Together(NOT Married) </td>
                                                            @elseif($user->profile->maritalstatus == 6)
                                                            <td> Living Together(NOT Married) </td>
                                                            @else
                                                            <td>N/A</td>
                                                            @endif


                                                        </tr>
                                                        <tr>
                                                            <th>Driving License Number</th>
                                                            <td>
                                                                @if($user->profile->driving_license_number != null)
                                                                {!! $user->profile->driving_license_number !!}
                                                                @else
                                                                N/A
                                                                @endif
                                                            </td>
                                                            <th>License Valid Till</th>
                                                                @if ($user->profile && $user->profile->license_valid_till != null)
                                                                {{-- <td>{!! $user->profile->license_valid_till !!}</td> --}}
                                                                <td>{!! Carbon::parse(str_replace("/", "-", $user->profile->license_valid_till))->format('d-m-Y') !!}</td>
                                                                @else
                                                                    <td>N/A</td>
                                                                @endif
                                                        </tr>

                                                        <tr>
                                                            <th>City/Town</th>
                                                            @if($user->profile && $user->profile->city != null)
                                                                @if(is_numeric($user->profile->city))
                                                                    <td>{!! ucfirst(\AlphaDirect\Helper::getCityName($user->profile->city)) !!}</td>
                                                                @else
                                                                    <td>{!! ucfirst($user->profile->city) !!}</td>
                                                                @endif
                                                            @else
                                                            <td>N/A</td>
                                                            @endif

                                                            <th>Province</th>
                                                            @if($user->profile && $user->profile->state_name != null)
                                                            <td>{!! ucfirst($user->profile->state_name->name) !!}</td>
                                                            @else
                                                            <td>N/A</td>
                                                            @endif
                                                        </tr>

                                                        <tr>
                                                            <th>Gender</th>
                                                            @if($user->profile->gender == 1)
                                                            <td> Male </td>
                                                            @else
                                                            <td> Female </td>
                                                            @endif

                                                            <th>Lead Source</th>
                                                            @if($policy->leadSource != null)
                                                             <td>{{ $policy->leadSource }}</td>
                                                            @else
                                                               <td>N/A</td>
                                                            @endif
                                                        </tr>

                                                        <tr>
                                                          <th>Policy Activated Date</th>
                                                            @php
                                                            $paytrxn = \AlphaDirect\PaymentTransaction::where('policyNumber',$policy->policyNumber)->whereIn('status',['Success','SUCCESS','success','1'])->orderBy('id','asc')->first();
                                                            @endphp
                                                              {{-- @if($policy->status==1) --}}
                                                                    @if($policy->policyActivatedDate != null)
                                                                        <td>{{  \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $policy->policyActivatedDate)->format('d-m-Y')   }}</td>
                                                                    @elseif($paytrxn)
                                                                     <td>{{  \Carbon\Carbon::parse($paytrxn->created_at)->format('d-m-Y') }}</td>
                                                                    @else
                                                                            <td>N/A</td>
                                                                    @endif
                                                              {{-- @else
                                                                    <td>--</td>
                                                              @endif --}}

                                                           @if ($policy->product_id != 3)
                                                              <th>Billing Type</th>
                                                                @if($policy->BillingStart)
                                                                        @if($policy->BillingStart == 'Immediate')
                                                                            <td>{{$policy->BillingStart}}</td>
                                                                        @else
                                                                            <td>{{$policy->BillingStart}}</td>
                                                                        @endif
                                                                @else
                                                                    <td> N/A </td>
                                                                @endif
                                                           @endif
                                                        </tr>
                                                        <tr>
                                                            <th>Policy Cancelled Date</th>
                                                            @if($policyactivatecancelleddates && $policyactivatecancelleddates->cancelled_date != null)
                                                                <td>{{  \Carbon\Carbon::createFromFormat('Y-m-d', $policyactivatecancelleddates->cancelled_date)->format('d-m-Y')   }}</td>
                                                            @elseif($policy->status == 2)
                                                            <td>{{  \Carbon\Carbon::parse($policy->updated_at)->format('d-m-Y') }}</td>
                                                            @else
                                                                <td>N/A</td>
                                                            @endif
                                                            <th>Agency</th>
                                                           
                                                           @if(isset($policy->user) && $policy->user->agency_id != null)
                                                               @if($policy->user->agency_id == 26)
                                                               <td> {{ "Telemarketing" }} </td>
                                                               @else

                                                                   @php
                                                                   $agency = \AlphaDirect\Agency::where('id',$policy->user->agency_id)->first(['name']);
                                                                   @endphp

                                                                   @if($agency)
                                                                   <td>{{ Str::ucfirst($agency->name) }}</td>
                                                                   @else
                                                                   <td>-</td>
                                                                   @endif
                                                               
                                                               @endif

                                                               
                                                           @else
                                                               <td>-</td>
                                                           @endif
                                                        </tr>
                                                        <tr>
                                                            <th>Policy Cancelled By</th>
                                                            @if($feedback && $feedback->cancelled_by != null)
                                                            <td>{{ $feedback->cancelled_by }}</td>
                                                            @elseif($policy->status == 2)
                                                               <td>Customer</td>
                                                            @else
                                                                <td>N/A</td>
                                                            @endif
                                                            <th>Policy Created by</th>
                                                            
                                                            @if(isset($policy->user) && $policy->user->firstName != null)
                                                                <td>{{ Str::ucfirst($policy->user->firstName) }} {{ Str::ucfirst($policy->user->lastName) }}</td>
                                                            @else
                                                                <td>Customer</td>
                                                            @endif
                                                        </tr>
                                                        <tr>
                                                            @if ($policy->product_id != 3)
                                                                <th>Virtual Box</th>
                                                                    @if($policy->isVirtualBox == 0)
                                                                        <td>No</td>
                                                                    @else
                                                                        <td>Yes</td>
                                                                    @endif
                                                            @endif
                                                            
                                                        </tr>
                                                        <tr>
                                                            <th>Source of income</th>
                                                            @if (isset($user->profile->sourceOfIncome))
                                                                @if(is_object($user->profile->sourceOfIncome) || is_array($user->profile->sourceOfIncome))
                                                                    @foreach ($user->profile->sourceOfIncome as $key => $source_Income)
                                                                        @if (isset($source_Income))
                                                                            @if ($key != null)
                                                                                <td>{{ Str::ucfirst(Str::replace('_', ' ', $key)) }}
                                                                                </td>
                                                                    </tr>
                                                                                @if (isset($source_Income))
                                                                                    @foreach ($source_Income as $sourceKey => $source)
                                                                                        <tr>
                                                                                            @if ($source != null)
                                                                                                <th>
                                                                                                    {{ Str::ucfirst(Str::replace('_', ' ', $sourceKey)) }}
                                                                                                </th>
                                                                                            @endif
                                                                                            @if ($source != null)
                                                                                                <td>
                                                                                                    {{ Str::ucfirst($source) }}
                                                                                                </td>
                                                                                            @endif
                                                                                        </tr>
                                                                                    @endforeach
                                                                                @endif
                                                                            @endif
                                                                        @endif
                                                                    @endforeach
                                                                @else
                                                                <td> {{ ucfirst($user->profile->sourceOfIncome) }}<td>
                                                                @endif
                                                            @else
                                                            <td>{{ '-' }}</td>
                                                            @endif
                                                        </tr>
 @if ($banking && $banking->billing != null &&  $banking->billing == 'DPO')
                                                        @if(isset($pay_email) && $pay_email != null)
                                                        <tr>
                                                            <th>Payment Email</th>
                                                            @if(isset($pay_email) && $pay_email != null)
                                                            <td> {{ $pay_email->pay_email }} @if($pay_email->is_edited == 1) <small style="color:#fe7f0c;"> Edited</small> @endif
                                                            </td>
                                                            @else
                                                            <td>-</td>
                                                            @endif

                                                            <th></th>
                                                            <td></td>

                                                        </tr>
                                                        @endif
                                                        @endif

                                                       
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- @if($policy->agent_id == null)
                                    <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>
                                    <div class="kt-portlet__head">
                                        <div class="kt-portlet__head-label">
                                            <h3 class="kt-portlet__head-title">
                                                Assign Agent:
                                                <select id="assign_new_agent"
                                                    class="form-control required kt_selectpicker"
                                                    title="Please select agent" name="assign_new_agent">
                                                    @foreach($agents as $agent)
                                                    <option value="{{ $agent->id }}">{!! ucwords($agent->firstName) !!}
                                                        {!! ucwords($agent->lastName) !!} - {{ $agent->id }}  </option>
                                                    @endforeach
                                                </select>
                                            </h3>
                                        </div>
                                    </div>
                                    @endif --}}
                                    @if($policy->is_bundled == 1)
                    @php $p_id66 = 0; @endphp
                    @foreach($PolicyBundled as $PolicyBundleds)

                    @if($PolicyBundleds->product_id == 4)
                    @php $p_id66 = 4; @endphp
                    @endif
                    @endforeach
                    @endif

                    @if ($user->profile->sourceOfIncome != "unemployed")

                    @if($policy->product_id == 4 || ($policy->is_bundled == 1 && $p_id66 == 4))

                    <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>
                    <div class="kt-portlet__head">
                        <div class="kt-portlet__head-label">
                            <h3 class="kt-portlet__head-title">
                            Employer Details
                            </h3>
                        </div>
                    </div>
                    <div class="kt-portlet_body">
                        <div class="kt-section">
                            <div class="kt-section__content" style="padding-left:20px; padding-right: 20px;">
                                <table class="table table-striped m-table">
                                    <tbody>
                                        <tr style="border-top: solid 2px #666;">
                                            <th>Employer Name</th>
                                            @if($user->profile && $user->profile->e_name != null)
                                            <td>{!! ucwords($user->profile->e_name) !!}</td>
                                            @else
                                            <td>N/A</td>
                                            @endif

                                            <th>Employee Number</th>
                                            @if($user->profile && $user->profile->emp_no != null)
                                            <td>{!! $user->profile->emp_no !!}</td>
                                            @else
                                            <td>N/A</td>
                                            @endif
                                        </tr>
                                        <tr>
                                            <th>Cellphone</th>
                                            @if($user->profile && $user->profile->emp_phone  != null)
                                            <td>{!! $user->profile->emp_phone !!}</td>
                                            @else
                                            <td>N/A</td>
                                            @endif

                                            <th>Salary Pay Date</th>
                                            <td>
                                                @if($user->profile && $user->profile->salary_pay_date != null)
                                                    {{  $user->profile->salary_pay_date }}
                                                @else
                                                    N/A
                                                @endif
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                @endif

                    @endif



                                    @if($policy->kyc_customer)
                                    <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>
                                    <div class="kt-portlet__head">
                                        <div class="kt-portlet__head-label">
                                            <h3 class="kt-portlet__head-title">
                                                Customer KYC
                                            </h3>
                                        </div>
                                    </div>
                                    <div class="kt-portlet_body">
                                        <div class="kt-section">
                                            <div class="kt-section__content"
                                                style="padding-left:20px; padding-right: 20px;">
                                                <table class="table table-striped m-table">
                                                    <tbody>

                                                        {{--<div class="form-group row">
                                                            <div class="col-md-2">
                                                                <h3 class="col-form-label" style="float: left;">Driving License</h3>
                                                                @if($kyc->driving_license == NULL)
                                                                    <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="100%" height="auto" >
                                                                @else
                                                                    <img src="{{Storage::disk('s3')->url($kyc->driving_license)}}"
                                                        width="100%" height="auto" >
                                                        @endif
                                                        <div class="kt-avatar" style="float: left; clear: left;"></div>
                                            </div>
                                            <div class="col-md-2">
                                                <h3 class="col-form-label" style="float: left;">Omang ID</h3>
                                                @if($kyc->omang == NULL)
                                                <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png"
                                                    width="100%" height="auto">
                                                @else
                                                <img src="{{Storage::disk('s3')->url($kyc->omang)}}" width="100%"
                                                    height="auto">
                                                @endif
                                                <div class="kt-avatar" style="float: left; clear: left;"></div>
                                            </div>

                                            <div class="col-md-2">
                                                <h3 class="col-form-label" style="float: left;">Proof of Residence</h3>
                                                @if($kyc->proof_residence == NULL)
                                                <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png"
                                                    width="100%" height="auto">
                                                @else
                                                <img src="{{Storage::disk('s3')->url($kyc->proof_residence)}}"
                                                    width="100%" height="auto">
                                                @endif
                                                <div class="kt-avatar" style="float: left; clear: left;">
                                                </div>
                                            </div>
                                            <div class="col-md-2">
                                                <h3 class="col-form-label" style="float: left;">Proof of Income</h3>
                                                @if($kyc->proof_income == NULL)
                                                <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png"
                                                    width="100%" height="auto">
                                                @else
                                                <img src="{{Storage::disk('s3')->url($kyc->proof_income)}}" width="100%"
                                                    height="auto">
                                                @endif
                                                <div class="kt-avatar" style="float: left; clear: left;">
                                                </div>
                                            </div>

                                            <div class="col-md-2">
                                                <h3 class="col-form-label" style="float: left;">Passport</h3>
                                                @if($kyc->passport == NULL)
                                                <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png"
                                                    width="100%" height="auto">
                                                @else
                                                <img src="{{Storage::disk('s3')->url($kyc->passport)}}" width="100%"
                                                    height="auto">
                                                @endif
                                                <div class="kt-avatar" style="float: left; clear: left;">
                                                </div>
                                            </div>
                                        </div>--}}
                                        <form action="{{route('admin.policy.uploadKycImages',$policy->id)}}"
                                            method="post" enctype="multipart/form-data" class="kt-form">
                                            @csrf
                                            <div class="form-group row">
                                                <div class="col-md-2">
                                                    <h3 class="col-form-label" style="float: left;">Driving
                                                        License</h3>
                                                    <div class="kt-avatar" id="drivinglicense"
                                                        style="float: left; clear: left;">
                                                        {{-- @if ($kyc->driving_license == null) --}}
                                                        @if (!isset($kyc->driving_license))
                                                            <div class="kt-avatar__holder"
                                                                style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)">
                                                            </div>
                                                        @else
                                                            <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($kyc->driving_license) !!}"
                                                                target="_blank" download>
                                                                <div class="kt-avatar__holder"
                                                                    style="background-image:url('{!! \AlphaDirect\Helper::getCloudFrontURL($kyc->driving_license) !!}')">
                                                                </div>
                                                            </a>
                                                        @endif
                                                        @if ($kyc == null || ($kyc != null && $kyc->compliance != 1))
                                                            <label class="kt-avatar__upload"
                                                                data-toggle="kt-tooltip"
                                                                title="driving_license">
                                                                <i class="fa fa-pen"></i>
                                                                <input type='file' class="drivinglicense"
                                                                    name="driving_license"
                                                                    <?php echo config('app.accept_attr'); ?>
                                                                    <?php echo config('app.accept_msg'); ?> />
                                                            </label>
                                                        @endif
                                                        <span class="kt-avatar__cancel"
                                                            data-toggle="kt-tooltip" title="Cancel Image">
                                                            <i class="fa fa-times"></i>
                                                        </span>
                                                    </div>
                                                </div>


                                                <div class="col-md-2">
                                                    <h3 class="col-form-label" style="float: left;">Omang
                                                        ID Front</h3>
                                                    <div class="kt-avatar" id="omangpic"
                                                        style="float: left; clear: left;">
                                                        @if (!isset($kyc->omang))
                                                            <div class="kt-avatar__holder"
                                                                style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)">
                                                            </div>
                                                        @else
                                                            <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($kyc->omang) !!}"
                                                                target="_blank" download>
                                                                <div class="kt-avatar__holder"
                                                                    style="background-image:url('{!! \AlphaDirect\Helper::getCloudFrontURL($kyc->omang) !!}')">
                                                                </div>
                                                            </a>
                                                        @endif
                                                        @if ($kyc == null || ($kyc != null && $kyc->compliance != 1))
                                                            <label class="kt-avatar__upload"
                                                                data-toggle="kt-tooltip" title="omang_pic">
                                                                <i class="fa fa-pen"></i>
                                                                <input type='file' class="omangpic"
                                                                    name="omang_pic" <?php echo config('app.accept_attr'); ?>
                                                                    <?php echo config('app.accept_msg'); ?> />
                                                            </label>
                                                        @endif
                                                        <span class="kt-avatar__cancel"
                                                            data-toggle="kt-tooltip" title="Cancel Image">
                                                            <i class="fa fa-times"></i>
                                                        </span>
                                                    </div>
                                                </div>

                                                <div class="col-md-2">
                                                    <h3 class="col-form-label" style="float: left;">Omang
                                                        ID Back</h3>
                                                    <div class="kt-avatar" id="omangpic_back"
                                                        style="float: left; clear: left;">
                                                        @if (!isset($kyc->omangBack))
                                                            <div class="kt-avatar__holder"
                                                                style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)">
                                                            </div>
                                                        @else
                                                            <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($kyc->omangBack) !!}"
                                                                target="_blank" download>
                                                                <div class="kt-avatar__holder"
                                                                    style="background-image:url('{!! \AlphaDirect\Helper::getCloudFrontURL($kyc->omangBack) !!}')">
                                                                </div>
                                                            </a>
                                                        @endif
                                                        @if ($kyc == null || ($kyc != null && $kyc->compliance != 1))
                                                            <label class="kt-avatar__upload"
                                                                data-toggle="kt-tooltip"
                                                                title="omang_pic_back">
                                                                <i class="fa fa-pen"></i>
                                                                <input type='file' class="omangpic_back"
                                                                    name="omangBack"
                                                                    <?php echo config('app.accept_attr'); ?>
                                                                    <?php echo config('app.accept_msg'); ?> />
                                                            </label>
                                                        @endif
                                                        <span class="kt-avatar__cancel"
                                                            data-toggle="kt-tooltip" title="Cancel Image">
                                                            <i class="fa fa-times"></i>
                                                        </span>
                                                    </div>
                                                </div>

                                                <div class="col-md-2">
                                                    <h3 class="col-form-label" style="float: left;">Proof
                                                        of Residence</h3>

                                                    <div class="kt-avatar" id="proofresidence"
                                                        style="float: left; clear: left;">
                                                        @if (!isset($kyc->proof_residence))
                                                            <div class="kt-avatar__holder"
                                                                style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)">
                                                            </div>
                                                        @else
                                                            <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($kyc->proof_residence) !!}"
                                                                target="_blank" download>
                                                                <div class="kt-avatar__holder"
                                                                    style="background-image:url('{!! \AlphaDirect\Helper::getCloudFrontURL($kyc->proof_residence) !!}')">
                                                                </div>
                                                            </a>
                                                        @endif
                                                        @if ($kyc == null || ($kyc != null && $kyc->compliance != 1))
                                                            <label class="kt-avatar__upload"
                                                                data-toggle="kt-tooltip"
                                                                title="proof_residence">
                                                                <i class="fa fa-pen"></i>
                                                                <input type='file' class="proofresidence"
                                                                    name="proof_residence"
                                                                    <?php echo config('app.accept_attr'); ?>
                                                                    <?php echo config('app.accept_msg'); ?> />
                                                            </label>
                                                        @endif
                                                        <span class="kt-avatar__cancel"
                                                            data-toggle="kt-tooltip" title="Cancel Image">
                                                            <i class="fa fa-times"></i>
                                                        </span>
                                                    </div>
                                                </div>
                                                <div class="col-md-2">
                                                    <h3 class="col-form-label" style="float: left;">Proof
                                                        Of Income</h3>
                                                    <div class="kt-avatar" id="proofincome"
                                                        style="float: left; clear: left;">
                                                        @if (!isset($kyc->proof_income))
                                                            <div class="kt-avatar__holder"
                                                                style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)">
                                                            </div>
                                                        @else
                                                            <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($kyc->proof_income) !!}"
                                                                target="_blank" download>
                                                                <div class="kt-avatar__holder"
                                                                    style="background-image:url('{!! \AlphaDirect\Helper::getCloudFrontURL($kyc->proof_income) !!}')">
                                                                </div>
                                                            </a>
                                                        @endif
                                                        @if ($kyc == null || ($kyc != null && $kyc->compliance != 1))
                                                            <label class="kt-avatar__upload"
                                                                data-toggle="kt-tooltip"
                                                                title="Change Image">
                                                                <i class="fa fa-pen"></i>
                                                                <input type='file' class="proofincome"
                                                                    <?php echo config('app.accept_attr'); ?>
                                                                    <?php echo config('app.accept_msg'); ?>
                                                                    name="proof_income" />
                                                            </label>
                                                        @endif
                                                        <span class="kt-avatar__cancel"
                                                            data-toggle="kt-tooltip" title="Cancel Image">
                                                            <i class="fa fa-times"></i>
                                                        </span>
                                                    </div>

                                                </div>
                                                <div class="col-md-2">
                                                    <h3 class="col-form-label" style="float: left;">
                                                        Passport</h3>
                                                    <div class="kt-avatar" id="passportpic"
                                                        style="float: left; clear: left;">
                                                        @if (!isset($kyc->passport))
                                                            <div class="kt-avatar__holder"
                                                                style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)">
                                                            </div>
                                                        @else
                                                            <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($kyc->passport) !!}"
                                                                target="_blank" download>
                                                                <div class="kt-avatar__holder"
                                                                    style="background-image:url('{!! \AlphaDirect\Helper::getCloudFrontURL($kyc->passport) !!}')">
                                                                </div>
                                                            </a>
                                                        @endif
                                                        @if ($kyc == null || ($kyc != null && $kyc->compliance != 1))
                                                            <label class="kt-avatar__upload"
                                                                data-toggle="kt-tooltip"
                                                                title="Change Image">
                                                                <i class="fa fa-pen"></i>
                                                                <input type='file' class="passportpic"
                                                                    <?php echo config('app.accept_attr'); ?>
                                                                    <?php echo config('app.accept_msg'); ?>
                                                                    name="passport_pic" />
                                                            </label>
                                                        @endif
                                                        <span class="kt-avatar__cancel"
                                                            data-toggle="kt-tooltip" title="Cancel Image">
                                                            <i class="fa fa-times"></i>
                                                        </span>
                                                    </div>

                                                </div>

                                                <div class="col-md-2">
                                                    <h3 class="col-form-label" style="float: left;">
                                                        Data Protection Consent</h3>
                                                    <div class="kt-avatar" id="dataProtectionConsent"
                                                        style="float: left; clear: left;">
                                                        @if (!isset($kyc->data_protection_consent))
                                                            <div class="kt-avatar__holder"
                                                                style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)">
                                                            </div>
                                                        @else
                                                            <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($kyc->data_protection_consent) !!}"
                                                                target="_blank" download>
                                                                <div class="kt-avatar__holder"
                                                                    style="background-image:url('{!! \AlphaDirect\Helper::getCloudFrontURL($kyc->data_protection_consent) !!}')">
                                                                    <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($kyc->data_protection_consent) !!}"
                                                                        target="_blank" download>
                                                                        @if(pathinfo($kyc->data_protection_consent,
                                                                        PATHINFO_EXTENSION) == 'pdf')
                                                                        <img src="{{asset('images/pdf.ico')}}" width="100%"
                                                                            height="auto">
                                                                        @elseif(pathinfo($kyc->data_protection_consent,
                                                                        PATHINFO_EXTENSION) == 'docx' ||
                                                                        pathinfo($kyc->data_protection_consent, PATHINFO_EXTENSION)
                                                                        == 'doc' || pathinfo($kyc->data_protection_consent,
                                                                        PATHINFO_EXTENSION) == 'docm')
                                                                        <img src="{{asset('images/word.ico')}}" width="100%"
                                                                            height="auto">
                                                                        @elseif(pathinfo($kyc->data_protection_consent,
                                                                        PATHINFO_EXTENSION) == 'xls' ||
                                                                        pathinfo($kyc->data_protection_consent, PATHINFO_EXTENSION)
                                                                        == 'xlsx' || pathinfo($kyc->data_protection_consent,
                                                                        PATHINFO_EXTENSION) == 'csv')
                                                                        <img src="{{asset('images/excel.png')}}" width="100%"
                                                                            height="auto">
                                                                        @elseif(pathinfo($kyc->data_protection_consent,
                                                                        PATHINFO_EXTENSION) == 'jpeg' ||
                                                                        pathinfo($kyc->data_protection_consent, PATHINFO_EXTENSION)
                                                                        == 'jpg' || pathinfo($kyc->data_protection_consent,
                                                                        PATHINFO_EXTENSION) == 'png')
                                                                        <img src="{{\AlphaDirect\Helper::getCloudFrontURL($kyc->data_protection_consent)}}"
                                                                            width="100%" height="auto">
                                                                        @else
                                                                        <img src="{{asset('images/doc.png')}}" width="100%"
                                                                            height="auto">
                                                                        @endif
                                                                    </a>
                                                                </div>
                                                            </a>
                                                        @endif
                                                        {{-- @if ($kyc == null || ($kyc != null && $kyc->compliance != 1)) --}}
                                                            <label class="kt-avatar__upload"
                                                                data-toggle="kt-tooltip"
                                                                title="Change Image">
                                                                <i class="fa fa-pen"></i>
                                                                <input type='file' class="dataProtectionConsent"
                                                                    <?php echo config('app.accept_attr'); ?>
                                                                    <?php echo config('app.accept_msg'); ?>
                                                                    name="data_protection_consent" />
                                                            </label>
                                                        {{-- @endif --}}
                                                        <span class="kt-avatar__cancel"
                                                            data-toggle="kt-tooltip" title="Cancel Image">
                                                            <i class="fa fa-times"></i>
                                                        </span>
                                                    </div>

                                                </div>
                                            </div>
                                            @if($kyc == null || ($kyc != null && $kyc->compliance != 1))
                                            <button type="submit" class="btn btn-primary">Upload Customer KYC Images</button>
                                            @endif
                                        </form>

                                        </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            @endif

                            {{-- @if ($customerMati && $customerMati != null)
                                    <div class="kt-portlet__head">
                                        <div class="kt-portlet__head-label">
                                            <h3 class="kt-portlet__head-title">
                                                Customer Verification Details
                                            </h3>
                                        </div>
                                    </div>
                                    <div class="kt-portlet_body">
                                        <div class="kt-section">
                                            <br>
                                            <h5 class="kt-portlet__head-title" style="margin-left: 20px;">Verification Status : @if ($customerMati && $customerMati->status != null)
                                                {{$customerMati->status}}
                            @else
                            {{'N/A'}}
                            @endif
                            </h5><br><br>

                            @if ($matiDetails && $matiDetails != null)
                            @foreach ($matiDetails as $customerMati)

                            <div class="kt-section__content" style="padding-left:20px; padding-right: 20px;">
                                <table class="table table-striped m-table">
                                    <tbody>
                                        @if ($customerMati->eventName == 'verification_started')
                                        <span style="font-size: 15px; font-weight: bold;">{!! str_replace("_", " ",
                                            $customerMati->eventName) !!}</span>
                                        <tr>
                                            <th>Event Name</th>
                                            <td>{{str_replace("_", " ", $customerMati->eventName)}}</td>
                                        </tr>
                                        @endif

                                        @if ($customerMati->eventName == 'step_completed' && $customerMati->step->id ==
                                        'ip-validation')
                                        <span style="font-size: 15px; font-weight: bold;">{!! str_replace("_", " ",
                                            $customerMati->eventName) !!}</span>
                                        <tr>
                                            <th>Step ID</th>
                                            <td>{{$customerMati->step->id}}</td>
                                            <th>Event Name</th>
                                            <td>{{str_replace("_", " ", $customerMati->eventName)}}</td>
                                        </tr>
                                        <tr>
                                            <th>Status</th>
                                            <td>{{$customerMati->step->status}}</td>
                                            <th>Country</th>
                                            <td>{{$customerMati->step->data->country}}</td>
                                        </tr>
                                        <tr>
                                            <th>Region</th>
                                            <td>{{$customerMati->step->data->region}}</td>
                                            <th>City</th>
                                            <td>{{$customerMati->step->data->city}}</td>
                                        </tr>
                                        <tr>
                                            <th>Zip</th>
                                            <td>{{$customerMati->step->data->zip}}</td>
                                            <th>Latitude</th>
                                            <td>{{$customerMati->step->data->latitude}}</td>
                                        </tr>
                                        <tr>
                                            <th>Longitude</th>
                                            <td>{{$customerMati->step->data->longitude}}</td>
                                            <th>
                                            <td></td>
                                            </th>
                                        </tr>
                                        @endif

                                        @if ($customerMati->eventName == 'step_completed' && $customerMati->step->id ==
                                        'document-reading')
                                        <span style="font-size: 15px; font-weight: bold;">{!! str_replace("_", " ",
                                            $customerMati->eventName) !!}</span>
                                        <tr>
                                            <th>Step ID</th>
                                            <td>{{$customerMati->step->id}}</td>
                                            <th>Event Name</th>
                                            <td>{{str_replace("_", " ", $customerMati->eventName)}}</td>
                                        </tr>
                                        <tr>
                                            <th>Status</th>
                                            <td>{{$customerMati->step->status}}</td>
                                            <th>FullName</th>
                                            <td>{{$customerMati->step->data->fullName->value}}</td>
                                        </tr>
                                        <tr>
                                            <th>Document Number</th>
                                            <td>{{$customerMati->step->data->documentNumber->value}}</td>
                                            <th>Date Of Birth</th>
                                            <td>{{$customerMati->step->data->dateOfBirth->value}}</td>
                                        </tr>
                                        <tr>
                                            <th>Expiration Date</th>
                                            <td>{{$customerMati->step->data->expirationDate->value}}</td>
                                            <th>Document Type</th>
                                            <td>{{str_replace("-", " ", $customerMati->step->documentType)}}</td>
                                        </tr>
                                        @endif

                                        @if ($customerMati->eventName == 'step_completed' && $customerMati->step->id ==
                                        'age-check')
                                        <span style="font-size: 15px; font-weight: bold;">{!! str_replace("_", " ",
                                            $customerMati->eventName) !!}</span>
                                        <tr>
                                            <th>Step ID</th>
                                            <td>{{$customerMati->step->id}}</td>
                                            <th>Event Name</th>
                                            <td>{{str_replace("_", " ", $customerMati->eventName)}}</td>
                                        </tr>
                                        <tr>
                                            <th>Status</th>
                                            <td>{{$customerMati->step->status}}</td>
                                            <th>Age</th>
                                            <td>{{$customerMati->step->data->age}}</td>
                                        </tr>
                                        <tr>
                                            <th>Age Threshold</th>
                                            <td>{{$customerMati->step->data->ageThreshold}}</td>
                                            <th>Underage</th>
                                            <td>{{$customerMati->step->data->underage}}</td>
                                        </tr>
                                        <tr>
                                            <th>Document Type</th>
                                            <td>{{str_replace("-", " ", $customerMati->step->documentType)}}</td>
                                            <th>
                                            <td></td>
                                            </th>
                                        </tr>
                                        @endif

                                        @if ($customerMati->eventName == 'step_completed' && $customerMati->step->id ==
                                        'watchlists')
                                        <span style="font-size: 15px; font-weight: bold;">{!! str_replace("_", " ",
                                            $customerMati->eventName) !!}</span>
                                        <tr>
                                            <th>Step ID</th>
                                            <td>{{$customerMati->step->id}}</td>
                                            <th>Event Name</th>
                                            <td>{{str_replace("_", " ", $customerMati->eventName)}}</td>
                                        </tr>
                                        <tr>
                                            <th>Status</th>
                                            <td>{{$customerMati->step->status}}</td>
                                            <th>Document Type</th>
                                            <td>{{str_replace("-", " ", $customerMati->step->documentType)}}</td>
                                        </tr>

                                        @endif

                                        @if ($customerMati->eventName == 'step_completed' && $customerMati->step->id ==
                                        'template-matching')
                                        <span style="font-size: 15px; font-weight: bold;">{!! str_replace("_", " ",
                                            $customerMati->eventName) !!}</span>
                                        <tr>
                                            <th>Step ID</th>
                                            <td>{{$customerMati->step->id}}</td>
                                            <th>Event Name</th>
                                            <td>{{str_replace("_", " ", $customerMati->eventName)}}</td>
                                        </tr>
                                        <tr>
                                            <th>Status</th>
                                            <td>{{$customerMati->step->status}}</td>
                                            <th>Document Type</th>
                                            <td>{{str_replace("-", " ", $customerMati->step->documentType)}}</td>
                                        </tr>

                                        @endif

                                        @if ($customerMati->eventName == 'verification_inputs_completed')
                                        <tr>
                                            <th>Event Name</th>
                                            <td>{{str_replace("_", " ", $customerMati->eventName)}}</td>
                                        </tr>
                                        @endif

                                        @if ($customerMati->eventName == 'step_completed' && $customerMati->step->id ==
                                        'selfie')
                                        <span style="font-size: 15px; font-weight: bold;">{!! str_replace("_", " ",
                                            $customerMati->eventName) !!}</span>
                                        <tr>
                                            <th>Step ID</th>
                                            <td>{{$customerMati->step->id}}</td>
                                            <th>Event Name</th>
                                            <td>{{str_replace("_", " ", $customerMati->eventName)}}</td>
                                        </tr>
                                        <tr>
                                            <th>Status</th>
                                            <td>{{$customerMati->step->status}}</td>
                                            <th>Photo</th>
                                            <td>
                                                @if($customerMati->step->data->selfiePhotoUrl != NULL)
                                                <img src="{{$customerMati->step->data->selfiePhotoUrl}}"
                                                    alt="selfie photo">
                                                @else
                                                {{'-'}}
                                                @endif
                                            </td>
                                        </tr>

                                        @endif

                                        @if ($customerMati->eventName == 'step_completed' && $customerMati->step->id ==
                                        'facematch')
                                        <span style="font-size: 15px; font-weight: bold;">{!! str_replace("_", " ",
                                            $customerMati->eventName) !!}</span>
                                        <tr>
                                            <th>Step ID</th>
                                            <td>{{$customerMati->step->id}}</td>
                                            <th>Event Name</th>
                                            <td>{{str_replace("_", " ", $customerMati->eventName)}}</td>
                                        </tr>
                                        <tr>
                                            <th>Status</th>
                                            <td>{{$customerMati->step->status}}</td>
                                            <th>Document Type</th>
                                            <td>{{str_replace("-", " ", $customerMati->step->documentType)}}</td>
                                        </tr>
                                        <tr>
                                            <th>Score</th>
                                            <td>{{$customerMati->step->data->score}}</td>
                                            <th>
                                            <td></td>
                                            </th>
                                        </tr>

                                        @endif

                                        @if ($customerMati->eventName == 'step_completed' && $customerMati->step->id ==
                                        'alteration-detection')
                                        <span style="font-size: 15px; font-weight: bold;">{!! str_replace("_", " ",
                                            $customerMati->eventName) !!}</span>
                                        <tr>
                                            <th>Step ID</th>
                                            <td>{{$customerMati->step->id}}</td>
                                            <th>Event Name</th>
                                            <td>{{str_replace("_", " ", $customerMati->eventName)}}</td>
                                        </tr>
                                        <tr>
                                            <th>Status</th>
                                            <td>{{$customerMati->step->status}}</td>
                                            <th>Document Type</th>
                                            <td>{{str_replace("-", " ", $customerMati->step->documentType)}}</td>
                                        </tr>

                                        @endif


                                        @if ($customerMati->eventName == 'verification_completed')
                                        <span style="font-size: 15px; font-weight: bold;">{!! str_replace("_", " ",
                                            $customerMati->eventName) !!}</span>
                                        <tr>
                                            <th>Browser</th>
                                            <td>
                                                @if ($customerMati && $customerMati->deviceFingerprint)
                                                {!! $customerMati->deviceFingerprint->browser->name !!}
                                                @else
                                                {{'-'}}
                                                @endif
                                            </td>
                                            <th>Event Name</th>
                                            <td>
                                                @if ($customerMati && $customerMati->eventName)
                                                {!! str_replace("_", " ", $customerMati->eventName) !!}
                                                @else
                                                {{'-'}}
                                                @endif
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>OS</th>
                                            <td>
                                                @if ($customerMati && $customerMati->deviceFingerprint)
                                                {!! $customerMati->deviceFingerprint->os->name !!}
                                                @else
                                                {{'-'}}
                                                @endif
                                            </td>
                                            <th>IP</th>
                                            <td>
                                                @if ($customerMati && $customerMati->deviceFingerprint)
                                                {!! $customerMati->deviceFingerprint->ip !!}
                                                @else
                                                {{'-'}}
                                                @endif
                                            </td>
                                        </tr>

                                        <tr>
                                            <th>Identity Status</th>
                                            <td>
                                                @if ($customerMati && $customerMati->identityStatus)
                                                {!! $customerMati->identityStatus !!}
                                                @else
                                                {{'-'}}
                                                @endif
                                            </td>
                                            <th>Status</th>
                                            <td>
                                                @if ($customerMati && $customerMati->status)
                                                {!! $customerMati->status !!}
                                                @else
                                                {{'-'}}
                                                @endif
                                            </td>
                                        </tr>

                                        @endif
                                    </tbody>
                                </table>
                            </div>
                            @endforeach
                            @endif

                        </div>
                    </div>
                    @endif --}}

                    <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>
                    <div class="kt-portlet__head ">
                        <div class="kt-portlet__head-label">
                            <h3 class="kt-portlet__head-title">
                                Product Details
                            </h3>

                        </div>

                    </div>
                    <div class="kt-portlet_body">
                        <div class="kt-section">
                            <div class="kt-section__content" style="padding-left:20px; padding-right: 20px;">
                                <table class="table table-striped m-table">
                                    <tbody>
                                    @if(isset($policy->is_bundled) && $policy->is_bundled == 1)
                                    <tr>
                                        <th>Product Type</th>
                                        <th style="color: blue;">Bundled Product</th>
                                    </tr>
                                    <tr>
                                        <th>Total Products</th>
                                        <td>{!! 1 + $PolicyBundled->count() !!}</td>
                                    </tr>
                                    @php $l = 1; @endphp
                                        @foreach($PolicyBundled as $PolicyBundleds)

                                        <tr>
                                            <th>{!! $l !!} . Product Name</th>

                                            <td>@if($PolicyBundleds->product_id == 1) P1 Million Accidental Death Insurance
                                                @elseif($PolicyBundleds->product_id == 2 && $PolicyBundleds->plan_name == 1)Third Party Car Insurance (P29 P100000 Cover)
                                                @elseif($PolicyBundleds->product_id == 2 && $PolicyBundleds->plan_name == 2)Third Party Car Insurance (P39 P500000 Cover)
                                                @elseif($PolicyBundleds->product_id == 2 && $PolicyBundleds->plan_name == 3)Third Party Car Insurance (P49 P1000000 Cover)
                                                @elseif($PolicyBundleds->product_id == 3) Motor Comprehensive
                                                @elseif($PolicyBundleds->product_id == 4 && $PolicyBundleds->plan_name == 1)Legal Insurance  (P49 Legal Insurance)
                                                @elseif($PolicyBundleds->product_id == 4 && $PolicyBundleds->plan_name == 2)Legal Insurance (P79 Funeral cover Insurance)
                                                @elseif($PolicyBundleds->product_id == 4 && $PolicyBundleds->plan_name == 3)Legal Insurance (P99 Funeral cover Insurance)
                                                @elseif($PolicyBundleds->product_id == 5) Mobile  And Electronic Device Instant Insurance
                                                @else --
                                                @endif
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Product Price</th>
                                            <td>@if(isset($PolicyBundleds->premium))P {!! $PolicyBundleds->premium !!} @else N/A @endif</td>
                                        </tr>
                                        @if($PolicyBundleds->product_id == 3 && isset($PolicyBundleds->frequency_mc))
                                         <tr>
                                            <th>Billing Frequency</th>
                                            <td>@if($PolicyBundleds->frequency_mc == 1) Monthly
                                                @elseif($PolicyBundleds->frequency_mc == 2) Three Installments
                                                @elseif($PolicyBundleds->frequency_mc == 3) Annual
                                                @else N/A @endif</td>
                                        </tr>
                                        @if($PolicyBundleds->frequency_mc == 1)

                                        <tr>
                                        <th>First Premium</th>
                                         @if ($policy->first_premium)
                                            <td>P {!! number_format($policy->first_premium, 2, '.', ',') !!}</td>
                                        </tr>
                                         @else
                                         <td>-</td>
                                        </tr>
                                         @endif
                                         @endif
                                         <tr>
                                                        <th>Sum Assured / Insured</th>
                                                            @if ($policy->sum_assured)
                                                            <td>P{!! number_format($policy->sum_assured, 0, '.', ',') !!}</td>
                                                            @else
                                                                <td>-</td>
                                                            @endif
                                        </tr>
                                        @endif

                                        @php  $l++; @endphp
                                        @endforeach

                                    @endif

                                        <tr>
                                            <th>
                                            @if(isset($policy->is_bundled) && $policy->is_bundled == 1) @php $m = 1; $m = 1 + $PolicyBundled->count(); @endphp
                                           {!! $m !!}. @endif Product Name</th>
                                            <td>{!! $product->name !!}</td>
                                        </tr>



                                            @if(isset($policy->is_bundled) && $policy->is_bundled == 1)
                                            <tr>
                                            <th>Product Price</th>
                                            <td>P {!! number_format( ( $PolicyBundleds->final_premium + $policy->bundled_discount - $PolicyBundleds->subtotal )     ,2,'.',',') !!}</td>
                                            </tr>

                                            @endif
           @if((isset($policy->is_bundled) && $policy->is_bundled == 1 ) && ($policy->product_id == 3))
           <tr>
                                            <th>Billing Frequency</th>
                                            <td>@if($policy->premium_freq == 1) Monthly
                                                @elseif($policy->premium_freq == 2) Three Installments
                                                @elseif($policy->premium_freq == 3) Annual
                                                @else N/A @endif</td>
                                        </tr>
            @if($policy->premium_freq == 1)
                <tr>
                    <th>First Premium</th>
                    @if ($policy->first_premium)
                        <td>P {!! number_format($policy->first_premium, 2, '.', ',') !!}</td>
                    </tr>
                    @else
                        <td>-</td>
                    </tr>
                @endif
            @endif
             <tr>
              <th>Sum Assured / Insured</th>
                  @if ($policy->sum_assured)
                  <td>P{!! number_format($policy->sum_assured, 0, '.', ',') !!}</td>
                  @else
                      <td>-</td>
                  @endif
             </tr>
               @endif
                                         @if(isset($policy->is_bundled) && $policy->is_bundled == 1)
                                         <tr>
                                            <th>Total Price</th>
                                            <td>P {!! number_format( ( $PolicyBundleds->final_premium + $policy->bundled_discount )     ,2,'.',',') !!}</td>
                                            </tr>
                                        <tr>
                                            <th>Discount</th>
                                            <td>@if(isset($policy->bundled_discount_precent)){!! $policy->bundled_discount_precent !!} % @else N/A @endif</td>
                                        </tr>
                                        <tr>
                                            <th>Discount Amount</th>
                                            <td>@if(isset($policy->bundled_discount))P {!! $policy->bundled_discount !!} @else N/A @endif</td>
                                        </tr>
                                        <tr>
                                            <th>Product Final Premium</th>
                                            <td>@if(isset($PolicyBundleds->final_premium)) P {!! $PolicyBundleds->final_premium !!}@else N/A @endif</td>
                                        </tr>
                                        <tr>
                                            <th>Net Premium</th>
                                            <td>@if(isset($PolicyBundleds->final_premium)) P {!! number_format((($PolicyBundleds->final_premium)*86/100),2,'.',',') !!}@else N/A @endif</td>
                                        </tr>
                                        <tr>
                                            <th>Vat</th>
                                            <td>@if(isset($PolicyBundleds->final_premium)) P {!! number_format((($PolicyBundleds->final_premium)*14/100),2,'.',',') !!}@else N/A @endif</td>
                                        </tr>
                                        <tr>
                                            <th>Gross Premium</th>
                                            <td>@if(isset($PolicyBundleds->final_premium)) P {!! $PolicyBundleds->final_premium !!}@else N/A @endif</td>
                                        </tr>



                                         @else
                                        <tr>
                                            <th>Premium</th>
                                            <td>P {!! number_format($policy->premium,2,'.',',') !!}</td>

                                         </tr>
                                        {{-- @if($policy->first_premium_wvat != null && $policy->vat_percent &&
                                        $policy->premium_freq == 1) --}}
                                        <tr>
                                            <th>Monthly Premium</th>
                                            <td>P {!! number_format($monthly_Premium,2,'.',',') !!}</td>
                                        </tr>
                                        @endif
                                        @if(isset($policy->is_bundled) && $policy->is_bundled == 1)
                                        @else
                                        <tr>
                                            <th>Three Installment Premium</th>
                                            <td>P{!! number_format($three_Installment,2,'.',',') !!}</td>
                                        </tr>
                                        <tr>
                                            <th>Annual Premium</th>
                                            <td>P {!! number_format($annual_Premium,2,'.',',') !!}</td>
                                        </tr>

                                        {{-- @endif --}}
                                        @if($policy->plan_id != NULL)
                                        <tr>
                                            <th>Product Plan</th>
                                            <td>{!! $productPlan->name !!}</td>
                                        </tr>
                                        @endif
                                        @endif
                                          @if(isset($policy->is_bundled) && $policy->is_bundled == 1)

                                          @else
                                           <tr>
                                                        <th>Sum Assured / Insured</th>
                                                            @if ($policy->sum_assured)
                                                            <td>P{!! number_format($policy->sum_assured, 0, '.', ',') !!}</td>
                                                            @else
                                                                <td>-</td>
                                                            @endif
                                        </tr>
                                          @endif
                                        <tr>
                                            <th>Store Name</th>
                                            @if($storeName != null)
                                            <td>{!! $storeName !!}</td>
                                            @else
                                            <td>N/A</td>
                                            @endif

                                        </tr>

                                        <tr>
                                            <th>Agency Name</th>
                                            @if($agent_name != null && $agent_name->agency_id)
                                            @if($agent_name->agency_name)
                                            <td>{!! $agent_name->agency_name !!}</td>
                                            @else
                                            <td style="color:red">Agency Name not found</td>
                                            @endif
                                            @else
                                            <td>N/A</td>
                                            @endif

                                        </tr>
                                        @if(isset($policy->is_bundled) && $policy->is_bundled == 1)
                                        @else
                                        {{-- @isset($policy->first_premium) --}}

                                            <tr>
                                                <th>First Premium</th>
                                                @if ($policy->first_premium != null && $policy->premium_freq == 1)
                                                    <td>P{!! number_format($policy->first_premium,2,'.',',') !!}</td>
                                                @else
                                                    <td>N/A</td>
                                                @endif
                                            </tr>

                                        {{-- @endisset --}}
                                        @endif
                                        @foreach($productFactors as $productFactor)
                                        @if($productFactor->type == 'Select')
                                        <tr>
                                            <th>{!! $productFactor->name !!}</th>
                                            <td>{!! implode(',', $productFactor->policyFactorsValueName)
                                                !!}</td>
                                        </tr>
                                        @elseif($productFactor->type == 'Radio')
                                        <tr>
                                            <th>{!! $productFactor->name !!}</th>
                                            <td>{!! implode(',', $productFactor->policyFactorsValueName)
                                                !!}</td>
                                        </tr>
                                        @elseif($productFactor->type == 'Checkbox')
                                        <tr>
                                            <th>{!! $productFactor->name !!}</th>
                                            <td>{!! implode(', ',
                                                $productFactor->policyFactorsValueName) !!}</td>
                                        </tr>
                                        @elseif($productFactor->type == 'Input Field')
                                        <tr>
                                            <th>{!! $productFactor->name !!}</th>
                                            <td>{!! implode(',', $productFactor->policyFactorsValueName)
                                                !!}</td>
                                        </tr>
                                        @endif
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>
                    <!-- Start Tyres & Rim Section -->

                    @if($product->id == 6)
                    <div class="TyreRims">
                        <!-- Start Vehicle Details -->
                        @if(count($vehicleDetailsTyreRim)>0)
                            @php $i = 1 ; @endphp
                            @foreach($vehicleDetailsTyreRim as $vTyreRim)
                                <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>
                                <div class="kt-portlet__head">
                                    <div class="kt-portlet__head-label">
                                        <h3 class="kt-portlet__head-title">
                                            Vehicle Details  {{$i}}
                                        </h3>
                                    </div>
                                </div>
                                @php $i++; @endphp
                                <div class="kt-portlet_body">
                                    <div class="kt-section">
                                        <div class="kt-section__content" style="padding-left:20px; padding-right: 20px;">
                                            <table class="table table-striped m-table">
                                                <tbody>
                                                    <tr>
                                                        <th>Vehicle Number</th>
                                                        <td>@if($vTyreRim->vehiclePlate != null) {!! strtoupper($vTyreRim->vehiclePlate) !!} @else N/A  @endif</td>
                                                        <th>Vehicle Purpose</th>
                                                        <td>
                                                            @if($vTyreRim->purpose != null)
                                                                @foreach($vehiclePurposeTyreRim as $purpose)
                                                                    @if($purpose->id == $vTyreRim->purpose)
                                                                    {!! $purpose->value !!}
                                                                    @endif
                                                                @endforeach
                                                            @else N/A  @endif
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <th>Make</th>
                                                        <td>@if($vTyreRim->make != null) {!! $vTyreRim->make !!} @else N/A  @endif</td>
                                                        <th>Model</th>
                                                        <td>@if($vTyreRim->model != null) {!! $vTyreRim->model !!} @else N/A  @endif</td>
                                                    </tr>
                                                    <tr>
                                                        <th>Year of Manufacturing</th>
                                                        <td>@if($vTyreRim->year != null) {!! $vTyreRim->year !!} @else N/A  @endif</td>

                                                        <th>Is it imported?</th>
                                                        @if($vTyreRim->is_imported != null)
                                                            @if($vTyreRim->is_imported == 0)
                                                            <td>No</td>
                                                            @else
                                                            <td>Yes</td>
                                                            @endif
                                                        @else N/A  @endif
                                                    </tr>
                                                </tbody>
                                            </table>
                                            <div class="form-group row">
                                                <div class="col-md-2">
                                                    <h3 class="col-form-label" style="float: left;">Invoice</h3>
                                                    @if($vTyreRim->tyre_invoice == NULL)
                                                       <div class="kt-avatar__holder" id="" style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)";></div>
                                                    @else
                                                    <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($vTyreRim->tyre_invoice) !!}"
                                                        target="_blank" download>
                                                        @if(pathinfo($vTyreRim->tyre_invoice,PATHINFO_EXTENSION) ==
                                                        'pdf')
                                                        <img src="{{asset('images/pdf.ico')}}" width="100%"
                                                            height="auto">
                                                                @elseif(pathinfo($vTyreRim->tyre_invoice,
                                                                PATHINFO_EXTENSION) == 'docx' ||
                                                                pathinfo($vTyreRim->tyre_invoice, PATHINFO_EXTENSION)
                                                                == 'doc' ||
                                                                pathinfo($vTyreRim->tyre_invoice,PATHINFO_EXTENSION) ==
                                                                'docm')
                                                        <img src="{{asset('images/word.ico')}}" width="100%"
                                                            height="auto">
                                                            @elseif(pathinfo($vTyreRim->tyre_invoice,
                                                            PATHINFO_EXTENSION) == 'xls' ||
                                                            pathinfo($vTyreRim->tyre_invoice, PATHINFO_EXTENSION)
                                                            == 'xlsx' ||
                                                            pathinfo($vTyreRim->tyre_invoice,PATHINFO_EXTENSION) ==
                                                            'csv')
                                                            <img src="{{asset('images/excel.png')}}" width="100%"
                                                                height="auto">
                                                            @elseif(pathinfo($vTyreRim->tyre_invoice,
                                                            PATHINFO_EXTENSION) == 'jpeg'
                                                            ||pathinfo($vTyreRim->tyre_invoice,
                                                            PATHINFO_EXTENSION) == 'JPEG' ||
                                                            pathinfo($vTyreRim->tyre_invoice, PATHINFO_EXTENSION)
                                                            == 'jpg' || pathinfo($vTyreRim->tyre_invoice,
                                                            PATHINFO_EXTENSION) == 'png')
                                                            <img src="{{ \AlphaDirect\Helper::getCloudFrontURL($vTyreRim->tyre_invoice) }}"
                                                                width="100%" height="auto">
                                                            @else
                                                            <img src="{{asset('images/doc.png')}}" width="100%"
                                                                height="auto">
                                                            @endif
                                                        </a>
                                                    @endif
                                                </div>
                                                <div class="col-md-2">
                                                   <h3 class="col-form-label" style="float: left;">Picture KM’s of the Car</h3>
                                                   <div class="kt-avatar" id="front" style="float: left; clear: left;">
                                                        @if($vTyreRim->km_of_car == NULL)
                                                        <div class="kt-avatar__holder"
                                                            style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)">
                                                        </div>
                                                        @else
                                                        <a href="{{ \AlphaDirect\Helper::getCloudFrontURL($vTyreRim->km_of_car) }}" target="_blank" download>
                                                            <div class="kt-avatar__holder"
                                                                style="background-image:url('{!! \AlphaDirect\Helper::getCloudFrontURL($vTyreRim->km_of_car) !!}')">
                                                            </div>
                                                        </a>
                                                        @endif
                                                        <span class="kt-avatar__cancel" data-toggle="kt-tooltip"
                                                            title="Cancel Image"> <i class="fa fa-times"></i>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>
                            @endforeach
                        @endif
                        <!--/ End Vehicle Details -->
                        <!-- Start Tyres & Rim Images -->
                        @if(count($vehiclePolicyTyreRim)>0)
                        <div class="kt-portlet__head">
                            <div class="kt-portlet__head-label">
                                <h3 class="kt-portlet__head-title">
                                    Tyres and Rims Images
                                </h3>
                            </div>
                        </div>
                        <div class="kt-portlet_body">
                            <div class="kt-section">
                                <div class="kt-section__content" style="padding-left:20px; padding-right: 20px;">
                                    <div class="form-group row">
                                        @foreach($vehiclePolicyTyreRim as $vehicle)
                                        <div class="col-md-2">
                                            <h3 class="col-form-label" style="float: left;">@if($vehicle->position != NULL){{$vehicle->position}}@else N/A @endif</h3>
                                            <div class="kt-avatar" id="front" style="float: left; clear: left;">
                                                @if($vehicle->image == NULL)
                                                <div class="kt-avatar__holder"
                                                    style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)">
                                                </div>
                                                @else
                                                <a href="{{ \AlphaDirect\Helper::getCloudFrontURL($vehicle->image) }}" target="_blank" download>
                                                    <div class="kt-avatar__holder"
                                                        style="background-image:url('{!! \AlphaDirect\Helper::getCloudFrontURL($vehicle->image) !!}')">
                                                    </div>
                                                </a>
                                                @endif
                                                <span class="kt-avatar__cancel" data-toggle="kt-tooltip"
                                                    title="Cancel Image"> <i class="fa fa-times"></i>
                                                </span>
                                            </div>
                                            <div>
                                                <table class="table table-striped m-table">
                                                    <tbody>
                                                        <tr>
                                                            <th colspan='2' style="text-align: center;">@if($vehicle->tyre_insure != NULL){{$vehicle->tyre_insure}}@endif</th>
                                                        </tr>
                                                        <tr>
                                                            <th>Vehicle</th>
                                                            <td>@if($vehicle->vehicle_id != NULL){{$vehicle->Vehicle->vehiclePlate}}@else N/A @endif</td>
                                                        </tr>
                                                        <tr>
                                                            <th>Size</th>
                                                            <td>@if($vehicle->size != NULL){{$vehicle->size}}@else N/A @endif</td>
                                                        </tr>
                                                        <tr>
                                                            <th>Value</th>
                                                            <td>@if($vehicle->value != NULL){{$vehicle->value}}@else N/A @endif</td>
                                                        </tr>
                                                        <tr>
                                                            <th>Dot No.</th>
                                                            <td>@if($vehicle->dot_number != NULL){{$vehicle->dot_number}}@else N/A @endif</td>
                                                        </tr>
                                                        <tr>
                                                            <th>Barcode</th>
                                                            <td>@if($vehicle->barcode != NULL){{$vehicle->barcode}}@else N/A @endif</td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endif
                        <!--/ End Tyres & Rims Images -->
                    </div>
                    @endif
                    <!--/ End Tyres & Rims Section -->

                    @if($policy->is_bundled == 1)
                    @php $p_id = 0; @endphp
                    @foreach($PolicyBundled as $PolicyBundleds)

                    @if($PolicyBundleds->product_id == 5)
                    @php $p_id = 5; @endphp
                    @endif
                    @endforeach
                    @endif

                    @if($product->type == 'Cellphone' || ($policy->is_bundled == 1 && $p_id == 5 ))
                    <form action="{{ route('admin.policy.updateDevices') }}" method="POST" enctype="multipart/form-data"
                        class="kt-form">
                        <!-- CSRF Token -->
                        <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                        <input type="hidden" name="policy_id" value="{{ $policy->id }}">
                        <input type="hidden" name="customer_id" value="{{ $policy->customer_id }}">
                        <div class="kt-repeater" id="cellphonesection">
                            <div data-repeater-list="devices" style="padding-left:20px; padding-right: 20px;">
                                @foreach($policy_cellphone as $key => $pok)
                                <div class="kt-portlet__head">
                                    <div class="kt-portlet__head-label">
                                        <h3 class="kt-portlet__head-title">
                                            Device Details : {!! $key+1 !!}
                                        </h3>
                                    </div>
                                </div>
                                <table class="table table-striped m-table">
                                    <tbody>
                                        <tr>
                                            <th>Device Type</th>
                                            <td>{!! $pok->device_type !!}</td>
                                            @if($pok->device_type == 'cellphone')
                                            <th>IMEI</th>
                                            @else
                                            <th>Serial Number</th>
                                            @endif
                                            <td>{!! $pok->imei !!}</td>
                                        </tr>
                                        <tr>
                                            <th>Device Brand</th>
                                            <td>{!! $pok->cell_phone_make !!}</td>
                                            <th>Device Model</th>
                                            <td>{!! $pok->cell_phone_model !!}</td>
                                        </tr>
                                        <tr>
                                            <th>Device Value</th>
                                            <td>P{!! number_format($pok->phone_value,0,'.',',') !!}</td>
                                            <th></th>
                                            <td></td>
                                        </tr>
                                        @if($pok->status == 1)
                                        <tr>
                                            <th>Front</th>
                                            <td>
                                                <div class="col-md-4">
                                                    <div class="kt-avatar" id="" style="float: left; clear: left;">
                                                        @if($pok != NULL && $pok->cell_phone_front == NULL)

                                                        <div class="kt-avatar__holder" id=""
                                                            style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"
                                                            ;></div>
                                                        @else
                                                        <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($pok->cell_phone_front) !!}"
                                                            target="_blank" download>
                                                            @if(pathinfo($pok->cell_phone_front,PATHINFO_EXTENSION) ==
                                                            'pdf')
                                                            <img src="{{asset('images/pdf.ico')}}" width="100%"
                                                                height="auto">
                                                            @elseif(pathinfo($pok->cell_phone_front,
                                                            PATHINFO_EXTENSION) == 'docx' ||
                                                            pathinfo($pok->cell_phone_front, PATHINFO_EXTENSION)
                                                            == 'doc' ||
                                                            pathinfo($pok->cell_phone_front,PATHINFO_EXTENSION) ==
                                                            'docm')
                                                            <img src="{{asset('images/word.ico')}}" width="100%"
                                                                height="auto">
                                                            @elseif(pathinfo($pok->cell_phone_front,
                                                            PATHINFO_EXTENSION) == 'xls' ||
                                                            pathinfo($pok->cell_phone_front, PATHINFO_EXTENSION)
                                                            == 'xlsx' ||
                                                            pathinfo($pok->cell_phone_front,PATHINFO_EXTENSION) ==
                                                            'csv')
                                                            <img src="{{asset('images/excel.png')}}" width="100%"
                                                                height="auto">
                                                            @elseif(pathinfo($pok->cell_phone_front,
                                                            PATHINFO_EXTENSION) == 'jpeg'
                                                            ||pathinfo($pok->cell_phone_front,
                                                            PATHINFO_EXTENSION) == 'JPEG' ||
                                                            pathinfo($pok->cell_phone_front, PATHINFO_EXTENSION)
                                                            == 'jpg' || pathinfo($pok->cell_phone_front,
                                                            PATHINFO_EXTENSION) == 'png')
                                                            <img src="{{ \AlphaDirect\Helper::getCloudFrontURL($pok->cell_phone_front) }}"
                                                                width="100%" height="auto">
                                                            @else
                                                            <img src="{{asset('images/doc.png')}}" width="100%"
                                                                height="auto">
                                                            @endif
                                                        </a>
                                                        @endif
                                                        <label class="kt-avatar__cancel" data-toggle="kt-tooltip"
                                                            title="Change Image">
                                                            <i class="fa fa-pen"></i>
                                                            <input type='file' class="" name="cell_phone_front"
                                                                <?php echo config('app.accept_attr'); ?>
                                                                <?php echo config('app.accept_msg'); ?> />
                                                        </label>
                                                        <span class="kt-avatar__cancel" data-toggle="kt-tooltip"
                                                            title="Cancel Image">
                                                            <i class="fa fa-times"></i>
                                                        </span>
                                                    </div>
                                                </div>
                                            </td>
                                            <th>Back</th>
                                            <td>
                                                <div class="col-md-4">
                                                    <div class="kt-avatar" style="float: left; clear: left;">
                                                        @if($pok != null && $pok->cell_phone_back == NULL)
                                                        <div class="kt-avatar__holder"
                                                            style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"
                                                            ;></div>
                                                        @else
                                                        <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($pok->cell_phone_back) !!}"
                                                            target="_blank" download>
                                                            @if(pathinfo($pok->cell_phone_back,PATHINFO_EXTENSION) ==
                                                            'pdf')
                                                            <img src="{{asset('images/pdf.ico')}}" width="100%"
                                                                height="auto">
                                                            @elseif(pathinfo($pok->cell_phone_back,
                                                            PATHINFO_EXTENSION) == 'docx' ||
                                                            pathinfo($pok->cell_phone_back, PATHINFO_EXTENSION)
                                                            == 'doc' ||
                                                            pathinfo($pok->cell_phone_back,PATHINFO_EXTENSION) ==
                                                            'docm')
                                                            <img src="{{asset('images/word.ico')}}" width="100%"
                                                                height="auto">
                                                            @elseif(pathinfo($pok->cell_phone_back,
                                                            PATHINFO_EXTENSION) == 'xls' ||
                                                            pathinfo($pok->cell_phone_back, PATHINFO_EXTENSION)
                                                            == 'xlsx' ||
                                                            pathinfo($pok->cell_phone_back,PATHINFO_EXTENSION) == 'csv')
                                                            <img src="{{asset('images/excel.png')}}" width="100%"
                                                                height="auto">
                                                            @elseif(pathinfo($pok->cell_phone_back,
                                                            PATHINFO_EXTENSION) == 'jpeg'
                                                            ||pathinfo($pok->cell_phone_back,
                                                            PATHINFO_EXTENSION) == 'JPEG' ||
                                                            pathinfo($pok->cell_phone_back, PATHINFO_EXTENSION)
                                                            == 'jpg' ||
                                                            pathinfo($pok->cell_phone_back,PATHINFO_EXTENSION) == 'png')
                                                            <img src="{{ \AlphaDirect\Helper::getCloudFrontURL($pok->cell_phone_back) }}"
                                                                width="100%" height="auto">
                                                            @else
                                                            <img src="{{asset('images/doc.png')}}" width="100%"
                                                                height="auto">
                                                            @endif
                                                        </a>
                                                        @endif
                                                        <label class="kt-avatar__cancel" data-toggle="kt-tooltip"
                                                            title="Change Image">
                                                            <i class="fa fa-pen"></i>
                                                            <input type='file' class="" name="cell_phone_back"
                                                                <?php echo config('app.accept_attr'); ?>
                                                                <?php echo config('app.accept_msg'); ?> />
                                                        </label>
                                                        <span class="kt-avatar__cancel" data-toggle="kt-tooltip"
                                                            title="Cancel Image">
                                                            <i class="fa fa-times"></i>
                                                        </span>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Left</th>
                                            <td>
                                                <div class="col-md-4">
                                                    <div class="kt-avatar" id="" style="float: left; clear: left;">
                                                        @if($pok != NULL && $pok->cell_phone_left == NULL)

                                                        <div class="kt-avatar__holder" id=""
                                                            style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"
                                                            ;></div>
                                                        @else
                                                        <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($pok->cell_phone_left) !!}"
                                                            target="_blank" download>
                                                            @if(pathinfo($pok->cell_phone_left,PATHINFO_EXTENSION) ==
                                                            'pdf')
                                                            <img src="{{asset('images/pdf.ico')}}" width="100%"
                                                                height="auto">
                                                            @elseif(pathinfo($pok->cell_phone_left,
                                                            PATHINFO_EXTENSION) == 'docx' ||
                                                            pathinfo($pok->cell_phone_left, PATHINFO_EXTENSION)
                                                            == 'doc' ||
                                                            pathinfo($pok->cell_phone_left,PATHINFO_EXTENSION) ==
                                                            'docm')
                                                            <img src="{{asset('images/word.ico')}}" width="100%"
                                                                height="auto">
                                                            @elseif(pathinfo($pok->cell_phone_left,
                                                            PATHINFO_EXTENSION) == 'xls' ||
                                                            pathinfo($pok->cell_phone_left, PATHINFO_EXTENSION)
                                                            == 'xlsx' ||
                                                            pathinfo($pok->cell_phone_left,PATHINFO_EXTENSION) == 'csv')
                                                            <img src="{{asset('images/excel.png')}}" width="100%"
                                                                height="auto">
                                                            @elseif(pathinfo($pok->cell_phone_left,
                                                            PATHINFO_EXTENSION) == 'jpeg'
                                                            ||pathinfo($pok->cell_phone_left,
                                                            PATHINFO_EXTENSION) == 'JPEG' ||
                                                            pathinfo($pok->cell_phone_left, PATHINFO_EXTENSION)
                                                            == 'jpg' || pathinfo($pok->cell_phone_left,
                                                            PATHINFO_EXTENSION) == 'png')
                                                            <img src="{{ \AlphaDirect\Helper::getCloudFrontURL($pok->cell_phone_left) }}"
                                                                width="100%" height="auto">
                                                            @else
                                                            <img src="{{asset('images/doc.png')}}" width="100%"
                                                                height="auto">
                                                            @endif
                                                        </a>
                                                        @endif
                                                        <label class="kt-avatar__cancel" data-toggle="kt-tooltip"
                                                            title="Change Image">
                                                            <i class="fa fa-pen"></i>
                                                            <input type='file' class="" name="cell_phone_left"
                                                                <?php echo config('app.accept_attr'); ?>
                                                                <?php echo config('app.accept_msg'); ?> />
                                                        </label>
                                                        <span class="kt-avatar__cancel" data-toggle="kt-tooltip"
                                                            title="Cancel Image">
                                                            <i class="fa fa-times"></i>
                                                        </span>
                                                    </div>
                                                </div>
                                            </td>
                                            <th>Right</th>
                                            <td>
                                                <div class="col-md-4">
                                                    <div class="kt-avatar" style="float: left; clear: left;">
                                                        @if($pok != null && $pok->cell_phone_right == NULL)
                                                        <div class="kt-avatar__holder"
                                                            style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"
                                                            ;></div>
                                                        @else
                                                        <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($pok->cell_phone_right) !!}"
                                                            target="_blank" download>
                                                            @if(pathinfo($pok->cell_phone_right,PATHINFO_EXTENSION) ==
                                                            'pdf')
                                                            <img src="{{asset('images/pdf.ico')}}" width="100%"
                                                                height="auto">
                                                            @elseif(pathinfo($pok->cell_phone_right,
                                                            PATHINFO_EXTENSION) == 'docx' ||
                                                            pathinfo($pok->cell_phone_right, PATHINFO_EXTENSION)
                                                            == 'doc' ||
                                                            pathinfo($pok->cell_phone_right,PATHINFO_EXTENSION) ==
                                                            'docm')
                                                            <img src="{{asset('images/word.ico')}}" width="100%"
                                                                height="auto">
                                                            @elseif(pathinfo($pok->cell_phone_right,
                                                            PATHINFO_EXTENSION) == 'xls' ||
                                                            pathinfo($pok->cell_phone_right, PATHINFO_EXTENSION)
                                                            == 'xlsx' ||
                                                            pathinfo($pok->cell_phone_right,PATHINFO_EXTENSION) ==
                                                            'csv')
                                                            <img src="{{asset('images/excel.png')}}" width="100%"
                                                                height="auto">
                                                            @elseif(pathinfo($pok->cell_phone_right,
                                                            PATHINFO_EXTENSION) == 'jpeg'
                                                            ||pathinfo($pok->cell_phone_right,
                                                            PATHINFO_EXTENSION) == 'JPEG' ||
                                                            pathinfo($pok->cell_phone_right, PATHINFO_EXTENSION)
                                                            == 'jpg' ||
                                                            pathinfo($pok->cell_phone_right,PATHINFO_EXTENSION) ==
                                                            'png')
                                                            <img src="{{ \AlphaDirect\Helper::getCloudFrontURL($pok->cell_phone_right) }}"
                                                                width="100%" height="auto">
                                                            @else
                                                            <img src="{{asset('images/doc.png')}}" width="100%"
                                                                height="auto">
                                                            @endif
                                                        </a>
                                                        @endif
                                                        <label class="kt-avatar__cancel" data-toggle="kt-tooltip"
                                                            title="Change Image">
                                                            <i class="fa fa-pen"></i>
                                                            <input type='file' class="" name="cell_phone_right"
                                                                <?php echo config('app.accept_attr'); ?>
                                                                <?php echo config('app.accept_msg'); ?> />
                                                        </label>
                                                        <span class="kt-avatar__cancel" data-toggle="kt-tooltip"
                                                            title="Cancel Image">
                                                            <i class="fa fa-times"></i>
                                                        </span>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Top</th>
                                            <td>
                                                <div class="col-md-4">
                                                    <div class="kt-avatar" id="" style="float: left; clear: left;">
                                                        @if($pok != NULL && $pok->cell_phone_top == NULL)

                                                        <div class="kt-avatar__holder" id=""
                                                            style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"
                                                            ;></div>
                                                        @else
                                                        <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($pok->cell_phone_top) !!}"
                                                            target="_blank" download>
                                                            @if(pathinfo($pok->cell_phone_top,PATHINFO_EXTENSION) ==
                                                            'pdf')
                                                            <img src="{{asset('images/pdf.ico')}}" width="100%"
                                                                height="auto">
                                                            @elseif(pathinfo($pok->cell_phone_top,
                                                            PATHINFO_EXTENSION) == 'docx' ||
                                                            pathinfo($pok->cell_phone_top, PATHINFO_EXTENSION)
                                                            == 'doc' ||
                                                            pathinfo($pok->cell_phone_top,PATHINFO_EXTENSION) == 'docm')
                                                            <img src="{{asset('images/word.ico')}}" width="100%"
                                                                height="auto">
                                                            @elseif(pathinfo($pok->cell_phone_top,
                                                            PATHINFO_EXTENSION) == 'xls' ||
                                                            pathinfo($pok->cell_phone_top, PATHINFO_EXTENSION)
                                                            == 'xlsx' ||
                                                            pathinfo($pok->cell_phone_top,PATHINFO_EXTENSION) == 'csv')
                                                            <img src="{{asset('images/excel.png')}}" width="100%"
                                                                height="auto">
                                                            @elseif(pathinfo($pok->cell_phone_top,
                                                            PATHINFO_EXTENSION) == 'jpeg'
                                                            ||pathinfo($pok->cell_phone_top,
                                                            PATHINFO_EXTENSION) == 'JPEG' ||
                                                            pathinfo($pok->cell_phone_top, PATHINFO_EXTENSION)
                                                            == 'jpg' || pathinfo($pok->cell_phone_top,
                                                            PATHINFO_EXTENSION) == 'png')
                                                            <img src="{{ \AlphaDirect\Helper::getCloudFrontURL($pok->cell_phone_top) }}"
                                                                width="100%" height="auto">
                                                            @else
                                                            <img src="{{asset('images/doc.png')}}" width="100%"
                                                                height="auto">
                                                            @endif
                                                        </a>
                                                        @endif
                                                        <label class="kt-avatar__cancel" data-toggle="kt-tooltip"
                                                            title="Change Image">
                                                            <i class="fa fa-pen"></i>
                                                            <input type='file' class="" name="cell_phone_top"
                                                                <?php echo config('app.accept_attr'); ?>
                                                                <?php echo config('app.accept_msg'); ?> />
                                                        </label>
                                                        <span class="kt-avatar__cancel" data-toggle="kt-tooltip"
                                                            title="Cancel Image">
                                                            <i class="fa fa-times"></i>
                                                        </span>
                                                    </div>
                                                </div>
                                            </td>
                                            <th>Bottom</th>
                                            <td>
                                                <div class="col-md-4">
                                                    <div class="kt-avatar" style="float: left; clear: left;">
                                                        @if($pok != null && $pok->cell_phone_bottom == NULL)
                                                        <div class="kt-avatar__holder"
                                                            style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"
                                                            ;></div>
                                                        @else
                                                        <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($pok->cell_phone_bottom) !!}"
                                                            target="_blank" download>
                                                            @if(pathinfo($pok->cell_phone_bottom,PATHINFO_EXTENSION) ==
                                                            'pdf')
                                                            <img src="{{asset('images/pdf.ico')}}" width="100%"
                                                                height="auto">
                                                            @elseif(pathinfo($pok->cell_phone_bottom,
                                                            PATHINFO_EXTENSION) == 'docx' ||
                                                            pathinfo($pok->cell_phone_bottom, PATHINFO_EXTENSION)
                                                            == 'doc' ||
                                                            pathinfo($pok->cell_phone_bottom,PATHINFO_EXTENSION) ==
                                                            'docm')
                                                            <img src="{{asset('images/word.ico')}}" width="100%"
                                                                height="auto">
                                                            @elseif(pathinfo($pok->cell_phone_bottom,
                                                            PATHINFO_EXTENSION) == 'xls' ||
                                                            pathinfo($pok->cell_phone_bottom, PATHINFO_EXTENSION)
                                                            == 'xlsx' ||
                                                            pathinfo($pok->cell_phone_bottom,PATHINFO_EXTENSION) ==
                                                            'csv')
                                                            <img src="{{asset('images/excel.png')}}" width="100%"
                                                                height="auto">
                                                            @elseif(pathinfo($pok->cell_phone_bottom,
                                                            PATHINFO_EXTENSION) == 'jpeg'
                                                            ||pathinfo($pok->cell_phone_bottom,
                                                            PATHINFO_EXTENSION) == 'JPEG' ||
                                                            pathinfo($pok->cell_phone_bottom, PATHINFO_EXTENSION)
                                                            == 'jpg' ||
                                                            pathinfo($pok->cell_phone_bottom,PATHINFO_EXTENSION) ==
                                                            'png')
                                                            <img src="{{ \AlphaDirect\Helper::getCloudFrontURL($pok->cell_phone_bottom) }}"
                                                                width="100%" height="auto">
                                                            @else
                                                            <img src="{{asset('images/doc.png')}}" width="100%"
                                                                height="auto">
                                                            @endif
                                                        </a>
                                                        @endif
                                                        <label class="kt-avatar__cancel" data-toggle="kt-tooltip"
                                                            title="Change Image">
                                                            <i class="fa fa-pen"></i>
                                                            <input type='file' class="" name="cell_phone_bottom"
                                                                <?php echo config('app.accept_attr'); ?>
                                                                <?php echo config('app.accept_msg'); ?> />
                                                        </label>
                                                        <span class="kt-avatar__cancel" data-toggle="kt-tooltip"
                                                            title="Cancel Image">
                                                            <i class="fa fa-times"></i>
                                                        </span>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                        @endif
                                    </tbody>
                                </table>
                                @if($pok->status == 0)
                                <div data-repeater-item class="kt-repeater__item">
                                    <div class="kt-portlet_body">
                                        <div class="kt-section">
                                            <div class="kt-section__Product Detailscontent"
                                                style="padding-left:20px; padding-right: 20px; padding-top: 20px;">
                                                <input type="hidden" name="device_id" value="{{ $pok->id }}">
                                                <!-- <div class="row">
                                                                    <div class="form-group col-lg-6">
                                                                        <label>Device Type <span class="red-star">*</span></label>
                                                                        <select class="form-control device_type" title="Please select Device Type" name="device_type" >
                                                                            <option  disabled="">Device Type</option>
                                                                            <option value="Cellphone" @if($pok->device_type == 'Cellphone') selected @endif>Cellphone</option>
                                                                            <option value="Laptop" @if($pok->device_type == 'Laptop') selected @endif>Laptop</option>
                                                                            <option value="Tablet" @if($pok->device_type == 'Tablet') selected @endif>Tablet</option>
                                                                        </select>
                                                                    </div>
                                                                    <div class="form-group col-lg-6">
                                                                            <label class="imei">IMEI</label>
                                                                            <input type="text" class="form-control vehiclePlate"
                                                                                name="imei"
                                                                                value="{{ $pok->imei }}">
                                                                            <p style="display:none; color:red;">
                                                                                Policy cell phone already taken for this Imei</p>
                                                                    </div>
                                                                </div>
                                                                <div class="row">
                                                                    <div id="cellPhoneMakeField" class="col-lg-6">
                                                                        <div class="form-group">
                                                                            <label class="device">Device Make : </label>
                                                                            <select class="form-control cell_phone_make" title="Please select Cellphone Make" name="cell_phone_make" >
                                                                                @foreach ($pok->brands as $brand)
                                                                                    <option @if($pok->cell_phone_make == $brand["name"]) selected @endif value='{!! $brand["id"] !!}'>{!! $brand['name'] !!}</option>
                                                                                @endforeach
                                                                            </select>
                                                                        </div>
                                                                        <div class="form-group make_other" style="display:none;">
                                                                            <label>Other Make<span class="red-star">*</span> <i class="icon-info-circle" data-container="body" data-trigger="hover" data-toggle="popover" data-placement="top" data-content="Select other make" ."="" data-original-title="" title="" aria-describedby="popover509230" style="font-size: 18px;width: 14px;color: #fe7f0c;vertical-align:bottom;height:24px;"></i>
                                                                            </label>
                                                                            <input type="text" class="form-control" name="other_make" autocomplete="off" value="" placeholder="Please enter Other Make" >
                                                                        </div>
                                                                    </div>
                                                                    <div id="cellPhoneModelField" class="col-lg-6">
                                                                        <div class="form-group">
                                                                            <label class="model">Device Model : </label>
                                                                            <select class="form-control cell_phone_model" title="Please select Cellphone Model" name="cell_phone_model" >
                                                                                @foreach ($pok->models as $model)
                                                                                    <option @if($pok->cell_phone_model == $model["name"]) selected @endif value='{!! $model["id"] !!}'>{!! $model['name'] !!}</option>
                                                                                @endforeach
                                                                            </select>
                                                                        </div>
                                                                        <div class="form-group model_other" style="display:none;">
                                                                            <label>Other Model<span class="red-star">*</span> <i class="icon-info-circle" data-container="body" data-trigger="hover" data-toggle="popover" data-placement="top" data-content="Select other make" ."="" data-original-title="" title="" aria-describedby="popover509230" style="font-size: 18px;width: 14px;color: #fe7f0c;vertical-align:bottom;height:24px;"></i></label>
                                                                            <input type="text" class="form-control" name="other_model" autocomplete="off" value="" placeholder="Please enter Other Model" >
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="row">
                                                                    <div class="form-group col-6">
                                                                        <label>Value Of The Phone <span class="red-star">*</span> <i class="icon-info-circle" data-container="body" data-trigger="hover" data-toggle="popover" data-placement="top" data-content="" data-original-title="" title="" aria-describedby="popover509230" style="font-size: 18px;width: 14px;color: #fe7f0c;vertical-align:bottom;height:24px;"></i></label>
                                                                        <input type="number" class="form-control" name="phone_value" autocomplete="off" value="{!! $pok->phone_value !!}" placeholder="Please enter Value of the phone" required>
                                                                    </div>
                                                                </div> -->
                                                <h3 class="kt-heading kt-heading&#45;&#45;md">
                                                    Cellphone Pre-inspection Photos
                                                </h3>
                                                <div class="form-group row">
                                                    <div class="col-md-2">
                                                        <h3 class="col-form-label" style="float: left;">Cell Phone Front
                                                        </h3>
                                                        <div class="kt-avatar" style="float: left; clear: left;">
                                                            @if($pok->cell_phone_front == NULL)
                                                            <div class="kt-avatar__holder"
                                                                style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)">
                                                            </div>
                                                            @else
                                                            <div class="kt-avatar__holder"
                                                                style="background-image:url('{{ \AlphaDirect\Helper::getCloudFrontURL($pok->cell_phone_front) }}');">
                                                            </div>
                                                            @endif
                                                            <label class="kt-avatar__upload" data-toggle="kt-tooltip"
                                                                title="Front">
                                                                <i class="fa fa-pen"></i>
                                                                <input type='file' class="cell_phone_front"
                                                                    name="cell_phone_front"
                                                                    <?php echo config('app.accept_attr'); ?>
                                                                    <?php echo config('app.accept_msg'); ?> />
                                                            </label>
                                                            <span class="kt-avatar__cancel" data-toggle="kt-tooltip"
                                                                title="Cancel Image"> <i class="fa fa-times"></i>
                                                            </span>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <h3 class="col-form-label" style="float: left;">Cell Phone Back
                                                        </h3>
                                                        <div class="kt-avatar" style="float: left; clear: left;">
                                                            @if($pok->cell_phone_back == NULL)
                                                            <div class="kt-avatar__holder"
                                                                style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)">
                                                            </div>
                                                            @else
                                                            <div class="kt-avatar__holder"
                                                                style="background-image:url('{{ \AlphaDirect\Helper::getCloudFrontURL($pok->cell_phone_back) }}');">
                                                            </div>
                                                            @endif
                                                            <label class="kt-avatar__upload" data-toggle="kt-tooltip"
                                                                title="Back">
                                                                <i class="fa fa-pen"></i>
                                                                <input type='file' class="cell_phone_back"
                                                                    name="cell_phone_back"
                                                                    <?php echo config('app.accept_attr'); ?>
                                                                    <?php echo config('app.accept_msg'); ?> />
                                                            </label>
                                                            <span class="kt-avatar__cancel" data-toggle="kt-tooltip"
                                                                title="Cancel Image"> <i class="fa fa-times"></i>
                                                            </span>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <h3 class="col-form-label" style="float: left;">Cell Phone Left
                                                        </h3>
                                                        <div class="kt-avatar" style="float: left; clear: left;">
                                                            @if($pok->cell_phone_left == NULL)
                                                            <div class="kt-avatar__holder"
                                                                style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)">
                                                            </div>
                                                            @else
                                                            <div class="kt-avatar__holder"
                                                                style="background-image:url('{{ \AlphaDirect\Helper::getCloudFrontURL($pok->cell_phone_left) }}');">
                                                            </div>
                                                            @endif
                                                            <label class="kt-avatar__upload" data-toggle="kt-tooltip"
                                                                title="Left">
                                                                <i class="fa fa-pen"></i>
                                                                <input type='file' class="cell_phone_left"
                                                                    name="cell_phone_left"
                                                                    <?php echo config('app.accept_attr'); ?>
                                                                    <?php echo config('app.accept_msg'); ?> />
                                                            </label>
                                                            <span class="kt-avatar__cancel" data-toggle="kt-tooltip"
                                                                title="Cancel Image"> <i class="fa fa-times"></i>
                                                            </span>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <h3 class="col-form-label" style="float: left;">Cell Phone Right
                                                        </h3>
                                                        <div class="kt-avatar" style="float: left; clear: left;">
                                                            @if($pok->cell_phone_right == NULL)
                                                            <div class="kt-avatar__holder"
                                                                style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)">
                                                            </div>
                                                            @else
                                                            <div class="kt-avatar__holder"
                                                                style="background-image:url('{{ \AlphaDirect\Helper::getCloudFrontURL($pok->cell_phone_right) }}');">
                                                            </div>
                                                            @endif
                                                            <label class="kt-avatar__upload" data-toggle="kt-tooltip"
                                                                title="Right">
                                                                <i class="fa fa-pen"></i>
                                                                <input type='file' class="cell_phone_right"
                                                                    name="cell_phone_right"
                                                                    <?php echo config('app.accept_attr'); ?>
                                                                    <?php echo config('app.accept_msg'); ?> />
                                                            </label>
                                                            <span class="kt-avatar__cancel" data-toggle="kt-tooltip"
                                                                title="Cancel Image"> <i class="fa fa-times"></i>
                                                            </span>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <h3 class="col-form-label" style="float: left;">Cell Phone Top
                                                        </h3>
                                                        <div class="kt-avatar" style="float: left; clear: left;">
                                                            @if($pok->cell_phone_top == NULL)
                                                            <div class="kt-avatar__holder"
                                                                style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)">
                                                            </div>
                                                            @else
                                                            <div class="kt-avatar__holder"
                                                                style="background-image:url('{{ \AlphaDirect\Helper::getCloudFrontURL($pok->cell_phone_top) }}');">
                                                            </div>
                                                            @endif
                                                            <label class="kt-avatar__upload" data-toggle="kt-tooltip"
                                                                title="Top">
                                                                <i class="fa fa-pen"></i>
                                                                <input type='file' class="cell_phone_top"
                                                                    name="cell_phone_top"
                                                                    <?php echo config('app.accept_attr'); ?>
                                                                    <?php echo config('app.accept_msg'); ?> />
                                                            </label>
                                                            <span class="kt-avatar__cancel" data-toggle="kt-tooltip"
                                                                title="Cancel Image"> <i class="fa fa-times"></i>
                                                            </span>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <h3 class="col-form-label" style="float: left;">Cell Phone
                                                            Bottom</h3>
                                                        <div class="kt-avatar" style="float: left; clear: left;">
                                                            @if($pok->cell_phone_bottom == NULL)
                                                            <div class="kt-avatar__holder"
                                                                style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)">
                                                            </div>
                                                            @else
                                                            <div class="kt-avatar__holder"
                                                                style="background-image:url('{{ \AlphaDirect\Helper::getCloudFrontURL($pok->cell_phone_bottom) }}');">
                                                            </div>
                                                            @endif
                                                            <label class="kt-avatar__upload" data-toggle="kt-tooltip"
                                                                title="Bottom">
                                                                <i class="fa fa-pen"></i>
                                                                <input type='file' class="cell_phone_bottom"
                                                                    name="cell_phone_bottom"
                                                                    <?php echo config('app.accept_attr'); ?>
                                                                    <?php echo config('app.accept_msg'); ?> />
                                                            </label>
                                                            <span class="kt-avatar__cancel" data-toggle="kt-tooltip"
                                                                title="Cancel Image"> <i class="fa fa-times"></i>
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>
                                @endif
                                @endforeach
                            </div>
                            @if($updateDevices == 1)
                            <div class="kt-repeater__add-data" style="padding-left:20px; padding-right: 20px;">
                                <!-- <span data-repeater-create="" class="btn btn-brand btn-sm" > <i class="la la-plus"></i>Add Another Device</span> -->
                                <button type="submit" class="btn btn-primary">Update Device Images</button>
                                <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>
                            </div>
                            @endif
                        </div>
                    </form>
                    @endif

                    @if($policy->is_bundled == 1)
                    @php $p_idddd = 0; @endphp
                    @foreach($PolicyBundled as $PolicyBundleds)

                    @if($PolicyBundleds->product_id == 3)
                    @php $p_idddd = 3; @endphp
                    @endif
                    @endforeach
                    @endif

                    @if($product->id != 6)
                        @if($policy->has_vehicle != 0 || ($policy->is_bundled == 1 && $p_idddd == 3 ))
                            @php $i = 1 ; @endphp

                            @foreach($vehicle as $vehicle)

                                <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>
                                <div class="kt-portlet__head">
                                    <div class="kt-portlet__head-label">
                                        <h3 class="kt-portlet__head-title">
                                            Vehicle Details  {{ $i }}
                                        </h3>
                                    </div>
                                </div>
                                @php $i++; @endphp

                                <div class="kt-portlet_body">
                                    <div class="kt-section">
                                        <div class="kt-section__content" style="padding-left:20px; padding-right: 20px;">
                                            <table class="table table-striped m-table">
                                                <tbody>
                                                    <tr>
                                                        <th>Vehicle Number</th>
                                                        <td>
                                                            @if($vehicle->vehiclePlate != null)
                                                            {!! strtoupper($vehicle->vehiclePlate) !!}
                                                            @else
                                                            N/A
                                                            @endif
                                                        </td>
                                                        <th>Chassis Number(VIN)</th>
                                                        <td>
                                                            {{-- @if($vehicle->chassisNo != null)
                                                            {!! $vehicle->chassisNo !!}
                                                            @else
                                                            N/A
                                                            @endif --}}

                                                            @if($vehicle->vinnumber != null)
                                                            {!! $vehicle->vinnumber !!}
                                                            @else
                                                            N/A
                                                            @endif
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <th>Odometer</th>
                                                        <td>
                                                            @if($vehicle->odometer != null)
                                                            {!! $vehicle->odometer !!}
                                                            @else
                                                            N/A
                                                            @endif
                                                        </td>
                                                        <th>Vehicle Purpose</th>
                                                        <td>
                                                            @foreach($vehicle_purpose as $purpose)
                                                            @if($purpose->id == $vehicle->purpose)
                                                            {!! $purpose->value !!}
                                                            @endif
                                                            @endforeach
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <th>Condition</th>
                                                        <td>
                                                            @if($vehicle->condition != null)
                                                            {!! $vehicle->condition !!}
                                                            @else
                                                            N/A
                                                            @endif
                                                        </td>
                                                        <th>Mileage</th>
                                                        @if($vehicle->mileage == 'LO')
                                                        <td>Low</td>
                                                        @else
                                                        <td>N/A</td>
                                                        @endif
                                                    </tr>
                                                    <tr>
                                                        <th>Make</th>
                                                        <td>
                                                            @if($vehicle->make != null)
                                                            {!! $vehicle->make !!}
                                                            @else
                                                            N/A
                                                            @endif
                                                        </td>
                                                        <th>Model</th>
                                                        <td>
                                                            @if($vehicle->model != null)
                                                            {!! $vehicle->model !!}
                                                            @else
                                                            N/A
                                                            @endif
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <th>Engine Number</th>
                                                        <td>
                                                            @if($vehicle->engineNo != null)
                                                            {!! $vehicle->engineNo !!}
                                                            @else
                                                            N/A
                                                            @endif
                                                        </td>
                                                        <th>Number of Seats</th>
                                                        <td>
                                                            @if($vehicle->seats != null)
                                                            {!! $vehicle->seats !!}
                                                            @else
                                                            N/A
                                                            @endif
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <th>Number of Cylinder</th>
                                                        <td>
                                                            @if($vehicle->cylinders != null)
                                                            {!! $vehicle->cylinders !!}
                                                            @else
                                                            N/A
                                                            @endif
                                                        </td>
                                                        <th>Cubic Capacity</th>
                                                        <td>
                                                            @if($vehicle->cubic_capacity != null)
                                                            {!! $vehicle->cubic_capacity !!}
                                                            @else
                                                            N/A
                                                            @endif
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <th>Is it imported?</th>
                                                        @if($vehicle->is_imported == 0)
                                                        <td>No</td>
                                                        @else
                                                        <td>Yes</td>
                                                        @endif
                                                        <th>Is the vehicle fitted with tracking device?</th>
                                                        @if($vehicle->is_tracking == 0)
                                                        <td>No</td>
                                                        @else
                                                        <td>Yes</td>
                                                        @endif
                                                    </tr>
                                                    <tr>
                                                        <th>Is the vehicle used for private use?</th>
                                                        @if($vehicle->is_private == 0)
                                                        <td>No</td>
                                                        @else
                                                        <td>Yes</td>
                                                        @endif
                                                        <th>Is the vehicle modified in any way?</th>
                                                        @if($vehicle->is_modified == 0)
                                                        <td>No</td>
                                                        @else
                                                        <td>Yes</td>
                                                        @endif
                                                    </tr>
                                                    <tr>
                                                        <th>Year of Manufacturing</th>
                                                        <td>
                                                            @if($vehicle->year != null)
                                                            {!! $vehicle->year !!}
                                                            @else
                                                            N/A
                                                            @endif
                                                        </td>
                                                        <th>Number of Prior Accident</th>
                                                        <td>
                                                            @if(isset($vehicle->claim_count))
                                                            {!! $vehicle->claim_count !!}
                                                            @else
                                                            N/A
                                                            @endif
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>

                                <!-- Vehicle Image  -->
                                <div class="kt-portlet__head">
                                    <div class="kt-portlet__head-label">
                                        <h3 class="kt-portlet__head-title">
                                            Vehicle Images
                                        </h3>
                                    </div>
                                </div>
                                <form action="{{route('admin.policy.uploadVehicleImages',$policy->id)}}" method="post"
                                    enctype="multipart/form-data" class="kt-form">
                                    @csrf
                                    <div class="kt-portlet_body">
                                        <div class="kt-section">
                                            <div class="kt-section__content" style="padding-left:20px; padding-right: 20px;">
                                                <div class="form-group row">
                                                    <div class="col-md-2">
                                                        <h3 class="col-form-label" style="float: left;">Left</h3>
                                                        <div class="kt-avatar" id="left" style="float: left; clear: left;">
                                                            @if($vehicle->left == NULL)
                                                            <div class="kt-avatar__holder"
                                                                style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)">
                                                            </div>
                                                            @else
                                                            <a href="{{ \AlphaDirect\Helper::getCloudFrontURL($vehicle->left) }}">
                                                                <div class="kt-avatar__holder"
                                                                    style="background-image:url('{!! \AlphaDirect\Helper::getCloudFrontURL($vehicle->left) !!}')">
                                                                </div>
                                                            </a>
                                                            @endif
                                                            @if($vehicle->compliance != 1)
                                                            <label class="kt-avatar__upload" data-toggle="kt-tooltip" title="Left">
                                                                <i class="fa fa-pen"></i>
                                                                <input type='file' class="left" name="left"
                                                                    <?php echo config('app.accept_attr'); ?>
                                                                    <?php echo config('app.accept_msg'); ?> />
                                                            </label>
                                                            @endif
                                                            <span class="kt-avatar__cancel" data-toggle="kt-tooltip"
                                                                title="Cancel Image"> <i class="fa fa-times"></i>
                                                            </span>
                                                        </div>
                                                    </div>


                                                    <div class="col-md-2">
                                                        <h3 class="col-form-label" style="float: left;">Right</h3>
                                                        <div class="kt-avatar" id="right" style="float: left; clear: left;">
                                                            @if($vehicle->right == NULL)
                                                            <div class="kt-avatar__holder"
                                                                style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)">
                                                            </div>
                                                            @else
                                                            <a href="{{ \AlphaDirect\Helper::getCloudFrontURL($vehicle->right) }}">
                                                                <div class="kt-avatar__holder"
                                                                    style="background-image:url('{!! \AlphaDirect\Helper::getCloudFrontURL($vehicle->right) !!}')">
                                                                </div>
                                                            </a>
                                                            @endif
                                                            @if($vehicle->compliance != 1)
                                                            <label class="kt-avatar__upload" data-toggle="kt-tooltip" title="Right">
                                                                <i class="fa fa-pen"></i>
                                                                <input type='file' class="right" name="right"
                                                                    <?php echo config('app.accept_attr'); ?>
                                                                    <?php echo config('app.accept_msg'); ?> />
                                                            </label>
                                                            @endif
                                                            <span class="kt-avatar__cancel" data-toggle="kt-tooltip"
                                                                title="Cancel Image"> <i class="fa fa-times"></i>
                                                            </span>
                                                        </div>
                                                    </div>

                                                    <div class="col-md-2">
                                                        <h3 class="col-form-label" style="float: left;">Back</h3>
                                                        <div class="kt-avatar" id="back" style="float: left; clear: left;">
                                                            @if($vehicle->back == NULL)
                                                            <div class="kt-avatar__holder"
                                                                style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)">
                                                            </div>
                                                            @else
                                                            <a href="{{ \AlphaDirect\Helper::getCloudFrontURL($vehicle->back) }}">
                                                                <div class="kt-avatar__holder"
                                                                    style="background-image:url('{!! \AlphaDirect\Helper::getCloudFrontURL($vehicle->back) !!}')">
                                                                </div>
                                                            </a>
                                                            @endif
                                                            @if($vehicle->compliance != 1)
                                                            <label class="kt-avatar__upload" data-toggle="kt-tooltip" title="Back">
                                                                <i class="fa fa-pen"></i>
                                                                <input type='file' class="back" name="back"
                                                                    <?php echo config('app.accept_attr'); ?>
                                                                    <?php echo config('app.accept_msg'); ?> />
                                                            </label>
                                                            @endif
                                                            <span class="kt-avatar__cancel" data-toggle="kt-tooltip"
                                                                title="Cancel Image"> <i class="fa fa-times"></i>
                                                            </span>
                                                        </div>
                                                    </div>

                                                    <div class="col-md-2">
                                                        <h3 class="col-form-label" style="float: left;">Front</h3>

                                                        <div class="kt-avatar" id="front" style="float: left; clear: left;">
                                                            @if($vehicle->front == NULL)
                                                            <div class="kt-avatar__holder"
                                                                style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)">
                                                            </div>
                                                            @else
                                                            <a href="{{ \AlphaDirect\Helper::getCloudFrontURL($vehicle->front) }}">
                                                                <div class="kt-avatar__holder"
                                                                    style="background-image:url('{!! \AlphaDirect\Helper::getCloudFrontURL($vehicle->front) !!}')">
                                                                </div>
                                                            </a>
                                                            @endif
                                                            @if($vehicle->compliance != 1)
                                                            <label class="kt-avatar__upload" data-toggle="kt-tooltip" title="Front">
                                                                <i class="fa fa-pen"></i>
                                                                <input type='file' class="front" name="front"
                                                                    <?php echo config('app.accept_attr'); ?>
                                                                    <?php echo config('app.accept_msg'); ?> />
                                                            </label>
                                                            @endif
                                                            <span class="kt-avatar__cancel" data-toggle="kt-tooltip"
                                                                title="Cancel Image"> <i class="fa fa-times"></i>
                                                            </span>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <h3 class="col-form-label" style="float: left;">Vehicle
                                                            Registration</h3>
                                                        <div class="kt-avatar" id="vehicleRegistration"
                                                            style="float: left; clear: left;">
                                                            @if($vehicle->vehicleRegistration == NULL)
                                                            <div class="kt-avatar__holder"
                                                                style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)">
                                                            </div>
                                                            @else
                                                            <a
                                                                href="{{ \AlphaDirect\Helper::getCloudFrontURL($vehicle->vehicleRegistration) }}">
                                                                <div class="kt-avatar__holder"
                                                                    style="background-image:url('{!! \AlphaDirect\Helper::getCloudFrontURL($vehicle->vehicleRegistration) !!}')">

                                                                    <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($vehicle->vehicleRegistration) !!}"
                                                                        target="_blank" download>
                                                                        @if(pathinfo($vehicle->vehicleRegistration,
                                                                        PATHINFO_EXTENSION) == 'pdf')
                                                                        <img src="{{asset('images/pdf.ico')}}" width="100%"
                                                                            height="auto">
                                                                        @elseif(pathinfo($vehicle->vehicleRegistration,
                                                                        PATHINFO_EXTENSION) == 'docx' ||
                                                                        pathinfo($vehicle->vehicleRegistration, PATHINFO_EXTENSION)
                                                                        == 'doc' || pathinfo($vehicle->vehicleRegistration,
                                                                        PATHINFO_EXTENSION) == 'docm')
                                                                        <img src="{{asset('images/word.ico')}}" width="100%"
                                                                            height="auto">
                                                                        @elseif(pathinfo($vehicle->vehicleRegistration,
                                                                        PATHINFO_EXTENSION) == 'xls' ||
                                                                        pathinfo($vehicle->vehicleRegistration, PATHINFO_EXTENSION)
                                                                        == 'xlsx' || pathinfo($vehicle->vehicleRegistration,
                                                                        PATHINFO_EXTENSION) == 'csv')
                                                                        <img src="{{asset('images/excel.png')}}" width="100%"
                                                                            height="auto">
                                                                        @elseif(pathinfo($vehicle->vehicleRegistration,
                                                                        PATHINFO_EXTENSION) == 'jpeg' ||
                                                                        pathinfo($vehicle->vehicleRegistration, PATHINFO_EXTENSION)
                                                                        == 'jpg' || pathinfo($vehicle->vehicleRegistration,
                                                                        PATHINFO_EXTENSION) == 'png')
                                                                        <img src="{{\AlphaDirect\Helper::getCloudFrontURL($vehicle->vehicleRegistration)}}"
                                                                            width="100%" height="auto">
                                                                        @else
                                                                        <img src="{{asset('images/doc.png')}}" width="100%"
                                                                            height="auto">
                                                                        @endif
                                                                    </a>
                                                                </div>
                                                            </a>
                                                            @endif
                                                            @if($vehicle->compliance != 1)
                                                            <label class="kt-avatar__upload" data-toggle="kt-tooltip"
                                                                title="Change Image">
                                                                <i class="fa fa-pen"></i>
                                                                <input type='file' class="vehicleRegistration"
                                                                    <?php echo config('app.accept_attr'); ?>
                                                                    <?php echo config('app.accept_msg'); ?>
                                                                    name="vehicleRegistration" />
                                                            </label>
                                                            @endif
                                                            <span class="kt-avatar__cancel" data-toggle="kt-tooltip"
                                                                title="Cancel Image"> <i class="fa fa-times"></i>
                                                            </span>
                                                        </div>

                                                    </div>
                                                    <div class="col-md-2">
                                                        <h3 class="col-form-label" style="float: left;">Vehicle Invoice</h3>
                                                        <div class="kt-avatar" id="vehicle_valuation"
                                                            style="float: left; clear: left;">
                                                            @if($vehicle->vehicle_valuation == NULL)
                                                            <div class="kt-avatar__holder"
                                                                style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)">
                                                            </div>
                                                            @else
                                                            <a
                                                                href="{{ \AlphaDirect\Helper::getCloudFrontURL($vehicle->vehicle_valuation) }}">
                                                                <div class="kt-avatar__holder"
                                                                    style="background-image:url('{!! \AlphaDirect\Helper::getCloudFrontURL($vehicle->vehicle_valuation) !!}')">

                                                                    <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($vehicle->vehicle_valuation) !!}"
                                                                        target="_blank" download>
                                                                        @if(pathinfo($vehicle->vehicle_valuation,
                                                                        PATHINFO_EXTENSION) == 'pdf')
                                                                        <img src="{{asset('images/pdf.ico')}}" width="100%"
                                                                            height="auto">
                                                                        @elseif(pathinfo($vehicle->vehicle_valuation,
                                                                        PATHINFO_EXTENSION) == 'docx' ||
                                                                        pathinfo($vehicle->vehicle_valuation, PATHINFO_EXTENSION)
                                                                        == 'doc' || pathinfo($vehicle->vehicle_valuation,
                                                                        PATHINFO_EXTENSION) == 'docm')
                                                                        <img src="{{asset('images/word.ico')}}" width="100%"
                                                                            height="auto">
                                                                        @elseif(pathinfo($vehicle->vehicle_valuation,
                                                                        PATHINFO_EXTENSION) == 'xls' ||
                                                                        pathinfo($vehicle->vehicle_valuation, PATHINFO_EXTENSION)
                                                                        == 'xlsx' || pathinfo($vehicle->vehicle_valuation,
                                                                        PATHINFO_EXTENSION) == 'csv')
                                                                        <img src="{{asset('images/excel.png')}}" width="100%"
                                                                            height="auto">
                                                                        @elseif(pathinfo($vehicle->vehicle_valuation,
                                                                        PATHINFO_EXTENSION) == 'jpeg' ||
                                                                        pathinfo($vehicle->vehicle_valuation, PATHINFO_EXTENSION)
                                                                        == 'jpg' || pathinfo($vehicle->vehicle_valuation,
                                                                        PATHINFO_EXTENSION) == 'png')
                                                                        <img src="{{\AlphaDirect\Helper::getCloudFrontURL($vehicle->vehicle_valuation)}}"
                                                                            width="100%" height="auto">
                                                                        @else
                                                                        <img src="{{asset('images/doc.png')}}" width="100%"
                                                                            height="auto">
                                                                        @endif
                                                                    </a>
                                                                </div>
                                                            </a>
                                                            @endif
                                                            @if($vehicle->compliance != 1)
                                                            <label class="kt-avatar__upload" data-toggle="kt-tooltip"
                                                                title="Change Image">
                                                                <i class="fa fa-pen"></i>
                                                                <input type='file' class="vehicle_valuation"
                                                                    <?php echo config('app.accept_attr'); ?>
                                                                    <?php echo config('app.accept_msg'); ?>
                                                                    name="vehicle_valuation" />
                                                            </label>
                                                            @endif
                                                            <span class="kt-avatar__cancel" data-toggle="kt-tooltip"
                                                                title="Cancel Image"> <i class="fa fa-times"></i>
                                                            </span>
                                                        </div>

                                                    </div>
                                                </div>
                                                @if($vehicle->compliance != 1)
                                                <button type="submit" class="btn btn-primary">Upload Vehicle Images</button>
                                                @endif
                                                @if(isset( $vehicle->id))
                                                <button class="sendemail btn btn-brand" id="sendemail" value="{{ $vehicle->id }}" >Send SMS and Email</button>
                                            @endif
                                            </div>
                                        </div>
                                    </div>
                                </form>
                                @if($policy->has_vehicle != 0)
                                <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>
                                <div class="kt-portlet__head">
                                    <div class="kt-portlet__head-label">
                                        <h3 class="kt-portlet__head-title">
                                            Coverages
                                        </h3>
                                    </div>
                                </div>

                                <div class="col-lg-8">
                                    <div class="form-group">
                                        <label>Terms</label>
                                        <select class="form-control required" name="selectTerm" id="selectedTerm">
                                            @foreach ($policy_term as $Terms)
                                                <option value="{{ $Terms->id }}">
                                                    {{-- {{ $Terms->profile && $Terms->profile->state == $state->id ? 'selected' : '' }}> --}}
                                                    {!! Carbon::parse(str_replace("/", "-", $Terms->term_start_date))->format('d-m-Y') !!}  To {!! Carbon::parse(str_replace("/", "-", $Terms->term_end_date))->format('d-m-Y') !!} </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <div class="kt-portlet_body">
                                    <div class="kt-section">
                                        <div class="kt-section__content" style="padding-left:20px; padding-right: 20px;">



                                        </div>
                                    </div>
                                </div>
                                @endif
                                <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>
                            @endforeach
                        @endif
                    @endif

                    @if($policy->is_motor_items != 0)
                    <div class="kt-portlet__head">
                        <div class="kt-portlet__head-label">
                            <h3 class="kt-portlet__head-title">
                                Specified Motor Items
                            </h3>
                        </div>
                    </div>
                    <div class="kt-portlet_body">
                        <div class="kt-section">
                            <div class="kt-section__content" style="padding-left:20px; padding-right: 20px;">
                                <table class="table table-striped m-table">
                                    <tbody>
                                        <th>Description Of Items</th>
                                        <th>Sum Insured</th>
                                        @if($count != 0)
                                        @foreach($policyMotorItems as $policyMotorItem)
                                        @foreach($motor_items as $motor_item)
                                        @if( $policyMotorItem->item_name == $motor_item->n_PRAppsSuppPersprop_PK)
                                        <tr>
                                            <td>{{ $motor_item->s_PersPropScreenName }}</td>
                                            <td>{{ $policyMotorItem->item_value }}</td>
                                        </tr>
                                        @endif
                                        @endforeach
                                        @endforeach
                                        @endif
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    @endif
                    <div class="kt-portlet__head">
                        <div class="kt-portlet__head-label">
                            <h3 class="kt-portlet__head-title">
                                Notes
                            </h3>
                        </div>
                    </div>
                    <div class="kt-portlet_body">
                        <div class="kt-section">
                            <div class="kt-section__content" style="padding-left:20px; padding-right: 20px;">

                                <form action="{{route('admin.policy.updatePolicyNotes',$policy->id)}}" method="post"
                                    enctype="multipart/form-data" class="kt-form">
                                    @csrf
                                    <div class="form-group row">
                                        <div class="col-lg-12">
                                            <div class="form-group">
                                                <textarea class="form-control" name="note"
                                                    rows="3">{!! $policy->note !!}</textarea>
                                            </div>
                                        </div>
                                        <button type="submit" class="btn btn-primary">Update Notes</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    {{--                                @if($policy->note != NULL)--}}
                    {{--                                    <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>--}}

                    {{--                                    <div class="kt-portlet__head">--}}
                    {{--                                        <div class="kt-portlet__head-label">--}}
                    {{--                                            <h3 class="kt-portlet__head-title">--}}
                    {{--                                                Note--}}
                    {{--                                            </h3>--}}
                    {{--                                        </div>--}}
                    {{--                                    </div>--}}
                    {{--                                    <div class="kt-portlet_body">--}}
                    {{--                                        <div class="kt-section">--}}
                    {{--                                            <div class="kt-section__content" style="padding-left:20px; padding-right: 20px;">--}}
                    {{--                                                <table class="table ">--}}
                    {{--                                                    <tbody>--}}
                    {{--                                                    <tr>--}}
                    {{--                                                        <td>{!! $policy->note !!} </td>--}}
                    {{--                                                    </tr>--}}
                    {{--                                                    </tbody>--}}
                    {{--                                                </table>--}}
                    {{--                                            </div>--}}
                    {{--                                        </div>--}}
                    {{--                                    </div>--}}
                    {{--                                @endif--}}
                    @if($policy->has_subApplicant != 0)
                    <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>
                    <div class="kt-portlet__head">
                        <div class="kt-portlet__head-label">
                            <h3 class="kt-portlet__head-title">
                                Sub Applicant Details
                            </h3>
                        </div>
                    </div>
                    <div class="kt-portlet_body">
                        <div class="kt-section">
                            <div class="kt-section__content" style="padding-left:20px; padding-right: 20px;">
                                <table class="table table-striped m-table">
                                    <tbody>
                                        @foreach($members as $key => $member)
                                        <tr style="border-top: solid 2px #666;">
                                            <th>Relation</th>
                                            <td>{!! $member->relation !!}</td>
                                            <th>Name</th>
                                            <td>{!! ucwords($member->first_name) !!} {!! ucwords($member->last_name) !!}
                                            </td>
                                        </tr>
                                        <tr style="border-bottom: solid 2px #666;">
                                            <th>Date of Birth</th>
                                            @if($member->dob != null)
                                                <td>{{--{!! \Carbon\Carbon::parse($member->dob)->format('d-m-y')  !!}--}}
                                                    {{ \Carbon\Carbon::createFromFormat('Y-m-d', $member->dob)->format('d-m-Y') }}
                                                </td>
                                            @endif
                                            <th>Gender</th>
                                            @if($member->gender == 0)
                                            <td>Female </td>
                                            @else
                                            <td>Male</td>
                                            @endif
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    @endif
                    @if($policy->is_bundled == 1)
                    @php $p_id4 = 0; @endphp
                    @php $p_id1 = 0; @endphp
                    @foreach($PolicyBundled as $PolicyBundleds)

                    @if($PolicyBundleds->product_id == 4)
                    @php $p_id4 = 4; @endphp
                    @endif
                    @if($PolicyBundleds->product_id == 1)
                    @php $p_id1 = 1; @endphp
                    @endif

                    @endforeach
                    @endif
                    @if($policy->has_member != 0 || ($policy->is_bundled == 1 && ($p_id1 == 1 || $p_id4 == 4) ))
                    <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>
                    <div class="kt-portlet__head">
                        <div class="kt-portlet__head-label">
                            <h3 class="kt-portlet__head-title">
                                Beneficiary Details
                            </h3>
                        </div>
                    </div>
                    <div class="kt-portlet_body">
                        <div class="kt-section">
                            <div class="kt-section__content" style="padding-left:20px; padding-right: 20px;">
                                <table class="table table-striped m-table">
                                    <tbody>
                                    @php   $benefi = $beneficiaries->where('relation','!=','Spouse'); @endphp
                                               @if($benefi != null)
                                        @foreach($benefi as $key => $beneficiary)
                                        <tr style="border-top: solid 2px #666;">
                                            <th>Relation with beneficiary</th>
                                            <td>{!! $beneficiary->relation !!}</td>
                                            <th>Name</th>
                                            <td>{!! ucwords($beneficiary->first_name) !!}
                                                @if($beneficiary->middle_name) {!! ucwords($beneficiary->middle_name)
                                                !!} @endif
                                                {!! ucwords($beneficiary->last_name) !!}</td>
                                        </tr>
                                        <tr>
                                            <th>Date of Birth</th>
                                            <td>{!! $beneficiary->dob !!}</td>

                                            <th>Gender</th>
                                            @if($beneficiary->gender == 0)
                                            <td>Female </td>
                                            @else
                                            <td>Male</td>
                                            @endif
                                        </tr>
                                        <tr>
                                            <th>Available Identity Type</th>
                                            <td>
                                                @if($beneficiary->omang != null)
                                                Omang
                                                @elseif($beneficiary->passport != null)
                                                Passport
                                                @endIf
                                            </td>
                                            <th>Number</th>
                                            <td>
                                                @if($beneficiary->omang != null)
                                                {{ $beneficiary->omang }}
                                                @elseif($beneficiary->passport != null)
                                                {{ $beneficiary->passport }}
                                                @endIf
                                            </td>
                                        </tr>
                                        <tr style="border-bottom: solid 2px #666;">
                                            <th>Payment</th>
                                            <td>{!! $beneficiary->payment !!} %</td>
                                            @php
                                            $from = new DateTime($beneficiary->dob);
                                            $to   = new DateTime('today');
                                            $ageGold =  $from->diff($to)->y;
                                            @endphp
                                            @if($policy->product_id == 1 && $policy->plan_id == 13 && $ageGold < 18)
                                                    @if (isset($beneficiary->under_18_is_allowed) && $beneficiary->under_18_is_allowed == 1)
                                                    <th>Cover This Beneficiary For ADI Coverage</th>
                                                    <td>Yes</td>
                                                    @else
                                                    <th>Cover This Beneficiary For ADI Coverage</th>
                                                    <td>No</td>
                                                    @endif
                                            @else
                                            <th>&nbsp</th>
                                            <td></td>
                                            @endif
                                        </tr>

                                        @endforeach
                                        @endif
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    @endif
                    @if($policy->is_bundled == 1)
                    @php $p_id44 = 0; @endphp
                    @foreach($PolicyBundled as $PolicyBundleds)

                    @if($PolicyBundleds->product_id == 4)
                    @php $p_id44 = 4; @endphp
                    @endif


                    @endforeach
                    @endif
                    @if($policy->product_id == 4 || ($policy->is_bundled == 1 && $p_id44 == 4 ))
                            <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed">
                            </div>
                            @if(count($beneficiaries) > 0)
                            <div class="kt-portlet__head">
                                <div class="kt-portlet__head-label">
                                    <h3 class="kt-portlet__head-title">
                                        Spouse / Life Partner Details
                                    </h3>
                                </div>
                            </div>
                            <div class="kt-portlet_body">
                                <div class="kt-section">
                                    <div class="kt-section__content"
                                        style="padding-left:20px; padding-right: 20px;">
                                        <table class="table table-striped m-table">
                                            <tbody>
                                                @foreach ($beneficiaries as $key => $beneficiarys)
                                                    @if($beneficiarys->relation == 'Spouse')
                                                        <tr style="border-top: solid 2px #666;">

                                                            <th>Name</th>
                                                            <td>{!! ucwords($beneficiarys->first_name) !!}
                                                                @if ($beneficiarys->middle_name) {!! ucwords($beneficiarys->middle_name) !!} @endif
                                                                {!! ucwords($beneficiarys->last_name) !!}
                                                            </td>

                                                            <th>Date of Birth</th>
                                                            @if (isset($beneficiarys->dob) && $beneficiarys->dob != null || $beneficiarys->dob != '')
                                                            <td>{!! Carbon::parse(str_replace("/", "-", $beneficiarys->dob))->format('d-m-Y') !!}</td>
                                                            {{-- <td>{!! $beneficiarys->dob !!}</td> --}}
                                                            @else
                                                                <td>N/A</td>
                                                            @endif
                                                        </tr>
                                                        <tr>
                                                            <th>Omang</th>
                                                            @if (isset($beneficiarys->omang) && $beneficiarys->omang != null || $beneficiarys->omang != '')
                                                                <td> {{ $beneficiarys->omang }}</td>
                                                            @else
                                                                <td>N/A</td>
                                                            @endif

                                                            <th>Gender</th>
                                                            @if ($beneficiarys && $beneficiarys->gender != null || $beneficiarys->gender != '')
                                                                @if ($beneficiarys->gender == 0)
                                                                <td>Female </td>
                                                                @else
                                                                    <td>Male</td>
                                                                @endif
                                                            @else
                                                            <td>N/A</td>
                                                            @endif
                                                        </tr>
                                                        <tr>
                                                            <th>Passport</th>
                                                            @if ($beneficiarys && $beneficiarys->passport != null || $beneficiarys->passport != '')
                                                                <td>{{ $beneficiarys->passport }}</td>
                                                            @else
                                                                <td>N/A</td>
                                                            @endif

                                                            <th>Email</th>
                                                            @if ($beneficiarys && $beneficiarys->email != null || $beneficiarys->email != '')
                                                                <td>{{ $beneficiarys->email }}</td>
                                                            @else
                                                                <td>N/A</td>
                                                            @endif
                                                        </tr>

                                                        <tr>
                                                            <th>Mobile Number</th>
                                                            @if ($beneficiarys && $beneficiarys->cellphone != null || $beneficiarys->cellphone != '')
                                                                <td>{{ $beneficiarys->cellphone }}</td>
                                                            @else
                                                                <td>N/A</td>
                                                            @endif
                                                        </tr>
                                                        <tr>
                                                            <th>Omang Expiry </th>
                                                            @if ($beneficiarys && $beneficiarys->legalOmangExpiry != null && $beneficiarys->legalOmangExpiry != 'null' && $beneficiarys->legalOmangExpiry !='')
                                                                <td> {!! Carbon::parse(str_replace("/", "-", $beneficiarys->legalOmangExpiry))->format('d-m-Y') !!}</td>
                                                            @else
                                                                <td>N/A</td>
                                                            @endif
                                                        </tr>
                                                        <tr>
                                                            <th>Passport Expiry</th>
                                                            @if ($beneficiarys && $beneficiarys->legalPassportExpiry != null && $beneficiarys->legalPassportExpiry != 'null' && $beneficiarys->legalPassportExpiry !='')
                                                                <td> {!! Carbon::parse(str_replace("/", "-", $beneficiarys->legalPassportExpiry))->format('d-m-Y') !!}</td>
                                                            @else
                                                                <td>N/A</td>
                                                            @endif
                                                        </tr>
                                                    @endif
                                                @endforeach

                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            @endif
                    @endif

                    <div class="kt-portlet__foot">
                        <div class="row">
                            <div class="col-12">
                                <a class="btn btn-secondary" href="{{ route('admin.policy.index') }}">Cancel</a>
                                {{-- @if($product->id == 3 && $policy->status == 1 && !isset($policyRenewalButton) && isset($activatedDiffDays))
                                    @if($activatedDiffDays >= 90) --}}
                                    @if(auth::user()->hasPermissionTo('policy-move-to-renew'))
                                        @if ($policy->product_id == 3)
                                            <a href="{{--{{ route('admin.policy.renew_policy') }} --}}#" id="MoveToRenewFormBottom" class="btn btn-success">Move to Renew Table</a>
                                        @endif
                                    @endif
                                    {{-- @endif

                                @endif --}}

                            </div>
                        </div>
                    </div>
                </div>

            </div>
            <div class="tab-pane" id="kt_portlet_base_demo_3_2_transactions_content" role="tabpanel">
                <div class="kt-portlet__head">
                    <div class="kt-portlet__head-label">
                        <h3 class="kt-portlet__head-title">
                            Total Dues : @if($balance <= 0) P {!! abs($balance) !!} @else - P{!! $balance !!} @endif
                        </h3>
                        <a class="btn btn-primary" id="add_invoice" style="color:#fff;margin-left: 710px;">Add Invoice</a>
                        <a class="btn btn-primary" style="margin-left: 50px" href="{{ route('admin.documents.accountStatement',$policy->id) }}">Account Statement</a>
                    </div>
                </div>
                <div class="kt-portlet__body" style="padding-top:0px !important">
                    <!--begin: Datatable -->
                    <!--begin: Datatable -->
                    <ul class="nav nav-pills nav-tabs-btn nav-pills-btn-info" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" data-toggle="tab" href="#kt_tabs_9_1" role="tab">
                                <span class="nav-link-icon"><i class="flaticon-clipboard"></i></span>
                                <span class="nav-link-title">Account View</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="tab" href="#kt_tabs_9_2" role="tab">
                                <span class="nav-link-icon"><i class="flaticon-globe"></i></span>
                                <span class="nav-link-title">Recievable View</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="tab" href="#kt_tabs_9_3" role="tab">
                                <span class="nav-link-icon"><i class="flaticon-layers"></i></span>
                                <span class="nav-link-title">Invoicing </span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="tab" href="#kt_tabs_9_4" role="tab">
                                <span class="nav-link-icon"><i class="flaticon-layers"></i></span>
                                <span class="nav-link-title">Sub Ledger </span>
                            </a>
                        </li>
                    </ul>
                    <div class="tab-content">
                        {{--Tab content 1--}}
                        <div class="tab-pane fade active show" id="kt_tabs_9_1" role="tabpanel">
                            <table class="table table-striped table-bordered table-hover table-checkable"
                                id="account_table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>ACCOUNTING DT.</th>
                                        <th>TRANS TYPE</th>
                                        <th>TRANS REF</th>
                                        <th>CUSTOMER NAME</th>
                                        <th>ORIG TRANS</th>
                                        <th>UNALLOCATED</th>
                                        <th>DEBIT</th>
                                        <th>CREDIT</th>
                                        <th>BALANCE</th>
                                        <th>SYSTEM DT.</th>
                                    </tr>
                                </thead>
                            </table>
                        </div>
                        {{--Tab content 2--}}
                        <div class="tab-pane fade" id="kt_tabs_9_2" role="tabpanel">
                            <table class="table table-striped table-bordered table-hover table-checkable"
                                id="recievable_table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>ACCOUNTING DATE</th>
                                        <th>TRANS TYPE</th>
                                        <th>TRANS-SUB-TYPE</th>
                                        <th>TRANS REF</th>
                                        <th>EFF DATE</th>
                                        <th>DEBIT</th>
                                        <th>CREDIT</th>
                                        <th>BALANCE</th>
                                    </tr>
                                </thead>
                            </table>
                        </div>
                        {{--Tab content 3--}}
                        <div class="tab-pane fade" id="kt_tabs_9_3" role="tabpanel">
                            <table class="table table-striped table-bordered table-hover table-checkable"
                                id="invoicing_table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>INVOICE DT.</th>
                                        <th>INVOICE NO.</th>
                                        <th>PREMIUM</th>
                                        <th>OTHER CHARGES</th>
                                        <th>DUE AMOUNT</th>
                                        <th>BALANCE</th>
                                        <th>PMTS/ADJUST</th>
                                        <th>INVOICE AMT.</th>
                                        <th>DUE DATE</th>
                                        <th>STATUS</th>
                                       <th>ACTION</th>
                                    </tr>
                                </thead>
                            </table>
                        </div>
                        <div class="tab-pane fade" id="kt_tabs_9_4" role="tabpanel">
                            <table class="table table-striped table-bordered table-hover table-checkable"
                                id="sub_ledger_table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>SYSTEM DATE</th>
                                        <th>TRANS TYPE</th>
                                        <th>TRANS REF</th>
                                        <th>ACCOUNT NAME</th>
                                        <th>DEBIT</th>
                                        <th>CREDIT</th>
                                    </tr>
                                </thead>
                            </table>
                        </div>
                    </div>
                    <!--end: Datatable -->
                </div>
                <div class="kt-portlet__foot">
                    <div class="row">
                        <div class="col-12">
                            <a class="btn btn-secondary" href="{{ route('admin.policy.index') }}">Cancel</a>
                        </div>
                    </div>
                </div>
            </div>
             <!-------------------------earn start------------------------------------------------>
        <div class="tab-pane" id="kt_portlet_base_demo_3_2_earned_premiums" role="tabpanel">
        <div class="kt-portlet__head">
            <div class="kt-portlet__head-label">


            </div>
        </div>
        <div class="kt-portlet_body">
                            <div class="kt-section">
                                <div class="kt-section__content"
                                    style="padding-left:20px; padding-right: 20px;">
                                    <table class="table table-striped m-table">
                                        <tbody>

                                                <tr style="border-top: solid 2px #666;">
                                                    <th>Policy #/Holder Name</th>

                                                    @if($user)
                                                            @if($user->firstName || $user->middleName ||
                                                            $user->lastName)
                                                            <td>{{$policy->policyNumber}} #/{!! ucwords($user->firstName).'
                                                                '.ucwords($user->middleName).'
                                                                '.ucwords($user->lastName) !!}</td>
                                                            @else
                                                            <td>Name not found</td>
                                                            @endif
                                                    @else
                                                    <td>User not found</td>
                                                    @endif





                                                    <th>Renewal Plan</th>
                                                    <td>--</td>
                                                </tr>
                                                <tr>
                                                    <th>Term Start Date</th>

                                                    <td>@if(isset($policy->term_start_date)){{ Carbon::parse(str_replace("/", "-", $policy->term_start_date))->format('d-m-Y')}}@else N/A @endif</td>
                                                    <th>Term End Date</th>
                                                    <td>@if(isset($policy->term_end_date)){{ Carbon::parse(str_replace("/", "-", $policy->term_end_date))->format('d-m-Y')}}@else N/A @endif</td>
                                                </tr>
                                                <tr>
                                                   <th>Transaction Eff. Dt.</th>
                                                   <td>@if(isset($PolicyLedgers->eff_date)){{ Carbon::parse(str_replace("/", "-", $PolicyLedgers->eff_date))->format('d-m-Y')}}@else N/A @endif</td>
                                                   <th>Transaction Exp. Dt.</th>
                                                   <td>--</td>
                                                </tr>
                                                <tr>
                                                   <th>Transaction Type</th>
                                                   <td>@if(isset($transaction->paymentMethod)){{ $transaction->paymentMethod }}@else N/A @endif</td>

                                                   <th>Bill To</th>
                                                   <td>@if(isset($transaction->paymentMethod)){{ $transaction->paymentMethod }}@else N/A @endif</td>
                                                </tr>
                                                <tr>
                                                   <th>Last Updated Date</th>
                                                   <td>@if(isset($policy->updated_at)){{ Carbon::parse(str_replace("/", "-", $policy->updated_at))->format('d-m-Y')}}@else N/A @endif</td>
                                                   <th>Last Updated By</th>
                                                   <td>@if(isset($transaction->paymentMethod)){{ $transaction->paymentMethod }}@else N/A @endif</td>
                                                </tr>
                                                <tr>
                                                   <th>Premium</th>
                                                   <td>@if(isset($policy->premium)){{ $policy->premium }}@else N/A @endif</td>

                                                   <th>Premim Change</th>
                                                   <td>--</td>
                                                </tr>
                                                <tr>
                                                   <th>Total Premium</th>
                                                   <td>@if(isset($policy->premium)){{ $policy->premium }}@else N/A @endif</td>
                                                   <th>Renew Indicator</th>
                                                   <td>--</td>
                                                </tr>
                                                <tr>
                                                   <th>Total Claim</th>
                                                   <td>--</td>
                                                   <th>Inception Date</th>
                                                   <td>@if(isset($policy->policyActivatedDate)){{ Carbon::parse(str_replace("/", "-", $policy->policyActivatedDate))->format('d-m-Y')}}@else N/A @endif</td>
                                                </tr>
                                                <tr>
                                                   <th>Serv Rep</th>
                                                   <td>--</td>
                                                   <th>U/writer</th>
                                                   <td>--</td>
                                                </tr>
                                                <tr>
                                                   <th>Transaction Note</th>
                                                   <td>@if(isset($transaction->note)){{  $transaction->note }}@else N/A @endif</td>

                                                </tr>

                                        </tbody>
                                    </table>
                                </div>
                            </div>
        </div>
        <div class="kt-portlet__body" style="padding-top:0px !important">
            <!--begin: Datatable -->

            <div class="tab-content">

                <div class="tab-pane fade active show" id="kt_tabs_9_9" role="tabpanel">
                    <table class="table table-striped table-bordered table-hover table-checkable" id="earn_table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Trans Type.</th>
                                <th>Days</th>
                                <th>Written Preminum</th>
                                <th>Day Premium</th>



                            </tr>
                        </thead>
                    </table>
                </div>



            </div>
            <!--end: Datatable -->
        </div>
    </div>
    <!------------------------earn end-------------------------------------------------->
            <div class="tab-pane" id="kt_portlet_base_demo_3_3_claims_content" role="tabpanel">
                <br>

                <div class="kt-portlet__body">
                    <!--begin: Datatable -->
                    <table class="table table-striped table-bordered table-hover table-checkable" id="claims_table">
                        <thead>
                            <tr>
                                <th>Claim Number</th>
                                <th>Claim Type</th>
                                <th>Status</th>
                                <th>Sub-Status</th>
                            </tr>
                        </thead>
                    </table>
                    <!--end: Datatable -->
                </div>
                <div class="kt-portlet__foot">
                    <div class="row">
                        <div class="col-12">
                            <a class="btn btn-secondary" href="{{ route('admin.policy.index') }}">Cancel</a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="tab-pane" id="kt_portlet_base_demo_3_5_mati_verification_content" role="tabpanel">
                <div class="kt-portlet__head">
                    <div class="kt-portlet__head-label">
                        <h3 class="kt-portlet__head-title">
                            Mati Verification Details
                            @if ($matiVerifData && $matiVerifData != null)
                                @isset($matiVerifData->identity->status)
                                    @if ($matiVerifData->identity->status == 'verified')
                                        <span class="kt-badge  kt-badge--success kt-badge--inline kt-badge--pill"
                                            style="font-size:15px">{{Str::ucfirst($matiVerifData->identity->status)}}</span>
                                    @elseif ($matiVerifData->identity->status == 'rejected')
                                        <span class="kt-badge  kt-badge--danger kt-badge--inline kt-badge--pill"
                                            style="font-size:15px">{{Str::ucfirst($matiVerifData->identity->status)}}</span>
                                    @elseif ($matiVerifData->identity->status == 'reviewNeeded')
                                        <span class="kt-badge  kt-badge--warning kt-badge--inline kt-badge--pill"
                                            style="font-size:15px">{{Str::ucfirst($matiVerifData->identity->status)}}</span>
                                    @else
                                    <span class="kt-badge  kt-badge--info kt-badge--inline kt-badge--pill"
                                        style="font-size:15px">{{Str::ucfirst($matiVerifData->identity->status)}}</span>
                                    @endif
                                @endisset
                            @endif
                        </h3>

                            <div style="position: absolute; right:0;">
                                <a class="btn btn-primary confirm-email" id="send_email" style="color:#fff">Verification Mail</a>
                                <a class="btn btn-primary confirm-sms" id="send_sms" style="color:#fff">Verification SMS</a>
                                @if (isset($user) && $user->mati_identity != NULL)
                                <a href="{{ route('admin.customer.fetchMatiData',$user->mati_identity) }}" class="btn btn-primary confirm-data" id="fetch_data" style="color:#fff">Fetch Data</a>
                                @endif
                                <a class="btn btn-primary confirm-data" id="update_mati_data" style="color:#fff">Update Mati Identity and Verification ID</a>
                            </div>

                    </div>
                </div>
                <div class="kt-portlet_body">
                    <div class="kt-section">
                        <div class="kt-section__content" style="padding-left:20px; padding-right: 20px;"><br>
                        @if (isset($user) && $user->mati_identity != null)
                        <h5>Mati Id : {{ $user->mati_identity }}</h5>
                        @endif
                        @if ($customerMati && $customerMati != null)
                                <h5>Identity ID : {{$customerMati->identity_id}}</h5>
                            @endif
                            @if ($customerMati && $customerMati != null)
                                <h5>Verification ID : {{$customerMati->verification_id}}</h5>
                            @endif
                            {{-- @if ($matiVerifData && $matiVerifData->identity->status == 'verified') --}}
                            @if($matiVerifData)
                                @isset ($matiVerifData->documents)
                                    <table class="table table-striped m-table">
                                        <tbody>
                                            @foreach ($matiVerifData->documents as $item)
                                            <tr>
                                                <th>Full Name</th>
                                                @isset($item->fields)
                                                    @if($item->fields)
                                                        @isset($item->fields->fullName->value)
                                                            @if($item->fields->fullName->value)
                                                                <td>{!! ucwords($item->fields->fullName->value) !!}</td>
                                                            @else
                                                                <td>Name not found</td>
                                                            @endif
                                                        @endisset
                                                    @else
                                                        <td>User not found</td>
                                                    @endif
                                                @endisset
                                                <th>Date Of Birth</th>
                                                @isset($item->fields->dateOfBirth->value)
                                                    @if($item->fields->dateOfBirth->value)
                                                        <td>{!! $item->fields->dateOfBirth->value !!}</td>
                                                    @else
                                                        <td>DOB not found</td>
                                                    @endif
                                                @endisset
                                            </tr>

                                            @if ($matiVerifData)
                                                @isset($matiVerifData->identity->status)
                                                    @if ($matiVerifData->identity->status != 'rejected')
                                                        <tr>
                                                            <th>First Name</th>
                                                            @isset($item->fields->firstName->value)
                                                                @if ($item->fields->firstName->value)
                                                                    <td>{!! ucwords($item->fields->firstName->value) !!}</td>
                                                                @else
                                                                    <td>First Name not found</td>
                                                                @endif
                                                            @endisset

                                                            <th>Surname</th>
                                                            @isset($item->fields->surname->value)
                                                                @if($item->fields->surname->value)
                                                                    <td>{!! ucwords($item->fields->surname->value) !!}</td>
                                                                @else
                                                                    <td>Surname not found</td>
                                                                @endif
                                                            @endisset
                                                        </tr>
                                                    @endif
                                                @endisset
                                            @endif

                                            <tr>
                                                @if ($matiVerifData)
                                                    @isset($matiVerifData->identity->status)
                                                        @if ($matiVerifData->identity->status != 'rejected')
                                                            <th>Gender</th>
                                                            @isset($item->fields->sex->value)
                                                                @if($item->fields->sex->value)
                                                                    <td>{!! $item->fields->sex->value !!}</td>
                                                                @else
                                                                    <td>Gender not found</td>
                                                                @endif
                                                            @endisset
                                                        @endif
                                                    @endisset
                                                @endif



                                                <th>Document Type</th>
                                                @isset($item->type)
                                                    @if($item->type)
                                                        <td>{!! ucfirst($item->type) !!}</td>
                                                    @else
                                                        <td>Document Type not found</td>
                                                    @endif
                                                @endisset


                                            </tr>
                                            <tr>
                                                <th>Document Number</th>
                                                @isset($item->fields->documentNumber->value)
                                                    @if($item->fields->documentNumber->value)
                                                        <td>{!! $item->fields->documentNumber->value !!}</td>
                                                    @else
                                                        <td>Document Number not found</td>
                                                    @endif
                                                @endisset


                                                <th>Expiration Date</th>
                                                @isset($item->fields->expirationDate->value)
                                                    @if($item->fields->expirationDate->value)
                                                        <td>{!! $item->fields->expirationDate->value !!}</td>
                                                    @else
                                                        <td>Expiration Date not found</td>
                                                    @endif
                                                @endisset


                                            </tr>
                                            @if ($matiVerifData)
                                                @isset($matiVerifData->identity->status)
                                                    @if ($matiVerifData->identity->status != 'rejected')
                                                        <tr>
                                                            <th>Country of issuance</th>
                                                            @isset($item->fields->issueCountry->value)
                                                                @if($item->fields->issueCountry->value)
                                                                    <td>{!! $item->fields->issueCountry->value !!}</td>
                                                                @else
                                                                    <td>Country of issuance not found</td>
                                                                @endif
                                                            @endisset


                                                            <th>Nationality</th>
                                                            @isset($item->fields->nationality->value)
                                                                @if($item->fields->nationality->value)
                                                                    <td>{!! ucfirst($item->fields->nationality->value) !!}</td>
                                                                @else
                                                                    <td>Nationality not found</td>
                                                                @endif
                                                            @endisset


                                                        </tr>
                                                    @endif
                                                @endisset
                                            @endif


                                            @endforeach
                                        </tbody>
                                    </table>
                                @endisset
                            @endif
                            {{-- @endif --}}
                            <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>

                            <div class="kt-portlet__head">
                                <div class="kt-portlet__head-label">
                                    <h3 class="kt-portlet__head-title">
                                        Location Details
                                    </h3>
                                </div>
                            </div>

                            @if ($matiVerifData)
                                @isset($matiVerifData->steps)
                                    @if ($matiVerifData->steps != null)
                                        @foreach ($matiVerifData->steps as $steps)
                                            @isset($steps->id)
                                                @if ($steps->id == 'ip-validation')
                                                    <table class="table table-striped m-table">
                                                        <tbody>
                                                            <tr>
                                                                <th>Country</th>
                                                                @isset($steps->data->country)
                                                                    <td>{!! $steps->data->country !!}</td>
                                                                @endisset

                                                            </tr>
                                                            <tr>
                                                                <th>Region</th>
                                                                @isset($steps->data->region)
                                                                    <td>{!! $steps->data->region !!}</td>
                                                                @endisset

                                                            </tr>
                                                            <tr>
                                                                <th>City</th>
                                                                @isset($steps->data->city)
                                                                    <td>{!! $steps->data->city !!}</td>
                                                                @endisset

                                                            </tr>
                                                            <tr>
                                                                <th>Zip</th>
                                                                @isset($steps->data->zip)
                                                                    <td>{!! $steps->data->zip !!}</td>
                                                                @endisset

                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                @endif
                                            @endisset

                                        @endforeach
                                    @endif
                                @endisset
                            @endif


                        </div>
                    </div>
                </div>

                <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>

                <div class="kt-portlet__head">
                    <div class="kt-portlet__head-label">
                        <h3 class="kt-portlet__head-title">
                            Images Uploaded
                        </h3>
                    </div>
                </div>
                <div class="kt-portlet_body">
                    <div class="kt-section">
                        <div class="kt-section__content" style="padding-left:20px; padding-right: 20px;">
                            {{-- @if ($matiVerifData && $matiVerifData->identity->status == 'verified') --}}
                            {{-- @if ($matiVerifData && $matiVerifData != null) --}}
                            @if ($customerMati && $customerMati != null)
                            <div class="form-group row">
                                <div class="col-2">
                                    <h3 class="col-form-label" style="float: left;">Passport</h3>
                                    <div class="kt-avatar" id="left" style="float: left; clear: left;">
                                        {{-- @foreach ($item->photos as $item) --}}
                                        {{-- @if($item == NULL) --}}
                                        @if($customerMati->passport == null)
                                        <div class="kt-avatar__holder"
                                            style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)">
                                        </div>
                                        @else
                                        <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($customerMati->passport) !!}"
                                            target="_blank" rel="noopener noreferrer">
                                            <div class="kt-avatar__holder"
                                                style="background-image:url('{!! \AlphaDirect\Helper::getCloudFrontURL($customerMati->passport) !!}')">
                                            </div>
                                        </a>
                                        @endif
                                        {{-- @endforeach --}}
                                    </div>
                                </div>

                                <div class="col-2">
                                    <h3 class="col-form-label" style="float: left;">Omang</h3>
                                    <div class="kt-avatar" id="left" style="float: left; clear: left;">
                                        {{-- @foreach ($item->photos as $item) --}}
                                        {{-- @if($item == NULL) --}}
                                        @if($customerMati->omang == null)
                                        <div class="kt-avatar__holder"
                                            style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)">
                                        </div>
                                        @else
                                        <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($customerMati->omang) !!}"
                                            target="_blank" rel="noopener noreferrer">
                                            <div class="kt-avatar__holder"
                                                style="background-image:url('{!! \AlphaDirect\Helper::getCloudFrontURL($customerMati->omang) !!}')">
                                            </div>
                                        </a>
                                        @endif
                                        {{-- @endforeach --}}
                                    </div>
                                </div>

                                <div class="col-2">
                                    <h3 class="col-form-label" style="float: left;">Omang Back</h3>
                                    <div class="kt-avatar" id="left" style="float: left; clear: left;">
                                        {{-- @foreach ($item->photos as $item) --}}
                                        {{-- @if($item == NULL) --}}
                                        @if($customerMati->omangBack == null)
                                        <div class="kt-avatar__holder"
                                            style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)">
                                        </div>
                                        @else
                                        <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($customerMati->omangBack) !!}"
                                            target="_blank" rel="noopener noreferrer">
                                            <div class="kt-avatar__holder"
                                                style="background-image:url('{!! \AlphaDirect\Helper::getCloudFrontURL($customerMati->omangBack) !!}')">
                                            </div>
                                        </a>
                                        @endif
                                        {{-- @endforeach --}}
                                    </div>
                                </div>

                                <div class="col-2">
                                    <h3 class="col-form-label" style="float: left;">Selfie</h3>
                                    <div class="kt-avatar" id="right" style="float: left; clear: left;">
                                        {{-- @foreach ($matiVerifData->steps as $item) --}}
                                        {{-- @if ($item->id == 'selfie') --}}
                                        {{-- @if($item->data->selfiePhotoUrl == NULL) --}}
                                        @if($customerMati->selfie == null)
                                        <div class="kt-avatar__holder"
                                            style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)">
                                        </div>
                                        @else
                                        <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($customerMati->selfie) !!}"
                                            target="_blank" rel="noopener noreferrer">
                                            <div class="kt-avatar__holder"
                                                style="background-image:url('{!! \AlphaDirect\Helper::getCloudFrontURL($customerMati->selfie) !!}')">
                                            </div>
                                        </a>
                                        @endif
                                        {{-- @endif --}}
                                        {{-- @endforeach --}}
                                    </div>
                                </div>
                            </div>
                            @endif
                            {{-- @endif --}}
                            {{-- @if ($matiVerifData && $matiVerifData->identity->status == 'reviewNeeded' || $matiVerifData && $matiVerifData->identity->status == 'rejected')
                                            @if ($matiVerifData && $matiVerifData != null)
                                                <div class="form-group row">
                                                    <div class="col-2">
                                                        <h3 class="col-form-label" style="float: left;"></h3>
                                                        <div class="kt-avatar" id="left"
                                                                style="float: left; clear: left;">
                                                            @foreach ($matiVerifData->documents as $item)
                                                                @foreach ($item->photos as $photo)
                                                                    @if($photo == NULL)
                                                                        <div class="kt-avatar__holder"
                                                                                style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)">
                                                                        </div>
                                                                    @else
                                                                        <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($photo) !!}" target="_blank" rel="noopener noreferrer">
                                                                            <div class="kt-avatar__holder"
                                                                                style="background-image:url('{!! \AlphaDirect\Helper::getCloudFrontURL($photo) !!}')">
                                                                            </div>
                                                                        </a>
                                                                    @endif
                                                                @endforeach
                                                            @endforeach
                                                        </div>
                                                    </div>


                                                    <div class="col-2">
                                                        <h3 class="col-form-label" style="float: left;"></h3>
                                                        <div class="kt-avatar" id="right"
                                                                style="float: left; clear: left;">
                                                            @foreach ($matiVerifData->steps as $item)
                                                                @if ($item->id == 'selfie')
                                                                    @if($item->data->selfiePhotoUrl == NULL)
                                                                        <div class="kt-avatar__holder"
                                                                                style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)">
                                                                        </div>
                                                                    @else
                                                                        <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($item->data->selfiePhotoUrl) !!}" target="_blank" rel="noopener noreferrer">
                                                                            <div class="kt-avatar__holder"
                                                                                style="background-image:url('{!! \AlphaDirect\Helper::getCloudFrontURL($item->data->selfiePhotoUrl) !!}')">
                                                                            </div>
                                                                        </a>
                                                                    @endif
                                                                @endif
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                </div>
                                            @endif
                                        @endif --}}

                            <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>

                            <div class="kt-portlet__head">
                                <div class="kt-portlet__head-label">
                                    <h3 class="kt-portlet__head-title">
                                        Device Checks
                                    </h3>
                                </div>
                            </div>

                            @if($matiVerifData)
                                @isset($matiVerifData->deviceFingerprint)
                                    @if ($matiVerifData->deviceFingerprint != null)
                                        <table class="table table-striped m-table">
                                            <tbody>
                                                <tr>
                                                    <th>Device Type</th>
                                                    <td>
                                                        @isset($matiVerifData->deviceFingerprint->app->platform)
                                                            @if ($matiVerifData->deviceFingerprint->app->platform == 'web_desktop')
                                                                {{ 'Desktop' }}
                                                            @else
                                                                {{$matiVerifData->deviceFingerprint->app->platform}}
                                                            @endif
                                                        @endisset

                                                    </td>
                                                </tr>
                                                <tr>
                                                    <th>OS</th>
                                                    @isset($matiVerifData->deviceFingerprint->os->name)
                                                        <td>{!! $matiVerifData->deviceFingerprint->os->name !!}&nbsp;
                                                            @isset($matiVerifData->deviceFingerprint->os->version){!!
                                                            $matiVerifData->deviceFingerprint->os->version !!}@endisset</td>
                                                    @endisset

                                                </tr>
                                                <tr>
                                                    <th>Browser</th>
                                                    @isset($matiVerifData->deviceFingerprint->browser->name)
                                                        <td>{!! $matiVerifData->deviceFingerprint->browser->name !!}&nbsp;{!!
                                                            $matiVerifData->deviceFingerprint->browser->major !!}</td>
                                                    @endisset

                                                </tr>
                                                <tr>
                                                    <th>IP Address</th>
                                                    @isset($matiVerifData->deviceFingerprint->ip)
                                                        <td>{!! $matiVerifData->deviceFingerprint->ip !!}</td>
                                                    @endisset

                                                </tr>
                                            </tbody>
                                        </table>
                                    @endif
                                @endisset
                            @endif



                        </div>
                    </div>
                </div>
            </div>

            <div class="tab-pane" id="kt_portlet_base_demo_3_8_loss_history" role="tabpanel">

                <br>

                <div class="kt-portlet__body">
                    <!--begin: Datatable -->
                    <table class="table table-striped table-bordered table-hover table-checkable" id="loss_history_table">
                        <thead>
                        <tr>
                            <th>Claim Number</th>
                            <th>Claim Type</th>
                            <th>Claim Handler</th>
                            <th>Status</th>
                        </tr>
                        </thead>
                    </table>
                    <!--end: Datatable -->
                </div>
            </div>

            <div class="tab-pane" id="kt_portlet_base_demo_3_4_realpay_translog" role="tabpanel">
                {{--Transaction logs--}}
                <div class="kt-portlet__head">
                    <div class="kt-portlet__head-label">
                        <div class="d-flex flex-row justify-content-between">
                            <div class="col-4">
                                <h3 class="kt-portlet__head-title">
                                    Realpay Transactions
                                </h3>
                            </div>
                            <div class="col-2 text-right">
                                @if (auth::user()->hasPermissionTo('policy-add_new_realpay_installment_data') )
                                    <button class="btn btn-primary m-3" id="addNewRealpayInstallment" data-policyNumber="{{ $policy->policyNumber }}" data-toggle="modal" data-target="#addRealpayInstallmentModel">Add New Installment</button>
                                @endif
                            </div>
                            <div class="col-2 text-right">
                                @if (auth::user()->hasPermissionTo('policy-update_realpay_installment_data') )
                                    <button class="btn btn-primary m-3" id="updateRealpayInstallment" data-policyNumber="{{ $policy->policyNumber }}" data-toggle="modal" data-target="#updateRealpayInstallmentModel">Update Installment</button>
                                @endif
                            </div>
                            <div class="col-2 text-right">
                                @if (auth::user()->hasPermissionTo('policy-update_realpay_installment_data') )
                                    <button class="btn btn-primary m-3" id="updateRealpayAllInstallment" data-policyNumber="{{ $policy->policyNumber }}" data-toggle="modal" data-target="#updateRealpayAllInstallmentModel">Update All Installment</button>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
                <div class="kt-portlet__body">
                    <!--begin: Datatable -->
                    <!--begin: Datatable -->
                    <table class="table table-striped table-bordered table-hover table-checkable" id="product_table">
                        <thead>
                            <tr>
                                <th>Sequence</th>
                                <th>Reference No.</th>
                                <th>CTC Amnt</th>
                                <th>Action Date</th>
                                <th>Tracking Code</th>
                                <th>Installment Amount</th>
                                <th>Bank Response</th>
                                <th>Installment Status</th>
                                <th>Retry Count</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                    </table>
                    <!--end: Datatable -->
                </div>
            </div>

            <div class="tab-pane" id="kt_portlet_base_demo_3_9_add_realpay_client_contract" role="tabpanel">
                <!--begin::Form-->
                <form id="clientForm" action="{{ route('admin.fetchCustomer') }}"
                    method="POST" enctype="multipart/form-data" class="kt-form">
                    <!-- CSRF Token -->
                    <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                    <input type="hidden" name="returnBlade" value="view" />
                    <div class="kt-portlet__body">
                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Policy Number:</label>
                            <div class="col-9">
                                <input class="form-control" name="policyNumber"  placeholder="Please provide policy number" title="Policy number is required">
                            </div>
                        </div>
                        <p></p>

                        <div class="kt-portlet__foot kt-portlet__foot--solid">
                            <div class="kt-form__actions">
                                <div class="row">
                                    <div class="col-3"></div>
                                    <div class="col-9">
                                        <button type="submit" value="Submit" id="btn" class="btn btn-brand">Submit</button>
                                        <button class="btn btn-brand" type="button" id="loadBtn" style="display:none" > <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Loading... </button>
                                        <a class="btn btn-secondary" href="{{ route('admin.branch.index') }}" >Cancel</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
                <!--end::Form-->
            </div>

            <div class="tab-pane" id="kt_portlet_base_demo_3_4_policy_reinstate_table_data" role="tabpanel">
                {{--Transaction logs--}}
                <div class="kt-portlet__body">
                    <!--begin: Datatable -->
                    <!--begin: Datatable -->
                    <table class="table table-striped table-bordered table-hover table-checkable" id="policyReinstate_table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>PolicyNumber</th>
                                <th>Reinstated By</th>
                                <th>Reinstated Date</th>
                                <th>Policy Activated Date</th>
                                <th>Policy Cancelled Date</th>
                                <th>Reinstate Type</th>
                                <th>Reinstated</th>
                                <th>Rerated</th>
                                {{-- <th>Bank Response</th>
                                <th>Installment Status</th>
                                <th>Retry Count</th>
                                <th>Action</th> --}}
                            </tr>
                        </thead>
                    </table>
                    <!--end: Datatable -->
                </div>
            </div>

            <div class="tab-pane" id="kt_portlet_base_demo_3_4_term_table_data" role="tabpanel">
                {{--Transaction logs--}}
                <div class="kt-portlet__body">
                    <!--begin: Datatable -->
                    <!--begin: Datatable -->
                    <table class="table table-striped table-bordered table-hover table-checkable" id="policyTerm_table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Term Start Date</th>
                                <th>Term End Date</th>
                                <th>Anuual Premium</th>
                                <th>Frequency and Premium</th>
                                <th>Trans Type</th>
                                <th>Status</th>
                                <th>Action</th>
                                {{-- <th>Bank Response</th>
                                <th>Installment Status</th>
                                <th>Retry Count</th>
                                <th>Action</th> --}}
                            </tr>
                        </thead>
                    </table>
                    <!--end: Datatable -->
                    <h3 class="mt-4 my-2">Policy Life Cycle</h3>
                    <table class="table table-striped table-bordered table-hover table-checkable" id="policyLifeCycle_table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <!-- <th>Term Start Date</th> -->
                                <!-- <th>Term End Date</th> -->
                                <th>Premium</th>
                                <th>Frequency</th>
                                <th>Billing Date</th>
                                <th>Action by</th>
                                <th>Action Date</th>
                                {{-- <th>Status</th> --}}
                                <th>Action</th>
                                {{-- <th>Bank Response</th>
                                <th>Installment Status</th>
                                <th>Retry Count</th>
                                <th>Action</th> --}}
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
            <!---policy lifecycle ---->
            <div class="tab-pane" id="kt_portlet_base_demo_3_4_term_table_data" role="tabpanel">
                {{--Transaction logs--}}
                <div class="kt-portlet__body">
                    <!--begin: Datatable -->
                    <!--begin: Datatable -->
                    <table class="table table-striped table-bordered table-hover table-checkable" id="policyLifeCycle_table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <!-- <th>Term Start Date</th> -->
                                <!-- <th>Term End Date</th> -->
                                <th>Premium</th>
                                <th>Frequency</th>
                                <th>Action by</th>
                                <th>Action Date</th>
                                {{-- <th>Status</th> --}}
                                <th>Action</th>
                                {{-- <th>Bank Response</th>
                                <th>Installment Status</th>
                                <th>Retry Count</th>
                                <th>Action</th> --}}
                            </tr>
                        </thead>
                    </table>
                    <!--end: Datatable -->
                </div>
            </div>

            <!---End policy lifecycle --->
            <div class="tab-pane" id="kt_portlet_base_demo_3_4_change_preminum_frequency" role="tabpanel">
                <div class="kt-portlet">
                    <!--begin::Form-->
                    <input type="hidden" name="quoteNumber" id="quoteNumber" value="{{ $policy->quoteNumber }}" />
                    <div class="kt-portlet__body">
                        <h5>Preminum Frequency:
                            @if ($policy->premium_freq == 1)
                                <span>Monthly Instalments</span>
                            @elseif ($policy->premium_freq == 2)
                                <span>Three Instalments in a year</span>
                            @else
                                <span>Annual Instalment</span>
                            @endif
                            {{$policy->premium}}
                        </h5>
                        <form id="accountCreate" action="{{ route('admin.changePreminumFrequencyPolicy') }}"
                        method="POST" enctype="multipart/form-data" class="kt-form">
                          <!-- CSRF Token -->
                          <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                          <div class="kt-portlet__body">
                              <div class="form-group row">
                                  <label  class="col-3 col-form-label">Policy Number :</label>
                                  <div class="col-9">
                                      <input  class="form-control" name="policyNumber"  value="{{ $policy->policyNumber }}" title="Please provide policy number" disabled placeholder="Please provide policy number">
                                      <input type="hidden" name="policyID" value="{{ $policy->id }}"/>
                                      <span class="form-text text-muted"></span>
                                  </div>
                              </div>
                              <div class="form-group row">
                                  <label  class="col-3 col-form-label">Customer Name :</label>
                                  <div class="col-9">
                                      <input  class="form-control" name="customerName"  title="Please provide policy number" value="{{ $user->firstName.' '.$user->lastName }}" placeholder="Please provide policy number" disabled>
                                      <span class="form-text text-muted"></span>
                                  </div>
                              </div>
                              <div class="form-group row">
                                  <label  class="col-3 col-form-label">ID Type and Number:</label>
                                  <div class="col-9">
                                      <input  class="form-control" name="customerName"  title="Please provide policy number" value="@isset($omang_passport_id) {{ $omang_passport_id }} @endisset" placeholder="Please provide policy number" disabled>
                                  </div>
                              </div>
                              <div class="form-group row">
                                  <label  class="col-3 col-form-label">EMail :</label>
                                  <div class="col-9">
                                      <input  class="form-control" name="email"  title="Please provide email"  value="{{ $user->email }}" placeholder="Please provide email" disabled>
                                      <span class="form-text text-muted"></span>
                                  </div>
                              </div>
                              <div class="form-group row">
                                  <label  class="col-3 col-form-label">Cellphone :</label>
                                  <div class="col-9">
                                      <input  class="form-control" name="cellphone"  value="{{ $user->cellphone }}" title="Please provide cellphone number" value="" placeholder="Please provide cellphone number">
                                      <span class="form-text text-muted"></span>
                                  </div>
                              </div>
                              <div class="form-group row">
                                  <label  class="col-3 col-form-label">Payment Frequency :</label>
                                  <div class="col-9">
                                    <select class="form-control" name="frequency" id="frequency">
                                        <option value="" disabled selected>Select frequency</option>
                                        @isset($motorPreminum)
                                            @if ($policy->premium_freq == 1)
                                                <option value="2" >Three Instalments in a year {{$motorPreminum['3_inst']}}</option>
                                                <option value="3" >Annual Instalment {{$motorPreminum['annual']}}</option>
                                            @elseif ($policy->premium_freq == 2)
                                                <option value="1" >Monthly Instalments {{$motorPreminum['monthly']}}</option>
                                                <option value="3" >Annual Instalment {{$motorPreminum['annual']}}</option>
                                            @elseif($policy->premium_freq == 3)
                                                <option value="1" >Monthly Instalments {{$motorPreminum['monthly']}}</option>
                                                <option value="2" >Three Instalments in a year {{$motorPreminum['3_inst']}}</option>
                                            @else
                                                <option value="1" >Monthly Instalments {{$motorPreminum['monthly']}}</option>
                                                <option value="2" >Three Instalments in a year {{$motorPreminum['3_inst']}}</option>
                                                <option value="3" >Annual Instalment {{$motorPreminum['annual']}}</option>
                                            @endif
                                        @endisset
                                    </select>
                                      <span class="form-text text-muted"></span>
                                  </div>
                              </div>
                              <div class="form-group row firstCollectionDate">
                                  <label  class="col-3 col-form-label">First Collection Date :</label>
                                  <div class="col-9">
                                      <input type="text" required class="form-control kt_datepicker_1 dob" name="first_collection_date" id="first_collection_date" autocomplete="off"  placeholder="Select date" />
                                      <span class="form-text text-muted"></span>
                                  </div>
                              </div>
                              <div class="form-group row" id="first_premium_row">
                                  <label  class="col-3 col-form-label">First Instalment Amount :</label>
                                  <div class="col-9">
                                      <input  class="form-control" name="first_premium" id="first_premium" title="Please provide pro rata premium" placeholder="Please provide pro rata premium">
                                      <span class="form-text text-muted"></span>
                                  </div>
                              </div>
                              <div class="form-group row">
                                  <label  class="col-3 col-form-label">Billing Date :</label>
                                  <div class="col-9">

                                      <input type="text" required class="form-control kt_datepicker_1 dob" name="billingDay" id="dob" autocomplete="off"  placeholder="Select date" />

                                      <span class="form-text text-muted"></span>
                                  </div>
                              </div>
                              <div class="form-group row">
                                  <label  class="col-3 col-form-label">Premium :</label>
                                  <div class="col-9">
                                      <input  class="form-control" name="premium" id="premium" title="Please provide premium" placeholder="Please provide premium">
                                      <span class="form-text text-muted"></span>
                                  </div>
                              </div>

                              <div class="form-group row">
                                  <label for="example-text-input" class="col-3 col-form-label">Bank:</label>
                                  <div class="col-9">
                                      <select class="form-control" name="BankCode" id="banks">
                                          <option value="" disabled selected>Select Bank</option>
                                          @foreach($banks as $b)
                                              <option value="{{ $b->bank_number }}">{{ $b->bank_name }}</option>
                                          @endforeach
                                      </select>
                                  </div>
                              </div>

                              <div class="form-group row">
                                  <label for="example-text-input" class="col-3 col-form-label">Branch:</label>
                                  <div class="col-9">
                                      <select class="form-control" name="BranchCode" id="branches"></select>
                                  </div>
                              </div>

                              <div class="form-group row">
                                  <label for="example-text-input" class="col-3 col-form-label">Account Number:</label>
                                  <div class="col-9">
                                      <input  class="form-control" name="accountNumber" title="Please provide bank account number" placeholder="Please provide bank account number">
                                  </div>
                              </div>

                              <div class="form-group row">
                                  <label for="example-text-input" class="col-3 col-form-label">Account Type:</label>
                                  <div class="col-9">
                                      <select class="form-control" name="accountType" id="accountType">
                                          <option value="">Please select account type</option>
                                          <option value="1">Cheque</option>
                                          <option value="2">Savings</option>
                                      </select>
                                  </div>
                              </div>
                          </div>
                          <div class="kt-portlet__foot kt-portlet__foot--solid">
                              <div class="kt-form__actions">
                                  <div class="row">
                                      <div class="col-3"></div>
                                      <div class="col-9">
                                          <button type="submit" value="Submit" id="submitbtn" class="btn btn-brand">Submit</button>
                                          <button class="btn btn-brand" type="button" id="loadBtn" style="display:none" > <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Loading... </button>
                                          <a class="btn btn-secondary" href="{{ route('admin.getCustomer') }}" >Cancel</a>
                                      </div>
                                  </div>
                              </div>
                          </div>
                      </form>
                    </div>
                    <!--end::Form-->
                </div>
            </div>

            <div class="tab-pane" id="kt_portlet_base_demo_3_4_client_contract_list" role="tabpanel">
                <div class="col-lg-12">
                    <button class="btn btn-brand" style="float:right; margin-top: 2%;  margin-right: 10px;" id="updateClient" type="button" data-toggle="collapse" data-target="#updateClientNumberDiv" aria-expanded="false" aria-controls="collapseExample"> Update Client Number </button>
                </div>
                <br>
                <div class="kt-portlet__body">
                    <div class="form-group row collapse" id="updateClientNumberDiv">
                        <div class="col-md-12">
                            <div class="kt-checkbox-inline">
                                <form action="{{ route('admin.updateRealpayClientNumber') }}" method="POST" id="updateClient" enctype="multipart/form-data" class="kt-form">
                                    <!-- CSRF Token -->
                                    <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                                    <input type="hidden" name="policy_id" id="policy_id" value= "{{ $policy->id }}" />
                                    <input type="hidden" name="product_id" id="product_id" value= "{{ $policy->product_id }}" />

                                    <div class="kt-portlet__body" id="ClientNumberDiv">
                                        <div class="form-group row">
                                            <label for="example-text-input" class="col-3 col-form-label">Client Number</label>
                                            <div class="col-6">
                                                <input type="text" class="form-control" name="clientNumber"  title="Client number is required" placeholder="Enter Client Number" required>
                                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <label for="example-text-input" class="col-3 col-form-label">Contract Number</label>
                                            <div class="col-6">
                                                <input type="text" class="form-control" name="contractNumber"  title="Contract number is required" placeholder="Enter Contract Number" required>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="kt-portlet__foot kt-portlet__foot--solid">
                                        <div class="kt-form__actions">
                                            <div class="row">
                                                <div class="col-3"></div>
                                                <div class="col-9" style="margin-left: 300px">
                                                    <button class="btn btn-brand" type="submit" id="submitClientNumber">Submit</button>
                                                    {{-- <button class="btn btn-brand" type="button" id="attachmentloadBtn" style="display:none" > <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Loading... </button> --}}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                {{--Transaction logs--}}
                <div class="kt-portlet__body">
                    <!--begin: Datatable -->
                    <!--begin: Datatable -->
                    <table class="table table-striped table-bordered table-hover table-checkable" id="contract_list_table">
                        <thead>
                            <tr>
                                {{-- <th>Contract Sequence</th> --}}
                                <th>ID</th>
                                <th>Client Number</th>
                                <th>Contract Number</th>
                                {{-- <th>CTC Percentage</th> --}}
                                {{-- <th>Instalment Start Date</th> --}}
                                {{-- <th>Tracking Code</th> --}}
                                {{-- <th>Number Of Instalments</th>
                                <th>First Collection Date</th>
                                <th>First Collection Amount</th>
                                <th>Frequency Code</th>
                                <th>Collection Day</th> --}}
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                    </table>
                    <!--end: Datatable -->
                </div>
            </div>

            {{-- generate payment url --}}
            {{-- {{ empty($tabName) || $tabName == 'kt_generatePaymentUrl' ? 'active' : '' }} --}}
            <div class="tab-pane"  id="kt_portlet_base_demo_3_4_generateURL_content" role="tabpanel">
                <div class="kt-portlet__body">
                    <div class="tab-content">
                        @if($product->id == 3 && isset($is_renewal->is_renewed)  && $is_renewal->is_renewed == 0)
                            <h4 id="urlPaymentHeader">Link For Renew</h4><br>
                            @if(auth()->user()->hasRole(['Super Admin']))
                                <a href="{{ route('admin.policy.generateAndSendRenewalLink', $policy->policyNumber) }}" class="btn btn-success">Generate Link</a>
                            @endif
                            <hr><br>
                        @endif
                        <div class="kt-portlet">
                            <form action="#" id="urlPaymentForm" class="kt-form">
                                <div class="form-group">
                                    <label for="urlCellphone">Confirm Customer CellPhone:</label>
                                    <input type="text" class="form-control col-6 urlCellphone" name="urlCellphone"
                                        aria-describedby="emailHelp" placeholder="" value="{{$user->cellphone}}"
                                        minlength="8" maxlength="8" pattern="[0-9]{8}">
                                </div>
                                <div class="form-group">
                                    <label for="urlPolicyNumber">Policy Number:</label>
                                    <input type="text" class="form-control col-6" name="urlPolicyNumber"
                                        aria-describedby="emailHelp" value="{{$policy->policyNumber}}">
                                </div>
                                <div class="form-group">
                                    <label>Link Type</label>
                                    <select class="form-control required col-6" name="type" id="type">
                                        <option value="">Please select link type</option>
                                        <option value="repay">Repay</option>
                                        <option value="update_card_details">Update card details</option>
                                        @if($product->id == 3 && isset($is_renewal->is_renewed)  && $is_renewal->is_renewed == 0)
                                            <option value="renew">Renew</option>
                                        @endif
                                        @if($policy->status == 2)
                                            <option value="reinstate_arrears">Reinstate Arrears</option>
                                            @if ($policy->product_id == 3)
                                                <option value="reinstate_fresh">Reinstate Fresh</option>
                                            @endif
                                            {{-- <option value="cancelled_accidently">Reinstate Accidently</option> --}}
                                        @endif

                                    </select>
                                </div>
                                <div class="form-group" id="requestedAmt">
                                    <label for="urlAmount">Requested Amount:</label>
                                    <input type="text" class="form-control col-6 urlAmount" name="urlAmount" id="urlAmount"
                                        title="Enter the amount" placeholder="Enter the Amount"
                                        aria-describedby="emailHelp" pattern="[0-9]{8}" value="{{$premium}}">
                                </div>
                                {{-- <div class="form-group" id="reinstateArrears" style="display: none;">
                                    <label for="urlAmount">Requested Amount:</label>
                                    <input type="text" class="form-control col-6 urlAmount" name="urlAmount"
                                        title="Enter the amount" placeholder="Enter the Amount"
                                        aria-describedby="emailHelp" pattern="[0-9]{8}" value="{{isset($balance_arrears->balance) ? $balance_arrears->balance : ''}}">
                                </div>
                                <div class="form-group" id="reinstateFresh" style="display: none;">
                                    <label for="urlAmount">Requested Amount:</label>
                                    <input type="text" class="form-control col-6 urlAmount" name="urlAmount"
                                        title="Enter the amount" placeholder="Enter the Amount"
                                        aria-describedby="emailHelp" pattern="[0-9]{8}" value="{{isset($balance_fresh) ? $balance_fresh : ''}}">
                                </div> --}}
                                {{-- <div class="form-group" id="reinstateAccidently" style="display: none;">
                                    <label for="urlAmount">Requested Amount:</label>
                                    <input type="text" class="form-control col-6 urlAmount" name="urlAmount"
                                        title="Enter the amount" placeholder="Enter the Amount"
                                        aria-describedby="emailHelp" pattern="[0-9]{8}" value="{{$premium}}">
                                </div> --}}

                                <div class="form-group">
                                    <label for="urlNote">Note:</label>
                                    <textarea class="form-control col-6 urlNote" id="urlNote" id="urlNote" name="urlNote" id="urlNote"
                                              title="Enter Note" placeholder="Enter Note" aria-describedby="emailHelp"></textarea>
                                </div>
                                <div class="form-group loader" style="display:none">
                                    <h1>test</h1>
                                    <div class="ml-4"><img src="{{ asset('img/loading.gif') }}" class="img-responsive"
                                            width=40 height=40></div>
                                </div>
                                <div class="form-group">
                                    <button type="button" class="btn btn-brand sendURL">Generate Payment
                                        URL</button>
                                </div>
                            </form>

                            <div class="alert alert-outline-success  alert-dismissible fade show" role="alert"
                                id="urlalert" style="display:none">
                                <strong class="text text-warning"><i class="flaticon-warning"></i>
                                    Excellent!! </strong>Payment Url Sent
                                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <table class="table table-striped table-bordered table-hover table-checkable"
                                id="paymentUrlTable">
                                <thead>
                                    <tr>
                                        <th>Id</th>
                                        <th>Created on</th>
                                        <th>Amount</th>
                                        <th>Sent From</th>
                                        <th>Url</th>
                                        <th>Type</th>
                                        <th>Status</th>
                                        <th>Created By</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                            </table>
                        </div>
                    </div>

                </div>
            </div>
            {{-- Calculate Per Day Premium --}}
            <div class="tab-pane" id="kt_portlet_base_demo_3_6_per_day_premium" role="tabpanel">
                <div class="kt-portlet">
                    <!--begin::Form-->
                    <input type="hidden" name="quoteNumber" id="quoteNumber" value="{{ $policy->quoteNumber }}" />
                    <div class="kt-portlet__body">
                        <div class="form-group row">
                            <label class="col-3 col-form-label">Quote Number:</label>
                            <div class="col-9">
                                <input class="form-control" value="{{ $policy->quoteNumber }}" disabled>
                                <span class="form-text text-muted"></span>
                            </div>
                        </div>
                        <div class="form-group row">
                            <label class="col-3 col-form-label">Difference in days:</label>
                            <div class="col-9">
                                <select class="form-control kt_selectpicker" title="Please select difference in days"
                                    data-live-search="true" id="days" name="days" required>
                                    <option value="">Please select difference in days</option>
                                    @for($i = 1;$i<=31;$i++) <option value="{{ $i }}">{{ $i }}</option>
                                        @endfor
                                </select>
                                <span class="form-text text-muted"></span>
                            </div>
                        </div>
                        <div class="form-group row" id="response">
                            <label class="col-3 col-form-label"></label>
                            <div class="col-9">
                                <div class="row">
                                    <label class="col-3 col-form-label">Per Day premium:</label>
                                    <div class="col-9">
                                        <label class="col-3 col-form-label" id="perDayValue"></label>
                                        <span class="form-text text-muted"></span>
                                    </div>
                                    <label class="col-3 col-form-label">Till Date:</label>
                                    <div class="col-9">
                                        <label class="col-3 col-form-label" id="datePremium"></label>
                                        <span class="form-text text-muted"></span>
                                    </div>
                                    <label class="col-3 col-form-label">Premium for <label class="col-form-label"
                                            id="daysP"></label> days:</label>
                                    <div class="col-9">
                                        <label class="col-3 col-form-label" id="tilldate"></label>
                                        <span class="form-text text-muted"></span>
                                    </div>

                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="kt-portlet__foot kt-portlet__foot--solid">
                        <div class="kt-form__actions">
                            <div class="row">
                                <div class="col-3"></div>
                                <div class="col-9">
                                    <button id="bt-ajax" class="btn btn-brand">Calculate</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!--end::Form-->
                </div>
            </div>
            {{-- Add offline payments --}}
            <div class="tab-pane {{ !empty($tabName) && $tabName == 'kt_portlet_base_demo_3_7_payments' ? 'active' : '' }}"
                id="kt_portlet_base_demo_3_7_payments" role="tabpanel">
                <div class="kt-portlet">
                    <form id="policyForm1" action="{{ route('admin.policy.storeCashPayment') }}" method="POST"
                        enctype="multipart/form-data" class="kt-form">
                        <!-- CSRF Token -->
                        <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                        <input type="hidden" name="policyNumber" value="{{ $policy->policyNumber }}" />
                        <input type="hidden" name="paymentMethod" value="Cash" />
                        <div class="kt-portlet__body">

                        @if(\Illuminate\Support\Facades\Auth::user()->hasPermissionTo('back-dated-transactions'))
                            <div class="form-group row">
                                <label class="col-3 col-form-label">Date of payment :</label>
                                <div class="col-9">
                                    <input type="text" class="form-control kt_datepicker_1 required validateGroup1"
                                        name="paymentDate" autocomplete="off" placeholder="Select date of payment" />
                                    <span class="form-text text-muted"></span>
                                </div>
                            </div>
                        @else
                            <p>Note : You can select Date of Payment for current month.</p>
                            <div class="form-group row">
                                <label class="col-3 col-form-label">Date of payment :</label>
                                <div class="col-9">
                                    <input type="text" class="form-control required validateGroup1"
                                       id="date_of_refund_for_current_month" name="paymentDate" autocomplete="off" placeholder="Select date of payment" />
                                    <span class="form-text text-muted"></span>
                                </div>
                            </div>
                        @endif

                            <div class="form-group row">
                                <label class="col-3 col-form-label">Payment Amount :</label>
                                <div class="col-9">
                                    <input class="form-control validateGroup1 required" name="paymentAmount" value=""
                                        title="Please provide amount" placeholder="Please provide amount">
                                    <span class="form-text text-muted"></span>
                                </div>
                            </div>
                            <div class="form-group row">
                                <label class="col-3 col-form-label">Receipt number:</label>
                                <div class="col-9">
                                    <input class="form-control validateGroup1 required" name="receiptNumber" value=""
                                        title="Please provide payment receipt"
                                        placeholder="Please provide payment receipt number">
                                    <span class="form-text text-muted"></span>
                                </div>
                            </div>
                            <div class="form-group row">
                                <label class="col-3 col-form-label">Payment Recieved By :</label>
                                <div class="col-9">
                                    <input class="form-control validateGroup1 required" name="paymentRecievedBy"
                                        value="" title="Please provide contract sequence"
                                        placeholder="Please provide payment recipient">
                                    <span class="form-text text-muted"></span>
                                </div>
                            </div>

                            <div class="form-group row">
                                <label class="col-3 col-form-label">Numbers of instalments paid :</label>
                                <div class="col-9">
                                    <input class="form-control validateGroup1 required" name="numberOfInstalmentsPaid"
                                        value="" title="Please provide the number of instalments paid"
                                        placeholder="Please provide the number of instalments paid">
                                    <span class="form-text text-muted"></span>
                                </div>
                            </div>
                            <div class="form-group row">
                                <label class="col-3 col-form-label">Note :</label>
                                <div class="col-9">
                                    <textarea class="form-control validateGroup1" name="paymentNote" value=""
                                        title="Please provide note" placeholder="Please provide note"></textarea>
                                    <span class="form-text text-muted"></span>
                                </div>
                            </div>
                            <div class="form-group row">
                                <label for="example-text-input" class="col-3 col-form-label">Upload Payment
                                    Proof</label>
                                <div class="col-2">
                                    <div class="kt-avatar" id="product_image" style="float: left; clear: left;">

                                        <div class="kt-avatar__holder"
                                            style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)">
                                        </div>

                                        <label class="kt-avatar__upload" data-toggle="kt-tooltip" title="Change Image">
                                            <i class="fa fa-pen"></i>
                                            <input type='file' name="payment_image"
                                                <?php echo config('app.accept_attr'); ?>
                                                <?php echo config('app.accept_msg'); ?> />
                                        </label>
                                        <span class="kt-avatar__cancel" data-toggle="kt-tooltip" title="Cancel Image">
                                            <i class="fa fa-times"></i> </span>
                                    </div>
                                </div>

                            </div>
                            <label style="font-weight: 500;">
                                <input id="updatePolicyStatus" type="checkbox" name="updatePolicyStatus" value="{{($policy->status==0) ? 0 : 1;}}"
                                @if ($policy->status == 1)
                                checked
                                @endif> Activate policy immediately?
                            </label>
                            <div class="form-group row">
                                <label for="example-text-input" class="col-3 col-form-label">Add payment on
                                    Realpay</label>
                                <div class="col-9">
                                    <span class="kt-switch">
                                        <label>
                                            <input id="vehicleValue" type="checkbox" name="addRealpay" value="1"
                                                onchange="vehicleMsg()">
                                            <span style="margin-top: 10px;margin-left: 10px;"></span>
                                            <h4 id="Msg"
                                                style="display:inline;float:left;margin-top: 14px;margin-left: 5px;color:#ff4d4d;">
                                                No</h4>

                                        </label>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="kt-portlet__body" id="paymentInfoDiv" style="margin-top:-50px !important;">
                            <div class="form-group row">
                                <label class="col-3 col-form-label">Instalment Start Date :</label>
                                <div class="col-9">
                                    <input type="text" class="form-control required kt_datepicker_123 validateGroup1"
                                        name="paymentStartDate" autocomplete="off"
                                        placeholder="Select payment start date" />
                                    <span class="form-text text-muted"></span>
                                </div>
                            </div>
                            <div class="form-group row">
                                <label class="col-3 col-form-label">Payment Frequency :</label>
                                <div class="col-9">
                                    <select id="paymentFreq" class="form-control required kt_selectpicker"
                                        title="Please select payment frequency" name="paymentFreq">
                                        <option value="1">Monthly Instalments</option>
                                        <option value="2">Three Instalments in a year</option>
                                        <option value="3">Annual Instalment</option>
                                    </select>
                                    <span class="form-text text-muted"></span>
                                </div>
                            </div>
                            <div class="form-group row">
                                <label class="col-3 col-form-label">Please select bank :</label>
                                <div class="col-9">
                                    <select id="RPBanks" class="form-control required kt_selectpicker"
                                        title="Please select bank" name="RPBanks">
                                        @foreach($banks as $bank)
                                        <option value="{{ $bank->bank_number }}">{{ $bank->bank_name }}</option>
                                        @endforeach

                                    </select>
                                    <span class="form-text text-muted"></span>
                                </div>
                            </div>
                            <div class="form-group row">
                                <label class="col-3 col-form-label">Please select bank branch :</label>
                                <div class="col-9">
                                    <select id="RPBankBranch" class="form-control required kt_selectpicker"
                                        title="Please select bank branch" name="RPBankBranch">

                                    </select>
                                    <span class="form-text text-muted"></span>
                                </div>
                            </div>
                            <input type="hidden" value="{{ $user->cellphone }}" name="billingCell" />
                            <div class="form-group row">
                                <label class="col-3 col-form-label">Account Type :</label>
                                <div class="col-9">
                                    <select id="RPBankBranch" class="form-control required kt_selectpicker"
                                        title="Please select account type" name="accountType">
                                        <option value="1">Cheque</option>
                                        <option value="2">Savings</option>
                                    </select>
                                    <span class="form-text text-muted"></span>
                                </div>
                            </div>
                            <div class="form-group row">
                                <label class="col-3 col-form-label">Account Number :</label>
                                <div class="col-9">
                                    <input class="form-control required validateGroup1" name="accountNumber" value=""
                                        title="Please provide account number"
                                        placeholder="Please provide account number">
                                    <span class="form-text text-muted"></span>
                                </div>
                            </div>

                        </div>
                        <div class="kt-portlet__foot kt-portlet__foot--solid">
                            <div class="kt-form__actions">
                                <div class="row">
                                    <div class="col-3"></div>
                                    <div class="col-9">
                                        <button type="submit" value="Submit" id="submitbtn"
                                            class="btn btn-brand">Submit</button>
                                        <button class="btn btn-brand" type="button" id="loadBtn" style="display:none">
                                            <span class="spinner-border spinner-border-sm" role="status"
                                                aria-hidden="true"></span> Loading... </button>
                                        <a class="btn btn-secondary" href="{{ route('admin.policy.index') }}">Cancel</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                    <!--end::Form-->

                </div>
            </div>

            {{-- Attachment --}}
            <div class="tab-pane" id="kt_portlet_base_demo_3_8_attachment_agent" role="tabpanel">
                @include('admin.policy.attachment')
            </div>
            <div class="tab-pane" id="kt_portlet_base_demo_3_8_sms_email_log" role="tabpanel">
                @include('admin.policy.sms_email_log')
            </div>
            <div class="tab-pane" id="kt_portlet_base_demo_3_8_discount_surcharge" role="tabpanel">
                @include('admin.policy.discount_surcharge_policy')
            </div>
            <div class="tab-pane" id="kt_portlet_base_demo_3_8_earned_premium" role="tabpanel">
                @include('admin.policy.policy_earned_premium')
            </div>
            @if(isset($scheduleTransactionCount) && $scheduleTransactionCount > 0)
                <div class="tab-pane" id="kt_portlet_scheduled_trasactions_logs" role="tabpanel">
                    @include('admin.policy.scheduledTrasactions')
                </div>

                <div class="tab-pane" id="kt_portlet_cancel_duplicate_scheduled_trasactions" role="tabpanel">
                    <div class="kt-portlet">
                        <!--begin::Form-->
                        <form id="cancelDPODuplicateTx" action="{{ route('admin.policy.cancelDPODuplicateTransactions') }}" method="POST"
                            enctype="multipart/form-data" class="kt-form">
                            <input type="hidden" name="policy_id" id="policyId" value="{{ $policy->id }}">
                            <!-- CSRF Token -->
                            <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                            <div class="kt-portlet__body">
                                <h3 class="kt-heading kt-heading--md kt-heading--no-top-margin">
                                    Cancel Scheduled Transactions
                                </h3>
                                <div class="row">
                                    <div class="col-lg-8">
                                        <div class="form-group">
                                            <label  class="col-3 col-form-label">Policy Number :</label>
                                            <div class="col-9">
                                                <input  class="form-control" name="policyNumber" title="Please provide policy number" value="" placeholder="Please provide policy number">
                                                <span class="form-text text-muted"></span>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label  class="col-3 col-form-label">Installment Range From :</label>
                                            <div class="col-9">
                                                <input  class="form-control" name="installment_range_from" title="Please provide Installment Range From" value="" placeholder="Please provide Installment Range From">
                                                <span class="form-text text-muted"></span>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label  class="col-3 col-form-label">Installment Range To :</label>
                                            <div class="col-9">
                                                <input  class="form-control" name="installment_range_to" title="Please provide Installment Range To" value="" placeholder="Please provide Installment Range To">
                                                <span class="form-text text-muted"></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="kt-portlet__foot">
                                <div class="kt-form__actions">
                                    <div class="row">
                                        <div class="col-5"></div>
                                        <div class="col-7">
                                            <button type="submit" value="Submit" class="btn btn-brand">Cancel</button>
                                            <a class="btn btn-secondary" href="{{ route('admin.policy.index') }}">Cancel</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                        <!--end::Form-->
                    </div>
                </div>
            @endif
            {{-- Assign Agent --}}
            <div class="tab-pane" id="kt_portlet_base_demo_3_8_assign_agent" role="tabpanel">
                <div class="kt-portlet">
                    <!--begin::Form-->
                    <form id="policyFormagent" action="{{ route('admin.policy.agentUpdate') }}" method="POST"
                        enctype="multipart/form-data" class="kt-form">
                        <input type="hidden" name="policy_id" id="policyId" value="{{ $policy->id }}">
                        <!-- CSRF Token -->
                        <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                        <div class="kt-portlet__body">
                            <h3 class="kt-heading kt-heading--md kt-heading--no-top-margin">
                                Assign Agent
                            </h3>
                            <div class="row">
                                <div class="col-lg-8">
                                    <div class="form-group">
                                        <select class="form-control kt_selectpicker" name="agent_id"
                                            data-live-search="true" title="Select Agent">
                                            @foreach($agents as $agent)
                                            <option value="{{$agent->id}}" @if($policy->agent_id
                                                == $agent->id) selected  @endif>
                                                {{ucwords($agent->firstName)}}  {{ucwords($agent->lastName)}} - {{$agent->id}}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="kt-portlet__foot">
                            <div class="kt-form__actions">
                                <div class="row">
                                    <div class="col-5"></div>
                                    <div class="col-7">
                                        <button type="submit" value="Submit" class="btn btn-brand">Update</button>
                                        <a class="btn btn-secondary" href="{{ route('admin.policy.index') }}">Cancel</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                    <!--end::Form-->
                </div>
            </div>

                            <div class="tab-pane" id="kt_portlet_base_demo_3_6_documents" role="tabpanel">
                                <div class="kt-portlet">
                                    <!--begin::Form-->
                                    <form id="sendDcument" action="{{ route('admin.policy.sendPolicyDocument',$policy->id) }}" method="POST" enctype="multipart/form-data" class="kt-form">
                                        <!-- CSRF Token -->
                                        <input type="hidden" name="_token" value="{{ csrf_token() }}" />

                                        <div class="kt-portlet__body">
                                            <div class="form-group row">
                                                <label for="example-text-input" class="col-2 col-form-label">Customer Email:</label>
                                                <div class="col-5">
                                                    <p style="font-weight:bold;margin-top:1.3%">{{ $user->email }}</p>
                                                </div>

                                                @if($product->id == 3)
                                                    <div class="col-3">
                                                        <select id="policy_terms" class="form-control required kt_selectpicker" title="Please select term" name="term">
                                                            @if(count($policy_term) > 0)
                                                                @foreach($policy_term as $key=>$data)
                                                                    <option value="{{ $data->id }}" data-id="{{ Carbon::parse($data->term_start_date)->year }}" @if($data->status == 'Active') selected @endif>{{ Carbon::parse($data->term_start_date)->year.'-'.Carbon::parse($data->term_end_date)->year}} @if($data->status == 'Active') <label style="color:green">(Active)</label> @endif</option>
                                                                @endforeach
                                                            @else
                                                                <option value='-1'>No term found</option>
                                                            @endif

                                                        </select>
                                                    </div>
                                                    <div claas="col-2">
                                                        <a id="termDocButton"class="btn btn-info" href="{{ route('admin.policy.index') }}">Generate Docs</a>
                                                    </div>
                                                @endif
                                            </div>

                                            <div class="form-group row">
                                                <label for="example-text-input" class="col-2 col-form-label">Attachments</label>
                                                <div class="col-10">
                                                    <p>Following documents will be sent to customers email.</p>
                                                    @foreach($emailDocs as $doc)
                                                        <span>=> <a target="_blank" href="{{ \AlphaDirect\Helper::getCloudFrontURL($doc->link) }}">{{ $doc->name }}</a></span><br>
                                                    @endforeach
                                                    @if($policy->policyDocument != null)
                                                        <span>=> <a target="_blank" href="{{ \AlphaDirect\Helper::getCloudFrontURL($policy->policyDocument) }}">Policy Schedule-{{ $policy->policyNumber }}-(V1)</a></span><br>
                                                    @endif
                                                </div>
                                            </div>

                                            @if(isset($policy->is_bundled) && $policy->is_bundled == 1)
                                            <div class="form-group row">
                                                <label for="example-text-input" class="col-2 col-form-label">Bundled Attachments</label>
                                                <div class="col-10">
                                                    <p>Following documents will be sent to customers email.</p>
                                                    @php $i = 1 @endphp
                                                    @foreach ($EmailDocsPolicyBundled as $bundledDoc)
                                                        <span>=> <a target="_blank"
                                                                href="{{ \AlphaDirect\Helper::getCloudFrontURL($bundledDoc->policyDocument) }}">Bundled Policy
                                                                Schedule-{{ $policy->policyNumber }}-({{$i}})</a></span><br>
                                                    @php $i++ @endphp
                                                    @endforeach
                                                </div>
                                            </div>
                                            @endif

                                            @if(auth::user()->hasPermissionTo('policy-Regenerate Policy Document'))
                                                <div class="form-group row">
                                                    <div class="col-2"></div>
                                                    <div class="col-3" style="display:inline;float:right">
                                                        <a class="btn btn-info" href="{{ route('admin.policy.regeneratePolicyDocument',$policy->id) }}">Regenerate Documents</a>
                                                    </div>
                                                     <div class="col-3" style="display:inline;float:right">
                                                        <a class="btn btn-success"
                                                            href="{{ route('admin.policy.reSendPolicyDocument', $policy->id) }}">Send Documents on Mail</a>
                                                    </div>
                                                </div>
                                            @endif

                                            {{-- customer verification document --}}
                                            <div class="form-group row">
                                                <label for="example-text-input" class="col-2 col-form-label">Information Verification Document</label>
                                                <div class="col-10">
                                                    <p>Following document will be sent to customers email.</p>
                                                    @if ($policy->verification_doc != null)
                                                        <span> <a target="_blank" class=""
                                                                href="{{ \AlphaDirect\Helper::getCloudFrontURL($policy->verification_doc) }}">Get Verification Document</a>
                                                        </span>
                                                    @endif
                                                </div>
                                            </div>

                                            <div class="form-group row">
                                                <div class="col-2"></div>
                                                <div class="col-3" style="display:inline;float:right">
                                                    <a class="btn btn-success"
                                                        href="{{ route('admin.policy.regenerateInformationDocument', $policy->id) }}">Regenerate Verification Document</a>
                                                </div>
                                            </div>

                                            <div class="form-group row">
                                                <label for="example-text-input" class="col-2 col-form-label">Policy Cancellation Note</label>
                                                <div class="col-10" style="margin-top:1%">
                                                    @if($cancelNote && $policy->status == 2)
                                                        <span> <a target="_blank" href="{{ \AlphaDirect\Helper::getCloudFrontURL($cancelNote->path) }}">Policy Cancellation Note</a></span><br>
                                                    @else
                                                        <span> - </span><br>
                                                    @endif
                                                </div>
                                            </div>

                                            <div class="form-group row">
                                                <label for="example-text-input" class="col-2 col-form-label">Policy Cover Note</label>
                                                <div class="col-10" style="margin-top:1%">
                                                    @if($coverNote && $policy->status == 1)
                                                        <span> <a target="_blank" href="{{ \AlphaDirect\Helper::getCloudFrontURL($coverNote->path) }}"> Policy Cover Note</a></span><br>
                                                    @else
                                                        <span> - </span><br>
                                                    @endif
                                                </div>
                                            </div>

                                        </div>
                                        <div class="kt-portlet__foot kt-portlet__foot--solid">
                                            <div class="kt-form__actions">
                                                <div class="row">
                                                    <div class="col-2"></div>
                                                    <div class="col-10">
                                                        <button type="submit" value="Submit" id="btn" class="btn btn-brand">Send</button>
                                                        <button class="btn btn-brand" type="button" id="loadBtn" style="display:none"> <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Loading... </button>
                                                        <a class="btn btn-secondary" href="{{ route('admin.policy.index') }}">Cancel</a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>


                                    </form>
                                    @if($product->id == 3)
                                        <form id="generateCoverNote" action="{{ route('admin.documents.generateCoverNote',$policy->id) }}" method="POST" enctype="multipart/form-data" class="kt-form">
                                            <!-- CSRF Token -->
                                            <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                                            <div class="kt-portlet__body">
                                                <div class="form-group row">
                                                    <label for="example-text-input" class="col-2 col-form-label">Generate Policy Note:</label>
                                                    <div class="col-6">
                                                        <select id="doc_type" class="form-control required kt_selectpicker" title="Please select document type" name="doc_type">
                                                            <option @if($policy->status == 1) enabled @else disabled @endif value="Cover">Policy Cover Document</option>
                                                            <option @if($policy->status == 2) enabled @else disabled @endif value="Cancel">Policy Cancel Document</option>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="kt-portlet__foot kt-portlet__foot--solid">
                                                <div class="kt-form__actions">
                                                    <div class="row">
                                                        <div class="col-2"></div>
                                                        <div class="col-10">
                                                            <button type="submit" value="Submit" id="btn" class="btn btn-brand">Generate</button>
                                                            <button class="btn btn-brand" type="button" id="loadBtn" style="display:none"> <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Loading... </button>
                                                            <a class="btn btn-secondary" href="{{ route('admin.policy.index') }}">Cancel</a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                        </form>
                                @endif
                                <!--end::Form-->

                                </div>
                                <div class="kt-portlet__body">
                                    <h5>Sent policy Documents</h5>
                                    <!--begin: Datatable -->
                                    <table class="table table-striped table-bordered table-hover table-checkable" id="document_table">
                                        <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Policy Number</th>
                                            <th>Email</th>
                                            <th>Email Type</th>
                                            <th>Documents</th>
                                            <th>Sent By</th>
                                            <th>Sent on</th>
                                        </tr>
                                        </thead>
                                    </table>
                                    <!--end: Datatable -->
                                </div>
                                <div class="kt-portlet__body">
                                    <h5>Policy Documents</h5>
                                    <!--begin: Datatable -->
                                    <table class="table table-striped table-bordered table-hover table-checkable" id="policy_document_table">
                                        <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Policy Number</th>
                                            <th>Term</th>
                                            <th>Documents</th>
                                            <th>File name</th>
                                            <th>Added by</th>
                                            <th>Created At</th>
                                        </tr>
                                        </thead>
                                    </table>
                                    <!--end: Datatable -->
                                </div>
                            </div>

                            <!-- Linked Policy -->
                            <div class="tab-pane" id="kt_portlet_base_demo_3_6_linked_policy" role="tabpanel">
                                <div class="kt-portlet__body">
                                    <!--begin: Datatable -->
                                    <table class="table table-striped table-bordered table-hover table-checkable" id="linked_policy_table">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Policy Number</th>
                                                <th>Customer Name</th>
                                                <th>Agent Name</th>
                                                <th>CellPhone Number</th>
                                                <th>Product</th>
                                                <th>Payment Method</th>
                                                <th>Payment Reference</th>
                                                <th>Vehicle Plate</th>
                                                <th>Status</th>
                                                <th>Created At</th>
                                                <!-- <th>Actions</th> -->
                                            </tr>
                                        </thead>
                                    </table>
                                    <!--end: Datatable -->
                                </div>
                            </div>

                            @if(\AlphaDirect\PaymentTransaction::where('policyNumber',$policy->policyNumber)->where('paymentMethod','DPO')->exists())
                                <div class="tab-pane" id="kt_portlet_base_demo_3_6_DPO_Payment" role="tabpanel">
                                    <div class="kt-portlet__body">
                                        <!--begin: Datatable -->
                                        <table class="table table-striped table-bordered table-hover table-checkable" id="DPOPaymenttable">
                                            <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>POLICY NUMBER</th>
                                                <th>AMOUNT</th>
                                                <th>API NAME</th>
                                                <th>REQUEST JSON</th>
                                                <th>RESPONSE JSON</th>
                                                <th>COMPANY ACC REF</th>
                                                <th>TRANS TOKEN</th>
                                                <th>REASON</th>
                                                <th>CREATED AT</th>
                                            </tr>

                                            </thead>
                                        </table>
                                        <!--end: Datatable -->
                                    </div>
                                </div>
                            @endif

    @if ($product->id == 3)
             <div class="tab-pane" id="kt_portlet_base_demo_3_7_rerate_premium" role="tabpanel">
                      @include('admin.policy.rerate_premium')
            </div>
    @endif

    <div class="tab-pane" id="kt_portlet_base_demo_3_7_change_policy_frequency" role="tabpanel">
        @include('admin.policy.changePolicyFrequency')
    </div>

    <div class="tab-pane" id="kt_portlet_base_demo_3_6_transaction_log" role="tabpanel">
        {{--Transaction logs--}}
        <div class="kt-portlet__body">
            <!--begin: Datatable -->
            @if (isset($policy) && $policy->status != 0)
                <div class="row">
                    <div class="col-12 py-3">
                    <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#refundModal">
                        Refund Money</button>
                    </div>
                </div>
                <!-- refund money -->
                <div class="modal" id="refundModal" tabindex="-1" role="dialog">
                    <div class="modal-dialog"  role="document">
                        <div class="modal-content">
                            <form action="{{ route('admin.policy.makeRefund') }}" method="post" id="refundForm">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="refundModalTitle">Refund Money</h5>
                                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                                <div class="modal-body">
                                    @csrf
                                    <input type="hidden" name="policy_number" id="policyNumber" value="{{ $policy->policyNumber }}">
                                    <div class="form-group col-12">
                                        <label for="reference_number">Reference Number</label>
                                        <input type="text" class="form-control text-uppercase" name="reference_number"
                                            id="reference_number" maxlength="30" minlength="1">
                                    </div>

                                @if(\Illuminate\Support\Facades\Auth::user()->hasPermissionTo('back-dated-transactions'))
                                    <div class="form-group col-12">
                                        <label for="date_of_refund">Date of refund</label>
                                        <input type="text" class="form-control kt_datepicker_1" name="date_of_refund"
                                            id="date_of_refund" autocomplete="off">
                                    </div>
                                @else
                                    <p>Note : You can select Date of Payment for current month.</p>
                                    <div class="form-group col-12">
                                        <label for="date_of_refund">Date of refund</label>
                                        <input type="text" class="form-control" name="date_of_refund"
                                            id="date_of_refund_for_current_month_refund" autocomplete="off">
                                    </div>
                                @endif

                                    <div class="form-group col-12">
                                        <label for="amount">Amount</label>
                                        <input type="number" step="0.01" class="form-control" name="amount" id="amount" min="1">
                                    </div>
                                    <div class="form-group col-12">
                                        <label for="reason">Reason </label>
                                        <textarea class="form-control" name="reason" id="reason" rows="2" cols="8" maxlength="300"
                                            required></textarea>

                                    </div>
                                    <div class="form-group col-12">
                                        <label for="refunded_by">Refunded by</label>
                                        <input type="text" class="form-control" name="refunded_by" id="refunded_by" maxlength="30"
                                            minlength="1">

                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                    <button type="submit" class="btn btn-primary">Save changes</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            @endif
            <div class="kt-portlet__body">
            <!--begin: Datatable -->
        <div class="row">
            <div class="col-4 mt-2">
                           <h5 class="h5">Success Transaction Count :
                                @php
                                $paytrx_graphite = \AlphaDirect\PaymentTransaction::where('policyNumber',$policy->policyNumber)
                                                        ->whereIn('status',['Success','SUCCESS','success',1])->get();
                                $paytrx_archive = \AlphaDirect\Models\PaymentTransactionArchive::where('policyNumber',$policy->policyNumber)
                                                        ->whereIn('status',['Success','SUCCESS','success',1])->get();

                                $merged = $paytrx_archive->merge($paytrx_graphite);
                                $trxn = $merged->all();
                                @endphp
                                @if(isset($trxn) && count($trxn) > 0 )
                                {{ count($trxn) }}
                                @else
                                {{ '0' }}
                                @endif
                                </h5>


           </div>
           <div class="col-4 mt-2">
                <h5 class="h5">Total Successful Transactions In Amount : P @if(isset($trxn) && count($trxn) > 0 )
                                @php
                                $AmountA = 0.0; // Initialize $Amount as a float
                                    foreach($trxn as $trxnA){
                                        $AmountA += floatval($trxnA->amount);
                                    }
                                @endphp
                                {{ $AmountA }}
                                @else
                                {{ '0.00' }}
                                @endif </h5>
         </div>
         </div>
     <div class="row">
     @php
                                $paytrx_graphiteF = \AlphaDirect\PaymentTransaction::where('policyNumber',$policy->policyNumber)
                                                        ->whereNotIn('status',['Success','SUCCESS','success',1])->get();
                                $paytrx_archiveF = \AlphaDirect\Models\PaymentTransactionArchive::where('policyNumber',$policy->policyNumber)
                                                        ->whereNotIn('status',['Success','SUCCESS','success',1])->get();

                                $mergeF = $paytrx_archiveF->merge($paytrx_graphiteF);
                                $trxnF = $mergeF->all();
                            @endphp
     <div class="col-4 mt-2">
     <h5 class="h5">Failed Transaction Count :  @if(isset($trxnF) && count($trxnF) > 0 )
                                {{ count($trxnF) }}
                                @else
                                {{ '0' }}
                                @endif
     </h5>
     </div>
         <div class="col-4 mt-2">
                          
                          <h5 class="h5">Total Failed Transactions In Amount: P @if(isset($trxnF) && count($trxnF) > 0 )
                                 @php
                                $AmountF = 0.0; // Initialize $Amount as a float
                                    foreach($trxnF as $trxnFA){
                                        $AmountF += floatval($trxnFA->amount);
                                    }
                                @endphp
                                {{ $AmountF }}
                                @else
                                {{ '0.00' }}
                                @endif
                       <h5>
                    </div>
     </div>
     <div class="row">
            <div class="col-4 mt-2">
                           <h5 class="h5">Total Refunded Transactions Count:


                                @php
                                $paytrx_graphiteRe = \AlphaDirect\PaymentTransaction::where('policyNumber',$policy->policyNumber)
                                                        ->whereIn('status',['Success','SUCCESS','success',1])->where('is_refund',1)->get();
                                $paytrx_archiveRe = \AlphaDirect\Models\PaymentTransactionArchive::where('policyNumber',$policy->policyNumber)
                                                        ->whereIn('status',['Success','SUCCESS','success',1])->where('is_refund',1)->get();

                                $mergedRe = $paytrx_archiveRe->merge($paytrx_graphiteRe);
                                $trxnRE = $mergedRe->all();
                                @endphp
                                @if(isset($trxnRE) && count($trxnRE) > 0 )
                                {{ count($trxnRE) }}
                                @else
                                {{ '0' }}
                                @endif
                              </h5>
        </div>


         <div class="col-4 mt-2">
                              <h5 class="h5">Total Refund Transactions In Amount : P @if(isset($trxnRE) && count($trxnRE) > 0 )
                                @php
                                $AmountRe = 0.0; // Initialize $Amount as a float
                                    foreach($trxnRE as $trxnREA){
                                        $AmountRe += floatval($trxnREA->amount);
                                    }
                                @endphp
                                {{ $AmountRe }}
                                @else
                                {{ '0.00' }}
                                @endif </h5>

           </div>
    </div>
   <br><hr>
    <div class="row">



        @if ( isset($trxn) && count($trxn) > 0 && ( $policy->status == 0 || $policy->status == null )  && auth::user()->hasPermissionTo('ActivatePolicy') )

            <div class="col-3 mt-2">
                <button class="btn btn-primary m-3" id="ActivatePolicy">Activate Policy Now</button>
            </div>
            <br><hr>
        @endif

    </div>
    <div class="row">
                 <div class="col-3 mt-2">
                    <label class="">Filter By Payment Status</label>

                    <select name="FilterBy" class="form-control" id="FilterBy">
                        <option value="-1">All</option>
                        <option  value="success">Success</option>
                        <option  value="failed">Failed</option>

                    </select>

                </div>
                  <div class="col-3 mt-2">

                    <label class="">Filter By Refund Payment</label>

                        <select name="FilterByRefund" class="form-control" id="FilterByRefund">
                            <option value="-1">All</option>
                            <option  value="1">is Refund</option>
                        </select>

                    </div>
        </div>
        <div class="row">
                    <div class="col-3 mt-2">
                        <label>Filter By  date From</label>
                            <input type="text" class="form-control  trasectiondate restrictDate"  id="filterDateFrom" placeholder="Select date" name="filterDateFrom" autocomplete="off">
                    </div>
                    <div class="col-3 mt-2">
                        <label>Filter By date To</label>
                        <input type="text" class="form-control  trasectiondate restrictDate"  id="filterDateTo" placeholder="Select date" name="filterDateTo" autocomplete="off">
                    </div>
                    <div class="col-3" style="margin-top: 18px !important;">
                    <button class="btn btn-primary m-3" id="Filterdate">Submit</button>

                    </div>

            </div>
            <br><hr>
            <br>
        </div>
            <!--begin: Datatable -->
            <table class="table table-striped table-bordered table-hover table-checkable" id="transaction_table">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Policy Number</th>
                    <th>Contract Number</th>
                    <th>Reference Number</th>
                    <th>Payment Method</th>
                    <th>Payment Frequency</th>
                    <th>Amount</th>
                    <th>Payment Date</th>
                    <th>Payment Settlement Date</th>
                    <th>Is Ledger</th>
                    <th>No. of Installments Paid</th>
                    <th>Note</th>
                    <th>Status</th>
                    <th>Payment Recieved By</th>
                    <th>Payment Added By</th>
                    <th>Actions</th>
                </tr>
                </thead>
            </table>
            <!--end: Datatable -->
        </div>
    </div>
    <div class="tab-pane" id="kt_portlet_base_demo_3_4_actionlog_content" role="tabpanel">
        <div class="kt-portlet__body">
            <!--begin: Datatable -->
            <!--begin: Datatable -->
            <table class="table table-striped table-bordered table-hover table-checkable" id="activity_table">
                <thead>
                    <tr>
                        <th>Id</th>
                        <th>Activity By</th>
                        <th>IP address</th>
                        <th>Done from</th>
                        <th>Activity Tag</th>
                        <th>Url</th>
                        <th>Old Values</th>
                        <th>New data</th>
                        <th>Activity Done</th>
                    </tr>
                </thead>
            </table>
            <!--end: Datatable -->

        </div>
        <div class="kt-portlet__foot">
            <div class="row">
                <div class="col-12">
                    <a class="btn btn-secondary" href="{{ route('admin.policy.index') }}">Cancel</a>
                </div>
            </div>
        </div>
    </div>
    <div class="tab-pane" id="kt_portlet_base_demo_3_5_bank_content" role="tabpanel">
        @if($banking && $banking->billing != null)
        <div class="kt-portlet__head">
            <div class="kt-portlet__head-label">
                <h3 class="kt-portlet__head-title">
                    Banking Details
                </h3>
            </div>
        </div>
        <div class="kt-portlet_body">
            <div class="kt-section">
                <div class="kt-section__content" style="padding-left:20px; padding-right: 20px;">
                    <table class="table table-striped m-table">
                        <tbody>
                            <tr>
                                <th>Billing Method</th>
                                @if($banking->billing == "VCS")
                                <td>Credit/Debit Card Processing</td>
                                @elseif($banking->billing == "Orange")
                                <th>Orange Money</th>
                                @elseif($banking->billing == "RealPay")
                                <th>RealPay</th>
                                @elseif($banking->billing == "orangeMoney")
                                <th>Orange Money</th>
                                @else
                                <td>Debit Order Solution</td>
                                @endif
                                <th>Account Type</th>
                                @if($banking->accountType == 1)
                                <td>Cheque</td>
                                @else
                                <td>Savings</td>
                                @endif
                            </tr>

                            @if($banking->billing != "VCS")
                            <tr>
                                <th>Bank Name</th>
                                <td>@if ($banking->billing == "RealPay")
                                    {!! $banking->bank_name !!}
                                    @else
                                    {!! $banking->bankName !!}
                                    @endif</td>
                                <th>Branch Code</th>
                                <td>@if ($banking->billing == "RealPay")
                                    {!! $banking->bank_branch !!}
                                    @else
                                    {!! $banking->branchCode !!}
                                    @endif</td>
                            </tr>
                            <tr>
                                <th>Account Number</th>
                                <td>{!! $banking->accountNumber !!}</td>
                                <th>Myzaka/Orange Money Cell</th>
                                <td>
                                    @if($banking && $banking->billingCell != NULL )
                                    {!! $banking->billingCell !!}
                                    @else
                                    N/A
                                    @endif
                                </td>
                            </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
            @endif
            <div class="kt-portlet__foot">
                <div class="row">
                    <div class="col-12">
                        <a class="btn btn-secondary" href="{{ route('admin.policy.index') }}">Cancel</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </div>
    </div>
    </div>
    <!-- end:: Content -->
    </div>
    <!-- begin:: Footer -->

    @include('includes.footer')
    <!-- end:: Footer -->
    </div>
    <!-- end:: Wrapper -->
    </div>
    <!-- end:: Page -->
    </div>
    <!-- end:: Root -->

    <!-- begin:: Scrolltop -->
    <div id="kt_scrolltop" class="kt-scrolltop"> <i class="la la-arrow-up"></i> </div>
    <!-- end:: Scrolltop -->

    <div class="modal fade" id="myModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel"
        aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-body">
                <div class="modal-content">
                    <div class="modal-header">
                        <h4 class="modal-title" id="myModalLabel">Cancel Your Policy</h4>
                        <button type="button" class="close" data-dismiss="modal"
                            aria-hidden="true">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div>
                            <h4>Your feedback is important to us. Please fill the following:</h4>
                            <div class="form-widget" data-loader="button">
                                <div class="form-result"></div>
                                <form class="nobottommargin position-relative row" id="modal-feedback"
                                    name="modal-feedback" action="{{ route('admin.policy.feedback') }}"
                                    method="post">
                                    {{ csrf_field() }}
                                    <input type="hidden" value="{{ $policy->customer_id }}" id="customerIdFeedback"
                                        name="customerIdFeedback">
                                    <input type="hidden" value="{{ $policy->product_id }}" id="productIdFeedback"
                                        name="productIdFeedback">
                                    <input type="hidden" value="{{ $policy->id }}" id="policyIdFeedback"
                                        name="policyIdFeedback">
                                    <div class="form-process"></div>
                                    <div class="col-12 form-group">
                                        <div class="list-group" id="feedbackOptionDiv">
                                            {{-- <div class="list-group-item">
                                            <div class="custom-control custom-radio">
                                                <input type="radio" class="custom-control-input" name="reason"
                                                    id="modal-feedback-cleanliness-1" value="cantAfford"></i>
                                                <label class="custom-control-label mb-0 ml-2 nott ls0"
                                                    for="modal-feedback-cleanliness-1">I can't afford insurance
                                                    anymore</label>
                                            </div>
                                            <div class="option1" hidden>
                                                <h5>Please explain what changed in your circumstances</h5>
                                                <div class="list-group-item">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="radio"
                                                            name="circum" id="gridRadios1" value="lostjob">
                                                        <label class="form-check-label" for="gridRadios1"
                                                            style="text-transform: capitalize;">
                                                            Lost Job
                                                        </label>
                                                    </div>
                                                </div>
                                                <div class="list-group-item">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="radio"
                                                            name="circum" id="gridRadios2" value="nomoney">
                                                        <label class="form-check-label" for="gridRadios2"
                                                            style="text-transform: capitalize;">
                                                            No Money
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="list-group-item">
                                            <div class="custom-control custom-radio">
                                                <input type="radio" class="custom-control-input" name="reason"
                                                    id="modal-feedback-cleanliness-2" value="cheaper">
                                                <label class="custom-control-label mb-0 ml-2 nott ls0"
                                                    for="modal-feedback-cleanliness-2">I found cheaper insurance
                                                    else where</label>
                                            </div>
                                            <div class="col-12 form-group option2" hidden>
                                                <h5>Please tell us which Insurance company you are moving to</h5>
                                                <!-- <label>Insurance Companies</label> -->
                                                <select class="form-control" id="exampleFormControlSelect1"
                                                        name="otherCompany">
                                                    <option value="">Please Select</option>
                                                    <option
                                                            value="B.I.C.B Limited t/a Bryte Risk Services Botswana">
                                                        B.I.C.B Limited t/a Bryte Risk Services Botswana
                                                    </option>
                                                    <option
                                                            value="BIHL Insurance Company Limited t/a Legal Guard">
                                                        BIHL Insurance Company Limited t/a Legal Guard</option>
                                                    <option value="Botswana Insurance Company Limited">Botswana
                                                        Insurance Company Limited</option>
                                                    <option
                                                            value="Export Credit Insurance & Guarantee Company (Botswana) (Pty) Ltd">
                                                        Export Credit Insurance & Guarantee Company (Botswana)
                                                        (Pty) Ltd</option>
                                                    <option value="Liberty General Botswana (Pty) Ltd">Liberty
                                                        General Botswana (Pty) Ltd</option>
                                                    <option
                                                            value="Old Mutual Short-Term Insurance (Botswana) Limited">
                                                        Old Mutual Short-Term Insurance (Botswana) Limited
                                                    </option>
                                                    <option
                                                            value="Phoenix of Botswana Assurance Company (Pty) Ltd">
                                                        Phoenix of Botswana Assurance Company (Pty) Ltd</option>
                                                    <option value="Sesiro Insurance Company (Pty) Ltd">Sesiro
                                                        Insurance Company (Pty) Ltd</option>
                                                    <option
                                                            value="Sunshine Insurance Company of Botswana (Pty) Ltd">
                                                        Sunshine Insurance Company of Botswana (Pty) Ltd
                                                    </option>
                                                    <option
                                                            value="The Hollard Insurance Company of Botswana (Pty) Ltd">
                                                        The Hollard Insurance Company of Botswana (Pty) Ltd
                                                    </option>
                                                    <option value="Western Insurance Botswana (Pty) Ltd">Western
                                                        Insurance Botswana (Pty) Ltd</option>
                                                    <option
                                                            value="B.I.C.B Limited t/a Bryte Risk Services Botswana">
                                                        B.I.C.B Limited t/a Bryte Risk Services Botswana
                                                    </option>
                                                    <option value="Botswana Insurance Company Limited">Botswana
                                                        Insurance Company Limited</option>
                                                    <option value="Liberty General Botswana (Pty) Ltd">Liberty
                                                        General Botswana (Pty) Ltd</option>
                                                    <option
                                                            value="Old Mutual Short-Term Insurance (Botswana) Limited">
                                                        Old Mutual Short-Term Insurance (Botswana) Limited
                                                    </option>
                                                    <option
                                                            value="Phoenix of Botswana Assurance Company (Pty) Ltd">
                                                        Phoenix of Botswana Assurance Company (Pty) Ltd</option>
                                                    <option value="Sesiro Insurance Company (Pty) Ltd">Sesiro
                                                        Insurance Company (Pty) Ltd</option>
                                                    <option
                                                            value="Sunshine Insurance Company of Botswana (Pty) Ltd">
                                                        Sunshine Insurance Company of Botswana (Pty) Ltd
                                                    </option>
                                                    <option
                                                            value="The Hollard Insurance Company of Botswana (Pty) Ltd">
                                                        The Hollard Insurance Company of Botswana (Pty) Ltd
                                                    </option>
                                                    <option value="Western Insurance Botswana (Pty) Ltd">Western
                                                        Insurance Botswana (Pty) Ltd</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="list-group-item">
                                            <div class="custom-control custom-radio">
                                                <input type="radio" class="custom-control-input option3"
                                                    name="reason" id="modal-feedback-cleanliness-3"
                                                    value="notHappy">
                                                <label class="custom-control-label mb-0 ml-2 nott ls0"
                                                    for="modal-feedback-cleanliness-3">I am not happy with your
                                                    service</label>
                                            </div>
                                        </div>
                                        <div class="list-group-item">
                                            <div class="custom-control custom-radio">
                                                <input type="radio" class="custom-control-input" name="reason"
                                                    id="modal-feedback-cleanliness-4" value="other">
                                                <label class="custom-control-label mb-0 ml-2 nott ls0"
                                                    for="modal-feedback-cleanliness-4">Other</label>
                                                <textarea class="form-control option4" name="other_reason"
                                                        id="exampleFormControlTextarea1" rows="3" hidden></textarea>
                                            </div>
                                        </div> --}}
                                        </div>
                                    </div>
                                    {{-- <div class="col-12 hidden"> --}}
                                    {{-- <input type="text" id="modal-feedback-botcheck" --}}
                                    {{-- name="modal-feedback-botcheck" value="" /> --}}
                                    {{-- </div> --}}
                                    <div class="col-6">
                                        <button type="submit" name="modal-feedback-submit" id="modal-feedback-submit"
                                            class="btn btn-block" style="color: white;
        background-color: #403F86;">Cancel Policy</button>
                                    </div>
                                    <div class="col-6">
                                        <a name="modal-feedback-cancel" id="modal-feedback-cancel"
                                            class="btn btn-block"
                                            style="color: white; background-color: #F08021;">Close</a>
                                    </div>
                                    <input type="hidden" name="prefix" value="modal-feedback-">
                                    <input type="hidden" name="subject" value="New Feedback Received">
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="myMoveToRenewModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel"
        aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-body">
                <div class="modal-content">
                    <div class="modal-header">
                        <h4 class="modal-title" id="myModalLabel">Move to Renew Table</h4>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close"> <span aria-hidden="true">&times;</span> </button>
                    </div>
                    <div class="modal-body">
                        <div>
                            <h4>Are you sure want to renew policy?</h4>
                            <div class="form-widget" data-loader="button">
                                <div class="form-result" style="padding-top: 25px;"></div>
                                <form class="nobottommargin position-relative row" id="modal-feedback" name="modal-feedback"
                                    action="{{ route('admin.policy.renew_policy') }}"  method="post">
                                    {{ csrf_field() }}
                                    <input type="hidden" value="{{ $policy->customer_id }}" id="customerId"
                                        name="customerId">
                                    <input type="hidden" value="{{ $policy->product_id }}" id="productId"
                                        name="productId">
                                    <input type="hidden" value="{{ $policy->id }}" id="policyId"
                                        name="policyId">


                                    <div class="col-6">
                                        <button type="submit" name="modal-feedback-submit" id="modal-feedback-submit"
                                            class="btn btn-block" style="color: white;  background-color: #403F86;">Move</button>
                                    </div>
                                    <div class="col-6">
                                        <a  type="button" data-dismiss="modal" name="modal-feedback-cancel" id="modal-feedback-cancel"
                                            class="btn btn-block"   style="color: white; background-color: #F08021;">Close</a>
                                    </div>
                                    <input type="hidden" name="prefix" value="modal-feedback-">
                                    <input type="hidden" name="subject" value="New Feedback Received">
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="ledgerInvoiceDeleteModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel"
        aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-body">
                <div class="modal-content">
                    <div class="modal-header">
                        <h4 class="modal-title" id="myModalLabel">Ledger Invoice</h4>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close"> <span aria-hidden="true">&times;</span> </button>
                    </div>
                    <div class="modal-body">
                        <div>
                            <h4>Are you sure want to reverse the Invoice?</h4>
                            <div class="form-widget" data-loader="button">
                                <div class="form-result" style="padding-top: 25px;"></div>
                                <form class="nobottommargin position-relative row" id="modal-feedback" name="modal-feedback"
                                    action="{{ route('admin.policy.ledger_invoice_delete') }}"  method="post">
                                    {{ csrf_field() }}
                                    <input type="hidden" value="{{ $policy->customer_id }}" id="customerId"
                                        name="customerId">
                                    <input type="hidden" value="{{ $policy->product_id }}" id="productId"
                                        name="productId">
                                    <input type="hidden" value="{{ $policy->id }}" id="policyId"
                                        name="policyId">
                                    <input type="hidden" value="" id="ledgerInvoiceId" name="ledgerInvoiceId">

                                    <div class="col-6">
                                        <button type="submit" name="modal-feedback-submit" id="modal-feedback-submit"
                                            class="btn btn-block" style="color: white;  background-color: #403F86;">Reverse</button>
                                    </div>
                                    <div class="col-6">
                                        <a  type="button" data-dismiss="modal" name="modal-feedback-cancel" id="modal-feedback-cancel"
                                            class="btn btn-block" style="color: white; background-color: #F08021;">Close</a>
                                    </div>
                                    <input type="hidden" name="prefix" value="modal-feedback-">
                                    <input type="hidden" name="subject" value="New Feedback Received">
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="transactionLogDeleteModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-body">
                <div class="modal-content">
                    <div class="modal-header">
                        <h4 class="modal-title" id="myModalLabel">Transaction Log</h4>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close"> <span aria-hidden="true">&times;</span> </button>
                    </div>
                    <div class="modal-body">
                        <div>
                            <h4>Are you sure want to reverse the Transaction ?</h4>
                            <div class="form-widget" data-loader="button">
                                <div class="form-result" style="padding-top: 25px;"></div>
                                <form class="nobottommargin position-relative row" id="modal-feedback" name="modal-feedback"
                                    action="{{ route('admin.policy.transaction_log_delete') }}"  method="post">
                                    {{ csrf_field() }}
                                    <input type="hidden" value="{{ $policy->customer_id }}" id="customerId"
                                        name="customerId">
                                    <input type="hidden" value="{{ $policy->product_id }}" id="productId"
                                        name="productId">
                                    <input type="hidden" value="{{ $policy->id }}" id="policyId"
                                        name="policyId">
                                    <input type="hidden" value="" id="transactionLogId" name="transactionLogId">

                                    <div class="col-6">
                                        <button type="submit" name="modal-feedback-submit" id="modal-feedback-submit"
                                            class="btn btn-block" style="color: white;  background-color: #403F86;">Reverse</button>
                                    </div>
                                    <div class="col-6">
                                        <a type="button" data-dismiss="modal" name="modal-feedback-cancel" id="modal-feedback-cancel"
                                            class="btn btn-block"  style="color: white; background-color: #F08021;">Close</a>
                                    </div>
                                    <input type="hidden" name="prefix" value="modal-feedback-">
                                    <input type="hidden" name="subject" value="New Feedback Received">
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="transactionLogDeleteModal2" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-body">
                <div class="modal-content">
                    <div class="modal-header">
                        <h4 class="modal-title" id="myModalLabel2">Transaction Log</h4>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close"> <span aria-hidden="true">&times;</span> </button>
                    </div>
                    <div class="modal-body">
                        <div>
                            <h4>Are you sure want to reverse the Transaction ?</h4>
                            <div class="form-widget" data-loader="button">
                                <div class="form-result" style="padding-top: 25px;"></div>
                                <form class="nobottommargin position-relative row" id="modal-feedback2" name="modal-feedback"
                                    action="{{ route('admin.policy.transaction_log_delete2') }}"  method="post">
                                    {{ csrf_field() }}
                                    <input type="hidden" value="{{ $policy->customer_id }}" id="customerId"
                                        name="customerId">
                                    <input type="hidden" value="{{ $policy->product_id }}" id="productId"
                                        name="productId">
                                    <input type="hidden" value="{{ $policy->id }}" id="policyId"
                                        name="policyId">
                                    <input type="hidden" value="" id="transactionLogId2" name="transactionLogId">

                                    <div class="col-6">
                                        <button type="submit" name="modal-feedback-submit" id="modal-feedback-submit"
                                            class="btn btn-block" style="color: white;  background-color: #403F86;">Reverse</button>
                                    </div>
                                    <div class="col-6">
                                        <a type="button" data-dismiss="modal" name="modal-feedback-cancel" id="modal-feedback-cancel2"
                                            class="btn btn-block"  style="color: white; background-color: #F08021;">Close</a>
                                    </div>
                                    <input type="hidden" name="prefix" value="modal-feedback-">
                                    <input type="hidden" name="subject" value="New Feedback Received">
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="purposeModal" tabindex="-1" role="dialog" aria-labelledby="user_delete_confirm_title"
        aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content" id="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">Transport Policy</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"> <span
                            aria-hidden="true">&times;</span> </button>
                </div>
                <div class="modal-body">
                    <h6>We are not providing Transport Vehicle Policy for Now. Sorry, fot the In-convenience caused.
                    </h6>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-brand" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    @if (auth::user()->hasPermissionTo('policy-add_schedule_transaction') )
    <div class="modal fade" tabindex="-1" role="dialog" id="addScheduleTransactionModel">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <!-- Modal -->
                <form action=" {{ route('admin.policy.addScheduleTransaction', ['policyNumber' => $policy->policyNumber]) }}" method="POST" id="addScheduleTransactionForm">
                    <div class="modal-header">
                        <h5 class="modal-title" id="exampleModalLabel">Add schedule transaction</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        {{ csrf_field() }}
                        <div class="form-group">
                            <label for="amount">Amount</label>
                            <input type="text" class="form-control" onchange="validateFloatKeyPress(this);" name="schedule_amount" id="schedule_amount" placeholder="Please enter amount" required>
                        </div>
                        <div class="form-group">
                            <label for="billing_date">Billing date</label>
                            <input type="text" class="form-control kt_datepicker_1" name="schedule_billing_date" id="schedule_billing_date" placeholder="Please enter billing date" required>
                        </div>
                        {{-- <div class="form-group">
                            <label for="installment">Installment number</label>
                            <input type="number" class="form-control" name="schedule_installment" id="schedule_installment" placeholder="Please enter installment" min="0">
                        </div> --}}
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                        <button class="btn btn-warning" type="reset">Reset</button>
                        <button type="submit" class="btn btn-primary" id="submitAddScheduleTransaction">Submit</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif

    @if (auth::user()->hasPermissionTo('policy-update_billng_date_schedule_transaction') )
    <div class="modal fade" tabindex="-1" role="dialog" id="updateBillingDateScheduleTransactionModel">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <!-- Modal -->
                <form action=" {{ route('admin.policy.updateBillingDateScheduleTransaction') }}" method="POST" id="updateBillingDateScheduleTransactionForm">
                    <div class="modal-header">
                        <h5 class="modal-title" id="exampleModalLabel">Update billing date schedule transaction</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        {{ csrf_field() }}
                        <input type="hidden" name="policy_number" value="{{ $policy->policyNumber }}">
                        <input type="hidden" name="product_id" value="{{ $policy->product_id }}">
                        <input type="hidden" name="premium_freq" value="{{ isset($policy->premium_freq) ? $policy->premium_freq : 1 }}">
                        <div class="form-group">
                            <label for="billing_day">Billing Dayyy</label>
                            <select id="billing_day_" name="billing_day" class="form-control" required>
                                <option value="">Select billing day</option>
                                @for ($i = 1; $i < 29; $i++)
                                <option value="{{ $i }}">{{ $i }}</option>
                                @endfor
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                        <button class="btn btn-warning" type="reset">Reset</button>
                        <button type="submit" class="btn btn-primary" id="submitAddScheduleTransaction">Submit</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif

    @if (auth::user()->hasPermissionTo('policy-add_new_realpay_installment_data') )
    <div class="modal fade" tabindex="-1" role="dialog" id="addRealpayInstallmentModel">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <!-- Modal -->
                <form id="addNewRealpayInstallmentForm" action="{{ route('admin.policy.addNewRealpayInstallment')}}" method="POST">
                    {{csrf_field()}}
                    <div id="addRealpayInstallmentForm">
                        <div class="modal-header">
                            <h5 class="modal-title" id="exampleModalLabel">Add Realpay Installment</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            {{ csrf_field() }}
                            <input type="hidden" name="policy_number" value="{{ $policy->policyNumber }}">
                            <input type="hidden" name="product_id" value="{{ $policy->product_id }}">
                            <input type="hidden" name="policy_id" value="{{ $policy->id }}">
                            <input type="hidden" name="premium_freq" value="{{ isset($policy->premium_freq) ? $policy->premium_freq : 1 }}">
                            <input type="hidden" name="add_realpay_contract_sequence" value="{{ isset($getContracts->ContractSequence) ? $getContracts->ContractSequence : null }}">
                            <div class="form-group">
                                <label for="client_number">Client Number</label>
                                <input type="text" class="form-control" value="{{ isset($getContracts->ClientNumber) ? $getContracts->ClientNumber : null }}" name="add_realpay_client_number" id="add_realpay_client_number" placeholder="Please enter client number" readonly>
                            </div>
                            <div class="form-group">
                                <label for="contract_number">Contract Number</label>
                                <input type="text" class="form-control" value="{{ isset($getContracts->ContractNumber) ? $getContracts->ContractNumber : null }}" name="add_realpay_contract_number" id="add_realpay_contract_number" placeholder="Please enter contract number" readonly>
                            </div>
                            <div class="form-group">
                                <label for="billing_day">Installment Date</label>
                                <input type="text" class="form-control kt_datepicker_today_1" name="add_realpay_installment_date" id="add_realpay_installment_date" placeholder="Please enter installment date">
                                <span style="color: red; display:none" class="add_realpay_instl_date_error" id="add_realpay_instl_date_error">Installment Date is required</span>
                            </div>
                            <div class="form-group">
                                <label for="premium_label">Installment Premium</label>
                                <input type="text" class="form-control" name="add_realpay_installment_premium" id="add_realpay_installment_premium" placeholder="Please enter installment premium">
                                <span style="color: red; display:none" class="add_realpay_instl_premium_error" id="add_realpay_instl_premium_error">Installment Premium is required</span>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                            <button class="btn btn-warning" type="reset">Reset</button>
                            <button type="submit" class="btn btn-primary" id="submitAddRealpayInstallment">Submit</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif

    @if (auth::user()->hasPermissionTo('policy-update_realpay_installment_data') )
    <div class="modal fade" tabindex="-1" role="dialog" id="updateRealpayInstallmentModel">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <!-- Modal -->
                <div id="updateRealpayInstallmentForm">
                    <div class="modal-header">
                        <h5 class="modal-title" id="exampleModalLabel">Update Realpay Installment</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        {{ csrf_field() }}
                        <input type="hidden" name="policy_number" value="{{ $policy->policyNumber }}">
                        <input type="hidden" name="product_id" value="{{ $policy->product_id }}">
                        <input type="hidden" name="policy_id" value="{{ $policy->id }}">
                        <input type="hidden" name="premium_freq" value="{{ isset($policy->premium_freq) ? $policy->premium_freq : 1 }}">
                        <div class="form-group">
                            <label for="client_number">Client Number</label>
                            <input type="text" class="form-control" value="{{ isset($getContracts->ClientNumber) ? $getContracts->ClientNumber : null }}" name="realpay_client_number" id="realpay_client_number" placeholder="Please enter client number" required readonly>
                        </div>
                        <div class="form-group">
                            <label for="contract_number">Contract Number</label>
                            <input type="text" class="form-control" value="{{ isset($getContracts->ContractNumber) ? $getContracts->ContractNumber : null }}" name="realpay_contract_number" id="realpay_contract_number" placeholder="Please enter contract number" required readonly>
                        </div>
                        <div class="form-group">
                            <label for="instl_number">Installment Number</label>
                            <input type="text" class="form-control" name="realpay_installment_number" id="realpay_installment_number" placeholder="Please enter installment number" required>
                            <span style="color: red; display:none" class="realpay_instl_number_error" id="realpay_instl_number_error">Installment Number is required</span>
                        </div>
                        <div class="form-group">
                            <label for="billing_day">Installment Date</label>
                            <input type="text" class="form-control kt_datepicker_today_1" name="realpay_installment_date" id="realpay_installment_date" placeholder="Please enter installment date" required>
                            <span style="color: red; display:none" class="realpay_instl_date_error" id="realpay_instl_date_error">Installment Date is required</span>
                        </div>
                        <div class="form-group">
                            <label for="premium_label">Installment Premium</label>
                            <input type="text" class="form-control" name="realpay_installment_premium" id="realpay_installment_premium" placeholder="Please enter installment premium" required>
                            <span style="color: red; display:none" class="realpay_instl_premium_error" id="realpay_instl_premium_error">Installment Premium is required</span>
                        </div>

                        <span style="color: red;">Note : After processing, Installments will be updated after 1 hour</span>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                        <button class="btn btn-warning" type="reset">Reset</button>
                        <button type="submit" class="btn btn-primary" id="submitUpdateRealpayInstallment">Submit</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    @if (auth::user()->hasPermissionTo('policy-update_realpay_installment_data') )
    <div class="modal fade" tabindex="-1" role="dialog" id="updateRealpayAllInstallmentModel">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <!-- Modal -->
                <div id="updateRealpayAllInstallmentForm">
                    <div class="modal-header">
                        <h5 class="modal-title" id="exampleModalLabel">Update Realpay All Installments</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        {{ csrf_field() }}
                        <input type="hidden" name="policy_number" value="{{ $policy->policyNumber }}">
                        <input type="hidden" name="product_id" value="{{ $policy->product_id }}">
                        <input type="hidden" name="policy_id" value="{{ $policy->id }}">
                        <input type="hidden" name="premium_freq" value="{{ isset($policy->premium_freq) ? $policy->premium_freq : 1 }}">
                        <div class="form-group">
                            <label for="client_number">Client Number</label>
                            <input type="text" class="form-control" value="{{ isset($getContracts->ClientNumber) ? $getContracts->ClientNumber : null }}" name="realpay_all_client_number" id="realpay_all_client_number" placeholder="Please enter client number" required readonly>
                        </div>
                        <div class="form-group">
                            <label for="contract_number">Contract Number</label>
                            <input type="text" class="form-control" value="{{ isset($getContracts->ContractNumber) ? $getContracts->ContractNumber : null }}" name="realpay_all_contract_number" id="realpay_all_contract_number" placeholder="Please enter contract number" required readonly>
                        </div>
                        <div class="form-group">
                            <label for="billing_day">Installment Date</label>
                            <input type="text" class="form-control kt_datepicker_today_1" name="realpay_all_installment_date" id="realpay_all_installment_date" placeholder="Please enter installment date" >
                            <span style="color: red; display:none" class="realpay_all_instl_date_error" id="realpay_all_instl_date_error">Installment Date is required</span>
                        </div>
                        <div class="form-group">
                            <label for="premium_label">Installment Premium</label>
                            <input type="text" class="form-control" name="realpay_all_installment_premium" id="realpay_all_installment_premium" placeholder="Please enter installment premium" >
                            <span style="color: red; display:none" class="realpay_all_instl_premium_error" id="realpay_all_instl_premium_error">Installment Premium is required</span>
                        </div>
                        <span style="color: red;">Note : After processing, Installments will be updated after 1 hour</span>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                        <button class="btn btn-warning" type="reset">Reset</button>
                        <button type="submit" class="btn btn-primary" id="submitUpdateRealpayAllInstallment">Submit</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    @if (auth::user()->hasPermissionTo('policy-update_billng_date_schedule_transaction') )
    <div class="modal fade" tabindex="-1" role="dialog" id="updatePremiumScheduleTransactionModel">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <!-- Modal -->
                <form action=" {{ route('admin.policy.updatePremiumScheduleTransaction') }}" method="POST" id="updatePremiumScheduleTransaction">
                    <div class="modal-header">
                        <h5 class="modal-title" id="exampleModalLabel">Update premium schedule transaction</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        {{ csrf_field() }}
                        <input type="hidden" name="policy_number" value="{{ $policy->policyNumber }}">
                        <input type="hidden" name="product_id" value="{{ $policy->product_id }}">
                        <input type="hidden" name="premium_freq" value="{{ isset($policy->premium_freq) ? $policy->premium_freq : 1 }}">
                        <div class="form-group">
                            <label for="premium">Amount</label>
                            <input type="text" class="form-control" onchange="validateFloatKeyPress(this);" name="premium" id="premium" placeholder="Please enter amount" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                        <button class="btn btn-warning" type="reset">Reset</button>
                        <button type="submit" class="btn btn-primary" id="submitAddScheduleTransaction">Submit</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif

    <div class="modal" tabindex="-1" role="dialog" id="remove_attachment">
        <div class="modal-dialog" role="document">
          <div class="modal-content" id="modal-content-remove-attachment">
            <!-- Modal -->
            <div class="modal-header">
              <h5 class="modal-title" id="exampleModalLabel">Document</h5>
              <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                <span aria-hidden="true">&times;</span>
              </button>
            </div>
            <div class="modal-body">
                Are you sure you want to delete this Document ?
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
              <button type="button" class="btn btn-primary" id="submitRemoveAttachment">Delete</button>
            </div>

          </div>
        </div>
    </div>

    <div class="modal fade" tabindex="-1" role="dialog" id="realpay_delete_confirm">
        <div class="modal-dialog" role="document">
          <div class="modal-content" id="realpay-modal-content">

          </div>
        </div>
    </div>

    <div class="modal" tabindex="-1" role="dialog" id="email_confirm">
        <div class="modal-dialog" role="document">
          <div class="modal-content" id="modal-email-send">
            <!-- Modal -->
            <form action=" {{ route('admin.policy.sendMatiVerificationLink',['customer_id' => $policy->customer_id,'type' => 'email']) }}" method="POST">
                {{csrf_field()}}
                <input type="hidden" value="{{ $policy->product_id }}" name="product_id">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">MATI verification</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    MATI verification Link will be shared to registered Email : {{ $user->email }}
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary" id="submitEmail">Send</button>
                </div>
            </form>
          </div>
        </div>
    </div>

    <div class="modal" tabindex="-1" role="dialog" id="update_mati_confirm">
        <div class="modal-dialog" role="document">
            <div class="modal-content" id="modal-update-mati-send">
                <!-- Modal -->
                <form
                    action="{{ route('admin.customer.updateMatiData', $policy->customer_id) }}"
                    method="POST">
                    {{ csrf_field() }}
                    <div class="modal-header">
                        <h5 class="modal-title" id="exampleModalLabel">Update Mati Identity and Verification ID</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                      Please Enter Mati Identity ID:
                       <input type="text" class="form-control" name="identity_id" value="{{ $user->mati_identity }}" required>
                    </div>
                    <div class="modal-body">
                        Please Enter Mati Verification ID:
                         <input type="text" class="form-control" name="verification_id" value="" required>
                      </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary" id="updateMati">Update</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal" tabindex="-1" role="dialog" id="add_invoice_confirm">
        <div class="modal-dialog" role="document">
            <div class="modal-content" id="modal-add-invoice">
                <form
                    action="{{ route('admin.policy.add_invoice', $policy->id) }}"
                    method="POST">
                    {{ csrf_field() }}
                    <div class="modal-header">
                        <h5 class="modal-title" id="exampleModalLabel">Add Invoice</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                      Please Select Invoice Date:
                      <input type="text" class="form-control kt_datepicker_1"
                        name="date"
                        value="" autocomplete="off"
                        placeholder="Select date" required />
                    </div>
                    <div class="modal-body">
                        Please Enter Amount:
                         <input type="number" class="form-control" name="invoice_amount" value="" required>
                      </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary" id="saveInvoice">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal" tabindex="-1" role="dialog" id="sms_confirm">
        <div class="modal-dialog" role="document">
          <div class="modal-content" id="modal-sms-send">
            <!-- Modal -->
            <form action="{{ route('admin.policy.sendMatiVerificationLink',['customer_id' => $policy->customer_id, 'type' => 'sms']) }}" method="POST">
                {{csrf_field()}}
                <input type="hidden" value="{{ $policy->product_id }}" name="product_id">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">MATI verification</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    MATI verification Link will be shared to registered cellphone no : {{ $user->cellphone }}
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary" id="submitSms">Send</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal" tabindex="-1" role="dialog" id="delete_confirm">
        <div class="modal-dialog" role="document">
            <div class="modal-content" id="modal-content-attachment">

            </div>
        </div>
    </div>
    <div class="modal" tabindex="-1" role="dialog" id="delete_confirm_log">
        <div class="modal-dialog" role="document">
          <div class="modal-content" id="modal-content-sms-email-log">

          </div>
        </div>
    </div>
    <div class="modal" tabindex="-1" role="dialog" id="delete_confirm_discount_surcharge">
        <div class="modal-dialog" role="document">
          <div class="modal-content" id="modal-content-discount-surcharge-policy">

          </div>
        </div>
    </div>
    <div class="modal fade" id="resend_sms" tabindex="-1" role="dialog" aria-labelledby="sms_resend_confirm_title"
        aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content" id="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">Resend Url sms</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form action="#" id="resend_sms_nodal" class="kt-form">
                        <div class="form-group">
                            <label for="urlCellphone">Confirm Customer CellPhone:</label>
                            <input type="text" class="form-control resendCellphone" name="resendCellphone"
                                aria-describedby="emailHelp" placeholder="" value="{{$user->cellphone}}" minlength="8"
                                maxlength="8" pattern="[0-9]{8}">
                        </div>
                        <div class="form_group">
                            <label for="urlPolicy">Confirm Policy Number:</label>
                            <input type="text" class="form-control" name="urlPolicyNumber" aria-describedby="emailHelp"
                                value="{{$policy->policyNumber}}">
                        </div>
                        <input type="hidden" class="paymentId">
                    </form>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-brand resendbtn">Resend SMS</button>
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    </div>
                </div>
            </div>
        </div>
    </div>



    @include('admin.layouts.scripts')
    <script src="{{ asset('assets/vendors/general/bootstrap-select/dist/js/bootstrap-select.min.js') }}"
        type="text/javascript"></script>
    <script src="{{ asset('assets/app/custom/general/components/forms/widgets/bootstrap-select.js') }}"
        type="text/javascript"></script>
    <script src="{{ asset('assets/vendors/general/jquery.repeater/src/lib.js') }}" type="text/javascript"></script>
    <script src="{{ asset('assets/vendors/general/jquery.repeater/src/jquery.input.js') }}" type="text/javascript">
    </script>
    <script src="{{ asset('assets/vendors/general/jquery.repeater/src/repeater.js') }}" type="text/javascript"></script>
    <script src="{{ asset('assets/app/custom/general/components/forms/layouts/repeater.js') }}" type="text/javascript">
    </script>
    <script src="{{ asset('assets/vendors/general/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}"
        type="text/javascript"></script>
    <script src="{{ asset('assets/vendors/custom/components/vendors/bootstrap-datepicker/init.js') }}"
        type="text/javascript"></script>

    <script src="{{  asset('assets/vendors/general/jquery-validation/dist/jquery.validate.js') }}"
        type="text/javascript"></script>
    <script src="{{  asset('assets/vendors/custom/components/vendors/jquery-validation/init.js') }}"
        type="text/javascript"></script>
    <script src="{{ asset('assets/vendors/general/jquery-validation/dist/additional-methods.js') }}"
        type="text/javascript"></script>
    <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <script src="{{  asset('assets/vendors/custom/datatables/datatables.bundle.js') }}" type="text/javascript"></script>


    <script>
        jQuery(document).ready(function() {
            $('#valueLoader').hide();
            // $('#loader').show();
            $('#acceptPremiumDiv').css('display','none');

            let path = document.URL;
            if (path.includes("#kt_generatePaymentUrl")) {
                $('.policyDetailsTab').removeClass('active');
                $('.policyDetailsTab').removeAttr('aria-selected');
                $('.generatePaymentUrlClass').addClass('active');
                $('#kt_portlet_base_demo_3_1_policy_content').removeClass('active');
                $('#kt_portlet_base_demo_3_4_generateURL_content').addClass('active');
            }

            $("#freqSubmitbtn").on("click", function(event) {
                var freqValue = $("#convert_frequency").val();
                if (freqValue) {
                    $('#convert_frequency_error').css('display','none');
                    $("#convert_frequency").prop('required', false);
                    $('#convert_frequency_confirm').modal('show');
                } else {
                    $('#convert_frequency_error').css('display','block');
                    $("#convert_frequency").prop('required', true);
                    // $('#convert_frequency').rules('add',  { required: true, messages: { required: "Please Select frequency" } });
                }
            });

            $("#convertFreqSubmit").on("click", function(event) {
                // $('#loader').show();
                $("#convertFreqSubmit").prop("disabled",true);
                $('#loader').show();
                $.ajax({
                    url: '{{ route('admin.policy.convertPolicyFrequency') }}',
                    data: {
                        "_token": "{{ csrf_token() }}",
                        "policyNumber" : $("#policyNumber_conFreq").val(),
                        "policyID" : $("#policyID_conFreq").val(),
                        "frequency" : $("#convert_frequency").val(),
                    },
                    beforeSend: function() {
                        $('#loader').show();
                    },
                    type: 'post',
                    datatype: 'json',
                    async: false,
                    success: function(data) {
                        // console.log(data);
                        Toastify({
                            text: data.message,
                            duration: 6000,
                            newWindow: true,
                            gravity: "top", // `top` or `bottom`
                            center: true, // `true` or `false`
                            backgroundColor: "#1dc9b7",
                        }).showToast();
                        location.reload();
                    },
                    error: function(data) {
                        // $('#loader').css("display", "none");
                        Toastify({
                            text: data.responseJSON.message,
                            duration: 6000,
                            newWindow: true,
                            gravity: "top", // `top` or `bottom`
                            center: true, // `true` or `false`
                            backgroundColor: "red",
                        }).showToast();
                        location.reload();
                    },
                    // complete: function() {
                    //     $('#loader').css("display", "none");
                    // }
                });
            });


            var status = '{{$policy->status}}';
            if (status == 0) {
                $('#acceptPremiumDiv').css('display','block');
                $('#realpaySwitchDiv').css('display','none');
                $('#dpoSwitchDiv').css('display','none');
                $('#cashSwitchDiv').css('display','none');
                $('#rerate_payment_note').css('display','none');
            }
        });
    </script>
    <script>
        jQuery.validator.addMethod("amountCheck", function(value, element) {
            return this.optional( element ) || /^[0-9]*\.?[0-9]*$/.test( value );
        }, 'Sorry, Comma is not allowed.');

        $("#newPremiumRate").validate({
            ignore: [],
            ignore: ":hidden",
            // define validation rules
            rules: {
                new_frequency: {
                    required: true
                },
                billingDay:{
                    required:true
                },
                BankName: {
                    required: true
                },
                BranchCode: {
                    required: true
                },
                accountType: {
                    required: true
                },
                accountNumber: {
                    required: true
                },
                paymentDate: {
                    required: true
                },
                paymentAmount: {
                    required: true,
                    amountCheck: true,
                },
                receiptNumber: {
                    required: true
                },
                paymentRecievedBy: {
                    required: true
                },
                numberOfInstalmentsPaid: {
                    required: true
                },
                paymentNote: {
                    required: true
                },
                payment_image: {
                    required: true
                },
                rerate_premium: {
                    required: true
                },
                first_premium: {
                    required: true
                }

            },
            messages: {
                new_frequency: {
                    required: 'Please select frequnecy.'
                },
                paymentAmount: {
                    required: 'Please enter amount.',
                    amountCheck: 'Sorry, Comma is not allowed.',
                },
            },

            //display error alert on form submit
            invalidHandler: function(event, validator) {
                $('html, body').animate({
                    scrollTop: $(validator.errorList[0].element).offset().top - 200
                }, 1000);
            },

            submitHandler: function(form) {
                var frequency = $('#new_frequency').val();
                if (!frequency) {
                    $('#newFrequencyError').css('display','block');
                } else {
                    form.submit(); // submit the form
                }
            }
        });

        $("#addNewRealpayInstallmentForm").validate({
            ignore: [],
            ignore: ":hidden",
            // define validation rules
            rules: {
                add_realpay_client_number: {
                    required: true
                },
                add_realpay_contract_number: {
                    required: true
                },
                add_realpay_installment_date: {
                    required: true
                },
                add_realpay_installment_premium: {
                    required: true
                },
            },
            messages: {
                add_realpay_client_number: {
                    required: 'This field is required.'
                },
                add_realpay_contract_number: {
                    required: 'This field is required.'
                },
                add_realpay_installment_date: {
                    required: 'Please select installment date.'
                },
                add_realpay_installment_premium: {
                    required: 'Please enter installment premium.'
                },
            },

            //display error alert on form submit
            invalidHandler: function(event, validator) {
                $('html, body').animate({
                    scrollTop: $(validator.errorList[0].element).offset().top - 200
                }, 1000);
            },

            submitHandler: function(form) {
                form.submit(); // submit the form
            }
        });

        $("#cancelDPODuplicateTx").validate({
            ignore: [],
            ignore: ":hidden",
            // define validation rules
            rules: {
                policyNumber: {
                    required: true
                },
                installment_range_from: {
                    required: true
                },
                installment_range_to: {
                    required: true
                },
            },
            messages: {
                policyNumber: {
                    required: 'Please select policy number.'
                },
                installment_range_from: {
                    required: 'Please select installment range from.'
                },
                installment_range_to: {
                    required: 'Please select installment range to.'
                },
            },

            //display error alert on form submit
            invalidHandler: function(event, validator) {
                $('html, body').animate({
                    scrollTop: $(validator.errorList[0].element).offset().top - 200
                }, 1000);
            },

            submitHandler: function(form) {
                form.submit(); // submit the form
            }
        });
    </script>
    <script>

    function getCustomerFeebackOption() {
            var baseURL = /* 'https://devgraphite.alphadirect.co.bw/' */ /* '{{ env('GRAPHITE_URL') }}' */
                '{{ \Config::get('values.graphite_url') }}';
            var ajaxRequest = setTimeout(function(sn) {
                $.ajax({
                    url: 'https://graphite.alphadirect.co.bw/api/customerFeedback/options',
                    data: {
                        "_token": "{{ csrf_token() }}",
                    },
                    beforeSend: function() {
                        $("#loader").show();
                    },
                    type: 'get',
                    datatype: 'json',
                    success: function(data) {
                        var string = '';
                        if (data.options.length > 0) {
                            for (let i = 0; i < data.options.length; i++) {
                                index = i;
                                var option = data.options[i];
                                string += '<div class="list-group-item">' +
                                    '<div class="custom-control custom-radio">' +
                                    '<input type="radio" class="custom-control-input" name="reason" id="modal-feedback-cleanliness-' +
                                    i + '" value="' + option.name + '" data-input="' + i + '">' +
                                    '<label class="custom-control-label mb-0 ml-2 nott ls0" for="modal-feedback-cleanliness-' +
                                    i + '">' + option.name + '</label>' +
                                    '</div>';
                                if (option.suboptions.length > 0) {
                                    string += '<div id="option' + i +
                                        '" class="option" hidden data-subinput="' + i + '">';
                                    if (option.description != null) {
                                        string += '<h5>' + option.description + '</h5>';
                                    }
                                    if (option.input_type == 1) {
                                        for (let j = 0; j < option.suboptions.length; j++) {
                                            var suboption = option.suboptions[j];
                                            string += '<div class="list-group-item">' +
                                                '<div class="form-check">' +
                                                '<label class="form-check-label" for="gridRadios' + j +
                                                '" style="text-transform: capitalize;">' +
                                                '<input class="form-check-input" type="radio" name="circum" id="gridRadios' +
                                                j + '" value="' + suboption.name + '">' +
                                                suboption.name +
                                                '</label>' +
                                                '</div>' +
                                                '</div>';
                                        }
                                    } else if (option.input_type == 2) {
                                        string +=
                                            '<select class="form-control valid" id="exampleFormControlSelect' +
                                            i + '" name="otherCompany" aria-invalid="false">';
                                        string += '<option value="">Please Select</option>';
                                        for (let j = 0; j < option.suboptions.length; j++) {
                                            var suboption = option.suboptions[j];
                                            string += '<option value="' + suboption.name + '">' +
                                                suboption.name + '</option>';
                                        }
                                        string += '</select>';
                                    }
                                    string += '</div>';
                                }

                                string += '</div>';
                                index++;
                            }
                        }
                        string += '<div class="list-group-item">' +
                            '<div class="custom-control custom-radio">' +
                            '<input type="radio" class="custom-control-input" name="reason" id="modal-feedback-cleanliness-' +
                            index + '" value="other">' +
                            '<label class="custom-control-label mb-0 ml-2 nott ls0" for="modal-feedback-cleanliness-' +
                            index + '">Other</label>' +
                            '</div>' +
                            '<div id="optionOther" class="option" hidden data-subinput="' + index +
                            '">' +
                            '<div class="list-group-item">' +
                            '<textarea class="form-control option' + index +
                            '" name="other_reason" id="exampleFormControlTextarea1" rows="3" minlength="10"></textarea>' +
                            '</div>' +
                            '</div>' +
                            '</div>';
                        $('#feedbackOptionDiv').html(string);
                    },
                    error: function(data) {
                        $('#loader').css("display", "none");
                    },
                    complete: function() {
                        $('#loader').css("display", "none");
                    }
                });
            }, 200);
        }

        $(document).ready(function() {
            var checkUrl = window.location.hash;
            if (checkUrl.includes('#kt_generatePaymentUrl')) {
                $('#type').prop('selectedIndex',4);
                $('#urlAmount').val('{{$balance_fresh}}');
            }

            $('#ledger-tab').one( "click", function() {
                LedgetDataTables.init();

            });
            $('#earned_premiums-tab').one("click", function() {
                    earned_premiumsDataTables.init();
                });

            $('#discount-surcharge-tab').one("click", function() {
                DiscountSurchargeDataTables.init();
            });

            $('#sms-email-log-tab').one("click", function() {
                SmsEmailLogDataTables.init();
            });

            $('#attachment-tab').one("click", function() {
                AttachmentDataTables.init();
            });

            $('#generate-payment-url').one("click", function() {
                PaymentUrlDataTables.init();
            });

            $('#send-policy-document').one("click", function() {
                SentDocumentsDataTables.init();
            });

            $('#DPO-Payment-tab').one("click", function() {
                DPOPaymentDataTables.init();
            });

            $('#scheduled-transaction-tab').one("click", function() {
                ScheduledTransactionDataTables.init();
            });

            $('#transaction-tab').one( "click", function() {
                TransactionLogTable.init();
            });
            $('#realPay-tab').one( "click", function() {
                RealPayTable.init();
            });
            $('#policyTerm-tab').one( "click", function() {
                PolicyTermTable.init();
                PolicyLifeCycle.init();
            });
            $('#policyReinstate-tab').one( "click", function() {
                PolicyReinstateTable.init();
            });
            $('#claim-tab').one( "click", function() {
                ClaimsTable.init();
            });
            $('#activity-tab').one( "click", function() {
                ActivityTable.init();
            });
            $('#loss_history_tab').one( "click", function() {
                LossHistoryTable.init();
            });
            $('#realpay-contract-list-tab').one( "click", function() {
                RealPayContractListTable.init();
            });

            $('#feedbackForm').on('click', function() {
                getCustomerFeebackOption();
                $('#myModal').modal('show');
            });

            $('#MoveToRenewFormTop').on('click', function() {
                $('#myMoveToRenewModal').modal('show');
            });

            $('#MoveToRenewFormBottom').on('click', function() {
                $('#myMoveToRenewModal').modal('show');
            });


            $('#response').slideUp();

            $('#termDocButton').hide();

            $('#policy_terms').on('change',function(){
                var route = '{{ route('admin.documents.generatePolicyDocumentTerms',$policy->id) }}';
                var selected = $(this).find(':selected').attr('data-id');
                var current = new Date().getFullYear();
                var termId = $(this).find(':selected').attr('value');

                if(selected != current){
                    $('#termDocButton').show();
                    $('#termDocButton').attr("href", route+'/'+termId);
                }
            });

            $('#days').on('change', function () {
                $('#response').slideUp();
            });

            $('#bt-ajax').on('click', function () {
                var quoteNumber = $('#quoteNumber').val();
                ajaxRequest = setTimeout(function (sn) {
                    $.ajax({
                        url: '{{ route('quote.calculatePerDayPremium') }}',
                        data: {
                            "_token": "{{ csrf_token() }}",
                            "quoteNumber": quoteNumber,
                            "day": $('#days').val(),
                        },
                        type: 'post',
                        datatype: 'json',
                        success: function (data) {
                            var datePremium = data.premiumDate
                            $('#response').slideDown();
                            $('#perDayValue').html('P'+data.perDayPremium);
                            $('#tilldate').html('P'+data.tilldate);
                            $('#datePremium').text(datePremium);
                            $('#daysP').html($('#days').val());
                        }
                    });
                }, 200);
            });



            var LossHistoryTable = function() {
                var initLossTable = function() {
                    var table = $('#loss_history_table');

                    // begin first table
                    table.DataTable({
                        responsive: true,
                        searchDelay: 500,
                        language: {
                            processing: "<img src='{{asset('img/loading.gif')}}'>"
                        },
                        processing: true,
                        serverSide: true,
                        order: [1, 'DESC'],
                        ajax: "{!! route('admin.policy.getclaimsdata', $policy->id)!!}",
                        columns: [{
                            data: 'claim_number'
                        }, {
                            data: 'claim_type'
                        }, {
                            data: 'claim_handler'
                        }, {
                            data: 'status'
                        },

                        ],
                    });
                };
                return {
                    init: function() {
                        initLossTable();
                    }
                };
            }();

            $('#send_email').click(function(){
                $('#email_confirm').modal('show');
                return false;
            });

            $('#update_mati_data').click(function() {
                    $('#update_mati_confirm').modal('show');
                    return false;
                })

            $('#add_invoice').click(function() {
                $('#add_invoice_confirm').modal('show');
                return false;
            })

            $('#send_sms').click(function(){
                $('#sms_confirm').modal('show');
                return false;
            });
        });

    var KTFormControls = function () {
        // Private functions
        jQuery.validator.addMethod("future", function(value, element) {
            return this.optional(element) || Date.parse(value) < new Date().getTime(); //use Date.now() if you can instead of getTime, it's nicer
        }, "Please enter only past dates");

        //Vehicle Registration
        jQuery.validator.addMethod("license", function(value, element) {
            return this.optional( element ) || /^[Bb]{1}\d{3}[a-zA-Z]{3}$/.test( value );
        }, 'Sorry, We only accept Botswana registered vehicles Eg: B123ABC');

        jQuery.validator.addMethod(
            "sum",
            function (value, element, params) {
                var sumOfVals = 0;
                $('#editDiv .beneficiaryPayment').each(function() {
                    sumOfVals += Number($(this).val());
                });
                if (sumOfVals <= params)
                    return true;

                return false;
            },
            'Total of Payments for all Beneficiaries cannot be more than 100'
        );

        // var demo12 = function() {
                $("#policyForm1").validate({
                    // define validation rules
                    ignore:":not(:visible)",
                    rules: {
                        paymentAmount: {
                            required: true,
                            amountCheck: true,
                        },
                    },
                    groups: {
                        validateGroup1: "omang passport"
                    },
                    messages: {
                        omang: {
                            require_from_group: "Please provide either your Omang Id or Passport",
                            max: "Your Omang Id can be max 9 characters long",
                            maxlength: "Your Omang Id can be max 9 characters long"
                        },
                        passport: {
                            require_from_group: "Please provide either your Omang Id or Passport",
                            max: "Your Passport can be max 12 characters long"
                        },
                        paymentAmount: {
                            required: 'Please enter amount.',
                            amountCheck: 'Sorry, Comma is not allowed.',
                        },
                    },

                    //display error alert on form submit
                    invalidHandler: function(event, validator) {
                        $('html, body').animate({
                            scrollTop: $(validator.errorList[0].element).offset().top - 200
                        }, 1000);
                    },

                    submitHandler: function(form) {
                        form.submit(); // submit the form
                    }
                });
            // }

            // return {
            //     // public functions
            //     init: function() {
            //         demo12();
            //     }
            // };
    }();


        $(document).on('click', '.makeOrangePaymentNow', function() {
            var id = $(this).attr('data-id');

            if (id != '')
            {
                // $('#makePaymentNowModal').modal('show');
                let text = "Are you sure?";
                if(confirm(text) == true)
                {
                    $.ajax({
                        url: '{{ route("admin.policy.makeOrangePaymentNow") }}',
                        data: {
                            "_token": "{{ csrf_token() }}",
                            "id": id,
                        },
                        beforeSend: function() {
                            $("#loader").show();
                        },
                        type: 'post',
                        datatype: 'json',
                        success: function(data) {
                            // console.log(data);
                            Toastify({
                                text: data.message,
                                duration: 6000,
                                newWindow: true,
                                gravity: "top", // `top` or `bottom`
                                center: true, // `true` or `false`
                                backgroundColor: "#1dc9b7",
                            }).showToast();
                            location.reload();
                        },
                        error: function(data) {
                            // console.log(data);

                            Toastify({
                                text: data.responseJSON.message,
                                duration: 6000,
                                newWindow: true,
                                gravity: "top", // `top` or `bottom`
                                center: true, // `true` or `false`
                                backgroundColor: "red",
                            }).showToast();
                            location.reload();
                        },
                        complete: function(data) {
                            $("#loader").hide();
                            // $('#makePaymentNowModal').modal('hide');
                        },

                    });
                }

            } else {
                alert('Please provide required data');
            }
        });

        $(document).on('click', '.removeOrangePayment', function() {
            var id = $(this).attr('data-id');

            if (id != '')
            {
                // $('#makePaymentNowModal').modal('show');
                let text = "Are you sure?";
                if(confirm(text) ==  true)
                {
                    $.ajax({
                        url: '{{ route("admin.policy.removeOrangePayment") }}',
                        data: {
                            "_token": "{{ csrf_token() }}",
                            "id": id,
                        },
                        beforeSend: function() {
                            $("#loader").show();
                        },
                        type: 'post',
                        datatype: 'json',
                        success: function(data) {
                            // console.log(data);
                            Toastify({
                                text: data.message,
                                duration: 6000,
                                newWindow: true,
                                gravity: "top", // `top` or `bottom`
                                center: true, // `true` or `false`
                                backgroundColor: "#1dc9b7",
                            }).showToast();
                            location.reload();
                        },
                        error: function(data) {
                            // console.log(data);

                            Toastify({
                                text: data.responseJSON.message,
                                duration: 6000,
                                newWindow: true,
                                gravity: "top", // `top` or `bottom`
                                center: true, // `true` or `false`
                                backgroundColor: "red",
                            }).showToast();
                            location.reload();
                        },
                        complete: function(data) {
                            $("#loader").hide();
                            // $('#makePaymentNowModal').modal('hide');
                        },

                    });
                }

            } else {
                alert('Please provide required data');
            }
        });

    @if (auth::user()->hasPermissionTo('policy-make_payment_dpo'))
                $(document).on('click', '.makePaymentNow', function() {
                    var id = $(this).attr('data-id');

                    if (id != '')
                    {
                        // $('#makePaymentNowModal').modal('show');
                        let text = "Are you sure?";
                        if(confirm(text) == true)
                        {
                            $.ajax({
                                url: '{{ route("admin.policy.makePaymentNowDpo") }}',
                                data: {
                                    "_token": "{{ csrf_token() }}",
                                    "id": id,
                                },
                                beforeSend: function() {
                                    $("#loader").show();
                                },
                                type: 'post',
                                datatype: 'json',
                                success: function(data) {
                                    // console.log(data);
                                    Toastify({
                                        text: data.message,
                                        duration: 6000,
                                        newWindow: true,
                                        gravity: "top", // `top` or `bottom`
                                        center: true, // `true` or `false`
                                        backgroundColor: "#1dc9b7",
                                    }).showToast();
                                    location.reload();
                                },
                                error: function(data) {
                                    // console.log(data);

                                    Toastify({
                                        text: data.responseJSON.message,
                                        duration: 6000,
                                        newWindow: true,
                                        gravity: "top", // `top` or `bottom`
                                        center: true, // `true` or `false`
                                        backgroundColor: "red",
                                    }).showToast();
                                    location.reload();
                                },
                                complete: function(data) {
                                    $("#loader").hide();
                                    // $('#makePaymentNowModal').modal('hide');
                                },

                            });
                        }

                    } else {
                        alert('Please provide required data');
                    }
                });
                @endif

                @if (auth::user()->hasPermissionTo('policy-update_realpay_installment_data'))
                    $(document).on('click', '#submitUpdateRealpayInstallment', function() {
                        var realpayClientNumber =  $('#realpay_client_number').val();
                        var realpayContractNumber = $('#realpay_contract_number').val();
                        var realpayInstallmentDate = $('#realpay_installment_date').val();
                        var realpayInstallmentNumber = $('#realpay_installment_number').val();
                        var realpayInstallmentPremium = $('#realpay_installment_premium').val();
                        var product_id = $('#product_id').val();

                        if ((realpayInstallmentDate != null && realpayInstallmentDate != '') || (realpayInstallmentPremium != null && realpayInstallmentPremium != '') && realpayInstallmentNumber != null && realpayInstallmentNumber != '') {
                            $('#realpay_instl_number_error').css('display','none');
                            $('#realpay_instl_date_error').css('display','none');
                            $('#realpay_instl_premium_error').css('display','none');

                            let text = "Are you sure?";
                            if(confirm(text) ==  true)
                            {
                                $.ajax({
                                    url: '{{ route("admin.policy.updateRealpayInstallment") }}',
                                    data: {
                                        "_token": "{{ csrf_token() }}",
                                        "realpay_client_number" : realpayClientNumber,
                                        "realpay_contract_number" : realpayContractNumber,
                                        "realpay_installment_date" : realpayInstallmentDate,
                                        "realpay_installment_number" : realpayInstallmentNumber,
                                        "realpay_installment_premium" : realpayInstallmentPremium,
                                        "policy_id" : "{{ $policy->id }}",
                                        "product_id" : product_id,
                                    },
                                    beforeSend: function() {
                                        $("#loader").show();
                                    },
                                    type: 'post',
                                    datatype: 'json',
                                    success: function(data) {
                                        // console.log(data);
                                        Toastify({
                                            text: data.message,
                                            duration: 6000,
                                            newWindow: true,
                                            gravity: "top", // `top` or `bottom`
                                            center: true, // `true` or `false`
                                            backgroundColor: "#1dc9b7",
                                        }).showToast();
                                        location.reload();
                                    },
                                    error: function(data) {
                                        // console.log(data);

                                        Toastify({
                                            text: data.responseJSON.message,
                                            duration: 6000,
                                            newWindow: true,
                                            gravity: "top", // `top` or `bottom`
                                            center: true, // `true` or `false`
                                            backgroundColor: "red",
                                        }).showToast();
                                        location.reload();
                                    },
                                    complete: function(data) {
                                        $("#loader").hide();
                                        // $('#makePaymentNowModal').modal('hide');
                                    },

                                });
                            }
                        } else {

                            if (realpayInstallmentNumber == null || realpayInstallmentNumber == '') {
                                $('#realpay_instl_number_error').css('display','block');
                            } else {
                                $('#realpay_instl_number_error').css('display','none');
                            }

                            if (realpayInstallmentDate == null || realpayInstallmentDate == '' && realpayInstallmentPremium == null || realpayInstallmentPremium == ''){
                                $('#realpay_instl_date_error').css('display','block');
                                $('#realpay_instl_premium_error').css('display','block');
                            }
                        }

                    });
                @endif

                @if (auth::user()->hasPermissionTo('policy-update_realpay_installment_data'))
                    $(document).on('click', '#submitUpdateRealpayAllInstallment', function() {
                        var realpayClientNumber =  $('#realpay_all_client_number').val();
                        var realpayContractNumber = $('#realpay_all_contract_number').val();
                        var realpayInstallmentDate = $('#realpay_all_installment_date').val();
                        var realpayInstallmentPremium = $('#realpay_all_installment_premium').val();
                        var product_id = $('#product_id').val();

                        if (realpayInstallmentDate != null && realpayInstallmentDate != '' || realpayInstallmentPremium != null || realpayInstallmentPremium != '') {
                            $('#realpay_all_instl_date_error').css('display','none');
                            $('#realpay_all_instl_premium_error').css('display','none');

                            let text = "Are you sure?";
                            if(confirm(text) ==  true)
                            {
                                $.ajax({
                                    url: '{{ route("admin.policy.updateRealpayAllInstallmentData") }}',
                                    data: {
                                        "_token": "{{ csrf_token() }}",
                                        "realpay_client_number" : realpayClientNumber,
                                        "realpay_contract_number" : realpayContractNumber,
                                        "realpay_installment_date" : realpayInstallmentDate,
                                        "realpay_installment_premium" : realpayInstallmentPremium,
                                        "policy_id" : "{{ $policy->id }}",
                                        "product_id" : product_id,
                                    },
                                    beforeSend: function() {
                                        $("#loader").show();
                                    },
                                    type: 'post',
                                    datatype: 'json',
                                    success: function(data) {
                                        // console.log(data);
                                        Toastify({
                                            text: data.message,
                                            duration: 6000,
                                            newWindow: true,
                                            gravity: "top", // `top` or `bottom`
                                            center: true, // `true` or `false`
                                            backgroundColor: "#1dc9b7",
                                        }).showToast();
                                        location.reload();
                                    },
                                    error: function(data) {
                                        // console.log(data);

                                        Toastify({
                                            text: data.responseJSON.message,
                                            duration: 6000,
                                            newWindow: true,
                                            gravity: "top", // `top` or `bottom`
                                            center: true, // `true` or `false`
                                            backgroundColor: "red",
                                        }).showToast();
                                        location.reload();
                                    },
                                    complete: function(data) {
                                        $("#loader").hide();
                                        // $('#makePaymentNowModal').modal('hide');
                                    },

                                });
                            }
                        } else {

                            // if (realpayInstallmentDate == null || realpayInstallmentDate == '') {
                            //     $('#realpay_all_instl_date_error').css('display','block');
                            // } else {
                            //     $('#realpay_all_instl_date_error').css('display','none');
                            // }

                            if (realpayInstallmentDate == null || realpayInstallmentDate == '' && realpayInstallmentPremium == null || realpayInstallmentPremium == ''){
                                $('#realpay_all_instl_date_error').css('display','block');
                                $('#realpay_all_instl_premium_error').css('display','block');
                            }
                        }

                    });
                @endif

                @if (auth::user()->hasPermissionTo('policy-suspend_payment_dpo'))
                $(document).on('click', '.suspendPaymentDpo', function() {
                    var id = $(this).attr('data-id');

                    if (id != '')
                    {
                        // $('#makePaymentNowModal').modal('show');
                        let text = "Are you sure?";
                        if(confirm(text) ==  true)
                        {
                            $.ajax({
                                url: '{{ route("admin.policy.suspendPaymentDpo") }}',
                                data: {
                                    "_token": "{{ csrf_token() }}",
                                    "id": id,
                                },
                                beforeSend: function() {
                                    $("#loader").show();
                                },
                                type: 'post',
                                datatype: 'json',
                                success: function(data) {
                                    // console.log(data);
                                    Toastify({
                                        text: data.message,
                                        duration: 6000,
                                        newWindow: true,
                                        gravity: "top", // `top` or `bottom`
                                        center: true, // `true` or `false`
                                        backgroundColor: "#1dc9b7",
                                    }).showToast();
                                    location.reload();
                                },
                                error: function(data) {
                                    // console.log(data);

                                    Toastify({
                                        text: data.responseJSON.message,
                                        duration: 6000,
                                        newWindow: true,
                                        gravity: "top", // `top` or `bottom`
                                        center: true, // `true` or `false`
                                        backgroundColor: "red",
                                    }).showToast();
                                    location.reload();
                                },
                                complete: function(data) {
                                    $("#loader").hide();
                                    // $('#makePaymentNowModal').modal('hide');
                                },

                            });
                        }

                    } else {
                        alert('Please provide required data');
                    }
                });
                @endif

                @if (auth::user()->hasPermissionTo('policy-suspend_payment_all_dpo'))
                $(document).on('click', '#suspendPaymentDpoAll', function() {
                    var policyNumber = $(this).attr('data-policyNumber');

                    if (policyNumber != '')
                    {
                        // $('#makePaymentNowModal').modal('show');
                        let text = "Are you sure you want to cancel all transactions. This action is irreversible?";
                        if(confirm(text) ==  true)
                        {
                            $.ajax({
                                url: '{{ route("admin.policy.suspendPaymentDpoAll") }}',
                                data: {
                                    "_token"      : "{{ csrf_token() }}",
                                    "policyNumber": policyNumber,
                                },
                                beforeSend: function() {
                                    $("#loader").show();
                                },
                                type: 'post',
                                datatype: 'json',
                                success: function(data) {
                                    // console.log(data);
                                    Toastify({
                                        text: data.message,
                                        duration: 6000,
                                        newWindow: true,
                                        gravity: "top", // `top` or `bottom`
                                        center: true, // `true` or `false`
                                        backgroundColor: "#1dc9b7",
                                    }).showToast();
                                    location.reload();
                                },
                                error: function(data) {
                                    // console.log(data);

                                    Toastify({
                                        text: data.responseJSON.message,
                                        duration: 6000,
                                        newWindow: true,
                                        gravity: "top", // `top` or `bottom`
                                        center: true, // `true` or `false`
                                        backgroundColor: "red",
                                    }).showToast();
                                    location.reload();
                                },
                                complete: function(data) {
                                    $("#loader").hide();
                                    // $('#makePaymentNowModal').modal('hide');
                                },

                            });
                        }

                    } else {
                        alert('Please provide required data');
                    }
                });
                @endif
        $("#refundForm").validate({
                ignore: [],
                ignore: ".ignore",
                // define validation rules
                rules: {
                    reference_number: {
                        required: true
                    },
                    date_of_refund: {
                        required: true
                    },
                    amount: {
                        required: true,
                        maxlength: 9,
                        number: true
                    },
                    reason: {
                        required: true,
                    },
                    refunded_by: {
                        required: true,
                    },
                },
                messages: {
                    reference_number: {
                        required: 'Reference number is required.'
                    },
                    date_of_refund: {
                        required: 'Date of refund is required.'
                    },
                    amount: {
                        required: 'Amount is required.',
                    },
                    refunded_by: {
                        required: 'This field is required.',
                    },
                    reason: {
                        required: 'Reason is required.',
                    }
                },

                //display error alert on form submit
                invalidHandler: function(event, validator) {
                    $('html, body').animate({
                        scrollTop: $(validator.errorList[0].element).offset().top - 200
                    }, 1000);
                },

                submitHandler: function(form) {
                    form.submit(); // submit the form
                }
            });


            // $("#policyAddOfflineForm").validate({
            //     ignore: [],
            //     ignore: ":hidden",
            //     // define validation rules
            //     rules: {
            //         paymentDate: {
            //             required: true
            //         },
            //         paymentAmount: {
            //             required: true
            //         },
            //         receiptNumber: {
            //             required: true,
            //         },
            //         paymentRecievedBy: {
            //             required: true,
            //         },
            //         numberOfInstalmentsPaid: {
            //             required: true,
            //         },
            //         paymentFreq: {
            //             required: true,
            //         },
            //         paymentNote: {
            //             required: true,
            //         },
            //         payment_image: {
            //             required: true,
            //         },
            //     },
            //     messages: {
            //         paymentDate: {
            //             required: 'Payment Date is required.'
            //         },
            //         paymentAmount: {
            //             required: 'Payment Amount is required.'
            //         },
            //         receiptNumber: {
            //             required: 'Receipt Number is required.',
            //         },
            //         paymentRecievedBy: {
            //             required: 'This field is required.',
            //         },
            //         numberOfInstalmentsPaid: {
            //             required: 'This field is required.',
            //         },
            //         paymentFreq: {
            //             required: 'Payment Frequency is required.',
            //         },
            //         paymentNote: {
            //             required: 'This field is required.',
            //         },
            //         payment_image: {
            //             required: 'This field is required.',
            //         }
            //     },

            //     //display error alert on form submit
            //     invalidHandler: function(event, validator) {
            //         $('html, body').animate({
            //             scrollTop: $(validator.errorList[0].element).offset().top - 200
            //         }, 1000);
            //     },

            //     submitHandler: function(form) {
            //         form.submit(); // submit the form
            //     }
            // });
    </script>

    <script>
        $(function () {
        $('body').on('hidden.bs.modal', '.modal', function () {
            $(this).removeData('bs.modal');
        });

    });

    $("#attatchment_table").on("click", "a.confirm-delete" , function(event) {
        event.preventDefault();
        var id = $(this).attr('value');
        $.ajax({
            url: '{{ route('admin.policy.attachment-confirm-delete') }}',
            data: {
                "_token": "{{ csrf_token() }}",
                "id":id
            },
            type: 'post',
            datatype : 'json',
            success: function(data) {
                var append = '<div class="modal-header"><h5 class="modal-title" id="exampleModalLabel">Delete </h5>' +
                    '<button type="button" class="close" data-dismiss="modal" aria-label="Close"> <span aria-hidden="true">&times;</span> </button> ' +
                    '</div> ' +
                    '<div class="modal-body"> ' +
                    '<p>'+data.body+'</p> ' +
                    '</div> ' +
                    '<div class="modal-footer"> ' +
                    '<button type="button" class="btn btn-outline-brand" data-dismiss="modal">Close</button> ';
                $('#modal-content-attachment').html(append);
                if(data.status == 'success'){
                    var url = '{{ route("admin.policy.attachment_delete", ":id") }}';
                    url = url.replace(':id', data.id);

                    append += '<a href="'+url+'" type="button" class="btn btn-brand">Delete</a></div>';
                }
                append += '</div>';
                $('#modal-content-attachment').empty();
                $('#modal-content-attachment').html(append);
                $('#delete_confirm').modal('show');
            },
        });
    });

    // invoicing
    $("#invoicing_table").on("click", "a.ledger-invoice-confirm-delete", function(event) {
        event.preventDefault();
        var id = $(this).attr('value');
        // alert(id);
        $("#ledgerInvoiceId").val(id);
        $('#ledgerInvoiceDeleteModal').modal('show');
    });

     // invoicing
     $("#transaction_table").on("click", "a.transaction-log-confirm-delete", function(event) {
        event.preventDefault();
        var id = $(this).attr('value');
        // alert(id);
        $("#transactionLogId").val(id);
        $('#transactionLogDeleteModal').modal('show');
    });
    $("#transaction_table").on("click", "a.transaction-log-confirm-delete2", function(event) {
                event.preventDefault();
                var id = $(this).attr('value');
                // alert(id);
                $("#transactionLogId2").val(id);
                $('#transactionLogDeleteModal2').modal('show');
            });

    $("#sms_email_table").on("click", "a.log-confirm-delete" , function(event) {
        event.preventDefault();
        var id = $(this).attr('value');
        $.ajax({
            url: '{{ route('admin.policy.sms-email-log-confirm-delete') }}',
            data: {
                "_token": "{{ csrf_token() }}",
                "id":id
            },
            type: 'post',
            datatype : 'json',
            success: function(data) {
                var append = '<div class="modal-header"><h5 class="modal-title" id="exampleModalLabel">Delete </h5>' +
                    '<button type="button" class="close" data-dismiss="modal" aria-label="Close"> <span aria-hidden="true">&times;</span> </button> ' +
                    '</div> ' +
                    '<div class="modal-body"> ' +
                    '<p>'+data.body+'</p> ' +
                    '</div> ' +
                    '<div class="modal-footer"> ' +
                    '<button type="button" class="btn btn-outline-brand" data-dismiss="modal">Close</button> ';
                $('#modal-content-sms-email-log').html(append);
                if(data.status == 'success'){
                    var url = '{{ route("admin.policy.sms_email_log_delete", ":id") }}';
                    url = url.replace(':id', data.id);

                    append += '<a href="'+url+'" type="button" class="btn btn-brand">Delete</a></div>';
                }
                append += '</div>';
                $('#modal-content-sms-email-log').empty();
                $('#modal-content-sms-email-log').html(append);
                $('#delete_confirm_log').modal('show');
            },
        });
    });

    $("#discount_surcharge_policy_table").on("click", "a.discount-surcharge-confirm-delete" , function(event) {
        event.preventDefault();
        var id = $(this).attr('value');
        $.ajax({
            url: '{{ route('admin.policy.discount-surcharge-policy-confirm-delete') }}',
            data: {
                "_token": "{{ csrf_token() }}",
                "id":id
            },
            type: 'post',
            datatype : 'json',
            success: function(data) {
                var append = '<div class="modal-header"><h5 class="modal-title" id="exampleModalLabel">Delete </h5>' +
                    '<button type="button" class="close" data-dismiss="modal" aria-label="Close"> <span aria-hidden="true">&times;</span> </button> ' +
                    '</div> ' +
                    '<div class="modal-body"> ' +
                    '<p>'+data.body+'</p> ' +
                    '</div> ' +
                    '<div class="modal-footer"> ' +
                    '<button type="button" class="btn btn-outline-brand" data-dismiss="modal">Close</button> ';
                $('#modal-content-discount-surcharge-policy').html(append);
                if(data.status == 'success'){
                    var url = '{{ route("admin.policy.discount_surcharge_policy_delete", ":id") }}';
                    url = url.replace(':id', data.id);

                    append += '<a href="'+url+'" type="button" class="btn btn-brand">Delete</a></div>';
                }
                append += '</div>';
                $('#modal-content-discount-surcharge-policy').empty();
                $('#modal-content-discount-surcharge-policy').html(append);
                $('#delete_confirm_discount_surcharge').modal('show');
            },
        });
    });
    </script>

    <script>
        $(document).on("click", "#addButton", function() {
        var count = $('.DivCountAttachment').length;
        var append = '';
        append += '<div class="kt-portlet__body">' +
            '<div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>' +
            ' <div class="form-group row">'+
            '<label for="example-text-input" class="col-3 col-form-label">Name</label>'+
            '<div class="col-6">'+
            '<input type="text" class="form-control" name="name['+ count +']"  title="Name is required" placeholder="Enter Name" required>'+
            '</div>'+
            '</div>'+
            '<div class="form-group row DivCountAttachment">' +
            '<label for="example-text-input" class="col-3 col-form-label">Document Type</label>' +
            '<div class="col-6">' +
            '<select class="form-control kt_selectpicker" data-live-search="true" title="Please Select document type" name="type['+ count +']" required>' +
            '@foreach($fileNames as $fileName)' +
            '<option value="{!! $fileName->value !!}">{!! $fileName->value !!}</option>' +
            '@endforeach' +
            '</select>' +
            '</div></div>' +
            '<div class="form-group row">' +
            '<label for="example-text-input" class="col-3 col-form-label">Attachment</label>' +
            '<div class="col-lg-9">' +
            '<div class="input-group appendFileDiv '+ count +'">' +
            '<div class="kt-avatar multiple_file_avatar" style="float: left; clear: left;">' +
            '<div class="kt-avatar__holder" style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>' +
            '<label class="kt-avatar__upload" data-toggle="kt-tooltip" title="Change Image">' +
            '<i class="fa fa-pen"></i>' +
            '<input type="file" class="attachment_file" name="attachment_file['+ count +'][]" />' +
            '</label>' +
            '<span class="kt-avatar__cancel" data-toggle="kt-tooltip" title="Cancel Image"> <i class="fa fa-times"></i> </span>' +
            '</div></div>' +
            '<br>'+
            '<div class="kt-separator kt-separator--space-sm"></div>' +
            '<div>' +
            '<span class="btn btn-info btn-sm addAttachment"> <i class="la la-plus"></i> Add </span>' +
            '</div></div>' +
            '<div>' +
            '<input type="button" class="btn btn-warning btn-sm removeAttachmentDiv" value="Remove">' +
            '</div>'
        $('#attachmentDiv').append(append);
        KTAvatarDemo.init();
    });
    </script>

    <script>
        $("#purpose").change(function(){
        var selectedPurpose = $(this).children("option:selected").val();
        if(selectedPurpose == 29){
            $('#purposeModal').modal('show');
            $("#purpose").val([]);
        }
        else{
            $('#purposeModal').modal('hide');
        }

    });

    // "use strict";
    // var KTDatatablesDataSourceAjaxServer = function() {

    //     var initTable13 = function() {
    //                 var table = $('#scheduled_trasactions_table');
    //                 // begin first table
    //                 table.DataTable({
    //                     responsive: true,
    //                     searchDelay: 500,
    //                     processing: true,
    //                     serverSide: true,
    //                     order: [1, 'DESC'],
    //                     ajax: "{!! route('admin.policy.scheduleTransactionsData', $policy->policyNumber) !!}",
    //                     columns: [{
    //                             data: 'id'
    //                         },
    //                         {
    //                             data: 'policy_number'
    //                         },
    //                         {
    //                             data: 'installment'
    //                         },
    //                         {
    //                             data: 'billing_date'
    //                         },
    //                         {
    //                             data: 'premium'
    //                         },
    //                         {
    //                             data: 'token'
    //                         },
    //                         {
    //                             data: 'retry_count'
    //                         },
    //                         {
    //                             data: 'status'
    //                         },
    //                         {
    //                             data: 'reason'
    //                         },
    //                         {
    //                             data: 'added_by'
    //                         },
    //                         @if(auth::user()->hasPermissionTo('policy-make_payment_dpo'))
    //                         {
    //                             data: 'action'
    //                         },
    //                         @endif
    //                         {
    //                             data: 'created_at'
    //                         },

    //                     ],

    //                 });

    //             };
    //     var initTable235 = function()
    //     {
    //         var table = $('#discount_surcharge_policy_table');
    //         // begin first table
    //         table.DataTable({
    //             responsive: true,
    //             searchDelay: 500,
    //             language:{
    //                 processing : "<img src='{{asset('img/loading.gif')}}'>"
    //             },
    //             processing: true,
    //             serverSide: true,
    //             ajax: '{!! route('admin.policy.discountSurchargePolicyTable',$policy->id) !!}',
    //             columns: [
    //                 {data: 'policy_id'},
    //                 {data: 'discount'},
    //                 {data: 'surcharge'},
    //                 {data: 'old_value'},
    //                 {data: 'new_value'},
    //                 {data: 'total_dis_surc'},
    //                 {data: 'user_id'},
    //             ],
    //             error: function(error) {
    //                 console.log(error);
    //             },
    //         });
    //     };

    //     var initTable234 = function()
    //     {
    //         var table = $('#sms_email_table');
    //         // begin first table
    //         table.DataTable({
    //             responsive: true,
    //             searchDelay: 500,
    //             language:{
    //                 processing : "<img src='{{asset('img/loading.gif')}}'>"
    //             },
    //             processing: true,
    //             serverSide: true,
    //             ajax: '{!! route('admin.policy.smsEmailData',$policy->id) !!}',
    //             columns: [
    //                 {
    //                     data: 'type'
    //                 },
    //                 {
    //                     data: 'message_id'
    //                 },
    //                 {
    //                     data: 'message'
    //                 },
    //                 // {
    //                 //     data: 'content'
    //                 // },
    //                 {
    //                     data: 'hook'
    //                 },
    //                 // {
    //                 //     data: 'attachments'
    //                 // },
    //                 {
    //                     data: 'send_to'
    //                 },
    //                 {
    //                     data: 'created_at'
    //                 },
    //                 // {
    //                 //     data: 'status'
    //                 // },
    //                 // {
    //                 //     data: 'actions'
    //                 // },
    //             ],
    //             error: function(error) {
    //                 console.log(error);
    //             },
    //         });
    //     };

    //     var initTable233 = function()
    //     {
    //         var table = $('#attatchment_table');
    //         // begin first table
    //         table.DataTable({
    //             responsive: true,
    //             searchDelay: 500,
    //             language:{
    //                 processing : "<img src='{{asset('img/loading.gif')}}'>"
    //             },
    //             processing: true,
    //             serverSide: true,
    //             ajax: '{!! route('admin.policy.attachmentData',$policy->id) !!}',
    //             columns: [
    //                 {data: 'name'},
    //                 {data: 'type'},
    //                 {data: 'attachment'},
    //                 {data: 'actions'},
    //             ],
    //             error: function(error) {
    //                 console.log(error);
    //             },
    //         });
    //     };

    //     var initTable8 = function() {
    //         var table = $('#paymentUrlTable');
    //         // begin first table
    //         table.DataTable({
    //             responsive: true,
    //             searchDelay: 500,
    //             processing: false,
    //             serverSide: true,
    //             ajax: "{!! route('admin.policy.paymentURLdata', $policy->id) !!}",
    //             columns: [{
    //                 data: 'id'
    //             },
    //                 {
    //                     data: 'created_at'
    //                 },
    //                 {
    //                     data: 'amount'
    //                 },
    //                 {
    //                     data: 'request_from'
    //                 },
    //                 {
    //                     data: 'url'
    //                 },
    //                 {
    //                     data: 'link_type'
    //                 },
    //                 {
    //                     data: 'status'
    //                 },
    //                 {
    //                     data: 'created_by'
    //                 },
    //                 {
    //                     data: 'actions'
    //                 },
    //             ],
    //             error: function(error) {
    //                 console.log(error);
    //             },
    //         });
    //     };

    //     var initTable10 = function() {
    //         var table = $('#document_table');

    //         // begin first table
    //         table.DataTable({
    //             responsive: true,
    //             searchDelay: 500,
    //             processing: false,
    //             serverSide: true,
    //             ajax: "{!! route('admin.policy.sentDocuments',$policy->policyNumber) !!}",
    //             order: [0, 'DESC'],
    //             columns: [{
    //                 data: 'id'
    //             }, {
    //                 data: 'policy_id'
    //             }, {
    //                 data: 'email'
    //             }, {
    //                 data: 'doc'
    //             },{
    //                 data: 'documents'
    //             },{
    //                 data: 'sentBy'
    //             }, {
    //                 data: 'created_at'
    //             },

    //             ],
    //             error: function(error) {
    //                 console.log(error);
    //             },

    //         });
    //     };

    //     var initTable57 = function() {
    //         var table = $('#DPOPaymenttable');
    //         // begin first table
    //         table.DataTable({

    //             columnDefs: [{
    //                             width: 200,
    //                             targets: 5
    //                         },
    //                         {
    //                             width: 300,
    //                             targets: 6
    //                         },
    //                         {
    //                             width: 300,
    //                             targets: 7
    //                         }
    //                     ],

    //             responsive: true,
    //             searchDelay: 500,
    //             processing: false,
    //             serverSide: true,
    //             ajax: "{!! route('admin.policy.dpoPaymentRecord', $policy->policyNumber) !!}",
    //             order: [0, 'DESC'],
    //             columns: [
    //                         { data: 'id' },
    //                         { data: 'policy_number' },
    //                         { data: 'amount'},
    //                         { data: 'api_name' },
    //                         { data: 'request_json' },
    //                         { data: 'response_json'},
    //                         { data: 'CompanyAccRef' },
    //                         { data: 'TransToken' },
    //                         { data: 'reason'},
    //                         { data: 'created_at'},
    //             ],
    //             error: function(error) {
    //                 console.log(error);
    //             },
    //         });
    //     };

    //     var initTable101 = function() {
    //             var table = $('#policy_document_table');
    //             // begin first table
    //             table.DataTable({
    //                 responsive: true,
    //                 searchDelay: 500,
    //                 processing: false,
    //                 serverSide: true,
    //                 ajax: "{!! route('admin.policy.getDocuments',$policy->policyNumber) !!}",
    //                 order: [0, 'DESC'],
    //                 columns: [{
    //                     data: 'id'
    //                 }, {
    //                     data: 'policyNumber'
    //                 }, {
    //                     data: 'term_id'
    //                 }, {
    //                     data: 'doc_path'
    //                 }, {
    //                     data: 'file_name'
    //                 }, {
    //                     data: 'added_by'
    //                 },{
    //                     data: 'created_at'
    //                 },

    //                 ],
    //                 error: function(error) {
    //                     console.log(error);
    //                 }
    //             });
    //         };

    //     return {
    //         //main function to initiate the module
    //         init: function() {
    //             initTable235();
    //             initTable234();
    //             initTable233();
    //             initTable8();
    //             initTable10();
    //             initTable101();
    //             initTable13();
    //             initTable57();
    //         }
    //     };
    // }();

    var ActivityTable = function() {
        var initTable2 = function() {
            var table = $('#activity_table');

            // begin first table
            table.DataTable({
                // "columns" : [
                //     { Width: '5%' },
                //     { Width: '5%' },
                //     { Width: '20%' },
                //     { Width: '20%' },
                //     { Width: '20%' },
                //     { Width: '5%' },
                //     { Width: '5%' },
                //     { Width: '5%' },
                //     { Width: '5%' },
                // ],
                columnDefs: [
                    { width: 200, targets: 5 },
                    { width: 300, targets: 6 },
                    { width: 300, targets: 7 }
              ],
                responsive: true,
                searchDelay: 500,
                processing: true,
                serverSide: true,
                ajax: "{!! route('admin.policy.activity',$policy->id) !!}",
                columns: [{
                    data: 'id'
                }, {
                    data: 'user_id'
                },
                {
                    data: 'ip_address'
                },
                {
                    data: 'user_agent'
                },
                {
                    data: 'tag'
                },
                {
                    data: 'url'
                },
                {
                    data: 'old_values'
                },
                {
                    data: 'new_values'
                },
                {
                    data: 'created_at'
                }, ],

            });
        };
        return {
            init: function() {
                initTable2();
            }
        };
    }();

    var ClaimsTable = function() {
        var initTable1 = function() {
            var table = $('#claims_table');

            // begin first table
            table.DataTable({
                responsive: true,
                searchDelay: 500,
                language: {
                    processing: "<img src='{{asset('img/loading.gif')}}'>"
                },
                processing: true,
                serverSide: true,
                order: [1, 'DESC'],
                ajax: "{!! route('admin.policy.getclaimsdata', $policy->id)!!}",
                columns: [{
                    data: 'claim_number'
                }, {
                    data: 'claim_type'
                }, {
                    data: 'status'
                }, {
                    data: 'claim_sub_status'
                },

                ],
            });
        };
        return {
            init: function() {
                initTable1();
            }
        };
    }();
    "use strict";
    var FilterBy = -1;
    var FilterByRefund = -1;
    var filterDateFrom = -1;
    var filterDateTo = -1;
    $('#FilterBy').on('change',function(){
        FilterBy = $(this).val();
    });
    $('#FilterByRefund').on('change',function(){
        FilterByRefund = $(this).val();
    });
    $('#filterDateFrom').on('change',function(){
                filterDateFrom = $(this).val();
            });
    $('#filterDateTo').on('change',function(){
        filterDateTo = $(this).val();
    });
    $('#Filterdate').on('click',function(){
                filterDateFrom = $('#filterDateFrom').val();
                filterDateTo = $('#filterDateTo').val();
                FilterBy = $('#FilterBy').val();
                FilterByRefund = $('#FilterByRefund').val();
            });
    var TransactionLogTable = function() {
        var table = '';
        var initTable9 = function() {
           // var table = $('#transaction_table');

            // begin first table
            table = $('#transaction_table').DataTable({
                responsive: true,
                searchDelay: 500,
                language: {
                    processing: "<img src='{{ asset('img/loading.gif') }}'>"
                },
                processing: true,
                serverSide: true,
                ajax: {
                           url: "{!! route('admin.policy.paymentTransactions', $policy->policyNumber) !!}",
                            data: function (d) {
                                d.FilterBy = FilterBy;
                                d.FilterByRefund = FilterByRefund;
                                d.filterDateFrom = filterDateFrom;
                                d.filterDateTo = filterDateTo;
                            }
                            },
                order: [0, 'DESC'],
                columns: [{
                    data: 'id'
                }, {
                    data: 'policyNumber'
                },{
                    data: 'contractNumber'
                },{
                    data: 'referenceNumber'
                }, {
                    data: 'paymentMethod'
                }, {
                    data: 'paymentFrequency'
                }, {
                    data: 'amount'
                }, {
                    data: 'paymentDate'
                }, {
                    data: 'created_at'
                }, {
                    data: 'is_ledger'
                }, {
                    data: 'numberOfInstalmentsPaid'
                }, {
                    data: 'note'
                }, {
                    data: 'status'
                }, {
                    data: 'cashRecipient'
                },{
                    data: 'paymentLoggedBy'
                },{
                    data: 'actions'
                },
                ],
                error: function(error) {
                    console.log(error);
                },

            });
        };
        return {
            init: function() {
                initTable9();
            },
            draw: function(){
                        table.draw();
                    }
        };
    }();
    $('#FilterBy').on('change',function(){
                FilterBy = $(this).val();
                TransactionLogTable.draw();
            });
            $('#FilterByRefund').on('change',function(){
                FilterByRefund = $(this).val();
                TransactionLogTable.draw();
            });
            $('#filterDateFrom').on('change',function(){
                filterDateFrom = $(this).val();
                TransactionLogTable.draw();
            });
            $('#filterDateTo').on('change',function(){
                filterDateTo = $(this).val();
                TransactionLogTable.draw();
            });
            $('#Filterdate').on('click',function(){
                filterDateFrom = $('#filterDateFrom').val();
                filterDateTo = $('#filterDateTo').val();
                FilterBy = $('#FilterBy').val();
                FilterByRefund = $('#FilterByRefund').val();
                TransactionLogTable.draw();
            });

    var RealPayContractListTable = function() {
        var initTablerealpayContract1 = function() {
            // begin first table
            table = $('#contract_list_table').DataTable({
                responsive: true,
                processing: true,
                language: {
                    processing: "<img src='{{asset('img/loading.gif')}}'>"
                },
                serverSide: true,
                ajax: {
                    url: '{!! route("admin.clientContractList",$policy->id) !!}',
                    data: {
                        "_token": "{{ csrf_token() }}",
                        "policy_id": '{{ $policy->id }}'
                    },
                },
                order: [0, 'DESC'],
                columns: [
                    {
                    data: 'id'
                    },
                    // {
                    //     data: 'ContractSequence'
                    // },
                    {
                        data: 'client_number'
                    },
                    {
                        data: 'contract_number'
                    },
                    // {
                    //     data: 'CTCPercentage'
                    // },
                    // {
                    //     data: 'InstalmentStartDate'
                    // },
                    // {
                    //     data: 'TrackingCode'
                    // },
                    // {
                    //     data: 'NumberOfInstalments'
                    // },
                    // {
                    //     data: 'first_collection_date'
                    // },
                    // {
                    //     data: 'first_collection_amount'
                    // },
                    // {
                    //     data: 'FrequencyCode'
                    // },
                    // {
                    //     data: 'CollectionDay'
                    // },
                    {
                        data: 'status'
                    },
                    {
                        data: 'actions'
                    }
                ],

            });
        };
        return {
            init: function() {
                initTablerealpayContract1();
            }
        };
    }();

    var PolicyTermTable = function() {
        var initTablePolicyTerm1 = function() {
            // begin first table
            table = $('#policyTerm_table').DataTable({
                responsive: true,
                processing: true,
                language: {
                    processing: "<img src='{{asset('img/loading.gif')}}'>"
                },
                serverSide: true,
                order: [0, 'DESC'],
                ajax: {
                    url: '{!! route("admin.policy.getPolicyTermsData",$policy->id) !!}',
                    data: {
                        "_token": "{{ csrf_token() }}",
                        "policy_id": '{{ $policy->id }}'
                    },
                },
                columns: [{
                    data: 'id'
                },
                    {
                        data: 'term_start_date'
                    },
                    {
                        data: 'term_end_date'
                    },
                    {
                        data: 'annual_premium'
                    },
                    {
                        data: 'frequency'
                    },
                    {
                        data: 'trans_type'
                    },
                    {
                        data: 'status'
                    },
                    {
                        data: 'actions'
                    },
                    // {
                    //     data: 'InstalmentStatus'
                    // },
                    // {
                    //     data: 'retry_count'
                    // },
                    // {
                    //     data: 'action'
                    // },
                ],

            });
        };
        return {
            init: function() {
                initTablePolicyTerm1();
            }
        };
    }();


    var PolicyReinstateTable = function() {
        var initTablePolicyReinstate1 = function() {
            // begin first table
            table = $('#policyReinstate_table').DataTable({
                responsive: true,
                processing: true,
                language: {
                    processing: "<img src='{{asset('img/loading.gif')}}'>"
                },
                serverSide: true,
                order: [0, 'DESC'],
                ajax: {
                    url: '{!! route("admin.policy.getPolicyReinstateData",$policy->id) !!}',
                    data: {
                        "_token": "{{ csrf_token() }}",
                        "policy_id": '{{ $policy->id }}'
                    },
                },
                columns: [{
                    data: 'id'
                },
                    {
                        data: 'policyNumber'
                    },
                    {
                        data: 'reinstated_by'
                    },
                    {
                        data: 'reinstated_date'
                    },
                    {
                        data: 'policyActivatedDate'
                    },
                    {
                        data: 'policyCancelledDate'
                    },
                    {
                        data: 'reinstate_type'
                    },
                    {
                        data: 'is_reinstate'
                    },
                    {
                        data: 'is_rerated'
                    },
                ],

            });
        };
        return {
            init: function() {
                initTablePolicyReinstate1();
            }
        };
    }();

    /****** Policy LifeCycle *******/

    var PolicyLifeCycle= function(){
            var initTablePolicyLifecycle = function(){
                table = $('#policyLifeCycle_table').DataTable({
                responsive: true,
                processing: true,
                language: {
                    processing: "<img src='{{asset('img/loading.gif')}}'>"
                },
                serverSide: true,
                order: [0, 'DESC'],
                ajax: {
                    url: '{!! route("admin.policy.policyLifeCycle",$policy->id) !!}',
                    data: {
                        "_token": "{{ csrf_token() }}",
                        "policy_id": '{{ $policy->id }}'
                    },
                },
                columns: [{
                    data: 'id'
                },
                    // {
                    //     data: 'term_start_date'
                    // },
                    // {
                    //     data: 'term_end_date'
                    // },
                    {
                        data: 'premium'
                    },
                    {
                        data: 'frequency'
                    },
                    {
                        data:'billingStartDate'
                    },
                    {
                        data: 'actionBy'
                    },
                    {
                        data: 'actionDate'
                    },
                    // {
                    //     data: 'status'
                    // },
                    {
                        data: 'action'
                    },
                    // {
                    //     data: 'InstalmentStatus'
                    // },
                    // {
                    //     data: 'retry_count'
                    // },
                    // {
                    //     data: 'action'
                    // },
                ],

            });

        };
        return {
            init: function() {
                initTablePolicyLifecycle();
            }
        };
    }();

/******* End policyLife Cycle ********/

    var RealPayTable = function() {
        var initTablerealpay1 = function() {
            // begin first table
            table = $('#product_table').DataTable({
                responsive: true,
                processing: true,
                language: {
                    processing: "<img src='{{asset('img/loading.gif')}}'>"
                },
                serverSide: true,
                ajax: {
                    url: '{!! route("admin.getContractInstallments",$policy->id) !!}',
                    data: {
                        "_token": "{{ csrf_token() }}",
                        "policy_id": '{{ $policy->id }}'
                    },
                },
                columns: [{
                    data: 'InstalmentSequence'
                },
                    {
                        data: 'InstalmentReferenceNumber'
                    },
                    {
                        data: 'CTCAmount'
                    },
                    {
                        data: 'InstalmentActionDate'
                    },
                    {
                        data: 'TrackingCode'
                    },
                    {
                        data: 'InstalmentAmount'
                    },
                    {
                        data: 'instalmentResponse'
                    },
                    {
                        data: 'InstalmentStatus'
                    },
                    {
                        data: 'retry_count'
                    },
                    {
                        data: 'action'
                    },
                ],

            });
        };
        return {
            init: function() {
                initTablerealpay1();
            }
        };
    }();

    var LedgetDataTables = function() {

        var initTable4 = function() {
            var table = $('#account_table');

            // begin first table
            table.DataTable({
                responsive: true,
                searchDelay: 500,
                processing: true,
                serverSide: true,
                dom: 'Blfrtip',
                buttons: [
                    'excelHtml5',
                    'csvHtml5',
                ],
                ajax: "{!! route('admin.ledgerAccountViewSonali', $policy->id)!!}",
                order: [0, 'DESC'],
                columns: [{
                    data: 'id',
                    "bVisible": false
                }, {
                    data: 'accounting_date'
                }, {
                    data: 'trans_type'
                }, {
                    data: 'trans_ref'
                }, {
                    data: 'customer_name'
                }, {
                    data: 'orig_trans'
                }, {
                    data: 'unallocated'
                }, {
                    data: 'debit'
                }, {
                    data: 'credit'
                }, {
                    data: 'balance'
                }, {
                    data: 'system_date'
                },

                ],

            });
        };
        var initTable5 = function() {
            var table = $('#recievable_table');

            // begin first table
            table.DataTable({
                responsive: true,
                searchDelay: 500,
                processing: true,
                serverSide: true,
                dom: 'Blfrtip',
                buttons: [
                    'excelHtml5',
                    'csvHtml5',
                ],
                ajax: "{!! route('admin.policy.recievableData', $policy->id)!!}",
                order: [0, 'DESC'],
                columns: [{
                    data: 'id',
                    "bVisible": false
                }, {
                    data: 'accounting_date'
                }, {
                    data: 'trans_type'
                }, {
                    data: 'trans_sub_type'
                }, {
                    data: 'trans_ref'
                }, {
                    data: 'eff_date'
                }, {
                    data: 'credit'
                }, {
                    data: 'debit'
                }, {
                    data: 'balance'
                },

                ],

            });
        };
        var initTable6 = function() {
            var table = $('#invoicing_table');

            // begin first table
            table.DataTable({
                responsive: true,
                searchDelay: 500,
                processing: true,
                serverSide: true,
                dom: 'Blfrtip',
                buttons: [
                    'excelHtml5',
                    'csvHtml5',
                ],
                ajax: "{!! route('admin.policy.invoicingDataSonali', $policy->id)!!}",
                order: [0, 'DESC'],
                columns: [{
                    data: 'id',
                    "bVisible": false
                }, {
                    data: 'accounting_date'
                }, {
                    data: 'invoice_no'
                }, {
                    data: 'premium'
                }, {
                    data: 'other_charges'
                }, {
                    data: 'due_amount'
                }, {
                    data: 'balance'
                }, {
                    data: 'pmts_adjust'
                }, {
                    data: 'invoice_amount'
                }, {
                    data: 'due_date'
                }, {
                    data: 'status'
                },
                {
                    data: 'actions'
                }

                ],

            });
        };
        var initTable7 = function() {
            var table = $('#sub_ledger_table');

            // begin first table
            table.DataTable({
                responsive: true,
                searchDelay: 500,
                processing: true,
                serverSide: true,
                dom: 'Blfrtip',
                buttons: [
                    'excelHtml5',
                    'csvHtml5',
                ],
                ajax: "{!! route('admin.policy.subLedgerDataSonali', $policy->id)!!}",
                order: [0, 'ASC'],
                columns: [{
                    data: "id",
                    "bVisible": false
                }, {
                    data: 'accounting_date'
                }, {
                    data: 'trans_type'
                }, {
                    data: 'trans_ref'
                }, {
                    data: 'account_name'
                }, {
                    data: 'debit'
                }, {
                    data: 'credit'
                }

                ],

            });
        };

        return {
            //main function to initiate the module
            init: function() {
                initTable4();
                initTable5();
                initTable6();
                initTable7();
            }
        };
    }();

      // Discount Surcharge
      var DiscountSurchargeDataTables = function() {
                  var initTable235 = function() {
                    var table = $('#discount_surcharge_policy_table');
                    // begin first table
                    table.DataTable({
                        responsive: true,
                        searchDelay: 500,
                        language: {
                            processing: "<img src='{{ asset('img/loading.gif') }}'>"
                        },
                        processing: true,
                        serverSide: true,
                        ajax: "{!! route('admin.policy.discountSurchargePolicyTable', $policy->id) !!}",
                        columns: [{
                                data: 'policy_id'
                            },
                            {
                                data: 'discount'
                            },
                            {
                                data: 'surcharge'
                            },
                            {
                                data: 'old_value'
                            },
                            {
                                data: 'new_value'
                            },
                            {
                                data: 'total_dis_surc'
                            },
                            {
                                data: 'user_id'
                            },
                            {
                                data: 'created_at'
                            },
                        ],
                        error: function(error) {
                            console.log(error);
                        },
                    });
                };
                return {
                    //main function to initiate the module
                    init: function() {
                        initTable235();
                    }
                };
            }();

            // sms_email_log
            var SmsEmailLogDataTables = function() {
                  var initTable234 = function() {
                    var table = $('#sms_email_table');
                    // begin first table
                    table.DataTable({
                        responsive: true,
                        searchDelay: 500,
                        language: {
                            processing: "<img src='{{ asset('img/loading.gif') }}'>"
                        },
                        processing: true,
                        serverSide: true,
                        ajax: '{!! route('admin.policy.smsEmailData', $policy->id) !!}',
                        columns: [{
                                data: 'type'
                            },
                            {
                                data: 'message_id'
                            },
                            {
                                data: 'message'
                            },
                            // {
                            //     data: 'content'
                            // },
                            {
                                data: 'hook'
                            },
                            // {
                            //     data: 'attachments'
                            // },
                            {
                                data: 'send_to'
                            },
                            {
                                data: 'created_at'
                            },
                            // {
                            //     data: 'status'
                            // },
                            // {
                            //     data: 'actions'
                            // },
                        ],
                        error: function(error) {
                            console.log(error);
                        },
                    });
                };
                return {
                    //main function to initiate the module
                    init: function() {
                        initTable234();
                    }
                };
            }();

            var AttachmentDataTables = function() {
                  var initTable233 = function() {
                    var table = $('#attatchment_table');
                    // begin first table
                    table.DataTable({
                        responsive: true,
                        searchDelay: 500,
                        language: {
                            processing: "<img src='{{ asset('img/loading.gif') }}'>"
                        },
                        processing: true,
                        serverSide: true,
                        ajax: '{!! route('admin.policy.attachmentData', $policy->id) !!}',
                        columns: [{
                                data: 'name'
                            },
                            {
                                data: 'type'
                            },
                            {
                                data: 'attachment'
                            },
                            {
                                data: 'actions'
                            },
                        ],
                        error: function(error) {
                            console.log(error);
                        },
                    });
                };
                return {
                    //main function to initiate the module
                    init: function() {
                        initTable233();
                    }
                };
            }();

            var PaymentUrlDataTables = function() {
                   var initTable8 = function() {
                    var table = $('#paymentUrlTable');
                    // begin first table
                    table.DataTable({
                        responsive: true,
                        searchDelay: 500,
                        processing: false,
                        serverSide: true,
                        ajax: "{!! route('admin.policy.paymentURLdata', $policy->id) !!}",
                        columns: [{
                                data: 'id'
                            },
                            {
                                data: 'created_at'
                            },
                            {
                                data: 'amount'
                            },
                            {
                                data: 'request_from'
                            },
                            {
                                data: 'url'
                            },
                            {
                                data: 'link_type'
                            },
                            {
                                data: 'status'
                            },
                            {
                                data: 'created_by'
                            },
                            {
                                data: 'actions'
                            },
                        ],
                        error: function(error) {
                            console.log(error);
                        },
                    });
                };
                return {
                    //main function to initiate the module
                    init: function() {
                        initTable8();
                    }
                };
            }();

            var SentDocumentsDataTables = function() {
                    var initTable10 = function() {
                    var table = $('#document_table');
                    // begin first table
                    table.DataTable({
                        responsive: true,
                        searchDelay: 500,
                        processing: false,
                        serverSide: true,
                        ajax: "{!! route('admin.policy.sentDocuments', $policy->policyNumber) !!}",
                        order: [0, 'DESC'],
                        columns: [{
                                data: 'id'
                            }, {
                                data: 'policy_id'
                            }, {
                                data: 'email'
                            }, {
                                data: 'doc'
                            }, {
                                data: 'documents'
                            }, {
                                data: 'sentBy'
                            }, {
                                data: 'created_at'
                            },
                        ],
                        error: function(error) {
                            console.log(error);
                        },
                    });
                };
                var initTable101 = function() {
                    var table = $('#policy_document_table');
                    // begin first table
                    table.DataTable({
                        responsive: true,
                        searchDelay: 500,
                        processing: false,
                        serverSide: true,
                        ajax: "{!! route('admin.policy.getDocuments', $policy->policyNumber) !!}",
                        order: [0, 'DESC'],
                        columns: [{
                                data: 'id'
                            }, {
                                data: 'policyNumber'
                            }, {
                                data: 'term_id'
                            }, {
                                data: 'doc_path'
                            }, {
                                data: 'file_name'
                            }, {
                                data: 'added_by'
                            }, {
                                data: 'created_at'
                            },

                        ],
                        error: function(error) {
                            console.log(error);
                        },

                    });
                };

                return {
                    //main function to initiate the module
                    init: function() {
                        initTable10();
                        initTable101();
                    }
                };
            }();

             var DPOPaymentDataTables = function() {
                    var initTable57 = function() {
                    var table = $('#DPOPaymenttable');
                    // begin first table
                    table.DataTable({
                        responsive: true,
                        searchDelay: 500,
                        processing: false,
                        serverSide: true,
                        ajax: "{!! route('admin.policy.dpoPaymentRecord', $policy->policyNumber) !!}",
                        order: [0, 'DESC'],
                        columnDefs: [
                                        { width: 10, targets: 0 },
                                        { width: 20, targets: 4 }
                                    ],
                        columns: [
                                  { data: 'id' },
                                  { data: 'policy_number' },
                                  { data: 'amount'},
                                  { data: 'api_name' },
                                  { data: 'request_json'},
                                //   { data: 'request_json', width: '10px' },
                                  { data: 'response_json'},
                                  { data: 'CompanyAccRef' },
                                  { data: 'TransToken' },
                                  { data: 'reason'},
                                  { data: 'created_at'},
                        ],
                        error: function(error) {
                            console.log(error);
                        },
                    });
                };
                return {
                    //main function to initiate the module
                    init: function() {
                        initTable57();
                    }
                };
            }();

            var ScheduledTransactionDataTables = function() {
                    var initTable13 = function() {
                    var table = $('#scheduled_trasactions_table');
                    // begin first table
                    table.DataTable({
                        responsive: true,
                        searchDelay: 500,
                        processing: true,
                        serverSide: true,
                        order: [1, 'DESC'],
                        ajax: "{!! route('admin.policy.scheduleTransactionsData', $policy->policyNumber) !!}",
                        columns: [{
                                data: 'id'
                            },
                            {
                                data: 'policy_number'
                            },
                            {
                                data: 'installment'
                            },
                            {
                                data: 'billing_date'
                            },
                            {
                                data: 'premium'
                            },
                            {
                                data: 'token'
                            },
                            {
                                data: 'retry_count'
                            },
                            {
                                data: 'status'
                            },
                            {
                                data: 'reason'
                            },
                            {
                                data: 'added_by'
                            },
                            @if(auth::user()->hasPermissionTo('policy-make_payment_dpo'))
                            {
                                data: 'action'
                            },
                            @endif
                            {
                                data: 'created_at'
                            },
                        ],
                    });
                };
                return {
                    //main function to initiate the module
                    init: function() {
                        initTable13();
                    }
                };
            }();

            var earned_premiumsDataTables = function() {
            var initTable44 = function() {
            var table = $('#earn_table');

                   // begin first table
                    table.DataTable({
                    responsive: true,
                    searchDelay: 500,
                    processing: true,
                    serverSide: true,

                        ajax: "{!! route('admin.policy.earned_premiumsView', $policy->id) !!}",
                        order: [0, 'ASC'],
                        columns: [{
                                data: 'accounting_date'
                            }, {
                                data: 'trans_type'
                            }, {
                                data: 'days'
                            }, {
                                data: 'written_preminum'
                            }, {
                                data: 'day_premium'
                            },

                        ],

                    });
                };
                return {
                    //main function to initiate the module
                    init: function() {
                        initTable44();


                    }
                };
            }();




    jQuery(document).ready(function() {
        KTDatatablesDataSourceAjaxServer.init();

        // var deviceType = '';

        // $(document).on('change','.device_type',function(){

        //     var selectMake = $(this).parent().parent().next().children().eq(0).find('select');
        //     var deviceType = $(this).val();
        //     ajaxRequest = setTimeout(function(sn) {
        //         $.ajax({
        //             url: "{{url('api/frontendpay/deviceBrands')}}",
        //             data: {
        //                 "_token": "{{ csrf_token() }}",
        //                 "deviceType":deviceType
        //             },
        //             type: 'post',
        //             datatype : 'json',
        //             beforeSend: function() {
        //                 $("#loader").show();
        //             },
        //             success: function (data) {
        //                 var brandName = data.Brands;
        //                 selectMake.empty();
        //                 $.each(brandName, function (key,val) {
        //                     selectMake.append('<option value="'+val.id+'">'+val.name+'</option>');
        //                 });
        //                 selectMake.append($('<option value="Other">Other</option>'));
        //             },
        //             error:function(){
        //                 //$("#cell_phone_make").append($('<option value="">NO '+deviceType+' BRAND FOUND</option>'));
        //             },
        //             complete:function(data){
        //                 $("#loader").hide();
        //             },
        //         });
        //     }, 200);
        // });

        // $(document).on('change','.cell_phone_make',function(){
        //     var selectModel = $(this).parent().parent().next().children().eq(0).find('select');
        //     var brand = $(this).val();
        //     var deviceType = $(this).val();
        //     $('#cell_phone_model').empty();
        //     if($(this).val() === 'Other') {
        //         $(this).parent().parent().next(".make_other").show();
        //         $(this).parent().parent().next().children().eq(1).rules('add', {required: true, messages: {required: "Please enter Make"}});
        //     } else {
        //         $(this).parent().parent().next(".make_other").hide();
        //         $(this).parent().parent().next().children().eq(1).rules('remove', 'required');
        //         ajaxRequest = setTimeout(function(sn) {
        //             $.ajax({
        //                 url: "{{url('api/frontendpay/deviceBrandModels')}}",
        //                 data: {
        //                     "_token": "{{ csrf_token() }}",
        //                     "brand":brand
        //                 },
        //                 type: 'post',
        //                 datatype : 'json',
        //                 beforeSend: function() {
        //                     $("#loader").show();
        //                 },
        //                 success: function (data) {
        //                     var ModelName = data.Models;
        //                     selectModel.empty();
        //                     $.each(ModelName, function (key,val) {
        //                         selectModel.append($('<option value="' + val.id + '">' + val.name + '</option>'));
        //                     });
        //                     selectModel.append($('<option value="Other">Other</option>'));
        //                 },
        //                 error:function(){
        //                     //selectModel.append($('<option value="">NO '+brand+' MODEL FOUND</option>'));
        //                 },
        //                 complete:function(data){
        //                     $("#loader").hide();
        //                 },
        //             });
        //         }, 200);
        //     }
        // });

        // $("#cellphonesection").on('change','.cell_phone_model',function () {
        //     if($(this).val() === 'Other') {
        //         $(this).parent().parent().next(".model_other").show();
        //         $(this).parent().parent().next().children().eq(1).rules('add', {required: true, messages: {required: "Please enter Model"}});
        //     } else {
        //         $(this).parent().parent().next(".model_other").hide();
        //         $(this).parent().parent().next().children().eq(1).rules('remove', 'required');
        //     }
        // });



        $(document).on('click', '.removeAttachment', function() {
            var attachment_id  = $(this).attr('data-id');
            var claim_id       = $(this).attr('data-claim-id');
            var attachment_div = $('#attachment_'+ claim_id + attachment_id);
            /* console.log('attachment_id',attachment_id);
            console.log('claim_id',claim_id);
            console.log('attachment_div',attachment_div); */

            if(claim_id != '' && attachment_id != '' && attachment_div != '')
            {
                $('#remove_attachment').modal('show');
                $(document).on('click', '#submitRemoveAttachment', function () {
                    $.ajax({
                        url: '{{ route("admin.policy.removeAttachment") }}',
                        data: {
                            "_token"       : "{{ csrf_token() }}",
                            "claim_id"     : claim_id,
                            "attachment_id": attachment_id
                        },
                        beforeSend: function() {
                            $("#loader").show();
                            attachment_div.addClass('dim');
                        },
                        type: 'post',
                        datatype: 'json',
                        success: function(data) {
                            attachment_div.remove();
                            alert('Attachment Deleted successfully');
                        },
                        error: function(data) {
                            attachment_div.removeClass('dim');
                            alert('Attachment failed to deleted.');
                        },
                        complete: function(data) {
                            $("#loader").hide();
                            $('#remove_attachment').modal('hide');
                        },

                    });
                });
            }else{
                alert('Please provide required data')
            }
        });



        $(document).on('click', '.getOTPButton', function() {
            if (cellphone) {
                var maskedCellphone = cellphone.slice(0, 2);
                maskedCellphone = maskedCellphone + '####' + cellphone.slice(6, 8) + '.';
                event.preventDefault();
                $.ajax({
                    url: '{{ route('admin.policy.getModalData') }}',
                    data: {
                        "_token": "{{ csrf_token() }}",
                        "cellphone": cellphone
                    },
                    beforeSend: function() {
                        $("#loader").show();
                    },
                    type: 'post',
                    datatype: 'json',

                    success: function(data) {
                        $("#loader").hide();
                        $('#customer_OTP_options').modal('hide');
                        $('#data_body').html(data.body + maskedCellphone);
                        $('#submit_otp').modal('show');
                    },
                })
            } else {
                $('#customer_no_cellphone').modal('show');
            }

        });

                $(document).on('click', '.submitOTP', function() {
                    var phoneNumber = $('#cphnumber').val();
                    var otpCode = $('.otpInput').val();

                    $('.clear').on('click', function() {
                        $('.otpInput').val('');
                        $('.wrongOTP').slideUp();
                        $('.noPaymentRef').slideUp();
                    })

                    verifyOTP(phoneNumber, otpCode);
                });

                function verifyOTP(phoneNumber, otpCode) {
                    var ajaxRequest = setTimeout(function(sn) {
                        $.ajax({
                            url: '{{ route('admin.policy.otpVerification') }}',
                            data: {
                                "_token": "{{ csrf_token() }}",
                                "phoneNumber": phoneNumber,
                                "otpCode": otpCode,
                            },
                            beforeSend: function() {
                                $("#loader").show();
                            },
                            type: 'post',
                            datatype: 'json',
                            success: function(data) {
                                if (data.status == 'success') {
                                    $("#loader").hide();
                                    $('#submit_otp').modal('hide');
                                    $('#myModal').modal('show');
                                } else {
                                    $("#loader").hide();
                                    var append2 =
                                        '<div class="modal-header"><h5 class="modal-title" id="exampleModalLabel" style="color: red">Customer Authentication Failed.Please retry</h5>' +
                                        '<button type="button" class="close" data-dismiss="modal" aria-label="Close"> <span aria-hidden="true">&times;</span> </button> ' +
                                        '</div> ' +
                                        '<div class="modal-body" style="text-align: center"> ' +
                                        '<p>Entered OTP is not correct. Please enter valid OTP.</p> ' +
                                        '<div style="text-align: center">' +
                                        '<input type="number" class="form-control otpInput" id="otpInput" name="otpInput" placeholder="Please enter OTP." >' +
                                        '</div> ' +
                                        '</div> ';
                                    $('#modal-customer_OTP').empty();
                                    $('#modal-customer_OTP').append(append2);
                                    $('#customer_OTP').modal('show');
                                    $('#customer_OTP_already').modal('hide');
                                    $("#loader").hide();
                                }

                                getCustomerFeebackOption();

                            },
                            error: function(data) {
                                $('#loader').css("display", "none");
                                $('.otpInput').val('');

                                if (data.status == 401)
                                    $('.wrongOTP').slideDown();

                                if (data.status == 402)
                                    $('.noPaymentRef').slideDown();


                            }
                        });
                    }, 200);
                }
                });

            $(document).on('change', '.custom-control-input', function() {
                var $this = $(this);
                var input = $this.attr('data-input'); //0 to n
                var subinput = $('div[id="option' + input + '"]');
                subinput.prop('hidden', false);
                $('input[name="circum"]').prop('checked', false);
                var otherSubinput = $('div[id!="option' + input + '"][class="option"]');
                otherSubinput.prop('hidden', true);
                var val = $this.val();
                if (val == 'other') {
                    $('div[id="optionOther"]').prop('hidden', false);
                } else {
                    $('div[id="optionOther"]').prop('hidden', true);
                }
            });

            $("#modal-feedback").validate({
                rules: {
                    other_reason: {
                        required: function(element) {
                            return $("input[name='reason']:checked").val() == "other";
                        },
                        minlength: 10,
                    },
                    otherCompany: {
                        required: function(element) {
                            return $("input[name='reason']:checked").val() ==
                                "I Found Cheaper Insurance Else Where";
                        },
                    },
                    reason: {
                        required: true,
                    },
                    circum: {
                        required: function(element) {
                            return $("input[name='reason']:checked").val() == "I Can Not Afford Insurance Anymore";
                        },
                    },
                },
                messages: {
                    other_reason: {
                        required: "Please tell us reason."
                    },
                    otherCompany: {
                        required: "Please tell us which company you are moving."
                    },
                    reason: {
                        required: "Please tell us reason.",
                    },
                    circum: {
                        required: "Please tell us your circumstances.",
                    },
                }
            });



    </script>

    <script>
        var dataID = '';

        $("#product_table").on("click", "a.confirm-cancel" , function(event) {
            event.preventDefault();
            var id = $(this).attr('value');
            $.ajax({
                url: '{{ route('confirm-cancel') }}',
                data: {
                    "_token": "{{ csrf_token() }}",
                    "id": id
                },
                type: 'post',
                datatype : 'json',
                success: function(data) {
                    $('#realpay-modal-content').append(append);
                    var append = '<div class="modal-header"><h5 class="modal-title" id="exampleModalLabel">Cancel Instalment</h5>' +
                        '<button type="button" class="close" data-dismiss="modal" aria-label="Close"> <span aria-hidden="true">&times;</span> </button> ' +
                        '</div> ' +
                        '<div class="modal-body"> ' +
                        '<p>'+data.body+'</p> ' +
                        '<p>Please provide the reason for cancellation</p> ' +
                        '<textarea id="cancellationreason" style="height:auto;width:100%"></textarea>'+
                        '</div> ' +
                        '<div class="modal-footer"> ' +
                        '<button type="button" class="btn btn-outline-brand" data-dismiss="modal">No</button> ';

                    if(data.status == 'success'){
                        dataID = data.id;
                        append += '<a href="#" id="confirm-cancel-button" type="button" class="btn btn-brand">Yes,Cancel</a></div>';

                    }

                    append += '</div>';
                    $('#realpay-modal-content').empty();
                    $('#realpay-modal-content').append(append);
                    $('#realpay_delete_confirm').modal('show');
                },
            });
        });

        $(document).on('click', '#confirm-cancel-button', function(){
            var product_id = '{{ $policy->product_id }}';
            if (product_id == 3) {
                var url = '{{ route("admin.updateStatus", [":id","I",":reason"]) }}';
            } else {
                var url = '{{ route("admin.updateStatusForInstantProduct", [":id","I",":reason"]) }}';
            }
            url = url.replace(':id', dataID);
            url = url.replace(':reason',$('textarea#cancellationreason').val());
            window.location.href = url;
        })
    </script>

    <script>
        var KTBootstrapDatepicker = function () {

        var arrows;
        if (KTUtil.isRTL()) {
            arrows = {
                leftArrow: '<i class="la la-angle-right"></i>',
                rightArrow: '<i class="la la-angle-left"></i>'
            }
        } else {
            arrows = {
                leftArrow: '<i class="la la-angle-left"></i>',
                rightArrow: '<i class="la la-angle-right"></i>'
            }
        }

        // Private functions
        var demo = function () {

            var date = new Date();
            var firstDay = new Date(date.getFullYear(), date.getMonth(), 1);
            var lastDay = new Date(date.getFullYear(), date.getMonth() + 1, 0);

            $("#date_of_refund_for_current_month").datepicker({
            startDate: firstDay,
            endDate: lastDay,
            format: 'yyyy-mm-dd'
            });

            $("#date_of_refund_for_current_month_refund").datepicker({
            startDate: firstDay,
            endDate: lastDay,
            format: 'yyyy-mm-dd'
            });
            // minimum setup
            $('.kt_datepicker_1').datepicker({
                rtl: KTUtil.isRTL(),
                todayHighlight: true,
                orientation: "bottom left",
                templates: arrows,
                format: 'yyyy-mm-dd'
            });

            $('.kt_datepicker_1_rerate').datepicker({
                rtl: KTUtil.isRTL(),
                todayHighlight: true,
                orientation: "bottom left",
                templates: arrows,
                format: 'dd-mm-yyyy'
            });

            $('.kt_datepicker_today_1').datepicker({
                rtl: KTUtil.isRTL(),
                todayHighlight: true,
                orientation: "bottom left",
                // templates: arrows,
                format: 'yyyy-mm-dd',
                startDate: "today",
            });

            $('.kt_datepicker_12').datepicker({
                    rtl: KTUtil.isRTL(),
                    todayHighlight: true,
                    orientation: "bottom left",
                    templates: arrows,
                    format: 'yyyy-mm-dd',
                    endDate: "today",
            });

            $('.kt_datepicker_123').datepicker({
                    rtl: KTUtil.isRTL(),
                    todayHighlight: true,
                    orientation: "bottom left",
                    templates: arrows,
                    format: 'yyyy-mm-dd',
                    startDate: "today",
            });

        }
        return {
            // public functions
            init: function() {
                demo();
            }
        };
    }();

    jQuery(document).ready(function() {


        $('#deleteTransactions').click(function(e){

            $policyNumberDeleteVal = '{{$policy->policyNumber}}';

            swal({
                input: 'text',
                inputPlaceholder: 'Enter the policy number',
                title:"Are you sure want to delete this transaction?",
                text:"This action is irreversible, for you to continue please enter the policy number ",
                showCancelButton: true,
                confirmButtonColor: ' #ff0000',
                confirmButtonText: 'Yes, delete it.',
                allowOutsideClick: false,
                inputValidator: (value) => {

                    if (!value ) {
                        return 'Please enter the policy number'
                    }

                    if($policyNumberDeleteVal !=  value){
                        return 'Policy number entered does not match';
                    }
                },

                type:"warning",

                preConfirm: (policyNumber) => {
                    $.ajax({
                        url: '{{ Route('deleteTransactions') }}',
                        data: {
                            "_token": "{{ csrf_token() }}",
                            "referenceNumber": $policyNumberDeleteVal
                        },
                        type: 'post',
                        datatype: 'json',
                        success: function (data) {

                            if(data == 0){
                                Swal.fire({
                                    type:"success",
                                    title:"Action completed",
                                });

                            }else if(data == 99){

                                Swal.fire({
                                    type:"error",
                                    title:"Transaction reference number not found",
                                });

                            }

                            //alert('SUCCESSFUL');
                        },
                        error: function (data){

                        }
                    })

                },
            })



            /*

                     $.ajax({
            url: '{{ Route('deleteTransactions') }}',
                data: {
                    "_token": "{{ csrf_token() }}",
                    "referenceNumber": $policyNumberDeleteVal
                },
                type: 'post',
                datatype: 'json',
                success: function (data) {

                    if(data == 0){
                        Swal.fire({
                            type:"success",
                            title:"Action completed",
                            });

                    }else if(data == 99){

                        Swal.fire({
                            type:"error",
                            title:"Transaction reference number not found",
                            });

                    }

                   //alert('SUCCESSFUL');
                },
                error: function (data){

                }
            });
                */
        });
        KTBootstrapDatepicker.init();
        KTFormControls.init();

        //Motor Items
        //group add limit
        var maxGroup = 10;
        //add more fields group
        $(".addMore").click(function(){
            if($('body').find('.fieldGroup').length < maxGroup){
                var fieldHTML = '<tr class="fieldGroup">'+$(".fieldGroupCopy").html()+'</tr>';
                $('body').find('.fieldGroup:last').after(fieldHTML);
            }else{
                alert('Maximum '+maxGroup+' groups are allowed.');
            }
        });
        //remove fields group
        $("body").on("click",".remove",function(){
            $(this).parents(".fieldGroup").remove();
        });

    });


    $('#addMember').click(function() {
        $('#addMemberDiv').toggle();
        $('#addMember').toggle();
    });
    $('#addBeneficiary').click(function () {
        $('#addBeneficiaryDiv').toggle();
        $('#addBeneficiary').toggle();
    });

    // Avatar Class definition
    var KTAvatarDemo = function() {

        return {
            // Init demos
            init: function() {
                var avatar1  = new KTAvatar('drivinglicense');
                var avatar2  = new KTAvatar('omangpic');
                var avatar11 = new KTAvatar('omangpic_back');
                var avatar3  = new KTAvatar('proofresidence');
                var avatar4  = new KTAvatar('proofincome');
                var avatar5  = new KTAvatar('passportpic');
                // var avatar1 = new KTAvatar('driving_license');
                // var avatar2 = new KTAvatar('omang_pic');
                // var avatar11 = new KTAvatar('omangBack');
                // var avatar3 = new KTAvatar('proof_residence');
                // var avatar4 = new KTAvatar('proof_income');
                // var avatar5 = new KTAvatar('passport_pic');
                var avatar6 = new KTAvatar('left');
                var avatar7 = new KTAvatar('right');
                var avatar8 = new KTAvatar('back');
                var avatar9 = new KTAvatar('front');
                var avatar10 = new KTAvatar('vehicleRegistration');
                var avatar10 = new KTAvatar('vehicle_valuation');

            }
        };
    }();

    // // Class initialization on page load
    // jQuery(document).ready(function() {
    //     KTAvatarDemo.init();

    // });

    jQuery(document).ready(function() {
        // Class initialization on page load
        KTAvatarDemo.init();
        $('#attachmentDiv').on('click', '.addAttachment', function() {
            var count = $(this).parent().parent().children(':first-child').attr('class').split(' ').pop();
            var append = '';
            append += '<div class="kt-avatar multiple_file_avatar" style="float: left; clear: left; margin: 0 20px 20px 0;">' +
                '<div class="kt-avatar__holder" style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>' +
                '<label class="kt-avatar__upload" data-toggle="kt-tooltip" title="Change Image">' +
                '<i class="fa fa-pen"></i>' +
                '<input type="file" class="attachment_file" name="attachment_file['+ count +'][]" />' +
                '</label>' +
                '<span class="kt-avatar__cancel" data-toggle="kt-tooltip" title="Cancel Image"> <i class="fa fa-times"></i> </span>' +
                '</div>';
            $(this).parent().parent().children(':first-child').prepend(append);
            KTAvatarDemo.init();
        });

        $('#attachmentDiv').on('click', '.removeAttachmentDiv', function() {
            $(this).parent().parent().parent().remove();
        });
    });

    </script>
    <script type="text/javascript">
        function restrictAlphabets(event) {
        var key = event.keyCode;
        return ((key >= 48 && key <= 57) || key == 8 || key>=35 && key<=40 || key==46);
    };

    </script>

    <script>
        $(document).ready(function() {

        var bankNum = $('.bankNumRealPay').val();
        $.ajax({
            url: "{{route('admin.paymentvendor.list')}}",
            type: 'GET',
            success: function (data) {
                if (data) {

                    $.each(data, function(key, value){
                        $('#billing').append('<option selected value="' + value.vendorName + '">' + value.vendorLabel + '</option>');
                    });
                    $("#billing").selectpicker('refresh');

                } else {
                    $('#billing').empty();
                }
            }
        });

        $('#billing').val('Orange');
        $('#billing').trigger("change");

    });
    $("#carMakeDropDown").change(function(){

        $("#bankNameSpinner").css("display", "contents");
        $.ajax({
            /* the route pointing to the post function */
            url: '{{Route('getCarModel')}}',
            type: 'POST',
            /* send the csrf-token and the input to the controller */
            data: {
                _token: CSRF_TOKEN,
                make:$('#carMakeDropDown option:selected').val()
            },
            dataType: 'JSON',
            /* remind that 'data' is the response of the AjaxController */
            success: function (data) {
                if (data) {
                    $("#carModelDropDown").removeAttr('title');
                    $("#carModelDropDown").removeAttr('disabled');
                    $('#carModelDropDown').empty();
                    $.each(data, function(key, value){
                        $('#carModelDropDown').append('<option value="' + value.s_Variant + '">' + value.s_Variant + '</option>');
                        $("#carModelDropDown").selectpicker('refresh');
                    });
                    $("#carSpinner").css("display", "none");
                } else {
                    $('#carModelDropDown').empty();
                }
            }
        });

        //get motor items
        //getMotor Items
        $.ajax({
            type: 'post',
            url: '{{route('getMotorItems')}}',
            data: {
                _token: CSRF_TOKEN,
            },
            dataType: 'JSON',
            success: function (response) {
                console.log(response);
                if(response){
                    $.each(data, function(key, value){
                        //append to option
                    });

                }


            },
            error: function(e){
                console.log(e.responseJSON)
            }
        });
    });
    </script>

    <script>
        $(document).ready(function () {
        var urlValue = '{{ \Config::get('values.graphite_url') }}'
        /*var currentVal =  $('.bankNumRealPay').text();
        console.log('loading banks');
        $.ajax({
            type: 'get',
            url: urlValue+'realpay/getBanks',
            data: {},
            dataType: 'JSON',
            cors: true ,
            success: function (response) {
                console.log(response);
                if (response){

                    $.each(response, function(key, value){
                        $('#bankNumDropDown').append('<option value="' + value["ns0:bankNum"] + '">' + value["ns0:bankDesc"] + '</option>');
                        if(value["ns0:bankNum"] == 17){
                            console.log(value["ns0:bankDesc"]);
                            $('.bankNumRealPay').text(value["ns0:bankDesc"]);
                        }
                    });
                    $("#bankNumDropDown").selectpicker('refresh');

                }
            },
            error: function(data){
                console.log(data)
            }
        });*/

    });
    </script>





    <script>
        $('#planPremium').on('change', function(){

        var premium = $('#planPremium').val();
        console.log(premium);

    });

    $('#billing').on('change',function(){


        var options = $('#billing').val();
        switch(options){

            case 'Orange':

                $("#billingOptionHidden").val(options);
                $('#bankNumDropDown').prop("hide",true);
                $('#branchDropdown').hide();
                $('#accountNumberField').hide();
                $('#accountTypeField').hide();
                $('#bankNameField').hide();
                $('#bankBranchField').hide();
                $('#billingCell').prop("disabled",false);
                $("#bankNameSpinner").css("display", "none");
                $('#billingCellField').show();

                $("#billingMethodField").removeClass( "col-lg-12" ).addClass( "col-lg-6" );


                break;


            case 'VCS':
                $("#billingOptionHidden").val(options);
                $('#accountNumberField').hide();
                $('#bankNameField').hide();
                $('#bankBranchField').hide();
                $("#bankNameSpinner").css("display", "none");
                $('#billingCellField').hide();
                $('#accountTypeField').hide();


                $("#billingMethodField").removeClass( "col-lg-6" ).addClass( "col-lg-12" );


                break;

            case 'RealPay':
                $("#billingOptionHidden").val(options);
                $('#bankNameDropdown').prop("required",true);
                $('#accountNumberField').show();
                $('#accountTypeField').show();
                $('#bankNameField').show();
                $('#bankBranchField').show();
                $('#billingCellField').hide();
                $("#billingMethodField").removeClass( "col-lg-6" ).addClass( "col-lg-12" );

                let branches = [];

                $("#bankNameSpinner").css("display", "contents");
                var urlValue = '{{ \Config::get('values.graphite_url') }}'


                break;

            default:
                console.log('default Reached');
                break;
        }

        $("#bankNumDropDown").change(function(){
            const bankNum = $(this).val();
            let filterArray = [];
            $("#branchNameSpinner").css("display", "contents");

            $.ajax({
                /* the route pointing to the post function */
                url: urlValue+'realpay/getBankBranches',
                type: 'GET',
                /* send the csrf-token and the input to the controller */
                data: {},
                dataType: 'JSON',
                /* remind that 'data' is the response of the AjaxController */
                success: function (data) {
                    if (data) {
                        $("#branchNameSpinner").css("display", "none");
                        branches = data;
                        branches.forEach((bank) => {
                            if (bank['ns0:bankNum'] == bankNum) {
                                filterArray.push(bank);
                            }
                        });
                        $('#bankBranchDropDown').empty();
                        filterArray.forEach((value) => {
                            $('#bankBranchDropDown').append('<option value="' + value["ns0:bankBranchNum"] + '">' + value["ns0:bankBranchDesc"] + '</option>');

                        });
                        $("#bankBranchDropDown").selectpicker('refresh');
                        $("#branchNameSpinner").css("display", "none");
                    }
                }
            });

        });

    });


    </script>

    <script>
        $(document).ready(function() {
        var ajaxRequest;
        $('#paymentInfoDiv').slideUp();
        $('#make').change(function() {
            var make = $('#make').val();
            ajaxRequest = setTimeout(function(sn) {
                $.ajax({
                    url: '{{ route('admin.policy.checkMakeModel') }}',
                    data: {
                        "_token": "{{ csrf_token() }}",
                        "make": make,
                    },
                    type: 'post',
                    datatype : 'json',
                    success: function (data) {
                        if (data) {
                            $('#model').empty();
                            $.each(data.count, function(key, modal){
                                $('#model').append('<option value="' + modal + '">' + modal + '</option>');
                            });
                        } else {
                            $('#model').empty();
                        }
                        $("#model").selectpicker('refresh');
                    }
                });
            }, 200);
        });

        $('#type').change(function() {
            if ($('#type').val() == 'reinstate_arrears') {
                $('#urlAmount').val('{{$balanceArrears}}');
                $('#urlAmount').refresh();
            } else if ($('#type').val() == 'reinstate_fresh') {
                $('#urlAmount').val('{{$balance_fresh}}');
                window.location = "{{ route('admin.policy.edit', [$policy->id,'#kt_portlet_base_demo_3_7_rerate_premium','#kt_generatePaymentUrl']) }}"; // redirect
                $('#loader').show();
            }
            else if(type == 'update_card_details')
                {
                    $('#urlAmount').val('1.00');
                    $('#urlAmount').attr('readonly', true);
                    $('#urlPaymentHeader').text('Update card details');
                }
            else {
                $('#urlAmount').val('{{$premium}}');
                $('#urlAmount').attr('readonly', false);
                $('#urlPaymentHeader').text('Link For Repay');
            }
        });

        $('#urlPaymentForm #type').on('change', function(){
                var type = $(this).val();
                if(type == 'update_card_details')
                {
                    $('#urlAmount').val('1.00');
                    $('#urlAmount').attr('readonly', true);
                    $('#urlPaymentHeader').text('Update card details');
                }else{
                    $('#urlAmount').val('');
                    $('#urlAmount').attr('readonly', false);
                    $('#urlPaymentHeader').text('Link For Repay');
                }
            });

        $('.sendURL').click(function(e) {
                const urlCellphone = $('.urlCellphone').val();
                const urlAmount = $('.urlAmount').val();
                var policy_id = {{ $policy->id }};
                var note = $('#urlNote').val();
                var type = $('#type').val();
                // var encodePolicyId = $.base64.encode(policy_id)
                console.log("sendURL");
                e.preventDefault();
                $.ajax({

                    type: 'post',
                    beforeSend: function() {
                        $('.loader').css("display", "block");
                    },
                    url: "{{route('sendPaymentUrlGraphite')}}",
                    data: {
                        "_token": "{{ csrf_token() }}",
                        cellphone: urlCellphone,
                        policy_id: policy_id,
                        amount: urlAmount,
                        note: note,
                        type: type
                    },
                    dataType: 'JSON',
                    success: function(response) {
                        console.log(response);
                        $('.loader').css("display", "none");

                        Toastify({
                            text: "Url generated and sent",
                            duration: 6000,
                            newWindow: true,
                            gravity: "top", // `top` or `bottom`
                            center: true, // `true` or `false`
                            backgroundColor: "#1dc9b7",
                        }).showToast();
                        location.reload();

                    },
                    error: function(error) {
                        $('.loader').css("display", "none");
                        Toastify({
                            text: "Problem with SMS service please try again later",
                            duration: 4000,
                            newWindow: true,
                            gravity: "top", // `top` or `bottom`
                            center: true, // `true` or `false`
                            backgroundColor: "#CC0000",
                        }).showToast();
                        console.log(error.responseJSON);
                    }
                });
            });

        $('a.editId').click(function (e) {
            e.preventDefault();
            var payId = $(this).attr('value');
            var passID = $('.paymentId').val(payId);
            console.log('click',payId );

        });

        $('.resendbtn').click(function (e) {
            e.preventDefault();
            var cellphone = $('.resendCellphone').val();
            var paymentId = $('.paymentId').val();
            $.ajax({
                type: 'post',
                url: '{{ route('resendPaymentUrlGraphite') }}',
                data: {
                    "_token": "{{ csrf_token() }}",
                    resendCellphone: cellphone,
                    payment_id:paymentId

                },
                dataType: 'JSON',
                success: function (response) {
                    console.log(response);
                },
                error:function(error){
                    console.log(error);
                }
            });
        });
    });
    </script>


    <script>
        @if($policy->has_member == 1){
        $(document).ready(function() {
            $("#addBeneficiary").click(function(){
                jQuery.validator.addMethod("future", function(value, element) {
                    return this.optional(element) || Date.parse(value) < new Date().getTime(); //use Date.now() if you can instead of getTime, it's nicer
                }, "Please enter only past dates");
                $('.beneficiaryRelation').rules('add', {required: true, messages: {required: "Please enter relationship"}});
                $('.beneficiaryFName').rules('add', {required: true, messages: {required: "Please enter first name"}});
                $('.beneficiaryLName').rules('add', {required: true, messages: {required: "Please enter last name"}});
                $('.beneficiaryDOB').rules('add', {required: true, future:true});
                $('.beneficiaryGender').rules('add', {required: true, messages: {required: "Please select gender"}});
                $(".beneficiaryPayment").rules('add', {sum: 100,required: true, messages: {required: "Please select payment"}} );
            });
        });
    }
    @endif
    </script>
    <script>
        function DeleteFnc(caller) {
        $(caller).closest('tr').remove();

        $.ajax({
            url: "{{ route('admin.policy.specifiedItemDelete') }}",
            data: {
                "_token": "{{ csrf_token() }}",
                "id": $(caller).prop("id")
            },
            type: "POST",
            success: function (response) {
                if(response.count)
                {
                    $(this).parent().parent().remove();
                }
            },
            error: function(e) {
                alert('error');
            }
        });

    }

            {{--$("#removeSpecifiedBtn").click(function(e) {--}}
            {{--    var motor_id = $(this).attr('value');--}}
            {{--    alert(motor_id);--}}
            {{--    e.preventDefault();--}}
            {{--    var count;--}}
            {{--    $.ajax({--}}
            {{--        url: "{{ route('admin.policy.specifiedItemDelete',$policyMotorItem->id) }}",--}}
            {{--        dataType: 'JSON',--}}
            {{--        success: function (response) {--}}
            {{--            if(response.count){--}}
            {{--               // alert(response.count);--}}
            {{--             $(this).parent().parent().remove();--}}
            {{--            }--}}
            {{--        },--}}
            {{--        error: function(e){--}}
            {{--            console.log(e.responseJSON)--}}
            {{--        }--}}
            {{--    });--}}
            {{--});--}}

      $(document).ready(function() {
                    var isProductTypeInstant = $('#isProductTypeInstant').val();
                    if (isProductTypeInstant == 'yes') {
                        $("input.cover_value").prop("readonly", true);
                    } else {
                        $("input.cover_value").prop("readonly", false);
                    }

                    if('{{isset($_REQUEST['policyPaymentStatus'])}}'){
                        $('.flaticon2-pie-chart-2').trigger('click');
                    }

                    $('#RPBanks').on('change',function(){
                        var bank = $(this).val();
                        ajaxRequest = setTimeout(function(sn) {
                            $.ajax({
                                url: "{{ route('admin.policy.rpGetBranches') }}",
                                data: {
                                    "_token": "{{ csrf_token() }}",
                                    "bank_id": bank
                                },
                                type: 'post',
                                datatype: 'json',
                                success: function(data) {
                                    if (data) {
                                        console.log(data);

                                        $('#RPBankBranch').empty();
                                        $.each( data.branches, function( index, value ){
                                            $option = $('<option value="' + this.branch_id + '">' + this.name + '</option>');
                                            $("#RPBankBranch").append($option);
                                        });

                                        $("#RPBankBranch").selectpicker('refresh');

                                    } else {
                                        $('#RPBankBranch').empty();
                                    }
                                }
                            });
                        }, 100);
                    })

         });

    </script>

    <script>
        function vehicleMsg(){
        var isChecked=document.getElementById("vehicleValue").checked;
        if (isChecked){
            $('#paymentInfoDiv').slideDown();
            document.getElementById("Msg").innerHTML="Yes";
            document.getElementById("Msg").style.color="cornflowerblue";
            $('#coverages').delay(100).slideDown(500);
            $('.coverage').rules('add',  { required: true, messages: { required: "Please Select Coverages" } });
        }
        else {
            $('#paymentInfoDiv').slideUp();
            document.getElementById("Msg").innerHTML="No";
            document.getElementById("Msg").style.color="#ff4d4d";
            $('#coverages').delay(100).slideUp(500);
            $('.coverage').rules('remove',  'required');
        }

    }

    function discountSurcharge() {
        var isChecked = document.getElementById("discountSurchargeValue").checked;

        if (isChecked) {
            $('#discount_surcharge_div').slideDown();
            document.getElementById("MsgDiscountSurcharge").innerHTML = "Yes";
            document.getElementById("MsgDiscountSurcharge").style.color = "cornflowerblue";
        } else {
            $("#reason").prop('required', false);
            $("#value").prop('required', false);
            $("#value_type").prop('required', false);
            $("#type").prop('required', false);
            $('#discount_surcharge_div').slideUp();
            document.getElementById("MsgDiscountSurcharge").innerHTML = "No";
            document.getElementById("MsgDiscountSurcharge").style.color = "#ff4d4d";
        }
    }

    // Apply Custom Rate
    function customDiscountSurcharge() {
        $("#add_dis_sur_rate").hide();
        var isChecked = document.getElementById("customDiscountSurchargeValue").checked;

        if (isChecked) {
            $('#custom_discount_surcharge_div').slideDown();
            document.getElementById("MsgCustomDiscountSurcharge").innerHTML = "Yes";
            document.getElementById("MsgCustomDiscountSurcharge").style.color = "cornflowerblue";
        } else {
            $("#add_dis_sur_rate").show();
            $("#rate_reason").prop('required', false);
            $("#rate_value").prop('required', false);
            $('#custom_discount_surcharge_div').slideUp();
            document.getElementById("MsgCustomDiscountSurcharge").innerHTML = "No";
            document.getElementById("MsgCustomDiscountSurcharge").style.color = "#ff4d4d";
        }
    }

    $('#RPBanksRerate').on('change', function() {

            var bank = $(this).val();
            ajaxRequest = setTimeout(function(sn) {
                $.ajax({
                    url: "{{ route('admin.policy.rpGetBranches') }}",
                    data: {
                        "_token": "{{ csrf_token() }}",
                        "bank_id": bank
                    },
                    type: 'post',
                    datatype: 'json',
                    success: function(data) {
                        if (data) {
                            console.log(data);

                            $('#RPBankBranchRerate').empty();
                            $.each(data.branches, function(index, value) {
                                $option = $('<option value="' + this.branch_id + '">' + this.name + '</option>');
                                $("#RPBankBranchRerate").append($option);
                            });

                            $("#RPBankBranchRerate").selectpicker('refresh');

                        } else {
                            $('#RPBankBranchRerate').empty();
                        }
                    }
                });
            }, 100);
        })

        $('#new_frequency').on('change', function() {
            $('#dis_sur_new_frequency').val($('#new_frequency').val());
            if ($('#new_frequency').val() == 1) {
                var monthly_ins = $('#monthly_ins').html();
                var monthly_ins_with_disSur = $('#dis_sur_monthly_ins').html();
                if (monthly_ins_with_disSur != '') {
                    $('#rerate_premium').val(monthly_ins_with_disSur);
                    $('#first_premium_rerate').val(monthly_ins_with_disSur);
                } else {
                    $('#rerate_premium').val(monthly_ins);
                    $('#first_premium_rerate').val(monthly_ins);
                }
                $('#rerate_premium').prop('readonly', true);
                $('#first_premium_row_rerate_div').slideDown();
                $('#first_collection_date_rerate_div').slideDown();
            } else if($('#new_frequency').val() == 2){
                var three_ins = $('#three_ins').html();
                var threeinstl_ins_with_disSur = $('#dis_sur_three_ins').html();
                if (threeinstl_ins_with_disSur != '') {
                    $('#rerate_premium').val(threeinstl_ins_with_disSur);
                    $('#first_premium_rerate').val(threeinstl_ins_with_disSur);
                } else {
                    $('#rerate_premium').val(three_ins);
                    $('#first_premium_rerate').val(three_ins);
                }

                $('#rerate_premium').prop('readonly', true);
                $('#first_premium_row_rerate_div').slideDown();
                $('#first_collection_date_rerate_div').slideDown();
            } else if($('#new_frequency').val() == 3){
                var annual_ins = $('#annual_ins').html();
                var annual_ins_with_disSur = $('#dis_sur_annual_ins').html();
                if (annual_ins_with_disSur != '') {
                    $('#rerate_premium').val(annual_ins_with_disSur);
                } else {
                    $('#rerate_premium').val(annual_ins);
                }

                $('#rerate_premium').prop('readonly', true);
                $('#first_premium_row_rerate_div').slideUp();
                $('#first_collection_date_rerate_div').slideUp();
            }
        });

    function addPaymentButton() {
        var isChecked = document.getElementById("paymentValue").checked;

        if (isChecked) {
            $('#rerate_new_div').css('display','block');
            document.getElementById("paymentMsg").innerHTML = "Yes";
            document.getElementById("paymentMsg").style.color = "cornflowerblue";
            $('#paymentValueDpo').prop('checked', false);
            document.getElementById("paymentMsgDpo").innerHTML = "No";
            document.getElementById("paymentMsgDpo").style.color = "#ff4d4d";
            $('#paymentValueCash').prop('checked', false);
            document.getElementById("paymentMsgCash").innerHTML = "No";
            document.getElementById("paymentMsgCash").style.color = "#ff4d4d";
            $('#payWithCashForm').css('display','none');
            $('#acceptPremiumDiv').css('display','block');
        } else {
            $('#rerate_new_div').css('display','none');
            document.getElementById("paymentMsg").innerHTML = "No";
            document.getElementById("paymentMsg").style.color = "#ff4d4d";
            $('#acceptPremiumDiv').css('display','none');
            $("#newPremiumRate")[0].reset();

        }
    }

    function addPaymentButtonCash() {
        var isChecked = document.getElementById("paymentValueCash").checked;

        if (isChecked) {
            $('#payWithCashForm').css('display','block');
            document.getElementById("paymentMsgCash").innerHTML = "Yes";
            document.getElementById("paymentMsgCash").style.color = "cornflowerblue";
            $('#paymentValueDpo').prop('checked', false);
            document.getElementById("paymentMsgDpo").innerHTML = "No";
            document.getElementById("paymentMsgDpo").style.color = "#ff4d4d";
            $('#paymentValue').prop('checked', false);
            document.getElementById("paymentMsg").innerHTML = "No";
            document.getElementById("paymentMsg").style.color = "#ff4d4d";
            $('#rerate_new_div').css('display','none');
            $('#acceptPremiumDiv').css('display','block');
            $('#payWithCashAlreadyDone').css('display','block');

        } else {
            $('#payWithCashForm').css('display','none');
            document.getElementById("paymentMsgCash").innerHTML = "No";
            document.getElementById("paymentMsgCash").style.color = "#ff4d4d";
            $('#acceptPremiumDiv').css('display','none');
            $('#payWithCashAlreadyDone').css('display','none');
            $("#newPremiumRate").reset();
        }
    }

    function addPaymentButtonDpo() {
        var isChecked = document.getElementById("paymentValueDpo").checked;

        if (isChecked) {
            document.getElementById("paymentMsgDpo").innerHTML = "Yes";
            document.getElementById("paymentMsgDpo").style.color = "cornflowerblue";
            $('#paymentValueCash').prop('checked', false);
            document.getElementById("paymentMsgCash").innerHTML = "No";
            document.getElementById("paymentMsgCash").style.color = "#ff4d4d";
            $('#paymentValue').prop('checked', false);
            document.getElementById("paymentMsg").innerHTML = "No";
            document.getElementById("paymentMsg").style.color = "#ff4d4d";
            $('#rerate_new_div').css('display','none');
            $('#payWithCashForm').css('display','none');
            $('#acceptPremiumDiv').css('display','block');
            $('#rerate_new_div_dpo').css('display','block');

        } else {
            document.getElementById("paymentMsgDpo").innerHTML = "No";
            document.getElementById("paymentMsgDpo").style.color = "#ff4d4d";
            $('#acceptPremiumDiv').css('display','none');
            $('#rerate_new_div_dpo').css('display','none');
        }
    }


    function addPaymentButtonCashAlreadyDone() {
        var isChecked = document.getElementById("cashPaymentDone").checked;

        if (isChecked) {
            $('#addPaymentButtonCashAlreadyDoneDiv').css('display','block');
            $('#payWithCashForm').css('display','none');
            document.getElementById("paymentMsgCashDone").innerHTML = "Yes";
            document.getElementById("paymentMsgCashDone").style.color = "cornflowerblue";
        } else {
            $('#addPaymentButtonCashAlreadyDoneDiv').css('display','none');
            $('#payWithCashForm').css('display','block');
            document.getElementById("paymentMsgCashDone").innerHTML = "No";
            document.getElementById("paymentMsgCashDone").style.color = "#ff4d4d";
            $("#newPremiumRate").reset();
        }
    }

    function selectTermToUpdate() {
        var isChecked = document.getElementById("rerate_update_renew_term").checked;

        if (isChecked) {
            $('#selectTermToUpdateDiv').css('display','block');
        } else {
            $('#selectTermToUpdateDiv').css('display','none');
        }
    }

    function rerateWithoutPayment() {
        var isChecked = document.getElementById("rerate_without_payment").checked;

        if (isChecked) {
            $('#acceptPremiumDiv').css('display','block');
        } else {
            $('#acceptPremiumDiv').css('display','none');
        }
    }

    function applyRerateRatesToRenewal() {
        var isChecked = document.getElementById("rerate_update_renewal_rates").checked;

        if (isChecked) {
            $('#acceptPremiumDiv').css('display','block');
        } else {
            $('#acceptPremiumDiv').css('display','none');
        }
    }

    </script>
    {{--var url = "{{url('admin/policy/specifiedItemDelete/:id}}";
    var mainurl = url.replace(":id", $id);
    alert(mainurl);--}}


    @if ($product->id == 3)
    <script>
        $(document).ready(function () {
                $('#valueLoader').hide();
                $("#new_rate_div").slideUp();
                $('#discount_surcharge_div').slideUp();
                var vehicle_status = "{{ $premiumCalcDetails->is_imported }}";
                var vehicle_make = "{{ $premiumCalcDetails->make }}";
                var vehicle_model = "{{ $premiumCalcDetails->model }}";
                var vehicle_year = "{{ $premiumCalcDetails->year }}";

                // if (vehicle_status == 1) {
                // var vehicle_sts = 'Yes';
                // } else {
                // var vehicle_sts = 'No';
                // }

                // Get vehicle make model
                // getVehicleMakes(vehicle_sts);
                // getVehicleModels(vehicle_sts,vehicle_make,vehicle_year);


                $('#is_imported').on('change', function() {
                    getVehicleMakes(this.value);
                });

                //Rerate Premium
                $('#submit_rerate_button').on('click', function()
                {
                    var make = $('#make_rerate').val();
                    var year = $('#year_rerate').val();
                    var model = $('#model_rerate').val();
                    var dob = $('#rerate_dob').val();
                    var estimatedValue = $('#estimatedValue').val();
                    var is_imported = $('#is_imported').val();
                    var marital = $('#marital_status').val();
                    var prior_accidents = $('#prior_accidents').val();
                    var gender = $('#gender').val();

                    if (make && year && model && dob && estimatedValue && is_imported && marital && prior_accidents && gender) {
                        $('#reratePremiumError').css('display','none');
                        var baseURL = '{{ env('GRAPHITE_URL') }}';
                        var fetchURL = baseURL+'api/frontendpay/reratePolicyPremium';
                        $.ajax({
                            method: "POST",
                            url: fetchURL,
                            data: {
                                make: make,
                                year: year,
                                model: model,
                                dob: dob,
                                estimatedValue: estimatedValue,
                                is_imported: is_imported,
                                marital: marital,
                                prior_accidents: prior_accidents,
                                gender: gender,
                                policyNumber: '{{ $policy->policyNumber }}',
                                user_id: '{{ auth::user()->id }}',
                            },
                            beforeSend: function() {
                                // setting a timeout
                                $('#loader').show();
                                $("#new_rate_div").slideUp();
                            },
                        })
                        .done(function(response) {
                            $('#loader').hide();
                            $("#page_footer").slideUp();
                            $("#new_rate_div").slideDown();
                            $('#monthly_ins').html(response.data.monthly_premium_vat);
                            $('#three_ins').html(response.data.threemonthly_preminum_vat);
                            $('#annual_ins').html(response.data.result);
                            $('#annual_premium_rerate').val(response.data.result);
                            $('#dis_sur_annual_premium_rerate').val(response.data.result);
                            $('#rate_id').html(response.data.rate_id);
                            $('#rateID').val(response.data.rate_id);
                            $('#premium_rate').html(((response.data.result/$('#estimatedValue').val())*100).toFixed(2)+'%');
                            // $('#acceptPremium').show();
                        })
                        .fail(function(response){
                            $('#loader').hide();
                            $("#new_rate_div").slideUp();
                            $("#page_footer").slideDown();
                            if (response.status == 400) {
                                toastr.error('Error ! ' + response.responseJSON.message);
                            }
                        });
                    } else {
                        $('#reratePremiumError').css('display','block');
                    }

                });

                //Add Discount Surcharge to Rerated Premium
                $("#dis_sur_type").on("change",function(){
                    $('#dis_sur_typeError').css('display','none');
                });

                $("#dis_sur_value_type").on("change",function(){
                    $('#dis_sur_value_typeError').css('display','none');
                });

                $("#dis_sur_value").on("change",function(){
                    $('#dis_sur_valueError').css('display','none');
                });

                $("#dis_sur_reason").on("change",function(){
                    $('#dis_sur_reasonError').css('display','none');
                });



                $('#addDiscSurcRerate').on('click', function() {
                    var type = $('#dis_sur_type').val();
                    if (!type) {
                        $('#dis_sur_typeError').css('display','block');
                    }
                    var value_type = $('#dis_sur_value_type').val();
                    if (!value_type) {
                        $('#dis_sur_value_typeError').css('display','block');
                    }
                    var value = $('#dis_sur_value').val();
                    if (!value) {
                        $('#dis_sur_valueError').css('display','block');
                    }
                    var reason = $('#dis_sur_reason').val();
                    if (!reason) {
                        $('#dis_sur_reasonError').css('display','block');
                    }

                    if (type !='' && value_type !='' && value !='' && reason !='') {
                        var baseURL =  '{{ env('GRAPHITE_URL') }}';
                        var fetchURL = baseURL+'api/policyDiscountSurchargeAPI';
                        $.ajax({
                            method: "POST",
                            url: fetchURL,
                            data: {
                                type                 : $('#dis_sur_type').val(),
                                value_type           : $('#dis_sur_value_type').val(),
                                value                : $('#dis_sur_value').val(),
                                reason               : $('#dis_sur_reason').val(),
                                policyId             : '{{$policy->id}}',
                                annual_premium_rerate: $('#annual_premium_rerate').val(),
                                user                 : '{{auth()->user()->id}}',
                            },
                            beforeSend: function() {
                                // setting a timeout
                                $('#loader').show();
                            },
                        })
                            .done(function(response) {
                                $('#loader').hide();
                                $("#page_footer").slideUp();
                                $("#new_rate_div").slideDown();
                                $("#dis_sur_added_div").slideDown();
                                $('#dis_sur_monthly_ins').html(response.monthly_premium);
                                $('#dis_sur_three_ins').html(response.threeintsll_premium);
                                $('#dis_sur_annual_ins').html(response.annualPremium);
                                $('#dis_sur_annual_premium').val(response.annualPremium);
                                if ($('#new_frequency').val() == 1) {
                                    $('#first_premium_rerate').val(response.monthly_premium);
                                    $('#rerate_premium').val(response.monthly_premium);
                                } else if ($('#new_frequency').val() == 2) {
                                    $('#first_premium_rerate').val(response.threeintsll_premium);
                                    $('#rerate_premium').val(response.threeintsll_premium);
                                } else if ($('#new_frequency').val() == 3) {
                                    $('#first_premium_rerate').val(response.annualPremium);
                                    $('#rerate_premium').val(response.annualPremium);
                                }
                            })
                            .fail(function(response){
                                $('#loader').hide();
                                $('#rerate_disSur_error_div').text(response.responseJSON.message);
                                $('#rerate_disSur_error_div').css('display','block');
                                // $("#new_rate_div").slideUp();
                                // $("#page_footer").slideDown();


                            });
                    }
                });

                $('#cancel_rerate_button').on('click',function(){
                    $('#new_rate_div').slideUp();
                    $('#page_footer').slideDown();
                });

                $('#cancel_custom_rerate_button').on('click', function() {
                    $('#new_rate_div').slideUp();
                    $('#page_footer').slideDown();
                });

                $('#customAddDiscSurcRerate').on('click', function() {

                    var value = $('#cus_dis_sur_value').val();
                    if (!value) {
                        $('#cus_dis_sur_valueError').css('display','block');
                    }else{
                        $('#cus_dis_sur_valueError').css('display','none');
                    }

                    var reason = $('#cus_dis_sur_reason').val();
                    if (!reason) {
                        $('#cus_dis_sur_reasonError').css('display','block');
                    }else{
                        $('#cus_dis_sur_reasonError').css('display','none');
                    }

                    if (value !='' && reason !='') {
                        var baseURL = '{{ env('GRAPHITE_URL') }}';
                        var fetchURL = baseURL+'api/customPolicyDiscountSurchargeAPI';
                        $.ajax({
                            method: "POST",
                            url: fetchURL,
                            data: {
                                value: $('#cus_dis_sur_value').val(),
                                reason: $('#cus_dis_sur_reason').val(),
                                value_type: $('#cus_dis_sur_value_type').val(),
                                policyId: '{{$policy->id}}',
                                annual_premium_rerate: $('#annual_premium_rerate').val(),
                                user: '{{auth()->user()->id}}',
                            },
                            beforeSend: function() {
                                // setting a timeout
                                $('#loader').show();
                            },
                        })
                        .done(function(response) {
                            $('#loader').hide();
                            $("#page_footer").slideUp();
                            $("#new_rate_div").slideDown();
                            $("#dis_sur_added_div").slideDown();
                            $('#dis_sur_monthly_ins').html(response.monthly_premium);
                            $('#dis_sur_three_ins').html(response.threeintsll_premium);
                            $('#dis_sur_annual_ins').html(response.annualPremium);
                            $('#dis_sur_annual_premium').val(response.annualPremium);
                            if ($('#new_frequency').val() == 1) {
                                $('#first_premium_rerate').val(response.monthly_premium);
                                $('#rerate_premium').val(response.monthly_premium);
                            } else if ($('#new_frequency').val() == 2) {
                                $('#first_premium_rerate').val(response.threeintsll_premium);
                                $('#rerate_premium').val(response.threeintsll_premium);
                            } else if ($('#new_frequency').val() == 3) {
                                $('#first_premium_rerate').val(response.annualPremium);
                                $('#rerate_premium').val(response.annualPremium);
                            }
                        })
                        .fail(function(response){
                            $('#loader').hide();
                            $('#rerate_disSur_error_div').text(response.responseJSON.message);
                            $('#rerate_disSur_error_div').css('display','block');
                        });
                    }
                });

                function getVehicleMakes(e) {
                    var baseURL = '{{ env('GRAPHITE_URL') }}';
                    if(e == 'Yes'){
                        var fetchURL = baseURL+'api/frontendpay/vehicleMake'
                    }else{
                        var fetchURL = baseURL+'api/frontendpay/getTTVehicleMakes'
                    }

                    $("#model_rerate").html('');
                    $("#model_rerate").empty();
                    $("#model_rerate").selectpicker('refresh');
                    document.getElementById("year_rerate").selectedIndex = "0";
                    $('#estimatedValue').val('');

                    $.ajax({
                        type: "POST",
                        datatype : 'json',
                        url: fetchURL,
                        dataType: "json",
                        beforeSend: function() {
                            $("#loader").show();
                        },
                        success: function(data){
                            $("#loader").hide();
                            $("#make_rerate").html('');
                            $("#make_rerate").empty();
                            $("#make_rerate").append('<option value="">Select vehicle make</option>');
                            if(e == 'Yes') {
                                $.each(data.makes, function () {
                                    $("#make_rerate").append('<option value="' + this.s_Make + '">' + this.s_Make + '</option>')
                                });
                            }else{
                                var makes = data.Makes;
                                $.each(makes, function (key,val) {
                                    var makes = $('<option value="' + val + '">' + val + '</option>');
                                    $("#make_rerate").append(makes);
                                });
                            }
                            // $('#year_rerate').prop('selectIndex',0);
                            $('#make_rerate').selectpicker('refresh');
                            $('#year_rerate').selectpicker('refresh');
                        }
                    });
                }


                $('#make_rerate').on('change',function(){
                    $("#model_rerate").html('');
                    $("#model_rerate").empty();
                    $("#model_rerate").selectpicker('refresh');
                    // document.getElementById("year_rerate").selectedIndex = "0";
                    $('#estimatedValue').val('');
                });

                $('#year_rerate').on('change',function(){
                    var status = $('#is_imported').val();
                    var make = $('#make_rerate').val();
                    var year = this.value;

                    $("#model_rerate").html('');
                    $("#model_rerate").empty();
                    $("#model_rerate").selectpicker('refresh');
                    // document.getElementById("year_rerate").selectedIndex = "0";
                    $('#estimatedValue').val('');

                    getVehicleModels(status,make,year);
                });

                $('#make_rerate').on('change',function(){
                    var status = $('#is_imported').val();
                    var make = this.value;
                    var year = $('#year_rerate').val();
                    $('#estimatedValue').val('');
                    getVehicleModels(status,make,year);
                });

                function getVehicleModels(status,make,year) {
                    var baseURL = '{{ env('GRAPHITE_URL') }}';
                    if(status == 'Yes'){
                        var fetchURL = baseURL+'api/frontendpay/vehicleModel'
                    }else{
                        var fetchURL = baseURL+'api/frontendpay/getTTVehicleModels'
                    }
                    if(status == 'Yes'){
                    $.ajax({
                        type: "POST",
                        datatype: 'json',
                        url: fetchURL,
                        data: {
                            vehicle_make: make,
                            "_token": "{{ csrf_token() }}",
                        },
                        dataType: "json",
                        beforeSend: function () {
                            $("#loader").show();
                            $("#model_rerate").empty();
                            $("#model_rerate").html('');
                        },
                        success: function (responseData) {
                            $("#loader").hide();
                            $("#model_rerate").html('');
                            $("#model_rerate").empty();
                            $("#model_rerate").append('<option value="">Select vehicle model</option>');
                            $.each(responseData.makes, function () {
                                $option = $('<option value="' + this.s_Variant + '">' + this.s_Variant + '</option>');
                                $("#model_rerate").append($option);
                            });
                            $("#model_rerate").selectpicker('refresh');
                            $('#year_rerate').val(year);
                            $("#year_rerate").selectpicker('refresh');
                        },complete:function(){
                            $("#loader").hide();
                        }
                    });
                    }else{
                    $.ajax({
                        type: "POST",
                        datatype: 'json',
                        url: fetchURL,
                        data: {

                            make: make,
                            manufacturing_year: year
                        },
                        dataType: "json",
                        beforeSend: function () {
                            $("#loader").show();
                            $("#model_rerate").empty();
                            $("#model_rerate").selectpicker('refresh');
                            // $('#model_rerate').prop('selectedIndex',0);
                        },
                        success: function (responseData) {
                            $("#loader").hide();
                            $("#model_rerate").html('');
                            $("#model_rerate").empty();
                            $("#model_rerate").append('<option value="">Select vehicle model</option>');
                            $.each(responseData.Models, function() {
                                if(this.Model){
                                $option = $('<option value="' + this.Model +
                                    '" data-vehicle="' + this.IntroYear +
                                    '" data-vehicle-disc="' + this.DisconYear + '" >' + this
                                    .Model + '</option>');
                                $("#model_rerate").append($option);
                                }
                            });
                            $("#model_rerate").selectpicker('refresh');
                        },
                        error:function(data){
                            $option = $('<option value="" disabled>No vehicle found</option>');
                            $("#model_rerate").append($option);
                        },
                        complete:function(){
                            $("#loader").hide();
                        }
                    });
                    }
                }

                $('#model_rerate').on('change',function(){
                    var baseURL = '{{ env('GRAPHITE_URL') }}';
                        $('#estimatedValue').val('');
                        if(this.value && $('#is_imported').val() == 'No'){
                            $.ajax({
                                type: "POST",
                                datatype: 'json',
                                url: baseURL+'api/frontendpay/getTTValue',
                                data: {
                                    vehicleMake: $('#make_rerate').val(),
                                    vehicleModel: $(this).val(),
                                    manufacturing_year: $('#year_rerate').val(),
                                    condition: 'EX',
                                    mileage: 'LO',
                                },
                                dataType: "json",
                                beforeSend: function () {
                                    $('#valueLoader').show();
                                    $('#estimatedValue').hide();
                                    $('#ratingsCalculation').prop('disabled', true);
                                },
                                success: function (data) {
                                    $('#valueLoader').hide();
                                    $('#estimatedValue').hide();
                                    if(data.value){
                                        var val = (data.value).toFixed(2)
                                        $('#estimatedValue').val(val);
                                        $('#ratingsCalculation').prop('disabled', false);
                                        if(data.value > 500000){
                                            $('#ratingsCalculation').prop('disabled', true);
                                            $("#ratingsCalculation").hide();
                                            $("#requestcallback").show();
                                        }
                                    }

                                },
                                error: function () {
                                    $('#valueLoader').hide();
                                    $('#estimatedValue').show();
                                    $('#estimatedValue').val('');
                                    $('#ratingsCalculation').prop('disabled', false);
                                },
                                complete: function() {
                                    $('#valueLoader').hide();
                                    $('#ratingsCalculation').prop('disabled', false);
                                    $('#estimatedValue').show();
                                },
                            });
                        }
                });

                // $('#year_rerate').prop('value',vehicle_year);
                $('#year_rerate').selectpicker('refresh');
            });
    </script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.js.map"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/js/toastr.js.map"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/js/toastr.min.js"></script>

    <script>
        $(document).ready(function () {
            $('#rerate_div_premium').slideUp();

            $('#edit-premium-button').click(function(e) {
                $('#rerate_div_premium').slideDown();
            });

            $('#rerate_hide').click(function(e) {
                $('#rerate_div_premium').slideUp();
            });

            $("#type").on("change",function(){
                $('#discountError').css('display','none');
            });

            $("#value_type").on("change",function(){
                $('#valueTypError').css('display','none');
            });

            $("#value").on("change",function(){
                $('#valueError').css('display','none');
            });

            $("#reason").on("change",function(){
                $('#reasonError').css('display','none');
            });

            $('#submitEditPremium').click(function(e) {
                var type = $('#type').val();
                if (!type) {
                    $('#discountError').css('display','block');
                }
                var value_type = $('#value_type').val();
                if (!value_type) {
                    $('#valueTypError').css('display','block');
                }
                var value = $('#value').val();
                if (!value) {
                    $('#valueError').css('display','block');
                }
                var reason = $('#reason').val();
                if (!reason) {
                    $('#reasonError').css('display','block');
                }

                if (type !='' && value_type !='' && value !='' && reason !='') {

                    $.ajax({
                        url: '{{ route('quote.updatePremiumAjax',$policy->policyNumber) }}',
                        data: {
                            "_token": "{{ csrf_token() }}",
                            "type" : type,
                            "value_type" : value_type,
                            "value" : value,
                            "reason" : reason,
                        },
                        type: 'post',
                        datatype : 'json',
                        beforeSend: function(){
                            $('#loader').css("display", "block");
                        },
                        success: function(data, xhr) {
                            if (data.status == 200) {
                                toastr.success('Success ! '+ data.message);
                                setTimeout(function () {
                                    location.reload();
                                }, 3000);
                                $('#loader').css("display", "none");
                            }else{
                                toastr.error('Error ! '+ data.message);
                            }
                        },
                        error:function(error){
                            $('#loader').css("display", "none");
                            if (error.status == 401) {
                                toastr.error('Error ! '+ error.responseJSON.message);
                            }
                        }
                    })
                }

            });

        });

        @if (auth::user()->hasPermissionTo('policy-add_schedule_transaction'))
            $('#addScheduleTransactionModel').on('hidden.bs.modal', function (e) {
                $('#addScheduleTransactionModel form')[0].reset();
            });
        @endif
    </script>
    <script type="text/javascript">
        function validateFloatKeyPress(el) {
            var v = parseFloat(el.value);
            el.value = (isNaN(v)) ? '' : v.toFixed(2);
        }
    </script>

    <script>
        $("#updateCustomerKYC").validate({
            ignore: [],
            ignore: ":hidden",
                rules: {
                    gender : {
                        required: true,
                    },
                    dob:{
                        required: true,
                    },
                    marital:{
                        required: true,
                    },
                    is_imported:{
                        required: true,
                    },
                    make_rerate:{
                        required: true,
                    },
                    // year_rerate:{
                    //     required: true,
                    // },
                    model_rerate:{
                        required: true,
                    },
                    estimatedValue:{
                        required: true,
                        // range:[20000,500000],
                        min:20000,
                        max:500000,
                    },
                    prior_accidents:{
                        required: true,
                        max: 3,
                    },
                    accountType: {
                        required: true
                    },
                    accountNumber:{
                        required:true
                    },
                    BranchCode:{
                        required:true
                    },
                    BankCode:{
                        required:true
                    },
                    billing_day:{
                        required:true
                    },
                    frequency:{
                        required:true
                    },
                    first_collection_date:{
                        required:true
                    },
                    first_premium:{
                        required:true
                    },
                    premium:{
                        required:true
                    },
                    cellphone:{
                        required:true
                    },
                },
                messages : {
                    gender: {
                        required: "Please select gender",
                    },
                    dob:{
                        required: "Please enter date of birth",
                    },
                    marital:{
                        required: "Please enter marital status",
                    },
                    is_imported:{
                        required: "Please select vehicle is imported or not",
                    },
                    make_rerate:{
                        required: "Please select vehicle make",
                    },
                    // year_rerate:{
                    //     required: "Please select vehicle year",
                    // },
                    model_rerate:{
                        required: "Please select vehicle model",
                    },
                    estimatedValue:{
                        required: "Please enter estimated value",
                        range: "Please enter value between 20000 to 500000",
                        min: "Please enter value greater than 20000",
                        max: "Please eneter value less than 500000",
                    },
                    prior_accidents:{
                        required: "Please enter prior accidents",
                        max: "Please enter value below 4",
                    },
                },
                submitHandler: function(form) {
                    $("#annual_premium2").val($("#annual_premium1").val());
                    return true;
                }
            });
    </script>
    @endif
    {{-- <script>
        document.onreadystatechange = function() {
            if (document.readyState !== "complete") {
                document.querySelector(
                  "body").style.visibility = "hidden";
                document.querySelector(
                  "#loader").style.visibility = "visible";
            } else {
                document.querySelector(
                  "#loader").style.display = "none";
                document.querySelector(
                  "body").style.visibility = "visible";
            }
        };
    </script> --}}
    <script>
        jQuery(document).ready(function() {


        $('#fetch_data').on('click',function () {
            $("#loader").show();
        });

        KTFormControls.init();
        var now = new Date();
        var days = new Date(now.getFullYear(), now.getMonth()+1, 0).getDate();

        $('#billing_day').append('<option value="">Please select billing day</option>');

        $('#branches').append('<option value="" selected disabled>Please select bank branch</option>');

        for(var i = 1; i <= days; i++){
            $('#billing_day').append('<option value="'+ i +'">'+ i +'</option>');
        }
    });

    $('#banks').on('change',function(){
        var bank = $(this).val();
        ajaxRequest = setTimeout(function(sn) {
            $.ajax({
                url: "{{ route('admin.policy.rpGetBranches') }}",
                data: {
                    "_token": "{{ csrf_token() }}",
                    "bank_id": bank
                },
                type: 'post',
                datatype: 'json',
                success: function(data) {
                    if (data) {
                        $('#branches').empty();
                        $.each( data.branches, function( index, value ){
                            var option = $('<option value="' + this.branch_id + '" selected>' + this.name + '</option>');
                            $("#branches").append(option);
                        });



                    } else {
                        $('#RPBankBranch').empty();
                    }
                }
            });
        }, 100);
    });

    </script>
<script>
    jQuery(document).ready(function() {
        let path = document.URL;
        if (path.includes("#kt_portlet_base_demo_3_7_rerate_premium")) {
            $('.policyDetailsTab').removeClass('active');
            $('.policyDetailsTab').removeAttr('aria-selected');
            $('.reratePremium').addClass('active');
            $('.reratePremium').removeAttr('aria-selected',true);
            $('#kt_portlet_base_demo_3_1_policy_content').removeClass('active');
            $('#kt_portlet_base_demo_3_7_rerate_premium').addClass('active');
        }
    });
</script>
<script>
   $(document).ready(function(){
    $("#sendemail").click(function(event){
        event.preventDefault();
        var id = $('#sendemail').val();
         console.log(id);
        $.ajax({
            type: "get",
            datatype : 'json',
            url: '{{ route("admin.sendemailurllink") }}',
            data: {
               id:id,
            },

            success: function(data){
                alert("Email and SMS for uploading Vehicle pre-inspection images has been sent successfully");


            }
            });

            });
   });

</script>

<script>
    "use strict";
    // Class definition

    var KTFormControls = function () {
        // Private functions

        var demo1 = function () {
            $( "#clientForm" ).validate({
                // define validation rules
                rules: {
                    policyNumber: {
                        required: true
                    },

                },

                //display error alert on form submit
                invalidHandler: function(event, validator) {
                    KTUtil.scrollTo("branchForm", -200);
                    $('#btn').show();
                },

                submitHandler: function (form) {
                    $('#btn').hide();
                    $('#loadBtn').show();
                    form[0].submit(); // submit the form
                }
            });
        }

        return {
            // public functions
            init: function() {
                demo1();
            }
        };
    }();

    jQuery(document).ready(function() {
        KTFormControls.init();
    });
</script>

<script>
    "use strict";
    var policyStatus_filter = -1;
    var product_filter = -1;
    var cellphone_filter = '';
    var reference_filter = '';
    var agent_filter = -1;
    var lead_agent =-1;
    var linkedCustomerId = '';

    var KTLinkedDatatablesDataSourceAjaxServer = function() {
        var table = '';
        var initLinkedTable1 = function() {
            table = $('#linked_policy_table').DataTable({
                responsive: true,
                searchDelay: 500,
                processing: true,
                language: {
                    processing: "<img src='{{asset('img/loading.gif')}}'>"
                },
                columnDefs: [
                    { width: 200, "targets": 5},
                    { width: 400, "targets": 9},
                ],
                serverSide: true,
                ajax: {
                    url: '{!! route('admin.policy.linkedPolicyData') !!}',
                    data: function (d) {
                        d.policyStatus_filter = policyStatus_filter;
                        d.product_filter = product_filter;
                        d.cellphone_filter = cellphone_filter;
                        d.reference_filter = reference_filter;
                        d.agent_filter = agent_filter;
                        d.lead_agent = lead_agent;
                        d.linkedCustomerId = '{{$policy->customer_id}}';
                        d.linkedPolicyId = '{{$policy->id}}';
                    }
                    },
                order: [0, 'DESC'],
                columns: [
                    {data: 'id'},
                    {data: 'view'},
                    {data: 'name'},
                    {data: 'agentName'},
                    {data: 'cellphone', "bVisible": false,},
                    {data: 'product_name'},
                    {data: 'payment_method'},
                    {data: 'referenceNumber'},
                    {data: 'vehicle_plate'},
                    {data: 'status'},
                    {data: 'created_at'},
                    // {data: 'actions'},
                ],
            });
        };

        return {
            //main function to initiate the module
            init: function() {
                initLinkedTable1();
            },
            draw: function(){
                table.draw();
            }
        };
    }();

    jQuery(document).ready(function() {
        KTLinkedDatatablesDataSourceAjaxServer.init();
    });
</script>
<script>
        $(document).ready(function() {
    @if (auth::user()->hasPermissionTo('ActivatePolicy'))
        $('#ActivatePolicy').click(function() {
        let text = "Are you sure you want to Activate this policy ?";
        if(confirm(text) ==  true)
        {
          var url = '{{ env("GRAPHITE_URL") }}ActivatePolicy';
          var policyNumber = '{{ $policy->policyNumber }}';
        $.ajax({
        url: url,
        data:  {
            "_token"       : "{{ csrf_token() }}",
            "policyNumber":policyNumber
        },
        type: 'post',
        datatype : 'json',
        beforeSend: function(){
            $("#loader").show();
        },

                  success: function(data) {
                    $("#loader").hide();

                                    Toastify({
                                            text: data.status,
                                            duration: 6000,
                                            newWindow: true,
                                            gravity: "top", // `top` or `bottom`
                                            center: true, // `true` or `false`
                                            backgroundColor: "#1dc9b7",
                                        }).showToast();
                                        location.reload();
                        },
                        error: function(data) {
                            $("#loader").hide();
                                    Toastify({
                                            text: data.status,
                                            duration: 6000,
                                            newWindow: true,
                                            gravity: "top", // `top` or `bottom`
                                            center: true, // `true` or `false`
                                            backgroundColor: "red",
                                        }).showToast();
                                        location.reload();
                            },

                            complete: function (data) {
                                $("#loader").hide();
                            },
    });
}
});
@endif
});
</script>
</body>
<!-- end::Body -->

</html>
