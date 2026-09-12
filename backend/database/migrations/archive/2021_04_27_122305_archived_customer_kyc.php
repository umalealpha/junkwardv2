<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ArchivedCustomerKyc extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('archived_customer_kyc', function (Blueprint $table) {
            $table->integer('id')->nullable(false);
            $table->string('customer_id')->nullable(true);
            $table->string('omangNumber')->nullable(true);
            $table->string('passportNumber')->nullable(true);

            $table->string('driving_license')->nullable(true);
            $table->string('omang')->nullable(true);
            $table->string('omangBack')->nullable(true);
            $table->string('proof_residence')->nullable(true);

            $table->string('proof_income')->nullable(true);
            $table->string('passport')->nullable(true);
            $table->string('omangExpiry')->nullable(true);
            $table->string('passportExpiry')->nullable(true);

            $table->string('licenseExpiry')->nullable(true);
            $table->string('residenceExpiry')->nullable(true);
            $table->string('incomeExpiry')->nullable(true);
            $table->string('passportIssuingCountry')->nullable(true);

            $table->string('proof_residence_doc_type')->nullable(true);
            $table->string('proof_income_doc_type')->nullable(true);
            $table->string('omangFrontStatus')->nullable(true);
            $table->string('omangFrontRemark')->nullable(true);

            $table->string('omangBackStatus')->nullable(true);
            $table->string('omangBackRemark')->nullable(true);

            $table->string('passportStatus')->nullable(true);
            $table->string('passportRemark')->nullable(true);
            $table->string('driving_licenseStatus')->nullable(true);
            $table->string('driving_licenseRemark')->nullable(true);

            $table->string('proof_residenceStatus')->nullable(true);
            $table->string('proof_residenceRemark')->nullable(true);
            $table->string('proof_incomeStatus')->nullable(true);
            $table->string('proof_incomeRemark')->nullable(true);

            $table->string('compliance')->nullable(true);
            $table->string('status')->nullable(true);
            $table->string('remark')->nullable(true);
            $table->string('created_at')->nullable(true);
            $table->string('updated_at')->nullable(true);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('archived_customer_kyc');
    }
}
