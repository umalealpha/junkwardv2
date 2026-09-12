<div>
    <div class="">
       <input type="hidden" name="_token" value="{{ csrf_token() }}" />
       <input type="hidden" name="quoteCode" value="{{ $premiumCalcDetails->quoteNumber ?? '' }}" />

       <div class="">
          <div class="">
            <div id="loader" class="form-group loader" style="display:none">
                <div class="ml-4"><img src="{{ asset('img/loading.gif') }}" class="img-responsive" width=40 height=40></div>
            </div>

            <h3 class="">Customer Details
                @isset($quote_id->quoteCode)
                <span style="float: right">
                <a href="{{ route('quote.download',[$quote_id->quoteCode,'100']) }}" class="btn btn-sm btn-elevate btn-brand btn-elevate" id="" data-toggle="kt-tooltip" title="" data-placement="left" data-original-title="Download"> <span class="kt-opacity-11" id="">Download</span>&nbsp; <i class="flaticon-download-1 kt-padding-l-5 kt-padding-r-0"></i> </a>
                </span>
                @endisset
            </h3>
          </div>

          <form id="updateCustomerDetails" action="" method="POST" enctype="multipart/form-data"
             class="kt-form">
             <table class="table table-striped table-bordered table-hover table-checkable"
                id="policy_table">
                <thead>
                   {{-- {{ dd($this) }} --}}
                   <tr>
                      <th>Name:</th>
                      <td width="50%">
                         {{ ucwords(strtolower($policy->customer->firstName . ' ' . $policy->customer->middleName . ' ' . $policy->customer->lastName)) }}
                      </td>
                   </tr>
                   <tr>
                      <th>Omang Number:</th>
                      @if ($policy->customer->profile->omang)
                      <td width="50%">{{ $policy->customer->profile->omang }}</td>
                      @else
                      <td width="50%">N/A</td>
                      @endif
                   </tr>
                   <tr>
                      <th>Passport Number:</th>
                      @if ($policy->customer->profile->passport)
                      <td width="50%">{{$policy->customer->profile->passport }}</td>
                      @else
                      <td width="50%">N/A</td>
                      @endif
                   </tr>
                   <tr>
                      <th>Email:</th>
                      @if ($policy->customer->email)
                      <td width="50%">{{ $policy->customer->email }}</td>
                      @else
                      <td width="50%">N/A</td>
                      @endif
                   </tr>
                   <tr>
                      <th>Cellphone:</th>
                      @if ($policy->customer->cellphone)
                      <td width="50%">{{ $policy->customer->cellphone }}</td>
                      @else
                      <td width="50%">N/A</td>
                      @endif
                   </tr>
                   <tr>
                      <th>Gender:</th>
                      <td width="50%">
                         <select class="form-control" required name="gender" id="gender">
                            <option value="">Please Select</option>
                            <option value="0" @if ($policy->customer->profile->gender == 0) selected @endif>Female</option>
                            <option value="1" @if ($policy->customer->profile->gender == 1) selected @endif>Male</option>
                         </select>
                      </td>
                   </tr>
                   <tr>
                      <th>Date Of Birth:</th>
                      @if ($policy->customer->profile->dob)

                      {{-- <div class="form-floating mb-3">
                        <x-form-input type="date" wire:model.defer="customer_profile.dob" placeholder="Date Of Birth" disabled="{{ !$this->editable }}" />
                        <x-form-label for="dob" required value="{{ __('Date Of Birth') }}"/>
                        <x-form-input-error name="customer_profile.dob"/>
                      </div> --}}

                      <td width="50%">
                         <input type="text" required class="form-control kt_datepicker_1 dob"
                            name="dob" id="rerate_dob" autocomplete="off" data-date-end-date="-18y"
                            value="{{ $policy->customer->profile->dob }}" placeholder="Select date" />
                      </td>
                      @else
                      <td width="50%">N/A</td>
                      @endif
                   </tr>
                   <tr>
                      <th>Marital Status:</th>
                      <td width="50%">
                         <select class="form-control" required name="marital" id="marital_status">
                            <option value="">Please Select</option>
                            <option value="1" @if ($policy->customer->profile->maritalstatus == 1) selected @endif>Single</option>
                            <option value="2" @if ($policy->customer->profile->maritalstatus == 2) selected @endif>Married</option>
                            <option value="3" @if ($policy->customer->profile->maritalstatus == 3) selected @endif>Divorced</option>
                            <option value="4" @if ($policy->customer->profile->maritalstatus == 4) selected @endif>Widowed</option>
                            <option value="5" @if ($policy->customer->profile->maritalstatus == 5) selected @endif>Living Together</option>
                            <option value="6" @if ($policy->customer->profile->maritalstatus == 6) selected @endif>Living Separately</option>
                         </select>
                      </td>
                   </tr>
                   <tr>
                      <th>Store Name:</th>
                      <td width="50%">{{ $policy->store?->name ?? '-' }}</td>
                   </tr>
                </thead>
             </table>
          </form>
          <!--end: Datatable -->
       </div>
       <hr>
       <div class="kt-portlet__body">
          <div class="kt-portlet__head-label">
             <h3 class="kt-portlet__head-title">Product Details</h3>
          </div>
          <table class="table table-striped table-bordered table-hover table-checkable" id="policy_table2">
             <thead>
                <tr>
                   <th>Product:</th>
                   <td width="50%">
                      @if ($policy->product->name != null)
                      {{ $policy->product->name }}
                      @else
                      NA
                      @endif
                   </td>
                </tr>
                <tr>
                   <th>Product Plan:</th>
                   <td width="50%">
                      @if ($policy->plan && $policy->plan->name != null)
                      {{ $policy->plan->name }}
                      @else
                      NA
                      @endif
                   </td>
                </tr>
                <tr>
                   <th>Sum Insured:</th>
                   <td width="50%">P {{ $policy->sum_assured }}</td>
                </tr>
             </thead>
          </table>
          <!--end: Datatable -->
       </div>
       <hr>
       <div class="kt-portlet__body">
          <div class="kt-portlet__head-label">
             <h3 class="kt-portlet__head-title">Vehicle Details</h3>
          </div>
          <form id="updateVehicleDetails" action="" method="POST" enctype="multipart/form-data"
             class="kt-form">
             <table class="table table-striped table-bordered table-hover table-checkable"
                id="policy_table3">
                <thead>
                   <tr>
                      <th>Japenese Import:</th>
                      <td width="50%">
                         <select class="form-control kt_selectpicker select" required name="is_imported"
                            title="Please select type" data-live-search="true" id="is_imported">
                         <option value="Yes" @if ($premiumCalcDetails->is_imported == 'Yes') selected @endif>Yes</option>
                         <option value="No" @if ($premiumCalcDetails->is_imported == 'No') selected @endif>No</option>
                         </select>
                      </td>
                   </tr>
                   <tr>
                      <th>Make:</th>
                      <td width="50%">
                         <select class="form-control kt_selectpicker" required name="make"
                            title="Please select type" data-live-search="true" id="make_rerate">
                         @foreach ($dataMake as $key => $make)
                         @if ($premiumCalcDetails->is_imported == 'Yes')
                         <option value="{{ $make->s_Make }}" @if ($premiumCalcDetails->make == $make->s_Make) selected @endif>
                         {{ ucwords(strtoupper($make->s_Make)) }}</option>
                         @endif
                         @if ($premiumCalcDetails->is_imported == 'No')
                         <option value="{{ $make }}" @if ($premiumCalcDetails->make == $make) selected @endif>
                         {{ ucwords(strtoupper($make)) }}</option>
                         @endif
                         @endforeach
                         </select>
                      </td>
                   </tr>
                   <tr>
                      <th>Manufacturing Year:</th>
                      {{-- {{ dd($policy->PolicyVehicle) }} --}}
                      <td width="50%">
                         <select class="form-control kt_selectpicker" required name="year"
                            title="Please select manufacturing year" data-live-search="true"
                            id="year_rerate">
                         @foreach ($years as $key => $year)
                         <option value="{{ $year }}" @if ($policy->vehicle?->year == $year) selected @endif>
                         {{ $year }}</option>
                         @endforeach
                         </select>
                      </td>
                   </tr>
                   <tr>
                      <th>Model:</th>
                      <td width="50%" height="72px">
                         <select class="form-control kt_selectpicker" required name="model"
                            title="Please select vehicle model" data-live-search="true"
                            id="model_rerate">
                         @if ($premiumCalcDetails->is_imported == 'Yes')
                         @foreach ($dataModel as $key => $model)
                         <option value="{{ $model['model'] }}" @if ($premiumCalcDetails->model == $model['model']) selected @endif>
                         {{ ucwords(strtoupper($model['model'])) }}</option>
                         @endforeach
                         @endif
                         @if (is_array($dataModel) && $premiumCalcDetails->is_imported == 'No')
                         @foreach ($dataModel as $key => $model)
                         <option value="{{ $model['Model'] }}" @if ($premiumCalcDetails->model == $model['Model']) selected @endif>
                         {{ ucwords(strtoupper($model['Model'])) }}</option>
                         @endforeach
                         @endif
                         </select>
                      </td>
                   </tr>
                   <tr>
                      <th>Estimated value of vehicle:</th>
                      <td width="50%">
                         {{-- <span class="spinner-border spinner-border-sm" id="valueLoader"></span> --}}
                         <input type="number" class="form-control" required min="20000" max="500000"
                            name="estimatedValue" id="estimatedValue"
                            value="{{ $policy->sum_assured }}"
                            placeholder="Enter estimated value" />
                      </td>
                   </tr>
                   <tr>
                      <th>Number of prior accidents:</th>
                      <td width="50%">
                         <input type="number" class="form-control" required min="0" max="3" name="prior_accidents"
                            id="prior_accidents" title="Enter number of prior accidents"
                            value="{{ $premiumCalcDetails->priorAccidents }}"
                            placeholder="Enter number of prior accidents" />
                      </td>
                   </tr>
                   <tr>
                      <th>Condition:</th>
                      <td width="50%">Excellent</td>
                   </tr>
                   <tr>
                      <th>Mileage:</th>
                      <td width="50%">Low</td>
                   </tr>
                   <tr>
                      <th>Purpose:</th>
                      <td width="50%">Personal</td>
                   </tr>
                </thead>
             </table>
          </form>
          <!--end: Datatable -->
       </div>
       <hr>
        <div class="kt-portlet__body">
          <h3 class="kt-portlet__head-title" style="display:inline">Premium Calculation Details</h3>
          @can('quotes-Update Premium')
          <div class="kt-portlet__head-label">
             <a href="{{ route('admin.policy.premiumUpdateHistory', $policy->policyNumber) }}"
                target="_blank" style="margin-left:1%;display:inline;float:right;"
                class="btn btn-sm btn-elevate btn-brand btn-elevate" id="" data-toggle="kt-tooltip"
                title="" data-placement="left" data-original-title="Edit & Update History"> <span
                class="btn btn-info kt-opacity-11" id="">Premium Update History</span>&nbsp; <i
                class="flaticon-edit kt-padding-l-5 kt-padding-r-0"></i>
             </a>
          </div>
          @endcan
          <div style="margin-bottom:2%" id="rerate_div_premium">
             <form id="updateCustomerPremium"  action="{{ route('quote.updatePremium', $policy->policyNumber) }}" method="POST" enctype="multipart/form-data" class="kt-form">
                <!-- CSRF Token -->
                <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                <table class="table table-striped table-bordered table-hover table-checkable">
                   <thead>
                      <tr>
                         <th>Please select type:</th>
                         <td width="50%">
                            <select class="form-control kt_selectpicker" name="type"
                               title="Please select type" data-live-search="true" id="type">
                               <option value="discount">Discount</option>
                               <option value="surcharge">Surcharge</option>
                            </select>
                            <span id="discountError" class="error"
                               style="display: none; font-size: 12px; color:red;">This field is
                            required.</span>
                         </td>
                      </tr>
                      <tr>
                         <th>Please select value type:</th>
                         <td width="50%">
                            <select class="form-control kt_selectpicker" name="value_type"
                               title="Please select value type" data-live-search="true"
                               id="value_type">
                               <option value="1">Flat value</option>
                               <option value="2">Percent(%) value</option>
                            </select>
                            <span id="valueTypError" class="error"
                               style="display: none; font-size: 12px; color:red;">This field is
                            required.</span>
                         </td>
                      </tr>
                      <tr>
                         <th>Value:</th>
                         <td width="50%">
                            <input type="number" class="form-control" name="value"
                               title="Enter the value" placeholder="Enter value" id="value" />
                            <span id="valueError" class="error"
                               style="display: none; font-size: 12px; color:red;">This field is
                            required.</span>
                         </td>
                      </tr>
                      <tr>
                         <th>Reason:</th>
                         <td width="50%">
                            <input type="text" class="form-control" name="reason"
                               title="Please provide the reason" placeholder="Please provide reason"
                               id="reason" />
                            <span id="reasonError" class="error"
                               style="display: none; font-size: 12px; color:red;">This field is
                            required.</span>
                         </td>
                      </tr>
                   </thead>
                </table>
                <div class="kt-form__actions">
                   <div class="row">
                      <div class="col-5"></div>
                      <div class="col-7">
                         <button type="button" class="btn btn-info"
                            id="submitEditPremium">Submit</button>
                         <p class="btn btn-secondary" id="rerate_hide" style="margin-top:2%">Cancel</p>
                      </div>
                   </div>
                </div>
             </form>
          </div>
          <hr>
          <table class="table table-striped table-bordered table-hover table-checkable">
             <thead>
                <tr>
                   <th>Ratings Calculation Log ID:</th>
                   <td width="50%">
                      @if (isset($premiumCalcDetails->ratings_id))
                      {{ $premiumCalcDetails->ratings_id }}
                      @else
                      N/A
                      @endif
                   </td>
                </tr>
                <tr>
                   <th>Monthly:</th>
                   <td width="50%">P {{ $premiumCalcDetails->premiumMonthly }}</td>
                </tr>
                <tr>
                   <th>3 Installments:</th>
                   <td width="50%">P {{ $premiumCalcDetails->premium3Inst }}</td>
                </tr>
                <tr>
                   <th>Annual:</th>
                   <td width="50%">P {{ $premiumCalcDetails->premiumAnnually }}
                      <input type="hidden" id="annual_premium1"
                         value="{{ $premiumCalcDetails->premiumAnnually }}" />
                      <input type="hidden" id="annual_premium2" name="annual_premium" value="" />
                   </td>
                </tr>
                <tr>
                   <th>Discount/Surcharge:</th>
                   @if ($premiumCalcDetails->discount_surcharge != null && $premiumCalcDetails->discount_surcharge != 0)
                   <td width="50%">P
                      {{ $premiumCalcDetails->discount_surcharge . ' (' . $premiumCalcDetails->percent_discount_surcharge . '%)' }}
                   </td>
                   @else
                   <td width="50%">-</td>
                   @endif
                </tr>
                <tr>
                   <th>Ratings Premium Rate:</th>
                   @if ($premiumCalcDetails->premium_rate > 2 && $premiumCalcDetails->premium_rate < 100)
                   <td width="50%" style="color: green">
                      {{ number_format((float) $premiumCalcDetails->premium_rate, 2, '.', '') }}%
                   </td>
                   @else
                   <td width="50%" style="color: red">
                      {{ number_format((float) $premiumCalcDetails->premium_rate, 2, '.', '') }}%
                   </td>
                   @endif
                </tr>
                <tr>
                   <th>Premium Rate after discount/surcharge:</th>
                   @if ($premiumCalcDetails->ratio != 0)
                   @if ($premiumCalcDetails->ratio > 2 && $premiumCalcDetails->ratio < 100)
                   <td width="50%" style="color: green">
                      {{ number_format((float) $premiumCalcDetails->ratio, 2, '.', '') }}%
                   </td>
                   @else
                   <td width="50%" style="color: red">
                      {{ number_format((float) $premiumCalcDetails->ratio, 2, '.', '') }}%
                   </td>
                   @endif
                   @else
                   <td width="50%">-</td>
                   @endif
                </tr>
                <tr>
                   <th>Reason:</th>
                   @if ($reratedPremiumQuotes)
                   @if ($reratedPremiumQuotes->reason != null)
                   <td width="50%">{{ $reratedPremiumQuotes->reason }}</td>
                   @else
                   <td width="50%">-</td>
                   @endif
                   @else
                   <td width="50%">-</td>
                   @endif
                </tr>
             </thead>
          </table>
          <span id="reratePremiumError" style="color: red; display:none; font-size: medium; font-weight: 600;">Please fill all required details.</span>
          <div class="kt-portlet__foot kt-portlet__foot--solid" id="page_footer">
             <div class="kt-form__actions">
                <div class="row">
                   <div class="col-5"></div>
                   <div class="col-7">
                      <button type="submit" class="btn btn-info"
                         id="submit_rerate_button">Update</button>
                      @if($policy->product->id == 3 && isset($is_renewal->is_renewed)  && $is_renewal->is_renewed== 0)
                      <a href="{{ route('admin.policy.renew', $policy->id) }}" class="btn btn-success">Renew</a>
                      @endif
                      <a class="btn btn-secondary"
                         href="{{ route('policy') }}">Cancel</a>
                   </div>
                </div>
             </div>
          </div>
       </div>
       <hr>
       <div class="kt-portlet__body">
          <div style="margin-bottom:2%; display: none;" id="new_rate_div">
             <div class="kt-portlet__head-label">
                <h3 class="kt-portlet__head-title">New Premium Calculation Details</h3>
             </div>
             <table class="table table-striped table-bordered table-hover table-checkable" id="policy_table4">
                <thead>
                   <tr>
                      <th>Ratings Calculation Log ID:</th>
                      <td width="50%" id="rate_id"></td>
                      <input type="hidden" id="rate_id_enc" value="" />
                   </tr>
                   <tr>
                      <th>Monthly:</th>
                      <td width="50%" id="monthly_ins"></td>
                   </tr>
                   <tr>
                      <th>3 Installments:</th>
                      <td width="50%" id="three_ins"></td>
                   </tr>
                   <tr>
                      <th>Annual:</th>
                      <td width="50%" id="annual_ins"></td>
                   </tr>
                   <tr>
                      <th>Ratings Premium Rate:</th>
                      <td width="50%" id="premium_rate"></td>
                   </tr>
                   <tr>
                      <th>Please select frequency:</th>
                      <td width="50%">
                         <select class="form-control kt_selectpicker" name="new_frequency"
                            title="Please select frequency" data-live-search="true"
                            id="new_frequency">
                            <option value="1">Monthly Installments</option>
                            <option value="2">Three Installmentsin a year</option>
                            <option value="3">Annual Installments</option>
                         </select>
                         <span id="newFrequencyError" class="error"
                            style="display: none; font-size: 12px; color:red;">This field is
                         required.</span>
                      </td>
                   </tr>
                </thead>
             </table>

            <div class="form-group row" id="add_dis_sur_rate">
                  <!-- <div class="col-6">
                        <select class="form-select mb-2" id="discountSurcharge_value" aria-label="Select option">
                            <option value="">- Select -</option>
                            <option value="Yes" >Yes</option>
                            <option value="No" >No</option>
                        </select>
                        <h4 id="MsgDiscountSurcharge"
                        style="display:none;float:left;margin-top: 5px;margin-left: 5px;color:#ff4d4d;">
                        No</h4>
                  </div> -->
                  <div class="col-3">
                     <h5>Add Discount / Surcharge</h5>
                  </div>
                  <div class="col-3">
                  </div>
                  <div class="col-3">
                     <span class="form-check form-switch form-check-custom form-check-solid">
                           <label>
                              <input class="form-check-input" id="discountSurchargeValue" type="checkbox" name="addDiscSurc" value="1">
                              <span style="margin-top: 5px;margin-left: 10px;"></span>
                              <h4 id="MsgDiscountSurcharge"
                                 style="display:inline;float:left;margin-top: 5px;margin-left: 5px;color:#ff4d4d;">
                                 No</h4>
                           </label>
                     </span>
                  </div>
            </div>

             {{-- Apply Custom Rate --}}

             <div class="form-group row" id="add_custom_rate">
               <div class="col-3">
                  <h5>Apply Custom Rate</h5>
               </div>
               <div class="col-3">
               </div>
               <div class="col-3">
                     <span class="form-check form-switch form-check-custom form-check-solid">
                           <label>
                              <input class="form-check-input" id="customDiscountSurchargeValue" type="checkbox" name="customAddDiscSurc" value="1">
                                 <span style="margin-top: 5px;margin-left: 10px;"></span>
                                 <h4 id="MsgCustomDiscountSurcharge"
                                    style="display:inline;float:left;margin-top: 5px;margin-left: 5px;color:#ff4d4d;">
                                    No</h4>
                           </label>
                     </span>
               </div>
             </div>

             <table class="table table-striped table-bordered table-hover table-checkable"
                id="discount_surcharge_div" style="display: none;">
                <thead>
                   <tr>
                      <th>Please select type:</th>
                      <td width="50%">
                         <select class="form-control kt_selectpicker" name="type"
                            title="Please select type" data-live-search="true" id="dis_sur_type">
                            <option value="discount">Discount</option>
                            <option value="surcharge">Surcharge</option>
                         </select>
                         <span id="dis_sur_typeError" class="error"
                            style="display: none; font-size: 12px; color:red;">This field is
                         required.</span>
                      </td>
                   </tr>
                   <tr>
                      <th>Please select value type:</th>
                      <td width="50%">
                         <select class="form-control kt_selectpicker" name="value_type"
                            title="Please select value type" data-live-search="true"
                            id="dis_sur_value_type">
                            <option value="1">Flat value</option>
                            <option value="2">Percent(%) value</option>
                         </select>
                         <span id="dis_sur_value_typeError" class="error"
                            style="display: none; font-size: 12px; color:red;">This field is
                         required.</span>
                      </td>
                   </tr>
                   <tr>
                      <th>Value:</th>
                      <td width="50%">
                         <input type="text" class="form-control" name="value" id="dis_sur_value"
                            title="Enter the value" placeholder="Enter value" />
                         <span id="dis_sur_valueError" class="error"
                            style="display: none; font-size: 12px; color:red;">This field is
                         required.</span>
                      </td>
                   </tr>
                   <tr>
                      <th>Reason:</th>
                      <td width="50%">
                         <input type="text" class="form-control" name="reason" id="dis_sur_reason"
                            title="Please provide the reason" placeholder="Please provide reason" />
                         <span id="dis_sur_reasonError" class="error"
                            style="display: none; font-size: 12px; color:red;">This field is
                         required.</span>
                      </td>
                   </tr>
                   <tr>
                      <th></th>
                      <td>
                         <div class="kt-form__actions">
                            <div class="row">
                               <div class="col-5"></div>
                               <div class="col-7">
                                  <button type="button" class="btn btn-info"
                                     id="addDiscSurcRerate">Proceed</button>
                                  <p class="btn btn-secondary" id="cancel_rerate_button"
                                     style="">Close</p>
                               </div>
                            </div>
                         </div>
                      </td>
                   </tr>
                </thead>
             </table>
             <table class="table table-striped table-bordered table-hover table-checkable"
                id="custom_discount_surcharge_div" style="display: none;">
                <thead>
                   <tr>
                      <th>Rate Value(%):</th>
                      <td width="50%">
                         <input type="text" class="form-control" name="value" id="cus_dis_sur_value"
                            title="Enter the value" placeholder="Enter value" />
                         <span id="cus_dis_sur_valueError" class="error"
                            style="display: none; font-size: 12px; color:red;">This field is
                         required.</span>
                      </td>
                   </tr>
                   <tr>
                      <th>Reason:</th>
                      <td width="50%">
                         <input type="text" class="form-control" name="reason" id="cus_dis_sur_reason"
                            title="Please provide the reason" placeholder="Please provide reason" />
                         <span id="cus_dis_sur_reasonError" class="error"
                            style="display: none; font-size: 12px; color:red;">This field is
                         required.</span>
                      </td>
                   </tr>
                   <tr>
                      <th></th>
                      <td>
                         <div class="kt-form__actions">
                            <div class="row">
                               <div class="col-5"></div>
                               <div class="col-7">
                                  <button type="button" class="btn btn-info"
                                     id="customAddDiscSurcRerate">Proceed</button>
                                  <p class="btn btn-secondary" id="cancel_custom_rerate_button"
                                     style="margin-top:2%">Close</p>
                               </div>
                            </div>
                         </div>
                      </td>
                   </tr>
                </thead>
             </table>
             <div class="form-group row" id="dis_sur_added_div" style="display: none;">
                <h3>Added Discount/Surcharge:</h3>
                <table class="table table-striped table-bordered table-hover table-checkable" id="addedDiscSurPrem_table">
                   <thead>
                      <tr>
                         <th>Monthly:</th>
                         <td width="50%" id="dis_sur_monthly_ins"></td>
                      </tr>
                      <tr>
                         <th>3 Installments:</th>
                         <td width="50%" id="dis_sur_three_ins"></td>
                      </tr>
                      <tr>
                         <th>Annual:</th>
                         <td width="50%" id="dis_sur_annual_ins"></td>
                      </tr>
                   </thead>
                </table>
             </div>
             <div>
                <h5 id="rerate_disSur_error_div" style="color: red; display: none;"></h5>
             </div>
             <form id="newPremiumRate" action="{{ route('admin.policy.acceptReratedPremium', $policy->policyNumber) }}"
                method="POST" enctype="multipart/form-data" class="kt-form">
                <!-- CSRF Token -->
                <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                <input type="hidden" name="rateID" id="rateID" />
                <input type="hidden" name="annual_premium_rerate" id="annual_premium_rerate" value=""/>
                <input type="hidden" name="dis_sur_annual_premium" id="dis_sur_annual_premium" value=""/>
                <input type="hidden" name="frequency" id="dis_sur_new_frequency" value=""/>
                <input type="hidden" name="trans_type" id="trans_type" value="rerate"/>
                <input type="hidden" name="generatePaymentUrl" value="" id="generatePaymentUrlId" />

               <div class="form-group row" id="realpaySwitchDiv">
                  <div class="col-3">
                     <h5>Add payment on realpay</h5>
                  </div>
                  <div class="col-3">
                  </div>
                  <div class="col-3">
                        <span class="form-check form-switch form-check-custom form-check-solid">
                              <label>
                                 <input class="form-check-input" id="paymentValue" type="checkbox" name="addPayment" value="1">
                                    <span style="margin-top: 5px;margin-left: 10px;"></span>
                                    <h4 id="paymentMsg"
                                        style="display:inline;float:left;margin-top: 5px;margin-left: 5px;color:#ff4d4d;">
                                        No</h4>
                              </label>
                        </span>
                  </div>
               </div>

                <div style="margin-bottom:2%;display: none;" id="rerate_new_div">
                   <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                   <input type="hidden" name="policyNumber" value="{{ $policy->policyNumber }}" />
                   <input type="hidden" name="policyID" value="{{ $policy->id }}" />
                   <input type="hidden" name="paymentMethod" value="Realpay" />
                   <div class="kt-portlet__body" id="paymentInfoDivRerate">
                      <div class="form-group row">
                         <label class="col-3 col-form-label">Instalment Start Date :</label>
                         <div class="col-9">
                            <input type="text" class="form-control kt_datepicker_1 validateGroup1"
                               name="billingDay" autocomplete="off"
                               placeholder="Select payment start date" title="Please select instalment start date"/>
                            <span class="form-text text-muted"></span>
                         </div>
                      </div>
                      <div class="form-group row" id="first_collection_date_rerate_div">
                         <label  class="col-3 col-form-label">First Collection Date :</label>
                         <div class="col-9">
                            <input type="text" required class="form-control kt_datepicker_1 dob" name="first_collection_date" id="first_collection_date_rerate" autocomplete="off"  placeholder="Select date" title="Please select first collection date"/>
                            <span style="color: red;">Note: Please note that First Collection Date should be always less than Instalment Start Date.</span>
                            <span class="form-text text-muted"></span>
                         </div>
                      </div>
                      <div class="form-group row" id="first_premium_row_rerate_div">
                         <label  class="col-3 col-form-label">First Instalment Amount :</label>
                         <div class="col-9">
                            <input  class="form-control" name="first_premium" id="first_premium_rerate" title="Please provide premium" placeholder="Please provide premium">
                            <span class="labelled" id="paymentNote" style="color: red;"></span>
                            <span class="form-text text-muted"></span>
                         </div>
                      </div>
                      <div class="form-group row" id="first_premium_row">
                         <label  class="col-3 col-form-label">Premium :</label>
                         <div class="col-9">
                            <input  class="form-control" name="rerate_premium" id="rerate_premium" title="Please provide premium" placeholder="Please provide premium">
                            <span class="labelled" id="paymentNote" style="color: red;"></span>
                            <span class="form-text text-muted"></span>
                         </div>
                      </div>
                      <div class="form-group row">
                         <label class="col-3 col-form-label">Please select bank :</label>
                         <div class="col-9">
                            {{--
                            <select id="RPBanksRerate" class="form-control kt_selectpicker"
                               title="Please select bank" name="BankName">
                               @if (isset($banks['names']) && count($banks) > 0)
                               @foreach ($banks['names'] as $bank)
                               <option value="{{ $bank->bank_number }}">{{ $bank->bank_name }}</option>
                               @endforeach
                               @else
                               @foreach($banks as $bank)
                               <option value="{{ $bank->bank_number }}">{{ $bank->bank_name }}</option>
                               @endforeach
                               @endif
                            </select>
                            --}}
                            <span class="form-text text-muted"></span>
                         </div>
                      </div>
                      <div class="form-group row">
                         <label class="col-3 col-form-label">Please select bank branch :</label>
                         <div class="col-9">
                            <select id="RPBankBranchRerate" class="form-control kt_selectpicker"
                               title="Please select bank branch" name="BranchCode">
                            </select>
                            <span class="form-text text-muted"></span>
                         </div>
                      </div>
                      <input type="hidden" value="{{ $policy->customer->cellphone }}" name="billingCell" />
                      <div class="form-group row">
                         <label class="col-3 col-form-label">Account Type :</label>
                         <div class="col-9">
                            <select class="form-control kt_selectpicker"
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
                            <input class="form-control validateGroup1" name="accountNumber" value=""
                               title="Please provide account number"
                               placeholder="Please provide account number">
                            <span class="form-text text-muted"></span>
                         </div>
                      </div>
                   </div>
                </div>

               <div class="form-group row" id="dpoSwitchDiv">
                  <div class="col-3">
                     <h5> Add payment on DPO</h5>
                  </div>
                  <div class="col-3">
                  </div>
                  <div class="col-3">
                        <span class="form-check form-switch form-check-custom form-check-solid">
                              <label>
                                 <input class="form-check-input" id="paymentValueDpo" type="checkbox" name="addPaymentDpo" value="1">
                                    <span style="margin-top: 5px;margin-left: 10px;"></span>
                                    <h4 id="paymentMsgDpo"
                                        style="display:inline;float:left;margin-top: 5px;margin-left: 5px;color:#ff4d4d;">
                                        No</h4>
                              </label>
                        </span>
                  </div>
               </div>

                <div style="margin-bottom:2%;display: none;" id="rerate_new_div_dpo">
                   <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                   <input type="hidden" name="policyNumber" value="{{ $policy->policyNumber }}" />
                   <input type="hidden" name="policyID" value="{{ $policy->id }}" />
                   <input type="hidden" name="paymentMethod" value="Dpo" />
                   <div class="kt-portlet__body" id="paymentInfoDivRerate">
                      <div class="form-group row">
                         <label class="col-3 col-form-label">Installment Start Date :</label>
                         <div class="col-9">
                            <input type="text" class="form-control kt_datepicker_1 validateGroup1"
                               name="billingDayDpo" autocomplete="off"
                               placeholder="Select payment start date" title="Please select instalment start date" required>
                            <span class="form-text text-muted"></span>
                         </div>
                      </div>
                      <div class="form-group row" id="first_collection_date_rerate_div">
                         <label  class="col-3 col-form-label">First Collection Date :</label>
                         <div class="col-9">
                            <input type="text" required class="form-control kt_datepicker_1 dob" name="first_collection_dateDpo" id="first_collection_date_rerate" autocomplete="off"  placeholder="Select date" title="Please select first collection date"/>
                            <span style="color: red;">Note: Please note that First Collection Date should be always less than Instalment Start Date.</span>
                            <span class="form-text text-muted"></span>
                         </div>
                      </div>
                      <div class="form-group row" id="first_premium_row_rerate_div">
                         <label  class="col-3 col-form-label">First Installment Amount :</label>
                         <div class="col-9">
                            <input  class="form-control" name="first_premiumDpo" id="first_premium_rerate" title="Please provide premium" placeholder="Please provide first premium" required>
                            <span class="labelled" id="paymentNote" style="color: red;"></span>
                            <span class="form-text text-muted"></span>
                         </div>
                      </div>
                      <div class="form-group row" id="first_premium_row">
                         <label  class="col-3 col-form-label">Premium :</label>
                         <div class="col-9">
                            <input  class="form-control" name="rerate_premiumDpo" id="rerate_premium" title="Please provide premium" placeholder="Please provide premium" required>
                            <span class="labelled" id="paymentNote" style="color: red;"></span>
                            <span class="form-text text-muted"></span>
                         </div>
                      </div>
                      <input type="hidden" value="{{ $policy->customer->cellphone }}" name="billingCell" />
                   </div>
             </div>
             <div class="form-group row" id="cashSwitchDiv">
                  <div class="col-3">
                     <h5> Pay with Cash</h5>
                  </div>
                  <div class="col-3">
                  </div>
                  <div class="col-3">
                        <span class="form-check form-switch form-check-custom form-check-solid">
                              <label>
                                 <input class="form-check-input" id="paymentValueCash" type="checkbox" name="addPaymentCash" value="1">
                                    <span style="margin-top: 5px;margin-left: 10px;"></span>
                                    <h4 id="paymentMsgCash"
                                       style="display:inline;float:left;margin-top: 5px;margin-left: 5px;color:#ff4d4d;">
                                       No
                                    </h4>
                              </label>
                        </span>
                  </div>
                <!-- <div class="col-3">
                   <span class="kt-switch">
                      <label>
                         <input id="paymentValueCash" type="checkbox" name="addPaymentCash" value="1"
                            onchange="addPaymentButtonCash()" title="Please select payment">
                         <span style="margin-top: 5px;margin-left: 10px;"></span>
                         <h4 id="paymentMsgCash"
                            style="display:inline;float:left;margin-top: 5px;margin-left: 5px;color:#ff4d4d;">
                            No
                         </h4>
                      </label>
                   </span>
                </div> -->
             </div>
             <div class="form-group row" id="payWithCashAlreadyDone" style="display: none;">
                <div class="col-3">
                   <h5>Payment is already logged in system</h5>
                </div>
                <div class="col-3">
                   <span class="kt-switch">
                      <label>
                         <input id="cashPaymentDone" type="checkbox" name="cashPaymentDone" value="1"
                           title="Please select payment">
                         <span style="margin-top: 5px;margin-left: 10px;"></span>
                         <h4 id="paymentMsgCashDone"
                            style="display:inline;float:left;margin-top: 5px;margin-left: 5px;color:#ff4d4d;">
                            No
                         </h4>
                      </label>
                   </span>
                </div>
             </div>
             <div class="form-group row" id="addPaymentButtonCashAlreadyDoneDiv" style="display: none;">
                <div class="kt-portlet__body">
                   <div class="form-group row">
                      <label class="col-3 col-form-label">Please select Payment :</label>
                      <div class="col-9">
                         <select id="selectPaymentData" class="form-control kt_selectpicker"
                            title="Please select payment" name="selectPaymentData">
                            @if (isset($paymentDetails) && count($paymentDetails) > 0)
                            @foreach ($paymentDetails as $payment)
                            <option value="{{ $payment->id }}">{{ $payment->referenceNumber.'/'.$payment->amount.'/'.$payment->paymentDate }}</option>
                            @endforeach
                            @endif
                         </select>
                         <span class="form-text text-muted"></span>
                      </div>
                   </div>
                </div>
             </div>
             <div id="payWithCashForm" style="display: none;">
                <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                <input type="hidden" name="policyNumber" value="{{ $policy->policyNumber }}" />
                <input type="hidden" name="policy_id" value="{{ $policy->id }}" />
                <input type="hidden" name="paymentMethod" value="Cash" />
                <div class="kt-portlet__body">
                   @if(\Illuminate\Support\Facades\Auth::user()->hasPermissionTo('back-dated-transactions'))
                   <div class="form-group row">
                      <label class="col-3 col-form-label">Date of payment :</label>
                      <div class="col-9">
                         <input type="text" class="form-control kt_datepicker_1 required validateGroup1"
                            name="paymentDate" autocomplete="off" placeholder="Select date of payment" title="Please select date of payment" />
                         <span class="form-text text-muted"></span>
                      </div>
                   </div>
                   @else
                   <p>Note : You can select Date of Payment for current month.</p>
                   <div class="form-group row">
                      <label class="col-3 col-form-label">Date of payment :</label>
                      <div class="col-9">
                         <input type="text" class="form-control required validateGroup1"
                            id="date_of_refund_for_current_month" name="paymentDate" autocomplete="off" placeholder="Select date of payment" title="Please select date of payment"/>
                         <span class="form-text text-muted"></span>
                      </div>
                   </div>
                   @endif
                   <div class="form-group row">
                      <label class="col-3 col-form-label">Payment Amount :</label>
                      <div class="col-9 payment-amount">
                         <input class="form-control required validateGroup1 paymentAmountDiv" id="paymentAmount" name="paymentAmount" value="" title="Please provide amount" placeholder="Please provide amount">
                         <span class="labelled" id="paymentNote" style="color: red;"></span>
                         <span class="form-text text-muted"></span>
                      </div>
                   </div>
                   <div class="form-group row">
                      <label class="col-3 col-form-label">Receipt number:</label>
                      <div class="col-9">
                         <input class="form-control required validateGroup1" name="receiptNumber" value="" title="Please provide payment receipt" placeholder="Please provide payment receipt number">
                         <span class="form-text text-muted"></span>
                      </div>
                   </div>
                   <div class="form-group row">
                      <label class="col-3 col-form-label">Payment Recieved By :</label>
                      <div class="col-9">
                         <input class="form-control required validateGroup1" name="paymentRecievedBy" value="" title="Please provide contract sequence" placeholder="Please provide payment recipient">
                         <span class="form-text text-muted"></span>
                      </div>
                   </div>
                   <div class="form-group row">
                      <label class="col-3 col-form-label">Numbers of Installments paid :</label>
                      <div class="col-9">
                         <input class="form-control required validateGroup1" name="numberOfInstalmentsPaid" value="" title="Please provide the number of Installments paid" placeholder="Please provide the number of Installments paid">
                         <span class="form-text text-muted"></span>
                      </div>
                   </div>
                   <div class="form-group row">
                      <label class="col-3 col-form-label">Note :</label>
                      <div class="col-9">
                         <textarea class="form-control required validateGroup1" name="paymentNote" value="" title="Please provide note" placeholder="Please provide note"></textarea>
                         <span class="form-text text-muted"></span>
                      </div>
                   </div>
                   <div class="form-group row">
                      <label for="example-text-input" class="col-3 col-form-label">Upload Payment Proof</label>
                      <div class="col-2">
                         <div class="kt-avatar" id="product_image" style="float: left; clear: left;">
                            <div class="kt-avatar__holder" style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                            <label class="kt-avatar__upload" data-toggle="kt-tooltip" title="Change Image">
                            <i class="fa fa-pen"></i>
                            <input type='file' name="payment_image" <?php echo config('app.accept_attr'); ?> <?php echo config('app.accept_msg'); ?> />
                            </label>
                            <span class="kt-avatar__cancel" data-toggle="kt-tooltip" title="Cancel Image">
                            <i class="fa fa-times"></i> </span>
                         </div>
                      </div>
                   </div>
                </div>
             </div>
             <br><br>
             <div>
                <h5 id="rerate_payment_note" style="color: red;">Note * : Please select payment method to accept above premium.</h5>
             </div>
             <div class="kt-form__actions" id="acceptPremiumDiv">
                <div class="row">
                   <div class="col-5"></div>
                   <div class="col-7">
                      <button type="submit" class="btn btn-info"
                         id="acceptPremium">Accept</button>
                      <p class="btn btn-secondary" id="cancel_rerate_button"
                         style="">Close</p>
                   </div>
                </div>
             </div>
             </form>
          </div>
       </div>
    </div>
 </div>

<script type="text/javascript">

        var baseURL = '{{ env('GRAPHITE_URL') }}';

        $('#rerate_div_premium').slideUp();

        $('#edit-premium-button').click(function(e) {
            $('#rerate_div_premium').slideDown();
        });

        $('#rerate_hide').click(function(e) {
            $('#rerate_div_premium').slideUp();
        });

        $('#submitEditPremium').click(function(e) {
            var type = $('#type').val();
            if (!type) {
                $('#discountError').css('display', 'block');
            }
            var value_type = $('#value_type').val();
            if (!value_type) {
                $('#valueTypError').css('display', 'block');
            }
            var value = $('#value').val();
            if (!value) {
                $('#valueError').css('display', 'block');
            }
            var reason = $('#reason').val();
            if (!reason) {
                $('#reasonError').css('display', 'block');
            }

            if (type != '' && value_type != '' && value != '' && reason != '') {

                $.ajax({
                    url: '{{ route('quote.updatePremiumAjax', $policy->policyNumber) }}',
                    data: {
                        "_token": "{{ csrf_token() }}",
                        "type": type,
                        "value_type": value_type,
                        "value": value,
                        "reason": reason,
                    },
                    type: 'post',
                    datatype: 'json',
                    beforeSend: function() {
                        $('#loader').css("display", "block");
                    },
                    success: function(data, xhr) {
                        if (data.status == 200) {
                            toastr.success('Success ! ' + data.message);
                            setTimeout(function() {
                                location.reload();
                            }, 3000);
                            $('#loader').css("display", "none");
                        } else {
                            toastr.error('Error ! ' + data.message);
                        }
                    },
                    error: function(error) {
                        $('#loader').css("display", "none");
                        if (error.status == 401) {
                            toastr.error('Error ! ' + error.responseJSON.message);
                        }
                    }
                })
            }

        });

        $("#newPremiumRate").validate({
            ignore: ":not(:visible)",
            rules: {
                reason: {
                    required: true,
                },
                value: {
                    required: true,
                },
                type: {
                    required: true,
                },
                value_type: {
                    required: true,
                },
            },
            messages: {
                reason: {
                    required: "Please select reason",
                },
                value: {
                    required: "Please select value",
                },
                type: {
                    required: "Please select type",
                },
                value_type: {
                    required: "Please select value_type",
                },
            },
            submitHandler: function(form) {
                $("#annual_premium2").val($("#annual_premium1").val());
                return true;
            }
        });

        $("#updateVehicleDetails").validate({
            rules: {
                is_imported: {
                    required: true,
                },
                make: {
                    required: true,
                },
                year: {
                    required: true,
                },
                model: {
                    required: true,
                },
                estimatedValue: {
                    required: true,
                    min: 20000,
                    max: 500000,
                },
                prior_accidents: {
                    required: true,
                    max: 3,
                },
            },
            messages: {
                is_imported: {
                    required: "Please select vehicle is imported or not",
                },
                make: {
                    required: "Please select vehicle make",
                },
                year: {
                    required: "Please select vehicle year",
                },
                model: {
                    required: "Please select vehicle model",
                },
                estimatedValue: {
                    required: "Please enter estimated value",
                    min: "Please enter value greater than 20000",
                    max: "Please eneter value less than 500000",
                },
                prior_accidents: {
                    required: "Please enter prior accidents",
                    max: "Please enter value below 4",
                },
            },
            submitHandler: function(form) {
                $("#annual_premium2").val($("#annual_premium1").val());
                return true;
            }
        });

        $('#acceptPremiumDiv').css('display','none');

        var status = '{{$policy->status}}';
        if (status == 0) {
            $('#acceptPremiumDiv').css('display','block');
            $('#realpaySwitchDiv').css('display','none');
            $('#dpoSwitchDiv').css('display','none');
            $('#cashSwitchDiv').css('display','none');
            $('#rerate_payment_note').css('display','none');
        }

        // Discount Surcharge
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
                var fetchURL = baseURL+'api/policyDiscountSurchargeAPI';
                $.ajax({
                    method: "POST",
                    url: fetchURL,
                    data: {
                        type: $('#dis_sur_type').val(),
                        value_type: $('#dis_sur_value_type').val(),
                        value: $('#dis_sur_value').val(),
                        reason: $('#dis_sur_reason').val(),
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
                        // $("#new_rate_div").slideUp();
                        // $("#page_footer").slideDown();
                    });
            }
        });

        $('#cancel_rerate_button').on('click', function() {
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

        // kt_datepicker_1
        $('.kt_datepicker_1').datepicker({
            rtl: KTUtil.isRTL(),
            todayHighlight: true,
            orientation: "bottom left",
            // templates: arrows,
            format: 'dd-mm-yyyy',
            endDate: "-18y",
        });

        $('#is_imported').on('change', function() {
            $("#make_rerate").html('');
            $("#make_rerate").empty();
            $("#make_rerate").selectpicker('refresh');
            $("#model_rerate").html('');
            $("#model_rerate").empty();
            $("#model_rerate").selectpicker('refresh');
            $('#estimatedValue').val('');
            getVehicleMakes(this.value);
        });

        // Vehicle Make
        function getVehicleMakes(e) {
            if (e == 'Yes') {
                var fetchURL = baseURL + 'api/frontendpay/vehicleMake'
            } else {
                var fetchURL = baseURL + 'api/frontendpay/getTTVehicleMakes'
            }
            $.ajax({
                type: "POST",
                datatype: 'json',
                url: fetchURL,
                dataType: "json",
                beforeSend: function() {
                    $("#loader").show();
                },
                success: function(data) {
                    $("#loader").hide();
                    $("#make_rerate").html('');
                    $("#make_rerate").empty();
                    $("#make_rerate").append('<option value="">Select vehicle make</option>');
                    if (e == 'Yes') {
                        $.each(data.makes, function() {
                            $("#make_rerate").append('<option value="' + this.s_Make +
                                '">' + this.s_Make + '</option>')
                        });
                    } else {
                        var makes = data.Makes;
                        $.each(makes, function(key, val) {
                            var makes = $('<option value="' + val + '">' + val +
                                '</option>');
                            $("#make_rerate").append(makes);
                        });
                    }
                    //$('#year_rerate').prop('selectIndex', 0);
                    $('#make_rerate').selectpicker('refresh');
                    $('#year_rerate').selectpicker('refresh');
                }
            });
        }

        // year → estimated value (final step of the make → model → year cascade)
        $('#year_rerate').on('change', function() {
            var status = $('#is_imported').val();
            var make   = $('#make_rerate').val();
            var model  = $('#model_rerate').val();
            var year   = this.value;
            $('#estimatedValue').val('');
            if (year && status == 'No') {
                getEstimatedValue(make, model, year);
            }
        });

        // make → models
        $('#make_rerate').on('change', function() {
            $("#model_rerate").html('').empty();
            $("#model_rerate").append('<option value="">Select vehicle model</option>');
            $("#model_rerate").selectpicker('refresh');
            $('#estimatedValue').val('');
            getVehicleModels($('#is_imported').val(), this.value);
        });

        // Vehicle Models — looked up by make only (getTTVehicleModels hits
        // /lookup/model/{make}; the imported path uses the local vehicleModel).
        function getVehicleModels(status, make) {
            $('#estimatedValue').val('');
            if (status == 'Yes') {
                $.ajax({
                    type: "POST",
                    datatype: 'json',
                    url: baseURL + 'api/frontendpay/vehicleModel',
                    data: {
                        vehicle_make: make,
                    },
                    dataType: "json",
                    beforeSend: function() {
                        $("#loader").show();
                        $("#model_rerate").empty();
                        $('#make_rerate').selectpicker('refresh');
                    },
                    success: function(responseData) {
                        $("#loader").hide();
                        $("#model_rerate").html('').empty();
                        $('#make_rerate').selectpicker('refresh');
                        $("#model_rerate").append('<option value="">Select Model</option>');
                        $.each(responseData.makes, function() {
                            $("#model_rerate").append('<option value="' + this.s_Variant +
                                '">' + this.s_Variant + '</option>')
                        });
                        $("#model_rerate").selectpicker('refresh');
                    },
                    complete: function() {
                        $("#loader").hide();
                    }
                });
            } else {
                $.ajax({
                    type: "POST",
                    datatype: 'json',
                    url: baseURL + 'api/frontendpay/getTTVehicleModels',
                    data: {
                        make: make,
                    },
                    dataType: "json",
                    beforeSend: function() {
                        $("#loader").show();
                        $("#model_rerate").empty();
                        $("#model_rerate").selectpicker('refresh');
                    },
                    success: function(responseData) {
                        $("#loader").hide();
                        $("#model_rerate").html('').empty();
                        $("#model_rerate").append('<option value="">Select vehicle model</option>');
                        $.each(responseData.Models, function() {
                            var option = $('<option value="' + this.Model +
                                '" data-vehicle="' + this.IntroYear +
                                '" data-vehicle-disc="' + this.DisconYear + '" >' + this
                                .Model + '</option>');
                            $("#model_rerate").append(option);
                        });
                        $("#model_rerate").selectpicker('refresh');
                    },
                    error: function(data) {
                        var option = $('<option value="" disabled>No vehicle found</option>');
                        $("#model_rerate").append(option);
                    },
                    complete: function() {
                        $("#loader").hide();
                    }
                });
            }
        }

        // model → manufacturing years (looked up by make + model). For imported
        // (local) vehicles the server-rendered year list stands.
        $('#model_rerate').on('change', function() {
            $('#estimatedValue').val('');
            var status = $('#is_imported').val();
            var make   = $('#make_rerate').val();
            var model  = this.value;
            if (model && status == 'No') {
                getVehicleYears(make, model);
            }
        });

        // Vehicle manufacturing years (getTTVehicleYear → /lookup/year/{make}/{model})
        function getVehicleYears(make, model) {
            $.ajax({
                type: "POST",
                datatype: 'json',
                url: baseURL + 'api/frontendpay/getTTVehicleYear',
                data: {
                    make: make,
                    vehicleModel: model,
                },
                dataType: "json",
                beforeSend: function() {
                    $("#loader").show();
                    $("#year_rerate").empty();
                    $('#year_rerate').selectpicker('refresh');
                },
                success: function(responseData) {
                    $("#loader").hide();
                    $("#year_rerate").html('').empty();
                    $("#year_rerate").append('<option value="">Select manufacturing year</option>');
                    var years = responseData.year || [];
                    $.each(years, function(key, val) {
                        var y = (val && typeof val === 'object') ? (val.year || val.Year || val.value || val) : val;
                        $("#year_rerate").append('<option value="' + y + '">' + y + '</option>');
                    });
                    $("#year_rerate").selectpicker('refresh');
                },
                error: function() {
                    $("#loader").hide();
                    $("#year_rerate").append('<option value="" disabled>No years found</option>');
                    $("#year_rerate").selectpicker('refresh');
                },
                complete: function() {
                    $("#loader").hide();
                }
            });
        }

        // Estimated value (getTTValue → by make + model + year)
        function getEstimatedValue(make, model, year) {
            $.ajax({
                type: "POST",
                datatype: 'json',
                url: baseURL + 'api/frontendpay/getTTValue',
                data: {
                    vehicleMake: make,
                    vehicleModel: model,
                    manufacturing_year: year,
                    condition: 'EX',
                    mileage: 'LO',
                },
                dataType: "json",
                beforeSend: function() {
                    $('#valueLoader').show();
                    $('#estimatedValue').hide();
                    $('#ratingsCalculation').prop('disabled', true);
                },
                success: function(data) {
                    $('#valueLoader').hide();
                    $('#estimatedValue').show();
                    if (data.value) {
                        var val = (data.value).toFixed(2)
                        $('#estimatedValue').val(val);
                        $('#ratingsCalculation').prop('disabled', false);
                        if (data.value > 500000) {
                            $('#ratingsCalculation').prop('disabled', true);
                            $("#ratingsCalculation").hide();
                            $("#requestcallback").show();
                        }
                    }
                },
                error: function() {
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
        $('#year_rerate').selectpicker('refresh');

        //Rerate Premium
        $('#submit_rerate_button').on('click', function() {
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
                var fetchURL = baseURL + 'api/frontendpay/reratePolicyPremium';
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
                        user_id: '{{ Auth::user()->id }}',
                    },
                    beforeSend: function() {
                        // setting a timeout
                        $('#loader').show();
                        $("#new_rate_div").slideUp();
                    },
                }).done(function(response) {
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
                }).fail(function(response) {
                        $('#loader').hide();
                        $("#new_rate_div").slideUp();
                        $("#page_footer").slideDown();
                        if (response.status == 400) {
                            toastr.error('Error ! ' + response.responseJSON.message);
                        }
                })
            } else {
                $('#reratePremiumError').css('display','block');
            }
        });

        $('#discountSurchargeValue').on('click', function() {
            $("#add_custom_rate").hide();
            console.log(1);
            var isChecked = document.getElementById("discountSurchargeValue").checked;
            console.log(isChecked);
            if (isChecked) {
                $('#discount_surcharge_div').slideDown();
                document.getElementById("MsgDiscountSurcharge").innerHTML = "Yes";
                document.getElementById("MsgDiscountSurcharge").style.color = "cornflowerblue";
            } else {
                $("#add_custom_rate").show();
                $("#reason").prop('required', false);
                $("#value").prop('required', false);
                $("#value_type").prop('required', false);
                $("#type").prop('required', false);
                $('#discount_surcharge_div').slideUp();
                document.getElementById("MsgDiscountSurcharge").innerHTML = "No";
                document.getElementById("MsgDiscountSurcharge").style.color = "#ff4d4d";
            }
        });

        // Apply Custom Rate
        $('#customDiscountSurchargeValue').on('click', function() {
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
        });

        $('#paymentValue').on('click', function() {
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
            }
        });

        $('#paymentValueDpo').on('click', function() {
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
        });

        $('#cashPaymentDone').on('click', function() {
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

            }
        });

        $('#paymentValueCash').on('click', function() {
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

            }
        });
</script>

