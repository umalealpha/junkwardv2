<?php
/**
 * SCAFFOLD migration — move to backend/database/migrations/ when deploying.
 * Two tables: the upload log + per-segment extraction results (with confidence
 * and human-verified flag for the review screen / audit trail).
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('smart_uw_uploads', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('uploaded_file');               // storage key
            $t->string('original_name');
            $t->string('file_ext', 8);
            $t->enum('status', ['queued', 'processing', 'completed', 'failed'])
              ->default('queued');
            $t->text('message')->nullable();
            $t->unsignedBigInteger('added_by')->nullable();
            $t->timestamps();
            $t->index(['status', 'added_by']);
        });

        Schema::create('smart_uw_extractions', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('upload_id');
            $t->string('segment_name');                // sheet / camp / subsidiary
            $t->json('extracted_json');                // Graphite-ready risk object
            $t->decimal('confidence', 5, 4)->nullable();
            $t->string('provider', 32)->nullable();    // local | gemini
            $t->json('discrepancies')->nullable();     // low-confidence fields
            $t->boolean('human_verified')->default(false);
            $t->unsignedBigInteger('issued_policy_id')->nullable(); // set once issued
            $t->timestamps();
            $t->index('upload_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('smart_uw_extractions');
        Schema::dropIfExists('smart_uw_uploads');
    }
};
