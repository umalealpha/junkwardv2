

<div class="tab-content" id="vehicleLabelDiv">
    <div class="tab-pane active" id="kt_portlet_base_demo_3_1_tab_content" role="tabpanel">
        <div class="kt-portlet">
            <div class="kt-portlet__head row" >
                <div class="kt-portlet__head-label col-lg-12">
                    <div class="col-lg-4">
                        <h3 class="kt-portlet__head-title">
                           Loss Of Key Claim Information
                        </h3>
                    </div>
                </div>
            </div>
            @isset($keyloss)
                <div class="kt-portlet_body">
                    <div class="kt-section">
                        <div class="kt-section__content" style="padding-left:20px; padding-right: 20px;">
                            <table class="table table-striped m-table">
                                <tbody>
                                <tr>
                                    <th>Financial Interest</th>
                                    <td>{!! $keyloss->financial_interest !!}</td>
                                </tr>
                                <tr>
                                    <th>Chassis Number</th>
                                    <td>{!! $keyloss->chassis_num !!}</td>
                                </tr>
                                <tr>
                                    <th>Purpose of use</th>
                                    <td>{!! $purposeName !!}</td>
                                </tr>
                                <tr>
                                    <th>Is the key lost or damaged or stolen</th>
                                    <td>{!! $reasonName !!}</td>
                                </tr>
                                <tr>
                                    <th>Replacement Estimate</th>
                                    <td>{!! $keyloss->replacement_estimate !!}</td>
                                </tr>
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
                            <h3>Insured Details</h3>
                            <table class="table table-striped m-table">
                                <tbody>
                                <tr>
                                    <th>Name of Insured</th>
                                    <td>{!! $keyloss->name_of_insured !!}</td>
                                    <th>Address</th>
                                    <td>{!! $keyloss->insured_address !!}</td>
                                </tr>
                                <tr>
                                    <th>Occupation</th>
                                    <td style="width:30%;">{!! $keyloss->insured_occupation !!}</td>
                                    <th>Email</th>
                                    <td>{!! $keyloss->insured_email !!}</td>
                                </tr>
                                <tr>
                                    <th>Contact No</th>
                                    <td>{!! $keyloss->insured_contact_no !!}</td>
                                </tr>
                                </tbody>

                            </table>
                            <hr>
                            <h3>Vehicle Details</h3>
                            <table class="table table-striped m-table">
                                <tbody>
                                <tr>
                                    <th>Registration Number</th>
                                    <td>{!! $vehicle->vehiclePlate !!}</td>
                                    <th>Is Imported?</th>
                                    @if($vehicle->is_imported == 'Yes' || $vehicle->is_imported == 1)
                                        <td>Yes</td>
                                        @else
                                        <td>No</td>
                                    @endif
                                </tr>
                                <tr>
                                    <th>Make</th>
                                    <td style="width:30%;">{!! $vehicle->make !!}</td>
                                    <th>Manufacturing Year</th>
                                    <td>{!! $vehicle->year !!}</td>
                                </tr>
                                <tr>
                                    <th>Model</th>
                                    <td style="width:30%;">{!! $vehicle->model !!}</td>
                                </tr>
                                </tbody>

                            </table>
                            <div class="kt-portlet_body">
                                <div class="kt-section">
                                    <div class="kt-section__content" style="padding-left:20px; padding-right: 20px;">
                                        <table class="table table-striped m-table">
                                            <tbody>
                                            <div class="form-group row">
                                                <div class="col-md-2">
                                                    <h3 class="col-form-label" style="float: left;">Police Affidavit</h3>
                                                    @if($keyloss->police_affidavit == NULL)
                                                        <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="100%" height="auto" >
                                                    @else
                                                        <img src="{{ \AlphaDirect\Helper::getCloudFrontURL($keyloss->police_affidavit) }}" width="100%" height="auto" >
                                                    @endif
                                                    <div class="kt-avatar" style="float: left; clear: left;"></div>
                                                </div>
                                            </div>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <br>
                        <div style="border:1px solid lightslategray;" class="kt-separator kt-separator--space-lg kt-separator--border-dashed"></div>


                            <div class="kt-portlet__head">
                                <div class="kt-portlet__head-label">
                                    <h3 class="kt-portlet__head-title">
                                        Quotation :
                                    </h3>
                                </div>
                            </div>
                        <div class="kt-portlet__head">
                            <div class="kt-portlet__head-label">
                                <h3 class="kt-portlet__head-title">
                                    Quote 1
                                </h3>
                            </div>
                        </div>


                        <table class="table table-striped m-table">
                            <tbody>
                                <tr>
                                    <th>Name of company</th>
                                    <td>{{ $keyloss->company_1 }}</td>
                                    <th>Amount of quote</th>
                                    <td style="width:40%;">{{ $keyloss->amount_quote_1 }}</td>
                                </tr>
                            </tbody>
                        </table>
                            <div class="kt-portlet_body">
                                <div class="kt-section">
                                    <div class="kt-section__content" style="padding-left:20px; padding-right: 20px;">
                                        <table class="table table-striped m-table">
                                            <tbody>
                                            <div class="form-group row">
                                                <div class="col-md-2">
                                                    <h3 class="col-form-label" style="float: left;">Quote</h3>
                                                    @if($keyloss->quote_1 == NULL)
                                                        <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="100%" height="auto" >
                                                    @else
                                                        <img src="{{ \AlphaDirect\Helper::getCloudFrontURL($keyloss->quote_1) }}" width="100%" height="auto" >
                                                    @endif
                                                    <div class="kt-avatar" style="float: left; clear: left;"></div>
                                                </div>
                                            </div>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                        <div class="kt-portlet__head">
                            <div class="kt-portlet__head-label">
                                <h3 class="kt-portlet__head-title">
                                    Quote 2
                                </h3>
                            </div>
                        </div>


                        <table class="table table-striped m-table">
                            <tbody>
                            <tr>
                                <th>Name of company</th>
                                <td>{{ $keyloss->company_2 }}</td>
                                <th>Amount of quote</th>
                                <td style="width:40%;">{{ $keyloss->amount_quote_2 }}</td>
                            </tr>
                            </tbody>
                        </table>
                        <div class="kt-portlet_body">
                            <div class="kt-section">
                                <div class="kt-section__content" style="padding-left:20px; padding-right: 20px;">
                                    <table class="table table-striped m-table">
                                        <tbody>
                                        <div class="form-group row">
                                            <div class="col-md-2">
                                                <h3 class="col-form-label" style="float: left;">Quote</h3>
                                                @if($keyloss->quote_2 == NULL)
                                                    <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="100%" height="auto" >
                                                @else
                                                    <img src="{{ \AlphaDirect\Helper::getCloudFrontURL($keyloss->quote_2) }}" width="100%" height="auto" >
                                                @endif
                                                <div class="kt-avatar" style="float: left; clear: left;"></div>
                                            </div>
                                        </div>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div style="border:1px solid lightslategray;" class="kt-separator kt-separator--space-lg kt-separator--border-dashed"></div>
                    </div>

                </div>
            @endisset


        </div>
    </div>
</div>

