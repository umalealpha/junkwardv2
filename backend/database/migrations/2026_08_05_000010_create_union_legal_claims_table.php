<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Union legal claims — the BONU Legal Claim Form filed AGAINST a specific union
 * member. Each row links the union, the member, the group policy and the
 * member's customer record, and stores every field on the paper form (header,
 * details of the matter, declaration, and the page-3 documentation checklist as
 * JSON). A parallel standard claim (claims/new_claims/claim_legal) is created at
 * the same time so the claim also appears in the Claims module — `claim_id` /
 * `claim_number` point at it. Enclosed documents are stored as `claim_attachments`
 * against that claim_id.
 *
 * Self-healing idempotent style (mirrors the union tables): create when absent,
 * otherwise top-up any missing columns, so re-runs and drifted envs are safe.
 */
return new class extends Migration {
    /** column name => Blueprint closure. Kept in one map so create + top-up agree. */
    private function columns(): array
    {
        return [
            'union_id'                     => fn (Blueprint $t) => $t->unsignedBigInteger('union_id')->index(),
            'union_member_id'              => fn (Blueprint $t) => $t->unsignedBigInteger('union_member_id')->index(),
            'policy_id'                    => fn (Blueprint $t) => $t->unsignedBigInteger('policy_id')->nullable(),
            'customer_id'                  => fn (Blueprint $t) => $t->unsignedBigInteger('customer_id')->nullable(),
            'claim_id'                     => fn (Blueprint $t) => $t->unsignedBigInteger('claim_id')->nullable()->index(),
            'claim_number'                 => fn (Blueprint $t) => $t->string('claim_number', 50)->nullable()->index(),
            // Dedicated per-union form identity: which union's form this record is
            // (e.g. form_code=BOWASEWU, form_name="BOWASEWU LEGAL CLAIM FORM").
            'form_code'                    => fn (Blueprint $t) => $t->string('form_code', 50)->nullable()->index(),
            'form_name'                    => fn (Blueprint $t) => $t->string('form_name', 150)->nullable(),
            // Header (form page 1)
            'policy_number'                => fn (Blueprint $t) => $t->string('policy_number', 100)->nullable(),
            'insured_name'                 => fn (Blueprint $t) => $t->string('insured_name', 255)->nullable(),
            'region'                       => fn (Blueprint $t) => $t->string('region', 150)->nullable(),
            'omang_passport'               => fn (Blueprint $t) => $t->string('omang_passport', 50)->nullable(),
            'cellphone'                    => fn (Blueprint $t) => $t->string('cellphone', 50)->nullable(),
            'email'                        => fn (Blueprint $t) => $t->string('email', 255)->nullable(),
            'claim_type'                   => fn (Blueprint $t) => $t->string('claim_type', 100)->nullable()->default('Legal'),
            // Details of the matter (page 2)
            'matter_relates_to'            => fn (Blueprint $t) => $t->string('matter_relates_to', 100)->nullable(),
            'child_financially_dependent'  => fn (Blueprint $t) => $t->tinyInteger('child_financially_dependent')->nullable(),
            'dependent_omang_passport'     => fn (Blueprint $t) => $t->string('dependent_omang_passport', 50)->nullable(),
            'dependent_dob'                => fn (Blueprint $t) => $t->date('dependent_dob')->nullable(),
            'matter_type'                  => fn (Blueprint $t) => $t->string('matter_type', 50)->nullable(),
            'matter_arose_date'            => fn (Blueprint $t) => $t->date('matter_arose_date')->nullable(),
            'proposed_course_of_action'    => fn (Blueprint $t) => $t->text('proposed_course_of_action')->nullable(),
            // Declaration
            'declaration_signed'           => fn (Blueprint $t) => $t->tinyInteger('declaration_signed')->default(0),
            'signatory_name'               => fn (Blueprint $t) => $t->string('signatory_name', 255)->nullable(),
            'signed_date'                  => fn (Blueprint $t) => $t->date('signed_date')->nullable(),
            // Documentation checklist (page 3) — { itemKey: {enclosed, forwarded} }
            'documentation_checklist'      => fn (Blueprint $t) => $t->json('documentation_checklist')->nullable(),
            'status'                       => fn (Blueprint $t) => $t->string('status', 50)->default('Pending')->index(),
            'created_by'                   => fn (Blueprint $t) => $t->unsignedBigInteger('created_by')->nullable(),
            'updated_by'                   => fn (Blueprint $t) => $t->unsignedBigInteger('updated_by')->nullable(),
        ];
    }

    public function up(): void
    {
        if (!Schema::hasTable('union_legal_claims')) {
            Schema::create('union_legal_claims', function (Blueprint $table) {
                $table->bigIncrements('id');
                foreach ($this->columns() as $make) {
                    $make($table);
                }
                $table->timestamps();
                $table->softDeletes();
            });
            return;
        }

        // Table exists — top-up any missing columns (drift-safe).
        Schema::table('union_legal_claims', function (Blueprint $table) {
            foreach ($this->columns() as $name => $make) {
                if (!Schema::hasColumn('union_legal_claims', $name)) {
                    $make($table);
                }
            }
            if (!Schema::hasColumn('union_legal_claims', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('union_legal_claims');
    }
};
