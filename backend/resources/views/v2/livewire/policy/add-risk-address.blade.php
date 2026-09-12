<div wire:loading.class="page-loading">
    <div class="page-loader flex-column bg-dark bg-opacity-25 ">
        <span class="spinner-border text-primary" role="status"></span>
        <span class="text-gray-800 fs-6 fw-semibold mt-5">Loading...</span>
    </div>
    <div class="row">
        <div class="col-sm-12">
            <div class='card card-custom gutter-b'>
                <div class="card-header">
                    <div class="card-title">
                        <h3 class="card-label">Risk Address Details</h3>
                    </div>
                </div>
                <div class="card-body">
                    <div class="col-sm-12">
                        <div class="table-responsive">
                            <table class="table align-middle table-row-dashed fs-6 gy-5 dataTable no-footer">
                                <thead>
                                <tr class="text-start fw-bold fs-7 text-uppercase gs-0">
                                    <th>Sr.No.</th>
                                    <th>Name</th>
                                    <th>Lat</th>
                                    <th>Lng</th>
                                    <th>Physical Address</th>
                                    <th>Province</th>
                                    <th>City</th>
                                    <th>Extension</th>
                                    <th>Occupation</th>
                                    <th>Town Class</th>
                                    <th>Risk Class</th>
                                    <th>Sub Company</th>
                                    @if($editable)
                                    <th style="text-align:center;">Action</th>
                                    @endif
                                </tr>
                                </thead>
                                <tbody class="fw-semibold text-gray-600">
                                @php $i=1;@endphp
                                @forelse($this->getallRiskAddress() as $risk)
                                    <tr class="{!! ($loop->iteration % 2 == 0)?'even':'odd' !!}">
                                        <td>{{$i++}}</td>
                                        <td>{{$risk->address_name ?? ""}}</td>
                                        <td>{{$risk->lat ?? ""}}</td>
                                        <td>{{$risk->lng ?? ""}}</td>
                                        <td>{{$risk->physical_address ?? ""}}</td>
                                        <td>{{$risk->state->name ?? ""}}</td>
                                        <td>{{$risk->city->name ?? ""}}</td>
                                        <td>{{$risk->extension ?? ""}}</td>
                                        <td>{{$risk->occupation ?? ""}}</td>
                                        <td>{{$risk->town_class ?? ""}}</td>
                                        <td>{{$risk->risk_class ?? ""}}</td>
                                        <td>{{$risk->company->name ?? ""}}</td>
                                        @if($editable)
                                        <td>
                                            <div class="flex space-x-1 justify-around">
                                                <button wire:click.prevent="riskAddressDetailsedit('{{\Crypt::encrypt($risk->id)}}')"  class="btn  btn-sm btn-outline btn-outline-dashed hover-scale btn-outline-primary btn-active-light-primary">
                                                    <span class="svg-icon svg-icon-2 m-0"><svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"></path></svg></span>
                                                    <!-- Edit -->
                                                </button>
                                                <button onclick="deleteRow('triggerRiskAddressDelete','{{$risk->id}}')" class="btn  btn-sm btn-outline btn-outline-dashed hover-scale btn-outline-danger btn-active-light-danger">
                                                    <span class="svg-icon svg-icon-2 m-0"><svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg></span>
                                                    <!-- Delete -->
                                                </button>
                                            </div>
                                        </td>
                                        @endif
                                    </tr>
                                @empty
                                    <tr><td colspan=7>Risk Address Details Not Present ..</td></tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
   
    @if($editable)
    <hr>
    <div class="row" id="risk-address-form-section">
        <div class="col-sm-12">
            <div class='card card-custom gutter-b {{ $R_isUpdate ? "border-warning" : "" }}'>
                <div class="card-header {{ $R_isUpdate ? "bg-light-warning" : "" }}">
                    <div class="card-title">
                        <h3 class="card-label">
                            @if($R_isUpdate)
                                <i class="fas fa-edit text-warning mr-2"></i>Edit
                            @else
                                 Add
                            @endif	Risk Address</h3>
                    </div>
                </div>
                <div class="card-body">
                    @if($this->actionTransactionType == 'NEWBUSINESS' || $this->actionTransactionType == 'ANNIVERSARY-RENEW')
                        <livewire:common.excel-import  :policy="$policy" :selectedType="'risk_address'" :termId="$termId" :actionId="$actionId" :exportLinkName="'Risk Address Data'"/>
                    <br>
                    @endif
                    <div class="row">
                        <div class="row">
                            <div class="col-sm-6">
                                <div class="form-floating mb-3">
                                    <x-form-input type="text"  wire:model.defer="riskinfo.address_name" placeholder="Address ID Name"/>
                                    <x-form-label for="address_name" required value="{{ __('Address ID Name:') }}"/>
                                    <x-form-input-error name="riskinfo.address_name"/>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="form-floating mb-3">
                                    <x-form-input  class="location-input" id="{{ $inSide }}-location-input" type="text"  wire:model.defer="riskinfo.physical_address" placeholder="Physical Address"/>
                                    <x-form-label for="physical_address" required value="{{ __('Physical Address:') }}"/>
                                    <x-form-input-error name="riskinfo.physical_address"/>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-sm-3">
                                <div class="form-floating mb-3">
                                    <x-form-input type="text" wire:model.defer="riskinfo.lat"  class="lat" placeholder="Lat." readonly/>
                                    <x-form-label for="lat" value="{{ __('Lat.') }}"/>
                                    <x-form-input-error name="riskinfo.lat"/>
                                </div>
                            </div>
                            <div class="col-sm-3">
                                <div class="form-floating mb-3">
                                    <x-form-input type="text" wire:model.defer="riskinfo.lng"  class="lng" placeholder="Lng." readonly/>
                                    <x-form-label for="lng" value="{{ __('Lng.') }}"/>
                                    <x-form-input-error name="riskinfo.lng"/>
                                </div>
                            </div>
                            <!-- Dropdown coming from db -->
                            <div class="col-sm-3">
                                <div class="form-floating mb-3">
                                    <x-select-search wire:model.lazy="riskinfo.risk_state"  id="risk_state"
                                                     aria-label="Select District" :options="$this->getStates()"	/>
                                    <x-form-label for="riskinfo.risk_state"  value="{{ __('Select District') }}" required />
                                    <x-form-input-error name="riskinfo.risk_state"/>
                                </div>
                            </div>

                            <!-- Dropdown coming from db -->
                            <div class="col-sm-3">
                                <div class="form-floating mb-3">
                                    <x-select-search id="risk_city"
                                                     aria-label="City"
                                                     listner="risk_state"
                                                     :options="$this->risk_cites" wire:model.defer='riskinfo.risk_city' />
                                    <x-form-label for="risk_city" required value="{{ __('City') }}"  />
                                    <x-form-input-error name="riskinfo.risk_city"/>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <!-- Dropdown coming from db -->
                            <div class="col-sm-3">
                                <div class="form-floating mb-3">
                                    <x-select-search wire:model.defer="riskinfo.extension"  id="extension"
                                    aria-label="Select Extension" :options="$this->getLookupExtension()"/>
                                    <x-form-label for="extension" value="{{ __('Extension') }}"  />
                                    <x-form-input-error name="riskinfo.extension"/>
                                </div>
                            </div>
                            <!-- Dropdown coming from db -->
                            <div class="col-sm-3">
                                <div class="form-floating mb-3">
                                    <x-select-search wire:model.defer="riskinfo.occupation"  id="occupation"
                                    aria-label="Select Occupation" :options="$this->getLookupOccupation()"/>
                                    {{-- <x-select name="occupation" wire:model.defer='riskinfo.occupation' aria-label="Occupation"
                                              :options="$this->getLookupOccupation()"/> --}}
                                    <x-form-label for="occupation" value="{{ __('Occupation') }}"  />
                                    <x-form-input-error name="riskinfo.occupation"/>
                                </div>
                            </div>
                            <div class="col-sm-3">
                                <div class="form-floating mb-3">
                                    <x-select  name="town_class" wire:model.defer="riskinfo.town_class" aria-label="Town Class"
                                               :options="array('High'=>'High','Medium'=>'Medium','Low'=>'Low')"/>
                                    <x-form-label for="town_class" value="{{ __('Town Class') }}"/>
                                    <x-form-input-error name="riskinfo.town_class"/>
                                </div>
                            </div>
                            <div class="col-sm-3">
                                <div class="form-floating mb-3">
                                    <x-select name="risk_class"  wire:model.defer="riskinfo.risk_class" aria-label="Risk Class"
                                              :options="array('High'=>'High','Medium'=>'Medium','Low'=>'Low')"/>
                                    <x-form-label for="risk_class" value="{{ __('Risk Class') }}"/>
                                    <x-form-input-error name="riskinfo.risk_class"/>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-sm-3">
                                <div class="form-floating mb-3">
                                    <x-form-input type="text" wire:model.defer="riskinfo.iso_rcv" placeholder="ISO RCV"/>
                                    <x-form-label for="iso_rcv" value="{{ __('ISO RCV') }}"/>
                                    <x-form-input-error name="riskinfo.iso_rcv"/>
                                </div>
                            </div>
                            <div class="col-sm-3">
                                <div class="form-floating mb-3">
                                    <x-form-input type="text"  wire:model.defer="riskinfo.year_built" placeholder="Year Built"/>
                                    <x-form-label for="year_built"  value="{{ __('Year Built') }}"/>
                                    <x-form-input-error name="riskinfo.year_built"/>
                                </div>
                            </div>
                            <div class="col-sm-3">
                                <div class="form-floating mb-3">
                                    <x-form-input type="text" wire:model.defer="riskinfo.area" placeholder="Area SqM"/>
                                    <x-form-label for="area"  value="{{ __('Area SqM') }}"/>
                                    <x-form-input-error name="riskinfo.area"/>
                                </div>
                            </div>
                            <div class="col-sm-3">
                                <div class="form-floating mb-3">
                                    <x-select-search wire:model.defer="riskinfo.structure_type"  id="structure_type"
                                    aria-label="Select Structure Type" :options="$this->getLookupStructureType()"	/>

                                    {{-- <x-select id="structure_type" wire:model.lazy="riskinfo.structure_type" aria-label="Structure Type"
                                              :options="$this->getLookupStructureType()"/> --}}
                                    <x-form-label for="structure_type"  value="{{ __('Structure Type') }}" />
                                    <x-form-input-error name="riskinfo.structure_type"/>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-sm-3">
                                <div class="form-floating mb-3">
                                    <x-select-search id="const_type"  wire:model.defer="riskinfo.const_type" aria-label="Construction Type"
                                              :options="$this->getLookupConstructionType()"/>
                                    <x-form-label for="const_type"  value="{{ __('Construction Type') }}" />
                                    <x-form-input-error name="riskinfo.const_type"/>
                                </div>
                            </div>
                            <div class="col-sm-3">
                                <div class="form-floating mb-3">
                                    <x-form-input type="text" wire:model.defer="riskinfo.distance_to_water" placeholder="Distance To Water(Km)"/>
                                    <x-form-label for="distance_to_water"  value="{{ __('Distance To Water(Km)') }}"/>
                                    <x-form-input-error name="riskinfo.distance_to_water"/>
                                </div>
                            </div>
                            <div class="col-sm-3">
                                <div class="form-floating mb-3">
                                    <x-form-input type="text" wire:model.defer="riskinfo.distance_to_fire" placeholder="Distance To Fire Stn."/>
                                    <x-form-label for="distance_to_fire"  value="{{ __('Distance To Fire Stn.') }}"/>
                                    <x-form-input-error name="riskinfo.distance_to_fire"/>
                                </div>
                            </div>
                            <div class="col-sm-3">
                                <div class="form-floating mb-3">
                                    <x-form-input type="text" wire:model.defer="riskinfo.distance_to_hydrant" placeholder="Distance To Hydrant"/>
                                    <x-form-label for="distance_to_hydrant"  value="{{ __('Distance To Hydrant') }}"/>
                                    <x-form-input-error name="riskinfo.distance_to_hydrant"/>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-sm-3">
                                <div class="form-floating mb-3">
                                    <x-select name="riskinfo.usage" wire:model.defer="riskinfo.usage" aria-label="Usage"
                                              :options="array('Administrative Office'=>'Administrative Office','Distribution Center'=>'Distribution Center','Manufacturing (Light)'=>'Manufacturing (Light)','Manufacturing (Heavy)'=>'Manufacturing (Heavy)',
									'Retail (FMGG)' => 'Retail (FMGG)','Retail (High Value)' => 'Retail (High Value)','Stock Yard' => 'Stock Yard')"/>
                                    <x-form-label for="riskinfo.usage"  value="{{ __('Usage') }}"/>
                                    <x-form-input-error name="riskinfo.usage"/>
                                </div>
                            </div>
                            <div class="col-sm-3">
                                <div class="form-floating mb-3">
                                    <x-select-search wire:model.defer="riskinfo.occupancy_type"  id="occupancy_type"
                                    aria-label="Select Occupancy Type" :options="$this->getLookupOccupancyType()"	/>

                                    {{-- <x-select  id="riskinfo.occupancy_type"
                                               wire:model.defer="riskinfo.occupancy_type"
                                               aria-label="Occupancy Type"
                                               :options="$this->getLookupOccupancyType()"/> --}}
                                    <x-form-label for="riskinfo.occupancy_type"  value="{{ __('Occupancy Type') }}"  />
                                    <x-form-input-error name="riskinfo.occupancy_type"/>
                                </div>
                            </div>
                            <div class="col-sm-3">
                                <div class="form-floating mb-3">
                                    <x-check-box  name="riskinfo.central_fire"  label="{{ __('Central Fire Alarm') }}" id="riskinfo.central_fire" wire:model.defer="riskinfo.central_fire"/>
                                    <x-form-input-error name="riskinfo.central_fire"/>
                                </div>
                            </div>
                            <div class="col-sm-3">
                                <div class="form-floating mb-3">
                                    <x-check-box name="riskinfo.central_burglar" label="{{ __('Central Burglar Alarm') }}" id="riskinfo.central_burglar" wire:model.defer="riskinfo.central_burglar"/>
                                    <x-form-input-error name="riskinfo.central_burglar"/>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-sm-3">
                                <div class="form-floating mb-3">
                                    <x-check-box label="{{ __('Gated Community') }}" id="riskinfo.gated_community" wire:model.defer="riskinfo.gated_community"/>
                                    <x-form-input-error name="riskinfo.gated_community"/>
                                </div>
                            </div>
                            <div class="col-sm-3">
                                <div class="form-floating mb-3">
                                    <x-select wire:model.defer="riskinfo.automatic" aria-label="Automatic Sprinklers"
                                              :options="array('Partial or None'=>'Partial or None','Full incl Bath/Attic etc'=>'Full incl Bath/Attic etc')"/>
                                    <x-form-label for="automatic"  value="{{ __('Automatic Sprinklers') }}"/>
                                    <x-form-input-error name="riskinfo.automatic"/>
                                </div>
                            </div>
                            <!-- Dropdown coming from db -->
							<div class="col-sm-3">
								<div class="form-floating mb-3">
									<x-select-search wire:model.defer="riskinfo.company_id"  id="company_id"
										aria-label="Select Sub Companies" :options="$this->getSubCompanies()??[]"	/>
                                        <x-form-label for="riskinfo.company_id"  value="{{ __('Select Sub Companies') }}"
                                        />
									<x-form-input-error name="riskinfo.company_id"/>
								</div>
							</div>
                        </div>
                        <!-- <div wire:loading >
                            <div class="overlay-layer  rounded ">
                                <div class="spinner-grow spinner-grow-sm bg-danger" style="width: 5rem; height: 5rem;"></div>
                            </div>
                        </div> -->
                        <div class="row">
                            <div class="col-sm-6" style="text-align: right;">
                                @if($R_isUpdate)
                                    <button type="button" wire:click.prevent="riskAddressDetailsEditCancel" class="btn btn-danger pull-right font-weight-bolder text-uppercase px-6 py-4" wire:loading.class="spinner spinner-right spinner-white pr-15"  wire:offline.attr="disabled" wire:loading.attr="disabled">
                                        Cancel
                                    </button>
                                @endif
                            </div>
                            <div class="col-sm-6">
                                <button type="button" wire:click.prevent="addRiskAddress" class="btn btn-primary pull-right font-weight-bolder text-uppercase px-6 py-4" wire:loading.class="spinner spinner-right spinner-white pr-15"  wire:offline.attr="disabled" wire:loading.attr="disabled">
                                    @if($R_isUpdate)
                                        Update
                                    @else
                                        <i class="la la-plus"></i>Save
                                    @endif
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                @if($isPrevious)
                    <div class="card-footer">
                        <div class="row mt-5">
                            <div class="col-sm-12">
                                <button type="button" wire:target="backToStep1" wire:loading.attr="disabled" wire:offline.attr="disabled" wire:click="backToStep1" class="btn btn-danger hover-rotate-end" style="float:left;margin-left:15px;">
                                    <span>Previous</span>
                                    <span wire:loading wire:target="backToStep1" class="indicator-progress">
									<span class="spinner-border spinner-border-sm align-middle ms-2"></span>
								</span>
                                </button>

                                <button type="button" wire:target="saveStep2" wire:loading.attr="disabled" wire:offline.attr="disabled" wire:click="saveStep2" class="btn btn-success hover-rotate-end" style="float:right;margin-right:15px;">
                                    <span>Save & Continue</span>
                                    <span wire:loading wire:target="saveStep2" class="indicator-progress">
									<span class="spinner-border spinner-border-sm align-middle ms-2"></span>
								</span>
                                </button>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
    @endif
</div>

<script>
    // Listen for scroll-to-edit-form event from Livewire
    document.addEventListener('livewire:updated', function() {
        // Scroll to form when edit is triggered
        const formSection = document.getElementById('risk-address-form-section');
        if (formSection) {
            setTimeout(() => {
                formSection.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }, 100);
        }
    });

    // Also handle the specific event
    window.addEventListener('scroll-to-edit-form', function(e) {
        const formSection = document.getElementById('risk-address-form-section');
        if (formSection) {
            formSection.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    });
</script>
