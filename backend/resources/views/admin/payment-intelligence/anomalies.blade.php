@extends('admin.layouts.app')
@section('title', 'Reconciliation Anomalies')
@section('content')
<div class="kt-content kt-grid__item kt-grid__item--fluid kt-grid kt-grid--hor" id="kt_content">
    <div class="kt-container kt-container--fluid kt-grid__item kt-grid__item--fluid">

        <div class="row mb-3">
            <div class="col-12 d-flex justify-content-between align-items-center">
                <h4 class="mb-0"><i class="fas fa-exclamation-circle text-danger"></i> All Anomalies</h4>
                <div>
                    <a href="{{ route('admin.payment-intelligence.dashboard') }}" class="btn btn-sm btn-outline-primary mr-2"><i class="fas fa-arrow-left"></i> Dashboard</a>
                    <a href="{{ route('admin.payment-intelligence.export', request()->all()) }}" class="btn btn-sm btn-outline-success"><i class="fas fa-download"></i> Export CSV</a>
                </div>
            </div>
        </div>

        {{-- Filters --}}
        <div class="row mb-3">
            <div class="col-md-3">
                <select id="filter_type" class="form-control form-control-sm">
                    <option value="">All Types</option>
                    @foreach($types as $t)
                        <option value="{{ $t }}">{{ str_replace('_', ' ', ucwords($t, '_')) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select id="filter_severity" class="form-control form-control-sm">
                    <option value="">All Severity</option>
                    <option value="critical">Critical</option>
                    <option value="high">High</option>
                    <option value="medium">Medium</option>
                    <option value="low">Low</option>
                </select>
            </div>
            <div class="col-md-2">
                <select id="filter_status" class="form-control form-control-sm">
                    <option value="">All Status</option>
                    <option value="open" selected>Open</option>
                    <option value="acknowledged">Acknowledged</option>
                    <option value="resolved">Resolved</option>
                    <option value="false_positive">False Positive</option>
                </select>
            </div>
        </div>

        <div class="card">
            <div class="card-body p-0">
                <table class="table table-hover table-sm mb-0" id="anomaliesTable" style="width:100%">
                    <thead class="thead-light">
                        <tr>
                            <th>ID</th>
                            <th>Policy #</th>
                            <th>Customer</th>
                            <th>Type</th>
                            <th>Severity</th>
                            <th>Description</th>
                            <th>Expected</th>
                            <th>Actual</th>
                            <th>Diff</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
$(function() {
    var table = $('#anomaliesTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ route("admin.payment-intelligence.anomalies.data") }}',
            data: function(d) {
                d.anomaly_type = $('#filter_type').val();
                d.severity = $('#filter_severity').val();
                d.status_filter = $('#filter_status').val();
            }
        },
        order: [[0, 'desc']],
        pageLength: 25,
        columns: [
            { data: 'id', width: '50px' },
            { data: 'policy_number', render: function(data, type, row) {
                return '<a href="/admin/policy/' + row.policy_id + '/details" class="text-primary">' + (data || '-') + '</a>';
            }},
            { data: 'customer_name', render: function(d) { return d || '-'; } },
            { data: 'anomaly_type', render: function(d) {
                var colors = { premium_mismatch:'primary', partial_payment:'warning', unpaid_invoice:'danger', balance_accumulating:'info', payment_gap:'secondary', cancelled_but_collecting:'danger' };
                return '<span class="badge badge-' + (colors[d]||'secondary') + '">' + d.replace(/_/g,' ') + '</span>';
            }},
            { data: 'severity', render: function(d) {
                var colors = { critical:'danger', high:'warning', medium:'info', low:'secondary' };
                return '<span class="badge badge-' + (colors[d]||'secondary') + '">' + d.toUpperCase() + '</span>';
            }},
            { data: 'description', render: function(d) {
                return '<span title="' + (d||'').replace(/"/g,'&quot;') + '">' + (d||'').substring(0,60) + (d && d.length>60 ? '...' : '') + '</span>';
            }},
            { data: 'expected_amount', render: function(d) { return d ? 'P' + parseFloat(d).toLocaleString(undefined,{minimumFractionDigits:2}) : '-'; } },
            { data: 'actual_amount', render: function(d) { return d ? 'P' + parseFloat(d).toLocaleString(undefined,{minimumFractionDigits:2}) : '-'; } },
            { data: 'difference', render: function(d) { return d ? '<b class="text-danger">P' + parseFloat(d).toLocaleString(undefined,{minimumFractionDigits:2}) + '</b>' : '-'; } },
            { data: 'status', render: function(d) {
                var colors = { open:'danger', acknowledged:'info', resolved:'success', false_positive:'secondary' };
                return '<span class="badge badge-' + (colors[d]||'secondary') + '">' + d.replace(/_/g,' ') + '</span>';
            }},
            { data: 'created_at', render: function(d) { return d ? new Date(d).toLocaleDateString() : '-'; } },
            { data: null, orderable: false, render: function(data, type, row) {
                if (row.status === 'open') {
                    return '<form method="POST" action="/admin/payment-intelligence/anomalies/' + row.id + '/update-status" class="d-inline">' +
                        '@csrf' +
                        '<input type="hidden" name="action" value="acknowledge">' +
                        '<button type="submit" class="btn btn-xs btn-outline-info mr-1" title="Acknowledge">Ack</button>' +
                    '</form>' +
                    '<form method="POST" action="/admin/payment-intelligence/anomalies/' + row.id + '/update-status" class="d-inline">' +
                        '@csrf' +
                        '<input type="hidden" name="action" value="resolve">' +
                        '<input type="hidden" name="resolution_notes" value="Resolved from admin panel">' +
                        '<button type="submit" class="btn btn-xs btn-outline-success mr-1" title="Resolve">Resolve</button>' +
                    '</form>' +
                    '<form method="POST" action="/admin/payment-intelligence/anomalies/' + row.id + '/update-status" class="d-inline">' +
                        '@csrf' +
                        '<input type="hidden" name="action" value="false_positive">' +
                        '<button type="submit" class="btn btn-xs btn-outline-secondary" title="False Positive">FP</button>' +
                    '</form>';
                }
                return '<span class="text-muted">-</span>';
            }}
        ]
    });

    // Filter events
    $('#filter_type, #filter_severity, #filter_status').change(function() {
        table.draw();
    });
});
</script>
@endsection
