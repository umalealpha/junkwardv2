<div>
    <div class="row">
        <div class="col-sm-12">
            <h3 class="fw-bold m-0" style="color:black;">Policy / Quote Details</h3><hr><br>
        </div>
        <div class="col-sm-3">
            <table style="width:100%">
                <tr>
                    <th>Policy / Quote Reference : </th>
                    <td>{{ $this->policy->policyNumber }}</td>
                </tr>
                <tr>
                    <th>Customer : </th>
                    <td> {{ $this->policy->customer?->firstName ?? '-' }} {{ $this->policy->customer?->lastName ?? '-' }}</td>
                </tr>
            </table>
        </div>

        <div class="col-sm-3">
            <table style="width:100%">
                <tr>
                    <th>Policy From Date : </th>
                    <td>
                        @if(isset($this->action?->effective_from))
                        {{ Carbon\Carbon::parse($this->action?->effective_from)->format('d/m/Y') }}
                        @endif
                    </td>
                </tr>
                <tr>
                    <th>Policy / Quote Reference : </th>
                    <td>{{ $this->policy->policyNumber }}</td>
                </tr>
            </table>
        </div>

        <div class="col-sm-3">
            <table style="width:100%">
                <tr>
                    <th>Policy To Date : </th>
                    <td>@if(isset($this->action?->effective_from))
                        {{ Carbon\Carbon::parse($this->action?->effective_to)->format('d/m/Y') }}
                        @endif
                    </td>
                </tr>
                <tr>
                    <th>Product : </th>
                    <td> {{ $this->policy?->product->name ?? '-'}}</td>
                </tr>
                <tr>
                    <th>Allocation Basis :</th>
                    <td></td>
                </tr>
            </table>
        </div>
    </div>
    <br><br>
    <form wire:submit.prevent="submit(Object.fromEntries(new FormData($event.target)))" autocomplete="off">
        <div class="row">
            <div class="col-sm-12">
                <h3 class="fw-bold m-0" style="color:black;">  Placement Summary </h3><hr><br>
            </div>
            <div class="col-sm-4">
                <div class="form-floating mb-3">
                    <x-form-input type="text"  name="tbporifacmaster.s_FacPlacmentNo"  wire:model.defer="tbporifacmaster.s_FacPlacmentNo" placeholder="FAC Placment No" />
                    <x-form-label for="tbporifacmaster.s_FacPlacmentNo" required value="{{ __('FAC Placment No :') }}"/>
                    <x-form-input-error name="tbporifacmaster.s_FacPlacmentNo"/>
                </div>
            </div>
            <div class="col-sm-4">
                <div class="form-floating mb-3">
                    <x-form-input type="text"  name="tbporifacmaster.s_FacOfferReference"  wire:model.defer="tbporifacmaster.s_FacOfferReference" placeholder="FAC Offer Reference" />
                    <x-form-label for="tbporifacmaster.s_FacOfferReference" required value="{{ __('FAC Offer Reference :') }}"/>
                    <x-form-input-error name="tbporifacmaster.s_FacOfferReference"/>
                </div>
            </div>
            <div class="col-sm-4">
                <div class="form-floating mb-3">
                    <x-form-input type="date" name="tbporifacmaster.d_PlacementDate"  wire:model.defer="tbporifacmaster.d_PlacementDate" placeholder="Placement Date"  />
                    <x-form-label for="tbporifacmaster.d_PlacementDate" required value="{{ __('Placement Date') }}"/>
                    <x-form-input-error name="tbporifacmaster.d_PlacementDate"/>
                </div>
            </div>
            <div class="col-sm-4">
                <div class="form-floating mb-3">
                    <x-form-input type="date" name="tbporifacmaster.d_PlacementEffectiveFrom"  wire:model.defer="tbporifacmaster.d_PlacementEffectiveFrom" placeholder="Placement Effective From" />
                    <x-form-label for="d_PlacementEffectiveFrom" required value="{{ __('Placement Effective From') }}"/>
                    <x-form-input-error name="tbporifacmaster.d_PlacementEffectiveFrom"/>
                </div>
            </div>
            <div class="col-sm-4">
                <div class="form-floating mb-3">
                    <x-select  name="tbporifacmaster.s_PremiumCalcType" wire:model.lazy="tbporifacmaster.s_PremiumCalcType" aria-label="s_PremiumCalcType"
                    :options="array('1'=>'Full premium','2'=>'Prorata')"  />
                    <x-form-label for="tbporifacmaster.s_PremiumCalcType" required value="{{ __('Select a Premium Calc Type') }}"/>
                    <x-form-input-error name="tbporifacmaster.s_PremiumCalcType"/>
                </div>
            </div>
            <div class="col-sm-4">
                <div class="form-floating mb-3">
                    <x-select  name="tbporifacmaster.s_RateBasis"  wire:model.lazy="tbporifacmaster.s_RateBasis" aria-label="RateBasis" :options="array('1'=>'Fixed premium','2'=>'Variable')"  />
                    <x-form-label for="s_RateBasis" required value="{{ __('Select a Rate Basis') }}"/>
                    <x-form-input-error name="tbporifacmaster.s_RateBasis"/>
                </div>
            </div>
            <div class="col-sm-4">
                <div class="form-floating mb-3">
                    <x-form-input type="text"  name="tbporifacmaster.n_FacShare"   wire:model.defer="tbporifacmaster.n_FacShare" placeholder="FAC Share %" />
                    <x-form-label for="tbporifacmaster.n_FacShare" required value="{{ __('FAC Share %') }}"/>
                    <x-form-input-error name="tbporifacmaster.n_FacShare"/>
                </div>
            </div>
            <div class="col-sm-4">
                <div class="form-floating mb-3">
                    <x-form-input type="text"  name="tbporifacmaster.s_Remarks"  wire:model.defer="tbporifacmaster.s_Remarks" placeholder="Remarks" />
                    <x-form-label for="tbporifacmaster.s_Remarks" required value="{{ __('Remarks') }}"/>
                    <x-form-input-error name="tbporifacmaster.s_Remarks"/>
                </div>
            </div>
        </div>
        <br><br>
        <div class="row">
            <div class="col-sm-12">
                <h3 class="fw-bold m-0" style="color:black;">  Placement Details </h3><hr><br>
                </div>
                <div class="col-sm-12">
                    <table style="width:100%">
                        <tr>
                            <th>Risk(Location)</th>
                            <th>RIGROUP</th>
                            <th>SHARE%</th>
                            <th></th>
                            <th>POLICY</th>
                            <th></th>
                            <th></th>
                            <th>FACULTATIVE</th>
                        </tr>
                        <tr>
                            <th></th>
                            <th></th>
                            <th></th>
                            <th>SumInsured(100%)</th>
                            <th>Premium</th>
                            <th>Average Rate</th>
                            <th>FAC Rate</th>
                            <th>FAC SumInsured</th>
                            <th>FAC Premium</th>
                        </tr>
                        @if(count($this->coverages)>0)
                            @foreach ($this->coverages as $coverage)
                                @php $data = $this->getPolicySubCoverage($coverage->id); @endphp
                                @php $coverageId = $this->getCoverageId($coverage->id); @endphp
                            <tr>
                                <td>
                                    {{$coverage->id}}
                                    <input type="hidden" class="form-control" name="{{$coverage->id}}_FIELD1" id="{{$coverage->id}}_FIELD1"  value="{{$coverageId??''}}" readonly/>
                                </td>
                                <td></td>
                                <td>
                                    <input type="text" class="form-control" value="" readonly/>
                                </td>
                                <td>
                                    <input type="text"  class="form-control" name="{{$coverage->id}}_FIELD2" id="{{$coverage->id}}_FIELD2"  value="{{ number_format($data['SumInsured'] ?? '', 2, '.', ',') }}" readonly/>
                                    {{-- round($data['SumInsured'],2) --}}
                                </td>
                                <td>
                                   <input type="text" class="form-control" name="{{$coverage->id}}_FIELD3" id="{{$coverage->id}}_FIELD3"  value="{{ number_format($data['Premium'] ?? '', 2, '.', ',') }}" readonly/>
                                    {{-- round($data['Premium'],2) --}}
                                </td>
                                <td>
                                    <input type="text" class="form-control"  value="" readonly/>
                                </td>
                                <td>
                                    <input type="text" class="form-control"  value="" readonly/>
                                </td>
                                <td>
                                    <input type="text" class="form-control" value="" readonly/>
                                </td>
                                <td>
                                    <input type="text" class="form-control"  value="" readonly/>
                                </td>
                            </tr>
                            @endforeach
                            <tr>
                                <td>TOTAL</td>
                                <td></td>
                                <td></td>
                                <td>
                                    {{-- <label>{{ number_format($this->totalSumInsured ?? '', 2, '.', ',') }}</label> --}}
                                    <input type="text" class="form-control" value="{{ number_format($this->totalSumInsured ?? '', 2, '.', ',') }}" readonly />
                                </td>
                                <td>
                                    {{-- <label>{{ number_format($this->totalPremium ?? '', 2, '.', ',') }}</label> --}}
                                    <input type="text" class="form-control" value="{{ number_format($this->totalPremium ?? '', 2, '.', ',') }}" readonly />
                                </td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                            </tr>
                        @endif
                    </table>
                </div>
                <div class="col-sm-2">
                    <table style="width:100%">
                    </table>
                </div>
                <div class="col-sm-5">
                </div>
        </div>
        <div class="row">
            <div class="col-sm-12">
                <div class="card-footer text-center">
                    @if($this->editForm )
                        <input type="submit" class="btn btn-primary" value="Submit">
                    @else
                        <input type="submit" class="btn btn-primary" value="Update">
                    @endif
                </div>
            </div>
        </div>
        <br><br><br>
    </form>


    <div class="row">
       <div class="col-sm-12">
           <h3 class="fw-bold m-0" style="color:black;"> Participants </h3><hr><br>
        </div>
        @livewire('policy.facutatlve-placement.participants',['policy'=>$this->policy,'actionId'=>$actionId])
    </div>
</div>
