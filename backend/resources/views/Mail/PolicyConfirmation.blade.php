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
        <p>Your policy has been created successfully.</p>
          <p> Policy Number is : <span style="font-weight: bold;color:darkred"> {!! $policyNumber !!}  </span></p>
    </div>

    Thanks,
    {{--<img src="{{ asset('/images/mail_footer.gif') }}">--}}

    {{-- Footer --}}
    @slot('footer')
    @component('mail::footer')
    &copy; {{ now()->year }} Copyrights Reserved
@endcomponent
@endslot
@endcomponent