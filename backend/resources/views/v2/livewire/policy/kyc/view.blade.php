<div>
    @if(session('status'))
        <div class="alert alert-success alert-dismissible fade show mt-3" role="alert">
            {{ session('status') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show mt-3" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    {{-- <div x-data="{ showHistory: false }">
        <!-- Button -->
        <div class="d-flex justify-content-end mb-3">
            <button class="btn btn-primary btn-sm mb-3" @click="showHistory = !showHistory">
                <template x-if="showHistory">Hide</template>
                <template x-if="!showHistory">View</template> KYC History
            </button>
        </div>

        <!-- Accordion -->
        <div x-show="showHistory" x-transition class="mb-20">
            <div class="accordion" id="kycAccordion">
                @foreach ($kycHistoryByYear as $yearLabel => $documents)
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="heading-{{ md5($yearLabel) }}">
                            <button class="accordion-button {{ !$loop->first ? 'collapsed' : '' }}" type="button"
                                    data-bs-toggle="collapse"
                                    data-bs-target="#collapse-{{ md5($yearLabel) }}"
                                    aria-expanded="{{ $loop->first ? 'true' : 'false' }}"
                                    aria-controls="collapse-{{ md5($yearLabel) }}">
                                {{ $yearLabel }}
                            </button>
                        </h2>
                        <div id="collapse-{{ md5($yearLabel) }}"
                            class="accordion-collapse collapse {{ $loop->first ? 'show' : '' }}"
                            aria-labelledby="heading-{{ md5($yearLabel) }}"
                            data-bs-parent="#kycAccordion">
                            <div class="accordion-body">
                                @if (!empty($documents))
                                    <div class="row">
                                        @foreach ($documents as $doc)
                                            <div class="col-md-3 mb-3">
                                                <div class="p-3 border rounded bg-white shadow-sm h-100">
                                                    <strong>{{ $doc['type'] }}</strong><br>
                                                    <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($doc['url']) . '?v=' . time() !!}" class="text-primary" target="_blank">View</a><br>
                                                    <small class="text-muted">{{ $doc['created_at'] }}</small>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    <p class="text-muted">No documents found.</p>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div> --}}

    {{-- below code is the latest code  --}}
    {{-- <!-- Loader -->
    <div wire:loading wire:target="toggleKycHistory" class="text-center my-4">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>

    <div>
    <!-- Button to toggle KYC History -->
    <div class="d-flex justify-content-end mb-3">
        <button class="btn btn-primary btn-sm mb-3" wire:click="toggleKycHistory">
            {{ $showKycHistory ? 'Hide' : 'View' }} KYC History
        </button>
    </div>

    <!-- Accordion Section -->
    @if ($showKycHistory)
        <div class="mb-20">
            <div class="accordion" id="kycAccordion">
                @foreach ($kycHistoryByYear as $yearLabel => $documents)
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="heading-{{ md5($yearLabel) }}">
                            <button class="accordion-button {{ !$loop->first ? 'collapsed' : '' }}" type="button"
                                    data-bs-toggle="collapse"
                                    data-bs-target="#collapse-{{ md5($yearLabel) }}"
                                    aria-expanded="{{ $loop->first ? 'true' : 'false' }}"
                                    aria-controls="collapse-{{ md5($yearLabel) }}">
                                {{ $yearLabel }}
                            </button>
                        </h2>
                        <div id="collapse-{{ md5($yearLabel) }}"
                            class="accordion-collapse collapse {{ $loop->first ? 'show' : '' }}"
                            aria-labelledby="heading-{{ md5($yearLabel) }}"
                            data-bs-parent="#kycAccordion">
                            <div class="accordion-body">
                                @if (!empty($documents))
                                    <div class="row">
                                        @foreach ($documents as $doc)
                                            <div class="col-md-3 mb-3">
                                                <div class="p-3 border rounded bg-white shadow-sm h-100">
                                                    <strong>{{ $doc['type'] }}</strong><br>
                                                    <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($doc['url']) . '?v=' . time() !!}" class="text-primary" target="_blank">View</a><br>
                                                    <small class="text-muted">{{ $doc['created_at'] }}</small>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    <p class="text-muted">No documents found.</p>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif --}}
{{-- </div> --}}

    @if($this->policy->kyc_customer) @endif
    {{-- <b>Customer KYC</b> --}}
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h4>Customer KYC</h4>
    </div>

    <form  class="mb-5" action="{{ route('admin.policy.savekyccustomer') }}" method="POST" enctype="multipart/form-data" id="" autocomplete="off">
        <!-- CSRF Token -->
        <input type="hidden" name="_token" value="{{ csrf_token() }}" />
        <input type="hidden" name="customer_id" value="{{$this->customer}}" />
        <input type="hidden" name="policy_id" value="{{$this->policy->id}}" />
        <input type="hidden" name="action_id" value="{{$this->actionId}}" />

        @if ($this->policy->product_id == 8)
            <div class="row">
                <!-- KYC Form -->
                <div class="col-xs-2 col-sm-2 col-md-2 mt-4">
                    @livewire('common.image-upload',['imageTitle'=>'KYC form','imageField'=>'kyc_form','imageExist'=>$this->kycDomCom->kyc_form??null, 'customer'=>$this->customer])
                </div>

                <!-- Data protection form -->
                <div class="col-xs-2 col-sm-2 col-md-2 mt-4">
                    @livewire('common.image-upload',['imageTitle'=>'Data protection form','imageField'=>'data_protection_form','imageExist'=>$this->kycDomCom->data_protection_form??null, 'customer'=>$this->customer])
                </div>

                <!-- Omang ID Front  -->
                <div class="col-xs-2 col-sm-2 col-md-2 mt-4">
                    @livewire('common.image-upload',['imageTitle'=>'Omang ID Front','imageField'=>'omang','imageExist'=>$this->kyc->omang??null, 'customer'=>$this->customer])
                </div>

                <!-- Omang ID Back -->
                <div class="col-xs-2 col-sm-2 col-md-2 mt-4">
                    @livewire('common.image-upload',['imageTitle'=>'Omang ID Back','imageField'=>'omangBack','imageExist'=>$this->kyc->omangBack??null, 'customer'=>$this->customer])
                </div>

                <!-- Proof of Residence -->
                <div class="col-xs-2 col-sm-2 col-md-2 mt-4">
                @livewire('common.image-upload',['imageTitle'=>'Proof of Residence','imageField'=>'proof_residence','imageExist'=>$this->kyc->proof_residence??null, 'customer'=>$this->customer])
                </div>

                <!-- Proof Of Income -->
                <div class="col-xs-2 col-sm-2 col-md-2 mt-4">
                @livewire('common.image-upload',['imageTitle'=>'Proof Of Income','imageField'=>'proof_income','imageExist'=>$this->kyc->proof_income??null, 'customer'=>$this->customer])
                </div>

                <!-- Passport -->
                <div class="col-xs-2 col-sm-2 col-md-2 mt-4">
                @livewire('common.image-upload',['imageTitle'=>'Passport','imageField'=>'passport','imageExist'=>$this->kyc->passport??null, 'customer'=>$this->customer])
                </div>
            </div>
        @else
            <div class="row">
                <!-- Certificate of Incorporation/Registration -->
                <div class="col-xs-2 col-sm-2 col-md-2 mt-4">
                    @livewire('common.image-upload', ['imageTitle' => 'Certificate of Incorporation/Registration', 'imageField' => 'certificate_of_incorporation', 'imageExist' => $this->kycDomCom->certificate_of_incorporation ?? null, 'customer'=>$this->customer])
                </div>

                <!-- Extract controllers and ownership structure -->
                <div class="col-xs-2 col-sm-2 col-md-2 mt-4">
                    @livewire('common.image-upload', ['imageTitle' => 'Extract controllers and ownership structure', 'imageField' => 'extract_controllers', 'imageExist' => $this->kycDomCom->extract_controllers ?? null, 'customer'=>$this->customer])
                </div>

                <!-- KYC form -->
                <div class="col-xs-2 col-sm-2 col-md-2 mt-4">
                    @livewire('common.image-upload', ['imageTitle' => 'KYC form', 'imageField' => 'kyc_form', 'imageExist' => $this->kycDomCom->kyc_form ?? null, 'customer'=>$this->customer])
                </div>

                <!-- Data Protection form -->
                <div class="col-xs-2 col-sm-2 col-md-2 mt-4">
                    @livewire('common.image-upload', ['imageTitle' => 'Data Protection form', 'imageField' => 'data_protection_form', 'imageExist' => $this->kycDomCom->data_protection_form ?? null, 'customer'=>$this->customer])
                </div>

                <!-- Resolution -->
                <div class="col-xs-2 col-sm-2 col-md-2 mt-4">
                    @livewire('common.image-upload', ['imageTitle' => 'Resolution', 'imageField' => 'resolution', 'imageExist' => $this->kycDomCom->resolution ?? null, 'customer'=>$this->customer])
                </div>

                <!-- Proof of business address -->
                <div class="col-xs-2 col-sm-2 col-md-2 mt-4">
                    @livewire('common.image-upload', ['imageTitle' => 'Proof of business address', 'imageField' => 'proof_business_address', 'imageExist' => $this->kycDomCom->proof_business_address ?? null, 'customer'=>$this->customer])
                </div>

                <!-- Proof of residential address for Directors and Shareholders -->
                <div class="col-xs-2 col-sm-2 col-md-2 mt-4">
                    @livewire('common.image-upload', ['imageTitle' => 'Proof of residential address for Directors and Shareholders', 'imageField' => 'proof_residential_address', 'imageExist' => $this->kycDomCom->proof_residential_address ?? null, 'customer'=>$this->customer])
                </div>

                <!-- Directors ID front -->
                <div class="col-xs-2 col-sm-2 col-md-2 mt-4">
                    @livewire('common.image-upload', ['imageTitle' => 'Directors ID front', 'imageField' => 'directors_id_front', 'imageExist' => $this->kycDomCom->directors_id_front ?? null, 'customer'=>$this->customer])
                </div>

                <!-- Directors ID back -->
                <div class="col-xs-2 col-sm-2 col-md-2 mt-4">
                    @livewire('common.image-upload', ['imageTitle' => 'Directors ID back', 'imageField' => 'directors_id_back', 'imageExist' => $this->kycDomCom->directors_id_back ?? null, 'customer'=>$this->customer])
                </div>

                <!-- Director’s passport -->
                <div class="col-xs-2 col-sm-2 col-md-2 mt-4">
                    @livewire('common.image-upload', ['imageTitle' => 'Director’s passport', 'imageField' => 'directors_passport', 'imageExist' => $this->kycDomCom->directors_passport ?? null, 'customer'=>$this->customer])
                </div>

                <!-- Shareholders ID front -->
                <div class="col-xs-2 col-sm-2 col-md-2 mt-4">
                    @livewire('common.image-upload', ['imageTitle' => 'Shareholders ID front', 'imageField' => 'shareholders_id_front', 'imageExist' => $this->kycDomCom->shareholders_id_front ?? null, 'customer'=>$this->customer])
                </div>

                <!-- Shareholders ID back -->
                <div class="col-xs-2 col-sm-2 col-md-2 mt-4">
                    @livewire('common.image-upload', ['imageTitle' => 'Shareholders ID back', 'imageField' => 'shareholders_id_back', 'imageExist' => $this->kycDomCom->shareholders_id_back ?? null, 'customer'=>$this->customer])
                </div>

                <!-- Shareholders passport -->
                <div class="col-xs-2 col-sm-2 col-md-2 mt-4">
                    @livewire('common.image-upload', ['imageTitle' => 'Shareholders passport', 'imageField' => 'shareholders_passport', 'imageExist' => $this->kycDomCom->shareholders_passport ?? null, 'customer'=>$this->customer])
                </div>
            </div>

        @endif

        {{-- <div class="row">
            <!-- Driving License -->
            <div class="col-xs-2 col-sm-2 col-md-2 mt-4">
                @livewire('common.image-upload',['imageTitle'=>'Driving License','imageField'=>'driving_license','imageExist'=>$this->kyc->driving_license??null])
            </div>

            <!-- Omang ID Front  -->
            <div class="col-xs-2 col-sm-2 col-md-2 mt-4">
                @livewire('common.image-upload',['imageTitle'=>'Omang ID Front','imageField'=>'omang','imageExist'=>$this->kyc->omang??null])
            </div>

            <!-- Omang ID Back -->
            <div class="col-xs-2 col-sm-2 col-md-2 mt-4">
                @livewire('common.image-upload',['imageTitle'=>'Omang ID Back','imageField'=>'omangBack','imageExist'=>$this->kyc->omangBack??null])
            </div>

            <!-- Proof of Residence -->
            <div class="col-xs-2 col-sm-2 col-md-2 mt-4">
            @livewire('common.image-upload',['imageTitle'=>'Proof of Residence','imageField'=>'proof_residence','imageExist'=>$this->kyc->proof_residence??null])
            </div>

            <!-- Proof Of Income -->
            <div class="col-xs-2 col-sm-2 col-md-2 mt-4">
            @livewire('common.image-upload',['imageTitle'=>'Proof Of Income','imageField'=>'proof_income','imageExist'=>$this->kyc->proof_income??null])
            </div>

            <!-- Passport -->
            <div class="col-xs-2 col-sm-2 col-md-2 mt-4">
            @livewire('common.image-upload',['imageTitle'=>'Passport','imageField'=>'passport','imageExist'=>$this->kyc->passport??null])
            </div>
        </div> --}}

        <div class="row">
            <div class="col-xs-12 col-sm-12 col-md-12 mt-5 text-center">
                <button type="submit" value="Submit" class="btn btn-primary btn-sm">Submit</button>
            </div>
        </div>
    </form>

</div>

{{-- <div class="container mt-5">
    <h3 class="mb-4">KYC History (Static Accordion)</h3>

    <div class="accordion" id="kycAccordion">
        <!-- Example Year Block 1 -->
        <div class="accordion-item">
            <h2 class="accordion-header" id="heading2024">
                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapse2024" aria-expanded="true" aria-controls="collapse2024">
                    2024-12-18 to 2025-06-25
                </button>
            </h2>
            <div id="collapse2024" class="accordion-collapse collapse show" aria-labelledby="heading2024" data-bs-parent="#kycAccordion">
                <div class="accordion-body">
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <div class="p-3 border rounded bg-white shadow-sm h-100">
                                <strong>KYC Form</strong><br>
                                <a href="#" class="text-primary">View</a><br>
                                <small class="text-muted">2025-04-09</small>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="p-3 border rounded bg-white shadow-sm h-100">
                                <strong>Data Protection Form</strong><br>
                                <a href="#" class="text-primary">View</a><br>
                                <small class="text-muted">2025-04-09</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Example Year Block 2 -->
        <div class="accordion-item">
            <h2 class="accordion-header" id="heading2023">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse2023" aria-expanded="false" aria-controls="collapse2023">
                    2023-01-01 to 2023-12-31
                </button>
            </h2>
            <div id="collapse2023" class="accordion-collapse collapse" aria-labelledby="heading2023" data-bs-parent="#kycAccordion">
                <div class="accordion-body">
                    <p class="text-muted">No documents found.</p>
                </div>
            </div>
        </div>
    </div>
</div> --}}



{{-- <div class="container mt-5">
    <h3 class="mb-4">KYC History</h3>

    <div class="accordion" id="kycAccordion">
        @foreach ($kycHistoryByYear as $yearLabel => $documents)
        <div class="accordion-item">
            <h2 class="accordion-header" id="heading-{{ md5($yearLabel) }}">
                <button class="accordion-button {{ !$loop->first ? 'collapsed' : '' }}" type="button"
                    data-bs-toggle="collapse"
                    data-bs-target="#collapse-{{ md5($yearLabel) }}"
                    aria-expanded="{{ $loop->first ? 'true' : 'false' }}"
                    aria-controls="collapse-{{ md5($yearLabel) }}">
                    {{ $yearLabel }}
                </button>
            </h2>
            <div id="collapse-{{ md5($yearLabel) }}"
                class="accordion-collapse collapse {{ $loop->first ? 'show' : '' }}"
                aria-labelledby="heading-{{ md5($yearLabel) }}"
                data-bs-parent="#kycAccordion">
                <div class="accordion-body">
                    @if (!empty($documents))
                        <div class="row">
                            @foreach ($documents as $doc)
                                <div class="col-md-3 mb-3">
                                    <div class="p-3 border rounded bg-white shadow-sm h-100">
                                        <strong>{{ $doc['type'] }}</strong><br>
                                        <a href="{{ asset($doc['url']) }}" class="text-primary" target="_blank">View</a><br>
                                        <small class="text-muted">{{ $doc['created_at'] }}</small>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-muted">No documents found.</p>
                    @endif
                </div>
            </div>
        </div>
        @endforeach
    </div>
</div> --}}
