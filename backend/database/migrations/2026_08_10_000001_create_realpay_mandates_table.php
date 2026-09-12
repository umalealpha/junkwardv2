<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per RealPay debit-order mandate attempt.
 *
 * Why this table exists: `realpay_payment_request.contractCreated` records
 * whether we *sent* a contract to RealPay, not whether that contract is a live
 * authority to collect. An unapproved / failed / cancelled mandate is therefore
 * indistinguishable from a collecting one, which is how a policy ends up with
 * two live contracts and the customer with two debits. This table holds the
 * mandate lifecycle (docs/REALPAY_MANDATE_IMPLEMENTATION.md §2) so that state is
 * explicit and a second contract can be refused.
 *
 * It sits ALONGSIDE the existing realpay_* tables and alters none of them; it
 * links on policy_id + contract_number.
 *
 * `policy_id` is unsignedInteger (not bigInteger) to match the INT UNSIGNED
 * type applied to realpay_client_contracts.policy_id by
 * 2026_04_11_000001_fix_policy_id_columns_in_payment_tables.php — otherwise the
 * key types do not line up with the rest of the RealPay tables.
 *
 * The eMandate/DebiCheck columns (emandate_url, mandate_type,
 * max_collection_amount, redirected_at, authenticated_at) are created here but
 * stay unwritten until RealPay supplies the Express integration pack — see §4 of
 * docs/REALPAY_MANDATE_IMPLEMENTATION.md. They are included now so the
 * hosted-eMandate work does not need a second migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('realpay_mandates')) {
            Schema::create('realpay_mandates', function (Blueprint $table) {
                $table->id();

                $table->unsignedInteger('policy_id')->index();
                $table->string('policy_number', 32)->index();       // == RealPay ClientNumber
                $table->string('contract_number', 64)->nullable()->index();

                // Provider identifiers. Names confirmed against the endpoints this
                // codebase already calls (/maintain/clients, /maintain/contracts);
                // nothing here is guessed from the unpublished Express spec.
                $table->string('provider_mandate_id', 128)->nullable()->index();
                $table->string('provider_reference', 128)->nullable();

                $table->string('status', 32)->default('pending');
                $table->string('mandate_type', 8)->nullable();      // TT1 / TT2 / TT3 — pending §5
                $table->string('tracking_code', 8)->nullable();     // '44', or 'B3' for FNB

                $table->decimal('collection_amount', 12, 2)->nullable();
                $table->decimal('max_collection_amount', 12, 2)->nullable();
                $table->unsignedTinyInteger('collection_day')->nullable(); // 99 when 29/30/31
                $table->date('first_collection_date')->nullable();
                $table->string('frequency_code', 8)->default('MNTH');

                $table->text('emandate_url')->nullable();
                $table->timestamp('emandate_url_expires_at')->nullable();
                $table->timestamp('redirected_at')->nullable();
                $table->timestamp('authenticated_at')->nullable();
                $table->timestamp('first_collection_succeeded_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();

                $table->unsignedSmallInteger('attempts')->default(0);
                $table->text('last_error')->nullable();
                $table->json('provider_payload')->nullable();

                $table->timestamps();

                // Concurrency guard: two simultaneous submits for one policy race
                // here and exactly one wins, so a double-click cannot mint two
                // mandates for the same contract.
                $table->unique(['policy_id', 'contract_number'], 'uq_realpay_mandates_policy_contract');

                // Hot path for the reuse lookup: "does this policy already have a
                // mandate that is still live?"
                $table->index(['policy_id', 'status'], 'idx_realpay_mandates_policy_status');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('realpay_mandates');
    }
};
