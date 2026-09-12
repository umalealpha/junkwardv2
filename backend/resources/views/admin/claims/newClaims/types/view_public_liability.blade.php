<h3>Give the name of defaulting employees and their respective positions:</h3>
<table class="table table-striped m-table">
    <tbody>
        <tr  width="100%">
            <th  width="50%">Claim sub-type</th>
            @foreach($claimSubType as $type)
                @if ($newclaim->claim_sub_type_id == $type->id)
                    <td  width="50%">{!! $type->value  !!}</td>
                @endif
            @endforeach
        </tr>
    </tbody>
</table>

<h3>INSURED'S DETAILS:</h3>
<table class="table table-striped m-table">
    <tbody>
        <tr width="100%">
            <th width="50%">Name</th>
            <td width="50%">{{$coverageClaimData->insured_name}}</td>
        </tr>
        <tr width="100%">
            <th width="50%">Business or Trading name</th>
            <td width="50%">{{$coverageClaimData->insured_treding_name}}</td>
        </tr>
        <tr width="100%">
            <th width="50%">Postal Address</th>
            <td width="50%">{{$coverageClaimData->insured_postal_address}}</td>
        </tr>
        <tr width="100%">
            <th width="50%">Email address</th>
            <td width="50%">{{$coverageClaimData->insured_email}}</td>
        </tr>
        <tr width="100%">
            <th width="50%">Telephone no</th>
            <td width="50%">{{$coverageClaimData->insured_telephone_no}}</td>
        </tr>
        <tr width="100%">
            <th width="50%">Facsimile</th>
            <td width="50%">{{$coverageClaimData->insured_facsimile}}</td>
        </tr>
        <tr width="100%">
            <th width="50%">Mobile no</th>
            <td width="50%">{{$coverageClaimData->insured_mobile_no}}</td>
        </tr>
    </tbody>
</table>


<h3>DETAILS OF THE ACCIDENT/INCIDENT:</h3>
<table class="table table-striped m-table">
    <tbody>
        <tr width="100%">
            <th width="50%">Date</th>
            <td width="50%">{{$coverageClaimData->accident_date}}</td>
        </tr>
        <tr width="100%">
            <th width="50%">Time</th>
            <td width="50%">{{$coverageClaimData->accident_time}}</td>
        </tr>
        <tr width="100%">
            <th width="50%">Location of accident/ incident</th>
            <td width="50%">{{$coverageClaimData->accident_incident}}</td>
        </tr>
        <tr width="100%">
            <th width="50%">Please provide details of damaged property and / or injuries suffered</th>
            <td width="50%">{{$coverageClaimData->accident_injuries}}</td>
        </tr>
        <tr width="100%">
            <th width="50%">Have you admitted responsibility/ liability for the incident?</th>
            <td width="50%">{{$coverageClaimData->accident_liability}}</td>
        </tr>
        <tr width="100%">
            <th width="50%">Does the claim involve a product that you manufacture or services provided to another person?</th>
            <td width="50%">{{$coverageClaimData->accident_person}}</td>
        </tr>
        <tr width="100%">
            <th width="50%">Were emergency services such as ambulance, police or fire brigade contacted?</th>
            <td width="50%">{{$coverageClaimData->accident_contacted}}</td>
        </tr>
    </tbody>
</table>


<h3>If yes please provide details and attach reports:</h3>
<p>Please give the following information about the job at which the accident occurred</p>
<table class="table table-striped m-table">
    <tbody>
        <tr width="100%">
            <th width="50%">(a) Are you the head contractor? If not, who is?</th>
            <td width="50%">{{$coverageClaimData->attach_contractor}}</td>
        </tr>
        <tr width="100%">
            <th width="50%">(b) Was anyone other than yourself or employee involved?</th>
            <td width="50%">{{$coverageClaimData->attach_employee}}</td>
        </tr>
        <tr width="100%">
            <th width="50%">If so, please give names and addresses and state by whom employed</th>
            <td width="50%">{{$coverageClaimData->attach_employed}}</td>
        </tr>
        <tr width="100%">
            <th width="50%">Do you think that (i) you or any of your employee (s) was to blame?</th>
            <td width="50%">{{$coverageClaimData->attach_blame}}</td>
        </tr>
        <tr width="100%">
            <th width="50%">(ii)Has any other accident ever occurred to any person, or damage been done under similar circumstances at the same place?</th>
            <td width="50%">{{$coverageClaimData->attach_circumstances}}</td>
        </tr>
        <tr width="100%">
            <th width="50%">Was there any damage to property?</th>
            <td width="50%">{{$coverageClaimData->attach_property}}</td>
        </tr>
        <tr width="100%">
            <th width="50%">If so, please give details</th>
            <td width="50%">{{$coverageClaimData->attach_details}}</td>
        </tr>
        <tr width="100%">
            <th width="50%">Name and address of the property owner</th>
            <td width="50%">{{$coverageClaimData->attach_owner}}</td>
        </tr>
        <tr width="100%">
            <th width="50%">Damage</th>
            <td width="50%">{{$coverageClaimData->attach_damage}}</td>
        </tr>
    </tbody>
</table>


<h3>DETAILS OF PARTY OR PARTIES MAKING CLAIM AGAINST YOU:</h3>
<table class="table table-striped m-table">
    <tbody>
        <tr width="100%">
            <th width="50%">Name</th>
            <td width="50%">{{$coverageClaimData->claim_name}}</td>
        </tr>
        <tr width="100%">
            <th width="50%">Telephone</th>
            <td width="50%">{{$coverageClaimData->claim_telephone}}</td>
        </tr>
        <tr width="100%">
            <th width="50%">Mobile no</th>
            <td width="50%">{{$coverageClaimData->claim_mobile}}</td>
        </tr>
        <tr width="100%">
            <th width="50%">Postal address</th>
            <td width="50%">{{$coverageClaimData->claim_postal}}</td>
        </tr>
        <tr width="100%">
            <th width="50%">Solicitor's name</th>
            <td width="50%">{{$coverageClaimData->claim_solicitor}}</td>
        </tr>
    </tbody>
</table>


<h3>1. WITNESSES:</h3>
<table class="table table-striped m-table">
    <tbody>
        <tr width="100%">
            <th width="50%">Name</th>
            <td width="50%">{{$coverageClaimData->witness1_name}}</td>
        </tr>
        <tr width="100%">
            <th width="50%">Telephone no</th>
            <td width="50%">{{$coverageClaimData->witness1_telephone}}</td>
        </tr>
        <tr width="100%">
            <th width="50%">Mobile no</th>
            <td width="50%">{{$coverageClaimData->witness1_mobile}}</td>
        </tr>
        <tr width="100%">
            <th width="50%">Postal address</th>
            <td width="50%">{{$coverageClaimData->witness1_postal}}</td>
        </tr>
        <tr width="100%">
            <th width="50%">Relationship (e.g. employee, family, friend)</th>
            <td width="50%">{{$coverageClaimData->witness1_relationship}}</td>
        </tr>
    </tbody>
</table>


<h3>2. WITNESSES:</h3>
<table class="table table-striped m-table">
    <tbody>
        <tr width="100%">
            <th width="50%">Name</th>
            <td width="50%">{{$coverageClaimData->witness2_name}}</td>
        </tr>
        <tr width="100%">
            <th width="50%">Telephone no</th>
            <td width="50%">{{$coverageClaimData->witness2_telephone}}</td>
        </tr>
        <tr width="100%">
            <th width="50%">Mobile no</th>
            <td width="50%">{{$coverageClaimData->witness2_mobile}}</td>
        </tr>
        <tr width="100%">
            <th width="50%">Postal address</th>
            <td width="50%">{{$coverageClaimData->witness2_postal}}</td>
        </tr>
        <tr width="100%">
            <th width="50%">Relationship (e.g. employee, family, friend)</th>
            <td width="50%">{{$coverageClaimData->witness2_relationship}}</td>
        </tr>
        <tr width="100%">
            <th width="50%">Was there any damage to property?</th>
            <td width="50%">{{$coverageClaimData->witness2_damage}}</td>
        </tr>
    </tbody>
</table>

