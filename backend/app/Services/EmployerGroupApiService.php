<?php

namespace AlphaDirect\Services;

use AlphaDirect\Models\EmployerGroup;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\UploadedFile;

class EmployerGroupApiService
{
    /**
     * Update employer group application data
     *
     * @param array $data
     * @return array
     */
    public function updateApplication(array $data): array
    {
        try {
            // Extract application data
            $applicationData = $data['application_data'] ?? [];
            $deviceInfo = $data['device_info'] ?? [];
            $submissionMetadata = $data['submission_metadata'] ?? [];

            // Get the ID from the application_data or top-level data
            $id = $data['id'] ?? $applicationData['employer_group_id'] ?? null;
            if (!$id) {
                return [
                    'success' => false,
                    'message' => 'ID is required for updating employer group',
                    'error' => 'Missing ID parameter (provide either "id" or "application_data.employer_group_id")'
                ];
            }

            // Find the existing employer group
            $employerGroup = EmployerGroup::find($id);
            if (!$employerGroup) {
                return [
                    'success' => false,
                    'message' => 'Employer group not found',
                    'error' => 'Invalid ID provided'
                ];
            }

            // Prepare data for update
            $employerGroupData = [
                'updated_at' => now(),
            ];

            // Extract company info
            $companyInfo = $applicationData['company_info'] ?? [];
            $employerGroupData['employer_group_name'] = $companyInfo['employer_group_name'] ?? null;
            $employerGroupData['industry_section'] = $companyInfo['industry_section'] ?? null;
            $employerGroupData['other_industry'] = $companyInfo['other_industry'] ?? null;
            $employerGroupData['street_address'] = $companyInfo['street_address'] ?? null;
            $employerGroupData['postal_address'] = $companyInfo['postal_address'] ?? null;
            $employerGroupData['city_town'] = $companyInfo['city_town'] ?? null;

            // Extract contact info
            $contactInfo = $applicationData['contact_info'] ?? [];
            $employerGroupData['contact_name'] = $contactInfo['contact_name'] ?? null;
            $employerGroupData['contact_email'] = $contactInfo['contact_email'] ?? null;
            $employerGroupData['contact_phone'] = $contactInfo['contact_phone'] ?? null;

            // Extract broker billing info
            $brokerBilling = $applicationData['broker_billing'] ?? [];
            $employerGroupData['broker_referral'] = $brokerBilling['broker_referral'] ?? null;
            $employerGroupData['payment_method'] = $brokerBilling['payment_method'] ?? null;
            $employerGroupData['account_name'] = $brokerBilling['account_name'] ?? null;
            $employerGroupData['account_number'] = $brokerBilling['account_number'] ?? null;
            $employerGroupData['bank_name_branch'] = $brokerBilling['bank_name_branch'] ?? null;

            // Extract documents info
            $documents = $applicationData['documents'] ?? [];
            
            // if (isset($applicationData['files']['certificate_file'])) {
            //     $file = $applicationData['files']['certificate_file'];
            //     $name = $file['originalName']; // Use 'originalName' from your array
            //     $filePath = 'employer-groups/certificates/' . $name;
            //     Storage::disk('s3')->put($filePath, file_get_contents($file['pathname']), 'public');
            //     $employerGroupData['certificate_file'] = $filePath;
            //     $employerGroupData['certificate_filename'] = $documents['certificate_filename'];
            // }
            
            // Handle file uploads if files are provided in the request
            $fileData = $this->handleFileUploads($data);
// dd($fileData);
            // Debug logging
            Log::info('File upload data received', [
                'fileData' => $fileData,
                'has_certificate_file' => isset($data['certificate_file']),
                'has_tax_certificate_file' => isset($data['tax_certificate_file']),
                'has_proof_address_file' => isset($data['proof_address_file']),
            ]);

            // Store file paths from uploaded files
            $employerGroupData['certificate_file'] = $fileData['certificate_file'] ?? null;
            $employerGroupData['certificate_filename'] = $fileData['certificate_filename'] ?? $documents['certificate_filename'] ?? null;
            $employerGroupData['tax_certificate_file'] = $fileData['tax_certificate_file'] ?? null;
            $employerGroupData['tax_certificate_filename'] = $fileData['tax_certificate_filename'] ?? $documents['tax_certificate_filename'] ?? null;
            $employerGroupData['proof_address_file'] = $fileData['proof_address_file'] ?? null;
            $employerGroupData['proof_address_filename'] = $fileData['proof_address_filename'] ?? $documents['proof_address_filename'] ?? null;

            // Debug logging for employer group data
            Log::info('Employer group data before update', [
                'certificate_file' => $employerGroupData['certificate_file'],
                'certificate_filename' => $employerGroupData['certificate_filename'],
                'tax_certificate_file' => $employerGroupData['tax_certificate_file'],
                'tax_certificate_filename' => $employerGroupData['tax_certificate_filename'],
                'proof_address_file' => $employerGroupData['proof_address_file'],
                'proof_address_filename' => $employerGroupData['proof_address_filename'],
            ]);

            // Extract agreement info
            $agreement = $applicationData['agreement'] ?? [];
            $employerGroupData['terms_agreement'] = $agreement['terms_agreed'] ?? 0;
            $employerGroupData['initials'] = $agreement['initials'] ?? null;

            // Store all metadata in JSON format
            $metadata = [
                'application_data' => $applicationData,
                'device_info' => $deviceInfo,
                'submission_metadata' => $submissionMetadata,
                'stored_at' => now()->toISOString(),
                'api_version' => '1.0'
            ];

            $employerGroupData['metadata'] = $metadata;

            // Also map to legacy fields for backward compatibility
            $employerGroupData['name'] = $employerGroupData['employer_group_name'];
            $employerGroupData['industry'] = $employerGroupData['industry_section'];
            $employerGroupData['address'] = $employerGroupData['street_address'];
            $employerGroupData['town'] = $employerGroupData['city_town'];
            $employerGroupData['broker'] = $employerGroupData['broker_referral'];

            // Update the employer group
            $employerGroup->update($employerGroupData);

            Log::info('Employer Group Application Updated via API', [
                'employer_group_id' => $employerGroup->id,
                'id' => $employerGroup->id,
                'contact_email' => $employerGroup->contact_email,
                'submission_source' => $deviceInfo['source'] ?? 'unknown'
            ]);

            return [
                'success' => true,
                'message' => 'Employer group application updated successfully',
                'data' => [
                    'id' => $employerGroup->id,
                    'employer_group_id' => $employerGroup->id,
                    'employer_group_name' => $employerGroup->name,
                    'contact_email' => $employerGroup->contact_email,
                    'status' => $employerGroup->status,
                    'updated_at' => $employerGroup->updated_at->toISOString(),
                    'submission_source' => $deviceInfo['source'] ?? 'unknown'
                    // 'uploaded_files' => [
                    //     'certificate_file' => $fileData['certificate_file'],
                    //     'certificate_filename' => $employerGroup->certificate_filename,
                    //     'tax_certificate_file' => $fileData['tax_certificate_file'],
                    //     'tax_certificate_filename' => $employerGroup->tax_certificate_filename,
                    //     'proof_address_file' => $fileData['proof_address_file'],
                    //     'proof_address_filename' => $employerGroup->proof_address_filename,
                    // ]
                ]
            ];

        } catch (\Exception $e) {
            Log::error('Employer Group API Storage Failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data_keys' => array_keys($data)
            ]);

            return [
                'success' => false,
                'message' => 'Failed to store employer group application',
                'error' => 'Internal server error'
            ];
        }
    }


    /**
     * Validate the incoming data structure
     *
     * @param array $data
     * @return array
     */
    public function validateData(array $data): array
    {
        $errors = [];

        // Check required top-level keys - ID can be either top-level or in application_data
        if (!isset($data['id']) && !isset($data['application_data']['employer_group_id'])) {
            $errors[] = 'id is required for updating employer group (provide either "id" or "application_data.employer_group_id")';
        }

        if (!isset($data['application_data'])) {
            $errors[] = 'application_data is required';
        }

        if (!isset($data['device_info'])) {
            $errors[] = 'device_info is required';
        }

        if (!isset($data['submission_metadata'])) {
            $errors[] = 'submission_metadata is required';
        }

        // Validate application_data structure
        if (isset($data['application_data'])) {
            $appData = $data['application_data'];

            // Check required sections
            $requiredSections = ['company_info', 'contact_info', 'broker_billing', 'documents', 'agreement'];
            foreach ($requiredSections as $section) {
                if (!isset($appData[$section])) {
                    $errors[] = "application_data.{$section} is required";
                }
            }

            // Validate company info
            if (isset($appData['company_info'])) {
                $companyInfo = $appData['company_info'];
                $requiredFields = ['employer_group_name', 'industry_section', 'street_address', 'postal_address', 'city_town'];
                foreach ($requiredFields as $field) {
                    if (empty($companyInfo[$field])) {
                        $errors[] = "application_data.company_info.{$field} is required";
                    }
                }
            }

            // Validate contact info
            if (isset($appData['contact_info'])) {
                $contactInfo = $appData['contact_info'];
                $requiredFields = ['contact_name', 'contact_email', 'contact_phone'];
                foreach ($requiredFields as $field) {
                    if (empty($contactInfo[$field])) {
                        $errors[] = "application_data.contact_info.{$field} is required";
                    }
                }

                // Validate email format
                if (isset($contactInfo['contact_email']) && !filter_var($contactInfo['contact_email'], FILTER_VALIDATE_EMAIL)) {
                    $errors[] = 'application_data.contact_info.contact_email must be a valid email address';
                }
            }

            // Validate agreement
            if (isset($appData['agreement'])) {
                $agreement = $appData['agreement'];
                if (!isset($agreement['terms_agreed']) || !$agreement['terms_agreed']) {
                    $errors[] = 'application_data.agreement.terms_agreed must be true';
                }
                if (empty($agreement['initials'])) {
                    $errors[] = 'application_data.agreement.initials is required';
                }
            }
        }

        return $errors;
    }


    /**
     * Handle file uploads from request
     *
     * @param array $requestData
     * @return array
     */
    private function handleFileUploads(array $requestData): array
    {
        $fileData = [
            'certificate_file' => null,
            'certificate_filename' => null,
            'tax_certificate_file' => null,
            'tax_certificate_filename' => null,
            'proof_address_file' => null,
            'proof_address_filename' => null,
        ];

        // Debug logging
        Log::info('handleFileUploads called', [
            'requestData_keys' => array_keys($requestData),
            'has_certificate_file' => isset($requestData['certificate_file']),
            'has_tax_certificate_file' => isset($requestData['tax_certificate_file']),
            'has_proof_address_file' => isset($requestData['proof_address_file']),
        ]);

        // Handle certificate file
        if (isset($requestData['certificate_file']) && $requestData['certificate_file'] instanceof UploadedFile) {
            $file = $requestData['certificate_file'];
            $name = $file->getClientOriginalName();
            $filePath = 'employer-groups/certificates/' . $name;

            try {
                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                $fileData['certificate_file'] = $filePath;
                $fileData['certificate_filename'] = $name;

                Log::info('Certificate file uploaded to S3 successfully', [
                    'original_name' => $name,
                    's3_path' => $filePath,
                    'size' => $file->getSize()
                ]);
            } catch (\Exception $e) {
                Log::error('Certificate file upload failed', [
                    'error' => $e->getMessage(),
                    'file' => $name
                ]);
            }
        }
        
        // Handle tax certificate file
        if (isset($requestData['tax_certificate_file']) && $requestData['tax_certificate_file'] instanceof UploadedFile) {
            $file = $requestData['tax_certificate_file'];
            $name = $file->getClientOriginalName();
            $filePath = 'employer-groups/tax-certificates/' . $name;

            try {
                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                $fileData['tax_certificate_file'] = $filePath;
                $fileData['tax_certificate_filename'] = $name;

                Log::info('Tax certificate file uploaded to S3 successfully', [
                    'original_name' => $name,
                    's3_path' => $filePath,
                    'size' => $file->getSize()
                ]);
            } catch (\Exception $e) {
                Log::error('Tax certificate file upload failed', [
                    'error' => $e->getMessage(),
                    'file' => $name
                ]);
            }
        }

        // Handle proof of address file
        if (isset($requestData['proof_address_file']) && $requestData['proof_address_file'] instanceof UploadedFile) {
            $file = $requestData['proof_address_file'];
            $name = $file->getClientOriginalName();
            $filePath = 'employer-groups/proof-address/' . $name;

            try {
                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                $fileData['proof_address_file'] = $filePath;
                $fileData['proof_address_filename'] = $name;

                Log::info('Proof of address file uploaded to S3 successfully', [
                    'original_name' => $name,
                    's3_path' => $filePath,
                    'size' => $file->getSize()
                ]);
            } catch (\Exception $e) {
                Log::error('Proof of address file upload failed', [
                    'error' => $e->getMessage(),
                    'file' => $name
                ]);
            }
        }

        // Debug logging for return data
        Log::info('handleFileUploads returning', [
            'fileData' => $fileData
        ]);

        return $fileData;
    }

    /**
     * Send OTP to employer group contact email and phone
     *
     * @param int $employerGroupId
     * @return array
     */
    public function sendOtp(int $employerGroupId): array
    {
        try {
            // Find the employer group
            $employerGroup = EmployerGroup::find($employerGroupId);
            if (!$employerGroup) {
                return [
                    'success' => false,
                    'message' => 'Employer group not found',
                    'error' => 'Invalid employer group ID'
                ];
            }

            // Check if contact email and phone exist
            if (empty($employerGroup->contact_email) && empty($employerGroup->contact_phone)) {
                return [
                    'success' => false,
                    'message' => 'No contact information available',
                    'error' => 'Both contact_email and contact_phone are empty'
                ];
            }

            $deliveryMethods = [];
            $errors = [];

            // Generate OTP
            $otp = new \AlphaDirect\OTP();
            $otpCode = null;

            // Send OTP via SMS if phone number exists
            if (!empty($employerGroup->contact_phone)) {
                try {
                    $otp_response = $otp->OTPStore($employerGroup->contact_phone);
                    $otp_data = $otp_response->getData();
                    $otpCode = $otp_data->otp_code;

                    // Send SMS
                    event(new \AlphaDirect\Events\SendSms(
                        '+267' . $employerGroup->contact_phone,
                        'Alpha Direct, Your Verification Code is: ' . $otpCode
                    ));

                    $deliveryMethods[] = 'sms';
                    Log::info('OTP sent via SMS to employer group', [
                        'employer_group_id' => $employerGroupId,
                        'phone' => $employerGroup->contact_phone
                    ]);
                } catch (\Exception $e) {
                    $errors[] = 'Failed to send SMS: ' . $e->getMessage();
                    Log::error('SMS OTP send failed for employer group', [
                        'employer_group_id' => $employerGroupId,
                        'phone' => $employerGroup->contact_phone,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // Send OTP via Email if email exists
            if (!empty($employerGroup->contact_email)) {
                try {
                    // Generate OTP for email if not already generated
                    if (!$otpCode) {
                        $otp_response = $otp->OTPStore($employerGroup->contact_phone ?: 'email_' . $employerGroup->contact_email);
                        $otp_data = $otp_response->getData();
                        $otpCode = $otp_data->otp_code;
                    }

                    // Prepare email data
                    $data = new \stdClass();
                    $data->user_id = $otp_data->id ?? null;
                    $data->customer_id = null;
                    $data->hook = 'otp_mail';
                    $data->attachment = null;

                    // Get email template
                    $emailTemplate = \AlphaDirect\EmailBroadcasting::where('hook_slug', $data->hook)->first(['subject']);
                    if ($emailTemplate) {
                        $markdown = new \AlphaDirect\Mail\MailTemplate($data);
                        $html = $markdown->render('Mail.mailTemplate', ['data' => $data]);

                        // Send email
                        event(new \AlphaDirect\Events\SendMail(
                            $employerGroup->contact_email,
                            $emailTemplate->subject,
                            "",
                            $html,
                            null,
                            ['hook' => $data->hook]
                        ));

                        $deliveryMethods[] = 'email';
                        Log::info('OTP sent via email to employer group', [
                            'employer_group_id' => $employerGroupId,
                            'email' => $employerGroup->contact_email
                        ]);
                    } else {
                        $errors[] = 'Email template not found';
                    }
                } catch (\Exception $e) {
                    $errors[] = 'Failed to send email: ' . $e->getMessage();
                    Log::error('Email OTP send failed for employer group', [
                        'employer_group_id' => $employerGroupId,
                        'email' => $employerGroup->contact_email,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // Check if at least one method succeeded
            if (empty($deliveryMethods)) {
                return [
                    'success' => false,
                    'message' => 'Failed to send OTP via any method',
                    'errors' => $errors
                ];
            }

            return [
                'success' => true,
                'message' => 'OTP sent successfully via ' . implode(' and ', $deliveryMethods),
                'data' => [
                    'employer_group_id' => $employerGroupId,
                    'delivery_methods' => $deliveryMethods,
                    'contact_email' => $employerGroup->contact_email,
                    'contact_phone' => $employerGroup->contact_phone,
                    'otp_code' => $otpCode
                ],
                'errors' => $errors
            ];

        } catch (\Exception $e) {
            Log::error('Employer Group OTP Send Failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'employer_group_id' => $employerGroupId
            ]);

            return [
                'success' => false,
                'message' => 'Failed to send OTP',
                'error' => 'Internal server error'
            ];
        }
    }

    /**
     * Verify OTP for employer group
     *
     * @param int $employerGroupId
     * @param string $otpCode
     * @return array
     */
    public function verifyOtp(int $employerGroupId, string $otpCode): array
    {
        try {
            // Find the employer group
            $employerGroup = EmployerGroup::find($employerGroupId);
            if (!$employerGroup) {
                return [
                    'success' => false,
                    'message' => 'Employer group not found',
                    'error' => 'Invalid employer group ID'
                ];
            }

            // Check if contact phone exists for OTP verification
            if (empty($employerGroup->contact_phone)) {
                return [
                    'success' => false,
                    'message' => 'No phone number available for OTP verification',
                    'error' => 'Contact phone is required for OTP verification'
                ];
            }

            // Verify OTP using the existing OTP model
            $otp = new \AlphaDirect\OTP();
            $isValidOtp = $otp->scopeauthenticateOTPCodeUsingOtpCodeAndCellphone(
                $otp->newQuery(),
                $otpCode,
                $employerGroup->contact_phone
            );

            if (!$isValidOtp) {
                Log::warning('Invalid OTP verification attempt for employer group', [
                    'employer_group_id' => $employerGroupId,
                    'phone' => $employerGroup->contact_phone,
                    'otp_code' => $otpCode
                ]);

                return [
                    'success' => false,
                    'message' => 'Invalid OTP code',
                    'error' => 'The provided OTP code is incorrect or expired'
                ];
            }

            // Get OTP data for cleanup
            $otpData = $otp->scopegetOTPDataUsingOTPCode($otp->newQuery(), $otpCode);

            // Clean up the used OTP
            if ($otpData) {
                $otp->scopedeleteOTP($otp->newQuery(), $otpData->id);
            }

            Log::info('OTP verification successful for employer group', [
                'employer_group_id' => $employerGroupId,
                'phone' => $employerGroup->contact_phone,
                'verified_at' => now()
            ]);

            return [
                'success' => true,
                'message' => 'OTP verification successful',
                'data' => [
                    'employer_group_id' => $employerGroupId,
                    'employer_group_name' => $employerGroup->employer_group_name,
                    'contact_email' => $employerGroup->contact_email,
                    'contact_phone' => $employerGroup->contact_phone,
                    'verified_at' => now()->toISOString()
                ]
            ];

        } catch (\Exception $e) {
            Log::error('Employer Group OTP Verification Failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'employer_group_id' => $employerGroupId,
                'otp_code' => $otpCode
            ]);

            return [
                'success' => false,
                'message' => 'Failed to verify OTP',
                'error' => 'Internal server error'
            ];
        }
    }
}
