@extends('dev.layout', ['title' => 'Dev Docs'])

@section('content')
<h2>Dev Docs</h2>
<p class="muted">Files under <code>backend/dev/</code>. Commit new <code>.md</code> files there and they'll show up here.</p>

@if (empty($files))
    <div class="card"><em>No docs yet. Run <code>php artisan dev:generate-docs</code> to seed <code>API_ENDPOINTS.md</code>.</em></div>
@else
    @foreach ($files as $f)
        <a class="card" href="{{ $f['url'] }}" style="display: block;">
            <h3>{{ $f['name'] }}</h3>
            <p>{{ number_format($f['size']) }} bytes</p>
        </a>
    @endforeach
@endif
@endsection
