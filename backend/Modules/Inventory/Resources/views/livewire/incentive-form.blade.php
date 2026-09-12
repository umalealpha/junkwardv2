<div  x-data="{product_id: @entangle('product_id'),plan_id: @entangle('plan_id'),payment_type: ''}"
x-init="$watch('product_id', ($value) => {
	$wire.call('setProduct', $value);
 });">
  <form  method="POST" enctype="multipart/form-data" class="kt-form">
		@csrf
	 <div class="row">
		<div class="col-sm-6">
			<div class="form-group">
				<label for="incentive_type" class="col-form-label">{{__('inventory::master.incentive_type')}}</label>
				<select class="form-control @error('incentive.incentive_type') is-invalid @enderror" wire:model.defer="incentive.incentive_type">
					<option>- Select Incentive Type -</option>
					@foreach(config('inventory.incentive_type') as $k=>$v)
						<option value="{{$k}}">{{$v}}</option>
					@endforeach
				</select>
				<div class="error invalid-feedback">
					@error('incentive.incentive_type') {{ $message }} @enderror
				</div>
			</div>
		</div>
	</div>
     <div class="row">
		<div class="col-sm-6">
			<div class="form-group">
				<label for="product_id" class="col-form-label">{{__('inventory::master.product')}}</label>
				<select class="form-control @error('incentive.product_id') is-invalid @enderror" wire:model.defer="incentive.product_id" x-model="product_id">
					<option value="">- Select -</option>
					@foreach($this->getProduct() as $s)
						<option value="{{$s->id}}"> {{$s->name ?? ""}}</option>
					@endforeach
				</select>
				<div class="error invalid-feedback">
					@error('incentive.product_id') {{ $message }} @enderror
				</div>
			</div>
			<div class="form-group">
				<label for="payment_type" class="col-form-label">{{__('inventory::master.payment_type')}}</label>
				<select class="form-control @error('incentive.payment_type') is-invalid @enderror" wire:model="incentive.payment_type" x-model="payment_type">
					<option value="">- Select -</option>
					<option value="per">Percentage</option>
					<option value="fixed">Fixed</option>
				</select>
				<div class="error invalid-feedback">
					@error('incentive.payment_type') {{ $message }} @enderror
				</div>
			</div>

		</div>
		<div class="col-sm-6">
			<div class="form-group">
				<label for="plan_id" class="col-form-label">{{__('inventory::master.plan')}}</label>
				<select class="form-control @error('incentive.plan_id') is-invalid @enderror"  x-model="plan_id" wire:model="incentive.plan_id">
					<option value="">- Select -</option>
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
								<span class="" wire:loading.class="spinner-grow spinner-grow-sm"></span>
							  Submit
							</button>
							<button class="btn btn-danger" wire:offline.attr="disabled" wire:loading.attr="disabled" href="{{route('inventory.incentive')}}">
								Cancel
							</button>
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
