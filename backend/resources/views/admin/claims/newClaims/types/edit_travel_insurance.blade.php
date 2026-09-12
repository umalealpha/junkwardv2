<input type="hidden" name="travel_ins_id" value="{{ $travelIns->id }}" />

<h5>Claimant Details:</h5>
<hr>
<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Title</label>
    <div class="col-4">
        <label class="kt-radio" style="margin-left: 10px;">
            <input type="radio" name="title" {{ $travelIns->title == "Mr" ? 'checked' : '' }}  class="form-control title"  value="Mr">Mr<span></span>
        </label>
        <label class="kt-radio" style="margin-left: 10px;">
            <input type="radio" name="title" {{ $travelIns->title == "Mrs" ? 'checked' : '' }}  class="form-control  title"  value="Mrs">Mrs<span></span>
        </label>
        <label class="kt-radio" style="margin-left: 10px;">
            <input type="radio" name="title" {{ $travelIns->title == "Miss" ? 'checked' : '' }}  class="form-control  title"  value="Miss">Miss<span></span>
        </label>
        <label class="kt-radio" style="margin-left: 10px;">
            <input type="radio" name="title" {{ $travelIns->title == "Ms" ? 'checked' : '' }}  class="form-control  title"  value="Ms">Ms<span></span>
        </label>
        <label class="kt-radio" style="margin-left: 10px;">
            <input type="radio" name="title" {{ $travelIns->title == "Other" ? 'checked' : '' }}  class="form-control  title"  value="Other">Other<span></span>
        </label>
    </div>
    <div class="col-4">
         <input type="text" class="form-control" name="other_title" value="{{$travelIns->other_title}}" placeholder="Other">
    </div>
</div>

<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Surname</label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <input type="text" class="form-control" name="surname" value="{{$travelIns->surname}}" placeholder="Enter surname">
         </div>
    </div>
 </div>

 <div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Forename(s)</label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <input type="text" class="form-control " name="forename" value="{{$travelIns->forename}}" placeholder="Enter forename">
         </div>
    </div>
 </div>

 <div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Date of Birth</label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <input type="date" class="form-control " name="dob" value="{{$travelIns->dob}}" placeholder="Enter Date of Birth">
         </div>
    </div>
 </div>

 <div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Passport No.</label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <input type="text" class="form-control " name="passport_no" value="{{$travelIns->passport_no}}" placeholder="Enter passport no.">
         </div>
    </div>
 </div>

 <div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Nationality</label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <input type="text" class="form-control " name="nationality" value="{{$travelIns->nationality}}" placeholder="Enter nationality">
         </div>
    </div>
 </div>

 <div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Telephone</label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <input type="text" class="form-control " name="telephone" value="{{$travelIns->telephone}}" placeholder="Enter telephone">
         </div>
    </div>
 </div>

 <div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Post Code</label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <input type="text" class="form-control " name="post_code" value="{{$travelIns->post_code}}" placeholder="Enter post code">
         </div>
    </div>
 </div>

 <div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Mobile</label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <input type="text" class="form-control " name="mobile" value="{{$travelIns->mobile}}" placeholder="Enter mobile">
         </div>
    </div>
 </div>

 <div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Email</label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <input type="email" class="form-control " name="email" value="{{$travelIns->email}}" placeholder="Enter email">
         </div>
    </div>
 </div>

 <div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Home Address</label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <textarea type="text" class="form-control" name="home_address" value=""  placeholder="">{{$travelIns->home_address}}</textarea>
         </div>
    </div>
 </div>

 <h5>Travel Insurance Policy and Journey Details:</h5>
 <hr>
 <div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Policy Number</label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <input type="text" class="form-control " name="policy_number" value="{{$travelIns->policy_number}}" placeholder="Enter policy number">
         </div>
    </div>
 </div>

 <div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Issued by (Insurance Company)</label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <input type="text" class="form-control " name="issued_by" value="{{$travelIns->issued_by}}" placeholder="Enter issued by">
         </div>
    </div>
 </div>

 <div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Issued on</label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <input type="text" class="form-control" name="issued_on" value="{{$travelIns->issued_on}}" placeholder="Enter issued on">
         </div>
    </div>
 </div>

 <div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Valid from</label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <input type="date" class="form-control" name="valid_from" value="{{$travelIns->valid_from}}" placeholder="Enter valid from">
         </div>
    </div>
 </div>

 <div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Valid to</label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <input type="date" class="form-control " name="valid_to" value="{{$travelIns->valid_to}}" placeholder="Enter valid to">
         </div>
    </div>
 </div>

 <h5>Bank Details (for Claim Reimbursement Purposes only):</h5>
 <hr>
 <div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Beneficiary (If different than the Insured)</label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <input type="text" class="form-control " name="beneficiary" value="{{$travelIns->beneficiary}}" placeholder="Enter beneficiary">
         </div>
    </div>
 </div>

 <div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Bank Name</label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <input type="text" class="form-control " name="bank_name" value="{{$travelIns->bank_name}}" placeholder="Enter bank name">
         </div>
    </div>
 </div>

 <div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Bank Address</label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <input type="text" class="form-control " name="bank_address" value="{{$travelIns->bank_address}}" placeholder="Enter bank address">
         </div>
    </div>
 </div>

 <div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Account Number</label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <input type="text" class="form-control " name="account_number" value="{{$travelIns->account_number}}" placeholder="Enter account number">
         </div>
    </div>
 </div>

 <div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">IBAN</label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <input type="text" class="form-control " name="iban" value="{{$travelIns->iban}}" placeholder="Enter IBAN">
         </div>
    </div>
 </div>

 <div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">SWIFT CODE</label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <input type="text" class="form-control " name="swift_code" value="{{$travelIns->swift_code}}" placeholder="Enter SWIFT CODE">
         </div>
    </div>
 </div>

 <div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">BIC CODE</label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <input type="text" class="form-control " name="bic_code" value="{{$travelIns->bic_code}}" placeholder="Enter BIC CODE">
         </div>
    </div>
 </div>

 <h5>Do you have any other Insurance Policy?:</h5>
 <hr>
 <div class="form-group row">
    <!-- <label for="example-text-input" class="col-3 col-form-label">IBAN</label> -->
    <div class="col-12">
        <label class="kt-radio" style="margin-left: 10px;">
            <input type="radio" name="other_insurance_policy" {{ $travelIns->other_insurance_policy == "No" ? 'checked' : '' }}  class="form-control other_insurance_policy"  value="No">No<span></span>
        </label>
        <label class="kt-radio" style="margin-left: 10px;">
            <input type="radio" name="other_insurance_policy" {{ $travelIns->other_insurance_policy == "Yes" ? 'checked' : '' }}  class="form-control  other_insurance_policy"  value="Yes">Yes<span> </span>
        </label>
       -  <b>If Yes please complete the information below:</b>
    </div>
 </div>

 <div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Name of the Insurance Company</label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <input type="text" class="form-control " name="name_insurance_company" value="{{$travelIns->name_insurance_company}}" placeholder="Enter Name of the Insurance Company">
         </div>
    </div>
 </div>

 <div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Address</label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <textarea type="text" class="form-control" name="address" value=""  placeholder="Enter Address">{{$travelIns->address}}</textarea>
         </div>
    </div>
 </div>

 <div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Phone Number</label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <input type="text" class="form-control " name="phone_number" value="{{$travelIns->phone_number}}" placeholder="Enter Phone Number">
         </div>
    </div>
 </div>

 {{-- <div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Policy Number</label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <input type="text" class="form-control " name="policy_number" value="" placeholder="Enter Policy Number">
         </div>
    </div>
 </div> --}}

<h5>Type of Refund:</h5>
<div class="form-group row">
        <div class="col-4">
            <label class="kt-radio" style="margin-left: 10px;">
                <input type="radio" name="type_of_refund" {{ $travelIns->type_of_refund == "Medical Expenses" ? 'checked' : '' }}  class="form-control type_of_refund"  value="Medical Expenses">Medical Expenses<span></span>
            </label>
        </div>
        <div class="col-4">
            <label class="kt-radio" style="margin-left: 10px;">
                <input type="radio" name="type_of_refund" {{ $travelIns->type_of_refund == "Delayed Luggage" ? 'checked' : '' }}  class="form-control  type_of_refund"  value="Delayed Luggage">Delayed Luggage<span></span>
            </label>
        </div>
        <div class="col-4">
            <label class="kt-radio" style="margin-left: 10px;">
                <input type="radio" name="type_of_refund" {{ $travelIns->type_of_refund == "Emergency Dental Care" ? 'checked' : '' }}  class="form-control  type_of_refund"  value="Emergency Dental Care">Emergency Dental Care<span></span>
            </label>
        </div>
        <div class="col-4">
            <label class="kt-radio" style="margin-left: 10px;">
                <input type="radio" name="type_of_refund" {{ $travelIns->type_of_refund == "Loss of Luggage" ? 'checked' : '' }}  class="form-control  type_of_refund"  value="Loss of Luggage">Loss of Luggage<span></span>
            </label>
        </div>
        <div class="col-4">
            <label class="kt-radio" style="margin-left: 10px;">
                <input type="radio" name="type_of_refund" {{ $travelIns->type_of_refund == "Flight Delay" ? 'checked' : '' }}  class="form-control  type_of_refund"  value="Flight Delay">Flight Delay<span></span>
            </label>
        </div>
        <div class="col-4">
            <label class="kt-radio" style="margin-left: 10px;">
                <input type="radio" name="type_of_refund" {{ $travelIns->type_of_refund == "Loss of Personal Documents" ? 'checked' : '' }}  class="form-control  type_of_refund"  value="Loss of Personal Documents">Loss of Personal Documents<span></span>
            </label>
        </div>
        <div class="col-4">
            <label class="kt-radio" style="margin-left: 10px;">
                <input type="radio" name="type_of_refund" {{ $travelIns->type_of_refund == "Trip Cancellation" ? 'checked' : '' }}  class="form-control  type_of_refund"  value="Trip Cancellation">Trip Cancellation<span></span>
            </label>
        </div>
        <div class="col-4">
            <label class="kt-radio" style="margin-left: 10px;">
                <input type="radio" name="type_of_refund" {{ $travelIns->type_of_refund == "Delayed Departure" ? 'checked' : '' }}  class="form-control  type_of_refund"  value="Delayed Departure">Delayed Departure<span></span>
            </label>
        </div>
        <div class="col-4">
            <label class="kt-radio" style="margin-left: 10px;">
                <input type="radio" name="type_of_refund" {{ $travelIns->type_of_refund == "Curtailment" ? 'checked' : '' }}  class="form-control  type_of_refund"  value="Curtailment">Curtailment<span></span>
            </label>
        </div>
        <input type="text" class="form-control col-12" name="type_of_refund_other" value="{{$travelIns->type_of_refund_other}}" placeholder="Other. Please specify">
</div>

    <h5>Compulsory Documentation for ALL claims</h5>
    <hr>
    <div class="form-group row">
       <div class="col-6">
            <label class="kt-radio" style="margin-left: 10px;">
                <input type="radio" name="compulsory_doc_all_claims" {{ $travelIns->compulsory_doc_all_claims == "Proof of residence in the Country where the Policy was issued" ? 'checked' : '' }}  class="form-control compulsory_doc_all_claims"  value="Proof of residence in the Country where the Policy was issued">Proof of residence in the Country where the Policy was issued<span></span>
            </label>
        </div>
        <div class="col-6">
            <label class="kt-radio" style="margin-left: 10px;">
                <input type="radio" name="compulsory_doc_all_claims" {{ $travelIns->compulsory_doc_all_claims == "Claim form duly completed" ? 'checked' : '' }}  class="form-control  compulsory_doc_all_claims"  value="Claim form duly completed">Claim form duly completed<span></span>
            </label>
        </div>
        <div class="col-6">
            <label class="kt-radio" style="margin-left: 10px;">
                <input type="radio" name="compulsory_doc_all_claims" {{ $travelIns->compulsory_doc_all_claims == "Copy of Insurance Policy" ? 'checked' : '' }}  class="form-control  compulsory_doc_all_claims"  value="Copy of Insurance Policy">Copy of Insurance Policy<span></span>
        </label>
        </div>
        <div class="col-6">
            <label class="kt-radio" style="margin-left: 10px;">
                <input type="radio" name="compulsory_doc_all_claims" {{ $travelIns->compulsory_doc_all_claims == "Detailed letter explaining the loss" ? 'checked' : '' }}  class="form-control  compulsory_doc_all_claims"  value="Detailed letter explaining the loss">Detailed letter explaining the loss<span></span>
            </label>
        </div>
        <div class="col-6">
            <label class="kt-radio" style="margin-left: 10px;">
                <input type="radio" name="compulsory_doc_all_claims" {{ $travelIns->compulsory_doc_all_claims == "ORIGINAL official Receipts of ALL incurred costs" ? 'checked' : '' }}  class="form-control  compulsory_doc_all_claims"  value="ORIGINAL official Receipts of ALL incurred costs">ORIGINAL official Receipts of ALL incurred costs<span></span>
            </label>
        </div>
        <div class="col-6">
            <label class="kt-radio" style="margin-left: 10px;">
                <input type="radio" name="compulsory_doc_all_claims" {{ $travelIns->compulsory_doc_all_claims == "Copy of insured's passport showing the FIRST page and the exit/entry dates from country of residence" ? 'checked' : '' }}  class="form-control  compulsory_doc_all_claims"  value="Copy of insured's passport showing the FIRST page and the exit/entry dates from country of residence">Copy of insured's passport showing the FIRST page and the exit/entry dates from country of residence<span></span>
            </label>
        </div>
    </div>

    <h5>Claim for MEDICAL EXPENSES / EMERGENCY DENTAL CARE</h5>
    <div class="form-group row">
        <div class="col-6">
            <label class="kt-radio" style="margin-left: 10px;">
                <input type="radio" name="medical_dental_care" {{ $travelIns->medical_dental_care == "Medical report with admission medical clinic" ? 'checked' : '' }}  class="form-control medical_dental_care"  value="Medical report with admission medical clinic">Medical report with admission medical clinic<span></span>
            </label>
        </div>
        <div class="col-6">
            <label class="kt-radio" style="margin-left: 10px;">
                <input type="radio" name="medical_dental_care" {{ $travelIns->medical_dental_care == "Clinical and/or Laboratory Results" ? 'checked' : '' }}  class="form-control  medical_dental_care"  value="Clinical and/or Laboratory Results">Clinical and/or Laboratory Results<span></span>
            </label>
        </div>
        <div class="col-6">
            <label class="kt-radio" style="margin-left: 10px;">
                <input type="radio" name="medical_dental_care" {{ $travelIns->medical_dental_care == "Bank Account Information" ? 'checked' : '' }}  class="form-control  medical_dental_care"  value="Bank Account Information">Bank Account Information<span></span>
            </label>
        </div>
    </div>

    <h5>Claim for DELAYED LUGGAGE</h5>
    <div class="form-group row">
        <div class="col-6">
            <label class="kt-radio" style="margin-left: 10px;">
                <input type="radio" name="claim_delayed_luggage" {{ $travelIns->claim_delayed_luggage == "Property Irregularity Report issued by the Carrier" ? 'checked' : '' }}  class="form-control claim_delayed_luggage"  value="Property Irregularity Report issued by the Carrier">Property Irregularity Report issued by the Carrier<span></span>
            </label>
        </div>
        <div class="col-6">
            <label class="kt-radio" style="margin-left: 10px;">
                <input type="radio" name="claim_delayed_luggage" {{ $travelIns->claim_delayed_luggage == "Incident Report from Client" ? 'checked' : '' }}  class="form-control  claim_delayed_luggage"  value="Incident Report from Client">Incident Report from Client<span></span>
            </label>
        </div>
        <div class="col-6">
            <label class="kt-radio" style="margin-left: 10px;">
                <input type="radio" name="claim_delayed_luggage" {{ $travelIns->claim_delayed_luggage == "Original receipts for basic necessity items bought" ? 'checked' : '' }}  class="form-control  claim_delayed_luggage"  value="Original receipts for basic necessity items bought">Original receipts for basic necessity items bought<span></span>
            </label>
        </div>
    </div>

    <h5>Claim for LOSS OF PERSONAL DOCUMENTS</h5>
    <div class="form-group row">
        <div class="col-6">
            <label class="kt-radio" style="margin-left: 10px;">
                <input type="radio" name="claim_loss_personal_doc" {{ $travelIns->claim_loss_personal_doc == "Statement of Loss (Police report)" ? 'checked' : '' }}  class="form-control claim_loss_personal_doc"  value="Statement of Loss (Police report)">Statement of Loss (Police report)<span></span>
            </label>
        </div>
        <div class="col-6">
            <label class="kt-radio" style="margin-left: 10px;">
                <input type="radio" name="claim_loss_personal_doc" {{ $travelIns->claim_loss_personal_doc == "Receipts of document replacement incurred costs" ? 'checked' : '' }}  class="form-control  claim_loss_personal_doc"  value="Receipts of document replacement incurred costs">Receipts of document replacement incurred costs<span></span>
            </label>
        </div>
    </div>

    <h5>Claim for LOST LUGGAGE</h5>
    <div class="form-group row">
        <div class="col-6">
            <label class="kt-radio" style="margin-left: 10px;">
                <input type="radio" name="claim_lost_luggage" {{ $travelIns->claim_lost_luggage == "Property Irregularity Report issued by the Carrier" ? 'checked' : '' }}  class="form-control claim_lost_luggage"  value="Property Irregularity Report issued by the Carrier">Property Irregularity Report issued by the Carrier<span></span>
            </label>
        </div>
        <div class="col-6">
            <label class="kt-radio" style="margin-left: 10px;">
                <input type="radio" name="claim_lost_luggage" {{ $travelIns->claim_lost_luggage == "Certificate of lost luggage issued by the Carrier" ? 'checked' : '' }}  class="form-control  claim_lost_luggage"  value="Certificate of lost luggage issued by the Carrier">Certificate of lost luggage issued by the Carrier<span></span>
            </label>
        </div>
        <div class="col-6">
            <label class="kt-radio" style="margin-left: 10px;">
                <input type="radio" name="claim_lost_luggage" {{ $travelIns->claim_lost_luggage == "Copy of the Carrier settlement/reimbursement form" ? 'checked' : '' }}  class="form-control  claim_lost_luggage"  value="Copy of the Carrier settlement/reimbursement form">Copy of the Carrier settlement/reimbursement form<span></span>
            </label>
        </div>
        <div class="col-6">
            <label class="kt-radio" style="margin-left: 10px;">
                <input type="radio" name="claim_lost_luggage" {{ $travelIns->claim_lost_luggage == "Incident Report from Client" ? 'checked' : '' }}  class="form-control  claim_lost_luggage"  value="Incident Report from Client">Incident Report from Client<span></span>
            </label>
        </div>
    </div>

    <h5>Claim for TRIP CANCELLATION or TRIP CURTAILMENT</h5>
    <div class="form-group row">
        <div class="col-6">
            <label class="kt-radio" style="margin-left: 10px;">
                <input type="radio" name="claim_trip_cancel" {{ $travelIns->claim_trip_cancel == "List of the services hired for the trip (accommodation, flights, etc...)" ? 'checked' : '' }}  class="form-control claim_trip_cancel"  value="List of the services hired for the trip (accommodation, flights, etc...)">List of the services hired for the trip (accommodation, flights, etc...)<span></span>
            </label>
        </div>
        <div class="col-6">
            <label class="kt-radio" style="margin-left: 10px;">
                <input type="radio" name="claim_trip_cancel" {{ $travelIns->claim_trip_cancel == "Conditions and proof of cancellation of the said services" ? 'checked' : '' }}  class="form-control  claim_trip_cancel"  value="Conditions and proof of cancellation of the said services">Conditions and proof of cancellation of the said services<span></span>
            </label>
        </div>
        <div class="col-6">
            <label class="kt-radio" style="margin-left: 10px;">
                <input type="radio" name="claim_trip_cancel" {{ $travelIns->claim_trip_cancel == "Certificate of non-refundable costs" ? 'checked' : '' }}  class="form-control  claim_trip_cancel"  value="Certificate of non-refundable costs">Certificate of non-refundable costs<span></span>
            </label>
        </div>
        <div class="col-6">
            <label class="kt-radio" style="margin-left: 10px;">
                <input type="radio" name="claim_trip_cancel" {{ $travelIns->claim_trip_cancel == "The payment receipts of the hired services for the trip" ? 'checked' : '' }}  class="form-control  claim_trip_cancel"  value="The payment receipts of the hired services for the trip">The payment receipts of the hired services for the trip<span></span>
            </label>
        </div>
    </div>

    <h5>Claim for DELAYED FLIGHT</h5>
    <div class="form-group row">
        <div class="col-6">
            <label class="kt-radio" style="margin-left: 10px;">
                <input type="radio" name="claim_delayed_flight" {{ $travelIns->claim_delayed_flight == "Certificate Issued by the Carrier" ? 'checked' : '' }}  class="form-control claim_delayed_flight"  value="Certificate Issued by the Carrier">Certificate Issued by the Carrier<span></span>
            </label>
        </div>
        <div class="col-6">
            <label class="kt-radio" style="margin-left: 10px;">
                <input type="radio" name="claim_delayed_flight" {{ $travelIns->claim_delayed_flight == "Copy of original travel ticket" ? 'checked' : '' }}  class="form-control  claim_delayed_flight"  value="Copy of original travel ticket">Copy of original travel ticket<span></span>
            </label>
        </div>
        <div class="col-6">
            <label class="kt-radio" style="margin-left: 10px;">
                <input type="radio" name="claim_delayed_flight" {{ $travelIns->claim_delayed_flight == "Copy of replacement ticket indicating the paid amount" ? 'checked' : '' }}  class="form-control  claim_delayed_flight"  value="Copy of replacement ticket indicating the paid amount">Copy of replacement ticket indicating the paid amount<span></span>
            </label>
        </div>
    </div>







