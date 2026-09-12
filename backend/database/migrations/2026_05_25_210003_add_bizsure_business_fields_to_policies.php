<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds 12 nullable structured BizSure business fields to the policies
 * table. Every column is nullable — existing rows stay NULL, no backfill
 * needed. Only populated when a request carries leadSource='bizsure'.
 */
class AddBizsureBusinessFieldsToPolicies extends Migration
{
    public function up(): void
    {
        Schema::table('policies', function (Blueprint $table) {
            if (! Schema::hasColumn('policies', 'business_name')) {
                $table->string('business_name', 255)->nullable();
            }
            if (! Schema::hasColumn('policies', 'occupation_type')) {
                $table->string('occupation_type', 100)->nullable();
            }
            if (! Schema::hasColumn('policies', 'annual_turnover')) {
                $table->decimal('annual_turnover', 14, 2)->nullable();
            }
            if (! Schema::hasColumn('policies', 'years_in_business')) {
                $table->unsignedSmallInteger('years_in_business')->nullable();
            }
            if (! Schema::hasColumn('policies', 'number_of_employees')) {
                $table->unsignedInteger('number_of_employees')->nullable();
            }
            if (! Schema::hasColumn('policies', 'floor_area_sqm')) {
                $table->unsignedInteger('floor_area_sqm')->nullable();
            }
            if (! Schema::hasColumn('policies', 'property_ownership')) {
                // 'owned' | 'rented'
                $table->string('property_ownership', 32)->nullable();
            }
            if (! Schema::hasColumn('policies', 'business_structure')) {
                // 'sole_proprietor' | 'company'
                $table->string('business_structure', 32)->nullable();
            }
            if (! Schema::hasColumn('policies', 'company_reg_number')) {
                $table->string('company_reg_number', 64)->nullable();
            }
            if (! Schema::hasColumn('policies', 'tin_number')) {
                $table->string('tin_number', 64)->nullable();
            }
            if (! Schema::hasColumn('policies', 'vat_number')) {
                $table->string('vat_number', 64)->nullable();
            }
            if (! Schema::hasColumn('policies', 'postal_address')) {
                $table->string('postal_address', 255)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('policies', function (Blueprint $table) {
            foreach ([
                'business_name',
                'occupation_type',
                'annual_turnover',
                'years_in_business',
                'number_of_employees',
                'floor_area_sqm',
                'property_ownership',
                'business_structure',
                'company_reg_number',
                'tin_number',
                'vat_number',
                'postal_address',
            ] as $col) {
                if (Schema::hasColumn('policies', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
}
