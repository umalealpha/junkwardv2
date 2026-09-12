<h3 class="kt-heading kt-heading--md kt-heading--no-top-margin" style="margin-top:20px;">
    Loss Of Key Claim Information
</h3>
<table class="table table-striped m-table">
    <tbody>
    {{-- <tr>
        <th>Financial Interest</th>
        <td>{!! $keyloss->financial_interest !!}</td>
    </tr> --}}
    {{-- <tr>
        <th>Chassis Number</th>
        <td>{!! $keyloss->chassis_num !!}</td>
    </tr> --}}
    <tr>
        <th>Purpose of use</th>
        <td>{!! $purposeName !!}</td>
    </tr>
    <tr>
        <th>Is the key lost or damaged or stolen</th>
        <td>{!! $reasonName !!}</td>
    </tr>
    {{-- <tr>
        <th>Replacement Estimate</th>
        <td>{!! $keyloss->replacement_estimate !!}</td>
    </tr> --}}
    <tr>
        <th>Date of Loss/stolen/Damage</th>
        <td>{!! \Carbon\Carbon::createFromFormat('Y-m-d', $keyloss->date_of_loss)->format('d-m-Y')  !!}</td>
    </tr>
    <tr>
        <th>Description</th>
        <td>{!! $keyloss->description !!}</td>
    </tr>
    <tr>
        <th>Date Of Claim Registered</th>
        @if ($claims->registered_claim)
            <td>{!!  \Carbon\Carbon::createFromFormat('Y-m-d', $claims->registered_claim)->format('d-m-Y')    !!}</td>
        @else
            <td>N/A</td>
        @endif
    </tr>

    </tbody>
</table>
