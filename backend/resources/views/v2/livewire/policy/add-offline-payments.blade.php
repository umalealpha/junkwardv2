<form wire:submit.prevent="submit" autocomplete="off">
    <div class="row">
        {{-- @if(\Illuminate\Support\Facades\Auth::user()->hasPermissionTo('back-dated-transactions')) --}}
            <div class="col-sm-4">
                <div class="form-floating mb-3">
                    <input type="text" 
                           class="form-control payment-date-picker" 
                           id="paymentDate" 
                           wire:ignore
                           placeholder="Date of payment" 
                           autocomplete="off" />
                    <x-form-label for="paymentDate" required value="Date of payment"/>
                    <x-form-input-error name="paymentDate"/>
                </div>
            </div>
            
            <script>
            (function() {
                var restrictDate = "{{ config('constants.policy.restrictionDate') }}";
                var initialized = false;
                
                function initDatePicker() {
                    var $input = $('#paymentDate');
                    if ($input.length === 0) {
                        setTimeout(initDatePicker, 100);
                        return;
                    }
                    
                    if (typeof jQuery === 'undefined' || typeof $.fn.datepicker === 'undefined') {
                        setTimeout(initDatePicker, 100);
                        return;
                    }
                    
                    // Destroy existing if any
                    if ($input.data('datepicker')) {
                        $input.datepicker('destroy');
                    }
                    $input.off('changeDate change');
                    
                    // Initialize
                    $input.datepicker({
                        todayHighlight: true,
                        orientation: "bottom left",
                        autoclose: true,
                        format: 'dd/mm/yyyy',
                        startDate: restrictDate,
                        endDate: new Date(),
                        zIndexOffset: 1050,
                        templates: {
                            leftArrow: '<i class="la la-angle-left"></i>',
                            rightArrow: '<i class="la la-angle-right"></i>'
                        }
                    });
                    
                    // Sync with Livewire
                    $input.on('changeDate', function(e) {
                        var date = e.date;
                        var day = String(date.getDate()).padStart(2, '0');
                        var month = String(date.getMonth() + 1).padStart(2, '0');
                        var year = date.getFullYear();
                        var selectedDate = day + '/' + month + '/' + year;
                        $input.val(selectedDate);
                        try {
                            @this.set('paymentDate', selectedDate);
                        } catch(err) {
                            console.error('Error setting date:', err);
                        }
                    });
                    
                    $input.on('change blur', function() {
                        var val = $(this).val();
                        if (val) {
                            try {
                                @this.set('paymentDate', val);
                            } catch(e) {}
                        }
                    });
                    
                    initialized = true;
                }
                
                // Clear function - make it globally accessible
                window.clearPaymentDatePicker = function() {
                    var $input = $('#paymentDate');
                    if ($input.length > 0) {
                        if ($input.data('datepicker')) {
                            $input.datepicker('clearDates');
                        }
                        $input.val('');
                        initialized = false; // Reset flag so it can reinitialize
                    }
                };
                
                // Try to initialize
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', initDatePicker);
                } else {
                    initDatePicker();
                }
                
                $(document).ready(initDatePicker);
                
                if (typeof Livewire !== 'undefined') {
                    document.addEventListener('livewire:load', initDatePicker);
                }
                
                document.addEventListener('livewire:update', function() {
                    setTimeout(function() {
                        // Reinitialize if needed
                        if (!initialized) {
                            initDatePicker();
                        }
                        
                        // Clear if Livewire value is empty but input has value
                        try {
                            var livewireValue = @this.get('paymentDate');
                            var $input = $('#paymentDate');
                            
                            if ((!livewireValue || livewireValue === '' || livewireValue === null) && 
                                $input.length > 0 && $input.val() !== '') {
                                window.clearPaymentDatePicker();
                            }
                        } catch(e) {
                            // Ignore errors
                        }
                    }, 200);
                });
            })();
            </script>
        {{-- @else
            <div class="col-sm-4">
                <div class="form-floating mb-3">
                    <x-form-date type="text" class="kt_datepicker_1" id="date_of_refund_for_current_month" wire:model.lazy="paymentDate"  placeholder="Date of payment" />
                    <x-form-label for="paymentDate" required value="Date of payment"/>
                    <x-form-input-error name="paymentDate"/>
                </div>
            </div>
        @endif --}}

        {{-- @if (Auth::user()->hasPermissionTo('edit_add_offline_payment')) --}}
            <div class="col-sm-4">
                <div class="form-floating mb-3">
                    <x-form-input type="text" name="paymentAmount" wire:model.defer="paymentAmount" placeholder="Payment Amount" x-mask:dynamic="$money($input)"/>
                    <x-form-label for="paymentAmount" value="Payment Amount"/>
                    <x-form-input-error name="paymentAmount"/>
                </div>
            </div>
        {{-- @else
            <div class="col-sm-4">
                <div class="form-floating mb-3">
                    <x-form-input type="text" name="paymentAmount" wire:model.defer="paymentAmount" placeholder="Payment Amount"  x-mask:dynamic="$money($input)"/>
                    <x-form-label for="paymentAmount" value="Payment Amount"/>
                    <x-form-input-error name="paymentAmount"/>
                </div>
            </div>
        @endif --}}

        <div class="col-sm-4">
            <div class="form-floating mb-3">
                <x-form-input type="text" name="receiptNumber" wire:model.defer="receiptNumber" placeholder="Receipt number"/>
                <x-form-label for="receiptNumber" value="Receipt number"/>
                <x-form-input-error name="receiptNumber"/>
            </div>
        </div>

        <div class="col-sm-4">
            <div class="form-floating mb-3">
                <x-form-input type="text" name="paymentRecievedBy" wire:model.defer="paymentRecievedBy" placeholder="Payment Recieved By"/>
                <x-form-label for="paymentRecievedBy" value="Payment Recieved By"/>
                <x-form-input-error name="paymentRecievedBy"/>
            </div>
        </div>

        @if (Auth::user()->hasPermissionTo('edit_add_offline_payment'))
            <div class="col-sm-4">
                <div class="form-floating mb-3">
                    <x-form-input type="text" name="numberOfInstalmentsPaid" wire:model.defer="numberOfInstalmentsPaid" placeholder="Numbers of instalments paid"/>
                    <x-form-label for="numberOfInstalmentsPaid" value="Numbers of instalments paid"/>
                    <x-form-input-error name="numberOfInstalmentsPaid"/>
                </div>
            </div>
        @endif

        <div class="col-sm-4">
            <div class="form-floating mb-3">
                <x-form-input type="text" name="paymentNote" wire:model.defer="paymentNote" placeholder="Note"/>
                <x-form-label for="paymentNote" value="Note"/>
                <x-form-input-error name="paymentNote"/>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="avatar-upload">
            <div class="avatar-edit">
                <input type='file' name="payment_image" wire:model.defer="payment_image" id="imageUpload"/>
                <label for="imageUpload"><i class="fa fa-pen" aria-hidden="true" title="Customer data edit" style="margin: 8px;"></i></label>
            </div>

            <div class="avatar-preview">
                @if ($payment_image)
                    <img src="{{ $payment_image->temporaryUrl() }}" alt="New Image Preview">
                @elseif ($imageExist)
                    <a id="linkImagePreview" href="{{ \AlphaDirect\Helper::getCloudFrontURL($imageExist) }}" target="_blank">
                        @if (pathinfo($imageExist, PATHINFO_EXTENSION) == 'pdf')
                            <img src="{{ asset('v2/image-plugin/image/pdf.png') }}" >
                        @elseif (pathinfo($imageExist, PATHINFO_EXTENSION) == 'docx' || pathinfo($imageExist, PATHINFO_EXTENSION) == 'doc' || pathinfo($imageExist, PATHINFO_EXTENSION) == 'docm')
                            <img src="{{ asset('v2/image-plugin/image/word.png') }}" >
                        @elseif (pathinfo($imageExist, PATHINFO_EXTENSION) == 'txt')
                            <img src="{{ asset('v2/image-plugin/image/text.jpg') }}">
                        @elseif (pathinfo($imageExist, PATHINFO_EXTENSION) == 'xls' || pathinfo($imageExist, PATHINFO_EXTENSION) == 'xlsx' || pathinfo($imageExist, PATHINFO_EXTENSION) == 'csv')
                            <img src="{{ asset('v2/image-plugin/image/excel.png') }}" >
                        @else
                            <img src="{{ \AlphaDirect\Helper::getCloudFrontURL($imageExist) }}" >
                        @endif
                    </a>
                @else
                    <img src="{{ asset('v2/image-plugin/image/avatar.jpg') }}" >
                @endif
            </div>

            <div wire:loading wire:target="payment_image" class="loading-spinner">
                <i class="fa fa-spinner fa-spin"></i>
            </div>
        </div>
        <div class="ml-4 mt-1">Upload Payment Proof</div>

    </div>

    <!-- Submit Button -->
    <div class="row col-xs-12 col-sm-12 col-md-12 mt-5 text-center">
        <div class="col-sm-12">
            <button type="submit" class="btn btn-primary" id="submitPaymentBtn">Submit</button>
        </div>
    </div>
</form>
@push('scripts')
    <script>
        $("#imageUpload_{{$this->imageField}}").change(function () {
            readImageUploadComponent(this, "imagePreview_{{$this->imageField}}", "imageUpload_{{$this->imageField}}", "linkImagePreview_{{$this->imageField}}");
        });
        
        // Listen for success alert to clear datepicker
        window.addEventListener('alert', function(event) {
            if (event.detail && event.detail.type === 'success') {
                setTimeout(function() {
                    if (typeof window.clearPaymentDatePicker === 'function') {
                        window.clearPaymentDatePicker();
                    }
                }, 300);
            }
        });
        
        // Ensure date is synced before form submission
        $(document).on('submit', 'form[wire\\:submit\\.prevent="submit"]', function(e) {
            var $paymentDateInput = $('#paymentDate');
            if ($paymentDateInput.length > 0) {
                var dateValue = $paymentDateInput.val();
                if (dateValue && dateValue.trim() !== '') {
                    @this.set('paymentDate', dateValue);
                }
            }
        });
        
        // Also handle button click to sync before Livewire processes
        $(document).on('click', '#submitPaymentBtn', function(e) {
            var $paymentDateInput = $('#paymentDate');
            if ($paymentDateInput.length > 0) {
                var dateValue = $paymentDateInput.val();
                if (dateValue && dateValue.trim() !== '') {
                    @this.set('paymentDate', dateValue);
                }
            }
        });
    </script>
@endpush
