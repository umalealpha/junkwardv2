<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use Laravel\Sanctum\Sanctum;

class ReconciliationTest extends TestCase
{
    private function authenticateUser()
    {
        $user = \AlphaDirect\User::first();
        if (!$user) {
            $this->markTestSkipped('No users in database');
        }
        Sanctum::actingAs($user, ['*']);
        return $user;
    }

    public function test_reconciliation_summary_requires_auth()
    {
        $response = $this->getJson('/api/v1/reconciliation/summary');
        $response->assertStatus(401);
    }

    public function test_reconciliation_summary_returns_data()
    {
        $this->authenticateUser();

        $response = $this->getJson('/api/v1/reconciliation/summary');

        $response->assertStatus(200);
    }

    public function test_reconciliation_anomalies_returns_data()
    {
        $this->authenticateUser();

        $response = $this->getJson('/api/v1/reconciliation/anomalies');

        $response->assertStatus(200);
    }
}
