<div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
    <div class="kt-portlet kt-portlet--mobile">
        <div class="kt-portlet__body">
            <div style="margin-bottom:2%" id="rerate_div">
                <form id="updatePremiumDiscSurc" action="{{ URL::to('admin/discountsurcharge/policy') }}"
                      method="POST" enctype="multipart/form-data" class="kt-form">
                    <!-- CSRF Token -->
                    <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                    <input type="hidden" name="policyId" value="{{ $policy->id }}" />

                    <div class="form-group row">
                        <label class="col-3 col-form-label"><b>Annual Premium :</b></label>
                        <div class="col-9">
                            <span>P {!! number_format($annual_Premium,2,'.',',') !!}</span>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label class="col-3 col-form-label"><b>Sum Insured :</b></label>
                        <div class="col-9">
                            @if ($policy->sum_assured)
                                <span>P{!! number_format($policy->sum_assured, 0, '.', ',') !!}</span>
                            @else
                                <span>-</span>
                            @endif
                        </div>
                    </div>

                    <table class="table table-striped table-bordered table-hover table-checkable">
                        <thead>
                        <tr>
                            <th>Please select type:</th>
                            <td width="50%">
                                <select class="form-control kt_selectpicker" name="type" title="Please select type" data-live-search="true" id="type">
                                    <option value="discount">Discount</option>
                                    <option value="surcharge">Surcharge</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th>Please select value type:</th>
                            <td width="50%">
                                <select class="form-control kt_selectpicker" name="value_type" title="Please select value type" data-live-search="true" id="value_type">
                                    <option value="1">Flat value</option>
                                    <option value="2">Percent(%) value</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th>Value:</th>
                            <td width="50%">
                                <input type="text" class="form-control" name="value" title="Enter the value" placeholder="Enter value"/>
                            </td>
                        </tr>
                        <tr>
                            <th>Reason:</th>
                            <td width="50%">
                                <input type="text" class="form-control" name="reason" title="Please provide the reason" placeholder="Please provide reason"/>
                            </td>
                        </tr>
                        </thead>
                    </table>
                    <div class="kt-form__actions">
                        <div class="row">
                            <div class="col-5"></div>
                            <div class="col-7">
                                <button type="submit" class="btn btn-info">Submit</button>
                                <p class="btn btn-secondary" id="rerate_hide" style="margin-top:2%">Cancel</p>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <!--begin::Section-->
            <table class="table table-striped table-bordered table-hover table-checkable" id="discount_surcharge_policy_table">
                <thead>
                <tr>
                    <th>Policy Number</th>
                    <th>Discount (%)</th>
                    <th>Surcharge (%)</th>
                    <th>Old Value</th>
                    <th>New Value</th>
                    <th>Total Discount Surcharge</th>
                    <th>User Name</th>
                    <th>Created At</th>
                </tr>
                </thead>
            </table>

        </div>
    </div>
</div>
