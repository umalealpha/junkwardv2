<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Link a public-uploaded file to the policy it ultimately belongs
     * to once the materialisation worker creates a Policy from a paid
     * motor_quote (or any future quote → policy worker).
     *
     * Without this column, KYC documents are anchored only by cellphone
     * + sha256 — which works for de-dup but doesn't let admin locate
     * "all docs for policy MOT-20260430-…" in one query.
     */
    public function up(): void
    {
        Schema::table('public_uploaded_files', function (Blueprint $table) {
            $table->unsignedBigInteger('materialised_policy_id')->nullable()->after('product_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('public_uploaded_files', function (Blueprint $table) {
            $table->dropColumn('materialised_policy_id');
        });
    }
};
