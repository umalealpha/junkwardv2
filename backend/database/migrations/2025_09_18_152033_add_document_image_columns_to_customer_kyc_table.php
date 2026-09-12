<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDocumentImageColumnsToCustomerKycTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('customer_kyc', function (Blueprint $table) {
            // Add missing image columns for document storage
            // Note: omang_front, omang_back, passport_back, driving_license_back already exist
            $table->string('passport_front_image')->nullable()->after('passport');
            $table->string('drivers_license_front_image')->nullable()->after('driving_license');
            $table->string('proof_of_address_image')->nullable()->after('proof_residence');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('customer_kyc', function (Blueprint $table) {
            // Drop the added image columns
            $table->dropColumn([
                'passport_front_image',
                'drivers_license_front_image',
                'proof_of_address_image'
            ]);
        });
    }
}
