<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Password Reset - HR Portal</title>
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
            background-color: #f8f9fa;
            padding: 20px;
            text-align: center;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .content {
            background-color: #ffffff;
            padding: 20px;
            border: 1px solid #e9ecef;
            border-radius: 5px;
        }
        .button {
            display: inline-block;
            background-color: #007bff;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 5px;
            margin: 10px 0;
        }
        .button:hover {
            background-color: #0056b3;
        }
        .footer {
            margin-top: 20px;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 5px;
            font-size: 12px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="header">
        <h2>Password Reset - HR Portal</h2>
    </div>
    
    <div class="content">
        <p>Hello,</p>
        
        <p>You have requested to reset your password for the HR Portal.</p>
        
        <p>To reset your password, please click the button below:</p>
        
        <div style="text-align: center;">
            <a href="{{ $resetLink }}" class="button">Reset Password</a>
        </div>
        
        <p><strong>Important:</strong></p>
        <ul>
            <li>This link will expire in 24 hours for security reasons</li>
            <li>If you did not request this password reset, please ignore this email</li>
            <li>Keep your login credentials secure and do not share them</li>
        </ul>
        
        <p>If you have any questions or need assistance, please contact our support team.</p>
        
        <p>Best regards,<br>
        AlphaDirect Insurance Team</p>
    </div>
    
    <div class="footer">
        <p>This is an automated message. Please do not reply to this email.</p>
        <p>If you did not request this password reset, please contact our support team immediately.</p>
    </div>
</body>
</html>
