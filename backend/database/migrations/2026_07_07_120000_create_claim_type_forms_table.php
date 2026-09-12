<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Maps each claim_type to the blank claim form / letter that should be
 * emailed to the claimant the moment a claim is registered.
 *
 * The claims team owns the CONTENT: they set form_url (the S3 / public link
 * to the form) and flip `active` on. Rows seeded here are inert placeholders
 * (active = 0, no url) so the team has the common types ready to fill in.
 *
 * Idempotent (pr-guard): guarded create + insertOrIgnore, safe to re-run.
 */
class CreateClaimTypeFormsTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('claim_type_forms')) {
            Schema::create('claim_type_forms', function (Blueprint $table) {
                $table->id();
                $table->string('claim_type', 100)->unique();   // e.g. 'Accident', 'Theft'
                $table->string('form_title', 200);              // shown in the email subject/body
                $table->string('form_url', 1000)->nullable();   // S3 / public link to the blank form
                $table->text('instructions')->nullable();       // extra guidance added to the email
                $table->boolean('active')->default(false);      // off until the claims team fills the url
                $table->timestamps();
            });
        }

        // Seed the common types as inert placeholders (active = 0). The claims
        // team edits form_url + form_title and flips active on. insertOrIgnore
        // keeps this idempotent against the unique claim_type index.
        if (Schema::hasTable('claim_type_forms')) {
            $now  = now();
            $seed = ['Accident', 'Motor', 'Theft', 'Glass', 'Life', 'Cellphone', 'Third Party', 'Key Loss'];
            foreach ($seed as $type) {
                DB::table('claim_type_forms')->insertOrIgnore([
                    'claim_type'  => $type,
                    'form_title'  => $type . ' Claim Form',
                    'form_url'    => null,
                    'active'      => 0,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ]);
            }
        }
    }

    public function down()
    {
        Schema::dropIfExists('claim_type_forms');
    }
}
