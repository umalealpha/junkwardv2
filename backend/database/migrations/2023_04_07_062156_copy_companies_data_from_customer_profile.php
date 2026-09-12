<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CopyCompaniesDataFromCustomerProfile extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasColumn('customer_profile', 'company_id')){
            Schema::table('customer_profile', function (Blueprint $table) {
                $table->unsignedBigInteger('company_id')->after('org_name')->nullable();
                $table->dropColumn('org_name');
                $table->dropColumn('vat_reg');
                $table->dropColumn('reg_no');
            });
        }

        $organisations = \AlphaDirect\CustomerProfile::OnlyOrganisation()->get();
        foreach ($organisations as $key => $organisation){

            if ($organisation->org_name and $organisation->vat_reg and $organisation->reg_no)
            {
                // create new company
                $company = new \AlphaDirect\Models\Company();
                $company->name = $organisation->org_name;
                $company->VAT_registration_number = $organisation->vat_reg;
                $company->company_registration_number = $organisation->reg_no;
                $company->created_by = 0;
                $company->save();

                // assign company id to customer
                $organisation->company_id = $company->id;
                $organisation->save();
            }
        }


    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('customer_profile', function (Blueprint $table) {
            $table->string('org_name')->nullable();
            $table->string('vat_reg')->nullable();
            $table->string('reg_no')->nullable();
        });

        if (Schema::hasColumn('customer_profile', 'company_id')){
            Schema::table('customer_profile', function (Blueprint $table) {
                $table->dropColumn('company_id');
            });
        }
    }
}
