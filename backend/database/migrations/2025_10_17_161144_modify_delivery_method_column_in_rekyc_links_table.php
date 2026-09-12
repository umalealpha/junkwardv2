<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ModifyDeliveryMethodColumnInRekycLinksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('rekyc_links', function (Blueprint $table) {
            // Change delivery_method from ENUM to VARCHAR to support multiple channels
            $table->string('delivery_method', 255)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('rekyc_links', function (Blueprint $table) {
            // Revert back to ENUM
            $table->enum('delivery_method', ['whatsapp', 'email', 'sms'])->nullable()->change();
        });
    }
}
