<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FAC Register — step 3 of 5. Documents hung off a placement.
 *
 * Files go to S3 private under fac/{fac_reference}/ — same convention as Help
 * Desk and Claims.
 *
 * `payment_date` is the date being ATTESTED TO, not the upload time. A proof of
 * payment uploaded in August for a July receipt must age from July.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('fac_placement_attachments')) {
            Schema::create('fac_placement_attachments', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('fac_placement_id')->index();
                $t->enum('doc_type', [
                    'client_pop',        // proof the CLIENT paid us
                    'ri_payment_advice', // proof WE paid the reinsurer/broker
                    'fac_slip',          // the generated slip
                    'other',
                ])->default('other')->index();

                $t->string('original_name', 255);
                $t->string('path', 500);
                $t->string('mime', 120)->nullable();
                $t->unsignedBigInteger('size')->nullable();

                $t->date('payment_date')->nullable();   // the date attested to
                $t->decimal('amount', 18, 2)->nullable();
                $t->string('note', 500)->nullable();

                $t->unsignedBigInteger('uploaded_by')->nullable();
                $t->string('uploaded_by_name', 160)->nullable();
                $t->timestamps();
                $t->softDeletes();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fac_placement_attachments');
    }
};
