<div x-data="{ store_id: @entangle('store_id'),warehouses_id: @entangle('warehouses_id'),reason: @entangle('reason') }"
x-init="$watch('store_id', ($value) => {
	$wire.call('setStores', $value);
 });
 $watch('warehouses_id', ($value) => {
	$wire.call('setWareHouse', $value);
 });

">
 <form  method="POST" class="kt-form">
 @csrf
    <div class="row">
		<div class="col-sm-6">
			<div class="form-group" wire:ignore>
				<label for="name" class="col-form-label">{{__('inventory::master.warehouse')}}</label>
				<select class="form-control selectpicker @error('warehouses_id') is-invalid @enderror"  wire:model="warehouses_id" data-live-search="true">
					<option value="">- Select -</option>
					@foreach($this->getWarehouse() as $w)
						<option value="{{$w->id}}"> {{$w->name ?? ""}}</option>
					@endforeach
				</select>
				<div class="error invalid-feedback">
					@error('warehouses_id') {{ $message }} @enderror
				</div>
			</div>
		</div>
		<div class="col-sm-6">
			<div class="form-group" >
				<label for="name" class="col-form-label">{{__('inventory::master.store_name')}}</label>
				<select class="form-control @error('store_id') is-invalid @enderror" wire:model="store_id">
					<option value="">- Select -</option>
					@foreach($this->getStore() as $s)
						<option value="{{$s->id}}"> {{$s->name ?? ""}}</option>
					@endforeach
				</select>
				<div class="error invalid-feedback">
					@error('store_id') {{ $message }} @enderror
				</div>
			</div>
		</div>
		<div class="col-sm-6">
			<div class="form-group">
				<label for="availabe_stock" class="col-form-label">Available Stock</label>
				<input type="text" class="form-control @error('availabe_stock') is-invalid @enderror"  wire:model="availabe_stock" readonly />
				<div class="error invalid-feedback">
					@error('availabe_stock') {{ $message }} @enderror
				</div>
			</div>
		</div>
		<div class="col-sm-6">
			<div class="form-group">
				<label for="name" class="col-form-label">{{__('inventory::master.reason')}}</label>
				<select class="form-control @error('reason') is-invalid @enderror" wire:model="reason">
					<option value="">- Select -</option>
					<option value="damaged"> Damaged</option>
					<option value="transfer"> Transfer to Other Warehouse / Stores </option>
				</select>
				<div class="error invalid-feedback">
					@error('reason') {{ $message }} @enderror
				</div>
			</div>
		</div>
		
			<div class="col-sm-6">
				<div class="form-group">
					<label style="font-variant: petite-caps;" for="damaged_stock" class="col-form-label"  x-text="(reason!='')?reason + ' Stock':' Stock'"> ??  Stock</label>
					<input type="text" class="form-control @error('damaged_stock') is-invalid @enderror" wire:model.defer="damaged_stock" />
					<div class="error invalid-feedback">
						@error('damaged_stock') {{ $message }} @enderror
					</div>
				</div>
			</div>
			<div class="col-sm-6">
				<div class="form-group" x-show="reason=='transfer'">
					<label for="name" class="col-form-label">{{__('inventory::master.transfer_warehouse')}}</label>
					<select class="form-control @error('transfer_warehouses_id') is-invalid @enderror" wire:model="transfer_warehouses_id">
						<option value="">- Select -</option>
						@foreach($this->getOtherWarehouse() as $w)
							<option value="{{$w->id}}"> {{$w->name ?? ""}}</option>
						@endforeach
					</select>
					<div class="error invalid-feedback">
						@error('transfer_warehouses_id') {{ $message }} @enderror
					</div>
				</div>
			</div>
			<div class="col-sm-6">
				<div class="form-group" x-show="reason=='transfer'">
					<label for="name" class="col-form-label">{{__('inventory::master.transfer_store')}}</label>
					<select  x-on:change="$wire.stock()" class="form-control @error('transfer_store_id') is-invalid @enderror" wire:model="transfer_store_id">
						<option value="">- Select -</option>
						@foreach($this->getOtherStore() as $s)
							<option value="{{$s->id}}"> {{$s->name ?? ""}}</option>
						@endforeach
					</select>
					<div class="error invalid-feedback">
						@error('transfer_store_id') {{ $message }} @enderror
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
							<button class="btn btn-primary" id="sb" wire:click.prevent="save"   wire:offline.attr="disabled" wire:loading.attr="disabled">
								{{-- <span class="" wire:loading.class="spinner-grow spinner-grow-sm"></span> --}}
							  Submit
							</button>
							<a class="btn btn-danger" wire:offline.attr="disabled" wire:loading.attr="disabled" href="{{route('inventory.stores.stock-reduce')}}">
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
