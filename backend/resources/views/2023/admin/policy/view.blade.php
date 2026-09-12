@push('css')
<x-app-layout>
    <x-slot name="breadcrum">
        <div class="d-flex align-items-center flex-wrap mr-1">
            <div class="d-flex align-items-baseline flex-wrap mr-5">
                <h5 class="text-dark font-weight-bold my-1 mr-5">View Policy</h5>
                {{ Breadcrumbs::render('policy') }}
            </div>
        </div>
    </x-slot>

    <div class="d-flex flex-column-fluid">
        <div class="container-fluid">
            @include('message-out')
            <div class="card card-custom">
                <div class="card-header card-header-tabs-line">
                    <div class="row">
                        <div class=" col-lg-2 text-left"><br>
                            <h5> <span class="card-label font-weight-bolder font-size-h5 text-dark-75">
                                            Policy No # {{$policy->policyNumber}}
                                 </span>
                            </h5>
                        </div>
                        @if($kyc == NULL)
                            <div class="col-lg-4 text-center">
                                <br>
                                <label>
                                    <h5><span class="card-label font-weight-bolder font-size-h5 text-dark-75">Compliance:</span>
                                        <span
                                            class="badge badge-danger"
                                            style="font-size:14px"> KYC Non Compliant</span></h5>
                                </label>
                            </div>
                        @else
                            <div class="col-lg-3">
                                <br>
                                <label>
                                    @if($kyc->compliance == 1)
                                        <h5><span class="card-label font-weight-bolder font-size-h5 text-dark-75">Compliance:</span>
                                            <span
                                                class="badge badge-success"
                                                style="font-size:15px"> KYC Compliant</span></h5>
                                    @else
                                        <h5><span class="card-label font-weight-bolder font-size-h5 text-dark-75">Compliance:</span>
                                            <span
                                                class="badge badge-danger"
                                                style="font-size:14px"> KYC Non Compliant</span></h5>
                                    @endif
                                </label>
                            </div>
                        @endif
                        <div class="col-lg-5">
                            <br>
                            <label>
                                @if ($policy->status == 1)
                                    <h5><span class="card-label font-weight-bolder font-size-h5 text-dark-75">Policy Status: </span><span
                                            class="badge badge-success"
                                            style="font-size:15px"> Activated</span>@if($transaction && $transaction->status == 'Success')
                                            <span class="badge badge-success"
                                                  style="font-size:15px;margin-left: 5px;"> Payment Success</span>@else
                                            <span class="badge badge-danger"
                                                  style="font-size:15px"> Payment Failed</span>@endif</h5>
                                @elseif ($policy->status == 2)
                                    <h5><span class="card-label font-weight-bolder font-size-h5 text-dark-75">Policy Status: </span><span
                                            class="badge badge-danger"
                                            style="font-size:15px"> Cancelled</span>@if($transaction && $transaction->status == 'Success')
                                            <span class="badge badge-success" style="font-size:15px"> Payment Success</span>@else
                                            <span class="badge badge-danger"
                                                  style="font-size:15px"> Payment Failed</span>@endif</h5>
                                @else
                                    <h5><span class="card-label font-weight-bolder font-size-h5 text-dark-75">Policy Status: </span><span
                                            class="badge badge-primary"
                                            style="font-size:15px"> Deactivated</span>@if($transaction && $transaction->status == 'Success')
                                            <span class="badge badge-success" style="font-size:15px"> Payment Success</span>@else
                                            <span class="badge badge-danger"
                                                  style="font-size:15px"> Payment Failed</span>@endif</h5>
                                @endif
                            </label>
                        </div>
                        <div class=" col-lg-2 "><br>
                            @if(isset($agent_name))
                                <h5><span class="card-label font-weight-bolder font-size-h5 text-dark-75">Agent: {!! $agent_name->firstName !!} {!! $agent_name->lastName !!}</span></h5>
                            @elseif(isset($agent_name->firstName))
                                <h5>Agent: {!! $agent_name->lastName !!}</h5>
                            @elseif(isset($agent_name->lastName))
                                <h5>Agent: {!! $agent_name->firstName !!}</h5>
                            @else
                                <h5><span class="card-label font-weight-bolder font-size-h5 text-dark-75">Agent: Not assigned </h5>
                            @endif
                        </div>
                    </div>

                    <div class="card-toolbar">
                        <ul class="nav nav-tabs nav-bold nav-tabs-line">
                            {{-- Policy Details --}}
                            <li class="nav-item">
                                <a class="nav-link active" data-toggle="tab" href="#policyDetails">
                                    <span class="nav-icon">
                                        <span class="svg-icon svg-icon-sm">
                                        <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                                            <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                                <rect x="0" y="0" width="24" height="24"></rect>
                                                <path d="M8,3 L8,3.5 C8,4.32842712 8.67157288,5 9.5,5 L14.5,5 C15.3284271,5 16,4.32842712 16,3.5 L16,3 L18,3 C19.1045695,3 20,3.8954305 20,5 L20,21 C20,22.1045695 19.1045695,23 18,23 L6,23 C4.8954305,23 4,22.1045695 4,21 L4,5 C4,3.8954305 4.8954305,3 6,3 L8,3 Z" fill="#000000" opacity="0.3"></path>
                                                <path d="M11,2 C11,1.44771525 11.4477153,1 12,1 C12.5522847,1 13,1.44771525 13,2 L14.5,2 C14.7761424,2 15,2.22385763 15,2.5 L15,3.5 C15,3.77614237 14.7761424,4 14.5,4 L9.5,4 C9.22385763,4 9,3.77614237 9,3.5 L9,2.5 C9,2.22385763 9.22385763,2 9.5,2 L11,2 Z" fill="#000000"></path>
                                                <rect fill="#000000" opacity="0.3" x="7" y="10" width="5" height="2" rx="1"></rect>
                                                <rect fill="#000000" opacity="0.3" x="7" y="14" width="9" height="2" rx="1"></rect>
                                            </g>
                                        </svg><!--end::Svg Icon--></span>
                                    </span>
                                    <span class="nav-text">Policy Details</span>
                                </a>
                            </li>
                            {{-- Billing Details --}}
                            <li class="nav-item">
                                <a class="nav-link" data-toggle="tab" href="#billingDetails">
                                   <span class="nav-icon">
                                        <span class="svg-icon svg-icon-sm">
                                            <!--begin::Svg Icon | path:/keen/theme/demo1/dist/assets/media/svg/icons/Communication/Chat-check.svg-->
                                            <svg xmlns="http://www.w3.org/2000/svg"
                                                 xmlns:xlink="http://www.w3.org/1999/xlink"
                                                 width="24px" height="24px"
                                                 viewBox="0 0 24 24" version="1.1">
                                                <g stroke="none" stroke-width="1"
                                                   fill="none" fill-rule="evenodd">
                                                    <rect x="0" y="0" width="24"
                                                          height="24"></rect>
                                                    <path
                                                        d="M4.875,20.75 C4.63541667,20.75 4.39583333,20.6541667 4.20416667,20.4625 L2.2875,18.5458333 C1.90416667,18.1625 1.90416667,17.5875 2.2875,17.2041667 C2.67083333,16.8208333 3.29375,16.8208333 3.62916667,17.2041667 L4.875,18.45 L8.0375,15.2875 C8.42083333,14.9041667 8.99583333,14.9041667 9.37916667,15.2875 C9.7625,15.6708333 9.7625,16.2458333 9.37916667,16.6291667 L5.54583333,20.4625 C5.35416667,20.6541667 5.11458333,20.75 4.875,20.75 Z"
                                                        fill="#000000"
                                                        fill-rule="nonzero"
                                                        opacity="0.3"></path>
                                                    <path
                                                        d="M2,11.8650466 L2,6 C2,4.34314575 3.34314575,3 5,3 L19,3 C20.6568542,3 22,4.34314575 22,6 L22,15 C22,15.0032706 21.9999948,15.0065399 21.9999843,15.009808 L22.0249378,15 L22.0249378,19.5857864 C22.0249378,20.1380712 21.5772226,20.5857864 21.0249378,20.5857864 C20.7597213,20.5857864 20.5053674,20.4804296 20.317831,20.2928932 L18.0249378,18 L12.9835977,18 C12.7263047,14.0909841 9.47412135,11 5.5,11 C4.23590829,11 3.04485894,11.3127315 2,11.8650466 Z M6,7 C5.44771525,7 5,7.44771525 5,8 C5,8.55228475 5.44771525,9 6,9 L15,9 C15.5522847,9 16,8.55228475 16,8 C16,7.44771525 15.5522847,7 15,7 L6,7 Z"
                                                        fill="#000000"></path>
                                                </g>
                                            </svg>
                                            <!--end::Svg Icon-->
                                        </span>
                                    </span>
                                    <span class="nav-text">Billing Details</span>
                                </a>
                            </li>
                            {{-- Policy Documents --}}
                            <li class="nav-item">
                                <a class="nav-link" data-toggle="tab" href="#policyDocument">
                                    <span class="nav-icon">
                                                        <span class="svg-icon svg-icon-sm">
                                                            <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                                                                <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                                                    <polygon points="0 0 24 0 24 24 0 24"></polygon>
                                                                    <path d="M5.85714286,2 L13.7364114,2 C14.0910962,2 14.4343066,2.12568431 14.7051108,2.35473959 L19.4686994,6.3839416 C19.8056532,6.66894833 20,7.08787823 20,7.52920201 L20,20.0833333 C20,21.8738751 19.9795521,22 18.1428571,22 L5.85714286,22 C4.02044787,22 4,21.8738751 4,20.0833333 L4,3.91666667 C4,2.12612489 4.02044787,2 5.85714286,2 Z" fill="#000000" fill-rule="nonzero" opacity="0.3"></path>
                                                                    <rect fill="#000000" x="9" y="12" width="6" height="2" rx="1"></rect>
                                                                </g>
                                                            </svg><!--end::Svg Icon-->
                                                        </span>
                                                    </span>
                                    <span class="nav-text">Policy Document</span>
                                </a>
                            </li>
                            {{-- TransactionLogs --}}
                            <li class="nav-item">
                                <a class="nav-link" data-toggle="tab" href="#TransactionLogs">
                                    <span class="nav-icon">
                                                        <span class="svg-icon svg-icon-sm">
                                                        <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                                                            <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                                                <polygon points="0 0 24 0 24 24 0 24"></polygon>
                                                                <path d="M4.85714286,1 L11.7364114,1 C12.0910962,1 12.4343066,1.12568431 12.7051108,1.35473959 L17.4686994,5.3839416 C17.8056532,5.66894833 18,6.08787823 18,6.52920201 L18,19.0833333 C18,20.8738751 17.9795521,21 16.1428571,21 L4.85714286,21 C3.02044787,21 3,20.8738751 3,19.0833333 L3,2.91666667 C3,1.12612489 3.02044787,1 4.85714286,1 Z M8,12 C7.44771525,12 7,12.4477153 7,13 C7,13.5522847 7.44771525,14 8,14 L15,14 C15.5522847,14 16,13.5522847 16,13 C16,12.4477153 15.5522847,12 15,12 L8,12 Z M8,16 C7.44771525,16 7,16.4477153 7,17 C7,17.5522847 7.44771525,18 8,18 L11,18 C11.5522847,18 12,17.5522847 12,17 C12,16.4477153 11.5522847,16 11,16 L8,16 Z" fill="#000000" fill-rule="nonzero" opacity="0.3"></path>
                                                                <path d="M6.85714286,3 L14.7364114,3 C15.0910962,3 15.4343066,3.12568431 15.7051108,3.35473959 L20.4686994,7.3839416 C20.8056532,7.66894833 21,8.08787823 21,8.52920201 L21,21.0833333 C21,22.8738751 20.9795521,23 19.1428571,23 L6.85714286,23 C5.02044787,23 5,22.8738751 5,21.0833333 L5,4.91666667 C5,3.12612489 5.02044787,3 6.85714286,3 Z M8,12 C7.44771525,12 7,12.4477153 7,13 C7,13.5522847 7.44771525,14 8,14 L15,14 C15.5522847,14 16,13.5522847 16,13 C16,12.4477153 15.5522847,12 15,12 L8,12 Z M8,16 C7.44771525,16 7,16.4477153 7,17 C7,17.5522847 7.44771525,18 8,18 L11,18 C11.5522847,18 12,17.5522847 12,17 C12,16.4477153 11.5522847,16 11,16 L8,16 Z" fill="#000000" fill-rule="nonzero"></path>
                                                            </g>
                                                        </svg>
                                                        </span>
                                                    </span>
                                    <span class="nav-text">Transaction Logs</span>
                                </a>
                            </li>
                            {{-- Payment Schedules --}}
                            <li class="nav-item">
                                <a class="nav-link" data-toggle="tab" href="#schedules">
                                <span class="nav-icon">
                                    <span class="svg-icon svg-icon-sm">
                                        <span class="svg-icon "><!--begin::Svg Icon | path:/var/www/preview.keenthemes.com/keen/releases/2021-04-21-040700/theme/demo1/dist/../src/media/svg/icons/General/Clipboard.svg--><svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                                            <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                                <rect x="0" y="0" width="24" height="24"></rect>
                                                <path d="M8,3 L8,3.5 C8,4.32842712 8.67157288,5 9.5,5 L14.5,5 C15.3284271,5 16,4.32842712 16,3.5 L16,3 L18,3 C19.1045695,3 20,3.8954305 20,5 L20,21 C20,22.1045695 19.1045695,23 18,23 L6,23 C4.8954305,23 4,22.1045695 4,21 L4,5 C4,3.8954305 4.8954305,3 6,3 L8,3 Z" fill="#000000" opacity="0.3"></path>
                                                <path d="M11,2 C11,1.44771525 11.4477153,1 12,1 C12.5522847,1 13,1.44771525 13,2 L14.5,2 C14.7761424,2 15,2.22385763 15,2.5 L15,3.5 C15,3.77614237 14.7761424,4 14.5,4 L9.5,4 C9.22385763,4 9,3.77614237 9,3.5 L9,2.5 C9,2.22385763 9.22385763,2 9.5,2 L11,2 Z" fill="#000000"></path>
                                                <rect fill="#000000" opacity="0.3" x="7" y="10" width="5" height="2" rx="1"></rect>
                                                <rect fill="#000000" opacity="0.3" x="7" y="14" width="9" height="2" rx="1"></rect>
                                            </g>
                                                </svg><!--end::Svg Icon-->
                                        </span>
                                    </span>
                                    </span>
                                    <span class="nav-text">Scheduled Transactions</span>
                                </a>
                            </li>
                            {{-- Ledger --}}
                            <li class="nav-item">
                                <a class="nav-link" data-toggle="tab" href="#Ledger">
                                <span class="nav-icon">
                                    <span class="svg-icon svg-icon-sm">
                                        <span class="svg-icon "><!--begin::Svg Icon | path:/var/www/preview.keenthemes.com/keen/releases/2021-04-21-040700/theme/demo1/dist/../src/media/svg/icons/General/Clipboard.svg--><svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                                            <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                                <rect x="0" y="0" width="24" height="24"></rect>
                                                <path d="M8,3 L8,3.5 C8,4.32842712 8.67157288,5 9.5,5 L14.5,5 C15.3284271,5 16,4.32842712 16,3.5 L16,3 L18,3 C19.1045695,3 20,3.8954305 20,5 L20,21 C20,22.1045695 19.1045695,23 18,23 L6,23 C4.8954305,23 4,22.1045695 4,21 L4,5 C4,3.8954305 4.8954305,3 6,3 L8,3 Z" fill="#000000" opacity="0.3"></path>
                                                <path d="M11,2 C11,1.44771525 11.4477153,1 12,1 C12.5522847,1 13,1.44771525 13,2 L14.5,2 C14.7761424,2 15,2.22385763 15,2.5 L15,3.5 C15,3.77614237 14.7761424,4 14.5,4 L9.5,4 C9.22385763,4 9,3.77614237 9,3.5 L9,2.5 C9,2.22385763 9.22385763,2 9.5,2 L11,2 Z" fill="#000000"></path>
                                                <rect fill="#000000" opacity="0.3" x="7" y="10" width="5" height="2" rx="1"></rect>
                                                <rect fill="#000000" opacity="0.3" x="7" y="14" width="9" height="2" rx="1"></rect>
                                            </g>
                                                </svg><!--end::Svg Icon-->
                                        </span>
                                    </span>
                                    </span>
                                    <span class="nav-text">Ledger</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                    @if($policy->status == 1 || $policy->status == 0)
                    <div class="col-lg-2">
                        <button type="button" id="canclep"  style="float:right" class="btn btn-danger " data-toggle="modal" data-target="#canclePolicy">
                            Cancel
                        </button>
                    </div>
                    @endif
                    <div class="card-toolbar">
                        <div class="dropdown dropdown-inline">
                            <button type="button" class="btn btn-hover-light-primary btn-icon btn-sm" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <i class="ki ki-bold-more-hor "></i>
                            </button>
                            <div class="dropdown-menu dropdown-menu-sm dropdown-menu-right">
                                <a class="dropdown-item" href="#">Action</a>
                                <a class="dropdown-item" href="#">Another action</a>
                                <a class="dropdown-item" href="#">Something else here</a>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item" href="#">Separated link</a>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="policyDetails" role="tabpanel" aria-labelledby="policyDetails">
                            <div class="card-body">
                                <h3 class="card-title align-items-start flex-column">
                                    <span class="card-label font-weight-bolder font-size-h4 text-dark-75">
                                        Customer Details:</span>
                                </h3>
                                <table class="table table-striped m-table">
                                    <tbody>
                                    <tr>
                                        <th>Name</th>
                                        @if($user != null)
                                            <td style="text-transform: capitalize">{!! $user->firstName !!} {!! $user->middleName !!} {!! $user->lastName !!}</td>
                                        @elseif($user->firstName == null)
                                            <td>{!! $user->lastName !!}</td>
                                        @elseif($user->lastName == null)
                                            <td>{!! $user->firstName !!}</td>
                                        @endif

                                        <th>Email</th>
                                        @if($user != null && $user->email)
                                            <td id="email"> {!! $user->email !!} </td>
                                        @else
                                            <td>N/A</td>
                                        @endif
                                    </tr>
                                    <tr>
                                        <th>Cellphone</th>
                                        <td id="cphnumber">{!! $user->cellphone !!}</td>
                                        <th>Address</th>
                                        <td>{!! $user->profile->address !!}</td>
                                    </tr>
                                    <tr>
                                        <th>Postal Address</th>
                                        <td>{!! $user->profile->postal_address !!}</td>

                                        <th>Gender</th>
                                        <td>{!! $user->profile->gender !!}</td>
                                        {{-- @if($user->profile->gender == 1)
                                            <td> {{"Male"}} </td>
                                        @elseif($user->profile->gender == 2)
                                            <td> {{"Other"}} </td>
                                        @else
                                            <td> {{"Female"}} </td>
                                        @endif --}}
                                    </tr>
                                    @if (isset($user->profile->gender) && $user->profile->gender == 'Other')
                                        <tr>
                                            <th>Other Gender</th>
                                            @if (isset($user->profile->gender_other) && $user->profile->gender_other != null)
                                            <td>{!!  $user->profile->gender_other !!}</td>
                                            @endif
                                        </tr>
                                    @endif
                                    <tr>
                                        <th>Marital Status</th>
                                        @if($user->profile->maritalstatus == 1)
                                            <td>{{"Single"}}</td>
                                        @elseif($user->profile->maritalstatus == 2)
                                            <td>{{"Married"}}</td>
                                        @elseif($user->profile->maritalstatus == 3)
                                            <td>{{"Divorced"}}</td>
                                        @elseif($user->profile->maritalstatus == 4)
                                            <td>{{"Widowed"}}</td>
                                        @elseif($user->profile->maritalstatus == 5)
                                            <td>{{" Living Together (Married)"}}</td>
                                        @elseif($user->profile->maritalstatus == 6)
                                            <td>{{"Living Separately"}}</td>
                                        @endif
                                        <th>Date of Birth</th>
                                        <td>{{ \Carbon\Carbon::parse($user->profile->dob)->format('d-m-Y') }}</td>
                                    </tr>
                                    <tr>
                                        <th>National ID</th>
                                        <td style="text-transform: uppercase;">{!! $user->profile->nid !!}</td>
                                        <th>Passport</th>
                                        @if(isset($user->profile->passport))
                                            <td style="text-transform: uppercase">{!! $user->profile->passport !!}</td>
                                        @else
                                            <td>N/A</td>
                                        @endif
                                    </tr>
                                    <tr>
                                        <th>Passport Issuing Country</th>
                                        @if(isset($passpostIssueCountry->name))
                                            <td> {!! $passpostIssueCountry->name !!} </td>
                                        @else
                                            <td>N/A</td>
                                        @endif
                                        <th>Province</th>
                                        @if(isset($user->profile->state_name))
                                            <td>{!! $user->profile->state_name !!}</td>
                                        @else
                                            <td>N/A</td>
                                        @endif
                                    </tr>
                                    <tr>
                                        <th>City</th>
                                        @if(isset($user->profile->city_name))
                                            <td>{!! $user->profile->city_name !!}</td>
                                        @else
                                            <td>N/A</td>
                                        @endif
                                        <th>Driving License Number</th>
                                        @if(isset($user->profile->driving_license_number))
                                            <td style="text-transform: uppercase">{!! $user->profile->driving_license_number !!}</td>
                                        @else
                                            <td>N/A</td>
                                        @endif
                                    </tr>
                                    <tr>
                                        <th>License Valid Till</th>
                                        @if(isset($user->profile->license_valid_till))
                                            <td>{{ \Carbon\Carbon::parse($user->profile->license_valid_till)->format('d-m-Y') }}</td>
                                        @else
                                            <td>N/A</td>
                                        @endif
                                        <th>Billing Start Date</th>
                                        @if($policy->billingStartDate != null)
                                            <td>{{ \Carbon\Carbon::parse($policy->billingStartDate)->format('d-m-Y') }}</td>
                                        @else
                                            <td>N/A</td>
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

                                    </tbody>
                                </table>
                                @if($policy->kyc_customer)
                                    <div class="separator separator-dashed my-10"></div>
                                    <h3 class="card-title align-items-start flex-column">
                                    <span class="card-label font-weight-bolder font-size-h4 text-dark-75">Customer
                                        KYC:</span>
                                    </h3>
                                    <table class="table table-striped m-table">
                                        <tbody>
                                            <form action="" method="post" enctype="multipart/form-data" class="kt-form">
                                              {{-- <form action="{{route('uploadKycImages',$policy->id)}}" method="post"
                                              enctype="multipart/form-data" class="kt-form"> --}}
                                            @csrf
                                            <div class="form-group row mt-3">
                                                <label class="col-lg-1 col-form-label text-lg-right"> Driving
                                                    License:</label>
                                                <div class="col-lg-2">
                                                    <div class="image-input" id="kt_image_d">
                                                        @if(isset($kyc) && !empty($kyc) && $kyc->driving_license!="")
                                                            <a href="{{ \Helper::getCloudFrontURL($kyc->driving_license)}}"
                                                               target="_blank">
                                                                <div class="image-input-wrapper"
                                                                     style="background-image: url({!! \Helper::getCloudFrontURL($kyc->driving_license) !!})"></div>
                                                            </a>
                                                            {{-- <label
                                                                class="btn btn-xs btn-icon btn-circle btn-white btn-hover-text-primary btn-shadow"
                                                                data-action="change" title=""
                                                                data-original-title="Change avatar">
                                                                <i class="fa fa-pen icon-sm text-muted"></i>
                                                                <input type="file" name="driving_license"
                                                                       accept=".png, .jpg, .jpeg"/>
                                                            </label> --}}
                                                            {{-- <span
                                                                class="btn btn-xs btn-icon btn-circle btn-white btn-hover-text-primary btn-shadow"
                                                                data-action="cancel" title="Cancel avatar">
                                                                <i class="ki ki-bold-close icon-xs text-muted"></i>
                                                            </span> --}}
                                                        @else
                                                            <div class="image-input-wrapper"
                                                                 style="background-image: url({{ asset('/img/200x200.png')}})"></div>
                                                            {{-- <label
                                                                class="btn btn-xs btn-icon btn-circle btn-white btn-hover-text-primary btn-shadow"
                                                                data-action="change" title=""
                                                                data-original-title="Change avatar">
                                                                <i class="fa fa-pen icon-sm text-muted"></i>
                                                                <input type="file" name="driving_license"
                                                                       accept=".png, .jpg, .jpeg"/>
                                                            </label> --}}
                                                            {{-- <span
                                                                class="btn btn-xs btn-icon btn-circle btn-white btn-hover-text-primary btn-shadow"
                                                                data-action="cancel" title="Cancel avatar">
                                                                <i class="ki ki-bold-close icon-xs text-muted"></i>
                                                            </span> --}}
                                                        @endif
                                                    </div>
                                                </div>
                                                <label class="col-lg-1 col-form-label text-lg-right"> National
                                                    ID:</label>
                                                <div class="col-lg-2">
                                                    <div class="image-input" id="kt_image_n">
                                                        @if(isset($kyc) && !empty($kyc) && $kyc->omang_front!="")
                                                            <a href="{{ \Helper::getCloudFrontURL($kyc->omang_front)}}"
                                                               target="_blank">
                                                                <div class="image-input-wrapper"
                                                                     style="background-image: url({!! \Helper::getCloudFrontURL($kyc->omang_front) !!})"></div>
                                                            </a>
                                                            {{-- <label
                                                                class="btn btn-xs btn-icon btn-circle btn-white btn-hover-text-primary btn-shadow"
                                                                data-action="change" title=""
                                                                data-original-title="Change avatar">
                                                                <i class="fa fa-pen icon-sm text-muted"></i>
                                                                <input type="file" name="nrc_front"
                                                                       accept=".png, .jpg, .jpeg"/>
                                                            </label>
                                                            <span
                                                                class="btn btn-xs btn-icon btn-circle btn-white btn-hover-text-primary btn-shadow"
                                                                data-action="cancel" title="Cancel avatar">
                                                                <i class="ki ki-bold-close icon-xs text-muted"></i>
                                                            </span> --}}
                                                        @else
                                                            <div class="image-input-wrapper"
                                                                 style="background-image: url({{ asset('/img/200x200.png')}})"></div>
                                                            {{-- <label
                                                                class="btn btn-xs btn-icon btn-circle btn-white btn-hover-text-primary btn-shadow"
                                                                data-action="change" title=""
                                                                data-original-title="Change avatar">
                                                                <i class="fa fa-pen icon-sm text-muted"></i>
                                                                <input type="file" name="nrc_front"
                                                                       accept=".png, .jpg, .jpeg"/>
                                                            </label>
                                                            <span
                                                                class="btn btn-xs btn-icon btn-circle btn-white btn-hover-text-primary btn-shadow"
                                                                data-action="cancel" title="Cancel avatar">
                                                                <i class="ki ki-bold-close icon-xs text-muted"></i>
                                                            </span> --}}
                                                        @endif
                                                    </div>
                                                </div>
                                                <label class="col-lg-1 col-form-label text-lg-right"> Proof of
                                                    Residence:</label>
                                                <div class="col-lg-2">
                                                    <div class="image-input" id="kt_image_r">
                                                        @if(isset($kyc) && !empty($kyc) && $kyc->proof_residence!="")
                                                            <a href="{{ \Helper::getCloudFrontURL($kyc->proof_residence)}}"
                                                               target="_blank">
                                                                <div class="image-input-wrapper"
                                                                     style="background-image: url({!! \Helper::getCloudFrontURL($kyc->proof_residence) !!})"></div>
                                                            </a>
                                                            {{-- <label
                                                                class="btn btn-xs btn-icon btn-circle btn-white btn-hover-text-primary btn-shadow"
                                                                data-action="change" title=""
                                                                data-original-title="Change avatar">
                                                                <i class="fa fa-pen icon-sm text-muted"></i>
                                                                <input type="file" name="proof_residence"
                                                                       accept=".png, .jpg, .jpeg"/>
                                                            </label>
                                                            <span
                                                                class="btn btn-xs btn-icon btn-circle btn-white btn-hover-text-primary btn-shadow"
                                                                data-action="cancel" title="Cancel avatar">
                                                                <i class="ki ki-bold-close icon-xs text-muted"></i>
                                                            </span> --}}
                                                        @else
                                                            <div class="image-input-wrapper"
                                                                 style="background-image: url({{ asset('/img/200x200.png')}})"></div>
                                                            {{-- <label
                                                                class="btn btn-xs btn-icon btn-circle btn-white btn-hover-text-primary btn-shadow"
                                                                data-action="change" title=""
                                                                data-original-title="Change avatar">
                                                                <i class="fa fa-pen icon-sm text-muted"></i>
                                                                <input type="file" name="proof_residence"
                                                                       accept=".png, .jpg, .jpeg"/>
                                                            </label>
                                                            <span
                                                                class="btn btn-xs btn-icon btn-circle btn-white btn-hover-text-primary btn-shadow"
                                                                data-action="cancel" title="Cancel avatar">
                                                                <i class="ki ki-bold-close icon-xs text-muted"></i>
                                                            </span> --}}
                                                        @endif
                                                    </div>
                                                </div>
                                                <label class="col-lg-1 col-form-label text-lg-right"> Proof of
                                                    Income:</label>
                                                <div class="col-lg-2">
                                                    <div class="image-input" id="kt_image_i">
                                                        @if(isset($kyc) && !empty($kyc) && $kyc->proof_income!="")
                                                            <a href="{{ \Helper::getCloudFrontURL($kyc->proof_income)}}"
                                                               target="_blank">
                                                                <div class="image-input-wrapper"
                                                                     style="background-image: url({!! \Helper::getCloudFrontURL($kyc->proof_income) !!})"></div>
                                                            </a>
                                                            {{-- <label
                                                                class="btn btn-xs btn-icon btn-circle btn-white btn-hover-text-primary btn-shadow"
                                                                data-action="change" title=""
                                                                data-original-title="Change avatar">
                                                                <i class="fa fa-pen icon-sm text-muted"></i>
                                                                <input type="file" name="proof_income"
                                                                       accept=".png, .jpg, .jpeg"/>
                                                            </label>
                                                            <span
                                                                class="btn btn-xs btn-icon btn-circle btn-white btn-hover-text-primary btn-shadow"
                                                                data-action="cancel" title="Cancel avatar">
                                                                <i class="ki ki-bold-close icon-xs text-muted"></i>
                                                            </span> --}}
                                                        @else
                                                            <div class="image-input-wrapper"
                                                                 style="background-image: url({{ asset('/img/200x200.png')}})"></div>
                                                            {{-- <label
                                                                class="btn btn-xs btn-icon btn-circle btn-white btn-hover-text-primary btn-shadow"
                                                                data-action="change" title=""
                                                                data-original-title="Change avatar">
                                                                <i class="fa fa-pen icon-sm text-muted"></i>
                                                                <input type="file" name="proof_income"
                                                                       accept=".png, .jpg, .jpeg"/>
                                                            </label>
                                                            <span
                                                                class="btn btn-xs btn-icon btn-circle btn-white btn-hover-text-primary btn-shadow"
                                                                data-action="cancel" title="Cancel avatar">
                                                                <i class="ki ki-bold-close icon-xs text-muted"></i>
                                                            </span> --}}
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="form-group row mt-3">
                                                <label class="col-lg-1 col-form-label text-lg-right"> Passport:</label>
                                                <div class="col-lg-2">
                                                    <div class="image-input" id="kt_image_p">
                                                        @if(isset($kyc) && !empty($kyc) && $kyc->passport!="")
                                                            <a href="{{ \Helper::getCloudFrontURL($kyc->passport)}}"
                                                               target="_blank">
                                                                <div class="image-input-wrapper"
                                                                     style="background-image: url({!! \Helper::getCloudFrontURL($kyc->passport) !!})"></div>
                                                            </a>
                                                            {{-- <label
                                                                class="btn btn-xs btn-icon btn-circle btn-white btn-hover-text-primary btn-shadow"
                                                                data-action="change" title=""
                                                                data-original-title="Change avatar">
                                                                <i class="fa fa-pen icon-sm text-muted"></i>
                                                                <input type="file" name="passport_pic"
                                                                       accept=".png, .jpg, .jpeg"/>
                                                            </label>
                                                            <span
                                                                class="btn btn-xs btn-icon btn-circle btn-white btn-hover-text-primary btn-shadow"
                                                                data-action="cancel" title="Cancel avatar">
                                                                <i class="ki ki-bold-close icon-xs text-muted"></i>
                                                            </span> --}}
                                                        @else
                                                            <div class="image-input-wrapper"
                                                                 style="background-image: url({{ asset('/img/200x200.png')}})"></div>
                                                            {{-- <label
                                                                class="btn btn-xs btn-icon btn-circle btn-white btn-hover-text-primary btn-shadow"
                                                                data-action="change" title=""
                                                                data-original-title="Change avatar">
                                                                <i class="fa fa-pen icon-sm text-muted"></i>
                                                                <input type="file" name="passport_pic"
                                                                       accept=".png, .jpg, .jpeg"/>
                                                            </label>
                                                            <span
                                                                class="btn btn-xs btn-icon btn-circle btn-white btn-hover-text-primary btn-shadow"
                                                                data-action="cancel" title="Cancel avatar">
                                                                <i class="ki ki-bold-close icon-xs text-muted"></i>
                                                            </span> --}}
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                            {{-- <button type="submit" class="btn btn-primary" style="float:right"> Upload
                                                Customer KYC Images
                                            </button> --}}
                                        </form>
                                        </tbody>
                                    </table>
                                @endif
                                <div class="separator separator-dashed my-10"></div>
                                <h3 class="card-title align-items-start flex-column">
                                            <span
                                                class="card-label font-weight-bolder font-size-h4 text-dark-75">Product
                                                Details:</span>
                                </h3>
                                <table class="table table-striped m-table">
                                    <tbody>
                                    <tr>
                                        <th>Product Name</th>
                                        @if(isset($product->name))
                                            <td>{!! $product->name !!}</td>
                                        @else
                                            <td>N/A</td>
                                        @endif
                                    </tr>
                                    <tr>
                                        <th>Premium</th>
                                        @if(isset($premium))
                                            <td>R{!! $premium !!}</td>
                                        @else
                                            <td>N/A</td>
                                        @endif
                                    </tr>
                                    <tr>
                                        @if($policy->plan_id != NULL)
                                            <th>Product Plan</th>
                                            @if(isset($productPlan->name))
                                                <td>{!! $productPlan->name !!}</td>
                                            @else
                                                <td>N/A</td>
                                            @endif
                                        @endif
                                    </tr>
                                    <tr>
                                        <th>Sum Assured / Insured</th>
                                        @if(isset($policy->sum_assured))
                                            <td>{!! $policy->sum_assured !!}</td>
                                        @else
                                            <td>N/A</td>
                                        @endif
                                    </tr>
                                    <tr>
                                        <th>Store Name</th>
                                        @if(isset($store))
                                            <td>{!! $store->name !!}</td>
                                        @else
                                            <td>N/A</td>
                                        @endif
                                    </tr>
                                    </tbody>
                                </table>
                                @if($policy->has_vehicle != 0)
                                    <div class="separator separator-dashed my-10"></div>
                                    <h3 class="card-title align-items-start flex-column">
                                        <span class="card-label font-weight-bolder font-size-h4 text-dark-75">
                                            Vehicle Details:</span>
                                    </h3>
                                    <table class="table table-striped m-table">
                                        <tbody>
                                        <tr>
                                            <th>Vehicle Number</th>
                                            @if(isset($vehicle->vehiclePlate))
                                                <td>{!! $vehicle->vehiclePlate !!}</td>
                                            @else
                                                <td>N/A</td>
                                            @endif
                                            <th>Chassis Number(VIN)</th>
                                            @if(isset($vehicle->chassisNo))
                                                <td>{!! $vehicle->chassisNo !!}</td>
                                            @else
                                                <td>N/A</td>
                                            @endif
                                        </tr>
                                        <tr>
                                            <th>Year of Manufacturing</th>
                                            @if(isset($vehicle->year))
                                                <td>{!! $vehicle->year !!}</td>
                                            @else
                                                <td>N/A</td>
                                            @endif
                                            <th>Number of Prior Accidents</th>
                                            @if(isset($vehicle->claim_count))
                                                <td>{!! $vehicle->claim_count !!}</td>
                                            @else
                                                <td>N/A</td>
                                            @endif
                                        </tr>
                                        <tr>
                                            <th>Make</th>
                                            @if(isset($vehicle->make))
                                                <td>{!! $vehicle->make !!}</td>
                                            @else
                                                <td>N/A</td>
                                            @endif
                                            <th>Model</th>
                                            @if(isset($vehicle->model))
                                                <td>{!! $vehicle->model !!}</td>
                                            @else
                                                <td>N/A</td>
                                            @endif
                                        </tr>
                                        <tr>
                                            <th>Engine Number</th>
                                            @if(isset($vehicle->engineNo))
                                                <td>{!! $vehicle->engineNo !!}</td>
                                            @else
                                                <td>N/A</td>
                                            @endif
                                            <th>Number of Seats</th>
                                            @if(isset($vehicle->seats))
                                                <td>{!! $vehicle->seats !!}</td>
                                            @else
                                                <td>N/A</td>
                                            @endif
                                        </tr>
                                        <tr>
                                            <th>Estimated value of Vehicle</th>
                                            @if(isset($vehicle->estimated_value))
                                                <td>{!! $vehicle->estimated_value !!}</td>
                                            @else
                                                <td>N/A</td>
                                            @endif
                                            <th>Is it imported?</th>
                                            @if(isset($vehicle->is_imported))
                                                @if($vehicle->is_imported == 0)
                                                    <td>No</td>
                                                @else
                                                    <td>Yes</td>
                                                @endif
                                            @else
                                                <td>N/A</td>
                                            @endif
                                        </tr>
                                        </tbody>
                                    </table>
                                    <div class="separator separator-dashed my-10"></div>
                                    <h3 class="card-title align-items-start flex-column">
                                        <span class="card-label font-weight-bolder font-size-h4 text-dark-75">
                                            Driver Details:</span>
                                    </h3>
                                    <table class="table table-striped m-table">
                                        <tbody>
                                        @foreach($policyDrivers as $key => $policyDriver)
                                            <tr style="border-top: solid 2px #666;">
                                                <th>Name</th>
                                                @if(isset($policyDriver))
                                                    <td style="text-transform: capitalize">{!! $policyDriver->first_name !!} {!! $policyDriver->middle_name !!} {!! $policyDriver->last_name !!}</td>
                                                @elseif($policyDriver->first_name == null)
                                                    <td>{!! $policyDriver->last_name !!}</td>
                                                @elseif($policyDriver->last_name == null)
                                                    <td>{!! $policyDriver->first_name !!}</td>
                                                @endif
                                                <th>Gender</th>
                                                @if($policyDriver->gender == 1)
                                                    <td> {{"Male"}} </td>
                                                @elseif($policyDriver->gender == 2)
                                                    <td> {{"Other"}} </td>
                                                @else
                                                <td> {{"Female"}} </td>
                                                @endif
                                            </tr>
                                            <tr>
                                                <th>Date of Birth</th>
                                                <td>{{ \Carbon\Carbon::parse($policyDriver->dob)->format('d-m-Y') }}</td>
                                                <th>ID Number</th>
                                                @if(isset($policyDriver))
                                                    <td style="text-transform: uppercase">{!! $policyDriver->id_number !!}</td>
                                                @else
                                                    <td>N/A</td>
                                                @endif
                                            </tr>
                                            <tr>
                                                <th>Driving License Number</th>
                                                @if(isset($policyDriver->license))
                                                    <td style="text-transform: uppercase">{!! $policyDriver->license !!}</td>
                                                @else
                                                    <td>N/A</td>
                                                @endif
                                                <th>License Valid Till</th>
                                                @if(isset($policyDriver->license_expiry))
                                                    <td>{!! $policyDriver->license_expiry !!}</td>
                                                @else
                                                    <td>N/A</td>
                                                @endif
                                            </tr>
                                            <tr>
                                                <th>Cellphone</th>
                                                @if(isset($policyDriver->cellphone))
                                                    <td style="text-transform: uppercase">{!! $policyDriver->cellphone !!}</td>
                                                @else
                                                    <td>N/A</td>
                                                @endif
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                    <div class="separator separator-dashed my-10"></div>
                                    <h3 class="card-title align-items-start flex-column">
                                            <span class="card-label font-weight-bolder font-size-h4 text-dark-75">
                                                Vehicle Preinspection:</span>
                                    </h3>
                                    <table class="table table-striped m-table">
                                        <tbody>
                                        <form action="{{route('uploadVehicleImages',$policy->id)}}" method="post"
                                              enctype="multipart/form-data" class="kt-form">
                                            @csrf
                                            <div class="form-group row mt-3">
                                                <label class="col-lg-1 col-form-label text-lg-right">Left:</label>
                                                <div class="col-lg-2">
                                                    <div class="image-input" id="kt_image_l">
                                                        @if(isset($vehicle) && !empty($vehicle) && $vehicle->left!="")
                                                            <a href="{{ \Helper::getCloudFrontURL($vehicle->left)}}"
                                                               target="_blank">
                                                                <div class="image-input-wrapper"
                                                                     style="background-image: url({{ \Helper::getCloudFrontURL($vehicle->left)}})"></div>
                                                            </a>
                                                            <label
                                                                class="btn btn-xs btn-icon btn-circle btn-white btn-hover-text-primary btn-shadow"
                                                                data-action="change" title=""
                                                                data-original-title="Change avatar">
                                                                <i class="fa fa-pen icon-sm text-muted"></i>
                                                                <input type="file" name="left"
                                                                       accept=".png, .jpg, .jpeg"/>
                                                            </label>
                                                            <span
                                                                class="btn btn-xs btn-icon btn-circle btn-white btn-hover-text-primary btn-shadow"
                                                                data-action="cancel" title="Cancel avatar">
                                                                        <i class="ki ki-bold-close icon-xs text-muted"></i>
                                                                    </span>
                                                        @else
                                                            <div class="image-input-wrapper"
                                                                 style="background-image: url({{ asset('/img/200x200.png')}})"></div>
                                                            <label
                                                                class="btn btn-xs btn-icon btn-circle btn-white btn-hover-text-primary btn-shadow"
                                                                data-action="change" title=""
                                                                data-original-title="Change avatar">
                                                                <i class="fa fa-pen icon-sm text-muted"></i>
                                                                <input type="file" name="left"
                                                                       accept=".png, .jpg, .jpeg"/>
                                                            </label>
                                                            <span
                                                                class="btn btn-xs btn-icon btn-circle btn-white btn-hover-text-primary btn-shadow"
                                                                data-action="cancel" title="Cancel avatar">
                                                                        <i class="ki ki-bold-close icon-xs text-muted"></i>
                                                                    </span>
                                                        @endif
                                                    </div>
                                                </div>
                                                <label class="col-lg-1 col-form-label text-lg-right">Right:</label>
                                                <div class="col-lg-2">
                                                    <div class="image-input" id="kt_image_right">
                                                        @if(isset($vehicle) && !empty($vehicle) && $vehicle->right!="")
                                                            <a href="{{ \Helper::getCloudFrontURL($vehicle->right)}}"
                                                               target="_blank">
                                                                <div class="image-input-wrapper"
                                                                     style="background-image: url({{ \Helper::getCloudFrontURL($vehicle->right)}})"></div>
                                                            </a>
                                                            <label
                                                                class="btn btn-xs btn-icon btn-circle btn-white btn-hover-text-primary btn-shadow"
                                                                data-action="change" title=""
                                                                data-original-title="Change avatar">
                                                                <i class="fa fa-pen icon-sm text-muted"></i>
                                                                <input type="file" name="right"
                                                                       accept=".png, .jpg, .jpeg"/>
                                                            </label>
                                                            <span
                                                                class="btn btn-xs btn-icon btn-circle btn-white btn-hover-text-primary btn-shadow"
                                                                data-action="cancel" title="Cancel avatar">
                                                                            <i class="ki ki-bold-close icon-xs text-muted"></i>
                                                                        </span>
                                                        @else
                                                            <div class="image-input-wrapper"
                                                                 style="background-image: url({{ asset('/img/200x200.png')}})"></div>
                                                            <label
                                                                class="btn btn-xs btn-icon btn-circle btn-white btn-hover-text-primary btn-shadow"
                                                                data-action="change" title=""
                                                                data-original-title="Change avatar">
                                                                <i class="fa fa-pen icon-sm text-muted"></i>
                                                                <input type="file" name="right"
                                                                       accept=".png, .jpg, .jpeg"/>
                                                            </label>
                                                            <span
                                                                class="btn btn-xs btn-icon btn-circle btn-white btn-hover-text-primary btn-shadow"
                                                                data-action="cancel" title="Cancel avatar">
                                                                            <i class="ki ki-bold-close icon-xs text-muted"></i>
                                                                        </span>
                                                        @endif
                                                    </div>
                                                </div>
                                                <label class="col-lg-1 col-form-label text-lg-right">Back:</label>
                                                <div class="col-lg-2">
                                                    <div class="image-input" id="kt_image_b">
                                                        @if(isset($vehicle) && !empty($vehicle) && $vehicle->back!="")
                                                            <a href="{{ \Helper::getCloudFrontURL($vehicle->back)}}"
                                                               target="_blank">
                                                                <div class="image-input-wrapper"
                                                                     style="background-image: url({{ \Helper::getCloudFrontURL($vehicle->back)}})"></div>
                                                            </a>
                                                            <label
                                                                class="btn btn-xs btn-icon btn-circle btn-white btn-hover-text-primary btn-shadow"
                                                                data-action="change" title=""
                                                                data-original-title="Change avatar">
                                                                <i class="fa fa-pen icon-sm text-muted"></i>
                                                                <input type="file" name="back"
                                                                       accept=".png, .jpg, .jpeg"/>
                                                            </label>
                                                            <span
                                                                class="btn btn-xs btn-icon btn-circle btn-white btn-hover-text-primary btn-shadow"
                                                                data-action="cancel" title="Cancel avatar">
                                                                                <i class="ki ki-bold-close icon-xs text-muted"></i>
                                                                            </span>
                                                        @else
                                                            <div class="image-input-wrapper"
                                                                 style="background-image: url({{ asset('/img/200x200.png')}})"></div>
                                                            <label
                                                                class="btn btn-xs btn-icon btn-circle btn-white btn-hover-text-primary btn-shadow"
                                                                data-action="change" title=""
                                                                data-original-title="Change avatar">
                                                                <i class="fa fa-pen icon-sm text-muted"></i>
                                                                <input type="file" name="back"
                                                                       accept=".png, .jpg, .jpeg"/>
                                                            </label>
                                                            <span
                                                                class="btn btn-xs btn-icon btn-circle btn-white btn-hover-text-primary btn-shadow"
                                                                data-action="cancel" title="Cancel avatar">
                                                                                <i class="ki ki-bold-close icon-xs text-muted"></i>
                                                                            </span>
                                                        @endif
                                                    </div>
                                                </div>
                                                <label class="col-lg-1 col-form-label text-lg-right">Front:</label>
                                                <div class="col-lg-2">
                                                    <div class="image-input" id="kt_image_f">
                                                        @if(isset($vehicle) && !empty($vehicle) && $vehicle->front!="")
                                                            <a href="{{ \Helper::getCloudFrontURL($vehicle->front)}}"
                                                               target="_blank">
                                                                <div class="image-input-wrapper"
                                                                     style="background-image: url({{ \Helper::getCloudFrontURL($vehicle->front)}})"></div>
                                                            </a>
                                                            <label
                                                                class="btn btn-xs btn-icon btn-circle btn-white btn-hover-text-primary btn-shadow"
                                                                data-action="change" title=""
                                                                data-original-title="Change avatar">
                                                                <i class="fa fa-pen icon-sm text-muted"></i>
                                                                <input type="file" name="front"
                                                                       accept=".png, .jpg, .jpeg"/>
                                                            </label>
                                                            <span
                                                                class="btn btn-xs btn-icon btn-circle btn-white btn-hover-text-primary btn-shadow"
                                                                data-action="cancel" title="Cancel avatar">
                                                                            <i class="ki ki-bold-close icon-xs text-muted"></i>
                                                                        </span>
                                                        @else
                                                            <div class="image-input-wrapper"
                                                                 style="background-image: url({{ asset('/img/200x200.png')}})"></div>
                                                            <label
                                                                class="btn btn-xs btn-icon btn-circle btn-white btn-hover-text-primary btn-shadow"
                                                                data-action="change" title=""
                                                                data-original-title="Change avatar">
                                                                <i class="fa fa-pen icon-sm text-muted"></i>
                                                                <input type="file" name="front"
                                                                       accept=".png, .jpg, .jpeg"/>
                                                            </label>
                                                            <span
                                                                class="btn btn-xs btn-icon btn-circle btn-white btn-hover-text-primary btn-shadow"
                                                                data-action="cancel" title="Cancel avatar">
                                                                            <i class="ki ki-bold-close icon-xs text-muted"></i>
                                                                        </span>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="form-group row mt-3">
                                                <label class="col-lg-1 col-form-label text-lg-right">Vehicle
                                                    Registration:</label>
                                                <div class="col-lg-2">
                                                    <div class="image-input" id="kt_image_v">
                                                        @if(isset($vehicle) && !empty($vehicle) && $vehicle->vehicleRegistration!="")
                                                            <a href="{{ \Helper::getCloudFrontURL($vehicle->vehicleRegistration)}}"
                                                               target="_blank">
                                                                <div class="image-input-wrapper"
                                                                     style="background-image: url({{ \Helper::getCloudFrontURL($vehicle->vehicleRegistration)}})"></div>
                                                            </a>
                                                            <label
                                                                class="btn btn-xs btn-icon btn-circle btn-white btn-hover-text-primary btn-shadow"
                                                                data-action="change" title=""
                                                                data-original-title="Change avatar">
                                                                <i class="fa fa-pen icon-sm text-muted"></i>
                                                                <input type="file" name="vehicleRegistration"
                                                                       accept=".png, .jpg, .jpeg"/>
                                                            </label>
                                                            <span
                                                                class="btn btn-xs btn-icon btn-circle btn-white btn-hover-text-primary btn-shadow"
                                                                data-action="cancel" title="Cancel avatar">
                                                                        <i class="ki ki-bold-close icon-xs text-muted"></i>
                                                                        </span>
                                                        @else
                                                            <div class="image-input-wrapper"
                                                                 style="background-image: url({{ asset('/img/200x200.png')}})"></div>
                                                            <label
                                                                class="btn btn-xs btn-icon btn-circle btn-white btn-hover-text-primary btn-shadow"
                                                                data-action="change" title=""
                                                                data-original-title="Change avatar">
                                                                <i class="fa fa-pen icon-sm text-muted"></i>
                                                                <input type="file" name="vehicleRegistration"
                                                                       accept=".png, .jpg, .jpeg"/>
                                                            </label>
                                                            <span
                                                                class="btn btn-xs btn-icon btn-circle btn-white btn-hover-text-primary btn-shadow"
                                                                data-action="cancel" title="Cancel avatar">
                                                                        <i class="ki ki-bold-close icon-xs text-muted"></i>
                                                                        </span>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                            <button type="submit" class="btn btn-primary" style="float:right">Upload
                                                Vehicle Images
                                            </button>
                                        </form>
                                        </tbody>
                                    </table>
                                @endif
                                @if($policy->has_vehicle != 0)
                                    <div class="separator separator-dashed my-10"></div>
                                    <h3 class="card-title align-items-start flex-column">
                                        <span class="card-label font-weight-bolder font-size-h4 text-dark-75">
                                            Coverages:</span>
                                    </h3>
                                    <table class="table table-striped m-table">
                                        <tbody>
                                        <th>Main</th>
                                        <th>Coverage Value</th>
                                        <th>Disc/Surcharge</th>
                                        <th>Flat/%</th>
                                        <th>Value</th>
                                        @foreach($policyCover as $key => $cover)
                                            <tr>
                                                <td>{!! $cover->main !!}</td>
                                                @if(isset($cover->coverage_value))
                                                    <td>{!! $cover->coverage_value !!}</td>
                                                @else
                                                    <td>-</td>
                                                @endif

                                                @if(isset($cover->discount) && $cover->discount!= 0)
                                                    @if($cover->discount == 1)
                                                        <td>Discount</td>
                                                    @elseif($cover->discount == 2)
                                                        <td>Surcharge</td>
                                                    @endif
                                                @else
                                                    <td>-</td>
                                                @endif

                                                @if(isset($cover->type))
                                                    @if($cover->type == 1)
                                                        <td>Flat</td>
                                                    @elseif($cover->type == 2)
                                                        <td>%</td>
                                                    @endif
                                                @else
                                                    <td>-</td>
                                                @endif

                                                @if(isset($cover->value))
                                                    <td>{!! $cover->value !!}</td>
                                                @else
                                                    <td>-</td>
                                                @endif

                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                @elseif($policy->has_member != 0)
                                    <div class="separator separator-dashed my-10"></div>
                                    <h3 class="card-title align-items-start flex-column">
                                        <span class="card-label font-weight-bolder font-size-h4 text-dark-75">
                                        Beneficiary Details:</span>
                                    </h3>
                                    <table class="table table-striped m-table">
                                        <tbody>
                                        @foreach($beneficiaries as $key => $beneficiary)
                                            <tr style="border-top: solid 2px #666;">
                                                <th>Relation with beneficiary</th>
                                                <td style="text-transform: capitalize">{!! $beneficiary->relation !!}</td>
                                                <th>Name</th>
                                                <td style="text-transform: capitalize">{!! $beneficiary->first_name !!} {!! $beneficiary->middle_name !!} {!! $beneficiary->last_name !!}</td>
                                            </tr>
                                            <tr>
                                                <th>Date of Birth</th>
                                                <td>{{ \Carbon\Carbon::parse($beneficiary->dob)->format('d-m-Y') }}</td>
                                                <th>Gender</th>
                                                @if($beneficiary->gender == 0)
                                                    <td>Female</td>
                                                @elseif($beneficiary->gender == 1)
                                                    <td>Male</td>
                                                @else
                                                    <td>Other</td>
                                                @endif
                                            </tr>
                                            <tr>
                                                <th>Available Identity Type</th>
                                                @if($beneficiary->passport != null)
                                                <td>Passport </td>
                                                @elseif($beneficiary->omang != null)
                                                <td>National ID </td>
                                                @endif

                                                <th>Number</th>
                                                    @if($beneficiary->omang != null)
                                                    <td style="text-transform: uppercase">{{ $beneficiary->omang }} </td>
                                                    @elseif($beneficiary->passport!= null)
                                                    <td style="text-transform: uppercase">{{ $beneficiary->passport }} </td>
                                                    @endif
                                            </tr>
                                            <tr>
                                                <th>Payment</th>
                                                <td>{!! $beneficiary->payment !!} %</td>
                                                <th>&nbsp</th>
                                                <td></td>
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                @endif
                                @if($policy->note != 0)
                                    <div class="separator separator-dashed my-10"></div>
                                    <h3 class="card-title align-items-start flex-column">
                                            <span class="card-label font-weight-bolder font-size-h4 text-dark-75">
                                            Note:</span>
                                    </h3>
                                    <table class="table ">
                                        <tbody>
                                        <tr>
                                            <td>{!! $policy->note !!} </td>
                                        </tr>
                                        </tbody>
                                    </table>
                                @endif
                                @if($policy->has_subApplicant != 0)
                                    <div class="separator separator-dashed my-10"></div>
                                    <h3 class="card-title align-items-start flex-column">
                                            <span class="card-label font-weight-bolder font-size-h4 text-dark-75">
                                            Sub Applicant Details:</span>
                                    </h3>
                                    <table class="table table-striped m-table">
                                        <tbody>
                                        @foreach($members as $key => $member)
                                            <tr style="border-top: solid 2px #666;">
                                                <th>Relation</th>
                                                <td>{!! $member->relation !!}</td>
                                                <th>Name</th>
                                                <td>{!! $member->first_name !!} {!!
                                                            $member->last_name !!}</td>
                                            </tr>
                                            <tr style="border-bottom: solid 2px #666;">
                                                <th>Date of Birth</th>
                                                <td>{!! $member->dob !!}</td>
                                                <th>Gender</th>
                                                @if($member->gender == 0)
                                                    <td>Female</td>
                                                @else
                                                    <td>Male</td>
                                                @endif
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                @endif
                                <div class="separator separator-dashed my-10"></div>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="billingDetails" role="tabpanel" aria-labelledby="billingDetails">
                            <h3 class="card-title align-items-start flex-column">
                                    <span class="card-label font-weight-bolder font-size-h4 text-dark-75">
                                        Customer Billing Details:</span>
                            </h3>
                            <table class="table table-striped m-table">
                                <tbody>
                                {{--                                <tr>--}}
                                {{--                                    @if($banking != NULL )--}}
                                {{--                                        <th>Myzaka/Orange Money Cell</th>--}}
                                {{--                                        <td>{!! $banking->billingCell !!}</td>--}}
                                {{--                                        <th>Bank Name</th>--}}
                                {{--                                        <td><span class="bankNumRealPay"> {!!--}}
                                {{--                                         $banking->bankName !!}</span>--}}
                                {{--                                        </td>--}}
                                {{--                                    @else--}}
                                {{--                                        <th>Myzaka/Orange Money Cell</th>--}}
                                {{--                                        <td></td>--}}
                                {{--                                        <th>Bank Name</th>--}}
                                {{--                                        <td></td>--}}
                                {{--                                    @endif--}}
                                {{--                                </tr>--}}
                                @if(isset($bankingdetails->paymentid))
                                    <tr>
                                        <th>Card Number</th>
                                        <td>XXXX-XXXX-XXXX-{!! $bankingdetails->last4Digits !!}</td>
                                        <th>Card Expiry month</th>
                                        @if($bankingdetails->expiryMonth != NULL)
                                            <td>{{$bankingdetails->expiryMonth}}</td>
                                        @endif
                                    </tr>
                                    <tr>
                                        <th>Card Expiry Year</th>
                                        <td>{!! $bankingdetails->expiryYear !!}</td>
                                        <th>Payment ID</th>
                                        <td>{!! $bankingdetails->paymentid !!}</td>
                                    </tr>
                                @else
                                    <p>No Billing Details Found for policy</p>
                                @endif
                                </tbody>
                            </table>
                        </div>
                        <div class="tab-pane fade" id="policyDocument" role="tabpanel" aria-labelledby="policyDocument">
                            <div class="form-group row">
                                <label class="col-lg-3 card-label font-weight-bolder font-size-h5 text-dark-75">Customer Email:</label>
                                <div class="col-lg-6">
                                    @if($user != null && $user->email)
                                        <label class="card-label font-weight-bolder font-size-h5 text-dark-75"> {!! $user->email !!} </label>
                                    @else
                                        <label class="card-label font-weight-bolder font-size-h5 text-dark-75">N/A</label>
                                        @endif
                                        </label>
                                </div>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="TransactionLogs" role="tabpanel" aria-labelledby="TransactionLogs">
                            <div class="card-body">
                                <livewire:payment-transaction-table :policy="$policy" exportable/>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="schedules" role="tabpanel" aria-labelledby="schedules">
                            <div class="card-body">
                                <livewire:schedule-transactions :policy="$policy" exportable/>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="Ledger" role="tabpanel" aria-labelledby="Ledger">
                            <ul class="nav nav-primary nav-pills justify-content-center" id="myTab3" role="tablist">
                                <li class="nav-item">
                                    <a class="nav-link active" id="home-tab-3" data-toggle="tab" href="#home-3">
                                            <span class="nav-icon">
												<span class="svg-icon svg-icon-sm">
												<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                                                    <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                                        <rect x="0" y="0" width="24" height="24"/>
                                                        <path d="M5,19 L20,19 C20.5522847,19 21,19.4477153 21,20 C21,20.5522847 20.5522847,21 20,21 L4,21 C3.44771525,21 3,20.5522847 3,20 L3,4 C3,3.44771525 3.44771525,3 4,3 C4.55228475,3 5,3.44771525 5,4 L5,19 Z" fill="#000000" fill-rule="nonzero"/>
                                                        <path d="M8.7295372,14.6839411 C8.35180695,15.0868534 7.71897114,15.1072675 7.31605887,14.7295372 C6.9131466,14.3518069 6.89273254,13.7189711 7.2704628,13.3160589 L11.0204628,9.31605887 C11.3857725,8.92639521 11.9928179,8.89260288 12.3991193,9.23931335 L15.358855,11.7649545 L19.2151172,6.88035571 C19.5573373,6.44687693 20.1861655,6.37289714 20.6196443,6.71511723 C21.0531231,7.05733733 21.1271029,7.68616551 20.7848828,8.11964429 L16.2848828,13.8196443 C15.9333973,14.2648593 15.2823707,14.3288915 14.8508807,13.9606866 L11.8268294,11.3801628 L8.7295372,14.6839411 Z" fill="#000000" fill-rule="nonzero" opacity="0.3"/>
                                                    </g>
                                                </svg>
												</span>
											</span>
                                        <span class="nav-text">Account view</span>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" id="profile-tab-3" data-toggle="tab" href="#profile-3" aria-controls="profile">
                                            <span class="nav-icon">
                                                <span class="svg-icon svg-icon-sm">
                                                <svg xmlns="http://www.w3.org/2000/svg"
                                                     xmlns:xlink="http://www.w3.org/1999/xlink" width="24px"
                                                     height="24px" viewBox="0 0 24 24" version="1.1">
                                                    <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                                        <rect x="0" y="0" width="24" height="24"/>
                                                        <path
                                                            d="M18.5,6 C19.3284271,6 20,6.67157288 20,7.5 L20,18.5 C20,19.3284271 19.3284271,20 18.5,20 C17.6715729,20 17,19.3284271 17,18.5 L17,7.5 C17,6.67157288 17.6715729,6 18.5,6 Z M12.5,11 C13.3284271,11 14,11.6715729 14,12.5 L14,18.5 C14,19.3284271 13.3284271,20 12.5,20 C11.6715729,20 11,19.3284271 11,18.5 L11,12.5 C11,11.6715729 11.6715729,11 12.5,11 Z M6.5,15 C7.32842712,15 8,15.6715729 8,16.5 L8,18.5 C8,19.3284271 7.32842712,20 6.5,20 C5.67157288,20 5,19.3284271 5,18.5 L5,16.5 C5,15.6715729 5.67157288,15 6.5,15 Z"
                                                            fill="#000000"/>
                                                    </g>
                                                </svg>
												</span>
											</span>
                                        <span class="nav-text">Recievable View</span>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" id="contact-tab-3" data-toggle="tab" href="#contact-3" aria-controls="contact">
																	<span class="nav-icon">
												<span class="svg-icon svg-icon-sm">
												<svg
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    xmlns:xlink="http://www.w3.org/1999/xlink"
                                                    width="24px" height="24px"
                                                    viewBox="0 0 24 24" version="1.1">
													<g stroke="none" stroke-width="1"
                                                       fill="none" fill-rule="evenodd">
														<polygon
                                                            points="0 0 24 0 24 24 0 24"/>
														<path
                                                            d="M4.85714286,1 L11.7364114,1 C12.0910962,1 12.4343066,1.12568431 12.7051108,1.35473959 L17.4686994,5.3839416 C17.8056532,5.66894833 18,6.08787823 18,6.52920201 L18,19.0833333 C18,20.8738751 17.9795521,21 16.1428571,21 L4.85714286,21 C3.02044787,21 3,20.8738751 3,19.0833333 L3,2.91666667 C3,1.12612489 3.02044787,1 4.85714286,1 Z M8,12 C7.44771525,12 7,12.4477153 7,13 C7,13.5522847 7.44771525,14 8,14 L15,14 C15.5522847,14 16,13.5522847 16,13 C16,12.4477153 15.5522847,12 15,12 L8,12 Z M8,16 C7.44771525,16 7,16.4477153 7,17 C7,17.5522847 7.44771525,18 8,18 L11,18 C11.5522847,18 12,17.5522847 12,17 C12,16.4477153 11.5522847,16 11,16 L8,16 Z"
                                                            fill="#000000"
                                                            fill-rule="nonzero"
                                                            opacity="0.3"/>
														<path
                                                            d="M6.85714286,3 L14.7364114,3 C15.0910962,3 15.4343066,3.12568431 15.7051108,3.35473959 L20.4686994,7.3839416 C20.8056532,7.66894833 21,8.08787823 21,8.52920201 L21,21.0833333 C21,22.8738751 20.9795521,23 19.1428571,23 L6.85714286,23 C5.02044787,23 5,22.8738751 5,21.0833333 L5,4.91666667 C5,3.12612489 5.02044787,3 6.85714286,3 Z M8,12 C7.44771525,12 7,12.4477153 7,13 C7,13.5522847 7.44771525,14 8,14 L15,14 C15.5522847,14 16,13.5522847 16,13 C16,12.4477153 15.5522847,12 15,12 L8,12 Z M8,16 C7.44771525,16 7,16.4477153 7,17 C7,17.5522847 7.44771525,18 8,18 L11,18 C11.5522847,18 12,17.5522847 12,17 C12,16.4477153 11.5522847,16 11,16 L8,16 Z"
                                                            fill="#000000"
                                                            fill-rule="nonzero"/>
													</g>
												</svg>
												</span>
											</span>
                                        <span class="nav-text">Invoicing</span>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" id="contact-tab-4" data-toggle="tab" href="#contact-4" aria-controls="contact">
                                            <span class="nav-icon">
												<span class="svg-icon svg-icon-sm">
												 <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                                                    <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                                        <rect x="0" y="0" width="24" height="24"/>
                                                        <rect fill="#000000" opacity="0.3" x="7" y="4" width="10" height="4"/>
                                                        <path d="M7,2 L17,2 C18.1045695,2 19,2.8954305 19,4 L19,20 C19,21.1045695 18.1045695,22 17,22 L7,22 C5.8954305,22 5,21.1045695 5,20 L5,4 C5,2.8954305 5.8954305,2 7,2 Z M8,12 C8.55228475,12 9,11.5522847 9,11 C9,10.4477153 8.55228475,10 8,10 C7.44771525,10 7,10.4477153 7,11 C7,11.5522847 7.44771525,12 8,12 Z M8,16 C8.55228475,16 9,15.5522847 9,15 C9,14.4477153 8.55228475,14 8,14 C7.44771525,14 7,14.4477153 7,15 C7,15.5522847 7.44771525,16 8,16 Z M12,12 C12.5522847,12 13,11.5522847 13,11 C13,10.4477153 12.5522847,10 12,10 C11.4477153,10 11,10.4477153 11,11 C11,11.5522847 11.4477153,12 12,12 Z M12,16 C12.5522847,16 13,15.5522847 13,15 C13,14.4477153 12.5522847,14 12,14 C11.4477153,14 11,14.4477153 11,15 C11,15.5522847 11.4477153,16 12,16 Z M16,12 C16.5522847,12 17,11.5522847 17,11 C17,10.4477153 16.5522847,10 16,10 C15.4477153,10 15,10.4477153 15,11 C15,11.5522847 15.4477153,12 16,12 Z M16,16 C16.5522847,16 17,15.5522847 17,15 C17,14.4477153 16.5522847,14 16,14 C15.4477153,14 15,14.4477153 15,15 C15,15.5522847 15.4477153,16 16,16 Z M16,20 C16.5522847,20 17,19.5522847 17,19 C17,18.4477153 16.5522847,18 16,18 C15.4477153,18 15,18.4477153 15,19 C15,19.5522847 15.4477153,20 16,20 Z M8,18 C7.44771525,18 7,18.4477153 7,19 C7,19.5522847 7.44771525,20 8,20 L12,20 C12.5522847,20 13,19.5522847 13,19 C13,18.4477153 12.5522847,18 12,18 L8,18 Z M7,4 L7,8 L17,8 L17,4 L7,4 Z" fill="#000000"/>
                                                    </g>
                                                </svg>
												</span>
											</span>
                                        <span class="nav-text">Sub Ledger</span>
                                    </a>
                                </li>
                            </ul>
                            <div class="tab-content mt-5" id="myTabContent3">
                                <div class="tab-pane fade show active" id="home-3" role="tabpanel" aria-labelledby="home-tab-3">
                                    <livewire:policy-recievable-table exportable/>
                                </div>
                                <div class="tab-pane fade" id="profile-3" role="tabpanel" aria-labelledby="profile-tab-3">
                                    <livewire:policy-recievable-table exportable/>
                                </div>
                                <div class="tab-pane fade" id="contact-3" role="tabpanel" aria-labelledby="contact-tab-3">
                                    <livewire:policy-recievable-table exportable/>
                                </div>
                                <div class="tab-pane fade" id="contact-4" role="tabpanel" aria-labelledby="contact-tab-4">
                                    <livewire:policy-subledger-table exportable/>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Cancle Policy Modal-->

    <div class="modal fade" id="canclePolicy" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLabel">Please select an option</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="container text-center">
                   <form action="">
                   @csrf
                        <button type="button" data-toggle="modal" data-target="#otpModal" class="btn btn-primary ml-2 getOTPButton" data-dismiss="modal">Request OTP</button>
                        <button type="button" data-toggle="modal" data-target="#otpModal" class="btn btn-primary mr-2 getOTPButton" data-dismiss="modal">Resend OTP</button>
                    </form>
                </div>
                <div class="text-center mt-4">
                    <a style="color:blue" data-toggle="modal" data-target="#otpModal" class="text-center" href="">Already have an OTP</a>
                </div>
            </div>

            </div>
        </div>
    </div>
   <!--Enter Otp Module-->

    <div class="modal fade" id="otpModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLabel">Customer Authentication</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                              <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
              <form action="">
                  @csrf
                <div class="container text-center">
                      <label for=""> OTP sent to <span >{{ $user->cellphone}}</span></label>
                      <input type="number" id="inputOTP" class="form-control" name="OTP" placeholder="Plese enter OTP">
                      <input type="hidden" value='{{$policy->id}}' id="policyId">
                      <span class="wrongOTP" style="color: red">Please enter correct OTP.</span>

                       <span id="otpMsg"></span>
                </div>
                <div class="text-center mt-4">
                     <button type="button" class="btn btn-danger" data-dismiss="modal">Close</button>
                     <button  type="button"   class="btn btn-primary submitOTP" >Submit OTP</button>
                </div>
                </form>
            </div>

            </div>
        </div>
    </div>

   <!--Enter Otp Module-->

     <!-- Cancle Policy Modal-->

    @push('scripts')
        <script>
            $(document).ready(function () {
                if ($('#product').val() != "") {
                    $('#product').trigger('change');
                }
                var avatar1 = new KTImageInput('kt_image_l');
                var avatar2 = new KTImageInput('kt_image_right');
                var avatar3 = new KTImageInput('kt_image_b');
                var avatar4 = new KTImageInput('kt_image_f');
                var avatar5 = new KTImageInput('kt_image_v');
                var avatar6 = new KTImageInput('kt_image_6');
                var avatar7 = new KTImageInput('kt_image_d');
                var avatar8 = new KTImageInput('kt_image_n');
                var avatar9 = new KTImageInput('kt_image_r');
                var avatar10 = new KTImageInput('kt_image_i');
                var avatar11 = new KTImageInput('kt_image_p');
                var btn = KTUtil.getById("sub_but");
                $('#sub_but').on('click', function (e) {
                    KTUtil.btnWait(btn, "spinner spinner-right spinner-white pr-15", "Please wait");
                    e.preventDefault();
                    validation.validate().then(function (status) {
                        if (status == 'Valid') {
                            $('form#product').submit();
                        } else {
                            var ehtml = "<ol>";
                            $($('.fv-help-block')).each(function (index) {
                                ehtml += "<li style='list-style-type:disc;color:red;'><b>" + $(this).text() + "</b></li>";
                            });
                            ehtml += "</ol>"
                            swal.fire({
                                html: ehtml,
                                icon: "error",
                                buttonsStyling: false,
                                confirmButtonText: "Ok, got it!",
                                customClass: {
                                    confirmButton: "btn font-weight-bold btn-light-primary"
                                }
                            }).then(function () {
                                KTUtil.btnRelease(btn);
                            });
                        }
                    });
                });
            });
            $('#product').on('change', function (e, data) {
                var product_id = $('#selected_product_id').val();
                // cellphone product_id == 5
                if (product_id == 5) {
                    $('#addCellphoneDiv').show();
                } else {
                    $('#addCellphoneDiv').hide();
                }
                var selected = '';
                $.ajax({
                    url: '{{ route('getProductFactors') }}',
                    data: {
                        "_token": "{{ csrf_token() }}",
                        "id": product_id,
                        "nid": $('#nid').val(),
                        "passport": $('#passport').val(),
                    },
                    type: 'post',
                    datatype: 'json',
                    success: function (data) {
                        var append = '';
                        var coverage = '';
                        if (data) {
                            if (data.status === 'existing') {
                                $('#existingProduct').css('display', 'block');
                            } else if (data.status === 'noCustomer') {
                                $('#existingProduct').text('Please enter omang or Passport of Customer to Select Product')
                                $('#existingProduct').css('display', 'block');
                            } else {
                                $('#existingProduct').css('display', 'none');
                                $('#factor_main').empty();
                                /*15 is the id of dynamic  preminum_type  which is coming from lookup data table*/
                                if (data.product.premium_type_id === 15) {
                                    append += '<div class="col-lg-12">' +
                                        '<button type="button" id="calculate" class="btn btn-primary col-lg-2" style="float: left; clear: left;">Calculate Premium</button>' +
                                        ' ' +
                                        '<div class="col-lg-10" id="premiumDiv" style="margin: 0.5% 0 0 20%;">' +
                                        '</div></div>';
                                    append += '<div class="col-lg-8" style="margin-top: 20px;">';
                                    if (data.product.sum_insured != null)
                                        append += '<h5>Sum Assured/Insured : P ' + data.product.sum_insured + '</h5>';
                                    else
                                        append += '<h5>Sum Assured/Insured Limit not set for this product.</h5>';
                                    append += '<input type="hidden" class="form-control" name="sum_assured" value="' + data.product.sum_insured + '" placeholder="Please enter Sum Assured/Insured">' +
                                        '</div>';
                                }
                                /* 11 is the id of manual preminum_type  which is coming from lookup data table */
                                else if (data.product.premium_type_id === 11) {

                                    append += '<option value="">Select Plan</option>';
                                    if (data.plans.length > 0) {
                                        data.plans.forEach(function ($value) {
                                            if ($('#selected_plan_id').val() == $value.id) {
                                                selected = 'selected';
                                            } else {
                                                selected = '';
                                            }
                                            append += '<option value="' + $value.id + '" ' + selected + ' content="' + $value.sum_assured + '" >' + $value.name + ' | Premium : ' + $value.premium + ' | Sum Assured : ' + $value.sum_assured + '</option>';
                                        });
                                    }


                                }
                                $('#plan').html(append);
                                $('#planID').hide();
                                if (data.product.has_vehicle == 1 || data.product.is_motor_items == 1) {
                                    if (data.product.has_vehicle == 1) {
                                        $('#vehicle_section').show();
                                    }
                                    if (data.product.is_motor_items == 1) {
                                        $('#motors').show();
                                    }
                                    $('#coverageDiv').show();
                                    $('#coverageDiv').html(data.coverageData);
                                } else {
                                    $('#vehicle_section').hide();
                                    $('#motors').hide();
                                    $('#coverageDiv').hide();
                                    $('#coverageDiv').html("");
                                }
                                if (data.product.kyc_customer == 1) {
                                    $('#customerKYCDiv').show();
                                } else {
                                    $('#customerKYCDiv').hide();
                                }
                                if (data.product.has_member == 1) {
                                    $('#addBeneficiaryDiv').show();
                                } else {
                                    $('#addBeneficiaryDiv').hide();
                                }

                            }

                        } else {
                            $('#factor_main').empty();
                            $('#factor_main').append("Nothing to Show");
                        }
                    },
                });

            });
            function isNumberKey(evt) {
                var charCode = (evt.which) ? evt.which : evt.keyCode;
                if (charCode != 46 && charCode > 31
                    && (charCode < 48 || charCode > 57))
                    return false;
                return true;
            }
            setInterval(function () {
                $('#loading').hide();
            }, 5000);

           /****** cancel policy *****/

           jQuery(document).ready(function () {
            $('.wrongOTP').slideUp();
            $('.noPaymentRef').slideUp();
            //KTDatatablesDataSourceAjaxServer.init();
            var cellphone = $('#cphnumber').html();

             $('.wrongOTP', '.noPaymentRef').slideUp();
            // $('.otpInput').on('keyup keydown paste', function () {
            //     $('.wrongOTP').slideUp();
            //     $('.noPaymentRef').slideUp();
            // })

            // $('#alredayOtp').on('click', function () {
            //     $('#customer_OTP_options').modal('hide');
            //     $('#customer_OTP_already').modal('show');
            // });

            // $('#no_OTP').on('click', function () {
            //     $('#customer_OTP_already').modal('hide');
            //     $('#customer_OTP_options').modal('show');
            // });

            $('#CancelPolicy').on('click', function () {
                $('#customer_OTP_options').modal('show');
                $('#action').val(2);
            });
            $('.activatePolicyButton').on('click', function () {
                $('#customer_OTP_options').modal('show');
                $('#action').val(1);
            });

            $(document).on('click', '.getOTPButton', function () {
                if (cellphone) {
                    var maskedCellphone = cellphone.slice(0, 2);
                    maskedCellphone = maskedCellphone + '###' + cellphone.slice(5, 9) + '.';
                    event.preventDefault();
                    $.ajax({
                        url: '{{ route('policy.getModalData') }}',
                        data: {
                            "_token": "{{ csrf_token() }}",
                            "cellphone": cellphone,
                            "email": $('#email').html(),
                        },
                        beforeSend: function () {
                            $("#loader").show();
                        },
                        type: 'post',
                        datatype: 'json',
                        success: function (data) {
                            $("#loader").hide();
                            $("#otpInput").val('');
                            $('#customer_OTP_options').modal('hide');
                            $('#data_body').html('Please provide the OTP sent to ' +
                                maskedCellphone);
                            $('#submit_otp').modal('show');
                        },
                    })
                } else {
                    $('#customer_no_cellphone').modal('show');
                }

            });

            $(document).on('click', '.submitOTP', function () {
                var phoneNumber = $('#cphnumber').html();
                var otpCode = $('#inputOTP').val();
                var policyId= $("#policyId").val();


                $('.clear').on('click', function () {
                    $('.otpInput').val('');
                    $('.wrongOTP').slideUp();
                    $('.noPaymentRef').slideUp();
                })

                if (phoneNumber != '' && otpCode != '') {
                    var ajaxRequest = setTimeout(function (sn) {
                        $.ajax({
                            url: '{{ route('policy.verifyOTP') }}',
                            data: {
                                "_token": "{{ csrf_token() }}",
                                "phoneNumber": phoneNumber,
                                "otpCode": otpCode,
                                "policyId":policyId,                                // "fire_from": "{{request()->get('fire_from')}}"

                            },
                            beforeSend: function () {
                                $("#loader").show();
                            },
                            type: 'post',
                            datatype: 'json',
                            success: function (data) {
                                //console.log(data);
                                $("#loader").show();
                                if (data.status == 'success') {
                                    $('#otpMsg').removeClass('text-danger');
                                    $('#otpMsg').addClass('text-success');
                                    $('#otpMsg').text(data.message +
                                        '. Please wait..');
                                    $.ajax({
                                        url: '{{ route('policy.cancel') }}',
                                        data: {

                                            "_token": "{{ csrf_token() }}",
                                            "policyId":policyId,
                                        },
                                        beforeSend: function () {
                                            $("#loader").show();
                                        },
                                        type: 'post',
                                        datatype: 'json',
                                        success: function (data) {
                                            $('#canclePolicy').hide();
                                            $('#otpModal').hide();
                                            location.reload(true);
                                        },
                                        error: function (data) {
                                            var err = JSON.parse(data.responseText);
                                            $('cancelPolicyAlertBody').text(err, message);
                                            $('cancelPolicyAlert').alert();
                                            $('#loader').css("display","none");
                                        }
                                    });
                                } else {
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
                                    // $('#modal-customer_OTP').empty();
                                    // $('#modal-customer_OTP').append(append2);
                                    // $('#customer_OTP').modal('show');
                                    // $('#customer_OTP_already').modal('hide');
                                    // $("#loader").hide();
                                    $('#canclePolicy').hide();
                                    $('#otpModal').hide();
                                    location.reload(true);
                                }
                            },
                            error: function (data) {
                                $("#loader").hide();
                                // console.log(data);
                                $('#loader').css("display", "none");
                                $('.otpInput').val('');

                                if (data.status == 401)
                                    $('.wrongOTP').slideDown();

                                // if (data.status == 402)
                                //     $('.noPaymentRef').slideDown();
                            },
                            complete: function () {}
                        });
                    }, 200);
                } else {
                    $('#otpMsg').addClass('text-danger');
                    $('#otpMsg').text('Please provide otp.');
                }
            });
        });
        </script>
    @endpush
</x-app-layout>
