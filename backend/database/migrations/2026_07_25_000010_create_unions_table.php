<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Union Registration module — the `unions` master table.
 *
 * A union is a Legal Insurance Group Scheme (BONU, BOWASEWU, …). Each union owns
 * one group policy (auto-minted MIS<union_code>, or an admin-supplied override)
 * and a per-member monthly premium; total monthly premium = active members ×
 * monthly_premium.
 *
 * Self-healing per the repo standard: creates the table when absent, otherwise
 * tops up any missing columns — safe / idempotent on a shared DB. `status`
 * (1 = active) is the active/inactive flag; `deleted_at` allows a union to be
 * retired without breaking history; created_by / updated_by feed the audit trail.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('unions')) {
            Schema::create('unions', function (Blueprint $table) {
                $table->id();
                $table->string('union_name');
                $table->string('union_code', 50);
                $table->string('description', 1000)->nullable();
                $table->unsignedBigInteger('product_id')->default(4);       // 4 = Legal Insurance
                $table->unsignedBigInteger('policy_id')->nullable();        // FK → policies.id
                $table->string('policy_number', 100)->nullable();
                $table->decimal('monthly_premium', 12, 2)->default(0);
                $table->date('effective_date')->nullable();
                $table->date('expiry_date')->nullable();
                $table->string('contact_person')->nullable();
                $table->string('contact_number', 50)->nullable();
                $table->string('email')->nullable();
                $table->string('address', 500)->nullable();
                $table->unsignedTinyInteger('status')->default(1)->index(); // 1 = active, 0 = inactive
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique('union_name');
                $table->unique('union_code');
            });
            return;
        }

        // Table pre-exists (older snapshot) — top up any missing columns.
        Schema::table('unions', function (Blueprint $table) {
            $cols = [
                'union_name'      => fn() => $table->string('union_name')->nullable(),
                'union_code'      => fn() => $table->string('union_code', 50)->nullable(),
                'description'     => fn() => $table->string('description', 1000)->nullable(),
                'product_id'      => fn() => $table->unsignedBigInteger('product_id')->default(4),
                'policy_id'       => fn() => $table->unsignedBigInteger('policy_id')->nullable(),
                'policy_number'   => fn() => $table->string('policy_number', 100)->nullable(),
                'monthly_premium' => fn() => $table->decimal('monthly_premium', 12, 2)->default(0),
                'effective_date'  => fn() => $table->date('effective_date')->nullable(),
                'expiry_date'     => fn() => $table->date('expiry_date')->nullable(),
                'contact_person'  => fn() => $table->string('contact_person')->nullable(),
                'contact_number'  => fn() => $table->string('contact_number', 50)->nullable(),
                'email'           => fn() => $table->string('email')->nullable(),
                'address'         => fn() => $table->string('address', 500)->nullable(),
                'status'          => fn() => $table->unsignedTinyInteger('status')->default(1),
                'created_by'      => fn() => $table->unsignedBigInteger('created_by')->nullable(),
                'updated_by'      => fn() => $table->unsignedBigInteger('updated_by')->nullable(),
            ];
            foreach ($cols as $name => $add) {
                if (!Schema::hasColumn('unions', $name)) $add();
            }
        });

        foreach (['union_name' => 'unions_union_name_unique', 'union_code' => 'unions_union_code_unique'] as $col => $idx) {
            if (Schema::hasColumn('unions', $col) && !$this->indexExists('unions', $idx)) {
                Schema::table('unions', fn(Blueprint $t) => $t->unique($col));
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('unions');
    }

    /** Portable index-exists check (MySQL/MariaDB). */
    private function indexExists(string $table, string $index): bool
    {
        try {
            return !empty(Schema::getConnection()->select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$index]));
        } catch (\Throwable $e) {
            return false;
        }
    }
};
