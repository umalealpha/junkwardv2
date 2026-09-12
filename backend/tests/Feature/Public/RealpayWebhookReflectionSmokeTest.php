<?php

namespace Tests\Feature\Public;

use AlphaDirect\PaymentTransaction;
use AlphaDirect\Policy;
use AlphaDirect\RealpayClientContracts;
use AlphaDirect\RealpayContractInstallments;
use AlphaDirect\RealpayReflectionException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * END-TO-END SMOKE TEST for the RealPay payment-reflection chain.
 *
 * RealpayPaymentReflectionTest exercises the recorder in isolation. This one
 * drives the whole delivery path the way production does:
 *
 *     webhook_buffer row
 *       → php artisan webhook:process-buffer
 *         → RealPayController::updateInstallment()
 *           → RealpayPaymentRecorder
 *             → payment_transactions
 *
 * That chain is where the reported failure actually lived: every individual
 * piece "worked", and the delivery was still consumed and discarded. So the
 * assertions here are as much about the BUFFER ROW's fate as the payment's —
 * an unapplied delivery that gets marked 'processed' is the bug, regardless of
 * what any single class returned.
 *
 * Runs against a throwaway file-backed SQLite database. File-backed, not
 * `:memory:`, because the code under test spans three connection names
 * ('mysql' for PaymentTransaction, 'mysql_write' for the buffer, and the
 * default for everything else) and separate `:memory:` handles would be three
 * separate databases.
 *
 * NOTE ON SCOPE: SQLite cannot catch MySQL-specific DDL problems. The migration
 * is exercised here for shape and behaviour only. There is no local MySQL on
 * this machine (127.0.0.1:3306 refuses connections), so the DDL itself must be
 * verified when the migration runs in a real environment.
 */
class RealpayWebhookReflectionSmokeTest extends TestCase
{
    private const CONNECTIONS = ['mysql', 'mysql_write', 'mysql_system'];

    private string $dbPath;
    private array $previousConfig = [];
    private string $previousDefault;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('audit.enabled', false);
        Policy::disableAuditing();
        PaymentTransaction::disableAuditing();
        RealpayClientContracts::disableAuditing();
        RealpayContractInstallments::disableAuditing();

        $this->dbPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rp_smoke_' . getmypid() . '.sqlite';
        if (file_exists($this->dbPath)) {
            unlink($this->dbPath);
        }
        touch($this->dbPath);

        $sqlite = [
            'driver'                  => 'sqlite',
            'database'                => $this->dbPath,
            'prefix'                  => '',
            'foreign_key_constraints' => false,
        ];

        $this->previousDefault = (string) Config::get('database.default');
        foreach (self::CONNECTIONS as $name) {
            $this->previousConfig[$name] = Config::get('database.connections.' . $name);
            Config::set('database.connections.' . $name, $sqlite);
            DB::purge($name);
        }
        Config::set('database.default', 'mysql');

        $this->buildSchema();
    }

    protected function tearDown(): void
    {
        foreach (self::CONNECTIONS as $name) {
            DB::purge($name);
            Config::set('database.connections.' . $name, $this->previousConfig[$name]);
        }
        Config::set('database.default', $this->previousDefault);

        if (file_exists($this->dbPath)) {
            @unlink($this->dbPath);
        }

        parent::tearDown();
    }

    // ──────────────────────────────────────────────────────────────
    // SMOKE 1 — the happy path, end to end
    // ──────────────────────────────────────────────────────────────

    public function test_a_collected_debit_flows_from_buffer_to_payment_record(): void
    {
        $policy = $this->makePolicy('MIS2026215341');
        $this->makeContract($policy, 'MIS2026215341', $policy->id . '/1');
        $this->makeScheduledInstallment($policy, 'MIS2026215341', $policy->id . '/1', '10524831620001');

        $bufferId = $this->bufferWebhook([
            'ClientNumber'   => 'MIS2026215341',
            'ContractNumber' => $policy->id . '/1',
        ]);

        $this->runCommand('webhook:process-buffer', ['--source' => 'realpay']);

        $payment = PaymentTransaction::where('referenceNumber', '10524831620001')->first();
        $this->assertNotNull($payment, 'The collected debit must reach payment_transactions.');
        $this->assertSame('SUCCESS', $payment->status);
        $this->assertSame('MIS2026215341', $payment->policyNumber);
        $this->assertSame((int) $policy->id, (int) $payment->policy_id);
        $this->assertSame('RealPay', $payment->paymentMethod);

        $this->assertSame('processed', $this->bufferStatus($bufferId),
            'A delivery that WAS applied should be consumed.');

        // The instalment is marked collected, and the policy activated — the
        // existing behaviour this change had to leave intact.
        $this->assertSame('S', RealpayContractInstallments::where('InstalmentReferenceNumber', '10524831620001')->first()->InstalmentStatus);
        $this->assertSame(1, (int) Policy::find($policy->id)->status);
    }

    /**
     * A collected debit whose payload carries no TrackingCode must still be
     * recorded.
     *
     * This is the reported "shows under RealPay Transactions, missing from the
     * Transaction Log" case. updateInstallment() used to gate ALL of its work —
     * instalment update, policy activation and the payment write — behind a
     * check that required every extracted field including TrackingCode, which
     * is optional in RealPay's payload. A collected instalment without one fell
     * to `return ...json(..., 401)`: no payment row, no activation, and (being
     * a non-5xx) nothing on the exception ledger for the daily
     * `--source=exceptions` report to surface. The customer was debited and
     * Graphite held no trace.
     */
    public function test_a_collected_debit_with_no_tracking_code_is_still_recorded(): void
    {
        $policy = $this->makePolicy('MIS2026215414');
        $this->makeContract($policy, 'MIS2026215414', $policy->id . '/1');
        $this->makeScheduledInstallment($policy, 'MIS2026215414', $policy->id . '/1', 'SMOKE-NOTRACK-001');

        $bufferId = $this->bufferWebhook([
            'ClientNumber'              => 'MIS2026215414',
            'ContractNumber'            => $policy->id . '/1',
            'InstalmentReferenceNumber' => 'SMOKE-NOTRACK-001',
            'TrackingCode'              => null,
        ]);

        $this->runCommand('webhook:process-buffer', ['--source' => 'realpay']);

        $payment = PaymentTransaction::where('referenceNumber', 'SMOKE-NOTRACK-001')->first();
        $this->assertNotNull($payment,
            'A collected debit with no TrackingCode must still reach payment_transactions.');
        $this->assertSame('SUCCESS', $payment->status);
        $this->assertSame('MIS2026215414', $payment->policyNumber);
        $this->assertSame((int) $policy->id, (int) $payment->policy_id);

        $this->assertSame('S',
            RealpayContractInstallments::where('InstalmentReferenceNumber', 'SMOKE-NOTRACK-001')->first()->InstalmentStatus,
            'The instalment must be marked collected.');
        $this->assertSame(1, (int) Policy::find($policy->id)->status,
            'The policy must go Active once the collection is recorded.');
        $this->assertSame('processed', $this->bufferStatus($bufferId),
            'The delivery WAS applied, so it should be consumed rather than retried.');
    }

    /**
     * A settling delivery that genuinely cannot be recorded must become an open
     * item, not a silent 401.
     *
     * Here the amount is absent, so there is no safe payment to write. The
     * delivery must be reported unapplied (so it is retried rather than
     * consumed) AND land on realpay_reflection_exceptions — the table the
     * scheduled `realpay:reconcile-reflection --source=exceptions` report
     * reads. Before the fix this returned 401 with nothing ledgered, which is
     * why that table sat empty while collected debits went unrecorded.
     *
     * The reference IS present here on purpose: it is the ledger's key, and a
     * delivery without one is deliberately preserved in the log only (see
     * RealpayPaymentRecorder::recordException) because an unkeyable row could
     * not be de-duplicated on replay.
     */
    public function test_an_incomplete_settling_delivery_becomes_an_open_item(): void
    {
        $policy = $this->makePolicy('MIS2026215542');
        $this->makeContract($policy, 'MIS2026215542', $policy->id . '/1');

        $bufferId = $this->bufferWebhook([
            'ClientNumber'              => 'MIS2026215542',
            'ContractNumber'            => $policy->id . '/1',
            'InstalmentReferenceNumber' => 'SMOKE-NOAMOUNT-001',
            'InstalmentAmount'          => null,
        ]);

        $this->runCommand('webhook:process-buffer', ['--source' => 'realpay']);

        $this->assertNotSame('processed', $this->bufferStatus($bufferId),
            'A delivery that was NOT applied must never be consumed.');

        $this->assertTrue(
            RealpayReflectionException::where('instalment_reference', 'SMOKE-NOAMOUNT-001')
                ->whereNull('resolved_at')->exists(),
            'An unrecordable collected debit must leave an open item to reconcile from.'
        );
    }

    // ──────────────────────────────────────────────────────────────
    // SMOKE 2 — the reported failure, and the fix
    // ──────────────────────────────────────────────────────────────

    public function test_an_unattachable_debit_is_not_consumed_and_becomes_an_open_item(): void
    {
        // No policy, no contract — the shape that produced "RealPay debited,
        // Graphite has nothing". Previously: no payment row, and the buffer row
        // marked 'processed' so the notification was gone for good.
        $bufferId = $this->bufferWebhook([
            'ClientNumber'              => 'MIS9999999999',
            'ContractNumber'            => '9999999/1',
            'InstalmentReferenceNumber' => 'SMOKE-ORPHAN-001',
        ]);

        $this->runCommand('webhook:process-buffer', ['--source' => 'realpay']);

        $this->assertSame(0, PaymentTransaction::where('referenceNumber', 'SMOKE-ORPHAN-001')->count());

        $this->assertNotSame('processed', $this->bufferStatus($bufferId),
            'An unapplied delivery must NOT be marked processed — that is what made the loss permanent.');
        $this->assertSame(1, $this->bufferAttempts($bufferId), 'It should have been attempted and left for retry.');

        $exception = RealpayReflectionException::where('instalment_reference', 'SMOKE-ORPHAN-001')->first();
        $this->assertNotNull($exception, 'The debit must land on the reconciliation ledger.');
        $this->assertNull($exception->resolved_at);
    }

    public function test_a_delivery_that_keeps_failing_is_parked_as_failed_not_processed(): void
    {
        $bufferId = $this->bufferWebhook([
            'ClientNumber'              => 'MIS9999999998',
            'ContractNumber'            => '9999998/1',
            'InstalmentReferenceNumber' => 'SMOKE-ORPHAN-002',
        ]);

        // Drain repeatedly with a low attempt ceiling, as the scheduler would
        // over successive runs.
        $this->runCommand('webhook:process-buffer', ['--source' => 'realpay', '--max-attempts' => 2]);
        $this->runCommand('webhook:process-buffer', ['--source' => 'realpay', '--max-attempts' => 2]);

        $this->assertSame('failed', $this->bufferStatus($bufferId),
            'Exhausted retries must park the row visibly, never silently succeed.');
    }

    // ──────────────────────────────────────────────────────────────
    // SMOKE 3 — replay safety on the real path
    // ──────────────────────────────────────────────────────────────

    public function test_replaying_the_same_delivery_does_not_duplicate_the_payment(): void
    {
        $policy = $this->makePolicy('MIS2026215350');
        $this->makeContract($policy, 'MIS2026215350', $policy->id . '/1');
        $this->makeScheduledInstallment($policy, 'MIS2026215350', $policy->id . '/1', 'SMOKE-REPLAY-001');

        // Three separate deliveries of the same debit — a RealPay redelivery
        // plus the every-minute drain picking each of them up.
        foreach (range(1, 3) as $ignored) {
            $this->bufferWebhook([
                'ClientNumber'              => 'MIS2026215350',
                'ContractNumber'            => $policy->id . '/1',
                'InstalmentReferenceNumber' => 'SMOKE-REPLAY-001',
            ]);
        }

        $this->runCommand('webhook:process-buffer', ['--source' => 'realpay']);

        $this->assertSame(1, PaymentTransaction::where('referenceNumber', 'SMOKE-REPLAY-001')->count(),
            'Three deliveries of one debit must leave exactly one payment record.');
        $this->assertSame(1, RealpayContractInstallments::where('InstalmentReferenceNumber', 'SMOKE-REPLAY-001')->count());
        $this->assertSame(3, DB::connection('mysql_write')->table('webhook_buffer')->where('status', 'processed')->count());
    }

    // ──────────────────────────────────────────────────────────────
    // SMOKE 4 — the reconciliation tooling, end to end
    // ──────────────────────────────────────────────────────────────

    public function test_reconcile_command_reports_then_repairs_an_open_item(): void
    {
        // A debit that arrived before its policy existed.
        $this->bufferWebhook([
            'ClientNumber'              => 'MIS2026214543',
            'ContractNumber'            => 'MIS2026214543/1',
            'InstalmentReferenceNumber' => 'SMOKE-RECON-001',
            'InstalmentAmount'          => '512.75',
        ]);
        $this->runCommand('webhook:process-buffer', ['--source' => 'realpay']);

        $this->assertSame(1, RealpayReflectionException::open()->count());

        // Report mode: names it, writes nothing. Artisan::call with an explicit
        // buffer — $this->artisan() swaps the console output for its own
        // expectation harness, so the rendered text is not readable from there.
        $report = $this->runCommand('realpay:reconcile-reflection', ['--source' => 'exceptions']);
        $this->assertStringContainsString('SMOKE-RECON-001', $report,
            'The report must name the outstanding reference so an operator can act on it.');
        $this->assertSame(0, PaymentTransaction::count(), 'Report mode must not write.');

        // The policy now exists (materialisation caught up / ops corrected it).
        $policy = $this->makePolicy('MIS2026214543');

        $this->runCommand('realpay:reconcile-reflection', ['--source' => 'exceptions', '--commit' => true]);

        $payment = PaymentTransaction::where('referenceNumber', 'SMOKE-RECON-001')->first();
        $this->assertNotNull($payment, 'The repair must write the missing payment.');
        $this->assertSame('MIS2026214543', $payment->policyNumber);
        $this->assertEqualsWithDelta(512.75, (float) $payment->amount, 0.001);
        // Recovered payments must reach the statement…
        $this->assertSame(0, (int) $payment->is_ledger);
        $this->assertNotNull($payment->new_payment_date);
        // …without re-notifying the customer.
        $this->assertSame(0, (int) $payment->request_type);

        $this->assertSame(0, RealpayReflectionException::open()->count(),
            'Once repaired, the item must stop being outstanding.');
    }

    public function test_reconcile_commit_is_safe_to_run_twice(): void
    {
        $policy = $this->makePolicy('MIS2026215336');
        $this->makeContract($policy, 'MIS2026215336', $policy->id . '/1');
        $this->makeScheduledInstallment($policy, 'MIS2026215336', $policy->id . '/1', 'SMOKE-TWICE-001', 'S');

        $this->runCommand('realpay:reconcile-reflection', ['--source' => 'installments', '--commit' => true]);
        $this->runCommand('realpay:reconcile-reflection', ['--source' => 'installments', '--commit' => true]);

        $this->assertSame(1, PaymentTransaction::where('referenceNumber', 'SMOKE-TWICE-001')->count(),
            'A second repair run must not duplicate the payment.');
    }

    // ──────────────────────────────────────────────────────────────
    // SMOKE 5 — the migration
    // ──────────────────────────────────────────────────────────────

    public function test_the_reflection_exceptions_migration_creates_a_usable_table(): void
    {
        // buildSchema() creates this table from the migration file itself, so
        // reaching here at all means the migration ran. Assert the columns the
        // recorder and the reconcile command depend on.
        foreach ([
            'instalment_reference', 'instalment_sequence', 'client_number',
            'contract_number', 'policy_number', 'policy_id', 'instalment_status',
            'amount', 'action_date', 'reason', 'error', 'payload', 'occurrences',
            'resolved_at', 'resolved_by',
        ] as $column) {
            $this->assertTrue(
                Schema::connection('mysql')->hasColumn('realpay_reflection_exceptions', $column),
                "realpay_reflection_exceptions is missing '{$column}'."
            );
        }
    }

    // ──────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────

    /** A RealPay instalment webhook body, as WebhookBuffer stores it. */
    private function bufferWebhook(array $overrides = []): int
    {
        $payload = ['InstalmentGetResponse' => [array_merge([
            'ClientNumber'              => 'MIS2026215341',
            'ContractNumber'            => '215341/1',
            'InstalmentReferenceNumber' => '10524831620001',
            'InstalmentStatus'          => 'S',
            'InstalmentActionDate'      => '2026-07-24',
            'TrackingCode'              => '44',
            'InstalmentAmount'          => '288.04',
            'InstalmentSequence'        => '1',
            'ResponseCode'              => '00',
        ], $overrides)]];

        return (int) DB::connection('mysql_write')->table('webhook_buffer')->insertGetId([
            'source'     => 'realpay',
            'event_type' => 'payment_success',
            'payload'    => json_encode($payload),
            'status'     => 'pending',
            'attempts'   => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function bufferStatus(int $id): string
    {
        return (string) DB::connection('mysql_write')->table('webhook_buffer')->where('id', $id)->value('status');
    }

    private function bufferAttempts(int $id): int
    {
        return (int) DB::connection('mysql_write')->table('webhook_buffer')->where('id', $id)->value('attempts');
    }

    /**
     * Run an artisan command and return its rendered output.
     *
     * Artisan::call with an explicit buffer rather than $this->artisan(), which
     * swaps the console output for its own expectation harness and leaves the
     * rendered text unreadable.
     */
    private function runCommand(string $command, array $parameters = []): string
    {
        $buffer = new \Symfony\Component\Console\Output\BufferedOutput();
        $exit = \Artisan::call($command, $parameters, $buffer);
        $this->assertSame(0, $exit, "artisan {$command} exited non-zero.");

        return $buffer->fetch();
    }

    private function makePolicy(string $policyNumber): Policy
    {
        $policy = new Policy();
        $policy->policyNumber = $policyNumber;
        $policy->product_id   = 2;
        $policy->customer_id  = 1;
        $policy->status       = 0;
        $policy->save();

        return $policy;
    }

    private function makeContract(Policy $policy, string $clientNumber, string $contractNumber): void
    {
        $contract = new RealpayClientContracts();
        $contract->policy_id       = $policy->id;
        $contract->client_number   = $clientNumber;
        $contract->contract_number = $contractNumber;
        $contract->status          = 1;
        $contract->save();
    }

    /** The instalment row RealPay's contract-create response seeds locally. */
    private function makeScheduledInstallment(Policy $policy, string $clientNumber, string $contractNumber, string $reference, string $status = 'A'): void
    {
        $row = new RealpayContractInstallments();
        $row->policy_id                 = $policy->id;
        $row->clientNumber              = $clientNumber;
        $row->contractNumber            = $contractNumber;
        $row->InstalmentReferenceNumber = $reference;
        $row->InstalmentSequence        = '1';
        $row->InstalmentActionDate      = '2026-07-24';
        $row->TrackingCode              = '44';
        $row->InstalmentAmount          = '288.04';
        $row->InstalmentStatus          = $status;
        $row->save();
    }

    private function buildSchema(): void
    {
        $schema = Schema::connection('mysql');

        $schema->create('policies', function (Blueprint $table) {
            $table->increments('id');
            $table->string('policyNumber')->nullable();
            $table->unsignedInteger('product_id')->nullable();
            $table->unsignedInteger('customer_id')->nullable();
            $table->integer('status')->nullable();
            $table->date('term_start_date')->nullable();
            $table->date('term_end_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->date('policyActivatedDate')->nullable();
            $table->timestamps();
        });

        $schema->create('payment_transactions', function (Blueprint $table) {
            $table->increments('id');
            $table->string('policyNumber');
            $table->unsignedInteger('policy_id')->nullable();
            $table->string('referenceNumber');
            $table->string('amount')->nullable();
            $table->string('status')->nullable();
            $table->string('paymentDate')->nullable();
            $table->date('new_payment_date')->nullable();
            $table->string('paymentMethod')->nullable();
            $table->string('note', 500)->nullable();
            $table->integer('is_ledger')->nullable()->default(0);
            $table->string('numberOfInstalmentsPaid')->nullable();
            $table->string('paymentFrequency')->nullable();
            $table->integer('request_type')->nullable()->default(0);
            $table->string('deleted_at')->nullable();
            $table->timestamps();
        });

        $schema->create('realpay_client_contracts', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('policy_id')->nullable();
            $table->string('client_number')->nullable();
            $table->string('contract_number')->nullable();
            $table->string('cellphone')->nullable();
            $table->integer('status')->nullable();
            $table->timestamps();
        });

        $schema->create('realpay_contract_installments', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('policy_id')->nullable();
            $table->string('clientNumber')->nullable();
            $table->string('contractNumber')->nullable();
            $table->string('InstalmentReferenceNumber')->nullable();
            $table->string('InstalmentSequence')->nullable();
            $table->string('CTCAmount')->nullable();
            $table->string('InstalmentActionDate')->nullable();
            $table->string('TrackingCode')->nullable();
            $table->string('InstalmentAmount')->nullable();
            $table->string('InstalmentStatus')->nullable();
            $table->string('instalmentResponse')->nullable();
            $table->integer('retry_count')->nullable()->default(0);
            $table->timestamps();
        });

        // Written directly by updateInstallment() (the legacy `transactions`
        // summary row) and by its webhook audit log.
        $schema->create('transactions', function (Blueprint $table) {
            $table->increments('id');
            $table->string('policyNumber')->nullable();
            $table->string('amount')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
        });

        $schema->create('realpay_webhook_response', function (Blueprint $table) {
            $table->increments('id');
            $table->string('policyNumber')->nullable();
            $table->string('instalmentSequence')->nullable();
            $table->string('instalmentActionDate')->nullable();
            $table->string('instalmentRefNumber')->nullable();
            $table->string('installmentAmount')->nullable();
            $table->string('bankResponse')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
        });

        // PolicyStatusLogs — legacy table name.
        $schema->create('policyactivatecancelleddates', function (Blueprint $table) {
            $table->increments('id');
            $table->string('policyNumber')->nullable();
            $table->date('activated_date')->nullable();
            $table->date('cancelled_date')->nullable();
            $table->timestamps();
        });

        // OneTimePaymentURL — read on the notification path after the payment
        // write. Present so the smoke test exercises that path rather than
        // tripping the notification try/catch and skipping it.
        $schema->create('one_time_payment_link', function (Blueprint $table) {
            $table->increments('id');
            $table->string('policyNumber')->nullable();
            $table->string('url')->nullable();
            $table->timestamps();
        });

        $schema->create('email_broadcasting', function (Blueprint $table) {
            $table->increments('id');
            $table->string('hook_slug')->nullable();
            $table->string('subject')->nullable();
            $table->timestamps();
        });

        $schema->create('policy_term', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('policy_id')->nullable();
            $table->string('trans_type')->nullable();
            $table->string('status')->nullable();
            $table->date('term_end_date')->nullable();
            $table->timestamps();
        });

        $schema->create('customer', function (Blueprint $table) {
            $table->increments('id');
            $table->string('firstName')->nullable();
            $table->string('lastName')->nullable();
            $table->string('cellphone')->nullable();
            $table->string('email')->nullable();
            $table->timestamps();
        });

        $schema->create('webhook_buffer', function (Blueprint $table) {
            $table->increments('id');
            $table->string('source');
            $table->string('event_type')->nullable();
            $table->text('payload');
            $table->string('status')->default('pending');
            $table->integer('attempts')->default(0);
            $table->timestamps();
        });

        $schema->create('activity_log', function (Blueprint $table) {
            $table->increments('id');
            $table->string('log_name')->nullable();
            $table->text('description')->nullable();
            $table->string('subject_type')->nullable();
            $table->unsignedInteger('subject_id')->nullable();
            $table->string('causer_type')->nullable();
            $table->unsignedInteger('causer_id')->nullable();
            $table->text('properties')->nullable();
            $table->string('event')->nullable();
            $table->uuid('batch_uuid')->nullable();
            $table->timestamps();
        });

        // The table under test, created from the real migration so the smoke
        // test fails if the migration and the code drift apart.
        $migration = require database_path('migrations/2026_08_13_000000_create_realpay_reflection_exceptions_table.php');
        $migration->up();
    }
}
