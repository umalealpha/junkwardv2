<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use AlphaDirect\Models\DeduplicationChecks;
use AlphaDirect\Customer;

class BankStatementUploadTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create a test customer
        $this->customer = Customer::factory()->create([
            'firstName' => 'John',
            'lastName' => 'Doe',
            'email' => 'john@example.com',
            'cellphone' => '12345678'
        ]);

        // Create a test deduplication check
        $this->deduplicationCheck = DeduplicationChecks::create([
            'customer_id' => $this->customer->id,
            'unique_customer_id' => 'CUST_' . $this->customer->id . '_' . time(),
            'unique_access_token' => 'test_token_123',
            'link_expires_at' => now()->addDays(7),
            'status' => 'active',
            'document_upload_status' => 'pending',
            'manual_verification_status' => 'pending'
        ]);
    }

    /** @test */
    public function it_can_access_bank_statement_upload_page()
    {
        $response = $this->getJson("/api/bank-statement/access/{$this->deduplicationCheck->unique_access_token}");

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'customer' => [
                            'name' => 'John Doe',
                            'email' => 'john@example.com',
                            'phone' => '12345678'
                        ],
                        'token' => 'test_token_123',
                        'status' => 'active'
                    ]
                ]);
    }

    /** @test */
    public function it_returns_404_for_invalid_token()
    {
        $response = $this->getJson('/api/bank-statement/access/invalid_token');

        $response->assertStatus(404)
                ->assertJson([
                    'success' => false,
                    'message' => 'Invalid or expired token'
                ]);
    }

    /** @test */
    public function it_can_send_otp()
    {
        $response = $this->postJson('/api/bank-statement/send-otp', [
            'token' => $this->deduplicationCheck->unique_access_token,
            'methods' => ['sms', 'email'],
            'cellphone' => '12345678'
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'OTP sent successfully'
                ]);
    }

    /** @test */
    public function it_can_verify_otp()
    {
        // Set OTP code
        $this->deduplicationCheck->update([
            'otp_code' => '123456',
            'otp_expires_at' => now()->addMinutes(10)
        ]);

        $response = $this->postJson("/api/bank-statement/verify-otp/{$this->deduplicationCheck->unique_access_token}", [
            'otp_code' => '123456'
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'OTP verified successfully'
                ]);
    }

    /** @test */
    public function it_returns_400_for_invalid_otp()
    {
        $this->deduplicationCheck->update([
            'otp_code' => '123456',
            'otp_expires_at' => now()->addMinutes(10)
        ]);

        $response = $this->postJson("/api/bank-statement/verify-otp/{$this->deduplicationCheck->unique_access_token}", [
            'otp_code' => '654321'
        ]);

        $response->assertStatus(400)
                ->assertJson([
                    'success' => false,
                    'message' => 'Invalid OTP code'
                ]);
    }

    /** @test */
    public function it_can_get_upload_requirements()
    {
        $response = $this->getJson("/api/bank-statement/upload-requirements/{$this->deduplicationCheck->unique_access_token}");

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'customer' => [
                            'name' => 'John Doe',
                            'customer_id' => 'CUST' . $this->customer->id
                        ],
                        'upload_requirements' => [
                            'max_file_size' => 10485760,
                            'accepted_formats' => ['pdf', 'jpg', 'jpeg', 'png'],
                            'required_fields' => ['bank_name', 'account_number', 'statement_period']
                        ],
                        'token' => 'test_token_123'
                    ]
                ]);
    }

    /** @test */
    public function it_can_upload_bank_statement()
    {
        // Set OTP as verified
        $this->deduplicationCheck->update([
            'otp_code' => '123456',
            'otp_verified_at' => now(),
            'status' => 'otp_verified'
        ]);

        // Create a test file
        $file = \Illuminate\Http\UploadedFile::fake()->create('test_statement.pdf', 1000, 'application/pdf');

        $response = $this->postJson("/api/bank-statement/upload/{$this->deduplicationCheck->unique_access_token}", [
            'bank_name' => 'First National Bank',
            'account_number' => '1234567890',
            'statement_period' => 'January 2024 - March 2024',
            'bank_statement' => $file
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Bank statement uploaded successfully'
                ]);
    }

    /** @test */
    public function it_can_get_completion_status()
    {
        $response = $this->getJson("/api/bank-statement/completion-status/{$this->deduplicationCheck->unique_access_token}");

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'customer_name' => 'John Doe',
                        'status' => 'pending'
                    ]
                ]);
    }

    /** @test */
    public function it_returns_410_for_expired_token()
    {
        $this->deduplicationCheck->update([
            'link_expires_at' => now()->subDay(),
            'status' => 'expired'
        ]);

        $response = $this->getJson("/api/bank-statement/access/{$this->deduplicationCheck->unique_access_token}");

        $response->assertStatus(410)
                ->assertJson([
                    'success' => false,
                    'message' => 'Link has expired'
                ]);
    }

    /** @test */
    public function it_returns_410_for_used_link()
    {
        $this->deduplicationCheck->update([
            'status' => 'completed'
        ]);

        $response = $this->getJson("/api/bank-statement/access/{$this->deduplicationCheck->unique_access_token}");

        $response->assertStatus(410)
                ->assertJson([
                    'success' => false,
                    'message' => 'Link already used'
                ]);
    }
}
