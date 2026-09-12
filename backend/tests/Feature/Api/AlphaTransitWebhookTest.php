<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

/**
 * Security-critical: the Alpha Transit webhook is publicly reachable and is
 * authenticated solely by the shared bearer (VerifyAlphaTransitToken). These
 * tests lock in the fail-closed behaviour plus the contract-level rejection
 * codes from the Integration Reply §05 (invalid_product, malformed_payload).
 *
 * Every case here is answered before the handler touches the database
 * (auth → integration toggle → product gate → envelope validation), so the
 * suite runs anywhere. The ingestion happy paths write to the legacy policy
 * tables and are exercised in the sandbox copy-test instead (Brief §10).
 */
class AlphaTransitWebhookTest extends TestCase
{
    private string $token = 'test-atc-bearer-123';
    private string $url = '/api/v1/webhooks/alpha-transit/event';

    /** @return array<string, string> */
    private function headers(array $extra = []): array
    {
        return array_merge([
            'HTTP_AUTHORIZATION'  => 'Bearer ' . $this->token,
            'HTTP_X-Product-Code' => 'ATC',
            'CONTENT_TYPE'        => 'application/json',
        ], $extra);
    }

    private function postEvent(array $body, array $headers): \Illuminate\Testing\TestResponse
    {
        return $this->call('POST', $this->url, [], [], [], $headers, json_encode($body));
    }

    public function test_rejects_when_token_not_configured(): void
    {
        config(['services.alpha_transit.webhook_token' => null]);

        $response = $this->postEvent(['event_type' => 'policy.created'], ['CONTENT_TYPE' => 'application/json']);

        $response->assertStatus(500);
    }

    public function test_rejects_missing_bearer(): void
    {
        config(['services.alpha_transit.webhook_token' => $this->token]);

        $response = $this->postEvent(['event_type' => 'policy.created'], ['CONTENT_TYPE' => 'application/json']);

        $response->assertStatus(401);
    }

    public function test_rejects_invalid_bearer(): void
    {
        config(['services.alpha_transit.webhook_token' => $this->token]);

        $response = $this->postEvent(
            ['event_type' => 'policy.created'],
            ['HTTP_AUTHORIZATION' => 'Bearer wrong-token', 'CONTENT_TYPE' => 'application/json']
        );

        $response->assertStatus(401);
    }

    public function test_answers_503_while_integration_disabled(): void
    {
        config([
            'services.alpha_transit.webhook_token' => $this->token,
            'services.alpha_transit.enabled'       => false,
        ]);

        $response = $this->postEvent(['event_type' => 'policy.created'], $this->headers());

        // 503 = the ATC retry queue holds the event; nothing is lost while off.
        $response->assertStatus(503)->assertJson(['error' => 'integration_disabled']);
    }

    public function test_rejects_wrong_product_code(): void
    {
        config([
            'services.alpha_transit.webhook_token' => $this->token,
            'services.alpha_transit.enabled'       => true,
        ]);

        $response = $this->postEvent(
            ['event_type' => 'policy.created', 'product_code' => 'MOT'],
            $this->headers(['HTTP_X-Product-Code' => 'MOT'])
        );

        // Risk gate 1 — nothing that isn't tagged ATC may be ingested.
        $response->assertStatus(400)->assertJson(['error' => 'invalid_product']);
    }

    public function test_rejects_unknown_event_type(): void
    {
        config([
            'services.alpha_transit.webhook_token' => $this->token,
            'services.alpha_transit.enabled'       => true,
        ]);

        $response = $this->postEvent(
            ['event_type' => 'policy.exploded', 'product_code' => 'ATC'],
            $this->headers()
        );

        $response->assertStatus(422)->assertJson(['error' => 'malformed_payload']);
    }

    public function test_rejects_missing_idempotency_key(): void
    {
        config([
            'services.alpha_transit.webhook_token' => $this->token,
            'services.alpha_transit.enabled'       => true,
        ]);

        $response = $this->postEvent(
            ['event_type' => 'policy.created', 'product_code' => 'ATC', 'payload' => []],
            $this->headers()
        );

        $response->assertStatus(422)->assertJson(['error' => 'malformed_payload']);
    }

    public function test_rejects_non_object_payload(): void
    {
        config([
            'services.alpha_transit.webhook_token' => $this->token,
            'services.alpha_transit.enabled'       => true,
        ]);

        $response = $this->postEvent(
            [
                'event_type'      => 'policy.created',
                'product_code'    => 'ATC',
                'idempotency_key' => 'atc-evt-test-1',
                'payload'         => 'not-an-object',
            ],
            $this->headers()
        );

        $response->assertStatus(422)->assertJson(['error' => 'malformed_payload']);
    }
}
