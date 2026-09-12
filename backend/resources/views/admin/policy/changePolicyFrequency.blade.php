<div class="kt-portlet">
    <!--begin::Form-->
    <div class="kt-portlet__body">
        <h5>Preminum Frequency:
            @if ($policy->premium_freq == 1)
                <span>Monthly Instalments</span>
            @elseif ($policy->premium_freq == 2)
                <span>Three Instalments in a year</span>
            @else
                <span>Annual Instalment</span>
            @endif
            {{$policy->premium}} BWP
        </h5>
        {{-- action="{{ route('admin.policy.convertPolicyFrequency') }}" --}}
        {{-- <form id="accountCreate" enctype="multipart/form-data" class="kt-form"> --}}
          <!-- CSRF Token -->
          <input type="hidden" name="_token" value="{{ csrf_token() }}" />
          <div class="kt-portlet__body">
              <div class="form-group row">
                  <label  class="col-3 col-form-label">Policy Number :</label>
                  <div class="col-9">
                      <input  class="form-control" id="policyNumber_conFreq" name="policyNumber"  value="{{ $policy->policyNumber }}" title="Please provide policy number" disabled placeholder="Please provide policy number">
                      <input type="hidden" id="policyID_conFreq" name="policyID" value="{{ $policy->id }}"/>
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
                      <input  class="form-control" name="cellphone"  value="{{ $user->cellphone }}" title="Please provide cellphone number" value="" placeholder="Please provide cellphone number" disabled>
                      <span class="form-text text-muted"></span>
                  </div>
              </div>
              <div class="form-group row">
                  <label  class="col-3 col-form-label">Payment Frequency :</label>
                  <div class="col-9">
                    <select class="form-control" name="frequency" id="convert_frequency">
                        <option value="" disabled selected>Select frequency</option>
                        @isset($newConvertedAmt)
                            @if ($policy->premium_freq == 1)
                                <option value="2" >Three Instalments in a year P {{ isset($newConvertedAmt['threeInstlPremium']) ? number_format($newConvertedAmt['threeInstlPremium'], 2, '.', ',') : number_format($newConvertedAmt['3_inst'], 2, '.', ','); }}</option>
                                <option value="3" >Annual Instalment P {{ isset($newConvertedAmt['annualPremium']) ? number_format($newConvertedAmt['annualPremium'], 2, '.', ',') : number_format($newConvertedAmt['annual'], 2, '.', ','); }}</option>
                            @elseif ($policy->premium_freq == 2)
                                <option value="1" >Monthly Instalments P {{ isset($newConvertedAmt['monthlyPremium']) ? number_format($newConvertedAmt['monthlyPremium'], 2, '.', ',') : number_format($newConvertedAmt['monthly'], 2, '.', ','); }}</option>
                                <option value="3" >Annual Instalment P {{ isset($newConvertedAmt['annualPremium']) ? number_format($newConvertedAmt['annualPremium'], 2, '.', ',') : number_format($newConvertedAmt['annual'], 2, '.', ','); }}</option>
                            @elseif($policy->premium_freq == 3)
                                <option value="1" >Monthly Instalments P {{ isset($newConvertedAmt['monthlyPremium']) ? number_format($newConvertedAmt['monthlyPremium'], 2, '.', ',') : number_format($newConvertedAmt['monthly'], 2, '.', ','); }}</option>
                                <option value="2" >Three Instalments in a year P {{ isset($newConvertedAmt['threeInstlPremium']) ? number_format($newConvertedAmt['threeInstlPremium'], 2, '.', ',') : number_format($newConvertedAmt['3_inst'], 2, '.', ','); }}</option>
                            @else
                                <option value="1" >Monthly Instalments</option>
                                <option value="2" >Three Instalments in a year</option>
                                <option value="3" >Annual Instalment</option>
                            @endif
                        @endisset
                    </select>
                    <span id="convert_frequency_error" class="error"
                                    style="display: none; font-size: 12px; color:red;">This field is
                                    required.</span>
                      <span class="form-text text-muted"></span>
                  </div>
              </div>
          </div>
          <div class="kt-portlet__foot kt-portlet__foot--solid">
              <div class="kt-form__actions">
                  <div class="row">
                      <div class="col-3"></div>
                      <div class="col-9">
                          <button type="submit" value="Submit" id="freqSubmitbtn" class="btn btn-brand">Submit</button>
                          <button class="btn btn-brand" type="button" id="loadBtn" style="display:none" > <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Loading... </button>
                          <a class="btn btn-secondary" href="{{ route('admin.getCustomer') }}" >Cancel</a>
                      </div>
                  </div>
              </div>
          </div>
      {{-- </form> --}}
    </div>
    <!--end::Form-->
</div>
<div class="modal fade" id="convert_frequency_confirm" tabindex="-1" role="dialog" aria-labelledby="convert_frequency_confirm_title" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content" id="modal-content">
            <div class="modal-header"><h5 class="modal-title" id="exampleModalLabel">Change payment frequency</h5><button type="button" class="close" data-dismiss="modal" aria-label="Close"> <span aria-hidden="true">&times;</span> </button></div>
            <div class="modal-body">
                Are you sure you want to change payment frequency :<span id="optionName" class="font-weight-bold"></span> ?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-brand" data-dismiss="modal">Close</button>
                {{-- <form action="" id="convert_frequency_form" method="POST">
                    @csrf --}}
                    <button type="submit" id="convertFreqSubmit" class="btn btn-brand" >Change</button>
                {{-- </form> --}}
            </div>
        </div>
    </div>
</div>
