<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use Laravel\Sanctum\Sanctum;

class ReinsuranceTest extends TestCase
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

    public function test_reinsurance_types_requires_auth()
    {
        $response = $this->getJson('/api/v1/reinsurance/types');
        $response->assertStatus(401);
    }

    public function test_reinsurance_types_returns_data()
    {
        $this->authenticateUser();

        $response = $this->getJson('/api/v1/reinsurance/types');
        $response->assertStatus(200);
    }

    public function test_reinsurance_treaties_returns_data()
    {
        $this->authenticateUser();

        $response = $this->getJson('/api/v1/reinsurance/treaties');
        $response->assertStatus(200);
    }

    public function test_reinsurance_formulas_returns_data()
    {
        $this->authenticateUser();

        $response = $this->getJson('/api/v1/reinsurance/formulas');
        $response->assertStatus(200);
    }

    public function test_reinsurance_coverage_groups_returns_data()
    {
        $this->authenticateUser();

        $response = $this->getJson('/api/v1/reinsurance/coverage-groups');
        $response->assertStatus(200);
    }
}
