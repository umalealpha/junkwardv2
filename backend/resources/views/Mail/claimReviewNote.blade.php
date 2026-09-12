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

{{-- Body + button MUST stay at column 0. Markdown mail runs the rendered
     blade through CommonMark, which treats any line indented 4+ spaces as a
     literal code block — that's why the old raw <a><button> showed as HTML
     text. $body is pre-rendered HTML (nl2br(e(...))); the CTA uses the
     built-in mail::button component so it renders through the markdown pipe. --}}
{!! $body !!}

@if(!empty($reviewUrl))
@component('mail::button', ['url' => $reviewUrl, 'color' => 'primary'])
View Review
@endcomponent
@endif

Thanks,

    @slot('footer')
        @component('mail::footer')
            &copy; {{ now()->year }} Copyrights Reserved
        @endcomponent
    @endslot
@endcomponent
