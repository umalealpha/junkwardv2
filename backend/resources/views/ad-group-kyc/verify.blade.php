<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AD Group KYC Verification</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .verification-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            padding: 40px;
            margin-top: 50px;
        }
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo img {
            max-height: 60px;
        }
        .verification-form {
            max-width: 500px;
            margin: 0 auto;
        }
        .form-group {
            margin-bottom: 25px;
        }
        .form-label {
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }
        .form-control {
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 12px 15px;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .btn-verify {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 8px;
            padding: 15px 30px;
            font-size: 16px;
            font-weight: 600;
            color: white;
            width: 100%;
            transition: all 0.3s ease;
        }
        .btn-verify:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        .alert {
            border-radius: 8px;
            border: none;
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
        .step.active {
            background: #667eea;
            color: white;
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
                <div class="verification-container">
                    <div class="logo">
                        <img src="{{ asset('alphadirect_logo.png') }}" alt="AlphaDirect Logo" class="img-fluid">
                    </div>
                    
                    <div class="step-indicator">
                        <div class="step active">1</div>
                        <div class="step">2</div>
                        <div class="step">3</div>
                    </div>
                    
                    <h2 class="text-center mb-4">AD Group KYC Verification</h2>
                    <p class="text-center text-muted mb-4">
                        Please complete your KYC verification to maintain your policy coverage.
                    </p>
                    
                    @if(session('error'))
                        <div class="alert alert-danger">
                            {{ session('error') }}
                        </div>
                    @endif
                    
                    @if(session('success'))
                        <div class="alert alert-success">
                            {{ session('success') }}
                        </div>
                    @endif
                    
                    <form method="POST" action="{{ route('ad-group-kyc.verify.submit', $token) }}" class="verification-form" enctype="multipart/form-data">
                        @csrf
                        
                        <div class="form-group">
                            <label for="otp_code" class="form-label">OTP Code</label>
                            <input type="text" 
                                   class="form-control @error('otp_code') is-invalid @enderror" 
                                   id="otp_code" 
                                   name="otp_code" 
                                   placeholder="Enter 6-digit OTP code"
                                   maxlength="6"
                                   required>
                            @error('otp_code')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        
                        <div class="form-group">
                            <label for="customer_name" class="form-label">Full Name</label>
                            <input type="text" 
                                   class="form-control @error('customer_name') is-invalid @enderror" 
                                   id="customer_name" 
                                   name="customer_name" 
                                   placeholder="Enter your full name"
                                   value="{{ $customer->firstName ?? '' }} {{ $customer->lastName ?? '' }}"
                                   required>
                            @error('customer_name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        
                        <div class="form-group">
                            <label for="id_number" class="form-label">ID Number</label>
                            <input type="text" 
                                   class="form-control @error('id_number') is-invalid @enderror" 
                                   id="id_number" 
                                   name="id_number" 
                                   placeholder="Enter your ID number"
                                   required>
                            @error('id_number')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        
                        <div class="form-group">
                            <label for="phone_number" class="form-label">Phone Number</label>
                            <input type="tel" 
                                   class="form-control @error('phone_number') is-invalid @enderror" 
                                   id="phone_number" 
                                   name="phone_number" 
                                   placeholder="Enter your phone number"
                                   value="{{ $customer->cellphone ?? '' }}"
                                   required>
                            @error('phone_number')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="document_type" class="form-label">Document Type</label>
                            <select class="form-control @error('document_type') is-invalid @enderror" 
                                    id="document_type" 
                                    name="document_type" 
                                    required>
                                <option value="">Select document type</option>
                                <option value="omang">Omang</option>
                                <option value="passport">Passport</option>
                                <option value="drivers_license">Driver's License</option>
                            </select>
                            @error('document_type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="front_image" class="form-label">Document Front Image</label>
                            <input type="file" 
                                   class="form-control @error('front_image') is-invalid @enderror" 
                                   id="front_image" 
                                   name="front_image" 
                                   accept="image/*,.pdf"
                                   required>
                            <small class="form-text text-muted">Upload front side of your document (JPG, PNG, PDF - Max 5MB)</small>
                            @error('front_image')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="back_image" class="form-label">Document Back Image (Optional)</label>
                            <input type="file" 
                                   class="form-control @error('back_image') is-invalid @enderror" 
                                   id="back_image" 
                                   name="back_image" 
                                   accept="image/*,.pdf">
                            <small class="form-text text-muted">Upload back side of your document (JPG, PNG, PDF - Max 5MB)</small>
                            @error('back_image')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        
                        <button type="submit" class="btn btn-verify">
                            Complete KYC Verification
                        </button>
                    </form>
                    
                    <div class="text-center mt-4">
                        <small class="text-muted">
                            Need help? Contact our support team at 
                            <a href="mailto:support@alphadirect.co.bw">support@alphadirect.co.bw</a>
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-format OTP input
        document.getElementById('otp_code').addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
        });
        
        // Auto-format phone number
        document.getElementById('phone_number').addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9+]/g, '');
        });
    </script>
</body>
</html>
