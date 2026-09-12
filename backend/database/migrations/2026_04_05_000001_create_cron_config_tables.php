<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCronConfigTables extends Migration
{
    public function up()
    {
        // Report email stakeholders
        if (!Schema::hasTable('report_stakeholders')) {
            Schema::create('report_stakeholders', function (Blueprint $table) {
                $table->id();
                $table->string('report_type', 100)->index();
                $table->string('name', 200)->nullable();
                $table->string('email', 200);
                $table->boolean('active')->default(true);
                $table->timestamps();
                $table->index('active');
            });
        }

        // Per-job configuration
        if (!Schema::hasTable('cron_jobs')) {
            Schema::create('cron_jobs', function (Blueprint $table) {
                $table->id();
                $table->string('job_key', 100)->unique();
                $table->string('label', 200)->nullable();
                $table->string('schedule', 100)->nullable()->comment('cron expression override');
                $table->boolean('enabled')->default(true);
                $table->boolean('email_enabled')->default(true);
                $table->json('recipients_override')->nullable();
                $table->json('threshold_config')->nullable();
                $table->text('notes')->nullable();
                $table->string('last_config_by', 200)->nullable();
                $table->timestamps();
            });
        }

        // Finance dashboard cache
        if (!Schema::hasTable('finance_dashboard_cache')) {
            Schema::create('finance_dashboard_cache', function (Blueprint $table) {
                $table->id();
                $table->date('cache_date');
                $table->longText('data');
                $table->timestamp('computed_at')->useCurrent();
                $table->unique('cache_date');
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('finance_dashboard_cache');
        Schema::dropIfExists('cron_jobs');
        Schema::dropIfExists('report_stakeholders');
    }
}
