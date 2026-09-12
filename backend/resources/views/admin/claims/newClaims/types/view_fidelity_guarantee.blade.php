<h3>Give the name of defaulting employees and their respective positions:</h3>
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
        <th>Defaulting Employees Name & Position</th>
        @if(isset($fg->defaulting_employees_name))
            <td>
                <ul>
                    @foreach ( $fg->defaulting_employees_name as $key => $name )
                        <li>{!! $key !!} : {!! $name !!}</li>
                    @endforeach
                </ul>
            </td>
        @else
            <td>-</td>
        @endif
    </tr>
    <tr>
        <th>Have the employees been involved in or been suspected of any previous loss?</th>
        <td>
            @if($fg->employees_been_involved == 1)
                Yes
            @else
                No
            @endif
        </td>
    </tr>
    {{-- <tr>
        <th>Give full details of the circumstances of the loss and how it was discovered</th>
        <td>
            {!! $fg->circumstances !!}
        </td>
    </tr> --}}
    <tr>
        <th>Claim Number</th>
        <td>{!! $claims->claim_number !!}</td>
    </tr>

    </tbody>
</table>
