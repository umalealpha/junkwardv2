<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <title>{{ $title }}</title>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.0/css/bootstrap.min.css">
         <link rel="license" href="https://www.opensource.org/licenses/mit-license/">
        <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.1/jquery.min.js"></script>
        <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.0/js/bootstrap.min.js"></script>

</head>
<body>
<header>

    <div style="display: inline">
        <table style="table-layout:fixed;width:100%;">
            <tr>
                <td width="50%" style="border: 0px;margin-top: -10%">
                    <img style="float: left" src="{!! asset('images/logo.png') !!}">
                    <br><br><br><br><br>
                    <p style="font-family: SansSerif">
                        Alpha Direct Insurance Co. (Pty) Ltd.<br>
                        Unit 12, Plot 103 G.I.C.P. | P.O. Box 26 ADC<br>
                        Gaborone, Botswana<br>
                        Phone +267 392 8264 | Fax +267 392 8265<br>
                        debtors@alphadirect.co.bw | <a href = "mailto: www.alphadirect.co.bw">www.alphadirect.co.bw</a><br><br>

                    </p></td>
                <td width="40%" style="border: 0px">
                    <p style=" float:right; font-size: 160%; color: #00ACED; font-family: 'Droid Serif' "><br>CLAIM DETAILS NOTE</p><br><br><br><br><br>
                    <p style=" float:right; color: #00ACED; font-family: SansSerif ">CLAIM DATE : {!! \Carbon\Carbon::now()->format('Y-m-d') !!}</p><br>
                </td>
            </tr>
        </table>
    </div>
    <div style="display: inline;">
    </div>
</header>
<h3>Customer Details </h3>
<br>
<br>
    <table class="table">
        <tr class="active">
            <th>Name</th>
            <td>{{ $user->firstName }} {{ $user->lastName }}</td>
            <th>Mobile Number</th>
            <td>{{ $user->cellphone }}</td>
        </tr>
        <tr>
            <th>Email</th>
            <td>{{ $user->email }}</td>
            <th>Address</th>
            <td>{{ $user->profile->address }}</td>
        </tr>
        <tr class="active">
            <th>Omang Id</th>
            <td>{{ $user->profile->omang}}</td>
            <th>Passport</th>
            <td>{{ $user->profile->passport }}</td>
        </tr>
        <tr>
            <th>Date Of Birth</th>
            <td>{{ $user->profile->dob}}</td>
        </tr>
    </table>

<br>
<h3>Vehicle Details</h3>
<br>
<table class="table">
    <tbody>
    <tr class="active">
        <th>Vehicle Number</th>
        <td>{!! $vehicle->vehiclePlate !!}</td>
        <th>Chassis Number(VIN)</th>
        <td>{!! $vehicle->chassisNo !!}</td>
    </tr>

    <tr>
        <th>Odometer</th>
        <td>{!! $vehicle->odometer !!}</td>
        <th>Purpose</th>
        <td>
            @foreach($vehicle_purpose as $purpose)
                @if($purpose->id == $vehicle->purpose)
                    {!! $purpose->value !!}
                @endif
            @endforeach
        </td>
    </tr>
    <tr class="active">
        <th>Condition</th>
        <td>{!! $vehicle->condition !!}</td>
        <th>Year of Manufacturing</th>
        <td>{!! $vehicle->year !!}</td>
    </tr>
    <tr>
        <th>Make</th>
        <td>{!! $vehicle->make !!}</td>
        <th>Model</th>
        <td>{!! $vehicle->model !!}</td>
    </tr>
    <tr class="active">
        <th>Engine Number</th>
        <td>{!! $vehicle->engineNo !!}</td>
        <th>Number of Seats</th>
        <td>{!! $vehicle->seats !!}</td>
    </tr>
    <tr>
        <th>Number of Cylinder</th>
        <td>{!! $vehicle->cylinders !!}</td>
        <th>Cubic Capacity</th>
        <td>{!! $vehicle->cubic_capacity !!}</td>
    </tr>
    <tr class="active">
        <th>Is it imported?</th>
        @if($vehicle->is_imported == 0)
            <td>No</td>
        @else
            <td>Yes</td>
        @endif
        <th>Is the vehicle fitted with tracking device?</th>
        @if($vehicle->is_tracking == 0)
            <td>No</td>
        @else
            <td>Yes</td>
        @endif
    </tr>
    <tr>
        <th>Is the vehicle used for private use?</th>
        @if($vehicle->is_private == 0)
            <td>No</td>
        @else
            <td>Yes</td>
        @endif
        <th>Is the vehicle modified in any way?</th>
        @if($vehicle->is_modified == 0)
            <td>No</td>
        @else
            <td>Yes</td>
        @endif
    </tr>
    </tbody>
</table>

<h3>Vehicle Images</h3>
<br>
<div class="row">
    <div class="col-sm-2" style="display: inline-block;">
        <p class="col-form-label" style="margin-left: 20px;">Left</p>
        <div style="margin-top: 30px;">
            @if($vehicle->left == NULL)
                <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="100px" height="auto">
            @else
                <img src="{{\AlphaDirect\Helper::getCloudFrontURL($vehicle->left)}}" width="100px" height="auto" style="margin-left: -10px">
            @endif
        </div>
        <div class="kt-avatar" style="float: left; clear: left;"></div>
    </div>

    <div class="col-sm-2" style="display: inline-block;">
        <p class="col-form-label" style="margin-left: 20px;">Right</p>
        <div style="margin-top: 30px;">
            @if($vehicle->right== NULL)
                <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="100px" height="auto">
            @else
                <img src="{{\AlphaDirect\Helper::getCloudFrontURL($vehicle->right)}}" width="100px" height="auto" style="margin-left: -10px">
            @endif
        </div>
        <div class="kt-avatar" style="float: left; clear: left;"></div>
    </div>
    <div class="col-sm-2" style="display: inline-block;">
        <p class="col-form-label" style="margin-left: 20px;">back</p>
        <div style="margin-top: 30px;">
            @if($vehicle->back  == NULL)
                <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="100px" height="auto">
            @else
                <img src="{{\AlphaDirect\Helper::getCloudFrontURL($vehicle->back )}}" width="100px" height="auto" style="margin-left: -10px">
            @endif
        </div>
        <div class="kt-avatar" style="float: left; clear: left;"></div>
    </div>
    <div class="col-sm-2" style="display: inline-block;">
        <p class="col-form-label" style="margin-left: 20px;">Front</p>
        <div style="margin-top: 30px;">
            @if($vehicle->front  == NULL)
                <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="100px" height="auto">
            @else
                <img src="{{\AlphaDirect\Helper::getCloudFrontURL($vehicle->front)}}" width="100px" height="auto" style="margin-left: -10px">
            @endif
        </div>
        <div class="kt-avatar" style="float: left; clear: left;"></div>
    </div>
    <div class="col-sm-2" style="display: inline-block;">
        <p class="col-form-label" style="margin-left: 20px;">Vehicle Registration</p>
        <div style="margin-top: 30px;">
            @if($vehicle->vehicleRegistration == NULL)
                <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="100px" height="auto">
            @else
                <img src="{{\AlphaDirect\Helper::getCloudFrontURL($vehicle->vehicleRegistration )}}" width="100px" height="auto" style="margin-left: -10px">
            @endif
        </div>
        <div class="kt-avatar" style="float: left; clear: left;"></div>
    </div>
</div>
<h3>Accident Details</h3>
<table class="table">

    <tr class="active">
        <th>Place of accident</th>
        <td>{{ $claimAccident->place_of_accident }}</td>
        <th>Time of accident</th>
        <td>{{ $claimAccident->time_of_accident }}</td>
    </tr>
    <tr>
        <th>Date of accident</th>
        <td>{{ $claimAccident->date_of_accident }}</td>
    </tr>

</table>
<h3>Driver Details</h3>
<table class="table">
    <tr class="active">
        <th>Name</th>
        <td>{{ $accident_driver->name }}</td>
        <th>Mobile Number</th>
        <td>{{ $accident_driver->cellphone }}</td>
    </tr>
    <tr>
        <th>Address</th>
        <td>{{ $accident_driver->address }}</td>
        <th>Purpose</th>
        <td>{{ $accident_driver->purpose}}</td>
    </tr>
    <tr class="active">
        <th>Date Of Birth</th>
        <td>{{ $accident_driver->dob}}</td>
        <th>License</th>
        <td>{{ $accident_driver->license }}</td>
    </tr>
</table>
<h3>Passengers Details</h3>
<table class="table">
    @foreach($accident_passenger as $key => $passenger)
        <tr class="active">
            <th>Name</th>
            <td>{{ $passenger->name }}</td>
            <th>Injury</th>
            <td>{{ $passenger->injury }}</td>
        </tr>
        <tr>
            <th>Address</th>
            <td>{{ $passenger->address }}</td>
        </tr>
    @endforeach
</table>
@if($claimAccident->third_party == 1)
<h3>Third Party Details</h3>
<table class="table">
    @foreach($thirdparty as $key => $third)
    <tr class="active">
        <th>Name</th>
        <td>{{ $third->name }}</td>
        <th>Mobile Number</th>
        <td>{{ $third->cellphone }}</td>
    </tr>
    <tr>
        <th>Address</th>
        <td>{{ $third->address }}</td>
        <th>Damage Details</th>
        <td>{{ $third->damage_details}}</td>
    </tr>
    <tr class="active">
        <th>Registration Number</th>
        <td>{{ $third->registration_no}}</td>
        <th>Vehicle Make</th>
        <td>{{ $third->make }}</td>
    </tr>
    <tr>
        <th>Vehicle Model </th>
        <td>{{ $third->model}}</td>
        <th>Injured Name</th>
        <td>{{ $third->injured_name }}</td>
    </tr>
    <tr class="active">
        <th>RelationShip</th>
        <td>{{ $third->relationship}}</td>
        <th>Hospital Name</th>
        <td>{{ $third->hospital_name }}</td>
    </tr>
    <tr>
        <th>Injured Details</th>
        <td>{{ $third->injured_details}}</td>
    </tr>
    @endforeach
</table>
@endif
 <h3>Other Information </h3>
<table class="table table-striped m-table">
    <tbody>
    <tr>
        <th>Claim sub-type</th>
        <td>{!! $claimAccident->claim_sub_type !!}</td>
        <th>Service Representative</th>
        <td>{!! $claimAccident->representative !!}</td>
    </tr>
    <tr>
        <th>Recovery involved</th>
        @if($claimAccident->recovery_involved == "on")
            <td>Yes</td>
        @else
            <td>No</td>
        @endif
        <th>Attorney involved</th>
        @if($claimAccident->attorney_involved == "on")
            <td>Yes</td>
        @else
            <td>No</td>
        @endif
    </tr>
    <tr>
        <th>Date of loss</th>
        <td>{!! $claimAccident->incident_date !!}</td>
        <th>Type of loss</th>
        <td>{!! $claimAccident->loss_type !!}</td>
    </tr>
    <tr>
        <th>Catastrophe loss</th>
        @if($claimAccident->catastrophe_loss == 1)
            <td>Yes</td>
        @else
            <td>No</td>
        @endif
        <th>DFS Complaint</th>
        @if($claimAccident->dfs_complaint == 1)
            <td>Yes</td>
        @else
            <td>No</td>
        @endif
    </tr>
    <tr>
        <th>Description of loss</th>
        <td>{!! $claimAccident->loss_description !!}</td>
        <th>Event Name</th>
        <td>{!! $claimAccident->event_name !!}</td>

    </tr>
    <tr>
        <th>Primary Attorney Assigned:</th>
        @if($claimAccident->primary_attorney_assigned == 1)
            <td>Yes</td>
        @else
            <td>No</td>
        @endif
        <th>Co-Attorney Assigned:</th>
        @if($claimAccident->co_attorney_assigned == 1)
            <td>Yes</td>
        @else
            <td>No</td>
        @endif
    </tr>
    <tr>
        <th>Assigned Date:</th>
        <td>{!! $claimAccident->attorney_assigned_date !!}</td>
        <th>Claim reported by</th>
        <td>{!! $claimAccident->relation !!}</td>
    </tr>
    <tr>
        <th>Claim Number</th>
        <td>{!! $claims->claim_number !!}</td>
        <th>Date first visited</th>
        <td>{!! $claimAccident->first_visit !!}</td>
    </tr>
    </tbody>
</table>

</body>
</html>