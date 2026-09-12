<div>
@foreach($fields as $index => $field)

                                
                                            @php
                                                // $header = $subExtention['s_CoverageName'];
                                                $preFixModel = 'policyExtentionDetail'.'.'.$policyCoverage->id.'.'.$subExtention->id.'.';
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
                                                <div class="col-sm-5">
                                                    <div class="form-floating mb-3">
                                                        <div class="col-sm-6">
                                                            @if($extentionLimitTypeCode == 'DROPDOWN')
                                                                <div class="form-floating mb-3">
                                                                    <select class="form-control" wire:model.defer="{{ ($preFixModel).'extention_limit_id' }}" aria-label="Select Screen Name">
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
                                                                            <input class="form-check-input" type="radio"  name="{{ ($preFixModel).'extention_limit_id' }}" value="{{ $radio->n_PCLimitId_PK ?? '' }}" wire:model.defer="{{ ($preFixModel).'extention_limit_id' }}">
                                                                            <label class="" for="inlineRadio1">{{ $radio->s_LimitScreenName }}</label>
                                                                        </div>
                                                                        @endforeach
                                                                    {{-- <input type="radio"  name="{{ ($preFixModel).'extention_limit_id' }}" value="{{ $radio->n_PCLimitId_PK ?? '' }}" wire:model.defer="{{ ($preFixModel).'extention_limit_id' }}" style="height:45px;width: 19px;margin-left: 170px;">{{ $radio->s_LimitScreenName }} --}}
                                                                </div>
                                                            @elseif ($extentionLimitTypeCode == 'NUMBER')
                                                                <div class="form-floating mb-3">
                                                                    <input title="{{ $subExtention['s_ScreenName'] }}" class="form-control" wire:model.defer="{{ ($preFixModel).'extention_coverage_value' }}"
                                                                        placeholder="{{ $subExtention['s_ScreenName'] }}"
                                                                        x-mask:dynamic="$money($input)" />
                                                                        <x-form-label for="{{ ($preFixModel).'extention_coverage_value' }}" value="{{ $subExtention['s_ScreenName'] }}" />
                                                                        <x-form-input-error name="{{ ($preFixModel).'extention_coverage_value' }}"/>
                                                                </div>
                                                            @elseif ($extentionLimitTypeCode == 'NOEDIT')
                                                                <div class="form-floating mb-3">
                                                                    <x-form-text-area title="{{ $subExtention['s_ScreenName'] }}" class="form-control" wire:model.defer="{{ ($preFixModel).'extention_text_value' }}"
                                                                       placeholder="{{ $subExtention['s_ScreenName'] }}"
                                                                        />
                                                                    <x-form-label for="{{ ($preFixModel).'extention_text_value' }}" value="{{ $subExtention['s_ScreenName'] }}" />
                                                                    <x-form-input-error name="{{ ($preFixModel).'extention_text_value' }}"/>
                                                                </div>
                                                            @else
                                                                <div class="form-floating mb-3">
                                                                    <x-form-text-area title="{{ $subExtention['s_ScreenName'] }}" class="form-control" wire:model.defer="{{ ($preFixModel).'extention_text_value' }}"
                                                                        placeholder="{{ $subExtention['s_ScreenName'] }}"
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
                                            <button class="btn text-white btn-danger btn-sm float-right mr-2" wire:click.prevent="removeField({{ $index }})"> <i class="fa fa-minus"></i>
                                                            Remove
                                            </button>
                                            @endif
@endforeach
<button  class="btn text-white btn-info btn-sm float-right mb-1" wire:click.prevent="addField2"><i class="fa fa-plus"></i>Add More</button>
</div>
