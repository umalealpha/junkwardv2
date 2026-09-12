<div class="kt-portlet">
    <!--begin::Form-->
    <div class="kt-portlet__body">
        <h5>Change Model</h5>
        {{-- action="{{ route('admin.policy.convertPolicyFrequency') }}" --}}
        <form id="changeVehicleModel" action="{{ route('admin.policy.changeVehicleModel') }}" method="POST" enctype="multipart/form-data" class="kt-form">
          <!-- CSRF Token -->
          <input type="hidden" name="_token" value="{{ csrf_token() }}" />
          <input type="hidden" id="vehicle_isImported" name="vehicle_isImported" value="{{ $premiumCalcDetails->is_imported ?? 'No' }}"/>

          <div class="kt-portlet__body">
              <div class="form-group row">
                  <label  class="col-3 col-form-label">Make :</label>
                  <div class="col-9">
                      <input  class="form-control" id="vehicle_make" name="vehicle_make"  value="{{ isset($vehicle) ? $vehicle->make : $vehicle->make; }}" title="Please provide vehicle make" placeholder="Please provide vehicle make" readonly>
                      <input type="hidden" id="policyID_changeModel" name="policyID" value="{{ $policy->id }}"/>
                      <span class="form-text text-muted"></span>
                  </div>
              </div>
              <div class="form-group row">
                <label  class="col-3 col-form-label">Make :</label>
                <div class="col-9">
                    <input  class="form-control" id="vehicle_year" name="vehicle_year"  value="{{ isset($vehicle) ? $vehicle->year : $vehicle->year; }}" title="Please provide vehicle year" disabled placeholder="Please provide vehicle year" readonly>

                    {{-- <select class="form-control kt_selectpicker" required name="year"
                        title="Please select manufacturing year" data-live-search="true"
                        id="year_rerate">
                        @foreach ($years as $key => $year)
                            <option value="{{ $year }}" @if ($vehicle->year == $year) selected @endif>
                                {{ $year }}</option>
                        @endforeach
                    </select> --}}
                </div>
            </div>
              <div class="form-group row">
                  <label  class="col-3 col-form-label">Model :</label>
                  <div class="col-9">
                      {{-- <input  class="form-control" name="vehicle_model" value="{{$vehicle->make}}"  title="Please provide vehicle model" placeholder="Please provide vehicle model">
                      <span class="form-text text-muted"></span> --}}
                      <select class="form-control kt_selectpicker" required name="vehicle_model"
                            title="Please select vehicle model" data-live-search="true"
                            id="vehicle_model">
                            @if (isset($vehicle))
                                @if (isset($premiumCalcDetails->is_imported) && $premiumCalcDetails->is_imported == "Yes")
                                @foreach ($dataModel as $key => $model)
                                    <option value="{{ $model['model'] }}" @if ($vehicle->model == $model['model']) selected @endif>
                                        {{ ucwords(strtoupper($model['model'])) }}</option>
                                @endforeach
                                <option value="vehicle_other_model" class="vehicle_other_model">Other Model</option>
                                @endif

                                @if (is_array($dataModel) && isset($premiumCalcDetails->is_imported) && $premiumCalcDetails->is_imported == "No")
                                    @foreach ($dataModel as $key => $model)
                                        <option value="{{ $model['Model'] }}" @if ($vehicle->model == $model['Model']) selected @endif>
                                            {{ ucwords(strtoupper($model['Model'])) }}</option>
                                    @endforeach
                                    <option value="vehicle_other_model" class="vehicle_other_model">Other Model</option>
                                @endif
                            @endif

                        </select>

                        <div class="form-group row" id="vehicle_otherModel_div" style="display: none;">
                            <label  class="col-3 col-form-label"></label>
                            <div class="col-9">
                                <input  class="form-control" id="vehicle_otherModel" name="vehicle_otherModel"  value="" title="Please provide vehicle other model" placeholder="Please provide vehicle other model">
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
                          <button type="submit" value="Submit" id="freqSubmitbtn" class="btn btn-brand">Submit</button>
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
