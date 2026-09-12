<div
    x-data="{
    optionsData: @js($options),trackBy:@js($trackBy ?? 'id'),name:@js($attributes->get('id')),value: @entangle($attributes->wire('model')),
    listner: @js($attributes->get('listner') ?? '0'),
        rebuild(event){
            if(event.detail.key==this.listner){
            if ($(this.$refs.select).hasClass('select2-hidden-accessible')) {
                $(this.$refs.select).select2('destroy');
                console.log('destroy select2');
                }
                this.select2 = $(this.$refs.select).select2({
                data:[
                {
                id: 0,text: 'enhancement'},]
                });
                this.select2.on('select2:select', (event) => {
                    this.value = event.target.value;
                });
            }
        }
	}"
    x-init="select2Alpine"  wire:ignore
    x-on:dropdown-changed.window="rebuild($event)"
>
    <select class="form-select form-select-solid ss" x-ref="select" id="{{$attributes->get('id')}}" value="{{ $attributes->get('value') }}">
        <option value=""> - Select -</option>
        <template x-for="(Name, index) in optionsData">
            <option :value="index" x-text="Name"></option>
        </template>
    </select>
</div>
@push('scripts')
    <script>
{{--        function select2Alpine(currentDomId,obj) {--}}
{{--            // selectId = ;--}}
{{--            // $("#"+id).select2();--}}
{{--            {{ $attributes->get('id') }}_select2.on("select2:select", (event) => {--}}
{{--                this.value = event.target.value;--}}
{{--            });--}}


{{--    obj.select2 = $("#"+currentDomId).select2();--}}
{{--            obj.select2.on("select2:select", (event) => {--}}
{{--                obj.value = event.target.value;--}}
{{--                console.log("Select Option in drop-down....."+event.target.value);--}}
{{--            });--}}
{{--            // this.$watch("selectedCity", (value) => {--}}
{{--            //     this.select2.val(value).trigger("change");--}}
{{--            // });--}}
{{--        }--}}
function select2Alpine() {
    if ($(this.$refs.select).hasClass("select2-hidden-accessible")) {
        $(this.$refs.select).select2("destroy");
        console.log('destroy select2');
    }
    this.select2 = $(this.$refs.select).select2();
    this.select2.on("select2:select", (event) => {
        this.value = event.target.value;
    });
    // this.$watch("selectedCity", (value) => {
    //     this.select2.val(value).trigger("change");
    // });
}

    </script>
@endpush
