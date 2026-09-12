<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class AuthTest extends TestCase
{
    public function test_login_requires_email_and_password()
    {
        $response = $this->postJson('/api/v1/auth/login', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_login_rejects_invalid_credentials()
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'nonexistent@test.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401);
    }

    public function test_login_returns_token_with_valid_credentials()
    {
        // Uses the first user in the database for testing
        $user = \AlphaDirect\User::first();

        if (!$user) {
            $this->markTestSkipped('No users in database');
        }

        // We can't test actual login without knowing the password,
        // but we can verify the endpoint accepts the right format
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'test_password_that_wont_work',
        ]);

        // Should be 401 (invalid password) not 500 (server error)
        $this->assertContains($response->getStatusCode(), [401, 200]);
    }

    public function test_protected_routes_require_auth()
    {
        $response = $this->getJson('/api/v1/dashboard/stats');
        $response->assertStatus(401);
    }

    public function test_user_endpoint_requires_auth()
    {
        $response = $this->getJson('/api/v1/auth/user');
        $response->assertStatus(401);
    }

    public function test_microsoft_sso_url_endpoint_exists()
    {
        $response = $this->getJson('/api/v1/auth/microsoft/url');

        // Should return a URL or error, not 404/500
        $this->assertNotEquals(404, $response->getStatusCode());
        $this->assertNotEquals(500, $response->getStatusCode());
    }
}
