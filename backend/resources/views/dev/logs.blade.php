@extends('dev.layout', ['title' => 'Error Logs'])

@section('content')
<h2>Error Logs</h2>
<p class="muted">Tailing <code>{{ $path }}</code> — last {{ number_format($lines) }} lines.</p>

<form method="get" style="margin: 14px 0;">
    <label class="muted" style="font-size: 12px;">Lines:</label>
    <input type="number" name="lines" min="100" max="20000" value="{{ $lines }}" step="100" style="width: 120px;">
    <button type="submit">Reload</button>
    <a href="{{ url('/dev/logs?lines=' . $lines) }}" style="margin-left: 12px; font-size: 12px;">Refresh</a>
</form>

@if (empty($tail))
    <div class="card"><em>Log file is empty or missing.</em></div>
@else
    <pre id="log">{{ $tail }}</pre>
@endif

<script>
    // Auto-scroll to bottom so newest lines are visible on load.
    var pre = document.getElementById('log');
    if (pre) pre.scrollTop = pre.scrollHeight;
</script>
@endsection
