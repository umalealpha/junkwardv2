<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ad_group_kyc_directors', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('submission_id');
            $table->string('full_name')->nullable();
            $table->text('residential_address')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('nationality')->nullable();
            $table->string('pip_declaration')->nullable();
            $table->string('source_of_wealth')->nullable();
            $table->timestamps();

            $table->index(['submission_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_group_kyc_directors');
    }
};


