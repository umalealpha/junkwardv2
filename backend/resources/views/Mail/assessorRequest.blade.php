@component('mail::layout')
    {{-- Header --}}
    @slot('header')
        @component('mail::header', ['url' => config('app.url')])
            <div class="clearfix float-my-children">
                <img style="width: 70px; " src="{{ asset('/images/logo.png') }}">
                <div>{{ config('app.name') }}</div>
            </div>
        @endcomponent
    @endslot
    {{-- Body --}}
    <div style="text-align: center;">
        {!! $mail_template !!}
        @if($is_attorney == 0)
            <a href="{!! route('assessorView',$assessment_id) !!}"><button type="button" style="background-color: #4CAF50; color: white; border: 2px solid #4CAF50; font-size: 16px; padding: 10px 24px; border-radius: 4px;">Submit Report</button></a>
            <a href=""><button type="button" style="background-color: #f44336; color: white; border: 2px solid #f44336; font-size: 16px; padding: 10px 24px; border-radius: 4px;">Reject</button></a>
        @endif
    </div>
    Thanks,
    @slot('footer')
    @component('mail::footer')
    &copy; {{ now()->year }} Copyrights Reserved
@endcomponent
@endslot
@endcomponent