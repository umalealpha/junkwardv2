<div wire:loading.class="page-loading">
    <!--begin::Page loading(append to body)-->
    <div class="page-loader flex-column bg-dark bg-opacity-25 ">
        <span class="spinner-border text-primary" role="status"></span>
        <span class="text-gray-800 fs-6 fw-semibold mt-5">Loading...</span>
    </div>
    <div class="form-floating m-3">
        <input type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#transaction-modal" value="New Transaction">
    </div>

    <div wire:ignore.self>
        <div class="modal fade @if($showModal) show @endif" tabindex="-1" id="transaction-modal" @if($showModal) style="display: block;" @endif wire:ignore.self>
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3 class="modal-title">New Transaction</h3>

                        <!--begin::Close-->
                        <div class="btn btn-icon btn-sm btn-active-light-primary ms-2" data-bs-dismiss="modal" aria-label="Close">
                            <span class="svg-icon svg-icon-1"></span>
                        </div>
                        <!--end::Close-->
                    </div>

                    <div class="modal-body">
                        <div class="row">
                            <div class="col-sm-12">

                                    <div class=" form-floating mb-3">
                                        <x-select-search id="policyAction.transaction_type" wire:model="policyAction.transaction_type"
                                                            :options="$this->getTransactionTypes()" />
                                        <x-form-label for="policyAction.transaction_type" value="Select Transaction Type" />
                                        <x-form-input-error name="policyAction.transaction_type"/>
                                    </div>

                            </div>
                            <div class="col-sm-12">
                                <div class="form-floating mb-3">
                                    <x-select-search id="transaction_reason"   wire:model.defer="policyAction.transaction_reason"  aria-label="Select Transaction Reason"
                                    :options="$this->subTransactionTypes ?? []"   listner="transaction_reason" />
                                    <x-form-label for="policyAction.policyAction" required value="{{ __('Transaction Reason') }}" />
                                    <x-form-input-error name="policyAction.transaction_reason"/>
                                </div>
                            </div>
                            <div class="col-sm-12">
                                <div class="form-floating mb-3">
                                    <x-form-date type="text" class="kt_datepicker_1" id="policyAction_effective_from" wire:model="effective_from"  placeholder="Effective From" />
                                    {{-- <x-form-input type="date" id="policyAction.effective_from" wire:model.defer="policyAction.effective_from"  placeholder="Effective From" /> --}}
                                    <x-form-label for="policyAction_effective_from" required value="{{ __('Effective From') }}"/>
                                    <x-form-input-error name="effective_from"/>
                                </div>  
                            </div>
                            <div class="col-sm-12">
                                <div class="form-floating mb-3">
                                    <x-form-date type="text" class="kt_datepicker_1" id="policyAction_effective_to" wire:model="effective_to"  placeholder="Effective To"/>
                                    {{-- <x-form-input type="date" id="policyAction.effective_to" wire:model.defer="policyAction.effective_to"  placeholder="Effective To"/> --}}
                                    <x-form-label for="policyAction_effective_to" required value="{{ __('Effective To') }}"/>
                                    <x-form-input-error name="effective_to"/>
                                </div>
                            </div>
                            <div class="col-sm-12">
                                <div class="form-floating mb-3">
                                    <x-form-date type="text" class="kt_datepicker_1" id="policyAction_transaction_date" wire:model="transaction_date"  placeholder="Transaction Date"/>
                                    {{-- <x-form-input type="date" id="policyAction.transaction_date" wire:model.defer="policyAction.transaction_date"  placeholder="Transaction Date"/> --}}
                                    <x-form-label for="policyAction_transaction_date" required value="{{ __('Transaction Date') }}"/>
                                    <x-form-input-error name="policyAction_transaction_date"/>
                                </div>
                            </div>
                            <div class="col-sm-12">
                                <div class="form-floating mb-3">
                                    <x-form-text-area id="policyAction.note" wire:model.defer="policyAction.note" placeholder="Transaction Notes" style="height:150px"/>
                                    <x-form-label for="policyAction.note" required value="{{ __('Transaction Notes') }}"/>
                                    <x-form-input-error name="policyAction.note"/>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                        <button type="button" class="btn btn-primary" wire:click.prevent="submit" x-on:click="$('#transaction-modal').modal('show');">Submit</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        window.addEventListener('closeTransactionModal', event => {
            $('#transaction-modal').modal('hide');
        })
    </script>
   <script>
    // added by snehal
    // document.addEventListener('livewire:load', function () {
      
    //     // Watch for changes in the effective_from field
    //     $(document).on('change', '#policyAction_effective_from', function () {
    //       var transaction_type=$('#policyAction\\.transaction_type').val();
    //        var premium_freq = @json($this->policy->premium_freq);
    //       var disableEffectiveTo = @json($disableEffectiveTo);
         
    //       if (transaction_type === "RENEW" && premium_freq=="3")
    //       {
    //         // Disable the effective_to field
    //         $('#policyAction_effective_to')
    //             .prop('disabled', true)
    //             .datepicker('remove'); // destroy the datepicker
    //       }
           
    //     });
    // });

//     $('#policyAction\\.transaction_type').on('change', function () {
//          var transaction_type=$(this).val();
//            var premium_freq = @json($this->policy->premium_freq);
//            if (transaction_type === "RENEW" && premium_freq=="3")
//           {
         
//         //    const selected = 0;
//             Livewire.emit('transactionTypeChanged', transaction_type);
//                $('#policyAction_effective_to')
//                 .prop('disabled', true) .datepicker('remove'); // destroy the datepicker
//          }
  
         
// });
  window.addEventListener('update-datepicker', event => {
        const { id, date } = event.detail;
        $('#' + id).datepicker('setDate', date);
    });
</script>

</div>
