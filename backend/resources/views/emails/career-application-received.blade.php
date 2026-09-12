<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>New Career Application</title>
</head>
<body style="font-family:Arial,sans-serif;color:#000066;background:#f7f7fb;margin:0;padding:24px">
    <table role="presentation" style="max-width:560px;margin:0 auto;background:#fff;border-radius:12px;padding:32px;box-shadow:0 8px 24px rgba(0,0,102,0.08)">
        <tr>
            <td>
                <h1 style="color:#000066;font-size:22px;margin:0 0 4px">New Career Application</h1>
                <p style="font-size:13px;color:#8a8aa3;margin:0 0 20px">
                    #{{ $lead->id }} &middot; {{ optional($lead->submitted_at)->format('d M Y H:i') }} &middot; {{ $lead->source ?: 'website' }}
                </p>

                <table role="presentation" style="width:100%;border-collapse:collapse;font-size:14px;color:#1b1b18">
                    <tr>
                        <td style="padding:6px 12px 6px 0;color:#4A4A6E;white-space:nowrap">Name</td>
                        <td style="padding:6px 0;font-weight:bold">{{ $lead->full_name }}</td>
                    </tr>
                    <tr>
                        <td style="padding:6px 12px 6px 0;color:#4A4A6E">Email</td>
                        <td style="padding:6px 0">{{ $lead->email }}</td>
                    </tr>
                    <tr>
                        <td style="padding:6px 12px 6px 0;color:#4A4A6E">Phone</td>
                        <td style="padding:6px 0">{{ $lead->phone ?: '—' }}</td>
                    </tr>
                    <tr>
                        <td style="padding:6px 12px 6px 0;color:#4A4A6E">Applying for</td>
                        <td style="padding:6px 0;font-weight:bold;color:#FE7F0C">{{ $lead->product ?: '—' }}</td>
                    </tr>
                </table>

                @if($lead->message)
                    <h2 style="color:#000066;font-size:15px;margin:20px 0 6px">Cover note</h2>
                    <p style="font-size:14px;line-height:1.6;color:#1b1b18;margin:0;white-space:pre-wrap">{{ $lead->message }}</p>
                @endif

                @if(!empty($lead->details))
                    <h2 style="color:#000066;font-size:15px;margin:24px 0 8px">Application details</h2>
                    <pre style="font-size:12px;line-height:1.6;color:#1b1b18;background:#f7f7fb;border-radius:8px;padding:14px;overflow-x:auto;white-space:pre-wrap;margin:0">{{ json_encode($lead->details, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                @endif

                <p style="font-size:12px;line-height:1.6;color:#8a8aa3;margin:24px 0 0">
                    CV and credential files are attached (large files are available on the Graphite Leads page instead).
                    Alpha Direct Insurance — automated notification from the website careers form.
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
