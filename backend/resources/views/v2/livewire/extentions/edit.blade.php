<div>
    <x-slot name="breadcrum">
        <!--begin::Page title-->
        <div class="page-title d-flex flex-column justify-content-center flex-wrap me-3">
            <!--begin::Title-->
            <h1 class="page-heading d-flex text-dark fw-bold fs-3 flex-column justify-content-center my-0">Ext,Excess & Misc</h1>
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
                    <a href="{{route('extentions')}}" class="text-muted text-hover-primary">Ext,Excess & Misc</a>
                </li>
                <!--end::Item-->
                <!--begin::Item-->
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-400 w-5px h-2px"></span>
                </li>
                <!--end::Item-->
                <!--begin::Item-->
                <li class="breadcrumb-item text-muted">
					@if($edit)
						Edit
					@else
						Add
					@endif
				</li>
                <!--end::Item-->
            </ul>
            <!--end::Breadcrumb-->
        </div>
        <!--end::Page title-->
    </x-slot>
    <!--begin::Content container-->
    <div  class="app-container container-fluid" wire:loading.class="page-loading">
        <!--begin::Page loading(append to body)-->
        <div class="page-loader flex-column bg-dark bg-opacity-25 ">
            <span class="spinner-border text-primary" role="status"></span>
            <span class="text-gray-800 fs-6 fw-semibold mt-5">Loading...</span>
        </div>
        <!--end::Page loading-->
		<!--begin::Card-->
		<div class="card">
			<div class="card-header">
				<div class="card-title m-0">
					<h3 class="fw-bold m-0">Edit Ext,Excess & Misc</h3>
				</div>
			</div>
			<!--begin::Card body-->
			<div class="card-body"
				x-data="{
					row:@entangle('i')
				}"
			>
				<div class="row">
					<div class="col-sm-6">
						<div class="form-floating mb-3">
							<x-select-search wire:model.defer="coverage" aria-label="Select Coverage"
								:options="$this->CoveragesMaster" />
							<x-form-label for="coverage" required value="{{ __('Select Coverage') }}" />
							<x-form-input-error name="coverage"/>
						</div>
					</div>
					<div class="col-sm-6">
						<div class="form-floating mb-3">
							<x-select-search wire:model.lazy="subcoverage" aria-label="Select Sub Coverage"
								:options="$this->SubCoverages" />
							<x-form-label for="subcoverage" value="{{ __('Select Sub Coverage') }}" />
							<x-form-input-error name="subcoverage"/>
						</div>
					</div>
				</div>

				<div class="row">
			    	<div class="col-sm-6">
						<div class="form-floating mb-3">
                            <x-select wire:model.lazy="type" aria-label="Type"
                            :options="array('Extention'=>'Extention','Excess'=>'Excess','Memoranda'=>'Memoranda','FirstAmountPayable'=>'First Amount Payable','BurglarAlarmWarranty'=>'Burglar Alarm Warranty')"/>
							<x-form-label for="type" required value="{{ __('Select Type') }}" />
							<x-form-input-error name="type"/>
						</div>
					</div>
                    <div class="col-sm-6">
						<div class="form-floating mb-3">
                            <x-select wire:model.lazy="extention_type" aria-label="Type"
                            :options="array('NOEDIT'=>'INPUT','RADIO'=>'RADIO','DROPDOWN'=>'DROPDOWN','NUMBER'=>'NUMBER')"/>
							<x-form-label for="extention_type" required value="{{ __('Select Type') }}" />
							<x-form-input-error name="extention_type"/>
						</div>
					</div>
			    </div>

                <div class="row">
					<div class="col-sm-4">
						<div class="form-floating mb-3">
                            <x-select wire:model.lazy="s_ExtensionsGroupName" aria-label="Type"
                            :options="array('Main'=>'Main','Heading'=>'Heading')"/>
							<x-form-label for="s_ExtensionsGroupName" required value="{{ __('Select Extention Group') }}" />
							<x-form-input-error name="s_ExtensionsGroupName"/>
						</div>
					</div>
				</div>
                <br>

				{{-- <div class="row" x-show="{{count($inputs)}}===1">  --}}
                <div class="row">
					<div class="col-sm-12">
						<button class="btn text-white btn-info btn-sm float-right" wire:click.prevent="addRow({{$i}})">
							<i class="fa fa-plus"></i>
							Add More
						</button>
					</div>
				</div>

				@foreach($inputs as $key => $value)
					<hr><br>
					<div class="row">
						<x-form-input type="hidden" name="coverageId.{{ $value }}" wire:model.defer='coverageId.{{ $value }}'/>
						<div class="col-sm-3">
							<div class="form-floating mb-3">
								<x-form-input type="text" placeholder="Extention Name" wire:model.defer='s_CoverageName.{{ $value }}'/>
								<x-form-label for="s_CoverageName.{{ $value }}" required value="{{ __('Extention Name') }}"/>
								<x-form-input-error name="s_CoverageName.{{ $value }}"/>
							</div>
						</div>
						<div class="col-sm-3">
							<div class="form-floating mb-3">
								<x-form-input type="text" name="s_ScreenName.{{ $value }}"  placeholder="Screen Name" wire:model.defer='s_ScreenName.{{ $value }}'/>
								<x-form-label for="s_ScreenName.{{ $value }}" required value="{{ __('Screen Name') }}"/>
								<x-form-input-error name="s_ScreenName.{{ $value }}"/>
							</div>
						</div>
                        <div class="col-sm-6">
							<div class="form-floating mb-6">
								<x-form-text-area placeholder="Description" wire:model.defer='s_CoverageDesc.{{ $value }}'/>
								<x-form-label for="s_CoverageDesc.{{ $value }}" required value="{{ __('Description') }}"/>
								<x-form-input-error name="s_CoverageDesc.{{ $value }}"/>
							</div>
						</div>
					</div>
                    <div class="row">
                        <div class="col-sm-3">
                            <div class="form-floating mb-3">
                                <x-form-input type="number" placeholder="Rate" wire:model.defer='rate.{{ $value }}'/>
                                <x-form-label for="rate.{{ $value }}" required value="{{ __('Rate') }}"/>
                                <x-form-input-error name="rate.{{ $value }}"/>
                        {{--  <span style="color:red">Please enter numeric value only</span>--}}
                            </div>
                        </div>

                        <div class="col-sm-3">
							<div class="form-floating mb-3">
								<x-form-date type="text" class="kt_datepicker_1" id="d_EffectiveDt_{{ $value }}"  placeholder="Effective From" wire:model.defer='d_EffectiveDt.{{ $value }}'/>
								<x-form-label for="d_EffectiveDt_{{ $value }}" required value="{{ __('Effective From') }}"/>
								<x-form-input-error name="d_EffectiveDt.{{ $value }}"/>
							</div>
						</div>
						<div class="col-sm-3">
							<div class="form-floating mb-3">
								<x-form-date type="text" class="kt_datepicker_1" id="d_ExpirationDt_{{ $value }}"  placeholder="Effective To" wire:model.defer='d_ExpirationDt.{{ $value }}'/>
								<x-form-label for="d_ExpirationDt_{{ $value }}" required value="{{ __('Effective To') }}"/>
								<x-form-input-error name="d_ExpirationDt.{{ $value }}"/>
							</div>
						</div>
                        <div class="col-sm-3">
							<div class="form-floating mb-3">
								<x-select
									aria-label="Select Rating Method"
									:options="array('PERCENT'=>'PERCENT','MANUAL'=>'MANUAL')"
									wire:model.defer='s_RatingMethod.{{ $value }}' />
								<x-form-label for="s_RatingMethod.{{ $value }}" required value="{{ __('Select Rating Method') }}"/>
								<x-form-input-error name="s_RatingMethod.{{ $value }}"/>
							</div>
						</div>
                    </div>
					<div class="row">
                        <div class="col-sm-3">
                            <div class="form-floating mb-3">
                                <x-check-box label="{{ __('Display to User') }}" wire:model.defer="s_DISPLAYTOUSER.{{ $value }}"/>
                                <x-form-input-error name="s_DISPLAYTOUSER.{{ $value }}"/>
                            </div>
                        </div>
                        <div class="col-sm-3">
							<div class="form-floating mb-3">
								<x-form-input type="number" placeholder="Display Sequence " wire:model.defer='n_DisplaySequence.{{ $value }}'/>
								<x-form-label for="n_DisplaySequence.{{ $value }}" required value="{{ __('Display Sequence') }}"/>
								<x-form-input-error name="n_DisplaySequence.{{ $value }}"/>
                                <span style="color:red">Please enter numeric value only</span>
							</div>
						</div>
					</div>

					<div class="row">
						<div class="col-sm-12">
								@if($key!=0)
								<button class="btn text-white btn-danger btn-sm float-right mr-2" wire:click.prevent="remove({{$key}})">
									<i class="fa fa-plus"></i>
									Remove
								</button>
								@endif

							<button x-show="row==={{ $value }}" class="btn text-white btn-info btn-sm float-right" wire:click.prevent="addRow({{$i}})">
								<i class="fa fa-plus"></i>
								Add More
							</button>
						</div>
					</div>
				@endforeach
			</div>
			<!--end::Card body-->
			<div class="card-footer text-center">
				<button type="button" class="btn btn-primary" wire:target="submit" wire:click.prevent="submit" wire:loading.attr="disabled" wire:offline.attr="disabled" >
					<span>Submit</span>
					<span wire:loading wire:target="submit" class="indicator-progress">
						<span class="spinner-border spinner-border-sm align-middle ms-2"></span>
					</span>
				</button>
				<a href="{{ route('extentions') }}" wire:loading.attr="disabled" wire:offline.attr="disabled" class="btn btn-secondary" value="Cancel">Cancel</a>
			</div>
		</div>
		<!--end::Card-->
    </div>
    <!--end::Content container-->
</div>
