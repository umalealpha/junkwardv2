<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>HR Portal Access - {{ $employerGroup->name }}</title>
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
        <h2>Welcome to HR Portal - {{ $employerGroup->name }}</h2>
    </div>
    
    <div class="content">
        <p>Hello,</p>
        
        <p>You have been granted access to the HR Portal for <strong>{{ $employerGroup->name }}</strong>.</p>
        
        <p><strong>Your Login Details:</strong></p>
        <ul>
            <li><strong>Email:</strong> {{ $email }}</li>
            <li><strong>Username:</strong> {{ $email }}</li>
        </ul>
        
        <p>To complete your account setup and set your password, please click the button below:</p>
        
        <div style="text-align: center;">
            <a href="{{ $resetLink }}" class="button">Set Your Password</a>
        </div>
        
        <p><strong>Important:</strong></p>
        <ul>
            <li>This link will expire in 24 hours for security reasons</li>
            <li>You must set your password before you can access the HR Portal</li>
            <li>Keep your login credentials secure and do not share them</li>
        </ul>
        
        <p>Once you have set your password, you can access the HR Portal using the login link below:</p>
        
        <div style="text-align: center;">
            <a href="{{ $loginLink }}" class="button">HR Portal Login</a>
        </div>
        
        <p>If you have any questions or need assistance, please contact our support team.</p>
        
        <p>Best regards,<br>
        AlphaDirect Insurance Team</p>
    </div>
    
    <div class="footer">
        <p>This is an automated message. Please do not reply to this email.</p>
        <p>If you did not request this access, please contact our support team immediately.</p>
    </div>
</body>
</html>
