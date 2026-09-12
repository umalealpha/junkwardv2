<?php

namespace AlphaDirect\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use AlphaDirect\Services\RekycSecurityService;

class RekycDataEncryption
{
    protected $securityService;

    public function __construct(RekycSecurityService $securityService)
    {
        $this->securityService = $securityService;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Encrypt sensitive data before processing
        if ($request->isMethod('post') || $request->isMethod('put') || $request->isMethod('patch')) {
            $this->encryptRequestData($request);
        }

        $response = $next($request);

        // Decrypt sensitive data in response
        if ($response->headers->get('content-type') === 'application/json') {
            $this->decryptResponseData($response);
        }

        return $response;
    }

    /**
     * Encrypt sensitive data in request
     */
    private function encryptRequestData(Request $request)
    {
        $sensitiveFields = [
            'omang',
            'passport',
            'cellphone',
            'email',
            'firstName',
            'lastName',
            'kyc_data',
            'consent_data'
        ];

        $data = $request->all();
        $encryptedData = $this->processSensitiveFields($data, $sensitiveFields, 'encrypt');
        
        if ($encryptedData !== $data) {
            $request->replace($encryptedData);
        }
    }

    /**
     * Decrypt sensitive data in response
     */
    private function decryptResponseData($response)
    {
        $content = $response->getContent();
        $data = json_decode($content, true);

        if (is_array($data)) {
            $sensitiveFields = [
                'omang',
                'passport',
                'cellphone',
                'email',
                'firstName',
                'lastName',
                'kyc_data',
                'consent_data'
            ];

            $decryptedData = $this->processSensitiveFields($data, $sensitiveFields, 'decrypt');
            
            if ($decryptedData !== $data) {
                $response->setContent(json_encode($decryptedData));
            }
        }
    }

    /**
     * Process sensitive fields for encryption/decryption
     */
    private function processSensitiveFields($data, $sensitiveFields, $operation)
    {
        if (!is_array($data)) {
            return $data;
        }

        $processed = $data;

        foreach ($sensitiveFields as $field) {
            if (isset($processed[$field])) {
                if ($operation === 'encrypt') {
                    $processed[$field] = $this->securityService->encryptData($processed[$field]);
                } else {
                    $processed[$field] = $this->securityService->decryptData($processed[$field]);
                }
            }
        }

        // Process nested arrays
        foreach ($processed as $key => $value) {
            if (is_array($value)) {
                $processed[$key] = $this->processSensitiveFields($value, $sensitiveFields, $operation);
            }
        }

        return $processed;
    }
}
