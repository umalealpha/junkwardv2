<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    public function test_health_endpoint_returns_ok()
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertStatus(200)
            ->assertJsonStructure(['status', 'db', 'time'])
            ->assertJson(['status' => 'ok', 'db' => 'connected']);
    }

    public function test_app_boots_without_errors()
    {
        $response = $this->get('/');

        // Should not be 500 (server error)
        $this->assertNotEquals(500, $response->getStatusCode());
    }
}
