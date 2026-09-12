<div>
     <form  method="POST" enctype="multipart/form-data" class="kt-form">
		@csrf
		<div class="row">
			<div class="col-sm-6">
				<div class="form-group">
					<label for="name" class="col-form-label">{{__('inventory::master.product')}}</label>
					<select class="form-control @error('Storequantity.store_id') is-invalid @enderror" wire:model="Storequantity.store_id">
						<option>- Select Store -</option>
						@foreach($this->getStore() as $s)
							<option value="{{$s->id}}"> {{$s->name ?? ""}}</option>
						@endforeach
					</select>
					<div class="error invalid-feedback">
						@error('Storequantity.store_id') {{ $message }} @enderror
					</div>
				</div>
			</div>
			<div class="col-sm-6">
				<div class="form-group">
					<label for="name" class="col-form-label">{{__('inventory::master.product')}}</label>
					<select class="form-control @error('Storequantity.product_id') is-invalid @enderror" wire:model="Storequantity.product_id">
						<option>- Select Product-</option>
						@foreach($this->getProduct() as $w)
							<option value="{{$w->id}}"> {{$w->name ?? ""}}</option>
						@endforeach
					</select>
					<div class="error invalid-feedback">
						@error('Storequantity.product_id') {{ $message }} @enderror
					</div>
				</div>
			</div>
		</div>
	</form>
</div>
