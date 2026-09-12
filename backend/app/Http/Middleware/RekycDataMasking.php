<?php

namespace AlphaDirect\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use AlphaDirect\Services\RekycSecurityService;

class RekycDataMasking
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
        $response = $next($request);

        try {
            // Apply data masking to response for non-admin users
            if ($this->shouldMaskData($request)) {
                $this->maskResponseData($response);
            }
        } catch (\Exception $e) {
            // Don't break the response if masking fails
            \Illuminate\Support\Facades\Log::warning('PII masking failed: ' . $e->getMessage());
        }

        return $response;
    }

    /**
     * Determine if data should be masked
     */
    private function shouldMaskData(Request $request)
    {
        // Don't mask for admin users or internal API calls
        if (auth()->check() && auth()->user()->hasRole('admin')) {
            return false;
        }

        // Don't mask for internal API calls (check for API key or internal header)
        if ($request->hasHeader('X-Internal-API-Key') || 
            $request->hasHeader('X-Internal-Request')) {
            return false;
        }

        // Mask for all other requests
        return true;
    }

    /**
     * Apply data masking to response
     */
    private function maskResponseData($response)
    {
        if ($response->headers->get('content-type') !== 'application/json') {
            return;
        }

        $content = $response->getContent();
        $data = json_decode($content, true);

        if (is_array($data)) {
            $maskedData = $this->maskSensitiveFields($data);
            $response->setContent(json_encode($maskedData));
        }
    }

    /**
     * Mask sensitive fields in data
     */
    private function maskSensitiveFields($data)
    {
        if (!is_array($data)) {
            return $data;
        }

        $masked = $data;
        $sensitiveFields = [
            'omang' => 'omang',
            'passport' => 'passport',
            'cellphone' => 'phone',
            'email' => 'email',
            'firstName' => 'name',
            'lastName' => 'name',
            'phone' => 'phone',
            'mobile' => 'phone',
        ];

        foreach ($sensitiveFields as $field => $maskType) {
            if (isset($masked[$field])) {
                $masked[$field] = $this->securityService->maskSensitiveData($masked[$field], $maskType);
            }
        }

        // Process nested arrays and objects
        foreach ($masked as $key => $value) {
            if (is_array($value)) {
                $masked[$key] = $this->maskSensitiveFields($value);
            }
        }

        return $masked;
    }
}
