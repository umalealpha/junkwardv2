<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDuplicateCustomersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('duplicate_customers', function (Blueprint $table) {
            $table->id();
            
            // Customer Information
            $table->integer('customer_id');
            $table->foreign('customer_id')->references('id')->on('customer')->onDelete('cascade');
            
            // Customer Details
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->nullable();
            $table->string('cellphone')->nullable();
            
            // Document Information
            $table->string('omang_number')->nullable();
            $table->string('passport_number')->nullable();
            
            // Banking Information
            $table->string('bank_account_number')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('bank_branch')->nullable();
            $table->string('billing')->nullable();
            
            // Duplicate Information
            $table->enum('duplicate_type', ['omang_passport', 'cellphone_email', 'multiple_accounts'])->index();
            $table->text('duplicate_reason');
            $table->json('duplicate_details')->nullable(); // Store additional duplicate information
            
            // Processing Information
            $table->enum('status', ['pending', 'reviewed', 'resolved', 'ignored'])->default('pending');
            $table->text('resolution_notes')->nullable();
            $table->unsignedBigInteger('resolved_by')->nullable();
            $table->foreign('resolved_by')->references('id')->on('users')->onDelete('set null');
            $table->timestamp('resolved_at')->nullable();
            
            // Additional Information
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable(); // Store any additional metadata
            
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['customer_id', 'duplicate_type']);
            $table->index(['duplicate_type', 'status']);
            $table->index(['omang_number']);
            $table->index(['passport_number']);
            $table->index(['cellphone']);
            $table->index(['email']);
            $table->index(['bank_account_number']);
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('duplicate_customers');
    }
}
