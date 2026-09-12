@extends('dev.layout', ['title' => 'API Endpoints'])

@section('content')
<h2>API Endpoints <span class="muted" style="font-weight: 400;">({{ $total }})</span></h2>
<p class="muted">Live from <code>Route::getRoutes()</code>. Filter with the search box (matches URI or controller).</p>

<div style="margin: 14px 0;">
    <input id="q" type="search" placeholder="Filter — e.g. policies, claims, PolicyCreateController..." style="width: 360px;">
    <span class="muted" id="count" style="margin-left: 10px;"></span>
</div>

<details open>
    <summary><strong>Groups</strong></summary>
    <div class="toc" style="margin-top: 10px;">
        @foreach ($grouped as $g => $rs)
            <div><a href="#g-{{ \Illuminate\Support\Str::slug($g) }}">{{ $g }}</a> <span class="muted">({{ $rs->count() }})</span></div>
        @endforeach
    </div>
</details>

@foreach ($grouped as $g => $rs)
    <h3 id="g-{{ \Illuminate\Support\Str::slug($g) }}" style="margin-top: 24px;">{{ $g }} <span class="muted" style="font-weight: 400; font-size: 13px;">({{ $rs->count() }})</span></h3>
    <table class="rtable">
        <thead><tr><th style="width:90px;">Method</th><th>URI</th><th>Controller @ Action</th><th>Middleware</th></tr></thead>
        <tbody>
        @foreach ($rs as $r)
            <tr class="rrow" data-txt="{{ strtolower($r['uri'] . ' ' . $r['action']) }}">
                <td>
                    @foreach ($r['methods'] as $m)
                        <span class="method method-{{ $m }}">{{ $m }}</span>
                    @endforeach
                </td>
                <td><code>{{ $r['uri'] }}</code></td>
                <td><code>{{ $r['action'] }}</code>@if ($r['name'])<br><span class="muted" style="font-size: 10px;">{{ $r['name'] }}</span>@endif</td>
                <td>
                    @foreach ($r['middleware'] as $m)
                        <code>{{ $m }}</code>
                    @endforeach
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endforeach

<script>
(function () {
    var q = document.getElementById('q');
    var count = document.getElementById('count');
    var rows = document.querySelectorAll('.rrow');
    function filter() {
        var t = q.value.trim().toLowerCase();
        var shown = 0;
        rows.forEach(function (r) {
            var match = !t || r.dataset.txt.indexOf(t) !== -1;
            r.style.display = match ? '' : 'none';
            if (match) shown++;
        });
        count.textContent = t ? shown + ' matches' : '';
    }
    q.addEventListener('input', filter);
})();
</script>
@endsection
