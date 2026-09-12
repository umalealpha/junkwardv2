
                    <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Date of Death</label>
                        <div class="col-9">
                            <input type="text" class="form-control kt_datepicker_1 dob" id="dob" name="date_of_death" autocomplete="off" title="Date of death is required">
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="example-text-input" class="col-3 col-form-label">Cause of Death</label>
                        <div class="col-9">
                            <select class="form-control kt_selectpicker" title="Please choose Cause"
                                    data-live-search="true"
                                    id="cause"  name="cause" required>
                                @foreach($deathCauses as $deathCause)
                                    <option value="{{ $deathCause->id }}">{{ $deathCause->value }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="example-text-input" class="col-3 col-form-label">Date of claim registered</label>
                        <div class="col-9">
                            <input type="text" class="form-control  kt_datepicker_1"   placeholder="Please select date" name="registered_claim" autocomplete="off">
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="example-text-input" class="col-3 col-form-label">Upload Death Certificate</label>
                        <div class="col-2">
                            <div class="kt-avatar" id="death_certificate" style="float: left; clear: left;">
                                <div class="kt-avatar__holder" style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                                <label class="kt-avatar__upload" data-toggle="kt-tooltip" title="Change Image">
                                    <i class="fa fa-pen"></i>
                                    <input type='file' name="death_certificate" <?php echo config('app.accept_attr'); ?> <?php echo config('app.accept_msg'); ?> />
                                </label>
                                <span class="kt-avatar__cancel" data-toggle="kt-tooltip" title="Cancel Image"> <i class="fa fa-times"></i> </span>
                            </div>
                        </div>
                        <div class="col-7">
                            <textarea class="form-control" name="description" placeholder="Add description" id="description" rows="3" required></textarea>
                        </div>
                    </div>


                    @if($policy->has_member != 0)
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
                                        @foreach($beneficiaries as $key => $beneficiary)
                                            <tr style="border-top: solid 2px #666;">
                                                <th>Relation with beneficiary</th>
                                                <td>{!! $beneficiary->relation !!}</td>
                                                <th>Name</th>
                                                <td>{!! $beneficiary->first_name !!} {!! $beneficiary->last_name !!}</td>
                                            </tr>
                                            <tr >
                                                <th>Date of Birth</th>
                                                <td>{!! $beneficiary->dob !!}</td>
                                                <th>Gender</th>
                                                @if($beneficiary->gender == 0)
                                                    <td>Female </td>
                                                @else
                                                    <td>Male</td>
                                                @endif
                                            </tr>
                                            <tr style="border-bottom: solid 2px #666;">
                                                <th>Payment</th>
                                                <td>{!! $beneficiary->payment !!} %</td>
                                                <th>&nbsp</th>
                                                <td></td>
                                            </tr>



                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    @endif
{{--                    <div class="col-12">--}}
{{--                        <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>--}}
{{--                        <h3 class="kt-heading kt-heading--md">--}}
{{--                            Beneficiary:--}}
{{--                        </h3>--}}

{{--                        @foreach($beneficiaries as $key => $beneficiary)--}}
{{--                        <input type="hidden" name="old_beneficiaryId[]" value="{!! $beneficiary->id !!}">--}}
{{--                            <div style="margin-left: 90%;" class="kt-repeater__data form-group col-12">--}}
{{--                                <span  class="btn btn-danger btn-sm"> <i class="la la-close"></i> Remove </span>--}}
{{--                            </div>--}}
{{--                            <div class="form-group row">--}}
{{--                                <div class="col-lg-6">--}}
{{--                                    <label>First name</label>--}}
{{--                                    <div class="input-group">--}}
{{--                                        <input type="text" class="form-control" placeholder="First name"  value="{!! $beneficiary->first_name !!}" name="old_beneficiaryFName[]">--}}
{{--                                    </div>--}}
{{--                                </div>--}}

{{--                                <div class="col-lg-6 ">--}}
{{--                                    <label>Last name</label>--}}
{{--                                    <div class="input-group">--}}
{{--                                        <input type="text" class="form-control" name="old_beneficiaryLName[]" value="{!! $beneficiary->last_name !!}" placeholder="Last name">--}}
{{--                                    </div>--}}
{{--                                </div>--}}
{{--                            </div>--}}
{{--                            <div class="form-group row ">--}}
{{--                                <div class="col-lg-6">--}}
{{--                                    <label>Date Of Birth</label>--}}
{{--                                    <input type="text" class="form-control kt_datepicker_1" name="old_beneficiaryDOB[]" value="{!! $beneficiary->dob !!}" autocomplete="off" placeholder="Select date"/>--}}
{{--                                </div>--}}
{{--                                <div class="col-lg-6">--}}
{{--                                    <label>Payment (%)</label>--}}
{{--                                    <div class="input-group">--}}
{{--                                        <input type="number"  class="form-control" name="old_beneficiaryPayment[]" value="{!! $beneficiary->payment !!}" placeholder="(%)">--}}
{{--                                    </div>--}}
{{--                                </div>--}}
{{--                            </div>--}}
{{--                            <div class="form-group">--}}
{{--                                <div class="col-lg-12">--}}
{{--                                    <label>Gender</label>--}}
{{--                                    <div class="kt-radio-inline">--}}
{{--                                        <label class="kt-radio">--}}
{{--                                            <input type="radio" name="old_beneficiaryGender[{!! $key !!}]" value="1" @if($beneficiary->gender == 1) checked @endif>--}}
{{--                                            Male <span></span>--}}
{{--                                        </label>--}}
{{--                                        <label class="kt-radio">--}}
{{--                                            <input type="radio" name="old_beneficiaryGender[{!! $key !!}]" value="0" @if($beneficiary->gender == 0) checked @endif>--}}
{{--                                            Female<span></span>--}}
{{--                                        </label>--}}
{{--                                    </div>--}}
{{--                                </div>--}}
{{--                            </div>--}}

{{--                            <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>--}}
{{--                        @endforeach--}}
{{--                        <div>--}}
{{--                            <button type="button" id="addBeneficiary" class="btn btn-brand">Add Beneficiary</button>--}}
{{--                            <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>--}}
{{--                        </div>--}}
{{--                        <div class="row" id="addBeneficiaryDiv" style="display: none;">--}}
{{--                            <div class="col-lg-12">--}}
{{--                                <div class="kt-repeater">--}}
{{--                                    <div data-repeater-list="members">--}}
{{--                                        <div data-repeater-item class="kt-repeater__item">--}}
{{--                                            <h3 class="kt-heading kt-heading--md kt-heading--no-top-margin">--}}
{{--                                                Beneficiary Details--}}
{{--                                            </h3>--}}

{{--                                            <div class="form-group row">--}}
{{--                                                <div class="col-lg-6">--}}
{{--                                                    <label>First name</label>--}}
{{--                                                    <div class="input-group">--}}
{{--                                                        <input type="text" class="form-control" placeholder="First name" name="beneficiaryFName">--}}
{{--                                                    </div>--}}
{{--                                                </div>--}}
{{--                                                <div class="col-lg-6 ">--}}
{{--                                                    <label>Last name</label>--}}
{{--                                                    <div class="input-group">--}}
{{--                                                        <input type="text" class="form-control" name="beneficiaryLName" placeholder="Last name">--}}
{{--                                                    </div>--}}
{{--                                                </div>--}}
{{--                                            </div>--}}
{{--                                            <div class="form-group row">--}}
{{--                                                <div class="col-lg-6">--}}
{{--                                                    <label>Date Of Birth</label>--}}
{{--                                                    <input type="text" class="form-control kt_datepicker_1" name="beneficiaryDOB" autocomplete="off" placeholder="Select date"/>--}}
{{--                                                </div>--}}

{{--                                                <div class="col-lg-6">--}}
{{--                                                    <label>Payment (%)</label>--}}
{{--                                                    <div class="input-group">--}}
{{--                                                        <input type="number"  class="form-control" name="beneficiaryPayment" placeholder="(%)">--}}
{{--                                                    </div>--}}
{{--                                                </div>--}}
{{--                                            </div>--}}
{{--                                            <div class="form-group">--}}
{{--                                                <div class="kt-radio-inline">--}}
{{--                                                    <label class="kt-radio">--}}
{{--                                                        <input type="radio" name="beneficiaryGender" value="1">--}}
{{--                                                        Male <span></span>--}}
{{--                                                    </label>--}}
{{--                                                    <label class="kt-radio">--}}
{{--                                                        <input type="radio" name="beneficiaryGender" value="0">--}}
{{--                                                        Female<span></span>--}}
{{--                                                    </label>--}}
{{--                                                </div>--}}
{{--                                            </div>--}}
{{--                                            <div class="kt-repeater__data form-group">--}}
{{--                                                <span data-repeater-delete="" class="btn btn-warning btn-sm"> <i class="la la-close"></i> Remove </span>--}}
{{--                                            </div>--}}
{{--                                            <div class="kt-separator kt-separator--border-dashed"></div>--}}
{{--                                            <div class="kt-separator kt-separator--height-sm"></div>--}}
{{--                                        </div>--}}
{{--                                    </div>--}}
{{--                                    <div class="kt-repeater__add-data">--}}
{{--                                        <span data-repeater-create="" class="btn btn-brand btn-sm" > <i class="la la-plus"></i> Add Beneficiary </span>--}}
{{--                                    </div>--}}
{{--                                    <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>--}}

{{--                                </div>--}}
{{--                            </div>--}}
{{--                        </div>--}}
{{--                    </div>--}}
                    @if($policy->kyc_recipient)
                        @include('admin.policy.recipient_kyc')
                    @endif

                    <div class="kt-portlet__foot kt-portlet__foot--solid">
                        <div class="kt-form__actions">
                            <div class="row">
                                <div class="col-3"></div>
                                <div class="col-9">
                                    <button type="submit"  value="Submit" class="btn btn-brand">Submit</button>
                                    <a class="btn btn-secondary" href="{{ route('admin.policy.index') }}" >Cancel</a>
                                </div>
                            </div>
                        </div>
                    </div>
