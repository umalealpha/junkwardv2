<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTreatyFieldsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('treaty', function (Blueprint $table) {
            if (!Schema::hasColumn('treaty', 'formula_name')) {
                $table->string('formula_name')->nullable();
            }
            if (!Schema::hasColumn('treaty', 'status')) {
                $table->string('status')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Schema::dropIfExists('treaty_fields');
        Schema::table('treaty', function (Blueprint $table) {
            $table->dropColumn('formula_name');
            $table->dropColumn('status');
        });
    }
}
