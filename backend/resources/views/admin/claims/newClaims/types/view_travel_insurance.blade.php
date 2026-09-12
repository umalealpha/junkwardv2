<h3>Claimant Details:</h3>
<table class="table table-striped m-table">
    <tbody>
        <tr>
            <th>Title</th>
            <td>
                {!! $travelIns->title !!} @if (isset($travelIns->other_title)) {!! $travelIns->other_title !!} @endif
            </td>
        </tr>
        <tr>
            <th>Surname</th>
            <td>{!! $travelIns->surname !!}</td>
        </tr>
        <tr>
            <th>Forename(s)</th>
            <td>{!! $travelIns->forename !!}</td>
        </tr>
        <tr>
            <th>Date of Birth</th>
            <td>{!! $travelIns->dob !!}</td>
        </tr>
        <tr>
            <th>Passport No.</th>
            <td>{!! $travelIns->passport_no !!}</td>
        </tr>
        <tr>
            <th>Nationality</th>
            <td>{!! $travelIns->nationality !!}</td>
        </tr>
        <tr>
            <th>Telephone</th>
            <td>{!! $travelIns->telephone !!}</td>
        </tr>
        <tr>
            <th>Post Code</th>
            <td>{!! $travelIns->post_code !!}</td>
        </tr>
        <tr>
            <th>Mobile</th>
            <td>{!! $travelIns->mobile !!}</td>
        </tr>
        <tr>
            <th>Email</th>
            <td>{!! $travelIns->email !!}</td>
        </tr>
        <tr>
            <th>Home Address</th>
            <td>{!! $travelIns->home_address !!}</td>
        </tr>
    </tbody>
</table>
<h3>Travel Insurance Policy and Journey Details:</h3>
<table class="table table-striped m-table">
    <tbody>
        <tr>
            <th>Policy Number</th>
            <td>{{$travelIns->policy_number}}</td>
        </tr>
        <tr>
            <th>Issued by (Insurance Company)</th>
            <td>{{$travelIns->issued_by}}</td>
        </tr>
        <tr>
            <th>Issued on</th>
            <td>{{$travelIns->issued_on}}</td>
        </tr>
        <tr>
            <th>Valid from</th>
            <td>{{$travelIns->valid_from}}</td>
        </tr>
        <tr>
            <th>Valid to</th>
            <td>{{$travelIns->valid_to}}</td>
        </tr>
    </tbody>
</table>
<h3>Bank Details (for Claim Reimbursement Purposes only):</h3>
<table class="table table-striped m-table">
    <tbody>
        <tr>
            <th>Beneficiary (If different than the Insured)</th>
            <td>{{$travelIns->beneficiary}}</td>
        </tr>
        <tr>
            <th>Bank Name</th>
            <td>{{$travelIns->bank_name}}</td>
        </tr>
        <tr>
            <th>Bank Address</th>
            <td>{{$travelIns->bank_address}}</td>
        </tr>
        <tr>
            <th>Account Number</th>
            <td>{{$travelIns->account_number}}</td>
        </tr>
        <tr>
            <th>IBAN</th>
            <td>{{$travelIns->iban}}</td>
        </tr>
        <tr>
            <th>SWIFT CODE</th>
            <td>{{$travelIns->swift_code}}</td>
        </tr>
        <tr>
            <th>BIC CODE</th>
            <td>{{$travelIns->bic_code}}</td>
        </tr>
    </tbody>
</table>

<table class="table table-striped m-table">
    <tbody>
        <tr>
            <th>Do you have any other Insurance Policy?</th>
            <td>{{$travelIns->other_insurance_policy}}</td>
        </tr>
        <tr>
            <th>Name of the Insurance Company</th>
            <td>{{$travelIns->name_insurance_company}}</td>
        </tr>
        <tr>
            <th>Address</th>
            <td>{{$travelIns->address}}</td>
        </tr>
        <tr>
            <th>Phone Number</th>
            <td>{{$travelIns->phone_number}}</td>
        </tr>
        {{-- <tr>
            <th>Policy Number</th>
            <td>{{$travelIns->other_title}}</td>
        </tr> --}}
    </tbody>
</table>

<table class="table table-striped m-table">
    <tbody>
        <tr>
            <th>Type of Refund</th>
            <td>{{$travelIns->type_of_refund}} @if (isset($travelIns->type_of_refund_other)) {{$travelIns->type_of_refund_other}} @endif</td>
        </tr>
        <tr>
            <th>Compulsory Documentation for ALL claims</th>
            <td>{{$travelIns->compulsory_doc_all_claims}}</td>
        </tr>
        <tr>
            <th>Claim for MEDICAL EXPENSES / EMERGENCY DENTAL CARE</th>
            <td>{{$travelIns->medical_dental_care}}</td>
        </tr>
        <tr>
            <th>Claim for DELAYED LUGGAGE</th>
            <td>{{$travelIns->claim_delayed_luggage}}</td>
        </tr>
        <tr>
            <th>Claim for LOSS OF PERSONAL DOCUMENTS</th>
            <td>{{$travelIns->claim_loss_personal_doc}}</td>
        </tr>
        <tr>
            <th>Claim for LOST LUGGAGE</th>
            <td>{{$travelIns->claim_lost_luggage}}</td>
        </tr>
        <tr>
            <th>Claim for TRIP CANCELLATION or TRIP CURTAILMENT</th>
            <td>{{$travelIns->claim_trip_cancel}}</td>
        </tr>
        <tr>
            <th>Claim for DELAYED FLIGHT</th>
            <td>{{$travelIns->claim_delayed_flight}}</td>
        </tr>
        <tr>
            <th>Claim Number</th>
            <td>{!! $claims->claim_number !!}</td>
        </tr>
    </tbody>
</table>

