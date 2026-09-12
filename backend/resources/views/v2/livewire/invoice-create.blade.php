<div>
<div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
                <div class="kt-portlet kt-portlet--mobile">
                    <div class="kt-portlet__body">
                        <form wire:submit.prevent="submitForm" autocomplete="off">
                         @csrf
                            <div class="row">
                                    <div class="col-sm-6">
                                    <label for="name">Policy Number:</label>
                                    <input type="text" id="policyNumber" wire:model="policyNumber" class="border p-2 rounded w-full">
                                    @error('policyNumber') <span class="text-red-500">{{ $message }}</span> @enderror
                                    </div>

                                    <div class="col-sm-6">
                                        <div class="col-3"></div>
                                        <div class="col-9">
                                        <button type="submit" value="Submit" id="submit_btn"
                                        class="btn btn-primary">Create Invoice</button>                                                </div>
                                    </div>        

                            </div>
                        </form>

                    </div>
                </div>
            </div>
</div>
<script>
  
    window.addEventListener('page-refresh', event => {
        location.reload(); // Refresh the page
    });
</script>