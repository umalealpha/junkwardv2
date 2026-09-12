<div>

    <!-- CSRF Token -->
    <input type="hidden" name="_token" value="{{ csrf_token() }}" />
    <input type="hidden" name="policyNumber" value="{{ $policy->policyNumber }}" />
    <input type="hidden" name="paymentMethod" value="Cash" />
    <div class="kt-portlet__body">

        @if (\Illuminate\Support\Facades\Auth::user()->hasPermissionTo('back-dated-transactions'))
            <div class="form-group row">
                <label class="col-3 col-form-label">Date of payment :</label>
                <div class="col-9">
                    <input type="text" class="form-control required kt_datepicker_1 validateGroup1"
                        name="paymentDate" autocomplete="off" title="Please provide payment received date"
                        placeholder="Select date of payment" />
                    <span class="form-text text-muted"></span>
                </div>
            </div>
        @else
            <p>Note : You can select Date of Payment for current month.</p>
            <div class="form-group row">
                <label class="col-3 col-form-label">Date of payment :</label>
                <div class="col-9">
                    <input type="text" class="form-control required validateGroup1"
                        id="date_of_refund_for_current_month" name="paymentDate" autocomplete="off"
                        title="Please provide payment received date" placeholder="Select date of payment" />
                    <span class="form-text text-muted"></span>
                </div>
            </div>
        @endif

        <div class="form-group row">
            <label class="col-3 col-form-label">Payment Amount :</label>
            <div class="col-9">
                <input class="form-control required validateGroup1" name="paymentAmount" value=""
                    title="Please provide amount" placeholder="Please provide amount">
                <span class="form-text text-muted"></span>
            </div>
        </div>
        <div class="form-group row">
            <label class="col-3 col-form-label">Receipt number:</label>
            <div class="col-9">
                <input class="form-control required validateGroup1" name="receiptNumber" value=""
                    title="Please provide payment receipt"
                    placeholder="Please provide payment receipt number">
                <span class="form-text text-muted"></span>
            </div>
        </div>
        <div class="form-group row">
            <label class="col-3 col-form-label">Payment Recieved By :</label>
            <div class="col-9">
                <input class="form-control required validateGroup1" name="paymentRecievedBy" value=""
                    title="Please provide contract sequence" placeholder="Please provide payment recipient">
                <span class="form-text text-muted"></span>
            </div>
        </div>

        <div class="form-group row">
            <label class="col-3 col-form-label">Numbers of Installments paid :</label>
            <div class="col-9">
                <input class="form-control required validateGroup1" name="numberOfInstallmentsPaid"
                    value="" title="Please provide the number of Installments paid"
                    placeholder="Please provide the number of Installments paid">
                <span class="form-text text-muted"></span>
            </div>
        </div>

        <div class="form-group row">
            <label class="col-3 col-form-label">Payment Frequency :</label>
            <div class="col-9">
                <select id="paymentFreq" class="form-control required kt_selectpicker"
                    title="Please select payment frequency" name="paymentFreq">
                    <option value="1">Monthly Installments</option>
                    <option value="2">Three Installments in a year</option>
                    <option value="3">Annual Installment</option>
                </select>
                <span class="form-text text-muted"></span>
            </div>
        </div>
        <div class="form-group row">
            <label class="col-3 col-form-label">Note :</label>
            <div class="col-9">
                <textarea class="form-control required validateGroup1" name="paymentNote" value=""
                    title="Please provide note" placeholder="Please provide note"></textarea>
                <span class="form-text text-muted"></span>
            </div>
        </div>
        <div class="form-group row">
            <label for="example-text-input" class="col-3 col-form-label">Upload Payment Proof</label>
            <div class="col-2">
                <div class="kt-avatar" id="product_image" style="float: left; clear: left;">

                    <div class="kt-avatar__holder"
                        style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)">
                    </div>

                    <label class="kt-avatar__upload" data-toggle="kt-tooltip" title="Change Image">
                        <i class="fa fa-pen"></i>
                        <input type='file' name="payment_image" <?php echo config('app.accept_attr'); ?> <?php echo config('app.accept_msg'); ?> />
                    </label>
                    <span class="kt-avatar__cancel" data-toggle="kt-tooltip" title="Cancel Image">
                        <i class="fa fa-times"></i> </span>
                </div>
            </div>

        </div>
        <div class="form-group row">
            <label for="example-text-input" class="col-3 col-form-label">Add payment on Realpay</label>
            <div class="col-9">
                <span class="kt-switch">
                    <label>
                        <input id="addPaymentRealpay" type="checkbox" name="addRealpay" value="1"
                            onchange="addPaymentRealpayPay()">
                        <span style="margin-top: 10px;margin-left: 10px;"></span>
                        <h4 id="paymentMsgRealpay"
                            style="display:inline;float:left;margin-top: 14px;margin-left: 5px;color:#ff4d4d;">
                            No</h4>

                    </label>
                </span>
            </div>
        </div>
    </div>
    <div class="kt-portlet__body" id="paymentInfoDiv" style="margin-top:-50px !important;">
        <div class="form-group row">
            <label class="col-3 col-form-label">Installment Start Date :</label>
            <div class="col-9">
                <input type="text" class="form-control required kt_datepicker_1 validateGroup1"
                    name="paymentStartDate" autocomplete="off" placeholder="Select payment start date" />
                <span class="form-text text-muted"></span>
            </div>
        </div>
        <div class="form-group row">
            <label class="col-3 col-form-label">Please select bank :</label>
            <div class="col-9">
                <select id="RPBanks" class="form-control required kt_selectpicker"
                    title="Please select bank" name="RPBanks">
                    @if (isset($banks['names']) && count($banks) > 0)
                        @foreach ($banks['names'] as $bank)
                            <option value="{{ $bank->bank_number }}">{{ $bank->bank_name }}</option>
                        @endforeach
                    @endif

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
                    title="Please provide account number" placeholder="Please provide account number">
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
                    <button class="btn btn-brand" type="button" id="loadBtn" style="display:none"> <span
                            class="spinner-border spinner-border-sm" role="status"
                            aria-hidden="true"></span> Loading... </button>
                    <a class="btn btn-secondary" href="{{ route('admin.policy.index') }}">Cancel</a>
                </div>
            </div>
        </div>
    </div>

</div>
