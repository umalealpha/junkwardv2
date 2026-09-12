<!--begin::Portlet-->
<div class="kt-portlet kt-portlet--height-fluid">
    <div class="kt-portlet__head">
        <div class="kt-portlet__head-label">
            <h3 class="kt-portlet__head-title">
                Legal Claim Information
            </h3>
        </div>
    </div>
    <div class="kt-portlet__body">
        <div class="kt-widget-4">
            <form id="storeClaim" action="{!! action('Admin\ClaimsController@update',  $legal->claim_id) !!}" method="POST" enctype="multipart/form-data">
                {{ method_field('PATCH') }}
                {{csrf_field()}}
                <br>
                <h3>Lawyer's details :</h3>
                <br>
                <div class="form-group row">
                        <label for="example-text-input" class="col-3 col-form-label">Legal Firm</label>
                        <div class="col-9">
                            <input type="text" id="legal_firm" name="legal_firm" class="form-control required" @if($legal->legal_firm != NULL) value="{!! $legal->legal_firm !!}" @endif title="Please enter name of legal firm" autocomplete="off" value="" placeholder="Please enter name of legal firm">
                        </div>
                </div>
                <div class="form-group row">
                    <label  for="example-text-input" class="col-3 col-form-label">Lawyer's name</label>
                    <div class="col-9">
                        <input type="text" id="lawyer_name" name="lawyer_name" class="form-control required" @if($legal->lawyer_name != NULL) value="{!! $legal->lawyer_name !!}" @endif title="Please enter lawyer's name " autocomplete="off" value="" placeholder="Please enter lawyer's name ">
                    </div>
                </div>
                <div class="form-group row">
                    <label  for="example-text-input" class="col-3 col-form-label">Telephone No.</label>
                    <div class="col-6 form-group">
                        <input type="number" id="legal_tel" name="legal_tel" class="form-control required" @if($legal->legal_tel != NULL) value="{!! $legal->legal_tel !!}" @endif title="Please enter Telephone no" autocomplete="off" value="" placeholder="Please enter Telephone no">
                    </div>
                </div>
                <div class="form-group row">
                    <label  for="example-text-input" class="col-3 col-form-label">Email</label>
                    <div class="col-6 form-group">
                        <input type="email" id="legal_email" name="legal_email" class="form-control required" @if($legal->legal_email != NULL) value="{!! $legal->legal_email !!}" @endif title="Please enter legal firm email" autocomplete="off" value="" placeholder="Please enter legal firm email">
                    </div>
                </div>
                <div class="switch form-group col-12 nodetails" style="margin-bottom: 10px">
                    <input type="hidden"  value="{!! $legal->legaloption !!}"  class="legaloptionValue">
                    <h4>Do you want to use your own law firm?</h4>
                    <div class="btn-group btn-group-toggle" data-toggle="buttons">
                        <label class="btn btn-outline-secondary px-3 t600 ls0 nott legal_no_active">
                            <input type="radio"  name="legaloption"  class="legaloption" @if($legal->legaloption == 0) checked @endif autocomplete="off" value="0"> NO
                        </label>
                        <label class="btn btn-outline-secondary px-3 t600 ls0 nott legal_yes_active">
                            <input type="radio"  name="legaloption" class="legaloption" @if($legal->legaloption == 1) checked @endif autocomplete="off" value="1"> Yes
                        </label>
                    </div>
                </div>

                <div class="ownlawyer">
                    <div class="col-12 form-group">
                        <p style="font-weight: 600;">Are you prepared to represent our Member in terms of the Alphadirect Membership Agreement and Alphadirect Tariffs?</p>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input representingMember" type="radio" name="representing_member" @if($legal->representing_member == 1) checked @endif value="1">
                            <p class="form-check-label nott ml-2 bold" >Yes</p>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input representingMember" type="radio" name="representing_member"  @if($legal->representing_member == 0) checked @endif value="0">
                            <p class="form-check-label nott ml-2 bold" >No</p>
                        </div>
                    </div>
                    <div class="col-12 form-group">
                        <p style="font-weight: 600;">If you answered no to the abovementioned question, are you prepared to assist Our Member in so far as your assistance is required to enable Our
                        Member to comply with the relevant provisions of the Membership Agreement in order for Us to assess the claim? If cover is confirmed are you
                        prepared to assist Our Member to obtain payment from Us for your fees for which the Company may be liable to your client in terms of any written
                        Confirmation of Cover and in terms of Our Tariff ?</p>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input lawyerTarrif" type="radio" name="lawyer_tarrif" @if($legal->lawyer_tarrif == 'yes') checked @endif value="yes">
                            <p class="form-check-label nott ml-2 bold">Yes</p>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input lawyerTarrif" type="radio" name="lawyer_tarrif" @if($legal->lawyer_tarrif == 'no') checked @endif value="no">
                            <p class="form-check-label nott ml-2 bold">No</p>
                        </div>
                    </div>
                </div>


                <br>
                <h3>Member Details :</h3>

                <div class="form-group row">
                    <label  for="example-text-input" class="col-3 col-form-label">Name :</label>
                    <div class="col-6 form-group">
                        <input type="text" id="member_name" name="member_name" class="form-control required"  value="{!! $legal->member_name !!}" title="Please enter Member Name" autocomplete="off" value="" placeholder="Please enter member name">
                    </div>
                </div>
                <div class="form-group row">
                    <label  for="example-text-input" class="col-3 col-form-label">Membership ID :</label>
                    <div class="col-6 form-group">
                        <input type="text" id="membership_id" name="membership_id" class="form-control required" @if($legal->membership_id != NULL) value="{!! $legal->membership_id !!}" @endif title="Please enter membership id" autocomplete="off" value="" placeholder="Please enter membership id">
                    </div>
                </div>
                <div class="form-group row">
                    <label  for="example-text-input" class="col-3 col-form-label">Membership ID :</label>
                    <div class="col-6 form-group">
                        <input type="text" id="member_contact" name="member_contact" class="form-control required" @if($legal->member_contact != NULL) value="{!! $legal->member_contact !!}" @endif title="Please enter member contact" autocomplete="off" value="" placeholder="Please enter member contact">
                    </div>
                </div>
                <div class="form-group row">
                    <label  for="example-text-input" class="col-3 col-form-label">Email :</label>
                    <div class="col-6 form-group">
                        <input type="email" id="member_email" name="member_email" class="form-control required" @if($legal->member_email != NULL) value="{!! $legal->member_email !!}" @endif title="Please enter member email" autocomplete="off" value="" placeholder="Please enter member email">
                    </div>
                </div>
                <div class="form-group row">
                    <label  for="example-text-input" class="col-3 col-form-label">Date Reported to Alpha Direct :</label>
                    <div class="col-6 form-group">
                        <input type="text" id="lossreported_date" name="lossreported_date" class="form-control required kt_datepicker_1" @if($legal->lossreported_date != NULL) value="{!! $legal->lossreported_date !!}" @endif title="Please enter date" autocomplete="off" value="" placeholder="Please enter date">
                    </div>
                </div>
                <br>
                <h3>Details of the Matter :</h3>
                <div class="col-12 form-group">
                    <label>Who does the matter relate to? :</label>
                    <br>
                      <div class="form-check form-check-inline">
                          <input class="form-check-input required" type="radio" name="matter_relatesto"  value="1" @if ($legal->matter_relatesto == 1) checked @endif>
                          <p class="form-check-label nott ml-2 bold" >Main Member</p>
                      </div>
                      <div class="form-check form-check-inline">
                          <input class="form-check-input" type="radio" name="matter_relatesto"  value="2" @if ($legal->matter_relatesto == 2) checked @endif>
                          <p class="form-check-label nott ml-2 bold" >Spouse/Life Partner</p>
                      </div>
                      <div class="form-check form-check-inline">
                          <input class="form-check-input required" type="radio" name="matter_relatesto"  value="3" @if ($legal->matter_relatesto == 3) checked @endif>
                          <p class="form-check-label nott ml-2 bold" >Child</p>
                      </div>
                      <div class="form-check form-check-inline">
                          <input class="form-check-input" type="radio" name="matter_relatesto"  value="4" @if ($legal->matter_relatesto == 4) checked @endif>
                          <p class="form-check-label nott ml-2 bold" >Extended Family Member</p>
                      </div>
                </div>
                <div class="col-12 form-group">
                    <label>If a child, is the child financially dependent on the Main Member and a fulltime scholar?</label>
                    <br>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input required" type="radio" name="child_financial_dependent"  value="Yes" @if ($legal->child_financial_dependent == 'Yes') checked @endif>
                        <p class="form-check-label nott ml-2 bold" >Yes</p>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="child_financial_dependent"  value="No" @if ($legal->child_financial_dependent == 'No') checked @endif>
                        <p class="form-check-label nott ml-2 bold" >No</p>
                    </div>
                </div>
                <div class="col-6">
                    <label>ID No of child </label>
                    <input type="number" id="idforchild" name="idforchild" class="form-control required" autocomplete="off" title="Please enter child's ID" @if($legal->idforchild != NULL) value="{!! $legal->idforchild !!}" @endif  placeholder="Please enter child's ID">
                </div>

                <div class="col-sm-6 col-xs-6 form-group">
                    <label>Date of birth</label>
                    <input type="text" id="child_dob" name="child_dob" class="form-control required child_dob" autocomplete="off" title="Please select Child's DOB" @if($legal->child_dob != NULL) value="{!! $legal->child_dob !!}" @endif  placeholder="Please select Child's DOB">
                </div>

                <div class="col-12 form-group">
                    <label>Type of Matter</label>
                    <br>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input required" type="radio" name="realestate_enquiry_from"  value="1" @if ($legal->realestate_enquiry_from == 1) checked @endif>
                        <p class="form-check-label nott ml-2 bold" for="realestate-enquiry-from-individual">Civil</p>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="realestate_enquiry_from"  value="2" @if ($legal->realestate_enquiry_from == 2) checked @endif>
                        <p class="form-check-label nott ml-2 bold" >Criminal</p>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="realestate_enquiry_from"  value="3" @if ($legal->realestate_enquiry_from == 3) checked @endif>
                        <p class="form-check-label nott ml-2 bold" >Labour</p>
                    </div>
                </div>
                <div class="col-sm-6 col-xs-6 form-group">
                    <label>Date upon which the matter arose</label>
                    <input type="text" id="arose_date" name="arose_date" class="form-control required kt_datepicker_1" autocomplete="off" title="Please select date" @if($legal->arose_date != NULL) value="{!! $legal->arose_date !!}" @endif placeholder="Please select date">
                </div>

                <div class="col-12 form-group">
                    <label>Quantum of the matter</label>
                    <input type="text" id="matter_quantum" name="matter_quantum" class="form-control required" autocomplete="off" title="Please enter quantum of matter" @if($legal->matter_quantum != NULL) value="{!! $legal->matter_quantum !!}" @endif  placeholder="Please enter quantum of matter">
                </div>
                <div class="col-12 form-group">
                    <label>Proposed course of action <span class="red-star">*</span><i class="icon-info-circle" data-container="body" data-trigger="hover" data-toggle="popover" data-placement="top" data-content="Enter Proposed course of action" data-original-title="" title="" aria-describedby="popover509230" style="font-size: 18px;width: 14px;color: #F08021;vertical-align:bottom;height:24px;"></i></label>
                    <textarea name="course_of_action" minlength="200" id="course_of_action" class="form-control required"
                     placeholder="Enter Proposed course of action">@if($legal->course_of_action != NULL){!! trim($legal->course_of_action) !!}@else {{ "N/A" }} @endif</textarea>
                </div>
                <div class="col-12 form-group">
                    <label>Jurisdiction</label>
                    <input type="text" id="jurisdiction" name="jurisdiction" class="form-control required" autocomplete="off" title="Please enter jurisdiction" @if($legal->jurisdiction != NULL) value="{!! $legal->jurisdiction !!}" @endif placeholder="Please enter jurisdiction">
                </div>
                <div class="col-12 form-group">
                    <label>If a criminal matter. Any previous convictions? If yes, please list charges and dates convicted of same.<span class="red-star">*</span><i class="icon-info-circle" data-container="body" data-trigger="hover" data-toggle="popover" data-placement="top" data-content="If a criminal matter. Any previous convictions? If yes, please list charges and dates convicted of same." data-original-title="" title="" aria-describedby="popover509230" style="font-size: 18px;width: 14px;color: #F08021;vertical-align:bottom;height:24px;"></i></label>
                    <textarea name="criminalmatter_detail" minlength="200" id="criminalmatter_detail" class="form-control required"
                    placeholder="Enter criminal matter detail">@if($legal->criminalmatter_detail != NULL){!! trim($legal->criminalmatter_detail) !!}@else {{ "N/A" }} @endif</textarea>
                </div>
                <div class="col-12 form-group">
                    <label>If a criminal matter. What is the charge?</label>
                    <textarea name="criminalmatter_charge" minlength="200" id="criminalmatter_charge" class="form-control required"
                    placeholder="Enter criminal matter charge details">@if($legal->criminalmatter_charge != NULL){!! trim($legal->criminalmatter_charge) !!}@else {{ "N/A" }} @endif</textarea>
                </div>

                <br>
                <div class="kt-portlet__foot kt-portlet__foot--solid">
                    <div class="kt-form__actions">
                        <div class="row">
                            <div class="col-3"></div>
                            <div class="col-9">
                                @if($claims->status == 'Pending')
                                    @can('claim-edit')
                                        <button type="submit"  id="sbtBtn" value="Submit" class="btn btn-brand" >Update</button>
                                        <button class="btn btn-brand" type="button" id="loadBtn" style="display:none" >
                                        <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Loading... </button>
                                    @endcan
                                @endif
                                <a class="btn btn-secondary" href="{{ route('admin.claims.index') }}" >Cancel</a>
                            </div>
                        </div>
                    </div>
               </div>

            </form>
        </div>
    </div>
</div>
<!--end::Portlet-->


