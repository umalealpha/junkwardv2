<div x-data="{incentive_type: '',payment_type: ''}">
  <form  method="POST" enctype="multipart/form-data" class="kt-form">
		@csrf
	 <div class="row">
		<div class="col-sm-6">
			<div class="form-group">
				<label for="incentive_type" class="col-form-label">{{__('incentive::master.incentive_type')}}</label>
				<select class="form-control @error('incentive.incentive_type') is-invalid @enderror" wire:model="incentive.incentive_type">
					<option value="">- Select Incentive Type -</option>
					@foreach(config('incentive.incentive_type') as $k=>$v)
						<option value="{{$k}}">{{$v}}</option>
					@endforeach
				</select>
				<div class="error invalid-feedback">
					@error('incentive.incentive_type') {{ $message }} @enderror
				</div>
			</div>
		</div>
	</div>
	@if($this->incentive->incentive_type==8)
    <div class="row">
        <div class="col-sm-6">
			@if($this->incentive->unlimited==0)
			<div class="form-group">
				<label for="subsquent_amount" class="col-form-label" >For how many months you want to give commisions?</label>
				<input type="text" class="form-control @error('incentive.subsquent_amount') is-invalid @enderror"  id="amountIncentive" wire:model="incentive.subsquent_amount" required />
				<div class="error invalid-feedback">
					@error('incentive.subsquent_amount') {{ $message }} @enderror
				</div>
			</div>
			@endif
			<div class="form-group">
				<input type="checkbox" value="1" id="checkbox" wire:model="incentive.unlimited">
				<label for="checkbox" class="col-form-label">Unlimited</label>
			</div>
        </div>
    </div>
	@endif
     <div class="row">
		<div class="col-sm-6">
			<div class="form-group">
				<label for="product_id" class="col-form-label">{{__('incentive::master.product')}}</label>
				<select class="form-control @error('incentive.product_id') is-invalid @enderror" wire:model="incentive.product_id" >
					<option value="">- Select Product-</option>
					@foreach($this->getProduct() as $s)
						<option value="{{$s->id}}"> {{$s->name ?? ""}}</option>
					@endforeach
				</select>
				<div class="error invalid-feedback">
					@error('incentive.product_id') {{ $message }} @enderror
				</div>
			</div>

			<div class="form-group">
				<label for="payment_type" class="col-form-label">{{__('incentive::master.payment_type')}}</label>
				<select class="form-control @error('incentive.payment_type') is-invalid @enderror" wire:model="incentive.payment_type" >
					<option value="">- Select Payment Type-</option>
					<option value="per">Percentage</option>
					<option value="fixed">Fixed</option>
				</select>
				<div class="error invalid-feedback">
					@error('incentive.payment_type') {{ $message }} @enderror
				</div>
			</div>

            <div class="form-group">
				<label for="status" class="col-form-label">{{__('incentive::master.status')}}</label>
				<select class="form-control @error('incentive.status') is-invalid @enderror" wire:model="incentive.status" >
					<option value="">- Select Status-</option>
					<option value="1">Active</option>
					<option value="0">In-Active</option>
				</select>
				<div class="error invalid-feedback">
					@error('incentive.status') {{ $message }} @enderror
				</div>
			</div>
		</div>
		<div class="col-sm-6">
			<div class="form-group">
				<label for="plan_id" class="col-form-label">{{__('incentive::master.plan')}}</label>
				<select class="form-control @error('incentive.plan_id') is-invalid @enderror"  wire:model.lazy="incentive.plan_id">
					<option value="">- Select Product Plan-</option>
					@foreach($this->getProductPlan() as $p)
						<option value="{{$p->id}}"> {{$p->name ?? ""}}</option>
					@endforeach
				</select>
				<div class="error invalid-feedback">
					@error('incentive.plan_id') {{ $message }} @enderror
				</div>
			</div>
			<div class="form-group">
				<label for="incentive_value" class="col-form-label" x-text="payment_type== 'per' ? 'Percentage Value' : 'Fixed Value' ">Value</label>
				<input type="text" class="form-control @error('incentive.incentive_value') is-invalid @enderror" wire:model.defer="incentive.incentive_value" required />
				<div class="error invalid-feedback">
					@error('incentive.incentive_value') {{ $message }} @enderror
				</div>
			</div>
		</div>
	</div>
</form>
	<div class="row">
		<div class="col-sm-12">
			<div class="kt-portlet__foot kt-portlet__foot--solid">
				<div class="kt-form__actions">
					<div class="row">
						<div class="col-12 text-center">
							<button class="btn btn-primary" wire:click.prevent="saveIncentive"   wire:offline.attr="disabled" wire:loading.attr="disabled">
								{{-- <span class="" wire:loading.class="spinner-grow spinner-grow-sm"></span> --}}
							  Submit
							</button>
							<a class="btn btn-danger" href="{{route('incentive.settings')}}">
								Cancel
							</a>
						</div>
						<div class="col-12 text-center" wire:loading wire:target="save">
							Please Wait . . . . .
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
