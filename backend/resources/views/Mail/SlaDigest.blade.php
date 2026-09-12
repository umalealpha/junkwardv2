<!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>
  body { font-family: 'Book Antiqua', Georgia, serif; color: #0D1B2A; margin: 0; padding: 0; background: #f4f5f7; }
  .wrap { max-width: 720px; margin: 0 auto; padding: 24px; }
  .card { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 20px; margin-bottom: 16px; }
  h1 { font-size: 20px; color: #0D1B2A; margin: 0 0 4px; }
  h2 { font-size: 14px; text-transform: uppercase; letter-spacing: .04em; color: #475569; margin: 0 0 10px; }
  .muted { color: #64748b; font-size: 12px; }
  table { width: 100%; border-collapse: collapse; font-size: 13px; }
  th, td { text-align: left; padding: 6px 8px; border-bottom: 1px solid #eef0f3; }
  th { color: #475569; font-weight: 600; }
  .kpis { display: flex; flex-wrap: wrap; gap: 10px; }
  .kpi { flex: 1; min-width: 120px; background: #f8fafc; border: 1px solid #eef0f3; border-radius: 6px; padding: 10px; }
  .kpi .n { font-size: 22px; font-weight: 700; color: #0D1B2A; }
  .kpi .l { font-size: 11px; color: #64748b; text-transform: uppercase; }
  .pill { display: inline-block; padding: 1px 8px; border-radius: 999px; font-size: 11px; }
  .breach { background: #fee2e2; color: #b91c1c; }
  .warn { background: #fef3c7; color: #b45309; }
  .accent { color: #F4A623; }
</style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <h1>Help Desk — SLA Daily Digest</h1>
    <div class="muted">Generated {{ optional($d['generated_at'] ?? null)->format('d M Y, H:i') }}</div>
  </div>

  <div class="card">
    <h2>SLA Compliance</h2>
    <div class="kpis">
      @foreach (['daily' => 'Last 24h', 'weekly' => 'Last 7 days', 'monthly' => 'Last 30 days'] as $k => $label)
        @php $c = $d['compliance'][$k] ?? ['compliance_pct' => null, 'total' => 0]; @endphp
        <div class="kpi">
          <div class="n">{{ $c['compliance_pct'] !== null ? $c['compliance_pct'] . '%' : '—' }}</div>
          <div class="l">{{ $label }} ({{ $c['total'] }} resolved)</div>
        </div>
      @endforeach
      <div class="kpi">
        <div class="n">{{ $d['open']['total'] ?? 0 }}</div>
        <div class="l">Open tickets</div>
      </div>
    </div>
  </div>

  <div class="card">
    <h2>Open Tickets by Priority</h2>
    <table>
      <tr><th>Priority</th><th>Open</th></tr>
      @forelse (($d['open']['by_priority'] ?? []) as $priority => $count)
        <tr><td style="text-transform:capitalize">{{ $priority }}</td><td>{{ $count }}</td></tr>
      @empty
        <tr><td colspan="2" class="muted">No open tickets.</td></tr>
      @endforelse
    </table>
  </div>

  <div class="card">
    <h2>Tickets Nearing Breach (next 24h) — {{ count($d['nearing_breach'] ?? []) }}</h2>
    <table>
      <tr><th>Ticket</th><th>Priority</th><th>Assignee</th><th>Resolution due</th></tr>
      @forelse (($d['nearing_breach'] ?? []) as $r)
        <tr>
          <td>{{ $r['ticket_ref'] }}</td>
          <td style="text-transform:capitalize">{{ $r['priority'] }}</td>
          <td>{{ $r['assignee_name'] ?: 'Unassigned' }}</td>
          <td>{{ $r['resolution_due_at'] ? \Illuminate\Support\Carbon::parse($r['resolution_due_at'])->format('d M, H:i') : '—' }}</td>
        </tr>
      @empty
        <tr><td colspan="4" class="muted">Nothing nearing breach.</td></tr>
      @endforelse
    </table>
  </div>

  <div class="card">
    <h2>Breached &amp; Unresolved — {{ count($d['breached_unresolved'] ?? []) }}</h2>
    <table>
      <tr><th>Ticket</th><th>Priority</th><th>Assignee</th><th>Breach</th></tr>
      @forelse (($d['breached_unresolved'] ?? []) as $r)
        <tr>
          <td>{{ $r['ticket_ref'] }}</td>
          <td style="text-transform:capitalize">{{ $r['priority'] }}</td>
          <td>{{ $r['assignee_name'] ?: 'Unassigned' }}</td>
          <td>
            @if ($r['response_breached']) <span class="pill breach">Response</span> @endif
            @if ($r['resolution_breached']) <span class="pill breach">Resolution</span> @endif
          </td>
        </tr>
      @empty
        <tr><td colspan="4" class="muted">No breached unresolved tickets.</td></tr>
      @endforelse
    </table>
  </div>

  <div class="card">
    <h2>Top Offenders</h2>
    <table>
      <tr><th>Assignee</th><th>Breaches</th></tr>
      @forelse (($d['top_offenders'] ?? []) as $r)
        <tr><td>{{ $r['assignee'] }}</td><td>{{ $r['breaches'] }}</td></tr>
      @empty
        <tr><td colspan="2" class="muted">No breaches recorded.</td></tr>
      @endforelse
    </table>
  </div>

  <div class="muted">Alpha Direct · Help Desk SLA · automated digest</div>
</div>
</body>
</html>
