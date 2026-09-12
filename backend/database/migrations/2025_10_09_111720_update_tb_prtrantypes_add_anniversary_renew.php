<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class UpdateTbPrtrantypesAddAnniversaryRenew extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // 🔹 Modify column length
        Schema::table('tb_prtrantypes', function (Blueprint $table) {
            $table->string('TranTypeCode', 25)->change();
        });

        // 🔹 Insert new transaction type (only if not exists)
        $exists = DB::table('tb_prtrantypes')
            ->where('TranTypeCode', 'ANNIVERSARY-RENEW')
            ->exists();

        if (!$exists) {
            DB::table('tb_prtrantypes')->insert([
                'TranTypeCode' => 'ANNIVERSARY-RENEW',
                'TranTypeScreenName' => 'Anniversary-Renewal',
            ]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // 🔹 Remove the inserted record
        DB::table('tb_prtrantypes')
            ->where('TranTypeCode', 'ANNIVERSARY-RENEW')
            ->delete();

        // 🔹 Revert column length (adjust old size if known)
        Schema::table('tb_prtrantypes', function (Blueprint $table) {
            $table->string('TranTypeCode', 15)->change(); // change 15 to previous length
        });
    }
}
