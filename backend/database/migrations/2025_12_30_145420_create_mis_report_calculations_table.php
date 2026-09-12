<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMisReportCalculationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
{
    Schema::create('mis_report_calculation', function (Blueprint $table) {
        $table->id(); // int(11) AUTO_INCREMENT PRIMARY KEY

        $table->integer('coverage_id');
        $table->string('s_CoverageCode', 200);
        $table->string('s_ScreenName', 200);

        $table->decimal('SCL', 20, 2);
        $table->decimal('AFCL', 20, 2);
        $table->decimal('QSCL', 20, 2);
        $table->decimal('TCL', 20, 2);

        $table->integer('financial_year_start'); // e.g. 2025
        $table->string('financial_year', 11);    // e.g. 2025-2026

        $table->integer('treaty_id');

        $table->timestamps();   // created_at, updated_at
        $table->softDeletes();  // deleted_at
    });
}

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
{
    Schema::dropIfExists('mis_report_calculation');
}
}
