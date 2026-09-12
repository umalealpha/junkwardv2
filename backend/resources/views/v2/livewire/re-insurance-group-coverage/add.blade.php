<div>

    <x-slot name="breadcrum">
        <!--begin::Page title-->
        <div class="page-title d-flex flex-column justify-content-center flex-wrap me-3">
            <!--begin::Title-->
            <h1 class="page-heading d-flex text-dark fw-bold fs-3 flex-column justify-content-center my-0">Create Reinsurance Group Coverage </h1>
            <!--end::Title-->
            <!--begin::Breadcrumb-->
            <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 my-0 pt-1">
                <!--begin::Item-->
                <li class="breadcrumb-item text-muted">
                    <a href="" class="text-muted text-hover-primary">Home</a>
                </li>
                <!--end::Item-->
                <!--begin::Item-->
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-400 w-5px h-2px"></span>
                </li>
                <!--end::Item-->
                <!--begin::Item-->
                <li class="breadcrumb-item text-muted">
                    <a href="{{ route('reinsurance-group-coverage') }}" class="text-muted text-hover-primary">Reinsurance Group Coverage </a>
                </li>
                <!--end::Item-->
                <!--begin::Item-->
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-400 w-5px h-2px"></span>
                </li>
                <!--end::Item-->
                <!--begin::Item-->
                <li class="breadcrumb-item text-muted">Add</li>
                <!--end::Item-->
            </ul>
            <!--end::Breadcrumb-->
        </div>
        <!--end::Page title-->
    </x-slot>
    <!--begin::Content container-->
    <div class="app-container container-fluid" wire:loading.class="page-loading" x-data>
        <!--begin::Page loading(append to body)-->
        <div class="page-loader flex-column bg-dark bg-opacity-25 ">
            <span class="spinner-border text-primary" role="status"></span>
            <span class="text-gray-800 fs-6 fw-semibold mt-5">Loading...</span>
        </div>
        <!--end::Page loading-->
        <form wire:submit.prevent="submit(Object.fromEntries(new FormData($event.target)))" autocomplete="off">

            <!--begin::Card-->
            <div class="card">
                <!--begin::Card body-->
                <div class="card-body">
                    <div class="row">
                        <div class="{{ $colSize }}">
                            <div class="form-floating mb-3">
                                <x-form-input type="text" name="reinsurancegroup.group_code"  placeholder="Group Code" wire:model.defer='reinsurancegroup.group_code'/>
                                <x-form-label for="reinsurancegroup.group_code" required value="{{ __('Group Code') }}"/>
                                <x-form-input-error name="reinsurancegroup.group_code"/>
                            </div>
                        </div>

                        <div class="{{ $colSize }}">
                            <div class="form-floating mb-3">
                                <x-form-input type="text" name="reinsurancegroup.group_name"  placeholder="Group Name" wire:model.defer='reinsurancegroup.group_name'/>
                                <x-form-label for="reinsurancegroup.group_name" required value="{{ __('Group Name') }}"/>
                                <x-form-input-error name="reinsurancegroup.group_name"/>
                            </div>
                        </div>

                        <div class="{{ $colSize }}">
                            <div class="form-floating mb-3">
                                <x-select aria-label="Select Product" name="reinsurancegroup.product_id"
                                    :options="$this->Products"  wire:model.lazy='reinsurancegroup.product_id'  />
                                <x-form-label for="reinsurancegroup.product_id" required value="{{ __('Select Product') }}"/>
                                <x-form-input-error name="reinsurancegroup.product_id"/>
                            </div>
                        </div>

                        <div class="{{ $colSize }}">
                            <div class="form-floating mb-3">
                                <x-check-box label="{{ __('Status') }}" name="reinsurancegroup.status" id="reinsurancegroup.status" wire:model.lazy="reinsurancegroup.status"/>
                                <x-form-input-error name="reinsurancegroup.status"/>
                            </div>
                        </div>
                    </div>

                    @if(count($this->seletedCoverages))
                        <div class="row">
                            <div class="col-sm-12">
                                <div class="table-responsive">
                                    <table class="table align-middle table-row-dashed fs-6 gy-5 dataTable no-footer">
                                        <thead>
                                            <tr class="text-start fw-bold fs-7 text-uppercase gs-0"  width="100%">
                                                <th  width="20%">COVERAGE NAME</th>
                                                <th  width="20%">SI/PREMIUM</th>
                                                <th  width="20%">RI LIMIT</th>
                                                <th  width="20%">LIMIT</th>
                                                <th  width="20%"></th>
                                            </tr>
                                        </thead>
                                        <tbody class="fw-semibold text-gray-600">
                                        @foreach($this->seletedCoverages as $selectedId => $coverageCode)
                                            <tr class="" width="100%">
                                                <td  colspan=5 style="color:black;padding-top: 0;padding-bottom: 0;">
                                                {{$coverageCode ?? ""}}
                                                </td>
                                            </tr>
                                            @if(count($this->getProductSubCoverages($coverageCode))>0)
                                                @foreach($this->getProductSubCoverages($coverageCode) as $subcoverage)
                                                <tr width="100%">
                                                    <td width="20%" style="padding-top: 0;padding-bottom: 0;">
                                                        {!!$subcoverage->s_ScreenName ?? ''!!}
                                                   </td>
                                                    <td width="20%" style="padding-top: 0;padding-bottom: 0;">
                                                        <div class="form-floating mb-3">
                                                            <select class = 'form-select form-select-solid' name="{{ $subcoverage->id }}_FIELD1" aria-label="Select SI/PREMIUM">
                                                                <option value="">- Select -</option>
                                                                <option value="1">SumInsured</option>
                                                                <option value="2" >Premium</option>
                                                            </select>
                                                            <x-form-label for="{{ $subcoverage->id }}_FIELD1" value="{{ __('Select SI/PREMIUM') }}"/>
                                                            <x-form-input-error name="{{ $subcoverage->id }}_FIELD1"/>
                                                        </div>
                                                    </td>
                                                    <td width="20%" style="padding-top: 0;padding-bottom: 0;">
                                                        <div class="form-floating mb-3">
                                                            <select class = 'form-select form-select-solid show_hide_dropdown' onChange="show_hide_dropdown(this,{{$subcoverage->id}})" name="{{ $subcoverage->id }}_FIELD2" aria-label="Select RI LIMIT">
                                                                <option value="">- Select -</option>
                                                                <option value="1" >SumInsured</option>
                                                                <option value="2" >Skip</option>
                                                                <option value="3" >Other</option>
                                                            </select>
                                                            <x-form-label for="{{ $subcoverage->id }}_FIELD2" value="{{ __('Select RI LIMIT') }}"/>
                                                            <x-form-input-error name="{{ $subcoverage->id }}_FIELD2"/>
                                                        </div>
                                                    </td>
                                                    <td width="20%" style="padding-top: 0;padding-bottom: 0;display:none;" id="show_hide_dropdown_{{ $subcoverage->id }}">
                                                        <div class="form-floating mb-3">
                                                            <input type="text" class="form-control" name="{{$subcoverage->id}}_FIELD3"
                                                            id="{{$subcoverage->id}}_FIELD4"  value="" x-mask:dynamic="$money($input)"/>
                                                            <x-form-label for="{{$subcoverage->id}}_FIELD3" required value="{{ __('Limit') }}"/>
                                                            <x-form-input-error name="{{$subcoverage->id}}_FIELD3"/>
                                                        </div>
                                                    </td>
                                                    <td width="20%" style="padding-top: 0;padding-bottom: 0;">
                                                    </td>
                                                </tr>
                                                @endforeach
                                            @elseif($coverageCode == 'COMMERCIALMOTOR')
                                            @php
                                                $commercialmotor = array(
                                                    "comprehensive" => "Comprehensive", 
                                                    "third_party_only" => 'Third Party Only', 
                                                    "Third_fire_and_theft" => 'Third Fire And Theft'
                                                    );                                                                                         
                                            @endphp
                                                @foreach($commercialmotor as $key => $subcoverage)          
                                                <tr width="100%">
                                                        <td width="20%" style="padding-top: 0;padding-bottom: 0;"> 
                                                        <input type="hidden" name="{{ $key }}_coverage_name_motor_comm" value="{{ $subcoverage }}"/>    
                                                        {{ $subcoverage }}</td>
                                                        <td width="20%" style="padding-top: 0;padding-bottom: 0;">
                                                            <div class="form-floating mb-3">
                                                                <select class = 'form-select form-select-solid' name="{{ $key }}_si_premium_motor_comm" aria-label="Select SI/PREMIUM">
                                                                    <option value="">- Select -</option>
                                                                    <option value="1">SumInsured</option>
                                                                    <option value="2" >Premium</option>
                                                                </select>
                                                                <x-form-label for="{{ $key }}_si_premium_motor_comm" value="{{ __('Select SI/PREMIUM') }}"/>
                                                                <x-form-input-error name="{{ $key }}_si_premium_motor_comm"/>
                                                            </div>
                                                        </td>
                                                        <td width="20%" style="padding-top: 0;padding-bottom: 0;">
                                                            <div class="form-floating mb-3">
                                                                <select class = 'form-select form-select-solid show_hide_dropdown' onChange="show_hide_dropdown(this,{{$key}})" name="{{ $key }}_ri_limit_motor_comm" aria-label="Select RI LIMIT">
                                                                    <option value="">- Select -</option>
                                                                    <option value="1" >SumInsured</option>
                                                                    <option value="2" >Skip</option>
                                                                    <option value="3" >Other</option>
                                                                </select>
                                                                <x-form-label for="{{ $key }}_ri_limit_motor_comm" value="{{ __('Select RI LIMIT') }}"/>
                                                                <x-form-input-error name="{{ $key }}_ri_limit_motor_comm"/>
                                                            </div>
                                                        </td>
                                                        <td width="20%" style="padding-top: 0;padding-bottom: 0;display:none;" id="show_hide_dropdown_{{ $key }}">
                                                            <div class="form-floating mb-3">
                                                                <input type="text" class="form-control" name="{{$key}}_limit_value_motor_comm"
                                                                id="{{$key}}_limit_value_motor_comm"  value="" x-mask:dynamic="$money($input)"/>
                                                                <x-form-label for="{{$key}}_limit_value_motor_comm" required value="{{ __('Limit') }}"/>
                                                                <x-form-input-error name="{{$key}}_limit_value_motor_comm"/>
                                                            </div>
                                                        </td>
                                                        <td width="20%" style="padding-top: 0;padding-bottom: 0;">
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            @elseif($coverageCode == 'MOTORTRADERSEXTERNAL')
                                            @php
                                                $motortradersexternal = array(
                                                    "comprehensive" => "Comprehensive", 
                                                    "third_party_only" => 'Third Party Only', 
                                                    "Third_fire_and_theft" => 'Third Fire And Theft'
                                                    );                                                                                         
                                            @endphp
                                                @foreach($motortradersexternal as $key => $subcoverage)          
                                                <tr width="100%">
                                                        <td width="20%" style="padding-top: 0;padding-bottom: 0;"> 
                                                        <input type="hidden" name="{{ $key }}_coverage_name_motro_traders_ext" value="{{ $subcoverage }}"/>    
                                                        {{ $subcoverage }}</td>
                                                        <td width="20%" style="padding-top: 0;padding-bottom: 0;">
                                                            <div class="form-floating mb-3">
                                                                <select class = 'form-select form-select-solid' name="{{ $key }}_si_premium_motro_traders_ext" aria-label="Select SI/PREMIUM">
                                                                    <option value="">- Select -</option>
                                                                    <option value="1">SumInsured</option>
                                                                    <option value="2" >Premium</option>
                                                                </select>
                                                                <x-form-label for="{{ $key }}_si_premium_motro_traders_ext" value="{{ __('Select SI/PREMIUM') }}"/>
                                                                <x-form-input-error name="{{ $key }}_si_premium_motro_traders_ext"/>
                                                            </div>
                                                        </td>
                                                        <td width="20%" style="padding-top: 0;padding-bottom: 0;">
                                                            <div class="form-floating mb-3">
                                                                <select class = 'form-select form-select-solid show_hide_dropdown' onChange="show_hide_dropdown(this,{{$key}})" name="{{ $key }}_ri_limit_motro_traders_ext" aria-label="Select RI LIMIT">
                                                                    <option value="">- Select -</option>
                                                                    <option value="1" >SumInsured</option>
                                                                    <option value="2" >Skip</option>
                                                                    <option value="3" >Other</option>
                                                                </select>
                                                                <x-form-label for="{{ $key }}_ri_limit_motro_traders_ext" value="{{ __('Select RI LIMIT') }}"/>
                                                                <x-form-input-error name="{{ $key }}_ri_limit_motro_traders_ext"/>
                                                            </div>
                                                        </td>
                                                        <td width="20%" style="padding-top: 0;padding-bottom: 0;display:none;" id="show_hide_dropdown_{{ $key }}">
                                                            <div class="form-floating mb-3">
                                                                <input type="text" class="form-control" name="{{$key}}_limit_value_motro_traders_ext"
                                                                id="{{$key}}_limit_value_motro_traders_ext"  value="" x-mask:dynamic="$money($input)"/>
                                                                <x-form-label for="{{$key}}_limit_value_motro_traders_ext" required value="{{ __('Limit') }}"/>
                                                                <x-form-input-error name="{{$key}}_limit_value_motro_traders_ext"/>
                                                            </div>
                                                        </td>
                                                        <td width="20%" style="padding-top: 0;padding-bottom: 0;">
                                                        </td>
                                                    </tr>
                                                @endforeach
                                                @elseif($coverageCode == 'MOTORTRADERSINTERNAL')
                                            @php
                                                $motortradersinternal = array(
                                                    "comprehensive" => "Comprehensive", 
                                                    "third_party_only" => 'Third Party Only', 
                                                    "Third_fire_and_theft" => 'Third Fire And Theft'
                                                    );                                                                                         
                                            @endphp
                                                @foreach($motortradersinternal as $key => $subcoverage)          
                                                <tr width="100%">
                                                <td width="20%" style="padding-top: 0;padding-bottom: 0;"> 
                                                <input type="hidden" name="{{ $key }}_coverage_name_motor_traders_int" value="{{ $subcoverage }}"/>    
                                                {{ $subcoverage }}</td>
                                                <td width="20%" style="padding-top: 0;padding-bottom: 0;">
                                                    <div class="form-floating mb-3">
                                                        <select class = 'form-select form-select-solid' name="{{ $key }}_si_premium_motor_traders_int" aria-label="Select SI/PREMIUM">
                                                            <option value="">- Select -</option>
                                                            <option value="1">SumInsured</option>
                                                            <option value="2" >Premium</option>
                                                        </select>
                                                        <x-form-label for="{{ $key }}_si_premium_motor_traders_int" value="{{ __('Select SI/PREMIUM') }}"/>
                                                        <x-form-input-error name="{{ $key }}_si_premium_motor_traders_int"/>
                                                    </div>
                                                </td>
                                                <td width="20%" style="padding-top: 0;padding-bottom: 0;">
                                                    <div class="form-floating mb-3">
                                                        <select class = 'form-select form-select-solid show_hide_dropdown' onChange="show_hide_dropdown(this,{{$key}})" name="{{ $key }}_ri_limit_motor_traders_int" aria-label="Select RI LIMIT">
                                                            <option value="">- Select -</option>
                                                            <option value="1" >SumInsured</option>
                                                            <option value="2" >Skip</option>
                                                            <option value="3" >Other</option>
                                                        </select>
                                                        <x-form-label for="{{ $key }}_ri_limit_motor_traders_int" value="{{ __('Select RI LIMIT') }}"/>
                                                        <x-form-input-error name="{{ $key }}_ri_limit_motor_traders_int"/>
                                                    </div>
                                                </td>
                                                <td width="20%" style="padding-top: 0;padding-bottom: 0;display:none;" id="show_hide_dropdown_{{ $key }}">
                                                    <div class="form-floating mb-3">
                                                        <input type="text" class="form-control" name="{{$key}}_limit_value_motor_traders_int"
                                                        id="{{$key}}_limit_value_motor_traders_int"  value="" x-mask:dynamic="$money($input)"/>
                                                        <x-form-label for="{{$key}}_limit_value_motor_traders_int" required value="{{ __('Limit') }}"/>
                                                        <x-form-input-error name="{{$key}}_limit_value_motor_traders_int"/>
                                                    </div>
                                                </td>
                                                <td width="20%" style="padding-top: 0;padding-bottom: 0;">
                                                </td>
                                            </tr>
                                                @endforeach
                                            @else
                                            <tr><td colspan=5>Coverages are not present.</td></tr>
                                            @endif
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
                <!--end::Card body-->

                <div class="card-footer text-center">
                    <input type="submit" class="btn btn-primary" value="Submit">
                    <a href="{{ route('reinsurance-group-coverage') }}" class="btn btn-secondary" value="Cancel">Cancel</a>
                </div>
            </div>
            <!--end::Card-->
        </form>
    </div>
    <!--end::Content container-->
</div>
@push('scripts')
    <script>
        $("show_hide_dropdown").change(function(){
            alert("The text has been changed.");
        });
        function show_hide_dropdown(obj,id){
            if ($(obj).val()==3){
                $("#show_hide_dropdown_"+id).show();
            }else{
                $("#show_hide_dropdown_"+id).hide();
            }
        }
    </script>
@endpush
