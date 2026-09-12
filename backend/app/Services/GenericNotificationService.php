<?php

namespace AlphaDirect\Services;

use Illuminate\Support\Facades\Log;
use Exception;

class GenericNotificationService
{
    /**
     * Send email notification
     */
    public function emailSend($hook_slug, $email, $customMessage = '', $additionalData = [])
    {
        //try {
            $emailObj = new \stdClass();
            $emailObj->user_id = $additionalData['user_id'] ?? "";
            $emailObj->hook = $hook_slug;
            $emailObj->customer_id = $additionalData['customer_id'] ?? null;
            $emailObj->email = $email;
            $emailObj->attachment = $additionalData['attachment'] ?? null;
            
            // Add any additional data to the email object
            foreach ($additionalData as $key => $value) {
                if (!in_array($key, ['user_id', 'customer_id', 'attachment'])) {
                    $emailObj->$key = $value;
                }
            }
            //dd($emailObj);
            // Add custom message
            $emailObj->custom_message = $customMessage ?? '';
            
            // Get email template
            $emailTemplate = \AlphaDirect\EmailBroadcasting::where('hook_slug', $emailObj->hook)->first(['subject']);
            
            if (!$emailTemplate) {
                throw new Exception("Email template not found for hook: {$hook_slug}");
            }
            
            // Render email template
            $markdown = new \AlphaDirect\Mail\MailTemplate($emailObj);
            $html = $markdown->render('Mail.mailTemplate', ['data' => $emailObj]);
            
            // Send email event
            event(new \AlphaDirect\Events\SendMail(
                $email, 
                $emailTemplate->subject, 
                "", 
                $html, 
                null, 
                [
                    'policyNumber' => $additionalData['policyNumber'] ?? '', 
                    'hook' => $emailObj->hook
                ]
            ));
            
            // Log successful email sending
            $this->logEmailSent($hook_slug, $email, $customMessage, $additionalData, 'Email sent successfully');
            
            return true;
            
        // } catch (Exception $e) {
        //     Log::error("Generic email notification error for hook {$hook_slug}: " . $e->getMessage());
            
        //     // Log email failure
        //     $this->logEmailFailed($hook_slug, $email, $customMessage, $additionalData, $e->getMessage());
            
        //     return false;
        // }
    }

    /**
     * Send SMS notification
     */
    public function smsSend($hook_slug, $cellphone, $customMessage = '', $additionalData = [])
    {
        try {
            $sms = new \AlphaDirect\Http\Controllers\SmsMessaging();
            
            // Get template ID from additional data or use default
            $templateId = $additionalData['template_id'] ?? 47; // Default template ID
            
            // Prepare SMS data
            $smsData = [
                'template_id' => $templateId,
                'phone' => $cellphone,
                'customer_name' => $additionalData['firstName'] ?? $additionalData['customer_name'] ?? 'Customer',
                'custom_message' => $customMessage,
                'hook_slug' => $hook_slug
            ];
            
            // Add any additional data for SMS
            foreach ($additionalData as $key => $value) {
                if (!in_array($key, ['template_id', 'firstName', 'customer_name'])) {
                    $smsData[$key] = $value;
                }
            }
            
            // Send SMS using the appropriate method based on hook
            $result = $this->sendSmsByHook($sms, $smsData);
            
            // Log successful SMS sending
            $this->logSmsSent($hook_slug, $cellphone, $customMessage, $additionalData, 'SMS sent successfully');
            
            return true;
            
        } catch (Exception $e) {
            Log::error("Generic SMS notification error for hook {$hook_slug}: " . $e->getMessage());
            
            // Log SMS failure
            $this->logSmsFailed($hook_slug, $cellphone, $customMessage, $additionalData, $e->getMessage());
            
            return false;
        }
    }

    /**
     * Send WhatsApp notification
     */
    public function whatsappSend($hook_slug, $cellphone, $customMessage = '', $additionalData = [])
    {
        try {
            $message = $customMessage ? $customMessage . "\n\n" : '';
            
            $whatsappController = app(\AlphaDirect\Http\Controllers\WhatsAppController::class);
            
            // Prepare WhatsApp data
            $whatsappData = [
                'mobileNumber' => $cellphone,
                'message' => $message,
                'customer_id' => $additionalData['customer_id'] ?? null,
                'policyNumber' => $additionalData['policyNumber'] ?? '',
                'delivery_method' => $additionalData['delivery_method'] ?? 'instant',
                'hook_slug' => $hook_slug
            ];
            
            // Add any additional data for WhatsApp
            foreach ($additionalData as $key => $value) {
                if (!in_array($key, ['customer_id', 'policyNumber', 'delivery_method'])) {
                    $whatsappData[$key] = $value;
                }
            }
            
            // Send WhatsApp message
            $whatsappController->sendMetaData($whatsappData);
            
            // Log successful WhatsApp sending
            $this->logWhatsappSent($hook_slug, $cellphone, $customMessage, $additionalData, 'WhatsApp message sent successfully');
            
            return true;
            
        } catch (Exception $e) {
            Log::error("Generic WhatsApp notification error for hook {$hook_slug}: " . $e->getMessage());
            
            // Log WhatsApp failure
            $this->logWhatsappFailed($hook_slug, $cellphone, $customMessage, $additionalData, $e->getMessage());
            
            return false;
        }
    }

    /**
     * Send notification via multiple channels
     */
    public function sendMultiChannel($hook_slug, $customer_id, $channels = ['email'], $customMessage = '', $additionalData = [])
    {
        $results = [];
        
        // Fetch customer data
        $customerData = $this->getCustomerData($customer_id);
       
        if (empty($customerData)) {
            Log::error("Customer data not found for ID: {$customer_id}");
            return ['error' => 'Customer not found'];
        }
        
        // Merge customer data with additional data
        $additionalData = array_merge($additionalData, $customerData);
       // dd($additionalData);
        // Get email and cellphone from customer data
        $email = $customerData['email'] ?? '';
        $cellphone = $customerData['cellphone'] ?? '';
        
        foreach ($channels as $channel) {
            switch ($channel) {
                case 'email':
                    if (!empty($email)) {
                        $results['email'] = $this->emailSend($hook_slug, $email, $customMessage, $additionalData);
                    } else {
                        $results['email'] = false;
                        Log::warning("No email found for customer ID: {$customer_id}");
                    }
                    break;
                case 'sms':
                    if (!empty($cellphone)) {
                        $results['sms'] = $this->smsSend($hook_slug, $cellphone, $customMessage, $additionalData);
                    } else {
                        $results['sms'] = false;
                        Log::warning("No cellphone found for customer ID: {$customer_id}");
                    }
                    break;
                case 'whatsapp':
                    if (!empty($cellphone)) {
                        $results['whatsapp'] = $this->whatsappSend($hook_slug, $cellphone, $customMessage, $additionalData);
                    } else {
                        $results['whatsapp'] = false;
                        Log::warning("No cellphone found for customer ID: {$customer_id}");
                    }
                    break;
            }
        }
        
        return $results;
    }

    /**
     * Get customer data by customer_id
     */
    private function getCustomerData($customer_id)
    {
        try {
            $customer = \AlphaDirect\Customer::find($customer_id);
            
            if (!$customer) {
                Log::warning("Customer not found with ID: {$customer_id}");
                return [];
            }
            
            // Return relevant customer data for notifications
            return [
                'customer_id' => $customer->id,
                'firstName' => $customer->firstName,
                'middleName' => $customer->middleName,
                'lastName' => $customer->lastName,
                'fullName' => $customer->fullName,
                'email' => $customer->email,
                'cellphone' => $customer->cellphone,
                'customer_name' => $customer->firstName . ' ' . $customer->lastName, // For SMS compatibility
            ];
            
        } catch (Exception $e) {
            Log::error("Failed to fetch customer data for ID {$customer_id}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Send SMS based on hook type
     */
    private function sendSmsByHook($sms, $smsData)
    {
        switch ($smsData['hook_slug']) {
            case 'rekyc_verification':
                return $sms->rekycVerification(
                    $smsData['template_id'],
                    $smsData['phone'],
                    $smsData['customer_name'],
                    $smsData['rekyc_url'] ?? '',
                    $smsData['rekyc_link_expiry_days'] ?? ''
                );
            case 'bank_statement_upload':
                return $sms->bankStatementUpload(
                    $smsData['template_id'],
                    $smsData['phone'],
                    $smsData['customer_name'],
                    $smsData['upload_url'] ?? '',
                    $smsData['expiry_days'] ?? ''
                );
            default:
                // Generic SMS sending
                return $sms->sendGenericSms(
                    $smsData['template_id'],
                    $smsData['phone'],
                    $smsData['customer_name'],
                    $smsData['custom_message'] ?? ''
                );
        }
    }

    /**
     * Log successful email sending
     */
    private function logEmailSent($hook_slug, $email, $customMessage, $additionalData, $message)
    {
        try {
            // You can implement your own logging service here
            Log::info("Email sent successfully", [
                'hook_slug' => $hook_slug,
                'email' => $email,
                'custom_message' => $customMessage,
                'additional_data' => $additionalData,
                'message' => $message
            ]);
        } catch (Exception $e) {
            Log::error("Failed to log email success: " . $e->getMessage());
        }
    }

    /**
     * Log email failure
     */
    private function logEmailFailed($hook_slug, $email, $customMessage, $additionalData, $error)
    {
        try {
            Log::error("Email sending failed", [
                'hook_slug' => $hook_slug,
                'email' => $email,
                'custom_message' => $customMessage,
                'additional_data' => $additionalData,
                'error' => $error
            ]);
        } catch (Exception $e) {
            Log::error("Failed to log email failure: " . $e->getMessage());
        }
    }

    /**
     * Log successful SMS sending
     */
    private function logSmsSent($hook_slug, $cellphone, $customMessage, $additionalData, $message)
    {
        try {
            Log::info("SMS sent successfully", [
                'hook_slug' => $hook_slug,
                'cellphone' => $cellphone,
                'custom_message' => $customMessage,
                'additional_data' => $additionalData,
                'message' => $message
            ]);
        } catch (Exception $e) {
            Log::error("Failed to log SMS success: " . $e->getMessage());
        }
    }

    /**
     * Log SMS failure
     */
    private function logSmsFailed($hook_slug, $cellphone, $customMessage, $additionalData, $error)
    {
        try {
            Log::error("SMS sending failed", [
                'hook_slug' => $hook_slug,
                'cellphone' => $cellphone,
                'custom_message' => $customMessage,
                'additional_data' => $additionalData,
                'error' => $error
            ]);
        } catch (Exception $e) {
            Log::error("Failed to log SMS failure: " . $e->getMessage());
        }
    }

    /**
     * Log successful WhatsApp sending
     */
    private function logWhatsappSent($hook_slug, $cellphone, $customMessage, $additionalData, $message)
    {
        try {
            Log::info("WhatsApp sent successfully", [
                'hook_slug' => $hook_slug,
                'cellphone' => $cellphone,
                'custom_message' => $customMessage,
                'additional_data' => $additionalData,
                'message' => $message
            ]);
        } catch (Exception $e) {
            Log::error("Failed to log WhatsApp success: " . $e->getMessage());
        }
    }

    /**
     * Log WhatsApp failure
     */
    private function logWhatsappFailed($hook_slug, $cellphone, $customMessage, $additionalData, $error)
    {
        try {
            Log::error("WhatsApp sending failed", [
                'hook_slug' => $hook_slug,
                'cellphone' => $cellphone,
                'custom_message' => $customMessage,
                'additional_data' => $additionalData,
                'error' => $error
            ]);
        } catch (Exception $e) {
            Log::error("Failed to log WhatsApp failure: " . $e->getMessage());
        }
    }
}
