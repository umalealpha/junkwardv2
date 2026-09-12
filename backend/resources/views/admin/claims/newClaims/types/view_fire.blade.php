<table class="table table-striped m-table">
    <tbody>
    <tr>
        <th>Address of premises where fire occurred</th>
        <td>{{$fire->address_of_theft_occurred}}</td>
    </tr>
    {{-- <tr>
        <th>State the exact location in the premises From where the articles stolen were removed</th>
        <td>
            {{$fire->location_article_stolen_removed}}
        </td>
    </tr> --}}
    <tr>
        <th>When was the property last seen by you?</th>
        <td>{{$fire->property_last_seen}}</td>
    </tr>
    <tr>
        <th>Date and time of fire</th>
        <td>
            {{$fire->date_time_of_theft}}
        </td>
    </tr>
    {{-- <tr>
        <th>Date and time loss was discovered</th>
        <td>{{$fire->date_time_loss_discovered}}</td>
    </tr> --}}
    <tr>
        <th>Brief description of incident</th>
        <td>
            {{$fire->brief_description_incident}}
        </td>
    </tr>
    <tr>
        <th>Date and time the police were advised of loss</th>
        <td>{{$fire->date_time_police_advised}}</td>
    </tr>
    <tr>
        <th>Name of police station</th>
        <td>{{$fire->police_station_name}}</td>
    </tr>

    <tr>
        <th> Was anyone at the premises during the loss?</th>
        <td>
            @if($fire->anyone_during_burglary == 1)
                Yes
            @else
                No
            @endif
        </td>
    </tr>
    @if($fire->anyone_during_burglary == 1)
        <tr>
            <th>Give details in brief who was in the premises during the burglary?</th>
            <td>{{$fire->details_during_burglary}}</td>
        </tr>
    @endif
    <tr>
        <th>How many days have the premises been unoccupied During the past Twelve months</th>
        <td>{{$fire->days_premises_unoccupied}}</td>
    </tr>
    <tr>
        <th>Is the premises guarded by a watchman?</th>
        <td>
            @if($fire->premises_guarded_by_watchman == 1)
                Yes
            @else
                No
            @endif
        </td>
    </tr>
    @if($fire->premises_guarded_by_watchman == 1)
        <tr>
            <th>Name of guard</th>
            <td>{{$fire->name_of_guard}}</td>
        </tr>
        <tr>
            <th>Telephone number of guard</th>
            <td>{{$fire->telephone_of_guard}}</td>
        </tr>
        <tr>
            <th>Where was the guard during the fire?</th>
            <td>{{$fire->guard_during_fire}}</td>
        </tr>
    @endif
    <tr>
        <th>State name of security agent</th>
        <td>{{$fire->name_of_security_agent}}</td>0
    </tr>
    <tr>
        <th>Please provide contract of agreement</th>
        <td>
            <div class="kt-avatar" id="contract_of_agreement" style="float: left; clear: left;">
                <div class="kt-avatar" id="contract_of_agreement"
                    style="float: left; clear: left;">
                    @if (!isset($fire->contract_of_agreement))
                        <div class="kt-avatar__holder"
                            style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)">
                        </div>
                    @else
                        <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($fire->contract_of_agreement) !!}"
                            target="_blank" download>
                            <div class="kt-avatar__holder"
                                style="background-image:url('{!! \AlphaDirect\Helper::getCloudFrontURL($fire->contract_of_agreement) !!}')">
                            </div>
                        </a>
                    @endif
                </div>
            </div>
        </td>
    </tr>
    <tr>
        <th>Were all means of access of the premises properly secured at the time of the fire?</th>
        <td>
            @if($fire->premises_properly_secured == 1)
                Yes
            @else
                No
            @endif
        </td>
    </tr>
    <tr>
        <th>Do you suspect any person?</th>
        <td>
            @if($fire->suspect_any_person == 1)
                Yes
            @else
                No
            @endif
        </td>
    </tr>
    @if($fire->suspect_any_person == 1)
        <tr>
            <th>Give details of suspect person</th>
            <td>{{$fire->suspect_person_details}}</td>
        </tr>
    @endif
    <tr>
        <th>What was the total value of the buildings of your premises at the time of the loss?</th>
        <td>
            {{$fire->total_value_premises_buildings}}
        </td>
    </tr>
    <tr>
        <th>Are there any other Insurances against fire upon the same property?</th>
        <td>
            @if($fire->other_insurance_against_fire == 1)
                Yes
            @else
                No
            @endif
        </td>
    </tr>
    @if($fire->other_insurance_against_fire == 1)
        <tr>
            <th>Give details for other Insurances against fire upon the same property</th>
            <td>{{$fire->insurance_against_fire_details}}</td>
        </tr>
    @endif
    <tr>
        <th>What is the estimated amount of the damaged property?</th>
        <td>{{$fire->estimated_amount_of_damaged}}</td>
    </tr>
    <tr>
        <th>Give details of records of previous loss in the premises or any other premises owned by you</th>
        <td>{{$fire->details_of_previous_loss}}</td>
    </tr>
    <tr>
        <th>Claim Number</th>
        <td>{!! $claims->claim_number !!}</td>
    </tr>

    </tbody>
</table>
