<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUploadedExcelFilesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('uploaded_excel_files', function (Blueprint $table) {
            $table->id();
            $table->string('file_name'); 
            $table->string('file_path'); 
            $table->string('uploaded_by')->nullable(); 
            $table->string('status')->default('pending');
            $table->text('remarks')->nullable(); 
            $table->string('report_file')->nullable(); 
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
        Schema::dropIfExists('uploaded_excel_files');
    }
}
