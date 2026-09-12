<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Re-KYC Process Completed</title>
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
            background-color: #2c3e50;
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
        .success-message {
            background-color: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
            border-left: 4px solid #28a745;
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
            background-color: #007bff;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 5px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Re-KYC Process Completed</h1>
        <p>{{ config('app.name') }}</p>
    </div>
    
    <div class="content">
        <h2>Dear {{ $customer->firstName }} {{ $customer->lastName }},</h2>
        
        <div class="success-message">
            <strong>✅ Thank you for completing your Re-KYC verification!</strong>
        </div>
        
        <p>We are pleased to confirm that you have successfully completed your Know Your Customer (KYC) verification process. Your information has been updated and verified in our system.</p>
        
        <h3>What happens next?</h3>
        <ul>
            <li>Your account status has been updated</li>
            <li>You can continue using our services without interruption</li>
            <li>Your updated information is now secure in our system</li>
        </ul>
        
        <h3>Important Information</h3>
        <p>If you have any questions about this process or need further assistance, please don't hesitate to contact our support team.</p>
        
        <p><strong>Campaign:</strong> {{ $campaign->name }}</p>
        <p><strong>Completed on:</strong> {{ $link->completed_at ? $link->completed_at->format('F j, Y \a\t g:i A') : 'N/A' }}</p>
        
        <div style="text-align: center;">
            <a href="{{ config('app.url') }}/support" class="button">Contact Support</a>
        </div>
    </div>
    
    <div class="footer">
        <p>This is an automated message from {{ config('app.name') }}.</p>
        <p>Please do not reply to this email. If you need assistance, contact our support team.</p>
        <p>&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
    </div>
</body>
</html>
