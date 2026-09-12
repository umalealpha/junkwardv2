<table class="table table-striped m-table">
    <tbody>
    <tr>
        <th>Claim sub-type</th>
        @foreach($claimSubType as $type)
            @if ($newclaim->claim_sub_type_id == $type->id)
                <td>{!! $type->value  !!}</td>
            @endif
        @endforeach
    </tr>
    {{-- <tr>
        <th>What is the nature of your interruption?</th>
        <td>
            {!! $bi->nature_of_interruption !!}
        </td>
    </tr> --}}
    {{-- <tr>
        <th>Please give details of and estimated amount of loss for each item to beclaimed</th>
        <td>{!! $bi->details_and_estimated_amount_of_loss !!}</td>
    </tr> --}}
    <tr>
        <th>Have you previously suffered loss/damage?</th>
        <td>
            @if ($bi->previously_suffered_loss == 1)
                Yes
            @else
                No
            @endif
        </td>
    </tr>
    <tr>
        <th>Has any other party have an interest in the insured property, e.g. credit agreement?</th>
        <td>
            {!! $bi->other_party_interest !!}
        </td>
    </tr>
    <tr>
        <th>Is there any other insurance covering this loss/damage?</th>
        <td>
            @if($bi->other_insurance_covering == 1)
                Yes
            @else
                No
            @endif
        </td>
    </tr>
    <tr>
        <th>Claim Number</th>
        <td>{!! $claims->claim_number !!}</td>
    </tr>
    </tbody>
</table>
