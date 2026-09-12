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

<table class="table table-striped m-table">
    <tbody>
        {{-- <tr width="100%">
            <th width="50%">BROKER/AGENT</th>
            <td width="50%">{{$coverageClaimData->broker_agent}}</td>
        </tr> --}}
        {{-- <tr width="100%">
            <th width="50%">POLICY NUMBER</th>
            <td width="50%">{{$coverageClaimData->policy_no}}</td>
        </tr>
        <tr width="100%">
            <th width="50%">ID number</th>
            <td width="50%">{{$coverageClaimData->id_number}}</td>
        </tr> --}}
    </tbody>
</table>

{{-- <h3>Insured:</h3>
<table class="table table-striped m-table">
    <tbody>
        <tr width="100%">
            <th width="50%">Name and occupation</th>
            <td width="50%">{{$coverageClaimData->policy_no}}</td>
        </tr>
        <tr width="100%">
            <th width="50%">Address and (day) telephone number</th>
            <td width="50%">{{$coverageClaimData->address_tele_no}}</td>
        </tr>
    </tbody>
</table> --}}



<h3>Loss/damage occurrence:</h3>
<table class="table table-striped m-table">
    <tbody>
        {{-- <tr width="100%">
            <th width="50%">Date and time of loss/damage</th>
            <td width="50%">{{$coverageClaimData->date_time_of_loss_damage}}</td>
        </tr> --}}
        <tr width="100%">
            <th width="50%">When was loss/damage discovered?</th>
            <td width="50%">{{$coverageClaimData->loss_damage_discovered}}</td>
        </tr>
    </tbody>
</table>



<h3>Loss/damage place:</h3>
<table class="table table-striped m-table">
    <tbody>
        <tr width="100%">
            <th width="50%">Place where loss/damage occurred</th>
            <td width="50%">{{$coverageClaimData->loss_damage_occurred}}</td>
        </tr>
        <tr width="100%">
            <th width="50%">Were premises occupied? By whom?</th>
            <td width="50%">{{$coverageClaimData->premises_occupied}}</td>
        </tr>
        <tr width="100%">
            <th width="50%">If not occupied, when last occupied?</th>
            <td width="50%">{{$coverageClaimData->last_occupied}}</td>
        </tr>
        <tr width="100%">
            <th width="50%">Purpose of occupation</th>
            <td width="50%">{{$coverageClaimData->purpose_of_occupation}}</td>
        </tr>
    </tbody>
</table>



<h3>Cause of loss/damage:</h3>
<table class="table table-striped m-table">
    <tbody>
        <tr width="100%">
            <th width="50%">What is the nature of your interruption?</th>
            <td width="50%">{{$coverageClaimData->nature_interruption}}</td>
        </tr>
        <tr width="100%">
            <th width="50%">Please give details of and estimated amount of loss for each item to beclaimed.</th>
            <td width="50%">{{$coverageClaimData->loss_for_each_item}}</td>
        </tr>
    </tbody>
</table>



<h3>Previous loss/damage:</h3>
<table class="table table-striped m-table">
    <tbody>
        <tr width="100%">
            <th width="50%">Have you previously suffered loss/damage?</th>
            <td width="50%">{{$coverageClaimData->previously_suffered_loss}}</td>
        </tr>
        <tr width="100%">
            <th width="50%">If so, give details</th>
            <td width="50%">{{$coverageClaimData->give_details}}</td>
        </tr>
        <tr width="100%">
            <th width="50%">If insured, provide name of insurer</th>
            <td width="50%">{{$coverageClaimData->name_of_insurer}}</td>
        </tr>
    </tbody>
</table>



<h3>Police:</h3>
<table class="table table-striped m-table">
    <tbody>
        <tr width="100%">
            <th width="50%">Police reference number and station and date reported</th>
            <td width="50%">{{$coverageClaimData->reference_no_station}}</td>
        </tr>
    </tbody>
</table>



<h3>Other interest:</h3>
<table class="table table-striped m-table">
    <tbody>
        <tr width="100%">
            <th width="50%">Has any other party have an interest in the insured property, e.g. credit agreement?</th>
            <td width="50%">{{$coverageClaimData->interest_insured_property}}</td>
        </tr>
    </tbody>
</table>



<h3>Other insurance:</h3>
<table class="table table-striped m-table">
    <tbody>
        <tr width="100%">
            <th width="50%">Is there any other insurance covering this loss/damage?</th>
            <td width="50%">{{ $coverageClaimData->other_insurance_covering }}</td>
        </tr>
        <tr width="100%">
            <th width="50%">If so, give name of insurer</th>
            <td width="50%">{{ $coverageClaimData->give_name_insurer }}</td>
        </tr>
    </tbody>
</table>



<h3>Value:</h3>
<table class="table table-striped m-table">
    <tbody>
        <tr width="100%">
            <th width="50%">Estimated total value of all the property insured under the policy</th>
            <td width="50%">{{ $coverageClaimData->value_all_property }}</td>
        </tr>
        <tr width="100%">
            <th width="50%">When last valued?</th>
            <td width="50%">{{ $coverageClaimData->when_last_valued }}</td>
        </tr>
    </tbody>
</table>



{{-- <h3>Payment method:</h3>
<table class="table table-striped m-table">
    <tbody>
        <tr width="100%">
            <th width="50%">Name of bank</th>
            <td width="50%">{{ $coverageClaimData->name_of_bank }}</td>
        </tr>
        <tr width="100%">
            <th width="50%">Branch</th>
            <td width="50%">{{ $coverageClaimData->branch }}</td>
        </tr>
        <tr width="100%">
            <th width="50%">Name of account</th>
            <td width="50%">{{ $coverageClaimData->name_of_account }}</td>
        </tr>
        <tr width="100%">
            <th width="50%">Account number</th>
            <td width="50%">{{ $coverageClaimData->account_number }}</td>
        </tr>
    </tbody>
</table> --}}

