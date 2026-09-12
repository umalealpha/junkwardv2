<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

/**
 * Security-critical: the Swiftly webhook is publicly reachable and is
 * authenticated solely by the HMAC-SHA256 X-Webhook-Signature. These tests
 * lock in the fail-closed behaviour of VerifySwiftlySignature.
 *
 * The rejection paths (missing/invalid signature, unconfigured secret) never
 * reach the DB, so they run anywhere. The accepted path returns 200 even if
 * the events table is absent (the controller swallows storage errors so a
 * verified provider never gets a retry-triggering 500).
 */
class SwiftlyWebhookTest extends TestCase
{
    private string $secret = 'test-webhook-secret-123';
    private string $url = '/api/v1/webhooks/swiftly/early-payment';

    private function sign(string $body): string
    {
        return hash_hmac('sha256', $body, $this->secret);
    }

    public function test_rejects_when_secret_not_configured(): void
    {
        config(['services.swiftly.webhook_secret' => null]);

        $response = $this->call('POST', $this->url, [], [], [], [], json_encode(['event_id' => 'e1']));

        $response->assertStatus(500);
    }

    public function test_rejects_missing_signature(): void
    {
        config(['services.swiftly.webhook_secret' => $this->secret]);

        $response = $this->call('POST', $this->url, [], [], [], [], json_encode(['event_id' => 'e1']));

        $response->assertStatus(401);
    }

    public function test_rejects_invalid_signature(): void
    {
        config(['services.swiftly.webhook_secret' => $this->secret]);
        $body = json_encode(['event_id' => 'e1', 'event_type' => 'early_payment']);

        $response = $this->call(
            'POST',
            $this->url,
            [],
            [],
            [],
            ['HTTP_X-Webhook-Signature' => 'deadbeef'],
            $body
        );

        $response->assertStatus(401);
    }

    public function test_accepts_valid_signature(): void
    {
        config(['services.swiftly.webhook_secret' => $this->secret]);
        $body = json_encode(['event_id' => 'evt_test_1', 'event_type' => 'early_payment']);

        $response = $this->call(
            'POST',
            $this->url,
            [],
            [],
            [],
            ['HTTP_X-Webhook-Signature' => $this->sign($body)],
            $body
        );

        $response->assertStatus(200);
    }

    public function test_integration_toggle_requires_authentication(): void
    {
        // The PUT toggle lives behind auth:sanctum — unauthenticated callers
        // must never be able to flip an integration on/off.
        $response = $this->putJson('/api/v1/integrations/swiftly', ['enabled' => true]);

        $response->assertStatus(401);
    }

    public function test_signature_is_computed_over_raw_body(): void
    {
        // A signature over a different body (even semantically equal) must fail —
        // proves we verify the raw bytes, not a re-encoded payload.
        config(['services.swiftly.webhook_secret' => $this->secret]);
        $sentBody = json_encode(['event_id' => 'e1', 'a' => 1]);
        $otherBody = json_encode(['a' => 1, 'event_id' => 'e1']); // same data, different bytes

        $response = $this->call(
            'POST',
            $this->url,
            [],
            [],
            [],
            ['HTTP_X-Webhook-Signature' => $this->sign($otherBody)],
            $sentBody
        );

        $response->assertStatus(401);
    }
}
