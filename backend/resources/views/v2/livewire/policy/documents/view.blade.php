
    <div>
        <div class="kt-portlet">
            <div class="row">
                <span class="text-end">v0.1</span>
            </div>
            <!--begin::Form-->
            <form id="sendDcument" action="{{ route('admin.policy.sendPolicyDocument', $this->policy->id) }}"
                method="POST" enctype="multipart/form-data" class="kt-form" autocomplete="off">
                <!-- CSRF Token -->
                <input type="hidden" name="_token" value="{{ csrf_token() }}" />

                    <div class="row">
                        <div class="col-sm-2">
                            <label for="example-text-input">Customer Email:</label>
                        </div>
                        <div class="col-sm-3">
                            <p style="font-weight:bold">{{ $this->customer->email }}</p>
                        </div>
                        @if ($this->policy->product_id == 3)
                        <div class="col-sm-3">
                            <select id="policy_terms" class="form-control required kt_selectpicker"
                                    title="Please select term" name="term">
                                    @if (count($this->policy_term) > 0)
                                        @foreach ($policy_term as $key => $data)
                                            <option value="{{ $data->id }}"
                                                data-id="{{ Carbon::parse($data->term_start_date)->year }}"
                                                @if ($data->status == 'Active') selected @endif>{{ Carbon::parse($data->term_start_date)->year . '-' . Carbon::parse($data->term_end_date)->year }} @if ($data->status == 'Active') <label style="color:green">(Active)</label> @endif</option>
                                        @endforeach
                                    @else
                                        <option value='-1'>No term found</option>
                                    @endif
                            </select>
                        </div>
                        <div class="col-sm-3">
                            <a id="termDocButton" class="btn btn-info"
                            href="{{ route('admin.policy.index') }}">Generate Docs</a>
                        </div>
                        @endif
                    </div>

                    <div class="row">
                        <label for="example-text-input" class="col-2"> Attachments</label>
                        <div class="col-10">
                            <p>Following documents will be sent to customers email.</p>
                            @foreach ($this->emailDocs as $doc)
                                <span>=> <a target="_blank"
                                        href="{{ \AlphaDirect\Helper::getCloudFrontURL($doc->link) }}">{{ $doc->name }}</a></span><br>
                            @endforeach


                            @if (isset($this->PolicyDocument))
                            <span>=>
                                <!-- <a target="_blank"
                                    href="{{ \AlphaDirect\Helper::getCloudFrontURL($this->PolicyDocument->doc_path) }}">Policy
                                    Schedule-{{ $policy->policyNumber }}-(V1)</a> -->
                                    <a target="_blank"
                                    href="{{ route('admin.policy.downloadPolicyDocs', $this->policy->id) }}">Policy
                                    Schedule-{{ $policy->policyNumber }}-(V1)</a>
                                </span><br>
                            @endif
                        </div>
                    </div>

                    @if(isset($this->policy->is_bundled) && $this->policy->is_bundled == 1)
                    <div class="form-group row">
                        <label for="example-text-input" class="col-2">Bundled Attachments</label>
                        <div class="col-10">
                            <p>Following documents will be sent to customers email.</p>
                            @if(isset($this->EmailDocsPolicyBundled))
                                @php $i = 1 @endphp
                                @foreach ($this->EmailDocsPolicyBundled as $bundledDoc)
                                    <span>=> <a target="_blank"
                                            href="{{ \AlphaDirect\Helper::getCloudFrontURL($bundledDoc->policyDocument) }}">Bundled Policy
                                            Schedule-{{$this->policy->policyNumber }}-({{$i}})</a></span><br>
                                @php $i++ @endphp
                                @endforeach
                            @endif
                        </div>
                    </div>
                    @endif
                    <br>
                    {{-- @if (auth::user()->hasPermissionTo('policy-Regenerate Policy Document')) --}}
                    <div class="row align-items-center g-2">

                            @php
                                $routeParams = [
                                    'policyId' => $this->policy->id,
                                    'termId'   => $this->termId,
                                    'actionId' => $this->dataShowForActionId,
                                ];
                            @endphp
                            <div class="col-md-2"></div>
                            <div class="col-auto">
                                @if ($this->policy->product_id == 16)
                                    <a class="btn btn-info" target="_blank"
                                    href="{{ route('admin.policy.v2_quotationPdfEngineering', $routeParams + ['flag' => 'v2_quotationPdfEngineeringDocuments']) }}">
                                        <i class="fa fa-cogs me-1"></i>
                                        Regenerate Engineering Documents
                                    </a>
                                @elseif ($this->policy->product_id == 17)
                                    <a class="btn btn-info" target="_blank"
                                    href="{{ route('admin.policy.v2_quotationPdfSpecialistProduct', $routeParams + ['flag' => 'v2_quotationPdfSpecialistProductDocuments']) }}">
                                        <i class="fa fa-cogs me-1"></i>
                                        Regenerate Specialist Product Documents
                                    </a>
                                    @php $checkProfessionalIndemnity = \AlphaDirect\Models\ProfessionalIndemnityCoverage::where('policy_id', $this->policy->id)->where('policy_coverage_id','!=',null)->exists(); @endphp
                                    @if($checkProfessionalIndemnity)    
                                    <a class="btn btn-info" target="_blank"
                                    href="{{ route('admin.policy.v2_quotationPdfProfessionalIndemnity', $routeParams) }}">
                                        <i class="fa fa-cogs me-1"></i>
                                        Professional Indemnity Certificate
                                    </a>
                                    @endif
                                @else
                                    <a class="btn btn-info"
                                    href="{{ route('admin.policy.regenerateNewPolicyDocument', $routeParams) }}">
                                        Regenerate Documents
                                    </a>
                                @endif
                            </div>

                            @if (isset($this->PolicyDocument))
                                <div class="col-auto ms-auto">
                                    <a class="btn btn-info"
                                    href="{{ route('admin.policy.downloadPolicyDocs', $this->policy->id) }}"
                                    target="_blank">
                                        V2 Policy Schedule
                                    </a>
                                </div>
                            @endif

                            @if (optional($this->currentAction)->status === 'ISSUED' || $this->policy->status == 1)
                                <div class="col-auto">
                                    <a class="btn btn-success"
                                    href="{{ route('admin.policy.reSendPolicyDocument', $this->policy->id) }}">
                                        Send Documents on Mail
                                    </a>
                                </div>
                            @endif

                            </div>

                    {{-- @endif --}}
                    <br><hr><br>
                    {{-- customer verification document --}}
                    <div class="row">
                        <label for="example-text-input" class="col-2">Information Verification Document</label>
                        <div class="col-10">
                            <p>Following document will be sent to customers email.</p>
                            @if ($this->policy->verification_doc != null)
                                <span> <a target="_blank" class=""
                                        href="{{ \AlphaDirect\Helper::getCloudFrontURL($this->policy->verification_doc) }}">Get Verification Document</a>
                                </span>
                            @endif
                        </div>
                    </div>
                    <br>
                    <div class="row">
                        <div class="col-2">
                        </div>
                        <div class="col-3" style="display:inline;float:right">
                            <a class="btn btn-success"
                                href="{{ route('admin.policy.regenerateInformationDocument',$this->policy->id) }}">Regenerate Verification Document</a>
                        </div>
                    </div>
                    <br><hr><br>
                    <div class="row">
                        <label for="example-text-input" class="col-2">Policy Cancellation Note</label>
                        <div class="col-10">
                            @if ($this->policyCancellationNoteDoc)
                                <span>=> <a target="_blank"
                                        href="{{ \AlphaDirect\Helper::getCloudFrontURL($this->policyCancellationNoteDoc->doc_path) }}">Policy
                                        Cancellation Note</a></span><br>
                            @else
                                <p> - </p><br>
                            @endif
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-2"></div>
                        <div class="col-3 mt-3" style="display:inline;float:right">
                            <a class="btn btn-info"
                               href="{{ route('admin.policy.regenerateNewPolicyCancellationNote', ['policyId'=>$this->policy->id,'termId'=>$this->termId ,'actionId'=>$this->dataShowForActionId]) }}">Regenerate Cancellation Note</a>
                        </div>
                    </div>
                    <br><hr><br>

                    <div class="row">
                        <label for="example-text-input" class="col-2">Policy Cover Note</label>
                        <div class="col-10" style="margin-top:1%">
                            @if ($this->coverNote && $this->policy->status == 1)
                                <span> <a target="_blank"
                                        href="{{ \AlphaDirect\Helper::getCloudFrontURL($this->coverNote->path) }}"> Policy
                                        Cover Note</a></span><br>
                            @else
                                <span> - </span><br>
                            @endif
                        </div>
                    </div>
                    <br><hr><br>
                    <div class="row">
                        <label for="example-text-input" class="col-2">Policy Cancel Note</label>
                        <div class="col-10">
                            @if ($this->cancelNote)
                                <span>=> <a target="_blank"
                                        href="{{ \AlphaDirect\Helper::getCloudFrontURL($this->cancelNote->path) }}">Policy
                                        Cancel Note</a></span><br>
                            @else
                                <p> - </p><br>
                            @endif
                        </div>
                    </div>
                    @if ($this->policy->status == 2 || ($this->currentAction && $this->currentAction->transaction_type == 'CANCEL'))
                    <div class="row">
                        <div class="col-2"></div>
                        <div class="col-3 mt-3" style="display:inline;float:right">
                            <a class="btn btn-info"
                               href="{{ route('admin.documents.generateV2CancelNote', ['policyId'=>$this->policy->id,'termId'=>$this->termId ,'actionId'=>$this->dataShowForActionId]) }}">Generate Cancel Note</a>
                        </div>
                    </div>
                    @endif
                </div>
                <br><hr><br>
                <div class="kt-portlet__foot kt-portlet__foot--solid">
                    <div class="kt-form__actions">
                        <div class="row">
                            <div class="col-2"></div>
                            <div class="col-10">
                                <button type="submit" value="Submit" id="btn" class="btn btn-primary">Send</button>
                                <button class="btn btn-primary" type="button" id="loadBtn" style="display:none"> <span
                                        class="spinner-border spinner-border-sm" role="status"
                                        aria-hidden="true"></span> Loading... </button>
                                <a class="btn btn-secondary" href="{{ route('admin.policy.index') }}">Cancel</a>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
            <br>
            @if ($this->policy->product_id == 3)
                <form id="generateCoverNote" action="{{ route('admin.documents.generateCoverNote', $this->policy->id) }}"
                    method="POST" enctype="multipart/form-data" class="kt-form" autocomplete="off">
                    <!-- CSRF Token -->
                    <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                    <div class="kt-portlet__body">
                        <div class="form-group row">
                            <label for="example-text-input" class="col-2">Generate Policy Note:</label>
                            <div class="col-6">
                                <select id="doc_type" class="form-control required kt_selectpicker" title="Please select document type" name="doc_type">
                                    <option>Please select document type</option>
                                    <option @if ($this->policy->status == 1) enabled @else disabled @endif value="Cover">Policy Cover Document</option>
                                    <option @if ($this->policy->status == 2) enabled @else disabled @endif value="Cancel">Policy Cancel Document</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <br>
                    <div class="kt-portlet__foot kt-portlet__foot--solid">
                        <div class="kt-form__actions">
                            <div class="row">
                                <div class="col-2"></div>
                                <div class="col-10">
                                    <button type="submit" value="Submit" id="btn"
                                        class="btn btn-primary">Generate</button>
                                    <button class="btn btn-primary" type="button" id="loadBtn" style="display:none">
                                        <span class="spinner-border spinner-border-sm" role="status"
                                            aria-hidden="true"></span> Loading... </button>
                                    <a class="btn btn-secondary"
                                        href="{{ route('admin.policy.index') }}">Cancel</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            @endif
            <!--end::Form-->
        </div>
        <br>
        <div class="card mb-5 mb-xl-10">
            <div class="card-header cursor-pointer">
                <div class="card-title m-0">
                    <h3 class="fw-bold m-0">Sent Policy Documents</h3>
                </div>
            </div>
            <div class="card-body p-9">
                @livewire('policy.documents.policy-doc-table', ['policy' => $this->policy])
            </div>
        </div>

        <div class="card mb-5 mb-xl-10">
            <div class="card-header cursor-pointer">
                <div class="card-title m-0">
                    <h3 class="fw-bold m-0">Policy Documents</h3>
                </div>
            </div>
            <div class="card-body p-9">
                @livewire('policy.documents.send-policy-doc-table', ['policy' => $this->policy])
            </div>
        </div>

    </div>

