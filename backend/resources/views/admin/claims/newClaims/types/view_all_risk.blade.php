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
        <th>Has the property been stolen or damaged?</th>
        <td>
            @if($allRisk->property_stolen_damaged == 1)
            Damaged
            @else
            Stolen
            @endif
        </td>
    </tr>
    {{-- <tr>
        <th>Full particulars of circumstances of the loss or damage</th>
        <td>{!! $allRisk->circumstances_loss_damage !!}</td>
    </tr> --}}
    @if($allRisk->property_stolen_damaged == 0)
    <tr>
        <th>Has a thorough search been made for the article(s)</th>
        <td>
            @if($allRisk->thorough_search_made_for_article == 1)
                Yes
            @else
                No
            @endif
        </td>
    </tr>
    @endif
    <tr>
        <th>Have you ever before sustained previous</th>
        <td>
            @if($allRisk->loss_cause == 1)
                Loss by theft
            @else
                {!! $allRisk->loss_by_other_cause !!}
            @endif
        </td>
    </tr>
    @if($allRisk->property_stolen_damaged == 0)
        <tr>
            <th>Was the property stolen from a car or unlocked premises?</th>
            <td>
                @if($allRisk->stolenfromcar_unlockedpremises == 1)
                    Yes
                @else
                    No
                @endif
            </td>
        </tr>

        <tr>
            <th>Are you the sole owner of the property?</th>
            <td>
                @if($allRisk->is_sole_owner_of_property == 1)
                    Yes
                @else
                    No
                @endif
            </td>
        </tr>

        @if ($allRisk->is_sole_owner_of_property == 0)
            <tr>
                <th>Sole owner of the property</th>
                <td>{!! $allRisk->sole_owner_of_property !!}</td>
            </tr>
        @endif
    @endif
    <tr>
        <th>Claim Number</th>
        <td>{!! $claims->claim_number !!}</td>
    </tr>

    </tbody>
</table>
