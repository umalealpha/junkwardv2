<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ExcelImportActivity extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('excel_import_activity')) {
            Schema::create('excel_import_activity', function (Blueprint $table) {
                $table->id();
                $table->string('excel_uploaded_file')->nullable();
                $table->string('excel_perform_file')->nullable();
                $table->string('added_by')->nullable();
                $table->integer('status')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('excel_import_activity');
    }
}
