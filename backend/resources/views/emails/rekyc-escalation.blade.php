<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Re-KYC Escalation Required</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #dc3545;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 5px 5px 0 0;
        }
        .content {
            background-color: #f8f9fa;
            padding: 30px;
            border-radius: 0 0 5px 5px;
        }
        .alert-message {
            background-color: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
            border-left: 4px solid #dc3545;
        }
        .customer-info {
            background-color: #e9ecef;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
            color: #6c757d;
            font-size: 14px;
        }
        .button {
            display: inline-block;
            background-color: #dc3545;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 5px;
            margin: 20px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            border: 1px solid #dee2e6;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f8f9fa;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>⚠️ Re-KYC Escalation Required</h1>
        <p>{{ config('app.name') }} - Compliance Team</p>
    </div>
    
    <div class="content">
        <div class="alert-message">
            <strong>🚨 URGENT:</strong> A customer's Re-KYC link has expired and requires immediate attention.
        </div>
        
        <h2>Customer Information</h2>
        <div class="customer-info">
            <p><strong>Customer ID:</strong> {{ $customer->id }}</p>
            <p><strong>Name:</strong> {{ $customer->firstName }} {{ $customer->lastName }}</p>
            <p><strong>Email:</strong> {{ $customer->email }}</p>
            <p><strong>Phone:</strong> {{ $customer->cellphone }}</p>
        </div>
        
        <h2>Campaign Details</h2>
        <table>
            <tr>
                <th>Campaign Name</th>
                <td>{{ $campaign->name }}</td>
            </tr>
            <tr>
                <th>Campaign ID</th>
                <td>{{ $campaign->id }}</td>
            </tr>
            <tr>
                <th>Link Status</th>
                <td>{{ $link->status }}</td>
            </tr>
            <tr>
                <th>Link Sent</th>
                <td>{{ $link->sent_at ? $link->sent_at->format('F j, Y \a\t g:i A') : 'Not sent' }}</td>
            </tr>
            <tr>
                <th>Link Expired</th>
                <td>{{ $link->expires_at ? $link->expires_at->format('F j, Y \a\t g:i A') : 'N/A' }}</td>
            </tr>
            <tr>
                <th>Days Since Sent</th>
                <td>{{ $link->sent_at ? $link->sent_at->diffInDays(now()) : 'N/A' }} days</td>
            </tr>
        </table>
        
        <h2>Required Actions</h2>
        <ol>
            <li><strong>Contact the customer</strong> via phone or email to follow up</li>
            <li><strong>Generate a new Re-KYC link</strong> if the customer is willing to complete the process</li>
            <li><strong>Update the customer's status</strong> in the compliance system</li>
            <li><strong>Document the escalation</strong> in the audit trail</li>
        </ol>
        
        <h2>Next Steps</h2>
        <p>Please take immediate action to resolve this Re-KYC escalation. The customer's account may need to be restricted until compliance is achieved.</p>
        
        <div style="text-align: center;">
            <a href="{{ config('app.url') }}/admin/rekyc/links/{{ $link->id }}" class="button">View Link Details</a>
        </div>
        
        <h3>Compliance Notes</h3>
        <p>This escalation is required to maintain regulatory compliance with Botswana Data Protection Act (DPA) 2018. All customer interactions must be logged in the audit system.</p>
    </div>
    
    <div class="footer">
        <p>This is an automated escalation from {{ config('app.name') }} Re-KYC system.</p>
        <p>Generated on {{ now()->format('F j, Y \a\t g:i A') }}</p>
        <p>&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
    </div>
</body>
</html>
