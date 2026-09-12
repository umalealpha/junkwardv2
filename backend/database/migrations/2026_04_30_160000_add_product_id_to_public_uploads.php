<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Stamp uploaded files with the product they relate to, when known.
     *
     * Customer flows know which product they're collecting docs for —
     * Motor Comprehensive needs vehicle bluebook + licence, MIS plans
     * need Omang + employment letter, etc. We persist the product_id on
     * each upload session + each final file row so the materialisation
     * worker can file documents under the right product folder when it
     * creates the real Policy + CustomerKyc records after payment.
     *
     * Nullable — bare KYC uploads (a customer refreshing Omang without
     * tying it to a specific product) leave the column empty.
     */
    public function up(): void
    {
        Schema::table('public_upload_sessions', function (Blueprint $table) {
            $table->unsignedSmallInteger('product_id')->nullable()->after('purpose')->index();
        });
        Schema::table('public_uploaded_files', function (Blueprint $table) {
            $table->unsignedSmallInteger('product_id')->nullable()->after('purpose')->index();
        });
    }

    public function down(): void
    {
        Schema::table('public_uploaded_files', function (Blueprint $table) {
            $table->dropColumn('product_id');
        });
        Schema::table('public_upload_sessions', function (Blueprint $table) {
            $table->dropColumn('product_id');
        });
    }
};
