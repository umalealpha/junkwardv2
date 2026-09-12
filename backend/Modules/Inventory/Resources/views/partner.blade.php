
<a data-toggle="modal" style='display:none' data-target="#partnerModal" class="btn btn-sm btn-clean open-model btn-icon btn-icon-md" >
	<i class="la la-edit"></i>
</a>
<div x-data="{ open: false }">
<div x-show="open" class="modal fade" id="partnerModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
		<div class="modal-dialog" role="document">
			<div class="modal-content">
			  <div class="modal-header">
				<h5 class="modal-title" id="exampleModalLabel">Add Partner</h5>
				
				<button type="button" class="close model-close" data-dismiss="modal" aria-label="Close">
				  <span aria-hidden="true">&times;</span>
				</button>
			  </div>
			  <div class="modal-body">
					<div class="form-group">
						<label for="name" class="col-form-label">{{__('inventory::master.partner_name')}}</label>
						<input type="text" class="form-control @error('partner.name') is-invalid @enderror" wire:model.defer="partner.name"
							placeholder="Please provide Partner Name"
							title="Please provide Partner Name" required maxlength="220"/>
						<div class="error invalid-feedback">
							@error('partner.name') {{ $message }} @enderror
						</div>
					</div>
					<div class="form-group">
						<label for="status" class="col-form-label">{{__('inventory::master.status')}}</label>
						<select class="form-control @error('partner.status') is-invalid @enderror" wire:model.defer="partner.status">
							<option>- Select -</option>
							<option value="1">Active</option>
							<option value="0">In-Active</option>
						</select>
						<div class="error invalid-feedback">
							@error('partner.status') {{ $message }} @enderror
						</div>
					</div>
			  </div>
			  <div class="modal-footer">
				<button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
				<button class="btn btn-primary" wire:click.prevent="savePartner"   wire:offline.attr="disabled" wire:loading.attr="disabled">
					<span class="" wire:loading.class="spinner-grow spinner-grow-sm"></span>
				  Submit
				</button>
				<div class="col-12 text-center" wire:loading wire:target="save">
					Please Wait . . . . .
				</div>
			  </div>
			</div>
		</div>
	</div>
</div>
@push('js')
	<script>
		window.addEventListener('partnerAdded', event => { 
			$('.model-close').click();
		});
		window.addEventListener('openPartnerMode', event => { 
			$('.open-model').click();
		});
	</script>
@endpush