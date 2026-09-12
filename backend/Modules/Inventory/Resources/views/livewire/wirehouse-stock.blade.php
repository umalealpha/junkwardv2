<div x-data="{ showDetails :  @entangle('showDetails'),warehouses_id: @entangle('warehouse_id') }"
x-init="$watch('warehouses_id', ($value) => {
	$wire.call('setWireHouse', $value);
	if($value!=''){
		showDetails=true;
	}else{
		showDetails=false;
	}
 }),
 $refs.mdiv.classList.remove('hide')
 "
>
    <div class="row">
		<div class="col-sm-6">
			<div class="form-group" wire:ignore>
				<label for="name" class="col-form-label">{{__('inventory::master.warehouse')}}</label>
				<select class="form-control selectpicker @error('stores.warehouses_id') is-invalid @enderror" x-model="warehouses_id" data-live-search="true">
					<option value="">- Select -</option>
					@foreach($this->getWarehouse() as $w)
						<option value="{{$w->id}}"> {{$w->name ?? ""}}</option>
					@endforeach
				</select>
				<div class="error invalid-feedback">
					@error('stores.warehouses_id') {{ $message }} @enderror
				</div>
			</div>
		</div>
	</div>

	<div class="row hide" x-ref="mdiv" x-show="showDetails">
		<div class="col-sm-12">
			<div class="table-responsive">
				<table class="table table-striped">
					<thead class="thead-dark">
						<tr>
							<th>{{__('inventory::master.product')}}</th>
							<th>{{__('inventory::master.plan')}}</th>
							<th>{{__('inventory::master.available_stock')}}</th>
							<th>No Of Stock</th>
							<th>Add / Reduce Stock</th>
						</tr>
					</thead>
					<tbody>
						@forelse($this->getWarehouseInventory() as $k=>$v)
							<tr x-data="{max: {{$v->max_inventory}},min: {{$v->min_inventory}},addinput:'',error:'',
									counter:{{intval($v->counter)}},id:{{$v->id}}}">
								<td>{{$v->product->name ?? ''}}</td>
								<td>{{$v->plan->name ?? ''}}</td>
								<td x-text='counter'></td>
								<td>
									<div class="form-group">
											<input type="text" class="form-control" x-model='addinput' :class="(error!='') ? 'is-invalid' : ''"/>
											<div x-html='error' class="error invalid-feedback">
											</div>
										</div>
								</td>
								<td>
									<div class="row">
										<div class="col-sm-6">
											<a @click="addStock" class="btn btn-sm btn-elevate 	btn-brand btn-elevate">
												<span class="kt-opacity-11" style="color:white">Add &nbsp;
												<i class="flaticon2-add-1 kt-padding-l-5 kt-padding-r-0"></i> </span>
											</a>
										</div>
									</div>
								</td>
							</tr>
						@empty
							<tr>
								<td colspan="5">No Inventory are added to the selected Warehouse</td>
							</tr>
						@endforelse
					</tbody>
				</table>
			</div>
		</div>
	</div>
</div>


@push('js')
<script>
	function addStock(e) {
		this.error="";
		if(this.addinput =="" | this.addinput == 0){
			this.error="Add Number Of Quantity you want to add.";
			return false;
		}
		if(parseInt(this.max) < (parseInt(this.counter) + parseInt(this.addinput))){
		   this.error="Quantity Should Not be Greater Than <strong class='badge badge-pill  badge-info'>"+parseInt(this.max)+"</strong>";
		   return false;
		}else{
			@this.call('addQuantity',this.id,this.addinput);
			this.counter=this.counter+parseInt(this.addinput);
			this.addinput='';
		}
	}
</script>
@endpush
