<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Data Access Request workflow (DPO-gated PII reveal).
 *
 * Customer PII (name / phone / bank / id / address) is masked by default.
 * A user raises a request naming the policy, the fields needed and a
 * justification (>= 50 words). An approver (UW Head / Auditor / Admin /
 * — Compliance & Finance Manager once their roles are confirmed) approves
 * or denies. Only an APPROVED request can reveal the fields, and every
 * step (request / decision / reveal) is written to data_access_audit.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('data_access_requests')) {
            Schema::create('data_access_requests', function (Blueprint $t) {
                $t->id();
                $t->string('policy_number', 80);
                $t->json('fields_requested');                 // ["name","phone","address","bank","id"]
                $t->text('justification');                    // >= 50 words (enforced in controller)
                $t->unsignedBigInteger('requested_by');
                $t->enum('status', ['pending', 'approved', 'denied'])->default('pending');
                $t->unsignedBigInteger('decided_by')->nullable();
                $t->text('decision_note')->nullable();
                $t->timestamp('decided_at')->nullable();
                $t->timestamp('revealed_at')->nullable();
                $t->timestamps();
                $t->index('policy_number');
                $t->index('status');
                $t->index('requested_by');
            });
        }

        if (!Schema::hasTable('data_access_audit')) {
            Schema::create('data_access_audit', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('request_id')->nullable();
                $t->string('action', 40);                     // requested|approved|denied|revealed
                $t->unsignedBigInteger('actor_id')->nullable();
                $t->string('policy_number', 80)->nullable();
                $t->json('fields')->nullable();
                $t->text('detail')->nullable();
                $t->timestamp('created_at')->useCurrent();
                $t->index('request_id');
                $t->index('action');
            });
        }

        // Spatie permission + grant to the approver roles that map cleanly today.
        // Compliance Manager + Finance Manager have NO matching Graphite role yet —
        // grant them once the CFO confirms which role represents each (or they are created).
        if (Schema::hasTable('permissions') && Schema::hasTable('roles') && Schema::hasTable('role_has_permissions')) {
            $guard = DB::table('permissions')->value('guard_name') ?: 'web';
            $now   = now();
            $permId = DB::table('permissions')
                ->where('name', 'customer-data.approve')->where('guard_name', $guard)->value('id');
            if (!$permId) {
                $permId = DB::table('permissions')->insertGetId([
                    'name' => 'customer-data.approve', 'guard_name' => $guard,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }
            foreach (['Super Admin', 'Admin', 'Underwriting Head', 'Auditor'] as $roleName) {
                $rid = DB::table('roles')->where('name', $roleName)->where('guard_name', $guard)->value('id');
                if ($rid && !DB::table('role_has_permissions')
                        ->where('permission_id', $permId)->where('role_id', $rid)->exists()) {
                    DB::table('role_has_permissions')->insert(['permission_id' => $permId, 'role_id' => $rid]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('data_access_audit');
        Schema::dropIfExists('data_access_requests');
    }
};
