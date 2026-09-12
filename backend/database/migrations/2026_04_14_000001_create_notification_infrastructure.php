<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateNotificationInfrastructure extends Migration
{
    public function up()
    {
        // Notification templates — configurable per event type
        if (!Schema::hasTable('notification_templates')) {
            Schema::create('notification_templates', function (Blueprint $table) {
                $table->id();
                $table->string('type', 100)->unique();       // e.g. 'policy_activated'
                $table->string('name', 200);                  // Human-friendly name
                $table->json('channels')->nullable();          // ['in_app','email','sms']
                $table->string('email_subject', 300)->nullable();
                $table->text('email_body')->nullable();        // HTML with {{variable}} placeholders
                $table->text('sms_body')->nullable();           // Plain text with {{variable}}
                $table->text('whatsapp_body')->nullable();
                $table->json('variables')->nullable();          // Available variables list
                $table->boolean('active')->default(true);
                $table->timestamps();
            });
        }

        // Notification delivery log — tracks every dispatch
        if (!Schema::hasTable('notification_logs')) {
            Schema::create('notification_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('type', 100);
                $table->string('channel', 30);               // in_app, email, sms, whatsapp
                $table->json('data')->nullable();
                $table->string('status', 20)->default('dispatched'); // dispatched, sent, failed, skipped
                $table->string('reason', 500)->nullable();
                $table->timestamps();
                $table->index(['user_id', 'created_at']);
                $table->index(['type', 'status']);
            });
        }

        // Policy lifecycle audit trail
        if (!Schema::hasTable('policy_lifecycle')) {
            Schema::create('policy_lifecycle', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('policy_id');
                $table->string('action', 100);               // policy_activated, policy_approved, etc.
                $table->unsignedBigInteger('performed_by')->nullable();
                $table->json('extra_data')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->index('policy_id');
                $table->index('action');
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('policy_lifecycle');
        Schema::dropIfExists('notification_logs');
        Schema::dropIfExists('notification_templates');
    }
}
