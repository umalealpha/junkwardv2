<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AML Weekly Screening Report</title>
</head>
<body style="margin:0;padding:0;background:#f4f6f9;font-family:Arial,Helvetica,sans-serif;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f9;padding:20px 0;">
        <tr>
            <td align="center">
                <table width="700" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.08);">

                    {{-- Header --}}
                    <tr>
                        <td style="background:#1a237e;padding:24px 32px;">
                            <h1 style="margin:0;color:#ffffff;font-size:22px;font-weight:600;">
                                AML Weekly Screening Report
                            </h1>
                            <p style="margin:6px 0 0;color:#b3b9ff;font-size:14px;">
                                {{ $date }} &mdash; Alpha Direct Insurance
                            </p>
                        </td>
                    </tr>

                    {{-- Summary Cards --}}
                    <tr>
                        <td style="padding:24px 32px 0;">
                            <table width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td width="25%" style="padding:8px;">
                                        <div style="background:#e8f5e9;border-radius:6px;padding:16px;text-align:center;">
                                            <div style="font-size:28px;font-weight:700;color:#2e7d32;">{{ number_format($total_screened) }}</div>
                                            <div style="font-size:12px;color:#555;margin-top:4px;">SCREENED</div>
                                        </div>
                                    </td>
                                    <td width="25%" style="padding:8px;">
                                        <div style="background:{{ $total_flagged > 0 ? '#fbe9e7' : '#e8f5e9' }};border-radius:6px;padding:16px;text-align:center;">
                                            <div style="font-size:28px;font-weight:700;color:{{ $total_flagged > 0 ? '#c62828' : '#2e7d32' }};">{{ $total_flagged }}</div>
                                            <div style="font-size:12px;color:#555;margin-top:4px;">FLAGGED</div>
                                        </div>
                                    </td>
                                    <td width="25%" style="padding:8px;">
                                        <div style="background:#fff3e0;border-radius:6px;padding:16px;text-align:center;">
                                            <div style="font-size:28px;font-weight:700;color:#e65100;">{{ $total_errors }}</div>
                                            <div style="font-size:12px;color:#555;margin-top:4px;">ERRORS</div>
                                        </div>
                                    </td>
                                    <td width="25%" style="padding:8px;">
                                        <div style="background:#e3f2fd;border-radius:6px;padding:16px;text-align:center;">
                                            <div style="font-size:28px;font-weight:700;color:#1565c0;">{{ $elapsed_seconds }}s</div>
                                            <div style="font-size:12px;color:#555;margin-top:4px;">DURATION</div>
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Data Sources --}}
                    <tr>
                        <td style="padding:16px 32px 0;">
                            <p style="margin:0;font-size:12px;color:#888;">
                                <strong>Data Sources:</strong>
                                {{ $health['entries_loaded'] ?? 'N/A' }} entries |
                                Last updated: {{ isset($health['last_updated']) ? \Carbon\Carbon::parse($health['last_updated'])->format('d M Y H:i') : 'N/A' }} UTC
                            </p>
                        </td>
                    </tr>

                    {{-- Flagged Customers Table --}}
                    @if(count($flagged_customers) > 0)
                    <tr>
                        <td style="padding:24px 32px 0;">
                            <h2 style="margin:0 0 12px;font-size:16px;color:#c62828;">
                                Flagged Customers ({{ count($flagged_customers) }})
                            </h2>
                            <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e0e0e0;border-radius:4px;overflow:hidden;font-size:13px;">
                                <tr style="background:#f5f5f5;">
                                    <th style="padding:10px 8px;text-align:left;border-bottom:1px solid #e0e0e0;color:#333;">ID</th>
                                    <th style="padding:10px 8px;text-align:left;border-bottom:1px solid #e0e0e0;color:#333;">Customer Name</th>
                                    <th style="padding:10px 8px;text-align:left;border-bottom:1px solid #e0e0e0;color:#333;">Score</th>
                                    <th style="padding:10px 8px;text-align:left;border-bottom:1px solid #e0e0e0;color:#333;">Risk</th>
                                    <th style="padding:10px 8px;text-align:left;border-bottom:1px solid #e0e0e0;color:#333;">Matched With</th>
                                    <th style="padding:10px 8px;text-align:left;border-bottom:1px solid #e0e0e0;color:#333;">Source</th>
                                    <th style="padding:10px 8px;text-align:center;border-bottom:1px solid #e0e0e0;color:#333;">Policies</th>
                                </tr>
                                @foreach($flagged_customers as $fc)
                                <tr style="background:{{ $fc['risk_level'] === 'CRITICAL' ? '#fff5f5' : '#ffffff' }};">
                                    <td style="padding:8px;border-bottom:1px solid #f0f0f0;">{{ $fc['customer_id'] }}</td>
                                    <td style="padding:8px;border-bottom:1px solid #f0f0f0;font-weight:600;">{{ $fc['name'] }}</td>
                                    <td style="padding:8px;border-bottom:1px solid #f0f0f0;">
                                        <span style="background:{{ $fc['score'] >= 0.9 ? '#c62828' : '#e65100' }};color:#fff;padding:2px 8px;border-radius:10px;font-size:12px;font-weight:600;">
                                            {{ $fc['score'] }}
                                        </span>
                                    </td>
                                    <td style="padding:8px;border-bottom:1px solid #f0f0f0;">
                                        <span style="color:{{ $fc['risk_level'] === 'CRITICAL' ? '#c62828' : '#e65100' }};font-weight:700;">
                                            {{ $fc['risk_level'] }}
                                        </span>
                                    </td>
                                    <td style="padding:8px;border-bottom:1px solid #f0f0f0;">{{ $fc['matched_name'] }}</td>
                                    <td style="padding:8px;border-bottom:1px solid #f0f0f0;font-size:11px;color:#666;">{{ $fc['datasets'] }}</td>
                                    <td style="padding:8px;border-bottom:1px solid #f0f0f0;text-align:center;">{{ $fc['policy_count'] }}</td>
                                </tr>
                                @endforeach
                            </table>
                        </td>
                    </tr>

                    {{-- Action Required --}}
                    <tr>
                        <td style="padding:20px 32px 0;">
                            <div style="background:#fff3e0;border-left:4px solid #e65100;padding:12px 16px;border-radius:0 4px 4px 0;">
                                <strong style="color:#e65100;">Action Required:</strong>
                                <span style="color:#555;">
                                    Please review flagged customers in the admin panel.
                                    @if(collect($flagged_customers)->where('risk_level', 'CRITICAL')->count() > 0)
                                        <strong>{{ collect($flagged_customers)->where('risk_level', 'CRITICAL')->count() }} CRITICAL match(es)</strong> have been auto-blocked.
                                    @endif
                                </span>
                            </div>
                        </td>
                    </tr>
                    @else
                    <tr>
                        <td style="padding:24px 32px;">
                            <div style="background:#e8f5e9;border-left:4px solid #2e7d32;padding:12px 16px;border-radius:0 4px 4px 0;">
                                <strong style="color:#2e7d32;">All Clear:</strong>
                                <span style="color:#555;">No customers matched against sanctions lists this week. No action required.</span>
                            </div>
                        </td>
                    </tr>
                    @endif

                    {{-- Footer --}}
                    <tr>
                        <td style="padding:24px 32px;border-top:1px solid #eee;margin-top:16px;">
                            <p style="margin:0;font-size:11px;color:#999;">
                                This is an automated AML screening report generated by the Alpha Direct self-hosted sanctions screening service.
                                Screening threshold: {{ $threshold }} | Sources: UN, OFAC, EU, UK HMT, World Bank, Interpol
                            </p>
                            <p style="margin:8px 0 0;font-size:11px;color:#bbb;">
                                Alpha Direct Insurance &mdash; Compliance & KYC Division
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
