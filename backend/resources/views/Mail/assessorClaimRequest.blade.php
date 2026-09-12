@php
    // Email-safe inline styles (kept as PHP vars to avoid repetition).
    $labelCell = 'padding:10px 14px;background-color:#f9fafb;border:1px solid #e5e7eb;font-weight:bold;width:38%;color:#374151;vertical-align:top;';
    $valueCell = 'padding:10px 14px;border:1px solid #e5e7eb;color:#111827;vertical-align:top;';
@endphp
<div style="margin:0;padding:0;background-color:#f4f5f7;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f5f7;padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:8px;overflow:hidden;font-family:Arial,Helvetica,sans-serif;box-shadow:0 1px 3px rgba(0,0,0,0.08);">
                    {{-- Header: the logo is a multicolour wordmark made for a LIGHT
                         background, so it sits on white. Embed inline (CID) so it
                         renders without an external fetch — asset() URLs point at
                         APP_URL (e.g. localhost) and break in mail. Falls back to a
                         navy text title when the file is missing. --}}
                    @php $logoPath = public_path('images/logo.png'); @endphp
                    <tr>
                        <td style="background-color:#ffffff;padding:24px 28px 18px;text-align:center;">
                            @if(isset($message) && is_file($logoPath))
                                <img src="{{ $message->embed($logoPath) }}" alt="Alpha Direct" style="height:46px;display:inline-block;" />
                            @else
                                <div style="color:#0D1B2A;font-size:18px;font-weight:bold;letter-spacing:0.3px;">Alpha Direct Insurance</div>
                            @endif
                        </td>
                    </tr>
                    {{-- Brand accent strip --}}
                    <tr>
                        <td style="height:4px;background-color:#0D1B2A;font-size:0;line-height:0;">&nbsp;</td>
                    </tr>

                    {{-- Body --}}
                    <tr>
                        <td style="padding:28px;color:#1f2937;font-size:14px;line-height:1.6;">
                            <p style="margin:0 0 16px;">Good day,</p>
                            <p style="margin:0 0 22px;">Kindly see below and assess on our behalf:</p>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
                                <tr>
                                    <td style="{{ $labelCell }}">Insured Name</td>
                                    <td style="{{ $valueCell }}">{{ $insuredName }}</td>
                                </tr>
                                <tr>
                                    <td style="{{ $labelCell }}">Policy #</td>
                                    <td style="{{ $valueCell }}">{{ $policyNumber }}</td>
                                </tr>
                                <tr>
                                    <td style="{{ $labelCell }}">Claim #</td>
                                    <td style="{{ $valueCell }}">{{ $claimNumber }}</td>
                                </tr>
                                <tr>
                                    <td style="{{ $labelCell }}">Date of Loss</td>
                                    <td style="{{ $valueCell }}">{{ $dateOfLoss }}</td>
                                </tr>
                                <tr>
                                    <td style="{{ $labelCell }}">Claim Document</td>
                                    <td style="{{ $valueCell }}">
                                        @forelse($documents ?? [] as $doc)
                                            <a href="{{ $doc['url'] }}" target="_blank" style="color:#1d4ed8;text-decoration:underline;">{{ $doc['name'] }}</a>@if(!$loop->last)<br />@endif
                                        @empty
                                            -
                                        @endforelse
                                    </td>
                                </tr>
                                <tr>
                                    <td style="{{ $labelCell }}">Contact Details</td>
                                    <td style="{{ $valueCell }}">{!! nl2br(e($contactDetails)) !!}</td>
                                </tr>
                                <tr>
                                    <td style="{{ $labelCell }}">Location</td>
                                    <td style="{{ $valueCell }}">{!! nl2br(e($location)) !!}</td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td style="background-color:#f9fafb;padding:16px 28px;text-align:center;color:#9ca3af;font-size:12px;border-top:1px solid #e5e7eb;">
                            &copy; {{ now()->year }} Alpha Direct Insurance. All rights reserved.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</div>
