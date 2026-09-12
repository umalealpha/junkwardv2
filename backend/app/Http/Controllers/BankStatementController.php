<?php

namespace AlphaDirect\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Carbon\Carbon;
use AlphaDirect\Models\DeduplicationChecks;
use AlphaDirect\Customer;
use AlphaDirect\OTP;
use AlphaDirect\Http\Controllers\WhatsAppController;
use AlphaDirect\EmailBroadcasting;
use AlphaDirect\KYC;
use AlphaDirect\Mail\MailTemplate;
use AlphaDirect\Services\DeduplicationActivityService;

class BankStatementController extends Controller
{
    protected $activityService;

    public function __construct(DeduplicationActivityService $activityService)
    {
        $this->activityService = $activityService;
    }
    /**
     * Access bank statement upload page
     */
    public function accessLink(Request $request, string $token): JsonResponse
    {
        try {
            $deduplicationCheck = DeduplicationChecks::where('unique_access_token', $token)
                ->with(['customer'])
                ->first();

            if (!$deduplicationCheck) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired token'
                ], 404);
            }

            if ($deduplicationCheck->status === 'expired' || $deduplicationCheck->link_expires_at < Carbon::now()) {
                $deduplicationCheck->update(['status' => 'expired']);
                return response()->json([
                    'success' => false,
                    'message' => 'Link has expired'
                ], 410);
            }

            if ($deduplicationCheck->status === 'completed') {
                return response()->json([
                    'success' => false,
                    'message' => 'Link already used'
                ], 410);
            }

            // Update access tracking
            $deduplicationCheck->update([
                'link_opened_at' => Carbon::now(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'status' => 'active'
            ]);
            
            // Log link access activity
            $this->activityService->logLinkAccessed($deduplicationCheck, $request->ip(), $request->userAgent());

            $customer = $deduplicationCheck->customer;

            return response()->json([
                'success' => true,
                'data' => [
                    'customer' => [
                        'name' => $customer->firstName . ' ' . $customer->lastName,
                        'email' => $customer->email,
                        'phone' => $customer->cellphone
                    ],
                    'token' => $token,
                    'expires_at' => $deduplicationCheck->link_expires_at->toISOString(),
                    'status' => 'active'
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to access bank statement link: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to access link',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verify OTP for bank statement upload
     */
    public function verifyOtp(Request $request, string $token): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'otp_code' => 'required|string|size:6'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $deduplicationCheck = DeduplicationChecks::where('unique_access_token', $token)
                ->with(['customer'])
                ->first();

            if (!$deduplicationCheck) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired token'
                ], 404);
            }

            if ($deduplicationCheck->status === 'expired' || $deduplicationCheck->link_expires_at < Carbon::now()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token has expired'
                ], 410);
            }

            // Check OTP
            if ($deduplicationCheck->otp_code !== $request->otp_code) {
                $deduplicationCheck->increment('otp_attempts');
                
                if ($deduplicationCheck->otp_attempts >= 3) {
                    $deduplicationCheck->update(['status' => 'expired']);
                    return response()->json([
                        'success' => false,
                        'message' => 'Maximum OTP attempts exceeded'
                    ], 429);
                }

                return response()->json([
                    'success' => false,
                    'message' => 'Invalid OTP code'
                ], 400);
            }

            if ($deduplicationCheck->otp_expires_at < Carbon::now()) {
                return response()->json([
                    'success' => false,
                    'message' => 'OTP has expired'
                ], 400);
            }

            // Update OTP verification
            $deduplicationCheck->update([
                'otp_verified_at' => Carbon::now(),
                'status' => 'otp_verified'
            ]);

            $customer = $deduplicationCheck->customer;

            return response()->json([
                'success' => true,
                'message' => 'OTP verified successfully',
                'data' => [
                    'customer' => [
                        'name' => $customer->firstName . ' ' . $customer->lastName,
                        'masked_phone' => $this->maskPhoneNumber($customer->cellphone),
                        'masked_email' => $this->maskEmail($customer->email)
                    ],
                    'token' => $token,
                    'otp_sent' => true
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to verify OTP: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to verify OTP',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send OTP for bank statement upload
     */
    public function sendOtp(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string|exists:deduplication_checks,unique_access_token',
            'methods' => 'required|array|min:1',
            'methods.*' => 'in:email,sms,whatsapp',
            'cellphone' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $deduplicationCheck = DeduplicationChecks::where('unique_access_token', $request->token)
                ->with(['customer'])
                ->first();

            if (!$deduplicationCheck) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired token'
                ], 404);
            }

            if ($deduplicationCheck->status === 'expired' || $deduplicationCheck->link_expires_at < Carbon::now()) {
                $deduplicationCheck->update(['status' => 'expired']);
                return response()->json([
                    'success' => false,
                    'message' => 'Token has expired'
                ], 410);
            }

            // Generate new OTP
            $otp = new OTP();
            $otp_response = $otp->OTPStore($deduplicationCheck->customer->cellphone);
            $otp_data = $otp_response->getData();
            $otp_code = $otp_data->otp_code;

            // Update the deduplication check with new OTP
            $deduplicationCheck->update([
                'otp_code' => $otp_code,
                'otp_attempts' => 0,
                'otp_sent_at' => Carbon::now(),
                'otp_expires_at' => Carbon::now()->addMinutes(10),
                'status' => 'otp_sent'
            ]);

            $customer = $deduplicationCheck->customer;
            $deliveryMethods = [];
            $errors = [];

            // Send OTP via requested methods
            foreach ($request->methods as $method) {
                try {
                    switch ($method) {
                        case 'sms':
                            if ($customer->cellphone) {
                                $sms_status = event(new \AlphaDirect\Events\SendSms(
                                    '+267' . $customer->cellphone, 
                                    'Alpha Direct, Your Bank Statement Upload Verification Code is: ' . $otp_code
                                ));
                                $deliveryMethods[] = 'SMS';
                            }
                            break;

                        case 'whatsapp':
                            if ($customer->cellphone) {
                                $whatsappData = [
                                    "type" => "template",
                                    "subType" => "policy_create_otp",
                                    "mobileNumber" => '267' . $customer->cellphone,
                                    "policyOtp" => $otp_code,
                                    "policyNumber" => null,
                                    "customer_id" => $customer->id
                                ];
                                
                                $whatsappController = new WhatsAppController();
                                $whatsappController->sendMessage($whatsappData);
                                $deliveryMethods[] = 'WhatsApp';
                            }
                            break;

                        case 'email':
                            if ($customer->email) {
                                $data = new \stdClass();
                                $data->user_id = $otp_data->id;
                                $data->customer_id = $customer->id;
                                $data->hook = 'otp_mail';
                                $data->attachment = null;
                                
                                $emailTemplate = EmailBroadcasting::where('hook_slug', $data->hook)->first(['subject']);
                                if ($emailTemplate) {
                                    $markdown = new MailTemplate($data);
                                    $html = $markdown->render('Mail.mailTemplate', ['data' => $data]);
                                    event(new \AlphaDirect\Events\SendMail(
                                        $customer->email,
                                        $emailTemplate->subject,
                                        "",
                                        $html,
                                        null,
                                        ['hook' => $data->hook]
                                    ));
                                    $deliveryMethods[] = 'Email';
                                }
                            }
                            break;
                    }
                } catch (\Exception $e) {
                    $errors[] = "Failed to send via {$method}: " . $e->getMessage();
                    Log::error("Failed to send OTP via {$method}: " . $e->getMessage());
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'OTP sent successfully',
                'data' => [
                    'token' => $request->token,
                    'methods' => $deliveryMethods,
                    'status' => 'sent',
                    'expires_in' => 600
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send OTP: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to send OTP',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get upload requirements for bank statement
     */
    public function getUploadRequirements(Request $request, string $token): JsonResponse
    {
        try {
            $deduplicationCheck = DeduplicationChecks::where('unique_access_token', $token)
                ->with(['customer'])
                ->first();

            if (!$deduplicationCheck) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired token'
                ], 404);
            }

            if ($deduplicationCheck->status === 'expired' || $deduplicationCheck->link_expires_at < Carbon::now()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token has expired'
                ], 410);
            }

            $customer = $deduplicationCheck->customer;

            return response()->json([
                'success' => true,
                'data' => [
                    'customer' => [
                        'name' => $customer->firstName . ' ' . $customer->lastName,
                        'customer_id' => 'CUST' . $customer->id
                    ],
                    'upload_requirements' => [
                        'max_file_size' => 10485760, // 10MB
                        'accepted_formats' => ['pdf', 'jpg', 'jpeg', 'png'],
                        'required_fields' => ['bank_name', 'account_number', 'statement_period']
                    ],
                    'token' => $token
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get upload requirements: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to get upload requirements',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload bank statement
     */
    public function uploadBankStatement(Request $request, string $token = null): JsonResponse
    {
        // Get token from request body if not provided in URL
        $token = $token ?: $request->input('token');
        
        $validator = Validator::make($request->all(), [
            'token' => 'required|string|exists:deduplication_checks,unique_access_token',
            'bank_name' => 'required|string|max:255',
            'account_number' => 'required|string|max:50',
            'statement_period' => 'required|string|max:255',
            'bank_statement' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240' // 10MB max
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $deduplicationCheck = DeduplicationChecks::where('unique_access_token', $token)
                ->with(['customer'])
                ->first();

            if (!$deduplicationCheck) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired token'
                ], 404);
            }

            if ($deduplicationCheck->status === 'expired' || $deduplicationCheck->link_expires_at < Carbon::now()) {
                $deduplicationCheck->update(['status' => 'expired']);
                return response()->json([
                    'success' => false,
                    'message' => 'Token has expired'
                ], 410);
            }

            if ($deduplicationCheck->status === 'completed') {
                return response()->json([
                    'success' => false,
                    'message' => 'Document already uploaded'
                ], 409);
            }

            // if ($deduplicationCheck->status !== 'otp_verified') {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'OTP must be verified before uploading'
            //     ], 400);
            // }

            $file = $request->file('bank_statement');
            
            // Check if file is actually uploaded (not empty object)
            if (!$file || !$file->isValid()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or missing bank statement file'
                ], 400);
            }

            $timestamp = time();
            $filename = $token . '_bank_statement_' . $timestamp . '.' . $file->getClientOriginalExtension();
            $path = 'bank-statements/' . date('Y/m') . '/' . $filename;
            
            // Upload to S3
            Storage::disk('s3')->put($path, file_get_contents($file), 'public');
            $fileUrl = config('app.S3_BASE_URL') . '' . $path;

            // Generate upload ID
            $uploadId = 'UPLOAD' . strtoupper(substr($token, 0, 8)) . $timestamp;

            // Update deduplication check with bank statement info
            $deduplicationCheck->update([
                'bank_statement_upload_url' => $fileUrl,
                'bank_statement_file_path' => $path,
                'bank_statement_file_name' => $file->getClientOriginalName(),
                'bank_statement_mime_type' => $file->getMimeType(),
                'bank_statement_file_size' => $file->getSize(),
                'bank_statement_file_hash' => hash_file('sha256', $file->getRealPath()),
                'document_upload_status' => 'uploaded',
                'status' => 'completed',
                'bank_name' => $request->bank_name,
                'account_number' => $request->account_number,
                'statement_period' => $request->statement_period
            ]);
            
            // Log document upload activity
            $this->activityService->logDocumentUploaded($deduplicationCheck, [
                'file_name' => $file->getClientOriginalName(),
                'file_size' => $file->getSize(),
                'file_type' => $file->getMimeType(),
                'file_hash' => hash_file('sha256', $file->getRealPath()),
                'bank_name' => $request->bank_name,
                'account_number' => $request->account_number,
                'statement_period' => $request->statement_period
            ]);
            
           $Customerkyc = KYC::where('customer_id', $deduplicationCheck->customer->id)->first();
            if($Customerkyc){
                $Customerkyc->update(['bank_statement_file_path' => $path]);
            }
            return response()->json([
                'success' => true,
                'message' => 'Bank statement uploaded successfully',
                'data' => [
                    'upload_id' => $uploadId,
                    'file_path' => $path,
                    'file_size' => $file->getSize(),
                    'file_type' => $file->getMimeType(),
                    'processed' => false,
                    'status' => 'uploaded',
                    'bank_name' => $request->bank_name,
                    'account_number' => $request->account_number,
                    'statement_period' => $request->statement_period
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to upload bank statement: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload bank statement',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get completion status
     */
    public function getCompletionStatus(Request $request, string $token): JsonResponse
    {
        try {
            $deduplicationCheck = DeduplicationChecks::where('unique_access_token', $token)
                ->with(['customer'])
                ->first();

            if (!$deduplicationCheck) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired token'
                ], 404);
            }

            $customer = $deduplicationCheck->customer;
            $uploadId = 'UPLOAD' . strtoupper(substr($token, 0, 8)) . strtotime($deduplicationCheck->created_at);

            return response()->json([
                'success' => true,
                'data' => [
                    'customer_name' => $customer->firstName . ' ' . $customer->lastName,
                    'upload_id' => $uploadId,
                    'status' => $deduplicationCheck->document_upload_status,
                    'uploaded_at' => $deduplicationCheck->updated_at->toISOString(),
                    'processed_at' => $deduplicationCheck->document_upload_status === 'processed' ? $deduplicationCheck->updated_at->toISOString() : null,
                    'file_url' => $deduplicationCheck->bank_statement_upload_url
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get completion status: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to get completion status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mask phone number for display
     */
    private function maskPhoneNumber(string $phoneNumber): string
    {
        if (strlen($phoneNumber) < 4) {
            return str_repeat('*', strlen($phoneNumber));
        }
        
        return substr($phoneNumber, 0, 2) . str_repeat('*', strlen($phoneNumber) - 4) . substr($phoneNumber, -2);
    }

    /**
     * Mask email for display
     */
    private function maskEmail(string $email): string
    {
        if (strpos($email, '@') === false) {
            return $email;
        }
        
        list($local, $domain) = explode('@', $email);
        if (strlen($local) <= 2) {
            return str_repeat('*', strlen($local)) . '@' . $domain;
        }
        
        return substr($local, 0, 1) . str_repeat('*', strlen($local) - 2) . substr($local, -1) . '@' . $domain;
    }
}
