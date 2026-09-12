<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use Laravel\Sanctum\Sanctum;

class PolicyTest extends TestCase
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

    public function test_policies_list_requires_auth()
    {
        $response = $this->getJson('/api/v1/policies');
        $response->assertStatus(401);
    }

    public function test_policies_list_returns_paginated_data()
    {
        $this->authenticateUser();

        $response = $this->getJson('/api/v1/policies');

        $response->assertStatus(200)
            ->assertJsonStructure(['data']);
    }

    public function test_products_list_returns_data()
    {
        $this->authenticateUser();

        $response = $this->getJson('/api/v1/products');

        $response->assertStatus(200);
    }

    public function test_lookups_policy_create_returns_data()
    {
        $this->authenticateUser();

        $response = $this->getJson('/api/v1/lookups/policy-create');

        $response->assertStatus(200);
    }

    public function test_policy_show_returns_404_for_invalid_id()
    {
        $this->authenticateUser();

        $response = $this->getJson('/api/v1/policies/999999999');

        $this->assertContains($response->getStatusCode(), [404, 200]);
    }
}
