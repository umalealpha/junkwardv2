<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMonthRateSettingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('month_rate_settings', function (Blueprint $table) {
            $table->id();
            $table->integer('min_months'); // Minimum months (inclusive)
            $table->integer('max_months')->nullable(); // Maximum months (exclusive), null means no upper limit
            $table->decimal('rate_percentage', 5, 2); // Rate percentage (e.g., 2.00 for 2%)
            $table->string('description')->nullable(); // Optional description
            $table->boolean('is_active')->default(true); // Whether this rate is active
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('month_rate_settings');
    }
}
