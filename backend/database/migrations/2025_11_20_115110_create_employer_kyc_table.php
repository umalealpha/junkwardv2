<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEmployerKycTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('employer_kyc', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedBigInteger('employer_group_id')->nullable();
            // $table->unsignedInteger('user_id')->nullable();
            
            // Employer Group Document file paths
            $table->string('certificate_of_incorporation_file')->nullable();
            $table->string('proof_of_business_address_file')->nullable();
            $table->string('ownership_control_structure_file')->nullable();
            $table->string('proof_residential_address_directors_file')->nullable();
            $table->string('senior_managing_officer_id_file')->nullable();
            $table->string('certified_id_passport_directors_file')->nullable();
            $table->string('resolution_authorised_persons_file')->nullable();
            $table->string('id_authorised_persons_file')->nullable();
            $table->string('trading_license_file')->nullable();
            $table->string('tax_vat_registration_file')->nullable();
            $table->string('proof_source_funds_file')->nullable();
            $table->string('bank_confirmation_letter_file')->nullable();
            $table->string('tax_clearance_certificate_file')->nullable();
            
            // // Bank documents
            // $table->string('bank_statement_file_path')->nullable();
            // $table->string('debit_authorization_form')->nullable();
            // $table->integer('bankStatementFileStatus')->nullable();
            // $table->text('bankStatementFileRemark')->nullable();
            
            // Status fields for each document
            $table->string('status')->nullable();
            $table->integer('certificate_of_incorporation_status')->nullable();
            $table->text('certificate_of_incorporation_remark')->nullable();
            $table->integer('proof_of_business_address_status')->nullable();
            $table->text('proof_of_business_address_remark')->nullable();
            $table->integer('ownership_control_structure_status')->nullable();
            $table->text('ownership_control_structure_remark')->nullable();
            $table->integer('proof_residential_address_directors_status')->nullable();
            $table->text('proof_residential_address_directors_remark')->nullable();
            $table->integer('senior_managing_officer_id_status')->nullable();
            $table->text('senior_managing_officer_id_remark')->nullable();
            $table->integer('certified_id_passport_directors_status')->nullable();
            $table->text('certified_id_passport_directors_remark')->nullable();
            $table->integer('resolution_authorised_persons_status')->nullable();
            $table->text('resolution_authorised_persons_remark')->nullable();
            $table->integer('id_authorised_persons_status')->nullable();
            $table->text('id_authorised_persons_remark')->nullable();
            $table->integer('trading_license_status')->nullable();
            $table->text('trading_license_remark')->nullable();
            $table->integer('tax_vat_registration_status')->nullable();
            $table->text('tax_vat_registration_remark')->nullable();
            $table->integer('proof_source_funds_status')->nullable();
            $table->text('proof_source_funds_remark')->nullable();
            $table->integer('bank_confirmation_letter_status')->nullable();
            $table->text('bank_confirmation_letter_remark')->nullable();
            $table->integer('tax_clearance_certificate_status')->nullable();
            $table->text('tax_clearance_certificate_remark')->nullable();
            
            // Compliance
            $table->boolean('compliance')->default(0);
            
            // Foreign keys
            $table->foreign('employer_group_id')->references('id')->on('employer_groups')->onDelete('cascade');
            // $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            
            $table->timestamps();
            
            // Indexes
            $table->index('employer_group_id');
            // $table->index('user_id');
            $table->index('compliance');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('employer_kyc');
    }
}
