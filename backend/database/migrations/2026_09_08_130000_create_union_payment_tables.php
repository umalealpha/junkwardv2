<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Union group scheme — monthly premium collection evidence (BONU brief, 2026-09-08).
 *
 *  union_member_payments — one row per member per month from the union's
 *      monthly payment list (imported spreadsheet or keyed manually). Claims
 *      reads it to show whether the member had paid for the month before the
 *      claim is processed.
 *  union_payment_proofs  — the union's proof-of-payment files (bank
 *      confirmation, remittance schedule) per month, on S3, visible to
 *      Underwriting, Accounts and Claims.
 */
class CreateUnionPaymentTables extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('union_member_payments')) {
            Schema::create('union_member_payments', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('union_id')->index();
                // Null when the payment list names an ID we have no member for
                // (kept so Accounts can see who paid but is not on the roster).
                $table->unsignedBigInteger('union_member_id')->nullable()->index();
                $table->string('id_number', 50);
                $table->string('member_name')->nullable();
                // Calendar month the premium covers, 'YYYY-MM'.
                $table->char('period', 7);
                $table->decimal('amount', 12, 2)->nullable();
                $table->date('paid_on')->nullable();
                $table->string('reference', 100)->nullable();
                $table->string('source', 20)->default('import'); // import | manual
                $table->string('batch_id', 40)->nullable()->index();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->unique(['union_id', 'id_number', 'period'], 'ump_union_id_period_unique');
                $table->index(['union_id', 'period'], 'ump_union_period_idx');
                $table->index(['union_member_id', 'period'], 'ump_member_period_idx');
            });
        }

        if (!Schema::hasTable('union_payment_proofs')) {
            Schema::create('union_payment_proofs', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('union_id')->index();
                $table->char('period', 7);
                $table->string('original_name');
                $table->string('path', 500);
                $table->string('mime', 100)->nullable();
                $table->unsignedBigInteger('size')->nullable();
                $table->decimal('amount', 14, 2)->nullable();
                $table->string('note', 500)->nullable();
                $table->unsignedBigInteger('uploaded_by')->nullable();
                $table->string('uploaded_by_name')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['union_id', 'period'], 'upp_union_period_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('union_payment_proofs');
        Schema::dropIfExists('union_member_payments');
    }
}
