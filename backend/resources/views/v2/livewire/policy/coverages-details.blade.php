<div>
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
        <form wire:submit.prevent="submit" autocomplete="off">
            <div class="row">
                @php $riskAddressId=''; @endphp
                @foreach($this->PolicyCoverages as $index => $policyCoverage)
                    @php
                        if($riskAddressId != $policyCoverage->risk_address_id){
                            if($riskAddressId!=""){
                                  echo "</div>
                                  </div>
                               </div>
                            </div>";
                            }

                            echo '
                            <div class="accordion" id="riskAddressAccordion_'.$policyCoverage->risk_address_id.'">
                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="heading'.$policyCoverage->risk_address_id.'">
                                        <button class="accordion-button fs-4 fw-semibold show" type="button" data-bs-toggle="collapse" data-bs-target="#collapse'.$policyCoverage->risk_address_id.'" aria-expanded="true" aria-controls="collapse'.$policyCoverage->risk_address_id.'">
                                           '.$policyCoverage->riskAddress->address_name.'
                                        </button>
                                    </h2>

                                    <div id="collapse'.$policyCoverage->risk_address_id.'" class="accordion-collapse collapse '.static::RISKACCORDIONCOLLAPSE.'" aria-labelledby="heading'.$policyCoverage->risk_address_id.'" data-bs-parent="#riskAccordion">
                                        <div class="accordion-body">
                            ';
                        }
                        $riskAddressId = $policyCoverage->risk_address_id;
                        $coverage = $policyCoverage->coverage;
                    @endphp
                    <div class="accordion" id="riskAccordion_{{$policyCoverage->risk_address_id}}" wire:ignore.self wire:key="accodion_{{ $riskAddressId }}_{{ $policyCoverage->id }}">
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="heading{{ $policyCoverage->risk_address_id."_".$policyCoverage->id }}" >
                                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapse{{ $policyCoverage->risk_address_id."_".$policyCoverage->id }}" aria-expanded="true" aria-controls="collapse{{ $policyCoverage->risk_address_id."_".$policyCoverage->id }}">
                                    {{ $policyCoverage->coverage['s_CoverageCode'] }}
                                </button>
                            </h2>
                            <div id="collapse{{ $policyCoverage->risk_address_id."_".$policyCoverage->id }}" class="accordion-collapse collapse {{ static::COVERSGEACCORDIONCOLLAPSE }}" aria-labelledby="heading{{ $policyCoverage->risk_address_id."_".$policyCoverage->id }}" data-bs-parent="#riskAccordion_{{$policyCoverage->risk_address_id}}" wire:ignore.self>
                                <div class="accordion-body show" wire:ignore.self>
                                    <div>
                                        @if(isset($this->policyCoverageEntity[$policyCoverage->id]['Vehicle']))
                                            <h4>
                                                Vehicle : {{ $this->AllVehicles[$this->policyCoverageEntity[$policyCoverage->id]['Vehicle']] ?? '' }}
                                            </h4>
                                            <hr>
                                        @endif
                                        @if(isset($this->policyCoverageEntity[$policyCoverage->id]['Member']))
                                            <h4>
                                                Member : {{ $this->AllBeneficiaries[$this->policyCoverageEntity[$policyCoverage->id]['Member']] ?? '' }}
                                            </h4>
                                            <hr>
                                        @endif
                                        @if(isset($this->policyCoverageEntity[$policyCoverage->id]['Device']))
                                            <h4>
                                                Device : {{ $this->AllDevices[$this->policyCoverageEntity[$policyCoverage->id]['Device']] ?? '' }}
                                            </h4>
                                            <hr>
                                        @endif
                                    </div>
                                    <div class="row">
                                        <div class="col-sm-4">
                                            <b>Coverage</b>
                                        </div>
                                        <div class="col-sm-4">
                                            <b>Limit</b>
                                        </div>
                                        <div class="col-sm-4">
                                            <b>Premium</b>
                                        </div>
                                    </div><br>
                                    @if($policyCoverage->coverage['s_CoverageCode'] == "MOTORTRADERSEXTERNAL")
                                    @php $motorTradersData = \AlphaDirect\Models\MotorTraders::where('policy_coverage_id',$policyCoverage->id)->orderBy('id', 'asc')->get(); @endphp
                                    @foreach($motorTradersData as $newIndex => $tradersData)
                                    @if($tradersData->type_of_cover != "TPMotorTradersExternal")
                                 
                                    <div class="row">                                            
                                    <div class="col-sm-4">
                                    <div class="form-floating mb-3">                          
                                     <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> Loss or damage </p>
                                     </div>
                                    </div>
                                    <div class="col-sm-4">
                                    <div class="form-floating mb-3">     
                                         <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{number_format((float)$tradersData->loss_or_damage_coverage_value ?? "", 2, '.', ',')}} </p>
                                         </div>
                                         </div>
                                    <div class="col-sm-4">
                                    <div class="form-floating mb-3">     
                                          <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{number_format((float)$tradersData->loss_or_damage_calculated_value ?? "", 2, '.', ',')}} </p>
                                    </div>
                                    </div>
                                    </div>
                                    <div class="row">                                            
                                    <div class="col-sm-4">
                                    <div class="form-floating mb-3">  
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> Third party liability </p>
                                    </div>
                                    </div>
                                    <div class="col-sm-4">
                                    <div class="form-floating mb-3">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{number_format((float)$tradersData->third_party_liability_coverage_value ?? "", 2, '.', ',')}} </p>
                                    </div>
                                    </div>
                                    <div class="col-sm-4">
                                    <div class="form-floating mb-3">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{number_format((float)$tradersData->third_party_liability_calculated_value ?? "", 2, '.', ',')}} </p>
                                    </div>
                                    </div>
                                    </div>
                                    <div class="row">                                            
                                    <div class="col-sm-4">
                                    <div class="form-floating mb-3"> 
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> Medical benefits </p>
                                    </div>
                                    </div>
                                    <div class="col-sm-4">
                                    <div class="form-floating mb-3"> 
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{number_format((float)$tradersData->medical_benefits_coverage_value ?? "", 2, '.', ',')}} </p>
                                    </div>
                                    </div>
                                    <div class="col-sm-4">
                                    <div class="form-floating mb-3">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{number_format((float)$tradersData->medical_benefits_calculated_value ?? "", 2, '.', ',')}} </p>
                                    </div>
                                    </div>
                                    </div>
                                    @elseif($tradersData->type_of_cover == "TPMotorTradersExternal")
                                    <div class="row">                                            
                                    <div class="col-sm-4">
                                    <div class="form-floating mb-3">                                            <td colspan="3">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> Third party liability </p>
                                    </div>
                                    </div>
                                    <div class="col-sm-4">
                                    <div class="form-floating mb-3">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{number_format((float)$tradersData->third_party_liability_coverage_value ?? "", 2, '.', ',')}} </p>
                                    </div>
                                    </div>
                                    <div class="col-sm-4">
                                    <div class="form-floating mb-3">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{number_format((float)$tradersData->third_party_liability_calculated_value ?? "", 2, '.', ',')}} </p>
                                    </div>
                                    </div>
                                    </div>
                                    @endif
                                    @endforeach
                                    @endif
                                    @if($policyCoverage->coverage['s_CoverageCode'] == "MOTORTRADERSINTERNAL")
                                    @php                                     
                                    $motorTradersData = \AlphaDirect\Models\MotorTradersInternal::where('policy_coverage_id',$policyCoverage->id)->orderBy('id', 'asc')->get();
                                    @endphp
                                    @foreach($motorTradersData as $newIndex => $tradersData)
                                    @if($tradersData->type_of_cover != "TPMotorTradersInternal")
                                        {{-- Start Description --}}
                                 
                                    <div class="row">
                                            <div class="col-sm-4">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> Loss or damage </p>
                                            </div>
                                            <div class="col-sm-4">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{ $tradersData->loss_or_damage_coverage_value ?? 0.00 }} </p>
                                            </div>
                                            <div class="col-sm-4">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{ $tradersData->loss_or_damage_calculated_value ?? 0.00 }} </p>
                                            </div>
                                    </div>
                                    <div class="row">
                                            <div class="col-sm-4">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> Third party liability </p>
                                            </div>
                                            <div class="col-sm-4">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{ $tradersData->third_party_liability_coverage_value ?? 0.00 }} </p>
                                            </div>
                                            <div class="col-sm-4">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{ $tradersData->third_party_liability_calculated_value ?? 0.00 }} </p>
                                            </div>
                                    </div>
                                    <div class="row">
                                            <div class="col-sm-4">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> Medical benefits </p>
                                            </div>
                                            <div class="col-sm-4">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{ $tradersData->medical_benefits_coverage_value ?? 0.00 }} </p>
                                            </div>
                                            <div class="col-sm-4">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{ $tradersData->medical_benefits_calculated_value ?? 0.00 }} </p>
                                            </div>
                                    </div>
                                            {{-- End Description --}}
                                   
                                    @elseif($tradersData->type_of_cover == "TPMotorTradersInternal")
                               
                                    <div class="row">
                                            <div class="col-sm-4">
                                                 <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> Third party liability </p>
                                            </div>
                                            <div class="col-sm-4">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{ $tradersData->third_party_liability_coverage_value ?? 0.00 }} </p>
                                            </div>
                                            <div class="col-sm-4">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{ $tradersData->third_party_liability_calculated_value ?? 0.00 }} </p>
                                            </div>
                                    </div>
                                    <div class="row">
                                            <div class="col-sm-4">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:#2e77c3;">Total </p>
                                            </div>
                                            <div class="col-sm-4">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:#2e77c3;"> P {{ $tradersData->third_party_liability_coverage_value ?? 0.00 }}</p>
                                            </div>
                                            <div class="col-sm-4">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:#2e77c3;"> P {{ $tradersData->third_party_liability_calculated_value ?? 0.00 }}</p>
                                            </div>
                                    </div>
                                    @endif
                                    @endforeach
                                    @endif
                                    @if( $policyCoverage->coverage['s_CoverageCode']  == "PERSONALMOTOR" || $policyCoverage->coverage['s_CoverageCode']  == "COMMERCIALMOTOR")

                                    @php
                                    $personalMotorData = \AlphaDirect\Models\Motor::where('policy_coverage_id',$policyCoverage->id)->orderBy('id', 'asc')->get();
                                    @endphp
                                    @foreach($personalMotorData as $personalMotorDatas)
                                    <div class="row">
                                            <div class="col-sm-4">
                                                <div class="form-floating mb-3">
                                                    {{$personalMotorDatas['vehicle_name'] ?? ""}}
                                                </div>
                                            </div>
                                            <div class="col-sm-4">
                                                <div class="form-floating mb-3">
                                                    {{ $personalMotorDatas['coverage_value_main'] ?? ""}}
                                                </div>
                                            </div>
                                            <div class="col-sm-4">
                                                <div class="form-floating mb-3">
                                                    {{ $personalMotorDatas['calculated_value_main'] ?? "" }}
                                                </div>
                                            </div>
                                    </div>  
                                    @endforeach      
                                    @endif
                                    @foreach(($coverage['subCoverage']) as $subCoverage)
                                        @php
                                            $preFixModel = 'policyCoverageDetail'.'.'.$policyCoverage->id.'.'.$subCoverage->id.'.';
                                            $policyCoverageData = $this->policyCoverageDetail[$policyCoverage->id][$subCoverage->id] ?? [];
                                            if(isset($policyCoverageData['limit_id'])){
                                                $tbCvgpclimits = AlphaDirect\Models\TbCvgpcLimits::where('n_PCLimitId_PK',$policyCoverageData['limit_id'])->first(['s_LimitScreenName']);
                                            }
    
                                        if (isset($policyCoverageData['calculated_value']) || isset($policyCoverageData['limit_id'])){
                                        @endphp
                                        <div class="row">
                                            <div class="col-sm-4">
                                                <div class="form-floating mb-3">
                                                    {{ $subCoverage['s_ScreenName'] }} 
                                                </div>
                                            </div>
                                            <div class="col-sm-4">
                                                <div class="form-floating mb-3">
                                                    @if (isset($tbCvgpclimits))
                                                    {{ $tbCvgpclimits->s_LimitScreenName ?? 'P 0.00'}}
                                                    @else
                                                        <p> P {{ number_format($policyCoverageData['coverage_value']  ?? "", 2, '.', ',') }}
                                                        @if($subCoverage['s_CoverageCode'] == 'BUILDING')

                                                          @if($policyCoverageData['ratefactor_type'] != null)
                                                            <span style="margin-left: 12%"> No of month - {{ $policyCoverageData['ratefactor_type'] ?? ''}}</span>
                                                          @endif

                                                        @elseif($subCoverage['s_CoverageCode'] == 'MONEYCAPITALSUM')

                                                          @if($policyCoverageData['ratefactor_value'] != null)
                                                            <span style="margin-left: 12%"> No of Employees - {{ $policyCoverageData['ratefactor_value'] ?? ''}}</span>
                                                          @endif

                                                        @elseif($subCoverage['s_CoverageCode']=='PUB_LEGALDEFENCE' || $subCoverage['s_CoverageCode ']=='PUB_WRONGARREST')

                                                          @if($policyCoverageData['ratefactor_value'] != null)
                                                            <span style="margin-left: 12%"> No of Persons - {{ $policyCoverageData['ratefactor_value'] ?? ''}}</span>
                                                          @endif

                                                        @elseif($subCoverage['s_CoverageCode']=='FIRESTOCK')

                                                          @if($policyCoverageData['ratefactor_type'] != null)
                                                            <span style="margin-left: 12%"> Decl M/Q/A - {{ $policyCoverageData['ratefactor_type'] ?? ''}}</span>
                                                          @endif

                                                        @elseif($subCoverage['s_CoverageCode']=='BUSI_WAGES')

                                                          @if($policyCoverageData['ratefactor_value'] != null)
                                                            <span style="margin-left: 12%"> No of Weeks - {{ $policyCoverageData['ratefactor_value'] ?? ''}}</span>
                                                          @endif

                                                        @elseif($subCoverage['s_CoverageCode']=='MONEYSEASONALINC1' || $subCoverage['s_CoverageCode']=='MONEYSEASONALINC2')

                                                          @if($policyCoverageData['ratefactor_value'] != null)
                                                            <span style="margin-left: 12%"> Date - {{ $policyCoverageData['ratefactor_value'] ?? ''}}</span>
                                                          @endif

                                                        @elseif($subCoverage['s_CoverageCode']=='THEFTSUMINS')

                                                            @if ($policyCoverageData['ratefactor_type'] != null)
                                                            <span style="margin-left: 12%">Basis Of Cover - {{ $policyCoverageData['ratefactor_type'] ?? ''}}</span>
                                                            @endif

                                                        @endif
                                                    </p>
                                                    @endif
                                                </div>
                                            </div>
                                            <div class="col-sm-4">
                                                <div class="form-floating mb-3">
                                                    P {{ number_format($policyCoverageData['calculated_value']  ?? "", 2, '.', ',') }}
                                                </div>
                                            </div>
                                        </div>
                                        @php
                                        }
                                        @endphp

                                    @endforeach

                                    {{-- Extention --}}

                                    @if(count($coverage['allExtention']) > 0)
                                    <hr><b>Extentions</b><br><br>
                                    @foreach(($coverage['allExtention']) as $subExtention)
                                        @if($subExtention['s_ExtensionsGroupName'] == "Heading")
                                        <div class="row">
                                            <b>{{ $subExtention['s_CoverageName'] }}</b>
                                            <br><br>
                                        </div>
                                        @else
                                        <div class="row">
                                            <br><br>
                                        </div>
                                        @endif

                                        @php
                                            $preFixModel = 'policyExtentionDetail'.'.'.$policyCoverage->id.'.'.$subExtention->id.'.';
                                            $policyExtentionData = $this->policyExtentionDetail[$policyCoverage->id][$subExtention->id] ?? [];
                                            if(isset($policyExtentionData['extention_limit_id'])){
                                                $tbCvgpclimits = AlphaDirect\Models\TbCvgpcLimits::where('n_PCLimitId_PK',$policyExtentionData['extention_limit_id'])->first(['s_LimitScreenName']);
                                            }
                                        if (isset($policyExtentionData['extention_calculated_value']) || isset($policyExtentionData['extention_limit_id'])){
                                        @endphp

                                        @if($subExtention['s_ExtensionsGroupName'] != "Heading")
                                        <div class="row">
                                            <div class="col-sm-4">
                                                <div class="form-floating mb-3">
                                                    {{ $subExtention['s_CoverageName'] }}
                                                </div>
                                            </div>
                                            <div class="col-sm-4">
                                                <div class="form-floating mb-3">
                                                    @if (isset($tbCvgpclimits))
                                                             {{ $tbCvgpclimits->s_LimitScreenName ?? 'P 0.00'}}
                                                    @elseif ($policyExtentionData['extention_text_value'] != null)
                                                        <p>
                                                            {{ $policyExtentionData['extention_text_value']  ?? ''}}
                                                        </p>
                                                    @else
                                                        <p>
                                                            P {{ number_format($policyExtentionData['extention_coverage_value']  ?? "", 2, '.', ',') }}
                                                        </p>
                                                    @endif
                                                </div>
                                            </div>
                                            <div class="col-sm-4">
                                                <div class="form-floating mb-3">
                                                    P {{ number_format($policyExtentionData['extention_calculated_value']  ?? "", 2, '.', ',') }}
                                                </div>
                                            </div>
                                        </div>
                                        @endif
                                        @php
                                        }
                                        @endphp
                                    @endforeach
                                    @endif

                                    {{-- For Perils --}}

                                     @if(count($coverage['allBurglarAlarmWarranty']) > 0)
                                     <hr><b>Burglar Alarm Warranty</b><br><br>
                                     @foreach(($coverage['allBurglarAlarmWarranty']) as $subExtention)
                                         @php
                                             $preFixModel = 'policyExtentionDetail'.'.'.$policyCoverage->id.'.'.$subExtention->id.'.';
                                             $policyExtentionData = $this->policyExtentionDetail[$policyCoverage->id][$subExtention->id] ?? [];
                                             if(isset($policyExtentionData['extention_limit_id'])){
                                                 $tbCvgpclimits = AlphaDirect\Models\TbCvgpcLimits::where('n_PCLimitId_PK',$policyExtentionData['extention_limit_id'])->first(['s_LimitScreenName']);
                                             }
                                         if (isset($policyExtentionData['extention_calculated_value']) || isset($policyExtentionData['extention_limit_id'])){
                                         @endphp
                                         <div class="row">
                                             <div class="col-sm-4">
                                                 <div class="form-floating mb-3">
                                                     {{ $subExtention['s_CoverageName'] }}
                                                 </div>
                                             </div>
                                             <div class="col-sm-4">
                                                 <div class="form-floating mb-3">
                                                     @if (isset($tbCvgpclimits))
                                                              {{ $tbCvgpclimits->s_LimitScreenName ?? 'P 0.00'}}
                                                     @elseif ($policyExtentionData['extention_text_value'] != null)
                                                         <p>
                                                             {{ $policyExtentionData['extention_text_value']  ?? ''}}
                                                         </p>
                                                     @else
                                                         <p>
                                                             P {{ number_format($policyExtentionData['extention_coverage_value']  ?? "", 2, '.', ',') }}
                                                         </p>
                                                     @endif
                                                 </div>
                                             </div>
                                             <div class="col-sm-4">
                                                 <div class="form-floating mb-3">
                                                     P {{ number_format($policyExtentionData['extention_calculated_value']  ?? "", 2, '.', ',') }}
                                                 </div>
                                             </div>
                                         </div>
                                         @php
                                         }
                                         @endphp
                                     @endforeach
                                     @endif
                                     <hr>
                                    @if ($policyCoverage->coverage['s_CoverageCode'] == "MONEY")
                                        @if($this->policyCoverageMemorandaWarranty[$policyCoverage->id])
                                        <div class="row">
                                            <div class="col-sm-12">
                                                <div class="card bg-light shadow-sm">
                                                    <div class="card-header">
                                                        <h3 class="card-title">Coverage Memoranda Warranty</h3>
                                                    </div>
                                                    <div class="card-body card-scroll h-200px">
                                                        <div class="form-floating" >
                                                            <pre style="font-family:'Montserrat', sans-serif;font-weight: 500;font-style:normal;font-size:10px;
                                                            overflow-y:scroll; solid lightgray; padding:5px; white-space:pre-wrap;">
                                                            {{ $this->policyCoverageMemorandaWarranty[$policyCoverage->id] }}
                                                            </pre>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <hr>
                                        @endif
                                        @if($this->policyCoverageCashWarranty[$policyCoverage->id])
                                        <div class="row">
                                            <div class="col-sm-12">
                                                <div class="card bg-light shadow-sm">
                                                    <div class="card-header">
                                                        <h3 class="card-title">Coverage Cash Warranty</h3>
                                                    </div>
                                                    <div class="card-body card-scroll h-200px">
                                                        <div class="form-floating" >
                                                            <pre style="font-family:'Montserrat', sans-serif;font-weight: 500;font-style:normal;font-size:10px;
                                                            overflow-y:scroll; solid lightgray; padding:5px; white-space:pre-wrap;">
                                                            {{ $this->policyCoverageCashWarranty[$policyCoverage->id] }}
                                                            </pre>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <hr>
                                        @endif
                                    @endif

                                    @if ($policyCoverage->coverage['s_CoverageCode'] == "OFFICECONTENTS" || $policyCoverage->coverage['s_CoverageCode'] == "THEFT" || $policyCoverage->coverage['s_CoverageCode'] == "HOUSEHOLDERS" || $policyCoverage->coverage['s_CoverageCode'] == "ELECTRONICEQUIPMENT" || $policyCoverage->coverage['s_CoverageCode'] == "MONEY")
                                        @if($this->policyCoverageBurglarWarranty[$policyCoverage->id])
                                        <div class="row">
                                            <div class="col-sm-12">
                                                <div class="card bg-light shadow-sm">
                                                    <div class="card-header">
                                                        <h3 class="card-title">Burglar Alarm Warranty</h3>
                                                    </div>
                                                    <div class="card-body card-scroll h-200px">
                                                        <div class="form-floating" >
                                                            <pre style="font-family:'Montserrat', sans-serif;font-weight: 500;font-style:normal;font-size:10px;
                                                            overflow-y:scroll; solid lightgray; padding:5px; white-space:pre-wrap;">
                                                            {{ $this->policyCoverageBurglarWarranty[$policyCoverage->id] }}
                                                            </pre>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <hr>
                                        @endif
                                    @endif

                                    @if ($policyCoverage->coverage['s_CoverageCode'] == "MOTORCOMPREHESIVE" || $policyCoverage->coverage['s_CoverageCode'] == "MOTORTHIRDPARTYFIREANDTHE" || $policyCoverage->coverage['s_CoverageCode'] == "MOTORTHIRDPARTYONLY")
                                        @if($this->policyCoverageEndorsements[$policyCoverage->id])
                                        <div class="row">
                                            <div class="col-sm-12">
                                                <div class="card bg-light shadow-sm">
                                                    <div class="card-header">
                                                        <h3 class="card-title">Endorsements</h3>
                                                    </div>
                                                    <div class="card-body card-scroll h-200px">
                                                        <div class="form-floating" >
                                                            <pre style="font-family:'Montserrat', sans-serif;font-weight: 500;font-style:normal;font-size:10px;
                                                            overflow-y:scroll; solid lightgray; padding:5px; white-space:pre-wrap;">
                                                            {{ $this->policyCoverageEndorsements[$policyCoverage->id] }}
                                                            </pre>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <hr>
                                        @endif
                                    @endif

                                    @if($this->policyCoverageNote[$policyCoverage->id])
                                    <div class="row">
                                            <div class="col-sm-12">
                                                <div class="card bg-light shadow-sm">
                                                    <div class="card-header">
                                                        <h3 class="card-title">Note</h3>
                                                    </div>
                                                    <div class="card-body card-scroll h-200px">
                                                        <div class="form-floating" >
                                                            <pre style="font-family:'Montserrat', sans-serif;font-weight: 500;font-style:normal;font-size:10px;
                                                            overflow-y:scroll; solid lightgray; padding:5px; white-space:pre-wrap;">
                                                            {{ $this->policyCoverageNote[$policyCoverage->id] }}
                                                            </pre>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                    @if(isset($this->specifiedItems[$coverage['id']]) and !empty($this->specifiedRow[$policyCoverage->id]))
                                        <br><br>
                                        <div class="row">
                                            <div class="col-sm-12">
                                                <div class="card bg-light shadow-sm">
                                                    <div class="card-header">
                                                        <h3 class="card-title">Miscellaneous Items </h3>
                                                    </div>
                                                    <div class="card-body card-scroll h-200px">
                                                        @foreach($this->specifiedRow[$policyCoverage->id] ?? [] as $key => $value)
                                                            <div class="row">
                                                                <div class="col-sm-12">
                                                                    <div class="form-floating mb-3">
                                                                        {{ $this->specifiedItems[$policyCoverage->coverage_id][($this->specified_items[$policyCoverage->id][$key]['selected_item'] ?? '')] ?? '' }} :
                                                                      P {{ number_format(($this->specified_items[$policyCoverage->id][$key]['sum_insured'] ?? ''),2) }}
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
                @php
                    if (count($this->PolicyCoverages)){
                        echo "</div></div></div></div>";
                    }
                @endphp

            </div>

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
                                <!-- {{--                                @livewire('policy.add-device', ['policy' => $policy,'isPrevious'=>false,'termId'=>$termId,'actionId'=>$actionId])--}} -->
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
