<div wire:ignore.self class="modal fade" id="transactionLogDeleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">Reverse Transaction</h4>
                <button type="button" class="close" data-bs-dismiss="modal">×</button>
            </div>
            <div class="modal-body" style="min-height: 200px;">
                {{-- Loading Spinner --}}
                <div wire:loading wire:target="setModalData,setModalDataFromButton" class="text-center py-5">
                    <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
                        <span class="sr-only">Loading...</span>
                    </div>
                    <p class="mt-3 text-muted">Loading reversal form...</p>
                </div>

                {{-- Modal Content --}}
                <div wire:loading.remove wire:target="setModalData,setModalDataFromButton">
                    @if($transactionLogId)
                        <h4 class="mb-3">
                            Are you sure you want to reverse the transaction
                            <strong>{{ $mode === 'before' ? 'before' : 'after' }} the ledger?</strong>
                        </h4>
                    @endif
                    
                    {{-- Form with fields for both table action and Reverse button --}}
                    <form wire:submit.prevent="submit" id="reverseTransactionForm" autocomplete="off">
                        @if(!$transactionLogId)
                            <div class="form-group">
                                <label for="reference_number">Reference Number <span class="text-danger">*</span></label>
                                <input type="text" 
                                       class="form-control" 
                                       id="reference_number"
                                       wire:model.defer="reference_number"
                                       placeholder="Enter reference number"
                                       maxlength="50"
                                       required>
                                @error('reference_number') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        @else
                            <div class="form-group">
                                <label for="reference_number">Reference Number</label>
                                <input type="text" 
                                       class="form-control" 
                                       id="reference_number"
                                       wire:model.defer="reference_number"
                                       placeholder="Enter reference number"
                                       maxlength="50"
                                       readonly
                                       style="background-color: #e9ecef;">
                                @error('reference_number') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        @endif

                        <div class="form-group">
                            <label for="reversal_date">Reversal Date <span class="text-danger">*</span></label>
                            <input type="text" 
                                   class="form-control kt_datepicker_reversal" 
                                   id="reversal_date"
                                   wire:ignore
                                   placeholder="YYYY-MM-DD"
                                   autocomplete="off"
                                   required>
                            @error('reversal_date') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="form-group">
                            <label for="comments">Comments <span class="text-danger">*</span></label>
                            <textarea class="form-control" 
                                      id="comments"
                                      wire:model.defer="comments"
                                      rows="3"
                                      placeholder="Enter comments for reversal"
                                      maxlength="500"
                                      required></textarea>
                            @error('comments') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="modal-footer d-flex justify-content-end gap-2">
                            <button type="button" wire:loading.attr="disabled" class="btn" id="submitReverseBtn" style="background-color: #403F86; color:white;">
                                <span wire:loading.remove wire:target="submit">Reverse</span>
                                <span wire:loading wire:target="submit">
                                    <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                                    Processing...
                                </span>
                            </button>
                            <button type="button" class="btn" data-bs-dismiss="modal" style="background-color: #F08021; color:white;">Close</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    (function() {
        var datepickerInitialized = false;
        
        function initializeReversalDatepicker() {
            if (datepickerInitialized) {
                return;
            }
            
            var restrictionDate = "{{ config('constants.policy.restrictionDate') }}";
            var $input = $('#reversal_date');
            
            // Destroy existing datepicker if any
            $input.datepicker('destroy');
            
            // Get current value from Livewire
            var currentValue = @this.get('reversal_date');
            
            // Initialize with restrictions
            $input.datepicker({
                todayHighlight: true,
                orientation: "bottom left",
                autoclose: true,
                format: 'yyyy-mm-dd',
                endDate: "today", // Don't allow future dates
                startDate: restrictionDate // Start from restriction date
            });
            
            // Set initial value if exists
            if (currentValue) {
                $input.datepicker('setDate', currentValue);
            }
            
            // Remove existing event handlers to avoid duplicates
            $input.off('changeDate change');
            
            // Sync datepicker value with Livewire when date is selected
            $input.on('changeDate', function(e) {
                // Get the date object and format it
                var date = e.date;
                var year = date.getFullYear();
                var month = String(date.getMonth() + 1).padStart(2, '0');
                var day = String(date.getDate()).padStart(2, '0');
                var selectedDate = year + '-' + month + '-' + day;
                
                // Update Livewire property
                @this.set('reversal_date', selectedDate);
            });
            
            // Also sync on input change (manual typing)
            $input.on('change', function() {
                var selectedDate = $(this).val();
                if (selectedDate) {
                    @this.set('reversal_date', selectedDate);
                }
            });
            
            datepickerInitialized = true;
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize datepicker when modal is shown
            $(document).on('shown.bs.modal', '#transactionLogDeleteModal', function() {
                datepickerInitialized = false;
                setTimeout(function() {
                    initializeReversalDatepicker();
                }, 100);
            });
            
            // Also initialize on page load if modal is already visible
            setTimeout(function() {
                if ($('#transactionLogDeleteModal').hasClass('show')) {
                    initializeReversalDatepicker();
                }
            }, 200);
            
            // Sync reversal_date from input field before button click and call submit
            var isSubmitting = false;
            $(document).on('click', '#submitReverseBtn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                // Prevent double submission
                if (isSubmitting) {
                    return false;
                }
                isSubmitting = true;
                
                var reversalDate = $('#reversal_date').val();
                var comments = $('#comments').val();
                
                // Validate required fields
                if (!reversalDate) {
                    alert('Please select a reversal date.');
                    isSubmitting = false;
                    return false;
                }
                if (!comments || comments.trim() === '') {
                    alert('Please enter comments.');
                    isSubmitting = false;
                    return false;
                }
                
                // Set the values before calling submit
                @this.set('reversal_date', reversalDate);
                @this.set('comments', comments);
                
                // Disable button to prevent double click
                $('#submitReverseBtn').prop('disabled', true);
                
                // Small delay to ensure Livewire processes the update, then call submit
                setTimeout(function() {
                    @this.call('submit').then(function() {
                        isSubmitting = false;
                        $('#submitReverseBtn').prop('disabled', false);
                    }).catch(function() {
                        isSubmitting = false;
                        $('#submitReverseBtn').prop('disabled', false);
                    });
                }, 100);
            });
            
            // Also sync on blur to ensure value is always up to date
            $(document).on('blur', '#reversal_date', function() {
                var reversalDate = $(this).val();
                if (reversalDate) {
                    @this.set('reversal_date', reversalDate);
                }
            });
        });
    })();
</script>
