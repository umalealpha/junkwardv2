<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRekycDocumentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('rekyc_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('link_id')->constrained('rekyc_links')->onDelete('cascade');
            $table->integer('customer_id');
        $table->foreign('customer_id')->references('id')->on('customer')->onDelete('cascade');
            $table->string('document_type'); // omang, passport, drivers_license, etc.
            $table->string('file_path'); // Encrypted file path
            $table->string('file_name');
            $table->string('mime_type');
            $table->bigInteger('file_size');
            $table->string('file_hash'); // For integrity verification
            $table->enum('status', ['uploaded', 'processing', 'verified', 'rejected', 'failed'])->default('uploaded');
            $table->json('ocr_data')->nullable(); // OCR extracted data
            $table->json('validation_results')->nullable(); // Validation results
            $table->json('fraud_analysis')->nullable(); // Fraud detection results
            $table->text('verification_notes')->nullable();
            $table->integer('verified_by')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            
            $table->index(['link_id', 'document_type']);
            $table->index(['customer_id', 'status']);
            $table->index('file_hash');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('rekyc_documents');
    }
}
