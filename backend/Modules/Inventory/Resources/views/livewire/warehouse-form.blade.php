<div>
    <form  method="POST" enctype="multipart/form-data" class="kt-form">
		@csrf
		<div class="row">
			<div class="col-sm-6">
				<div class="form-group">
					<label for="name" class="col-form-label">{{__('inventory::master.name')}}</label>
					<input type="text" class="form-control @error('warehouse.name') is-invalid @enderror" wire:model.defer="warehouse.name"
						placeholder="Please provide warehouse Name"
						title="Please provide warehouse Name" required maxlength="220"/>
					<div class="error invalid-feedback">
						@error('warehouse.name') {{ $message }} @enderror
					</div>
				</div>
				<div class="form-group">
					<label for="email_id" class="col-form-label">{{__('inventory::master.email')}}</label>
					<input type="email" class="form-control @error('warehouse.email_id') is-invalid @enderror" wire:model.defer="warehouse.email_id" required maxlength="220"/>
					<div class="error invalid-feedback">
						@error('warehouse.email_id') {{ $message }} @enderror
					</div>
				</div>
				<div class="form-group">
					<label for="address" class="col-form-label">{{__('inventory::master.address')}}</label>
					<textarea type="text" class="form-control @error('warehouse.address') is-invalid @enderror" wire:model.defer="warehouse.address" required></textarea>
					<div class="error invalid-feedback">
						@error('warehouse.address') {{ $message }} @enderror
					</div>
				</div>
			</div>
			<div class="col-sm-6">
				<div class="form-group">
					<label for="contact_person" class="col-form-label">{{__('inventory::master.contact_person')}}</label>
					<input type="text" class="form-control @error('warehouse.contact_person') is-invalid @enderror" wire:model.defer="warehouse.contact_person" required />
					<div class="error invalid-feedback">
						@error('warehouse.contact_person') {{ $message }} @enderror
					</div>
				</div>
				<div class="form-group">
					<label for="mobile" class="col-form-label">{{__('inventory::master.mobile')}}</label>
					<input type="text" class="form-control @error('warehouse.mobile') is-invalid @enderror" wire:model.defer="warehouse.mobile" required />
					<div class="error invalid-feedback">
						@error('warehouse.mobile') {{ $message }} @enderror
					</div>
				</div>
				<div class="form-group">
					<label for="status" class="col-form-label">{{__('inventory::master.status')}}</label>
					<select class="form-control @error('warehouse.status') is-invalid @enderror" wire:model.defer="warehouse.status">
						<option>- Select -</option>
						<option value="1">Active</option>
						<option value="0">In-Active</option>
					</select>
					<div class="error invalid-feedback">
						@error('warehouse.status') {{ $message }} @enderror
					</div>
				</div>
			</div>
		</div>
		<hr>

		<div class="row">
			<div class="col-sm-12">
				<div class="table-responsive">
					<table class="table table-striped">
						<thead class="thead-dark">
							<tr>
								<th>{{__('inventory::master.product')}}</th>
								<th>{{__('inventory::master.plan')}}</th>
								<th>{{__('inventory::master.minimum_warehouse_inventory')}}</th>
								<th>{{__('inventory::master.maximum_warehouse_inventory')}}</th>
								<th>{{__('inventory::master.available_stock')}}</th>
							</tr>
						</thead>
						<tbody>
							@forelse($this->getProduct() as $k=>$v)
								<tr x-data="{
									max:@entangle('maxinventory.'.$v->id).defer,
								min:@entangle('mininventory.'.$v->id).defer,
								error:'',available:{{$this->counter[$v->id] ?? 0}}}",
								x-init="$watch('max', (value) => {
									if ((value *10) < (min*10)) {
										error='Max Should be greater than minimum.';
										document.getElementById('sb').disabled = true;
									} else {
										error='';
										document.getElementById('sb').disabled = false;
									}
								})
								$watch('min', (value) => {
									if ((value * 10) > (max *10)) {
										error='Max Should be greater than minimum.';
										document.getElementById('sb').disabled = true;
									} else {
										error='';
										document.getElementById('sb').disabled = false;
									}
								})"
								>
									<td>
										<span>{{$v->product->name ?? ""}}  </span>
									</td>
									<td>
										<span>{{$v->name ?? ""}}</span>
									</td>
									<td>
										<div class="form-group">
											<input onkeypress="return /[0-9]/i.test(event.key)" x-model="min" wire:model.defer="mininventory.{{$v->id}}" :class="(error!='') ? 'is-invalid' : ''" type="number" class="form-control @error('mininventory.{{$v->id}}') is-invalid @enderror"/>
											<div x-html='error'  class="error invalid-feedback">
												@error('mininventory.{{$v->id}}') {{ $message }} @enderror
											</div>
										</div>
									</td>
									<td>
										<div class="form-group">
											<input onkeypress="return /[0-9]/i.test(event.key)" x-model="max" wire:model.defer="maxinventory.{{$v->id}}" :class="(error!='') ? 'is-invalid' : ''" type="number"  class="form-control @error('maxinventory.{{$v->id}}') is-invalid @enderror"/>
											<div x-html='error' class="error invalid-feedback">
												@error('maxinventory.{{$v->id}}') {{ $message }} @enderror
											</div>
										</div>
									</td>
									<td>
										<div class="form-group" >
											Available
											<span class="badge badge-pill " :class="(available < min) ? 'badge-warning' : 'badge-success'"
											x-text='available'></span>
										</div>
									</td>
								</tr>
							@empty
								<tr>
									<td colspan=5>
										No Product / Plan Found
									</td>
								<tr>
							@endforelse
						</tbody>
					</table>
				</div>
			</div>
		</div>
	<hr>
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
							<a class="btn btn-danger" wire:offline.attr="disabled" wire:loading.attr="disabled" href="{{route('inventory.warehouse')}}">
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
