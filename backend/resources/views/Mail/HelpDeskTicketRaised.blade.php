<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, Helvetica, sans-serif; color: #1f2937; line-height: 1.5; }
        .wrap { max-width: 640px; margin: 0 auto; padding: 24px; }
        .card { border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden; }
        .head { background: #0B1272; color: #fff; padding: 16px 20px; }
        .head h2 { margin: 0; font-size: 18px; }
        .body { padding: 20px; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        td { padding: 6px 0; vertical-align: top; }
        td.label { color: #6b7280; width: 140px; }
        .desc { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; padding: 12px; margin-top: 8px; white-space: pre-wrap; font-size: 14px; }
        .muted { color: #9ca3af; font-size: 12px; margin-top: 16px; }
        .badge { display: inline-block; background: #dbeafe; color: #1d4ed8; border-radius: 9999px; padding: 2px 10px; font-size: 12px; font-weight: 600; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <div class="head">
            <h2>🎧 New Help Desk Ticket — {{ $ticket->ticket_ref }}</h2>
        </div>
        <div class="body">
            <p>An issue has been raised in Graphite V2 and needs a developer's attention.</p>
            <table>
                <tr><td class="label">Reference</td><td><strong>{{ $ticket->ticket_ref }}</strong></td></tr>
                <tr><td class="label">Title</td><td><strong>{{ $ticket->title }}</strong></td></tr>
                <tr><td class="label">Priority</td><td>{{ ucfirst($ticket->priority) }}</td></tr>
                <tr><td class="label">Type</td><td>{{ ucfirst($ticket->type) }}</td></tr>
                <tr><td class="label">Raised by</td><td>{{ $ticket->reporter_name }} ({{ $ticket->reporter_email }})</td></tr>
                <tr><td class="label">Status</td><td><span class="badge">{{ ucfirst(str_replace('_', ' ', $ticket->status)) }}</span></td></tr>
                <tr><td class="label">Assigned to</td><td>{{ $ticket->assignee_name ?: 'Unassigned' }}</td></tr>
                <tr><td class="label">Attachments</td><td>{{ count($ticket->attachments ?? []) }} file(s)</td></tr>
                <tr><td class="label">Raised at</td><td>{{ optional($ticket->created_at)->format('d M Y H:i') }}</td></tr>
            </table>

            <p style="margin-bottom: 4px; color:#6b7280; font-size:13px;">Description</p>
            <div class="desc">{{ $ticket->description }}</div>

            <p class="muted">
                Open the ticket in Graphite V2 → Help Desk (reference {{ $ticket->ticket_ref }}) to assign,
                update status, or download attachments. This is an automated notification.
            </p>
        </div>
    </div>
</div>
</body>
</html>
