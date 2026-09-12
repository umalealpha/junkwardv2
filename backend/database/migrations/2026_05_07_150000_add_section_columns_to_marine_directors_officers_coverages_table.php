<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('marine_directors_officers_coverages', function (Blueprint $table) {
            $table->decimal('sum_insured', 15, 2)->nullable()->after('limit_of_liability');
            $table->json('section1_items')->nullable()->after('coverage_extensions');
            $table->json('insured_persons_listing')->nullable()->after('section1_items');
            $table->json('extra_cover_section1')->nullable()->after('insured_persons_listing');
            $table->json('section2_items')->nullable()->after('extra_cover_section1');
            $table->json('extra_cover_section2')->nullable()->after('section2_items');
            $table->json('section3_items')->nullable()->after('extra_cover_section2');
            $table->json('extra_cover_section3')->nullable()->after('section3_items');
            $table->json('extra_cover_all_sections')->nullable()->after('extra_cover_section3');
            $table->json('excess_details')->nullable()->after('extra_cover_all_sections');
            $table->text('endorsements')->nullable()->after('excess_details');
            $table->json('misc_items')->nullable()->after('endorsements');
        });
    }

    public function down(): void
    {
        Schema::table('marine_directors_officers_coverages', function (Blueprint $table) {
            $table->dropColumn([
                'sum_insured',
                'section1_items',
                'insured_persons_listing',
                'extra_cover_section1',
                'section2_items',
                'extra_cover_section2',
                'section3_items',
                'extra_cover_section3',
                'extra_cover_all_sections',
                'excess_details',
                'endorsements',
                'misc_items',
            ]);
        });
    }
};
