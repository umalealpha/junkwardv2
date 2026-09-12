<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AD Group KYC Verification Complete</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .completion-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            padding: 40px;
            margin-top: 50px;
            text-align: center;
        }
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo img {
            max-height: 60px;
        }
        .success-icon {
            font-size: 80px;
            color: #28a745;
            margin-bottom: 20px;
        }
        .completion-message {
            max-width: 500px;
            margin: 0 auto;
        }
        .btn-primary {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            border: none;
            border-radius: 8px;
            padding: 15px 30px;
            font-size: 16px;
            font-weight: 600;
            color: white;
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(40, 167, 69, 0.3);
        }
        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 30px;
        }
        .step {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 10px;
            font-weight: 600;
            color: #6c757d;
        }
        .step.completed {
            background: #28a745;
            color: white;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="completion-container">
                    <div class="logo">
                        <img src="{{ asset('alphadirect_logo.png') }}" alt="AlphaDirect Logo" class="img-fluid">
                    </div>
                    
                    <div class="step-indicator">
                        <div class="step completed">1</div>
                        <div class="step completed">2</div>
                        <div class="step completed">3</div>
                    </div>
                    
                    <div class="success-icon">
                        ✅
                    </div>
                    
                    <h2 class="text-center mb-4 text-success">KYC Verification Complete!</h2>
                    
                    <div class="completion-message">
                        <p class="lead mb-4">
                            Congratulations! Your AD Group KYC verification has been successfully completed.
                        </p>
                        
                        <div class="alert alert-success">
                            <h5 class="alert-heading">What's Next?</h5>
                            <ul class="mb-0 text-start">
                                <li>Your policy coverage is now fully verified</li>
                                <li>You will receive a confirmation email shortly</li>
                                <li>Your account is now compliant with regulatory requirements</li>
                                <li>You can access all policy benefits and services</li>
                            </ul>
                        </div>
                        
                        <div class="mt-4">
                            <h6>Verification Details:</h6>
                            <div class="row text-start">
                                <div class="col-6">
                                    <strong>Verification Date:</strong><br>
                                    {{ now()->format('d M Y, H:i') }}
                                </div>
                                <div class="col-6">
                                    <strong>Status:</strong><br>
                                    <span class="badge bg-success">Verified</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <a href="{{ route('home') }}" class="btn btn-primary me-3">
                                Go to Dashboard
                            </a>
                            <a href="mailto:support@alphadirect.co.bw" class="btn btn-outline-primary">
                                Contact Support
                            </a>
                        </div>
                    </div>
                    
                    <div class="text-center mt-4">
                        <small class="text-muted">
                            Thank you for completing your KYC verification with AlphaDirect Insurance.
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

