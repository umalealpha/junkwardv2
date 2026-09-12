@extends('admin.layouts.app')

@section('title', 'Error Detail #' . $log->id)

@section('content')
<div class="kt-container kt-container--fluid kt-grid__item kt-grid__item--fluid">

{{-- ── Top nav ──────────────────────────────────────────────────────────── --}}
<div class="d-flex justify-content-between align-items-center mb-3">
    <a href="{{ route('admin.api-error-log.index') }}" class="btn btn-sm btn-secondary">
        <i class="fas fa-arrow-left"></i> Back to Log
    </a>
    <div>
        @if($prev)
        <a href="{{ route('admin.api-error-log.show', $prev) }}" class="btn btn-sm btn-outline-secondary mr-1">
            <i class="fas fa-chevron-left"></i> Prev
        </a>
        @endif
        @if($next)
        <a href="{{ route('admin.api-error-log.show', $next) }}" class="btn btn-sm btn-outline-secondary">
            Next <i class="fas fa-chevron-right"></i>
        </a>
        @endif
    </div>
</div>

{{-- ── Summary card ─────────────────────────────────────────────────────── --}}
<div class="kt-portlet kt-portlet--bordered" style="border-left: 4px solid {{ $log->status_code >= 500 ? '#e74c3c' : ($log->status_code >= 400 ? '#f39c12' : '#27ae60') }}">
    <div class="kt-portlet__body py-3">
        <div class="row align-items-center">
            <div class="col-auto">
                <span class="badge badge-{{ $log->statusBadgeClass() }}" style="font-size:1.1em;padding:6px 12px">
                    {{ $log->status_code }}
                </span>
            </div>
            <div class="col-auto">
                <span class="badge badge-{{ $log->methodBadgeClass() }}">{{ $log->method }}</span>
            </div>
            <div class="col">
                <code class="text-dark" style="font-size:14px;word-break:break-all">{{ $log->url }}</code>
                @if($log->route)
                    <span class="text-muted small ml-2">{{ $log->route }}</span>
                @endif
            </div>
            <div class="col-auto text-right">
                <div class="text-muted small">{{ $log->created_at?->format('d M Y H:i:s') }}</div>
                <div class="text-muted small">{{ $log->duration_ms }}ms
                    @if($log->user_name) · {{ $log->user_name }} @endif
                </div>
                <div class="text-muted small">Trace: <code>{{ substr($log->trace_id, 0, 8) }}…</code></div>
            </div>
        </div>

        @if($log->error_class)
        <div class="mt-2 alert alert-danger py-2 mb-0">
            <strong><i class="fas fa-exclamation-triangle"></i>
            {{ class_basename($log->error_class) }}</strong>
            @if(!empty($log->error_data['message']))
                — {{ Str::limit($log->error_data['message'], 200) }}
            @endif
            @if(!empty($log->error_data['file']))
                <br><small class="text-muted">{{ $log->error_data['file'] }}:{{ $log->error_data['line'] ?? '' }}</small>
            @endif
        </div>
        @endif
    </div>
</div>

{{-- ── Investigation panel ─────────────────────────────────────────────── --}}
<div class="kt-portlet kt-portlet--bordered mb-3">
    <div class="kt-portlet__head">
        <div class="kt-portlet__head-label">
            <h3 class="kt-portlet__head-title">
                <i class="fas {{ $log->is_investigated ? 'fa-check-circle text-success' : 'fa-circle text-warning' }}"></i>
                Investigation Status
            </h3>
        </div>
    </div>
    <div class="kt-portlet__body py-3">
        @if($log->is_investigated)
            <span class="badge badge-success">Investigated</span>
            @if($log->investigated_at)
                <span class="text-muted small ml-2">{{ $log->investigated_at->format('d M Y H:i') }}</span>
            @endif
            @if($log->investigation_note)
                <p class="mt-2 mb-1"><em>"{{ $log->investigation_note }}"</em></p>
            @endif
        @else
            <span class="badge badge-warning">Needs Review</span>
        @endif
        <button class="btn btn-sm {{ $log->is_investigated ? 'btn-outline-secondary' : 'btn-success' }} ml-3"
                id="btnInvestigate" data-id="{{ $log->id }}">
            <i class="fas {{ $log->is_investigated ? 'fa-undo' : 'fa-check' }}"></i>
            {{ $log->is_investigated ? 'Mark Unresolved' : 'Mark as Investigated' }}
        </button>
    </div>
</div>

{{-- ── Tabs: Error · Request · Response · Trace · Related ──────────────── --}}
<ul class="nav nav-tabs mb-0" id="detailTabs">
    @if($log->error_data)
    <li class="nav-item">
        <a class="nav-link active" data-toggle="tab" href="#tab-error">
            <i class="fas fa-bug text-danger"></i> Exception
        </a>
    </li>
    @endif
    <li class="nav-item">
        <a class="nav-link {{ !$log->error_data ? 'active' : '' }}" data-toggle="tab" href="#tab-request">
            <i class="fas fa-arrow-right text-primary"></i> Request
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-toggle="tab" href="#tab-response">
            <i class="fas fa-arrow-left text-success"></i> Response
        </a>
    </li>
    @if(!empty($log->error_data['trace']))
    <li class="nav-item">
        <a class="nav-link" data-toggle="tab" href="#tab-trace">
            <i class="fas fa-list-ol"></i> Stack Trace
        </a>
    </li>
    @endif
    @if($related->isNotEmpty())
    <li class="nav-item">
        <a class="nav-link" data-toggle="tab" href="#tab-related">
            <i class="fas fa-link"></i> Related ({{ $related->count() }})
        </a>
    </li>
    @endif
</ul>

<div class="tab-content kt-portlet">
    <div class="kt-portlet__body">

    {{-- Exception tab --}}
    @if($log->error_data)
    <div id="tab-error" class="tab-pane active">
        <div class="row">
            <div class="col-md-6">
                <table class="table table-sm table-bordered">
                    <tr><th>Class</th><td><code>{{ $log->error_data['class'] ?? '—' }}</code></td></tr>
                    <tr><th>Message</th><td>{{ $log->error_data['message'] ?? '—' }}</td></tr>
                    <tr><th>Code</th><td>{{ $log->error_data['code'] ?? '—' }}</td></tr>
                    <tr><th>File</th><td><code>{{ $log->error_data['file'] ?? '—' }}</code></td></tr>
                    <tr><th>Line</th><td>{{ $log->error_data['line'] ?? '—' }}</td></tr>
                    @if(!empty($log->error_data['previous']))
                    <tr><th>Previous</th>
                        <td><code>{{ $log->error_data['previous']['class'] ?? '' }}</code><br>
                            {{ $log->error_data['previous']['message'] ?? '' }}</td>
                    </tr>
                    @endif
                </table>
            </div>
            <div class="col-md-6">
                <pre class="bg-dark text-success p-3 rounded" style="font-size:11px;max-height:300px;overflow:auto">{{ json_encode($log->error_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
            </div>
        </div>
    </div>
    @endif

    {{-- Request tab --}}
    <div id="tab-request" class="tab-pane {{ !$log->error_data ? 'active' : '' }}">
        <div class="row">
            <div class="col-md-6">
                <h6 class="mb-2">Query Params</h6>
                <pre class="bg-light p-2 rounded small" style="max-height:200px;overflow:auto">{{ json_encode($log->request_data['query'] ?? [], JSON_PRETTY_PRINT) }}</pre>

                <h6 class="mb-2 mt-3">Request Body</h6>
                <pre class="bg-light p-2 rounded small" style="max-height:250px;overflow:auto">{{ json_encode($log->request_data['body'] ?? [], JSON_PRETTY_PRINT) }}</pre>
            </div>
            <div class="col-md-6">
                <h6 class="mb-2">Headers</h6>
                <pre class="bg-light p-2 rounded small" style="max-height:200px;overflow:auto">{{ json_encode($log->request_data['headers'] ?? [], JSON_PRETTY_PRINT) }}</pre>

                <div class="mt-3">
                    <h6 class="mb-2">Replay as CURL</h6>
                    <textarea id="curlCmd" class="form-control font-monospace small" rows="4" readonly>{{ $curlCommand ?? '' }}</textarea>
                    <button class="btn btn-sm btn-outline-secondary mt-1" onclick="document.getElementById('curlCmd').select(); document.execCommand('copy'); this.textContent='Copied!'">
                        <i class="fas fa-copy"></i> Copy CURL
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Response tab --}}
    <div id="tab-response" class="tab-pane">
        <div class="row">
            <div class="col-md-4">
                <table class="table table-sm">
                    <tr><th>Status</th><td><span class="badge badge-{{ $log->statusBadgeClass() }}">{{ $log->response_data['status'] ?? $log->status_code }}</span></td></tr>
                    <tr><th>Content-Type</th><td>{{ $log->response_data['content_type'] ?? '—' }}</td></tr>
                    <tr><th>Size</th><td>{{ isset($log->response_data['size_bytes']) ? number_format($log->response_data['size_bytes']) . ' bytes' : '—' }}</td></tr>
                </table>
            </div>
            <div class="col-md-8">
                <h6 class="mb-2">Response Body</h6>
                @php
                    $body    = $log->response_data['body'] ?? '';
                    $decoded = json_decode($body, true);
                @endphp
                <pre class="bg-dark text-light p-3 rounded" style="font-size:11px;max-height:350px;overflow:auto">{{ $decoded ? json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : $body }}</pre>
            </div>
        </div>
    </div>

    {{-- Stack Trace tab --}}
    @if(!empty($log->error_data['trace']))
    <div id="tab-trace" class="tab-pane">
        <div class="table-responsive">
            <table class="table table-sm table-bordered" style="font-size:12px">
                <thead class="thead-light">
                    <tr><th>#</th><th>File</th><th>Line</th><th>Function</th></tr>
                </thead>
                <tbody>
                @foreach($log->error_data['trace'] as $i => $frame)
                    <tr class="{{ str_starts_with($frame['file'] ?? '', 'app/') ? 'table-warning' : '' }}">
                        <td>{{ $i }}</td>
                        <td><code style="font-size:11px">{{ $frame['file'] ?? '' }}</code></td>
                        <td>{{ $frame['line'] ?? '' }}</td>
                        <td><code style="font-size:11px">{{ $frame['fn'] ?? '' }}</code></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- Related logs tab --}}
    @if($related->isNotEmpty())
    <div id="tab-related" class="tab-pane">
        <p class="text-muted small mb-2">All requests sharing trace ID <code>{{ $log->trace_id }}</code></p>
        <table class="table table-sm">
            <thead class="thead-light"><tr><th>ID</th><th>Method</th><th>URL</th><th>Status</th><th>Time</th></tr></thead>
            <tbody>
            @foreach($related as $r)
            <tr>
                <td><a href="{{ route('admin.api-error-log.show', $r->id) }}">#{{ $r->id }}</a></td>
                <td><span class="badge badge-secondary">{{ $r->method }}</span></td>
                <td><code>{{ parse_url($r->url, PHP_URL_PATH) }}</code></td>
                <td><span class="badge badge-{{ $r->statusBadgeClass() }}">{{ $r->status_code }}</span></td>
                <td class="text-muted small">{{ $r->created_at }}</td>
            </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @endif

    </div>{{-- /portlet body --}}
</div>{{-- /tab-content --}}

</div>
@endsection

@push('scripts')
<script>
// Build CURL command from request data
(function() {
    const rd = @json($log->request_data ?? []);
    const method = '{{ $log->method }}';
    const url    = '{{ addslashes($log->url) }}';
    let cmd = `curl -X ${method} "${url}"`;
    // Headers
    const skip = ['cookie','accept-encoding'];
    for (const [k,v] of Object.entries(rd.headers ?? {})) {
        if (!skip.includes(k.toLowerCase())) {
            cmd += `\\\n  -H "${k}: ${Array.isArray(v) ? v[0] : v}"`;
        }
    }
    // Body
    if (rd.body && Object.keys(rd.body).length) {
        cmd += `\\\n  -d '${JSON.stringify(rd.body)}'`;
    }
    document.getElementById('curlCmd').value = cmd;
})();

// Investigate toggle
document.getElementById('btnInvestigate')?.addEventListener('click', function() {
    const id   = this.dataset.id;
    const note = prompt('Note (optional):') ?? '';
    fetch(`/admin/api-error-log/${id}/investigate`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
            'Content-Type': 'application/json', 'Accept': 'application/json',
        },
        body: JSON.stringify({ note }),
    }).then(() => location.reload());
});
</script>
@endpush
