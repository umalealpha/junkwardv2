@if (isset($coverType))
<?php
// $coverage = $coverType->coverage;
$coverage = $coverType;
$header = "";
?>
<br><br>
<h3>{{ $coverage['s_CoverageCode'] }}</h3>
<br><br>
@if($coverType['s_CoverageCode'] == "DOMESTICMOTORCOMPREHENSIV" || $coverType['s_CoverageCode'] == "DOMESTICMOTORFTPFT"
|| $coverType['s_CoverageCode'] == "DOMESTICMOTORFTP" || $coverType['s_CoverageCode'] == "PERSONALMOTOR")
  @include('v2.livewire.policy.add-sub-coverage-add-ons')
@endif
@foreach(($coverage['subCoverage']) as $subCoverage)
    @if($header!=$subCoverage['s_CoverageGroupName'])
        <div class="row">
            <h4>{{ $subCoverage['s_CoverageGroupName'] }}</h4>
            <br><hr><br>
        </div>
    @endif
    @if($subCoverage['s_SubCoverageMainName'] == "Heading")
        <div class="row">
            <hr><b>{{ $subCoverage['s_CoverageName'] }}</b>
            <br><br>
        </div>
    @endif
    @php
        // dump($subCoverage['s_ScreenName']);
        $header = $subCoverage['s_CoverageGroupName'];
        $preFixModel = 'policyCoverageDetail'.'.'.$policyCoverage->id.'.'.$subCoverage->id.'.';
        // dd($preFixModel,$this->policyCoverageDetail,$coverage->id);
        $tbValidoptions = AlphaDirect\Models\TbValidOptions::where('s_OptionType','CVG_LIMIT')->where('n_SourceOneFK',$subCoverage->id)->pluck('n_SourceTwoFK');
        // dd($coverType->coverage_id,$coverage->id);
        $tbCvgpclimits = AlphaDirect\Models\TbCvgpcLimits::whereIn('n_PCLimitId_PK',$tbValidoptions)->get(['n_PCLimitId_PK','s_LimitTypeCode','s_LimitScreenName']);
        // dump($tbValidoptions,$tbCvgpclimits);
        $s_LimitTypeCode = $tbCvgpclimits[0]['s_LimitTypeCode'] ?? 0;

        $s_LimitScreenName = $tbCvgpclimits->pluck('s_LimitScreenName','n_PCLimitId_PK')->toArray() ?? 0;
        $screenName = null;
        foreach ($tbCvgpclimits as $screen_name){
            $screenName = $screen_name->s_LimitScreenName;
        }

        $vehicleValue = '';
        $selectedVehicleValue = '';
        if (isset($selectedVehicleData) && isset($subCoverage['s_ScreenName'])){
            if ($subCoverage['s_ScreenName'] == 'Vehicle Make') {
                $vehicleValue = 'selectedVehicleData'.'.'.$coverType->id.'.'.$subCoverage->id.'.make';
                $selectedVehicleValue = isset($selectedVehicleData[$coverType->id][$subCoverage->id]['make']) ? $selectedVehicleData[$coverType->id][$subCoverage->id]['make'] : null;
            } elseif ($subCoverage['s_ScreenName'] == 'Model') {
                $vehicleValue = 'selectedVehicleData'.'.'.$coverType->id.'.'.$subCoverage->id.'.model';
                $selectedVehicleValue = isset($selectedVehicleData[$coverType->id][$subCoverage->id]['model']) ? $selectedVehicleData[$coverType->id][$subCoverage->id]['model'] : null;
            }  elseif ($subCoverage['s_ScreenName'] == 'Engine Number' || $subCoverage['s_ScreenName'] == 'Engine number') {
                $vehicleValue = 'selectedVehicleData'.'.'.$coverType->id.'.'.$subCoverage->id.'.engineNo';
                $selectedVehicleValue = isset($selectedVehicleData[$coverType->id][$subCoverage->id]['engineNo']) ? $selectedVehicleData[$coverType->id][$subCoverage->id]['engineNo'] : null;;
            }  elseif ($subCoverage['s_ScreenName'] == 'Chassis Number' || $subCoverage['s_ScreenName'] == 'Chassis Number ' || $subCoverage['s_ScreenName'] == 'Chassis number') {
                $vehicleValue = 'selectedVehicleData'.'.'.$coverType->id.'.'.$subCoverage->id.'.chassisNo';
                $selectedVehicleValue = isset($selectedVehicleData[$coverType->id][$subCoverage->id]['chassisNo']) ? $selectedVehicleData[$coverType->id][$subCoverage->id]['chassisNo'] : null;;
            }  elseif ($subCoverage['s_ScreenName'] == 'Registration number') {
                $vehicleValue = 'selectedVehicleData'.'.'.$coverType->id.'.'.$subCoverage->id.'.vehiclePlate';
                $selectedVehicleValue = isset($selectedVehicleData[$coverType->id][$subCoverage->id]['vehiclePlate']) ? $selectedVehicleData[$coverType->id][$subCoverage->id]['vehiclePlate'] : null;;
            }

        }
    @endphp
    @if($subCoverage['s_SubCoverageMainName'] != "Heading")

        <div class="row">
            <div class="col-sm-5">
                <div class="form-floating mb-3">
                    <div class="col-sm-6">
                        @if($s_LimitTypeCode == 'DROPDOWN')
                            @if( $subCoverage['s_ScreenName'] == 'Make' && ($coverType['s_CoverageCode'] == "DOMESTICMOTORCOMPREHENSIV" || $coverType['s_CoverageCode'] == "DOMESTICMOTORFTPFT"
                                || $coverType['s_CoverageCode'] == "DOMESTICMOTORFTP" || $coverType['s_CoverageCode'] == "PERSONALMOTOR"))

                                <div class="form-floating mb-3">
                                    <x-select-search id="selectedVehicleData.{{$coverType->id}}.{{$subCoverage['id']}}.make"
                                        wire:model.lazy="selectedVehicleData.{{$coverType->id}}.{{$subCoverage['id']}}.make" wire:change="handleVehicleMakeChange($event.target.value,{{ $coverType->id }},{{ $subCoverage['id'] }})"
                                        aria-label="Select Vechicle Make"
                                        value="{{$selectedVehicleValue}}"
                                        :options="$this->getVechicleMake()"
                                    />
                                    <x-form-label for="make" value="Select Make" />
                                    <x-form-input-error name="selectedVehicleData.{{$coverType->id}}.{{$subCoverage['id']}}.make"/>
                                </div>
                            @else
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
                            @endif
                        @elseif ( $subCoverage['s_ScreenName'] == 'Model' && ($coverType['s_CoverageCode'] == "DOMESTICMOTORCOMPREHENSIV" || $coverType['s_CoverageCode'] == "DOMESTICMOTORFTPFT"
                            || $coverType['s_CoverageCode'] == "DOMESTICMOTORFTP" || $coverType['s_CoverageCode'] == "PERSONALMOTOR"))
                                <div class="form-floating mb-3">
                                    <select class="form-control" wire:model="selectedVehicleData.{{$coverType->id}}.{{$subCoverage['id']}}.model" id="selectedVehicleData.{{$coverType->id}}.{{$subCoverage['id']}}.model" aria-label="Select Screen Name">
                                        <option value="">- Select -</option>
                                            @isset($vehicleModels[$coverType->id])
                                                @foreach ($vehicleModels[$coverType->id] as $vehicle)
                                                    <option value="{{ $vehicle['id'] ?? '' }}" @if($selectedVehicleValue == $vehicle['id']) selected @endif>
                                                    {{ $vehicle['name'] ?? '' }}
                                                    </option>
                                                @endforeach
                                            @endisset
                                    </select>
                                    <x-form-label for="model" value="Select Model" />
                                    <x-form-input-error name="selectedVehicleData.{{$coverType->id}}.{{$subCoverage['id']}}.model"/>
                                </div>
                        @elseif ($s_LimitTypeCode == 'RADIO')
                            <div class="form-floating mb-3">
                                <label for="question">{{ $subCoverage['s_ScreenName'] }}</label><br><br>
                                    @foreach ($tbCvgpclimits as $radio)
                                    <div class="form-check form-check-inline" style="margin-left: 12px">
                                        <input class="form-check-input" type="radio" id="{{ ($preFixModel).'limit_id' }}" value="{{ $radio->n_PCLimitId_PK ?? '' }}" wire:model.defer="{{ ($preFixModel).'limit_id' }}">
                                        <label class="" for="inlineRadio1">{{ $radio->s_LimitScreenName }}</label>
                                    </div>
                                    @endforeach
                            </div>
                        @elseif ($s_LimitTypeCode == 'NUMBER')
                            <div class="form-floating mb-3">
                                <input class="form-control" wire:model.defer="{{ ($preFixModel).'coverage_value' }}"
                                    id="{{ ($preFixModel).'coverage_value' }}" placeholder="{{ $subCoverage['s_ScreenName'] }}"
                                    x-mask:dynamic="$money($input)" />
                                    <x-form-label for="{{ ($preFixModel).'coverage_value' }}" value="{{ $subCoverage['s_ScreenName'] }}" />
                                    <x-form-input-error name="{{ ($preFixModel).'coverage_value' }}"/>
                            </div>

                        @elseif ($subCoverage['s_ScreenName'] == 'Type of cover')
                            <div class="form-floating mb-3">
                                <input class="form-control"
                                    id="{{ ($preFixModel).'coverage_value' }}" value="{{$coverTypeName}}"  placeholder="{{ $subCoverage['s_ScreenName'] }}"
                                    readonly/>
                                    <x-form-label for="{{ ($preFixModel).'coverage_value' }}" value="{{ $subCoverage['s_ScreenName'] }}" />
                                    <x-form-input-error name="{{ ($preFixModel).'coverage_value' }}"/>
                            </div>
                        @elseif ($s_LimitTypeCode == 'NOEDIT')
                            @if($coverType['s_CoverageCode'] == "DOMESTICMOTORCOMPREHENSIV" || $coverType['s_CoverageCode'] == "DOMESTICMOTORFTPFT"
                                || $coverType['s_CoverageCode'] == "DOMESTICMOTORFTP" || $coverType['s_CoverageCode'] == "PERSONALMOTOR")

                                <div class="form-floating mb-3">
                                    <input type="text"  class="form-control" wire:model.defer="{{$vehicleValue}}"
                                    id="{{$vehicleValue}}" placeholder="{{ $subCoverage['s_ScreenName'] }}" value="{{$selectedVehicleValue}}">
                                    <x-form-label for="{{ ($preFixModel).'coverage_value_string' }}" value="{{ $subCoverage['s_ScreenName'] }}" />
                                    <x-form-input-error name="{{$vehicleValue}}" />
                                </div>
                            @else
                                <div class="form-floating mb-3">
                                    <input type="text"  class="form-control" wire:model.defer="{{ ($preFixModel).'coverage_value_string' }}"
                                    id="{{ ($preFixModel).'coverage_value_string' }}" value="{{ $subCoverage['s_ScreenName'] ?? '' }}" placeholder="{{ $subCoverage['s_ScreenName'] }}">
                                    <x-form-label for="{{ ($preFixModel).'coverage_value_string' }}" value="{{ $subCoverage['s_ScreenName'] }}" />
                                    <x-form-input-error name="{{ ($preFixModel).'coverage_value_string' }}" />
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
                    </div>
                </div>
            </div>
        </div>
    @endif
@endforeach


{{-- For Extention --}}

@if(count($coverage['allExtention']) > 0)
    <hr>
    <h4> Extentions</h4>
    @foreach(($coverage['allExtention']) as $subExtention)

        @if($subExtention['s_ExtensionsGroupName'] == "Heading")
            <div class="row">

                <b>{{ $subExtention['s_CoverageName'] }}</b>
                <br><br>
            </div>
        @else
        <div class="row">
            <br>
            <br>
        </div>
        @endif
        @php
            $preFixModel = 'policyExtentionDetail'.'.'.$coverType->id.'.'.$subExtention->id.'.';
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
                                <label for="question">{{ $subExtention['s_ScreenName'] }}</label><br><br>
                                    @foreach ($extentionCvgpclimits as $radio)
                                    <div class="form-check form-check-inline" style="margin-left: 12px">
                                        <input class="form-check-input" type="radio" id="{{ ($preFixModel).'extention_limit_id' }}" value="{{ $radio->n_PCLimitId_PK ?? '' }}" wire:model.defer="{{ ($preFixModel).'extention_limit_id' }}">
                                        <label class="" for="inlineRadio1">{{ $radio->s_LimitScreenName }}</label>
                                    </div>
                                    @endforeach
                            </div>
                        @elseif ($extentionLimitTypeCode == 'NUMBER')
                            <div class="form-floating mb-3">
                                <input class="form-control" wire:model.defer="{{ ($preFixModel).'extention_coverage_value' }}"
                                    id="{{ ($preFixModel).'extention_coverage_value' }}" placeholder="{{ $subExtention['s_ScreenName'] }}"
                                    x-mask:dynamic="$money($input)" />
                                    <x-form-label for="{{ ($preFixModel).'extention_coverage_value' }}" value="{{ $subExtention['s_ScreenName'] }}" />
                                    <x-form-input-error name="{{ ($preFixModel).'extention_coverage_value' }}"/>
                            </div>
                        @elseif ($extentionLimitTypeCode == 'NOEDIT')
                            <div class="form-floating mb-3">
                                <x-form-text-area class="form-control" wire:model.defer="{{ ($preFixModel).'extention_text_value' }}"
                                    id="{{ ($preFixModel).'extention_text_value' }}" placeholder="{{ $subExtention['s_ScreenName'] }}"
                                    />
                                <x-form-label for="{{ ($preFixModel).'extention_text_value' }}" value="{{ $subExtention['s_ScreenName'] }}" />
                                <x-form-input-error name="{{ ($preFixModel).'extention_text_value' }}"/>
                            </div>
                        @else
                            <div class="form-floating mb-3">
                                <input class="form-control" wire:model.defer="{{ ($preFixModel).'extention_coverage_value' }}"
                                    id="{{ ($preFixModel).'extention_coverage_value' }}" placeholder="{{ $subExtention['s_ScreenName'] }}"
                                    x-mask:dynamic="$money($input)" />
                                <x-form-label for="{{ ($preFixModel).'extention_coverage_value' }}" value="{{ $subExtention['s_ScreenName'] }}" />
                                <x-form-input-error name="{{ ($preFixModel).'extention_coverage_value' }}"/>
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
                    <input wire:model.defer="{{ ($preFixModel).'extention_discount_surcharge_value' }}" placeholder="Value" class="form-control" x-mask:dynamic="$money($input)" />
                    <x-form-label for="{{ ($preFixModel).'extention_discount_surcharge_value' }}" value="Discounted/Surcharge value" />
                    <x-form-input-error name="{{ ($preFixModel).'extention_discount_surcharge_value' }}"/>
                </div>
            </div>
            <div class="col-sm-2">
                <div class="form-floating mb-3">
                    <input type="text" wire:model.defer="{{ ($preFixModel).'extention_calculated_value' }}" placeholder="Premium" class="form-control" x-mask:dynamic="$money($input)"/>
                    <x-form-label for="{{ ($preFixModel).'extention_calculated_value' }}" value="Premium" />
                    <x-form-input-error name="{{ ($preFixModel).'extention_calculated_value' }}"/>
                </div>
            </div>
        </div>
        @endif
    @endforeach
@endif

{{-- Excess --}}

@if(count($coverage['allExcess']) > 0)
    <hr>
    <h4> Excess</h4> <br>
    @foreach(($coverage['allExcess']) as $subExtention)
        @php
            $header = $subExtention['s_CoverageName'];
            $preFixModel = 'policyExtentionDetail'.'.'.$coverType->id.'.'.$subExtention->id.'.';
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
                                <label for="question">{{ $subExtention['s_ScreenName'] }}</label><br><br>
                                    @foreach ($extentionCvgpclimits as $radio)
                                    <div class="form-check form-check-inline" style="margin-left: 12px">
                                        <input class="form-check-input" type="radio" id="{{ ($preFixModel).'extention_limit_id' }}" value="{{ $radio->n_PCLimitId_PK ?? '' }}" wire:model.defer="{{ ($preFixModel).'extention_limit_id' }}">
                                        <label class="" for="inlineRadio1">{{ $radio->s_LimitScreenName }}</label>
                                    </div>
                                    @endforeach
                            </div>
                        @elseif ($extentionLimitTypeCode == 'NUMBER')
                            <div class="form-floating mb-3">
                                <input class="form-control" wire:model.defer="{{ ($preFixModel).'extention_coverage_value' }}"
                                    id="{{ ($preFixModel).'extention_coverage_value' }}" placeholder="{{ $subExtention['s_ScreenName'] }}"
                                    x-mask:dynamic="$money($input)" />
                                    <x-form-label for="{{ ($preFixModel).'extention_coverage_value' }}" value="{{ $subExtention['s_ScreenName'] }}" />
                                    <x-form-input-error name="{{ ($preFixModel).'extention_coverage_value' }}"/>
                            </div>
                        @elseif ($extentionLimitTypeCode == 'NOEDIT')
                            <div class="form-floating mb-3">
                                <x-form-text-area class="form-control" wire:model.defer="{{ ($preFixModel).'extention_text_value' }}"
                                    id="{{ ($preFixModel).'extention_text_value' }}" placeholder="{{ $subExtention['s_ScreenName'] }}"
                                    />
                                <x-form-label for="{{ ($preFixModel).'extention_text_value' }}" value="{{ $subExtention['s_ScreenName'] }}" />
                                <x-form-input-error name="{{ ($preFixModel).'extention_text_value' }}"/>
                            </div>
                        @else
                        <div class="form-floating mb-3">
                                <x-form-text-area class="form-control" wire:model.defer="{{ ($preFixModel).'extention_text_value' }}"
                                    id="{{ ($preFixModel).'extention_text_value' }}" placeholder="{{ $subExtention['s_ScreenName'] }}"
                                    />
                                <x-form-label for="{{ ($preFixModel).'extention_text_value' }}" value="{{ $subExtention['s_ScreenName'] }}" />
                                <x-form-input-error name="{{ ($preFixModel).'extention_text_value' }}"/>
                        </div>
                        @endif

                        <div class="col-sm-12" style="margin-left: 104%;margin-top: -24%;">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" wire:model.defer="{{ ($preFixModel).'extention_excess_min_value' }}"
                                    id="{{ ($preFixModel).'extention_excess_min_value' }}" placeholder="{{ $subExtention['s_ScreenName'] }}"
                                    />
                                <x-form-label for="{{ ($preFixModel).'extention_excess_min_value' }}" value="Min %" />
                                <x-form-input-error name="{{ ($preFixModel).'extention_excess_min_value' }}"/>
                            </div>
                            <div class="form-floating mb-3">
                                <input class="form-control" wire:model.defer="{{ ($preFixModel).'extention_excess_max_value' }}"
                                    id="{{ ($preFixModel).'extention_excess_max_value' }}" placeholder="{{ $subExtention['s_ScreenName'] }}"
                                    x-mask:dynamic="$money($input)" />
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
                    <input wire:model.defer="{{ ($preFixModel).'extention_discount_surcharge_value' }}" placeholder="Value" class="form-control" x-mask:dynamic="$money($input)" />
                    <x-form-label for="{{ ($preFixModel).'extention_discount_surcharge_value' }}" value="Discounted/Surcharge value" />
                    <x-form-input-error name="{{ ($preFixModel).'extention_discount_surcharge_value' }}"/>
                </div>
            </div>
            <div class="col-sm-2">
                <div class="form-floating mb-3">
                    <input type="text" wire:model.defer="{{ ($preFixModel).'extention_calculated_value' }}" placeholder="Premium" disabled class="form-control" x-mask:dynamic="$money($input)"/>
                    <x-form-label for="{{ ($preFixModel).'extention_calculated_value' }}" value="Premium" />
                    <x-form-input-error name="{{ ($preFixModel).'extention_calculated_value' }}"/>
                </div>
            </div>
        </div>
    @endforeach
@endif
@endif
