<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ad_group_kyc_submissions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('policy_id');
            $table->string('employer_group_id', 191)->nullable();

            // General/company details
            $table->text('form_last_completed')->nullable();
            $table->text('company_name')->nullable();
            $table->text('registration_no')->nullable();
            $table->text('tin_number')->nullable();
            $table->text('vat_number')->nullable();
            $table->text('country_of_incorporation')->nullable();
            $table->text('corporate_email')->nullable();
            $table->text('postal_address')->nullable();
            $table->text('corporate_physical_address')->nullable();
            $table->text('website')->nullable();
            $table->text('corporate_telephone')->nullable();
            $table->text('type_of_business')->nullable();
            $table->text('business_description')->nullable();

            // Primary contact
            $table->text('contact_title')->nullable();
            $table->text('contact_names')->nullable();
            $table->text('contact_surname')->nullable();
            $table->date('contact_date_of_birth')->nullable();
            $table->text('contact_national_id')->nullable();
            $table->text('contact_nationality')->nullable();
            $table->text('contact_position')->nullable();
            $table->text('contact_email')->nullable();
            $table->text('contact_telephone')->nullable();
            $table->text('contact_fax')->nullable();
            $table->text('contact_physical_address')->nullable();
            $table->text('contact_village')->nullable();
            $table->text('contact_country')->nullable();

            // Additional contact
            $table->text('additional_contact_name')->nullable();
            $table->text('additional_contact_surname')->nullable();
            $table->text('additional_contact_telephone')->nullable();
            $table->text('additional_contact_mobile')->nullable();
            $table->text('additional_contact_email')->nullable();

            // Banking
            $table->text('account_name')->nullable();
            $table->text('account_number')->nullable();
            $table->text('bank_name')->nullable();
            $table->text('bank_branch')->nullable();
            $table->text('branch_code')->nullable();

            // Risk flags
            $table->text('high_risk_country_involvement')->nullable();
            $table->text('high_risk_country_details')->nullable();
            $table->text('complex_ownership_structure')->nullable();
            $table->text('complex_ownership_details')->nullable();
            $table->text('other_high_risk_indicators')->nullable();

            // Declaration
            $table->boolean('consent')->default(false);
            $table->text('declaration_full_name')->nullable();
            $table->text('declaration_designation')->nullable();
            $table->date('declaration_date')->nullable();
            $table->text('declaration_place')->nullable();
            $table->text('declaration_signature')->nullable();

            $table->timestamps();

            $table->index(['customer_id']);
            $table->index(['policy_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_group_kyc_submissions');
    }
};


