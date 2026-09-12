<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reconciliation Exceptions module.
 *
 * A routine task (artisan `recon:realpay-exceptions`, scheduled weekly) writes
 * one run per execution and the exceptions it found into these tables. Finance
 * then reviews each exception in the admin UI (/finance/exceptions), changes its
 * status and leaves comments. This is the in-app home of what used to be a
 * weekly emailed Excel — kept here so the review + comment trail is auditable.
 *
 * Three tables:
 *   recon_exception_runs       one per routine run (week)
 *   recon_exceptions           one per flagged policy/contract
 *   recon_exception_comments   Finance review thread per exception
 *
 * Flags (DOMG = Domestic, COMG = Commercial policies vs RealPay mandates):
 *   A  active monthly policy with NO active RealPay mandate
 *   B  active RealPay mandate on a non-active (cancelled/deactivated) policy
 *   C  RealPay debit amount != Graphite policy premium (> P1)
 *   D  cancelled/non-active policy with an active RealPay mandate (claim-driven flagged)
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('recon_exception_runs')) {
        Schema::create('recon_exception_runs', function (Blueprint $table) {
            $table->id();
            $table->string('source', 40)->default('realpay')
                ->comment('Which routine produced this run, e.g. realpay');
            $table->string('period_label', 60)->nullable()
                ->comment('Human label, e.g. "Week of 23 Jun 2026"');
            $table->date('run_date');
            $table->enum('status', ['generating', 'ready', 'reviewing', 'closed', 'failed'])
                  ->default('generating');
            $table->integer('exception_count')->default(0);
            $table->integer('open_count')->default(0);
            $table->json('totals')->nullable()
                ->comment('Per-flag / per-product counts for the dashboard');
            $table->text('error')->nullable();
            $table->unsignedBigInteger('generated_by')->nullable()
                ->comment('users.id when triggered manually; null when by scheduler');
            $table->timestamps();
            $table->index(['source', 'status']);
            $table->unique(['source', 'run_date'], 'uniq_run_source_date');
        });
        }

        if (!Schema::hasTable('recon_exceptions')) {
        Schema::create('recon_exceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('run_id')->constrained('recon_exception_runs')->cascadeOnDelete();
            $table->enum('product', ['DOMG', 'COMG']);
            $table->enum('flag_code', ['A', 'B', 'C', 'D']);
            $table->string('flag_label', 120);
            $table->string('policy_number', 60)->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('contract_number', 100)->nullable();
            $table->enum('severity', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->decimal('graphite_value', 15, 2)->nullable()
                ->comment('Graphite monthly premium (flag C)');
            $table->decimal('realpay_value', 15, 2)->nullable()
                ->comment('RealPay latest installment (flag C)');
            $table->decimal('variance', 15, 2)->nullable()
                ->comment('realpay_value - graphite_value (flag C)');
            $table->json('detail')->nullable()
                ->comment('Full row context: status, claim_count, claim_types, dates, etc.');
            $table->enum('status', ['open', 'reviewing', 'accepted', 'disputed', 'resolved'])
                  ->default('open');
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['run_id', 'status']);
            $table->index(['product', 'flag_code']);
            $table->index(['policy_number']);
            $table->index(['severity']);
        });
        }

        if (!Schema::hasTable('recon_exception_comments')) {
        Schema::create('recon_exception_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exception_id')->constrained('recon_exceptions')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_name', 120)->nullable()
                ->comment('Snapshot of the commenter name for display without a join');
            $table->text('comment');
            $table->timestamps();
            $table->index(['exception_id']);
        });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('recon_exception_comments');
        Schema::dropIfExists('recon_exceptions');
        Schema::dropIfExists('recon_exception_runs');
    }
};
