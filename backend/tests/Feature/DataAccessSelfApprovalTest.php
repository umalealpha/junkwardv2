<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Segregation-of-duties guard on the DPO / PoPIA customer-data release flow.
 *
 * A user who RAISED a data-access request must never be able to approve
 * (or deny) their own request, even if they hold customer-data.approve.
 * PII release always requires a second, independent approver.
 *
 * NOTE: authored without a local PHP runtime — run in CI/staging
 * (php artisan test --filter=DataAccessSelfApproval).
 */
class DataAccessSelfApprovalTest extends TestCase
{
    use RefreshDatabase;

    private function approver(): User
    {
        Permission::findOrCreate('customer-data.approve', 'web');
        $u = User::factory()->create();
        $u->givePermissionTo('customer-data.approve');
        return $u;
    }

    private function seedRequest(int $requestedBy): int
    {
        return DB::table('data_access_requests')->insertGetId([
            'policy_number'    => 'TEST-POL-0001',
            'fields_requested' => json_encode(['phone']),
            'justification'    => str_repeat('word ', 60),
            'requested_by'     => $requestedBy,
            'status'           => 'pending',
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);
    }

    public function test_requester_cannot_approve_their_own_request(): void
    {
        $u  = $this->approver();
        $id = $this->seedRequest($u->id);

        $resp = $this->actingAs($u)->postJson("/api/v1/data-access/{$id}/decide", [
            'decision' => 'approved',
        ]);

        $resp->assertStatus(403);
        $this->assertSame('pending', DB::table('data_access_requests')->where('id', $id)->value('status'),
            'self-approval must leave the request pending');
        $this->assertDatabaseHas('data_access_audit', [
            'request_id' => $id,
            'action'     => 'self_approval_blocked',
        ]);
    }

    public function test_requester_cannot_deny_their_own_request_either(): void
    {
        $u  = $this->approver();
        $id = $this->seedRequest($u->id);

        $this->actingAs($u)->postJson("/api/v1/data-access/{$id}/decide", [
            'decision' => 'denied',
        ])->assertStatus(403);

        $this->assertSame('pending', DB::table('data_access_requests')->where('id', $id)->value('status'));
    }

    public function test_a_different_approver_can_approve(): void
    {
        $requester = $this->approver();           // holds the permission but is the requester
        $secondApprover = $this->approver();      // independent approver
        $id = $this->seedRequest($requester->id);

        $this->actingAs($secondApprover)->postJson("/api/v1/data-access/{$id}/decide", [
            'decision' => 'approved',
        ])->assertStatus(200);

        $row = DB::table('data_access_requests')->where('id', $id)->first();
        $this->assertSame('approved', $row->status);
        $this->assertSame($secondApprover->id, (int) $row->decided_by);
    }
}
