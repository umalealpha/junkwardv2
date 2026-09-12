<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use Laravel\Sanctum\Sanctum;

class DashboardTest extends TestCase
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

    public function test_dashboard_stats_requires_auth()
    {
        $response = $this->getJson('/api/v1/dashboard/stats');
        $response->assertStatus(401);
    }

    public function test_dashboard_stats_returns_data()
    {
        $this->authenticateUser();

        $response = $this->getJson('/api/v1/dashboard/stats');

        $response->assertStatus(200);
    }

    public function test_dashboard_finance_returns_data()
    {
        $this->authenticateUser();

        $response = $this->getJson('/api/v1/dashboard/finance');

        $response->assertStatus(200);
    }
}
