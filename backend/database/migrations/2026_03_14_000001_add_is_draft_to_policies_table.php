<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIsDraftToPoliciesTable extends Migration
{
    public function up()
    {
        Schema::table('policies', function (Blueprint $table) {
            $table->boolean('is_draft')->default(0)->after('status');
        });
    }

    public function down()
    {
        Schema::table('policies', function (Blueprint $table) {
            $table->dropColumn('is_draft');
        });
    }
}
