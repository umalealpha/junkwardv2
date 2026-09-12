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
<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.10.0/js/bootstrap-datepicker.min.js" type="text/javascript"></script>

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
           $(".kt_datepicker_2").each(function () {

    var $input = $(this);
    var currentValue = ($input.val() || "").trim();

    var options = {
        maxDate: moment().subtract(1, 'days'),
        singleDatePicker: true,
        todayHighlight: true,
        templates: arrows,
        locale: { format: "DD/MM/YYYY" },
        autoUpdateInput: false  // prevents forcing date on empty input
    };

    // Case 1: VALUE EXISTS → preload it
    if (currentValue !== "") {

        let parsed = moment(currentValue, "DD/MM/YYYY");

        if (parsed.isValid()) {
            options.startDate = parsed; // set existing date
        }

    }

    // Initialize picker
    $input.daterangepicker(options);

    // If value existed → manually set it after init (otherwise daterangepicker clears it)
    if (currentValue !== "") {
        $input.val(currentValue);
    }

    // When user selects a date manually
    $input.on('apply.daterangepicker', function(ev, picker) {
        $(this).val(picker.startDate.format('DD/MM/YYYY')).trigger('change');
    });

});
        }
        return {
            init: function() {
                demoss();
            }
        };
    }();
    jQuery(document).ready(function() {
        KTBootstrapDatepicker.init();
        KTBootstrapDatepickerNew.init();
        $(document).on("change", '#{{$attributes->get("id")}}', function(e){
            this.value = e.target.value;
            @this.set("{{ $attributes->wire('model')->value }}",this.value);
        });
    });

</script>

