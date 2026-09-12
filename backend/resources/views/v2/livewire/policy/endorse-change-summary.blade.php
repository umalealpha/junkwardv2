<div>
    {{-- Read-only trigger button (styled like the neighbouring action buttons) --}}
    <div class="form-floating m-3">
        <a wire:click="load"
           data-bs-toggle="modal" data-bs-target="#endorse-change-summary-modal-{{ $actionId }}"
           class="btn"
           style="background-color:#0D1B2A; border-color:#0D1B2A; color:#fff;
                  height:35px; margin:0; font-size:12px; width:134px;
                  display:flex; align-items:center; justify-content:center;">
            Change Summary
        </a>
    </div>

    <div wire:ignore.self>
        <div class="modal fade @if($showModal) show @endif" tabindex="-1"
             id="endorse-change-summary-modal-{{ $actionId }}"
             @if($showModal) style="display:block;" @endif wire:ignore.self>
            <div class="modal-dialog modal-xl modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header" style="background-color:#0D1B2A; color:#fff;">
                        <h3 class="modal-title" style="color:#fff;">
                            Endorse / Cancel Change Summary
                            <small style="color:#F4A623; font-size:12px;">(read-only)</small>
                        </h3>
                        <div class="btn btn-icon btn-sm btn-active-light-primary ms-2"
                             data-bs-dismiss="modal" aria-label="Close"
                             style="color:#fff;">✕</div>
                    </div>

                    <div class="modal-body">
                        <div wire:loading wire:target="load" class="text-center p-5">
                            <span class="spinner-border text-primary" role="status"></span>
                            <div class="mt-3 fw-semibold text-gray-700">Reading stamped data…</div>
                        </div>

                        <div wire:loading.remove wire:target="load">
                            @if($error)
                                <div class="alert alert-warning">{{ $error }}</div>
                            @elseif($loaded)
                                @php
                                    $added   = collect($rows)->where('change_type','ADDED');
                                    $changed = collect($rows)->where('change_type','CHANGED');
                                    $deleted = collect($rows)->where('change_type','DELETED');
                                @endphp

                                <div class="d-flex gap-3 mb-4 flex-wrap">
                                    <span class="badge bg-success fs-7 p-2">Added: {{ $added->count() }}</span>
                                    <span class="badge bg-warning text-dark fs-7 p-2">Changed: {{ $changed->count() }}</span>
                                    <span class="badge bg-danger fs-7 p-2">Deleted: {{ $deleted->count() }}</span>
                                </div>

                                @if(count($rows) === 0)
                                    <div class="alert alert-info">
                                        No line-level changes were stamped on this action.
                                        @if(($math['type'] ?? '') === 'CANCEL')
                                            The refund below is computed at policy level.
                                        @endif
                                    </div>
                                @else
                                <div class="table-responsive">
                                    <table class="table table-row-bordered table-row-dashed align-middle gs-2 gy-2">
                                        <thead>
                                            <tr class="fw-bold fs-7 text-uppercase text-gray-600"
                                                style="border-bottom:2px solid #0D1B2A;">
                                                <th>ID</th>
                                                <th>Coverage Name</th>
                                                <th>Change</th>
                                                <th class="min-w-150px">What Changed (old → new)</th>
                                                <th class="text-end">Calculated Value</th>
                                                <th class="text-end">Δ (delta)</th>
                                                <th class="text-end">Pro-Rata</th>
                                            </tr>
                                        </thead>
                                        <tbody class="fs-7">
                                            @foreach($rows as $r)
                                                <tr>
                                                    <td class="text-muted">{{ $r['line_id'] ?: '—' }}</td>
                                                    <td>
                                                        <div class="fw-semibold">{{ $r['name'] }}</div>
                                                        @if($r['detail'])
                                                            <div class="text-muted fs-8">{{ $r['detail'] }}</div>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        @if($r['change_type']==='ADDED')
                                                            <span class="badge bg-success">ADDED</span>
                                                        @elseif($r['change_type']==='CHANGED')
                                                            <span class="badge bg-warning text-dark">CHANGED</span>
                                                        @else
                                                            <span class="badge bg-danger">DELETED</span>
                                                        @endif
                                                    </td>
                                                    <td>{{ $r['what_changed'] }}</td>
                                                    <td class="text-end">
                                                        {{ is_null($r['calc_value']) ? '—' : number_format($r['calc_value'],2) }}
                                                    </td>
                                                    <td class="text-end {{ $r['delta'] < 0 ? 'text-danger' : '' }}">
                                                        {{ number_format($r['delta'],2) }}
                                                    </td>
                                                    <td class="text-end fw-semibold {{ $r['pro_rata'] < 0 ? 'text-danger' : 'text-dark' }}">
                                                        {{ number_format($r['pro_rata'],2) }}
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                        <tfoot>
                                            <tr class="fw-bold" style="border-top:2px solid #0D1B2A;">
                                                <td colspan="5" class="text-end">Total</td>
                                                <td class="text-end">{{ number_format(collect($rows)->sum('delta'),2) }}</td>
                                                <td class="text-end">{{ number_format(collect($rows)->sum('pro_rata'),2) }}</td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                                @endif

                                {{-- ── Pro-rata math block ────────────────────────────── --}}
                                @if(!empty($math))
                                <div class="mt-5 p-4 rounded"
                                     style="background:#f6f8fb; border:1px solid #0D1B2A;">
                                    <h4 style="color:#0D1B2A;" class="mb-3">
                                        Pro-Rata Calculation
                                        <span class="badge {{ $math['type']==='CANCEL' ? 'bg-danger' : 'bg-primary' }}">{{ $math['type'] }}</span>
                                    </h4>

                                    <div style="font-family:'Book Antiqua', Georgia, serif; font-size:15px; line-height:2;">
                                        @if($math['type']==='ENDORSE')
                                            <div>
                                                <strong>Step 1 — Factor</strong> (period ratio)<br>
                                                factor = newDays ÷ prevDays
                                                = {{ $math['numerator'] }} ÷ {{ $math['denominator'] }}
                                                = <strong>{{ number_format($math['factor'],6) }}</strong>
                                            </div>
                                            <div class="text-muted fs-7 mb-3">
                                                new period: {{ $math['new_from'] }} → {{ $math['new_to'] }}
                                                &nbsp;|&nbsp; baseline ({{ $math['prev_label'] }}):
                                                {{ $math['prev_from'] ?? '—' }} → {{ $math['prev_to'] ?? '—' }}
                                            </div>
                                            <div>
                                                <strong>Step 2 — Sum of line deltas</strong><br>
                                                Σ(Δ) = <strong>{{ number_format($math['basis_value'],2) }}</strong>
                                                @if(abs($math['specialist_delta']) > 0.01)
                                                    <span class="text-muted fs-7">(incl. specialist annual delta {{ number_format($math['specialist_delta'],2) }})</span>
                                                @endif
                                            </div>
                                            <div>
                                                <strong>Step 3 — Pro-rata</strong><br>
                                                pro-rata = Σ(Δ) × factor
                                                = {{ number_format($math['basis_value'],2) }} × {{ number_format($math['factor'],6) }}
                                                = <strong>{{ number_format($math['recomputed'],2) }}</strong>
                                            </div>
                                        @else
                                            <div>
                                                <strong>Step 1 — Factor</strong> (unexpired portion)<br>
                                                factor = unexpiredDays ÷ totalDays
                                                = {{ $math['numerator'] }} ÷ {{ $math['denominator'] }}
                                                = <strong>{{ number_format($math['factor'],6) }}</strong>
                                            </div>
                                            <div class="text-muted fs-7 mb-3">
                                                cancel from: {{ $math['cancel_from'] }}
                                                &nbsp;|&nbsp; period: {{ $math['period_from'] ?? '—' }} → {{ $math['period_to'] ?? '—' }}
                                                &nbsp;|&nbsp; freq: {{ $math['freq'] }}
                                            </div>
                                            <div>
                                                <strong>Step 2 — Basis</strong><br>
                                                {{ $math['basis_label'] }} = <strong>{{ number_format($math['basis_value'],2) }}</strong>
                                            </div>
                                            <div>
                                                <strong>Step 3 — Refund</strong> (always negative)<br>
                                                refund = −({{ $math['basis_label'] }} × factor)
                                                = −({{ number_format($math['basis_value'],2) }} × {{ number_format($math['factor'],6) }})
                                                = <strong class="text-danger">{{ number_format($math['recomputed'],2) }}</strong>
                                            </div>
                                        @endif

                                        <hr>
                                        <div class="d-flex align-items-center flex-wrap gap-3">
                                            <div>
                                                <span class="text-muted">Stored on action (policy_actions.premium):</span>
                                                <strong>{{ number_format($math['stored'],2) }}</strong>
                                            </div>
                                            @if($math['reconciles'])
                                                <span class="badge bg-success p-2">✓ Reconciles with computed pro-rata</span>
                                            @else
                                                <span class="badge bg-danger p-2">
                                                    ✗ Mismatch — computed {{ number_format($math['recomputed'],2) }}
                                                    vs stored {{ number_format($math['stored'],2) }} — investigate
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                @endif

                                <div class="text-muted fs-8 mt-3">
                                    This view only reads data already stamped by the Rate engine. It changes nothing.
                                    Per-row Δ is the raw annual delta (current − baseline); the factor is applied to the sum.
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
