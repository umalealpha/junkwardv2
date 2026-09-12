<?php

namespace AlphaDirect\Http\Middleware;

/**
 * Tracks outgoing HTTP requests (DPO, Realpay, Infobip, etc.) to MongoDB.
 *
 * Usage with Guzzle:
 *   $client = new \GuzzleHttp\Client([
 *       'handler' => TrackOutgoingRequest::addToStack(\GuzzleHttp\HandlerStack::create()),
 *   ]);
 *
 * Or call directly:
 *   TrackOutgoingRequest::log($method, $url, $requestBody, $responseBody, $statusCode, $durationMs);
 */
class TrackOutgoingRequest
{
    /**
     * Log an outgoing HTTP request to MongoDB
     */
    public static function log(
        string $method,
        string $url,
        $requestBody = null,
        $responseBody = null,
        int $statusCode = 0,
        float $durationMs = 0,
        array $headers = []
    ): void {
        try {
            $config = config('tracking');

            if (!$config['enabled'] || !$config['track_outgoing']) {
                return;
            }

            // Check if host is in the tracked list
            $host = parse_url($url, PHP_URL_HOST);
            $tracked = false;
            foreach ($config['outgoing_hosts'] as $trackedHost) {
                if (stripos($host, $trackedHost) !== false) {
                    $tracked = true;
                    break;
                }
            }

            if (!$tracked) {
                return;
            }

            $mongoHost = $config['mongodb_host'];
            $mongoPort = $config['mongodb_port'];
            $mongoDb = $config['mongodb_database'];

            // Redact sensitive fields
            $redactFields = $config['redact_fields'];
            $requestData = is_string($requestBody) ? $requestBody : json_encode($requestBody);
            foreach ($redactFields as $field) {
                $requestData = preg_replace("/{$field}\"?\s*[:=]\s*\"?[^\"&,}]+/i", "{$field}:***REDACTED***", $requestData ?? '');
            }

            // Truncate response
            $maxSize = $config['max_response_size'];
            $responseData = is_string($responseBody) ? $responseBody : json_encode($responseBody);
            if (strlen($responseData) > $maxSize) {
                $responseData = substr($responseData, 0, $maxSize) . '...[truncated]';
            }

            $document = [
                'timestamp' => new \MongoDB\BSON\UTCDateTime(now()->getTimestamp() * 1000),
                'direction' => 'outgoing',
                'method' => strtoupper($method),
                'url' => $url,
                'host' => $host,
                'status_code' => $statusCode,
                'duration_ms' => round($durationMs),
                'request_body' => $requestData,
                'response_body' => $responseData,
                'response_size_bytes' => strlen($responseData ?? ''),
                'request_headers' => $headers,
                'server' => gethostname(),
                'initiated_by_user' => auth()->id(),
            ];

            $manager = new \MongoDB\Driver\Manager("mongodb://{$mongoHost}:{$mongoPort}");
            $bulk = new \MongoDB\Driver\BulkWrite();
            $bulk->insert($document);
            $manager->executeBulkWrite("{$mongoDb}.api_requests_outgoing", $bulk);

        } catch (\Throwable $e) {
            if (config('app.debug')) {
                \Log::warning('Outgoing tracking failed: ' . $e->getMessage());
            }
        }
    }

    /**
     * Add tracking middleware to a Guzzle HandlerStack
     */
    public static function addToStack(\GuzzleHttp\HandlerStack $stack): \GuzzleHttp\HandlerStack
    {
        $stack->push(function (callable $handler) {
            return function ($request, array $options) use ($handler) {
                $startTime = microtime(true);

                return $handler($request, $options)->then(
                    function ($response) use ($request, $startTime) {
                        $durationMs = (microtime(true) - $startTime) * 1000;

                        self::log(
                            $request->getMethod(),
                            (string) $request->getUri(),
                            (string) $request->getBody(),
                            (string) $response->getBody(),
                            $response->getStatusCode(),
                            $durationMs,
                            $request->getHeaders()
                        );

                        // Rewind response body so it can be read again
                        $response->getBody()->rewind();

                        return $response;
                    }
                );
            };
        }, 'track_outgoing');

        return $stack;
    }
}
