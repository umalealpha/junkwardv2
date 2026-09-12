<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('v2_pdf_jobs', function (Blueprint $table) {
            if (!Schema::hasColumn('v2_pdf_jobs', 'document_title')) {
                $table->string('document_title', 100)->nullable()->after('file_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('v2_pdf_jobs', function (Blueprint $table) {
            if (Schema::hasColumn('v2_pdf_jobs', 'document_title')) {
                $table->dropColumn('document_title');
            }
        });
    }
};
