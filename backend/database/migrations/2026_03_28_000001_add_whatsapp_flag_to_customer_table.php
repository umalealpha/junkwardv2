<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddWhatsappFlagToCustomerTable extends Migration
{
    public function up()
    {
        Schema::table('customer', function (Blueprint $table) {
            $table->tinyInteger('is_whatsapp')->nullable()->default(null)->after('cellphone')
                  ->comment('null=unchecked, 1=on WhatsApp, 0=not on WhatsApp');
            $table->timestamp('whatsapp_checked_at')->nullable()->after('is_whatsapp');
        });
    }

    public function down()
    {
        Schema::table('customer', function (Blueprint $table) {
            $table->dropColumn(['is_whatsapp', 'whatsapp_checked_at']);
        });
    }
}
