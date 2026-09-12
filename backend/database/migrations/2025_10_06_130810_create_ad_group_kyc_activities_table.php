<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAdGroupKycActivitiesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ad_group_kyc_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('link_id')->constrained('ad_group_kyc_links')->onDelete('cascade');
            $table->integer('customer_id');
            $table->foreign('customer_id')->references('id')->on('customer')->onDelete('cascade');
            $table->integer('policy_id')->nullable();
            $table->foreign('policy_id')->references('id')->on('policies')->onDelete('cascade');
            $table->string('activity_type'); // Type of activity (link_opened, otp_verified, etc.)
            $table->text('description')->nullable();
            $table->json('metadata')->nullable(); // Additional data for the activity
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->json('device_info')->nullable(); // Device fingerprinting data
            $table->timestamp('occurred_at')->nullable();
            $table->timestamps();
            
            $table->index(['link_id', 'activity_type']);
            $table->index(['customer_id', 'occurred_at']);
            $table->index(['policy_id', 'occurred_at']);
            $table->index('occurred_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ad_group_kyc_activities');
    }
}
