@if(!empty($disableForm))
    <div class="alert alert-danger m-4">
        The contract cannot be created as the policy is not issued. Please issue the policy first, then try again.
    </div>
@endif

@if(empty($showOnlyModal))
<div class="card shadow-sm">
    <div class="alert alert-warning d-flex align-items-start gap-3 m-4 mb-0">
        <i class="fas fa-info-circle text-warning fs-4 mt-1"></i>
        <div class="text-gray-800">
            For creating a contract, <strong>ID Type and Number</strong>, <strong>Email</strong>, and <strong>Cellphone</strong> are mandatory. If they are missing, please add them from the <strong>Application Informations</strong> tab before proceeding.
        </div>
    </div>
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <h3 class="fw-bold m-0">Add Realpay Contract</h3>
        </div>
    </div>
    <div class="card-body pt-0">
        @if(empty($disableForm))
        <form wire:submit.prevent="submit">
            <!-- Customer Information Section -->
            <div class="mb-10">
                <h4 class="text-gray-800 fw-bold mb-4">
                    <i class="fas fa-user-circle text-primary me-2"></i>Customer Information
                </h4>
                <div class="row">
                    <div class="col-md-6 mb-4">
                        <label class="form-label fw-semibold text-gray-700">Policy Number</label>
                        <input type="text" class="form-control form-control-solid" value="{{ $policy->policyNumber ?? '' }}" readonly>
                    </div>
                    <div class="col-md-6 mb-4">
                        <label class="form-label fw-semibold text-gray-700">Customer Name</label>
                        <input type="text" class="form-control form-control-solid" wire:model="customerName" readonly>
                    </div>
                    <div class="col-md-6 mb-4">
                        <label class="form-label fw-semibold text-gray-700">ID Type and Number</label>
                        <input type="text" class="form-control form-control-solid" wire:model="idTypeAndNumber" readonly>
                    </div>
                    <div class="col-md-6 mb-4">
                        <label class="form-label fw-semibold text-gray-700">Email</label>
                        <input type="email" class="form-control form-control-solid" wire:model="email" readonly>
                    </div>
                    <div class="col-md-6 mb-4">
                        <label class="form-label fw-semibold text-gray-700">Cellphone</label>
                        <input type="text" class="form-control form-control-solid" wire:model="cellphone" readonly>
                    </div>
                </div>
            </div>

            <!-- Contract Details Section -->
            <div class="mb-10">
                <h4 class="text-gray-800 fw-bold mb-4">
                    <i class="fas fa-file-contract text-primary me-2"></i>Contract Details
                </h4>
                <div class="row">
                    <div class="col-md-6 mb-4">
                        <label class="form-label fw-semibold text-gray-700">
                            Payment Frequency <span class="text-danger">*</span>
                        </label>
                        @php
                            $frequencyLabel = [
                                '1' => 'Monthly',
                                '2' => 'Quarterly',
                                '3' => 'Annual',
                            ][$paymentFrequency ?? ''] ?? '';
                        @endphp
                        <input type="text" class="form-control form-control-solid" value="{{ $frequencyLabel }}" readonly>
                        @error('paymentFrequency') 
                            <div class="text-danger mt-1 small">{{ $message }}</div> 
                        @enderror
                    </div>

                    <div class="col-md-6 mb-4">
                        <label class="form-label fw-semibold text-gray-700">
                            Premium <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-light">P</span>
                            <input type="text" 
                                   class="form-control form-control-solid amount-input" 
                                   id="premiumInput"
                                   wire:ignore
                                   placeholder="Please provide premium" 
                                   required>
                            <input type="hidden" wire:model.defer="premium" id="premiumHidden">
                        </div>
                        @error('premium') 
                            <div class="text-danger mt-1 small">{{ $message }}</div> 
                        @enderror
                    </div>

                    <div class="col-md-6 mb-4">
                        <label class="form-label fw-semibold text-gray-700">
                            Billing Date <span class="text-danger">*</span>
                        </label>
                        <div class="position-relative">
                            <input type="text" 
                                   class="form-control form-control-solid payment-date-picker" 
                                   id="billingDate" 
                                   wire:ignore
                                   placeholder="Select date" 
                                   autocomplete="off" />
                            <span class="position-absolute top-50 end-0 translate-middle-y me-3">
                                <i class="fas fa-calendar-alt text-gray-400"></i>
                            </span>
                        </div>
                        <input type="hidden" wire:model.defer="billingDate">
                        @error('billingDate') 
                            <div class="text-danger mt-1 small">{{ $message }}</div> 
                        @enderror
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-gray-700 d-block">
                                Is your first collection date the same as the billing date?
                            </label>
                            <div class="d-flex gap-4">
                                <div class="form-check">
                                    <input class="form-check-input"
                                           type="radio"
                                           id="sameAsBillingDateYes"
                                           value="1"
                                           wire:model="isFirstCollectionSameAsBillingDate">
                                    <label class="form-check-label fw-semibold text-gray-700" for="sameAsBillingDateYes">
                                        Yes
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input"
                                           type="radio"
                                           id="sameAsBillingDateNo"
                                           value="0"
                                           wire:model="isFirstCollectionSameAsBillingDate">
                                    <label class="form-check-label fw-semibold text-gray-700" for="sameAsBillingDateNo">
                                        No
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-gray-700 d-block">
                                Is your first instalment premium the same as the premium?
                            </label>
                            <div class="d-flex gap-4">
                                <div class="form-check">
                                    <input class="form-check-input"
                                           type="radio"
                                           id="sameAsPremiumYes"
                                           value="1"
                                           wire:model="isFirstInstalmentSameAsPremium">
                                    <label class="form-check-label fw-semibold text-gray-700" for="sameAsPremiumYes">
                                        Yes
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input"
                                           type="radio"
                                           id="sameAsPremiumNo"
                                           value="0"
                                           wire:model="isFirstInstalmentSameAsPremium">
                                    <label class="form-check-label fw-semibold text-gray-700" for="sameAsPremiumNo">
                                        No
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-4">
                            @if($isFirstCollectionSameAsBillingDate)
                                <div class="text-gray-600 small mb-2">First Collection Date will match Billing Date.</div>
                            @endif
                            @if(!$isFirstCollectionSameAsBillingDate)
                                <label class="form-label fw-semibold text-gray-700">
                                    First Collection Date <span class="text-danger">*</span>
                                </label>
                                <div wire:loading wire:target="isFirstCollectionSameAsBillingDate" class="mb-3">
                                    <div class="d-flex align-items-center justify-content-start p-3 bg-light rounded">
                                        <div class="spinner-border spinner-border-sm text-primary me-3" role="status" style="width: 1.5rem; height: 1.5rem;">
                                            <span class="visually-hidden">Loading...</span>
                                        </div>
                                        <span class="text-gray-700 fw-semibold">Loading field...</span>
                                    </div>
                                </div>
                                <div wire:loading.remove wire:target="isFirstCollectionSameAsBillingDate">
                                    <div class="position-relative">
                                        <input type="text" 
                                               class="form-control form-control-solid payment-date-picker" 
                                               id="firstCollectionDate" 
                                               wire:ignore
                                               placeholder="Select date" 
                                               autocomplete="off" />
                                        <span class="position-absolute top-50 end-0 translate-middle-y me-3">
                                            <i class="fas fa-calendar-alt text-gray-400"></i>
                                        </span>
                                    </div>
                                    <input type="hidden" wire:model.defer="firstCollectionDate">
                                    @error('firstCollectionDate') 
                                        <div class="text-danger mt-1 small">{{ $message }}</div> 
                                    @enderror
                                </div>
                            @endif
                        </div>

                        <div class="col-md-6 mb-4">
                            @if($isFirstInstalmentSameAsPremium)
                                <div class="text-gray-600 small mb-2">First Instalment Amount will match Premium.</div>
                            @endif
                            @if(!$isFirstInstalmentSameAsPremium)
                                <label class="form-label fw-semibold text-gray-700">
                                    First Instalment Amount <span class="text-danger">*</span>
                                </label>
                                <div wire:loading wire:target="isFirstInstalmentSameAsPremium" class="mb-3">
                                    <div class="d-flex align-items-center justify-content-start p-3 bg-light rounded">
                                        <div class="spinner-border spinner-border-sm text-primary me-3" role="status" style="width: 1.5rem; height: 1.5rem;">
                                            <span class="visually-hidden">Loading...</span>
                                        </div>
                                        <span class="text-gray-700 fw-semibold">Loading field...</span>
                                    </div>
                                </div>
                                <div wire:loading.remove wire:target="isFirstInstalmentSameAsPremium">
                                    <div class="input-group">
                                        <span class="input-group-text bg-light">P</span>
                                        <input type="text" 
                                               class="form-control form-control-solid amount-input" 
                                               id="firstInstalmentAmountInput"
                                               wire:ignore
                                               placeholder="Please provide pro rata premium" 
                                               required>
                                        <input type="hidden" wire:model.defer="firstInstalmentAmount" id="firstInstalmentAmountHidden">
                                    </div>
                                    @error('firstInstalmentAmount') 
                                        <div class="text-danger mt-1 small">{{ $message }}</div> 
                                    @enderror
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- Banking Information Section -->
            <div class="mb-10">
                <h4 class="text-gray-800 fw-bold mb-4">
                    <i class="fas fa-university text-primary me-2"></i>Banking Information
                </h4>
                <div class="row">
                    <div class="col-md-6 mb-4">
                        <label class="form-label fw-semibold text-gray-700">
                            Bank <span class="text-danger">*</span>
                        </label>
                        <select class="form-select form-select-solid" wire:model="bankId" required>
                            <option value="">Select Bank</option>
                            @foreach($banks as $bank)
                                <option value="{{ $bank->bank_number }}">{{ $bank->bank_name }}</option>
                            @endforeach
                        </select>
                        @error('bankId') 
                            <div class="text-danger mt-1 small">{{ $message }}</div> 
                        @enderror
                    </div>

                    <div class="col-md-6 mb-4">
                        <label class="form-label fw-semibold text-gray-700">
                            Branch <span class="text-danger">*</span>
                        </label>
                        <select class="form-select form-select-solid" 
                                wire:model.defer="branchId" 
                                wire:key="branch-select-{{ $bankId }}" 
                                @if(!$bankId) disabled @endif 
                                required>
                            <option value="">@if($bankId) Please select bank branch @else Please select a bank first @endif</option>
                            @if($bankId)
                                @foreach($branches as $branch)
                                    <option value="{{ (string)$branch->branch_id }}">{{ $branch->name }}</option>
                                @endforeach
                            @endif
                        </select>
                        @error('branchId') 
                            <div class="text-danger mt-1 small">{{ $message }}</div> 
                        @enderror
                    </div>

                    <div class="col-md-6 mb-4">
                        <label class="form-label fw-semibold text-gray-700">
                            Account Number <span class="text-danger">*</span>
                        </label>
                        <input type="text" 
                               class="form-control form-control-solid" 
                               wire:model.defer="accountNumber" 
                               placeholder="Please provide bank account number" 
                               required>
                        @error('accountNumber') 
                            <div class="text-danger mt-1 small">{{ $message }}</div> 
                        @enderror
                    </div>

                    <div class="col-md-6 mb-4">
                        <label class="form-label fw-semibold text-gray-700">
                            Account Type <span class="text-danger">*</span>
                        </label>
                        <select class="form-select form-select-solid" 
                                wire:model.defer="accountType" 
                                wire:change.prevent 
                                required>
                            <option value="">Please select account type</option>
                            <option value="1">Cheque</option>
                            <option value="2">Savings</option>
                        </select>
                        @error('accountType') 
                            <div class="text-danger mt-1 small">{{ $message }}</div> 
                        @enderror
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="separator separator-dashed my-8"></div>
            <div class="d-flex justify-content-end gap-2">
                <button type="button" 
                        wire:click="cancel" 
                        class="btn btn-light btn-active-light-primary me-2"
                        wire:loading.attr="disabled">
                    <i class="fas fa-times me-2"></i>Cancel
                </button>
                <button type="submit" 
                        class="btn btn-primary" 
                        wire:loading.attr="disabled"
                        wire:target="submit">
                    <span wire:loading.remove wire:target="submit">
                        <i class="fas fa-save me-2"></i>Submit
                    </span>
                    <span wire:loading wire:target="submit">
                        <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                        Processing...
                    </span>
                </button>
            </div>
        </form>
        @endif
        <div class="alert alert-danger mt-4" role="alert">
            While creating a new contract, the system will cancel the existing contract and then create a new one.
        </div>
    </div>
</div>
@endif

<script>
(function() {
    var today = new Date();
    var todayMidnight = new Date(today.getFullYear(), today.getMonth(), today.getDate()).getTime();
    
    function initDatePicker(id, wireModel) {
        var $input = $('#' + id);
        if ($input.length === 0) return false;
        
        if (typeof jQuery === 'undefined' || typeof $.fn.datepicker === 'undefined') {
            setTimeout(function() { initDatePicker(id, wireModel); }, 100);
            return false;
        }
        
        if ($input.data('datepicker')) {
            $input.datepicker('destroy');
        }
        $input.off('changeDate change');
        
        var minDate = new Date();
        var minMidnight = new Date(minDate.getFullYear(), minDate.getMonth(), minDate.getDate()).getTime();

        $input.datepicker({
            todayHighlight: true,
            orientation: "bottom left",
            autoclose: true,
            format: 'dd/mm/yyyy',
            // Only allow today and future dates
            startDate: minDate,
            endDate: null,
            zIndexOffset: 1050,
            container: 'body',
            beforeShowDay: function(date) {
                var dMid = new Date(date.getFullYear(), date.getMonth(), date.getDate()).getTime();
                return dMid >= minMidnight; // true enables, false disables
            },
            templates: {
                leftArrow: '<i class="la la-angle-left"></i>',
                rightArrow: '<i class="la la-angle-right"></i>'
            }
        });

        // Ensure min date is enforced even after re-init
        try { $input.datepicker('setStartDate', minDate); } catch(err) {}
        
        // Prefill the visible input if Livewire already has a value
        try {
            var existingVal = @this.get(wireModel);
            if (existingVal) {
                $input.val(existingVal);
                $input.datepicker('setDate', existingVal);
            }
        } catch(err) {
            console.warn('Unable to prefill datepicker for', wireModel, err);
        }
        
        // Ensure the picker opens reliably on focus
        $input.on('focus', function() {
            try { $input.datepicker('show'); } catch(err) {}
        });
        
        $input.on('changeDate', function(e) {
            var date = e.date;
            var day = String(date.getDate()).padStart(2, '0');
            var month = String(date.getMonth() + 1).padStart(2, '0');
            var year = date.getFullYear();
            var selectedDate = day + '/' + month + '/' + year;
            $input.val(selectedDate);

            // Client-side guard: block past dates
            var selectedMid = new Date(year, month - 1, day).setHours(0,0,0,0);
            if (selectedMid < minMidnight) {
                // Reset to today if a past date slips through
                var todayDate = new Date(minMidnight);
                var tDay = String(todayDate.getDate()).padStart(2, '0');
                var tMonth = String(todayDate.getMonth() + 1).padStart(2, '0');
                var tYear = todayDate.getFullYear();
                var todayFormatted = tDay + '/' + tMonth + '/' + tYear;
                $input.val(todayFormatted);
                try { $input.datepicker('setDate', todayFormatted); } catch(err) {}
                selectedDate = todayFormatted;
                selectedMid = minMidnight;
            }

            // Update the hidden Livewire-bound input (deferred) without triggering an immediate re-render
            var $hidden = $input.closest('.mb-4').find('input[type="hidden"][wire\\:model\\.defer="' + wireModel + '"]');
            if ($hidden.length === 0) {
                // Fallback: search by wire:model if defer isn't present
                $hidden = $input.closest('.mb-4').find('input[type="hidden"][wire\\:model="' + wireModel + '"]');
            }
            if ($hidden.length) {
                $hidden.val(selectedDate);
                // Trigger input event so Livewire picks it up on the next interaction/submit
                $hidden.trigger('input');
            } else {
                // As a fallback (should not happen), try setting via Livewire
                try { @this.set(wireModel, selectedDate); } catch(err) {}
            }
        });
        
        return true;
    }
    
    function tryInit() {
        if (typeof jQuery === 'undefined' || typeof $.fn.datepicker === 'undefined') {
            setTimeout(tryInit, 100);
            return;
        }
        
        initDatePicker('firstCollectionDate', 'firstCollectionDate');
        initDatePicker('billingDate', 'billingDate');
    }
    
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', tryInit);
    } else {
        tryInit();
    }
    
    $(document).ready(tryInit);
    
    if (typeof Livewire !== 'undefined') {
        document.addEventListener('livewire:load', tryInit);
    }
    
    document.addEventListener('livewire:update', function() {
        setTimeout(tryInit, 100);
    });

    // Warning popup for quote stage policies (Bootstrap modal if available, else custom fallback)
    // Make it globally available
    window.showQuoteModal = function() {
        var modalEl = document.getElementById('quotePolicyModal');
        if (modalEl) {
            // Try Bootstrap 5 first
            if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                try {
                    if (typeof bootstrap.Modal.getOrCreateInstance === 'function') {
                        // Bootstrap 5
                        modalEl.style.display = 'block';
                        var m = bootstrap.Modal.getOrCreateInstance(modalEl);
                        m.show();
                        return true;
                    } else if (typeof bootstrap.Modal === 'function') {
                        // Bootstrap 4 or older - use constructor
                        modalEl.style.display = 'block';
                        var m = new bootstrap.Modal(modalEl);
                        m.show();
                        return true;
                    }
                } catch(e) {
                    console.warn('Bootstrap modal error:', e);
                }
            }
            
            // Try jQuery Bootstrap modal (Bootstrap 3/4)
            if (typeof jQuery !== 'undefined' && jQuery.fn.modal) {
                try {
                    jQuery(modalEl).modal('show');
                    return true;
                } catch(e) {
                    console.warn('jQuery modal error:', e);
                }
            }
        }
        
        // Fallback to custom modal
        var custom = document.getElementById('quotePolicyModalFallback');
        if (custom) {
            custom.classList.add('show');
            return true;
        }
        
        // Last resort: alert
        alert('The contract cannot be created as the policy is not issued. Please issue the policy first, then try again.');
        return false;
    };

    // Close modal function - handles all Bootstrap versions
    window.closeQuoteModal = function() {
        var modalEl = document.getElementById('quotePolicyModal');
        var custom = document.getElementById('quotePolicyModalFallback');
        var closed = false;
        
        // Try jQuery Bootstrap modal first (most common)
        if (modalEl && typeof jQuery !== 'undefined' && jQuery.fn.modal) {
            try {
                jQuery(modalEl).modal('hide');
                closed = true;
            } catch(e) {
                // Continue to next method
            }
        }
        
        // Try Bootstrap 5
        if (!closed && modalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            try {
                if (typeof bootstrap.Modal.getOrCreateInstance === 'function') {
                    // Bootstrap 5 - use getOrCreateInstance
                    var m = bootstrap.Modal.getOrCreateInstance(modalEl);
                    m.hide();
                    closed = true;
                } else if (typeof bootstrap.Modal.getInstance === 'function') {
                    // Bootstrap 5 - try to get existing instance
                    var m = bootstrap.Modal.getInstance(modalEl);
                    if (m) {
                        m.hide();
                        closed = true;
                    }
                } else if (typeof bootstrap.Modal === 'function') {
                    // Bootstrap 4 - try constructor approach
                    try {
                        var m = new bootstrap.Modal(modalEl);
                        m.hide();
                        closed = true;
                    } catch(e) {
                        // Constructor might fail if modal already exists
                    }
                }
            } catch(e) {
                // Continue to fallback
            }
        }
        
        // Fallback to custom modal
        if (!closed && custom) {
            custom.classList.remove('show');
            closed = true;
        }
        
        // Manual hide for Bootstrap modal (always do this as cleanup)
        if (modalEl) {
            modalEl.style.display = 'none';
            modalEl.classList.remove('show');
            modalEl.setAttribute('aria-hidden', 'true');
            var backdrop = document.querySelector('.modal-backdrop');
            if (backdrop) {
                backdrop.remove();
            }
            document.body.classList.remove('modal-open');
            document.body.style.overflow = '';
            document.body.style.paddingRight = '';
        }
        
        // Also hide custom modal as cleanup
        if (custom) {
            custom.classList.remove('show');
            custom.setAttribute('aria-hidden', 'true');
        }
        
        return closed;
    };

    // Always listen for event
    window.addEventListener('quote-policy-warning', function() {
        setTimeout(window.showQuoteModal, 50);
    });

    // Also auto-show on page load if disabled flag set
    if (window.quotePolicyDisabled === true) {
        setTimeout(window.showQuoteModal, 200);
    }

    // Amount formatting functions
    function formatNumberWithCommas(value) {
        if (!value) return '';
        // Remove all non-digit characters except decimal point
        var numStr = value.toString().replace(/[^\d.]/g, '');
        // Split by decimal point
        var parts = numStr.split('.');
        // Format integer part with commas
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        // Join back with decimal if exists
        return parts.length > 1 ? parts[0] + '.' + parts[1] : parts[0];
    }

    function removeCommas(value) {
        if (!value) return '';
        return value.toString().replace(/,/g, '');
    }

    function initAmountFormatting() {
        // Format Premium field
        var premiumInput = document.getElementById('premiumInput');
        var premiumHidden = document.getElementById('premiumHidden');
        if (premiumInput && premiumHidden) {
            // Format on blur
            premiumInput.addEventListener('blur', function() {
                var value = removeCommas(this.value);
                if (value) {
                    this.value = formatNumberWithCommas(value);
                    // Update hidden input for Livewire
                    premiumHidden.value = value;
                    premiumHidden.dispatchEvent(new Event('input'));
                }
            });
            
            // Format on input (as user types)
            premiumInput.addEventListener('input', function(e) {
                var cursorPos = this.selectionStart;
                var value = removeCommas(this.value);
                var formatted = formatNumberWithCommas(value);
                this.value = formatted;
                
                // Restore cursor position (adjust for added commas)
                var diff = formatted.length - value.length;
                var newPos = cursorPos + diff;
                this.setSelectionRange(newPos, newPos);
                
                // Update hidden input for Livewire
                premiumHidden.value = value;
                premiumHidden.dispatchEvent(new Event('input'));
            });
            
            // Format existing value on load from Livewire
            function updatePremiumDisplay() {
                if (premiumHidden && premiumHidden.value) {
                    premiumInput.value = formatNumberWithCommas(removeCommas(premiumHidden.value));
                } else if (premiumInput && !premiumInput.value && typeof @this !== 'undefined') {
                    // Try to get from Livewire directly
                    try {
                        var livewireValue = @this.get('premium');
                        if (livewireValue) {
                            premiumInput.value = formatNumberWithCommas(removeCommas(livewireValue.toString()));
                        }
                    } catch(err) {}
                }
            }
            
            updatePremiumDisplay();
            
            // Listen for changes to hidden input (when Livewire updates it)
            if (premiumHidden) {
                premiumHidden.addEventListener('input', updatePremiumDisplay);
                premiumHidden.addEventListener('change', updatePremiumDisplay);
            }
            
            // Listen for Livewire updates to format the display
            if (typeof Livewire !== 'undefined') {
                document.addEventListener('livewire:update', function() {
                    setTimeout(updatePremiumDisplay, 50);
                });
            }
        }

        // Format First Instalment Amount field
        var firstInstalmentInput = document.getElementById('firstInstalmentAmountInput');
        var firstInstalmentHidden = document.getElementById('firstInstalmentAmountHidden');
        if (firstInstalmentInput && firstInstalmentHidden) {
            // Format on blur
            firstInstalmentInput.addEventListener('blur', function() {
                var value = removeCommas(this.value);
                if (value) {
                    this.value = formatNumberWithCommas(value);
                    // Update hidden input for Livewire
                    firstInstalmentHidden.value = value;
                    firstInstalmentHidden.dispatchEvent(new Event('input'));
                }
            });
            
            // Format on input (as user types)
            firstInstalmentInput.addEventListener('input', function(e) {
                var cursorPos = this.selectionStart;
                var value = removeCommas(this.value);
                var formatted = formatNumberWithCommas(value);
                this.value = formatted;
                
                // Restore cursor position (adjust for added commas)
                var diff = formatted.length - value.length;
                var newPos = cursorPos + diff;
                this.setSelectionRange(newPos, newPos);
                
                // Update hidden input for Livewire
                firstInstalmentHidden.value = value;
                firstInstalmentHidden.dispatchEvent(new Event('input'));
            });
            
            // Format existing value on load from Livewire
            function updateFirstInstalmentDisplay() {
                if (firstInstalmentHidden && firstInstalmentHidden.value) {
                    firstInstalmentInput.value = formatNumberWithCommas(removeCommas(firstInstalmentHidden.value));
                } else if (firstInstalmentInput && !firstInstalmentInput.value && typeof @this !== 'undefined') {
                    // Try to get from Livewire directly
                    try {
                        var livewireValue = @this.get('firstInstalmentAmount');
                        if (livewireValue) {
                            firstInstalmentInput.value = formatNumberWithCommas(removeCommas(livewireValue.toString()));
                        }
                    } catch(err) {}
                }
            }
            
            updateFirstInstalmentDisplay();
            
            // Listen for changes to hidden input (when Livewire updates it)
            if (firstInstalmentHidden) {
                firstInstalmentHidden.addEventListener('input', updateFirstInstalmentDisplay);
                firstInstalmentHidden.addEventListener('change', updateFirstInstalmentDisplay);
            }
            
            // Listen for Livewire updates to format the display
            if (typeof Livewire !== 'undefined') {
                document.addEventListener('livewire:update', function() {
                    setTimeout(updateFirstInstalmentDisplay, 50);
                });
            }
        }
    }

    // Initialize amount formatting
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            initAmountFormatting();
            initFormSubmitHandler();
        });
    } else {
        initAmountFormatting();
        initFormSubmitHandler();
    }

    // Re-initialize on Livewire updates
    if (typeof Livewire !== 'undefined') {
        document.addEventListener('livewire:update', function() {
            setTimeout(initAmountFormatting, 100);
        });
    }

    // Handle form submission - ensure hidden inputs have unformatted values
    function initFormSubmitHandler() {
        var form = document.querySelector('form[wire\\:submit\\.prevent="submit"]');
        if (form) {
            form.addEventListener('submit', function(e) {
                // Ensure hidden inputs have unformatted values
                var premiumInput = document.getElementById('premiumInput');
                var premiumHidden = document.getElementById('premiumHidden');
                if (premiumInput && premiumHidden && premiumInput.value) {
                    var unformatted = removeCommas(premiumInput.value);
                    premiumHidden.value = unformatted;
                    premiumHidden.dispatchEvent(new Event('input'));
                }
                
                var firstInstalmentInput = document.getElementById('firstInstalmentAmountInput');
                var firstInstalmentHidden = document.getElementById('firstInstalmentAmountHidden');
                if (firstInstalmentInput && firstInstalmentHidden && firstInstalmentInput.value) {
                    var unformatted = removeCommas(firstInstalmentInput.value);
                    firstInstalmentHidden.value = unformatted;
                    firstInstalmentHidden.dispatchEvent(new Event('input'));
                }
            });
        }
    }
})();
</script>

<!-- Quote Policy Modal (Bootstrap) - Always available -->
<div class="modal fade" id="quotePolicyModal" tabindex="-1" aria-labelledby="quotePolicyModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="quotePolicyModalLabel">{{ $modalTitle ?? 'Policy Not Issued' }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" data-dismiss="modal" aria-label="Close" onclick="window.closeQuoteModal()">×</button>
      </div>
      <div class="modal-body">
        {{ $modalMessage ?? 'The contract cannot be created as the policy is not issued. Please issue the policy first, then try again.' }}
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-primary" data-bs-dismiss="modal" data-dismiss="modal" onclick="window.closeQuoteModal()">OK</button>
      </div>
    </div>
  </div>
</div>

<!-- Fallback modal (pure CSS/JS) - Always available -->
<style>
    #quotePolicyModalFallback {
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.5);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 1055;
    }
    #quotePolicyModalFallback.show { display: flex; }
    #quotePolicyModalFallback .modal-box {
        background: #fff;
        border-radius: 8px;
        max-width: 480px;
        width: 90%;
        padding: 20px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.2);
    }
    #quotePolicyModalFallback .modal-footer {
        display: flex;
        justify-content: flex-end;
        gap: 8px;
        margin-top: 16px;
    }
    #quotePolicyModalFallback .btn {
        padding: 8px 14px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
    }
    #quotePolicyModalFallback .btn-primary {
        background: #0d6efd;
        color: #fff;
    }
    #quotePolicyModalFallback .btn-light {
        background: #f1f1f1;
        color: #333;
    }
    #quotePolicyModalFallback .modal-title {
        margin: 0 0 8px 0;
        font-weight: 600;
        font-size: 18px;
    }
    #quotePolicyModalFallback .modal-body {
        color: #4b4b4b;
        line-height: 1.4;
    }
    #quotePolicyModalFallback .btn-close {
        background: transparent;
        border: none;
        font-size: 18px;
        cursor: pointer;
    }
</style>
<div id="quotePolicyModalFallback" aria-hidden="true" onclick="if(event.target === this) window.closeQuoteModal();">
    <div class="modal-box" onclick="event.stopPropagation();">
        <div style="display:flex;justify-content:space-between;align-items:center;">
            <div class="modal-title">{{ $modalTitle ?? 'Policy Not Issued' }}</div>
            <button type="button" class="btn-close" aria-label="Close" onclick="window.closeQuoteModal();">×</button>
        </div>
        <div class="modal-body">
            {{ $modalMessage ?? 'The contract cannot be created as the policy is not issued. Please issue the policy first, then try again.' }}
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-primary" onclick="window.closeQuoteModal();">OK</button>
        </div>
    </div>
</div>

@if(!empty($showOnlyModal))
<script>
    // Force show modal immediately when showOnlyModal is true
    (function() {
        var attempts = 0;
        var maxAttempts = 15;
        var shown = false;
        
        var modalTitle = @json($modalTitle ?? 'Policy Not Issued');
        var modalMessage = @json($modalMessage ?? 'The contract cannot be created as the policy is not issued. Please issue the policy first, then try again.');

        function tryShowModal() {
            if (shown) return true;
            
            attempts++;
            var success = false;
            
            // Use global function if available, otherwise define inline
            if (typeof window.showQuoteModal === 'function') {
                success = window.showQuoteModal();
            } else {
                var modalEl = document.getElementById('quotePolicyModal');
                var custom = document.getElementById('quotePolicyModalFallback');
                
                if (modalEl) {
                    // Try Bootstrap 5 first
                    if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                        try {
                            if (typeof bootstrap.Modal.getOrCreateInstance === 'function') {
                                // Bootstrap 5
                                modalEl.style.display = 'block';
                                var m = bootstrap.Modal.getOrCreateInstance(modalEl);
                                m.show();
                                success = true;
                            } else if (typeof bootstrap.Modal === 'function') {
                                // Bootstrap 4 or older - use constructor
                                modalEl.style.display = 'block';
                                var m = new bootstrap.Modal(modalEl);
                                m.show();
                                success = true;
                            }
                        } catch(e) {
                            console.warn('Bootstrap modal error:', e);
                        }
                    }
                    
                    // Try jQuery Bootstrap modal (Bootstrap 3/4)
                    if (!success && typeof jQuery !== 'undefined' && jQuery.fn.modal) {
                        try {
                            jQuery(modalEl).modal('show');
                            success = true;
                        } catch(e) {
                            console.warn('jQuery modal error:', e);
                        }
                    }
                }
                
                // Fallback to custom modal
                if (!success && custom) {
                    custom.classList.add('show');
                    success = true;
                }
            }
            
            if (success) {
                shown = true;
                return true;
            }
            
            // Retry if not shown and haven't exceeded attempts
            if (attempts < maxAttempts) {
                setTimeout(tryShowModal, 150);
                return false;
            }
            
            // Last resort: alert
            if (!shown) {
                alert(modalMessage);
                shown = true;
            }
            return false;
        }
        
        // Try immediately
        tryShowModal();
        
        // Also try on DOM ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                attempts = 0;
                setTimeout(tryShowModal, 100);
            });
        } else {
            setTimeout(function() {
                attempts = 0;
                tryShowModal();
            }, 200);
        }
        
        // Listen for Livewire events
        if (typeof Livewire !== 'undefined') {
            document.addEventListener('livewire:load', function() {
                attempts = 0;
                setTimeout(tryShowModal, 300);
            });
            
            document.addEventListener('livewire:update', function() {
                attempts = 0;
                setTimeout(tryShowModal, 300);
            });
        }
        
        // Also listen for the browser event
        window.addEventListener('quote-policy-warning', function() {
            attempts = 0;
            setTimeout(tryShowModal, 50);
        });
    })();
</script>
@endif
