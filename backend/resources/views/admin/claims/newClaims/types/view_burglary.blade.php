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
    <tr>
        <th>Address of premises where theft occurred</th>
        <td>
            {!! isset($burglary->address_of_premises) ? $burglary->address_of_premises : '' !!}
        </td>
    </tr>
    {{-- <tr>
        <th>Brief description of incident</th>
        <td>{!! $burglary->description_of_incident !!}</td>
    </tr> --}}
    <tr>
        <th>Date the police were advised of loss</th>
        <td>
            {!! isset($burglary->date_time_police_advised) ? $burglary->date_time_police_advised : '' !!}
        </td>
    </tr>
    <tr>
        <th>Was anyone at the premises during the burglary? If yes, provide details</th>
        <td>
            @if(isset($burglary->anyone_on_premises) && $burglary->anyone_on_premises == 1)
                Yes
            @else
                No
            @endif
        </td>
    </tr>
    <tr>
        <th>Details in brief</th>
        <td>{!! isset($burglary->anyone_on_premises_brief) ? $burglary->anyone_on_premises_brief : '' !!}</td>
    </tr>

    <tr>
        <th>Is the premises guarded by a watchman?</th>
        <td>
            @if(isset($burglary->guarded_by_watchman) && $burglary->guarded_by_watchman == 1)
                Yes
            @else
                No
            @endif
        </td>
    </tr>
    <tr>
        <th>Were all means of access of the premises properly secured at the time of the theft?</th>
        <td>
            @if(isset($burglary->premises_properly_secured) && $burglary->premises_properly_secured == 1)
                Yes
            @else
                No
            @endif
        </td>
    </tr>
    <tr>
        <th>What was the total value of the contents of your premises at the time of the theft?</th>
        <td>{!! isset($burglary->total_value_contents_of_premises) ? $burglary->total_value_contents_of_premises : '' !!}</td>
    </tr>
    <tr>
        <th>State where the stock books and records were located at the time of the theft</th>
        <td>{!! isset($burglary->stock_books_records_located) ? $burglary->stock_books_records_located : '' !!}</td>
    </tr>
    <tr>
        <th>Claim Number</th>
        <td>{!! $claims->claim_number !!}</td>
    </tr>

    </tbody>
</table>
