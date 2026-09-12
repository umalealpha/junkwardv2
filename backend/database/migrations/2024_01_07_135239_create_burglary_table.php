<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBurglaryTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('burglary', function (Blueprint $table) {
            $table->id();
            $table->string('newclaim_id')->nullable();
            $table->string('policyNumber')->nullable();
            $table->integer('claim_sub_type_id')->nullable();
            $table->longText('address_of_premises')->nullable();
            $table->longText('description_of_incident')->nullable();
            $table->text('date_time_police_advised');
            $table->integer('anyone_on_premises')->nullable();
            $table->longText('anyone_on_premises_brief');
            $table->integer('guarded_by_watchman');
            $table->integer('premises_properly_secured');
            $table->longText('total_value_contents_of_premises');
            $table->text('stock_books_records_located');
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
        Schema::dropIfExists('burglary');
    }
}
