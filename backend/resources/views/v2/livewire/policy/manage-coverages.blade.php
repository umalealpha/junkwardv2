<div>
        {{-- Global Utility Functions for Parsing Numeric Values --}}
        <script>
        window.parseAmount = function(value) {
            if (!value || value === '') return 0;
            const cleaned = String(value).replace(/,/g, '').replace(/\s/g, '').trim();
            const parsed = parseFloat(cleaned);
            return isNaN(parsed) || !isFinite(parsed) ? 0 : parsed;
        };

        window.formatAmount = function(value, decimals = 2) {
            const num = typeof value === 'string' ? window.parseAmount(value) : parseFloat(value);
            if (isNaN(num)) return '0.' + '0'.repeat(decimals);
            return num.toLocaleString('en-US', {
                minimumFractionDigits: decimals,
                maximumFractionDigits: decimals
            });
        };

        // Bidirectional calculation: SI ↔ Rate ↔ Premium
        // Premium = (SI × Rate) / 100
        // Rate = (Premium × 100) / SI
        // SI = (Premium × 100) / Rate
        window.attachCalculationListeners = function(containerId) {
            const container = document.getElementById(containerId);
            if (!container) return;

            const siInputs = container.querySelectorAll('input[name*=".coverage_value]"], input[name*=".ratefactor_value]"]');
            const rateInputs = container.querySelectorAll('input[name*=".ratefactor_value]"]');
            const premiumInputs = container.querySelectorAll('input[name*=".calculated_value]"]');

            const attachListeners = (inputs) => {
                inputs.forEach(input => {
                    if (input.hasCalculationListener) return;

                    input.addEventListener('blur', function() {
                        setTimeout(() => {
                            const siField = container.querySelector('input[name*=".coverage_value]"]') ||
                                           container.querySelector('input[name*=".ratefactor_value]"]');
                            const rateField = container.querySelector('input[name*=".ratefactor_value]"]');
                            const premiumField = container.querySelector('input[name*=".calculated_value]"]');

                            if (!siField || !rateField || !premiumField) return;

                            const si = window.parseAmount(siField.value);
                            const rate = window.parseAmount(rateField.value);
                            const premium = window.parseAmount(premiumField.value);

                            // Determine which field changed and calculate the others
                            if (this === siField && rate > 0) {
                                // SI changed: calculate Premium
                                const newPremium = (si * rate) / 100;
                                premiumField.value = window.formatAmount(newPremium);
                            } else if (this === rateField && si > 0) {
                                // Rate changed: calculate Premium
                                const newPremium = (si * rate) / 100;
                                premiumField.value = window.formatAmount(newPremium);
                            } else if (this === premiumField && si > 0) {
                                // Premium changed: calculate Rate
                                const newRate = (premium * 100) / si;
                                rateField.value = window.formatAmount(newRate);
                            }
                        }, 50);
                    });

                    input.hasCalculationListener = true;
                });
            };

            attachListeners(siInputs);
            attachListeners(rateInputs);
            attachListeners(premiumInputs);
        };
        </script>

        @if($this->policyAction->transaction_type=='ENDORSE')
        <div class="col-sm-12">
        <span style="color:red;">Endorsment Reason: {{$this->policyAction->note}}</span>
        </div>
        @endif
    <div wire:loading.class="page-loading" x-data="{ 'showtab':0,Modaltitle:'Modal' }">
        <div class="page-loader flex-column bg-dark bg-opacity-25 ">
            <span class="spinner-border text-primary" role="status"></span>
            <span class="text-gray-800 fs-6 fw-semibold mt-5">Loading...</span>
        </div>
      
        <form wire:submit.prevent="addMainCoverage" autocomplete="off">
            <div class="container">
                <div class="row justify-content-md-center">
                    <div class="col col-lg-3">
                        <div class="form-floating mb-3">
                            <x-select-search wire:model.defer="policyCoverage.risk_address_id"  id="risk_id"
                                             aria-label="Select Risk Address" :options="$this->allRiskAddress ?? []"	/>
                            <x-form-label for="policyCoverage.risk_address_id" required value="{{ __('Select Risk Address') }}"/>
                            <x-form-input-error name="policyCoverage.risk_address_id"/>
                        </div>
                    </div>
                    <div class="col col-lg-3">
                        <div class="form-floating mb-3">
                            <x-select-search :options="$this->AllMainCoverages" wire:model.defer='policyCoverage.coverage_id' />
                            <x-form-label for="policyCoverage.coverage_id" required value="{{ __('Select Main Coverage') }}" />
                            <x-form-input-error name="policyCoverage.coverage_id"/>
                        </div>
                    </div>
                    <div class="col col-lg-2">
                        <input type="submit" class="btn btn-primary" value="Add" id="addMainCoverageBtn">
                    </div>
                </div>
            </div>
        </form>

        <form wire:submit.prevent="submit" autocomplete="off">
            <div class="row">

                 @php $riskAddressId=''; @endphp
                @foreach($this->PolicyCoverages as $index => $policyCoverage)
                   @php
                    if ($riskAddressId != $policyCoverage->risk_address_id) {

                        // Close previous accordion
                        if (!empty($riskAddressId)) {
                @endphp
                                </div>
                            </div>
                        </div>
                    </div>
                @php
                        }
                @endphp

                        <div class="accordion" id="riskAddressAccordion_{{ $policyCoverage->risk_address_id }}">
                            <div class="accordion-item">
                            <h2 class="accordion-header" id="heading{{ $policyCoverage->risk_address_id }}">
                    <button
                        class="accordion-button fs-4 fw-semibold d-flex align-items-center"
                        type="button"
                        @if($policyCoverage->riskAddress->status != 1)
                                    data-bs-toggle="collapse"
                                    data-bs-target="#collapse{{ $policyCoverage->risk_address_id }}"
                                    aria-expanded="true"
                            @endif
                        aria-controls="collapse{{ $policyCoverage->risk_address_id }}">

                        <span>
                            {{ $policyCoverage->riskAddress->address_name }}
                        </span>

                        <span>
                            @if($policyCoverage->riskAddress->status != NULL)
                                <a wire:click.prevent.stop="reinstateRiskAddress('{{ $policyCoverage->riskAddress->id }}')"
                                class="btn btn-sm btn-outline-success">
                                    Reinstate
                                </a>
                            @else
                                <a wire:click.prevent.stop="cancelRiskAddress('{{ $policyCoverage->riskAddress->id }}')"
                                class="btn btn-sm btn-outline-danger">
                                    Cancel
                                </a>
                            @endif
                        </span>

                    </button>
                </h2>
                                <div id="collapse{{ $policyCoverage->risk_address_id }}"
                                    class="accordion-collapse collapse {{ static::RISKACCORDIONCOLLAPSE }}"
                                    aria-labelledby="heading{{ $policyCoverage->risk_address_id }}"
                                    data-bs-parent="#riskAccordion">

                                    <div class="accordion-body">
                @php
                    }
                    $riskAddressId = $policyCoverage->risk_address_id;
                    $coverage = $policyCoverage->coverage;
                    $header = "";
                @endphp
                        <div class="accordion" id="riskAccordion_{{$policyCoverage->risk_address_id}}" wire:ignore.self wire:key="accodion_{{ $riskAddressId }}_{{ $policyCoverage->id }}">
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="heading{{ $policyCoverage->risk_address_id."_".$policyCoverage->id }}" >
                                    <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapse{{ $policyCoverage->risk_address_id."_".$policyCoverage->id }}" aria-expanded="true" aria-controls="collapse{{ $policyCoverage->risk_address_id."_".$policyCoverage->id }}">
                                    {{ $policyCoverage->coverage['s_CoverageCode'] }}

                                        {{

                                            isset($this->policyCoverageEntity[$policyCoverage->id]['Vehicle']) && !empty($this->policyCoverageEntity[$policyCoverage->id]['Vehicle']) ?
                                            " / ".($this->AllVehicles[$this->policyCoverageEntity[$policyCoverage->id]['Vehicle']] ?? null) : ''
                                        }}
                                        {{
                                            isset($this->policyCoverageEntity[$policyCoverage->id]['Member']) && !empty($this->policyCoverageEntity[$policyCoverage->id]['Member']) ?
                                            " / ".($this->AllBeneficiaries[$this->policyCoverageEntity[$policyCoverage->id]['Member']] ?? null) : ''
                                        }}
                                        {{
                                            isset($this->policyCoverageEntity[$policyCoverage->id]['Device']) && !empty($this->policyCoverageEntity[$policyCoverage->id]['Device']) ?
                                            " / ".($this->AllDevices[$this->policyCoverageEntity[$policyCoverage->id]['Device']] ?? null) : ''
                                        }}
                                        <a wire:click.prevent="deleteCoverage('{{ $policyCoverage->id }}')"  class="btn  btn-sm btn-outline-danger btn-active-light-danger" style="position: absolute;right: 35px;">
                                            <span class="svg-icon svg-icon-2 m-0"> <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg></span>
                                        </a>
                                        @if($policyCoverage->status==0)
                                        <a wire:click.prevent="cancelCoverage('{{ $policyCoverage->id }}')"  
                                        class="btn btn-sm btn-outline-danger btn-active-light-danger"  
                                        style="position: absolute;right: 75px;">
                                        Cancel
                                        </a>
                                        @elseif($policyCoverage->status==1 && $policyCoverage->riskAddress->status != 1)
                                        <a wire:click.prevent="reinstateCoverage('{{ $policyCoverage->id }}')"  
                                        class="btn btn-sm btn-outline-danger btn-active-light-danger"  
                                        style="position: absolute;right: 75px;">
                                        Reinstate
                                        </a>
                                        @endif
                                    </button>
                                </h2>
                               @if(($policyCoverage->coverage['s_CoverageCode'] =='PUBLICLIABILITY') || $policyCoverage->coverage_id == 21)
<div style="background-color: #f8f9fa; padding: 20px; margin-bottom: 20px; border-left: 4px solid #007bff;">
    <div class="row">  
        <div class="col-md-4">
            <div class="form-floating mb-3">
                <x-form-date type="text" class="kt_datepicker_2" id="publicliability_date"  wire:model="publicliability_date.publicliability_date"  placeholder="publicliability Date" />
                <x-form-label for="publicliability_date" required value="Retroactive Date"/>
                <x-form-input-error name="publicliability_date.publicliability_date"/>
            </div>
        </div>
    </div>
</div>
@endif
                                <div id="collapse{{ $policyCoverage->risk_address_id.'_'.$policyCoverage->id }}" class="accordion-collapse collapse {{ static::COVERSGEACCORDIONCOLLAPSE }}" aria-labelledby="heading{{ $policyCoverage->risk_address_id."_".$policyCoverage->id }}" data-bs-parent="#riskAccordion_{{$policyCoverage->risk_address_id}}" wire:ignore.self>
                                    <div class="accordion-body show" wire:ignore.self>
                                        @if($policyCoverage->status==0)
                                        @if(($policyCoverage->coverage['s_CoverageCode'] == "PERSONALMOTOR" ||
                                            $policyCoverage->coverage['s_CoverageCode'] == "COMMERCIALMOTOR")
                                            && !in_array($policyCoverage->coverage_id, [15, 16]))
                                            @include('v2.livewire.policy.add-coverage-add-ons')
                                        @endif

                                        {{-- Start MOTOR --}}
                                        @if (($policyCoverage->coverage['s_CoverageCode'] == "PERSONALMOTOR" || $policyCoverage->coverage['s_CoverageCode'] == "COMMERCIALMOTOR") && !in_array($policyCoverage->coverage_id, [15, 16]))
                                        <div>
                                            <div class="row">
                                                <div class="col-sm-12">
                                                    <div class='card card-custom gutter-b'>
                                                        <div class="card-header">
                                                            <div class="card-title">
                                                                <h2 class="">Summary Of Vehicles</h2>
                                                            </div>
                                                        </div>
                                                        <div class="card-body">
                                                            <div class="col-sm-12">
                                                                <div class="table-responsive">
                                                                    <table class="table align-middle table-row-dashed fs-6 gy-5 dataTable no-footer">
                                                                        <thead>
                                                                            <tr class="text-start fw-bold fs-7 text-uppercase gs-0">
                                                                                <th>Description</th>
                                                                                <th>Registration No.</th>
                                                                                <th>Engine No.</th>
                                                                                <th>Chassis No.</th>
                                                                                <th>Estimated Value</th>
                                                                                <th>Usage</th>
                                                                                <th>Type of Cover </th>
                                                                                <th>Sum Insured</th>
                                                                                <th>Premium</th>
                                                                                <th>Action</th>
                                                                            </tr>
                                                                        </thead>
                                                                        <tbody class="fw-semibold text-gray-600">
                                                                            @php
                                                                                $i=1;
                                                                            @endphp
                                                                            @forelse($this->getallVehicles($riskAddressId) as $vehicle)
                                                                            @if($vehicle->risk_id == $riskAddressId)
                                                                                <tr class="{!! ($loop->iteration % 2 == 0)?'even':'odd' !!}">
                                                                                    <td>{{$vehicle->make ?? ""}} {{$vehicle->model ?? ""}}</td>
                                                                                    <td style="text-transform: uppercase">{{$vehicle->vehiclePlate ?? ""}}</td>
                                                                                    <td>{{$vehicle->engineNo ?? ""}}</td>
                                                                                    <td>{{$vehicle->chassisNo ?? ""}}</td>
                                                                                    <td>{{ number_format((float)$vehicle->estimated_value ?? "", 2, '.', ',') }}</td>
                                                                                    <td>
                                                                                        @if($policyCoverage->coverage['s_CoverageCode'] == "PERSONALMOTOR")
                                                                                            <select title="Use" wire:model.defer="motorVehicleData.use_main_{{ $vehicle->vehiclePlate.'_'.$policyCoverage->id }}" aria-label="Select Use" class="form-select form-select-solid">
                                                                                                <option value="">-Select-</option>
                                                                                                <option value="Personal">Personal</option>
                                                                                                <option value="Normal operations">Normal operations</option>
                                                                                                <option value="Heavy duty">Heavy duty</option>
                                                                                                <option value="Transport">Transport</option>
                                                                                            </select>
                                                                                            <x-form-input-error name="motorVehicleData.use_main"/>
                                                                                        @elseif($policyCoverage->coverage['s_CoverageCode'] == "COMMERCIALMOTOR")
                                                                                            <select title="Use" wire:model.defer="motorVehicleData.use_main_{{ $vehicle->vehiclePlate.'_'.$policyCoverage->id }}" aria-label="Select Use" class="form-select form-select-solid">
                                                                                                <option value="">-Select-</option>
                                                                                                <option value="Personal">Personal</option>
                                                                                                <option value="Business">Business</option>
                                                                                            </select>
                                                                                            <x-form-input-error name="motorVehicleData.use_main"/>
                                                                                        @endif
                                                                                    </td>
                                                                                    <td>
                                                                                        <select title="Type of cover" wire:model.defer="motorVehicleData.type_of_cover_main_{{ $vehicle->vehiclePlate.'_'.$policyCoverage->id }}" aria-label="Select Type of cover" wire:change="SummaryOfVehicles($event.target.value,{{$vehicle}},{{$policyCoverage->id}})"  class="form-select form-select-solid type_of_cover_main">
                                                                                            <option value="">-Select-</option>
                                                                                            <option value="Comprehensive">Comprehensive</option>
                                                                                            <option value="third_party_only">Third party only</option>
                                                                                            <option value="Third_fire_and_theft">Third party, fire and theft</option>
                                                                                        </select>
                                                                                        <x-form-input-error name="motorVehicleData.type_of_cover_main"/>
                                                                                    </td>
                                                                                    <td>
                                                                                        <input title="Sum Insured"  type="text" id="input1" class="form-control amount-field" wire:model.defer="motorVehicleData.coverage_value_main_{{ $vehicle->vehiclePlate.'_'.$policyCoverage->id }}" wire:keyup="SumInsuredOfVehicles($event.target.value,{{$vehicle}},{{$policyCoverage->id}})"
                                                                                        placeholder="Sum Insured" class="amount-field" @blur="$el.value = window.formatAmount($el.value)" />
                                                                                        <x-form-input-error name="motorVehicleData.coverage_value_main_"/>
                                                                                    </td>
                                                                                    <td>
                                                                                        <input title="calculated value" type="text" id="input3" class="form-control" wire:model.defer="motorVehicleData.calculated_value_main_{{ $vehicle->vehiclePlate.'_'.$policyCoverage->id }}" wire:keyup="PremiumOfVehicles($event.target.value,{{$vehicle}},{{$policyCoverage->id}})"
                                                                                        placeholder="Premium" @blur="$el.value = window.formatAmount($el.value)"/>
                                                                                        <x-form-input-error name="motorVehicleData.calculated_value_main_"/>
                                                                                    </td>
                                                                                    @php
                                                                                    $personalMotorData = AlphaDirect\Models\Motor::where('policy_coverage_id',$policyCoverage->id)->where('registration_no',$vehicle->vehiclePlate)->orderBy('id', 'asc')->first();
                                                                                    @endphp

                                                                                    <td>
                                                                                        @if(isset($personalMotorData) && $personalMotorData['type_of_cover'] == "Comprehensive")
                                                                                           @if($personalMotorData['deleted_at'] != null)
                                                                                            @if($personalMotorData['write_off'] == 1)
                                                                                                @can('policy_reinstate')
                                                                                                    <button
                                                                                                        class="btn btn-primary"
                                                                                                        value="Comprehensive"
                                                                                                        wire:click="ReinstateOfVehicles(
                                                                                                            $event.target.value,
                                                                                                            {{ $personalMotorData['id'] }},
                                                                                                            {{ $policyCoverage->id }}
                                                                                                        )">
                                                                                                        Reinstate
                                                                                                    </button>
                                                                                                @endcan
                                                                                            @else
                                                                                                <button
                                                                                                    class="btn btn-primary"
                                                                                                    value="Comprehensive"
                                                                                                    wire:click="ReinstateOfVehicles(
                                                                                                        $event.target.value,
                                                                                                        {{ $personalMotorData['id'] }},
                                                                                                        {{ $policyCoverage->id }}
                                                                                                    )">
                                                                                                    Reinstate
                                                                                                </button>
                                                                                            @endif
                                                                                            @else
                                                                                            <button class="btn btn-primary" value="Comprehensive" wire:click.prevent="SummaryOfVehicles($event.target.value,{{$vehicle}},{{$policyCoverage->id}})" >View</button>&nbsp;
                                                                                            <button class="btn btn-primary" value="Comprehensive" wire:click="DeleteOfVehicles($event.target.value,{{ $personalMotorData['id']}},{{$policyCoverage->id}})" >Cancel</button>
                                                                                            @endif
                                                                                        @elseif(isset($personalMotorData) && $personalMotorData['type_of_cover'] == "third_party_only")
                                                                                            @if($personalMotorData['deleted_at'] != null)
                                                                                            @if($personalMotorData['write_off'] == 1)
                                                                                                @can('policy_reinstate')
                                                                                                    <button
                                                                                                        class="btn btn-primary"
                                                                                                        value="Comprehensive"
                                                                                                        wire:click="ReinstateOfVehicles(
                                                                                                            $event.target.value,
                                                                                                            {{ $personalMotorData['id'] }},
                                                                                                            {{ $policyCoverage->id }}
                                                                                                        )">
                                                                                                        Reinstate
                                                                                                    </button>
                                                                                                @endcan
                                                                                            @else
                                                                                                <button
                                                                                                    class="btn btn-primary"
                                                                                                    value="Comprehensive"
                                                                                                    wire:click="ReinstateOfVehicles(
                                                                                                        $event.target.value,
                                                                                                        {{ $personalMotorData['id'] }},
                                                                                                        {{ $policyCoverage->id }}
                                                                                                    )">
                                                                                                    Reinstate
                                                                                                </button>
                                                                                            @endif                                                                                           
                                                                                            @else
                                                                                            <button class="btn btn-primary" value="third_party_only" wire:click.prevent="SummaryOfVehicles($event.target.value,{{$vehicle}},{{$policyCoverage->id}})" >View</button>&nbsp;
                                                                                            <button class="btn btn-primary" value="third_party_only" wire:click="DeleteOfVehicles($event.target.value,{{$personalMotorData['id']}},{{$policyCoverage->id}})" >Cancel</button>
                                                                                            @endif
                                                                                        @elseif(isset($personalMotorData) && $personalMotorData['type_of_cover'] == "Third_fire_and_theft")
                                                                                            @if($personalMotorData['deleted_at'] != null)
                                                                                            @if($personalMotorData['write_off'] == 1)
                                                                                                @can('policy_reinstate')
                                                                                                    <button
                                                                                                        class="btn btn-primary"
                                                                                                        value="Comprehensive"
                                                                                                        wire:click="ReinstateOfVehicles(
                                                                                                            $event.target.value,
                                                                                                            {{ $personalMotorData['id'] }},
                                                                                                            {{ $policyCoverage->id }}
                                                                                                        )">
                                                                                                        Reinstate
                                                                                                    </button>
                                                                                                @endcan
                                                                                            @else
                                                                                                <button
                                                                                                    class="btn btn-primary"
                                                                                                    value="Comprehensive"
                                                                                                    wire:click="ReinstateOfVehicles(
                                                                                                        $event.target.value,
                                                                                                        {{ $personalMotorData['id'] }},
                                                                                                        {{ $policyCoverage->id }}
                                                                                                    )">
                                                                                                    Reinstate
                                                                                                </button>
                                                                                            @endif                                                                                           
                                                                                            @else
                                                                                            <button class="btn btn-primary" value="Third_fire_and_theft" wire:click="SummaryOfVehicles($event.target.value,{{$vehicle}},{{$policyCoverage->id}})" >View</button>&nbsp;
                                                                                            <button class="btn btn-primary" value="Third_fire_and_theft" wire:click="DeleteOfVehicles($event.target.value,{{$personalMotorData['id']}},{{$policyCoverage->id}})" >Cancel</button>
                                                                                            @endif
                                                                                        @else
                                                                                                -
                                                                                        @endif
                                                                                    </td>
                                                                                </tr>
                                                                            @endif
                                                                            @empty
                                                                                <tr><td colspan=7>Vehicle Details Not Present ..</td></tr>
                                                                            @endforelse
                                                                        </tbody>
                                                                    </table>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <hr>
                                            <div class="row">
                                                <div class="col-sm-12">
                                                    <div class='card card-custom gutter-b'>
                                                        <div class="card-header">
                                                            <div class="card-title">
                                                                <h2 class="Comprehensive_section" @if(!$Comprehensive_section) style="display: none;" @endif>
                                                                    Motor Comprehensive
                                                                </h2>
                                                                <h2 class="third_party_only_section" @if(!$third_party_only_section) style="display: none;" @endif>
                                                                    Third party only
                                                                </h2>
                                                                <h2 class="Third_fire_and_theft_section" @if(!$Third_fire_and_theft_section) style="display: none;" @endif>
                                                                    Third party, fire and theft
                                                                </h2>
                                                            </div>
                                                        </div>
                                                        {{-- Comprehensive_section --}}
                                                        <div class="card-body Comprehensive_section" @if(!$Comprehensive_section) style="display: none;" @endif>
                                                            <input type="hidden"  title="policy_coverage_id" wire:model.defer="motorVehicleData.policy_coverage_id" class="form-control"
                                                                        id="motorVehicleData.policy_coverage_id" placeholder="policy_coverage_id" />
                                                            <input type="hidden" wire:model.defer="motor.estimated_value"  placeholder="estimated_value" class="form-control">
                                                            <input type="hidden" wire:model.defer="motorVehicleData.motor_id"  placeholder="motr_id" class="form-control">

                                                            <div class="row">
                                                                <div class="col-sm-4">
                                                                    <div class="form-floating mb-3">
                                                                        <input title="Vehicle Name" wire:model.defer="motorVehicleData.vehicle_name" class="form-control"
                                                                        id="motorVehicleData.vehicle_name" placeholder="Vehicle Name" disabled/>
                                                                        <x-form-label for="make" required value="{{ __('Vehicle Name') }}"/>
                                                                        <x-form-input-error name="motorVehicleData.vehicle_name"/>
                                                                    </div>
                                                                </div>
                                                                <div class="col-sm-4">
                                                                    <div class="form-floating mb-3">
                                                                        <input title="Sum Insured" id="input2" class="form-control amount-field" wire:model.defer="motorVehicleData.coverage_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                        id="motorVehicleData.coverage_value" placeholder="Sum Insured" disabled/>
                                                                        <x-form-label for="coverage_value" required value="{{ __('Sum Insured') }}"/>
                                                                        <x-form-input-error name="motorVehicleData.coverage_value"/>
                                                                    </div>
                                                                </div>
                                                                <div class="col-sm-4">
                                                                    <div class="form-floating mb-3">
                                                                        <input title="calculated_value" type="text" id="input4" wire:model.defer="motorVehicleData.calculated_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                        placeholder="Premium" class="form-control amount-field" disabled/>
                                                                        <x-form-label for="calculated_value" required value="{{ __('Premium') }}"/>
                                                                        <x-form-input-error name="motorVehicleData.calculated_value"/>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <hr>
                                                            <div class="row">
                                                                <!-- Comprehensive Vehicle -->
                                                                <h4>Vehicle Details</h4>
                                                                <hr>
                                                                <div class="col-sm-3">
                                                                    <div class="form-floating mb-3">
                                                                        <select wire:model.defer="motor.use" aria-label="Select Use" class="form-select form-select-solid">
                                                                            <option value="">-Select-</option>
                                                                            <option value="Personal">Personal</option>
                                                                            <option value="Normal operations">Normal operations</option>
                                                                            <option value="Heavy duty">Heavy duty</option>
                                                                            <option value="Transport">Transport</option>
                                                                        </select>
                                                                        <x-form-label for="motor.use" value="Select Use" />
                                                                        <x-form-input-error name="motor.use"/>
                                                                    </div>
                                                                </div>
                                                                <div class="col-sm-3">
                                                                    <div class="form-floating mb-3">
                                                                        <input type="text" wire:model.defer="motor.registration_no"  placeholder="Registration number" class="form-control" disabled>
                                                                        <x-form-label for="motor.registration_no" value="Registration number" />
                                                                        <x-form-input-error name="motor.registration_no"/>
                                                                    </div>
                                                                </div>
                                                                <div class="col-sm-3">
                                                                    <div class="form-floating mb-3">
                                                                        <x-form-input class=""  type="text" name="motor.make"  wire:model.defer="motor.make" placeholder="Make" disabled/>
                                                                        <x-form-label for="motor.make"  value="Make"/>
                                                                        <x-form-input-error name="motor.make"/>
                                                                    </div>
                                                                </div>
                                                                <div class="col-sm-3">
                                                                    <div class="form-floating mb-3">
                                                                        <x-form-input class=""  type="text" name="motor.model"  wire:model.defer="motor.model" placeholder="Model" disabled/>
                                                                        <x-form-label for="motor.model"  value="Model"/>
                                                                        <x-form-input-error name="motor.model"/>
                                                                    </div>
                                                                </div>
                                                                <div class="col-sm-3">
                                                                    <div class="form-floating mb-3">
                                                                        <input type="text" wire:model.defer="motor.engine_number"  placeholder="Engine Number" class="form-control" disabled>
                                                                        <x-form-label for="motor.engine_number" value="Engine Number" />
                                                                        <x-form-input-error name="motor.engine_number"/>
                                                                    </div>
                                                                </div>
                                                                <div class="col-sm-3">
                                                                    <div class="form-floating mb-3">
                                                                        <input type="text" wire:model.defer="motor.chassis_number"  placeholder="Chassis Number" class="form-control" disabled>
                                                                        <x-form-label for="motor.chassis_number" value="Chassis Number" />
                                                                        <x-form-input-error name="motor.chassis_number"/>
                                                                    </div>
                                                                </div>
                                                                <div class="col-sm-3">
                                                                    <div class="form-floating mb-3">
                                                                        <input type="text" wire:model.defer="motor.tracking_device" placeholder="Tracking device" class="form-control">
                                                                        <x-form-label for="motor.tracking_device" value="Tracking device" />
                                                                        <x-form-input-error name="motor.tracking_device"/>
                                                                    </div>
                                                                </div>
                                                                <div class="col-sm-3">
                                                                    <div class="form-floating mb-3">
                                                                        <input type="text" wire:model.defer="motor.security_features" placeholder="Security features" class="form-control">
                                                                        <x-form-label for="motor.security_features" value="Security features" />
                                                                        <x-form-input-error name="motor.security_features"/>
                                                                    </div>
                                                                </div>
                                                                <div class="col-sm-3">
                                                                    <div class="form-floating mb-3">
                                                                        <select wire:model.defer="motor.type_of_cover" aria-label="Select Type of cover" class="form-select form-select-solid">
                                                                            <option value="Comprehensive">Comprehensive</option>
                                                                        </select>
                                                                        <x-form-label for="motor.type_of_cover" value="Select Type of cover" />
                                                                        <x-form-input-error name="motor.type_of_cover"/>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <hr>
                                                            {{-- Comprehensive Extensions and Clauses --}}
                                                            @if($policyCoverage->coverage['s_CoverageCode'] == "PERSONALMOTOR")
                                                                <br>
                                                                <div class="row">
                                                                    <h4>Extensions and Clauses</h4>
                                                                    <hr>
                                                                    <div class="row">
                                                                        <div class="col-sm-4">
                                                                            <div class="form-floating mb-3">
                                                                                <label for="question">Wreckage removal</label><br><br>
                                                                                <div class="form-check form-check-inline" style="margin-left: 12px">
                                                                                    <input wire:model.defer="motor.wreckage_removal" id="" name="" value="Yes" type="radio" class="form-check-input" />
                                                                                    <label for="">Yes</label>
                                                                                </div>
                                                                                <div class="form-check form-check-inline" style="margin-left: 2px">
                                                                                    <input wire:model.defer="motor.wreckage_removal" id="" name="" value="No" type="radio" class="form-check-input" />
                                                                                    <label for="">No</label>
                                                                                </div>
                                                                            </div>
                                                                            <div class="form-floating mb-3">
                                                                                <input title="premium_wreckage_removal" type="text" wire:model.defer="motor.premium_wreckage_removal" @blur="$el.value = window.formatAmount($el.value)"placeholder="premium_wreckage_removal" class="form-control"/>
                                                                                <x-form-label for="premium_wreckage_removal" required value="{{ __('Premium') }}"/>
                                                                                <x-form-input-error name="motor.premium_wreckage_removal"/>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-sm-4">
                                                                            <div class="form-floating mb-3">
                                                                                <label for="question">Window glass</label><br><br>
                                                                                <div class="form-check form-check-inline" style="margin-left: 12px">
                                                                                    <input wire:model.defer="motor.window_glass" id="" name="" value="Yes" type="radio" class="form-check-input" />
                                                                                    <label for="">Yes</label>
                                                                                </div>
                                                                                <div class="form-check form-check-inline" style="margin-left: 2px">
                                                                                    <input wire:model.defer="motor.window_glass" id="" name="" value="No" type="radio" class="form-check-input" />
                                                                                    <label for="">No</label>
                                                                                </div>
                                                                            </div>
                                                                            <div class="form-floating mb-3">
                                                                                <input title="premium_window_glass" type="text" wire:model.defer="motor.premium_window_glass" @blur="$el.value = window.formatAmount($el.value)"placeholder="premium_window_glass" class="form-control"/>
                                                                                <x-form-label for="premium_window_glass" required value="{{ __('Premium') }}"/>
                                                                                <x-form-input-error name="motor.premium_window_glass"/>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-sm-4">
                                                                            <div class="form-floating mb-3">
                                                                                <label for="question">Locks and keys</label><br><br>
                                                                                <div class="form-check form-check-inline" style="margin-left: 12px">
                                                                                    <input wire:model.defer="motor.locks_keys" id="" name="" value="Yes" type="radio" class="form-check-input" />
                                                                                    <label for="">Yes</label>
                                                                                </div>
                                                                                <div class="form-check form-check-inline" style="margin-left: 2px">
                                                                                    <input wire:model.defer="motor.locks_keys" id="" name="" value="No" type="radio" class="form-check-input" />
                                                                                    <label for="">No</label>
                                                                                </div>
                                                                            </div>
                                                                            <div class="form-floating mb-3">
                                                                                <input title="premium_locks_keys" type="text" wire:model.defer="motor.premium_locks_keys" @blur="$el.value = window.formatAmount($el.value)"placeholder="premium_locks_keys" class="form-control"/>
                                                                                <x-form-label for="premium_locks_keys" required value="{{ __('Premium') }}"/>
                                                                                <x-form-input-error name="motor.premium_locks_keys"/>
                                                                            </div>
                                                                        </div>
                                                                    </div>

                                                                    <div class="row">
                                                                        <div class="col-sm-4">
                                                                            <div class="form-floating mb-3">
                                                                                <label for="question">Parts or accessories not readily available</label><br><br>
                                                                                <div class="form-check form-check-inline" style="margin-left: 12px">
                                                                                    <input wire:model.defer="motor.parts_accessories" id="" name="" value="Yes" type="radio" class="form-check-input" />
                                                                                    <label for="">Yes</label>
                                                                                </div>
                                                                                <div class="form-check form-check-inline" style="margin-left: 2px">
                                                                                    <input wire:model.defer="motor.parts_accessories" id="" name="" value="No" type="radio" class="form-check-input" />
                                                                                    <label for="">No</label>
                                                                                </div>
                                                                            </div>
                                                                            <div class="form-floating mb-3">
                                                                                <input title="premium_parts_accessories" type="text" wire:model.defer="motor.premium_parts_accessories" @blur="$el.value = window.formatAmount($el.value)"placeholder="premium_parts_accessories" class="form-control"/>
                                                                                <x-form-label for="premium_parts_accessories" required value="{{ __('Premium') }}"/>
                                                                                <x-form-input-error name="motor.premium_parts_accessories"/>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-sm-4">
                                                                            <div class="form-floating mb-3">
                                                                                <label for="question">Audio accessories</label><br><br>
                                                                                <div class="form-check form-check-inline" style="margin-left: 12px">
                                                                                    <input wire:model.defer="motor.audio_accessories" id="" name="" value="Yes" type="radio" class="form-check-input" />
                                                                                    <label for="">Yes</label>
                                                                                </div>
                                                                                <div class="form-check form-check-inline" style="margin-left: 2px">
                                                                                    <input wire:model.defer="motor.audio_accessories" id="" name="" value="No" type="radio" class="form-check-input" />
                                                                                    <label for="">No</label>
                                                                                </div>
                                                                            </div>
                                                                            <div class="form-floating mb-3">
                                                                                <input title="premium_audio_accessories" type="text" wire:model.defer="motor.premium_audio_accessories" @blur="$el.value = window.formatAmount($el.value)"placeholder="premium_audio_accessories" class="form-control"/>
                                                                                <x-form-label for="premium_audio_accessories" required value="{{ __('Premium') }}"/>
                                                                                <x-form-input-error name="motor.premium_audio_accessories"/>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-sm-4">
                                                                            <div class="form-floating mb-3">
                                                                                <label for="question">Riot and strike</label><br><br>
                                                                                <div class="form-check form-check-inline" style="margin-left: 12px">
                                                                                    <input wire:model.defer="motor.riot_strike" id="" name="" value="Yes" type="radio" class="form-check-input" />
                                                                                    <label for="">Yes</label>
                                                                                </div>
                                                                                <div class="form-check form-check-inline" style="margin-left: 2px">
                                                                                    <input wire:model.defer="motor.riot_strike" id="" name="" value="No" type="radio" class="form-check-input" />
                                                                                    <label for="">No</label>
                                                                                </div>
                                                                            </div>
                                                                            <div class="form-floating mb-3">
                                                                                <input title="premium_riot_strike" type="text" wire:model.defer="motor.premium_riot_strike" @blur="$el.value = window.formatAmount($el.value)"placeholder="premium_riot_strike" class="form-control"/>
                                                                                <x-form-label for="premium_riot_strike" required value="{{ __('Premium') }}"/>
                                                                                <x-form-input-error name="motor.premium_riot_strike"/>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    <div class="row">
                                                                        <div class="col-sm-4">
                                                                            <div class="form-floating mb-3">
                                                                                <label for="question">Car hire-theft/hijack of the vehicle</label><br><br>
                                                                                <div class="form-check form-check-inline" style="margin-left: 12px">
                                                                                    <input wire:model.defer="motor.car_hire_theft" id="" name="" value="Yes" type="radio" class="form-check-input" />
                                                                                    <label for="">Yes</label>
                                                                                </div>
                                                                                <div class="form-check form-check-inline" style="margin-left: 2px">
                                                                                    <input wire:model.defer="motor.car_hire_theft" id="" name="" value="No" type="radio" class="form-check-input" />
                                                                                    <label for="">No</label>
                                                                                </div>
                                                                            </div>
                                                                            <div class="form-floating mb-3">
                                                                                <input title="premium_car_hire_theft" type="text" wire:model.defer="motor.premium_car_hire_theft" @blur="$el.value = window.formatAmount($el.value)"placeholder="premium_car_hire_theft" class="form-control"/>
                                                                                <x-form-label for="premium_car_hire_theft" required value="{{ __('Premium') }}"/>
                                                                                <x-form-input-error name="motor.premium_car_hire_theft"/>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-sm-4">
                                                                            <div class="form-floating mb-3">
                                                                                <label for="question">Credit Shortfall</label><br><br>
                                                                                <div class="form-check form-check-inline" style="margin-left: 12px">
                                                                                    <input wire:model.defer="motor.credit_shortfall" id="" name="" value="Yes" type="radio" class="form-check-input" />
                                                                                    <label for="">Yes</label>
                                                                                </div>
                                                                                <div class="form-check form-check-inline" style="margin-left: 2px">
                                                                                    <input wire:model.defer="motor.credit_shortfall" id="" name="" value="No" type="radio" class="form-check-input" />
                                                                                    <label for="">No</label>
                                                                                </div>
                                                                            </div>
                                                                            <div class="form-floating mb-3">
                                                                                <input title="premium_credit_shortfall" type="text" wire:model.defer="motor.premium_credit_shortfall" @blur="$el.value = window.formatAmount($el.value)"placeholder="premium_credit_shortfall" class="form-control"/>
                                                                                <x-form-label for="premium_credit_shortfall" required value="{{ __('Premium') }}"/>
                                                                                <x-form-input-error name="motor.premium_credit_shortfall"/>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-sm-4">
                                                                            <div class="form-floating mb-3">
                                                                                <label for="question">Insured only driver</label><br><br>
                                                                                <div class="form-check form-check-inline" style="margin-left: 12px">
                                                                                    <input wire:model.defer="motor.insured_driver" id="" name="" value="Yes" type="radio" class="form-check-input" />
                                                                                    <label for="">Yes</label>
                                                                                </div>
                                                                                <div class="form-check form-check-inline" style="margin-left: 2px">
                                                                                    <input wire:model.defer="motor.insured_driver" id="" name="" value="No" type="radio" class="form-check-input" />
                                                                                    <label for="">No</label>
                                                                                </div>
                                                                            </div>
                                                                            <div class="form-floating mb-3">
                                                                                <input title="premium_insured_driver" type="text" wire:model.defer="motor.premium_insured_driver" @blur="$el.value = window.formatAmount($el.value)"placeholder="premium_insured_driver" class="form-control"/>
                                                                                <x-form-label for="premium_insured_driver" required value="{{ __('Premium') }}"/>
                                                                                <x-form-input-error name="motor.premium_insured_driver"/>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    <div class="row">
                                                                        <div class="col-sm-4">
                                                                            <div class="form-floating mb-3">
                                                                                <label for="question">Insured and family only drivers</label><br><br>
                                                                                <div class="form-check form-check-inline" style="margin-left: 12px">
                                                                                    <input wire:model.defer="motor.insured_family" id="" name="" value="Yes" type="radio" class="form-check-input" />
                                                                                    <label for="">Yes</label>
                                                                                </div>
                                                                                <div class="form-check form-check-inline" style="margin-left: 2px">
                                                                                    <input wire:model.defer="motor.insured_family" id="" name="" value="No" type="radio" class="form-check-input" />
                                                                                    <label for="">No</label>
                                                                                </div>
                                                                            </div>
                                                                            <div class="form-floating mb-3">
                                                                                <input title="premium_insured_family" type="text" wire:model.defer="motor.premium_insured_family" @blur="$el.value = window.formatAmount($el.value)"placeholder="premium_insured_family" class="form-control"/>
                                                                                <x-form-label for="premium_insured_family" required value="{{ __('Premium') }}"/>
                                                                                <x-form-input-error name="motor.premium_insured_family"/>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-sm-4">
                                                                            <div class="form-floating mb-3">
                                                                                <label for="question">Medical expenses</label><br><br>
                                                                                <div class="form-check form-check-inline" style="margin-left: 12px">
                                                                                    <input wire:model.defer="motor.medical_expenses" id="" name="" value="Yes" type="radio" class="form-check-input" />
                                                                                    <label for="">Yes</label>
                                                                                </div>
                                                                                <div class="form-check form-check-inline" style="margin-left: 2px">
                                                                                    <input wire:model.defer="motor.medical_expenses" id="" name="" value="No" type="radio" class="form-check-input" />
                                                                                    <label for="">No</label>
                                                                                </div>
                                                                            </div>
                                                                            <div class="form-floating mb-3">
                                                                                <input title="premium_medical_expenses" type="text" wire:model.defer="motor.premium_medical_expenses" @blur="$el.value = window.formatAmount($el.value)"placeholder="premium_medical_expenses" class="form-control"/>
                                                                                <x-form-label for="premium_medical_expenses" required value="{{ __('Premium') }}"/>
                                                                                <x-form-input-error name="motor.premium_medical_expenses"/>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                                <label for="question">Passenger liability excluded</label><br><br>
                                                                                <div class="form-check form-check-inline" style="margin-left: 12px">
                                                                                    <input wire:model.defer="motor.passenger_liability" id="" name="" value="Yes" type="radio" class="form-check-input" />
                                                                                    <label for="">Yes</label>
                                                                                </div>
                                                                                <div class="form-check form-check-inline" style="margin-left: 2px">
                                                                                    <input wire:model.defer="motor.passenger_liability" id="" name="" value="No" type="radio" class="form-check-input" />
                                                                                    <label for="">No</label>
                                                                                </div>
                                                                            </div>
                                                                            <div class="form-floating mb-3">
                                                                                <input title="premium_passenger_liability" type="text" wire:model.defer="motor.premium_passenger_liability" @blur="$el.value = window.formatAmount($el.value)"placeholder="premium_passenger_liability" class="form-control"/>
                                                                                <x-form-label for="premium_passenger_liability" required value="{{ __('Premium') }}"/>
                                                                                <x-form-input-error name="motor.premium_passenger_liability"/>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    <div class="row">
                                                                        <div class="col-sm-4">
                                                                            <div class="form-floating mb-3">
                                                                                <label for="question">Third party liability</label><br><br>
                                                                                <input class="form-control" type="text" wire:model.defer="third_party_liability" value="{{$this->third_party_liability}}">
                                                                            </div>

                                                                            <div class="form-floating mb-3">
                                                                                <input title="premium_third_party_liability" type="text" wire:model.defer="motor.premium_third_party_liability" @blur="$el.value = window.formatAmount($el.value)"placeholder="premium_third_party_liability" class="form-control"/>
                                                                                <x-form-label for="premium_third_party_liability" required value="{{ __('Premium') }}"/>
                                                                                <x-form-input-error name="motor.premium_third_party_liability"/>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-sm-4">
                                                                            <div class="form-floating mb-3">
                                                                                <label for="question">Specified accessories</label><br><br>
                                                                                <div class="form-check form-check-inline" style="margin-left: 12px">
                                                                                    <input wire:model.defer="motor.specified_accessories" id="" name="" value="Yes" type="radio" class="form-check-input" />
                                                                                    <label for="specified_accessories_yes">Yes</label>
                                                                                </div>
                                                                                <div class="form-check form-check-inline" style="margin-left: 2px">
                                                                                    <input wire:model.defer="motor.specified_accessories" id="" name="" value="No" type="radio" class="form-check-input" />
                                                                                    <label for="">No</label>
                                                                                </div>
                                                                            </div>
                                                                            <div class="form-floating mb-3">
                                                                                <input title="premium_specified_accessories" type="text" wire:model.defer="motor.premium_specified_accessories" @blur="$el.value = window.formatAmount($el.value)"placeholder="premium_specified_accessories" class="form-control"/>
                                                                                <x-form-label for="premium_specified_accessories" required value="{{ __('Premium') }}"/>
                                                                                <x-form-input-error name="motor.premium_specified_accessories"/>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                    <!-- Comprehensive Excesses -->
                                                                <h4>Excesses</h4>
                                                                <hr>
                                                                <div class="row">
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input type="text" class="form-control" wire:model.defer="motor.own_damage"
                                                                                id="motor.own_damage" placeholder="Basic Excess"/>
                                                                            <x-form-label for="motor.own_damage" value="Basic Excess" />
                                                                            <x-form-input-error name="motor.own_damage"/>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input type="text" class="form-control" wire:model.defer="motor.own_damage_minimun_percent"
                                                                                id="motor.own_damage_minimun_percent" placeholder="Min %"/>
                                                                            <span style="color:red">Please Enter Number Only without % sign</span>
                                                                            <x-form-label for="motor.own_damage_minimun_percent" value="Min %" />
                                                                            <x-form-input-error name="motor.own_damage_minimun_percent"/>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input class="form-control" wire:model.defer="motor.own_damage_minimum_amount"
                                                                                id="motor.own_damage_minimum_amount" placeholder="Minimum Amount"
                                                                                @blur="$el.value = window.formatAmount($el.value)" />
                                                                            <x-form-label for="motor.own_damage_minimum_amount" value="Minimum Amount" />
                                                                            <x-form-input-error name="motor.own_damage_minimum_amount"/>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="row">
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input type="text" class="form-control" wire:model.defer="motor.windscreen"
                                                                                id="motor.windscreen" placeholder="Windscreen"/>
                                                                            <x-form-label for="motor.windscreen" value="Windscreen" />
                                                                            <x-form-input-error name="motor.windscreen"/>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input type="text" class="form-control" wire:model.defer="motor.windscreen_minimun_percent"
                                                                                id="motor.windscreen_minimun_percent" placeholder="Min %"/>
                                                                                <span style="color:red">Please Enter Number Only without % sign</span>
                                                                            <x-form-label for="motor.windscreen_minimun_percent" value="Min %" />
                                                                            <x-form-input-error name="motor.windscreen_minimun_percent"/>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input class="form-control" wire:model.defer="motor.windscreen_minimum_amount"
                                                                                id="motor.windscreen_minimum_amount" placeholder="minimum_amount"
                                                                                @blur="$el.value = window.formatAmount($el.value)" />
                                                                            <x-form-label for="motor.windscreen_minimum_amount" value="Minimum Amount" />
                                                                            <x-form-input-error name="motor.windscreen_minimum_amount"/>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="row">
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input type="text" class="form-control" wire:model.defer="motor.loss_of_keys"
                                                                                id="motor.loss_of_keys" placeholder="Loss of Keys"/>
                                                                            <x-form-label for="motor.loss_of_keys" value="Loss of Keys" />
                                                                            <x-form-input-error name="motor.loss_of_keys"/>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input type="text" class="form-control" wire:model.defer="motor.loss_of_keys_minimun_percent"
                                                                                id="motor.loss_of_keys_minimun_percent" placeholder="motor.loss_of_keys_minimun_percent"/>
                                                                            <span style="color:red">Please Enter Number Only without % sign</span>
                                                                            <x-form-label for="motor.loss_of_keys_minimun_percent" value="Min %" />
                                                                            <x-form-input-error name="motor.loss_of_keys_minimun_percent"/>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input class="form-control" wire:model.defer="motor.loss_of_keys_minimum_amount"
                                                                                id="motor.loss_of_keys_minimum_amount" placeholder="motor.loss_of_keys_minimum_amount"
                                                                                @blur="$el.value = window.formatAmount($el.value)" />
                                                                            <x-form-label for="motor.loss_of_keys_minimum_amount" value="Minimum Amount" />
                                                                            <x-form-input-error name="motor.loss_of_keys_minimum_amount"/>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            @elseif($policyCoverage->coverage['s_CoverageCode'] == "COMMERCIALMOTOR")
                                                                <div class="row">
                                                                    <h4>Excesses</h4>
                                                                    <hr>
                                                                    <div class="row">
                                                                        <div class="col-sm-4">
                                                                            <div class="form-floating mb-3">
                                                                                <input type="text" class="form-control" wire:model.defer="motor.own_damage"
                                                                                    id="motor.own_damage" placeholder="Basic Excess"/>
                                                                                <x-form-label for="motor.own_damage" value="Basic Excess" />
                                                                                <x-form-input-error name="motor.own_damage"/>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-sm-4">
                                                                            <div class="form-floating mb-3">
                                                                                <input type="text" class="form-control" wire:model.defer="motor.own_damage_minimun_percent"
                                                                                    id="motor.own_damage_minimun_percent" placeholder="Min %"/>
                                                                                    <span style="color:red">Please Enter Number Only without % sign</span>
                                                                                <x-form-label for="motor.own_damage_minimun_percent" value="Min %" />
                                                                                <x-form-input-error name="motor.own_damage_minimun_percent"/>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-sm-4">
                                                                            <div class="form-floating mb-3">
                                                                                <input class="form-control" wire:model.defer="motor.own_damage_minimum_amount"
                                                                                    id="motor.own_damage_minimum_amount" placeholder="Minimum Amount"
                                                                                    @blur="$el.value = window.formatAmount($el.value)" />
                                                                                <x-form-label for="motor.own_damage_minimum_amount" value="Minimum Amount" />
                                                                                <x-form-input-error name="motor.own_damage_minimum_amount"/>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    <div class="row">
                                                                        <div class="col-sm-4">
                                                                            <div class="form-floating mb-3">
                                                                                <input type="text" class="form-control" wire:model.defer="motor.windscreen"
                                                                                    id="motor.windscreen" placeholder="Windscreen"/>
                                                                                <x-form-label for="motor.windscreen" value="Windscreen" />
                                                                                <x-form-input-error name="motor.windscreen"/>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-sm-4">
                                                                            <div class="form-floating mb-3">
                                                                                <input type="text" class="form-control" wire:model.defer="motor.windscreen_minimun_percent"
                                                                                    id="motor.windscreen_minimun_percent" placeholder="Min %"/>
                                                                                    <span style="color:red">Please Enter Number Only without % sign</span>
                                                                                <x-form-label for="motor.windscreen_minimun_percent" value="Min %" />
                                                                                <x-form-input-error name="motor.windscreen_minimun_percent"/>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-sm-4">
                                                                            <div class="form-floating mb-3">
                                                                                <input class="form-control" wire:model.defer="motor.windscreen_minimum_amount"
                                                                                    id="motor.windscreen_minimum_amount" placeholder="minimum_amount"
                                                                                    @blur="$el.value = window.formatAmount($el.value)" />
                                                                                <x-form-label for="motor.windscreen_minimum_amount" value="Minimum Amount" />
                                                                                <x-form-input-error name="motor.windscreen_minimum_amount"/>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <br>
                                                                <div class="row">
                                                                    <h4>Extensions and Clauses</h4>
                                                                    <hr>
                                                                    <div class="row">
                                                                        <div class="col-sm-4">
                                                                            <div class="form-floating mb-3">
                                                                                <label for="question">Contigent Liability</label><br><br>
                                                                                <div class="form-check form-check-inline" style="margin-left: 12px">
                                                                                    <input wire:model.defer="motor.contigent_liability" id="" name="" value="Yes" type="radio" class="form-check-input" />
                                                                                    <label for="">Yes</label>
                                                                                </div>
                                                                                <div class="form-check form-check-inline" style="margin-left: 2px">
                                                                                    <input wire:model.defer="motor.contigent_liability" id="" name="" value="No" type="radio" class="form-check-input" />
                                                                                    <label for="">No</label>
                                                                                </div>
                                                                            </div>
                                                                            <div class="form-floating mb-3">
                                                                                <input title="premium_contigent_liability" type="text" wire:model.defer="motor.premium_contigent_liability" @blur="$el.value = window.formatAmount($el.value)"placeholder="premium_contigent_liability" class="form-control"/>
                                                                                <x-form-label for="premium_contigent_liability" required value="{{ __('Premium') }}"/>
                                                                                <x-form-input-error name="motor.premium_contigent_liability"/>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                                <label for="question">Passenger liability excluded</label><br><br>
                                                                                <div class="form-check form-check-inline" style="margin-left: 12px">
                                                                                    <input wire:model.defer="motor.passenger_liability" id="passenger_liability_yes" name="motor.passenger_liability" value="Yes" type="radio" class="form-check-input" />
                                                                                    <label for="passenger_liability_yes">Yes</label>
                                                                                </div>
                                                                                <div class="form-check form-check-inline" style="margin-left: 2px">
                                                                                    <input wire:model.defer="motor.passenger_liability" id="passenger_liability_no" name="motor.passenger_liability" value="No" type="radio" class="form-check-input" />
                                                                                    <label for="passenger_liability_no">No</label>
                                                                                </div>
                                                                            </div>
                                                                            <div class="form-floating mb-3">
                                                                                <input title="premium_passenger_liability" type="text" wire:model.defer="motor.premium_passenger_liability" @blur="$el.value = window.formatAmount($el.value)"placeholder="premium_passenger_liability" class="form-control"/>
                                                                                <x-form-label for="premium_passenger_liability" required value="{{ __('Premium') }}"/>
                                                                                <x-form-input-error name="motor.premium_passenger_liability"/>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-sm-4">
                                                                            <div class="form-floating mb-3">
                                                                                <label for="question">Unorthorised passanger liability</label><br><br>
                                                                                <div class="form-check form-check-inline" style="margin-left: 12px">
                                                                                    <input wire:model.defer="motor.unorthorised_passanger_liability" id="" name="" value="Yes" type="radio" class="form-check-input" />
                                                                                    <label for="">Yes</label>
                                                                                </div>
                                                                                <div class="form-check form-check-inline" style="margin-left: 2px">
                                                                                    <input wire:model.defer="motor.unorthorised_passanger_liability" id="" name="" value="No" type="radio" class="form-check-input" />
                                                                                    <label for="">No</label>
                                                                                </div>
                                                                            </div>
                                                                            <div class="form-floating mb-3">
                                                                                <input title="premium_unorthorised_passanger_liability" type="text" wire:model.defer="motor.premium_unorthorised_passanger_liability" @blur="$el.value = window.formatAmount($el.value)"placeholder="premium_unorthorised_passanger_liability" class="form-control"/>
                                                                                <x-form-label for="premium_unorthorised_passanger_liability" required value="{{ __('Premium') }}"/>
                                                                                <x-form-input-error name="motor.premium_unorthorised_passanger_liability"/>
                                                                            </div>
                                                                        </div>
                                                                    </div>

                                                                    <div class="row">
                                                                        <div class="col-sm-4">
                                                                            <div class="form-floating mb-3">
                                                                                <label for="question">Parking facilities and movement of third party vehicles</label><br><br>
                                                                                <div class="form-check form-check-inline" style="margin-left: 12px">
                                                                                    <input wire:model.defer="motor.parking_facilities" id="" name="" value="Yes" type="radio" class="form-check-input" />
                                                                                    <label for="">Yes</label>
                                                                                </div>
                                                                                <div class="form-check form-check-inline" style="margin-left: 2px">
                                                                                    <input wire:model.defer="motor.parking_facilities" id="" name="" value="No" type="radio" class="form-check-input" />
                                                                                    <label for="">No</label>
                                                                                </div>
                                                                            </div>
                                                                            <div class="form-floating mb-3">
                                                                                <input title="premium_parking_facilities" type="text" wire:model.defer="motor.premium_parking_facilities" @blur="$el.value = window.formatAmount($el.value)"placeholder="premium_parking_facilities" class="form-control"/>
                                                                                <x-form-label for="premium_parking_facilities" required value="{{ __('Premium') }}"/>
                                                                                <x-form-input-error name="motor.premium_parking_facilities"/>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-sm-4">
                                                                            <div class="form-floating mb-3">
                                                                                <label for="question">Windscreen</label><br><br>
                                                                                <div class="form-check form-check-inline" style="margin-left: 12px">
                                                                                    <input wire:model.defer="motor.com_windscreen" id="" name="" value="Yes" type="radio" class="form-check-input" />
                                                                                    <label for="">Yes</label>
                                                                                </div>
                                                                                <div class="form-check form-check-inline" style="margin-left: 2px">
                                                                                    <input wire:model.defer="motor.com_windscreen" id="" name="" value="No" type="radio" class="form-check-input" />
                                                                                    <label for="">No</label>
                                                                                </div>
                                                                            </div>
                                                                            <div class="form-floating mb-3">
                                                                                <input title="premium_com_windscreen" type="text" wire:model.defer="motor.premium_com_windscreen" @blur="$el.value = window.formatAmount($el.value)"placeholder="premium_com_windscreen" class="form-control"/>
                                                                                <x-form-label for="premium_com_windscreen" required value="{{ __('Premium') }}"/>
                                                                                <x-form-input-error name="motor.premium_com_windscreen"/>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-sm-4">
                                                                            <div class="form-floating mb-3">
                                                                                <label for="question">Riot and strike</label><br><br>
                                                                                <div class="form-check form-check-inline" style="margin-left: 12px">
                                                                                    <input wire:model.defer="motor.riot_strike" id="" name="" value="Yes" type="radio" class="form-check-input" />
                                                                                    <label for="">Yes</label>
                                                                                </div>
                                                                                <div class="form-check form-check-inline" style="margin-left: 2px">
                                                                                    <input wire:model.defer="motor.riot_strike" id="" name="" value="No" type="radio" class="form-check-input" />
                                                                                    <label for="">No</label>
                                                                                </div>
                                                                            </div>
                                                                            <div class="form-floating mb-3">
                                                                                <input title="premium_riot_strike" type="text" wire:model.defer="motor.premium_riot_strike" @blur="$el.value = window.formatAmount($el.value)"placeholder="premium_riot_strike" class="form-control"/>
                                                                                <x-form-label for="premium_riot_strike" required value="{{ __('Premium') }}"/>
                                                                                <x-form-input-error name="motor.premium_riot_strike"/>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    <div class="row">
                                                                        <div class="col-sm-4">
                                                                            <div class="form-floating mb-3">
                                                                                <label for="question">Locks and keys</label><br><br>
                                                                                <div class="form-check form-check-inline" style="margin-left: 12px">
                                                                                    <input wire:model.defer="motor.locks_keys" id="" name="" value="Yes" type="radio" class="form-check-input" />
                                                                                    <label for="">Yes</label>
                                                                                </div>
                                                                                <div class="form-check form-check-inline" style="margin-left: 2px">
                                                                                    <input wire:model.defer="motor.locks_keys" id="" name="" value="No" type="radio" class="form-check-input" />
                                                                                    <label for="">No</label>
                                                                                </div>
                                                                            </div>
                                                                            <div class="form-floating mb-3">
                                                                                <input title="premium_locks_keys" type="text" wire:model.defer="motor.premium_locks_keys" @blur="$el.value = window.formatAmount($el.value)"placeholder="premium_locks_keys" class="form-control"/>
                                                                                <x-form-label for="premium_locks_keys" required value="{{ __('Premium') }}"/>
                                                                                <x-form-input-error name="motor.premium_locks_keys"/>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-sm-4">
                                                                            <div class="form-floating mb-3">
                                                                                <label for="question">Wreckage removal</label><br><br>
                                                                                <div class="form-check form-check-inline" style="margin-left: 12px">
                                                                                    <input wire:model.defer="motor.wreckage_removal" id="" name="" value="Yes" type="radio" class="form-check-input" />
                                                                                    <label for="">Yes</label>
                                                                                </div>
                                                                                <div class="form-check form-check-inline" style="margin-left: 2px">
                                                                                    <input wire:model.defer="motor.wreckage_removal" id="" name="" value="No" type="radio" class="form-check-input" />
                                                                                    <label for="">No</label>
                                                                                </div>
                                                                            </div>
                                                                            <div class="form-floating mb-3">
                                                                                <input title="premium_wreckage_removal" type="text" wire:model.defer="motor.premium_wreckage_removal" @blur="$el.value = window.formatAmount($el.value)"placeholder="premium_wreckage_removal" class="form-control"/>
                                                                                <x-form-label for="premium_wreckage_removal" required value="{{ __('Premium') }}"/>
                                                                                <x-form-input-error name="motor.premium_wreckage_removal"/>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-sm-4">
                                                                            <div class="form-floating mb-3">
                                                                                <label for="question">Credit Shortfall</label><br><br>
                                                                                <div class="form-check form-check-inline" style="margin-left: 12px">
                                                                                    <input wire:model.defer="motor.credit_shortfall" id="" name="" value="Yes" type="radio" class="form-check-input" />
                                                                                    <label for="">Yes</label>
                                                                                </div>
                                                                                <div class="form-check form-check-inline" style="margin-left: 2px">
                                                                                    <input wire:model.defer="motor.credit_shortfall" id="" name="" value="No" type="radio" class="form-check-input" />
                                                                                    <label for="">No</label>
                                                                                </div>
                                                                            </div>
                                                                            <div class="form-floating mb-3">
                                                                                <input title="premium_credit_shortfall" type="text" wire:model.defer="motor.premium_credit_shortfall" @blur="$el.value = window.formatAmount($el.value)"placeholder="premium_credit_shortfall" class="form-control"/>
                                                                                <x-form-label for="premium_credit_shortfall" required value="{{ __('Premium') }}"/>
                                                                                <x-form-input-error name="motor.premium_credit_shortfall"/>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    <div class="row">
                                                                        <div class="col-sm-4">
                                                                            <div class="form-floating mb-3">
                                                                                <label for="question">Third party liability</label><br><br>
                                                                                <input class="form-control" type="text" wire:model.defer="third_party_liability" value="{{$this->third_party_liability}}">
                                                                            </div>

                                                                            <div class="form-floating mb-3">
                                                                                <input title="premium_third_party_liability" type="text" wire:model.defer="motor.premium_third_party_liability" @blur="$el.value = window.formatAmount($el.value)"placeholder="premium_third_party_liability" class="form-control"/>
                                                                                <x-form-label for="premium_third_party_liability" required value="{{ __('Premium') }}"/>
                                                                                <x-form-input-error name="motor.premium_third_party_liability"/>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            @endif
                                                            <hr>
                                                            <div class="row">
                                                            <div class="col-sm-12">
                                                                <div class="card bg-light shadow-sm">
                                                                    <div class="card-header">
                                                                        <h3 class="card-title">Note</h3>
                                                                    </div>
                                                                    <div class="card-body card-scroll h-200px">
                                                                        <div class="form-floating">
                                                                          @php
                                                                           $key = isset($this->motorVehicleData['motor_id']) && $this->motorVehicleData['motor_id']
                                                                                    ? $this->motorVehicleData['motor_id'].'_'.$policyCoverage->id
                                                                                    : $policyCoverage->id;
                                                                            @endphp
                                                                           <x-form-text-area 
                                                                            wire:model.defer="policyCoverageNote.{{ $key }}"
                                                                            class="h-100" 
                                                                            style="height: 15em !important;"
                                                                        />
                                                                        <label>Enter Your Note</label>
                                                                        <x-form-input-error name="policyCoverageNote.{{ $key }}"/>                                                 </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="row">
                                                                <div class="col-sm-12">
                                                                    <div class="card bg-light shadow-sm">
                                                                        <div class="card-header">
                                                                            <h3 class="card-title">Miscellaneous Items Comprenhsive</h3>
                                                                            <div class="card-toolbar">
                                                                                <button type="button" class="btn btn-sm btn-primary" wire:click.prevent="addSpecifiedRow({{ $policyCoverage->id}})">
                                                                                    Add <Row></Row>
                                                                                </button>
                                                                            </div>
                                                                        </div>
                                                                        <div class="card-body card-scroll h-200px">
                                                                            @foreach($this->specifiedRow[$policyCoverage->id] ?? [] as $key => $value)
                                                                                <div class="row">
                                                                                    {{-- <pre>{{ print_r($this->specifiedItems, true) }}</pre> --}}
                                                                                    <div class="col-sm-5">
                                                                                        <div class="form-floating mb-3">
                                                                                            <x-select wire:model.defer="specified_items.{{ $policyCoverage->id }}.{{ $key }}.selected_item"
                                                                                                    :options="$this->specifiedItems[$policyCoverage->coverage_id]" />
                                                                                            <x-form-label for="specified_items.{{ $policyCoverage->id }}.{{ $key }}.selected_item" required value="{{ __('Select Description Of Item') }}"/>
                                                                                            <x-form-input-error name="specified_items.{{ $policyCoverage->id }}.{{ $key }}.selected_item"/>
                                                                                        </div>
                                                                                    </div>
                                                                                    <div class="col-sm-5">
                                                                                        <div class="form-floating mb-3">
                                                                                            <x-form-input wire:model.defer="specified_items.{{ $policyCoverage->id }}.{{ $key }}.sum_insured"
                                                                                                        placeholder="Sum Insured" class="amount-field" @blur="$el.value = window.formatAmount($el.value)"/>
                                                                                            <x-form-label for="specified_items.{{ $policyCoverage->id }}.{{ $key }}.sum_insured" required value="{{ __('Sum Insured') }}"/>
                                                                                            <x-form-input-error name="specified_items.{{ $policyCoverage->id }}.{{ $key }}.sum_insured"/>
                                                                                        </div>
                                                                                    </div>
                                                                                    <div class="col-sm-2">
                                                                                       
                                                                                       @if( isset($policyCoverage->specifedItems[$key])
                                                                                            && $policyCoverage->specifedItems[$key]->deleted_at === null)
                                                                                            <button type="button" class="btn btn-danger btn-small"
                                                                                                wire:click.prevent="removeRow(
                                                                                                    {{ $policyCoverage->id }},
                                                                                                    {{ $key }}
                                                                                                    @isset($policyCoverage->specifedItems[$key]->id),{{ $policyCoverage->specifedItems[$key]->id }}@endisset
                                                                                                )">
                                                                                                &times;
                                                                                            </button>
                                                                                              @elseif(!isset($policyCoverage->specifedItems[$key]))
                                                                                               <button type="button" class="btn btn-danger btn-small" wire:click.prevent="removeRow({{ $policyCoverage->id }},{{ $key }})">&times;</button>
                                                                                   
                                                                                        @else
                                                                                            <a wire:click.prevent="reinstateMisc('{{ $policyCoverage->specifedItems[$key]->id ?? '' }}')"
                                                                                            class="btn btn-sm btn-primary"
                                                                                            style="position: absolute; right: 75px;">
                                                                                            Reinstate
                                                                                            </a>
                                                                                        @endif

                                                                                    </div>
                                                                                </div>
                                                                            @endforeach
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                        </div>
                                                        </div>
                                         
                                                        
                                                        {{-- third_party_only_section --}}
                                                        <div class="card-body third_party_only_section" @if(!$third_party_only_section) style="display: none;" @endif>
                                                            <input type="hidden"  title="policy_coverage_id" wire:model.defer="motorVehicleData.policy_coverage_id" class="form-control"
                                                                        id="motorVehicleData.policy_coverage_id" placeholder="policy_coverage_id" />
                                                            <input type="hidden" wire:model.defer="motor.estimated_value"  placeholder="estimated_value" class="form-control">
                                                            <input type="hidden" wire:model.defer="motorVehicleData.motor_id"  placeholder="motr_id" class="form-control">

                                                            <div class="row">
                                                                <div class="col-sm-4">
                                                                    <div class="form-floating mb-3">
                                                                        <input title="Vehicle Name" wire:model.defer="motorVehicleData.vehicle_name" class="form-control"
                                                                        id="motorVehicleData.vehicle_name" placeholder="Vehicle Name" disabled/>
                                                                        <x-form-label for="make" required value="{{ __('Vehicle Name') }}"/>
                                                                        <x-form-input-error name="motorVehicleData.vehicle_name"/>
                                                                    </div>
                                                                </div>
                                                                <div class="col-sm-4">
                                                                    <div class="form-floating mb-3">
                                                                        <input title="Sum Insured" id="input2" class="form-control amount-field" wire:model.defer="motorVehicleData.coverage_value"
                                                                        id="motorVehicleData.coverage_value" placeholder="Sum Insured" disabled />
                                                                        <x-form-label for="coverage_value" required value="{{ __('Sum Insured') }}"/>
                                                                        <x-form-input-error name="motorVehicleData.coverage_value"/>
                                                                    </div>
                                                                </div>
                                                                <div class="col-sm-4">
                                                                    <div class="form-floating mb-3">
                                                                        <input title="calculated_value" id="input4" type="text" wire:model.defer="motorVehicleData.calculated_value"
                                                                        @blur="$el.value = window.formatAmount($el.value)"placeholder="Premium" class="form-control amount-field" disabled/>
                                                                        <x-form-label for="calculated_value" required value="{{ __('Premium') }}"/>
                                                                        <x-form-input-error name="motorVehicleData.calculated_value"/>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <hr>
                                                            <!-- third_party_only Vehicle -->
                                                            <div class="row">
                                                                <h4>Vehicle Details</h4>
                                                                <hr>
                                                                <div class="col-sm-3">
                                                                    <div class="form-floating mb-3">
                                                                        <select wire:model.defer="motor.use" aria-label="Select Use" class="form-select form-select-solid">
                                                                            <option value="">-Select-</option>
                                                                            <option value="Personal">Personal</option>
                                                                            <option value="Normal operations">Normal operations</option>
                                                                            <option value="Heavy duty">Heavy duty</option>
                                                                            <option value="Transport">Transport</option>
                                                                        </select>
                                                                        <x-form-label for="motor.use" value="Select Use" />
                                                                        <x-form-input-error name="motor.use"/>
                                                                    </div>
                                                                </div>
                                                                <div class="col-sm-3">
                                                                    <div class="form-floating mb-3">
                                                                        <input type="text" wire:model.defer="motor.registration_no"  placeholder="Registration number" class="form-control" disabled>
                                                                        <x-form-label for="motor.registration_no" value="Registration number" />
                                                                        <x-form-input-error name="motor.registration_no"/>
                                                                    </div>
                                                                </div>
                                                                <div class="col-sm-3">
                                                                    <div class="form-floating mb-3">
                                                                        <x-form-input class=""  type="text" name="motor.make"  wire:model.defer="motor.make" placeholder="Make" disabled/>
                                                                        <x-form-label for="motor.make"  value="Make"/>
                                                                        <x-form-input-error name="motor.make"/>
                                                                    </div>
                                                                </div>
                                                                <div class="col-sm-3">
                                                                    <div class="form-floating mb-3">
                                                                        <x-form-input class=""  type="text" name="motor.model"  wire:model.defer="motor.model" placeholder="Model" disabled/>
                                                                        <x-form-label for="motor.model"  value="Model"/>
                                                                        <x-form-input-error name="motor.model"/>
                                                                    </div>
                                                                </div>
                                                                <div class="col-sm-3">
                                                                    <div class="form-floating mb-3">
                                                                        <input type="text" wire:model.defer="motor.engine_number"  placeholder="Engine Number" class="form-control" disabled>
                                                                        <x-form-label for="motor.engine_number" value="Engine Number" />
                                                                        <x-form-input-error name="motor.engine_number"/>
                                                                    </div>
                                                                </div>
                                                                <div class="col-sm-3">
                                                                    <div class="form-floating mb-3">
                                                                        <input type="text" wire:model.defer="motor.chassis_number"  placeholder="Chassis Number" class="form-control" disabled>
                                                                        <x-form-label for="motor.chassis_number" value="Chassis Number" />
                                                                        <x-form-input-error name="motor.chassis_number"/>
                                                                    </div>
                                                                </div>
                                                                <div class="col-sm-3">
                                                                    <div class="form-floating mb-3">
                                                                        <input type="text" wire:model.defer="motor.tracking_device" placeholder="Tracking device" class="form-control">
                                                                        <x-form-label for="motor.tracking_device" value="Tracking device" />
                                                                        <x-form-input-error name="motor.tracking_device"/>
                                                                    </div>
                                                                </div>
                                                                <div class="col-sm-3">
                                                                    <div class="form-floating mb-3">
                                                                        <input type="text" wire:model.defer="motor.security_features" placeholder="Security features" class="form-control">
                                                                        <x-form-label for="motor.security_features" value="Security features" />
                                                                        <x-form-input-error name="motor.security_features"/>
                                                                    </div>
                                                                </div>
                                                                <div class="col-sm-3">
                                                                    <div class="form-floating mb-3">
                                                                        <select wire:model.defer="motor.type_of_cover" aria-label="Select Type of cover" class="form-select form-select-solid">
                                                                            <option value="Third party only">Third party only</option>
                                                                        </select>
                                                                        <x-form-label for="motor.type_of_cover" value="Select Type of cover" />
                                                                        <x-form-input-error name="motor.type_of_cover"/>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="row">
                                                            <div class="col-sm-12">
                                                                <div class="card bg-light shadow-sm">
                                                                    <div class="card-header">
                                                                        <h3 class="card-title">Note</h3>
                                                                    </div>
                                                                     <div class="card-body card-scroll h-200px">
                                                                        <div class="form-floating">
                                                                          @php
                                                                           $key = isset($this->motorVehicleData['motor_id']) && $this->motorVehicleData['motor_id']
                                                                                    ? $this->motorVehicleData['motor_id'].'_'.$policyCoverage->id
                                                                                    : $policyCoverage->id;
                                                                            @endphp
                                                                           <x-form-text-area 
                                                                            wire:model.defer="policyCoverageNote.{{ $key }}"
                                                                            class="h-100" 
                                                                            style="height: 15em !important;"
                                                                        />
                                                                        <label>Enter Your Note</label>
                                                                        <x-form-input-error name="policyCoverageNote.{{ $key }}"/>                                                 </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="row">
                                                                <div class="col-sm-12">
                                                                    <div class="card bg-light shadow-sm">
                                                                        <div class="card-header">
                                                                            <h3 class="card-title">Miscellaneous Items Comprenhsive</h3>
                                                                            <div class="card-toolbar">
                                                                                <button type="button" class="btn btn-sm btn-primary" wire:click.prevent="addSpecifiedRow({{ $policyCoverage->id}})">
                                                                                    Add <Row></Row>
                                                                                </button>
                                                                            </div>
                                                                        </div>
                                                                        <div class="card-body card-scroll h-200px">
                                                                            @foreach($this->specifiedRow[$policyCoverage->id] ?? [] as $key => $value)
                                                                                <div class="row">
                                                                                    <div class="col-sm-5">
                                                                                        <div class="form-floating mb-3">
                                                                                            <x-select wire:model.defer="specified_items.{{ $policyCoverage->id }}.{{ $key }}.selected_item"
                                                                                                    :options="$this->specifiedItems[$policyCoverage->coverage_id]" />
                                                                                            <x-form-label for="specified_items.{{ $policyCoverage->id }}.{{ $key }}.selected_item" required value="{{ __('Select Description Of Item') }}"/>
                                                                                            <x-form-input-error name="specified_items.{{ $policyCoverage->id }}.{{ $key }}.selected_item"/>
                                                                                        </div>
                                                                                    </div>
                                                                                    <div class="col-sm-5">
                                                                                        <div class="form-floating mb-3">
                                                                                            <x-form-input wire:model.defer="specified_items.{{ $policyCoverage->id }}.{{ $key }}.sum_insured"
                                                                                                        placeholder="Sum Insured" class="amount-field" @blur="$el.value = window.formatAmount($el.value)"/>
                                                                                            <x-form-label for="specified_items.{{ $policyCoverage->id }}.{{ $key }}.sum_insured" required value="{{ __('Sum Insured') }}"/>
                                                                                            <x-form-input-error name="specified_items.{{ $policyCoverage->id }}.{{ $key }}.sum_insured"/>
                                                                                        </div>
                                                                                    </div>
                                                                                    <div class="col-sm-2">
                                                                                        @if( isset($policyCoverage->specifedItems[$key])
                                                                                            && $policyCoverage->specifedItems[$key]->deleted_at === null)
                                                                                            <button type="button" class="btn btn-danger btn-small"
                                                                                                wire:click.prevent="removeRow(
                                                                                                    {{ $policyCoverage->id }},
                                                                                                    {{ $key }}
                                                                                                    @isset($policyCoverage->specifedItems[$key]->id),{{ $policyCoverage->specifedItems[$key]->id }}@endisset
                                                                                                )">
                                                                                                &times;
                                                                                            </button>
                                                                                              @elseif(!isset($policyCoverage->specifedItems[$key]))
                                                                                               <button type="button" class="btn btn-danger btn-small" wire:click.prevent="removeRow({{ $policyCoverage->id }},{{ $key }})">&times;</button>
                                                                                   
                                                                                        @else
                                                                                            <a wire:click.prevent="reinstateMisc('{{ $policyCoverage->specifedItems[$key]->id ?? '' }}')"
                                                                                            class="btn btn-sm btn-primary"
                                                                                            style="position: absolute; right: 75px;">
                                                                                            Reinstate
                                                                                            </a>
                                                                                        @endif </div>
                                                                                </div>
                                                                            @endforeach
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                        </div>
                                                        </div>
                                                        {{-- Third_fire_and_theft_section --}}
                                                        <div class="card-body Third_fire_and_theft_section" @if(!$Third_fire_and_theft_section) style="display: none;" @endif>
                                                            <input type="hidden"  title="policy_coverage_id" wire:model.defer="motorVehicleData.policy_coverage_id" class="form-control"
                                                                        id="motorVehicleData.policy_coverage_id" placeholder="policy_coverage_id" />
                                                            <input type="hidden" wire:model.defer="motor.estimated_value"  placeholder="estimated_value" class="form-control">
                                                            <input type="hidden" wire:model.defer="motorVehicleData.motor_id"  placeholder="motr_id" class="form-control">

                                                            <div class="row">
                                                                <div class="col-sm-4">
                                                                    <div class="form-floating mb-3">
                                                                        <input title="Vehicle Name" wire:model.defer="motorVehicleData.vehicle_name" class="form-control"
                                                                        id="motorVehicleData.vehicle_name" placeholder="Vehicle Name" disabled/>
                                                                        <x-form-label for="make" required value="{{ __('Vehicle Name') }}"/>
                                                                        <x-form-input-error name="motorVehicleData.vehicle_name"/>
                                                                    </div>
                                                                </div>
                                                                <div class="col-sm-4">
                                                                    <div class="form-floating mb-3">
                                                                        <input title="Sum Insured" type="text" class="form-control amount-field" wire:model.defer="motorVehicleData.coverage_value"
                                                                        id="input2" placeholder="Sum Insured" disabled />
                                                                        <x-form-label for="coverage_value" required value="{{ __('Sum Insured') }}"/>
                                                                        <x-form-input-error name="motorVehicleData.coverage_value"/>
                                                                    </div>
                                                                </div>
                                                                <div class="col-sm-4">
                                                                    <div class="form-floating mb-3">
                                                                        <input title="calculated_value"  id="input4" type="text" wire:model.defer="motorVehicleData.calculated_value"
                                                                        @blur="$el.value = window.formatAmount($el.value)"placeholder="Premium" class="form-control amount-field" disabled/>
                                                                        <x-form-label for="calculated_value" required value="{{ __('Premium') }}"/>
                                                                        <x-form-input-error name="motorVehicleData.calculated_value"/>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <hr>
                                                            <!-- Third_fire_and_theft Vehicle -->
                                                            <div class="row">
                                                                <h4>Vehicle Details</h4>
                                                                <hr>
                                                                <div class="col-sm-3">
                                                                    <div class="form-floating mb-3">
                                                                        <select wire:model.defer="motor.use" aria-label="Select Use" class="form-select form-select-solid">
                                                                            <option value="">-Select-</option>
                                                                            <option value="Personal">Personal</option>
                                                                            <option value="Normal operations">Normal operations</option>
                                                                            <option value="Heavy duty">Heavy duty</option>
                                                                            <option value="Transport">Transport</option>
                                                                        </select>
                                                                        <x-form-label for="motor.use" value="Select Use" />
                                                                        <x-form-input-error name="motor.use"/>
                                                                    </div>
                                                                </div>
                                                                <div class="col-sm-3">
                                                                    <div class="form-floating mb-3">
                                                                        <input type="text" wire:model.defer="motor.registration_no"  placeholder="Registration number" class="form-control" disabled>
                                                                        <x-form-label for="motor.registration_no" value="Registration number" />
                                                                        <x-form-input-error name="motor.registration_no"/>
                                                                    </div>
                                                                </div>
                                                                <div class="col-sm-3">
                                                                    <div class="form-floating mb-3">
                                                                        <x-form-input class=""  type="text" name="motor.make"  wire:model.defer="motor.make" placeholder="Make" disabled/>
                                                                        <x-form-label for="motor.make"  value="Make"/>
                                                                        <x-form-input-error name="motor.make"/>
                                                                    </div>
                                                                </div>
                                                                <div class="col-sm-3">
                                                                    <div class="form-floating mb-3">
                                                                        <x-form-input class=""  type="text" name="motor.model"  wire:model.defer="motor.model" placeholder="Model" disabled/>
                                                                        <x-form-label for="motor.model"  value="Model"/>
                                                                        <x-form-input-error name="motor.model"/>
                                                                    </div>
                                                                </div>
                                                                <div class="col-sm-3">
                                                                    <div class="form-floating mb-3">
                                                                        <input type="text" wire:model.defer="motor.engine_number"  placeholder="Engine Number" class="form-control" disabled>
                                                                        <x-form-label for="motor.engine_number" value="Engine Number" />
                                                                        <x-form-input-error name="motor.engine_number"/>
                                                                    </div>
                                                                </div>
                                                                <div class="col-sm-3">
                                                                    <div class="form-floating mb-3">
                                                                        <input type="text" wire:model.defer="motor.chassis_number"  placeholder="Chassis Number" class="form-control" disabled>
                                                                        <x-form-label for="motor.chassis_number" value="Chassis Number" />
                                                                        <x-form-input-error name="motor.chassis_number"/>
                                                                    </div>
                                                                </div>
                                                                <div class="col-sm-3">
                                                                    <div class="form-floating mb-3">
                                                                        <input type="text" wire:model.defer="motor.tracking_device" placeholder="Tracking device" class="form-control">
                                                                        <x-form-label for="motor.tracking_device" value="Tracking device" />
                                                                        <x-form-input-error name="motor.tracking_device"/>
                                                                    </div>
                                                                </div>
                                                                <div class="col-sm-3">
                                                                    <div class="form-floating mb-3">
                                                                        <input type="text" wire:model.defer="motor.security_features" placeholder="Security features" class="form-control">
                                                                        <x-form-label for="motor.security_features" value="Security features" />
                                                                        <x-form-input-error name="motor.security_features"/>
                                                                    </div>
                                                                </div>
                                                                <div class="col-sm-3">
                                                                    <div class="form-floating mb-3">
                                                                        <select wire:model.defer="motor.type_of_cover" aria-label="Select Type of cover" class="form-select form-select-solid">
                                                                            <option value="Third party, fire and theft">Third party, fire and theft</option>
                                                                        </select>
                                                                        <x-form-label for="motor.type_of_cover" value="Select Type of cover" />
                                                                        <x-form-input-error name="motor.type_of_cover"/>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <hr>
                                                            @if($policyCoverage->coverage['s_CoverageCode'] == "PERSONALMOTOR")
                                                                <!-- Third_fire_and_theft Extensions --><br>
                                                                <div class="row">
                                                                    <h4>Extensions and Clauses</h4>
                                                                    <hr>
                                                                    <div class="row">
                                                                        <div class="col-sm-4">
                                                                            <div class="form-floating mb-3">
                                                                                <label for="question">Wreckage removal</label><br><br>
                                                                                <div class="form-check form-check-inline" style="margin-left: 12px">
                                                                                    <input wire:model.defer="motor.wreckage_removal" id="wreckage_removal_yes" name="motor.wreckage_removal" value="Yes" type="radio" class="form-check-input" />
                                                                                    <label for="wreckage_removal_yes">Yes</label>
                                                                                </div>
                                                                                <div class="form-check form-check-inline" style="margin-left: 2px">
                                                                                    <input wire:model.defer="motor.wreckage_removal" id="wreckage_removal_no" name="motor.wreckage_removal" value="No" type="radio" class="form-check-input" />
                                                                                    <label for="wreckage_removal_no">No</label>
                                                                                </div>
                                                                            </div>
                                                                            <div class="form-floating mb-3">
                                                                                <input title="premium_wreckage_removal" type="text" wire:model.defer="motor.premium_wreckage_removal" @blur="$el.value = window.formatAmount($el.value)"placeholder="premium_wreckage_removal" class="form-control"/>
                                                                                <x-form-label for="premium_wreckage_removal" required value="{{ __('Premium') }}"/>
                                                                                <x-form-input-error name="motor.premium_wreckage_removal"/>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-sm-4">
                                                                            <div class="form-floating mb-3">
                                                                                <label for="question">Window glass</label><br><br>
                                                                                <div class="form-check form-check-inline" style="margin-left: 12px">
                                                                                    <input wire:model.defer="motor.window_glass" id="window_glass_yes" name="motor.window_glass" value="Yes" type="radio" class="form-check-input" />
                                                                                    <label for="window_glass_no">Yes</label>
                                                                                </div>
                                                                                <div class="form-check form-check-inline" style="margin-left: 2px">
                                                                                    <input wire:model.defer="motor.window_glass" id="window_glass_no" name="motor.window_glass" value="No" type="radio" class="form-check-input" />
                                                                                    <label for="window_glass_no">No</label>
                                                                                </div>
                                                                            </div>
                                                                            <div class="form-floating mb-3">
                                                                                <input title="premium_window_glass" type="text" wire:model.defer="motor.premium_window_glass" @blur="$el.value = window.formatAmount($el.value)"placeholder="premium_window_glass" class="form-control"/>
                                                                                <x-form-label for="premium_window_glass" required value="{{ __('Premium') }}"/>
                                                                                <x-form-input-error name="motor.premium_window_glass"/>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-sm-4">
                                                                            <div class="form-floating mb-3">
                                                                                <label for="question">Locks and keys</label><br><br>
                                                                                <div class="form-check form-check-inline" style="margin-left: 12px">
                                                                                    <input wire:model.defer="motor.locks_keys" id="locks_keys_yes" name="motor.locks_keys" value="Yes" type="radio" class="form-check-input" />
                                                                                    <label for="locks_keys_yes">Yes</label>
                                                                                </div>
                                                                                <div class="form-check form-check-inline" style="margin-left: 2px">
                                                                                    <input wire:model.defer="motor.locks_keys" id="locks_keys_no" name="motor.locks_keys" value="No" type="radio" class="form-check-input" />
                                                                                    <label for="locks_keys_no">No</label>
                                                                                </div>
                                                                            </div>
                                                                            <div class="form-floating mb-3">
                                                                                <input title="premium_locks_keys" type="text" wire:model.defer="motor.premium_locks_keys" @blur="$el.value = window.formatAmount($el.value)"placeholder="premium_locks_keys" class="form-control"/>
                                                                                <x-form-label for="premium_locks_keys" required value="{{ __('Premium') }}"/>
                                                                                <x-form-input-error name="motor.premium_locks_keys"/>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    <div class="row">
                                                                        <div class="col-sm-4">
                                                                            <div class="form-floating mb-3">
                                                                                <label for="question">Parts or accessories not readily available</label><br><br>
                                                                                <div class="form-check form-check-inline" style="margin-left: 12px">
                                                                                    <input wire:model.defer="motor.parts_accessories" id="parts_accessories_yes" name="motor.parts_accessories" value="Yes" type="radio" class="form-check-input" />
                                                                                    <label for="parts_accessories_yes">Yes</label>
                                                                                </div>
                                                                                <div class="form-check form-check-inline" style="margin-left: 2px">
                                                                                    <input wire:model.defer="motor.parts_accessories" id="parts_accessories_no" name="motor.parts_accessories" value="No" type="radio" class="form-check-input" />
                                                                                    <label for="parts_accessories_no">No</label>
                                                                                </div>
                                                                            </div>
                                                                            <div class="form-floating mb-3">
                                                                                <input title="premium_parts_accessories" type="text" wire:model.defer="motor.premium_parts_accessories" @blur="$el.value = window.formatAmount($el.value)"placeholder="premium_parts_accessories" class="form-control"/>
                                                                                <x-form-label for="premium_parts_accessories" required value="{{ __('Premium') }}"/>
                                                                                <x-form-input-error name="motor.premium_parts_accessories"/>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-sm-4">
                                                                            <div class="form-floating mb-3">
                                                                                <label for="question">Audio accessories</label><br><br>
                                                                                <div class="form-check form-check-inline" style="margin-left: 12px">
                                                                                    <input wire:model.defer="motor.audio_accessories" id="audio_accessories_yes" name="motor.audio_accessories" value="Yes" type="radio" class="form-check-input" />
                                                                                    <label for="audio_accessories_yes">Yes</label>
                                                                                </div>
                                                                                <div class="form-check form-check-inline" style="margin-left: 2px">
                                                                                    <input wire:model.defer="motor.audio_accessories" id="audio_accessories_no" name="motor.audio_accessories" value="No" type="radio" class="form-check-input" />
                                                                                    <label for="audio_accessories_no">No</label>
                                                                                </div>
                                                                            </div>
                                                                            <div class="form-floating mb-3">
                                                                                <input title="premium_audio_accessories" type="text" wire:model.defer="motor.premium_audio_accessories" @blur="$el.value = window.formatAmount($el.value)" placeholder="premium_audio_accessories" class="form-control"/>
                                                                                <x-form-label for="premium_audio_accessories" required value="{{ __('Premium') }}"/>
                                                                                <x-form-input-error name="motor.premium_audio_accessories"/>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-sm-4">
                                                                            <div class="form-floating mb-3">
                                                                                <label for="question">Riot and strike</label><br><br>
                                                                                <div class="form-check form-check-inline" style="margin-left: 12px">
                                                                                    <input wire:model.defer="motor.riot_strike" id="riot_strike_yes" name="motor.riot_strike" value="Yes" type="radio" class="form-check-input" />
                                                                                    <label for="riot_strike_yes">Yes</label>
                                                                                </div>
                                                                                <div class="form-check form-check-inline" style="margin-left: 2px">
                                                                                    <input wire:model.defer="motor.riot_strike" id="riot_strike_no" name="motor.riot_strike" value="No" type="radio" class="form-check-input" />
                                                                                    <label for="riot_strike_no">No</label>
                                                                                </div>
                                                                            </div>
                                                                            <div class="form-floating mb-3">
                                                                                <input title="premium_riot_strike" type="text" wire:model.defer="motor.premium_riot_strike" @blur="$el.value = window.formatAmount($el.value)"placeholder="premium_riot_strike" class="form-control"/>
                                                                                <x-form-label for="premium_riot_strike" required value="{{ __('Premium') }}"/>
                                                                                <x-form-input-error name="motor.premium_riot_strike"/>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    <div class="row">
                                                                        <div class="col-sm-4">
                                                                            <div class="form-floating mb-3">
                                                                                <label for="question">Car hire-theft/hijack of the vehicle</label><br><br>
                                                                                <div class="form-check form-check-inline" style="margin-left: 12px">
                                                                                    <input wire:model.defer="motor.car_hire_theft" id="car_hire_theft_yes" name="motor.car_hire_theft" value="Yes" type="radio" class="form-check-input" />
                                                                                    <label for="car_hire_theft_yes">Yes</label>
                                                                                </div>
                                                                                <div class="form-check form-check-inline" style="margin-left: 2px">
                                                                                    <input wire:model.defer="motor.car_hire_theft" id="car_hire_theft_no" name="motor.car_hire_theft" value="No" type="radio" class="form-check-input" />
                                                                                    <label for="car_hire_theft_no">No</label>
                                                                                </div>
                                                                            </div>
                                                                            <div class="form-floating mb-3">
                                                                                <input title="premium_car_hire_theft" type="text" wire:model.defer="motor.premium_car_hire_theft" @blur="$el.value = window.formatAmount($el.value)"placeholder="premium_car_hire_theft" class="form-control"/>
                                                                                <x-form-label for="premium_car_hire_theft" required value="{{ __('Premium') }}"/>
                                                                                <x-form-input-error name="motor.premium_car_hire_theft"/>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-sm-4">
                                                                            <div class="form-floating mb-3">
                                                                                <label for="question">Credit Shortfall</label><br><br>
                                                                                <div class="form-check form-check-inline" style="margin-left: 12px">
                                                                                    <input wire:model.defer="motor.credit_shortfall" id="credit_shortfall_yes" name="motor.credit_shortfall" value="Yes" type="radio" class="form-check-input" />
                                                                                    <label for="credit_shortfall_yes">Yes</label>
                                                                                </div>
                                                                                <div class="form-check form-check-inline" style="margin-left: 2px">
                                                                                    <input wire:model.defer="motor.credit_shortfall" id="credit_shortfall_no" name="motor.credit_shortfall" value="No" type="radio" class="form-check-input" />
                                                                                    <label for="credit_shortfall_no">No</label>
                                                                                </div>
                                                                            </div>
                                                                            <div class="form-floating mb-3">
                                                                                <input title="premium_credit_shortfall" type="text" wire:model.defer="motor.premium_credit_shortfall" @blur="$el.value = window.formatAmount($el.value)"placeholder="premium_credit_shortfall" class="form-control"/>
                                                                                <x-form-label for="premium_credit_shortfall" required value="{{ __('Premium') }}"/>
                                                                                <x-form-input-error name="motor.premium_credit_shortfall"/>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-sm-4">
                                                                            <div class="form-floating mb-3">
                                                                                <label for="question">Insured only driver</label><br><br>
                                                                                <div class="form-check form-check-inline" style="margin-left: 12px">
                                                                                    <input wire:model.defer="motor.insured_driver" id="insured_driver_yes" name="motor.insured_driver" value="Yes" type="radio" class="form-check-input" />
                                                                                    <label for="insured_driver_yes">Yes</label>
                                                                                </div>
                                                                                <div class="form-check form-check-inline" style="margin-left: 2px">
                                                                                    <input wire:model.defer="motor.insured_driver" id="insured_driver_no" name="motor.insured_driver" value="No" type="radio" class="form-check-input" />
                                                                                    <label for="insured_driver_no">No</label>
                                                                                </div>
                                                                            </div>
                                                                            <div class="form-floating mb-3">
                                                                                <input title="premium_insured_driver" type="text" wire:model.defer="motor.premium_insured_driver" @blur="$el.value = window.formatAmount($el.value)"placeholder="premium_insured_driver" class="form-control"/>
                                                                                <x-form-label for="premium_insured_driver" required value="{{ __('Premium') }}"/>
                                                                                <x-form-input-error name="motor.premium_insured_driver"/>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    <div class="row">
                                                                        <div class="col-sm-4">
                                                                            <div class="form-floating mb-3">
                                                                                <label for="question">Insured and family only drivers</label><br><br>
                                                                                <div class="form-check form-check-inline" style="margin-left: 12px">
                                                                                    <input wire:model.defer="motor.insured_family" id="insured_family_yes" name="motor.insured_family" value="Yes" type="radio" class="form-check-input" />
                                                                                    <label for="insured_family_yes">Yes</label>
                                                                                </div>
                                                                                <div class="form-check form-check-inline" style="margin-left: 2px">
                                                                                    <input wire:model.defer="motor.insured_family" id="insured_family_no" name="motor.insured_family" value="No" type="radio" class="form-check-input" />
                                                                                    <label for="insured_family_no">No</label>
                                                                                </div>
                                                                            </div>
                                                                            <div class="form-floating mb-3">
                                                                                <input title="premium_insured_family" type="text" wire:model.defer="motor.premium_insured_family" @blur="$el.value = window.formatAmount($el.value)"placeholder="premium_insured_family" class="form-control"/>
                                                                                <x-form-label for="premium_insured_family" required value="{{ __('Premium') }}"/>
                                                                                <x-form-input-error name="motor.premium_insured_family"/>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-sm-4">
                                                                            <div class="form-floating mb-3">
                                                                                <label for="question">Medical expenses</label><br><br>
                                                                                <div class="form-check form-check-inline" style="margin-left: 12px">
                                                                                    <input wire:model.defer="motor.medical_expenses" id="medical_expenses_yes" name="motor.medical_expenses" value="Yes" type="radio" class="form-check-input" />
                                                                                    <label for="medical_expenses_yes">Yes</label>
                                                                                </div>
                                                                                <div class="form-check form-check-inline" style="margin-left: 2px">
                                                                                    <input wire:model.defer="motor.medical_expenses" id="medical_expenses_no" name="motor.medical_expenses" value="No" type="radio" class="form-check-input" />
                                                                                    <label for="medical_expenses_no">No</label>
                                                                                </div>
                                                                            </div>
                                                                            <div class="form-floating mb-3">
                                                                                <input title="premium_medical_expenses" type="text" wire:model.defer="motor.premium_medical_expenses" @blur="$el.value = window.formatAmount($el.value)"placeholder="premium_medical_expenses" class="form-control"/>
                                                                                <x-form-label for="premium_medical_expenses" required value="{{ __('Premium') }}"/>
                                                                                <x-form-input-error name="motor.premium_medical_expenses"/>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-sm-4">
                                                                            <div class="form-floating mb-3">
                                                                                <label for="question">Passenger liability excluded</label><br><br>
                                                                                <div class="form-check form-check-inline" style="margin-left: 12px">
                                                                                    <input wire:model.defer="motor.passenger_liability" id="passenger_liability_yes" name="motor.passenger_liability" value="Yes" type="radio" class="form-check-input" />
                                                                                    <label for="passenger_liability_yes">Yes</label>
                                                                                </div>
                                                                                <div class="form-check form-check-inline" style="margin-left: 2px">
                                                                                    <input wire:model.defer="motor.passenger_liability" id="passenger_liability_no" name="motor.passenger_liability" value="No" type="radio" class="form-check-input" />
                                                                                    <label for="passenger_liability_no">No</label>
                                                                                </div>
                                                                            </div>
                                                                            <div class="form-floating mb-3">
                                                                                <input title="premium_passenger_liability" type="text" wire:model.defer="motor.premium_passenger_liability" @blur="$el.value = window.formatAmount($el.value)"placeholder="premium_passenger_liability" class="form-control"/>
                                                                                <x-form-label for="premium_passenger_liability" required value="{{ __('Premium') }}"/>
                                                                                <x-form-input-error name="motor.premium_passenger_liability"/>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    <div class="row">
                                                                        <div class="col-sm-4">
                                                                            <div class="form-floating mb-3">
                                                                                <label for="question">Third party liability</label><br><br>
                                                                                <input class="form-control" type="text" wire:model.defer="third_party_liability" value="{{$this->third_party_liability}}">
                                                                            </div>
                                                                            <div class="form-floating mb-3">
                                                                                <input title="premium_third_party_liability" type="text" wire:model.defer="motor.premium_third_party_liability" @blur="$el.value = window.formatAmount($el.value)"placeholder="premium_third_party_liability" class="form-control"/>
                                                                                <x-form-label for="premium_third_party_liability" required value="{{ __('Premium') }}"/>
                                                                                <x-form-input-error name="motor.premium_third_party_liability"/>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-sm-4">
                                                                            <div class="form-floating mb-3">
                                                                                <label for="question">Specified accessories</label><br><br>
                                                                                <div class="form-check form-check-inline" style="margin-left: 12px">
                                                                                    <input wire:model.defer="motor.specified_accessories" id="specified_accessories_yes" name="motor.specified_accessories" value="Yes" type="radio" class="form-check-input" />
                                                                                    <label for="specified_accessories_yes">Yes</label>
                                                                                </div>
                                                                                <div class="form-check form-check-inline" style="margin-left: 2px">
                                                                                    <input wire:model.defer="motor.specified_accessories" id="specified_accessories_no" name="motor.specified_accessories" value="No" type="radio" class="form-check-input" />
                                                                                    <label for="specified_accessories_no">No</label>
                                                                                </div>
                                                                            </div>
                                                                            <div class="form-floating mb-3">
                                                                                <input title="premium_specified_accessories" type="text" wire:model.defer="motor.premium_specified_accessories" @blur="$el.value = window.formatAmount($el.value)"placeholder="premium_specified_accessories" class="form-control"/>
                                                                                <x-form-label for="premium_specified_accessories" required value="{{ __('Premium') }}"/>
                                                                                <x-form-input-error name="motor.premium_specified_accessories"/>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <hr>
                                                                <!-- Third_fire_and_theft Excesses -->
                                                                <div class="row">
                                                                    <h4>Excesses</h4>
                                                                    <hr>
                                                                    <div class="row">
                                                                        <div class="col-sm-4">
                                                                            <div class="form-floating mb-3">
                                                                                <input type="text" class="form-control" wire:model.defer="motor.own_damage"
                                                                                    id="motor.own_damage" placeholder="Own Damage"/>
                                                                                <x-form-label for="motor.own_damage" value="Own Damage" />
                                                                                <x-form-input-error name="motor.own_damage"/>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-sm-4">
                                                                            <div class="form-floating mb-3">
                                                                                <input type="text" class="form-control" wire:model.defer="motor.own_damage_minimun_percent"
                                                                                    id="motor.own_damage_minimun_percent" placeholder="Min %"/>
                                                                                    <span style="color:red">Please Enter Number Only without % sign</span>
                                                                                <x-form-label for="motor.own_damage_minimun_percent" value="Min %" />
                                                                                <x-form-input-error name="motor.own_damage_minimun_percent"/>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-sm-4">
                                                                            <div class="form-floating mb-3">
                                                                                <input class="form-control" wire:model.defer="motor.own_damage_minimum_amount"
                                                                                    id="motor.own_damage_minimum_amount" placeholder="Minimum Amount"
                                                                                    @blur="$el.value = window.formatAmount($el.value)" />
                                                                                <x-form-label for="motor.own_damage_minimum_amount" value="Minimum Amount" />
                                                                                <x-form-input-error name="motor.own_damage_minimum_amount"/>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    <div class="row">
                                                                        <div class="col-sm-4">
                                                                            <div class="form-floating mb-3">
                                                                                <input type="text" class="form-control" wire:model.defer="motor.windscreen"
                                                                                    id="motor.windscreen" placeholder="Windscreen"/>
                                                                                <x-form-label for="motor.windscreen" value="Windscreen" />
                                                                                <x-form-input-error name="motor.windscreen"/>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-sm-4">
                                                                            <div class="form-floating mb-3">
                                                                                <input type="text" class="form-control" wire:model.defer="motor.windscreen_minimun_percent"
                                                                                    id="motor.windscreen_minimun_percent" placeholder="Min %"/>
                                                                                    <span style="color:red">Please Enter Number Only without % sign</span>
                                                                                <x-form-label for="motor.windscreen_minimun_percent" value="Min %" />
                                                                                <x-form-input-error name="motor.windscreen_minimun_percent"/>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-sm-4">
                                                                            <div class="form-floating mb-3">
                                                                                <input class="form-control" wire:model.defer="motor.windscreen_minimum_amount"
                                                                                    id="motor.windscreen_minimum_amount" placeholder="minimum_amount"
                                                                                    @blur="$el.value = window.formatAmount($el.value)" />
                                                                                <x-form-label for="motor.windscreen_minimum_amount" value="Minimum Amount" />
                                                                                <x-form-input-error name="motor.windscreen_minimum_amount"/>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    <div class="row">
                                                                        <div class="col-sm-4">
                                                                            <div class="form-floating mb-3">
                                                                                <input type="text" class="form-control" wire:model.defer="motor.loss_of_keys"
                                                                                    id="motor.loss_of_keys" placeholder="Loss of Keys"/>
                                                                                <x-form-label for="motor.loss_of_keys" value="Loss of Keys" />
                                                                                <x-form-input-error name="motor.loss_of_keys"/>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-sm-4">
                                                                            <div class="form-floating mb-3">
                                                                                <input type="text" class="form-control" wire:model.defer="motor.loss_of_keys_minimun_percent"
                                                                                    id="motor.loss_of_keys_minimun_percent" placeholder="motor.loss_of_keys_minimun_percent"/>
                                                                                <x-form-label for="motor.loss_of_keys_minimun_percent" value="Min %" />
                                                                                <span style="color:red">Please Enter Number Only without % sign</span>
                                                                                <x-form-input-error name="motor.loss_of_keys_minimun_percent"/>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-sm-4">
                                                                            <div class="form-floating mb-3">
                                                                                <input class="form-control" wire:model.defer="motor.loss_of_keys_minimum_amount"
                                                                                    id="motor.loss_of_keys_minimum_amount" placeholder="motor.loss_of_keys_minimum_amount"
                                                                                    @blur="$el.value = window.formatAmount($el.value)" />
                                                                                <x-form-label for="motor.loss_of_keys_minimum_amount" value="Minimum Amount" />
                                                                                <x-form-input-error name="motor.loss_of_keys_minimum_amount"/>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            @elseif($policyCoverage->coverage['s_CoverageCode'] == "COMMERCIALMOTOR")
                                                                <!-- Third_fire_and_theft Excesses -->
                                                                <div class="row">
                                                                    <h4>Excesses</h4>
                                                                    <hr>
                                                                    <div class="row">
                                                                        <div class="col-sm-4">
                                                                            <div class="form-floating mb-3">
                                                                                <input type="text" class="form-control" wire:model.defer="motor.own_damage"
                                                                                    id="motor.own_damage" placeholder="Basic Excess"/>
                                                                                <x-form-label for="motor.own_damage" value="Basic Excess" />
                                                                                <x-form-input-error name="motor.own_damage"/>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-sm-4">
                                                                            <div class="form-floating mb-3">
                                                                                <input type="text" class="form-control" wire:model.defer="motor.own_damage_minimun_percent"
                                                                                    id="motor.own_damage_minimun_percent" placeholder="Min %"/>
                                                                                    <span style="color:red">Please Enter Number Only without % sign</span>
                                                                                <x-form-label for="motor.own_damage_minimun_percent" value="Min %" />
                                                                                <x-form-input-error name="motor.own_damage_minimun_percent"/>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-sm-4">
                                                                            <div class="form-floating mb-3">
                                                                                <input class="form-control" wire:model.defer="motor.own_damage_minimum_amount"
                                                                                    id="motor.own_damage_minimum_amount" placeholder="Minimum Amount"
                                                                                    @blur="$el.value = window.formatAmount($el.value)" />
                                                                                <x-form-label for="motor.own_damage_minimum_amount" value="Minimum Amount" />
                                                                                <x-form-input-error name="motor.own_damage_minimum_amount"/>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <!-- Third_fire_and_theft Extensions -->
                                                                <div class="row">
                                                                    <h4>Extensions and Clauses</h4>
                                                                    <hr>
                                                                    <div class="row">
                                                                        <div class="col-sm-4">
                                                                            <div class="form-floating mb-3">
                                                                                <label for="question">Riot and strike</label><br><br>
                                                                                <div class="form-check form-check-inline" style="margin-left: 12px">
                                                                                    <input wire:model.defer="motor.riot_strike" id="riot_strike_yes" name="motor.riot_strike" value="Yes" type="radio" class="form-check-input" />
                                                                                    <label for="riot_strike_yes">Yes</label>
                                                                                </div>
                                                                                <div class="form-check form-check-inline" style="margin-left: 2px">
                                                                                    <input wire:model.defer="motor.riot_strike" id="riot_strike_no" name="motor.riot_strike" value="No" type="radio" class="form-check-input" />
                                                                                    <label for="riot_strike_no">No</label>
                                                                                </div>
                                                                            </div>
                                                                            <div class="form-floating mb-3">
                                                                                <input title="premium_riot_strike" type="text" wire:model.defer="motor.premium_riot_strike" @blur="$el.value = window.formatAmount($el.value)"placeholder="premium_riot_strike" class="form-control"/>
                                                                                <x-form-label for="premium_riot_strike" required value="{{ __('Premium') }}"/>
                                                                                <x-form-input-error name="motor.premium_riot_strike"/>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-sm-4">
                                                                            <div class="form-floating mb-3">
                                                                                <label for="question">Wreckage removal</label><br><br>
                                                                                <div class="form-check form-check-inline" style="margin-left: 12px">
                                                                                    <input wire:model.defer="motor.wreckage_removal" id="wreckage_removal_yes" name="motor.wreckage_removal" value="Yes" type="radio" class="form-check-input" />
                                                                                    <label for="wreckage_removal_yes">Yes</label>
                                                                                </div>
                                                                                <div class="form-check form-check-inline" style="margin-left: 2px">
                                                                                    <input wire:model.defer="motor.wreckage_removal" id="wreckage_removal_no" name="motor.wreckage_removal" value="No" type="radio" class="form-check-input" />
                                                                                    <label for="wreckage_removal_no">No</label>
                                                                                </div>
                                                                            </div>
                                                                            <div class="form-floating mb-3">
                                                                                <input title="premium_wreckage_removal" type="text" wire:model.defer="motor.premium_wreckage_removal" @blur="$el.value = window.formatAmount($el.value)"placeholder="premium_wreckage_removal" class="form-control"/>
                                                                                <x-form-label for="premium_wreckage_removal" required value="{{ __('Premium') }}"/>
                                                                                <x-form-input-error name="motor.premium_wreckage_removal"/>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-sm-4">
                                                                            <div class="form-floating mb-3">
                                                                                <label for="question">Credit Shortfall</label><br><br>
                                                                                <div class="form-check form-check-inline" style="margin-left: 12px">
                                                                                    <input wire:model.defer="motor.credit_shortfall" id="credit_shortfall_yes" name="motor.credit_shortfall" value="Yes" type="radio" class="form-check-input" />
                                                                                    <label for="credit_shortfall_yes">Yes</label>
                                                                                </div>
                                                                                <div class="form-check form-check-inline" style="margin-left: 2px">
                                                                                    <input wire:model.defer="motor.credit_shortfall" id="credit_shortfall_no" name="motor.credit_shortfall" value="No" type="radio" class="form-check-input" />
                                                                                    <label for="credit_shortfall_no">No</label>
                                                                                </div>
                                                                            </div>
                                                                            <div class="form-floating mb-3">
                                                                                <input title="premium_credit_shortfall" type="text" wire:model.defer="motor.premium_credit_shortfall" @blur="$el.value = window.formatAmount($el.value)"placeholder="premium_credit_shortfall" class="form-control"/>
                                                                                <x-form-label for="premium_credit_shortfall" required value="{{ __('Premium') }}"/>
                                                                                <x-form-input-error name="motor.premium_credit_shortfall"/>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    <div class="row">
                                                                        <div class="col-sm-4">
                                                                            <div class="form-floating mb-3">
                                                                                <label for="question">Third party liability</label><br><br>
                                                                                <input class="form-control" type="text" wire:model.defer="third_party_liability" value="{{$this->third_party_liability}}">
                                                                            </div>
                                                                            <div class="form-floating mb-3">
                                                                                <input title="premium_third_party_liability" type="text" wire:model.defer="motor.premium_third_party_liability" @blur="$el.value = window.formatAmount($el.value)"placeholder="premium_third_party_liability" class="form-control"/>
                                                                                <x-form-label for="premium_third_party_liability" required value="{{ __('Premium') }}"/>
                                                                                <x-form-input-error name="motor.premium_third_party_liability"/>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            @endif
                                                            <div class="row">
                                                            <div class="col-sm-12">
                                                                <div class="card bg-light shadow-sm">
                                                                    <div class="card-header">
                                                                        <h3 class="card-title">Note</h3>
                                                                    </div>
                                                                     <div class="card-body card-scroll h-200px">
                                                                        <div class="form-floating">
                                                                          @php
                                                                           $key = isset($this->motorVehicleData['motor_id']) && $this->motorVehicleData['motor_id']
                                                                                    ? $this->motorVehicleData['motor_id'].'_'.$policyCoverage->id
                                                                                    : $policyCoverage->id;
                                                                            @endphp
                                                                           <x-form-text-area 
                                                                            wire:model.defer="policyCoverageNote.{{ $key }}"
                                                                            class="h-100" 
                                                                            style="height: 15em !important;"
                                                                        />
                                                                        <label>Enter Your Note</label>
                                                                        <x-form-input-error name="policyCoverageNote.{{ $key }}"/>                                                 </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="row">
                                                                <div class="col-sm-12">
                                                                    <div class="card bg-light shadow-sm">
                                                                        <div class="card-header">
                                                                            <h3 class="card-title">Miscellaneous Items Comprenhsive</h3>
                                                                            <div class="card-toolbar">
                                                                                <button type="button" class="btn btn-sm btn-primary" wire:click.prevent="addSpecifiedRow({{ $policyCoverage->id}})">
                                                                                    Add <Row></Row>
                                                                                </button>
                                                                            </div>
                                                                        </div>
                                                                        <div class="card-body card-scroll h-200px">
                                                                            @foreach($this->specifiedRow[$policyCoverage->id] ?? [] as $key => $value)
                                                                                <div class="row">
                                                                                    <div class="col-sm-5">
                                                                                        <div class="form-floating mb-3">
                                                                                            <x-select wire:model.defer="specified_items.{{ $policyCoverage->id }}.{{ $key }}.selected_item"
                                                                                                    :options="$this->specifiedItems[$policyCoverage->coverage_id]" />
                                                                                            <x-form-label for="specified_items.{{ $policyCoverage->id }}.{{ $key }}.selected_item" required value="{{ __('Select Description Of Item') }}"/>
                                                                                            <x-form-input-error name="specified_items.{{ $policyCoverage->id }}.{{ $key }}.selected_item"/>
                                                                                        </div>
                                                                                    </div>
                                                                                    <div class="col-sm-5">
                                                                                        <div class="form-floating mb-3">
                                                                                            <x-form-input wire:model.defer="specified_items.{{ $policyCoverage->id }}.{{ $key }}.sum_insured"
                                                                                                        placeholder="Sum Insured" class="amount-field" @blur="$el.value = window.formatAmount($el.value)"/>
                                                                                            <x-form-label for="specified_items.{{ $policyCoverage->id }}.{{ $key }}.sum_insured" required value="{{ __('Sum Insured') }}"/>
                                                                                            <x-form-input-error name="specified_items.{{ $policyCoverage->id }}.{{ $key }}.sum_insured"/>
                                                                                        </div>
                                                                                    </div>
                                                                                    <div class="col-sm-2">
                                                                                        @if( isset($policyCoverage->specifedItems[$key])
                                                                                            && $policyCoverage->specifedItems[$key]->deleted_at === null)
                                                                                            <button type="button" class="btn btn-danger btn-small"
                                                                                                wire:click.prevent="removeRow(
                                                                                                    {{ $policyCoverage->id }},
                                                                                                    {{ $key }}
                                                                                                    @isset($policyCoverage->specifedItems[$key]->id),{{ $policyCoverage->specifedItems[$key]->id }}@endisset
                                                                                                )">
                                                                                                &times;
                                                                                            </button>
                                                                                              @elseif(!isset($policyCoverage->specifedItems[$key]))
                                                                                               <button type="button" class="btn btn-danger btn-small" wire:click.prevent="removeRow({{ $policyCoverage->id }},{{ $key }})">&times;</button>
                                                                                   
                                                                                        @else
                                                                                            <a wire:click.prevent="reinstateMisc('{{ $policyCoverage->specifedItems[$key]->id ?? '' }}')"
                                                                                            class="btn btn-sm btn-primary"
                                                                                            style="position: absolute; right: 75px;">
                                                                                            Reinstate
                                                                                            </a>
                                                                                        @endif</div>
                                                                                </div>
                                                                            @endforeach
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                        </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <hr>
                                        @endif
                                        {{-- End MOTOR --}}

                                        {{-- Start MOTOR TRADER EXTERNAL--}}
                                        @if($policyCoverage->coverage['s_CoverageCode'] == "MOTORTRADERSEXTERNAL" || $policyCoverage->coverage_id == 15)
                                            <div class="row">
                                                <div class="col-sm-8">
                                                    <select title="Type of cover" wire:model.defer="motorTradersExternal.type_of_cover_external_main_{{ $policyCoverage->id }}" aria-label="Select Type of cover" wire:change="MotorTradersExternal($event.target.value,{{$policyCoverage->id}})"  class="form-select form-select-solid type_of_cover_main">
                                                        <option value="">-Select-</option>
                                                        <option value="ComprehensiveMotorTradersExternal">Comprehensive</option>
                                                        <option value="TPMotorTradersExternal">Third party only</option>
                                                        <option value="TPFTMotorTradersExternal">Third party, fire and theft</option>
                                                    </select>
                                                </div>
                                                <div class="col-sm-4">
                                                    @php
                                                    $tradersExternalData = AlphaDirect\Models\MotorTraders::where('policy_coverage_id',$policyCoverage->id)->first();
                                                    @endphp
                                                    @if(isset($tradersExternalData) && $tradersExternalData['type_of_cover'] == "ComprehensiveMotorTradersExternal")
                                                        <button class="btn btn-primary" value="ComprehensiveMotorTradersExternal" wire:click="MotorTradersExternal($event.target.value,{{$policyCoverage->id}})" >View</button>
                                                    @elseif(isset($tradersExternalData) && $tradersExternalData['type_of_cover'] == "TPMotorTradersExternal")
                                                        <button class="btn btn-primary" value="TPMotorTradersExternal" wire:click="MotorTradersExternal($event.target.value,{{$policyCoverage->id}})" >View</button>
                                                    @elseif(isset($tradersExternalData) && $tradersExternalData['type_of_cover'] == "TPFTMotorTradersExternal")
                                                        <button class="btn btn-primary" value="TPFTMotorTradersExternal" wire:click="MotorTradersExternal($event.target.value,{{$policyCoverage->id}})" >View</button>
                                                    @else
                                                            -
                                                    @endif
                                                </div>
                                            </div>
                                            <x-form-input-error name="motorTradersExternal.type_of_cover_main"/>
                                            <div class="row">
                                                <div class="col-sm-12">
                                                    <div class='card card-custom gutter-b'>
                                                        <div class="card-header">
                                                            <div class="card-title">
                                                                <h2 class="ComprehensiveMotorTradersExternal_section" @if(!$ComprehensiveMotorTradersExternal_section) style="display: none;" @endif>
                                                                Motor Traders External Comprehensive
                                                                </h2>
                                                                <h2 class="TPMotorTradersExternal_section" @if(!$TPMotorTradersExternal_section) style="display: none;" @endif>
                                                                    Motor Traders External FTP
                                                                </h2>
                                                                <h2 class="TPFTMotorTradersExternal_section" @if(!$TPFTMotorTradersExternal_section) style="display: none;" @endif>
                                                                    Motor Traders External FTPFT
                                                                </h2>
                                                            </div>
                                                        </div>
                                                        <div class="card-body ComprehensiveMotorTradersExternal_section" @if(!$ComprehensiveMotorTradersExternal_section) style="display: none;" @endif>
                                                            <input type="hidden"  title="policy_coverage_id" wire:model.defer="motorTradersExternal.policy_coverage_id" class="form-control"
                                                                    id="motorTradersExternal.policy_coverage_id" placeholder="policy_coverage_id" />
                                                            <div class="row">
                                                                <div class="row">
                                                                    <div class="col-sm-4">
                                                                    <div class="form-floating mb-3">
                                                                        <label for="question">Loss or damage : </label><br><br>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="Sum Insured" id="input2" class="form-control amount-field" wire:model.defer="motorTradersExternal.loss_or_damage_coverage_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            id="motorTradersExternal.loss_or_damage_coverage_value" placeholder="Sum Insured"/>
                                                                            <x-form-label for="loss_or_damage_coverage_value" required value="{{ __('Sum Insured') }}"/>
                                                                            <x-form-input-error name="motorTradersExternal.loss_or_damage_coverage_value"/>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="loss_or_damage_calculated_value" type="text" id="input4" wire:model.defer="motorTradersExternal.loss_or_damage_calculated_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            placeholder="Premium" class="form-control amount-field"/>
                                                                            <x-form-label for="loss_or_damage_calculated_value" required value="{{ __('Premium') }}"/>
                                                                            <x-form-input-error name="motorTradersExternal.loss_or_damage_calculated_value"/>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="row">
                                                                    <div class="col-sm-4">
                                                                    <div class="form-floating mb-3">
                                                                        <label for="question">Third party liability : </label><br><br>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="Sum Insured" id="input2" class="form-control amount-field" wire:model.defer="motorTradersExternal.third_party_liability_coverage_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            id="motorTradersExternal.third_party_liability_coverage_value" placeholder="Sum Insured"/>
                                                                            <x-form-label for="third_party_liability_coverage_value" required value="{{ __('Sum Insured') }}"/>
                                                                            <x-form-input-error name="motorTradersExternal.third_party_liability_coverage_value"/>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="third_party_liability_calculated_value" type="text" id="input4" wire:model.defer="motorTradersExternal.third_party_liability_calculated_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            placeholder="Premium" class="form-control amount-field"/>
                                                                            <x-form-label for="third_party_liability_calculated_value" required value="{{ __('Premium') }}"/>
                                                                            <x-form-input-error name="motorTradersExternal.third_party_liability_calculated_value"/>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="row">
                                                                    <div class="col-sm-4">
                                                                    <div class="form-floating mb-3">
                                                                        <label for="question">Medical benefits : </label><br><br>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="Sum Insured" id="input2" class="form-control amount-field" wire:model.defer="motorTradersExternal.medical_benefits_coverage_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            id="motorTradersExternal.medical_benefits_coverage_value" placeholder="Sum Insured"/>
                                                                            <x-form-label for="medical_benefits_coverage_value" required value="{{ __('Sum Insured') }}"/>
                                                                            <x-form-input-error name="motorTradersExternal.medical_benefits_coverage_value"/>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="medical_benefits_calculated_value" type="text" id="input4" wire:model.defer="motorTradersExternal.medical_benefits_calculated_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            placeholder="Premium" class="form-control amount-field"/>
                                                                            <x-form-label for="medical_benefits_calculated_value" required value="{{ __('Premium') }}"/>
                                                                            <x-form-input-error name="motorTradersExternal.medical_benefits_calculated_value"/>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <hr>
                                                            <h4>Extensions and Clauses</h4><br>
                                                            <div class="row">
                                                                <div class="row">
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                        <label for="question">Vehicles lent or hired to Customers : </label><br><br>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="Sum Insured" id="input2" class="form-control amount-field" wire:model.defer="motorTradersExternal.vehicle_lent_hire_coverage_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            id="motorTradersExternal.vehicle_lent_hire_coverage_value" placeholder="Sum Insured"/>
                                                                            <x-form-label for="vehicle_lent_hire_coverage_value" required value="{{ __('Sum Insured') }}"/>
                                                                            <x-form-input-error name="motorTradersExternal.vehicle_lent_hire_coverage_value"/>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="vehicle_lent_hire_calculated_value" type="text" id="input4" wire:model.defer="motorTradersExternal.vehicle_lent_hire_calculated_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            placeholder="Premium" class="form-control amount-field"/>
                                                                            <x-form-label for="vehicle_lent_hire_calculated_value" required value="{{ __('Premium') }}"/>
                                                                            <x-form-input-error name="motorTradersExternal.vehicle_lent_hire_calculated_value"/>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="row">
                                                                    <div class="col-sm-4">
                                                                    <div class="form-floating mb-3">
                                                                        <label for="question">Social, domestic and pleasure use : </label><br><br>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="Sum Insured" id="input2" class="form-control amount-field" wire:model.defer="motorTradersExternal.social_domestic_pleasure_coverage_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            id="motorTradersExternal.social_domestic_pleasure_coverage_value" placeholder="Sum Insured"/>
                                                                            <x-form-label for="social_domestic_pleasure_coverage_value" required value="{{ __('Sum Insured') }}"/>
                                                                            <x-form-input-error name="motorTradersExternal.social_domestic_pleasure_coverage_value"/>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="social_domestic_pleasure_calculated_value" type="text" id="input4" wire:model.defer="motorTradersExternal.social_domestic_pleasure_calculated_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            placeholder="Premium" class="form-control amount-field"/>
                                                                            <x-form-label for="social_domestic_pleasure_calculated_value" required value="{{ __('Premium') }}"/>
                                                                            <x-form-input-error name="motorTradersExternal.social_domestic_pleasure_calculated_value"/>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="row">
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                        <label for="question">Unauthoried use by employees : </label><br><br>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="Sum Insured" id="input2" class="form-control amount-field" wire:model.defer="motorTradersExternal.unauthoried_use_coverage_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            id="motorTradersExternal.unauthoried_use_coverage_value" placeholder="Sum Insured"/>
                                                                            <x-form-label for="unauthoried_use_coverage_value" required value="{{ __('Sum Insured') }}"/>
                                                                            <x-form-input-error name="motorTradersExternal.unauthoried_use_coverage_value"/>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="unauthoried_use_calculated_value" type="text" id="input4" wire:model.defer="motorTradersExternal.unauthoried_use_calculated_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            placeholder="Premium" class="form-control amount-field"/>
                                                                            <x-form-label for="unauthoried_use_calculated_value" required value="{{ __('Premium') }}"/>
                                                                            <x-form-input-error name="motorTradersExternal.unauthoried_use_calculated_value"/>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="row">
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                        <label for="question">Windscreen : </label><br><br>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="Sum Insured" id="input2" class="form-control amount-field" wire:model.defer="motorTradersExternal.windscreen_coverage_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            id="motorTradersExternal.windscreen_coverage_value" placeholder="Sum Insured"/>
                                                                            <x-form-label for="windscreen_coverage_value" required value="{{ __('Sum Insured') }}"/>
                                                                            <x-form-input-error name="motorTradersExternal.windscreen_coverage_value"/>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="windscreen_calculated_value" type="text" id="input4" wire:model.defer="motorTradersExternal.windscreen_calculated_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            placeholder="Premium" class="form-control amount-field"/>
                                                                            <x-form-label for="windscreen_calculated_value" required value="{{ __('Premium') }}"/>
                                                                            <x-form-input-error name="motorTradersExternal.windscreen_calculated_value"/>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="row">
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                        <label for="question">Contigent liability : </label><br><br>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="Sum Insured" id="input2" class="form-control amount-field" wire:model.defer="motorTradersExternal.contigent_liability_coverage_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            id="motorTradersExternal.contigent_liability_coverage_value" placeholder="Sum Insured"/>
                                                                            <x-form-label for="contigent_liability_coverage_value" required value="{{ __('Sum Insured') }}"/>
                                                                            <x-form-input-error name="motorTradersExternal.contigent_liability_coverage_value"/>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="contigent_liability_calculated_value" type="text" id="input4" wire:model.defer="motorTradersExternal.contigent_liability_calculated_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            placeholder="Premium" class="form-control amount-field"/>
                                                                            <x-form-label for="contigent_liability_calculated_value" required value="{{ __('Premium') }}"/>
                                                                            <x-form-input-error name="motorTradersExternal.contigent_liability_calculated_value"/>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="row">
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                        <label for="question">Wreckage removal : </label><br><br>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="Sum Insured" id="input2" class="form-control amount-field" wire:model.defer="motorTradersExternal.wreckage_removal_coverage_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            id="motorTradersExternal.wreckage_removal_coverage_value" placeholder="Sum Insured"/>
                                                                            <x-form-label for="wreckage_removal_coverage_value" required value="{{ __('Sum Insured') }}"/>
                                                                            <x-form-input-error name="motorTradersExternal.wreckage_removal_coverage_value"/>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="wreckage_removal_calculated_value" type="text" id="input4" wire:model.defer="motorTradersExternal.wreckage_removal_calculated_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            placeholder="Premium" class="form-control amount-field"/>
                                                                            <x-form-label for="wreckage_removal_calculated_value" required value="{{ __('Premium') }}"/>
                                                                            <x-form-input-error name="motorTradersExternal.wreckage_removal_calculated_value"/>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="row">
                                                                    <div class="col-sm-4">
                                                                    <div class="form-floating mb-3">
                                                                        <label for="question">Loss of keys : </label><br><br>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="Sum Insured" id="input2" class="form-control amount-field" wire:model.defer="motorTradersExternal.loss_of_key_coverage_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            id="motorTradersExternal.loss_of_key_coverage_value" placeholder="Sum Insured"/>
                                                                            <x-form-label for="loss_of_key_coverage_value" required value="{{ __('Sum Insured') }}"/>
                                                                            <x-form-input-error name="motorTradersExternal.loss_of_key_coverage_value"/>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="loss_of_key_calculated_value" type="text" id="input4" wire:model.defer="motorTradersExternal.loss_of_key_calculated_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            placeholder="Premium" class="form-control amount-field"/>
                                                                            <x-form-label for="loss_of_key_calculated_value" required value="{{ __('Premium') }}"/>
                                                                            <x-form-input-error name="motorTradersExternal.loss_of_key_calculated_value"/>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="row">
                                                                    <div class="col-sm-4">
                                                                    <div class="form-floating mb-3">
                                                                        <label for="question">Loss of use of customer's vehicle : </label><br><br>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="Sum Insured" id="input2" class="form-control amount-field" wire:model.defer="motorTradersExternal.Loss_of_use_of_customer_coverage_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            id="motorTradersExternal.Loss_of_use_of_customer_coverage_value" placeholder="Sum Insured"/>
                                                                            <x-form-label for="Loss_of_use_of_customer_coverage_value" required value="{{ __('Sum Insured') }}"/>
                                                                            <x-form-input-error name="motorTradersExternal.Loss_of_use_of_customer_coverage_value"/>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="Loss_of_use_of_customer_calculated_value" type="text" id="input4" wire:model.defer="motorTradersExternal.Loss_of_use_of_customer_calculated_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            placeholder="Premium" class="form-control amount-field"/>
                                                                            <x-form-label for="Loss_of_use_of_customer_calculated_value" required value="{{ __('Premium') }}"/>
                                                                            <x-form-input-error name="motorTradersExternal.Loss_of_use_of_customer_calculated_value"/>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="row">
                                                                    <div class="col-sm-4">
                                                                    <div class="form-floating mb-3">
                                                                        <label for="question">Motor cycle, motor tricycle or quad bike : </label><br><br>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="Sum Insured" id="input2" class="form-control amount-field" wire:model.defer="motorTradersExternal.motor_cycle_motor_tricycle_coverage_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            id="motorTradersExternal.motor_cycle_motor_tricycle_coverage_value" placeholder="Sum Insured"/>
                                                                            <x-form-label for="motor_cycle_motor_tricycle_coverage_value" required value="{{ __('Sum Insured') }}"/>
                                                                            <x-form-input-error name="motorTradersExternal.motor_cycle_motor_tricycle_coverage_value"/>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="motor_cycle_motor_tricycle_calculated_value" type="text" id="input4" wire:model.defer="motorTradersExternal.motor_cycle_motor_tricycle_calculated_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            placeholder="Premium" class="form-control amount-field"/>
                                                                            <x-form-label for="motor_cycle_motor_tricycle_calculated_value" required value="{{ __('Premium') }}"/>
                                                                            <x-form-input-error name="motorTradersExternal.motor_cycle_motor_tricycle_calculated_value"/>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="row">
                                                                    <div class="col-sm-4">
                                                                    <div class="form-floating mb-3">
                                                                            Passanger liability in respect of motor
                                                                        <label for="question">cycles and motor tricycles : </label><br><br>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="Sum Insured" id="input2" class="form-control amount-field" wire:model.defer="motorTradersExternal.passanger_liability_respect_of_motor_coverage_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            id="motorTradersExternal.passanger_liability_respect_of_motor_coverage_value" placeholder="Sum Insured"/>
                                                                            <x-form-label for="passanger_liability_respect_of_motor_coverage_value" required value="{{ __('Sum Insured') }}"/>
                                                                            <x-form-input-error name="motorTradersExternal.passanger_liability_respect_of_motor_coverage_value"/>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="passanger_liability_respect_of_motor_calculated_value" type="text" id="input4" wire:model.defer="motorTradersExternal.passanger_liability_respect_of_motor_calculated_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            placeholder="Premium" class="form-control amount-field"/>
                                                                            <x-form-label for="passanger_liability_respect_of_motor_calculated_value" required value="{{ __('Premium') }}"/>
                                                                            <x-form-input-error name="motorTradersExternal.passanger_liability_respect_of_motor_calculated_value"/>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="row">
                                                                    <div class="col-sm-4">
                                                                    <div class="form-floating mb-3">
                                                                        <label for="question">Special type vehicle : </label><br><br>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="Sum Insured" id="input2" class="form-control amount-field" wire:model.defer="motorTradersExternal.special_type_vehicle_coverage_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            id="motorTradersExternal.special_type_vehicle_coverage_value" placeholder="Sum Insured"/>
                                                                            <x-form-label for="special_type_vehicle_coverage_value" required value="{{ __('Sum Insured') }}"/>
                                                                            <x-form-input-error name="motorTradersExternal.special_type_vehicle_coverage_value"/>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="special_type_vehicle_calculated_value" type="text" id="input4" wire:model.defer="motorTradersExternal.special_type_vehicle_calculated_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            placeholder="Premium" class="form-control amount-field"/>
                                                                            <x-form-label for="special_type_vehicle_calculated_value" required value="{{ __('Premium') }}"/>
                                                                            <x-form-input-error name="motorTradersExternal.special_type_vehicle_calculated_value"/>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <hr>
                                                            <h4>Excesses</h4>
                                                            <div class="row">
                                                                <div class="row">
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                        <label for="question">Own Damage : </label><br><br>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input type="text" class="form-control" wire:model.defer="motorTradersExternal.own_damage_minimun_percent"
                                                                                id="motorTradersExternal.own_damage_minimun_percent" placeholder="Min %"/>
                                                                                <span style="color:red">Please Enter Number Only without % sign</span>
                                                                            <x-form-label for="motorTradersExternal.own_damage_minimun_percent" value="Min %" />
                                                                            <x-form-input-error name="motorTradersExternal.own_damage_minimun_percent"/>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input class="form-control" wire:model.defer="motorTradersExternal.own_damage_minimum_amount"
                                                                                id="motorTradersExternal.own_damage_minimum_amount" placeholder="Minimum Amount"
                                                                                @blur="$el.value = window.formatAmount($el.value)" />
                                                                            <x-form-label for="motorTradersExternal.own_damage_minimum_amount" value="Minimum Amount" />
                                                                            <x-form-input-error name="motorTradersExternal.own_damage_minimum_amount"/>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="row">
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                        <label for="question">Windscreen : </label><br><br>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input type="text" class="form-control" wire:model.defer="motorTradersExternal.windscreen_minimun_percent"
                                                                                id="motorTradersExternal.windscreen_minimun_percent" placeholder="Min %"/>
                                                                                <span style="color:red">Please Enter Number Only without % sign</span>
                                                                            <x-form-label for="motorTradersExternal.windscreen_minimun_percent" value="Min %" />
                                                                            <x-form-input-error name="motorTradersExternal.windscreen_minimun_percent"/>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input class="form-control" wire:model.defer="motorTradersExternal.windscreen_minimum_amount"
                                                                                id="motorTradersExternal.windscreen_minimum_amount" placeholder="minimum_amount"
                                                                                @blur="$el.value = window.formatAmount($el.value)" />
                                                                            <x-form-label for="motorTradersExternal.windscreen_minimum_amount" value="Minimum Amount" />
                                                                            <x-form-input-error name="motorTradersExternal.windscreen_minimum_amount"/>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="card-body TPMotorTradersExternal_section" @if(!$TPMotorTradersExternal_section) style="display: none;" @endif>
                                                            <input type="hidden"  title="policy_coverage_id" wire:model.defer="motorTradersExternal.policy_coverage_id" class="form-control"
                                                                    id="motorTradersExternal.policy_coverage_id" placeholder="policy_coverage_id" />
                                                            <div class="row">
                                                                <div class="col-sm-4">
                                                                    <div class="form-floating mb-3">
                                                                    <label for="question">Third party liability : </label><br><br>
                                                                    </div>
                                                                </div>
                                                                <div class="col-sm-4">
                                                                    <div class="form-floating mb-3">
                                                                        <input title="Sum Insured" id="input2" class="form-control amount-field" wire:model.defer="motorTradersExternal.third_party_liability_coverage_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                        id="motorTradersExternal.third_party_liability_coverage_value" placeholder="Sum Insured"/>
                                                                        <x-form-label for="third_party_liability_coverage_value" required value="{{ __('Sum Insured') }}"/>
                                                                        <x-form-input-error name="motorTradersExternal.third_party_liability_coverage_value"/>
                                                                    </div>
                                                                </div>
                                                                <div class="col-sm-4">
                                                                    <div class="form-floating mb-3">
                                                                        <input title="third_party_liability_calculated_value" type="text" id="input4" wire:model.defer="motorTradersExternal.third_party_liability_calculated_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                        placeholder="Premium" class="form-control amount-field"/>
                                                                        <x-form-label for="third_party_liability_calculated_value" required value="{{ __('Premium') }}"/>
                                                                        <x-form-input-error name="motorTradersExternal.third_party_liability_calculated_value"/>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <hr>
                                                        </div>
                                                        <div class="card-body TPFTMotorTradersExternal_section" @if(!$TPFTMotorTradersExternal_section) style="display: none;" @endif>
                                                                <input type="hidden"  title="policy_coverage_id" wire:model.defer="motorTradersExternal.policy_coverage_id" class="form-control"
                                                                    id="motorTradersExternal.policy_coverage_id" placeholder="policy_coverage_id" />
                                                            <div class="row">
                                                                <div class="row">
                                                                    <div class="col-sm-4">
                                                                    <div class="form-floating mb-3">
                                                                        <label for="question">Loss or damage : </label><br><br>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="Sum Insured" id="input2" class="form-control amount-field" wire:model.defer="motorTradersExternal.loss_or_damage_coverage_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            id="motorTradersExternal.loss_or_damage_coverage_value" placeholder="Sum Insured"/>
                                                                            <x-form-label for="loss_or_damage_coverage_value" required value="{{ __('Sum Insured') }}"/>
                                                                            <x-form-input-error name="motorTradersExternal.loss_or_damage_coverage_value"/>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="loss_or_damage_calculated_value" type="text" id="input4" wire:model.defer="motorTradersExternal.loss_or_damage_calculated_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            placeholder="Premium" class="form-control amount-field"/>
                                                                            <x-form-label for="loss_or_damage_calculated_value" required value="{{ __('Premium') }}"/>
                                                                            <x-form-input-error name="motorTradersExternal.loss_or_damage_calculated_value"/>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="row">
                                                                    <div class="col-sm-4">
                                                                    <div class="form-floating mb-3">
                                                                        <label for="question">Third party liability : </label><br><br>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="Sum Insured" id="input2" class="form-control amount-field" wire:model.defer="motorTradersExternal.third_party_liability_coverage_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            id="motorTradersExternal.third_party_liability_coverage_value" placeholder="Sum Insured"/>
                                                                            <x-form-label for="third_party_liability_coverage_value" required value="{{ __('Sum Insured') }}"/>
                                                                            <x-form-input-error name="motorTradersExternal.third_party_liability_coverage_value"/>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="third_party_liability_calculated_value" type="text" id="input4" wire:model.defer="motorTradersExternal.third_party_liability_calculated_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            placeholder="Premium" class="form-control amount-field"/>
                                                                            <x-form-label for="third_party_liability_calculated_value" required value="{{ __('Premium') }}"/>
                                                                            <x-form-input-error name="motorTradersExternal.third_party_liability_calculated_value"/>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="row">
                                                                    <div class="col-sm-4">
                                                                    <div class="form-floating mb-3">
                                                                        <label for="question">Medical benefits : </label><br><br>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="Sum Insured" id="input2" class="form-control amount-field" wire:model.defer="motorTradersExternal.medical_benefits_coverage_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            id="motorTradersExternal.medical_benefits_coverage_value" placeholder="Sum Insured"/>
                                                                            <x-form-label for="medical_benefits_coverage_value" required value="{{ __('Sum Insured') }}"/>
                                                                            <x-form-input-error name="motorTradersExternal.medical_benefits_coverage_value"/>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="medical_benefits_calculated_value" type="text" id="input4" wire:model.defer="motorTradersExternal.medical_benefits_calculated_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            placeholder="Premium" class="form-control amount-field"/>
                                                                            <x-form-label for="medical_benefits_calculated_value" required value="{{ __('Premium') }}"/>
                                                                            <x-form-input-error name="motorTradersExternal.medical_benefits_calculated_value"/>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <hr>
                                                            <h4>Extensions and Clauses</h4><br>
                                                            <div class="row">
                                                                <div class="row">
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                        <label for="question">Vehicles lent or hired to Customers : </label><br><br>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="Sum Insured" id="input2" class="form-control amount-field" wire:model.defer="motorTradersExternal.vehicle_lent_hire_coverage_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            id="motorTradersExternal.vehicle_lent_hire_coverage_value" placeholder="Sum Insured"/>
                                                                            <x-form-label for="vehicle_lent_hire_coverage_value" required value="{{ __('Sum Insured') }}"/>
                                                                            <x-form-input-error name="motorTradersExternal.vehicle_lent_hire_coverage_value"/>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="vehicle_lent_hire_calculated_value" type="text" id="input4" wire:model.defer="motorTradersExternal.vehicle_lent_hire_calculated_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            placeholder="Premium" class="form-control amount-field"/>
                                                                            <x-form-label for="vehicle_lent_hire_calculated_value" required value="{{ __('Premium') }}"/>
                                                                            <x-form-input-error name="motorTradersExternal.vehicle_lent_hire_calculated_value"/>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="row">
                                                                    <div class="col-sm-4">
                                                                    <div class="form-floating mb-3">
                                                                        <label for="question">Social, domestic and pleasure use : </label><br><br>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="Sum Insured" id="input2" class="form-control amount-field" wire:model.defer="motorTradersExternal.social_domestic_pleasure_coverage_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            id="motorTradersExternal.social_domestic_pleasure_coverage_value" placeholder="Sum Insured"/>
                                                                            <x-form-label for="social_domestic_pleasure_coverage_value" required value="{{ __('Sum Insured') }}"/>
                                                                            <x-form-input-error name="motorTradersExternal.social_domestic_pleasure_coverage_value"/>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="social_domestic_pleasure_calculated_value" type="text" id="input4" wire:model.defer="motorTradersExternal.social_domestic_pleasure_calculated_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            placeholder="Premium" class="form-control amount-field"/>
                                                                            <x-form-label for="social_domestic_pleasure_calculated_value" required value="{{ __('Premium') }}"/>
                                                                            <x-form-input-error name="motorTradersExternal.social_domestic_pleasure_calculated_value"/>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="row">
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                        <label for="question">Unauthoried use by employees : </label><br><br>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="Sum Insured" id="input2" class="form-control amount-field" wire:model.defer="motorTradersExternal.unauthoried_use_coverage_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            id="motorTradersExternal.unauthoried_use_coverage_value" placeholder="Sum Insured"/>
                                                                            <x-form-label for="unauthoried_use_coverage_value" required value="{{ __('Sum Insured') }}"/>
                                                                            <x-form-input-error name="motorTradersExternal.unauthoried_use_coverage_value"/>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="unauthoried_use_calculated_value" type="text" id="input4" wire:model.defer="motorTradersExternal.unauthoried_use_calculated_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            placeholder="Premium" class="form-control amount-field"/>
                                                                            <x-form-label for="unauthoried_use_calculated_value" required value="{{ __('Premium') }}"/>
                                                                            <x-form-input-error name="motorTradersExternal.unauthoried_use_calculated_value"/>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="row">
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                        <label for="question">Windscreen : </label><br><br>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="Sum Insured" id="input2" class="form-control amount-field" wire:model.defer="motorTradersExternal.windscreen_coverage_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            id="motorTradersExternal.windscreen_coverage_value" placeholder="Sum Insured"/>
                                                                            <x-form-label for="windscreen_coverage_value" required value="{{ __('Sum Insured') }}"/>
                                                                            <x-form-input-error name="motorTradersExternal.windscreen_coverage_value"/>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="windscreen_calculated_value" type="text" id="input4" wire:model.defer="motorTradersExternal.windscreen_calculated_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            placeholder="Premium" class="form-control amount-field"/>
                                                                            <x-form-label for="windscreen_calculated_value" required value="{{ __('Premium') }}"/>
                                                                            <x-form-input-error name="motorTradersExternal.windscreen_calculated_value"/>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="row">
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                        <label for="question">Contigent liability : </label><br><br>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="Sum Insured" id="input2" class="form-control amount-field" wire:model.defer="motorTradersExternal.contigent_liability_coverage_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            id="motorTradersExternal.contigent_liability_coverage_value" placeholder="Sum Insured"/>
                                                                            <x-form-label for="contigent_liability_coverage_value" required value="{{ __('Sum Insured') }}"/>
                                                                            <x-form-input-error name="motorTradersExternal.contigent_liability_coverage_value"/>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="contigent_liability_calculated_value" type="text" id="input4" wire:model.defer="motorTradersExternal.contigent_liability_calculated_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            placeholder="Premium" class="form-control amount-field"/>
                                                                            <x-form-label for="contigent_liability_calculated_value" required value="{{ __('Premium') }}"/>
                                                                            <x-form-input-error name="motorTradersExternal.contigent_liability_calculated_value"/>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="row">
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                        <label for="question">Wreckage removal : </label><br><br>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="Sum Insured" id="input2" class="form-control amount-field" wire:model.defer="motorTradersExternal.wreckage_removal_coverage_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            id="motorTradersExternal.wreckage_removal_coverage_value" placeholder="Sum Insured"/>
                                                                            <x-form-label for="wreckage_removal_coverage_value" required value="{{ __('Sum Insured') }}"/>
                                                                            <x-form-input-error name="motorTradersExternal.wreckage_removal_coverage_value"/>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="wreckage_removal_calculated_value" type="text" id="input4" wire:model.defer="motorTradersExternal.wreckage_removal_calculated_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            placeholder="Premium" class="form-control amount-field"/>
                                                                            <x-form-label for="wreckage_removal_calculated_value" required value="{{ __('Premium') }}"/>
                                                                            <x-form-input-error name="motorTradersExternal.wreckage_removal_calculated_value"/>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="row">
                                                                    <div class="col-sm-4">
                                                                    <div class="form-floating mb-3">
                                                                        <label for="question">Loss of keys : </label><br><br>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="Sum Insured" id="input2" class="form-control amount-field" wire:model.defer="motorTradersExternal.loss_of_key_coverage_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            id="motorTradersExternal.loss_of_key_coverage_value" placeholder="Sum Insured"/>
                                                                            <x-form-label for="loss_of_key_coverage_value" required value="{{ __('Sum Insured') }}"/>
                                                                            <x-form-input-error name="motorTradersExternal.loss_of_key_coverage_value"/>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="loss_of_key_calculated_value" type="text" id="input4" wire:model.defer="motorTradersExternal.loss_of_key_calculated_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            placeholder="Premium" class="form-control amount-field"/>
                                                                            <x-form-label for="loss_of_key_calculated_value" required value="{{ __('Premium') }}"/>
                                                                            <x-form-input-error name="motorTradersExternal.loss_of_key_calculated_value"/>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="row">
                                                                    <div class="col-sm-4">
                                                                    <div class="form-floating mb-3">
                                                                        <label for="question">Loss of use of customer's vehicle : </label><br><br>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="Sum Insured" id="input2" class="form-control amount-field" wire:model.defer="motorTradersExternal.Loss_of_use_of_customer_coverage_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            id="motorTradersExternal.Loss_of_use_of_customer_coverage_value" placeholder="Sum Insured"/>
                                                                            <x-form-label for="Loss_of_use_of_customer_coverage_value" required value="{{ __('Sum Insured') }}"/>
                                                                            <x-form-input-error name="motorTradersExternal.Loss_of_use_of_customer_coverage_value"/>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="Loss_of_use_of_customer_calculated_value" type="text" id="input4" wire:model.defer="motorTradersExternal.Loss_of_use_of_customer_calculated_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            placeholder="Premium" class="form-control amount-field"/>
                                                                            <x-form-label for="Loss_of_use_of_customer_calculated_value" required value="{{ __('Premium') }}"/>
                                                                            <x-form-input-error name="motorTradersExternal.Loss_of_use_of_customer_calculated_value"/>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="row">
                                                                    <div class="col-sm-4">
                                                                    <div class="form-floating mb-3">
                                                                        <label for="question">Motor cycle, motor tricycle or quad bike : </label><br><br>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="Sum Insured" id="input2" class="form-control amount-field" wire:model.defer="motorTradersExternal.motor_cycle_motor_tricycle_coverage_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            id="motorTradersExternal.motor_cycle_motor_tricycle_coverage_value" placeholder="Sum Insured"/>
                                                                            <x-form-label for="motor_cycle_motor_tricycle_coverage_value" required value="{{ __('Sum Insured') }}"/>
                                                                            <x-form-input-error name="motorTradersExternal.motor_cycle_motor_tricycle_coverage_value"/>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="motor_cycle_motor_tricycle_calculated_value" type="text" id="input4" wire:model.defer="motorTradersExternal.motor_cycle_motor_tricycle_calculated_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            placeholder="Premium" class="form-control amount-field"/>
                                                                            <x-form-label for="motor_cycle_motor_tricycle_calculated_value" required value="{{ __('Premium') }}"/>
                                                                            <x-form-input-error name="motorTradersExternal.motor_cycle_motor_tricycle_calculated_value"/>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="row">
                                                                    <div class="col-sm-4">
                                                                    <div class="form-floating mb-3">
                                                                            Passanger liability in respect of motor
                                                                        <label for="question">cycles and motor tricycles : </label><br><br>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="Sum Insured" id="input2" class="form-control amount-field" wire:model.defer="motorTradersExternal.passanger_liability_respect_of_motor_coverage_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            id="motorTradersExternal.passanger_liability_respect_of_motor_coverage_value" placeholder="Sum Insured"/>
                                                                            <x-form-label for="passanger_liability_respect_of_motor_coverage_value" required value="{{ __('Sum Insured') }}"/>
                                                                            <x-form-input-error name="motorTradersExternal.passanger_liability_respect_of_motor_coverage_value"/>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="passanger_liability_respect_of_motor_calculated_value" type="text" id="input4" wire:model.defer="motorTradersExternal.passanger_liability_respect_of_motor_calculated_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            placeholder="Premium" class="form-control amount-field"/>
                                                                            <x-form-label for="passanger_liability_respect_of_motor_calculated_value" required value="{{ __('Premium') }}"/>
                                                                            <x-form-input-error name="motorTradersExternal.passanger_liability_respect_of_motor_calculated_value"/>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="row">
                                                                    <div class="col-sm-4">
                                                                    <div class="form-floating mb-3">
                                                                        <label for="question">Special type vehicle : </label><br><br>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="Sum Insured" id="input2" class="form-control amount-field" wire:model.defer="motorTradersExternal.special_type_vehicle_coverage_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            id="motorTradersExternal.special_type_vehicle_coverage_value" placeholder="Sum Insured"/>
                                                                            <x-form-label for="special_type_vehicle_coverage_value" required value="{{ __('Sum Insured') }}"/>
                                                                            <x-form-input-error name="motorTradersExternal.special_type_vehicle_coverage_value"/>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="special_type_vehicle_calculated_value" type="text" id="input4" wire:model.defer="motorTradersExternal.special_type_vehicle_calculated_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            placeholder="Premium" class="form-control amount-field"/>
                                                                            <x-form-label for="special_type_vehicle_calculated_value" required value="{{ __('Premium') }}"/>
                                                                            <x-form-input-error name="motorTradersExternal.special_type_vehicle_calculated_value"/>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <hr>
                                                            <h4>Excesses</h4>
                                                            <div class="row">
                                                                <div class="row">
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                        <label for="question">Own Damage : </label><br><br>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input type="text" class="form-control" wire:model.defer="motorTradersExternal.own_damage_minimun_percent"
                                                                                id="motorTradersExternal.own_damage_minimun_percent" placeholder="Min %"/>
                                                                                <span style="color:red">Please Enter Number Only without % sign</span>
                                                                            <x-form-label for="motorTradersExternal.own_damage_minimun_percent" value="Min %" />
                                                                            <x-form-input-error name="motorTradersExternal.own_damage_minimun_percent"/>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input class="form-control" wire:model.defer="motorTradersExternal.own_damage_minimum_amount"
                                                                                id="motorTradersExternal.own_damage_minimum_amount" placeholder="Minimum Amount"
                                                                                @blur="$el.value = window.formatAmount($el.value)" />
                                                                            <x-form-label for="motorTradersExternal.own_damage_minimum_amount" value="Minimum Amount" />
                                                                            <x-form-input-error name="motorTradersExternal.own_damage_minimum_amount"/>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="row">
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                        <label for="question">Windscreen : </label><br><br>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input type="text" class="form-control" wire:model.defer="motorTradersExternal.windscreen_minimun_percent"
                                                                                id="motorTradersExternal.windscreen_minimun_percent" placeholder="Min %"/>
                                                                                <span style="color:red">Please Enter Number Only without % sign</span>
                                                                            <x-form-label for="motorTradersExternal.windscreen_minimun_percent" value="Min %" />
                                                                            <x-form-input-error name="motorTradersExternal.windscreen_minimun_percent"/>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input class="form-control" wire:model.defer="motorTradersExternal.windscreen_minimum_amount"
                                                                                id="motorTradersExternal.windscreen_minimum_amount" placeholder="minimum_amount"
                                                                                @blur="$el.value = window.formatAmount($el.value)" />
                                                                            <x-form-label for="motorTradersExternal.windscreen_minimum_amount" value="Minimum Amount" />
                                                                            <x-form-input-error name="motorTradersExternal.windscreen_minimum_amount"/>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <hr>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        @endif
                                        {{--End MOTOR TRADER EXTERNAL --}}

                                        {{-- Start MOTOR TRADER INTERNAL --}}
                                        @if($policyCoverage->coverage['s_CoverageCode'] == "MOTORTRADERSINTERNAL" || $policyCoverage->coverage_id == 16)
                                            <div class="row">
                                                <div class="col-sm-8">
                                                    <select title="Type of cover" wire:model.defer="motorTradersInternal.type_of_cover_internal_main_{{ $policyCoverage->id }}" aria-label="Select Type of cover" wire:change="MotorTradersInternal($event.target.value,{{$policyCoverage->id}})"  class="form-select form-select-solid type_of_cover_main">
                                                        <option value="">-Select-</option>
                                                        <option value="ComprehensiveMotorTradersInternal">Comprehensive</option>
                                                        <option value="TPMotorTradersInternal">Third party only</option>
                                                        <option value="TPFTMotorTradersInternal">Third party, fire and theft</option>
                                                    </select>
                                                </div>
                                                <div class="col-sm-4">
                                                    @php
                                                    $tradersInternalData = AlphaDirect\Models\MotorTradersInternal::where('policy_coverage_id',$policyCoverage->id)->first();
                                                    @endphp

                                                    @if(isset($tradersInternalData) && $tradersInternalData['type_of_cover'] == "ComprehensiveMotorTradersInternal")
                                                        <button class="btn btn-primary" value="ComprehensiveMotorTradersInternal" wire:click="MotorTradersInternal($event.target.value,{{$policyCoverage->id}})" >View</button>
                                                    @elseif(isset($tradersInternalData) && $tradersInternalData['type_of_cover'] == "TPMotorTradersInternal")
                                                        <button class="btn btn-primary" value="TPMotorTradersInternal" wire:click="MotorTradersInternal($event.target.value,{{$policyCoverage->id}})" >View</button>
                                                    @elseif(isset($tradersInternalData) && $tradersInternalData['type_of_cover'] == "TPFTMotorTradersInternal")
                                                        <button class="btn btn-primary" value="TPFTMotorTradersInternal" wire:click="MotorTradersInternal($event.target.value,{{$policyCoverage->id}})" >View</button>
                                                    @else
                                                            -
                                                    @endif
                                                </div>
                                            </div>
                                            <x-form-input-error name="motorTradersInternal.type_of_cover_main"/>
                                            <div class="row">
                                                <div class="col-sm-12">
                                                    <div class='card card-custom gutter-b'>
                                                        <div class="card-header">
                                                            <div class="card-title">
                                                                <h2 class="ComprehensiveMotorTradersInternal_section" @if(!$ComprehensiveMotorTradersInternal_section) style="display: none;" @endif>
                                                                Motor Traders Internal Comprehensive
                                                                </h2>
                                                                <h2 class="TPMotorTradersInternal_section" @if(!$TPMotorTradersInternal_section) style="display: none;" @endif>
                                                                Motor Traders Internal  TP
                                                                </h2>
                                                                <h2 class="TPFTMotorTradersInternal_section" @if(!$TPFTMotorTradersInternal_section) style="display: none;" @endif>
                                                                Motor Traders Internal  FTPFT
                                                                </h2>
                                                            </div>
                                                        </div>
                                                        <div class="card-body ComprehensiveMotorTradersInternal_section" @if(!$ComprehensiveMotorTradersInternal_section) style="display: none;" @endif>
                                                            <input type="hidden"  title="policy_coverage_id" wire:model.defer="motorTradersInternal.policy_coverage_id" class="form-control"
                                                                    id="motorTradersInternal.policy_coverage_id" placeholder="policy_coverage_id" />
                                                            <div class="row">
                                                                <div class="row">
                                                                    <div class="col-sm-4">
                                                                    <div class="form-floating mb-3">
                                                                        <label for="question">Loss or damage : </label><br><br>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="Sum Insured" id="input2" class="form-control amount-field" wire:model.defer="motorTradersInternal.loss_or_damage_coverage_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            id="motorTradersInternal.loss_or_damage_coverage_value" placeholder="Sum Insured"/>
                                                                            <x-form-label for="loss_or_damage_coverage_value" required value="{{ __('Sum Insured') }}"/>
                                                                            <x-form-input-error name="motorTradersInternal.loss_or_damage_coverage_value"/>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="loss_or_damage_calculated_value" type="text" id="input4" wire:model.defer="motorTradersInternal.loss_or_damage_calculated_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            placeholder="Premium" class="form-control amount-field"/>
                                                                            <x-form-label for="loss_or_damage_calculated_value" required value="{{ __('Premium') }}"/>
                                                                            <x-form-input-error name="motorTradersInternal.loss_or_damage_calculated_value"/>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="row">
                                                                    <div class="col-sm-4">
                                                                    <div class="form-floating mb-3">
                                                                        <label for="question">Third party liability : </label><br><br>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="Sum Insured" id="input2" class="form-control amount-field" wire:model.defer="motorTradersInternal.third_party_liability_coverage_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            id="motorTradersInternal.third_party_liability_coverage_value" placeholder="Sum Insured"/>
                                                                            <x-form-label for="third_party_liability_coverage_value" required value="{{ __('Sum Insured') }}"/>
                                                                            <x-form-input-error name="motorTradersInternal.third_party_liability_coverage_value"/>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="third_party_liability_calculated_value" type="text" id="input4" wire:model.defer="motorTradersInternal.third_party_liability_calculated_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            placeholder="Premium" class="form-control amount-field"/>
                                                                            <x-form-label for="third_party_liability_calculated_value" required value="{{ __('Premium') }}"/>
                                                                            <x-form-input-error name="motorTradersInternal.third_party_liability_calculated_value"/>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="row">
                                                                    <div class="col-sm-4">
                                                                    <div class="form-floating mb-3">
                                                                        <label for="question">Medical benefits : </label><br><br>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="Sum Insured" id="input2" class="form-control amount-field" wire:model.defer="motorTradersInternal.medical_benefits_coverage_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            id="motorTradersInternal.medical_benefits_coverage_value" placeholder="Sum Insured"/>
                                                                            <x-form-label for="medical_benefits_coverage_value" required value="{{ __('Sum Insured') }}"/>
                                                                            <x-form-input-error name="motorTradersInternal.medical_benefits_coverage_value"/>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="medical_benefits_calculated_value" type="text" id="input4" wire:model.defer="motorTradersInternal.medical_benefits_calculated_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            placeholder="Premium" class="form-control amount-field"/>
                                                                            <x-form-label for="medical_benefits_calculated_value" required value="{{ __('Premium') }}"/>
                                                                            <x-form-input-error name="motorTradersInternal.medical_benefits_calculated_value"/>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <hr>
                                                            <h4>Extensions and Clauses</h4><br>
                                                            <div class="row">
                                                                <div class="row">
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                        <label for="question">Vehicles lent or hired to Customers : </label><br><br>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="Sum Insured" id="input2" class="form-control amount-field" wire:model.defer="motorTradersInternal.vehicle_lent_hire_coverage_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            id="motorTradersInternal.vehicle_lent_hire_coverage_value" placeholder="Sum Insured"/>
                                                                            <x-form-label for="vehicle_lent_hire_coverage_value" required value="{{ __('Sum Insured') }}"/>
                                                                            <x-form-input-error name="motorTradersInternal.vehicle_lent_hire_coverage_value"/>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="vehicle_lent_hire_calculated_value" type="text" id="input4" wire:model.defer="motorTradersInternal.vehicle_lent_hire_calculated_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            placeholder="Premium" class="form-control amount-field"/>
                                                                            <x-form-label for="vehicle_lent_hire_calculated_value" required value="{{ __('Premium') }}"/>
                                                                            <x-form-input-error name="motorTradersInternal.vehicle_lent_hire_calculated_value"/>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="row">
                                                                    <div class="col-sm-4">
                                                                    <div class="form-floating mb-3">
                                                                        <label for="question">Social, domestic and pleasure use : </label><br><br>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="Sum Insured" id="input2" class="form-control amount-field" wire:model.defer="motorTradersInternal.social_domestic_pleasure_coverage_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            id="motorTradersInternal.social_domestic_pleasure_coverage_value" placeholder="Sum Insured"/>
                                                                            <x-form-label for="social_domestic_pleasure_coverage_value" required value="{{ __('Sum Insured') }}"/>
                                                                            <x-form-input-error name="motorTradersInternal.social_domestic_pleasure_coverage_value"/>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="social_domestic_pleasure_calculated_value" type="text" id="input4" wire:model.defer="motorTradersInternal.social_domestic_pleasure_calculated_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            placeholder="Premium" class="form-control amount-field"/>
                                                                            <x-form-label for="social_domestic_pleasure_calculated_value" required value="{{ __('Premium') }}"/>
                                                                            <x-form-input-error name="motorTradersInternal.social_domestic_pleasure_calculated_value"/>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="row">
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                        <label for="question">Unauthoried use by employees : </label><br><br>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="Sum Insured" id="input2" class="form-control amount-field" wire:model.defer="motorTradersInternal.unauthoried_use_coverage_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            id="motorTradersInternal.unauthoried_use_coverage_value" placeholder="Sum Insured"/>
                                                                            <x-form-label for="unauthoried_use_coverage_value" required value="{{ __('Sum Insured') }}"/>
                                                                            <x-form-input-error name="motorTradersInternal.unauthoried_use_coverage_value"/>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="unauthoried_use_calculated_value" type="text" id="input4" wire:model.defer="motorTradersInternal.unauthoried_use_calculated_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            placeholder="Premium" class="form-control amount-field"/>
                                                                            <x-form-label for="unauthoried_use_calculated_value" required value="{{ __('Premium') }}"/>
                                                                            <x-form-input-error name="motorTradersInternal.unauthoried_use_calculated_value"/>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="row">
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                        <label for="question">Windscreen : </label><br><br>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="Sum Insured" id="input2" class="form-control amount-field" wire:model.defer="motorTradersInternal.windscreen_coverage_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            id="motorTradersInternal.windscreen_coverage_value" placeholder="Sum Insured"/>
                                                                            <x-form-label for="windscreen_coverage_value" required value="{{ __('Sum Insured') }}"/>
                                                                            <x-form-input-error name="motorTradersInternal.windscreen_coverage_value"/>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="windscreen_calculated_value" type="text" id="input4" wire:model.defer="motorTradersInternal.windscreen_calculated_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            placeholder="Premium" class="form-control amount-field"/>
                                                                            <x-form-label for="windscreen_calculated_value" required value="{{ __('Premium') }}"/>
                                                                            <x-form-input-error name="motorTradersInternal.windscreen_calculated_value"/>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="row">
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                        <label for="question">Contigent liability : </label><br><br>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="Sum Insured" id="input2" class="form-control amount-field" wire:model.defer="motorTradersInternal.contigent_liability_coverage_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            id="motorTradersInternal.contigent_liability_coverage_value" placeholder="Sum Insured"/>
                                                                            <x-form-label for="contigent_liability_coverage_value" required value="{{ __('Sum Insured') }}"/>
                                                                            <x-form-input-error name="motorTradersInternal.contigent_liability_coverage_value"/>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="contigent_liability_calculated_value" type="text" id="input4" wire:model.defer="motorTradersInternal.contigent_liability_calculated_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            placeholder="Premium" class="form-control amount-field"/>
                                                                            <x-form-label for="contigent_liability_calculated_value" required value="{{ __('Premium') }}"/>
                                                                            <x-form-input-error name="motorTradersInternal.contigent_liability_calculated_value"/>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="row">
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                        <label for="question">Wreckage removal : </label><br><br>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="Sum Insured" id="input2" class="form-control amount-field" wire:model.defer="motorTradersInternal.wreckage_removal_coverage_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            id="motorTradersInternal.wreckage_removal_coverage_value" placeholder="Sum Insured"/>
                                                                            <x-form-label for="wreckage_removal_coverage_value" required value="{{ __('Sum Insured') }}"/>
                                                                            <x-form-input-error name="motorTradersInternal.wreckage_removal_coverage_value"/>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="wreckage_removal_calculated_value" type="text" id="input4" wire:model.defer="motorTradersInternal.wreckage_removal_calculated_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            placeholder="Premium" class="form-control amount-field"/>
                                                                            <x-form-label for="wreckage_removal_calculated_value" required value="{{ __('Premium') }}"/>
                                                                            <x-form-input-error name="motorTradersInternal.wreckage_removal_calculated_value"/>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="row">
                                                                    <div class="col-sm-4">
                                                                    <div class="form-floating mb-3">
                                                                        <label for="question">Loss of keys : </label><br><br>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="Sum Insured" id="input2" class="form-control amount-field" wire:model.defer="motorTradersInternal.loss_of_key_coverage_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            id="motorTradersInternal.loss_of_key_coverage_value" placeholder="Sum Insured"/>
                                                                            <x-form-label for="loss_of_key_coverage_value" required value="{{ __('Sum Insured') }}"/>
                                                                            <x-form-input-error name="motorTradersInternal.loss_of_key_coverage_value"/>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="loss_of_key_calculated_value" type="text" id="input4" wire:model.defer="motorTradersInternal.loss_of_key_calculated_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            placeholder="Premium" class="form-control amount-field"/>
                                                                            <x-form-label for="loss_of_key_calculated_value" required value="{{ __('Premium') }}"/>
                                                                            <x-form-input-error name="motorTradersInternal.loss_of_key_calculated_value"/>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="row">
                                                                    <div class="col-sm-4">
                                                                    <div class="form-floating mb-3">
                                                                        <label for="question">Loss of use of customer's vehicle : </label><br><br>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="Sum Insured" id="input2" class="form-control amount-field" wire:model.defer="motorTradersInternal.Loss_of_use_of_customer_coverage_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            id="motorTradersInternal.Loss_of_use_of_customer_coverage_value" placeholder="Sum Insured"/>
                                                                            <x-form-label for="Loss_of_use_of_customer_coverage_value" required value="{{ __('Sum Insured') }}"/>
                                                                            <x-form-input-error name="motorTradersInternal.Loss_of_use_of_customer_coverage_value"/>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="Loss_of_use_of_customer_calculated_value" type="text" id="input4" wire:model.defer="motorTradersInternal.Loss_of_use_of_customer_calculated_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            placeholder="Premium" class="form-control amount-field"/>
                                                                            <x-form-label for="Loss_of_use_of_customer_calculated_value" required value="{{ __('Premium') }}"/>
                                                                            <x-form-input-error name="motorTradersInternal.Loss_of_use_of_customer_calculated_value"/>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="row">
                                                                    <div class="col-sm-4">
                                                                    <div class="form-floating mb-3">
                                                                        <label for="question">Motor cycle, motor tricycle or quad bike : </label><br><br>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="Sum Insured" id="input2" class="form-control amount-field" wire:model.defer="motorTradersInternal.motor_cycle_motor_tricycle_coverage_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            id="motorTradersInternal.motor_cycle_motor_tricycle_coverage_value" placeholder="Sum Insured"/>
                                                                            <x-form-label for="motor_cycle_motor_tricycle_coverage_value" required value="{{ __('Sum Insured') }}"/>
                                                                            <x-form-input-error name="motorTradersInternal.motor_cycle_motor_tricycle_coverage_value"/>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="motor_cycle_motor_tricycle_calculated_value" type="text" id="input4" wire:model.defer="motorTradersInternal.motor_cycle_motor_tricycle_calculated_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            placeholder="Premium" class="form-control amount-field"/>
                                                                            <x-form-label for="motor_cycle_motor_tricycle_calculated_value" required value="{{ __('Premium') }}"/>
                                                                            <x-form-input-error name="motorTradersInternal.motor_cycle_motor_tricycle_calculated_value"/>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="row">
                                                                    <div class="col-sm-4">
                                                                    <div class="form-floating mb-3">
                                                                            Passanger liability in respect of motor
                                                                        <label for="question">cycles and motor tricycles : </label><br><br>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="Sum Insured" id="input2" class="form-control amount-field" wire:model.defer="motorTradersInternal.passanger_liability_respect_of_motor_coverage_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            id="motorTradersInternal.passanger_liability_respect_of_motor_coverage_value" placeholder="Sum Insured"/>
                                                                            <x-form-label for="passanger_liability_respect_of_motor_coverage_value" required value="{{ __('Sum Insured') }}"/>
                                                                            <x-form-input-error name="motorTradersInternal.passanger_liability_respect_of_motor_coverage_value"/>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="passanger_liability_respect_of_motor_calculated_value" type="text" id="input4" wire:model.defer="motorTradersInternal.passanger_liability_respect_of_motor_calculated_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            placeholder="Premium" class="form-control amount-field"/>
                                                                            <x-form-label for="passanger_liability_respect_of_motor_calculated_value" required value="{{ __('Premium') }}"/>
                                                                            <x-form-input-error name="motorTradersInternal.passanger_liability_respect_of_motor_calculated_value"/>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="row">
                                                                    <div class="col-sm-4">
                                                                    <div class="form-floating mb-3">
                                                                        <label for="question">Special type vehicle : </label><br><br>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="Sum Insured" id="input2" class="form-control amount-field" wire:model.defer="motorTradersInternal.special_type_vehicle_coverage_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            id="motorTradersInternal.special_type_vehicle_coverage_value" placeholder="Sum Insured"/>
                                                                            <x-form-label for="special_type_vehicle_coverage_value" required value="{{ __('Sum Insured') }}"/>
                                                                            <x-form-input-error name="motorTradersInternal.special_type_vehicle_coverage_value"/>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="special_type_vehicle_calculated_value" type="text" id="input4" wire:model.defer="motorTradersInternal.special_type_vehicle_calculated_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            placeholder="Premium" class="form-control amount-field"/>
                                                                            <x-form-label for="special_type_vehicle_calculated_value" required value="{{ __('Premium') }}"/>
                                                                            <x-form-input-error name="motorTradersInternal.special_type_vehicle_calculated_value"/>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <hr>
                                                            <h4>Excesses</h4>
                                                            <div class="row">
                                                                <div class="row">
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                        <label for="question">Own Damage : </label><br><br>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input type="text" class="form-control" wire:model.defer="motorTradersInternal.own_damage_minimun_percent"
                                                                                id="motorTradersInternal.own_damage_minimun_percent" placeholder="Min %"/>
                                                                                <span style="color:red">Please Enter Number Only without % sign</span>
                                                                            <x-form-label for="motorTradersInternal.own_damage_minimun_percent" value="Min %" />
                                                                            <x-form-input-error name="motorTradersInternal.own_damage_minimun_percent"/>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input class="form-control" wire:model.defer="motorTradersInternal.own_damage_minimum_amount"
                                                                                id="motorTradersInternal.own_damage_minimum_amount" placeholder="Minimum Amount"
                                                                                @blur="$el.value = window.formatAmount($el.value)" />
                                                                            <x-form-label for="motorTradersInternal.own_damage_minimum_amount" value="Minimum Amount" />
                                                                            <x-form-input-error name="motorTradersInternal.own_damage_minimum_amount"/>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="row">
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                        <label for="question">Windscreen : </label><br><br>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input type="text" class="form-control" wire:model.defer="motorTradersInternal.windscreen_minimun_percent"
                                                                                id="motorTradersInternal.windscreen_minimun_percent" placeholder="Min %"/>
                                                                                <span style="color:red">Please Enter Number Only without % sign</span>
                                                                            <x-form-label for="motorTradersInternal.windscreen_minimun_percent" value="Min %" />
                                                                            <x-form-input-error name="motorTradersInternal.windscreen_minimun_percent"/>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input class="form-control" wire:model.defer="motorTradersInternal.windscreen_minimum_amount"
                                                                                id="motorTradersInternal.windscreen_minimum_amount" placeholder="minimum_amount"
                                                                                @blur="$el.value = window.formatAmount($el.value)" />
                                                                            <x-form-label for="motorTradersInternal.windscreen_minimum_amount" value="Minimum Amount" />
                                                                            <x-form-input-error name="motorTradersInternal.windscreen_minimum_amount"/>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="card-body TPMotorTradersInternal_section" @if(!$TPMotorTradersInternal_section) style="display: none;" @endif>
                                                            <input type="hidden"  title="policy_coverage_id" wire:model.defer="motorTradersInternal.policy_coverage_id" class="form-control"
                                                                    id="motorTradersInternal.policy_coverage_id" placeholder="policy_coverage_id" />
                                                            <div class="row">
                                                                <div class="col-sm-4">
                                                                    <div class="form-floating mb-3">
                                                                    <label for="question">Third party liability : </label><br><br>
                                                                    </div>
                                                                </div>
                                                                <div class="col-sm-4">
                                                                    <div class="form-floating mb-3">
                                                                        <input title="Sum Insured" id="input2" class="form-control amount-field" wire:model.defer="motorTradersInternal.third_party_liability_coverage_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                        id="motorTradersInternal.third_party_liability_coverage_value" placeholder="Sum Insured"/>
                                                                        <x-form-label for="third_party_liability_coverage_value" required value="{{ __('Sum Insured') }}"/>
                                                                        <x-form-input-error name="motorTradersInternal.third_party_liability_coverage_value"/>
                                                                    </div>
                                                                </div>
                                                                <div class="col-sm-4">
                                                                    <div class="form-floating mb-3">
                                                                        <input title="third_party_liability_calculated_value" type="text" id="input4" wire:model.defer="motorTradersInternal.third_party_liability_calculated_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                        placeholder="Premium" class="form-control amount-field"/>
                                                                        <x-form-label for="third_party_liability_calculated_value" required value="{{ __('Premium') }}"/>
                                                                        <x-form-input-error name="motorTradersInternal.third_party_liability_calculated_value"/>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <hr>
                                                        </div>
                                                        <div class="card-body TPFTMotorTradersInternal_section" @if(!$TPFTMotorTradersInternal_section) style="display: none;" @endif>
                                                            <input type="hidden"  title="policy_coverage_id" wire:model.defer="motorTradersInternal.policy_coverage_id" class="form-control"
                                                                    id="motorTradersInternal.policy_coverage_id" placeholder="policy_coverage_id" />
                                                            <div class="row">
                                                                <div class="row">
                                                                    <div class="col-sm-4">
                                                                    <div class="form-floating mb-3">
                                                                        <label for="question">Loss or damage : </label><br><br>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="Sum Insured" id="input2" class="form-control amount-field" wire:model.defer="motorTradersInternal.loss_or_damage_coverage_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            id="motorTradersInternal.loss_or_damage_coverage_value" placeholder="Sum Insured"/>
                                                                            <x-form-label for="loss_or_damage_coverage_value" required value="{{ __('Sum Insured') }}"/>
                                                                            <x-form-input-error name="motorTradersInternal.loss_or_damage_coverage_value"/>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="loss_or_damage_calculated_value" type="text" id="input4" wire:model.defer="motorTradersInternal.loss_or_damage_calculated_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            placeholder="Premium" class="form-control amount-field"/>
                                                                            <x-form-label for="loss_or_damage_calculated_value" required value="{{ __('Premium') }}"/>
                                                                            <x-form-input-error name="motorTradersInternal.loss_or_damage_calculated_value"/>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="row">
                                                                    <div class="col-sm-4">
                                                                    <div class="form-floating mb-3">
                                                                        <label for="question">Third party liability : </label><br><br>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="Sum Insured" id="input2" class="form-control amount-field" wire:model.defer="motorTradersInternal.third_party_liability_coverage_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            id="motorTradersInternal.third_party_liability_coverage_value" placeholder="Sum Insured"/>
                                                                            <x-form-label for="third_party_liability_coverage_value" required value="{{ __('Sum Insured') }}"/>
                                                                            <x-form-input-error name="motorTradersInternal.third_party_liability_coverage_value"/>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="third_party_liability_calculated_value" type="text" id="input4" wire:model.defer="motorTradersInternal.third_party_liability_calculated_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            placeholder="Premium" class="form-control amount-field"/>
                                                                            <x-form-label for="third_party_liability_calculated_value" required value="{{ __('Premium') }}"/>
                                                                            <x-form-input-error name="motorTradersInternal.third_party_liability_calculated_value"/>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="row">
                                                                    <div class="col-sm-4">
                                                                    <div class="form-floating mb-3">
                                                                        <label for="question">Medical benefits : </label><br><br>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="Sum Insured" id="input2" class="form-control amount-field" wire:model.defer="motorTradersInternal.medical_benefits_coverage_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            id="motorTradersInternal.medical_benefits_coverage_value" placeholder="Sum Insured"/>
                                                                            <x-form-label for="medical_benefits_coverage_value" required value="{{ __('Sum Insured') }}"/>
                                                                            <x-form-input-error name="motorTradersInternal.medical_benefits_coverage_value"/>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="medical_benefits_calculated_value" type="text" id="input4" wire:model.defer="motorTradersInternal.medical_benefits_calculated_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            placeholder="Premium" class="form-control amount-field"/>
                                                                            <x-form-label for="medical_benefits_calculated_value" required value="{{ __('Premium') }}"/>
                                                                            <x-form-input-error name="motorTradersInternal.medical_benefits_calculated_value"/>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <hr>
                                                            <h4>Extensions and Clauses</h4><br>
                                                            <div class="row">
                                                                <div class="row">
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                        <label for="question">Vehicles lent or hired to Customers : </label><br><br>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="Sum Insured" id="input2" class="form-control amount-field" wire:model.defer="motorTradersInternal.vehicle_lent_hire_coverage_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            id="motorTradersInternal.vehicle_lent_hire_coverage_value" placeholder="Sum Insured"/>
                                                                            <x-form-label for="vehicle_lent_hire_coverage_value" required value="{{ __('Sum Insured') }}"/>
                                                                            <x-form-input-error name="motorTradersInternal.vehicle_lent_hire_coverage_value"/>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="vehicle_lent_hire_calculated_value" type="text" id="input4" wire:model.defer="motorTradersInternal.vehicle_lent_hire_calculated_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            placeholder="Premium" class="form-control amount-field"/>
                                                                            <x-form-label for="vehicle_lent_hire_calculated_value" required value="{{ __('Premium') }}"/>
                                                                            <x-form-input-error name="motorTradersInternal.vehicle_lent_hire_calculated_value"/>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="row">
                                                                    <div class="col-sm-4">
                                                                    <div class="form-floating mb-3">
                                                                        <label for="question">Social, domestic and pleasure use : </label><br><br>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="Sum Insured" id="input2" class="form-control amount-field" wire:model.defer="motorTradersInternal.social_domestic_pleasure_coverage_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            id="motorTradersInternal.social_domestic_pleasure_coverage_value" placeholder="Sum Insured"/>
                                                                            <x-form-label for="social_domestic_pleasure_coverage_value" required value="{{ __('Sum Insured') }}"/>
                                                                            <x-form-input-error name="motorTradersInternal.social_domestic_pleasure_coverage_value"/>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="social_domestic_pleasure_calculated_value" type="text" id="input4" wire:model.defer="motorTradersInternal.social_domestic_pleasure_calculated_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            placeholder="Premium" class="form-control amount-field"/>
                                                                            <x-form-label for="social_domestic_pleasure_calculated_value" required value="{{ __('Premium') }}"/>
                                                                            <x-form-input-error name="motorTradersInternal.social_domestic_pleasure_calculated_value"/>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="row">
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                        <label for="question">Unauthoried use by employees : </label><br><br>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="Sum Insured" id="input2" class="form-control amount-field" wire:model.defer="motorTradersInternal.unauthoried_use_coverage_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            id="motorTradersInternal.unauthoried_use_coverage_value" placeholder="Sum Insured"/>
                                                                            <x-form-label for="unauthoried_use_coverage_value" required value="{{ __('Sum Insured') }}"/>
                                                                            <x-form-input-error name="motorTradersInternal.unauthoried_use_coverage_value"/>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="unauthoried_use_calculated_value" type="text" id="input4" wire:model.defer="motorTradersInternal.unauthoried_use_calculated_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            placeholder="Premium" class="form-control amount-field"/>
                                                                            <x-form-label for="unauthoried_use_calculated_value" required value="{{ __('Premium') }}"/>
                                                                            <x-form-input-error name="motorTradersInternal.unauthoried_use_calculated_value"/>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="row">
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                        <label for="question">Windscreen : </label><br><br>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="Sum Insured" id="input2" class="form-control amount-field" wire:model.defer="motorTradersInternal.windscreen_coverage_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            id="motorTradersInternal.windscreen_coverage_value" placeholder="Sum Insured"/>
                                                                            <x-form-label for="windscreen_coverage_value" required value="{{ __('Sum Insured') }}"/>
                                                                            <x-form-input-error name="motorTradersInternal.windscreen_coverage_value"/>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="windscreen_calculated_value" type="text" id="input4" wire:model.defer="motorTradersInternal.windscreen_calculated_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            placeholder="Premium" class="form-control amount-field"/>
                                                                            <x-form-label for="windscreen_calculated_value" required value="{{ __('Premium') }}"/>
                                                                            <x-form-input-error name="motorTradersInternal.windscreen_calculated_value"/>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="row">
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                        <label for="question">Contigent liability : </label><br><br>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="Sum Insured" id="input2" class="form-control amount-field" wire:model.defer="motorTradersInternal.contigent_liability_coverage_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            id="motorTradersInternal.contigent_liability_coverage_value" placeholder="Sum Insured"/>
                                                                            <x-form-label for="contigent_liability_coverage_value" required value="{{ __('Sum Insured') }}"/>
                                                                            <x-form-input-error name="motorTradersInternal.contigent_liability_coverage_value"/>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="contigent_liability_calculated_value" type="text" id="input4" wire:model.defer="motorTradersInternal.contigent_liability_calculated_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            placeholder="Premium" class="form-control amount-field"/>
                                                                            <x-form-label for="contigent_liability_calculated_value" required value="{{ __('Premium') }}"/>
                                                                            <x-form-input-error name="motorTradersInternal.contigent_liability_calculated_value"/>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="row">
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                        <label for="question">Wreckage removal : </label><br><br>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="Sum Insured" id="input2" class="form-control amount-field" wire:model.defer="motorTradersInternal.wreckage_removal_coverage_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            id="motorTradersInternal.wreckage_removal_coverage_value" placeholder="Sum Insured"/>
                                                                            <x-form-label for="wreckage_removal_coverage_value" required value="{{ __('Sum Insured') }}"/>
                                                                            <x-form-input-error name="motorTradersInternal.wreckage_removal_coverage_value"/>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="wreckage_removal_calculated_value" type="text" id="input4" wire:model.defer="motorTradersInternal.wreckage_removal_calculated_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            placeholder="Premium" class="form-control amount-field"/>
                                                                            <x-form-label for="wreckage_removal_calculated_value" required value="{{ __('Premium') }}"/>
                                                                            <x-form-input-error name="motorTradersInternal.wreckage_removal_calculated_value"/>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="row">
                                                                    <div class="col-sm-4">
                                                                    <div class="form-floating mb-3">
                                                                        <label for="question">Loss of keys : </label><br><br>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="Sum Insured" id="input2" class="form-control amount-field" wire:model.defer="motorTradersInternal.loss_of_key_coverage_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            id="motorTradersInternal.loss_of_key_coverage_value" placeholder="Sum Insured"/>
                                                                            <x-form-label for="loss_of_key_coverage_value" required value="{{ __('Sum Insured') }}"/>
                                                                            <x-form-input-error name="motorTradersInternal.loss_of_key_coverage_value"/>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="loss_of_key_calculated_value" type="text" id="input4" wire:model.defer="motorTradersInternal.loss_of_key_calculated_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            placeholder="Premium" class="form-control amount-field"/>
                                                                            <x-form-label for="loss_of_key_calculated_value" required value="{{ __('Premium') }}"/>
                                                                            <x-form-input-error name="motorTradersInternal.loss_of_key_calculated_value"/>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="row">
                                                                    <div class="col-sm-4">
                                                                    <div class="form-floating mb-3">
                                                                        <label for="question">Loss of use of customer's vehicle : </label><br><br>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="Sum Insured" id="input2" class="form-control amount-field" wire:model.defer="motorTradersInternal.Loss_of_use_of_customer_coverage_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            id="motorTradersInternal.Loss_of_use_of_customer_coverage_value" placeholder="Sum Insured"/>
                                                                            <x-form-label for="Loss_of_use_of_customer_coverage_value" required value="{{ __('Sum Insured') }}"/>
                                                                            <x-form-input-error name="motorTradersInternal.Loss_of_use_of_customer_coverage_value"/>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="Loss_of_use_of_customer_calculated_value" type="text" id="input4" wire:model.defer="motorTradersInternal.Loss_of_use_of_customer_calculated_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            placeholder="Premium" class="form-control amount-field"/>
                                                                            <x-form-label for="Loss_of_use_of_customer_calculated_value" required value="{{ __('Premium') }}"/>
                                                                            <x-form-input-error name="motorTradersInternal.Loss_of_use_of_customer_calculated_value"/>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="row">
                                                                    <div class="col-sm-4">
                                                                    <div class="form-floating mb-3">
                                                                        <label for="question">Motor cycle, motor tricycle or quad bike : </label><br><br>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="Sum Insured" id="input2" class="form-control amount-field" wire:model.defer="motorTradersInternal.motor_cycle_motor_tricycle_coverage_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            id="motorTradersInternal.motor_cycle_motor_tricycle_coverage_value" placeholder="Sum Insured"/>
                                                                            <x-form-label for="motor_cycle_motor_tricycle_coverage_value" required value="{{ __('Sum Insured') }}"/>
                                                                            <x-form-input-error name="motorTradersInternal.motor_cycle_motor_tricycle_coverage_value"/>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="motor_cycle_motor_tricycle_calculated_value" type="text" id="input4" wire:model.defer="motorTradersInternal.motor_cycle_motor_tricycle_calculated_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            placeholder="Premium" class="form-control amount-field"/>
                                                                            <x-form-label for="motor_cycle_motor_tricycle_calculated_value" required value="{{ __('Premium') }}"/>
                                                                            <x-form-input-error name="motorTradersInternal.motor_cycle_motor_tricycle_calculated_value"/>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="row">
                                                                    <div class="col-sm-4">
                                                                    <div class="form-floating mb-3">
                                                                            Passanger liability in respect of motor
                                                                        <label for="question">cycles and motor tricycles : </label><br><br>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="Sum Insured" id="input2" class="form-control amount-field" wire:model.defer="motorTradersInternal.passanger_liability_respect_of_motor_coverage_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            id="motorTradersInternal.passanger_liability_respect_of_motor_coverage_value" placeholder="Sum Insured"/>
                                                                            <x-form-label for="passanger_liability_respect_of_motor_coverage_value" required value="{{ __('Sum Insured') }}"/>
                                                                            <x-form-input-error name="motorTradersInternal.passanger_liability_respect_of_motor_coverage_value"/>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="passanger_liability_respect_of_motor_calculated_value" type="text" id="input4" wire:model.defer="motorTradersInternal.passanger_liability_respect_of_motor_calculated_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            placeholder="Premium" class="form-control amount-field"/>
                                                                            <x-form-label for="passanger_liability_respect_of_motor_calculated_value" required value="{{ __('Premium') }}"/>
                                                                            <x-form-input-error name="motorTradersInternal.passanger_liability_respect_of_motor_calculated_value"/>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="row">
                                                                    <div class="col-sm-4">
                                                                    <div class="form-floating mb-3">
                                                                        <label for="question">Special type vehicle : </label><br><br>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="Sum Insured" id="input2" class="form-control amount-field" wire:model.defer="motorTradersInternal.special_type_vehicle_coverage_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            id="motorTradersInternal.special_type_vehicle_coverage_value" placeholder="Sum Insured"/>
                                                                            <x-form-label for="special_type_vehicle_coverage_value" required value="{{ __('Sum Insured') }}"/>
                                                                            <x-form-input-error name="motorTradersInternal.special_type_vehicle_coverage_value"/>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input title="special_type_vehicle_calculated_value" type="text" id="input4" wire:model.defer="motorTradersInternal.special_type_vehicle_calculated_value" @blur="$el.value = window.formatAmount($el.value)"
                                                                            placeholder="Premium" class="form-control amount-field"/>
                                                                            <x-form-label for="special_type_vehicle_calculated_value" required value="{{ __('Premium') }}"/>
                                                                            <x-form-input-error name="motorTradersInternal.special_type_vehicle_calculated_value"/>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <hr>
                                                            <h4>Excesses</h4>
                                                            <div class="row">
                                                                <div class="row">
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                        <label for="question">Own Damage : </label><br><br>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input type="text" class="form-control" wire:model.defer="motorTradersInternal.own_damage_minimun_percent"
                                                                                id="motorTradersInternal.own_damage_minimun_percent" placeholder="Min %"/>
                                                                                <span style="color:red">Please Enter Number Only without % sign</span>
                                                                            <x-form-label for="motorTradersInternal.own_damage_minimun_percent" value="Min %" />
                                                                            <x-form-input-error name="motorTradersInternal.own_damage_minimun_percent"/>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input class="form-control" wire:model.defer="motorTradersInternal.own_damage_minimum_amount"
                                                                                id="motorTradersInternal.own_damage_minimum_amount" placeholder="Minimum Amount"
                                                                                @blur="$el.value = window.formatAmount($el.value)" />
                                                                            <x-form-label for="motorTradersInternal.own_damage_minimum_amount" value="Minimum Amount" />
                                                                            <x-form-input-error name="motorTradersInternal.own_damage_minimum_amount"/>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="row">
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                        <label for="question">Windscreen : </label><br><br>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input type="text" class="form-control" wire:model.defer="motorTradersInternal.windscreen_minimun_percent"
                                                                                id="motorTradersInternal.windscreen_minimun_percent" placeholder="Min %"/>
                                                                                <span style="color:red">Please Enter Number Only without % sign</span>
                                                                            <x-form-label for="motorTradersInternal.windscreen_minimun_percent" value="Min %" />
                                                                            <x-form-input-error name="motorTradersInternal.windscreen_minimun_percent"/>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4">
                                                                        <div class="form-floating mb-3">
                                                                            <input class="form-control" wire:model.defer="motorTradersInternal.windscreen_minimum_amount"
                                                                                id="motorTradersInternal.windscreen_minimum_amount" placeholder="minimum_amount"
                                                                                @blur="$el.value = window.formatAmount($el.value)" />
                                                                            <x-form-label for="motorTradersInternal.windscreen_minimum_amount" value="Minimum Amount" />
                                                                            <x-form-input-error name="motorTradersInternal.windscreen_minimum_amount"/>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        @endif
                                        {{-- End MOTOR TRADER INTERNAL --}}

                                        {{-- For All Section Without Motor Section --}}
                                        @if ($policyCoverage->coverage['s_CoverageCode'] != "PERSONALMOTOR")

                                             @php
                                                $seenCombinations = [];
                                            @endphp
                                            @foreach(($coverage['subCoverage']) as $extr => $subCoverage)
                                                @php
                                                    $combination = ($subCoverage['s_CoverageCode'] ?? '') . '_' . ($subCoverage['s_ParentCoverageCode'] ?? '') . '_' . ($subCoverage['s_CoverageGroupName'] ?? '');
                                                    
                                                @endphp
                                                @if($header!=$subCoverage['s_CoverageGroupName'])
                                                    <div class="row">
                                                        <h4>{{ $subCoverage['s_CoverageGroupName'] }}</h4>
                                                        <br><hr><br>
                                                    </div>
                                                    @if($policyCoverage->coverage['s_CoverageCode'] == "GOODSINTRANSIT")
                                                        <div class="row">
                                                            <div class="row">
                                                                <div class="col-4"><h5>All property usual to the Insured's business being: </h5>
                                                                    <h6>(including ropes, tarpaulins and packing materials in connection with the transit)</h6></div>
                                                                <div class="col-6"><input type="text" wire:model.defer="property_business_being" value="" placeholder="Free Text" class="form-control"></div>
                                                            </div>
                                                            <br><hr><br>
                                                        </div>
                                                    @endif
                                                @endif
                                                @if($subCoverage['s_SubCoverageMainName'] == "Heading")
                                                    <div class="row">
                                                        <b>{{ $subCoverage['s_CoverageName'] }}</b>
                                                        <br><br>
                                                    </div>
                                                @endif

                                                @php
                                                    $header = $subCoverage['s_CoverageGroupName'];
                                                    $preFixModel = 'policyCoverageDetail'.'.'.$policyCoverage->id.'.'.$subCoverage->id.'.';
                                                    //dd($this->selectedDataDropdownValue);
                                                    // dd($preFixModel,$this->policyCoverageDetail,$subCoverage->id);
                                                    $tbValidoptions = AlphaDirect\Models\TbValidOptions::where('s_OptionType','CVG_LIMIT')->where('n_SourceOneFK',$subCoverage->id)->pluck('n_SourceTwoFK');
                                                    $tbCvgpclimits = AlphaDirect\Models\TbCvgpcLimits::whereIn('n_PCLimitId_PK',$tbValidoptions)->get(['n_PCLimitId_PK','s_LimitTypeCode','s_LimitScreenName']);
                                                    $s_LimitTypeCode = $tbCvgpclimits[0]['s_LimitTypeCode'] ?? 0;
                                                    $s_LimitScreenName = $tbCvgpclimits->pluck('s_LimitScreenName','n_PCLimitId_PK')->toArray() ?? 0;
                                                    $screenName = null;
                                                    foreach ($tbCvgpclimits as $screen_name){
                                                        $screenName = $screen_name->s_LimitScreenName;
                                                    }

                                                    $vehicleValue = '';
                                                    $selectedVehicleValue = '';

                                                    if (isset($selectedVehicleData) && isset($subCoverage['s_ScreenName'])){
                                                        if ($subCoverage['s_ScreenName'] == 'Vehicle Make' || $subCoverage['s_ScreenName'] == 'Make') {

                                                            $vehicleValue = 'selectedVehicleData'.'.'.$policyCoverage->id.'.'.$subCoverage->id.'.make';
                                                            $selectedVehicleValue = isset($selectedVehicleData[$policyCoverage->id][$subCoverage->id]['make']) ? $selectedVehicleData[$policyCoverage->id][$subCoverage->id]['make'] : null;

                                                        } elseif ($subCoverage['s_ScreenName'] == 'Model') {
                                                            $vehicleValue = 'selectedVehicleData'.'.'.$policyCoverage->id.'.'.$subCoverage->id.'.model';
                                                            $selectedVehicleValue = isset($selectedVehicleData[$policyCoverage->id][$subCoverage->id]['model']) ? $selectedVehicleData[$policyCoverage->id][$subCoverage->id]['model'] : null;
                                                        }  elseif ($subCoverage['s_ScreenName'] == 'Engine Number' || $subCoverage['s_ScreenName'] == 'Engine number') {
                                                            $vehicleValue = 'selectedVehicleData'.'.'.$policyCoverage->id.'.'.$subCoverage->id.'.engineNo';
                                                            $selectedVehicleValue = isset($selectedVehicleData[$policyCoverage->id][$subCoverage->id]['engineNo']) ? $selectedVehicleData[$policyCoverage->id][$subCoverage->id]['engineNo'] : null;;
                                                        }  elseif ($subCoverage['s_ScreenName'] == 'Chassis Number' || $subCoverage['s_ScreenName'] == 'Chassis Number ' || $subCoverage['s_ScreenName'] == 'Chassis number') {
                                                            $vehicleValue = 'selectedVehicleData'.'.'.$policyCoverage->id.'.'.$subCoverage->id.'.chassisNo';
                                                            $selectedVehicleValue = isset($selectedVehicleData[$policyCoverage->id][$subCoverage->id]['chassisNo']) ? $selectedVehicleData[$policyCoverage->id][$subCoverage->id]['chassisNo'] : null;;
                                                        }  elseif ($subCoverage['s_ScreenName'] == 'Registration number') {
                                                            $vehicleValue = 'selectedVehicleData'.'.'.$policyCoverage->id.'.'.$subCoverage->id.'.vehiclePlate';
                                                            $selectedVehicleValue = isset($selectedVehicleData[$policyCoverage->id][$subCoverage->id]['vehiclePlate']) ? $selectedVehicleData[$policyCoverage->id][$subCoverage->id]['vehiclePlate'] : null;;
                                                        }

                                                    }
                                                @endphp



                                                @if($subCoverage['s_SubCoverageMainName'] != "Heading")
                                                <div class="row copyExtrafiled">
                                                    <div class="col-sm-4">
                                                        <div class="form-floating mb-3">
                                                            <div class="col-sm-6">
                                                                    @if($s_LimitTypeCode == 'DROPDOWN')
                                                                        @if ( ($subCoverage['s_ScreenName'] == 'Vehicle Make' || $subCoverage['s_ScreenName'] == 'Make') &&
                                                                        $policyCoverage->coverage['s_CoverageCode'] == "PERSONALMOTOR")

                                                                        <div class="form-floating mb-3">
                                                                            <x-select-search id="selectedVehicleData.{{$policyCoverage->id}}.{{$subCoverage['id']}}.make"
                                                                                wire:model.lazy="selectedVehicleData.{{$policyCoverage->id}}.{{$subCoverage['id']}}.make" wire:change="handleVehicleMakeChange($event.target.value,{{ $policyCoverage->id}},{{$subCoverage['id'] }})"
                                                                                aria-label="Select Vechicle Make"
                                                                                value="{{$selectedVehicleValue}}"
                                                                                {{-- listner="is_imported" --}}
                                                                                :options="$this->getVechicleMake()"
                                                                            />
                                                                            <x-form-label for="make" value="Select Make" />
                                                                            <x-form-input-error name="selectedVehicleData.{{$policyCoverage->id}}.{{$subCoverage['id']}}.make"/>
                                                                        </div>
                                                                        @else
                                                                            @if ($policyCoverage->coverage['s_CoverageCode'] == "GOODSINTRANSIT")
                                                                                <div class="form-floating mb-3">
                                                                                    <select class="form-control" wire:model.defer="{{ ($preFixModel).'limit_id' }}" id="{{ ($preFixModel).'limit_id' }}" aria-label="Select Screen Name">
                                                                                        <option value="">- Select -</option>
                                                                                        @foreach ($tbCvgpclimits as $data)
                                                                                        <option value="{{ $data->n_PCLimitId_PK ?? '' }}">
                                                                                        {{ $data->s_LimitScreenName ?? '' }}
                                                                                        </option>
                                                                                        @endforeach
                                                                                    </select>
                                                                                    <x-form-label for="{{ $screenName ?? '' }}" value="{{ $subCoverage['s_ScreenName'] ?? '' }}" />
                                                                                    <x-form-input-error name="{{ $screenName ?? '' }}"/>
                                                                                </div>
                                                                            @else
                                                                                <div class="form-floating mb-3">
                                                                                    <select class="form-control" wire:model.defer="{{ ($preFixModel).'limit_id' }}" id="{{ ($preFixModel).'limit_id' }}" aria-label="Select Screen Name" wire:change="handleDropdownChange($event.target.value,{{ $policyCoverage->coverage_id }})">
                                                                                        <option value="">- Select -</option>
                                                                                        @foreach ($tbCvgpclimits as $data)
                                                                                        <option value="{{ $data->n_PCLimitId_PK ?? '' }}">
                                                                                        {{ $data->s_LimitScreenName ?? '' }}
                                                                                        </option>
                                                                                        @endforeach
                                                                                    </select>
                                                                                    <x-form-label for="{{ $screenName ?? '' }}" value="{{ $subCoverage['s_ScreenName'] ?? '' }}" />
                                                                                    <x-form-input-error name="{{ $screenName ?? '' }}"/>
                                                                                </div>
                                                                            @endif
                                                                        @endif
                                                                @elseif ($policyCoverage->coverage['s_CoverageCode'] == "PERSONALMOTOR")
                                                                        <div class="form-floating mb-3">
                                                                            <select class="form-control" wire:model="selectedVehicleData.{{$policyCoverage->id}}.{{$subCoverage['id']}}.model" id="selectedVehicleData.{{$policyCoverage->id}}.{{$subCoverage['id']}}.model" aria-label="Select Screen Name">
                                                                                <option value="">- Select -</option>
                                                                                    @isset($vehicleModels[$policyCoverage->id])
                                                                                        @foreach ($vehicleModels[$policyCoverage->id] as $vehicle)
                                                                                            <option value="{{ $vehicle['id'] ?? '' }}" @if($selectedVehicleValue == $vehicle['id']) selected @endif>
                                                                                            {{ $vehicle['name'] ?? '' }}
                                                                                            </option>
                                                                                        @endforeach
                                                                                    @endisset
                                                                            </select>
                                                                            <x-form-label for="model" value="Select Model" />
                                                                            <x-form-input-error name="selectedVehicleData.{{$policyCoverage->id}}.model"/>
                                                                        </div>
                                                                @elseif ($s_LimitTypeCode == 'RADIO')
                                                                    <div class="form-floating mb-3">
                                                                        <label for="question 1">{{ $subCoverage['s_ScreenName'] }}</label><br><br>
                                                                            @foreach ($tbCvgpclimits as $radio)
                                                                            <div class="form-check form-check-inline" style="margin-left: 12px">
                                                                                <input class="form-check-input" type="radio" id="{{ ($preFixModel).'limit_id' }}" name="{{ ($preFixModel).'limit_id' }}" value="{{ $radio->n_PCLimitId_PK ?? '' }}" wire:model.defer="{{ ($preFixModel).'limit_id' }}">
                                                                                <label class="" for="inlineRadio1">{{ $radio->s_LimitScreenName }}</label>
                                                                            </div>
                                                                            @endforeach
                                                                    </div>
                                                                @elseif ($s_LimitTypeCode == 'NUMBER')
                                                                    <div class="form-floating mb-3">
                                                                        <input title="{{ $subCoverage['s_ScreenName'] }}" class="form-control amount-field" wire:model.defer="{{ ($preFixModel).'coverage_value' }}"
                                                                            id="{{ ($preFixModel).'coverage_value' }}" placeholder="{{ $subCoverage['s_ScreenName'] }}"
                                                                            @blur="$el.value = window.formatAmount($el.value)" />
                                                                            <x-form-label for="{{ ($preFixModel).'coverage_value' }}"   value="{{ $subCoverage['s_ScreenName']  }}" />
                                                                            <x-form-input-error name="{{ ($preFixModel).'coverage_value' }}"/>
                                                                    </div>
                                                                @elseif ($s_LimitTypeCode == 'NOEDIT')
                                                                    @if ($policyCoverage->coverage['s_CoverageCode'] == "PERSONALMOTOR")

                                                                        @if(($subCoverage['s_ScreenName'] !='Tracking device') && ($subCoverage['s_ScreenName'] !='Security features'))
                                                                        <div class="form-floating mb-3">
                                                                            <input title="{{ $subCoverage['s_ScreenName'] }}" type="text"  class="form-control" wire:model="{{$vehicleValue}}"
                                                                            id="{{$vehicleValue}}" placeholder="{{ $subCoverage['s_ScreenName'] }}" value="{{$selectedVehicleValue}}">
                                                                            <x-form-label for="{{ ($preFixModel).'coverage_value_string' }}" value="{{ $subCoverage['s_ScreenName'] }}" />
                                                                            <x-form-input-error name="{{$vehicleValue}}" />
                                                                        </div>
                                                                        @elseif(($subCoverage['s_ScreenName'] =='Tracking device') || ($subCoverage['s_ScreenName'] =='Security features'))
                                                                        <div class="form-floating mb-3">
                                                                            <x-form-text-area title="{{ $subCoverage['s_ScreenName'] }}" class="form-control" wire:model.defer="{{ ($preFixModel).'coverage_value_string' }}"
                                                                            id="{{ ($preFixModel).'coverage_value_string' }}" placeholder="{{ $subCoverage['s_ScreenName'] }}"/>
                                                                            <x-form-label for="{{ ($preFixModel).'coverage_value_string' }}" value="{{ $subCoverage['s_ScreenName'] }}" />
                                                                            <x-form-input-error name="{{ ($preFixModel).'coverage_value_string' }}"/>
                                                                        </div>
                                                                        @endif

                                                                    @else
                                                                        <div class="form-floating mb-3">
                                                                            <x-form-text-area title="{{ $subCoverage['s_ScreenName'] }}" class="form-control" wire:model.defer="{{ ($preFixModel).'coverage_value_string' }}"
                                                                            id="{{ ($preFixModel).'coverage_value_string' }}" placeholder="{{ $subCoverage['s_ScreenName'] }}"/>
                                                                            <x-form-label for="{{ ($preFixModel).'coverage_value_string' }}" value="{{ $subCoverage['s_ScreenName'] }}" />
                                                                            <x-form-input-error name="{{ ($preFixModel).'coverage_value_string' }}"/>
                                                                        </div>
                                                                    @endif
                                                                @else
                                                                    <div class="form-floating mb-3">
                                                                        <x-form-text-area class="form-control" wire:model.defer="{{ ($preFixModel).'coverage_value_string' }}"
                                                                        id="{{ ($preFixModel).'coverage_value_string' }}" placeholder="{{ $subCoverage['s_ScreenName'] }}"/>
                                                                        <x-form-label for="{{ ($preFixModel).'coverage_value_string' }}" value="{{ $subCoverage['s_ScreenName'] }}" />
                                                                        <x-form-input-error name="{{ ($preFixModel).'coverage_value_string' }}"/>
                                                                    </div>
                                                                @endif

                                                                <div class="col-sm-12" style="margin-left: 104%;margin-top: -24%;">
                                                                    <div class="form-floating mb-3">
                                                                        @if($subCoverage['s_CoverageCode']=='RENT')
                                                                            <select class='form-select form-select-solid' wire:model.defer="{{ ($preFixModel).'ratefactor_type' }}" aria-label="Select No Of Month">
                                                                                <option value="">- Select -</option>
                                                                                <option value="1">1</option>
                                                                                <option value="2">2</option>
                                                                                <option value="3">3</option>
                                                                                <option value="4">4</option>
                                                                                <option value="5">5</option>
                                                                                <option value="6">6</option>
                                                                                <option value="7">7</option>
                                                                                <option value="8">8</option>
                                                                                <option value="9">9</option>
                                                                                <option value="10">10</option>
                                                                                <option value="11">11</option>
                                                                                <option value="12">12</option>
                                                                            </select>
                                                                            <x-form-label for="{{ ($preFixModel).'ratefactor_type' }}" value="{{ __('Select No Of Month') }}"/>

                                                                            <x-form-input-error name="{{ ($preFixModel).'ratefactor_type' }}"/>
                                                                        @elseif($policyCoverage->coverage['s_CoverageCode']=='ELECTRONICEQUIPMENT' )
                                                                        <div class="form-floating mb-3">
                                                                            <input type="text" wire:model.defer="{{ ($preFixModel).'ratefactor_value' }}" value="{{ $subCoverage['s_ScreenName'] ?? '' }}" placeholder="Free Text" class="form-control">
                                                                            <x-form-label for="{{ ($preFixModel).'ratefactor_value' }}" value="Free Text" />
                                                                            <x-form-input-error name="{{ ($preFixModel).'ratefactor_value' }}"/>
                                                                        </div>
                                                                        @elseif($subCoverage['s_CoverageCode']=='SUMMARY OF VEHICLES FOR' )
                                                                        <div class="form-floating mb-3">
                                                                            <input type="text" wire:model.defer="{{ ($preFixModel).'ratefactor_value' }}" value="{{ $subCoverage['s_ScreenName'] ?? '' }}" placeholder="Free Text" class="form-control">
                                                                            <x-form-label for="{{ ($preFixModel).'ratefactor_value' }}" value="Free Text" />
                                                                            <x-form-input-error name="{{ ($preFixModel).'ratefactor_value' }}"/>
                                                                        </div>
                                                                        @elseif($subCoverage['s_CoverageCode']=='MONEYCAPITALSUM' )
                                                                            <input type="text" wire:model.defer="{{ ($preFixModel).'ratefactor_value' }}"  value="{{ $subCoverage['s_ScreenName'] ?? '' }}" placeholder="No of Employees" class="form-control">
                                                                            <x-form-label for="{{ ($preFixModel).'ratefactor_value' }}" value="No of Employees" />
                                                                            <x-form-input-error name="{{ ($preFixModel).'ratefactor_value' }}"/>
                                                                        @elseif($subCoverage['s_CoverageCode']=='PUB_LEGALDEFENCE' || $subCoverage['s_CoverageCode ']=='PUB_WRONGARREST')

                                                                            <input type="text" wire:model.defer="{{ ($preFixModel).'ratefactor_value' }}" value="{{ $subCoverage['s_ScreenName'] ?? '' }}" placeholder="No of Persons" class="form-control">
                                                                            <x-form-label for="{{ ($preFixModel).'ratefactor_value' }}" value="No of Persons" />
                                                                            <x-form-input-error name="{{ ($preFixModel).'ratefactor_value' }}"/>
                                                                        @elseif($subCoverage['s_CoverageCode']=='STOCK')
                                                                            <select class='form-select form-select-solid' wire:model.defer="{{ ($preFixModel).'ratefactor_type' }}" aria-label="Select Decl M/Q/A">
                                                                                <option value="">- Select -</option>
                                                                                <option value="Monthly">Monthly</option>
                                                                                <option value="Quarterly">Quarterly</option>
                                                                                <option value="Annually">Annually</option>
                                                                            </select>
                                                                            <x-form-label for="{{ ($preFixModel).'ratefactor_type' }}" value="{{ __('Select Decl M/Q/A') }}"/>
                                                                            <x-form-input-error name="{{ ($preFixModel).'ratefactor_type' }}"/>
                                                                        @elseif($subCoverage['s_CoverageCode']=='BUSI_WAGES')
                                                                            <input type="text" wire:model.defer="{{ ($preFixModel).'ratefactor_value' }}" value="{{ $subCoverage['s_ScreenName'] ?? '' }}"
                                                                            placeholder="No of Weeks" id="InsuredValueCoveredRate_" class="form-control">
                                                                            <x-form-label for="{{ ($preFixModel).'ratefactor_value' }}" value="No of Weeks" />
                                                                            <x-form-input-error name="{{ ($preFixModel).'ratefactor_value' }}"/>
                                                                        @elseif($subCoverage['s_CoverageCode']=='MONEYSEASONALINC1' || $subCoverage['s_CoverageCode']=='MONEYSEASONALINC2')
                                                                            <input name="CustomStat1_Date_To_" type="text" wire:model.defer="{{ ($preFixModel).'ratefactor_value' }}" class='form-control datepicker maskdate'>
                                                                            <x-form-label for="{{ ($preFixModel).'ratefactor_type' }}" value=" To" style="margin-top: 70px;" />
                                                                            <x-form-input-error name="{{ ($preFixModel).'ratefactor_type' }}"/>
                                                                        @elseif($subCoverage['s_CoverageCode']=='ENTER SUM INSURED' && $subCoverage['s_ParentCoverageCode']=='THEFT')
                                                                            <select wire:model.defer="{{ ($preFixModel).'ratefactor_type' }}" aria-label="Select Basis" class="form-select form-select-solid">
                                                                                <option value="">-Select-</option>
                                                                                <option value="Full Value">Full Value</option>
                                                                                <option value="First Loss">First Loss</option>
                                                                            </select>
                                                                            <x-form-label for="{{ ($preFixModel).'ratefactor_type' }}" value="{{ __('Select Basis Of Cover') }}" />
                                                                            <x-form-input-error name="{{ ($preFixModel).'ratefactor_type' }}"/>

                                                                        @elseif(($subCoverage['s_CoverageCode']=='SUM INSURED' && $subCoverage['s_ParentCoverageCode']=='HOUSEOWNER-BUILDINGS') || ($subCoverage['s_CoverageCode']=='SUM INSURED' && $subCoverage['s_ParentCoverageCode']=='HOUSEHOLDERS')
                                                                        || ($subCoverage['s_CoverageCode']=='SUM INSURED' && $subCoverage['s_ParentCoverageCode']=='HOUSEOWNERS'))
                                                                            <input type="number" step="0.01" wire:model.defer="{{ ($preFixModel).'ratefactor_value' }}" placeholder="Sum Insured" class="form-control">
                                                                            <x-form-label for="{{ ($preFixModel).'ratefactor_value' }}" value="Free Text" />
                                                                            <x-form-input-error name="{{ ($preFixModel).'ratefactor_value' }}"/>
                                                                        @elseif($subCoverage['s_ParentCoverageCode']=='PERSONALALLRISKS' || $subCoverage['s_ParentCoverageCode']=='COMPUTEREQUIPMENT')
                                                                        <input type="number" step="0.01" wire:model.defer="{{ ($preFixModel).'ratefactor_value' }}" placeholder="Sum Insured" class="form-control">
                                                                        <x-form-label for="{{ ($preFixModel).'ratefactor_value' }}" value="Free Text" />
                                                                        <x-form-input-error name="{{ ($preFixModel).'ratefactor_value' }}"/>
                                                                        @elseif($subCoverage['s_ParentCoverageCode']=='HOUSEHOLDERS-CONTENTS' || $subCoverage['s_ParentCoverageCode']=='BUSINESSALLRISKS'
                                                                        || ($subCoverage['s_ScreenName']=='Employers Liablity (Common Law Liability)' && $subCoverage['s_ParentCoverageCode']=='WORKERSCOMPENSATION')
                                                                        && $subCoverage['s_CoverageGroupName']=='Description of cover')
                                                                        <input type="number" step="0.01" wire:model.defer="{{ ($preFixModel).'ratefactor_value' }}"  placeholder="Sum Insured" class="form-control">
                                                                        <x-form-label for="{{ ($preFixModel).'ratefactor_value' }}" value="Free Text" />
                                                                        <x-form-input-error name="{{ ($preFixModel).'ratefactor_value' }}"/>
                                                                        @elseif(($subCoverage['s_CoverageCode']=='FREE TEXT' && $subCoverage['s_ParentCoverageCode']=='STATEDBENEFITS') ||
                                                                                ($subCoverage['s_ScreenName']=='Free text' && $subCoverage['s_ParentCoverageCode']=='WORKERSCOMPENSATION'))
                                                                            <div class="form-check form-check-inline">
                                                                                <input class="form-check-input" style="margin-left:2px" type="checkbox" id="{{ ($preFixModel).'ratefactor_value_check' }}" name="{{ ($preFixModel).'ratefactor_value_check' }}" value="All Employees" wire:model.defer="{{ ($preFixModel).'ratefactor_value_check' }}">
                                                                                <label class="" for="" style="margin-left: 12px">All Employees</label>
                                                                                <p></p>OR
                                                                                <input type="number" wire:model.defer="{{ ($preFixModel).'ratefactor_value' }}" value="{{ $subCoverage['s_ScreenName'] ?? '' }}"
                                                                                placeholder="No of Employees" class="form-control">
                                                                                <p></p>
                                                                            </div>
                                                                                @if($subCoverage['s_ParentCoverageCode']=='STATEDBENEFITS' || $subCoverage['s_ParentCoverageCode']=='WORKERSCOMPENSATION')
                                                                                    <div class="form-check form-check-inline">
                                                                                        <select wire:model.defer="{{ ($preFixModel).'ratefactor_type' }}" aria-label="Select " class="form-select form-select-solid">
                                                                                            <option value="">Select Individual Cover</option>
                                                                                            <option value="Yes">Yes</option>
                                                                                            <option value="No">No</option>
                                                                                        </select>
                                                                                        <x-form-input-error name="{{ ($preFixModel).'ratefactor_type' }}"/>
                                                                                    </div>
                                                                                    <div class="form-check form-check-inline">
                                                                                        <p></p>
                                                                                        <input type="text" wire:model.defer="{{ ($preFixModel).'ratefactor_AnnualWages' }}" @blur="$el.value = window.formatAmount($el.value)" value="{{ $subCoverage['s_ScreenName'] ?? '' }}" placeholder="AnnualWages" class="form-control">
                                                                                        <x-form-label for="{{ ($preFixModel).'ratefactor_AnnualWages' }}" value="AnnualWages" />
                                                                                        <x-form-input-error name="{{ ($preFixModel).'ratefactor_AnnualWages' }}"/>
                                                                                    </div>
                                                                                    <div class="form-check form-check-inline">
                                                                                        <p></p>
                                                                                        <input type="text" wire:model.defer="{{ ($preFixModel).'ratefactor_deposit_min_pre' }}" @blur="$el.value = window.formatAmount($el.value)" value="{{ $subCoverage['s_ScreenName'] ?? '' }}" placeholder="Deposit and Min Prem" class="form-control">
                                                                                        <x-form-label for="{{ ($preFixModel).'ratefactor_deposit_min_pre' }}" value="Deposit and Min Prem" />
                                                                                        <x-form-input-error name="{{ ($preFixModel).'ratefactor_deposit_min_pre' }}"/>
                                                                                        <p></p>
                                                                    </div><br>
                                                                                @endif
                                                                        @elseif ($subCoverage['s_ParentCoverageCode']=='STATEDBENEFITS' && $subCoverage['s_CoverageCode']!='FREE TEXT')
                                                                            <div class="form-floating mb-3">
                                                                                <input type="text" wire:model.defer="{{ ($preFixModel).'coverage_value_string' }}" value="{{ $subCoverage['coverage_value_string'] ?? '' }}" placeholder="Free Text" class="form-control">
                                                                                <x-form-label for="{{ ($preFixModel).'coverage_value_string' }}" value="Free Text" />
                                                                                <x-form-input-error name="{{ ($preFixModel).'coverage_value_string' }}"/>
                                                                            </div>
                                                                            <div class="form-floating mb-3">
                                                                                <input type="text" wire:model.defer="{{ ($preFixModel).'coverage_value' }}" value="{{ $subCoverage['coverage_value'] ?? '' }}" placeholder="Sum Insured" class="form-control amount-field" @blur="$el.value = window.formatAmount($el.value)">
                                                                                <x-form-label for="{{ ($preFixModel).'coverage_value' }}" value="Sum Insured" />
                                                                                <x-form-input-error name="{{ ($preFixModel).'coverage_value' }}"/>
                                                                            </div>
                                                                        @endif
                                                                        @if ($subCoverage['s_ParentCoverageCode']=='THEFT' && $subCoverage['s_CoverageCode']!='ENTER SUM INSURED')
                                                                            <input title="Sum Insured" type="number" step="0.01" wire:model.defer="{{ ($preFixModel).'ratefactor_value' }}" placeholder="Sum Insured" class="form-control amount-field"/>
                                                                            <x-form-label for="Sum Insured" value="Sum Insured" />
                                                                            <x-form-input-error name="{{ ($preFixModel).'ratefactor_value' }}"/>
                                                                        @endif
                                                                        <!-- @if ($subCoverage['s_ParentCoverageCode']=='PERSONALACCIDENT')
                                                                            <input type="text" wire:model.defer="{{ ($preFixModel).'coverage_value_string' }}" value="{{ $subCoverage['s_ScreenName'] ?? '' }}" placeholder="Free Text" class="form-control {{ ($subCoverage['s_ScreenName'] === 'Name of Insured Person and Designation') ? 'amount-field' : '' }}">
                                                                            <x-form-label for="{{ ($preFixModel).'coverage_value_string' }}" value="Free Text" />
                                                                            <x-form-input-error name="{{ ($preFixModel).'coverage_value_string' }}"/>
                                                                        @endif -->
                                                                        @if ($subCoverage['s_ParentCoverageCode']=='PERSONALACCIDENT' && $subCoverage['s_CoverageCode']=='NAME OF INSURED PERSON AN')
                                                                            <input type="text" wire:model.defer="{{ ($preFixModel).'coverage_value' }}" value="{{ $subCoverage['coverage_value'] ?? '' }}" placeholder="Free Text" class="form-control amount-field">
                                                                            <x-form-label for="{{ ($preFixModel).'coverage_value' }}" value="Free Text" />
                                                                            <x-form-input-error name="{{ ($preFixModel).'coverage_value' }}"/>
                                                                       
                                                                        @else ($subCoverage['s_ParentCoverageCode']=='PERSONALACCIDENT' )
                                                                            <input type="text" wire:model.defer="{{ ($preFixModel).'coverage_value_string' }}" value="{{ $subCoverage['s_ScreenName'] ?? '' }}" placeholder="Free Text" class="form-control">
                                                                            <x-form-label for="{{ ($preFixModel).'coverage_value_string' }}" value="Free Text" />
                                                                            <x-form-input-error name="{{ ($preFixModel).'coverage_value_string' }}"/>
                                                                        @endif
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    {{-- <div class="col-sm-1">
                                                        <div class="form-floating mb-3">
                                                            <input class="form-control" wire:model.defer="{{ ($preFixModel).'rate' }}"
                                                                id="{{ ($preFixModel).'rate' }}" placeholder="rate" disabled />
                                                            <x-form-label for="{{ ($preFixModel).'rate' }}" value="Rate %" />
                                                            <x-form-input-error name="{{ ($preFixModel).'rate' }}"/>
                                                        </div>
                                                    </div> --}}


                                                    <div class="col-sm-2">
                                                        <div class="form-floating mb-3">
                                                            <select title="Select Discount/Surcharge" class='form-select form-select-solid' wire:model.defer="{{ ($preFixModel).'discount_surcharge' }}" aria-label="Select Discount">
                                                                <option value="">- Select -</option>
                                                                <option value="Discount">Discount</option>
                                                                <option value="Surcharge">Surcharge</option>
                                                            </select>
                                                            <x-form-label for="{{ ($preFixModel).'discount_surcharge' }}" value="{{ __('Select Discount') }}"/>
                                                            <x-form-input-error name="{{ ($preFixModel).'discount_surcharge' }}"/>
                                                        </div>
                                                    </div>
                                                    <div class="col-sm-1">
                                                        <div class="form-floating mb-3">
                                                            <select title="Select Discount/Surcharge Type" class ='form-select form-select-solid' wire:model.defer="{{ ($preFixModel).'discount_surcharge_type' }}" aria-label="Select Percentage">
                                                                <option value="">- Select -</option>
                                                                <option value="Flat">Flat</option>
                                                                <option value="Percentage">Percentage</option>
                                                            </select>
                                                            <x-form-label for="{{ ($preFixModel).'discount_surcharge_type' }}" value="{{ __('Select Discount') }}"/>
                                                            <x-form-input-error name="{{ ($preFixModel).'discount_surcharge_type' }}"/>
                                                        </div>
                                                    </div>
                                                    <div class="col-sm-2">
                                                        <div class="form-floating mb-3">
                                                            <input type="number" step="0.01" title="Discount/Surcharge Value" wire:model.defer="{{ ($preFixModel).'discount_surcharge_value' }}" placeholder="Value" class="form-control amount-field" />
                                                            <x-form-label for="{{ ($preFixModel).'discount_surcharge_value' }}" value="Discounted/Surcharge value" />
                                                            <x-form-input-error name="{{ ($preFixModel).'discount_surcharge_value' }}"/>
                                                        </div>
                                                    </div>

                                                    @if ($subCoverage['s_ParentCoverageCode'] == "PERSONALMOTOR")

                                                        <div class="col-sm-2">
                                                            <div class="form-floating mb-3">
                                                                <input type="number" step="0.01" wire:model.defer="{{ ($preFixModel).'calculated_value' }}" placeholder="Premium" class="form-control amount-field" @blur="$el.value = window.formatAmount($el.value)" />
                                                                <x-form-label for="{{ ($preFixModel).'calculated_value' }}" value="Premium" />
                                                                <x-form-input-error name="{{ ($preFixModel).'calculated_value' }}"/>
                                                            </div>
                                                        </div>
                                                    @else
                                                        <div class="col-sm-2">
                                                            <div class="form-floating mb-3">
                                                                <input type="number" step="0.01" wire:model.defer="{{ ($preFixModel).'calculated_value' }}" placeholder="Premium" class="form-control amount-field" @blur="$el.value = window.formatAmount($el.value)" />
                                                                <x-form-label for="{{ ($preFixModel).'calculated_value' }}" value="Premium" />
                                                                <x-form-input-error name="{{ ($preFixModel).'calculated_value' }}"/>
                                                            </div>
                                                        </div>
                                                    @endif

                                                       <div class="col-sm-1">
                                                        <div class="form-floating mb-3">
                                                            <button class="btn text-white btn-info btn-sm float-right" wire:click="repeateSubCoverage({{$subCoverage['id']}})">
                                                                <i class="fa fa-plus"></i>
                                                            </button>
                                                                <p></p>
                                                            <button class="btn text-white btn-danger btn-sm float-right mr-2" wire:click="removeSubCoverage({{$policyCoverage->id}},{{$subCoverage->id}})">
                                                                <i class="fa fa-minus"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                                <br>
                                                @endif

                                                {{-- Excess --}}
                                                {{-- Start excess for perticular coverage  --}}

                                                @php
                                                $allExcess = $coverage->allExcess()->SubCoveragesCode($subCoverage['id'])->get();
                                                @endphp

                                                {{--  @if(count($coverage['allExcess']) > 0) --}}

                                                @if(count($allExcess) > 0)
                                                <h4> Excess</h4> <br>
                                                {{-- @foreach(($coverage['allExcess']) as $by => $subExtention) --}}
                                                @foreach($allExcess as $by => $subExtention)
                                                    @php
                                                        $header = $subExtention['s_CoverageName'];
                                                        $preFixModel = 'policyExtentionDetail'.'.'.$policyCoverage->id.'.'.$subExtention->id.'.';
                                                        // for
                                                        $extentiontbValidoptions = AlphaDirect\Models\TbValidOptions::where('s_OptionType','EXT_LIMIT')->where('n_SourceOneFK',$subExtention->id)->where('s_SourceOneType',$subExtention->type)->pluck('n_SourceTwoFK');
                                                        $extentionData = AlphaDirect\Models\Extention::pluck('extention_type');
                                                        $extentionCvgpclimits = AlphaDirect\Models\TbCvgpcLimits::whereIn('s_LimitTypeCode',$extentionData)->whereIn('n_PCLimitId_PK',$extentiontbValidoptions)->get(['n_PCLimitId_PK','s_LimitTypeCode','s_LimitScreenName']);
                                                        $extentionLimitTypeCode = $extentionCvgpclimits[0]['s_LimitTypeCode'] ?? 0;
                                                        $extentionLimitScreenName = $extentionCvgpclimits->pluck('s_LimitScreenName','n_PCLimitId_PK')->toArray() ?? 0;
                                                        $extentionscreenName = null;
                                                        foreach ($extentionCvgpclimits as $extentionscreen_name){
                                                            $extentionscreenName = $extentionscreen_name->extentionLimitScreenName;
                                                        }
                                                    @endphp
                                                    <div class="row">
                                                        <div class="col-sm-3">
                                                            <div class="form-floating mb-3">
                                                                <div class="col-sm-6">
                                                                    @if($extentionLimitTypeCode == 'DROPDOWN')
                                                                        <div class="form-floating mb-3">
                                                                            <select class="form-control" wire:model.defer="{{ ($preFixModel).'extention_limit_id' }}" id="{{ ($preFixModel).'extention_limit_id' }}" aria-label="Select Screen Name">
                                                                                <option value="">- Select -</option>
                                                                                @foreach ($extentionCvgpclimits as $data)
                                                                                <option value="{{ $data->n_PCLimitId_PK ?? '' }}">
                                                                                {{ $data->s_LimitScreenName ?? '' }}
                                                                                </option>
                                                                                @endforeach
                                                                            </select>
                                                                            <x-form-label for="{{ $extentionscreenName ?? '' }}" value="{{ $subExtention['s_ScreenName'] ?? '' }}" />
                                                                            <x-form-input-error name="{{ $extentionscreenName ?? '' }}"/>
                                                                        </div>
                                                                    @elseif ($extentionLimitTypeCode == 'RADIO')
                                                                        <div class="form-floating mb-3">
                                                                            <label for="question 2">{{ $subExtention['s_ScreenName'] }}</label><br><br>
                                                                                @foreach ($extentionCvgpclimits as $radio)
                                                                                <div class="form-check form-check-inline" style="margin-left: 12px">
                                                                                    <input class="form-check-input" type="radio" id="{{ ($preFixModel).'extention_limit_id' }}" name="{{ ($preFixModel).'extention_limit_id' }}" value="{{ $radio->n_PCLimitId_PK ?? '' }}" wire:model.defer="{{ ($preFixModel).'extention_limit_id' }}">
                                                                                    <label class="" for="inlineRadio1">{{ $radio->s_LimitScreenName }}</label>
                                                                                </div>
                                                                                @endforeach
                                                                        </div>
                                                                    @elseif ($extentionLimitTypeCode == 'NUMBER')
                                                                        <div class="form-floating mb-3">
                                                                            <input type="number" step="0.01" title="{{ $subExtention['s_ScreenName'] }}" class="form-control" wire:model.defer="{{ ($preFixModel).'extention_coverage_value' }}"
                                                                                id="{{ ($preFixModel).'extention_coverage_value' }}" placeholder="{{ $subExtention['s_ScreenName'] }}" />
                                                                                <x-form-label for="{{ ($preFixModel).'extention_coverage_value' }}" value="{{ $subExtention['s_ScreenName'] }}" />
                                                                                <x-form-input-error name="{{ ($preFixModel).'extention_coverage_value' }}"/>
                                                                        </div>
                                                                    @elseif ($extentionLimitTypeCode == 'NOEDIT')
                                                                        <div class="form-floating mb-3">
                                                                            <x-form-text-area title="{{ $subExtention['s_ScreenName'] }}" class="form-control" wire:model.defer="{{ ($preFixModel).'extention_text_value' }}"
                                                                                id="{{ ($preFixModel).'extention_text_value' }}" placeholder="{{ $subExtention['s_ScreenName'] }}"
                                                                                />
                                                                            <x-form-label for="{{ ($preFixModel).'extention_text_value' }}" value="{{ $subExtention['s_ScreenName'] }}" />
                                                                            <x-form-input-error name="{{ ($preFixModel).'extention_text_value' }}"/>
                                                                        </div>
                                                                    @else
                                                                        <div class="form-floating mb-3">
                                                                            <x-form-text-area title="{{ $subExtention['s_ScreenName'] }}" class="form-control" wire:model.defer="{{ ($preFixModel).'extention_text_value' }}"
                                                                                id="{{ ($preFixModel).'extention_text_value' }}" placeholder="{{ $subExtention['s_ScreenName'] }}"
                                                                                />
                                                                            <x-form-label for="{{ ($preFixModel).'extention_text_value' }}" value="{{ $subExtention['s_ScreenName'] }}" />
                                                                            <x-form-input-error name="{{ ($preFixModel).'extention_text_value' }}"/>
                                                                        </div>
                                                                    @endif

                                                                    <div class="col-sm-12" style="margin-left: 104%;margin-top: -36%;">
                                                                        <div class="form-floating mb-3">
                                                                                <input type="number" step="0.01" class="form-control" wire:model.defer="{{ ($preFixModel).'extention_excess_min_value' }}"
                                                                                    id="{{ ($preFixModel).'extention_excess_min_value' }}" placeholder="Min Excess"/>
                                                                                    <span style="color:red">Please Enter Number Only without % sign</span>
                                                                                <x-form-label for="{{ ($preFixModel).'extention_excess_min_value' }}" value="Min %" />
                                                                                <x-form-input-error name="{{ ($preFixModel).'extention_excess_min_value' }}"/>
                                                                            </div>
                                                                            <div class="form-floating mb-3">
                                                                                <input type="number" step="0.01" class="form-control" wire:model.defer="{{ ($preFixModel).'extention_excess_max_value' }}"
                                                                                    id="{{ ($preFixModel).'extention_excess_max_value' }}" placeholder="Minimum Amount" />
                                                                                <x-form-label for="{{ ($preFixModel).'extention_excess_max_value' }}" value="Minimum Amount" />
                                                                                <x-form-input-error name="{{ ($preFixModel).'extention_excess_max_value' }}"/>
                                                                            </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-sm-1" style="pointer-events: auto !important; opacity: 1 !important; cursor: pointer !important; background-color: white !important; color: inherit !important; border-color: #dee2e6 !important;">
                                                            <div class="form-floating mb-3">
                                                                <x-form-text-area title="Free Text" class="form-control" wire:model.defer="{{ ($preFixModel).'coverage_value_string' }}"
                                                                    id="{{ ($preFixModel).'coverage_value_string' }}" placeholder="Free Text" />
                                                                <x-form-label for="{{ ($preFixModel).'coverage_value_string' }}" value="Free Text" />
                                                                <x-form-input-error name="{{ ($preFixModel).'coverage_value_string' }}"/>
                                                            </div>
                                                        </div>
                                                        <!-- DEBUG: Coverage Code = {{ $policyCoverage->coverage['s_CoverageCode'] }} -->
                                                        @if(trim(strtoupper($policyCoverage->coverage['s_CoverageCode'])) != "HOUSEHOLDERS" && trim(strtoupper($policyCoverage->coverage['s_CoverageCode'])) != "HOUSEOWNERS" && trim(strtoupper($policyCoverage->coverage['s_CoverageCode'])) != "HOUSEOWNER-BUILDINGS")
                                                        <div class="col-sm-1" style="pointer-events: auto !important; opacity: 1 !important; cursor: pointer !important; background-color: white !important; color: inherit !important; border-color: #dee2e6 !important;">
                                                            <div class="form-floating mb-3">
                                                                <input type="number" step="0.01" wire:model.defer="{{ ($preFixModel).'coverage_value' }}" placeholder="Sum Insured" class="form-control amount-field" style="pointer-events: auto !important; opacity: 1 !important; cursor: pointer !important; background-color: white !important; color: inherit !important; border-color: #dee2e6 !important;" />
                                                                <x-form-label for="{{ ($preFixModel).'coverage_value' }}" value="Sum Insured" />
                                                                <x-form-input-error name="{{ ($preFixModel).'coverage_value' }}"/>
                                                            </div>
                                                        </div>
                                                        @endif
                                                        <div class="col-sm-2" style="pointer-events: auto !important; opacity: 1 !important; cursor: pointer !important; background-color: white !important; color: inherit !important; border-color: #dee2e6 !important;">
                                                            <div class="form-floating mb-3">
                                                                <select class='form-select form-select-solid' wire:model.defer="{{ ($preFixModel).'extention_discount_surcharge' }}" aria-label="Select Discount" style="pointer-events: auto !important; opacity: 1 !important; cursor: pointer !important; background-color: white !important; color: inherit !important; border-color: #dee2e6 !important;">
                                                                    <option value="">- Select -</option>
                                                                    <option value="Discount">Discount</option>
                                                                    <option value="Surcharge">Surcharge</option>
                                                                </select>
                                                                <x-form-label for="{{ ($preFixModel).'extention_discount_surcharge' }}" value="{{ __('Select Discount') }}"/>
                                                                <x-form-input-error name="{{ ($preFixModel).'extention_discount_surcharge' }}"/>
                                                            </div>
                                                        </div>
                                                        <div class="col-sm-1" style="pointer-events: auto !important; opacity: 1 !important; cursor: pointer !important; background-color: white !important; color: inherit !important; border-color: #dee2e6 !important;">
                                                            <div class="form-floating mb-3">
                                                                <select class = 'form-select form-select-solid' wire:model.defer="{{ ($preFixModel).'extention_discount_surcharge_type' }}" aria-label="Select Percentage" style="pointer-events: auto !important; opacity: 1 !important; cursor: pointer !important; background-color: white !important; color: inherit !important; border-color: #dee2e6 !important;">
                                                                    <option value="">- Select -</option>
                                                                    <option value="Flat">Flat</option>
                                                                    <option value="Percentage">Percentage</option>
                                                                </select>
                                                                <x-form-label for="{{ ($preFixModel).'extention_discount_surcharge_type' }}" value="{{ __('Select Discount') }}"/>
                                                                <x-form-input-error name="{{ ($preFixModel).'extention_discount_surcharge_type' }}"/>
                                                            </div>
                                                        </div>
                                                        <div class="col-sm-2" style="pointer-events: auto !important; opacity: 1 !important; cursor: pointer !important; background-color: white !important; color: inherit !important; border-color: #dee2e6 !important;">
                                                            <div class="form-floating mb-3">
                                                                <input type="number" step="0.01" wire:model.defer="{{ ($preFixModel).'extention_discount_surcharge_value' }}" placeholder="Value" class="form-control amount-field" style="pointer-events: auto !important; opacity: 1 !important; cursor: pointer !important; background-color: white !important; color: inherit !important; border-color: #dee2e6 !important;" />
                                                                <x-form-label for="{{ ($preFixModel).'extention_discount_surcharge_value' }}" value="Discounted/Surcharge value" />
                                                                <x-form-input-error name="{{ ($preFixModel).'extention_discount_surcharge_value' }}"/>
                                                            </div>
                                                        </div>
                                                        <div class="col-sm-2" style="pointer-events: auto !important; opacity: 1 !important; cursor: pointer !important; background-color: white !important; color: inherit !important; border-color: #dee2e6 !important;">
                                                            <div class="form-floating mb-3">
                                                                <input type="number" step="0.01" wire:model.defer="{{ ($preFixModel).'extention_calculated_value' }}" placeholder="Premium" class="form-control amount-field" style="pointer-events: auto !important; opacity: 1 !important; cursor: pointer !important; background-color: white !important; color: inherit !important; border-color: #dee2e6 !important;"/>
                                                                <x-form-label for="{{ ($preFixModel).'extention_calculated_value' }}" value="Premium" />
                                                                <x-form-input-error name="{{ ($preFixModel).'extention_calculated_value' }}"/>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    @endforeach
                                                @endif
                                                {{-- end excess for perticular coverage  --}}
                                            @endforeach
                                            {{-- SubCoverage Summary - Display entered values --}}
                                            <div class="px-4 py-3 bg-blue-50 border-t flex items-center justify-between"
                                                 id="summary_{{ $policyCoverage->id }}"
                                                 style="display: flex; justify-content: space-between; align-items: center; padding: 12px 16px; background-color: #f3f6ff; border-top: 1px solid #ddd; margin-top: 16px;">
                                                <span style="font-size: 14px; font-weight: 600; color: #4b5563;">Total: 1 subcoverage(s) filled</span>
                                                <div style="display: flex; gap: 24px; font-size: 14px;">
                                                    <span>Sum Insured: <b>P <span id="sumInsured_{{ $policyCoverage->id }}">0.00</span></b></span>
                                                    <span>Premium: <b>P <span id="premium_{{ $policyCoverage->id }}">0.00</span></b></span>
                                                </div>
                                            </div>

                                            <script>
                                            (function() {
                                                const covId = '{{ $policyCoverage->id }}';
                                                const sumSpan = document.getElementById('sumInsured_' + covId);
                                                const premiumSpan = document.getElementById('premium_' + covId);

                                                function updateSummary() {
                                                    // Find all input fields for this coverage
                                                    const allInputs = document.querySelectorAll('input[name*="policyCoverageDetail.' + covId + '"]');

                                                    let sumInsuredValue = 0;
                                                    let premiumValue = 0;

                                                    // Loop through all inputs to find coverage_value, ratefactor_value, and calculated_value
                                                    allInputs.forEach(input => {
                                                        const name = input.name;
                                                        console.log('Input found:', name, 'Value:', input.value);

                                                        if (name.includes('.coverage_value]') || name.includes('.ratefactor_value]')) {
                                                            sumInsuredValue += window.parseAmount(input.value);
                                                            console.log('Sum Insured parsed:', sumInsuredValue);
                                                        }
                                                        if (name.includes('.calculated_value]')) {
                                                            premiumValue += window.parseAmount(input.value);
                                                            console.log('Premium parsed:', premiumValue);
                                                        }
                                                    });

                                                    // Update display
                                                    sumSpan.textContent = window.formatAmount(sumInsuredValue);
                                                    premiumSpan.textContent = window.formatAmount(premiumValue);

                                                    console.log('Summary updated - Sum:', window.formatAmount(sumInsuredValue), 'Premium:', window.formatAmount(premiumValue));
                                                }

                                                // Initial update on load
                                                setTimeout(updateSummary, 500);

                                                // Listen to all inputs for this coverage
                                                const observer = setInterval(() => {
                                                    const allInputs = document.querySelectorAll('input[name*="policyCoverageDetail.' + covId + '"]');
                                                    if (allInputs.length > 0) {
                                                        allInputs.forEach(input => {
                                                            if (!input.hasListener) {
                                                                input.addEventListener('input', updateSummary);
                                                                input.addEventListener('change', updateSummary);
                                                                input.addEventListener('blur', updateSummary);
                                                                input.hasListener = true;
                                                                console.log('Listener added to:', input.name);
                                                            }
                                                        });
                                                        clearInterval(observer);
                                                    }
                                                }, 100);
                                            })();
                                            </script>
                                        @else
                                           {{-- For Motor Section --}}
                                        @endif



                                        @if($policyCoverage->coverage['s_CoverageCode'] == "FIDELITYGUARANTEE")
                                        @php
                                        $selectedDataDropdownValue = isset($PolicyCoveragesCoverNewData[$policyCoverage->id]['cover_type']) ? $PolicyCoveragesCoverNewData[$policyCoverage->id]['cover_type'] : "No";
                                        @endphp
                                            <div class="row">
                                                <div class="col-sm-5">
                                                <h4>Select Basis Of Cover 
                                                @foreach ($PolicyCoveragesCoverNewData as $index => $coverType)
                                                        @if($coverType['policyCoverageID'] == $policyCoverage->id)
                                                            {{ str_replace("_", " " ,$coverType['cover_type']) }}
                                                        @endif
                                                @endforeach
                                                 </h4>
                                                
                                                <select wire:change="selectedOption({{ $policyCoverage->risk_address_id }},{{ $policyCoverage->id }},$event.target.value)" aria-label="Select " class="form-select form-select-solid">    
                                                    <option value="">-Select-</option>
                                                    <option value="Blanket" @if($selectedDataDropdownValue == 'Blanket') selected @endif>Blanket</option>
                                                    <option value="Named_Position"  @if($selectedDataDropdownValue == 'Named_Position') selected @endif>Named / Position</option>
                                                </select>
                                                </div>
                                            </div>
                                            
                                        @endif
                                        @if($policyCoverage->coverage['s_CoverageCode'] == "FIDELITYGUARANTEE") 
                                        
                                        @foreach ($PolicyCoveragesNewData as $index => $post)
                                            @foreach ($post as $indexKey => $data)
                                            <?php
                                                $preFixPostModel = 'PolicyCoveragesNewData'.'.'.$policyCoverage->id.'.'.$indexKey.'.';
                                                
                                            ?> 
                                                @if($data['cover_type'] == 'Blanket' && $data['policyCoverageID'] == $policyCoverage->id)
                                                    <div class="row">
                                                        <div class="col-sm-3">
                                                            <h5>{{ $data['cover_type'] }}</h5>
                                                        </div>
                                                        <div class="col-sm-3">
                                                            <h5>{{ $data['cover_area'] }}</h5>
                                                        </div>
                                                        <div class="col-sm-2">
                                                        <input type="hidden" wire:model.defer="{{ ($preFixPostModel).'tId' }}" placeholder="tId" class="form-control"  />   
                                                            <input type="text" wire:model.defer="{{ ($preFixPostModel).'amount_to_be_guaranteed' }}" placeholder="Amount to be guaranteed" class="form-control" @blur="$el.value = window.formatAmount($el.value)" />                                                                                                                   
                                                        </div>
                                                        <div class="col-sm-2">
                                                            <input type="text" wire:model.defer="{{ ($preFixPostModel).'premium' }}" placeholder="Premium" class="form-control amount-field" @blur="$el.value = window.formatAmount($el.value)" />                                                                                                                   
                                                        </div>
                                                    </div>
                                                @elseif($data['cover_type'] == 'Named_Position' && $data['policyCoverageID'] == $policyCoverage->id)
                                                 
                                                <br/>
                                                    <div class="row" id="{{ $indexKey }}">
                                                            
                                                            <div class="col-sm-2">
                                                                <input type="text"  wire:model.defer="{{ ($preFixPostModel).'name_and_position' }}"   placeholder="Name And Position" class="form-control">
                                                            </div>
                                                            <div class="col-sm-2">
                                                                <input type="text"  wire:model.defer="{{ ($preFixPostModel).'designation' }}"  placeholder="Designation" class="form-control">
                                                            </div>
                                                            <div class="col-sm-2">
                                                                <input type="text"  wire:model.defer="{{ ($preFixPostModel).'length_of_service' }}"  placeholder="Length of service" class="form-control">
                                                            </div>
                                                            <div class="col-sm-2">
                                                                <input type="hidden" wire:model.defer="{{ ($preFixPostModel).'tId' }}" placeholder="tId" class="form-control"  />                                                                                                                   
                                                                <input type="hidden" wire:model.defer="{{ ($preFixPostModel).'policyCoverageID' }}" placeholder="policyCoverageID" class="form-control"  />                                                                                                                   
                                                                <input type="hidden" wire:model.defer="{{ ($preFixPostModel).'subpolicyCoverageID' }}" placeholder="subpolicyCoverageID" class="form-control" />                                                                                                                   
                                                                <input type="text" wire:model.defer="{{ ($preFixPostModel).'amount_to_be_guaranteed' }}" placeholder="Amount to be guaranteed" class="form-control" @blur="$el.value = window.formatAmount($el.value)" />                                                                                                                   
                                                            </div>
                                                            <div class="col-sm-2">
                                                                <input type="text"  wire:model.defer="{{ ($preFixPostModel).'premium' }}" @blur="$el.value = window.formatAmount($el.value)"   placeholder="Premium" class="form-control amount-field">
                                                            </div>
                                                            <div class="col-sm-2" >
                                                                @if($indexKey == 0)
                                                                <div class="btn btn-primary" wire:click="addFidelityGuarantee({{ $policyCoverage->risk_address_id }},{{ $policyCoverage->id }},'Named_Position')">
                                                                    <i class="fa fa-plus"></i>
                                                                </div>
                                                                @else
                                                                    <button wire:click="removeDBFideltiyGuarantee({{ $data['tId'] }})" class="btn btn-danger">
                                                                        <i class="fa fa-minus"></i>
                                                                    </button>
                                                                @endif
                                                                </div>
                                                        </div>
                                                @endif
                                            @endforeach
                                        @endforeach

                                        @endif
                                       {{-- For Extention --}}
                                        @php
                                        // Explicitly load all extensions for this coverage
                                        $coverageId = $coverage->id ?? null;
                                        $coverageCode = $coverage->s_CoverageCode ?? null;

                                        // Debug: Show what we're querying
                                        echo "<!-- DEBUG: coverageId={$coverageId}, coverageCode={$coverageCode} -->";

                                        // Query extensions - try both ID and Code
                                        $allExtention = \AlphaDirect\Models\Extention::where(function($q) use ($coverageCode, $coverageId) {
                                                $q->where('s_ParentCoverageCode', $coverageCode)
                                                  ->orWhere('s_ParentCoverageID', $coverageId);
                                            })
                                            ->where('type', 'Extention')
                                            ->where('s_DISPLAYTOUSER', '1')
                                            ->orderBy('n_DisplaySequence', 'asc')
                                            ->get();

                                        // Debug: Show results
                                        echo "<!-- DEBUG: Found " . count($allExtention) . " extensions -->";
                                        foreach($allExtention as $ext) {
                                            echo "<!-- Extension: {$ext->s_ScreenName} (ID: {$ext->id}) -->";
                                        }
                                        @endphp
                                        @if(count($allExtention) > 0)
                                            <hr>
                                            <h4> Extentions ({{ count($allExtention) }}) </h4>
                                            <div class="alert alert-info mb-3" style="display: none;" id="extensionSaveInfo">
                                                <strong>✓ Extension Data Inserted:</strong>
                                                <div id="extensionSaveDetails"></div>
                                            </div>
                                            @foreach(($allExtention) as $yx => $subExtention)

                                                {{-- @if($header!=$subExtention['s_CoverageName'])
                                                    <div class="row">
                                                        <h4>{{ $subExtention['s_CoverageName'] }}</h4>
                                                        <br><hr><br>
                                                    </div>
                                                @endif --}}
                                                @if($subExtention['s_ExtensionsGroupName'] == "Heading")
                                                    <br><br>
                                                    <div class="row">
                                                        <h6>{{ $subExtention['s_CoverageName'] }}</h6>
                                                        <br>
                                                    </div>
                                                @endif

                                                @php
                                                    // 3-level nested array for proper form data binding
                                                    $preFixModel = 'policyExtentionDetail.'.$policyCoverage->id.'.'.$subExtention->id.'.';
                                                    // for extentions
                                                    $extentiontbValidoptions = AlphaDirect\Models\TbValidOptions::where('s_OptionType','EXT_LIMIT')->where('n_SourceOneFK',$subExtention->id)->where('s_SourceOneType',$subExtention->type)->pluck('n_SourceTwoFK');
                                                    $extentionData = AlphaDirect\Models\Extention::pluck('extention_type');
                                                    $extentionCvgpclimits = AlphaDirect\Models\TbCvgpcLimits::whereIn('s_LimitTypeCode',$extentionData)->whereIn('n_PCLimitId_PK',$extentiontbValidoptions)->get(['n_PCLimitId_PK','s_LimitTypeCode','s_LimitScreenName']);
                                                    $extentionLimitTypeCode = $extentionCvgpclimits[0]['s_LimitTypeCode'] ?? 0;
                                                    $extentionLimitScreenName = $extentionCvgpclimits->pluck('s_LimitScreenName','n_PCLimitId_PK')->toArray() ?? 0;
                                                    $extentionscreenName = null;
                                                    foreach ($extentionCvgpclimits as $extentionscreen_name){
                                                        $extentionscreenName = $extentionscreen_name->extentionLimitScreenName;
                                                    }
                                                @endphp

                                                @if($subExtention['s_ExtensionsGroupName'] != "Heading")

                                                    <div class="row">
                                                        <div class="col-sm-2">
                                                            <div class="form-floating mb-3">
                                                                <label style="font-weight: 600;">{{ $subExtention['s_CoverageName'] }}</label><br><br>
                                                                <div class="form-check form-check-inline" style="margin-left: 12px; pointer-events: auto !important;">
                                                                    <input class="form-check-input" type="radio" id="{{ $preFixModel }}extension_yes" name="{{ $preFixModel }}extension_select" value="Yes" wire:model.defer="{{ $preFixModel }}extension_select" />
                                                                    <label class="form-check-label" for="{{ $preFixModel }}extension_yes">Yes</label>
                                                                </div>
                                                                <div class="form-check form-check-inline" style="margin-left: 2px; pointer-events: auto !important;">
                                                                    <input class="form-check-input" type="radio" id="{{ $preFixModel }}extension_no" name="{{ $preFixModel }}extension_select" value="No" wire:model.defer="{{ $preFixModel }}extension_select" />
                                                                    <label class="form-check-label" for="{{ $preFixModel }}extension_no">No</label>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        @if(trim(strtoupper($policyCoverage->coverage['s_CoverageCode'])) != "HOUSEHOLDERS" && trim(strtoupper($policyCoverage->coverage['s_CoverageCode'])) != "HOUSEOWNERS" && trim(strtoupper($policyCoverage->coverage['s_CoverageCode'])) != "HOUSEOWNER-BUILDINGS")
                                                        <div class="col-sm-3" style="pointer-events: auto !important; opacity: 1 !important;">
                                                            <div class="form-floating mb-3">
                                                                <div class="col-sm-12">
                                                                    @if($extentionLimitTypeCode == 'DROPDOWN')
                                                                        <div class="form-floating mb-3">
                                                                            <select class="form-control" wire:model.defer="{{ ($preFixModel).'extention_limit_id' }}" id="{{ ($preFixModel).'extention_limit_id' }}" aria-label="Select Screen Name">
                                                                                <option value="">- Select -</option>
                                                                                @foreach ($extentionCvgpclimits as $data)
                                                                                <option value="{{ $data->n_PCLimitId_PK ?? '' }}">
                                                                                {{ $data->s_LimitScreenName ?? '' }}
                                                                                </option>
                                                                                @endforeach
                                                                            </select>
                                                                            <x-form-label for="{{ $extentionscreenName ?? '' }}" value="{{ $subExtention['s_ScreenName'] ?? '' }}" />
                                                                            <x-form-input-error name="{{ $extentionscreenName ?? '' }}"/>
                                                                        </div>
                                                                    @elseif ($extentionLimitTypeCode == 'RADIO')
                                                                        <div class="form-floating mb-3">
                                                                            <label for="question 3" class="newtext" >{{ $subExtention['s_ScreenName'] }}
                                                                            </label><br><br>
                                                                                @foreach ($extentionCvgpclimits as $radio)
                                                                                <div class="form-check form-check-inline" style="margin-left: 12px">
                                                                                    <input class="form-check-input" type="radio" id="{{ ($preFixModel).'extention_limit_id' }}" name="{{ ($preFixModel).'extention_limit_id' }}" value="{{ $radio->n_PCLimitId_PK ?? '' }}" wire:model.defer="{{ ($preFixModel).'extention_limit_id' }}">
                                                                                    <label class="" for="inlineRadio1">{{ $radio->s_LimitScreenName }}</label>
                                                                                </div>
                                                                                @endforeach
                                                                                @if($policyCoverage->coverage['s_CoverageCode'] == "BUSINESSALLRISKS")
                                                                                <span >
                                                                                    <input type="text" @blur="$el.value = window.formatAmount($el.value)"  wire:model.defer="{{ ($preFixModel).'extention_sum_insured' }}"
                                                                                    value="{{ $subCoverage['s_ScreenName'] ?? '' }}"
                                                                                    placeholder="Sum Insured " class="form-control"
                                                                                    style="width: 50%;display: inline;position: absolute;height:50px;">
                                                                                    <x-form-label for="{{ ($preFixModel).'extention_sum_insured' }}" value="Sum Insured" />
                                                                                    <x-form-input-error name="{{ ($preFixModel).'extention_sum_insured' }}"/>
                                                                                </span>
                                                                            @endif
                                                                            <br/>

                                                                            {{-- <input type="radio" id="{{ ($preFixModel).'extention_limit_id' }}" name="{{ ($preFixModel).'extention_limit_id' }}" value="{{ $radio->n_PCLimitId_PK ?? '' }}" wire:model.defer="{{ ($preFixModel).'extention_limit_id' }}" style="height:45px;width: 19px;margin-left: 170px;">{{ $radio->s_LimitScreenName }} --}}
                                                                        </div>
                                                                    @elseif ($extentionLimitTypeCode == 'NUMBER')
                                                                        <div class="form-floating mb-3">
                                                                            <input type="number" step="0.01" title="{{ $subExtention['s_ScreenName'] }}" class="form-control" wire:model.defer="{{ ($preFixModel).'extention_coverage_value' }}"
                                                                                id="{{ ($preFixModel).'extention_coverage_value' }}" placeholder="{{ $subExtention['s_ScreenName'] }}" />
                                                                                <x-form-label for="{{ ($preFixModel).'extention_coverage_value' }}" value="{{ $subExtention['s_ScreenName'] }}" />
                                                                                <x-form-input-error name="{{ ($preFixModel).'extention_coverage_value' }}"/>
                                                                        </div>
                                                                    @elseif ($extentionLimitTypeCode == 'NOEDIT')
                                                                        <div class="form-floating mb-3">
                                                                            <x-form-text-area title="{{ $subExtention['s_ScreenName'] }}" class="form-control" wire:model.defer="{{ ($preFixModel).'extention_text_value' }}"
                                                                                id="{{ ($preFixModel).'extention_text_value' }}" placeholder="{{ $subExtention['s_ScreenName'] }}"
                                                                                />
                                                                            <x-form-label for="{{ ($preFixModel).'extention_text_value' }}" value="{{ $subExtention['s_ScreenName'] }}" />
                                                                            <x-form-input-error name="{{ ($preFixModel).'extention_text_value' }}"/>
                                                                        </div>
                                                                    @else
                                                                        <div class="form-floating mb-3">
                                                                            <x-form-text-area title="{{ $subExtention['s_ScreenName'] }}" class="form-control" wire:model.defer="{{ ($preFixModel).'extention_text_value' }}"
                                                                                id="{{ ($preFixModel).'extention_text_value' }}" placeholder="{{ $subExtention['s_ScreenName'] }}"
                                                                                />
                                                                            <x-form-label for="{{ ($preFixModel).'extention_text_value' }}" value="{{ $subExtention['s_ScreenName'] }}" />
                                                                            <x-form-input-error name="{{ ($preFixModel).'extention_text_value' }}"/>
                                                                        </div>
                                                                    @endif

                                                                    <div class="col-sm-12" style="margin-left: 104%;margin-top: -24%;">
                                                                        <div class="form-floating mb-3">
                                                                            @if($subExtention['s_ParentCoverageCode']=='COMPUTEREQUIPMENT')
                                                                            {{-- <div class="form-floating mb-3">
                                                                            <x-form-text-area class="form-control" wire:model.defer="{{ ($preFixModel).'extention_ratefactor_value' }}"
                                                                                    id="{{ ($preFixModel).'extention_ratefactor_value' }}" placeholder="{{ $subExtention['s_ScreenName'] }}"
                                                                                    />
                                                                                <x-form-label for="{{ ($preFixModel).'extention_ratefactor_value' }}" value="Free Text" />
                                                                                <x-form-input-error name="{{ ($preFixModel).'extention_ratefactor_value' }}"/>
                                                                            </div> --}}
                                                                            {{-- @elseif($subExtention['s_ParentCoverageCode']=='HOUSEHOLDERS-CONTENTS')
                                                                            <div class="form-floating mb-3">
                                                                                <input type="text" class="form-control" wire:model.defer="{{ ($preFixModel).'extention_ratefactor_value' }}"
                                                                                    id="{{ ($preFixModel).'extention_ratefactor_value' }}" placeholder="{{ $subExtention['s_ScreenName'] }}"
                                                                                    />
                                                                                <x-form-label for="{{ ($preFixModel).'extention_ratefactor_value' }}" value="Free Text" />
                                                                                <x-form-input-error name="{{ ($preFixModel).'extention_ratefactor_value' }}"/>
                                                                            </div> --}}
                                                                            @endif
                                                                        </div>
                                                                    </div>


                                                                </div>

                                                            </div>
                                                        </div>
                                                        @endif

                                                        <div class="col-sm-2" style="pointer-events: auto !important; opacity: 1 !important; cursor: pointer !important; background-color: white !important; color: inherit !important; border-color: #dee2e6 !important;">
                                                            <div class="form-floating mb-3">
                                                                <select class='form-select form-select-solid' wire:model.defer="{{ ($preFixModel).'extention_discount_surcharge' }}" aria-label="Select Discount" style="pointer-events: auto !important; opacity: 1 !important; cursor: pointer !important; background-color: white !important; color: inherit !important; border-color: #dee2e6 !important;">
                                                                    <option value="">- Select -</option>
                                                                    <option value="Discount">Discount</option>
                                                                    <option value="Surcharge">Surcharge</option>
                                                                </select>
                                                                <x-form-label for="{{ ($preFixModel).'extention_discount_surcharge' }}" value="{{ __('Select Discount') }}"/>
                                                                <x-form-input-error name="{{ ($preFixModel).'extention_discount_surcharge' }}"/>
                                                            </div>
                                                        </div>
                                                        <div class="col-sm-1" style="pointer-events: auto !important; opacity: 1 !important; cursor: pointer !important; background-color: white !important; color: inherit !important; border-color: #dee2e6 !important;">
                                                            <div class="form-floating mb-3">
                                                                <select class = 'form-select form-select-solid' wire:model.defer="{{ ($preFixModel).'extention_discount_surcharge_type' }}" aria-label="Select Percentage" style="pointer-events: auto !important; opacity: 1 !important; cursor: pointer !important; background-color: white !important; color: inherit !important; border-color: #dee2e6 !important;">
                                                                    <option value="">- Select -</option>
                                                                    <option value="Flat">Flat</option>
                                                                    <option value="Percentage">Percentage</option>
                                                                </select>
                                                                <x-form-label for="{{ ($preFixModel).'extention_discount_surcharge_type' }}" value="{{ __('Select Discount') }}"/>
                                                                <x-form-input-error name="{{ ($preFixModel).'extention_discount_surcharge_type' }}"/>
                                                            </div>
                                                        </div>
                                                        <div class="col-sm-2" style="pointer-events: auto !important; opacity: 1 !important; cursor: pointer !important; background-color: white !important; color: inherit !important; border-color: #dee2e6 !important;">
                                                            <div class="form-floating mb-3">
                                                                <input type="number" step="0.01" wire:model.defer="{{ ($preFixModel).'extention_discount_surcharge_value' }}" placeholder="Value" class="form-control amount-field" style="pointer-events: auto !important; opacity: 1 !important; cursor: pointer !important; background-color: white !important; color: inherit !important; border-color: #dee2e6 !important;" />
                                                                <x-form-label for="{{ ($preFixModel).'extention_discount_surcharge_value' }}" value="Discounted/Surcharge value" />
                                                                <x-form-input-error name="{{ ($preFixModel).'extention_discount_surcharge_value' }}"/>
                                                            </div>
                                                        </div>
                                                        <div class="col-sm-2" style="pointer-events: auto !important; opacity: 1 !important; cursor: pointer !important; background-color: white !important; color: inherit !important; border-color: #dee2e6 !important;">
                                                            <div class="form-floating mb-3">
                                                                <input type="number" step="0.01" wire:model.defer="{{ ($preFixModel).'extention_calculated_value' }}" placeholder="Premium" class="form-control amount-field" style="pointer-events: auto !important; opacity: 1 !important; cursor: pointer !important; background-color: white !important; color: inherit !important; border-color: #dee2e6 !important;"/>
                                                                <x-form-label for="{{ ($preFixModel).'extention_calculated_value' }}" value="Premium" />
                                                                <x-form-input-error name="{{ ($preFixModel).'extention_calculated_value' }}"/>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <br>
                                                @endif
                                            @endforeach
                                                @if($policyCoverage->coverage['s_CoverageCode'] == "BUSINESSALLRISKS")
                                                    <hr/>
                                                    <div class="row">
                                                            <div class="col-sm-12"><h5>Excesses</h5></div>
                                                            <div class="col-sm-3">
                                                                <input type="text"  wire:model.defer="inputBusiExcessesData.0.excesses"  value="{{ $subCoverage['s_ScreenName'] ?? '' }}" placeholder="Excesses " class="form-control">
                                                            </div>
                                                            <div class="col-sm-3">
                                                                <input type="text"  wire:model.defer="inputBusiExcessesData.0.min_percent"  value="{{ $subCoverage['s_ScreenName'] ?? '' }}" placeholder="Min %" class="form-control">
                                                            </div>
                                                            <div class="col-sm-3">
                                                                <input type="text" @blur="$el.value = window.formatAmount($el.value)"   wire:model.defer="inputBusiExcessesData.0.min_amt"  value="{{ $subCoverage['s_ScreenName'] ?? '' }}" placeholder="Minimum Amount" class="form-control">
                                                            </div>
                                                            <div class="col-sm-3">
                                                                <div class="btn btn-primary" wire:click="addBusiExcessesFidelityGuarantee({{ $i }})">
                                                                    <i class="fa fa-plus"></i>
                                                                </div>
                                                            </div>
                                                    </div>
                                                    <br/>

                                                    @if(isset($inputBusiExcessesData))

                                                        @foreach($inputBusiExcessesData as $key => $input)
                                                            @if($key != 0)
                                                                <div class="row" id="{{ $key }}">
                                                                        <div class="col-sm-3">
                                                                            <input type="text"  wire:model.defer="inputBusiExcessesData.{{ $key }}.excesses"  value="{{ $subCoverage['s_ScreenName'] ?? '' }}" placeholder="Excesses " class="form-control">
                                                                        </div>
                                                                        <div class="col-sm-3">
                                                                            <input type="text"  wire:model.defer="inputBusiExcessesData.{{ $key }}.min_percent"  value="{{ $subCoverage['s_ScreenName'] ?? '' }}" placeholder="Min %" class="form-control">
                                                                        </div>
                                                                        <div class="col-sm-3">
                                                                            <input type="text" @blur="$el.value = window.formatAmount($el.value)"  wire:model.defer="inputBusiExcessesData.{{ $key }}.min_amt"  value="{{ $subCoverage['s_ScreenName'] ?? '' }}" placeholder="Minimum Amount" class="form-control">
                                                                        </div>
                                                                        <div class="col-sm-3">
                                                                            <div wire:click="removeBusiExcessesFidelityGuarantee({{ $key }})" data-rowId="{{ $key }}"  class="btn btn-danger">
                                                                                <i class="fa fa-minus"></i>
                                                                            </div>
                                                                        </div>
                                                                </div>
                                                            @endif
                                                            <br/>
                                                        @endforeach
                                                     @endif
                                                    @if(isset($pBusiExcessesData))
                                                        @foreach ($pBusiExcessesData as $pKey => $pData)
                                                            <br/>
                                                            <div class="row" id="{{ $pData['id']  }}">
                                                                    <div class="col-sm-3">
                                                                        <input type="text"  value="{{ $pData['excesses'] ?? '' }}" placeholder="Excesses " class="form-control">
                                                                    </div>
                                                                    <div class="col-sm-3">
                                                                        <input type="text"  value="{{ $pData['min_percent'] ?? '' }}" placeholder="Min %" class="form-control">
                                                                    </div>
                                                                    <div class="col-sm-3">
                                                                        <input type="text"   value="{{ number_format((float)$pData['min_amt'] ?? "", 2, '.', ',')  }}" placeholder="Minimum Amount" class="form-control">
                                                                    </div>
                                                                    <div class="col-sm-3">
                                                                        <div wire:click="removeDBBusiExcessesFidelityGuarantee({{ $pData['id'] }})" data-rowId="{{ $pData['id'] }}" class="btn btn-danger hideBusiExcessesFidelityGuarantee">
                                                                            <i class="fa fa-minus"></i>
                                                                        </div>
                                                                    </div>
                                                            </div>
                                                        @endforeach
                                                    @endif
                                                    <hr>
                                                @endif
                                                    @if($policyCoverage->coverage['s_CoverageCode'] == "FIDELITYGUARANTEE")
                                                        <hr>
                                                            <div class="row">
                                                                <div class="col-sm-12"><h5>Excesses</h5></div>
                                                                <div class="col-sm-3">
                                                                    <input type="text"   wire:model.defer="inputExcessesData.0.excesses"  value="{{ $subCoverage['s_ScreenName'] ?? '' }}" placeholder="Excesses " class="form-control">
                                                                </div>
                                                                <div class="col-sm-3">
                                                                    <input type="text"  @blur="$el.value = window.formatAmount($el.value)" wire:model.defer="inputExcessesData.0.min_percent"  value="{{ $subCoverage['s_ScreenName'] ?? '' }}" placeholder="Min %" class="form-control">
                                                                </div>
                                                                <div class="col-sm-3">
                                                                    <input type="text"  @blur="$el.value = window.formatAmount($el.value)" wire:model.defer="inputExcessesData.0.min_amt"  value="{{ $subCoverage['s_ScreenName'] ?? '' }}" placeholder="Minimum Amount" class="form-control amount-field">
                                                                </div>
                                                                <div class="col-sm-3">
                                                                    <div class="btn btn-primary" wire:click="addExcessesFidelityGuarantee({{ $k }})">
                                                                        <i class="fa fa-plus"></i>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        <br/>
                                                            @if(isset($inputExcessesData))
                                                                @foreach($inputExcessesData as $key => $input)
                                                                    @if($key != 0)
                                                                        <div class="row" id="{{ $key }}">
                                                                                <div class="col-sm-3">
                                                                                    <input type="text"   wire:model.defer="inputExcessesData.{{ $key }}.excesses"  value="{{ $subCoverage['s_ScreenName'] ?? '' }}" placeholder="Excesses " class="form-control">
                                                                                </div>
                                                                                <div class="col-sm-3">
                                                                                    <input type="text"  @blur="$el.value = window.formatAmount($el.value)" wire:model.defer="inputExcessesData.{{ $key }}.min_percent"  value="{{ $subCoverage['s_ScreenName'] ?? '' }}" placeholder="Min %" class="form-control">
                                                                                </div>
                                                                                <div class="col-sm-3">
                                                                                    <input type="text"  @blur="$el.value = window.formatAmount($el.value)" wire:model.defer="inputExcessesData.{{ $key }}.min_amt"  value="{{ $subCoverage['s_ScreenName'] ?? '' }}" placeholder="Minimum Amount" class="form-control amount-field">
                                                                                </div>
                                                                                <div class="col-sm-3">
                                                                                    <button wire:click="removeExcessesFidelityGuarantee({{ $key }})" class="btn btn-danger">
                                                                                        <i class="fa fa-minus"></i>
                                                                                    </button>
                                                                                </div>
                                                                        </div>
                                                                        <br/>
                                                                    @endif
                                                                @endforeach
                                                            @endif
                                                            @if(isset($pExcessesData))
                                                                @foreach ($pExcessesData as $pKey => $pData)
                                                                    <br/>
                                                                    <div class="row" id="{{ $pData['id'] }}">
                                                                            <div class="col-sm-3">
                                                                                <input type="text"  value="{{ $pData['excesses'] ?? '' }}" placeholder="Excesses " class="form-control">
                                                                            </div>
                                                                            <div class="col-sm-3">
                                                                                <input type="text"  value="{{ $pData['min_percent'] ?? '' }}" placeholder="Min %" class="form-control">
                                                                            </div>
                                                                            <div class="col-sm-3">
                                                                                <input type="text"   value="{{ number_format((float)$pData['min_amt'] ?? "", 2, '.', ',')  }}" placeholder="Minimum Amount" class="form-control">
                                                                            </div>
                                                                            <div class="col-sm-3">
                                                                                <div wire:click="removeDBExcessesFidelityGuarantee({{ $pData['id'] }})" data-rowId="{{ $pData['id'] }}"  class="btn btn-danger removeDBExcessesFidelityGuarantee">
                                                                                    <i class="fa fa-minus"></i>
                                                                                </div>
                                                                            </div>
                                                                    </div>
                                                                @endforeach
                                                            @endif
                                                        <hr/>
                                                    @endif
                                        @endif

                                        {{-- Excess --}}

                                        @php
                                        $allExcess = $coverage->allExcess()->MainCoveragesCode()->get();
                                        @endphp

                                        {{--  @if(count($coverage['allExcess']) > 0) --}}
                                         @if(count($allExcess) > 0)
                                         <hr>
                                         <h4> Excess</h4> <br>
                                         {{-- @foreach(($coverage['allExcess']) as $by => $subExtention) --}}
                                         @foreach($allExcess as $by => $subExtention)
                                             @php
                                                 $header = $subExtention['s_CoverageName'];
                                                 $preFixModel = 'policyExtentionDetail'.'.'.$policyCoverage->id.'.'.$subExtention->id.'.';
                                                 // for
                                                 $extentiontbValidoptions = AlphaDirect\Models\TbValidOptions::where('s_OptionType','EXT_LIMIT')->where('n_SourceOneFK',$subExtention->id)->where('s_SourceOneType',$subExtention->type)->pluck('n_SourceTwoFK');
                                                 $extentionData = AlphaDirect\Models\Extention::pluck('extention_type');
                                                 $extentionCvgpclimits = AlphaDirect\Models\TbCvgpcLimits::whereIn('s_LimitTypeCode',$extentionData)->whereIn('n_PCLimitId_PK',$extentiontbValidoptions)->get(['n_PCLimitId_PK','s_LimitTypeCode','s_LimitScreenName']);
                                                 $extentionLimitTypeCode = $extentionCvgpclimits[0]['s_LimitTypeCode'] ?? 0;
                                                 $extentionLimitScreenName = $extentionCvgpclimits->pluck('s_LimitScreenName','n_PCLimitId_PK')->toArray() ?? 0;
                                                 $extentionscreenName = null;
                                                 foreach ($extentionCvgpclimits as $extentionscreen_name){
                                                     $extentionscreenName = $extentionscreen_name->extentionLimitScreenName;
                                                 }
                                             @endphp
                                             <div class="row">
                                                 <div class="col-sm-5">
                                                     <div class="form-floating mb-3">
                                                         <div class="col-sm-6">
                                                             @if($extentionLimitTypeCode == 'DROPDOWN')
                                                                 <div class="form-floating mb-3">
                                                                     <select class="form-control" wire:model.defer="{{ ($preFixModel).'extention_limit_id' }}" id="{{ ($preFixModel).'extention_limit_id' }}" aria-label="Select Screen Name">
                                                                         <option value="">- Select -</option>
                                                                         @foreach ($extentionCvgpclimits as $data)
                                                                         <option value="{{ $data->n_PCLimitId_PK ?? '' }}">
                                                                         {{ $data->s_LimitScreenName ?? '' }}
                                                                         </option>
                                                                         @endforeach
                                                                     </select>
                                                                     <x-form-label for="{{ $extentionscreenName ?? '' }}" value="{{ $subExtention['s_ScreenName'] ?? '' }}" />
                                                                     <x-form-input-error name="{{ $extentionscreenName ?? '' }}"/>
                                                                 </div>
                                                             @elseif ($extentionLimitTypeCode == 'RADIO')
                                                                 <div class="form-floating mb-3">
                                                                     <label for="question 4">{{ $subExtention['s_ScreenName'] }}</label><br><br>
                                                                         @foreach ($extentionCvgpclimits as $radio)
                                                                         <div class="form-check form-check-inline" style="margin-left: 12px">
                                                                             <input class="form-check-input" type="radio" id="{{ ($preFixModel).'extention_limit_id' }}" name="{{ ($preFixModel).'extention_limit_id' }}" value="{{ $radio->n_PCLimitId_PK ?? '' }}" wire:model.defer="{{ ($preFixModel).'extention_limit_id' }}">
                                                                             <label class="" for="inlineRadio1">{{ $radio->s_LimitScreenName }}</label>
                                                                         </div>
                                                                         @endforeach
                                                                 </div>
                                                             @elseif ($extentionLimitTypeCode == 'NUMBER')
                                                                 <div class="form-floating mb-3">
                                                                     <input title="{{ $subExtention['s_ScreenName'] }}" class="form-control" wire:model.defer="{{ ($preFixModel).'extention_coverage_value' }}"
                                                                         id="{{ ($preFixModel).'extention_coverage_value' }}" placeholder="{{ $subExtention['s_ScreenName'] }}"
                                                                         @blur="$el.value = window.formatAmount($el.value)" />
                                                                         <x-form-label for="{{ ($preFixModel).'extention_coverage_value' }}" value="{{ $subExtention['s_ScreenName'] }}" />
                                                                         <x-form-input-error name="{{ ($preFixModel).'extention_coverage_value' }}"/>

                                                                 </div>
                                                             @elseif ($extentionLimitTypeCode == 'NOEDIT')
                                                                 <div class="form-floating mb-3">
                                                                     <x-form-text-area title="{{ $subExtention['s_ScreenName'] }}" class="form-control" wire:model.defer="{{ ($preFixModel).'extention_text_value' }}"
                                                                         id="{{ ($preFixModel).'extention_text_value' }}" placeholder="{{ $subExtention['s_ScreenName'] }}"
                                                                         />
                                                                     <x-form-label for="{{ ($preFixModel).'extention_text_value' }}" value="{{ $subExtention['s_ScreenName'] }}" />
                                                                     <x-form-input-error name="{{ ($preFixModel).'extention_text_value' }}"/>

                                                                 </div>
                                                             @else
                                                                 <div class="form-floating mb-3">
                                                                     <x-form-text-area title="{{ $subExtention['s_ScreenName'] }}" class="form-control" wire:model.defer="{{ ($preFixModel).'extention_text_value' }}"
                                                                         id="{{ ($preFixModel).'extention_text_value' }}" placeholder="{{ $subExtention['s_ScreenName'] }}"
                                                                         />
                                                                     <x-form-label for="{{ ($preFixModel).'extention_text_value' }}" value="{{ $subExtention['s_ScreenName'] }}" />
                                                                     <x-form-input-error name="{{ ($preFixModel).'extention_text_value' }}"/>
                                                                 </div>
                                                             @endif

                                                               <div class="col-sm-12" style="margin-left: 104%;margin-top: -36%;">
                                                                   <div class="form-floating mb-3">
                                                                        <input type="text" class="form-control" wire:model.defer="{{ ($preFixModel).'extention_excess_min_value' }}"
                                                                            id="{{ ($preFixModel).'extention_excess_min_value' }}" placeholder="{{ $subExtention['s_ScreenName'] }}"
                                                                            />
                                                                            <span style="color:red">Please Enter Number Only without % sign</span>
                                                                        <x-form-label for="{{ ($preFixModel).'extention_excess_min_value' }}" value="Min %" />
                                                                        <x-form-input-error name="{{ ($preFixModel).'extention_excess_min_value' }}"/>
                                                                    </div>
                                                                    <div class="form-floating mb-3">
                                                                        <input class="form-control" wire:model.defer="{{ ($preFixModel).'extention_excess_max_value' }}"
                                                                            id="{{ ($preFixModel).'extention_excess_max_value' }}" placeholder="{{ $subExtention['s_ScreenName'] }}"
                                                                            @blur="$el.value = window.formatAmount($el.value)" />
                                                                        <x-form-label for="{{ ($preFixModel).'extention_excess_max_value' }}" value="Minimum Amount" />
                                                                        <x-form-input-error name="{{ ($preFixModel).'extention_excess_max_value' }}"/>
                                                                    </div>
                                                               </div>

                                                         </div>
                                                     </div>
                                                 </div>
                                                 <div class="col-sm-1">
                                                     <div class="form-floating mb-3">
                                                         <x-form-text-area title="Free Text" class="form-control" wire:model.defer="{{ ($preFixModel).'coverage_value_string' }}"
                                                             id="{{ ($preFixModel).'coverage_value_string' }}" placeholder="Free Text" />
                                                         <x-form-label for="{{ ($preFixModel).'coverage_value_string' }}" value="Free Text" />
                                                         <x-form-input-error name="{{ ($preFixModel).'coverage_value_string' }}"/>
                                                     </div>
                                                 </div>
                                                 <!-- DEBUG2: Coverage Code = {{ $policyCoverage->coverage['s_CoverageCode'] }} -->
                                                 @if(trim(strtoupper($policyCoverage->coverage['s_CoverageCode'])) != "HOUSEHOLDERS" && trim(strtoupper($policyCoverage->coverage['s_CoverageCode'])) != "HOUSEOWNERS" && trim(strtoupper($policyCoverage->coverage['s_CoverageCode'])) != "HOUSEOWNER-BUILDINGS")
                                                 <div class="col-sm-1">
                                                     <div class="form-floating mb-3">
                                                         <input type="number" step="0.01" wire:model.defer="{{ ($preFixModel).'coverage_value' }}" placeholder="Sum Insured" class="form-control amount-field" />
                                                         <x-form-label for="{{ ($preFixModel).'coverage_value' }}" value="Sum Insured" />
                                                         <x-form-input-error name="{{ ($preFixModel).'coverage_value' }}"/>
                                                     </div>
                                                 </div>
                                                 @endif
                                                 <div class="col-sm-2">
                                                     <div class="form-floating mb-3">
                                                         <select class='form-select form-select-solid' wire:model.defer="{{ ($preFixModel).'extention_discount_surcharge' }}" aria-label="Select Discount">
                                                             <option value="">- Select -</option>
                                                             <option value="Discount">Discount</option>
                                                             <option value="Surcharge">Surcharge</option>
                                                         </select>
                                                         <x-form-label for="{{ ($preFixModel).'extention_discount_surcharge' }}" value="{{ __('Select Discount') }}"/>
                                                         <x-form-input-error name="{{ ($preFixModel).'extention_discount_surcharge' }}"/>
                                                     </div>
                                                 </div>
                                                 <div class="col-sm-1">
                                                     <div class="form-floating mb-3">
                                                         <select class = 'form-select form-select-solid' wire:model.defer="{{ ($preFixModel).'extention_discount_surcharge_type' }}" aria-label="Select Percentage">
                                                             <option value="">- Select -</option>
                                                             <option value="Flat">Flat</option>
                                                             <option value="Percentage">Percentage</option>
                                                         </select>
                                                         <x-form-label for="{{ ($preFixModel).'extention_discount_surcharge_type' }}" value="{{ __('Select Discount') }}"/>
                                                         <x-form-input-error name="{{ ($preFixModel).'extention_discount_surcharge_type' }}"/>
                                                     </div>
                                                 </div>
                                                 <div class="col-sm-2">
                                                     <div class="form-floating mb-3">
                                                         <input wire:model.defer="{{ ($preFixModel).'extention_discount_surcharge_value' }}" placeholder="Value" class="form-control amount-field" @blur="$el.value = window.formatAmount($el.value)" />
                                                         <x-form-label for="{{ ($preFixModel).'extention_discount_surcharge_value' }}" value="Discounted/Surcharge value" />
                                                         <x-form-input-error name="{{ ($preFixModel).'extention_discount_surcharge_value' }}"/>
                                                     </div>
                                                 </div>
                                                 <div class="col-sm-2">
                                                     <div class="form-floating mb-3">
                                                         <input wire:model.defer="{{ ($preFixModel).'extention_calculated_value' }}" @blur="$el.value = window.formatAmount($el.value)" placeholder="Premium" class="form-control amount-field" />
                                                         <x-form-label for="{{ ($preFixModel).'extention_calculated_value' }}" value="Premium" />
                                                         <x-form-input-error name="{{ ($preFixModel).'extention_calculated_value' }}"/>
                                                     </div>
                                                 </div>
                                             </div>
                                             @endforeach
                                         @endif

                                        {{-- For Burglar Alarm Warranty --}}

                                        @if(count($coverage['allBurglarAlarmWarranty']) > 0)
                                            <hr>
                                            <h4> Burglar Alarm Warranty</h4> <br>
                                            @foreach(($coverage['allBurglarAlarmWarranty']) as $ke => $subExtention)
                                                @php
                                                    $header = $subExtention['s_CoverageName'];
                                                    $preFixModel = 'policyExtentionDetail'.'.'.$policyCoverage->id.'.'.$subExtention->id.'.';
                                                    // for
                                                    $extentiontbValidoptions = AlphaDirect\Models\TbValidOptions::where('s_OptionType','EXT_LIMIT')->where('n_SourceOneFK',$subExtention->id)->where('s_SourceOneType',$subExtention->type)->pluck('n_SourceTwoFK');
                                                    $extentionData = AlphaDirect\Models\Extention::pluck('extention_type');
                                                    $extentionCvgpclimits = AlphaDirect\Models\TbCvgpcLimits::whereIn('s_LimitTypeCode',$extentionData)->whereIn('n_PCLimitId_PK',$extentiontbValidoptions)->get(['n_PCLimitId_PK','s_LimitTypeCode','s_LimitScreenName']);
                                                    $extentionLimitTypeCode = $extentionCvgpclimits[0]['s_LimitTypeCode'] ?? 0;
                                                    $extentionLimitScreenName = $extentionCvgpclimits->pluck('s_LimitScreenName','n_PCLimitId_PK')->toArray() ?? 0;
                                                    $extentionscreenName = null;
                                                    foreach ($extentionCvgpclimits as $extentionscreen_name){
                                                        $extentionscreenName = $extentionscreen_name->extentionLimitScreenName;
                                                    }
                                                @endphp
                                                <div class="row">
                                                    <div class="col-sm-5">
                                                        <div class="form-floating mb-3">
                                                            <div class="col-sm-6">
                                                                @if($extentionLimitTypeCode == 'DROPDOWN')
                                                                    <div class="form-floating mb-3">
                                                                        <select class="form-control" wire:model.defer="{{ ($preFixModel).'extention_limit_id' }}" id="{{ ($preFixModel).'extention_limit_id' }}" aria-label="Select Screen Name">
                                                                            <option value="">- Select -</option>
                                                                            @foreach ($extentionCvgpclimits as $data)
                                                                            <option value="{{ $data->n_PCLimitId_PK ?? '' }}">
                                                                            {{ $data->s_LimitScreenName ?? '' }}
                                                                            </option>
                                                                            @endforeach
                                                                        </select>
                                                                        <x-form-label for="{{ $extentionscreenName ?? '' }}" value="{{ $subExtention['s_ScreenName'] ?? '' }}" />
                                                                        <x-form-input-error name="{{ $extentionscreenName ?? '' }}"/>
                                                                    </div>
                                                                @elseif ($extentionLimitTypeCode == 'RADIO')

                                                                    <div class="form-floating mb-3">
                                                                        <label for="question 5">{{ $subExtention['s_ScreenName'] }}</label><br><br>
                                                                            @foreach ($extentionCvgpclimits as $pk => $radio)
                                                                            <div class="form-check form-check-inline" style="margin-left: 12px">
                                                                                <input class="form-check-input" type="radio" id="{{ ($preFixModel).'extention_limit_id' }}"
                                                                                    name="{{ ($preFixModel).'extention_limit_id' }}" value="{{ $radio->n_PCLimitId_PK ?? '' }}"
                                                                                        wire:model="{{ ($preFixModel).'extention_limit_id' }}">
                                                                                <label class="" for="inlineRadio1">{{ $radio->s_LimitScreenName }}</label>
                                                                            </div>
                                                                            @endforeach
                                                                    </div>
                                                                @elseif ($extentionLimitTypeCode == 'NUMBER')
                                                                    <div class="form-floating mb-3">
                                                                        <input title="{{ $subExtention['s_ScreenName'] }}" class="form-control" wire:model.defer="{{ ($preFixModel).'extention_coverage_value' }}"
                                                                            id="{{ ($preFixModel).'extention_coverage_value' }}" placeholder="{{ $subExtention['s_ScreenName'] }}"
                                                                            @blur="$el.value = window.formatAmount($el.value)" />
                                                                            <x-form-label for="{{ ($preFixModel).'extention_coverage_value' }}" value="{{ $subExtention['s_ScreenName'] }}" />
                                                                            <x-form-input-error name="{{ ($preFixModel).'extention_coverage_value' }}"/>
                                                                    </div>
                                                                @elseif ($extentionLimitTypeCode == 'NOEDIT')
                                                                    <div class="form-floating mb-3">
                                                                        <x-form-text-area title="{{ $subExtention['s_ScreenName'] }}" class="form-control" wire:model.defer="{{ ($preFixModel).'extention_text_value' }}"
                                                                            id="{{ ($preFixModel).'extention_text_value' }}" placeholder="{{ $subExtention['s_ScreenName'] }}"
                                                                            />
                                                                        <x-form-label for="{{ ($preFixModel).'extention_text_value' }}" value="{{ $subExtention['s_ScreenName'] }}" />
                                                                        <x-form-input-error name="{{ ($preFixModel).'extention_text_value' }}"/>
                                                                    </div>
                                                                @else
                                                                    <div class="form-floating mb-3">
                                                                        <x-form-text-area title="{{ $subExtention['s_ScreenName'] }}" class="form-control" wire:model.defer="{{ ($preFixModel).'extention_text_value' }}"
                                                                            id="{{ ($preFixModel).'extention_text_value' }}" placeholder="{{ $subExtention['s_ScreenName'] }}"
                                                                            />
                                                                        <x-form-label for="{{ ($preFixModel).'extention_text_value' }}" value="{{ $subExtention['s_ScreenName'] }}" />
                                                                        <x-form-input-error name="{{ ($preFixModel).'extention_text_value' }}"/>
                                                                    </div>
                                                                @endif
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="col-sm-2">
                                                        <div class="form-floating mb-3 ">
                                                            <select class='form-select form-select-solid' wire:model.defer="{{ ($preFixModel).'extention_discount_surcharge' }}" aria-label="Select Discount">
                                                                <option value="">- Select -</option>
                                                                <option value="Discount">Discount</option>
                                                                <option value="Surcharge">Surcharge</option>
                                                            </select>
                                                            <x-form-label for="{{ ($preFixModel).'extention_discount_surcharge' }}" value="{{ __('Select Discount') }}"/>
                                                            <x-form-input-error name="{{ ($preFixModel).'extention_discount_surcharge' }}"/>
                                                        </div>
                                                    </div>
                                                    <div class="col-sm-1">
                                                        <div class="form-floating mb-3">
                                                            <select class = 'form-select form-select-solid' wire:model.defer="{{ ($preFixModel).'extention_discount_surcharge_type' }}" aria-label="Select Percentage">
                                                                <option value="">- Select -</option>
                                                                <option value="Flat">Flat</option>
                                                                <option value="Percentage">Percentage</option>
                                                            </select>
                                                            <x-form-label for="{{ ($preFixModel).'extention_discount_surcharge_type' }}" value="{{ __('Select Discount') }}"/>
                                                            <x-form-input-error name="{{ ($preFixModel).'extention_discount_surcharge_type' }}"/>
                                                        </div>
                                                    </div>
                                                    <div class="col-sm-2">
                                                        <div class="form-floating mb-3">
                                                            <input wire:model.defer="{{ ($preFixModel).'extention_discount_surcharge_value' }}" placeholder="Value" class="form-control amount-field" @blur="$el.value = window.formatAmount($el.value)" />
                                                            <x-form-label for="{{ ($preFixModel).'extention_discount_surcharge_value' }}" value="Discounted/Surcharge value" />
                                                            <x-form-input-error name="{{ ($preFixModel).'extention_discount_surcharge_value' }}"/>
                                                        </div>
                                                    </div>
                                                    <div class="col-sm-2">
                                                        <div class="form-floating mb-3">
                                                            <input wire:model.defer="{{ ($preFixModel).'extention_calculated_value' }}" placeholder="Premium" class="form-control amount-field" />
                                                            <x-form-label for="{{ ($preFixModel).'extention_calculated_value' }}" value="Premium" />
                                                            <x-form-input-error name="{{ ($preFixModel).'extention_calculated_value' }}"/>
                                                        </div>
                                                    </div>
                                                </div>
                                            @endforeach
                                        @endif

                                        @php
                                            if(isset($policyCoverage->id)){
                                                $policyCoverageId = $policyCoverage->id;
                                            }else{
                                                $policyCoverageId = null;
                                            }

                                            if(isset($subExtention->id)){
                                                $policySubExtentionId = $subExtention->id;
                                            }else{
                                                $policySubExtentionId = null;
                                            }

                                        @endphp

                                        @if ($policyCoverage->coverage['s_CoverageCode'] == "OFFICECONTENTS" || $policyCoverage->coverage['s_CoverageCode'] == "HOUSEHOLDERS-CONTENTS" || $policyCoverage->coverage['s_CoverageCode'] == "THEFT" || $policyCoverage->coverage['s_CoverageCode'] == "HOUSEHOLDERS" || $policyCoverage->coverage['s_CoverageCode'] == "ELECTRONICEQUIPMENT" || $policyCoverage->coverage['s_CoverageCode'] == "MONEY" )
                                            @if(isset($this->policyExtentionDetail[$policyCoverageId][$policySubExtentionId]['extention_limit_id']) && $this->policyExtentionDetail[$policyCoverageId][$policySubExtentionId]['extention_limit_id'] == 2)
                                                <div class="row">
                                                    <div class="col-sm-12">
                                                        <div class="card bg-light shadow-sm">
                                                            <div class="card-header">
                                                                <h3 class="card-title">Burglar Alarm Warranty</h3>
                                                            </div>
                                                            <div class="card-body card-scroll h-200px">
                                                                <div class="form-floating" style="height: 120px">
                                                                    <x-form-text-area wire:model.defer="policyCoverageBurglarWarranty.{{ $policyCoverage->id }}" class="h-100"/>
                                                                    <label for="policyCoverageBurglarWarranty.{{ $policyCoverage->id }}">Enter Burglar Alarm Warranty</label>
                                                                    <x-form-input-error name="policyCoverageBurglarWarranty.{{ $policyCoverage->id }}"/>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            @endif
                                            <br>
                                        @endif

                                        {{-- Memoranda --}}
                                         @if(count($coverage['allMemoranda']) > 0)
                                            <hr>
                                            <h4> Memoranda</h4> <br>
                                            @foreach(($coverage['allMemoranda']) as $subExtention)
                                                @php
                                                    $header = $subExtention['s_CoverageName'];
                                                    $preFixModel = 'policyExtentionDetail'.'.'.$policyCoverage->id.'.'.$subExtention->id.'.';
                                                    // for
                                                    $extentiontbValidoptions = AlphaDirect\Models\TbValidOptions::where('s_OptionType','EXT_LIMIT')->where('n_SourceOneFK',$subExtention->id)->where('s_SourceOneType',$subExtention->type)->pluck('n_SourceTwoFK');
                                                    $extentionData = AlphaDirect\Models\Extention::pluck('extention_type');
                                                    $extentionCvgpclimits = AlphaDirect\Models\TbCvgpcLimits::whereIn('s_LimitTypeCode',$extentionData)->whereIn('n_PCLimitId_PK',$extentiontbValidoptions)->get(['n_PCLimitId_PK','s_LimitTypeCode','s_LimitScreenName']);
                                                    $extentionLimitTypeCode = $extentionCvgpclimits[0]['s_LimitTypeCode'] ?? 0;
                                                    $extentionLimitScreenName = $extentionCvgpclimits->pluck('s_LimitScreenName','n_PCLimitId_PK')->toArray() ?? 0;
                                                    $extentionscreenName = null;
                                                    foreach ($extentionCvgpclimits as $extentionscreen_name){
                                                        $extentionscreenName = $extentionscreen_name->extentionLimitScreenName;
                                                    }
                                                @endphp
                                                <div class="row">
                                                    <div class="col-sm-5">
                                                        <div class="form-floating mb-3">
                                                            <div class="col-sm-6">
                                                                @if($extentionLimitTypeCode == 'DROPDOWN')
                                                                    <div class="form-floating mb-3">
                                                                        <select class="form-control" wire:model.defer="{{ ($preFixModel).'extention_limit_id' }}" id="{{ ($preFixModel).'extention_limit_id' }}" aria-label="Select Screen Name">
                                                                            <option value="">- Select -</option>
                                                                            @foreach ($extentionCvgpclimits as $data)
                                                                            <option value="{{ $data->n_PCLimitId_PK ?? '' }}">
                                                                            {{ $data->s_LimitScreenName ?? '' }}
                                                                            </option>
                                                                            @endforeach
                                                                        </select>
                                                                        <x-form-label for="{{ $extentionscreenName ?? '' }}" value="{{ $subExtention['s_ScreenName'] ?? '' }}" />
                                                                        <x-form-input-error name="{{ $extentionscreenName ?? '' }}"/>
                                                                    </div>
                                                                @elseif ($extentionLimitTypeCode == 'RADIO')
                                                                    <div class="form-floating mb-3">
                                                                        <label for="question 6">{{ $subExtention['s_ScreenName'] }}</label><br><br>
                                                                            @foreach ($extentionCvgpclimits as $radio)
                                                                            <div class="form-check form-check-inline" style="margin-left: 12px">
                                                                                <input class="form-check-input" type="radio" id="{{ ($preFixModel).'extention_limit_id' }}" name="{{ ($preFixModel).'extention_limit_id' }}" value="{{ $radio->n_PCLimitId_PK ?? '' }}" wire:model.defer="{{ ($preFixModel).'extention_limit_id' }}">
                                                                                <label class="" for="inlineRadio1">{{ $radio->s_LimitScreenName }}</label>
                                                                            </div>
                                                                            @endforeach
                                                                    </div>
                                                                @elseif ($extentionLimitTypeCode == 'NUMBER')
                                                                    <div class="form-floating mb-3">
                                                                        <input title="{{ $subExtention['s_ScreenName'] }}" class="form-control" wire:model.defer="{{ ($preFixModel).'extention_coverage_value' }}"
                                                                            id="{{ ($preFixModel).'extention_coverage_value' }}" placeholder="{{ $subExtention['s_ScreenName'] }}"
                                                                            @blur="$el.value = window.formatAmount($el.value)" />
                                                                            <x-form-label for="{{ ($preFixModel).'extention_coverage_value' }}" value="{{ $subExtention['s_ScreenName'] }}" />
                                                                            <x-form-input-error name="{{ ($preFixModel).'extention_coverage_value' }}"/>
                                                                    </div>
                                                                @elseif ($extentionLimitTypeCode == 'NOEDIT')
                                                                    <div class="form-floating mb-3">
                                                                        <x-form-text-area title="{{ $subExtention['s_ScreenName'] }}" class="form-control" wire:model.defer="{{ ($preFixModel).'extention_text_value' }}"
                                                                            id="{{ ($preFixModel).'extention_text_value' }}" placeholder="{{ $subExtention['s_ScreenName'] }}"
                                                                            />
                                                                        <x-form-label for="{{ ($preFixModel).'extention_text_value' }}" value="{{ $subExtention['s_ScreenName'] }}" />
                                                                        <x-form-input-error name="{{ ($preFixModel).'extention_text_value' }}"/>
                                                                    </div>
                                                                @else
                                                                    <div class="form-floating mb-3">
                                                                        <x-form-text-area title="{{ $subExtention['s_ScreenName'] }}" class="form-control" wire:model.defer="{{ ($preFixModel).'extention_text_value' }}"
                                                                            id="{{ ($preFixModel).'extention_text_value' }}" placeholder="{{ $subExtention['s_ScreenName'] }}"
                                                                            />
                                                                        <x-form-label for="{{ ($preFixModel).'extention_text_value' }}" value="{{ $subExtention['s_ScreenName'] }}" />
                                                                        <x-form-input-error name="{{ ($preFixModel).'extention_text_value' }}"/>
                                                                    </div>
                                                                @endif
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-sm-2">
                                                        <div class="form-floating mb-3">
                                                            <select class='form-select form-select-solid' wire:model.defer="{{ ($preFixModel).'extention_discount_surcharge' }}" aria-label="Select Discount">
                                                                <option value="">- Select -</option>
                                                                <option value="Discount">Discount</option>
                                                                <option value="Surcharge">Surcharge</option>
                                                            </select>
                                                            <x-form-label for="{{ ($preFixModel).'extention_discount_surcharge' }}" value="{{ __('Select Discount') }}"/>
                                                            <x-form-input-error name="{{ ($preFixModel).'extention_discount_surcharge' }}"/>
                                                        </div>
                                                    </div>
                                                    <div class="col-sm-1">
                                                        <div class="form-floating mb-3">
                                                            <select class = 'form-select form-select-solid' wire:model.defer="{{ ($preFixModel).'extention_discount_surcharge_type' }}" aria-label="Select Percentage">
                                                                <option value="">- Select -</option>
                                                                <option value="Flat">Flat</option>
                                                                <option value="Percentage">Percentage</option>
                                                            </select>
                                                            <x-form-label for="{{ ($preFixModel).'extention_discount_surcharge_type' }}" value="{{ __('Select Discount') }}"/>
                                                            <x-form-input-error name="{{ ($preFixModel).'extention_discount_surcharge_type' }}"/>
                                                        </div>
                                                    </div>
                                                    <div class="col-sm-2">
                                                        <div class="form-floating mb-3">
                                                            <input wire:model.defer="{{ ($preFixModel).'extention_discount_surcharge_value' }}" placeholder="Value" class="form-control amount-field" @blur="$el.value = window.formatAmount($el.value)" />
                                                            <x-form-label for="{{ ($preFixModel).'extention_discount_surcharge_value' }}" value="Discounted/Surcharge value" />
                                                            <x-form-input-error name="{{ ($preFixModel).'extention_discount_surcharge_value' }}"/>
                                                        </div>
                                                    </div>
                                                    <div class="col-sm-2">
                                                        <div class="form-floating mb-3">
                                                            <input wire:model.defer="{{ ($preFixModel).'extention_calculated_value' }}" placeholder="Premium" class="form-control amount-field"  @blur="$el.value = window.formatAmount($el.value)"/>
                                                            <x-form-label for="{{ ($preFixModel).'extention_calculated_value' }}" value="Premium" />
                                                            <x-form-input-error name="{{ ($preFixModel).'extention_calculated_value' }}"/>
                                                        </div>
                                                    </div>
                                                </div>

                                            @endforeach
                                         @endif

                                        {{-- First Amount Payable --}}
                                        @if(count($coverage['allFirstAmountPayable']) > 0)
                                            <hr><h4> First Amount Payable</h4> <br>
                                            @foreach(($coverage['allFirstAmountPayable']) as $subExtention)
                                                @php
                                                    $header = $subExtention['s_CoverageName'];
                                                    $preFixModel = 'policyExtentionDetail'.'.'.$policyCoverage->id.'.'.$subExtention->id.'.';
                                                    // for
                                                    $extentiontbValidoptions = AlphaDirect\Models\TbValidOptions::where('s_OptionType','EXT_LIMIT')->where('n_SourceOneFK',$subExtention->id)->where('s_SourceOneType',$subExtention->type)->pluck('n_SourceTwoFK');
                                                    $extentionData = AlphaDirect\Models\Extention::pluck('extention_type');
                                                    $extentionCvgpclimits = AlphaDirect\Models\TbCvgpcLimits::whereIn('s_LimitTypeCode',$extentionData)->whereIn('n_PCLimitId_PK',$extentiontbValidoptions)->get(['n_PCLimitId_PK','s_LimitTypeCode','s_LimitScreenName']);
                                                    $extentionLimitTypeCode = $extentionCvgpclimits[0]['s_LimitTypeCode'] ?? 0;
                                                    $extentionLimitScreenName = $extentionCvgpclimits->pluck('s_LimitScreenName','n_PCLimitId_PK')->toArray() ?? 0;
                                                    $extentionscreenName = null;
                                                    foreach ($extentionCvgpclimits as $extentionscreen_name){
                                                        $extentionscreenName = $extentionscreen_name->extentionLimitScreenName;
                                                    }
                                                @endphp
                                                <div class="row">
                                                    <div class="col-sm-5">
                                                        <div class="form-floating mb-3">
                                                            <div class="col-sm-6">
                                                                @if($extentionLimitTypeCode == 'DROPDOWN')
                                                                    <div class="form-floating mb-3">
                                                                        <select class="form-control" wire:model.defer="{{ ($preFixModel).'extention_limit_id' }}" id="{{ ($preFixModel).'extention_limit_id' }}" aria-label="Select Screen Name">
                                                                            <option value="">- Select -</option>
                                                                            @foreach ($extentionCvgpclimits as $data)
                                                                            <option value="{{ $data->n_PCLimitId_PK ?? '' }}">
                                                                            {{ $data->s_LimitScreenName ?? '' }}
                                                                            </option>
                                                                            @endforeach
                                                                        </select>
                                                                        <x-form-label for="{{ $extentionscreenName ?? '' }}" value="{{ $subExtention['s_ScreenName'] ?? '' }}" />
                                                                        <x-form-input-error name="{{ $extentionscreenName ?? '' }}"/>
                                                                    </div>
                                                                @elseif ($extentionLimitTypeCode == 'RADIO')
                                                                    <div class="form-floating mb-3">
                                                                        <label for="question 7">{{ $subExtention['s_ScreenName'] }}</label><br><br>
                                                                            @foreach ($extentionCvgpclimits as $radio)
                                                                            <div class="form-check form-check-inline" style="margin-left: 12px">
                                                                                <input class="form-check-input" type="radio" id="{{ ($preFixModel).'extention_limit_id' }}" name="{{ ($preFixModel).'extention_limit_id' }}" value="{{ $radio->n_PCLimitId_PK ?? '' }}" wire:model.defer="{{ ($preFixModel).'extention_limit_id' }}">
                                                                                <label class="" for="inlineRadio1">{{ $radio->s_LimitScreenName }}</label>
                                                                            </div>
                                                                            @endforeach
                                                                    </div>
                                                                @elseif ($extentionLimitTypeCode == 'NUMBER')
                                                                    <div class="form-floating mb-3">
                                                                        <input title="{{ $subExtention['s_ScreenName'] }}" class="form-control" wire:model.defer="{{ ($preFixModel).'extention_coverage_value' }}"
                                                                            id="{{ ($preFixModel).'extention_coverage_value' }}" placeholder="{{ $subExtention['s_ScreenName'] }}"
                                                                            @blur="$el.value = window.formatAmount($el.value)" />
                                                                            <x-form-label for="{{ ($preFixModel).'extention_coverage_value' }}" value="{{ $subExtention['s_ScreenName'] }}" />
                                                                            <x-form-input-error name="{{ ($preFixModel).'extention_coverage_value' }}"/>
                                                                    </div>
                                                                @elseif ($extentionLimitTypeCode == 'NOEDIT')
                                                                    <div class="form-floating mb-3">
                                                                        <x-form-text-area title="{{ $subExtention['s_ScreenName'] }}" class="form-control" wire:model.defer="{{ ($preFixModel).'extention_text_value' }}"
                                                                            id="{{ ($preFixModel).'extention_text_value' }}" placeholder="{{ $subExtention['s_ScreenName'] }}"
                                                                            />
                                                                        <x-form-label for="{{ ($preFixModel).'extention_text_value' }}" value="{{ $subExtention['s_ScreenName'] }}" />
                                                                        <x-form-input-error name="{{ ($preFixModel).'extention_text_value' }}"/>
                                                                    </div>
                                                                @else
                                                                    <div class="form-floating mb-3">
                                                                        <x-form-text-area title="{{ $subExtention['s_ScreenName'] }}" class="form-control" wire:model.defer="{{ ($preFixModel).'extention_text_value' }}"
                                                                            id="{{ ($preFixModel).'extention_text_value' }}" placeholder="{{ $subExtention['s_ScreenName'] }}"
                                                                            />
                                                                        <x-form-label for="{{ ($preFixModel).'extention_text_value' }}" value="{{ $subExtention['s_ScreenName'] }}" />
                                                                        <x-form-input-error name="{{ ($preFixModel).'extention_text_value' }}"/>
                                                                    </div>
                                                                @endif
                                                                <div class="col-sm-12" style="margin-left: 104%;margin-top: -36%;">
                                                                    <div class="form-floating mb-3">
                                                                        <input type="text" class="form-control" wire:model.defer="{{ ($preFixModel).'extention_excess_min_value' }}"
                                                                            id="{{ ($preFixModel).'extention_excess_min_value' }}" placeholder="{{ $subExtention['s_ScreenName'] }}"
                                                                            />
                                                                            <span style="color:red">Please Enter Number Only without % sign</span>
                                                                        <x-form-label for="{{ ($preFixModel).'extention_excess_min_value' }}" value="Min %" />
                                                                        <x-form-input-error name="{{ ($preFixModel).'extention_excess_min_value' }}"/>
                                                                    </div>
                                                                    <div class="form-floating mb-3">
                                                                        <input class="form-control" wire:model.defer="{{ ($preFixModel).'extention_excess_max_value' }}"
                                                                            id="{{ ($preFixModel).'extention_excess_max_value' }}" placeholder="{{ $subExtention['s_ScreenName'] }}"
                                                                            @blur="$el.value = window.formatAmount($el.value)" />
                                                                        <x-form-label for="{{ ($preFixModel).'extention_excess_max_value' }}" value="Minimum Amount" />
                                                                        <x-form-input-error name="{{ ($preFixModel).'extention_excess_max_value' }}"/>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-sm-2">
                                                        <div class="form-floating mb-3">
                                                            <select class='form-select form-select-solid' wire:model.defer="{{ ($preFixModel).'extention_discount_surcharge' }}" aria-label="Select Discount">
                                                                <option value="">- Select -</option>
                                                                <option value="Discount">Discount</option>
                                                                <option value="Surcharge">Surcharge</option>
                                                            </select>
                                                            <x-form-label for="{{ ($preFixModel).'extention_discount_surcharge' }}" value="{{ __('Select Discount') }}"/>
                                                            <x-form-input-error name="{{ ($preFixModel).'extention_discount_surcharge' }}"/>
                                                        </div>
                                                    </div>
                                                    <div class="col-sm-1">
                                                        <div class="form-floating mb-3">
                                                            <select class = 'form-select form-select-solid' wire:model.defer="{{ ($preFixModel).'extention_discount_surcharge_type' }}" aria-label="Select Percentage">
                                                                <option value="">- Select -</option>
                                                                <option value="Flat">Flat</option>
                                                                <option value="Percentage">Percentage</option>
                                                            </select>
                                                            <x-form-label for="{{ ($preFixModel).'extention_discount_surcharge_type' }}" value="{{ __('Select Discount') }}"/>
                                                            <x-form-input-error name="{{ ($preFixModel).'extention_discount_surcharge_type' }}"/>
                                                        </div>
                                                    </div>
                                                    <div class="col-sm-2">
                                                        <div class="form-floating mb-3">
                                                            <input wire:model.defer="{{ ($preFixModel).'extention_discount_surcharge_value' }}" placeholder="Value" class="form-control amount-field" @blur="$el.value = window.formatAmount($el.value)" />
                                                            <x-form-label for="{{ ($preFixModel).'extention_discount_surcharge_value' }}" value="Discounted/Surcharge value" />
                                                            <x-form-input-error name="{{ ($preFixModel).'extention_discount_surcharge_value' }}"/>
                                                        </div>
                                                    </div>
                                                    <div class="col-sm-2">
                                                        <div class="form-floating mb-3">
                                                            <input wire:model.defer="{{ ($preFixModel).'extention_calculated_value' }}" placeholder="Premium" class="form-control amount-field"  @blur="$el.value = window.formatAmount($el.value)"/>
                                                            <x-form-label for="{{ ($preFixModel).'extention_calculated_value' }}" value="Premium" />
                                                            <x-form-input-error name="{{ ($preFixModel).'extention_calculated_value' }}"/>
                                                        </div>
                                                    </div>
                                                </div>
                                            @endforeach
                                        @endif

                                        @if ($policyCoverage->coverage['s_CoverageCode'] == "MONEY")
                                        <div class="row">
                                            <div class="col-sm-12">
                                                <div class="card bg-light shadow-sm">
                                                    <div class="card-header">
                                                        <h3 class="card-title">Memoranda and Warranties</h3>
                                                    </div>
                                                    <div class="card-body card-scroll h-200px">
                                                        <div class="form-floating">
                                                            <x-form-text-area wire:model.defer="policyCoverageMemorandaWarranty.{{ $policyCoverage->id }}" class="h-100" style="height: 15em !important;"/>
                                                            <label for="policyCoverageMemorandaWarranty.{{ $policyCoverage->id }}">Enter Memoranda and Warranties</label>
                                                            <x-form-input-error name="policyCoverageMemorandaWarranty.{{ $policyCoverage->id }}"/>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <br>
                                        <div class="row">
                                            <div class="col-sm-12">
                                                <div class="card bg-light shadow-sm">
                                                    <div class="card-header">
                                                        <h3 class="card-title">Cash Carrying Warranty</h3>
                                                    </div>
                                                    <div class="card-body card-scroll h-200px">
                                                        <div class="form-floating">
                                                            <x-form-text-area wire:model.defer="policyCoverageCashWarranty.{{ $policyCoverage->id }}" class="h-100" style="height: 15em !important;"/>
                                                            <label for="policyCoverageCashWarranty.{{ $policyCoverage->id }}">Enter Cash Carrying Warranty</label>
                                                            <x-form-input-error name="policyCoverageCashWarranty.{{ $policyCoverage->id }}"/>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <br><br>
                                        @endif

                                        @if ($policyCoverage->coverage['s_CoverageCode'] == "MOTORTHIRDPARTYFIREANDTHE" || $policyCoverage->coverage['s_CoverageCode'] == "MOTORTHIRDPARTYONLY")
                                        <div class="row">
                                            <div class="col-sm-12">
                                                <div class="card bg-light shadow-sm">
                                                    <div class="card-header">
                                                        <h3 class="card-title">Endorsements</h3>
                                                    </div>
                                                    <div class="card-body card-scroll h-200px">
                                                        <div class="form-floating">
                                                            <x-form-text-area wire:model.defer="policyCoverageEndorsements.{{ $policyCoverage->id }}" class="h-100"  />
                                                            <label for="policyCoverageEndorsements.{{ $policyCoverage->id }}">Enter Endorsements</label>
                                                            <x-form-input-error name="policyCoverageEndorsements.{{ $policyCoverage->id }}"/>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <br>
                                        @endif



                                        {{-- CONTRACTORSALLRISKS (CAR) Coverage --}}
                                        @if ($policyCoverage->coverage['s_CoverageCode'] == "CONTRACTORSALLRISKS" || $policyCoverage->coverage['s_CoverageCode'] == "CAR")
                                            @include('v2.livewire.policy.car-coverage')
                                        @endif

                                        {{-- PLANTALLRISKS (PAR) Coverage --}}
                                        @if ($policyCoverage->coverage['s_CoverageCode'] == "PLANTALLRISKS" || $policyCoverage->coverage['s_CoverageCode'] == "PAR")
                                            @include('v2.livewire.policy.par-coverage')
                                        @endif

                                        {{-- ERECTIONALLRISKSs (EAR) Coverage --}}
                                        @if ($policyCoverage->coverage['s_CoverageCode'] == "ERECTIONALLRISKS" || $policyCoverage->coverage['s_CoverageCode'] == "EAR")
                                            @include('v2.livewire.policy.ear-coverage')
                                        @endif

                                        {{-- TRAVEL INSURANCE Coverage --}}
                                        @if ($policyCoverage->coverage['s_CoverageCode'] == "TRAVEL" || $policyCoverage->coverage['s_CoverageCode'] == "TRAVELINSURANCE")
                                            @include('v2.livewire.policy.travel-coverage')
                                        @endif
                                        {{-- PROFESSIONAL INDEMNITY Coverage --}}
                                        @if (strtoupper($policyCoverage->coverage['s_CoverageCode']) == "PROFESSIONALINDEMNITY" || strtoupper($policyCoverage->coverage['s_CoverageCode']) == "PROFESSIONAL_INDEMNITY" || strtoupper($policyCoverage->coverage['s_CoverageCode']) == "PI")
                                            @include('v2.livewire.policy.professional-indemnity')
                                        @endif

                                        {{-- MEDICAL MALPRACTICE Coverage --}}
                                        @if (strtoupper($policyCoverage->coverage['s_CoverageCode']) == "MEDICAMALPRACTICEINSURANCE" || strtoupper($policyCoverage->coverage['s_CoverageCode']) == "MEDICAMALPRACTICEINSURANCE" || strtoupper($policyCoverage->coverage['s_CoverageCode']) == "MM")
                                            @include('v2.livewire.policy.medical-malpractice')
                                        @endif

                                        {{-- DIRECTORS & OFFICERS LIABILITY Coverage --}}
                                        @if (strtoupper($policyCoverage->coverage['s_CoverageCode']) == "DIRECTORSOFFICERSLIABILITY")
                                            @include('v2.livewire.policy.directors-officers-liability')
                                        @endif

                                        {{-- MARINE ONCE-OFF COVER (matches both new MARINEONCEOFFCOVER and existing MARINECARGOONCEOFF codes) --}}
                                        @if (strtoupper($policyCoverage->coverage['s_CoverageCode']) == "MARINEONCEOFFCOVER" || strtoupper($policyCoverage->coverage['s_CoverageCode']) == "MARINECARGOONCEOFF")
                                            @include('v2.livewire.policy.marine-onceoff-cover')
                                        @endif

                                        {{-- MARINE OPEN COVER --}}
                                        @if (strtoupper($policyCoverage->coverage['s_CoverageCode']) == "MARINEOPENCOVER")
                                            @include('v2.livewire.policy.marine-open-cover')
                                        @endif

                                        {{-- MARINE DIRECTORS & OFFICERS --}}
                                        @if (strtoupper($policyCoverage->coverage['s_CoverageCode']) == "MARINEDIRECTORSOFFICERS")
                                            @include('v2.livewire.policy.marine-directors-officers')
                                        @endif

                                        @if ($policyCoverage->coverage['s_CoverageCode'] == "WORKERSCOMPENSATION" || $policyCoverage->coverage['s_CoverageCode'] == "STATEDBENEFITS")
                                        <div class="row">
                                            <div class="col-sm-12">
                                                <div class="card bg-light shadow-sm">
                                                    <div class="card-header">
                                                        <h3 class="card-title">Benefits for the circumstances</h3>
                                                    </div>
                                                    <div class="card-body card-scroll h-200px">
                                                        <div class="form-floating">
                                                            <x-form-text-area wire:model.defer="policyCoverageBenefits.{{ $policyCoverage->id }}" class="h-100" style="height: 15em !important;"/>
                                                            <label for="policyCoverageBenefits.{{ $policyCoverage->id }}">Enter Your Benefits for the circumstances</label>
                                                            <x-form-input-error name="policyCoverageBenefits.{{ $policyCoverage->id }}"/>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        @endif
                                        @if($policyCoverage->coverage['s_CoverageCode'] != "PERSONALMOTOR" &&
                                            $policyCoverage->coverage['s_CoverageCode'] != "COMMERCIALMOTOR" &&
                                            $policyCoverage->coverage['s_CoverageCode'] != "CONTRACTORSALLRISKS" &&
                                            $policyCoverage->coverage['s_CoverageCode'] != "CAR" &&
                                            $policyCoverage->coverage['s_CoverageCode'] != "PLANTALLRISKS" &&
                                            $policyCoverage->coverage['s_CoverageCode'] != "PAR" &&
                                            $policyCoverage->coverage['s_CoverageCode'] != "ERECTIONALLRISKS" &&
                                            $policyCoverage->coverage['s_CoverageCode'] != "EAR" &&
                                            $policyCoverage->coverage['s_CoverageCode'] != "TRAVEL" &&
                                            $policyCoverage->coverage['s_CoverageCode'] != "TRAVELINSURANCE" &&
                                            $policyCoverage->coverage['s_CoverageCode'] != "MEDICAMALPRACTICEINSURANCE" &&
                                            $policyCoverage->coverage['s_CoverageCode'] != "PROFESSIONALINDEMNITY" &&
                                            strtoupper($policyCoverage->coverage['s_CoverageCode']) != "DIRECTORSOFFICERSLIABILITY" &&
                                            strtoupper($policyCoverage->coverage['s_CoverageCode']) != "MARINEONCEOFFCOVER" &&
                                            strtoupper($policyCoverage->coverage['s_CoverageCode']) != "MARINECARGOONCEOFF" &&
                                            strtoupper($policyCoverage->coverage['s_CoverageCode']) != "MARINEOPENCOVER" &&
                                            strtoupper($policyCoverage->coverage['s_CoverageCode']) != "MARINEDIRECTORSOFFICERS")
                                        <div class="row">
                                            <div class="col-sm-12">
                                                <div class="card bg-light shadow-sm">
                                                    <div class="card-header">
                                                        <h3 class="card-title">Note</h3>
                                                    </div>
                                                    <div class="card-body card-scroll h-200px">
                                                        <div class="form-floating">
                                                            <x-form-text-area wire:model.defer="policyCoverageNote.{{ $policyCoverage->id }}" class="h-100"  style="height: 15em !important;"/>
                                                            <label for="policyCoverageNote.{{ $policyCoverage->id }}">Enter Your Note</label>
                                                            <x-form-input-error name="policyCoverageNote.{{ $policyCoverage->id }}"/>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        @endif
                                        <!-- DEBUG: Coverage Code = {{ $policyCoverage->coverage['s_CoverageCode'] ?? 'NULL' }}, Coverage ID = {{ $policyCoverage->coverage_id ?? 'NULL' }}, Exact Match: {{ ($policyCoverage->coverage['s_CoverageCode'] ?? '') === 'THEFT' ? 'YES' : 'NO' }} -->
                                        @if ($policyCoverage->coverage['s_CoverageCode'] == "THEFT")
                                            <br><br>
                                            <div class="row">
                                                <div class="col-sm-12">
                                                    <div class="card bg-light shadow-sm">
                                                        <div class="card-header">
                                                            <h3 class="card-title">General Questions</h3>
                                                        </div>
                                                        <div class="card-body card-scroll">
                                                            <div class="form-floating">
                                                                <label for="question">1. What physical protections have been implemented to protect the premises and their contents from theft?</label><br><br>
                                                                <div class="form-check form-check-inline">
                                                                    <select class = 'form-select' wire:model.defer="theft.physical_protection_implemented" aria-label="Select">
                                                                        <option value="">- Select -</option>
                                                                        <option value="Perimeter Fence">Perimeter Fence</option>
                                                                        <option value="Armed Security Guards">Armed Security Guards</option>
                                                                        <option value="Other">Other</option>
                                                                    </select>
                                                                </div>
                                                            </div>
                                                            <div class="form-floating">
                                                                <label for="question">2. Are the premises alarmed?</label><br><br>
                                                                <div class="form-check form-check-inline" style="margin-left: 12px">
                                                                    <input wire:model.defer="theft.premises_alarmed" id="" name="theft.premises_alarmed" value="Yes" type="radio" class="form-check-input" />
                                                                    <label for="">Yes</label>
                                                                </div>
                                                                <div class="form-check form-check-inline" style="margin-left: 2px">
                                                                    <input wire:model.defer="theft.premises_alarmed" id="" name="theft.premises_alarmed" value="No" type="radio" class="form-check-input" />
                                                                    <label for="">No</label>
                                                                </div>
                                                            </div>
                                                            <div class="form-floating">
                                                                <label for="question">3. If yes, do you subscribe to an armed response or security company?</label><br><br>
                                                                <div class="form-check form-check-inline" style="margin-left: 12px">
                                                                    <input wire:model.defer="theft.subscribe_armed_security" id="" name="theft.subscribe_armed_security" value="Yes" type="radio" class="form-check-input" />
                                                                    <label for="">Yes</label>
                                                                </div>
                                                                <div class="form-check form-check-inline" style="margin-left: 2px">
                                                                    <input wire:model.defer="theft.subscribe_armed_security" id="" name="theft.subscribe_armed_security" value="No" type="radio" class="form-check-input" />
                                                                    <label for="">No</label>
                                                                </div>
                                                            </div>
                                                            <div class="form-floating" style="margin-left: 20px">
                                                                <label for="question">3.1 Name of company</label><br><br>
                                                                <div class="form-check form-check-inline">
                                                                    <input type="text" wire:model.defer="property_business_being" value="" placeholder="Free Text" class="form-control">
                                                                    {{-- <select class = 'form-select' wire:model.defer="theft.security_company" aria-label="Select">
                                                                        <option value="">Select Security Company</option>
                                                                        <option value="G4S Botswana">G4S Botswana</option>
                                                                        <option value="Security Systems">Security Systems</option>
                                                                        <option value="Security Services">Security Services</option>
                                                                        <option value="Savuti Security Services">Savuti Security Services</option>
                                                                    </select> --}}
                                                                </div>
                                                            </div>
                                                            <div class="form-floating">
                                                                <label for="question">4. Do you have a maintenance contract with this company?</label><br><br>
                                                                <div class="form-check form-check-inline" style="margin-left: 12px">
                                                                    <input wire:model.defer="theft.maintenance_contract" id="" name="theft.maintenance_contract" value="Yes" type="radio" class="form-check-input" />
                                                                    <label for="">Yes</label>
                                                                </div>
                                                                <div class="form-check form-check-inline" style="margin-left: 2px">
                                                                    <input wire:model.defer="theft.maintenance_contract" id="" name="theft.maintenance_contract" value="No" type="radio" class="form-check-input" />
                                                                    <label for="">No</label>
                                                                </div>
                                                            </div>
                                                            <div class="form-floating">
                                                                <label for="question">5. When was the alarmed installed?</label><br><br>
                                                                <div class="form-check form-check-inline">
                                                                    <input type="date" class="form-control" id="alarmed_installed_date"  wire:model.defer="theft.alarmed_installed_date" name="theft.alarmed_installed_date"  placeholder="Alarmed Installed Date"/>
                                                                </div>
                                                            </div>
                                                            <div class="form-floating">
                                                                <label for="question">6. Are opening and closing signals monitored?</label><br><br>
                                                                <div class="form-check form-check-inline" style="margin-left: 12px">
                                                                    <input wire:model.defer="theft.opening_closing_signals" id="" name="theft.opening_closing_signals" value="Yes" type="radio" class="form-check-input" />
                                                                    <label for="">Yes</label>
                                                                </div>
                                                                <div class="form-check form-check-inline" style="margin-left: 2px">
                                                                    <input wire:model.defer="theft.opening_closing_signals" id="" name="theft.opening_closing_signals" value="No" type="radio" class="form-check-input" />
                                                                    <label for="">No</label>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        @endif

                                        <br><br>
                                         @if($policyCoverage->coverage['s_CoverageCode'] != "MEDICAMALPRACTICEINSURANCE" &&  $policyCoverage->coverage['s_CoverageCode'] != "PERSONALMOTOR" && $policyCoverage->coverage['s_CoverageCode'] != "COMMERCIALMOTOR" && $policyCoverage->coverage['s_CoverageCode'] != "PROFESSIONALINDEMNITY" && strtoupper($policyCoverage->coverage['s_CoverageCode']) != "MARINEDIRECTORSOFFICERS")
                                         @if(isset($this->specifiedItems[$coverage['id']]))
                                        <div class="row">
                                                <div class="col-sm-12">
                                                    <div class="card bg-light shadow-sm">
                                                        <div class="card-header">
                                                            <h3 class="card-title">Miscellaneous Items</h3>
                                                            <div class="card-toolbar">
                                                                <button type="button" class="btn btn-sm btn-primary" wire:click.prevent="addSpecifiedRow({{ $policyCoverage->id }})">
                                                                    Add <Row></Row>
                                                                </button>
                                                            </div>
                                                        </div>
                                                        <div class="card-body card-scroll h-200px">
                                                            @foreach($this->specifiedRow[$policyCoverage->id] ?? [] as $key => $value)
                                                                <div class="row">
                                                                    <div class="col-sm-5">
                                                                        <div class="form-floating mb-3">
                                                                            <x-select wire:model.defer="specified_items.{{ $policyCoverage->id }}.{{ $key }}.selected_item"
                                                                                      :options="$this->specifiedItems[$policyCoverage->coverage_id]" />
                                                                            <x-form-label for="specified_items.{{ $policyCoverage->id }}.{{ $key }}.selected_item" required value="{{ __('Select Description Of Item') }}"/>
                                                                            <x-form-input-error name="specified_items.{{ $policyCoverage->id }}.{{ $key }}.selected_item"/>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-5">
                                                                        <div class="form-floating mb-3">
                                                                            <x-form-input wire:model.defer="specified_items.{{ $policyCoverage->id }}.{{ $key }}.sum_insured"
                                                                                          placeholder="Sum Insured" class="amount-field" @blur="$el.value = window.formatAmount($el.value)"/>
                                                                            <x-form-label for="specified_items.{{ $policyCoverage->id }}.{{ $key }}.sum_insured" required value="{{ __('Sum Insured') }}"/>
                                                                            <x-form-input-error name="specified_items.{{ $policyCoverage->id }}.{{ $key }}.sum_insured"/>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-2">
                                                                       @if( isset($policyCoverage->specifedItems[$key])
                                                                                            && $policyCoverage->specifedItems[$key]->deleted_at === null)
                                                                                            <button type="button" class="btn btn-danger btn-small"
                                                                                                wire:click.prevent="removeRow(
                                                                                                    {{ $policyCoverage->id }},
                                                                                                    {{ $key }}
                                                                                                    @isset($policyCoverage->specifedItems[$key]->id),{{ $policyCoverage->specifedItems[$key]->id }}@endisset
                                                                                                )">
                                                                                                &times;
                                                                                            </button>
                                                                                              @elseif(!isset($policyCoverage->specifedItems[$key]))
                                                                                               <button type="button" class="btn btn-danger btn-small" wire:click.prevent="removeRow({{ $policyCoverage->id }},{{ $key }})">&times;</button>
                                                                                   
                                                                                        @else
                                                                                            <a wire:click.prevent="reinstateMisc('{{ $policyCoverage->specifedItems[$key]->id ?? '' }}')"
                                                                                            class="btn btn-sm btn-primary"
                                                                                            style="position: absolute; right: 75px;">
                                                                                            Reinstate
                                                                                            </a>
                                                                                        @endif  </div>
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                </div>
                                        </div>
                                        @endif
                                        @endif
                                    </div>
                                </div>@endif
                            </div>
                        </div>
                @endforeach
                {{-- Do not comment this code before commenting ask to nidhi --}}
                @php
                    if (count($this->PolicyCoverages)){
                        echo "</div></div></div></div>";
                    }
                @endphp

            </div>
            @php
                $code = $policyCoverage->coverage['s_CoverageCode'] ?? '';
            @endphp
            @if(!in_array($code, ['MEDICAMALPRACTICEINSURANCE','PROFESSIONALINDEMNITY']))
                @if(!$editmode)
                    <div class="row">
                        <div class="col-sm-6">
                            <button type="button" wire:loading.attr="disabled" wire:offline.attr="disabled" wire:click.prevent="backToStep2" class="btn btn-danger hover-rotate-end" style="float:left;">
                                <span>Previous</span>
                                <span wire:loading wire:target="backToStep2" class="indicator-progress">
                                <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                            </span>
                            </button>
                        </div>
                        <div class="col-sm-6">
                            <button type="submit"  wire:loading.attr="disabled" wire:offline.attr="disabled" class="btn btn-success hover-rotate-end" style="float:right;margin-right:15px;">
                                <span>Save & Continue</span>
                            </button>
                        </div>
                    </div>
                @else
                    <div class="row">
                        <div class="row mt-4 justify-content-md-center">
                            <div class="col-sm-3">
                                <input type="submit" class="btn btn-primary" value="Submit">
                            </div>
                        </div>
                    </div>
                @endif
            @endif

        </form>

        <div x-show="Modaltitle!='Modal'">
            <div class="modal fade" id="kt_modal_create_campaign" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-fullscreen" style="max-width:87%">
                    <div class="modal-content modal-rounded">
                        <div class="modal-header py-7 d-flex justify-content-between">
                            <h2 x-text="Modaltitle"></h2>
                            <div class="btn btn-sm btn-icon btn-active-color-primary" data-bs-dismiss="modal" wire:click="$emitUp('refreshParent',{{ $actionId }})">
                                        <span class="svg-icon svg-icon-1">
                                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1" transform="rotate(-45 6 17.3137)" fill="currentColor" />
                                                <rect x="7.41422" y="6" width="16" height="2" rx="1" transform="rotate(45 7.41422 6)" fill="currentColor" />
                                            </svg>
                                        </span>
                            </div>
                        </div>
                        <div class="modal-body scroll-y">
                            <div x-show="Modaltitle=='Device'">
                                @livewire('policy.add-device', ['policy' => $policy,'isPrevious'=>false,'termId'=>$termId,'actionId'=>$actionId])
                            </div>
                            <div x-show="Modaltitle=='Member'">
                                @livewire('policy.add-member', ['policy' => $policy,'isPrevious'=>false,'termId'=>$termId,'actionId'=>$actionId])
                            </div>
                            <div x-show="Modaltitle=='RiskAddress'">
                                @livewire('policy.add-risk-address', ['policy' => $policy,'isPrevious'=>false,'inSide' => 'coverage','termId'=>$termId,'actionId'=>$actionId])
                            </div>
                            <div x-show="Modaltitle=='Vehicle'">
                                @livewire('policy.add-vehicle', ['policy' => $policy,'isPrevious'=>false,'termId'=>$termId,'actionId'=>$actionId])
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="{{  asset('assets/vendors/general/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}" type="text/javascript"></script>
<script src="{{  asset('assets/vendors/custom/components/vendors/bootstrap-datepicker/init.js') }}" type="text/javascript"></script>
<script>
    $(document).ready(function () {

        var KTBootstrapDatepicker = function () {
            var arrows;
            if (KTUtil.isRTL()) {
                arrows = {
                    leftArrow: '<i class="la la-angle-right"></i>',
                    rightArrow: '<i class="la la-angle-left"></i>'
                }
            } else {
                arrows = {
                    leftArrow: '<i class="la la-angle-left"></i>',
                    rightArrow: '<i class="la la-angle-right"></i>'
                }
            }
            // Private functions
            var demos = function () {
                // minimum setup
                $('.maskdate').datepicker({
                    rtl: KTUtil.isRTL(),
                    todayHighlight: true,
                    orientation: "bottom left",
                    templates: arrows,
                    format: 'yyyy-mm-dd'
                });
            }
            return {
                // public functions
                init: function() {
                    demos();
                }
            };
        }();
        jQuery(document).ready(function() {
            KTBootstrapDatepicker.init();
        });
    });
</script>


<script type="text/javascript">
    $(document).ready(function(){
		// Get references to the input elements
		var input1 = document.getElementById('input1');
		var input2 = document.getElementById('input2');
		// Add an event listener to input1 to detect changes
		input1.addEventListener('input', function() {
			// Reflect the value of input1 in input2
			input2.value = input1.value;
		});
    });
</script>
<script type="text/javascript">
    $(document).ready(function(){
		var input3 = document.getElementById('input3');
		var input4 = document.getElementById('input4');
		input1.addEventListener('input', function() {
			input4.value = input3.value;
		});

        $(".newtext").hover(function(){
            alert("The paragraph was clicked.");
        });
    });
    // function addCommas(x) {
    //     var parts = x.toString().split(".");
    //     parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    //     return parts.join(".");
    // }

    // $('#num').keypress(function(){
    //     var numberTyped=$("#num").val();
    //     var convertedNum=addCommas(numberTyped);
    //     $("#num").val(convertedNum);
    // });
    // $(".hideBusiExcessesFidelityGuarantee").on("click",function(){
    //     var rowId = $(this).attr('data-rowId');
    //     $("#" + rowId).hide();
    //     location.reload();
    // });
    // $(".removeDBExcessesFidelityGuarantee").on("click",function(){
    //     var rowId = $(this).attr('data-rowId');
    //     $("#" + rowId).hide();
    //     location.reload();
    // });
    // $(".removeBusiExcessesFidelityGuarantee").on("click",function(){
    //     var rowId = $(this).attr('data-rowId');
    //     $("#" + rowId).hide();
    //     location.reload();
    // });

    // Shared formatter for amount inputs
    // function formatAmountInput(el) {
    //     if (!el) return;
    //     let rawValue = (el.value || "").toString().replace(/,/g, "");
    //     if (!rawValue) {
    //         el.value = "";
    //         return;
    //     }
    //     // keep only digits and a single dot
    //     rawValue = rawValue.replace(/[^0-9.]/g, "");
    //     const parts = rawValue.split(".");
    //     parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    //     el.value = parts.length > 1 ? `${parts[0]}.${parts[1]}` : parts[0];
    // }
    // function applyAmountFormatting() {
    //     document.querySelectorAll(".amount-field").forEach(formatAmountInput);
    // }
    // // Allow decimals when typing into amount fields
    // document.addEventListener("keydown", function (e) {
    //     if (!e.target.classList.contains("amount-field")) {
    //         return;
    //     }
    //     const allowedControlKeys = ["Backspace", "Delete", "ArrowLeft", "ArrowRight", "Tab", "Home", "End"];
    //     if (allowedControlKeys.includes(e.key)) {
    //         return;
    //     }
    //     // Permit a single decimal point
    //     if (e.key === ".") {
    //         if (e.target.value.includes(".")) {
    //             e.preventDefault();
    //         }
    //         return;
    //     }
    //     // Block anything that is not a digit
    //     if (!/^\d$/.test(e.key)) {
    //         e.preventDefault();
    //     }
    // });
    // document.addEventListener("input", function (e) {
    //     if (!e.target.classList.contains("amount-field")) {
    //         return;
    //     }
    //     formatAmountInput(e.target);
    // });
    // document.addEventListener("livewire:load", () => {
    //     formatAllAmountFields();

    //     // Reformat again after Livewire updates DOM
    //     Livewire.hook('message.processed', () => {
    //         formatAllAmountFields();
    //     });
    // });

    // function formatAllAmountFields() {
    //     document.querySelectorAll(".amount-field").forEach(el => {
    //         el.value = formatValueKeepingDecimal(el.value);
    //     });
    // }

    // function stripCommas(value) {
    //     return (value || "").toString().replace(/,/g, "");
    // }

    // function formatIntegerPart(intPart) {
    //     return intPart.replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    // }

    // function formatValueKeepingDecimal(raw = "") {
    //     raw = stripCommas(raw);
    //     if (raw === "") return "";

    //     // keep digits + single dot
    //     raw = raw.replace(/[^0-9.]/g, "");
    //     const parts = raw.split(".");

    //     const intPart = parts[0];
    //     const fracPart = parts[1] ? parts.slice(1).join("") : "";

    //     const formattedInt = formatIntegerPart(intPart);

    //     return fracPart ? `${formattedInt}.${fracPart}` : formattedInt;
    // }

    function formatAmountInput(el) {
    if (!el) return;

    let rawValue = (el.value || "").toString().replace(/,/g, "");

    // If empty, skip
    if (!rawValue) {
        el.value = "";
        return;
    }

    // Validate number (digits + optional one decimal)
    if (!/^\d*\.?\d*$/.test(rawValue)) {
        return; // ❌ Not a number → do NOT format
    }

    // keep digits and a single dot
    rawValue = rawValue.replace(/[^0-9.]/g, "");

    const parts = rawValue.split(".");
    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ",");

    el.value = parts.length > 1 ? `${parts[0]}.${parts[1]}` : parts[0];
}

function applyAmountFormatting() {
    document.querySelectorAll(".amount-field").forEach(formatAmountInput);
}

document.addEventListener("keydown", function (e) {
    if (!e.target.classList.contains("amount-field")) {
        return;
    }

    const allowedControlKeys = ["Backspace", "Delete", "ArrowLeft", "ArrowRight", "Tab", "Home", "End"];
    if (allowedControlKeys.includes(e.key)) {
        return;
    }

    // Allow one decimal point
    if (e.key === ".") {
        if (e.target.value.includes(".")) {
            e.preventDefault();
        }
        return;
    }

    // Only digits
    if (!/^\d$/.test(e.key)) {
        e.preventDefault();
    }
});

document.addEventListener("input", function (e) {
    if (!e.target.classList.contains("amount-field")) return;
    formatAmountInput(e.target);
});

document.addEventListener("livewire:load", () => {
    formatAllAmountFields();
    Livewire.hook('message.processed', () => {
        formatAllAmountFields();
    });
});

function formatAllAmountFields() {
    document.querySelectorAll(".amount-field").forEach(el => {
        const raw = el.value.trim();

        // Validate number before formatting
        if (!/^\d*\.?\d*$/.test(raw.replace(/,/g, ""))) {
            return; // ❌ skip invalid values
        }

        el.value = formatValueKeepingDecimal(raw);
    });
}

function stripCommas(value) {
    return (value || "").toString().replace(/,/g, "");
}

function formatIntegerPart(intPart) {
    return intPart.replace(/\B(?=(\d{3})+(?!\d))/g, ",");
}

function formatValueKeepingDecimal(raw = "") {
    raw = stripCommas(raw);
    if (raw === "") return "";

    // validate number
    if (!/^\d*\.?\d*$/.test(raw)) return raw;

    raw = raw.replace(/[^0-9.]/g, "");
    const parts = raw.split(".");

    const intPart = parts[0];
    const fracPart = parts[1] ? parts.slice(1).join("") : "";

    const formattedInt = formatIntegerPart(intPart);

    return fracPart ? `${formattedInt}.${fracPart}` : formattedInt;
}
function formatAmount(value) {
        value = value.toString().replace(/,/g, '');
        if (!value || isNaN(value)) return '';

        const parts = value.split('.');
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        return parts.join('.');
    }

    function formatAllAmounts() {
        document.querySelectorAll('.amount-field').forEach(el => {
            if (el.value) {
                el.value = formatAmount(el.value);
            }
        });
    }

    document.addEventListener('livewire:load', () => {
        formatAllAmounts();
    });

    document.addEventListener('livewire:update', () => {
        formatAllAmounts();
        displayExtensionSaveInfo();
    });

    // Display extension data that is being filled in
    function displayExtensionSaveInfo() {
        const extensionRows = document.querySelectorAll('.row:has(input[name*="extension_select"])');
        let savedData = [];

        extensionRows.forEach(row => {
            // Get extension name
            const nameLabel = row.querySelector('label[style*="font-weight"]');
            const extensionName = nameLabel ? nameLabel.textContent.trim() : 'Unknown';

            // Get radio button state
            const yesRadio = row.querySelector('input[value="Yes"][name*="extension_select"]');
            const noRadio = row.querySelector('input[value="No"][name*="extension_select"]');
            const selected = yesRadio?.checked ? 'Yes' : (noRadio?.checked ? 'No' : 'Not selected');

            // Get discount dropdown
            const discountSelect = row.querySelector('select[name*="extention_discount_surcharge"]');
            const discount = discountSelect ? discountSelect.value : '-';

            // Get type dropdown
            const typeSelect = row.querySelector('select[name*="extention_discount_surcharge_type"]');
            const type = typeSelect ? typeSelect.value : '-';

            // Get premium value
            const premiumInput = row.querySelector('input[placeholder="Premium"]');
            const premium = premiumInput ? premiumInput.value || '0.00' : '0.00';

            // Get discount value
            const discountValueInput = row.querySelector('input[placeholder="Value"]');
            const discountValue = discountValueInput ? discountValueInput.value || '0.00' : '0.00';

            if (selected !== 'Not selected' && (discount !== '-' || type !== '-' || premium !== '0.00')) {
                savedData.push({
                    name: extensionName,
                    selected: selected,
                    discount: discount,
                    type: type,
                    discountValue: discountValue,
                    premium: premium
                });
            }
        });

        // Display saved data
        if (savedData.length > 0) {
            const infoBox = document.getElementById('extensionSaveInfo');
            const details = document.getElementById('extensionSaveDetails');

            let html = '<ul style="margin-top: 10px; margin-bottom: 0;">';
            savedData.forEach(ext => {
                html += `<li>
                    <strong>${ext.name}</strong> | Selection: ${ext.selected} |
                    Discount: ${ext.discount} | Type: ${ext.type} |
                    Discount Value: ${ext.discountValue} | Premium: ${ext.premium}
                </li>`;
            });
            html += '</ul>';

            details.innerHTML = html;
            infoBox.style.display = 'block';
        }
    }

    // Initial display on page load
    document.addEventListener('DOMContentLoaded', () => {
        displayExtensionSaveInfo();
    });

</script>

<style>
/* Ensure extension discount/premium fields are always editable */
.col-sm-2[style*="pointer-events"],
.col-sm-1[style*="pointer-events"] {
    pointer-events: auto !important !important;
    opacity: 1 !important !important;
}

.col-sm-2[style*="pointer-events"] select,
.col-sm-2[style*="pointer-events"] input,
.col-sm-1[style*="pointer-events"] select,
.col-sm-1[style*="pointer-events"] input {
    opacity: 1 !important !important;
    pointer-events: auto !important !important;
    background-color: white !important !important;
    color: #212529 !important !important;
    cursor: pointer !important !important;
}

.col-sm-2[style*="pointer-events"] select:disabled,
.col-sm-2[style*="pointer-events"] input:disabled,
.col-sm-1[style*="pointer-events"] select:disabled,
.col-sm-1[style*="pointer-events"] input:disabled {
    opacity: 1 !important !important;
    pointer-events: auto !important !important;
    background-color: white !important !important;
    color: #212529 !important !important;
}

/* Additional fallback - override any parent opacity */
div.col-sm-2:has(input[placeholder*="Value"]),
div.col-sm-2:has(input[placeholder*="Premium"]) {
    opacity: 1 !important !important;
    pointer-events: auto !important !important;
}

/* Force all extension form fields to be enabled */
.form-control:disabled,
.form-select:disabled {
    opacity: 1 !important !important;
    pointer-events: auto !important !important;
    background-color: white !important !important;
    color: #212529 !important !important;
    cursor: text !important !important;
}

/* Override Bootstrap fieldset disabled */
fieldset:disabled .form-control,
fieldset:disabled .form-select,
.form-control[disabled],
.form-select[disabled] {
    opacity: 1 !important !important !important;
    pointer-events: auto !important !important !important;
    background-color: white !important !important !important;
    color: #212529 !important !important !important;
    border-color: #dee2e6 !important !important !important;
    cursor: text !important !important !important;
}

/* Extension row visibility */
.row:has(input[name*="extention_"]) {
    display: block !important !important;
    visibility: visible !important !important;
    opacity: 1 !important !important;
}

/* All extension fields must be clickable */
input[name*="extention_"],
select[name*="extention_"] {
    pointer-events: auto !important !important !important;
    opacity: 1 !important !important !important;
    background-color: white !important !important !important;
    color: #212529 !important !important !important !important;
    cursor: text !important !important !important;
}

/* Extension rows always visible and interactive */
.row:has(input[name*="extension_select"]) {
    display: flex !important !important;
    opacity: 1 !important !important;
    pointer-events: auto !important !important;
}

/* All columns within extension rows must be visible */
.row:has(input[name*="extension_select"]) .col-sm-1,
.row:has(input[name*="extension_select"]) .col-sm-2,
.row:has(input[name*="extension_select"]) .col-sm-3 {
    display: block !important !important;
    visibility: visible !important !important;
    opacity: 1 !important !important;
    pointer-events: auto !important !important;
}

/* Extension radio buttons always interactive */
input[name*="extension_select"],
input[name*="extension_select"] + label {
    pointer-events: auto !important !important !important;
    cursor: pointer !important !important !important;
}
</style>