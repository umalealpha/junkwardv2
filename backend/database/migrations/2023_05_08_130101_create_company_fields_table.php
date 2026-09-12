<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCompanyFieldsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('companies', function (Blueprint $table) {

            if (!Schema::hasColumn('companies', 'head_office_physical_address')) {
                $table->string('head_office_physical_address')->nullable();
            }
            if (!Schema::hasColumn('companies', 'postal_address')) {
                $table->string('postal_address')->nullable();
            }
            if (!Schema::hasColumn('companies', 'city')) {
                $table->string('city')->nullable();
            }
            if (!Schema::hasColumn('companies', 'state')) {
                $table->string('state')->nullable();
            }
            if (!Schema::hasColumn('companies', 'pincode')) {
                $table->string('pincode')->nullable();
            }
            if (!Schema::hasColumn('companies', 'contact_person')) {
                $table->string('contact_person')->nullable();
            }
            if (!Schema::hasColumn('companies', 'contact_person_number')) {
                $table->string('contact_person_number')->nullable();
            }
            if (!Schema::hasColumn('companies', 'primary_email')) {
                $table->string('primary_email')->nullable();
            }
            if (!Schema::hasColumn('companies', 'secondary_email')) {
                $table->string('secondary_email')->nullable();
            }
            if (!Schema::hasColumn('companies', 'broker_email')) {
                $table->string('broker_email')->nullable();
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
        // Schema::dropIfExists('companies');
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('head_office_physical_address');
            $table->dropColumn('postal_address');
            $table->dropColumn('city');
            $table->dropColumn('state');
            $table->dropColumn('pincode');
            $table->dropColumn('contact_person');
            $table->dropColumn('contact_person_number');
            $table->dropColumn('primary_email');
            $table->dropColumn('secondary_email');
            $table->dropColumn('broker_email');
        });
    }
}
