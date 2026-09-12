<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddUploadedByToAdGroupUploadedExcelFilesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('ad_group_uploaded_excel_files', function (Blueprint $table) {
            $table->unsignedBigInteger('uploaded_by')->nullable()->after('file_path');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('ad_group_uploaded_excel_files', function (Blueprint $table) {
            $table->dropColumn('uploaded_by');
        });
    }
}
