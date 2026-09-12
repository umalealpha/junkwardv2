<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCustomerProfileColumnsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('customer_profile', function (Blueprint $table) {
            if (!Schema::hasColumn('customer_profile', 'date')) {
                $table->date('date')->nullable();
            }
            if (!Schema::hasColumn('customer_profile', 'second_address')) {
                $table->string('second_address')->after('date')->nullable();
            }
            if (!Schema::hasColumn('customer_profile', 'Insure')) {
                $table->string('Insure')->after('second_address')->nullable();
            }
            if (!Schema::hasColumn('customer_profile', 'decline_proposal')) {
                $table->string('decline_proposal')->after('Insure')->nullable();
            }
            if (!Schema::hasColumn('customer_profile', 'refused_policy')) {
                $table->string('refused_policy')->after('decline_proposal')->nullable();
            }
            if (!Schema::hasColumn('customer_profile', 'firm_member')) {
                $table->string('firm_member')->after('refused_policy')->nullable();
            }
            if (!Schema::hasColumn('customer_profile', 'books')) {
                $table->string('books')->after('firm_member')->nullable();
            }
            if (!Schema::hasColumn('customer_profile', 'about_alpha')) {
                $table->string('about_alpha')->after('books')->nullable();
            }
            if (!Schema::hasColumn('customer_profile', 'binder_date')) {
                $table->string('binder_date')->after('about_alpha')->nullable();
            }
            if (!Schema::hasColumn('customer_profile', 'select_product')) {
                $table->string('select_product')->after('binder_date')->nullable();
            }
            if (!Schema::hasColumn('customer_profile', 'estm')) {
                $table->string('estm')->after('select_product')->nullable();
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
        Schema::table('customer_profile', function (Blueprint $table) {
            $table->dropColumn('date');
            $table->dropColumn('second_address');
            $table->dropColumn('Insure');
            $table->dropColumn('decline_proposal');
            $table->dropColumn('refused_policy');
            $table->dropColumn('firm_member');
            $table->dropColumn('books');
            $table->dropColumn('about_alpha');
            $table->dropColumn('binder_date');
            $table->dropColumn('select_product');
            $table->dropColumn('estm');
        });
    }
}
