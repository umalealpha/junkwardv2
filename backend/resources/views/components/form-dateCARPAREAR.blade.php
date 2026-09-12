@props([
	'disabled' => false,
	'model' => $attributes->wire('model')->value ?? $attributes->get('name')
])

{{-- <div x-data="{ value: @entangle($attributes->wire('model'))}"  wire:ignore> --}}
    @error($model)
    <input {{ $disabled ? 'disabled' : '' }} {!! $attributes->merge(['class' => 'form-control is-invalid']) !!} >
    @else
    <input {{ $disabled ? 'disabled' : '' }} {!! $attributes->merge(['class' => 'form-control']) !!}  >
    @enderror
{{-- </div> --}}

@push('scripts')
    <script>

        // $('#{{$attributes->get("id")}}').on('change', function (e) {
        //     this.value = e.target.value;
        //     @this.set("{{ $attributes->wire('model')->value }}",this.value);
        // });

        // $('body').on('change',"#{{$attributes->get("id")}}", function(){
        //     alert('{{$attributes->get("id")}}');
        // });

        // $(document).on("change", "#{{$attributes->get("id")}}", function(){
        //     // alert('{{$attributes->get("id")}}');
        //     this.value = e.target.value;
        //     @this.set("{{ $attributes->wire('model')->value }}",this.value);
        // });
    </script>
@endpush
<link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.10.0/css/bootstrap-datepicker.min.css" rel="stylesheet" type="text/css" />
<link href="{{ asset('assets/vendors/general/bootstrap-daterangepicker/daterangepicker.css') }}" rel="stylesheet" type="text/css" />
<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.10.0/js/bootstrap-datepicker.min.js" type="text/javascript"></script>
<script src="{{asset('assets/vendors/general/moment/min/moment.min.js')}}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/general/bootstrap-daterangepicker/daterangepicker.js') }}" type="text/javascript"></script>

<script type="text/javascript">
    var KTBootstrapDatepicker = function () {
        var arrows;
            arrows = {
                leftArrow: '<i class="la la-angle-right"></i>',
                rightArrow: '<i class="la la-angle-left"></i>'
            }
        var demos = function () {
            $('.kt_datepicker_1').datepicker({
                todayHighlight: true,
                orientation: "bottom left",
                templates: arrows,
                format: "{{ config('constants.date.js_format') }}",
            });
        }
        return {
            init: function() {
                demos();
            }
        };
    }();

    var KTBootstrapDatepickerNew = function () {
        var arrows;
            arrows = {
                leftArrow: '<i class="la la-angle-right"></i>',
                rightArrow: '<i class="la la-angle-left"></i>'
            }
        var demoss = function () {
            $(".kt_datepicker_2").each(function() {
                var $input = $(this);
                var currentValue = $input.val();
                var startDate = moment().subtract(1, 'days');
                
                // If there's a valid date value, use it
                if (currentValue && currentValue !== 'Invalid date' && currentValue !== 'undefinedInvalid date') {
                    var parsed = moment(currentValue, 'DD/MM/YYYY', true);
                    if (parsed.isValid() && parsed.isBefore(moment())) {
                        startDate = parsed;
                    } else {
                        parsed = moment(currentValue);
                        if (parsed.isValid() && parsed.isBefore(moment())) {
                            startDate = parsed;
                        }
                    }
                }
                
                // Destroy existing daterangepicker if it exists
                if ($input.data('daterangepicker')) {
                    $input.data('daterangepicker').remove();
                }
                
                $input.daterangepicker({
                    maxDate: moment().subtract(1, 'days'),
                    singleDatePicker: true,
                    startDate: startDate,
                    todayHighlight: true,
                    templates: arrows,
                    locale: {
                        format: 'DD/MM/YYYY'
                    },
                    autoUpdateInput: false,
                    autoApply: true
                }).on('apply.daterangepicker', function(ev, picker) {
                    var formattedDate = picker.startDate.format('DD/MM/YYYY');
                    var inputElement = $input[0];
                    
                    // Set the value first
                    $input.val(formattedDate);
                    inputElement.value = formattedDate;
                    
                    // Use setTimeout to ensure value is set before dispatching events
                    setTimeout(function() {
                        // Dispatch native events that Livewire can detect
                        var nativeInputEvent = new Event('input', { bubbles: true, cancelable: true });
                        var nativeChangeEvent = new Event('change', { bubbles: true, cancelable: true });
                        inputElement.dispatchEvent(nativeInputEvent);
                        inputElement.dispatchEvent(nativeChangeEvent);
                        
                        // Also trigger jQuery events for compatibility
                        $input.trigger('input');
                        $input.trigger('change');
                    }, 10);
                });
            });
        }
        return {
            init: function() {
                demoss();
            }
        };
    }();

    var KTBootstrapDatepickerUnrestricted = function () {
        var arrows;
            arrows = {
                leftArrow: '<i class="la la-angle-right"></i>',
                rightArrow: '<i class="la la-angle-left"></i>'
            }
        var demosUnrestricted = function () {
            $(".kt_datepicker_2_unrestricted").each(function() {
                var $input = $(this);
                
                // Clear "Invalid date" text immediately
                if ($input.val() === 'Invalid date' || $input.val() === 'undefinedInvalid date') {
                    $input.val('');
                }
                
                var currentValue = $input.val();
                var startDate = moment();
                var maxDate = null;
                
                // Check if this is an expiry date field and set maxDate based on inception date
                var wireModel = $input.attr('wire:model') || $input.attr('wire:model.defer') || '';
                
                // CAR Coverage: policy_expiry_date should be max 3 years from policy_inception_date
                if (wireModel.includes('policy_expiry_date')) {
                    var match = wireModel.match(/carCoverage\.(\d+)\.policy_expiry_date/);
                    if (match) {
                        var policyCoverageId = match[1];
                        var $inceptionInput = $('input[wire\\:model="carCoverage.' + policyCoverageId + '.policy_inception_date"]');
                        if ($inceptionInput.length > 0) {
                            var inceptionValue = $inceptionInput.val();
                            if (inceptionValue) {
                                var inceptionMoment = moment(inceptionValue, 'DD/MM/YYYY', true);
                                if (!inceptionMoment.isValid()) {
                                    inceptionMoment = moment(inceptionValue);
                                }
                                if (inceptionMoment.isValid()) {
                                    maxDate = moment(inceptionMoment).add(3, 'years');
                                }
                            }
                        }
                    }
                }
                
                // EAR Coverage: period_to should be max 3 years from period_from
                if (wireModel.includes('period_to')) {
                    var match = wireModel.match(/earCoverage\.(\d+)\.period_to/);
                    if (match) {
                        var policyCoverageId = match[1];
                        var $fromInput = $('input[wire\\:model\\.defer="earCoverage.' + policyCoverageId + '.period_from"]');
                        if ($fromInput.length > 0) {
                            var fromValue = $fromInput.val();
                            if (fromValue) {
                                var fromMoment = moment(fromValue, 'DD/MM/YYYY', true);
                                if (!fromMoment.isValid()) {
                                    fromMoment = moment(fromValue);
                                }
                                if (fromMoment.isValid()) {
                                    maxDate = moment(fromMoment).add(3, 'years');
                                }
                            }
                        }
                    }
                }
                
                // If there's a valid date value, use it; otherwise use today
                if (currentValue && currentValue !== 'Invalid date' && currentValue !== 'undefinedInvalid date') {
                    var parsed = moment(currentValue, 'DD/MM/YYYY', true);
                    if (parsed.isValid()) {
                        startDate = parsed;
                    } else {
                        // Try parsing without strict format
                        parsed = moment(currentValue);
                        if (parsed.isValid()) {
                            startDate = parsed;
                        }
                    }
                }
                
                // Destroy existing daterangepicker if it exists
                if ($input.data('daterangepicker')) {
                    $input.data('daterangepicker').remove();
                }
                
                var datepickerOptions = {
                    singleDatePicker: true,
                    startDate: startDate,
                    todayHighlight: true,
                    templates: arrows,
                    locale: {
                        format: 'DD/MM/YYYY'
                    },
                    autoUpdateInput: false,
                    autoApply: true
                };
                
                // Add maxDate if restriction is needed
                if (maxDate) {
                    datepickerOptions.maxDate = maxDate;
                }
                
                $input.daterangepicker(datepickerOptions).on('apply.daterangepicker', function(ev, picker) {
                    var formattedDate = picker.startDate.format('DD/MM/YYYY');
                    var inputElement = $input[0];
                    
                    // Set the value first
                    $input.val(formattedDate);
                    inputElement.value = formattedDate;
                    
                    // Use setTimeout to ensure value is set before dispatching events
                    setTimeout(function() {
                        // Dispatch native events that Livewire can detect
                        var nativeInputEvent = new Event('input', { bubbles: true, cancelable: true });
                        var nativeChangeEvent = new Event('change', { bubbles: true, cancelable: true });
                        inputElement.dispatchEvent(nativeInputEvent);
                        inputElement.dispatchEvent(nativeChangeEvent);
                        
                        // Also trigger jQuery events for compatibility
                        $input.trigger('input');
                        $input.trigger('change');
                    }, 10);
                });
                
                // Clear invalid values on focus
                $input.on('focus', function() {
                    var val = $input.val();
                    if (val === 'Invalid date' || val === 'undefinedInvalid date' || (val && !moment(val, 'DD/MM/YYYY', true).isValid() && !moment(val).isValid())) {
                        $input.val('');
                    }
                });
            });
        }
        return {
            init: function() {
                demosUnrestricted();
            }
        };
    }();
    jQuery(document).ready(function() {
        KTBootstrapDatepicker.init();
        KTBootstrapDatepickerNew.init();
        KTBootstrapDatepickerUnrestricted.init();
    });
    
    // Reinitialize datepickers after Livewire updates
    document.addEventListener('livewire:load', function () {
        KTBootstrapDatepickerNew.init();
        KTBootstrapDatepickerUnrestricted.init();
    });
    
    document.addEventListener('livewire:update', function () {
        setTimeout(function() {
            KTBootstrapDatepickerNew.init();
            KTBootstrapDatepickerUnrestricted.init();
        }, 100);
    });

</script>

