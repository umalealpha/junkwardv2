<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePolicyAppliedDiscountsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('policy_applied_discounts', function (Blueprint $table) {
            $table->id();
            $table->string('policyNumber');
            $table->decimal('discount_rate', 5, 2); // e.g., 10.50 for 10.50%
            $table->decimal('discount_amount', 10, 2); // Actual discount amount
            $table->decimal('original_premium', 10, 2); // Original premium before discount
            $table->integer('calculated_months'); // Months that qualified for discount
            $table->enum('status', ['applied', 'cancelled', 'superseded'])->default('applied');
            $table->unsignedBigInteger('action_by'); // User ID who applied the discount
            $table->text('notes')->nullable(); // Optional notes
            $table->timestamp('applied_at')->useCurrent(); // When discount was applied
            $table->timestamps();
            
            // Indexes for better performance
            $table->index('policyNumber');
            $table->index('status');
            $table->index('action_by');
            $table->index(['policyNumber', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('policy_applied_discounts');
    }
}
