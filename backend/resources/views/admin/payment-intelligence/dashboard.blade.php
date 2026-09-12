@extends('admin.layouts.app')
@section('title', 'Payment Intelligence')
@section('content')
<div class="kt-content kt-grid__item kt-grid__item--fluid kt-grid kt-grid--hor" id="kt_content">
    <div class="kt-container kt-container--fluid kt-grid__item kt-grid__item--fluid">

        <div class="row mb-4">
            <div class="col-12 d-flex justify-content-between align-items-center">
                <h3 class="mb-0"><i class="fas fa-brain text-primary"></i> Payment Intelligence</h3>
                <div>
                    <a href="{{ route('admin.payment-intelligence.export') }}" class="btn btn-sm btn-outline-success mr-2">
                        <i class="fas fa-download"></i> Export All CSV
                    </a>
                    <form action="{{ route('admin.payment-intelligence.trigger') }}" method="POST" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-primary" onclick="return confirm('Run reconciliation now? This may take a few minutes.')">
                            <i class="fas fa-play"></i> Run Now
                        </button>
                    </form>
                </div>
            </div>
        </div>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        {{-- Severity Cards --}}
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card border-danger">
                    <div class="card-body text-center py-3">
                        <h2 class="text-danger mb-0">{{ $bySeverity['critical'] ?? 0 }}</h2>
                        <small class="text-muted">CRITICAL</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-warning">
                    <div class="card-body text-center py-3">
                        <h2 class="text-warning mb-0">{{ $bySeverity['high'] ?? 0 }}</h2>
                        <small class="text-muted">HIGH</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card" style="border-color: #fbbf24;">
                    <div class="card-body text-center py-3">
                        <h2 style="color: #ca8a04;" class="mb-0">{{ $bySeverity['medium'] ?? 0 }}</h2>
                        <small class="text-muted">MEDIUM</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-secondary">
                    <div class="card-body text-center py-3">
                        <h2 class="text-secondary mb-0">{{ $bySeverity['low'] ?? 0 }}</h2>
                        <small class="text-muted">LOW</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            {{-- Stats --}}
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header"><h5 class="mb-0">Overview</h5></div>
                    <div class="card-body">
                        <table class="table table-sm mb-0">
                            <tr><td>Open Anomalies</td><td class="text-right font-weight-bold text-danger">{{ number_format($totalOpen) }}</td></tr>
                            <tr><td>Resolved</td><td class="text-right font-weight-bold text-success">{{ number_format($totalResolved) }}</td></tr>
                            @if($latestRun)
                            <tr><td>Last Run</td><td class="text-right">{{ \Carbon\Carbon::parse($latestRun->completed_at ?? $latestRun->started_at)->diffForHumans() }}</td></tr>
                            <tr><td>Policies Checked</td><td class="text-right">{{ number_format($latestRun->policies_checked) }}</td></tr>
                            <tr><td>Run Status</td><td class="text-right"><span class="badge badge-{{ $latestRun->status === 'completed' ? 'success' : ($latestRun->status === 'running' ? 'info' : 'danger') }}">{{ ucfirst($latestRun->status) }}</span></td></tr>
                            @else
                            <tr><td colspan="2" class="text-center text-muted">No runs yet. Click "Run Now" to start.</td></tr>
                            @endif
                        </table>
                    </div>
                </div>
            </div>

            {{-- By Type --}}
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header"><h5 class="mb-0">Anomalies by Type</h5></div>
                    <div class="card-body p-0">
                        <table class="table table-sm mb-0">
                            <thead><tr><th>Type</th><th class="text-right">Count</th><th></th></tr></thead>
                            <tbody>
                            @forelse($byType as $type => $count)
                                <tr>
                                    <td>{{ str_replace('_', ' ', ucwords($type, '_')) }}</td>
                                    <td class="text-right font-weight-bold">{{ number_format($count) }}</td>
                                    <td class="text-right">
                                        <a href="{{ route('admin.payment-intelligence.check', $type) }}" class="btn btn-sm btn-outline-primary py-0">View</a>
                                        <a href="{{ route('admin.payment-intelligence.export', ['anomaly_type' => $type]) }}" class="btn btn-sm btn-outline-success py-0"><i class="fas fa-download"></i></a>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-muted py-3">No anomalies found</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Recent Runs --}}
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header"><h5 class="mb-0">Recent Runs</h5></div>
                    <div class="card-body p-0">
                        <table class="table table-sm mb-0">
                            <thead><tr><th>#</th><th>Type</th><th>Anomalies</th><th>Status</th><th>When</th></tr></thead>
                            <tbody>
                            @forelse($recentRuns as $run)
                                <tr>
                                    <td>{{ $run->id }}</td>
                                    <td>{{ ucfirst($run->run_type) }}</td>
                                    <td class="font-weight-bold {{ $run->anomalies_found > 0 ? 'text-danger' : 'text-success' }}">{{ number_format($run->anomalies_found) }}</td>
                                    <td><span class="badge badge-{{ $run->status === 'completed' ? 'success' : ($run->status === 'running' ? 'info' : 'danger') }}">{{ ucfirst($run->status) }}</span></td>
                                    <td>{{ \Carbon\Carbon::parse($run->started_at)->format('d M H:i') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted py-3">No runs yet</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- Quick Access: 6 Checks --}}
        <div class="row">
            <div class="col-12"><h5 class="mb-3">Reconciliation Checks</h5></div>
            @php
            $checks = [
                ['type' => 'premium_mismatch', 'icon' => 'fas fa-exchange-alt', 'color' => 'primary', 'title' => 'Premium vs Debit Order', 'desc' => 'Premium increased but debit order still at old amount'],
                ['type' => 'partial_payment', 'icon' => 'fas fa-minus-circle', 'color' => 'warning', 'title' => 'Partial Payments', 'desc' => 'Payments received less than the premium due'],
                ['type' => 'unpaid_invoice', 'icon' => 'fas fa-file-invoice', 'color' => 'danger', 'title' => 'Unpaid Invoices', 'desc' => 'Scheduled payments that were never processed'],
                ['type' => 'balance_accumulating', 'icon' => 'fas fa-chart-line', 'color' => 'info', 'title' => 'Accumulating Balances', 'desc' => 'Total paid significantly less than total expected'],
                ['type' => 'payment_gap', 'icon' => 'fas fa-calendar-times', 'color' => 'secondary', 'title' => 'Payment Gaps', 'desc' => 'No payment received for 60+ days'],
                ['type' => 'cancelled_but_collecting', 'icon' => 'fas fa-exclamation-triangle', 'color' => 'danger', 'title' => 'Cancelled but Collecting', 'desc' => 'Cancelled policies with active debit orders'],
            ];
            @endphp
            @foreach($checks as $check)
            <div class="col-md-4 mb-3">
                <div class="card h-100">
                    <div class="card-body d-flex align-items-start">
                        <div class="mr-3">
                            <i class="{{ $check['icon'] }} text-{{ $check['color'] }}" style="font-size: 24px;"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="mb-1">
                                <a href="{{ route('admin.payment-intelligence.check', $check['type']) }}">{{ $check['title'] }}</a>
                                <span class="badge badge-{{ $check['color'] }} ml-1">{{ $byType[$check['type']] ?? 0 }}</span>
                            </h6>
                            <small class="text-muted">{{ $check['desc'] }}</small>
                        </div>
                        <a href="{{ route('admin.payment-intelligence.export', ['anomaly_type' => $check['type']]) }}" class="btn btn-sm btn-outline-success" title="Export CSV">
                            <i class="fas fa-download"></i>
                        </a>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</div>
@endsection
