@extends('admin.layouts.app')

@section('title', 'API Error Log')

@section('content')
<div class="kt-container kt-container--fluid kt-grid__item kt-grid__item--fluid">

{{-- ── Header ──────────────────────────────────────────────────────────── --}}
<div class="kt-portlet">
    <div class="kt-portlet__head">
        <div class="kt-portlet__head-label">
            <h3 class="kt-portlet__head-title">
                <i class="fas fa-bug" style="color:#e74c3c"></i>&nbsp; API Error Log
            </h3>
        </div>
        <div class="kt-portlet__head-toolbar">
            <a href="{{ route('admin.api-error-log.test') }}" class="btn btn-sm btn-warning mr-2">
                <i class="fas fa-flask"></i> Test Tool
            </a>
            <a href="{{ route('admin.api-error-log.export') }}{{ request()->getQueryString() ? '?'.request()->getQueryString() : '' }}" class="btn btn-sm btn-secondary">
                <i class="fas fa-download"></i> Export CSV
            </a>
        </div>
    </div>

    {{-- ── Stats strip ─────────────────────────────────────────────────── --}}
    <div class="kt-portlet__body pb-0 pt-3">
        <div class="row mb-3">
            <div class="col-6 col-md-3 mb-2">
                <div class="kt-widget24">
                    <div class="kt-widget24__details">
                        <a href="{{ route('admin.api-error-log.index') }}" class="kt-widget24__title">Total Logged</a>
                        <span class="kt-widget24__desc">all time</span>
                    </div>
                    <span class="kt-widget24__stats kt-font-brand">{{ number_format($counts['total']) }}</span>
                </div>
            </div>
            <div class="col-6 col-md-3 mb-2">
                <div class="kt-widget24">
                    <div class="kt-widget24__details">
                        <a href="{{ route('admin.api-error-log.index', ['status'=>'5xx']) }}" class="kt-widget24__title kt-font-danger">5xx Errors</a>
                        <span class="kt-widget24__desc">server errors</span>
                    </div>
                    <span class="kt-widget24__stats kt-font-danger">{{ number_format($counts['5xx']) }}</span>
                </div>
            </div>
            <div class="col-6 col-md-3 mb-2">
                <div class="kt-widget24">
                    <div class="kt-widget24__details">
                        <a href="{{ route('admin.api-error-log.index', ['status'=>'4xx']) }}" class="kt-widget24__title kt-font-warning">4xx Client</a>
                        <span class="kt-widget24__desc">auth / validation</span>
                    </div>
                    <span class="kt-widget24__stats kt-font-warning">{{ number_format($counts['4xx']) }}</span>
                </div>
            </div>
            <div class="col-6 col-md-3 mb-2">
                <div class="kt-widget24">
                    <div class="kt-widget24__details">
                        <a href="{{ route('admin.api-error-log.index', ['uninvestigated'=>1,'status'=>'5xx']) }}" class="kt-widget24__title kt-font-danger">Needs Review</a>
                        <span class="kt-widget24__desc">uninvestigated errors</span>
                    </div>
                    <span class="kt-widget24__stats kt-font-danger">{{ number_format($counts['uninvestigated']) }}</span>
                </div>
            </div>
        </div>

        {{-- ── Filters ─────────────────────────────────────────────────── --}}
        <form method="GET" action="{{ route('admin.api-error-log.index') }}" id="filterForm">
        <div class="row align-items-end mb-3">
            <div class="col-md-2 mb-2">
                <label class="col-form-label col-form-label-sm">Status</label>
                <select name="status" class="form-control form-control-sm" onchange="this.form.submit()">
                    <option value="">All</option>
                    <option value="5xx"   @selected(request('status')=='5xx')  >5xx Server Error</option>
                    <option value="4xx"   @selected(request('status')=='4xx')  >4xx Client Error</option>
                    <option value="error" @selected(request('status')=='error') >Has Exception</option>
                    <option value="2xx"   @selected(request('status')=='2xx')  >2xx Success</option>
                </select>
            </div>
            <div class="col-md-3 mb-2">
                <label class="col-form-label col-form-label-sm">URL contains</label>
                <input type="text" name="url" class="form-control form-control-sm" placeholder="e.g. /api/v1/policies" value="{{ request('url') }}">
            </div>
            <div class="col-md-2 mb-2">
                <label class="col-form-label col-form-label-sm">Error Class</label>
                <input type="text" name="error_class" class="form-control form-control-sm" placeholder="QueryException" value="{{ request('error_class') }}">
            </div>
            <div class="col-md-2 mb-2">
                <label class="col-form-label col-form-label-sm">Date From</label>
                <input type="date" name="date_from" class="form-control form-control-sm" value="{{ request('date_from') }}">
            </div>
            <div class="col-md-2 mb-2">
                <label class="col-form-label col-form-label-sm">Date To</label>
                <input type="date" name="date_to" class="form-control form-control-sm" value="{{ request('date_to') }}">
            </div>
            <div class="col-md-1 mb-2 d-flex align-items-end">
                <button type="submit" class="btn btn-sm btn-brand btn-block">Filter</button>
            </div>
        </div>

        {{-- ── JSON Path query (advanced) ──────────────────────────────── --}}
        <div class="row mb-3" id="jsonQueryRow">
            <div class="col-12 mb-1">
                <small class="text-muted">
                    <i class="fas fa-code"></i>
                    <strong>JSON Path Query</strong> — query any nested JSON field directly.
                    Examples: <code>error_data.class</code> · <code>request_data.body.product_id</code> · <code>response_data.status</code>
                </small>
            </div>
            <div class="col-md-4 mb-2">
                <input type="text" name="json_path" class="form-control form-control-sm font-monospace"
                       placeholder="JSON path e.g. error_data.message" value="{{ request('json_path') }}">
            </div>
            <div class="col-md-2 mb-2">
                <select name="json_op" class="form-control form-control-sm">
                    <option value="contains"   @selected(request('json_op','contains')=='contains')  >contains</option>
                    <option value="="          @selected(request('json_op')=='=')                    >equals</option>
                    <option value="!="         @selected(request('json_op')=='!=')                   >not equals</option>
                    <option value=">"          @selected(request('json_op')=='>')                    >&gt;</option>
                    <option value=">="         @selected(request('json_op')=='>=')                   >&gt;=</option>
                    <option value="<"          @selected(request('json_op')=='<')                    >&lt;</option>
                    <option value="starts_with" @selected(request('json_op')=='starts_with')         >starts with</option>
                </select>
            </div>
            <div class="col-md-3 mb-2">
                <input type="text" name="json_value" class="form-control form-control-sm"
                       placeholder="value" value="{{ request('json_value') }}">
            </div>
            <div class="col-md-2 mb-2">
                <div class="form-check mt-1">
                    <input class="form-check-input" type="checkbox" name="uninvestigated" value="1"
                           id="chkUninvestigated" {{ request('uninvestigated') ? 'checked' : '' }} onchange="this.form.submit()">
                    <label class="form-check-label small" for="chkUninvestigated">Uninvestigated only</label>
                </div>
            </div>
            <div class="col-md-1 mb-2 d-flex align-items-end">
                <a href="{{ route('admin.api-error-log.index') }}" class="btn btn-sm btn-secondary btn-block">Clear</a>
            </div>
        </div>
        </form>
    </div>
</div>

{{-- ── Log Table ────────────────────────────────────────────────────────── --}}
<div class="kt-portlet kt-portlet--mobile">
    <div class="kt-portlet__body kt-portlet__body--fit">
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0" style="font-size:13px">
                <thead class="thead-light">
                    <tr>
                        <th style="width:140px">Time</th>
                        <th style="width:60px">Method</th>
                        <th>URL / Route</th>
                        <th style="width:65px">Status</th>
                        <th style="width:70px">Time (ms)</th>
                        <th>Error / Exception</th>
                        <th style="width:110px">User</th>
                        <th style="width:100px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($logs as $log)
                    <tr class="{{ $log->is_investigated ? 'table-secondary' : ($log->status_code >= 500 ? 'table-danger' : ($log->status_code >= 400 ? 'table-warning' : '')) }}"
                        style="{{ $log->is_investigated ? 'opacity:0.6' : '' }}">

                        {{-- Time --}}
                        <td class="text-muted" title="{{ $log->created_at }}">
                            {{ $log->created_at?->diffForHumans() }}
                        </td>

                        {{-- Method --}}
                        <td>
                            <span class="badge badge-{{ $log->methodBadgeClass() }}">{{ $log->method }}</span>
                        </td>

                        {{-- URL --}}
                        <td>
                            <a href="{{ route('admin.api-error-log.show', $log->id) }}" class="text-dark font-weight-bold">
                                {{ Str::limit(parse_url($log->url, PHP_URL_PATH), 60) }}
                            </a>
                            @if($log->route)
                                <br><small class="text-muted">{{ $log->route }}</small>
                            @endif
                        </td>

                        {{-- Status --}}
                        <td>
                            <span class="badge badge-{{ $log->statusBadgeClass() }}">{{ $log->status_code }}</span>
                        </td>

                        {{-- Duration --}}
                        <td class="{{ $log->duration_ms > 2000 ? 'text-danger font-weight-bold' : ($log->duration_ms > 500 ? 'text-warning' : 'text-muted') }}">
                            {{ $log->duration_ms !== null ? number_format($log->duration_ms) . 'ms' : '—' }}
                        </td>

                        {{-- Error --}}
                        <td>
                            @if($log->error_class)
                                <code class="small text-danger">{{ Str::afterLast($log->error_class, '\\') }}</code>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>

                        {{-- User --}}
                        <td class="text-muted small">
                            {{ $log->user_name ?? ($log->user_id ? '#'.$log->user_id : 'Guest') }}
                        </td>

                        {{-- Actions --}}
                        <td>
                            <a href="{{ route('admin.api-error-log.show', $log->id) }}"
                               class="btn btn-xs btn-outline-primary mr-1" title="View details">
                                <i class="fas fa-search"></i>
                            </a>
                            <button class="btn btn-xs {{ $log->is_investigated ? 'btn-secondary' : 'btn-outline-success' }} btn-investigate"
                                    data-id="{{ $log->id }}"
                                    title="{{ $log->is_investigated ? 'Mark unresolved' : 'Mark investigated' }}">
                                <i class="fas {{ $log->is_investigated ? 'fa-undo' : 'fa-check' }}"></i>
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center py-4 text-muted">
                            <i class="fas fa-check-circle fa-2x mb-2 d-block text-success"></i>
                            No errors found matching your filters.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if ($logs->hasPages())
        <div class="kt-portlet__foot">
            <div class="d-flex justify-content-between align-items-center px-3 py-2">
                <small class="text-muted">Showing {{ $logs->firstItem() }}–{{ $logs->lastItem() }} of {{ number_format($logs->total()) }}</small>
                {{ $logs->links() }}
            </div>
        </div>
        @endif
    </div>
</div>

</div>
@endsection

@push('scripts')
<script>
// Mark investigated toggle
document.querySelectorAll('.btn-investigate').forEach(btn => {
    btn.addEventListener('click', function () {
        const id   = this.dataset.id;
        const note = prompt('Investigation note (optional):') ?? '';
        fetch(`/admin/api-error-log/${id}/investigate`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify({ note }),
        })
        .then(r => r.json())
        .then(d => {
            if (d.success) location.reload();
        });
    });
});
</script>
@endpush
