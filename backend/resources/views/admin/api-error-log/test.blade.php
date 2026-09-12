@extends('admin.layouts.app')

@section('title', 'API Test Tool')

@section('content')
<div class="kt-container kt-container--fluid kt-grid__item kt-grid__item--fluid">

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><i class="fas fa-flask text-warning"></i> API Test Tool</h4>
    <a href="{{ route('admin.api-error-log.index') }}" class="btn btn-sm btn-secondary">
        <i class="fas fa-arrow-left"></i> Back to Log
    </a>
</div>

<div class="row">

    {{-- ── Request builder ─────────────────────────────────────────────── --}}
    <div class="col-md-7">
        <div class="kt-portlet">
            <div class="kt-portlet__head">
                <div class="kt-portlet__head-label">
                    <h3 class="kt-portlet__head-title">Build Request</h3>
                </div>
            </div>
            <div class="kt-portlet__body">

                {{-- URL bar --}}
                <div class="input-group mb-3">
                    <div class="input-group-prepend">
                        <select id="reqMethod" class="form-control" style="border-radius:4px 0 0 4px;width:100px">
                            <option>GET</option>
                            <option>POST</option>
                            <option>PUT</option>
                            <option>PATCH</option>
                            <option>DELETE</option>
                        </select>
                    </div>
                    <input type="text" id="reqUrl" class="form-control font-monospace"
                           placeholder="{{ config('app.url') }}/api/v1/..."
                           value="{{ config('app.url') }}/api/v1/">
                    <div class="input-group-append">
                        <button class="btn btn-brand" id="btnSend">
                            <i class="fas fa-paper-plane"></i> Send
                        </button>
                    </div>
                </div>

                {{-- Tabs: Headers / Body / Presets --}}
                <ul class="nav nav-tabs mb-3" id="reqTabs">
                    <li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#req-headers">Headers</a></li>
                    <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#req-body">Body (JSON)</a></li>
                    <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#req-presets">Quick Presets</a></li>
                </ul>
                <div class="tab-content">
                    <div id="req-headers" class="tab-pane active">
                        <div id="headerRows">
                            <div class="input-group input-group-sm mb-1 header-row">
                                <input type="text" class="form-control hdr-key"   placeholder="Header name"  value="Accept">
                                <input type="text" class="form-control hdr-val"   placeholder="Value"        value="application/json">
                                <div class="input-group-append"><button class="btn btn-outline-danger btn-remove-header">×</button></div>
                            </div>
                        </div>
                        <button class="btn btn-sm btn-outline-secondary mt-1" id="btnAddHeader">
                            <i class="fas fa-plus"></i> Add header
                        </button>
                    </div>
                    <div id="req-body" class="tab-pane">
                        <textarea id="reqBody" class="form-control font-monospace" rows="8"
                                  placeholder='{"key": "value"}'></textarea>
                        <small class="text-muted">JSON body — automatically sets Content-Type: application/json</small>
                    </div>
                    <div id="req-presets" class="tab-pane">
                        <p class="text-muted small mb-2">Click a preset to fill the request builder:</p>
                        <div class="list-group">
                            <button class="list-group-item list-group-item-action small" data-preset="policies-list">
                                GET /api/v1/policies — list all policies
                            </button>
                            <button class="list-group-item list-group-item-action small" data-preset="policy-stats">
                                GET /api/v1/dashboard/stats — dashboard stats
                            </button>
                            <button class="list-group-item list-group-item-action small" data-preset="wa-webhook">
                                POST /api/v1/whatsapp/webhook — WhatsApp webhook
                            </button>
                            <button class="list-group-item list-group-item-action small" data-preset="health">
                                GET /api/v1/health — health check
                            </button>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    {{-- ── Response panel ───────────────────────────────────────────────── --}}
    <div class="col-md-5">
        <div class="kt-portlet" id="responsePanel" style="display:none">
            <div class="kt-portlet__head">
                <div class="kt-portlet__head-label">
                    <h3 class="kt-portlet__head-title">Response</h3>
                </div>
                <div class="kt-portlet__head-toolbar">
                    <span id="respStatus" class="badge badge-secondary mr-2"></span>
                    <span id="respTime"   class="text-muted small"></span>
                </div>
            </div>
            <div class="kt-portlet__body">
                <pre id="respBody" class="bg-dark text-light p-3 rounded"
                     style="font-size:11px;max-height:400px;overflow:auto;white-space:pre-wrap"></pre>

                <div id="logLink" class="mt-2 alert alert-info py-2 small" style="display:none">
                    <i class="fas fa-database"></i>
                    This request was automatically logged.
                    <a href="{{ route('admin.api-error-log.index') }}?status=error" class="alert-link">View in error log →</a>
                </div>
            </div>
        </div>

        {{-- Spinner --}}
        <div id="loadingPanel" style="display:none;text-align:center;padding:40px">
            <div class="spinner-border text-brand"></div>
            <div class="mt-2 text-muted">Sending…</div>
        </div>
    </div>
</div>

{{-- ── Recent test history ──────────────────────────────────────────────── --}}
@if($history->isNotEmpty())
<div class="kt-portlet">
    <div class="kt-portlet__head">
        <div class="kt-portlet__head-label">
            <h3 class="kt-portlet__head-title">Recent Test Requests (from log)</h3>
        </div>
    </div>
    <div class="kt-portlet__body kt-portlet__body--fit">
        <table class="table table-sm table-hover mb-0" style="font-size:12px">
            <thead class="thead-light">
                <tr><th>Time</th><th>Method</th><th>URL</th><th>Status</th><th>ms</th><th></th></tr>
            </thead>
            <tbody>
            @foreach($history as $h)
            <tr>
                <td class="text-muted">{{ $h->created_at?->diffForHumans() }}</td>
                <td><span class="badge badge-secondary">{{ $h->method }}</span></td>
                <td><code style="font-size:11px">{{ Str::limit(parse_url($h->url, PHP_URL_PATH), 55) }}</code></td>
                <td><span class="badge badge-{{ $h->statusBadgeClass() }}">{{ $h->status_code }}</span></td>
                <td class="text-muted">{{ $h->duration_ms }}</td>
                <td><a href="{{ route('admin.api-error-log.show', $h->id) }}" class="btn btn-xs btn-outline-primary">View</a></td>
            </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

</div>
@endsection

@push('scripts')
<script>
const BASE = '{{ config('app.url') }}';
const CSRF = document.querySelector('meta[name=csrf-token]')?.content ?? '';

// ── Add / remove header rows ──────────────────────────────────────────────
document.getElementById('btnAddHeader').addEventListener('click', () => {
    const row = document.createElement('div');
    row.className = 'input-group input-group-sm mb-1 header-row';
    row.innerHTML = `<input type="text" class="form-control hdr-key" placeholder="Header name">
                     <input type="text" class="form-control hdr-val" placeholder="Value">
                     <div class="input-group-append"><button class="btn btn-outline-danger btn-remove-header">×</button></div>`;
    document.getElementById('headerRows').appendChild(row);
});
document.getElementById('headerRows').addEventListener('click', e => {
    if (e.target.classList.contains('btn-remove-header')) e.target.closest('.header-row').remove();
});

// ── Presets ────────────────────────────────────────────────────────────────
const presets = {
    'policies-list': { method: 'GET',  url: BASE + '/api/v1/policies', body: '' },
    'policy-stats':  { method: 'GET',  url: BASE + '/api/v1/dashboard/stats', body: '' },
    'wa-webhook':    { method: 'POST', url: BASE + '/api/v1/whatsapp/webhook', body: JSON.stringify({object:'whatsapp_business_account',entry:[{changes:[{value:{messages:[]}}]}]}, null, 2) },
    'health':        { method: 'GET',  url: BASE + '/api/v1/health', body: '' },
};
document.querySelectorAll('[data-preset]').forEach(btn => {
    btn.addEventListener('click', () => {
        const p = presets[btn.dataset.preset];
        if (!p) return;
        document.getElementById('reqMethod').value = p.method;
        document.getElementById('reqUrl').value    = p.url;
        document.getElementById('reqBody').value   = p.body;
        document.querySelector('[href="#req-body"]').click();
    });
});

// ── Send request ──────────────────────────────────────────────────────────
document.getElementById('btnSend').addEventListener('click', async () => {
    const method  = document.getElementById('reqMethod').value;
    const url     = document.getElementById('reqUrl').value.trim();
    const body    = document.getElementById('reqBody').value.trim();

    // Collect headers
    const headers = [];
    document.querySelectorAll('.header-row').forEach(row => {
        const k = row.querySelector('.hdr-key').value.trim();
        const v = row.querySelector('.hdr-val').value.trim();
        if (k) headers.push({ key: k, value: v });
    });

    document.getElementById('loadingPanel').style.display = 'block';
    document.getElementById('responsePanel').style.display = 'none';

    try {
        const res = await fetch('{{ route('admin.api-error-log.test.run') }}', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': CSRF, 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ method, url, headers, body }),
        });
        const d = await res.json();

        document.getElementById('loadingPanel').style.display = 'none';
        document.getElementById('responsePanel').style.display = 'block';

        const statusEl = document.getElementById('respStatus');
        statusEl.textContent = d.status ?? 'Error';
        statusEl.className = `badge badge-${d.status >= 500 ? 'danger' : d.status >= 400 ? 'warning' : 'success'}`;
        document.getElementById('respTime').textContent = (d.duration_ms ?? 0) + 'ms';

        // Pretty-print JSON if possible
        let bodyText = d.body ?? (d.error ?? '');
        try { bodyText = JSON.stringify(JSON.parse(bodyText), null, 2); } catch (_) {}
        document.getElementById('respBody').textContent = bodyText;

        // Show log link if error
        if (d.status >= 400) {
            document.getElementById('logLink').style.display = 'block';
        }
    } catch (err) {
        document.getElementById('loadingPanel').style.display = 'none';
        document.getElementById('responsePanel').style.display = 'block';
        document.getElementById('respBody').textContent = 'Request failed: ' + err.message;
    }
});
</script>
@endpush
