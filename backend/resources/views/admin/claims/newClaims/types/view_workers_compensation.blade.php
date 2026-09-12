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
{{-- <h3>EMPLOYER:</h3>
<table class="table table-striped m-table">
    <tbody>
        <tr width="100%">
            <th width="50%">Name</th>
            <td width="50%">{{ $coverageClaimData->employer_name }}</td>
        </tr>
        <tr  width="100%">
            <th width="50%">Policy Number</th>
            <td width="50%">{{ $coverageClaimData->employer_policy_no }}</td>
        </tr>
        <tr width="100%">
            <th width="50%">Address</th>
            <td width="50%">{{ $coverageClaimData->employer_Address }}</td>
        </tr>
        <tr width="100%">
            <th width="50%">Phone No</th>
            <td width="50%">{{ $coverageClaimData->employer_phone_no }}</td>
        </tr>
        <tr width="100%">
            <th width="50%">Trade or Business</th>
            <td width="50%">{{ $coverageClaimData->employer_trade_or_business }}</td>
        </tr>
    </tbody>
</table> --}}

<h3>THE INJURED PERSON:</h3>
<table class="table table-striped m-table">
    <tbody>
        <tr width="100%">
            <th width="50%">Name</th>
            <td width="50%">{{ $coverageClaimData->injured_name }}</td>
        </tr>
        <tr width="100%">
            <th width="50%">Age</th>
            <td width="50%">{{ $coverageClaimData->injured_age }}</td>
        </tr>
        <tr width="100%">
            <th width="50%">Address</th>
            <td width="50%">{{ $coverageClaimData->injured_address }}</td>
        </tr>
        <tr width="100%">
            <th width="50%">Status</th>
            <td width="50%">{{$coverageClaimData->injured_status}}</td>
        </tr>
        <tr width="100%">
            <th width="50%">Normal Occupation</th>
            <td width="50%">{{ $coverageClaimData->injured_occupation }}</td>
        </tr>
        <tr width="100%">
            <th width="50%">Nationality</th>
            <td width="50%">{{ $coverageClaimData->injured_nationality }}</td>
        </tr>
        <tr width="100%">
            <th width="50%">Period of service</th>
            <td width="50%">{{ $coverageClaimData->injured_service_period }}</td>
        </tr>
    </tbody>
</table>

<!-- <h3>EMPLOYER:</h3> -->
<table class="table table-striped m-table">
    <tbody >
        <tr width="100%">
            <th width="50%">Is he/she in your direct employ?</th>
            <td width="50%">
                @if ($coverageClaimData->your_direct_employ == 1)
                    Yes
                @else
                    No
                @endif
            </td>
        </tr>
        @if ($coverageClaimData->your_direct_employ == 1)
            <tr  width="100%">
                <th width="50%">If not, give name and address of Contractor.</th>
                <td width="50%">{{ $coverageClaimData->address_of_contractor }}</td>
            </tr>
        @endif
        {{-- <tr width="100%">
            <th width="50%">State fully the work upon which he/she was engage at the time of the accident</th>
            <td width="50%">{{ $coverageClaimData->time_of_accident }}</td>
        </tr> --}}
    </tbody>
</table>

<h3>THE ACCIDENT:</h3>
<table class="table table-striped m-table">
    <tbody>
        <tr width="100%">
            <th width="50%">Date</th>
            <td width="50%">{{ $coverageClaimData->date }}</td>
        </tr>
        <tr width="100%">
            <th width="50%">Time</th>
            <td width="50%">{{ $coverageClaimData->time }}</td>
        </tr>
        <tr width="100%">
            <th width="50%">Place</th>
            <td width="50%">{{ $coverageClaimData->place }}</td>
        </tr>
        {{-- <tr width="100%">
            <th width="50%">Date the injured person ceased work</th>
            <td width="50%">{{ $coverageClaimData->injured_person_ceased_work }}</td>
        </tr> --}}
        <tr width="100%">
            <th width="50%">How did the accident occur?</th>
            <td width="50%">{{ $coverageClaimData->how_accident_occur }}</td>
        </tr>
        <tr width="100%">
            <th width="50%">When and whom did he/she first report the accident? </th>
            <td width="50%">{{ $coverageClaimData->first_report_accident }}</td>
        </tr>
        {{-- <tr width="100%">
            <th width="50%">If accident happened in connection with any machinery give name of machine and state part causing accident </th>
            <td width="50%">{{ $coverageClaimData->machine_state_part_causing }}</td>
        </tr> --}}
        {{-- <tr width="100%">
            <th width="50%">State names of any witness </th>
            <td width="50%">{{ $coverageClaimData->state_names_witness }}</td>
        </tr> --}}
        {{-- <tr width="100%">
            <th width="50%">State the nature of the injuries  </th>
            <td width="50%">{{ $coverageClaimData->state_nature_injuries }}</td>
        </tr> --}}
        {{-- <tr width="100%">
            <th width="50%"> Was he/she under the influence of drugs or drink or was he/she guilty of any misconduct or breach of orders or rules? </th>
            <td width="50%">{{ $coverageClaimData->influence_drugs_or_drink }}</td>
        </tr> --}}
        {{-- <tr width="100%">
            <th width="50%">If so, please explain fully?  </th>
            <td width="50%">{{ $coverageClaimData->please_explain }}</td>
        </tr> --}}
        {{-- <tr width="100%">
            <th width="50%"> Was the accident due to anyone's negligence?  </th>
            <td width="50%">{{ $coverageClaimData->anyone_negligence }}</td>
        </tr> --}}
        {{-- <tr width="100%">
            <th width="50%"> If so, give particulars.  </th>
            <td width="50%">{{ $coverageClaimData->give_particulars }}</td>
        </tr> --}}
        {{-- <tr width="100%">
            <th width="50%">Is he/she able to perform any part of his/her duties?  </th>
            <td width="50%">{{ $coverageClaimData->perform_any_part_duties }}</td>
        </tr> --}}
        <tr width="100%">
            <th width="50%">What is the probable period of disablement in your opinion?  </th>
            <td width="50%">{{ $coverageClaimData->period_of_disablement }}</td>
        </tr>
    </tbody>
</table>
