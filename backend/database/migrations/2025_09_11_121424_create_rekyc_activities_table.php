<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRekycActivitiesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('rekyc_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('link_id')->constrained('rekyc_links')->onDelete('cascade');
            $table->integer('customer_id');
        $table->foreign('customer_id')->references('id')->on('customer')->onDelete('cascade');
            $table->string('activity_type'); // link_sent, link_opened, otp_verified, consent_given, data_updated, document_uploaded, etc.
            $table->text('description')->nullable();
            $table->json('metadata')->nullable(); // Additional activity data
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->json('device_info')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
            
            $table->index(['link_id', 'activity_type']);
            $table->index(['customer_id', 'occurred_at']);
            $table->index('activity_type');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('rekyc_activities');
    }
}
