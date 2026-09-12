<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use Laravel\Sanctum\Sanctum;

class CustomerTest extends TestCase
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

    public function test_customer_show_requires_auth()
    {
        $response = $this->getJson('/api/v1/customers/1');
        $response->assertStatus(401);
    }

    public function test_customer_blocklist_requires_auth()
    {
        $response = $this->getJson('/api/v1/customers/block-list');
        $response->assertStatus(401);
    }

    public function test_customer_blocklist_returns_data()
    {
        $this->authenticateUser();

        $response = $this->getJson('/api/v1/customers/block-list');

        $response->assertStatus(200);
    }
}
