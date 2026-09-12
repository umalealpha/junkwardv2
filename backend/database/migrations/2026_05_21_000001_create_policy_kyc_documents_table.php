<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Port of graphiteBWV8's 2026_04_22_000003_create_policy_kyc_documents_table.
 *
 * Sparse table that stores variable-length document sets per policy (e.g.
 * N director ID images) that can't be represented on the flat customer_kyc
 * or customer_kyc_dom_com columns.
 *
 * Used by V8 BizSure for directors[] / shareholders[] KYC forwarding.
 * Pattern: { doc_type: 'director_id', doc_index: 0, file_path: 's3/path/...' }
 *
 * Leaves existing customer_kyc_dom_com flat columns untouched; this is
 * purely additive.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('policy_kyc_documents')) {
            return;
        }

        Schema::create('policy_kyc_documents', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('policy_id')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('doc_type', 64);            // e.g., 'director_id', 'shareholder_id'
            $table->integer('doc_index')->nullable();  // 0, 1, 2, ... for multiple directors / shareholders
            $table->string('file_path', 512);
            $table->timestamps();

            $table->index(['policy_id', 'doc_type'], 'policy_kyc_documents_policy_doc_type_idx');
            $table->index(['customer_id', 'doc_type'], 'policy_kyc_documents_cust_doc_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('policy_kyc_documents');
    }
};
