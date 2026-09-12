<table class="table table-striped m-table">
    <tbody>
    <tr>
        <th>Address of premises where loss occurred</th>
        <td>{{$goodsInTransit->address_of_premises_loss}}</td>
    </tr>
    <tr>
        <th>Details of the carrier/ driver</th>
        <td>
            {{$goodsInTransit->details_of_driver}}
        </td>
    </tr>
    <tr>
        <th>When was the property last seen by you?</th>
        <td>{{$goodsInTransit->property_last_seen}}</td>
    </tr>
    <tr>
        <th>Date and time of loss</th>
        <td>
            {{$goodsInTransit->date_time_of_loss}}
        </td>
    </tr>
    <tr>
        <th>Brief description of incident</th>
        <td>
            {{$goodsInTransit->brief_description_incident}}
        </td>
    </tr>
    <tr>
        <th>Date and time the police were advised of loss</th>
        <td>{{$goodsInTransit->date_time_police_advised}}</td>
    </tr>

    <tr>
        <th>Name of police station</th>
        <td>{{$goodsInTransit->police_station_name}}</td>
    </tr>

    <tr>
        <th>Names of witnesses</th>
        <td>{{$goodsInTransit->witnesses_name}}</td>
    </tr>
    <tr>
        <th>Witnesses mobile number</th>
        <td>{{$goodsInTransit->witnesses_mobile_number}}</td>
    </tr>
    <tr>
        <th>What is the total value of the loss?</th>
        <td>{{$goodsInTransit->total_value_of_loss}}</td>
    </tr>
    <tr>
        <th>Where was the consignment being transported to?</th>
        <td>{{$goodsInTransit->consignment_transported_to}}</td>
    </tr>
    <tr>
        <th>Where was the consignment from?</th>
        <td>{{$goodsInTransit->consignment_from}}</td>
    </tr>
    <tr>
        <th>Registration number of the vehicle carrying the good?</th>
        <td>{{$goodsInTransit->vehicle_registration_number}}</td>
    </tr>
    <tr>
        <th>Is the carrier contracted?</th>
        <td>
            @if($goodsInTransit->is_carrier_contracted == 1)
                Yes
            @else
                No
            @endif
        </td>
    </tr>
    @if($goodsInTransit->is_carrier_contracted == 1)
        <tr>
            <th>Please provide a copy of the contract</th>
            <td>
                <div class="kt-avatar" id="copy_of_contract" style="float: left; clear: left;">
                    <div class="kt-avatar" id="copy_of_contract"
                        style="float: left; clear: left;">
                        @if (!isset($goodsInTransit->copy_of_contract))
                            <div class="kt-avatar__holder"
                                style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)">
                            </div>
                        @else
                            <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($goodsInTransit->copy_of_contract) !!}"
                                target="_blank" download>
                                <div class="kt-avatar__holder"
                                    style="background-image:url('{!! \AlphaDirect\Helper::getCloudFrontURL($goodsInTransit->copy_of_contract) !!}')">
                                </div>
                            </a>
                        @endif
                    </div>
                </div>
            </td>
        </tr>
    @endif
    <tr>
        <th>Does the carrier have their own GIT insurance</th>
        <td>
            @if($goodsInTransit->carrier_has_own_GIT_ins == 1)
                Yes
            @else
                No
            @endif
        </td>
    </tr>
    <tr>
        <th>Are there any other Insurances against theft upon the same property?</th>
        <td>
            @if($goodsInTransit->other_insurance_against_theft == 1)
                Yes
            @else
                No
            @endif
        </td>
    </tr>
    @if($goodsInTransit->other_insurance_against_theft == 1)
        <tr>
            <th>Give details in brief</th>
            <td>{{$goodsInTransit->insurance_against_theft_details}}</td>
        </tr>
    @endif
    <tr>
        <th>Give details of records of previous loss in the premises or any other premises on similar goods</th>
        <td>{{$goodsInTransit->details_of_previous_loss_records}}</td>
    </tr>
    <tr>
        <th>Claim Number</th>
        <td>{!! $claims->claim_number !!}</td>
    </tr>

    </tbody>
</table>
