@extends('dev.layout', ['title' => $file])

@section('content')
<h2>{{ $file }} <span class="muted" style="font-weight: 400; font-size: 13px;"><a href="{{ url('/dev/docs') }}">← Back to docs</a></span></h2>
<pre style="white-space: pre-wrap;">{{ $content }}</pre>
@endsection
