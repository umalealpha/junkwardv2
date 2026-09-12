<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ad_group_kyc_documents', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('submission_id');
            $table->string('field_key');
            $table->string('original_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('path');
            $table->string('url')->nullable();
            $table->timestamps();

            $table->index(['submission_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_group_kyc_documents');
    }
};


