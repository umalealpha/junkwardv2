<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSection2FieldsToParCoveragesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('par_coverages', function (Blueprint $table) {
            $table->json('section2_items')->nullable()->after('insured_items');
            $table->decimal('section2_total_limit', 15, 2)->nullable()->after('total_premium');
            $table->decimal('section2_total_premium', 15, 2)->nullable()->after('section2_total_limit');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('par_coverages', function (Blueprint $table) {
            $table->dropColumn([
                'section2_items',
                'section2_total_limit',
                'section2_total_premium',
            ]);
        });
    }
}


