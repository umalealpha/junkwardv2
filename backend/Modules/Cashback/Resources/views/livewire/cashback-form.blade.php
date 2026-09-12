<div  x-data="{product_id: @entangle('product_id'),plan_id: @entangle('plan_id'),payment_type: ''}"
x-init="$watch('product_id', ($value) => {
	$wire.call('setProduct', $value);
 });">
  <form  method="POST" enctype="multipart/form-data" class="kt-form">
		@csrf
	 <div class="row">
		<div class="col-sm-6">
			<div class="form-group">
				<label for="cashback_type" class="col-form-label">{{__('cashback::master.cashback_type')}}</label>
				<select class="form-control @error('cashback.cashback_type') is-invalid @enderror" wire:model.defer="cashback.cashback_type">
					<option>- Select Cashback Type -</option>
					@foreach(config('cashback.cashback_type') as $k=>$v)
						<option value="{{$k}}">{{$v}}</option>
					@endforeach
				</select>
				<div class="error invalid-feedback">
					@error('cashback.cashback_type') {{ $message }} @enderror
				</div>
			</div>
		</div>
	</div>

     <div class="row">
		<div class="col-sm-6">
			<div class="form-group">
				<label for="product_id" class="col-form-label">{{__('cashback::master.product')}}</label>
				<select class="form-control @error('cashback.product_id') is-invalid @enderror" wire:model="cashback.product_id" >
					<option value="">- Select Product-</option>
					@foreach($this->getProduct() as $s)
						<option value="{{$s->id}}"> {{$s->name ?? ""}}</option>
					@endforeach
				</select>
				<div class="error invalid-feedback">
					@error('cashback.product_id') {{ $message }} @enderror
				</div>
			</div>
			<div class="form-group">
				<label for="payment_type" class="col-form-label">{{__('cashback::master.payment_type')}}</label>
				<select class="form-control @error('cashback.payment_type') is-invalid @enderror" wire:model="cashback.payment_type" >
					<option value="">- Select Payment Type-</option>
					<option value="per">Percentage</option>
					<option value="fixed">Fixed</option>
				</select>
				<div class="error invalid-feedback">
					@error('cashback.payment_type') {{ $message }} @enderror
				</div>
			</div>
		</div>
		<div class="col-sm-6">
			<div class="form-group">
				<label for="plan_id" class="col-form-label">{{__('cashback::master.plan')}}</label>
				<select class="form-control @error('cashback.plan_id') is-invalid @enderror"  wire:model.lazy="cashback.plan_id">
					<option value="">- Select Product Plan-</option>
					@foreach($this->getProductPlan() as $p)
						<option value="{{$p->id}}"> {{$p->name ?? ""}}</option>
					@endforeach
				</select>
				<div class="error invalid-feedback">
					@error('cashback.plan_id') {{ $message }} @enderror
				</div>
			</div>
			<div class="form-group">
				<label for="cashback_value" class="col-form-label" x-text="payment_type== 'per' ? 'Percentage Value' : 'Fixed Value' ">Value</label>
				<input type="text" class="form-control @error('cashback.cashback_value') is-invalid @enderror" wire:model.defer="cashback.cashback_value" required />
				<div class="error invalid-feedback">
					@error('cashback.cashback_value') {{ $message }} @enderror
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
							<button class="btn btn-primary" wire:click.prevent="saveCashback"   wire:offline.attr="disabled" wire:loading.attr="disabled">
								<span class="" wire:loading.class="spinner-grow spinner-grow-sm"></span>
							  Submit
							</button>
							<button class="btn btn-danger" wire:offline.attr="disabled" wire:loading.attr="disabled" href="{{route('cashback.settings')}}">
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
