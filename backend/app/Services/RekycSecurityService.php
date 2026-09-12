<?php

namespace AlphaDirect\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Exception;

class RekycSecurityService
{
    /**
     * Encrypt sensitive data using AES-256
     */
    public function encryptData($data)
    {
        if (is_array($data)) {
            return $this->encryptArray($data);
        }
        
        if (is_string($data)) {
            return Crypt::encryptString($data);
        }
        
        return $data;
    }

    /**
     * Decrypt sensitive data
     */
    public function decryptData($encryptedData)
    {
        if (is_array($encryptedData)) {
            return $this->decryptArray($encryptedData);
        }
        
        if (is_string($encryptedData)) {
            try {
                return Crypt::decryptString($encryptedData);
            } catch (Exception $e) {
                return $encryptedData; // Return original if decryption fails
            }
        }
        
        return $encryptedData;
    }

    /**
     * Encrypt array of data
     */
    private function encryptArray(array $data)
    {
        $encrypted = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $encrypted[$key] = $this->encryptArray($value);
            } else {
                $encrypted[$key] = $this->encryptData($value);
            }
        }
        return $encrypted;
    }

    /**
     * Decrypt array of data
     */
    private function decryptArray(array $data)
    {
        $decrypted = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $decrypted[$key] = $this->decryptArray($value);
            } else {
                $decrypted[$key] = $this->decryptData($value);
            }
        }
        return $decrypted;
    }

    /**
     * Mask sensitive identifiers for display
     */
    public function maskSensitiveData($data, $type = 'default')
    {
        switch ($type) {
            case 'omang':
                return $this->maskOmang($data);
            case 'passport':
                return $this->maskPassport($data);
            case 'phone':
                return $this->maskPhoneNumber($data);
            case 'email':
                return $this->maskEmail($data);
            case 'name':
                return $this->maskName($data);
            default:
                return $this->maskDefault($data);
        }
    }

    /**
     * Mask Omang number (Botswana national ID)
     */
    private function maskOmang($omang)
    {
        if (empty($omang) || strlen($omang) < 4) {
            return $omang;
        }
        
        // Show first 2 and last 2 characters
        return substr($omang, 0, 2) . str_repeat('*', strlen($omang) - 4) . substr($omang, -2);
    }

    /**
     * Mask passport number
     */
    private function maskPassport($passport)
    {
        if (empty($passport) || strlen($passport) < 4) {
            return $passport;
        }
        
        // Show first 2 and last 2 characters
        return substr($passport, 0, 2) . str_repeat('*', strlen($passport) - 4) . substr($passport, -2);
    }

    /**
     * Mask phone number
     */
    private function maskPhoneNumber($phone)
    {
        if (empty($phone) || strlen($phone) < 4) {
            return $phone;
        }
        
        // Show first 3 and last 3 characters
        return substr($phone, 0, 3) . str_repeat('*', strlen($phone) - 6) . substr($phone, -3);
    }

    /**
     * Mask email address
     */
    private function maskEmail($email)
    {
        if (empty($email) || strpos($email, '@') === false) {
            return $email;
        }
        
        list($local, $domain) = explode('@', $email);
        $maskedLocal = substr($local, 0, 2) . str_repeat('*', strlen($local) - 2);
        
        return $maskedLocal . '@' . $domain;
    }

    /**
     * Mask name
     */
    private function maskName($name)
    {
        if (empty($name) || strlen($name) < 3) {
            return $name;
        }
        
        // Show first character and mask the rest
        return substr($name, 0, 1) . str_repeat('*', strlen($name) - 1);
    }

    /**
     * Default masking for unknown data types
     */
    private function maskDefault($data)
    {
        if (empty($data) || strlen($data) < 4) {
            return $data;
        }
        
        // Show first 2 and last 2 characters
        return substr($data, 0, 2) . str_repeat('*', strlen($data) - 4) . substr($data, -2);
    }

    /**
     * Hash sensitive data for comparison (one-way)
     */
    public function hashSensitiveData($data)
    {
        if (is_array($data)) {
            return $this->hashArray($data);
        }
        
        return Hash::make($data);
    }

    /**
     * Hash array of data
     */
    private function hashArray(array $data)
    {
        $hashed = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $hashed[$key] = $this->hashArray($value);
            } else {
                $hashed[$key] = $this->hashSensitiveData($value);
            }
        }
        return $hashed;
    }

    /**
     * Generate secure token for access
     */
    public function generateSecureToken($length = 64)
    {
        return Str::random($length);
    }

    /**
     * Generate OTP code
     */
    public function generateOTP($length = 6)
    {
        return str_pad(random_int(0, pow(10, $length) - 1), $length, '0', STR_PAD_LEFT);
    }

    /**
     * Validate data integrity
     */
    public function validateDataIntegrity($originalData, $currentData)
    {
        // Remove timestamps and IDs for comparison
        $cleanOriginal = $this->cleanDataForComparison($originalData);
        $cleanCurrent = $this->cleanDataForComparison($currentData);
        
        return $cleanOriginal === $cleanCurrent;
    }

    /**
     * Clean data for comparison by removing timestamps and IDs
     */
    private function cleanDataForComparison($data)
    {
        if (is_array($data)) {
            $cleaned = $data;
            unset($cleaned['id'], $cleaned['created_at'], $cleaned['updated_at'], $cleaned['deleted_at']);
            
            foreach ($cleaned as $key => $value) {
                if (is_array($value)) {
                    $cleaned[$key] = $this->cleanDataForComparison($value);
                }
            }
            
            return $cleaned;
        }
        
        return $data;
    }

    /**
     * Sanitize input data
     */
    public function sanitizeInput($data)
    {
        if (is_array($data)) {
            return $this->sanitizeArray($data);
        }
        
        if (is_string($data)) {
            return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
        }
        
        return $data;
    }

    /**
     * Sanitize array of data
     */
    private function sanitizeArray(array $data)
    {
        $sanitized = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $sanitized[$key] = $this->sanitizeArray($value);
            } else {
                $sanitized[$key] = $this->sanitizeInput($value);
            }
        }
        return $sanitized;
    }

    /**
     * Check if data contains PII (Personally Identifiable Information)
     */
    public function containsPII($data)
    {
        $piiPatterns = [
            '/\b\d{9}\b/', // 9-digit numbers (like Omang)
            '/\b[A-Z]{2}\d{6}\b/', // Passport format
            '/\b\d{3}-\d{3}-\d{4}\b/', // Phone number format
            '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/', // Email
        ];
        
        $dataString = is_array($data) ? json_encode($data) : (string)$data;
        
        foreach ($piiPatterns as $pattern) {
            if (preg_match($pattern, $dataString)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Apply DPA 2018 compliance measures
     */
    public function applyDPACompliance($data)
    {
        // Remove or mask sensitive data based on DPA 2018 requirements
        $compliantData = $data;
        
        if (is_array($data)) {
            $sensitiveFields = ['omang', 'passport', 'cellphone', 'email', 'firstName', 'lastName'];
            
            foreach ($sensitiveFields as $field) {
                if (isset($compliantData[$field])) {
                    $compliantData[$field] = $this->maskSensitiveData($compliantData[$field], $field);
                }
            }
        }
        
        return $compliantData;
    }

    /**
     * Generate audit hash for data integrity verification
     */
    public function generateAuditHash($data)
    {
        $dataString = is_array($data) ? json_encode($data, JSON_SORT_KEYS) : (string)$data;
        return hash('sha256', $dataString . config('app.key'));
    }

    /**
     * Verify audit hash
     */
    public function verifyAuditHash($data, $expectedHash)
    {
        $actualHash = $this->generateAuditHash($data);
        return hash_equals($expectedHash, $actualHash);
    }
}
