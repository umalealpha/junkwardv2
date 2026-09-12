<div class="kt-portlet">
    <div class="kt-portlet_body"  >
        <div class="kt-section">
            <div class="kt-section__content" style="padding-left:20px; padding-right: 20px;">
                    <p style="font-size: 172%;margin-bottom: 15px;font-weight: 510;">Lawyer's details :</p>

                    <div class="row">
                        <input type="hidden" name="machine_data" id="machine_data" value="" >
                        <div class="col-6 form-group">
                            <label>Legal Firm</label>
                            <input type="text" id="legal_firm" name="legal_firm" class="form-control required" title="Please enter name of legal firm" autocomplete="off" value="" placeholder="Please enter name of legal firm">
                        </div>
                        <div class="col-6 form-group">
                            <label>Lawyer's name</label>
                            <input type="text" id="lawyer_name" name="lawyer_name" class="form-control required" title="Please enter lawyer's name " autocomplete="off" value="" placeholder="Please enter lawyer's name ">
                        </div>
                        <div class="col-6 form-group">
                            <label>Telephone No.</label>
                            <input type="number" id="legal_tel" name="legal_tel" class="form-control required" title="Please enter Telephone no" autocomplete="off" value="" placeholder="Please enter Telephone no">
                        </div>
                        <div class="col-6 form-group">
                            <label>Email</label>
                            <input type="email" id="legal_email" name="legal_email" class="form-control required" title="Please enter legal firm email" autocomplete="off" value="" placeholder="Please enter legal firm email">
                        </div>
                        <div class="switch col-6 form-group" style="margin-bottom: 10px">
                            <h4>Do you want to use your own law firm?</h4>
                            <div class="btn-group btn-group-toggle" data-toggle="buttons">
                                <label class="btn btn-outline-secondary px-3 t600 ls0 nott active">
                                    <input type="radio" name="legaloption" class="legaloption"  autocomplete="off" checked value="0"> NO
                                </label>
                                <label class="btn btn-outline-secondary px-3 t600 ls0 nott ">
                                    <input type="radio" name="legaloption" class="legaloption"  autocomplete="off" value="1"> Yes
                                </label>
                            </div>
                        </div>
                        <div class="ownlawyer" style="display:none">
                            <div class="col-12 form-group">
                                <p class="bold">Are you prepared to represent our Member in terms of the Alphadirect Membership Agreement and Alphadirect Tariffs?</p>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input representingMember" type="radio" name="representing_member" value="1">
                                    <p class="form-check-label nott ml-2 bold" >Yes</p>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input representingMember" type="radio" name="representing_member"  value="0">
                                    <p class="form-check-label nott ml-2 bold" >No</p>
                                </div>
                            </div>
                            <div class="col-12 form-group">
                                <p style="font-weight: 600;">If you answered no to the abovementioned question, are you prepared to assist Our Member in so far as your assistance is required to enable Our
                                Member to comply with the relevant provisions of the Membership Agreement in order for Us to assess the claim? If cover is confirmed are you
                                prepared to assist Our Member to obtain payment from Us for your fees for which the Company may be liable to your client in terms of any written
                                Confirmation of Cover and in terms of Our Tariff ?</p>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input lawyerTarrif" type="radio" name="lawyer_tarrif" value="yes">
                                <p class="form-check-label nott ml-2 bold">Yes</p>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input lawyerTarrif" type="radio" name="lawyer_tarrif" value="no">
                                <p class="form-check-label nott ml-2 bold">No</p>
                            </div>
                        </div>
                        </div>
                    </div>
                    <hr>
                    <!-- <h3></h3> -->
                    <p style="font-size: 172%;margin-bottom: 15px;font-weight: 510;">Member Details :</p>
                    <div class="row">
                        <div class="col-6 form-group">
                            <label>Name</label>
                            <input type="text" id="member_name" name="member_name" class="form-control required" autocomplete="off" title="Please enter name of member" value="" placeholder="Please enter name of member">
                        </div>
                        <!-- <div class="col-6 form-group">
                            <label>Membership Number</label>
                            <input type="number" id="membership_number" name="membership_number" class="form-control required" autocomplete="off" title="Please enter the membership number" value="" placeholder="Please enter  membership number">
                        </div> -->
                        <div class="col-6 form-group">
                            <label>Membership ID</label>
                            <input type="number" id="membership_id" name="membership_id" class="form-control required" autocomplete="off" title="Please enter the membership Id" value="" placeholder="Please enter  membership ID">
                        </div>
                        <div class="col-6 form-group">
                            <label>Contact Number </label>
                            <input type="number" id="member_contact" name="member_contact" class="form-control required" autocomplete="off" title="Please enter member contact number" value="" placeholder="Please enter member contact number">
                        </div>
                        <div class="col-6 form-group">
                            <label>Email </label>
                            <input type="email" id="member_email" name="member_email" class="form-control required" autocomplete="off" title="Please enter member email" value="" placeholder="Please enter member email">
                        </div>
                        <div class="col-sm-6 col-xs-6 form-group">
                            <label>Date Reported to Alpha Direct<span class="red-star">*</span><i class="icon-info-circle" data-container="body" data-trigger="hover" data-toggle="popover" data-placement="top" data-content="Please enter the date when you realised that the Device is lost,stolen or damaged." data-original-title="" title="" aria-describedby="popover509230" style="font-size: 18px;width: 14px;color: #F08021;vertical-align:bottom;height:24px;"></i></label>
                            <input type="text" id="lossreported_date" name="lossreported_date" class="form-control required kt_datepicker_1" autocomplete="off" title="Please select incident date of loss/stolen/damage" value="" placeholder="Enter date of death">
                        </div>

                        <div  style="margin-bottom: 18px;display: inline-flex">
                        <input class="checkbox-style" name="tariffs_1" type="checkbox">
                            <span style=" margin-left: 7px;"> I accept that if my Lawyer is not prepared to work in accordance with the Alphadirect
                            Membership Agreement and Alphadirect Tariffs, that I will be personally liable for the difference.
                            </span>
                        </div>
                        <div  style="margin-bottom: 18px;display: inline-flex">
                            <input class="checkbox-style" name="tariffs_2" type="checkbox">
                            <span style=" margin-left: 7px;"> I understand that Alphadirect shall not be liable, unless a written Confirmation of Cover
                             is issued to me or my appointed Lawyer.
                            </span>
                        </div>
                    </div>
                    <br>
                    <p style="font-size: 172%;margin-bottom: 15px;font-weight: 510;"> Details of the Matter :</p>
                    <div class="row">
                        <div class="col-12 form-group">
                          <label>Who does the matter relate to? :</label>
                          <br>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input required" type="radio" name="matter_relatesto"  value="1">
                                <p class="form-check-label nott ml-2 bold" >Main Member</p>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="matter_relatesto"  value="2">
                                <p class="form-check-label nott ml-2 bold" >Spouse/Life Partner</p>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input required" type="radio" name="matter_relatesto"  value="3">
                                <p class="form-check-label nott ml-2 bold" >Child</p>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="matter_relatesto"  value="4">
                                <p class="form-check-label nott ml-2 bold" >Extended Family Member</p>
                            </div>
                        </div>
                        <div class="col-12 form-group">
                            <label>If a child, is the child financially dependent on the Main Member and a fulltime scholar?</label>
                            <br>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input required" type="radio" name="child_financial_dependent"  value="Yes">
                                <p class="form-check-label nott ml-2 bold" >Yes</p>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="child_financial_dependent"  value="No">
                                <p class="form-check-label nott ml-2 bold" >No</p>
                            </div>
                        </div>
                        <div class="col-6">
                            <label>ID No of child </label>
                            <input type="number" id="idforchild" name="idforchild" class="form-control required" autocomplete="off" title="Please enter child's ID" value="" placeholder="Please enter child's ID">
                        </div>
                        <div class="col-sm-6 col-xs-6 form-group">
                            <label>Date of birth</label>
                            <input type="text" id="child_dob" name="child_dob" class="form-control required child_dob" autocomplete="off" title="Please select Child's DOB" value="" placeholder="Please select Child's DOB">
                        </div>
                        <div class="col-12 form-group">
                            <label>Type of Matter</label>
                            <br>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input required" type="radio" name="realestate_enquiry_from"  value="1">
                                <p class="form-check-label nott ml-2 bold" for="realestate-enquiry-from-individual">Civil</p>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="realestate_enquiry_from"  value="2">
                                <p class="form-check-label nott ml-2 bold" >Criminal</p>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="realestate_enquiry_from"  value="3">
                                <p class="form-check-label nott ml-2 bold" >Labour</p>
                            </div>
                        </div>
                        <div class="col-sm-6 col-xs-6 form-group">
                            <label>Date upon which the matter arose</label>
                            <input type="text" id="arose_date" name="arose_date" class="form-control required kt_datepicker_1" autocomplete="off" title="Please select date" value="" placeholder="Please select date">
                        </div>

                        <div class="col-12 form-group">
                            <label>Quantum of the matter</label>
                            <input type="text" id="matter_quantum" name="matter_quantum" class="form-control required" autocomplete="off" title="Please enter quantum of matter" value="" placeholder="Please enter quantum of matter">
                        </div>
                        <div class="col-12 form-group">
                            <label>Proposed course of action <span class="red-star">*</span><i class="icon-info-circle" data-container="body" data-trigger="hover" data-toggle="popover" data-placement="top" data-content="Enter Proposed course of action" data-original-title="" title="" aria-describedby="popover509230" style="font-size: 18px;width: 14px;color: #F08021;vertical-align:bottom;height:24px;"></i></label>
                            <textarea name="course_of_action" minlength="200" id="course_of_action" class="form-control required" value="" placeholder="Enter Proposed course of action"></textarea>
                        </div>
                        <div class="col-12 form-group">
                            <label>Jurisdiction</label>
                            <input type="text" id="jurisdiction" name="jurisdiction" class="form-control required" autocomplete="off" title="Please enter jurisdiction" value="" placeholder="Please enter jurisdiction">
                        </div>
                        <div class="col-12 form-group">
                            <label>If a criminal matter. Any previous convictions? If yes, please list charges and dates convicted of same.<span class="red-star">*</span><i class="icon-info-circle" data-container="body" data-trigger="hover" data-toggle="popover" data-placement="top" data-content="If a criminal matter. Any previous convictions? If yes, please list charges and dates convicted of same." data-original-title="" title="" aria-describedby="popover509230" style="font-size: 18px;width: 14px;color: #F08021;vertical-align:bottom;height:24px;"></i></label>
                            <textarea name="criminalmatter_detail" minlength="200" id="criminalmatter_detail" class="form-control required" value="" placeholder="Enter criminal matter detail"></textarea>
                        </div>
                        <div class="col-12 form-group">
                            <label>If a criminal matter. What is the charge?</label>
                            <textarea name="criminalmatter_charge" minlength="200" id="criminalmatter_charge" class="form-control required" value="" placeholder="Enter criminal matter charge details"></textarea>
                        </div>
                    </div>
                    <br>
                    <div style="margin-bottom: 15px;display: inline-flex">
                        <input class="checkbox-style" name="declaration" type="checkbox">
                        <span style=" margin-left: 7px;">No claim will be paid out in cash. An excess cannot be offset against any claim. All amounts are inclusive of VAT.</span>
                    </div>
                    <div  style="margin-bottom: 18px;display: inline-flex">
                        <input class="checkbox-style" name="nofalseinfo" type="checkbox">
                        <span style=" margin-left: 7px;">I understand that willfully falsifying facts, or omitting material information in an insurance claim can be regarded as fraud and risks severe penalties including legal action, fines and prison sentences.</span>
                    </div>
                    <div  style="margin-bottom: 12px;display: inline-flex">
                        <input class="checkbox-style" name="signature" type="checkbox">
                        <span style=" margin-left: 7px;"> I understand that by clicking the submit button below, I am placing my electronic signature in place of a physical signature. I am aware that my IP address and all pertinent information is being collected.</span>
                    </div>
            </div>
        </div>
        {{-- @if($policy->kyc_recipient)
            @include('admin.policy.recipient_kyc')
        @endif --}}
        <div class="kt-portlet__foot kt-portlet__foot--solid">
            <div class="kt-form__actions">
                <div class="row">
                    <div class="col-5"></div>
                    <div class="col-7">
                        <button type="submit" value="Submit" id="saveBtn" class="btn btn-brand">Save Claim</button>
                        <button class="btn btn-brand" type="button" id="loadBtn" style="display:none" > <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Loading... </button>
                        <a class="btn btn-secondary" href="{{ route('admin.policy.index') }}" >Cancel</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>
