<div x-data="{ showDetails :  @entangle('showDetails'),store_id: @entangle('store_id') }"
x-init="$watch('store_id', ($value) => {
	$wire.call('setStores', $value);
	if($value!=''){
		showDetails=true;
	}else{
		showDetails=false;
	}
 }),
 $refs.mdiv.classList.remove('hide')"
>
    <div class="row">
		<div class="col-sm-6">
			<div class="form-group" wire:ignore>
				<label for="name" class="col-form-label">{{__('inventory::master.store_name')}}</label>
				<select class="form-control selectpicker @error('store_id') is-invalid @enderror" x-model="store_id" data-live-search="true">
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
	</div>
	<div class="row hide" x-ref="mdiv"  x-show="showDetails">
		<div class="col-sm-12">
			<div class="table-responsive">
				<table class="table table-striped">
					<thead class="thead-dark">
						<tr>
							<th>{{__('inventory::master.product')}}</th>
							<th>{{__('inventory::master.plan')}}</th>
							<th>
								{{__('inventory::master.available_stock')}}
							</th>
							<th>Add More</th>
						</tr>
					</thead>
					<tbody>
						@forelse($this->getStoreInventory() as $k=>$v)
							@php
								$wirehose = $v->store->wireHouse->inventory()
								->where('plan_id',$v->plan->id)
								->where('product_id',$v->product->id)->first()
							@endphp
							<tr x-data="{
								max: {{$v->max_inventory}},
								min: {{$v->min_inventory}},
								addinput:'',error:'',
								counter:{{intval($v->counter)}},
								wire_counter:{{intval($wirehose->counter ?? 0)}},
								wire_inventory:{{$wirehose->id ?? 0}},
								id:{{$v->id}}}">
								<td>{{$v->product->name ?? ''}} </td>
								<td>{{$v->plan->name ?? ''}}</td>
								<td x-text='counter'></td>
								<td>
									<div class="row">
										<div class="col-sm-8">
										<div class="form-group">
											<input type="text" class="form-control" x-model='addinput' :class="(error!='') ? 'is-invalid' : ''"/>
											<div x-html='error' class="error invalid-feedback">
											</div>
											<div class="success">
												Available Stock to Assign
												<strong x-html="wire_counter" class='badge badge-pill badge-info'></strong>
											</div>
										</div>
										</div>
										<div class="col-sm-4">
											<button x-bind:disabled="(wire_counter>0)?false:true" @click="addStock" class="btn btn-sm btn-elevate btn-brand btn-elevate">
												<span class="kt-opacity-11" style="color:white">Add &nbsp;
												<i class="flaticon2-add-1 kt-padding-l-5 kt-padding-r-0"></i> </span>
											</button>
										</div>
									</div>
								</td>
							</tr>
						@empty
							<tr>
								<td colspan="4">No Inventory are added to the selected Stores</td>
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
		}else if(parseInt(this.wire_counter) < parseInt(this.addinput)){
			this.error="<strong class='badge badge-pill badge-danger'>"+this.addinput + "</strong> Quntity Not Available In Wire House ";
		   return false;
		}else{
			@this.call('addQuantity',this.id,this.addinput,this.wire_inventory);
			this.counter=this.counter+parseInt(this.addinput);
			this.wire_counter=this.wire_counter-parseInt(this.addinput);
			this.addinput='';
		}
	}
</script>
@endpush
