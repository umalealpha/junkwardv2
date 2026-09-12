<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bank Statement Upload - Alpha Direct</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .upload-container {
            max-width: 600px;
            margin: 0 auto;
            padding: 2rem;
        }
        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 2rem;
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
            position: relative;
        }
        .step.active {
            background: #007bff;
            color: white;
        }
        .step.completed {
            background: #28a745;
            color: white;
        }
        .step::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 100%;
            width: 20px;
            height: 2px;
            background: #e9ecef;
            transform: translateY(-50%);
        }
        .step:last-child::after {
            display: none;
        }
        .upload-area {
            border: 2px dashed #dee2e6;
            border-radius: 10px;
            padding: 3rem;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .upload-area:hover {
            border-color: #007bff;
            background-color: #f8f9fa;
        }
        .upload-area.dragover {
            border-color: #007bff;
            background-color: #e3f2fd;
        }
        .file-preview {
            margin-top: 1rem;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 5px;
            display: none;
        }
        .loading {
            display: none;
        }
        .error-message {
            color: #dc3545;
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }
        .success-message {
            color: #28a745;
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="upload-container">
            <!-- Header -->
            <div class="text-center mb-4">
                <h2><i class="fas fa-file-upload text-primary"></i> Bank Statement Upload</h2>
                <p class="text-muted">Upload your bank statement for verification</p>
            </div>

            <!-- Step Indicator -->
            <div class="step-indicator">
                <div class="step active" id="step1">
                    <i class="fas fa-phone"></i>
                </div>
                <div class="step" id="step2">
                    <i class="fas fa-upload"></i>
                </div>
                <div class="step" id="step3">
                    <i class="fas fa-check"></i>
                </div>
            </div>

            <!-- Step 1: OTP Verification -->
            <div id="otpStep" class="step-content">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Verify Your Identity</h5>
                        <p class="card-text">We'll send you a verification code to confirm your identity.</p>
                        
                        <div id="customerInfo" class="mb-3">
                            <!-- Customer info will be loaded here -->
                        </div>

                        <div class="mb-3">
                            <label for="otpCode" class="form-label">Enter Verification Code</label>
                            <input type="text" class="form-control" id="otpCode" placeholder="Enter 6-digit code" maxlength="6">
                            <div id="otpError" class="error-message"></div>
                        </div>

                        <div class="d-grid gap-2">
                            <button class="btn btn-primary" id="verifyOtpBtn">
                                <span class="btn-text">Verify Code</span>
                                <span class="loading">
                                <i class="fas fa-spinner fa-spin"></i> Verifying...
                                </span>
                            </button>
                            <button class="btn btn-outline-secondary" id="resendOtpBtn">
                                <span class="btn-text">Resend Code</span>
                                <span class="loading">
                                <i class="fas fa-spinner fa-spin"></i> Sending...
                                </span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Step 2: Upload Form -->
            <div id="uploadStep" class="step-content" style="display: none;">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Upload Bank Statement</h5>
                        <p class="card-text">Please upload your bank statement and provide the required information.</p>

                        <form id="uploadForm">
                            <div class="mb-3">
                                <label for="bankName" class="form-label">Bank Name *</label>
                                <input type="text" class="form-control" id="bankName" required>
                                <div id="bankNameError" class="error-message"></div>
                            </div>

                            <div class="mb-3">
                                <label for="accountNumber" class="form-label">Account Number *</label>
                                <input type="text" class="form-control" id="accountNumber" required>
                                <div id="accountNumberError" class="error-message"></div>
                            </div>

                            <div class="mb-3">
                                <label for="statementPeriod" class="form-label">Statement Period *</label>
                                <input type="text" class="form-control" id="statementPeriod" placeholder="e.g., January 2024 - March 2024" required>
                                <div id="statementPeriodError" class="error-message"></div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Bank Statement File *</label>
                                <div class="upload-area" id="uploadArea">
                                    <i class="fas fa-cloud-upload-alt fa-3x text-muted mb-3"></i>
                                    <h5>Drag & Drop your file here</h5>
                                    <p class="text-muted">or click to browse</p>
                                    <input type="file" id="bankStatementFile" accept=".pdf,.jpg,.jpeg,.png" style="display: none;">
                                    <div class="mt-2">
                                        <small class="text-muted">Accepted formats: PDF, JPG, JPEG, PNG (Max: 10MB)</small>
                                    </div>
                                </div>
                                <div id="filePreview" class="file-preview">
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-file-pdf fa-2x text-danger me-3"></i>
                                        <div>
                                            <div class="fw-bold" id="fileName"></div>
                                            <div class="text-muted" id="fileSize"></div>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-outline-danger ms-auto" id="removeFile">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </div>
                                <div id="fileError" class="error-message"></div>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary" id="uploadBtn">
                                    <span class="btn-text">Upload Bank Statement</span>
                                    <span class="loading">
                                    <i class="fas fa-spinner fa-spin"></i> Uploading...
                                    </span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Step 3: Success -->
            <div id="successStep" class="step-content" style="display: none;">
                <div class="card">
                    <div class="card-body text-center">
                        <i class="fas fa-check-circle fa-4x text-success mb-3"></i>
                        <h5 class="card-title">Upload Successful!</h5>
                        <p class="card-text">Your bank statement has been uploaded successfully and is being processed.</p>
                        <div id="uploadDetails" class="mt-3">
                            <!-- Upload details will be shown here -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        class BankStatementUpload {
            constructor() {
                this.token = new URLSearchParams(window.location.search).get('token');
                this.currentStep = 1;
                this.customerData = null;
                this.uploadRequirements = null;
                
                this.init();
            }

            init() {
                this.loadCustomerData();
                this.bindEvents();
            }

            async loadCustomerData() {
                try {
                    const response = await fetch(`/api/bank-statement/access/${this.token}`);
                    const data = await response.json();

                    if (data.success) {
                        this.customerData = data.data;
                        this.displayCustomerInfo();
                        this.loadUploadRequirements();
                    } else {
                        this.showError(data.message);
                    }
                } catch (error) {
                    this.showError('Failed to load customer data');
                }
            }

            displayCustomerInfo() {
                const customerInfo = document.getElementById('customerInfo');
                customerInfo.innerHTML = `
                    <div class="alert alert-info">
                        <strong>Customer:</strong> ${this.customerData.customer.name}<br>
                        <strong>Email:</strong> ${this.customerData.customer.email}<br>
                        <strong>Phone:</strong> ${this.customerData.customer.phone}
                    </div>
                `;
            }

            async loadUploadRequirements() {
                try {
                    const response = await fetch(`/api/bank-statement/upload-requirements/${this.token}`);
                    const data = await response.json();

                    if (data.success) {
                        this.uploadRequirements = data.data.upload_requirements;
                    }
                } catch (error) {
                    console.error('Failed to load upload requirements:', error);
                }
            }

            bindEvents() {
                // OTP Verification
                document.getElementById('verifyOtpBtn').addEventListener('click', () => this.verifyOtp());
                document.getElementById('resendOtpBtn').addEventListener('click', () => this.resendOtp());

                // File Upload
                const uploadArea = document.getElementById('uploadArea');
                const fileInput = document.getElementById('bankStatementFile');

                uploadArea.addEventListener('click', () => fileInput.click());
                uploadArea.addEventListener('dragover', (e) => this.handleDragOver(e));
                uploadArea.addEventListener('dragleave', (e) => this.handleDragLeave(e));
                uploadArea.addEventListener('drop', (e) => this.handleDrop(e));

                fileInput.addEventListener('change', (e) => this.handleFileSelect(e));
                document.getElementById('removeFile').addEventListener('click', () => this.removeFile());

                // Form Submission
                document.getElementById('uploadForm').addEventListener('submit', (e) => this.handleUpload(e));
            }

            async verifyOtp() {
                const otpCode = document.getElementById('otpCode').value;
                if (!otpCode || otpCode.length !== 6) {
                    this.showError('Please enter a valid 6-digit code', 'otpError');
                    return;
                }

                this.setLoading('verifyOtpBtn', true);

                try {
                    const response = await fetch(`/api/bank-statement/verify-otp/${this.token}`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify({ otp_code: otpCode })
                    });

                    const data = await response.json();

                    if (data.success) {
                        this.nextStep();
                    } else {
                        this.showError(data.message, 'otpError');
                    }
                } catch (error) {
                    this.showError('Failed to verify OTP', 'otpError');
                } finally {
                    this.setLoading('verifyOtpBtn', false);
                }
            }

            async resendOtp() {
                this.setLoading('resendOtpBtn', true);

                try {
                    const response = await fetch('/api/bank-statement/send-otp', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify({
                            token: this.token,
                            methods: ['sms', 'email', 'whatsapp']
                        })
                    });

                    const data = await response.json();

                    if (data.success) {
                        this.showSuccess('OTP sent successfully', 'otpError');
                    } else {
                        this.showError(data.message, 'otpError');
                    }
                } catch (error) {
                    this.showError('Failed to send OTP', 'otpError');
                } finally {
                    this.setLoading('resendOtpBtn', false);
                }
            }

            handleDragOver(e) {
                e.preventDefault();
                e.currentTarget.classList.add('dragover');
            }

            handleDragLeave(e) {
                e.preventDefault();
                e.currentTarget.classList.remove('dragover');
            }

            handleDrop(e) {
                e.preventDefault();
                e.currentTarget.classList.remove('dragover');
                
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    this.handleFile(files[0]);
                }
            }

            handleFileSelect(e) {
                const file = e.target.files[0];
                if (file) {
                    this.handleFile(file);
                }
            }

            handleFile(file) {
                // Validate file
                const maxSize = this.uploadRequirements?.max_file_size || 10485760; // 10MB
                const allowedTypes = this.uploadRequirements?.accepted_formats || ['pdf', 'jpg', 'jpeg', 'png'];
                const fileExtension = file.name.split('.').pop().toLowerCase();

                if (file.size > maxSize) {
                    this.showError('File size exceeds 10MB limit', 'fileError');
                    return;
                }

                if (!allowedTypes.includes(fileExtension)) {
                    this.showError('File type not supported. Please upload PDF, JPG, JPEG, or PNG files.', 'fileError');
                    return;
                }

                // Display file preview
                document.getElementById('fileName').textContent = file.name;
                document.getElementById('fileSize').textContent = this.formatFileSize(file.size);
                document.getElementById('filePreview').style.display = 'block';
                document.getElementById('uploadArea').style.display = 'none';
            }

            removeFile() {
                document.getElementById('bankStatementFile').value = '';
                document.getElementById('filePreview').style.display = 'none';
                document.getElementById('uploadArea').style.display = 'block';
                this.clearError('fileError');
            }

            async handleUpload(e) {
                e.preventDefault();

                const formData = new FormData();
                formData.append('bank_name', document.getElementById('bankName').value);
                formData.append('account_number', document.getElementById('accountNumber').value);
                formData.append('statement_period', document.getElementById('statementPeriod').value);
                formData.append('bank_statement', document.getElementById('bankStatementFile').files[0]);

                this.setLoading('uploadBtn', true);

                try {
                    const response = await fetch(`/api/bank-statement/upload/${this.token}`, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: formData
                    });

                    const data = await response.json();

                    if (data.success) {
                        this.showUploadSuccess(data.data);
                        this.nextStep();
                    } else {
                        this.showError(data.message, 'fileError');
                    }
                } catch (error) {
                    this.showError('Failed to upload file', 'fileError');
                } finally {
                    this.setLoading('uploadBtn', false);
                }
            }

            showUploadSuccess(uploadData) {
                const uploadDetails = document.getElementById('uploadDetails');
                uploadDetails.innerHTML = `
                    <div class="alert alert-success">
                        <strong>Upload ID:</strong> ${uploadData.upload_id}<br>
                        <strong>File Size:</strong> ${this.formatFileSize(uploadData.file_size)}<br>
                        <strong>File Type:</strong> ${uploadData.file_type}<br>
                        <strong>Status:</strong> ${uploadData.status}
                    </div>
                `;
            }

            nextStep() {
                document.getElementById(`step${this.currentStep}`).classList.remove('active');
                document.getElementById(`step${this.currentStep}`).classList.add('completed');
                
                this.currentStep++;
                
                document.getElementById(`step${this.currentStep}`).classList.add('active');
                document.getElementById(`step${this.currentStep}Step`).style.display = 'block';
                document.getElementById(`step${this.currentStep - 1}Step`).style.display = 'none';
            }

            setLoading(buttonId, loading) {
                const button = document.getElementById(buttonId);
                const btnText = button.querySelector('.btn-text');
                const loadingEl = button.querySelector('.loading');

                if (loading) {
                    btnText.style.display = 'none';
                    loadingEl.style.display = 'inline-block';
                    button.disabled = true;
                } else {
                    btnText.style.display = 'inline-block';
                    loadingEl.style.display = 'none';
                    button.disabled = false;
                }
            }

            showError(message, elementId = null) {
                if (elementId) {
                    const errorEl = document.getElementById(elementId);
                    errorEl.textContent = message;
                    errorEl.className = 'error-message';
                } else {
                    alert(message);
                }
            }

            showSuccess(message, elementId = null) {
                if (elementId) {
                    const errorEl = document.getElementById(elementId);
                    errorEl.textContent = message;
                    errorEl.className = 'success-message';
                }
            }

            clearError(elementId) {
                const errorEl = document.getElementById(elementId);
                errorEl.textContent = '';
                errorEl.className = '';
            }

            formatFileSize(bytes) {
                if (bytes === 0) return '0 Bytes';
                const k = 1024;
                const sizes = ['Bytes', 'KB', 'MB', 'GB'];
                const i = Math.floor(Math.log(bytes) / Math.log(k));
                return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
            }
        }

        // Initialize the application
        document.addEventListener('DOMContentLoaded', () => {
            new BankStatementUpload();
        });
    </script>
</body>
</html>
