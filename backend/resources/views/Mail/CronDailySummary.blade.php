<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
  body { font-family: Arial, sans-serif; font-size: 14px; color: #333; margin: 0; padding: 0; }
  .container { max-width: 900px; margin: 20px auto; padding: 20px; }
  h2 { color: #1a1a2e; margin-bottom: 4px; }
  .summary { display: flex; gap: 20px; margin: 16px 0; }
  .badge { padding: 8px 18px; border-radius: 6px; font-weight: bold; font-size: 15px; }
  .badge-total   { background: #e8f0fe; color: #1a73e8; }
  .badge-done    { background: #e6f4ea; color: #188038; }
  .badge-missing { background: #fce8e6; color: #c5221f; }
  table { width: 100%; border-collapse: collapse; margin-top: 16px; }
  th { background: #f1f3f4; text-align: left; padding: 10px 12px; font-size: 13px; color: #555; border-bottom: 2px solid #dadce0; }
  td { padding: 9px 12px; border-bottom: 1px solid #e8eaed; font-size: 13px; }
  tr:last-child td { border-bottom: none; }
  .status-done    { color: #188038; font-weight: bold; }
  .status-missing { color: #c5221f; font-weight: bold; }
  .footer { margin-top: 24px; font-size: 12px; color: #888; }
</style>
</head>
<body>
<div class="container">
  <h2>Daily Cron Report</h2>
  <p style="color:#666; margin:0;">{{ $date }} &nbsp;|&nbsp; Generated at {{ now()->format('H:i:s') }} (server time)</p>

  <div class="summary">
    <span class="badge badge-total">Total: {{ $total }}</span>
    <span class="badge badge-done">Completed: {{ $completed }}</span>
    @if($failed > 0)
    <span class="badge badge-missing">Incomplete / No End Time: {{ $failed }}</span>
    @endif
  </div>

  <table>
    <thead>
      <tr>
        <th>#</th>
        <th>Cron Name</th>
        <th>Started At</th>
        <th>Ended At</th>
        <th>Duration</th>
        <th>Status</th>
      </tr>
    </thead>
    <tbody>
      @foreach($rows as $i => $row)
      @php
        $start    = $row->start ? \Carbon\Carbon::parse($row->start) : null;
        $end      = $row->end   ? \Carbon\Carbon::parse($row->end)   : null;
        $duration = ($start && $end) ? gmdate('H:i:s', $end->diffInSeconds($start)) : '—';
        $done     = !empty($row->end);
      @endphp
      <tr>
        <td>{{ $i + 1 }}</td>
        <td>{{ $row->name }}</td>
        <td>{{ $start ? $start->format('H:i:s') : '—' }}</td>
        <td>{{ $end   ? $end->format('H:i:s')   : '—' }}</td>
        <td>{{ $duration }}</td>
        <td class="{{ $done ? 'status-done' : 'status-missing' }}">
          {{ $done ? 'Completed' : 'No End Time' }}
        </td>
      </tr>
      @endforeach
    </tbody>
  </table>

  <div class="footer">
    This is an automated daily cron status report from Alpha Direct Graphite.<br>
    Do not reply to this email.
  </div>
</div>
</body>
</html>
