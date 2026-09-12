<?php
/**
 * Smart Underwriting Upload — upload log + per-segment extraction results.
 * Feeds the underwriter review screen (confidence + human-verified flag) which
 * replays the existing create-policy endpoints (no new write paths).
 * Engine: smart-uw-engine (Python), invoked by SmartUnderwritingExtractJob.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

return new class extends Migration {
    public function up(): void
    {
        // Idempotent — guarded with hasTable so a re-run never throws
        // "table already exists" and blocks the rest of the migration pipeline.
        if (!Schema::hasTable('smart_uw_uploads')) {
        Schema::create('smart_uw_uploads', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('uploaded_file');               // storage key
            $t->string('original_name');
            $t->string('file_ext', 8);                 // xlsx | xls | xlsb | pdf
            $t->enum('status', ['queued', 'processing', 'completed', 'failed'])
              ->default('queued');
            $t->text('message')->nullable();
            $t->unsignedBigInteger('added_by')->nullable();
            $t->timestamps();
            $t->index(['status', 'added_by']);
        });
        }

        if (!Schema::hasTable('smart_uw_extractions')) {
        Schema::create('smart_uw_extractions', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('upload_id');
            $t->string('segment_name');                // sheet / camp / subsidiary
            $t->json('extracted_json');                // Graphite-ready risk object
            $t->decimal('confidence', 5, 4)->nullable();
            $t->string('provider', 32)->nullable();    // local | gemini
            $t->json('discrepancies')->nullable();     // validation errors / low-confidence fields
            $t->boolean('human_verified')->default(false);
            $t->unsignedBigInteger('issued_policy_id')->nullable(); // set once issued
            $t->timestamps();
            $t->index('upload_id');
        });
        }

        // Self-provision the RBAC permission the routes are gated on, so the
        // feature is reachable post-migrate without a manual seeder step.
        // Idempotent (firstOrCreate). Role assignment stays a deliberate admin
        // action — creating the permission alone keeps the gate fail-closed.
        if (Schema::hasTable('permissions')) {
            Permission::firstOrCreate(['name' => 'underwriting_smart_upload', 'guard_name' => 'web']);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('smart_uw_extractions');
        Schema::dropIfExists('smart_uw_uploads');
    }
};
