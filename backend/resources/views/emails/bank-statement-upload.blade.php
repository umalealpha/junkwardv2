<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bank Statement Upload Required</title>
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
        .button {
            display: inline-block;
            background-color: #007bff;
            color: white;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 5px;
            margin: 20px 0;
            font-weight: bold;
        }
        .button:hover {
            background-color: #0056b3;
        }
        .warning {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            color: #666;
            font-size: 14px;
        }
        .info-box {
            background-color: #e7f3ff;
            border-left: 4px solid #007bff;
            padding: 15px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Bank Statement Upload Required</h1>
        <p>AlphaDirect Insurance Co.</p>
    </div>
    
    <div class="content">
        <h2>Dear {{ $customer->firstName }} {{ $customer->lastName }},</h2>
        
        <p>We hope this email finds you well. As part of our ongoing verification process, we require you to upload your bank statement for account verification purposes.</p>
        
        <div class="info-box">
            <h3>📋 What You Need to Do:</h3>
            <ul>
                <li>Click the button below to access the secure upload portal</li>
                <li>Upload a clear, recent bank statement (PDF or image format)</li>
                <li>Ensure the statement shows your account details clearly</li>
                <li>Complete the upload process within the specified timeframe</li>
            </ul>
        </div>
        
        <div style="text-align: center;">
            <a href="{{ $uploadUrl }}" class="button">Upload Bank Statement Now</a>
        </div>
        
        <div class="warning">
            <strong>⚠️ Important Information:</strong>
            <ul>
                <li>This link will expire on: <strong>{{ $expiryDate }}</strong></li>
                <li>Please ensure your bank statement is recent (within the last 3 months)</li>
                <li>The document should clearly show your account number and bank name</li>
                <li>If you're unable to access the link, please contact our support team immediately</li>
            </ul>
        </div>
        
        <h3>Why We Need This Information:</h3>
        <p>This verification process helps us:</p>
        <ul>
            <li>Ensure account security and prevent fraud</li>
            <li>Comply with regulatory requirements</li>
            <li>Provide you with the best possible service</li>
            <li>Maintain the integrity of our customer database</li>
        </ul>
        
        <h3>Need Help?</h3>
        <p>If you have any questions or need assistance with the upload process, please don't hesitate to contact our support team:</p>
        <ul>
            <li>📞 Phone: +267 XXX XXXX</li>
            <li>📧 Email: support@alphadirect.co.bw</li>
            <li>🕒 Hours: Monday - Friday, 8:00 AM - 5:00 PM</li>
        </ul>
        
        <p>Thank you for your cooperation in completing this verification process.</p>
        
        <p>Best regards,<br>
        <strong>AlphaDirect Insurance Team</strong></p>
    </div>
    
    <div class="footer">
        <p>This is an automated message. Please do not reply to this email.</p>
        <p>© {{ date('Y') }} AlphaDirect Insurance Co. All rights reserved.</p>
    </div>
</body>
</html>
