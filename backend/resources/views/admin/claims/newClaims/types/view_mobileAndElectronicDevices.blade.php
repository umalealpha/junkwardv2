<table class="table table-striped m-table">
    <tbody>
    {{-- <tr>
        <th>Claim sub-type</th>
        @foreach($claimSubType as $type)
            @if ($newclaim->claim_sub_type_id == $type->id)
                <td>{!! $type->value  !!}</td>
            @endif
        @endforeach
    </tr> --}}
    <tr>
        <th>Insured’s Name</th>
        <td>{!! $mobileAndElectDev->insured_name !!}</td>
    </tr>
    <tr>
        <th>E-mail Address</th>
        <td>{!! $mobileAndElectDev->email_address !!}</td>
    </tr>
    <tr>
        <th>Address</th>
        <td>{!! $mobileAndElectDev->address !!}</td>
    </tr>
    <tr>
        <th>Telephone No.</th>
        <td>{!! $mobileAndElectDev->telephone_no !!}</td>
    </tr>
    <tr>
        <th>Has the property been stolen or damaged?</th>
        <td>
            @if($mobileAndElectDev->property_stolen_damaged == 1)
            Damaged
            @else
            Stolen
            @endif
        </td>
    </tr>
    {{-- <tr>
        <th>Address of the premises or place, where loss or damage occurred (If lost) from premises please state use of premises)</th>
        <td>{!! $mobileAndElectDev->premises_address !!}</td>
    </tr> --}}
    {{-- <tr>
        <th>Full particulars of circumstances of the loss or damage</th>
        <td>{!! $mobileAndElectDev->circumstances_loss_damage !!}</td>
    </tr> --}}
    <tr>
        <th>Date and time when loss or damage was discovered</th>
        <td>{!! $mobileAndElectDev->date_time_loss_discovered !!}</td>
    </tr>
    <tr>
        <th>By whom discovered?</th>
        <td>{!! $mobileAndElectDev->whom_discovered !!}</td>
    </tr>
    {{-- <tr>
        <th>Date and time when articles(s) last seen</th>
        <td>{!! $mobileAndElectDev->articles_last_seen !!}</td>
    </tr> --}}
    {{-- <tr>
        <th>By whom last seen and where?</th>
        <td>{!! $mobileAndElectDev->whom_last_seen_and_where !!}</td>
    </tr> --}}
    {{-- <tr>
        <th>When were the police notified</th>
        <td>{!! $mobileAndElectDev->when_police_notified !!}</td>
    </tr> --}}
    {{-- <tr>
        <th>Name of police station?</th>
        <td>{!! $mobileAndElectDev->police_station_name !!}</td>
    </tr>
    <tr>
        <th>Attending officer name</th>
        <td>{!! $mobileAndElectDev->officer_name !!}</td>
    </tr> --}}
    {{-- <tr>
        <th>Report number</th>
        <td>{!! $mobileAndElectDev->report_number !!}</td>
    </tr>
    <tr>
        <th>Has a thorough search been made for the article(s)</th>
        <td>
            @if($mobileAndElectDev->thorough_search_made_for_article == 1)
                Yes
            @else
                No
            @endif
        </td>
    </tr>
    <tr>
        <th>Have you ever before sustained previous</th>
        <td>
            @if($mobileAndElectDev->loss_cause == 1)
                Loss by theft
            @else
                {!! $mobileAndElectDev->loss_by_other_cause !!}
            @endif
        </td>
    </tr>
    <tr>
        <th>Is the property for which you are claiming insured against Burglary, Theft Loss or Damage, with any other Company or underwriter?</th>
        <td>
            @if($mobileAndElectDev->property_insured_against == 1)
                Yes
            @else
                No
            @endif
        </td>
    </tr>
    <tr>
        <th>Is pre inspection images uploaded?</th>
        <td>
            @if($mobileAndElectDev->preinspection_images_uploaded == 1)
                Yes
            @else
                No
            @endif
        </td>
    </tr> --}}
    <tr>
        <th>Are you the sole owner of the property?</th>
        <td>{!! $mobileAndElectDev->is_sole_owner_of_property !!}</td>
    </tr>

    @if ($mobileAndElectDev->is_sole_owner_of_property == 0)
        <tr>
            <th>Sole owner of the property</th>
            <td>{!! $mobileAndElectDev->sole_owner_of_property !!}</td>
        </tr>
    @endif
    <tr>
        <th>Claim Number</th>
        <td>{!! $claims->claim_number !!}</td>
    </tr>

    </tbody>
</table>
