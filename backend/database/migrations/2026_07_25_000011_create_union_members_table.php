<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Union Members module — union_members.
 *
 * Fields follow the Members-module spec (id_number, member_name, member_type,
 * contact_number, nationality) while KEEPING the group-scheme linkage
 * (customer_id → a full customer record, policy_id → the union's group policy)
 * so premium calc and future claims attribution still work.
 *
 * Rules enforced here:
 *   - status (1 = active, 0 = inactive) drives active-member counts + premium;
 *     deactivation retains the historical row (soft delete for removal).
 *   - UNIQUE (union_id, id_number) blocks a duplicate ID within a union;
 *     "one member → one union" (across ALL unions) is enforced app-side +
 *     during Excel import.
 *   - created_by / updated_by feed the audit trail.
 *
 * Self-healing per the repo standard: create when absent, else top up missing
 * columns — idempotent / re-runnable.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('union_members')) {
            Schema::create('union_members', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('union_id')->index();
                $table->unsignedBigInteger('policy_id')->nullable()->index();
                $table->unsignedBigInteger('customer_id')->nullable()->index();

                $table->string('id_number', 50);
                $table->string('member_name');
                $table->string('member_type', 100)->nullable();
                $table->date('date_of_birth')->nullable();
                $table->tinyInteger('gender')->nullable();          // 1 = Male, 0 = Female
                $table->string('contact_number', 50)->nullable();
                $table->string('email')->nullable();
                $table->string('nationality', 100)->nullable();
                $table->unsignedTinyInteger('status')->default(1)->index();

                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['union_id', 'id_number'], 'union_members_union_id_number_unique');
            });
            return;
        }

        // Table pre-exists — top up any missing columns.
        Schema::table('union_members', function (Blueprint $table) {
            $cols = [
                'union_id'      => fn() => $table->unsignedBigInteger('union_id')->nullable(),
                'policy_id'     => fn() => $table->unsignedBigInteger('policy_id')->nullable(),
                'customer_id'   => fn() => $table->unsignedBigInteger('customer_id')->nullable(),
                'id_number'     => fn() => $table->string('id_number', 50)->nullable(),
                'member_name'   => fn() => $table->string('member_name')->nullable(),
                'member_type'   => fn() => $table->string('member_type', 100)->nullable(),
                'date_of_birth' => fn() => $table->date('date_of_birth')->nullable(),
                'gender'        => fn() => $table->tinyInteger('gender')->nullable(),
                'contact_number'=> fn() => $table->string('contact_number', 50)->nullable(),
                'email'         => fn() => $table->string('email')->nullable(),
                'nationality'   => fn() => $table->string('nationality', 100)->nullable(),
                'status'        => fn() => $table->unsignedTinyInteger('status')->default(1),
                'created_by'    => fn() => $table->unsignedBigInteger('created_by')->nullable(),
                'updated_by'    => fn() => $table->unsignedBigInteger('updated_by')->nullable(),
            ];
            foreach ($cols as $name => $add) {
                if (!Schema::hasColumn('union_members', $name)) $add();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('union_members');
    }
};
