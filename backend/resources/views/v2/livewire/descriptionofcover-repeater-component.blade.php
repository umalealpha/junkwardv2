<div>
    @foreach($fields as $k1 => $field) 
                 @php  $subCoverage = $coverage[0]; 
                                            $header = $subCoverage['s_CoverageGroupName'];
                                            $preFixModel = 'policyCoverageDetail'.'.'.$policyCoverage->id.'.'.$subCoverage->id.'.';
                                            // dd($preFixModel,$this->policyCoverageDetail,$subCoverage->id);
                                            $tbValidoptions = AlphaDirect\Models\TbValidOptions::where('s_OptionType','CVG_LIMIT')->where('n_SourceOneFK',$subCoverage->id)->pluck('n_SourceTwoFK');
                                            // dd($policyCoverage->coverage_id,$subCoverage->id);
                                            $tbCvgpclimits = AlphaDirect\Models\TbCvgpcLimits::whereIn('n_PCLimitId_PK',$tbValidoptions)->get(['n_PCLimitId_PK','s_LimitTypeCode','s_LimitScreenName']);
                                            //dd($tbValidoptions,$tbCvgpclimits);
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
                                                    $vehicleValue = 'selectedVehicleData'.'.'.$policyCoverage->id.'.make';
                                                    $selectedVehicleValue = isset($selectedVehicleData[$policyCoverage->id]['make']) ? $selectedVehicleData[$policyCoverage->id]['make'] : null;
                                                } elseif ($subCoverage['s_ScreenName'] == 'Model') {
                                                    $vehicleValue = 'selectedVehicleData'.'.'.$policyCoverage->id.'.model';
                                                    $selectedVehicleValue = isset($selectedVehicleData[$policyCoverage->id]['model']) ? $selectedVehicleData[$policyCoverage->id]['model'] : null;
                                                }  elseif ($subCoverage['s_ScreenName'] == 'Engine Number' || $subCoverage['s_ScreenName'] == 'Engine number') {
                                                    $vehicleValue = 'selectedVehicleData'.'.'.$policyCoverage->id.'.engineNo';
                                                    $selectedVehicleValue = isset($selectedVehicleData[$policyCoverage->id]['engineNo']) ? $selectedVehicleData[$policyCoverage->id]['engineNo'] : null;;
                                                }  elseif ($subCoverage['s_ScreenName'] == 'Chassis Number' || $subCoverage['s_ScreenName'] == 'Chassis Number ' || $subCoverage['s_ScreenName'] == 'Chassis number') {
                                                    $vehicleValue = 'selectedVehicleData'.'.'.$policyCoverage->id.'.chassisNo';
                                                    $selectedVehicleValue = isset($selectedVehicleData[$policyCoverage->id]['chassisNo']) ? $selectedVehicleData[$policyCoverage->id]['chassisNo'] : null;;
                                                }  elseif ($subCoverage['s_ScreenName'] == 'Registration number') {
                                                    $vehicleValue = 'selectedVehicleData'.'.'.$policyCoverage->id.'.vehiclePlate';
                                                    $selectedVehicleValue = isset($selectedVehicleData[$policyCoverage->id]['vehiclePlate']) ? $selectedVehicleData[$policyCoverage->id]['vehiclePlate'] : null;;
                                                }

                                            }
                                        @endphp
                                        @if($subCoverage['s_SubCoverageMainName'] != "Heading")
                                        <div class="row">
                                            <div class="col-sm-5">
                                                <div class="form-floating mb-3">
                                                    <div class="col-sm-6">
                                                            @if($s_LimitTypeCode == 'DROPDOWN')
                                                                @if ( ($subCoverage['s_ScreenName'] == 'Vehicle Make' || $subCoverage['s_ScreenName'] == 'Make') && ($policyCoverage->coverage['s_CoverageCode'] == 'MOTORTRADERSINTERNALCOMPR' ||
                                                                $policyCoverage->coverage['s_CoverageCode'] == 'MOTORTRADERSEXTERNALCOMPR' ||
                                                                $policyCoverage->coverage['s_CoverageCode'] == 'MOTORTRADERSINTERNALFTPFT' ||
                                                                $policyCoverage->coverage['s_CoverageCode'] == 'MOTORTRADERSEXTERNALFTPFT' ||
                                                                $policyCoverage->coverage['s_CoverageCode'] == 'MOTORTRADERSINTERNALTP' ||
                                                                $policyCoverage->coverage['s_CoverageCode'] == 'MOTORTRADERSEXTERNALFTP' ||
                                                                $policyCoverage->coverage['s_CoverageCode'] == 'MOTORCOMPREHESIVE' ||
                                                                $policyCoverage->coverage['s_CoverageCode'] == 'MOTORTHIRDPARTYFIREANDTHE' ||
                                                                $policyCoverage->coverage['s_CoverageCode'] == 'MOTORTHIRDPARTYONLY' ||
                                                                $policyCoverage->coverage['s_CoverageCode'] == "PERSONALMOTOR"))

                                                                <div class="form-floating mb-3">
                                                                  

                                                                    <x-select-search
                                                                        wire:model.lazy="selectedVehicleData.{{$policyCoverage->id}}.make" wire:change="handleVehicleMakeChange($event.target.value,{{ $policyCoverage->id }})"
                                                                        aria-label="Select Vechicle Make"
                                                                        value="{{$selectedVehicleValue}}"
                                                                        {{-- listner="is_imported" --}}
                                                                        :options="$this->getVechicleMake()"
                                                                    />
                                                                    <x-form-label for="make" value="Select Make" />
                                                                    <x-form-input-error name="selectedVehicleData.{{$policyCoverage->id}}.make"/>
                                                                </div>
                                                            @else
                                                                <div class="form-floating mb-3">
                                                                    <select class="form-control" wire:model.defer="{{ ($preFixModel).'limit_id' }}"  aria-label="Select Screen Name" wire:change="handleDropdownChange($event.target.value,{{ $policyCoverage->coverage_id }})">
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
                                                        @elseif ( $subCoverage['s_ScreenName'] == 'Model' && ($policyCoverage->coverage['s_CoverageCode'] == 'MOTORTRADERSINTERNALCOMPR' ||
                                                            $policyCoverage->coverage['s_CoverageCode'] == 'MOTORTRADERSEXTERNALCOMPR' ||
                                                            $policyCoverage->coverage['s_CoverageCode'] == 'MOTORTRADERSINTERNALFTPFT' ||
                                                            $policyCoverage->coverage['s_CoverageCode'] == 'MOTORTRADERSEXTERNALFTPFT' ||
                                                            $policyCoverage->coverage['s_CoverageCode'] == 'MOTORTRADERSINTERNALTP' ||
                                                            $policyCoverage->coverage['s_CoverageCode'] == 'MOTORTRADERSEXTERNALFTP' ||
                                                            $policyCoverage->coverage['s_CoverageCode'] == 'MOTORCOMPREHESIVE' ||
                                                            $policyCoverage->coverage['s_CoverageCode'] == 'MOTORTHIRDPARTYFIREANDTHE' ||
                                                            $policyCoverage->coverage['s_CoverageCode'] == 'MOTORTHIRDPARTYONLY' ||
                                                            $policyCoverage->coverage['s_CoverageCode'] == "PERSONALMOTOR"))

                                                              

                                                                <div class="form-floating mb-3">
                                                                  
                                                                    <select class="form-control" wire:model="selectedVehicleData.{{$policyCoverage->id}}.model"  aria-label="Select Screen Name">
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
                                                                <label for="question">{{ $subCoverage['s_ScreenName'] }}</label><br><br>
                                                                    @foreach ($tbCvgpclimits as $radio)
                                                                    <div class="form-check form-check-inline" style="margin-left: 12px">
                                                                        <input class="form-check-input" type="radio"  name="{{ ($preFixModel).'limit_id' }}" value="{{ $radio->n_PCLimitId_PK ?? '' }}" wire:model.defer="{{ ($preFixModel).'limit_id' }}">
                                                                        <label class="" for="inlineRadio1">{{ $radio->s_LimitScreenName }}</label>
                                                                    </div>
                                                                    @endforeach
                                                            </div>
                                                        @elseif ($s_LimitTypeCode == 'NUMBER')
                                                            <div class="form-floating mb-3">
                                                                <input title="{{ $subCoverage['s_ScreenName'] }}" class="form-control" wire:model.defer="{{ ($preFixModel).'coverage_value' }}"
                                                                     placeholder="{{ $subCoverage['s_ScreenName'] }}"
                                                                    x-mask:dynamic="$money($input)" />
                                                                    <x-form-label for="{{ ($preFixModel).'coverage_value' }}"   value="{{ $subCoverage['s_ScreenName']  }}" />
                                                                    <x-form-input-error name="{{ ($preFixModel).'coverage_value' }}"/>

                                                                
                                                            </div>
                                                        @elseif ($s_LimitTypeCode == 'NOEDIT')
                                                            @if ($policyCoverage->coverage['s_CoverageCode'] == 'MOTORTRADERSINTERNALCOMPR' ||
                                                                $policyCoverage->coverage['s_CoverageCode'] == 'MOTORTRADERSEXTERNALCOMPR' ||
                                                                $policyCoverage->coverage['s_CoverageCode'] == 'MOTORTRADERSINTERNALFTPFT' ||
                                                                $policyCoverage->coverage['s_CoverageCode'] == 'MOTORTRADERSEXTERNALFTPFT' ||
                                                                $policyCoverage->coverage['s_CoverageCode'] == 'MOTORTRADERSINTERNALTP' ||
                                                                $policyCoverage->coverage['s_CoverageCode'] == 'MOTORTRADERSEXTERNALFTP' ||
                                                                $policyCoverage->coverage['s_CoverageCode'] == 'MOTORCOMPREHESIVE' ||
                                                                $policyCoverage->coverage['s_CoverageCode'] == 'MOTORTHIRDPARTYFIREANDTHE' ||
                                                                $policyCoverage->coverage['s_CoverageCode'] == 'MOTORTHIRDPARTYONLY' ||
                                                                $policyCoverage->coverage['s_CoverageCode'] == "PERSONALMOTOR")

                                                                <div class="form-floating mb-3">
                                                                    <input title="{{ $subCoverage['s_ScreenName'] }}" type="text"  class="form-control" wire:model="{{$vehicleValue}}"
                                                                    placeholder="{{ $subCoverage['s_ScreenName'] }}" value="{{$selectedVehicleValue}}">
                                                                    <x-form-label for="{{ ($preFixModel).'coverage_value_string' }}" value="{{ $subCoverage['s_ScreenName'] }}" />
                                                                    <x-form-input-error name="{{$vehicleValue}}" />
                                                                </div>
                                                            @else
                                                                <div class="form-floating mb-3">
                                                                    <x-form-text-area title="{{ $subCoverage['s_ScreenName'] }}" class="form-control" wire:model.defer="{{ ($preFixModel).'coverage_value_string' }}"
                                                                    placeholder="{{ $subCoverage['s_ScreenName'] }}"/>
                                                                    <x-form-label for="{{ ($preFixModel).'coverage_value_string' }}" value="{{ $subCoverage['s_ScreenName'] }}" />
                                                                    <x-form-input-error name="{{ ($preFixModel).'coverage_value_string' }}"/>
                                                                </div>
                                                            @endif

                                                        @else
                                                            <div class="form-floating mb-3">
                                                                <x-form-text-area class="form-control" wire:model.defer="{{ ($preFixModel).'coverage_value_string' }}"
                                                                placeholder="{{ $subCoverage['s_ScreenName'] }}"/>
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
                                                                    placeholder="No of Weeks"  class="form-control">
                                                                 
                                                                    <x-form-label for="{{ ($preFixModel).'ratefactor_value' }}" value="No of Weeks" />
                                                                    <x-form-input-error name="{{ ($preFixModel).'ratefactor_value' }}"/>

                                                                @elseif($subCoverage['s_CoverageCode']=='MONEYSEASONALINC1' || $subCoverage['s_CoverageCode']=='MONEYSEASONALINC2')
                                                                      
                                                                        <input name="CustomStat1_Date_To_" type="text" wire:model.defer="{{ ($preFixModel).'ratefactor_value' }}" class='form-control datepicker maskdate'>
                                                                        <x-form-label for="{{ ($preFixModel).'ratefactor_type' }}" value=" To" style="margin-top: 70px;" />
                                                                    <x-form-input-error name="{{ ($preFixModel).'ratefactor_type' }}"/>

                                                                  
                                                                @elseif($subCoverage['s_CoverageCode']=='SUM INSURED' && $subCoverage['s_ParentCoverageCode']=='HOUSEOWNER-BUILDINGS')
                                                                    <input type="text" wire:model.defer="{{ ($preFixModel).'ratefactor_value' }}" value="{{ $subCoverage['s_ScreenName'] ?? '' }}" placeholder="Free Text" class="form-control">
                                                                    <x-form-label for="{{ ($preFixModel).'ratefactor_value' }}" value="Free Text" />
                                                                    <x-form-input-error name="{{ ($preFixModel).'ratefactor_value' }}"/>
                                                                  
                                                                @elseif($subCoverage['s_ParentCoverageCode']=='PERSONALALLRISKS')
                                                                <input type="text" wire:model.defer="{{ ($preFixModel).'ratefactor_value' }}" value="{{ $subCoverage['s_ScreenName'] ?? '' }}" placeholder="Free Text" class="form-control">
                                                                <x-form-label for="{{ ($preFixModel).'ratefactor_value' }}" value="Free Text" />
                                                                <x-form-input-error name="{{ ($preFixModel).'ratefactor_value' }}"/>
                                                                @elseif($subCoverage['s_ParentCoverageCode']=='HOUSEHOLDERS-CONTENTS'
                                                                || ($subCoverage['s_ScreenName']=='Employers Liablity (Common Law Liability)' && $subCoverage['s_ParentCoverageCode']=='WORKERSCOMPENSATION')
                                                                && $subCoverage['s_CoverageGroupName']=='Description of cover')
                                                                <input type="text" wire:model.defer="{{ ($preFixModel).'ratefactor_value' }}" value="{{ $subCoverage['s_ScreenName'] ?? '' }}" placeholder="Free Text" class="form-control">
                                                                <x-form-label for="{{ ($preFixModel).'ratefactor_value' }}" value="Free Text" />
                                                                <x-form-input-error name="{{ ($preFixModel).'ratefactor_value' }}"/>
                                                                @elseif(($subCoverage['s_CoverageCode']=='FREE TEXT' && $subCoverage['s_ParentCoverageCode']=='STATEDBENEFITS') ||
                                                                        ($subCoverage['s_ScreenName']=='Free text' && $subCoverage['s_ParentCoverageCode']=='WORKERSCOMPENSATION'))
                                                              
                                                                    <div class="form-check form-check-inline">
                                                                        <input class="form-check-input" style="margin-left:2px" type="radio"  name="{{ ($preFixModel).'ratefactor_value_check' }}" value="All Employees" wire:model.defer="{{ ($preFixModel).'ratefactor_value_check' }}">
                                                                        <label class="" for="inlineRadio1" style="margin-left: 12px">All Employees</label>
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
                                                                                <input type="text" wire:model.defer="{{ ($preFixModel).'ratefactor_AnnualWages' }}" x-mask:dynamic="$money($input)" value="{{ $subCoverage['s_ScreenName'] ?? '' }}" placeholder="AnnualWages" class="form-control">
                                                                                <x-form-label for="{{ ($preFixModel).'ratefactor_AnnualWages' }}" value="AnnualWages" />
                                                                                <x-form-input-error name="{{ ($preFixModel).'ratefactor_AnnualWages' }}"/>
                                                                            </div>
                                                                            <div class="form-check form-check-inline">
                                                                                <p></p>
                                                                                <input type="text" wire:model.defer="{{ ($preFixModel).'ratefactor_deposit_min_pre' }}" x-mask:dynamic="$money($input)" value="{{ $subCoverage['s_ScreenName'] ?? '' }}" placeholder="Deposit and Min Prem" class="form-control">
                                                                                <x-form-label for="{{ ($preFixModel).'ratefactor_deposit_min_pre' }}" value="Deposit and Min Prem" />
                                                                                <x-form-input-error name="{{ ($preFixModel).'ratefactor_deposit_min_pre' }}"/>
                                                                                <p></p>
                                                                            </div><br>
                                                                        @endif
                                                                   
                                                           
                                                              
                                                           
                                                                @endif
                                                            </div>
                                                        </div>
                                                   </div>
                                                </div>
                                            </div>
                                         
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
                                                    <input title="Discount/Surcharge Value" wire:model.defer="{{ ($preFixModel).'discount_surcharge_value' }}" placeholder="Value" class="form-control" x-mask:dynamic="$money($input)" />
                                                    <x-form-label for="{{ ($preFixModel).'discount_surcharge_value' }}" value="Discounted/Surcharge value" />
                                                    <x-form-input-error name="{{ ($preFixModel).'discount_surcharge_value' }}"/>

                                                </div>
                                            </div>
                                            <div class="col-sm-2">
                                                <div class="form-floating mb-3">
                                                    <input type="text" wire:model.defer="{{ ($preFixModel).'calculated_value' }}" placeholder="Premium" class="form-control" x-mask:dynamic="$money($input)"/>
                                                    <x-form-label for="{{ ($preFixModel).'calculated_value' }}" value="Premium" />
                                                    <x-form-input-error name="{{ ($preFixModel).'calculated_value' }}"/>

                                                  
                                                </div>
                                                
                                            </div>
                                           
                                           
                                             
                                        </div>
                                        @endif
                                       
                                        <button class="btn text-white btn-danger btn-sm float-right mr-2 my-3" wire:click.prevent="removeField({{ $k1 }})"> <i class="fa fa-minus"></i>
                                                        Remove
                                        </button>


    @endforeach
    <button   class="btn text-white btn-info btn-sm float-right my-3" wire:click.prevent="addField1"><i class="fa fa-plus"></i>Add More</button>
</div>
