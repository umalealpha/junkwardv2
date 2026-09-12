<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>AD Group Insurance - KYC Verification Required</title>
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
        .policy-details {
            background-color: white;
            padding: 20px;
            border-radius: 5px;
            margin: 20px 0;
            border-left: 4px solid #3498db;
        }
        .otp-code {
            background-color: #e8f5e8;
            padding: 15px;
            border-radius: 5px;
            text-align: center;
            margin: 20px 0;
            border: 2px solid #27ae60;
        }
        .otp-code .code {
            font-size: 24px;
            font-weight: bold;
            color: #27ae60;
            letter-spacing: 3px;
        }
        .button {
            display: inline-block;
            background-color: #3498db;
            color: white;
            padding: 15px 30px;
            text-decoration: none;
            border-radius: 5px;
            margin: 20px 0;
            font-weight: bold;
        }
        .button:hover {
            background-color: #2980b9;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            font-size: 12px;
            color: #666;
        }
        .warning {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>AD Group Insurance</h1>
        <h2>KYC Verification Required</h2>
    </div>
    
    <div class="content">
        <p>Hello {{ $customer->firstName }},</p>
        
        <p>As the policyholder for your AD Group insurance policy, you are required to complete KYC verification to ensure compliance and security.</p>
        
        <div class="policy-details">
            <h3>Your Policy Details:</h3>
            <p><strong>Policy Number:</strong> {{ $policy->policyNumber }}</p>
            <p><strong>Policyholder:</strong> {{ $customer->firstName }} {{ $customer->lastName }}</p>
            <p><strong>Email:</strong> {{ $customer->email }}</p>
        </div>
        
        <div class="otp-code">
            <p><strong>Your OTP Code:</strong></p>
            <div class="code">{{ $otpCode }}</div>
            <p><small>Please use this code when completing your KYC verification</small></p>
        </div>
        
        <div class="warning">
            <strong>Important:</strong> This verification link will expire in 7 days. Please complete your KYC verification as soon as possible.
        </div>
        
        <p>To complete your KYC verification, please click the button below:</p>
        
        <div style="text-align: center;">
            <a href="{{ $kycUrl }}" class="button">Complete Your KYC Verification</a>
        </div>
        
        <p>This KYC verification is required for your individual policy and must be completed by you as the policyholder.</p>
        
        <p>If you have any questions or need assistance, please contact our support team.</p>
        
        <div class="footer">
            <p>Best regards,<br>AlphaDirect Insurance Team</p>
            <p><small>This is an automated message. Please do not reply to this email.</small></p>
        </div>
    </div>
</body>
</html>

