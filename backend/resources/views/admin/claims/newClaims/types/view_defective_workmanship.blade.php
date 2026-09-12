<h3>DETAILS OF THE ACCIDENT/INCIDENT</h3>
@if ($dw != null)
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
        <th>Location of accident/ incident</th>
        <td>
            {!! $dw->location_of_accident !!}
        </td>
    </tr>
    <tr>
        <th>Accident Date Time</th>
        <td>{!! $dw->accident_date_time !!}</td>
    </tr>
    <tr>
        <th>Owners Name</th>
        <td>
            {!! $dw->owners_name !!}
        </td>
    </tr>
    <tr>
        <th>Telephone number</th>
        <td>
           {!! $dw->telephone_number !!}
        </td>
    </tr>
    <tr>
        <th>Mobile number</th>
        <td>{!! $dw->mobile_number !!}</td>
    </tr>

    <tr>
        <th>Address</th>
        <td>{!! $dw->address !!}</td>
    </tr>

    </tbody>
</table>
@endif


<div style="border:1px solid lightslategray;" class="kt-separator kt-separator--space-lg kt-separator--border-dashed"></div>

<h3>CLAIMANTS VEHICLE</h3>
@if ($dw != null)
<table class="table table-striped m-table">
    <tbody>
    <tr>
        <th>Make</th>
        <td>
            {!! $dw->make !!}
        </td>
    </tr>
    <tr>
        <th>Model</th>
        <td>{!! $dw->model !!}</td>
    </tr>
    <tr>
        <th>Registration</th>
        <td>
            {!! $dw->registration !!}
        </td>
    </tr>
    <tr>
        <th>Is the vehicle drivable?</th>
        <td>
           @if ($dw->vehicle_drivable == 1)
               Yes
           @else
               No
           @endif
        </td>
    </tr>

    </tbody>
</table>
@endif
<div style="border:1px solid lightslategray;" class="kt-separator kt-separator--space-lg kt-separator--border-dashed"></div>

<h3>INCIDENT DETAILS</h3>
@if ($dw != null)
<table class="table table-striped m-table">
    <tbody>
    <tr>
        <th>Was the vehicle handed to the claimant?</th>
        <td>
            @if ($dw->vehicle_handed_claimant == 1)
                Yes
            @else
                No
            @endif
        </td>
    </tr>
    <tr>
        <th>When was the vehicle handed?</th>
        <td>{!! $dw->when_vehicle_handed !!}</td>
    </tr>
    <tr>
        <th>Date and allegations received from claimant</th>
        <td>
            {!! $dw->allegations_received !!}
        </td>
    </tr>
    <tr>
        <th>Claim Number</th>
        <td>{!! $claims->claim_number !!}</td>
    </tr>

    </tbody>
</table>
@endif
