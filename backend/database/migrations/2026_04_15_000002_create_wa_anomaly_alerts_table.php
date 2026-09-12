<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wa_anomaly_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('alert_key', 120)->index();          // unique key per anomaly instance
            $table->string('severity', 20)->default('medium');  // critical / high / medium / low
            $table->text('message');
            $table->timestamp('alerted_at')->index();           // when it was sent
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_anomaly_alerts');
    }
};
