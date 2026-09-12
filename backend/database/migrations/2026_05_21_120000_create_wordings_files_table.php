<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wordings_files', function (Blueprint $table) {
            $table->id();
            $table->string('category', 64)->index();
            $table->string('product_code', 32)->nullable();
            $table->string('display_name', 255);
            $table->string('s3_key', 512);
            $table->string('content_hash', 64)->nullable()->index();
            $table->unsignedBigInteger('size_bytes');
            $table->string('mime_type', 64)->default('application/pdf');
            $table->boolean('is_active')->default(true)->index();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->timestamp('uploaded_at')->useCurrent();
            $table->unsignedBigInteger('deactivated_by')->nullable();
            $table->timestamp('deactivated_at')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();

            $table->index(['category', 'is_active']);
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wordings_files');
    }
};
