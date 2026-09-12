<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Two-table model for the public chunked-upload pipeline:
     *
     *   public_upload_sessions  — one row per logical upload (customer KYC
     *                             doc, vehicle inspection photo, etc.).
     *                             Tracks how many chunks expected vs
     *                             received, when it'll expire, and what
     *                             session token authorised it.
     *
     *   public_uploaded_files   — one row per assembled file (the
     *                             session's terminal state). Holds the
     *                             S3 path, sha256 + size for de-dup,
     *                             and the kind/category linking it back
     *                             to the flow that uploaded it.
     *
     * Replaces the legacy kyc_documents table which had no session
     * concept, no de-dup, no expiry, and stored everything publicly.
     */
    public function up(): void
    {
        Schema::create('public_upload_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('session_uuid', 36)->unique();
            // Authorising session — must be a valid public_session_tokens row at
            // chunk-receive time. We store the hash so a leaked sessions
            // table doesn't leak the live tokens.
            $table->string('auth_token_hash', 64)->index();
            $table->string('cellphone', 24)->index();
            // What the customer is uploading. Drives validation rules:
            // omang_kyc → image|pdf, ≤10MB; vehicle_photo → image, ≤8MB.
            $table->enum('purpose', [
                'omang_kyc', 'passport_kyc', 'license_kyc', 'employment_letter',
                'proof_of_residence', 'vehicle_photo', 'vehicle_inspection',
                'cellphone_photo', 'other',
            ])->index();
            $table->string('original_filename', 255);
            $table->string('safe_filename', 255); // sanitised, used on disk + S3
            $table->string('mime_type', 100)->nullable();
            $table->unsignedInteger('total_chunks');
            $table->unsignedInteger('received_chunks')->default(0);
            $table->unsignedBigInteger('expected_size')->nullable();
            $table->enum('status', ['receiving', 'assembling', 'completed', 'failed', 'expired'])
                  ->default('receiving')->index();
            $table->text('error_message')->nullable();
            $table->timestamp('expires_at')->index();
            $table->string('client_ip', 45)->nullable();
            $table->string('client_ua', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('public_uploaded_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('public_upload_sessions')->cascadeOnDelete();
            $table->string('cellphone', 24)->index();
            $table->string('purpose', 32)->index();
            $table->string('s3_bucket', 100);
            $table->string('s3_path',   255);
            $table->unsignedBigInteger('size_bytes');
            $table->string('sha256', 64)->index();
            $table->string('mime_type', 100)->nullable();
            // OCR results pinned to the upload — populated async by the
            // OcrExtractor pipeline. Lets ops review what was extracted
            // alongside the original.
            $table->json('ocr_extracted')->nullable();
            $table->float('ocr_confidence')->nullable();
            // Virus-scan hook — populated by ClamAV worker when wired.
            $table->enum('scan_status', ['pending', 'clean', 'infected', 'skipped'])
                  ->default('pending')->index();
            $table->string('scan_signature', 100)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public_uploaded_files');
        Schema::dropIfExists('public_upload_sessions');
    }
};
