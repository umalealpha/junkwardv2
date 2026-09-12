<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tracks RealPay settlement Excel uploads through their preview -> confirm
 * lifecycle for the dedicated import screen.
 *
 * status: uploaded -> previewing -> preview_ready -> committing -> committed
 *         (or -> failed at any stage). preview_summary / commit_summary hold
 *         the importer's stats counters as JSON.
 *
 * Default (mysql) connection — same as the policies / payment_transactions /
 * realpay_client_contracts tables the import writes to.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('realpay_settlement_imports')) {
            Schema::create('realpay_settlement_imports', function (Blueprint $table) {
                $table->id();
                $table->string('file_name');
                $table->string('file_path');
                $table->string('uploaded_by')->nullable();
                $table->string('status', 20)->default('uploaded')->index();
                $table->unsignedInteger('row_count')->nullable();
                $table->json('preview_summary')->nullable();
                $table->json('commit_summary')->nullable();
                $table->text('error')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('realpay_settlement_imports');
    }
};
