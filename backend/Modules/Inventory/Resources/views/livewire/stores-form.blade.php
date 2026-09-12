        <div>

            <form  method="POST" enctype="multipart/form-data" class="kt-form">
                @csrf
                <div class="row">
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label for="name" class="col-form-label">{{__('inventory::master.warehouse')}}</label>
                            <select class="form-control @error('stores.warehouses_id') is-invalid @enderror" wire:model.lazy="stores.warehouses_id">
                                <option>- Select -</option>
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
                <div class="row">
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label for="name" class="col-form-label">{{__('inventory::master.partner')}}</label>
                            <select class="form-control @error('stores.partner_id') is-invalid @enderror" name="partner_id" wire:model.lazy="stores.partner_id" data-live-search="true">
                                <option>- Select -</option>
                                @foreach($this->getPartner() as $p)
                                    <option value="{{$p->id}}"> {{$p->name ?? ""}}</option>
                                @endforeach
                            </select>
                            <div class="error invalid-feedback">
                                @error('stores.partner_id') {{ $message }} @enderror
                            </div>
                            <button style="margin-top: 3px;" type="button" class="btn btn-sm btn-primary" data-toggle="modal" data-target="#partnerModal"  >Didn't find partner in the list? Add one.
                            </button>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label for="name" class="col-form-label">{{__('inventory::master.store_name')}}</label>
                            <input type="text" class="form-control @error('stores.name') is-invalid @enderror" wire:model.defer="stores.name"
                                placeholder="Please provide store Name"
                                title="Please provide store Name" required maxlength="220"/>
                            <div class="error invalid-feedback">
                                @error('stores.name') {{ $message }} @enderror
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label for="name" class="col-form-label">{{__('inventory::master.state')}}</label>
                            <select class="form-control @error('stores.state_id') is-invalid @enderror"  name="state_id" wire:model="stores.state_id" id="state_id" >
                                    <option>- Select -</option>
                                    @foreach($this->getState() as $p)
                                        <option value="{{$p->id}}"> {{$p->name ?? ""}}</option>
                                    @endforeach
                            </select>
                            <div class="error invalid-feedback">
                                @error('stores.state_id') {{ $message }} @enderror
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label for="name" class="col-form-label">{{__('inventory::master.city')}}</label>
                            <select class="form-control @error('stores.city') is-invalid @enderror"  name="city" wire:model.lazy="stores.city" id="city" data-live-search="true">
                                <option>- Select -</option>
                                @foreach($this->getCity() as $p)
                                    <option value="{{$p->id}}"> {{$p->name ?? ""}}</option>
                                @endforeach
                            </select>
                            <div class="error invalid-feedback">
                                @error('stores.city') {{ $message }} @enderror
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label for="status" class="col-form-label">{{__('inventory::master.status')}}</label>
                            <select class="form-control @error('stores.status') is-invalid @enderror" wire:model.defer="stores.status">
                                <option>- Select -</option>
                                <option value="1">Active</option>
                                <option value="0">In-Active</option>
                            </select>
                            <div class="error invalid-feedback">
                                @error('stores.status') {{ $message }} @enderror
                            </div>
                        </div>
                    </div>
                </div>
                <hr>

                <div class="row">
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label for="contact_person" class="col-form-label">{{__('inventory::master.contact_person')}}</label>
                            <input type="text" class="form-control @error('stores.contact_person') is-invalid @enderror" wire:model.defer="stores.contact_person" required />
                            <div class="error invalid-feedback">
                                @error('stores.contact_person') {{ $message }} @enderror
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="mobile" class="col-form-label">{{__('inventory::master.mobile')}}</label>
                            <input type="text" class="form-control @error('stores.mobile') is-invalid @enderror" wire:model.defer="stores.mobile" required />
                            <div class="error invalid-feedback">
                                @error('stores.mobile') {{ $message }} @enderror
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label for="email_id" class="col-form-label">{{__('inventory::master.email')}}</label>
                            <input type="email" class="form-control @error('stores.email_id') is-invalid @enderror" wire:model.defer="stores.email_id" required maxlength="220"/>
                            <div class="error invalid-feedback">
                                @error('stores.email_id') {{ $message }} @enderror
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
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($this->getProduct() as $k=>$v)
                                        <tr x-data="{
                                            max:@entangle('inventorymax.'.$v->id).defer,
                                            min:@entangle('inventorymin.'.$v->id).defer,
                                        error:''}",
                                        x-init="$watch('max', (value) => {
                                            if ((value *10) < (min*10)) {
                                                error='Max Should be greater than minimum.';
                                                document.getElementById('submit').disabled = true;
                                            } else {
                                                error='';
                                                document.getElementById('submit').disabled = false;
                                            }
                                        })
                                        $watch('min', (value) => {
                                            if ((value * 10) > (max *10)) {
                                                error='Max Should be greater than minimum.';
                                                document.getElementById('submit').disabled = true;
                                            } else {
                                                error='';
                                                document.getElementById('submit').disabled = false;
                                            }
                                        })">
                                            <td>
                                                <span>{{$v->product->name ?? ""}}</span>
                                            </td>
                                            <td>
                                                <span>{{$v->name ?? ""}}</span>
                                            </td>
                                            <td>
                                                <div class="form-group">
                                                    <input onkeypress="return /[0-9]/i.test(event.key)" x-model="min" wire:model.defer="inventorymin.{{$v->id}}" :class="(error!='') ? 'is-invalid' : ''" type="number" class="form-control @error('inventorymin.{{$v->id}}') is-invalid @enderror"/>
                                                    <div x-html='error'  class="error invalid-feedback">
                                                        @error('inventorymin.{{$v->id}}') {{ $message }} @enderror
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="form-group">
                                                    <input onkeypress="return /[0-9]/i.test(event.key)" x-model="max" wire:model.defer="inventorymax.{{$v->id}}" :class="(error!='') ? 'is-invalid' : ''" type="number"  class="form-control @error('inventorymax.{{$v->id}}') is-invalid @enderror"/>
                                                    <div x-html='error' class="error invalid-feedback">
                                                        @error('inventorymax.{{$v->id}}') {{ $message }} @enderror
                                                    </div>
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
            </form>
            <div class="row">
                <div class="col-sm-12">
                    <div class="kt-portlet__foot kt-portlet__foot--solid">
                        <div class="kt-form__actions">
                            <div class="row">
                                <div class="col-12 text-center">
                                    <button class="btn btn-primary" id="submit" wire:click.prevent="save" wire:offline.attr="disabled" wire:loading.attr="disabled">
                                        {{-- <span class="" wire:loading.class="spinner-grow spinner-grow-sm"></span> --}}
                                    Submit
                                    </button>
                                    <button class="btn btn-danger" wire:offline.attr="disabled" wire:loading.attr="disabled" href="{{route('inventory.stores')}}">
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
            @include('inventory::partner')
        </div>
